/**
 * FAMMA — carrousel du héros de l'accueil.
 *
 * Ne s'active qu'à partir de deux visuels : le serveur ne pose l'attribut
 * `data-famma-hero` que dans ce cas. Sans lui, ce fichier ne fait rien, et le
 * héros reste un panneau fixe — ce qui est le comportement voulu tant que le
 * catalogue n'a pas assez de produits illustrés.
 *
 * La valeur de l'attribut donne le style choisi en administration
 * (FAMMA → Pages) : `classic` (vide), `stack`, `showcase` ou `thumbs`. Le
 * socle — diapo active, accessibilité, défilement, chargement différé — est
 * commun ; chaque style n'ajoute que sa mise en place.
 *
 * Le défilement automatique s'arrête sous `prefers-reduced-motion`, au survol
 * et dès qu'un élément du héros reçoit le focus clavier : personne ne devrait
 * voir la diapositive changer pendant qu'il lit ou qu'il tabule. Un clic, une
 * flèche ou un glisser l'arrêtent pour de bon : c'est une intention.
 */
( function () {
	'use strict';

	var track = document.querySelector( '[data-famma-hero]' );

	if ( ! track ) {
		return;
	}

	var slides = track.querySelectorAll( '.famma-hero2__slide' );
	var count = slides.length;

	if ( count < 2 ) {
		return;
	}

	var style = track.getAttribute( 'data-famma-hero' ) || 'classic';
	var media = track.closest( '.famma-hero2__media' ) || track.parentNode;
	var hero = track.closest( '.famma-hero2' ) || track;
	var dots = media.querySelectorAll( '[data-famma-hero-dot]' );
	var captions = media.querySelectorAll( '[data-famma-hero-caption]' );
	var forEach = Array.prototype.forEach;

	/*
	 * Les diapos 2 et suivantes — et les miniatures — arrivent sans `src`
	 * (`data-src`, voir famma_child_defer_image) : elles ne disputent plus la
	 * bande passante à l'image LCP. On les rétablit après `load`, bien avant
	 * la première rotation, et au plus tard au premier geste.
	 */
	function hydrate() {
		forEach.call( media.querySelectorAll( 'img[data-src]' ), function ( img ) {
			if ( img.dataset.srcset ) {
				img.srcset = img.dataset.srcset;
				img.removeAttribute( 'data-srcset' );
			}
			img.src = img.dataset.src;
			img.removeAttribute( 'data-src' );
		} );
	}

	if ( 'complete' === document.readyState ) {
		hydrate();
	} else {
		window.addEventListener( 'load', hydrate, { once: true } );
	}

	// Doit rester égal à `--hero-interval` (tokens.css), qui rythme les barres.
	var INTERVAL = 6000;
	// Au-delà de ces seuils, un glisser change de diapo : distance OU vitesse,
	// pour qu'un simple coup de doigt suffise.
	var SWIPE_DISTANCE = 70;
	var SWIPE_VELOCITY = 0.11;

	var current = 0;
	var timer = null;
	var auto = 'thumbs' !== style;
	var reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	var rtl = 'rtl' === window.getComputedStyle( track ).direction;

	/*
	 * Place d'une carte dans la pile : 0 devant, 1 et 2 en éventail derrière,
	 * `out` hors de vue — dont la carte qui vient de partir, pour qu'elle ne
	 * revienne pas en glissant derrière la pile. Même calcul côté serveur :
	 * famma_child_home_hero_stack_slot().
	 */
	function stackSlot( index ) {
		var place = ( index - current + count ) % count;

		if ( 0 === place ) {
			return '0';
		}

		if ( place > 2 || ( count > 2 && count - 1 === place ) ) {
			return 'out';
		}

		return String( place );
	}

	function layoutStack( previous, options ) {
		var forward = previous !== current && current === ( previous + 1 ) % count;
		var backward = previous !== current && ! forward && current === ( previous - 1 + count ) % count;

		if ( forward ) {
			var leaving = slides[ previous ];

			leaving.classList.remove( 'is-dragging' );
			leaving.style.transform = '';
			leaving.classList.toggle( 'is-leaving-end', !! ( options && options.toEnd ) );
			leaving.classList.add( 'is-leaving' );

			window.setTimeout( function () {
				leaving.classList.remove( 'is-leaving', 'is-leaving-end' );
			}, 300 );
		} else if ( backward ) {
			// La carte qui revient arrive du côté où les cartes partent.
			var incoming = slides[ current ];

			incoming.classList.add( 'is-dragging', 'is-leaving' );
			void incoming.offsetWidth;
			incoming.classList.remove( 'is-dragging', 'is-leaving' );
		}

		forEach.call( slides, function ( slide, i ) {
			slide.setAttribute( 'data-pos', stackSlot( i ) );
		} );
	}

	function runBars() {
		forEach.call( dots, function ( bar, i ) {
			bar.classList.remove( 'is-running', 'is-static', 'is-done' );

			if ( i < current ) {
				bar.classList.add( 'is-done' );
			}
		} );

		var bar = dots[ current ];

		if ( bar ) {
			// Relance l'animation de remplissage depuis zéro.
			void bar.offsetWidth;
			bar.classList.add( auto && ! reduced.matches ? 'is-running' : 'is-static' );
		}
	}

	function scrollToCurrent() {
		track.scrollTo( {
			left: ( rtl ? -1 : 1 ) * current * track.clientWidth,
			behavior: reduced.matches ? 'auto' : 'smooth',
		} );
	}

	function show( index, options ) {
		var previous = current;

		current = ( index + count ) % count;

		if ( 0 !== current ) {
			hydrate();
		}

		forEach.call( slides, function ( slide, i ) {
			var active = i === current;
			slide.classList.toggle( 'is-active', active );

			// Une diapositive masquée ne doit être ni lue ni atteignable au
			// clavier : sans cela la tabulation traverse des liens invisibles.
			if ( active ) {
				slide.removeAttribute( 'aria-hidden' );
				slide.removeAttribute( 'tabindex' );
			} else {
				slide.setAttribute( 'aria-hidden', 'true' );
				slide.setAttribute( 'tabindex', '-1' );
			}
		} );

		forEach.call( dots, function ( dot, i ) {
			var active = i === current;
			dot.classList.toggle( 'is-active', active );
			dot.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		} );

		forEach.call( captions, function ( caption, i ) {
			var active = i === current;
			caption.classList.toggle( 'is-active', active );

			if ( active ) {
				caption.removeAttribute( 'aria-hidden' );
			} else {
				caption.setAttribute( 'aria-hidden', 'true' );
			}
		} );

		if ( 'stack' === style ) {
			layoutStack( previous, options );
		} else if ( 'showcase' === style ) {
			runBars();
		} else if ( 'thumbs' === style && ! ( options && options.fromScroll ) ) {
			scrollToCurrent();
		}
	}

	function stop() {
		if ( 'showcase' === style ) {
			// Les barres se figent là où elles en sont : la pause se voit.
			media.classList.add( 'is-paused' );
			return;
		}

		if ( timer ) {
			window.clearInterval( timer );
			timer = null;
		}
	}

	function start() {
		if ( ! auto || reduced.matches ) {
			return;
		}

		if ( 'showcase' === style ) {
			media.classList.remove( 'is-paused' );
			return;
		}

		if ( ! timer ) {
			timer = window.setInterval( function () {
				show( current + 1 );
			}, INTERVAL );
		}
	}

	// Un clic, une flèche, un glisser : on rend la main au visiteur plutôt
	// que de reprendre le défilement sous ses yeux.
	function intent() {
		auto = false;
		stop();
	}

	forEach.call( dots, function ( dot, i ) {
		dot.addEventListener( 'click', function () {
			intent();
			show( i );
		} );
	} );

	forEach.call( media.querySelectorAll( '[data-famma-hero-step]' ), function ( button ) {
		button.addEventListener( 'click', function () {
			intent();
			show( current + Number( button.getAttribute( 'data-famma-hero-step' ) ) );
		} );
	} );

	// Vitrine : chaque barre pleine fait avancer d'une diapo.
	if ( 'showcase' === style ) {
		media.addEventListener( 'animationend', function ( event ) {
			if ( 'famma-hero-fill' === event.animationName && auto ) {
				show( current + 1 );
			}
		} );
	}

	// Vignettes : la piste défile au doigt nativement, on suit sa position.
	if ( 'thumbs' === style ) {
		track.addEventListener( 'scroll', function () {
			var index = Math.round( Math.abs( track.scrollLeft ) / track.clientWidth );

			if ( index !== current && index < count ) {
				show( index, { fromScroll: true } );
			}
		}, { passive: true } );
	}

	/*
	 * Glisser (pile et vitrine). La capture du pointeur n'est prise qu'une
	 * fois le geste engagé : un simple tap reste un clic sur le lien produit.
	 * Un second doigt est ignoré tant que le premier glisse.
	 */
	function swipe( surface ) {
		var pointer = null;
		var startX = 0;
		var startTime = 0;
		var dx = 0;
		var moved = false;

		function swallowClick( event ) {
			event.preventDefault();
			event.stopPropagation();
		}

		function drag( offset ) {
			var card = slides[ current ];
			card.classList.add( 'is-dragging' );
			card.style.transform = 'translateX(' + offset + 'px) rotate(' + ( offset / 18 ) + 'deg)';
		}

		function release() {
			var card = slides[ current ];
			card.classList.remove( 'is-dragging' );
			card.style.transform = '';
		}

		surface.addEventListener( 'dragstart', function ( event ) {
			event.preventDefault();
		} );

		surface.addEventListener( 'pointerdown', function ( event ) {
			if ( null !== pointer || ( 'mouse' === event.pointerType && 0 !== event.button ) ) {
				return;
			}

			pointer = event.pointerId;
			startX = event.clientX;
			startTime = Date.now();
			dx = 0;
			moved = false;
		} );

		surface.addEventListener( 'pointermove', function ( event ) {
			if ( event.pointerId !== pointer ) {
				return;
			}

			dx = event.clientX - startX;

			if ( ! moved && Math.abs( dx ) > 6 ) {
				moved = true;
				surface.setPointerCapture( pointer );
				stop();
				hydrate();
			}

			if ( moved && 'stack' === style ) {
				drag( dx );
			}
		} );

		surface.addEventListener( 'pointerup', function ( event ) {
			if ( event.pointerId !== pointer ) {
				return;
			}

			pointer = null;

			if ( ! moved ) {
				return;
			}

			var velocity = Math.abs( dx ) / Math.max( 1, Date.now() - startTime );

			if ( Math.abs( dx ) > SWIPE_DISTANCE || velocity > SWIPE_VELOCITY ) {
				intent();

				if ( 'stack' === style ) {
					// On jette la carte du dessus, du côté du geste.
					show( current + 1, { toEnd: dx > 0 } );
				} else {
					show( current + ( ( dx < 0 ) !== rtl ? 1 : -1 ) );
				}
			} else {
				if ( 'stack' === style ) {
					release();
				}
				start();
			}

			// Le geste ne doit pas se terminer en clic sur le lien produit.
			surface.addEventListener( 'click', swallowClick, true );
			window.setTimeout( function () {
				surface.removeEventListener( 'click', swallowClick, true );
			}, 0 );
		} );

		surface.addEventListener( 'pointercancel', function ( event ) {
			if ( event.pointerId !== pointer ) {
				return;
			}

			pointer = null;

			if ( moved && 'stack' === style ) {
				release();
			}
		} );
	}

	if ( 'stack' === style || 'showcase' === style ) {
		swipe( track );
	}

	hero.addEventListener( 'mouseenter', stop );
	hero.addEventListener( 'mouseleave', start );
	hero.addEventListener( 'focusin', stop );

	if ( typeof reduced.addEventListener === 'function' ) {
		reduced.addEventListener( 'change', function ( event ) {
			if ( event.matches ) {
				stop();
			} else {
				start();
			}
		} );
	}

	show( 0 );
	start();
}() );
