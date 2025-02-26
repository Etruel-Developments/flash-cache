<?php
/**
 * @package         etruel\Flash Cache
 * @subpackage 	   Process
 * @author          Sebastian Robles
 * @author          Esteban Truelsegaard
 * @copyright       Copyright (c) 2017
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

class flash_cache_process {

	public static $origin_url			 = null;
	public static $template_redirect	 = false;
	public static $optional_post_id		 = 0;
	public static $current_query		 = array();
	public static $url_to_cache			 = '';
	public static $pattern				 = null;
	public static $cache_type			 = 'html';
	public static $first_ob_output		 = true;
	public static $current_buffer		 = '';
	public static $force_process_type	 = null;
	public static $force_permalink		 = false;
	public static $advanced_settings	 = null;
	public static $can_cache_handler	 = null;

	/* Added for validation in optimize process. */
	public static $current_pattern = array();

	public static function hooks() {
		add_action('template_redirect', array(__CLASS__, 'process_patterns'));
		add_action('admin_post_nopriv_onload_flash_cache', array(__CLASS__, 'onload_cache'));
		add_action('admin_post_onload_flash_cache', array(__CLASS__, 'onload_cache'));
		add_filter('flash_cache_response_html', array(__CLASS__, 'cache_response_html'), 100, 2);
	}

	/**
	 * Static function get_file_lock
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function get_file_lock($path_file) {
		if (!file_exists($path_file)) {
			@mkdir($path_file, 0777, true);
			file_put_contents($path_file . 'can_create_cache.txt', 'Created by Flash Cache');
		}
		self::$can_cache_handler = fopen($path_file . 'can_create_cache.txt', 'a');
		/**
		 * bloquea exclusivamente el archivo (el archivo solo puede ser leído y escrito por el usuario), 
		 * luego, si otros usuarios nuevos desean acceder al archivo, serán bloqueados hasta que el primero cierre el archivo (libera el bloqueo).
		 * */
		return flock(self::$can_cache_handler, LOCK_EX | LOCK_NB);
	}
	
	public static function get_db_lock($path_file) {
		global $wpdb;
		$wpdb->query('SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE');
		$wpdb->query('START TRANSACTION');
	
		
		$option_lock = 'flash_cache_db_lock_' . hash('sha256', $path_file);

		$results = $wpdb->get_results(
				$wpdb->prepare(
						"SELECT * FROM ". flash_cache_settings::$flash_cache_table ." WHERE option_lock = %s  LIMIT 0, 25 FOR UPDATE NOWAIT",
						$option_lock
				)
		);

		if ($wpdb->last_error) {
			$wpdb->query('COMMIT');
			return false;
		}

		if (empty($results)) {
			$wpdb->query(
					$wpdb->prepare(
							"INSERT INTO ". flash_cache_settings::$flash_cache_table ." (option_lock, option_value) VALUES(%s, 1)",
							$option_lock
					)
			); 
		}

		if ($wpdb->last_error) {
			$wpdb->query('COMMIT');
			return false;
		}
		$wpdb->query('COMMIT');
		return true;
	}
	/**
	 * Static function can_create_cache
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function start_create_cache($path_file) {
		$advanced_settings = flash_cache_get_advanced_settings();
		if (empty($advanced_settings)) {
			return self::get_file_lock($path_file);
		}
		if (empty($advanced_settings['lock_type'])) {
			return self::get_file_lock($path_file);
		}
		if ($advanced_settings['lock_type'] == 'db') {
			return self::get_db_lock($path_file);
		}
		return self::get_file_lock($path_file);
	}

	/**
	 * Static function end_create_cache
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function end_create_cache() {
		$advanced_settings = flash_cache_get_advanced_settings();
		if (!empty($advanced_settings['lock_type'])) {
			if ($advanced_settings['lock_type'] == 'db') {
				return true;
			}
		}
		flock(self::$can_cache_handler, LOCK_UN);
		fclose(self::$can_cache_handler);
		return true;
	}

	/**
	 * Static function debug_log
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function debug($message) {
		if (empty(self::$advanced_settings)) {
			self::$advanced_settings = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
		}
		$cache_dir = flash_cache_get_home_path() . self::$advanced_settings['cache_dir'];
		$log_file = $cache_dir . flash_cache_get_server_name() . '/cache_debug.log';
		if (!file_exists($cache_dir . flash_cache_get_server_name())) {
			@mkdir($cache_dir . flash_cache_get_server_name(), 0777, true);
		}
		
		$request_URI = sanitize_url(wp_unslash($_SERVER['REQUEST_URI']));
		
		// Convert arrays and objects to string
		if (is_array($message) || is_object($message)) {
			$message = print_r($message, true);
		}
		
		$log_message = date('H:i:s') . " " . getmypid() . " {$request_URI} {$message}\n\r";
		error_log($log_message, 3, $log_file);
	}

	/**
	 * Static function cache_response_html
	 * @access public
	 * @return String $response with source code of a html object
	 * @since 1.0.0
	 */
	public static function cache_response_html($response, $url_to_cache) {
		$defer_flash_cache_js	 = '<script type="text/javascript">
		function flash_cache_onloadjs() {
		var pattern_minimum = ' . absint(self::$pattern['ttl_minimum']) . ';
		var current_query = " ' . base64_encode(json_encode(self::$current_query)) . '";
		var flash_cache_optional_post_id = ' . absint(self::$optional_post_id) . ';
		var element = document.createElement("script");
		element.src = "' . admin_url('admin-post.php?action=onload_flash_cache&p=' . urlencode(base64_encode(self::$url_to_cache)) . '') . '&token=' . hash('sha256', base64_encode(self::$url_to_cache) . wp_salt('nonce')) . '";
		document.body.appendChild(element);
		}
		if (window.addEventListener) {
			window.addEventListener("load", flash_cache_onloadjs, false);
		} else if (window.attachEvent) {
			window.attachEvent("onload", flash_cache_onloadjs);
		} else {
			window.onload = flash_cache_onloadjs;
		}
		</script>';
		$response				 = str_replace('</body>', $defer_flash_cache_js . '</body>', $response);
		return $response;
	}

	public static function create_cache_html() {
        if (is_null(self::$origin_url)) {
            self::$origin_url = get_site_url(null, '/');
        }
        self::$origin_url = flash_cache_sanitize_origin_url(self::$origin_url);
    
        $advanced_settings = flash_cache_get_advanced_settings();
        $cache_dir = rtrim(flash_cache_get_home_path() . $advanced_settings['cache_dir'], '/') . '/';
        $server_name = trim(flash_cache_get_server_name(), '/');
    
        if (empty($server_name)) {
            error_log('Error: flash_cache_get_server_name() devolvió un valor vacío.');
            $server_name = 'default';
        }
    
        if (filter_var(self::$url_to_cache, FILTER_VALIDATE_URL)) {
            $parsed_url = parse_url(self::$url_to_cache);
            $host_to_remove = $parsed_url['host'] ?? '';
            $relative_path = str_replace($parsed_url['scheme'] . '://' . $parsed_url['host'] . (isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ''), '', self::$url_to_cache);
        } else {
            preg_match('~flash_cache/([^/]+)/~', self::$url_to_cache, $matches);
            $host_to_remove = $matches[1] ?? '';
            $relative_path = str_replace($host_to_remove, '', self::$url_to_cache);
        }
    
        $path = trim(str_replace(self::$origin_url, '', $relative_path), '/');
        $cache_path = trailingslashit($cache_dir . flash_cache_get_server_name() . '/' . $path);
        $response = flash_cache_get_content(self::$url_to_cache);
        
        $parent_dir = $cache_dir . $server_name;
        if (!file_exists($parent_dir)) {
            
            @mkdir($parent_dir, 0777, true);
        }
        if (!file_exists($cache_path)) {
            
            @mkdir($cache_path, 0777, true);
        }

    
        if (!self::start_create_cache($cache_path)) {
            self::end_create_cache();
            return false;
        }

       
    
        
    
        if (empty($response['response'])) {
            return;
        }
    
        self::debug('Creating HTML cache file path:' . $path . ' - URL:' . self::$url_to_cache);
    
        $response['response'] = apply_filters('flash_cache_response_html', $response['response'], self::$url_to_cache);
    
        $gzip_html = gzencode($response['response']);
        file_put_contents($cache_path . 'index-cache.html', $response['response']);
        file_put_contents($cache_path . 'index-cache.html.gz', $gzip_html);
        flash_cache_increment_disk_usage(mb_strlen($response['response'], '8bit'));
        flash_cache_increment_disk_usage(mb_strlen($gzip_html, '8bit'));
    
        self::end_create_cache();
    }

	public static function create_cache_php() {
		global  $wp_query;
		if (is_null(self::$origin_url)) {
			self::$origin_url = get_site_url(null, '/');
		}
		self::$origin_url = flash_cache_sanitize_origin_url(self::$origin_url);
		$advanced_settings = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
		$home_path	 = flash_cache_get_home_path();
		$cache_dir	 = $home_path . $advanced_settings['cache_dir'];
	
		$parsed_url = parse_url(self::$url_to_cache);
		
        $relative_url = str_replace($parsed_url['scheme'] . '://' . $parsed_url['host'] . (isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ''), '', self::$url_to_cache);

		$path = str_replace(self::$origin_url, '', $relative_url);
		$cache_path = trailingslashit($cache_dir . flash_cache_get_server_name() . '/' . $path);
	
		if (!file_exists($cache_path)) {
			@mkdir($cache_path, 0777, true);
		}

		if (!self::start_create_cache($cache_path)) {
			self::end_create_cache();
			return false;
		}
		
		$cache_template = apply_filters('flash_cache_template_file', 'cache.tpl', $advanced_settings);

		self::debug('Creating PHP cache file path:' . $path . ' - URL:' . self::$url_to_cache);
		$template_php	 = file_get_contents(FLASH_CACHE_PLUGIN_DIR . 'includes/'.$cache_template);
		$template_php	 = str_replace('{home_path}', "'" . $home_path . "'", $template_php);
		$template_php	 = str_replace('{url_path}', "'" . self::$url_to_cache . "'", $template_php);
		$template_php	 = str_replace('{minimum_ttl}', self::$pattern['ttl_minimum'], $template_php);
		$template_php	 = str_replace('{maximum_ttl}', self::$pattern['ttl_maximum'], $template_php);
		$request_url = flash_cache_get_content_to_php(self::$url_to_cache);
        
		if (defined('FLASH_CACHE_NOT_USE_THIS_REQUEST')) {
			$request = hash('sha256', http_build_query(array()));
		} else {
			$request = hash('sha256', http_build_query($_REQUEST));
		}
        
		$request_path = 'requests/';
		if (!file_exists($cache_path . $request_path)) {
			@mkdir($cache_path . $request_path, 0777, true);
		}
       
        if (!file_exists($cache_path . 'index-cache.php')) {
			file_put_contents($cache_path . 'index-cache.php', $template_php);
			flash_cache_increment_disk_usage(mb_strlen($template_php, '8bit'));
		}
        
		$request_file_path	 = $cache_path . $request_path . $request . '.html';
		$header_file_path	 = $cache_path . $request_path . $request . '.header';

		if (is_search() && $wp_query->post_count > 0) {
			file_put_contents($request_file_path, $request_url['response']);
			file_put_contents($header_file_path, $request_url['content_type']);
		}elseif(!is_search()){
			file_put_contents($request_file_path, $request_url['response']);
			file_put_contents($header_file_path, $request_url['content_type']);
		}
       
		flash_cache_increment_disk_usage(mb_strlen($request_url['response'], '8bit'));
		flash_cache_increment_disk_usage(mb_strlen($request_url['content_type'], '8bit'));
		self::end_create_cache();
	}


	public static function process_cache_from_query($current_query, $opcional_url = '') {
		
		$current_url		 = '';
		$advanced_settings	 = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());

		self::$cache_type	 = 'html';
		$create_cache		 = false;
		$args				 = array('post_type' => 'flash_cache_patterns', 'orderby' => 'ID', 'order' => 'ASC', 'numberposts' => -1);
		$patterns			 = get_posts($args);
		$is_post			 = false;

		// We validate if it is post
		if ($current_query['is_single'] || $current_query['is_page']) {
			global $post;
			if (!isset($post)) {
				$post = get_post(self::$optional_post_id);
			}
			if (isset($post)) {
				self::$optional_post_id = $post->ID;
			}
			$is_post = true;
		}


		if (!empty($opcional_url)) {
			$current_url = $opcional_url;
		}

		if ($is_post && empty($current_url)) {
			$current_url = get_permalink($post->ID);
		}

		foreach ($patterns as $pt) {
			$pattern = flash_cache_patterns::get_data($pt->ID);

			$url_must_contain_array	 = array();
			$line_arr				 = explode("\n", $pattern['url_must_contain']);

			foreach ($line_arr as $key => $value) {
				$value = trim($value);
				if (!empty($value)) {
					$url_must_contain_array[] = $value;
				}
			}

			
			if (!empty($url_must_contain_array)) {
				foreach ($url_must_contain_array as $km => $url_must_contain) {
					if (stripos($current_url, $url_must_contain) === false) {
						continue 2;
					}
				}
			}

			$url_not_contain_array	 = array();
			$line_arr				 = explode("\n", $pattern['url_not_contain']);

			foreach ($line_arr as $key => $value) {
				$value = trim($value);
				if (!empty($value)) {
					$url_not_contain_array[] = $value;
				}
			}

			if (!empty($url_not_contain_array)) {
				foreach ($url_not_contain_array as $kc => $url_not_contain) {
					if (stripos($current_url, $url_not_contain) !== false) {
						continue 2;
					}
				}
			}


			if ($pattern['page_type']['single'] && ($current_query['is_single'] || $current_query['is_page']) && !$current_query['is_feed']) {
				if (empty($pattern['page_type']['posts'])) {
					$create_cache		 = true;
					self::$cache_type	 = $pattern['cache_type'];
					break;
				} else {
					foreach ($pattern['page_type']['posts'] as $pcpt => $pp) {
						if ($pcpt == $post->post_type) {
							$create_cache		 = true;
							self::$cache_type	 = $pattern['cache_type'];
							break;
						}
					}
				}
			}


			if ($pattern['page_type']['search'] && $current_query['is_search'] && !$current_query['is_feed']) {
				$create_cache		 = true;
				self::$cache_type	 = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['archives'] && $current_query['is_archive'] && !$current_query['is_feed']) {
				$create_cache		 = true;
				self::$cache_type	 = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['category'] && $current_query['is_category'] && !$current_query['is_feed']) {
				$create_cache		 = true;
				self::$cache_type	 = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['tag'] && $current_query['is_tag'] && !$current_query['is_feed']) {
				$create_cache		 = true;
				self::$cache_type	 = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['frontpage'] && $current_query['is_front_page']) {
				$create_cache		 = true;
				self::$cache_type	 = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['home'] && $current_query['is_home']) {
				$create_cache		 = true;
				self::$cache_type	 = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['author'] && $current_query['is_author'] && !$current_query['is_feed']) {
				$create_cache		 = true;
				self::$cache_type	 = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['feed'] && $current_query['is_feed']) {
				$create_cache		 = true;
				self::$cache_type	 = $pattern['cache_type'];
				break;
			}
		}

		if ($create_cache) {




			self::$current_query = $current_query;
			self::$url_to_cache	 = $current_url;
			self::$pattern		 = $pattern;
			
			$process_type = $advanced_settings['process_type'];
			if (!empty(self::$force_process_type)) {
				$process_type = self::$force_process_type;
			}

			

			if ($create_cache) {
				
				if ($process_type == 'ob_with_curl_request') {
					
					ob_start(array(__CLASS__, 'ob_callback'));
				} else {
					if (self::$cache_type == 'html') {
						self::create_cache_html();
					} else {
						self::create_cache_php();
					}
				}
			}
		}
	}

	public static function process_patterns() {
		
		if (isset($_COOKIE["flash_cache"]) || isset($_COOKIE["flash_cache_backend"])) {
			return true;
		}
		if ($_SERVER["REQUEST_METHOD"] == 'PUT') {
			return true;
		}
		if ($_SERVER["REQUEST_METHOD"] == 'DELETE') {
			return true;
		}
		if (isset($_GET['preview'])) {
			return true;
		}


		$general_settings	 = wp_parse_args(get_option('flash_cache_settings', array()), flash_cache_settings::default_general_options());
		$advanced_settings	 = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
		if (!$general_settings['activate']) {
			return true;
		}
		if (is_user_logged_in()) {
			return true;
		}
		reset($_COOKIE);
		foreach ($_COOKIE as $cookie => $val) {
			foreach ($advanced_settings['dont_cache_cookie'] as $key) {
				if (strpos($cookie, $key) !== FALSE) {
					return true;
				}
			}
		}

		self::$template_redirect = true;
		$default_query			 = flash_cache_default_query();
		$current_query			 = array();
		if (is_search()) {
			$current_query['is_search'] = 1;
		}
		if (is_page()) {
			$current_query['is_page'] = 1;
		}
		if (is_archive()) {
			$current_query['is_archive'] = 1;
		}
		if (is_tag()) {
			$current_query['is_tag'] = 1;
		}
		if (is_single()) {
			$current_query['is_single'] = 1;
		}
		if (is_category()) {
			$current_query['is_category'] = 1;
		}
		if (is_front_page()) {
			$current_query['is_front_page'] = 1;
		}
		if (is_home()) {
			$current_query['is_home'] = 1;
		}
		if (is_author()) {
			$current_query['is_author'] = 1;
		}
		if (is_feed()) {
			$current_query['is_feed'] = 1;
		}

		self::debug('Procesing a new pattern from template_redirect:' . var_export($current_query, true));
		$current_query = wp_parse_args($current_query, $default_query);
			
		self::process_cache_from_query($current_query, flash_cache_get_current_url());
	}

	public static function onload_cache() {
		if (empty($_GET['p'])) {
			exit;
		}
		if (empty($_GET['token'])) {
			exit;
		}
		if (is_null(self::$origin_url)) {
			self::$origin_url = get_site_url(null, '/');
		}

		$p		 = sanitize_text_field($_GET['p']);
		$token	 = sanitize_text_field($_GET['token']);

		$checksum = hash('sha256', $p . wp_salt('nonce'));
		// Validates token is valid
		if ($checksum != $token) {
			exit;
		}

		$url = base64_decode($p);
		$url = sanitize_url($url);

		if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
			exit;
		}

		$url_parse		 = parse_url($url);
		$origin_parse	 = parse_url(self::$origin_url);
		if ($url_parse['host'] != $origin_parse['host']) {
			exit;
		}

		do_action('onload_cache_file', $url);
		$advanced_settings	 = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
		$cache_dir			 = flash_cache_get_home_path() . $advanced_settings['cache_dir'];
		$path				 = str_replace(self::$origin_url, '', $url);
		$cache_path			 = trailingslashit($cache_dir . flash_cache_get_server_name() . '/' . $path);
		$cache_file			 = $cache_path . 'index-cache.html';
		if (file_exists($cache_file)) {
			$cache_response = file_get_contents($cache_file);
		} else {
			exit;
		}

		$minimum_ttl = flash_cache_get_var_javascript('pattern_minimum', $cache_response);

		if (!is_numeric($minimum_ttl)) {
			exit;
		}
		if (time() - filemtime($cache_file) < (int) $minimum_ttl) {
			exit;
		} else {
			$opcional_url				 = self::$origin_url . $path;
			$current_query				 = flash_cache_get_var_javascript('current_query', $cache_response);
			$current_query				 = json_decode(base64_decode($current_query), true);
			self::debug('Procesing a new pattern from onload_cache:' . var_export($current_query, true));
			self::$force_process_type	 = 'curl';
			self::$optional_post_id		 = flash_cache_get_var_javascript('flash_cache_optional_post_id', $cache_response);
			self::process_cache_from_query($current_query, $opcional_url);
		}
	}

	public static function ob_callback($buffer) {
		if (self::$template_redirect) {
			$use_curl = false;
		} else {
			$use_curl = true;
		}
		self::create_cache_from_ob($buffer, $use_curl);
		return $buffer;
	}

	/**
	 * Static function create_cache_from_ob
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function create_cache_from_ob($buffer, $use_curl) {
		self::$current_buffer	 .= $buffer;
		$new_cache				 = true;
		if (!preg_match(apply_filters('flash_cache_end_of_tags', '/(<\/html>|<\/rss>|<\/feed>|<\/urlset|<\?xml)/i'), self::$current_buffer)) {
			// No closing html tag. Not caching.
			$new_cache = false;
		}

		if (strpos($_SERVER['REQUEST_URI'], 'robots.txt') !== false) {
			// robots.txt detected. Not caching.
			$new_cache = false;
		}

		if ($new_cache) {

			if (self::$cache_type == 'html') {
				self::create_cache_ob_html(self::$current_buffer, $use_curl);
			} else {
				self::create_cache_ob_php(self::$current_buffer, $use_curl);
			}
		}
	}

	/**
	 * Static function create_cache_file_html
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function create_cache_ob_html($response, $use_curl) {

		$advanced_settings = flash_cache_get_advanced_settings();

		$cache_dir	 = flash_cache_get_home_path() . $advanced_settings['cache_dir'];
		$path		 = self::get_path(self::$optional_post_id);
		$cache_path	 = trailingslashit($cache_dir . flash_cache_get_server_name() . '/' . $path);

		if (!self::start_create_cache($cache_path)) {
			self::end_create_cache();
			return false;
		}

		if ($use_curl) {
			$response = flash_cache_get_content(self::$url_to_cache);
		}
		if (is_null(self::$origin_url)) {
			self::$origin_url = get_site_url(null, '/');
		}


		if (!file_exists($cache_path)) {
			@mkdir($cache_path, 0777, true);
		}


		self::debug('Creating OB HTML cache file path:' . $path . ' - URL:' . self::$url_to_cache);
		$response = apply_filters('flash_cache_response_html', $response, self::$url_to_cache);

		$gzip_response = gzencode($response);
		if (file_exists($cache_path.'index-cache.html') && file_exists($cache_path.'index-cache.html.gz')) {
			return false; 
		}
		file_put_contents($cache_path . 'index-cache.html', $response);
		file_put_contents($cache_path . 'index-cache.html.gz', $gzip_response);

		flash_cache_increment_disk_usage(mb_strlen($response, '8bit'));
		flash_cache_increment_disk_usage(mb_strlen($gzip_response, '8bit'));
		self::end_create_cache();
	}

	public static function create_cache_ob_php($response, $use_curl) {
		global  $wp_query;
		if (is_null(self::$origin_url)) {
			self::$origin_url = get_site_url(null, '/');
		}
		self::$origin_url = flash_cache_sanitize_origin_url(self::$origin_url);
		$advanced_settings	 = flash_cache_get_advanced_settings();
		$home_path			 = flash_cache_get_home_path();
		$cache_dir			 = $home_path . $advanced_settings['cache_dir'];
		$path		 		 = self::get_path(self::$optional_post_id);
		$cache_path			 = trailingslashit($cache_dir . flash_cache_get_server_name() . '/' . $path);
		
		if (!file_exists($cache_path)) {
			@mkdir($cache_path, 0777, true);
		}
		if (!self::start_create_cache($cache_path)) {
			self::end_create_cache();
			return false;
		}

		self::debug('Creating OB PHP cache file path:' . $path . ' - URL:' . self::$url_to_cache);

		$cache_template = apply_filters('flash_cache_template_file', 'cache.tpl', $advanced_settings);

		$template_php	 = file_get_contents(FLASH_CACHE_PLUGIN_DIR . 'includes/'.$cache_template);
		$template_php	 = str_replace('{home_path}', "'" . $home_path . "'", $template_php);
		$template_php	 = str_replace('{url_path}', "'" . self::$url_to_cache . "'", $template_php);
		$template_php	 = str_replace('{minimum_ttl}', self::$pattern['ttl_minimum'], $template_php);
		$template_php	 = str_replace('{maximum_ttl}', self::$pattern['ttl_maximum'], $template_php);
		
		if (!file_exists($cache_path . 'index-cache.php')) {
			file_put_contents($cache_path . 'index-cache.php', $template_php);
			flash_cache_increment_disk_usage(mb_strlen($template_php, '8bit'));
		}

		if ($use_curl) {
			$response = flash_cache_get_content(self::$url_to_cache);
		}
		if (defined('FLASH_CACHE_NOT_USE_THIS_REQUEST')) {
			$request = hash('sha256', http_build_query(array()));
		} else {
			$request = hash('sha256', http_build_query($_REQUEST));
		}

		$request_path = 'requests/';
		if (!file_exists($cache_path . $request_path)) {
			@mkdir($cache_path . $request_path, 0777, true);
		}
		$request_file_path		 = $cache_path . $request_path . $request . '.html';
		$header_file_path		 = $cache_path . $request_path . $request . '.header';
		$current_content_type	 = 'text/html; charset=UTF-8';
		$headers				 = headers_list();
		foreach ($headers as $header) {
			if (stripos($header, 'Content-Type') !== FALSE) {
				$headerParts			 = explode(':', $header);
				$current_content_type	 = trim($headerParts[1]);
				break;
			}
		}
		if (is_search() && $wp_query->post_count > 0) {
			// A search query has been performed.
			file_put_contents($request_file_path, $response);
			file_put_contents($header_file_path, $current_content_type);
		}elseif(!is_search()){
			file_put_contents($request_file_path, $response);
			file_put_contents($header_file_path, $current_content_type);
		}
		flash_cache_increment_disk_usage(mb_strlen($response, '8bit'));
		flash_cache_increment_disk_usage(mb_strlen($current_content_type, '8bit'));

		self::end_create_cache();
	}

	/**
	 * Static function get_path
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function get_path($post_id = 0, $process_type = 'ob_with_curl_request') {
		$uri = '';
		if ($post_id != 0) {
			$site_url	 = site_url();
			$permalink	 = get_permalink($post_id);
			if (false === strpos($permalink, $site_url)) {

				self::debug("flash_cache_get_path: warning! site_url ($site_url) not found in permalink ($permalink).");
				if (false === strpos($permalink, htmlentities($_SERVER['HTTP_HOST']))) {
					wp_cache_debug("flash_cache_get_path: WARNING! SERVER_NAME ({$WPSC_HTTP_HOST}) not found in permalink ($permalink). ");
					$p = parse_url($permalink);
					if (is_array($p)) {
						$uri = $p['path'];
						self::debug("flash_cache_get_path: WARNING! Using $uri as permalink. Used parse_url.");
					} else {
						self::debug("flash_cache_get_path: WARNING! Permalink ($permalink) could not be understood by parse_url. Using front page.");
						$uri = '';
					}
				} else {
					self::debug("flash_cache_get_path: Removing SERVER_NAME ({$WPSC_HTTP_HOST}) from permalink ($permalink). Is the url right?");
					$uri = str_replace(htmlentities($_SERVER['HTTP_HOST']), '', $permalink);
					$uri = str_replace('http://', '', $uri);
					$uri = str_replace('https://', '', $uri);
				}
			} else {
				$uri = str_replace($site_url, '', $permalink);
			}
		} else {
			if (is_null(self::$origin_url)) {
				self::$origin_url = get_site_url(null, '/');
			}
			self::$origin_url = flash_cache_sanitize_origin_url(self::$origin_url);
			if ($process_type == 'ob_with_curl_request' && false === strpos(self::$url_to_cache, self::$origin_url)) {
				$request_URI = sanitize_url(wp_unslash($_SERVER['REQUEST_URI']));
				$uri = strtolower($request_URI);
			} else {
				$uri = str_replace(self::$origin_url, '', self::$url_to_cache);
			}
		}
		return $uri;
	}

}

flash_cache_process::hooks();
