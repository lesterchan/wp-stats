<?php
/**
 * Plugin Name: WP-Stats
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Display your WordPress blog statistics. Ranging from general total statistics, some of my plugins statistics and top 10 statistics.
 * Version: 3.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-stats
 * Domain Path: /languages
 *
 * @package WP-Stats
 */

/*
	Copyright 2026 Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Version.
define( 'WP_STATS_VERSION', '3.0.0' );
define( 'WP_STATS_MAIN_FILE', __FILE__ );

// Classes. Required at file load because the activation hook and the template
// tags are both reached before any action fires.
require_once __DIR__ . '/includes/class-stats-options.php';
require_once __DIR__ . '/includes/class-stats-query.php';
require_once __DIR__ . '/includes/class-stats-display.php';
require_once __DIR__ . '/includes/class-stats-page.php';
require_once __DIR__ . '/includes/class-stats-widget.php';
require_once __DIR__ . '/includes/class-stats-admin.php';
require_once __DIR__ . '/includes/class-stats-settings.php';
require_once __DIR__ . '/includes/class-stats.php';
require_once __DIR__ . '/includes/template-tags.php';

Stats::get_instance();
