<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal dependency-free PDF fallback using the PDF core Helvetica font.
 * Dompdf is preferred when Composer dependencies are installed.
 */
final class ELM_Simple_PDF {
	/**
	 * Render the official A4 leave-request form without third-party libraries.
	 * This keeps the HTML-template layout available on hosts where Dompdf is absent.
	 */
	public static function render_request_form( array $data, string $state_logo = '', string $municipality_logo = '' ): string {
		return self::render_request_forms( array( $data ), $state_logo, $municipality_logo );
	}

	/**
	 * Render multiple official A4 request forms in one PDF, one form per page.
	 */
	public static function render_request_forms( array $forms, string $state_logo = '', string $municipality_logo = '' ): string {
		$forms = array_values( array_filter( $forms, 'is_array' ) );
		if ( ! $forms ) {
			return '';
		}

		$page_width = 595.28;
		$page_height = 841.89;
		$objects = array(
			1 => '<< /Type /Catalog /Pages 2 0 R >>',
			3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>',
			4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>',
			5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
			6 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
		);
		$next_id = 7;
		$xobjects = array();

		foreach ( array( 'StateLogo' => $state_logo, 'MunicipalityLogo' => $municipality_logo ) as $name => $path ) {
			$image = self::jpeg_data( $path );
			if ( null === $image ) {
				continue;
			}
			$image_id = $next_id++;
			$color_space = 1 === $image['channels'] ? '/DeviceGray' : '/DeviceRGB';
			$objects[ $image_id ] = '<< /Type /XObject /Subtype /Image /Width ' . $image['width'] . ' /Height ' . $image['height'] . ' /ColorSpace ' . $color_space . ' /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $image['data'] ) . ">>\nstream\n" . $image['data'] . "\nendstream";
			$xobjects[ $name ] = $image_id;
		}

		$xobject_resource = '';
		if ( $xobjects ) {
			$pairs = array();
			foreach ( $xobjects as $name => $id ) {
				$pairs[] = '/' . $name . ' ' . $id . ' 0 R';
			}
			$xobject_resource = ' /XObject << ' . implode( ' ', $pairs ) . ' >>';
		}

		$page_ids = array();
		foreach ( $forms as $data ) {
			$content = self::request_page_content( $data, $xobjects );
			$content_id = $next_id++;
			$page_id = $next_id++;
			$page_ids[] = $page_id;
			$objects[ $content_id ] = '<< /Length ' . strlen( $content ) . ">>\nstream\n" . $content . "\nendstream";
			$objects[ $page_id ] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $page_width . ' ' . $page_height . '] /Resources << /Font << /F3 3 0 R /F4 4 0 R /F5 5 0 R /F6 6 0 R >>' . $xobject_resource . ' >> /Contents ' . $content_id . ' 0 R >>';
		}

		$kids = implode( ' ', array_map( static fn( int $id ): string => $id . ' 0 R', $page_ids ) );
		$objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count( $page_ids ) . ' >>';
		return self::assemble_pdf( $objects );
	}

