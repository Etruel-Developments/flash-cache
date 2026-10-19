<?php

/**
 * @package         etruel\Flash Cache
 * @subpackage 	    About
 * @author          Esteban Truelsegaard
 */
// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

/**
 * The About screen: what Flash Cache does, how fast it is, where it runs and Free vs Pro.
 * Last item of the plugin's submenu.
 */
class flash_cache_about {

	const SLUG = 'flash_cache_about';

	public static function hooks() {
		add_action('admin_menu', array(__CLASS__, 'admin_menu'), 20);
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
		add_action('admin_head', array(__CLASS__, 'menu_style'));
		add_filter('flash_cache_add_menus', array(__CLASS__, 'add_menu_link'), 20, 2);
		add_filter('flash_cache_settings_icons', array(__CLASS__, 'add_icon'));
	}

	public static function admin_menu() {
		add_submenu_page(
				'flash_cache_setting',
				__('About', 'flash-cache'),
				'<span class="flash-cache-about-menu">' . __('About', 'flash-cache') . '</span>',
				'manage_options',
				self::SLUG,
				array(__CLASS__, 'page')
		);
	}

	public static function menu_style() {
		echo '<style>#adminmenu .flash-cache-about-menu{color:#00e065;font-weight:600}</style>';
	}

	public static function add_icon($icons) {
		$icons['icon_about'] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 8C119 8 8 119.1 8 256s111 248 248 248 248-111 248-248S393 8 256 8zm0 110c23.2 0 42 18.8 42 42s-18.8 42-42 42-42-18.8-42-42 18.8-42 42-42zm56 254c0 6.6-5.4 12-12 12h-88c-6.6 0-12-5.4-12-12v-24c0-6.6 5.4-12 12-12h12v-64h-12c-6.6 0-12-5.4-12-12v-24c0-6.6 5.4-12 12-12h64c6.6 0 12 5.4 12 12v100h12c6.6 0 12 5.4 12 12v24z"/></svg>';
		return $icons;
	}

	/**
	 * Side menu of the screens: About goes last, after anything Pro adds.
	 */
	public static function add_menu_link($extra_menus, $icons) {
		global $current_screen;
		$active = ( $current_screen && $current_screen->id == 'flash-cache_page_' . self::SLUG ) ? ' active' : '';
		return $extra_menus . '<a class="wpm_menu_link' . $active . '" href="admin.php?page=' . self::SLUG . '"><span class="wpm_link_icon">' . $icons['icon_about'] . '</span> <span class="wpm_link_text">' . __('About', 'flash-cache') . '</span></a>';
	}

	public static function enqueue() {
		global $current_screen;
		if (!$current_screen || $current_screen->id !== 'flash-cache_page_' . self::SLUG) {
			return;
		}
		$css = FLASH_CACHE_PLUGIN_DIR . 'assets/css/about.css';
		$js	 = FLASH_CACHE_PLUGIN_DIR . 'assets/js/about.js';
		wp_enqueue_style('flash_cache-about', FLASH_CACHE_PLUGIN_URL . 'assets/css/about.css', array(), FLASH_CACHE_VERSION . '.' . filemtime($css));
		wp_enqueue_script('flash_cache-about', FLASH_CACHE_PLUGIN_URL . 'assets/js/about.js', array(), FLASH_CACHE_VERSION . '.' . filemtime($js), true);
	}

	public static function page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to do this.', 'flash-cache'));
		}
		$img	 = FLASH_CACHE_PLUGIN_URL . 'assets/img/about/';
		$has_pro = defined('FLASH_CACHE_PRO_VERSION');
		$links	 = apply_filters('flash_cache_about_links', array(
			'site'		=> 'https://flashcache.net',
			'review'	=> 'https://wordpress.org/support/plugin/flash-cache/reviews/#new-post',
			'support'	=> 'https://wordpress.org/support/plugin/flash-cache/',
			'translate'	=> 'https://translate.wordpress.org/projects/wp-plugins/flash-cache',
			'checkout'	=> 'https://etruel.com/checkout/?edd_action=add_to_cart&download_id=15114',
		));
		echo '<div class="wrap">';
		?>
