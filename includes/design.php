<?php

/**
 * Keeps cached pages in step with the site design: saving widgets, menus, Customizer settings or
 * block theme templates applies the "After design changes" setting once per request, and
 * switching themes always empties the cache.
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

class flash_cache_design {

	/** Published posts above which "Automatic" refreshes gradually instead of deleting. */
	const AUTOMATIC_THRESHOLD = 2000;

	private static $pending = null;

	/** What changed in this request: 'option:<name>', 'menu', 'customizer', 'post:<type>' or 'theme'. */
	private static $changes = array();

	public static function hooks() {
		add_action('updated_option', array(__CLASS__, 'option_changed'), 10, 1);
		add_action('added_option', array(__CLASS__, 'option_changed'), 10, 1);
		add_action('wp_update_nav_menu', array(__CLASS__, 'changed'), 10, 0);
		add_action('wp_delete_nav_menu', array(__CLASS__, 'changed'), 10, 0);
		add_action('customize_save_after', array(__CLASS__, 'changed'), 10, 0);
		add_action('save_post', array(__CLASS__, 'post_saved'), 10, 2);
		add_action('deleted_post', array(__CLASS__, 'post_saved'), 10, 2);
		add_action('switch_theme', array(__CLASS__, 'theme_switched'), 10, 0);
		add_action('flash_cache_expire_stale', array(__CLASS__, 'expire_stale'), 10, 0);
	}

	/**
	 * Widget, sidebar and theme options change on many requests besides a design edit, so only
	 * changes made by a user who can edit the design count.
	 */
	public static function option_changed($option) {
		if (($option === 'sidebars_widgets' || str_starts_with($option, 'widget_') || str_starts_with($option, 'theme_mods_')) && function_exists('wp_get_current_user') && current_user_can('edit_theme_options')) {
			self::queue('design', 'option:' . $option);
		}
	}

	public static function post_saved($post_id, $post = null) {
		if ($post instanceof WP_Post && in_array($post->post_type, array('wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation'), true)) {
			self::queue('design', 'post:' . $post->post_type);
		}
	}

	public static function changed() {
		self::queue('design', 'customize_save_after' === current_action() ? 'customizer' : 'menu');
	}

	public static function theme_switched() {
		self::queue('theme', 'theme');
	}

	private static function queue($kind, $change) {
		self::$changes[] = $change;
		if (null === self::$pending) {
			add_action('shutdown', array(__CLASS__, 'apply'), 0);
		}
		if (self::$pending !== 'theme') {
			self::$pending = $kind;
		}
	}

	public static function apply() {
		$theme		   = 'theme' === self::$pending;
		$mode		   = $theme ? 'delete' : self::mode();
		$changes	   = array_values(array_unique(self::$changes));
		self::$pending = false;
		self::$changes = array();
		/**
		 * Lets an extension update the cached pages itself, for example by replacing a changed
		 * widget inside them. Return true when it took care of every change listed.
		 */
		if (!$theme && 'none' !== $mode && apply_filters('flash_cache_design_change_handled', false, $changes, $mode)) {
			return;
		}
		if ('delete' === $mode) {
			flash_cache_purge_all();
		} elseif ('gradual' === $mode) {
			update_option('flash_cache_stale_before', time(), false);
			if (!wp_next_scheduled('flash_cache_expire_stale')) {
				wp_schedule_single_event(time(), 'flash_cache_expire_stale');
			}
		}
	}

	/**
	 * The effective action: 'delete', 'gradual' or 'none'.
	 */
	public static function mode() {
		$mode = flash_cache_get_advanced_settings()['design_changes'] ?? 'auto';
		if ('auto' === $mode) {
			return self::published_posts() > (int) apply_filters('flash_cache_gradual_refresh_threshold', self::AUTOMATIC_THRESHOLD) ? 'gradual' : 'delete';
		}
		return in_array($mode, array('delete', 'gradual', 'none'), true) ? $mode : 'delete';
	}

	public static function published_posts() {
		global $wpdb;
		$types = array_values(get_post_types(array('public' => true)));
		if (!$types) {
			return 0;
		}
		return (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN (" . implode(',', array_fill(0, count($types), '%s')) . ')',
				$types
		));
	}

	/**
	 * Gradual refresh: deletes a batch of pages stored before the design change per run, so they
	 * are cached again on their next visit or by the preload, and runs again a minute later until
	 * none is left. Combined styles and scripts stay, since the remaining pages still use them.
	 */
	public static function expire_stale() {
		$stale_before = (int) get_option('flash_cache_stale_before', 0);
		$root		  = flash_cache_get_cache_root();
		if (!$stale_before || !is_dir($root)) {
			delete_option('flash_cache_stale_before');
			return;
		}
		$batch	  = max(1, (int) apply_filters('flash_cache_gradual_refresh_batch', 200));
		$deadline = microtime(true) + 20;
		$deleted  = 0;
		$freed	  = 0;
		$hosts	  = new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
				function ($item, $key, $iterator) use ($root) {
					return !($item->isLink() || ($item->isDir() && in_array($item->getFilename(), array('.trash', 'locks', 'assets'), true) && trailingslashit(wp_normalize_path($item->getPath())) === trailingslashit(wp_normalize_path($root))));
				}
		);
		foreach (new RecursiveIteratorIterator($hosts) as $file) {
			$name = $file->getFilename();
			if ($name === 'index-cache.html') {
				$related = array($file->getPathname() . '.gz');
			} elseif (str_ends_with($name, '.html') && basename($file->getPath()) === 'requests') {
				$related = array(substr($file->getPathname(), 0, -5) . '.header', substr($file->getPathname(), 0, -5) . '.php');
			} else {
				continue;
			}
			if ($file->getMTime() >= $stale_before) {
				continue;
			}
			foreach (array_merge(array($file->getPathname()), $related) as $path) {
				if (is_file($path)) {
					$freed += (int) filesize($path);
					@unlink($path);
				}
			}
			if (++$deleted >= $batch || microtime(true) > $deadline) {
				flash_cache_decrement_disk_usage($freed);
				wp_schedule_single_event(time() + 60, 'flash_cache_expire_stale');
				return;
			}
		}
		flash_cache_decrement_disk_usage($freed);
		delete_option('flash_cache_stale_before');
	}
}

flash_cache_design::hooks();
