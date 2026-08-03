<?php
/**
 * Uninstall behaviour.
 *
 * The family-wide "nothing matching wp_stats_% survives" assertion is in
 * test-metadata.php. What is here is the rest: the pre-3.0.0 rows an aborted
 * migration can leave behind, and the shape of the multisite branch, which a
 * single-site suite cannot run and which has failed silently before.
 *
 * @package WP-Stats
 */

/**
 * @coversNothing
 */
class WP_Stats_Uninstall_Test extends WP_Stats_TestCase {

	/**
	 * Every option the plugin can leave behind.
	 *
	 * @var string[]
	 */
	private $options = array(
		'wp_stats_options',
		'wp_stats_version',
		'stats_options',
		'stats_db_version',
		'stats_mostlimit',
		'stats_display',
		'stats_url',
		'widget_stats',
	);

	/**
	 * Uninstalling removes the settings row, the upgrade markers, the widget
	 * instances and every pre-3.0.0 row an aborted migration left behind.
	 */
	public function test_it_removes_every_option() {
		global $wpdb;

		foreach ( $this->options as $option ) {
			update_option( $option, 'set' );
		}

		wp_stats_test_run_uninstall();

		// Checked against the options table rather than through get_option(),
		// which reads through the object cache: a row deleted while the plugin
		// is loaded can still be answered from there.
		foreach ( $this->options as $option ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->options WHERE option_name = %s", $option ) );
			$this->assertSame( 0, (int) $count, "$option should have been deleted." );
		}
	}

	/**
	 * Uninstalling twice is not an error.
	 */
	public function test_it_is_idempotent() {
		global $wpdb;

		wp_stats_test_run_uninstall();
		wp_stats_test_run_uninstall();

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options WHERE option_name = 'wp_stats_options'" );
		$this->assertSame( 0, (int) $count );
	}

	/**
	 * The multisite loop must not be capped at a hundred sites.
	 *
	 * WP_SITE_Query defaults 'number' to 100, so a bare get_sites() silently
	 * stops at the hundredth site and leaves every later one untouched while
	 * still reporting success. A single-site suite cannot stand up a 101-site
	 * network, so this is a source-level guard: it exists to stop the argument
	 * being dropped again, not to exercise the loop.
	 */
	public function test_the_multisite_loop_is_not_capped() {
		// Comments are stripped first. The comment above the call explains the
		// argument, so matching the raw file would match the prose and pass even
		// when the code says something else - which is exactly what happened the
		// first time this test was written.
		$source = $this->code_of( dirname( __DIR__ ) . '/uninstall.php' );

		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $source, "get_sites() must be passed 'number' => 0." );
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $source, 'get_sites() should only fetch ids.' );
		$this->assertStringNotContainsString( 'wp_get_sites', $source, 'wp_get_sites() was deprecated in WP 4.6.' );
	}

	/**
	 * A PHP file's code with its comments and whitespace removed.
	 *
	 * @param string $file Absolute path.
	 * @return string
	 */
	private function code_of( $file ) {
		return php_strip_whitespace( $file );
	}

	/**
	 * The blog is restored inside the loop.
	 *
	 * Switching pushes onto a stack, so restoring once after the loop leaves
	 * it unwound by exactly one.
	 */
	public function test_the_blog_is_restored_inside_the_loop() {
		$source = $this->code_of( dirname( __DIR__ ) . '/uninstall.php' );

		$restore = strpos( $source, 'restore_current_blog' );
		$this->assertNotFalse( $restore, 'uninstall.php calls restore_current_blog at all.' );

		// The single-site branch of the if/else. Everything before it is the
		// multisite loop, so that is where the restore has to fall.
		$this->assertSame( 1, preg_match( '/\}\s*else\s*\{/', $source, $m, PREG_OFFSET_CAPTURE ) );

		$this->assertLessThan( $m[0][1], $restore, 'restore_current_blog() must be inside the foreach.' );
	}
}