<div class="fc-about">

<header class="fc-hero">
 <video class="fc-storm" src="<?php echo esc_url($img . 'storm-small.mp4'); ?>" autoplay loop muted playsinline></video>
 <div class="fc-brand"><img src="<?php echo esc_url(FLASH_CACHE_PLUGIN_URL . 'assets/img/flash-cache-logo.png'); ?>" alt=""><div><h1><b>FLASH</b> CACHE <small><?php echo esc_html(FLASH_CACHE_VERSION); ?></small></h1><span class="fc-tag"><?php esc_html_e('The super accelerator for WordPress', 'flash-cache'); ?></span></div></div>
 <p><?php esc_html_e('Every page of your site becomes a static file that your web server sends straight to the visitor — no PHP, no WordPress, no waiting.', 'flash-cache'); ?></p>
 <div class="fc-actions"><a class="fc-btn solid" href="<?php echo esc_url($links['review']); ?>" target="_blank" rel="noopener"><?php esc_html_e('★ Rate on WordPress.org', 'flash-cache'); ?></a><a class="fc-btn" href="<?php echo esc_url($links['site']); ?>" target="_blank" rel="noopener">flashcache.net</a><a class="fc-btn" href="<?php echo esc_url($links['support']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Support', 'flash-cache'); ?></a></div>
</header>
<nav class="fc-tabs"><button data-tab="overview"><?php esc_html_e('Overview', 'flash-cache'); ?></button><button data-tab="speed"><?php esc_html_e('Performance', 'flash-cache'); ?></button><button data-tab="servers"><?php esc_html_e('Servers', 'flash-cache'); ?></button><button data-tab="pro" class="pro"><?php esc_html_e('Free vs Pro ★', 'flash-cache'); ?></button></nav>

