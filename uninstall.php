<?php
/**
 * Clean up all Lean Cookie Consent data when the plugin is deleted.
 *
 * Removes every option the plugin has ever stored (legacy local CMP options
 * from versions 1.x and the new SaaS connector Site Key option from 2.0.0)
 * and drops the legacy `wp_lean_cookie_consent` table if present.
 *
 * @package LeanCookieConsent
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete every Lean Cookie Consent option and drop the legacy consent log table
 * for the current site. Multisite aware.
 *
 * @return void
 */
function lean_cookie_consent_uninstall_site() {
	global $wpdb;

	// Current (2.0.0+) Site Key option.
	delete_option( 'lean_cookie_consent_site_key' );
	delete_option( 'lean_cookie_consent_plugin_version' );
	delete_option( 'lean_cookie_consent_upgrade_notice' );

	// Legacy 1.x local CMP options.
	delete_option( 'lean_cookie_consent_settings' );
	delete_option( 'lean_cookie_consent_settings_version' );
	delete_option( 'lean_cookie_consent_db_version' );
	delete_option( 'lean_cookie_consent_hash_secret' );

	// Legacy consent log table. The table may not exist on fresh installs;
	// DROP TABLE IF EXISTS makes the cleanup safe.
	$table_name = $wpdb->prefix . 'lean_cookie_consent';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall cleanup of the legacy plugin custom consent log table.
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
}

if ( is_multisite() ) {
	$lean_cookie_consent_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $lean_cookie_consent_site_ids as $lean_cookie_consent_site_id ) {
		switch_to_blog( (int) $lean_cookie_consent_site_id );
		lean_cookie_consent_uninstall_site();
		restore_current_blog();
	}
} else {
	lean_cookie_consent_uninstall_site();
}
