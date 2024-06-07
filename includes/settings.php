<?php
/**
 * @package         etruel\Flash Cache
 * @subpackage 	   Settings
 * @author          Sebastian Robles
 * @author          Esteban Truelsegaard
 * @copyright       Copyright (c) 2017
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

class flash_cache_settings {

	public static $flash_cache_table;

	/**
	 * Static function hooks
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function hooks() {
		global $wpdb;
		//set the complete name of the table
		self::$flash_cache_table = $wpdb->prefix . 'flash_lock';

		add_action('admin_notices', array(__CLASS__, 'flash_cache_check_permalinks'));
		add_action('admin_menu', array(__CLASS__, 'admin_menu'));
		add_action('admin_print_styles', array(__CLASS__, 'all_WP_admin_styles'));
		add_action('admin_post_save_flash_cache_general', array(__CLASS__, 'save_general'));
		add_action('admin_post_save_flash_cache_advanced', array(__CLASS__, 'save_advanced'));
		add_action('admin_post_update_flash_cache_httacess', array(__CLASS__, 'update_httacess'));
		add_action('admin_post_delete_flash_cache', array(__CLASS__, 'delete_cache'));
		add_action('admin_post_reset_to_default_general_settings', array(__CLASS__, 'reset_to_default_general_settings'));
		add_action('admin_post_reset_to_default_advanced_options', array(__CLASS__, 'reset_to_default_advanced_options'));
		add_action('admin_print_scripts', array(__CLASS__, 'scripts'));
		add_action('admin_print_styles', array(__CLASS__, 'styles'));

		add_action('all_admin_notices', array(__CLASS__, 'cpt_settings_opentags'), 1, 0);
		add_action('in_admin_footer', array(__CLASS__, 'cpt_settings_closetags'), 1, 1);
		add_action('dbx_post_sidebar', array(__CLASS__, 'cpt_edit_settings_closetags'), 1, 1);
		//dbx_post_sidebar
	}

	public static function scripts() {
		global $current_screen;
		if (strpos($current_screen->id, 'flash_cache') === false) {
			return false;
		}
		wp_enqueue_script('flash_cache-settings', FLASH_CACHE_PLUGIN_URL . 'assets/js/settings.js', array('jquery'), FLASH_CACHE_VERSION, true);
	}

	public static function styles() {
		global $current_screen;
		if (strpos($current_screen->id, 'flash_cache') === false) {
			return false;
		}
		wp_enqueue_style('flash_cache-style', FLASH_CACHE_PLUGIN_URL . 'assets/css/style.css');

		wp_enqueue_style('flash_cache-icons', FLASH_CACHE_PLUGIN_URL . 'assets/css/icons.css');
	}

	public static function admin_menu() {
		add_menu_page(
				__('Flash Cache', 'flash-cache'),
				__('Flash Cache', 'flash-cache'),
				'manage_options',
				'flash_cache_setting',
				array(__CLASS__, 'general_settings_page'),
				FLASH_CACHE_PLUGIN_URL . 'assets/img/flash-cache-icon.png', 29);
		$page = add_submenu_page(
				'flash_cache_setting',
				__('Advanced options', 'flash-cache'),
				__('Advanced options', 'flash-cache'),
				'manage_options',
				'flash_cache_advanced_setting',
				array(__CLASS__, 'advanced_settings_page')
		);
	}

	public static function all_WP_admin_styles() {
		?><style type="text/css">
			#adminmenu .toplevel_page_flash_cache_setting .wp-menu-image img {
				padding-top: 5px;
			}
		</style><?php
	}

	public static function default_general_options() {
		$array	 = array(
			'activate' => false,
		);
		$array	 = apply_filters('flash_cache_default_general_options', $array);
		return $array;
	}

	public static function general_settings_page() {
		$values = wp_parse_args(get_option('flash_cache_settings', array()), self::default_general_options());
		echo '<div class="wrap wpm_container show_menu"><div class="flash-wrap-notices"></div>
			<div class="wpm_header">
			<h1>' . __('General Settings', 'flash-cache') . '</h1>
			</div>
			<div class="postbox">';
		self::get_changes_httacess();
		echo '<div class="wpm_flex">';

		echo flash_cache_get_menus();

		echo '<div class="wpm_main"><form action="' . admin_url('admin-post.php') . '" id="form_flash_cache_settings" class="pt-30" method="post">
				<input type="hidden" name="action" value="save_flash_cache_general"/>';
		wp_nonce_field('save_flash_cache_general');
		//Option for do and show a message in case that permalinks will be "Plain".
		if (get_option('permalink_structure') == '') {
			$activation = '<input type="radio" ' . checked($values['activate'], true, false) . ' name="flash_cache_general[activate]" value="0" disabled/>
			<label for="flash_cache_general[activate]">Off</label>
			<input type="radio" ' . checked($values['activate'], false, false) . ' name="flash_cache_general[activate]" value="1" disabled/>
			<label for="flash_cache_general[activate]">On</label>';
		} else {
			$activation = '<input type="radio" ' . checked($values['activate'], false, false) . ' name="flash_cache_general[activate]" value="0" />
			<label for="flash_cache_general[activate]">Off</label>
			<input type="radio" ' . checked($values['activate'], true, false) . ' name="flash_cache_general[activate]" value="1" />
			<label for="flash_cache_general[activate]">On</label>';
		}
		echo '<table class="form-table mh-250">
					<tr valign="top">
						<th scope="row">' . __('Enable Cache', 'flash-cache') . '</th>
						<td>
							<div class="switch switch--horizontal switch--no-label">
								' . $activation . '
								<span class="toggle-outside">
									<span class="toggle-inside"></span>
								</span>
							</div>
						<p class="description">' . __('Deactivate this option to stop the creation of the new cache objects.', 'flash-cache') . '</p>

						</td>
					</tr>
					<tr valign="top">
						<th scope="row">' . __('Delete cache', 'flash-cache') . '</th>
						<td>
							<a href="' . wp_nonce_url(admin_url('admin-post.php?action=delete_flash_cache'), 'delete_flash_cache', '_wpnonce') . '" class="button">' . __('Delete Cache', 'flash-cache') . '</a>
							<p class="description">' . __('Cached pages are stored on your server. If you need to clean all them, use this button to delete all the files and begin to create the cache from scratch.', 'flash-cache') . '</p>
							<p class="description">' . __('Current Disk Usage: ', 'flash-cache') . '<code>' . size_format(get_option('flash_cache_disk_usage', 0)) . '</code></p>
						</td>
					</tr>
					
				</table>';

		echo '<div class="wpm_footer">';

		echo flash_cache_get_menus_social_footer();

		echo '<div class="wpm_buttons">';
		submit_button();
		echo '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=reset_to_default_general_settings'), 'reset_to_default_general_settings', '_wpnonce') . '" class="button btn_reset_to_default">' . __('Reset to default', 'flash-cache') . '</a>
		</div> <!-- wpm_buttons -->
	</div>'; // wpm_footer

		echo '</form></div></div>';
		echo ' </div>';
	}

	public static function default_advanced_options() {
		$array	 = array(
			'cache_dir'				 => 'flash_cache/',
			'viewer_protocol_policy' => 'http_and_https',
			'ttl_default'			 => 86400,
			'dont_cache_cookie'		 => array(
				'comment_author_',
				flash_cache_get_logged_in_cookie(),
				'wp-postpass_'
			),
			'process_type'			 => 'ob_with_curl_request',
			'optimize_styles'		 => false,
			'optimize_scripts'		 => false,
			'inline_scripts'		 => false,
			'social_scripts'		 => false,
			'theme_files'			 => false,
			'plugins_files'			 => false,
			'plugins_exclude'		 => array(),
			'avoid_optimize'		 => false,
			'avoid_optimize_text'	 => '',
			'lock_type'				 => 'file',
		);
		$array	 = apply_filters('flash_cache_default_advanced_options', $array);
		return $array;
	}

	public static function cpt_settings_opentags() {
		global $typenow;

		if ($typenow == 'flash_cache_patterns') {

			wp_enqueue_style('flash_cache-style', FLASH_CACHE_PLUGIN_URL . 'assets/css/style.css');
			wp_enqueue_style('flash_cache-icons', FLASH_CACHE_PLUGIN_URL . 'assets/css/icons.css');

			echo '<div class="wrap wpm_container show_menu"><div class="flash-wrap-notices"></div>
			<div class="wpm_header">
			<h1>' . __('Patterns Settings', 'flash-cache') . '</h1>
			</div>
			<div class="postbox">';
			self::get_changes_httacess();
			echo '<div class="wpm_flex">';
			echo flash_cache_get_menus();

			echo '<div class="wpm_main">';
		}
	}

	public static function cpt_edit_settings_closetags($post) {
		global $typenow;
		if ($typenow == 'flash_cache_patterns') {
			?>
			<div class="clear"></div></div>
			<div class="clear"></div></div>
			<div class="clear"></div></div>
			<?php
		}
	}

	public static function cpt_settings_closetags() {
		global $typenow, $current_screen;

		if ($current_screen->id == 'edit-flash_cache_patterns') {
			?>
			</div> <!-- wpfooter fix -->
			<div class="clear"></div></div>
			<div class="clear"></div></div>
			<div class="clear"></div></div>
			<div id="wpfooter" role="contentinfo">
			<?php
		}
	}

	public static function advanced_settings_page() {
		$values					 = wp_parse_args(get_option('flash_cache_advanced_settings', array()), self::default_advanced_options());

		echo '<div class="wrap wpm_container show_menu"><div class="flash-wrap-notices"></div>
			<div class="wpm_header">
			<h1>' . __('Advanced Settings', 'flash-cache') . '</h1>
			</div>
			<div class="postbox">';
		self::get_changes_httacess();
		echo '<div class="wpm_flex">';

		echo flash_cache_get_menus();

		echo '<div class="wpm_main"><form action="' . admin_url('admin-post.php') . '" id="form_flash_cache_settings" method="post">
				<input type="hidden" name="action" value="save_flash_cache_advanced"/>';
		wp_nonce_field('save_flash_cache_advanced');
		echo '<div class="wpm_head"><div class="wpm_buttons">';
		submit_button();
		echo '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=reset_to_default_advanced_options'), 'reset_to_default_advanced_options', '_wpnonce') . '" class="button btn_reset_to_default">' . __('Reset to default', 'flash-cache') . '</a></div></div>';
		echo '<table class="form-table">
					<tr valign="top" class="wrap-row">
						<th scope="row">' . __('Cache Location', 'flash-cache') . '</th>
						<td>
							<input type="text" name="flash_cache_advanced[cache_dir]" id="flash_cache_advanced_cache_dir" value="' . esc_attr($values['cache_dir']) . '"/>
							<code>' . flash_cache_get_home_path() . '</code><br/>
							<p class="description">' . __('Specifies the file system path where the cache objects for each page will be placed, this option can be changed to another custom path.', 'flash-cache') . '</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">' . __('Viewer Protocol Policy', 'flash-cache') . '</th>
						<td>
							<div class="radio-group">
								<input type="radio" ' . checked($values['viewer_protocol_policy'], 'http_and_https', false) . ' name="flash_cache_advanced[viewer_protocol_policy]" value="http_and_https"/> ' . __('HTTP and HTTPS (If this protocol is enabled)', 'flash-cache') . '
								<p class="description">' . __('Keeps the cache in both protocols as in the HTTP and in HTTPS if it exists.', 'flash-cache') . '</p>
							</div> ';
		if (is_ssl()) {
			echo '
								<div class="radio-group">
									<input type="radio" ' . checked($values['viewer_protocol_policy'], 'redirect_http_to_https', false) . ' name="flash_cache_advanced[viewer_protocol_policy]" value="redirect_http_to_https"/> ' . __('Redirect HTTP to HTTPS', 'flash-cache') . ' 
									<p class="description">' . __('Redirect the users from HTTP to HTTPS, and other advantages as create a cache of the certificate to improve the speed through the header Strict-Transport-Security.', 'flash-cache') . '</p> 
								</div>';
		}

		echo '</td>
					</tr>

					<tr valign="top" class="wrap-row">
						<th scope="row">' . __('Default TTL', 'flash-cache') . '</th>
						<td>
							<input type="text" name="flash_cache_advanced[ttl_default]" id="flash_cache_advanced_ttl_default" value="' . esc_attr(absint($values['ttl_default'])) . '"/>
							<p class="description">' . __('The time life by default is used in the whole website to specifies the lifetime of the cache objects but in the client-side rather the user browser is besieged the header Cache-Control with the value specified in the field.', 'flash-cache') . '</p>
						</td>
					</tr>
					
					<tr valign="top" class="wrap-row">
						<th scope="row">' . __('Do not cache users with cookies', 'flash-cache') . '</th>
						<td>';

		echo '<table class="form-table" id="table_dont_cache_cookie">';

		echo '	<p class="description">' . __('This list allows add the users which have the part of the name of a Cookie, it won’t be showed in the objects in cache, as the users that have begun session in the WP-ADMIN, you can also add other cookies to websites which uses user roles at the theme system.', 'flash-cache') . '</p>';

		foreach ($values['dont_cache_cookie'] as $value) {
			echo '<tr valign="top" class="tr_item_dont_cache_cookie">
										<td scope="row">
											<input type="text" name="flash_cache_advanced[dont_cache_cookie][]" value="' . esc_attr($value) . '"/><label title="" data-id="1" class="delete"><span class="dashicons dashicons-trash"></span></label>
										</td>
									</tr>';
		}

		echo '</table>';
		echo '<div class="add-cookie form-table">
						<input type="button" name="add_new_dont_cache_cookie" id="add_new_dont_cache_cookie" class="button" value="Add"/>
				</div>';
		echo '</td>
					</tr>
					<tr valign="top" class="wrap-row">
						<th scope="row">' . __('Cache Process', 'flash-cache') . '</th>
						<td>
							<div class="radio-group"><input type="radio" ' . checked($values['process_type'], 'ob_with_curl_request', false) . ' name="flash_cache_advanced[process_type]" value="ob_with_curl_request"/> OB with cURL requests (Recommended) <p class="description">' . __('This option is recommended by default because it uses OB (Output Buffer) to create the first cache of a page being faster and optimum, and in the next cache updates of the same object it will be used the cURL for its update.', 'flash-cache') . '</p>
							</div>
							<div class="radio-group"><input type="radio" ' . checked($values['process_type'], 'only_curl', false) . ' name="flash_cache_advanced[process_type]" value="only_curl"/> Only with cURL requests
							<p class="description">' . __('It is only used with cURL to get the content of the pages to be cached, that’s why it is usually slower, nules there are cases where there are conflicts on OB with others plugins, in these cases is recommended this option.', 'flash-cache') . '</p>
							</div>
						</td>
					</tr>';
		echo '
					<tr valign="top" class="wrap-row">
						<th scope="row">' . __('Optimize styles', 'flash-cache') . '</th>
						<td>
							<div class="switch switch--horizontal switch--no-label">
								<input type="radio" ' . checked($values['optimize_styles'], false, false) . ' name="flash_cache_advanced[optimize_styles]" value="0"/>
								<label for="flash_cache_advanced[optimize_styles]">Off</label>
								<input type="radio" ' . checked($values['optimize_styles'], true, false) . ' name="flash_cache_advanced[optimize_styles]" value="1"/>
								<label for="flash_cache_advanced[optimize_styles]">On</label>
								<span class="toggle-outside">
									<span class="toggle-inside"></span>
								</span>
							</div>
							<p class="description">' . __('Optimize and combine all your Stylesheets files into one, this allows your site to request fewer files and get better page load performance.', 'flash-cache') . '</p>
							' . apply_filters('flash_cache_optimize_styles_extra_html', '', $values) . ' 
						</td>
						
					</tr>';
		echo '
					<tr valign="top" class="wrap-row">
						<th scope="row">' . __('Optimize scripts', 'flash-cache') . '</th>
						<td>
							<div class="switch switch--horizontal switch--no-label">
								<input type="radio" ' . checked($values['optimize_scripts'], false, false) . ' name="flash_cache_advanced[optimize_scripts]" value="0"/>
								<label for="flash_cache_advanced[optimize_scripts]">Off</label>
								<input type="radio" ' . checked($values['optimize_scripts'], true, false) . ' name="flash_cache_advanced[optimize_scripts]" value="1"/>
								<label for="flash_cache_advanced[optimize_scripts]">On</label>
								<span class="toggle-outside">
									<span class="toggle-inside"></span>
								</span>
							</div>
							<p class="description">' . __('Optimize and combine all your JavaScript files into one, this allows your site to request fewer files and get better page load performance.', 'flash-cache') . '</p>
					';
		
		// new advanced options for javascripts 
		// since 3.1 version
		echo '
							<div class="flash_cache_avoid_optimize" style="display:none">
								<p class="description"><b>' . __('If you find any problems with the optimizations, you can exclude the JS files by using these options to choose which ones are left in the original format.', 'flash-cache') . '</b></p>
								<table class="form-table">
									<tbody>
										<tr valign="top" class="wrap-row">
											<th scope="row">' . __('Inline scripts', 'flash-cache') . '</th>
											<td>
												<div class="switch switch--horizontal switch--no-label">
													<input type="radio" checked="checked" name="flash_cache_advanced[inline_scripts]"' . checked($values["inline_scripts"], false, false) . ' value="0">
													<label for="flash_cache_advanced[inline_scripts]">Off</label>
													<input type="radio" name="flash_cache_advanced[inline_scripts]"' . checked($values["inline_scripts"], true, false) . ' value="1">
													<label for="flash_cache_advanced[inline_scripts]">On</label>
													<span class="toggle-outside">
														<span class="toggle-inside"></span>
													</span>
												</div>
												<p class="description">' . __('Avoid optimizing inline JS scripts found in HTML DOM.', 'flash-cache') . '</p>
											</td>
										</tr>
										<tr valign="top" class="wrap-row">
											<th scope="row">' . __('Theme JS files', 'flash-cache') . '</th>
											<td>
												<div class="switch switch--horizontal switch--no-label">
													<input type="radio" checked="checked" name="flash_cache_advanced[theme_files]"' . checked($values["theme_files"], false, false) . ' value="0">
													<label for="flash_cache_advanced[theme_files]">Off</label>
													<input type="radio" name="flash_cache_advanced[theme_files]"' . checked($values["theme_files"], true, false) . ' value="1">
													<label for="flash_cache_advanced[theme_files]">On</label>
													<span class="toggle-outside">
														<span class="toggle-inside"></span>
													</span>
												</div>
												<p class="description">' . __('Avoid optimizing theme JavaScript files.', 'flash-cache') . '</p>
											</td>
										</tr>
										 <tr valign="top" class="wrap-row">
											<th scope="row">' . __('Plugins JS files', 'flash-cache') . '</th>
											<td>
												<div class="switch switch--horizontal switch--no-label">
													<input type="radio" checked="checked" name="flash_cache_advanced[plugins_files]"' . checked($values["plugins_files"], false, false) . ' value="0">
													<label for="flash_cache_advanced[plugins_files]">Off</label>
													<input type="radio" name="flash_cache_advanced[plugins_files]"' . checked($values["plugins_files"], true, false) . ' value="1">
													<label for="flash_cache_advanced[plugins_files]">On</label>
													<span class="toggle-outside">
														<span class="toggle-inside"></span>
													</span>
												</div>
												<p class="description">' . __('Avoid optimizing plugins JavaScript files.', 'flash-cache') . '</p>
												' . apply_filters('flash_cache_exclude_scripts_extra_html', '', $values) . '
											</td>
										</tr>

									<tr valign="top" class="wrap-row">
										<th scope="row">' . __('Include SEO &amp; Social scripts in optimized file', 'flash-cache') . '</th>
										<td>
										<div class="switch switch--horizontal switch--no-label">
											<input type="radio" checked="checked" name="flash_cache_advanced[social_scripts]"' . checked($values["social_scripts"], false, false) . ' value="0">
											<label for="flash_cache_advanced[social_scripts]">Off</label>
											<input type="radio" name="flash_cache_advanced[social_scripts]"' . checked($values["social_scripts"], true, false) . ' value="1">
											<label for="flash_cache_advanced[social_scripts]">On</label>
											<span class="toggle-outside">
												<span class="toggle-inside"></span>
											</span>
										</div>
										<p class="description">' . __('By default these scripts are not included because there are already optimized, hosted on remote servers and load asyncroniously. But by activating this option you can try to optimize and host them in your server.', 'flash-cache') . '</p>
										</td>
									</tr>
									</tbody>
								</table>
							</div>';
		
							echo apply_filters('flash_cache_optimize_scripts_extra_html', '', $values) . ' 
						</td>
					</tr>';

		echo '<tr valign="top" class="wrap-row">
					<th scope="row">' . __('Lock Type', 'flash-cache') . '</th>
					<td>
						<div class="radio-group"><input type="radio" ' . checked($values['lock_type'], 'file', false) . ' name="flash_cache_advanced[lock_type]" value="file"/> Lock with files
							<p class="description">' . __('This option is recommended by default because it uses Files to prevent two processes from creating cache of the same page at the same time.', 'flash-cache') . '</p>
						</div>
						<div class="radio-group"><input type="radio" ' . checked($values['lock_type'], 'db', false) . ' name="flash_cache_advanced[lock_type]" value="db"/> Lock with DB
							<p class="description">' . __('This option uses DB to prevent two processes from creating cache of the same page at the same time.', 'flash-cache') . '</p>
						</div>
					</td>
				</tr>';
		echo '</table>';
		echo '<div class="wpm_footer">';
		echo '<div class="wpm_buttons">';
		submit_button();
		echo '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=reset_to_default_advanced_options'), 'reset_to_default_advanced_options', '_wpnonce') . '" class="button btn_reset_to_default">' . __('Reset to default', 'flash-cache') . '</a>
			</div> <!-- wpm_buttons -->
		</div>'; // wpm_footer

		echo '</form></div></div>';

		echo ' </div>';
	}

	public static function get_changes_httacess() {

		if (flash_cache_enviroment::is_nginx()) {
			extract(flash_cache_get_nginx_conf_info());
		} else {
			extract(flash_cache_get_htaccess_info());
		}

		if ($current_cache_rules != $cache_rules || $current_utils_rules != $utils_rules || $current_optimization_rules != $optimization_rules) {

			if (flash_cache_enviroment::is_nginx()) {
				echo '<div id="message" class="notice notice-error below-h2"><p>' . __('A difference between the rules in your <strong>nginx.conf</strong> file and the Flash Cache rules has been found. This could be simple whitespace differences, but you should compare the rules in the file with those below as soon as possible. Click the &#8217;Update Rules&#8217; button to update the rules.', 'flash-cache') . '</p></div>';
			} else {
				echo '<div id="message" class="notice notice-error below-h2"><p>' . __('A difference between the rules in your <strong>.htaccess</strong> file and the plugin rewrite rules has been found. This could be simple whitespace differences, but you should compare the rules in the file with those below as soon as possible. Click the &#8217;Update Rules&#8217; button to update the rules.', 'flash-cache') . '</p></div>';
			}

			echo '<p><pre style="background:#fcf6f6;"># BEGIN FlashCache Page Cache<br/>' . esc_html($cache_rules) . '<br/># END FlashCache Page Cache</pre></p>';
			echo '<p><pre style="background:#fcf6f6;"># BEGIN FlashCache Utils<br/>' . esc_html($utils_rules) . '<br/># END FlashCache Utils</pre></p>';
			echo '<p><pre style="background:#fcf6f6;"># BEGIN FlashCache Optimizations<br/>' . esc_html($optimization_rules) . '<br/># END FlashCache Optimizations</pre></p>';

			echo '<form action="' . admin_url('admin-post.php') . '" id="form_flash_cache_update_httacess" method="post">
				<input type="hidden" name="action" value="update_flash_cache_httacess"/>';
			wp_nonce_field('update_flash_cache_httacess');
			submit_button('Update Rules');
			echo '<hr/></form>';
		}
	}

	public static function save_general() {
		if (!wp_verify_nonce($_POST['_wpnonce'], 'save_flash_cache_general')) {
			wp_die(__('Security check', 'flash-cache'));
		}
		/** Validating user inputs  */
		$post_values = array();
		if (!empty($_POST['flash_cache_general'])) {
			$post_values = $_POST['flash_cache_general'];
			if (!is_array($post_values)) {
				$post_values = array();
			}
		}

		/** Sanitize all inputs and only accept the valid settings */
		$post_values = flash_cache_sanitize_settings_deep(self::default_general_options(), $post_values);

		$new_options = wp_parse_args($post_values, self::default_general_options());
		$new_options = apply_filters('flash_cache_check_general_settings', $new_options);

		update_option('flash_cache_settings', $new_options);
		flash_cache_update_htaccess();

		flash_cache_notices::add(__('Settings updated', 'flash-cache'));
		wp_redirect(sanitize_url($_POST['_wp_http_referer']));
		exit;
	}

	public static function save_advanced() {
		if (!wp_verify_nonce($_POST['_wpnonce'], 'save_flash_cache_advanced')) {
			wp_die(__('Security check', 'flash-cache'));
		}

		/** Validating user inputs  */
		$post_values = array();
		if (!empty($_POST['flash_cache_advanced'])) {
			$post_values = $_POST['flash_cache_advanced'];
			if (!is_array($post_values)) {
				$post_values = array();
			}
		}
		/** Sanitize all inputs and only accept the valid settings */
		$post_values = flash_cache_sanitize_settings_deep(self::default_advanced_options(), $post_values);
		
		$new_options = wp_parse_args($post_values, self::default_advanced_options());
		$new_options = apply_filters('flash_cache_check_advanced_settings', $new_options);
		if($old_value_lock = flash_cache_get_option('lock_type')) {
			if ($old_value_lock != $new_options['lock_type']) {
				$cache_dir = flash_cache_get_option('cache_dir');
				if(isset($cache_dir) && $cache_dir != '/'){
					$cache_dir = flash_cache_get_home_path() . $cache_dir;
					flash_cache_delete_dir($cache_dir, true);
				}
			}
		}
		update_option('flash_cache_advanced_settings', $new_options);
		flash_cache_update_htaccess();
		flash_cache_notices::add(__('Settings updated', 'flash-cache'));
		wp_redirect(sanitize_url($_POST['_wp_http_referer']));
		exit;
	}

	public static function update_httacess() {
		if (!wp_verify_nonce($_POST['_wpnonce'], 'update_flash_cache_httacess')) {
			wp_die(__('Security check', 'flash-cache'));
		}
		flash_cache_update_htaccess();
		flash_cache_notices::add(__('Web server rules updated', 'flash-cache'));
		wp_redirect(sanitize_url($_POST['_wp_http_referer']));
		exit;
	}

	public static function delete_cache() {
		if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_flash_cache')) {
			wp_die(__('Security check', 'flash-cache'));
		}
		$advanced_settings	 = wp_parse_args(get_option('flash_cache_advanced_settings', array()), self::default_advanced_options());
		$cache_dir			 = get_home_path() . $advanced_settings['cache_dir'];
		flash_cache_delete_dir($cache_dir, true);
		flash_cache_notices::add(__('The cache files have been deleted.', 'flash-cache'));
		wp_redirect(admin_url('admin.php?page=flash_cache_setting'));
	}

	public static function reset_to_default_general_settings() {
		if (!wp_verify_nonce($_GET['_wpnonce'], 'reset_to_default_general_settings')) {
			wp_die(__('Security check', 'flash-cache'));
		}

		$new_options = wp_parse_args(array('activate' => false), self::default_general_options());
		$new_options = apply_filters('flash_cache_check_general_settings', $new_options);

		update_option('flash_cache_settings', $new_options);
		flash_cache_update_htaccess();

		$advanced_settings = wp_parse_args(get_option('flash_cache_advanced_settings', array()), self::default_advanced_options());

		$cache_dir = get_home_path() . $advanced_settings['cache_dir'];
		flash_cache_delete_dir($cache_dir, true);
		flash_cache_notices::add(__('Defaults have been restored.', 'flash-cache'));
		wp_redirect(admin_url('admin.php?page=flash_cache_setting'));
	}

	public static function reset_to_default_advanced_options() {
		if (!wp_verify_nonce($_GET['_wpnonce'], 'reset_to_default_advanced_options')) {
			wp_die(__('Security check', 'flash-cache'));
		}

		update_option('flash_cache_advanced_settings', self::default_advanced_options());
		flash_cache_notices::add(__('Defaults have been restored.', 'flash-cache'));
		wp_redirect(admin_url('admin.php?page=flash_cache_advanced_setting'));
	}

	public static function flash_cache_check_permalinks() {
		global $current_screen;
		if (isset($current_screen->id) &&
				($current_screen->id == 'toplevel_page_flash_cache_setting' || $current_screen->id == 'options-permalink')
		) {
			if (get_option('permalink_structure') == '') {
				if ($current_screen->id == 'options-permalink') {
					$notice_text = 'Flash Cache requires a different Permalink structure to work.';
					flash_cache_notices::add(['text' => $notice_text, 'error' => true, 'screen' => 'options-permalink']);
				} elseif ($current_screen->id == 'toplevel_page_flash_cache_setting') {
					$notice_text = 'Flash Cache requires a different Permalink structure to work. You can change it <a href="' . esc_url(admin_url('/options-permalink.php')) . '">here</a>.';
					flash_cache_notices::add(['text' => $notice_text, 'error' => true, 'screen' => 'toplevel_page_flash_cache_setting']);
				}
			}
		}
	}
}

flash_cache_settings::hooks();
?>