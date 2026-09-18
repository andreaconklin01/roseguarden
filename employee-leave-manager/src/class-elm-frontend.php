<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_Frontend {
	private static bool $localized = false;

	public function register_shortcodes(): void {
		add_shortcode( 'elm_leave_portal', array( $this, 'render_portal' ) );
		add_shortcode( 'employee_leave_manager', array( $this, 'render_portal' ) );
	}

	public function register_assets(): void {
		wp_register_style( 'elm-portal', ELM_URL . 'assets/css/portal.css', array(), ELM_VERSION );
		wp_register_script( 'elm-portal', ELM_URL . 'assets/js/portal.js', array(), ELM_VERSION, false );
		wp_script_add_data( 'elm-portal', 'strategy', 'defer' );

		if ( $this->current_page_has_portal() ) {
			$this->enqueue_assets();
		}
	}

	private function current_page_has_portal(): bool {
		global $post;

		return is_object( $post )
			&& isset( $post->post_content )
			&& ( has_shortcode( (string) $post->post_content, 'elm_leave_portal' ) || has_shortcode( (string) $post->post_content, 'employee_leave_manager' ) );
	}

	private function enqueue_assets(): void {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'elm-portal' );
		wp_enqueue_script( 'elm-portal' );

		if ( self::$localized ) {
			return;
		}

		self::$localized = true;
		wp_localize_script(
			'elm-portal',
			'ELMPortal',
			array(
				'ajaxUrl'                 => admin_url( 'admin-ajax.php' ),
				'ajaxNonce'               => wp_create_nonce( 'elm_submit_request' ),
				'portalNonce'             => wp_create_nonce( 'elm_portal_action' ),
				'cancelFormNonce'         => wp_create_nonce( 'elm_cancel_request' ),
				'restUrl'                 => esc_url_raw( rest_url( 'elm/v1/' ) ),
				'restNonce'               => wp_create_nonce( 'wp_rest' ),
				'currentUserId'           => get_current_user_id(),
				'canManageLeave'          => current_user_can( 'elm_manage_leave' ),
				'canManageSettings'       => current_user_can( 'elm_adjust_balances' ) || current_user_can( 'manage_options' ),
				'canApproveExceptions'    => current_user_can( 'elm_adjust_balances' ),
				'canViewMedical'          => current_user_can( 'elm_view_medical_documents' ),
				'canDeleteRequests'       => current_user_can( 'elm_manage_leave' ) || current_user_can( 'elm_adjust_balances' ) || current_user_can( 'manage_options' ),
				'exportRequestBase'       => admin_url( 'admin-post.php?action=elm_export_request_pdf&elm_frontend=1&request_id=' ),
				'exportRequestNonce'      => wp_create_nonce( 'elm_export_request_pdf_frontend' ),
				'exportEmployeeBase'      => admin_url( 'admin-post.php?action=elm_export_employee_pdf&elm_frontend=1&user_id=' ),
				'exportEmployeeNonce'     => wp_create_nonce( 'elm_export_employee_pdf_frontend' ),
				'downloadMedicalBase'     => admin_url( 'admin-post.php?action=elm_download_medical&elm_frontend=1&document_id=' ),
				'downloadMedicalNonce'    => wp_create_nonce( 'elm_download_medical_frontend' ),
				'today'              => ELM_Policy::today(),
				'noticeEnd'          => current_datetime()->modify( '+14 days' )->format( 'Y-m-d' ),
				'year'               => (int) current_datetime()->format( 'Y' ),
				'i18n'               => array(
					'loading'          => __( 'Po ngarkohet...', 'employee-leave-manager' ),
					'genericError'     => __( 'Ndodhi një gabim. Provoni përsëri.', 'employee-leave-manager' ),
					'cancelConfirm'       => __( 'Ta anuloni këtë kërkesë? Veprimi do të regjistrohet në regjistrin e auditimit.', 'employee-leave-manager' ),
					'cancelReasonPrompt'  => __( 'Shkruani arsyetimin e anulimit:', 'employee-leave-manager' ),
					'cancelReasonRequired'=> __( 'Shkruani arsyetimin e anulimit.', 'employee-leave-manager' ),
					'cancelledMessage'    => __( 'Kërkesa u anulua. Veprimi u regjistrua në regjistrin e auditimit.', 'employee-leave-manager' ),
					'deleteOwnConfirm'    => __( 'Kërkesa do të fshihet përgjithmonë. Ky veprim nuk mund të zhbëhet.', 'employee-leave-manager' ),
					'deleteOwnPrompt'     => __( 'Shkruani saktësisht DELETE për të konfirmuar fshirjen:', 'employee-leave-manager' ),
					'deleteOwnSuccess'    => __( 'Kërkesa u fshi përgjithmonë.', 'employee-leave-manager' ),
					'noRequests'       => __( 'Nuk u gjet asnjë kërkesë për pushim.', 'employee-leave-manager' ),
					'submitRequest'    => __( 'Paraqit kërkesën', 'employee-leave-manager' ),
					'annual'           => __( 'Pushim vjetor', 'employee-leave-manager' ),
					'medical'          => __( 'Pushim mjekësor', 'employee-leave-manager' ),
					'cancel'           => __( 'Anulo', 'employee-leave-manager' ),
					'edit'             => __( 'Redakto', 'employee-leave-manager' ),
					'saveChanges'      => __( 'Ruaj ndryshimet', 'employee-leave-manager' ),
					'cancelEdit'       => __( 'Anulo redaktimin', 'employee-leave-manager' ),
					'updated'          => __( 'Kërkesa nr. %d u përditësua me sukses.', 'employee-leave-manager' ),
					'editingRequest'   => __( 'Po redaktohet kërkesa nr. %d', 'employee-leave-manager' ),
					'newRequest'       => __( 'Kërkesë e re', 'employee-leave-manager' ),
					'requestLeave'     => __( 'Kërko pushim', 'employee-leave-manager' ),
					'editPendingOnly'  => __( 'Mund të redaktohen vetëm kërkesat në pritje.', 'employee-leave-manager' ),
					'pdfReport'        => __( 'Shkarko PDF', 'employee-leave-manager' ),
					'noneSelected'     => __( 'asnjë', 'employee-leave-manager' ),
					'chooseDates'      => __( 'Zgjidhni së paku një ditë të disponueshme në kalendar.', 'employee-leave-manager' ),
					'reasonRequired'   => __( 'Shkruani arsyetimin para paraqitjes.', 'employee-leave-manager' ),
					'medicalRequired'  => __( 'Konfirmoni dokumentacionin mjekësor ose bashkëngjitni dokumentin.', 'employee-leave-manager' ),
					'saved'            => __( 'Kërkesa nr. %d u paraqit me sukses.', 'employee-leave-manager' ),
					'pendingWarning'   => __( 'Ka kërkesa në pritje për datat: %s.', 'employee-leave-manager' ),
					'unavailable'      => __( 'Kjo datë nuk është e disponueshme.', 'employee-leave-manager' ),
					'workingDays'     => __( 'Do të llogariten %d ditë pune.', 'employee-leave-manager' ),
					'calendarDays'    => __( '%d ditë kalendarike të zgjedhura.', 'employee-leave-manager' ),
					'rangeHint'       => __( 'Zgjidhni datën e parë dhe të fundit për të plotësuar periudhën. Klikoni përsëri në një datë të zgjedhur për ta përjashtuar vetëm atë datë.', 'employee-leave-manager' ),
					'skippedDates'    => __( 'Datat e padisponueshme u anashkaluan: %s.', 'employee-leave-manager' ),
					'ownRequest'      => __( 'Tashmë keni një kërkesë me statusin %s për këtë ditë.', 'employee-leave-manager' ),
					'periodOneAvailable'   => __( 'Kërkesa për pushim vjetor paraqitet së paku 15 ditë para fillimit.', 'employee-leave-manager' ),
					'shortNoticeWarning'   => __( 'Afati minimal 15-ditor nuk plotësohet për këto data: %2$s. Kërkesa mund të vazhdojë dhe vërejtja do t\'i shfaqet mbikëqyrësit gjatë shqyrtimit.', 'employee-leave-manager' ),
					'periodOneWarning'     => __( 'Periudha janar-qershor ka një prag informues prej %1$d ditësh pune. Përzgjedhja aktuale e kalon këtë prag me %2$d ditë (%3$s). Kërkesa mund të vazhdojë; mbikëqyrësi do ta shohë këtë vërejtje gjatë shqyrtimit.', 'employee-leave-manager' ),
					'chiefShortNoticeWarning' => __( 'Vërejtje: kjo kërkesë përfshin data që nuk e plotësojnë afatin minimal 15-ditor. Datat: %s.', 'employee-leave-manager' ),
					'chiefShortNoticeConfirm' => __( 'Kjo kërkesë përfshin data brenda afatit minimal 15-ditor: %s. Shqyrtojeni këtë vërejtje para miratimit.', 'employee-leave-manager' ),
					'chiefPeriodOneWarning' => __( 'Vërejtje: kjo kërkesë kalon pragun informues prej 10 ditësh pune për periudhën janar-qershor. Ditët mbi prag: %s.', 'employee-leave-manager' ),
					'chiefPeriodOneConfirm' => __( 'Kjo kërkesë kalon pragun informues 10-ditor për periudhën janar-qershor. Ditët mbi prag: %s. Vazhdoni me miratimin vetëm pasi ta keni shqyrtuar këtë tejkalim.', 'employee-leave-manager' ),
					'policyWarningSaved'   => __( '', 'employee-leave-manager' ),
					'chiefApprovalRequired'=> __( '', 'employee-leave-manager' ),
					'warningPendingDay'     => __( '', 'employee-leave-manager' ),
					'policyExceptionDay'    => __( '', 'employee-leave-manager' ),
					'employeeReason'       => __( 'Arsyetimi i punonjësit', 'employee-leave-manager' ),
					'chiefRequestReason'    => __( 'Arsyetimi i kërkesës nga mbikëqyrësi', 'employee-leave-manager' ),
					'chiefDecisionReason'  => __( 'Arsyetimi i vendimit nga mbikëqyrësi', 'employee-leave-manager' ),
					'employeeCancelReason' => __( 'Arsyetimi i anulimit nga punonjësi', 'employee-leave-manager' ),
					'chiefCancelReason'    => __( 'Arsyetimi i anulimit nga mbikëqyrësi', 'employee-leave-manager' ),
					'employerReasons'      => __( 'Punonjësi', 'employee-leave-manager' ),
					'chiefReasons'         => __( 'Mbikëqyrësi', 'employee-leave-manager' ),
					'totalExceeded'        => __( 'Pas llogaritjes së kërkesave në pritje mbeten %d ditë pune.', 'employee-leave-manager' ),
					'myRequirements'        => __( 'Kërkesat e mia', 'employee-leave-manager' ),
					'employerRequirements'  => __( 'Kërkesat e punonjësve', 'employee-leave-manager' ),
					'settings'              => __( 'Cilësimet', 'employee-leave-manager' ),
					'allStatuses'           => __( 'Të gjitha statuset', 'employee-leave-manager' ),
					'allEmployers'          => __( 'Të gjithë punonjësit', 'employee-leave-manager' ),
					'applyFilters'          => __( 'Zbato filtrat', 'employee-leave-manager' ),
					'history'               => __( 'Historiku', 'employee-leave-manager' ),
					'historyTitle'          => __( 'Historiku i pushimeve', 'employee-leave-manager' ),
					'historyAllEmployees'   => __( 'Të gjithë punonjësit', 'employee-leave-manager' ),
					'historyNoRequests'     => __( 'Nuk u gjet asnjë kërkesë që përputhet me filtrat.', 'employee-leave-manager' ),
					'historyTotalDays'      => __( 'Gjithsej ditë pushimi', 'employee-leave-manager' ),
					'historyUsedDays'       => __( 'Të shfrytëzuara', 'employee-leave-manager' ),
					'historyPendingDays'    => __( 'Në pritje', 'employee-leave-manager' ),
					'historyRemainingDays'  => __( 'Të mbetura', 'employee-leave-manager' ),
					'historyRequestedAt'    => __( 'Paraqitur më', 'employee-leave-manager' ),
					'historyLeavePeriod'    => __( 'Periudha e zgjedhur', 'employee-leave-manager' ),
					'historyDecision'       => __( 'Statusi', 'employee-leave-manager' ),
					'historyEmployeeSummary'=> __( 'Përmbledhja e punonjësve', 'employee-leave-manager' ),
					'historyRequestDetails' => __( 'Historiku i kërkesave', 'employee-leave-manager' ),
					'historySelectedDates'  => __( 'Datat e zgjedhura', 'employee-leave-manager' ),
					'historyReason'         => __( 'Arsyetimi', 'employee-leave-manager' ),
					'historyTotalRequests'  => __( 'Gjithsej kërkesa', 'employee-leave-manager' ),
					'historyAnnualBalance'  => __( 'Gjendja e pushimit vjetor', 'employee-leave-manager' ),
					'approve'               => __( 'Mirato', 'employee-leave-manager' ),
					'approveException'      => __( 'Mirato', 'employee-leave-manager' ),
					'reject'                => __( 'Refuzo', 'employee-leave-manager' ),
					'approvePrompt'         => __( 'Arsyetim për miratimin (opsional):', 'employee-leave-manager' ),
					'exceptionConfirm'      => __( 'Ta miratoni këtë kërkesë?', 'employee-leave-manager' ),
					'exceptionPrompt'       => __( 'Arsyetim për miratimin (opsional):', 'employee-leave-manager' ),
					'rejectPrompt'          => __( 'Arsyetimi i refuzimit:', 'employee-leave-manager' ),
					'chiefOnly'             => __( 'Nuk keni leje ta miratoni këtë kërkesë.', 'employee-leave-manager' ),
					'cancelManagementPrompt'=> __( 'Arsyetimi i anulimit:', 'employee-leave-manager' ),
					'operationSaved'        => __( 'Kërkesa u përditësua me sukses.', 'employee-leave-manager' ),
					'noReviewRequests'      => __( 'Nuk u gjet asnjë kërkesë për shqyrtim.', 'employee-leave-manager' ),
					'noFilteredRequests'    => __( 'Nuk u gjet asnjë kërkesë që përputhet me filtrat.', 'employee-leave-manager' ),
					'editEmployerRequest'   => __( 'Redakto kërkesën e punonjësit', 'employee-leave-manager' ),
					'editCalendarHint'      => __( 'Klikoni në një datë pune për ta zgjedhur ose hequr nga përzgjedhja. Datat ekzistuese të kërkesës mbeten të redaktueshme.', 'employee-leave-manager' ),
					'settingsSaved'         => __( 'Cilësimet u ruajtën me sukses.', 'employee-leave-manager' ),
					'daysCount'             => __( '%d ditë', 'employee-leave-manager' ),
					'medicalFile'           => __( 'Dokumenti mjekësor', 'employee-leave-manager' ),
					'delete'                => __( 'Fshi', 'employee-leave-manager' ),
					'deletePermanently'     => __( 'Fshi përgjithmonë', 'employee-leave-manager' ),
					'deleteRequestTitle'    => __( 'Fshi kërkesën e punonjësit', 'employee-leave-manager' ),
					'deleteRequestHelp'     => __( 'Kërkesa do të fshihet përgjithmonë. Ky veprim nuk mund të zhbëhet. Shkruani DELETE për të vazhduar.', 'employee-leave-manager' ),
					'deleteRequestLabel'    => __( 'Shkruani DELETE', 'employee-leave-manager' ),
					'deleteRequestMismatch' => __( 'Shkruani saktësisht DELETE për të aktivizuar fshirjen.', 'employee-leave-manager' ),
					'deleteRequestSuccess'  => __( 'Kërkesa u fshi përgjithmonë.', 'employee-leave-manager' ),
					'entitlementChange'     => __( 'Kërko +1 ditë për përvojë', 'employee-leave-manager' ),
					'entitlementRequested'  => __( 'Kërkesa për +1 ditë për përvojë pune iu dërgua mbikëqyrësit për verifikim dhe vendim.', 'employee-leave-manager' ),
					'entitlementCancelled'  => __( 'Kërkesa në pritje u anulua.', 'employee-leave-manager' ),
					'entitlementCancelConfirm'=> __( 'Ta anuloni këtë kërkesë në pritje?', 'employee-leave-manager' ),
					'entitlementNoHistory'   => __( 'Nuk u gjet asnjë kërkesë për +1 ditë.', 'employee-leave-manager' ),
					'entitlementNoDecisionHistory'=> __( 'Nuk u gjet asnjë kërkesë +1 ditë në historik.', 'employee-leave-manager' ),
					'entitlementNoPending'   => __( 'Nuk u gjet asnjë kërkesë +1 ditë në pritje.', 'employee-leave-manager' ),
					'entitlementSelectEmployee'=> __( 'Zgjidhni një punonjës për ta parë historikun e ndryshimeve.', 'employee-leave-manager' ),
					'entitlementApprovePrompt'=> __( 'Arsyetim për miratimin (opsional):', 'employee-leave-manager' ),
					'entitlementRejectPrompt'=> __( 'Arsyetimi i refuzimit:', 'employee-leave-manager' ),
					'entitlementSaved'       => __( 'Vendimi u ruajt.', 'employee-leave-manager' ),
					'entitlementApproved'    => __( 'Kërkesa për +1 ditë për përvojë pune u miratua.', 'employee-leave-manager' ),
					'entitlementRejected'    => __( 'Kërkesa për +1 ditë për përvojë pune u refuzua.', 'employee-leave-manager' ),
					'entitlementView'        => __( 'Shiko kërkesën +1', 'employee-leave-manager' ),
					'entitlementAccessSaved' => __( 'Cilësimi i kërkesave për ndryshim u ruajt.', 'employee-leave-manager' ),
					'entitlementEnabled'     => __( 'Aktivizuar', 'employee-leave-manager' ),
					'entitlementDisabled'    => __( 'Çaktivizuar', 'employee-leave-manager' ),
					'crossYear'             => __( 'Periudha mund të vazhdojë në vitin e ardhshëm kalendarik.', 'employee-leave-manager' ),
					'employeeDetailsSaved'  => __( 'Të dhënat e punonjësit u ruajtën.', 'employee-leave-manager' ),
					'noEmployees'           => __( 'Nuk u gjet asnjë punonjës.', 'employee-leave-manager' ),
					'generateReport'        => __( 'Krijo PDF', 'employee-leave-manager' ),
					'adjustmentSaved'       => __( 'Ditët shtesë u shtuan për vitin e zgjedhur.', 'employee-leave-manager' ),
					'noAdjustments'         => __( 'Nuk u gjet asnjë regjistrim për ditë shtesë.', 'employee-leave-manager' ),
					'adjustmentSelectEmployee'=> __( 'Zgjidhni një punonjës për t\'i parë ditët shtesë.', 'employee-leave-manager' ),
					'auditValid'            => __( 'Regjistri i auditimit u verifikua me sukses.', 'employee-leave-manager' ),
					'auditInvalid'          => __( 'Verifikimi i regjistrit të auditimit dështoi.', 'employee-leave-manager' ),
					'accessModeSaved'      => __( 'Cilësimet e qasjes u ruajtën.', 'employee-leave-manager' ),
					'invalidPortalPage'    => __( 'Zgjidhni një faqe të publikuar të portalit para aktivizimit të qasjes vetëm përmes portalit.', 'employee-leave-manager' ),
				),
			)
		);
	}

	private function portal_return_url(): string {
		$portal_url = ELM_Access::portal_url();
		if ( '' !== $portal_url ) {
			return $portal_url;
		}

		$queried_id = get_queried_object_id();
		if ( $queried_id ) {
			$permalink = get_permalink( $queried_id );
			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		return home_url( '/' );
	}

	private function render_logged_out_portal(): string {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'elm-portal' );

		$redirect_url = $this->portal_return_url();
		$brand_name   = __( 'Komuna e Prishtinës', 'employee-leave-manager' );
		$logo_url     = ELM_URL . 'assets/images/request-pdf-municipality-logo.png';
		$login_form   = wp_login_form(
			array(
				'echo'           => false,
				'redirect'       => $redirect_url,
				'form_id'        => 'elm-portal-loginform',
				'label_username' => __( 'Përdoruesi ose emaili', 'employee-leave-manager' ),
				'label_password' => __( 'Fjalëkalimi', 'employee-leave-manager' ),
				'label_remember' => __( 'Më mbaj mend', 'employee-leave-manager' ),
				'label_log_in'   => __( 'Hyr', 'employee-leave-manager' ),
				'remember'       => true,
			),
		);

		ob_start();
		?>
		<div class="elm-portal elm-login-portal">
			<div class="elm-login-brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="elm-brand-link">
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" class="elm-brand-logo">
					<span class="elm-brand-name"><?php echo esc_html( $brand_name ); ?></span>
				</a>
			</div>

			<section class="elm-login-card" aria-labelledby="elm-login-title">
				<div class="elm-login-card__intro">
					<span class="elm-login-icon" aria-hidden="true"><span class="dashicons dashicons-lock"></span></span>
					<div>
						<p class="elm-eyebrow"><?php esc_html_e( 'Portali i pushimeve', 'employee-leave-manager' ); ?></p>
						<h2 id="elm-login-title"><?php esc_html_e( 'Hyr në portal', 'employee-leave-manager' ); ?></h2>
						<p><?php esc_html_e( 'Përdorni llogarinë tuaj për të vazhduar.', 'employee-leave-manager' ); ?></p>
					</div>
				</div>

				<div class="elm-login-form">
					<?php echo $login_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by wp_login_form(). ?>
				</div>

				<div class="elm-login-help">
					<a href="<?php echo esc_url( wp_lostpassword_url( $redirect_url ) ); ?>"><?php esc_html_e( 'Keni harruar fjalëkalimin?', 'employee-leave-manager' ); ?></a>
				</div>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function render_portal(): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_logged_out_portal();
		}
		if ( ! ELM_Access::user_has_portal_access( wp_get_current_user() ) && ! current_user_can( 'manage_options' ) ) {
			return '<div class="elm-login-required">' . esc_html__( 'Nuk keni qasje në portalin e pushimeve.', 'employee-leave-manager' ) . '</div>';
		}

		$this->enqueue_assets();

		$year          = (int) current_datetime()->format( 'Y' );
		$schema_ready  = ELM_DB::schema_ready();
		$service             = new ELM_Leave_Service();
		$is_manager          = current_user_can( 'elm_manage_leave' );
		$can_manage_settings = current_user_can( 'elm_adjust_balances' ) || current_user_can( 'manage_options' );
		$can_manage_plus_one_display = $is_manager || current_user_can( 'manage_options' );
		$can_view_settings = $can_manage_settings || $can_manage_plus_one_display;
		$portal_settings     = $can_view_settings ? ELM_Policy::settings() : array();
		$can_manage_access_mode = current_user_can( 'manage_options' );
		$portal_pages = $can_manage_access_mode ? get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC', 'post_status' => 'publish' ) ) : array();
		$requests            = $schema_ready ? $service->list_requests( array( 'employee_id' => get_current_user_id() ), get_current_user_id() ) : array();
		$balance       = $schema_ready ? ( new ELM_Balance_Service() )->summary( get_current_user_id(), $year ) : array();
		$entitlement_service = new ELM_Entitlement_Service();
		$entitlement_enabled = ELM_Entitlement_Service::employee_request_enabled( get_current_user_id(), $year );
		$current_entitlement_change = $schema_ready ? $entitlement_service->change_for_year( get_current_user_id(), $year, get_current_user_id() ) : null;
		$current_entitlement_status = is_array( $current_entitlement_change ) ? sanitize_key( (string) ( $current_entitlement_change['status'] ?? '' ) ) : '';
		$can_start_entitlement_change = $schema_ready && ELM_Entitlement_Service::can_employee_create_for_year( get_current_user_id(), $year );
		$show_entitlement_toggle = $entitlement_enabled;
		$flash_kind    = isset( $_GET['elm_notice'] ) && 'success' === sanitize_key( wp_unslash( $_GET['elm_notice'] ) ) ? 'success' : 'error';
		$flash_message = '';
		if ( isset( $_GET['elm_notice'] ) ) {
			if ( 'success' === $flash_kind && ! empty( $_GET['elm_request_id'] ) ) {
				$flash_message = sprintf( __( 'Kërkesa nr. %d u paraqit me sukses.', 'employee-leave-manager' ), absint( $_GET['elm_request_id'] ) );
			} elseif ( ! empty( $_GET['elm_message'] ) ) {
				$flash_message = sanitize_text_field( wp_unslash( $_GET['elm_message'] ) );
			}
		}
		$instance              = wp_unique_id( 'elm-portal-' );
		$calendar_month         = current_datetime()->modify( 'first day of this month' )->setTime( 0, 0, 0 );
		$notice_end             = current_datetime()->modify( '+14 days' )->format( 'Y-m-d' );
		$first_calendar         = $schema_ready ? $service->calendar( $calendar_month->format( 'Y-m' ), get_current_user_id() ) : array();
		$second_calendar        = $schema_ready ? $service->calendar( $calendar_month->modify( '+1 month' )->format( 'Y-m' ), get_current_user_id() ) : array();
		$first_calendar_days    = is_wp_error( $first_calendar ) ? array() : (array) ( $first_calendar['days'] ?? array() );
		$second_calendar_days   = is_wp_error( $second_calendar ) ? array() : (array) ( $second_calendar['days'] ?? array() );
		if ( ! $is_manager && ! current_user_can( 'manage_options' ) ) {
			$first_calendar_days  = $this->redact_calendar_occupancy( $first_calendar_days );
			$second_calendar_days = $this->redact_calendar_occupancy( $second_calendar_days );
		}
		$current_user           = wp_get_current_user();
		$portal_brand_name      = __( 'Komuna e Prishtinës', 'employee-leave-manager' );
		$portal_logo_url        = ELM_URL . 'assets/images/request-pdf-municipality-logo.png';
		$portal_home_url        = home_url( '/' );
		$portal_logout_url      = wp_logout_url( $this->portal_return_url() );
		$portal_role_label      = $is_manager ? __( 'Mbikëqyrës', 'employee-leave-manager' ) : __( 'Punonjës', 'employee-leave-manager' );
		$print_fallback_style   = did_action( 'wp_head' ) && ! wp_style_is( 'elm-portal', 'done' );
		$print_fallback_script  = did_action( 'wp_head' ) && ! wp_script_is( 'elm-portal', 'done' );

		ob_start();
		if ( $print_fallback_style ) {
			wp_print_styles( array( 'dashicons', 'elm-portal' ) );
		}
		?>
		<div class="elm-portal" id="<?php echo esc_attr( $instance ); ?>" data-elm-today="<?php echo esc_attr( ELM_Policy::today() ); ?>" data-elm-notice-end="<?php echo esc_attr( $notice_end ); ?>" data-elm-year="<?php echo esc_attr( $year ); ?>" data-elm-total-remaining="<?php echo esc_attr( (int) ( $balance['remaining_after_pending'] ?? 0 ) ); ?>" data-elm-standard-entitlement="<?php echo esc_attr( (int) ( $balance['standard_entitlement'] ?? 20 ) ); ?>" data-elm-entitlement-enabled="<?php echo $entitlement_enabled ? '1' : '0'; ?>" data-elm-entitlement-can-start="<?php echo $can_start_entitlement_change ? '1' : '0'; ?>" data-elm-entitlement-year-status="<?php echo esc_attr( $current_entitlement_status ); ?>" data-elm-period-one-remaining="<?php echo esc_attr( (int) ( $balance['period_one_remaining'] ?? 0 ) ); ?>" data-elm-period-one-limit="<?php echo esc_attr( (int) ( $balance['period_one_limit'] ?? 0 ) ); ?>" data-elm-chief="<?php echo $is_manager ? '1' : '0'; ?>" data-elm-current-user-id="<?php echo esc_attr( get_current_user_id() ); ?>" data-elm-can-approve-exceptions="<?php echo current_user_can( 'elm_adjust_balances' ) ? '1' : '0'; ?>" data-elm-can-view-medical="<?php echo current_user_can( 'elm_view_medical_documents' ) ? '1' : '0'; ?>" data-elm-can-delete-requests="<?php echo ( current_user_can( 'elm_manage_leave' ) || current_user_can( 'elm_adjust_balances' ) || current_user_can( 'manage_options' ) ) ? '1' : '0'; ?>" data-elm-plus-one-always-visible="<?php echo ( $can_manage_plus_one_display && ! empty( $portal_settings['show_plus_one_when_empty'] ) ) ? '1' : '0'; ?>" data-elm-export-request-base="<?php echo esc_url( admin_url( 'admin-post.php?action=elm_export_request_pdf&elm_frontend=1&request_id=' ) ); ?>" data-elm-export-request-nonce="<?php echo esc_attr( wp_create_nonce( 'elm_export_request_pdf_frontend' ) ); ?>" data-elm-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-elm-portal-nonce="<?php echo esc_attr( wp_create_nonce( 'elm_portal_action' ) ); ?>">
			<script type="application/json" data-elm-initial-requests><?php echo wp_json_encode( $requests, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
			<header class="elm-workspace-header">
				<div class="elm-brandbar">
					<a href="<?php echo esc_url( $portal_home_url ); ?>" class="elm-brand-link">
						<img src="<?php echo esc_url( $portal_logo_url ); ?>" alt="<?php echo esc_attr( $portal_brand_name ); ?>" class="elm-brand-logo">
						<span class="elm-brand-name"><?php echo esc_html( $portal_brand_name ); ?></span>
					</a>
					<div class="elm-profilebar elm-no-print">
						<div class="elm-profile-info">
							<span class="elm-profile-avatar" aria-hidden="true"><span class="dashicons dashicons-admin-users"></span></span>
							<span class="elm-profile-copy">
								<strong><?php echo esc_html( sprintf( __( 'Mirë se vini, %s', 'employee-leave-manager' ), $current_user->display_name ) ); ?></strong>
								<small><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Përdorues i autentikuar', 'employee-leave-manager' ); ?></small>
							</span>
						</div>
						<span class="elm-profile-divider" aria-hidden="true"></span>
						<a href="<?php echo esc_url( $portal_logout_url ); ?>" class="elm-logout-button"><?php esc_html_e( 'Dil', 'employee-leave-manager' ); ?></a>
					</div>
				</div>

				<div class="elm-portal__header elm-workspace-hero">
					<div class="elm-workspace-hero__copy">
						<p class="elm-eyebrow"><?php echo esc_html( $portal_role_label ); ?></p>
						<h2><?php esc_html_e( 'Portali i pushimeve', 'employee-leave-manager' ); ?><span class="elm-pro-badge">Pro</span></h2>
						<p class="elm-workspace-hero__description"><?php esc_html_e( 'Paraqitni, ndiqni dhe menaxhoni kërkesat për pushim, gjendjen vjetore dhe rregullat përkatëse nga një vend i vetëm.', 'employee-leave-manager' ); ?></p>
					</div>
					<div class="elm-year-chip elm-year-chip--input">
						<input type="number" class="elm-year-input" data-elm-year-select min="2024" max="2060" step="1" value="<?php echo esc_attr( min( 2060, max( 2024, (int) $year ) ) ); ?>" inputmode="numeric" aria-label="<?php esc_attr_e( 'Viti i kalendarit', 'employee-leave-manager' ); ?>" title="<?php esc_attr_e( 'Ndrysho vitin e kalendarit', 'employee-leave-manager' ); ?>">
					</div>
				</div>
			</header>

			<?php if ( $can_manage_settings ) : ?>
				<div class="elm-chief-notice elm-chief-notice--global" data-settings-notice hidden role="status" aria-live="polite"></div>
			<?php endif; ?>

			<nav class="elm-portal-tabs" aria-label="<?php esc_attr_e( 'Seksionet e portalit të pushimeve', 'employee-leave-manager' ); ?>">
				<button type="button" class="elm-portal-tab is-active" data-portal-tab="my" aria-selected="true"><?php esc_html_e( 'Pushimi im', 'employee-leave-manager' ); ?></button>
				<?php if ( $is_manager ) : ?><button type="button" class="elm-portal-tab" data-portal-tab="employers" aria-selected="false"><?php esc_html_e( 'Kërkesat për shqyrtim', 'employee-leave-manager' ); ?></button><?php endif; ?>
				<?php if ( $is_manager ) : ?><button type="button" class="elm-portal-tab" data-portal-tab="history" aria-selected="false"><?php esc_html_e( 'Historiku', 'employee-leave-manager' ); ?></button><?php endif; ?>
				<button type="button" class="elm-portal-tab" data-portal-tab="rules" aria-selected="false"><?php esc_html_e( 'Rregullat e pushimit', 'employee-leave-manager' ); ?></button>
				<?php if ( $can_view_settings ) : ?><button type="button" class="elm-portal-tab" data-portal-tab="settings" aria-selected="false"><?php esc_html_e( 'Cilësimet', 'employee-leave-manager' ); ?></button><?php endif; ?>
			</nav>

			<div class="elm-portal-tab-panel is-active" data-portal-panel="my">
			<div class="elm-notice<?php echo $flash_message ? ' elm-notice--' . esc_attr( $flash_kind ) : ''; ?>" <?php echo $flash_message ? '' : 'hidden'; ?> role="status" aria-live="polite"><?php echo esc_html( $flash_message ); ?></div>
			<div class="elm-balance-grid" aria-label="<?php esc_attr_e( 'Gjendja e pushimit vjetor', 'employee-leave-manager' ); ?>">
				<div class="elm-metric elm-metric--entitlement"><span><?php esc_html_e( 'Gjithsej ditë pushimi', 'employee-leave-manager' ); ?></span><strong data-balance="total"><?php echo esc_html( $this->whole_days( $balance['total_entitlement'] ?? 0 ) ); ?></strong><?php if ( $show_entitlement_toggle ) : ?><button type="button" class="elm-metric-action" data-entitlement-toggle><?php echo esc_html( $can_start_entitlement_change ? __( 'Kërko +1 ditë për përvojë', 'employee-leave-manager' ) : __( 'Shiko kërkesën +1', 'employee-leave-manager' ) ); ?></button><?php endif; ?></div>
				<div class="elm-metric"><span><?php esc_html_e( 'Të shfrytëzuara', 'employee-leave-manager' ); ?></span><strong data-balance="used"><?php echo esc_html( $this->whole_days( $balance['approved_used'] ?? 0 ) ); ?></strong></div>
				<div class="elm-metric"><span><?php esc_html_e( 'Në pritje', 'employee-leave-manager' ); ?></span><strong data-balance="pending"><?php echo esc_html( $this->whole_days( $balance['pending_units'] ?? 0 ) ); ?></strong></div>
				<div class="elm-metric elm-metric--primary">
					<div class="elm-metric__ring" data-balance-ring style="--elm-ring-pct:<?php echo esc_attr( $this->remaining_ring_percent( $balance ) ); ?>;" aria-hidden="true"><span class="elm-metric__ring-value" data-balance-ring-value><?php echo esc_html( $this->remaining_ring_percent( $balance ) ); ?>%</span></div>
					<div class="elm-metric__content"><span><?php esc_html_e( 'Të mbetura', 'employee-leave-manager' ); ?></span><strong data-balance="remaining"><?php echo esc_html( $this->whole_days( $balance['remaining_after_pending'] ?? 0 ) ); ?></strong></div>
				</div>
			</div>

			<section class="elm-entitlement-panel elm-plus-one-panel" data-entitlement-panel hidden>
				<div class="elm-entitlement-panel__head">
					<div>
						<p class="elm-eyebrow"><?php esc_html_e( 'Pushimi vjetor', 'employee-leave-manager' ); ?></p>
						<h3><?php esc_html_e( 'Kërko +1 ditë për përvojë pune', 'employee-leave-manager' ); ?></h3>
					</div>
					<button type="button" class="elm-entitlement-close" data-entitlement-close aria-label="<?php esc_attr_e( 'Mbyll', 'employee-leave-manager' ); ?>">&times;</button>
				</div>
				<p class="elm-entitlement-help"><?php esc_html_e( 'Sipas nenit 9 paragrafi 3, për çdo pesë (5) vjet të përvojës së punës shtohet një (1) ditë pune në pushimin vjetor. Paraqitni kërkesën kur plotësoni një prag të ri 5-vjeçar; mbikëqyrësi e verifikon para miratimit.', 'employee-leave-manager' ); ?></p>
				<div class="elm-chief-notice" data-entitlement-notice hidden role="status" aria-live="polite"></div>
				<div class="elm-plus-one-preview" aria-label="<?php esc_attr_e( 'Ndryshimi i kërkuar', 'employee-leave-manager' ); ?>">
					<span><?php esc_html_e( 'Gjithsej ditë pushimi', 'employee-leave-manager' ); ?></span>
					<strong><span data-entitlement-from><?php echo esc_html( (int) ( $balance['standard_entitlement'] ?? 20 ) ); ?></span><span class="elm-plus-one-preview__arrow" aria-hidden="true">→</span><span data-entitlement-to><?php echo esc_html( (int) ( $balance['standard_entitlement'] ?? 20 ) + 1 ); ?></span></strong>
					<small><?php echo esc_html( sprintf( __( 'Ndryshimi hyn në fuqi nga viti %d.', 'employee-leave-manager' ), $year ) ); ?></small>
				</div>
				<form class="elm-entitlement-form elm-plus-one-form" data-entitlement-form <?php echo $can_start_entitlement_change ? '' : 'hidden'; ?>>
					<input type="hidden" name="requested_entitlement" value="<?php echo esc_attr( (int) ( $balance['standard_entitlement'] ?? 20 ) + 1 ); ?>">
					<input type="hidden" name="effective_year" value="<?php echo esc_attr( $year ); ?>">
					<label class="elm-entitlement-form__reason"><?php esc_html_e( 'Arsyetimi', 'employee-leave-manager' ); ?><textarea name="reason" rows="2" maxlength="2000" required placeholder="<?php esc_attr_e( 'Shembull: Kam plotësuar 10 vite të përvojës së punës.', 'employee-leave-manager' ); ?>"></textarea></label>
					<div class="elm-entitlement-form__actions">
						<button type="submit" class="elm-button elm-button--primary"><?php esc_html_e( 'Dërgo kërkesën', 'employee-leave-manager' ); ?></button>
						<button type="button" class="elm-button elm-button--secondary" data-entitlement-close><?php esc_html_e( 'Mbyll', 'employee-leave-manager' ); ?></button>
					</div>
				</form>
				<details class="elm-entitlement-history elm-plus-one-history">
					<summary><?php esc_html_e( 'Historiku i kërkesave', 'employee-leave-manager' ); ?></summary>
					<div data-entitlement-history><p class="elm-empty-state elm-empty-state--loading"><?php esc_html_e( 'Po ngarkohet...', 'employee-leave-manager' ); ?></p></div>
				</details>
			</section>
			<div class="elm-layout">
				<section class="elm-card elm-calendar-card ot-section ot-no-print">
					<div class="ot-cal-header">
						<button type="button" class="ot-cal-nav-btn" data-ot-nav="-1" aria-label="<?php esc_attr_e( 'Muaji i kaluar', 'employee-leave-manager' ); ?>">&#8249;</button>
						<strong><?php esc_html_e( 'Zgjidhni së paku një ditë', 'employee-leave-manager' ); ?></strong>
						<button type="button" class="ot-cal-nav-btn" data-ot-nav="1" aria-label="<?php esc_attr_e( 'Muaji i ardhshëm', 'employee-leave-manager' ); ?>">&#8250;</button>
					</div>
					<div class="ot-cal-grids" data-calendar aria-busy="true">
						<?php $this->render_initial_month( $calendar_month, 0, $first_calendar_days, $notice_end ); ?>
						<?php $this->render_initial_month( $calendar_month->modify( '+1 month' ), 1, $second_calendar_days, $notice_end ); ?>
					</div>
					<div class="ot-selected-info">
						<?php esc_html_e( 'Periudha e zgjedhur:', 'employee-leave-manager' ); ?> <span data-ot-selected-list><?php esc_html_e( 'asnjë', 'employee-leave-manager' ); ?></span>
						<button type="button" class="ot-btn ot-secondary ot-btn-small" data-ot-clear-dates><?php esc_html_e( 'Pastro datat', 'employee-leave-manager' ); ?></button>
					</div>
					<div class="elm-policy-warnings elm-calendar-policy-notice" data-policy-warnings hidden role="status" aria-live="polite"></div>
					<details class="elm-calendar-guide" data-calendar-guide>
						<summary class="elm-calendar-guide__summary">
							<span class="elm-calendar-guide__summary-main">
								<span class="elm-calendar-guide__summary-icon" aria-hidden="true">?</span>
								<span class="elm-calendar-guide__summary-copy">
									<strong><?php esc_html_e( 'Sqarimet e kalendarit', 'employee-leave-manager' ); ?></strong>
									<small><?php esc_html_e( 'Rregullat, shenjat dhe ditët e disponueshme', 'employee-leave-manager' ); ?></small>
								</span>
							</span>
							<span class="elm-calendar-guide__summary-action" aria-hidden="true">
								<span class="elm-calendar-guide__show"><?php esc_html_e( 'Shfaq', 'employee-leave-manager' ); ?></span>
								<span class="elm-calendar-guide__hide"><?php esc_html_e( 'Fshih', 'employee-leave-manager' ); ?></span>
								<span class="elm-calendar-guide__chevron"></span>
							</span>
						</summary>
						<div class="elm-calendar-guide__content">
							<div class="elm-calendar-guide__section elm-calendar-guide__section--allowance elm-calendar-guide__section--legal">
								<strong class="elm-calendar-guide__title"><?php esc_html_e( 'Afati për pushim vjetor', 'employee-leave-manager' ); ?></strong>
								<div class="elm-legal-deadline"><?php esc_html_e( 'Kërkesa paraqitet së paku 15 ditë para fillimit të pushimit. Datat brenda këtij afati shënohen me ! dhe mund të përzgjidhen. Vërejtja i shfaqet edhe mbikëqyrësit gjatë shqyrtimit.', 'employee-leave-manager' ); ?></div>
							</div>
							<div class="elm-calendar-guide__section elm-calendar-guide__section--allowance">
								<strong class="elm-calendar-guide__title"><?php esc_html_e( 'Periudha janar-qershor', 'employee-leave-manager' ); ?></strong>
								<div class="elm-legal-deadline"><?php esc_html_e( 'Pragu 10-ditor është informues. Nëse tejkalohet, kërkesa nuk bllokohet; vërejtja i shfaqet punonjësit dhe mbikëqyrësit gjatë shqyrtimit.', 'employee-leave-manager' ); ?></div>
							</div>
							<div class="elm-calendar-guide__section elm-calendar-guide__section--instruction">
								<strong class="elm-calendar-guide__title"><?php esc_html_e( 'Përzgjedhja e datave', 'employee-leave-manager' ); ?></strong>
								<p class="elm-calendar-instruction"><?php esc_html_e( 'Zgjidhni datën e fillimit dhe të përfundimit. Fundjavat dhe festat mund të përzgjidhen si pjesë e periudhës, por nuk llogariten si ditë pushimi. Klikoni përsëri në një datë për ta përjashtuar.', 'employee-leave-manager' ); ?></p>
							</div>
							<div class="elm-calendar-guide__legend-grid">
								<div class="elm-calendar-guide__legend-group">
									<strong class="elm-calendar-guide__legend-title"><?php esc_html_e( 'Kalendari', 'employee-leave-manager' ); ?></strong>
									<div class="elm-calendar-key">
										<span><i class="elm-key-dot elm-key-dot--nonworking"></i><?php esc_html_e( 'Fundjavë - përzgjidhet, nuk llogaritet', 'employee-leave-manager' ); ?></span>
										<span><i class="elm-key-holiday">?</i><?php esc_html_e( 'Festë - përzgjidhet, nuk llogaritet', 'employee-leave-manager' ); ?></span>
										<span><i class="elm-key-warning">!</i><?php esc_html_e( 'Vërejtje - afati 15-ditor ose tejkalimi i pragut 10-ditor', 'employee-leave-manager' ); ?></span>
										<span><i class="elm-key-dot elm-key-dot--blocked"></i><?php esc_html_e( 'E padisponueshme', 'employee-leave-manager' ); ?></span>
									</div>
								</div>
								<div class="elm-calendar-guide__legend-group">
									<strong class="elm-calendar-guide__legend-title"><?php esc_html_e( 'Kërkesat e mia', 'employee-leave-manager' ); ?></strong>
									<div class="elm-calendar-key elm-calendar-key--statuses" aria-label="<?php esc_attr_e( 'Historiku i kërkesave', 'employee-leave-manager' ); ?>">
										<span><i class="elm-key-dot elm-key-dot--status-pending"></i><?php esc_html_e( 'Në pritje', 'employee-leave-manager' ); ?></span>
										<span><i class="elm-key-dot elm-key-dot--status-approved"></i><?php esc_html_e( 'Miratuar', 'employee-leave-manager' ); ?></span>
										<span><i class="elm-key-dot elm-key-dot--status-rejected"></i><?php esc_html_e( 'Refuzuar', 'employee-leave-manager' ); ?></span>
									</div>
								</div>
							</div>
						</div>
					</details>
				</section>

				<section class="elm-card elm-request-card" data-request-card>
					<div class="elm-card__head"><div><p class="elm-eyebrow" data-request-form-eyebrow><?php esc_html_e( 'Kërkesë e re', 'employee-leave-manager' ); ?></p><h3 data-request-form-title><?php esc_html_e( 'Kërko pushim', 'employee-leave-manager' ); ?></h3></div></div>
					<form data-request-form novalidate method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<input type="hidden" name="action" value="elm_submit_request_form" data-request-form-action>
						<input type="hidden" name="request_id" value="" data-edit-request-id>
						<?php wp_nonce_field( 'elm_submit_request', 'elm_submit_request_nonce' ); ?>
						<input type="hidden" name="return_url" value="<?php echo esc_url( remove_query_arg( array( 'elm_notice', 'elm_message', 'elm_request_id' ) ) ); ?>">
						<input type="hidden" name="selected_dates" value="">
						<input type="hidden" name="start_date" value="">
						<input type="hidden" name="end_date" value="">
						<label><?php esc_html_e( 'Lloji i pushimit', 'employee-leave-manager' ); ?>
							<select name="leave_type" required>
								<option value="annual"><?php esc_html_e( 'Pushim vjetor', 'employee-leave-manager' ); ?></option>
								<option value="medical"><?php esc_html_e( 'Pushim mjekësor', 'employee-leave-manager' ); ?></option>
							</select>
						</label>
						<div class="elm-selected-request-days"><span><?php esc_html_e( 'Ditë pune', 'employee-leave-manager' ); ?></span><strong data-request-day-count>0</strong></div>
						<div class="elm-waitlist" data-waitlist hidden></div>
						<label><?php esc_html_e( 'Arsyetimi', 'employee-leave-manager' ); ?><textarea name="reason" rows="4" maxlength="2000" required placeholder="<?php esc_attr_e( 'Shkruani arsyetimin e nevojshëm për shqyrtim.', 'employee-leave-manager' ); ?>"></textarea></label>
						<div class="elm-medical-fields" data-medical hidden>
							<input type="hidden" name="medical_ack_present" value="1">
							<label class="elm-check"><input type="checkbox" name="medical_ack" value="1"><span><?php esc_html_e( 'Konfirmoj se do të ofroj dëshminë përkatëse mjekësore sipas rregullores.', 'employee-leave-manager' ); ?></span></label>
							<label class="elm-file-field"><span><?php esc_html_e( 'Dokument mjekësor (PDF, JPG, PNG)', 'employee-leave-manager' ); ?></span><span class="elm-file-picker"><input type="file" name="medical_document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-file-input><span class="elm-file-button"><?php esc_html_e( 'Zgjidh skedarin', 'employee-leave-manager' ); ?></span><span class="elm-file-name" data-file-name data-empty-label="<?php esc_attr_e( 'Nuk është zgjedhur asnjë skedar', 'employee-leave-manager' ); ?>"><?php esc_html_e( 'Nuk është zgjedhur asnjë skedar', 'employee-leave-manager' ); ?></span></span></label>
							<p class="elm-privacy-note"><?php esc_html_e( 'Dokumentet enkriptohen para ruajtjes dhe nuk publikohen në Bibliotekën e Mediave.', 'employee-leave-manager' ); ?></p>
						</div>
						<div class="elm-submit-status" data-submit-status hidden role="status" aria-live="polite"></div>
						<div class="elm-request-form-actions">
							<button type="submit" class="elm-button elm-button--primary" data-request-submit><?php esc_html_e( 'Paraqit kërkesën', 'employee-leave-manager' ); ?></button>
							<button type="button" class="elm-button elm-button--secondary" data-cancel-edit hidden><?php esc_html_e( 'Anulo redaktimin', 'employee-leave-manager' ); ?></button>
						</div>
					</form>
				</section>
			</div>

			<section class="elm-card elm-history-card" id="elm-my-requests">
				<div class="elm-card__head">
					<div><p class="elm-eyebrow"><?php esc_html_e( 'Statusi aktual', 'employee-leave-manager' ); ?></p><h3><?php esc_html_e( 'Kërkesat e mia', 'employee-leave-manager' ); ?></h3></div>
					<button type="button" class="elm-refresh-button" data-refresh-requests><?php esc_html_e( 'Rifresko', 'employee-leave-manager' ); ?></button>
				</div>
				<div class="elm-table-wrap"><table class="elm-table"><thead><tr><th><?php esc_html_e( 'Lloji', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Datat e zgjedhura', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Ditë', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Statusi', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Paraqitur më', 'employee-leave-manager' ); ?></th><th><span class="screen-reader-text"><?php esc_html_e( 'Veprimet', 'employee-leave-manager' ); ?></span></th></tr></thead><tbody data-requests-body><?php $this->render_request_rows( $requests ); ?></tbody></table></div>
			</section>
			</div>

			<div class="elm-portal-tab-panel elm-rules-panel" data-portal-panel="rules" hidden>
				<section class="elm-card elm-rules-card" aria-labelledby="elm-rules-title">
					<div class="elm-rules-hero">
						<div><p class="elm-eyebrow"><?php esc_html_e( 'Rregullore (QRK) Nr. 04/2024', 'employee-leave-manager' ); ?></p><h3 id="elm-rules-title"><?php esc_html_e( 'Rregullat e pushimit', 'employee-leave-manager' ); ?></h3><p><?php esc_html_e( 'Përmbledhje praktike për orarin e punës, pushimet dhe afatet që lidhen me kërkesat e zyrtarëve publikë.', 'employee-leave-manager' ); ?></p></div>
						<div class="elm-rules-source-actions"><span class="elm-rules-badge"><?php esc_html_e( 'Në fuqi nga publikimi më 28.02.2024', 'employee-leave-manager' ); ?></span><a class="elm-rules-source-link" href="https://gzk.rks-gov.net/ActDetail.aspx?ActID=87434" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Teksti zyrtar', 'employee-leave-manager' ); ?></a></div>
					</div>
					<div class="elm-rules-stats">
						<div><strong>20</strong><span><?php esc_html_e( 'ditë pune pushim vjetor bazë', 'employee-leave-manager' ); ?></span></div>
						<div><strong>15</strong><span><?php esc_html_e( 'ditë afati për njoftim', 'employee-leave-manager' ); ?></span></div>
						<div><strong>5</strong><span><?php esc_html_e( 'ditë afati për vendim', 'employee-leave-manager' ); ?></span></div>
						<div><strong>10</strong><span><?php esc_html_e( 'ditë pune për pjesën kryesore të pushimit', 'employee-leave-manager' ); ?></span></div>
					</div>
					<div class="elm-rules-list">
						<details open><summary><?php esc_html_e( 'Pushimi vjetor, nenet 9 dhe 10', 'employee-leave-manager' ); ?></summary><div><ul>
							<li><?php esc_html_e( 'E drejta bazë është 20 ditë pune për çdo vit kalendarik.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Për punët ku, edhe pas zbatimit të masave mbrojtëse, zyrtari nuk mund të mbrohet nga ndikimet e dëmshme, pushimi vjetor është së paku 30 ditë pune.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Për çdo 5 vjet përvojë pune shtohet 1 ditë pune në pushimin vjetor.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Nënat, prindi vetushqyes ose vetmbajtës i fëmijës deri në moshën 3 vjeçare dhe zyrtari me aftësi të kufizuar përfitojnë 2 ditë pune shtesë.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Festat zyrtare dhe pushimi mjekësor i lejuar gjatë pushimit vjetor nuk llogariten si ditë të pushimit vjetor.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Kur një pushim mjekësor i miratuar përputhet me ditë të pushimit vjetor, ato ditë nuk zbriten nga gjendja e pushimit vjetor në portal.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Nëse pushimi ndahet në dy ose më shumë pjesë, pjesa kryesore duhet të jetë së paku 10 ditë pune të pandërprera. Pjesa e mbetur shfrytëzohet jo më vonë se 30 qershor i vitit vijues.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Në rastin e punësimit të parë, e drejta për shfrytëzimin e pushimit vjetor fitohet pas 6 muajsh pune të pandërprerë. Në rastet e përcaktuara nga rregullorja llogariten 1.5 ditë për çdo muaj kalendarik të punuar.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Me kalimin nga një institucion në një institucion tjetër, zyrtari bart pushimin vjetor të pashfrytëzuar.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Ditët e pashfrytëzuara nuk kompensohen me para, përveç në rast të ndërprerjes së marrëdhënies së punës.', 'employee-leave-manager' ); ?></li>
						</ul></div></details>
						<details><summary><?php esc_html_e( 'Afatet dhe miratimi i pushimit vjetor', 'employee-leave-manager' ); ?></summary><div><ul>
							<li><?php esc_html_e( 'Mbikëqyrësi i drejtpërdrejtë njoftohet së paku 15 ditë para fillimit të pushimit vjetor.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Vendimi për orarin dhe kohëzgjatjen e pushimit duhet të lëshohet së paku 5 ditë para fillimit.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Kërkesa miratohet nga mbikëqyrësi i drejtpërdrejtë. Njësia për Menaxhimin e Burimeve Njerëzore njoftohet dhe vendimi evidentohet në dosjen individuale.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Në portal, përdoruesi me rolin Mbikëqyrësi i drejtpërdrejtë mund të miratojë ose refuzojë edhe kërkesën e vet sipas të drejtave të rolit të caktuara nga institucioni. Çdo vendim evidentohet në regjistrin e auditimit.', 'employee-leave-manager' ); ?></li>
						</ul></div></details>
						<details><summary><?php esc_html_e( 'Pushimi mjekësor, nenet 11 dhe 12', 'employee-leave-manager' ); ?></summary><div><ul>
							<li><?php esc_html_e( 'Në rast sëmundjeje, zyrtari ka të drejtë deri në 20 ditë pune pushim mjekësor brenda vitit me kompensim 100% të pagës, sipas kushteve të rregullores.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Mbikëqyrësi njoftohet menjëherë ose më së largu gjatë ditës së mungesës. Kur mungesa zgjat më shumë se 3 ditë, mbikëqyrësi mund të kërkojë certifikatë mjekësore.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Ditët e pushimit mjekësor mund të shfrytëzohen edhe në rast të sëmundjes së fëmijës, me dëshminë përkatëse.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Lëndimi në punë ose sëmundja profesionale rregullohet veçmas. Pushimi mund të zgjasë nga 10 deri në 90 ditë pune me kompensim 70% të pagës.', 'employee-leave-manager' ); ?></li>
						</ul></div></details>
						<details><summary><?php esc_html_e( 'Pushime dhe mungesa të tjera', 'employee-leave-manager' ); ?></summary><div><ul>
							<li><?php esc_html_e( 'Pushimi i lehonisë mund të zgjasë deri në 12 muaj. Ai mund të fillojë deri në 45 ditë para datës së parashikuar të lindjes.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Mungesa me pagesë lejohet 5 ditë për martesë, 5 ditë për vdekje të anëtarit të ngushtë të familjes, 3 ditë për lindje të fëmijës dhe 2 ditë pune për çdo rast të dhënies vullnetare të gjakut.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Pushimi pa pagesë kërkohet me arsyetim së paku 15 ditë përpara, përveç pushimit mjekësor pa pagesë.', 'employee-leave-manager' ); ?></li>
						</ul></div></details>
						<details><summary><?php esc_html_e( 'Orari i punës dhe puna jashtë orarit', 'employee-leave-manager' ); ?></summary><div><ul>
							<li><?php esc_html_e( 'Orari i rregullt është nga ora 08:00 deri në 16:00, nga e hëna deri të premten, me 1 orë pushim gjatë ditës. Institucioni mund të caktojë orar tjetër kur këtë e kërkon natyra e punës.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Orari i rregullt nuk mund të kalojë 40 orë në javë dhe 12 orë në ditë, duke përfshirë pushimin.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Puna jashtë orarit, si rregull, nuk mund të kalojë 8 orë në javë. Zyrtari mund të kërkojë kompensim me ditë pushimi në vend të kompensimit monetar, sipas aktit nënligjor përkatës.', 'employee-leave-manager' ); ?></li>
						</ul></div></details>
						<details><summary><?php esc_html_e( 'Burimet ligjore', 'employee-leave-manager' ); ?></summary><div><ul class="elm-rules-sources">
							<li><a href="https://gzk.rks-gov.net/ActDetail.aspx?ActID=87434" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Rregullore (QRK) Nr. 04/2024, teksti zyrtar', 'employee-leave-manager' ); ?></a></li>
							<li><a href="https://gzk.rks-gov.net/ActDetail.aspx?ActID=81430" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ligji Nr. 08/L-197 për Zyrtarët Publik', 'employee-leave-manager' ); ?></a> <span><?php esc_html_e( '(i ndryshuar/plotësuar me Ligjin Nr. 08/L-294, publikuar më 02.06.2025)', 'employee-leave-manager' ); ?></span></li>
							<li><a href="https://gzk.rks-gov.net/ActDetail.aspx?ActID=107530" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ligji Nr. 03/L-064 për Festat Zyrtare (versioni i konsoliduar)', 'employee-leave-manager' ); ?></a></li>
						</ul><p><?php esc_html_e( 'Për çështjet që rregullohen edhe me ligje ose akte të tjera, kontrolloni gjithmonë tekstin zyrtar në fuqi dhe procedurat e institucionit.', 'employee-leave-manager' ); ?></p></div></details>
						<details><summary><?php esc_html_e( 'Si zbatohen këto rregulla në portal', 'employee-leave-manager' ); ?></summary><div><ul>
							<li><?php esc_html_e( 'Pushimi vjetor bazë llogaritet me 20 ditë pune.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Datat e pushimit vjetor brenda afatit minimal prej 15 ditësh shënohen me ! dhe ruhen si vërejtje për shqyrtim.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Miratimi i pushimit vjetor bllokohet kur kanë mbetur më pak se 5 ditë deri në fillim.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Kapaciteti ditor është rregull organizativ vetëm për pushimin vjetor dhe nuk bllokon kërkesat për pushim mjekësor.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Në portal, mbikëqyrësi i drejtpërdrejtë që ka të drejtën e menaxhimit mund të marrë vendim edhe për kërkesën e vet. Kjo e drejtë caktohet nga roli i përdoruesit dhe çdo vendim regjistrohet në auditim.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Kërkesa +1 ditë përdoret për të drejtën që lind për çdo 5 vjet të përvojës së punës dhe kërkon verifikim nga mbikëqyrësi ose Njësia për Menaxhimin e Burimeve Njerëzore.', 'employee-leave-manager' ); ?></li>
							<li><?php esc_html_e( 'Të drejtat që varen nga të dhëna që portali nuk i ruan (p.sh. punë me ndikime të dëmshme, statusi prindëror, aftësia e kufizuar, data e fillimit të punës, bartja nga institucioni tjetër ose lëndimi në punë) verifikohen nga Njësia për Menaxhimin e Burimeve Njerëzore dhe zbatohen sipas procedurës përkatëse.', 'employee-leave-manager' ); ?></li>
						</ul></div></details>
					</div>
					<p class="elm-rules-disclaimer"><strong><?php esc_html_e( 'Burimi:', 'employee-leave-manager' ); ?></strong> <?php esc_html_e( 'Rregullore (QRK) Nr. 04/2024 për Orarin e Punës, Pushimet dhe Vijushmërinë e Zyrtarëve Publik. Kjo skedë është përmbledhje informative dhe nuk zëvendëson interpretimin juridik ose verifikimin e Burimeve Njerëzore. Në rast mospërputhjeje zbatohet teksti zyrtar dhe legjislacioni tjetër përkatës në fuqi.', 'employee-leave-manager' ); ?></p>
				</section>
			</div>

			<?php if ( $is_manager ) : ?>
			<div class="elm-portal-tab-panel" data-portal-panel="history" hidden>
				<section class="elm-card elm-chief-history" data-chief-history>
					<div class="elm-card__head"><div><p class="elm-eyebrow"><?php esc_html_e( 'Historiku i pushimeve', 'employee-leave-manager' ); ?></p><h3><?php esc_html_e( 'Historiku', 'employee-leave-manager' ); ?></h3><p><?php esc_html_e( 'Shikoni gjendjen vjetore dhe historikun e kërkesave të punonjësve që menaxhoni.', 'employee-leave-manager' ); ?></p></div><button type="button" class="elm-refresh-button" data-chief-history-refresh><?php esc_html_e( 'Rifresko', 'employee-leave-manager' ); ?></button></div>
					<div class="elm-chief-notice" data-chief-history-notice hidden role="status" aria-live="polite"></div>
					<div class="elm-chief-filters elm-history-filters">
						<label><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?><select data-chief-history-user><option value=""><?php esc_html_e( 'Të gjithë punonjësit', 'employee-leave-manager' ); ?></option></select></label>
						<label><?php esc_html_e( 'Statusi', 'employee-leave-manager' ); ?><select data-chief-history-status><option value=""><?php esc_html_e( 'Të gjitha statuset', 'employee-leave-manager' ); ?></option><option value="pending"><?php esc_html_e( 'Në pritje', 'employee-leave-manager' ); ?></option><option value="approved"><?php esc_html_e( 'Miratuar', 'employee-leave-manager' ); ?></option><option value="rejected"><?php esc_html_e( 'Refuzuar', 'employee-leave-manager' ); ?></option><option value="cancelled"><?php esc_html_e( 'Anuluar', 'employee-leave-manager' ); ?></option></select></label>
						<label><?php esc_html_e( 'Viti', 'employee-leave-manager' ); ?><input type="number" min="2000" max="2100" value="<?php echo esc_attr( $year ); ?>" data-chief-history-year></label>
						<button type="button" class="elm-button elm-button--primary elm-button--compact" data-chief-history-apply><?php esc_html_e( 'Zbato filtrat', 'employee-leave-manager' ); ?></button>
					</div>
					<div class="elm-history-report" data-chief-history-report><p class="elm-empty-state elm-empty-state--loading"><?php esc_html_e( 'Po ngarkohet...', 'employee-leave-manager' ); ?></p></div>
				</section>
			</div>

			<div class="elm-chief-history-overlay" data-chief-history-dialog hidden>
				<div class="elm-chief-history-dialog" role="dialog" aria-modal="true" aria-labelledby="elm-chief-history-dialog-title">
					<div class="elm-chief-history-dialog__head"><div><p class="elm-eyebrow"><?php esc_html_e( 'Historiku i pushimeve', 'employee-leave-manager' ); ?></p><h3 id="elm-chief-history-dialog-title" data-chief-history-dialog-title><?php esc_html_e( 'Historiku', 'employee-leave-manager' ); ?></h3></div><button type="button" class="elm-chief-edit-close" data-chief-history-close aria-label="<?php esc_attr_e( 'Mbyll', 'employee-leave-manager' ); ?>">&times;</button></div>
					<div class="elm-chief-filters elm-history-dialog__filters">
						<label><?php esc_html_e( 'Statusi', 'employee-leave-manager' ); ?><select data-chief-history-dialog-status><option value=""><?php esc_html_e( 'Të gjitha statuset', 'employee-leave-manager' ); ?></option><option value="pending"><?php esc_html_e( 'Në pritje', 'employee-leave-manager' ); ?></option><option value="approved"><?php esc_html_e( 'Miratuar', 'employee-leave-manager' ); ?></option><option value="rejected"><?php esc_html_e( 'Refuzuar', 'employee-leave-manager' ); ?></option><option value="cancelled"><?php esc_html_e( 'Anuluar', 'employee-leave-manager' ); ?></option></select></label>
						<label><?php esc_html_e( 'Viti', 'employee-leave-manager' ); ?><input type="number" min="2000" max="2100" value="<?php echo esc_attr( $year ); ?>" data-chief-history-dialog-year></label>
						<button type="button" class="elm-button elm-button--primary elm-button--compact" data-chief-history-dialog-apply><?php esc_html_e( 'Zbato filtrat', 'employee-leave-manager' ); ?></button>
					</div>
					<div class="elm-chief-notice" data-chief-history-dialog-notice hidden role="status" aria-live="polite"></div>
					<div class="elm-history-report elm-history-report--dialog" data-chief-history-dialog-report><p class="elm-empty-state elm-empty-state--loading"><?php esc_html_e( 'Po ngarkohet...', 'employee-leave-manager' ); ?></p></div>
				</div>
			</div>

			<div class="elm-portal-tab-panel" data-portal-panel="employers" hidden>
				<section class="elm-card elm-chief-requirements" data-chief-requirements>
					<div class="elm-card__head"><div><p class="elm-eyebrow"><?php esc_html_e( 'Menaxhimi i kërkesave', 'employee-leave-manager' ); ?></p><h3><?php esc_html_e( 'Kërkesat për shqyrtim', 'employee-leave-manager' ); ?></h3></div><button type="button" class="elm-refresh-button" data-chief-refresh><?php esc_html_e( 'Rifresko', 'employee-leave-manager' ); ?></button></div>
					<p class="elm-chief-requirements__legal-note"><?php esc_html_e( 'Pushimi vjetor miratohet nga mbikëqyrësi i drejtpërdrejtë. Pas vendimit, Njësia për Menaxhimin e Burimeve Njerëzore duhet të njoftohet dhe vendimi të evidentohet në dosjen individuale të zyrtarit publik.', 'employee-leave-manager' ); ?></p>
					<div class="elm-chief-notice" data-chief-notice hidden role="status" aria-live="polite"></div>
					<div class="elm-chief-filters">
						<label><?php esc_html_e( 'Statusi', 'employee-leave-manager' ); ?><select data-chief-filter-status><option value=""><?php esc_html_e( 'Të gjitha statuset', 'employee-leave-manager' ); ?></option><option value="pending"><?php esc_html_e( 'Në pritje', 'employee-leave-manager' ); ?></option><option value="approved"><?php esc_html_e( 'Miratuar', 'employee-leave-manager' ); ?></option><option value="rejected"><?php esc_html_e( 'Refuzuar', 'employee-leave-manager' ); ?></option><option value="cancelled"><?php esc_html_e( 'Anuluar', 'employee-leave-manager' ); ?></option></select></label>
						<label><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?><select data-chief-filter-user><option value=""><?php esc_html_e( 'Të gjithë punonjësit', 'employee-leave-manager' ); ?></option></select></label>
						<label><?php esc_html_e( 'Viti', 'employee-leave-manager' ); ?><input type="number" min="2000" max="2100" value="" placeholder="<?php esc_attr_e( 'Të gjitha vitet', 'employee-leave-manager' ); ?>" data-chief-filter-year></label>
						<button type="button" class="elm-button elm-button--primary elm-button--compact" data-chief-apply-filters><?php esc_html_e( 'Zbato filtrat', 'employee-leave-manager' ); ?></button>
					</div>
					<div class="elm-table-wrap"><table class="elm-table elm-chief-table"><thead><tr><th><?php esc_html_e( 'ID', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Lloji / datat', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Arsyetimet', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Statusi', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Paraqitur më', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Veprimet', 'employee-leave-manager' ); ?></th></tr></thead><tbody data-chief-requests-body><tr class="elm-table-state elm-table-state--loading"><td class="elm-table-state__cell" colspan="7"><?php esc_html_e( 'Po ngarkohet...', 'employee-leave-manager' ); ?></td></tr></tbody></table></div>
				</section>

				<section class="elm-card elm-chief-entitlements elm-chief-plus-one elm-chief-plus-one--hidden" data-chief-entitlements aria-labelledby="elm-chief-plus-one-title" aria-hidden="true" hidden>
					<div class="elm-card__head elm-chief-entitlements__head">
						<div>
							<p class="elm-eyebrow"><?php esc_html_e( 'Pushimi vjetor', 'employee-leave-manager' ); ?></p>
							<h3 id="elm-chief-plus-one-title"><?php esc_html_e( 'Kërkesat për +1 ditë për përvojë pune', 'employee-leave-manager' ); ?></h3>
							<p class="elm-chief-plus-one__help"><?php esc_html_e( 'Sipas nenit 9 paragrafi 3, +1 ditë njihet për çdo pesë vjet të përvojës së punës. Miratojeni vetëm pasi pragu i ri 5-vjeçar të jetë verifikuar në të dhënat/dosjen e punonjësit.', 'employee-leave-manager' ); ?></p>
						</div>
					</div>
					<div class="elm-chief-notice" data-chief-entitlement-notice hidden role="status" aria-live="polite"></div>
					<div class="elm-chief-plus-one__pending">
						<h4><?php esc_html_e( 'Në pritje', 'employee-leave-manager' ); ?></h4>
						<div class="elm-plus-one-list" data-chief-entitlements-pending><p class="elm-empty-state elm-empty-state--loading"><?php esc_html_e( 'Po ngarkohet...', 'employee-leave-manager' ); ?></p></div>
					</div>
					<details class="elm-chief-plus-one__history">
						<summary><?php esc_html_e( 'Historiku i kërkesave', 'employee-leave-manager' ); ?></summary>
						<div class="elm-plus-one-list elm-plus-one-list--history" data-chief-entitlements-history><p class="elm-empty-state elm-empty-state--loading"><?php esc_html_e( 'Po ngarkohet...', 'employee-leave-manager' ); ?></p></div>
					</details>
				</section>

				<div class="elm-chief-edit-overlay" data-chief-edit-dialog hidden>
					<div class="elm-chief-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="elm-chief-edit-title">
						<form data-chief-edit-form>
							<div class="elm-chief-edit-head"><div><p class="elm-eyebrow"><?php esc_html_e( 'Kërkesë në pritje', 'employee-leave-manager' ); ?></p><h3 id="elm-chief-edit-title"><?php esc_html_e( 'Redakto kërkesën e punonjësit', 'employee-leave-manager' ); ?></h3></div><button type="button" class="elm-chief-edit-close" data-chief-edit-close aria-label="<?php esc_attr_e( 'Mbyll', 'employee-leave-manager' ); ?>">&times;</button></div>
							<div class="elm-chief-edit-fields">
								<label><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?><input type="text" data-chief-edit-employee readonly></label>
								<label><?php esc_html_e( 'Lloji i pushimit', 'employee-leave-manager' ); ?><select name="leave_type"><option value="annual"><?php esc_html_e( 'Pushim vjetor', 'employee-leave-manager' ); ?></option><option value="medical"><?php esc_html_e( 'Pushim mjekësor', 'employee-leave-manager' ); ?></option></select></label>
							</div>
							<div class="elm-chief-edit-calendar-head"><button type="button" class="elm-icon-button" data-chief-edit-nav="-1" aria-label="<?php esc_attr_e( 'Muaji i kaluar', 'employee-leave-manager' ); ?>">&#8249;</button><strong><?php esc_html_e( 'Zgjidhni ose hiqni datat e punës', 'employee-leave-manager' ); ?></strong><button type="button" class="elm-icon-button" data-chief-edit-nav="1" aria-label="<?php esc_attr_e( 'Muaji i ardhshëm', 'employee-leave-manager' ); ?>">&#8250;</button></div>
							<div class="elm-chief-edit-calendars" data-chief-edit-calendars></div>
							<p class="elm-calendar-instruction"><?php esc_html_e( 'Klikoni në një datë pune për ta zgjedhur ose hequr nga përzgjedhja. Datat ekzistuese të kërkesës mbeten të redaktueshme.', 'employee-leave-manager' ); ?></p>
							<div class="elm-chief-edit-selected" data-chief-edit-selected></div>
							<label class="elm-chief-edit-reason"><?php esc_html_e( 'Arsyetimi', 'employee-leave-manager' ); ?><textarea name="reason" rows="4" maxlength="2000" required></textarea></label>
							<label class="elm-check elm-chief-edit-medical" data-chief-edit-medical hidden><input type="checkbox" name="medical_ack" value="1"><span><?php esc_html_e( 'Dëshmia mjekësore u konfirmua', 'employee-leave-manager' ); ?></span></label>
							<div class="elm-chief-edit-feedback" data-chief-edit-feedback hidden></div>
							<div class="elm-request-form-actions"><button type="submit" class="elm-button elm-button--primary"><?php esc_html_e( 'Ruaj ndryshimet', 'employee-leave-manager' ); ?></button><button type="button" class="elm-button elm-button--secondary" data-chief-edit-close><?php esc_html_e( 'Anulo redaktimin', 'employee-leave-manager' ); ?></button></div>
						</form>
					</div>
				</div>
				<?php if ( current_user_can( 'elm_manage_leave' ) || current_user_can( 'elm_adjust_balances' ) || current_user_can( 'manage_options' ) ) : ?>
				<div class="elm-chief-delete-overlay" data-chief-delete-dialog hidden>
					<div class="elm-chief-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="elm-chief-delete-title">
						<div class="elm-chief-delete-head"><div><p class="elm-eyebrow"><?php esc_html_e( 'Veprim i pakthyeshëm', 'employee-leave-manager' ); ?></p><h3 id="elm-chief-delete-title"><?php esc_html_e( 'Fshi kërkesën e punonjësit', 'employee-leave-manager' ); ?></h3></div><button type="button" class="elm-chief-edit-close" data-chief-delete-close aria-label="<?php esc_attr_e( 'Mbyll', 'employee-leave-manager' ); ?>">&times;</button></div>
						<p class="elm-chief-delete-help"><?php esc_html_e( 'Kërkesa do të fshihet përgjithmonë. Ky veprim nuk mund të zhbëhet. Shkruani DELETE për të vazhduar.', 'employee-leave-manager' ); ?></p>
						<div class="elm-chief-delete-summary" data-chief-delete-summary></div>
						<label class="elm-chief-delete-confirm-label"><?php esc_html_e( 'Shkruani DELETE', 'employee-leave-manager' ); ?><input type="text" data-chief-delete-confirm autocomplete="off" autocapitalize="characters" spellcheck="false" inputmode="text"></label>
						<div class="elm-chief-edit-feedback" data-chief-delete-feedback hidden></div>
						<div class="elm-request-form-actions"><button type="button" class="elm-button elm-button--danger" data-chief-delete-submit disabled><?php esc_html_e( 'Fshi përgjithmonë', 'employee-leave-manager' ); ?></button><button type="button" class="elm-button elm-button--secondary" data-chief-delete-close><?php esc_html_e( 'Anulo', 'employee-leave-manager' ); ?></button></div>
					</div>
				</div>
				<?php endif; ?>
			</div>


			<?php if ( $can_view_settings ) : ?>
			<div class="elm-portal-tab-panel" data-portal-panel="settings" hidden>
				<section class="elm-card elm-chief-settings elm-settings-hub" data-chief-settings>
					<div class="elm-card__head elm-settings-hub__head"><div><h3><?php esc_html_e( 'Cilësimet', 'employee-leave-manager' ); ?></h3><p><?php esc_html_e( 'Menaxhoni paraqitjen dhe konfigurimin e portalit sipas të drejtave tuaja.', 'employee-leave-manager' ); ?></p></div></div>
					<?php if ( $can_manage_plus_one_display ) : ?>
					<section class="elm-settings-group elm-plus-one-display-settings" aria-labelledby="elm-settings-plus-one-display">
						<div class="elm-settings-group__head elm-plus-one-display-settings__head">
							<div><h4 id="elm-settings-plus-one-display"><?php esc_html_e( 'Kërkesat +1 ditë', 'employee-leave-manager' ); ?></h4><p><?php esc_html_e( 'Normalisht seksioni shfaqet vetëm kur ka kërkesa në pritje.', 'employee-leave-manager' ); ?></p></div>
							<label class="elm-switch" title="<?php esc_attr_e( 'Shfaqe edhe kur nuk ka kërkesa në pritje', 'employee-leave-manager' ); ?>">
								<input class="elm-switch__input" type="checkbox" data-plus-one-visibility-toggle <?php checked( ! empty( $portal_settings['show_plus_one_when_empty'] ) ); ?> aria-label="<?php esc_attr_e( 'Shfaq seksionin +1 ditë edhe kur nuk ka kërkesa në pritje', 'employee-leave-manager' ); ?>">
								<span class="elm-switch__track" aria-hidden="true"><span class="elm-switch__thumb"></span></span>
							</label>
						</div>
						<div class="elm-plus-one-display-settings__meta"><span><?php esc_html_e( 'Aktivizojeni vetëm kur dëshironi të shihni historikun edhe pa kërkesa të reja.', 'employee-leave-manager' ); ?></span><strong data-plus-one-visibility-status hidden aria-live="polite"></strong></div>
					</section>
					<?php endif; ?>

					<?php if ( $can_manage_settings ) : ?>
				<section class="elm-settings-group elm-chief-employees" data-chief-employees aria-labelledby="elm-settings-employees">
					<div class="elm-settings-group__head"><div><h4 id="elm-settings-employees"><?php esc_html_e( 'Punonjësit dhe raportet', 'employee-leave-manager' ); ?></h4><p><?php esc_html_e( 'Përditësoni pozitën dhe sektorin/njësinë, shihni gjendjen vjetore dhe krijoni raportet A4.', 'employee-leave-manager' ); ?></p></div><button type="button" class="elm-refresh-button" data-chief-employees-refresh><?php esc_html_e( 'Rifresko', 'employee-leave-manager' ); ?></button></div>
					<div class="elm-chief-notice" data-chief-employees-notice hidden role="status" aria-live="polite"></div>
					<div class="elm-chief-directory-controls"><label><?php esc_html_e( 'Viti i raportimit', 'employee-leave-manager' ); ?><input type="number" min="2000" max="2100" value="<?php echo esc_attr( $year ); ?>" data-chief-employees-year></label></div>
					<div class="elm-table-wrap"><table class="elm-table elm-chief-employees-table"><thead><tr><th><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Pozita', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Sektori/Njësia', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Gjendja vjetore', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Veprimet', 'employee-leave-manager' ); ?></th></tr></thead><tbody data-chief-employees-body><tr class="elm-table-state elm-table-state--loading"><td class="elm-table-state__cell" colspan="5"><?php esc_html_e( 'Po ngarkohet...', 'employee-leave-manager' ); ?></td></tr></tbody></table></div>
				</section>

					<section class="elm-settings-group elm-chief-audit" aria-labelledby="elm-settings-audit">
						<div class="elm-settings-group__head"><div><h4 id="elm-settings-audit"><?php esc_html_e( 'Regjistri i auditimit', 'employee-leave-manager' ); ?></h4></div><button type="button" class="elm-refresh-button" data-chief-audit-verify><?php esc_html_e( 'Verifiko integritetin', 'employee-leave-manager' ); ?></button></div>
						<div class="elm-chief-audit-status" data-chief-audit-status><?php esc_html_e( 'Hapni Cilësimet për të verifikuar integritetin e regjistrit të auditimit.', 'employee-leave-manager' ); ?></div>
					</section>

					<form class="elm-chief-settings-form" data-chief-settings-form>
						<?php if ( $can_manage_access_mode ) : ?>
						<section class="elm-settings-group elm-frontend-access-mode" aria-labelledby="elm-settings-frontend-access">
							<div class="elm-settings-group__head"><div><h4 id="elm-settings-frontend-access"><?php esc_html_e( 'Qasja vetëm përmes portalit', 'employee-leave-manager' ); ?></h4><p><?php esc_html_e( 'Kur aktivizohet, llogaritë e punonjësve dhe mbikëqyrësve ridrejtohen në faqen e zgjedhur të portalit. Administratorët e WordPress-it ruajnë qasjen e plotë në panelin administrativ.', 'employee-leave-manager' ); ?></p></div></div>
							<div class="elm-settings-grid">
								<label><?php esc_html_e( 'Faqja e portalit', 'employee-leave-manager' ); ?><select name="portal_page_id"><option value="0"><?php esc_html_e( 'Zgjidhni një faqe të publikuar të portalit', 'employee-leave-manager' ); ?></option><?php foreach ( $portal_pages as $portal_page ) : ?><?php $contains_portal = has_shortcode( (string) $portal_page->post_content, 'elm_leave_portal' ) || has_shortcode( (string) $portal_page->post_content, 'employee_leave_manager' ); ?><option value="<?php echo esc_attr( $portal_page->ID ); ?>" <?php selected( absint( $portal_settings['portal_page_id'] ?? 0 ), (int) $portal_page->ID ); ?> <?php disabled( ! $contains_portal ); ?>><?php echo esc_html( $portal_page->post_title . ( $contains_portal ? '' : ' - ' . __( 'mungon kodi i shkurtër', 'employee-leave-manager' ) ) ); ?></option><?php endforeach; ?></select></label>
								<label class="elm-check elm-frontend-access-toggle"><input type="checkbox" name="frontend_only_enabled" value="1" <?php checked( ! empty( $portal_settings['frontend_only_enabled'] ) ); ?>><span><?php esc_html_e( 'Aktivizo qasjen vetëm përmes portalit për llogaritë e punonjësve dhe mbikëqyrësve', 'employee-leave-manager' ); ?></span></label>
							</div>
						</section>
						<?php endif; ?>

						<section class="elm-settings-group" aria-labelledby="elm-settings-leave-rules">
							<div class="elm-settings-group__head"><div><h4 id="elm-settings-leave-rules"><?php esc_html_e( 'Organizimi i pushimeve', 'employee-leave-manager' ); ?></h4><p><?php esc_html_e( 'Të drejtat dhe afatet ligjore janë të përcaktuara në skedën “Rregullat e pushimit”. Këtu caktohet vetëm kapaciteti operacional i institucionit.', 'employee-leave-manager' ); ?></p></div></div>
							<div class="elm-settings-grid">
								<label><span class="elm-setting-label"><?php esc_html_e( 'Kapaciteti ditor i pushimit vjetor', 'employee-leave-manager' ); ?><span class="elm-help-tip" tabindex="0" aria-label="<?php esc_attr_e( 'Numri maksimal i punonjësve të cilëve mund t\'u miratohet pushimi vjetor për të njëjtën ditë pune. Ky kufi organizativ nuk zbatohet për pushimin mjekësor.', 'employee-leave-manager' ); ?>">?<span class="elm-help-tip__text" aria-hidden="true"><?php esc_html_e( 'Kufi organizativ vetëm për miratimet e pushimit vjetor; nuk përdoret për të bllokuar pushimin mjekësor.', 'employee-leave-manager' ); ?></span></span></span><input type="number" min="1" max="20" name="concurrency_limit" value="<?php echo esc_attr( (int) ( $portal_settings['concurrency_limit'] ?? 2 ) ); ?>" required></label>
							</div>
						</section>

						<section class="elm-settings-group" aria-labelledby="elm-settings-calendar">
							<div class="elm-settings-group__head"><div><h4 id="elm-settings-calendar"><?php esc_html_e( 'Kalendari i punës', 'employee-leave-manager' ); ?></h4></div></div>
							<fieldset class="elm-settings-fieldset"><legend><span class="elm-setting-label"><?php esc_html_e( 'Ditët e punës', 'employee-leave-manager' ); ?><span class="elm-help-tip" tabindex="0" aria-label="<?php esc_attr_e( 'Ditët e zgjedhura të javës llogariten si ditë pune dhe përfshihen në ditët e shfrytëzuara të pushimit vjetor.', 'employee-leave-manager' ); ?>">?<span class="elm-help-tip__text" aria-hidden="true"><?php esc_html_e( 'Ditët e zgjedhura të javës përfshihen në ditët e shfrytëzuara të pushimit vjetor.', 'employee-leave-manager' ); ?></span></span></span></legend><div class="elm-chief-settings-checks"><?php foreach ( array( 1 => __( 'E hënë', 'employee-leave-manager' ), 2 => __( 'E martë', 'employee-leave-manager' ), 3 => __( 'E mërkurë', 'employee-leave-manager' ), 4 => __( 'E enjte', 'employee-leave-manager' ), 5 => __( 'E premte', 'employee-leave-manager' ), 6 => __( 'E shtunë', 'employee-leave-manager' ), 7 => __( 'E diel', 'employee-leave-manager' ) ) as $number => $label ) : ?><label><input type="checkbox" name="working_weekdays[]" value="<?php echo esc_attr( $number ); ?>" <?php checked( in_array( $number, array_map( 'intval', (array) ( $portal_settings['working_weekdays'] ?? array( 1, 2, 3, 4, 5 ) ) ), true ) ); ?>><?php echo esc_html( $label ); ?></label><?php endforeach; ?></div></fieldset>
							<?php $fixed_holidays = ELM_Policy::fixed_holiday_definitions(); $movable_edit_dates = ELM_Policy::movable_holiday_edit_dates( $portal_settings ); ?>
							<div class="elm-chief-settings-wide elm-holiday-policy">
								<div class="elm-holiday-policy__fixed">
									<div class="elm-holiday-policy__head">
										<div><strong><?php esc_html_e( 'Festat e përsëritshme', 'employee-leave-manager' ); ?></strong><p><?php esc_html_e( 'Këto data zbatohen automatikisht çdo vit. Nuk është e nevojshme t’i shtoni përsëri për vitin e ardhshëm.', 'employee-leave-manager' ); ?></p></div>
										<span class="elm-holiday-policy__badge"><?php esc_html_e( 'Automatike', 'employee-leave-manager' ); ?></span>
									</div>
									<div class="elm-fixed-holiday-grid">
										<?php foreach ( $fixed_holidays as $month_day => $holiday_name ) : ?>
											<div class="elm-fixed-holiday"><time><?php echo esc_html( str_replace( '-', '.', $month_day ) ); ?></time><span><?php echo esc_html( $holiday_name ); ?></span></div>
										<?php endforeach; ?>
									</div>
								</div>
								<div class="elm-holiday-policy__movable">
									<div class="elm-holiday-policy__head">
										<div><strong><?php esc_html_e( 'Festat me datë të ndryshueshme', 'employee-leave-manager' ); ?></strong><p><?php esc_html_e( 'Vetëm këto dy festa ndryshohen sipas vitit. Klikoni fushën e datës dhe zgjidheni ditën drejtpërdrejt nga kalendari.', 'employee-leave-manager' ); ?></p></div>
									</div>
									<div class="elm-movable-holiday-grid">
										<label><span><?php esc_html_e( 'Bajrami i Madh, dita e parë', 'employee-leave-manager' ); ?></span><input type="date" name="movable_bajrami_i_madh" min="2000-01-01" max="2100-12-31" value="<?php echo esc_attr( $movable_edit_dates['bajrami_i_madh'] ?? '' ); ?>"></label>
										<label><span><?php esc_html_e( 'Bajrami i Vogël, dita e parë', 'employee-leave-manager' ); ?></span><input type="date" name="movable_bajrami_i_vogel" min="2000-01-01" max="2100-12-31" value="<?php echo esc_attr( $movable_edit_dates['bajrami_i_vogel'] ?? '' ); ?>"></label>
									</div>
									<p class="elm-holiday-policy__note"><?php esc_html_e( 'Kur ruani një datë për një vit të ri, datat e viteve të mëparshme ruhen në historik dhe kalendari përdor automatikisht datën përkatëse për secilin vit.', 'employee-leave-manager' ); ?></p>
								</div>
							</div>
						</section>

						<section class="elm-settings-group" aria-labelledby="elm-settings-documents">
							<div class="elm-settings-group__head"><div><h4 id="elm-settings-documents"><?php esc_html_e( 'Dokumentet', 'employee-leave-manager' ); ?></h4></div></div>
							<div class="elm-settings-grid"><label><span class="elm-setting-label"><?php esc_html_e( 'Madhësia maksimale e dokumentit', 'employee-leave-manager' ); ?><span class="elm-help-tip" tabindex="0" aria-label="<?php esc_attr_e( 'Madhësia maksimale e dokumentit mjekësor, në megabajt.', 'employee-leave-manager' ); ?>">?<span class="elm-help-tip__text" aria-hidden="true"><?php esc_html_e( 'Madhësia maksimale e dokumentit mjekësor në MB.', 'employee-leave-manager' ); ?></span></span></span><input type="number" min="1" max="50" name="max_upload_mb" value="<?php echo esc_attr( (int) ( $portal_settings['max_upload_mb'] ?? 10 ) ); ?>" required></label></div>
						</section>


						<div class="elm-settings-save"><button type="submit" class="elm-button elm-button--primary"><?php esc_html_e( 'Ruaj cilësimet', 'employee-leave-manager' ); ?></button></div>
					</form>
					<?php endif; ?>

				</section>
			</div>
			<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		if ( $print_fallback_script ) {
			wp_print_scripts( array( 'elm-portal' ) );
		}
		return (string) ob_get_clean();
	}

	private function render_initial_month( DateTimeInterface $month, int $index, array $days = array(), string $notice_end = '' ): void {
		$year                  = (int) $month->format( 'Y' );
		$month_number          = (int) $month->format( 'n' );
		$first_day_offset      = (int) $month->format( 'N' ) - 1;
		$days_in_month         = (int) $month->format( 't' );
		$days_in_previous      = (int) $month->modify( '-1 month' )->format( 't' );
		$today                 = ELM_Policy::today();
		$settings              = ELM_Policy::settings();
		$working_weekdays      = array_map( 'intval', (array) $settings['working_weekdays'] );
		$holiday_names         = ELM_Policy::effective_holiday_map( $settings );
		$holidays              = array_flip( array_keys( $holiday_names ) );
		$weekday_abbreviations = array(
			esc_html_x( 'HËN', 'Monday abbreviation', 'employee-leave-manager' ),
			esc_html_x( 'MAR', 'Tuesday abbreviation', 'employee-leave-manager' ),
			esc_html_x( 'MËR', 'Wednesday abbreviation', 'employee-leave-manager' ),
			esc_html_x( 'ENJ', 'Thursday abbreviation', 'employee-leave-manager' ),
			esc_html_x( 'PRE', 'Friday abbreviation', 'employee-leave-manager' ),
			esc_html_x( 'SHT', 'Saturday abbreviation', 'employee-leave-manager' ),
			esc_html_x( 'DIE', 'Sunday abbreviation', 'employee-leave-manager' ),
		);
		$month_names = array(
			1  => __( 'Janar', 'employee-leave-manager' ),
			2  => __( 'Shkurt', 'employee-leave-manager' ),
			3  => __( 'Mars', 'employee-leave-manager' ),
			4  => __( 'Prill', 'employee-leave-manager' ),
			5  => __( 'Maj', 'employee-leave-manager' ),
			6  => __( 'Qershor', 'employee-leave-manager' ),
			7  => __( 'Korrik', 'employee-leave-manager' ),
			8  => __( 'Gusht', 'employee-leave-manager' ),
			9  => __( 'Shtator', 'employee-leave-manager' ),
			10 => __( 'Tetor', 'employee-leave-manager' ),
			11 => __( 'Nëntor', 'employee-leave-manager' ),
			12 => __( 'Dhjetor', 'employee-leave-manager' ),
		);
		$cells = array();

		for ( $cell = 0; $cell < $first_day_offset; $cell++ ) {
			$cells[] = array( 'day' => $days_in_previous - $first_day_offset + 1 + $cell, 'other' => true );
		}
		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$cells[] = array( 'day' => $day, 'other' => false );
		}
		$next_day = 1;
		while ( count( $cells ) % 7 ) {
			$cells[] = array( 'day' => $next_day++, 'other' => true );
		}
		?>
		<div class="ot-month" data-ot-month-index="<?php echo esc_attr( $index ); ?>">
			<div class="ot-month-title"><?php echo esc_html( sprintf( '%s %d', $month_names[ $month_number ], $year ) ); ?></div>
			<table class="ot-cal-table">
				<thead><tr><?php foreach ( $weekday_abbreviations as $weekday ) : ?><th><?php echo esc_html( $weekday ); ?></th><?php endforeach; ?></tr></thead>
				<tbody>
				<?php foreach ( array_chunk( $cells, 7 ) as $week ) : ?>
					<tr>
					<?php foreach ( $week as $cell ) : ?>
						<td>
						<?php if ( $cell['other'] ) : ?>
							<span class="ot-day ot-other-month" aria-hidden="true"><?php echo esc_html( $cell['day'] ); ?></span>
						<?php else : ?>
							<?php
							$date          = sprintf( '%04d-%02d-%02d', $year, $month_number, $cell['day'] );
							$parsed        = new DateTimeImmutable( $date, wp_timezone() );
							$day_data      = is_array( $days[ $date ] ?? null ) ? $days[ $date ] : array();
							$is_holiday    = isset( $holidays[ $date ] ) || ! empty( $day_data['holiday'] );
							$holiday_name  = sanitize_text_field( (string) ( $day_data['holiday_name'] ?? ( $holiday_names[ $date ] ?? '' ) ) );
							$is_weekend    = (int) $parsed->format( 'N' ) >= 6;
							$is_working    = array_key_exists( 'working_day', $day_data ) ? ! empty( $day_data['working_day'] ) : in_array( (int) $parsed->format( 'N' ), $working_weekdays, true ) && ! $is_holiday;
							$is_capacity   = ! empty( $day_data['capacity_blocked'] );
							$own_status    = sanitize_key( (string) ( $day_data['own_status'] ?? '' ) );
							$own_leave_type = sanitize_key( (string) ( $day_data['own_leave_type'] ?? '' ) );
							$is_own_active = in_array( $own_status, array( 'pending', 'approved' ), true );
							$is_policy_warning = ! empty( $day_data['own_policy_warning'] );
							$is_past       = $date < $today;
							$is_notice     = ! empty( $day_data['short_notice'] ) || ( $date >= $today && $notice_end && $date <= $notice_end );
							$classes       = array( 'ot-day' );
							if ( ! $is_working ) {
								$classes[] = 'ot-nonworking';
							}
							if ( $is_weekend ) {
								$classes[] = 'ot-weekend';
							}
							if ( $is_holiday ) {
								$classes[] = 'ot-holiday';
							}
							if ( $is_capacity ) {
								$classes[] = 'ot-capacity-full';
							}
							if ( $own_status ) {
								$classes[] = 'ot-own-status';
								$classes[] = 'ot-own-status--' . $own_status;
							}
							if ( $is_policy_warning ) {
								$classes[] = 'ot-own-policy-warning';
							}
							if ( $is_notice ) {
								$classes[] = 'ot-short-notice-warning';
							}
							if ( $is_capacity || $is_own_active || $is_past ) {
								$classes[] = 'ot-disabled';
							}
							$day_note = (string) ( $day_data['day_note'] ?? ( $is_holiday ? ( $holiday_name ? sprintf( __( 'Festë: %s. Nuk llogaritet si ditë pushimi.', 'employee-leave-manager' ), $holiday_name ) : __( 'Festë e institucionit; nuk llogaritet si ditë pushimi.', 'employee-leave-manager' ) ) : ( $is_working ? '' : __( 'Ditë jopune; nuk llogaritet si ditë pushimi.', 'employee-leave-manager' ) ) ) );

							$notice_note = $is_notice ? __( 'Vërejtje: data është brenda afatit minimal 15-ditor për pushim vjetor. Mund të përzgjidhet.', 'employee-leave-manager' ) : '';

							$display_note = trim( $notice_note . ' ' . $day_note );
							?>
							<button
								type="button"
								class="<?php echo esc_attr( implode( ' ', array_unique( $classes ) ) ); ?>"
								data-ot-date="<?php echo esc_attr( $date ); ?>"
								data-working-day="<?php echo $is_working ? '1' : '0'; ?>"
								data-weekend="<?php echo $is_weekend ? '1' : '0'; ?>"
								data-holiday="<?php echo $is_holiday ? '1' : '0'; ?>"
								data-holiday-name="<?php echo esc_attr( $holiday_name ); ?>"
								data-capacity-blocked="<?php echo $is_capacity ? '1' : '0'; ?>"
								data-approved="<?php echo esc_attr( (int) ( $day_data['approved'] ?? 0 ) ); ?>"
								data-pending="<?php echo esc_attr( (int) ( $day_data['pending'] ?? 0 ) ); ?>"
								data-own-status="<?php echo esc_attr( $own_status ); ?>"
								data-own-leave-type="<?php echo esc_attr( $own_leave_type ); ?>"
								data-own-policy-warning="<?php echo $is_policy_warning ? '1' : '0'; ?>"
								data-short-notice="<?php echo $is_notice ? '1' : '0'; ?>"
								data-day-note="<?php echo esc_attr( $day_note ); ?>"
								aria-pressed="false"
								<?php echo ( $is_capacity || $is_own_active || $is_past ) ? 'aria-disabled="true"' : ''; ?>
								title="<?php echo esc_attr( $display_note ); ?>"
								aria-label="<?php echo esc_attr( trim( $this->format_date( $date ) . '. ' . $display_note ) ); ?>"
							><span class="ot-day-number"><?php echo esc_html( $cell['day'] ); ?></span><?php if ( $is_holiday ) : ?><span class="ot-holiday-help" data-holiday-name="<?php echo esc_attr( $holiday_name ?: __( 'Festë e institucionit', 'employee-leave-manager' ) ); ?>" aria-hidden="true">?</span><?php endif; ?></button>
						<?php endif; ?>
						</td>
					<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_request_rows( array $requests ): void {
		if ( ! $requests ) {
			echo '<tr class="elm-table-state elm-table-state--empty"><td class="elm-table-state__cell" colspan="6">' . esc_html__( 'Nuk u gjet asnjë kërkesë për pushim.', 'employee-leave-manager' ) . '</td></tr>';
			return;
		}
		foreach ( $requests as $request ) {
			$status       = sanitize_key( (string) $request['status'] );
			$date_values = is_array( $request['selected_dates'] ?? null ) ? $request['selected_dates'] : array();
			if ( $date_values ) {
				$date_label = $this->format_grouped_dates( $date_values );
			} else {
				$fallback_dates = array_values( array_unique( array_filter( array( $request['start_date'] ?? '', $request['end_date'] ?? '' ) ) ) );
				$date_label = $this->format_grouped_dates( $fallback_dates );
			}
			$reason_history = is_array( $request['reason_history'] ?? null ) ? $request['reason_history'] : array();
			$pdf_url      = wp_nonce_url( admin_url( 'admin-post.php?action=elm_export_request_pdf&elm_frontend=1&request_id=' . absint( $request['id'] ) ), 'elm_export_request_pdf_frontend' );
			?>
			<tr>
				<td><?php echo esc_html( 'annual' === $request['leave_type'] ? __( 'Pushim vjetor', 'employee-leave-manager' ) : __( 'Pushim mjekësor', 'employee-leave-manager' ) ); ?></td>
				<td class="elm-request-dates">
					<?php echo esc_html( $date_label ); ?>
					<?php if ( $reason_history ) : ?>
						<div class="elm-request-reasons-notice">
							<?php foreach ( $reason_history as $reason_index => $reason_entry ) : ?>
								<div class="elm-request-reasons-item elm-request-reasons-item--<?php echo esc_attr( sanitize_key( (string) ( $reason_entry['status'] ?? 'pending' ) ) ); ?>">
									<span class="elm-request-reasons-number">#<?php echo esc_html( (string) ( $reason_index + 1 ) ); ?></span>
									<span class="elm-request-reasons-content"><strong class="elm-request-reasons-actor"><?php echo esc_html( trim( (string) ( $reason_entry['actor_name'] ?? '' ) ) ); ?>:</strong> <span><?php echo esc_html( (string) ( $reason_entry['text'] ?? '' ) ); ?></span></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $this->whole_days( $request['requested_units'] ) ); ?></td>
				<td><span class="elm-status elm-status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $this->status_label( $status ) ); ?></span></td>
				<td><?php echo esc_html( $this->format_datetime( $request['submitted_at'] ) ); ?></td>
				<td class="elm-table-actions">
					<div class="elm-table-actions-inner elm-my-actions">
						<?php if ( 'pending' === $status ) : ?>
							<button type="button" class="button button-small elm-self-action elm-self-action--edit" data-edit-request="<?php echo esc_attr( $request['id'] ); ?>" aria-label="<?php esc_attr_e( 'Redakto', 'employee-leave-manager' ); ?>" title="<?php esc_attr_e( 'Redakto', 'employee-leave-manager' ); ?>"><?php esc_html_e( 'Redakto', 'employee-leave-manager' ); ?></button>
							<?php if ( current_user_can( 'elm_manage_leave' ) || current_user_can( 'manage_options' ) ) : ?>
								<button type="button" class="button button-small elm-self-action elm-self-action--approve" data-own-decision="approved" data-own-decision-request="<?php echo esc_attr( $request['id'] ); ?>" aria-label="<?php esc_attr_e( 'Mirato', 'employee-leave-manager' ); ?>" title="<?php esc_attr_e( 'Mirato', 'employee-leave-manager' ); ?>"><?php esc_html_e( 'Mirato', 'employee-leave-manager' ); ?></button>
								<button type="button" class="button button-small elm-self-action elm-self-action--reject" data-own-decision="rejected" data-own-decision-request="<?php echo esc_attr( $request['id'] ); ?>" aria-label="<?php esc_attr_e( 'Refuzo', 'employee-leave-manager' ); ?>" title="<?php esc_attr_e( 'Refuzo', 'employee-leave-manager' ); ?>"><?php esc_html_e( 'Refuzo', 'employee-leave-manager' ); ?></button>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( in_array( $status, array( 'pending', 'approved' ), true ) ) : ?>
							<form class="elm-inline-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="elm_cancel_request_form">
								<?php wp_nonce_field( 'elm_cancel_request', 'elm_cancel_request_nonce' ); ?>
								<input type="hidden" name="request_id" value="<?php echo esc_attr( $request['id'] ); ?>">
								<input type="hidden" name="cancel_reason" value="">
								<noscript><label class="elm-cancel-reason-fallback"><?php esc_html_e( 'Arsyetimi i anulimit', 'employee-leave-manager' ); ?><input type="text" name="cancel_reason" maxlength="2000" required></label></noscript>
								<input type="hidden" name="return_url" value="<?php echo esc_url( remove_query_arg( array( 'elm_notice', 'elm_message', 'elm_request_id' ) ) ); ?>">
								<button type="submit" class="button button-small elm-self-action elm-self-action--cancel" data-cancel-request="<?php echo esc_attr( $request['id'] ); ?>" aria-label="<?php esc_attr_e( 'Anulo', 'employee-leave-manager' ); ?>" title="<?php esc_attr_e( 'Anulo', 'employee-leave-manager' ); ?>"><?php esc_html_e( 'Anulo', 'employee-leave-manager' ); ?></button>
							</form>
						<?php endif; ?>
						<?php if ( current_user_can( 'elm_manage_leave' ) || current_user_can( 'elm_adjust_balances' ) || current_user_can( 'manage_options' ) ) : ?>
							<button type="button" class="button button-small elm-self-action elm-self-action--delete button-link-delete" data-delete-own-request="<?php echo esc_attr( $request['id'] ); ?>" aria-label="<?php esc_attr_e( 'Fshi', 'employee-leave-manager' ); ?>" title="<?php esc_attr_e( 'Fshi', 'employee-leave-manager' ); ?>"><?php esc_html_e( 'Fshi', 'employee-leave-manager' ); ?></button>
						<?php endif; ?>
						<a class="button button-small elm-self-action elm-self-action--pdf" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'PDF', 'employee-leave-manager' ); ?>" title="<?php esc_attr_e( 'PDF', 'employee-leave-manager' ); ?>"><?php esc_html_e( 'PDF', 'employee-leave-manager' ); ?></a>
					</div>
				</td>
			</tr>
			<?php
		}
	}

	private function status_label( string $status ): string {
		return match ( sanitize_key( $status ) ) {
			'pending'   => __( 'Në pritje', 'employee-leave-manager' ),
			'approved'  => __( 'Miratuar', 'employee-leave-manager' ),
			'rejected'  => __( 'Refuzuar', 'employee-leave-manager' ),
			'cancelled' => __( 'Anuluar', 'employee-leave-manager' ),
			default     => sanitize_text_field( $status ),
		};
	}

	private function format_date( string $date ): string {
		$timestamp = strtotime( $date . ' 12:00:00' );
		return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : $date;
	}

	private function format_day_number( string $date ): string {
		$timestamp = strtotime( $date . ' 12:00:00' );
		return $timestamp ? wp_date( 'j', $timestamp ) : $date;
	}

	private function format_grouped_dates( array $dates ): string {
		$groups = array();
		$dates  = array_values( array_unique( array_filter( array_map( 'strval', $dates ) ) ) );
		sort( $dates, SORT_STRING );

		foreach ( $dates as $date ) {
			$timestamp = strtotime( $date . ' 12:00:00' );
			if ( ! $timestamp ) {
				continue;
			}
			$year  = wp_date( 'Y', $timestamp );
			$month = wp_date( 'm', $timestamp );
			$key   = $year . '-' . $month;
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'year'  => $year,
					'month' => $month,
					'days'  => array(),
				);
			}
			$groups[ $key ]['days'][] = wp_date( 'j', $timestamp );
		}

		$labels = array();
		foreach ( $groups as $group ) {
			$labels[] = implode( ', ', $group['days'] ) . ' | ' . $group['month'] . ' | ' . $group['year'];
		}

		return implode( ' ; ', $labels );
	}

	private function format_datetime( string $datetime ): string {
		$timestamp = strtotime( $datetime );
		return $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : $datetime;
	}

	private function whole_days( mixed $value ): string {
		return (string) max( 0, (int) round( (float) $value ) );
	}

	private function remaining_ring_percent( array $balance ): int {
		$total = max( 0.0, (float) ( $balance['total_entitlement'] ?? 0 ) );
		$remaining = max( 0.0, (float) ( $balance['remaining_after_pending'] ?? 0 ) );
		return $total > 0 ? (int) min( 100, round( ( $remaining / $total ) * 100 ) ) : 0;
	}
	private function redact_calendar_occupancy( array $days ): array {
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
		return $days;
	}

}
