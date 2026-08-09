/**
 * The statistics page a visitor sees, block by block.
 *
 * Every number here is a count of somebody else's data -- posts, pages,
 * comments, terms, links, users -- so the fixture is the site itself. The
 * beforeAll empties everything the assertions count and then puts back a known
 * set, because a total is only worth asserting against a site whose contents
 * the test decided.
 *
 * The page is reached through the [page_stats] shortcode rather than the admin
 * screen: the shortcode is the surface a visitor gets, it renders the identical
 * string, and it is the one of the two that does *not* pass the markup through
 * wp_kses_post() on the way out.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	TOGGLES,
	asGuest,
	createStatsPage,
	deleteAllComments,
	deleteAllLinks,
	insertComment,
	insertComments,
	insertLink,
	installMuPlugin,
	option,
	removeMuPlugin,
	removeSections,
	resetOptions,
	setAllToggles,
	setOptions,
	wpEval,
} = require( './helpers.js' );

/**
 * Patterns matching one Total Stats line each, in either grammatical number.
 *
 * Every line in that block is pluralised with _n_noop(), and the two arms do
 * not share a common substring: the singular is "1 tag was created." and the
 * plural "2 tags were created.", and the nickname line gains the word
 * "different" only in the plural. A locator written against one arm therefore
 * matches nothing at all the moment the count is not the one the fixture
 * expected -- and Playwright reports that as a timeout waiting for an element,
 * which says nothing about the count and sends you looking in the wrong place.
 *
 * Matching both arms means a wrong count fails on the number, with the line's
 * text in the diff.
 */
const TOTALS = {
	authors: /authors? to this blog/,
	posts: /posts? (?:was|were) posted/,
	pages: /pages? (?:was|were) created/,
	tags: /tags? (?:was|were) created/,
	comments: /comments? (?:was|were) posted/,
	nicknames: /nicknames? (?:was|were) represented/,
	links: /links? (?:was|were) added/,
	postCategories: /post categor(?:y|ies) (?:was|were) needed/,
	linkCategories: /link categor(?:y|ies) (?:was|were) needed/,
};

/**
 * The list that follows a block's heading.
 *
 * The page is a flat run of `<p><strong>heading</strong></p>` followed by a
 * list, so the honest way to find one block is the element after its own
 * heading rather than an nth-child guess that shifts every time a toggle is
 * turned off.
 *
 * @param {import('@playwright/test').Page} page    Page showing the statistics.
 * @param {string}                          heading Exact heading text.
 * @return {import('@playwright/test').Locator} The ul or ol under it.
 */
function block( page, heading ) {
	return page
		.locator( '.wp-stats > p', { has: page.locator( `strong:text-is("${ heading }")` ) } )
		.locator( 'xpath=following-sibling::*[1]' );
}

/**
 * One line of the General Stats bullet list.
 *
 * @param {import('@playwright/test').Page} page    Page showing the statistics.
 * @param {RegExp}                          pattern One of TOTALS, matching either grammatical number.
 * @return {import('@playwright/test').Locator} The matching list item.
 */
function totalLine( page, pattern ) {
	return block( page, 'Total Stats' ).locator( 'li' ).filter( { hasText: pattern } );
}

