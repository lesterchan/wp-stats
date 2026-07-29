# WP-Stats
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: stats, statistics, widget, popular, information  
Requires at least: 6.8  
Tested up to: 7.0  
Stable tag: 3.0.0  
Requires PHP: 8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your WordPress blog statistics. Ranging from general total statistics, some of my plugins statistics and top 10 statistics.

## Description
WP-Stats builds a statistics page for your blog out of what is already in your database: how many posts, pages, comments, tags, categories and links there are, which posts drew the most comments, who has commented most often, and who writes for you. Put the `[page_stats]` shortcode on a page and that page becomes your statistics page. There is a sidebar widget for the totals, and the same statistics appear in wp-admin under WP-Stats.

Other plugins of mine can add their own blocks to that page. WP-Stats does not need to know anything about them in advance and never reads their settings.

### Features
* A full statistics page from one shortcode
* Totals for posts, pages, comments, commenters, tags, categories, links and authors
* Top X most commented posts and pages, most recent posts and most recent comments
* A per-commenter view listing every comment somebody has left, with paging
* A sidebar widget for the totals
* Every block can be switched off individually

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Usage

### Create The Stats Page
1. Go to `WP-Admin -> Pages -> Add New`
2. Type any title you like in the page's title area
3. If you ARE using nice permalinks, WordPress will generate the permalink to the page after you type the title. You will see an 'Edit' link just beside the permalink.
4. Click 'Edit', type in `stats` in the text field and click 'Save'.
5. Type `[page_stats]` in the page's content area
6. Click 'Publish'
7. If you ARE NOT using nice permalinks, go to `WP-Admin -> WP-Stats -> Settings` and fill in 'Stats URL' with the URL of the page you just created.

### The Widget
1. Go to `WP-Admin -> Appearance -> Widgets`
2. The widget name is `Stats`.

### For Plugin Authors
A plugin adds a block to the statistics page by answering one filter. Return an entry keyed by your own slug with underscores:

```php
add_filter( 'wp_stats_sections', function ( $sections ) {
	$options = get_option( 'my_plugin_options', array() );

	if ( empty( $options['stats_display'] ) ) {
		return $sections;   // the site owner switched this block off
	}

	$sections['my_plugin'] = array(
		'title'    => __( 'My Plugin', 'my-plugin' ),
		'priority' => 10,
		'render'   => 'my_plugin_render_stats',
	);

	return $sections;
} );

function my_plugin_render_stats() {
	echo '<ul><li>42 things.</li></ul>';
}
```

`title` is a translated heading and is required. `render` is a callable that echoes the block body, takes no arguments, and is required. `priority` sorts the blocks and defaults to 10; ties are broken by the array key.

Decide out of your own settings whether to contribute at all, and return `$sections` untouched if the answer is no rather than adding an entry with an empty body. WP-Stats never reads another plugin's option row and never checks whether your classes exist — if your plugin is not installed, nothing answers the filter and no block appears.

WP-Stats then fires `wp_stats_section_my_plugin` with your entry. Its own listener on that action is what prints the heading and calls `render`, so a theme can take one plugin's block over without disturbing the others:

```php
add_action( 'wp_stats_section_my_plugin', function ( $section ) {
	remove_action( 'wp_stats_section_my_plugin', array( 'WP_Stats_Page', 'render_section' ) );
	echo '<p>drawn by the theme instead</p>';
}, 5 );
```

An entry that is malformed — not an array, no title, a `render` that is not callable — is skipped. It will not take the page down, and it will not tell you either, so check your block appears.

## Frequently Asked Questions

### Which options does the plugin store?
Two rows. `wp_stats_options` holds the stats page URL, the "top X" limit and the display toggles; `wp_stats_version` records the version last run so an upgrade knows what it is upgrading from. Deleting the plugin from the Plugins screen removes both.

### I have a plugin that reads `get_option( 'stats_display' )`. Will it break?
Yes. That row is deleted during the upgrade, and 3.0.0 no longer answers for it. Seven plugins used to share it, which meant none of them could change how it was stored without breaking the other six. Keep your own copy of the setting in your own option row and answer `wp_stats_sections` — see "For Plugin Authors" above.

### The Plugins Stats heading has disappeared
It only appears now when a plugin has actually contributed a block. Before 3.0.0 it was printed whether or not there was anything under it.

### Why do my author counts look different?
"Authors" now means the users who can publish posts, which is what the block always claimed to count. It used to be derived from the legacy `user_level` meta, and it excluded anyone with a password reset in progress.

## Screenshots

1. Admin - Statistics
2. Admin - Settings
3. Admin - Widget
4. Stats Page
5. Stats Sidebar

