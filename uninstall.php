<?php
/**
 * WP-Stats uninstall.php
 *
 * @package WP-Stats
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete this site's WP-Stats options.
 *
 * @return void
 */
function stats_uninstall_site() {
	$option_names = array(
		// The consolidated option and its schema version.
		'stats_options',
		'stats_db_version',
		// Pre-3.0.0 rows. Still removed here so an install that never ran the
		// migration - deactivated before it fired - does not leave them behind.
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
	// 'number' => 0 lifts WP_Site_Query's default cap of 100, which silently
	// left every site past the hundredth untouched.
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		stats_uninstall_site();
		// switch_to_blog() pushes onto a stack, so restoring once after the loop
		// would leave it unwound by one.
		restore_current_blog();
	}
} else {
	stats_uninstall_site();
}
