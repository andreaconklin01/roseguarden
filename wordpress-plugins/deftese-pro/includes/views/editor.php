<?php
/**
 * Certificate editor: toolbar, scaled A4 sheet and the confirm dialog.
 *
 * @package DeftesePro
 *
 * @var array|null $cert        Certificate row, or null for a blank sheet.
 * @var string     $cert_id     Certificate id being edited.
 * @var array      $grades      Grade rows.
 * @var bool       $is_frontend Shortcode context.
 * @var string     $prev_url    Previous certificate link.
 * @var string     $next_url    Next certificate link.
 * @var string|null $prev_id    Previous certificate id.
 * @var string|null $next_id    Next certificate id.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="deftese-app-container" id="deftese-app">

	<div class="deftese-toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Mjetet e dëftesës', 'deftese-pro' ); ?>">
		<div class="deftese-toolbar-group">
			<button type="button" class="dp-tool dp-tool--edit" id="btn-toggle-edit" aria-pressed="false" title="<?php esc_attr_e( 'Aktivizo/çaktivizo redaktimin', 'deftese-pro' ); ?>">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
				<span class="dp-tool__label"><?php esc_html_e( 'Ndrysho', 'deftese-pro' ); ?></span>
			</button>
		</div>

		<span class="deftese-toolbar-sep" aria-hidden="true"></span>

		<div class="deftese-toolbar-group">
			<button type="button" class="dp-tool dp-tool--save" id="btn-save-cert" title="<?php esc_attr_e( 'Ruaj ndryshimet aktuale', 'deftese-pro' ); ?>">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
				<span class="dp-tool__label"><?php esc_html_e( 'Ruaj', 'deftese-pro' ); ?></span>
			</button>
			<button type="button" class="dp-tool dp-tool--new" id="btn-save-new" title="<?php esc_attr_e( 'Ruaj si dëftesë e re', 'deftese-pro' ); ?>">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M12 18v-6M9 15h6"/></svg>
				<span class="dp-tool__label"><?php esc_html_e( 'E Re', 'deftese-pro' ); ?></span>
			</button>
		</div>

		<span class="deftese-toolbar-sep" aria-hidden="true"></span>

		<div class="deftese-toolbar-group">
			<button type="button" class="dp-tool dp-tool--import" id="btn-import-trigger" title="<?php esc_attr_e( 'Importo nga CSV', 'deftese-pro' ); ?>">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/></svg>
				<span class="dp-tool__label"><?php esc_html_e( 'Importo CSV', 'deftese-pro' ); ?></span>
			</button>
			<button type="button" class="dp-tool dp-tool--print" id="btn-trigger-print" title="<?php esc_attr_e( 'Printo dëftesën', 'deftese-pro' ); ?>">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6Z"/></svg>
				<span class="dp-tool__label"><?php esc_html_e( 'Printo', 'deftese-pro' ); ?></span>
			</button>
		</div>

		<span class="deftese-toolbar-spacer"></span>

		<span class="deftese-cert-id-badge" id="deftese-id-badge">
			<?php
			if ( '' !== $cert_id ) {
				printf(
					/* translators: %s: registry number. */
					esc_html__( 'Libri Amë: %s', 'deftese-pro' ),
					esc_html( $cert_id )
				);
			} else {
				esc_html_e( 'E Paredaktuar', 'deftese-pro' );
			}
			?>
		</span>

		<div class="deftese-toolbar-group">
			<button type="button" class="dp-tool dp-tool--fullscreen" id="btn-toggle-fullscreen" aria-pressed="false" title="<?php esc_attr_e( 'Hap në Ekran të Plotë', 'deftese-pro' ); ?>">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M16 21h3a2 2 0 0 0 2-2v-3M8 21H5a2 2 0 0 1-2-2v-3"/></svg>
				<span class="dp-tool__label"><?php esc_html_e( 'Fullscreen', 'deftese-pro' ); ?></span>
			</button>
		</div>
	</div>

	<label class="dp-u-visually-hidden" for="deftese-import-file"><?php esc_html_e( 'Skedar CSV', 'deftese-pro' ); ?></label>
	<input type="file" id="deftese-import-file" accept=".csv" hidden>

	<p id="deftese-status-msg" role="status" aria-live="polite"></p>

	<div id="deftese-main-editor-wrap">
		<?php if ( '' !== $cert_id ) : ?>
			<a href="<?php echo esc_url( $prev_url ); ?>"
				class="nav-arrow nav-arrow-left<?php echo $prev_id ? '' : ' disabled'; ?>"
				data-nav-id="<?php echo esc_attr( (string) $prev_id ); ?>"
				title="<?php esc_attr_e( 'Dëftesa e Mëparshme', 'deftese-pro' ); ?>"
				aria-label="<?php esc_attr_e( 'Dëftesa e Mëparshme', 'deftese-pro' ); ?>">&#10094;</a>
			<a href="<?php echo esc_url( $next_url ); ?>"
				class="nav-arrow nav-arrow-right<?php echo $next_id ? '' : ' disabled'; ?>"
				data-nav-id="<?php echo esc_attr( (string) $next_id ); ?>"
				title="<?php esc_attr_e( 'Dëftesa e Radhës', 'deftese-pro' ); ?>"
				aria-label="<?php esc_attr_e( 'Dëftesa e Radhës', 'deftese-pro' ); ?>">&#10095;</a>
		<?php endif; ?>

		<div id="deftese-scale-wrapper">
			<?php
			DeftesePro\view(
				'certificate',
				array(
					'cert'   => $cert,
					'grades' => $grades,
				)
			);
			?>
		</div>
	</div>

	<div class="deftese-modal-overlay" id="deftese-modal" role="dialog" aria-modal="true" aria-labelledby="deftese-modal-title" hidden>
		<div class="deftese-modal">
			<h3 class="deftese-modal__title" id="deftese-modal-title"><?php esc_html_e( 'Konfirmo', 'deftese-pro' ); ?></h3>
			<p class="deftese-modal__msg" id="deftese-modal-msg"></p>
			<div class="deftese-modal__actions">
				<button type="button" class="dp-btn dp-btn--neutral" id="deftese-modal-cancel"><?php esc_html_e( 'Anulo', 'deftese-pro' ); ?></button>
				<button type="button" class="dp-btn dp-btn--success" id="deftese-modal-confirm"><?php esc_html_e( 'Konfirmo', 'deftese-pro' ); ?></button>
			</div>
		</div>
	</div>
</div>
