<?php
/**
 * The certificate list, shared by the admin screen and the front-end manager.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Handles list actions and renders the table.
 */
final class List_View {

	/**
	 * Render the list.
	 *
	 * @param bool $is_frontend Whether this is the shortcode context.
	 */
	public static function render( bool $is_frontend = false ): void {
		if ( ! Access::can_manage() ) {
			Frontend::render_denied( $is_frontend );

			return;
		}

		$is_admin = Access::is_supervisor();
		$notices  = array_merge( self::handle_bulk_action(), self::handle_single_delete(), self::import_notices() );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$search       = isset( $_GET['d_search'] ) ? sanitize_text_field( wp_unslash( $_GET['d_search'] ) ) : '';
		$view_user_id = isset( $_GET['view_user_id'] ) ? absint( wp_unslash( $_GET['view_user_id'] ) ) : 0;
		$orderby      = isset( $_GET['d_orderby'] ) ? sanitize_key( wp_unslash( $_GET['d_orderby'] ) ) : 'created_at';
		$order        = isset( $_GET['d_order'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['d_order'] ) ) ) : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! array_key_exists( $orderby, Fields::sortable() ) ) {
			$orderby = 'created_at';
		}

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		if ( $is_admin ) {
			$owner = $view_user_id > 0 ? $view_user_id : null;
		} else {
			$owner        = get_current_user_id();
			$view_user_id = 0;
		}

		// Administrators land on a per-user summary until they pick a user or search.
		$show_user_groups = $is_admin && 0 === $view_user_id && '' === $search;

		$page_var  = $is_frontend ? 'd_paged' : 'paged';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$paged     = isset( $_GET[ $page_var ] ) ? max( 1, absint( wp_unslash( $_GET[ $page_var ] ) ) ) : 1;
		$base_url  = self::base_url( $is_frontend, $view_user_id, $search );

		$result = $show_user_groups
			? array(
				'rows'  => array(),
				'total' => 0,
				'pages' => 0,
			)
			: Repository::paginate(
				array(
					'owner'   => $owner,
					'search'  => $search,
					'orderby' => $orderby,
					'order'   => $order,
					'page'    => $paged,
				)
			);

		Assets::enqueue_list(
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'isAdmin' => $is_admin,
				'i18n'    => array(
					'confirmDelete'   => __( 'Fshi rekordet e zgjedhura?', 'deftese-pro' ),
					'confirmMove'     => __( 'Lëviz rekordet e zgjedhura tek ky përdorues?', 'deftese-pro' ),
					'pickUser'        => __( 'Ju lutem zgjidhni një përdorues!', 'deftese-pro' ),
					'confirmRowTrash' => __( 'Fshi këtë dëftesë?', 'deftese-pro' ),
					/* translators: 1: files done, 2: files total. */
					'importProgress'  => __( 'Duke importuar… (%1$d / %2$d)', 'deftese-pro' ),
					'importButton'    => __( 'Importo Skedarët', 'deftese-pro' ),
				),
			)
		);

		view(
			'list',
			array(
				'notices'          => $notices,
				'is_frontend'      => $is_frontend,
				'is_admin'         => $is_admin,
				'search'           => $search,
				'view_user_id'     => $view_user_id,
				'orderby'          => $orderby,
				'order'            => $order,
				'paged'            => $paged,
				'page_var'         => $page_var,
				'base_url'         => $base_url,
				'rows'             => $result['rows'],
				'total'            => $result['total'],
				'total_pages'      => $result['pages'],
				'show_user_groups' => $show_user_groups,
				'user_groups'      => $show_user_groups ? Repository::counts_by_user() : array(),
			)
		);
	}

	/**
	 * The canonical list URL, carrying the active filters.
	 *
	 * @param bool   $is_frontend  Shortcode context.
	 * @param int    $view_user_id Filtered author.
	 * @param string $search       Search term.
	 * @return string URL.
	 */
	public static function base_url( bool $is_frontend, int $view_user_id = 0, string $search = '' ): string {
		$url = $is_frontend
			? add_query_arg( 'd_view', 'list', Access::manager_url() )
			: admin_url( 'admin.php?page=deftese-manager' );

		if ( $view_user_id > 0 ) {
			$url = add_query_arg( 'view_user_id', $view_user_id, $url );
		}

		if ( '' !== $search ) {
			$url = add_query_arg( 'd_search', rawurlencode( $search ), $url );
		}

		return $url;
	}

	/**
	 * Apply a posted bulk action.
	 *
	 * @return array<int,array{tone:string,message:string}> Notices to display.
	 */
	private static function handle_bulk_action(): array {
		if ( ! isset( $_POST['deftese_bulk_apply'], $_POST['deftese_bulk_nonce'] ) ) {
			return array();
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['deftese_bulk_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'deftese_bulk_action' ) || ! Access::can_manage() ) {
			return array();
		}

		$action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$ids    = isset( $_POST['cert_ids'] )
			? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['cert_ids'] ) )
			: array();

		if ( ! $ids ) {
			return array();
		}

		if ( 'delete' === $action ) {
			$removed = Repository::delete_many( $ids, Access::query_owner() );

			return array(
				array(
					'tone'    => 'success',
					/* translators: %d: number of rows. */
					'message' => sprintf( _n( '%d rekord u fshi.', '%d rekorde u fshinë.', $removed, 'deftese-pro' ), $removed ),
				),
			);
		}

		if ( 'move' === $action && Access::is_supervisor() ) {
			$target = isset( $_POST['deftese_target_user'] ) ? absint( wp_unslash( $_POST['deftese_target_user'] ) ) : 0;

			if ( $target <= 0 || ! get_userdata( $target ) ) {
				return array(
					array(
						'tone'    => 'danger',
						'message' => __( 'Përdoruesi i zgjedhur nuk ekziston.', 'deftese-pro' ),
					),
				);
			}

			$moved = Repository::reassign( $ids, $target );

			return array(
				array(
					'tone'    => 'success',
					/* translators: %d: number of rows. */
					'message' => sprintf( _n( '%d rekord u transferua.', '%d rekorde u transferuan.', $moved, 'deftese-pro' ), $moved ),
				),
			);
		}

		return array();
	}

	/**
	 * Apply a single-row delete link.
	 *
	 * @return array<int,array{tone:string,message:string}> Notices to display.
	 */
	private static function handle_single_delete(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
		if ( ! isset( $_GET['action'], $_GET['id'], $_GET['_wpnonce'] ) || 'delete' !== $_GET['action'] ) {
			return array();
		}

		$id    = sanitize_text_field( wp_unslash( $_GET['id'] ) );
		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! Access::can_manage() || ! wp_verify_nonce( $nonce, 'deftese_delete_' . md5( $id ) ) ) {
			return array();
		}

		if ( ! Repository::delete( $id, Access::query_owner() ) ) {
			return array(
				array(
					'tone'    => 'danger',
					'message' => __( 'Fshirja dështoi ose dëftesa nuk ju përket juve.', 'deftese-pro' ),
				),
			);
		}

		return array(
			array(
				'tone'    => 'success',
				'message' => __( 'U fshi me sukses.', 'deftese-pro' ),
			),
		);
	}

	/**
	 * Turn the importer's redirect parameters into notices.
	 *
	 * @return array<int,array{tone:string,message:string}> Notices to display.
	 */
	private static function import_notices(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag.
		if ( ! isset( $_GET['deftese_msg'] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag.
		$raw      = sanitize_text_field( wp_unslash( $_GET['deftese_msg'] ) );
		$imported = 0;
		$errors   = 0;

		foreach ( explode( '|', $raw ) as $part ) {
			if ( 0 === strpos( $part, 'imported_' ) ) {
				$imported = (int) substr( $part, strlen( 'imported_' ) );
			}

			if ( 0 === strpos( $part, 'errors_' ) ) {
				$errors = (int) substr( $part, strlen( 'errors_' ) );
			}
		}

		$notices = array();

		if ( $imported > 0 ) {
			$notices[] = array(
				'tone'    => 'success',
				/* translators: %d: number of files. */
				'message' => sprintf( _n( '%d skedar CSV u importua me sukses.', '%d skedarë CSV u importuan me sukses.', $imported, 'deftese-pro' ), $imported ),
			);
		}

		if ( $errors > 0 ) {
			$notices[] = array(
				'tone'    => 'warning',
				/* translators: %d: number of files. */
				'message' => sprintf( _n( '%d skedar pati probleme (ose nuk ju përket juve).', '%d skedarë patën probleme (ose nuk ju përkasin juve).', $errors, 'deftese-pro' ), $errors ),
			);
		}

		return $notices;
	}
}
