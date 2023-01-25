<?php

class flash_cache_preaload {

	public static $start_time_callback	 = null;
	public static $can_cron_handler		 = null;

	/**
	 * Static function hooks
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function hooks() {
		add_action('admin_menu', array(__CLASS__, 'admin_menu'));
		add_action('admin_post_save_flash_cache_preload', array(__CLASS__, 'save'));
		add_filter('cron_schedules', array(__CLASS__, 'intervals')); //add cron intervals
		add_action('flash_cache_cron', array(__CLASS__, 'cron_callback'));  //Actions for Cron job
		//test if cron active
		if (!wp_next_scheduled('flash_cache_cron')) {
			wp_schedule_event(time(), 'flash_cache_preload_int', 'flash_cache_cron');
		}
		// It's reset values on preload options to execute without wait.
		add_action('admin_post_save_flash_cache_preload_execution', array(__CLASS__, 'execute_preload'));
		add_action('admin_post_reset_to_default_preload', array(__CLASS__, 'reset_to_default_preload'));
	}

	

	/**
	 * Static function end_cron
	 * @access public
	 * @return void
	 * @since 1.2.1
	 */
	public static function end_cron() {
		fwrite(self::$can_cron_handler, '');
		flock(self::$can_cron_handler, LOCK_UN);
		fclose(self::$can_cron_handler);
	}

	/**
	 * Static function check_time_exection
	 * @access public
	 * @return void
	 * @since version
	 */
	public static function check_time_exection() {
		$ret = true;
		if (empty(self::$start_time_callback)) {
			self::$start_time_callback = microtime(true);
		}
		$time_taken = microtime(true) - self::$start_time_callback;
		if ($time_taken >= 57) {
			$ret = false;
		}
		$preload_now = get_option('flash_cache_preload_now', false);
		if ($preload_now) {
			$ret = false;
		}
		return $ret;
	}

	/**
	 * Static function intervals
	 * Add cron interval
	 * @access public
	 * @param array $schedules
	 * @return array
	 * @since 1.0.0
	 */
	public static function intervals($schedules) {
		$schedules['flash_cache_preload_int'] = array('interval' => '60', 'display' => __('Flash Cache Preload', 'flash-cache'));
		return $schedules;
	}

