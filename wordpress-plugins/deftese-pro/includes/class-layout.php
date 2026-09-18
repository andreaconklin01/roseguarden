<?php
/**
 * Reads the A4 layout settings and turns them into CSS custom properties.
 *
 * The settings themselves are written by the companion "Layout & Margin
 * Manager" plugin. This class owns the schema so both plugins agree on the
 * defaults, the units and the sanitising rules.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Layout settings schema and CSS variable emitter.
 */
final class Layout {

	/**
	 * Option name holding the saved layout.
	 */
	public const OPTION = 'deftese_layout_settings';

	/**
	 * Schema: setting => [default, type, css variable, unit].
	 *
	 * `type` is one of `float`, `int`, `color`, `url`.
	 *
	 * @return array<string,array{0:mixed,1:string,2:?string,3:string}> Schema.
	 */
	public static function schema(): array {
		return array(
			'margin_top'      => array( 5, 'float', '--dp-pad-top', 'mm' ),
			'margin_right'    => array( 17, 'float', '--dp-pad-right', 'mm' ),
			'margin_bottom'   => array( 5, 'float', '--dp-pad-bottom', 'mm' ),
			'margin_left'     => array( 5, 'float', '--dp-pad-left', 'mm' ),

			'row_height'      => array( 14, 'int', '--dp-row-height', 'px' ),
			'cell_pad_y'      => array( 2, 'float', '--dp-cell-pad-y', 'px' ),

			'col1'            => array( 32, 'float', '--dp-col1', '%' ),
			'col2'            => array( 15, 'float', '--dp-col2', '%' ),
			'col3'            => array( 15, 'float', '--dp-col3', '%' ),
			'col4'            => array( 15, 'float', '--dp-col4', '%' ),
			'col5'            => array( 15, 'float', '--dp-col5', '%' ),
			'col6'            => array( 8, 'float', '--dp-col6', '%' ),

			'gap_top_1'       => array( 15, 'float', '--dp-gap-top-1', 'px' ),
			'gap_top_2'       => array( 5, 'float', '--dp-gap-top-2', 'px' ),
			'gap_top_3'       => array( 15, 'float', '--dp-gap-top-3', 'px' ),
			'gap_top_4'       => array( 15, 'float', '--dp-gap-top-4', 'px' ),
			'gap_bottom_1'    => array( 15, 'float', '--dp-gap-bottom-1', 'px' ),
			'gap_bottom_2'    => array( 15, 'float', '--dp-gap-bottom-2', 'px' ),
			'gap_bottom_3'    => array( 0, 'float', '--dp-gap-bottom-3', 'px' ),

			'gap_hf_elements' => array( 15, 'float', '--dp-gap-hf', 'px' ),
			'decor_gap'       => array( 20, 'int', '--dp-decor-gap', 'px' ),

			'fs_title'        => array( 34, 'float', '--dp-fs-title', 'px' ),
			'fs_table'        => array( 10, 'float', '--dp-fs-table', 'px' ),

			'color_main'      => array( '#cc0000', 'color', '--dp-color-main', '' ),
			'bg_color'        => array( '#ffffff', 'color', '--dp-bg-color', '' ),

			'border_out'      => array( 2, 'float', '--dp-border-out', 'px' ),
			'border_in'       => array( 1, 'float', '--dp-border-in', 'px' ),

			'logo_url'        => array( '', 'url', null, '' ),
			'logo_size'       => array( 50, 'float', '--dp-logo-size', '%' ),
			'logo_x'          => array( 50, 'float', '--dp-logo-x', '%' ),
			'logo_y'          => array( 50, 'float', '--dp-logo-y', '%' ),
			'logo_opacity'    => array( 10, 'float', null, '' ),
		);
	}

	/**
	 * Settings renamed between 6.x releases, mapped old => new.
	 *
	 * @return array<string,string> Legacy key => current key.
	 */
	private static function aliases(): array {
		return array(
			'gap_title_top'    => 'gap_top_1',
			'gap_table_top'    => 'gap_top_4',
			'gap_table_bottom' => 'gap_bottom_1',
		);
	}

	/**
	 * Default value for every setting.
	 *
	 * @return array<string,mixed> Defaults.
	 */
	public static function defaults(): array {
		$defaults = array();

		foreach ( self::schema() as $key => $spec ) {
			$defaults[ $key ] = $spec[0];
		}

		return $defaults;
	}

