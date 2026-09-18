<?php
/**
 * Certificate field definitions and default data.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for which columns the editor may write.
 */
final class Fields {

	/**
	 * Default footer legend printed under the signatures.
	 */
	public const DEFAULT_FOOTER_NOTE = 'Notat: shkëlqyeshëm (5), shumë mirë (4), mirë (3), mjaftueshëm (2), pamjaftueshëm (1) dhe PIA (*). - Notat e sjelljes: shembullore (SH), e mirë (M), jo e mirë (JM)';

	/**
	 * Columns the editor and the importer are allowed to write.
	 *
	 * @return string[] Column names.
	 */
	public static function editable(): array {
		return array(
			'id',
			'registry_no',
			'student_name',
			'school_name',
			'school_city',
			'protocol_no',
			'ministry_code',
			'birth_date',
			'birth_city',
			'birth_commune',
			'birth_state',
			'school_year',
			'parent_name',
			'cert_city',
			'cert_date',
			'class_teacher',
			'director_name',
			'footer_note',
			'remarks_1',
			'remarks_2',
			'remarks_3',
		);
	}

	/**
	 * Columns sortable from the list table, mapped to their ORDER BY clause.
	 *
	 * Registry numbers are numeric strings such as "12/2024", so they sort
	 * numerically first and lexically second.
	 *
	 * @return array<string,string> Column => ORDER BY expression (without direction).
	 */
	public static function sortable(): array {
		return array(
			'registry_no'  => 'CAST(registry_no AS UNSIGNED)|registry_no',
			'student_name' => 'student_name',
			'school_name'  => 'school_name',
			'school_year'  => 'school_year',
			'created_at'   => 'created_at',
		);
	}

	/**
	 * Grade row keys, in table-column order.
	 *
	 * @return string[] Class keys.
	 */
	public static function grade_classes(): array {
		return array( 'vi', 'vii', 'viii', 'ix' );
	}

	/**
	 * Build one blank grade row.
	 *
	 * @param string $subject Subject label.
	 * @param bool   $is_cat  Whether the row is a category heading.
	 * @param bool   $is_bold Whether the row renders bold.
	 * @return array<string,mixed> Grade row.
	 */
	public static function grade_row( string $subject, bool $is_cat = false, bool $is_bold = false ): array {
		$row = array(
			'cat'     => $is_cat,
			'subject' => $subject,
		);

		if ( $is_bold ) {
			$row['bold'] = true;
		}

		foreach ( self::grade_classes() as $class ) {
			$row[ $class ]           = '';
			$row[ 'grade_' . $class ] = '';
		}

		$row['test'] = '';

		return $row;
	}

	/**
	 * The default Kosovo lower-secondary subject sheet.
	 *
	 * Rows flagged `cat` are red category headings that span the full width;
	 * rows flagged `bold` are the two summary rows at the bottom.
	 *
	 * @return array<int,array<string,mixed>> Grade rows.
	 */
	public static function default_grades(): array {
		$sheet = array(
			array( 'Gjuhët dhe komunikimi', true ),
			array( 'Gjuhë shqipe' ),
			array( 'Gjuhë angleze' ),
			array( 'Gjuhë gjermane' ),
			array( 'Artet', true ),
			array( 'Edukatë muzikore' ),
			array( 'Edukatë figurative' ),
			array( 'Matematikë', true ),
			array( 'Matematikë' ),
			array( 'Shkencat natyrore', true ),
			array( 'Fizikë' ),
			array( 'Kimi' ),
			array( 'Biologji' ),
			array( 'Shoqëria dhe mjedisi', true ),
			array( 'Histori' ),
			array( 'Gjeografi' ),
			array( 'Edukatë qytetare' ),
			array( 'Edukatë fizike, sportet dhe shëndeti', true ),
			array( 'Edukatë fizike, sportet dhe shëndeti' ),
			array( 'Jeta dhe puna', true ),
			array( 'Teknologji me TIK' ),
			array( 'Mësimi me zgjedhje', true ),
			array( 'Matematikë zbavitëse' ),
			array( 'Njohuri e instrumenteve muzikore' ),
			array( 'Artet e bukura' ),
			array( 'Arti në shkollë' ),
			array( 'Kulturë gjuhe dhe shkathtësi komunikimi' ),
			array( 'Mbrojtje e mjedisit jetësor' ),
			array( 'Plani individual i arsimit (PIA)', true ),
			array( 'Sjellje', false, true ),
			array( 'Vlerësimi / Suksesi i përgjithshëm', false, true ),
		);

		$grades = array();

		foreach ( $sheet as $spec ) {
			$grades[] = self::grade_row( $spec[0], ! empty( $spec[1] ), ! empty( $spec[2] ) );
		}

		return $grades;
	}

	/**
	 * Subject label of the row that totals the achievement-test points.
	 */
	public const TOTAL_ROW_SUBJECT = 'Vlerësimi / Suksesi i përgjithshëm';
}
