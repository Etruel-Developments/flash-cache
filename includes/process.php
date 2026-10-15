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

	public static $origin_url		  = null;
	public static $template_redirect  = false;
	public static $optional_post_id	  = 0;
	public static $current_query	  = array();
	public static $url_to_cache		  = '';
	public static $pattern			  = null;
	public static $cache_type		  = 'html';
	public static $first_ob_output	  = true;
	public static $current_buffer	  = '';
	public static $force_process_type = null;
	public static $force_permalink	  = false;
	public static $advanced_settings  = null;

	/** Locks held by this request, newest last: key => file handle, or true for a DB lock. */
	private static $held_locks = array();

	/* Added for validation in optimize process. */
	public static $current_pattern = array();

	public static function hooks() {
		add_action('template_redirect', array(__CLASS__, 'process_patterns'));
		add_action('admin_post_nopriv_onload_flash_cache', array(__CLASS__, 'onload_cache'));
		add_action('admin_post_onload_flash_cache', array(__CLASS__, 'onload_cache'));
		add_filter('flash_cache_response_html', array(__CLASS__, 'cache_response_html'), 100, 2);
	}

	/**
	 * Takes the generation lock for a page (or any named job) without waiting. Returns false
	 * when another request holds it or the lock cannot be taken; the caller then skips the work.
	 * Every successful call must be paired with end_create_cache() on every return path.
	 */
	public static function start_create_cache($key) {
		$key = md5(wp_normalize_path((string) $key));
		if (isset(self::$held_locks[$key])) {
			return false;
		}
		$taken = flash_cache_get_advanced_settings()['lock_type'] === 'db' ? self::get_db_lock($key) : self::get_file_lock($key);
		if (false === $taken) {
			return false;
		}
		self::$held_locks[$key] = $taken;
		return true;
	}

	/**
	 * Releases the lock taken for $key, or the newest one when no key is given.
	 */
	public static function end_create_cache($key = null) {
		if (null === $key) {
			end(self::$held_locks);
			$key = key(self::$held_locks);
		} else {
			$key = md5(wp_normalize_path((string) $key));
		}
		if (null === $key || !isset(self::$held_locks[$key])) {
			return false;
		}
		$lock = self::$held_locks[$key];
		unset(self::$held_locks[$key]);
		if (true === $lock) {
			global $wpdb;
			$wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::db_lock_name($key)));
		} elseif (is_resource($lock)) {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		return true;
	}

	/**
	 * A non-blocking flock() on a file under the cache folder's locks/ directory. Returns the
	 * handle, or false when the folder is not writable or the lock is taken.
	 */
	private static function get_file_lock($key) {
		$locks_dir = flash_cache_get_cache_root() . 'locks/';
		if (!is_dir($locks_dir) && !@mkdir($locks_dir, 0777, true) && !is_dir($locks_dir)) {
			return false;
		}
		$handle = @fopen($locks_dir . $key . '.lock', 'c');
		if (false === $handle) {
			return false;
		}
		if (!flock($handle, LOCK_EX | LOCK_NB)) {
			fclose($handle);
			return false;
		}
		return $handle;
	}

	/**
	 * A MySQL named lock: atomic, released with the connection, and independent of the
	 * transaction isolation level.
	 */
	private static function get_db_lock($key) {
		global $wpdb;
		return '1' === (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', self::db_lock_name($key))) ? true : false;
	}

	private static function db_lock_name($key) {
		global $wpdb;
		return 'flash_cache_' . md5($wpdb->prefix . $key);
	}

	/**
	 * Writes to the PHP error log only when FLASH_CACHE_DEBUG is true, so a production request
	 * costs nothing and no log file sits in a web-readable folder.
	 */
	public static function debug($message) {
		if (!defined('FLASH_CACHE_DEBUG') || !FLASH_CACHE_DEBUG) {
			return;
		}
		if (is_array($message) || is_object($message)) {
			$message = print_r($message, true);
		}
		$request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_url(wp_unslash($_SERVER['REQUEST_URI'])) : '';
		error_log('Flash Cache [' . getmypid() . '] ' . $request_uri . ' ' . $message);
	}

	/**
	 * Static function cache_response_html
	 * @access public
	 * @return String $response with source code of a html object
	 * @since 1.0.0
	 */
	/**
	 * Adds the refresh script to a cached HTML page. The browser compares the generation time
	 * with the pattern's minimum TTL and calls the server only once the page has expired.
	 * onload_cache() reads pattern_minimum, current_query and flash_cache_optional_post_id
	 * back from this markup, so their "name = value;" form must stay as it is.
	 */
	public static function cache_response_html($response, $url_to_cache) {
		if (flash_cache_get_advanced_settings()['refresh_mode'] === 'cron') {
			return $response;
		}
		$p			 = base64_encode(self::$url_to_cache);
		$refresh_url = admin_url('admin-post.php?action=onload_flash_cache&p=' . rawurlencode($p) . '&token=' . hash('sha256', $p . wp_salt('nonce')));

		$script = '<script>(function () {
var flash_cache_generated = ' . time() . ';
var pattern_minimum = ' . absint(self::$pattern['ttl_minimum'] ?? 0) . ';
var current_query = "' . base64_encode(wp_json_encode(self::$current_query)) . '";
var flash_cache_optional_post_id = ' . absint(self::$optional_post_id) . ';
if (Date.now() / 1000 - flash_cache_generated < pattern_minimum) { return; }
window.addEventListener("load", function () { var s = document.createElement("script"); s.async = true; s.src = ' . wp_json_encode($refresh_url) . '; document.body.appendChild(s); });
})();</script>';
		return str_replace('</body>', $script . '</body>', $response);
	}

	public static function create_cache_html() {
		if (is_null(self::$origin_url)) {
			self::$origin_url = get_site_url(null, '/');
		}
		self::$origin_url = flash_cache_sanitize_origin_url(self::$origin_url);

		$cache_dir	 = flash_cache_get_cache_root();
		$server_name = flash_cache_get_server_name();
		$path		 = flash_cache_url_to_path(self::$url_to_cache);
		$cache_path = trailingslashit($cache_dir . $server_name . '/' . $path);
		if (!file_exists($cache_path)) {
			@mkdir($cache_path, 0777, true);
		}

		// Another request is building this page; it will write the same content.
		if (!self::start_create_cache($cache_path)) {
			return false;
		}
		$response = flash_cache_get_content(self::$url_to_cache);
		if (!empty($response['response'])) {
			self::debug('Creating HTML cache file path:' . $path . ' - URL:' . self::$url_to_cache);
			self::write_html_cache($cache_path, apply_filters('flash_cache_response_html', $response['response'], self::$url_to_cache));
		}
		self::end_create_cache($cache_path);
	}

	public static function write_html_cache($cache_path, $html) {
		$gzip_html = gzencode($html);
		file_put_contents($cache_path . 'index-cache.html', $html);
		file_put_contents($cache_path . 'index-cache.html.gz', $gzip_html);
		flash_cache_increment_disk_usage(mb_strlen($html, '8bit') + mb_strlen($gzip_html, '8bit'));
	}

	public static function create_cache_php() {
		if (is_null(self::$origin_url)) {
			self::$origin_url = get_site_url(null, '/');
		}
		self::$origin_url = flash_cache_sanitize_origin_url(self::$origin_url);
		$cache_dir		  = flash_cache_get_cache_root();

		$path		= flash_cache_url_to_path(self::$url_to_cache);
		$cache_path = trailingslashit($cache_dir . flash_cache_get_server_name() . '/' . $path);

		if (!file_exists($cache_path)) {
			@mkdir($cache_path, 0777, true);
		}
		if (!self::start_create_cache($cache_path)) {
			return false;
		}

		self::debug('Creating PHP cache file path:' . $path . ' - URL:' . self::$url_to_cache);
		$response = flash_cache_get_content_to_php(self::$url_to_cache);
		if (!empty($response['response'])) {
			self::write_php_cache($cache_path, $response['response'], $response['content_type']);
		}
		self::end_create_cache($cache_path);
	}

	/**
	 * The generated index-cache.php. Every value is written as a PHP literal, never as raw text.
	 */
	public static function php_cache_template() {
		$cache_template = apply_filters('flash_cache_template_file', FLASH_CACHE_PLUGIN_DIR . 'includes/cache.tpl', flash_cache_get_advanced_settings());
		return strtr((string) file_get_contents($cache_template), array(
			'{home_path}'	=> var_export((string) flash_cache_get_home_path(), true),
			'{url_path}'	=> var_export((string) self::$url_to_cache, true),
			'{minimum_ttl}' => (string) absint(self::$pattern['ttl_minimum']),
			'{maximum_ttl}' => (string) absint(self::$pattern['ttl_maximum']),
		));
	}

	/**
	 * Cache key of a request in PHP mode: the query string without tracking parameters, in a
	 * stable order. includes/cache.tpl computes the same key; both must change together.
	 */
	public static function request_key(array $query) {
		foreach (array_keys($query) as $name) {
			if (preg_match('/^(utm_[a-z]+|fbclid|gclid|gclsrc|dclid|msclkid|mc_cid|mc_eid|_ga|_gl)$/i', (string) $name)) {
				unset($query[$name]);
			}
		}
		ksort($query);
		return hash('sha256', http_build_query($query));
	}

	/**
	 * Writes the PHP-mode files of one page: index-cache.php once, and the response of this
	 * request under requests/. A page keeps at most flash_cache_max_request_files variants.
	 */
	public static function write_php_cache($cache_path, $html, $content_type) {
		global $wp_query;
		if (is_search() && (empty($wp_query) || $wp_query->post_count == 0)) {
			return false;
		}

		$template_file = $cache_path . 'index-cache.php';
		if (!file_exists($template_file)) {
			$template_php = self::php_cache_template();
			file_put_contents($template_file, $template_php);
			flash_cache_increment_disk_usage(mb_strlen($template_php, '8bit'));
		}

		$requests_path = $cache_path . 'requests/';
		if (!file_exists($requests_path)) {
			@mkdir($requests_path, 0777, true);
		}
		$key		  = defined('FLASH_CACHE_NOT_USE_THIS_REQUEST') ? self::request_key(array()) : self::request_key(wp_unslash($_GET));
		$request_file = $requests_path . $key . '.html';
		if (!file_exists($request_file)) {
			$max_files = (int) apply_filters('flash_cache_max_request_files', 100, $cache_path);
			$existing  = glob($requests_path . '*.html');
			if (is_array($existing) && count($existing) >= $max_files) {
				return false;
			}
		}

		file_put_contents($request_file, $html);
		file_put_contents($requests_path . $key . '.header', $content_type);
		flash_cache_increment_disk_usage(mb_strlen($html, '8bit') + mb_strlen($content_type, '8bit'));
		return true;
	}

	public static function process_cache_from_query($current_query, $opcional_url = '') {

		$current_url	   = '';
		$advanced_settings = flash_cache_get_advanced_settings();

		self::$cache_type = 'html';
		$create_cache	  = false;
		$patterns		  = flash_cache_patterns::get_all();
		$is_post		  = false;

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

		foreach ($patterns as $pattern) {

			$url_must_contain_array = array();
			$line_arr				= explode("\n", $pattern['url_must_contain']);

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

			$url_not_contain_array = array();
			$line_arr			   = explode("\n", $pattern['url_not_contain']);

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
					$create_cache	  = true;
					self::$cache_type = $pattern['cache_type'];
					break;
				} else {
					foreach ($pattern['page_type']['posts'] as $pcpt => $pp) {
						if ($pcpt == $post->post_type) {
							$create_cache	  = true;
							self::$cache_type = $pattern['cache_type'];
							break;
						}
					}
				}
			}


			if ($pattern['page_type']['search'] && $current_query['is_search'] && !$current_query['is_feed']) {
				$create_cache	  = true;
				self::$cache_type = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['archives'] && $current_query['is_archive'] && !$current_query['is_feed']) {
				$create_cache	  = true;
				self::$cache_type = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['category'] && $current_query['is_category'] && !$current_query['is_feed']) {
				$create_cache	  = true;
				self::$cache_type = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['tag'] && $current_query['is_tag'] && !$current_query['is_feed']) {
				$create_cache	  = true;
				self::$cache_type = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['frontpage'] && $current_query['is_front_page']) {
				$create_cache	  = true;
				self::$cache_type = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['home'] && $current_query['is_home']) {
				$create_cache	  = true;
				self::$cache_type = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['author'] && $current_query['is_author'] && !$current_query['is_feed']) {
				$create_cache	  = true;
				self::$cache_type = $pattern['cache_type'];
				break;
			}
			if ($pattern['page_type']['feed'] && $current_query['is_feed']) {
				$create_cache	  = true;
				self::$cache_type = $pattern['cache_type'];
				break;
			}
		}

		// A static HTML copy is served for the bare URL, so it must come from a request without a
		// query string (tracking parameters aside); PHP mode keeps one copy per query instead.
		if ($create_cache && self::$cache_type === 'html' && self::$template_redirect && self::request_key(wp_unslash($_GET)) !== self::request_key(array())) {
			$create_cache = false;
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
		if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', array('GET', 'HEAD'), true)) {
			return true;
		}
		if (isset($_GET['preview'])) {
			return true;
		}

		$general_settings  = flash_cache_get_general_settings();
		$advanced_settings = flash_cache_get_advanced_settings();
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

		self::debug(array('Processing a new pattern from template_redirect' => $current_query));
		$current_query = wp_parse_args($current_query, $default_query);

		self::process_cache_from_query($current_query, flash_cache_get_current_url());
	}

	public static function onload_cache() {
		nocache_headers();
		header('Content-Type: application/javascript; charset=UTF-8');
		if (empty($_GET['p'])) {
			exit;
		}
		if (empty($_GET['token'])) {
			exit;
		}
		if (is_null(self::$origin_url)) {
			self::$origin_url = get_site_url(null, '/');
		}

		$p	   = sanitize_text_field($_GET['p']);
		$token = sanitize_text_field($_GET['token']);

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

		$url_parse	  = parse_url($url);
		$origin_parse = parse_url(self::$origin_url);
		if ($url_parse['host'] != $origin_parse['host']) {
			exit;
		}

		do_action('onload_cache_file', $url);
		$advanced_settings = flash_cache_get_advanced_settings();
		$cache_dir		   = flash_cache_get_cache_root();
		$path			   = flash_cache_url_to_path($url);
		$cache_path		   = trailingslashit($cache_dir . flash_cache_get_server_name() . '/' . $path);
		$cache_file		   = $cache_path . 'index-cache.html';
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
			if (!self::claim_refresh($url, min(300, max(30, (int) $minimum_ttl)))) {
				exit;
			}
			if (function_exists('fastcgi_finish_request')) {
				fastcgi_finish_request();
			}

			$opcional_url			  = $url;
			$current_query			  = flash_cache_get_var_javascript('current_query', $cache_response);
			$current_query			  = json_decode(base64_decode($current_query), true);
			self::debug(array('Processing a new pattern from onload_cache' => $current_query));
			self::$force_process_type = 'curl';
			self::$optional_post_id	  = flash_cache_get_var_javascript('flash_cache_optional_post_id', $cache_response);
			self::process_cache_from_query($current_query, $opcional_url);
		}
	}

	/**
	 * One refresh per page at a time: the first request inserts the claim row with INSERT
	 * IGNORE (add_option() upserts, so it cannot arbitrate), later ones give up until the
	 * claim is $window seconds old.
	 */
	public static function claim_refresh($url, $window) {
		global $wpdb;
		$name = 'flash_cache_refresh_' . md5($url);
		if ($wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, time())) === 1) {
			return true;
		}
		$claimed_at = (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name));
		if (time() - $claimed_at < $window) {
			return false;
		}
		$taken = $wpdb->update($wpdb->options, array('option_value' => time()), array('option_name' => $name, 'option_value' => $claimed_at));
		wp_cache_delete($name, 'options');
		return $taken === 1;
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
		self::$current_buffer .= $buffer;
		$new_cache			  = true;
		if (!preg_match(apply_filters('flash_cache_end_of_tags', '/(<\/html>|<\/rss>|<\/feed>|<\/urlset|<\?xml)/i'), self::$current_buffer)) {
			// No closing html tag. Not caching.
			$new_cache = false;
		}

		if (strpos($_SERVER['REQUEST_URI'], 'robots.txt') !== false) {
			$new_cache = false;
		}
		// Error pages, redirects and other non-200 responses are never stored.
		$status = http_response_code();
		if (false !== $status && 200 !== $status) {
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
		if (is_null(self::$origin_url)) {
			self::$origin_url = get_site_url(null, '/');
		}
		$path		= self::get_path(self::$optional_post_id);
		$cache_path = trailingslashit(flash_cache_get_cache_root() . flash_cache_get_server_name() . '/' . $path);
		if (file_exists($cache_path . 'index-cache.html') && file_exists($cache_path . 'index-cache.html.gz')) {
			return false;
		}
		if (!file_exists($cache_path)) {
			@mkdir($cache_path, 0777, true);
		}
		if (!self::start_create_cache($cache_path)) {
			return false;
		}

		if ($use_curl) {
			$response = flash_cache_get_content(self::$url_to_cache);
			$response = $response['response'] ?? '';
		}
		if (!empty($response)) {
			self::debug('Creating OB HTML cache file path:' . $path . ' - URL:' . self::$url_to_cache);
			self::write_html_cache($cache_path, apply_filters('flash_cache_response_html', $response, self::$url_to_cache));
		}
		self::end_create_cache($cache_path);
	}

	public static function create_cache_ob_php($response, $use_curl) {
		if (is_null(self::$origin_url)) {
			self::$origin_url = get_site_url(null, '/');
		}
		self::$origin_url = flash_cache_sanitize_origin_url(self::$origin_url);
		$path			  = self::get_path(self::$optional_post_id);
		$cache_path		  = trailingslashit(flash_cache_get_cache_root() . flash_cache_get_server_name() . '/' . $path);

		if (!file_exists($cache_path)) {
			@mkdir($cache_path, 0777, true);
		}
		if (!self::start_create_cache($cache_path)) {
			return false;
		}

		self::debug('Creating OB PHP cache file path:' . $path . ' - URL:' . self::$url_to_cache);
		if ($use_curl) {
			$response = flash_cache_get_content(self::$url_to_cache);
			$response = $response['response'] ?? '';
		}

		$content_type = 'text/html; charset=UTF-8';
		foreach (headers_list() as $header) {
			if (stripos($header, 'Content-Type:') === 0) {
				$content_type = trim(substr($header, strlen('Content-Type:')));
				break;
			}
		}
		if (!empty($response)) {
			self::write_php_cache($cache_path, $response, $content_type);
		}
		self::end_create_cache($cache_path);
	}

	/**
	 * The cache folder of the page being cached, relative to the host folder: the post's
	 * permalink when a post is given, the URL being cached otherwise.
	 */
	public static function get_path($post_id = 0, $process_type = 'ob_with_curl_request') {
		if ($post_id != 0) {
			return flash_cache_url_to_path(get_permalink($post_id));
		}
		if (self::$url_to_cache !== '') {
			return flash_cache_url_to_path(self::$url_to_cache);
		}
		return flash_cache_url_to_path(isset($_SERVER['REQUEST_URI']) ? sanitize_url(wp_unslash($_SERVER['REQUEST_URI'])) : '/');
	}
}

flash_cache_process::hooks();
