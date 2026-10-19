/**
 * Lot 3 — T3.3 (H-S1) : fbclid → cookie _fbc → méta _famma_fbc → CAPI.
 *
 * 1. Arrivée sur la fiche 16 avec ?fbclid=FAMMATEST123 : le cookie _fbc doit
 *    valoir fb.1.<horodatage ms>.FAMMATEST123.
 * 2. Commande express QA-L3 sur la fiche 16 (vraie commande dans le clone).
 * 3. Commande QA-L3 par le panier et le checkout classique (produit 17).
 * Les deux commandes sont créées par ce navigateur, avec le même cookie.
 * La lecture des méta et de la requête CAPI interceptée se fait ensuite par
 * WP-CLI (voir t3-3-verifier.sh).
 *
 * Données 100 % fictives, préfixées « QA-L3 ». Téléphones de test au format
 * tunisien (8 chiffres), jamais contactés : le clone bloque courriel et HTTP.
 */
'use strict';

const { BASE, launch, cleanPage, fbqCalls, cookies, writeProof, sleep, tracked, fillExpress } = require( './qa-lib' );

const FBC_RE = /^fb\.1\.(\d{13})\.FAMMATEST123$/;
const FBP_RE = /^fb\.1\.\d{13}\.\d{10}$/;

function orderIdFrom( url ) {
	const m = url.match( /order-received\/(\d+)/ ) || url.match( /[?&]order(?:_id)?=(\d+)/ );
	return m ? Number( m[ 1 ] ) : null;
}

/** Vide un champ (le checkout peut être pré-rempli par la session WooCommerce) puis le saisit. */
async function retype( page, sel, value ) {
	await page.$eval( sel, ( el ) => {
		el.value = '';
	} );
	await page.type( sel, value );
}

// Phase : « tout » (express puis classique), « classique » seul, ou « reprise »
// (express puis classique dans la même session, preuve séparée, après coupure).
const PHASE = process.env.QA_ARG || 'tout';
const PROOFS = { classique: 't3-3-fbc-navigateur-classique.json', reprise: 't3-3-fbc-navigateur-reprise.json' };
const PROOF = PROOFS[ PHASE ] || 't3-3-fbc-navigateur.json';

function pick( list, name ) {
	const c = list.find( ( x ) => x.name === name );
	return c ? c.value : null;
}

