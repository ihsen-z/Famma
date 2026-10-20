/**
 * Lot 5 — I2 et I3 (S3-a) : OrderPlaced navigateur, interrupteur ACTIVÉ.
 *
 * L'option famma_meta_orderplaced_browser est mise à 1 par WP-CLI AVANT ce
 * script, puis supprimée juste après (voir le journal du testeur). Trois
 * vraies commandes QA-L5N sont passées dans le clone :
 *
 *  1. express, enregistreur fbq tel quel (pas de `fbq.callMethod`, comme une
 *     bibliothèque Meta pas encore chargée) : l'express n'émet rien, la page
 *     de confirmation émet OrderPlaced ;
 *  2. express, bibliothèque « chargée » simulée (`fbq.callMethod` posé sur
 *     l'enregistreur juste avant le clic) : l'express émet OrderPlaced avant
 *     la redirection, la page de confirmation ne doit PAS le réémettre ;
 *  3. checkout classique (panier puis /commande/) : la confirmation émet.
 *
 * I3, sur chacune : rechargement de la confirmation, puis retour arrière et
 * avancée : aucun second OrderPlaced dans l'onglet (journal sessionStorage
 * « __qaFbqLog », qui survit aux changements de page). En plus, constat :
 * la même URL de confirmation ouverte dans un NOUVEL onglet (nouvelle
 * session de navigation).
 *
 * Les eventID relevés ici sont comparés ensuite à ceux des requêtes CAPI
 * interceptées (qa-l5n-orders.php send + journal qa-http-log.jsonl).
 *
 * Usage : bash scripts/qa/browser/run.sh l5-i2-orderplaced.js [classique]
 * « classique » : rejoue seulement le parcours 3 (preuve séparée), par
 * exemple après un refus du garde-fou « 5 commandes par IP et par heure ».
 */
'use strict';

const { BASE, launch, cleanPage, fbqCalls, fbqSessionLog, writeProof, sleep, fillExpress } = require( './qa-lib' );

const PREFIX = process.env.QA_PREFIX || 'QA-L5N';
const ONLY_CLASSIC = 'classique' === process.env.QA_ARG;
const PROOF = ONLY_CLASSIC ? 'l5-i2-i3-orderplaced-classique.json' : 'l5-i2-i3-orderplaced.json';

function orderIdFrom( url ) {
	const m = url.match( /order-received\/(\d+)/ );
	return m ? Number( m[ 1 ] ) : null;
}

function orderPlaced( list ) {
	return list.filter( ( c ) => ( 'trackCustom' === c[ 0 ] || 'track' === c[ 0 ] ) && 'OrderPlaced' === c[ 1 ] ).map( ( c ) => ( { params: c[ 2 ] || null, options: c[ 3 ] || null } ) );
}

function logOrderPlaced( log ) {
	return log.filter( ( e ) => Array.isArray( e.args ) && 'OrderPlaced' === e.args[ 1 ] ).map( ( e ) => ( { page: e.page.replace( /key=[^&]+/, 'key=<masquée>' ), eventID: e.args[ 3 ] && e.args[ 3 ].eventID, params: e.args[ 2 ] } ) );
}

async function retype( page, sel, value ) {
	await page.$eval( sel, ( el ) => {
		el.value = '';
	} );
	await page.type( sel, value );
}

/** Type de la dernière navigation du document (reload, back_forward…). */
function navType( page ) {
	return page.evaluate( () => {
		const n = performance.getEntriesByType( 'navigation' )[ 0 ];
		return n ? n.type : null;
	} ).catch( () => null );
}

/**
 * I3 : rechargement, retour arrière, avancée, sur l'onglet de la confirmation.
 * Le décompte qui fait foi est celui du journal de session de l'onglet : une
 * page restaurée depuis le cache arrière-avant garde ses anciens appels dans
 * window.__qaFbq sans rien réémettre.
 */
