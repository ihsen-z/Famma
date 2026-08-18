/**
 * FAMMA — carrousel du héros de l'accueil.
 *
 * Ne s'active qu'à partir de deux visuels : le serveur ne pose l'attribut
 * `data-famma-hero` que dans ce cas. Sans lui, ce fichier ne fait rien, et le
 * héros reste un panneau fixe — ce qui est le comportement voulu tant que le
 * catalogue n'a pas assez de produits illustrés.
 *
 * Le défilement automatique s'arrête sous `prefers-reduced-motion`, au survol
 * et dès qu'un élément du héros reçoit le focus clavier : personne ne devrait
 * voir la diapositive changer pendant qu'il lit ou qu'il tabule.
 */
( function () {
	'use strict';

	var track = document.querySelector( '[data-famma-hero]' );

	if ( ! track ) {
		return;
	}

	var slides = track.querySelectorAll( '.famma-hero2__slide' );
	var dots = document.querySelectorAll( '[data-famma-hero-dot]' );

	if ( slides.length < 2 ) {
		return;
	}

	var INTERVAL = 6000;
	var current = 0;
	var timer = null;
	var reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	function show( index ) {
		current = ( index + slides.length ) % slides.length;

		Array.prototype.forEach.call( slides, function ( slide, i ) {
			var active = i === current;
			slide.classList.toggle( 'is-active', active );

			// Une diapositive masquée ne doit être ni lue ni atteignable au
			// clavier : sans cela la tabulation traverse des liens invisibles.
			if ( active ) {
				slide.removeAttribute( 'aria-hidden' );
				slide.removeAttribute( 'tabindex' );
			} else {
				slide.setAttribute( 'aria-hidden', 'true' );
				slide.setAttribute( 'tabindex', '-1' );
			}
		} );

		Array.prototype.forEach.call( dots, function ( dot, i ) {
			var active = i === current;
			dot.classList.toggle( 'is-active', active );
			dot.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		} );
	}

	function stop() {
		if ( timer ) {
			window.clearInterval( timer );
			timer = null;
		}
	}

	function start() {
		if ( reduced.matches || timer ) {
			return;
		}

		timer = window.setInterval( function () {
			show( current + 1 );
		}, INTERVAL );
	}

	Array.prototype.forEach.call( dots, function ( dot, i ) {
		dot.addEventListener( 'click', function () {
			show( i );
			// Un clic est une intention : on rend la main au visiteur plutôt
			// que de reprendre le défilement sous ses yeux.
			stop();
		} );
	} );

	var hero = track.closest( '.famma-hero2' ) || track;

	hero.addEventListener( 'mouseenter', stop );
	hero.addEventListener( 'mouseleave', start );
	hero.addEventListener( 'focusin', stop );

	if ( typeof reduced.addEventListener === 'function' ) {
		reduced.addEventListener( 'change', function ( event ) {
			if ( event.matches ) {
				stop();
			} else {
				start();
			}
		} );
	}

	show( 0 );
	start();
}() );
