/**
 * FAMMA — navigation de l'en-tête.
 *
 * Une seule responsabilité : ouvrir et fermer le panneau mobile. Le reste de
 * l'en-tête (collant, sous-menus, bascule desktop/mobile) est en CSS pur, et
 * les sous-menus s'ouvrent au survol comme au focus clavier — donc sans une
 * ligne de script.
 *
 * Le panneau est fermé par l'attribut `hidden` plutôt que par une classe :
 * si ce fichier ne se charge pas, le panneau reste fermé et la navigation
 * du pied de page prend le relais. Aucune page ne devient inatteignable
 * parce qu'un script a échoué.
 */
( function () {
	'use strict';

	var burger = document.querySelector( '.famma-burger' );
	var panel = document.getElementById( 'famma-mobile-panel' );

	if ( ! burger || ! panel ) {
		return;
	}

	var DESKTOP = window.matchMedia( '(min-width: 1000px)' );

	function isOpen() {
		return burger.getAttribute( 'aria-expanded' ) === 'true';
	}

	function open() {
		burger.setAttribute( 'aria-expanded', 'true' );
		panel.hidden = false;
	}

	function close( refocus ) {
		burger.setAttribute( 'aria-expanded', 'false' );
		panel.hidden = true;

		if ( refocus ) {
			burger.focus();
		}
	}

	burger.addEventListener( 'click', function () {
		if ( isOpen() ) {
			close( false );
		} else {
			open();
		}
	} );

	// Échap ferme et rend le focus au bouton : sans ce retour, le focus
	// reste sur un élément devenu invisible et la tabulation repart du haut
	// du document.
	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' && isOpen() ) {
			close( true );
		}
	} );

	// Un clic hors du panneau le referme, sauf sur le bouton lui-même qui a
	// déjà son propre gestionnaire.
	document.addEventListener( 'click', function ( event ) {
		if ( ! isOpen() ) {
			return;
		}

		if ( panel.contains( event.target ) || burger.contains( event.target ) ) {
			return;
		}

		close( false );
	} );

	// Le panneau n'existe que sous 1000px. En passant en desktop pendant
	// qu'il est ouvert, il faut le refermer : sinon `hidden` reste à false et
	// le panneau réapparaît au retour en mobile sans que rien ne l'ait
	// demandé.
	function syncBreakpoint( event ) {
		if ( event.matches && isOpen() ) {
			close( false );
		}
	}

	if ( typeof DESKTOP.addEventListener === 'function' ) {
		DESKTOP.addEventListener( 'change', syncBreakpoint );
	} else if ( typeof DESKTOP.addListener === 'function' ) {
		// Safari < 14.
		DESKTOP.addListener( syncBreakpoint );
	}
}() );