async function i3( s ) {
	const steps = [];
	const confUrl = s.page.url();
	await s.page.reload( { waitUntil: 'load' } );
	await sleep( 1200 );
	steps.push( { etape: 'rechargement', navigation: await navType( s.page ), orderPlacedDansLaPage: orderPlaced( await fbqCalls( s.page ) ).length, journalSession: logOrderPlaced( await fbqSessionLog( s.page ) ).length } );
	await s.page.goBack( { waitUntil: 'load' } ).catch( () => null );
	await sleep( 1000 );
	steps.push( { etape: 'retour arrière', url: s.page.url().replace( /key=[^&]+/, 'key=<masquée>' ), navigation: await navType( s.page ), orderPlacedDansLaPage: orderPlaced( await fbqCalls( s.page ) ).length, journalSession: logOrderPlaced( await fbqSessionLog( s.page ) ).length } );
	await s.page.goForward( { waitUntil: 'load' } ).catch( () => null );
	await sleep( 1200 );
	steps.push( { etape: 'avancée', url: s.page.url().replace( /key=[^&]+/, 'key=<masquée>' ), revenuSurConfirmation: s.page.url() === confUrl, navigation: await navType( s.page ), orderPlacedDansLaPage: orderPlaced( await fbqCalls( s.page ) ).length, journalSession: logOrderPlaced( await fbqSessionLog( s.page ) ).length } );
	const log = logOrderPlaced( await fbqSessionLog( s.page ) );
	return { etapes: steps, orderPlacedDansLaSessionDeLOnglet: log.length, journal: log };
}

async function expressOrder( browser, label, simulateLoadedLibrary ) {
	const s = await cleanPage( browser, { timeout: 90000 } );
	const res = { parcours: label };
	await s.page.goto( BASE + '/produit/qa-simple-sku/', { waitUntil: 'load' } );
	await fillExpress( s.page, { firstName: PREFIX + ' I2 ' + label, phone: '29000552', stateLabel: 'Sousse' } );
	if ( simulateLoadedLibrary ) {
		res.callMethodPose = await s.page.evaluate( () => {
			window.fbq.callMethod = function () {};
			return 'function' === typeof window.fbq.callMethod;
		} );
	}
	const ajax = [];
	s.page.on( 'response', async ( r ) => {
		if ( r.url().includes( 'wc-ajax=famma_express_checkout' ) ) {
			try {
				const j = await r.json();
				ajax.push( { result: j.result, famma_order_placed: j.famma_order_placed || null } );
			} catch ( e ) {
				ajax.push( { error: String( e ).slice( 0, 120 ) } );
			}
		}
	} );
	await Promise.all( [ s.page.waitForNavigation( { waitUntil: 'load', timeout: 90000 } ), s.page.click( '.famma-express__submit' ) ] );
	await sleep( 1500 );
	res.orderId = orderIdFrom( s.page.url() );
	res.reponseExpress = ajax;
	res.confirmation = { orderPlaced: orderPlaced( await fbqCalls( s.page ) ), init: ( await fbqCalls( s.page ) ).filter( ( c ) => 'init' === c[ 0 ] ).length };
	res.journalAvantI3 = logOrderPlaced( await fbqSessionLog( s.page ) );
	const confUrl = s.page.url();
	res.i3 = await i3( s );
	res.horsClone = s.blocked;
	await s.context.close();
	res.confUrl = confUrl;
	return res;
}

