/**
 * CTA collant de la fiche produit (§13, §30).
 *
 * La barre ne s'affiche que lorsque le vrai bouton « Ajouter au panier » est
 * sorti de l'écran : tant qu'il est visible, une seconde barre le doublerait
 * inutilement et mangerait de la hauteur sur mobile.
 *
 * Le bouton de la barre ne réimplémente rien — il déclenche le formulaire
 * WooCommerce d'origine, donc quantité, variations et validations restent
 * celles du cœur.
 */
( function () {
	'use strict';

	var bar = document.querySelector( '.famma-sticky-cta' );
	var form = document.querySelector( 'form.cart' );

	if ( ! bar ) {
		return;
	}

	/*
	 * Aucun formulaire d'achat sur la page : boutique en « bientôt
	 * disponible », produit non achetable, ou gabarit remplacé. La barre
	 * n'aurait alors rien à déclencher. Un CTA redondant vaut mieux qu'un
	 * CTA absent — mais un CTA mort vaut moins que pas de CTA du tout.
	 */
	if ( ! form ) {
		bar.remove();
		return;
	}

	var realButton = form.querySelector( 'button[type="submit"], input[type="submit"]' );
	var barButton = bar.querySelector( '.famma-sticky-cta__button' );

	if ( barButton && realButton ) {
		barButton.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			realButton.click();
		} );
	}

	/*
	 * Amélioration progressive : la barre est visible par défaut en CSS, et
	 * ce script ne fait que la ranger quand le vrai bouton d'achat est déjà
	 * à l'écran. Si le script échoue ou qu'IntersectionObserver manque, la
	 * barre reste affichée — un CTA redondant vaut infiniment mieux qu'un
	 * CTA absent sur la page qui fait vendre.
	 */
	if ( ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	var observer = new IntersectionObserver(
		function ( entries ) {
			entries.forEach( function ( entry ) {
				bar.classList.toggle( 'is-hidden', entry.isIntersecting );
			} );
		},
		{ rootMargin: '0px 0px -40px 0px' }
	);

	observer.observe( form );
}() );
