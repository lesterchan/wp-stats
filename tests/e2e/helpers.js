/**
 * Shared steps for the WP-Stats end-to-end suite.
 *
 * Four things about this plugin shape everything below.
 *
 * **It reports on other people's data.** There is no wp_stats table: every
 * number on the page is a count of posts, comments, users, links or terms. So
 * the fixtures are ordinary content, and a spec that asserts on a total has to
 * start from a site whose content it put there itself.
 *
 * **The statistics page is a shortcode.** [page_stats] renders the whole thing,
 * and the admin screen renders the very same string, so most assertions can be
 * made against either. Where the two differ -- the admin screen runs the markup
 * through wp_kses_post() and the front end does not -- both are tested.
 *
 * **A companion plugin contributes a section through one filter.** No sibling
 * plugin is installed here and none needs to be: wp_stats_sections is the whole
 * contract, so a mu-plugin answering it is a contributor as far as WP-Stats is
 * concerned. installSections() is that contributor.
 *
 * **The template tags are what a theme calls**, and twentytwentyone calls none
 * of them, so installProbe() drops a mu-plugin that does.
 */

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { expect } = require( '@wordpress/e2e-test-utils-playwright' );

/** The plugin root, which is where wp-env reads .wp-env.json from. */
const PLUGIN_ROOT = path.join( __dirname, '../..' );

/**
 * The one admin page, and its two tabs.
 *
 * Statistics is the default, so its URL is the page with no tab argument --
 * which is also the URL the menu entry points at.
 */
const STATS_URL = '/wp-admin/admin.php?page=wp-stats';
const SETTINGS_URL = '/wp-admin/admin.php?page=wp-stats&tab=settings';

/**
 * Every display toggle, with the exact heading of the block it governs.
 *
 * The headings that count in the entry limit are written here at a limit of
 * ten, which is what the page spec sets before it uses them. They are exact
 * because the section headings on the same page contain them as substrings --
 * "Authors Stats" contains "Authors" -- so anything looser would say a block
 * was still there when only its section heading was.
 */
const TOGGLES = {
	total_stats: 'Total Stats',
	recent_posts: '10 Recent Posts',
	recent_comments: '10 Recent Comments',
	commented_post: '10 Most Commented Posts',
	commented_page: '10 Most Commented Pages',
	authors: 'Authors',
	comment_members: 'Comment Members',
	post_cats: 'Post Categories',
	link_cats: 'Link Categories',
	tags_list: 'Tags List',
};

/**
 * Run PHP inside the tests environment and hand back what it printed.
 *
 * The code is base64'd rather than passed as itself: the security spec stores
 * quotes, angle brackets and a script tag, and a fixture that is not the
 * payload byte for byte proves nothing about escaping it.
 *
 * @param {string} code PHP to evaluate, without an opening tag.
 * @return {string} Whatever the code echoed between its markers.
 */
function wpEval( code ) {
	const encoded = Buffer.from( code, 'utf8' ).toString( 'base64' );

	const output = execFileSync(
		'npx',
		[
			'--yes',
			'@wordpress/env',
			'run',
			'tests-cli',
			'wp',
			'eval',
			`eval( base64_decode( '${ encoded }' ) );`,
		],
		{ cwd: PLUGIN_ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] },
	);

	// wp-env prints its own progress around the command's output, so the code
	// wraps what it wants to return in markers rather than the caller trying to
	// tell the two apart by position.
	const matched = output.match( /<<<([\s\S]*?)>>>/ );

	return matched ? matched[ 1 ] : '';
}

/**
 * Run PHP and read back a JSON value, so types survive the round trip.
 *
 * @param {string} expression PHP expression to encode and return.
 * @return {*} The decoded value.
 */
function wpEvalJson( expression ) {
	return JSON.parse( wpEval( `echo '<<<' . wp_json_encode( ${ expression } ) . '>>>';` ) );
}

/**
 * Encode a value for a PHP literal, so payloads cross the shell intact.
 *
 * @param {*} value Anything JSON can carry.
 * @return {string} A base64 string.
 */
function encode( value ) {
	return Buffer.from( JSON.stringify( value ), 'utf8' ).toString( 'base64' );
}

/**
 * The settings, as the plugin's own code reads them.
 *
 * Through the accessor rather than get_option(), because the accessor merges
 * the defaults in and that is what every read path in the plugin sees.
 *
 * @param {string|null} key Top level setting, or null for all of them.
 * @return {*} The stored value.
 */
