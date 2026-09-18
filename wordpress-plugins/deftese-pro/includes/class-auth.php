<?php
/**
 * Front-end login, rate limiting and the two-factor challenge.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Handles authentication that happens outside wp-login.php.
 */
final class Auth {

	/**
	 * Failed attempts tolerated per IP before the lockout kicks in.
	 */
	public const MAX_ATTEMPTS = 5;

	/**
	 * Lockout duration in seconds.
	 */
	public const LOCKOUT = 15 * MINUTE_IN_SECONDS;

	/**
	 * How long a half-finished 2FA login stays resumable.
	 */
	public const PENDING_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'handle_request' ) );
	}

	/**
	 * Transient key for the current client's failed-attempt counter.
	 *
	 * @return string Transient key.
	 */
	private static function rate_limit_key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';

		return 'deftese_login_attempts_' . md5( $ip );
	}

	/**
	 * Whether the current client is locked out.
	 *
	 * @return bool True when locked out.
	 */
	public static function is_rate_limited(): bool {
		return (int) get_transient( self::rate_limit_key() ) >= self::MAX_ATTEMPTS;
	}

	/**
	 * Record a failed attempt.
	 */
	private static function register_failure(): void {
		$key = self::rate_limit_key();

		set_transient( $key, (int) get_transient( $key ) + 1, self::LOCKOUT );
	}

	/**
	 * Clear the failure counter after a successful login.
	 */
	private static function clear_failures(): void {
		delete_transient( self::rate_limit_key() );
	}

	/**
	 * Dispatch the posted authentication step, if any.
	 */
	public static function handle_request(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		if ( isset( $_POST['deftese_2fa_nonce'] ) ) {
			self::handle_second_factor();

			return;
		}

		if ( isset( $_POST['deftese_login_nonce'] ) ) {
			self::handle_password_step();
		}
	}

	/**
	 * Verify username and password, then either sign in or start the 2FA step.
	 */
	private static function handle_password_step(): void {
		$nonce = isset( $_POST['deftese_login_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['deftese_login_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'deftese_frontend_login' ) ) {
			return;
		}

		$redirect = isset( $_POST['deftese_redirect'] )
			? esc_url_raw( wp_unslash( $_POST['deftese_redirect'] ) )
			: Access::manager_url();

		$redirect = wp_validate_redirect( $redirect, Access::manager_url() );

		if ( self::is_rate_limited() ) {
			self::bail( add_query_arg( 'deftese_login', 'locked', $redirect ) );
		}

		$username = isset( $_POST['deftese_username'] ) ? sanitize_user( wp_unslash( $_POST['deftese_username'] ), false ) : '';
		// The password is deliberately not sanitised: it is verified, never stored or echoed.
		$password = isset( $_POST['deftese_password'] ) ? (string) wp_unslash( $_POST['deftese_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$remember = ! empty( $_POST['deftese_remember'] );

		if ( '' === $username || '' === $password ) {
			self::bail( add_query_arg( 'deftese_login', 'failed', $redirect ) );
		}

		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			self::register_failure();
			self::bail( add_query_arg( 'deftese_login', 'failed', $redirect ) );
		}

		if ( Totp::is_enabled( (int) $user->ID ) ) {
			$token = wp_generate_password( 32, false, false );

			set_transient(
				'deftese_2fa_pending_' . $token,
				array(
					'user_id'  => (int) $user->ID,
					'remember' => $remember,
					'redirect' => $redirect,
				),
				self::PENDING_TTL
			);

			self::bail(
				add_query_arg(
					array(
						'deftese_login'     => '2fa',
						'deftese_2fa_token' => $token,
					),
					$redirect
				)
			);
		}

		self::complete_login( (int) $user->ID, $remember, $redirect );
	}

	/**
	 * Verify the one-time code and finish a pending login.
	 */
	private static function handle_second_factor(): void {
		$nonce = isset( $_POST['deftese_2fa_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['deftese_2fa_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'deftese_2fa_verify' ) ) {
			return;
		}

		$token   = isset( $_POST['deftese_2fa_token'] ) ? sanitize_text_field( wp_unslash( $_POST['deftese_2fa_token'] ) ) : '';
		$pending = '' !== $token ? get_transient( 'deftese_2fa_pending_' . $token ) : false;

		if ( ! is_array( $pending ) || empty( $pending['user_id'] ) ) {
			self::bail( Access::manager_url() );
		}

		$redirect = wp_validate_redirect( (string) ( $pending['redirect'] ?? '' ), Access::manager_url() );

		if ( self::is_rate_limited() ) {
			self::bail( add_query_arg( 'deftese_login', 'locked', $redirect ) );
		}

		$code   = isset( $_POST['deftese_2fa_code'] ) ? sanitize_text_field( wp_unslash( $_POST['deftese_2fa_code'] ) ) : '';
		$secret = (string) get_user_meta( (int) $pending['user_id'], Totp::META_SECRET, true );

		if ( ! Totp::verify( $secret, $code ) ) {
			self::register_failure();

			self::bail(
				add_query_arg(
					array(
						'deftese_login'     => '2fa',
						'deftese_2fa_token' => $token,
						'deftese_2fa_error' => '1',
					),
					$redirect
				)
			);
		}

		delete_transient( 'deftese_2fa_pending_' . $token );

		self::complete_login( (int) $pending['user_id'], ! empty( $pending['remember'] ), $redirect );
	}

	/**
	 * Set the auth cookie and redirect.
	 *
	 * Unlike the legacy flow this fires `wp_login`, so session handling,
	 * activity logs and security plugins see the sign-in.
	 *
	 * @param int    $user_id  Authenticated user.
	 * @param bool   $remember Whether to issue a long-lived cookie.
	 * @param string $redirect Destination after login.
	 */
	private static function complete_login( int $user_id, bool $remember, string $redirect ): void {
		self::clear_failures();

		wp_set_auth_cookie( $user_id, $remember, is_ssl() );
		wp_set_current_user( $user_id );

		$user = get_userdata( $user_id );

		if ( $user instanceof \WP_User ) {
			do_action( 'wp_login', $user->user_login, $user );
		}

		$destination = user_can( $user_id, 'manage_options' ) ? $redirect : Access::manager_url();

		self::bail( $destination );
	}

	/**
	 * Redirect and stop.
	 *
	 * @param string $url Destination.
	 */
	private static function bail( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render the branded login form.
	 *
	 * @return string Markup.
	 */
	public static function login_form(): string {
		Assets::enqueue_auth();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag.
		$status = isset( $_GET['deftese_login'] ) ? sanitize_text_field( wp_unslash( $_GET['deftese_login'] ) ) : '';

		return view_to_string(
			'login-form',
			array(
				'status'      => $status,
				'current_url' => esc_url_raw( remove_query_arg( array( 'deftese_login', 'deftese_2fa_token', 'deftese_2fa_error' ), home_url( add_query_arg( null, null ) ) ) ),
			)
		);
	}

	/**
	 * Render the one-time-code form.
	 *
	 * @param string $token Pending-login token.
	 * @return string Markup.
	 */
	public static function second_factor_form( string $token ): string {
		$pending = '' !== $token ? get_transient( 'deftese_2fa_pending_' . $token ) : false;

		if ( ! is_array( $pending ) ) {
			return self::login_form();
		}

		Assets::enqueue_auth();

		return view_to_string(
			'twofactor-form',
			array(
				'token'     => $token,
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag.
				'has_error' => isset( $_GET['deftese_2fa_error'] ),
			)
		);
	}
}
