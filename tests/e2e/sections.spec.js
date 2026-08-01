/**
 * The one cross-plugin contract: wp_stats_sections.
 *
 * Before 3.0.0 a companion plugin hooked one of seven wp_stats_page_* filters,
 * appended a string, and first read WP-Stats' own option row to find out whether
 * it was allowed to. Both halves of that are gone. A contributor answers this
 * one filter with an entry describing its block and decides for itself, out of
 * its own settings, whether to answer at all -- so WP-Stats reads nobody's row
 * and can never render a section for a plugin that is not installed.
 *
 * No sibling plugin is installed here and none is needed: the filter is the
 * whole contract, so the mu-plugin behind installSections() is a contributor as
 * far as WP-Stats is concerned. It is also the only way to reach the branches
 * that matter most -- a malformed entry has to be skipped rather than allowed
 * to fatal a public page, and no real sibling would ever send one.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	asGuest,
	createStatsPage,
	installSections,
	removeSections,
	resetOptions,
	setAllToggles,
	setOptions,
	takeOverSection,
} = require( './helpers.js' );

test.describe( 'Contributed sections', () => {
	let statsPage;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();

		statsPage = await createStatsPage( requestUtils );
	} );

	test.afterAll( async () => {
		removeSections();
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
		setOptions( { url: statsPage.link } );
		setAllToggles( 1 );
		takeOverSection( '' );
	} );

	test.afterEach( async () => {
		removeSections();
		resetOptions();
	} );

	test( 'the fixture really is a contributor answering the filter', async ( { page } ) => {
		// Everything below leans on the mu-plugin actually being loaded and the
		// filter actually being fired. Without both, "no section appeared" is
		// what every one of these tests would see, and they would all pass.
		installSections( [ { key: 'wp_alpha', title: 'Alpha Stats', priority: 10 } ] );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			await expect( guest.locator( '#PluginsStats' ) ).toBeVisible();
			await expect( guest.locator( '.wp-stats' ) ).toContainText( 'Alpha Stats' );
			await expect( guest.locator( '.e2e-body-wp_alpha' ) ).toHaveText( 'body of wp_alpha' );
		} );
	} );

	test( 'the Plugins Stats heading only appears when something contributed', async ( { page } ) => {
		// Both directions, in one test. A site running WP-Stats on its own used
		// to show an empty section with a heading and nothing under it.
		installSections( [] );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			await expect( guest.locator( '#PluginsStats' ) ).toHaveCount( 0 );
			// The rest of the page is still there, so the heading went rather
			// than the render giving up.
			await expect( guest.locator( '#GeneralStats' ) ).toBeVisible();
		} );

		installSections( [ { key: 'wp_alpha', title: 'Alpha Stats', priority: 10 } ] );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );
			await expect( guest.locator( '#PluginsStats' ) ).toBeVisible();
		} );
	} );

	test( 'sections are ordered by priority, lowest first', async ( { page } ) => {
		installSections( [
			{ key: 'wp_alpha', title: 'Alpha Stats', priority: 30 },
			{ key: 'wp_beta', title: 'Beta Stats', priority: 10 },
			{ key: 'wp_gamma', title: 'Gamma Stats', priority: 20 },
		] );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// Declared alphabetically and rendered in none of that order, which
			// is what makes this about the priority rather than about the order
			// the contributor happened to add them in.
			await expect( guest.locator( '.wp-stats p[class^="e2e-body-"]' ) ).toHaveText( [
				'body of wp_beta',
				'body of wp_gamma',
				'body of wp_alpha',
			] );
		} );
	} );

	test( 'sections of equal priority fall back to their key', async ( { page } ) => {
		installSections( [
			{ key: 'wp_gamma', title: 'Gamma Stats', priority: 10 },
			{ key: 'wp_alpha', title: 'Alpha Stats', priority: 10 },
		] );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// The documented tie-break is the key, which is why the sort is
			// uksort() rather than uasort(): the latter never sees a key at all.
			await expect( guest.locator( '.wp-stats p[class^="e2e-body-"]' ) ).toHaveText( [
				'body of wp_alpha',
				'body of wp_gamma',
			] );
		} );
	} );

	test( 'a section that names no priority is treated as ten', async ( { page } ) => {
		installSections( [
			{ key: 'wp_alpha', title: 'Alpha Stats', kind: 'no_priority' },
			{ key: 'wp_beta', title: 'Beta Stats', priority: 5 },
			{ key: 'wp_gamma', title: 'Gamma Stats', priority: 20 },
		] );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// A missing priority is the documented default rather than a reason
			// to throw the entry away, so it sorts between five and twenty.
			await expect( guest.locator( '.wp-stats p[class^="e2e-body-"]' ) ).toHaveText( [
				'body of wp_beta',
				'body of wp_alpha',
				'body of wp_gamma',
			] );
		} );
	} );

	test( 'a malformed entry is skipped and the rest of the page still renders', async ( {
		page,
	} ) => {
		installSections( [
			{ key: 'wp_notarray', title: 'Not An Array', kind: 'not_array' },
			{ key: 'wp_notitle', title: '', kind: 'no_title' },
			{ key: 'wp_badrender', title: 'Bad Render', kind: 'bad_render' },
			{ key: 'wp_good', title: 'Good Stats', priority: 10 },
		] );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// A sibling plugin is third-party code as far as WP-Stats is
			// concerned. Three broken shapes and one good one: the good one has
			// to survive, which is what proves the page did not simply stop.
			await expect( guest.locator( '.e2e-body-wp_good' ) ).toBeVisible();
			await expect( guest.locator( '.wp-stats' ) ).toContainText( 'Good Stats' );

			await expect( guest.locator( '.wp-stats' ) ).not.toContainText( 'Not An Array' );
			await expect( guest.locator( '.wp-stats' ) ).not.toContainText( 'Bad Render' );
			await expect( guest.locator( '.e2e-body-wp_notitle' ) ).toHaveCount( 0 );

			await expect( guest.locator( '#GeneralStats' ) ).toBeVisible();
			await expect( guest.locator( '#MiscStats' ) ).toBeVisible();
		} );
	} );

	test( 'a theme can take one section over and leave the others alone', async ( { page } ) => {
		installSections( [
			{ key: 'wp_alpha', title: 'Alpha Stats', priority: 10 },
			{ key: 'wp_beta', title: 'Beta Stats', priority: 20 },
		] );
		takeOverSection( 'wp_alpha' );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// Each section is rendered through wp_stats_section_<key> rather
			// than called inline for exactly this: the theme's listener runs
			// first, unhooks WP-Stats' own, and writes its own markup -- and
			// the section beside it is untouched.
			await expect( guest.locator( '.e2e-takeover' ) ).toBeVisible();
			await expect( guest.locator( '.e2e-body-wp_alpha' ) ).toHaveCount( 0 );
			await expect( guest.locator( '.wp-stats' ) ).not.toContainText( 'Alpha Stats' );

			await expect( guest.locator( '.e2e-body-wp_beta' ) ).toBeVisible();
			await expect( guest.locator( '.wp-stats' ) ).toContainText( 'Beta Stats' );
		} );
	} );

	test( 'the same sections appear on the admin screen', async ( { page } ) => {
		installSections( [ { key: 'wp_alpha', title: 'Alpha Stats', priority: 10 } ] );

		await page.goto( '/wp-admin/admin.php?page=wp-stats' );

		// The admin screen runs the assembled page through wp_kses_post(),
		// which a contributor's markup has to survive: a class attribute and a
		// paragraph are both things it keeps, and a section that came out blank
		// there while working on the front end is a difference nothing else
		// would notice.
		await expect( page.locator( '.wp-stats' ) ).toContainText( 'Alpha Stats' );
		await expect( page.locator( '.e2e-body-wp_alpha' ) ).toHaveText( 'body of wp_alpha' );
	} );
} );
