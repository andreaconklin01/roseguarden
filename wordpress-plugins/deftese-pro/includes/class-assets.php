<?php
/**
 * Stylesheet and script registration.
 *
 * The legacy plugin shipped every rule as an inline `style` attribute and every
 * behaviour as a heredoc of JavaScript built inside a render function. Assets
 * are real files here, so they are cacheable, minifiable and reviewable.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and enqueues the plugin's front-end and admin assets.
 */
final class Assets {

	/**
	 * Handle prefix for every asset this plugin owns.
	 */
	private const PREFIX = 'deftese-pro-';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_all' ), 5 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'register_all' ), 5 );
	}

	/**
	 * Register (but do not enqueue) every asset.
	 */
	public static function register_all(): void {
		$css = DEFTESE_URL . 'assets/css/';
		$js  = DEFTESE_URL . 'assets/js/';

		wp_register_style( self::PREFIX . 'tokens', $css . 'tokens.css', array(), DEFTESE_VERSION );
		wp_register_style( self::PREFIX . 'app', $css . 'app.css', array( self::PREFIX . 'tokens' ), DEFTESE_VERSION );
		wp_register_style( self::PREFIX . 'auth', $css . 'auth.css', array( self::PREFIX . 'tokens' ), DEFTESE_VERSION );
		wp_register_style( self::PREFIX . 'certificate', $css . 'certificate.css', array( self::PREFIX . 'tokens' ), DEFTESE_VERSION );

		wp_register_script( self::PREFIX . 'list', $js . 'list.js', array(), DEFTESE_VERSION, true );
		wp_register_script( self::PREFIX . 'twofactor', $js . 'twofactor.js', array(), DEFTESE_VERSION, true );
		wp_register_script( self::PREFIX . 'csv', $js . 'csv.js', array(), DEFTESE_VERSION, true );
		wp_register_script( self::PREFIX . 'editor', $js . 'editor.js', array( self::PREFIX . 'csv' ), DEFTESE_VERSION, true );
	}

	/**
	 * Make sure registration has happened before an enqueue outside the normal hooks.
	 */
	private static function ensure_registered(): void {
		if ( ! wp_style_is( self::PREFIX . 'tokens', 'registered' ) ) {
			self::register_all();
		}
	}

	/**
	 * Enqueue the shared manager chrome.
	 */
	public static function enqueue_app(): void {
		self::ensure_registered();
		wp_enqueue_style( self::PREFIX . 'app' );
	}

	/**
	 * Enqueue the login and two-factor screens.
	 */
	public static function enqueue_auth(): void {
		self::ensure_registered();
		wp_enqueue_style( self::PREFIX . 'auth' );
	}

	/**
	 * Enqueue the list view and its bulk/import behaviour.
	 *
	 * @param array<string,mixed> $data Data passed to list.js.
	 */
	public static function enqueue_list( array $data ): void {
		self::ensure_registered();
		self::enqueue_app();

		wp_enqueue_script( self::PREFIX . 'list' );
		wp_localize_script( self::PREFIX . 'list', 'defteseList', $data );
	}

	/**
	 * Enqueue the two-factor settings behaviour.
	 *
	 * @param array<string,mixed> $data Data passed to twofactor.js.
	 */
	public static function enqueue_twofactor( array $data ): void {
		self::ensure_registered();
		self::enqueue_app();

		wp_enqueue_script( self::PREFIX . 'twofactor' );
		wp_localize_script( self::PREFIX . 'twofactor', 'deftese2fa', $data );
	}

	/**
	 * Enqueue the certificate editor.
	 *
	 * @param array<string,mixed> $data Data passed to editor.js.
	 */
	public static function enqueue_editor( array $data ): void {
		self::ensure_registered();
		self::enqueue_app();

		wp_enqueue_style( self::PREFIX . 'certificate' );
		wp_add_inline_style( self::PREFIX . 'certificate', Layout::css_variables() );

		wp_enqueue_script( self::PREFIX . 'editor' );
		wp_localize_script( self::PREFIX . 'editor', 'defteseEditor', $data );
	}
}
