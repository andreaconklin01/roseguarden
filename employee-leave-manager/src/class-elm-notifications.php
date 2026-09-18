<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional email notifications for leave-request and entitlement-change events.
 *
 * Every hook here fires only after the originating transaction has already
 * committed (see the elm_request_* and elm_entitlement_* do_action() calls in
 * ELM_Leave_Service and ELM_Entitlement_Service), so a mail-delivery failure
 * can never roll back or block the underlying HR record.
 */
final class ELM_Notifications {
	public static function register(): void {
		add_action( 'elm_request_created', array( self::class, 'on_request_created' ), 10, 2 );
		add_action( 'elm_request_decided', array( self::class, 'on_request_decided' ), 10, 2 );
		add_action( 'elm_request_cancelled', array( self::class, 'on_request_cancelled' ), 10, 2 );
		add_action( 'elm_entitlement_created', array( self::class, 'on_entitlement_created' ), 10, 2 );
		add_action( 'elm_entitlement_decided', array( self::class, 'on_entitlement_decided' ), 10, 2 );
	}

	private static function enabled(): bool {
		return ! empty( ELM_Policy::settings()['email_notifications_enabled'] );
	}

	public static function on_request_created( int $request_id, int $employee_id ): void {
		if ( ! self::enabled() ) {
			return;
		}
		$request = ( new ELM_Leave_Service() )->get_request( $request_id );
		if ( is_wp_error( $request ) ) {
			return;
		}
		$recipients = self::chief_recipients( $employee_id );
		if ( ! $recipients ) {
			return;
		}
		$subject = sprintf( __( '[%1$s] Kërkesë e re për pushim nga %2$s', 'employee-leave-manager' ), self::site_name(), (string) $request['employee_name'] );
		$body = self::format_request_summary( $request, __( 'Është paraqitur një kërkesë e re për pushim që kërkon shqyrtimin tuaj.', 'employee-leave-manager' ) );
		self::send( $recipients, $subject, $body );
	}

	public static function on_request_decided( int $request_id, string $decision ): void {
		if ( ! self::enabled() ) {
			return;
		}
		$request = ( new ELM_Leave_Service() )->get_request( $request_id );
		if ( is_wp_error( $request ) ) {
			return;
		}
		if ( (int) ( $request['decided_by'] ?? 0 ) === (int) $request['employee_id'] ) {
			return; // Vetë-vendim; punonjësi e di tashmë rezultatin pa email shtesë.
		}
		$employee = get_userdata( (int) $request['employee_id'] );
		if ( ! $employee || ! is_email( $employee->user_email ) ) {
			return;
		}
		$is_approved = 'approved' === $decision;
		$subject = sprintf(
			$is_approved
				? __( '[%s] Kërkesa juaj për pushim u miratua', 'employee-leave-manager' )
				: __( '[%s] Kërkesa juaj për pushim u refuzua', 'employee-leave-manager' ),
			self::site_name()
		);
		$body = self::format_request_summary(
			$request,
			$is_approved
				? __( 'Kërkesa juaj për pushim është miratuar.', 'employee-leave-manager' )
				: __( 'Kërkesa juaj për pushim është refuzuar.', 'employee-leave-manager' )
		);
		self::send( array( $employee->user_email ), $subject, $body );
	}

	public static function on_request_cancelled( int $request_id, int $actor_id ): void {
		if ( ! self::enabled() ) {
			return;
		}
		$request = ( new ELM_Leave_Service() )->get_request( $request_id );
		if ( is_wp_error( $request ) ) {
			return;
		}
		$employee_id = (int) $request['employee_id'];
		$subject = sprintf( __( '[%1$s] Kërkesa për pushim e %2$s u anulua', 'employee-leave-manager' ), self::site_name(), (string) $request['employee_name'] );
		$body = self::format_request_summary( $request, __( 'Kjo kërkesë për pushim është anuluar.', 'employee-leave-manager' ) );
		if ( $actor_id === $employee_id ) {
			self::send( self::chief_recipients( $employee_id ), $subject, $body );
			return;
		}
		$employee = get_userdata( $employee_id );
		if ( $employee && is_email( $employee->user_email ) ) {
			self::send( array( $employee->user_email ), $subject, $body );
		}
	}

	public static function on_entitlement_created( int $change_id, int $actor_id ): void {
		if ( ! self::enabled() ) {
			return;
		}
		$change = self::entitlement_row( $change_id );
		if ( ! $change ) {
			return;
		}
		$recipients = self::chief_recipients( (int) $change['user_id'] );
		if ( ! $recipients ) {
			return;
		}
		$subject = sprintf( __( '[%1$s] Kërkesë për +1 ditë pushimi nga %2$s', 'employee-leave-manager' ), self::site_name(), $change['employee_name'] );
		$body = self::format_entitlement_summary( $change, __( 'Është paraqitur një kërkesë e re për +1 ditë pushimi vjetor për përvojë pune.', 'employee-leave-manager' ) );
		self::send( $recipients, $subject, $body );
	}

	public static function on_entitlement_decided( int $change_id, string $decision ): void {
		if ( ! self::enabled() ) {
			return;
		}
		$change = self::entitlement_row( $change_id );
		if ( ! $change ) {
			return;
		}
		if ( (int) $change['decided_by'] === (int) $change['user_id'] ) {
			return;
		}
		$employee = get_userdata( (int) $change['user_id'] );
		if ( ! $employee || ! is_email( $employee->user_email ) ) {
			return;
		}
		$is_approved = 'approved' === $decision;
		$subject = sprintf(
			$is_approved
				? __( '[%s] Kërkesa juaj për +1 ditë pushimi u miratua', 'employee-leave-manager' )
				: __( '[%s] Kërkesa juaj për +1 ditë pushimi u refuzua', 'employee-leave-manager' ),
			self::site_name()
		);
		$body = self::format_entitlement_summary(
			$change,
			$is_approved
				? __( 'Kërkesa juaj për +1 ditë pushimi vjetor është miratuar.', 'employee-leave-manager' )
				: __( 'Kërkesa juaj për +1 ditë pushimi vjetor është refuzuar.', 'employee-leave-manager' )
		);
		self::send( array( $employee->user_email ), $subject, $body );
	}

