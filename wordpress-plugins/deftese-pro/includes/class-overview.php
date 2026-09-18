<?php
/**
 * The administrator-only categorised overview.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Groups every certificate by author, school, year or class teacher.
 */
final class Overview {

	/**
	 * The groupings offered by the screen.
	 *
	 * @return array<string,string> Key => label.
	 */
	public static function groupings(): array {
		return array(
			'user'          => __( 'Sipas Përdoruesit', 'deftese-pro' ),
			'school'        => __( 'Sipas Shkollës', 'deftese-pro' ),
			'school_year'   => __( 'Sipas Vitit Shkollor', 'deftese-pro' ),
			'class_teacher' => __( 'Sipas Kujdestarit', 'deftese-pro' ),
		);
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Ju nuk keni akses për të parë këtë faqe.', 'deftese-pro' ), 403 );
		}

		$rows       = Repository::overview_rows();
		$groupings  = self::groupings();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch.
		$group_by = isset( $_GET['d_group'] ) ? sanitize_key( wp_unslash( $_GET['d_group'] ) ) : 'user';

		if ( ! array_key_exists( $group_by, $groupings ) ) {
			$group_by = 'user';
		}

		Assets::enqueue_app();

		view(
			'overview',
			array(
				'rows'      => $rows,
				'groups'    => self::group( $rows, $group_by ),
				'groupings' => $groupings,
				'group_by'  => $group_by,
				'stats'     => self::stats( $rows ),
				'base_url'  => remove_query_arg( array( 'd_group', 'd_open' ) ),
			)
		);
	}

	/**
	 * Headline counters.
	 *
	 * @param array<int,array<string,mixed>> $rows Overview rows.
	 * @return array<string,int> Counter => value.
	 */
	private static function stats( array $rows ): array {
		$users    = array();
		$distinct = array(
			'school_name'   => array(),
			'school_year'   => array(),
			'class_teacher' => array(),
		);

		foreach ( $rows as $row ) {
			$users[ (int) ( $row['created_by'] ?? 0 ) ] = true;

			foreach ( array_keys( $distinct ) as $column ) {
				$value = (string) ( $row[ $column ] ?? '' );

				if ( '' !== $value ) {
					$distinct[ $column ][ $value ] = true;
				}
			}
		}

		return array(
			'certificates' => count( $rows ),
			'users'        => count( $users ),
			'schools'      => count( $distinct['school_name'] ),
			'years'        => count( $distinct['school_year'] ),
			'teachers'     => count( $distinct['class_teacher'] ),
		);
	}

	/**
	 * Bucket rows by the chosen grouping, largest group first.
	 *
	 * @param array<int,array<string,mixed>> $rows      Overview rows.
	 * @param string                         $group_key Grouping key.
	 * @return array<string,array{label:string,rows:array<int,array<string,mixed>>}> Groups.
	 */
	private static function group( array $rows, string $group_key ): array {
		$groups = array();

		foreach ( $rows as $row ) {
			list( $key, $label ) = self::bucket_for( $row, $group_key );

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'label' => $label,
					'rows'  => array(),
				);
			}

			$groups[ $key ]['rows'][] = $row;
		}

		uasort(
			$groups,
			static function ( array $a, array $b ): int {
				$by_size = count( $b['rows'] ) <=> count( $a['rows'] );

				return 0 !== $by_size ? $by_size : strcasecmp( $a['label'], $b['label'] );
			}
		);

		return $groups;
	}

	/**
	 * Resolve one row's group key and label.
	 *
	 * @param array<string,mixed> $row       Overview row.
	 * @param string              $group_key Grouping key.
	 * @return array{0:string,1:string} Key and label.
	 */
	private static function bucket_for( array $row, string $group_key ): array {
		switch ( $group_key ) {
			case 'school':
				$value = (string) ( $row['school_name'] ?? '' );

				return array( 'sc_' . md5( $value ), '' !== $value ? $value : __( '— Pa shkollë të specifikuar —', 'deftese-pro' ) );

			case 'school_year':
				$value = (string) ( $row['school_year'] ?? '' );

				return array( 'sy_' . md5( $value ), '' !== $value ? $value : __( '— Pa vit shkollor —', 'deftese-pro' ) );

			case 'class_teacher':
				$value = (string) ( $row['class_teacher'] ?? '' );

				return array( 'ct_' . md5( $value ), '' !== $value ? $value : __( '— Pa kujdestar të specifikuar —', 'deftese-pro' ) );

			case 'user':
			default:
				$id    = (int) ( $row['created_by'] ?? 0 );
				$label = (string) ( $row['author_name'] ?? '' );

				if ( '' === $label ) {
					$label = (string) ( $row['author_login'] ?? '' );
				}

				if ( '' === $label ) {
					/* translators: %d: user id. */
					$label = sprintf( __( 'Përdorues i panjohur (ID: %d)', 'deftese-pro' ), $id );
				}

				return array( 'u_' . $id, $label );
		}
	}
}
