<?php
/**
 * WP-Stats class-wp-stats-admin.php
 *
 * @package WP-Stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Dashboard -> WP-Stats screen.
 *
 * @since 3.0.0
 */
class WP_Stats_Admin {

	/**
	 * Menu slug, and the slug of the statistics screen itself.
	 *
	 * Before 3.0.0 this was the file path 'wp-stats/wp-stats.php'.
	 */
	const PAGE = 'wp-stats';

	/**
	 * Capability every WP-Stats screen requires.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Hook the screen up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
	}

	/**
	 * The capability a screen requires, filtered.
	 *
	 * Every capability check in the plugin goes through here, so a site that
	 * wants to hand the statistics screen to editors has one place to say so
	 * and cannot accidentally open the settings screen at the same time.
	 *
	 * @since 3.0.0
	 *
	 * @param string $context Which screen is asking: 'statistics' or 'settings'.
	 * @return string
	 */
	public static function capability( $context ) {
		/**
		 * Filter the capability a WP-Stats screen requires.
		 *
		 * @since 3.0.0
		 *
		 * @param string $capability Capability name.
		 * @param string $context    Which screen is asking.
		 */
		return apply_filters( 'wp_stats_capability', self::CAPABILITY, $context );
	}

	/**
	 * Add the Dashboard submenu entry.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_submenu_page(
			'index.php',
			__( 'WP-Stats', 'wp-stats' ),
			__( 'WP-Stats', 'wp-stats' ),
			self::capability( 'statistics' ),
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Render the stats page inside the admin.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::capability( 'statistics' ) ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-stats' ) );
		}

		echo '<div class="wrap">';
		// WP_Stats_Page::render() assembles escaped markup, and third-party filters
		// deliberately contribute HTML sections to it.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo WP_Stats_Page::render();
		echo '</div>';
	}
}
