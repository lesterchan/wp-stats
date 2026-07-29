<?php
/**
 * The wp_stats_sections contract, which six sibling plugins implement.
 *
 * This is the one place in the family where changing a plugin in isolation
 * breaks another one, so the shape is asserted here rather than assumed: an
 * entry is title, priority and render, entries sort by priority with the key as
 * the tie-break, and anything malformed is skipped rather than allowed to take
 * the page down with it.
 *
 * @package WP-Stats
 */

/**
 * @covers WP_Stats_Page
 */
class WP_Stats_Sections_Test extends WP_Stats_TestCase {

	/**
	 * A well-formed entry, as a contributing plugin would return it.
	 *
	 * @param string $key      Contributor key.
	 * @param string $title    Heading.
	 * @param int    $priority Sort order.
	 * @return array
	 */
	private function contribute( $key, $title, $priority = 10 ) {
		add_filter(
			'wp_stats_sections',
			static function ( $sections ) use ( $key, $title, $priority ) {
				$sections[ $key ] = array(
					'title'    => $title,
					'priority' => $priority,
					'render'   => static function () use ( $key ) {
						echo '<ul><li>body of ' . esc_html( $key ) . '</li></ul>';
					},
				);

				return $sections;
			}
		);

		return array( $key, $title, $priority );
	}

	/**
	 * Add whatever the closure returns, however malformed.
	 *
	 * @param callable $callback Filter callback.
	 * @return void
	 */
	private function contribute_raw( callable $callback ) {
		add_filter( 'wp_stats_sections', $callback );
	}

