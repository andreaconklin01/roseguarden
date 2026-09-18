<?php
/**
 * Uninstall routine.
 *
 * The 6.x plugin shipped no uninstaller, so removing it left the certificates
 * table, four options and three user-meta keys behind on every site. This
 * cleans up after itself — but only when the site owner explicitly opts in, so
 * deactivating and reactivating never destroys a school's records.
 *
 * Define DEFTESE_REMOVE_DATA as true in wp-config.php to erase the data.
 *
 * @package DeftesePro
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'DEFTESE_REMOVE_DATA' ) || ! DEFTESE_REMOVE_DATA ) {
	return;
}

global $wpdb;

$deftese_table = $wpdb->prefix . 'deftese_certificates';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$deftese_table}" );

foreach ( array( 'deftese_db_version', 'deftese_force_db_v1', 'deftese_layout_settings' ) as $deftese_option ) {
	delete_option( $deftese_option );
}

delete_transient( 'deftese_frontend_url' );

foreach ( array( '_deftese_2fa_secret', '_deftese_2fa_enabled', '_deftese_2fa_secret_pending' ) as $deftese_meta ) {
	delete_metadata( 'user', 0, $deftese_meta, '', true );
}
