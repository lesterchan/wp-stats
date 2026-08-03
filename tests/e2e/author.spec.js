/**
 * The per-commenter view, reached by ?stats_author= on the statistics page.
 *
 * It is the only part of this plugin that pages, and the paging is the reason
 * the file exists: thirty comments per page and ten page links either side is a
 * lot of arithmetic for something nobody looks at twice, and it is reached by
 * clicking the links the page rendered rather than by building a URL, because
 * the tests environment ships plain permalinks and the Stats URL setting is
 * what every one of those links is built from.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	asGuest,
	createStatsPage,
	deleteAllComments,
	insertComment,
	insertComments,
	resetOptions,
	setAllToggles,
	setOptions,
} = require( './helpers.js' );

/** What WP_Stats_Page::PER_PAGE is, which decides where page two starts. */
const PER_PAGE = 30;

test.describe( 'The per-commenter view', () => {
	let statsPage;
	let firstPost;
	let secondPost;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
		deleteAllComments();

		firstPost = await requestUtils.createPost( {
			title: 'Author view first post',
			content: 'Body.',
			status: 'publish',
		} );
		secondPost = await requestUtils.createPost( {
			title: 'Author view second post',
			content: 'Body.',
			status: 'publish',
		} );

		// Thirty-five by one person, which is one full page and a short one.
		insertComments( { postId: firstPost.id, author: 'Prolific', count: PER_PAGE + 5 } );
		// And two more on another post, so the grouping by post has something
		// to group. Dated later, so they lead the listing.
		insertComment( {
			postId: secondPost.id,
			author: 'Prolific',
			content: 'On the second post.',
			minutesAgo: 0,
		} );
		// Five, not one: the Comment Members block on the overview is rendered
		// with a threshold of five, so anybody below that has no link to click
		// and this file reaches the author view through the links the page
		// rendered rather than by building a URL.
		insertComments( { postId: firstPost.id, author: 'Quiet', count: 5, offset: 60 } );

		statsPage = await createStatsPage( requestUtils );
	} );

	test.afterAll( async () => {
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
		setOptions( { url: statsPage.link, most_limit: 10 } );
		setAllToggles( 1 );
	} );

	test( 'the fixture really is more than one page of comments by one person', async ( {
		page,
	} ) => {
		// Everything about paging below leans on this. With thirty-six comments
		// there are two pages; with thirty or fewer the paging strip is never
		// rendered at all and every assertion about it would be about markup
		// that was never meant to be there.
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			await guest.getByRole( 'link', { name: 'Prolific' } ).click();

			await expect( guest.locator( '.wp-stats' ) ).toContainText( 'Of 36 Comments' );
			await expect( guest.locator( '.wp-pagenavi' ) ).toBeVisible();
		} );
	} );

	test( 'the Comment Members link opens that person\'s comments', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// Through the link the page rendered, not a hand-built URL: the
			// Stats URL setting is what that link is made of, and a page that
			// links somewhere unreachable is exactly the failure this catches.
			await guest.getByRole( 'link', { name: 'Prolific' } ).click();

			await expect(
				guest.getByRole( 'heading', { name: 'Comments Posted By Prolific' } ),
			).toBeVisible();
			await expect( guest.locator( '.wp-stats' ) ).toContainText( 'Displaying 1 To 30 Of 36' );
			await expect( guest.locator( '.wp-stats blockquote' ) ).toHaveCount( PER_PAGE );
		} );
	} );

	test( 'each comment says who left it and when, and links to itself', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );
			await guest.getByRole( 'link', { name: 'Prolific' } ).click();

			const first = guest.locator( '.wp-stats blockquote' ).first();

			await expect( first ).toContainText( 'Posted By' );
			await expect( first ).toContainText( 'Prolific' );
			await expect( first.locator( 'a' ) ).toHaveAttribute( 'href', /#comment-\d+$/ );
		} );
	} );

	test( 'consecutive comments on one post share a single heading', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );
			await guest.getByRole( 'link', { name: 'Prolific' } ).click();

			// Thirty comments on this page span two posts, so exactly two post
			// headings: one per run of comments on the same post, not one per
			// comment.
			const headings = guest.locator( '.wp-stats > p > strong > a' );

			await expect( headings ).toHaveCount( 2 );
			await expect( headings.first() ).toHaveText( 'Author view second post' );
			await expect( headings.nth( 1 ) ).toHaveText( 'Author view first post' );
		} );
	} );

	test( 'the paging strip reaches the second page and back', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );
			await guest.getByRole( 'link', { name: 'Prolific' } ).click();

			await expect( guest.locator( '.wp-pagenavi .pages' ) ).toContainText( 'Page 1 of 2' );
			await expect( guest.locator( '.wp-pagenavi .current' ) ).toHaveText( /1/ );

			await guest.locator( '.wp-pagenavi a', { hasText: /^\s*2\s*$/ } ).first().click();

			// Six left over, and the counter has to say which six.
			await expect( guest.locator( '.wp-stats' ) ).toContainText( 'Displaying 31 To 36 Of 36' );
			await expect( guest.locator( '.wp-stats blockquote' ) ).toHaveCount( 6 );
			await expect( guest.locator( '.wp-pagenavi .current' ) ).toHaveText( /2/ );

			// And back, through the « link the second page draws and the first
			// one does not.
			await guest.locator( '.wp-pagenavi a' ).first().click();
			await expect( guest.locator( '.wp-stats' ) ).toContainText( 'Displaying 1 To 30 Of 36' );
		} );
	} );

	test( 'one page of comments draws no paging strip at all', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// The other commenter left five comments, well inside one page, so
			// there is nothing to page through -- and a strip saying "Page 1 of
			// 1" would be noise on every small site.
			await guest.getByRole( 'link', { name: 'Quiet' } ).click();

			await expect( guest.locator( '.wp-stats' ) ).toContainText( 'Displaying 1 To 5 Of 5' );
			await expect( guest.locator( '.wp-pagenavi' ) ).toHaveCount( 0 );
		} );
	} );

	test( 'a name nobody has commented under says so plainly', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			// Built by hand this once, because there is deliberately no link to
			// a commenter who does not exist.
			//
			// Through URL rather than by concatenating "&", because the page's
			// own link only carries a query string on a site with plain
			// permalinks. On a pretty one it is /stats/, and appending "&…"
			// gives /stats/&stats_author=Nobody -- no query var, so the drill-
			// down never happens and the heading this asserts is simply absent.
			// WordPress turns pretty permalinks on at install time, so that is
			// what CI has and a drifted local wp-env does not.
			const url = new URL( statsPage.link );
			url.searchParams.set( 'stats_author', 'Nobody' );

			await guest.goto( url.toString() );

			await expect(
				guest.getByRole( 'heading', { name: 'Comments Posted By Nobody' } ),
			).toBeVisible();
			await expect( guest.locator( '.wp-stats' ) ).toContainText(
				'Nobody has not made any comments yet.',
			);
			await expect( guest.locator( '.wp-stats blockquote' ) ).toHaveCount( 0 );
		} );
	} );

	test( 'Back To Stats Page returns to the overview', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );
			await guest.getByRole( 'link', { name: 'Quiet' } ).click();

			await guest.getByRole( 'link', { name: 'Back To Stats Page' } ).click();

			// The overview, not the author view: the General Stats block only
			// exists on the one the shortcode renders by default.
			await expect( guest.locator( '#GeneralStats' ) ).toBeVisible();
			await expect( guest.locator( '.wp-stats blockquote' ) ).toHaveCount( 0 );
		} );
	} );

	test( 'the admin screen shows the overview and sends this view to the public page', async ( {
		page,
	} ) => {
		// The two arguments are registered query variables, which means
		// WordPress parses them on a front-end request and not on an admin one
		// -- so the screen under wp-admin renders the overview however the URL
		// is decorated. That is not a gap: the links it draws lead to the public
		// page, which is where the per-commenter view lives.
		await page.goto( '/wp-admin/admin.php?page=wp-stats&stats_author=Quiet' );

		await expect( page.locator( '#GeneralStats' ) ).toBeVisible();
		await expect( page.locator( '.wp-stats blockquote' ) ).toHaveCount( 0 );

		const href = await page
			.locator( '.wp-stats' )
			.getByRole( 'link', { name: 'Quiet' } )
			.first()
			.getAttribute( 'href' );

		expect( href.startsWith( statsPage.link ) ).toBe( true );
		expect( href ).toContain( 'stats_author=Quiet' );
	} );

	test( 'a page number past the end shows the last of the comments rather than failing', async ( {
		page,
	} ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( `${ statsPage.link }&stats_author=Prolific&stats_page=99` );

			// The offset runs past the total, so the counter is clamped to the
			// total rather than printing a number larger than there are
			// comments -- and the page still renders.
			await expect( guest.locator( '.wp-stats' ) ).toContainText( 'Of 36 Comments' );
			await expect( guest.locator( '.wp-pagenavi' ) ).toBeVisible();
		} );
	} );

	test( 'a hostile name in the query variable is rendered as text', async ( { page } ) => {
		// An attribute breakout rather than a script tag. The name arrives
		// through sanitize_text_field(), which strips a script element *and its
		// contents* -- so a script payload could not survive as text here even
		// in principle, and asserting that it did would be asserting the wrong
		// thing about core. This one it keeps, which is what makes both halves
		// of the assertion meaningful.
		const payload = '" onmouseover="window.__pwned = 1';

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto(
				`${ statsPage.link }&stats_author=${ encodeURIComponent( `Nobody ${ payload }` ) }`,
			);

			// The name is echoed into the heading and into the "has not made any
			// comments" sentence, so it is attacker-controlled input on a public
			// page even before anything has been stored.
			expect( await guest.evaluate( () => window.__pwned === 1 ) ).toBe( false );
			await expect( guest.locator( '.wp-stats [onmouseover]' ) ).toHaveCount( 0 );
			await expect( guest.locator( '.wp-stats' ) ).toContainText( 'onmouseover' );
		} );
	} );

	test( 'the fixture page really is the one the Stats URL setting names', async ( { page } ) => {
		// Every link tested above is built from that setting rather than from
		// the current URL, so a setting pointing somewhere else would send a
		// visitor off the page and this file would be testing a 404.
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			const link = guest.getByRole( 'link', { name: 'Quiet' } );
			const href = await link.getAttribute( 'href' );

			expect( href.startsWith( statsPage.link ) ).toBe( true );
			expect( href ).toContain( 'stats_author=Quiet' );
		} );
	} );
} );
