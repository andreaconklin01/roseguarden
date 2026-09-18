<?php
/**
 * Database schema installation and migration.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the certificates table definition and its upgrade path.
 */
final class Schema {

	/**
	 * Option holding the schema version the database was last migrated to.
	 */
	public const VERSION_OPTION = 'deftese_db_version';

	/**
	 * Legacy option written by the 6.x "forced upgrade" safety net.
	 *
	 * It is still read so that sites upgrading from 6.x do not re-run the
	 * column patch, and still written so a downgrade keeps working.
	 */
	public const LEGACY_OPTION = 'deftese_force_db_v1';

	/**
	 * Fully-qualified table name.
	 *
	 * @return string Prefixed table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . DEFTESE_TABLE;
	}

	/**
	 * Create or update the table.
	 *
	 * Safe to call repeatedly: dbDelta only issues the statements it needs.
	 */
	public static function install(): void {
		global $wpdb;

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id            VARCHAR(100)    NOT NULL,
			registry_no   VARCHAR(100)    NOT NULL DEFAULT '',
			student_name  VARCHAR(255)    NOT NULL DEFAULT '',
			school_name   VARCHAR(255)    NOT NULL DEFAULT '',
			school_city   VARCHAR(255)    NOT NULL DEFAULT '',
			protocol_no   VARCHAR(100)    NOT NULL DEFAULT '',
			ministry_code VARCHAR(100)    NOT NULL DEFAULT '',
			birth_date    VARCHAR(50)     NOT NULL DEFAULT '',
			birth_city    VARCHAR(100)    NOT NULL DEFAULT '',
			birth_commune VARCHAR(100)    NOT NULL DEFAULT '',
			birth_state   VARCHAR(100)    NOT NULL DEFAULT '',
			school_year   VARCHAR(20)     NOT NULL DEFAULT '',
			parent_name   VARCHAR(255)    NOT NULL DEFAULT '',
			cert_city     VARCHAR(100)    NOT NULL DEFAULT '',
			cert_date     VARCHAR(50)     NOT NULL DEFAULT '',
			class_teacher VARCHAR(255)    NOT NULL DEFAULT '',
			director_name VARCHAR(255)    NOT NULL DEFAULT '',
			stamp_text    VARCHAR(100)    NOT NULL DEFAULT '',
			footer_note   TEXT            NOT NULL,
			remarks_1     VARCHAR(255)    NOT NULL DEFAULT '',
			remarks_2     VARCHAR(255)    NOT NULL DEFAULT '',
			remarks_3     VARCHAR(255)    NOT NULL DEFAULT '',
			grades_json   LONGTEXT        NOT NULL,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			created_by    BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY idx_registry (registry_no),
			KEY idx_student (student_name(100)),
			KEY idx_created (created_at),
			KEY idx_created_by (created_by)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::VERSION_OPTION, DEFTESE_VERSION );
		update_option( self::LEGACY_OPTION, DEFTESE_VERSION );
	}

	/**
	 * Run the migration once per version change.
	 *
	 * The legacy plugin executed dbDelta plus a SHOW COLUMNS loop on *every*
	 * request until two separate options matched the version, which meant two
	 * schema round-trips per page load on any site where one of them failed to
	 * persist. This collapses that into a single guarded check.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( self::VERSION_OPTION );

		if ( DEFTESE_VERSION === $installed ) {
			return;
		}

		self::install();
	}

	/**
	 * Remove the table. Used by uninstall.php only.
	 */
	public static function drop(): void {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from $wpdb->prefix.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
