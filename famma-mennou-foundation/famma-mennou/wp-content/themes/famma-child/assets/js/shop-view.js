/**
 * FAMMA — boutique : bascule grille/liste et dépliage des filtres sur mobile.
 *
 * Deux comportements purement visuels. Ni l'un ni l'autre ne touche la requête :
 * le tri, les filtres et la pagination restent ceux de WooCommerce, servis par
 * le serveur. Ce fichier ne fait que changer une classe et un attribut.
 *
 * Sans ce script, la boutique reste complète : la grille s'affiche, les filtres
 * sont visibles en desktop et tout se navigue au clavier. Sous 1000px, la
 * colonne arrive repliée du serveur (`hidden`) : la replier ici, après coup,
 * faisait remonter toute la grille (CLS 0,49, audit perf du 23/09).
 */
( function () {
	'use strict';

	var shop = document.querySelector( '.famma-shop' );

	if ( ! shop ) {
		return;
	}

	var DESKTOP = window.matchMedia( '(min-width: 1000px)' );

	/* ---------------------------------------------------------------
	   Bascule grille / liste
	   --------------------------------------------------------------- */

	var STORAGE_KEY = 'famma-shop-view';
	var switches = shop.querySelectorAll( '[data-famma-view]' );

	function applyView( view ) {
		shop.classList.toggle( 'is-list', view === 'list' );

		Array.prototype.forEach.call( switches, function ( button ) {
			var active = button.getAttribute( 'data-famma-view' ) === view;
			button.classList.toggle( 'is-active', active );
			button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );
	}

	function readStoredView() {
		// Le mode navigation privée de Safari fait lever localStorage.
		try {
			return window.localStorage.getItem( STORAGE_KEY );
		} catch ( error ) {
			return null;
		}
	}

	function storeView( view ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, view );
		} catch ( error ) {
			// Préférence non mémorisée : sans conséquence pour l'affichage.
		}
	}

	if ( switches.length ) {
		var stored = readStoredView();

		if ( stored === 'list' || stored === 'grid' ) {
			applyView( stored );
		}

		Array.prototype.forEach.call( switches, function ( button ) {
			button.addEventListener( 'click', function () {
				var view = button.getAttribute( 'data-famma-view' );
				applyView( view );
				storeView( view );
			} );
		} );
	}

	/* ---------------------------------------------------------------
	   Repliage de chaque bloc de filtre

	   La maquette pose un chevron sur chaque titre. Le chevron n'apparaît
	   qu'ici, une fois le titre devenu un vrai bouton : dessiné en CSS sans
	   ce script, il annoncerait un repliage qui n'existe pas.
	   --------------------------------------------------------------- */

	shop.querySelectorAll( '.famma-filter' ).forEach( function ( block, index ) {
		var title = block.querySelector( '.famma-filter__title' );

		if ( ! title ) {
			return;
		}

		// Le contenu du bloc, c'est tout sauf son titre.
		var body = Array.prototype.filter.call( block.children, function ( node ) {
			return node !== title;
		} );

		if ( ! body.length ) {
			return;
		}

		var panelId = 'famma-filter-panel-' + index;
		var button = document.createElement( 'button' );

		button.type = 'button';
		button.className = title.className + ' is-collapsible';
		button.innerHTML = title.innerHTML;
		button.setAttribute( 'aria-expanded', 'true' );
		button.setAttribute( 'aria-controls', panelId );

		var panel = document.createElement( 'div' );
		panel.id = panelId;
		body.forEach( function ( node ) {
			panel.appendChild( node );
		} );

		title.replaceWith( button );
		block.appendChild( panel );

		button.addEventListener( 'click', function () {
			var open = button.getAttribute( 'aria-expanded' ) === 'true';
			button.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
			panel.hidden = open;
		} );
	} );

	/* ---------------------------------------------------------------
	   Curseur de prix

	   Le serveur rend un formulaire GET complet : sans ce script, le champ
	   reste utilisable et le bouton d'envoi — visible au clavier — valide la
	   sélection. Le script ne fait qu'afficher la valeur en direct et
	   soumettre au relâchement, pour éviter un rechargement à chaque pixel
	   parcouru.
	   --------------------------------------------------------------- */

	var range = shop.querySelector( '.famma-price__range' );

	if ( range ) {
		var form = range.closest( 'form' );
		var readout = shop.querySelector( '.famma-price__value' );
		var unit = readout ? readout.textContent.replace( /[\d\s]/g, '' ) : '';

		range.addEventListener( 'input', function () {
			if ( readout ) {
				readout.textContent = range.value + ' ' + unit;
			}
		} );

		// `change` plutôt que `input` : il ne se déclenche qu'au relâchement.
		range.addEventListener( 'change', function () {
			if ( form ) {
				form.submit();
			}
		} );
	}

	/* ---------------------------------------------------------------
	   Filtres repliables sous 1000px
	   --------------------------------------------------------------- */

	var toggle = shop.querySelector( '.famma-shop__filters-toggle' );
	var aside = document.getElementById( 'famma-shop-filters' );

	if ( ! toggle || ! aside ) {
		return;
	}

	function closeFilters() {
		toggle.setAttribute( 'aria-expanded', 'false' );
		aside.hidden = true;
	}

	function openFilters() {
		toggle.setAttribute( 'aria-expanded', 'true' );
		aside.hidden = false;
	}

	// Repli initial sur mobile seulement. Sur desktop la colonne doit rester
	// visible : la replier au chargement ferait disparaître les filtres là où
	// il y a toute la place de les montrer.
	if ( ! DESKTOP.matches ) {
		closeFilters();
	}

	toggle.addEventListener( 'click', function () {
		if ( toggle.getAttribute( 'aria-expanded' ) === 'true' ) {
			closeFilters();
		} else {
			openFilters();
		}
	} );

	function syncBreakpoint( event ) {
		if ( event.matches ) {
			// Passage en desktop : on rend la colonne, sans quoi elle resterait
			// masquée par l'attribut `hidden` posé en mobile.
			openFilters();
		} else {
			closeFilters();
		}
	}

	if ( typeof DESKTOP.addEventListener === 'function' ) {
		DESKTOP.addEventListener( 'change', syncBreakpoint );
	} else if ( typeof DESKTOP.addListener === 'function' ) {
		// Safari < 14.
		DESKTOP.addListener( syncBreakpoint );
	}
}() );
