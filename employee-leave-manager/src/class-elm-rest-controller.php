<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_REST_Controller {
	private string $namespace = 'elm/v1';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'calendar' ),
				'permission_callback' => array( $this, 'logged_in' ),
				'args'                => array(
					'month'       => array( 'required' => true, 'type' => 'string' ),
					'employee_id' => array( 'required' => false, 'type' => 'integer', 'default' => 0 ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/availability',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'availability' ),
				'permission_callback' => array( $this, 'logged_in' ),
				'args'                => array(
					'start_date' => array( 'required' => true, 'type' => 'string' ),
					'end_date'   => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/balance',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'balance' ),
				'permission_callback' => array( $this, 'logged_in' ),
				'args'                => array(
					'user_id' => array( 'type' => 'integer', 'default' => 0 ),
					'year'    => array( 'type' => 'integer', 'default' => 0 ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/requests',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'requests' ),
					'permission_callback' => array( $this, 'logged_in' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_request' ),
					'permission_callback' => array( $this, 'logged_in' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/requests/(?P<id>\d+)/decision',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'decision' ),
				'permission_callback' => static fn() => current_user_can( 'elm_manage_leave' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/requests/(?P<id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel' ),
				'permission_callback' => array( $this, 'logged_in' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/requests/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_request' ),
					'permission_callback' => array( $this, 'logged_in' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_request' ),
					'permission_callback' => static fn() => current_user_can( 'elm_manage_leave' ) || current_user_can( 'elm_adjust_balances' ) || current_user_can( 'manage_options' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/adjustments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'adjustments' ),
					'permission_callback' => static fn() => current_user_can( 'elm_adjust_balances' ) || current_user_can( 'manage_options' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_adjustment' ),
					'permission_callback' => static fn() => current_user_can( 'elm_adjust_balances' ) || current_user_can( 'manage_options' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/entitlement-changes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'entitlement_changes' ),
					'permission_callback' => array( $this, 'logged_in' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_entitlement_change' ),
					'permission_callback' => array( $this, 'logged_in' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/entitlement-changes/(?P<id>\d+)/decision',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'decide_entitlement_change' ),
				'permission_callback' => static fn() => current_user_can( 'elm_adjust_balances' ) || current_user_can( 'manage_options' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/entitlement-changes/(?P<id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_entitlement_change' ),
				'permission_callback' => array( $this, 'logged_in' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/users',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'users' ),
				'permission_callback' => static fn() => current_user_can( 'elm_manage_leave' ) || current_user_can( 'elm_export_leave_reports' ),
				'args'                => array(
					'year' => array( 'type' => 'integer', 'default' => 0 ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/users/(?P<id>\d+)/profile',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_employee_profile' ),
				'permission_callback' => static fn() => current_user_can( 'elm_manage_leave' ) || current_user_can( 'manage_options' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'settings' ),
					'permission_callback' => array( $this, 'can_manage_settings' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage_settings' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/audit/verify',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static fn() => rest_ensure_response( ELM_Audit::verify_chain() ),
				'permission_callback' => static fn() => current_user_can( 'elm_verify_audit' ),
			)
		);
	}

	public function logged_in(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return current_user_can( 'manage_options' ) || ELM_Access::user_has_portal_access( wp_get_current_user() );
	}

	public function can_manage_settings(): bool {
		return current_user_can( 'elm_adjust_balances' ) || current_user_can( 'manage_options' );
	}

	private function can_access_employee( int $employee_id, bool $allow_self = true ): bool {
		$current_user_id = get_current_user_id();
		if ( $employee_id <= 0 || $current_user_id <= 0 ) {
			return false;
		}
		if ( $allow_self && $employee_id === $current_user_id ) {
			return current_user_can( 'manage_options' ) || ELM_Access::user_has_portal_access( wp_get_current_user() );
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return ELM_Chief_Access::can_manage_employee( $current_user_id, $employee_id );
	}

	private function employee_access_error(): WP_Error {
		return new WP_Error( 'elm_forbidden', __( 'Ky punonjës nuk është i caktuar nën mbikëqyrjen tuaj të drejtpërdrejtë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
	}

	public function calendar( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$employee_id = absint( $request['employee_id'] );
		if ( ! $employee_id ) {
			$employee_id = get_current_user_id();
		}
		if ( ! $this->can_access_employee( $employee_id ) ) {
			return $this->employee_access_error();
		}
		$result = ( new ELM_Leave_Service() )->calendar( sanitize_text_field( (string) $request['month'] ), $employee_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! current_user_can( 'elm_manage_leave' ) && ! current_user_can( 'manage_options' ) ) {
			$result = $this->redact_calendar_occupancy( $result );
		}
		return rest_ensure_response( $result );
	}

	public function availability( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = ( new ELM_Leave_Service() )->availability(
			sanitize_text_field( (string) $request['start_date'] ),
			sanitize_text_field( (string) $request['end_date'] )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! current_user_can( 'elm_manage_leave' ) && ! current_user_can( 'manage_options' ) ) {
			// Employees need to know only whether a date is unavailable. Pending
			// organization-wide staffing metadata is not exposed.
			unset( $result['pending_dates'] );
		}
		return rest_ensure_response( $result );
	}

	public function balance( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = absint( $request['user_id'] );
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $this->can_access_employee( $user_id ) ) {
			return $this->employee_access_error();
		}
		$year = absint( $request['year'] ) ?: (int) current_datetime()->format( 'Y' );
		return rest_ensure_response( ( new ELM_Balance_Service() )->summary( $user_id, $year ) );
	}

	public function settings(): WP_REST_Response {
		$settings = ELM_Policy::settings();
		$settings['movable_holiday_edit_dates'] = ELM_Policy::movable_holiday_edit_dates( $settings );
		return rest_ensure_response( $settings );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = $request->get_body_params();
		}
		$result = $this->save_settings( is_array( $data ) ? $data : array(), 'frontend' );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	private function save_settings( array $data, string $source = 'frontend' ): array|WP_Error {
		$before = ELM_Policy::settings();
		$concurrency = max( 1, min( 20, absint( $data['concurrency_limit'] ?? 2 ) ) );
		$weekdays = array_values(
			array_intersect(
				array( 1, 2, 3, 4, 5, 6, 7 ),
				array_map( 'absint', (array) ( $data['working_weekdays'] ?? array() ) )
			)
		);
		$movable_holidays = (array) ( $before['movable_holidays'] ?? array() );
		if ( array_key_exists( 'movable_holidays', $data ) ) {
			$submitted_movable = is_array( $data['movable_holidays'] ) ? $data['movable_holidays'] : array();
			$merged_movable = ELM_Policy::merge_movable_holiday_dates( $movable_holidays, $submitted_movable );
			if ( is_wp_error( $merged_movable ) ) {
				return $merged_movable;
			}
			$movable_holidays = $merged_movable;
		}
		$portal_page_id = absint( $before['portal_page_id'] ?? 0 );
		$frontend_only_enabled = ! empty( $before['frontend_only_enabled'] );
		$show_plus_one_when_empty = array_key_exists( 'show_plus_one_when_empty', $data )
			? ! empty( $data['show_plus_one_when_empty'] )
			: ! empty( $before['show_plus_one_when_empty'] );
		$email_notifications_enabled = array_key_exists( 'email_notifications_enabled', $data )
			? ! empty( $data['email_notifications_enabled'] )
			: ! empty( $before['email_notifications_enabled'] );
		$notification_cc_admin = array_key_exists( 'notification_cc_admin', $data )
			? ! empty( $data['notification_cc_admin'] )
			: ! empty( $before['notification_cc_admin'] );
		if ( current_user_can( 'manage_options' ) && ( array_key_exists( 'portal_page_id', $data ) || array_key_exists( 'frontend_only_enabled', $data ) ) ) {
			$portal_page_id = absint( $data['portal_page_id'] ?? 0 );
			$frontend_only_enabled = ! empty( $data['frontend_only_enabled'] );
			$page = $portal_page_id ? get_post( $portal_page_id ) : null;
			$has_portal_shortcode = $page instanceof WP_Post
				&& 'publish' === $page->post_status
				&& ( has_shortcode( (string) $page->post_content, 'elm_leave_portal' ) || has_shortcode( (string) $page->post_content, 'employee_leave_manager' ) );
			if ( $frontend_only_enabled && ! $has_portal_shortcode ) {
				return new WP_Error( 'elm_invalid_portal_page', __( 'Zgjidhni një faqe të publikuar që përmban kodin e shkurtër të portalit para aktivizimit të qasjes vetëm përmes portalit.', 'employee-leave-manager' ), array( 'status' => 400 ) );
			}
			if ( ! $has_portal_shortcode ) {
				$portal_page_id = 0;
				$frontend_only_enabled = false;
			}
		}
		$after = array(
			'annual_entitlement' => 20.0,
			'period_one_limit'   => 0.0,
			'concurrency_limit'  => $concurrency,
			'working_weekdays'   => $weekdays ?: array( 1, 2, 3, 4, 5 ),
			'holidays'           => array(),
			'holiday_names'      => array(),
			'movable_holidays'   => $movable_holidays,
			'max_upload_mb'      => max( 1, min( 50, absint( $data['max_upload_mb'] ?? 10 ) ) ),
			'frontend_only_enabled' => $frontend_only_enabled,
			'portal_page_id'        => $portal_page_id,
			'show_plus_one_when_empty' => $show_plus_one_when_empty,
			'email_notifications_enabled' => $email_notifications_enabled,
			'notification_cc_admin'       => $notification_cc_admin,
		);
		update_option( 'elm_settings', $after, false );
		if ( ELM_DB::schema_ready() ) {
			ELM_DB::begin();
			$audit = ELM_Audit::append( 'policy_settings', 0, 'updated', get_current_user_id(), array( 'before' => $before, 'after' => $after, 'source' => $source ) );
			if ( is_wp_error( $audit ) ) {
				ELM_DB::rollback();
				update_option( 'elm_settings', $before, false );
				return $audit;
			}
			ELM_DB::commit();
		}
		$result = ELM_Policy::settings();
		$result['holiday_text'] = ELM_Policy::format_holiday_text( $result ); // Pajtueshmëri me klientët e vjetër.
		$result['movable_holiday_edit_dates'] = ELM_Policy::movable_holiday_edit_dates( $result );
		if ( array_key_exists( 'entitlement_global_enabled', $data ) ) {
			$requested_access = ! empty( $data['entitlement_global_enabled'] );
			$current_access   = ELM_Entitlement_Service::global_request_enabled();
			if ( $requested_access !== $current_access ) {
				$access = ( new ELM_Entitlement_Service() )->save_global_access( $requested_access, get_current_user_id() );
				if ( is_wp_error( $access ) ) {
					return $access;
				}
				$current_access = (bool) $access;
			}
			$result['entitlement_global_enabled'] = $current_access;
		} else {
			$result['entitlement_global_enabled'] = ELM_Entitlement_Service::global_request_enabled();
		}
		return $result;
	}

	private function save_plus_one_visibility( bool $enabled ): array|WP_Error {
		$before = ELM_Policy::settings();
		$previous = ! empty( $before['show_plus_one_when_empty'] );
		if ( $previous === $enabled ) {
			return array( 'show_plus_one_when_empty' => $enabled );
		}

		$after = $before;
		$after['show_plus_one_when_empty'] = $enabled;
		update_option( 'elm_settings', $after, false );

		if ( ELM_DB::schema_ready() ) {
			ELM_DB::begin();
			$audit = ELM_Audit::append(
				'policy_settings',
				0,
				'plus_one_visibility_updated',
				get_current_user_id(),
				array( 'before' => $previous, 'after' => $enabled, 'source' => 'frontend_toggle' )
			);
			if ( is_wp_error( $audit ) ) {
				ELM_DB::rollback();
				update_option( 'elm_settings', $before, false );
				return $audit;
			}
			ELM_DB::commit();
		}

		return array( 'show_plus_one_when_empty' => $enabled );
	}

	public function requests( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$employee_id = absint( $request['employee_id'] );
		if ( $employee_id && ! $this->can_access_employee( $employee_id ) ) {
			return $this->employee_access_error();
		}
		$args = array(
			'employee_id' => $employee_id,
			'status'      => sanitize_key( (string) $request['status'] ),
			'year'        => absint( $request['year'] ),
		);
		return rest_ensure_response( ( new ELM_Leave_Service() )->list_requests( $args, get_current_user_id() ) );
	}

	public function create_request( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = $request->get_body_params();
		}
		$result = $this->persist_request( is_array( $data ) ? $data : array(), $request->get_file_params() );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 201 );
	}

	public function ajax_create_request(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Sesioni ka skaduar. Hyni përsëri.', 'employee-leave-manager' ) ), 401 );
		}
		if ( ! check_ajax_referer( 'elm_submit_request', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Verifikimi i sigurisë ka skaduar. Rifreskoni faqen dhe provoni përsëri.', 'employee-leave-manager' ) ), 403 );
		}
		if ( ! ELM_Access::user_has_portal_access( wp_get_current_user() ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nuk keni leje të paraqitni kërkesa për pushim.', 'employee-leave-manager' ) ), 403 );
		}

		$data = wp_unslash( $_POST );
		unset( $data['action'], $data['nonce'] );
		$files = is_array( $_FILES ) ? $_FILES : array();
		$request_id = absint( $data['request_id'] ?? 0 );
		$result = $request_id
			? $this->persist_request_update( $request_id, $data, $files )
			: $this->persist_request( $data, $files );
		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status = is_array( $error_data ) && ! empty( $error_data['status'] ) ? (int) $error_data['status'] : 400;
			wp_send_json_error(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
					'data'    => is_array( $error_data ) ? $error_data : array(),
				),
				$status
			);
		}
		wp_send_json_success( $result, 201 );
	}


	public function ajax_update_request(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Sesioni ka skaduar. Hyni përsëri.', 'employee-leave-manager' ) ), 401 );
		}
		if ( ! check_ajax_referer( 'elm_submit_request', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Verifikimi i sigurisë ka skaduar. Rifreskoni faqen dhe provoni përsëri.', 'employee-leave-manager' ) ), 403 );
		}
		if ( ! ELM_Access::user_has_portal_access( wp_get_current_user() ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nuk keni qasje në portalin e pushimeve.', 'employee-leave-manager' ) ), 403 );
		}
		$data = wp_unslash( $_POST );
		unset( $data['action'], $data['nonce'] );
		$request_id = absint( $data['request_id'] ?? 0 );
		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Kërkesa është e pavlefshme.', 'employee-leave-manager' ) ), 400 );
		}
		$existing_request = ( new ELM_Leave_Service() )->get_request( $request_id );
		if ( is_wp_error( $existing_request ) ) {
			$this->send_ajax_wp_error( $existing_request );
		}
		if ( (int) $existing_request['employee_id'] !== get_current_user_id() && ! ELM_Chief_Access::can_manage_employee( get_current_user_id(), (int) $existing_request['employee_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Ky punonjës nuk është në listën tuaj të menaxhimit në portal.', 'employee-leave-manager' ) ), 403 );
		}
		$result = $this->persist_request_update( $request_id, $data, is_array( $_FILES ) ? $_FILES : array() );
		if ( is_wp_error( $result ) ) {
			$this->send_ajax_wp_error( $result );
		}
		wp_send_json_success( $result );
	}

	public function ajax_get_request(): void {
		$this->verify_portal_ajax();
		if ( ! ELM_DB::schema_ready() && ! ELM_Activator::install_schema() ) {
			wp_send_json_error( array( 'message' => __( 'Baza e të dhënave të pushimeve nuk është e gatshme. Kontaktoni administratorin për ta riaktivizuar shtojcën.', 'employee-leave-manager' ) ), 503 );
		}
		$request_id = absint( $_POST['request_id'] ?? 0 );
		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Kërkesa është e pavlefshme.', 'employee-leave-manager' ) ), 400 );
		}
		$result = ( new ELM_Leave_Service() )->get_request( $request_id );
		if ( is_wp_error( $result ) ) {
			$this->send_ajax_wp_error( $result );
		}
		if ( (int) $result['employee_id'] !== get_current_user_id() ) {
			if ( ! current_user_can( 'elm_manage_leave' ) || ! ELM_Chief_Access::can_manage_employee( get_current_user_id(), (int) $result['employee_id'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Nuk keni leje ta redaktoni këtë kërkesë.', 'employee-leave-manager' ) ), 403 );
			}
		}
		wp_send_json_success( $result );
	}

	public function ajax_portal_snapshot(): void {
		$this->verify_portal_ajax();
		if ( ! ELM_DB::schema_ready() && ! ELM_Activator::install_schema() ) {
			wp_send_json_error( array( 'message' => __( 'Baza e të dhënave të pushimeve nuk është e gatshme. Kontaktoni administratorin për ta riaktivizuar shtojcën.', 'employee-leave-manager' ) ), 503 );
		}
		$year = absint( $_POST['year'] ?? 0 ) ?: (int) current_datetime()->format( 'Y' );
		$service = new ELM_Leave_Service();
		wp_send_json_success(
			array(
				'requests' => $service->list_requests( array( 'employee_id' => get_current_user_id() ), get_current_user_id() ),
				'balance'  => ( new ELM_Balance_Service() )->summary( get_current_user_id(), $year ),
			)
		);
	}

	public function ajax_calendar_data(): void {
		$this->verify_portal_ajax();
		if ( ! ELM_DB::schema_ready() && ! ELM_Activator::install_schema() ) {
			wp_send_json_error( array( 'message' => __( 'Baza e të dhënave të pushimeve nuk është e gatshme. Kontaktoni administratorin për ta riaktivizuar shtojcën.', 'employee-leave-manager' ) ), 503 );
		}
		$month = sanitize_text_field( wp_unslash( (string) ( $_POST['month'] ?? '' ) ) );
		$result = ( new ELM_Leave_Service() )->calendar( $month, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$this->send_ajax_wp_error( $result );
		}
		wp_send_json_success( $result );
	}

	public function ajax_cancel_request(): void {
		$this->verify_portal_ajax();
		if ( ! ELM_DB::schema_ready() && ! ELM_Activator::install_schema() ) {
			wp_send_json_error( array( 'message' => __( 'Baza e të dhënave të pushimeve nuk është e gatshme. Kontaktoni administratorin për ta riaktivizuar shtojcën.', 'employee-leave-manager' ) ), 503 );
		}
		$request_id = absint( $_POST['request_id'] ?? 0 );
		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Kërkesa është e pavlefshme.', 'employee-leave-manager' ) ), 400 );
		}
		$cancel_reason = sanitize_textarea_field( wp_unslash( (string) ( $_POST['cancel_reason'] ?? '' ) ) );
		$cancel_service = new ELM_Leave_Service();
		$existing_request = $cancel_service->get_request( $request_id );
		if ( is_wp_error( $existing_request ) ) {
			$this->send_ajax_wp_error( $existing_request );
		}
		if ( (int) $existing_request['employee_id'] !== get_current_user_id() && ! ELM_Chief_Access::can_manage_employee( get_current_user_id(), (int) $existing_request['employee_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Ky punonjës nuk është në listën tuaj të menaxhimit në portal.', 'employee-leave-manager' ) ), 403 );
		}
		$result = $cancel_service->cancel_request( $request_id, get_current_user_id(), $cancel_reason );
		if ( is_wp_error( $result ) ) {
			$this->send_ajax_wp_error( $result );
		}
		wp_send_json_success( $result );
	}


	public function ajax_delete_own_request(): void {
		$this->verify_portal_ajax();
		if ( ! current_user_can( 'elm_manage_leave' ) && ! current_user_can( 'elm_adjust_balances' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nuk keni leje ta fshini përgjithmonë këtë kërkesë.', 'employee-leave-manager' ) ), 403 );
		}
		if ( ! ELM_DB::schema_ready() && ! ELM_Activator::install_schema() ) {
			wp_send_json_error( array( 'message' => __( 'Baza e të dhënave të pushimeve nuk është e gatshme. Kontaktoni administratorin për ta riaktivizuar shtojcën.', 'employee-leave-manager' ) ), 503 );
		}

		$request_id = absint( $_POST['request_id'] ?? 0 );
		$confirmation = sanitize_text_field( wp_unslash( (string) ( $_POST['confirmation'] ?? '' ) ) );
		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Kërkesa është e pavlefshme.', 'employee-leave-manager' ) ), 400 );
		}
		if ( 'DELETE' !== $confirmation ) {
			wp_send_json_error( array( 'message' => __( 'Shkruani saktësisht DELETE për të konfirmuar fshirjen.', 'employee-leave-manager' ) ), 400 );
		}

		$service = new ELM_Leave_Service();
		$existing_request = $service->get_request( $request_id );
		if ( is_wp_error( $existing_request ) ) {
			$this->send_ajax_wp_error( $existing_request );
		}
		if ( (int) $existing_request['employee_id'] !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Te Pushimi im mund të fshini vetëm kërkesat tuaja personale.', 'employee-leave-manager' ) ), 403 );
		}

		$result = $service->delete_request( $request_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$this->send_ajax_wp_error( $result );
		}
		wp_send_json_success( $result );
	}


	public function ajax_entitlement_change(): void {
		$this->verify_portal_ajax();
		if ( ! ELM_DB::schema_ready() && ! ELM_Activator::install_schema() ) {
			wp_send_json_error( array( 'message' => __( 'Baza e të dhënave të pushimeve nuk është e gatshme. Kontaktoni administratorin për ta riaktivizuar shtojcën.', 'employee-leave-manager' ) ), 503 );
		}
		$operation = sanitize_key( wp_unslash( (string) ( $_POST['operation'] ?? '' ) ) );
		$raw_payload = wp_unslash( (string) ( $_POST['payload'] ?? '' ) );
		$payload = $raw_payload ? json_decode( $raw_payload, true ) : array();
		if ( ! is_array( $payload ) ) {
			wp_send_json_error( array( 'message' => __( 'Të dhënat e kërkesës për ndryshimin e numrit vjetor të ditëve të pushimit janë të pavlefshme.', 'employee-leave-manager' ) ), 400 );
		}
		$service = new ELM_Entitlement_Service();
		$user_id = get_current_user_id();
		switch ( $operation ) {
			case 'list':
				$result = $service->list( array( 'user_id' => $user_id ), $user_id );
				break;
			case 'create':
				$result = $service->create(
					$user_id,
					absint( $payload['requested_entitlement'] ?? 0 ),
					absint( $payload['effective_year'] ?? 0 ),
					sanitize_textarea_field( (string) ( $payload['reason'] ?? '' ) ),
					$user_id
				);
				break;
			case 'cancel':
				$result = $service->cancel( absint( $payload['id'] ?? 0 ), $user_id );
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Ky veprim për ndryshimin e numrit vjetor të ditëve të pushimit nuk është i disponueshëm.', 'employee-leave-manager' ) ), 400 );
		}
		if ( is_wp_error( $result ) ) {
			$this->send_ajax_wp_error( $result );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Reliable frontend management channel for the Chief workspace.
	 *
	 * The public portal already uses admin-ajax.php for employee actions. Keeping
	 * the Chief workspace on the same authenticated channel avoids failures on
	 * installations that block REST cookies/nonces or cache REST bootstrap data.
	 */

	private function require_chief_employee_access( int $employee_id ): void {
		if ( ! $employee_id || ! ELM_Chief_Access::can_manage_employee( get_current_user_id(), $employee_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Ky punonjës nuk është në listën tuaj të menaxhimit në portal.', 'employee-leave-manager' ) ), 403 );
		}
	}

	private function require_chief_request_access( int $request_id, ELM_Leave_Service $service ): array {
		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Kërkesa është e pavlefshme.', 'employee-leave-manager' ) ), 400 );
		}
		$request = $service->get_request( $request_id );
		if ( is_wp_error( $request ) ) {
			$this->send_ajax_wp_error( $request );
		}
		$employee_id = (int) $request['employee_id'];
		if ( $employee_id === get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Kërkesat tuaja personale menaxhohen te skeda Pushimi im. Përdorni veprimet e asaj skede për redaktim, vendim, anulim, fshirje ose PDF.', 'employee-leave-manager' ) ), 403 );
		}
		$this->require_chief_employee_access( $employee_id );
		return $request;
	}

	private function entitlement_change_employee_id( int $change_id ): int {
		if ( $change_id <= 0 ) {
			return 0;
		}
		global $wpdb;
		$table = ELM_DB::table( 'entitlement_changes' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM $table WHERE id=%d", $change_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function ajax_chief_portal(): void {
		$this->verify_portal_ajax();
		if ( ! current_user_can( 'elm_manage_leave' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nuk keni leje t\'i menaxhoni kërkesat e punonjësve për pushim.', 'employee-leave-manager' ) ), 403 );
		}

		$operation = sanitize_key( wp_unslash( (string) ( $_POST['operation'] ?? '' ) ) );
		$raw_payload = wp_unslash( (string) ( $_POST['payload'] ?? '' ) );
		$payload = $raw_payload ? json_decode( $raw_payload, true ) : array();
		if ( ! is_array( $payload ) ) {
			wp_send_json_error( array( 'message' => __( 'Të dhënat e kërkesës së menaxhimit janë të pavlefshme.', 'employee-leave-manager' ) ), 400 );
		}

		if ( ! ELM_DB::schema_ready() && ! ELM_Activator::install_schema() ) {
			wp_send_json_error( array( 'message' => __( 'Baza e të dhënave të pushimeve nuk është e gatshme. Kontaktoni administratorin për ta riaktivizuar shtojcën.', 'employee-leave-manager' ) ), 503 );
		}

		$service = new ELM_Leave_Service();
		$result = null;

		switch ( $operation ) {
			case 'requests':
				$current_user_id = get_current_user_id();
				$request_employee_id = absint( $payload['employee_id'] ?? 0 );
				$request_args = array(
					'status' => sanitize_key( (string) ( $payload['status'] ?? '' ) ),
					'year'   => absint( $payload['year'] ?? 0 ),
				);
				if ( $request_employee_id ) {
					if ( $request_employee_id === $current_user_id ) {
						$result = array();
						break;
					}
					$this->require_chief_employee_access( $request_employee_id );
					$request_args['employee_id'] = $request_employee_id;
				} else {
					$request_args['employee_ids'] = array_values( array_diff( ELM_Chief_Access::visible_employee_ids( $current_user_id ), array( $current_user_id ) ) );
				}
				$result = $service->list_requests( $request_args, $current_user_id );
				foreach ( $result as &$request_row ) {
					$request_row['pdf_url'] = esc_url_raw(
						add_query_arg(
							'_wpnonce',
							wp_create_nonce( 'elm_export_request_pdf_frontend' ),
							admin_url( 'admin-post.php?action=elm_export_request_pdf&elm_frontend=1&request_id=' . absint( $request_row['id'] ?? 0 ) )
						)
					);
				}
				unset( $request_row );
				break;


			case 'history':
				$current_user_id = get_current_user_id();
				$history_employee_id = absint( $payload['employee_id'] ?? 0 );
				$history_year = absint( $payload['year'] ?? 0 ) ?: (int) current_datetime()->format( 'Y' );
				$history_status = sanitize_key( (string) ( $payload['status'] ?? '' ) );
				$history_employee_ids = array_values( array_diff( ELM_Chief_Access::visible_employee_ids( $current_user_id ), array( $current_user_id ) ) );

				if ( $history_employee_id ) {
					if ( $history_employee_id === $current_user_id ) {
						wp_send_json_error( array( 'message' => __( 'Historiku i kësaj skede është vetëm për punonjësit që menaxhoni.', 'employee-leave-manager' ) ), 403 );
					}
					$this->require_chief_employee_access( $history_employee_id );
					$history_employee_ids = array( $history_employee_id );
				}

				$history_args = array(
					'year' => $history_year,
					'status' => $history_status,
					'employee_ids' => $history_employee_ids,
				);
				$history_requests = $service->list_requests( $history_args, $current_user_id );
				$balance_service = new ELM_Balance_Service();
				$history_users = $history_employee_ids
					? get_users(
						array(
							'include' => $history_employee_ids,
							'orderby' => 'display_name',
							'order' => 'ASC',
							'fields' => array( 'ID', 'display_name', 'user_email' ),
						)
					)
					: array();
				$history_summaries = array();
				foreach ( $history_users as $history_user ) {
					$history_user_id = (int) $history_user->ID;
					$history_balance = $balance_service->summary( $history_user_id, $history_year );
					$history_summaries[] = array(
						'id' => $history_user_id,
						'name' => $history_user->display_name,
						'email' => $history_user->user_email,
						'position' => ELM_Employee_Profile::position( $history_user_id ),
						'sector' => ELM_Employee_Profile::sector( $history_user_id ),
						'balance' => array(
							'total' => (float) ( $history_balance['total_entitlement'] ?? 0 ),
							'used' => (float) ( $history_balance['approved_used'] ?? 0 ),
							'pending' => (float) ( $history_balance['pending_units'] ?? 0 ),
							'remaining' => (float) ( $history_balance['remaining_after_pending'] ?? 0 ),
						),
					);
				}
				$result = array(
					'year' => $history_year,
					'employee_id' => $history_employee_id,
					'status' => $history_status,
					'employees' => $history_summaries,
					'requests' => $history_requests,
				);
				break;

			case 'users':
				$visible_employee_ids = ELM_Chief_Access::visible_employee_ids( get_current_user_id() );
				$users = $visible_employee_ids ? get_users( array( 'include' => $visible_employee_ids, 'orderby' => 'display_name', 'order' => 'ASC', 'fields' => array( 'ID', 'display_name', 'user_email' ) ) ) : array();
				$directory_year = absint( $payload['year'] ?? 0 ) ?: (int) current_datetime()->format( 'Y' );
				$result = array();
				foreach ( $users as $user ) {
					if ( in_array( (int) $user->ID, $visible_employee_ids, true ) ) {
						$balance = ( new ELM_Balance_Service() )->summary( (int) $user->ID, $directory_year );
						$result[] = array(
							'id' => (int) $user->ID,
							'name' => $user->display_name,
							'email' => $user->user_email,
							'position' => ELM_Employee_Profile::position( (int) $user->ID ),
							'sector' => ELM_Employee_Profile::sector( (int) $user->ID ),
							'year' => $directory_year,
							'balance' => array(
								'total' => (float) ( $balance['total_entitlement'] ?? 0 ),
								'used' => (float) ( $balance['approved_used'] ?? 0 ),
								'pending' => (float) ( $balance['pending_units'] ?? 0 ),
								'remaining' => (float) ( $balance['remaining_after_pending'] ?? 0 ),
							),
							'entitlement_request_enabled' => ELM_Entitlement_Service::employee_request_enabled( (int) $user->ID, $directory_year ),
						);
					}
				}
				break;

			case 'save_employee_profile':
				$profile_user_id = absint( $payload['user_id'] ?? 0 );
				$this->require_chief_employee_access( $profile_user_id );
				$result = ELM_Employee_Profile::update_for_manager(
					$profile_user_id,
					$payload['position'] ?? '',
					$payload['sector'] ?? '',
					get_current_user_id()
				);
				break;

			case 'calendar':
				$employee_id = absint( $payload['employee_id'] ?? 0 );
				if ( ! $employee_id ) {
					wp_send_json_error( array( 'message' => __( 'Zgjidhni një punonjës të vlefshëm.', 'employee-leave-manager' ) ), 400 );
				}
				$this->require_chief_employee_access( $employee_id );
				$result = $service->calendar( sanitize_text_field( (string) ( $payload['month'] ?? '' ) ), $employee_id );
				break;

			case 'update_request':
				$request_id = absint( $payload['request_id'] ?? 0 );
				$this->require_chief_request_access( $request_id, $service );
				$data = is_array( $payload['data'] ?? null ) ? $payload['data'] : array();
				$result = $request_id
					? $this->persist_request_update( $request_id, $data, array() )
					: new WP_Error( 'elm_invalid_request', __( 'Kërkesa është e pavlefshme.', 'employee-leave-manager' ), array( 'status' => 400 ) );
				break;

			case 'decision':
				$decision_request_id = absint( $payload['request_id'] ?? 0 );
				$this->require_chief_request_access( $decision_request_id, $service );
				$result = $service->decide_request(
					$decision_request_id,
					sanitize_key( (string) ( $payload['decision'] ?? '' ) ),
					sanitize_textarea_field( (string) ( $payload['note'] ?? '' ) ),
					get_current_user_id()
				);
				break;

			case 'decision_own':
				$own_decision_request_id = absint( $payload['request_id'] ?? 0 );
				if ( ! $own_decision_request_id ) {
					wp_send_json_error( array( 'message' => __( 'Kërkesa është e pavlefshme.', 'employee-leave-manager' ) ), 400 );
				}
				$own_request = $service->get_request( $own_decision_request_id );
				if ( is_wp_error( $own_request ) ) {
					$this->send_ajax_wp_error( $own_request );
				}
				if ( (int) $own_request['employee_id'] !== get_current_user_id() ) {
					wp_send_json_error( array( 'message' => __( 'Te Pushimi im mund të merrni vendim vetëm për kërkesën tuaj personale.', 'employee-leave-manager' ) ), 403 );
				}
				$result = $service->decide_request(
					$own_decision_request_id,
					sanitize_key( (string) ( $payload['decision'] ?? '' ) ),
					sanitize_textarea_field( (string) ( $payload['note'] ?? '' ) ),
					get_current_user_id()
				);
				break;

			case 'cancel':
				$cancel_request_id = absint( $payload['request_id'] ?? 0 );
				$this->require_chief_request_access( $cancel_request_id, $service );
				$result = $service->cancel_request(
					$cancel_request_id,
					get_current_user_id(),
					sanitize_textarea_field( (string) ( $payload['note'] ?? '' ) )
				);
				break;

			case 'delete_request':
				if ( ! current_user_can( 'elm_manage_leave' ) && ! current_user_can( 'elm_adjust_balances' ) && ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => __( 'Vetëm mbikëqyrësi i drejtpërdrejtë ose administratori mund ta fshijë përgjithmonë një kërkesë të punonjësit.', 'employee-leave-manager' ) ), 403 );
				}
				if ( 'DELETE' !== (string) ( $payload['confirmation'] ?? '' ) ) {
					wp_send_json_error( array( 'message' => __( 'Shkruani saktësisht DELETE për të konfirmuar fshirjen.', 'employee-leave-manager' ) ), 400 );
				}
				$delete_request_id = absint( $payload['request_id'] ?? 0 );
				$this->require_chief_request_access( $delete_request_id, $service );
				$result = $service->delete_request(
					$delete_request_id,
					get_current_user_id()
				);
				break;

			case 'entitlement_changes':
				$entitlement_user_id = absint( $payload['user_id'] ?? 0 );
				if ( $entitlement_user_id ) {
					$this->require_chief_employee_access( $entitlement_user_id );
				}
				$result = ( new ELM_Entitlement_Service() )->list(
					array(
						'user_id' => $entitlement_user_id,
						'status'  => sanitize_key( (string) ( $payload['status'] ?? '' ) ),
					),
					get_current_user_id()
				);
				if ( ! $entitlement_user_id ) {
					$chief_id = get_current_user_id();
					$result = array_values(
						array_filter(
							$result,
							static function ( array $change ) use ( $chief_id ): bool {
								$employee_id = absint( $change['user_id'] ?? 0 );
								return $employee_id > 0
									&& $employee_id !== $chief_id
									&& ELM_Chief_Access::can_manage_employee( $chief_id, $employee_id );
							}
						)
					);
				}
				break;

			case 'create_entitlement_change':
				$create_entitlement_user_id = absint( $payload['user_id'] ?? 0 );
				$this->require_chief_employee_access( $create_entitlement_user_id );
				$result = ( new ELM_Entitlement_Service() )->create(
					$create_entitlement_user_id,
					absint( $payload['requested_entitlement'] ?? 0 ),
					absint( $payload['effective_year'] ?? 0 ),
					sanitize_textarea_field( (string) ( $payload['reason'] ?? '' ) ),
					get_current_user_id()
				);
				break;

			case 'decide_entitlement_change':
				$decide_entitlement_id = absint( $payload['id'] ?? 0 );
				$this->require_chief_employee_access( $this->entitlement_change_employee_id( $decide_entitlement_id ) );
				$result = ( new ELM_Entitlement_Service() )->decide(
					$decide_entitlement_id,
					sanitize_key( (string) ( $payload['decision'] ?? '' ) ),
					sanitize_textarea_field( (string) ( $payload['note'] ?? '' ) ),
					get_current_user_id()
				);
				break;

			case 'cancel_entitlement_change':
				$cancel_entitlement_id = absint( $payload['id'] ?? 0 );
				$this->require_chief_employee_access( $this->entitlement_change_employee_id( $cancel_entitlement_id ) );
				$result = ( new ELM_Entitlement_Service() )->cancel( $cancel_entitlement_id, get_current_user_id() );
				break;

			case 'save_entitlement_access':
				$result = ( new ELM_Entitlement_Service() )->save_global_access(
					! empty( $payload['enabled'] ),
					get_current_user_id()
				);
				break;

			case 'adjustments':
				if ( ! current_user_can( 'elm_adjust_balances' ) && ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => __( 'Nuk keni leje t\'i shihni ditët shtesë.', 'employee-leave-manager' ) ), 403 );
				}
				$adjustment_user_id = absint( $payload['user_id'] ?? 0 );
				$this->require_chief_employee_access( $adjustment_user_id );
				$result = $service->list_adjustments(
					array(
						'user_id' => $adjustment_user_id,
						'year'    => absint( $payload['year'] ?? 0 ),
					)
				);
				break;

			case 'create_adjustment':
				if ( ! current_user_can( 'elm_adjust_balances' ) && ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => __( 'Nuk keni leje të shtoni ditë pushimi.', 'employee-leave-manager' ) ), 403 );
				}
				$create_adjustment_user_id = absint( $payload['user_id'] ?? 0 );
				$this->require_chief_employee_access( $create_adjustment_user_id );
				$result = $service->add_adjustment(
					$create_adjustment_user_id,
					absint( $payload['year'] ?? 0 ),
					(float) ( $payload['amount'] ?? 0 ),
					sanitize_textarea_field( (string) ( $payload['note'] ?? '' ) ),
					get_current_user_id()
				);
				break;

			case 'verify_audit':
				if ( ! current_user_can( 'elm_verify_audit' ) && ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => __( 'Nuk keni leje ta verifikoni regjistrin e auditimit.', 'employee-leave-manager' ) ), 403 );
				}
				$result = ELM_Audit::verify_chain();
				break;

			case 'save_plus_one_visibility':
				$result = $this->save_plus_one_visibility( ! empty( $payload['enabled'] ) );
				break;

			case 'save_settings':
				if ( ! current_user_can( 'elm_adjust_balances' ) && ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => __( 'Nuk keni leje t\'i ndryshoni cilësimet e pushimeve.', 'employee-leave-manager' ) ), 403 );
				}
				$data = is_array( $payload['data'] ?? null ) ? $payload['data'] : array();
				$result = $this->save_settings( $data, 'frontend' );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Ky veprim menaxhimi nuk është i disponueshëm.', 'employee-leave-manager' ) ), 400 );
		}

		if ( is_wp_error( $result ) ) {
			$this->send_ajax_wp_error( $result );
		}
		wp_send_json_success( $result );
	}

	private function verify_portal_ajax(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Sesioni ka skaduar. Hyni përsëri.', 'employee-leave-manager' ) ), 401 );
		}
		$nonce = sanitize_text_field( wp_unslash( (string) ( $_POST['nonce'] ?? '' ) ) );
		$valid_portal_nonce = wp_verify_nonce( $nonce, 'elm_portal_action' );
		$valid_submit_nonce = wp_verify_nonce( $nonce, 'elm_submit_request' );
		if ( ! $valid_portal_nonce && ! $valid_submit_nonce ) {
			wp_send_json_error( array( 'message' => __( 'Verifikimi i sigurisë ka skaduar. Rifreskoni faqen dhe provoni përsëri.', 'employee-leave-manager' ) ), 403 );
		}
		if ( ! ELM_Access::user_has_portal_access( wp_get_current_user() ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nuk keni qasje në portalin e pushimeve.', 'employee-leave-manager' ) ), 403 );
		}
	}

	private function send_ajax_wp_error( WP_Error $error ): void {
		$error_data = $error->get_error_data();
		$status = is_array( $error_data ) && ! empty( $error_data['status'] ) ? (int) $error_data['status'] : 400;
		wp_send_json_error(
			array(
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
				'data'    => is_array( $error_data ) ? $error_data : array(),
			),
			$status
		);
	}

	public function form_create_request(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Sesioni ka skaduar. Hyni përsëri.', 'employee-leave-manager' ), '', array( 'response' => 401 ) );
		}
		check_admin_referer( 'elm_submit_request', 'elm_submit_request_nonce' );
		if ( ! ELM_Access::user_has_portal_access( wp_get_current_user() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nuk keni leje të paraqitni kërkesa për pushim.', 'employee-leave-manager' ), '', array( 'response' => 403 ) );
		}

		$data = wp_unslash( $_POST );
		$return_url = wp_validate_redirect( (string) ( $data['return_url'] ?? '' ), wp_get_referer() ?: home_url( '/' ) );
		unset( $data['action'], $data['elm_submit_request_nonce'], $data['_wp_http_referer'], $data['return_url'] );
		$result = $this->persist_request( $data, is_array( $_FILES ) ? $_FILES : array() );
		$return_url = remove_query_arg( array( 'elm_notice', 'elm_message', 'elm_request_id' ), $return_url );
		if ( is_wp_error( $result ) ) {
			$return_url = add_query_arg(
				array(
					'elm_notice' => 'error',
					'elm_message' => $result->get_error_message(),
				),
				$return_url
			);
		} else {
			$return_url = add_query_arg(
				array(
					'elm_notice' => 'success',
					'elm_request_id' => (int) $result['id'],
				),
				$return_url
			);
		}
		wp_safe_redirect( $return_url );
		exit;
	}


	public function form_update_request(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Sesioni ka skaduar. Hyni përsëri.', 'employee-leave-manager' ), '', array( 'response' => 401 ) );
		}
		check_admin_referer( 'elm_submit_request', 'elm_submit_request_nonce' );
		if ( ! ELM_Access::user_has_portal_access( wp_get_current_user() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nuk keni qasje në portalin e pushimeve.', 'employee-leave-manager' ), '', array( 'response' => 403 ) );
		}
		$data = wp_unslash( $_POST );
		$return_url = wp_validate_redirect( (string) ( $data['return_url'] ?? '' ), wp_get_referer() ?: home_url( '/' ) );
		$request_id = absint( $data['request_id'] ?? 0 );
		unset( $data['action'], $data['elm_submit_request_nonce'], $data['_wp_http_referer'], $data['return_url'] );
		if ( $request_id ) {
			$existing_request = ( new ELM_Leave_Service() )->get_request( $request_id );
			if ( ! is_wp_error( $existing_request ) && (int) $existing_request['employee_id'] !== get_current_user_id() && ! ELM_Chief_Access::can_manage_employee( get_current_user_id(), (int) $existing_request['employee_id'] ) ) {
				$existing_request = new WP_Error( 'elm_forbidden', __( 'Ky punonjës nuk është në listën tuaj të menaxhimit në portal.', 'employee-leave-manager' ), array( 'status' => 403 ) );
			}
		} else {
			$existing_request = new WP_Error( 'elm_invalid_request', __( 'Kërkesa është e pavlefshme.', 'employee-leave-manager' ) );
		}
		$result = is_wp_error( $existing_request ) ? $existing_request : $this->persist_request_update( $request_id, $data, is_array( $_FILES ) ? $_FILES : array() );
		$return_url = remove_query_arg( array( 'elm_notice', 'elm_message', 'elm_request_id' ), $return_url );
		if ( is_wp_error( $result ) ) {
			$return_url = add_query_arg( array( 'elm_notice' => 'error', 'elm_message' => $result->get_error_message() ), $return_url );
		} else {
			$return_url = add_query_arg( array( 'elm_notice' => 'success', 'elm_message' => __( 'Kërkesa u përditësua me sukses.', 'employee-leave-manager' ) ), $return_url );
		}
		wp_safe_redirect( $return_url );
		exit;
	}

	public function form_cancel_request(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Sesioni ka skaduar. Hyni përsëri.', 'employee-leave-manager' ), '', array( 'response' => 401 ) );
		}
		check_admin_referer( 'elm_cancel_request', 'elm_cancel_request_nonce' );
		if ( ! ELM_Access::user_has_portal_access( wp_get_current_user() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nuk keni leje t\'i anuloni kërkesat për pushim.', 'employee-leave-manager' ), '', array( 'response' => 403 ) );
		}

		$data = wp_unslash( $_POST );
		$return_url = wp_validate_redirect( (string) ( $data['return_url'] ?? '' ), wp_get_referer() ?: home_url( '/' ) );
		$request_id = absint( $data['request_id'] ?? 0 );
		$cancel_reason = sanitize_textarea_field( (string) ( $data['cancel_reason'] ?? '' ) );
		$cancel_service = new ELM_Leave_Service();
		$existing_request = $request_id ? $cancel_service->get_request( $request_id ) : new WP_Error( 'elm_invalid_request', __( 'Kërkesa është e pavlefshme.', 'employee-leave-manager' ) );
		if ( ! is_wp_error( $existing_request ) && (int) $existing_request['employee_id'] !== get_current_user_id() && ! ELM_Chief_Access::can_manage_employee( get_current_user_id(), (int) $existing_request['employee_id'] ) ) {
			$existing_request = new WP_Error( 'elm_forbidden', __( 'Ky punonjës nuk është në listën tuaj të menaxhimit në portal.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		$result = is_wp_error( $existing_request ) ? $existing_request : $cancel_service->cancel_request( $request_id, get_current_user_id(), $cancel_reason );
		$return_url = remove_query_arg( array( 'elm_notice', 'elm_message', 'elm_request_id' ), $return_url );
		if ( is_wp_error( $result ) ) {
			$return_url = add_query_arg( array( 'elm_notice' => 'error', 'elm_message' => $result->get_error_message() ), $return_url );
		} else {
			$return_url = add_query_arg( array( 'elm_notice' => 'success', 'elm_message' => __( 'Kërkesa u anulua me sukses.', 'employee-leave-manager' ) ), $return_url );
		}
		wp_safe_redirect( $return_url );
		exit;
	}

	private function persist_request( array $data, array $files ): array|WP_Error {
		if ( ! ELM_DB::schema_ready() ) {
			ELM_Activator::install_schema();
		}
		$employee_id = get_current_user_id();
		if ( current_user_can( 'elm_manage_leave' ) && ! empty( $data['employee_id'] ) ) {
			$employee_id = absint( $data['employee_id'] );
			if ( $employee_id !== get_current_user_id() && ! ELM_Chief_Access::can_manage_employee( get_current_user_id(), $employee_id ) ) {
				return new WP_Error( 'elm_forbidden', __( 'Ky punonjës nuk është në listën tuaj të menaxhimit në portal.', 'employee-leave-manager' ), array( 'status' => 403 ) );
			}
		}

		$temporary_document_id = 0;
		$medical_file = is_array( $files['medical_document'] ?? null ) ? $files['medical_document'] : null;
		if ( 'medical' === sanitize_key( (string) ( $data['leave_type'] ?? '' ) ) && ! empty( $medical_file['size'] ) ) {
			$uploaded = ELM_Medical_Storage::store_upload( 'medical_document', $employee_id, $medical_file );
			if ( is_wp_error( $uploaded ) ) {
				return $uploaded;
			}
			$temporary_document_id = (int) $uploaded['id'];
			$data['medical_document_id'] = $temporary_document_id;
		}

		$result = ( new ELM_Leave_Service() )->create_request( $employee_id, $data );
		if ( is_wp_error( $result ) && $temporary_document_id ) {
			ELM_Medical_Storage::purge_if_unlinked( $temporary_document_id, $employee_id );
		}
		return $result;
	}


	private function persist_request_update( int $request_id, array $data, array $files ): array|WP_Error {
		if ( ! ELM_DB::schema_ready() ) {
			ELM_Activator::install_schema();
		}
		$service = new ELM_Leave_Service();
		$existing = $service->get_request( $request_id );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}
		$employee_id = (int) $existing['employee_id'];
		$actor_id = get_current_user_id();
		if ( $actor_id !== $employee_id && ! $this->can_access_employee( $employee_id, false ) ) {
			return $this->employee_access_error();
		}
		if ( 'pending' !== sanitize_key( (string) ( $existing['status'] ?? '' ) ) ) {
			return new WP_Error( 'elm_invalid_status', __( 'Mund të redaktohen vetëm kërkesat në pritje.', 'employee-leave-manager' ), array( 'status' => 409 ) );
		}
		$old_document_id = absint( $existing['medical_document_id'] ?? 0 );
		$data['medical_document_id'] = $old_document_id;
		$temporary_document_id = 0;
		$medical_file = is_array( $files['medical_document'] ?? null ) ? $files['medical_document'] : null;
		$target_type = sanitize_key( (string) ( $data['leave_type'] ?? $existing['leave_type'] ) );
		if ( 'medical' === $target_type && ! empty( $medical_file['size'] ) ) {
			$uploaded = ELM_Medical_Storage::store_upload( 'medical_document', $employee_id, $medical_file );
			if ( is_wp_error( $uploaded ) ) {
				return $uploaded;
			}
			$temporary_document_id = (int) $uploaded['id'];
			$data['medical_document_id'] = $temporary_document_id;
		}
		$result = $service->update_request( $request_id, $data, $actor_id );
		if ( is_wp_error( $result ) ) {
			if ( $temporary_document_id ) {
				ELM_Medical_Storage::purge_if_unlinked( $temporary_document_id, $employee_id );
			}
			return $result;
		}
		$new_document_id = absint( $result['medical_document_id'] ?? 0 );
		if ( $old_document_id && $old_document_id !== $new_document_id ) {
			ELM_Medical_Storage::purge_if_unlinked( $old_document_id, $employee_id );
		}
		return $result;
	}

	public function update_request( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = $request->get_body_params();
		}
		$result = $this->persist_request_update( absint( $request['id'] ), is_array( $data ) ? $data : array(), array() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function decision( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$service = new ELM_Leave_Service();
		$existing = $service->get_request( absint( $request['id'] ) );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}
		if ( ! $this->can_access_employee( (int) $existing['employee_id'], true ) ) {
			return $this->employee_access_error();
		}
		$data = $request->get_json_params();
		$result = $service->decide_request(
			absint( $request['id'] ),
			sanitize_key( (string) ( $data['decision'] ?? '' ) ),
			sanitize_textarea_field( (string) ( $data['note'] ?? '' ) ),
			get_current_user_id()
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function cancel( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$service = new ELM_Leave_Service();
		$existing = $service->get_request( absint( $request['id'] ) );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}
		if ( ! $this->can_access_employee( (int) $existing['employee_id'] ) ) {
			return $this->employee_access_error();
		}
		$data = $request->get_json_params();
		$result = $service->cancel_request( absint( $request['id'] ), get_current_user_id(), sanitize_textarea_field( (string) ( $data['note'] ?? '' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function delete_request( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = $request->get_body_params();
		}
		if ( 'DELETE' !== (string) ( $data['confirmation'] ?? '' ) ) {
			return new WP_Error( 'elm_delete_confirmation', __( 'Shkruani saktësisht DELETE për të konfirmuar fshirjen.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		$service = new ELM_Leave_Service();
		$existing = $service->get_request( absint( $request['id'] ) );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}
		if ( ! $this->can_access_employee( (int) $existing['employee_id'] ) ) {
			return $this->employee_access_error();
		}
		$result = $service->delete_request( absint( $request['id'] ), get_current_user_id() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function adjustments( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = absint( $request['user_id'] );
		if ( ! $user_id || ! $this->can_access_employee( $user_id ) ) {
			return $this->employee_access_error();
		}
		return rest_ensure_response(
			( new ELM_Leave_Service() )->list_adjustments(
				array( 'user_id' => $user_id, 'year' => absint( $request['year'] ) )
			)
		);
	}

	public function create_adjustment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		$user_id = absint( $data['user_id'] ?? 0 );
		if ( ! $user_id || ! $this->can_access_employee( $user_id, false ) ) {
			return $this->employee_access_error();
		}
		$result = ( new ELM_Leave_Service() )->add_adjustment(
			$user_id,
			absint( $data['year'] ?? 0 ),
			(float) ( $data['amount'] ?? 0 ),
			sanitize_textarea_field( (string) ( $data['note'] ?? '' ) ),
			get_current_user_id()
		);
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 201 );
	}

	public function entitlement_changes( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = absint( $request['user_id'] );
		if ( $user_id && ! $this->can_access_employee( $user_id ) ) {
			return $this->employee_access_error();
		}
		return rest_ensure_response(
			( new ELM_Entitlement_Service() )->list(
				array(
					'user_id' => $user_id,
					'status'  => sanitize_key( (string) $request['status'] ),
				),
				get_current_user_id()
			)
		);
	}

	public function create_entitlement_change( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = $request->get_body_params();
		}
		$user_id = absint( $data['user_id'] ?? 0 ) ?: get_current_user_id();
		if ( ! $this->can_access_employee( $user_id ) ) {
			return $this->employee_access_error();
		}
		$result = ( new ELM_Entitlement_Service() )->create(
			$user_id,
			absint( $data['requested_entitlement'] ?? 0 ),
			absint( $data['effective_year'] ?? 0 ),
			sanitize_textarea_field( (string) ( $data['reason'] ?? '' ) ),
			get_current_user_id()
		);
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 201 );
	}

	public function decide_entitlement_change( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$employee_id = $this->entitlement_change_employee_id( absint( $request['id'] ) );
		if ( ! $employee_id || ! $this->can_access_employee( $employee_id, false ) ) {
			return $this->employee_access_error();
		}
		$data = $request->get_json_params();
		$result = ( new ELM_Entitlement_Service() )->decide(
			absint( $request['id'] ),
			sanitize_key( (string) ( $data['decision'] ?? '' ) ),
			sanitize_textarea_field( (string) ( $data['note'] ?? '' ) ),
			get_current_user_id()
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function cancel_entitlement_change( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$employee_id = $this->entitlement_change_employee_id( absint( $request['id'] ) );
		if ( ! $employee_id || ! $this->can_access_employee( $employee_id ) ) {
			return $this->employee_access_error();
		}
		$result = ( new ELM_Entitlement_Service() )->cancel( absint( $request['id'] ), get_current_user_id() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function users( WP_REST_Request $request ): WP_REST_Response {
		if ( current_user_can( 'manage_options' ) ) {
			$users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC', 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
		} else {
			$visible_ids = ELM_Chief_Access::visible_employee_ids( get_current_user_id() );
			$users = $visible_ids ? get_users( array( 'include' => $visible_ids, 'orderby' => 'display_name', 'order' => 'ASC', 'fields' => array( 'ID', 'display_name', 'user_email' ) ) ) : array();
		}
		$year = absint( $request->get_param( 'year' ) );
		$include_directory_data = $year > 0;
		$data = array();
		foreach ( $users as $user ) {
			if ( user_can( $user->ID, 'elm_submit_leave' ) || user_can( $user->ID, 'elm_view_own_leave' ) || user_can( $user->ID, 'elm_manage_leave' ) ) {
				$item = array(
					'id'    => (int) $user->ID,
					'name'  => $user->display_name,
					'email' => $user->user_email,
				);
				if ( $include_directory_data ) {
					$balance = ELM_DB::schema_ready() ? ( new ELM_Balance_Service() )->summary( (int) $user->ID, $year ) : array();
					$item['position'] = ELM_Employee_Profile::position( (int) $user->ID );
					$item['sector'] = ELM_Employee_Profile::sector( (int) $user->ID );
					$item['year'] = $year;
					$item['balance'] = array(
						'total'     => (float) ( $balance['total_entitlement'] ?? 0 ),
						'used'      => (float) ( $balance['approved_used'] ?? 0 ),
						'pending'   => (float) ( $balance['pending_units'] ?? 0 ),
						'remaining' => (float) ( $balance['remaining_after_pending'] ?? 0 ),
					);
				}
				$data[] = $item;
			}
		}
		return rest_ensure_response( $data );
	}

	public function update_employee_profile( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = absint( $request['id'] );
		if ( ! $this->can_access_employee( $user_id, false ) ) {
			return $this->employee_access_error();
		}
		$result = ELM_Employee_Profile::update_for_manager(
			$user_id,
			$request->get_param( 'position' ),
			$request->get_param( 'sector' ),
			get_current_user_id()
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
	private function redact_calendar_occupancy( array $calendar ): array {
		$days = isset( $calendar['days'] ) && is_array( $calendar['days'] ) ? $calendar['days'] : array();
		foreach ( $days as $date => $day ) {
			if ( ! is_array( $day ) ) {
				continue;
			}
			$blocked = ! empty( $day['capacity_blocked'] ) || ! empty( $day['disabled'] );
			$day['approved'] = 0;
			$day['pending'] = 0;
			$day['traffic'] = $blocked ? 'red' : 'green';
			$days[ $date ] = $day;
		}
		$calendar['days'] = $days;
		return $calendar;
	}

}
