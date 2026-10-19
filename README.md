# Flash Cache

Static page cache served by your web server, without PHP: cache rules per page type, background refreshes and lighter CSS and JavaScript.

[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/flash-cache?label=wordpress.org)](https://wordpress.org/plugins/flash-cache/) ![PHP 7.2+](https://img.shields.io/badge/PHP-7.2%2B-777bb4) ![WordPress 5.9+](https://img.shields.io/badge/WordPress-5.9%2B-21759b)

**Flash Cache turns every page of your WordPress site into a static file that Apache, nginx or LiteSpeed sends straight to your visitors, without starting PHP or WordPress.** A cached page is delivered in a few milliseconds, your server keeps its resources for the visitors and tasks that really need them, and your site stays fast when traffic peaks.

It is built around one principle: performance comes first. Every feature is designed so that serving a cached page costs nothing, and keeping the cache fresh costs as little as possible.

**Measured, not promised.** On our Apache test site (WordPress 7.1, PHP 7.4), the home page took 102 ms to start arriving and the server answered 18 requests per second without a cache. With Flash Cache it started arriving in 0.85 ms, the same server answered more than 23,000 requests per second, and each visit carried 5.8 KB instead of 43 KB.

[![Flash Cache video](https://img.youtube.com/vi/htlgaxQQIwk/hqdefault.jpg)](https://www.youtube.com/watch?v=htlgaxQQIwk)

### What sets Flash Cache apart

* **Truly static pages.** Cached pages are served by the web server's own rules. Visits to a fresh page never start WordPress, not even to check whether the page has expired: the page knows its own age, and only once its minimum lifetime has passed does it ask for a refresh, in the background.
* **Cache patterns: one set of rules for each kind of page.** Decide how the front page, posts, pages, categories, tags, author pages, archives, search results and feeds are cached, with their own lifetimes, for some post types or for URLs containing a given text. Drag the patterns into the order you want: the first one that applies to a page decides how it is cached, and a "Don't cache" type keeps any page live.
* **Pages with query strings get cached too.** Search results, filtered lists and other dynamic pages can be stored in PHP mode: a compact cache file serves the stored copy of each variant with a single lookup. Visits with tracking parameters (utm_*, fbclid, gclid and similar) share the same copy, and each page keeps a bounded number of variants.
* **Visitors never meet an empty cache.** Only one refresh per page runs at a time, however many visitors arrive when it expires. New posts are rebuilt in the background right after you publish, so saving stays instant. On large sites, design changes replace the cached pages a few at a time instead of throwing the whole cache away.
* **Always up to date, by itself.** Publishing or updating a post refreshes its page, the home page and its category and tag archives. Approved comments refresh their post. Saving widgets, menus, Customizer settings, block theme templates or synced patterns refreshes the cache automatically, and switching themes starts a fresh one.
* **Emptying the cache is instant, at any size.** The files are set aside at once and removed in the background, so large sites never wait or time out.
* **Preload the whole site.** A background crawler warms the cache in small batches, with the batch size and the pause between batches under your control, and can include the category and tag archives of every post.
* **Lighter pages.** Styles and scripts are combined into fewer files, read straight from disk and shared by every page that uses the same assets. Theme scripts, plugin scripts, inline scripts or single files can stay in place with one switch each.
* **Faster for returning visitors.** Every static page is stored ready-compressed with gzip, and the browser cache time is yours to choose.
* **Light on the server, even when nothing is cached yet.** Flash Cache reads its settings once per request, loads its patterns from a single stored option instead of database queries, and on PHP-FPM servers releases the visitor's connection before a page is rebuilt.
* **Clean and portable.** No drop-in files and no changes to wp-config.php. The server rules live in their own marked blocks of .htaccess or nginx.conf, and deactivating the plugin removes the rules and the cache. Moving your site to another host or domain is just as easy as with no cache at all.
* **You always know what is in the cache.** Cache statistics show static pages, PHP pages, styles, scripts and fonts with their size and count, they load instantly at any cache size, and on nginx and OpenLiteSpeed the settings screen tells you whether the server is running the current rules.
* **Made for autoblogs and busy sites.** Bulk publishing, such as a WPeMatico fetch, rebuilds each new post's page and refreshes the home page and each archive once per batch.

### Also included

* Redirect HTTP to HTTPS, with a Strict-Transport-Security header for the site's own host.
* Choose which visitors skip the cache by cookie. Logged-in users, commenters and password-protected posts are always served live.
* Form submissions always reach WordPress: only GET and HEAD requests are cached.
* A lock file or a database lock keeps two visitors from building the same page at the same time.
* A custom cache folder, validated on save.
* Settings, cache and preload actions reserved to users with the matching permissions.
* Clear settings screens grouped by topic (General, Cache updates, Advanced options and Optimization), each with its own save and reset.
* Ready for translation, with community translations at translate.wordpress.org.
* For developers: the `flash_cache_clear_url` action refreshes the cached copy of any page your code changed, every block of server rules is filterable, and defining `FLASH_CACHE_DEBUG` sends a debug log to the PHP error log.

### Works where your site runs

* **Web servers:** Apache, LiteSpeed Enterprise, OpenLiteSpeed and nginx.
* **PHP 7.2 or later** and **WordPress 5.9 or later**, on single WordPress sites.
* Tested with WPeMatico and its add-ons.

### Flash Cache Pro

Everything described above is free and complete: Flash Cache does not hold back its core to sell an upgrade. [Flash Cache Pro](https://etruel.com/downloads/flash-cache-pro/) supports its continued development and adds a few extras for professional sites:

* **Widgets and blocks without cache:** mark a block or a legacy widget as live, so it is rendered fresh on every visit while the rest of the page stays cached, optionally refreshed every few minutes.
* **Shared widget cache:** widgets that look the same on every page are built once and shared by all cached pages, and updating them never empties the cache.
* **Minify styles and scripts:** the combined files lose spaces and comments, so they weigh less.
* **Google Fonts on your server:** pages make fewer requests to other sites.
* **Leave chosen plugins' scripts in place**, one plugin at a time.
* **Per-post tools:** clear a single post's cache from the posts list, and rebuild it automatically when you save.

### Known third-party compatibility issues

In some cases other plugins insert scripts or styles in an unusual way that the script optimization cannot follow. Leave those scripts in place from the Optimization screen.

**Shortcodes Ultimate:** _Uncaught ReferenceError: SUShortcodesL10n is not defined._ Leave its scripts in place.

### Translations

Many thanks to the generous efforts of all translators.
If you'd like to help out by translating this plugin, please [sign up for an account and dig in](https://translate.wordpress.org/projects/wp-plugins/flash-cache).

## Installation

From the WordPress admin: **Plugins → Add New**, search for `flash-cache`, then **Install Now** and **Activate**. The Flash Cache menu appears in the admin sidebar.

Manually: upload the `flash-cache` folder to `/wp-content/plugins/` and activate it from the **Plugins** screen.

## Screenshots

![Settings](https://user-images.githubusercontent.com/3663229/210109406-bf4cd24d-b91c-4953-a569-bbb8701238cf.png)

![Advanced settings](https://user-images.githubusercontent.com/3663229/210109864-3a15a1b6-7cb4-462f-8f43-fb4ca2084561.png)

## Frequently Asked Questions

### I have this plugin installed and activated. What must I do now?

* Go To Flash Cache settings and set up the plugin configurations. 

### Do I need Flash Cache Pro?

* No. The free plugin is a complete page cache, with patterns, background refreshes, preload and style and script optimization. [Flash Cache Pro](https://etruel.com/downloads/flash-cache-pro/) funds its development and adds extras such as live widgets and blocks, the shared widget cache, minification and local Google Fonts.

### Can I support development and new features?

* Yes: purchasing [Flash Cache Pro](https://etruel.com/downloads/flash-cache-pro/) keeps Flash Cache growing for everyone, and you get its extra features. Reviews and translations help a lot too.

### How do I install and configure Flash Cache?

* In this [FAQ](https://etruel.com/question/how-to-install-and-setup-flash-cache/) you will find a step to step tutorial on how to install and configure Flash Cache.

### Does Flash Cache work with nginx?

* Yes. nginx reads rules only from its own configuration, so Flash Cache writes them to nginx.conf in the site's folder and you include that file once in the site's server block. The General settings screen shows the exact line and tells you when nginx needs a reload to pick up changed settings.

### Does Flash Cache work with LiteSpeed?

* Yes, with LiteSpeed Enterprise and OpenLiteSpeed. Flash Cache writes its rules to .htaccess and the server sends the cached pages, compressing them itself. OpenLiteSpeed reads .htaccess changes when it restarts, and the General settings screen tells you when a restart is due. It also skips the headers in .htaccess: the same screen gives you the virtual host settings for browser cache headers, ready to copy.

### Does Flash Cache work on WordPress Multisite?

* No. Flash Cache writes its rules and cache folders for one site, and the sites of a network share the same server rules and home folder. It can only be activated on single WordPress sites.

### How many PHP workers does Flash Cache need?

* Cached pages are served by the web server alone, without PHP. Building a page in the background (after publishing, during the preload or when a cached page expires) requests that page from your own site, so it needs one free PHP worker besides the one doing the work.
* We recommend at least 4 PHP workers (PHP-FPM `pm.max_children`). With 2, a long background task such as a large import can make uncached visits wait in line for a free worker.
* On hosting with few workers, turn off the pattern options "Cache the post when it is published or updated", "Refresh the home page…" and "Refresh the first page of its categories and tags": publishing then clears those pages, and the next visitor caches them without any background request.

## Changelog

See [readme.txt](readme.txt) for the full changelog.
