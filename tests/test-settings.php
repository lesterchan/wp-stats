<?php
/**
 * The Settings API screen.
 *
 * @package WP-Stats
 */

/**
 * @covers WP_Stats_Settings
 */
class WP_Stats_Settings_Test extends WP_Stats_TestCase {

	/**
	 * The display rows, in the order they are registered and drawn.
	 *
	 * @var string[]
	 */
	private $rows = array( 'general', 'recent', 'most', 'authors', 'comment_members', 'misc' );

	/**
	 * Be an administrator, and load the admin API the screen needs.
	 */
	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/admin.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// The screen renders whatever is registered against its page, and in the
		// admin that registration happens on admin_init. Nothing fires admin_init
		// here, so do what it would have done.
		WP_Stats_Settings::register();
	}

	public function test_the_screen_renders() {
		$html = $this->render_screen();

		$this->assertStringContainsString( '<h1>Stats</h1>', $html, 'The screen is headed.' );
		$this->assertStringContainsString( 'option_page', $html, 'settings_fields() must be present or nothing saves.' );
		$this->assertStringContainsString( '_wpnonce', $html, 'And carries a nonce, so the save is not forgeable.' );
		$this->assertMarkupIsClean( $html );
	}

	public function test_the_screen_registers_its_sections_and_fields() {
		global $wp_settings_sections, $wp_settings_fields;

		$sections = $wp_settings_sections[ WP_Stats_Settings::PAGE ];
		$fields   = $wp_settings_fields[ WP_Stats_Settings::PAGE ];

		$this->assertArrayHasKey( WP_Stats_Settings::SECTION_GENERAL, $sections, 'The General section is registered on the screen.' );
		$this->assertArrayHasKey( WP_Stats_Settings::SECTION_DISPLAY, $sections, 'The Display section is registered on the screen.' );

		$this->assertSame(
			array( 'url', 'most_limit' ),
			array_keys( $fields[ WP_Stats_Settings::SECTION_GENERAL ] ),
			'The two scalar settings open the screen.'
		);

		$this->assertSame(
			$this->rows,
			array_keys( $fields[ WP_Stats_Settings::SECTION_DISPLAY ] ),
			'One row per heading on the statistics page, in the same order.'
		);
	}

	/**
	 * The body is whatever the Settings API holds for this page.
	 *
	 * A field registered against the screen from outside has to appear, which
	 * only holds if do_settings_sections() is what draws the body.
	 */
	public function test_the_screen_renders_whatever_is_registered() {
		global $wp_settings_fields;

		add_settings_field(
			'wp_stats_third_party_field',
			'A Third Party Field',
			static function () {
				echo '<p>drawn-by-the-settings-api</p>';
			},
			WP_Stats_Settings::PAGE,
			WP_Stats_Settings::SECTION_DISPLAY
		);

		try {
			$html = $this->render_screen();
		} finally {
			unset( $wp_settings_fields[ WP_Stats_Settings::PAGE ][ WP_Stats_Settings::SECTION_DISPLAY ]['wp_stats_third_party_field'] );
		}

		$this->assertStringContainsString( 'A Third Party Field', $html, 'A field another plugin registered is drawn.' );
		$this->assertStringContainsString( 'drawn-by-the-settings-api', $html, 'Drawn by the Settings API rather than by this plugin walking a list of its own.' );

		// The section heading and the form table are core's, not hand-written.
		$this->assertStringContainsString( 'Type Of Stats To Display', $html, 'The plugin own fields are drawn alongside it.' );
		$this->assertStringContainsString( 'class="form-table"', $html, 'All of them inside the core form table, so the screen looks like core.' );
	}

	/**
	 * Labels that count in the limit follow it.
	 */
	public function test_the_row_labels_track_the_limit() {
		$this->set_limit( 7 );
		WP_Stats_Settings::register();

		$html = $this->render_screen();

		$this->assertStringContainsString( 'Top 7 Recent Stats', $html, 'The stored limit reaches this label.' );
		$this->assertStringContainsString( '7 Most Recent Posts', $html, 'And this one.' );
		$this->assertStringContainsString( 'Top 7 Most/Highest Stats', $html, 'And this one, which is why they are rebuilt per call rather than registered once.' );
	}

	/**
	 * Every checkbox posts into the plugin's own row, under its own key.
	 *
	 * Before 3.0.0 they posted as a flat stats_display[] list of ticked names,
	 * which is what made an unticked box indistinguishable from a box that was
	 * never on the screen, and why the screen needed a hidden field per box to
	 * tell the two apart.
	 */
	public function test_every_toggle_posts_under_its_own_name() {
		$html = $this->render_screen();

		foreach ( array_keys( WP_Stats_Options::display_defaults() ) as $key ) {
			$this->assertStringContainsString(
				'name="wp_stats_options[display][' . $key . ']"',
				$html,
				"The {$key} toggle needs a checkbox of its own."
			);
		}

		$this->assertStringNotContainsString( 'name="stats_display[]"', $html, 'No toggle posts as a bare array member; each has its own name.' );
		$this->assertStringNotContainsString( '[known][]', $html, 'The hidden bookkeeping field is no longer needed.' );
	}

	public function test_a_checkbox_reflects_the_stored_state() {
		$this->set_display( array( 'tags_list' => 1 ) );
		$this->assertMatchesRegularExpression(
			'/id="wp_stats_display_tags_list"[^>]*checked/',
			$this->render_screen(),
			'A stored 1 renders the checkbox checked.'
		);

		$this->set_display( array( 'tags_list' => 0 ) );
		$this->assertDoesNotMatchRegularExpression(
			'/id="wp_stats_display_tags_list"[^>]*checked/',
			$this->render_screen(),
			'A stored 0 renders the checkbox unchecked.'
		);
	}

	/**
	 * A save keeps the scalars and turns unticked boxes off rather than
	 * dropping them, which is what would let them re-inherit their defaults.
	 */
	public function test_sanitize_round_trips_a_save() {
		$saved = WP_Stats_Settings::sanitize(
			array(
				'url'        => 'https://example.com/stats/',
				'most_limit' => '25',
				'display'    => array(
					'total_stats' => '1',
					'tags_list'   => '1',
				),
			)
		);

		$this->assertSame( 'https://example.com/stats/', $saved['url'], 'The URL round-trips through the sanitiser.' );
		$this->assertSame( 25, $saved['most_limit'], 'And the limit.' );
		$this->assertSame( 1, $saved['display']['total_stats'], 'And a ticked toggle.' );
		$this->assertSame( 1, $saved['display']['tags_list'], 'And every ticked toggle, not only the first.' );

		$this->assertArrayHasKey( 'recent_posts', $saved['display'], 'An unticked box must persist as 0, not vanish.' );
		$this->assertSame( 0, $saved['display']['recent_posts'], 'While an unticked one comes back off rather than being left out.' );
	}

	/**
	 * The sanitiser stores the toggles WP-Stats offers and no others.
	 */
	public function test_sanitize_ignores_a_key_the_form_never_offered() {
		$saved = WP_Stats_Settings::sanitize(
			array(
				'url'        => '',
				'most_limit' => '10',
				'display'    => array( 'a_key_we_have_never_heard_of' => '1' ),
			)
		);

		$this->assertSame(
			array_keys( WP_Stats_Options::display_defaults() ),
			array_keys( $saved['display'] ),
			'The stored toggle set is exactly the one the screen draws.'
		);
	}

	public function test_sanitize_clamps_the_limit() {
		$saved = WP_Stats_Settings::sanitize(
			array(
				'url'        => '',
				'most_limit' => '0',
				'display'    => array(),
			)
		);

		$this->assertSame( 1, $saved['most_limit'], 'A limit below one is clamped rather than stored as zero.' );
	}

	/**
	 * Nonsense in must not fatal.
	 *
	 * @dataProvider data_junk_input
	 *
	 * @param mixed $input Whatever arrives.
	 */
	public function test_sanitize_survives_junk( $input ) {
		$saved = WP_Stats_Settings::sanitize( $input );

		$this->assertIsArray( $saved, 'The sanitiser returns an array however junk the input was.' );
		$this->assertArrayHasKey( 'url', $saved, 'The sanitiser rebuilds the url key rather than dropping it.' );
		$this->assertArrayHasKey( 'most_limit', $saved, 'The sanitiser rebuilds the most_limit key rather than dropping it.' );
		$this->assertIsArray( $saved['display'], 'The display key comes back an array, whatever was posted into it.' );
	}

	/**
	 * Shapes the sanitiser can be handed.
	 *
	 * @return array
	 */
	public function data_junk_input() {
		return array(
			'null'             => array( null ),
			'string'           => array( 'nope' ),
			'empty array'      => array( array() ),
			'display a string' => array( array( 'display' => 'nope' ) ),
		);
	}

	/**
	 * Writing the option directly still stores what was asked for.
	 *
	 * The setting is registered in set_up here, exactly as admin_init registers
	 * it in the admin, so the sanitize filter is live for this write.
	 */
	public function test_the_option_can_be_written_while_the_setting_is_registered() {
		$this->set_display(
			array(
				'tags_list'   => 1,
				'total_stats' => 0,
			)
		);

		$this->assertTrue( WP_Stats_Options::display( 'tags_list' ), 'A stored 1 reads back as on.' );
		$this->assertFalse( WP_Stats_Options::display( 'total_stats' ), 'A stored 0 reads back as off.' );
	}

	/**
	 * Saving reports back.
	 *
	 * Core renders settings notices from wp-admin/options-head.php, which only
	 * runs for its own screens; a plugin page goes through admin.php and gets
	 * nothing. Without an explicit settings_errors() the save is silent, which
	 * reads exactly like a save that did not work.
	 */
	public function test_the_screen_reports_a_save() {
		add_settings_error( 'general', 'settings_updated', 'Settings saved.', 'updated' );

		$html = $this->render_screen();

		$this->assertStringContainsString( 'settings_updated', $html, 'The save marker reaches the screen.' );
		$this->assertStringContainsString( 'Settings saved.', $html, 'And the notice it produces is rendered.' );
	}

	public function test_the_setting_is_registered_against_its_own_group() {
		WP_Stats_Settings::register();

		$registered = get_registered_settings();

		$this->assertArrayHasKey( WP_Stats_Options::OPTION, $registered, 'The settings row is registered, so its sanitise callback and default are attached.' );
		$this->assertSame( WP_Stats_Options::OPTION, WP_Stats_Settings::GROUP, 'The group is named after the row it writes.' );
	}

	/**
	 * Render the Settings tab, the way a browser asks for it.
	 *
	 * Through the page rather than through WP_Stats_Settings::render() alone:
	 * the wrapper, the heading, the tab strip and settings_errors() belong to
	 * the page now, and a test that skipped it would stop noticing when they
	 * went missing.
	 *
	 * @return string
	 */
	private function render_screen() {
		$_GET['tab'] = 'settings';

		return $this->render( array( 'WP_Stats_Admin', 'render' ) );
	}
}
