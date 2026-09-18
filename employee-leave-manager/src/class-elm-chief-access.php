<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controls which employees are exposed in each Leave Chief's frontend workspace.
 *
 * Assignments are stored per Chief as user metadata. In line with Regulation
 * (GRK) No. 04/2024 Article 10(3), a non-administrator Chief sees only employees
 * explicitly assigned to them. An absent or empty assignment means no managed
 * employees. WordPress administrators retain technical access for administration.
 */
final class ELM_Chief_Access {
	private const META_KEY = 'elm_chief_visible_employee_ids';

	public static function eligible_users(): array {
		$users = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name', 'user_email' ),
			)
		);

		return array_values(
			array_filter(
				$users,
				static function ( $user ): bool {
					if ( user_can( $user->ID, 'manage_options' ) ) {
						return false;
					}
					return user_can( $user->ID, 'elm_submit_leave' )
						|| user_can( $user->ID, 'elm_view_own_leave' )
						|| user_can( $user->ID, 'elm_manage_leave' );
				}
			)
		);
	}

	public static function eligible_employee_ids(): array {
		return array_map( static fn( $user ): int => (int) $user->ID, self::eligible_users() );
	}

	public static function chiefs(): array {
		$users = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name', 'user_email' ),
			)
		);
		return array_values(
			array_filter(
				$users,
				static fn( $user ): bool => user_can( $user->ID, 'elm_manage_leave' ) && ! user_can( $user->ID, 'manage_options' )
			)
		);
	}

	public static function is_configured( int $chief_id ): bool {
		return metadata_exists( 'user', $chief_id, self::META_KEY );
	}

	/**
	 * Returns null when no assignment record has been saved yet.
	 */
	public static function configured_employee_ids( int $chief_id ): ?array {
		if ( ! self::is_configured( $chief_id ) ) {
			return null;
		}
		$stored = get_user_meta( $chief_id, self::META_KEY, true );
		$ids = array_values( array_unique( array_filter( array_map( 'absint', is_array( $stored ) ? $stored : array() ) ) ) );
		return array_values( array_diff( array_intersect( $ids, self::eligible_employee_ids() ), array( $chief_id ) ) );
	}

	public static function visible_employee_ids( int $chief_id ): array {
		if ( user_can( $chief_id, 'manage_options' ) ) {
			return self::eligible_employee_ids();
		}
		$configured = self::configured_employee_ids( $chief_id );
		return null === $configured ? array() : $configured;
	}

	public static function can_manage_employee( int $viewer_id, int $employee_id ): bool {
		if ( $viewer_id <= 0 || $employee_id <= 0 ) {
			return false;
		}
		if ( user_can( $viewer_id, 'manage_options' ) ) {
			return true;
		}
		if ( ! user_can( $viewer_id, 'elm_manage_leave' ) ) {
			return false;
		}
		return in_array( $employee_id, self::visible_employee_ids( $viewer_id ), true );
	}

	public static function save_selected( int $chief_id, array $employee_ids, int $actor_id ): array|WP_Error {
		if ( ! user_can( $actor_id, 'manage_options' ) ) {
			return new WP_Error( 'elm_forbidden', __( 'Vetëm administratori mund t\'i caktojë punonjësit një mbikëqyrësi të drejtpërdrejtë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		if ( ! get_userdata( $chief_id ) || ! user_can( $chief_id, 'elm_manage_leave' ) ) {
			return new WP_Error( 'elm_invalid_chief', __( 'Zgjidhni një mbikëqyrës të vlefshëm.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}

		$eligible = self::eligible_employee_ids();
		$selected = array_values( array_unique( array_diff( array_intersect( array_map( 'absint', $employee_ids ), $eligible ), array( $chief_id ) ) ) );
		sort( $selected, SORT_NUMERIC );
		return self::persist( $chief_id, $selected, $actor_id, true );
	}

	public static function clear_assignments( int $chief_id, int $actor_id ): array|WP_Error {
		if ( ! user_can( $actor_id, 'manage_options' ) ) {
			return new WP_Error( 'elm_forbidden', __( 'Vetëm administratori mund t\'i caktojë punonjësit një mbikëqyrësi të drejtpërdrejtë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		if ( ! get_userdata( $chief_id ) || ! user_can( $chief_id, 'elm_manage_leave' ) ) {
			return new WP_Error( 'elm_invalid_chief', __( 'Zgjidhni një mbikëqyrës të vlefshëm.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		return self::persist( $chief_id, array(), $actor_id, true );
	}

	/**
	 * Legacy alias retained for integrations from earlier plugin versions.
	 * For legal/role separation it now clears assignments instead of granting all access.
	 */
	public static function reset_to_all( int $chief_id, int $actor_id ): array|WP_Error {
		return self::clear_assignments( $chief_id, $actor_id );
	}

	private static function persist( int $chief_id, array $selected, int $actor_id, bool $configured ): array|WP_Error {
		$before_exists = self::is_configured( $chief_id );
		$before_ids    = self::configured_employee_ids( $chief_id );

		if ( $configured ) {
			$updated = update_user_meta( $chief_id, self::META_KEY, $selected );
			if ( false === $updated && $selected !== ( $before_ids ?? array() ) ) {
				return new WP_Error( 'elm_chief_assignment_save_failed', __( 'Ruajtja e caktimit të punonjësve dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) );
			}
		} else {
			delete_user_meta( $chief_id, self::META_KEY );
		}

		if ( ELM_DB::schema_ready() ) {
			$audit = ELM_Audit::append(
				'chief_visibility',
				$chief_id,
				'updated',
				$actor_id,
				array(
					'before_mode'         => $before_exists && ! empty( $before_ids ) ? 'selected' : 'none',
					'before_employee_ids' => $before_ids ?? array(),
					'after_mode'          => $configured && ! empty( $selected ) ? 'selected' : 'none',
					'after_employee_ids'  => $configured ? $selected : array(),
				)
			);
			if ( is_wp_error( $audit ) ) {
				if ( $before_exists ) {
					update_user_meta( $chief_id, self::META_KEY, $before_ids ?? array() );
				} else {
					delete_user_meta( $chief_id, self::META_KEY );
				}
				return $audit;
			}
		}

		return array(
			'chief_id'    => $chief_id,
			'mode'        => $configured && ! empty( $selected ) ? 'selected' : 'none',
			'employee_ids'=> $configured ? $selected : array(),
		);
	}
}