function option( key = null ) {
	const all = wpEvalJson( 'WP_Stats_Options::get()' );

	return null === key ? all : all[ key ];
}

/**
 * Overwrite settings, leaving everything else alone.
 *
 * For arranging a precondition, never for asserting one: a setting a test is
 * actually about goes in through the settings screen, so the sanitiser between
 * the form and the row is exercised by the test that depends on it.
 *
 * @param {Object} values Keys to overwrite. `display` is merged key by key.
 * @return {void}
 */
function setOptions( values ) {
	wpEval(
		`$values = json_decode( base64_decode( '${ encode( values ) }' ), true );
		WP_Stats_Options::update( array_replace_recursive( WP_Stats_Options::get(), $values ) );
		WP_Stats_Options::flush();
		echo '<<<done>>>';`,
	);
}

/**
 * Put every setting back to what a fresh install has.
 *
 * @return {void}
 */
function resetOptions() {
	wpEval(
		`WP_Stats_Options::update( WP_Stats_Options::defaults() );
		WP_Stats_Options::flush();
		echo '<<<done>>>';`,
	);
}

/**
 * Turn every display toggle on, or off.
 *
 * @param {number} value 1 for on, 0 for off.
 * @return {void}
 */
function setAllToggles( value ) {
	const display = {};

	for ( const key of Object.keys( TOGGLES ) ) {
		display[ key ] = value;
	}

	setOptions( { display } );
}

/**
 * Write one file into the mu-plugins directory.
 *
 * @param {string} name   File name, without the directory.
 * @param {string} source Complete PHP file, opening tag included.
 * @return {void}
 */
function installMuPlugin( name, source ) {
	const encoded = Buffer.from( source, 'utf8' ).toString( 'base64' );

	wpEval(
		`if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			mkdir( WPMU_PLUGIN_DIR, 0777, true );
		}
		file_put_contents( WPMU_PLUGIN_DIR . '/${ name }', base64_decode( '${ encoded }' ) );
		echo '<<<done>>>';`,
	);
}

/**
 * Remove one file from the mu-plugins directory.
 *
 * @param {string} name File name, without the directory.
 * @return {void}
 */
function removeMuPlugin( name ) {
	wpEval(
		`$file = WPMU_PLUGIN_DIR . '/${ name }';
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
		echo '<<<done>>>';`,
	);
}

/**
 * The mu-plugin that stands in for a theme's calls to the template tags.
 *
 * Every public tag is here, each in a container of its own, so a spec can
 * assert on one without the others' output getting in the way. wp_footer
 * rather than the_content, because the_content does not run on every kind of
 * page and this has to be reachable from the front page.
 */
const PROBE_SOURCE = `<?php
/**
 * Plugin Name: WP-Stats E2E probe
 * Description: Calls the template tags from wp_footer, standing in for a theme.
 */
add_action(
	'wp_footer',
	function () {
		echo '<div id="st-authors">' . get_totalauthors( false ) . '</div>';
		echo '<div id="st-posts">' . get_totalposts( false ) . '</div>';
		echo '<div id="st-pages">' . get_totalpages( false ) . '</div>';
		echo '<div id="st-comments">' . get_totalcomments( false ) . '</div>';
		echo '<div id="st-commentposters">' . get_totalcommentposters( false ) . '</div>';
		echo '<div id="st-links">' . get_totallinks( false ) . '</div>';
		echo '<ul id="st-recent-posts">' . get_recentposts( 'post', 5, false ) . '</ul>';
		echo '<ul id="st-recent-comments">' . get_recentcomments( 'both', 5, false ) . '</ul>';
		echo '<ul id="st-most-commented">' . get_mostcommented( 'post', 5, 0, false ) . '</ul>';
		echo '<ul id="st-most-commented-chars">' . get_mostcommented( 'post', 5, 12, false ) . '</ul>';
		echo '<ul id="st-authors-list">' . get_authorsstats( 'post', false ) . '</ul>';
		echo '<ul id="st-comment-members">' . get_commentmembersstats( -1, 0, false ) . '</ul>';
		echo '<ul id="st-post-cats">' . get_postcats( false ) . '</ul>';
		echo '<ul id="st-link-cats">' . get_linkcats( false ) . '</ul>';
		echo '<ul id="st-tags">' . get_tags_list( false ) . '</ul>';
		echo '<div id="st-page-link">' . esc_html( stats_page_link( 'Somebody', 3 ) ) . '</div>';
		echo '<div id="st-snippet">' . snippet_text( 'A rather long piece of text', 10 ) . '</div>';
		echo '<div id="st-page" data-length="' . esc_attr( strlen( stats_page() ) ) . '"></div>';
		// The echoing form of the same tag, which is the default every one of
		// them has and the one a theme actually writes.
		echo '<div id="st-posts-echoed">';
		get_totalposts();
		echo '</div>';
		echo '<ul id="st-recent-posts-echoed">';
		get_recentposts( 'post', 5 );
		echo '</ul>';
		// Ranked by comments, with anybody below two hidden.
		echo '<ul id="st-comment-members-threshold">' . get_commentmembersstats( 2, 0, false ) . '</ul>';
	},
	5
);
`;

