<?php
/**
 * Uninstall WP-Stats.
 *
 * Runs with the plugin inactive, so nothing here may depend on the plugin's own
 * classes or constants being loaded. The row names are therefore spelled out
 * rather than read from WP_Stats_Options.
 *
 * @package WP-Stats
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete the plugin's rows for the current site.
 *
 * The pre-3.0.0 names are still listed: an install deactivated before the
 * migration fired would otherwise keep them for ever, and tests/test-metadata.php
 * asserts over wp_options with a LIKE rather than naming rows, so a row added
 * later and forgotten here fails the suite.
 *
 * @return void
 */
function wp_stats_uninstall_site() {
	$option_names = array(
		// The settings row and the upgrade markers.
		'wp_stats_options',
		'wp_stats_version',
		// Pre-3.0.0 rows, including the unprefixed name an unreleased 3.0.0
		// build used before the prefix rule landed.
		'stats_options',
		'stats_db_version',
		'stats_mostlimit',
		'stats_display',
		'stats_url',
		// Widget instances.
		'widget_stats',
	);

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}
}

if ( is_multisite() ) {
	/*
	 * 'number' => 0 lifts WP_Site_Query's default cap of 100, which would
	 * otherwise leave the rows behind on every site past the hundredth while
	 * still reporting a successful uninstall. 'fields' => 'ids' avoids
	 * hydrating WP_Site objects the loop never looks at.
	 */
	$wp_stats_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $wp_stats_site_ids as $wp_stats_site_id ) {
		// switch_to_blog() pushes onto a stack, so the restore belongs inside
		// the loop -- restoring once at the end leaves it unwound by one.
		switch_to_blog( (int) $wp_stats_site_id );

		wp_stats_uninstall_site();

		restore_current_blog();
	}
} else {
	wp_stats_uninstall_site();
}
