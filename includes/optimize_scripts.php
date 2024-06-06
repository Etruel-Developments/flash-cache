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

class flash_cache_optimize_scripts {

	public static $js_tags_links	 = array();
	public static $js_tags_inline	 = array();

	public static function hooks() {
		add_filter('flash_cache_response_html', array(__CLASS__, 'init_process'), 2, 2);
	}

	public static function init_process($content, $url_to_cache) {
		if (empty(flash_cache_process::$advanced_settings)) {
			flash_cache_process::$advanced_settings = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
		}
		if (empty(flash_cache_process::$advanced_settings['optimize_scripts'])) {
			return $content;
		}
		$noptimize_scripts = apply_filters('flash_cache_scripts_noptimize', false, $content, $url_to_cache);
		if ($noptimize_scripts) {
			return $content;
		}

		// Get script files.
		if (preg_match_all('#<script.*</script>#Usmi', $content, $matches)) {
			foreach ($matches[0] as $tag) {
				if (!preg_match("/<script[^>]+json[^>]+>.+/", $tag) && !preg_match("/<script[^>]+text\/template[^>]+>.+/", $tag)) {

					$should_aggregate = self::should_aggregate($tag);
					if (!$should_aggregate) {
						$tag = '';
						continue;
					}

					$tag = self::checkExcludes($tag);

					if (preg_match('#<script[^>]*src=("|\')([^>]*)("|\')#Usmi', $tag, $source)) {
						$url = current(explode('?', $source[2], 2));
						if (!self::is_valid_url($url)) {
							continue;
						}
						self::$js_tags_links[] = flash_cache_get_path($url);
						$content = preg_replace('/<!--(.*?)-->/s', '', $content);
					} else {
						if (flash_cache_process::$advanced_settings['inline_scripts']) {
							$tag = '';
						} else {
							if (preg_match('/<script[^>]*>(.*?)<\/script>/is', $tag, $script_content)) {
								if (!empty($script_content[1])) {
									self::$js_tags_inline[] = $script_content[1];
									$content = preg_replace('/<!--(.*?)-->/s', '', $content);
								}
							}
						}
					}

					$content = str_replace($tag, '', $content);
				}
			}
		}

		$content = self::end_process($content);

		return $content;
	}

	public static function end_process($content) {

		$arrContextOptions = array(
			"ssl" => array(
				"verify_peer"		 => false,
				"verify_peer_name"	 => false,
			),
		);

		if (empty(flash_cache_process::$advanced_settings)) {
			flash_cache_process::$advanced_settings = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
		}
		if (is_null(flash_cache_process::$origin_url)) {
			flash_cache_process::$origin_url = get_site_url(null, '/');
		}
		$server_name = flash_cache_get_server_name();
		$all_js_code = '';
		$basename_js = '';

		foreach (self::$js_tags_links as $path) {
			if (!empty($path)) {
				$code		 = file_get_contents($path, false, stream_context_create($arrContextOptions));
				$all_js_code .= $code;
				$basename_js = md5($basename_js . $path);
			}
		}
		foreach (self::$js_tags_inline as $tag) {
			if (!empty($tag)) {
				$all_js_code .= $tag;
				$basename_js = md5($basename_js . $tag);
			}
		}

		$all_js_code = trim($all_js_code);
		$cache_dir	 = flash_cache_get_home_path() . flash_cache_process::$advanced_settings['cache_dir'];
		$cache_path	 = $cache_dir . $server_name . '/scripts/';

		if (!file_exists($cache_path)) {
			@mkdir($cache_path, 0777, true);
		}

		$full_path_file_js	 = $cache_path . $basename_js . '.js';
		$url_file_js		 = str_replace(flash_cache_get_home_path(), get_home_url(null, '/'), $full_path_file_js);
		$all_js_code		 = apply_filters('flash_cache_js_code_before_join', $all_js_code, $full_path_file_js, flash_cache_process::$advanced_settings);
		file_put_contents($full_path_file_js, $all_js_code);

		//Call the function insert_html_before_element for change the actual html by the new with styles and scripts
		$content = self::insert_html_before_element($content, '<title>', '<script type="text/javascript" src="' . $url_file_js . '"></script>');

		return $content;
	}

	public static function insert_html_before_element($html, $element_selector, $new_html) {
		// Find the position of the element in the HTML
		$pos = strpos($html, $element_selector);

		if ($pos !== false) {
			// Insert the new HTML before the element
			$html = substr_replace($html, $new_html . PHP_EOL, $pos, 0);
		}

		// Return the modified HTML code
		return $html;
	}

	public static function should_aggregate($tag) {
		// We're only interested in the type attribute of the <script> tag itself, not any possible
		// inline code that might just contain the 'type=' string...
		$tag_parts				 = array();
		preg_match('#<(script[^>]*)>#i', $tag, $tag_parts);
		$tag_without_contents	 = null;
		if (!empty($tag_parts[1])) {
			$tag_without_contents = $tag_parts[1];
		}

		$has_type = ( strpos($tag_without_contents, 'type') !== false );

		$type_valid = false;
		if ($has_type) {
			$type_valid = (bool) preg_match('/type\s*=\s*[\'"]?(?:text|application)\/(?:javascript|ecmascript)[\'"]?/i', $tag_without_contents);
		}

		$should_aggregate = false;
		if (!$has_type || $type_valid) {
			$should_aggregate = true;
		}

		return $should_aggregate;
	}

