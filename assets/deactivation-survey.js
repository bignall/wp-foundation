/**
 * Deactivation survey for plugins built on Arbe WP Foundation.
 *
 * This file is a function expression; PHP appends the config and the call:
 * `(function (config) { ... })({ ...config });`
 */
( function ( config ) {
	'use strict';

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			if ( key === 'text' ) {
				node.textContent = attrs[ key ];
			} else {
				node.setAttribute( key, attrs[ key ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( child );
			}
		} );
		return node;
	}

	function init() {
		var row = document.querySelector( 'tr[data-plugin="' + CSS.escape( config.basename ) + '"]' );
		var link = row && row.querySelector( '.deactivate a' );
		if ( ! link || typeof HTMLDialogElement !== 'function' ) {
			return;
		}

		var s = config.strings;
		var id = 'wpf-survey-' + config.action;
		var dialog = buildDialog( id, s );
		document.body.appendChild( dialog );

		link.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			dialog.showModal();
		} );

		function deactivate() {
			window.location.href = link.href;
		}

		dialog.querySelector( '[data-wpf="skip"]' ).addEventListener( 'click', deactivate );
		dialog.querySelector( '[data-wpf="cancel"]' ).addEventListener( 'click', function () {
			dialog.close();
		} );

		dialog.querySelector( 'form' ).addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var form = event.target;
			var chosen = form.querySelector( 'input[name="reason"]:checked' );
			var error = form.querySelector( '.wpf-survey__error' );

			if ( ! chosen ) {
				error.hidden = false;
				return;
			}

			var submit = form.querySelector( '[type="submit"]' );
			submit.disabled = true;
			submit.classList.add( 'updating-message' );

			var body = new FormData();
			body.append( 'action', config.action );
			body.append( 'nonce', config.nonce );
			body.append( 'reason', chosen.value );
			var details = form.querySelector( '[name="details-' + chosen.value + '"]' );
			body.append( 'details', details ? details.value : '' );
			var consent = form.querySelector( '[name="consent"]' );
			body.append( 'consent', consent && consent.checked ? '1' : '' );

			// Never let feedback delay deactivation for long.
			var controller = typeof AbortController === 'function' ? new AbortController() : null;
			var timer = setTimeout( function () {
				if ( controller ) {
					controller.abort();
				}
			}, 4000 );

			fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
				signal: controller ? controller.signal : undefined,
			} )
				.catch( function () {} )
				.then( function () {
					clearTimeout( timer );
					deactivate();
				} );
		} );
	}

	function buildDialog( id, s ) {
		var list = el( 'ul', { class: 'wpf-survey__reasons' } );

		config.reasons.forEach( function ( reason ) {
			var inputId = id + '-' + reason.id;
			var radio = el( 'input', { type: 'radio', name: 'reason', value: reason.id, id: inputId } );
			var details = null;

			if ( reason.detailsPrompt ) {
				details = el( 'textarea', {
					name: 'details-' + reason.id,
					placeholder: reason.detailsPrompt,
					'aria-label': reason.detailsPrompt,
					maxlength: String( config.maxLength ),
					rows: '2',
					hidden: '',
				} );
			}

			radio.addEventListener( 'change', function () {
				list.querySelectorAll( 'textarea' ).forEach( function ( t ) {
					t.hidden = t !== details;
				} );
				list.parentNode.querySelector( '.wpf-survey__error' ).hidden = true;
				if ( details ) {
					details.focus();
				}
			} );

			list.appendChild(
				el( 'li', {}, [ el( 'label', { for: inputId }, [ radio, document.createTextNode( ' ' + reason.label ) ] ), details ] )
			);
		} );

		var consent = null;
		if ( config.askConsent ) {
			consent = el( 'p', { class: 'wpf-survey__consent' }, [
				el( 'label', {}, [ el( 'input', { type: 'checkbox', name: 'consent' } ), document.createTextNode( ' ' + s.consent ) ] ),
			] );
		}

		var form = el( 'form', { method: 'dialog' }, [
			el( 'h2', { id: id + '-title', text: s.title } ),
			el( 'p', { text: s.intro } ),
			list,
			el( 'p', { class: 'wpf-survey__error', role: 'alert', text: s.required, hidden: '' } ),
			s.privacy ? el( 'p', { class: 'wpf-survey__privacy description', text: s.privacy } ) : null,
			consent,
			el( 'div', { class: 'wpf-survey__actions' }, [
				el( 'button', { type: 'button', class: 'button-link', 'data-wpf': 'cancel', text: s.cancel } ),
				el( 'span', { class: 'wpf-survey__spacer' } ),
				el( 'button', { type: 'button', class: 'button', 'data-wpf': 'skip', text: s.skip } ),
				el( 'button', { type: 'submit', class: 'button button-primary', text: s.submit } ),
			] ),
		] );

		return el( 'dialog', { id: id, class: 'wpf-survey', 'aria-labelledby': id + '-title' }, [ form ] );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )
