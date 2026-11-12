/**
 * Upsell One Click — quick view sheet.
 *
 * Opens the product sheet inside the thank-you page instead of sending the
 * customer to the product page (where the special price is lost and a second
 * order may be placed). Closing: cross, "Back", Escape, tap outside, drag
 * down, and the phone's back button.
 *
 * Without <dialog> (very old browser), nothing is intercepted: the link leads
 * to the product page.
 */
( function () {
	'use strict';

	var sheet = document.getElementById( 'uoc-offer-sheet' );
	if ( ! sheet || 'function' !== typeof sheet.showModal ) {
		return;
	}

	var form = document.getElementById( 'uoc-offer-form' );
	var root = document.documentElement;
	var pushed = false;
	var tracked = false;

	/**
	 * Photos load on first opening only; beyond the first two, the browser
	 * loads them when they come close (loading=lazy).
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

	/** "Sheet opened" measurement (once per page), without blocking. */
	function track() {
		if ( tracked || ! form ) {
			return;
		}
		var nonce = form.querySelector( '[name="upsell_oc_nonce"]' );
		if ( ! nonce ) {
			return;
		}
		tracked = true;
		var data = new FormData();
		data.append( 'upsell_oc_action', 'view' );
		data.append( 'upsell_oc_nonce', nonce.value );
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
		// Always start from the photos, even after a first read.
		var scroller = sheet.querySelector( '.uoc-sheet__scroll' );
		var photos = sheet.querySelector( '.uoc-sheet__gallery' );
		if ( scroller ) {
			scroller.scrollTop = 0;
		}
		if ( photos ) {
			photos.scrollLeft = 0;
		}
		setActive( 0 );
		root.classList.add( 'uoc-sheet-open' );
		// One history entry: the phone's back button closes the sheet
		// instead of leaving the thank-you page.
		try {
			history.pushState( { uocSheet: true }, '' );
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
		root.classList.remove( 'uoc-sheet-open' );
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

	// The sheet's accept button submits the form: the added history entry
	// must not trigger a back navigation.
	if ( form ) {
		form.addEventListener( 'submit', function () {
			pushed = false;
		} );
	}

	document.querySelectorAll( '[data-uoc-sheet-open]' ).forEach( function ( el ) {
		el.addEventListener( 'click', open );
	} );

	sheet.querySelectorAll( '[data-uoc-sheet-close]' ).forEach( function ( el ) {
		el.addEventListener( 'click', close );
	} );

	// Tap on the backdrop: the sheet fills the <dialog>, so a click whose
	// target is the <dialog> itself comes from the backdrop.
	sheet.addEventListener( 'click', function ( event ) {
		if ( event.target === sheet ) {
			close();
		}
	} );

	// Drag down from the handle.
	var drag = sheet.querySelector( '[data-uoc-sheet-drag]' );
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

	// Gallery: swipe (finger, trackpad, keyboard arrows), previous / next
	// arrows, clickable dots and a "2 / 6" counter.
	var gallery = sheet.querySelector( '.uoc-sheet__gallery' );
	var slides = gallery ? gallery.querySelectorAll( '.uoc-sheet__slide' ) : [];
	var dots = sheet.querySelectorAll( '.uoc-sheet__dot' );
	var prev = sheet.querySelector( '[data-uoc-gallery="prev"]' );
	var next = sheet.querySelector( '[data-uoc-gallery="next"]' );
	var counter = sheet.querySelector( '[data-uoc-gallery-index]' );
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

	// Scroll computed from the photo's real position: right in left-to-right
	// and right-to-left alike.
	function goTo( index, smooth ) {
		if ( ! gallery || ! slides.length ) {
			return;
		}
		index = Math.max( 0, Math.min( slides.length - 1, index ) );
		// During smooth scrolling, the photos passed over must not change the
		// counter (otherwise a double tap skips a photo).
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
