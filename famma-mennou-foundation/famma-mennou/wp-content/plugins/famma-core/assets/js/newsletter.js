/**
 * Newsletter : jeton frais juste avant l'envoi.
 *
 * L'accueil est servi par le cache de page jusqu'à 7 jours, alors que le
 * jeton imprimé dans le HTML expire au bout de 12 à 24 h. Le formulaire en
 * demande un neuf au point `wc-ajax` (jamais mis en cache), puis s'envoie.
 * Si la demande échoue, il part avec le jeton de la page : rien n'est perdu
 * par rapport au comportement d'avant.
 */
( function () {
	'use strict';

	document.querySelectorAll( 'form[data-famma-nl-nonce-url]' ).forEach( function ( form ) {
		var url = form.getAttribute( 'data-famma-nl-nonce-url' );
		var field = form.querySelector( 'input[name="' + form.getAttribute( 'data-famma-nl-nonce-field' ) + '"]' );
		var refreshed = false;

		if ( ! url || ! field || ! window.fetch ) {
			return;
		}

		form.addEventListener( 'submit', function ( event ) {
			if ( refreshed ) {
				return;
			}

			event.preventDefault();

			fetch( url, { credentials: 'same-origin', cache: 'no-store' } )
				.then( function ( response ) {
					return response.ok ? response.json() : null;
				} )
				.then( function ( body ) {
					if ( body && body.success && body.data && body.data.nonce ) {
						field.value = body.data.nonce;
					}
				} )
				.catch( function () {} )
				.then( function () {
					refreshed = true;

					if ( form.requestSubmit ) {
						form.requestSubmit();
					} else {
						form.submit();
					}
				} );
		} );
	} );
}() );
