<?php

/**
 * @package         etruel\Flash Cache
 * @subpackage 	    Optimize Styles
 * @author          Sebastian Robles
 * @author          Esteban Truelsegaard
 * @copyright       Copyright (c) 2017
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

/**
 * Combines the page's stylesheets and <style> blocks, in their order, into shared files under
 * the cache folder's assets/. A stylesheet that cannot be combined ends a run, so the cascade
 * keeps its order; each run of two or more becomes one <link> where the run started.
 */
class flash_cache_optimize_styles {

	public static function hooks() {
		add_filter('flash_cache_response_html', array(__CLASS__, 'init_process'), 1, 2);
	}

	public static function init_process($content, $url_to_cache) {
		$settings = flash_cache_get_advanced_settings();
		if (empty($settings['optimize_styles']) || !flash_cache_assets::is_html_document($content)) {
			return $content;
		}
		if (apply_filters('flash_cache_css_noptimize', false, $content, $url_to_cache)) {
			return $content;
		}

		list($masked, $map) = flash_cache_assets::mask($content, true);
		if (!preg_match_all('#<link\b[^>]*>|<style\b[^>]*>.*?</style>#is', $masked, $matches, PREG_OFFSET_CAPTURE)) {
			return $content;
		}

		$runs = array(array());
		foreach ($matches[0] as $match) {
			$item = self::prepare($match[0], $url_to_cache);
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
			$file = flash_cache_assets::write_once('css', array_merge(array_column($run, 'id'), array(md5(wp_json_encode($settings)))), 'css', function ($path) use ($run, $settings) {
				return apply_filters('flash_cache_css_code_before_join', implode("\n", array_column($run, 'code')), $path, $settings);
			});
			if (false === $file) {
				continue;
			}
			foreach ($run as $index => $item) {
				$replacements[$item['offset']] = array($item['length'], 0 === $index ? '<link rel="stylesheet" href="' . esc_url(flash_cache_assets::url($file)) . '" media="all" />' : '');
			}
		}

		krsort($replacements);
		foreach ($replacements as $offset => $replacement) {
			$masked = substr_replace($masked, $replacement[1], $offset, $replacement[0]);
		}
		return flash_cache_assets::unmask($masked, $map);
	}

	/**
	 * One stylesheet ready to combine (code, id), false when it must stay where it is, or null
	 * when the tag is not a stylesheet at all.
	 */
	private static function prepare($tag, $page_url) {
		$is_link = stripos($tag, '<link') === 0;
		if ($is_link) {
			$rel = strtolower((string) flash_cache_assets::get_attribute($tag, 'rel'));
			if (!in_array('stylesheet', preg_split('/\s+/', $rel), true) || str_contains($rel, 'alternate')) {
				return null;
			}
		} else {
			$type = flash_cache_assets::get_attribute($tag, 'type');
			if (null !== $type && stripos($type, 'text/css') === false) {
				return null;
			}
		}
		foreach (array('integrity', 'data-noptimize', 'disabled') as $attribute) {
			if (flash_cache_assets::has_attribute($tag, $attribute)) {
				return false;
			}
		}

		if ($is_link) {
			$href = flash_cache_assets::absolute_url((string) flash_cache_assets::get_attribute($tag, 'href'));
			if (!flash_cache_assets::is_local_url($href) && !apply_filters('flash_cache_optimize_external_fonts', false, $href)) {
				return false;
			}
			$code = (string) apply_filters('flash_cache_save_fonts', $href, flash_cache_get_cache_root());
			if ($code === '' || $code === $href) {
				return false;
			}
			$source = $href;
		} else {
			$code	= (string) preg_replace('#^<style\b[^>]*>|</style>$#i', '', $tag);
			$code	= flash_cache_assets::absolutize_css_urls($code, $page_url);
			$source = 'inline';
		}

		if (stripos($code, '@import') !== false) {
			return false;
		}
		$code  = preg_replace('/@charset\s+[^;]+;/i', '', $code);
		$media = trim((string) flash_cache_assets::get_attribute($tag, 'media'));
		if ($media !== '' && strtolower($media) !== 'all') {
			$code = '@media ' . $media . " {\n" . $code . "\n}";
		}
		return array('code' => $code, 'id' => $source . '|' . md5($code));
	}
}

flash_cache_optimize_styles::hooks();
