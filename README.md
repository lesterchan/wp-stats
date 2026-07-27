# WP-Stats
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: stats, statistics, widget, popular, information  
Requires at least: 6.0  
Tested up to: 7.0  
Stable tag: 3.0.0  
Requires PHP: 7.4  
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your WordPress blog statistics. Ranging from general total statistics, some of my plugins statistics and top 10 statistics.

## Description

### Usage (Create Stats Page)
1. Go to 'WP-Admin -> Pages -> Add New'
2. Type any title you like in the page's title area
3. If you ARE using nice permalinks, after typing the title, WordPress will generate the permalink to the page. You will see an 'Edit' link just beside the permalink.
4. Click 'Edit' and type in 'stats' in the text field (without the quotes) and click 'Save'.
5. Type `[page_stats]` in the page's content area
6. Click 'Publish'
7. If you ARE NOT using nice permalinks, you need to go to `WP-Admin -> Settings -> Stats` and under 'Stats URL', you need to fill in the URL to the Stats Page you created above.

### Usage (With Widget)
1. Go to `WP-Admin -> Appearance -> Widgets`
2. The widget name is `Stats`.

### For Plugin Authors
WP-Stats lets other plugins add their own blocks to the statistics page. Register your toggle, then hang panels off the filters:

~~~php
add_filter( 'wp_stats_display_defaults', function ( $defaults ) {
	return array_merge( array( 'my_plugin' => 1 ), (array) $defaults );
} );

add_filter( 'wp_stats_page_admin_plugins', function ( $content ) {
	return $content . wp_stats_checkbox( 'my_plugin', __( 'My Plugin', 'my-plugin' ) );
} );

add_filter( 'wp_stats_page_plugins', function ( $content ) {
	if ( ! wp_stats_display_enabled( 'my_plugin' ) ) {
		return $content;
	}

	return $content . '<p><strong>My Plugin</strong></p><ul><li>42 things.</li></ul>';
} );
~~~

The options screen is built with the Settings API, and each `wp_stats_page_admin_*` filter fills one registered field row: `general`, `plugins`, `recent`, `most`, `authors`, `comments_members` and `misc`. Return checkbox markup from `wp_stats_checkbox()` and it lands in that row.

`wp_stats_most_limit()` returns how many entries the "top X" blocks are configured to show.

Use these helpers rather than reading WP-Stats' options directly: 3.0.0 consolidated the settings into a single row, and the helpers are what insulate you from that.

### Development
[https://github.com/lesterchan/wp-stats](https://github.com/lesterchan/wp-stats "https://github.com/lesterchan/wp-stats")

### Credits
* Plugin icon by [SimpleIcon](https://www.simpleicon.com) from [Flaticon](https://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog
### 3.0.0
* NEW: Requires WordPress 6.0 and PHP 7.4
* NEW: Restructured into `includes/` with one class per responsibility
* NEW: Settings screen rebuilt on the WordPress Settings API
* NEW: `wp_stats_display_enabled()`, `wp_stats_most_limit()` and `wp_stats_checkbox()` for plugins that add their own panels
* NEW: PHPUnit test suite and GitHub Actions CI
* CHANGED: The `stats_url`, `stats_mostlimit` and `stats_display` options are consolidated into a single `stats_options` row, migrated automatically on upgrade. The old option names still answer `get_option()`, and the options screen still accepts the old `stats_display[]` checkbox field, so a plugin written against WP-Stats 2.x keeps working unchanged
* CHANGED: The admin pages moved from `wp-stats/wp-stats.php` and `wp-stats/stats-options.php` to `wp-stats` and `wp-stats-options`, so any bookmarks to them need updating
* CHANGED: The widget class is now `Stats_Widget`; placed widgets and their settings are unaffected
* FIXED: The stylesheet never loaded, because it was hooked to `wp_enqueue_script`, which is not an action. It now loads on pages that render the stats paging
* FIXED: Uninstalling on multisite stopped at the hundredth site and left the rest of the network's options behind
* FIXED: `get_linkcats()` and `get_tags_list()` used arguments and functions deprecated since WordPress 3.0
* FIXED: Removed the unreachable "Comments Protected" branch; every listing query already excludes password-protected posts
* FIXED: A negative `stats_page` query argument caused a SQL error
* FIXED: Undefined index warning reading the post-password cookie
* FIXED: The widget's "Statistics To Display" label used the wrong text domain and could not be translated
* FIXED: Prepared the remaining queries that interpolated their limit and post type

### 2.56.1
* FIXED: Reflected XSS via double-encoded `stats_author` parameter (sanitize input, escape output)
* FIXED: Stored XSS via comment author names, display names and post titles (escape all output)
* CHANGED: Use `$wpdb->prepare()` for comment author queries

### 2.56
* NEW: WordPress 5.5 only because of comment_type changes

### 2.55
* FIXED: Notices

### 2.54
* FIXED: Akismet_Admin class not found

### 2.53
* FIXED: Notices in Widget Constructor for WordPress 4.3

### 2.52
* FIXED: Added nonce field and esc_url() to fix XSS. Props HSASec-Team.

### 2.51
* NEW: Supports WordPress Multisite Network Activation
* NEW: Uses WordPress native uninstall.php

### 2.50 (01-06-2009)
* NEW: Works For WordPress 2.8
* NEW: Update PageNavi Function Names
* NEW: Added Most Commented Pages
* NEW: Use _n() Instead Of __ngettext() And _n_noop() Instead Of __ngettext_noop()
* NEW: Uses New Widget Class From WordPress
* NEW: Merge Widget Code To wp-stats.php And Remove wp-stats-widget.php
* FIXED: Uses $_SERVER['PHP_SELF'] With plugin_basename(__FILE__) Instead Of Just $_SERVER['REQUEST_URI']
* FIXED: Recent Comments Now Include Comments Posted In Pages

## Upgrade Notice

### 3.0.0
The three `stats_*` options are consolidated into one and migrated automatically. If you use WP-EMail, WP-PostViews, WP-UserOnline, WP-Polls, WP-PostRatings or WP-DownloadManager, update them too so their panels keep saving.

## Screenshots

1. Admin - Dashboard
2. Admin - Options
3. Admin - Widget
4. Stats Page
5. Stats Sidebar

## Frequently Asked Questions

### Which options does the plugin store?
One row, `stats_options`, holding the stats page URL, the "top X" limit and the display toggles. Before 3.0.0 these were three separate rows; they are migrated on upgrade and then removed.

### I have a plugin that reads `get_option( 'stats_display' )`. Will it break?
No. The old option names are served from the consolidated row, so existing code keeps reading the right values. New code should use `wp_stats_display_enabled()` instead.
