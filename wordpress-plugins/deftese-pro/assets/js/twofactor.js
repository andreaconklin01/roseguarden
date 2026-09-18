/**
 * Dëftesë PRO — two-factor settings panel.
 */
( function ( window, document ) {
	'use strict';

	var config = window.deftese2fa || {};
	var strings = config.i18n || {};
	var message;

	/**
	 * Show a status line.
	 *
	 * @param {string} text Message.
	 * @param {string} tone One of ok, busy, error.
	 */
	function setMessage( text, tone ) {
		if ( ! message ) {
			return;
		}

		message.textContent = text || '';
		message.setAttribute( 'data-tone', tone || '' );
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
	 * Read and clear a code input.
	 *
	 * @param {string} id Input id.
	 * @return {string} Trimmed code.
	 */
	function codeFrom( id ) {
		var input = document.getElementById( id );

		return input ? input.value.trim() : '';
	}

	/**
	 * Boot.
	 */
	function init() {
		var startButton = document.getElementById( 'btn-2fa-start' );
		var confirmButton = document.getElementById( 'btn-2fa-confirm' );
		var disableButton = document.getElementById( 'btn-2fa-disable' );

		message = document.getElementById( 'deftese-2fa-msg' );

		if ( startButton ) {
			startButton.addEventListener( 'click', function () {
				setMessage( strings.preparing, 'busy' );

				post( 'deftese_2fa_setup', {} ).then( function ( res ) {
					if ( ! res || ! res.success ) {
						setMessage( ( res && res.data && res.data.message ) || strings.error, 'error' );

						return;
					}

					document.getElementById( 'deftese-2fa-secret' ).textContent = res.data.secret;
					document.getElementById( 'deftese-2fa-otpauth-link' ).href = res.data.otpauth;
					document.getElementById( 'deftese-2fa-setup-start' ).hidden = true;
					document.getElementById( 'deftese-2fa-setup-details' ).hidden = false;
					setMessage( '', '' );
				} ).catch( function () {
					setMessage( strings.error, 'error' );
				} );
			} );
		}

		if ( confirmButton ) {
			confirmButton.addEventListener( 'click', function () {
				setMessage( strings.verifying, 'busy' );

				post( 'deftese_2fa_confirm', { code: codeFrom( 'deftese-2fa-confirm-code' ) } ).then( function ( res ) {
					if ( res && res.success ) {
						setMessage( strings.enabled, 'ok' );
						window.setTimeout( function () {
							window.location.reload();
						}, 900 );
					} else {
						setMessage( ( res && res.data && res.data.message ) || strings.badCode, 'error' );
					}
				} ).catch( function () {
					setMessage( strings.error, 'error' );
				} );
			} );
		}

		if ( disableButton ) {
			disableButton.addEventListener( 'click', function () {
				setMessage( strings.verifying, 'busy' );

				post( 'deftese_2fa_disable', { code: codeFrom( 'deftese-2fa-disable-code' ) } ).then( function ( res ) {
					if ( res && res.success ) {
						setMessage( strings.disabled, 'ok' );
						window.setTimeout( function () {
							window.location.reload();
						}, 900 );
					} else {
						setMessage( ( res && res.data && res.data.message ) || strings.badCode, 'error' );
					}
				} ).catch( function () {
					setMessage( strings.error, 'error' );
				} );
			} );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}( window, document ) );
