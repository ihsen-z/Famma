/**
 * Lot 3 — T3.1 (H-N1) : nombre de fbq('init') et de PageView par page.
 *
 * Visiteur anonyme, contexte vierge. Pages : accueil, fiche, boutique,
 * panier vide, puis (après un ajout au panier par le formulaire de la fiche)
 * panier et commande. Pour chaque page : appels fbq de la page (window.__qaFbq),
 * occurrences du chargeur et des appels init/PageView dans le HTML servi, et
 * requêtes bloquées hors du clone.
 *
 * Succès attendu : 1 init et 1 PageView par page.
 */
'use strict';

const { BASE, launch, cleanPage, fbqCalls, summarize, writeProof, sleep } = require( './qa-lib' );

/** Compte les motifs du pixel dans le HTML servi. */
function htmlCounts( html ) {
	const count = ( re ) => ( html.match( re ) || [] ).length;
	return {
		loader: count( /if\(f\.fbq\)return;/g ),
		initCalls: count( /fbq\('init',/g ),
		pageViewCalls: count( /fbq\('track','PageView'\)/g ),
		fammaPixelsHandle: count( /id="famma-pixels-js-after"/g ),
		recorder: count( /id="famma-qa-fbq-recorder"/g ),
	};
}

async function visit( s, label, url ) {
	const res = await s.page.goto( url, { waitUntil: 'load' } );
	await sleep( 1500 );
	return record( s, label, res );
}

async function record( s, label, res ) {
	let html = '';
	try {
		html = res ? await res.text() : '';
	} catch ( e ) {
		html = '';
	}
	const calls = await fbqCalls( s.page );
	return {
		label,
		url: s.page.url(),
		status: res ? res.status() : null,
		summary: summarize( calls ),
		calls,
		html: htmlCounts( html ),
	};
}

( async () => {
	const browser = await launch();
	const s = await cleanPage( browser );
	const pages = [];

	pages.push( await visit( s, 'accueil', BASE + '/' ) );
	pages.push( await visit( s, 'fiche QA Simple SKU (16)', BASE + '/produit/qa-simple-sku/' ) );
	pages.push( await visit( s, 'boutique', BASE + '/boutique/' ) );
	pages.push( await visit( s, 'panier vide', BASE + '/panier/' ) );

	// Ajout au panier par le formulaire classique de la fiche (POST, rechargement).
	await s.page.goto( BASE + '/produit/qa-simple-sku/', { waitUntil: 'load' } );
	const [ postRes ] = await Promise.all( [
		s.page.waitForNavigation( { waitUntil: 'load' } ),
		s.page.click( '.famma-express__cart' ), // bouton visible « Ajouter au panier » de la fiche (mobile)
	] );
	await sleep( 1500 );
	pages.push( await record( s, 'fiche après POST ajout au panier', postRes ) );

	pages.push( await visit( s, 'panier (1 article)', BASE + '/panier/' ) );
	pages.push( await visit( s, 'commande (1 article)', BASE + '/commande/' ) );

	// Champs du checkout classique (servira à T3.3).
	const checkoutFields = await s.page.evaluate( () => Array.from( document.querySelectorAll( 'form.checkout input, form.checkout select, form.checkout textarea' ) )
		.filter( ( el ) => 'hidden' !== el.type )
		.map( ( el ) => ( { name: el.name, type: el.type || el.tagName.toLowerCase(), required: el.required || !! el.closest( '.validate-required' ) } ) ) );

	const verdict = pages.map( ( p ) => ( {
		page: p.label,
		init: p.summary.init,
		pageView: p.summary.pageView,
		autres: p.summary.track.filter( ( t ) => 'PageView' !== t ),
		ok: 1 === p.summary.init && 1 === p.summary.pageView,
	} ) );

	const out = {
		test: 'T3.1',
		hypothese: 'H-N1',
		date: new Date().toISOString(),
		outil: 'Chromium 124 sans interface (zenika/alpine-chrome:with-puppeteer), contexte vierge, visiteur anonyme',
		verdict,
		pages,
		checkoutFields,
		blockedOutsideClone: s.blocked,
		consoleErrors: s.console,
	};
	writeProof( 't3-1-pageviews.json', out );
	console.log( JSON.stringify( { verdict, blocked: s.blocked.length, consoleErrors: s.console.length }, null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
