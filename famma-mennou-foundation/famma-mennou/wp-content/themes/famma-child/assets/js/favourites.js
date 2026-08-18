/**
 * FAMMA — favoris.
 *
 * La sélection vit dans le navigateur du visiteur (`localStorage`), pas sur le
 * serveur : aucun compte à créer, aucune donnée personnelle transmise, et le
 * cœur garde son état d'une page à l'autre. C'est ce qui distingue un bouton
 * qui mémorise d'un bouton décoratif.
 *
 * Le serveur rend toujours l'état « non favori » : la page reste ainsi
 * identique pour tout le monde et donc cacheable. C'est ce script qui rétablit
 * l'état réel au chargement.
 */
( function () {
	'use strict';

	var KEY = 'famma-favourites';

	function read() {
		try {
			var raw = window.localStorage.getItem( KEY );
			var list = raw ? JSON.parse( raw ) : [];
			return Array.isArray( list ) ? list : [];
		} catch ( error ) {
			// Navigation privée Safari, ou stockage plein : on dégrade en
			// bouton sans mémoire plutôt que de casser la page.
			return [];
		}
	}

	function write( list ) {
		try {
			window.localStorage.setItem( KEY, JSON.stringify( list ) );
		} catch ( error ) {
			// Préférence non conservée — sans conséquence sur l'achat.
		}
	}

	var buttons = document.querySelectorAll( '[data-famma-fav]' );

	if ( ! buttons.length ) {
		return;
	}

	var labels = {
		on: buttons[0].getAttribute( 'data-famma-fav-on' ) || 'Retirer des favoris',
		off: buttons[0].querySelector( '.famma-fav__label' )
			? buttons[0].querySelector( '.famma-fav__label' ).textContent
			: 'Ajouter aux favoris'
	};

	function paint( button, active ) {
		button.classList.toggle( 'is-active', active );
		button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );

		var label = button.querySelector( '.famma-fav__label' );
		if ( label ) {
			label.textContent = active ? labels.on : labels.off;
		}
	}

	var favourites = read();

	Array.prototype.forEach.call( buttons, function ( button ) {
		var id = button.getAttribute( 'data-famma-fav' );

		paint( button, favourites.indexOf( id ) !== -1 );

		button.addEventListener( 'click', function ( event ) {
			// La carte entière est un lien : sans cela, cliquer le cœur
			// ouvrirait la fiche produit au lieu de le cocher.
			event.preventDefault();
			event.stopPropagation();

			var list = read();
			var at = list.indexOf( id );

			if ( at === -1 ) {
				list.push( id );
			} else {
				list.splice( at, 1 );
			}

			write( list );

			// Toutes les occurrences du même produit sur la page suivent :
			// la grille et le bloc « produits similaires » peuvent le montrer
			// deux fois.
			document.querySelectorAll( '[data-famma-fav="' + id + '"]' ).forEach( function ( twin ) {
				paint( twin, at === -1 );
			} );
		} );
	} );
}() );
