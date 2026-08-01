/**
 * The admin surface: one menu with two entries, and the capability that gates
 * them.
 *
 * Until 3.0.0 the two screens lived in different menus entirely -- the
 * statistics under Dashboard, the settings under Settings -- which is the
 * scattering the house rule exists to stop, so where they are is as much a
 * test as what they contain.
 *
 * The two contexts the capability filter distinguishes are the point of the
 * last two tests: a site that hands the read-only statistics screen to editors
 * must not open the settings screen to them by accident, and the only way to
 * see that is to move one context and check the other stayed put.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	SETTINGS_URL,
	STATS_URL,
	createStatsPage,
	ensureUser,
	installMuPlugin,
	loginAs,
	removeMuPlugin,
	resetOptions,
	setAllToggles,
	setOptions,
} = require( './helpers.js' );

/** A password for the throwaway accounts the capability tests log in as. */
const PASSWORD = 'correct-horse-battery-staple';

/** The mu-plugin name the capability filter is installed under. */
const CAP_FILTER = 'wp-stats-e2e-capability.php';

test.describe( 'The admin screens', () => {
	let statsPage;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();

		statsPage = await createStatsPage( requestUtils );
	} );

	test.afterAll( async () => {
		removeMuPlugin( CAP_FILTER );
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
		setOptions( { url: statsPage.link } );
		setAllToggles( 1 );
	} );

	test.afterEach( async () => {
		removeMuPlugin( CAP_FILTER );
		resetOptions();
	} );

	test( 'the fixture really is one menu with both screens under it', async ( { page } ) => {
		// Every navigation test below leans on the menu existing at all, and on
		// both entries being under the same top-level item.
		await page.goto( '/wp-admin/index.php' );

		const menu = page.locator( '#adminmenu li', { hasText: 'WP-Stats' } ).first();

		await expect( menu.getByRole( 'link', { name: 'Statistics', exact: true } ) ).toBeAttached();
		await expect( menu.getByRole( 'link', { name: 'Settings', exact: true } ) ).toBeAttached();
	} );

	test( 'the Statistics entry opens the statistics screen', async ( { page } ) => {
		await page.goto( '/wp-admin/index.php' );

		// The submenu is only drawn out on hover, so this is the sequence a
		// person performs.
		const menu = page.locator( '#adminmenu li', { hasText: 'WP-Stats' } ).first();
		await menu.hover();
		await menu.getByRole( 'link', { name: 'Statistics', exact: true } ).click();

		await expect( page.getByRole( 'heading', { name: 'Stats', exact: true } ) ).toBeVisible();
		expect( page.url() ).toContain( 'admin.php?page=wp-stats' );
		await expect( page.locator( '.wp-stats #GeneralStats' ) ).toBeVisible();
	} );

	test( 'the Settings entry opens the settings screen', async ( { page } ) => {
		await page.goto( '/wp-admin/index.php' );

		// Scoped to this plugin's own menu item: core's Settings menu is in
		// #adminmenu too, and an unscoped role query matches both.
		const menu = page.locator( '#adminmenu li', { hasText: 'WP-Stats' } ).first();
		await menu.hover();
		await menu.getByRole( 'link', { name: 'Settings', exact: true } ).click();

		await expect( page.getByRole( 'heading', { name: 'WP-Stats Settings' } ) ).toBeVisible();
		expect( page.url() ).toContain( 'page=wp-stats-settings' );
	} );

	test( 'the screen renders the same statistics the shortcode does', async ( { page } ) => {
		await page.goto( STATS_URL );

		// The admin screen and the shortcode both call WP_Stats_Page::render();
		// the screen additionally runs the result through wp_kses_post(). The
		// blocks surviving that is the difference worth pinning, because a
		// filter that ate them would leave the front end working and wp-admin
		// blank.
		for ( const id of [
			'GeneralStats',
			'TopRecentStats',
			'TopMostHighestStats',
			'AuthorsStats',
			'CommentsMembersStats',
			'MiscStats',
		] ) {
			await expect( page.locator( `#${ id }` ) ).toBeVisible();
		}
	} );

	test( 'a subscriber gets neither screen, and an administrator gets both', async ( { page } ) => {
		// Both directions in one test on purpose. "The subscriber sees nothing"
		// passes just as well with the plugin deactivated; the administrator
		// half is what proves the gate is the capability rather than a missing
		// page.
		await page.goto( '/wp-admin/index.php' );
		await expect( page.locator( '#adminmenu' ) ).toContainText( 'WP-Stats' );

		await page.goto( STATS_URL );
		await expect( page.getByRole( 'heading', { name: 'Stats', exact: true } ) ).toBeVisible();
		await page.goto( SETTINGS_URL );
		await expect( page.getByRole( 'heading', { name: 'WP-Stats Settings' } ) ).toBeVisible();

		ensureUser( 'stats_subscriber', 'subscriber', PASSWORD );
		const other = await loginAs( page, 'stats_subscriber', PASSWORD );

		await other.goto( '/wp-admin/index.php' );
		await expect( other.locator( '#adminmenu' ).getByText( 'WP-Stats' ) ).toHaveCount( 0 );

		await other.goto( STATS_URL );
		await expect( other.locator( 'body' ) ).toContainText( /not allowed to access this page/ );

		await other.goto( SETTINGS_URL );
		await expect( other.locator( 'body' ) ).toContainText( /not allowed to access this page/ );

		await other.context().close();
	} );

	test( 'the capability filter can hand the statistics screen to an editor without the settings', async ( { page } ) => {
		// The whole reason capability() takes a context. A site that wants
		// editors to read the numbers says so for 'statistics' alone, and
		// 'settings' has to stay where it was.
		installMuPlugin(
			CAP_FILTER,
			`<?php
/**
 * Plugin Name: WP-Stats E2E capability filter
 * Description: Hands the statistics screen, and only that screen, to editors.
 */
add_filter(
	'wp_stats_capability',
	function ( $capability, $context ) {
		return 'statistics' === $context ? 'edit_pages' : $capability;
	},
	10,
	2
);
`,
		);

		ensureUser( 'stats_editor', 'editor', PASSWORD );
		const other = await loginAs( page, 'stats_editor', PASSWORD );

		try {
			await other.goto( STATS_URL );
			await expect( other.getByRole( 'heading', { name: 'Stats', exact: true } ) ).toBeVisible();
			await expect( other.locator( '#GeneralStats' ) ).toBeVisible();

			// The menu is drawn from the same capability, so the entry has to be
			// there too -- a screen you can only reach by typing the URL is not
			// the same as one you were given.
			await other.goto( '/wp-admin/index.php' );
			await expect( other.locator( '#adminmenu' ) ).toContainText( 'WP-Stats' );
			await expect(
				other
					.locator( '#adminmenu li', { hasText: 'WP-Stats' } )
					.first()
					.getByRole( 'link', { name: 'Settings', exact: true } ),
			).toHaveCount( 0 );

			await other.goto( SETTINGS_URL );
			await expect( other.locator( 'body' ) ).toContainText( /not allowed to access this page/ );
		} finally {
			await other.context().close();
		}
	} );

	test( 'an editor gets neither screen once the filter is gone', async ( { page } ) => {
		// The other half of the test above: without the filter the shipped
		// capability is manage_options, so the same account sees nothing. Two
		// tests, one account, opposite answers -- which is what makes the pair
		// about the filter rather than about the role.
		ensureUser( 'stats_editor', 'editor', PASSWORD );
		const other = await loginAs( page, 'stats_editor', PASSWORD );

		try {
			await other.goto( '/wp-admin/index.php' );
			await expect( other.locator( '#adminmenu' ).getByText( 'WP-Stats' ) ).toHaveCount( 0 );

			await other.goto( STATS_URL );
			await expect( other.locator( 'body' ) ).toContainText( /not allowed to access this page/ );
		} finally {
			await other.context().close();
		}
	} );

	test( 'the statistics page is public, so a visitor reads it without an account', async ( {
		page,
	} ) => {
		// The screens are gated; the shortcode is not, and never has been. A
		// gate that leaked onto the front end would take the page away from
		// every visitor of every site that publishes one.
		const context = await page.context().browser().newContext( { storageState: undefined } );
		const guest = await context.newPage();

		try {
			await guest.goto( statsPage.link );
			await expect( guest.locator( '.wp-stats #GeneralStats' ) ).toBeVisible();
		} finally {
			await context.close();
		}
	} );
} );
