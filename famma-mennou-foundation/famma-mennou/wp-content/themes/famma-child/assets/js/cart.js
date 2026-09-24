/**
 * FAMMA — panier : mise à jour sans bouton, et compteur d'articles à jour.
 *
 * Le script ne calcule rien : quand une quantité change, il appuie à la place
 * du client sur « Mettre à jour le panier », et WooCommerce recalcule tout en
 * AJAX comme d'habitude. Un délai court regroupe plusieurs appuis sur « + ».
 *
 * Dégradation assumée : sans JavaScript, le bouton reste visible et le panier
 * se met à jour comme avant. Il n'est masqué que par la classe que pose ce
 * script, jamais en CSS seul.
 *
 * WooCommerce ne remplace que le formulaire et les totaux : le libellé
 * « N articles dans votre panier » de l'en-tête est recalculé ici, à partir
 * des champs de quantité du nouveau formulaire.
 */
( function ( $ ) {
	'use strict';

	var DELAY = 600;
	var timer = null;

	document.documentElement.classList.add( 'famma-cart-auto' );

	/**
	 * Demande la mise à jour du panier à WooCommerce.
	 *
	 * @return {void}
	 */
	function submit() {
		var button = document.querySelector( '.woocommerce-cart-form button[name="update_cart"]' );

		if ( ! button ) {
			return;
		}

		// WooCommerce désactive le bouton tant que rien n'a changé.
		button.disabled = false;
		button.removeAttribute( 'aria-disabled' );
		button.click();
	}

	/**
	 * Réécrit « N articles dans votre panier ».
	 *
	 * @param {boolean} emptied Le panier vient-il d'être vidé ?
	 * @return {void}
	 */
	function refreshCount( emptied ) {
		var label = document.querySelector( '[data-famma-cart-count]' );

		if ( ! label ) {
			return;
		}

		var count = 0;

		if ( ! emptied ) {
			document.querySelectorAll( '.woocommerce-cart-form input.qty' ).forEach( function ( input ) {
				var value = parseFloat( input.value );
				count += isNaN( value ) ? 0 : value;
			} );
		}

		if ( 0 === count ) {
			label.textContent = label.getAttribute( 'data-empty' );
		} else if ( 1 === count ) {
			label.textContent = label.getAttribute( 'data-one' );
		} else {
			label.textContent = label.getAttribute( 'data-many' ).replace( '%d', String( count ) );
		}
	}

	document.addEventListener( 'change', function ( event ) {
		if ( ! event.target.matches( '.woocommerce-cart-form input.qty' ) ) {
			return;
		}

		window.clearTimeout( timer );
		timer = window.setTimeout( submit, DELAY );
	} );

	// Événements jQuery émis par le script du panier de WooCommerce.
	$( document.body ).on( 'updated_wc_div updated_cart_totals', function () {
		refreshCount( false );
	} );

	$( document.body ).on( 'wc_cart_emptied', function () {
		refreshCount( true );
	} );
}( jQuery ) );
