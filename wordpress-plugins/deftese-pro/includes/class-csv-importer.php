<?php
/**
 * Server-side CSV parsing and record import.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Turns an exported certificate spreadsheet into a database row.
 */
final class Csv_Importer {

	/**
	 * Largest accepted upload, per file.
	 */
	public const MAX_BYTES = 5 * MB_IN_BYTES;

	/**
	 * Most files accepted in a single request.
	 */
	public const MAX_FILES = 50;

	/**
	 * Repair mis-encoded bytes coming out of Excel exports.
	 *
	 * @param mixed $value Raw cell value.
	 * @return mixed UTF-8 string, or the input unchanged when not a string.
	 */
	public static function to_utf8( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		$value = str_replace(
			array( "\x93", "\x94", "\x91", "\x92" ),
			array( '"', '"', "'", "'" ),
			$value
		);

		if ( preg_match( '//u', $value ) ) {
			return $value;
		}

		if ( function_exists( 'mb_convert_encoding' ) ) {
			$converted = mb_convert_encoding( $value, 'UTF-8', 'Windows-1252' );

			if ( is_string( $converted ) && preg_match( '//u', $converted ) ) {
				return $converted;
			}
		}

		return strtr(
			$value,
			array(
				"\xeb" => 'ë',
				"\xcb" => 'Ë',
				"\xe7" => 'ç',
				"\xc7" => 'Ç',
			)
		);
	}

	/**
	 * Normalise a points value into the "(n)" form used on the certificate.
	 *
	 * @param string $value Raw cell value.
	 * @return string Formatted value.
	 */
	public static function format_points( string $value ): string {
		$value = trim( $value );

		if ( '' === $value || 'FALSE' === $value ) {
			return '';
		}

		if ( preg_match( '/^\(\s*(\d+(?:\.\d+)?)\s*\)$/i', $value, $matches )
			|| preg_match( '/^(\d+(?:\.\d+)?)$/i', $value, $matches ) ) {
			$number = $matches[1];

			return '(' . ( false !== strpos( $number, '.' ) ? number_format( (float) $number, 2, '.', '' ) : $number ) . ')';
		}

		return $value;
	}

	/**
	 * Repair Albanian diacritics mangled by the export.
	 *
	 * @param string $value Raw text.
	 * @return string Repaired text.
	 */
	public static function clean_text( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$value = (string) preg_replace( '/Shk[ëe.?]lqyesh[ëe.?]m/ui', 'Shkëlqyeshëm', $value );
		$value = (string) preg_replace( '/Shum[ëe.?]\s+mir[ëe.?]/ui', '@@SHUMEMIRE@@', $value );
		$value = (string) preg_replace( '/(?<![a-zA-ZëËçÇ])Mir[ëe.?](?![a-zA-ZëËçÇ])/ui', 'Mirë', $value );
		$value = (string) preg_replace( '/Mjaftuesh[ëe.?]m/ui', 'Mjaftueshëm', $value );
		$value = (string) preg_replace( '/Pamjaftuesh[ëe.?]m/ui', 'Pamjaftueshëm', $value );
		$value = (string) preg_replace( '/([A-Za-z])\?([A-Za-z]?)/u', '$1ë$2', $value );
		$value = str_replace( '@@SHUMEMIRE@@', 'Shumë mirë', $value );

		return trim( $value );
	}

	/**
	 * Read a CSV file into a padded grid of trimmed, UTF-8 cells.
	 *
	 * @param string $path Absolute path to a readable file.
	 * @return array<int,array<int,string>>|null Grid, or null when unreadable.
	 */
	public static function read_grid( string $path ): ?array {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $handle ) {
			return null;
		}

