<?php
/**
 * The Settings API screen and the companion-plugin contract.
 *
 * @package WP-Stats
 */

/**
 * Stats_Settings and the wp_stats_* helpers.
 */
class Test_Stats_Settings extends WP_Stats_TestCase {

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
		$saved = Stats_Settings::sanitize(
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
	 * A key WP-Stats has never heard of must still save.
	 *
	 * A companion plugin renders its own checkbox, so rejecting unrecognised
	 * keys would report "Settings saved" and silently drop the toggle.
	 */
	public function test_sanitize_accepts_a_companion_key() {
		$saved = Stats_Settings::sanitize(
			array(
				'url'        => '',
				'most_limit' => '10',
				'display'    => array( 'a_plugin_we_have_never_seen' ),
			)
		);

		$this->assertSame( 1, (int) $saved['display']['a_plugin_we_have_never_seen'] );
	}

	/**
	 * The limit never saves below 1.
	 */
	public function test_sanitize_clamps_the_limit() {
		$saved = Stats_Settings::sanitize(
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
		$saved = Stats_Settings::sanitize( $input );

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
	 * The registered setting is the one the form posts to.
	 */
	public function test_the_setting_is_registered() {
		Stats_Settings::register();

		$registered = get_registered_settings();

		$this->assertArrayHasKey( Stats_Options::OPTION, $registered );
	}

	/**
	 * Render the options screen.
	 *
	 * @return string
	 */
	private function render_screen() {
		return $this->render( array( 'Stats_Settings', 'render' ) );
	}
}
