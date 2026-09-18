<?php
/**
 * Certificate list: importer, filters, bulk actions and the table.
 *
 * @package DeftesePro
 *
 * @var array  $notices          Notices to display.
 * @var bool   $is_frontend      Shortcode context.
 * @var bool   $is_admin         Whether the viewer supervises every record.
 * @var string $search           Active search term.
 * @var int    $view_user_id     Author filter.
 * @var string $orderby          Active sort column.
 * @var string $order            Active sort direction.
 * @var int    $paged            Current page.
 * @var string $page_var         Pagination query variable.
 * @var string $base_url         List URL carrying the active filters.
 * @var array  $rows             Certificates on this page.
 * @var int    $total            Total matching certificates.
 * @var int    $total_pages      Number of pages.
 * @var bool   $show_user_groups Whether to render the per-user summary.
 * @var array  $user_groups      Per-user counts.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a complete sortable column header.
 *
 * aria-sort belongs on the header cell itself, so this emits the whole <th>
 * rather than just the link inside it.
 *
 * @param string $column   Column key.
 * @param string $label    Header label.
 * @param string $base_url List URL.
 * @param string $orderby  Active column.
 * @param string $order    Active direction.
 * @return string Header markup.
 */
$dp_sort_th = static function ( string $column, string $label, string $base_url, string $orderby, string $order ): string {
	$is_active = ( $orderby === $column );
	$next      = ( $is_active && 'ASC' === $order ) ? 'DESC' : 'ASC';

	$url = add_query_arg(
		array(
			'd_orderby' => $column,
			'd_order'   => $next,
		),
		$base_url
	);

	return sprintf(
		'<th scope="col" aria-sort="%s"><a class="dp-sort%s" href="%s">%s<span class="dp-sort__arrow" aria-hidden="true"></span></a></th>',
		esc_attr( $is_active ? ( 'ASC' === $order ? 'ascending' : 'descending' ) : 'none' ),
		$is_active ? ' is-active is-' . strtolower( $order ) : '',
		esc_url( $url ),
		esc_html( $label )
	);
};

$dp_back_url = $is_frontend
	? add_query_arg( 'd_view', 'list', DeftesePro\Access::manager_url() )
	: admin_url( 'admin.php?page=deftese-manager' );
?>

<?php foreach ( $notices as $dp_notice ) : ?>
	<?php DeftesePro\view( 'notice', $dp_notice ); ?>
<?php endforeach; ?>

<section class="dp-card dp-card--import">
	<header class="dp-card__head">
		<h2 class="dp-card__title"><?php esc_html_e( 'Importo Skedarët (CSV Bulk)', 'deftese-pro' ); ?></h2>
		<p class="dp-card__hint"><?php esc_html_e( 'Mund të zgjidhni 100+ skedarë njëherësh. Sistemi do t\'i ngarkojë të sigurt në prapavijë.', 'deftese-pro' ); ?></p>
	</header>

	<form class="dp-card__body dp-import" id="deftese-import-form"
		data-frontend="<?php echo $is_frontend ? '1' : '0'; ?>">
		<?php wp_nonce_field( 'deftese_import_action', 'deftese_import_nonce' ); ?>

		<div class="dp-field">
			<label class="dp-label" for="deftese-import-files"><?php esc_html_e( 'Skedarët CSV', 'deftese-pro' ); ?></label>
			<input class="dp-file" type="file" id="deftese-import-files" name="deftese_import_files[]" accept=".csv" multiple required>
		</div>

		<label class="dp-checkbox">
			<input type="checkbox" name="overwrite" value="1" checked>
			<span><?php esc_html_e( 'Zëvendëso rekordet ekzistuese (nëse Libri amë përputhet)', 'deftese-pro' ); ?></span>
		</label>

		<button type="submit" class="dp-btn dp-btn--primary" id="btn-bulk-import"><?php esc_html_e( 'Importo Skedarët', 'deftese-pro' ); ?></button>
		<p class="dp-import__progress" id="deftese-import-progress" role="status" aria-live="polite"></p>
	</form>
</section>

<form class="dp-searchbar" method="get">
	<?php if ( $is_frontend ) : ?>
		<input type="hidden" name="d_view" value="list">
		<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Preserving read-only routing args. ?>
		<?php if ( isset( $_GET['page_id'] ) ) : ?>
			<input type="hidden" name="page_id" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['page_id'] ) ) ); ?>">
		<?php endif; ?>
		<?php if ( isset( $_GET['p'] ) ) : ?>
			<input type="hidden" name="p" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['p'] ) ) ); ?>">
		<?php endif; ?>
		<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>
	<?php else : ?>
		<input type="hidden" name="page" value="deftese-manager">
	<?php endif; ?>

	<input type="hidden" name="d_orderby" value="<?php echo esc_attr( $orderby ); ?>">
	<input type="hidden" name="d_order" value="<?php echo esc_attr( $order ); ?>">

	<?php if ( $view_user_id > 0 ) : ?>
		<input type="hidden" name="view_user_id" value="<?php echo esc_attr( (string) $view_user_id ); ?>">
	<?php endif; ?>

	<label class="dp-label dp-u-visually-hidden" for="deftese-search"><?php esc_html_e( 'Kërko dëftesa', 'deftese-pro' ); ?></label>
	<input class="dp-input dp-searchbar__input" type="search" id="deftese-search" name="d_search"
		value="<?php echo esc_attr( $search ); ?>"
		placeholder="<?php esc_attr_e( 'Kërko me emër, shkollë ose libër amë…', 'deftese-pro' ); ?>">

	<button class="dp-btn dp-btn--neutral" type="submit"><?php esc_html_e( 'Kërko', 'deftese-pro' ); ?></button>

	<?php if ( '' !== $search ) : ?>
		<a class="dp-link dp-link--danger" href="<?php echo esc_url( remove_query_arg( 'd_search', $base_url ) ); ?>"><?php esc_html_e( 'Fshij filtrin', 'deftese-pro' ); ?></a>
	<?php endif; ?>