/**
 * Install the template-tag probe.
 *
 * @return {void}
 */
function installProbe() {
	installMuPlugin( 'wp-stats-e2e-probe.php', PROBE_SOURCE );
}

/**
 * Remove the template-tag probe.
 *
 * @return {void}
 */
function removeProbe() {
	removeMuPlugin( 'wp-stats-e2e-probe.php' );
}

/**
 * The mu-plugin that plays the part of a companion plugin.
 *
 * It reads what to contribute out of an option, so one file covers every shape
 * the specs need: a well formed entry, one with no title, one whose render
 * callback is not callable, one that is not an array at all. A contributor is
 * third-party code as far as WP-Stats is concerned, and the malformed shapes
 * are the ones that must be skipped rather than allowed to fatal the page.
 */
const SECTIONS_SOURCE = `<?php
/**
 * Plugin Name: WP-Stats E2E contributor
 * Description: Answers wp_stats_sections with whatever the stats_e2e_sections option describes.
 */
add_filter(
	'wp_stats_sections',
	function ( $sections ) {
		$spec = json_decode( (string) get_option( 'stats_e2e_sections', '[]' ), true );

		if ( ! is_array( $spec ) ) {
			return $sections;
		}

		foreach ( $spec as $entry ) {
			$key  = $entry['key'];
			$kind = isset( $entry['kind'] ) ? $entry['kind'] : 'valid';

			$render = function () use ( $key ) {
				echo '<p class="e2e-body-' . esc_attr( $key ) . '">body of ' . esc_html( $key ) . '</p>';
			};

			if ( 'not_array' === $kind ) {
				$sections[ $key ] = 'not an array at all';
				continue;
			}

			if ( 'no_title' === $kind ) {
				$sections[ $key ] = array( 'render' => $render );
				continue;
			}

			if ( 'bad_render' === $kind ) {
				$sections[ $key ] = array( 'title' => $entry['title'], 'render' => 'wp_stats_e2e_no_such_function' );
				continue;
			}

			$section = array(
				'title'  => $entry['title'],
				'render' => $render,
			);

			if ( 'no_priority' !== $kind ) {
				$section['priority'] = (int) $entry['priority'];
			}

			$sections[ $key ] = $section;
		}

		return $sections;
	}
);

// A theme taking one contributor's block over, which is the whole reason each
// section is rendered through a hook of its own rather than inline.
add_action(
	'init',
	function () {
		$key = (string) get_option( 'stats_e2e_takeover', '' );

		if ( '' === $key ) {
			return;
		}

		add_action(
			'wp_stats_section_' . $key,
			function () {
				remove_action( 'wp_stats_section_' . get_option( 'stats_e2e_takeover' ), array( 'WP_Stats_Page', 'render_section' ) );
				echo '<p class="e2e-takeover">the theme wrote this instead</p>';
			},
			5
		);
	}
);
`;

/**
 * Install the contributor mu-plugin and describe what it should contribute.
 *
 * @param {Array} entries Section descriptions: key, title, priority, kind.
 * @return {void}
 */
function installSections( entries ) {
	installMuPlugin( 'wp-stats-e2e-sections.php', SECTIONS_SOURCE );

	wpEval(
		`update_option( 'stats_e2e_sections', base64_decode( '${ Buffer.from(
			JSON.stringify( entries ),
			'utf8',
		).toString( 'base64' ) }' ) );
		echo '<<<done>>>';`,
	);
}

/**
 * Have a theme take one contributed section over.
 *
 * @param {string} key Contributor key, or '' to stop.
 * @return {void}
 */
function takeOverSection( key ) {
	wpEval( `update_option( 'stats_e2e_takeover', '${ key }' ); echo '<<<done>>>';` );
}

