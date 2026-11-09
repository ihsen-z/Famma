/**
 * Remarque des commandes : édition en ligne dans WooCommerce → Commandes.
 *
 * Clic sur « Modifier » : zone de texte, Ctrl+Entrée enregistre, Échap annule.
 * Envoi par admin-ajax (nonce + droit vérifiés côté serveur).
 */
( function () {
	'use strict';
	var cfg = window.fammaOrderNote;
	if ( ! cfg ) {
		return;
	}

	function label( box, note ) {
		box.querySelector( '.famma-note__text' ).textContent = note;
		box.querySelector( '.famma-note__edit' ).textContent = note ? cfg.i18n.edit : cfg.i18n.add;
	}

	function close( box ) {
		var form = box.querySelector( '.famma-note__form' );
		if ( form ) {
			form.remove();
		}
		box.classList.remove( 'is-editing' );
	}

	function save( box, area, status ) {
		var body = new URLSearchParams();
		body.append( 'action', cfg.action );
		body.append( 'nonce', cfg.nonce );
		body.append( 'order_id', box.getAttribute( 'data-order' ) );
		body.append( 'note', area.value );
		status.textContent = cfg.i18n.saving;
		area.disabled = true;
		fetch( cfg.url, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				if ( ! res || ! res.success ) {
					throw new Error( 'save' );
				}
				label( box, res.data.note );
				close( box );
			} )
			.catch( function () {
				area.disabled = false;
				status.textContent = cfg.i18n.error;
			} );
	}

	function open( box ) {
		if ( box.classList.contains( 'is-editing' ) ) {
			return;
		}
		box.classList.add( 'is-editing' );
		var form = document.createElement( 'div' );
		var area = document.createElement( 'textarea' );
		var ok = document.createElement( 'button' );
		var no = document.createElement( 'button' );
		var status = document.createElement( 'span' );
		form.className = 'famma-note__form';
		area.rows = 3;
		area.maxLength = cfg.max;
		area.value = box.querySelector( '.famma-note__text' ).textContent;
		ok.type = 'button';
		ok.className = 'button button-primary button-small';
		ok.textContent = cfg.i18n.save;
		no.type = 'button';
		no.className = 'button button-small';
		no.textContent = cfg.i18n.cancel;
		status.className = 'famma-note__status';
		status.setAttribute( 'role', 'status' );
		ok.addEventListener( 'click', function () {
			save( box, area, status );
		} );
		no.addEventListener( 'click', function () {
			close( box );
		} );
		area.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				close( box );
			} else if ( 'Enter' === e.key && ( e.ctrlKey || e.metaKey ) ) {
				save( box, area, status );
			}
		} );
		form.append( area, ok, no, status );
		box.appendChild( form );
		area.focus();
	}

	document.addEventListener( 'click', function ( e ) {
		var box = e.target.closest ? e.target.closest( '.famma-note' ) : null;
		if ( ! box ) {
			return;
		}
		// La ligne ouvre la commande au clic ; la classe `no-link` de la
		// cellule l'en empêche (règle de WooCommerce).
		if ( e.target.closest( '.famma-note__edit' ) ) {
			e.preventDefault();
			open( box );
		}
	} );
}() );
