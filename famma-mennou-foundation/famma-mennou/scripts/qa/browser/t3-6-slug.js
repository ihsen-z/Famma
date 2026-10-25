/**
 * Lot 3 — T3.6 (H-N7) : une pub pointant vers un ancien slug garde-t-elle
 * fbclid et utm_* après la redirection 301 de WordPress ?
 *
 * Produit dédié « QA-L3 Slug » (créé pour ce test), passé de
 * qa-l3-ancien-slug à qa-l3-nouveau-slug par « wp post update ». Le
 * navigateur arrive sur l'ancien slug avec ?fbclid=FAMMATEST456&utm_source=qa,
 * suit la redirection, puis on relève : chaîne de redirection, URL finale,
 * cookies _fbc et famma_utm, appels fbq de la page d'arrivée. Contrôle :
 * même visite sur le nouveau slug.
 */
'use strict';

const { BASE, launch, cleanPage, fbqCalls, summarize, cookies, writeProof, sleep } = require( './qa-lib' );

const QS = '?fbclid=FAMMATEST456&utm_source=qa';

async function arrive( browser, label, path ) {
	const s = await cleanPage( browser );
	const res = await s.page.goto( BASE + path + QS, { waitUntil: 'load' } );
	await sleep( 1000 );
	const chain = res.request().redirectChain().map( ( r ) => ( {
		url: r.url(),
		status: r.response() ? r.response().status() : null,
		location: r.response() ? r.response().headers().location || null : null,
	} ) );
	const jar = await cookies( s.page );
	const get = ( n ) => {
		const c = jar.find( ( x ) => x.name === n );
		return c ? decodeURIComponent( c.value ) : null;
	};
	const out = {
		label,
		urlDemandee: BASE + path + QS,
		chaineRedirection: chain,
		urlFinale: s.page.url(),
		statutFinal: res.status(),
		parametresConservesDansUrlFinale: s.page.url().includes( 'fbclid=FAMMATEST456' ) && s.page.url().includes( 'utm_source=qa' ),
		cookie_fbc: get( '_fbc' ),
		cookie_famma_utm: get( 'famma_utm' ),
		fbq: summarize( await fbqCalls( s.page ) ),
		blocked: s.blocked,
	};
	await s.context.close();
	return out;
}

( async () => {
	const browser = await launch();
	const ancien = await arrive( browser, 'ancien slug', '/produit/qa-l3-ancien-slug/' );
	const nouveau = await arrive( browser, 'nouveau slug (contrôle)', '/produit/qa-l3-nouveau-slug/' );
	const out = {
		test: 'T3.6',
		hypothese: 'H-N7',
		date: new Date().toISOString(),
		ancien,
		nouveau,
		verdict: {
			redirection301: ancien.chaineRedirection.some( ( r ) => 301 === r.status ),
			fbclidEtUtmPerdus: ! ancien.parametresConservesDansUrlFinale,
			fbcPoseApresRedirection: !! ancien.cookie_fbc,
			utmPoseApresRedirection: !! ancien.cookie_famma_utm,
			controleNouveauSlug_fbc: !! nouveau.cookie_fbc,
			controleNouveauSlug_utm: !! nouveau.cookie_famma_utm,
		},
	};
	writeProof( 't3-6-slug.json', out );
	console.log( JSON.stringify( { verdict: out.verdict, ancien: { chain: ancien.chaineRedirection, final: ancien.urlFinale, fbc: ancien.cookie_fbc, utm: ancien.cookie_famma_utm }, nouveau: { final: nouveau.urlFinale, fbc: nouveau.cookie_fbc, utm: nouveau.cookie_famma_utm } }, null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
