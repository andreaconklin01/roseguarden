<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Employee-specific +1 annual-leave day workflow for verified work-experience increments.
 *
 * Requests are append-only HR records. A pending proposal has no effect on the
 * balance. Once approved by a Leave Chief/administrator, the approved value is
 * used as the employee's standard annual entitlement from the effective year
 * onward. Later approved changes supersede earlier values only for their own
 * effective year and subsequent years.
 */
final class ELM_Entitlement_Service {
	private const GLOBAL_REQUEST_ACCESS_OPTION = 'elm_entitlement_requests_enabled';

	public static function employee_request_enabled( int $user_id = 0, int $year = 0 ): bool {
		// The simplified portal always allows employees to request +1 day.
		// Only one request may remain pending at a time; after a decision,
		// another +1-day request can be submitted if needed.
		return true;
	}

	/**
	 * Kept for response compatibility with earlier portal builds.
	 * Global access does not create per-employee reopen states.
	 */
	public static function employee_request_reopened( int $user_id, int $year ): bool {
		return false;
	}

	public static function can_employee_create_for_year( int $user_id, int $year ): bool {
		if ( $user_id <= 0 || $year <= 0 || ! self::employee_request_enabled() ) {
			return false;
		}
		global $wpdb;
		$table = ELM_DB::table( 'entitlement_changes' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE user_id=%d AND effective_year=%d AND status='pending' ORDER BY id DESC LIMIT 1", $user_id, $year ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return ! $existing;
	}

	public static function global_request_enabled(): bool {
		return self::employee_request_enabled();
	}

	public function save_global_access( bool $enabled, int $actor_id ): bool|WP_Error {
		if ( ! user_can( $actor_id, 'elm_adjust_balances' ) && ! user_can( $actor_id, 'manage_options' ) ) {
			return new WP_Error( 'elm_forbidden', __( 'Nuk keni leje ta ndryshoni këtë cilësim.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		// Kept only for compatibility with older clients. In the simplified
		// workflow the +1-day request is always available to employees.
		delete_option( self::GLOBAL_REQUEST_ACCESS_OPTION );
		return true;
	}

	public function change_for_year( int $user_id, int $year, int $viewer_id ): ?array {
		$rows = $this->list( array( 'user_id' => $user_id, 'effective_year' => $year ), $viewer_id );
		return $rows ? $rows[0] : null;
	}

	public static function entitlement_for( int $user_id, int $year ): int {
		$default = (int) round( (float) ( ELM_Policy::settings()['annual_entitlement'] ?? 20 ) );
		if ( $user_id <= 0 || $year < 2000 || ! ELM_DB::schema_ready() ) {
			return $default;
		}

		global $wpdb;
		$table = ELM_DB::table( 'entitlement_changes' );
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT requested_entitlement
				 FROM $table
				 WHERE user_id=%d AND status='approved' AND effective_year<=%d
				 ORDER BY effective_year DESC, decided_at DESC, id DESC
				 LIMIT 1",
				$user_id,
				$year
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return null === $value ? $default : max( 1, (int) round( (float) $value ) );
	}

	public function create( int $user_id, int $requested_entitlement, int $effective_year, string $reason, int $actor_id ): array|WP_Error {
		global $wpdb;
		if ( ! ELM_DB::schema_ready() ) {
			return new WP_Error( 'elm_database_unavailable', __( 'Baza e të dhënave të pushimeve nuk është e gatshme. Kontaktoni administratorin për ta riaktivizuar shtojcën.', 'employee-leave-manager' ), array( 'status' => 503 ) );
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'elm_user_not_found', __( 'Punonjësi nuk u gjet.', 'employee-leave-manager' ), array( 'status' => 404 ) );
		}
		if ( $actor_id !== $user_id && ! user_can( $actor_id, 'manage_options' ) && ! ELM_Chief_Access::can_manage_employee( $actor_id, $user_id ) ) {
			return new WP_Error( 'elm_forbidden', __( 'Nuk keni leje të paraqitni kërkesë për +1 ditë për këtë punonjës. Punonjësi duhet të jetë i caktuar nën mbikëqyrjen tuaj të drejtpërdrejtë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		if ( $actor_id === $user_id && ! self::global_request_enabled() ) {
			return new WP_Error( 'elm_entitlement_requests_disabled', __( 'Kërkesat për njohjen e +1 dite të pushimit vjetor për çdo pesë vjet të përvojës së punës janë aktualisht të çaktivizuara për të gjithë punonjësit. Kontaktoni mbikëqyrësin e drejtpërdrejtë nëse nevojitet ndryshim.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		$current_year = (int) current_datetime()->format( 'Y' );
		if ( $actor_id === $user_id && $effective_year !== $current_year ) {
			return new WP_Error( 'elm_entitlement_current_year_only', __( 'Kërkesa për +1 ditë për përvojë pune mund të paraqitet vetëm për vitin aktual të pushimit.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		if ( $effective_year < $current_year || $effective_year > 2100 ) {
			return new WP_Error( 'elm_invalid_year', __( 'Viti i hyrjes në fuqi duhet të jetë viti aktual i pushimit ose një vit i ardhshëm.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		$reason = sanitize_textarea_field( $reason );
		if ( '' === $reason ) {
			return new WP_Error( 'elm_entitlement_reason_required', __( 'Shkruani arsyetimin dhe tregoni pragun e përvojës së punës që keni plotësuar (p.sh. 5, 10 ose 15 vjet).', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		$current = self::entitlement_for( $user_id, $effective_year );
		$requested_entitlement = $current + 1;
		if ( $requested_entitlement < 1 || $requested_entitlement > 365 ) {
			return new WP_Error( 'elm_invalid_entitlement', __( 'Numri i ditëve të pushimit duhet të jetë nga 1 deri në 365.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		if ( $requested_entitlement === $current ) {
			return new WP_Error( 'elm_entitlement_unchanged', __( 'Numri i kërkuar i ditëve të pushimit është tashmë i njëjtë me numrin e caktuar për punonjësin në atë vit.', 'employee-leave-manager' ), array( 'status' => 409 ) );
		}

		$table = ELM_DB::table( 'entitlement_changes' );

		// A plain pre-check-then-insert has a race window: two concurrent requests
		// for the same (user_id, effective_year) can both see "no pending row" and
		// both insert. Serialize with a named lock scoped to that pair so only one
		// request at a time evaluates the check and performs the insert.
		$lock_name = self::entitlement_lock_name( $user_id, $effective_year );
		$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 5 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( 1 !== $locked ) {
			return new WP_Error( 'elm_entitlement_lock_failed', __( 'Kërkesa juaj për +1 ditë po përpunohet. Provoni përsëri.', 'employee-leave-manager' ), array( 'status' => 503 ) );
		}

		try {
			$existing = $wpdb->get_row(
				$wpdb->prepare( "SELECT id,status FROM $table WHERE user_id=%d AND effective_year=%d AND status='pending' ORDER BY id DESC LIMIT 1", $user_id, $effective_year ),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $existing ) {
				return new WP_Error(
					'elm_entitlement_pending_exists',
					__( 'Keni tashmë një kërkesë për +1 ditë në pritje. Prisni vendimin e udhëheqësit para se të paraqitni një tjetër.', 'employee-leave-manager' ),
					array( 'status' => 409, 'request_id' => (int) $existing['id'], 'request_status' => 'pending' )
				);
			}

			ELM_DB::begin();
			try {
				$now = ELM_DB::now();
				$ok = $wpdb->insert(
					$table,
					array(
						'user_id'               => $user_id,
						'current_entitlement'    => $current,
						'requested_entitlement'  => $requested_entitlement,
						'effective_year'         => $effective_year,
						'reason'                 => $reason,
						'status'                 => 'pending',
						'requested_by'           => $actor_id,
						'requested_at'           => $now,
						'updated_at'             => $now,
						'version'                => 1,
					),
					array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%d' )
				);
				if ( false === $ok ) {
					throw new RuntimeException( 'Entitlement request insert failed.' );
				}
				$id = (int) $wpdb->insert_id;
				$audit = ELM_Audit::append(
					'entitlement_change',
					$id,
					'created',
					$actor_id,
					array(
						'user_id'              => $user_id,
						'current_entitlement'   => $current,
						'requested_entitlement' => $requested_entitlement,
						'effective_year'        => $effective_year,
						'reason'                => $reason,
					)
				);
				if ( is_wp_error( $audit ) ) {
					throw new RuntimeException( $audit->get_error_message() );
				}
				ELM_DB::commit();
			} catch ( Throwable $e ) {
				ELM_DB::rollback();
				ELM_DB::log_failure( 'Creating permanent entitlement request', $e );
				return new WP_Error( 'elm_entitlement_create_failed', __( 'Ruajtja e kërkesës për +1 ditë dështoi. Provoni përsëri.', 'employee-leave-manager' ), array( 'status' => 500 ) );
			}
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		/**
		 * Fires after a +1-day entitlement change request is stored.
		 *
		 * @param int $id       Entitlement change id.
		 * @param int $actor_id User who submitted the request.
		 */
		do_action( 'elm_entitlement_created', $id, $actor_id );

		return $this->get( $id, $actor_id );
	}

	private static function entitlement_lock_name( int $user_id, int $effective_year ): string {
		global $wpdb;
		return 'elm_ent_' . substr( hash( 'sha256', $wpdb->prefix . DB_NAME . '_' . $user_id . '_' . $effective_year ), 0, 40 );
	}

	public function decide( int $id, string $decision, string $note, int $actor_id ): array|WP_Error {
		global $wpdb;
		if ( ! user_can( $actor_id, 'elm_adjust_balances' ) && ! user_can( $actor_id, 'manage_options' ) ) {
			return new WP_Error( 'elm_forbidden', __( 'Vetëm mbikëqyrësi i autorizuar ose administratori mund t\'i miratojë ndryshimet e numrit vjetor të ditëve të pushimit.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		$precheck = $this->get( $id, $actor_id );
		if ( ! is_wp_error( $precheck ) && (int) $precheck['user_id'] === $actor_id ) {
			return new WP_Error( 'elm_self_approval_forbidden', __( 'Mbikëqyrësi i drejtpërdrejtë nuk mund ta miratojë ose refuzojë kërkesën e vet për +1 ditë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		if ( ! in_array( $decision, array( 'approved', 'rejected' ), true ) ) {
			return new WP_Error( 'elm_invalid_decision', __( 'Zgjidhni miratim ose refuzim.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		$note = sanitize_textarea_field( $note );
		if ( 'rejected' === $decision && '' === $note ) {
			return new WP_Error( 'elm_decision_note_required', __( 'Shkruani arsyetimin e refuzimit.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		$table = ELM_DB::table( 'entitlement_changes' );
		$balances = ELM_DB::table( 'balances' );

		ELM_DB::begin();
		try {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d FOR UPDATE", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $row ) {
				throw new DomainException( 'not_found' );
			}
			if ( (int) $row['user_id'] === $actor_id ) {
				throw new DomainException( 'self_approval_forbidden' );
			}
			if ( ! user_can( $actor_id, 'manage_options' ) && ! ELM_Chief_Access::can_manage_employee( $actor_id, (int) $row['user_id'] ) ) {
				throw new DomainException( 'forbidden' );
			}
			if ( 'pending' !== $row['status'] ) {
				throw new DomainException( 'not_pending' );
			}
			if ( 'approved' === $decision ) {
				$requests = ELM_DB::table( 'requests' );
				$request_days = ELM_DB::table( 'request_days' );
				$years = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT YEAR(d.leave_date)
						 FROM $request_days d INNER JOIN $requests r ON r.id=d.request_id
						 WHERE r.employee_id=%d AND r.leave_type='annual' AND r.status IN ('pending','approved')
						 AND YEAR(d.leave_date)>=%d",
						(int) $row['user_id'],
						(int) $row['effective_year']
					)
				); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$balance_service = new ELM_Balance_Service();
				foreach ( array_map( 'intval', $years ) as $leave_year ) {
					$summary = $balance_service->summary( (int) $row['user_id'], $leave_year );
					$available_under_change = (int) $row['requested_entitlement'] + (int) ( $summary['manual_added'] ?? 0 );
					if ( (int) ( $summary['committed_units'] ?? 0 ) > $available_under_change ) {
						throw new DomainException( 'committed_exceeds_new_entitlement:' . $leave_year );
					}
				}
			}
			$now = ELM_DB::now();
			$ok = $wpdb->update(
				$table,
				array(
					'status'        => $decision,
					'decision_note' => $note,
					'decided_by'    => $actor_id,
					'decided_at'    => $now,
					'updated_at'    => $now,
					'version'       => (int) $row['version'] + 1,
				),
				array( 'id' => $id, 'status' => 'pending' ),
				array( '%s', '%s', '%d', '%s', '%s', '%d' ),
				array( '%d', '%s' )
			);
			if ( false === $ok || 0 === $ok ) {
				throw new RuntimeException( 'Entitlement decision update failed.' );
			}
			if ( 'approved' === $decision ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE $balances SET standard_entitlement=%d,updated_at=%s WHERE user_id=%d AND leave_year>=%d",
						(int) $row['requested_entitlement'],
						$now,
						(int) $row['user_id'],
						(int) $row['effective_year']
					)
				); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			$audit = ELM_Audit::append(
				'entitlement_change',
				$id,
				$decision,
				$actor_id,
				array(
					'user_id'              => (int) $row['user_id'],
					'requested_entitlement' => (int) $row['requested_entitlement'],
					'effective_year'        => (int) $row['effective_year'],
					'note'                  => $note,
					'global_self_service_enabled' => self::global_request_enabled(),
				)
			);
			if ( is_wp_error( $audit ) ) {
				throw new RuntimeException( $audit->get_error_message() );
			}
			ELM_DB::commit();
		} catch ( DomainException $e ) {
			ELM_DB::rollback();
			if ( 'not_found' === $e->getMessage() ) {
				return new WP_Error( 'elm_not_found', __( 'Kërkesa për +1 ditë nuk u gjet.', 'employee-leave-manager' ), array( 'status' => 404 ) );
			}
			if ( 'self_approval_forbidden' === $e->getMessage() ) {
				return new WP_Error( 'elm_self_approval_forbidden', __( 'Udhëheqësi nuk mund ta miratojë ose refuzojë kërkesën e vet për +1 ditë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
			}
			if ( 'forbidden' === $e->getMessage() ) {
				return new WP_Error( 'elm_forbidden', __( 'Nuk keni leje të vendosni për këtë kërkesë për +1 ditë. Punonjësi duhet të jetë i caktuar nën mbikëqyrjen tuaj të drejtpërdrejtë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
			}
			if ( str_starts_with( $e->getMessage(), 'committed_exceeds_new_entitlement:' ) ) {
				$year = absint( substr( $e->getMessage(), strrpos( $e->getMessage(), ':' ) + 1 ) );
				return new WP_Error(
					'elm_entitlement_below_committed',
					sprintf( __( 'Ky numër ditësh nuk mund të miratohet, sepse në vitin %d punonjësi ka më shumë ditë të shfrytëzuara ose në pritje sesa numri i propozuar.', 'employee-leave-manager' ), $year ),
					array( 'status' => 409, 'year' => $year )
				);
			}
			return new WP_Error( 'elm_not_pending', __( 'Vendimi mund të merret vetëm për kërkesat për +1 ditë që janë në pritje.', 'employee-leave-manager' ), array( 'status' => 409 ) );
		} catch ( Throwable $e ) {
			ELM_DB::rollback();
			ELM_DB::log_failure( 'Deciding permanent entitlement request', $e );
			return new WP_Error( 'elm_entitlement_decision_failed', __( 'Ruajtja e vendimit për kërkesën e +1 dite dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after a +1-day entitlement change is approved or rejected.
		 *
		 * @param int    $id       Entitlement change id.
		 * @param string $decision 'approved' or 'rejected'.
		 */
		do_action( 'elm_entitlement_decided', $id, $decision );

		return $this->get( $id, $actor_id );
	}

	public function cancel( int $id, int $actor_id ): array|WP_Error {
		global $wpdb;
		$table = ELM_DB::table( 'entitlement_changes' );
		ELM_DB::begin();
		try {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d FOR UPDATE", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $row ) {
				throw new DomainException( 'not_found' );
			}
			$is_own = $actor_id === (int) $row['user_id'] || $actor_id === (int) $row['requested_by'];
			if ( ! $is_own && ! user_can( $actor_id, 'manage_options' ) && ! ELM_Chief_Access::can_manage_employee( $actor_id, (int) $row['user_id'] ) ) {
				throw new DomainException( 'forbidden' );
			}
			if ( 'pending' !== $row['status'] ) {
				throw new DomainException( 'not_pending' );
			}
			$now = ELM_DB::now();
			$ok = $wpdb->update(
				$table,
				array( 'status' => 'cancelled', 'updated_at' => $now, 'version' => (int) $row['version'] + 1 ),
				array( 'id' => $id, 'status' => 'pending' ),
				array( '%s', '%s', '%d' ),
				array( '%d', '%s' )
			);
			if ( false === $ok || 0 === $ok ) {
				throw new RuntimeException( 'Entitlement cancellation update failed.' );
			}
			$audit = ELM_Audit::append( 'entitlement_change', $id, 'cancelled', $actor_id, array( 'user_id' => (int) $row['user_id'] ) );
			if ( is_wp_error( $audit ) ) {
				throw new RuntimeException( $audit->get_error_message() );
			}
			ELM_DB::commit();
		} catch ( DomainException $e ) {
			ELM_DB::rollback();
			$map = array(
				'not_found'   => new WP_Error( 'elm_not_found', __( 'Kërkesa për +1 ditë nuk u gjet.', 'employee-leave-manager' ), array( 'status' => 404 ) ),
				'forbidden'   => new WP_Error( 'elm_forbidden', __( 'Nuk keni leje ta anuloni këtë kërkesë për ndryshimin e numrit vjetor të ditëve të pushimit.', 'employee-leave-manager' ), array( 'status' => 403 ) ),
				'not_pending' => new WP_Error( 'elm_not_pending', __( 'Mund të anulohen vetëm kërkesat në pritje për ndryshimin e numrit vjetor të ditëve të pushimit.', 'employee-leave-manager' ), array( 'status' => 409 ) ),
			);
			return $map[ $e->getMessage() ] ?? $map['not_pending'];
		} catch ( Throwable $e ) {
			ELM_DB::rollback();
			ELM_DB::log_failure( 'Cancelling permanent entitlement request', $e );
			return new WP_Error( 'elm_entitlement_cancel_failed', __( 'Anulimi i kërkesës për ndryshimin e numrit vjetor të ditëve të pushimit dështoi.', 'employee-leave-manager' ), array( 'status' => 500 ) );
		}
		return $this->get( $id, $actor_id );
	}

	public function get( int $id, int $viewer_id ): array|WP_Error {
		$rows = $this->list( array( 'id' => $id ), $viewer_id );
		return $rows ? $rows[0] : new WP_Error( 'elm_not_found', __( 'Kërkesa për +1 ditë nuk u gjet.', 'employee-leave-manager' ), array( 'status' => 404 ) );
	}

	public function list( array $args, int $viewer_id ): array {
		global $wpdb;
		$table = ELM_DB::table( 'entitlement_changes' );
		$can_manage = user_can( $viewer_id, 'elm_manage_leave' );
		$is_admin = user_can( $viewer_id, 'manage_options' );
		$where = array( '1=1' );
		$params = array();
		if ( ! $can_manage && ! $is_admin ) {
			$where[] = 'user_id=%d';
			$params[] = $viewer_id;
		} elseif ( ! empty( $args['user_id'] ) ) {
			$user_id = absint( $args['user_id'] );
			if ( ! $is_admin && $user_id !== $viewer_id && ! ELM_Chief_Access::can_manage_employee( $viewer_id, $user_id ) ) {
				$where[] = '1=0';
			} else {
				$where[] = 'user_id=%d';
				$params[] = $user_id;
			}
		} elseif ( array_key_exists( 'user_ids', $args ) ) {
			$user_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $args['user_ids'] ) ) ) );
			if ( ! $is_admin ) {
				$allowed_ids = array_values( array_unique( array_merge( array( $viewer_id ), ELM_Chief_Access::visible_employee_ids( $viewer_id ) ) ) );
				$user_ids = array_values( array_intersect( $user_ids, $allowed_ids ) );
			}
			if ( ! $user_ids ) {
				$where[] = '1=0';
			} else {
				$where[] = 'user_id IN (' . implode( ',', array_fill( 0, count( $user_ids ), '%d' ) ) . ')';
				$params = array_merge( $params, $user_ids );
			}
		} elseif ( $can_manage && ! $is_admin ) {
			$user_ids = ELM_Chief_Access::visible_employee_ids( $viewer_id );
			if ( ! $user_ids ) {
				$where[] = '1=0';
			} else {
				$where[] = 'user_id IN (' . implode( ',', array_fill( 0, count( $user_ids ), '%d' ) ) . ')';
				$params = array_merge( $params, $user_ids );
			}
		}
		if ( ! empty( $args['id'] ) ) {
			$where[] = 'id=%d';
			$params[] = absint( $args['id'] );
		}
		if ( ! empty( $args['effective_year'] ) ) {
			$where[] = 'effective_year=%d';
			$params[] = absint( $args['effective_year'] );
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'pending', 'approved', 'rejected', 'cancelled' ), true ) ) {
			$where[] = 'status=%s';
			$params[] = $args['status'];
		}
		$sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . ' ORDER BY requested_at DESC,id DESC LIMIT 500';
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$current_year = (int) current_datetime()->format( 'Y' );
		foreach ( $rows as &$row ) {
			$user = get_userdata( (int) $row['user_id'] );
			$requester = get_userdata( (int) $row['requested_by'] );
			$decider = ! empty( $row['decided_by'] ) ? get_userdata( (int) $row['decided_by'] ) : null;
			$row['id'] = (int) $row['id'];
			$row['user_id'] = (int) $row['user_id'];
			$row['current_entitlement'] = (int) round( (float) $row['current_entitlement'] );
			$row['requested_entitlement'] = (int) round( (float) $row['requested_entitlement'] );
			$row['effective_year'] = (int) $row['effective_year'];
			$row['requested_by'] = (int) $row['requested_by'];
			$row['decided_by'] = $row['decided_by'] ? (int) $row['decided_by'] : 0;
			$row['employee_name'] = $user ? $user->display_name : __( 'Përdorues i fshirë', 'employee-leave-manager' );
			$row['employee_username'] = $user ? $user->user_login : '';
			$row['requested_by_name'] = $requester ? $requester->display_name : __( 'Përdorues i fshirë', 'employee-leave-manager' );
			$row['requested_by_username'] = $requester ? $requester->user_login : '';
			$row['decided_by_name'] = $decider ? $decider->display_name : '';
			$row['decided_by_username'] = $decider ? $decider->user_login : '';
			$row['self_service_enabled'] = self::global_request_enabled();
			$row['self_service_reopened'] = false;
			$row['permanent_entitlement'] = self::entitlement_for( (int) $row['user_id'], max( $current_year, (int) $row['effective_year'] ) );
		}
		unset( $row );
		return $rows;
	}
}