	/**
	 * Static function default_options_cron
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function default_options_cron() {
		$array	 = array(
			'started'			 => false,
			'finished'			 => true,
			'execution_offeset'	 => 0,
			'next_run'			 => time(),
		);
		$array	 = apply_filters('flash_cache_default_preload_cron_options', $array);
		return $array;
	}

	/**
	 * Static function cron_callback
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function cron_callback() {

		$values_general = wp_parse_args(get_option('flash_cache_settings', array()), flash_cache_settings::default_general_options());
		if (!$values_general['activate']) {
			return true;
		}

		
		if (!flash_cache_process::start_create_cache(FLASH_CACHE_PLUGIN_DIR)) {
			flash_cache_process::end_create_cache();
			return false;
		}

		$in_while	 = true;
		$values_cron = wp_parse_args(get_option('flash_cache_preload_cron', array()), self::default_options_cron());

		if ($values_cron['next_run'] > time()) {
			$in_while = false;
		}

		if ($in_while) {
			self::execution_callback();
		}

		update_option('flash_cache_preload_now', false);
		flash_cache_process::end_create_cache();
	}

	/**
	 * Static function execution_callback
	 * @access public
	 * @return void
	 * @since version
	 */
	public static function execution_callback() {
		global $wpdb;
		
		$values_general = wp_parse_args(get_option('flash_cache_settings', array()), flash_cache_settings::default_general_options());

		$values_settings = wp_parse_args(get_option('flash_cache_preload', array()), self::default_options());
		if ($values_settings['activate']) {
			$values_cron = wp_parse_args(get_option('flash_cache_preload_cron', array()), self::default_options_cron());

			if ($values_cron['next_run'] < time() && $values_cron['finished']) {
				$values_cron['started']				 = true;
				$values_cron['execution_offeset']	 = 0;
				$values_cron['finished']			 = false;
				update_option('flash_cache_preload_cron', $values_cron);
			}

			if ($values_cron['next_run'] > time() || !$values_cron['started']) {
				return true;
			}

			$execution_offeset	 = absint($values_cron['execution_offeset']);

			$args		 = array('post_type' => 'flash_cache_patterns', 'orderby' => 'ID', 'order' => 'ASC', 'numberposts' => -1);
			$patterns	 = get_posts($args);

			if (is_null(flash_cache_process::$origin_url)) {
				flash_cache_process::$origin_url = get_site_url(null, '/');
			}

			$advanced_settings		 = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
			$cache_dir				 = flash_cache_get_home_path() . $advanced_settings['cache_dir'];
			$default_posts_per_page	 = get_option('posts_per_page', 10);

			/** Prepare WHERE statement like wp-admin\includes\export.php:111 */
			$post_types	 = get_post_types(array('public' => true));
			foreach ($post_types as $kpt => $ptype) {
				$post_types[$kpt] = sanitize_text_field($ptype);
			}
			$types      = array_fill( 0, count( $post_types ), '%s' );
			$where = $wpdb->prepare( "( post_type IN ( " . implode( ',',  $types  ) . " ) ) AND post_status = 'publish'", $post_types );

			$posts = $wpdb->get_col(
					$wpdb->prepare(
							"SELECT ID FROM {$wpdb->posts} WHERE $where ORDER BY ID ASC LIMIT %d, %d",
							absint($execution_offeset),
							absint($values_settings['pages_per_execution'])
					)
			);
			
			foreach ($posts as $k => $post_id) {
				if (!self::check_time_exection()) {
					return false;
				}
				$post = get_post( $post_id );
				$current_url	 = get_permalink($post_id);
				update_option('flash_cache_preload_current_post', $current_url);
				$create_cache	 = false;

				foreach ($patterns as $pt) {
					$pattern				 = flash_cache_patterns::get_data($pt->ID);
					$url_must_contain_array	 = array();
					$line_arr				 = explode("\n", $pattern['url_must_contain']);

					foreach ($line_arr as $key => $value) {
						$value = trim($value);
						if (!empty($value)) {
							$ramdom_rewrites_array[] = $value;
						}
					}

					foreach ($url_must_contain_array as $km => $url_must_contain) {
						if (stripos($current_url, $url_must_contain) === false) {
							continue;
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

					foreach ($url_not_contain_array as $kc => $url_not_contain) {
						if (stripos($current_url, $url_not_contain) !== false) {
							continue;
						}
					}

					if ($pattern['page_type']['single']) {
						if (empty($pattern['page_type']['posts'])) {
							$create_cache = true;
							break;
						} else {
							foreach ($pattern['page_type']['posts'] as $pcpt => $pp) {
								if ($pcpt == $post->post_type) {
									$create_cache = true;
									break;
								}
							}
						}
					}
				}

				$path		 = str_replace(flash_cache_process::$origin_url, '', $current_url);
				$cache_path	 = $cache_dir . flash_cache_get_server_name() . '/' . $path;
				$cache_file	 = $cache_path . 'index-cache.html';

				if (file_exists($cache_file)) {
					if (time() - filemtime($cache_file) < (int) $pattern['ttl_maximum']) {
						continue;
					}
				}
				if ($create_cache) {
					$post = null;
					flash_cache_posts::create_cache_post_id($post_id);
					if ($values_settings['cache_taxonomies']) {
						flash_cache_posts::update_taxonomies($post_id, $pattern['ttl_maximum'], $default_posts_per_page, $cache_dir, true);
					}
				}
				
			}
			

			$values_cron['next_run'] = time() + intval($values_settings['time_per_preload']);
			if (empty($posts)) {
				$values_cron['started']	 = false;
				$values_cron['finished'] = true;
				if ($values_settings['disable_preload_finish']) {
					$values_settings['activate'] = false;
					update_option('flash_cache_preload', $values_settings);
				}
				
			}
			$values_cron['execution_offeset'] = (int) $values_cron['execution_offeset'] + (int) $values_settings['pages_per_execution'];

			update_option('flash_cache_preload_cron', $values_cron);
		}
	}

	public static function admin_menu() {

		$page = add_submenu_page(
				'flash_cache_setting',
				__('Preload', 'flash-cache'),
				__('Preload', 'flash-cache'),
				'manage_options',
				'flash_cache_preload',
				array(__CLASS__, 'page')
		);
	}

	/**
	 * Static function default_options
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function default_options() {
		$array	 = array(
			'activate'				 => false,
			'cache_taxonomies'		 => true,
			'pages_per_execution'	 => 100,
			'time_per_preload'		 => 300,
			'disable_preload_finish' => false,
		);
		$array	 = apply_filters('flash_cache_default_preload_options', $array);
		return $array;
	}

	/**
	 * Static function execute_preload_html
	 * @access public
	 * @return void
	 * @since 0.7
	 */
	public static function execute_preload_html() {
		echo '<form action="' . admin_url('admin-post.php') . '" id="form_flash_cache_preload_execution" method="post">
				<div class="wpm_head ml-31-i"><div class="wpm_buttons">
					<input type="hidden" name="action" value="save_flash_cache_preload_execution"/>';
		wp_nonce_field('save_flash_cache_preload_execution');
		submit_button(__('Execute Preload', 'flash-cache'));
		echo '</div></div></form>';
	}

	/**
	 * Static function page
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function page() {

		$values = wp_parse_args(get_option('flash_cache_preload', array()), self::default_options());

		echo '<div class="wrap wpm_container show_menu">
				<div class="wpm_header">
				<h1>' . __('Preload', 'flash-cache') . '</h1>
				<p class="description">' . __('Preload is a process that creates a cache of all public pages and taxonomies depending on your options and patterns.', 'flash-cache') . '</p>
				<p class="description">' . __('The preload process is divided into several executions to prevent server collapse.', 'flash-cache') . '</p>
				</div>
				<div class="postbox">';

		echo '<div class="wpm_flex">';

		echo flash_cache_get_menus();

		echo '<div class="wpm_main">';
		if ($values['activate']) {
			self::execute_preload_html();
		}
		echo '<form action="' . admin_url('admin-post.php') . '" id="form_flash_cache_preload" method="post">
					<input type="hidden" name="action" value="save_flash_cache_preload"/>';
		wp_nonce_field('save_flash_cache_preload');

		if ($values['activate']) {
			$values_cron		 = wp_parse_args(get_option('flash_cache_preload_cron', array()), self::default_options_cron());
			$execution_offeset	 = absint($values_cron['execution_offeset']);
			$next_run			 = absint($values_cron['next_run']);

			if ( $values_cron['started'] && !$values_cron['finished']) {
				$current_post_url	 = get_option('flash_cache_preload_current_post', '');
				$next_page			 = ( absint($values_cron['execution_offeset']) + absint($values['pages_per_execution']) );

				echo '<div class="preload_info"><code>' . sprintf(__('Preload is executing now: %s - %s - %s', 'flash-cache'), esc_attr($execution_offeset), esc_attr($next_page), esc_url($current_post_url)) . '</code></div>';
			} else {
				if ($next_run < time() && $values_cron['finished'] && !$values_cron['started']) {
					echo '<div class="preload_info"><code>' . __('Preload is pending to execution.', 'flash-cache') . '</code></div>';
				} else {
					if ($next_run > time() && $values_cron['finished'] && !$values_cron['started']) {
						echo '<div class="preload_info"><code>' . sprintf(__('Next run: %s', 'flash-cache'), esc_attr(date('Y-m-d H:i:s', $next_run))) . '</code></div>';
					}
				}
			}
		}
		echo '<table class="form-table">
				<tr valign="top">
					<th scope="row">' . __('Enable Preload', 'flash-cache') . '</th>
					<td>
						<div class="switch switch--horizontal switch--no-label">
							<input type="radio" ' . checked($values['activate'], false, false) . ' name="flash_cache_preload[activate]" value="0"/> Off
							<label for="flash_cache_preload[activate]">Off</label>
							<input type="radio" ' . checked($values['activate'], true, false) . ' name="flash_cache_preload[activate]" value="1"/> On
							<label for="flash_cache_preload[activate]">On</label>
							<span class="toggle-outside">
								<span class="toggle-inside"></span>
							</span>
						</div>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">' . __('Cache Taxonomies', 'flash-cache') . '</th>
					<td>
						<div class="switch switch--horizontal switch--no-label">
							<input type="radio" ' . checked($values['cache_taxonomies'], false, false) . ' name="flash_cache_preload[cache_taxonomies]" value="0"/> Off 
							<label for="flash_cache_preload[cache_taxonomies]">Off</label>
							<input type="radio" ' . checked($values['cache_taxonomies'], true, false) . ' name="flash_cache_preload[cache_taxonomies]" value="1"/> On 
							<label for="flash_cache_preload[cache_taxonomies]">On</label>
							<span class="toggle-outside">
								<span class="toggle-inside"></span>
							</span>
						</div>
						<p class="description">' . __('By activating this option the preload process will create cache of every public taxonomies (Categories or Tags) which can be accessed by the users in the website.', 'flash-cache') . '</p>
					</td>
				</tr>
				<tr class="wrap-row" valign="top">
					<th scope="row">' . __('Number of pages per execution', 'flash-cache') . '</th>
					<td>
						<input type="text" name="flash_cache_preload[pages_per_execution]" id="pages_per_execution" value="' . esc_attr(absint($values['pages_per_execution'])) . '">
						<p class="description">' . __('The preload process is separated by different processes to avoid the collapse of the website. With this option you can set the number of pages which will create a cache object for every execution of the preload process.', 'flash-cache') . '</p>
					</td>
				</tr>
				<tr class="wrap-row" valign="top">
					<th scope="row">' . __('Time per Preload', 'flash-cache') . '</th>
					<td>
						<input type="text" name="flash_cache_preload[time_per_preload]" id="time_per_preload" value="' . esc_attr(absint($values['time_per_preload'])) . '">
						<p class="description">' . __('Is the time in seconds for the next execution of the preload alter finishing the previous execution.', 'flash-cache') . '</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">' . __('Disable preload on finish', 'flash-cache') . '</th>
					<td>
						<div class="switch switch--horizontal switch--no-label">
							<input type="radio" ' . checked($values['disable_preload_finish'], false, false) . ' name="flash_cache_preload[disable_preload_finish]" value="0"/> Off 
							<label for="flash_cache_preload[disable_preload_finish]">Off</label>
							<input type="radio" ' . checked($values['disable_preload_finish'], true, false) . ' name="flash_cache_preload[disable_preload_finish]" value="1"/> On 
							<label for="flash_cache_preload[disable_preload_finish]">On</label>
							<span class="toggle-outside">
								<span class="toggle-inside"></span>
							</span>
						</div>
						<p class="description">' . __('By activating this option will turn off the preload cache process upon completion. This means that the cache will not be preloaded again after the initial load.', 'flash-cache') . '</p>
					</td>
				</tr>
			</table>';

		echo '<div class="wpm_footer">';

		echo flash_cache_get_menus_social_footer();

		echo '<div class="wpm_buttons">';
		submit_button();
		echo '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=reset_to_default_preload'), 'reset_to_default_preload', '_wpnonce') . '" class="button btn_reset_to_default">' . __('Reset to default', 'flash-cache') . '</a>
			</div> <!-- wpm_buttons -->
		</div>'; // wpm_footer

		echo '</form></div></div>';
		echo '</div>';
	}

	public static function save() {
		if (!wp_verify_nonce($_POST['_wpnonce'], 'save_flash_cache_preload')) {
			wp_die(__('Security check', 'flash-cache'));
		}
		/** Validating user inputs  */
		$post_values = array();
		if ( ! empty( $_POST['flash_cache_preload'] ) ) {
			$post_values = $_POST['flash_cache_preload'];
			if (  ! is_array( $post_values ) ) {
				$post_values = array();
			}
		}
		
		/** Sanitize all inputs and only accept the valid settings */
		$post_values = flash_cache_sanitize_settings_deep( self::default_options(), $post_values);
		
		$new_options = wp_parse_args( $post_values , self::default_options());
		$new_options = apply_filters('flash_cache_check_preload_settings', $new_options);

		update_option('flash_cache_preload', $new_options);
		flash_cache_notices::add(__('Settings updated', 'flash-cache'));
		wp_redirect(sanitize_url($_POST['_wp_http_referer']));
		exit;
	}

	public static function execute_preload() {
		if (!wp_verify_nonce($_POST['_wpnonce'], 'save_flash_cache_preload_execution')) {
			wp_die(__('Security check', 'flash-cache'));
		}
		$values_cron = wp_parse_args(array(), self::default_options_cron());
		update_option('flash_cache_preload_cron', $values_cron);
		update_option('flash_cache_preload_now', true);
		flash_cache_notices::add(__('Preload execution pending..', 'flash-cache'));
		wp_redirect(sanitize_url($_POST['_wp_http_referer']));
		exit;
	}

	public static function reset_to_default_preload() {
		if (!wp_verify_nonce($_GET['_wpnonce'], 'reset_to_default_preload')) {
			wp_die(__('Security check', 'flash-cache'));
		}

		update_option('flash_cache_preload', self::default_options());

		flash_cache_notices::add(__('Defaults have been restored.', 'flash-cache'));
		wp_redirect(admin_url('admin.php?page=flash_cache_preload'));
	}

}

flash_cache_preaload::hooks();
?>