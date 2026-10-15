<?php

/**
 * @package         etruel\Flash Cache
 * @subpackage 	    Optimize Fonts
 * @author          Sebastian Robles
 * @author          Esteban Truelsegaard
 * @copyright       Copyright (c) 2017
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

class flash_cache_optimize_fonts {

	public static function hooks() {
		add_filter('flash_cache_save_fonts', array(__CLASS__, 'flash_cache_link_fonts_from_url'), 1, 2);
	}

	/**
	 * The code of a stylesheet, ready to join a combined file: its url() references are made
	 * absolute, and the font files of hosts an add-on claims through
	 * flash_cache_optimize_external_fonts are handed to flash_cache_save_external_fonts.
	 * Returns '' when the stylesheet cannot be read, and a line break for an empty one.
	 */
	public static function flash_cache_link_fonts_from_url($url, $cache_dir) {
		$source = flash_cache_assets::read($url);
		if (false === $source) {
			return '';
		}
		if (trim($source['code']) === '') {
			return "\n";
		}
		$css = flash_cache_assets::absolutize_css_urls($source['code'], $url);

		if (!preg_match_all('/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i', $css, $matches)) {
			return $css;
		}
		$font_folder_path = flash_cache_assets::dir('fonts');
		foreach (array_unique($matches[2]) as $font_url) {
			if (flash_cache_assets::is_local_url($font_url) || !preg_match('/\.(woff2?|eot|ttf|otf)(\?|#|$)/i', $font_url)) {
				continue;
			}
			if (apply_filters('flash_cache_optimize_external_fonts', false, $font_url)) {
				wp_mkdir_p($font_folder_path);
				$css = apply_filters('flash_cache_save_external_fonts', $css, $font_url, $font_folder_path);
			}
		}
		return $css;
	}
}

flash_cache_optimize_fonts::hooks();
