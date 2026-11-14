/**
 * FAMMA → Nouveau look : onglets, état réel en direct, recherche, modifications.
 *
 * Sans ce script, l'écran montre les cinq pages l'une sous l'autre et
 * s'enregistre pareil. Avec lui, seuls les textes modifiés sont envoyés.
 *
 * @package famma-core
 */
( function () {
	'use strict';

	var root = document.querySelector( '.fnl' );
	var form = document.getElementById( 'fnl-form' );
	var cfg = window.fammaNewLook || {};
	if ( ! root || ! form ) {
		return;
	}

	var tabs = Array.prototype.slice.call( root.querySelectorAll( '.fnl-tab' ) );
	var panels = Array.prototype.slice.call( root.querySelectorAll( '.fnl-panel' ) );
	var bricks = Array.prototype.slice.call( root.querySelectorAll( '.fnl-brick' ) );
	var pages = tabs.map( function ( t ) { return t.getAttribute( 'data-tab' ); } );
	var search = document.getElementById( 'fnl-q' );
	var current = 'site';
	var submitting = false;

	// Un champ d'une ligne retire les retours à la ligne d'une valeur ancienne :
	// la valeur affichée devient la référence, sinon l'écran se croirait
	// modifié dès l'ouverture.
	form.querySelectorAll( 'input[data-text]' ).forEach( function ( f ) {
		if ( f.value !== f.defaultValue ) {
			f.defaultValue = f.value;
		}
	} );

	root.classList.add( 'fnl--js' );
	root.querySelector( '.fnl-tools' ).hidden = false;
	root.querySelector( '.fnl-tabs' ).hidden = false;

	function fmt( text, n ) {
		return String( text || '' ).replace( '%d', n );
	}

	function mode() {
		var on = form.querySelector( 'input[name="famma_new_look[mode]"]:checked' );
		return on ? on.value : 'off';
	}

	function checked( key ) {
		var sw = document.getElementById( 'fnl-sw-' + key );
		return !! ( sw && sw.checked );
	}

	/* ---------- État réel ---------- */

	function stateOf( brick ) {
		var key = brick.getAttribute( 'data-key' );
		var needs = ( brick.getAttribute( 'data-needs' ) || '' ).split( ' ' ).filter( Boolean );
		var wait = ( brick.getAttribute( 'data-wait' ) || '' ).split( ' ' ).filter( Boolean );
		var m = mode();

		if ( ! checked( key ) ) {
			return 'off';
		}
		if ( needs.some( function ( k ) { return document.getElementById( 'fnl-sw-' + k ) && ! checked( k ); } ) ) {
			return 'missing';
		}
		if ( wait.some( function ( id ) { var f = document.getElementById( id ); return f && '' === f.value.trim(); } ) ) {
			return 'wait';
		}
		return 'all' === m ? 'all' : ( 'admins' === m ? 'admins' : 'nobody' );
	}

	function refresh() {
		var counts = { all: 0, admins: 0, warn: 0, off: 0 };

		bricks.forEach( function ( brick ) {
			var s = stateOf( brick );
			var pill = brick.querySelector( '[data-pill]' );
			pill.className = 'fnl-pill fnl-pill--' + s;
			pill.textContent = ( cfg.states || {} )[ s ] || '';

			var line = brick.querySelector( '[data-needs-line]' );
			if ( line ) {
				var needs = ( brick.getAttribute( 'data-needs' ) || '' ).split( ' ' ).filter( Boolean );
				var missing = needs.some( function ( k ) { return ! checked( k ); } );
				line.classList.toggle( 'is-missing', missing );
				line.querySelector( '.fnl-needs__ok' ).hidden = missing;
				line.querySelector( '.fnl-needs__ko' ).hidden = ! missing;
				line.querySelectorAll( '[data-jump]' ).forEach( function ( link ) {
					link.hidden = missing && checked( link.getAttribute( 'data-jump' ) );
				} );
			}

			if ( 'all' === s || 'admins' === s ) {
				counts[ s ]++;
			} else if ( 'missing' === s || 'wait' === s ) {
				counts.warn++;
			} else {
				counts.off++;
			}
		} );

		var list = document.getElementById( 'fnl-summary' );
		if ( list ) {
			list.textContent = '';
			[ 'all', 'admins', 'warn', 'off' ].forEach( function ( g ) {
				if ( ! counts[ g ] && 'off' !== g ) {
					return;
				}
				var li = document.createElement( 'li' );
				li.className = 'fnl-pill fnl-pill--' + g;
				li.textContent = fmt( ( cfg.summary || {} )[ g ], counts[ g ] );
				list.appendChild( li );
			} );
		}

		root.querySelectorAll( '.fnl-mode__help' ).forEach( function ( p ) {
			p.hidden = p.getAttribute( 'data-mode' ) !== mode();
		} );
	}

	/* ---------- Modifications non enregistrées ---------- */

	function isDirty() {
		var dirty = false;
		form.querySelectorAll( 'input, textarea' ).forEach( function ( el ) {
			if ( 'checkbox' === el.type || 'radio' === el.type ) {
				dirty = dirty || el.checked !== el.defaultChecked;
			} else if ( el.hasAttribute( 'data-text' ) ) {
				dirty = dirty || el.value !== el.defaultValue;
			}
		} );
		return dirty;
	}

	function markDirty() {
		var out = document.getElementById( 'fnl-dirty' );
		var dirty = isDirty();
		if ( out ) {
			out.textContent = dirty ? cfg.dirty : cfg.clean;
			out.classList.toggle( 'is-dirty', dirty );
		}
	}

	/* ---------- Onglets ---------- */

	function setTab( page, focus ) {
		if ( pages.indexOf( page ) < 0 ) {
			page = 'site';
		}
		current = page;
		tabs.forEach( function ( t ) {
			var on = t.getAttribute( 'data-tab' ) === page;
			t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			t.tabIndex = on ? 0 : -1;
			if ( on && focus ) {
				t.focus();
			}
		} );
		panels.forEach( function ( p ) {
			p.hidden = p.getAttribute( 'data-page' ) !== page;
		} );
		document.getElementById( 'fnl-tab' ).value = page;
		if ( window.history && window.history.replaceState ) {
			window.history.replaceState( null, '', '#' + page );
		}
	}

	root.querySelector( '.fnl-tabs' ).addEventListener( 'click', function ( e ) {
		var t = e.target.closest( '.fnl-tab' );
		if ( t ) {
			setTab( t.getAttribute( 'data-tab' ), false );
		}
	} );

	root.querySelector( '.fnl-tabs' ).addEventListener( 'keydown', function ( e ) {
		if ( 'ArrowRight' !== e.key && 'ArrowLeft' !== e.key && 'Home' !== e.key && 'End' !== e.key ) {
			return;
		}
		e.preventDefault();
		var i = pages.indexOf( current );
		var rtl = 'rtl' === window.getComputedStyle( root ).direction;
		if ( 'Home' === e.key ) {
			i = 0;
		} else if ( 'End' === e.key ) {
			i = pages.length - 1;
		} else {
			i += ( ( 'ArrowRight' === e.key ) !== rtl ) ? 1 : -1;
		}
		setTab( pages[ ( i + pages.length ) % pages.length ], true );
	} );

	/* ---------- Recherche ---------- */

	function hay( el ) {
		var text = el.getAttribute( 'data-search' ) || '';
		el.querySelectorAll( '[data-text]' ).forEach( function ( f ) {
			text += ' ' + f.value.toLowerCase();
		} );
		return text;
	}

	function find() {
		var q = search.value.trim().toLowerCase();
		var hits = {};

		panels.forEach( function ( panel ) {
			var page = panel.getAttribute( 'data-page' );
			var n = 0;

			panel.querySelectorAll( '.fnl-brick, .fnl-other' ).forEach( function ( box ) {
				var rows = Array.prototype.slice.call( box.querySelectorAll( '.fnl-text' ) );
				var rowHit = false;
				rows.forEach( function ( row ) {
					var hit = '' !== q && hay( row ).indexOf( q ) > -1;
					row.classList.toggle( 'is-hit', hit );
					rowHit = rowHit || hit;
				} );
				var own = box.classList.contains( 'fnl-brick' ) && hay( box ).indexOf( q ) > -1;
				var show = '' === q || own || rowHit;
				box.hidden = ! show;
				if ( '' !== q && rowHit ) {
					var d = box.matches( 'details' ) ? box : box.querySelector( '.fnl-texts' );
					if ( d ) {
						d.open = true;
					}
				}
				if ( show && '' !== q ) {
					n++;
				}
			} );

			hits[ page ] = n;
			var none = panel.querySelector( '.fnl-nomatch' );
			none.textContent = cfg.noMatch || '';
			none.hidden = '' === q || n > 0;
		} );

		tabs.forEach( function ( t ) {
			var badge = t.querySelector( '.fnl-tab__hit' );
			var n = hits[ t.getAttribute( 'data-tab' ) ] || 0;
			badge.hidden = '' === q || ! n;
			badge.textContent = n;
			badge.setAttribute( 'title', fmt( cfg.hits, n ) );
		} );

		if ( '' !== q && ! hits[ current ] ) {
			var first = pages.filter( function ( p ) { return hits[ p ]; } )[ 0 ];
			if ( first ) {
				setTab( first, false );
			}
		}
	}

	search.addEventListener( 'input', find );

	/* ---------- Liens vers une case requise ---------- */

	root.addEventListener( 'click', function ( e ) {
		var a = e.target.closest( '[data-jump]' );
		if ( ! a ) {
			return;
		}
		var target = document.getElementById( 'fnl-b-' + a.getAttribute( 'data-jump' ) );
		if ( ! target ) {
			return;
		}
		e.preventDefault();
		if ( search.value ) {
			search.value = '';
			find();
		}
		setTab( target.closest( '.fnl-panel' ).getAttribute( 'data-page' ), false );
		target.scrollIntoView( { block: 'center' } );
		var sw = target.querySelector( '.fnl-switch' );
		if ( sw ) {
			sw.focus();
		}
	} );

	/* ---------- Saisie et envoi ---------- */

	form.addEventListener( 'change', function () {
		refresh();
		markDirty();
	} );

	form.addEventListener( 'input', function ( e ) {
		if ( e.target.hasAttribute( 'data-text' ) ) {
			refresh();
			markDirty();
		}
	} );

	form.addEventListener( 'submit', function () {
		submitting = true;
		// N'envoyer que les textes modifiés : la requête reste courte, et un
		// texte jamais touché garde sa valeur (ou la proposition par défaut).
		form.querySelectorAll( '[data-text]' ).forEach( function ( f ) {
			if ( f.value === f.defaultValue ) {
				f.disabled = true;
			}
		} );
	} );

	window.addEventListener( 'pageshow', function () {
		form.querySelectorAll( '[data-text]' ).forEach( function ( f ) {
			f.disabled = false;
		} );
		submitting = false;
	} );

	window.addEventListener( 'beforeunload', function ( e ) {
		if ( ! submitting && isDirty() ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );

	setTab( ( window.location.hash || '' ).replace( '#', '' ), false );
	refresh();
	markDirty();
}() );
