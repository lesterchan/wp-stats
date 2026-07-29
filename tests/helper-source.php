<?php
/**
 * Source-inspection helpers shared by the test cases.
 *
 * Kept apart from helper-testcase.php because a file may declare either
 * functions or a class, not both.
 *
 * @package WP-Stats
 */

/**
 * Every shipped PHP source file, root and includes/.
 *
 * Built from two glob() calls rather than one GLOB_BRACE pattern: GLOB_BRACE is
 * a GNU extension and is not defined in every PHP build, including the one in
 * the wp-env container.
 *
 * @return string[] Absolute paths.
 */
function wp_stats_test_source_files() {
	$root = dirname( __DIR__ );

	return array_merge(
		(array) glob( $root . '/*.php' ),
		(array) glob( $root . '/includes/*.php' )
	);
}

/**
 * Every shipped PHP source file concatenated, with all comments removed.
 *
 * Comments must not be searchable: these files document the arrangements they
 * no longer use, so "does WP-Stats still read stats_display?" would match the
 * migration's own explanation of why it does not, and the assertion would fail
 * for the wrong reason - or worse, pass for one.
 *
 * @param string[] $skip Basenames to leave out.
 * @return string
 */
function wp_stats_test_source_code( array $skip = array() ) {
	$code = '';

	foreach ( wp_stats_test_source_files() as $file ) {
		if ( in_array( basename( $file ), $skip, true ) ) {
			continue;
		}

		$code .= php_strip_whitespace( $file );
	}

	return $code;
}

/**
 * Read a file from the plugin root.
 *
 * @param string $relative Path relative to the plugin root.
 * @return string
 */
function wp_stats_test_read( $relative ) {
	return (string) file_get_contents( dirname( __DIR__ ) . '/' . $relative );
}

/**
 * Run uninstall.php the way WordPress does.
 *
 * The file declares wp_stats_uninstall_site(), so it can only be required once
 * per process; every later call has to go straight to the function or PHP
 * fatals on the redeclaration. Two test files exercise uninstall and their
 * order is not fixed, so both go through here.
 *
 * @return void
 */
function wp_stats_test_run_uninstall() {
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		define( 'WP_UNINSTALL_PLUGIN', 'wp-stats/wp-stats.php' );
	}

	if ( function_exists( 'wp_stats_uninstall_site' ) ) {
		wp_stats_uninstall_site();
	} else {
		require dirname( __DIR__ ) . '/uninstall.php';
	}

	WP_Stats_Options::flush();
}
