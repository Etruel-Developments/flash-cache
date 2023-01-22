<?php

/**
 * @package         etruel\Flash Cache
 * @subpackage 	   Posts
 * @author          Sebastian Robles
 * @author          Esteban Truelsegaard
 * @copyright       Copyright (c) 2017
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

class flash_cache_posts {

	public static function hooks() {

		add_action('pingback_post', array(__CLASS__, 'cache_from_comment'), 99);
		add_action('comment_post', array(__CLASS__, 'cache_from_comment'), 99);
		add_action('edit_comment', array(__CLASS__, 'cache_from_comment'), 99);
		add_action('post_submitbox_minor_actions', array(__CLASS__, 'delete_cache_button'));
		add_action('admin_post_flash_cache_delete_cache', array(__CLASS__, 'delete_cache_action'));
		/* add_action('pre_post_update', array(__CLASS__, 'before_data_is_saved_function'), 99); 
		  add_action('save_post', array(__CLASS__, 'create_cache'), 999, 3 );
		 */
		add_action('transition_post_status', array(__CLASS__, 'status_transition'), 10, 3);
	}

	/**
	 * Static function status_transition
	 * Delete the cache of all page relationated with the post updated.
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function status_transition($new_status, $old_status, $post) {
		if ($new_status === 'publish' || $new_status === 'draft' || $new_status === 'trash') {
			self::delete_cache_post_taxonomies($post->ID);
			flash_cache_delete_cache_from_url(get_permalink($post));
			flash_cache_delete_cache_from_url(home_url('/'));
			flash_cache_delete_cache_from_url(home_url('/feed/'));
		}
	}

	public static function before_data_is_saved_function($post_id) {

		$taxonomy_names = get_post_taxonomies($post_id);

		foreach ($taxonomy_names as $key => $tax) {
			$term_list = wp_get_post_terms($post_id, $tax, array("fields" => "all"));

			foreach ($term_list as $term) {
				$term_url						 = get_term_link($term->term_id, $tax);
				$default_query					 = flash_cache_default_query();
				$current_query					 = array();
				$current_query['is_tag']		 = 1;
				$current_query['is_category']	 = 1;
				$current_query					 = wp_parse_args($current_query, $default_query);

				flash_cache_process::$force_process_type = 'curl';
				flash_cache_process::process_cache_from_query($current_query, $term_url);
			}
		}
	}

	public static function delete_cache_post_taxonomies($post_id) {
		$taxonomy_names = get_post_taxonomies($post_id);
		foreach ($taxonomy_names as $key => $tax) {
			$term_list = wp_get_post_terms($post_id, $tax, array("fields" => "all"));
			foreach ($term_list as $term) {
				$term_url = get_term_link($term->term_id, $tax);
				flash_cache_delete_cache_from_url($term_url);
			}
		}
	}

	/**
	 * Static function delete_cache_action
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function delete_cache_action() {
		if (!isset($_GET['_nonce']) || !wp_verify_nonce($_GET['_nonce'], 'delete_cache_nonce')) {
			flash_cache_notices::add(__('A problem, please try again.', 'flash-cache'));
			wp_redirect(admin_url(''));
			exit;
		}
		$referer = sanitize_text_field($referer);

		$post_id = absint(sanitize_text_field($_REQUEST['id']));
		if (empty($post_id)) {
			flash_cache_notices::add(__('Invalid post_id.', 'flash-cache'));
			wp_redirect(base64_decode($referer));
			exit;
		}
		$post = get_post($post_id);
		if (!$post) {
			flash_cache_notices::add(__('Invalid post.', 'flash-cache'));
			wp_redirect(base64_decode($referer));
			exit;
		}
		$post_path			 = flash_cache_process::get_path($post->ID, 'cURL');
		$advanced_settings	 = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
		$cache_dir			 = flash_cache_get_home_path() . $advanced_settings['cache_dir'];
		$cache_path			 = $cache_dir . flash_cache_get_server_name() . $post_path;
		if (!file_exists($cache_path)) {
			flash_cache_notices::add(__('The post has no cache.', 'flash-cache'));
			wp_redirect(base64_decode($referer));
			exit;
		}
		$html_file = $cache_path . 'index-cache.html';
		if (file_exists($html_file)) {
			@unlink($html_file);
		}
		$gzip_file = $cache_path . 'index-cache.html.gz';
		if (file_exists($gzip_file)) {
			@unlink($gzip_file);
		}
		$php_file = $cache_path . 'index-cache.php';
		if (file_exists($php_file)) {
			@unlink($php_file);
		}
		$requests_folder = $cache_path . 'requests/';
		if (file_exists($requests_folder)) {
			flash_cache_delete_dir($requests_folder);
		}
		if ($cache_dir . flash_cache_get_server_name() != $cache_path) {
			flash_cache_delete_dir($cache_path);
		}
		wp_redirect(base64_decode($referer));
	}

	/**
	 * Static function delete_cache_button
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function delete_cache_button() {
		global $post;
		$args		 = array('public' => true);
		$output		 = 'names'; // names or objects
		$post_types	 = get_post_types($args, $output);
		$is_public	 = false;
		foreach ($post_types as $kpt => $pt) {
			if ($post->post_type == $kpt || $post->post_type == $pt) {
				$is_public = true;
				break;
			}
		}
		if (!$is_public) {
			return false;
		}
		if ($post->post_status != 'publish') {
			return false;
		}
		$post_path			 = flash_cache_process::get_path($post->ID, 'cURL');
		$advanced_settings	 = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
		$cache_dir			 = flash_cache_get_home_path() . $advanced_settings['cache_dir'];
		$cache_path			 = $cache_dir . flash_cache_get_server_name() . $post_path;
		if (!file_exists($cache_path)) {
			return false;
		}
		$delete_url		 = wp_nonce_url(admin_url('admin-post.php?id=' . absint($post->ID) . '&action=flash_cache_delete_cache&referer=' . esc_attr(base64_encode(sanitize_url(wp_unslash($_SERVER['REQUEST_URI']))))), 'delete_cache_nonce', '_nonce');
		$delete_button	 = '<a  id="reset_button" class="button button-large" href="' . esc_attr($delete_url) . '" style="margin:5px;">' . __('Clear Entry Cache', 'flash-cache') . '</a>';
		echo '<div class="misc-pub-section sec_flash_cache_delete_cache_button" style="text-align:center;">
			' . $delete_button . '
		</div>';
	}

	public static function create_cache($post_id, $post, $update) {
		if ($post->post_status != 'publish') {
			return true;
		}

		$general_settings = wp_parse_args(get_option('flash_cache_settings', array()), flash_cache_settings::default_general_options());
		if (!$general_settings['activate']) {
			return true;
		}
		$args		 = array('post_type' => 'flash_cache_patterns', 'orderby' => 'ID', 'order' => 'ASC', 'numberposts' => -1);
		$patterns	 = get_posts($args);

		$create_cache			 = false;
		$create_cache_home		 = false;
		$create_cache_categories = false;
		$is_new_post			 = false;
		$modified_post			 = false;

		if ($post->post_modified_gmt == $post->post_date_gmt) {
			$is_new_post = true;
		}

		if ($post->post_modified_gmt > $post->post_date_gmt) {
			$modified_post = true;
		}

		foreach ($patterns as $pt) {

			$pattern = flash_cache_patterns::get_data($pt->ID);

			// Just Default
			$just_option_post_type_and_create_cache_on_insert_update = $pattern['page_type']['single'] && $pattern['create_cache_on_insert_update'];
			$just_option_post_type_and_update_home_on_insert_post	 = $pattern['page_type']['single'] && $pattern['update_home_on_insert_post'];
			$just_option_post_type_and_update_categories_on_post	 = $pattern['page_type']['single'] && $pattern['update_categories_on_post'];
			//$just_option_search_and_create_cache_on_insert_update = $pattern['page_type']['search'] && $pattern['create_cache_on_insert_update'];


			if ($just_option_post_type_and_create_cache_on_insert_update || $just_option_post_type_and_update_home_on_insert_post || $just_option_post_type_and_update_categories_on_post) {
				//If you do not select any of the Post Type options
				if (empty($pattern['page_type']['posts'])) {
					if ($just_option_post_type_and_create_cache_on_insert_update) {
						$create_cache = true;
						break;
					}
					if ($just_option_post_type_and_update_home_on_insert_post) {
						$create_cache_home = true;
						break;
					}
					if ($just_option_post_type_and_update_categories_on_post) {
						$create_cache_categories = true;
						break;
					}
				} else {
					if ($just_option_post_type_and_create_cache_on_insert_update) {
						foreach ($pattern['page_type']['posts'] as $pcpt => $pp) {

							if ($pcpt == $post->post_type) {
								$create_cache = true;
								break;
							}
						}
					}

					if ($just_option_post_type_and_update_home_on_insert_post) {
						foreach ($pattern['page_type']['posts'] as $pcpt => $pp) {
							if ($pcpt == $post->post_type && $pattern['update_home_on_insert_post']) {
								$create_cache_home = true;
								break;
							}
						}
					}

					if ($just_option_post_type_and_update_categories_on_post) {
						foreach ($pattern['page_type']['posts'] as $pcpt => $pp) {
							if ($pcpt == $post->post_type && $pattern['update_categories_on_post']) {
								$create_cache_categories = true;
								break;
							}
						}
					}
					/*
					  if($just_option_search_and_create_cache_on_insert_update){
						foreach ($pattern['page_type']['posts'] as $pcpt => $pp) {
							if ($pcpt == $post->post_type && $pattern['create_cache_on_insert_update']) {
								$create_cache_search = true;
								break;
							}
						}
					  } */
				}
			}
		}

		if ($create_cache) {
			self::create_cache_post_id($post_id);
		}
		if ($create_cache_home) {
			self::create_cache_home();
		}
		if ($create_cache_categories) {
			$default_posts_per_page = get_option('posts_per_page', 10);
			self::update_taxonomies(0, 0, $default_posts_per_page);
		}
		//if ($create_cache_search) {
		//self::create_cache_search();
		//}
	}

	public static function create_cache_publish($postid) {
		return self::create_cache_post_id($postid);
	}

	public static function cache_from_comment($comment_id, $status = 'NA') {
		$general_settings = wp_parse_args(get_option('flash_cache_settings', array()), flash_cache_settings::default_general_options());
		if (!$general_settings['activate']) {
			return true;
		}
		$comment = get_comment($comment_id, ARRAY_A);
		if ($status != 'NA') {
			$comment['old_comment_approved'] = $comment['comment_approved'];
			$comment['comment_approved']	 = $status;
		}

		if (( $status == 'trash' || $status == 'spam' ) && $comment['old_comment_approved'] != 1) {
			return -1;
		}
		$postid			 = $comment['comment_post_ID'];
		$post			 = get_post($postid);
		$args			 = array('post_type' => 'flash_cache_patterns', 'orderby' => 'ID', 'order' => 'ASC', 'numberposts' => -1);
		$patterns		 = get_posts($args);
		$create_cache	 = false;
		foreach ($patterns as $pt) {
			$pattern = flash_cache_patterns::get_data($pt->ID);
			if ($pattern['page_type']['single'] && $pattern['update_on_comment']) {
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
		if ($create_cache) {
			self::create_cache_post_id($postid);
		}
	}

	public static function create_cache_post_id($postid) {
		$post = get_post($postid);
		if ($post->post_status != 'publish') {
			return true;
		}
		if (!defined('FLASH_CACHE_NOT_USE_THIS_REQUEST')) {
			define('FLASH_CACHE_NOT_USE_THIS_REQUEST', true);
		}

		$default_query							 = flash_cache_default_query();
		$current_query							 = array();
		$current_query['is_page']				 = 1;
		$current_query['is_single']				 = 1;
		$current_query							 = wp_parse_args($current_query, $default_query);
		flash_cache_process::$force_process_type = 'curl';
		flash_cache_process::$optional_post_id	 = $postid;

		if (defined('AMP__VERSION')) {
			$opcional_url							 = get_permalink($postid);
			$opcional_url							 = $opcional_url . 'amp/';
			flash_cache_process::$force_permalink	 = false;
			flash_cache_process::process_cache_from_query($current_query, $opcional_url);
		}
		flash_cache_process::$force_permalink = true;
		flash_cache_process::process_cache_from_query($current_query);
	}

	public static function create_cache_home() {

		$default_query				 = flash_cache_default_query();
		$current_query				 = array();
		$current_query['is_home']	 = 1;
		$current_query				 = wp_parse_args($current_query, $default_query);

		flash_cache_process::$force_permalink	 = true;
		flash_cache_process::$force_process_type = 'curl';
		flash_cache_process::process_cache_from_query($current_query, get_site_url(null, '/'));
	}

	public static function update_taxonomies($post_id, $ttl = 0, $default_posts_per_page = 10, $cache_dir = '', $update_current_page = false) {

		if (is_null(flash_cache_process::$origin_url)) {
			flash_cache_process::$origin_url = get_site_url(null, '/');
		}
		if (empty($cache_dir)) {
			$advanced_settings	 = wp_parse_args(get_option('flash_cache_advanced_settings', array()), flash_cache_settings::default_advanced_options());
			$cache_dir			 = flash_cache_get_home_path() . $advanced_settings['cache_dir'];
		}


		//flash_cache_delete_dir($cache_dir);

		if ($post_id == 0) {
			//$categories = get_categories( array( 'hide_empty'   => 0 ) );

			$url_def	 = array();
			$url_def_tag = array();
			$url_feed	 = flash_cache_process::$origin_url . 'feed/';
			//$url_feed[] = flash_cache_process::$origin_url.'feed/';
			//$url_feed_tag[] = flash_cache_process::$origin_url.'feed/';

			$default_query					 = flash_cache_default_query();
			$current_query					 = array();
			$current_query['is_tag']		 = 1;
			$current_query['is_category']	 = 1;

			$current_query							 = wp_parse_args($current_query, $default_query);
			flash_cache_process::$force_process_type = 'curl';

			flash_cache_process::process_cache_from_query($current_query, $url_feed);
		} else {

			$server_name = flash_cache_get_server_name();

			$taxonomy_names = get_post_taxonomies($post_id);
			foreach ($taxonomy_names as $key => $tax) {
				$term_list = wp_get_post_terms($post_id, $tax, array("fields" => "all"));
				foreach ($term_list as $term) {
					$pages_url_term_array	 = array();
					$term_url				 = get_term_link($term->term_id, $tax);
					$pages_url_term_array[]	 = $term_url;
					$pages_count			 = ceil($term->count / $default_posts_per_page);
					for ($paged = 2; $paged <= $pages_count; $paged++) {
						$pages_url_term_array[] = $term_url . 'page/' . $paged . '/';
					}
					foreach ($pages_url_term_array as $page_term_url) {
						$path = str_replace(flash_cache_process::$origin_url, '', $page_term_url);
						if (stripos($path, '?') !== false) { //Not create cache of taxonomies if have query strings.
							continue;
						}
						$cache_path	 = $cache_dir . $server_name . '/' . $path;
						$cache_file	 = $cache_path . 'index-cache.html';
						if (file_exists($cache_file)) {
							if (time() - filemtime($cache_file) < (int) $ttl) {
								continue;
							}
						}

						$default_query							 = flash_cache_default_query();
						$current_query							 = array();
						$current_query['is_tag']				 = 1;
						$current_query['is_category']			 = 1;
						$current_query							 = wp_parse_args($current_query, $default_query);
						flash_cache_process::$force_process_type = 'curl';
						flash_cache_process::process_cache_from_query($current_query, $page_term_url);
						if ($update_current_page) {
							update_option('flash_cache_preload_current_post', $page_term_url);
						}
					}
				}
			}
		}
	}

}

flash_cache_posts::hooks();
?>