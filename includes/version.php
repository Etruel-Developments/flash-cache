<?php

/**
 * @package         etruel\Flash Cache
 * @subpackage 	   Version
 * @author          Sebastian Robles
 * @author          Esteban Truelsegaard
 * @copyright       Copyright (c) 2017
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

class flash_cache_version {

	public static function hooks() {
		add_action('admin_init', array(__CLASS__, 'init'), 11);
	}

	public static function init() {
		$current_version = get_option('flash_cache_version', 0.0);
		if (version_compare($current_version, FLASH_CACHE_VERSION, '<')) {
			// Update

			update_option('flash_cache_version', FLASH_CACHE_VERSION);
			if (version_compare($current_version, 0.0, '=')) {
				//first time
				self::install_patterns_default();
			}

			/**
			 * If updated to 3.2 and not executed before 
			 */
			if(version_compare($current_version, '3.2', '<') && get_option('flash_cache_updated_3_2') != 1){
				$advanced_settings	 = wp_parse_args(get_option('flash_cache_advanced_settings', array()));
				if(isset($advanced_settings['lock_type']) && $advanced_settings['lock_type'] == 'db'){
					//Method for delete the cache in the database
					self::update_to_3_2();
					//call to function for delete the cache
					$cache_dir = flash_cache_get_home_path() . $advanced_settings['cache_dir'];
					flash_cache_delete_dir($cache_dir, true);
				}
			}

			if (version_compare($current_version, '3.3', '<') && get_option('flash_cache_updated_3_3') != 1) {
				//Method for delete the cache in the database
				Flash_Cache::create_flash_lock_table();
				//call to function for delete the cache
				update_option('flash_cache_updated_3_3', 1);
			}
		}
	}

	/**
	 * Function to delete on 3.2 all old records from options db with prefix flash_cache_{hash}
	 */	
	private static function update_to_3_2(){
		global $wpdb;

		try {
			$wpdb->query('SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE');
			$wpdb->query('START TRANSACTION');
	
			// Your existing code
			$origin_url = get_site_url(null, '/');
			$origin_url = flash_cache_sanitize_origin_url($origin_url);
	
			$advanced_settings = flash_cache_get_advanced_settings();
			$cache_dir = flash_cache_get_home_path() . $advanced_settings['cache_dir'];
	
			// Delete initial path
			$cache_path = trailingslashit($cache_dir . flash_cache_get_server_name());
			$base64_cache_path = base64_encode($cache_path);
			$characters_to_delete = substr($base64_cache_path, 0, 5);
			$option_delete = "flash_cache_" . $wpdb->esc_like($characters_to_delete) . "%";
	
			$beforeQuery = $wpdb->query(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->options WHERE option_name LIKE %s",
					$option_delete
				)
			);
	
			if ($beforeQuery !== false) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
						$option_delete
					)
				);
	
				$wpdb->query('COMMIT');
				//update option for know if the delete of those registers in the database is already erased
				update_option('flash_cache_updated_3_2', 1);
			} else {
				throw new Exception('Skipping: Database query missing old format records');
			}
		} catch (Exception $e) {
			// Handle the exception (e.g., log the error)
			$wpdb->query('ROLLBACK');
		}
	}

	public static function install_patterns_default() {
		$default_html = array(
			'post_title'	 => 'Default',
			'post_status'	 => 'publish',
			'post_type'		 => 'flash_cache_patterns',
		);

		$pattern_id	 = wp_insert_post($default_html);
		$fields		 = flash_cache_patterns::default_fields_array();

		$fields['page_type']['feed']	 = 0;
		$fields['page_type']['search']	 = 0;
		foreach ($fields as $field => $value) {
			if (!is_null($value)) {
				$new = apply_filters('flash_cache_pattern_save_' . $field, $value);
				update_post_meta($pattern_id, $field, $new);
			}
		}

		$default_html = array(
			'post_title'	 => 'Feed & Search',
			'post_status'	 => 'publish',
			'post_type'		 => 'flash_cache_patterns',
		);

		$pattern_id								 = wp_insert_post($default_html);
		$fields									 = flash_cache_patterns::default_fields_array();
		$fields['page_type']['single']			 = 0;
		$fields['page_type']['frontpage']		 = 0;
		$fields['page_type']['home']			 = 0;
		$fields['page_type']['archives']		 = 0;
		$fields['page_type']['tag']				 = 0;
		$fields['page_type']['category']		 = 0;
		$fields['page_type']['author']			 = 0;
		$fields['update_on_comment']			 = 0;
		$fields['create_cache_on_insert_update'] = 0;
		$fields['update_home_on_insert_post']	 = 0;
		$fields['cache_type']					 = 'php';

		foreach ($fields as $field => $value) {
			if (!is_null($value)) {
				$new = apply_filters('flash_cache_pattern_save_' . $field, $value);
				update_post_meta($pattern_id, $field, $new);
			}
		}
	}

	public static function delete_all_patterns() {
		$patterns = get_posts(array('post_type' => 'flash_cache_patterns'));

		foreach ($patterns as $pattern) {
			wp_delete_post($pattern->ID, true);
		}
	}

}

flash_cache_version::hooks();
?>