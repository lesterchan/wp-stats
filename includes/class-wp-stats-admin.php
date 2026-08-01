<?php
/**
 * The WP-Stats menu, and the one page under it.
 *
 * One top-level menu with a single page, and that page carries two tabs:
 * Statistics first, because it is what the plugin is opened to look at, then
 * Settings. Until 3.0.0 the two screens lived in different menus entirely - the
 * statistics under Dashboard, the settings under Settings - which is the
 * scattering the house rule exists to stop. They were briefly two submenu
 * entries; they are tabs now, so WP-Stats reads the same way as WP-UserOnline
 * and WP-Ban.
 *
 * @package WP-Stats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the menu, draws the tab strip and renders the statistics tab.
 *
 * @since 3.0.0
 */
class WP_Stats_Admin {

	/**
	 * Menu slug, and the slug of the one page the menu opens.
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
	 * The capability a tab requires, filtered.
	 *
	 * Every capability check in the plugin goes through here, so a site that
	 * wants to hand the read-only statistics tab to editors has one place to
	 * say so and cannot open the settings tab by accident at the same time.
	 *
	 * @since 3.0.0
	 *
	 * @param string $context Which tab is asking: 'statistics' or 'settings'.
	 * @return string
	 */
	public static function capability( $context ) {
		/**
		 * Filter the capability a WP-Stats tab requires.
		 *
		 * @since 3.0.0
		 *
		 * @param string $capability Capability name.
		 * @param string $context    Which tab is asking.
		 */
		return apply_filters( 'wp_stats_capability', self::CAPABILITY, $context );
	}

	/**
	 * The capability the page itself is registered with.
	 *
	 * The two tabs used to be two menu entries, each gated by its own
	 * capability. One page cannot be registered with two, and taking either one
	 * would be wrong in a different way: the page's capability is what core
	 * enforces before the callback runs, so registering the stricter one hides a
	 * tab the site meant to hand out, and registering one tab's capability
	 * unconditionally would open the other to anyone holding it.
	 *
	 * So the page takes whichever of the two the current user actually holds,
	 * and each tab checks its own before it draws. The page is reachable by
	 * anyone who can see at least one tab, and no tab is reachable by anyone who
	 * could not see it when it was a separate screen.
	 *
	 * @return string
	 */
	public static function page_capability() {
		$capabilities = array();

		foreach ( array_keys( self::tabs() ) as $tab ) {
			$capabilities[] = self::capability( $tab );
		}

		foreach ( $capabilities as $capability ) {
			if ( current_user_can( $capability ) ) {
				return $capability;
			}
		}

		return reset( $capabilities );
	}

	/**
	 * The tabs on the page, in the order they are drawn.
	 *
	 * Statistics first: it is what somebody opens WP-Stats to look at, and
	 * Settings is last everywhere in this family of plugins.
	 *
	 * @return array<string,string> Tab slug => label.
	 */
	public static function tabs() {
		return array(
			'statistics' => __( 'Statistics', 'wp-stats' ),
			'settings'   => __( 'Settings', 'wp-stats' ),
		);
	}

	/**
	 * Which tab is being drawn.
	 *
	 * An unknown tab, or none at all, opens the first tab the current user may
	 * actually see - so a site that hands out only one of the two lands on it
	 * rather than on a permissions error.
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tabs = self::tabs();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Choosing which tab to draw; nothing is read from the request beyond that and nothing is written.
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( isset( $tabs[ $requested ] ) ) {
			return $requested;
		}

		foreach ( array_keys( $tabs ) as $tab ) {
			if ( current_user_can( self::capability( $tab ) ) ) {
				return $tab;
			}
		}

		return 'statistics';
	}

	/**
	 * The URL of a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Add the menu and the one page under it.
	 *
	 * No submenu entries: the page carries its own tabs, and settings never
	 * span more than one page.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_menu_page(
			__( 'Stats', 'wp-stats' ),
			__( 'WP-Stats', 'wp-stats' ),
			self::page_capability(),
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-chart-area'
		);
	}

	/**
	 * Render the page: the tab strip, and whichever tab was asked for.
	 *
	 * @return void
	 */
	public static function render() {
		$tab = self::current_tab();

		if ( ! current_user_can( self::capability( $tab ) ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-stats' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Stats', 'wp-stats' ) . '</h1>';

		/*
		 * Core only calls this from wp-admin/options-head.php, which runs for
		 * its own settings screens. A plugin page is dispatched by admin.php
		 * instead, so without this the save redirect lands back here with no
		 * confirmation at all. It is printed for the page rather than for the
		 * settings tab because a save can return to either.
		 */
		settings_errors();

		self::render_tabs( $tab );

		if ( 'settings' === $tab ) {
			WP_Stats_Settings::render();
		} else {
			self::render_statistics();
		}

		echo '</div>';
	}

	/**
	 * The tab strip.
	 *
	 * A tab the current user cannot open is not drawn: an entry that dies when
	 * it is clicked is worse than no entry at all.
	 *
	 * @param string $current Slug of the tab being drawn.
	 * @return void
	 */
	protected static function render_tabs( $current ) {
		echo '<nav class="nav-tab-wrapper">';

		foreach ( self::tabs() as $slug => $label ) {
			if ( ! current_user_can( self::capability( $slug ) ) ) {
				continue;
			}

			printf(
				'<a href="%1$s" class="%2$s">%3$s</a>',
				esc_url( self::tab_url( $slug ) ),
				esc_attr( $slug === $current ? 'nav-tab nav-tab-active' : 'nav-tab' ),
				esc_html( $label )
			);
		}

		echo '</nav>';
	}

	/**
	 * The Statistics tab: a read-only report, and no form.
	 *
	 * The page is assembled as a string for the [page_stats] shortcode's sake,
	 * and contributed sections are third-party markup, so it goes out through
	 * wp_kses_post() rather than being trusted wholesale.
	 *
	 * @return void
	 */
	protected static function render_statistics() {
		echo wp_kses_post( WP_Stats_Page::render() );
	}
}
