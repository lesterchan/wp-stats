# WP-Stats
Contributors: GamerZ  
Donate link: http://leterchan.net/site/donation/  
Tags: stat, stats, statistics, wp-stats, wp-stat, top, most, widget, popular, information  
Requires at least: 2.8  
Tested up to: 3.9  
Stable tag: trunk  

Display your WordPress blog statistics. Ranging from general total statistics, some of my plugins statistics and top 10 statistics.

## Description

### Development
* [https://github.com/lesterchan/wp-stats](https://github.com/lesterchan/wp-stats "https://github.com/lesterchan/wp-stats")

### Translations
* [http://dev.wp-plugins.org/browser/wp-stats/i18n/](http://dev.wp-plugins.org/browser/wp-stats/i18n/ "http://dev.wp-plugins.org/browser/wp-stats/i18n/")

### Credits
* __ngetext() by [Anna Ozeritskaya](http://hweia.ru/ "Anna Ozeritskaya")
* Right To Left Language Support by [Kambiz R. Khojasteh](http://persian-programming.com/ "Kambiz R. Khojasteh")

### Donations
* I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appericiate it. If not feel free to use it without any obligations.

## Changelog

### Version 2.50 (01-06-2009)
* NEW: Works For WordPress 2.8
* NEW: Update PageNavi Function Names
* NEW: Added Most Commented Pages
* NEW: Use _n() Instead Of __ngettext() And _n_noop() Instead Of __ngettext_noop()
* NEW: Uses New Widget Class From WordPress
* NEW: Merge Widget Code To wp-stats.php And Remove wp-stats-widget.php
* FIXED: Uses $_SERVER['PHP_SELF'] With plugin_basename(__FILE__) Instead Of Just $_SERVER['REQUEST_URI']
* FIXED: Recent Comments Now Include Comments Posted In Pages

### Version 2.40 (12-12-2008)
* NEW: Works For WordPress 2.7 Only
* NEW: Right To Left Language Support by Kambiz R. Khojasteh
* NEW: Uses wp_register_style(), wp_print_styles(), plugins_url() And site_url()
* FIXED: Comment Will Not Work With HTML Code by Kambiz R. Khojasteh

### Version 2.31 (16-07-2008)
* NEW: Works For WordPress 2.6
* NEW: Added stats-css.css For Use In Comment Author Stats Paging
* NEW: Better Translation Using __ngetext() by Anna Ozeritskaya
* FIXED: Changed Default Threshold For Comment Author Stats To 5 Comments
* FIXED: Renamed Widget To WP-Stats To Advoid Conflict With FireStats

### Version 2.30 (01-06-2008)
* NEW: Works With WordPress 2.5 Only
* NEW: Uses Shortcode API
* NEW: Uses /wp-stats/ Folder Instead Of /stats/
* NEW: Uses wp-stats.php Instead Of stats.php
* NEW: Uses wp-stats-widget.php Instead Of stats-widget.php

### Version 2.20 (01-10-2007)
* NEW: Works For WordPress 2.3 Only
* NEW: Added Total Tags And Tag Lists
* NEW: Added Total Post Categories And Total Link Categories Stats
* NEW: Ability To Uninstall WP-Stats
* NEW: Added Filters For WP-Stats So That Plugins Can Plug Into WP-Stats
* NEW: Removed All Plugins Stats (Plugins Are Plugged In Via Filters Now)
* FIXED: Recent Post Authors Are Now Displayed By Their Display Name Instead Of Nickname

### Version 2.11 (01-06-2007)
* NEW: Post Categories Are Now Hierarchical
* NEW: Used WordPress's Date/Time Format
* FIXED: Missing WHERE in get_authorsstats()
* FIXED: Password Protected Post Comments Viewable On Comment Author Stats
* FIXED: Wrong URL For Page Under Certain Stat Posts Listing

### Version 2.10 (01-02-2007)
* NEW: Works For WordPress 2.1 Only

### Version 2.06 (02-01-2007)
* NEW: Localize WP-Stats

### Version 2.05 (01-10-2006)
* NEW: Allow You To Choose Which Type Of Stats To Display Under Options -> Stats
* NEW: Stats Is Now Embedded Into A Page, And Hence No More Integrating Of Stats Page (Removed wp-stats.php)
* NEW: Added Widget Title Option To WP-Stats Widget

### Version 2.04 (01-07-2006)
* NEW: Added Highest Rated Post If WP-PostRatings Is Installed
* NEW: Remove My Other Plugins Stats Functions Inside stats.php
* NEW: WP-Stats Files Are Now In wp-content/plugins/stats/ Folder
* NEW: You Can Now Place Selected Stats On The Sidebar As A Widget
* NEW: Code That WP-Stats Generated Is XHTML 1.0 Transitional

### Version 2.03 (01-04-2006)
* FIXED: Hardcoded Table Name For Ratings Stats
* FIXED: Removed Table From Comment's Author Page Navigation
* FIXED: Password Protected Post's Comments Viewable

### Version 2.02 (01-03-2006)
* NEW: Added WP-PostRatings, WP-PostViews, WP-UserOnline Stats
* NEW: Slightly Changed The Grouping Of Stats
* NEW: WP-Stats Now Can Be Found ALso On The Dashboard
* NEW: Added Author Status
* FIXED: Moved All Stats Functions To stats.php

### Version 2.01 (01-02-2006)
* NEW: Display EMail Stats If You Have WP-EMail Installed
* NEW: Display N/A If There Are No Stats For That Particular Stats
* FIXED: Page Permalink Issues
* FIXED: Error If User Has Not Posted Comment
* FIXED: Page Navigation if There Are 0 or 1 Page
* FIXED: Exploit $comment_author

### Version 2.00 (01-01-2006)
* NEW: Compatible With WordPress 2.0 Only
* NEW: Display Poll Stats If You Have WP-Polls Installed

## Installation

1. Open `wp-content/plugins` Folder
2. Put: `Folder: wp-stats`
3. Activate `WP-Stats` Plugin
4. Go to `WP-Admin -> Settings -> Stats` to configure WP-Stats

### Create Stats Page
1. Go to 'WP-Admin -> Pages -> Add New'
2. Type any title you like in the page's title area
3. If you ARE using nice permalinks, after typing the title, WordPress will generate the permalink to the page. You will see an 'Edit' link just beside the permalink.
4. Click 'Edit' and type in 'stats' in the text field (without the quotes) and click 'Save'.
5. Type `[page_stats]` in the page's content area
6. Click 'Publish'
7. If you ARE NOT using nice permalinks, you need to go to `WP-Admin -> Settings -> Stats` and under 'Stats URL', you need to fill in the URL to the Stats Page you created above.

### General Usage (With Widget)
1. Go to `WP-Admin -> Appearance -> Widgets`
2. The widget name is `Stats`.

## Upgrading

1. Deactivate `WP-Stats` Plugin
2. Open `wp-content/plugins` Folder
3. Put/Overwrite: `Folder: wp-stats`
4. Activate `WP-Stats` Plugin

## Upgrade Notice

N/A

## Screenshots

1. Admin - Dashboard
2. Admin - Options
3. Admin - Widget
4. Stats Page
5. Stats Sidebar

## Frequently Asked Questions