async function classicOrder( browser ) {
	const s = await cleanPage( browser, { timeout: 90000 } );
	const res = { parcours: 'checkout classique' };
	await s.page.goto( BASE + '/produit/qa-simple-sans-sku/', { waitUntil: 'load' } );
	await Promise.all( [ s.page.waitForNavigation( { waitUntil: 'load' } ), s.page.click( '.famma-express__cart' ) ] );
	await s.page.goto( BASE + '/commande/', { waitUntil: 'load' } );
	await sleep( 1500 );
	await retype( s.page, '#billing_first_name', PREFIX + ' I2 Classique' );
	await retype( s.page, '#billing_phone', ONLY_CLASSIC ? '29000555' : '29000553' );
	await retype( s.page, '#billing_address_1', PREFIX + ' adresse fictive 2' );
	if ( await s.page.$( '#billing_city' ) ) {
		await retype( s.page, '#billing_city', 'Sousse' );
	}
	await s.page.select( '#billing_state', 'TN-51' );
	await sleep( 2500 );
	await s.page.waitForFunction( () => ! document.querySelector( '.blockUI' ), { timeout: 30000 } );
	await s.page.click( '#place_order' );
	const end = Date.now() + 90000;
	while ( Date.now() < end ) {
		await sleep( 500 );
		const st = await s.page.evaluate( () => ( { url: location.href, errors: Array.from( document.querySelectorAll( '.woocommerce-error li, .woocommerce-NoticeGroup-checkout li' ) ).map( ( e ) => e.textContent.trim() ) } ) ).catch( () => ( { url: '', errors: [] } ) );
		if ( /order-received/.test( st.url ) || st.errors.length ) {
			res.erreurs = st.errors;
			break;
		}
	}
	try {
		await s.page.waitForFunction( () => 'complete' === document.readyState, { timeout: 30000 } );
	} catch ( e ) {
		// lecture de ce qui est là.
	}
	await sleep( 1500 );
	res.orderId = orderIdFrom( s.page.url() );
	const calls = await fbqCalls( s.page );
	res.confirmation = { orderPlaced: orderPlaced( calls ), init: calls.filter( ( c ) => 'init' === c[ 0 ] ).length };
	res.journalAvantI3 = logOrderPlaced( await fbqSessionLog( s.page ) );
	const confUrl = s.page.url();
	res.i3 = await i3( s );

	// Constat : même confirmation dans un NOUVEL onglet du même contexte
	// (cookies partagés, sessionStorage neuf).
	const tab = await s.context.newPage();
	await tab.setRequestInterception( true );
	tab.on( 'request', ( req ) => ( req.url().startsWith( BASE ) || req.url().startsWith( 'data:' ) ? req.continue() : req.abort( 'blockedbyclient' ) ) );
	await tab.goto( confUrl, { waitUntil: 'load' } );
	await sleep( 1200 );
	res.nouvelOnglet = { orderPlaced: orderPlaced( await fbqCalls( tab ) ) };
	res.horsClone = s.blocked;
	await s.context.close();
	return res;
}

( async () => {
	const browser = await launch();
	const out = { test: 'I2+I3', decision: 'S3-a', date: new Date().toISOString(), interrupteur: 'famma_meta_orderplaced_browser = 1 (posé par WP-CLI avant le script)', commandes: [] };
	if ( ! ONLY_CLASSIC ) {
		out.commandes.push( await expressOrder( browser, 'express (bibliothèque non chargée)', false ) );
		writeProof( PROOF, out );
		out.commandes.push( await expressOrder( browser, 'express (bibliothèque chargée simulée)', true ) );
		writeProof( PROOF, out );
	}
	out.commandes.push( await classicOrder( browser ) );
	out.verdict = out.commandes.map( ( c ) => {
		const ids = c.i3.journal.map( ( j ) => j.eventID );
		return {
			parcours: c.parcours,
			orderId: c.orderId,
			eventIDsNavigateur: ids,
			eventIDAttendu: c.orderId ? 'famma_orderplaced_' + c.orderId : null,
			memeEventID: ids.length > 0 && ids.every( ( id ) => id === 'famma_orderplaced_' + c.orderId ),
			unSeulOrderPlacedDansLOnglet: 1 === c.i3.orderPlacedDansLaSessionDeLOnglet,
			emisDepuis: c.i3.journal.map( ( j ) => j.page.split( '?' )[ 0 ] ),
			rechargementEtHistorique: c.i3.etapes.map( ( e ) => e.etape + ' (' + e.navigation + ') page=' + e.orderPlacedDansLaPage + ' session=' + e.journalSession ),
			nouvelOnglet: c.nouvelOnglet ? c.nouvelOnglet.orderPlaced.map( ( e ) => e.options && e.options.eventID ) : null,
		};
	} );
	for ( const c of out.commandes ) {
		delete c.confUrl;
	}
	writeProof( PROOF, out );
	const classic = out.commandes.find( ( c ) => 'checkout classique' === c.parcours );
	console.log( JSON.stringify( { verdict: out.verdict, reponsesExpress: out.commandes.map( ( c ) => c.reponseExpress ), callMethod: out.commandes.map( ( c ) => c.callMethodPose ), erreursClassique: classic ? classic.erreurs : null }, null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
