<?php
/**
 * Capability checks, ownership rules and the site-wide access gate.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Decides who may see and edit what.
 */
final class Access {

	/**
	 * Transient caching the resolved manager-page URL.
	 */
	public const URL_TRANSIENT = 'deftese_frontend_url';

	/**
	 * Capability required to use the certificate manager.
	 *
	 * @return string Capability name.
	 */
	public static function capability(): string {
		/**
		 * Filters the capability required to manage certificates.
		 *
		 * @param string $capability Default: `edit_posts`.
		 */
		return (string) apply_filters( 'deftese_capability', DEFTESE_CAP );
	}

	/**
	 * Whether the current user may use the manager at all.
	 *
	 * @return bool True when allowed.
	 */
	public static function can_manage(): bool {
		return is_user_logged_in() && current_user_can( self::capability() );
	}

	/**
	 * Whether the current user sees every certificate rather than only their own.
	 *
	 * @return bool True for administrators.
	 */
	public static function is_supervisor(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Owner id to scope queries to, or null when the viewer sees everything.
	 *
	 * @return int|null Owner user id.
	 */
	public static function query_owner(): ?int {
		return self::is_supervisor() ? null : get_current_user_id();
	}

	/**
	 * Whether the current user may read or write a given certificate row.
	 *
	 * @param array<string,mixed>|object|null $cert Certificate row or ownership record.
	 * @return bool True when the row belongs to the user or the user supervises.
	 */
	public static function owns( $cert ): bool {
		if ( self::is_supervisor() ) {
			return true;
		}

		if ( is_object( $cert ) ) {
			$cert = (array) $cert;
		}

		if ( ! is_array( $cert ) || ! isset( $cert['created_by'] ) ) {
			return false;
		}

		return (int) $cert['created_by'] === get_current_user_id();
	}

	/**
	 * A logged-in user without administrator rights.
	 *
	 * @return bool True for restricted editors.
	 */
	public static function is_restricted_user(): bool {
		return is_user_logged_in() && ! current_user_can( 'manage_options' );
	}

	/**
	 * Permalink of the page hosting the `[deftese_manager]` shortcode.
	 *
	 * @return string URL, falling back to the site home.
	 */
	public static function manager_url(): string {
		$cached = get_transient( self::URL_TRANSIENT );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$like = '%' . $wpdb->esc_like( '[deftese_manager' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
				$like
			)
		);

		if ( ! $post_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
					$like
				)
			);
		}

		$url = $post_id ? (string) get_permalink( (int) $post_id ) : home_url( '/' );

		set_transient( self::URL_TRANSIENT, $url, HOUR_IN_SECONDS );

		return $url;
	}

	/**
	 * Whether a manager page actually exists.
	 *
	 * Used to keep the redirect gate from looping when the shortcode has not
	 * been placed on any page yet.
	 *
	 * @return bool True when the resolved URL is a real manager page.
	 */
	public static function manager_page_exists(): bool {
		return untrailingslashit( self::manager_url() ) !== untrailingslashit( home_url( '/' ) );
	}

	/**
	 * Whether the request being rendered is the manager page itself.
	 *
	 * @return bool True on the manager page.
	 */
	public static function is_manager_request(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();

		return $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, 'deftese_manager' );
	}

	/**
	 * Drop the cached manager URL when content changes.
	 *
	 * The legacy plugin cached this for an hour with no invalidation, so moving
	 * the shortcode to a different page left every redirect pointing at the old
	 * one until the transient expired.
	 */
	public static function flush_url_cache(): void {
		delete_transient( self::URL_TRANSIENT );
	}

	/**
	 * Register the access-control hooks.
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'block_backend_for_editors' ) );
		add_action( 'login_init', array( self::class, 'redirect_wp_login' ) );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 3 );
		add_filter( 'logout_redirect', array( self::class, 'logout_redirect' ), 10, 3 );
		add_action( 'after_setup_theme', array( self::class, 'hide_admin_bar' ) );
		add_action( 'template_redirect', array( self::class, 'frontend_gate' ), 5 );
		add_action( 'template_redirect', array( self::class, 'protect_privacy' ) );

		add_action( 'save_post', array( self::class, 'flush_url_cache' ) );
		add_action( 'deleted_post', array( self::class, 'flush_url_cache' ) );
		add_action( 'switch_theme', array( self::class, 'flush_url_cache' ) );
	}

	/**
	 * Send non-administrators out of wp-admin and onto the manager page.
	 */
	public static function block_backend_for_editors(): void {
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! self::manager_page_exists() ) {
			return;
		}

		wp_safe_redirect( self::manager_url() );
		exit;
	}

	/**
	 * Route anonymous visits to wp-login.php to the branded front-end form.
	 *
	 * Only the plain login screen is intercepted: logout, password resets,
	 * registration and other `action` flows are left alone so nobody can be
	 * locked out of their own site.
	 */
	public static function redirect_wp_login(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		if ( isset( $_GET['action'] ) || isset( $_GET['key'] ) || isset( $_GET['checkemail'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) ) {
			return;
		}

		if ( ! self::manager_page_exists() ) {
			return;
		}

		wp_safe_redirect( self::manager_url() );
		exit;
	}

	/**
	 * Send non-administrators to the manager page after logging in.
	 *
	 * @param string           $redirect_to           Requested destination.
	 * @param string           $requested_redirect_to Raw requested destination.
	 * @param \WP_User|\WP_Error $user                Authenticated user.
	 * @return string Destination URL.
	 */
	public static function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( $user instanceof \WP_User && $user->ID && ! user_can( $user, 'manage_options' ) ) {
			return self::manager_url();
		}

		return $redirect_to;
	}

	/**
	 * Send everyone to the manager page after logging out.
	 *
	 * @param string $redirect_to           Requested destination.
	 * @param string $requested_redirect_to Raw requested destination.
	 * @param mixed  $user                  User that logged out.
	 * @return string Destination URL.
	 */
	public static function logout_redirect( $redirect_to, $requested_redirect_to, $user ) {
		unset( $redirect_to, $requested_redirect_to, $user );

		return self::manager_url();
	}

	/**
	 * Hide the admin bar for restricted editors.
	 */
	public static function hide_admin_bar(): void {
		if ( self::is_restricted_user() ) {
			show_admin_bar( false );
		}
	}

	/**
	 * Keep the public site closed: everything funnels to the manager page.
	 *
	 * This reproduces the original "private deployment" behaviour. The guards
	 * added here are what stop it turning into a redirect loop: the gate is
	 * skipped when no manager page exists, and when the request is already
	 * heading for the manager URL.
	 */
	public static function frontend_gate(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( self::is_manager_request() || current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! self::manager_page_exists() ) {
			return;
		}

		$target = self::manager_url();

		if ( self::is_current_url( $target ) ) {
			return;
		}

		/**
		 * Filters whether the site-wide front-end gate applies to this request.
		 *
		 * Returning false lets a request through, which is how a site can keep
		 * a public page (a privacy policy, say) reachable.
		 *
		 * @param bool   $gated  Whether to redirect.
		 * @param string $target Destination URL.
		 */
		if ( ! apply_filters( 'deftese_gate_frontend', true, $target ) ) {
			return;
		}

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Compare a URL against the current request path.
	 *
	 * @param string $url Candidate URL.
	 * @return bool True when it points at the request being served.
	 */
	private static function is_current_url( string $url ): bool {
		$request = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path    = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( '' === $request || '' === $path ) {
			return false;
		}

		return untrailingslashit( strtok( $request, '?' ) ) === untrailingslashit( $path );
	}

	/**
	 * Keep the manager page out of caches and search indexes.
	 */
	public static function protect_privacy(): void {
		if ( ! self::is_manager_request() ) {
			return;
		}

		nocache_headers();

		add_action(
			'wp_head',
			static function (): void {
				echo '<meta name="robots" content="noindex, nofollow, noarchive">' . "\n";
			},
			1
		);
	}
}
