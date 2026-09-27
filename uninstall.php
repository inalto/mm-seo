<?php
/**
 * MM SEO — Uninstall script.
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Only deletes data when the 'delete_data_on_uninstall' setting is enabled.
 *
 * Multisite-aware: iterates every site on a network installation.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove all MM SEO data for a single site (the current blog).
 *
 * @param \wpdb $wpdb WordPress database object.
 */
function mmseo_uninstall_site( \wpdb $wpdb ): void {

	// ------------------------------------------------------------------
	// Options
	// ------------------------------------------------------------------

	$options = [
		'mmseo_settings',
		'mmseo_titles',
		'mmseo_social',
		'mmseo_redirects',
		'mmseo_robots_txt',
		'mmseo_indexnow_key',
		'mmseo_db_version',
		'mmseo_flush_rewrite',
		'mmseo_sitemap_stamp',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// ------------------------------------------------------------------
	// Post meta (all keys with _mmseo_ prefix)
	// ------------------------------------------------------------------

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` LIKE '\_mmseo\_%'"
	);

	// ------------------------------------------------------------------
	// Term meta (all keys with _mmseo_ prefix)
	// ------------------------------------------------------------------

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM `{$wpdb->termmeta}` WHERE `meta_key` LIKE '\_mmseo\_%'"
	);

	// ------------------------------------------------------------------
	// Custom table
	// ------------------------------------------------------------------

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}mmseo_404_log`" );

	// ------------------------------------------------------------------
	// Cron events
	// ------------------------------------------------------------------

	wp_clear_scheduled_hook( 'mmseo_purge_404_log' );
}

global $wpdb;

if ( is_multisite() ) {
	// Iterate every site on the network.
	$sites = get_sites( [ 'number' => 0, 'fields' => 'ids' ] );

	foreach ( $sites as $site_id ) {
		switch_to_blog( (int) $site_id );

		// Re-assign $wpdb after switch so table prefixes are correct.
		global $wpdb;

		// Check the per-site setting.
		$settings = get_option( 'mmseo_settings', [] );
		if ( ! empty( $settings['delete_data_on_uninstall'] ) ) {
			mmseo_uninstall_site( $wpdb );
		}

		restore_current_blog();
	}
} else {
	$settings = get_option( 'mmseo_settings', [] );

	// Bail out without deleting anything if the admin has not opted in.
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	mmseo_uninstall_site( $wpdb );
}
