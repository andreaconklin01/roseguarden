<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_Leave_Service {
	private ELM_Balance_Service $balances;

	public function __construct() {
		$this->balances = new ELM_Balance_Service();
	}

	public function create_request( int $employee_id, array $input ): array|WP_Error {
		global $wpdb;
		if ( ! ELM_DB::schema_ready() ) {
			return new WP_Error( 'elm_database_unavailable', __( 'Baza e të dhënave të pushimeve nuk është e gatshme. Kontaktoni administratorin për ta riaktivizuar shtojcën.', 'employee-leave-manager' ), array( 'status' => 503 ) );
		}
		if ( ! get_userdata( $employee_id ) ) {
			return new WP_Error( 'elm_user_not_found', __( 'Punonjësi nuk u gjet.', 'employee-leave-manager' ), array( 'status' => 404 ) );
		}
		$actor_id = get_current_user_id();
		if ( $employee_id !== $actor_id && ! user_can( $actor_id, 'manage_options' ) && ! ELM_Chief_Access::can_manage_employee( $actor_id, $employee_id ) ) {
			return new WP_Error( 'elm_forbidden', __( 'Ky punonjës nuk është i caktuar nën mbikëqyrjen tuaj të drejtpërdrejtë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		$type = sanitize_key( (string) ( $input['leave_type'] ?? '' ) );
		if ( ! in_array( $type, array( 'annual', 'medical' ), true ) ) {
			return new WP_Error( 'elm_invalid_type', __( 'Zgjidhni pushim vjetor ose mjekësor.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		$request_source = current_user_can( 'elm_manage_leave' ) && $employee_id !== get_current_user_id() ? 'admin' : 'employee';
		$employee_position = ELM_Employee_Profile::position( $employee_id );
		$employee_sector   = ELM_Employee_Profile::sector( $employee_id );

		$start = sanitize_text_field( (string) ( $input['start_date'] ?? '' ) );
		$end = sanitize_text_field( (string) ( $input['end_date'] ?? '' ) );
		$reason = sanitize_textarea_field( (string) ( $input['reason'] ?? '' ) );
		if ( '' === $reason ) {
			return new WP_Error( 'elm_reason_required', __( 'Shkruani arsyetimin e kërkesës.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}

		$selection = $input['selected_dates'] ?? array();
		$dates = ! empty( $selection ) ? ELM_Policy::dates_from_selection( is_array( $selection ) ? $selection : (string) $selection ) : ELM_Policy::dates_for_request( $start, $end, 1.0 );
		if ( is_wp_error( $dates ) ) {
			return $dates;
		}
		$selected_values = wp_list_pluck( $dates, 'date' );
		$selected_start = (string) reset( $selected_values );
		$selected_end = (string) end( $selected_values );

		// Preserve the employee's inclusive calendar range when it safely contains
		// all deducted working days. Weekends and holidays remain visible as part
		// of the requested period but are not inserted into request_days.
		$range_is_valid = ELM_Policy::validate_iso_date( $start )
			&& ELM_Policy::validate_iso_date( $end )
			&& $start <= $selected_start
			&& $end >= $selected_end;
		if ( $range_is_valid ) {
			$range_from = new DateTimeImmutable( $start, wp_timezone() );
			$range_to = new DateTimeImmutable( $end, wp_timezone() );
			$range_is_valid = $range_to >= $range_from && (int) $range_from->diff( $range_to )->days <= 366;
		}
		if ( ! $range_is_valid ) {
			$start = $selected_start;
			$end = $selected_end;
		}
		if ( 'annual' === $type ) {
			$today = ELM_Policy::today();
			if ( $start < $today ) {
				return new WP_Error( 'elm_annual_in_past', __( 'Pushimi vjetor nuk mund të përfshijë data të kaluara.', 'employee-leave-manager' ), array( 'status' => 400 ) );
			}
		}

		$medical_ack = ! empty( $input['medical_ack'] ) ? 1 : 0;
		$document_id = absint( $input['medical_document_id'] ?? 0 );
		if ( 'medical' === $type ) {
			if ( ! $medical_ack && ! $document_id ) {
				return new WP_Error( 'elm_medical_evidence_required', __( 'Konfirmoni se do të ofroni dëshminë përkatëse mjekësore ose bashkëngjitni dokumentin.', 'employee-leave-manager' ), array( 'status' => 400 ) );
			}
			if ( $document_id && ! ELM_Medical_Storage::document_belongs_to( $document_id, $employee_id ) ) {
				return new WP_Error( 'elm_invalid_document', __( 'Dokumenti mjekësor është i pavlefshëm.', 'employee-leave-manager' ), array( 'status' => 403 ) );
			}
		} else {
			$medical_ack = 0;
			$document_id = 0;
		}

		$blocked_dates = 'annual' === $type ? $this->blocked_dates( wp_list_pluck( $dates, 'date' ) ) : array();
		if ( $blocked_dates ) {
			return new WP_Error( 'elm_capacity_reached', __( 'Për një ose më shumë data është arritur kapaciteti ditor i miratimeve.', 'employee-leave-manager' ), array( 'status' => 409, 'dates' => $blocked_dates ) );
		}

		$overlap = $this->employee_overlap( $employee_id, wp_list_pluck( $dates, 'date' ), 0, $type );
		if ( $overlap ) {
			return new WP_Error( 'elm_employee_overlap', __( 'Tashmë keni një kërkesë në pritje ose të miratuar për një ose më shumë data të zgjedhura.', 'employee-leave-manager' ), array( 'status' => 409, 'dates' => $overlap ) );
		}

		$policy_warnings = $this->empty_policy_warnings();
		if ( 'annual' === $type ) {
			$balance_check = $this->balances->validate_new_annual_request( $employee_id, $dates );
			if ( is_wp_error( $balance_check ) ) {
				return $balance_check;
			}
			$policy_warnings = $this->classify_policy_warnings( $employee_id, $dates );
		}

		$requests = ELM_DB::table( 'requests' );
		$request_days = ELM_DB::table( 'request_days' );
		$now = ELM_DB::now();
		$total = count( $dates );
		$pending_conflicts = $this->pending_dates( wp_list_pluck( $dates, 'date' ) );

		ELM_DB::begin();
		try {
			$inserted = $wpdb->insert(
				$requests,
				array(
					'employee_id'          => $employee_id,
					'employee_position'    => $employee_position,
					'employee_sector'      => $employee_sector,
					'leave_type'           => $type,
					'request_source'       => $request_source,
					'start_date'           => $start,
					'end_date'             => $end,
					'requested_units'       => $total,
					'status'                => 'pending',
					'reason'                => $reason,
					'medical_ack'           => $medical_ack,
					'medical_document_id'   => $document_id ?: null,
					'submitted_at'          => $now,
					'updated_at'            => $now,
			),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%d', '%s', '%s' )
			);
			if ( false === $inserted ) {
				throw new RuntimeException( 'Request insert failed.' );
			}
			$request_id = (int) $wpdb->insert_id;
			$short_notice_set = array_flip( $policy_warnings['short_notice_warning_dates'] );
			$period_one_set   = array_flip( $policy_warnings['period_one_warning_dates'] );
			foreach ( $dates as $date ) {
				$ok = $wpdb->insert(
					$request_days,
					array(
						'request_id'           => $request_id,
						'leave_date'           => $date['date'],
						'units'                => $date['units'],
						'period_no'            => $date['period'],
						'short_notice_warning' => isset( $short_notice_set[ $date['date'] ] ) ? 1 : 0,
						'period_one_warning'   => isset( $period_one_set[ $date['date'] ] ) ? 1 : 0,
					),
					array( '%d', '%s', '%f', '%d', '%d', '%d' )
				);
				if ( false === $ok ) {
					throw new RuntimeException( 'Request-day insert failed.' );
				}
			}
			$audit = ELM_Audit::append(
				'leave_request',
				$request_id,
				'created',
				get_current_user_id(),
				array(
					'employee_id'       => $employee_id,
					'employee_position' => $employee_position,
					'employee_sector'   => $employee_sector,
					'leave_type'       => $type,
					'start_date'       => $start,
					'end_date'         => $end,
					'requested_units'  => $total,
					'status'           => 'pending',
					'selected_dates'                 => $selected_values,
					'short_notice_warning_dates'      => $policy_warnings['short_notice_warning_dates'],
					'period_one_warning_dates'         => $policy_warnings['period_one_warning_dates'],
					'requires_chief_approval'          => $policy_warnings['requires_chief_approval'],
					'medical_ack'                      => (bool) $medical_ack,
					'document_present' => (bool) $document_id,
				)
			);
			if ( is_wp_error( $audit ) ) {
				throw new RuntimeException( $audit->get_error_message() );
			}
			ELM_DB::commit();
		} catch ( Throwable $e ) {
			ELM_DB::rollback();
			ELM_DB::log_failure( 'Saving leave request', $e );
			$message = __( 'Ruajtja e kërkesës dështoi. Gabimi u regjistrua për administratorin.', 'employee-leave-manager' );
			if ( current_user_can( 'manage_options' ) && ! empty( $wpdb->last_error ) ) {
				$message .= ' ' . sprintf( __( 'Gabim në bazën e të dhënave: %s', 'employee-leave-manager' ), sanitize_text_field( $wpdb->last_error ) );
			}
			return new WP_Error( 'elm_create_failed', $message, array( 'status' => 500 ) );
		}

		/**
		 * Fires after a new leave request is stored.
		 *
		 * @param int $request_id Newly created request id.
		 * @param int $employee_id Employee the request belongs to.
		 */
		do_action( 'elm_request_created', $request_id, $employee_id );

		$result = $this->get_request( $request_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['pending_conflict_dates'] = $pending_conflicts;
		return $result;
	}


	public function update_request( int $request_id, array $input, int $actor_id ): array|WP_Error {
		global $wpdb;
		if ( ! ELM_DB::schema_ready() ) {
			return new WP_Error( 'elm_database_unavailable', __( 'Baza e të dhënave të pushimeve nuk është e gatshme. Kontaktoni administratorin për ta riaktivizuar shtojcën.', 'employee-leave-manager' ), array( 'status' => 503 ) );
		}

		$requests = ELM_DB::table( 'requests' );
		$request_days = ELM_DB::table( 'request_days' );
		$request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $requests WHERE id=%d", $request_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $request ) {
			return new WP_Error( 'elm_not_found', __( 'Kërkesa për pushim nuk u gjet.', 'employee-leave-manager' ), array( 'status' => 404 ) );
		}
		$employee_id = (int) $request['employee_id'];
		if ( $employee_id !== $actor_id && ! user_can( $actor_id, 'manage_options' ) && ! ELM_Chief_Access::can_manage_employee( $actor_id, $employee_id ) ) {
			return new WP_Error( 'elm_forbidden', __( 'Nuk keni leje ta redaktoni këtë kërkesë. Punonjësi duhet të jetë i caktuar nën mbikëqyrjen tuaj të drejtpërdrejtë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		if ( 'pending' !== sanitize_key( (string) $request['status'] ) ) {
			return new WP_Error( 'elm_invalid_status', __( 'Mund të redaktohen vetëm kërkesat në pritje.', 'employee-leave-manager' ), array( 'status' => 409 ) );
		}

		$type = sanitize_key( (string) ( $input['leave_type'] ?? $request['leave_type'] ) );
		if ( ! in_array( $type, array( 'annual', 'medical' ), true ) ) {
			return new WP_Error( 'elm_invalid_type', __( 'Zgjidhni pushim vjetor ose mjekësor.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		$reason = sanitize_textarea_field( (string) ( $input['reason'] ?? $request['reason'] ) );
		if ( '' === $reason ) {
			return new WP_Error( 'elm_reason_required', __( 'Shkruani arsyetimin e kërkesës.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}

		$start = sanitize_text_field( (string) ( $input['start_date'] ?? $request['start_date'] ) );
		$end = sanitize_text_field( (string) ( $input['end_date'] ?? $request['end_date'] ) );
		$has_explicit_selection = array_key_exists( 'selected_dates', $input );
		$selection = $has_explicit_selection ? $input['selected_dates'] : $this->request_dates( $request_id );
		$dates = ELM_Policy::dates_from_selection( is_array( $selection ) ? $selection : (string) $selection );
		if ( is_wp_error( $dates ) ) {
			return $dates;
		}
		$selected_values = wp_list_pluck( $dates, 'date' );
		$old_dates = $this->request_dates( $request_id );
		$selected_start = (string) reset( $selected_values );
		$selected_end = (string) end( $selected_values );
		$range_is_valid = ELM_Policy::validate_iso_date( $start )
			&& ELM_Policy::validate_iso_date( $end )
			&& $start <= $selected_start
			&& $end >= $selected_end;
		if ( $range_is_valid ) {
			$range_from = new DateTimeImmutable( $start, wp_timezone() );
			$range_to = new DateTimeImmutable( $end, wp_timezone() );
			$range_is_valid = $range_to >= $range_from && (int) $range_from->diff( $range_to )->days <= 366;
		}
		if ( ! $range_is_valid ) {
			$start = $selected_start;
			$end = $selected_end;
		}
		if ( 'annual' === $type ) {
			$today = ELM_Policy::today();
			$old_type = sanitize_key( (string) $request['leave_type'] );
			$new_annual_dates = 'annual' === $old_type ? array_values( array_diff( $selected_values, $old_dates ) ) : $selected_values;
			$new_past_dates = array_values( array_filter( $new_annual_dates, static fn( string $date ): bool => $date < $today ) );
			if ( $new_past_dates ) {
				return new WP_Error( 'elm_annual_in_past', __( 'Pushimit vjetor nuk mund t\'i shtohen data të kaluara.', 'employee-leave-manager' ), array( 'status' => 400, 'dates' => $new_past_dates ) );
			}
		}

		$medical_ack = array_key_exists( 'medical_ack_present', $input ) ? ( ! empty( $input['medical_ack'] ) ? 1 : 0 ) : (int) $request['medical_ack'];
		$document_id = array_key_exists( 'medical_document_id', $input ) ? absint( $input['medical_document_id'] ) : absint( $request['medical_document_id'] ?? 0 );
		if ( 'medical' === $type ) {
			if ( ! $medical_ack && ! $document_id ) {
				return new WP_Error( 'elm_medical_evidence_required', __( 'Konfirmoni se do të ofroni dëshminë përkatëse mjekësore ose bashkëngjitni dokumentin.', 'employee-leave-manager' ), array( 'status' => 400 ) );
			}
			if ( $document_id && ! ELM_Medical_Storage::document_belongs_to( $document_id, $employee_id ) ) {
				return new WP_Error( 'elm_invalid_document', __( 'Dokumenti mjekësor është i pavlefshëm.', 'employee-leave-manager' ), array( 'status' => 403 ) );
			}
		} else {
			$medical_ack = 0;
			$document_id = 0;
		}

		$blocked_dates = 'annual' === $type ? array_values( array_diff( $this->blocked_dates( $selected_values ), $old_dates ) ) : array();
		if ( $blocked_dates ) {
			return new WP_Error( 'elm_capacity_reached', __( 'Për një ose më shumë data të reja është arritur kapaciteti ditor i miratimeve.', 'employee-leave-manager' ), array( 'status' => 409, 'dates' => $blocked_dates ) );
		}
		$overlap = $this->employee_overlap( $employee_id, $selected_values, $request_id, $type );
		if ( $overlap ) {
			return new WP_Error( 'elm_employee_overlap', __( 'Tashmë keni një kërkesë tjetër në pritje ose të miratuar për një ose më shumë data të zgjedhura.', 'employee-leave-manager' ), array( 'status' => 409, 'dates' => $overlap ) );
		}

		$policy_warnings = $this->empty_policy_warnings();
		if ( 'annual' === $type ) {
			$balance_check = $this->balances->validate_new_annual_request( $employee_id, $dates, $request_id );
			if ( is_wp_error( $balance_check ) ) {
				return $balance_check;
			}
			$policy_warnings = $this->classify_policy_warnings( $employee_id, $dates, $request_id );
		}

		$old_years = $this->years_from_dates( $old_dates );
		$new_years = $this->years_from_dates( $selected_values );
		$now = ELM_DB::now();
		$expected_version = (int) $request['version'];

		ELM_DB::begin();
		try {
			$locked = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $requests WHERE id=%d FOR UPDATE", $request_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $locked ) {
				throw new DomainException( 'not_found' );
			}
			if ( 'pending' !== sanitize_key( (string) $locked['status'] ) ) {
				throw new DomainException( 'invalid_status' );
			}
			if ( (int) $locked['version'] !== $expected_version ) {
				throw new DomainException( 'conflict' );
			}

			$deleted = $wpdb->delete( $request_days, array( 'request_id' => $request_id ), array( '%d' ) );
			if ( false === $deleted ) {
				throw new RuntimeException( 'Request-day replacement failed.' );
			}
			$short_notice_set = array_flip( $policy_warnings['short_notice_warning_dates'] );
			$period_one_set = array_flip( $policy_warnings['period_one_warning_dates'] );
			foreach ( $dates as $date ) {
				$inserted = $wpdb->insert(
					$request_days,
					array(
						'request_id'           => $request_id,
						'leave_date'           => $date['date'],
						'units'                => $date['units'],
						'period_no'            => $date['period'],
						'short_notice_warning' => isset( $short_notice_set[ $date['date'] ] ) ? 1 : 0,
						'period_one_warning'   => isset( $period_one_set[ $date['date'] ] ) ? 1 : 0,
					),
					array( '%d', '%s', '%f', '%d', '%d', '%d' )
				);
				if ( false === $inserted ) {
					throw new RuntimeException( 'Request-day replacement failed.' );
				}
			}

			$updated = $wpdb->update(
				$requests,
				array(
					'leave_type'          => $type,
					'start_date'          => $start,
					'end_date'            => $end,
					'requested_units'      => count( $dates ),
					'reason'               => $reason,
					'medical_ack'          => $medical_ack,
					'medical_document_id'  => $document_id ?: null,
					'updated_at'           => $now,
					'version'              => $expected_version + 1,
				),
				array( 'id' => $request_id, 'status' => 'pending', 'version' => $expected_version ),
				array( '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%d' ),
				array( '%d', '%s', '%d' )
			);
			if ( 1 !== $updated ) {
				throw new DomainException( 'conflict' );
			}

			$audit = ELM_Audit::append(
				'leave_request',
				$request_id,
				'updated',
				$actor_id,
				array(
					'previous_leave_type' => (string) $request['leave_type'],
					'leave_type'          => $type,
					'previous_start_date' => (string) $request['start_date'],
					'previous_end_date'   => (string) $request['end_date'],
					'start_date'          => $start,
					'end_date'            => $end,
					'previous_dates'      => $old_dates,
					'selected_dates'      => $selected_values,
					'previous_reason'     => (string) $request['reason'],
					'reason'              => $reason,
					'reason_changed'      => (string) $request['reason'] !== $reason,
					'requires_chief_approval' => $policy_warnings['requires_chief_approval'],
				)
			);
			if ( is_wp_error( $audit ) ) {
				throw new RuntimeException( $audit->get_error_message() );
			}
			ELM_DB::commit();
		} catch ( DomainException $e ) {
			ELM_DB::rollback();
			$errors = array(
				'not_found'      => array( 404, __( 'Kërkesa për pushim nuk u gjet.', 'employee-leave-manager' ) ),
				'invalid_status' => array( 409, __( 'Mund të redaktohen vetëm kërkesat në pritje.', 'employee-leave-manager' ) ),
				'conflict'       => array( 409, __( 'Kjo kërkesë ndryshoi gjatë redaktimit. Rifreskoni faqen dhe provoni përsëri.', 'employee-leave-manager' ) ),
			);
			$error = $errors[ $e->getMessage() ] ?? array( 409, __( 'Redaktimi i kërkesës dështoi.', 'employee-leave-manager' ) );
			return new WP_Error( 'elm_' . $e->getMessage(), $error[1], array( 'status' => $error[0] ) );
		} catch ( Throwable $e ) {
			ELM_DB::rollback();
			ELM_DB::log_failure( 'Updating leave request', $e );
			return new WP_Error( 'elm_update_failed', __( 'Ruajtja e ndryshimeve dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}

		foreach ( array_values( array_unique( array_merge( $old_years, $new_years ) ) ) as $year ) {
			$this->balances->sync_cache( $employee_id, (int) $year );
		}
		$result = $this->get_request( $request_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['pending_conflict_dates'] = $this->pending_dates( $selected_values, $request_id );
		return $result;
	}

	public function get_request( int $request_id ): array|WP_Error {
		global $wpdb;
		$table = ELM_DB::table( 'requests' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", $request_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			return new WP_Error( 'elm_not_found', __( 'Kërkesa për pushim nuk u gjet.', 'employee-leave-manager' ), array( 'status' => 404 ) );
		}
		$user = get_userdata( (int) $row['employee_id'] );
		$row['id'] = (int) $row['id'];
		$row['employee_id'] = (int) $row['employee_id'];
		$row['employee_name'] = $user ? $user->display_name : __( 'Përdorues i fshirë', 'employee-leave-manager' );
		$row['requested_units'] = (int) round( (float) $row['requested_units'] );
		$row['medical_ack'] = (bool) $row['medical_ack'];
		$row['medical_document_id'] = $row['medical_document_id'] ? (int) $row['medical_document_id'] : null;
		$row['selected_dates'] = $this->request_dates( (int) $row['id'] );
		$row = array_merge( $row, $this->request_policy_warnings( (int) $row['id'] ) );
		$created_actor_map = $this->request_created_actor_map( array( (int) $row['id'] ) );
		$update_event_map = $this->request_update_events_map( array( (int) $row['id'] ) );
		$row['reason_history'] = $this->build_reason_history( $row, $created_actor_map[ (int) $row['id'] ] ?? null, $update_event_map[ (int) $row['id'] ] ?? array() );
		return $row;
	}

	public function list_requests( array $args, int $viewer_id, bool $unrestricted = false ): array {
		global $wpdb;
		$table = ELM_DB::table( 'requests' );
		$where = array( '1=1' );
		$params = array();
		$is_admin = user_can( $viewer_id, 'manage_options' );
		$is_manager = user_can( $viewer_id, 'elm_manage_leave' );
		if ( ! $unrestricted && ! $is_admin && ! $is_manager ) {
			$where[] = 'employee_id=%d';
			$params[] = $viewer_id;
		} elseif ( ! empty( $args['employee_id'] ) ) {
			$employee_id = absint( $args['employee_id'] );
			if ( ! $unrestricted && ! $is_admin && $employee_id !== $viewer_id && ! ELM_Chief_Access::can_manage_employee( $viewer_id, $employee_id ) ) {
				$where[] = '1=0';
			} else {
				$where[] = 'employee_id=%d';
				$params[] = $employee_id;
			}
		} elseif ( array_key_exists( 'employee_ids', $args ) ) {
			$employee_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $args['employee_ids'] ) ) ) );
			if ( ! $unrestricted && ! $is_admin && $is_manager ) {
				$allowed_ids = array_values( array_unique( array_merge( array( $viewer_id ), ELM_Chief_Access::visible_employee_ids( $viewer_id ) ) ) );
				$employee_ids = array_values( array_intersect( $employee_ids, $allowed_ids ) );
			}
			if ( ! $employee_ids ) {
				$where[] = '1=0';
			} else {
				$where[] = 'employee_id IN (' . implode( ',', array_fill( 0, count( $employee_ids ), '%d' ) ) . ')';
				$params = array_merge( $params, $employee_ids );
			}
		} elseif ( ! $unrestricted && ! $is_admin && $is_manager ) {
			$employee_ids = ELM_Chief_Access::visible_employee_ids( $viewer_id );
			if ( ! $employee_ids ) {
				$where[] = '1=0';
			} else {
				$where[] = 'employee_id IN (' . implode( ',', array_fill( 0, count( $employee_ids ), '%d' ) ) . ')';
				$params = array_merge( $params, $employee_ids );
			}
		}
		$status = sanitize_key( (string) ( $args['status'] ?? '' ) );
		if ( in_array( $status, array( 'pending', 'approved', 'rejected', 'cancelled' ), true ) ) {
			$where[] = 'status=%s';
			$params[] = $status;
		}
		$year = absint( $args['year'] ?? 0 );
		if ( $year ) {
			$days_table = ELM_DB::table( 'request_days' );
			$where[] = "EXISTS (SELECT 1 FROM $days_table filter_days WHERE filter_days.request_id=$table.id AND YEAR(filter_days.leave_date)=%d)";
			$params[] = $year;
		}
		$sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT 2000';
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $rows ?: array();
		$request_ids = array_map( static fn( array $row ): int => (int) $row['id'], $rows );
		$date_map = $this->request_dates_map( $request_ids );
		$warning_map = $this->request_policy_warnings_map( $request_ids );
		$created_actor_map = $this->request_created_actor_map( $request_ids );
		$update_event_map = $this->request_update_events_map( $request_ids );
		return array_map(
			function ( array $row ) use ( $date_map, $warning_map, $created_actor_map, $update_event_map ): array {
				$user = get_userdata( (int) $row['employee_id'] );
				$row['id'] = (int) $row['id'];
				$row['employee_id'] = (int) $row['employee_id'];
				$row['employee_name'] = $user ? $user->display_name : __( 'Përdorues i fshirë', 'employee-leave-manager' );
				$row['requested_units'] = (int) round( (float) $row['requested_units'] );
				$row['medical_ack'] = (bool) $row['medical_ack'];
				$row['medical_document_id'] = $row['medical_document_id'] ? (int) $row['medical_document_id'] : null;
				$row['selected_dates'] = $date_map[ $row['id'] ] ?? array();
				$row = array_merge( $row, $warning_map[ $row['id'] ] ?? array(
					'short_notice_warning_dates' => array(),
					'period_one_warning_dates'    => array(),
					'policy_warning_dates'        => array(),
					'requires_chief_approval'     => false,
				) );
				$row['reason_history'] = $this->build_reason_history( $row, $created_actor_map[ $row['id'] ] ?? null, $update_event_map[ $row['id'] ] ?? array() );
				return $row;
			},
			$rows
		);
	}

	private function request_created_actor_map( array $request_ids ): array {
		global $wpdb;
		$request_ids = array_values( array_filter( array_map( 'absint', $request_ids ) ) );
		if ( ! $request_ids ) {
			return array();
		}
		$table = ELM_DB::table( 'audit' );
		$placeholders = implode( ',', array_fill( 0, count( $request_ids ), '%d' ) );
		$params = array_merge( array( 'leave_request', 'created' ), $request_ids );
		$sql = $wpdb->prepare(
			"SELECT entity_id,actor_id,created_at FROM $table WHERE entity_type=%s AND action_name=%s AND entity_id IN ($placeholders) ORDER BY id ASC",
			$params
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$map = array();
		foreach ( $rows ?: array() as $row ) {
			$request_id = (int) $row['entity_id'];
			if ( isset( $map[ $request_id ] ) ) {
				continue;
			}
			$map[ $request_id ] = array(
				'actor_id'   => (int) $row['actor_id'],
				'created_at' => (string) $row['created_at'],
			);
		}
		return $map;
	}


	private function request_update_events_map( array $request_ids ): array {
		global $wpdb;
		$request_ids = array_values( array_filter( array_map( 'absint', $request_ids ) ) );
		if ( ! $request_ids ) {
			return array();
		}
		$table = ELM_DB::table( 'audit' );
		$placeholders = implode( ',', array_fill( 0, count( $request_ids ), '%d' ) );
		$params = array_merge( array( 'leave_request', 'updated' ), $request_ids );
		$sql = $wpdb->prepare(
			"SELECT id,entity_id,actor_id,payload_json,created_at FROM $table WHERE entity_type=%s AND action_name=%s AND entity_id IN ($placeholders) ORDER BY id ASC",
			$params
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$map = array();
		foreach ( $rows ?: array() as $row ) {
			$payload = json_decode( (string) $row['payload_json'], true );
			$map[ (int) $row['entity_id'] ][] = array(
				'audit_id'   => (int) $row['id'],
				'actor_id'   => (int) $row['actor_id'],
				'created_at' => (string) $row['created_at'],
				'payload'    => is_array( $payload ) ? $payload : array(),
			);
		}
		return $map;
	}

	private function reason_actor_name( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return __( 'Përdorues i panjohur', 'employee-leave-manager' );
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return sprintf( __( 'Përdoruesi nr. %d', 'employee-leave-manager' ), $user_id );
		}
		return '' !== trim( (string) $user->user_login ) ? (string) $user->user_login : (string) $user->display_name;
	}

	private function build_reason_history( array $request, ?array $created_actor, array $update_events = array() ): array {
		$history = array();
		$employee_id = (int) ( $request['employee_id'] ?? 0 );
		$request_source = sanitize_key( (string) ( $request['request_source'] ?? 'employee' ) );
		$current_reason = sanitize_textarea_field( (string) ( $request['reason'] ?? '' ) );
		$original_reason = $current_reason;
		foreach ( $update_events as $update_event ) {
			$payload = is_array( $update_event['payload'] ?? null ) ? $update_event['payload'] : array();
			if ( ! empty( $payload['reason_changed'] ) ) {
				$previous_reason = sanitize_textarea_field( (string) ( $payload['previous_reason'] ?? '' ) );
				if ( '' !== $previous_reason ) {
					$original_reason = $previous_reason;
				}
				break;
			}
		}
		if ( '' !== $original_reason ) {
			$created_by = (int) ( $created_actor['actor_id'] ?? ( 'admin' === $request_source ? 0 : $employee_id ) );
			$history[] = array(
				'role'        => 'admin' === $request_source ? __( 'Mbikëqyrësi', 'employee-leave-manager' ) : __( 'Punonjësi', 'employee-leave-manager' ),
				'actor_id'    => $created_by,
				'actor_name'  => $this->reason_actor_name( $created_by ),
				'text'        => $original_reason,
				'status'      => 'pending',
				'happened_at' => (string) ( $request['submitted_at'] ?? ( $created_actor['created_at'] ?? '' ) ),
				'event_order' => 1,
			);
		}

		foreach ( $update_events as $update_event ) {
			$payload = is_array( $update_event['payload'] ?? null ) ? $update_event['payload'] : array();
			if ( empty( $payload['reason_changed'] ) ) {
				continue;
			}
			$updated_reason = sanitize_textarea_field( (string) ( $payload['reason'] ?? '' ) );
			if ( '' === $updated_reason ) {
				continue;
			}
			$updated_by = (int) ( $update_event['actor_id'] ?? 0 );
			$history[] = array(
				'role'        => $updated_by === $employee_id ? __( 'Punonjësi', 'employee-leave-manager' ) : __( 'Mbikëqyrësi', 'employee-leave-manager' ),
				'actor_id'    => $updated_by,
				'actor_name'  => $this->reason_actor_name( $updated_by ),
				'text'        => $updated_reason,
				'status'      => 'pending',
				'happened_at' => (string) ( $update_event['created_at'] ?? '' ),
				'event_order' => 100 + (int) ( $update_event['audit_id'] ?? 0 ),
			);
		}

		$decision_reason = sanitize_textarea_field( (string) ( $request['decision_note'] ?? '' ) );
		if ( '' !== $decision_reason ) {
			$decision_status = 'rejected' === sanitize_key( (string) ( $request['status'] ?? '' ) ) ? 'rejected' : 'approved';
			$decided_by = (int) ( $request['decided_by'] ?? 0 );
			$history[] = array(
				'role'        => __( 'Mbikëqyrësi', 'employee-leave-manager' ),
				'actor_id'    => $decided_by,
				'actor_name'  => $this->reason_actor_name( $decided_by ),
				'text'        => $decision_reason,
				'status'      => $decision_status,
				'happened_at' => (string) ( $request['decided_at'] ?? '' ),
				'event_order' => 1000000,
			);
		}

		$cancel_reason = sanitize_textarea_field( (string) ( $request['cancel_reason'] ?? '' ) );
		if ( '' !== $cancel_reason ) {
			$cancelled_by = (int) ( $request['cancelled_by'] ?? 0 );
			$history[] = array(
				'role'        => $cancelled_by === $employee_id ? __( 'Punonjësi', 'employee-leave-manager' ) : __( 'Mbikëqyrësi', 'employee-leave-manager' ),
				'actor_id'    => $cancelled_by,
				'actor_name'  => $this->reason_actor_name( $cancelled_by ),
				'text'        => $cancel_reason,
				'status'      => 'cancelled',
				'happened_at' => (string) ( $request['cancelled_at'] ?? '' ),
				'event_order' => 2000000,
			);
		}

		usort(
			$history,
			static function ( array $left, array $right ): int {
				$time_compare = strcmp( (string) $left['happened_at'], (string) $right['happened_at'] );
				return 0 !== $time_compare ? $time_compare : ( (int) $left['event_order'] <=> (int) $right['event_order'] );
			}
		);
		return array_map(
			static function ( array $entry ): array {
				unset( $entry['event_order'] );
				return $entry;
			},
			$history
		);
	}

	private function request_dates( int $request_id ): array {
		$map = $this->request_dates_map( array( $request_id ) );
		return $map[ $request_id ] ?? array();
	}

	private function request_dates_map( array $request_ids ): array {
		global $wpdb;
		$request_ids = array_values( array_filter( array_map( 'absint', $request_ids ) ) );
		if ( ! $request_ids ) {
			return array();
		}
		$table = ELM_DB::table( 'request_days' );
		$placeholders = implode( ',', array_fill( 0, count( $request_ids ), '%d' ) );
		$sql = $wpdb->prepare( "SELECT request_id,leave_date FROM $table WHERE request_id IN ($placeholders) ORDER BY leave_date ASC", $request_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$map = array();
		foreach ( $rows ?: array() as $row ) {
			$id = (int) $row['request_id'];
			$map[ $id ][] = (string) $row['leave_date'];
		}
		return $map;
	}


	private function years_from_dates( array $dates ): array {
		$years = array();
		foreach ( $dates as $date ) {
			$value = is_array( $date ) ? (string) ( $date['date'] ?? '' ) : (string) $date;
			$year = (int) substr( $value, 0, 4 );
			if ( $year > 0 ) {
				$years[] = $year;
			}
		}
		$years = array_values( array_unique( $years ) );
		sort( $years, SORT_NUMERIC );
		return $years;
	}

	private function empty_policy_warnings(): array {
		return array(
			'short_notice_warning_dates' => array(),
			'period_one_warning_dates'    => array(),
			'policy_warning_dates'        => array(),
			'requires_chief_approval'     => false,
		);
	}

	private function classify_policy_warnings( int $employee_id, array $dates, int $exclude_request_id = 0 ): array {
		$warnings = $this->empty_policy_warnings();
		$limit = ELM_Policy::period_one_warning_limit();
		$today = ELM_Policy::today();
		$earliest_start = ELM_Policy::annual_earliest_start( $today );

		$existing_short_notice = array();
		$existing_dates = array();
		if ( $exclude_request_id > 0 ) {
			$existing = $this->request_policy_warnings( $exclude_request_id );
			$existing_short_notice = array_flip( (array) ( $existing['short_notice_warning_dates'] ?? array() ) );
			$existing_dates = array_flip( $this->request_dates( $exclude_request_id ) );
		}

		$period_one_index = array();
		foreach ( $dates as $date ) {
			$value = (string) ( $date['date'] ?? '' );
			$period = (int) ( $date['period'] ?? 0 );
			if ( ! ELM_Policy::validate_iso_date( $value ) ) {
				continue;
			}

			// Afati 15-ditor është vërejtje. Për redaktim ruhen flamujt ekzistues
			// dhe vetëm datat e reja vlerësohen sipas datës së sotme.
			$is_existing_date = isset( $existing_dates[ $value ] );
			if ( isset( $existing_short_notice[ $value ] ) || ( ! $is_existing_date && $value >= $today && $value < $earliest_start ) ) {
				$warnings['short_notice_warning_dates'][] = $value;
			}

			if ( 1 === $period && $limit > 0 ) {
				$year = (int) substr( $value, 0, 4 );
				$period_one_index[ $year ] = (int) ( $period_one_index[ $year ] ?? 0 ) + 1;
				if ( $period_one_index[ $year ] > $limit ) {
					$warnings['period_one_warning_dates'][] = $value;
				}
			}
		}

		$warnings['short_notice_warning_dates'] = array_values( array_unique( $warnings['short_notice_warning_dates'] ) );
		$warnings['period_one_warning_dates'] = array_values( array_unique( $warnings['period_one_warning_dates'] ) );
		sort( $warnings['short_notice_warning_dates'], SORT_STRING );
		sort( $warnings['period_one_warning_dates'], SORT_STRING );
		$warnings['policy_warning_dates'] = array_values( array_unique( array_merge( $warnings['short_notice_warning_dates'], $warnings['period_one_warning_dates'] ) ) );
		sort( $warnings['policy_warning_dates'], SORT_STRING );
		$warnings['requires_chief_approval'] = ! empty( $warnings['policy_warning_dates'] );
		return $warnings;
	}

	private function request_policy_warnings( int $request_id ): array {
		$map = $this->request_policy_warnings_map( array( $request_id ) );
		return $map[ $request_id ] ?? $this->empty_policy_warnings();
	}

	private function request_policy_warnings_map( array $request_ids ): array {
		global $wpdb;
		$request_ids = array_values( array_filter( array_map( 'absint', $request_ids ) ) );
		if ( ! $request_ids ) {
			return array();
		}

		$map = array();
		foreach ( $request_ids as $request_id ) {
			$map[ $request_id ] = $this->empty_policy_warnings();
		}

		$table = ELM_DB::table( 'request_days' );
		$placeholders = implode( ',', array_fill( 0, count( $request_ids ), '%d' ) );
		$sql = $wpdb->prepare(
			"SELECT request_id,leave_date,short_notice_warning,period_one_warning
				 FROM $table
				 WHERE request_id IN ($placeholders)
				 AND (short_notice_warning=1 OR period_one_warning=1)
				 ORDER BY leave_date ASC",
			$request_ids
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $rows ?: array() as $row ) {
			$request_id = (int) $row['request_id'];
			if ( ! isset( $map[ $request_id ] ) ) {
				continue;
			}
			$date = (string) $row['leave_date'];
			if ( ! empty( $row['short_notice_warning'] ) ) {
				$map[ $request_id ]['short_notice_warning_dates'][] = $date;
			}
			if ( ! empty( $row['period_one_warning'] ) ) {
				$map[ $request_id ]['period_one_warning_dates'][] = $date;
			}
			$map[ $request_id ]['policy_warning_dates'][] = $date;
		}

		foreach ( $map as &$warnings ) {
			$warnings['short_notice_warning_dates'] = array_values( array_unique( $warnings['short_notice_warning_dates'] ) );
			$warnings['period_one_warning_dates'] = array_values( array_unique( $warnings['period_one_warning_dates'] ) );
			$warnings['policy_warning_dates'] = array_values( array_unique( $warnings['policy_warning_dates'] ) );
			sort( $warnings['short_notice_warning_dates'], SORT_STRING );
			sort( $warnings['period_one_warning_dates'], SORT_STRING );
			sort( $warnings['policy_warning_dates'], SORT_STRING );
			$warnings['requires_chief_approval'] = ! empty( $warnings['policy_warning_dates'] );
		}
		unset( $warnings );
		return $map;
	}

	public function decide_request( int $request_id, string $decision, string $note, int $actor_id ): array|WP_Error {
		global $wpdb;
		$decision = sanitize_key( $decision );
		$note = sanitize_textarea_field( $note );
		if ( ! in_array( $decision, array( 'approved', 'rejected' ), true ) ) {
			return new WP_Error( 'elm_invalid_decision', __( 'Vendimi duhet të jetë miratim ose refuzim.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		if ( 'rejected' === $decision && '' === $note ) {
			return new WP_Error( 'elm_rejection_note_required', __( 'Shkruani arsyetimin e refuzimit.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}

		$requests = ELM_DB::table( 'requests' );
		$days_table = ELM_DB::table( 'request_days' );
		$capacity = ELM_DB::table( 'capacity' );
		$settings = ELM_Policy::settings();
		$limit = max( 1, (int) $settings['concurrency_limit'] );

		ELM_DB::begin();
		try {
			$request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $requests WHERE id=%d FOR UPDATE", $request_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $request ) {
				throw new DomainException( 'not_found' );
			}
			// Vendimi kërkon rolin menaxhues. Për kërkesën personale, një përdorues
			// me të drejtën elm_manage_leave mund të miratojë ose refuzojë kërkesën e vet.
			// Për punonjësit e tjerë vazhdon të kërkohet caktimi i drejtpërdrejtë.
			$employee_id = (int) $request['employee_id'];
			if ( $employee_id === $actor_id ) {
				if ( ! user_can( $actor_id, 'elm_manage_leave' ) && ! user_can( $actor_id, 'manage_options' ) ) {
					throw new DomainException( 'forbidden' );
				}
			} elseif ( ! user_can( $actor_id, 'manage_options' ) && ! ELM_Chief_Access::can_manage_employee( $actor_id, $employee_id ) ) {
				throw new DomainException( 'forbidden' );
			}
			if ( 'pending' !== $request['status'] ) {
				throw new DomainException( 'invalid_status' );
			}
			$policy_warnings = $this->request_policy_warnings( $request_id );
			if ( 'approved' === $decision && 'annual' === sanitize_key( (string) $request['leave_type'] ) ) {
				$start_date = (string) $request['start_date'];
				if ( ! ELM_Policy::annual_decision_is_timely( ELM_Policy::today(), $start_date ) ) {
					throw new DomainException( 'annual_decision_late' );
				}
			}

			$days = $wpdb->get_col( $wpdb->prepare( "SELECT leave_date FROM $days_table WHERE request_id=%d ORDER BY leave_date", $request_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 'approved' === $decision && 'annual' === sanitize_key( (string) $request['leave_type'] ) ) {
				$medical_overlap = $this->approved_medical_overlap( (int) $request['employee_id'], $days );
				if ( $medical_overlap ) {
					throw new DomainException( 'annual_overlaps_medical' );
				}
				foreach ( $days as $day ) {
					$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $capacity (leave_date,approved_count,updated_at) VALUES (%s,0,%s)", $day, ELM_DB::now() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
				if ( $days ) {
					$placeholders = implode( ',', array_fill( 0, count( $days ), '%s' ) );
					$locked = $wpdb->get_results( $wpdb->prepare( "SELECT leave_date,approved_count FROM $capacity WHERE leave_date IN ($placeholders) ORDER BY leave_date FOR UPDATE", $days ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$blocked = array();
					foreach ( $locked as $row ) {
						if ( (int) $row['approved_count'] >= $limit ) {
							$blocked[] = $row['leave_date'];
						}
					}
					if ( $blocked ) {
						throw new UnexpectedValueException( 'capacity:' . implode( ',', $blocked ) );
					}
					foreach ( $days as $day ) {
						$updated = $wpdb->query( $wpdb->prepare( "UPDATE $capacity SET approved_count=approved_count+1,updated_at=%s WHERE leave_date=%s AND approved_count<%d", ELM_DB::now(), $day, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						if ( 1 !== $updated ) {
							throw new UnexpectedValueException( 'capacity:' . $day );
						}
					}
				}
			}

			$updated = $wpdb->update(
				$requests,
				array(
					'status'        => $decision,
					'decided_at'    => ELM_DB::now(),
					'decided_by'    => $actor_id,
					'decision_note' => $note,
					'updated_at'    => ELM_DB::now(),
					'version'       => (int) $request['version'] + 1,
				),
				array( 'id' => $request_id, 'status' => 'pending' ),
				array( '%s', '%s', '%d', '%s', '%s', '%d' ),
				array( '%d', '%s' )
			);
			if ( 1 !== $updated ) {
				throw new RuntimeException( 'Status update failed.' );
			}
			$audit = ELM_Audit::append(
				'leave_request',
				$request_id,
				$decision,
				$actor_id,
				array(
					'from_status'                => 'pending',
					'to_status'                  => $decision,
					'decision_note'              => $note,
					'policy_exception_approved'  => 'approved' === $decision && ! empty( $policy_warnings['short_notice_warning_dates'] ),
					'policy_warning_dates'        => $policy_warnings['policy_warning_dates'],
				)
			);
			if ( is_wp_error( $audit ) ) {
				throw new RuntimeException( $audit->get_error_message() );
			}
			foreach ( $this->years_from_dates( $days ) as $year ) {
				$this->balances->sync_cache( (int) $request['employee_id'], $year );
			}
			ELM_DB::commit();
		} catch ( DomainException $e ) {
			ELM_DB::rollback();
			$errors = array(
				'not_found'      => array( 404, __( 'Kërkesa për pushim nuk u gjet.', 'employee-leave-manager' ) ),
				'forbidden'      => array( 403, __( 'Nuk keni leje të vendosni për këtë kërkesë. Punonjësi duhet të jetë i caktuar nën mbikëqyrjen tuaj të drejtpërdrejtë.', 'employee-leave-manager' ) ),
				'invalid_status' => array( 409, __( 'Vendimi mund të merret vetëm për kërkesat në pritje.', 'employee-leave-manager' ) ),
				'annual_decision_late' => array( 409, sprintf( __( 'Kjo kërkesë nuk mund të miratohet në këtë datë. Vendimi për pushimin vjetor duhet të lëshohet së paku %d ditë para fillimit.', 'employee-leave-manager' ), ELM_Policy::annual_decision_days() ) ),
				'annual_overlaps_medical' => array( 409, __( 'Kërkesa për pushim vjetor përmban një ose më shumë ditë që tashmë janë të mbuluara me pushim mjekësor të miratuar. Hiqni ato data para miratimit.', 'employee-leave-manager' ) ),
			);
			$error = $errors[ $e->getMessage() ] ?? array( 409, __( 'Nuk keni leje të vendosni për këtë kërkesë.', 'employee-leave-manager' ) );
			return new WP_Error( 'elm_' . $e->getMessage(), $error[1], array( 'status' => $error[0] ) );
		} catch ( UnexpectedValueException $e ) {
			ELM_DB::rollback();
			$dates = str_starts_with( $e->getMessage(), 'capacity:' ) ? explode( ',', substr( $e->getMessage(), 9 ) ) : array();
			return new WP_Error( 'elm_capacity_reached', __( 'Kërkesa nuk mund të miratohet sepse për një ose më shumë data është arritur kapaciteti ditor.', 'employee-leave-manager' ), array( 'status' => 409, 'dates' => $dates ) );
		} catch ( Throwable $e ) {
			ELM_DB::rollback();
			return new WP_Error( 'elm_decision_failed', __( 'Ruajtja e vendimit dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after a leave request is approved or rejected.
		 *
		 * @param int    $request_id Request id.
		 * @param string $decision   'approved' or 'rejected'.
		 */
		do_action( 'elm_request_decided', $request_id, $decision );

		return $this->get_request( $request_id );
	}

	public function cancel_request( int $request_id, int $actor_id, string $note = '' ): array|WP_Error {
		global $wpdb;
		$requests = ELM_DB::table( 'requests' );
		$days_table = ELM_DB::table( 'request_days' );
		$capacity = ELM_DB::table( 'capacity' );
		$note = sanitize_textarea_field( $note );
		if ( '' === $note ) {
			return new WP_Error( 'elm_cancel_reason_required', __( 'Shkruani arsyetimin e anulimit.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}

		ELM_DB::begin();
		try {
			$request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $requests WHERE id=%d FOR UPDATE", $request_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $request ) {
				throw new DomainException( 'not_found' );
			}
			$employee_id = (int) $request['employee_id'];
			if ( $employee_id !== $actor_id && ! user_can( $actor_id, 'manage_options' ) && ! ELM_Chief_Access::can_manage_employee( $actor_id, $employee_id ) ) {
				throw new DomainException( 'forbidden' );
			}
			if ( ! in_array( $request['status'], array( 'pending', 'approved' ), true ) ) {
				throw new DomainException( 'invalid_status' );
			}
			$days = $wpdb->get_col( $wpdb->prepare( "SELECT leave_date FROM $days_table WHERE request_id=%d ORDER BY leave_date", $request_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 'approved' === $request['status'] && 'annual' === sanitize_key( (string) $request['leave_type'] ) ) {
				if ( $days ) {
					$placeholders = implode( ',', array_fill( 0, count( $days ), '%s' ) );
					$wpdb->get_results( $wpdb->prepare( "SELECT leave_date FROM $capacity WHERE leave_date IN ($placeholders) ORDER BY leave_date FOR UPDATE", $days ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					foreach ( $days as $day ) {
						$wpdb->query( $wpdb->prepare( "UPDATE $capacity SET approved_count=GREATEST(approved_count-1,0),updated_at=%s WHERE leave_date=%s", ELM_DB::now(), $day ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					}
				}
			}

			$from_status = $request['status'];
			$updated = $wpdb->update(
				$requests,
				array(
					'status'       => 'cancelled',
					'cancelled_at' => ELM_DB::now(),
					'cancelled_by' => $actor_id,
					'cancel_reason' => $note,
					'updated_at'   => ELM_DB::now(),
					'version'      => (int) $request['version'] + 1,
				),
				array( 'id' => $request_id ),
				array( '%s', '%s', '%d', '%s', '%s', '%d' ),
				array( '%d' )
			);
			if ( 1 !== $updated ) {
				throw new RuntimeException( 'Cancel update failed.' );
			}
			$audit = ELM_Audit::append( 'leave_request', $request_id, 'cancelled', $actor_id, array( 'from_status' => $from_status, 'to_status' => 'cancelled', 'note' => $note ) );
			if ( is_wp_error( $audit ) ) {
				throw new RuntimeException( $audit->get_error_message() );
			}
			foreach ( $this->years_from_dates( $days ) as $year ) {
				$this->balances->sync_cache( (int) $request['employee_id'], $year );
			}
			ELM_DB::commit();
		} catch ( DomainException $e ) {
			ELM_DB::rollback();
			$map = array(
				'not_found'      => array( 404, __( 'Kërkesa për pushim nuk u gjet.', 'employee-leave-manager' ) ),
				'forbidden'      => array( 403, __( 'Nuk keni leje ta anuloni këtë kërkesë.', 'employee-leave-manager' ) ),
				'invalid_status' => array( 409, __( 'Kjo kërkesë nuk mund të anulohet më.', 'employee-leave-manager' ) ),
			);
			return new WP_Error( 'elm_' . $e->getMessage(), $map[ $e->getMessage() ][1] ?? __( 'Anulimi dështoi.', 'employee-leave-manager' ), array( 'status' => $map[ $e->getMessage() ][0] ?? 409 ) );
		} catch ( Throwable $e ) {
			ELM_DB::rollback();
			return new WP_Error( 'elm_cancel_failed', __( 'Anulimi i kërkesës dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after a leave request is cancelled.
		 *
		 * @param int $request_id Request id.
		 * @param int $actor_id   User who cancelled it.
		 */
		do_action( 'elm_request_cancelled', $request_id, $actor_id );

		return $this->get_request( $request_id );
	}

	public function delete_request( int $request_id, int $actor_id ): array|WP_Error {
		global $wpdb;
		if ( ! user_can( $actor_id, 'manage_options' ) && ! user_can( $actor_id, 'elm_adjust_balances' ) && ! user_can( $actor_id, 'elm_manage_leave' ) ) {
			return new WP_Error( 'elm_forbidden', __( 'Vetëm mbikëqyrësi i drejtpërdrejtë ose administratori mund ta fshijë përgjithmonë një kërkesë për pushim.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		if ( ! ELM_DB::schema_ready() ) {
			return new WP_Error( 'elm_database_unavailable', __( 'Baza e të dhënave të pushimeve nuk është e gatshme.', 'employee-leave-manager' ), array( 'status' => 503 ) );
		}

		$requests = ELM_DB::table( 'requests' );
		$days_table = ELM_DB::table( 'request_days' );
		$capacity = ELM_DB::table( 'capacity' );
		$document_id = 0;
		$employee_id = 0;
		$leave_years = array();

		ELM_DB::begin();
		try {
			$request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $requests WHERE id=%d FOR UPDATE", $request_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $request ) {
				throw new DomainException( 'not_found' );
			}
			$employee_id = (int) $request['employee_id'];
			if ( $employee_id !== $actor_id && ! user_can( $actor_id, 'manage_options' ) && ! ELM_Chief_Access::can_manage_employee( $actor_id, $employee_id ) ) {
				throw new DomainException( 'forbidden' );
			}
			// Approved leave is an authoritative HR record. It may be cancelled/reversed,
			// but it must not be physically deleted because deletion would restore the
			// balance while erasing the source record. Self-approval remains available
			// for managers as configured by this plugin's workflow.
			if ( 'approved' === sanitize_key( (string) $request['status'] ) ) {
				throw new DomainException( 'approved_must_cancel' );
			}
			$document_id = (int) ( $request['medical_document_id'] ?? 0 );
			$days = $wpdb->get_col( $wpdb->prepare( "SELECT leave_date FROM $days_table WHERE request_id=%d ORDER BY leave_date", $request_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$leave_years = $this->years_from_dates( $days );

			if ( 'approved' === $request['status'] && 'annual' === sanitize_key( (string) $request['leave_type'] ) && $days ) {
				$placeholders = implode( ',', array_fill( 0, count( $days ), '%s' ) );
				$wpdb->get_results( $wpdb->prepare( "SELECT leave_date FROM $capacity WHERE leave_date IN ($placeholders) ORDER BY leave_date FOR UPDATE", $days ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				foreach ( $days as $day ) {
					$result = $wpdb->query( $wpdb->prepare( "UPDATE $capacity SET approved_count=GREATEST(approved_count-1,0),updated_at=%s WHERE leave_date=%s", ELM_DB::now(), $day ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					if ( false === $result ) {
						throw new RuntimeException( 'Capacity update failed.' );
					}
				}
			}

			$audit = ELM_Audit::append(
				'leave_request',
				$request_id,
				'deleted',
				$actor_id,
				array(
					'employee_id' => $employee_id,
					'leave_type'  => $request['leave_type'],
					'status'      => $request['status'],
					'dates'       => $days,
				)
			);
			if ( is_wp_error( $audit ) ) {
				throw new RuntimeException( $audit->get_error_message() );
			}

			$deleted_days = $wpdb->delete( $days_table, array( 'request_id' => $request_id ), array( '%d' ) );
			if ( false === $deleted_days ) {
				throw new RuntimeException( 'Request-day deletion failed.' );
			}
			$deleted_request = $wpdb->delete( $requests, array( 'id' => $request_id ), array( '%d' ) );
			if ( 1 !== $deleted_request ) {
				throw new RuntimeException( 'Request deletion failed.' );
			}

			foreach ( $leave_years as $year ) {
				$this->balances->sync_cache( $employee_id, $year );
			}
			ELM_DB::commit();
		} catch ( DomainException $e ) {
			ELM_DB::rollback();
			if ( 'forbidden' === $e->getMessage() ) {
				return new WP_Error( 'elm_forbidden', __( 'Nuk keni leje ta fshini këtë kërkesë. Punonjësi duhet të jetë i caktuar nën mbikëqyrjen tuaj të drejtpërdrejtë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
			}
			if ( 'approved_must_cancel' === $e->getMessage() ) {
				return new WP_Error( 'elm_delete_approved_forbidden', __( 'Kërkesa e miratuar nuk mund të fshihet përgjithmonë. Anulojeni fillimisht që ndryshimi të ruhet në historik dhe në auditim.', 'employee-leave-manager' ), array( 'status' => 409 ) );
			}
			return new WP_Error( 'elm_not_found', __( 'Kërkesa për pushim nuk u gjet.', 'employee-leave-manager' ), array( 'status' => 404 ) );
		} catch ( Throwable $e ) {
			ELM_DB::rollback();
			ELM_DB::log_failure( 'Deleting leave request', $e );
			return new WP_Error( 'elm_delete_failed', __( 'Fshirja e kërkesës për pushim dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}

		if ( $document_id ) {
			ELM_Medical_Storage::purge_if_unlinked( $document_id, $employee_id );
		}
		return array( 'id' => $request_id, 'deleted' => true );
	}

	public function add_adjustment( int $user_id, int $year, float $amount, string $note, int $actor_id ): array|WP_Error {
		global $wpdb;
		if ( ! ELM_DB::schema_ready() ) {
			return new WP_Error( 'elm_database_unavailable', __( 'Baza e të dhënave të pushimeve nuk është e gatshme. Kontaktoni administratorin për ta riaktivizuar shtojcën.', 'employee-leave-manager' ), array( 'status' => 503 ) );
		}
		$note = sanitize_textarea_field( $note );
		if ( ! user_can( $actor_id, 'manage_options' ) && ! user_can( $actor_id, 'elm_adjust_balances' ) ) {
			return new WP_Error( 'elm_forbidden', __( 'Nuk keni leje të regjistroni ditë shtesë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'elm_user_not_found', __( 'Punonjësi nuk u gjet.', 'employee-leave-manager' ), array( 'status' => 404 ) );
		}
		if ( ! user_can( $actor_id, 'manage_options' ) && ! ELM_Chief_Access::can_manage_employee( $actor_id, $user_id ) ) {
			return new WP_Error( 'elm_forbidden', __( 'Ky punonjës nuk është i caktuar nën mbikëqyrjen tuaj të drejtpërdrejtë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		if ( $year < 2000 || $year > 2100 ) {
			return new WP_Error( 'elm_invalid_year', __( 'Zgjidhni një vit të vlefshëm të pushimit.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		if ( $amount <= 0 || abs( $amount - round( $amount ) ) > 0.0001 ) {
			return new WP_Error( 'elm_invalid_adjustment', __( 'Ditët shtesë duhet të jenë ditë të plota me vlerë pozitive.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		$amount = (float) round( $amount );
		if ( '' === $note ) {
			return new WP_Error( 'elm_adjustment_note_required', __( 'Kërkohet arsyetimi.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}

		$table = ELM_DB::table( 'adjustments' );
		ELM_DB::begin();
		try {
			$ok = $wpdb->insert(
				$table,
				array( 'user_id' => $user_id, 'leave_year' => $year, 'amount' => $amount, 'note' => $note, 'created_by' => $actor_id, 'created_at' => ELM_DB::now() ),
				array( '%d', '%d', '%f', '%s', '%d', '%s' )
			);
			if ( false === $ok ) {
				throw new RuntimeException( 'Adjustment insert failed.' );
			}
			$id = (int) $wpdb->insert_id;
			$audit = ELM_Audit::append( 'balance_adjustment', $id, 'created', $actor_id, array( 'user_id' => $user_id, 'leave_year' => $year, 'amount' => $amount, 'note' => $note ) );
			if ( is_wp_error( $audit ) ) {
				throw new RuntimeException( $audit->get_error_message() );
			}
			$this->balances->sync_cache( $user_id, $year );
			ELM_DB::commit();
		} catch ( Throwable $e ) {
			ELM_DB::rollback();
			ELM_DB::log_failure( 'Saving balance extension', $e );
			return new WP_Error( 'elm_adjustment_failed', __( 'Ruajtja e ditëve shtesë dështoi. Gabimi u regjistrua.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}
		return array( 'id' => $id, 'user_id' => $user_id, 'year' => $year, 'amount' => $amount, 'note' => $note, 'balance' => $this->balances->summary( $user_id, $year ) );
	}

	public function list_adjustments( array $args = array() ): array {
		global $wpdb;
		$table = ELM_DB::table( 'adjustments' );
		$where = array( '1=1' );
		$params = array();
		if ( ! empty( $args['user_id'] ) ) {
			$where[] = 'user_id=%d';
			$params[] = absint( $args['user_id'] );
		}
		if ( ! empty( $args['year'] ) ) {
			$where[] = 'leave_year=%d';
			$params[] = absint( $args['year'] );
		}
		$sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT 500';
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $rows as &$row ) {
			$user = get_userdata( (int) $row['user_id'] );
			$actor = get_userdata( (int) $row['created_by'] );
			$row['id'] = (int) $row['id'];
			$row['user_id'] = (int) $row['user_id'];
			$row['amount'] = (float) $row['amount'];
			$row['employee_name'] = $user ? $user->display_name : __( 'Përdorues i fshirë', 'employee-leave-manager' );
			$row['created_by_name'] = $actor ? $actor->display_name : __( 'Përdorues i fshirë', 'employee-leave-manager' );
		}
		return $rows ?: array();
	}

	public function availability( string $start, string $end ): array|WP_Error {
		$dates = ELM_Policy::dates_for_request( $start, $end, 1.0 );
		if ( is_wp_error( $dates ) ) {
			return $dates;
		}
		$date_values = wp_list_pluck( $dates, 'date' );
		return array(
			'start_date' => $start,
			'end_date'   => $end,
			'working_days' => count( $date_values ),
			'blocked_dates' => $this->blocked_dates( $date_values ),
			'pending_dates' => $this->pending_dates( $date_values ),
		);
	}

	public function calendar( string $month, int $employee_id = 0 ): array|WP_Error {
		global $wpdb;
		if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) {
			return new WP_Error( 'elm_invalid_month', __( 'Shkruani muajin në formatin YYYY-MM.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		$timezone = wp_timezone();
		$first_day = new DateTimeImmutable( $month . '-01', $timezone );
		$last_day = $first_day->modify( 'last day of this month' );
		$start = $first_day->format( 'Y-m-d' );
		$end = $last_day->format( 'Y-m-d' );
		$requests = ELM_DB::table( 'requests' );
		$days = ELM_DB::table( 'request_days' );
		$capacity = ELM_DB::table( 'capacity' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.leave_date,
				 COALESCE(c.approved_count,0) approved_count,
				 COUNT(DISTINCT CASE WHEN r.status='pending' THEN r.id END) pending_count
				 FROM $days d
				 INNER JOIN $requests r ON r.id=d.request_id
				 LEFT JOIN $capacity c ON c.leave_date=d.leave_date
				 WHERE d.leave_date BETWEEN %s AND %s AND r.status IN ('pending','approved')
				 GROUP BY d.leave_date,c.approved_count ORDER BY d.leave_date",
				$start,
				$end
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$own_days = array();
		if ( $employee_id > 0 ) {
			$status_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT d.leave_date,r.status,r.leave_type,r.submitted_at,r.id
					 FROM $days d
					 INNER JOIN $requests r ON r.id=d.request_id
					 WHERE r.employee_id=%d AND d.leave_date BETWEEN %s AND %s
					 ORDER BY d.leave_date ASC,r.submitted_at DESC,r.id DESC",
					$employee_id,
					$start,
					$end
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $status_rows ?: array() as $row ) {
				$date = (string) $row['leave_date'];
				if ( ! isset( $own_days[ $date ] ) ) {
					$status       = sanitize_key( (string) $row['status'] );
					$is_cancelled = 'cancelled' === $status;

					// A cancelled request remains in history, but its dates return to ordinary calendar days.
					$own_days[ $date ] = array(
						'status'         => $is_cancelled ? '' : $status,
						'leave_type'     => $is_cancelled ? '' : sanitize_key( (string) ( $row['leave_type'] ?? '' ) ),
						'policy_warning' => false,
					);
				}
			}
		}

		$settings = ELM_Policy::settings();
		$limit = max( 1, (int) $settings['concurrency_limit'] );
		$weekdays = array_map( 'intval', (array) $settings['working_weekdays'] );
		$holiday_names = ELM_Policy::effective_holiday_map( $settings );
		$holidays = array_flip( array_keys( $holiday_names ) );
		$counts = array();
		foreach ( $rows ?: array() as $row ) {
			$counts[ $row['leave_date'] ] = array(
				'approved' => (int) $row['approved_count'],
				'pending'  => (int) $row['pending_count'],
			);
		}

		$today = new DateTimeImmutable( ELM_Policy::today(), $timezone );
		$notice_end = $today->modify( '+14 days' )->format( 'Y-m-d' );
		$data = array();
		for ( $cursor = $first_day; $cursor <= $last_day; $cursor = $cursor->modify( '+1 day' ) ) {
			$date = $cursor->format( 'Y-m-d' );
			$approved = (int) ( $counts[ $date ]['approved'] ?? 0 );
			$pending = (int) ( $counts[ $date ]['pending'] ?? 0 );
			$is_holiday = isset( $holidays[ $date ] );
			$holiday_name = $is_holiday ? (string) ( $holiday_names[ $date ] ?? '' ) : '';
			$is_working_day = in_array( (int) $cursor->format( 'N' ), $weekdays, true ) && ! $is_holiday;
			$is_full = $approved >= $limit;
			$own_day = $own_days[ $date ] ?? array();
			$own_status = sanitize_key( (string) ( $own_day['status'] ?? '' ) );
			$own_leave_type = sanitize_key( (string) ( $own_day['leave_type'] ?? '' ) );
			$data[ $date ] = array(
				'approved'         => $approved,
				'pending'          => $pending,
				'traffic'          => $is_full ? 'red' : ( $approved > 0 ? 'yellow' : 'green' ),
				'disabled'         => $is_full,
				'capacity_blocked' => $is_full,
				'working_day'      => $is_working_day,
				'holiday'          => $is_holiday,
				'holiday_name'     => $holiday_name,
				'short_notice'     => $date >= $today->format( 'Y-m-d' ) && $date <= $notice_end,
				'own_status'       => in_array( $own_status, array( 'pending', 'approved', 'rejected', 'cancelled' ), true ) ? $own_status : '',
				'own_leave_type'    => in_array( $own_leave_type, array( 'annual', 'medical' ), true ) ? $own_leave_type : '',
				'own_policy_warning'=> ! empty( $own_day['policy_warning'] ),
				'disabled_reason'  => $is_full ? __( 'Është arritur kapaciteti ditor i miratimeve.', 'employee-leave-manager' ) : '',
				'day_note'         => $is_holiday ? ( $holiday_name ? sprintf( __( 'Festë: %s. Nuk llogaritet si ditë pushimi.', 'employee-leave-manager' ), $holiday_name ) : __( 'Festë e institucionit; nuk llogaritet si ditë pushimi.', 'employee-leave-manager' ) ) : ( $is_working_day ? '' : __( 'Ditë jopune; nuk llogaritet si ditë pushimi.', 'employee-leave-manager' ) ),
			);
		}
		return array( 'month' => $month, 'capacity_limit' => $limit, 'notice_end' => $notice_end, 'days' => $data );
	}

	private function blocked_dates( array $dates ): array {
		global $wpdb;
		if ( ! $dates ) {
			return array();
		}
		$capacity = ELM_DB::table( 'capacity' );
		$limit = max( 1, (int) ELM_Policy::settings()['concurrency_limit'] );
		$placeholders = implode( ',', array_fill( 0, count( $dates ), '%s' ) );
		$params = array_merge( array( $limit ), $dates );
		return $wpdb->get_col( $wpdb->prepare( "SELECT leave_date FROM $capacity WHERE approved_count >= %d AND leave_date IN ($placeholders)", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function employee_overlap( int $employee_id, array $dates, int $exclude_request_id = 0, string $request_type = '' ): array {
		global $wpdb;
		if ( ! $dates ) {
			return array();
		}
		$requests = ELM_DB::table( 'requests' );
		$days = ELM_DB::table( 'request_days' );
		$placeholders = implode( ',', array_fill( 0, count( $dates ), '%s' ) );
		$exclude_sql = $exclude_request_id > 0 ? ' AND r.id<>%d' : '';
		// Neni 9(5): pushimi mjekësor i lejuar gjatë pushimit vjetor nuk llogaritet në pushim vjetor.
		// Prandaj një kërkesë mjekësore mund të mbivendoset me pushimin vjetor, por jo me një kërkesë tjetër mjekësore aktive.
		$type_sql = 'medical' === sanitize_key( $request_type ) ? " AND r.leave_type='medical'" : '';
		$params = array_merge( array( $employee_id ), $dates );
		if ( $exclude_request_id > 0 ) {
			$params[] = $exclude_request_id;
		}
		return $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT d.leave_date FROM $days d INNER JOIN $requests r ON r.id=d.request_id WHERE r.employee_id=%d AND r.status IN ('pending','approved') AND d.leave_date IN ($placeholders)$type_sql$exclude_sql", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function approved_medical_overlap( int $employee_id, array $dates ): array {
		global $wpdb;
		$dates = array_values( array_filter( array_map( 'strval', $dates ) ) );
		if ( ! $dates ) {
			return array();
		}
		$requests = ELM_DB::table( 'requests' );
		$days = ELM_DB::table( 'request_days' );
		$placeholders = implode( ',', array_fill( 0, count( $dates ), '%s' ) );
		$params = array_merge( array( $employee_id ), $dates );
		return $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT d.leave_date FROM $days d INNER JOIN $requests r ON r.id=d.request_id WHERE r.employee_id=%d AND r.leave_type='medical' AND r.status='approved' AND d.leave_date IN ($placeholders)", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function pending_dates( array $dates, int $exclude_request_id = 0 ): array {
		global $wpdb;
		if ( ! $dates ) {
			return array();
		}
		$requests = ELM_DB::table( 'requests' );
		$days = ELM_DB::table( 'request_days' );
		$placeholders = implode( ',', array_fill( 0, count( $dates ), '%s' ) );
		$exclude_sql = $exclude_request_id > 0 ? ' AND r.id<>%d' : '';
		$params = $dates;
		if ( $exclude_request_id > 0 ) {
			$params[] = $exclude_request_id;
		}
		return $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT d.leave_date FROM $days d INNER JOIN $requests r ON r.id=d.request_id WHERE r.status='pending' AND d.leave_date IN ($placeholders)$exclude_sql", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
