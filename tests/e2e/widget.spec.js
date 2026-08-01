/**
 * The Stats sidebar widget.
 *
 * The widget is a second renderer of the same numbers, with its own set of
 * toggles, and it is the only surface that pulls the whole thing through
 * wp_kses_post() before printing it. That last part is what these tests are
 * mostly about: an escaping pass over markup the widget assembled itself is the
 * kind of thing that quietly eats a list.
 *
 * The instance goes straight into widget_stats and sidebars_widgets rather than
 * through the widgets screen, which is the block editor's Legacy Widget wrapper
 * -- driving that would test Gutenberg rather than this plugin. What the form
 * and update() do with a submission is covered by tests/test-widget.php.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	addStatsWidget,
	asGuest,
	createStatsPage,
	deleteAllComments,
	insertComment,
	removeStatsWidget,
	resetOptions,
	setOptions,
} = require( './helpers.js' );

/** Every toggle the widget offers, with the wording it puts on the page. */
const LINES = {
	stats_total_authors: 'Author',
	stats_total_posts: 'Post',
	stats_total_pages: 'Page',
	stats_total_tags: 'Tag',
	stats_total_comments: 'Comment',
	stats_total_commenters: 'Comment Poster',
	stats_total_links: 'Link',
	stats_total_post_cat: 'Post Categor',
	stats_total_link_cat: 'Link Categor',
};

/**
 * An instance with every toggle on, overridden as the test wants.
 *
 * @param {Object} overrides What to change.
 * @return {Object} The instance.
 */
function instance( overrides = {} ) {
	const base = { title: 'Site Stats', limit: 10, chars: 200, show_link: 1 };

	for ( const key of Object.keys( LINES ) ) {
		base[ key ] = 1;
	}

	return { ...base, stats_total_spam: 1, stats_most_commented_post: 1, ...overrides };
}

