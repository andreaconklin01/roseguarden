<?php
/**
 * The layout settings screen.
 *
 * @package DefteseProLayout
 */

declare( strict_types = 1 );

namespace DefteseProLayout;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the submenu, its assets and its form handling.
 */
final class Screen {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/**
	 * Add the submenu under the certificate manager.
	 */
	public static function menu(): void {
		add_submenu_page(
			PARENT_SLUG,
			__( 'Dizajni i Fletës', 'deftese-pro-layout' ),
			__( 'Dizajni i Fletës', 'deftese-pro-layout' ),
			Settings::capability(),
			PAGE_SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Load the media library, colour picker and this screen's own assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( string $hook ): void {
		if ( false === strpos( $hook, PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style(
			'deftese-layout-admin',
			DEFTESE_LAYOUT_URL . 'assets/css/admin.css',
			array( 'wp-color-picker' ),
			DEFTESE_LAYOUT_VERSION
		);

		wp_enqueue_script(
			'deftese-layout-admin',
			DEFTESE_LAYOUT_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			DEFTESE_LAYOUT_VERSION,
			true
		);

		wp_localize_script(
			'deftese-layout-admin',
			'defteseLayout',
			array(
				'i18n' => array(
					'mediaTitle'  => __( 'Zgjidh Logon / Sfondin', 'deftese-pro-layout' ),
					'mediaButton' => __( 'Përdor këtë imazh', 'deftese-pro-layout' ),
					'overflow'    => __( 'Kujdes: Shuma e Kolonës 1 dhe 6 është 100% ose më shumë. Nuk ka vend për kolonat e tjera!', 'deftese-pro-layout' ),
				),
			)
		);
	}

	/**
	 * Handle the form and render the screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( Settings::capability() ) ) {
			wp_die( esc_html__( 'Ju nuk keni akses për të parë këtë faqe.', 'deftese-pro-layout' ), 403 );
		}

		$saved = false;

		if ( isset( $_POST['deftese_save_layout'] ) ) {
			check_admin_referer( 'deftese_layout_nonce' );

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Every member is validated by Settings::save().
			Settings::save( wp_unslash( $_POST ) );

			$saved = true;
		}

		$layout = Settings::get();

		require DEFTESE_LAYOUT_DIR . 'includes/views/settings-page.php';
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
