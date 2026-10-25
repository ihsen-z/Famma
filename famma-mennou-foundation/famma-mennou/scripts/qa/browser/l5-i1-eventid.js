/**
 * Lot 5 — I1 (S3-a) : deux visiteurs sur la même fiche servie « en cache ».
 *
 * Le clone n'a pas LiteSpeed. Le cache est donc simulé dans le navigateur :
 * - visiteur A : contexte vierge, fiche chargée normalement ; le HTML exact
 *   reçu est gardé ;
 * - visiteur B : autre contexte vierge ; la requête de document de la même
 *   fiche reçoit, octet pour octet, le HTML de A (ce que fait un cache de page
 *   en « hit ») ;
 * - visiteur C : contexte vierge, fiche servie par WordPress (témoin).
 * On relève les eventID de PageView et de ViewContent de chacun, et l'on
 * vérifie que le HTML ne contient aucun eventID littéral (tirage navigateur).
 *
 * Succès : eventID ViewContent différents entre A et B (et C) ; chaque
 * PageView porte un eventID, différent d'un visiteur à l'autre.
 * Référence « avant » : T4.1 (prod 0.3.1) — même eventID ViewContent pour
 * deux chargements en cache.
 */
'use strict';

const { BASE, launch, cleanPage, fbqCalls, writeProof, sleep, tracked } = require( './qa-lib' );

const FICHE = BASE + '/produit/qa-simple-sku/';

function ids( calls ) {
	return {
		pageView: tracked( calls, 'PageView' ).map( ( e ) => ( e.options && e.options.eventID ) || null ),
		viewContent: tracked( calls, 'ViewContent' ).map( ( e ) => ( e.options && e.options.eventID ) || null ),
		viewContentParams: tracked( calls, 'ViewContent' ).map( ( e ) => e.params ),
	};
}

( async () => {
	const browser = await launch();

	// Visiteur A.
	const a = await cleanPage( browser );
	const resA = await a.page.goto( FICHE, { waitUntil: 'load' } );
	const htmlA = await resA.text();
	await sleep( 1000 );
	const callsA = await fbqCalls( a.page );
	await a.context.close();

	// Visiteur B : même HTML, servi comme par un cache de page.
	let servedFromCopy = 0;
	const b = await cleanPage( browser, {
		onRequest: ( req ) => {
			if ( req.isNavigationRequest() && req.url() === FICHE ) {
				servedFromCopy++;
				req.respond( { status: 200, contentType: 'text/html; charset=UTF-8', body: htmlA } );
				return true;
			}
			return false;
		},
	} );
	const resB = await b.page.goto( FICHE, { waitUntil: 'load' } );
	const htmlB = await resB.text();
	await sleep( 1000 );
	const callsB = await fbqCalls( b.page );
	await b.context.close();

	// Visiteur C : témoin servi par WordPress.
	const c = await cleanPage( browser );
	const resC = await c.page.goto( FICHE, { waitUntil: 'load' } );
	const htmlC = await resC.text();
	await sleep( 1000 );
	const callsC = await fbqCalls( c.page );
	await c.context.close();

	const A = ids( callsA );
	const B = ids( callsB );
	const C = ids( callsC );
	const vc = [ ...A.viewContent, ...B.viewContent, ...C.viewContent ];
	const pv = [ ...A.pageView, ...B.pageView, ...C.pageView ];
	const literalEventId = ( html ) => ( html.match( /eventID:\s*["'][^"']+["']/g ) || [] );
	const out = {
		test: 'I1',
		decision: 'S3-a',
		date: new Date().toISOString(),
		montage: 'cache simulé : le document de B est le HTML exact reçu par A (réponse interceptée) ; C est servi par WordPress',
		documentBServiDepuisLaCopie: servedFromCopy,
		htmlBIdentiqueA: htmlA === htmlB,
		tailleHtml: htmlA.length,
		eventIdLitteralDansHtml: { A: literalEventId( htmlA ), C: literalEventId( htmlC ) },
		appelsPixelDansHtml: ( htmlA.match( /fbq\("track","ViewContent"[^;]*;|fbq\('track','PageView'[^;]*;/g ) || [] ),
		visiteurA: A,
		visiteurB: B,
		visiteurC: C,
		verdict: {
			viewContentParVisiteur: [ A.viewContent.length, B.viewContent.length, C.viewContent.length ],
			viewContentEventIDsDistincts: new Set( vc.filter( Boolean ) ).size,
			viewContentAttendus: vc.length,
			pageViewAvecEventID: pv.filter( Boolean ).length + ' / ' + pv.length,
			pageViewEventIDsDistincts: new Set( pv.filter( Boolean ) ).size,
			ok: 3 === vc.length && 3 === new Set( vc.filter( Boolean ) ).size && 3 === pv.filter( Boolean ).length && 3 === new Set( pv ).size,
		},
		horsClone: { A: a.blocked, B: b.blocked, C: c.blocked },
	};
	writeProof( 'l5-i1-eventid.json', out );
	console.log( JSON.stringify( { verdict: out.verdict, htmlBIdentiqueA: out.htmlBIdentiqueA, servie: servedFromCopy, A: A.viewContent, B: B.viewContent, C: C.viewContent, pv, litteral: out.eventIdLitteralDansHtml }, null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
