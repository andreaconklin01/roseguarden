<?php
/**
 * AJAX endpoints.
 *
 * Every handler follows the same three steps: verify the nonce, verify the
 * capability, then verify ownership of the row being touched.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up and implements the plugin's admin-ajax actions.
 */
final class Ajax {

	/**
	 * Nonce action guarding the editor endpoints.
	 */
	public const NONCE = 'deftese_nonce_v2';

	/**
	 * Nonce action guarding the importer.
	 */
	public const IMPORT_NONCE = 'deftese_import_action';

	/**
	 * Nonce action guarding the two-factor endpoints.
	 */
	public const TWOFACTOR_NONCE = 'deftese_2fa_manage';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		$actions = array(
			'deftese_save'         => 'save',
			'deftese_get_cert'     => 'get_certificate',
			'deftese_import_ajax'  => 'import',
			'deftese_2fa_setup'    => 'twofactor_setup',
			'deftese_2fa_confirm'  => 'twofactor_confirm',
			'deftese_2fa_disable'  => 'twofactor_disable',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( self::class, $method ) );
		}
	}

	/**
	 * Stop unless the caller holds the manager capability.
	 */
	private static function require_capability(): void {
		if ( ! Access::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Nuk keni leje.', 'deftese-pro' ) ), 403 );
		}
	}

	/**
	 * Create or update a certificate.
	 */
	public static function save(): void {
		check_ajax_referer( self::NONCE, 'security' );
		self::require_capability();

		$original_id = isset( $_POST['original_id'] ) ? sanitize_text_field( wp_unslash( $_POST['original_id'] ) ) : '';
		$is_new      = ( isset( $_POST['is_new'] ) && 'true' === $_POST['is_new'] ) || '' === $original_id;

		$data = array();

		foreach ( Fields::editable() as $field ) {
			$raw = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '';

			$data[ $field ] = 'footer_note' === $field
				? wp_kses_post( (string) $raw )
				: sanitize_text_field( (string) $raw );
		}

		if ( '' === trim( $data['registry_no'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Gabim: Numri i librit amë nuk mund të jetë bosh.', 'deftese-pro' ) ) );
		}

		$canonical           = Csv_Importer::derive_id( $data['registry_no'] );
		$data['registry_no'] = $canonical;
		$data['id']          = '' !== $canonical ? $canonical : 'C-' . wp_generate_password( 6, false );

		if ( '' === trim( (string) $data['footer_note'] ) ) {
			$data['footer_note'] = Fields::DEFAULT_FOOTER_NOTE;
		}

		$grades              = isset( $_POST['grades_json'] ) ? json_decode( (string) wp_unslash( $_POST['grades_json'] ), true ) : array();
		$data['grades_json'] = wp_json_encode( self::sanitize_grades( is_array( $grades ) ? $grades : array() ) );

		$target = Repository::find_owner( (string) $data['id'] );

		if ( $target && ! Access::owns( $target ) ) {
			wp_send_json_error( array( 'message' => __( 'Ky numër i librit amë ekziston dhe i përket një përdoruesi tjetër.', 'deftese-pro' ) ), 403 );
		}

		if ( ! $is_new && '' !== $original_id ) {
			$original = Repository::find_owner( $original_id );

			if ( $original && ! Access::owns( $original ) ) {
				wp_send_json_error( array( 'message' => __( 'Nuk keni leje për të ndryshuar këtë dëftesë.', 'deftese-pro' ) ), 403 );
			}

			$saved   = Repository::update( $original_id, $data );
			$id      = (string) $data['id'];
			/* translators: %s: registry number. */
			$message = sprintf( __( 'Dëftesa u përditësua! (Libri Amë: %s)', 'deftese-pro' ), $id );
		} elseif ( $target ) {
			$saved   = Repository::update( $target['id'], $data );
			$id      = $target['id'];
			/* translators: %s: registry number. */
			$message = sprintf( __( 'Dëftesa ekzistuese u mbishkrua! (Libri Amë: %s)', 'deftese-pro' ), $id );
		} else {
			// created_by is only set when the row is created, so editing someone
			// else's certificate as an administrator no longer reassigns it.
			$data['created_by'] = get_current_user_id();

			$saved   = Repository::insert( $data );
			$id      = (string) $data['id'];
			/* translators: %s: registry number. */
			$message = sprintf( __( 'Dëftesa u ruajt si e re! (Libri Amë: %s)', 'deftese-pro' ), $id );
		}

		if ( ! $saved ) {
			wp_send_json_error( array( 'message' => __( 'Ruajtja dështoi. Provoni sërish.', 'deftese-pro' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => $message,
				'cert_id' => $id,
			)
		);
	}

	/**
	 * Load a certificate for the editor's previous/next navigation.
	 */
	public static function get_certificate(): void {
		check_ajax_referer( self::NONCE, 'security' );
		self::require_capability();

		$id   = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$cert = Repository::find( $id );

		if ( ! $cert ) {
			wp_send_json_error( array( 'message' => __( 'Dëftesa nuk u gjet.', 'deftese-pro' ) ), 404 );
		}

		if ( ! Access::owns( $cert ) ) {
			wp_send_json_error( array( 'message' => __( 'Nuk keni akses në këtë dëftesë.', 'deftese-pro' ) ), 403 );
		}

		$grades              = json_decode( (string) ( $cert['grades_json'] ?? '' ), true );
		$cert['grades_json'] = is_array( $grades ) && $grades ? $grades : Fields::default_grades();

		$neighbours = Repository::neighbours( $id, Access::query_owner() );

		wp_send_json_success(
			array(
				'cert'    => $cert,
				'prev_id' => $neighbours['prev'],
				'next_id' => $neighbours['next'],
			)
		);
	}

	/**
	 * Import a batch of uploaded CSV files.
	 */
	public static function import(): void {
		check_ajax_referer( self::IMPORT_NONCE, 'deftese_import_nonce' );
		self::require_capability();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Each member is validated in Csv_Importer.
		$files = isset( $_FILES['deftese_import_files'] ) ? (array) $_FILES['deftese_import_files'] : array();

		$result = Csv_Importer::import_upload( $files, ! empty( $_POST['overwrite'] ) );

		wp_send_json_success( $result );
	}

	/**
	 * Issue a pending two-factor secret.
	 */
	public static function twofactor_setup(): void {
		check_ajax_referer( self::TWOFACTOR_NONCE, 'security' );
		self::require_capability();

		$user   = wp_get_current_user();
		$secret = Totp::generate_secret();

		update_user_meta( $user->ID, Totp::META_PENDING, $secret );

		wp_send_json_success(
			array(
				'secret'  => $secret,
				'otpauth' => Totp::otpauth_uri( $secret, $user->user_login ),
			)
		);
	}

	/**
	 * Confirm and activate two-factor authentication.
	 */
	public static function twofactor_confirm(): void {
		check_ajax_referer( self::TWOFACTOR_NONCE, 'security' );
		self::require_capability();

		$user_id = get_current_user_id();
		$secret  = (string) get_user_meta( $user_id, Totp::META_PENDING, true );
		$code    = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( '' === $secret ) {
			wp_send_json_error( array( 'message' => __( 'Fillo procesin e aktivizimit sërish.', 'deftese-pro' ) ) );
		}

		if ( ! Totp::verify( $secret, $code ) ) {
			wp_send_json_error( array( 'message' => __( 'Kodi është i pasaktë.', 'deftese-pro' ) ) );
		}

		update_user_meta( $user_id, Totp::META_SECRET, $secret );
		update_user_meta( $user_id, Totp::META_ENABLED, '1' );
		delete_user_meta( $user_id, Totp::META_PENDING );

		wp_send_json_success( array( 'message' => __( 'U aktivizua.', 'deftese-pro' ) ) );
	}

	/**
	 * Turn two-factor authentication off, after proving possession of the device.
	 */
	public static function twofactor_disable(): void {
		check_ajax_referer( self::TWOFACTOR_NONCE, 'security' );
		self::require_capability();

		$user_id = get_current_user_id();
		$secret  = (string) get_user_meta( $user_id, Totp::META_SECRET, true );
		$code    = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( ! Totp::verify( $secret, $code ) ) {
			wp_send_json_error( array( 'message' => __( 'Kodi është i pasaktë.', 'deftese-pro' ) ) );
		}

		delete_user_meta( $user_id, Totp::META_SECRET );
		delete_user_meta( $user_id, Totp::META_ENABLED );
		delete_user_meta( $user_id, Totp::META_PENDING );

		wp_send_json_success( array( 'message' => __( 'U çaktivizua.', 'deftese-pro' ) ) );
	}

	/**
	 * Reduce a posted grade sheet to the keys the renderer understands.
	 *
	 * Anything else a client sends is dropped rather than round-tripped back
	 * into the page.
	 *
	 * @param array<int,mixed> $grades Raw posted rows.
	 * @return array<int,array<string,mixed>> Clean rows.
	 */
	private static function sanitize_grades( array $grades ): array {
		$clean = array();

		foreach ( $grades as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$entry = array(
				'cat'     => ! empty( $row['cat'] ),
				'bold'    => ! empty( $row['bold'] ),
				'subject' => sanitize_text_field( (string) ( $row['subject'] ?? '' ) ),
			);

			foreach ( Fields::grade_classes() as $class ) {
				$entry[ $class ]            = sanitize_text_field( (string) ( $row[ $class ] ?? '' ) );
				$entry[ 'grade_' . $class ] = sanitize_text_field( (string) ( $row[ 'grade_' . $class ] ?? '' ) );
			}

			$entry['test'] = sanitize_text_field( (string) ( $row['test'] ?? '' ) );

			$clean[] = $entry;
		}

		return $clean;
	}
}
