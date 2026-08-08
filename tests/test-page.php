<?php
/**
 * The public statistics page and its two views.
 *
 * The contract companion plugins hang off lives in test-sections.php.
 *
 * @package WP-Stats
 */

/**
 * @covers WP_Stats_Page
 */
class WP_Stats_Page_Test extends WP_Stats_TestCase {

	/**
	 * Seed a small blog for every test in this file.
	 */
	public function set_up() {
		parent::set_up();
		$this->seed_blog();
		$this->set_display(
			array(
				'total_stats'     => 1,
				'recent_posts'    => 1,
				'recent_comments' => 1,
				'commented_post'  => 1,
				'commented_page'  => 0,
				'authors'         => 1,
				'comment_members' => 1,
				'post_cats'       => 1,
				'link_cats'       => 1,
				'tags_list'       => 1,
			)
		);
	}

	/**
	 * The file's own reasoning is that a site's content-hiding filters still
	 * apply because these queries go through core's APIs. That holds for the
	 * get_comments(), get_terms() and WP_User_Query paths and did not hold for
	 * these two: get_posts() defaults suppress_filters to true, so posts_where
	 * never ran and a membership plugin's hidden posts were listed on the public
	 * statistics page anyway.
	 *
	 * @dataProvider data_post_listings
	 *
	 * @param string $method Query method under test.
	 */
	public function test_a_sites_own_posts_where_is_honoured( $method ) {
		$hidden = self::factory()->post->create(
			array(
				'post_title'  => 'Members Only Post',
				'post_status' => 'publish',
			)
		);

		$filter = static function ( $where ) use ( $hidden ) {
			global $wpdb;

			return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID != %d", $hidden );
		};

		$this->assertContains(
			$hidden,
			wp_list_pluck( WP_Stats_Query::$method( '', 50 ), 'ID' ),
			'The post is listed before the filter, or the assertion below is vacuous.'
		);

		add_filter( 'posts_where', $filter );

		$ids = wp_list_pluck( WP_Stats_Query::$method( '', 50 ), 'ID' );

		remove_filter( 'posts_where', $filter );

		$this->assertNotContains( $hidden, $ids, $method . '() honours the site\'s own posts_where.' );
	}

	/**
	 * The two listings built on get_posts().
	 *
	 * @return array
	 */
	public function data_post_listings() {
		return array(
			'recent posts'   => array( 'recent_posts' ),
			'most commented' => array( 'most_commented' ),
		);
	}

