/**
 * Offre après achat — fiche rapide « Découvrir le produit ».
 *
 * Ouvre la fiche dans la page de remerciement au lieu d'envoyer la cliente
 * sur la fiche produit (où elle perdrait le prix spécial et pourrait passer
 * une deuxième commande). Fermeture : croix, « Retour », touche Échap, toucher
 * à côté, glisser vers le bas, et bouton retour du téléphone.
 *
 * Sans <dialog> (très vieux navigateur), rien n'est intercepté : le lien mène
 * à la fiche produit.
 */
( function () {
	'use strict';

	var sheet = document.getElementById( 'famma-offer-sheet' );
	if ( ! sheet || 'function' !== typeof sheet.showModal ) {
		return;
	}

	var form = document.getElementById( 'famma-offer-form' );
	var root = document.documentElement;
	var pushed = false;
	var tracked = false;

	/**
	 * Photos chargées à la première ouverture seulement ; au-delà des deux
	 * premières, le navigateur les charge à l'approche (loading=lazy).
	 */
	function loadPhotos() {
		sheet.querySelectorAll( 'img[data-src]' ).forEach( function ( img, i ) {
			if ( i > 1 ) {
				img.loading = 'lazy';
			}
			if ( img.dataset.srcset ) {
				img.srcset = img.dataset.srcset;
			}
			img.src = img.dataset.src;
			img.removeAttribute( 'data-src' );
			img.removeAttribute( 'data-srcset' );
		} );
	}

	/** Mesure « fiche ouverte » (une fois par page), sans bloquer l'écran. */
	function track() {
		if ( tracked || ! form ) {
			return;
		}
		var nonce = form.querySelector( '[name="famma_offer_nonce"]' );
		if ( ! nonce ) {
			return;
		}
		tracked = true;
		var data = new FormData();
		data.append( 'famma_offer_action', 'view' );
		data.append( 'famma_offer_nonce', nonce.value );
		if ( navigator.sendBeacon && navigator.sendBeacon( form.action, data ) ) {
			return;
		}
		if ( window.fetch ) {
			fetch( form.action, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true } ).catch( function () {} );
		}
	}

	function open( event ) {
		event.preventDefault();
		if ( sheet.open ) {
			return;
		}
		loadPhotos();
		sheet.style.transform = '';
		sheet.showModal();
		// Toujours repartir des photos, même après une première lecture.
		var scroller = sheet.querySelector( '.famma-sheet__scroll' );
		var photos = sheet.querySelector( '.famma-sheet__gallery' );
		if ( scroller ) {
			scroller.scrollTop = 0;
		}
		if ( photos ) {
			photos.scrollLeft = 0;
		}
		setActive( 0 );
		root.classList.add( 'famma-sheet-open' );
		// Une entrée d'historique : le bouton retour du téléphone ferme la
		// fiche au lieu de quitter la page de remerciement.
		try {
			history.pushState( { fammaSheet: true }, '' );
			pushed = true;
		} catch ( e ) {
			pushed = false;
		}
		track();
	}

	function close() {
		if ( sheet.open ) {
			sheet.close();
		}
	}

	sheet.addEventListener( 'close', function () {
		root.classList.remove( 'famma-sheet-open' );
		sheet.style.transform = '';
		if ( pushed ) {
			pushed = false;
			history.back();
		}
	} );

	window.addEventListener( 'popstate', function () {
		if ( sheet.open ) {
			pushed = false;
			sheet.close();
		}
	} );

	// Le bouton « Oui, ajouter » de la fiche envoie le formulaire : l'entrée
	// d'historique ajoutée ne doit pas déclencher de retour en arrière.
	if ( form ) {
		form.addEventListener( 'submit', function () {
			pushed = false;
		} );
	}

	document.querySelectorAll( '[data-famma-sheet-open]' ).forEach( function ( el ) {
		el.addEventListener( 'click', open );
	} );

	sheet.querySelectorAll( '[data-famma-sheet-close]' ).forEach( function ( el ) {
		el.addEventListener( 'click', close );
	} );

	// Toucher le fond assombri : la fiche occupe tout le <dialog>, donc un
	// clic dont la cible est le <dialog> lui-même vient du fond.
	sheet.addEventListener( 'click', function ( event ) {
		if ( event.target === sheet ) {
			close();
		}
	} );

	// Glisser vers le bas depuis la poignée.
	var drag = sheet.querySelector( '[data-famma-sheet-drag]' );
	var startY = null;
	if ( drag ) {
		drag.addEventListener( 'pointerdown', function ( event ) {
			startY = event.clientY;
			sheet.classList.add( 'is-dragging' );
			if ( drag.setPointerCapture ) {
				drag.setPointerCapture( event.pointerId );
			}
		} );
		drag.addEventListener( 'pointermove', function ( event ) {
			if ( null === startY ) {
				return;
			}
			var dy = Math.max( 0, event.clientY - startY );
			sheet.style.transform = 'translateY(' + dy + 'px)';
		} );
		var end = function ( event ) {
			if ( null === startY ) {
				return;
			}
			var dy = event.clientY - startY;
			startY = null;
			sheet.classList.remove( 'is-dragging' );
			if ( dy > 90 ) {
				close();
			} else {
				sheet.style.transform = '';
			}
		};
		drag.addEventListener( 'pointerup', end );
		drag.addEventListener( 'pointercancel', end );
	}

	// Galerie : glisser (doigt, pavé tactile, flèches du clavier), flèches
	// précédent / suivant, points cliquables et compteur « 2 / 6 ».
	var gallery = sheet.querySelector( '.famma-sheet__gallery' );
	var slides = gallery ? gallery.querySelectorAll( '.famma-sheet__slide' ) : [];
	var dots = sheet.querySelectorAll( '.famma-sheet__dot' );
	var prev = sheet.querySelector( '[data-famma-gallery="prev"]' );
	var next = sheet.querySelector( '[data-famma-gallery="next"]' );
	var counter = sheet.querySelector( '[data-famma-gallery-index]' );
	var current = 0;
	var steering = 0;
	var calm = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function setActive( index ) {
		current = index;
		dots.forEach( function ( dot, i ) {
			dot.classList.toggle( 'is-active', i === index );
			if ( i === index ) {
				dot.setAttribute( 'aria-current', 'true' );
			} else {
				dot.removeAttribute( 'aria-current' );
			}
		} );
		if ( counter ) {
			counter.textContent = String( index + 1 );
		}
		if ( prev ) {
			prev.disabled = 0 === index;
		}
		if ( next ) {
			next.disabled = index === slides.length - 1;
		}
	}

	// Défilement calculé sur la position réelle de la photo : juste en
	// français comme en arabe (sens de lecture inversé).
	function goTo( index, smooth ) {
		if ( ! gallery || ! slides.length ) {
			return;
		}
		index = Math.max( 0, Math.min( slides.length - 1, index ) );
		// Pendant le défilement fluide, les photos traversées ne doivent pas
		// changer le compteur (sinon un double toucher perd une photo).
		steering = Date.now() + 700;
		var delta = slides[ index ].getBoundingClientRect().left - gallery.getBoundingClientRect().left;
		gallery.scrollBy( { left: delta, behavior: smooth && ! calm ? 'smooth' : 'auto' } );
		setActive( index );
	}

	if ( prev ) {
		prev.addEventListener( 'click', function () {
			goTo( current - 1, true );
		} );
	}
	if ( next ) {
		next.addEventListener( 'click', function () {
			goTo( current + 1, true );
		} );
	}
	dots.forEach( function ( dot, i ) {
		dot.addEventListener( 'click', function () {
			goTo( i, true );
		} );
	} );

	if ( gallery && slides.length > 1 && 'IntersectionObserver' in window ) {
		var observer = new IntersectionObserver(
			function ( entries ) {
				if ( Date.now() < steering ) {
					return;
				}
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						setActive( Array.prototype.indexOf.call( slides, entry.target ) );
					}
				} );
			},
			{ root: gallery, threshold: 0.6 }
		);
		slides.forEach( function ( slide ) {
			observer.observe( slide );
		} );
	}
} )();
