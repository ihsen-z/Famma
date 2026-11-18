/**
 * Nouveau look — page commande comme le formulaire express (brique
 * `co_express`) : gouvernorat choisi d'après la ville tapée dans l'adresse.
 *
 * Même script que le formulaire express (`fammaGovernoratePlaces`, famma-core) :
 * rien n'est réécrit ici. Le client garde la main : un gouvernorat choisi à la
 * main n'est jamais remplacé.
 */
( function () {
	'use strict';

	function start() {
		var select = document.getElementById( 'billing_state' );
		var input = document.getElementById( 'billing_address_1' );
		var texts = window.fammaCoExpress || {};

		if ( ! select || ! input || ! window.fammaGovernoratePlaces || 'SELECT' !== select.tagName ) {
			return;
		}

		window.fammaGovernoratePlaces.attach( select, input, {
			found: texts.found || '',
			flashClass: 'famma-express__flash',
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
