<?php
/**
 * Uninstall behaviour.
 *
 * @package WP-Stats
 */

/**
 * Uninstall.php.
 */
class WP_Stats_Uninstall_Test extends WP_Stats_TestCase {

	/**
	 * Every option the plugin can leave behind.
	 *
	 * @var string[]
	 */
	private $options = array(
		'stats_options',
		'stats_db_version',
		'stats_mostlimit',
		'stats_display',
		'stats_url',
		'widget_stats',
	);

	/**
	 * Uninstalling removes the consolidated option, the schema version, the
	 * widget instances and any pre-3.0.0 rows an aborted migration left.
	 */
	public function test_it_removes_every_option() {
		global $wpdb;

		foreach ( $this->options as $option ) {
			update_option( $option, 'set' );
		}

		$this->run_uninstall();

		// Checked against the options table rather than through get_option():
		// the backwards-compatibility filters answer the three legacy names out
		// of the consolidated row, so get_option() never reports them missing
		// while the plugin is loaded.
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

		$this->run_uninstall();
		$this->run_uninstall();

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options WHERE option_name = 'stats_options'" );
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
		$this->assertNotFalse( $restore );

		// The single-site branch of the if/else. Everything before it is the
		// multisite loop, so that is where the restore has to fall.
		$this->assertSame( 1, preg_match( '/\}\s*else\s*\{/', $source, $m, PREG_OFFSET_CAPTURE ) );

		$this->assertLessThan( $m[0][1], $restore, 'restore_current_blog() must be inside the foreach.' );
	}

	/**
	 * Run uninstall.php the way WordPress does.
	 *
	 * The file declares stats_uninstall_site(), so it can only be required
	 * once per process; later calls go straight to the function.
	 *
	 * @return void
	 */
	private function run_uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-stats/wp-stats.php' );
		}

		if ( function_exists( 'stats_uninstall_site' ) ) {
			stats_uninstall_site();
		} else {
			require dirname( __DIR__ ) . '/uninstall.php';
		}

		WP_Stats_Options::flush();
	}
}