		// Skip the UTF-8 byte order mark when present.
		if ( "\xEF\xBB\xBF" !== fread( $handle, 3 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			rewind( $handle );
		}

		$grid = array();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) ) {
			$row    = array_map( array( self::class, 'to_utf8' ), $row );
			$grid[] = array_pad( array_map( 'trim', $row ), Csv_Map::MIN_COLUMNS, '' );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $grid;
	}

	/**
	 * Apply the spreadsheet map to a parsed grid.
	 *
	 * @param array<int,array<int,string>> $grid Parsed CSV grid.
	 * @return array<string,mixed> Certificate record, including `grades_json`.
	 */
	public static function map_grid( array $grid ): array {
		$cell = static function ( int $row, int $col ) use ( $grid ): string {
			return isset( $grid[ $row ][ $col ] ) ? trim( (string) $grid[ $row ][ $col ] ) : '';
		};

		$record = array();

		foreach ( Csv_Map::scalar_rules() as $field => $rule ) {
			$raw   = $cell( (int) $rule['row'], (int) $rule['col'] );
			$value = '';

			if ( isset( $rule['pattern'] ) ) {
				if ( preg_match( '/' . $rule['pattern'] . '/ui', $raw, $matches ) ) {
					$value = trim( $matches[1] );
				} elseif ( isset( $rule['strip'] ) ) {
					$value = trim( (string) preg_replace( '/' . $rule['strip'] . '/ui', '', $raw ) );
				}
			} elseif ( isset( $rule['strip'] ) ) {
				$value = trim( (string) preg_replace( '/' . $rule['strip'] . '/ui', '', $raw ) );
			} else {
				$value = $raw;
			}

			if ( ! empty( $rule['collapse'] ) ) {
				$value = (string) preg_replace( '/\s+/', '', $value );
			}

			if ( ! empty( $rule['clean'] ) ) {
				$value = self::clean_text( $value );
			}

			// A pattern that never matched and has no fallback leaves the field
			// untouched, matching the original importer's behaviour.
			if ( '' !== $value || ! isset( $rule['pattern'] ) || isset( $rule['strip'] ) ) {
				$record[ $field ] = $value;
			}
		}

		$registry = trim( (string) ( $record['registry_no'] ?? '' ) );
		$canonical = self::derive_id( $registry );

		// A registry number such as "12/2024" (or "12-2024") becomes the row's
		// primary key; anything else is used verbatim.
		$record['registry_no'] = $canonical;
		$record['id']          = '' !== $canonical ? $canonical : 'C-' . wp_generate_password( 6, false );

		$record['grades_json'] = self::map_grades( $cell );

		return $record;
	}

	/**
	 * Normalise a registry number into the canonical "n/m" certificate id.
	 *
	 * @param string $registry_no Raw registry number.
	 * @return string Canonical id, or the trimmed input when it has no pair.
	 */
	public static function derive_id( string $registry_no ): string {
		if ( preg_match( '/(\d+)\s*[\/\-\\\\]\s*(\d+)/', $registry_no, $matches ) ) {
			return $matches[1] . '/' . $matches[2];
		}

		return trim( $registry_no );
	}

	/**
	 * Build the grade sheet from the spreadsheet grid.
	 *
	 * @param callable $cell Accessor returning a trimmed cell value.
	 * @return array<int,array<string,mixed>> Grade rows.
	 */
	private static function map_grades( callable $cell ): array {
		$categories = Csv_Map::category_rows();
		$bolds      = Csv_Map::bold_rows();
		$columns    = Csv_Map::grade_columns();
		$grades     = array();

		for ( $row = Csv_Map::GRADE_FIRST_ROW; $row <= Csv_Map::GRADE_LAST_ROW; $row++ ) {
			$subject = self::clean_text( $cell( $row, $columns['subject'] ) );

			if ( '' === $subject ) {
				$subject = self::clean_text( $cell( $row, Csv_Map::SUBJECT_FALLBACK_COLUMN ) );
			}

			$is_category = in_array( $row, $categories, true );
			$is_bold     = in_array( $row, $bolds, true );

			$entry = array(
				'cat'     => $is_category,
				'bold'    => $is_bold,
				'subject' => $subject,
			);

			foreach ( Fields::grade_classes() as $class ) {
				$entry[ $class ]            = $is_category ? '' : self::clean_text( $cell( $row, $columns[ $class ] ) );
				$entry[ 'grade_' . $class ] = $is_category ? '' : self::format_points( $cell( $row, $columns[ 'grade_' . $class ] ) );
			}

			$test          = $cell( $row, $columns['test'] );
			$entry['test'] = ( $is_category || 'FALSE' === $test ) ? '' : $test;

			$grades[] = $entry;
		}

		return $grades;
	}

	/**
	 * Persist an imported record.
	 *
	 * @param array<string,mixed> $record    Mapped record.
	 * @param bool                $overwrite Whether an existing row may be replaced.
	 * @return bool True on success.
	 */
	public static function save( array $record, bool $overwrite ): bool {
		$data = array();

		foreach ( Fields::editable() as $field ) {
			$data[ $field ] = sanitize_text_field( (string) ( $record[ $field ] ?? '' ) );
		}

		$data['grades_json'] = wp_json_encode( $record['grades_json'] ?? array() );

		$existing = $overwrite ? Repository::find_owner( (string) $data['id'] ) : null;

		if ( $existing ) {
			if ( ! Access::owns( $existing ) ) {
				return false;
			}

			// The owner is preserved on update: importing over a record must not
			// quietly transfer it to whoever ran the import.
			return Repository::update( $existing['id'], $data );
		}

		$data['created_by']  = get_current_user_id();
		$data['stamp_text']  = '';
		$data['footer_note'] = Fields::DEFAULT_FOOTER_NOTE;

		return Repository::insert( $data );
	}

	/**
	 * Import a batch of uploaded files.
	 *
	 * @param array<string,mixed> $files     A `$_FILES` entry for a multi-file field.
	 * @param bool                $overwrite Whether existing rows may be replaced.
	 * @return array{imported:int,errors:int} Counts.
	 */
	public static function import_upload( array $files, bool $overwrite ): array {
		$count    = isset( $files['tmp_name'] ) && is_array( $files['tmp_name'] ) ? count( $files['tmp_name'] ) : 0;
		$count    = min( $count, self::MAX_FILES );
		$imported = 0;
		$errors   = 0;

		for ( $i = 0; $i < $count; $i++ ) {
			if ( self::import_one( $files, $i, $overwrite ) ) {
				++$imported;
			} else {
				++$errors;
			}
		}

		return array(
			'imported' => $imported,
			'errors'   => $errors,
		);
	}

	/**
	 * Validate and import a single uploaded file.
	 *
	 * @param array<string,mixed> $files     A `$_FILES` entry.
	 * @param int                 $index     Index within the upload.
	 * @param bool                $overwrite Whether existing rows may be replaced.
	 * @return bool True on success.
	 */
	private static function import_one( array $files, int $index, bool $overwrite ): bool {
		if ( ( $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK ) {
			return false;
		}

		$name = isset( $files['name'][ $index ] ) ? sanitize_file_name( (string) $files['name'][ $index ] ) : '';

		if ( 'csv' !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			return false;
		}

		if ( (int) ( $files['size'][ $index ] ?? 0 ) > self::MAX_BYTES ) {
			return false;
		}

		$tmp = (string) ( $files['tmp_name'][ $index ] ?? '' );

		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return false;
		}

		$filetype = wp_check_filetype( $name, array( 'csv' => 'text/csv' ) );

		if ( empty( $filetype['ext'] ) ) {
			return false;
		}

		$grid = self::read_grid( $tmp );

		if ( null === $grid ) {
			return false;
		}

		return self::save( self::map_grid( $grid ), $overwrite );
	}
}
