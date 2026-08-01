/**
 * The template tags, which are the plugin's public API.
 *
 * Themes in the wild call every one of these by name, they are documented in
 * the readme, and none of them is deprecated -- so their names, their argument
 * order and the echo/return convention they share are all part of what must not
 * change. twentytwentyone calls none of them, so the probe mu-plugin stands in
 * for the theme that would.
 *
 * The echo/return convention is worth a test of its own rather than being taken
 * on trust: every tag defaults to echoing, the probe mostly asks for the return
 * value, and a tag that echoed when asked to return would print the numbers
 * into the middle of whatever the theme was building.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	asGuest,
	createStatsPage,
	deleteAllComments,
	deleteAllLinks,
	insertComment,
	insertLink,
	installProbe,
	removeProbe,
	resetOptions,
	setAllToggles,
	setOptions,
	wpEval,
	wpEvalJson,
} = require( './helpers.js' );

test.describe( 'The template tags', () => {
	let statsPage;
	let popular;
	let quiet;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
		deleteAllComments();
		deleteAllLinks();
		installProbe();

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
			data: { name: 'Tags spec tag' },
		} );

		quiet = await requestUtils.createPost( {
			title: 'Quiet post',
			content: 'Body.',
			status: 'publish',
		} );
		popular = await requestUtils.createPost( {
			title: 'Tags popular post with a very long title indeed',
			content: 'Body.',
			status: 'publish',
			tags: [ tag.id ],
		} );

		insertComment( { postId: popular.id, author: 'Ada', minutesAgo: 30 } );
		insertComment( { postId: popular.id, author: 'Ada', minutesAgo: 20 } );
		insertComment( { postId: quiet.id, author: 'Solo', minutesAgo: 10 } );

		insertLink( 'A useful site', 'Tags spec links' );

		statsPage = await createStatsPage( requestUtils );
	} );

	test.afterAll( async () => {
		removeProbe();
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
		setOptions( { url: statsPage.link, most_limit: 10 } );
		setAllToggles( 1 );
	} );

	test( 'the fixture really is the probe calling the tags on the front end', async ( { page } ) => {
		// Without the probe none of this plugin's tags is called on any page,
		// so every locator below would find nothing and every "not present"
		// assertion would pass for the wrong reason.
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( quiet.link );

			await expect( guest.locator( '#st-posts' ) ).toHaveText( '2' );
			await expect( guest.locator( '#st-recent-posts li' ) ).toHaveCount( 2 );
		} );
	} );

	test( 'the six total tags each return their own count', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( quiet.link );

			// Two posts, one page, three comments, two names, one link -- all
			// of which this spec put there itself. Authors is not exact,
			// because other spec files create accounts, so it is asserted as
			// "at least one" rather than as a number.
			await expect( guest.locator( '#st-posts' ) ).toHaveText( '2' );
			await expect( guest.locator( '#st-pages' ) ).toHaveText( '1' );
			await expect( guest.locator( '#st-comments' ) ).toHaveText( '3' );
			await expect( guest.locator( '#st-commentposters' ) ).toHaveText( '2' );
			await expect( guest.locator( '#st-links' ) ).toHaveText( '1' );
			await expect( guest.locator( '#st-authors' ) ).not.toHaveText( '0' );
		} );
	} );

	test( 'a tag asked to display prints instead of returning', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( quiet.link );

			// The echoing form is the default, and it is the one a theme
			// writes. Both containers hold the same number, from the same tag,
			// called the two different ways.
			await expect( guest.locator( '#st-posts-echoed' ) ).toHaveText( '2' );
			await expect( guest.locator( '#st-recent-posts-echoed li' ) ).toHaveCount( 2 );
		} );
	} );

	test( 'the listing tags render linked list items', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( quiet.link );

			await expect( guest.locator( '#st-recent-posts li a' ).first() ).toHaveText(
				'Tags popular post with a very long title indeed',
			);
			await expect( guest.locator( '#st-recent-comments li' ).first() ).toContainText( 'Solo' );
			await expect( guest.locator( '#st-most-commented li' ).first() ).toContainText(
				'2 comments',
			);
			await expect( guest.locator( '#st-authors-list li' ).first() ).toContainText( 'admin' );
			await expect( guest.locator( '#st-post-cats li' ).first() ).toContainText( 'Uncategorized' );
			await expect( guest.locator( '#st-link-cats' ) ).toContainText( 'Tags spec links (1)' );
			await expect( guest.locator( '#st-tags' ) ).toContainText( 'Tags spec tag (1)' );
		} );
	} );

	test( 'the characters argument truncates a long title and leaves a short one alone', async ( {
		page,
	} ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( quiet.link );

			// Twelve characters, from the probe. The short title comes back
			// untouched, which is the half that matters: the ellipsis is only
			// added when something was actually cut.
			await expect( guest.locator( '#st-most-commented-chars li a' ).first() ).toHaveText(
				'Tags popular...',
			);
			await expect( guest.locator( '#st-most-commented-chars li a' ).nth( 1 ) ).toHaveText(
				'Quiet post',
			);
		} );
	} );

	test( 'the comment members threshold hides anyone below it', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( quiet.link );

			// Ada left two and Solo one, and the probe asks for a threshold of
			// two -- so the unrestricted list has both and the restricted one
			// has Ada alone. Both on the same page, so the difference is the
			// threshold rather than the fixture.
			await expect( guest.locator( '#st-comment-members li' ) ).toHaveCount( 2 );
			await expect( guest.locator( '#st-comment-members-threshold li' ) ).toHaveText( [
				'Ada (2)',
			] );
		} );
	} );

	test( 'stats_page_link builds a link into the per-commenter view', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( quiet.link );

			const link = await guest.locator( '#st-page-link' ).textContent();

			expect( link.startsWith( statsPage.link ) ).toBe( true );
			expect( link ).toContain( 'stats_author=Somebody' );
			expect( link ).toContain( 'stats_page=3' );
		} );
	} );

	test( 'snippet_text truncates to a character count and encodes what is left', async ( {
		page,
	} ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( quiet.link );

			await expect( guest.locator( '#st-snippet' ) ).toHaveText( 'A rather l...' );
		} );

		// Encoding as well as truncating, which is what makes it safe to print
		// a title through. The name is very generic, so the plugin guards the
		// declaration -- and WP-PostViews, which used to define it too, keeps
		// its own copy on a class now.
		expect( wpEvalJson( 'snippet_text( "<b>bold</b>", 100 )' ) ).toBe( '&lt;b&gt;bold&lt;/b&gt;' );
		expect( wpEvalJson( 'function_exists( "snippet_text" )' ) ).toBe( true );
	} );

	test( 'stats_page returns the whole page rather than printing it', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( quiet.link );

			// The probe only writes the length into an attribute, so a tag that
			// echoed instead of returning would put the entire statistics page
			// into the middle of the footer -- which is exactly what the
			// assertion below would catch.
			const length = parseInt(
				await guest.locator( '#st-page' ).getAttribute( 'data-length' ),
				10,
			);

			expect( length ).toBeGreaterThan( 100 );
			await expect( guest.locator( '#st-page' ) ).toHaveText( '' );
			await expect( guest.locator( 'footer .wp-stats' ) ).toHaveCount( 0 );
		} );
	} );
} );
