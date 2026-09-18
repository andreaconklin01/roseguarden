<?php
/**
 * The coordinates and patterns that describe the source spreadsheet.
 *
 * The legacy plugin carried this mapping twice — once in PHP for the bulk
 * importer and once in JavaScript for the single-file importer — with the row
 * and column numbers written out by hand in both copies. They are declared once
 * here and handed to the browser as data, so the two importers cannot drift.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Describes where each certificate value sits in the exported CSV.
 */
final class Csv_Map {

	/**
	 * First spreadsheet row (zero-indexed) holding a subject.
	 */
	public const GRADE_FIRST_ROW = 25;

	/**
	 * Last spreadsheet row (zero-indexed) holding a subject.
	 */
	public const GRADE_LAST_ROW = 55;

	/**
	 * Minimum number of columns each parsed row is padded to.
	 */
	public const MIN_COLUMNS = 15;

	/**
	 * Rows that are red category headings rather than subjects.
	 *
	 * @return int[] Zero-indexed row numbers.
	 */
	public static function category_rows(): array {
		/**
		 * Filters which spreadsheet rows are treated as category headings.
		 *
		 * @param int[] $rows Zero-indexed row numbers.
		 */
		return (array) apply_filters( 'deftese_csv_category_rows', array( 25, 29, 32, 34, 38, 42, 44, 46, 53 ) );
	}

	/**
	 * Rows rendered in bold (the two summary rows).
	 *
	 * @return int[] Zero-indexed row numbers.
	 */
	public static function bold_rows(): array {
		/**
		 * Filters which spreadsheet rows render bold.
		 *
		 * @param int[] $rows Zero-indexed row numbers.
		 */
		return (array) apply_filters( 'deftese_csv_bold_rows', array( 54, 55 ) );
	}

	/**
	 * Column index for each grade-table field.
	 *
	 * @return array<string,int> Field => column index.
	 */
	public static function grade_columns(): array {
		return array(
			'subject'    => 1,
			'vi'         => 2,
			'grade_vi'   => 3,
			'vii'        => 4,
			'grade_vii'  => 5,
			'viii'       => 6,
			'grade_viii' => 7,
			'ix'         => 8,
			'grade_ix'   => 9,
			'test'       => 10,
		);
	}

	/**
	 * Column consulted when the subject column is empty.
	 */
	public const SUBJECT_FALLBACK_COLUMN = 0;

	/**
	 * Where each single-value field lives, and how to pull it out of the cell.
	 *
	 * Each entry has a `row`/`col` pair and an optional `pattern`. A pattern is
	 * a JavaScript-compatible regular expression whose first capture group is
	 * the value; `strip` removes a leading fragment when no pattern matches;
	 * `clean` runs the Albanian text repair; `collapse` removes whitespace.
	 *
	 * @return array<string,array<string,mixed>> Field => extraction rule.
	 */
	public static function scalar_rules(): array {
		return array(
			'school_name'   => array(
				'row'   => 13,
				'col'   => 1,
				'clean' => true,
			),
			'school_city'   => array(
				'row'   => 13,
				'col'   => 4,
				'strip' => '^n[ëe.?]\\s+',
				'clean' => true,
			),
			'registry_no'   => array(
				'row'     => 16,
				'col'     => 1,
				'pattern' => 'librit\\s*am[ëe.?]\\s*:\\s*(.+)',
				'strip'   => '.*?am[ëe.?]\\s*:\\s*',
			),
			'protocol_no'   => array(
				'row'     => 16,
				'col'     => 4,
				'pattern' => 'protokollit\\s*:\\s*([^\\s]+)',
				'strip'   => '.*?protokollit\\s*:\\s*',
			),
			'ministry_code' => array(
				'row' => 16,
				'col' => 8,
			),
			'student_name'  => array(
				'row'   => 20,
				'col'   => 1,
				'clean' => true,
			),
			'parent_name'   => array(
				'row'   => 20,
				'col'   => 7,
				'clean' => true,
			),
			'birth_date'    => array(
				'row'     => 22,
				'col'     => 1,
				'pattern' => 'lindur\\s+m[ëe.?]\\s*([\\d]{1,2}[\\.\\/\\-][\\d]{1,2}[\\.\\/\\-][\\d]{2,4})',
			),
			'birth_city'    => array(
				'row'     => 22,
				'col'     => 1,
				'pattern' => 'n[ëe.?]\\s+([^,]+)',
				'clean'   => true,
			),
			'birth_commune' => array(
				'row'     => 22,
				'col'     => 1,
				'pattern' => 'komuna\\s+([^,]+)',
				'clean'   => true,
			),
			'birth_state'   => array(
				'row'     => 22,
				'col'     => 1,
				'pattern' => 'shteti\\s+(.+?)\\s+n[ëe.?]',
				'clean'   => true,
			),
			'school_year'   => array(
				'row'      => 22,
				'col'      => 1,
				'pattern'  => 'vitin\\s+shkollor\\s+([\\d\\/\\s]+)\\s+e\\s+kreu',
				'collapse' => true,
			),
			'cert_city'     => array(
				'row'     => 58,
				'col'     => 1,
				'pattern' => 'N[ëe.?]\\s+(.*?)\\s*,',
				'clean'   => true,
			),
			'cert_date'     => array(
				'row'     => 58,
				'col'     => 1,
				'pattern' => 'm[ëe.?]\\s+([\\d]{1,2}[\\.\\/\\-][\\d]{1,2}[\\.\\/\\-][\\d]{2,4})',
			),
			'class_teacher' => array(
				'row'   => 61,
				'col'   => 1,
				'clean' => true,
			),
			'director_name' => array(
				'row'   => 61,
				'col'   => 9,
				'clean' => true,
			),
		);
	}

	/**
	 * The whole map, in the shape the browser importer consumes.
	 *
	 * @return array<string,mixed> Map payload.
	 */
	public static function to_array(): array {
		return array(
			'firstGradeRow'         => self::GRADE_FIRST_ROW,
			'lastGradeRow'          => self::GRADE_LAST_ROW,
			'minColumns'            => self::MIN_COLUMNS,
			'categoryRows'          => array_values( array_map( 'intval', self::category_rows() ) ),
			'boldRows'              => array_values( array_map( 'intval', self::bold_rows() ) ),
			'gradeColumns'          => self::grade_columns(),
			'subjectFallbackColumn' => self::SUBJECT_FALLBACK_COLUMN,
			'scalars'               => self::scalar_rules(),
		);
	}
}