## Changelog
### 3.0.0
* BREAKING: Requires WordPress 6.8 and PHP 8.2, up from 6.0 and 7.4.
* BREAKING: The `stats_url`, `stats_mostlimit`, `stats_display`, `stats_options` and `stats_db_version` rows are replaced by `wp_stats_options` and `wp_stats_version`, migrated automatically on upgrade and then deleted. The old names are no longer answered.
* BREAKING: The unprefixed `stats_page` filter is now `wp_stats_page`, and is handed the page complete with its wrapper element.
* BREAKING: The seven `wp_stats_page_*` filters, the two `wp_stats_paging_*` filters and the seven `wp_stats_page_admin_*` filters are removed. A plugin contributing a block answers `wp_stats_sections` and renders through `wp_stats_section_<slug>`.
* BREAKING: `stats_display_defaults()` is removed. A plugin keeps its own display setting in its own option row.
* BREAKING: The statistics screen moved from `Dashboard -> WP-Stats` to its own `WP-Stats` menu, and the settings from `Settings -> Stats` to `WP-Stats -> Settings`.
* BREAKING: Every class is prefixed. The widget class is `WP_Stats_Widget`; placed widgets and their settings are unaffected.
* NEW: `wp_stats_sections` and `wp_stats_section_<slug>`, the contract other plugins use to add a block to the statistics page.
* NEW: `wp_stats_capability`, which every capability check goes through, so the read-only statistics screen can be opened to editors without opening the settings.
* NEW: Restructured into `includes/` with one class per responsibility.
* NEW: Settings screen rebuilt on the WordPress Settings API.
* NEW: PHPUnit test suite and GitHub Actions CI.
* CHANGED: The statistics page is wrapped in `<div class="wp-stats">` and the stylesheet is scoped to it, so it no longer restyles WP-PageNavi's paging and no longer refuses to load when that plugin is active.
* CHANGED: The stylesheet moved to `css/wp-stats.css` and inherits the theme's colours instead of hardcoding blue on white. A theme's own copy of `stats-css.css` is no longer looked for; dequeue the `wp-stats` handle instead.
* CHANGED: Every listing is built from core query APIs rather than hand-written SQL, so the results are cached the way the rest of WordPress caches them and any filter a site already uses to hide content still applies.
* CHANGED: The "Authors" counts are the users who can publish posts, rather than users with a legacy `user_level` above 1.
* CHANGED: `stats_author` and `stats_page` are registered query variables now.
* FIXED: The stylesheet never loaded, because it was hooked to `wp_enqueue_script`, which is not an action. It now loads on pages that render the paging.
* FIXED: Rendering the statistics page no longer overwrites the global `$post`, which could leave a theme's loop pointing at the wrong entry.
* FIXED: Uninstalling on multisite stopped at the hundredth site and left the rest of the network's rows behind.
* FIXED: `get_linkcats()` and `get_tags_list()` used arguments and functions deprecated since WordPress 3.0.
* FIXED: Removed the unreachable "Comments Protected" branch; every listing already excludes password-protected posts.
* FIXED: A negative `stats_page` query argument caused a SQL error.
* FIXED: Undefined index warning reading the post-password cookie.
* FIXED: The widget's "Statistics To Display" label used the wrong text domain and could not be translated.
* FIXED: The widget's spam line read "Spam Blockeds" in the plural.

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
The first release since 2.56.1, and five things about it are worth knowing before you update.

**Your site must be on WordPress 6.8 or later and PHP 8.2 or later.** Anything older will simply not be offered the update. If your host still runs PHP 7.4, ask to be moved to a supported version before updating — 7.4 stopped receiving security fixes in 2022.

**Both admin screens have moved.** The statistics used to be at `Dashboard -> WP-Stats` and the settings at `Settings -> Stats`. There is one `WP-Stats` menu now, with `Statistics` and `Settings` under it. Update any bookmarks; nothing else about the screens changed.

**Update WP-EMail, WP-Polls, WP-PostRatings, WP-PostViews, WP-UserOnline and WP-DownloadManager at the same time.** Those six put their own blocks on the statistics page, and until now all seven plugins shared one `stats_display` option row between them. Each plugin keeps that setting in its own settings now, and the shared row is deleted during the upgrade. An older copy of any of those six will simply stop appearing on the statistics page until you update it — nothing breaks, but its block goes missing. If a block does not come back after updating, switch it on again in that plugin's own settings.

**Everything WP-Stats stores has been renamed and is migrated for you.** `stats_url`, `stats_mostlimit` and `stats_display` become one `wp_stats_options` row, and the upgrade marker becomes `wp_stats_version`. The old rows are read once and then deleted. If your own code called `get_option( 'stats_url' )` or `get_option( 'stats_display' )`, it will get `false` from now on.

**If you have written code against WP-Stats, the hook names have changed.** `stats_page` is now `wp_stats_page`. The `wp_stats_page_general`, `wp_stats_page_plugins`, `wp_stats_page_recent`, `wp_stats_page_most`, `wp_stats_page_authors`, `wp_stats_page_comments_members`, `wp_stats_page_misc`, `wp_stats_paging_start`, `wp_stats_paging_end` and the seven `wp_stats_page_admin_*` filters are all removed, as is the `stats_display_defaults()` function. The replacement is one filter, `wp_stats_sections` — see "For Plugin Authors" in the description. Every template tag a theme calls (`get_totalposts()`, `get_recentcomments()`, `stats_page()`, `snippet_text()` and the rest) keeps its name and its arguments.

Two smaller things you may notice: the "Plugins Stats" heading no longer appears when no plugin has contributed a block to it, and the front-end stylesheet is scoped to WP-Stats' own markup, so it no longer restyles WP-PageNavi — which also means WP-Stats no longer switches its own styles off when WP-PageNavi is active.