( async () => {
	const browser = await launch();
	const s = await cleanPage( browser, { timeout: 90000 } );
	const out = { test: 'T3.3', hypothese: 'H-S1', date: new Date().toISOString(), etapes: [] };

	// 1. Arrivée publicitaire simulée.
	const t0 = Date.now();
	await s.page.goto( BASE + '/produit/qa-simple-sku/?fbclid=FAMMATEST123', { waitUntil: 'load' } );
	const t1 = Date.now();
	await sleep( 800 );
	const c1 = await cookies( s.page );
	const fbc = pick( c1, '_fbc' );
	const fbp = pick( c1, '_fbp' );
	const m = fbc ? fbc.match( FBC_RE ) : null;
	out.cookieArrivee = {
		_fbc: fbc,
		_fbp: fbp,
		fbcFormatOk: !! m,
		fbcHorodatageDansFenetre: m ? Number( m[ 1 ] ) >= t0 - 5000 && Number( m[ 1 ] ) <= t1 + 5000 : false,
		fbpFormatOk: fbp ? FBP_RE.test( fbp ) : false,
		attributs: c1.filter( ( c ) => '_fbc' === c.name || '_fbp' === c.name ),
		nomsCookies: c1.map( ( c ) => c.name ),
	};

	// 2. Commande express.
	if ( 'classique' !== PHASE ) {
		await fillExpress( s.page, { firstName: 'QA-L3 Express T33', phone: '29000333', stateLabel: 'Sousse' } );
		await Promise.all( [
			s.page.waitForNavigation( { waitUntil: 'load', timeout: 90000 } ),
			s.page.click( '.famma-express__submit' ),
		] );
		await sleep( 1200 );
		const expressUrl = s.page.url();
		const c2 = await cookies( s.page );
		out.express = {
			urlFinale: expressUrl.replace( /key=[^&]+/, 'key=<masquée>' ),
			orderId: orderIdFrom( expressUrl ),
			cookieFbcAuMomentDeLaCommande: pick( c2, '_fbc' ),
			fbqPageMerci: ( await fbqCalls( s.page ) ).map( ( c ) => c.slice( 0, 2 ) ),
		};
		writeProof( PROOF, Object.assign( {}, out, { enCours: 'après express' } ) );
	}

	// 3. Commande classique : fiche 17 → panier → checkout.
	await s.page.goto( BASE + '/produit/qa-simple-sans-sku/', { waitUntil: 'load' } );
	await Promise.all( [ s.page.waitForNavigation( { waitUntil: 'load' } ), s.page.click( '.famma-express__cart' ) ] );
	await s.page.goto( BASE + '/commande/', { waitUntil: 'load' } );
	await sleep( 1500 );
	const icClassique = tracked( await fbqCalls( s.page ), 'InitiateCheckout' );
	await retype( s.page, '#billing_first_name', 'QA-L3 Classique T33' );
	await retype( s.page, '#billing_phone', '29000334' );
	await retype( s.page, '#billing_address_1', 'QA-L3 adresse fictive 1' );
	if ( await s.page.$( '#billing_city' ) ) {
		await retype( s.page, '#billing_city', 'Sousse' );
	}
	await s.page.select( '#billing_state', 'TN-51' );
	await sleep( 2500 ); // update_order_review (AJAX) après le changement de gouvernorat.
	await s.page.waitForFunction( () => ! document.querySelector( '.blockUI' ), { timeout: 30000 } );
	const c3 = await cookies( s.page );
	const filled = await s.page.evaluate( () => Array.from( document.querySelectorAll( 'form.checkout [name^="billing_"]' ) ).map( ( e ) => e.name + '=' + ( e.value ? 'renseigné' : 'vide' ) ) );
	// Diagnostic : valeur du téléphone (numéro de test fictif) juste avant l'envoi.
	const phoneAvantEnvoi = await s.page.$eval( '#billing_phone', ( el ) => el.value );
	writeProof( PROOF, Object.assign( {}, out, { enCours: 'checkout classique', champs: filled } ) );
	await s.page.click( '#place_order' );
	const end = Date.now() + 90000;
	while ( Date.now() < end ) {
		await sleep( 500 );
		const state = await s.page.evaluate( () => ( {
			url: location.href,
			errors: Array.from( document.querySelectorAll( '.woocommerce-error li, .woocommerce-NoticeGroup-checkout li' ) ).map( ( e ) => e.textContent.trim() ),
		} ) ).catch( () => ( { url: '', errors: [] } ) );
		if ( /order-received/.test( state.url ) || state.errors.length ) {
			break;
		}
	}
	try {
		await s.page.waitForFunction( () => 'complete' === document.readyState, { timeout: 30000 } );
	} catch ( e ) {
		// La page de remerciement peut encore charger : on lit ce qui est là.
	}
	await sleep( 1500 );
	const classicUrl = s.page.url();
	out.classique = {
		urlFinale: classicUrl.replace( /key=[^&]+/, 'key=<masquée>' ),
		orderId: orderIdFrom( classicUrl ),
		cookieFbcAuMomentDeLaCommande: pick( c3, '_fbc' ),
		champs: filled,
		telephoneTestAvantEnvoi: phoneAvantEnvoi,
		initiateCheckout: icClassique,
		notices: await s.page.evaluate( () => Array.from( document.querySelectorAll( '.woocommerce-error li, .woocommerce-NoticeGroup li' ) ).map( ( e ) => e.textContent.trim() ) ),
		fbqPageMerci: ( await fbqCalls( s.page ) ).map( ( c ) => c.slice( 0, 2 ) ),
	};
	out.blockedOutsideClone = s.blocked;
	out.consoleErrors = s.console;
	writeProof( PROOF, out );
	console.log( JSON.stringify( {
		fbc: out.cookieArrivee._fbc,
		fbcFormatOk: out.cookieArrivee.fbcFormatOk,
		fbcHorodatage: out.cookieArrivee.fbcHorodatageDansFenetre,
		fbpFormatOk: out.cookieArrivee.fbpFormatOk,
		express: out.express ? out.express.orderId : null,
		expressUrl: out.express ? out.express.urlFinale : null,
		classique: out.classique.orderId,
		classiqueUrl: out.classique.urlFinale,
		notices: out.classique.notices,
		blocked: s.blocked.length,
	}, null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
