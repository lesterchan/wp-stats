<?php
/**
 * Plugin Name: WP-Stats
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Display your WordPress blog statistics. Ranging from general total statistics, some of my plugins statistics and top 10 statistics.
 * Version: 3.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
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
	Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)

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
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*/

defined( 'ABSPATH' ) || exit;

/**
 * WP-Stats version. The last-run value is kept in the wp_stats_version row.
 */
define( 'WP_STATS_VERSION', '3.0.0' );

/**
 * Schema counter. Bumped only when the stored rows need reshaping.
 */
define( 'WP_STATS_DB_VERSION', '1' );

/**
 * WP-Stats slug, which is also the text domain.
 */
define( 'WP_STATS_SLUG', 'wp-stats' );

/**
 * WP-Stats main file.
 */
define( 'WP_STATS_MAIN_FILE', __FILE__ );

/**
 * WP-Stats directory, with a trailing slash.
 */
define( 'WP_STATS_DIR', plugin_dir_path( __FILE__ ) );

/**
 * WP-Stats URL, with a trailing slash.
 */
define( 'WP_STATS_URL', plugin_dir_url( __FILE__ ) );

/*
 * Required at file load rather than from a hook: the activation hook and the
 * template tags are both reached before any action fires.
 */
require_once WP_STATS_DIR . 'includes/class-wp-stats-options.php';
require_once WP_STATS_DIR . 'includes/class-wp-stats-query.php';
require_once WP_STATS_DIR . 'includes/class-wp-stats-display.php';
require_once WP_STATS_DIR . 'includes/class-wp-stats-page.php';
require_once WP_STATS_DIR . 'includes/class-wp-stats-blocks.php';
require_once WP_STATS_DIR . 'includes/class-wp-stats-widget.php';
require_once WP_STATS_DIR . 'includes/class-wp-stats-admin.php';
require_once WP_STATS_DIR . 'includes/class-wp-stats-settings.php';
require_once WP_STATS_DIR . 'includes/template-tags.php';
require_once WP_STATS_DIR . 'includes/class-wp-stats.php';

WP_Stats::get_instance();