<!-- OVERVIEW -->
<section class="fc-pane" id="fc-overview">
 <h2><?php esc_html_e('Measured, not promised', 'flash-cache'); ?></h2>
 <p class="lead"><?php esc_html_e('Home page on our Apache test site (WordPress 7.1, PHP 7.4), warm cache, compared with the same site without any cache plugin.', 'flash-cache'); ?></p>
 <div class="fc-stats">
  <div class="fc-stat"><strong><?php esc_html_e('0.85 ms', 'flash-cache'); ?></strong><span><?php esc_html_e('to the first byte', 'flash-cache'); ?></span><em><?php esc_html_e('102 ms without cache', 'flash-cache'); ?></em></div>
  <div class="fc-stat"><strong>23,000+</strong><span><?php esc_html_e('requests per second', 'flash-cache'); ?></span><em><?php esc_html_e('18 without cache', 'flash-cache'); ?></em></div>
  <div class="fc-stat"><strong><?php esc_html_e('5.8 KB', 'flash-cache'); ?></strong><span><?php esc_html_e('sent per visit', 'flash-cache'); ?></span><em><?php esc_html_e('43 KB without cache', 'flash-cache'); ?></em></div>
 </div>
 <h2><?php esc_html_e('Everything your cache needs', 'flash-cache'); ?></h2>
 <p class="lead"><?php esc_html_e('Free and complete: Flash Cache does not hold back its core to sell an upgrade.', 'flash-cache'); ?></p>
 <div class="fc-grid">
  <div class="fc-card"><div class="fc-ico"><svg viewBox="0 0 24 24"><path d="M13 2 3 14h9l-1 8 10-12h-9z"/></svg></div><h3><?php esc_html_e('Truly static pages', 'flash-cache'); ?></h3><p><?php esc_html_e('Served by the web server\'s own rules. A fresh page never starts WordPress, not even to check its age.', 'flash-cache'); ?></p></div>
  <div class="fc-card"><div class="fc-ico"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 17h7"/></svg></div><h3><?php esc_html_e('Cache patterns', 'flash-cache'); ?></h3><p><?php esc_html_e('One set of rules for each kind of page, with its own lifetime. Drag them into the order you want.', 'flash-cache'); ?></p></div>
  <div class="fc-card"><div class="fc-ico"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg></div><h3><?php esc_html_e('Background refreshes', 'flash-cache'); ?></h3><p><?php esc_html_e('Visitors never meet an empty cache. One refresh per page, however many arrive when it expires.', 'flash-cache'); ?></p></div>
  <div class="fc-card"><div class="fc-ico"><svg viewBox="0 0 24 24"><path d="m13 17 5-5-5-5"/><path d="m6 17 5-5-5-5"/></svg></div><h3><?php esc_html_e('Preload the site', 'flash-cache'); ?></h3><p><?php esc_html_e('A background crawler warms the cache in small batches you control.', 'flash-cache'); ?></p></div>
  <div class="fc-card"><div class="fc-ico"><svg viewBox="0 0 24 24"><path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/></svg></div><h3><?php esc_html_e('Lighter pages', 'flash-cache'); ?></h3><p><?php esc_html_e('Styles and scripts combined into fewer files, shared by every page, pre-compressed with gzip.', 'flash-cache'); ?></p></div>
  <div class="fc-card"><div class="fc-ico"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></svg></div><h3><?php esc_html_e('Always in the know', 'flash-cache'); ?></h3><p><?php esc_html_e('Live statistics of pages, styles, scripts and fonts, loaded instantly at any cache size.', 'flash-cache'); ?></p></div>
  <div class="fc-card"><div class="fc-ico"><svg viewBox="0 0 24 24"><path d="M12 2 4 6v6c0 5 3.5 8.5 8 10 4.5-1.5 8-5 8-10V6z"/></svg></div><h3><?php esc_html_e('Clean and portable', 'flash-cache'); ?></h3><p><?php esc_html_e('No drop-in files, no wp-config.php changes. Deactivate it and the rules and the cache are gone.', 'flash-cache'); ?></p></div>
  <div class="fc-card"><div class="fc-ico raw"><svg viewBox="0 0 682.66669 682.66669" role="img" aria-label="WPeMatico"><path d="M 330.86559,660.93052 144.63672,343.39515 331.09697,23.456861 517.81393,343.41118 Z" fill="#dddddd" stroke="#000000" stroke-width="22.63259697"/><path d="M 330.40869,577.16826 198.9974,344.47835 330.57141,109.14702 463.02901,344.63641 Z" fill="#ef8e2f" stroke="#ea6208" stroke-width="13.52285862"/><g fill="#ffca79"><path d="M 365.1785,500.80015 C 355.89144,455.13711 335.7767,411.7133 306.95198,375.10048 283.95345,345.88802 255.46624,321.01172 223.4218,302.15823 l -11.78612,30.25163 c 29.91253,19.79655 56.38875,44.77042 77.89699,73.47683 28.76371,38.39007 48.54656,83.46517 57.32936,130.62459 z"/><path d="M 457.27369,351.27065 C 443.57501,306.85789 420.85482,265.24856 390.8988,229.71289 366.78011,201.10183 338.00474,176.42512 306.05098,156.95049 l -15.90283,31.66866 c 30.86051,18.20726 58.83471,41.29531 82.56406,68.14291 30.97747,35.04818 54.65944,76.51539 69.10583,121.00449 z"/><path d="M 414.82276,418.66811 C 401.64725,373.98584 377.87172,332.4672 346.00288,298.48962 320.57719,271.38152 290.056,249.06889 256.51141,233.06655 l -9.14023,31.16104 c 31.50216,17.12724 60.02806,39.71143 83.92531,66.44436 31.96119,35.75374 55.54804,78.94632 68.34965,125.16284 z"/></g><path d="M 330.40869,577.16826 198.9974,344.47835 330.57141,109.14702 463.02901,344.63641 Z" fill="none" stroke="#ea6208" stroke-width="13.52285862"/></svg></div><h3><?php esc_html_e('Made for autoblogs', 'flash-cache'); ?></h3><p><?php esc_html_e('Bulk publishing, like a WPeMatico fetch, refreshes the home page and each archive once per batch.', 'flash-cache'); ?></p><a class="fc-link" href="https://wordpress.org/plugins/wpematico/" target="_blank" rel="noopener"><?php esc_html_e('Let\'s autoblog →', 'flash-cache'); ?></a></div>
  <a class="fc-card pro" href="#pro" data-go="pro"><div class="fc-ico"><svg viewBox="0 0 24 24"><path d="m12 2 3 7 7 .6-5.3 4.6 1.6 7.3L12 17.8 5.7 21.5l1.6-7.3L2 9.6 9 9z"/></svg></div><h3><?php esc_html_e('Go further with Pro', 'flash-cache'); ?></h3><p><?php esc_html_e('Live blocks and widgets, shared widget cache, minify, local fonts and more. Not required, but it keeps the free plugin growing.', 'flash-cache'); ?></p><span class="more"><?php esc_html_e('Compare Free vs Pro →', 'flash-cache'); ?></span></a>
 
 </div>
