<?php
/**
 * The Settings API screen and the companion-plugin contract.
 *
 * @package WP-Stats
 */

/**
 * WP_Stats_Settings and the wp_stats_* helpers.
 */
class WP_Stats_Settings_Test extends WP_Stats_TestCase {

	/**
	 * Admin filters other plugins hook, in the order they fire.
	 *
	 * @var string[]
	 */
	private $admin_filters = array( 'general', 'plugins', 'recent', 'most', 'authors', 'comments_members', 'misc' );

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

	/**
	 * The screen renders cleanly.
	 */
	public function test_the_screen_renders() {
		$html = $this->render_screen();

		$this->assertStringContainsString( 'Stats Options', $html );
		$this->assertStringContainsString( 'option_page', $html, 'settings_fields() must be present or nothing saves.' );
		$this->assertStringContainsString( '_wpnonce', $html );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * The screen is assembled from registered sections and fields.
	 */
	public function test_the_screen_registers_sections_and_fields() {
		global $wp_settings_sections, $wp_settings_fields;

		$sections = $wp_settings_sections[ WP_Stats_Settings::PAGE ];
		$fields   = $wp_settings_fields[ WP_Stats_Settings::PAGE ];

		$this->assertArrayHasKey( WP_Stats_Settings::SECTION_GENERAL, $sections );
		$this->assertArrayHasKey( WP_Stats_Settings::SECTION_DISPLAY, $sections );

		$this->assertArrayHasKey( 'stats_url', $fields[ WP_Stats_Settings::SECTION_GENERAL ] );
		$this->assertArrayHasKey( 'stats_mostlimit', $fields[ WP_Stats_Settings::SECTION_GENERAL ] );

		// One row per admin filter, registered in the order they fire.
		$this->assertSame(
			array_map(
				static function ( $slug ) {
					return 'wp_stats_display_' . $slug;
				},
				$this->admin_filters
			),
			array_keys( $fields[ WP_Stats_Settings::SECTION_DISPLAY ] )
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

		$this->assertStringContainsString( 'A Third Party Field', $html );
		$this->assertStringContainsString( 'drawn-by-the-settings-api', $html );

		// The section heading and form table are core's, not hand-written.
		$this->assertStringContainsString( 'Type Of Stats To Display', $html );
		$this->assertStringContainsString( 'class="form-table"', $html );
	}

	/**
	 * Labels that count in the limit follow it.
	 */
	public function test_the_row_labels_track_the_limit() {
		$this->set_limit( 7 );
		WP_Stats_Settings::register();

		$html = $this->render_screen();

		$this->assertStringContainsString( 'Top 7 Recent Stats', $html );
		$this->assertStringContainsString( '7 Most Recent Posts', $html );
		$this->assertStringContainsString( 'Top 7 Most/Highest Stats', $html );
	}

	/**
	 * Every admin filter fires, in document order.
	 */
	public function test_the_admin_filters_fire_in_order() {
		$fired = array();

		foreach ( $this->admin_filters as $slug ) {
			add_filter(
				"wp_stats_page_admin_$slug",
				static function ( $content ) use ( $slug, &$fired ) {
					$fired[] = $slug;
					return $content . '<label for="hook-' . $slug . '">x</label>';
				}
			);
		}

		$html = $this->render_screen();

		$this->assertSame( $this->admin_filters, $fired );

		foreach ( $this->admin_filters as $slug ) {
			$this->assertStringContainsString( 'hook-' . $slug, $html );
		}
	}

	/**
	 * Every checkbox posts under the consolidated option name.
	 *
	 * A companion still emitting the pre-3.0.0 stats_display[] name would save
	 * nothing at all.
	 */
	public function test_checkboxes_post_to_the_consolidated_option() {
		$html = $this->render_screen();

		$this->assertGreaterThan( 5, substr_count( $html, 'name="stats_options[display][]"' ) );
		$this->assertStringNotContainsString( 'name="stats_display[]"', $html );
	}

	/**
	 * The helper builds a checkbox reflecting the stored state.
	 */
	public function test_the_checkbox_helper_reflects_state() {
		$this->set_display( array( 'tags_list' => 1 ) );
		$on = wp_stats_checkbox( 'tags_list', 'Tags List' );

		$this->assertStringContainsString( 'name="stats_options[display][]"', $on );
		$this->assertStringContainsString( 'value="tags_list"', $on );
		$this->assertStringContainsString( 'id="wpstats_tags_list"', $on );
		$this->assertStringContainsString( 'checked', $on );

		$this->set_display( array( 'tags_list' => 0 ) );
		$this->assertStringNotContainsString( 'checked', wp_stats_checkbox( 'tags_list', 'Tags List' ) );
	}

	/**
	 * A save keeps the scalars and turns unticked boxes off rather than
	 * dropping them.
	 */
	public function test_sanitize_round_trips_a_save() {
		$saved = WP_Stats_Settings::sanitize(
			array(
				'url'        => 'https://example.com/stats/',
				'most_limit' => '25',
				'display'    => array( 'total_stats', 'tags_list' ),
			)
		);

		$this->assertSame( 'https://example.com/stats/', $saved['url'] );
		$this->assertSame( 25, $saved['most_limit'] );
		$this->assertSame( 1, (int) $saved['display']['total_stats'] );
		$this->assertSame( 1, (int) $saved['display']['tags_list'] );

		$this->assertArrayHasKey( 'recent_posts', $saved['display'], 'An unticked box must persist as 0, not vanish.' );
		$this->assertSame( 0, (int) $saved['display']['recent_posts'] );
	}

	/**
	 * The checkbox carries a hidden field naming itself.
	 *
	 * Only ticked boxes are posted, so the save needs to be told which toggles
	 * were on the screen in order to store the rest as 0.
	 */
	public function test_the_checkbox_helper_declares_its_key() {
		$html = wp_stats_checkbox( 'tags_list', 'Tags List' );

		$this->assertStringContainsString( 'name="stats_options[known][]" value="tags_list"', $html );
	}

	/**
	 * Every rendered checkbox declares itself, so nothing can be stuck on.
	 */
	public function test_every_checkbox_on_the_screen_declares_its_key() {
		$html = $this->render_screen();

		$this->assertSame(
			substr_count( $html, 'name="stats_options[display][]"' ),
			substr_count( $html, 'name="stats_options[known][]"' ),
			'Each checkbox needs a matching hidden field.'
		);
	}

	/**
	 * A toggle that is on by default can actually be switched off.
	 *
	 * This is the bug the hidden field exists for. A companion plugin's toggle
	 * defaults to 1, so if the save does not know the key was on the screen it
	 * writes nothing for it, and the next read merges the default straight back
	 * in - the box ticks itself again and the setting looks unsaveable. Relying
	 * on the registered defaults instead is not enough, because whether a
	 * companion has registered by the time the sanitizer runs depends on hook
	 * timing WP-Stats does not control.
	 */
	public function test_a_default_on_toggle_can_be_switched_off() {
		// A key nothing has registered and nothing has stored, exactly like a
		// companion whose filter has not run in this request.
		$saved = WP_Stats_Settings::sanitize(
			array(
				'url'        => '',
				'most_limit' => '10',
				'known'      => array( 'total_stats', 'unregistered_companion_key' ),
				'display'    => array( 'total_stats' ),
			)
		);

		$this->assertSame( 1, (int) $saved['display']['total_stats'] );
		$this->assertArrayHasKey( 'unregistered_companion_key', $saved['display'] );
		$this->assertSame( 0, (int) $saved['display']['unregistered_companion_key'] );
	}

	/**
	 * The bookkeeping field is not stored alongside the settings.
	 */
	public function test_the_known_list_is_not_persisted() {
		$saved = WP_Stats_Settings::sanitize(
			array(
				'url'        => '',
				'most_limit' => '10',
				'known'      => array( 'total_stats' ),
				'display'    => array(),
			)
		);

		$this->assertArrayNotHasKey( 'known', $saved );
	}

	/**
	 * A 2.x-style checkbox still saves.
	 *
	 * Companions built before 3.0.0 render name="stats_display[]", and
	 * options.php only saves the option names registered to the settings group,
	 * so that field never reaches the sanitizer as part of $input.
	 */
	public function test_a_legacy_checkbox_still_ticks() {
		$_POST['stats_display'] = array( 'legacy_companion' );

		try {
			$saved = WP_Stats_Settings::sanitize(
				array(
					'url'        => '',
					'most_limit' => '10',
					'display'    => array( 'total_stats' ),
				)
			);
		} finally {
			unset( $_POST['stats_display'] );
		}

		$this->assertSame( 1, (int) $saved['display']['total_stats'], 'The 3.0.0 field still works.' );
		$this->assertSame( 1, (int) $saved['display']['legacy_companion'], 'The 2.x field is honoured too.' );
	}

	/**
	 * A stale companion's panel is not forced off by the first save.
	 *
	 * This is the failure the legacy fallback exists for, and it is worse than
	 * the checkbox being ignored: the key is already in the stored row, so the
	 * sanitizer starts it at 0, and nothing the user can do from the screen
	 * would ever set it back to 1.
	 */
	public function test_a_stale_companions_toggle_is_not_forced_off() {
		// The companion's toggle is on, as a previous save left it.
		$this->set_display( array( 'legacy_companion' => 1 ) );

		// The user saves the screen without changing that plugin's checkbox,
		// which still posts under the old field name.
		$_POST['stats_display'] = array( 'legacy_companion' );

		try {
			$saved = WP_Stats_Settings::sanitize(
				array(
					'url'        => '',
					'most_limit' => '10',
					'known'      => array( 'total_stats' ),
					'display'    => array( 'total_stats' ),
				)
			);
		} finally {
			unset( $_POST['stats_display'] );
		}

		$this->assertSame( 1, (int) $saved['display']['legacy_companion'], 'The panel must survive an unrelated save.' );
	}

	/**
	 * Unticking a 2.x-style checkbox still turns it off.
	 */
	public function test_a_legacy_checkbox_still_unticks() {
		$this->set_display( array( 'legacy_companion' => 1 ) );

		// Unticked boxes are not posted, and this time nothing arrives under the
		// old field name either.
		$saved = WP_Stats_Settings::sanitize(
			array(
				'url'        => '',
				'most_limit' => '10',
				'display'    => array( 'total_stats' ),
			)
		);

		$this->assertSame( 0, (int) $saved['display']['legacy_companion'] );
	}

	/**
	 * Junk in the legacy field must not fatal.
	 */
	public function test_a_malformed_legacy_field_is_ignored() {
		$_POST['stats_display'] = 'not-an-array';

		try {
			$saved = WP_Stats_Settings::sanitize(
				array(
					'url'        => '',
					'most_limit' => '10',
					'display'    => array( 'total_stats' ),
				)
			);
		} finally {
			unset( $_POST['stats_display'] );
		}

		$this->assertSame( 1, (int) $saved['display']['total_stats'] );
	}

	/**
	 * A key WP-Stats has never heard of must still save.
	 *
	 * A companion plugin renders its own checkbox, so rejecting unrecognised
	 * keys would report "Settings saved" and silently drop the toggle.
	 */
	public function test_sanitize_accepts_a_companion_key() {
		$saved = WP_Stats_Settings::sanitize(
			array(
				'url'        => '',
				'most_limit' => '10',
				'display'    => array( 'a_plugin_we_have_never_seen' ),
			)
		);

		$this->assertSame( 1, (int) $saved['display']['a_plugin_we_have_never_seen'] );
	}

	/**
	 * The stored shape survives a round trip through the sanitizer.
	 *
	 * The callback is hung on sanitize_option_stats_options by
	 * register_setting(), so every write to the option on an admin request is
	 * handed the stored name => 0|1 map rather than the list of ticked names
	 * the screen posts. Read as a list, its values are the literal 0s and 1s
	 * and every real toggle would be zeroed.
	 */
	public function test_sanitize_reads_the_stored_shape() {
		$saved = WP_Stats_Settings::sanitize(
			array(
				'url'        => '',
				'most_limit' => 10,
				'display'    => array(
					'total_stats' => 1,
					'tags_list'   => 0,
					'a_companion' => 1,
				),
			)
		);

		$this->assertSame( 1, (int) $saved['display']['total_stats'] );
		$this->assertSame( 0, (int) $saved['display']['tags_list'] );
		$this->assertSame( 1, (int) $saved['display']['a_companion'], 'A companion key must survive a direct write.' );
		$this->assertArrayNotHasKey( '1', $saved['display'], 'The map was read as a list of ticked names.' );
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

		$this->assertTrue( WP_Stats_Options::display( 'tags_list' ) );
		$this->assertFalse( WP_Stats_Options::display( 'total_stats' ) );
	}

	/**
	 * The limit never saves below 1.
	 */
	public function test_sanitize_clamps_the_limit() {
		$saved = WP_Stats_Settings::sanitize(
			array(
				'url'        => '',
				'most_limit' => '0',
				'display'    => array(),
			)
		);

		$this->assertSame( 1, $saved['most_limit'] );
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

		$this->assertIsArray( $saved );
		$this->assertArrayHasKey( 'url', $saved );
		$this->assertArrayHasKey( 'most_limit', $saved );
		$this->assertIsArray( $saved['display'] );
	}

	/**
	 * Shapes the sanitizer can be handed.
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

		$this->assertStringContainsString( 'settings_updated', $html );
		$this->assertStringContainsString( 'Settings saved.', $html );
	}

	/**
	 * The registered setting is the one the form posts to.
	 */
	public function test_the_setting_is_registered() {
		WP_Stats_Settings::register();

		$registered = get_registered_settings();

		$this->assertArrayHasKey( WP_Stats_Options::OPTION, $registered );
	}

	/**
	 * Render the options screen.
	 *
	 * @return string
	 */
	private function render_screen() {
		return $this->render( array( 'WP_Stats_Settings', 'render' ) );
	}
}