	private static function request_page_content( array $data, array $xobjects ): string {
		$value = static function ( string $key ) use ( $data ): string {
			return isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? trim( (string) $data[ $key ] ) : '';
		};

		$content = "0 G\n0 g\n0.7 w\n";
		if ( isset( $xobjects['StateLogo'] ) ) {
			$content .= self::image_command( 'StateLogo', 59.0, 735.0, 61.4, 68.0 );
		}
		if ( isset( $xobjects['MunicipalityLogo'] ) ) {
			$content .= self::image_command( 'MunicipalityLogo', 480.0, 735.0, 53.1, 68.0 );
		}

		$content .= self::center_text( 'F4', 14.0, 150.0, 445.0, 792.0, 'Republika e Kosovës' );
		$content .= self::center_text( 'F4', 12.5, 135.0, 460.0, 773.0, 'Republika Kosova - Republic of Kosovo' );
		$content .= self::center_text( 'F4', 12.0, 150.0, 445.0, 748.0, 'Komuna e Prishtinës' );
		$content .= self::center_text( 'F4', 10.5, 135.0, 460.0, 731.0, 'Opština Priština - Municipality of Prishtina' );
		$content .= self::line_command( 45.0, 718.0, 550.0, 718.0 );

		$content .= self::center_report_title( 'F6', 12.0, 696.0, 'Kërkesa për pushim' );
		$content .= self::line_command( 225.0, 693.0, 370.0, 693.0 );
		$content .= self::center_report_title( 'F6', 14.0, 674.0, 'KOMUNA E PRISHTINËS' );

		$employee_info_width = max( 180.0, min( 300.0, (float) $value( 'EMPLOYEE_INFO_LINE_WIDTH' ) ) );
		$employee_name_label = 'Emri dhe mbiemri i punonjësit:';
		$position_label = 'Pozita:';
		$sector_label = 'Sektori/Njësia:';
		$content .= self::text_command( 'F6', 10.0, 50.0, 642.0, $employee_name_label );
		$content .= self::employee_info_command( 50.0 + self::estimate_text_width( $employee_name_label, 10.0 ) + 4.0, 641.0, $employee_info_width, $value( 'EMPLOYEE_NAME' ), 10.0, 'F6' );
		$content .= self::text_command( 'F6', 10.0, 50.0, 612.0, $position_label );
		$content .= self::employee_info_command( 50.0 + self::estimate_text_width( $position_label, 10.0 ) + 4.0, 611.0, $employee_info_width, $value( 'POSITION' ), 10.0, 'F6' );
		$content .= self::text_command( 'F6', 10.0, 50.0, 582.0, $sector_label );
		$content .= self::employee_info_command( 50.0 + self::estimate_text_width( $sector_label, 10.0 ) + 4.0, 581.0, $employee_info_width, $value( 'SECTOR' ), 10.0, 'F6' );

		$leave_rows = array(
			array( '1.', 'Pushim vjetor', 'ANNUAL_MARK', 552.0 ),
			array( '2.', 'Pushim mjekësor', 'MEDICAL_MARK', 536.0 ),
			array( '3.', 'Pushim për rast zie', 'BEREAVEMENT_MARK', 520.0 ),
			array( '4.', 'Pushim prindor', 'PARENTAL_MARK', 504.0 ),
		);
		foreach ( $leave_rows as $row ) {
			$content .= self::text_command( 'F5', 9.5, 55.0, $row[3], $row[0] );
			$content .= self::text_command( 'F5', 9.5, 82.0, $row[3], $row[1] );
			$content .= self::field_command( 220.0, $row[3] - 3.0, 245.0, $value( $row[2] ), 9.5, 'F5' );
		}

		$content .= self::text_command( 'F6', 10.0, 50.0, 474.0, 'Prej' );
		$content .= self::date_part_command( 88.0, 471.0, 36.0, $value( 'START_DAY' ) );
		$content .= self::text_command( 'F5', 10.0, 128.0, 474.0, '/' );
		$content .= self::date_part_command( 139.0, 471.0, 36.0, $value( 'START_MONTH' ) );
		$content .= self::text_command( 'F5', 10.0, 179.0, 474.0, '/' );
		$content .= self::date_part_command( 190.0, 471.0, 55.0, $value( 'START_YEAR' ) );
		$content .= self::text_command( 'F6', 10.0, 263.0, 474.0, 'deri më datën' );
		$content .= self::date_part_command( 365.0, 471.0, 36.0, $value( 'END_DAY' ) );
		$content .= self::text_command( 'F5', 10.0, 405.0, 474.0, '/' );
		$content .= self::date_part_command( 416.0, 471.0, 36.0, $value( 'END_MONTH' ) );
		$content .= self::text_command( 'F5', 10.0, 456.0, 474.0, '/' );
		$content .= self::date_part_command( 467.0, 471.0, 64.0, $value( 'END_YEAR' ) );

		$content .= self::text_command( 'F6', 10.0, 50.0, 443.0, 'Të rikthehet në punë më datën:' );
		$content .= self::date_part_command( 260.0, 440.0, 36.0, $value( 'RETURN_DAY' ) );
		$content .= self::text_command( 'F5', 10.0, 300.0, 443.0, '/' );
		$content .= self::date_part_command( 311.0, 440.0, 36.0, $value( 'RETURN_MONTH' ) );
		$content .= self::text_command( 'F5', 10.0, 351.0, 443.0, '/' );
		$content .= self::date_part_command( 362.0, 440.0, 64.0, $value( 'RETURN_YEAR' ) );

		$content .= self::text_command( 'F6', 10.0, 50.0, 410.0, 'Nënshkrimi i punonjësit' );
		$content .= self::line_command( 185.0, 407.0, 330.0, 407.0 );
		$content .= self::text_command( 'F6', 9.0, 350.0, 410.0, 'Data e paraqitjes së kërkesës' );
		$content .= self::field_command( 485.0, 407.0, 550.0, $value( 'SUBMITTED_DATE' ), 9.0, 'F6' );

		$content .= self::dashed_line_command( 45.0, 385.0, 550.0, 385.0 );
		$content .= self::text_command( 'F6', 11.0, 50.0, 365.0, 'Për mbikëqyrësin e drejtpërdrejtë' );
		$content .= self::line_command( 50.0, 362.0, 252.0, 362.0 );

		$content .= self::text_command( 'F6', 10.0, 50.0, 333.0, 'Pushim' );
		$content .= self::field_command( 95.0, 330.0, 340.0, $value( 'LEAVE_TYPE_LABEL' ), 10.0, 'F6' );
		$content .= self::center_text( 'F5', 7.5, 95.0, 340.0, 318.0, '(lloji i pushimit)' );
		$content .= self::text_command( 'F6', 9.5, 352.0, 333.0, 'i kërkuar nga punonjësi,' );

		$decision_rows = array(
			array( '1.', 'miratohet sipas kërkesës', 'APPROVED_MARK', 285.0 ),
			array( '2.', 'miratohet pjesërisht', 'PARTIAL_MARK', 268.0 ),
			array( '3.', 'refuzohet', 'REJECTED_MARK', 251.0 ),
		);
		foreach ( $decision_rows as $row ) {
			$content .= self::text_command( 'F6', 9.5, 55.0, $row[3], $row[0] );
			$content .= self::text_command( 'F6', 9.5, 82.0, $row[3], $row[1] );
			$content .= self::field_command( 470.0, $row[3] - 3.0, 535.0, $value( $row[2] ), 9.5, 'F6' );
		}

		$content .= self::dashed_line_command( 45.0, 228.0, 550.0, 228.0 );
		$content .= self::text_command( 'F6', 10.0, 50.0, 207.0, 'Arsyetimi i mbikëqyrësit' );
		$content .= self::text_command( 'F5', 9.0, 190.0, 207.0, 'për miratimin e pjesshëm ose refuzimin e kërkesës:' );

		$content .= self::reason_line_command( 50.0, 174.0, 545.0, $value( 'DECISION_REASON_1' ) );
		$content .= self::reason_line_command( 50.0, 153.0, 545.0, $value( 'DECISION_REASON_2' ) );
		$content .= self::reason_line_command( 50.0, 132.0, 545.0, $value( 'DECISION_REASON_3' ) );

		$content .= self::text_command( 'F6', 10.0, 50.0, 98.0, 'Nënshkrimi i mbikëqyrësit' );
		$content .= self::line_command( 195.0, 95.0, 330.0, 95.0 );
		$content .= self::text_command( 'F5', 9.5, 390.0, 98.0, 'Data' );
		$content .= self::field_command( 425.0, 95.0, 550.0, $value( 'DECISION_DATE' ), 9.5, 'F5' );

		$content .= self::text_command( 'F6', 8.5, 50.0, 58.0, 'Vërejtje:' );
		$content .= self::line_command( 50.0, 55.5, 92.0, 55.5 );
		$content .= self::text_command( 'F5', 8.2, 96.0, 58.0, 'Pas nënshkrimit nga mbikëqyrësi, formulari i përcillet Njësisë për Menaxhimin e Burimeve Njerëzore.' );
		return $content;
	}

