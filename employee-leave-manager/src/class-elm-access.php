<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_Access {
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'maybe_redirect_admin' ), 1 );
		add_filter( 'show_admin_bar', array( self::class, 'filter_admin_bar' ) );
		add_filter( 'login_redirect', array( self::class, 'filter_login_redirect' ), 20, 3 );
	}

	public static function enabled(): bool {
		$settings = ELM_Policy::settings();
		return ! empty( $settings['frontend_only_enabled'] ) && self::portal_url( $settings ) !== '';
	}

	public static function portal_page_id( ?array $settings = null ): int {
		$settings = $settings ?? ELM_Policy::settings();
		$page_id  = absint( $settings['portal_page_id'] ?? 0 );
		$page     = $page_id ? get_post( $page_id ) : null;
		if ( ! $page instanceof WP_Post || 'publish' !== $page->post_status ) {
			return 0;
		}
		$content = (string) $page->post_content;
		if ( ! has_shortcode( $content, 'elm_leave_portal' ) && ! has_shortcode( $content, 'employee_leave_manager' ) ) {
			return 0;
		}
		return $page_id;
	}

	public static function portal_url( ?array $settings = null ): string {
		$page_id = self::portal_page_id( $settings );
		if ( ! $page_id ) {
			return '';
		}
		$url = get_permalink( $page_id );
		return is_string( $url ) ? $url : '';
	}

	public static function maybe_redirect_admin(): void {
		if ( ! self::should_use_frontend() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		global $pagenow;
		if ( in_array( (string) $pagenow, array( 'admin-post.php', 'async-upload.php' ), true ) ) {
			return;
		}
		$url = self::portal_url();
		if ( '' === $url ) {
			return;
		}
		wp_safe_redirect( $url );
		exit;
	}

	public static function filter_admin_bar( bool $show ): bool {
		return self::should_use_frontend() ? false : $show;
	}

	public static function filter_login_redirect( string $redirect_to, string $requested_redirect_to, WP_User|WP_Error $user ): string {
		if ( is_wp_error( $user ) || ! $user instanceof WP_User || user_can( $user, 'manage_options' ) ) {
			return $redirect_to;
		}
		if ( ! self::user_has_portal_access( $user ) || ! self::enabled() ) {
			return $redirect_to;
		}
		$url = self::portal_url();
		return '' !== $url ? $url : $redirect_to;
	}

	private static function should_use_frontend(): bool {
		if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) || ! self::enabled() ) {
			return false;
		}
		$user = wp_get_current_user();
		return self::user_has_portal_access( $user );
	}

	public static function user_has_portal_access( WP_User $user ): bool {
		return user_can( $user, 'elm_submit_leave' )
			|| user_can( $user, 'elm_view_own_leave' )
			|| user_can( $user, 'elm_manage_leave' );
	}
}
