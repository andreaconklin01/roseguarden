<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_Privacy {
	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
	}

	public static function register_exporter( array $exporters ): array {
		$exporters['employee-leave-manager'] = array(
			'exporter_friendly_name' => __( 'Menaxhimi i Pushimeve të Punonjësve', 'employee-leave-manager' ),
			'callback'               => array( __CLASS__, 'export_personal_data' ),
		);
		return $exporters;
	}

	public static function register_eraser( array $erasers ): array {
		$erasers['employee-leave-manager'] = array(
			'eraser_friendly_name' => __( 'Regjistri i Burimeve Njerëzore për Pushimet e Punonjësve', 'employee-leave-manager' ),
			'callback'             => array( __CLASS__, 'erase_personal_data' ),
		);
		return $erasers;
	}

	public static function export_personal_data( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}
		$service = new ELM_Leave_Service();
		$requests = $service->list_requests( array(), (int) $user->ID );
		$adjustments = $service->list_adjustments( array( 'user_id' => (int) $user->ID ) );
		$entitlement_changes = ELM_DB::schema_ready() ? ( new ELM_Entitlement_Service() )->list( array( 'user_id' => (int) $user->ID ), (int) $user->ID ) : array();
		$data = array(
			array(
				'group_id'    => 'elm-employee-details',
				'group_label' => __( 'Të dhënat e punonjësit', 'employee-leave-manager' ),
				'item_id'     => 'employee-details-' . $user->ID,
				'data'        => array(
					array( 'name' => __( 'Pozita', 'employee-leave-manager' ), 'value' => ELM_Employee_Profile::position( (int) $user->ID ) ),
					array( 'name' => __( 'Sektori/Njësia', 'employee-leave-manager' ), 'value' => ELM_Employee_Profile::sector( (int) $user->ID ) ),
				),
			),
		);
		foreach ( $requests as $request ) {
			$data[] = array(
				'group_id'    => 'elm-leave-requests',
				'group_label' => __( 'Kërkesat për pushim', 'employee-leave-manager' ),
				'item_id'     => 'leave-request-' . $request['id'],
				'data'        => array(
					array( 'name' => __( 'ID-ja e kërkesës', 'employee-leave-manager' ), 'value' => $request['id'] ),
					array( 'name' => __( 'Pozita në kohën e paraqitjes', 'employee-leave-manager' ), 'value' => null === ( $request['employee_position'] ?? null ) ? '' : $request['employee_position'] ),
					array( 'name' => __( 'Sektori/Njësia në kohën e paraqitjes', 'employee-leave-manager' ), 'value' => null === ( $request['employee_sector'] ?? null ) ? '' : $request['employee_sector'] ),
					array( 'name' => __( 'Lloji i pushimit', 'employee-leave-manager' ), 'value' => $request['leave_type'] ),
					array( 'name' => __( 'Datat', 'employee-leave-manager' ), 'value' => $request['start_date'] . ' to ' . $request['end_date'] ),
					array( 'name' => __( 'Ditë pune', 'employee-leave-manager' ), 'value' => $request['requested_units'] ),
					array( 'name' => __( 'Statusi', 'employee-leave-manager' ), 'value' => $request['status'] ),
					array( 'name' => __( 'Arsyetimi', 'employee-leave-manager' ), 'value' => $request['reason'] ),
					array( 'name' => __( 'Paraqitur më', 'employee-leave-manager' ), 'value' => $request['submitted_at'] ),
					array( 'name' => __( 'Arsyetimi i vendimit', 'employee-leave-manager' ), 'value' => $request['decision_note'] ?: '' ),
					array( 'name' => __( 'Dokument mjekësor i bashkëngjitur', 'employee-leave-manager' ), 'value' => $request['medical_document_id'] ? __( 'Po', 'employee-leave-manager' ) : __( 'Jo', 'employee-leave-manager' ) ),
				),
			);
		}
		foreach ( $adjustments as $adjustment ) {
			$data[] = array(
				'group_id'    => 'elm-balance-adjustments',
				'group_label' => __( 'Ditët shtesë të pushimit sipas vitit', 'employee-leave-manager' ),
				'item_id'     => 'balance-adjustment-' . $adjustment['id'],
				'data'        => array(
					array( 'name' => __( 'Viti i pushimit', 'employee-leave-manager' ), 'value' => $adjustment['leave_year'] ),
					array( 'name' => __( 'Ditë shtesë', 'employee-leave-manager' ), 'value' => $adjustment['amount'] ),
					array( 'name' => __( 'Arsyetimi', 'employee-leave-manager' ), 'value' => $adjustment['note'] ),
					array( 'name' => __( 'Krijuar më', 'employee-leave-manager' ), 'value' => $adjustment['created_at'] ),
				),
			);
		}
		foreach ( $entitlement_changes as $change ) {
			$data[] = array(
				'group_id'    => 'elm-entitlement-changes',
				'group_label' => __( 'Ndryshimet e numrit vjetor të ditëve të pushimit', 'employee-leave-manager' ),
				'item_id'     => 'entitlement-change-' . $change['id'],
				'data'        => array(
					array( 'name' => __( 'Numri i ditëve para ndryshimit', 'employee-leave-manager' ), 'value' => $change['current_entitlement'] ),
					array( 'name' => __( 'Numri i kërkuar i ditëve', 'employee-leave-manager' ), 'value' => $change['requested_entitlement'] ),
					array( 'name' => __( 'Viti i hyrjes në fuqi', 'employee-leave-manager' ), 'value' => $change['effective_year'] ),
					array( 'name' => __( 'Arsyetimi', 'employee-leave-manager' ), 'value' => $change['reason'] ),
					array( 'name' => __( 'Statusi', 'employee-leave-manager' ), 'value' => $change['status'] ),
					array( 'name' => __( 'Arsyetimi i vendimit', 'employee-leave-manager' ), 'value' => $change['decision_note'] ?: '' ),
				),
			);
		}
		return array( 'data' => $data, 'done' => true );
	}

	public static function erase_personal_data( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		return array(
			'items_removed'  => false,
			'items_retained' => true,
			'messages'       => array(
				__( 'Kërkesat për pushim, vendimet, korrigjimet e gjendjes dhe ndryshimet e numrit vjetor të ditëve të pushimit janë ruajtur sepse kjo shtojcë është konfiguruar si regjistër i përhershëm i burimeve njerëzore. Para anonimizimit ose asgjësimit, shqyrtoni legjislacionin përkatës të punës, privatësisë dhe afateve të ruajtjes.', 'employee-leave-manager' ),
			),
			'done'           => true,
		);
	}

	public static function privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'Shtojca për menaxhimin e pushimeve ruan identifikuesit e punonjësve, pozitën dhe sektorin/njësinë, datat dhe arsyetimet e kërkesave për pushim, vendimet e miratimit, korrigjimet e gjendjes, ndryshimet e numrit vjetor të ditëve të pushimit dhe ngjarjet e auditimit. Dokumentet mjekësore, kur ngarkohen, enkriptohen para ruajtjes dhe mund të shkarkohen vetëm pas verifikimit të lejeve.', 'employee-leave-manager' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Shtojca është projektuar si regjistër i përhershëm i burimeve njerëzore dhe nuk i fshin të dhënat gjatë përdorimit normal ose çinstalimit. Operatori i faqes është përgjegjës për përcaktimin e afateve ligjore të ruajtjes, kontrolleve të qasjes, kopjeve rezervë, reagimit ndaj incidenteve dhe çdo procedure të nevojshme të anonimizimit.', 'employee-leave-manager' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Menaxhimi i Pushimeve të Punonjësve', 'employee-leave-manager' ), wp_kses_post( wpautop( $content, false ) ) );
	}
}
