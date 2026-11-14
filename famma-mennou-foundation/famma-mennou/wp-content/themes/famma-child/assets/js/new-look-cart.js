/**
 * FAMMA — panier, case `cart_dock` : barre du bas sur téléphone.
 *
 * La barre est rendue par le serveur avec le panier courant
 * (inc/new-look-cart-totals.php). Ce script ne calcule rien :
 * - après chaque mise à jour de WooCommerce, il recopie le total affiché
 *   dans le récapitulatif ;
 * - il masque la barre quand le vrai bouton « Passer la commande » est à
 *   l'écran (elle le doublerait) et quand le panier est vide.
 *
 * Sans script, la barre reste affichée : c'est un simple lien vers la
 * commande. Sur ordinateur, la feuille la cache.
 */
( function () {
	'use strict';

	var dock = document.querySelector( '[data-famma-cart-dock]' );

	if ( ! dock ) {
		return;
	}

	var total = dock.querySelector( '[data-famma-cart-dock-total]' );
	var observer = null;
	var seen = false;

	/**
	 * Affiche ou masque la barre selon le panier et le bouton.
	 *
	 * @return {void}
	 */
	function render() {
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
	 * Observe le bouton de commande, remplacé à chaque mise à jour.
	 *
	 * @return {void}
	 */
	function watch() {
		var target = document.querySelector( '.cart_totals .wc-proceed-to-checkout' );

		if ( observer ) {
			observer.disconnect();
		}

		seen = false;

		if ( ! target || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		observer = new IntersectionObserver( function ( entries ) {
			seen = entries.some( function ( entry ) {
				return entry.isIntersecting;
			} );
			render();
		} );
		observer.observe( target );
	}

	/**
	 * Relit le total et le bouton après une mise à jour du panier.
	 *
	 * @return {void}
	 */
	function sync() {
		var source = document.querySelector( '.cart_totals .order-total .woocommerce-Price-amount' );

		if ( source && total ) {
			total.textContent = source.textContent;
		}

		watch();
		render();
	}

	// Événements jQuery de WooCommerce (cart.js) : invisibles pour addEventListener.
	if ( window.jQuery ) {
		window.jQuery( document.body ).on( 'updated_cart_totals updated_wc_div wc_cart_emptied', sync );
	}

	sync();
}() );
