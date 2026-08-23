/**
 * FAMMA — carrousel à défilement natif.
 *
 * Le défilement lui-même est du CSS : la piste est un conteneur
 * `overflow-x: auto` avec accroche. Ce fichier n'ajoute que les flèches, et
 * seulement quand la piste déborde vraiment — un catalogue de trois rayons sur
 * un écran large tient dans la largeur, et deux flèches inertes y seraient un
 * mensonge d'interface.
 *
 * Sans JavaScript, la piste glisse au doigt et les liens restent atteignables
 * au clavier : le carrousel ne dépend pas de ce fichier pour fonctionner.
 */
( function () {
	'use strict';

	var carousels = document.querySelectorAll( '[data-famma-carousel]' );

	if ( ! carousels.length ) {
		return;
	}

	/**
	 * Marge de tolérance, en pixels.
	 *
	 * Les largeurs de mise en page tombent sur des fractions de pixel : sans
	 * cette marge, une piste qui tient exactement dans sa boîte se déclare
	 * débordante et affiche des flèches qui ne bougent rien.
	 */
	var EPSILON = 2;

	Array.prototype.forEach.call( carousels, function ( carousel ) {
		var track = carousel.querySelector( '[data-famma-carousel-track]' );
		var prev = carousel.querySelector( '[data-famma-carousel-prev]' );
		var next = carousel.querySelector( '[data-famma-carousel-next]' );

		if ( ! track || ! prev || ! next ) {
			return;
		}

		/**
		 * Sens de lecture : en RTL, `scrollLeft` décroît vers la suite.
		 *
		 * @return {number} 1 en LTR, -1 en RTL.
		 */
		function direction() {
			return 'rtl' === window.getComputedStyle( track ).direction ? -1 : 1;
		}

		/**
		 * Distance de défilement d'un appui : une piste visible moins un rayon.
		 *
		 * Garder un rayon à l'écran donne le repère de continuité — sauter une
		 * pleine largeur donne l'impression d'avoir perdu quelque chose.
		 *
		 * @return {number} Distance en pixels, toujours positive.
		 */
		function step() {
			var item = track.querySelector( 'li' );
			var width = item ? item.getBoundingClientRect().width : 0;

			return Math.max( track.clientWidth - width, width || track.clientWidth );
		}

		/**
		 * Affiche ou masque les flèches, et met à jour leur état.
		 *
		 * @return {void}
		 */
		function update() {
			var overflows = track.scrollWidth - track.clientWidth > EPSILON;

			prev.hidden = ! overflows;
			next.hidden = ! overflows;

			if ( ! overflows ) {
				return;
			}

			var offset = Math.abs( track.scrollLeft );
			var max = track.scrollWidth - track.clientWidth;

			prev.disabled = offset <= EPSILON;
			next.disabled = offset >= max - EPSILON;
		}

		/**
		 * Fait défiler la piste d'un cran.
		 *
		 * @param {number} sign -1 vers le début, 1 vers la suite.
		 * @return {void}
		 */
		function scrollBy( sign ) {
			/*
			 * `behavior: 'auto'` ne veut pas dire « sans animation » : la valeur
			 * demande de suivre le `scroll-behavior` du CSS — c'est `instant`
			 * qui supprime l'animation. Suivre le CSS est justement ce qu'on
			 * veut ici : la feuille pose `smooth`, et `auto` sous
			 * `prefers-reduced-motion`. Le mouvement a donc une seule source, et
			 * ce fichier n'a pas à relire la préférence pour la dupliquer.
			 */
			track.scrollBy( {
				left: sign * direction() * step(),
				behavior: 'auto',
			} );
		}

		prev.addEventListener( 'click', function () {
			scrollBy( -1 );
		} );

		next.addEventListener( 'click', function () {
			scrollBy( 1 );
		} );

		track.addEventListener( 'scroll', update, { passive: true } );
		window.addEventListener( 'resize', update );

		/*
		 * Le débordement dépend de la largeur rendue : elle change au
		 * redimensionnement, mais aussi quand une police se charge ou qu'une
		 * vignette de rayon arrive. `ResizeObserver` couvre les trois.
		 */
		if ( 'function' === typeof window.ResizeObserver ) {
			new window.ResizeObserver( update ).observe( track );
		}

		update();
	} );
}() );
