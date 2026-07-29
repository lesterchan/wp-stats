<?php
/**
 * The consolidated option row and the 2.x migration.
 *
 * @package WP-Stats
 */

/**
 * WP_Stats_Options.
 */
class WP_Stats_Options_Test extends WP_Stats_TestCase {

	/**
	 * The pre-3.0.0 option names, folded into one row by the migration.
	 *
	 * @var string[]
	 */
	private $legacy = array( 'stats_url', 'stats_mostlimit', 'stats_display' );

	/**
	 * Settings live in exactly one row.
	 */
	public function test_settings_occupy_a_single_option_row() {
		global $wpdb;

		$names = $wpdb->get_col(
			"SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'stats\_%' ORDER BY option_name"
		);

		$this->assertSame( array( 'stats_db_version', 'stats_options' ), $names );
	}

	/**
	 * Defaults are merged over whatever is stored, so a key added by a newly
	 * activated companion plugin appears without a save.
	 */
	public function test_unknown_keys_fall_back_to_their_default() {
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
	 * A companion plugin can register its own toggle.
	 */
	public function test_a_companion_can_register_a_toggle() {
		add_filter(
			'wp_stats_display_defaults',
			static function ( $defaults ) {
				return array_merge( array( 'my_plugin' => 1 ), (array) $defaults );
			}
		);

		WP_Stats_Options::flush();

		$this->assertArrayHasKey( 'my_plugin', WP_Stats_Options::display_defaults() );
		$this->assertTrue( wp_stats_display_enabled( 'my_plugin' ) );
	}

	/**
	 * The limit never drops below 1, which would produce an empty LIMIT.
	 */
	public function test_most_limit_is_clamped() {
		WP_Stats_Options::update( array( 'most_limit' => 0 ) );
		WP_Stats_Options::flush();

		$this->assertSame( 1, WP_Stats_Options::most_limit() );
	}

	/**
	 * The migration folds the three legacy rows into one, preserving values
	 * that differ from the defaults in both directions.
	 */
	public function test_migration_carries_the_legacy_rows_over() {
		$this->given_a_pre_300_install();

		WP_Stats_Options::maybe_migrate();
		WP_Stats_Options::flush();

		$options = WP_Stats_Options::get();

		$this->assertSame( 'https://legacy.example.com/my-stats/', $options['url'] );
		$this->assertSame( 7, $options['most_limit'] );
		$this->assertSame( 0, (int) $options['display']['total_stats'], 'An explicit 0 must not be overwritten by the default 1.' );
		$this->assertSame( 1, (int) $options['display']['tags_list'], 'An explicit 1 must not be overwritten by the default 0.' );
		$this->assertSame( 1, (int) $options['display']['downloads'], "A companion plugin's own key must survive." );
		$this->assertSame( 1, (int) $options['display']['authors'], 'A key the legacy row never had takes its default.' );
	}

	/**
	 * The legacy rows are removed once folded in.
	 */
	public function test_migration_deletes_the_legacy_rows() {
		global $wpdb;

		$this->given_a_pre_300_install();

		WP_Stats_Options::maybe_migrate();

		foreach ( $this->legacy as $name ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->options WHERE option_name = %s", $name ) );
			$this->assertSame( 0, (int) $count, "$name should have been deleted." );
		}

		$this->assertSame( WP_Stats_Options::DB_VERSION, (int) get_option( WP_Stats_Options::DB_VERSION_OPTION ) );
	}

	/**
	 * Running it twice must not reset anything.
	 *
	 * The migration is gated on the schema version rather than on "do the old
	 * keys exist", because on an already-migrated install the old keys are gone
	 * and the second run would write defaults over the settings.
	 */
	public function test_migration_is_idempotent() {
		$this->given_a_pre_300_install();

		WP_Stats_Options::maybe_migrate();
		WP_Stats_Options::flush();
		WP_Stats_Options::maybe_migrate();
		WP_Stats_Options::flush();

		$options = WP_Stats_Options::get();

		$this->assertSame( 'https://legacy.example.com/my-stats/', $options['url'] );
		$this->assertSame( 7, $options['most_limit'] );
		$this->assertSame( 0, (int) $options['display']['total_stats'] );
	}

	/**
	 * A legacy row reappearing must not restart the migration.
	 *
	 * This is what the schema-version gate is actually for. Deleting the old
	 * rows is not enough on its own: anything that recreates one - a restored
	 * backup, or a plugin built against 2.x calling update_option() - would
	 * otherwise send the migration round again, merging that stale row over
	 * settings the user has since changed and resetting url and most_limit to
	 * their defaults.
	 */
	public function test_a_reappearing_legacy_row_does_not_restart_the_migration() {
		$this->given_a_pre_300_install();

		WP_Stats_Options::maybe_migrate();
		WP_Stats_Options::flush();

		// The user changes their settings after upgrading.
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

		WP_Stats_Options::maybe_migrate();
		WP_Stats_Options::flush();

		$after = WP_Stats_Options::get();

		$this->assertSame( 'https://current.example.com/stats/', $after['url'], 'The migration must not run again.' );
		$this->assertSame( 42, $after['most_limit'] );
		$this->assertSame( 0, (int) $after['display']['total_stats'], 'The stale row must not be merged in.' );
	}

	/**
	 * A fresh install has no legacy rows and must not be treated as an upgrade.
	 */
	public function test_migration_leaves_a_fresh_install_alone() {
		delete_option( WP_Stats_Options::DB_VERSION_OPTION );
		WP_Stats_Options::update(
			array(
				'url'        => 'https://fresh.example.com/stats/',
				'most_limit' => 3,
				'display'    => array( 'total_stats' => 0 ),
			)
		);
		WP_Stats_Options::flush();

		WP_Stats_Options::maybe_migrate();
		WP_Stats_Options::flush();

		$options = WP_Stats_Options::get();

		$this->assertSame( 'https://fresh.example.com/stats/', $options['url'] );
		$this->assertSame( 3, $options['most_limit'] );
		$this->assertSame( 0, (int) $options['display']['total_stats'] );
	}

	/**
	 * Plugins built against 2.x read the old names directly. They keep working.
	 *
	 * @dataProvider data_legacy_reads
	 *
	 * @param string $option   Legacy option name.
	 * @param mixed  $expected What it should answer with.
	 */
	public function test_legacy_option_names_still_answer( $option, $expected ) {
		$this->given_a_pre_300_install();

		WP_Stats_Options::maybe_migrate();
		WP_Stats_Options::flush();

		$value = get_option( $option );

		if ( is_array( $expected ) ) {
			$this->assertIsArray( $value );
			foreach ( $expected as $key => $sub ) {
				$this->assertSame( $sub, (int) $value[ $key ] );
			}
			return;
		}

		$this->assertSame( $expected, $value );
	}

	/**
	 * Legacy names and what they must return after the migration.
	 *
	 * @return array
	 */
	public function data_legacy_reads() {
		return array(
			'url'     => array( 'stats_url', 'https://legacy.example.com/my-stats/' ),
			'limit'   => array( 'stats_mostlimit', 7 ),
			'display' => array(
				'stats_display',
				array(
					'total_stats' => 0,
					'downloads'   => 1,
				),
			),
		);
	}

	/**
	 * Put the database back into its pre-3.0.0 shape.
	 *
	 * @return void
	 */
	private function given_a_pre_300_install() {
		delete_option( WP_Stats_Options::OPTION );
		delete_option( WP_Stats_Options::DB_VERSION_OPTION );
		WP_Stats_Options::flush();

		update_option( 'stats_url', 'https://legacy.example.com/my-stats/' );
		update_option( 'stats_mostlimit', 7 );
		update_option(
			'stats_display',
			array(
				// Deliberately opposite to the defaults in both directions.
				'total_stats' => 0,
				'tags_list'   => 1,
				// A key from a companion plugin that WP-Stats does not define.
				'downloads'   => 1,
			)
		);
	}
}
