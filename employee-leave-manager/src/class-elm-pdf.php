<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_PDF {
	public static function stream_request( int $request_id ): void {
		$service = new ELM_Leave_Service();
		$request = $service->get_request( $request_id );
		if ( is_wp_error( $request ) ) {
			wp_die( esc_html( $request->get_error_message() ), '', array( 'response' => 404 ) );
		}

		$user = get_userdata( (int) $request['employee_id'] );
		if ( ! $user ) {
			wp_die( esc_html__( 'Punonjësi nuk u gjet.', 'employee-leave-manager' ), '', array( 'response' => 404 ) );
		}

		$form_data = self::request_form_data( $request, $user );
		$html = self::request_html( $form_data );
		$lines = self::request_fallback_lines( $request, $user );
		self::stream_request_document( self::request_filename( $request, $user ), $html, $lines, $form_data );
	}

	public static function stream_employee_year( int $user_id, int $year ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_die( esc_html__( 'Punonjësi nuk u gjet.', 'employee-leave-manager' ), '', array( 'response' => 404 ) );
		}

		$service = new ELM_Leave_Service();
		$requests = $service->list_requests( array( 'employee_id' => $user_id, 'year' => $year ), get_current_user_id(), true );
		if ( ! $requests ) {
			wp_die( esc_html__( 'Nuk u gjet asnjë kërkesë për pushim për këtë punonjës dhe vit.', 'employee-leave-manager' ), '', array( 'response' => 404 ) );
		}

		usort(
			$requests,
			static function ( array $left, array $right ): int {
				$left_date = (string) ( $left['start_date'] ?? '' );
				$right_date = (string) ( $right['start_date'] ?? '' );
				$date_order = strcmp( $left_date, $right_date );
				return 0 !== $date_order ? $date_order : (int) ( $left['id'] ?? 0 ) <=> (int) ( $right['id'] ?? 0 );
			}
		);

		$forms = array_map(
			static fn( array $request ): array => self::request_form_data( $request, $user ),
			$requests
		);
		$html = self::request_batch_html( $forms );
		self::stream_request_batch_document(
			'employee-' . $user_id . '-leave-requests-' . $year . '.pdf',
			$html,
			$forms
		);
	}

	/**
	 * Build a descriptive, download-safe PDF filename from the request itself.
	 * Example: guxim.krasniqi_pushim-vjetor_miratuar_07-08-2026_16-11-20.pdf
	 */
	private static function request_filename( array $request, WP_User $user ): string {
		$employee = trim( (string) ( $request['employee_name'] ?? $user->display_name ?? $user->user_login ) );
		if ( '' === $employee ) {
			$employee = 'punonjesi-' . (int) $user->ID;
		}

		$leave_type = match ( sanitize_key( (string) ( $request['leave_type'] ?? '' ) ) ) {
			'annual'  => 'Pushim vjetor',
			'medical' => 'Pushim mjekesor',
			default   => 'Pushim',
		};

		$status = match ( sanitize_key( (string) ( $request['status'] ?? '' ) ) ) {
			'approved'  => 'Miratuar',
			'rejected'  => 'Refuzuar',
			'cancelled' => 'Anuluar',
			'pending'   => 'Ne-pritje',
			default     => 'Status',
		};

		$timestamp = self::filename_datetime( (string) ( $request['submitted_at'] ?? '' ) );
		$parts = array( $employee, $leave_type, $status, $timestamp );
		$parts = array_map( array( __CLASS__, 'filename_component' ), $parts );
		$parts = array_values( array_filter( $parts, static fn( string $part ): bool => '' !== $part ) );

		$filename = implode( '_', $parts ) . '.pdf';
		$filename = strtolower( sanitize_file_name( $filename ) );
		$filename = (string) apply_filters( 'elm_request_pdf_filename', $filename, $request, $user );

		// Keep the suggested download filename consistently lowercase, including filtered values.
		return strtolower( sanitize_file_name( $filename ) );
	}

	private static function filename_datetime( string $value ): string {
		$value = trim( $value );
		$parsed = false;

		if ( '' !== $value ) {
			$parsed = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value, wp_timezone() );
			if ( ! $parsed ) {
				try {
					$parsed = new DateTimeImmutable( $value, wp_timezone() );
				} catch ( Exception $e ) {
					$parsed = false;
				}
			}
		}

		if ( ! $parsed ) {
			$parsed = new DateTimeImmutable( 'now', wp_timezone() );
		}

		return $parsed->format( 'd-m-Y_H-i-s' );
	}

	private static function filename_component( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$value = remove_accents( $value );
		$value = preg_replace( '/\s+/u', '-', $value ) ?? $value;
		$value = preg_replace( '/[^A-Za-z0-9._-]+/u', '-', $value ) ?? $value;
		$value = preg_replace( '/-+/u', '-', $value ) ?? $value;

		return trim( $value, '-_.' );
	}

	private static function request_form_data( array $request, WP_User $user ): array {
		$employee_name = trim( $user->first_name . ' ' . $user->last_name );
		if ( '' === $employee_name ) {
			$employee_name = $user->display_name;
		}

		$position = array_key_exists( 'employee_position', $request ) && null !== $request['employee_position']
			? sanitize_text_field( (string) $request['employee_position'] )
			: ELM_Employee_Profile::position( $user->ID );
		$sector = array_key_exists( 'employee_sector', $request ) && null !== $request['employee_sector']
			? sanitize_text_field( (string) $request['employee_sector'] )
			: ELM_Employee_Profile::sector( $user->ID );
		$position = sanitize_text_field( (string) apply_filters( 'elm_request_pdf_employee_position', $position, $user, $request ) );
		$sector = sanitize_text_field( (string) apply_filters( 'elm_request_pdf_employee_sector', $sector, $user, $request ) );

		$start = self::date_parts( (string) $request['start_date'] );
		$end = self::date_parts( (string) $request['end_date'] );
		$return = self::date_parts( self::return_to_work_date( (string) $request['end_date'] ) );
		$submitted_date = self::format_datetime_date( (string) ( $request['submitted_at'] ?? '' ) );
		$status = sanitize_key( (string) ( $request['status'] ?? '' ) );
		$leave_type = sanitize_key( (string) ( $request['leave_type'] ?? '' ) );
		$decision_date = in_array( $status, array( 'approved', 'rejected' ), true ) ? self::format_datetime_date( (string) ( $request['decided_at'] ?? '' ) ) : '';
		$decision_reason = 'rejected' === $status ? sanitize_textarea_field( (string) ( $request['decision_note'] ?? '' ) ) : '';
		$reason_lines = self::reason_lines( $decision_reason );

		$employee_info_line_width = self::employee_info_line_width( array( $employee_name, $position, $sector ) );

		return array(
			'EMPLOYEE_NAME'            => trim( $employee_name ),
			'POSITION'                 => trim( $position ),
			'SECTOR'                   => trim( $sector ),
			'EMPLOYEE_INFO_LINE_WIDTH' => self::decimal_number( $employee_info_line_width ),
			'ANNUAL_MARK'        => 'annual' === $leave_type ? 'X' : '',
			'MEDICAL_MARK'       => 'medical' === $leave_type ? 'X' : '',
			'BEREAVEMENT_MARK'   => '',
			'PARENTAL_MARK'      => '',
			'START_DAY'          => $start['day'],
			'START_MONTH'        => $start['month'],
			'START_YEAR'         => $start['year'],
			'END_DAY'            => $end['day'],
			'END_MONTH'          => $end['month'],
			'END_YEAR'           => $end['year'],
			'RETURN_DAY'         => $return['day'],
			'RETURN_MONTH'       => $return['month'],
			'RETURN_YEAR'        => $return['year'],
			'SUBMITTED_DATE'     => $submitted_date,
			'LEAVE_TYPE_LABEL'   => self::leave_type_label( $leave_type ),
			'APPROVED_MARK'      => 'approved' === $status ? 'X' : '',
			'PARTIAL_MARK'       => '',
			'REJECTED_MARK'      => 'rejected' === $status ? 'X' : '',
			'DECISION_REASON_1'  => $reason_lines[0],
			'DECISION_REASON_2'  => $reason_lines[1],
			'DECISION_REASON_3'  => $reason_lines[2],
			'DECISION_DATE'      => $decision_date,
		);
	}

	private static function request_html( array $values ): string {
		$template_path = ELM_DIR . 'templates/request-pdf.html';
		$template = is_readable( $template_path ) ? file_get_contents( $template_path ) : false;
		if ( false === $template ) {
			return '';
		}

		$template = str_replace( '{{STATE_LOGO_URI}}', esc_attr( self::image_data_uri( ELM_DIR . 'assets/images/request-pdf-state-logo.png' ) ), $template );
		$template = str_replace( '{{MUNICIPALITY_LOGO_URI}}', esc_attr( self::image_data_uri( ELM_DIR . 'assets/images/request-pdf-municipality-logo.png' ) ), $template );

		foreach ( $values as $key => $value ) {
			$template = str_replace( '{{' . $key . '}}', self::html_field( (string) $value ), $template );
		}

		return $template;
	}

	private static function request_batch_html( array $forms ): string {
		$forms = array_values( array_filter( $forms, 'is_array' ) );
		if ( ! $forms ) {
			return '';
		}

		$pages = array();
		$document_head = '';
		$page_count = count( $forms );
		foreach ( $forms as $index => $values ) {
			$html = self::request_html( $values );
			if ( '' === $html ) {
				return '';
			}
			$body_start = stripos( $html, '<body>' );
			$body_end = strripos( $html, '</body>' );
			if ( false === $body_start || false === $body_end || $body_end <= $body_start ) {
				return '';
			}
			$body_open_end = strpos( $html, '>', $body_start );
			if ( false === $body_open_end ) {
				return '';
			}
			if ( '' === $document_head ) {
				$document_head = substr( $html, 0, $body_start );
				$document_head = str_replace(
					'</style>',
					"  .elm-report-page-break { page-break-after: always; }\n</style>",
					$document_head
				);
			}
			$page = substr( $html, $body_open_end + 1, $body_end - $body_open_end - 1 );
			$class = $index < $page_count - 1 ? 'page elm-report-page-break' : 'page';
			$page = preg_replace( '/class=("|\')page\1/', 'class="' . $class . '"', $page, 1 ) ?? $page;
			$pages[] = $page;
		}

		return $document_head . '<body>' . implode( "\n", $pages ) . '</body></html>';
	}

	private static function request_fallback_lines( array $request, WP_User $user ): array {
		$return_date = self::return_to_work_date( (string) $request['end_date'] );
		$status = sanitize_key( (string) ( $request['status'] ?? '' ) );
		$lines = array(
			'KERKESA PER PUSHIM - KOMUNA E PRISHTINES',
			'Emri dhe mbiemri i punonjesit: ' . $user->display_name,
			'Lloji i pushimit: ' . self::leave_type_label( sanitize_key( (string) $request['leave_type'] ) ),
			'Prej: ' . self::format_iso_date( (string) $request['start_date'] ),
			'Deri me daten: ' . self::format_iso_date( (string) $request['end_date'] ),
			'Ne pune te lajmerohet me daten: ' . self::format_iso_date( $return_date ),
			'Data e paraqitjes se kerkeses: ' . self::format_datetime_date( (string) ( $request['submitted_at'] ?? '' ) ),
			'Statusi: ' . strtoupper( $status ),
		);
		if ( 'rejected' === $status && ! empty( $request['decision_note'] ) ) {
			$lines[] = 'Arsyetimi nga udheheqesi: ' . sanitize_textarea_field( (string) $request['decision_note'] );
		}
		$lines[] = '';
		$lines[] = 'Nenshkrimi i punonjesit: __________________________';
		$lines[] = 'Nenshkrimi i udheheqesit: _________________________';
		return $lines;
	}

	private static function employee_info_line_width( array $values ): float {
		$longest = 0;
		foreach ( $values as $value ) {
			$value = trim( (string) $value );
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
			$longest = max( $longest, $length );
		}

		// Double the previous field length while preserving approximately four
		// character widths of visible underline before and after the longest value.
		return min( 300.0, max( 180.0, ( $longest + 8 ) * 9.8 ) );
	}

	private static function decimal_number( float $value ): string {
		return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
	}

	private static function return_to_work_date( string $end_date ): string {
		if ( ! ELM_Policy::validate_iso_date( $end_date ) ) {
			return '';
		}

		$settings = ELM_Policy::settings();
		$weekdays = array_values( array_unique( array_map( 'intval', (array) ( $settings['working_weekdays'] ?? array( 1, 2, 3, 4, 5 ) ) ) ) );
		$holidays = array_fill_keys( array_keys( ELM_Policy::effective_holiday_map( $settings ) ), true );
		$cursor = ( new DateTimeImmutable( $end_date, wp_timezone() ) )->modify( '+1 day' );

		for ( $offset = 0; $offset < 366; $offset++, $cursor = $cursor->modify( '+1 day' ) ) {
			$iso = $cursor->format( 'Y-m-d' );
			if ( in_array( (int) $cursor->format( 'N' ), $weekdays, true ) && ! isset( $holidays[ $iso ] ) ) {
				return $iso;
			}
		}

		return '';
	}

	private static function date_parts( string $date ): array {
		if ( ! ELM_Policy::validate_iso_date( $date ) ) {
			return array( 'day' => '', 'month' => '', 'year' => '' );
		}
		$parsed = new DateTimeImmutable( $date, wp_timezone() );
		return array(
			'day'   => $parsed->format( 'd' ),
			'month' => $parsed->format( 'm' ),
			'year'  => $parsed->format( 'Y' ),
		);
	}

	private static function format_iso_date( string $date ): string {
		$parts = self::date_parts( $date );
		return '' === $parts['year'] ? '' : $parts['day'] . '/' . $parts['month'] . '/' . $parts['year'];
	}

	private static function format_datetime_date( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$parsed = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value, wp_timezone() );
		if ( ! $parsed ) {
			try {
				$parsed = new DateTimeImmutable( $value, wp_timezone() );
			} catch ( Exception $e ) {
				return '';
			}
		}
		return $parsed->format( 'd/m/Y' );
	}

	private static function leave_type_label( string $leave_type ): string {
		return match ( $leave_type ) {
			'annual'  => 'Pushim vjetor',
			'medical' => 'Pushim mjekësor',
			default   => '',
		};
	}

	private static function reason_lines( string $reason ): array {
		$reason = trim( preg_replace( '/\s+/u', ' ', $reason ) ?? $reason );
		if ( '' === $reason ) {
			return array( '', '', '' );
		}

		$max_length = 105;
		$lines = array( '', '', '' );
		$line_index = 0;
		foreach ( preg_split( '/\s+/u', $reason ) ?: array() as $word ) {
			$candidate = '' === $lines[ $line_index ] ? $word : $lines[ $line_index ] . ' ' . $word;
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $candidate, 'UTF-8' ) : strlen( $candidate );
			if ( $length <= $max_length ) {
				$lines[ $line_index ] = $candidate;
				continue;
			}

			if ( $line_index < 2 ) {
				$line_index++;
				$word_length = function_exists( 'mb_strlen' ) ? mb_strlen( $word, 'UTF-8' ) : strlen( $word );
				if ( $word_length <= $max_length ) {
					$lines[ $line_index ] = $word;
					continue;
				}
				$lines[ $line_index ] = self::truncate_text( $word, $max_length );
				break;
			}

			$lines[2] = self::truncate_text( $lines[2], $max_length );
			break;
		}
		return $lines;
	}

	private static function truncate_text( string $value, int $max_length ): string {
		if ( $max_length < 2 ) {
			return '';
		}
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		if ( $length < $max_length ) {
			return rtrim( $value ) . '…';
		}
		$trimmed = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length - 1, 'UTF-8' ) : substr( $value, 0, $max_length - 1 );
		return rtrim( $trimmed ) . '…';
	}

	private static function image_data_uri( string $path ): string {
		$data = is_readable( $path ) ? file_get_contents( $path ) : false;
		return false === $data ? '' : 'data:image/png;base64,' . base64_encode( $data );
	}

	private static function html_field( string $value ): string {
		$value = trim( $value );
		return '' === $value ? '&nbsp;' : esc_html( $value );
	}

	private static function stream_request_batch_document( string $filename, string $html, array $forms ): void {
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		if ( '' !== $html && class_exists( '\\Dompdf\\Dompdf' ) ) {
			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', false );
			$options->set( 'isPhpEnabled', false );
			$options->set( 'isJavascriptEnabled', false );
			$options->set( 'chroot', ELM_DIR );
			$options->set( 'defaultFont', 'DejaVu Sans' );
			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			$dompdf->stream( sanitize_file_name( $filename ), array( 'Attachment' => true ) );
			exit;
		}

		$pdf = ELM_Simple_PDF::render_request_forms(
			$forms,
			ELM_DIR . 'assets/images/request-pdf-state-logo.jpg',
			ELM_DIR . 'assets/images/request-pdf-municipality-logo.jpg'
		);
		if ( '' === $pdf ) {
			wp_die( esc_html__( 'Krijimi i raportit PDF dështoi.', 'employee-leave-manager' ), '', array( 'response' => 500 ) );
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private static function stream_request_document( string $filename, string $html, array $fallback_lines, array $form_data ): void {
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		if ( '' !== $html && class_exists( '\\Dompdf\\Dompdf' ) ) {
			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', false );
			$options->set( 'isPhpEnabled', false );
			$options->set( 'isJavascriptEnabled', false );
			$options->set( 'chroot', ELM_DIR );
			$options->set( 'defaultFont', 'DejaVu Sans' );
			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			$dompdf->stream( sanitize_file_name( $filename ), array( 'Attachment' => true ) );
			exit;
		}

		$pdf = ELM_Simple_PDF::render_request_form(
			$form_data,
			ELM_DIR . 'assets/images/request-pdf-state-logo.jpg',
			ELM_DIR . 'assets/images/request-pdf-municipality-logo.jpg'
		);
		if ( '' === $pdf ) {
			$pdf = ELM_Simple_PDF::render( 'Kerkesa per pushim', $fallback_lines );
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private static function dates_label( array $request ): string {
		$dates = is_array( $request['selected_dates'] ?? null ) ? $request['selected_dates'] : array();
		$period = $request['start_date'] === $request['end_date'] ? $request['start_date'] : $request['start_date'] . ' deri më ' . $request['end_date'];
		return $dates ? $period . ' - ditët e punës të zbritura: ' . implode( ', ', $dates ) : $period;
	}

	private static function whole_days( mixed $value ): string {
		return (string) max( 0, (int) round( (float) $value ) );
	}

	private static function stream( string $filename, string $title, array $lines ): void {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		if ( class_exists( '\\Dompdf\\Dompdf' ) ) {
			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', false );
			$options->set( 'isPhpEnabled', false );
			$options->set( 'isJavascriptEnabled', false );
			$dompdf = new \Dompdf\Dompdf( $options );
			$html = '<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:40px}body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:11px;line-height:1.5}h1{font-size:21px;border-bottom:2px solid #3157d5;padding-bottom:10px}.line{padding:4px 0;border-bottom:1px solid #e5e7eb;white-space:pre-wrap}.footer{margin-top:24px;font-size:9px;color:#667085}</style></head><body><h1>' . esc_html( $title ) . '</h1>';
			foreach ( $lines as $line ) {
				$html .= '<div class="line">' . esc_html( $line ) . '</div>';
			}
			$html .= '<div class="footer">Gjeneruar nga Menaxhimi i Pushimeve të Punonjësve</div></body></html>';
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			$dompdf->stream( sanitize_file_name( $filename ), array( 'Attachment' => true ) );
			exit;
		}

		$pdf = ELM_Simple_PDF::render( $title, $lines );
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
