/**
 * FAMMA — « Mot de passe oublié ? » et « Supprimer mon compte » (case
 * acct_privacy).
 *
 * - Le dépliant, ouvert sans script, se referme et devient un bouton.
 * - « Demander sur WhatsApp » ajoute au message le numéro tapé dans le
 *   champ de connexion, s'il en est un.
 * - « Supprimer mon compte » montre la confirmation dans la page ;
 *   « Garder mon compte » la referme. Sans script, la confirmation est
 *   visible et « Oui, supprimer » envoie la demande.
 */
( function () {
	'use strict';

	var doc = document;
	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var ease = 'cubic-bezier(.23,1,.32,1)';

	var forgot = doc.querySelector( '.famma-acct-forgot' );

	if ( forgot ) {
		var btn = forgot.querySelector( '.famma-acct-dbtn' );
		var disc = forgot.querySelector( '.famma-acct-disc' );
		var wa = forgot.querySelector( '[data-famma-fg-wa]' );

		if ( btn && disc ) {
			disc.classList.remove( 'is-open' );
			btn.setAttribute( 'aria-expanded', 'false' );
			btn.hidden = false;

			btn.addEventListener( 'click', function () {
				var open = 'true' !== btn.getAttribute( 'aria-expanded' );

				btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				disc.classList.toggle( 'is-open', open );
			} );
		}

		if ( wa ) {
			var base = wa.getAttribute( 'href' );

			wa.addEventListener( 'click', function () {
				var field = doc.getElementById( 'username' );
				var raw = field ? field.value : '';
				var digits = raw.replace( /\D/g, '' ).replace( /^(00216|216)(\d{8})$/, '$2' );
				var label = wa.getAttribute( 'data-num' ) || '';

				wa.setAttribute( 'href', base );

				if ( 8 !== digits.length || -1 === label.indexOf( '%s' ) || -1 !== raw.indexOf( '@' ) ) {
					return;
				}

				var grouped = digits.slice( 0, 2 ) + ' ' + digits.slice( 2, 5 ) + ' ' + digits.slice( 5 );
				var line = label.replace( '%s', grouped );

				// Même codage que le lien préparé par famma-core (%20, %0A).
				wa.setAttribute( 'href', -1 !== base.indexOf( '?text=' ) ? base + encodeURIComponent( '\n' + line ) : base + '?text=' + encodeURIComponent( line ) );
			} );
		}
	}

	var box = doc.querySelector( '[data-famma-del]' );
	var opener = box && box.querySelector( '[data-famma-del-open]' );
	var confirm = box && box.querySelector( '[data-famma-del-confirm]' );
	var keep = box && box.querySelector( '[data-famma-del-keep]' );

	if ( opener && confirm && keep ) {
		confirm.hidden = true;
		opener.hidden = false;
		keep.hidden = false;

		opener.addEventListener( 'click', function () {
			confirm.hidden = false;
			opener.hidden = true;

			if ( ! reduce && confirm.animate ) {
				confirm.animate( [ { opacity: 0, transform: 'translateY(-6px)' }, { opacity: 1, transform: 'none' } ], { duration: 280, easing: ease } );
			}

			keep.focus();
		} );

		keep.addEventListener( 'click', function () {
			confirm.hidden = true;
			opener.hidden = false;
			opener.focus();
		} );
	}
}() );
