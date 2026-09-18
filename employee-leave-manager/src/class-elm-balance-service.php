<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_Balance_Service {
	public function ensure_balance( int $user_id, int $year ): void {
		global $wpdb;
		$table = ELM_DB::table( 'balances' );
		$standard_entitlement = ELM_Entitlement_Service::entitlement_for( $user_id, $year );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $table (user_id,leave_year,standard_entitlement,manual_added,approved_annual_used,updated_at)
				 VALUES (%d,%d,%f,0.0,0.0,%s)
				 ON DUPLICATE KEY UPDATE standard_entitlement = VALUES(standard_entitlement)",
				$user_id,
				$year,
				(float) $standard_entitlement,
				ELM_DB::now()
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function sync_cache( int $user_id, int $year ): void {
		global $wpdb;
		$this->ensure_balance( $user_id, $year );
		$requests = ELM_DB::table( 'requests' );
		$days = ELM_DB::table( 'request_days' );
		$adjustments = ELM_DB::table( 'adjustments' );
		$balances = ELM_DB::table( 'balances' );

		$approved = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(d.units),0)
				 FROM $days d INNER JOIN $requests r ON r.id=d.request_id
				 WHERE r.employee_id=%d AND r.leave_type='annual' AND r.status='approved'
				 AND YEAR(d.leave_date)=%d
				 AND NOT EXISTS (
					 SELECT 1 FROM $days md INNER JOIN $requests mr ON mr.id=md.request_id
					 WHERE mr.employee_id=r.employee_id AND mr.leave_type='medical' AND mr.status='approved'
					 AND md.leave_date=d.leave_date
				 )",
				$user_id,
				$year
			)
		);
		$approved = (float) round( $approved );

		$manual = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount),0) FROM $adjustments WHERE user_id=%d AND leave_year=%d",
				$user_id,
				$year
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$manual = (float) round( $manual );

		$wpdb->update(
			$balances,
			array(
				'manual_added'        => $manual,
				'approved_annual_used'=> $approved,
				'updated_at'          => ELM_DB::now(),
			),
			array( 'user_id' => $user_id, 'leave_year' => $year ),
			array( '%f', '%f', '%s' ),
			array( '%d', '%d' )
		);
	}

	public function committed_units( int $user_id, int $year, int $exclude_request_id = 0 ): array {
		global $wpdb;
		$requests = ELM_DB::table( 'requests' );
		$days = ELM_DB::table( 'request_days' );
		$exclude_sql = $exclude_request_id > 0 ? ' AND r.id<>%d' : '';
		$params = array( $user_id, $year );
		if ( $exclude_request_id > 0 ) {
			$params[] = $exclude_request_id;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
				 COALESCE(SUM(CASE WHEN r.status='approved' THEN d.units ELSE 0 END),0) approved,
				 COALESCE(SUM(CASE WHEN r.status='pending' THEN d.units ELSE 0 END),0) pending,
				 COALESCE(SUM(CASE WHEN d.period_no=1 AND r.status IN ('pending','approved') THEN d.units ELSE 0 END),0) period_one_committed,
				 COALESCE(SUM(CASE WHEN d.period_no=2 AND r.status IN ('pending','approved') THEN d.units ELSE 0 END),0) period_two_committed
				 FROM $days d INNER JOIN $requests r ON r.id=d.request_id
				 WHERE r.employee_id=%d AND r.leave_type='annual' AND YEAR(d.leave_date)=%d
				 AND r.status IN ('pending','approved')
				 AND NOT EXISTS (
					 SELECT 1 FROM $days md INNER JOIN $requests mr ON mr.id=md.request_id
					 WHERE mr.employee_id=r.employee_id AND mr.leave_type='medical' AND mr.status='approved'
					 AND md.leave_date=d.leave_date
				 )$exclude_sql",
				$params
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( static fn( $value ): int => (int) round( (float) $value ), $row ?: array( 'approved' => 0, 'pending' => 0, 'period_one_committed' => 0, 'period_two_committed' => 0 ) );
	}

	public function summary( int $user_id, int $year, int $exclude_request_id = 0 ): array {
		global $wpdb;
		$this->sync_cache( $user_id, $year );
		$balances = ELM_DB::table( 'balances' );
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $balances WHERE user_id=%d AND leave_year=%d", $user_id, $year ),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$committed = $this->committed_units( $user_id, $year, $exclude_request_id );
		$standard = (int) round( (float) ( $row['standard_entitlement'] ?? 20.0 ) );
		$manual = (int) round( (float) ( $row['manual_added'] ?? 0.0 ) );
		$total = $standard + $manual;
		// Pragu janar-qershor përdoret vetëm si paralajmërim informues në portal.
		// Ai nuk kufizon bilancin dhe nuk e ndalon paraqitjen ose miratimin e kërkesës.
		$p1_cap = ELM_Policy::period_one_warning_limit();
		$unlocked = $standard;

		return array(
			'user_id'                => $user_id,
			'year'                   => $year,
			'standard_entitlement'   => $standard,
			'manual_added'           => $manual,
			'total_entitlement'      => $total,
			'unlocked_standard'      => $unlocked,
			'approved_used'          => $committed['approved'],
			'pending_units'          => $committed['pending'],
			'committed_units'        => $committed['approved'] + $committed['pending'],
			'remaining_after_pending'=> max( 0, $total - $committed['approved'] - $committed['pending'] ),
			'period_one_committed'   => $committed['period_one_committed'],
			'period_one_limit'       => $p1_cap,
			'period_one_remaining'   => max( 0, $p1_cap - $committed['period_one_committed'] ),
		);
	}

	public function validate_new_annual_request( int $user_id, array $dates, int $exclude_request_id = 0 ): true|WP_Error {
		if ( empty( $dates ) ) {
			return new WP_Error( 'elm_empty_dates', __( 'Nuk është zgjedhur asnjë ditë pune.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}

		$days_by_year = array();
		foreach ( $dates as $date ) {
			$year = (int) substr( (string) ( $date['date'] ?? '' ), 0, 4 );
			if ( $year > 0 ) {
				$days_by_year[ $year ] = ( $days_by_year[ $year ] ?? 0 ) + 1;
			}
		}
		ksort( $days_by_year, SORT_NUMERIC );

		foreach ( $days_by_year as $year => $new_total ) {
			$summary = $this->summary( $user_id, (int) $year, $exclude_request_id );
			if ( $summary['committed_units'] + $new_total > $summary['total_entitlement'] ) {
				return new WP_Error(
					'elm_balance_exceeded',
					sprintf(
						/* translators: 1: leave year, 2: requested days in that year, 3: remaining days in that year. */
						__( 'Për vitin %1$d kërkohen %2$d ditë, ndërsa pas kërkesave në pritje mbeten %3$d ditë.', 'employee-leave-manager' ),
						(int) $year,
						(int) $new_total,
						(int) $summary['remaining_after_pending']
					),
					array( 'status' => 409, 'balance' => $summary, 'year' => (int) $year )
				);
			}
		}
		return true;
	}
}
