/**
 * FAMMA — écran « Bienvenue » après l'inscription (case acct_welcome).
 *
 * Comme la maquette : l'écran arrive, la mascotte saute et cligne avec des
 * étincelles (260 ms), puis à 2,3 s l'écran s'efface et le compte apparaît
 * avec ses animations d'arrivée (classe famma-acct-intro, compteurs relancés
 * par l'événement famma:acct-welcomed). Mouvement réduit : pas de saut ni
 * d'étincelles, le compte apparaît à 0,9 s.
 */
( function () {
	'use strict';

	var doc = document;
	var box = doc.querySelector( '[data-famma-welcome]' );

	if ( ! box ) {
		return;
	}

	var html = doc.documentElement;
	var RM = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var mascot = box.querySelector( '.famma-acct-mascot' );

	/* Étincelles : les trois traits du logo, éclatés autour d'un point. */
	function burst( x, y, n ) {
		if ( ! doc.body.animate ) {
			return;
		}

		for ( var i = 0; i < n; i++ ) {
			var s = doc.createElement( 'i' );
			var a = ( i / n ) * Math.PI * 2 + 0.3;
			var d = 22 + ( i % 3 ) * 9;
			var deg = a * 180 / Math.PI + 90;

			s.className = 'famma-nl-spark';
			s.setAttribute( 'aria-hidden', 'true' );
			doc.body.appendChild( s );
			s.animate( [
				{ transform: 'translate(' + x + 'px,' + y + 'px) rotate(' + deg + 'deg) scaleY(.2)', opacity: 1 },
				{ transform: 'translate(' + ( x + Math.cos( a ) * d ) + 'px,' + ( y + Math.sin( a ) * d ) + 'px) rotate(' + deg + 'deg) scaleY(1)', opacity: 1, offset: 0.55 },
				{ transform: 'translate(' + ( x + Math.cos( a ) * ( d + 10 ) ) + 'px,' + ( y + Math.sin( a ) * ( d + 10 ) ) + 'px) rotate(' + deg + 'deg) scaleY(.2)', opacity: 0 }
			], { duration: 560, easing: 'cubic-bezier(.2,.8,.2,1)' } ).onfinish = ( function ( el ) {
				return function () {
					el.remove();
				};
			}( s ) );
		}
	}

	if ( ! RM && mascot ) {
		window.setTimeout( function () {
			var r = mascot.getBoundingClientRect();

			mascot.classList.add( 'is-wink' );
			burst( r.left + r.width / 2, r.top + r.height / 2, 10 );
			window.setTimeout( function () {
				mascot.classList.remove( 'is-wink' );
			}, 700 );
		}, 260 );
	}

	window.setTimeout( function () {
		box.classList.add( 'is-out' );

		window.setTimeout( function () {
			html.classList.add( 'famma-acct-intro' );
			doc.body.classList.remove( 'famma-acct-welcoming' );
			box.remove();
			doc.dispatchEvent( new CustomEvent( 'famma:acct-welcomed' ) );
		}, RM ? 0 : 260 );
	}, RM ? 900 : 2300 );
}() );