	/**
	 * The headings themes anchor on are all present.
	 *
	 * @dataProvider data_section_ids
	 *
	 * @param string $id Heading id.
	 */
	public function test_section_headings_render( $id ) {
		$html = $this->render( 'stats_page' );

		$this->assertStringContainsString( 'id="' . $id . '"', $html, 'The ' . $id . ' section heading is missing from the page.' );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * Heading ids on the stats page.
	 *
	 * @return array
	 */
	public function data_section_ids() {
		return array(
			array( 'GeneralStats' ),
			array( 'TopRecentStats' ),
			array( 'TopMostHighestStats' ),
			array( 'AuthorsStats' ),
			array( 'CommentsMembersStats' ),
			array( 'MiscStats' ),
		);
	}

	/**
	 * Each toggle gates its own block.
	 */
	public function test_a_toggle_gates_its_block() {
		$this->assertStringNotContainsString( 'Most Commented Page', $this->render( 'stats_page' ), 'With the toggle off the block is absent.' );

		$this->set_display( array( 'commented_page' => 1 ) );
		$this->assertStringContainsString( 'Most Commented Page', $this->render( 'stats_page' ), 'With it on the block is drawn, so the toggle is what gates it.' );
	}

	/**
	 * The General Stats heading lives inside its own toggle, unlike the other
	 * section headings, which are unconditional.
	 */
	public function test_general_stats_heading_goes_with_its_block() {
		$this->set_display( array( 'total_stats' => 0 ) );

		$html = $this->render( 'stats_page' );

		$this->assertStringNotContainsString( 'id="GeneralStats"', $html, 'Turning the block off takes its heading with it.' );
		$this->assertStringContainsString( 'id="MiscStats"', $html, 'The blocks beside it are untouched.' );
	}

	/**
	 * The limit reaches the headings.
	 */
	public function test_most_limit_reaches_the_headings() {
		$this->set_limit( 1 );

		$this->assertStringContainsString( 'Top 1 Recent Stat', $this->render( 'stats_page' ), 'The stored limit reaches the heading, not only the query.' );
	}

	/**
	 * The whole page goes through one filter, and it sees the wrapper.
	 */
	public function test_the_page_filter_receives_the_finished_markup() {
		add_filter(
			'wp_stats_page',
			static function ( $content ) {
				return $content . '<!--filtered-->';
			}
		);

		$html = $this->render( 'stats_page' );

		$this->assertStringContainsString( '<!--filtered-->', $html, 'The page filter is handed the finished markup and can add to it.' );
		$this->assertStringContainsString( '<div class="wp-stats">', $html, 'The filter is handed the wrapper, not the bare blocks.' );
	}

	/**
	 * The pre-3.0.0 name for that filter is gone rather than shimmed.
	 */
	public function test_the_unprefixed_stats_page_filter_is_no_longer_fired() {
		add_filter(
			'stats_page',
			static function ( $content ) {
				return $content . '<!--old-name-->';
			}
		);

		$this->assertStringNotContainsString( '<!--old-name-->', $this->render( 'stats_page' ), 'The unprefixed filter name is no longer fired; it was any plugin to claim.' );
	}

	/**
	 * The per-commenter view lists that person's comments.
	 */
	public function test_the_author_view_lists_their_comments() {
		$html = $this->with_query( array( 'stats_author' => 'Normal Commenter' ) );

		$this->assertStringContainsString( 'Comments Posted By', $html, 'The author view is headed with whose comments these are.' );
		$this->assertStringContainsString( 'Back To Stats Page', $html, 'And offers the way back, since it replaces the whole page.' );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * The author name comes from the query string, so it must be escaped.
	 */
	public function test_the_author_name_is_escaped() {
		$html = $this->with_query( array( 'stats_author' => '<script>alert(1)</script>' ) );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html, 'A hostile author name never renders as markup.' );
		$this->assertStringContainsString( '&lt;script&gt;', $html, 'It renders as text, so the name is still readable.' );
	}

	/**
	 * An apostrophe in a commenter's name is the ordinary case, not an attack.
	 *
	 * The fixture slashes the name because that is what the page is handed:
	 * WP::parse_request() copies a registered query variable straight out of
	 * $_GET, which wp_magic_quotes() has already slashed. Written in plain
	 * ASCII this test cannot fail - the name has to carry a quote through the
	 * round trip for the missing wp_unslash() to show. Without it the lookup is
	 * for "Sinead O\'Brien", which matches no row.
	 */
	public function test_an_apostrophe_in_the_name_still_finds_their_comments() {
		$this->add_comment( $this->ids['p1'], "Sinead O'Brien", 22 );

		$html = $this->with_query( array( 'stats_author' => wp_slash( "Sinead O'Brien" ) ) );

		$this->assertStringNotContainsString( 'has not made any comments yet', $html, 'An apostrophe in the name still matches their comments.' );
		$this->assertStringContainsString( 'Stats Post One', $html, 'The drill-down found none of their comments.' );
		$this->assertStringContainsString( 'Sinead O&#039;Brien', $html, 'And the name renders with the apostrophe escaped rather than as a slash.' );
		$this->assertStringNotContainsString( 'O\\&#039;Brien', $html, 'A backslash reached the heading.' );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * The paging links out of that view carry the name back unmangled.
	 *
	 * The view is only reachable from the Comment Members list, so a name that
	 * survives the first hop but not the second still ends in an empty page.
	 */
	public function test_an_apostrophe_survives_the_paging_links() {
		for ( $i = 0; $i < WP_Stats_Page::PER_PAGE + 1; $i++ ) {
			$this->add_comment( $this->ids['p1'], "Sinead O'Brien", 22 );
		}

		$html = $this->with_query( array( 'stats_author' => wp_slash( "Sinead O'Brien" ) ) );

		$this->assertStringContainsString( 'stats_author=' . rawurlencode( "Sinead O'Brien" ), $html, 'The paging links carry the name encoded, so page two finds the same commenter.' );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * An unknown commenter gets a message rather than a warning.
	 */
	public function test_an_unknown_author_gets_the_empty_message() {
		$html = $this->with_query( array( 'stats_author' => 'Nobody At All' ) );

		$this->assertStringContainsString( 'has not made any comments yet', $html, 'An author with no comments gets the empty message rather than an empty list.' );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * A negative page produced a negative LIMIT offset and a SQL error.
	 */
	public function test_a_negative_page_is_clamped() {
		$html = $this->with_query(
			array(
				'stats_author' => 'Normal Commenter',
				'stats_page'   => '-5',
			)
		);

		$this->assertMarkupIsClean( $html );
		$this->assertStringContainsString( 'Comments Posted By', $html, 'A negative page is clamped and the view still renders.' );
	}

	/**
	 * Password-protected posts are excluded by every listing query, which is
	 * also why the old "Comments Protected" branch could never run.
	 */
	public function test_password_protected_posts_are_excluded() {
		$protected = self::factory()->post->create(
			$this->days_ago( 5 ) + array(
				'post_title'    => 'Stats Protected Post',
				'post_status'   => 'publish',
				'post_password' => 'letmein',
			)
		);
		$this->add_comment( $protected, 'Secret Commenter', 20 );

		$detail = $this->with_query( array( 'stats_author' => 'Secret Commenter' ) );

		$this->assertStringContainsString( 'has not made any comments yet', $detail, 'Comments on a password-protected post are excluded from the author view.' );
		$this->assertStringNotContainsString( 'Stats Protected Post', $this->render( 'stats_page' ), 'And the post itself is excluded from the listings.' );
	}

	/**
	 * The admin screen renders the same page inside a wrap.
	 */
	public function test_the_admin_screen_renders_the_page() {
		wp_set_current_user( $this->create_admin() );

		$html = $this->render( array( 'WP_Stats_Admin', 'render' ) );

		$this->assertStringContainsString( 'class="wrap"', $html, 'The admin screen wraps the page in the core container.' );
		$this->assertStringContainsString( 'id="GeneralStats"', $html, 'And renders the same blocks the public page does.' );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * Every capability check goes through one filter, so a site can hand the
	 * read-only screen to editors without handing over the settings too.
	 */
	public function test_the_capability_filter_sees_the_context() {
		$seen = array();

		add_filter(
			'wp_stats_capability',
			static function ( $capability, $context ) use ( &$seen ) {
				$seen[] = $context;

				return 'statistics' === $context ? 'edit_posts' : $capability;
			},
			10,
			2
		);

		$this->assertSame( 'edit_posts', WP_Stats_Admin::capability( 'statistics' ), 'The filter can set the Statistics tab capability.' );
		$this->assertSame( 'manage_options', WP_Stats_Admin::capability( 'settings' ), 'And the Settings tab separately, so the two are not one gate.' );
		$this->assertSame( array( 'statistics', 'settings' ), $seen, 'It is told which tab is asking, each time it is asked.' );
	}

	/**
	 * The shortcode renders the same page.
	 */
	public function test_the_shortcode_renders_the_page() {
		$html = $this->render(
			static function () {
				return do_shortcode( '[page_stats]' );
			}
		);

		$this->assertStringContainsString( 'id="GeneralStats"', $html, 'The shortcode renders the same page a theme would.' );
	}

	/**
	 * Neither view touches the loop globals any more.
	 *
	 * Both used to assign $post to walk their result rows, and both had to
	 * remember to put it back. The listings pass post ids to get_the_title()
	 * and friends instead, so there is nothing left to restore.
	 */
	public function test_the_post_global_is_left_alone() {
		global $post;

		$post   = get_post( $this->ids['p2'] );
		$before = $post;

		stats_page();

		$this->assertSame( $before, $post, 'Rendering the page must not disturb the loop.' );
	}

	/**
	 * Render the page with the query arguments WordPress would have parsed.
	 *
	 * They are registered query variables now rather than raw $_GET reads, so
	 * the fixture sets them where get_query_var() looks.
	 *
	 * @param array $vars Query variables.
	 * @return string
	 */
	private function with_query( array $vars ) {
		foreach ( $vars as $name => $value ) {
			set_query_var( $name, $value );
		}

		try {
			return $this->render( 'stats_page' );
		} finally {
			foreach ( array_keys( $vars ) as $name ) {
				set_query_var( $name, '' );
			}
		}
	}
}
