/**
 * WP Foundation site: copy buttons and the deactivation survey demo.
 */
( function () {
	'use strict';

	// The guides menu starts collapsed on small screens, so the guide comes first.
	var menu = document.querySelector( '.docs-menu' );
	if ( menu && window.matchMedia( '(max-width: 899px)' ).matches ) {
		menu.open = false;
	}

	// Copy buttons.
	document.querySelectorAll( '[data-copy]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var source = document.querySelector( button.getAttribute( 'data-copy' ) );
			if ( ! source || ! navigator.clipboard ) {
				return;
			}
			navigator.clipboard.writeText( source.textContent.trim() ).then( function () {
				button.textContent = 'Copied';
				setTimeout( function () {
					button.textContent = 'Copy';
				}, 1500 );
			} );
		} );
	} );

	// Survey demo. The real survey script POSTs to admin-ajax.php; here, requests to
	// "demo:" URLs are answered in the page and the payload is shown instead.
	var output = document.getElementById( 'demo-output' );
	if ( ! output ) {
		return;
	}

	var realFetch = window.fetch ? window.fetch.bind( window ) : null;
	window.fetch = function ( url, options ) {
		if ( typeof url === 'string' && url.indexOf( 'demo:' ) === 0 ) {
			show( options && options.body );
			return Promise.resolve( new Response( '{"success":true}', { headers: { 'Content-Type': 'application/json' } } ) );
		}
		return realFetch( url, options );
	};

	function show( body ) {
		var get = function ( key ) {
			return body && body.get ? body.get( key ) : null;
		};
		// The Feedback object built by DeactivationSurvey::buildFeedback().
		var feedback = {
			reason: get( 'reason' ),
			details: ( get( 'details' ) || '' ).trim().slice( 0, 1000 ),
			plugin: 'acme-bookings',
			plugin_version: '1.1.0',
			wp_version: '6.7',
			php_version: '8.3',
			locale: document.documentElement.lang === 'en' ? 'en_US' : document.documentElement.lang,
			multisite: false,
			submitted_at: Math.floor( Date.now() / 1000 ),
			site_url: get( 'consent' ) ? 'https://example.com' : null,
		};
		output.querySelector( 'code' ).textContent = JSON.stringify( feedback, null, 2 );
	}

	// Start fresh if the page was reloaded after a demo deactivation.
	if ( window.location.hash === '#demo-deactivated' ) {
		history.replaceState( null, '', window.location.pathname + window.location.search );
	}

	var row = document.querySelector( '.demo-plugins tr[data-plugin]' );
	var deactivate = row.querySelector( '.deactivate' );
	var activate = row.querySelector( '.activate' );

	function setDeactivated( isDeactivated ) {
		row.classList.toggle( 'active', ! isDeactivated );
		row.classList.toggle( 'inactive', isDeactivated );
		deactivate.hidden = isDeactivated;
		activate.hidden = ! isDeactivated;
	}

	// The survey sends the browser to the Deactivate link's href when it finishes.
	window.addEventListener( 'hashchange', function () {
		if ( window.location.hash === '#demo-deactivated' ) {
			// In WordPress the browser loads a new page here, which removes the dialog.
			resetDialog();
			setDeactivated( true );
			if ( output.querySelector( 'code' ).textContent.indexOf( '{' ) !== 0 ) {
				output.querySelector( 'code' ).textContent = 'Skipped: nothing was sent, and the plugin was deactivated.';
			}
		}
	} );

	function resetDialog() {
		var dialog = document.querySelector( 'dialog.wpf-survey' );
		if ( ! dialog ) {
			return;
		}
		var form = dialog.querySelector( 'form' );
		var submit = dialog.querySelector( '[type="submit"]' );
		form.reset();
		dialog.querySelectorAll( 'textarea' ).forEach( function ( t ) {
			t.hidden = true;
		} );
		submit.disabled = false;
		submit.classList.remove( 'updating-message' );
		if ( dialog.open ) {
			dialog.close();
		}
	}

	activate.querySelector( 'a' ).addEventListener( 'click', function ( event ) {
		event.preventDefault();
		history.replaceState( null, '', window.location.pathname + window.location.search );
		setDeactivated( false );
		output.querySelector( 'code' ).textContent = 'Click Deactivate to try the survey.';
	} );
} )();
