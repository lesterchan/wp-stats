<?php
/*
 * Uninstall plugin
 */
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();

/**
 * Delete this site's WP-Stats options.
 *
 * @return void
 */
function stats_uninstall_site() {
	$option_names = array(
		'stats_mostlimit'
		, 'stats_display'
		, 'stats_url'
		, 'widget_stats'
	);

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}
}

if ( is_multisite() ) {
	// 'number' => 0 lifts WP_Site_Query's default cap of 100. wp_get_sites(),
	// which this used before, was deprecated in WP 4.6 and is capped the same way.
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

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
