<?php
/**
 * The WP-Stats menu, and the statistics screen under it.
 *
 * One top-level menu with two entries: the statistics themselves, and Settings
 * last. Until 3.0.0 the two screens lived in different menus entirely - the
 * statistics under Dashboard, the settings under Settings - which is the
 * scattering the house rule exists to stop. The statistics screen is the
 * plugin's own surface rather than a settings page, so it is not a submenu of
 * Settings either.
 *
 * @package WP-Stats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the menu and renders the statistics screen.
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
	 * Hook the screens up.
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
	 * wants to hand the read-only statistics screen to editors has one place to
	 * say so and cannot open the settings screen by accident at the same time.
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
	 * Add the menu and its two entries.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_menu_page(
			__( 'WP-Stats', 'wp-stats' ),
			__( 'WP-Stats', 'wp-stats' ),
			self::capability( 'statistics' ),
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-chart-bar'
		);

		// add_menu_page() creates a first submenu labelled after the menu, so
		// this one only exists to call it what it is.
		add_submenu_page(
			self::PAGE,
			__( 'Statistics', 'wp-stats' ),
			__( 'Statistics', 'wp-stats' ),
			self::capability( 'statistics' ),
			self::PAGE,
			array( __CLASS__, 'render' )
		);

		add_submenu_page(
			self::PAGE,
			__( 'Settings', 'wp-stats' ),
			__( 'Settings', 'wp-stats' ),
			self::capability( 'settings' ),
			WP_Stats_Settings::PAGE,
			array( 'WP_Stats_Settings', 'render' )
		);
	}

	/**
	 * Render the statistics screen.
	 *
	 * The page is assembled as a string for the [page_stats] shortcode's sake,
	 * and contributed sections are third-party markup, so it goes out through
	 * wp_kses_post() rather than being trusted wholesale.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::capability( 'statistics' ) ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-stats' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Stats', 'wp-stats' ) . '</h1>';
		echo wp_kses_post( WP_Stats_Page::render() );
		echo '</div>';
	}
}
