<?php

if (!defined('ABSPATH')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

add_action('admin_init', 'flash_cache_admin_init');

function create_flash_lock_table() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS". $wpdb->prefix . flash_cache_settings::$flash_cache_table ."(
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        option_lock VARCHAR(191) NOT NULL DEFAULT '',
        option_value LONGTEXT NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'create_flash_lock_table');

function flash_cache_admin_init() {
	add_filter('plugin_row_meta', 'flash_cache_init_row_meta', 10, 2);
	add_filter('plugin_action_links_' . plugin_basename(FLASH_CACHE_PLUGIN_FILE), 'flash_cache_init_action_links');
}

/**
 * Actions-Links del Plugin
 *
 * @param   array   $data  Original Links
 * @return  array   $data  modified Links
 */
function flash_cache_init_action_links($data) {
	if (!current_user_can('manage_options')) {
		return $data;
	}
	return array_merge(
			$data,
			array(
				'<a href="' . admin_url('admin.php?page=flash_cache_setting') . '" title="' . __('Go to Flash Cache Settings Page') . '">' . __('Settings') . '</a>',
			)
	);
}

/**
 * Plugin Meta-Links
 *
 * @param   array   $data  Original Links
 * @param   string  $page  plugin actual
 * @return  array   $data  modified Links
 */
function flash_cache_init_row_meta($data, $page) {
	if (basename($page) != 'flash_cache.php') {
		return $data;
	}
	return array_merge(
			$data,
			array(
				'<a href="https://etruel.com/my-account/support/" target="_blank">' . __('Support') . '</a>',
				'<a href="' . admin_url('admin.php?page=flash_cache_advanced_setting') . '" title="' . __('Advanced Settings Page') . '">' . __('Advanced Settings') . '</a>',
			)
	);
}
