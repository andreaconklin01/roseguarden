<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_Admin {
	/** @var string[] Page hook suffixes registered by this plugin, populated in register_menu(). */
	private array $page_hooks = array();


	public function render_schema_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! get_option( 'elm_schema_error', false ) ) {
			return;
		}
		?>
		<div class="notice notice-error"><p><strong><?php esc_html_e( 'Kërkohet riparimi i bazës së të dhënave të shtojcës për menaxhimin e pushimeve.', 'employee-leave-manager' ); ?></strong> <?php esc_html_e( 'Çaktivizoni dhe riaktivizoni shtojcën. Nëse mesazhi vazhdon të shfaqet, kontrolloni lejet e përdoruesit të bazës së të dhënave dhe regjistrin e gabimeve të PHP-së.', 'employee-leave-manager' ); ?></p></div>
		<?php
	}

	public function register_menu(): void {
		$hooks   = array();
		$hooks[] = add_menu_page(
			__( 'Menaxhimi i pushimeve', 'employee-leave-manager' ),
			__( 'Menaxhimi i pushimeve', 'employee-leave-manager' ),
			'elm_manage_leave',
			'elm-leave',
			array( $this, 'render_dashboard' ),
			'dashicons-calendar-alt',
			30
		);
		$hooks[] = add_submenu_page( 'elm-leave', __( 'Kërkesat', 'employee-leave-manager' ), __( 'Kërkesat', 'employee-leave-manager' ), 'elm_manage_leave', 'elm-leave', array( $this, 'render_dashboard' ) );
		$hooks[] = add_submenu_page( 'elm-leave', __( 'Ditët e pushimit', 'employee-leave-manager' ), __( 'Ditët e pushimit', 'employee-leave-manager' ), 'elm_adjust_balances', 'elm-leave-adjustments', array( $this, 'render_adjustments' ) );
		$hooks[] = add_submenu_page( 'elm-leave', __( 'Raportet PDF', 'employee-leave-manager' ), __( 'Raportet PDF', 'employee-leave-manager' ), 'elm_export_leave_reports', 'elm-leave-reports', array( $this, 'render_reports' ) );
		$hooks[] = add_submenu_page( 'elm-leave', __( 'Qasja e udhëheqësit në portal', 'employee-leave-manager' ), __( 'Qasja e udhëheqësit në portal', 'employee-leave-manager' ), 'manage_options', 'elm-chief-access', array( $this, 'render_chief_access' ) );
		$hooks[] = add_submenu_page( 'elm-leave', __( 'Cilësimet', 'employee-leave-manager' ), __( 'Cilësimet', 'employee-leave-manager' ), 'manage_options', 'elm-leave-settings', array( $this, 'render_settings' ) );
		$this->page_hooks = array_values( array_unique( array_filter( $hooks, 'is_string' ) ) );
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, $this->page_hooks, true ) ) {
			return;
		}
		wp_enqueue_style( 'elm-admin', ELM_URL . 'assets/css/admin.css', array(), ELM_VERSION );
		wp_enqueue_script( 'elm-admin', ELM_URL . 'assets/js/admin.js', array(), ELM_VERSION, true );
		wp_localize_script(
			'elm-admin',
			'ELMAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( 'elm/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'exportRequestBase' => admin_url( 'admin-post.php?action=elm_export_request_pdf&request_id=' ),
				'exportRequestNonce'=> wp_create_nonce( 'elm_export_request_pdf' ),
				'exportEmployeeBase' => admin_url( 'admin-post.php?action=elm_export_employee_pdf&user_id=' ),
				'exportEmployeeNonce'=> wp_create_nonce( 'elm_export_employee_pdf' ),
				'canManageEmployeeProfiles' => current_user_can( 'elm_manage_leave' ) || current_user_can( 'manage_options' ),
				'downloadMedicalBase'=> admin_url( 'admin-post.php?action=elm_download_medical&document_id=' ),
				'downloadMedicalNonce'=> wp_create_nonce( 'elm_download_medical' ),
				'canViewMedical'      => current_user_can( 'elm_view_medical_documents' ),
				'canApproveExceptions' => current_user_can( 'elm_adjust_balances' ),
				'canDeleteRequests'     => current_user_can( 'elm_adjust_balances' ) || current_user_can( 'manage_options' ),
				'i18n' => array(
					'approvePrompt'          => __( 'Arsyetim për miratimin (opsional):', 'employee-leave-manager' ),
					'exceptionApprovePrompt' => __( 'Arsyetim për miratimin (opsional):', 'employee-leave-manager' ),
					'exceptionConfirm'       => __( 'Ta miratoni këtë kërkesë?', 'employee-leave-manager' ),
					'chiefOnly'              => __( 'Nuk keni leje ta miratoni këtë kërkesë.', 'employee-leave-manager' ),
					'rejectPrompt'           => __( 'Arsyetimi i refuzimit:', 'employee-leave-manager' ),
					'editLabel'              => __( 'Redakto', 'employee-leave-manager' ),
					'editPendingOnly'        => __( 'Mund të redaktohen vetëm kërkesat në pritje.', 'employee-leave-manager' ),
					'editTitle'              => __( 'Redakto kërkesën në pritje', 'employee-leave-manager' ),
					'editEmployee'           => __( 'Punonjësi', 'employee-leave-manager' ),
					'editLeaveType'          => __( 'Lloji i pushimit', 'employee-leave-manager' ),
					'editSelectedDates'      => __( 'Datat e zgjedhura', 'employee-leave-manager' ),
					'editCalendarHint'       => __( 'Klikoni në një datë pune për ta zgjedhur ose hequr nga përzgjedhja. Datat ekzistuese të kërkesës mbeten të redaktueshme.', 'employee-leave-manager' ),
					'editReason'             => __( 'Arsyetimi', 'employee-leave-manager' ),
					'editMedicalAck'         => __( 'Dëshmia mjekësore u konfirmua', 'employee-leave-manager' ),
					'editSave'               => __( 'Ruaj ndryshimet', 'employee-leave-manager' ),
					'editCancel'             => __( 'Anulo redaktimin', 'employee-leave-manager' ),
					'editSaved'              => __( 'Ndryshimet e kërkesës u ruajtën.', 'employee-leave-manager' ),
					'editInvalidType'        => __( 'Zgjidhni pushim vjetor ose mjekësor.', 'employee-leave-manager' ),
					'editInvalidDates'       => __( 'Zgjidhni së paku një datë pune të disponueshme.', 'employee-leave-manager' ),
					'editCrossYear'          => __( 'Periudha mund të vazhdojë në vitin e ardhshëm kalendarik.', 'employee-leave-manager' ),
					'editReasonRequired'     => __( 'Shkruani arsyetimin e kërkesës.', 'employee-leave-manager' ),
					'editLoadingCalendar'    => __( 'Po ngarkohet kalendari...', 'employee-leave-manager' ),
					'annualLabel'            => __( 'Pushim vjetor', 'employee-leave-manager' ),
					'medicalLabel'           => __( 'Pushim mjekësor', 'employee-leave-manager' ),
					'employeeReason'         => __( 'Arsyetimi i punonjësit', 'employee-leave-manager' ),
					'chiefRequestReason'      => __( 'Arsyetimi i kërkesës nga mbikëqyrësi', 'employee-leave-manager' ),
					'chiefDecisionReason'    => __( 'Arsyetimi i vendimit nga mbikëqyrësi', 'employee-leave-manager' ),
					'employeeCancelReason'   => __( 'Arsyetimi i anulimit nga punonjësi', 'employee-leave-manager' ),
					'chiefCancelReason'      => __( 'Arsyetimi i anulimit nga mbikëqyrësi', 'employee-leave-manager' ),
					'employerReasons'         => __( 'Punonjësi', 'employee-leave-manager' ),
					'chiefReasons'            => __( 'Mbikëqyrësi', 'employee-leave-manager' ),
					'deleteRequestTitle'     => __( 'Fshi kërkesën e punonjësit', 'employee-leave-manager' ),
					'deleteRequestHelp'      => __( 'Kërkesa do të fshihet përgjithmonë. Ky veprim nuk mund të zhbëhet. Shkruani DELETE për të vazhduar.', 'employee-leave-manager' ),
					'deleteRequestConfirm'   => __( 'Shkruani saktësisht DELETE për të konfirmuar fshirjen.', 'employee-leave-manager' ),
					'deleteRequestSuccess'   => __( 'Kërkesa u fshi përgjithmonë.', 'employee-leave-manager' ),
					'genericError'           => __( 'Veprimi dështoi.', 'employee-leave-manager' ),
				),
			)
		);
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( 'elm_manage_leave' ) ) {
			wp_die( esc_html__( 'Nuk keni leje të menaxhoni pushimet.', 'employee-leave-manager' ) );
		}
		?>
		<div class="wrap elm-admin" data-elm-admin="requests">
			<div class="elm-admin__hero">
				<div><p class="elm-admin__eyebrow"><?php esc_html_e( 'Burimet njerëzore', 'employee-leave-manager' ); ?></p><h1><?php esc_html_e( 'Menaxhimi i kërkesave për pushim', 'employee-leave-manager' ); ?><span class="elm-pro-badge">Pro</span></h1><p><?php esc_html_e( 'Shqyrtoni kërkesat, merrni vendime dhe shkarkoni raportet përkatëse.', 'employee-leave-manager' ); ?></p></div>
				<div class="elm-admin__audit" data-audit-status><?php esc_html_e( 'Po verifikohet regjistri i auditimit...', 'employee-leave-manager' ); ?></div>
			</div>
			<div class="elm-admin__notice" data-admin-notice hidden></div>
			<div class="elm-admin__filters">
				<label><?php esc_html_e( 'Statusi', 'employee-leave-manager' ); ?><select data-filter-status><option value=""><?php esc_html_e( 'Të gjitha statuset', 'employee-leave-manager' ); ?></option><option value="pending"><?php esc_html_e( 'Në pritje', 'employee-leave-manager' ); ?></option><option value="approved"><?php esc_html_e( 'Miratuar', 'employee-leave-manager' ); ?></option><option value="rejected"><?php esc_html_e( 'Refuzuar', 'employee-leave-manager' ); ?></option><option value="cancelled"><?php esc_html_e( 'Anuluar', 'employee-leave-manager' ); ?></option></select></label>
				<label><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?><select data-filter-user><option value=""><?php esc_html_e( 'Të gjithë punonjësit', 'employee-leave-manager' ); ?></option></select></label>
				<label><?php esc_html_e( 'Viti', 'employee-leave-manager' ); ?><input type="number" min="2000" max="2100" value="" placeholder="<?php esc_attr_e( 'Të gjitha vitet', 'employee-leave-manager' ); ?>" data-filter-year></label>
				<button type="button" class="button button-primary" data-apply-filters><?php esc_html_e( 'Zbato', 'employee-leave-manager' ); ?></button>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=elm_export_requests_csv' ), 'elm_export_requests_csv' ) ); ?>"><span class="dashicons dashicons-media-spreadsheet" style="margin-top:3px;"></span> <?php esc_html_e( 'Eksporto CSV', 'employee-leave-manager' ); ?></a>
			</div>
			<div class="elm-admin__card"><div class="elm-admin__table-wrap"><table class="widefat fixed striped elm-admin__table"><thead><tr><th><?php esc_html_e( 'ID', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Lloji / datat', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Arsyetimet', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Statusi', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Paraqitur më', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Veprimet', 'employee-leave-manager' ); ?></th></tr></thead><tbody data-admin-requests><tr class="elm-table-state elm-table-state--loading"><td class="elm-table-state__cell" colspan="7"><?php esc_html_e( 'Po ngarkohet...', 'employee-leave-manager' ); ?></td></tr></tbody></table></div></div>

			<div class="elm-admin__edit-overlay" data-admin-edit-dialog hidden>
				<div class="elm-admin__edit-dialog" role="dialog" aria-modal="true" aria-labelledby="elm-admin-edit-title">
					<form class="elm-admin__edit-form" data-admin-edit-form>
						<div class="elm-admin__edit-head">
							<div><p class="elm-admin__eyebrow"><?php esc_html_e( 'Kërkesë në pritje', 'employee-leave-manager' ); ?></p><h2 id="elm-admin-edit-title"><?php esc_html_e( 'Redakto kërkesën në pritje', 'employee-leave-manager' ); ?></h2></div>
							<button type="button" class="elm-admin__edit-close" data-admin-edit-close aria-label="<?php esc_attr_e( 'Mbyll formularin', 'employee-leave-manager' ); ?>">&times;</button>
						</div>
						<input type="hidden" name="request_id" value="">
						<div class="elm-admin__edit-grid">
							<label><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?><input type="text" data-admin-edit-employee readonly></label>
							<label><?php esc_html_e( 'Lloji i pushimit', 'employee-leave-manager' ); ?><select name="leave_type" required><option value="annual"><?php esc_html_e( 'Pushim vjetor', 'employee-leave-manager' ); ?></option><option value="medical"><?php esc_html_e( 'Pushim mjekësor', 'employee-leave-manager' ); ?></option></select></label>
						</div>
						<div class="elm-admin__edit-calendar-section">
							<div class="elm-admin__edit-calendar-head"><strong><?php esc_html_e( 'Datat e zgjedhura', 'employee-leave-manager' ); ?></strong><div><button type="button" class="button button-small" data-admin-edit-nav="-1" aria-label="<?php esc_attr_e( 'Muaji i kaluar', 'employee-leave-manager' ); ?>">&#8249;</button><button type="button" class="button button-small" data-admin-edit-nav="1" aria-label="<?php esc_attr_e( 'Muaji i ardhshëm', 'employee-leave-manager' ); ?>">&#8250;</button></div></div>
							<div class="elm-admin__edit-calendars" data-admin-edit-calendars></div>
							<p class="description"><?php esc_html_e( 'Klikoni në një datë pune për ta zgjedhur ose hequr nga përzgjedhja. Datat ekzistuese të kërkesës mbeten të redaktueshme.', 'employee-leave-manager' ); ?></p>
							<div class="elm-admin__edit-selected" data-admin-edit-selected></div>
						</div>
						<label class="elm-admin__edit-reason"><?php esc_html_e( 'Arsyetimi', 'employee-leave-manager' ); ?><textarea name="reason" rows="4" maxlength="2000" required></textarea></label>
						<label class="elm-admin__edit-medical" data-admin-edit-medical hidden><input type="checkbox" name="medical_ack" value="1"> <span><?php esc_html_e( 'Dëshmia mjekësore u konfirmua', 'employee-leave-manager' ); ?></span></label>
						<div class="elm-admin__edit-feedback" data-admin-edit-feedback hidden></div>
						<div class="elm-admin__edit-actions"><button type="button" class="button" data-admin-edit-close><?php esc_html_e( 'Anulo redaktimin', 'employee-leave-manager' ); ?></button><button type="submit" class="button button-primary"><?php esc_html_e( 'Ruaj ndryshimet', 'employee-leave-manager' ); ?></button></div>
					</form>
				</div>
			</div>

			<div class="elm-admin__edit-overlay" data-admin-delete-dialog hidden>
				<div class="elm-admin__edit-dialog elm-admin__delete-dialog" role="dialog" aria-modal="true" aria-labelledby="elm-admin-delete-title">
					<div class="elm-admin__edit-form">
						<div class="elm-admin__edit-head">
							<div><p class="elm-admin__eyebrow"><?php esc_html_e( 'Veprim i pakthyeshëm', 'employee-leave-manager' ); ?></p><h2 id="elm-admin-delete-title"><?php esc_html_e( 'Fshi kërkesën e punonjësit', 'employee-leave-manager' ); ?></h2></div>
							<button type="button" class="elm-admin__edit-close" data-admin-delete-close aria-label="<?php esc_attr_e( 'Mbyll dritaren e konfirmimit', 'employee-leave-manager' ); ?>">&times;</button>
						</div>
						<p class="elm-admin__delete-summary" data-admin-delete-summary></p>
						<p class="description"><?php esc_html_e( 'Kërkesa do të fshihet përgjithmonë. Ky veprim nuk mund të zhbëhet. Shkruani DELETE për të vazhduar.', 'employee-leave-manager' ); ?></p>
						<label><?php esc_html_e( 'Konfirmimi', 'employee-leave-manager' ); ?><input type="text" autocomplete="off" spellcheck="false" data-admin-delete-confirm></label>
						<div class="elm-admin__edit-feedback" data-admin-delete-feedback hidden></div>
						<div class="elm-admin__edit-actions"><button type="button" class="button" data-admin-delete-close><?php esc_html_e( 'Anulo', 'employee-leave-manager' ); ?></button><button type="button" class="button button-primary button-link-delete" data-admin-delete-submit disabled><?php esc_html_e( 'Fshi përgjithmonë', 'employee-leave-manager' ); ?></button></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_adjustments(): void {
		if ( ! current_user_can( 'elm_adjust_balances' ) ) {
			wp_die( esc_html__( 'Nuk keni leje të shtoni ditë pushimi.', 'employee-leave-manager' ) );
		}
		?>
		<div class="wrap elm-admin" data-elm-admin="adjustments">
			<div class="elm-admin__hero"><div><h1><?php esc_html_e( 'Ditët e pushimit', 'employee-leave-manager' ); ?></h1><p><?php esc_html_e( 'Menaxhoni të drejtat shtesë të pushimit që kërkojnë verifikim administrativ dhe regjistroni kompensimet/ditët shtesë me arsyetim.', 'employee-leave-manager' ); ?></p></div></div>
			<div class="elm-admin__notice" data-admin-notice hidden></div>
			<div class="elm-admin__card elm-admin__entitlements">
				<h2><?php esc_html_e( '+1 ditë për përvojë pune', 'employee-leave-manager' ); ?></h2>
				<p><?php esc_html_e( 'Sipas nenit 9 paragrafi 3 të Rregullores (QRK) Nr. 04/2024, për çdo pesë (5) vjet të përvojës së punës shtohet një (1) ditë pune në pushimin vjetor. Verifikoni përvojën para miratimit.', 'employee-leave-manager' ); ?></p>
				<form data-entitlement-admin-form class="elm-admin__form elm-admin__entitlement-form">
					<label><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?><select name="user_id" data-entitlement-user-select required><option value=""><?php esc_html_e( 'Zgjidhni punonjësin', 'employee-leave-manager' ); ?></option></select></label>
					<input type="hidden" name="requested_entitlement" value="0">
					<label><?php esc_html_e( 'Viti i hyrjes në fuqi', 'employee-leave-manager' ); ?><input type="number" name="effective_year" min="<?php echo esc_attr( current_datetime()->format( 'Y' ) ); ?>" max="2100" value="<?php echo esc_attr( current_datetime()->format( 'Y' ) ); ?>" required></label>
					<label><?php esc_html_e( 'Arsyetimi', 'employee-leave-manager' ); ?><textarea name="reason" rows="3" required maxlength="2000" placeholder="<?php esc_attr_e( 'Shembull: Ka plotësuar 10 vite të përvojës së punës.', 'employee-leave-manager' ); ?>"></textarea></label>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Paraqit kërkesën për miratim', 'employee-leave-manager' ); ?></button>
				</form>
				<div class="elm-admin__table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Ndryshimi', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Në fuqi nga', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Arsyetimi / paraqitur nga', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Statusi', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Veprimet', 'employee-leave-manager' ); ?></th></tr></thead><tbody data-entitlement-admin-body><tr class="elm-table-state elm-table-state--loading"><td class="elm-table-state__cell" colspan="6"><?php esc_html_e( 'Po ngarkohet...', 'employee-leave-manager' ); ?></td></tr></tbody></table></div>
			</div>
			<div class="elm-admin__grid">
				<div class="elm-admin__card"><h2><?php esc_html_e( 'Regjistro ditët shtesë', 'employee-leave-manager' ); ?></h2><form data-adjustment-form class="elm-admin__form">
					<label><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?><select name="user_id" data-user-select required><option value=""><?php esc_html_e( 'Zgjidhni punonjësin', 'employee-leave-manager' ); ?></option></select></label>
					<label><?php esc_html_e( 'Viti i pushimit', 'employee-leave-manager' ); ?><input type="number" name="year" min="2000" max="2100" value="<?php echo esc_attr( current_datetime()->format( 'Y' ) ); ?>" required></label>
					<label><?php esc_html_e( 'Ditë shtesë', 'employee-leave-manager' ); ?><input type="number" name="amount" min="1" step="1" required placeholder="1"></label>
					<label><?php esc_html_e( 'Arsyetimi', 'employee-leave-manager' ); ?><textarea name="note" rows="5" required maxlength="3000" placeholder="<?php esc_attr_e( 'Shembull: Kompensim për punën jashtë orarit gjatë projektit të fundjavës.', 'employee-leave-manager' ); ?>"></textarea></label>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Shto ditët për vitin', 'employee-leave-manager' ); ?></button>
				</form></div>
				<div class="elm-admin__card"><h2><?php esc_html_e( 'Regjistri i ditëve shtesë', 'employee-leave-manager' ); ?></h2><div class="elm-admin__table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Viti', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Ditë', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Arsyetimi', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Krijuar më', 'employee-leave-manager' ); ?></th></tr></thead><tbody data-adjustments-body><tr class="elm-table-state elm-table-state--loading"><td class="elm-table-state__cell" colspan="5"><?php esc_html_e( 'Po ngarkohet...', 'employee-leave-manager' ); ?></td></tr></tbody></table></div></div>
			</div>
		</div>
		<?php
	}

	public function render_reports(): void {
		if ( ! current_user_can( 'elm_export_leave_reports' ) ) {
			wp_die( esc_html__( 'Nuk keni leje të shkarkoni raporte.', 'employee-leave-manager' ) );
		}
		$schema_ready = ELM_DB::schema_ready();
		if ( current_user_can( 'manage_options' ) ) {
			$users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC', 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
		} else {
			$visible_ids = ELM_Chief_Access::visible_employee_ids( get_current_user_id() );
			$users = $visible_ids ? get_users( array( 'include' => $visible_ids, 'orderby' => 'display_name', 'order' => 'ASC', 'fields' => array( 'ID', 'display_name', 'user_email' ) ) ) : array();
		}
		$report_users = array_filter(
			$users,
			static fn( $user ) => user_can( $user->ID, 'elm_submit_leave' ) || user_can( $user->ID, 'elm_view_own_leave' ) || user_can( $user->ID, 'elm_manage_leave' )
		);
		?>
		<div class="wrap elm-admin" data-elm-admin="reports">
			<div class="elm-admin__hero"><div><p class="elm-admin__eyebrow"><?php esc_html_e( 'Raportet', 'employee-leave-manager' ); ?></p><h1><?php esc_html_e( 'Raportet e pushimeve', 'employee-leave-manager' ); ?></h1><p><?php esc_html_e( 'Krijoni formularin zyrtar A4 për kërkesat e punonjësit dhe vitin e zgjedhur.', 'employee-leave-manager' ); ?></p></div><div class="elm-admin__hero-icon"><span class="dashicons dashicons-media-document"></span></div></div>
			<?php if ( ! $schema_ready ) : ?>
				<div class="elm-admin__notice elm-admin__notice--error"><?php esc_html_e( 'Baza e të dhënave të raporteve nuk është e plotë. Çaktivizoni dhe riaktivizoni shtojcën një herë, pastaj rifreskoni këtë faqe.', 'employee-leave-manager' ); ?></div>
			<?php endif; ?>
			<div class="elm-admin__notice" data-admin-notice hidden role="status" aria-live="polite"></div>
			<div class="elm-admin__grid elm-admin__grid--reports">
				<div class="elm-admin__card elm-admin__report-card">
					<div class="elm-admin__section-heading"><span class="dashicons dashicons-pdf"></span><div><h2><?php esc_html_e( 'Formularët e kërkesave të punonjësit sipas vitit', 'employee-leave-manager' ); ?></h2><p><?php esc_html_e( 'Krijon një formular PDF në format A4 për secilën kërkesë, të renditur sipas datës së fillimit.', 'employee-leave-manager' ); ?></p></div></div>
					<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank" class="elm-admin__form elm-admin__report-form">
						<input type="hidden" name="action" value="elm_export_employee_pdf">
						<?php wp_nonce_field( 'elm_export_employee_pdf' ); ?>
						<label><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?><select name="user_id" required><option value=""><?php esc_html_e( 'Zgjidhni punonjësin', 'employee-leave-manager' ); ?></option><?php foreach ( $report_users as $user ) : ?><option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name . ' - ' . $user->user_email ); ?></option><?php endforeach; ?></select></label>
						<label><?php esc_html_e( 'Viti', 'employee-leave-manager' ); ?><input type="number" name="year" value="<?php echo esc_attr( current_datetime()->format( 'Y' ) ); ?>" min="2000" max="2100" required></label>
						<button type="submit" class="button button-primary button-hero" <?php disabled( ! $schema_ready ); ?>><?php esc_html_e( 'Krijo raportin PDF', 'employee-leave-manager' ); ?></button>
					</form>
				</div>
				<div class="elm-admin__card elm-admin__dependency"><div class="elm-admin__section-heading"><span class="dashicons dashicons-yes-alt"></span><div><h2><?php esc_html_e( 'Statusi i modulit PDF', 'employee-leave-manager' ); ?></h2><p><?php echo class_exists( '\Dompdf\Dompdf' ) ? esc_html__( 'Dompdf është aktiv. Krijimi i PDF-ve të formatuara me Unicode është i aktivizuar.', 'employee-leave-manager' ) : esc_html__( 'Moduli i integruar për PDF është aktiv, prandaj raportet funksionojnë pa varësi nga Composer-i.', 'employee-leave-manager' ); ?></p></div></div><ul class="elm-admin__feature-list"><li><?php esc_html_e( 'Krijim i drejtpërdrejtë në server', 'employee-leave-manager' ); ?></li><li><?php esc_html_e( 'Pa varësi nga JavaScript-i', 'employee-leave-manager' ); ?></li><li><?php esc_html_e( 'Shkarkimi regjistrohet në regjistrin e auditimit', 'employee-leave-manager' ); ?></li></ul></div>
			</div>
			<div class="elm-admin__card elm-admin__employees-reports" data-report-employees>
				<div class="elm-admin__section-heading"><span class="dashicons dashicons-groups"></span><div><h2><?php esc_html_e( 'Punonjësit dhe raportet', 'employee-leave-manager' ); ?></h2><p><?php esc_html_e( 'Përditësoni pozitën dhe sektorin/njësinë, shihni gjendjen vjetore dhe krijoni raportin A4.', 'employee-leave-manager' ); ?></p></div></div>
				<div class="elm-admin__employees-controls"><label><?php esc_html_e( 'Viti i gjendjes dhe raportit', 'employee-leave-manager' ); ?><input type="number" min="2000" max="2100" value="<?php echo esc_attr( current_datetime()->format( 'Y' ) ); ?>" data-report-employees-year></label><button type="button" class="button" data-report-employees-refresh><?php esc_html_e( 'Rifresko', 'employee-leave-manager' ); ?></button></div>
				<div class="elm-admin__table-wrap"><table class="widefat striped elm-admin__employees-table"><thead><tr><th><?php esc_html_e( 'Punonjësi', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Pozita', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Sektori/Njësia', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Gjendja vjetore', 'employee-leave-manager' ); ?></th><th><?php esc_html_e( 'Veprimet', 'employee-leave-manager' ); ?></th></tr></thead><tbody data-report-employees-body><tr class="elm-table-state elm-table-state--loading"><td class="elm-table-state__cell" colspan="5"><?php esc_html_e( 'Po ngarkohet...', 'employee-leave-manager' ); ?></td></tr></tbody></table></div>
			</div>
		</div>
		<?php
	}

	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nuk keni leje t\'i ndryshoni cilësimet e pushimeve.', 'employee-leave-manager' ) );
		}
		$settings = ELM_Policy::settings();
		$can_manage_access_mode = current_user_can( 'manage_options' );
		$portal_pages = $can_manage_access_mode ? get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC', 'post_status' => 'publish' ) ) : array();
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['elm_settings_nonce'] ) ) {
			check_admin_referer( 'elm_save_settings', 'elm_settings_nonce' );
			$entitlement = 20.0;
			$p1 = 0.0;
			$concurrency = max( 1, min( 20, absint( $_POST['concurrency_limit'] ?? 2 ) ) );
			$weekdays = array_map( 'absint', (array) ( $_POST['working_weekdays'] ?? array() ) );
			$weekdays = array_values( array_intersect( array( 1, 2, 3, 4, 5, 6, 7 ), $weekdays ) );
			$movable_holidays = ELM_Policy::merge_movable_holiday_dates(
				(array) ( $settings['movable_holidays'] ?? array() ),
				array(
					'bajrami_i_madh'  => sanitize_text_field( wp_unslash( $_POST['movable_bajrami_i_madh'] ?? '' ) ),
					'bajrami_i_vogel' => sanitize_text_field( wp_unslash( $_POST['movable_bajrami_i_vogel'] ?? '' ) ),
				)
			);
			$settings_error = is_wp_error( $movable_holidays ) ? $movable_holidays : null;
			$portal_page_id = absint( $settings['portal_page_id'] ?? 0 );
			$frontend_only_enabled = ! empty( $settings['frontend_only_enabled'] );
			$show_plus_one_when_empty = ! empty( $_POST['show_plus_one_when_empty'] );
			$email_notifications_enabled = ! empty( $_POST['email_notifications_enabled'] );
			$notification_cc_admin = ! empty( $_POST['notification_cc_admin'] );
			if ( $can_manage_access_mode ) {
				$portal_page_id = absint( $_POST['portal_page_id'] ?? 0 );
				$frontend_only_enabled = ! empty( $_POST['frontend_only_enabled'] );
				$portal_page = $portal_page_id ? get_post( $portal_page_id ) : null;
				$has_portal_shortcode = $portal_page instanceof WP_Post
					&& 'publish' === $portal_page->post_status
					&& ( has_shortcode( (string) $portal_page->post_content, 'elm_leave_portal' ) || has_shortcode( (string) $portal_page->post_content, 'employee_leave_manager' ) );
				if ( $frontend_only_enabled && ! $has_portal_shortcode ) {
					$settings_error = new WP_Error( 'elm_invalid_portal_page', __( 'Zgjidhni një faqe të publikuar që përmban kodin e shkurtër të portalit para aktivizimit të qasjes vetëm përmes portalit.', 'employee-leave-manager' ) );
				}
				if ( ! $has_portal_shortcode ) {
					$portal_page_id = 0;
					$frontend_only_enabled = false;
				}
			}
			if ( is_wp_error( $settings_error ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $settings_error->get_error_message() ) . '</p></div>';
			} else {
				$new_settings = array(
					'annual_entitlement' => $entitlement,
					'period_one_limit'   => min( $entitlement, $p1 ),
					'concurrency_limit'  => $concurrency,
					'working_weekdays'   => $weekdays ?: array( 1, 2, 3, 4, 5 ),
					'holidays'           => array(),
					'holiday_names'      => array(),
					'movable_holidays'   => is_array( $movable_holidays ) ? $movable_holidays : (array) ( $settings['movable_holidays'] ?? array() ),
					'max_upload_mb'      => max( 1, min( 50, absint( $_POST['max_upload_mb'] ?? 10 ) ) ),
					'frontend_only_enabled' => $frontend_only_enabled,
					'portal_page_id'        => $portal_page_id,
					'show_plus_one_when_empty' => $show_plus_one_when_empty,
					'email_notifications_enabled' => $email_notifications_enabled,
					'notification_cc_admin'       => $notification_cc_admin,
				);
				update_option( 'elm_settings', $new_settings, false );
				ELM_DB::begin();
				ELM_Audit::append( 'policy_settings', 0, 'updated', get_current_user_id(), array( 'before' => $settings, 'after' => $new_settings ) );
				ELM_DB::commit();
				$settings = ELM_Policy::settings();
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cilësimet e pushimeve u ruajtën.', 'employee-leave-manager' ) . '</p></div>';
			}
		}
		?>
		<div class="wrap elm-admin"><div class="elm-admin__hero"><div><h1><?php esc_html_e( 'Cilësimet', 'employee-leave-manager' ); ?></h1><p><?php esc_html_e( 'Menaxhoni parametrat operacionalë, kalendarin e punës, dokumentet dhe qasjen në portal.', 'employee-leave-manager' ); ?></p></div></div>
		<div class="elm-admin__card elm-admin__settings-hub"><form method="post" enctype="multipart/form-data" class="elm-admin__form elm-admin__settings"><?php wp_nonce_field( 'elm_save_settings', 'elm_settings_nonce' ); ?>
		<section class="elm-admin__settings-group"><div class="elm-admin__settings-head"><h2><?php esc_html_e( 'Paraqitja e kërkesave +1 ditë', 'employee-leave-manager' ); ?></h2><p><?php esc_html_e( 'Seksioni shfaqet automatikisht kur ka kërkesa të reja në pritje. Opsioni më poshtë e mban të dukshëm edhe kur lista është bosh.', 'employee-leave-manager' ); ?></p></div><div class="elm-admin__settings-grid">
		<label><span><?php esc_html_e( 'Seksioni +1 ditë', 'employee-leave-manager' ); ?></span><span class="elm-admin__inline-check"><input type="checkbox" name="show_plus_one_when_empty" value="1" <?php checked( ! empty( $settings['show_plus_one_when_empty'] ) ); ?>><?php esc_html_e( 'Shfaqe edhe kur nuk ka kërkesa në pritje', 'employee-leave-manager' ); ?></span></label>
		</div></section>
		<section class="elm-admin__settings-group"><div class="elm-admin__settings-head"><h2><?php esc_html_e( 'Njoftimet me email', 'employee-leave-manager' ); ?></h2><p><?php esc_html_e( 'Njoftoni automatikisht udhëheqësit dhe punonjësit me email kur paraqitet, miratohet, refuzohet ose anulohet një kërkesë.', 'employee-leave-manager' ); ?></p></div><div class="elm-admin__settings-grid">
		<label><span><?php esc_html_e( 'Njoftimet', 'employee-leave-manager' ); ?></span><span class="elm-admin__inline-check"><input type="checkbox" name="email_notifications_enabled" value="1" <?php checked( ! empty( $settings['email_notifications_enabled'] ) ); ?>><?php esc_html_e( 'Dërgo njoftime me email për kërkesat dhe vendimet', 'employee-leave-manager' ); ?></span></label>
		<label><span><?php esc_html_e( 'Kopja e administratorit', 'employee-leave-manager' ); ?></span><span class="elm-admin__inline-check"><input type="checkbox" name="notification_cc_admin" value="1" <?php checked( ! empty( $settings['notification_cc_admin'] ) ); ?>><?php echo esc_html( sprintf( __( 'Përfshi edhe email-in e administratorit (%s) te njoftimet për kërkesa të reja', 'employee-leave-manager' ), get_option( 'admin_email' ) ) ); ?></span></label>
		</div></section>
		<section class="elm-admin__settings-group"><div class="elm-admin__settings-head"><h2><?php esc_html_e( 'Organizimi i pushimeve', 'employee-leave-manager' ); ?></h2><p><?php esc_html_e( 'Të drejtat dhe afatet ligjore shfaqen te skeda “Rregullat e pushimit”. Këtu caktohen vetëm parametrat operacionalë të institucionit.', 'employee-leave-manager' ); ?></p></div><div class="elm-admin__settings-grid">
		<label><span class="elm-setting-label"><?php esc_html_e( 'Kapaciteti ditor i pushimit vjetor', 'employee-leave-manager' ); ?><span class="elm-help-tip" tabindex="0" aria-label="<?php esc_attr_e( 'Numri maksimal i punonjësve të cilëve mund t\'u miratohet pushimi vjetor për të njëjtën ditë pune. Ky është parametër organizativ dhe nuk zbatohet për pushimin mjekësor.', 'employee-leave-manager' ); ?>">?<span class="elm-help-tip__text" aria-hidden="true"><?php esc_html_e( 'Parametër organizativ vetëm për pushimin vjetor; nuk bllokon pushimin mjekësor.', 'employee-leave-manager' ); ?></span></span></span><input type="number" min="1" max="20" name="concurrency_limit" value="<?php echo esc_attr( $settings['concurrency_limit'] ); ?>"></label>
		</div></section>
		<section class="elm-admin__settings-group"><div class="elm-admin__settings-head"><h2><?php esc_html_e( 'Kalendari i punës', 'employee-leave-manager' ); ?></h2></div>
		<fieldset><legend><span class="elm-setting-label"><?php esc_html_e( 'Ditët e punës', 'employee-leave-manager' ); ?><span class="elm-help-tip" tabindex="0" aria-label="<?php esc_attr_e( 'Ditët e zgjedhura të javës llogariten si ditë pune dhe përfshihen në ditët e shfrytëzuara të pushimit vjetor.', 'employee-leave-manager' ); ?>">?<span class="elm-help-tip__text" aria-hidden="true"><?php esc_html_e( 'Ditët e zgjedhura të javës përfshihen në ditët e shfrytëzuara të pushimit vjetor.', 'employee-leave-manager' ); ?></span></span></span></legend><div class="elm-admin__checks"><?php foreach ( array( 1 => 'E hënë', 2 => 'E martë', 3 => 'E mërkurë', 4 => 'E enjte', 5 => 'E premte', 6 => 'E shtunë', 7 => 'E diel' ) as $number => $label ) : ?><label><input type="checkbox" name="working_weekdays[]" value="<?php echo esc_attr( $number ); ?>" <?php checked( in_array( $number, (array) $settings['working_weekdays'], true ) ); ?>><?php echo esc_html( $label ); ?></label><?php endforeach; ?></div></fieldset>
		<?php $fixed_holidays = ELM_Policy::fixed_holiday_definitions(); $movable_edit_dates = ELM_Policy::movable_holiday_edit_dates( $settings ); ?>
		<div class="elm-admin__holiday-policy">
			<div class="elm-admin__holiday-fixed">
				<div class="elm-admin__settings-head elm-admin__holiday-head"><div><h3><?php esc_html_e( 'Festat e përsëritshme', 'employee-leave-manager' ); ?></h3><p><?php esc_html_e( 'Këto data zbatohen automatikisht çdo vit dhe nuk kërkojnë shtim manual.', 'employee-leave-manager' ); ?></p></div><span class="elm-admin__holiday-badge"><?php esc_html_e( 'Automatike', 'employee-leave-manager' ); ?></span></div>
				<div class="elm-admin__fixed-holiday-grid">
					<?php foreach ( $fixed_holidays as $month_day => $holiday_name ) : ?>
						<div class="elm-admin__fixed-holiday"><time><?php echo esc_html( str_replace( '-', '.', $month_day ) ); ?></time><span><?php echo esc_html( $holiday_name ); ?></span></div>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="elm-admin__holiday-movable">
				<div class="elm-admin__settings-head elm-admin__holiday-head"><div><h3><?php esc_html_e( 'Festat me datë të ndryshueshme', 'employee-leave-manager' ); ?></h3><p><?php esc_html_e( 'Klikoni fushën e datës dhe zgjidheni ditën nga kalendari. Ruajtja e një viti të ri nuk i fshin datat e viteve të mëparshme.', 'employee-leave-manager' ); ?></p></div></div>
				<div class="elm-admin__movable-holiday-grid">
					<label><span><?php esc_html_e( 'Bajrami i Madh, dita e parë', 'employee-leave-manager' ); ?></span><input type="date" name="movable_bajrami_i_madh" min="2000-01-01" max="2100-12-31" value="<?php echo esc_attr( $movable_edit_dates['bajrami_i_madh'] ?? '' ); ?>"></label>
					<label><span><?php esc_html_e( 'Bajrami i Vogël, dita e parë', 'employee-leave-manager' ); ?></span><input type="date" name="movable_bajrami_i_vogel" min="2000-01-01" max="2100-12-31" value="<?php echo esc_attr( $movable_edit_dates['bajrami_i_vogel'] ?? '' ); ?>"></label>
				</div>
			</div>
		</div>
		</section>
		<section class="elm-admin__settings-group"><div class="elm-admin__settings-head"><h2><?php esc_html_e( 'Dokumentet', 'employee-leave-manager' ); ?></h2></div><div class="elm-admin__settings-grid">
		<label><span class="elm-setting-label"><?php esc_html_e( 'Madhësia maksimale e dokumentit', 'employee-leave-manager' ); ?><span class="elm-help-tip" tabindex="0" aria-label="<?php esc_attr_e( 'Madhësia maksimale e dokumentit mjekësor, në megabajt.', 'employee-leave-manager' ); ?>">?<span class="elm-help-tip__text" aria-hidden="true"><?php esc_html_e( 'Madhësia maksimale e dokumentit mjekësor në MB.', 'employee-leave-manager' ); ?></span></span></span><input type="number" min="1" max="50" name="max_upload_mb" value="<?php echo esc_attr( $settings['max_upload_mb'] ); ?>"></label>
		</div></section>
		<?php if ( $can_manage_access_mode ) : ?>
		<section class="elm-admin__settings-group"><div class="elm-admin__settings-head"><h2><?php esc_html_e( 'Qasja vetëm përmes portalit', 'employee-leave-manager' ); ?></h2><p><?php esc_html_e( 'Punonjësit dhe udhëheqësit e pushimeve mund të ridrejtohen në portal, ndërsa administratorët ruajnë qasjen e plotë në panelin administrativ të WordPress-it.', 'employee-leave-manager' ); ?></p></div><div class="elm-admin__settings-grid">
		<label><?php esc_html_e( 'Faqja e portalit', 'employee-leave-manager' ); ?><select name="portal_page_id"><option value="0"><?php esc_html_e( 'Zgjidhni një faqe të publikuar të portalit', 'employee-leave-manager' ); ?></option><?php foreach ( $portal_pages as $portal_page ) : ?><?php $contains_portal = has_shortcode( (string) $portal_page->post_content, 'elm_leave_portal' ) || has_shortcode( (string) $portal_page->post_content, 'employee_leave_manager' ); ?><option value="<?php echo esc_attr( $portal_page->ID ); ?>" <?php selected( absint( $settings['portal_page_id'] ?? 0 ), (int) $portal_page->ID ); ?> <?php disabled( ! $contains_portal ); ?>><?php echo esc_html( $portal_page->post_title . ( $contains_portal ? '' : ' - ' . __( 'mungon kodi i shkurtër', 'employee-leave-manager' ) ) ); ?></option><?php endforeach; ?></select></label>
		<label><span><?php esc_html_e( 'Qasja në portal', 'employee-leave-manager' ); ?></span><span class="elm-admin__inline-check"><input type="checkbox" name="frontend_only_enabled" value="1" <?php checked( ! empty( $settings['frontend_only_enabled'] ) ); ?>><?php esc_html_e( 'Aktivizo qasjen vetëm përmes portalit për llogaritë e punonjësve dhe mbikëqyrësve', 'employee-leave-manager' ); ?></span></label>
		</div></section>
		<?php endif; ?>
		<div class="elm-admin__settings-save"><button type="submit" class="button button-primary"><?php esc_html_e( 'Ruaj cilësimet', 'employee-leave-manager' ); ?></button></div></form></div></div>
		<?php
	}


	public function render_chief_access(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vetëm administratori mund ta menaxhojë qasjen e udhëheqësit në portal.', 'employee-leave-manager' ) );
		}

		$chiefs = ELM_Chief_Access::chiefs();
		$selected_chief_id = absint( $_REQUEST['chief_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $selected_chief_id && $chiefs ) {
			$selected_chief_id = (int) $chiefs[0]->ID;
		}
		$notice = '';
		$notice_type = 'success';

		if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			check_admin_referer( 'elm_save_chief_access' );
			$selected_chief_id = absint( $_POST['chief_id'] ?? 0 );
			$action = sanitize_key( wp_unslash( (string) ( $_POST['elm_chief_access_action'] ?? 'save' ) ) );
			$is_clear = in_array( $action, array( 'clear', 'reset' ), true );
			$result = $is_clear
				? ELM_Chief_Access::clear_assignments( $selected_chief_id, get_current_user_id() )
				: ELM_Chief_Access::save_selected( $selected_chief_id, (array) ( $_POST['employee_ids'] ?? array() ), get_current_user_id() );
			if ( is_wp_error( $result ) ) {
				$notice = $result->get_error_message();
				$notice_type = 'error';
			} else {
				$notice = $is_clear
					? __( 'Caktimet u pastruan. Udhëheqësi nuk mund të shqyrtojë kërkesa të punonjësve derisa t\'i caktohen punonjësit përkatës.', 'employee-leave-manager' )
					: __( 'Punonjësit e zgjedhur iu caktuan këtij udhëheqësi.', 'employee-leave-manager' );
			}
		}

		$selected_chief = $selected_chief_id ? get_userdata( $selected_chief_id ) : null;
		$employees = ELM_Chief_Access::eligible_users();
		$selected_ids = $selected_chief_id ? ELM_Chief_Access::configured_employee_ids( $selected_chief_id ) : array();
		$selected_ids = is_array( $selected_ids ) ? $selected_ids : array();
		?>
		<div class="wrap elm-admin">
			<div class="elm-admin__hero"><div><p class="elm-admin__eyebrow"><?php esc_html_e( 'Administrimi i qasjes', 'employee-leave-manager' ); ?></p><h1><?php esc_html_e( 'Caktimi i punonjësve për mbikëqyrësin', 'employee-leave-manager' ); ?></h1><p><?php esc_html_e( 'Zgjidhni saktësisht cilët punonjës mund t\'i shohë dhe menaxhojë secili mbikëqyrës në portal. Për vendimmarrjen e pushimit vjetor, caktimi duhet të pasqyrojë mbikëqyrësin e drejtpërdrejtë të punonjësit. Administratorët e WordPress-it ruajnë qasjen teknike të administrimit.', 'employee-leave-manager' ); ?></p></div><div class="elm-admin__hero-icon"><span class="dashicons dashicons-groups"></span></div></div>
			<?php if ( $notice ) : ?><div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
			<div class="elm-admin__card">
				<form method="get" class="elm-admin__filters">
					<input type="hidden" name="page" value="elm-chief-access">
					<label><?php esc_html_e( 'Mbikëqyrësi i drejtpërdrejtë', 'employee-leave-manager' ); ?>
						<select name="chief_id" onchange="this.form.submit()">
							<?php if ( ! $chiefs ) : ?><option value="0"><?php esc_html_e( 'Nuk u gjet asnjë llogari me rolin e udhëheqësit', 'employee-leave-manager' ); ?></option><?php endif; ?>
							<?php foreach ( $chiefs as $chief ) : ?><option value="<?php echo esc_attr( $chief->ID ); ?>" <?php selected( $selected_chief_id, (int) $chief->ID ); ?>><?php echo esc_html( $chief->display_name . ' - ' . $chief->user_email ); ?></option><?php endforeach; ?>
						</select>
					</label>
					<noscript><button type="submit" class="button"><?php esc_html_e( 'Shfaq udhëheqësin', 'employee-leave-manager' ); ?></button></noscript>
				</form>
			</div>

			<?php if ( $selected_chief && user_can( $selected_chief_id, 'elm_manage_leave' ) ) : ?>
			<div class="elm-admin__card">
				<div class="elm-admin__section-heading"><span class="dashicons dashicons-visibility"></span><div><h2><?php echo esc_html( $selected_chief->display_name ); ?></h2><p><?php echo $selected_ids ? esc_html__( 'Mbikëqyrësi sheh dhe shqyrton vetëm punonjësit e caktuar më poshtë.', 'employee-leave-manager' ) : esc_html__( 'Nuk ka punonjës të caktuar. Mbikëqyrësi ka qasje vetëm te pushimi i vet derisa administratori t\'i caktojë punonjësit që mbikëqyr drejtpërdrejt.', 'employee-leave-manager' ); ?></p></div></div>
				<form method="post">
					<?php wp_nonce_field( 'elm_save_chief_access' ); ?>
					<input type="hidden" name="chief_id" value="<?php echo esc_attr( $selected_chief_id ); ?>">
					<div class="elm-admin__checks" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;margin:18px 0;max-height:520px;overflow:auto;padding:12px;border:1px solid #dcdcde;border-radius:8px;">
						<?php if ( ! $employees ) : ?><p><?php esc_html_e( 'Nuk u gjet asnjë punonjës i disponueshëm.', 'employee-leave-manager' ); ?></p><?php endif; ?>
						<?php foreach ( $employees as $employee ) : ?>
							<?php if ( (int) $employee->ID === $selected_chief_id ) { continue; } ?>
							<label style="display:flex;align-items:flex-start;gap:8px;padding:8px;background:#fff;border:1px solid #e2e4e7;border-radius:6px;">
								<input type="checkbox" name="employee_ids[]" value="<?php echo esc_attr( $employee->ID ); ?>" <?php checked( in_array( (int) $employee->ID, $selected_ids, true ) ); ?>>
								<span><strong><?php echo esc_html( $employee->display_name ); ?></strong><br><small><?php echo esc_html( $employee->user_email ); ?> - #<?php echo esc_html( $employee->ID ); ?></small></span>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e( 'Pa punonjës të zgjedhur, mbikëqyrësi ka qasje vetëm te Pushimi im.', 'employee-leave-manager' ); ?></p>
					<p><button type="submit" name="elm_chief_access_action" value="save" class="button button-primary"><?php esc_html_e( 'Ruaj punonjësit e zgjedhur', 'employee-leave-manager' ); ?></button> <button type="submit" name="elm_chief_access_action" value="clear" class="button" onclick="return confirm('<?php echo esc_js( __( 'Të hiqen të gjitha caktimet e këtij udhëheqësi?', 'employee-leave-manager' ) ); ?>');"><?php esc_html_e( 'Pastro caktimet', 'employee-leave-manager' ); ?></button></p>
				</form>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function export_request_pdf(): void {
		$is_frontend_export = ! empty( $_GET['elm_frontend'] );
		check_admin_referer( $is_frontend_export ? 'elm_export_request_pdf_frontend' : 'elm_export_request_pdf' );
		$request_id = absint( $_GET['request_id'] ?? 0 );
		if ( ! ELM_DB::schema_ready() ) {
			wp_die( esc_html__( 'Baza e të dhënave të pushimeve nuk është e plotë. Riaktivizoni shtojcën dhe provoni përsëri.', 'employee-leave-manager' ), '', array( 'response' => 503 ) );
		}
		if ( ! $request_id ) {
			wp_die( esc_html__( 'Kërkesa për pushim është e pavlefshme.', 'employee-leave-manager' ), '', array( 'response' => 400 ) );
		}
		$request = ( new ELM_Leave_Service() )->get_request( $request_id );
		if ( is_wp_error( $request ) ) {
			wp_die( esc_html( $request->get_error_message() ), '', array( 'response' => 404 ) );
		}
		$owns_request = get_current_user_id() === (int) $request['employee_id'];
		$can_manage_request = current_user_can( 'elm_manage_leave' ) && ELM_Chief_Access::can_manage_employee( get_current_user_id(), (int) $request['employee_id'] );
		if ( ! $owns_request && ! current_user_can( 'manage_options' ) && ! $can_manage_request ) {
			wp_die( esc_html__( 'Ky punonjës nuk është i caktuar nën mbikëqyrjen tuaj të drejtpërdrejtë.', 'employee-leave-manager' ), '', array( 'response' => 403 ) );
		}
		$this->record_export( 'leave_request', $request_id, array( 'self_service' => $owns_request && ! current_user_can( 'elm_export_leave_reports' ) ) );
		ELM_PDF::stream_request( $request_id );
	}

	public function export_employee_pdf(): void {
		if ( ! current_user_can( 'elm_export_leave_reports' ) ) {
			wp_die( esc_html__( 'Nuk keni leje për këtë veprim.', 'employee-leave-manager' ), '', array( 'response' => 403 ) );
		}
		$is_frontend_export = ! empty( $_GET['elm_frontend'] );
		check_admin_referer( $is_frontend_export ? 'elm_export_employee_pdf_frontend' : 'elm_export_employee_pdf' );
		$user_id = absint( $_GET['user_id'] ?? 0 );
		$year = absint( $_GET['year'] ?? 0 );
		if ( ! get_userdata( $user_id ) || $year < 2000 || $year > 2100 ) {
			wp_die( esc_html__( 'Zgjidhni një punonjës dhe një vit të vlefshëm të raportit.', 'employee-leave-manager' ), '', array( 'response' => 400 ) );
		}
		$owns_employee = get_current_user_id() === $user_id;
		if ( ! current_user_can( 'manage_options' ) && ! $owns_employee && ! ELM_Chief_Access::can_manage_employee( get_current_user_id(), $user_id ) ) {
			wp_die( esc_html__( 'Ky punonjës nuk është i caktuar nën mbikëqyrjen tuaj të drejtpërdrejtë.', 'employee-leave-manager' ), '', array( 'response' => 403 ) );
		}
		if ( ! ELM_DB::schema_ready() ) {
			wp_die( esc_html__( 'Baza e të dhënave të pushimeve nuk është e plotë. Riaktivizoni shtojcën dhe provoni përsëri.', 'employee-leave-manager' ), '', array( 'response' => 503 ) );
		}
		$this->record_export( 'employee_report', $user_id, array( 'year' => $year ) );
		ELM_PDF::stream_employee_year( $user_id, $year );
	}

	public function export_requests_csv(): void {
		if ( ! current_user_can( 'elm_export_leave_reports' ) && ! current_user_can( 'elm_manage_leave' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nuk keni leje të shkarkoni raporte.', 'employee-leave-manager' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'elm_export_requests_csv' );
		if ( ! ELM_DB::schema_ready() ) {
			wp_die( esc_html__( 'Baza e të dhënave të pushimeve nuk është e plotë. Riaktivizoni shtojcën dhe provoni përsëri.', 'employee-leave-manager' ), '', array( 'response' => 503 ) );
		}

		$args = array(
			'status' => sanitize_key( wp_unslash( (string) ( $_GET['status'] ?? '' ) ) ),
			'year'   => absint( $_GET['year'] ?? 0 ),
		);
		// unrestricted=false: an administrator sees every request, a Chief only the
		// requests of employees explicitly assigned to them, matching the same
		// visibility rules as the requests table rendered on this screen.
		$rows = ( new ELM_Leave_Service() )->list_requests( $args, get_current_user_id() );
		$this->record_export( 'requests_csv', 0, array( 'count' => count( $rows ), 'status' => $args['status'], 'year' => $args['year'] ) );

		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="kerkesat-pushim-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM: Excel needs it to open UTF-8/Albanian diacritics correctly.
		fputcsv(
			$out,
			array(
				__( 'ID', 'employee-leave-manager' ),
				__( 'Punonjësi', 'employee-leave-manager' ),
				__( 'Pozita', 'employee-leave-manager' ),
				__( 'Sektori/Njësia', 'employee-leave-manager' ),
				__( 'Lloji', 'employee-leave-manager' ),
				__( 'Fillimi', 'employee-leave-manager' ),
				__( 'Përfundimi', 'employee-leave-manager' ),
				__( 'Ditë', 'employee-leave-manager' ),
				__( 'Statusi', 'employee-leave-manager' ),
				__( 'Arsyetimi', 'employee-leave-manager' ),
				__( 'Paraqitur më', 'employee-leave-manager' ),
				__( 'Vendosur më', 'employee-leave-manager' ),
				__( 'Shënimi i vendimit', 'employee-leave-manager' ),
			)
		);
		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array(
					(int) $row['id'],
					self::csv_safe( (string) $row['employee_name'] ),
					self::csv_safe( (string) ( $row['employee_position'] ?? '' ) ),
					self::csv_safe( (string) ( $row['employee_sector'] ?? '' ) ),
					'medical' === $row['leave_type'] ? __( 'Pushim mjekësor', 'employee-leave-manager' ) : __( 'Pushim vjetor', 'employee-leave-manager' ),
					(string) $row['start_date'],
					(string) $row['end_date'],
					(int) $row['requested_units'],
					(string) $row['status'],
					self::csv_safe( (string) $row['reason'] ),
					(string) $row['submitted_at'],
					(string) ( $row['decided_at'] ?? '' ),
					self::csv_safe( (string) ( $row['decision_note'] ?? '' ) ),
				)
			);
		}
		fclose( $out );
		exit;
	}

	/**
	 * Prefixes a leading apostrophe on values starting with a formula trigger
	 * character so spreadsheet apps (Excel, LibreOffice, Sheets) never
	 * evaluate free-text employee/HR input (reason, decision note, position)
	 * as a formula when the CSV export is opened.
	 */
	private static function csv_safe( string $value ): string {
		return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
	}

	private function record_export( string $entity_type, int $entity_id, array $payload ): void {
		ELM_DB::begin();
		$audit = ELM_Audit::append( $entity_type, $entity_id, 'pdf_exported', get_current_user_id(), $payload );
		if ( is_wp_error( $audit ) ) {
			ELM_DB::rollback();
			ELM_DB::log_failure( 'Recording PDF export', $audit->get_error_message() );
			return;
		}
		ELM_DB::commit();
	}

	public function download_medical(): void {
		if ( ! current_user_can( 'elm_view_medical_documents' ) ) {
			wp_die( esc_html__( 'Nuk keni leje për këtë veprim.', 'employee-leave-manager' ), '', array( 'response' => 403 ) );
		}
		$document_id = absint( $_GET['document_id'] ?? 0 );
		$is_frontend_download = ! empty( $_GET['elm_frontend'] );
		check_admin_referer( $is_frontend_download ? 'elm_download_medical_frontend' : 'elm_download_medical' );
		$document = ELM_Medical_Storage::retrieve( $document_id );
		if ( is_wp_error( $document ) ) {
			wp_die( esc_html( $document->get_error_message() ), '', array( 'response' => 404 ) );
		}
		$owns_document = get_current_user_id() === (int) $document['owner_user_id'];
		if ( ! current_user_can( 'manage_options' ) && ! $owns_document && ! ELM_Chief_Access::can_manage_employee( get_current_user_id(), (int) $document['owner_user_id'] ) ) {
			wp_die( esc_html__( 'Ky punonjës nuk është i caktuar nën mbikëqyrjen tuaj të drejtpërdrejtë.', 'employee-leave-manager' ), '', array( 'response' => 403 ) );
		}
		ELM_DB::begin();
		$audit = ELM_Audit::append( 'medical_document', $document_id, 'downloaded', get_current_user_id(), array( 'owner_user_id' => $document['owner_user_id'] ) );
		if ( is_wp_error( $audit ) ) {
			ELM_DB::rollback();
			ELM_DB::log_failure( 'Recording medical document download', $audit->get_error_message() );
			wp_die( esc_html__( 'Shkarkimi i dokumentit mjekësor dështoi. Provoni përsëri.', 'employee-leave-manager' ), '', array( 'response' => 503 ) );
		}
		ELM_DB::commit();
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: ' . $document['mime_type'] );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $document['name'] ) . '"' );
		header( 'Content-Length: ' . strlen( $document['content'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		echo $document['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
