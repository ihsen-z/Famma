/**
 * Nouveau look — fiche produit, lot 46 (briques `pdp_story` et `pdp_anatomy`).
 *
 * Récit : les mots s'allument au fil du défilement (mot par mot, jamais
 * lettre par lettre : ligatures arabes) ; toucher une situation écrit sa
 * phrase sous les boutons. « Ce qu'il y a dedans » : la photo zoome sur le
 * repère de la pièce que l'on lit. Sans ce script, le texte est entier et la
 * première pièce est marquée. Moins d'animations demandé : ni lecture au
 * défilement ni zoom, seulement le repère et la pièce marqués.
 */
( function () {
	'use strict';

	var calm = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var txt = document.querySelector( '.famma-story__txt' );
	var chips = document.querySelector( '.famma-story__chips' );
	var echo = document.querySelector( '.famma-story__echo' );
	var items = Array.prototype.slice.call( document.querySelectorAll( '.famma-ana__item' ) );
	var spots = document.querySelectorAll( '.famma-ana__spot' );
	var cam = document.querySelector( '.famma-ana__cam' );
	var sticky = document.querySelector( '.famma-ana__sticky' );
	var words = [];
	var current = 0;
	var ticking = false;

	/* ---------- Récit ---------- */

	function split( node ) {
		Array.prototype.slice.call( node.childNodes ).forEach( function ( child ) {
			if ( 1 === child.nodeType ) {
				split( child );
				return;
			}
			if ( 3 !== child.nodeType ) {
				return;
			}

			var frag = document.createDocumentFragment();

			child.textContent.split( /(\s+)/ ).forEach( function ( part ) {
				if ( ! part ) {
					return;
				}
				if ( /^\s+$/.test( part ) ) {
					frag.appendChild( document.createTextNode( part ) );
					return;
				}

				var w = document.createElement( 'span' );
				w.className = 'famma-story__w';
				w.textContent = part;
				words.push( w );
				frag.appendChild( w );
			} );

			node.replaceChild( frag, child );
		} );
	}

	if ( txt && ! calm ) {
		split( txt );
		txt.classList.add( 'is-scrub' );
	}

	if ( chips && echo ) {
		var pick = echo.getAttribute( 'data-pick' ) || '';

		chips.addEventListener( 'click', function ( e ) {
			var chip = e.target && e.target.closest ? e.target.closest( '.famma-story__chip' ) : null;

			if ( ! chip ) {
				return;
			}

			Array.prototype.forEach.call( chips.querySelectorAll( '.famma-story__chip' ), function ( c ) {
				c.setAttribute( 'aria-pressed', c === chip ? 'true' : 'false' );
			} );

			// Texte seulement, jamais de HTML : la phrase vient du produit.
			var phrase = chip.getAttribute( 'data-phrase' ) || '';
			var at = pick.indexOf( '{situation}' );
			var before = at >= 0 ? pick.slice( 0, at ) : ( pick ? pick + ' ' : '' );
			var after = at >= 0 ? pick.slice( at + 11 ) : '';
			var b = document.createElement( 'b' );

			b.textContent = phrase;
			echo.textContent = '';
			echo.appendChild( document.createTextNode( before ) );
			echo.appendChild( b );
			echo.appendChild( document.createTextNode( after ) );

			if ( ! calm ) {
				echo.classList.remove( 'is-new' );
				void echo.offsetWidth;
				echo.classList.add( 'is-new' );
			}
		} );
	}

	/* ---------- Ce qu'il y a dedans ---------- */

	function setAna( i ) {
		current = i;
		items.forEach( function ( x, k ) {
			x.classList.toggle( 'is-on', k === i );
		} );
		Array.prototype.forEach.call( spots, function ( x, k ) {
			x.classList.toggle( 'is-on', k === i );
		} );

		if ( calm || ! cam || ! spots[ i ] ) {
			return;
		}

		// Repère en coordonnées de la photo (gauche/haut), donc translation physique.
		var x = parseFloat( spots[ i ].style.getPropertyValue( '--x' ) ) || 50;
		var y = parseFloat( spots[ i ].style.getPropertyValue( '--y' ) ) || 50;
		var clamp = function ( v ) {
			return Math.max( -25, Math.min( 25, v ) );
		};

		cam.style.transform = 'translate(' + clamp( ( 50 - x ) * 1.5 ) + '%,' + clamp( ( 50 - y ) * 1.5 ) + '%) scale(1.5)';
	}

	/* ---------- Défilement ---------- */

	function tick() {
		ticking = false;

		var vh = window.innerHeight;

		if ( words.length ) {
			var r = txt.getBoundingClientRect();

			if ( r.bottom > -50 && r.top < vh ) {
				var p = Math.max( 0, Math.min( 1, ( vh * 0.82 - r.top ) / ( r.height + vh * 0.3 ) ) );
				var n = Math.round( p * words.length );

				words.forEach( function ( w, k ) {
					w.classList.toggle( 'is-on', k < n );
				} );
			}
		}

		if ( items.length ) {
			var on = 0;
			var line = vh * 0.55;
			var photo = sticky ? sticky.getBoundingClientRect() : null;
			var list = items[ 0 ].parentNode.getBoundingClientRect();

			// Téléphone : la photo collante couvre le haut de l'écran, la ligne
			// de lecture descend au milieu de ce qui reste visible sous elle.
			if ( photo && photo.left < list.right && photo.right > list.left ) {
				line = Math.max( line, photo.bottom + ( vh - photo.bottom ) * 0.5 );
			}

			// La pièce lue est la dernière dont le haut a passé cette ligne.
			items.forEach( function ( item, k ) {
				if ( item.getBoundingClientRect().top < line ) {
					on = k;
				}
			} );

			if ( on !== current ) {
				setAna( on );
			}
		}
	}

	function onScroll() {
		if ( ! ticking ) {
			ticking = true;
			window.requestAnimationFrame( tick );
		}
	}

	if ( items.length ) {
		setAna( 0 );
	}

	if ( words.length || items.length ) {
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', onScroll, { passive: true } );
		tick();
	}
}() );