	public static function render( string $title, array $lines ): string {
		$wrapped = array();
		foreach ( $lines as $line ) {
			$line = self::latinize( (string) $line );
			foreach ( self::wrap( $line, 92 ) as $piece ) {
				$wrapped[] = $piece;
			}
		}
		$chunks = array_chunk( $wrapped, 47 );
		if ( empty( $chunks ) ) {
			$chunks = array( array( '' ) );
		}

		$objects = array();
		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$page_ids = array();
		$font_id = 3;
		$objects[ $font_id ] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$next_id = 4;
		foreach ( $chunks as $page_number => $chunk ) {
			$page_id = $next_id++;
			$content_id = $next_id++;
			$page_ids[] = $page_id;
			$content = "BT\n/F1 16 Tf\n50 790 Td\n(" . self::escape( self::latinize( $title ) ) . ") Tj\n/F1 9 Tf\n0 -24 Td\n";
			foreach ( $chunk as $index => $line ) {
				if ( 0 !== $index ) {
					$content .= "0 -15 Td\n";
				}
				$content .= '(' . self::escape( $line ) . ") Tj\n";
			}
			$content .= "ET\nBT\n/F1 8 Tf\n500 28 Td\n(Page " . ( $page_number + 1 ) . ' of ' . count( $chunks ) . ") Tj\nET";
			$objects[ $content_id ] = '<< /Length ' . strlen( $content ) . ">>\nstream\n" . $content . "\nendstream";
			$objects[ $page_id ] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 $font_id 0 R >> >> /Contents $content_id 0 R >>";
		}
		$kids = implode( ' ', array_map( static fn( $id ) => $id . ' 0 R', $page_ids ) );
		$objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count( $page_ids ) . ' >>';
		ksort( $objects );

		$pdf = "%PDF-1.4\n";
		$offsets = array( 0 );
		foreach ( $objects as $id => $body ) {
			$offsets[ $id ] = strlen( $pdf );
			$pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$max_id = max( array_keys( $objects ) );
		$pdf .= "xref\n0 " . ( $max_id + 1 ) . "\n";
		$pdf .= "0000000000 65535 f \n";
		for ( $id = 1; $id <= $max_id; $id++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $id ] ?? 0 );
		}
		$pdf .= "trailer\n<< /Size " . ( $max_id + 1 ) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
		return $pdf;
	}


	private static function text_command( string $font, float $size, float $x, float $y, string $text ): string {
		if ( '' === trim( $text ) ) {
			return '';
		}
		return "BT\n/" . $font . ' ' . self::number( $size ) . " Tf\n1 0 0 1 " . self::number( $x ) . ' ' . self::number( $y ) . " Tm\n(" . self::escape( self::latinize( $text ) ) . ") Tj\nET\n";
	}

	private static function center_text( string $font, float $size, float $left, float $right, float $y, string $text ): string {
		$width = self::estimate_text_width( $text, $size );
		$x = $left + max( 0.0, ( $right - $left - $width ) / 2.0 );
		return self::text_command( $font, $size, $x, $y, $text );
	}

	/**
	 * Center the two fixed report titles against the physical A4 page midpoint.
	 * Core Helvetica-Bold glyph metrics avoid the average-character estimate
	 * used by ordinary field content, which can visibly shift uppercase titles.
	 */
	private static function center_report_title( string $font, float $size, float $y, string $text ): string {
		$page_width = 595.28;
		$width = self::report_title_width( $text, $size );
		$x = max( 0.0, ( $page_width - $width ) / 2.0 );
		return self::text_command( $font, $size, $x, $y, $text );
	}

	private static function report_title_width( string $text, float $size ): float {
		$widths = array(
			' ' => 278,
			'A' => 722,
			'E' => 667,
			'H' => 722,
			'I' => 278,
			'K' => 722,
			'M' => 833,
			'N' => 722,
			'O' => 778,
			'P' => 667,
			'R' => 722,
			'S' => 667,
			'T' => 611,
			'U' => 722,
			'a' => 556,
			'e' => 556,
			'h' => 611,
			'i' => 278,
			'k' => 556,
			'm' => 889,
			'p' => 611,
			'r' => 389,
			's' => 556,
			'u' => 611,
			'Ë' => 667,
			'ë' => 556,
		);
		$units = 0;
		$characters = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		foreach ( false === $characters ? array() : $characters as $character ) {
			$units += $widths[ $character ] ?? 556;
		}
		return ( $units / 1000 ) * $size;
	}

	private static function employee_info_command( float $x, float $y, float $width, string $text, float $size, string $font ): string {
		$right = $x + $width;
		$command = self::thin_line_command( $x, $y - 2.0, $right, $y - 2.0 );
		if ( '' === trim( $text ) ) {
			return $command;
		}

		$size = self::fit_font_size( $text, $size, max( 1.0, $width - 18.0 ), 7.0 );
		$text_width = self::estimate_text_width( $text, $size );
		$text_x = $x + max( 9.0, ( $width - $text_width ) / 2.0 );
		return $command . self::text_command( $font, $size, $text_x, $y + 1.0, $text );
	}

	private static function field_command( float $left, float $y, float $right, string $text, float $size, string $font ): string {
		$command = self::line_command( $left, $y, $right, $y );
		if ( '' === trim( $text ) ) {
			return $command;
		}
		$size = self::fit_font_size( $text, $size, max( 1.0, $right - $left - 6.0 ), 6.5 );
		$width = self::estimate_text_width( $text, $size );
		$x = $left + max( 3.0, ( $right - $left - $width ) / 2.0 );
		return $command . self::text_command( $font, $size, $x, $y + 2.0, $text );
	}

	private static function date_part_command( float $left, float $y, float $width, string $text ): string {
		return self::field_command( $left, $y, $left + $width, $text, 10.0, 'F6' );
	}

	private static function reason_line_command( float $left, float $y, float $right, string $text ): string {
		$command = self::line_command( $left, $y, $right, $y );
		if ( '' === trim( $text ) ) {
			return $command;
		}
		$size = self::fit_font_size( $text, 8.2, max( 1.0, $right - $left - 4.0 ), 6.5 );
		return $command . self::text_command( 'F5', $size, $left + 2.0, $y + 3.0, $text );
	}

	private static function line_command( float $x1, float $y1, float $x2, float $y2 ): string {
		return self::number( $x1 ) . ' ' . self::number( $y1 ) . ' m ' . self::number( $x2 ) . ' ' . self::number( $y2 ) . " l S\n";
	}

	private static function thin_line_command( float $x1, float $y1, float $x2, float $y2 ): string {
		return "q\n0.4 w\n" . self::line_command( $x1, $y1, $x2, $y2 ) . "Q\n";
	}

	private static function dashed_line_command( float $x1, float $y1, float $x2, float $y2 ): string {
		return "[1 3] 0 d\n" . self::line_command( $x1, $y1, $x2, $y2 ) . "[] 0 d\n";
	}

	private static function image_command( string $name, float $x, float $y, float $width, float $height ): string {
		return "q\n" . self::number( $width ) . ' 0 0 ' . self::number( $height ) . ' ' . self::number( $x ) . ' ' . self::number( $y ) . " cm\n/" . $name . " Do\nQ\n";
	}

	private static function fit_font_size( string $text, float $preferred, float $max_width, float $minimum ): float {
		$size = $preferred;
		while ( $size > $minimum && self::estimate_text_width( $text, $size ) > $max_width ) {
			$size -= 0.25;
		}
		return max( $minimum, $size );
	}

	private static function estimate_text_width( string $text, float $size ): float {
		$encoded = self::latinize( $text );
		return strlen( $encoded ) * $size * 0.49;
	}

	private static function jpeg_data( string $path ): ?array {
		if ( '' === $path || ! is_readable( $path ) ) {
			return null;
		}
		$data = file_get_contents( $path );
		$info = function_exists( 'getimagesize' ) ? getimagesize( $path ) : false;
		if ( false === $data || false === $info || IMAGETYPE_JPEG !== ( $info[2] ?? 0 ) ) {
			return null;
		}
		return array(
			'data'     => $data,
			'width'    => (int) $info[0],
			'height'   => (int) $info[1],
			'channels' => (int) ( $info['channels'] ?? 3 ),
		);
	}

	private static function assemble_pdf( array $objects ): string {
		ksort( $objects );
		$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array( 0 );
		foreach ( $objects as $id => $body ) {
			$offsets[ $id ] = strlen( $pdf );
			$pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$max_id = max( array_keys( $objects ) );
		$pdf .= "xref\n0 " . ( $max_id + 1 ) . "\n";
		$pdf .= "0000000000 65535 f \n";
		for ( $id = 1; $id <= $max_id; $id++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $id ] ?? 0 );
		}
		$pdf .= "trailer\n<< /Size " . ( $max_id + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
		return $pdf;
	}

	private static function number( float $value ): string {
		return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
	}

	private static function wrap( string $line, int $width ): array {
		if ( '' === $line ) {
			return array( ' ' );
		}
		return explode( "\n", wordwrap( $line, $width, "\n", true ) );
	}

	private static function escape( string $text ): string {
		return str_replace( array( '\\', '(', ')', "\r", "\n" ), array( '\\\\', '\\(', '\\)', '', ' ' ), $text );
	}

	private static function latinize( string $text ): string {
		$converted = function_exists( 'iconv' ) ? iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text ) : $text;
		return false === $converted ? preg_replace( '/[^\x20-\x7E]/', '?', $text ) : $converted;
	}
}
