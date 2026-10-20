/**
 * Lot 5 — D7 / défaut T3.2 : InitiateCheckout une seule fois par panier et
 * par session de navigation, sur le checkout classique (/commande/).
 *
 * Un visiteur, un onglet :
 *  1. ajout du produit 16 (POST de la fiche) ;
 *  2. /commande/ : 1er chargement, rechargement, passage par /panier/ puis
 *     retour sur /commande/, retour arrière puis avancée ;
 *  3. ajout du produit 17 (le panier change) : /commande/, puis rechargement ;
 *  4. constat : nouvel onglet du même contexte (même panier, nouvelle
 *     session de navigation) sur /commande/.
 * Aucune commande n'est passée.
 *
 * Attendu : un InitiateCheckout au 1er chargement, aucun ensuite tant que
 * le panier ne change pas ; un nouveau quand le panier change. Référence
 * « avant » : T3.2 du lot 3 (un InitiateCheckout à chaque chargement).
 */
'use strict';

const { BASE, launch, cleanPage, fbqCalls, fbqSessionLog, writeProof, sleep, tracked } = require( './qa-lib' );

async function ic( page ) {
	return tracked( await fbqCalls( page ), 'InitiateCheckout' ).map( ( e ) => ( { content_ids: e.params && e.params.content_ids, value: e.params && e.params.value, num_items: e.params && e.params.num_items, eventID: e.options && e.options.eventID } ) );
}

async function addFrom( s, slug ) {
	await s.page.goto( BASE + '/produit/' + slug + '/', { waitUntil: 'load' } );
	await Promise.all( [ s.page.waitForNavigation( { waitUntil: 'load' } ), s.page.click( '.famma-express__cart' ) ] );
	await sleep( 800 );
}

( async () => {
	const browser = await launch();
	const s = await cleanPage( browser );
	const steps = [];
	const step = async ( label, fn ) => {
		await fn();
		await sleep( 1200 );
		steps.push( { etape: label, url: s.page.url().replace( BASE, '' ), initiateCheckout: await ic( s.page ) } );
	};

	await addFrom( s, 'qa-simple-sku' );
	await step( '/commande/ 1er chargement (panier : 16)', () => s.page.goto( BASE + '/commande/', { waitUntil: 'load' } ) );
	await step( 'rechargement', () => s.page.reload( { waitUntil: 'load' } ) );
	await step( '/panier/', () => s.page.goto( BASE + '/panier/', { waitUntil: 'load' } ) );
	await step( '/commande/ de nouveau', () => s.page.goto( BASE + '/commande/', { waitUntil: 'load' } ) );
	await step( 'retour arrière (/panier/)', () => s.page.goBack( { waitUntil: 'load' } ) );
	await step( 'avancée (/commande/)', () => s.page.goForward( { waitUntil: 'load' } ) );
	await addFrom( s, 'qa-simple-sans-sku' );
	await step( '/commande/ après changement du panier (16 + 17)', () => s.page.goto( BASE + '/commande/', { waitUntil: 'load' } ) );
	await step( 'rechargement', () => s.page.reload( { waitUntil: 'load' } ) );

	const sessionLog = ( await fbqSessionLog( s.page ) ).filter( ( e ) => Array.isArray( e.args ) && 'InitiateCheckout' === e.args[ 1 ] ).map( ( e ) => ( { page: e.page, content_ids: e.args[ 2 ] && e.args[ 2 ].content_ids, eventID: e.args[ 3 ] && e.args[ 3 ].eventID } ) );

	// Constat : nouvel onglet, même panier, nouvelle session de navigation.
	const tab = await s.context.newPage();
	await tab.setRequestInterception( true );
	tab.on( 'request', ( req ) => ( req.url().startsWith( BASE ) || req.url().startsWith( 'data:' ) ? req.continue() : req.abort( 'blockedbyclient' ) ) );
	await tab.goto( BASE + '/commande/', { waitUntil: 'load' } );
	await sleep( 1200 );
	const nouvelOnglet = await ic( tab );

	const out = {
		test: 'InitiateCheckout classique (D7, défaut T3.2)',
		date: new Date().toISOString(),
		etapes: steps,
		journalSessionInitiateCheckout: sessionLog,
		nouvelOnglet,
		verdict: {
			parEtape: steps.map( ( x ) => x.etape + ' = ' + x.initiateCheckout.length ),
			totalDansLOnglet: sessionLog.length,
			attenduDansLOnglet: 2,
			ok: 2 === sessionLog.length && 1 === steps[ 0 ].initiateCheckout.length && 1 === steps[ 6 ].initiateCheckout.length && steps.filter( ( x, i ) => 0 !== i && 6 !== i ).every( ( x ) => 0 === x.initiateCheckout.length ),
			nouvelOngletInitiateCheckout: nouvelOnglet.length,
		},
		horsClone: s.blocked,
	};
	writeProof( 'l5-ic-checkout-classique.json', out );
	console.log( JSON.stringify( out.verdict, null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
