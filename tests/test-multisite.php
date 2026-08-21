<?php
/**
 * Network activation: the settings seed has to reach every site.
 *
 * Only runs under WP_MULTISITE=1 (see bin/test-multisite.sh). The settings row
 * is per-site and carries a per-site value: activation seeds 'url' from that
 * site's own home_url(), so a loop that stays on one site does not merely skip
 * the others -- any site it did seed while switched wrongly would point its
 * statistics page at another site's address. The markers self-heal from
 * plugins_loaded, but nothing after activation ever seeds the url, so a site
 * the loop misses stays missed.
 *
 * @package WP-Stats
 */

/**
 * WP_Stats::activate() across a network.
 *
 * @group ms-required
 */
class WP_Stats_Multisite_Test extends WP_Stats_TestCase {

	/**
	 * Skip the whole class on a single site install.
	 *
	 * @return void
	 */
	public function set_up() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires a multisite install. Run bin/test-multisite.sh.' );
		}

		parent::set_up();
	}

	/**
	 * Create sites and tear the plugin's rows down on each.
	 *
	 * Torn down so activation has something to do: a leftover row would let a
	 * loop that never reaches the site pass anyway.
	 *
	 * @param int $count How many extra sites to create.
	 * @return int[] Site IDs, the current site first.
	 */
	protected function seed_network( $count = 3 ) {
		$site_ids = array( get_current_blog_id() );

		for ( $i = 0; $i < $count; $i++ ) {
			$site_ids[] = (int) self::factory()->blog->create();
		}

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );

			delete_option( WP_Stats_Options::OPTION );
			delete_option( WP_Stats_Options::VERSION );

			restore_current_blog();
		}

		WP_Stats_Options::flush();

		return $site_ids;
	}

	/**
	 * Network activation seeds every site with its own address.
	 *
	 * Each site's home_url() differs, so asserting the stored url against the
	 * one read inside the switch catches both a site the loop never reached
	 * and a site seeded with another site's address -- the cross-site bleed no
	 * existence check can see. The row is read raw because the options API
	 * would answer with the defaults either way.
	 *
	 * @return void
	 */
	public function test_network_activation_seeds_every_site() {
		$site_ids = $this->seed_network( 3 );

		WP_Stats::activate( true );

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );

			$row      = get_option( WP_Stats_Options::OPTION );
			$expected = home_url( '/stats/' );

			restore_current_blog();

			$this->assertIsArray(
				$row,
				"Site {$site_id} never had its settings row created."
			);

			$this->assertSame(
				$expected,
				$row['url'],
				"Site {$site_id}'s statistics page points at another site's address."
			);
		}
	}

	/**
	 * Activating on one site does not touch the rest of the network.
	 *
	 * @return void
	 */
	public function test_single_site_activation_leaves_other_sites_alone() {
		$site_ids = $this->seed_network( 1 );
		$other    = $site_ids[1];

		WP_Stats::activate( false );

		switch_to_blog( $other );

		$this->assertFalse(
			get_option( WP_Stats_Options::OPTION ),
			"A per-site activation seeded site {$other}."
		);
		$this->assertFalse(
			get_option( WP_Stats_Options::VERSION ),
			"A per-site activation stamped site {$other}."
		);

		restore_current_blog();
	}

	/**
	 * The site query is uncapped and asks only for IDs.
	 *
	 * Asserted by reading the arguments the query was given rather than by
	 * building a 101 site fixture: get_sites() defaults to 100, so a larger
	 * network silently skips every site past the hundredth, and the cheap
	 * version of that assertion is the only one worth running per suite.
	 *
	 * @return void
	 */
	public function test_network_activation_queries_sites_without_a_cap() {
		$this->seed_network( 2 );

		$captured = array();
		add_action(
			'pre_get_sites',
			function ( $query ) use ( &$captured ) {
				$captured[] = $query->query_vars;
			}
		);

		WP_Stats::activate( true );

		$this->assertNotEmpty( $captured, 'Activation never queried the site list.' );
		$this->assertSame( 0, (int) $captured[0]['number'], 'get_sites() was left at its default cap of 100 sites.' );
		$this->assertSame( 'ids', $captured[0]['fields'], 'Only the site IDs are needed.' );
	}

	/**
	 * The blog stack is left unwound and the original site is current.
	 *
	 * Calling switch_to_blog() pushes onto a stack. Restoring once after the loop
	 * rather than once per iteration leaves the stack short, so whatever runs next
	 * operates against the last site visited instead of the one it thinks it is on.
	 *
	 * @return void
	 */
	public function test_network_activation_unwinds_the_blog_stack() {
		$original = get_current_blog_id();
		$this->seed_network( 3 );

		WP_Stats::activate( true );

		$this->assertFalse( ms_is_switched(), 'The blog stack was left switched.' );
		$this->assertSame( $original, get_current_blog_id(), 'The original site is no longer current.' );
	}
}
