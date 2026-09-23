/**
 * CTA collant de la fiche produit (§13, §30).
 *
 * La barre ne s'affiche que lorsque le vrai point d'achat est sorti de
 * l'écran : tant qu'il est visible, une seconde barre le doublerait
 * inutilement et mangerait de la hauteur sur mobile.
 *
 * ── Ce que la barre déclenche dépend du parcours en place ───────────────────
 *
 * Depuis l'option C de la décision D-8, la fiche produit porte un formulaire
 * de commande directe. Quand il est là, c'est LUI le point d'achat : la barre
 * y amène le client et met le curseur dans le premier champ à remplir. Elle
 * n'ajoute rien au panier — ce serait envoyer le client vers un parcours que
 * le formulaire est précisément là pour éviter.
 *
 * En son absence (produit variable, formulaire non affiché), la barre reprend
 * son rôle d'origine : déclencher le formulaire d'achat de WooCommerce, sans
 * rien réimplémenter — quantité, variations et validations restent celles du
 * cœur.
 */
( function () {
	'use strict';

	var bar = document.querySelector( '.famma-sticky-cta' );

	if ( ! bar ) {
		return;
	}

	var express = document.querySelector( '.famma-express__form' );
	var cartForm = document.querySelector( 'form.cart' );

	/*
	 * Le point d'achat de la page, dans l'ordre de priorité. Il sert deux
	 * fois : ce que la barre déclenche, et ce qu'elle observe pour savoir
	 * quand s'effacer.
	 */
	var target = express || cartForm;

	/*
	 * Aucun point d'achat sur la page : boutique en « bientôt disponible »,
	 * produit non achetable, ou gabarit remplacé. La barre n'aurait alors rien
	 * à déclencher. Un CTA redondant vaut mieux qu'un CTA absent — mais un CTA
	 * mort vaut moins que pas de CTA du tout.
	 */
	if ( ! target ) {
		bar.remove();
		return;
	}

	var barButton = bar.querySelector( '.famma-sticky-cta__button' );

	/**
	 * Indique si l'animation doit être supprimée.
	 *
	 * @return {boolean}
	 */
	function reducedMotion() {
		return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	/**
	 * Amène au formulaire de commande et met le curseur au bon endroit.
	 *
	 * Le focus est posé avec `preventScroll` : sans lui, le navigateur saute
	 * immédiatement au champ et annule le défilement en cours, ce qui prive le
	 * client de tout repère sur l'endroit où il vient d'arriver.
	 *
	 * @return {void}
	 */
	function goToExpress() {
		/*
		 * Défile vers la SECTION, pas vers le formulaire : c'est elle qui porte
		 * le titre « Commander », et sa `scroll-margin` la dégage de l'en-tête
		 * collant. Viser le formulaire laissait titre et premier champ dessous.
		 */
		var section = express.closest( '.famma-express' ) || express;

		section.scrollIntoView( {
			behavior: reducedMotion() ? 'auto' : 'smooth',
			block: 'start',
		} );

		var fields = express.querySelectorAll( 'input:not([type="hidden"]), select' );
		var first = null;

		for ( var i = 0; i < fields.length; i++ ) {
			if ( 'checkbox' !== fields[ i ].type && '' === fields[ i ].value ) {
				first = fields[ i ];
				break;
			}
		}

		if ( first ) {
			first.focus( { preventScroll: true } );
		}
	}

	if ( barButton ) {
		barButton.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			if ( express ) {
				goToExpress();
				return;
			}

			var realButton = cartForm.querySelector( 'button[type="submit"], input[type="submit"]' );

			if ( realButton ) {
				realButton.click();
			}
		} );
	}

	/*
	 * Amélioration progressive : la barre est visible par défaut en CSS, et
	 * ce script ne fait que la ranger quand le point d'achat est déjà à
	 * l'écran. Si le script échoue ou qu'IntersectionObserver manque, la
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

	observer.observe( target );
}() );
