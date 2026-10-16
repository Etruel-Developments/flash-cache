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

	/**
	 * Listing URLs (home, term archives) already rebuilt by this process. A WP-Cron run holds
	 * only posts published before it started, so one rebuild per URL already shows them all.
	 */
	private static $rebuilt_listings = array();

	public static function hooks() {

		add_action('comment_post', array(__CLASS__, 'comment_posted'), 99, 2);
		add_action('edit_comment', array(__CLASS__, 'comment_edited'), 99);
		add_action('transition_comment_status', array(__CLASS__, 'comment_status_changed'), 99, 3);
		add_action('flash_cache_rebuild_post', array(__CLASS__, 'rebuild_post'));
		add_action('flash_cache_rebuild_comment_post', array(__CLASS__, 'rebuild_comment_post'));
		// Public: other plugins refresh a page whose content they changed with do_action('flash_cache_clear_url', $url).
		add_action('flash_cache_clear_url', 'flash_cache_delete_cache_from_url');
		add_action('post_submitbox_minor_actions', array(__CLASS__, 'delete_cache_button'));
		add_action('admin_post_flash_cache_delete_cache', array(__CLASS__, 'delete_cache_action'));
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
			flash_cache_delete_cache_from_url(home_url('/page/'));
		}
		if ($new_status === 'publish' && is_post_type_viewable($post->post_type)) {
			self::schedule('flash_cache_rebuild_post', $post->ID);
		}
	}

	/**
	 * Rebuilds run from WP-Cron, never inside the request that saves a post or a comment.
	 * WP-Cron also drops an identical event scheduled again within ten minutes.
	 */
	public static function schedule($hook, $post_id) {
		if (!flash_cache_get_general_settings()['activate'] || wp_next_scheduled($hook, array((int) $post_id))) {
			return;
		}
		wp_schedule_single_event(time(), $hook, array((int) $post_id));
	}

	/**
	 * What the pattern that caches this post asks for when it is published, updated or commented.
	 */
	public static function rebuild_targets($post) {
		$targets = array('post' => false, 'home' => false, 'terms' => false, 'comment' => false);
		$pattern = flash_cache_preaload::matching_pattern(flash_cache_patterns::get_all(), get_permalink($post), $post->post_type);
		if (null === $pattern || 'none' === $pattern['cache_type']) {
			return $targets;
		}
		$targets['post']	= !empty($pattern['create_cache_on_insert_update']);
		$targets['home']	= !empty($pattern['update_home_on_insert_post']);
		$targets['terms']	= !empty($pattern['update_categories_on_post']);
		$targets['comment'] = !empty($pattern['update_on_comment']);
		return $targets;
	}

	public static function rebuild_post($post_id) {
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'publish' || !flash_cache_get_general_settings()['activate']) {
			return;
		}
		$targets = self::rebuild_targets($post);
		if ($targets['post']) {
			self::create_cache_post_id($post_id);
		}
		if ($targets['home'] && self::claim_listing(home_url('/'))) {
			self::create_cache_home();
		}
		if ($targets['terms']) {
			self::rebuild_terms($post_id);
		}
	}

	/**
	 * The first archive page of each term of the post. Its other pages are deleted along with
	 * the post's caches and rebuilt by visits, so a large category costs one request, not one
	 * per archive page.
	 */
	public static function rebuild_terms($post_id) {
		foreach (get_post_taxonomies($post_id) as $taxonomy) {
			$current_query = flash_cache_patterns::term_query($taxonomy);
			if (!is_taxonomy_viewable($taxonomy)) {
				continue;
			}
			$terms = wp_get_post_terms($post_id, $taxonomy);
			if (is_wp_error($terms)) {
				continue;
			}
			foreach ($terms as $term) {
				$term_url = get_term_link($term, $taxonomy);
				if (is_wp_error($term_url) || strpos($term_url, '?') !== false || !self::claim_listing($term_url)) {
					continue;
				}
				flash_cache_process::$force_process_type = 'curl';
				flash_cache_process::process_cache_from_query($current_query, $term_url);
			}
		}
	}

	private static function claim_listing($url) {
		if (isset(self::$rebuilt_listings[$url])) {
			return false;
		}
		self::$rebuilt_listings[$url] = true;
		return true;
	}

	public static function rebuild_comment_post($post_id) {
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'publish' || !flash_cache_get_general_settings()['activate']) {
			return;
		}
		if (self::rebuild_targets($post)['comment']) {
			self::create_cache_post_id($post_id);
		}
	}

	public static function comment_posted($comment_id, $approved) {
		if ($approved === 1 || $approved === '1') {
			$comment = get_comment($comment_id);
			self::schedule('flash_cache_rebuild_comment_post', $comment->comment_post_ID);
		}
	}

	public static function comment_edited($comment_id) {
		$comment = get_comment($comment_id);
		if ($comment && $comment->comment_approved === '1') {
			self::schedule('flash_cache_rebuild_comment_post', $comment->comment_post_ID);
		}
	}

	public static function comment_status_changed($new_status, $old_status, $comment) {
		if ($new_status !== $old_status && ($new_status === 'approved' || $old_status === 'approved')) {
			self::schedule('flash_cache_rebuild_comment_post', $comment->comment_post_ID);
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
			if (is_wp_error($term_list)) {
				continue;
			}
			foreach ($term_list as $term) {
				$term_url = get_term_link($term->term_id, $tax);
				if (!is_wp_error($term_url)) {
					flash_cache_delete_cache_from_url($term_url);
				}
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
		$post_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
		flash_cache_verify_admin_request('delete_cache_nonce', 'edit_post', $post_id, '_nonce');

		$referer = isset($_GET['referer']) ? base64_decode(sanitize_text_field(wp_unslash($_GET['referer']))) : '';
		$back	 = $referer ? admin_url(ltrim(preg_replace('#^.*?/wp-admin/#', '', $referer), '/')) : admin_url('edit.php');

		$post = get_post($post_id);
		if (!$post) {
			flash_cache_notices::add(__('Invalid post.', 'flash-cache'));
			wp_safe_redirect($back);
			exit;
		}
		$cache_dir	= flash_cache_get_cache_root();
		$cache_path = realpath($cache_dir . flash_cache_get_server_name() . '/' . flash_cache_process::get_path($post->ID, 'cURL'));
		if (false === $cache_path) {
			flash_cache_notices::add(__('The post has no cache.', 'flash-cache'));
			wp_safe_redirect($back);
			exit;
		}
		flash_cache_delete_cache_files($cache_dir, trailingslashit(wp_normalize_path($cache_path)));
		wp_safe_redirect($back);
		exit;
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
		$post_path			 = '/' . flash_cache_process::get_path($post->ID, 'cURL');
		$advanced_settings	 = flash_cache_get_advanced_settings();
		$cache_dir			 = flash_cache_get_cache_root();
		$cache_path			 = $cache_dir . flash_cache_get_server_name() . $post_path;
		if (!file_exists($cache_path)) {
			return false;
		}
		$delete_url		 = wp_nonce_url(admin_url('admin-post.php?id=' . absint($post->ID) . '&action=flash_cache_delete_cache&referer=' . rawurlencode(base64_encode(sanitize_url(wp_unslash($_SERVER['REQUEST_URI']))))), 'delete_cache_nonce', '_nonce');
		$delete_button	 = '<a  id="reset_button" class="button button-large" href="' . esc_attr($delete_url) . '" style="margin:5px;">' . __('Clear Entry Cache', 'flash-cache') . '</a>';
		echo '<div class="misc-pub-section sec_flash_cache_delete_cache_button" style="text-align:center;">
			' . $delete_button . '
		</div>';
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
		$current_query				 = array('is_front_page' => 1);
		$current_query['page' === get_option('show_on_front') ? 'is_page' : 'is_home'] = 1;
		$current_query				 = wp_parse_args($current_query, $default_query);

		flash_cache_process::$force_permalink	 = true;
		flash_cache_process::$force_process_type = 'curl';
		flash_cache_process::process_cache_from_query($current_query, home_url('/'));
	}

	public static function update_taxonomies($post_id, $ttl = 0, $default_posts_per_page = 10, $cache_dir = '', $update_current_page = false) {

		if (is_null(flash_cache_process::$origin_url)) {
			flash_cache_process::$origin_url = get_site_url(null, '/');
		}
		if (empty($cache_dir)) {
			$advanced_settings	 = flash_cache_get_advanced_settings();
			$cache_dir			 = flash_cache_get_cache_root();
		}


		//flash_cache_delete_dir($cache_dir);

		if ($post_id == 0) {
			//$categories = get_categories( array( 'hide_empty'   => 0 ) );

			$url_def	 = array();
			$url_def_tag = array();
			$url_feed	 = home_url('/feed/');
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
						$path = flash_cache_url_to_path($page_term_url) . '/';
						if (strpos($page_term_url, '?') !== false) {
							continue;
						}
						$cache_path	 = $cache_dir . $server_name . '/' . $path;
						$cache_file	 = $cache_path . 'index-cache.html';
						if (flash_cache_is_fresh($cache_file, $ttl)) {
							continue;
						}

						flash_cache_process::$force_process_type = 'curl';
						flash_cache_process::process_cache_from_query(flash_cache_patterns::term_query($tax), $page_term_url);
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