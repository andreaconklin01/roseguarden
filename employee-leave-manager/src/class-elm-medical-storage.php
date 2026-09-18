<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_Medical_Storage {
	private const LEGACY_SODIUM_HEADER = 'ELM1SODIUM';
	private const LEGACY_OPENSSL_HEADER = 'ELM1AESGCM';
	private const SODIUM_HEADER = 'ELM2SODIUM';
	private const OPENSSL_HEADER = 'ELM2AESGCM';

	public static function store_upload( string $field_name, int $owner_user_id, ?array $uploaded_file = null ): array|WP_Error {
		global $wpdb;
		if ( ! ELM_DB::schema_ready() ) {
			return new WP_Error( 'elm_database_unavailable', __( 'Baza e të dhënave të pushimeve nuk është e gatshme. Kontaktoni administratorin për ta riaktivizuar shtojcën.', 'employee-leave-manager' ), array( 'status' => 503 ) );
		}
		$file = $uploaded_file ?? ( $_FILES[ $field_name ] ?? null );
		if ( ! is_array( $file ) ) {
			return new WP_Error( 'elm_upload_missing', __( 'Nuk është ngarkuar dokument mjekësor.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'elm_upload_error', __( 'Ngarkimi i dokumentit mjekësor dështoi.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		$max_bytes = max( 1, (int) ELM_Policy::settings()['max_upload_mb'] ) * MB_IN_BYTES;
		if ( (int) $file['size'] <= 0 || (int) $file['size'] > $max_bytes ) {
			return new WP_Error( 'elm_upload_size', sprintf( __( 'Dokumenti mjekësor duhet të jetë më i vogël se %d MB.', 'employee-leave-manager' ), (int) ELM_Policy::settings()['max_upload_mb'] ), array( 'status' => 400 ) );
		}
		$name = sanitize_file_name( (string) $file['name'] );
		$allowed = array(
			'pdf'      => 'application/pdf',
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
		);
		$checked = wp_check_filetype_and_ext( (string) $file['tmp_name'], $name, $allowed );
		$detected_type = $checked['type'] ?? '';
		if ( class_exists( 'finfo' ) ) {
			$finfo = new finfo( FILEINFO_MIME_TYPE );
			$sniffed = $finfo->file( (string) $file['tmp_name'] );
			if ( is_string( $sniffed ) && '' !== $sniffed ) {
				$detected_type = $sniffed;
			}
		}
		if ( empty( $checked['type'] ) || ! in_array( $checked['type'], array_values( $allowed ), true ) || ! in_array( $detected_type, array_values( $allowed ), true ) ) {
			return new WP_Error( 'elm_upload_type', __( 'Lejohen vetëm skedarë PDF, JPG ose PNG.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		$content = file_get_contents( (string) $file['tmp_name'] );
		if ( false === $content ) {
			return new WP_Error( 'elm_upload_read', __( 'Leximi i dokumentit të ngarkuar dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}

		$encrypted = self::encrypt( $content );
		if ( is_wp_error( $encrypted ) ) {
			return $encrypted;
		}
		$directory = self::directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$storage_name = bin2hex( random_bytes( 24 ) ) . '.bin';
		$path = trailingslashit( $directory ) . $storage_name;
		if ( false === file_put_contents( $path, $encrypted['blob'], LOCK_EX ) ) {
			return new WP_Error( 'elm_upload_write', __( 'Ruajtja e dokumentit të enkriptuar dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}
		@chmod( $path, 0600 );

		$table = ELM_DB::table( 'medical_documents' );
		ELM_DB::begin();
		try {
			$ok = $wpdb->insert(
				$table,
				array(
					'owner_user_id' => $owner_user_id,
					'storage_name'  => $storage_name,
					'original_name' => $name,
					'mime_type'     => $checked['type'],
					'file_size'     => (int) $file['size'],
					'cipher'        => $encrypted['cipher'],
					'created_at'    => ELM_DB::now(),
				),
				array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
			if ( false === $ok ) {
				throw new RuntimeException( 'Document insert failed.' );
			}
			$id = (int) $wpdb->insert_id;
			$audit = ELM_Audit::append( 'medical_document', $id, 'uploaded', get_current_user_id(), array( 'owner_user_id' => $owner_user_id, 'original_name' => $name, 'mime_type' => $checked['type'], 'file_size' => (int) $file['size'] ) );
			if ( is_wp_error( $audit ) ) {
				throw new RuntimeException( $audit->get_error_message() );
			}
			ELM_DB::commit();
		} catch ( Throwable $e ) {
			ELM_DB::rollback();
			ELM_DB::log_failure( 'Registering medical document', $e );
			@unlink( $path );
			return new WP_Error( 'elm_upload_database', __( 'Regjistrimi i dokumentit mjekësor dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}

		return array( 'id' => $id, 'name' => $name, 'mime_type' => $checked['type'], 'size' => (int) $file['size'] );
	}

	public static function document_belongs_to( int $document_id, int $user_id ): bool {
		global $wpdb;
		$table = ELM_DB::table( 'medical_documents' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE id=%d AND owner_user_id=%d", $document_id, $user_id ) ) === 1; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function purge_if_unlinked( int $document_id, int $owner_user_id ): void {
		global $wpdb;
		$documents = ELM_DB::table( 'medical_documents' );
		$requests = ELM_DB::table( 'requests' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $documents WHERE id=%d AND owner_user_id=%d", $document_id, $owner_user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			return;
		}
		$linked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $requests WHERE medical_document_id=%d", $document_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $linked > 0 ) {
			return;
		}

		// Delete the database row (and record the audit entry) first, and only
		// remove the encrypted file once that transaction is confirmed committed.
		// Deleting the file first would leave an orphaned row pointing at a
		// missing file if the audit write or the row deletion then failed.
		ELM_DB::begin();
		try {
			$audit = ELM_Audit::append( 'medical_document', $document_id, 'discarded_unlinked', get_current_user_id(), array( 'owner_user_id' => $owner_user_id ) );
			if ( is_wp_error( $audit ) ) {
				throw new RuntimeException( $audit->get_error_message() );
			}
			$deleted = $wpdb->delete( $documents, array( 'id' => $document_id, 'owner_user_id' => $owner_user_id ), array( '%d', '%d' ) );
			if ( false === $deleted ) {
				throw new RuntimeException( 'Medical document deletion failed.' );
			}
			ELM_DB::commit();
		} catch ( Throwable $e ) {
			ELM_DB::rollback();
			ELM_DB::log_failure( 'Discarding unlinked medical document', $e );
			return;
		}

		$directory = self::directory();
		if ( ! is_wp_error( $directory ) ) {
			@unlink( trailingslashit( $directory ) . wp_basename( $row['storage_name'] ) );
		}
	}

	public static function retrieve( int $document_id ): array|WP_Error {
		global $wpdb;
		$table = ELM_DB::table( 'medical_documents' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", $document_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			return new WP_Error( 'elm_document_not_found', __( 'Dokumenti mjekësor nuk u gjet.', 'employee-leave-manager' ), array( 'status' => 404 ) );
		}
		$directory = self::directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$path = trailingslashit( $directory ) . wp_basename( $row['storage_name'] );
		$blob = file_get_contents( $path );
		if ( false === $blob ) {
			return new WP_Error( 'elm_document_read_failed', __( 'Ruajtja e dokumenteve mjekësore nuk është e disponueshme.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}
		$content = self::decrypt( $blob );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		return array(
			'id'            => (int) $row['id'],
			'owner_user_id' => (int) $row['owner_user_id'],
			'name'          => $row['original_name'],
			'mime_type'     => $row['mime_type'],
			'content'       => $content,
		);
	}

	private static function directory(): string|WP_Error {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'elm_upload_directory', $uploads['error'], array( 'status' => 500 ) );
		}
		$directory = trailingslashit( $uploads['basedir'] ) . 'elm-private';
		if ( ! wp_mkdir_p( $directory ) ) {
			return new WP_Error( 'elm_private_directory', __( 'Krijimi i dosjes private për dokumentet mjekësore dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}
		if ( ! file_exists( trailingslashit( $directory ) . '.htaccess' ) ) {
			file_put_contents( trailingslashit( $directory ) . '.htaccess', "Require all denied\nDeny from all\n" );
		}
		if ( ! file_exists( trailingslashit( $directory ) . 'web.config' ) ) {
			file_put_contents( trailingslashit( $directory ) . 'web.config', '<?xml version="1.0"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>' );
		}
		if ( ! file_exists( trailingslashit( $directory ) . 'index.php' ) ) {
			file_put_contents( trailingslashit( $directory ) . 'index.php', "<?php\nhttp_response_code(404);\n" );
		}
		return $directory;
	}

	private static function encrypt( string $plaintext ): array|WP_Error {
		$key = ELM_Security::medical_key();
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return array( 'cipher' => 'sodium_secretbox_v2', 'blob' => self::SODIUM_HEADER . $nonce . sodium_crypto_secretbox( $plaintext, $nonce, $key ) );
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv = random_bytes( 12 );
			$tag = '';
			$ciphertext = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( false !== $ciphertext ) {
				return array( 'cipher' => 'aes-256-gcm-v2', 'blob' => self::OPENSSL_HEADER . $iv . $tag . $ciphertext );
			}
		}
		return new WP_Error( 'elm_crypto_unavailable', __( 'Enkriptimi i sigurt i skedarëve nuk është i disponueshëm në këtë server.', 'employee-leave-manager' ), array( 'status' => 500 ) );
	}

	private static function decrypt( string $blob ): string|WP_Error {
		if ( str_starts_with( $blob, self::SODIUM_HEADER ) ) {
			return self::decrypt_sodium( $blob, self::SODIUM_HEADER, ELM_Security::medical_key() );
		}
		if ( str_starts_with( $blob, self::OPENSSL_HEADER ) ) {
			return self::decrypt_openssl( $blob, self::OPENSSL_HEADER, ELM_Security::medical_key() );
		}
		if ( str_starts_with( $blob, self::LEGACY_SODIUM_HEADER ) ) {
			return self::decrypt_sodium( $blob, self::LEGACY_SODIUM_HEADER, ELM_Security::legacy_medical_key() );
		}
		if ( str_starts_with( $blob, self::LEGACY_OPENSSL_HEADER ) ) {
			return self::decrypt_openssl( $blob, self::LEGACY_OPENSSL_HEADER, ELM_Security::legacy_medical_key() );
		}
		return new WP_Error( 'elm_cipher_unsupported', __( 'Ky server nuk e mbështet algoritmin e enkriptimit të dokumenteve.', 'employee-leave-manager' ), array( 'status' => 500 ) );
	}

	private static function decrypt_sodium( string $blob, string $header, string $key ): string|WP_Error {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return new WP_Error( 'elm_cipher_unsupported', __( 'Ky server nuk e mbështet algoritmin e enkriptimit të dokumenteve.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}
		$offset = strlen( $header );
		$nonce = substr( $blob, $offset, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = substr( $blob, $offset + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
		return false === $plain ? new WP_Error( 'elm_decryption_failed', __( 'Dekriptimi i dokumentit mjekësor dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) ) : $plain;
	}

	private static function decrypt_openssl( string $blob, string $header, string $key ): string|WP_Error {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return new WP_Error( 'elm_cipher_unsupported', __( 'Ky server nuk e mbështet algoritmin e enkriptimit të dokumenteve.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}
		$offset = strlen( $header );
		$iv = substr( $blob, $offset, 12 );
		$tag = substr( $blob, $offset + 12, 16 );
		$ciphertext = substr( $blob, $offset + 28 );
		$plain = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return false === $plain ? new WP_Error( 'elm_decryption_failed', __( 'Dekriptimi i dokumentit mjekësor dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) ) : $plain;
	}

}
