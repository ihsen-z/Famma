/**
 * Nouveau look — onglet « Ma sélection » (case acct_favs), comme la maquette.
 *
 * Le cœur est celui de la boutique (`favourites.js`) : c'est lui qui retire
 * le produit du navigateur, et famma-core qui l'envoie au compte. Ici :
 * - la carte rétrécit et s'efface, un message « Retiré… » propose
 *   « Annuler » pendant 5 secondes ;
 * - la mascotte revient quand la sélection est vide ;
 * - si l'accord avec le compte change la liste affichée (favori ajouté sur
 *   un autre téléphone), la page se recharge une fois.
 */
( function () {
	'use strict';

	var doc = document;
	var grid = doc.querySelector( '[data-famma-favs-panel]' );

	if ( ! grid ) {
		return;
	}

	var RM = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var empty = doc.querySelector( '.famma-acct-fempty' );
	var toast = doc.querySelector( '.famma-acct-toast' );
	var toastText = toast && toast.querySelector( 'span' );
	var toastBtn = toast && toast.querySelector( 'button' );
	var shown = ( grid.getAttribute( 'data-ids' ) || '' ).split( ',' ).filter( Boolean );
	var pending = null;
	var timer = 0;
	var RELOAD = 'famma-favs-reloaded';

	function refreshEmpty() {
		var left = grid.querySelectorAll( '.famma-acct-fv:not([hidden])' ).length;

		if ( empty ) {
			empty.hidden = left > 0;
		}
	}

	function hideToast() {
		window.clearTimeout( timer );

		if ( toast ) {
			toast.classList.remove( 'is-on' );
			window.setTimeout( function () {
				if ( ! toast.classList.contains( 'is-on' ) ) {
					toast.hidden = true;
				}
			}, 240 );
		}
	}

	// Retrait confirmé : la carte quitte la page.
	function settle() {
		if ( pending ) {
			pending.card.remove();
			pending = null;
		}
		hideToast();
	}

	function showToast() {
		if ( ! toast || ! toastText || ! toastBtn ) {
			return;
		}

		toastText.textContent = grid.getAttribute( 'data-gone' ) || '';
		toastBtn.textContent = grid.getAttribute( 'data-undo' ) || '';
		toastBtn.hidden = ! toastBtn.textContent;
		toast.hidden = false;
		void toast.offsetWidth;
		toast.classList.add( 'is-on' );
		window.clearTimeout( timer );
		timer = window.setTimeout( settle, 5000 );
	}

	function animate( el, frames, options ) {
		if ( RM || ! el.animate ) {
			return { finished: Promise.resolve() };
		}
		return el.animate( frames, options );
	}

	Array.prototype.forEach.call( grid.querySelectorAll( '.famma-acct-heart' ), function ( heart ) {
		// Après le gestionnaire de favourites.js, posé sur le même bouton :
		// aria-pressed dit déjà ce qui vient de se passer.
		heart.addEventListener( 'click', function () {
			var card = heart.closest( '.famma-acct-fv' );
			var id = heart.getAttribute( 'data-famma-fav' );

			if ( ! card || 'false' !== heart.getAttribute( 'aria-pressed' ) ) {
				return;
			}

			settle();
			shown = shown.filter( function ( x ) {
				return x !== id;
			} );
			pending = { card: card, heart: heart, id: id };

			animate( heart, [ { transform: 'scale(1)' }, { transform: 'scale(.7)' }, { transform: 'scale(1)' } ], { duration: 300 } );
			animate( card, [ { opacity: 1, transform: 'none' }, { opacity: 0, transform: 'scale(.9)' } ], { duration: 300, delay: 120, easing: 'ease-in', fill: 'forwards' } ).finished.then( function () {
				if ( pending && pending.card === card ) {
					card.hidden = true;
					refreshEmpty();
				}
			} );

			showToast();
		} );
	} );

	if ( toastBtn ) {
		toastBtn.addEventListener( 'click', function () {
			if ( ! pending ) {
				return;
			}

			var undo = pending;
			pending = null;
			hideToast();

			// Le cœur remet le produit dans la sélection (navigateur et compte).
			undo.heart.click();
			shown.push( undo.id );
			undo.card.hidden = false;
			refreshEmpty();

			if ( undo.card.getAnimations ) {
				undo.card.getAnimations().forEach( function ( a ) {
					a.cancel();
				} );
			}
			animate( undo.card, [ { opacity: 0, transform: 'translateY(12px) scale(.97)' }, { opacity: 1, transform: 'none' } ], { duration: 460 } );
			undo.heart.focus();
		} );
	}

	doc.addEventListener( 'famma:favs-synced', function ( event ) {
		var favs = event.detail && Array.isArray( event.detail.favs ) ? event.detail.favs.map( String ) : null;
		var same;

		if ( ! favs ) {
			return;
		}

		same = favs.length === shown.length && favs.every( function ( id ) {
			return shown.indexOf( id ) !== -1;
		} );

		try {
			if ( same ) {
				window.sessionStorage.removeItem( RELOAD );
				return;
			}

			if ( window.sessionStorage.getItem( RELOAD ) ) {
				return;
			}

			window.sessionStorage.setItem( RELOAD, '1' );
			window.location.reload();
		} catch ( e ) {
			// Sans stockage de session : pas de rechargement, la liste sera
			// juste à la prochaine visite.
		}
	} );
}() );
