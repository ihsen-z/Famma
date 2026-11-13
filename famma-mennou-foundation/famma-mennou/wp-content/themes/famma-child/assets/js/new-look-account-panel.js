/**
 * FAMMA — « Mon compte » connecté : les gestes de la maquette espace client.
 *
 * - soulignement des onglets qui glisse de l'onglet quitté vers l'onglet
 *   ouvert (l'onglet quitté est noté sur <html> par le petit script de
 *   l'en-tête) ;
 * - compteurs qui défilent depuis 0 à l'arrivée dans le compte ;
 * - nombre de favoris lu dans ce navigateur ;
 * - onglets collés sous l'en-tête du site, avec une ombre ;
 * - frise d'une commande qui se remplit quand elle entre à l'écran ;
 * - « Voir le détail » qui déplie la carte.
 *
 * Tout le contenu est déjà dans la page : sans ce script, la page reste
 * complète (détail ouvert, frise pleine, soulignement fixe).
 */
( function () {
	'use strict';

	var doc = document;
	var html = doc.documentElement;
	var RM = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function $$( sel, root ) {
		return Array.prototype.slice.call( ( root || doc ).querySelectorAll( sel ) );
	}

	/* ---------- Compteurs ---------- */

	function roll( el, to, duration ) {
		if ( RM || to <= 0 ) {
			el.textContent = String( to );
			return;
		}

		var t0 = 0;
		el.textContent = '0';

		function step( now ) {
			if ( ! t0 ) {
				t0 = now;
			}

			var k = Math.min( 1, ( now - t0 ) / duration );
			el.textContent = String( Math.round( to * ( 1 - Math.pow( 1 - k, 3 ) ) ) );

			if ( k < 1 ) {
				window.requestAnimationFrame( step );
			}
		}

		window.requestAnimationFrame( step );
	}

	var favs = doc.querySelector( '[data-famma-favs]' );

	// Compteur « dans ma sélection » et pastille de l'onglet « Ma sélection »
	// (case acct_favs) : lus dans le navigateur, puis remis à jour quand
	// famma-core a accordé la sélection avec le compte.
	var paintFavs = function ( list ) {
		if ( ! Array.isArray( list ) ) {
			return;
		}

		var count = String( list.length );
		var b = favs && favs.querySelector( 'b' );

		if ( b ) {
			b.setAttribute( 'data-roll', count );
			b.textContent = count;
			favs.hidden = false;
		}

		var tab = doc.querySelector( '.woocommerce-MyAccount-navigation-link--ma-selection a' );

		if ( tab ) {
			var n = tab.querySelector( '.famma-acct-n' );

			if ( ! n ) {
				n = doc.createElement( 'span' );
				n.className = 'famma-acct-n';
				tab.appendChild( n );
			}

			n.textContent = count;
		}
	};

	try {
		var raw = window.localStorage.getItem( 'famma-favourites' );
		paintFavs( raw ? JSON.parse( raw ) : [] );
	} catch ( e ) {
		// Stockage indisponible (navigation privée) : le compteur reste caché.
	}

	doc.addEventListener( 'famma:favs-synced', function ( event ) {
		paintFavs( event.detail && event.detail.favs );
	} );

	var intro = function () {
		$$( '.famma-acct-stat:not([hidden]) b[data-roll]' ).forEach( function ( el ) {
			roll( el, parseInt( el.getAttribute( 'data-roll' ), 10 ) || 0, 900 );
		} );
	};

	// Écran « Bienvenue » (case acct_welcome) : les compteurs attendent qu'il
	// laisse place au compte.
	if ( html.classList.contains( 'famma-acct-intro' ) && ! doc.body.classList.contains( 'famma-acct-welcoming' ) ) {
		intro();
	}

	doc.addEventListener( 'famma:acct-welcomed', intro );

	/* ---------- Onglets ---------- */

	var nav = doc.querySelector( '.famma-nl-acct .woocommerce-MyAccount-navigation' );
	var row = nav && nav.querySelector( 'ul' );

	if ( row ) {
		var orders = doc.querySelector( '.famma-acct-stat b[data-roll]' );
		var tabOrders = row.querySelector( '.woocommerce-MyAccount-navigation-link--orders a' );

		if ( orders && tabOrders && ! tabOrders.querySelector( '.famma-acct-n' ) ) {
			var n = doc.createElement( 'span' );
			n.className = 'famma-acct-n';
			n.textContent = orders.getAttribute( 'data-roll' );
			tabOrders.appendChild( n );
		}

		var ind = doc.createElement( 'span' );
		ind.className = 'famma-acct-ind';
		ind.setAttribute( 'aria-hidden', 'true' );
		row.appendChild( ind );

		var tabs = $$( ':scope > li', row );

		var place = function ( li, animate ) {
			var a = li && li.querySelector( 'a' );

			if ( ! a || ! a.offsetWidth ) {
				return;
			}

			if ( ! animate ) {
				ind.style.transition = 'none';
			}

			ind.style.transform = 'translateX(' + a.offsetLeft + 'px) scaleX(' + ( a.offsetWidth / 100 ) + ')';

			if ( ! animate ) {
				void ind.offsetWidth;
				ind.style.transition = '';
			}
		};

		var active = row.querySelector( 'li.is-active' );
		var from = parseInt( html.getAttribute( 'data-famma-acct-from' ) || '', 10 );

		if ( active ) {
			if ( ! RM && ! isNaN( from ) && tabs[ from ] && tabs[ from ] !== active ) {
				place( tabs[ from ], false );
				window.requestAnimationFrame( function () {
					window.requestAnimationFrame( function () {
						place( active, true );
					} );
				} );
			} else {
				place( active, false );
			}

			if ( row.scrollWidth > row.clientWidth ) {
				active.scrollIntoView( { block: 'nearest', inline: 'center' } );
			}

			window.addEventListener( 'resize', function () {
				place( active, false );
			} );

			// Onglets cachés derrière l'écran « Bienvenue » : placés à son départ.
			doc.addEventListener( 'famma:acct-welcomed', function () {
				place( active, false );
			} );
		}

		// Onglets collés sous l'en-tête du site, quand il reste visible.
		var ticking = false;
		var stick = function () {
			ticking = false;

			var bar = doc.querySelector( '.item-is-fixed' );
			var top = 0;

			if ( bar ) {
				var r = bar.getBoundingClientRect();
				top = r.bottom > 0 ? Math.round( r.bottom ) : 0;
			}

			nav.style.setProperty( '--famma-acct-tabs-top', top + 'px' );
			nav.classList.toggle( 'is-stuck', window.scrollY > 120 && nav.getBoundingClientRect().top <= top + 1 );
		};

		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( stick );
			}
		}, { passive: true } );
		stick();
	}

	/* ---------- Frise : se remplit à l'écran ---------- */

	var lines = $$( '.famma-oc__tl' );
	var fill = function ( el ) {
		el.classList.add( 'is-in' );
	};

	if ( RM || ! ( 'IntersectionObserver' in window ) ) {
		lines.forEach( fill );
	} else if ( lines.length ) {
		var io = new window.IntersectionObserver( function ( entries ) {
			entries.forEach( function ( en ) {
				if ( en.isIntersecting ) {
					io.unobserve( en.target );
					window.setTimeout( fill, 120, en.target );
				}
			} );
		}, { threshold: 0.4 } );

		lines.forEach( function ( el ) {
			io.observe( el );
		} );
	}

	/* ---------- Détail d'une commande ---------- */

	doc.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest && e.target.closest( '.famma-oc__more' );

		if ( ! btn ) {
			return;
		}

		var box = doc.getElementById( btn.getAttribute( 'aria-controls' ) );

		if ( ! box ) {
			return;
		}

		var open = btn.getAttribute( 'aria-expanded' ) !== 'true';
		var label = btn.querySelector( 'span' );

		btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		box.classList.toggle( 'is-open', open );

		if ( label ) {
			label.textContent = btn.getAttribute( open ? 'data-less' : 'data-more' );
		}
	} );
}() );
