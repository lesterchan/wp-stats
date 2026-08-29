<?php
/**
 * Tests for the front end stylesheet gating.
 *
 * @package WP-Stats
 */

/**
 * When the stylesheet is enqueued, and when it is not.
 *
 * The rules are for the paging strip in the per-commenter view, which only the
 * [page_stats] shortcode, the block and the widget lead to, so the front end
 * does not carry them site-wide. There is one pass and no footer fallback:
 * whatever this decides is what the page gets.
 *
 * @covers WP_Stats
 */
class WP_Stats_Assets_Test extends WP_Stats_TestCase {

	/**
	 * Start each test from an empty registry.
	 *
	 * The wp_styles() registry is a process global, so a handle one test
	 * enqueues would answer for the next.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		unset( $GLOBALS['wp_styles'], $GLOBALS['_wp_sidebars_widgets'] );
	}

	/**
	 * A page that leads to no statistics carries no stylesheet.
	 *
	 * @return void
	 */
	public function test_a_bare_page_carries_no_stylesheet() {
		WP_Stats::enqueue_styles();

		$this->assertFalse( wp_style_is( 'wp-stats', 'enqueued' ), 'A page showing no statistics must not carry the stylesheet.' );
	}

	/**
	 * A post holding the shortcode gets the stylesheet.
	 *
	 * @return void
	 */
	public function test_a_post_holding_the_shortcode_enqueues_the_stylesheet() {
		$GLOBALS['post'] = get_post(
			self::factory()->post->create( array( 'post_content' => '[page_stats]' ) )
		);

		WP_Stats::enqueue_styles();

		$this->assertTrue( wp_style_is( 'wp-stats', 'enqueued' ), 'The shortcode is a reason to load the stylesheet.' );
	}

	/**
	 * An active widget gets the stylesheet.
	 *
	 * The widget renders in the sidebar, well after `wp_enqueue_scripts`, but
	 * whether it is in a sidebar at all is already on record by then.
	 *
	 * @return void
	 */
	public function test_an_active_widget_enqueues_the_stylesheet() {
		wp_set_sidebars_widgets( array( 'sidebar-1' => array( 'stats-2' ) ) );

		WP_Stats::enqueue_styles();

		$this->assertTrue( wp_style_is( 'wp-stats', 'enqueued' ), 'An active widget is a reason to load the stylesheet.' );
	}

	/**
	 * A render path the detection cannot see says so through the filter.
	 *
	 * A theme calling WP_Stats_Page::render() itself, or markup fetched over
	 * AJAX into a page carrying no shortcode, block or widget, is invisible to
	 * the detection -- and there is no second pass to recover from it.
	 *
	 * @return void
	 */
	public function test_the_filter_can_ask_for_the_stylesheet_on_a_bare_page() {
		add_filter( 'wp_stats_needs_styles', '__return_true' );

		WP_Stats::enqueue_styles();

		$this->assertTrue( wp_style_is( 'wp-stats', 'enqueued' ), 'The filter asked for the stylesheet and did not get it.' );
	}

	/**
	 * The filter sees what the detection found, and can overrule it.
	 *
	 * @return void
	 */
	public function test_the_filter_sees_the_detected_value_and_can_refuse_it() {
		$GLOBALS['post'] = get_post(
			self::factory()->post->create( array( 'post_content' => '[page_stats]' ) )
		);

		$seen = null;
		add_filter(
			'wp_stats_needs_styles',
			function ( $needs_styles ) use ( &$seen ) {
				$seen = $needs_styles;
				return false;
			}
		);

		WP_Stats::enqueue_styles();

		$this->assertTrue( $seen, 'The filter was handed the detected value.' );
		$this->assertFalse( wp_style_is( 'wp-stats', 'enqueued' ), 'The enqueue ran over the filter.' );
	}
}