	public static function is_valid_url($url) {
		$url_host = parse_url($url);
		if (empty($url_host['host'])) {
			return false;
		}
		$url_host	 = $url_host['host'];
		$url_host	 = sanitize_text_field($url_host);

		$is_valid = flash_cache_get_server_name() == $url_host;

		if (!$is_valid) {
			if (flash_cache_process::$advanced_settings['social_scripts']) {
				$is_valid = 1;
			}
		}

		return $is_valid;
	}

	public static function checkExcludes($tag) {
		// Get advanced settings
		$advanced_settings = flash_cache_process::$advanced_settings;

		// Check if the "social_scripts" option is enabled
		if (!$advanced_settings['social_scripts']) {
			// Exclude social scripts
			$tag = self::excludeSocialScripts($tag);
		}

		// Check if the "social_scripts" option is enabled
		if ($advanced_settings['theme_files']) {
			// Exclude social scripts
			$tag = self::excludeThemes($tag);
		}

		// Check if the "avoid_optimize" option is enabled
		if ($advanced_settings['avoid_optimize']) {
			if (!empty($advanced_settings['avoid_optimize_text'])) {
				// Exclude scripts from the specified URLs in the textarea
				$tag = self::excludeOptimizeText($tag, $advanced_settings['avoid_optimize_text']);
			}
		}

		// Check if the "plugins_files" option is enabled
		if ($advanced_settings['plugins_files']) {
			// Exclude scripts from plugins
			$tag = self::excludeScriptsPlugins($tag, $advanced_settings);
		}

		return $tag;
	}

	private static function excludeThemes($tag) {
		// Get the path to the theme directory
		$theme_directory = get_template_directory();

		// Obtain the base URL of the site
		$base_url = home_url('/');

		// Build the complete URL of the theme directory
		$theme_url = str_replace(ABSPATH, $base_url, $theme_directory);

		// Get the name of the current theme
		$current_theme	 = wp_get_theme();
		$theme_name		 = $current_theme->get('Name');
		if (preg_match('#<script[^>]*src=("|\')([^>]*)("|\')#Usmi', $tag, $source)) {
			$url		 = current(explode('?', $source[2], 2));
			// Get URL content
			$response	 = wp_remote_get($url);
			if (!is_wp_error($response)) {
				// Obtain the content of the response
				$body = wp_remote_retrieve_body($response);

				// Check if the content matches the topic name
				if (strpos($body, $theme_name) !== false) {
					$tag = ''; // Exclude script
				}
			}
		}

		// Check if the script comes from the theme directory
		if (strpos($tag, $theme_url) !== false || strpos($tag, $theme_name) !== false) {
			$tag = ''; // Exclude script
		}

		return $tag;
	}

	private static function excludeScriptsPlugins($tag, $values) {
		$pluginValues	 = isset($values['plugins_exclude']) ? $values['plugins_exclude'] : array();
		// Get the list of active plugins
		$active_plugins	 = get_option('active_plugins', array());
		// Loop through each active plugin
		foreach ($active_plugins as $plugin) {
			//If exist $name_plugins
			if (!empty($pluginValues)) {
				$plugin_name	 = dirname($plugin);
				$plugin_exclude	 = plugins_url() . '/' . $plugin_name;
				$isChecked		 = isset($pluginValues[$plugin_name]) && $pluginValues[$plugin_name] === 'on';

				if ($isChecked) {
					if (strpos($tag, $plugin_exclude) !== false) {
						$tag = ''; // Exclude the script
						break; // Stop checking other plugins
					}
				}
			} else {
				if (strpos($tag, plugins_url()) !== false) {
					$tag = ''; // Exclude the script
					break; // Stop checking other plugins
				}
			}
		}

		return $tag;
	}

	private static function get_excludedPlatforms(){
		// Add more social media platforms here
		return apply_filters('flash_cache_excluded_seo_platforms', array(
			'twitter',
			'youtube',
			'tiktok',
			'alexa',
			'google',
			'facebook',
			'gtag()',
			'fbq'
		));
	}
	
	private static function excludeSocialScripts($tag) {
		// Define the list of social media platforms to exclude
		$excludedPlatforms = self::get_excludedPlatforms();
				
		// Check if the tag contains a URL
		if (preg_match('/<script[^>]*src=("|\')([^>]*)("|\')/i', $tag, $matches)) {
			$url		 = current(explode('?', $matches[2], 2));
			// Get the content of the URL
			$response	 = wp_remote_get($url);
			if (!is_wp_error($response)) {
				$body = wp_remote_retrieve_body($response);
				// Check if any excluded platform name is found in the URL content
				foreach ($excludedPlatforms as $platform) {
					if (stripos($tag, $platform) !== false) {
						$tag = ''; // Exclude the script
						break;
					}

					if (stripos($body, $platform) !== false) {
						$tag = ''; // Exclude the script
						break;
					}
				}
			}
		} else {
			// If the tag is not a URL, check if it contains any excluded platform name
			foreach ($excludedPlatforms as $platform) {
				if (stripos($tag, $platform) !== false) {
					$tag = ''; // Exclude the script
					break;
				}
			}
		}

		return $tag;
	}

	private static function excludeOptimizeText($tag, $text) {
		// Get the URLs to exclude from the "avoid_optimize_text" textarea
		$urls_to_exclude = explode(" ", $text);

		// Loop through each URL and check if it matches the script URL
		foreach ($urls_to_exclude as $url) {
			$url = trim($url);
			if (!empty($url) && strpos($tag, $url) !== false) {
				$tag = ''; // Exclude the script
				break; // Stop checking other URLs
			}
		}

		return $tag;
	}
}

flash_cache_optimize_scripts::hooks();