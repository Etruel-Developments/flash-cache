<?php

/**
 * What the cache holds, by kind. Counters follow every file written or deleted through
 * flash_cache_write_file() / flash_cache_delete_file() and reach the database once per request,
 * as atomic UPDATEs. The General Settings screen corrects them with a recount, run in short AJAX
 * steps from the browser when the last one is older than RECOUNT_AFTER: no cron and no waiting.
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

class flash_cache_stats {

	const KINDS			= array('static', 'php', 'styles', 'scripts', 'fonts', 'other');
	const RECOUNT_AFTER = 3600;

	private static $delta = array();

	public static function hooks() {
		add_action('wp_ajax_flash_cache_recount', array(__CLASS__, 'ajax_recount'));
	}

	/**
	 * The kind of a file in the cache folder and whether it counts as an item (a page or a
	 * combined file). Null for files that are not cache contents.
	 */
	public static function classify($path) {
		$root = wp_normalize_path(flash_cache_get_cache_root());
		$path = wp_normalize_path($path);
		if (!str_starts_with($path, $root)) {
			return null;
		}
		$relative = substr($path, strlen($root));
		$parts	  = explode('/', $relative);
		$name	  = end($parts);
		if (in_array($parts[0], array('.trash', 'locks'), true) || str_ends_with($name, '.tmp')) {
			return null;
		}
		if ('assets' === $parts[0]) {
			$kinds = array('css' => 'styles', 'js' => 'scripts', 'fonts' => 'fonts');
			return isset($kinds[$parts[2] ?? '']) ? array($kinds[$parts[2]], true) : array('other', false);
		}
		if ('index-cache.html' === $name) {
			return array('static', true);
		}
		if ('index-cache.html.gz' === $name) {
			return array('static', false);
		}
		if ('index-cache.php' === $name) {
			return array('php', false);
		}
		if (count($parts) > 1 && 'requests' === $parts[count($parts) - 2]) {
			return array('php', 1 === preg_match('/^[0-9a-f]{64}\.html$/', $name));
		}
		return array('other', false);
	}

	public static function record($path, $old_size, $new_size) {
		$class = self::classify($path);
		if (null === $class) {
			return;
		}
		list($kind, $item) = $class;
		$files = 0;
		if ($item && null === $old_size && null !== $new_size) {
			$files = 1;
		} elseif ($item && null !== $old_size && null === $new_size) {
			$files = -1;
		}
		$bytes = (int) $new_size - (int) $old_size;
		if (0 === $files && 0 === $bytes) {
			return;
		}
		// Pages are written by an output buffer that closes on the shutdown action, too late for
		// a shutdown callback added then; a PHP shutdown function still runs.
		if (empty(self::$delta)) {
			register_shutdown_function(array(__CLASS__, 'save'));
		}
		self::$delta[$kind]['files'] = (self::$delta[$kind]['files'] ?? 0) + $files;
		self::$delta[$kind]['bytes'] = (self::$delta[$kind]['bytes'] ?? 0) + $bytes;
	}

	public static function save() {
		global $wpdb;
		$delta		 = self::$delta;
		self::$delta = array();
		foreach ($delta as $kind => $values) {
			foreach ($values as $field => $change) {
				if (0 === (int) $change) {
					continue;
				}
				$name = 'flash_cache_stats_' . $kind . '_' . $field;
				if (!$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = GREATEST(0, CAST(option_value AS SIGNED) + %d) WHERE option_name = %s", $change, $name))) {
					add_option($name, max(0, (int) $change), '', false);
				}
				wp_cache_delete($name, 'options');
			}
		}
	}

	/**
	 * Counters of every kind plus 'total', and when they were last recounted (0: never).
	 */
	public static function get() {
		global $wpdb;
		$stats = array();
		foreach (self::KINDS as $kind) {
			$stats[$kind] = array('files' => 0, 'bytes' => 0);
		}
		$rows = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('flash_cache_stats_') . '%'), ARRAY_A);
		$counted = 0;
		foreach ((array) $rows as $row) {
			if ('flash_cache_stats_counted' === $row['option_name']) {
				$counted = (int) $row['option_value'];
			} elseif (preg_match('/^flash_cache_stats_([a-z]+)_(files|bytes)$/', $row['option_name'], $match) && isset($stats[$match[1]])) {
				$stats[$match[1]][$match[2]] = max(0, (int) $row['option_value']);
			}
		}
		$stats['total'] = array('files' => array_sum(array_column($stats, 'files')), 'bytes' => array_sum(array_column($stats, 'bytes')));
		$stats['counted'] = $counted;
		return $stats;
	}

	/**
	 * Sets every counter, for a recount or an emptied cache.
	 */
	public static function set(array $totals) {
		self::$delta = array();
		foreach (self::KINDS as $kind) {
			foreach (array('files', 'bytes') as $field) {
				update_option('flash_cache_stats_' . $kind . '_' . $field, max(0, (int) ($totals[$kind][$field] ?? 0)), false);
			}
		}
		update_option('flash_cache_stats_counted', time(), false);
	}

	public static function reset() {
		delete_option('flash_cache_disk_usage');
		@unlink(self::scan_file());
		self::set(array());
	}

	private static function scan_file() {
		return flash_cache_get_cache_root() . 'locks/recount.json';
	}

	/**
	 * One step of the recount, about a second long; the browser calls it again until done.
	 * The folders still to read are kept in a file, so a step resumes where the last one ended.
	 */
	public static function ajax_recount() {
		check_ajax_referer('flash_cache_recount');
		if (!current_user_can('manage_options')) {
			wp_send_json_error();
		}
		$root  = flash_cache_get_cache_root();
		$file  = self::scan_file();
		$state = !empty($_POST['restart']) || !is_file($file) ? null : json_decode((string) file_get_contents($file), true);
		if (!is_array($state)) {
			$state = array('stack' => is_dir($root) ? array('') : array(), 'totals' => array());
		}
		$deadline = microtime(true) + (float) apply_filters('flash_cache_recount_step_seconds', 1);
		while ($state['stack'] && microtime(true) < $deadline) {
			$relative = array_pop($state['stack']);
			$names	  = @scandir($root . $relative);
			foreach ((array) $names as $name) {
				if ('.' === $name || '..' === $name || ('' === $relative && in_array($name, array('.trash', 'locks'), true))) {
					continue;
				}
				$path = $root . $relative . $name;
				if (is_link($path)) {
					continue;
				}
				if (is_dir($path)) {
					$state['stack'][] = $relative . $name . '/';
					continue;
				}
				$class = self::classify($path);
				if (null !== $class) {
					$state['totals'][$class[0]]['files'] = ($state['totals'][$class[0]]['files'] ?? 0) + ($class[1] ? 1 : 0);
					$state['totals'][$class[0]]['bytes'] = ($state['totals'][$class[0]]['bytes'] ?? 0) + (int) @filesize($path);
				}
			}
		}
		if ($state['stack']) {
			if (wp_mkdir_p(dirname($file))) {
				file_put_contents($file, wp_json_encode($state));
			}
			wp_send_json_success(array('done' => false));
		}
		@unlink($file);
		self::set($state['totals']);
		wp_send_json_success(array('done' => true, 'html' => self::cards()));
	}

	private static function size($bytes) {
		return $bytes > 0 ? size_format($bytes, 1) : size_format(0);
	}

	/**
	 * The statistics block of the General Settings screen.
	 */
	public static function cards() {
		$stats = self::get();
		$cards = array(
			'static'  => array('dashicons-desktop', __('Static pages', 'flash-cache'), _n_noop('%s page', '%s pages', 'flash-cache')),
			'php'	  => array('dashicons-editor-code', __('PHP pages', 'flash-cache'), _n_noop('%s page', '%s pages', 'flash-cache')),
			'styles'  => array('dashicons-admin-appearance', __('Styles', 'flash-cache'), _n_noop('%s file', '%s files', 'flash-cache')),
			'scripts' => array('dashicons-media-code', __('Scripts', 'flash-cache'), _n_noop('%s file', '%s files', 'flash-cache')),
			'fonts'	  => array('dashicons-editor-textcolor', __('Fonts', 'flash-cache'), _n_noop('%s file', '%s files', 'flash-cache')),
		);
		$html = '<div class="flash-cache-stats-cards">';
		foreach ($cards as $kind => $card) {
			$files = $stats[$kind]['files'];
			$html .= '<div class="flash-cache-stats-card"><span class="dashicons ' . $card[0] . '"></span><strong>' . esc_html($card[1]) . '</strong><span class="flash-cache-stats-size">' . esc_html(self::size($stats[$kind]['bytes'])) . '</span><span>' . esc_html(sprintf(translate_nooped_plural($card[2], $files, 'flash-cache'), number_format_i18n($files))) . '</span></div>';
		}
		$html .= '</div><p class="flash-cache-stats-total">' . sprintf(
				/* translators: 1: size, 2: number of files */
				__('Total: %1$s in %2$s files', 'flash-cache'),
				'<strong>' . esc_html(self::size($stats['total']['bytes'])) . '</strong>',
				esc_html(number_format_i18n($stats['total']['files']))
		) . '</p>';
		$counted = $stats['counted'] ? sprintf(
						/* translators: %s: time span, such as "3 mins" */
						__('Counted %s ago', 'flash-cache'),
						human_time_diff($stats['counted'])
				) : __('Not counted yet', 'flash-cache');
		$stale = !$stats['counted'] || time() - $stats['counted'] > self::RECOUNT_AFTER;
		return '<div class="flash-cache-stats" data-stale="' . ($stale ? '1' : '0') . '" data-nonce="' . esc_attr(wp_create_nonce('flash_cache_recount')) . '">
				<div class="flash-cache-stats-head"><h3>' . __('Cache statistics', 'flash-cache') . '</h3><span class="flash-cache-stats-when">' . esc_html($counted) . ' · <a href="#" class="flash-cache-recount">' . __('Recount', 'flash-cache') . '</a></span></div>
				' . $html . '</div>';
	}
}

flash_cache_stats::hooks();

/**
 * Writes a file of the cache folder through a temporary file, so readers never get it half
 * written, and keeps the statistics in step.
 */
function flash_cache_write_file($path, $content) {
	clearstatcache(true, $path);
	$old	   = is_file($path) ? (int) filesize($path) : null;
	$temporary = $path . '.' . uniqid('', true) . '.tmp';
	if (false === file_put_contents($temporary, $content) || !@rename($temporary, $path)) {
		@unlink($temporary);
		return false;
	}
	flash_cache_stats::record($path, $old, strlen($content));
	return true;
}

/**
 * Counts a file of the cache folder that was just written in place.
 */
function flash_cache_file_added($path, $old_size = null) {
	clearstatcache(true, $path);
	if (is_file($path)) {
		flash_cache_stats::record($path, $old_size, (int) filesize($path));
	}
}

function flash_cache_delete_file($path) {
	clearstatcache(true, $path);
	if (!is_file($path) || is_link($path)) {
		return is_link($path) ? @unlink($path) : false;
	}
	$size = (int) filesize($path);
	if (!@unlink($path)) {
		return false;
	}
	flash_cache_stats::record($path, $size, null);
	return true;
}
