<?php
/**
 * Shared fixtures for the WP-Stats suite.
 *
 * @package WP-Stats
 */

/**
 * Base case: a small blog with known counts, dated in the past.
 */
abstract class WP_Stats_TestCase extends WP_UnitTestCase {

	/**
	 * Diagnostics raised by the code under test.
	 *
	 * @var string[]
	 */
	protected $notices = array();

	/**
	 * Seeded fixture ids, keyed by a short name.
	 *
	 * @var array<string,int>
	 */
	protected $ids = array();

	/**
	 * Reset the plugin's per-request state before each test.
	 */
	public function set_up() {
		parent::set_up();

		WP_Stats_Options::flush();
		WP_Stats_Options::update( WP_Stats_Options::defaults() );
		WP_Stats_Options::flush();

		// A previous test may have left one behind, and get_query_var() has no
		// notion of "not set for this request".
		set_query_var( 'stats_author', '' );
		set_query_var( 'stats_page', '' );

		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );
	}

	/**
	 * Content dated in the past.
	 *
	 * Every listing filters on `post_date < current_time('mysql')`, so content
	 * created "now" is a race: if the insert and the query land in the same
	 * second the row disappears from the results.
	 *
	 * @param int $days How many days ago.
	 * @return array Post date arguments.
	 */
	protected function days_ago( $days ) {
		$when = gmdate( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

		return array(
			'post_date'     => $when,
			'post_date_gmt' => $when,
		);
	}

	/**
	 * Seed two posts, a page, a draft and a handful of comments.
	 *
	 * @return void
	 */
	protected function seed_blog() {
		$editor = self::factory()->user->create(
			array(
				'role'         => 'editor',
				'display_name' => 'Ed Editor',
			)
		);

		$p1 = self::factory()->post->create(
			$this->days_ago( 3 ) + array(
				'post_title'  => 'Stats Post One',
				'post_status' => 'publish',
				'post_author' => $editor,
			)
		);
		$p2 = self::factory()->post->create(
			$this->days_ago( 2 ) + array(
				'post_title'  => 'Stats Post Two',
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create(
			$this->days_ago( 1 ) + array(
				'post_title'  => 'Stats Draft',
				'post_status' => 'draft',
			)
		);
		$page = self::factory()->post->create(
			$this->days_ago( 4 ) + array(
				'post_title'  => 'Stats Page One',
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);

		$this->add_comment( $p1, 'Normal Commenter', 30 );
		$this->add_comment( $p1, 'Normal Commenter', 28 );
		$this->add_comment( $p2, 'Normal Commenter', 26 );
		$this->add_comment( $p2, '<script>alert(1)</script>', 24 );

		$this->seed_link();

		$this->ids = compact( 'editor', 'p1', 'p2', 'page' );
	}

	/**
	 * One link, in one link category.
	 *
	 * The links table has had no UI since WP 3.5 and no factory, so the row goes
	 * in directly. Link categories are hidden when empty, so the term needs the
	 * link attached or the block renders nothing.
	 *
	 * @return void
	 */
	protected function seed_link() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->links,
			array(
				'link_url'     => 'https://example.com/',
				'link_name'    => 'Stats Link',
				'link_visible' => 'Y',
			)
		);

		$link_id = (int) $wpdb->insert_id;
		$term    = wp_insert_term( 'Stats Link Cat', 'link_category' );

		if ( ! is_wp_error( $term ) && $link_id ) {
			wp_set_object_terms( $link_id, array( (int) $term['term_id'] ), 'link_category' );
		}
	}

	/**
	 * One approved comment, dated in the past.
	 *
	 * @param int    $post_id Post to attach it to.
	 * @param string $author  Comment author name.
	 * @param int    $hours   How many hours ago.
	 * @return int
	 */
	protected function add_comment( $post_id, $author, $hours ) {
		$when = gmdate( 'Y-m-d H:i:s', strtotime( "-$hours hours" ) );

		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_author'   => $author,
				'comment_content'  => 'hello',
				'comment_approved' => 1,
				'comment_type'     => 'comment',
				'comment_date'     => $when,
				'comment_date_gmt' => $when,
			)
		);
	}

	/**
	 * Set the display toggles, expanded over the defaults.
	 *
	 * @param array $display Partial toggle map.
	 * @return void
	 */
	protected function set_display( array $display ) {
		$options            = WP_Stats_Options::get();
		$options['display'] = array_merge( WP_Stats_Options::display_defaults(), $display );
		WP_Stats_Options::update( $options );
		WP_Stats_Options::flush();
	}

	/**
	 * Set the URL of the page holding the shortcode.
	 *
	 * @param string $url Stats page URL.
	 * @return void
	 */
	protected function set_url( $url ) {
		$options        = WP_Stats_Options::get();
		$options['url'] = $url;
		WP_Stats_Options::update( $options );
		WP_Stats_Options::flush();
	}

	/**
	 * Set the "top X" limit.
	 *
	 * @param int $limit Limit.
	 * @return void
	 */
	protected function set_limit( $limit ) {
		$options               = WP_Stats_Options::get();
		$options['most_limit'] = (int) $limit;
		WP_Stats_Options::update( $options );
		WP_Stats_Options::flush();
	}

	/**
	 * Render something while collecting any diagnostic it raises.
	 *
	 * Asserting the notice list is empty is what makes these more than smoke
	 * tests: it proves the page is clean under PHP 8, not merely non-empty.
	 *
	 * @param callable $callback Thing to run.
	 * @return string Captured output plus whatever the callback returned.
	 */
	protected function render( callable $callback ) {
		$this->notices = array();

		set_error_handler(
			function ( $errno, $errstr, $errfile = '', $errline = 0 ) {
				if ( false !== strpos( (string) $errfile, 'wp-stats' ) ) {
					$this->notices[] = $errstr . ' in ' . basename( $errfile ) . ':' . $errline;
				}
				return true;
			}
		);

		try {
			ob_start();
			$returned = $callback();
			return ob_get_clean() . (string) $returned;
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Assert markup is free of the damage no PHP-level tool reports.
	 *
	 * @param string $html Rendered markup.
	 * @return void
	 */
	protected function assertMarkupIsClean( $html ) {
		$this->assertSame( array(), $this->notices, 'Rendering raised diagnostics.' );
		$this->assertStringNotContainsString( '<?php', $html, 'Unclosed PHP tag.' );
		$this->assertStringNotContainsString( 'translators:', $html, 'A translators comment reached the markup.' );
		$this->assertStringNotContainsString( '&amp;nbsp;', $html, 'Double-encoded entity.' );
		$this->assertStringNotContainsString( 'Warning</b>', $html, 'PHP warning in the markup.' );
		$this->assertStringNotContainsString( 'Fatal error', $html, 'PHP fatal in the markup.' );
	}
}
