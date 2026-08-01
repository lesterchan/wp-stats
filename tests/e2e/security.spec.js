/**
 * The stored XSS regressions, in a real browser.
 *
 * Everything WP-Stats renders it read out of somebody else's table, and two of
 * those values are supplied by people who are not the site owner: a comment
 * author's name, which anyone who can leave a comment chooses, and a post title,
 * which is the row a compromised install already has. A display name and a term
 * name are the site's own, but they reach the page through the same lists.
 *
 * The payloads go in unsanitised, straight into the row, because sanitising on
 * the way in is the assumption under test rather than a step to reproduce.
 *
 * Every assertion has two halves: the sentinel the payload would set must never
 * become defined, and the payload's text must survive on the page. Escaping that
 * ate the value entirely passes the first half and is its own bug -- a commenter
 * who silently vanishes from the rankings because their name has an angle
 * bracket in it is not a fixed list.
 *
 * The front end and the admin screen are both checked, because they are not the
 * same path: the screen runs the assembled markup through wp_kses_post() and the
 * shortcode does not, so an escaping mistake can be invisible on one and live on
 * the other.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	STATS_URL,
	addStatsWidget,
	asGuest,
	createStatsPage,
	deleteAllComments,
	insertComments,
	insertLink,
	removeStatsWidget,
	resetOptions,
	setAllToggles,
	setOptions,
	setRawTitle,
	wpEval,
} = require( './helpers.js' );

const SCRIPT_PAYLOAD = '<script>window.__pwned = 1;</script>';
const IMG_PAYLOAD = '<img src=x onerror="window.__pwned = 1">';
const ATTR_PAYLOAD = '" onmouseover="window.__pwned = 1';

/**
 * Whether any payload managed to run.
 *
 * @param {import('@playwright/test').Page} target Page to ask.
 * @return {Promise<boolean>} True if the sentinel was set.
 */
function pwned( target ) {
	return target.evaluate( () => window.__pwned === 1 );
}

/**
 * Assert that a container holds nothing that could ever fire.
 *
 * A bare <img> is not by itself a finding -- wp_kses_post keeps one -- so the
 * assertion is about handlers and script elements, which is the property that
 * matters.
 *
 * @param {import('@playwright/test').Locator} container Where to look.
 * @return {Promise<void>} Resolves once every check has passed.
 */
async function expectNothingArmed( container ) {
	await expect( container.locator( 'script' ) ).toHaveCount( 0 );
	await expect( container.locator( '[onerror]' ) ).toHaveCount( 0 );
	await expect( container.locator( '[onmouseover]' ) ).toHaveCount( 0 );
}

