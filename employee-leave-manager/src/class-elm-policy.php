<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_Policy {
	public static function settings(): array {
		$defaults = array(
			'annual_entitlement' => 20.0,
			'period_one_limit'   => 0.0,
			'concurrency_limit'  => 2,
			'working_weekdays'   => array( 1, 2, 3, 4, 5 ),
			// Fushat e vjetra ruhen vetëm për pajtueshmëri me instalimet ekzistuese.
			'holidays'           => array(),
			'holiday_names'      => array(),
			'movable_holidays'   => self::default_movable_holidays(),
			'max_upload_mb'      => 10,
			'frontend_only_enabled' => false,
			'portal_page_id'        => 0,
			'show_plus_one_when_empty' => false,
			'email_notifications_enabled' => true,
			'notification_cc_admin'       => false,
		);
		$stored = get_option( 'elm_settings', array() );
		$stored = is_array( $stored ) ? $stored : array();
		$settings = wp_parse_args( $stored, $defaults );
		// Rregullorja (QRK) Nr. 04/2024, neni 9: pushimi vjetor bazë është 20 ditë pune.
		// Fusha period_one_limit ruhet vetëm për pajtueshmëri teknike me versionet e vjetra; rregullorja nuk përcakton kufi janar-qershor.
		$settings['annual_entitlement'] = 20.0;
		$settings['period_one_limit'] = 0.0;
		$settings['holidays'] = array_values( array_unique( array_filter( array_map( 'strval', (array) $settings['holidays'] ), array( __CLASS__, 'validate_iso_date' ) ) ) );
		sort( $settings['holidays'], SORT_STRING );
		$holiday_names = array();
		foreach ( (array) $settings['holiday_names'] as $date => $name ) {
			$date = (string) $date;
			if ( self::validate_iso_date( $date ) && in_array( $date, $settings['holidays'], true ) ) {
				$holiday_names[ $date ] = sanitize_text_field( (string) $name );
			}
		}
		$settings['holiday_names'] = $holiday_names;
		$settings['movable_holidays'] = self::normalize_movable_holidays(
			array_key_exists( 'movable_holidays', $stored ) ? $stored['movable_holidays'] : array(),
			$holiday_names
		);
		$settings['portal_page_id'] = absint( $settings['portal_page_id'] ?? 0 );
		$settings['frontend_only_enabled'] = ! empty( $settings['frontend_only_enabled'] );
		$settings['show_plus_one_when_empty'] = ! empty( $settings['show_plus_one_when_empty'] );
		$settings['email_notifications_enabled'] = array_key_exists( 'email_notifications_enabled', $stored ) ? ! empty( $stored['email_notifications_enabled'] ) : true;
		$settings['notification_cc_admin'] = ! empty( $settings['notification_cc_admin'] );
		if ( ! $settings['portal_page_id'] || 'publish' !== get_post_status( $settings['portal_page_id'] ) ) {
			$settings['portal_page_id'] = 0;
			$settings['frontend_only_enabled'] = false;
		}
		return $settings;
	}

	/**
	 * Festat me datë fikse që përsëriten automatikisht çdo vit.
	 *
	 * Çelësi përdor formatin MM-DD, prandaj nuk kërkohet shtimi manual i vitit.
	 */
	public static function fixed_holiday_definitions(): array {
		return array(
			'01-01' => __( 'Viti i Ri', 'employee-leave-manager' ),
			'01-02' => __( 'Viti i Ri', 'employee-leave-manager' ),
			'01-07' => __( 'Krishtlindjet ortodokse', 'employee-leave-manager' ),
			'02-17' => __( 'Dita e Pavarësisë së Republikës së Kosovës', 'employee-leave-manager' ),
			'04-06' => __( 'Pashkët Katolike', 'employee-leave-manager' ),
			'04-09' => __( 'Dita e Kushtetutës së Republikës së Kosovës', 'employee-leave-manager' ),
			'04-13' => __( 'Pashkët Ortodokse', 'employee-leave-manager' ),
			'05-01' => __( 'Dita Ndërkombëtare e Punës', 'employee-leave-manager' ),
			'05-11' => __( 'Dita e Evropës', 'employee-leave-manager' ),
			'12-25' => __( 'Krishtlindjet Katolike', 'employee-leave-manager' ),
		);
	}

	public static function movable_holiday_labels(): array {
		return array(
			'bajrami_i_madh'  => __( 'Bajrami i Madh, dita e parë', 'employee-leave-manager' ),
			'bajrami_i_vogel' => __( 'Bajrami i Vogël, dita e parë', 'employee-leave-manager' ),
		);
	}

	public static function default_movable_holidays(): array {
		return array(
			'2026' => array(
				'bajrami_i_madh'  => '2026-03-20',
				'bajrami_i_vogel' => '2026-05-27',
			),
		);
	}

	private static function normalize_movable_holidays( mixed $value, array $legacy_names = array() ): array {
		$labels = self::movable_holiday_labels();
		$normalized = array();

		if ( is_array( $value ) ) {
			foreach ( $value as $year => $entries ) {
				$year = absint( $year );
				if ( $year < 2000 || $year > 2100 || ! is_array( $entries ) ) {
					continue;
				}
				foreach ( array_keys( $labels ) as $key ) {
					$date = sanitize_text_field( (string) ( $entries[ $key ] ?? '' ) );
					if ( self::validate_iso_date( $date ) && (int) substr( $date, 0, 4 ) === $year ) {
						$normalized[ (string) $year ][ $key ] = $date;
					}
				}
			}
		}

		// Migrim i butë nga lista e vjetër e festave, nëse instalimi nuk ka ende
		// strukturën e re për Bajramet.
		foreach ( $legacy_names as $date => $name ) {
			if ( ! self::validate_iso_date( (string) $date ) ) {
				continue;
			}
			$plain = strtolower( remove_accents( (string) $name ) );
			$key = str_contains( $plain, 'bajrami i madh' )
				? 'bajrami_i_madh'
				: ( str_contains( $plain, 'bajrami i vogel' ) ? 'bajrami_i_vogel' : '' );
			if ( '' === $key ) {
				continue;
			}
			$year = substr( (string) $date, 0, 4 );
			if ( empty( $normalized[ $year ][ $key ] ) ) {
				$normalized[ $year ][ $key ] = (string) $date;
			}
		}

		// Datat e vitit 2026 vijnë nga konfigurimi aktual i kërkuar nga përdoruesi.
		foreach ( self::default_movable_holidays() as $year => $entries ) {
			foreach ( $entries as $key => $date ) {
				if ( empty( $normalized[ $year ][ $key ] ) ) {
					$normalized[ $year ][ $key ] = $date;
				}
			}
		}

		ksort( $normalized, SORT_NUMERIC );
		return $normalized;
	}

	public static function movable_holiday_edit_dates( ?array $settings = null ): array {
		$settings = $settings ?? self::settings();
		$all = self::normalize_movable_holidays( $settings['movable_holidays'] ?? array() );
		$current_year = (int) current_datetime()->format( 'Y' );
		$result = array();
		foreach ( array_keys( self::movable_holiday_labels() ) as $key ) {
			$candidates = array();
			foreach ( $all as $year => $entries ) {
				if ( ! empty( $entries[ $key ] ) && (int) $year >= $current_year ) {
					$candidates[ (int) $year ] = (string) $entries[ $key ];
				}
			}
			if ( $candidates ) {
				krsort( $candidates, SORT_NUMERIC );
				$result[ $key ] = (string) reset( $candidates );
				continue;
			}
			foreach ( array_reverse( $all, true ) as $entries ) {
				if ( ! empty( $entries[ $key ] ) ) {
					$result[ $key ] = (string) $entries[ $key ];
					break;
				}
			}
			$result[ $key ] = $result[ $key ] ?? '';
		}
		return $result;
	}

	public static function merge_movable_holiday_dates( array $existing, array $submitted ): array|WP_Error {
		$merged = self::normalize_movable_holidays( $existing );
		foreach ( array_keys( self::movable_holiday_labels() ) as $key ) {
			$date = sanitize_text_field( (string) ( $submitted[ $key ] ?? '' ) );
			if ( '' === $date ) {
				continue;
			}
			if ( ! self::validate_iso_date( $date ) ) {
				return new WP_Error( 'elm_invalid_movable_holiday_date', __( 'Zgjidhni një datë të vlefshme për festën e lëvizshme.', 'employee-leave-manager' ), array( 'status' => 400 ) );
			}
			$year = (int) substr( $date, 0, 4 );
			if ( $year < 2000 || $year > 2100 ) {
				return new WP_Error( 'elm_invalid_movable_holiday_year', __( 'Viti i festës së lëvizshme duhet të jetë ndërmjet 2000 dhe 2100.', 'employee-leave-manager' ), array( 'status' => 400 ) );
			}
			$merged[ (string) $year ][ $key ] = $date;
		}
		ksort( $merged, SORT_NUMERIC );
		return $merged;
	}

	public static function holiday_map( ?array $settings = null ): array {
		$settings = $settings ?? self::settings();
		$map = array();

		// Intervali përputhet me kufijtë e viteve të përdorura nga ndërfaqja.
		foreach ( range( 2000, 2100 ) as $year ) {
			foreach ( self::fixed_holiday_definitions() as $month_day => $name ) {
				$map[ sprintf( '%04d-%s', $year, $month_day ) ] = $name;
			}
		}

		$movable = self::normalize_movable_holidays( $settings['movable_holidays'] ?? array(), (array) ( $settings['holiday_names'] ?? array() ) );
		foreach ( $movable as $year => $entries ) {
			foreach ( self::movable_holiday_labels() as $key => $label ) {
				$date = (string) ( $entries[ $key ] ?? '' );
				if ( self::validate_iso_date( $date ) ) {
					$map[ $date ] = $label;
				}
			}
		}

		ksort( $map, SORT_STRING );
		return $map;
	}

	public static function effective_holiday_map( ?array $settings = null ): array {
		$settings = $settings ?? self::settings();
		$configured = self::holiday_map( $settings );
		$weekdays = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $settings['working_weekdays'] ?? array( 1, 2, 3, 4, 5 ) ) ), static fn( int $day ): bool => $day >= 1 && $day <= 7 ) ) );
		if ( empty( $weekdays ) ) {
			$weekdays = array( 1, 2, 3, 4, 5 );
		}

		$effective = $configured;
		$occupied = array_fill_keys( array_keys( $configured ), true );
		foreach ( $configured as $date => $name ) {
			$holiday = new DateTimeImmutable( $date, wp_timezone() );
			if ( (int) $holiday->format( 'N' ) < 6 ) {
				continue;
			}

			$observed = $holiday->modify( 'next monday' );
			for ( $offset = 0; $offset < 366; $offset++, $observed = $observed->modify( '+1 day' ) ) {
				$observed_date = $observed->format( 'Y-m-d' );
				if ( ! in_array( (int) $observed->format( 'N' ), $weekdays, true ) || isset( $occupied[ $observed_date ] ) ) {
					continue;
				}

				$effective[ $observed_date ] = $name;
				$occupied[ $observed_date ] = true;
				break;
			}
		}

		ksort( $effective, SORT_STRING );
		return $effective;
	}

	public static function format_holiday_text( ?array $settings = null ): string {
		$settings = $settings ?? self::settings();
		$year = (int) current_datetime()->format( 'Y' );
		$lines = array( 'Dita|Muaji|Viti|Festa' );
		$effective = self::holiday_map( $settings );
		foreach ( $effective as $date => $name ) {
			if ( (int) substr( $date, 0, 4 ) !== $year ) {
				continue;
			}
			$parsed = new DateTimeImmutable( $date, wp_timezone() );
			$lines[] = sprintf( '%d|%d|%d|%s', (int) $parsed->format( 'j' ), (int) $parsed->format( 'n' ), (int) $parsed->format( 'Y' ), str_replace( array( "\r", "\n", '|' ), array( ' ', ' ', '/' ), $name ) );
		}
		return implode( "\n", $lines );
	}

	public static function parse_holiday_text( string $input ): array|WP_Error {
		$input = preg_replace( '/^\xEF\xBB\xBF/', '', $input ) ?? $input;
		$input = trim( $input );
		if ( '' === $input ) {
			return array();
		}

		$map = array();
		$lines = preg_split( '/\R/u', $input ) ?: array();
		foreach ( $lines as $index => $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}

			$normalized = strtolower( remove_accents( preg_replace( '/\s+/', '', $line ) ?? $line ) );
			if ( str_contains( $normalized, 'dita' ) && str_contains( $normalized, 'muaji' ) && str_contains( $normalized, 'viti' ) ) {
				continue;
			}

			if ( preg_match( '/^(\d{4}-\d{2}-\d{2})(?:[|;,\t](.*))?$/u', $line, $matches ) ) {
				$date = $matches[1];
				if ( ! self::validate_iso_date( $date ) ) {
					return new WP_Error( 'elm_invalid_holiday_date', sprintf( __( 'Rreshti %d i festave përmban datë të pavlefshme.', 'employee-leave-manager' ), $index + 1 ), array( 'status' => 400 ) );
				}
				$map[ $date ] = sanitize_text_field( trim( (string) ( $matches[2] ?? '' ) ) );
				continue;
			}

			$delimiter = '|';
			$best_count = 0;
			foreach ( array( '|', ';', "\t", ',' ) as $candidate ) {
				$count = substr_count( $line, $candidate );
				if ( $count > $best_count ) {
					$delimiter = $candidate;
					$best_count = $count;
				}
			}
			$parts = str_getcsv( $line, $delimiter );
			if ( count( $parts ) < 4 ) {
				return new WP_Error( 'elm_invalid_holiday_format', sprintf( __( 'Rreshti %d i festave duhet të përdorë formatin Dita|Muaji|Viti|Festa.', 'employee-leave-manager' ), $index + 1 ), array( 'status' => 400 ) );
			}
			$day = absint( trim( (string) $parts[0] ) );
			$month = absint( trim( (string) $parts[1] ) );
			$year = absint( trim( (string) $parts[2] ) );
			$name = sanitize_text_field( trim( implode( $delimiter, array_slice( $parts, 3 ) ) ) );
			if ( ! checkdate( $month, $day, $year ) ) {
				return new WP_Error( 'elm_invalid_holiday_date', sprintf( __( 'Rreshti %d i festave përmban datë të pavlefshme.', 'employee-leave-manager' ), $index + 1 ), array( 'status' => 400 ) );
			}
			if ( '' === $name ) {
				return new WP_Error( 'elm_missing_holiday_name', sprintf( __( 'Në rreshtin %d të festave mungon emri i festës.', 'employee-leave-manager' ), $index + 1 ), array( 'status' => 400 ) );
			}
			$map[ sprintf( '%04d-%02d-%02d', $year, $month, $day ) ] = $name;
		}

		ksort( $map, SORT_STRING );
		return $map;
	}

	public static function validate_iso_date( string $date ): bool {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		return $parsed instanceof DateTimeImmutable && $parsed->format( 'Y-m-d' ) === $date;
	}

	public static function dates_from_selection( array|string $selected_dates ): array|WP_Error {
		if ( is_string( $selected_dates ) ) {
			$selected_dates = preg_split( '/[\s,;]+/', $selected_dates, -1, PREG_SPLIT_NO_EMPTY ) ?: array();
		}
		$selected_dates = array_values( array_unique( array_map( 'sanitize_text_field', $selected_dates ) ) );
		sort( $selected_dates, SORT_STRING );
		if ( empty( $selected_dates ) ) {
			return new WP_Error( 'elm_empty_dates', __( 'Zgjidhni së paku një datë të disponueshme.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		if ( count( $selected_dates ) > 366 ) {
			return new WP_Error( 'elm_range_too_large', __( 'Janë zgjedhur tepër data.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}

		$settings = self::settings();
		$weekdays = array_map( 'intval', (array) $settings['working_weekdays'] );
		$holidays = array_flip( array_keys( self::effective_holiday_map( $settings ) ) );
		$dates = array();
		foreach ( $selected_dates as $date ) {
			if ( ! self::validate_iso_date( $date ) ) {
				return new WP_Error( 'elm_invalid_date', __( 'Një ose më shumë data të zgjedhura janë të pavlefshme.', 'employee-leave-manager' ), array( 'status' => 400 ) );
			}
			$parsed = new DateTimeImmutable( $date, wp_timezone() );
			if ( ! in_array( (int) $parsed->format( 'N' ), $weekdays, true ) || isset( $holidays[ $date ] ) ) {
				return new WP_Error( 'elm_nonworking_date', sprintf( __( '%s nuk është ditë pune e disponueshme.', 'employee-leave-manager' ), $date ), array( 'status' => 400, 'date' => $date ) );
			}
			$dates[] = array(
				'date'   => $date,
				'units'  => 1,
				'period' => (int) $parsed->format( 'n' ) <= 6 ? 1 : 2,
			);
		}
		return $dates;
	}

	public static function dates_for_request( string $start, string $end, float $units_per_day = 1.0 ): array|WP_Error {
		if ( ! self::validate_iso_date( $start ) || ! self::validate_iso_date( $end ) ) {
			return new WP_Error( 'elm_invalid_date', __( 'Shkruani datat në formatin YYYY-MM-DD.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}

		$timezone = wp_timezone();
		$from = new DateTimeImmutable( $start, $timezone );
		$to = new DateTimeImmutable( $end, $timezone );
		if ( $to < $from ) {
			return new WP_Error( 'elm_date_order', __( 'Data e përfundimit nuk mund të jetë para datës së fillimit.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		if ( (int) $from->diff( $to )->days > 366 ) {
			return new WP_Error( 'elm_range_too_large', __( 'Periudha e zgjedhur është tepër e gjatë.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}

		$settings = self::settings();
		$weekdays = array_map( 'intval', (array) $settings['working_weekdays'] );
		$holidays = array_flip( array_keys( self::effective_holiday_map( $settings ) ) );
		$dates = array();

		for ( $cursor = $from; $cursor <= $to; $cursor = $cursor->modify( '+1 day' ) ) {
			$iso = $cursor->format( 'Y-m-d' );
			if ( ! in_array( (int) $cursor->format( 'N' ), $weekdays, true ) || isset( $holidays[ $iso ] ) ) {
				continue;
			}
			$dates[] = array(
				'date'   => $iso,
				'units'  => 1,
				'period' => (int) $cursor->format( 'n' ) <= 6 ? 1 : 2,
			);
		}

		if ( empty( $dates ) ) {
			return new WP_Error( 'elm_no_working_days', __( 'Periudha nuk përmban ditë pune.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		return $dates;
	}

	/**
	 * Prag informues organizativ për periudhën janar-qershor.
	 *
	 * Ky prag nuk e bllokon kërkesën dhe nuk paraqitet si kufizim ligjor;
	 * përdoret vetëm për të njoftuar punonjësin dhe mbikëqyrësin gjatë shqyrtimit.
	 */
	public static function period_one_warning_limit(): int {
		return 10;
	}

	public static function annual_notice_days(): int {
		return 15;
	}

	public static function annual_decision_days(): int {
		return 5;
	}

	public static function annual_earliest_start( ?string $from_date = null ): string {
		$from_date = $from_date && self::validate_iso_date( $from_date ) ? $from_date : self::today();
		return ( new DateTimeImmutable( $from_date, wp_timezone() ) )
			->modify( '+' . self::annual_notice_days() . ' days' )
			->format( 'Y-m-d' );
	}

	public static function annual_notice_is_timely( string $submitted_date, string $start_date ): bool {
		if ( ! self::validate_iso_date( $submitted_date ) || ! self::validate_iso_date( $start_date ) ) {
			return false;
		}
		return $start_date >= self::annual_earliest_start( $submitted_date );
	}

	public static function annual_decision_is_timely( string $decision_date, string $start_date ): bool {
		if ( ! self::validate_iso_date( $decision_date ) || ! self::validate_iso_date( $start_date ) ) {
			return false;
		}
		$latest = ( new DateTimeImmutable( $start_date, wp_timezone() ) )
			->modify( '-' . self::annual_decision_days() . ' days' )
			->format( 'Y-m-d' );
		return $decision_date <= $latest;
	}

	public static function today(): string {
		return current_datetime()->format( 'Y-m-d' );
	}

	public static function request_year( array|object $request ): int {
		$start = is_array( $request ) ? $request['start_date'] : $request->start_date;
		return (int) substr( (string) $start, 0, 4 );
	}
}
