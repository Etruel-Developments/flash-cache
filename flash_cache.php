<?php
/**
 * Plugin Name: Flash Cache
 * Plugin URI: https://flashcache.net
 * Description: Flash Cache is a plugin to really improve performance of Wordpress Websites. If you like it, please rate it 5 stars to allow us to improve its development.
 * Version: 4.0
 * Requires at least: 5.9
 * Requires PHP: 7.2
 * Author: Etruel Developments LLC
 * Author URI: https://etruel.com/
 * Text Domain: flash-cache
 * Domain Path: /languages/
 * License: GPLv2 or later

 * @package         etruel\Flash Cache
 * @author          Esteban Truelsegaard
 * @author          Sebastian Robles
 * @copyright       Copyright (c) 2017
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

// Plugin version
if (!defined('FLASH_CACHE_VERSION')) {
	define('FLASH_CACHE_VERSION', '4.0');
}
// Oldest Flash Cache Pro this version works with; raised only by a release that changes what Pro relies on.
if (!defined('FLASH_CACHE_REQUIRED_PRO_VERSION')) {
	define('FLASH_CACHE_REQUIRED_PRO_VERSION', '2.0');
}

/**
 * Main Flash Cache class
 *
 * @since 1.0.0
 */
class Flash_Cache {

	/**
	 * @var         Flash_Cache $instance The one true Flash Cache
	 * @since       1.0.0
	 */
	private static $instance = null;

	/**
	 * Get active instance
	 *
	 * @access      public
	 * @since       1.0.0
	 * @return      object self::$instance The one true Flash Cache
	 */
	public static function getInstance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
			self::$instance->constants();
			self::$instance->hooks();
			self::$instance->includes();
		}
		return self::$instance;
	}

	/**
	 * Static function constants
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function constants() {
		// Plugin Folder Path
		if (!defined('FLASH_CACHE_PLUGIN_DIR')) {
			define('FLASH_CACHE_PLUGIN_DIR', plugin_dir_path(__FILE__));
		}

		// Plugin Folder URL
		if (!defined('FLASH_CACHE_PLUGIN_URL')) {
			define('FLASH_CACHE_PLUGIN_URL', plugin_dir_url(__FILE__));
		}

		// Plugin Root File
		if (!defined('FLASH_CACHE_PLUGIN_FILE')) {
			define('FLASH_CACHE_PLUGIN_FILE', __FILE__);
		}
	}

	/**
	 * Static function includes
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function includes() {
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/plugin_functions.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/functions.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/stats.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/process.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/posts.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/design.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/settings.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/notices.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/patterns.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/version.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/preload.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/about.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/compat.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/votes.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/enviroment.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/assets.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/optimize_styles.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/optimize_scripts.php';
		require_once FLASH_CACHE_PLUGIN_DIR . 'includes/optimize_fonts.php';
	}

	/**
	 * Static function hooks
	 * Add all hooks needs to primary feature.
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function hooks() {
		register_deactivation_hook(__FILE__, array(__CLASS__, 'deactivation'));
		register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));
		add_action('permalink_structure_changed', 'flash_cache_changes_permalinks', 10, 2);
		add_action('init', [self::$instance, 'load_textdomain'] );

	}

	/**
	 * Static function load_textdomain 
	 * Load the text domain.
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function load_textdomain() {
		// Set filter for plugin's languages directory
		$lang_dir	 = dirname(plugin_basename(__FILE__)) . '/languages/';
		$lang_dir	 = apply_filters('flash_cache_languages_directory', $lang_dir);
		load_plugin_textdomain('flash-cache', false, $lang_dir);
	}

	/**
	 * A Multisite network shares one .htaccess and one home folder between its sites, and the
	 * rules and cache folders here belong to a single site.
	 */
	public static function activation() {
		if (is_multisite()) {
			wp_die(esc_html__('Flash Cache works on single WordPress sites and cannot be activated on a Multisite network.', 'flash-cache'), esc_html__('Plugin not activated', 'flash-cache'), array('back_link' => true));
		}
		// The cache comes back as it was left: deactivation removed its rules, not the "Enable cache" setting.
		self::getInstance();
		if (flash_cache_get_general_settings()['activate']) {
			flash_cache_update_htaccess();
		}
	}

	/**
	 * Static function deactivation
	 * Deactivation action hook
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function deactivation() {
		flash_cache_remove_rules();
		// A cache too large to delete now is left in .trash/ and deleted after the next activation.
		flash_cache_purge_all();
		flash_cache_empty_trash(20);
		if (!is_dir(flash_cache_get_cache_root() . '.trash')) {
			flash_cache_delete_dir(flash_cache_get_cache_root(), true);
		}
		flash_cache_remove_breakspaces(get_home_path() . '.htaccess');
		foreach (array('flash_cache_cron', 'flash_cache_rebuild_post', 'flash_cache_rebuild_comment_post', 'flash_cache_empty_trash', 'flash_cache_expire_stale') as $hook) {
			wp_unschedule_hook($hook);
		}
	}

	/**
	 * Static function uninstall
	 * uninstall action hook
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function uninstall() {
		if (!class_exists('flash_cache_version')) {
			require_once plugin_dir_path(__FILE__) . 'includes/version.php';
		}
		if (!function_exists('flash_cache_delete_all_options')) {
			require_once plugin_dir_path(__FILE__) . 'includes/functions.php';
		}
		flash_cache_version::delete_all_patterns();
		flash_cache_delete_all_options();
	}
}

/**
 * The main function responsible for returning the one true Flash Cache
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \Flash_Cache The one true Flash Cache
 *
 * @todo        Inclusion of the activation code below isn't mandatory, but
 *              can prevent any number of errors, including fatal errors, in
 *              situations where your extension is activated but EDD is not
 *              present.
 */
function Flash_Cache_load() {
	return Flash_Cache::getInstance();
}

add_action('plugins_loaded', 'Flash_Cache_load');
register_activation_hook(__FILE__, array('Flash_Cache', 'activation'));