/**
 * Remove the contributor mu-plugin and the options it reads.
 *
 * @return {void}
 */
function removeSections() {
	removeMuPlugin( 'wp-stats-e2e-sections.php' );
	wpEval(
		`delete_option( 'stats_e2e_sections' );
		delete_option( 'stats_e2e_takeover' );
		echo '<<<done>>>';`,
	);
}

/**
 * Insert one approved comment exactly as given.
 *
 * Straight in rather than through REST, for two reasons: the author name is a
 * visitor-supplied value the security spec needs to store unsanitised, and the
 * date has to be set explicitly. Comments created in parallel land in the same
 * second, and the tie breaks on insertion order, so every assertion about "the
 * most recent one" would otherwise be a coin toss.
 *
 * @param {Object} spec              What to insert.
 * @param {number} spec.postId       Post the comment belongs to.
 * @param {string} spec.author       Comment author name.
 * @param {string} [spec.content]    Comment body.
 * @param {number} [spec.minutesAgo] How long ago it was left.
 * @param {string} [spec.approved]   Approval status: '1', '0' or 'spam'.
 * @return {number} The new comment's id.
 */
function insertComment( spec ) {
	const id = wpEval(
		`$data = json_decode( base64_decode( '${ encode( {
			content: 'A comment.',
			minutesAgo: 0,
			approved: '1',
			...spec,
		} ) }' ), true );
		$date = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - ( (int) $data['minutesAgo'] * 60 ) );
		$id = wp_insert_comment(
			array(
				'comment_post_ID'  => (int) $data['postId'],
				'comment_author'   => $data['author'],
				'comment_content'  => $data['content'],
				'comment_approved' => $data['approved'],
				'comment_date'     => $date,
				'comment_date_gmt' => $date,
				'comment_type'     => 'comment',
			)
		);
		echo '<<<' . (int) $id . '>>>';`,
	);

	return parseInt( id, 10 );
}

/**
 * Insert many comments in one round trip.
 *
 * One wpEval per comment would be one wp-env container start per comment, and
 * the paging fixture needs more than thirty of them. They are dated a minute
 * apart, descending, so the order the author view puts them in is decided by
 * the fixture rather than by which insert the database reached first.
 *
 * @param {Object} spec           What to insert.
 * @param {number} spec.postId    Post the comments belong to.
 * @param {string} spec.author    Comment author name.
 * @param {number} spec.count     How many.
 * @param {number} [spec.offset]  Minutes to push the whole run further back.
 * @param {string} [spec.content] Body, with %d replaced by the index.
 * @return {number} How many were inserted.
 */
function insertComments( spec ) {
	const inserted = wpEval(
		`$data = json_decode( base64_decode( '${ encode( {
			content: 'Comment number %d.',
			offset: 0,
			...spec,
		} ) }' ), true );
		$now = strtotime( current_time( 'mysql' ) );
		$made = 0;
		for ( $index = 0; $index < (int) $data['count']; $index++ ) {
			$date = gmdate( 'Y-m-d H:i:s', $now - ( ( $index + (int) $data['offset'] ) * 60 ) );
			$id = wp_insert_comment(
				array(
					'comment_post_ID'  => (int) $data['postId'],
					'comment_author'   => $data['author'],
					'comment_content'  => sprintf( $data['content'], $index ),
					'comment_approved' => '1',
					'comment_date'     => $date,
					'comment_date_gmt' => $date,
					'comment_type'     => 'comment',
				)
			);
			if ( $id ) {
				$made++;
			}
		}
		echo '<<<' . $made . '>>>';`,
	);

	return parseInt( inserted, 10 );
}

/**
 * Delete every comment on the site.
 *
 * The counts and the listings are site-wide, so a spec that asserts on either
 * has to start from a known set rather than from whatever the last run left.
 *
 * @return {void}
 */
function deleteAllComments() {
	wpEval(
		`global $wpdb;
		$ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments}" );
		foreach ( $ids as $id ) {
			wp_delete_comment( (int) $id, true );
		}
		echo '<<<done>>>';`,
	);
}

/**
 * Delete every link, so the link counts start from nothing.
 *
 * @return {void}
 */
function deleteAllLinks() {
	wpEval(
		// wp_delete_link() lives in wp-admin, which WP-CLI does not load: the
		// Links Manager has been hidden since WordPress 3.5 and its API never
		// moved out of the admin includes.
		`require_once ABSPATH . 'wp-admin/includes/bookmark.php';
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT link_id FROM {$wpdb->links}" );
		foreach ( $ids as $id ) {
			wp_delete_link( (int) $id );
		}
		echo '<<<done>>>';`,
	);
}

