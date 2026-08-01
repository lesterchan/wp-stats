/**
 * WP-Stats > Settings, and what each row stores.
 *
 * A setting that saves but does nothing, and a setting that does something but
 * will not save, are the two failures a screenshot cannot tell apart. The
 * effect half of the display toggles is proved block by block in page.spec.js,
 * so this file is about the other half: what the form posted is what the option
 * row ends up holding, through the sanitiser, and reloading the screen shows it
 * back.
 *
 * The sanitiser here is a pure function of what was posted -- it reads no stored
 * value at all, which is only possible because the upgrade markers live in a row
 * of their own. So every save rewrites the whole settings row, and a test that
 * changes one field is also asserting that the other eleven came back off the
 * form intact.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	TOGGLES,
	createStatsPage,
	openSettings,
	option,
	resetOptions,
	saveSettings,
	setOptions,
	uniqueTitle,
	wpEvalJson,
} = require( './helpers.js' );

test.describe( 'The settings screen', () => {
	let statsPage;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();

		statsPage = await createStatsPage( requestUtils );
	} );

	test.afterAll( async () => {
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
	} );

	test.afterEach( async () => {
		// Everything this file changes lives in one option row, so one reset
		// puts the whole screen back for the next test.
		resetOptions();
	} );

	test( 'the fixture really is both sections with all twelve controls on screen', async ( {
		page,
	} ) => {
		// Every test below fills or ticks something by id. If a field ever
		// stopped being registered the fills would find nothing, the saves would
		// post the defaults back, and the assertions would still pass.
		await openSettings( page );

		await expect( page.locator( '#wp_stats_url' ) ).toBeVisible();
		await expect( page.locator( '#wp_stats_most_limit' ) ).toBeVisible();

		await expect( page.getByRole( 'heading', { name: 'Type Of Stats To Display' } ) ).toBeVisible();

		for ( const key of Object.keys( TOGGLES ) ) {
			await expect( page.locator( `#wp_stats_display_${ key }` ) ).toBeAttached();
		}
		await expect( page.locator( 'input[type="checkbox"][id^="wp_stats_display_"]' ) ).toHaveCount(
			10,
		);
	} );

	test( 'the Stats URL saves and is what every link back to the page is built from', async ( {
		page,
	} ) => {
		await openSettings( page );

		await page.locator( '#wp_stats_url' ).fill( 'https://example.com/blog/stats/' );
		await saveSettings( page );

		expect( option( 'url' ) ).toBe( 'https://example.com/blog/stats/' );

		await openSettings( page );
		await expect( page.locator( '#wp_stats_url' ) ).toHaveValue( 'https://example.com/blog/stats/' );

		// The far end: the author links on the page itself are assembled from
		// this setting, so a URL that saved and was never read would leave every
		// commenter's name pointing at nothing.
		expect( wpEvalJson( 'WP_Stats_Page::author_link( "Ada" )' ) ).toBe(
			'https://example.com/blog/stats/?stats_author=Ada',
		);
	} );

	test( 'a Stats URL that already carries a query string gets an ampersand, not a question mark', async () => {
		// The tests environment ships plain permalinks, so the page this plugin
		// documents is reached as ?page_id=N -- which is exactly the case the
		// separator logic exists for, and the one a pretty-permalink developer
		// never sees.
		setOptions( { url: statsPage.link } );

		const link = wpEvalJson( 'WP_Stats_Page::author_link( "Ada", 2 )' );

		expect( link ).toContain( 'page_id=' );
		expect( link ).toContain( '&amp;stats_author=Ada' );
		expect( link ).toContain( '&amp;stats_page=2' );
	} );

	test( 'the Stats Most Limit saves and relabels the screen', async ( { page } ) => {
		await openSettings( page );

		await page.locator( '#wp_stats_most_limit' ).fill( '3' );
		await saveSettings( page );

		expect( option( 'most_limit' ) ).toBe( 3 );

		await openSettings( page );
		await expect( page.locator( '#wp_stats_most_limit' ) ).toHaveValue( '3' );

		// Half the labels on this screen count in the stored limit, which is why
		// they are rebuilt on every call rather than registered once.
		await expect( page.locator( 'label[for="wp_stats_display_recent_posts"]' ) ).toContainText(
			'3 Most Recent Posts',
		);
		await expect( page.locator( 'th', { hasText: 'Top 3 Recent Stats' } ) ).toBeVisible();
	} );

	test( 'a limit of zero is floored at one rather than stored', async ( { page } ) => {
		await openSettings( page );

		// The field carries min="1", so this goes in past the browser's own
		// validation to reach the server-side floor -- which is the one that
		// matters, because a stored zero would make every listing empty.
		await page.locator( '#wp_stats_most_limit' ).evaluate( ( el ) => {
			el.removeAttribute( 'min' );
			el.value = '0';
		} );
		await saveSettings( page );

		expect( option( 'most_limit' ) ).toBe( 1 );
	} );

	test( 'every display toggle stores both answers', async ( { page } ) => {
		// All ten in one test rather than ten near-identical ones: they are one
		// loop over one array in the sanitiser, and what is worth proving is
		// that none of them is dropped or written over its neighbour. Which
		// block each one governs is page.spec.js's business.
		await openSettings( page );

		for ( const key of Object.keys( TOGGLES ) ) {
			await page.locator( `#wp_stats_display_${ key }` ).uncheck();
		}
		await saveSettings( page );

		for ( const key of Object.keys( TOGGLES ) ) {
			expect( option( 'display' )[ key ] ).toBe( 0 );
		}

		await openSettings( page );
		for ( const key of Object.keys( TOGGLES ) ) {
			await expect( page.locator( `#wp_stats_display_${ key }` ) ).not.toBeChecked();
			await page.locator( `#wp_stats_display_${ key }` ).check();
		}
		await saveSettings( page );

		for ( const key of Object.keys( TOGGLES ) ) {
			expect( option( 'display' )[ key ] ).toBe( 1 );
		}
	} );

	test( 'an unticked box is stored as off rather than left as it was', async ( { page } ) => {
		setOptions( { display: { tags_list: 1 } } );

		await openSettings( page );
		await expect( page.locator( '#wp_stats_display_tags_list' ) ).toBeChecked();

		await page.locator( '#wp_stats_display_tags_list' ).uncheck();
		await saveSettings( page );

		// An unticked checkbox posts nothing at all, so the sanitiser walks the
		// toggles it knows about rather than the input. Getting that wrong gives
		// a box that can be ticked and never unticked.
		expect( option( 'display' ).tags_list ).toBe( 0 );
		// And its neighbour in the same row is untouched, so "absent means off"
		// did not become "absent means everything off".
		expect( option( 'display' ).post_cats ).toBe( 1 );
	} );

	test( 'a toggle the screen never offered cannot get into the row', async ( { page } ) => {
		await openSettings( page );

		// A key from an older release, when this row also carried a toggle per
		// companion plugin. Posted by hand, which is all it takes.
		await page.locator( '#wp_stats_display_tags_list' ).evaluate( ( el ) => {
			el.insertAdjacentHTML(
				'afterend',
				'<input type="checkbox" name="wp_stats_options[display][polls]" value="1" checked />',
			);
		} );
		await saveSettings( page );

		expect( Object.keys( option( 'display' ) ) ).not.toContain( 'polls' );
		expect( Object.keys( option( 'display' ) ).sort() ).toEqual( Object.keys( TOGGLES ).sort() );
	} );

	test( 'saving shows the confirmation notice', async ( { page } ) => {
		await openSettings( page );

		// Core only prints "Settings saved." from wp-admin/options-head.php,
		// which options-*.php includes and admin.php does not. This screen lives
		// under a menu of its own, so it calls settings_errors() itself -- and
		// without that the form saves and says nothing at all.
		await saveSettings( page );
	} );

	test( 'the settings survive a round trip through the form unchanged', async ( { page } ) => {
		setOptions( {
			url: statsPage.link,
			most_limit: 7,
			display: { commented_page: 1, tags_list: 1 },
		} );

		await openSettings( page );
		await saveSettings( page );

		// The sanitiser reads nothing but the form, so pressing Save without
		// touching anything is the test that the form renders everything the row
		// holds. Anything it failed to render would come back as a default.
		expect( option( 'url' ) ).toBe( statsPage.link );
		expect( option( 'most_limit' ) ).toBe( 7 );
		expect( option( 'display' ).commented_page ).toBe( 1 );
		expect( option( 'display' ).tags_list ).toBe( 1 );
	} );

	test( 'a URL with surrounding whitespace is trimmed on the way in', async ( { page } ) => {
		await openSettings( page );

		// A type="url" field will not submit this on its own, so it goes in past
		// the browser's validation to reach the sanitiser, which is where the
		// trim lives.
		await page.locator( '#wp_stats_url' ).evaluate( ( el ) => {
			el.type = 'text';
			el.value = '  https://example.com/stats/  ';
		} );
		await saveSettings( page );

		expect( option( 'url' ) ).toBe( 'https://example.com/stats/' );
	} );

	test( 'a fresh page carrying the shortcode can be pointed at from here', async ( {
		page,
		requestUtils,
	} ) => {
		// The setting and the page holding [page_stats] are two separate things,
		// and nothing keeps them in step, so the readme tells people to set one
		// to the other. This is that sequence, end to end.
		const another = await requestUtils.createPage( {
			title: uniqueTitle( 'Another statistics page' ),
			content: '[page_stats]',
			status: 'publish',
		} );

		await openSettings( page );
		await page.locator( '#wp_stats_url' ).fill( another.link );
		await saveSettings( page );

		expect( option( 'url' ) ).toBe( another.link );

		// The page renders the statistics, and the links the plugin builds now
		// lead back to it rather than to whatever the setting said before.
		await page.goto( another.link );
		await expect( page.locator( '#GeneralStats' ) ).toBeVisible();
		expect( wpEvalJson( 'WP_Stats_Page::author_link( "Ada" )' ).startsWith( another.link ) ).toBe(
			true,
		);
	} );
} );
