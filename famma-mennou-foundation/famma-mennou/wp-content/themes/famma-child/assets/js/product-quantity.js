/**
 * FAMMA — sélecteur de quantité − / + de la fiche produit.
 *
 * Progressif : le champ de quantité de WooCommerce reste la source de vérité.
 * Sans ce script, les deux boutons ne font rien et le client saisit la valeur
 * au clavier — le formulaire d'achat n'en dépend pas.
 *
 * Les bornes viennent du champ (`min`, `max`, `step`), donc d'un produit à
 * stock limité : le bouton « + » ne peut pas proposer une quantité que la
 * boutique refusera au panier.
 */
( function () {
	'use strict';

	/**
	 * Lit un attribut numérique du champ, avec repli.
	 *
	 * @param {HTMLInputElement} input    Champ de quantité.
	 * @param {string}           name     Nom de l'attribut.
	 * @param {number}           fallback Valeur de repli.
	 * @return {number} Valeur numérique.
	 */
	function attr( input, name, fallback ) {
		var value = parseFloat( input.getAttribute( name ) );

		return isNaN( value ) ? fallback : value;
	}

	/**
	 * Applique un pas au champ, en restant dans les bornes.
	 *
	 * @param {HTMLInputElement} input     Champ de quantité.
	 * @param {number}           direction 1 pour incrémenter, -1 pour décrémenter.
	 * @return {void}
	 */
	function nudge( input, direction ) {
		var step = attr( input, 'step', 1 ) || 1;
		var min = attr( input, 'min', 0 );
		var max = attr( input, 'max', Infinity );
		var current = parseFloat( input.value );

		if ( isNaN( current ) ) {
			current = min;
		}

		var next = current + ( direction * step );

		if ( next < min ) {
			next = min;
		}

		if ( next > max ) {
			next = max;
		}

		// Le pas peut être décimal (produits vendus au poids) : on ne tronque pas.
		var rounded = Math.round( next * 1000 ) / 1000;

		if ( rounded === current ) {
			return;
		}

		input.value = String( rounded );

		// WooCommerce écoute `change` pour réévaluer le bouton d'achat.
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-famma-qty-step]' );

		if ( ! button ) {
			return;
		}

		var wrapper = button.closest( '[data-famma-qty]' );
		var input = wrapper ? wrapper.querySelector( 'input.qty' ) : null;

		if ( ! input ) {
			return;
		}

		event.preventDefault();
		nudge( input, 'up' === button.getAttribute( 'data-famma-qty-step' ) ? 1 : -1 );
	} );
}() );
