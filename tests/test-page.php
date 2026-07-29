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
	 * The headings themes anchor on are all present.
	 *
	 * @dataProvider data_section_ids
	 *
	 * @param string $id Heading id.
	 */
	public function test_section_headings_render( $id ) {
		$html = $this->render( 'stats_page' );

		$this->assertStringContainsString( 'id="' . $id . '"', $html );
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
		$this->assertStringNotContainsString( 'Most Commented Page', $this->render( 'stats_page' ) );

		$this->set_display( array( 'commented_page' => 1 ) );
		$this->assertStringContainsString( 'Most Commented Page', $this->render( 'stats_page' ) );
	}

	/**
	 * The General Stats heading lives inside its own toggle, unlike the other
	 * section headings, which are unconditional.
	 */
	public function test_general_stats_heading_goes_with_its_block() {
		$this->set_display( array( 'total_stats' => 0 ) );

		$html = $this->render( 'stats_page' );

		$this->assertStringNotContainsString( 'id="GeneralStats"', $html );
		$this->assertStringContainsString( 'id="MiscStats"', $html );
	}

	/**
	 * The limit reaches the headings.
	 */
	public function test_most_limit_reaches_the_headings() {
		$this->set_limit( 1 );

		$this->assertStringContainsString( 'Top 1 Recent Stat', $this->render( 'stats_page' ) );
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

		$this->assertStringContainsString( '<!--filtered-->', $html );
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

		$this->assertStringNotContainsString( '<!--old-name-->', $this->render( 'stats_page' ) );
	}

	/**
	 * The per-commenter view lists that person's comments.
	 */
	public function test_the_author_view_lists_their_comments() {
		$html = $this->with_query( array( 'stats_author' => 'Normal Commenter' ) );

		$this->assertStringContainsString( 'Comments Posted By', $html );
		$this->assertStringContainsString( 'Back To Stats Page', $html );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * The author name comes from the query string, so it must be escaped.
	 */
	public function test_the_author_name_is_escaped() {
		$html = $this->with_query( array( 'stats_author' => '<script>alert(1)</script>' ) );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * An unknown commenter gets a message rather than a warning.
	 */
	public function test_an_unknown_author_gets_the_empty_message() {
		$html = $this->with_query( array( 'stats_author' => 'Nobody At All' ) );

		$this->assertStringContainsString( 'has not made any comments yet', $html );
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
		$this->assertStringContainsString( 'Comments Posted By', $html );
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

		$this->assertStringContainsString( 'has not made any comments yet', $detail );
		$this->assertStringNotContainsString( 'Stats Protected Post', $this->render( 'stats_page' ) );
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

		$this->assertStringContainsString( 'id="GeneralStats"', $html );
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