/**
 * Add one link in one link category.
 *
 * The Links Manager has been hidden since WordPress 3.5 and has no REST route,
 * so a link fixture can only be made this way.
 *
 * @param {string} name     Link name.
 * @param {string} category Link category name.
 * @return {number} The new link's id.
 */
function insertLink( name, category ) {
	const id = wpEval(
		// See deleteAllLinks(): wp_insert_link() is a wp-admin function too.
		`require_once ABSPATH . 'wp-admin/includes/bookmark.php';
		$data = json_decode( base64_decode( '${ encode( { name, category } ) }' ), true );
		$term = term_exists( $data['category'], 'link_category' );
		if ( ! $term ) {
			$term = wp_insert_term( $data['category'], 'link_category' );
		}
		$id = wp_insert_link(
			array(
				'link_name'     => $data['name'],
				'link_url'      => 'https://example.com/' . rawurlencode( $data['name'] ),
				'link_category' => array( (int) $term['term_id'] ),
			)
		);
		echo '<<<' . (int) $id . '>>>';`,
	);

	return parseInt( id, 10 );
}

/**
 * Put one Stats widget in twentytwentyone's sidebar.
 *
 * Straight into widget_stats and sidebars_widgets rather than through the
 * widgets screen: that screen is the block editor's Legacy Widget wrapper, and
 * driving it would test Gutenberg rather than this plugin. The instance is
 * exactly what the screen would have written, and the widget's own form and
 * update() are covered by tests/test-widget.php.
 *
 * Always slot 9, so a second call replaces the widget rather than stacking
 * another one beside it.
 *
 * @param {Object} instance Widget settings.
 * @return {void}
 */
function addStatsWidget( instance ) {
	wpEval(
		`$instance = json_decode( base64_decode( '${ encode( instance ) }' ), true );
		update_option( 'widget_stats', array( 9 => $instance, '_multiwidget' => 1 ) );
		$sidebars = (array) get_option( 'sidebars_widgets', array() );
		$sidebars['sidebar-1'] = array( 'stats-9' );
		update_option( 'sidebars_widgets', $sidebars );
		echo '<<<done>>>';`,
	);
}

/**
 * Empty the sidebar again.
 *
 * @return {void}
 */
function removeStatsWidget() {
	wpEval(
		`delete_option( 'widget_stats' );
		$sidebars = (array) get_option( 'sidebars_widgets', array() );
		$sidebars['sidebar-1'] = array();
		update_option( 'sidebars_widgets', $sidebars );
		echo '<<<done>>>';`,
	);
}

/**
 * Rewrite a post's title straight in the posts table.
 *
 * Not through REST: the whole point is a title that never met a sanitiser, and
 * wp_insert_post() runs one for any user without unfiltered_html.
 *
 * @param {number} postId Post to rewrite.
 * @param {string} title  The title to store, byte for byte.
 * @return {void}
 */
function setRawTitle( postId, title ) {
	wpEval(
		`global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array( 'post_title' => base64_decode( '${ Buffer.from( title, 'utf8' ).toString(
		'base64',
	) }' ) ),
			array( 'ID' => ${ postId } ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( ${ postId } );
		echo '<<<done>>>';`,
	);
}

/**
 * Do something in a browser nobody is logged in to.
 *
 * The statistics page is public, and half of what the screens do differs
 * between an administrator and a visitor, so anything about a visitor needs a
 * context of its own.
 *
 * @param {import('@playwright/test').Page} page    Page under test, for its browser.
 * @param {Object}                          options Extra newContext options.
 * @param {Function}                        run     Called with the guest page.
 * @return {Promise<void>} Resolves once the guest context has been closed.
 */
async function asGuest( page, options, run ) {
	const context = await page.context().browser().newContext( {
		...options,
		storageState: undefined,
	} );

	try {
		await run( await context.newPage() );
	} finally {
		await context.close();
	}
}

/**
 * Log a second browser context in as a named user.
 *
 * wp-login.php focuses and *selects* #user_login on a 200ms timer so a visitor
 * can start typing. Filling across that moment puts the password into the
 * username box -- Playwright focuses #user_pass, the timer takes focus back and
 * selects what is there, and the typed text replaces the selection. Waiting for
 * the timer's own effect is the signal that it has already fired.
 *
 * @param {import('@playwright/test').Page} page     Page under test, for its browser.
 * @param {string}                          username Username to log in as.
 * @param {string}                          password That user's password.
 * @return {Promise<import('@playwright/test').Page>} A page carrying that session.
 */
