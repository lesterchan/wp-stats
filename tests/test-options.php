<?php
/**
 * The two stored rows, and the migration off the pre-3.0.0 names.
 *
 * @package WP-Stats
 */

/**
 * @covers WP_Stats_Options
 */
class WP_Stats_Options_Test extends WP_Stats_TestCase {

	/**
	 * The pre-3.0.0 rows, every one of which the migration folds in and deletes.
	 *
	 * Read from the class rather than restated, so a row added to the list
	 * without a matching delete fails here.
	 *
	 * @var string[]
	 */
	private $legacy = WP_Stats_Options::LEGACY_ROWS;

	public function test_the_settings_and_the_markers_are_the_only_rows_the_plugin_owns() {
		WP_Stats_Options::maybe_upgrade();

		$this->assertSame(
			array( 'wp_stats_options', 'wp_stats_version' ),
			$this->stored_option_names( 'wp_stats_' ),
			'WP-Stats stores its settings in one row and its upgrade markers in another, and nothing else.'
		);
	}

	public function test_the_version_row_holds_the_running_version_and_the_schema_counter() {
		WP_Stats_Options::maybe_upgrade();

		$markers = WP_Stats_Options::markers();

		$this->assertSame( WP_STATS_VERSION, $markers['plugin'], 'The plugin marker records the version last run.' );
		$this->assertSame( WP_STATS_DB_VERSION, $markers['db'], 'The db marker records the schema the rows are in.' );
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
	 * The 2.x rows carried settings that differ from the defaults in both
	 * directions, and both directions have to survive the fold.
	 */
	public function test_the_migration_carries_the_two_x_rows_over() {
		$this->given_a_pre_300_install();

		WP_Stats_Options::maybe_upgrade();
		WP_Stats_Options::flush();

		$options = WP_Stats_Options::get();

		$this->assertSame( 'https://legacy.example.com/my-stats/', $options['url'], 'The 2.x URL row is carried into the new settings row.' );
		$this->assertSame( 7, $options['most_limit'], 'And the 2.x limit row with it, so neither is lost.' );
		$this->assertSame( 0, (int) $options['display']['total_stats'], 'An explicit 0 must not be overwritten by the default 1.' );
		$this->assertSame( 1, (int) $options['display']['tags_list'], 'An explicit 1 must not be overwritten by the default 0.' );
		$this->assertSame( 1, (int) $options['display']['authors'], 'A toggle the old row never had takes its default.' );
	}

	/**
	 * A toggle that belonged to another plugin is not WP-Stats' to keep.
	 *
	 * Before 3.0.0 the shared stats_display row held a key per companion
	 * plugin. Each of those plugins now reads that row in its own migration and
	 * keeps the answer in its own settings, so carrying the key over here would
	 * leave two copies of one setting and no way to tell which one won.
	 */
	public function test_the_migration_drops_a_companion_plugins_toggle() {
		$this->given_a_pre_300_install();

		WP_Stats_Options::maybe_upgrade();
		WP_Stats_Options::flush();

		$options = WP_Stats_Options::get();

		$this->assertArrayNotHasKey( 'downloads', $options['display'], "A companion plugin's toggle must not survive into wp_stats_options." );
		$this->assertArrayNotHasKey( 'polls', $options['display'], 'The polls toggle belongs to WP-Polls, not to wp_stats_options.' );
	}

	/**
	 * The unreleased 3.0.0 row is the other generation the migration folds in.
	 */
	public function test_the_migration_carries_the_unprefixed_three_zero_row_over() {
		$this->given_a_clean_slate();

		update_option(
			'stats_options',
			array(
				'url'        => 'https://unprefixed.example.com/stats/',
				'most_limit' => 4,
				'display'    => array( 'link_cats' => 0 ),
			)
		);

		WP_Stats_Options::maybe_upgrade();
		WP_Stats_Options::flush();

		$options = WP_Stats_Options::get();

		$this->assertSame( 'https://unprefixed.example.com/stats/', $options['url'], 'The unprefixed 3.0 URL row is carried over too.' );
		$this->assertSame( 4, $options['most_limit'], 'And its limit.' );
		$this->assertSame( 0, (int) $options['display']['link_cats'], 'And its display toggles, which is the half a partial migration drops.' );
	}

	/**
	 * A site that never touched the settings still gets a row written.
	 *
	 * Every other fixture in this file is customised, and deliberately so --
	 * "deliberately opposite to the defaults in both directions" is the comment
	 * on the one above. That is right for "did the values carry across" and it
	 * is blind to §7.6.1, because a result differing from the defaults lands
	 * whatever happened on the way in.
	 *
	 * This is the other install, and the commonest one. It seeds the legacy rows
	 * with exactly what the plugin ships, so the migration's result is equal to
	 * the defaults, and it runs on the far side of register_setting() so the
	 * default_option_wp_stats_options filter is live while the write happens.
	 * Under those two conditions an absent row reads back as the defaults, and
	 * a bare update_option() would compare the two, find them identical and
	 * write nothing -- while the legacy rows were deleted regardless.
	 *
	 * WP_Stats_Options::write() is what stops that, and this is what says so.
	 * Asserted on the raw row, because get() merges over the defaults and cannot
	 * tell a write that happened from one that did not.
	 */
	public function test_a_stock_pre_300_install_still_gets_its_row_written() {
		$this->given_a_pre_300_install_on_stock_settings();

		$this->assertFalse( get_option( WP_Stats_Options::OPTION, false ), 'The fixture is only pre-migration if the settings row is genuinely absent.' );

		WP_Stats_Settings::register();

		WP_Stats_Options::maybe_upgrade();
		WP_Stats_Options::flush();

		$stored = get_option( WP_Stats_Options::OPTION, false );

		$this->assertIsArray( $stored, 'The migration must write the settings row even when its result equals the shipped defaults.' );
		$this->assertSame( WP_Stats_Options::display_defaults(), $stored['display'], 'And the toggles are in the row, not merely answered by the registered default.' );

		foreach ( $this->legacy as $name ) {
			$this->assertFalse( get_option( $name, false ), sprintf( 'The legacy row %s must not survive the migration.', $name ) );
		}
	}

	public function test_the_migration_deletes_every_row_it_folded_in() {
		$this->given_a_pre_300_install();

		WP_Stats_Options::maybe_upgrade();

		$this->assertSame(
			array(),
			$this->stored_option_names( 'stats_' ),
			'Every pre-3.0.0 row must be gone once its value has been carried over.'
		);
	}

	/**
	 * Running it twice must not reset anything.
	 */
	public function test_the_migration_is_idempotent() {
		$this->given_a_pre_300_install();

		WP_Stats_Options::maybe_upgrade();
		WP_Stats_Options::flush();
		WP_Stats_Options::maybe_upgrade();
		WP_Stats_Options::flush();

		$options = WP_Stats_Options::get();

		$this->assertSame( 'https://legacy.example.com/my-stats/', $options['url'], 'Running the migration twice leaves the URL as it was.' );
		$this->assertSame( 7, $options['most_limit'], 'And the limit.' );
		$this->assertSame( 0, (int) $options['display']['total_stats'], 'And the toggles, so a second run cannot reset them to the defaults.' );
	}

	/**
	 * A legacy row reappearing must not restart the migration.
	 *
	 * This is what the marker gate is actually for. Deleting the old rows is
	 * not enough on its own: anything that recreates one - a restored backup,
	 * or a plugin built against 2.x calling update_option() - would otherwise
	 * send the migration round again, merging that stale row over settings the
	 * site owner has since changed.
	 */
	public function test_a_reappearing_legacy_row_does_not_restart_the_migration() {
		$this->given_a_pre_300_install();

		WP_Stats_Options::maybe_upgrade();
		WP_Stats_Options::flush();

		// The site owner changes their settings after upgrading.
		$options               = WP_Stats_Options::get();
		$options['url']        = 'https://current.example.com/stats/';
		$options['most_limit'] = 42;
		WP_Stats_Options::update( $options );
		WP_Stats_Options::flush();

		// Something writes the old row back.
		update_option(
			'stats_display',
			array(
				'total_stats' => 1,
				'tags_list'   => 0,
			)
		);

		WP_Stats_Options::maybe_upgrade();
		WP_Stats_Options::flush();

		$after = WP_Stats_Options::get();

		$this->assertSame( 'https://current.example.com/stats/', $after['url'], 'The migration must not run again.' );
		$this->assertSame( 42, $after['most_limit'], 'A legacy row written back after the migration does not restart it and overwrite the current value.' );
		$this->assertSame( 0, (int) $after['display']['total_stats'], 'The stale row must not be merged in.' );
	}

	/**
	 * A fresh install has no legacy rows and must not be treated as an upgrade.
	 */
	public function test_the_migration_leaves_a_fresh_install_alone() {
		delete_option( WP_Stats_Options::VERSION );
		WP_Stats_Options::update(
			array(
				'url'        => 'https://fresh.example.com/stats/',
				'most_limit' => 3,
				'display'    => array( 'total_stats' => 0 ),
			)
		);
		WP_Stats_Options::flush();

		WP_Stats_Options::maybe_upgrade();
		WP_Stats_Options::flush();

		$options = WP_Stats_Options::get();

		$this->assertSame( 'https://fresh.example.com/stats/', $options['url'], 'A fresh install keeps the URL it was configured with.' );
		$this->assertSame( 3, $options['most_limit'], 'And its limit.' );
		$this->assertSame( 0, (int) $options['display']['total_stats'], 'And its toggles; there was no legacy row to fold in.' );
	}

	/**
	 * Activation seeds the settings row and points it at the conventional page.
	 */
	public function test_activation_seeds_the_settings_row() {
		$this->given_a_clean_slate();

		WP_Stats_Options::activate();
		WP_Stats_Options::flush();

		$markers = WP_Stats_Options::markers();

		$this->assertSame( home_url( '/stats/' ), WP_Stats_Options::url(), 'A new install gets a sensible stats page URL.' );
		$this->assertSame( WP_STATS_DB_VERSION, $markers['db'], 'Activation stamps the db marker, which is what stops the migration running again.' );
	}

	/**
	 * WP-Stats no longer answers for another plugin's storage.
	 *
	 * Until 3.0.0 the class filtered option_stats_display and friends so a
	 * companion built against 2.x kept reading values out of the consolidated
	 * row. That shim is what STANDARDS.md 13 replaces, and leaving it in place
	 * would mean six plugins still had a reason not to migrate.
	 */
	public function test_the_legacy_option_names_are_no_longer_served() {
		$this->given_a_pre_300_install();

		WP_Stats_Options::maybe_upgrade();
		WP_Stats_Options::flush();

		foreach ( $this->legacy as $name ) {
			$this->assertFalse( get_option( $name ), "get_option( '{$name}' ) must not be answered out of wp_stats_options." );
		}
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

	/**
	 * Remove every row the plugin owns, in either generation.
	 *
	 * @return void
	 */
	private function given_a_clean_slate() {
		delete_option( WP_Stats_Options::OPTION );
		delete_option( WP_Stats_Options::VERSION );

		foreach ( $this->legacy as $name ) {
			delete_option( $name );
		}

		WP_Stats_Options::flush();
	}

	/**
	 * The same install, on the settings WP-Stats shipped.
	 *
	 * Built from defaults() and display_defaults() rather than typed out, so a
	 * default that changes stays stock here instead of quietly becoming a
	 * customised fixture and losing the property this is for.
	 *
	 * @return void
	 */
	private function given_a_pre_300_install_on_stock_settings() {
		$this->given_a_clean_slate();

		$defaults = WP_Stats_Options::defaults();

		update_option( 'stats_url', $defaults['url'] );
		update_option( 'stats_mostlimit', $defaults['most_limit'] );
		update_option( 'stats_display', WP_Stats_Options::display_defaults() );
	}

	/**
	 * Put the database back into its pre-3.0.0 shape.
	 *
	 * @return void
	 */
	private function given_a_pre_300_install() {
		$this->given_a_clean_slate();

		update_option( 'stats_url', 'https://legacy.example.com/my-stats/' );
		update_option( 'stats_mostlimit', 7 );
		update_option(
			'stats_display',
			array(
				// Deliberately opposite to the defaults in both directions.
				'total_stats' => 0,
				'tags_list'   => 1,
				// Two keys that belonged to companion plugins.
				'downloads'   => 1,
				'polls'       => 1,
			)
		);
	}
}
