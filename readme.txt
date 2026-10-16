=== Flash Cache ===
Contributors: etruel, khaztiel, Gerarjos14, sniuk
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7267TH4PT3GSW
Tags: cache, performance, speed, optimize, wp cache
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 3.6
License: GPLv2 or later

Flash Cache is a plugin to improve the performance of Wordpress Websites by making html versions of each post, pages or sections of your website.

== Description ==

Flash Cache is a powerful plugin which optimizes the websites speed thanks to processes and technologies that reduces the overload of the websites where it is installed, improving the velocity till 10x comparing with other cache plugins for WordPress.

[youtube https://www.youtube.com/watch?v=htlgaxQQIwk]

#### With the Flash Cache plugin you will be able to:

* Increase the speed at which your site is displayed to the end user.
* Reduce the use of server resources when there are many visitors.
* Create a more enjoyable experience for the user.
* Help with the positioning and the SEO of the web site.
* Decrease the workload on the system behind the cache.
* And if that's not enough for you, read on for many more benefits.

== Some Features ==
The most important characteristics that makes this add-on the first choice comparing to others are the next ones:

> * The Flash Cache plugin is tested with the most important servers that can run WordPress, such as *Apache, nginx and LiteSpeed* and runs on *PHP 8.1 or higher*.
> * Flash Cache allows infinities configurations thanks to the patterns, these can be done with different cache patterns according the kind of page that will be cached.
> * It avoid the PHP execution in foreground at time of serve the cache objects, improving significantly the performance and the overload of the server.
> * Crawl Budget optimization, the majority of the search engines assigns a quantity of means to go over the websites, with Flash Cache a higher number of pages are indexes and is better positioned in the search engines.
> * It allows keep the content in the cache updating instantaneously when is modified, for example, when a new post is added, it creates a cache object of the post and it can refresh other pages automatically as the homepage.
> * In addition to optimizing the cache on the server, it manages the cache in the web browser improving the performance in client-side and server-side.
> * It allows to make a preload of the entire website, to keep a cache of all the site optimized and configurable.
> * Most of the cache plugins in WordPress break the website structure, and this brings problems at the time of move the websites to another hosting or domain.  This problem doesn’t exist with Flash Cache. You only need to deactivate the cache and the plugin you can change the hosting of the website and reinstalled the Flash Cache without problems.


= Known third-party plugins or theme compatibility issues =
In some cases, there are compatibility issues with other plugins. Most of the time this is caused by other plugins inserting javascript or styles in a wrong or weird way.

**Shortcodes Ultimate:** _Uncaught ReferenceError: SUShortcodesL10n is not defined._

### Translations

Many thanks to the generous efforts of all translators.
If you'd like to help out by translating this plugin, please [sign up for an account and dig in](https://translate.wordpress.org/projects/wp-plugins/flash-cache).

== Installation ==

You can either install it automatically from the WordPress admin, or do it manually:

= Using the Plugin Manager =

1. Click Plugins
2. Click Add New
3. Search for `flash-cache`
4. Click Install
5. Click Install Now
6. Click Activate Plugin
7. Now you must see the Flash Cache Items on the Wordpress menu

= Manually =

1. Upload `flash-cache` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress


== Screenshots ==

1. Settings Screens.
1. Advanced Settings.


== Frequently Asked Questions ==

= I have this plugin installed and activated. What must I do now? =

* Go To Flash Cache settings and set up the plugin configurations. 

= Can I support development and new features? =

* By purchasing [Flash Cache PRO](https://etruel.com/downloads/flash-cache-pro/), you help us continue to develop new features with better and more powerful functionalities.

= How do I install and configure Flash Cache? =

* In this [FAQ](https://etruel.com/question/how-to-install-and-setup-flash-cache/) you will find a step to step tutorial on how to install and configure Flash Cache.

= Does Flash Cache work with nginx? =

* Yes. nginx reads rules only from its own configuration, so Flash Cache writes them to nginx.conf in the site's folder and you include that file once in the site's server block. The General settings screen shows the exact line and tells you when nginx needs a reload to pick up changed settings.

= Does Flash Cache work on WordPress Multisite? =

* No. Flash Cache writes its rules and cache folders for one site, and the sites of a network share the same server rules and home folder. It can only be activated on single WordPress sites.

= How many PHP workers does Flash Cache need? =

* Cached pages are served by the web server alone, without PHP. Building a page in the background (after publishing, during the preload or when a cached page expires) requests that page from your own site, so it needs one free PHP worker besides the one doing the work.
* We recommend at least 4 PHP workers (PHP-FPM `pm.max_children`). With 2, a long background task such as a large import can make uncached visits wait in line for a free worker.
* On hosting with few workers, turn off the pattern options "Cache the post when it is published or updated", "Refresh the home page…" and "Refresh the first page of its categories and tags": publishing then clears those pages, and the next visitor caches them without any background request.


== Changelog ==

= 4.0 Oct 15, 2026 =
* A new major version, built for PHP 8.1 or later and WordPress 5.9 or later.
* Leaner requests: Flash Cache reads its settings once per page load.
* Safer cache folder setting: the folder is validated on save and must be a folder of its own inside the site, apart from the WordPress folders.
* Cache maintenance works strictly inside the cache folder, removing hidden files too and leaving linked folders untouched.
* Cache folders are named after the site's own address, and the server rules point to them directly.
* Sites addressed by an IPv6 address, such as http://[2001:db8::1]/, get their own cache folder and rules too.
* Settings, cache and preload actions are reserved to users with the matching permissions, with clear messages when an action is not allowed.
* Leaner PHP mode: a compact cache file serves each stored page with a single lookup and hands over to WordPress cleanly when the page needs refreshing.
* Smarter PHP-mode cache keys: visits with tracking parameters (utm_*, fbclid, gclid and similar) share the same cached page, and each page keeps a bounded number of variants (filter `flash_cache_max_request_files`).
* Cached pages stay fully static: the browser checks the page's age itself and asks for a refresh only once its minimum TTL has passed, so visits to fresh pages never start WordPress.
* One refresh per page at a time, however many visitors arrive when it expires, and on PHP-FPM servers the visitor's connection is released before the page is rebuilt.
* New setting "Refresh expired pages": on visits (default) or from the preload only, for fully script-free cached pages.
* Form submissions always reach WordPress live: only GET and HEAD requests are cached and served from cache.
* Reliable page locks: the database lock uses MySQL named locks, so it works on every MySQL and MariaDB version and leaves the database session untouched; file locks live in one locks folder and step aside quietly where the folder cannot be written.
* The pattern options "Create cache on insert/update", "Update home on insert post" and "Update categories on post" rebuild those pages in the background right after publishing, so saving a post stays instant.
* Bulk publishing, such as a WPeMatico fetch, rebuilds each new post's own page and refreshes the home page and each archive once per batch.
* Approved comments refresh their post's cache in the background.
* Faster uncached requests: patterns load from a single stored option instead of database queries.
* Patterns follow the order you give them: drag them in the Patterns list, and the first pattern that applies to a page decides how it is cached. The Default pattern always comes last and caches every page no other pattern takes.
* Each Page types box decides for its own pages: categories, tags and author pages follow their own boxes, "Other archives" covers date, custom taxonomy and post type archives, and "Posts page" is the list of latest posts when the front page is a static page.
* New pattern cache type "Don't cache", to keep the pages it applies to always served live.
* A pattern limited to some post types takes those posts ahead of the patterns below it, and the Patterns list shows what each pattern applies to and how it caches.
* Saving, reordering or deleting a pattern clears the cache, so the new rules apply to the next visit.
* New action `flash_cache_clear_url`: other plugins can refresh the cached copy of a page whose content they changed, with `do_action( 'flash_cache_clear_url', $url )`.
* Pattern settings are stored clean, each field validated by type, and "Reset to default" restores exactly the default patterns.
* Rebuilt style and script optimization: the site's own files are read straight from disk, and each combined file is shared by every page that uses the same assets, written once, in its own folder apart from the cached pages.
* Combined styles keep each stylesheet's media (print styles stay for print) and the cascade order, and relative image and font references keep working.
* Combined scripts keep their place and order: consecutive scripts are joined where the last of them stood, and async, deferred and module scripts stay as they are.
* Third-party stylesheets load from their own servers; Flash Cache Pro can still host Google Fonts locally.
* Feeds, sitemaps and other non-HTML responses are cached exactly as WordPress sends them, and HTML comments are kept.
* One rule maps every URL to its cache folder: paths relative to the home address, decoded as the web server reads them and with their original letter case, also for sites installed in a subfolder.
* On nginx, the General settings screen shows whether nginx is running the current Flash Cache rules and, when it is not, the exact line to add to the server configuration or the reminder to reload nginx.
* nginx rules serve cached copies of pages with accented or non-Latin addresses, follow sites installed in a subfolder, and match the site's trailing-slash permalinks.
* Static HTML copies come only from requests without a query string (tracking parameters aside), so a language or cart parameter never shapes the page everyone else gets.
* Only successful responses are cached: error pages and other non-200 answers are always served live.
* "Execute Preload" starts right away and caches every published page in turn.
* The preload follows each pattern's "URL must contain" and "URL should not contain" rules and its own refresh times, and its background task exists only while the preload is enabled.
* The disk usage counter is saved once per request.
* Clearer settings screens: short labels, plain-language descriptions, and every text ready for translation.
* The script exclusion switches say what they do ("Leave theme scripts in place"), and "Scripts from other domains" names the switch that keeps analytics, ads and social scripts on their own servers.
* "Redirect HTTP to HTTPS" works behind proxies, CDNs and hosts that end HTTPS before the web server, runs before any cached page is served, and keeps the query string.
* "Redirect HTTP to HTTPS" sends a Strict-Transport-Security header for the site's own host, adjustable with the `flash_cache_hsts_header` filter; on a site opened over HTTP the screen explains when the option appears.
* Preload times follow the site's date and time format.
* Debug logging is off by default; define FLASH_CACHE_DEBUG as true to send it to the PHP error log.
* "Clear Entry Cache" takes you back to the post you were editing.
* Changing the cache folder, deactivating the plugin or deleting the cache leaves no empty folders behind.
* Built for single WordPress sites: activating it on a Multisite network stops with a clear message.
* After updating, the cache is rebuilt from scratch, the server rules are refreshed automatically and the database lock table is removed, since locks need no table.

= 3.6 Apr 15, 2025 =
* This update features security improvements and is recommended for all users.
* Bump to WP 6.8

= 3.5 Apr 3, 2025 =
* Improved many some filters to allow pro version to be more efficient by avoiding caching widgets.
* Fixes getting cache initial path on creating cache for special url of some files.
* Fixes double compression some times serving zipped files.
* Fixes Cache PHP mode to be used with avoid cache widgets feature of PRO Version. 
* Fixed an issue to prevent WP framework to being loaded in caches generated on PHP mode.
* Fixes bug Notice Function _load_textdomain_just_in_time was called incorrectly.

= 3.4 Jul 25, 2024 =
* Added compatibility with pro version to cache external fonts. 
* Added checking css files from links to sometimes avoid 404 errors.
* Fixes some logical things on hidding/showing options in Settings page.
* Fixes one option not deleted and optimize functions to clean all on uninstall. 

= 3.3 Jun 7, 2024 =
* We've made a very important change creating our own table to store the URl hashes of the cache, and avoid using the WordPress tables, losing a lot of performance in the process.
* Bump to WP 6.6
* Added new functions flash_cache_get_option and flash_cache_set_option.
* Fixes PHP Warning: Undefined array key

= 3.2.3 Feb 1, 2024 =
* Fixes two issues with the options Url must contain and not contain. 
* See more details about current release at [this link](https://flashcache.net/update-to-3-2-3-feb-1-2024/)

= 3.2.2 Dec 28, 2023 =
* Fixes the CSS optimization process that could sometimes fail for responsive styles. 
* See more details about current release at [this link](https://flashcache.net/update-to-3-2-2-dec-28-2023/)

= 3.2.1 Dec 9, 2023 =
* Added Reset to Default button on Patterns Settings screen.
* Tested up to WordPress 6.4.2
* See more details about current release at [this link](https://flashcache.net/update-to-3-2-1-dec-9-2023/)

= 3.2 Nov 21, 2023 =
* Fixes and improves Lock by DB methods on creating Cache.
* NOTE: If you've selected Lock by DB option, on update Flash Cache will delete the cache to start from scratch and improve performance and security.
* Tested up to WordPress 6.4.1
* See more details about current release at [this link](https://flashcache.net/update-to-3-2-nov-21-2023/)

= 3.1.4 Nov 02, 2023 =
* Drastically improves the folder creation process by avoiding the cache of empty searches. This includes search accesses by bots.
* Compatibility with WordPress 6.4

= 3.1.3 Sep 11, 2023 =
* Added new feature to remove HTML comments.
* Fixes minor issues with translation strings.
* Updated .pot language project file.
* Started working on translations for Spanish-Argentina.
* Waiting for editors' approval on https://translate.wordpress.org/projects/wp-plugins/flash-cache/

= 3.1.2 Sep 05, 2023 =
* Fixes error on uninstall plugin.

= 3.1.1 Aug 02, 2023 =
* Fixes error on using new function to include SEO Platforms in optimized scripts.

= 3.1 Jul 31, 2023 =
* New options to exclude specific JS files:
* Avoid optimization of inline JS scripts found in HTML DOM.
* Avoid optimization of JavaScript files from themes.
* Avoid optimization of JavaScript files from plugins.

= 3.0.6 Jul 04, 2023 =
* Added Settings footer link to YouTube tutorials for Flash Cache.

= 3.0.5 May 31, 2023 =
* Added controls to check the allowed Permalink structure to work.
* Fixes a notices bug showing duplicated ones. 
* Fixes the uninstall sometimes did not remove the cache files. 

= 3.0.4 Apr 19, 2023 =
* Added delete cache feature on save WordPress Permalinks structure as changes also directories of cache.
* Added fonts cache on its own directory.
* Added style images cache on its own directory.
* Fixes CSS styles for icons and fonts URLs for combined files.

= 3.0.3 Apr 12, 2023 =
* Fixes a bug for css styles and js code sometimes failing to load.
* Fixes javascript errors on loading javascript combined file.
* Fixes an issue on filter flash_cache_js_code_before_join.
* Fixes an issue on calculating cache size.
* Fixes the blank lines added to .htaccess on modifing its rules.

= 3.0.2 Feb 28, 2023 =
* Fixes a bug for WordPress based sites installed in subdirectories.
* Fixes an issue with third party notices showing below the Flash Cache Settings title.
* Fixes LinkedIn link in the icon at Settings.
* Fixes menu position for some third party plugins.

= 3.0.1 Feb 21, 2023 =
* Improves the urls validating. 
* Minor tweaks & texts improvements.

= 3.0 Jan 24, 2023 =
* New version released as free plugin.
* Fixes an issue with the cache template file.
* Many fixes and improvements to the preload cache process.
* Many security improvements.

= 2.0 Apr 5, 2022 =
* Rebranding images, logo and name change to Flash Cache!
* New design from scratch.
* New Settings and administration pages.
* Refactored and tested many new features and solved issues. (most important)
* Many help texts and tips on the screens to help users understand each option better and more intuitively.

= 1.4.2 Jan 23, 2022 =
* Fixes a Fatal Error on PHP 8.
* Compatibility with PHP 8 and WordPress 5.8.3

= 1.4.1 Jun 25, 2019 =
* Fixes a Fatal Error when try to deactivate the plugin without Flash Cache Activated.

= 1.4 =
* Added an option to delete all the cache from the front-end in the WordPress admin bar.
* Tweaks in the creation of cache of the taxonomies pages.
* Tweaks in the license page hidden the renewal links for lifetime licenses.
* Fixes number version, missed 1.3 in previous release.

= 1.3 Aug 25, 2017 =
* Tweaks in the preload to run a single process simultaneously.

= 1.2 =
* Fixes an issue to avoid cache an individual feed.
* Minor changes on serialized cache.

= 1.1 =
* Some minnor fixes.

= 1.0.0 Beta =
First Release

== Upgrade Notice ==

= 4.0 =
A major version: it requires PHP 8.1 and WordPress 5.9 or later, and it works on single sites only, not on Multisite networks. After updating, the cache is rebuilt from scratch and the server rules are refreshed automatically.
