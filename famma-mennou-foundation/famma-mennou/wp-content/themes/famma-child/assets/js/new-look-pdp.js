/**
 * Nouveau look — fiche produit, lot 44 (brique `pdp_gallery`).
 *
 * Habille la galerie de WooCommerce sans la remplacer : glisser, vignettes,
 * loupe plein écran et variantes restent les siens. Le script ne fait que
 * lire l'état de la galerie (classe `flex-active` des vignettes, ou
 * `flex-active-slide` des photos) pour poser :
 * - le compteur « 2 / 5 » en bas de la photo ;
 * - le cadre orange qui glisse d'une vignette à l'autre ;
 * - sur ordinateur (souris), le zoom ×1,7 qui suit le pointeur.
 * Une seule photo, ou une galerie que flexslider n'a pas démarrée : rien.
 * Moins d'animations demandé : pas de zoom ; le reste est immobile (CSS).
 */
( function () {
	'use strict';

	var gallery = document.querySelector( '.famma-product .woocommerce-product-gallery' );

	if ( ! gallery || ! window.MutationObserver ) {
		return;
	}

	var ready = false;

	function start() {
		var thumbs = gallery.querySelector( '.flex-control-thumbs' );
		var slides = gallery.querySelectorAll( '.woocommerce-product-gallery__image' );

		if ( ready || ! thumbs || slides.length < 2 ) {
			return;
		}

		ready = true;

		var count = document.createElement( 'span' );
		count.className = 'famma-pdp-count';
		count.setAttribute( 'dir', 'ltr' );
		count.setAttribute( 'aria-hidden', 'true' );
		gallery.appendChild( count );

		var ind = document.createElement( 'span' );
		ind.className = 'famma-pdp-ind';
		ind.setAttribute( 'aria-hidden', 'true' );
		thumbs.appendChild( ind );
		thumbs.classList.add( 'famma-pdp-ind-on' );

		function current() {
			var imgs = thumbs.querySelectorAll( 'img' );
			var i;

			for ( i = 0; i < imgs.length; i++ ) {
				if ( imgs[ i ].classList.contains( 'flex-active' ) ) {
					return { index: i, img: imgs[ i ] };
				}
			}

			for ( i = 0; i < slides.length; i++ ) {
				if ( slides[ i ].classList.contains( 'flex-active-slide' ) ) {
					return { index: i, img: imgs[ i ] || null };
				}
			}

			return { index: 0, img: imgs[ 0 ] || null };
		}

		function paint() {
			var now = current();

			count.textContent = ( now.index + 1 ) + ' / ' + slides.length;

			if ( ! now.img ) {
				ind.style.display = 'none';
				return;
			}

			// Coordonnées physiques : le cadre est posé en haut à gauche de la
			// rangée (même en arabe) et déplacé d'autant que la vignette.
			var box = thumbs.getBoundingClientRect();
			var img = now.img.getBoundingClientRect();
			var x = img.left - box.left - thumbs.clientLeft + thumbs.scrollLeft;
			var y = img.top - box.top - thumbs.clientTop + thumbs.scrollTop;

			ind.style.display = '';
			ind.style.width = img.width + 'px';
			ind.style.height = img.height + 'px';
			ind.style.transform = 'translate(' + x + 'px,' + y + 'px)';
		}

		new MutationObserver( paint ).observe( gallery, {
			attributes: true,
			attributeFilter: [ 'class' ],
			subtree: true,
		} );

		if ( window.ResizeObserver ) {
			new ResizeObserver( paint ).observe( thumbs );
		} else {
			window.addEventListener( 'resize', paint );
		}

		paint();
		zoom();
	}

	function zoom() {
		var fine = window.matchMedia && window.matchMedia( '(hover: hover) and (pointer: fine)' );
		var calm = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' );

		if ( ! fine || ! fine.matches || ( calm && calm.matches ) ) {
			return;
		}

		gallery.querySelectorAll( '.woocommerce-product-gallery__image' ).forEach( function ( cell ) {
			var img = cell.querySelector( 'img' );

			if ( ! img ) {
				return;
			}

			cell.addEventListener( 'pointermove', function ( e ) {
				var r = cell.getBoundingClientRect();

				if ( ! r.width || ! r.height ) {
					return;
				}

				img.style.setProperty( '--famma-ox', ( ( e.clientX - r.left ) / r.width * 100 ).toFixed( 1 ) + '%' );
				img.style.setProperty( '--famma-oy', ( ( e.clientY - r.top ) / r.height * 100 ).toFixed( 1 ) + '%' );
				img.style.setProperty( '--famma-z', '1.7' );
			} );

			cell.addEventListener( 'pointerleave', function () {
				img.style.setProperty( '--famma-z', '1' );
			} );
		} );
	}

	// flexslider construit les vignettes après le chargement des images.
	var wait = new MutationObserver( function () {
		start();

		if ( ready ) {
			wait.disconnect();
		}
	} );

	wait.observe( gallery, { childList: true, subtree: true } );
	start();

	window.addEventListener( 'load', function () {
		start();

		if ( ready ) {
			wait.disconnect();
		}
	} );
}() );
