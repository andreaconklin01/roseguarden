/**
 * Dëftesë PRO — list view behaviour.
 *
 * Select-all, the bulk-action guard and the batched CSV uploader.
 */
( function ( window, document ) {
	'use strict';

	var config = window.defteseList || {};
	var strings = config.i18n || {};
	var BATCH_SIZE = 10;

	/**
	 * Interpolate numbered placeholders in a translated string.
	 *
	 * @param {string} template Translated template.
	 * @param {Array}  values   Replacements, in order.
	 * @return {string} Final string.
	 */
	function format( template, values ) {
		return String( template || '' ).replace( /%(\d+)\$[sd]|%[sd]/g, function ( match, position ) {
			return position ? values[ position - 1 ] : values.shift();
		} );
	}

	/**
	 * Wire the select-all checkbox.
	 */
	function initSelectAll() {
		var toggle = document.getElementById( 'deftese-select-all' );

		if ( ! toggle ) {
			return;
		}

		toggle.addEventListener( 'change', function () {
			Array.prototype.forEach.call( document.querySelectorAll( 'input[name="cert_ids[]"]' ), function ( box ) {
				box.checked = toggle.checked;
			} );
		} );
	}

	/**
	 * Show the destination picker only for the "move" action, and confirm on submit.
	 */
	function initBulkActions() {
		var form = document.getElementById( 'deftese-list-form' );
		var selector = document.getElementById( 'bulk-action-selector' );
		var target = document.getElementById( 'deftese-target-user' );

		if ( ! form || ! selector ) {
			return;
		}

		if ( target ) {
			selector.addEventListener( 'change', function () {
				target.hidden = 'move' !== selector.value;
			} );
		}

		form.addEventListener( 'submit', function ( event ) {
			var action = selector.value;

			if ( 'delete' === action ) {
				if ( ! window.confirm( strings.confirmDelete ) ) {
					event.preventDefault();
				}

				return;
			}

			if ( 'move' === action ) {
				if ( ! target || ! target.value ) {
					window.alert( strings.pickUser );
					event.preventDefault();

					return;
				}

				if ( ! window.confirm( strings.confirmMove ) ) {
					event.preventDefault();
				}

				return;
			}

			event.preventDefault();
		} );
	}

	/**
	 * Confirm single-row deletions.
	 */
	function initRowDelete() {
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( '[data-dp-confirm="row"]' );

			if ( link && ! window.confirm( strings.confirmRowTrash ) ) {
				event.preventDefault();
			}
		} );
	}

	/**
	 * Upload the selected CSV files in batches, then reload with a summary.
	 */
	function initImport() {
		var form = document.getElementById( 'deftese-import-form' );

		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function ( event ) {
			var input = form.querySelector( 'input[type="file"]' );
			var button = document.getElementById( 'btn-bulk-import' );
			var progress = document.getElementById( 'deftese-import-progress' );
			var overwrite = form.querySelector( 'input[name="overwrite"]' ).checked;
			var nonce = form.querySelector( 'input[name="deftese_import_nonce"]' ).value;
			var isFrontend = '1' === form.dataset.frontend;
			var files;

			event.preventDefault();

			if ( ! input || ! input.files.length ) {
				return;
			}

			files = Array.prototype.slice.call( input.files );
			button.disabled = true;

			uploadBatches( files, {
				nonce: nonce,
				overwrite: overwrite,
				progress: progress,
				button: button
			} ).then( function ( totals ) {
				var base = window.location.href.split( '?' )[ 0 ];
				var params = isFrontend
					? '?d_view=list&deftese_msg=imported_' + totals.imported + '|errors_' + totals.errors
					: '?page=deftese-manager&deftese_msg=imported_' + totals.imported + '|errors_' + totals.errors;

				window.location.href = base + params;
			} );
		} );
	}

	/**
	 * Send the files to admin-ajax in fixed-size batches.
	 *
	 * @param {File[]} files   Selected files.
	 * @param {Object} options Upload options.
	 * @return {Promise<{imported: number, errors: number}>} Totals.
	 */
	function uploadBatches( files, options ) {
		var totals = { imported: 0, errors: 0 };
		var chain = window.Promise.resolve();
		var index;

		for ( index = 0; index < files.length; index += BATCH_SIZE ) {
			chain = chain.then( sendBatch( files, index, totals, options ) );
		}

		return chain.then( function () {
			return totals;
		} );
	}

	/**
	 * Build a task that uploads one batch.
	 *
	 * @param {File[]} files   All selected files.
	 * @param {number} offset  Index of the first file in this batch.
	 * @param {Object} totals  Accumulator.
	 * @param {Object} options Upload options.
	 * @return {Function} Task returning a promise.
	 */
	function sendBatch( files, offset, totals, options ) {
		return function () {
			var batch = files.slice( offset, offset + BATCH_SIZE );
			var data = new window.FormData();
			var done = Math.min( offset + BATCH_SIZE, files.length );

			if ( options.progress ) {
				options.progress.textContent = format( strings.importProgress, [ done, files.length ] );
			}

			data.append( 'action', 'deftese_import_ajax' );
			data.append( 'deftese_import_nonce', options.nonce );

			if ( options.overwrite ) {
				data.append( 'overwrite', '1' );
			}

			batch.forEach( function ( file ) {
				data.append( 'deftese_import_files[]', file );
			} );

			return window.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( result ) {
				if ( result && result.success ) {
					totals.imported += result.data.imported;
					totals.errors += result.data.errors;
				} else {
					totals.errors += batch.length;
				}
			} ).catch( function () {
				// A dropped batch is reported rather than silently lost.
				totals.errors += batch.length;
			} );
		};
	}

	/**
	 * Boot.
	 */
	function init() {
		initSelectAll();
		initBulkActions();
		initRowDelete();
		initImport();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}( window, document ) );
