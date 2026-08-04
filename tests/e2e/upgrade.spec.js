/**
 * The pre-3.0.0 migration, run the way a real site runs it.
 *
 * Activation does not fire when a plugin is merely updated -- a site that
 * updates from the Plugins screen never calls activate() -- so maybe_upgrade()
 * hangs off plugins_loaded instead, which every request goes through. Loading a
 * page in a browser is the only way to reach it.
 *
 * WP-Stats is the plugin at the centre of §13, so its migration has a shape none
 * of the others do: `stats_display` was **its own** row that six companion
 * plugins wrote their toggles into. The fold-in therefore has to keep the ten
 * toggles WP-Stats owns and drop the companions' -- which are not WP-Stats' to
 * hold and which each sibling reads out of the same row in its own migration.
 *
 * Every row is read *raw*. WP_Stats_Options::get() merges over the defaults, so
 * it answers identically for a row holding the defaults and for no row at all --
 * which is the §7.6.1 failure exactly: rows read, deleted and never written.
 * Ask the database, not the plugin.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	STATS_URL,
	defaultOptions,
	installLegacyRows,
	rawOptions,
	resetOptions,
	runningVersions,
	setVersionRow,
	survivingLegacyRows,
	versionRow,
	wpEval,
} = require( './helpers.js' );

/** The Dashboard: an ordinary admin request, which is what an update goes through. */
const DASHBOARD_URL = '/wp-admin/index.php';

test.describe( 'The pre-3.0.0 upgrade', () => {
	test.afterEach( async () => {
		// Back to a current install: markers stamped, settings at a fresh
		// install's, no legacy rows anywhere. Every other spec in this suite
		// starts from that, and this is the only file that takes it apart.
		wpEval(
			`foreach ( WP_Stats_Options::LEGACY_ROWS as $row ) {
				delete_option( $row );
			}
			echo '<<<done>>>';`,
		);
		setVersionRow( runningVersions() );
		resetOptions();
	} );

	test( 'the 2.x rows are folded into one, written, deleted and stamped', async ( { page } ) => {
		const before = installLegacyRows( {
			stats_url: 'https://example.com/statistics',
			stats_mostlimit: 4,
			stats_display: {
				total_stats: 1,
				recent_posts: 0,
				tags_list: 1,

				// Toggles that belonged to companion plugins. WP-Stats decided
				// whether a plugin it knew nothing about got to draw a panel;
				// each of those plugins owns that decision now, and reads this
				// same row in its own migration.
				polls: 1,
				postviews: 0,
			},
			stats_db_version: '2.56.1',
		} );

		// The fixture is asserted from what the seeding call itself saw, not
		// from a second one. maybe_upgrade() runs on plugins_loaded, which a
		// WP-CLI request reaches too -- ask again through another `wp eval` and
		// the rows have already moved, and the request below would have nothing
		// left to do.
		expect( before.legacy ).toContain( 'stats_url' );
		expect( before.options ).toBe( false );
		expect( before.version ).toBe( false );

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		// Written, not merely readable through the defaults.
		expect( stored ).not.toBe( false );
		expect( stored.url ).toBe( 'https://example.com/statistics' );
		expect( stored.most_limit ).toBe( 4 );
		expect( stored.display.recent_posts ).toBe( 0 );
		expect( stored.display.tags_list ).toBe( 1 );

		// And only the toggles WP-Stats still owns survive the fold, so a
		// companion's old key cannot linger in this row for ever.
		expect( stored.display.polls ).toBeUndefined();
		expect( stored.display.postviews ).toBeUndefined();

		// Every old row gone rather than left to rot, read through the plugin's
		// own list so a row the migration stops deleting shows up here.
		expect( survivingLegacyRows() ).toEqual( [] );

		// One write, both markers, matching the code that is running.
		expect( versionRow() ).toEqual( runningVersions() );
	} );

	test( 'the migrated settings are what the statistics page acts on', async ( { page } ) => {
		installLegacyRows( {
			stats_mostlimit: 3,
			stats_display: { recent_posts: 1, tags_list: 0 },
		} );

		await page.goto( DASHBOARD_URL );

		// Present is not alive. The entry limit that came through the migration
		// has to be the one the headings are built from, and a toggle that came
		// through switched off has to be a block that is not drawn.
		await page.goto( STATS_URL );

		await expect( page.locator( '#wpbody-content' ) ).toContainText( '3 Recent Posts' );
		await expect( page.locator( '#wpbody-content' ) ).not.toContainText( 'Tags List' );
	} );

	test( "an unreleased 3.0.0 build's consolidated row is folded in too", async ( { page } ) => {
		// The other generation this migration meets: a development install that
		// already had one row, under the name before it gained its prefix.
		installLegacyRows( {
			stats_options: {
				url: 'https://example.com/from-the-beta',
				most_limit: 6,
				display: { ...defaultOptions().display, authors: 0 },
			},
		} );

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		expect( stored ).not.toBe( false );
		expect( stored.url ).toBe( 'https://example.com/from-the-beta' );
		expect( stored.most_limit ).toBe( 6 );
		expect( stored.display.authors ).toBe( 0 );
		expect( survivingLegacyRows() ).toEqual( [] );
	} );

	test( 'a dedicated 2.x row wins over the copy inside the beta row', async ( { page } ) => {
		// Both generations at once, which a site that ran a development build
		// over an existing 2.x install really has. The dedicated rows are the
		// later word and have to win.
		installLegacyRows( {
			stats_options: { url: 'https://example.com/from-the-beta', most_limit: 6 },
			stats_url: 'https://example.com/from-2x',
			stats_mostlimit: 9,
		} );

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		expect( stored.url ).toBe( 'https://example.com/from-2x' );
		expect( stored.most_limit ).toBe( 9 );
	} );

	test( 'a fresh install writes no settings row at all, and is still stamped', async ( {
		page,
	} ) => {
		// No legacy rows anywhere, which is what a site installing WP-Stats for
		// the first time looks like. There is nothing to carry over, so the
		// migration writes nothing -- the row stays absent and every read merges
		// the defaults, which is the plugin's normal fresh state. Only the
		// markers are written, and only so the check does not run again.
		const before = installLegacyRows( {} );

		expect( before.legacy ).toEqual( [] );
		expect( before.options ).toBe( false );

		await page.goto( DASHBOARD_URL );

		expect( rawOptions() ).toBe( false );
		expect( versionRow() ).toEqual( runningVersions() );
	} );

	test( 'an install already on this version is left alone', async ( { page } ) => {
		// A legacy row that should never be read, alongside markers saying the
		// upgrade has already happened. maybe_upgrade() returning early is what
		// keeps every request from being an option write, and the proof it
		// returned early is that this row survives untouched.
		// Stamped in the same call that writes the row: with the markers already
		// current, the WP-CLI request doing the writing cannot migrate it on the
		// way in, and neither can the browser.
		wpEval(
			`update_option( WP_Stats_Options::VERSION, array(
				'plugin' => WP_STATS_VERSION,
				'db'     => WP_STATS_DB_VERSION,
			) );
			update_option( 'stats_url', 'https://example.com/never-read' );
			echo '<<<done>>>';`,
		);

		await page.goto( DASHBOARD_URL );

		expect( survivingLegacyRows() ).toContain( 'stats_url' );
		expect( rawOptions().url ).not.toBe( 'https://example.com/never-read' );
	} );
} );
