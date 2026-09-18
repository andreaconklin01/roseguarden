<?php
/**
 * wp-admin: categorised overview.
 *
 * @package DeftesePro
 *
 * @var array  $rows      All overview rows.
 * @var array  $groups    Grouped rows.
 * @var array  $groupings Available groupings.
 * @var string $group_by  Active grouping key.
 * @var array  $stats     Headline counters.
 * @var string $base_url  Screen URL without the grouping args.
 */

defined( 'ABSPATH' ) || exit;

$dp_cards = array(
	array( 'certificates', __( 'Dëftesa gjithsej', 'deftese-pro' ), 'a' ),
	array( 'users', __( 'Përdorues aktivë', 'deftese-pro' ), 'b' ),
	array( 'schools', __( 'Shkolla', 'deftese-pro' ), 'c' ),
	array( 'years', __( 'Vite shkollore', 'deftese-pro' ), 'd' ),
	array( 'teachers', __( 'Kujdestarë', 'deftese-pro' ), 'e' ),
);
?>
<div class="wrap dp-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Pasqyra e Dëftesave — Sipas Kategorive', 'deftese-pro' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=deftese-manager' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Kthehu te Lista', 'deftese-pro' ); ?></a>
	<hr class="wp-header-end">

	<div class="dp-app dp-app--embedded">
		<div class="dp-stats">
			<?php foreach ( $dp_cards as $dp_card ) : ?>
				<div class="dp-stat dp-stat--<?php echo esc_attr( $dp_card[2] ); ?>">
					<p class="dp-stat__num"><?php echo esc_html( number_format_i18n( $stats[ $dp_card[0] ] ) ); ?></p>
					<p class="dp-stat__label"><?php echo esc_html( $dp_card[1] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>

		<nav class="dp-tabs" aria-label="<?php esc_attr_e( 'Grupimi', 'deftese-pro' ); ?>">
			<?php foreach ( $groupings as $dp_key => $dp_label ) : ?>
				<a class="dp-tab<?php echo $group_by === $dp_key ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( 'd_group', $dp_key, $base_url ) ); ?>"
					<?php echo $group_by === $dp_key ? 'aria-current="page"' : ''; ?>>
					<span><?php echo esc_html( $dp_label ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php if ( ! $groups ) : ?>
			<p class="dp-empty"><?php esc_html_e( 'Nuk ka ende dëftesa të regjistruara.', 'deftese-pro' ); ?></p>
		<?php else : ?>
			<?php foreach ( $groups as $dp_key => $dp_group ) : ?>
				<details class="dp-group" <?php echo count( $groups ) === 1 ? 'open' : ''; ?>>
					<summary class="dp-group__head">
						<span class="dp-group__title"><?php echo esc_html( $dp_group['label'] ); ?></span>
						<span class="dp-badge">
							<?php
							printf(
								/* translators: %s: number of certificates. */
								esc_html( _n( '%s dëftesë', '%s dëftesa', count( $dp_group['rows'] ), 'deftese-pro' ) ),
								esc_html( number_format_i18n( count( $dp_group['rows'] ) ) )
							);
							?>
						</span>
					</summary>

					<div class="dp-table-wrap">
						<table class="dp-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Nr. Librit Amë', 'deftese-pro' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Nxënësi', 'deftese-pro' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Shkolla', 'deftese-pro' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Viti Shkollor', 'deftese-pro' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Kujdestari', 'deftese-pro' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Përdoruesi', 'deftese-pro' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Ruajtur më', 'deftese-pro' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Veprime', 'deftese-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $dp_group['rows'] as $dp_row ) : ?>
									<?php
									$dp_edit_url = add_query_arg(
										array(
											'page' => 'deftese-new',
											'id'   => $dp_row['id'],
										),
										admin_url( 'admin.php' )
									);

									$dp_author = (string) ( $dp_row['author_name'] ?? '' );

									if ( '' === $dp_author ) {
										$dp_author = (string) ( $dp_row['author_login'] ?? '' );
									}

									if ( '' === $dp_author ) {
										/* translators: %d: user id. */
										$dp_author = sprintf( __( 'ID %d', 'deftese-pro' ), (int) $dp_row['created_by'] );
									}

									$dp_saved = strtotime( (string) $dp_row['created_at'] );
									?>
									<tr>
										<td><strong><?php echo esc_html( $dp_row['registry_no'] ); ?></strong></td>
										<td><?php echo esc_html( $dp_row['student_name'] ); ?></td>
										<td class="dp-table__muted"><?php echo esc_html( $dp_row['school_name'] ); ?></td>
										<td class="dp-table__muted"><?php echo esc_html( $dp_row['school_year'] ); ?></td>
										<td class="dp-table__muted"><?php echo esc_html( $dp_row['class_teacher'] ); ?></td>
										<td><?php echo esc_html( $dp_author ); ?></td>
										<td class="dp-table__stamp"><?php echo $dp_saved ? esc_html( date_i18n( 'd/m/Y H:i', $dp_saved ) ) : '—'; ?></td>
										<td class="dp-table__actions"><a class="dp-btn dp-btn--tiny dp-btn--soft" href="<?php echo esc_url( $dp_edit_url ); ?>"><?php esc_html_e( 'Hap', 'deftese-pro' ); ?></a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</details>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
