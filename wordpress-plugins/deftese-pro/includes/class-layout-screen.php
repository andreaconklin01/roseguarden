<?php
/**
 * The A4 layout settings screen.
 *
 * This was a separate companion plugin in 6.x. It is built in now, so one
 * install provides the whole feature set — but it stands down automatically if
 * a standalone layout plugin is also active, so nobody ends up with the screen
 * registered twice.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the layout submenu, its assets and its form handling.
 */
final class Layout_Screen {

	/**
	 * Submenu slug, unchanged from the 6.x companion plugin.
	 */
	public const PAGE_SLUG = 'deftese-layout';

	/**
	 * Nonce action, unchanged so a stale form still validates.
	 */
	public const NONCE = 'deftese_layout_nonce';

	/**
	 * Whether a standalone layout plugin has already claimed this screen.
	 *
	 * Checks both the 6.x companion (a global function) and the 2.0 standalone
	 * rebuild (a constant), so upgrading in either order is safe.
	 *
	 * @return bool True when an external plugin owns the screen.
	 */
	public static function is_externally_provided(): bool {
		return defined( 'DEFTESE_LAYOUT_VERSION' ) || function_exists( 'deftese_layout_manager_menu' );
	}

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		if ( self::is_externally_provided() ) {
			return;
		}

		add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/**
	 * Add the submenu under the certificate manager.
	 */
	public static function menu(): void {
		add_submenu_page(
			Admin::MENU_SLUG,
			__( 'Dizajni i Fletës', 'deftese-pro' ),
			__( 'Dizajni i Fletës', 'deftese-pro' ),
			Layout::capability(),
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Load the media library, colour picker and this screen's own assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style(
			'deftese-pro-layout-admin',
			DEFTESE_URL . 'assets/css/layout-admin.css',
			array( 'wp-color-picker' ),
			DEFTESE_VERSION
		);

		wp_enqueue_script(
			'deftese-pro-layout-admin',
			DEFTESE_URL . 'assets/js/layout-admin.js',
			array( 'jquery', 'wp-color-picker' ),
			DEFTESE_VERSION,
			true
		);

		wp_localize_script(
			'deftese-pro-layout-admin',
			'defteseLayout',
			array(
				'i18n' => array(
					'mediaTitle'  => __( 'Zgjidh Logon / Sfondin', 'deftese-pro' ),
					'mediaButton' => __( 'Përdor këtë imazh', 'deftese-pro' ),
					'overflow'    => __( 'Kujdes: Shuma e Kolonës 1 dhe 6 është 100% ose më shumë. Nuk ka vend për kolonat e tjera!', 'deftese-pro' ),
				),
			)
		);
	}

	/**
	 * Handle the form and render the screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( Layout::capability() ) ) {
			wp_die( esc_html__( 'Ju nuk keni akses për të parë këtë faqe.', 'deftese-pro' ), 403 );
		}

		$saved = false;

		if ( isset( $_POST['deftese_save_layout'] ) ) {
			check_admin_referer( self::NONCE );

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Every member is validated by Layout::sanitize().
			Layout::save( wp_unslash( $_POST ) );

			$saved = true;
		}

		view(
			'layout-settings',
			array(
				'layout' => Layout::get(),
				'saved'  => $saved,
			)
		);
	}

	/**
	 * Render one numeric field row.
	 *
	 * @param string              $name   Field name.
	 * @param string              $label  Field label.
	 * @param array<string,mixed> $layout Current settings.
	 * @param string              $unit   Unit suffix.
	 * @param string              $step   Input step.
	 * @param string              $hint   Optional description.
	 */
	public static function number_row( string $name, string $label, array $layout, string $unit = 'px', string $step = '1', string $hint = '' ): void {
		?>
		<tr>
			<th scope="row"><label for="dpl-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="number" step="<?php echo esc_attr( $step ); ?>"
					id="dpl-<?php echo esc_attr( $name ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( (string) ( $layout[ $name ] ?? '' ) ); ?>"
					class="small-text">
				<span class="dpl-unit"><?php echo esc_html( $unit ); ?></span>
				<?php if ( '' !== $hint ) : ?>
					<p class="description"><?php echo esc_html( $hint ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
