<?php
/**
 * Plugin container.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's components onto WordPress.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Private constructor: use instance().
	 */
	private function __construct() {}

	/**
	 * Shared instance.
	 *
	 * @return Plugin Instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register every component exactly once.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		load_plugin_textdomain( 'deftese-pro', false, dirname( plugin_basename( DEFTESE_FILE ) ) . '/languages' );

		Schema::maybe_upgrade();

		Access::register();
		Auth::register();
		Assets::register();
		Ajax::register();
		Admin::register();
		Frontend::register();

		/**
		 * Fires once the plugin has registered its components.
		 */
		do_action( 'deftese_loaded' );
	}
}
