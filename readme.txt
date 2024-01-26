=== Flash Cache ===
Contributors: sniuk, etruel, khaztiel, Gerarjos14
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7267TH4PT3GSW
Tags: cache, performance, flash, automatically cache, seo, nginx, apache, litespeed, flashcache, seo, wordpress cache
Requires at least: 3.6
Tested up to: 6.4.2
Requires PHP: 5.6
Stable tag: trunk
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

> * The Flash Cache plugin is tested with the most important servers that can run WordPress, such as *Apache, nginx and LiteSpeed* and supports *PHP 5.6 to 8.2.*
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


== Changelog ==

= 3.2.2 Dec 28, 2023 =
* Fixes the CSS optimization process that could sometimes fail for responsive styles. 

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
* Few improvements and bug fixes!
