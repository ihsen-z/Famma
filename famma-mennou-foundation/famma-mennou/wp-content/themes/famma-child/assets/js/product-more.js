/**
 * « Lire la suite » de la fiche produit : accroche sur une ligne, description
 * sur trois lignes (demande du propriétaire, 11/11/2026).
 *
 * Les blocs `[data-famma-more]` sont repliés par `product.css` dès le HTML :
 * aucun saut de mise en page au chargement. Ce script :
 * 1. ouvre et referme le bloc au clic, avec `aria-expanded` à jour ;
 * 2. masque le bouton (classe `is-fit`) quand le texte tient déjà dans les
 *    lignes prévues — une accroche d'une ligne n'a rien à déplier ;
 * 3. refait la mesure quand le bloc change de taille, par exemple quand
 *    l'onglet Description, masqué au chargement, s'ouvre.
 */
( function () {
	'use strict';

	var blocks = document.querySelectorAll( '[data-famma-more]' );

	if ( ! blocks.length ) {
		return;
	}

	/**
	 * Branche un bloc.
	 *
	 * @param {HTMLElement} block Bloc `[data-famma-more]`.
	 */
	function setup( block ) {
		var body = block.querySelector( '.famma-more__body' );
		var toggle = block.querySelector( '.famma-more__toggle' );

		if ( ! body || ! toggle ) {
			return;
		}

		var lines = parseInt( block.getAttribute( 'data-famma-more' ), 10 ) || 3;
		var more = toggle.textContent;
		var less = toggle.getAttribute( 'data-label-less' ) || more;

		/**
		 * Le texte tient-il dans les lignes prévues ? `scrollHeight` donne la
		 * hauteur complète du contenu, replié ou non.
		 */
		function update() {
			if ( block.classList.contains( 'is-open' ) || ! body.clientHeight ) {
				// Ouvert : rien à décider. Hauteur nulle : onglet fermé, on mesurera à l'ouverture.
				return;
			}

			var lineHeight = parseFloat( window.getComputedStyle( body ).lineHeight );
			var limit = lineHeight > 0 ? lines * lineHeight : body.clientHeight;

			block.classList.toggle( 'is-fit', body.scrollHeight <= Math.ceil( limit ) + 1 );
		}

		toggle.addEventListener( 'click', function () {
			var open = ! block.classList.contains( 'is-open' );

			block.classList.toggle( 'is-open', open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			toggle.textContent = open ? less : more;

			// Replié depuis le bas d'un long texte : on ramène le début du bloc à l'écran.
			if ( ! open && block.getBoundingClientRect().top < 0 ) {
				block.scrollIntoView( { block: 'start' } );
			}
		} );

		update();

		if ( 'ResizeObserver' in window ) {
			new window.ResizeObserver( update ).observe( body );
		}

		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( update );
		}
	}

	Array.prototype.forEach.call( blocks, setup );
}() );
