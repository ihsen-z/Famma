/**
 * Saisies à relancer : statut et remarque enregistrés sur place, confirmation
 * avant de créer une commande ou de supprimer, alerte « nouvelles saisies ».
 * Le serveur revérifie nonce et droit à chaque envoi.
 */
( function () {
	'use strict';
	var cfg = window.fammaInputs;
	var root = document.querySelector( '.famma-inputs' );
	if ( ! cfg || ! root ) {
		return;
	}
	root.classList.add( 'has-js' );

	function post( data ) {
		var body = new URLSearchParams();
		body.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return fetch( cfg.url, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) {
			return r.json();
		} ).then( function ( res ) {
			if ( ! res || ! res.success ) {
				throw new Error( 'famma' );
			}
			return res.data;
		} );
	}

	function flash( el, ok ) {
		var out = el.parentNode.querySelector( '.famma-inputs__saved' );
		if ( ! out ) {
			return;
		}
		out.textContent = ok ? cfg.i18n.saved : cfg.i18n.error;
		out.classList.toggle( 'is-error', ! ok );
		window.clearTimeout( out._t );
		out._t = window.setTimeout( function () {
			out.textContent = '';
		}, ok ? 2000 : 6000 );
	}

	function counts( map ) {
		var all = 0;
		Object.keys( map || {} ).forEach( function ( key ) {
			var card = root.querySelector( '.famma-inputs__card[data-status="' + key + '"] .famma-inputs__count' );
			all += map[ key ];
			if ( card ) {
				card.textContent = map[ key ];
			}
		} );
		var total = root.querySelector( '.famma-inputs__card[data-status="all"] .famma-inputs__count' );
		if ( total && map ) {
			total.textContent = all;
		}
	}

	root.addEventListener( 'focusin', function ( e ) {
		if ( e.target.classList.contains( 'famma-inputs__status' ) ) {
			e.target.setAttribute( 'data-prev', e.target.value );
		}
	} );

	root.addEventListener( 'change', function ( e ) {
		var sel = e.target;
		if ( ! sel.classList.contains( 'famma-inputs__status' ) ) {
			return;
		}
		var row = sel.closest( 'tr' );
		sel.disabled = true;
		post( Object.assign( { action: cfg.update, id: sel.getAttribute( 'data-id' ), field: 'status', value: sel.value }, cfg.scope || {} ) )
			.then( function ( data ) {
				row.className = row.className.replace( /famma-inputs__row--\S+/, 'famma-inputs__row--' + data.value );
				sel.setAttribute( 'data-prev', data.value );
				counts( data.counts );
				flash( sel, true );
			} )
			.catch( function () {
				// Le serveur a refusé : la liste reprend la valeur enregistrée.
				if ( sel.hasAttribute( 'data-prev' ) ) {
					sel.value = sel.getAttribute( 'data-prev' );
				}
				flash( sel, false );
			} )
			.then( function () {
				sel.disabled = false;
			} );
	} );

	root.addEventListener( 'focusout', function ( e ) {
		var area = e.target;
		if ( ! area.classList.contains( 'famma-inputs__note' ) || area.value === area.defaultValue ) {
			return;
		}
		post( { action: cfg.update, id: area.getAttribute( 'data-id' ), field: 'note', value: area.value } )
			.then( function ( data ) {
				area.value = data.value;
				area.defaultValue = data.value;
				flash( area, true );
			} )
			.catch( function () {
				flash( area, false );
			} );
	} );

	root.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		var text = form.classList.contains( 'famma-inputs__order' ) ? cfg.i18n.order : ( form.classList.contains( 'famma-inputs__delete' ) ? cfg.i18n.confirm : '' );
		if ( text && ! window.confirm( text ) ) {
			e.preventDefault();
			return;
		}
		if ( form.classList.contains( 'famma-inputs__order' ) ) {
			var btn = form.querySelector( 'button' );
			window.setTimeout( function () {
				btn.disabled = true;
			}, 0 );
		}
	} );

	// Nouvelles saisies arrivées depuis l'ouverture de la page (toutes les 60 s).
	var fresh = root.querySelector( '.famma-inputs__fresh' );
	window.setInterval( function () {
		if ( document.hidden || ! fresh ) {
			return;
		}
		post( { action: cfg.poll, since: cfg.since } ).then( function ( data ) {
			if ( data.fresh > 0 ) {
				var link = fresh.querySelector( 'a' );
				link.textContent = cfg.i18n.fresh.replace( '%d', data.fresh );
				link.href = window.location.href;
				fresh.hidden = false;
			}
		} ).catch( function () {} );
	}, 60000 );
}() );