</section>

<!-- PERFORMANCE -->
<section class="fc-pane" id="fc-speed">
 <h2><?php esc_html_e('Same server, with and without Flash Cache', 'flash-cache'); ?></h2>
 <p class="lead"><?php esc_html_e('Apache 2.4, WordPress 7.1, PHP 7.4 + OPcache, home page, ApacheBench 3,000 requests at 10 concurrent. Bars start at the same baseline.', 'flash-cache'); ?></p>
 <div class="fc-bench">
  <div class="fc-chart"><h3><?php esc_html_e('Time to first byte', 'flash-cache'); ?> <small style="color:#646970"><?php esc_html_e('(lower is better)', 'flash-cache'); ?></small></h3>
   <div class="fc-bar"><span><?php esc_html_e('No cache', 'flash-cache'); ?></span><i data-w="100%"></i><b><?php esc_html_e('102 ms', 'flash-cache'); ?></b></div>
   <div class="fc-bar us"><span><?php esc_html_e('Flash Cache', 'flash-cache'); ?></span><i data-w="2%"></i><b><?php esc_html_e('0.85 ms', 'flash-cache'); ?></b></div>
   <p style="color:#646970;font-size:12px;margin:12px 0 0"><?php esc_html_e('≈120× faster', 'flash-cache'); ?></p></div>
  <div class="fc-chart"><h3><?php esc_html_e('Bytes sent per visit', 'flash-cache'); ?> <small style="color:#646970"><?php esc_html_e('(lower is better)', 'flash-cache'); ?></small></h3>
   <div class="fc-bar"><span><?php esc_html_e('No cache', 'flash-cache'); ?></span><i data-w="100%"></i><b><?php esc_html_e('43.2 KB', 'flash-cache'); ?></b></div>
   <div class="fc-bar us"><span><?php esc_html_e('Flash Cache', 'flash-cache'); ?></span><i data-w="13%"></i><b><?php esc_html_e('5.8 KB', 'flash-cache'); ?></b></div>
   <p style="color:#646970;font-size:12px;margin:12px 0 0"><?php esc_html_e('7× lighter: optimized HTML + precompressed gzip', 'flash-cache'); ?></p></div>
  <div class="fc-chart" style="grid-column:1/-1"><h3><?php esc_html_e('Requests per second against other cache plugins', 'flash-cache'); ?> <small style="color:#646970"><?php esc_html_e('(higher is better)', 'flash-cache'); ?></small></h3>
   <div class="fc-bar us"><span><?php esc_html_e('Flash Cache', 'flash-cache'); ?></span><i data-w="100%"></i><b>23,555</b></div>
   <div class="fc-bar"><span><?php esc_html_e('WP Fastest Cache', 'flash-cache'); ?></span><i data-w="99%"></i><b>23,304</b></div>
   <div class="fc-bar"><span><?php esc_html_e('WP Super Cache (Expert)', 'flash-cache'); ?></span><i data-w="96.5%"></i><b>22,743</b></div>
   <div class="fc-bar"><span><?php esc_html_e('W3 Total Cache', 'flash-cache'); ?></span><i data-w="47.5%"></i><b>11,191</b></div>
   <div class="fc-bar"><span><?php esc_html_e('WP Super Cache (Simple)', 'flash-cache'); ?></span><i data-w="2.4%"></i><b>559</b></div>
   <div class="fc-bar"><span><?php esc_html_e('No cache', 'flash-cache'); ?></span><i data-w="0.5%"></i><b>18</b></div>
  </div>
 </div>
 <div class="fc-note"><b><?php esc_html_e('Honest reading:', 'flash-cache'); ?></b> <?php esc_html_e('against plugins that also serve static files, raw speed is equal. Flash Cache sends 7–9× fewer bytes per visit, lets the browser keep the page for a day, and keeps itself up to date when you publish — all by default.', 'flash-cache'); ?></div>
 <h2 style="margin-top:34px"><?php esc_html_e('Even faster with Pro', 'flash-cache'); ?> <span class="fc-badge">PRO</span></h2>
 <p class="lead"><?php esc_html_e('Optimizations that add performance on top of the free plugin, and fund its development.', 'flash-cache'); ?></p>
 <div class="fc-grid">
  <a class="fc-card pro" href="#pro" data-go="pro"><div class="fc-ico"><svg viewBox="0 0 24 24"><path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/></svg></div><h3><?php esc_html_e('Minify CSS and JavaScript', 'flash-cache'); ?></h3><p><?php esc_html_e('The combined files lose spaces and comments, so every visit carries fewer bytes.', 'flash-cache'); ?></p></a>
  <a class="fc-card pro" href="#pro" data-go="pro"><div class="fc-ico"><svg viewBox="0 0 24 24"><path d="M4 7V5h16v2M9 5v14M15 5v14M7 19h4M13 19h4"/></svg></div><h3><?php esc_html_e('Google Fonts on your server', 'flash-cache'); ?></h3><p><?php esc_html_e('Fewer requests to other sites and no wait for third-party hosts.', 'flash-cache'); ?></p></a>
  <a class="fc-card pro" href="#pro" data-go="pro"><div class="fc-ico"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></div><h3><?php esc_html_e('Shared widget cache', 'flash-cache'); ?></h3><p><?php esc_html_e('Widgets that look the same on every page are built once and shared, so rebuilding pages is cheaper.', 'flash-cache'); ?></p></a>
  <a class="fc-card pro" href="#pro" data-go="pro"><div class="fc-ico"><svg viewBox="0 0 24 24"><path d="M13 2 3 14h9l-1 8 10-12h-9z"/></svg></div><h3><?php esc_html_e('Live blocks without losing the cache', 'flash-cache'); ?></h3><p><?php esc_html_e('Only the marked block or widget renders on each visit, even refreshed every few minutes. The rest stays static.', 'flash-cache'); ?></p></a>
  <a class="fc-card pro" href="#pro" data-go="pro"><div class="fc-ico"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></div><h3><?php esc_html_e('Scripts in place, plugin by plugin', 'flash-cache'); ?></h3><p><?php esc_html_e('Keep a problematic plugin\'s scripts out of the combined file without giving up optimization for the rest.', 'flash-cache'); ?></p></a>
  <a class="fc-card pro" href="#pro" data-go="pro"><div class="fc-ico"><svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></div><h3><?php esc_html_e('Clear or rebuild a single post', 'flash-cache'); ?></h3><p><?php esc_html_e('From the posts list, and automatically when you save.', 'flash-cache'); ?></p></a>
 </div>
