/**
 * Nouveau look — finitions de l'espace client (case acct_finish, lot 43).
 *
 * - « Mes informations » : le téléphone passe sous « Nom et prénom » (ordre
 *   de la maquette : nom, téléphone, e-mail) ; « (facultatif) » rejoint le
 *   libellé de l'e-mail, dont l'aide est reliée au champ.
 * - Après la déconnexion : la marque quitte l'adresse, le message s'efface.
 *
 * Sans script : nom, e-mail, téléphone ; libellé « E-mail » sans mention ;
 * le message s'efface par sa seule animation.
 */
( function () {
	'use strict';

	var name = document.querySelector( '.famma-acct-fin-name' );
	var tel = document.getElementById( 'famma_id_tel' );
	var telRow = tel ? tel.closest( '.form-row, p' ) : null;

	if ( name && telRow && name.parentNode === telRow.parentNode ) {
		name.after( telRow );
	}

	var help = document.getElementById( 'famma-acct-mail-h' );
	var mail = document.getElementById( 'account_email' );
	var label = document.querySelector( 'label[for="account_email"]' );

	if ( help && label && help.dataset.opt && ! label.querySelector( '.famma-acct-opt' ) ) {
		var opt = document.createElement( 'span' );
		opt.className = 'famma-acct-opt';
		opt.textContent = help.dataset.opt;
		label.append( ' ', opt );
	}

	if ( help && mail && ! help.hidden ) {
		mail.setAttribute( 'aria-describedby', ( ( mail.getAttribute( 'aria-describedby' ) || '' ) + ' ' + help.id ).trim() );
	}

	var out = document.querySelector( '[data-famma-out]' );

	if ( out ) {
		try {
			var url = new URL( window.location.href );
			url.searchParams.delete( 'famma_out' );
			window.history.replaceState( window.history.state, '', url.toString() );
		} catch ( e ) {
			// Adresse laissée telle quelle : sans effet sur la page.
		}

		window.setTimeout( function () {
			out.remove();
		}, 2800 );
	}
}() );
