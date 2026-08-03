<?php
/**
 * WP-Stats' half of the metadata contract.
 *
 * The contract itself is Plugin_Metadata_TestCase, a byte-identical copy of
 * _standards/templates/helper-metadata-testcase.php that every one of the
 * nineteen plugins carries. Everything shared lives there.
 *
 * What is left is what a machine cannot derive from the directory, plus the
 * assertions that are genuinely about WP-Stats: the five readme tags, the
 * licence block, the dead forum links that must not come back, the text domain
 * on every translation call, and the one stylesheet's logical properties.
 *
 * @package WP-Stats
 */

/**
 * The shared contract, plus what only WP-Stats can answer.
 *
 * @coversNothing
 */
class WP_Stats_Metadata_Test extends Plugin_Metadata_TestCase {

	/**
	 * The version this release ships.
	 *
	 * Written out rather than read from WP_STATS_VERSION, so a bump has to be
	 * made here as well and cannot happen by accident.
	 *
	 * @return string
	 */
	protected function expected_version() {
		return '3.0.0';
	}

	/**
	 * The prefix every class the plugin declares carries.
	 *
	 * @return string
	 */
	protected function class_prefix() {
		return 'WP_Stats';
	}

	/**
	 * Everything a site owner updating from the released version would notice.
	 *
	 * The two admin screens that became one, the option rows that were folded
	 * up and deleted, the renamed page hook, the nine section filters and the
	 * seven admin ones that were removed outright along with
	 * stats_display_defaults(), the single filter that replaces them, and the
	 * WP-PageNavi arrangement that is gone.
	 *
	 * @return string[]
	 */
	protected function upgrade_notice_subjects() {
		return array(
			'6.8',
			'8.2',
			'admin.php?page=wp-stats',
			'wp_stats_capability',
			'stats_url',
			'stats_mostlimit',
			'stats_display',
			'wp_stats_options',
			'wp_stats_version',
			'stats_page',
			'wp_stats_page',
			'wp_stats_page_general',
			'wp_stats_page_admin_',
			'wp_stats_paging_start',
			'wp_stats_paging_end',
			'stats_display_defaults()',
			'wp_stats_sections',
			'WP-PageNavi',
		);
	}

	/**
	 * WP-Stats is one of the seven sharing the WP-Stats surface.
	 *
	 * It is the one whose page the other six put their blocks on, so it makes
	 * the same promise about updating the set together.
	 *
	 * @return bool
	 */
	protected function wp_stats_family() {
		return true;
	}

	/**
	 * WP-Stats owns stats_display and stats_mostlimit, so it may delete them.
	 *
	 * The list is the rows a plugin reads but does not own, and for this plugin
	 * that is none of them. The other six return one or both.
	 *
	 * @return string[]
	 */
	protected function shared_wp_stats_rows() {
		return array();
	}

	/**
	 * Write the rows uninstall is expected to remove.
	 *
	 * @return void
	 */
	protected function seed_option_rows() {
		WP_Stats_Options::maybe_upgrade();
	}

	/**
	 * Write the wp_stats_version marker row.
	 *
	 * @return void
	 */
	protected function write_version_row() {
		WP_Stats_Options::maybe_upgrade();
	}

	/**
	 * Round-trip the settings sanitiser.
	 *
	 * @param array $input What the settings form is pretending to have posted.
	 * @return array
	 */
	protected function sanitize_settings( array $input ) {
		return (array) WP_Stats_Settings::sanitize( $input );
	}

	/**
	 * Real settings keys to send through the sanitiser beside the poison.
	 *
	 * @return array
	 */
	protected function settings_fixture() {
		return array(
			'url'        => 'https://example.com/stats/',
			'most_limit' => '10',
			'display'    => array( 'total_stats' => '1' ),
		);
	}

