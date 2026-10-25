/**
 * Lot 3 — contrôle préalable : Chrome du conteneur joint-il le clone, et le
 * filtre bloque-t-il bien toute origine extérieure ?
 */
'use strict';

const { BASE, launch, cleanPage, fbqCalls, summarize, writeProof } = require( './qa-lib' );

( async () => {
	const browser = await launch();
	const s = await cleanPage( browser );
	const res = await s.page.goto( BASE + '/produit/qa-simple-sku/', { waitUntil: 'load' } );
	const calls = await fbqCalls( s.page );
	const info = await s.page.evaluate( () => ( {
		href: location.href,
		recorder: !! ( window.fbq && window.fbq.qaRecorder ),
		fbevents: document.querySelectorAll( 'script[src*="fbevents"]' ).length,
		ua: navigator.userAgent,
	} ) );
	const out = { status: res.status(), info, summary: summarize( calls ), calls, blocked: s.blocked, requestCount: s.requests.length };
	writeProof( '00-smoke.json', out );
	console.log( JSON.stringify( { status: out.status, info, summary: out.summary, blocked: s.blocked.length, requests: out.requestCount } ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
