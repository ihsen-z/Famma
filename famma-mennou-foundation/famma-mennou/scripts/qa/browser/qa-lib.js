/**
 * Outils communs du lot 3 (plan pixel du 19/10/2026) : Chrome sans interface
 * piloté par Puppeteer dans l'image zenika/alpine-chrome:with-puppeteer.
 *
 * Le conteneur est relié au réseau du clone (famma-qa-pixel_default) et
 * Chrome résout « localhost:8980 » vers « wordpress:80 » : l'URL et l'en-tête
 * Host restent ceux du clone (http://localhost:8980), donc WordPress répond
 * comme pour un navigateur de l'hôte.
 *
 * Sur le clone, toute requête dont l'origine n'est pas http://localhost:8980
 * est interrompue (blockedbyclient) et notée : aucun appel réel vers Meta,
 * Google ou TikTok. Les appels fbq sont lus dans window.__qaFbq, posé par le
 * mu-plugin qa-pixel-guard.php (lot 0).
 *
 * Aucun secret : pixel factice 000000000000001, jeton factice côté serveur
 * seulement. Les identifiants de l'admin QA ne sont jamais imprimés.
 *
 * Usage : voir run.sh (même dossier).
 */
'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const puppeteer = require( 'puppeteer' );

const BASE = 'http://localhost:8980';
const OUT = process.env.QA_OUT || '/out';

/** Lance Chrome. `prod: true` : aucune réécriture DNS (visite de la prod). */
async function launch( opts = {} ) {
	const args = [ '--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--no-first-run' ];
	if ( ! opts.prod ) {
		args.push( '--host-resolver-rules=MAP localhost:8980 wordpress:80' );
	}
	return puppeteer.launch( {
		headless: true,
		executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/chromium-browser',
		args,
		protocolTimeout: 240000,
	} );
}

/**
 * Ouvre un onglet dans un contexte vierge (cookies et stockage isolés).
 * Toute requête hors du clone est interrompue et enregistrée dans `blocked`.
 * `onRequest(req)` peut répondre lui-même (retour true) avant le filtre.
 */
async function cleanPage( browser, opts = {} ) {
	const context = await browser.createBrowserContext();
	const page = await context.newPage();
	page.setDefaultTimeout( opts.timeout || 60000 );
	await page.setViewport( { width: 412, height: 915, isMobile: true, hasTouch: true } );
	const state = { context, page, blocked: [], requests: [], console: [] };
	await page.setRequestInterception( true );
	page.on( 'request', ( req ) => {
		const url = req.url();
		state.requests.push( { t: Date.now(), method: req.method(), url: url.slice( 0, 300 ) } );
		if ( opts.onRequest && opts.onRequest( req ) ) {
			return;
		}
		if ( url.startsWith( BASE + '/' ) || url === BASE || url.startsWith( 'data:' ) || url.startsWith( 'blob:' ) ) {
			req.continue();
			return;
		}
		state.blocked.push( url.slice( 0, 300 ) );
		req.abort( 'blockedbyclient' );
	} );
	page.on( 'console', ( msg ) => {
		if ( 'error' === msg.type() ) {
			state.console.push( msg.text().slice( 0, 300 ) );
		}
	} );
	return state;
}

/** Appels fbq de la page courante (copie JSON de window.__qaFbq). */
async function fbqCalls( page ) {
	return page.evaluate( () => JSON.parse( JSON.stringify( window.__qaFbq || [] ) ) );
}

/** Journal fbq de la session de l'onglet (sessionStorage « __qaFbqLog »). */
async function fbqSessionLog( page ) {
	return page.evaluate( () => {
		try {
			return JSON.parse( window.sessionStorage.getItem( '__qaFbqLog' ) || '[]' );
		} catch ( e ) {
			return [];
		}
	} );
}

/** Résumé : nombre d'init, de PageView et liste des événements « track ». */
function summarize( calls ) {
	const out = { init: 0, pageView: 0, track: [] };
	for ( const c of calls ) {
		if ( 'init' === c[ 0 ] ) {
			out.init++;
		} else if ( 'track' === c[ 0 ] && 'PageView' === c[ 1 ] ) {
			out.pageView++;
		}
		if ( 'track' === c[ 0 ] || 'trackCustom' === c[ 0 ] ) {
			out.track.push( c[ 1 ] );
		}
	}
	return out;
}

/** Noms et valeurs des cookies du contexte (clone uniquement). */
async function cookies( page ) {
	const client = await page.createCDPSession();
	const res = await client.send( 'Network.getAllCookies' );
	await client.detach();
	return res.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: c.domain, path: c.path, expires: c.expires, secure: c.secure, sameSite: c.sameSite } ) );
}

/** Écrit une preuve JSON dans le dossier du lot. */
function writeProof( name, data ) {
	const file = path.join( OUT, name );
	fs.writeFileSync( file, JSON.stringify( data, null, 2 ) + '\n' );
	return file;
}

