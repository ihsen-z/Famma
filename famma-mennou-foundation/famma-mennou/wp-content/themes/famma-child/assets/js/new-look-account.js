/**
 * FAMMA — « Mon compte », case acct_look : onglets Connexion / Création.
 *
 * Range les deux colonnes de WooCommerce (#customer_login) en deux onglets,
 * comme la maquette. Rien n'est créé côté formulaires : mêmes champs, mêmes
 * noms, même envoi. Sans script, les deux formulaires restent l'un sous
 * l'autre, tels que WooCommerce les rend.
 *
 * L'onglet « Créer un compte » s'ouvre d'office si l'adresse #register est
 * demandée, ou si WooCommerce a regarni l'e-mail après un refus.
 */
( function () {
	'use strict';

	var box = document.getElementById( 'customer_login' );

	if ( ! box ) {
		return;
	}

	var cols = [ box.querySelector( '.u-column1' ), box.querySelector( '.u-column2' ) ];

	if ( ! cols[ 0 ] || ! cols[ 1 ] ) {
		return;
	}

	var heads = cols.map( function ( col ) {
		var h = col.querySelector( 'h2' );
		return h ? h.textContent.replace( /\s+/g, ' ' ).trim() : '';
	} );

	if ( ! heads[ 0 ] || ! heads[ 1 ] ) {
		return;
	}

	var cfg = window.fammaAccount || {};
	var seg = document.createElement( 'div' );
	var pill = document.createElement( 'span' );

	seg.className = 'famma-acct-seg';
	seg.setAttribute( 'role', 'tablist' );
	if ( cfg.tabs ) {
		seg.setAttribute( 'aria-label', String( cfg.tabs ) );
	}
	pill.className = 'famma-acct-seg__pill';
	pill.setAttribute( 'aria-hidden', 'true' );
	seg.appendChild( pill );

	var tabs = cols.map( function ( col, i ) {
		var b = document.createElement( 'button' );

		b.type = 'button';
		b.id = 'famma-acct-tab-' + i;
		b.className = 'famma-acct-seg__tab';
		b.textContent = heads[ i ];
		b.setAttribute( 'role', 'tab' );

		if ( ! col.id ) {
			col.id = 'famma-acct-pane-' + i;
		}
		b.setAttribute( 'aria-controls', col.id );
		col.setAttribute( 'role', 'tabpanel' );
		col.setAttribute( 'aria-labelledby', b.id );

		seg.appendChild( b );
		return b;
	} );

	function show( i, focus ) {
		tabs.forEach( function ( b, j ) {
			var on = i === j;
			b.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			b.tabIndex = on ? 0 : -1;
			cols[ j ].hidden = ! on;
		} );
		seg.classList.toggle( 'is-second', 1 === i );
		if ( focus ) {
			tabs[ i ].focus();
		}
	}

	tabs.forEach( function ( b, i ) {
		b.addEventListener( 'click', function () {
			show( i, false );
		} );

		b.addEventListener( 'keydown', function ( e ) {
			var rtl = 'rtl' === window.getComputedStyle( seg ).direction;
			var next = rtl ? 'ArrowLeft' : 'ArrowRight';
			var prev = rtl ? 'ArrowRight' : 'ArrowLeft';
			var to = -1;

			if ( e.key === next || e.key === prev ) {
				to = 1 - i;
			} else if ( 'Home' === e.key ) {
				to = 0;
			} else if ( 'End' === e.key ) {
				to = 1;
			}

			if ( to > -1 ) {
				e.preventDefault();
				show( to, true );
			}
		} );
	} );

	box.insertBefore( seg, box.firstChild );
	box.classList.add( 'is-tabbed' );

	var email = cols[ 1 ].querySelector( 'input[name="email"]' );
	show( '#register' === window.location.hash || ( email && email.value ) ? 1 : 0, false );

	window.addEventListener( 'hashchange', function () {
		if ( '#register' === window.location.hash ) {
			show( 1, true );
		}
	} );
} )();
