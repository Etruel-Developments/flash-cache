<?php

/**
 * @package         etruel\Flash Cache
 * @subpackage 	    Optimize Scripts
 * @author          Sebastian Robles
 * @author          Esteban Truelsegaard
 * @copyright       Copyright (c) 2017
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

/**
 * Combines runs of consecutive classic scripts into shared files under the cache folder's
 * assets/. Any script that is not combined ends the run, and so does </head>. Each run of two
 * or more becomes one script at the place of its last script, so no code runs before the
 * markup or the scripts it ran after in the original page.
 */
class flash_cache_optimize_scripts {

	public static function hooks() {
		add_filter('flash_cache_response_html', array(__CLASS__, 'init_process'), 2, 2);
	}

	public static function init_process($content, $url_to_cache) {
		$settings = flash_cache_get_advanced_settings();
		if (empty($settings['optimize_scripts']) || !flash_cache_assets::is_html_document($content)) {
			return $content;
		}
		if (apply_filters('flash_cache_scripts_noptimize', false, $content, $url_to_cache)) {
			return $content;
		}

		list($masked, $map) = flash_cache_assets::mask($content);
		if (!preg_match_all('#<script\b[^>]*>.*?</script>#is', $masked, $matches, PREG_OFFSET_CAPTURE)) {
			return $content;
		}
		$head_end = stripos($masked, '</head>');

		$runs		 = array(array());
		$in_head	 = true;
		foreach ($matches[0] as $match) {
			if ($in_head && $match[1] > $head_end) {
				$in_head = false;
				$runs[]	 = array();
			}
			$item = self::prepare($match[0], $settings);
			if (null === $item) {
				continue;
			}
			if (false === $item) {
				$runs[] = array();
				continue;
			}
			$item['offset']				 = $match[1];
			$item['length']				 = strlen($match[0]);
			$runs[count($runs) - 1][]	 = $item;
		}

		$replacements = array();
		foreach ($runs as $run) {
			if (count($run) < 2) {
				continue;
			}
			$file = flash_cache_assets::write_once('js', array_merge(array_column($run, 'id'), array(md5(wp_json_encode($settings)))), 'js', function ($path) use ($run, $settings) {
				return apply_filters('flash_cache_js_code_before_join', implode(";\n", array_column($run, 'code')) . ";\n", $path, $settings);
			});
			if (false === $file) {
				continue;
			}
			$last = count($run) - 1;
			foreach ($run as $index => $item) {
				$replacements[$item['offset']] = array($item['length'], $last === $index ? '<script src="' . esc_url(flash_cache_assets::url($file)) . '"></script>' : '');
			}
		}

		krsort($replacements);
		foreach ($replacements as $offset => $replacement) {
			$masked = substr_replace($masked, $replacement[1], $offset, $replacement[0]);
		}
		return flash_cache_assets::unmask($masked, $map);
	}

	/**
	 * One script ready to combine (code, id), false when it must stay where it is, or null for
	 * a data block (JSON, templates) that runs nothing and so does not end a run.
	 */
	private static function prepare($tag, $settings) {
		$open = preg_match('#^<script\b[^>]*>#i', $tag, $open_match) ? $open_match[0] : '<script>';
		$type = strtolower(trim((string) flash_cache_assets::get_attribute($open, 'type')));
		if ($type !== '' && !in_array($type, array('text/javascript', 'application/javascript', 'text/ecmascript', 'application/ecmascript'), true)) {
			return in_array($type, array('module', 'importmap'), true) ? false : null;
		}
		foreach (array('async', 'defer', 'nomodule', 'integrity', 'data-noptimize') as $attribute) {
			if (flash_cache_assets::has_attribute($open, $attribute)) {
				return false;
			}
		}

		$src = flash_cache_assets::get_attribute($open, 'src');
		if (null === $src) {
			$code = (string) preg_replace('#^<script\b[^>]*>|</script>$#i', '', $tag);
			if (trim($code) === '') {
				return null;
			}
			if (!empty($settings['inline_scripts']) || stripos($code, 'document.write') !== false || self::is_third_party($code, $settings)) {
				return false;
			}
			return array('code' => $code, 'id' => 'inline|' . md5($code));
		}

		$src = flash_cache_assets::absolute_url($src);
		if (self::is_excluded($src, $settings)) {
			return false;
		}
		if (!flash_cache_assets::is_local_url($src) && empty($settings['social_scripts'])) {
			return false;
		}
		$source = flash_cache_assets::read($src);
		if (false === $source || stripos($source['code'], 'document.write') !== false) {
			return false;
		}
		return array('code' => $source['code'], 'id' => $source['id']);
	}

	/**
	 * Theme, plugin and listed files the settings keep out of the combined files.
	 */
	private static function is_excluded($src, $settings) {
		if (!empty($settings['theme_files'])) {
			foreach (array(get_template_directory_uri(), get_stylesheet_directory_uri()) as $theme_url) {
				if (str_starts_with(set_url_scheme($src, 'http'), set_url_scheme(trailingslashit($theme_url), 'http'))) {
					return true;
				}
			}
		}
		if (!empty($settings['plugins_files'])) {
			$selected = (array) ($settings['plugins_exclude'] ?? array());
			$plugins  = set_url_scheme(trailingslashit(plugins_url()), 'http');
			$path	  = set_url_scheme($src, 'http');
			if (str_starts_with($path, $plugins)) {
				$plugin = strtok(substr($path, strlen($plugins)), '/');
				if (empty($selected) || (isset($selected[$plugin]) && $selected[$plugin] === 'on')) {
					return true;
				}
			}
		}
		if (!empty($settings['avoid_optimize']) && !empty($settings['avoid_optimize_text'])) {
			foreach (preg_split('/\s+/', (string) $settings['avoid_optimize_text']) as $fragment) {
				if ($fragment !== '' && strpos($src, str_replace('\\', '/', $fragment)) !== false) {
					return true;
				}
			}
		}
		return self::is_third_party($src, $settings);
	}

	/**
	 * Analytics, ads and social code stays out unless the other-domains setting is on.
	 */
	private static function is_third_party($code_or_src, $settings) {
		if (!empty($settings['social_scripts'])) {
			return false;
		}
		foreach (self::get_excludedPlatforms() as $platform) {
			if (stripos($code_or_src, $platform) !== false) {
				return true;
			}
		}
		return false;
	}

	private static function get_excludedPlatforms() {
		return apply_filters('flash_cache_excluded_seo_platforms', array(
			'twitter',
			'youtube',
			'tiktok',
			'alexa',
			'google',
			'facebook',
			'gtag(',
			'fbq(',
		));
	}
}

flash_cache_optimize_scripts::hooks();