/** Pause. */
function sleep( ms ) {
	return new Promise( ( r ) => setTimeout( r, ms ) );
}

/** Attend qu'un appel fbq satisfasse `test` (ou expiration) ; renvoie les appels. */
async function waitForFbq( page, test, timeout = 15000 ) {
	const end = Date.now() + timeout;
	let calls = [];
	while ( Date.now() < end ) {
		calls = await fbqCalls( page );
		if ( calls.some( test ) ) {
			return calls;
		}
		await sleep( 250 );
	}
	return calls;
}

/** Événements « track » d'un nom donné, avec paramètres et eventID. */
function tracked( calls, name ) {
	return calls.filter( ( c ) => 'track' === c[ 0 ] && name === c[ 1 ] ).map( ( c ) => ( { params: c[ 2 ] || null, options: c[ 3 ] || null } ) );
}

/**
 * Remplit le formulaire express de la fiche courante (données fictives
 * préfixées QA-L3), comme un visiteur : saisie au clavier, gouvernorat choisi
 * dans la liste de recherche (frappe du libellé puis Entrée). Renvoie la
 * valeur finale du <select> billing_state.
 */
async function fillExpress( page, data ) {
	await page.click( '.famma-express__form [name="billing_first_name"]' );
	await page.type( '.famma-express__form [name="billing_first_name"]', data.firstName );
	await page.type( '.famma-express__form [name="billing_phone"]', data.phone );
	const combo = '.famma-express__form input[role="combobox"]';
	await page.click( combo );
	await page.type( combo, data.stateLabel );
	await page.keyboard.press( 'Enter' );
	if ( data.size ) {
		await page.evaluate( ( size ) => {
			const r = document.querySelector( '.famma-express__form input[name="famma_size"][value="' + size + '"]' );
			if ( r ) {
				r.click();
			}
		}, data.size );
	}
	return page.evaluate( () => document.querySelector( '.famma-express__form select[name="billing_state"]' ).value );
}

/**
 * Identifiants de l'admin QA (fichier monté par « run.sh --admin » sur
 * /secrets/qa-admin.txt). Jamais imprimés ni écrits dans une preuve.
 */
function adminCredentials() {
	const raw = fs.readFileSync( '/secrets/qa-admin.txt', 'utf8' );
	const get = ( k ) => {
		const m = raw.match( new RegExp( '^' + k + '=(.*)$', 'm' ) );
		return m ? m[ 1 ].trim() : '';
	};
	return { user: get( 'user' ), pass: get( 'password' ) };
}

/**
 * Connexion de l'admin QA par wp-login.php du clone (lot 5). Renvoie un
 * constat sans aucun secret : saisie complète, message d'erreur, URL après.
 */
async function adminLogin( s ) {
	const cred = adminCredentials();
	if ( ! cred.user || ! cred.pass ) {
		throw new Error( 'Identifiants QA absents de /secrets/qa-admin.txt' );
	}
	await s.page.goto( BASE + '/wp-login.php', { waitUntil: 'load' } );
	// wp-login.php sélectionne le champ identifiant 200 ms après le chargement.
	await sleep( 800 );
	await s.page.$eval( '#user_login', ( el, v ) => {
		el.value = v;
	}, cred.user );
	await s.page.$eval( '#user_pass', ( el, v ) => {
		el.value = v;
	}, cred.pass );
	const typedOk = await s.page.evaluate( ( u, n ) => document.getElementById( 'user_login' ).value === u && document.getElementById( 'user_pass' ).value.length === n, cred.user, cred.pass.length );
	await Promise.all( [ s.page.waitForNavigation( { waitUntil: 'load' } ), s.page.click( '#wp-submit' ) ] );
	const jar = await cookies( s.page );
	return {
		saisieComplete: typedOk,
		messageErreur: ( await s.page.evaluate( () => ( document.getElementById( 'login_error' ) || {} ).textContent || '' ) ).trim().slice( 0, 200 ),
		urlApres: s.page.url(),
		connecte: jar.some( ( c ) => /^wordpress_logged_in_/.test( c.name ) ),
	};
}

/** En-têtes de réponse utiles aux preuves de cache (aucun cookie recopié). */
function cacheHeaders( res ) {
	if ( ! res ) {
		return null;
	}
	const h = res.headers();
	const out = {};
	for ( const k of Object.keys( h ) ) {
		if ( /^(cache-control|expires|pragma|vary|location|x-redirect-by|x-qa-l5n-|x-litespeed)/i.test( k ) ) {
			out[ k ] = h[ k ];
		}
	}
	return out;
}

module.exports = { BASE, OUT, launch, cleanPage, fbqCalls, fbqSessionLog, summarize, cookies, writeProof, sleep, waitForFbq, tracked, fillExpress, adminCredentials, adminLogin, cacheHeaders };
