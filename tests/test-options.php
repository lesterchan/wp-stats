<?php
/**
 * The two stored rows: defaults, merging and clamping.
 *
 * @package WP-Stats
 */

/**
 * @covers WP_Stats_Options
 */
class WP_Stats_Options_Test extends WP_Stats_TestCase {

	public function test_the_settings_and_the_markers_are_the_only_rows_the_plugin_owns() {
		WP_Stats_Options::maybe_upgrade();

		$this->assertSame(
			array( 'wp_stats_options', 'wp_stats_version' ),
			$this->stored_option_names( 'wp_stats_' ),
			'WP-Stats stores its settings in one row and its upgrade markers in another, and nothing else.'
		);
	}

	/**
	 * Defaults are merged over whatever is stored, so a toggle added by a later
	 * release appears without anyone having to visit the settings screen.
	 */
	public function test_a_toggle_missing_from_the_stored_row_falls_back_to_its_default() {
		WP_Stats_Options::update(
			array(
				'url'        => 'https://example.com/stats/',
				'most_limit' => 5,
				'display'    => array( 'total_stats' => 0 ),
			)
		);
		WP_Stats_Options::flush();

		$options = WP_Stats_Options::get();

		$this->assertSame( 0, (int) $options['display']['total_stats'], 'A stored 0 must survive.' );
		$this->assertSame( 1, (int) $options['display']['authors'], 'A missing key takes its default.' );
	}

	/**
	 * A limit of 0 would produce an empty LIMIT clause and an empty block.
	 */
	public function test_the_most_limit_never_drops_below_one() {
		WP_Stats_Options::update( array( 'most_limit' => 0 ) );
		WP_Stats_Options::flush();

		$this->assertSame( 1, WP_Stats_Options::most_limit(), 'The limit is clamped on read as well as on save.' );
	}

	public function test_a_malformed_settings_row_still_reads_as_the_defaults() {
		update_option( WP_Stats_Options::OPTION, 'not-an-array' );
		WP_Stats_Options::flush();

		$this->assertSame( WP_Stats_Options::defaults(), WP_Stats_Options::get(), 'Junk in the row must not fatal a page render.' );
	}

	/**
	 * Row names the plugin has left in wp_options, read straight from the table.
	 *
	 * @param string $prefix Row name prefix to match.
	 * @return string[]
	 */
	private function stored_option_names( $prefix ) {
		global $wpdb;

		return (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);
	}
}
