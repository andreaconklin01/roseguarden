<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Long-lived cryptographic material used by ELM.
 *
 * WordPress authentication salts are intentionally not used for new records so
 * rotating AUTH/SECURE_AUTH/NONCE salts does not invalidate HR records. Legacy
 * keys are captured once during upgrade so existing encrypted documents and
 * audit entries remain readable/verifiable after a later WordPress salt change.
 */
final class ELM_Security {
	private const MEDICAL_KEY_OPTION        = 'elm_medical_master_key_v2';
	private const MEDICAL_LEGACY_OPTION     = 'elm_medical_legacy_key_v1';
	private const AUDIT_KEY_OPTION          = 'elm_audit_hmac_key_v2';
	private const AUDIT_LEGACY_OPTION       = 'elm_audit_legacy_key_v1';
	private const AUDIT_V2_START_ID_OPTION  = 'elm_audit_v2_start_after_id';

	public static function ensure_keys(): void {
		if ( false === get_option( self::MEDICAL_LEGACY_OPTION, false ) ) {
			self::add_secret_option( self::MEDICAL_LEGACY_OPTION, self::legacy_medical_key_from_wp_salts() );
		}
		if ( false === get_option( self::AUDIT_LEGACY_OPTION, false ) ) {
			self::add_secret_option( self::AUDIT_LEGACY_OPTION, wp_salt( 'auth' ) );
		}
		if ( false === get_option( self::MEDICAL_KEY_OPTION, false ) ) {
			self::add_secret_option( self::MEDICAL_KEY_OPTION, random_bytes( 32 ) );
		}
		if ( false === get_option( self::AUDIT_KEY_OPTION, false ) ) {
			self::add_secret_option( self::AUDIT_KEY_OPTION, random_bytes( 32 ) );
		}

		if ( false === get_option( self::AUDIT_V2_START_ID_OPTION, false ) ) {
			$last_id = 0;
			if ( class_exists( 'ELM_DB' ) && ELM_DB::schema_ready() ) {
				global $wpdb;
				$table = ELM_DB::table( 'audit' );
				$last_id = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			}
			add_option( self::AUDIT_V2_START_ID_OPTION, $last_id, '', false );
		}
	}

	public static function medical_key(): string {
		self::ensure_keys();
		if ( defined( 'ELM_ENCRYPTION_KEY' ) && is_string( ELM_ENCRYPTION_KEY ) && '' !== ELM_ENCRYPTION_KEY ) {
			return hash( 'sha256', ELM_ENCRYPTION_KEY, true );
		}
		$key = self::read_secret_option( self::MEDICAL_KEY_OPTION );
		return 32 === strlen( $key ) ? $key : hash( 'sha256', $key, true );
	}

	public static function legacy_medical_key(): string {
		self::ensure_keys();
		$key = self::read_secret_option( self::MEDICAL_LEGACY_OPTION );
		return 32 === strlen( $key ) ? $key : self::legacy_medical_key_from_wp_salts();
	}

	public static function audit_key_for_new_entry(): string {
		self::ensure_keys();
		if ( defined( 'ELM_AUDIT_KEY' ) && is_string( ELM_AUDIT_KEY ) && '' !== ELM_AUDIT_KEY ) {
			return hash( 'sha256', ELM_AUDIT_KEY, true );
		}
		$key = self::read_secret_option( self::AUDIT_KEY_OPTION );
		return '' !== $key ? $key : wp_salt( 'auth' );
	}

	public static function audit_key_for_entry( int $entry_id ): string {
		self::ensure_keys();
		$boundary = (int) get_option( self::AUDIT_V2_START_ID_OPTION, 0 );
		if ( $entry_id > 0 && $entry_id <= $boundary ) {
			$legacy = self::read_secret_option( self::AUDIT_LEGACY_OPTION );
			return '' !== $legacy ? $legacy : wp_salt( 'auth' );
		}
		return self::audit_key_for_new_entry();
	}

	public static function audit_lock_name(): string {
		global $wpdb;
		return 'elm_audit_' . substr( hash( 'sha256', (string) $wpdb->prefix . DB_NAME ), 0, 32 );
	}

	private static function legacy_medical_key_from_wp_salts(): string {
		return hash( 'sha256', wp_salt( 'secure_auth' ) . wp_salt( 'nonce' ), true );
	}

	private static function add_secret_option( string $name, string $raw ): void {
		add_option( $name, base64_encode( $raw ), '', false );
	}

	private static function read_secret_option( string $name ): string {
		$value = get_option( $name, '' );
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}
		$decoded = base64_decode( $value, true );
		return false === $decoded ? '' : $decoded;
	}
}