test.describe( 'The Stats widget', () => {
	let statsPage;
	let popular;
	let quiet;
	let ordinary;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
		deleteAllComments();

		quiet = await requestUtils.createPost( {
			title: 'Widget quiet post',
			content: 'Body.',
			status: 'publish',
		} );
		popular = await requestUtils.createPost( {
			title: 'Widget popular post with a very long title indeed',
			content: 'Body.',
			status: 'publish',
		} );
		ordinary = await requestUtils.createPost( {
			title: 'Widget ordinary post',
			content: 'Somewhere to look at the sidebar.',
			status: 'publish',
		} );

		insertComment( { postId: popular.id, author: 'Ada' } );
		insertComment( { postId: popular.id, author: 'Grace' } );
		insertComment( { postId: quiet.id, author: 'Ada' } );

		statsPage = await createStatsPage( requestUtils );
	} );

	test.afterAll( async () => {
		removeStatsWidget();
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
		setOptions( { url: statsPage.link, most_limit: 10 } );
	} );

	test.afterEach( async () => {
		removeStatsWidget();
		resetOptions();
	} );

	test( 'the fixture really is a widget in the sidebar of an ordinary post', async ( { page } ) => {
		// Everything below asserts on markup inside #stats-9. If the sidebar
		// ever stopped rendering, every one of those assertions would fail for
		// the same uninteresting reason, and this is the test that says which.
		addStatsWidget( instance() );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( ordinary.link );

			await expect( guest.locator( '#stats-9' ) ).toBeVisible();
			await expect( guest.locator( '#stats-9' ) ).toContainText( 'Site Stats' );
			await expect( guest.locator( '#stats-9' ) ).toContainText( 'Total Stats' );
		} );
	} );

	test( 'every total the widget offers is on the page when it is switched on', async ( {
		page,
	} ) => {
		addStatsWidget( instance() );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( ordinary.link );

			const widget = guest.locator( '#stats-9' );

			for ( const wording of Object.values( LINES ) ) {
				await expect( widget ).toContainText( wording );
			}

			// The counts are real: three posts, one page, three comments and
			// two names, all of which this spec put there.
			await expect( widget ).toContainText( '3 Posts' );
			await expect( widget ).toContainText( '1 Page' );
			await expect( widget ).toContainText( '3 Comments' );
			await expect( widget ).toContainText( '2 Comment Posters' );
		} );
	} );

	test( 'each total toggle removes its own line and leaves the rest', async ( { page } ) => {
		// One test rather than nine: they are one loop over one array in the
		// widget, and what is worth proving is that a toggle takes its own line
		// and nothing else with it.
		const off = {};
		for ( const key of Object.keys( LINES ) ) {
			off[ key ] = 0;
		}
		off.stats_total_posts = 1;
		// The most commented block renders a nested list of its own, which the
		// count below would otherwise pick up.
		off.stats_most_commented_post = 0;

		addStatsWidget( instance( off ) );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( ordinary.link );

			const widget = guest.locator( '#stats-9' );

			await expect( widget ).toContainText( '3 Posts' );
			await expect( widget ).not.toContainText( 'Comment Poster' );
			await expect( widget ).not.toContainText( 'Link Categor' );
			// One line left under the Total Stats heading, not nine.
			await expect( widget.locator( 'ul ul > li' ) ).toHaveCount( 1 );
		} );
	} );

	test( 'the most commented list obeys the record limit and the title length', async ( { page } ) => {
		addStatsWidget( instance( { limit: 1, chars: 12 } ) );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( ordinary.link );

			const list = guest.locator( '#stats-9' ).getByRole( 'link', { name: /Widget/ } );

			// One entry because the limit says one, and truncated because chars
			// says twelve -- the pair rules out a widget that renders a fixed
			// listing whatever it was configured with.
			await expect( list ).toHaveCount( 1 );
			await expect( list.first() ).toHaveText( 'Widget popul...' );
			await expect( guest.locator( '#stats-9' ) ).toContainText( '1 Most Commented Post' );
		} );
	} );

	test( 'a title length of zero leaves the title alone', async ( { page } ) => {
		// The other side of the truncation: the ellipsis is only added when
		// something was actually cut, and 0 disables the limit entirely.
		addStatsWidget( instance( { limit: 1, chars: 0 } ) );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( ordinary.link );

			await expect(
				guest.locator( '#stats-9' ).getByRole( 'link', { name: /Widget/ } ).first(),
			).toHaveText( 'Widget popular post with a very long title indeed' );
		} );
	} );

	test( 'the most commented list can be switched off on its own', async ( { page } ) => {
		addStatsWidget( instance( { stats_most_commented_post: 0 } ) );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( ordinary.link );

			await expect( guest.locator( '#stats-9' ) ).not.toContainText( 'Most Commented Post' );
			// And the totals above it are still there, so the toggle took its
			// own block rather than everything after it.
			await expect( guest.locator( '#stats-9' ) ).toContainText( '3 Posts' );
		} );
	} );

	test( 'the link to the statistics page points at the Stats URL setting', async ( { page } ) => {
		addStatsWidget( instance( { show_link: 1 } ) );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( ordinary.link );

			const link = guest.locator( '#stats-9' ).getByRole( 'link', {
				name: 'My Blog Statistics',
			} );

			await expect( link ).toHaveAttribute( 'href', statsPage.link );

			// And it goes somewhere: a link built from a setting nobody filled
			// in used to land on the site root.
			await link.click();
			await expect( guest.locator( '.wp-stats #GeneralStats' ) ).toBeVisible();
		} );
	} );

	test( 'the link can be switched off', async ( { page } ) => {
		addStatsWidget( instance( { show_link: 0 } ) );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( ordinary.link );

			await expect(
				guest.locator( '#stats-9' ).getByRole( 'link', { name: 'My Blog Statistics' } ),
			).toHaveCount( 0 );
		} );
	} );

	test( 'the widget carries no spam line while Akismet is absent', async ( { page } ) => {
		addStatsWidget( instance( { stats_total_spam: 1 } ) );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( ordinary.link );

			// The toggle is on and the line still must not appear: the count is
			// read from Akismet's own class, and there is nothing to read.
			await expect( guest.locator( '#stats-9' ) ).not.toContainText( 'Spam Blocked' );
		} );
	} );

	test( 'the widget brings the stylesheet with it onto a page that has no shortcode', async ( {
		page,
	} ) => {
		addStatsWidget( instance() );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( ordinary.link );

			// An ordinary post, so the shortcode test in page.spec.js is not
			// what put the stylesheet there. An active widget is the other half
			// of the "is any of this on screen" question the enqueue asks.
			await expect( guest.locator( 'link[href*="wp-stats.css"]' ) ).toHaveCount( 1 );
		} );

		removeStatsWidget();

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( ordinary.link );
			await expect( guest.locator( 'link[href*="wp-stats.css"]' ) ).toHaveCount( 0 );
		} );
	} );
} );
