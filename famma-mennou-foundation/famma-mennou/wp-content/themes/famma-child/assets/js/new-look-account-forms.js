/**
 * FAMMA — « Mes adresses » et « Mes informations » (case acct_forms).
 *
 * - « Modifier » / « Ajouter une adresse » ouvrent la feuille qui glisse,
 *   au lieu de changer de page ; elle se ferme par la croix, Échap ou un
 *   toucher à côté. Sans <dialog>, le lien mène à la page de WooCommerce.
 * - Le changement de mot de passe de WooCommerce passe dans la boîte
 *   « Connexion », replié derrière « Changer mon mot de passe ». Ses champs
 *   restent rattachés au formulaire (attribut form) : même envoi, même
 *   contrôle. Sans script, il reste dans le formulaire.
 */
( function () {
	'use strict';

	var doc = document;

	/* ---------- Feuille d'adresse ---------- */

	var sheet = doc.getElementById( 'famma-acct-sheet' );

	if ( sheet && 'function' === typeof sheet.showModal ) {
		var opener = null;

		doc.addEventListener( 'click', function ( e ) {
			var link = e.target.closest && e.target.closest( '[data-famma-sheet]' );

			if ( link ) {
				e.preventDefault();
				opener = link;
				sheet.showModal();

				var first = sheet.querySelector( 'input:not([type=hidden]), select' );

				if ( first ) {
					window.setTimeout( function () {
						first.focus( { preventScroll: true } );
					}, 200 );
				}

				return;
			}

			if ( e.target.closest && e.target.closest( '[data-famma-sheet-close]' ) ) {
				sheet.close();
			}
		} );

		// Un toucher sur le voile, hors de la feuille.
		sheet.addEventListener( 'click', function ( e ) {
			if ( e.target === sheet ) {
				sheet.close();
			}
		} );

		sheet.addEventListener( 'close', function () {
			if ( opener ) {
				opener.focus( { preventScroll: true } );
			}
		} );
	}

	/* ---------- Mot de passe dans « Connexion » ---------- */

	var form = doc.querySelector( 'form.woocommerce-EditAccountForm' );
	var spot = doc.querySelector( '[data-famma-pw]' );
	var set = form && form.querySelector( 'fieldset' );
	var btn = doc.querySelector( '.famma-acct-dbtn' );
	var save = form && form.querySelector( '[name="save_account_details"]' );

	if ( ! form || ! spot || ! set || ! btn || ! save ) {
		return;
	}

	if ( ! form.id ) {
		form.id = 'famma-acct-infos';
	}

	spot.appendChild( set );

	Array.prototype.forEach.call( set.querySelectorAll( 'input, select, textarea, button' ), function ( el ) {
		el.setAttribute( 'form', form.id );
	} );

	var again = doc.createElement( 'button' );
	again.type = 'submit';
	again.name = save.name;
	again.value = save.value;
	again.className = 'button famma-acct-pw-save';
	again.textContent = spot.getAttribute( 'data-save' ) || save.textContent;
	again.setAttribute( 'form', form.id );
	spot.appendChild( again );

	var box = doc.getElementById( btn.getAttribute( 'aria-controls' ) );

	btn.hidden = false;
	btn.addEventListener( 'click', function () {
		var open = 'true' !== btn.getAttribute( 'aria-expanded' );

		btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

		if ( box ) {
			box.classList.toggle( 'is-open', open );
		}

		if ( open ) {
			var first = set.querySelector( 'input[type=password]' );

			if ( first ) {
				window.setTimeout( function () {
					first.focus( { preventScroll: true } );
				}, 200 );
			}
		}
	} );
}() );
