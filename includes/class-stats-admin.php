<?php
/**
 * WP-Stats class-stats-admin.php
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
class Stats_Admin {

	/**
	 * Menu slug.
	 *
	 * Before 3.0.0 this was the file path 'wp-stats/wp-stats.php'.
	 */
	const SLUG = 'wp-stats';

	/**
	 * Hook the screen up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
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
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Render the stats page inside the admin.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-stats' ) );
		}

		echo '<div class="wrap">';
		// Stats_Page::render() assembles escaped markup, and third-party filters
		// deliberately contribute HTML sections to it.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Stats_Page::render();
		echo '</div>';
	}
}