</form>

<?php if ( $show_user_groups ) : ?>

	<h2 class="dp-section-title"><?php esc_html_e( 'Dëftesat sipas Përdoruesve', 'deftese-pro' ); ?></h2>

	<div class="dp-table-wrap">
		<table class="dp-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Përdoruesi', 'deftese-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Username', 'deftese-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Dëftesa të Regjistruara', 'deftese-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Veprime', 'deftese-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $user_groups ) : ?>
					<?php foreach ( $user_groups as $dp_group ) : ?>
						<?php
						$dp_author = $dp_group->display_name ? $dp_group->display_name : $dp_group->user_login;

						if ( ! $dp_author ) {
							/* translators: %d: user id. */
							$dp_author = sprintf( __( 'ID %d', 'deftese-pro' ), (int) $dp_group->created_by );
						}

						$dp_view_url = add_query_arg( 'view_user_id', (int) $dp_group->created_by, $dp_back_url );
						?>
						<tr>
							<td><strong><?php echo esc_html( $dp_author ); ?></strong></td>
							<td class="dp-table__muted"><?php echo esc_html( $dp_group->user_login ? $dp_group->user_login : '—' ); ?></td>
							<td><span class="dp-badge"><?php echo esc_html( (string) (int) $dp_group->cert_count ); ?></span></td>
							<td><a class="dp-btn dp-btn--tiny dp-btn--soft" href="<?php echo esc_url( $dp_view_url ); ?>"><?php esc_html_e( 'Shiko dhe Menaxho', 'deftese-pro' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td class="dp-table__empty" colspan="4"><?php esc_html_e( 'Nuk ka dëftesa të regjistruara.', 'deftese-pro' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

<?php else : ?>

	<?php if ( $is_admin && $view_user_id > 0 ) : ?>
		<p><a class="dp-btn dp-btn--tiny dp-btn--neutral" href="<?php echo esc_url( $dp_back_url ); ?>"><?php esc_html_e( '← Kthehu te Lista e Përdoruesve', 'deftese-pro' ); ?></a></p>
	<?php endif; ?>

	<form method="post" id="deftese-list-form">
		<?php wp_nonce_field( 'deftese_bulk_action', 'deftese_bulk_nonce' ); ?>

		<div class="dp-toolbar-row">
			<label class="dp-label dp-u-visually-hidden" for="bulk-action-selector"><?php esc_html_e( 'Veprimet masive', 'deftese-pro' ); ?></label>
			<select class="dp-select" name="bulk_action" id="bulk-action-selector">
				<option value="">— <?php esc_html_e( 'Veprimet masive', 'deftese-pro' ); ?> —</option>
				<option value="delete"><?php esc_html_e( 'Fshi', 'deftese-pro' ); ?></option>
				<?php if ( $is_admin ) : ?>
					<option value="move"><?php esc_html_e( 'Lëviz tek Përdoruesi', 'deftese-pro' ); ?></option>
				<?php endif; ?>
			</select>

			<?php if ( $is_admin ) : ?>
				<label class="dp-label dp-u-visually-hidden" for="deftese-target-user"><?php esc_html_e( 'Përdoruesi i destinacionit', 'deftese-pro' ); ?></label>
				<select class="dp-select" name="deftese_target_user" id="deftese-target-user" hidden>
					<option value="">— <?php esc_html_e( 'Zgjidh Përdoruesin', 'deftese-pro' ); ?> —</option>
					<?php foreach ( get_users( array( 'fields' => array( 'ID', 'display_name', 'user_login' ) ) ) as $dp_user ) : ?>
						<option value="<?php echo esc_attr( (string) $dp_user->ID ); ?>">
							<?php echo esc_html( $dp_user->display_name . ' (' . $dp_user->user_login . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<button class="dp-btn dp-btn--neutral" type="submit" name="deftese_bulk_apply" value="1"><?php esc_html_e( 'Apliko', 'deftese-pro' ); ?></button>

			<p class="dp-toolbar-row__count">
				<?php
				printf(
					/* translators: %s: number of certificates. */
					esc_html( _n( '%s dëftesë gjithsej', '%s dëftesa gjithsej', $total, 'deftese-pro' ) ),
					'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>'
				);
				?>
			</p>
		</div>

		<div class="dp-table-wrap">
			<table class="dp-table dp-table--records">
				<thead>
					<tr>
						<th scope="col" class="dp-table__check">
							<label class="dp-u-visually-hidden" for="deftese-select-all"><?php esc_html_e( 'Zgjidh të gjitha', 'deftese-pro' ); ?></label>
							<input type="checkbox" id="deftese-select-all">
						</th>
						<?php echo $dp_sort_th( 'registry_no', __( 'Nr. Librit Amë', 'deftese-pro' ), $base_url, $orderby, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>
						<?php echo $dp_sort_th( 'student_name', __( 'Nxënësi', 'deftese-pro' ), $base_url, $orderby, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $dp_sort_th( 'school_name', __( 'Shkolla', 'deftese-pro' ), $base_url, $orderby, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $dp_sort_th( 'school_year', __( 'Viti shkollor', 'deftese-pro' ), $base_url, $orderby, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $dp_sort_th( 'created_at', __( 'Ruajtur më', 'deftese-pro' ), $base_url, $orderby, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<th scope="col"><?php esc_html_e( 'Veprime', 'deftese-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $rows ) : ?>
						<?php foreach ( $rows as $dp_row ) : ?>
							<?php
							if ( $is_frontend ) {
								$dp_edit_url = add_query_arg(
									array(
										'd_view' => 'edit',
										'id'     => $dp_row->id,
									),
									remove_query_arg( array( 'd_view', 'id' ) )
								);

								$dp_delete_url = wp_nonce_url(
									add_query_arg(
										array(
											'd_view' => 'list',
											'action' => 'delete',
											'id'     => $dp_row->id,
										),
										remove_query_arg( array( 'd_view', 'action', 'id' ) )
									),
									'deftese_delete_' . md5( (string) $dp_row->id )
								);
							} else {
								$dp_edit_url = add_query_arg(
									array(
										'page' => 'deftese-new',
										'id'   => $dp_row->id,
									),
									admin_url( 'admin.php' )
								);

								$dp_delete_url = wp_nonce_url(
									add_query_arg(
										array(
											'page'   => 'deftese-manager',
											'action' => 'delete',
											'id'     => $dp_row->id,
										),
										admin_url( 'admin.php' )
									),
									'deftese_delete_' . md5( (string) $dp_row->id )
								);
							}

							$dp_saved = strtotime( (string) $dp_row->created_at );
							?>
							<tr>
								<td class="dp-table__check">
									<label class="dp-u-visually-hidden" for="dp-pick-<?php echo esc_attr( md5( (string) $dp_row->id ) ); ?>">
										<?php
										printf(
											/* translators: %s: student name. */
											esc_html__( 'Zgjidh dëftesën e %s', 'deftese-pro' ),
											esc_html( $dp_row->student_name )
										);
										?>
									</label>
									<input type="checkbox" id="dp-pick-<?php echo esc_attr( md5( (string) $dp_row->id ) ); ?>" name="cert_ids[]" value="<?php echo esc_attr( (string) $dp_row->id ); ?>">
								</td>
								<td><strong><?php echo esc_html( $dp_row->registry_no ); ?></strong></td>
								<td><strong><?php echo esc_html( $dp_row->student_name ); ?></strong></td>
								<td class="dp-table__muted"><?php echo esc_html( $dp_row->school_name ); ?></td>
								<td class="dp-table__muted"><?php echo esc_html( $dp_row->school_year ); ?></td>
								<td class="dp-table__stamp">
									<?php echo $dp_saved ? esc_html( date_i18n( 'd/m/Y H:i', $dp_saved ) ) : '—'; ?>
								</td>
								<td class="dp-table__actions">
									<a class="dp-btn dp-btn--tiny dp-btn--soft" href="<?php echo esc_url( $dp_edit_url ); ?>"><?php esc_html_e( 'Ndrysho', 'deftese-pro' ); ?></a>
									<a class="dp-btn dp-btn--tiny dp-btn--soft-danger" href="<?php echo esc_url( $dp_delete_url ); ?>" data-dp-confirm="row"><?php esc_html_e( 'Fshi', 'deftese-pro' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td class="dp-table__empty" colspan="7"><?php esc_html_e( 'Nuk u gjet asnjë dëftesë.', 'deftese-pro' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</form>

	<?php if ( $total_pages > 1 ) : ?>
		<nav class="dp-pagination" aria-label="<?php esc_attr_e( 'Faqet e listës', 'deftese-pro' ); ?>">
			<?php
			echo wp_kses_post(
				(string) paginate_links(
					array(
						'base'    => add_query_arg(
							$page_var,
							'%#%',
							add_query_arg(
								array(
									'd_orderby' => $orderby,
									'd_order'   => $order,
								),
								$base_url
							)
						),
						'format'  => '',
						'current' => $paged,
						'total'   => $total_pages,
					)
				)
			);
			?>
		</nav>
	<?php endif; ?>

<?php endif; ?>