	/**
	 * Direct, viewer-agnostic row lookup. ELM_Entitlement_Service::get()/list()
	 * intentionally scope results to what the current viewer may see, which is
	 * the wrong lens here: the decision has already been authorized, and this
	 * class only needs the plain record to compose the notification.
	 */
	private static function entitlement_row( int $change_id ): ?array {
		if ( $change_id <= 0 || ! ELM_DB::schema_ready() ) {
			return null;
		}
		global $wpdb;
		$table = ELM_DB::table( 'entitlement_changes' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", $change_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			return null;
		}
		$employee = get_userdata( (int) $row['user_id'] );
		$row['user_id'] = (int) $row['user_id'];
		$row['decided_by'] = (int) ( $row['decided_by'] ?? 0 );
		$row['current_entitlement'] = (int) round( (float) $row['current_entitlement'] );
		$row['requested_entitlement'] = (int) round( (float) $row['requested_entitlement'] );
		$row['effective_year'] = (int) $row['effective_year'];
		$row['employee_name'] = $employee ? $employee->display_name : __( 'Përdorues i fshirë', 'employee-leave-manager' );
		return $row;
	}

	/** @return string[] */
	private static function chief_recipients( int $employee_id ): array {
		$emails = array();
		foreach ( ELM_Chief_Access::chiefs() as $chief ) {
			if ( ELM_Chief_Access::can_manage_employee( (int) $chief->ID, $employee_id ) && is_email( $chief->user_email ) ) {
				$emails[] = $chief->user_email;
			}
		}
		if ( ! empty( ELM_Policy::settings()['notification_cc_admin'] ) ) {
			$admin_email = get_option( 'admin_email' );
			if ( is_email( $admin_email ) ) {
				$emails[] = (string) $admin_email;
			}
		}
		return array_values( array_unique( $emails ) );
	}

	private static function site_name(): string {
		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	private static function format_request_summary( array $request, string $intro ): string {
		$type_label = 'medical' === ( $request['leave_type'] ?? '' )
			? __( 'Pushim mjekësor', 'employee-leave-manager' )
			: __( 'Pushim vjetor', 'employee-leave-manager' );
		$lines = array(
			$intro,
			'',
			sprintf( __( 'Punonjësi: %s', 'employee-leave-manager' ), (string) ( $request['employee_name'] ?? '' ) ),
			sprintf( __( 'Lloji: %s', 'employee-leave-manager' ), $type_label ),
			sprintf( __( 'Periudha: %1$s deri %2$s', 'employee-leave-manager' ), (string) ( $request['start_date'] ?? '' ), (string) ( $request['end_date'] ?? '' ) ),
			sprintf( __( 'Ditë: %d', 'employee-leave-manager' ), (int) ( $request['requested_units'] ?? 0 ) ),
		);
		if ( ! empty( $request['reason'] ) ) {
			$lines[] = sprintf( __( 'Arsyetimi: %s', 'employee-leave-manager' ), (string) $request['reason'] );
		}
		if ( ! empty( $request['decision_note'] ) ) {
			$lines[] = sprintf( __( 'Shënimi i vendimit: %s', 'employee-leave-manager' ), (string) $request['decision_note'] );
		}
		$lines[] = '';
		$lines[] = self::portal_line();
		return implode( "\n", $lines );
	}

	private static function format_entitlement_summary( array $change, string $intro ): string {
		$lines = array(
			$intro,
			'',
			sprintf( __( 'Punonjësi: %s', 'employee-leave-manager' ), (string) ( $change['employee_name'] ?? '' ) ),
			sprintf( __( 'Numri aktual: %d', 'employee-leave-manager' ), (int) ( $change['current_entitlement'] ?? 0 ) ),
			sprintf( __( 'Numri i kërkuar: %d', 'employee-leave-manager' ), (int) ( $change['requested_entitlement'] ?? 0 ) ),
			sprintf( __( 'Në fuqi nga viti: %d', 'employee-leave-manager' ), (int) ( $change['effective_year'] ?? 0 ) ),
		);
		if ( ! empty( $change['reason'] ) ) {
			$lines[] = sprintf( __( 'Arsyetimi: %s', 'employee-leave-manager' ), (string) $change['reason'] );
		}
		if ( ! empty( $change['decision_note'] ) ) {
			$lines[] = sprintf( __( 'Shënimi i vendimit: %s', 'employee-leave-manager' ), (string) $change['decision_note'] );
		}
		$lines[] = '';
		$lines[] = self::portal_line();
		return implode( "\n", $lines );
	}

	private static function portal_line(): string {
		$url = ELM_Access::portal_url();
		return '' !== $url
			? sprintf( __( 'Hapni portalin e pushimeve: %s', 'employee-leave-manager' ), $url )
			: __( 'Hapni panelin e menaxhimit të pushimeve për më shumë detaje.', 'employee-leave-manager' );
	}

	/** @param string[] $recipients */
	private static function send( array $recipients, string $subject, string $body ): void {
		$recipients = array_values( array_unique( array_filter( $recipients, 'is_email' ) ) );
		if ( ! $recipients ) {
			return;
		}
		wp_mail( $recipients, $subject, $body );
	}
}