	/**
	 * Enqueue the one stylesheet the plugin ships.
	 *
	 * It is only enqueued where it applies - a post carrying the shortcode, or
	 * a sidebar carrying the widget - so the request has to be put into that
	 * shape first or nothing is registered and the assertions run against an
	 * empty list.
	 *
	 * @return void
	 */
	protected function register_plugin_assets() {
		$post_id = self::factory()->post->create( array( 'post_content' => '[page_stats]' ) );

		$GLOBALS['post'] = get_post( $post_id );

		WP_Stats::enqueue_styles();
	}

	/**
	 * At most five tags.
	 *
	 * The listing shows five and silently ignores the rest, so a sixth is work
	 * that does nothing (§3.2).
	 *
	 * @return void
	 */
	public function test_the_readme_lists_at_most_five_tags() {
		$tags = $this->readme_field( 'Tags' );

		$this->assertNotEmpty( $tags, 'The readme must carry a Tags line.' );
		$this->assertLessThanOrEqual( 5, count( explode( ',', $tags ) ), 'wordpress.org shows five tags: ' . $tags );
	}

	/**
	 * The licence statement does not contradict itself.
	 *
	 * The header says "or later", so the GPL block below it has to be the
	 * "or later" variant too.
	 *
	 * @return void
	 */
	public function test_the_licence_block_is_the_or_later_variant() {
		$this->assertSame( 'GPLv2 or later', $this->header_field( 'License' ), 'The header offers the later-version option, matching the licence comment.' );
		$this->assertStringContainsString(
			'either version 2 of the License, or',
			$this->plugin_file(),
			'The GPL block is the v2-only variant, which contradicts the header above it.'
		);
	}

	/**
	 * No insecure or dead links remain.
	 *
	 * The old forums.lesterchan.net is gone, and the rest of these had drifted
	 * to http over twenty years. Code spans are exempt: they document input.
	 *
	 * @return void
	 */
	public function test_no_insecure_or_dead_links_remain() {
		$readme = (string) preg_replace( '/`[^`]*`/', '', $this->readme() );

		$this->assertSame( 0, preg_match( '#http://#', $readme ), 'Every readme link must use https.' );
		$this->assertSame( 0, preg_match( '#http://#', $this->plugin_file() ), 'Every plugin header link must use https.' );
		$this->assertStringNotContainsString( 'forums.lesterchan.net', $readme, 'The old support forum no longer exists.' );
	}

	/**
	 * Every translation call names the plugin's text domain.
	 *
	 * A missing domain is not a parse error and not a phpcs error either once
	 * the call is built across two lines, so it ships and simply never
	 * translates.
	 *
	 * @return void
	 */
	public function test_every_translation_call_uses_the_plugin_text_domain() {
		$pattern = '/(?<![\w])(?:esc_html__|esc_attr__|esc_html_e|esc_attr_e|_n_noop|__|_n)\((.*?)\);/s';

		preg_match_all( $pattern, wp_stats_test_source_code(), $calls );

		foreach ( $calls[1] as $arguments ) {
			$this->assertStringContainsString(
				"'wp-stats'",
				$arguments,
				"A translation call is missing the text domain: {$arguments}"
			);
		}
	}

	/**
	 * The one stylesheet uses logical properties throughout.
	 *
	 * §5.1 drops the mirrored sheet, and the shared test proves no second sheet
	 * ships. That is only safe if the sheet that remains works in both
	 * directions: a physical margin-left reads correctly in English and wrongly
	 * in Arabic, and nothing else would catch it.
	 *
	 * @return void
	 */
	public function test_the_stylesheet_uses_logical_properties_only() {
		$sheets = (array) glob( $this->metadata_root() . '/css/*.css' );

		$this->assertNotEmpty( $sheets, 'The plugin ships a stylesheet.' );

		foreach ( $sheets as $sheet ) {
			$this->assertSame(
				0,
				preg_match( '/(?:margin|padding|border)-(?:left|right)\s*:/', (string) file_get_contents( $sheet ) ),
				basename( $sheet ) . ' must use logical properties, or RTL needs a second sheet.'
			);
		}
	}
}
