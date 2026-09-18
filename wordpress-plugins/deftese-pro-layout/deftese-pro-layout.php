<?php
/**
 * Plugin Name:       Dëftesë PRO — Layout & Margin Manager
 * Plugin URI:        https://example.org/deftese-pro
 * Description:       Visual control over the A4 certificate: margins, row sizes, column widths, section spacing, typography, borders, colours and the background watermark.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Professional Studio
 * Text Domain:       deftese-pro-layout
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package DefteseProLayout
 */

declare( strict_types = 1 );

namespace DefteseProLayout;

defined( 'ABSPATH' ) || exit;

define( 'DEFTESE_LAYOUT_VERSION', '2.0.0' );
define( 'DEFTESE_LAYOUT_DIR', plugin_dir_path( __FILE__ ) );
define( 'DEFTESE_LAYOUT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Menu slug this screen attaches to, owned by the main plugin.
 */
const PARENT_SLUG = 'deftese-manager';

/**
 * This screen's own slug.
 */
const PAGE_SLUG = 'deftese-layout';

require_once DEFTESE_LAYOUT_DIR . 'includes/class-settings.php';
require_once DEFTESE_LAYOUT_DIR . 'includes/class-screen.php';

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'deftese-pro-layout', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		Screen::register();
	}
);
