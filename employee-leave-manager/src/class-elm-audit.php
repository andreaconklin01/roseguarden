<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_Audit {
	private static bool $lock_held = false;

	public static function append( string $entity_type, int $entity_id, string $action, int $actor_id, array $payload ): int|WP_Error {
		global $wpdb;
		ELM_Security::ensure_keys();

		$table = ELM_DB::table( 'audit' );
		$release_after_append = ! ELM_DB::in_transaction();
		if ( ! self::$lock_held ) {
			$lock_name = ELM_Security::audit_lock_name();
			$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 10 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( 1 !== $locked ) {
				return new WP_Error( 'elm_audit_lock_failed', __( 'Regjistri i auditimit është i zënë. Provoni përsëri.', 'employee-leave-manager' ), array( 'status' => 503 ) );
			}
			self::$lock_held = true;
		}

		try {
			// The named database lock serializes the read-hash-insert sequence even when
			// a caller did not start an explicit transaction.
			$last_hash = (string) $wpdb->get_var( "SELECT entry_hash FROM $table ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( '' === $last_hash ) {
				$last_hash = str_repeat( '0', 64 );
			}
			$created_at = ELM_DB::now();
			$normalized = array(
				'entity_type' => sanitize_key( $entity_type ),
				'entity_id'   => $entity_id,
				'action'      => sanitize_key( $action ),
				'actor_id'    => $actor_id,
				'payload'     => $payload,
				'created_at'  => $created_at,
				'prev_hash'   => $last_hash,
			);
			$json = wp_json_encode( $normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$entry_hash = hash_hmac( 'sha256', (string) $json, ELM_Security::audit_key_for_new_entry() );

			$ok = $wpdb->insert(
				$table,
				array(
					'entity_type' => $normalized['entity_type'],
					'entity_id'   => $entity_id,
					'action_name' => $normalized['action'],
					'actor_id'    => $actor_id,
					'payload_json'=> wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
					'prev_hash'   => $last_hash,
					'entry_hash'  => $entry_hash,
					'created_at'  => $created_at,
				),
				array( '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
			);
			if ( false === $ok ) {
				return new WP_Error( 'elm_audit_failed', __( 'Shkrimi në regjistrin e auditimit dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) );
			}
			return (int) $wpdb->insert_id;
		} finally {
			if ( $release_after_append ) {
				self::release_lock();
			}
		}
	}

	public static function release_lock(): void {
		if ( ! self::$lock_held ) {
			return;
		}
		global $wpdb;
		$lock_name = ELM_Security::audit_lock_name();
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		self::$lock_held = false;
	}

	public static function verify_chain(): array {
		global $wpdb;
		ELM_Security::ensure_keys();
		$table = ELM_DB::table( 'audit' );
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$previous = str_repeat( '0', 64 );
		foreach ( $rows as $row ) {
			$payload = json_decode( (string) $row->payload_json, true );
			$normalized = array(
				'entity_type' => (string) $row->entity_type,
				'entity_id'   => (int) $row->entity_id,
				'action'      => (string) $row->action_name,
				'actor_id'    => (int) $row->actor_id,
				'payload'     => is_array( $payload ) ? $payload : array(),
				'created_at'  => (string) $row->created_at,
				'prev_hash'   => (string) $row->prev_hash,
			);
			$expected = hash_hmac(
				'sha256',
				(string) wp_json_encode( $normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				ELM_Security::audit_key_for_entry( (int) $row->id )
			);
			if ( ! hash_equals( $previous, (string) $row->prev_hash ) || ! hash_equals( $expected, (string) $row->entry_hash ) ) {
				return array( 'valid' => false, 'broken_at' => (int) $row->id, 'entries' => count( $rows ) );
			}
			$previous = (string) $row->entry_hash;
		}
		return array( 'valid' => true, 'broken_at' => null, 'entries' => count( $rows ) );
	}
}
