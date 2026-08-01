<?php
/**
 * The menu, the page it opens and the two tabs on it.
 *
 * WP-Stats had two submenu entries until this pass and has one page with two
 * tabs now, so where the screens are is as much a test as what they contain: a
 * second submenu entry reappearing, or a tab losing its own capability check,
 * are both silent.
 *
 * @package WP-Stats
 */

/**
 * @covers WP_Stats_Admin
 */
class WP_Stats_Admin_Test extends WP_Stats_TestCase {

	/**
	 * Be an administrator, with the admin API loaded and the menu globals
	 * empty: both are plain globals that no transaction rolls back.
	 */
	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/admin.php';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$GLOBALS['menu']    = array();
		$GLOBALS['submenu'] = array();

		// Both are process-wide too, and an earlier file's registrations would
		// make "every field is on the Settings tab" answer for that file.
		$GLOBALS['wp_settings_sections'] = array();
		$GLOBALS['wp_settings_fields']   = array();

		WP_Stats_Settings::register();
	}

	public function test_the_menu_is_one_top_level_entry_and_no_submenus() {
		WP_Stats_Admin::add_page();

		$slugs = wp_list_pluck( $GLOBALS['menu'], 2 );

		$this->assertContains( WP_Stats_Admin::PAGE, $slugs, 'WP-Stats keeps its top-level menu.' );
		$this->assertArrayNotHasKey(
			WP_Stats_Admin::PAGE,
			$GLOBALS['submenu'],
			'The two screens are tabs now, so the menu has no submenu entries at all.'
		);
	}

	/**
	 * The sidebar says the plugin's name; the page says what the screen is.
	 */
	public function test_the_menu_is_titled_after_the_plugin_and_the_page_after_the_screen() {
		WP_Stats_Admin::add_page();

		foreach ( $GLOBALS['menu'] as $item ) {
			if ( WP_Stats_Admin::PAGE === $item[2] ) {
				$this->assertSame( 'WP-Stats', $item[0], 'The menu title carries the prefix.' );
				$this->assertSame( 'Stats', $item[3], 'The page title does not.' );

				return;
			}
		}

		$this->fail( 'The WP-Stats menu was not registered.' );
	}

	public function test_the_tabs_are_statistics_then_settings() {
		$this->assertSame(
			array( 'statistics', 'settings' ),
			array_keys( WP_Stats_Admin::tabs() ),
			'Statistics first: it is what the plugin is opened to look at, and Settings is always last.'
		);

		$this->assertSame( 'Statistics', WP_Stats_Admin::tabs()['statistics'], 'The tab is named for the screen, not "WP-Stats Statistics".' );
		$this->assertSame( 'Settings', WP_Stats_Admin::tabs()['settings'] );
	}

	public function test_the_default_tab_is_statistics() {
		$this->assertSame( 'statistics', WP_Stats_Admin::current_tab() );
	}

	public function test_a_tab_can_be_asked_for_by_name() {
		$_GET['tab'] = 'settings';

		$this->assertSame( 'settings', WP_Stats_Admin::current_tab() );
	}

	public function test_an_unknown_tab_falls_back_rather_than_drawing_nothing() {
		$_GET['tab'] = 'no-such-tab';

		$this->assertSame( 'statistics', WP_Stats_Admin::current_tab() );
	}

	/**
	 * One page, one heading, both tabs on it.
	 */
	public function test_the_page_draws_a_tab_strip() {
		$html = $this->render_page();

		$this->assertSame( 1, substr_count( $html, '<h1>' ), 'One h1 per screen.' );
		$this->assertStringContainsString( 'nav-tab-wrapper', $html );
		$this->assertStringContainsString( 'page=wp-stats&#038;tab=statistics', $html );
		$this->assertStringContainsString( 'page=wp-stats&#038;tab=settings', $html );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * @dataProvider data_tabs
	 *
	 * @param string $tab Tab slug.
	 */
	public function test_the_tab_being_drawn_is_the_active_one( $tab ) {
		$_GET['tab'] = $tab;

		$html = $this->render_page();

		$this->assertMatchesRegularExpression(
			'/tab=' . $tab . '"\s+class="nav-tab nav-tab-active"/',
			$html,
			"The {$tab} tab must be marked active while it is being drawn."
		);
		$this->assertSame( 1, substr_count( $html, 'nav-tab-active' ), 'Exactly one tab is active.' );
	}

	/**
	 * The tab slugs.
	 *
	 * @return array
	 */
	public function data_tabs() {
		return array(
			'statistics' => array( 'statistics' ),
			'settings'   => array( 'settings' ),
		);
	}

	/**
	 * The Statistics tab is a report, not a form.
	 *
	 * It reads nothing back and writes nothing, so a form posting to options.php
	 * on it would be a settings save with no settings on it.
	 */
	public function test_the_statistics_tab_posts_nowhere() {
		$_GET['tab'] = 'statistics';

		$html = $this->render_page();

		$this->assertStringContainsString( 'id="GeneralStats"', $html );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringNotContainsString( 'options.php', $html );
	}

	public function test_the_settings_tab_carries_the_form() {
		$_GET['tab'] = 'settings';

		$html = $this->render_page();

		$this->assertStringContainsString( 'options.php', $html );
		$this->assertStringContainsString( 'option_page', $html, 'settings_fields() must be present or nothing saves.' );
		$this->assertStringNotContainsString( 'id="GeneralStats"', $html, 'The report belongs to the other tab.' );
	}

	/**
	 * A save returns to the tab it was submitted from.
	 *
	 * Core's options.php redirects to wp_get_referer(), which is whatever the
	 * last _wp_http_referer field on the form said.
	 */
	public function test_the_settings_form_carries_the_tab_through_a_save() {
		$_GET['tab'] = 'settings';

		$this->assertStringContainsString(
			'name="_wp_http_referer" value="' . esc_url( WP_Stats_Admin::tab_url( 'settings' ) ) . '"',
			$this->render_page()
		);
	}

	/**
	 * Notices print on both tabs.
	 *
	 * Core renders settings notices from wp-admin/options-head.php, which only
	 * runs for its own screens; a plugin page goes through admin.php and gets
	 * nothing. A save can return to either tab, so both have to report it.
	 *
	 * @dataProvider data_tabs
	 *
	 * @param string $tab Tab slug.
	 */
	public function test_a_save_is_reported_on_either_tab( $tab ) {
		$_GET['tab'] = $tab;

		add_settings_error( 'general', 'settings_updated', 'Settings saved.', 'updated' );

		$html = $this->render_page();

		$this->assertStringContainsString( 'settings_updated', $html );
		$this->assertStringContainsString( 'Settings saved.', $html );
	}

	/**
	 * The page is registered with a capability the reader actually holds.
	 *
	 * One page cannot be registered with the two capabilities its tabs are
	 * gated by, and core enforces the registered one before the callback runs.
	 * An editor handed the statistics tab has to be able to open the page at
	 * all, or the tab was not handed over.
	 */
	public function test_the_page_capability_is_whichever_tab_the_reader_can_open() {
		$this->assertSame( 'manage_options', WP_Stats_Admin::page_capability(), 'Both tabs ship on manage_options.' );

		$this->open_statistics_to_editors();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( 'edit_pages', WP_Stats_Admin::page_capability() );
	}

	/**
	 * Loosening one tab must not open the other.
	 */
	public function test_a_tab_the_reader_cannot_open_is_neither_drawn_nor_reachable() {
		$this->open_statistics_to_editors();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$html = $this->render_page();

		$this->assertStringContainsString( 'tab=statistics', $html );
		$this->assertStringNotContainsString( 'tab=settings', $html, 'A tab that dies when it is clicked is worse than no tab.' );
		$this->assertStringNotContainsString( 'options.php', $html );

		$_GET['tab'] = 'settings';

		$this->expectException( 'WPDieException' );
		WP_Stats_Admin::render();
	}

	/**
	 * Somebody who can open neither tab gets the page's own refusal.
	 */
	public function test_a_subscriber_is_turned_away() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->expectException( 'WPDieException' );
		WP_Stats_Admin::render();
	}

	/**
	 * Every field is on the Settings tab, which is what makes the sanitizer
	 * honest.
	 *
	 * The sanitize callback register_setting() takes is handed only what the
	 * submitting form posted. WP-Stats' returns that, which is safe only while one
	 * tab owns every field. The day a field moves to another tab, this fails -
	 * and the sanitizer has to start merging over the stored value instead.
	 */
	public function test_every_registered_field_belongs_to_the_settings_tab() {
		global $wp_settings_fields, $wp_settings_sections;

		$this->assertSame(
			array( WP_Stats_Settings::PAGE ),
			array_keys( $wp_settings_fields ),
			'A field registered against another tab would be dropped by the sanitizer on every save of this one.'
		);
		$this->assertSame( array( WP_Stats_Settings::PAGE ), array_keys( $wp_settings_sections ) );
	}

	/**
	 * Hand the statistics tab, and only that tab, to editors.
	 *
	 * @return void
	 */
	private function open_statistics_to_editors() {
		add_filter(
			'wp_stats_capability',
			static function ( $capability, $context ) {
				return 'statistics' === $context ? 'edit_pages' : $capability;
			},
			10,
			2
		);
	}

	/**
	 * Render the page.
	 *
	 * @return string
	 */
	private function render_page() {
		return $this->render( array( 'WP_Stats_Admin', 'render' ) );
	}
}
