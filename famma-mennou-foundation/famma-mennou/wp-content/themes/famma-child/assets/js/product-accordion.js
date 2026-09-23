/**
 * Accordéon de la fiche produit — ouverture par ancre (§B14-15).
 *
 * L'accordéon lui-même ne demande aucun JavaScript : `<details>` s'ouvre tout
 * seul. Ce script ne traite qu'un seul cas, celui que le HTML natif ne couvre
 * pas — une ancre qui pointe *à l'intérieur* d'une section fermée.
 *
 * Le cas est réel et visible : le résumé des avis affiche « Lire tous les
 * avis » vers `#reviews`, qui vit dans la section Avis. Fermée, cette section
 * masque sa cible ; le navigateur ne défile alors vers rien du tout et le lien
 * paraît cassé. Même chose pour un visiteur qui arrive d'un lien externe avec
 * l'ancre déjà dans l'URL.
 *
 * Sans ce script, rien n'est perdu : les sections restent toutes ouvrables à la
 * main. Le script ne fait que rendre les liens d'ancre honnêtes.
 */
( function () {
	'use strict';

	var root = document.getElementById( 'famma-product-sections' );

	if ( ! root ) {
		return;
	}

	/**
	 * Ouvre la section contenant l'élément visé, puis défile jusqu'à lui.
	 *
	 * Le défilement est refait ici même quand le navigateur l'a déjà tenté :
	 * au moment où il a essayé, la cible était masquée et sa position valait
	 * zéro. Il faut donc rejouer le déplacement une fois la section ouverte.
	 *
	 * @param {string}  hash    Ancre visée, `#compris`.
	 * @param {boolean} animate Défilement animé, sauf au chargement.
	 * @return {boolean} Vrai si une section a bien été ouverte.
	 */
	function revealAnchor( hash, animate ) {
		if ( ! hash || hash.length < 2 ) {
			return false;
		}

		var target;

		/*
		 * `getElementById` plutôt qu'un sélecteur : une ancre peut contenir des
		 * caractères qui font de `querySelector` une exception (chiffre en
		 * tête, accents), et une exception ici empêcherait le défilement.
		 */
		try {
			target = document.getElementById( decodeURIComponent( hash.slice( 1 ) ) );
		} catch ( e ) {
			return false;
		}

		if ( ! target ) {
			return false;
		}

		var section = target.closest( 'details.famma-accordion__item' );

		if ( ! section ) {
			return false;
		}

		var wasClosed = ! section.open;
		section.open = true;

		/*
		 * `requestAnimationFrame` : la position de la cible n'est utilisable
		 * qu'une fois la section dépliée et la mise en page recalculée.
		 */
		window.requestAnimationFrame( function () {
			target.scrollIntoView( {
				behavior: animate && ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
					? 'smooth'
					: 'auto',
				block: 'start',
			} );
		} );

		return wasClosed;
	}

	/*
	 * Délégation sur le document : le lien « Lire tous les avis » vit dans le
	 * résumé produit, donc HORS de l'accordéon. Écouter sur `root` seul le
	 * manquerait — c'est justement le lien qui motive ce script.
	 */
	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( 'a[href*="#"]' );

		if ( ! link ) {
			return;
		}

		// Un lien vers l'ancre d'une AUTRE page n'a pas à être intercepté.
		if ( link.pathname !== window.location.pathname || link.host !== window.location.host ) {
			return;
		}

		if ( revealAnchor( link.hash, true ) ) {
			/*
			 * Uniquement quand une section était fermée : le défilement est
			 * repris ici, celui du navigateur viserait la mauvaise position.
			 * Si la section était déjà ouverte, on ne touche à rien.
			 */
			event.preventDefault();

			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', link.hash );
			}
		}
	} );

	/*
	 * Description repliée : elle s'ouvrait sur 2 492 px (3,7 écrans mobiles) et
	 * repoussait la section Livraison tout en bas (audit du 23/09, FP-10). Le
	 * début reste visible, le reste suit sur « Lire la suite ».
	 *
	 * Amélioration progressive : c'est le script qui pose la troncature. S'il
	 * ne se charge pas, la description s'affiche en entier, comme avant.
	 */
	var description = root.querySelector( '.woocommerce-Tabs-panel--description' );
	var moreLabel = root.getAttribute( 'data-more' );
	var lessLabel = root.getAttribute( 'data-less' );

	if ( description && moreLabel && lessLabel && description.scrollHeight > 640 ) {
		var toggle = document.createElement( 'button' );

		toggle.type = 'button';
		toggle.className = 'famma-accordion__more';
		toggle.textContent = moreLabel;
		toggle.setAttribute( 'aria-expanded', 'false' );

		description.classList.add( 'is-clamped' );
		description.after( toggle );

		toggle.addEventListener( 'click', function () {
			var clamped = description.classList.toggle( 'is-clamped' );

			toggle.textContent = clamped ? moreLabel : lessLabel;
			toggle.setAttribute( 'aria-expanded', clamped ? 'false' : 'true' );

			// Replié depuis le bas du texte : on ramène le client au début, sinon il se retrouve plus bas, sans repère.
			if ( clamped ) {
				description.closest( 'details' ).scrollIntoView( { block: 'start' } );
			}
		} );
	}

	// Arrivée directe sur la page avec l'ancre déjà dans l'URL.
	revealAnchor( window.location.hash, false );

	window.addEventListener( 'hashchange', function () {
		revealAnchor( window.location.hash, true );
	} );
} )();
