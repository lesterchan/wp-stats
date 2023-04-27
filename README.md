# WP-Stats
Contributors: GamerZ  
Donate link: https://leterchan.net/site/donation/  
Tags: stat, stats, statistics, wp-stats, wp-stat, top, most, widget, popular, information  
Requires at least: 5.5  
Tested up to: 6.2  
Stable tag: 2.56  

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

### Build Status
[![Build Status](https://travis-ci.org/lesterchan/wp-stats.svg?branch=master)](https://travis-ci.org/lesterchan/wp-stats)

### Development
[https://github.com/lesterchan/wp-stats](https://github.com/lesterchan/wp-stats "https://github.com/lesterchan/wp-stats")

### Translations
[http://dev.wp-plugins.org/browser/wp-stats/i18n/](http://dev.wp-plugins.org/browser/wp-stats/i18n/ "http://dev.wp-plugins.org/browser/wp-stats/i18n/")

### Credits
* Plugin icon by [SimpleIcon](http://www.simpleicon.com) from [Flaticon](http://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog
### Version 2.56
* NEW: WordPress 5.5 only because of comment_type changes

### Version 2.55
* FIXED: Notices

### Version 2.54
* FIXED: Akismet_Admin class not found

### Version 2.53
* FIXED: Notices in Widget Constructor for WordPress 4.3

### Version 2.52
* FIXED: Added nonce field and esc_url() to fix XSS. Props HSASec-Team.

### Version 2.51
* NEW: Supports WordPress Multisite Network Activation
* NEW: Uses WordPress native uninstall.php

### Version 2.50 (01-06-2009)
* NEW: Works For WordPress 2.8
* NEW: Update PageNavi Function Names
* NEW: Added Most Commented Pages
* NEW: Use _n() Instead Of __ngettext() And _n_noop() Instead Of __ngettext_noop()
* NEW: Uses New Widget Class From WordPress
* NEW: Merge Widget Code To wp-stats.php And Remove wp-stats-widget.php
* FIXED: Uses $_SERVER['PHP_SELF'] With plugin_basename(__FILE__) Instead Of Just $_SERVER['REQUEST_URI']
* FIXED: Recent Comments Now Include Comments Posted In Pages

## Upgrade Notice

N/A

## Screenshots

1. Admin - Dashboard
2. Admin - Options
3. Admin - Widget
4. Stats Page
5. Stats Sidebar

## Frequently Asked Questions
