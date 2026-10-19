/**
 * Lot 4 — T4.5 (H-N1), PROD, passif : simple visite en visiteur non connecté.
 *
 * Quatre chargements dans un même contexte vierge (comme un visiteur qui
 * navigue) : accueil, fiche ceinture-dorsale-pro, panier, boutique. Aucun
 * clic, aucun ajout, aucune saisie. Plafond strict : 6 chargements.
 *
 * Meta n'est PAS bloqué (consigne T4.5 : c'est la prod réelle, simple
 * visite). Pour n'écrire nulle part ailleurs, sont interrompus : toute
 * requête POST vers fammaminnou.tn (dont le relevé visiteurs
 * famma/v1/collect) et les hits GA4 (google-analytics.com/g/collect).
 *
 * Relevés par page : fbq.getState() réduit (ID des pixels, nombre
 * d'événements, NOMS des clés userData), file fbq.queue, requêtes /tr
 * observées (nom d'événement, pixel, présence d'un eid, noms des paramètres
 * seulement), noms des cookies, en-tête de cache LiteSpeed, taille transférée
 * de fbevents.js et de la config du pixel, et dans le HTML : nombre de
 * chargeurs fbq, d'appels init et de motifs ressemblant à un jeton Meta (doit
 * valoir 0 ; aucune valeur n'est recopiée).
 *
 * Mode « reseau » (5e chargement, sous le plafond de 6) : la fiche seule,
 * avec la liste de toutes les requêtes tierces (hôte + chemin, sans aucune
 * valeur de paramètre) et navigator.webdriver, pour savoir si fbevents.js
 * émet quelque chose depuis un navigateur automatisé. Preuve séparée
 * t4-5-prod-reseau.json ; le compteur de chargements y repart de 4.
 *
 * Usage : bash scripts/qa/browser/run.sh t4-5-prod.js --prod [reseau]
 */
'use strict';

const { launch, writeProof, sleep } = require( './qa-lib' );

const PROD = 'https://fammaminnou.tn';
const MODE = process.env.QA_ARG || 'pages';
const ALL_PAGES = [
	{ label: 'accueil', url: PROD + '/' },
	{ label: 'fiche ceinture-dorsale-pro', url: PROD + '/produit/ceinture-dorsale-pro/' },
	{ label: 'panier', url: PROD + '/panier/' },
	{ label: 'boutique', url: PROD + '/boutique/' },
];
const PAGES = 'reseau' === MODE ? ALL_PAGES.filter( ( p ) => p.label.startsWith( 'fiche' ) ) : ALL_PAGES;
const PROOF = 'reseau' === MODE ? 't4-5-prod-reseau.json' : 't4-5-prod.json';
const LOADS_BEFORE = 'reseau' === MODE ? 4 : 0;
const MAX_LOADS = 6;
const WAIT_AFTER_LOAD = 12000;

