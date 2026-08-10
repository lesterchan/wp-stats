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

## Installation

1. Install and activate the plugin.
1. Create a page and put the `[page_stats]` shortcode in it, or add the **Stats** block. That page becomes your statistics page. Usage below has the full steps.
1. The same statistics are in wp-admin under `WP-Stats`, and there is a sidebar widget for the totals.

Other plugins of mine add their own sections to that page when they are installed. WP-Stats needs to know nothing about them in advance.

## Usage

### Create The Stats Page
1. Go to `WP-Admin -> Pages -> Add New`
2. Type any title you like in the page's title area
3. If you ARE using nice permalinks, WordPress will generate the permalink to the page after you type the title. You will see an 'Edit' link just beside the permalink.
4. Click 'Edit', type in `stats` in the text field and click 'Save'.
5. Type `[page_stats]` in the page's content area
6. Click 'Publish'
7. If you ARE NOT using nice permalinks, go to `WP-Admin -> WP-Stats`, open the `Settings` tab and fill in 'Stats URL' with the URL of the page you just created.

### The Block

**Blog Statistics** is in the editor's inserter, under **Widgets**. Add it to a page and that page is your statistics page — there is nothing to configure on the block itself, because what the page shows comes from the settings and from whichever companion plugins are installed, not from the post.

It renders on the server, so the preview in the editor is the real page rather than an approximation, and the numbers are counted afresh on every view rather than frozen into the post when you save it.

**The shortcode still works and is not going anywhere.** `[page_stats]` behaves exactly as it always has, and a page already containing it needs no change. The block calls the same code the shortcode calls, so the two render identically — including the blocks other plugins contribute — and you can use whichever suits the page.

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

1. Stats -> Statistics: the site, then a section for every plugin that contributes one
2. The Settings tab, which chooses what the report and the widget carry
3. The stats page a visitor sees, from the shortcode
4. The widget, in a sidebar

## Changelog
### 3.0.0
* FIXED: Recent Posts and Most Commented did not honour a site's own `posts_where` filter. `get_posts()` suppresses filters by default, so a membership or paywall plugin hiding published posts had them listed on the public statistics page anyway — which contradicted the reasoning the rest of the file is built on, that these queries go through core's APIs precisely so a site's content rules still apply
* FIXED: One of the three places that builds an author link did not escape the URL, relying instead on the caller having encoded the name first. It happens to be true today; nothing enforces it
* BREAKING: Requires WordPress 6.8 and PHP 8.2.
* BREAKING: The `stats_url`, `stats_mostlimit`, `stats_display`, `stats_options` and `stats_db_version` rows are replaced by `wp_stats_options` and `wp_stats_version`, migrated automatically on upgrade and then deleted. The old names are no longer answered.
* BREAKING: The unprefixed `stats_page` filter is now `wp_stats_page`, and is handed the page complete with its wrapper element.
* BREAKING: The seven `wp_stats_page_*` filters, the two `wp_stats_paging_*` filters and the seven `wp_stats_page_admin_*` filters are removed. A plugin contributing a block answers `wp_stats_sections` and renders through `wp_stats_section_<slug>`.
* BREAKING: `stats_display_defaults()` is removed. A plugin keeps its own display setting in its own option row.
* BREAKING: The statistics screen moved from `Dashboard -> WP-Stats` and the settings from `Settings -> Stats` onto one `WP-Stats` page with two tabs, `Statistics` and `Settings`, at `admin.php?page=wp-stats`.
* BREAKING: Every class is prefixed. The widget class is `WP_Stats_Widget`; placed widgets and their settings are unaffected.
* NEW: A **Blog Statistics** block, `wp-stats/page-stats`, which renders the statistics page in the editor and on the front end. The `[page_stats]` shortcode is unchanged and still supported — the block calls the same code, so the two render identically and a page already using the shortcode needs no change.
* NEW: `wp_stats_sections` and `wp_stats_section_<slug>`, the contract other plugins use to add a block to the statistics page.
* NEW: `wp_stats_capability`, which every capability check goes through, so the read-only statistics tab can be opened to editors without opening the settings.
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
* FIXED: A commenter whose name contains an apostrophe or a quote — "Sinead O'Brien" is enough — got an empty list of comments, under a heading with a stray backslash in it. The `stats_author` query argument was read without removing the slashes WordPress adds to every request value, so the name never matched the row it came from.
* FIXED: Undefined index warning reading the post-password cookie.
* FIXED: The widget's "Statistics To Display" label used the wrong text domain and could not be translated.
* FIXED: The widget's spam line read "Spam Blockeds" in the plural.
* NOTE: A comment attribution was "Posted By" glued to the author and "On" glued to the date, and a link title was "Posted On" glued to a date. Each is one string with placeholders now, so a translation decides the word order. Those msgids changed, so existing translations of them fall back to English until they are retranslated

## Upgrade Notice

### 3.0.0

Requires WordPress 6.8 and PHP 8.2.

**Both admin screens moved, and they are now one screen.** The statistics were at `Dashboard -> WP-Stats` and the settings at `Settings -> Stats`; there is one `WP-Stats` menu now opening one page with two tabs, `Statistics` and `Settings`. Bookmarks to either old screen stop working: the page is `wp-admin/admin.php?page=wp-stats`, and the settings are `wp-admin/admin.php?page=wp-stats&tab=settings`. The menu has no submenu entries any more. Nothing else about the screens changed.

**`wp_stats_capability` still tells the two apart.** It is filtered per tab rather than per screen, with the same `statistics` and `settings` contexts. A tab the current user cannot open is not drawn, and the page itself is registered with whichever of the two capabilities that user holds — so handing out the statistics tab alone works exactly as it did when it was its own menu entry.

**Update all seven WP-Stats plugins together.** WP-Stats, WP-EMail, WP-Polls, WP-PostRatings, WP-PostViews, WP-UserOnline and WP-DownloadManager put their own blocks on the statistics page, and all seven shared one unprefixed `stats_display` row between them. Each plugin keeps that setting in its own settings now, and the shared row is deleted during the upgrade. An older copy of any of the six simply stops appearing on the statistics page until you update it; if a block does not come back, switch it on again in that plugin's own settings.

**Everything WP-Stats stores is renamed and migrated for you.** `stats_url`, `stats_mostlimit` and `stats_display` become one `wp_stats_options` row, and the upgrade marker becomes `wp_stats_version`. The old rows are read once and then deleted, so code calling `get_option( 'stats_url' )` or `get_option( 'stats_display' )` now gets `false`.

**Hook names changed.** `stats_page` is now `wp_stats_page`. The `wp_stats_page_general`, `wp_stats_page_plugins`, `wp_stats_page_recent`, `wp_stats_page_most`, `wp_stats_page_authors`, `wp_stats_page_comments_members`, `wp_stats_page_misc`, `wp_stats_paging_start` and `wp_stats_paging_end` filters, the seven `wp_stats_page_admin_*` filters, and the `stats_display_defaults()` function are all removed. The replacement is one filter, `wp_stats_sections` — see "For Plugin Authors" in the description. Every template tag a theme calls (`get_totalposts()`, `get_recentcomments()`, `stats_page()`, `snippet_text()` and the rest) keeps its name and its arguments.

**Two smaller changes.** The "Plugins Stats" heading no longer appears when no plugin has contributed a block to it, and the front-end stylesheet is scoped to WP-Stats' own markup, so it no longer restyles WP-PageNavi — which also means WP-Stats no longer switches its own styles off when WP-PageNavi is active.
