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

	/**
	 * Static function hooks
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function hooks() {
		add_action('admin_notices', array(__CLASS__, 'flash_cache_check_permalinks'));
		add_action('admin_menu', array(__CLASS__, 'admin_menu'));
		add_action('admin_print_styles', array(__CLASS__, 'all_WP_admin_styles'));
		add_action('admin_post_save_flash_cache_general', array(__CLASS__, 'save_general'));
		add_action('admin_post_save_flash_cache_advanced', array(__CLASS__, 'save_advanced'));
		add_action('admin_post_update_flash_cache_httacess', array(__CLASS__, 'update_httacess'));
		add_action('admin_post_delete_flash_cache', array(__CLASS__, 'delete_cache'));
		add_action('admin_post_flash_cache_dismiss_ols_headers', array(__CLASS__, 'dismiss_ols_headers'));
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
		wp_enqueue_script('flash_cache-settings', FLASH_CACHE_PLUGIN_URL . 'assets/js/settings.js', array('jquery'), FLASH_CACHE_VERSION . '.' . filemtime(FLASH_CACHE_PLUGIN_DIR . 'assets/js/settings.js'), true);
		wp_localize_script('flash_cache-settings', 'flash_cache_settings_i18n', array(
			'clear_cache'	 => __('Changing this option clears the whole cache. Continue?', 'flash-cache'),
			'reset_defaults' => __('Restore the default settings?', 'flash-cache'),
			'counting'		 => __('Counting…', 'flash-cache'),
		));
	}

	public static function styles() {
		global $current_screen;
		if (strpos($current_screen->id, 'flash_cache') === false) {
			return false;
		}
		wp_enqueue_style('flash_cache-style', FLASH_CACHE_PLUGIN_URL . 'assets/css/style.css', array(), FLASH_CACHE_VERSION . '.' . filemtime(FLASH_CACHE_PLUGIN_DIR . 'assets/css/style.css'));

		wp_enqueue_style('flash_cache-icons', FLASH_CACHE_PLUGIN_URL . 'assets/css/icons.css');
	}

	public static function admin_menu() {
		add_menu_page(
				__('Flash Cache', 'flash-cache'),
				__('Flash Cache', 'flash-cache'),
				'manage_options',
				'flash_cache_setting',
				array(__CLASS__, 'general_settings_page'),
				FLASH_CACHE_PLUGIN_URL . 'assets/img/flash-cache-icon.png',
				29
		);
		self::admin_submenus();
	}

	/**
	 * Registered with the main menu, so they come before Patterns and Preload.
	 */
	public static function admin_submenus() {
		foreach (self::advanced_pages() as $section => $page) {
			add_submenu_page('flash_cache_setting', $page['title'], $page['title'], 'manage_options', $page['slug'], array(__CLASS__, $section . '_page'));
		}
	}

	/**
	 * The screens of the flash_cache_advanced_settings option, in menu order.
	 */
	public static function advanced_pages() {
		return array(
			'updates'	   => array('slug' => 'flash_cache_updates', 'title' => __('Cache updates', 'flash-cache')),
			'advanced'	   => array('slug' => 'flash_cache_advanced_setting', 'title' => __('Advanced options', 'flash-cache')),
			'optimization' => array('slug' => 'flash_cache_optimization', 'title' => __('Optimization', 'flash-cache')),
		);
	}

	/**
	 * The option keys each screen saves and resets. A key of another plugin that is not listed
	 * keeps its stored value unless its screen posts it.
	 */
	public static function advanced_sections() {
		return apply_filters('flash_cache_advanced_sections', array(
			'optimization' => array('optimize_styles', 'optimize_scripts', 'inline_scripts', 'social_scripts', 'theme_files', 'plugins_files', 'plugins_exclude', 'avoid_optimize', 'avoid_optimize_text'),
			'updates'	   => array('process_type', 'refresh_mode', 'design_changes', 'disable_widget_cache'),
			'advanced'	   => array('cache_dir', 'viewer_protocol_policy', 'ttl_default', 'dont_cache_cookie', 'lock_type'),
		));
	}

	public static function requested_section() {
		$section = isset($_REQUEST['flash_cache_section']) ? sanitize_key(wp_unslash($_REQUEST['flash_cache_section'])) : 'advanced';
		return isset(self::advanced_pages()[$section]) ? $section : 'advanced';
	}

	public static function all_WP_admin_styles() {
		?>
		<style type="text/css">
			#adminmenu .toplevel_page_flash_cache_setting .wp-menu-image img {
				padding-top: 5px;
			}
		</style><?php
	}

	public static function default_general_options() {
		$array = array(
			'activate' => false,
		);
		$array = apply_filters('flash_cache_default_general_options', $array);
		return $array;
	}

	public static function general_settings_page() {
		$values = flash_cache_get_general_settings();
		echo '<div class="wrap wpm_container show_menu"><div class="flash-wrap-notices"></div>
			' . flash_cache_get_header(__('General Settings', 'flash-cache')) . '
			<div class="postbox">';
		self::get_changes_httacess();
		echo '<div class="wpm_flex">';

		echo flash_cache_get_menus();

		echo '<div class="wpm_main"><form action="' . admin_url('admin-post.php') . '" id="form_flash_cache_settings" class="pt-30" method="post">
				<input type="hidden" name="action" value="save_flash_cache_general"/>';
		wp_nonce_field('save_flash_cache_general');
		//Option for do and show a message in case that permalinks will be "Plain".
		if (get_option('permalink_structure') == '') {
			$activation = '<input type="radio" checked="checked" name="flash_cache_general[activate]" value="0" disabled/>
			<label for="flash_cache_general[activate]">' . __('Off', 'flash-cache') . '</label>
			<input type="radio" name="flash_cache_general[activate]" value="1" disabled/>
			<label for="flash_cache_general[activate]">' . __('On', 'flash-cache') . '</label>';
		} else {
			$activation = '<input type="radio" ' . checked($values['activate'], false, false) . ' name="flash_cache_general[activate]" value="0" />
			<label for="flash_cache_general[activate]">' . __('Off', 'flash-cache') . '</label>
			<input type="radio" ' . checked($values['activate'], true, false) . ' name="flash_cache_general[activate]" value="1" />
			<label for="flash_cache_general[activate]">' . __('On', 'flash-cache') . '</label>';
		}
		echo '<table class="form-table mh-250">
					<tr valign="top">
						<th scope="row">' . __('Enable cache', 'flash-cache') . '</th>
						<td>
							<div class="switch switch--horizontal switch--no-label">
								' . $activation . '
								<span class="toggle-outside">
									<span class="toggle-inside"></span>
								</span>
							</div>
						<p class="description">' . __('Turn it off to stop creating new cached pages. Pages already cached are deleted with the button below.', 'flash-cache') . '</p>

						</td>
					</tr>
					<tr valign="top" class="wrap-row flash-cache-stats-row">
						<td colspan="2">' . flash_cache_stats::cards() . '</td>
					</tr>
					<tr valign="top">
						<th scope="row">' . __('Delete cache', 'flash-cache') . '</th>
						<td>
							<a href="' . wp_nonce_url(admin_url('admin-post.php?action=delete_flash_cache'), 'delete_flash_cache', '_wpnonce') . '" class="button">' . __('Delete Cache', 'flash-cache') . '</a>
							<p class="description">' . __('Removes every cached page and combined file. Pages are cached again as they are visited or preloaded.', 'flash-cache') . '</p>
						</td>
					</tr>' . (flash_cache_enviroment::is_nginx() ? self::nginx_rules_row() : '') . (flash_cache_enviroment::is_openlitespeed() ? self::openlitespeed_rules_row() : '') . '
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
		$array = array(
			'cache_dir'				 => 'flash_cache/',
			'viewer_protocol_policy' => 'http_and_https',
			'ttl_default'			 => 86400,
			'dont_cache_cookie'		 => array(
				'comment_author_',
				flash_cache_get_logged_in_cookie(),
				'wp-postpass_'
			),
			'process_type'			 => 'ob_with_curl_request',
			'refresh_mode'			 => 'visitors',
			'design_changes'		 => 'auto',
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
			'disable_widget_cache'	 => false,
		);
		$array = apply_filters('flash_cache_default_advanced_options', $array);
		return $array;
	}

	public static function cpt_settings_opentags() {
		global $typenow;

		if ($typenow == 'flash_cache_patterns') {

			wp_enqueue_style('flash_cache-style', FLASH_CACHE_PLUGIN_URL . 'assets/css/style.css', array(), FLASH_CACHE_VERSION . '.' . filemtime(FLASH_CACHE_PLUGIN_DIR . 'assets/css/style.css'));
			wp_enqueue_style('flash_cache-icons', FLASH_CACHE_PLUGIN_URL . 'assets/css/icons.css');

			echo '<div class="wrap wpm_container show_menu"><div class="flash-wrap-notices"></div>
			' . flash_cache_get_header(__('Patterns Settings', 'flash-cache')) . '
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
			<div class="clear"></div>
			</div>
			<div class="clear"></div>
			</div>
			<div class="clear"></div>
			</div>
			<?php
		}
	}

	public static function cpt_settings_closetags() {
		global $typenow, $current_screen;

		if ($current_screen->id == 'edit-flash_cache_patterns') {
			?>
			</div> <!-- wpfooter fix -->
			<div class="clear"></div>
			</div>
			<div class="clear"></div>
			</div>
			<div class="clear"></div>
			</div>
			<div id="wpfooter" role="contentinfo">
			<?php
		}
	}

	public static function optimization_page() {
		self::settings_page('optimization');
	}

	public static function updates_page() {
		self::settings_page('updates');
	}

	public static function advanced_page() {
		self::settings_page('advanced');
	}

	/**
	 * One screen of the advanced option: the form posts its section, and saving or resetting it
	 * touches only that section's keys.
	 */
	private static function settings_page($section) {
		$values = flash_cache_get_advanced_settings();
		$titles = array(
			'optimization' => __('Optimization', 'flash-cache'),
			'updates'	   => __('Cache updates', 'flash-cache'),
			'advanced'	   => __('Advanced options', 'flash-cache'),
		);
		$reset	= wp_nonce_url(admin_url('admin-post.php?action=reset_to_default_advanced_options&flash_cache_section=' . $section), 'reset_to_default_advanced_options', '_wpnonce');

		echo '<div class="wrap wpm_container show_menu"><div class="flash-wrap-notices"></div>
			' . flash_cache_get_header($titles[$section]) . '
			<div class="postbox">';
		self::get_changes_httacess();
		echo '<div class="wpm_flex">';

		echo flash_cache_get_menus();

		echo '<div class="wpm_main"><form action="' . admin_url('admin-post.php') . '" id="form_flash_cache_settings" method="post">
				<input type="hidden" name="action" value="save_flash_cache_advanced"/>
				<input type="hidden" name="flash_cache_section" value="' . esc_attr($section) . '"/>';
		wp_nonce_field('save_flash_cache_advanced');
		echo '<div class="wpm_head"><div class="wpm_buttons">';
		submit_button();
		echo '<a href="' . $reset . '" class="button btn_reset_to_default">' . __('Reset to default', 'flash-cache') . '</a></div></div>';
		echo '<table class="form-table">';
		call_user_func(array(__CLASS__, $section . '_rows'), $values);
		echo '</table>';
		echo '<div class="wpm_footer">';
		echo flash_cache_get_menus_social_footer();
		echo '<div class="wpm_buttons">';
		submit_button();
		echo '<a href="' . $reset . '" class="button btn_reset_to_default">' . __('Reset to default', 'flash-cache') . '</a>
			</div> <!-- wpm_buttons -->
		</div>'; // wpm_footer

		echo '</form></div></div>';

		echo ' </div>';
	}

	private static function advanced_rows($values) {
		echo '<tr valign="top" class="wrap-row">
						<th scope="row">' . __('Cache folder', 'flash-cache') . '</th>
						<td>
							<input type="text" name="flash_cache_advanced[cache_dir]" id="flash_cache_advanced_cache_dir" value="' . esc_attr($values['cache_dir']) . '"/>
							<code>' . flash_cache_get_home_path() . '</code><br/>
							<p class="description">' . __('Where cached pages and combined files are stored, relative to the path shown. It must be a folder of its own, outside the WordPress folders.', 'flash-cache') . '</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">' . __('HTTPS', 'flash-cache') . '</th>
						<td>';
		if (is_ssl()) {
			echo '
							<div class="radio-group">
								<label><input type="radio" ' . checked($values['viewer_protocol_policy'], 'http_and_https', false) . ' name="flash_cache_advanced[viewer_protocol_policy]" value="http_and_https"/> ' . __('Serve HTTP and HTTPS', 'flash-cache') . '</label>
								<p class="description">' . __('Cached pages are served on both protocols.', 'flash-cache') . '</p>
							</div>
							<div class="radio-group">
								<label><input type="radio" ' . checked($values['viewer_protocol_policy'], 'redirect_http_to_https', false) . ' name="flash_cache_advanced[viewer_protocol_policy]" value="redirect_http_to_https"/> ' . __('Redirect HTTP to HTTPS', 'flash-cache') . '</label>
								<p class="description">' . __('Visitors arriving over HTTP are redirected to HTTPS, and browsers are told to keep using HTTPS for the browser cache time (Strict-Transport-Security header).', 'flash-cache') . '</p>
							</div>';
		} else {
			echo '<input type="hidden" name="flash_cache_advanced[viewer_protocol_policy]" value="http_and_https"/>
							<p class="description">' . __('This admin page is open over HTTP, so cached pages are served as the site is reached. The option to redirect visitors to HTTPS appears here when the admin is opened over HTTPS.', 'flash-cache') . '</p>';
		}
		echo '</td>
					</tr>

					<tr valign="top" class="wrap-row">
						<th scope="row">' . __('Browser cache time', 'flash-cache') . '</th>
						<td>
							<input type="text" name="flash_cache_advanced[ttl_default]" id="flash_cache_advanced_ttl_default" value="' . esc_attr(absint($values['ttl_default'])) . '"/>
							<p class="description">' . __('Seconds a browser may keep a cached page before asking for it again, sent in the Cache-Control header. 86400 is one day.', 'flash-cache') . '</p>
						</td>
					</tr>
					
					<tr valign="top" class="wrap-row">
						<th scope="row">' . __('Skip visitors with cookies', 'flash-cache') . '</th>
						<td>';

		echo '<table class="form-table" id="table_dont_cache_cookie">';

		echo '	<p class="description">' . __('Visitors who send a cookie whose name contains one of these texts always get the live page. Logged-in users, commenters and visitors of password-protected posts are listed by default.', 'flash-cache') . '</p>';

		foreach ($values['dont_cache_cookie'] as $value) {
			echo '<tr valign="top" class="tr_item_dont_cache_cookie">
										<td scope="row">
											<input type="text" name="flash_cache_advanced[dont_cache_cookie][]" value="' . esc_attr($value) . '"/><label title="" data-id="1" class="delete"><span class="dashicons dashicons-trash"></span></label>
										</td>
									</tr>';
		}

		echo '</table>';
		echo '<div class="add-cookie form-table">
						<input type="button" name="add_new_dont_cache_cookie" id="add_new_dont_cache_cookie" class="button" value="' . esc_attr__('Add', 'flash-cache') . '"/>
				</div>';
		echo '</td>
					</tr>';
		echo '<tr valign="top" class="wrap-row">
					<th scope="row">' . __('Lock', 'flash-cache') . '</th>
					<td>
						<div class="radio-group"><input type="radio" ' . checked($values['lock_type'], 'file', false) . ' name="flash_cache_advanced[lock_type]" value="file"/> ' . __('Files (Recommended)', 'flash-cache') . '
							<p class="description">' . __('A lock file per page keeps two visitors from building the same page at the same time.', 'flash-cache') . '</p>
						</div>
						<div class="radio-group"><input type="radio" ' . checked($values['lock_type'], 'db', false) . ' name="flash_cache_advanced[lock_type]" value="db"/> ' . __('Database', 'flash-cache') . '
							<p class="description">' . __('A MySQL lock does the same. Useful when several servers share the database but not the disk.', 'flash-cache') . '</p>
						</div>
					</td>
				</tr>';
	}

	private static function updates_rows($values) {
		echo '<tr valign="top" class="wrap-row">
						<th scope="row">' . __('Cache creation', 'flash-cache') . '</th>
						<td>
							<div class="radio-group"><input type="radio" ' . checked($values['process_type'], 'ob_with_curl_request', false) . ' name="flash_cache_advanced[process_type]" value="ob_with_curl_request"/> ' . __('Capture the visited page (Recommended)', 'flash-cache') . ' <p class="description">' . __('The page a visitor receives is stored as it is sent; later refreshes request the page again in the background.', 'flash-cache') . '</p>
							</div>
							<div class="radio-group"><input type="radio" ' . checked($values['process_type'], 'only_curl', false) . ' name="flash_cache_advanced[process_type]" value="only_curl"/> ' . __('Request the page separately', 'flash-cache') . '
							<p class="description">' . __('Every page is fetched with its own request. Slower; use it when another plugin conflicts with output buffering.', 'flash-cache') . '</p>
							</div>
						</td>
					</tr>';
		echo '<tr valign="top" class="wrap-row">
					<th scope="row">' . __('Refresh expired pages', 'flash-cache') . '</th>
					<td>
						<div class="radio-group"><label><input type="radio" ' . checked($values['refresh_mode'], 'visitors', false) . ' name="flash_cache_advanced[refresh_mode]" value="visitors"/> ' . __('On visits (Recommended)', 'flash-cache') . '</label>
							<p class="description">' . __('A visit to a page whose “Refresh after” time has passed asks the server to rebuild it in the background. Fresh pages make no extra request.', 'flash-cache') . '</p>
						</div>
						<div class="radio-group"><label><input type="radio" ' . checked($values['refresh_mode'], 'cron', false) . ' name="flash_cache_advanced[refresh_mode]" value="cron"/> ' . __('From the preload only', 'flash-cache') . '</label>
							<p class="description">' . __('Cached pages carry no refresh script. Expired pages are rebuilt by the Preload process, so keep it enabled.', 'flash-cache') . '</p>
						</div>
					</td>
				</tr>';
		$published = flash_cache_design::published_posts();
		$threshold = (int) apply_filters('flash_cache_gradual_refresh_threshold', flash_cache_design::AUTOMATIC_THRESHOLD);
		$this_site = $published > $threshold
				? sprintf(_n('This site has %s published post, so its cache is refreshed gradually.', 'This site has %s published posts, so its cache is refreshed gradually.', $published, 'flash-cache'), number_format_i18n($published))
				: sprintf(_n('This site has %s published post, so its whole cache is deleted.', 'This site has %s published posts, so its whole cache is deleted.', $published, 'flash-cache'), number_format_i18n($published));
		echo '<tr valign="top" class="wrap-row">
					<th scope="row">' . __('After design changes', 'flash-cache') . '</th>
					<td>
						<div class="radio-group"><label><input type="radio" ' . checked($values['design_changes'], 'auto', false) . ' name="flash_cache_advanced[design_changes]" value="auto"/> ' . __('Automatic (Recommended)', 'flash-cache') . '</label>
							<p class="description">' . sprintf(__('Deletes the whole cache on sites with up to %s published posts and refreshes it gradually on larger ones.', 'flash-cache'), number_format_i18n($threshold)) . '</p>
							<p class="description">' . $this_site . '</p>
						</div>
						<div class="radio-group"><label><input type="radio" ' . checked($values['design_changes'], 'delete', false) . ' name="flash_cache_advanced[design_changes]" value="delete"/> ' . __('Delete the whole cache', 'flash-cache') . '</label>
							<p class="description">' . __('Pages are cached again as they are visited or preloaded. Best for small and medium sites.', 'flash-cache') . '</p>
						</div>
						<div class="radio-group"><label><input type="radio" ' . checked($values['design_changes'], 'gradual', false) . ' name="flash_cache_advanced[design_changes]" value="gradual"/> ' . __('Refresh gradually', 'flash-cache') . '</label>
							<p class="description">' . __('Visitors keep getting the current pages while they are replaced a few at a time, by the preload or on their next visit. No empty cache, no load spike. Best for large sites.', 'flash-cache') . '</p>
						</div>
						<div class="radio-group"><label><input type="radio" ' . checked($values['design_changes'], 'none', false) . ' name="flash_cache_advanced[design_changes]" value="none"/> ' . __('Do nothing', 'flash-cache') . '</label>
							<p class="description">' . __('Delete the cache yourself after each change.', 'flash-cache') . '</p>
						</div>
						<p class="description">' . __('Applies when widgets, menus, Customizer settings, block theme templates or synced patterns are saved. Switching themes always deletes the whole cache.', 'flash-cache') . '</p>
					</td>
				</tr>';
		$pro = has_filter('flash_cache_disable_cache_option');
		echo '<tr valign="top" class="wrap-row">
					<th scope="row"' . ($pro ? ' class="flash-cache-pro-option"' : '') . '>' . __('Widgets/blocks without cache', 'flash-cache') . '</th>
					<td>';

		$nopro = '<p class="description">' . __('Widget cache disabling is available in the Pro version.', 'flash-cache') . ' <span class="flash-cache-pro-badge">PRO</span></p>';
		echo apply_filters('flash_cache_disable_cache_option', $nopro, $values);

		echo '</td>
				</tr>';

		$pro = has_filter('flash_cache_shared_widget_cache_option');
		echo '<tr valign="top" class="wrap-row">
					<th scope="row"' . ($pro ? ' class="flash-cache-pro-option"' : '') . '>' . __('Shared widget cache', 'flash-cache') . '</th>
					<td>';
		$nopro = '<p class="description">' . __('Building each widget once for the whole site, and updating it inside the cached pages when it is saved, is available in the Pro version.', 'flash-cache') . ' <span class="flash-cache-pro-badge">PRO</span></p>';
		echo apply_filters('flash_cache_shared_widget_cache_option', $nopro, $values);
		echo '</td>
				</tr>';
	}

	/**
	 * A Pro option shown in the free plugin: its label, what it does and the PRO badge.
	 * $close = false leaves the cell open, for the free markup that follows to close it.
	 */
	private static function pro_row($label, $description, $close = true) {
		return '<tr valign="top" class="wrap-row"><th scope="row">' . esc_html($label) . '</th><td><p class="description">' . esc_html($description) . ' <span class="flash-cache-pro-badge">PRO</span></p>' . ($close ? '</td></tr>' : '');
	}

	private static function optimization_rows($values) {
		echo '
					<tr valign="top" class="wrap-row">
						<th scope="row">' . __('Optimize styles', 'flash-cache') . '</th>
						<td>
							<div class="switch switch--horizontal switch--no-label">
								<input type="radio" ' . checked($values['optimize_styles'], false, false) . ' name="flash_cache_advanced[optimize_styles]" value="0"/>
								<label for="flash_cache_advanced[optimize_styles]">' . __('Off', 'flash-cache') . '</label>
								<input type="radio" ' . checked($values['optimize_styles'], true, false) . ' name="flash_cache_advanced[optimize_styles]" value="1"/>
								<label for="flash_cache_advanced[optimize_styles]">' . __('On', 'flash-cache') . '</label>
								<span class="toggle-outside">
									<span class="toggle-inside"></span>
								</span>
							</div>
							<p class="description">' . __('Joins the stylesheets of each page into shared files, in their order and with their media, so pages load fewer files.', 'flash-cache') . '</p>
							<div id="advanced_settings_styles">
							' . (has_filter('flash_cache_optimize_styles_extra_html') ? apply_filters('flash_cache_optimize_styles_extra_html', '', $values) : '<table class="form-table">' . self::pro_row(__('Minify styles', 'flash-cache'), __('Removes spaces and comments from the combined stylesheets, so they weigh less.', 'flash-cache')) . '</table>') . '
							
							' . (has_filter('flash_cache_optimize_fonts_extra_html') ? apply_filters('flash_cache_optimize_fonts_extra_html', '', $values) : '<table class="form-table">' . self::pro_row(__('Cache Google fonts', 'flash-cache'), __('Keeps a copy of Google Fonts on your server, so pages make fewer requests to other sites.', 'flash-cache')) . '</table>') . '
							</div>  
						</td>
					</tr>';
		echo '
					<tr valign="top" class="wrap-row">
						<th scope="row">' . __('Optimize scripts', 'flash-cache') . '</th>
						<td>
							<div class="switch switch--horizontal switch--no-label">
								<input type="radio" ' . checked($values['optimize_scripts'], false, false) . ' name="flash_cache_advanced[optimize_scripts]" value="0"/>
								<label for="flash_cache_advanced[optimize_scripts]">' . __('Off', 'flash-cache') . '</label>
								<input type="radio" ' . checked($values['optimize_scripts'], true, false) . ' name="flash_cache_advanced[optimize_scripts]" value="1"/>
								<label for="flash_cache_advanced[optimize_scripts]">' . __('On', 'flash-cache') . '</label>
								<span class="toggle-outside">
									<span class="toggle-inside"></span>
								</span>
							</div>
							<p class="description">' . __('Joins consecutive scripts into shared files, keeping their order and their place in the page, so pages load fewer files.', 'flash-cache') . '</p>';

		echo '
							<div class="flash_cache_avoid_optimize" style="display:none">
								<p class="description"><b>' . __('If something stops working with optimized scripts, leave the scripts involved in place:', 'flash-cache') . '</b></p>
								<table class="form-table">
									<tbody>
										<tr valign="top" class="wrap-row">
											<th scope="row">' . __('Leave inline scripts in place', 'flash-cache') . '</th>
											<td>
												<div class="switch switch--horizontal switch--no-label">
													<input type="radio" checked="checked" name="flash_cache_advanced[inline_scripts]"' . checked($values["inline_scripts"], false, false) . ' value="0">
													<label for="flash_cache_advanced[inline_scripts]">' . __('Off', 'flash-cache') . '</label>
													<input type="radio" name="flash_cache_advanced[inline_scripts]"' . checked($values["inline_scripts"], true, false) . ' value="1">
													<label for="flash_cache_advanced[inline_scripts]">' . __('On', 'flash-cache') . '</label>
													<span class="toggle-outside">
														<span class="toggle-inside"></span>
													</span>
												</div>
												<p class="description">' . __('Scripts written inside the page stay where they are.', 'flash-cache') . '</p>
											</td>
										</tr>
										<tr valign="top" class="wrap-row">
											<th scope="row">' . __('Leave theme scripts in place', 'flash-cache') . '</th>
											<td>
												<div class="switch switch--horizontal switch--no-label">
													<input type="radio" checked="checked" name="flash_cache_advanced[theme_files]"' . checked($values["theme_files"], false, false) . ' value="0">
													<label for="flash_cache_advanced[theme_files]">' . __('Off', 'flash-cache') . '</label>
													<input type="radio" name="flash_cache_advanced[theme_files]"' . checked($values["theme_files"], true, false) . ' value="1">
													<label for="flash_cache_advanced[theme_files]">' . __('On', 'flash-cache') . '</label>
													<span class="toggle-outside">
														<span class="toggle-inside"></span>
													</span>
												</div>
												<p class="description">' . __('Scripts of the active theme, and of its parent theme, stay as separate files.', 'flash-cache') . '</p>
											</td>
										</tr>
										 <tr valign="top" class="wrap-row">
											<th scope="row">' . __('Leave plugin scripts in place', 'flash-cache') . '</th>
											<td>
												<div class="switch switch--horizontal switch--no-label">
													<input type="radio" checked="checked" name="flash_cache_advanced[plugins_files]"' . checked($values["plugins_files"], false, false) . ' value="0">
													<label for="flash_cache_advanced[plugins_files]">' . __('Off', 'flash-cache') . '</label>
													<input type="radio" name="flash_cache_advanced[plugins_files]"' . checked($values["plugins_files"], true, false) . ' value="1">
													<label for="flash_cache_advanced[plugins_files]">' . __('On', 'flash-cache') . '</label>
													<span class="toggle-outside">
														<span class="toggle-inside"></span>
													</span>
												</div>
												<p class="description">' . __('Scripts of plugins stay as separate files.', 'flash-cache') . '</p>
												' . (has_filter('flash_cache_exclude_scripts_extra_html') ? apply_filters('flash_cache_exclude_scripts_extra_html', '', $values) : '<p class="description">' . __('Choosing each plugin individually is available in the Pro version.', 'flash-cache') . ' <span class="flash-cache-pro-badge">PRO</span></p></td></tr>' . self::pro_row(__('Avoid optimizing JavaScript by file', 'flash-cache'), __('Leaves specific script files in place, by name or path.', 'flash-cache'), false)) . '
											</td>
										</tr>

									<tr valign="top" class="wrap-row">
										<th scope="row">' . __('Scripts from other domains', 'flash-cache') . '</th>
										<td>
										<div class="switch switch--horizontal switch--no-label">
											<input type="radio" checked="checked" name="flash_cache_advanced[social_scripts]"' . checked($values["social_scripts"], false, false) . ' value="0">
											<label for="flash_cache_advanced[social_scripts]">' . __('Off', 'flash-cache') . '</label>
											<input type="radio" name="flash_cache_advanced[social_scripts]"' . checked($values["social_scripts"], true, false) . ' value="1">
											<label for="flash_cache_advanced[social_scripts]">' . __('On', 'flash-cache') . '</label>
											<span class="toggle-outside">
												<span class="toggle-inside"></span>
											</span>
										</div>
										<p class="description">' . __('Off (recommended): analytics, ads, social and other third-party scripts keep loading from their own servers, where they stay up to date. On: they are copied into the combined files.', 'flash-cache') . '</p>
										</td>
									</tr>
									</tbody>
								</table>
							</div>';
		
		echo (has_filter('flash_cache_optimize_scripts_extra_html') ? apply_filters('flash_cache_optimize_scripts_extra_html', '', $values) : '<table class="form-table advanced-minify-scripts">' . self::pro_row(__('Minify scripts', 'flash-cache'), __('Removes spaces and comments from the combined scripts, so they weigh less.', 'flash-cache')) . '</table>') . ' 
			
						</td>
					</tr>';

	}

	/**
	 * nginx reads the rules only from its own configuration: the row says whether it runs the
	 * current ones and, when it does not, which line to add.
	 */
	private static function nginx_rules_row() {
		$status	 = flash_cache_nginx_rules_status();
		$include = 'include "' . get_home_path() . 'nginx.conf";';
		$check	 = '<a href="' . esc_url(admin_url('admin.php?page=flash_cache_setting')) . '" class="button">' . esc_html__('Check again', 'flash-cache') . '</a>';
		$html	 = '<tr valign="top"><th scope="row">' . esc_html__('Server rules', 'flash-cache') . '</th><td>';
		if ('ok' === $status) {
			$html .= '<p><span class="dashicons dashicons-yes-alt" style="color:#00a32a" aria-hidden="true"></span> ' . esc_html__('nginx is serving cached pages with the current rules.', 'flash-cache') . '</p>';
			$html .= '<p class="description">' . esc_html__('After changing settings, reload nginx so it reads the updated rules.', 'flash-cache') . '</p>';
			return $html . '</td></tr>';
		}
		if ('outdated' === $status) {
			$html .= '<p><span class="dashicons dashicons-warning" style="color:#dba617" aria-hidden="true"></span> ' . esc_html__('nginx is running an older version of the Flash Cache rules. Reload nginx to apply the current settings.', 'flash-cache') . '</p>';
			return $html . '<p>' . $check . '</p></td></tr>';
		}
		$html .= '<p><span class="dashicons dashicons-warning" style="color:#dba617" aria-hidden="true"></span> ' . ('unknown' === $status ? esc_html__('The site could not be reached to check whether nginx reads the Flash Cache rules.', 'flash-cache') : esc_html__('nginx is not reading the Flash Cache rules yet: pages are cached but still built by PHP.', 'flash-cache')) . '</p>';
		$html .= '<p class="description">' . esc_html__('Add this line inside the server { } block of your site, before its location rules, and reload nginx:', 'flash-cache') . '</p>';
		$html .= '<p><input type="text" class="large-text code" style="width:100%;max-width:100%" id="flash_cache_nginx_include" readonly value="' . esc_attr($include) . '" onfocus="this.select()"/></p>';
		$html .= '<p><button type="button" class="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById(\'flash_cache_nginx_include\').value); this.textContent = ' . esc_attr(wp_json_encode(__('Copied', 'flash-cache'))) . ';">' . esc_html__('Copy', 'flash-cache') . '</button> ' . $check . '</p>';
		$html .= '<p class="description">' . esc_html__('Flash Cache keeps that file up to date; reload nginx after changing settings.', 'flash-cache') . '</p>';
		return $html . '</td></tr>';
	}

	private static function openlitespeed_rules_row() {
		$html = '<tr valign="top"><th scope="row">' . esc_html__('Server rules', 'flash-cache') . '</th><td>';
		if (flash_cache_get_general_settings()['activate']) {
			$status = flash_cache_openlitespeed_rules_status();
			if ('ok' === $status) {
				$html .= '<p><span class="dashicons dashicons-yes-alt" style="color:#00a32a" aria-hidden="true"></span> ' . esc_html__('OpenLiteSpeed is serving cached pages with the current rules.', 'flash-cache') . '</p>';
				$html .= '<p class="description">' . esc_html__('OpenLiteSpeed reads .htaccess only when it restarts: restart it after changing settings.', 'flash-cache') . '</p>';
			} else {
				$html .= '<p><span class="dashicons dashicons-warning" style="color:#dba617" aria-hidden="true"></span> ' . ('unknown' === $status ? esc_html__('The site could not be reached to check whether OpenLiteSpeed runs the current Flash Cache rules.', 'flash-cache') : esc_html__('OpenLiteSpeed is not running the current Flash Cache rules yet. Restart it to apply them: Graceful Restart in WebAdmin, or the LiteSpeed restart of your hosting panel.', 'flash-cache')) . '</p>';
				$html .= '<p class="description">' . esc_html__('Required: until then, pages follow the rules OpenLiteSpeed loaded last, or are built by PHP.', 'flash-cache') . '</p>';
				$html .= '<p><a href="' . esc_url(admin_url('admin.php?page=flash_cache_setting')) . '" class="button">' . esc_html__('Check again', 'flash-cache') . '</a></p>';
			}
		}
		if (get_option('flash_cache_ols_headers_dismissed') !== FLASH_CACHE_VERSION) {
			$home_root = parse_url(get_bloginfo('url'), PHP_URL_PATH);
			$uri	   = trailingslashit(is_string($home_root) ? $home_root : '/') . flash_cache_get_cache_dir();
			$ttl	   = absint(flash_cache_get_advanced_settings()['ttl_default']);
			$context   = 'context ' . $uri . " {\n  location                \$DOC_ROOT" . $uri . "\n  allowBrowse             1\n  extraHeaders            <<<END_extraHeaders\nset Cache-Control \"max-age=" . $ttl . ", public\"\nset Cached-By \"Flash Cache\"\n  END_extraHeaders\n}";
			$html	  .= '<div class="flash-cache-ols-headers" style="margin-top:12px;padding:1px 12px;background:#fff;border:1px solid #c3c4c7;border-left:4px solid #72aee6"><p><strong>' . esc_html__('Optional: browser cache headers', 'flash-cache') . '</strong></p>';
			/* translators: %s: browser cache time, such as "1 day". */
			$html	  .= '<p>' . esc_html(sprintf(__('Cached pages already work without this. OpenLiteSpeed ignores the headers in .htaccess, so browsers get no caching instructions for them. Add this context to the virtual host of your site to let browsers keep each page for %s, so return visits load faster, then restart OpenLiteSpeed:', 'flash-cache'), flash_cache_seconds_2_time($ttl))) . '</p>';
			$html	  .= '<p><textarea class="large-text code" rows="8" id="flash_cache_ols_context" readonly onfocus="this.select()">' . esc_textarea($context) . '</textarea></p>';
			if (flash_cache_get_advanced_settings()['viewer_protocol_policy'] == 'redirect_http_to_https') {
				/* translators: %s: a header line for the OpenLiteSpeed configuration. */
				$html .= '<p class="description">' . sprintf(esc_html__('For "Redirect HTTP to HTTPS", also add %s to the Header Operations of the / context.', 'flash-cache'), '<code>' . esc_html('set Strict-Transport-Security "' . flash_cache_hsts_header() . '"') . '</code>') . '</p>';
			}
			$html .= '<p><button type="button" class="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById(\'flash_cache_ols_context\').value); this.textContent = ' . esc_attr(wp_json_encode(__('Copied', 'flash-cache'))) . ';">' . esc_html__('Copy', 'flash-cache') . '</button> ';
			$html .= '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=flash_cache_dismiss_ols_headers'), 'flash_cache_dismiss_ols_headers')) . '" class="button-link">' . esc_html__('Dismiss until the next update', 'flash-cache') . '</a></p></div>';
		}
		return $html . '</td></tr>';
	}

	public static function dismiss_ols_headers() {
		flash_cache_verify_admin_request('flash_cache_dismiss_ols_headers');
		update_option('flash_cache_ols_headers_dismissed', FLASH_CACHE_VERSION, false);
		wp_safe_redirect(admin_url('admin.php?page=flash_cache_setting'));
		exit;
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
			submit_button(__('Update Rules', 'flash-cache'));
			echo '<hr/></form>';
		}
	}

	public static function save_general() {
		flash_cache_verify_admin_request('save_flash_cache_general');

		$post_values = array();
		if (!empty($_POST['flash_cache_general']) && is_array($_POST['flash_cache_general'])) {
			$post_values = wp_unslash($_POST['flash_cache_general']);
		}
		$post_values = flash_cache_sanitize_settings_deep(self::default_general_options(), $post_values);

		$new_options = wp_parse_args($post_values, self::default_general_options());
		$new_options = apply_filters('flash_cache_check_general_settings', $new_options);

		update_option('flash_cache_settings', $new_options);
		flash_cache_update_htaccess();

		flash_cache_notices::add(__('Settings updated', 'flash-cache'));
		flash_cache_redirect_back(admin_url('admin.php?page=flash_cache_setting'));
	}

	public static function save_advanced() {
		flash_cache_verify_admin_request('save_flash_cache_advanced');

		$post_values = array();
		if (!empty($_POST['flash_cache_advanced']) && is_array($_POST['flash_cache_advanced'])) {
			$post_values = wp_unslash($_POST['flash_cache_advanced']);
		}
		$defaults	 = self::default_advanced_options();
		$post_values = flash_cache_sanitize_settings_deep($defaults, $post_values);

		$new_options = array_merge(flash_cache_get_advanced_settings(), $post_values);
		foreach (self::advanced_sections()[self::requested_section()] as $key) {
			if (!array_key_exists($key, $post_values) && array_key_exists($key, $defaults)) {
				$new_options[$key] = is_array($defaults[$key]) ? array() : $defaults[$key];
			}
		}
		$new_options = apply_filters('flash_cache_check_advanced_settings', $new_options);

		$cache_dir = flash_cache_sanitize_cache_dir($new_options['cache_dir']);
		if (false === $cache_dir) {
			$new_options['cache_dir'] = flash_cache_get_cache_dir();
			flash_cache_notices::add(array(
				'text'	=> __('The cache folder must be a folder inside the site, such as flash_cache/, outside the WordPress folders. The current folder was kept.', 'flash-cache'),
				'error' => true,
			));
		} else {
			$new_options['cache_dir'] = $cache_dir;
		}

		if (!in_array($new_options['refresh_mode'], array('visitors', 'cron'), true)) {
			$new_options['refresh_mode'] = 'visitors';
		}
		if (!in_array($new_options['design_changes'], array('auto', 'delete', 'gradual', 'none'), true)) {
			$new_options['design_changes'] = 'auto';
		}

		$old_options = flash_cache_get_advanced_settings();
		if ($old_options['lock_type'] != $new_options['lock_type'] || flash_cache_get_cache_dir() != $new_options['cache_dir']) {
			flash_cache_delete_dir(flash_cache_get_cache_root(), true);
		}

		$new_options  = apply_filters('flash_cache_save_advanced_options', $new_options, $post_values);
		$redirect_url = apply_filters('flash_cache_save_advanced_redirect_url', wp_get_referer() ?: admin_url('admin.php?page=' . self::advanced_pages()[self::requested_section()]['slug']));

		update_option('flash_cache_advanced_settings', $new_options);
		flash_cache_update_htaccess();
		flash_cache_purge_all();

		flash_cache_notices::add(__('Settings updated', 'flash-cache'));
		wp_safe_redirect($redirect_url);
		exit;
	}

	public static function is_widget_cache_disabled() {
		return !empty(flash_cache_get_advanced_settings()['disable_widget_cache']);
	}

	public static function update_httacess() {
		flash_cache_verify_admin_request('update_flash_cache_httacess');
		flash_cache_update_htaccess();
		flash_cache_notices::add(__('Web server rules updated', 'flash-cache'));
		flash_cache_redirect_back(admin_url('admin.php?page=flash_cache_setting'));
	}

	public static function delete_cache() {
		flash_cache_verify_admin_request('delete_flash_cache');
		flash_cache_purge_all();
		flash_cache_notices::add(__('The cache files have been deleted.', 'flash-cache'));
		wp_safe_redirect(admin_url('admin.php?page=flash_cache_setting'));
		exit;
	}

	public static function reset_to_default_general_settings() {
		flash_cache_verify_admin_request('reset_to_default_general_settings');

		$new_options = wp_parse_args(array('activate' => false), self::default_general_options());
		$new_options = apply_filters('flash_cache_check_general_settings', $new_options);

		update_option('flash_cache_settings', $new_options);
		flash_cache_update_htaccess();
		flash_cache_purge_all();

		flash_cache_notices::add(__('Defaults have been restored.', 'flash-cache'));
		wp_safe_redirect(admin_url('admin.php?page=flash_cache_setting'));
		exit;
	}

	public static function reset_to_default_advanced_options() {
		flash_cache_verify_admin_request('reset_to_default_advanced_options');

		$section  = self::requested_section();
		$defaults = self::default_advanced_options();
		$options  = flash_cache_get_advanced_settings();
		foreach (self::advanced_sections()[$section] as $key) {
			if (array_key_exists($key, $defaults)) {
				$options[$key] = $defaults[$key];
			}
		}
		flash_cache_purge_all();
		update_option('flash_cache_advanced_settings', $options);
		flash_cache_update_htaccess();

		flash_cache_notices::add(__('Defaults have been restored.', 'flash-cache'));
		wp_safe_redirect(admin_url('admin.php?page=' . self::advanced_pages()[$section]['slug']));
		exit;
	}

	public static function flash_cache_check_permalinks() {
		global $current_screen;
		if( isset($current_screen->id) &&
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
