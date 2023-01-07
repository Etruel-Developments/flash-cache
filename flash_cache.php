<?php
/**
 * Plugin Name: Flash Cache
 * Plugin URI: https://flashcache.net
 * Description: Flash Cache is a plugin to really improve performance of Wordpress Websites. If you like it, please rate it 5 stars to allow us to improve its development.
 * Version: 3.0
 * Author: Etruel Developments LLC
 * Author URI: https://etruel.com/
 * Text Domain: flash-cache
 * Domain Path: /languages/
 * 
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
	define('FLASH_CACHE_VERSION', '3.0');
}


if ( ! class_exists('Flash_Cache') ) :

	/**
	 * Main Flash Cache class
	 *
	 * @since 1.0.0
	 */
	class Flash_Cache {

		/**
		 * @var         Flash Cache $instance The one true Flash Cache
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
				self::$instance->load_text_domain();
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

			if (!defined('FLASH_CACHE_STORE_URL')) {
				define('FLASH_CACHE_STORE_URL', 'https://etruel.com');
			}

			if (!defined('FLASH_CACHE_ITEM_NAME')) {
				define('FLASH_CACHE_ITEM_NAME', 'Flash Cache');
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
			require_once FLASH_CACHE_PLUGIN_DIR . 'includes/process.php';
			require_once FLASH_CACHE_PLUGIN_DIR . 'includes/posts.php';
			require_once FLASH_CACHE_PLUGIN_DIR . 'includes/settings.php';
			require_once FLASH_CACHE_PLUGIN_DIR . 'includes/notices.php';
			require_once FLASH_CACHE_PLUGIN_DIR . 'includes/patterns.php';
			require_once FLASH_CACHE_PLUGIN_DIR . 'includes/version.php';
			require_once FLASH_CACHE_PLUGIN_DIR . 'includes/preload.php';
			require_once FLASH_CACHE_PLUGIN_DIR . 'includes/enviroment.php';
			require_once FLASH_CACHE_PLUGIN_DIR . 'includes/optimize_styles.php';
			require_once FLASH_CACHE_PLUGIN_DIR . 'includes/optimize_scripts.php';
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
		}

		/**
		 * Static function load_text_domain 
		 * Load the text domain.
		 * @access public
		 * @return void
		 * @since 1.0.0
		 */
		public static function load_text_domain() {
			// Set filter for plugin's languages directory
			$lang_dir	 = dirname(plugin_basename(__FILE__)) . '/languages/';
			$lang_dir	 = apply_filters('flash_cache_languages_directory', $lang_dir);

			// Traditional WordPress plugin locale filter
			$locale	 = apply_filters('plugin_locale', get_locale(), 'flash-cache');
			$mofile	 = sprintf('%1$s-%2$s.mo', 'flash-cache', $locale);

			// Setup paths to current locale file
			$mofile_local	 = $lang_dir . $mofile;
			$mofile_global	 = WP_LANG_DIR . '/flash-cache/' . $mofile;

			if (file_exists($mofile_global)) {
				// Look in global /wp-content/languages/flash_cache/ folder
				load_textdomain('flash-cache', $mofile_global);
			} elseif (file_exists($mofile_local)) {
				// Look in local /wp-content/plugins/flash_cache/languages/ folder
				load_textdomain('flash-cache', $mofile_local);
			} else {
				// Load the default language files
				load_plugin_textdomain('flash-cache', false, $lang_dir);
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
			$advanced_settings	 = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
			flash_cache_remove_rules();
			$cache_dir			 = get_home_path() . $advanced_settings['cache_dir'];
			flash_cache_delete_dir($cache_dir, true);
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
			if ( ! function_exists('flash_cache_delete_all_options')) {
				require_once plugin_dir_path(__FILE__) . 'includes/functions.php';
			}
			flash_cache_version::delete_all_patterns();
			delete_option('flash_cache_version');
			delete_option('flash_cache_settings');
			delete_option('flash_cache_advanced_settings');
			delete_option('flash_cache_disk_usage');
			flash_cache_delete_all_options();
		}

	}

	endif;

/**
 * The main function responsible for returning the one true Flash Cache
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \Flash Cache The one true Flash Cache
 *
 * @todo        Inclusion of the activation code below isn't mandatory, but
 *              can prevent any number of errors, including fatal errors, in
 *              situations where your extension is activated but EDD is not
 *              present.
 */
if ( ! function_exists('Flash_Cache_load') ):
	function Flash_Cache_load() {
		
		return Flash_Cache::getInstance();
	}
endif;
add_action('plugins_loaded', 'Flash_Cache_load');
?>