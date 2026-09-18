/**
 * Dëftesë PRO — certificate editor.
 *
 * Handles edit mode, saving, previous/next navigation, single-file CSV import,
 * the dynamic subject table, the achievement-test total and printing.
 */
( function ( window, document ) {
	'use strict';

	var config = window.defteseEditor || {};
	var strings = config.i18n || {};
	var gradeKeys = config.gradeKeys || [ 'vi', 'vii', 'viii', 'ix' ];

	var state = {
		isEditing: false,
		certId: config.certId || '',
		grades: Array.isArray( config.grades ) ? config.grades : []
	};

	var els = {};
	var modalCallback = null;

	/**
	 * Interpolate the first %s / %d placeholder in a translated string.
	 *
	 * @param {string} template Translated template.
	 * @param {*}      value    Replacement.
	 * @return {string} Final string.
	 */
	function format( template, value ) {
		return String( template || '' ).replace( /%[sd]/, value );
	}

	/**
	 * Show a status line under the toolbar.
	 *
	 * @param {string} message Text to show.
	 * @param {string} tone    One of ok, busy, error, info.
	 */
	function setStatus( message, tone ) {
		if ( ! els.status ) {
			return;
		}

		els.status.textContent = message || '';
		els.status.setAttribute( 'data-tone', tone || 'info' );
	}

	/**
	 * Update the certificate id shown in the toolbar.
	 *
	 * @param {string} id Certificate id.
	 */
	function setBadge( id ) {
		state.certId = id || '';

		if ( els.badge ) {
			els.badge.textContent = state.certId
				? format( strings.badgeFilled, state.certId )
				: ( strings.badgeEmpty || '' );
		}
	}

	/* ------------------------------------------------------------ scaling -- */

	/**
	 * Scale the A4 sheet down to fit the available width.
	 */
	function recalcScale() {
		var parent;
		var available;
		var scale = 1;
		var height;
		var paperWidth = 794;   // 210mm at 96dpi.
		var paperHeight = 1122; // 297mm at 96dpi.
		var gutter = 20;

		if ( ! els.scaleWrapper || ! els.paper || ! els.mainWrap ) {
			return;
		}

		parent = document.fullscreenElement ? els.app : els.scaleWrapper.parentElement;
		available = parent ? parent.clientWidth : paperWidth;

		if ( available < paperWidth + gutter ) {
			scale = ( available - gutter ) / paperWidth;
		}

		scale = Math.max( scale, 0.1 );
		els.paper.style.transform = 'scale(' + scale + ')';

		height = paperHeight * scale + 'px';
		els.scaleWrapper.style.height = height;
		els.mainWrap.style.height = height;

		positionNavArrows();
	}

	/**
	 * Keep the navigation arrows beside the sheet while in fullscreen.
	 */
	function positionNavArrows() {
		var arrows = [ els.prevArrow, els.nextArrow ];
		var isFullscreen = !! document.fullscreenElement;
		var rect;
		var midY;

		if ( ! arrows[ 0 ] && ! arrows[ 1 ] ) {
			return;
		}

		if ( ! isFullscreen || ! els.paper ) {
			arrows.forEach( function ( arrow ) {
				if ( arrow ) {
					arrow.style.position = '';
					arrow.style.top = '';
					arrow.style.left = '';
					arrow.style.right = '';
				}
			} );

			return;
		}

		rect = els.paper.getBoundingClientRect();
		midY = rect.top + rect.height / 2;

		if ( els.prevArrow ) {
			els.prevArrow.style.position = 'fixed';
			els.prevArrow.style.top = midY + 'px';
			els.prevArrow.style.left = Math.max( 8, rect.left - 70 ) + 'px';
			els.prevArrow.style.right = 'auto';
		}

		if ( els.nextArrow ) {
			els.nextArrow.style.position = 'fixed';
			els.nextArrow.style.top = midY + 'px';
			els.nextArrow.style.right = Math.max( 8, window.innerWidth - rect.right - 70 ) + 'px';
			els.nextArrow.style.left = 'auto';
		}
	}

	/* ------------------------------------------------------- grade table -- */

	/**
	 * Create one editable cell.
	 *
	 * @param {number} index Grade row index.
	 * @param {string} key   Grade field key.
	 * @param {string} value Cell text.
	 * @param {string} extra Extra class names.
	 * @return {HTMLElement} The span.
	 */
	function editableSpan( index, key, value, extra ) {
		var span = document.createElement( 'span' );

		span.className = 'editable-field' + ( extra ? ' ' + extra : '' );
		span.dataset.gi = String( index );
		span.dataset.gk = key;
		// textContent, never innerHTML: subject text comes from the database and
		// may have been entered by another user.
		span.textContent = value || '';

		if ( state.isEditing ) {
			span.setAttribute( 'contenteditable', 'true' );
		}

		return span;
	}

	/**
	 * Build the up/down/remove controls for a row.
	 *
	 * @param {number} index Grade row index.
	 * @return {HTMLElement} The control group.
	 */
	function rowControls( index ) {
		var wrap = document.createElement( 'span' );

		wrap.className = 'row-controls';
		wrap.setAttribute( 'role', 'group' );

		[
			[ 'btn-row-up', '▲', strings.rowUp ],
			[ 'btn-row-down', '▼', strings.rowDown ],
			[ 'btn-remove-row', '✖', strings.rowRemove ]
		].forEach( function ( spec ) {
			var button = document.createElement( 'button' );

			button.type = 'button';
			button.className = spec[ 0 ];
			button.dataset.index = String( index );
			button.textContent = spec[ 1 ];
			button.title = spec[ 2 ] || '';
			button.setAttribute( 'aria-label', spec[ 2 ] || '' );

			wrap.appendChild( button );
		} );

		return wrap;
	}

	/**
	 * Redraw the grade table from state.
	 */
	function renderGrades() {
		var tbody = els.table ? els.table.querySelector( 'tbody' ) : null;
		var fragment;

		if ( ! tbody ) {
			return;
		}

		fragment = document.createDocumentFragment();

		state.grades.forEach( function ( grade, index ) {
			var tr = document.createElement( 'tr' );
			var subjectCell = document.createElement( 'td' );

			subjectCell.className = grade.cat ? 'cat-row' : ( grade.bold ? 'bold' : '' );

			if ( grade.cat ) {
				subjectCell.colSpan = 6;
			}

			subjectCell.appendChild( rowControls( index ) );
			subjectCell.appendChild( editableSpan( index, 'subject', grade.subject, 'subject-cell' ) );
			tr.appendChild( subjectCell );

			if ( ! grade.cat ) {
				gradeKeys.forEach( function ( key ) {
					var td = document.createElement( 'td' );
					var score = grade[ 'grade_' + key ] || '';
					var flex;

					if ( score ) {
						flex = document.createElement( 'span' );
						flex.className = 'flex-cell';
						flex.appendChild( editableSpan( index, key, grade[ key ], '' ) );
						flex.appendChild( editableSpan( index, 'grade_' + key, score, '' ) );
						td.appendChild( flex );
					} else {
						td.appendChild( editableSpan( index, key, grade[ key ], 'center mark-cell' ) );
					}

					tr.appendChild( td );
				} );

				tr.appendChild( ( function () {
					var td = document.createElement( 'td' );

					td.className = 'center editable-field';
					td.dataset.gi = String( index );
					td.dataset.gk = 'test';
					td.textContent = grade.test || '';

					if ( state.isEditing ) {
						td.setAttribute( 'contenteditable', 'true' );
					}

					return td;
				}() ) );
			}

			fragment.appendChild( tr );
		} );

		tbody.textContent = '';
		tbody.appendChild( fragment );

		calculateTestPoints();
	}

	/**
	 * Total the achievement-test points and flag a mismatched manual total.
	 */
	function calculateTestPoints() {
		var cells = els.table ? els.table.querySelectorAll( '.editable-field[data-gk="test"]' ) : [];
		var sum = 0;
		var totalCell = null;
		var current;
		var parsed;

		Array.prototype.forEach.call( cells, function ( cell ) {
			var index = parseInt( cell.dataset.gi, 10 );
			var text;
			var value;

			if ( state.grades[ index ] && state.grades[ index ].subject === config.totalRow ) {
				totalCell = cell;

				return;
			}

			text = cell.textContent.trim();

			if ( '' !== text && 'FALSE' !== text ) {
				value = parseInt( text, 10 );

				if ( ! isNaN( value ) ) {
					sum += value;
				}
			}
		} );

		if ( ! totalCell ) {
			return;
		}

		current = totalCell.textContent.trim();

		if ( '' === current ) {
			if ( sum > 0 ) {
				totalCell.textContent = String( sum );
			}

			totalCell.classList.remove( 'test-cell-mismatch' );
			totalCell.removeAttribute( 'title' );
		} else {
			parsed = parseInt( current, 10 );

			if ( ! isNaN( parsed ) && parsed !== sum && sum > 0 ) {
				totalCell.classList.add( 'test-cell-mismatch' );
				totalCell.title = format( strings.sumHint, sum );
			} else {
				totalCell.classList.remove( 'test-cell-mismatch' );
				totalCell.removeAttribute( 'title' );
			}
		}

		if ( state.grades[ totalCell.dataset.gi ] ) {
			state.grades[ totalCell.dataset.gi ].test = totalCell.textContent.trim();
		}
	}

	/* -------------------------------------------------------------- data -- */

	/**
	 * Read every editable field back into a payload.
	 *
	 * @return {Object} Scalar fields keyed by column name.
	 */
	function collectData() {
		var scalar = {};

		Array.prototype.forEach.call( document.querySelectorAll( '.editable-field[data-field]' ), function ( el ) {
			var key = el.dataset.field;
			var nested = el.querySelector( '.vertical-text' );

			scalar[ key ] = ( nested ? nested.textContent : el.textContent ).trim();
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '.editable-field[data-gi]' ), function ( el ) {
			var index = parseInt( el.dataset.gi, 10 );

			if ( state.grades[ index ] ) {
				state.grades[ index ][ el.dataset.gk ] = el.textContent.trim();
			}
		} );

		return scalar;
	}

	/**
	 * Write a loaded certificate back into the sheet.
	 *
	 * @param {Object} cert Certificate row.
	 */
	function applyCert( cert ) {
		Array.prototype.forEach.call( document.querySelectorAll( '.editable-field[data-field]' ), function ( el ) {
			var key = el.dataset.field;
			var nested;

			if ( ! ( key in cert ) ) {
				return;
			}

			nested = el.querySelector( '.vertical-text' );

			if ( nested ) {
				nested.textContent = cert[ key ] || '';
			} else {
				el.textContent = cert[ key ] || '';
			}
		} );
	}

	/**
	 * POST to admin-ajax.
	 *
	 * @param {string} action Action name.
	 * @param {Object} data   Extra fields.
	 * @return {Promise<Object>} Parsed response.
	 */
	function post( action, data ) {
		var payload = new URLSearchParams();

		payload.set( 'action', action );
		payload.set( 'security', config.nonce );

		Object.keys( data || {} ).forEach( function ( key ) {
			payload.set( key, data[ key ] );
		} );

		return window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: payload.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/**
	 * Persist the sheet.
	 *
	 * @param {boolean} asNew Whether to force a new record.
	 */
	function save( asNew ) {
		var scalar = collectData();

		setStatus( strings.saving, 'busy' );

		post( 'deftese_save', Object.assign(
			{
				is_new: asNew ? 'true' : 'false',
				grades_json: JSON.stringify( state.grades ),
				original_id: state.certId
			},
			scalar
		) ).then( function ( res ) {
			if ( res && res.success ) {
				setBadge( res.data.cert_id );
				setStatus( res.data.message, 'ok' );
			} else {
				setStatus( ( res && res.data && res.data.message ) || strings.genericError, 'error' );
			}
		} ).catch( function () {
			setStatus( strings.networkError, 'error' );
		} );
	}

	/**
	 * Load a neighbouring certificate without a page reload.
	 *
	 * @param {HTMLElement} arrow The arrow that was activated.
	 */
	function navigate( arrow ) {
		var targetId = arrow.dataset.navId;

		if ( ! targetId ) {
			return;
		}

		setStatus( strings.loading, 'busy' );

		post( 'deftese_get_cert', { id: targetId } ).then( function ( res ) {
			var cert;
			var url;

			if ( ! res || ! res.success ) {
				setStatus( ( res && res.data && res.data.message ) || strings.loadError, 'error' );

				return;
			}

			cert = res.data.cert;
			state.grades = Array.isArray( cert.grades_json ) ? cert.grades_json : Object.values( cert.grades_json || {} );

			applyCert( cert );
			renderGrades();
			setBadge( cert.id );
			setArrow( els.prevArrow, res.data.prev_id );
			setArrow( els.nextArrow, res.data.next_id );

			try {
				url = new URL( window.location.href );
				url.searchParams.set( 'id', cert.id );
				window.history.pushState( null, '', url.toString() );
			} catch ( e ) {
				// A non-parseable URL only costs the address-bar update.
			}

			setStatus( strings.loaded, 'ok' );
		} ).catch( function () {
			setStatus( strings.networkError, 'error' );
		} );
	}

	/**
	 * Enable or disable a navigation arrow.
	 *
	 * @param {HTMLElement} arrow Arrow element.
	 * @param {string|null} id    Neighbour id.
	 */
	function setArrow( arrow, id ) {
		if ( ! arrow ) {
			return;
		}

		arrow.dataset.navId = id || '';
		arrow.classList.toggle( 'disabled', ! id );
	}

	/* ------------------------------------------------------------- modal -- */

	/**
	 * Show the confirm dialog.
	 *
	 * @param {string}   title     Dialog title.
	 * @param {string}   message   Dialog body.
	 * @param {Function} onConfirm Callback when confirmed.
	 */
	function showModal( title, message, onConfirm ) {
		els.modalTitle.textContent = title;
		els.modalMsg.textContent = message;
		modalCallback = onConfirm;
		els.modal.hidden = false;
		els.modalConfirm.focus();
	}

	/**
	 * Hide the confirm dialog.
	 */
	function hideModal() {
		els.modal.hidden = true;
		modalCallback = null;
	}

	/* ------------------------------------------------------------ import -- */

	/**
	 * Read one CSV file into the open sheet.
	 *
	 * @param {File} file Selected file.
	 */
	function importFile( file ) {
		var reader = new window.FileReader();

		reader.onload = function ( event ) {
			var text = String( event.target.result );

			showModal( strings.confirmImport, format( strings.confirmImportBody, file.name ), function () {
				var mapped;

				try {
					if ( 0xFEFF === text.charCodeAt( 0 ) ) {
						text = text.slice( 1 );
					}

					mapped = window.DeftesePro.csv.applyMap(
						window.DeftesePro.csv.parse( text ).filter( function ( row ) {
							return row && row.length;
						} ),
						config.csvMap
					);

					state.grades = mapped.grades;
					renderGrades();
					applyCert( mapped.scalars );
					setStatus( strings.importSuccess, 'info' );
				} catch ( e ) {
					setStatus( strings.importError, 'error' );
				}
			} );
		};

		// The source spreadsheets are Excel exports in the Windows code page.
		reader.readAsText( file, 'windows-1252' );
	}

	/* -------------------------------------------------------------- wire -- */

	/**
	 * Toggle edit mode.
	 */
	function toggleEditing() {
		state.isEditing = ! state.isEditing;

		els.app.classList.toggle( 'is-editing', state.isEditing );
		els.editButton.setAttribute( 'aria-pressed', state.isEditing ? 'true' : 'false' );

		if ( els.editLabel ) {
			els.editLabel.textContent = state.isEditing ? strings.editOn : strings.editOff;
		}

		Array.prototype.forEach.call( document.querySelectorAll( '.editable-field' ), function ( el ) {
			if ( state.isEditing ) {
				el.setAttribute( 'contenteditable', 'true' );
			} else {
				el.removeAttribute( 'contenteditable' );
			}
		} );

		setStatus( state.isEditing ? strings.editingActive : strings.editingClosed, 'info' );
	}

	/**
	 * Enter or leave fullscreen.
	 */
	function toggleFullscreen() {
		if ( document.fullscreenElement ) {
			if ( document.exitFullscreen ) {
				document.exitFullscreen();
			}

			return;
		}

		if ( els.app.requestFullscreen ) {
			els.app.requestFullscreen().catch( function () {
				// Denied by the browser; the editor still works inline.
			} );
		}
	}

	/**
	 * Cache element references.
	 *
	 * @return {boolean} Whether the editor is present on this page.
	 */
	function cacheElements() {
		els.app = document.getElementById( 'deftese-app' );

		if ( ! els.app ) {
			return false;
		}

		els.scaleWrapper = document.getElementById( 'deftese-scale-wrapper' );
		els.mainWrap = document.getElementById( 'deftese-main-editor-wrap' );
		els.paper = document.getElementById( 'deftese-paper' );
		els.status = document.getElementById( 'deftese-status-msg' );
		els.badge = document.getElementById( 'deftese-id-badge' );
		els.table = document.getElementById( 'deftese-grades-table' );
		els.fileInput = document.getElementById( 'deftese-import-file' );
		els.editButton = document.getElementById( 'btn-toggle-edit' );
		els.editLabel = els.editButton ? els.editButton.querySelector( '.dp-tool__label' ) : null;
		els.fsButton = document.getElementById( 'btn-toggle-fullscreen' );
		els.fsLabel = els.fsButton ? els.fsButton.querySelector( '.dp-tool__label' ) : null;
		els.prevArrow = document.querySelector( '.nav-arrow-left' );
		els.nextArrow = document.querySelector( '.nav-arrow-right' );
		els.modal = document.getElementById( 'deftese-modal' );
		els.modalTitle = document.getElementById( 'deftese-modal-title' );
		els.modalMsg = document.getElementById( 'deftese-modal-msg' );
		els.modalConfirm = document.getElementById( 'deftese-modal-confirm' );
		els.modalCancel = document.getElementById( 'deftese-modal-cancel' );

		return true;
	}

	/**
	 * Attach every listener.
	 */
	function init() {
		if ( ! cacheElements() ) {
			return;
		}

		window.addEventListener( 'resize', recalcScale );
		window.addEventListener( 'scroll', positionNavArrows, { passive: true } );
		recalcScale();

		document.addEventListener( 'fullscreenchange', function () {
			var isFullscreen = !! document.fullscreenElement;

			if ( els.fsLabel ) {
				els.fsLabel.textContent = isFullscreen ? strings.fullscreenOn : strings.fullscreenOff;
			}

			if ( els.fsButton ) {
				els.fsButton.setAttribute( 'aria-pressed', isFullscreen ? 'true' : 'false' );
			}

			window.setTimeout( recalcScale, 100 );
		} );

		els.app.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.deftese-toolbar button, #edit-table-actions button' );

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			switch ( button.id ) {
				case 'btn-toggle-edit':
					toggleEditing();
					break;

				case 'btn-toggle-fullscreen':
					toggleFullscreen();
					break;

				case 'btn-save-cert':
					if ( state.certId ) {
						showModal( strings.confirmSave, format( strings.confirmSaveBody, state.certId ), function () {
							save( false );
						} );
					} else {
						save( false );
					}
					break;

				case 'btn-save-new':
					showModal( strings.confirmNew, strings.confirmNewBody, function () {
						save( true );
					} );
					break;

				case 'btn-import-trigger':
					els.fileInput.click();
					break;

				case 'btn-trigger-print':
					window.print();
					break;

				case 'btn-add-subject':
					collectData();
					state.grades.push( newGradeRow( false ) );
					renderGrades();
					break;

				case 'btn-add-category':
					collectData();
					state.grades.push( newGradeRow( true ) );
					renderGrades();
					break;
			}
		} );

		if ( els.table ) {
			els.table.addEventListener( 'input', function ( event ) {
				if ( event.target.dataset && 'test' === event.target.dataset.gk ) {
					calculateTestPoints();
				}
			} );

			els.table.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '.row-controls button' );
				var index;

				if ( ! button ) {
					return;
				}

				collectData();
				index = parseInt( button.dataset.index, 10 );

				if ( button.classList.contains( 'btn-remove-row' ) ) {
					state.grades.splice( index, 1 );
				} else if ( button.classList.contains( 'btn-row-up' ) && index > 0 ) {
					swap( index, index - 1 );
				} else if ( button.classList.contains( 'btn-row-down' ) && index < state.grades.length - 1 ) {
					swap( index, index + 1 );
				} else {
					return;
				}

				renderGrades();
			} );
		}

		Array.prototype.forEach.call( document.querySelectorAll( '.nav-arrow' ), function ( arrow ) {
			arrow.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				if ( ! arrow.classList.contains( 'disabled' ) ) {
					navigate( arrow );
				}
			} );
		} );

		if ( els.fileInput ) {
			els.fileInput.addEventListener( 'change', function () {
				if ( this.files && this.files[ 0 ] ) {
					importFile( this.files[ 0 ] );
				}

				this.value = '';
			} );
		}

		els.modalCancel.addEventListener( 'click', hideModal );

		els.modalConfirm.addEventListener( 'click', function () {
			var callback = modalCallback;

			hideModal();

			if ( callback ) {
				callback();
			}
		} );

		els.modal.addEventListener( 'click', function ( event ) {
			if ( event.target === els.modal ) {
				hideModal();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! els.modal.hidden ) {
				hideModal();
			}
		} );

		calculateTestPoints();
	}

	/**
	 * Swap two grade rows.
	 *
	 * @param {number} a First index.
	 * @param {number} b Second index.
	 */
	function swap( a, b ) {
		var temp = state.grades[ a ];

		state.grades[ a ] = state.grades[ b ];
		state.grades[ b ] = temp;
	}

	/**
	 * Build a blank grade row.
	 *
	 * @param {boolean} isCategory Whether this is a category heading.
	 * @return {Object} Grade row.
	 */
	function newGradeRow( isCategory ) {
		var row = {
			cat: isCategory,
			bold: false,
			subject: isCategory ? strings.newCategory : strings.newSubject
		};

		gradeKeys.forEach( function ( key ) {
			row[ key ] = '';
			row[ 'grade_' + key ] = '';
		} );

		row.test = '';

		return row;
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}( window, document ) );
