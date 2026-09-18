<?php
/**
 * wp-admin screens.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the admin menu and renders its screens.
 */
final class Admin {

	/**
	 * Top-level menu slug. Referenced by the companion layout plugin.
	 */
	public const MENU_SLUG = 'deftese-manager';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
	}

	/**
	 * Build the menu.
	 */
	public static function menu(): void {
		$capability = Access::capability();

		add_menu_page(
			__( 'Dëftesë PRO', 'deftese-pro' ),
			__( 'Dëftesë PRO', 'deftese-pro' ),
			$capability,
			self::MENU_SLUG,
			array( self::class, 'render_list' ),
			'dashicons-media-document',
			30
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Të Gjitha Dëftesat', 'deftese-pro' ),
			__( 'Të Gjitha', 'deftese-pro' ),
			$capability,
			self::MENU_SLUG,
			array( self::class, 'render_list' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Shto Dëftesë të Re', 'deftese-pro' ),
			__( '+ Dëftesë e Re', 'deftese-pro' ),
			$capability,
			'deftese-new',
			array( self::class, 'render_editor' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Pasqyra Sipas Kategorive', 'deftese-pro' ),
			__( 'Sipas Kategorive', 'deftese-pro' ),
			'manage_options',
			'deftese-overview',
			array( Overview::class, 'render' )
		);
	}

	/**
	 * The "all certificates" screen.
	 */
	public static function render_list(): void {
		view(
			'admin-list',
			array(
				'new_url'      => admin_url( 'admin.php?page=deftese-new' ),
				'overview_url' => admin_url( 'admin.php?page=deftese-overview' ),
				'can_overview' => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * The add/edit screen.
	 */
	public static function render_editor(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only record selector.
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';

		view(
			'admin-editor',
			array(
				'cert_id'  => $id,
				'list_url' => admin_url( 'admin.php?page=' . self::MENU_SLUG ),
			)
		);
	}
}