	public function test_a_contributed_section_reaches_the_page() {
		$this->contribute( 'wp_polls', 'Polls' );

		$html = $this->render( 'stats_page' );

		$this->assertStringContainsString( 'id="PluginsStats"', $html, 'The Plugins Stats heading appears once something contributes.' );
		$this->assertStringContainsString( '<strong>Polls</strong>', $html, 'WP-Stats echoes the title, not the contributor.' );
		$this->assertStringContainsString( 'body of wp_polls', $html, "The entry's render callback is what draws the body." );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * A site running WP-Stats alone should not be shown an empty heading.
	 */
	public function test_the_plugins_heading_is_absent_when_nothing_contributes() {
		$html = $this->render( 'stats_page' );

		$this->assertStringNotContainsString( 'id="PluginsStats"', $html );
	}

	public function test_sections_render_in_priority_order() {
		$this->contribute( 'wp_useronline', 'UserOnline', 30 );
		$this->contribute( 'wp_polls', 'Polls', 20 );
		$this->contribute( 'wp_email', 'EMail', 10 );

		$html = $this->render( 'stats_page' );

		$this->assertSame(
			array( 'EMail', 'Polls', 'UserOnline' ),
			$this->titles_in_order( $html ),
			'Lowest priority first.'
		);
	}

	/**
	 * Equal priorities have to resolve the same way on every request, or two
	 * plugins shipping the default 10 would swap places at random.
	 */
	public function test_equal_priorities_are_broken_by_the_section_key() {
		$this->contribute( 'wp_postviews', 'Views' );
		$this->contribute( 'wp_downloadmanager', 'Downloads' );
		$this->contribute( 'wp_polls', 'Polls' );

		$html = $this->render( 'stats_page' );

		$this->assertSame(
			array( 'Downloads', 'Polls', 'Views' ),
			$this->titles_in_order( $html ),
			'wp_downloadmanager, wp_polls, wp_postviews - the keys in string order.'
		);
	}

	public function test_a_missing_priority_takes_the_documented_default() {
		$this->contribute( 'wp_email', 'EMail', 5 );
		$this->contribute_raw(
			static function ( $sections ) {
				$sections['wp_polls'] = array(
					'title'  => 'Polls',
					'render' => static function () {
						echo '<ul><li>polls</li></ul>';
					},
				);

				return $sections;
			}
		);
		$this->contribute( 'wp_useronline', 'UserOnline', 20 );

		$this->assertSame(
			array( 'EMail', 'Polls', 'UserOnline' ),
			$this->titles_in_order( $this->render( 'stats_page' ) ),
			'An entry with no priority sorts as though it had said 10.'
		);
	}

	/**
	 * A sibling plugin is third-party code as far as WP-Stats is concerned.
	 *
	 * @dataProvider data_malformed_entries
	 *
	 * @param array $entry Whatever the contributor returned.
	 */
	public function test_a_malformed_entry_is_skipped_rather_than_fatal( $entry ) {
		$this->contribute( 'wp_polls', 'Polls' );
		$this->contribute_raw(
			static function ( $sections ) use ( $entry ) {
				$sections['wp_broken'] = $entry;

				return $sections;
			}
		);

		$html = $this->render( 'stats_page' );

		$this->assertSame( array( 'Polls' ), $this->titles_in_order( $html ), 'The good entry still renders.' );
		$this->assertMarkupIsClean( $html );
	}

	/**
	 * Every way an entry can be wrong.
	 *
	 * @return array
	 */
	public function data_malformed_entries() {
		return array(
			'not an array'        => array( 'nope' ),
			'no title'            => array(
				array(
					'render' => '__return_true',
				),
			),
			'empty title'         => array(
				array(
					'title'  => '',
					'render' => '__return_true',
				),
			),
			'title is not a string' => array(
				array(
					'title'  => array( 'Broken' ),
					'render' => '__return_true',
				),
			),
			'no render'           => array(
				array(
					'title' => 'Broken',
				),
			),
			'render not callable' => array(
				array(
					'title'  => 'Broken',
					'render' => 'wp_stats_no_such_function_exists',
				),
			),
		);
	}

	public function test_the_filter_returning_something_that_is_not_an_array_is_survivable() {
		$this->contribute_raw( '__return_false' );

		$this->assertSame( array(), WP_Stats_Page::sections() );
		$this->assertMarkupIsClean( $this->render( 'stats_page' ) );
	}

	/**
	 * The per-section action is what makes one plugin's block replaceable
	 * without disturbing the others.
	 */
	public function test_a_theme_can_take_one_sections_action_over() {
		$this->contribute( 'wp_polls', 'Polls' );
		$this->contribute( 'wp_email', 'EMail' );

		add_action(
			'wp_stats_section_wp_polls',
			static function () {
				remove_action( 'wp_stats_section_wp_polls', array( 'WP_Stats_Page', 'render_section' ) );
				echo '<p>replaced by the theme</p>';
			},
			5
		);

		$html = $this->render( 'stats_page' );

		$this->assertStringContainsString( 'replaced by the theme', $html );
		$this->assertStringNotContainsString( 'body of wp_polls', $html, "WP-Stats' own rendering stood down." );
		$this->assertStringContainsString( 'body of wp_email', $html, 'The other section is untouched.' );
	}

	/**
	 * The action carries the whole entry, so a listener can read the title and
	 * the priority the contributor declared.
	 */
	public function test_the_section_action_receives_the_entry() {
		$this->contribute( 'wp_polls', 'Polls', 7 );

		$seen = null;

		add_action(
			'wp_stats_section_wp_polls',
			static function ( $section ) use ( &$seen ) {
				$seen = $section;
			},
			5
		);

		$this->render( 'stats_page' );

		$this->assertSame( 'Polls', $seen['title'] );
		$this->assertSame( 7, $seen['priority'] );
		$this->assertIsCallable( $seen['render'] );
	}

	/**
	 * WP-Stats must never decide for a contributor whether it gets a block.
	 *
	 * The shared stats_display row was how it used to: seven plugins read and
	 * wrote one unprefixed option, and WP-Stats held the toggle for all of them.
	 */
	public function test_wp_stats_reads_no_sibling_option_row() {
		$source = wp_stats_test_source_code();

		foreach ( array( 'stats_display', 'stats_mostlimit' ) as $shared ) {
			$this->assertSame(
				0,
				preg_match( "/get_option\(\s*'{$shared}'/", $source ),
				"WP-Stats must not read the shared {$shared} row; deleting it in the migration is all it may do."
			);
		}

		$this->assertSame(
			0,
			preg_match( '/(class_exists|function_exists)\(\s*.(WP_Polls|WP_Email|WP_PostViews|WP_PostRatings|WP_UserOnline|WP_DownloadManager)/i', $source ),
			'Section contributors are discovered through the filter, never by probing for their classes.'
		);
	}

	/**
	 * The titles WP-Stats printed for the contributed sections, in order.
	 *
	 * @param string $html Rendered page.
	 * @return string[]
	 */
	private function titles_in_order( $html ) {
		$start = strpos( $html, 'id="PluginsStats"' );

		if ( false === $start ) {
			return array();
		}

		$plugins = substr( $html, $start, strpos( $html, 'id="TopRecentStats"' ) - $start );

		preg_match_all( '#<strong>(.+?)</strong>#', $plugins, $matches );

		return $matches[1];
	}
}
