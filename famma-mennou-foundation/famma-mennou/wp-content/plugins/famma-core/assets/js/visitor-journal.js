/**
 * Parcours visiteurs — collecte navigateur (famma-core).
 *
 * Identifiant visiteur aléatoire propre au site (cookie famma_vid), session
 * renouvelée après 30 minutes d'inactivité (cookie famma_sid). Les événements
 * sont mis en file et envoyés par lots : à la sortie de la page, et toutes les
 * 15 secondes s'il y en a en attente.
 *
 * Aucune valeur saisie n'est lue : pour les formulaires, seul le nom du champ
 * touché est envoyé.
 */
( function () {
	'use strict';

	var ctx = window.fammaJournal;
	if ( ! ctx || ! ctx.endpoint || ! window.JSON ) {
		return;
	}

	var SESSION_MS = 30 * 60 * 1000;
	var STATE_KEY = 'famma_journal';
	var started = Date.now();
	var queue = [];
	var secure = 'https:' === location.protocol ? ';Secure' : '';

	function uuid() {
		if ( window.crypto && crypto.randomUUID ) {
			return crypto.randomUUID();
		}
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, function ( c ) {
			var r = ( Math.random() * 16 ) | 0;
			return ( 'x' === c ? r : ( r & 0x3 ) | 0x8 ).toString( 16 );
		} );
	}

	function readCookie( name ) {
		var m = document.cookie.match( new RegExp( '(?:^|; )' + name + '=([^;]*)' ) );
		return m ? decodeURIComponent( m[ 1 ] ) : '';
	}

	function writeCookie( name, value, seconds ) {
		document.cookie = name + '=' + encodeURIComponent( value ) + ';path=/;max-age=' + seconds + ';SameSite=Lax' + secure;
	}

	function readState() {
		try {
			return JSON.parse( localStorage.getItem( STATE_KEY ) || '{}' ) || {};
		} catch ( e ) {
			return {};
		}
	}

	function saveState( state ) {
		try {
			localStorage.setItem( STATE_KEY, JSON.stringify( state ) );
		} catch ( e ) {}
	}

	/**
	 * Source de la session : UTM d'abord, puis fbclid, puis site référent.
	 */
	function detectSource() {
		var p = new URLSearchParams( location.search );
		if ( p.get( 'utm_source' ) ) {
			return {
				source: p.get( 'utm_source' ),
				medium: p.get( 'utm_medium' ) || '',
				campaign: p.get( 'utm_campaign' ) || '',
				content: p.get( 'utm_content' ) || '',
			};
		}
		if ( p.get( 'fbclid' ) ) {
			return { source: 'facebook', medium: 'fbclid', campaign: '', content: '' };
		}
		var ref = '';
		try {
			ref = document.referrer ? new URL( document.referrer ).hostname : '';
		} catch ( e ) {}
		if ( ref && ref !== location.hostname ) {
			return { source: ref.replace( /^www\./, '' ), medium: 'referral', campaign: '', content: '' };
		}
		return null;
	}

	var days = parseInt( ctx.days, 10 ) || 180;
	var visitor = readCookie( 'famma_vid' ) || uuid();
	writeCookie( 'famma_vid', visitor, days * 86400 );

	var state = readState();
	var fresh = detectSource();
	var session = readCookie( 'famma_sid' );
	// Une nouvelle campagne ouvre une nouvelle session, même dans les 30 minutes.
	if ( ! session || ! state.s || state.s !== session || Date.now() - ( state.t || 0 ) > SESSION_MS || ( fresh && fresh.source !== ( state.src || {} ).source ) ) {
		session = uuid();
		state = { s: session, src: fresh || { source: 'direct', medium: '', campaign: '', content: '' } };
	}

	function touch() {
		state.t = Date.now();
		saveState( state );
		writeCookie( 'famma_sid', session, SESSION_MS / 1000 );
	}
	touch();

	function track( name, extra ) {
		var e = { n: name, pt: ctx.pageType, p: location.pathname, pid: ctx.productId || 0, at: Date.now() };
		if ( extra ) {
			if ( extra.label !== undefined ) {
				e.l = String( extra.label ).slice( 0, 100 );
			}
			if ( extra.num !== undefined ) {
				e.x = Math.max( 0, Math.round( extra.num ) );
			}
		}
		queue.push( e );
		touch();
		if ( queue.length >= 20 ) {
			flush();
		}
	}

	function flush() {
		if ( ! queue.length ) {
			return;
		}
		var now = Date.now();
		var events = queue.splice( 0, 25 ).map( function ( e ) {
			e.a = now - e.at;
			delete e.at;
			return e;
		} );
		var body = JSON.stringify( { v: visitor, s: session, src: state.src, e: events } );
		// text/plain : requête « simple », jamais de pré-vol CORS.
		var blob = new Blob( [ body ], { type: 'text/plain' } );
		if ( ! ( navigator.sendBeacon && navigator.sendBeacon( ctx.endpoint, blob ) ) && window.fetch ) {
			fetch( ctx.endpoint, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin', headers: { 'Content-Type': 'text/plain' } } ).catch( function () {} );
		}
	}

	// ── Page vue, puis temps actif et profondeur de défilement à la sortie ──
	track( 'page_view' );

	var activeMs = 0;
	var visibleSince = 'visible' === document.visibilityState ? Date.now() : 0;
	var maxScroll = 0;
	var left = false;

	function scrollDepth() {
		var doc = document.documentElement;
		var total = doc.scrollHeight - window.innerHeight;
		var pct = total > 0 ? ( window.scrollY / total ) * 100 : 100;
		maxScroll = Math.max( maxScroll, Math.min( 100, pct ) );
	}
	window.addEventListener( 'scroll', scrollDepth, { passive: true } );

	function leave() {
		if ( visibleSince ) {
			activeMs += Date.now() - visibleSince;
			visibleSince = 0;
		}
		if ( ! left ) {
			left = true;
			scrollDepth();
			// Palier de 25 % : 0, 25, 50, 75, 100.
			track( 'page_leave', { label: String( Math.floor( maxScroll / 25 ) * 25 ), num: activeMs / 1000 } );
		}
		flush();
	}

	document.addEventListener( 'visibilitychange', function () {
		if ( 'hidden' === document.visibilityState ) {
			leave();
		} else {
			visibleSince = Date.now();
		}
	} );
	window.addEventListener( 'pagehide', leave );
	setInterval( flush, 15000 );

	// ── Formulaires de commande : express (fiche produit) et checkout ──
	var formSelector = '.famma-express__form, form.checkout';
	var startedForms = [];
	var seenFields = {};

	document.addEventListener( 'focusin', function ( event ) {
		var field = event.target;
		var form = field && field.closest ? field.closest( formSelector ) : null;
		// Champ piège anti-robots (famma_hp_*) : un humain ne l'atteint jamais.
		if ( ! form || ! field.name || 'hidden' === field.type || 0 === field.name.indexOf( 'famma_hp' ) ) {
			return;
		}
		if ( startedForms.indexOf( form ) < 0 ) {
			startedForms.push( form );
			track( 'form_start' );
		}
		if ( ! seenFields[ field.name ] ) {
			seenFields[ field.name ] = true;
			track( 'form_field', { label: field.name } );
		}
	} );

	document.addEventListener( 'submit', function ( event ) {
		if ( event.target && event.target.matches && event.target.matches( formSelector ) ) {
			track( 'form_submit', { num: ( Date.now() - started ) / 1000 } );
			flush();
		}
	}, true );

	// ── Clics : WhatsApp, téléphone, liens sortants ──
	document.addEventListener( 'click', function ( event ) {
		var link = event.target && event.target.closest ? event.target.closest( 'a[href]' ) : null;
		if ( ! link ) {
			return;
		}
		var href = link.getAttribute( 'href' ) || '';
		if ( /wa\.me|whatsapp\.com|^whatsapp:/i.test( href ) ) {
			track( 'click_whatsapp' );
		} else if ( /^tel:/i.test( href ) ) {
			track( 'click_phone' );
		} else if ( link.hostname && link.hostname !== location.hostname && /^https?:/i.test( link.protocol ) ) {
			track( 'click_outbound', { label: link.hostname } );
		} else {
			return;
		}
		flush();
	}, true );
}() );
