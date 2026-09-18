<?php
/**
 * The printed A4 certificate sheet.
 *
 * The markup and class names here are load-bearing: the print stylesheet and
 * the layout plugin's CSS variables target them, and the sheet is what schools
 * hand out. It is a faithful reproduction of the 6.x output.
 *
 * @package DeftesePro
 *
 * @var array|null $cert   Certificate row, or null for a blank sheet.
 * @var array      $grades Grade rows.
 */

defined( 'ABSPATH' ) || exit;

use function DeftesePro\field;

$dp_footer = ( $cert && '' !== (string) ( $cert['footer_note'] ?? '' ) )
	? $cert['footer_note']
	: DeftesePro\Fields::DEFAULT_FOOTER_NOTE;
?>
<div class="a4-page" id="deftese-paper">
	<div class="deftese-watermark" aria-hidden="true"></div>

	<div class="page-centered-content">
		<div class="header-top">
			<div class="header-left">
				<span class="name-slot">
					<span class="bold editable-field" data-field="school_name"><?php echo field( $cert, 'school_name' ); ?></span>
					<span class="sub-text"><?php esc_html_e( '(emri i shkollës)', 'deftese-pro' ); ?></span>
				</span>
				&nbsp;&nbsp;&nbsp; <?php esc_html_e( 'në', 'deftese-pro' ); ?> &nbsp;&nbsp;&nbsp;
				<u class="bold editable-field school-city" data-field="school_city"><?php echo field( $cert, 'school_city' ); ?></u>

				<div class="header-left-bottom">
					<div><?php esc_html_e( 'Nr. i librit amë:', 'deftese-pro' ); ?> <u class="editable-field" data-field="registry_no"><?php echo field( $cert, 'registry_no' ); ?></u></div>
					<div><?php esc_html_e( 'Nr. i protokollit:', 'deftese-pro' ); ?> <u class="editable-field" data-field="protocol_no"><?php echo field( $cert, 'protocol_no' ); ?></u></div>
				</div>
			</div>

			<div class="header-right">
				<?php esc_html_e( 'Këndi i shkollës në bazë të Vendimit', 'deftese-pro' ); ?><br>
				<?php esc_html_e( 'të Ministrisë së Arsimit, Shkencës,', 'deftese-pro' ); ?><br>
				<?php esc_html_e( 'Teknologjisë dhe Inovacionit', 'deftese-pro' ); ?>
				<u class="bold editable-field header-right-bottom" data-field="ministry_code"><?php echo field( $cert, 'ministry_code' ); ?></u>
			</div>
		</div>

		<div class="title-section">
			<h1><?php esc_html_e( 'DËFTESË', 'deftese-pro' ); ?></h1>
			<h3><?php esc_html_e( 'PËR KRYERJEN E SHKOLLËS SË MESME TË ULËT', 'deftese-pro' ); ?></h3>
		</div>

		<div class="student-info">
			<div class="student-names">
				<div class="name-block">
					<div class="name-text editable-field" data-field="student_name" id="deftese-student-name"><?php echo field( $cert, 'student_name' ); ?></div>
					<div class="sub-text"><?php esc_html_e( '(emri dhe mbiemri)', 'deftese-pro' ); ?></div>
				</div>
				<div class="middle-text"><?php esc_html_e( 'i biri, e bija', 'deftese-pro' ); ?></div>
				<div class="name-block">
					<div class="name-text editable-field" data-field="parent_name"><?php echo field( $cert, 'parent_name' ); ?></div>
				</div>
			</div>

			<div class="details-text">
				<?php esc_html_e( 'lindur më', 'deftese-pro' ); ?> &nbsp; <u class="editable-field" data-field="birth_date"><?php echo field( $cert, 'birth_date' ); ?></u>
				&nbsp; <?php esc_html_e( 'në', 'deftese-pro' ); ?> &nbsp; <u class="editable-field" data-field="birth_city"><?php echo field( $cert, 'birth_city' ); ?></u>,
				<?php esc_html_e( 'komuna', 'deftese-pro' ); ?> &nbsp; <u class="editable-field" data-field="birth_commune"><?php echo field( $cert, 'birth_commune' ); ?></u>,
				<?php esc_html_e( 'shteti', 'deftese-pro' ); ?> &nbsp; <u class="editable-field" data-field="birth_state"><?php echo field( $cert, 'birth_state' ); ?></u><br>
				<?php esc_html_e( 'në vitin shkollor', 'deftese-pro' ); ?> <u class="editable-field" data-field="school_year"><?php echo field( $cert, 'school_year' ); ?></u>
				<?php esc_html_e( 'e kreu shkollën e mesme të ulët dhe tregoi këtë sukses:', 'deftese-pro' ); ?>
			</div>
		</div>
	</div>

	<div class="table-wrapper">
		<div class="right-decor-wrapper" aria-hidden="false">
			<div class="right-decor-line">
				<div class="vertical-note">
					<?php esc_html_e( 'Vërejtje:', 'deftese-pro' ); ?>
					<span class="editable-field vertical-text" data-field="remarks_1"><?php echo field( $cert, 'remarks_1' ); ?></span>
				</div>
			</div>
			<div class="right-decor-line">
				<div class="vertical-note">
					<span class="editable-field vertical-text" data-field="remarks_2"><?php echo field( $cert, 'remarks_2' ); ?></span>
				</div>
			</div>
			<div class="right-decor-line">
				<div class="vertical-note">
					<span class="editable-field vertical-text" data-field="remarks_3"><?php echo field( $cert, 'remarks_3' ); ?></span>
				</div>
			</div>
		</div>

		<table id="deftese-grades-table">
			<colgroup>
				<col class="dp-c1"><col class="dp-c2"><col class="dp-c3">
				<col class="dp-c4"><col class="dp-c5"><col class="dp-c6">
			</colgroup>
			<thead>
				<tr>
					<th colspan="5" class="grades-caption"><?php esc_html_e( 'Suksesi sipas fushave dhe klasave', 'deftese-pro' ); ?></th>
					<th class="grades-test-head"><?php esc_html_e( 'Testi i', 'deftese-pro' ); ?><br><?php esc_html_e( 'arritshmërisë', 'deftese-pro' ); ?></th>
				</tr>
				<tr>
					<th class="grades-classes-head"><?php esc_html_e( 'Klasat', 'deftese-pro' ); ?></th>
					<th>VI</th>
					<th>VII</th>
					<th>VIII</th>
					<th>IX</th>
					<th class="grades-points-head"><?php esc_html_e( 'Numri', 'deftese-pro' ); ?><br><?php esc_html_e( 'i pikave', 'deftese-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $grades as $dp_index => $dp_row ) : ?>
					<?php
					$dp_is_cat   = ! empty( $dp_row['cat'] );
					$dp_is_bold  = ! empty( $dp_row['bold'] );
					$dp_row_cls  = $dp_is_cat ? 'cat-row' : ( $dp_is_bold ? 'bold' : '' );
					?>
					<tr>
						<td class="<?php echo esc_attr( $dp_row_cls ); ?>" <?php echo $dp_is_cat ? 'colspan="6"' : ''; ?>>
							<span class="row-controls" role="group" aria-label="<?php esc_attr_e( 'Rreshti', 'deftese-pro' ); ?>">
								<button type="button" class="btn-row-up" data-index="<?php echo esc_attr( (string) $dp_index ); ?>" title="<?php esc_attr_e( 'Lart', 'deftese-pro' ); ?>" aria-label="<?php esc_attr_e( 'Zhvendos lart', 'deftese-pro' ); ?>">&#9650;</button>
								<button type="button" class="btn-row-down" data-index="<?php echo esc_attr( (string) $dp_index ); ?>" title="<?php esc_attr_e( 'Poshtë', 'deftese-pro' ); ?>" aria-label="<?php esc_attr_e( 'Zhvendos poshtë', 'deftese-pro' ); ?>">&#9660;</button>
								<button type="button" class="btn-remove-row" data-index="<?php echo esc_attr( (string) $dp_index ); ?>" title="<?php esc_attr_e( 'Fshi', 'deftese-pro' ); ?>" aria-label="<?php esc_attr_e( 'Fshi rreshtin', 'deftese-pro' ); ?>">&#10006;</button>
							</span>
							<span class="editable-field subject-cell" data-gi="<?php echo esc_attr( (string) $dp_index ); ?>" data-gk="subject"><?php echo esc_html( (string) ( $dp_row['subject'] ?? '' ) ); ?></span>
						</td>

						<?php if ( ! $dp_is_cat ) : ?>
							<?php foreach ( DeftesePro\Fields::grade_classes() as $dp_class ) : ?>
								<?php
								$dp_mark  = (string) ( $dp_row[ $dp_class ] ?? '' );
								$dp_score = (string) ( $dp_row[ 'grade_' . $dp_class ] ?? '' );
								?>
								<td>
									<?php if ( '' !== $dp_score ) : ?>
										<span class="flex-cell">
											<span class="editable-field" data-gi="<?php echo esc_attr( (string) $dp_index ); ?>" data-gk="<?php echo esc_attr( $dp_class ); ?>"><?php echo esc_html( $dp_mark ); ?></span>
											<span class="editable-field" data-gi="<?php echo esc_attr( (string) $dp_index ); ?>" data-gk="grade_<?php echo esc_attr( $dp_class ); ?>"><?php echo esc_html( $dp_score ); ?></span>
										</span>
									<?php else : ?>
										<span class="center editable-field mark-cell" data-gi="<?php echo esc_attr( (string) $dp_index ); ?>" data-gk="<?php echo esc_attr( $dp_class ); ?>"><?php echo esc_html( $dp_mark ); ?></span>
									<?php endif; ?>
								</td>
							<?php endforeach; ?>
							<td class="center editable-field" data-gi="<?php echo esc_attr( (string) $dp_index ); ?>" data-gk="test"><?php echo esc_html( (string) ( $dp_row['test'] ?? '' ) ); ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div id="edit-table-actions">
			<button type="button" class="dp-btn dp-btn--tiny dp-btn--soft" id="btn-add-subject"><?php esc_html_e( '+ Shto Lëndë', 'deftese-pro' ); ?></button>
			<button type="button" class="dp-btn dp-btn--tiny dp-btn--soft" id="btn-add-category"><?php esc_html_e( '+ Shto Kategori', 'deftese-pro' ); ?></button>
		</div>
	</div>

	<div class="page-centered-content">
		<div class="footer-signatures">
			<div class="sig-block sig-block--teacher">
				<div class="sig-place">
					<?php esc_html_e( 'Në', 'deftese-pro' ); ?> <u class="editable-field sig-city" data-field="cert_city"><?php echo field( $cert, 'cert_city' ); ?></u>,
					<?php esc_html_e( 'më', 'deftese-pro' ); ?> <u class="editable-field sig-date" data-field="cert_date"><?php echo field( $cert, 'cert_date' ); ?></u>
				</div>
				<div class="sig-role"><?php esc_html_e( 'Kujdestari i klasës:', 'deftese-pro' ); ?></div>
				<div class="sig-name editable-field" data-field="class_teacher"><?php echo field( $cert, 'class_teacher' ); ?></div>
				<div class="sig-line"></div>
			</div>

			<div class="stamp-mark">V.V</div>

			<div class="sig-block sig-block--director">
				<div class="sig-role"><?php esc_html_e( 'Drejtori:', 'deftese-pro' ); ?></div>
				<div class="sig-name editable-field" data-field="director_name"><?php echo field( $cert, 'director_name' ); ?></div>
				<div class="sig-line"></div>
			</div>
		</div>

		<div class="footer-note editable-field" data-field="footer_note"><?php echo wp_kses_post( $dp_footer ); ?></div>
	</div>
</div>
