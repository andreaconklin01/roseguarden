<?php
/**
 * The `[deftese_manager]` front-end application.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the shortcode-driven manager.
 */
final class Frontend {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_shortcode( 'deftese_manager', array( self::class, 'shortcode' ) );
	}

	/**
	 * Render the manager, the login form or the two-factor challenge.
	 *
	 * @return string Markup.
	 */
	public static function shortcode(): string {
		if ( ! is_user_logged_in() ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only status flags.
			$status = isset( $_GET['deftese_login'] ) ? sanitize_text_field( wp_unslash( $_GET['deftese_login'] ) ) : '';
			$token  = isset( $_GET['deftese_2fa_token'] ) ? sanitize_text_field( wp_unslash( $_GET['deftese_2fa_token'] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( '2fa' === $status && '' !== $token ) {
				return Auth::second_factor_form( $token );
			}

			return Auth::login_form();
		}

		if ( ! Access::can_manage() ) {
			wp_logout();

			return Auth::login_form();
		}

		Assets::enqueue_app();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view selector.
		$view = isset( $_GET['d_view'] ) ? sanitize_key( wp_unslash( $_GET['d_view'] ) ) : 'list';
		$id   = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $view, array( 'list', 'edit', 'security' ), true ) ) {
			$view = 'list';
		}

		$list_url = remove_query_arg(
			array( 'd_view', 'id', 'action', '_wpnonce', 'deftese_msg', 'd_orderby', 'd_order', 'd_paged', 'd_search', 'view_user_id' )
		);

		return view_to_string(
			'manager',
			array(
				'view'         => $view,
				'cert_id'      => $id,
				'current_user' => wp_get_current_user(),
				'logout_url'   => wp_logout_url( Access::manager_url() ),
				'list_url'     => $list_url,
				'edit_url'     => add_query_arg( 'd_view', 'edit', $list_url ),
				'security_url' => add_query_arg( 'd_view', 'security', $list_url ),
			)
		);
	}

	/**
	 * Render the two-factor settings panel.
	 */
	public static function render_security(): void {
		if ( ! Access::can_manage() ) {
			self::render_denied( true );

			return;
		}

		$user_id = get_current_user_id();

		Assets::enqueue_twofactor(
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Ajax::TWOFACTOR_NONCE ),
				'i18n'    => array(
					'preparing' => __( 'Duke përgatitur…', 'deftese-pro' ),
					'verifying' => __( 'Duke verifikuar…', 'deftese-pro' ),
					'enabled'   => __( '2FA u aktivizua me sukses!', 'deftese-pro' ),
					'disabled'  => __( '2FA u çaktivizua.', 'deftese-pro' ),
					'error'     => __( 'Gabim.', 'deftese-pro' ),
					'badCode'   => __( 'Kodi është i pasaktë.', 'deftese-pro' ),
				),
			)
		);

		view(
			'security',
			array(
				'enabled' => Totp::is_enabled( $user_id ),
			)
		);
	}

	/**
	 * Render an access-denied state.
	 *
	 * On the front end an unauthorised session is ended and the login form is
	 * shown; in wp-admin a notice is printed instead.
	 *
	 * @param bool $is_frontend Whether this is the shortcode context.
	 */
	public static function render_denied( bool $is_frontend ): void {
		if ( $is_frontend ) {
			wp_logout();

			echo wp_kses_post( Auth::login_form() );

			return;
		}

		view(
			'notice',
			array(
				'tone'    => 'danger',
				'message' => __( 'Ju nuk keni akses.', 'deftese-pro' ),
			)
		);
	}
}
