<?php
/**
 * The A4 certificate editor.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Prepares data for, and renders, the WYSIWYG certificate sheet.
 */
final class Editor {

	/**
	 * Render the editor.
	 *
	 * @param string $cert_id     Certificate to open, or an empty string for a blank sheet.
	 * @param bool   $is_frontend Whether this is the shortcode context.
	 */
	public static function render( string $cert_id = '', bool $is_frontend = false ): void {
		if ( ! Access::can_manage() ) {
			Frontend::render_denied( $is_frontend );

			return;
		}

		$cert       = null;
		$neighbours = array(
			'prev' => null,
			'next' => null,
		);

		if ( '' !== $cert_id ) {
			$cert = Repository::find( $cert_id );

			if ( $cert && ! Access::owns( $cert ) ) {
				view(
					'notice',
					array(
						'tone'    => 'danger',
						'message' => __( 'Ndalohet aksesi. Kjo dëftesë është regjistruar nga një përdorues tjetër.', 'deftese-pro' ),
					)
				);

				return;
			}

			if ( $cert ) {
				$decoded             = json_decode( (string) ( $cert['grades_json'] ?? '' ), true );
				$cert['grades_json'] = is_array( $decoded ) ? $decoded : array();
				$neighbours          = Repository::neighbours( $cert_id, Access::query_owner() );
			}
		}

		$grades = ( $cert && ! empty( $cert['grades_json'] ) && is_array( $cert['grades_json'] ) )
			? $cert['grades_json']
			: Fields::default_grades();

		Assets::enqueue_editor(
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( Ajax::NONCE ),
				'certId'     => $cert_id,
				'grades'     => array_values( $grades ),
				'csvMap'     => Csv_Map::to_array(),
				'totalRow'   => Fields::TOTAL_ROW_SUBJECT,
				'gradeKeys'  => Fields::grade_classes(),
				'i18n'       => self::strings(),
			)
		);

		view(
			'editor',
			array(
				'cert'        => $cert,
				'cert_id'     => $cert_id,
				'grades'      => $grades,
				'is_frontend' => $is_frontend,
				'prev_url'    => self::neighbour_url( $neighbours['prev'], $is_frontend ),
				'next_url'    => self::neighbour_url( $neighbours['next'], $is_frontend ),
				'prev_id'     => $neighbours['prev'],
				'next_id'     => $neighbours['next'],
			)
		);
	}

	/**
	 * Build the link to a neighbouring certificate.
	 *
	 * @param string|null $id          Neighbour id.
	 * @param bool        $is_frontend Whether this is the shortcode context.
	 * @return string URL, or "#" when there is no neighbour.
	 */
	private static function neighbour_url( ?string $id, bool $is_frontend ): string {
		if ( null === $id || '' === $id ) {
			return '#';
		}

		if ( $is_frontend ) {
			return add_query_arg(
				array(
					'd_view' => 'edit',
					'id'     => $id,
				),
				remove_query_arg( array( 'd_view', 'id' ) )
			);
		}

		return add_query_arg(
			array(
				'page' => 'deftese-new',
				'id'   => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Translatable strings handed to editor.js.
	 *
	 * @return array<string,string> Strings.
	 */
	private static function strings(): array {
		return array(
			'editOn'          => __( 'Mbyll Redaktimin', 'deftese-pro' ),
			'editOff'         => __( 'Ndrysho', 'deftese-pro' ),
			'editingActive'   => __( 'Modaliteti i redaktimit është aktiv.', 'deftese-pro' ),
			'editingClosed'   => __( 'Redaktimi u mbyll.', 'deftese-pro' ),
			'saving'          => __( 'Duke ruajtur…', 'deftese-pro' ),
			'loading'         => __( 'Duke ngarkuar…', 'deftese-pro' ),
			'loaded'          => __( 'U ngarkua.', 'deftese-pro' ),
			'genericError'    => __( 'Gabim.', 'deftese-pro' ),
			'networkError'    => __( 'Gabim lidhjeje.', 'deftese-pro' ),
			'loadError'       => __( 'Gabim gjatë ngarkimit.', 'deftese-pro' ),
			'importError'     => __( 'Gabim gjatë importimit.', 'deftese-pro' ),
			'importSuccess'   => __( 'CSV u importua me sukses.', 'deftese-pro' ),
			'confirmSave'     => __( 'Ruaj Ndryshimet', 'deftese-pro' ),
			/* translators: %s: registry number. */
			'confirmSaveBody' => __( 'Do të përditësohet dëftesa: %s. Vazhdo?', 'deftese-pro' ),
			'confirmNew'      => __( 'Ruaj si Dëftesë e Re', 'deftese-pro' ),
			'confirmNewBody'  => __( 'Do të krijohet një rekord i ri. Vazhdo?', 'deftese-pro' ),
			'confirmImport'   => __( 'Importo Dëftesën', 'deftese-pro' ),
			/* translators: %s: file name. */
			'confirmImportBody' => __( 'Dëftesa aktuale do të zëvendësohet me të dhënat e skedarit "%s". Vazhdo?', 'deftese-pro' ),
			'badgeEmpty'      => __( 'E Paredaktuar', 'deftese-pro' ),
			/* translators: %s: registry number. */
			'badgeFilled'     => __( 'Libri Amë: %s', 'deftese-pro' ),
			'fullscreenOn'    => __( 'Dil nga Fullscreen', 'deftese-pro' ),
			'fullscreenOff'   => __( 'Fullscreen', 'deftese-pro' ),
			'newSubject'      => __( 'Lëndë e re', 'deftese-pro' ),
			'newCategory'     => __( 'KATEGORI E RE', 'deftese-pro' ),
			/* translators: %d: expected total. */
			'sumHint'         => __( 'Shuma e saktë është: %d', 'deftese-pro' ),
			'rowUp'           => __( 'Lart', 'deftese-pro' ),
			'rowDown'         => __( 'Poshtë', 'deftese-pro' ),
			'rowRemove'       => __( 'Fshi', 'deftese-pro' ),
		);
	}
}
