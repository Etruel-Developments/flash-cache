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