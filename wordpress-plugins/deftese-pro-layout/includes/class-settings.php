<?php
/**
 * Layout settings schema, sanitising and persistence.
 *
 * The schema lives in the main plugin (DeftesePro\Layout) so the writer and the
 * reader can never disagree about defaults or units. This class falls back to a
 * local copy only if the main plugin is missing, which keeps the screen usable
 * rather than fatal.
 *
 * @package DefteseProLayout
 */

declare( strict_types = 1 );

namespace DefteseProLayout;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the shared layout option.
 */
final class Settings {

	/**
	 * Option name, shared with the main plugin.
	 */
	public const OPTION = 'deftese_layout_settings';

	/**
	 * Whether the main plugin is active and exposing its schema.
	 *
	 * @return bool True when available.
	 */
	private static function has_core(): bool {
		return class_exists( '\\DeftesePro\\Layout' );
	}

	/**
	 * Default value for every setting.
	 *
	 * @return array<string,mixed> Defaults.
	 */
	public static function defaults(): array {
		if ( self::has_core() ) {
			return \DeftesePro\Layout::defaults();
		}

		return array(
			'margin_top'      => 5,
			'margin_right'    => 17,
			'margin_bottom'   => 5,
			'margin_left'     => 5,
			'row_height'      => 14,
			'cell_pad_y'      => 2,
			'col1'            => 32,
			'col2'            => 15,
			'col3'            => 15,
			'col4'            => 15,
			'col5'            => 15,
			'col6'            => 8,
			'gap_top_1'       => 15,
			'gap_top_2'       => 5,
			'gap_top_3'       => 15,
			'gap_top_4'       => 15,
			'gap_bottom_1'    => 15,
			'gap_bottom_2'    => 15,
			'gap_bottom_3'    => 0,
			'gap_hf_elements' => 15,
			'decor_gap'       => 20,
			'fs_title'        => 34,
			'fs_table'        => 10,
			'color_main'      => '#cc0000',
			'bg_color'        => '#ffffff',
			'border_out'      => 2,
			'border_in'       => 1,
			'logo_url'        => '',
			'logo_size'       => 50,
			'logo_x'          => 50,
			'logo_y'          => 50,
			'logo_opacity'    => 10,
		);
	}

	/**
	 * Current settings, merged over the defaults.
	 *
	 * @return array<string,mixed> Settings.
	 */
	public static function get(): array {
		if ( self::has_core() ) {
			return \DeftesePro\Layout::get();
		}

		$stored = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Validate a submitted form and persist it.
	 *
	 * @param array<string,mixed> $input Raw `$_POST` values.
	 * @return array<string,mixed> The values that were stored.
	 */
	public static function save( array $input ): array {
		$clean = self::has_core()
			? \DeftesePro\Layout::sanitize( $input )
			: self::sanitize_fallback( $input );

		update_option( self::OPTION, $clean );

		return $clean;
	}

	/**
	 * Minimal sanitiser used when the main plugin is not active.
	 *
	 * @param array<string,mixed> $input Raw values.
	 * @return array<string,mixed> Sanitised values.
	 */
	private static function sanitize_fallback( array $input ): array {
		$clean = array();

		foreach ( self::defaults() as $key => $default ) {
			$value = $input[ $key ] ?? null;

			if ( 'logo_url' === $key ) {
				$clean[ $key ] = is_string( $value ) ? esc_url_raw( $value ) : '';
				continue;
			}

			if ( is_string( $default ) && 0 === strpos( $default, '#' ) ) {
				$colour        = sanitize_hex_color( is_string( $value ) ? $value : '' );
				$clean[ $key ] = $colour ? $colour : $default;
				continue;
			}

			$clean[ $key ] = ( null === $value || '' === $value ) ? $default : (float) $value;
		}

		return $clean;
	}

	/**
	 * Capability required to change the printed layout.
	 *
	 * Kept at `edit_posts` to match the 6.x screen, so existing roles keep the
	 * access they have today. Note that this is site-wide print configuration:
	 * anyone who can edit a post can change the margins of every certificate the
	 * site produces. Tightening it is a one-liner:
	 *
	 *     add_filter( 'deftese_layout_capability', fn() => 'manage_options' );
	 *
	 * @return string Capability name.
	 */
	public static function capability(): string {
		/**
		 * Filters the capability required to edit the certificate layout.
		 *
		 * @param string $capability Default: `edit_posts`.
		 */
		return (string) apply_filters( 'deftese_layout_capability', 'edit_posts' );
	}
}