async function loginAs( page, username, password ) {
	const context = await page.context().browser().newContext( { storageState: undefined } );
	const other = await context.newPage();

	await other.goto( '/wp-login.php' );
	await expect( other.locator( '#user_login' ) ).toBeFocused();

	await other.locator( '#user_login' ).fill( username );
	await other.locator( '#user_pass' ).fill( password );
	await other.locator( '#wp-submit' ).click();
	await expect( other.locator( '#wpadminbar' ) ).toBeVisible();

	return other;
}

/**
 * Create a user, or reset the one an earlier run already created.
 *
 * Through WP-CLI rather than REST, because REST answers "that login is taken"
 * with an error and there is no second call that reliably finds the account
 * again -- the suite is run more than once against the same database, so the
 * second run has to be able to log in as the account the first one made.
 *
 * @param {string} username Username.
 * @param {string} role     Role slug.
 * @param {string} password Password.
 * @return {number} The user id.
 */
function ensureUser( username, role, password ) {
	return parseInt(
		wpEval(
			`$login = '${ username }';
			$user = get_user_by( 'login', $login );

			if ( $user ) {
				$id = (int) $user->ID;
				wp_set_password( '${ password }', $id );
				$user = new WP_User( $id );
				$user->set_role( '${ role }' );
			} else {
				$id = (int) wp_insert_user( array(
					'user_login' => $login,
					'user_pass'  => '${ password }',
					'user_email' => $login . '@example.com',
					'role'       => '${ role }',
				) );
			}

			echo '<<<' . $id . '>>>';`,
		),
		10,
	);
}

/**
 * Open the Settings tab of the WP-Stats page.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once the tab is up.
 */
async function openSettings( page ) {
	await page.goto( SETTINGS_URL );

	// The heading names the page, which is the same on both tabs, so the tab
	// strip is what says the settings are the half being drawn.
	await expect( page.getByRole( 'heading', { name: 'Stats', exact: true } ) ).toBeVisible();
	await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'Settings' );
}

/**
 * Save the settings form and wait for WordPress to confirm it.
 *
 * The notice rather than the redirect: options.php sends the browser back
 * whether or not anything was written, so arriving here again says nothing.
 * This screen lives under admin.php, which does not include options-head.php,
 * so the notice is only there because the screen calls settings_errors()
 * itself -- which makes its presence a regression guard as well as a signal.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once "Settings saved." is on screen.
 */
async function saveSettings( page ) {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	await expect( page.locator( '.settings-error, .notice-success' ).first() ).toContainText(
		'Settings saved.',
	);
}

/**
 * A title no earlier run can have used.
 *
 * @param {string} base What the title should say.
 * @return {string} That, plus enough to tell this run from the last.
 */
function uniqueTitle( base ) {
	return `${ base } ${ Date.now().toString( 36 ) }`;
}

/**
 * Publish the page that holds the statistics shortcode, and point the plugin at it.
 *
 * The Stats URL setting is what every link back to the page is built from, so
 * the fixture and the setting have to be the same page or half the links on the
 * screen lead somewhere that does not exist.
 *
 * @param {Object} requestUtils The e2e-test-utils request helper.
 * @return {Promise<Object>} The created page.
 */
async function createStatsPage( requestUtils ) {
	const statsPage = await requestUtils.createPage( {
		title: uniqueTitle( 'Statistics' ),
		content: '[page_stats]',
		status: 'publish',
	} );

	setOptions( { url: statsPage.link } );

	return statsPage;
}

module.exports = {
	SETTINGS_URL,
	STATS_URL,
	TOGGLES,
	addStatsWidget,
	asGuest,
	createStatsPage,
	deleteAllComments,
	deleteAllLinks,
	ensureUser,
	insertComment,
	insertComments,
	insertLink,
	installMuPlugin,
	installProbe,
	installSections,
	loginAs,
	openSettings,
	option,
	removeMuPlugin,
	removeProbe,
	removeSections,
	removeStatsWidget,
	resetOptions,
	saveSettings,
	setAllToggles,
	setOptions,
	setRawTitle,
	takeOverSection,
	uniqueTitle,
	wpEval,
	wpEvalJson,
};
