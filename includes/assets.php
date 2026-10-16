<?php

/**
 * @package         etruel\Flash Cache
 * @subpackage 	    Assets
 * @author          Esteban Truelsegaard
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

/**
 * Shared ground of the style and script optimizers: which documents they touch, where an asset
 * is read from, and where the combined files are written.
 */
class flash_cache_assets {

	/**
	 * Only full HTML documents are optimized; feeds, sitemaps and fragments pass through.
	 */
	public static function is_html_document($html) {
		$start = ltrim(substr((string) $html, 0, 200));
		if (stripos($start, '<?xml') === 0 || stripos($start, '<rss') === 0 || stripos($start, '<feed') === 0) {
			return false;
		}
		return stripos($html, '<head') !== false && stripos($html, '</head>') !== false;
	}

	/**
	 * Replaces the parts no tag must be read from (HTML comments, including conditional ones,
	 * noscript and template blocks, and optionally scripts) with placeholders, so nothing
	 * inside them is optimized. unmask() puts them back.
	 */
	public static function mask($html, $mask_scripts = false) {
		$map	  = array();
		$patterns = array('#<!--.*?-->#s', '#<noscript\b.*?</noscript>#is', '#<template\b.*?</template>#is');
		if ($mask_scripts) {
			$patterns[] = '#<script\b.*?</script>#is';
		}
		foreach ($patterns as $pattern) {
			$html = preg_replace_callback($pattern, function ($match) use (&$map) {
				$key	   = "\x01FC" . count($map) . "\x01";
				$map[$key] = $match[0];
				return $key;
			}, $html);
		}
		return array($html, $map);
	}

	public static function unmask($html, $map) {
		return strtr($html, $map);
	}

	public static function get_attribute($tag, $name) {
		if (preg_match('/\s' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $match)) {
			return html_entity_decode($match[1] !== '' ? $match[1] : ($match[2] ?? '') . ($match[3] ?? ''), ENT_QUOTES);
		}
		return null;
	}

	public static function has_attribute($tag, $name) {
		return (bool) preg_match('/\s' . preg_quote($name, '/') . '(?:\s|=|>|\/)/i', $tag);
	}

	/**
	 * An absolute URL for a src or href found in the page.
	 */
	public static function absolute_url($url) {
		$url = trim((string) $url);
		if (str_starts_with($url, '//')) {
			return (is_ssl() ? 'https:' : 'http:') . $url;
		}
		if (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
			return WP_Http::make_absolute_url($url, home_url('/'));
		}
		return $url;
	}

	public static function is_local_url($url) {
		return strtolower((string) wp_parse_url($url, PHP_URL_HOST)) === strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
	}

	/**
	 * The file on disk behind a URL of this site, or false. Only .css and .js files under one of
	 * the WordPress URL bases qualify, and a path with "." or ".." segments never does; symbolic
	 * links (a plugin folder linked from elsewhere) are followed as the web server would.
	 */
	public static function local_path($url) {
		if (!self::is_local_url($url)) {
			return false;
		}
		$path = (string) wp_parse_url($url, PHP_URL_PATH);
		if (!preg_match('/\.(css|js)$/i', $path)) {
			return false;
		}
		$bases = array(
			array(content_url('/'), WP_CONTENT_DIR . '/'),
			array(includes_url('/'), ABSPATH . WPINC . '/'),
			array(site_url('/'), ABSPATH),
			array(home_url('/'), flash_cache_get_home_path()),
		);
		foreach ($bases as $base) {
			$base_path = (string) wp_parse_url($base[0], PHP_URL_PATH);
			if ($base_path !== '' && str_starts_with($path, $base_path)) {
				$relative = rawurldecode(substr($path, strlen($base_path)));
				if (preg_match('#(^|/)\.\.?(/|$)#', $relative) || str_contains($relative, "\0")) {
					return false;
				}
				$file = wp_normalize_path($base[1] . $relative);
				return is_file($file) ? $file : false;
			}
		}
		return false;
	}

	/**
	 * The content of an asset and an id that changes when the content does. Local files are read
	 * from disk; other URLs over HTTP, with certificate checks, and only once per request.
	 */
	public static function read($url) {
		static $remote = array();
		$file = self::local_path($url);
		if (false !== $file) {
			$code = file_get_contents($file);
			return false === $code ? false : array('code' => $code, 'id' => $file . '@' . filemtime($file));
		}
		if (!isset($remote[$url])) {
			$response	  = wp_safe_remote_get($url, array('timeout' => 10));
			$remote[$url] = (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) ? wp_remote_retrieve_body($response) : false;
		}
		return false === $remote[$url] ? false : array('code' => $remote[$url], 'id' => $url . '@' . md5($remote[$url]));
	}

	/**
	 * Relative url() references of a stylesheet made absolute, so the rules keep working once
	 * the code moves into a combined file.
	 */
	public static function absolutize_css_urls($css, $base_url) {
		return preg_replace_callback('/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i', function ($match) use ($base_url) {
			$reference = trim($match[2]);
			if (preg_match('#^(data:|[a-z][a-z0-9+.-]*:|//|\#)#i', $reference)) {
				return $match[0];
			}
			return 'url("' . WP_Http::make_absolute_url($reference, $base_url) . '")';
		}, $css);
	}

	public static function dir($type) {
		return flash_cache_get_cache_root() . 'assets/' . flash_cache_get_server_name() . '/' . $type . '/';
	}

	public static function url($file) {
		return str_replace(wp_normalize_path(flash_cache_get_home_path()), home_url('/'), wp_normalize_path($file));
	}

	/**
	 * Writes a combined file once: files are named after their sources, so an existing file
	 * already holds this content. $build runs only when the file has to be written.
	 */
	public static function write_once($type, array $source_ids, $extension, callable $build) {
		$dir  = self::dir($type);
		$file = $dir . md5(implode('|', $source_ids)) . '.' . $extension;
		if (!is_file($file)) {
			if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
				return false;
			}
			if (!flash_cache_write_file($file, (string) $build($file))) {
				return false;
			}
		}
		return $file;
	}
}
