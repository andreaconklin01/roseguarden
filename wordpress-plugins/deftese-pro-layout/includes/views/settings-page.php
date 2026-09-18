<?php
/**
 * Layout settings screen markup.
 *
 * @package DefteseProLayout
 *
 * @var array $layout Current settings.
 * @var bool  $saved  Whether the form was just submitted.
 */

defined( 'ABSPATH' ) || exit;

use DefteseProLayout\Screen;
?>
<div class="wrap dpl-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Dëftesë PRO — Menaxheri i Dizajnit', 'deftese-pro-layout' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><strong><?php esc_html_e( 'Dëftesë PRO:', 'deftese-pro-layout' ); ?></strong> <?php esc_html_e( 'Të gjitha konfigurimet u ruajtën me sukses!', 'deftese-pro-layout' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=deftese-layout' ) ); ?>">
		<?php wp_nonce_field( 'deftese_layout_nonce' ); ?>

		<div class="dpl-grid">

			<section class="postbox dpl-box">
				<h2 class="hndle"><span><?php esc_html_e( 'Hapësirat e Fletës A4 (Margins)', 'deftese-pro-layout' ); ?></span></h2>
				<div class="inside">
					<table class="form-table" role="presentation">
						<?php
						Screen::number_row( 'margin_top', __( 'Lart (Top)', 'deftese-pro-layout' ), $layout, 'mm', '0.1' );
						Screen::number_row( 'margin_bottom', __( 'Poshtë (Bottom)', 'deftese-pro-layout' ), $layout, 'mm', '0.1' );
						Screen::number_row( 'margin_left', __( 'Majtas (Left)', 'deftese-pro-layout' ), $layout, 'mm', '0.1' );
						Screen::number_row( 'margin_right', __( 'Djathtas (Right)', 'deftese-pro-layout' ), $layout, 'mm', '0.1' );
						?>
					</table>
				</div>
			</section>

			<section class="postbox dpl-box">
				<h2 class="hndle"><span><?php esc_html_e( 'Tipografia dhe Ngjyrat', 'deftese-pro-layout' ); ?></span></h2>
				<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="dpl-color_main"><?php esc_html_e( 'Ngjyra Kryesore (Titujt)', 'deftese-pro-layout' ); ?></label></th>
							<td><input type="text" id="dpl-color_main" name="color_main" value="<?php echo esc_attr( (string) $layout['color_main'] ); ?>" class="dpl-color"></td>
						</tr>
						<tr>
							<th scope="row"><label for="dpl-bg_color"><?php esc_html_e( 'Ngjyra e Sfondit (Letrës)', 'deftese-pro-layout' ); ?></label></th>
							<td><input type="text" id="dpl-bg_color" name="bg_color" value="<?php echo esc_attr( (string) $layout['bg_color'] ); ?>" class="dpl-color"></td>
						</tr>
						<?php
						Screen::number_row( 'fs_title', __( 'Teksti: Titulli (DËFTESË)', 'deftese-pro-layout' ), $layout, 'px', '0.5' );
						Screen::number_row( 'fs_table', __( 'Teksti: Tabela (Baza)', 'deftese-pro-layout' ), $layout, 'px', '0.5' );
						?>
					</table>
				</div>
			</section>

			<section class="postbox dpl-box dpl-box--wide">
				<h2 class="hndle"><span><?php esc_html_e( 'Hapësirat e Seksioneve', 'deftese-pro-layout' ); ?></span></h2>
				<div class="inside">
					<p class="description"><?php esc_html_e( 'Rregulloni distancat (në pixel) mes pjesëve kryesore të dëftesës.', 'deftese-pro-layout' ); ?></p>

					<div class="dpl-split">
						<div>
							<h3 class="dpl-subhead dpl-subhead--top"><?php esc_html_e( 'Pjesa e Sipërme', 'deftese-pro-layout' ); ?></h3>
							<table class="form-table" role="presentation">
								<?php
								Screen::number_row( 'gap_top_1', __( 'Top 1 (Koka ➝ Titulli)', 'deftese-pro-layout' ), $layout );
								Screen::number_row( 'gap_top_2', __( 'Top 2 (Titulli ➝ Nëntitulli)', 'deftese-pro-layout' ), $layout );
								Screen::number_row( 'gap_top_3', __( 'Top 3 (Nëntitulli ➝ Të Dhënat)', 'deftese-pro-layout' ), $layout );
								Screen::number_row( 'gap_top_4', __( 'Top 4 (Të Dhënat ➝ Tabela)', 'deftese-pro-layout' ), $layout );
								?>
							</table>
						</div>

						<div>
							<h3 class="dpl-subhead dpl-subhead--bottom"><?php esc_html_e( 'Pjesa e Poshtme', 'deftese-pro-layout' ); ?></h3>
							<table class="form-table" role="presentation">
								<?php
								Screen::number_row( 'gap_bottom_1', __( 'Bottom 1 (Tabela ➝ Nënshkrimet)', 'deftese-pro-layout' ), $layout );
								Screen::number_row( 'gap_bottom_2', __( 'Bottom 2 (Nënshkrimet ➝ Vërejtja)', 'deftese-pro-layout' ), $layout );
								Screen::number_row( 'gap_bottom_3', __( 'Bottom 3 (Pas Vërejtjes / Fundi)', 'deftese-pro-layout' ), $layout );
								?>
							</table>
						</div>
					</div>
				</div>
			</section>

			<section class="postbox dpl-box dpl-box--wide">
				<h2 class="hndle"><span><?php esc_html_e( 'Përmasat e Tabelës & Vërejtjeve', 'deftese-pro-layout' ); ?></span></h2>
				<div class="inside">
					<table class="form-table" role="presentation">
						<?php
						Screen::number_row( 'row_height', __( 'Lartësia e Rreshtit', 'deftese-pro-layout' ), $layout );
						Screen::number_row( 'cell_pad_y', __( 'Hapësira e Qelizave Y (Brenda)', 'deftese-pro-layout' ), $layout, 'px', '0.5' );
						Screen::number_row(
							'decor_gap',
							__( 'Gjerësia mes vijave vertikale (Vërejtje)', 'deftese-pro-layout' ),
							$layout,
							'px',
							'1',
							__( 'Rregullon distancën mes vijave vertikale në anën e djathtë, ku shkruhen komentet.', 'deftese-pro-layout' )
						);
						Screen::number_row(
							'gap_hf_elements',
							__( 'Hapësira e Rreshtave të Vogla', 'deftese-pro-layout' ),
							$layout,
							'px',
							'1',
							__( 'Distanca ndërmjet rreshtave në kokën e faqes dhe te nënshkrimet.', 'deftese-pro-layout' )
						);
						?>
						<tr>
							<th scope="row"><label for="dpl-border_out"><?php esc_html_e( 'Trashësia e Kornizës', 'deftese-pro-layout' ); ?></label></th>
							<td>
								<label for="dpl-border_out"><?php esc_html_e( 'Tabela (Jashtë)', 'deftese-pro-layout' ); ?></label>
								<input type="number" step="0.5" id="dpl-border_out" name="border_out" value="<?php echo esc_attr( (string) $layout['border_out'] ); ?>" class="small-text">
								<span class="dpl-unit">px</span>
								&nbsp;|&nbsp;
								<label for="dpl-border_in"><?php esc_html_e( 'Qelizat (Brenda)', 'deftese-pro-layout' ); ?></label>
								<input type="number" step="0.5" id="dpl-border_in" name="border_in" value="<?php echo esc_attr( (string) $layout['border_in'] ); ?>" class="small-text">
								<span class="dpl-unit">px</span>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Gjerësia e Kolonave (%)', 'deftese-pro-layout' ); ?></th>
							<td>
								<div class="dpl-columns">
									<?php
									$dpl_columns = array(
										'col1' => __( 'Kol 1 (Lëndët)', 'deftese-pro-layout' ),
										'col2' => __( 'Kol 2 (VI)', 'deftese-pro-layout' ),
										'col3' => __( 'Kol 3 (VII)', 'deftese-pro-layout' ),
										'col4' => __( 'Kol 4 (VIII)', 'deftese-pro-layout' ),
										'col5' => __( 'Kol 5 (IX)', 'deftese-pro-layout' ),
										'col6' => __( 'Kol 6 (Testi)', 'deftese-pro-layout' ),
									);

									foreach ( $dpl_columns as $dpl_key => $dpl_label ) :
										$dpl_derived = in_array( $dpl_key, array( 'col2', 'col3', 'col4', 'col5' ), true );
										?>
										<div class="dpl-col<?php echo $dpl_derived ? ' dpl-col--derived' : ''; ?>">
											<label for="<?php echo esc_attr( $dpl_key ); ?>"><?php echo esc_html( $dpl_label ); ?></label>
											<input type="number" step="0.1" id="<?php echo esc_attr( $dpl_key ); ?>" name="<?php echo esc_attr( $dpl_key ); ?>"
												value="<?php echo esc_attr( (string) $layout[ $dpl_key ] ); ?>" class="small-text">
											<span class="dpl-unit">%</span>
										</div>
									<?php endforeach; ?>
								</div>
								<p class="description"><?php esc_html_e( 'Kolonat e klasave ndahen automatikisht nga hapësira e mbetur, por mund t\'i ndryshoni edhe manualisht.', 'deftese-pro-layout' ); ?></p>
								<p class="dpl-warning" id="col-total-warning" hidden></p>
							</td>
						</tr>
					</table>
				</div>
			</section>

			<section class="postbox dpl-box">
				<h2 class="hndle"><span><?php esc_html_e( 'Vula / Logo e Sfondit (Watermark)', 'deftese-pro-layout' ); ?></span></h2>
				<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Ngarko Imazhin', 'deftese-pro-layout' ); ?></th>
							<td>
								<input type="hidden" name="logo_url" id="logo_url" value="<?php echo esc_attr( (string) $layout['logo_url'] ); ?>">
								<button type="button" class="button" id="upload_logo_button"><?php esc_html_e( 'Kërko ose Ngarko', 'deftese-pro-layout' ); ?></button>
								<button type="button" class="button dpl-remove" id="remove_logo_button"><?php esc_html_e( 'Hiq', 'deftese-pro-layout' ); ?></button>
								<img id="logo_preview" class="dpl-preview" alt=""
									src="<?php echo esc_url( (string) $layout['logo_url'] ); ?>"
									<?php echo $layout['logo_url'] ? '' : 'hidden'; ?>>
							</td>
						</tr>
						<?php
						Screen::number_row( 'logo_size', __( 'Madhësia e Logos', 'deftese-pro-layout' ), $layout, '%' );
						Screen::number_row( 'logo_x', __( 'Pozicioni Horiz. (X)', 'deftese-pro-layout' ), $layout, '%', '1', __( '50% = Qendër', 'deftese-pro-layout' ) );
						Screen::number_row( 'logo_y', __( 'Pozicioni Vertik. (Y)', 'deftese-pro-layout' ), $layout, '%', '1', __( '50% = Qendër', 'deftese-pro-layout' ) );
						Screen::number_row( 'logo_opacity', __( 'Dukshmëria (Opacity)', 'deftese-pro-layout' ), $layout, '%', '1', __( 'Rekomandohet 10% deri 20% për një watermark të butë.', 'deftese-pro-layout' ) );
						?>
					</table>
				</div>
			</section>
		</div>

		<p class="submit">
			<button type="submit" name="deftese_save_layout" value="1" class="button button-primary button-large">
				<?php esc_html_e( 'Ruaj të Gjitha Parametrat', 'deftese-pro-layout' ); ?>
			</button>
		</p>
	</form>
</div>
