<?php
/**
 * The template tags: the plugin's public API for themes.
 *
 * Counts are checked against an independent core API rather than against the
 * plugin's own SQL restated, so a rewritten query is really being tested.
 *
 * @package WP-Stats
 */

/**
 * @coversNothing
 */
class WP_Stats_Template_Tags_Test extends WP_Stats_TestCase {

	/**
	 * Seed a small blog for every test in this file.
	 */
	public function set_up() {
		parent::set_up();
		$this->seed_blog();
	}

	/**
	 * Every documented tag still exists.
	 *
	 * @dataProvider data_public_functions
	 *
	 * @param string $name Function name.
	 */
	public function test_the_public_api_is_intact( $name ) {
		$this->assertTrue( function_exists( $name ), "$name is part of the public API." );
	}

	/**
	 * Functions themes call. Every one of them shipped in 2.56.1 and keeps its
	 * name, because a template tag rename is a theme that stops rendering.
	 *
	 * @return array
	 */
	public function data_public_functions() {
		return array_map(
			static function ( $name ) {
				return array( $name );
			},
			array(
				'get_totalauthors',
				'get_totalposts',
				'get_totalpages',
				'get_totalcomments',
				'get_totalcommentposters',
				'get_totallinks',
				'get_recentposts',
				'get_recentcomments',
				'get_mostcommented',
				'get_authorsstats',
				'get_commentmembersstats',
				'get_postcats',
				'get_linkcats',
				'get_tags_list',
				'snippet_text',
				'stats_page',
				'stats_page_link',
			)
		);
	}

	/**
	 * Counts agree with core's own.
	 */
	public function test_post_and_page_counts_match_core() {
		$this->assertSame( (int) wp_count_posts( 'post' )->publish, get_totalposts( false ), 'The post count is core count, not a query of its own that could drift.' );
		$this->assertSame( (int) wp_count_posts( 'page' )->publish, get_totalpages( false ), 'And the page count.' );
	}

	/**
	 * Comment counts agree with the comments API.
	 */
	public function test_comment_counts_match_core() {
		$approved = get_comments(
			array(
				'status' => 'approve',
				'count'  => true,
			)
		);

		$this->assertSame( (int) $approved, get_totalcomments( false ), 'The comment count is the approved comments, excluding spam and pending.' );

		$names = array();
		foreach ( get_comments(
			array(
				'status' => 'approve',
				'type'   => 'comment',
			)
		) as $comment ) {
			$names[ $comment->comment_author ] = true;
		}

		$this->assertSame( count( $names ), get_totalcommentposters( false ), 'The commenter count is distinct people, not comments.' );
	}

	/**
	 * Authors are the users who can publish.
	 */
	public function test_author_count_matches_the_users_who_can_publish() {
		$expected = 0;

		foreach ( get_users() as $user ) {
			if ( user_can( $user, 'publish_posts' ) ) {
				++$expected;
			}
		}

		$this->assertGreaterThanOrEqual( 2, $expected, 'Fixture sanity: more than one author.' );
		$this->assertSame( $expected, get_totalauthors( false ), 'The author count is users who can publish, not every registered user.' );
	}

	/**
	 * Drafts never appear in a listing.
	 */
	public function test_listings_exclude_drafts() {
		$html = get_recentposts( 'post', 10, false );

		$this->assertStringContainsString( 'Stats Post One', $html, 'A published post is listed.' );
		$this->assertStringNotContainsString( 'Stats Draft', $html, 'A draft is not, so the listing is what a visitor may see.' );
	}

	/**
	 * Comment author names are escaped, not rendered.
	 */
	public function test_comment_author_names_are_escaped() {
		// Keyed by the tag that produced the markup, so a failure names which of
		// the two stopped escaping rather than reporting an anonymous string.
		$rendered = array(
			'get_recentcomments'      => get_recentcomments( 'both', 10, false ),
			'get_commentmembersstats' => get_commentmembersstats( -1, 0, false ),
		);

		foreach ( $rendered as $tag => $html ) {
			$this->assertStringNotContainsString( '<script>alert(1)</script>', $html, $tag . '() renders a comment author name as text.' );
		}
	}

	/**
	 * $chars truncates the link text but leaves the title attribute whole.
	 */
	public function test_most_commented_truncates_the_link_text() {
		$html = get_mostcommented( 'post', 10, 6, false );

		$this->assertStringContainsString( '>Stats ...</a>', $html, 'The link text is truncated to the character limit.' );
		$this->assertStringNotContainsString( '>Stats Post One</a>', $html, 'The untruncated title is not rendered alongside it.' );
	}

	/**
	 * The threshold hides commenters below it.
	 */
	public function test_comment_members_honours_the_threshold() {
		$this->assertStringContainsString( 'Normal Commenter', get_commentmembersstats( -1, 0, false ), 'Below the threshold the commenter is listed.' );
		$this->assertStringNotContainsString( 'Normal Commenter', get_commentmembersstats( 99, 0, false ), 'Above it they are not, so the threshold is what filters.' );
	}

	/**
	 * Snippet text truncates and leaves short text alone.
	 */
	public function test_snippet_text() {
		$this->assertSame( 'Stats Post...', snippet_text( 'Stats Post One', 10 ), 'A string past the limit is cut and given an ellipsis.' );
		$this->assertSame( 'Short', snippet_text( 'Short', 100 ), 'A string within it is returned whole, with no ellipsis added.' );
	}

	/**
	 * $display = true echoes; $display = false returns.
	 */
	public function test_display_argument_echoes_or_returns() {
		ob_start();
		get_totalposts( true );
		$echoed = ob_get_clean();

		$this->assertSame( (string) get_totalposts( false ), $echoed, 'The display argument echoes exactly what the return form would have given.' );
	}

	/**
	 * The author link is appended with the right separator either way.
	 *
	 * @dataProvider data_author_links
	 *
	 * @param string $url      Configured stats URL.
	 * @param int    $page     Page number.
	 * @param string $expected Resulting link.
	 */
	public function test_stats_page_link( $url, $page, $expected ) {
		$this->set_url( $url );

		$this->assertSame( $expected, stats_page_link( 'bob', $page ), 'The author link is built from the configured Stats URL.' );
	}

	/**
	 * Stats URLs and the links built from them.
	 *
	 * @return array
	 */
	public function data_author_links() {
		return array(
			'plain'             => array( 'https://example.com/stats/', 0, 'https://example.com/stats/?stats_author=bob' ),
			'plain, paged'      => array( 'https://example.com/stats/', 3, 'https://example.com/stats/?stats_author=bob&amp;stats_page=3' ),
			'query string'      => array( 'https://example.com/?page_id=2', 0, 'https://example.com/?page_id=2&amp;stats_author=bob' ),
			'page 1 is implied' => array( 'https://example.com/stats/', 1, 'https://example.com/stats/?stats_author=bob' ),
		);
	}
}
