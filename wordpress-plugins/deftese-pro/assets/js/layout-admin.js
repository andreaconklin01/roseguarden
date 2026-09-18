/**
 * Dëftesë PRO — layout manager screen.
 *
 * Colour pickers, the media-library watermark picker, and the derived class
 * column widths.
 */
( function ( $, window, document ) {
	'use strict';

	var config = window.defteseLayout || {};
	var strings = config.i18n || {};

	/**
	 * Turn the two colour inputs into WordPress colour pickers.
	 */
	function initColorPickers() {
		if ( $.fn.wpColorPicker ) {
			$( '.dpl-color' ).wpColorPicker();
		}
	}

	/**
	 * Wire the media-library picker for the background watermark.
	 */
	function initMediaPicker() {
		var frame;
		var field = $( '#logo_url' );
		var preview = $( '#logo_preview' );

		$( '#upload_logo_button' ).on( 'click', function ( event ) {
			event.preventDefault();

			if ( ! frame ) {
				frame = window.wp.media( {
					title: strings.mediaTitle,
					button: { text: strings.mediaButton },
					library: { type: 'image' },
					multiple: false
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();

					field.val( attachment.url );
					preview.attr( 'src', attachment.url ).prop( 'hidden', false );
				} );
			}

			frame.open();
		} );

		$( '#remove_logo_button' ).on( 'click', function ( event ) {
			event.preventDefault();

			field.val( '' );
			preview.prop( 'hidden', true ).attr( 'src', '' );
		} );
	}

	/**
	 * Split the width left over by columns 1 and 6 across the four class columns.
	 */
	function initColumnMath() {
		var first = document.getElementById( 'col1' );
		var last = document.getElementById( 'col6' );
		var warning = document.getElementById( 'col-total-warning' );
		var derived = [ 'col2', 'col3', 'col4', 'col5' ].map( function ( id ) {
			return document.getElementById( id );
		} );

		if ( ! first || ! last || derived.some( function ( el ) { return ! el; } ) ) {
			return;
		}

		function recalculate() {
			var remainder = 100 - ( parseFloat( first.value ) || 0 ) - ( parseFloat( last.value ) || 0 );
			var share;

			if ( remainder <= 0 ) {
				warning.textContent = strings.overflow;
				warning.hidden = false;

				return;
			}

			warning.hidden = true;
			share = ( remainder / derived.length ).toFixed( 1 );

			derived.forEach( function ( el ) {
				el.value = share;
			} );
		}

		first.addEventListener( 'input', recalculate );
		last.addEventListener( 'input', recalculate );
		recalculate();
	}

	$( function () {
		initColorPickers();
		initMediaPicker();
		initColumnMath();
	} );
}( window.jQuery, window, document ) );
