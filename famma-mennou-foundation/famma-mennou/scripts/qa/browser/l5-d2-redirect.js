/**
 * Lot 5 — D2 : redirections du front (ancien slug, URL héritée, canonique)
 * dans un vrai navigateur.
 *
 * Pour chaque adresse, deux visiteurs vierges arrivent avec des paramètres
 * DIFFÉRENTS (fbclid et utm_*). On relève la chaîne de redirection (statut,
 * Location, en-têtes de cache, sonde qa-l5n-cache-probe.php), l'URL finale,
 * les cookies _fbc et famma_utm posés après la redirection, et les appels fbq
 * de la page d'arrivée.
 *
 * Attendu : la chaîne de requête de CHAQUE visiteur survit (deux Location
 * différentes), la redirection porte des en-têtes sans cache et l'action
 * LiteSpeed « no-cache », _fbc reprend le fbclid du visiteur.
 * Référence « avant » : T3.6 (lot 3, paramètres perdus dans la 301).
 *
 * L'URL héritée (/shop/, /cart/) suppose le mu-plugin famma-legacy-urls.php
 * présent dans le clone (copié temporairement par le testeur).
 */
'use strict';

const { BASE, launch, cleanPage, fbqCalls, summarize, cookies, writeProof, sleep, cacheHeaders } = require( './qa-lib' );

const CASES = [
	{ label: 'ancien slug', path: '/produit/qa-l3-ancien-slug/' },
	{ label: 'URL héritée /shop/', path: '/shop/' },
	{ label: 'URL héritée /cart/', path: '/cart/' },
	{ label: 'canonique (sans barre finale)', path: '/produit/qa-simple-sku' },
];
const VISITORS = [
	{ fbclid: 'FAMMATEST-L5N-D2-V1', qs: 'utm_source=qa-l5n-v1&utm_medium=paid&utm_campaign=olympe-v1' },
	{ fbclid: 'FAMMATEST-L5N-D2-V2', qs: 'utm_source=qa-l5n-v2&utm_content=pub-v2' },
];

async function arrive( browser, c, v ) {
	const s = await cleanPage( browser );
	const asked = BASE + c.path + '?fbclid=' + v.fbclid + '&' + v.qs;
	const res = await s.page.goto( asked, { waitUntil: 'load' } );
	await sleep( 1000 );
	const chain = res.request().redirectChain().map( ( r ) => ( {
		url: r.url(),
		status: r.response() ? r.response().status() : null,
		entetes: cacheHeaders( r.response() ),
	} ) );
	const jar = await cookies( s.page );
	const get = ( n ) => {
		const k = jar.find( ( x ) => x.name === n );
		return k ? decodeURIComponent( k.value ) : null;
	};
	const final = s.page.url();
	const finalParams = new URL( final ).searchParams;
	const askedParams = new URL( asked ).searchParams;
	const kept = [ ...askedParams.keys() ].every( ( k ) => finalParams.get( k ) === askedParams.get( k ) );
	const out = {
		visiteur: v.fbclid,
		urlDemandee: asked,
		chaineRedirection: chain,
		urlFinale: final,
		statutFinal: res.status(),
		parametresDuVisiteurConserves: kept,
		cookie_fbc: get( '_fbc' ),
		fbcReprendLeFbclid: !! ( get( '_fbc' ) || '' ).match( new RegExp( '^fb\\.1\\.\\d{13}\\.' + v.fbclid + '$' ) ),
		cookie_famma_utm: get( 'famma_utm' ),
		fbq: summarize( await fbqCalls( s.page ) ),
		horsClone: s.blocked,
	};
	await s.context.close();
	return out;
}

( async () => {
	const browser = await launch();
	const out = { test: 'D2 (+ T3.6 rejoué)', date: new Date().toISOString(), cas: [] };
	for ( const c of CASES ) {
		const visits = [];
		for ( const v of VISITORS ) {
			visits.push( await arrive( browser, c, v ) );
		}
		const locations = visits.map( ( x ) => ( x.chaineRedirection[ 0 ] && x.chaineRedirection[ 0 ].entetes ? x.chaineRedirection[ 0 ].entetes.location : null ) );
		out.cas.push( {
			cas: c.label,
			visites: visits,
			verdict: {
				redirige: visits.every( ( x ) => x.chaineRedirection.length > 0 && 301 === x.chaineRedirection[ 0 ].status ),
				deuxLocationDifferentes: 2 === new Set( locations.filter( Boolean ) ).size,
				parametresConserves: visits.every( ( x ) => x.parametresDuVisiteurConserves ),
				enTetesSansCache: visits.every( ( x ) => x.chaineRedirection.length > 0 && /no-store/.test( ( x.chaineRedirection[ 0 ].entetes || {} )[ 'cache-control' ] || '' ) ),
				actionLiteSpeedNoCache: visits.every( ( x ) => x.chaineRedirection.length > 0 && !! ( x.chaineRedirection[ 0 ].entetes || {} )[ 'x-qa-l5n-lscache-nocache' ] ),
				fbcDuVisiteur: visits.every( ( x ) => x.fbcReprendLeFbclid ),
				utmPose: visits.every( ( x ) => !! x.cookie_famma_utm ),
				pixelSurLaPageFinale: visits.map( ( x ) => x.fbq.init + ' init, ' + x.fbq.track.join( '+' ) ),
			},
		} );
		writeProof( 'l5-d2-redirections-navigateur.json', out );
	}
	console.log( JSON.stringify( out.cas.map( ( c ) => ( { cas: c.cas, verdict: c.verdict, locations: c.visites.map( ( v ) => v.chaineRedirection.map( ( r ) => r.status + ' ' + ( r.entetes || {} ).location ) ), fbc: c.visites.map( ( v ) => v.cookie_fbc ), utm: c.visites.map( ( v ) => v.cookie_famma_utm ) } ) ), null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
