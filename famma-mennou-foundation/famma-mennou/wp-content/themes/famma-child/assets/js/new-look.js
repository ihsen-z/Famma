/**
 * FAMMA — nouveau look animé (maquettes du 04-06/11/2026).
 *
 * Surcouche purement visuelle. Ce fichier n'ajoute jamais rien au panier,
 * n'empêche aucun clic (pas de preventDefault, pas de stopPropagation) et
 * n'émet aucun événement de suivi : l'ajout reste celui de WooCommerce, le
 * pixel Meta garde son propre écouteur sur `added_to_cart`. On écoute les
 * mêmes événements, après coup, pour animer.
 *
 * Chaque brique ne démarre que si le serveur a posé sa classe sur <body>
 * (famma-nl-motion, famma-nl-alt-photo, famma-nl-dock). Une erreur dans une
 * brique ne casse pas les autres : chacune est isolée dans un try/catch.
 */
( function () {
	'use strict';

	var body = document.body;

	if ( ! body || ! body.classList.contains( 'famma-nl' ) ) {
		return;
	}

	var has = function ( part ) {
		return body.classList.contains( 'famma-nl-' + part );
	};
	var reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var fine = window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;
	var EASE = 'cubic-bezier(0.23, 1, 0.32, 1)';
	var SPRING = window.CSS && CSS.supports && CSS.supports( 'animation-timing-function', 'linear(0, 1)' )
		? 'linear(0, 0.06 3%, 0.22 7%, 0.55 14%, 0.86 22%, 1.04 30%, 1.09 36%, 1.07 44%, 1.01 58%, 0.99 72%, 1)'
		: 'cubic-bezier(0.34, 1.56, 0.64, 1)';
	var $ = window.jQuery;

	function animate( el, frames, options ) {
		if ( reduced || ! el || ! el.animate ) {
			return null;
		}
		try {
			return el.animate( frames, options );
		} catch ( error ) {
			return null;
		}
	}

	function center( el ) {
		var r = el.getBoundingClientRect();
		return [ r.left + r.width / 2, r.top + r.height / 2 ];
	}

	function visible( el ) {
		if ( ! el || el.hidden ) {
			return false;
		}
		var r = el.getBoundingClientRect();
		return r.width > 0 && r.bottom > 0 && r.top < window.innerHeight;
	}

	function guard( name, fn ) {
		try {
			fn();
		} catch ( error ) {
			if ( window.console && console.warn ) {
				console.warn( 'FAMMA nouveau look, brique « ' + name + ' » ignorée :', error );
			}
		}
	}

	/* Étincelles : le motif des trois traits du logo, éclaté autour d'un point. */
	function burst( x, y, count ) {
		if ( reduced || ! document.body.animate ) {
			return;
		}
		var n = count || 7;
		for ( var i = 0; i < n; i++ ) {
			( function ( index ) {
				var s = document.createElement( 'i' );
				var a = ( index / n ) * Math.PI * 2 + 0.3;
				var d = 20 + ( index % 3 ) * 8;
				var deg = a * 180 / Math.PI + 90;
				s.className = 'famma-nl-spark';
				s.setAttribute( 'aria-hidden', 'true' );
				document.body.appendChild( s );
				var run = s.animate( [
					{ transform: 'translate(' + x + 'px,' + y + 'px) rotate(' + deg + 'deg) scaleY(.2)', opacity: 1 },
					{ transform: 'translate(' + ( x + Math.cos( a ) * d ) + 'px,' + ( y + Math.sin( a ) * d ) + 'px) rotate(' + deg + 'deg) scaleY(1)', opacity: 1, offset: 0.55 },
					{ transform: 'translate(' + ( x + Math.cos( a ) * ( d + 9 ) ) + 'px,' + ( y + Math.sin( a ) * ( d + 9 ) ) + 'px) rotate(' + deg + 'deg) scaleY(.2)', opacity: 0 }
				], { duration: 520, easing: 'cubic-bezier(.2,.8,.2,1)' } );
				var done = function () {
					s.remove();
				};
				run.onfinish = done;
				run.oncancel = done;
				window.setTimeout( done, 900 );
			} )( i );
		}
	}

	/* ---------------------------------------------------------------
	   1. Apparition des cartes — visibles au repos, l'animation ne fait
	      que rejouer leur entrée. Rien n'est jamais laissé à opacity 0.
	   --------------------------------------------------------------- */
	guard( 'apparitions', function () {
		if ( ! has( 'motion' ) || reduced || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		var cards = document.querySelectorAll( 'ul.products.famma-cards > li.product' );

		if ( ! cards.length ) {
			return;
		}

		var io = new IntersectionObserver( function ( entries ) {
			var k = 0;
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) {
					return;
				}
				io.unobserve( entry.target );
				animate( entry.target, [
					{ opacity: 0, transform: 'translateY(18px) scale(.97)' },
					{ opacity: 1, transform: 'none' }
				], { duration: 520, delay: Math.min( k++, 5 ) * 70, easing: SPRING, fill: 'backwards' } );
			} );
		}, { rootMargin: '0px 0px -6% 0px' } );

		Array.prototype.forEach.call( cards, function ( card ) {
			// Les cartes déjà à l'écran au chargement ne rejouent rien :
			// le premier rendu reste celui du serveur, sans clignotement.
			var r = card.getBoundingClientRect();
			if ( r.top < window.innerHeight && r.bottom > 0 ) {
				return;
			}
			io.observe( card );
		} );
	} );

	/* ---------------------------------------------------------------
	   2. Seconde photo — l'image n'est créée qu'au premier survol.
	   --------------------------------------------------------------- */
	guard( 'seconde photo', function () {
		if ( ! has( 'alt-photo' ) || ! fine ) {
			return;
		}

		document.addEventListener( 'pointerover', function ( event ) {
			var card = event.target.closest && event.target.closest( 'ul.products.famma-cards > li.product' );
			if ( ! card ) {
				return;
			}
			var slot = card.querySelector( '.famma-alt[data-src]' );
			if ( ! slot || slot.firstChild ) {
				return;
			}
			var main = card.querySelector( '.woocommerce-loop-image-link img' );
			var img = new Image();
			img.alt = '';
			img.decoding = 'async';
			if ( slot.getAttribute( 'data-srcset' ) ) {
				img.srcset = slot.getAttribute( 'data-srcset' );
				img.sizes = ( main && main.getAttribute( 'sizes' ) ) || '300px';
			}
			img.src = slot.getAttribute( 'data-src' );
			img.addEventListener( 'load', function () {
				slot.classList.add( 'is-ready' );
			} );
			slot.appendChild( img );
		}, { passive: true } );
	} );

	/* ---------------------------------------------------------------
	   3. Favoris — le cœur pétille quand il s'allume. favourites.js
	      garde seul l'état ; on lit aria-pressed après son passage.
	   --------------------------------------------------------------- */
	guard( 'favoris', function () {
		if ( ! has( 'motion' ) ) {
			return;
		}

		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest && event.target.closest( 'button.famma-fav' );
			if ( ! button ) {
				return;
			}
			window.setTimeout( function () {
				if ( button.getAttribute( 'aria-pressed' ) !== 'true' ) {
					return;
				}
				animate( button, [
					{ transform: 'scale(.7)' },
					{ transform: 'scale(1.12)', offset: 0.6 },
					{ transform: 'scale(1)' }
				], { duration: 480, easing: EASE } );
				var c = center( button );
				burst( c[ 0 ], c[ 1 ], 7 );
			}, 0 );
		} );
	} );

	/* ---------------------------------------------------------------
	   4. Barre du panier — remplie par les fragments WooCommerce.
	   --------------------------------------------------------------- */
	function dock() {
		return document.querySelector( 'div.famma-dock' );
	}

	function syncDock( arrived ) {
		var el = dock();
		if ( ! el ) {
			return;
		}
		var on = ! el.hidden && parseInt( el.getAttribute( 'data-count' ) || '0', 10 ) > 0;
		var was = body.classList.contains( 'famma-dock-on' );
		body.classList.toggle( 'famma-dock-on', on );
		if ( on && ! was ) {
			animate( el, [
				{ transform: 'translateY(140%)' },
				{ transform: 'none' }
			], { duration: 460, easing: 'cubic-bezier(0.32, 0.72, 0, 1)' } );
		}
		if ( on && arrived ) {
			window.setTimeout( function () {
				animate( el.querySelector( '.famma-dock__zero' ), [
					{ transform: 'scale(1.5)', opacity: 0.4 },
					{ transform: 'scale(1)', opacity: 1 }
				], { duration: 520, easing: SPRING } );
			}, 380 );
		}
	}

	/* Le HTML en cache porte une barre vide. Si le navigateur a déjà un
	   panier (cookie posé par WooCommerce), on relit les fragments une
	   seule fois ; sinon, aucune requête n'est envoyée. */
	function readCartOnce() {
		var el = dock();
		if ( ! el || document.cookie.indexOf( 'woocommerce_items_in_cart=1' ) === -1 ) {
			return;
		}
		var url = el.getAttribute( 'data-endpoint' );
		if ( ! url || ! window.fetch ) {
			return;
		}
		window.fetch( url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } } )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( data ) {
				if ( ! data || ! data.fragments ) {
					return;
				}
				[ 'div.famma-dock', '.famma-cart__badge' ].forEach( function ( selector ) {
					var html = data.fragments[ selector ];
					var target = document.querySelector( selector );
					if ( ! html || ! target ) {
						return;
					}
					var holder = document.createElement( 'div' );
					holder.innerHTML = html;
					if ( holder.firstElementChild ) {
						target.replaceWith( holder.firstElementChild );
					}
				} );
				syncDock( false );
			} )
			.catch( function () {} );
	}

	guard( 'barre du panier', function () {
		if ( ! has( 'dock' ) ) {
			return;
		}
		syncDock( false );
		readCartOnce();
	} );

	/* ---------------------------------------------------------------
	   5. Ajout au panier — la photo vole vers le panier. Les écouteurs
	      jQuery sont ceux de WooCommerce (add-to-cart.js) ; WooCommerce
	      remplace ses fragments dans son propre écouteur, d'où le report
	      d'un tour de boucle avant de lire le DOM.
	   --------------------------------------------------------------- */
	function flyFrom( img, target ) {
		if ( reduced || ! img || ! target || ! document.body.animate ) {
			return;
		}
		var start = center( img );
		var end = center( target );
		var ox = document.createElement( 'div' );
		var oy = document.createElement( 'div' );
		var ghost = new Image();
		ghost.src = img.currentSrc || img.src;
		ghost.alt = '';
		ox.className = 'famma-nl-fly';
		ox.setAttribute( 'aria-hidden', 'true' );
		oy.appendChild( ghost );
		ox.appendChild( oy );
		document.body.appendChild( ox );
		var D = 620;
		ox.animate( [ { transform: 'translateX(' + start[ 0 ] + 'px)' }, { transform: 'translateX(' + end[ 0 ] + 'px)' } ], { duration: D, easing: 'cubic-bezier(.45,.05,.55,.95)', fill: 'forwards' } );
		oy.animate( [ { transform: 'translateY(' + start[ 1 ] + 'px)' }, { transform: 'translateY(' + ( Math.min( start[ 1 ], end[ 1 ] ) - 60 ) + 'px)', offset: 0.3 }, { transform: 'translateY(' + end[ 1 ] + 'px)' } ], { duration: D, easing: 'cubic-bezier(.3,.6,.5,1)', fill: 'forwards' } );
		var g = ghost.animate( [ { transform: 'scale(1) rotate(0)', opacity: 1 }, { transform: 'scale(.9) rotate(-8deg)', opacity: 1, offset: 0.5 }, { transform: 'scale(.2) rotate(12deg)', opacity: 0.7 } ], { duration: D, easing: 'ease-in', fill: 'forwards' } );
		var done = function () {
			ox.remove();
		};
		g.onfinish = done;
		g.oncancel = done;
		window.setTimeout( done, D + 400 );
	}

	function bump() {
		var cart = document.querySelector( '.famma-header .famma-cart' );
		if ( cart && visible( cart ) ) {
			animate( cart, [ { transform: 'scale(1)' }, { transform: 'scale(1.22)' }, { transform: 'scale(1)' } ], { duration: 460, easing: SPRING } );
			var badge = cart.querySelector( '.famma-cart__badge' );
			animate( badge, [ { transform: 'scale(.4)' }, { transform: 'scale(1)' } ], { duration: 480, easing: SPRING } );
			var c = center( cart );
			burst( c[ 0 ], c[ 1 ], 6 );
		}
	}

	guard( 'ajout au panier', function () {
		if ( ! $ || ! ( has( 'motion' ) || has( 'dock' ) ) ) {
			return;
		}

		$( document.body ).on( 'adding_to_cart', function ( event, $button ) {
			if ( ! has( 'motion' ) || ! $button || ! $button.length ) {
				return;
			}
			var card = $button[ 0 ].closest( 'li.product' );
			var img = card && card.querySelector( '.woocommerce-loop-image-link img' );
			var target = visible( dock() ) ? dock() : document.querySelector( '.famma-header .famma-cart' );
			if ( img && visible( img ) && visible( target ) ) {
				flyFrom( img, target );
			}
		} );

		$( document.body ).on( 'added_to_cart', function () {
			window.setTimeout( function () {
				if ( has( 'motion' ) ) {
					bump();
				}
				if ( has( 'dock' ) ) {
					syncDock( true );
				}
			}, 0 );
		} );
	} );
	/* Navigation du bureau : un trait orange glisse sous le lien survolé ou
	   focalisé, puis revient sous l'entrée active. Seulement avec un pointeur
	   fin et sans demande de moins de mouvement ; sinon le soulignement CSS
	   d'origine reste en place. Aucun changement de mise en page : le trait
	   est en position absolue et ne bouge que par `transform`. */
	guard( 'navigation', function () {
		if ( ! has( 'motion' ) || reduced || ! fine ) {
			return;
		}

		var list = document.querySelector( '.famma-nav--desktop .famma-nav__list' );

		if ( ! list ) {
			return;
		}

		var links = Array.prototype.filter.call( list.children, function ( li ) {
			return li.tagName === 'LI' && li.querySelector( 'a' );
		} ).map( function ( li ) {
			return li.querySelector( 'a' );
		} );
		var current = list.querySelector( ':scope > .current-menu-item > a, :scope > .current-menu-ancestor > a, :scope > .current_page_item > a' );
		var ink = document.createElement( 'span' );

		ink.className = 'famma-nav__ink';
		ink.setAttribute( 'aria-hidden', 'true' );
		list.appendChild( ink );
		list.classList.add( 'has-ink' );

		function moveTo( link, instant ) {
			if ( ! link ) {
				ink.style.opacity = '0';
				return;
			}

			var box = list.getBoundingClientRect();
			var r = link.getBoundingClientRect();

			ink.style.transition = instant ? 'none' : '';
			ink.style.opacity = '1';
			ink.style.transform = 'translateX(' + ( r.left - box.left ) + 'px) scaleX(' + r.width + ')';
		}

		links.forEach( function ( link ) {
			link.addEventListener( 'mouseenter', function () {
				moveTo( link, false );
			} );
			link.addEventListener( 'focus', function () {
				moveTo( link, false );
			} );
		} );
		list.addEventListener( 'mouseleave', function () {
			moveTo( current, false );
		} );
		list.addEventListener( 'focusout', function ( event ) {
			if ( ! list.contains( event.relatedTarget ) ) {
				moveTo( current, false );
			}
		} );

		var place = function () {
			moveTo( current, true );
		};

		place();
		window.addEventListener( 'resize', place, { passive: true } );

		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( place );
		}
	} );
	/* Héros « pile » : la pile s'incline légèrement vers le pointeur fin.
	   Seulement sur le conteneur de la pile (.famma-hero2__deck), jamais sur
	   les cartes, dont hero.js gère seul la position et le glisser. Remise à
	   plat dès qu'un geste commence ou que le pointeur sort. */
	guard( 'héros', function () {
		if ( ! has( 'motion' ) || reduced || ! fine ) {
			return;
		}

		var deck = document.querySelector( '.famma-hero2__media--stack .famma-hero2__deck' );

		if ( ! deck ) {
			return;
		}

		var frame = 0;
		var pending = null;
		var held = false;

		deck.classList.add( 'has-tilt' );

		function paint() {
			frame = 0;

			if ( ! pending || held ) {
				deck.style.transform = '';
				return;
			}

			deck.style.transform = 'perspective(900px) rotateX(' + ( pending.y * -5 ).toFixed( 2 ) + 'deg) rotateY(' + ( pending.x * 6 ).toFixed( 2 ) + 'deg)';
		}

		function queue( value ) {
			pending = value;

			if ( ! frame ) {
				frame = window.requestAnimationFrame( paint );
			}
		}

		deck.addEventListener( 'pointermove', function ( event ) {
			if ( 'mouse' !== event.pointerType ) {
				return;
			}

			var r = deck.getBoundingClientRect();

			queue( {
				x: ( event.clientX - r.left ) / r.width - 0.5,
				y: ( event.clientY - r.top ) / r.height - 0.5
			} );
		} );
		deck.addEventListener( 'pointerdown', function () {
			held = true;
			queue( null );
		} );
		window.addEventListener( 'pointerup', function () {
			held = false;
		} );
		deck.addEventListener( 'pointerleave', function () {
			queue( null );
		} );
	} );
} )();