	/**
	 * Saved settings, merged over the defaults and re-validated on read.
	 *
	 * Re-validating here means a value written by an older release — or by hand
	 * in the database — can never reach the stylesheet unchecked.
	 *
	 * @return array<string,mixed> Settings.
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		foreach ( self::aliases() as $old => $new ) {
			if ( ! isset( $stored[ $new ] ) && isset( $stored[ $old ] ) ) {
				$stored[ $new ] = $stored[ $old ];
			}
		}

		return self::sanitize( $stored );
	}

	/**
	 * Coerce a raw settings array to the schema.
	 *
	 * @param array<string,mixed> $input Raw values.
	 * @return array<string,mixed> Sanitised values, with defaults filled in.
	 */
	public static function sanitize( array $input ): array {
		$clean = array();

		foreach ( self::schema() as $key => $spec ) {
			list( $default, $type ) = $spec;
			$value                  = $input[ $key ] ?? null;

			switch ( $type ) {
				case 'int':
					$clean[ $key ] = ( null === $value || '' === $value ) ? (int) $default : absint( $value );
					break;

				case 'color':
					$color         = sanitize_hex_color( is_string( $value ) ? $value : '' );
					// sanitize_hex_color() returns null for anything malformed,
					// which the legacy code wrote straight into the stylesheet
					// and produced an empty CSS declaration.
					$clean[ $key ] = $color ? $color : (string) $default;
					break;

				case 'url':
					$clean[ $key ] = is_string( $value ) ? esc_url_raw( $value ) : (string) $default;
					break;

				case 'float':
				default:
					$clean[ $key ] = ( null === $value || '' === $value ) ? (float) $default : (float) $value;
					break;
			}
		}

		return $clean;
	}

	/**
	 * Validate a submitted settings form and persist it.
	 *
	 * @param array<string,mixed> $input Raw form values.
	 * @return array<string,mixed> The values that were stored.
	 */
	public static function save( array $input ): array {
		$clean = self::sanitize( $input );

		update_option( self::OPTION, $clean );

		return $clean;
	}

	/**
	 * Capability required to change the printed layout.
	 *
	 * Kept at the manager capability to match the 6.x screen, so existing roles
	 * keep the access they have today. Note that this is site-wide print
	 * configuration: anyone who can edit a certificate can change the margins of
	 * every certificate the site produces. Tightening it is a one-liner:
	 *
	 *     add_filter( 'deftese_layout_capability', fn() => 'manage_options' );
	 *
	 * @return string Capability name.
	 */
	public static function capability(): string {
		/**
		 * Filters the capability required to edit the certificate layout.
		 *
		 * @param string $capability Default: the manager capability (`edit_posts`).
		 */
		return (string) apply_filters( 'deftese_layout_capability', Access::capability() );
	}

	/**
	 * Build the `:root` custom-property block for the certificate sheet.
	 *
	 * @return string CSS declarations, already escaped for inline output.
	 */
	public static function css_variables(): string {
		$settings = self::get();
		$lines    = array();

		foreach ( self::schema() as $key => $spec ) {
			$variable = $spec[2];

			if ( null === $variable ) {
				continue;
			}

			$unit  = $spec[3];
			$value = $settings[ $key ];

			if ( 'color' === $spec[1] ) {
				$lines[] = $variable . ':' . $value . ';';
				continue;
			}

			$lines[] = $variable . ':' . self::number( (float) $value ) . $unit . ';';
		}

		$logo    = (string) $settings['logo_url'];
		$lines[] = '--dp-logo-url:' . ( '' !== $logo ? 'url("' . esc_url( $logo ) . '")' : 'none' ) . ';';
		$lines[] = '--dp-logo-opacity:' . self::number( (float) $settings['logo_opacity'] / 100 ) . ';';

		return ':root{' . implode( '', $lines ) . '}';
	}

	/**
	 * Format a number for CSS without a trailing ".0".
	 *
	 * @param float $value Number.
	 * @return string Formatted number.
	 */
	private static function number( float $value ): string {
		$formatted = rtrim( rtrim( number_format( $value, 4, '.', '' ), '0' ), '.' );

		return '' === $formatted ? '0' : $formatted;
	}
}