function trParams( url, postData ) {
	const u = new URL( url );
	const p = new URLSearchParams( u.search );
	if ( postData && ! p.get( 'ev' ) ) {
		try {
			new URLSearchParams( postData ).forEach( ( v, k ) => p.append( k, v ) );
		} catch ( e ) {
			// Corps multipart : seuls les paramètres de l'URL sont lus.
		}
	}
	return {
		ev: p.get( 'ev' ),
		id: p.get( 'id' ),
		eidPresent: !! p.get( 'eid' ),
		cles: Array.from( new Set( Array.from( p.keys() ).map( ( k ) => k.replace( /\[.*$/, '[]' ) ) ) ).sort(),
	};
}

( async () => {
	const browser = await launch( { prod: true } );
	const context = await browser.createBrowserContext();
	const page = await context.newPage();
	page.setDefaultTimeout( 90000 );
	await page.setViewport( { width: 412, height: 915, isMobile: true, hasTouch: true } );

	const state = { current: null, blocked: [], tr: [], sizes: {}, urls: {}, thirdParty: [] };
	await page.setRequestInterception( true );
	page.on( 'request', ( req ) => {
		const url = req.url();
		const host = ( () => {
			try {
				return new URL( url ).hostname;
			} catch ( e ) {
				return '';
			}
		} )();
		if ( host && 'fammaminnou.tn' !== host ) {
			state.thirdParty.push( { page: state.current, methode: req.method(), type: req.resourceType(), hote: host, chemin: new URL( url ).pathname } );
		}
		if ( /(^|\.)facebook\.com$/.test( host ) && /\/tr\/?/.test( new URL( url ).pathname ) ) {
			state.tr.push( Object.assign( { page: state.current, methode: req.method() }, trParams( url, req.postData() ) ) );
		}
		if ( 'fammaminnou.tn' === host && 'POST' === req.method() ) {
			state.blocked.push( { page: state.current, raison: 'POST vers la prod', chemin: new URL( url ).pathname } );
			req.abort( 'blockedbyclient' );
			return;
		}
		if ( /google-analytics\.com$/.test( host ) && /\/g\/collect/.test( url ) ) {
			state.blocked.push( { page: state.current, raison: 'hit GA4', hote: host } );
			req.abort( 'blockedbyclient' );
			return;
		}
		req.continue();
	} );
	const cdp = await page.createCDPSession();
	await cdp.send( 'Network.enable' );
	cdp.on( 'Network.requestWillBeSent', ( e ) => {
		state.urls[ e.requestId ] = e.request.url;
	} );
	cdp.on( 'Network.loadingFinished', ( e ) => {
		const url = state.urls[ e.requestId ] || '';
		if ( /connect\.facebook\.net\/(en_US\/fbevents\.js|signals\/config\/)/.test( url ) ) {
			state.sizes[ url.replace( /\?.*$/, '' ) ] = e.encodedDataLength;
		}
	} );

	const out = { test: 'T4.5', hypothese: 'H-N1', date: new Date().toISOString(), mode: 'visiteur non connecté, contexte vierge, Chromium 124 sans interface (Docker, réseau bridge)', chargements: LOADS_BEFORE, mode: MODE, pages: [] };
	for ( const p of PAGES ) {
		if ( out.chargements >= MAX_LOADS ) {
			break;
		}
		state.current = p.label;
		out.chargements++;
		const res = await page.goto( p.url, { waitUntil: 'load', timeout: 90000 } );
		const html = res ? await res.text().catch( () => '' ) : '';
		await sleep( WAIT_AFTER_LOAD );
		const info = await page.evaluate( () => {
			const f = window.fbq;
			const r = { fbqDefini: 'function' === typeof f, loaded: f ? !! f.loaded : null, version: f ? f.version || null : null, file: null, etat: null, erreurEtat: null };
			if ( f && f.queue ) {
				r.file = Array.prototype.map.call( f.queue, ( a ) => a[ 0 ] + ( a[ 1 ] ? ':' + a[ 1 ] : '' ) );
			}
			try {
				const st = f && 'function' === typeof f.getState ? f.getState() : null;
				if ( st ) {
					r.etat = {
						pixels: ( st.pixels || [] ).map( ( px ) => ( { id: px.id, eventCount: px.eventCount, userDataCles: px.userData ? Object.keys( px.userData ).sort() : [] } ) ),
						clesEtat: Object.keys( st ).sort(),
					};
				}
			} catch ( e ) {
				r.erreurEtat = String( e ).slice( 0, 200 );
			}
			r.webdriver = navigator.webdriver;
			r.uaHeadless = /HeadlessChrome/.test( navigator.userAgent );
			r.balisesFbevents = document.querySelectorAll( 'script[src*="fbevents.js"]' ).length;
			r.balisesConfig = document.querySelectorAll( 'script[src*="signals/config"]' ).length;
			// Chronologie réelle (ms depuis le début de la navigation), pour H-N6.
			const nav = performance.getEntriesByType( 'navigation' )[ 0 ] || {};
			r.chronologie = {
				dclMs: Math.round( nav.domContentLoadedEventEnd || 0 ),
				loadMs: Math.round( nav.loadEventEnd || 0 ),
				meta: performance.getEntriesByType( 'resource' )
					.filter( ( e ) => /connect\.facebook\.net|facebook\.com\/tr/.test( e.name ) )
					.map( ( e ) => ( { ressource: e.name.replace( /\?.*$/, '' ), debutMs: Math.round( e.startTime ), finMs: Math.round( e.responseEnd ), transferSize: e.transferSize, encodedBodySize: e.encodedBodySize } ) ),
			};
			return r;
		} );
		const jar = await cdp.send( 'Network.getAllCookies' );
		out.pages.push( {
			label: p.label,
			url: p.url,
			statut: res ? res.status() : null,
			urlFinale: page.url(),
			cacheLiteSpeed: res ? res.headers()[ 'x-litespeed-cache' ] || null : null,
			html: {
				chargeursFbq: ( html.match( /connect\.facebook\.net\/en_US\/fbevents\.js/g ) || [] ).length,
				appelsInit: ( html.match( /fbq\(\s*['"]init['"]/g ) || [] ).length,
				appelsPageView: ( html.match( /fbq\(\s*['"]track['"]\s*,\s*['"]PageView['"]/g ) || [] ).length,
				motifsJetonMeta: ( html.match( /EAA[A-Za-z0-9]{20,}/g ) || [] ).length,
				mentionAccessToken: ( html.match( /access_token/g ) || [] ).length,
			},
			fbq: info,
			requetesTr: state.tr.filter( ( t ) => t.page === p.label ),
			cookies: jar.cookies.map( ( c ) => c.name + ' (' + c.domain + ')' ).sort(),
			requetesTierces: state.thirdParty.filter( ( t ) => t.page === p.label ),
		} );
		writeProof( PROOF, out );
	}
	out.taillesTransferees = state.sizes;
	out.bloquesVolontairement = state.blocked;
	out.synthese = out.pages.map( ( p ) => ( {
		page: p.label,
		statut: p.statut,
		cache: p.cacheLiteSpeed,
		pixels: p.fbq.etat ? p.fbq.etat.pixels.map( ( x ) => x.id + ' : ' + x.eventCount + ' évén.' ) : null,
		fileEnAttente: p.fbq.file,
		evenementsTr: p.requetesTr.map( ( t ) => t.ev + ( t.eidPresent ? ' (eid)' : '' ) ),
		pixelsTr: Array.from( new Set( p.requetesTr.map( ( t ) => t.id ) ) ),
		jetonDansHtml: p.html.motifsJetonMeta,
		dclMs: p.fbq.chronologie ? p.fbq.chronologie.dclMs : null,
		loadMs: p.fbq.chronologie ? p.fbq.chronologie.loadMs : null,
		fbeventsDebutMs: p.fbq.chronologie ? ( p.fbq.chronologie.meta.find( ( m ) => /fbevents\.js$/.test( m.ressource ) ) || {} ).debutMs : null,
		premierTrMs: p.fbq.chronologie ? ( p.fbq.chronologie.meta.find( ( m ) => /facebook\.com\/tr/.test( m.ressource ) ) || {} ).debutMs : null,
	} ) );
	writeProof( PROOF, out );
	console.log( JSON.stringify( { chargements: out.chargements, synthese: out.synthese, tailles: out.taillesTransferees, bloques: out.bloquesVolontairement.length }, null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