</section>

<!-- SERVERS -->
<section class="fc-pane" id="fc-servers">
 <h2><?php esc_html_e('Works where your site runs', 'flash-cache'); ?></h2>
 <p class="lead"><?php esc_html_e('Flash Cache writes the rules for your server and tells you whether it is running them.', 'flash-cache'); ?></p>
 <div class="fc-servers">
  <div class="fc-srv"><h3><?php esc_html_e('Apache', 'flash-cache'); ?></h3><p><?php esc_html_e('Rules written to .htaccess in their own marked blocks. Zero setup.', 'flash-cache'); ?></p><span class="chip"><?php esc_html_e('automatic', 'flash-cache'); ?></span></div>
  <div class="fc-srv"><h3><?php esc_html_e('LiteSpeed', 'flash-cache'); ?></h3><p><?php esc_html_e('Enterprise: same .htaccess rules, same static speed.', 'flash-cache'); ?></p><span class="chip"><?php esc_html_e('automatic', 'flash-cache'); ?></span></div>
  <div class="fc-srv"><h3><?php esc_html_e('OpenLiteSpeed', 'flash-cache'); ?></h3><p><?php esc_html_e('Cached pages straight from disk, compressed by the server. 48,000+ req/s measured.', 'flash-cache'); ?></p><span class="chip"><?php esc_html_e('restart once', 'flash-cache'); ?></span></div>
  <div class="fc-srv"><h3><?php esc_html_e('nginx', 'flash-cache'); ?></h3><p><?php esc_html_e('Copy the ready-made block into your server config and reload.', 'flash-cache'); ?></p><span class="chip"><?php esc_html_e('copy & paste', 'flash-cache'); ?></span></div>
 </div>
 <p class="lead" style="margin-top:24px"><?php esc_html_e('PHP 7.2 or later · WordPress 5.9 or later · single sites · tested with WPeMatico and its add-ons.', 'flash-cache'); ?></p>
