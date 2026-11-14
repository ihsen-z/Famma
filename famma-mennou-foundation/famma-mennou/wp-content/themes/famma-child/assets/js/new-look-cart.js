/**
 * FAMMA — panier, nouveau look : barre du bas (`cart_dock`), code promo
 * replié (`cart_promo`), « Mettre de côté » (`cart_save`).
 *
 * Ce script ne calcule rien et n'envoie rien lui-même : les montants et le
 * balisage viennent du serveur (inc/new-look-cart-totals.php,
 * inc/new-look-cart-keep.php), les retraits passent par la corbeille de
 * WooCommerce. WooCommerce remplace le formulaire et les totaux à chaque
 * mise à jour : tout est relu après ses événements.
 *
 * Sans script : la barre reste un simple lien, le code promo reste ouvert,
 * « Mettre de côté » reste caché.
 */
( function () {
	'use strict';

	var $ = window.jQuery;

	/* ------------------------------------------------------------
	   Barre du bas
	   ------------------------------------------------------------ */

	var dock = document.querySelector( '[data-famma-cart-dock]' );
	var total = dock ? dock.querySelector( '[data-famma-cart-dock-total]' ) : null;
	var observer = null;
	var seen = false;

	/**
	 * Affiche ou masque la barre selon le panier et le bouton.
	 *
	 * @return {void}
	 */
	function renderDock() {
		var filled = !! document.querySelector( '.woocommerce-cart-form tr.cart_item' );

		dock.hidden = ! filled;
		document.body.classList.toggle( 'famma-cart-dock-on', filled );
		dock.classList.toggle( 'is-away', seen );

		if ( seen ) {
			dock.setAttribute( 'inert', '' );
			dock.setAttribute( 'aria-hidden', 'true' );
		} else {
			dock.removeAttribute( 'inert' );
			dock.removeAttribute( 'aria-hidden' );
		}
	}

	/**
	 * Recopie le total et observe le bouton de commande (remplacé à chaque
	 * mise à jour).
	 *
	 * @return {void}
	 */
	function syncDock() {
		if ( ! dock ) {
			return;
		}

		var source = document.querySelector( '.cart_totals .order-total .woocommerce-Price-amount' );
		var target = document.querySelector( '.cart_totals .wc-proceed-to-checkout' );

		if ( source && total ) {
			total.textContent = source.textContent;
		}

		if ( observer ) {
			observer.disconnect();
		}

		seen = false;

		if ( target && 'IntersectionObserver' in window ) {
			observer = new IntersectionObserver( function ( entries ) {
				seen = entries.some( function ( entry ) {
					return entry.isIntersecting;
				} );
				renderDock();
			} );
			observer.observe( target );
		}

		renderDock();
	}

	/* ------------------------------------------------------------
	   Code promo replié
	   ------------------------------------------------------------ */

	var promoOpen = false;

	/**
	 * Replie le cadre du code, sauf si le client l'a ouvert.
	 *
	 * @return {void}
	 */
	function setupPromo() {
		var toggle = document.querySelector( '[data-famma-cart-promo]' );
		var box = toggle ? toggle.closest( '.coupon' ) : null;

		if ( ! box || toggle.dataset.ready ) {
			return;
		}

		toggle.dataset.ready = '1';
		toggle.hidden = false;
		box.classList.add( 'famma-cart-promo-box' );

		/**
		 * @param {boolean} open Cadre ouvert.
		 * @return {void}
		 */
		function paint( open ) {
			box.classList.toggle( 'is-folded', ! open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}

		paint( promoOpen );

		toggle.addEventListener( 'click', function () {
			promoOpen = ! promoOpen;
			paint( promoOpen );

			var field = box.querySelector( '#coupon_code' );

			if ( promoOpen && field ) {
				field.focus();
			}
		} );
	}

	/* ------------------------------------------------------------
	   Mettre de côté
	   ------------------------------------------------------------ */

	var KEY = 'famma-favourites';
	var pendingDone = '';

	/**
	 * Ajoute un produit à « Ma sélection » (même stockage que favourites.js).
	 *
	 * @param {string} id Identifiant du produit.
	 * @return {boolean} Faux si le stockage du navigateur est indisponible.
	 */
	function keep( id ) {
		try {
			var raw = window.localStorage.getItem( KEY );
			var list = raw ? JSON.parse( raw ) : [];

			list = Array.isArray( list ) ? list : [];

			if ( list.indexOf( id ) === -1 ) {
				list.push( id );
				window.localStorage.setItem( KEY, JSON.stringify( list ) );
				// Compte connecté : famma-core envoie l'ajout au compte.
				document.dispatchEvent( new window.CustomEvent( 'famma:fav', { detail: { id: id, on: true } } ) );
			}

			return true;
		} catch ( error ) {
			return false;
		}
	}

	/**
	 * Montre les boutons du formulaire courant.
	 *
	 * @return {void}
	 */
	function setupSave() {
		var storage = true;

		try {
			window.localStorage.getItem( KEY );
		} catch ( error ) {
			storage = false;
		}

		if ( ! storage ) {
			return;
		}

		Array.prototype.forEach.call( document.querySelectorAll( '[data-famma-cart-save]' ), function ( button ) {
			button.hidden = false;
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-famma-cart-save]' ) : null;

		if ( ! button ) {
			return;
		}

		var row = button.closest( 'tr.cart_item' );
		var remove = row ? row.querySelector( 'td.product-remove a.remove' ) : null;

		if ( ! remove || ! keep( button.getAttribute( 'data-famma-cart-save' ) ) ) {
			return;
		}

		pendingDone = button.getAttribute( 'data-done' ) || '';
		button.disabled = true;
		remove.click();
	} );

	/**
	 * Ajoute « Gardé dans Ma sélection » au message de retrait.
	 *
	 * @return {void}
	 */
	function noteSaved() {
		if ( '' === pendingDone ) {
			return;
		}

		var message = document.querySelector( '.woocommerce-notices-wrapper .woocommerce-message' );

		// Pas encore de message (premier des deux événements) : on attend le suivant.
		if ( ! message ) {
			return;
		}

		if ( ! message.querySelector( '.famma-cart-saved' ) ) {
			var note = document.createElement( 'strong' );

			note.className = 'famma-cart-saved';
			note.textContent = pendingDone;
			message.insertBefore( note, message.firstChild );
		}

		pendingDone = '';
	}

	/* ------------------------------------------------------------
	   Après chaque mise à jour de WooCommerce
	   ------------------------------------------------------------ */

	/**
	 * @return {void}
	 */
	function refresh() {
		syncDock();
		setupPromo();
		setupSave();
		noteSaved();
	}

	// Événements jQuery de WooCommerce (cart.js) : invisibles pour addEventListener.
	if ( $ ) {
		$( document.body ).on( 'updated_cart_totals updated_wc_div wc_cart_emptied', refresh );
	}

	refresh();
}() );
