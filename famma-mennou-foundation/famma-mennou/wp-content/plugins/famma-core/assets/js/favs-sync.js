/**
 * FAMMA — « Ma sélection » gardée dans le compte (case acct_favs).
 *
 * Chargé seulement pour un client connecté. La sélection reste dans le
 * navigateur (`famma-favourites`, tenu par le thème) ; ce script l'accorde
 * avec celle du compte :
 *
 * - première fois sur ce navigateur pour ce compte : tout ce qui est dans le
 *   navigateur rejoint le compte (aucun favori perdu) ;
 * - ensuite : seuls les ajouts et retraits faits depuis le dernier accord
 *   partent, puis la liste du compte revient et remplace celle du navigateur.
 *   Un produit retiré sur un téléphone ne revient donc pas par un autre.
 *
 * Le thème annonce chaque clic sur un cœur (`famma:fav`) ; ce script annonce
 * la liste accordée (`famma:favs-synced`) pour que les cœurs et les compteurs
 * suivent.
 */
( function () {
	'use strict';

	var cfg = window.fammaFavsSync;

	if ( ! cfg || ! cfg.url || ! window.fetch ) {
		return;
	}

	var KEY = 'famma-favourites';
	var SYNC = 'famma-favourites-sync';
	var PULL = 120000;
	var busy = false;
	var again = false;
	var timer = 0;

	function get( key, fallback ) {
		try {
			var value = JSON.parse( window.localStorage.getItem( key ) || 'null' );
			return null === value ? fallback : value;
		} catch ( error ) {
			return fallback;
		}
	}

	function set( key, value ) {
		try {
			window.localStorage.setItem( key, JSON.stringify( value ) );
		} catch ( error ) {
			// Stockage indisponible : la sélection du compte reste la référence.
		}
	}

	function ids( value ) {
		return Array.isArray( value ) ? value.map( String ) : [];
	}

	function minus( a, b ) {
		return a.filter( function ( x ) {
			return b.indexOf( x ) === -1;
		} );
	}

	function run( force ) {
		if ( busy ) {
			again = true;
			return;
		}

		var sent = ids( get( KEY, [] ) );
		var state = get( SYNC, null );
		var mine = !! state && state.who === cfg.who && Array.isArray( state.list );
		var last = mine ? ids( state.list ) : [];
		var add = mine ? minus( sent, last ) : sent;
		var remove = mine ? minus( last, sent ) : [];

		if ( mine && ! force && ! add.length && ! remove.length && Date.now() - ( state.t || 0 ) < PULL ) {
			return;
		}

		busy = true;

		window.fetch( cfg.url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			body: JSON.stringify( { add: add.map( Number ), remove: remove.map( Number ) } )
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( String( response.status ) );
			}
			return response.json();
		} ).then( function ( data ) {
			if ( ! data || ! Array.isArray( data.favs ) ) {
				return;
			}

			var account = ids( data.favs );

			// Un cœur touché pendant l'envoi garde son effet ; il partira au
			// tour suivant.
			var now = ids( get( KEY, [] ) );
			var merged = minus( account, minus( sent, now ) ).concat( minus( minus( now, sent ), account ) );

			set( KEY, merged );
			set( SYNC, { who: cfg.who, list: account, t: Date.now() } );

			document.dispatchEvent( new window.CustomEvent( 'famma:favs-synced', { detail: { favs: merged.slice() } } ) );
		} ).catch( function () {
			// Hors ligne ou session expirée : on réessaiera à la page suivante.
		} ).then( function () {
			busy = false;

			if ( again ) {
				again = false;
				run( true );
			}
		} );
	}

	document.addEventListener( 'famma:fav', function () {
		window.clearTimeout( timer );
		timer = window.setTimeout( function () {
			run( true );
		}, 300 );
	} );

	run( !! document.querySelector( '[data-famma-favs-panel]' ) );
}() );