</section>

<!-- PRO -->
<section class="fc-pane" id="fc-pro">
 <h2><?php esc_html_e('You don\'t need Pro. But it helps a lot.', 'flash-cache'); ?></h2>
 <p class="lead"><?php esc_html_e('Flash Cache is complete and free. Pro is not required to get the best of it: it adds a few extras, and every licence — priced so low on purpose — pays for support and for new improvements to the free plugin.', 'flash-cache'); ?></p>
 <div class="fc-vs">
  <div class="fc-col free"><div class="fc-colhead"><?php esc_html_e('Flash Cache', 'flash-cache'); ?></div><img class="fc-collogo" src="<?php echo esc_url($img . 'fc-icon.png'); ?>" alt="">
   <ul class="fc-checks">
    <li><?php esc_html_e('Static pages served without PHP', 'flash-cache'); ?></li>
    <li><?php esc_html_e('Cache patterns, lifetimes and PHP mode for query strings', 'flash-cache'); ?></li>
    <li><?php esc_html_e('Background refresh, preload and instant purge', 'flash-cache'); ?></li>
    <li><?php esc_html_e('Combined styles and scripts, gzip', 'flash-cache'); ?></li>
    <li><?php esc_html_e('Apache, LiteSpeed, OpenLiteSpeed and nginx', 'flash-cache'); ?></li>
    <li><?php esc_html_e('Pages with query strings cached, without tracking-parameter duplicates', 'flash-cache'); ?></li>
    <li><?php esc_html_e('Cache refreshed by itself when you publish, comment or change widgets, menus and themes', 'flash-cache'); ?></li>
    <li><?php esc_html_e('Only one refresh per page at a time, so visitors never meet an empty cache', 'flash-cache'); ?></li>
    <li><?php esc_html_e('Browser cache time of your choice', 'flash-cache'); ?></li>
    <li><?php esc_html_e('Live cache statistics and server-rules status', 'flash-cache'); ?></li>
    <li><a href="#overview" data-go="overview"><?php esc_html_e('View all free features', 'flash-cache'); ?></a></li>
   </ul></div>
  <div class="fc-col gopro"><div class="fc-colhead"><?php esc_html_e('Go PRO', 'flash-cache'); ?></div><img class="fc-collogo" src="<?php echo esc_url($img . 'fcp-icon.png'); ?>" alt="">
   <ul class="fc-feats">
    <li><svg viewBox="0 0 24 24"><path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/></svg><div><h4><?php esc_html_e('Minify CSS and JS', 'flash-cache'); ?></h4><p><?php esc_html_e('The combined files lose spaces and comments, so they weigh less.', 'flash-cache'); ?></p></div></li>
    <li><svg viewBox="0 0 24 24"><path d="M4 7V5h16v2M9 5v14M15 5v14M7 19h4M13 19h4"/></svg><div><h4><?php esc_html_e('Google Fonts on your server', 'flash-cache'); ?></h4><p><?php esc_html_e('Pages make fewer requests to other sites.', 'flash-cache'); ?></p></div></li>
    <li><svg viewBox="0 0 24 24"><path d="M13 2 3 14h9l-1 8 10-12h-9z"/></svg><div><h4><?php esc_html_e('Widgets and blocks without cache', 'flash-cache'); ?></h4><p><?php esc_html_e('Render a block or legacy widget live on every visit while the page stays cached.', 'flash-cache'); ?></p></div></li>
    <li><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><div><h4><?php esc_html_e('Shared widget cache', 'flash-cache'); ?></h4><p><?php esc_html_e('Widgets built once and shared by every page; updating them never empties the cache.', 'flash-cache'); ?></p></div></li>
    <li><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg><div><h4><?php esc_html_e('Scripts in place, plugin by plugin', 'flash-cache'); ?></h4><p><?php esc_html_e('Leave chosen plugins\' scripts where they are.', 'flash-cache'); ?></p></div></li>
    <li><svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg><div><h4><?php esc_html_e('Per-post tools', 'flash-cache'); ?></h4><p><?php esc_html_e('Clear a single post\'s cache from the posts list, and rebuild it when you save.', 'flash-cache'); ?></p></div></li>
    <li><svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg><div><h4><?php esc_html_e('Updates and support', 'flash-cache'); ?></h4><p><?php esc_html_e('One-click updates and licence support.', 'flash-cache'); ?></p></div></li>
   </ul></div>
 </div>
