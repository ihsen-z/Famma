/*
 * Commande — relie le choix « avec / sans compte » à la case native de WooCommerce.
 *
 * Le script ne decide rien : il recopie le choix du client dans la case
 * `#createaccount` de WooCommerce, puis laisse le script de WooCommerce ouvrir
 * ou fermer le champ mot de passe comme il le fait deja. Aucune bibliotheque,
 * aucun appel reseau.
 *
 * Degradation assumee : sans JavaScript, la case native reste visible et
 * utilisable a sa place habituelle. Le client garde donc les deux parcours —
 * il voit simplement deux commandes au lieu d'une. C'est la raison pour
 * laquelle la case n'est masquee qu'ici, par la classe posee sur le document,
 * et jamais en CSS seul.
 */
( function () {
	'use strict';

	var native = document.getElementById( 'createaccount' );
	var radios = document.querySelectorAll( 'input[name="famma_account_choice"]' );

	if ( ! native || ! radios.length ) {
		return;
	}

	// Signale au CSS que le relais fonctionne : la case native peut disparaitre.
	document.documentElement.classList.add( 'famma-has-account-choice' );

	/**
	 * Recopie le choix dans la case native et previent WooCommerce.
	 *
	 * @param {boolean} wantsAccount Le client veut-il un compte ?
	 */
	function sync( wantsAccount ) {
		if ( native.checked === wantsAccount ) {
			return;
		}

		native.checked = wantsAccount;

		// WooCommerce ecoute `change` sur cette case pour deplier le mot de
		// passe. L'evenement doit donc etre emis, pas seulement la propriete
		// changee.
		native.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	Array.prototype.forEach.call( radios, function ( radio ) {
		radio.addEventListener( 'change', function () {
			if ( radio.checked ) {
				sync( 'create' === radio.value );
			}
		} );
	} );

	/*
	 * Etat initial. WooCommerce peut avoir remis la case a « cochee » apres une
	 * erreur de validation : dans ce cas le choix du client doit refleter ce
	 * qu'il avait demande, pas le defaut.
	 */
	if ( native.checked ) {
		var create = document.getElementById( 'famma-account-create' );

		if ( create ) {
			create.checked = true;
		}
	} else {
		sync( false );
	}
} )();
