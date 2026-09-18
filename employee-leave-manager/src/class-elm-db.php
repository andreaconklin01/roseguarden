<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_DB {
	private static bool $schema_confirmed = false;
	private static bool $transaction_active = false;

	private const REQUEST_SNAPSHOT_COLUMNS = array( 'employee_position', 'employee_sector' );

	private const TABLES = array(
		'requests',
		'request_days',
		'balances',
		'adjustments',
		'entitlement_changes',
		'capacity',
		'audit',
		'medical_documents',
	);

	public static function table( string $name ): string {
		global $wpdb;
		if ( ! in_array( $name, self::TABLES, true ) ) {
			throw new InvalidArgumentException( 'Unknown ELM table.' );
		}
		return $wpdb->prefix . 'elm_' . $name;
	}

	public static function missing_tables(): array {
		global $wpdb;
		$missing = array();
		foreach ( self::TABLES as $name ) {
			$table = self::table( $name );
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( $table !== $found ) {
				$missing[] = $table;
			}
		}
		return $missing;
	}

	public static function missing_request_snapshot_columns(): array {
		global $wpdb;
		$table = self::table( 'requests' );
		if ( in_array( $table, self::missing_tables(), true ) ) {
			return self::REQUEST_SNAPSHOT_COLUMNS;
		}
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM $table", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return array_values( array_diff( self::REQUEST_SNAPSHOT_COLUMNS, is_array( $columns ) ? $columns : array() ) );
	}

	public static function schema_ready(): bool {
		if ( self::$schema_confirmed ) {
			return true;
		}
		self::$schema_confirmed = empty( self::missing_tables() ) && empty( self::missing_request_snapshot_columns() );
		return self::$schema_confirmed;
	}

	public static function begin(): void {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		self::$transaction_active = true;
	}

	public static function commit(): void {
		global $wpdb;
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		self::$transaction_active = false;
		if ( class_exists( 'ELM_Audit', false ) ) {
			ELM_Audit::release_lock();
		}
	}

	public static function rollback(): void {
		global $wpdb;
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		self::$transaction_active = false;
		if ( class_exists( 'ELM_Audit', false ) ) {
			ELM_Audit::release_lock();
		}
	}

	public static function in_transaction(): bool {
		return self::$transaction_active;
	}

	public static function now(): string {
		return current_datetime()->format( 'Y-m-d H:i:s' );
	}

	public static function log_failure( string $operation, Throwable|string|null $error = null ): void {
		global $wpdb;
		$parts = array( '[Employee Leave Manager]', $operation );
		if ( $error instanceof Throwable ) {
			$parts[] = $error->getMessage();
		} elseif ( is_string( $error ) && '' !== $error ) {
			$parts[] = $error;
		}
		if ( ! empty( $wpdb->last_error ) ) {
			$parts[] = 'Database: ' . $wpdb->last_error;
		}
		error_log( implode( ' | ', $parts ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