<?php if ($has_pro) : ?>
 <div class="fc-cta thanks"><div><h3><?php esc_html_e('Thank you for supporting Flash Cache', 'flash-cache'); ?></h3><p><?php esc_html_e('Your licence funds support and new improvements for the free plugin.', 'flash-cache'); ?></p></div>
  <div><a class="fc-btn" href="<?php echo esc_url(admin_url('admin.php?page=flash_cache_pro_licenses')); ?>"><?php esc_html_e('Manage your licence', 'flash-cache'); ?></a></div></div>
<?php else : ?>
 <div class="fc-cta"><div><h3><?php esc_html_e('Support Flash Cache and unlock the extras', 'flash-cache'); ?></h3><p><?php esc_html_e('Every Pro licence funds the free plugin\'s development. Pick the plan that fits your sites.', 'flash-cache'); ?></p></div>
  <div><a class="fc-btn" href="<?php echo esc_url($links['checkout']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Get Flash Cache Pro →', 'flash-cache'); ?></a><small><?php esc_html_e('Secure checkout at etruel.com', 'flash-cache'); ?></small></div></div>
<?php endif; ?>
</section>

<footer class="fc-foot"><span><?php
	/* translators: %s: Flash Cache version. */
	printf(esc_html__('Flash Cache %s by', 'flash-cache'), esc_html(FLASH_CACHE_VERSION)); ?> <a href="https://etruel.com/" target="_blank" rel="noopener">Etruel Developments</a></span><a href="<?php echo esc_url($links['site']); ?>" target="_blank" rel="noopener">flashcache.net</a><a href="<?php echo esc_url($links['support']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Support', 'flash-cache'); ?></a><a href="<?php echo esc_url($links['translate']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Translate', 'flash-cache'); ?></a><a href="<?php echo esc_url($links['review']); ?>" target="_blank" rel="noopener"><?php esc_html_e('★★★★★ Leave a review', 'flash-cache'); ?></a></footer>

		<?php
		echo '</div>';
	}

}

flash_cache_about::hooks();