test.describe( 'Hostile values on the statistics page', () => {
	let statsPage;
	let hostilePost;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
		deleteAllComments();

		hostilePost = await requestUtils.createPost( {
			title: 'Placeholder title',
			content: 'Body.',
			status: 'publish',
		} );

		// Straight into the posts table: wp_insert_post() sanitises a title for
		// anybody without unfiltered_html, and the row a compromised site has
		// never went through it.
		setRawTitle( hostilePost.id, `Hostile ${ SCRIPT_PAYLOAD } ${ IMG_PAYLOAD }` );

		// Comment author names are the classic vector here: whoever left the
		// comment chose the name, and it is printed in three separate lists.
		//
		// Five each, because the Comment Members block on the overview is
		// rendered with a threshold of five -- below that neither name appears
		// on the page at all and there would be no link to follow.
		insertComments( {
			postId: hostilePost.id,
			author: `Ada ${ SCRIPT_PAYLOAD }`,
			count: 5,
			offset: 20,
		} );
		insertComments( {
			postId: hostilePost.id,
			author: `Grace ${ ATTR_PAYLOAD }`,
			count: 5,
			offset: 1,
		} );

		insertLink( `Link ${ IMG_PAYLOAD }`, `Links ${ SCRIPT_PAYLOAD }` );

		// The site's own values, which reach the page through the same lists.
		wpEval(
			`global $wpdb;
			$admin = get_user_by( 'login', 'admin' );
			$wpdb->update(
				$wpdb->users,
				array( 'display_name' => base64_decode( '${ Buffer.from(
		`Admin ${ SCRIPT_PAYLOAD }`,
		'utf8',
	).toString( 'base64' ) }' ) ),
				array( 'ID' => $admin->ID ),
				array( '%s' ),
				array( '%d' )
			);
			clean_user_cache( $admin->ID );
			$term = wp_insert_term( 'Hostile tag placeholder', 'post_tag' );
			$wpdb->update(
				$wpdb->terms,
				array( 'name' => base64_decode( '${ Buffer.from(
		`Tag ${ IMG_PAYLOAD }`,
		'utf8',
	).toString( 'base64' ) }' ) ),
				array( 'term_id' => (int) $term['term_id'] ),
				array( '%s' ),
				array( '%d' )
			);
			clean_term_cache( (int) $term['term_id'], 'post_tag' );
			wp_set_post_terms( ${ hostilePost.id }, array( (int) $term['term_id'] ), 'post_tag' );
			echo '<<<done>>>';`,
		);

		statsPage = await createStatsPage( requestUtils );
	} );

	test.afterAll( async () => {
		removeStatsWidget();
		resetOptions();

		// The administrator's display name is global to the install, so it goes
		// back rather than being left for whichever spec file runs next.
		wpEval(
			`global $wpdb;
			$admin = get_user_by( 'login', 'admin' );
			$wpdb->update( $wpdb->users, array( 'display_name' => 'admin' ), array( 'ID' => $admin->ID ), array( '%s' ), array( '%d' ) );
			clean_user_cache( $admin->ID );
			echo '<<<done>>>';`,
		);
	} );

	test.beforeEach( async () => {
		resetOptions();
		setOptions( { url: statsPage.link, most_limit: 10 } );
		setAllToggles( 1 );
	} );

	test.afterEach( async () => {
		removeStatsWidget();
		resetOptions();
	} );

	test( 'the fixture really is unsanitised rows in the database', () => {
		// Straight out of the rows rather than through a template tag, so these
		// are the byte-for-byte payloads and not something a filter has already
		// tidied up on the way in.
		const stored = JSON.parse(
			wpEval(
				`global $wpdb;
				echo '<<<' . wp_json_encode( array(
					'title'  => $wpdb->get_var( $wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE ID = %d", ${ hostilePost.id } ) ),
					'author' => $wpdb->get_var( "SELECT comment_author FROM {$wpdb->comments} ORDER BY comment_ID ASC LIMIT 1" ),
					'user'   => $wpdb->get_var( "SELECT display_name FROM {$wpdb->users} WHERE user_login = 'admin'" ),
				) ) . '>>>';`,
			),
		);

		expect( stored.title ).toContain( '<script>window.__pwned = 1;</script>' );
		expect( stored.author ).toContain( '<script>window.__pwned = 1;</script>' );
		expect( stored.user ).toContain( '<script>window.__pwned = 1;</script>' );
	} );

	test( 'the public statistics page renders every hostile value as text', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			const stats = guest.locator( '.wp-stats' );

			expect( await pwned( guest ) ).toBe( false );
			await expectNothingArmed( stats );

			// And every one of them is still readable, which is the half a
			// sanitiser that simply deleted the value would pass.
			await expect( stats ).toContainText( 'Hostile' );
			await expect( stats ).toContainText( 'Ada' );
			await expect( stats ).toContainText( 'Grace' );
			await expect( stats ).toContainText( 'window.__pwned' );
		} );
	} );

	test( 'the admin screen renders them as text too', async ( { page } ) => {
		await page.goto( STATS_URL );

		// A different path from the front end: this one goes through
		// wp_kses_post() as well, which can hide an escaping mistake or make a
		// new one by eating a value the escaping had already made safe.
		const stats = page.locator( '.wp-stats' );

		expect( await pwned( page ) ).toBe( false );
		await expectNothingArmed( stats );
		await expect( stats ).toContainText( 'Ada' );
		await expect( stats ).toContainText( 'window.__pwned' );
	} );

	test( 'an attribute breakout in a name does not become a handler', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// The names are written into a title attribute as well as between
			// tags, which is the one place a quote can break out of.
			await expect( guest.locator( '.wp-stats [onmouseover]' ) ).toHaveCount( 0 );

			// Hovered, because a breakout that survived would only fire on the
			// event it named.
			const link = guest.locator( '.wp-stats a', { hasText: 'Grace' } ).first();
			await expect( link ).toBeAttached();
			await link.hover();

			expect( await pwned( guest ) ).toBe( false );
		} );
	} );

	test( 'a hostile name survives the round trip into its own comment view', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			// The Comment Members list links to a view keyed on the name, so the
			// name goes out through a URL and comes back through a query
			// variable -- two more places for it to stop being text.
			//
			// The attribute-breakout name, not the script one: the query
			// variable is read through sanitize_text_field(), which strips a
			// script element and its contents, so the script name cannot come
			// back matching the row it was stored in. That is core doing its
			// job, and it is why this half of the file uses the payload that
			// makes the round trip at all.
			await guest.locator( '.wp-stats a', { hasText: 'Grace' } ).first().click();

			expect( await pwned( guest ) ).toBe( false );
			await expectNothingArmed( guest.locator( '.wp-stats' ) );
			await expect( guest.locator( '.wp-stats' ) ).toContainText( 'Comments Posted By' );
			await expect( guest.locator( '.wp-stats' ) ).toContainText( 'onmouseover' );
			// The listing really did find that person's comments, so the round
			// trip preserved the name exactly rather than a mangled version of
			// it that happens to match nothing.
			await expect( guest.locator( '.wp-stats blockquote' ) ).toHaveCount( 5 );
		} );
	} );

	test( 'a hostile post title in the author view stays text', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );
			await guest.locator( '.wp-stats a', { hasText: 'Grace' } ).first().click();

			// The post heading above each run of comments carries the title in
			// its text and its own title attribute.
			const heading = guest.locator( '.wp-stats > p > strong > a' ).first();

			await expect( heading ).toContainText( 'Hostile' );
			expect( await pwned( guest ) ).toBe( false );
			await heading.hover();
			expect( await pwned( guest ) ).toBe( false );
		} );
	} );

	test( 'the widget renders the same values without running them', async ( { page } ) => {
		addStatsWidget( {
			title: 'Site Stats',
			limit: 10,
			chars: 200,
			show_link: 1,
			stats_total_posts: 1,
			stats_most_commented_post: 1,
		} );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( hostilePost.link );

			// The widget is the third renderer of the most commented list, and
			// the only one that runs its own assembled markup through
			// wp_kses_post() before printing it.
			const widget = guest.locator( '#stats-9' );

			expect( await pwned( guest ) ).toBe( false );
			await expectNothingArmed( widget );
			await expect( widget ).toContainText( 'Hostile' );
		} );
	} );

	test( 'a hostile term name in the Misc lists stays text', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			const stats = guest.locator( '.wp-stats' );

			// Tag and link-category names are the site's own values rather than
			// a visitor's, but they land in the same lists and are escaped by
			// the same code, so a mistake there shows up here first.
			await expect( stats ).toContainText( 'Tag <img src=x onerror=' );
			await expect( stats ).toContainText( 'Links <script>' );
			expect( await pwned( guest ) ).toBe( false );
			await expectNothingArmed( stats );
		} );
	} );

	test( 'a hostile display name in the Authors list stays text and keeps its name', async ( {
		page,
	} ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( statsPage.link );

			const authors = guest.locator( '.wp-stats' );

			// The escaping here is wp_kses() with nothing allowed rather than
			// wp_strip_all_tags(), because the latter also removes the
			// *contents* of a script element -- a display name written as
			// "<script>Bob</script>" would render as an empty, unclickable
			// link instead of "Bob".
			await expect( authors ).toContainText( 'Admin' );
			await expect( authors ).toContainText( 'window.__pwned' );
			expect( await pwned( guest ) ).toBe( false );
		} );
	} );
} );
