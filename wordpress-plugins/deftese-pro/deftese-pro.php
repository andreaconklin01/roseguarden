<?php
/**
 * Plugin Name:       Dëftesë PRO — Certificate Manager
 * Plugin URI:        https://example.org/deftese-pro
 * Description:       A4 school certificate manager with a responsive WYSIWYG editor, dynamic subject rows, CSV import, per-user row-level security, TOTP two-factor authentication and full control over the printed page layout.
 * Version:           7.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Professional Studio
 * Text Domain:       deftese-pro
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/*
 * Legacy-compatible constants.
 *
 * These names are part of the plugin's public surface: the companion layout
 * plugin and any site snippet may reference them, so they keep their original
 * spelling even though the rebuilt code uses class constants internally.
 */
define( 'DEFTESE_VERSION', '7.1.0' );
define( 'DEFTESE_TABLE', 'deftese_certificates' );
define( 'DEFTESE_CAP', 'edit_posts' );

define( 'DEFTESE_FILE', __FILE__ );
define( 'DEFTESE_DIR', plugin_dir_path( __FILE__ ) );
define( 'DEFTESE_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4-ish autoloader for the DeftesePro namespace.
 *
 * DeftesePro\Csv_Importer -> includes/class-csv-importer.php
 */
spl_autoload_register(
	static function ( string $class ): void {
		if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$relative = substr( $class, strlen( __NAMESPACE__ ) + 1 );
		$file     = DEFTESE_DIR . 'includes/class-' . str_replace( '_', '-', strtolower( $relative ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

require_once DEFTESE_DIR . 'includes/functions.php';

register_activation_hook( __FILE__, array( Schema::class, 'install' ) );

/**
 * Boot the plugin once all other plugins are loaded.
 */
add_action( 'plugins_loaded', static function (): void {
	Plugin::instance()->boot();
} );
