/**
 * FAMMA — boîte « Préférences » de « Mes informations » (case acct_prefs).
 *
 * - FR / عربي : la pastille glisse, puis le choix part et la page se
 *   recharge dans la langue choisie (comme la maquette, 260 ms).
 * - Nouveautés WhatsApp : l'interrupteur bascule tout de suite, le choix
 *   part sans recharger la page ; en cas d'échec il revient et un message
 *   s'affiche.
 * Sans script, chaque choix est un formulaire ordinaire.
 */
( function () {
	'use strict';

	var doc = document;
	var box = doc.querySelector( '[data-famma-prefs]' );

	if ( ! box ) {
		return;
	}

	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var pageLang = ( doc.documentElement.getAttribute( 'lang' ) || '' ).slice( 0, 2 ) === 'ar' ? 'ar' : 'fr';

	var langForm = box.querySelector( '[data-famma-prefs-lang]' );

	if ( langForm ) {
		var seg = langForm.querySelector( '.famma-acct-seg' );
		var sent = false;

		langForm.addEventListener( 'click', function ( e ) {
			var tab = e.target.closest( '.famma-acct-seg__tab' );

			if ( ! tab || sent ) {
				return;
			}

			e.preventDefault();

			var value = tab.value;
			var same = 'true' === tab.getAttribute( 'aria-pressed' ) && value === pageLang;

			langForm.querySelectorAll( '.famma-acct-seg__tab' ).forEach( function ( b ) {
				b.setAttribute( 'aria-pressed', b === tab ? 'true' : 'false' );
			} );

			if ( seg ) {
				seg.classList.toggle( 'is-second', 'ar' === value );
			}

			if ( same ) {
				return;
			}

			sent = true;

			var input = doc.createElement( 'input' );
			input.type = 'hidden';
			input.name = 'famma_prefs_value';
			input.value = value;
			langForm.appendChild( input );

			window.setTimeout( function () {
				langForm.submit();
			}, reduce ? 0 : 260 );
		} );
	}

	var waForm = box.querySelector( '[data-famma-prefs-wa]' );
	var sw = waForm ? waForm.querySelector( '[role="switch"]' ) : null;

	if ( ! waForm || ! sw || ! window.fetch || ! window.FormData ) {
		return;
	}

	var field = waForm.querySelector( 'input[name="famma_prefs_value"]' );
	var err = null;

	function setOn( on ) {
		sw.setAttribute( 'aria-checked', on ? 'true' : 'false' );
		field.value = on ? '0' : '1';
	}

	function fail( message ) {
		if ( ! err ) {
			err = doc.createElement( 'p' );
			err.className = 'famma-acct-prefs__err';
			err.setAttribute( 'role', 'alert' );
			waForm.appendChild( err );
		}

		err.textContent = message || '';
		err.hidden = ! message;
	}

	waForm.addEventListener( 'submit', function ( e ) {
		e.preventDefault();

		if ( 'true' === sw.getAttribute( 'aria-busy' ) ) {
			return;
		}

		var was = 'true' === sw.getAttribute( 'aria-checked' );
		var data = new FormData( waForm );

		data.append( 'famma_prefs_ajax', '1' );
		setOn( ! was );
		fail( '' );
		sw.setAttribute( 'aria-busy', 'true' );

		window.fetch( waForm.getAttribute( 'action' ) || window.location.href, {
			method: 'POST',
			body: data,
			credentials: 'same-origin'
		} ).then( function ( r ) {
			return r.json();
		} ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				throw json;
			}

			setOn( !! json.data.wa );
		} ).catch( function ( json ) {
			setOn( was );
			fail( json && json.data && json.data.message ? json.data.message : sw.getAttribute( 'data-err' ) );
		} ).then( function () {
			sw.removeAttribute( 'aria-busy' );
		} );
	} );
}() );
