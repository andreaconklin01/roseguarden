<?php
/**
 * Plugin Name: Menaxhimi i Pushimeve të Punonjësve
 * Plugin URI:  https://example.com/employee-leave-manager
 * Description: Menaxhimi i kërkesave për pushim, ditëve të pushimit vjetor, afateve, miratimeve, dokumenteve mjekësore dhe raporteve PDF, me terminologji të përshtatur për Rregulloren (QRK) Nr. 04/2024.
 * Version:     1.6.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author:      Custom Development
 * License:     GPL-2.0-or-later
 * Text Domain: employee-leave-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ELM_VERSION', '1.6.0' );
define( 'ELM_DB_VERSION', '1.6.0' );
define( 'ELM_FILE', __FILE__ );
define( 'ELM_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELM_URL', plugin_dir_url( __FILE__ ) );

$elm_autoload = ELM_DIR . 'vendor/autoload.php';
if ( file_exists( $elm_autoload ) ) {
	require_once $elm_autoload;
}

spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'ELM_' ) ) {
			return;
		}
		$file = ELM_DIR . 'src/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'ELM_Activator', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		ELM_Activator::maybe_upgrade();
		ELM_Plugin::instance()->boot();
	}
);
