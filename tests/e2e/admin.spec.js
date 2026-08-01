/**
 * The admin surface: one menu opening one page with two tabs, and the
 * capability that gates them.
 *
 * Until 3.0.0 the two screens lived in different menus entirely -- the
 * statistics under Dashboard, the settings under Settings -- which is the
 * scattering the house rule exists to stop, so where they are is as much a
 * test as what they contain. They were briefly two submenu entries under one
 * menu; they are two tabs on one page now.
 *
 * The two contexts the capability filter distinguishes are the point of the
 * last two tests: a site that hands the read-only statistics tab to editors
 * must not open the settings tab to them by accident, and the only way to see
 * that is to move one context and check the other stayed put.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	SETTINGS_URL,
	STATS_URL,
	createStatsPage,
	ensureUser,
	installMuPlugin,
	loginAs,
	openSettings,
	removeMuPlugin,
	resetOptions,
	saveSettings,
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

	test( 'the menu is one entry with no submenu of its own', async ( { page } ) => {
		// Every navigation test below leans on the menu existing at all. The
		// second half is the change: the two screens are tabs now, so the menu
		// has nothing under it and the entry itself opens the page.
		await page.goto( '/wp-admin/index.php' );

		const menu = page.locator( '#adminmenu li', { hasText: 'WP-Stats' } ).first();

		await expect( menu.getByRole( 'link', { name: 'WP-Stats', exact: true } ) ).toBeAttached();
		await expect( menu.locator( '.wp-submenu' ) ).toHaveCount( 0 );
	} );

	test( 'the menu entry opens the statistics tab', async ( { page } ) => {
		await page.goto( '/wp-admin/index.php' );

		const menu = page.locator( '#adminmenu li', { hasText: 'WP-Stats' } ).first();
		await menu.getByRole( 'link', { name: 'WP-Stats', exact: true } ).click();

		await expect( page.getByRole( 'heading', { name: 'Stats', exact: true } ) ).toBeVisible();
		expect( page.url() ).toContain( 'admin.php?page=wp-stats' );

		// Statistics first, because it is what the plugin is opened to look at.
		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'Statistics' );
		await expect( page.locator( '.wp-stats #GeneralStats' ) ).toBeVisible();
	} );

	test( 'the tabs are Statistics then Settings, and each opens the other', async ( { page } ) => {
		await page.goto( STATS_URL );

		// Scoped to the tab strip throughout: core's own Settings menu is in
		// #adminmenu on every one of these screens, and an unscoped role query
		// matches both.
		const tabs = page.locator( '.nav-tab-wrapper' );

		await expect( tabs.locator( '.nav-tab' ) ).toHaveText( [ 'Statistics', 'Settings' ] );

		await tabs.getByRole( 'link', { name: 'Settings', exact: true } ).click();

		expect( page.url() ).toContain( 'page=wp-stats&tab=settings' );
		await expect( page.getByRole( 'heading', { name: 'Stats', exact: true } ) ).toBeVisible();
		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'Settings' );
		await expect( page.getByRole( 'button', { name: 'Save Changes' } ) ).toBeVisible();

		// The report is not on the settings tab, and the settings form is not on
		// the report: one page, but not one screenful of both.
		await expect( page.locator( '#GeneralStats' ) ).toHaveCount( 0 );

		await tabs.getByRole( 'link', { name: 'Statistics', exact: true } ).click();

		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'Statistics' );
		await expect( page.locator( 'form[action*="options.php"]' ) ).toHaveCount( 0 );
	} );

	test( 'saving comes back to the Settings tab, not to the report', async ( { page } ) => {
		// options.php redirects to whatever referer the form carried. Without the
		// tab in it the save lands on Statistics and the notice arrives on a
		// screen that has nothing to do with saving.
		await openSettings( page );
		await saveSettings( page );

		expect( page.url() ).toContain( 'tab=settings' );
		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'Settings' );
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

	test( 'a subscriber gets neither tab, and an administrator gets both', async ( { page } ) => {
		// Both directions in one test on purpose. "The subscriber sees nothing"
		// passes just as well with the plugin deactivated; the administrator
		// half is what proves the gate is the capability rather than a missing
		// page.
		await page.goto( '/wp-admin/index.php' );
		await expect( page.locator( '#adminmenu' ) ).toContainText( 'WP-Stats' );

		await page.goto( STATS_URL );
		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'Statistics' );
		await page.goto( SETTINGS_URL );
		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'Settings' );

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

	test( 'the capability filter can hand the statistics tab to an editor without the settings', async ( { page } ) => {
		// The whole reason capability() takes a context, and the reason one page
		// with two tabs is not simply one capability. A site that wants editors
		// to read the numbers says so for 'statistics' alone, and 'settings' has
		// to stay where it was even though both tabs are now the same page.
		installMuPlugin(
			CAP_FILTER,
			`<?php
/**
 * Plugin Name: WP-Stats E2E capability filter
 * Description: Hands the statistics tab, and only that tab, to editors.
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

			// The tab strip is drawn from the same capability, so the tab this
			// account cannot open is not offered at all: an entry that dies when
			// it is clicked is worse than no entry.
			await expect( other.locator( '.nav-tab' ) ).toHaveText( [ 'Statistics' ] );

			// The menu is registered with whichever capability the reader holds,
			// so the entry has to be there too -- a screen you can only reach by
			// typing the URL is not the same as one you were given.
			await other.goto( '/wp-admin/index.php' );
			await expect( other.locator( '#adminmenu' ) ).toContainText( 'WP-Stats' );

			// And the settings tab still refuses, even though the page it is on
			// is now one this account may open.
			await other.goto( SETTINGS_URL );
			await expect( other.locator( 'body' ) ).toContainText(
				/do not have sufficient permissions/,
			);
			await expect( other.locator( 'form[action*="options.php"]' ) ).toHaveCount( 0 );
		} finally {
			await other.context().close();
		}
	} );

	test( 'an editor gets neither tab once the filter is gone', async ( { page } ) => {
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
