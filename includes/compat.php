<?php

/**
 * @package         etruel\Flash Cache
 * @subpackage 	    Compatibility
 * @author          Esteban Truelsegaard
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

/**
 * What this version expects of Flash Cache Pro. Pro checks the same constant on its side and turns
 * its features off when it is older; releases that do not change the contract leave it alone.
 */
class flash_cache_compat {

	public static function hooks() {
		if (!is_admin()) {
			return;
		}
		add_action('admin_init', array(__CLASS__, 'purge_once'));
		add_action('admin_notices', array(__CLASS__, 'notice'));
	}

	/**
	 * A Pro older than the one this version asks for that cannot say so itself: from 2.0 on, Pro
	 * checks this on its own side and turns its features off with its own notice.
	 */
	public static function pro_too_old() {
		return defined('FLASH_CACHE_PRO_VERSION') && !defined('FLASH_CACHE_PRO_INERT') && version_compare(FLASH_CACHE_PRO_VERSION, FLASH_CACHE_REQUIRED_PRO_VERSION, '<');
	}

	/**
	 * Pages cached while the two plugins disagreed are emptied once per Pro version seen.
	 */
	public static function purge_once() {
		if (!self::pro_too_old() || !current_user_can('manage_options')) {
			return;
		}
		if (get_option('flash_cache_pro_too_old_purged') !== FLASH_CACHE_PRO_VERSION) {
			flash_cache_purge_all();
			update_option('flash_cache_pro_too_old_purged', FLASH_CACHE_PRO_VERSION, false);
		}
	}

	public static function notice() {
		if (!self::pro_too_old() || !current_user_can('update_plugins')) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>' . esc_html__('Flash Cache Pro needs an update.', 'flash-cache') . '</strong> ';
		printf(
			/* translators: 1: installed Pro version, 2: Flash Cache version, 3: Pro version to install. */
			esc_html__('Flash Cache Pro %1$s does not work with Flash Cache %2$s: its blocks and widgets without cache may show old content. Update Flash Cache Pro to %3$s or later from the Plugins screen; updates need an active license.', 'flash-cache'),
			esc_html(FLASH_CACHE_PRO_VERSION),
			esc_html(FLASH_CACHE_VERSION),
			esc_html(FLASH_CACHE_REQUIRED_PRO_VERSION)
		);
		echo ' <a href="' . esc_url(admin_url('plugins.php')) . '">' . esc_html__('Go to Plugins', 'flash-cache') . '</a> &middot; <a href="https://etruel.com/my-account/" target="_blank" rel="noopener">' . esc_html__('My account', 'flash-cache') . '</a></p></div>';
	}

}

flash_cache_compat::hooks();