test.describe( 'The statistics page', () => {
	let statsPage;
	let popular;
	let quiet;
	let unloved;
	let tagId;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
		deleteAllComments();
		deleteAllLinks();

		// Terms as well: the tag and link-category counts below are exact, and
		// a term left over from another spec file would make them wrong for a
		// reason that has nothing to do with this plugin.
		wpEval(
			`foreach ( array( 'post_tag', 'link_category' ) as $taxonomy ) {
				foreach ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $term ) {
					wp_delete_term( (int) $term->term_id, $taxonomy );
				}
			}
			echo '<<<done>>>';`,
		);

		const tag = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/tags',
			data: { name: 'Statistics tag' },
		} );
		tagId = tag.id;

		// Sequentially, because the recent-posts listing is ordered by date and
		// three posts created at once land in the same second, where the tie
		// breaks on whichever request the server reached first.
		unloved = await requestUtils.createPost( {
			title: 'Stats unloved post',
			content: 'Nobody said anything.',
			status: 'publish',
		} );
		quiet = await requestUtils.createPost( {
			title: 'Stats quiet post',
			content: 'One person said something.',
			status: 'publish',
			tags: [ tagId ],
		} );
		popular = await requestUtils.createPost( {
			title: 'Stats popular post with a very long title indeed',
			content: 'Everybody said something.',
			status: 'publish',
		} );

		// Six and five, because the Comment Members block on the overview is
		// rendered with a threshold of five: anybody below that is dropped from
		// it, so a fixture of two comments each would make the block empty and
		// every assertion about it meaningless. Solo's single comment is the
		// other side of that threshold, and the singular arm of the "%s
		// comment" wording besides.
		//
		// Offset in minutes, so the three runs cannot land in the same second
		// and leave "the most recent comment" to be decided by insertion order.
		insertComments( { postId: popular.id, author: 'Ada', count: 6, offset: 20 } );
		insertComments( { postId: quiet.id, author: 'Grace', count: 5, offset: 10 } );
		insertComment( { postId: unloved.id, author: 'Solo', minutesAgo: 1 } );

		insertLink( 'A useful site', 'Statistics links' );

		statsPage = await createStatsPage( requestUtils );
	} );

	test.afterAll( async () => {
		removeSections();
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
		setOptions( { url: statsPage.link, most_limit: 10 } );
		setAllToggles( 1 );
	} );

	test.afterEach( async () => {
		resetOptions();
	} );

	test( 'the fixture really is three posts, one page and twelve comments', async ( { page } ) => {
		// Every count below leans on this, and on nothing else on the site
		// having posts or comments. If another spec file ever leaves content
		// behind, this is the test that says so plainly rather than the eight
		// that would each fail by one.
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			await expect( guest.locator( '.wp-stats' ) ).toBeVisible();
			await expect( totalLine( guest, TOTALS.posts ) ).toContainText( '3' );
			await expect( totalLine( guest, TOTALS.pages ) ).toContainText( '1' );
			await expect( totalLine( guest, TOTALS.comments ) ).toContainText( '12' );
		} );
	} );

	test( 'the General Stats block counts every kind of thing on the site', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// Three names left the twelve comments, one tag, one link and one
			// link category, all of which this spec put there itself.
			await expect( totalLine( guest, TOTALS.nicknames ) ).toContainText( '3' );
			await expect( totalLine( guest, TOTALS.tags ) ).toContainText( '1' );
			await expect( totalLine( guest, TOTALS.links ) ).toContainText( '1' );
			await expect( totalLine( guest, TOTALS.linkCategories ) ).toContainText( '1' );

			// Authors and post categories are not exact -- other spec files
			// create users, and the default category is always there -- so the
			// assertion is that the line is there and its number is not zero.
			await expect( totalLine( guest, TOTALS.authors ) ).toHaveText( /^[1-9]/ );
			await expect( totalLine( guest, TOTALS.postCategories ) ).toHaveText( /^[1-9]/ );
		} );
	} );

	test( 'Akismet is absent, so no spam line is offered', async ( { page } ) => {
		// The spam count is the one line that is not always there: WP-Stats asks
		// whether Akismet's class is loaded, because Akismet publishes no filter
		// and is not part of the wp_stats_sections contract.
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			await expect( block( guest, 'Total Stats' ).locator( 'li', { hasText: 'spam' } ) ).toHaveCount(
				0,
			);
		} );
	} );

	test( 'Recent Posts lists the newest first, with its author', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			await expect( block( guest, '10 Recent Posts' ).locator( 'li a' ) ).toHaveText( [
				'Stats popular post with a very long title indeed',
				'Stats quiet post',
				'Stats unloved post',
			] );
			await expect( block( guest, '10 Recent Posts' ).locator( 'li' ).first() ).toContainText(
				'admin',
			);
		} );
	} );

	test( 'Recent Comments lists the newest first, linked to the post', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			const items = block( guest, '10 Recent Comments' ).locator( 'li' );

			// Newest first, and the fixture's timestamps are minutes apart so
			// the order cannot come out of a tie.
			await expect( items.first() ).toContainText( 'Solo' );
			await expect( items.first() ).toContainText( 'Stats unloved post' );
			await expect( items.first().locator( 'a' ) ).toHaveAttribute( 'href', /#comment-\d+$/ );
			// Ten of the twelve, because the entry limit says ten.
			await expect( items ).toHaveCount( 10 );
		} );
	} );

	test( 'Most Commented Posts is ordered by comment count', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			const items = block( guest, '10 Most Commented Posts' ).locator( 'li' );

			await expect( items.first() ).toContainText(
				'Stats popular post with a very long title indeed',
			);
			await expect( items.first() ).toContainText( '6 comments' );
			// The singular form as well, which is a different branch of the
			// same _n() call.
			await expect( items.nth( 2 ) ).toContainText( '1 comment' );
		} );
	} );

	test( 'Most Commented Pages is scoped to pages rather than posts', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// The only page on the site is the statistics page itself, so a
			// listing that ignored the post type would show four entries here.
			await expect( block( guest, '10 Most Commented Pages' ).locator( 'li' ) ).toHaveCount( 1 );
			await expect( block( guest, '10 Most Commented Pages' ) ).toContainText(
				statsPage.title.rendered,
			);
		} );
	} );

	test( 'Authors lists the people who publish, with their totals', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			const list = block( guest, 'Authors' );

			// An ordered list, not a bullet one: this block and Comment Members
			// are the two that are rankings.
			await expect( list ).toHaveJSProperty( 'tagName', 'OL' );
			await expect( list.locator( 'li' ).first() ).toContainText( 'admin' );
			await expect( list.locator( 'li a' ).first() ).toHaveAttribute( 'href', /author/ );
		} );
	} );

	test( 'Comment Members ranks commenters and links into their own view', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			const items = block( guest, 'Comment Members' ).locator( 'li' );

			// Ada left six and Grace five, so they rank in that order. Solo left
			// one and is missing: this block is rendered with a threshold of
			// five, which is the only place that argument is exercised at all.
			await expect( items ).toHaveText( [ 'Ada (6)', 'Grace (5)' ] );
			await expect( items.first().locator( 'a' ) ).toHaveAttribute( 'href', /stats_author=Ada/ );
		} );
	} );

	test( 'the Misc blocks list categories, link categories and tags', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			await expect( block( guest, 'Post Categories' ).locator( 'li' ).first() ).toContainText(
				'Uncategorized',
			);
			await expect( block( guest, 'Link Categories' ) ).toContainText( 'Statistics links (1)' );
			await expect( block( guest, 'Tags List' ) ).toContainText( 'Statistics tag (1)' );
			await expect( block( guest, 'Tags List' ).locator( 'a' ) ).toHaveAttribute(
				'href',
				/tag/,
			);
		} );
	} );

	test( 'a block with nothing in it says N/A rather than showing an empty list', async ( {
		page,
	} ) => {
		deleteAllComments();

		try {
			await asGuest( page, {}, async ( guest ) => {
				await guest.goto( statsPage.link );

				await expect( block( guest, '10 Recent Comments' ).locator( 'li' ) ).toHaveText( [
					'N/A',
				] );
				await expect( block( guest, 'Comment Members' ).locator( 'li' ) ).toHaveText( [ 'N/A' ] );
			} );
		} finally {
			// Put the fixture back for every test that follows, which is the
			// whole file.
			insertComments( { postId: popular.id, author: 'Ada', count: 6, offset: 20 } );
			insertComments( { postId: quiet.id, author: 'Grace', count: 5, offset: 10 } );
			insertComment( { postId: unloved.id, author: 'Solo', minutesAgo: 1 } );
		}
	} );

	for ( const [ key, heading ] of Object.entries( TOGGLES ) ) {
		test( `the "${ key }" toggle decides whether its block is on the page`, async ( { page } ) => {
			// Both directions in the same test. "The block is gone" on its own
			// passes with the plugin deactivated, because then there is no page
			// at all.
			await asGuest( page, {}, async ( guest ) => {
				await guest.goto( statsPage.link );
				// The block, not the words: "Authors Stats" is a section heading
				// that is always there and contains the "Authors" block heading,
				// so a text search would say the block survived when only its
				// section had.
				await expect( block( guest, heading ) ).toHaveCount( 1 );

				setOptions( { display: { [ key ]: 0 } } );

				await guest.goto( statsPage.link );
				await expect( block( guest, heading ) ).toHaveCount( 0 );
				// The page itself is still there, so the block went rather than
				// the whole render failing.
				await expect( guest.locator( '.wp-stats' ) ).toBeVisible();
			} );
		} );
	}

	test( 'the section headings stay on the page even when their blocks are off', async ( {
		page,
	} ) => {
		// The six h2s are anchors themes style and link to, and they are printed
		// unconditionally -- unlike Plugins Stats, which only appears when
		// something contributed. Turning every block off is what tells the two
		// kinds apart.
		setAllToggles( 0 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			for ( const id of [
				'TopRecentStats',
				'TopMostHighestStats',
				'AuthorsStats',
				'CommentsMembersStats',
				'MiscStats',
			] ) {
				await expect( guest.locator( `#${ id }` ) ).toBeAttached();
			}

			// General Stats is the exception: its heading is inside the toggle.
			await expect( guest.locator( '#GeneralStats' ) ).toHaveCount( 0 );
			await expect( guest.locator( '#PluginsStats' ) ).toHaveCount( 0 );
		} );
	} );

	test( 'the entry limit decides how many rows each listing carries and what it is called', async ( {
		page,
	} ) => {
		setOptions( { most_limit: 2 } );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// The number is in the heading as well as in the query, and the two
			// coming from one setting is the thing worth pinning: a heading
			// saying ten above a list of two is the bug this shape prevents.
			await expect( block( guest, '2 Recent Posts' ).locator( 'li' ) ).toHaveCount( 2 );
			await expect( block( guest, '2 Recent Comments' ).locator( 'li' ) ).toHaveCount( 2 );
			await expect( guest.locator( '#TopRecentStats' ) ).toHaveText( 'Top 2 Recent Stats' );
		} );

		expect( option( 'most_limit' ) ).toBe( 2 );
	} );

	test( 'a limit of one uses the singular wording throughout', async ( { page } ) => {
		setOptions( { most_limit: 1 } );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// Every heading on this page is an _n() call, and the singular arm
			// is only ever reached at a limit of one.
			await expect( guest.locator( '#TopRecentStats' ) ).toHaveText( 'Top 1 Recent Stat' );
			await expect( block( guest, '1 Recent Post' ).locator( 'li' ) ).toHaveCount( 1 );
		} );
	} );

	test( 'the wp_stats_page filter can rewrite the whole page', async ( { page } ) => {
		installMuPlugin(
			'wp-stats-e2e-page-filter.php',
			`<?php
/**
 * Plugin Name: WP-Stats E2E page filter
 * Description: Appends a marker to the assembled statistics page.
 */
add_filter(
	'wp_stats_page',
	function ( $output ) {
		return $output . '<p id="e2e-page-filter">a theme added this</p>';
	}
);
`,
		);

		try {
			await asGuest( page, {}, async ( guest ) => {
				await guest.goto( statsPage.link );

				// The filter runs after the wrapper is in place, so the marker
				// lands outside .wp-stats -- which is itself the documented
				// shape and worth pinning.
				await expect( guest.locator( '#e2e-page-filter' ) ).toBeVisible();
			} );
		} finally {
			removeMuPlugin( 'wp-stats-e2e-page-filter.php' );
		}
	} );

	test( 'the stylesheet loads on a page that renders statistics and nowhere else', async ( {
		page,
	} ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );
			await expect( guest.locator( 'link[href*="wp-stats.css"]' ) ).toHaveCount( 1 );

			// An ordinary post has no shortcode and no widget, so nothing on it
			// the stylesheet applies to.
			await guest.goto( popular.link );
			await expect( guest.locator( 'link[href*="wp-stats.css"]' ) ).toHaveCount( 0 );
		} );
	} );
} );
