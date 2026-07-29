<?php
/**
 * The public stats page and the filter contract companion plugins hang off.
 *
 * @package WP-Stats
 */

/**
 * WP_Stats_Page.
 */
class WP_Stats_Page_Test extends WP_Stats_TestCase {

	/**
	 * Filters other plugins hook, in the order they fire.
	 *
	 * @var string[]
	 */
	private $page_filters = array( 'general', 'plugins', 'recent', 'most', 'authors', 'comments_members', 'misc' );

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
	 * The section headings themes anchor on are all present.
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
			array( 'PluginsStats' ),
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
	 * Every filter fires, in document order, and its return value is kept.
	 */
	public function test_the_page_filters_fire_in_order() {
		$fired = array();

		foreach ( $this->page_filters as $slug ) {
			add_filter(
				"wp_stats_page_$slug",
				static function ( $content ) use ( $slug, &$fired ) {
					$fired[] = $slug;
					return $content . "<!--HOOK:$slug-->";
				}
			);
		}
		add_filter(
			'stats_page',
			static function ( $content ) {
				return $content . '<!--HOOK:stats_page-->';
			}
		);

		$html = $this->render( 'stats_page' );

		$this->assertSame( $this->page_filters, $fired );

		foreach ( array_merge( $this->page_filters, array( 'stats_page' ) ) as $slug ) {
			$this->assertStringContainsString( "<!--HOOK:$slug-->", $html );
		}
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
	 * Both views loop over $post, and both must put it back.
	 */
	public function test_the_post_global_is_restored() {
		global $post;

		$post   = get_post( $this->ids['p2'] );
		$before = $post;

		stats_page();

		$this->assertSame( $before, $post );
	}

	/**
	 * Render the page with query arguments in place.
	 *
	 * @param array $get Query arguments.
	 * @return string
	 */
	private function with_query( array $get ) {
		$_GET = $get;

		try {
			return $this->render( 'stats_page' );
		} finally {
			$_GET = array();
		}
	}
}
