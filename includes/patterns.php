<?php

/**
 * @package         etruel\Flash Cache
 * @subpackage 	   Patterns
 * @author          Sebastian Robles
 * @author          Esteban Truelsegaard
 * @copyright       Copyright (c) 2017
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

class flash_cache_patterns {
	

	/**
	 * Static function hooks
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function hooks() {
		add_action('init', array(__CLASS__, 'setup'), 1);
		add_action('admin_menu', array(__CLASS__, 'admin_menu'));
		add_filter('parent_file', array(__CLASS__, 'menu_correction'));
		add_filter('submenu_file', array(__CLASS__, 'submenu_correction'), 999);
		add_action('transition_post_status', array(__CLASS__, 'default_fields'), 10, 3);
		add_filter('flash_cache_patterns_fields_clean', array(__CLASS__, 'clean_fields'), 100, 1);
		add_action('save_post', array(__CLASS__, 'save'), 99, 2);
		add_action('restrict_manage_posts', array(__CLASS__, 'add_reset_button'));
		add_action('admin_print_scripts-edit.php', array(__CLASS__, 'patterns_edit_scripts'));
		add_action('admin_print_scripts-post.php', array(__CLASS__, 'patterns_edit_scripts'));
		add_action('admin_print_scripts-post-new.php', array(__CLASS__, 'patterns_edit_scripts'));
		add_action('admin_post_reset_to_default_patterns_options', array(__CLASS__, 'reset_to_default_patterns_options'));

		add_action('save_flash_cache_patterns', array(__CLASS__, 'flush'));
		foreach (array('added_post_meta', 'updated_post_meta', 'deleted_post_meta') as $meta_hook) {
			add_action($meta_hook, array(__CLASS__, 'flush_on_meta'), 10, 2);
		}
		foreach (array('deleted_post', 'trashed_post', 'untrashed_post') as $post_hook) {
			add_action($post_hook, array(__CLASS__, 'flush_on_post'));
		}
		add_action('transition_post_status', array(__CLASS__, 'flush_on_status'), 20, 3);
	}

	/** Published patterns with their data, keyed by ID in ID order. */
	private static $all = null;

	/**
	 * Every published pattern with its data, read from one autoloaded option that is rebuilt
	 * whenever a pattern or its meta changes, so front-end requests run no pattern queries.
	 */
	public static function get_all() {
		if (null !== self::$all) {
			return self::$all;
		}
		$stored = get_option('flash_cache_patterns_data', false);
		if (is_array($stored)) {
			return self::$all = $stored;
		}
		self::$all = array();
		$patterns  = get_posts(array('post_type' => 'flash_cache_patterns', 'orderby' => 'ID', 'order' => 'ASC', 'numberposts' => -1));
		foreach ($patterns as $pattern) {
			self::$all[$pattern->ID] = self::get_data($pattern->ID);
		}
		update_option('flash_cache_patterns_data', self::$all, true);
		return self::$all;
	}

	public static function flush() {
		self::$all = null;
		delete_option('flash_cache_patterns_data');
	}

	public static function flush_on_meta($meta_ids, $object_id) {
		if (get_post_type($object_id) === 'flash_cache_patterns') {
			self::flush();
		}
	}

	public static function flush_on_post($post_id) {
		if (get_post_type($post_id) === 'flash_cache_patterns') {
			self::flush();
		}
	}

	public static function flush_on_status($new_status, $old_status, $post) {
		if ($post->post_type === 'flash_cache_patterns' && $new_status !== $old_status) {
			self::flush();
		}
	}
	
	/**
	 * Static function setup
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function setup() {
		$slug			 = 'flash_cache_patterns';
		$labels			 = array(
			'name'				 => __('Patterns', 'flash-cache'),
			'singular_name'		 => __('Pattern', 'flash-cache'),
			'add_new'			 => __('Add New', 'flash-cache'),
			'add_new_item'		 => __('Add New Pattern', 'flash-cache'),
			'edit_item'			 => __('Edit Pattern', 'flash-cache'),
			'new_item'			 => __('New Pattern', 'flash-cache'),
			'view_item'			 => __('View Pattern', 'flash-cache'),
			'search_items'		 => __('Search Patterns', 'flash-cache'),
			'not_found'			 => __('No Patterns found', 'flash-cache'),
			'not_found_in_trash' => __('No Patterns found in Trash', 'flash-cache'),
			'parent_item_colon'	 => __('Parent Pattern:', 'flash-cache'),
			'menu_name'			 => __('Patterns', 'flash-cache'),
		);
		$capabilities	 = array(
			'publish_post'			 => 'publish_flash_cache_pattern',
			'publish_posts'			 => 'publish_flash_cache_patterns',
			'read_post'				 => 'read_flash_cache_pattern',
			'read_private_posts'	 => 'read_private_flash_cache_patterns',
			'edit_post'				 => 'edit_flash_cache_pattern',
			'edit_published_posts'	 => 'edit_published_flash_cache_patterns',
			'edit_private_posts'	 => 'edit_private_flash_cache_patterns',
			'edit_posts'			 => 'edit_flash_cache_patterns',
			'edit_others_posts'		 => 'edit_others_flash_cache_patterns',
			'delete_post'			 => 'delete_flash_cache_pattern',
			'delete_posts'			 => 'delete_flash_cache_patterns',
			'delete_published_posts' => 'delete_published_flash_cache_patterns',
			'delete_private_posts'	 => 'delete_private_flash_cache_patterns',
			'delete_others_posts'	 => 'delete_others_flash_cache_patterns',
		);

		$args = array(
			'labels'				 => $labels,
			'hierarchical'			 => false,
			'description'			 => 'Flash Cache Patterns',
			'supports'				 => array('title', /* 'custom-fields' */),
			'register_meta_box_cb'	 => array(__CLASS__, 'meta_boxes'),
			'public'				 => false,
			'show_ui'				 => true,
			'show_in_menu'			 => false,
			'menu_position'			 => 27,
			'menu_icon'				 => 'dashicons-images-alt2',
			'show_in_nav_menus'		 => false,
			'publicly_queryable'	 => false,
			'exclude_from_search'	 => false,
			'has_archive'			 => false,
			'query_var'				 => true,
			'can_export'			 => true,
			'rewrite'				 => true,
		);

		register_post_type($slug, $args);
	}

	/**
	 * Static function patterns_edit_scripts
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function patterns_edit_scripts() {
		global $post;
		if (!isset($post->post_type) or $post->post_type != 'flash_cache_patterns')
			return isset($post->ID) ? $post->ID : false;
		wp_dequeue_script('autosave');
		wp_enqueue_script('flash_cache-settings', FLASH_CACHE_PLUGIN_URL . 'assets/js/settings.js', array('jquery'), FLASH_CACHE_VERSION, true);
		wp_localize_script('flash_cache-settings', 'flash_cache_settings_i18n', array(
			'clear_cache'	 => __('Changing this option clears the whole cache. Continue?', 'flash-cache'),
			'reset_defaults' => __('Restore the default settings?', 'flash-cache'),
		));
	}

	/**
	 * Static function admin_menu
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function admin_menu() {

		$page = add_submenu_page(
				'flash_cache_setting',
				__('Patterns', 'flash-cache'),
				__('Patterns', 'flash-cache'),
				'manage_options',	
				'edit.php?post_type=flash_cache_patterns'
		);
//			add_action('admin_print_styles-' . $page, array(__CLASS__, 'patterns_edit_scripts'));

		$page = add_submenu_page(
				'flash_cache_setting',
				__('Add New Patterns', 'flash-cache'),
				__('Add New Patterns', 'flash-cache'),
				'manage_options',
				'post-new.php?post_type=flash_cache_patterns'
		);
//			add_action('admin_print_styles-' . $page, array(__CLASS__, 'patterns_edit_scripts'));
	}

	/**
	 * Static function menu_correction
	 * @access public
	 * @return $parent_file String with URL of parent Menu.
	 * @since 1.0.0
	 */
	public static function menu_correction($parent_file) {
		global $current_screen;
		if ($current_screen->id == 'edit-flash_cache_patterns' || $current_screen->id == 'flash_cache_patterns') {
			$parent_file = 'flash_cache_setting';
			//$parent_file = 'admin.php?page=flash_cache_setting';
		}
		return $parent_file;
	}

	/**
	 * Static function submenu_correction
	 * @access public
	 * @return $submenu_file String with URL of Submenu.
	 * @since 1.0.0
	 */
	public static function submenu_correction($submenu_file) {
		global $current_screen;
		if ($current_screen->id == 'edit-flash_cache_patterns' || $current_screen->id == 'flash_cache_patterns') {
			$submenu_file = 'edit.php?post_type=flash_cache_patterns';
		}
		return $submenu_file;
	}

	/**
	 * Static function meta_boxes
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function meta_boxes() {
		add_meta_box('flash_cache-pattern-data', __('Pattern Data', 'flash-cache'), array(__CLASS__, 'metabox_data'), 'flash_cache_patterns', 'normal', 'default');
	}

	/**
	 * Static function metabox_data
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function metabox_data() {
		global $post;
		$values	 = self::get_data($post->ID);
		$args	 = array(
			'public'	 => true,
			'_builtin'	 => false
		);

		echo '<div class="wrap wpm_container"><table class="form-table">
					<tr valign="top">
						<th scope="row">' .
		__('Page types', 'flash-cache') .
		'</th>
						<td>
						<p class="description">' .
		__('The kinds of pages this pattern caches. A page is cached by the first pattern that matches it.', 'flash-cache') .
		'<div class="checkbox-group">
								<input type="checkbox" value="1" name="page_type[single]" ' . checked(1, $values['page_type']['single'], false) . ' />' . __('Single posts and pages', 'flash-cache') . '
							</div>';

		$args		 = array('public' => true);
		$output		 = 'names'; // names or objects
		$post_types	 = get_post_types($args, $output);
		echo '<div style="margin-left:20px">';

		foreach ($post_types as $post_type_name) {
			$cpt_data = get_post_type_object($post_type_name);
			if (!isset($values['page_type']['posts'][$post_type_name])) {
				$values['page_type']['posts'][$post_type_name] = false;
			}
			echo '<div class="checkbox-group"><input type="checkbox" value="1" name="page_type[posts][' . esc_attr( $post_type_name ) . ']" ' . checked(1, $values['page_type']['posts'][$post_type_name], false) . ' />'
			. esc_html( $cpt_data->label ) .
			'</div>';
		}

		echo '<p class="description">' .
		__('With no post type selected, every post type is included.', 'flash-cache') . '</p><br/>' .
		'</p>
				<div class="checkbox-group"><input type="checkbox" value="1" name="create_cache_on_insert_update" ' . checked(1, $values['create_cache_on_insert_update'], false) . ' />' . __('Cache the post when it is published or updated', 'flash-cache') . '
				</div>
				<div class="checkbox-group"><input type="checkbox" value="1" name="update_home_on_insert_post" ' . checked(1, $values['update_home_on_insert_post'], false) . ' />' . __('Refresh the home page when a post is published or updated', 'flash-cache') . '
				</div>
				<div class="checkbox-group"><input type="checkbox" value="1" name="update_categories_on_post" ' . checked(1, $values['update_categories_on_post'], false) . ' />' . __('Refresh the first page of its categories and tags', 'flash-cache') . '
				</div>
				<div class="checkbox-group"><input type="checkbox" value="1" name="update_on_comment" ' . checked(1, $values['update_on_comment'], false) . ' />' . __('Refresh the post when a comment is approved', 'flash-cache') . '
				</div>
			</div>';
		echo '
				<br/>
				<div class="checkbox-group">
					<input type="checkbox" value="1" name="page_type[frontpage]" ' . checked(1, $values['page_type']['frontpage'], false) . ' />' . __('Front Page (is_front_page)', 'flash-cache') . '
				</div>
				<div style="margin-left:20px">
					<div class="checkbox-group">
						<input type="checkbox" value="1" name="page_type[home]" ' . checked(1, $values['page_type']['home'], false) . ' />
					' . __('Home (is_home)', 'flash-cache') . '
					</div>
				</div>
				<br/>
				<div class="checkbox-group">
					<input type="checkbox" value="1" name="page_type[archives]" ' . checked(1, $values['page_type']['archives'], false) . ' />' . __('Archives (is_archive)', 'flash-cache') . '
				</div>
				<div style="margin-left:20px">
					<div class="checkbox-group">
						<input type="checkbox" value="1" name="page_type[tag]" ' . checked(1, $values['page_type']['tag'], false) . ' />' . __('Tags (is_tag)', 'flash-cache') . '
					</div>
					<div class="checkbox-group">
						<input type="checkbox" value="1" name="page_type[category]"' . checked(1, $values['page_type']['category'], false) . ' />' . __('Category (is_category)', 'flash-cache') . '
					</div>
				</div>
				<br/>
				<div class="checkbox-group">
					<input type="checkbox" value="1" name="page_type[feed]" ' . checked(1, $values['page_type']['feed'], false) . ' />' . __('Feeds (is_feed)', 'flash-cache') . '
				</div>
				<div class="checkbox-group">
					<input type="checkbox" value="1" name="page_type[search]" ' . checked(1, $values['page_type']['search'], false) . ' />' . __('Search Pages (is_search)', 'flash-cache') . '
				</div>
				<div class="checkbox-group">
					<input type="checkbox" value="1" name="page_type[author]" ' . checked(1, $values['page_type']['author'], false) . ' />' . __('Author Pages (is_author)', 'flash-cache') . '
				</div>
			</td>
		</tr>

	</table>';

		echo '<table class="form-table">
					<tr valign="top">
						<th scope="row">' . __('URL must contain', 'flash-cache') . '</th>
						<td>
							<textarea style="min-height: 100px;" id="url_must_contain" name="url_must_contain">' . esc_textarea($values['url_must_contain']) . '</textarea>
							<p class="description">' . __('Only pages whose URL contains every one of these texts use this pattern.', 'flash-cache') . '</p>
							<p class="description">' . __('One text per line.', 'flash-cache') . '</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">' . __('URL should not contain', 'flash-cache') . '</th>
						<td>
							<textarea style="min-height: 100px;" id="url_not_contain" name="url_not_contain">' . esc_textarea($values['url_not_contain']) . '</textarea>
							<p class="description">' . __('Pages whose URL contains any of these texts skip this pattern, for example “login”.', 'flash-cache') . '</p>
							<p class="description">' . __('One text per line.', 'flash-cache') . '</p>
						</td>
					</tr>
					
				</table>';
				$advanced_settings = flash_cache_get_advanced_settings();
				$values['cache_type'] = ($advanced_settings['disable_widget_cache'] == 1) ? 'php' : $values['cache_type'];
				echo '<table class="form-table">
					<tr valign="top">
						<th scope="row">' . __('Cache type', 'flash-cache') . '</th>
						<td>
							<div class="radio-group">
								<input type="radio" ' . 
									checked($values['cache_type'], 'html', false) . 
									($advanced_settings['disable_widget_cache'] == 1 ? ' disabled' : '') . 
									' name="cache_type" value="html"/>' . 
									__('Static HTML (fastest)', 'flash-cache') . '
								<p class="description">' . __('The web server delivers the stored page without running PHP. Pages with a query string are not served from this cache.', 'flash-cache') . '</p>
							</div>
				
							<div class="radio-group">
								<input type="radio" ' . 
									checked($values['cache_type'], 'php', false) . 
									' name="cache_type" value="php"/>' . 
									__('PHP (pages with query strings)', 'flash-cache') . '
								<p class="description">' . __('A small PHP file keeps one copy per query string, which suits searches and feeds. A bit slower than static HTML; form submissions always reach WordPress.', 'flash-cache') . '</p>
							</div>
						</td>
					</tr>
				</table>';
				
		echo '<table class="form-table">
					
					<tr valign="top">
						<th scope="row">' . __('Refresh after', 'flash-cache') . '</th>
						<td>
							<input type="text" name="ttl_minimum" id="ttl_minimum" value="' . esc_attr(absint($values['ttl_minimum'])) . '"/>
							<p class="description">' . __('Seconds a cached page is served before a visit asks for a fresh copy. 86400 is one day.', 'flash-cache') . '</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">' . __('Preload refresh after', 'flash-cache') . '</th>
						<td>
							<input type="text" name="ttl_maximum" id="ttl_maximum" value="' . esc_attr(absint($values['ttl_maximum'])) . '"/>
							<p class="description">' . __('Seconds before the preload rebuilds a cached page. With “Refresh expired pages: from the preload only”, the preload uses “Refresh after” instead.', 'flash-cache') . '</p>
						</td>
					</tr>
					
				</table></div>';
	}


	/**
	 * Static function default_fields_array
	 * @access public
	 * @return Array with all default values.
	 * @since 1.0.0
	 */
	public static function default_fields_array() {
		$array	 = array(
			'ttl_minimum'					 => '86400',
			'ttl_maximum'					 => '31536000',
			'page_type'						 => array(
				'single'	 => 1,
				'posts'		 => array(),
				'frontpage'	 => 1,
				'home'		 => 1,
				'archives'	 => 1,
				'tag'		 => 1,
				'category'	 => 1,
				'feed'		 => 1,
				'search'	 => 1,
				'author'	 => 1,
			),
			'update_on_comment'				 => 1,
			'create_cache_on_insert_update'	 => 1,
			'update_home_on_insert_post'	 => 1,
			'update_categories_on_post'		 => 1,
			'update_tax_on_comment'			 => 1,
			'cache_type'					 => 'html',
			'url_must_contain'				 => '',
			'url_not_contain'				 => '',
		);
		
		$array	 = apply_filters('flash_cache_pattern_default_fields', $array);
		return $array;
	}

	/**
	 * Static function default_fields
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function default_fields($new_status, $old_status, $post) {

		if ($post->post_type == 'flash_cache_patterns' && $old_status == 'new') {
			$fields	 = wp_parse_args(array(), self::default_fields_array());
			$fields	 = apply_filters('flash_cache_patterns_fields_clean', $fields);
			foreach ($fields as $field => $value) {
				if (!is_null($value)) {
					$new = apply_filters('flash_cache_patterns_metabox_save_' . $field, $value);
					update_post_meta($post->ID, $field, $new);
				}
			}
		}
	}

	/**
	 * Static function clean_fields
	 * @access public
	 * @return $fields Array with all fields cleaned.
	 * @since 1.0.0
	 */
	public static function clean_fields($fields = array()) {

		if (!is_array($fields) || empty($fields)) {
			$fields = self::default_fields_array();
		}

		if (empty($fields['create_cache_on_insert_update'])) {
			$fields['create_cache_on_insert_update'] = 0;
		} else {
			$fields['create_cache_on_insert_update'] = 1;
		}
		if (empty($fields['update_home_on_insert_post'])) {
			$fields['update_home_on_insert_post'] = 0;
		} else {
			$fields['update_home_on_insert_post'] = 1;
		}
		if (empty($fields['update_categories_on_post'])) {
			$fields['update_categories_on_post'] = 0;
		} else {
			$fields['update_categories_on_post'] = 1;
		}

		if (empty($fields['update_on_comment'])) {
			$fields['update_on_comment'] = 0;
		} else {
			$fields['update_on_comment'] = 1;
		}

		if (empty($fields['page_type'])) {
			$fields['page_type'] = array();
		}

		if (empty($fields['page_type']['single'])) {
			$fields['page_type']['single'] = 0;
		} else {
			$fields['page_type']['single'] = 1;
		}
		if (empty($fields['page_type']['tag'])) {
			$fields['page_type']['tag'] = 0;
		} else {
			$fields['page_type']['tag'] = 1;
		}
		if (empty($fields['page_type']['category'])) {
			$fields['page_type']['category'] = 0;
		} else {
			$fields['page_type']['category'] = 1;
		}
		if (empty($fields['page_type']['feed'])) {
			$fields['page_type']['feed'] = 0;
		} else {
			$fields['page_type']['feed'] = 1;
		}
		if (empty($fields['page_type']['search'])) {
			$fields['page_type']['search'] = 0;
		} else {
			$fields['page_type']['search'] = 1;
		}
		if (empty($fields['page_type']['author'])) {
			$fields['page_type']['author'] = 0;
		} else {
			$fields['page_type']['author'] = 1;
		}
		if (empty($fields['page_type']['home'])) {
			$fields['page_type']['home'] = 0;
		} else {
			$fields['page_type']['home'] = 1;
		}
		if (empty($fields['page_type']['frontpage'])) {
			$fields['page_type']['frontpage'] = 0;
		} else {
			$fields['page_type']['frontpage'] = 1;
		}
		if (empty($fields['page_type']['archives'])) {
			$fields['page_type']['archives'] = 0;
		} else {
			$fields['page_type']['archives'] = 1;
		}
		return $fields;
	}

	/**
	 * Static function get_data
	 * @access public
	 * @param $pattern_id Int Identifier of a Pattern
	 * @return $fields Array with all fields of a Pattern
	 * @since 1.0.0
	 */
	public static function get_data($patern_id) {
		$custom_field_keys	 = get_post_custom($patern_id);
		$fields				 = array();

		foreach ($custom_field_keys as $key => $value) {
			$fields[$key] = maybe_unserialize($value[0]);
		}

		$fields = apply_filters('flash_cache_patterns_fields_clean', $fields);

		return $fields;
	}

	/**
	 * Static function save
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public static function save($post_id, $post) {
		global $wpdb;
		if (( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || ( defined('DOING_AJAX') && DOING_AJAX ) || isset($_REQUEST['bulk_edit'])) {
			return false;
		}

		if (isset($post->post_type) && $post->post_type == 'revision' || $post->post_type != 'flash_cache_patterns') {
			return false;
		}

		if (!current_user_can('manage_options', $post_id)) {
			return false;
		}
		if (( defined('FLASH_CACHE_STOP_PROPAGATION') && FLASH_CACHE_STOP_PROPAGATION)) {
			return false;
		}

		$fields	 = apply_filters('flash_cache_patterns_fields_clean', self::fields_from_request(wp_unslash($_POST)));
		$fields	 = apply_filters('flash_cache_patterns_save', $fields);

		foreach ($fields as $field => $value) {
			if (!is_null($value)) {
				$new = apply_filters('flash_cache_pattern_save_' . $field, $value);
				update_post_meta($post_id, $field, $new);
			}
		}
		do_action('save_flash_cache_patterns', $post_id, $post);
	}

	/**
	 * Only the pattern fields, each cast to its type. A field the form did not send keeps
	 * the shape clean_fields() expects (an unchecked box arrives as 0).
	 */
	public static function fields_from_request($request) {
		$fields = array();
		foreach (self::default_fields_array() as $field => $default) {
			$value = $request[$field] ?? null;
			switch ($field) {
				case 'ttl_minimum':
				case 'ttl_maximum':
					$fields[$field] = absint($value ?? $default);
					break;
				case 'cache_type':
					$fields[$field] = in_array($value, array('html', 'php'), true) ? $value : 'html';
					break;
				case 'url_must_contain':
				case 'url_not_contain':
					$fields[$field] = sanitize_textarea_field((string) $value);
					break;
				case 'page_type':
					$page_type = array();
					foreach ($default as $type => $type_default) {
						if ($type === 'posts') {
							$page_type['posts'] = array();
							foreach ((array) ($value['posts'] ?? array()) as $post_type => $on) {
								if (!empty($on) && post_type_exists($post_type)) {
									$page_type['posts'][sanitize_key($post_type)] = 1;
								}
							}
						} else {
							$page_type[$type] = empty($value[$type]) ? 0 : 1;
						}
					}
					$fields[$field] = $page_type;
					break;
				default:
					$fields[$field] = is_array($default) ? array_map('sanitize_text_field', (array) $value) : (empty($value) ? 0 : (is_numeric($default) ? 1 : sanitize_text_field((string) $value)));
			}
		}
		return $fields;
	}

	public static function reset_to_default_patterns_options() {
		flash_cache_verify_admin_request('reset_to_default_patterns_options');
		flash_cache_version::delete_all_patterns();
		flash_cache_version::install_patterns_default();
		flash_cache_notices::add(__('Defaults have been restored.', 'flash-cache'));
		wp_safe_redirect(admin_url('edit.php?post_type=flash_cache_patterns'));
		exit;
	}

	public static function add_reset_button() {
		global $post_type;
	
		// Check if the current post type is your custom post type
		if ($post_type == 'flash_cache_patterns') {
			?>
				<a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=reset_to_default_patterns_options'), 'reset_to_default_patterns_options', '_wpnonce') ?>" class="button btn_reset_to_default reset-data-button" style="margin-left: 10px;"> <?php echo __('Reset to default', 'flash-cache') ?> </a>
			<?php
		}
	}
}

flash_cache_patterns::hooks();