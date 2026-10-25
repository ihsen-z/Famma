/**
 * Lot 5 — D7 : commande express arrivée avec des UTM, et profil « standard ».
 *
 * 1. Arrivée sur la fiche 16 avec
 *    ?utm_source=qa-l5n-d7&utm_medium=cpc&utm_campaign=qa-l5n-olympe&utm_content=qa-l5n-ad&fbclid=FAMMATEST-L5N-D7 ;
 *    on relève les cookies de WooCommerce (sbjs_*) et famma_utm, et les
 *    champs wc_order_attribution_* présents dans le formulaire express.
 * 2. Commande express QA-L5N réelle dans le clone.
 * 3. Les méta _wc_order_attribution_* de la commande sont lues ensuite par
 *    WP-CLI (qa-l5n-orders.php check), hors de ce script.
 * Profil standard : aucun appel fbq de la session (ViewContent, AddToCart,
 * InitiateCheckout de l'express, PageView) ne porte content_name.
 *
 * Référence « avant » : T2.10 (lot 2), origine « Inconnu » pour l'express.
 */
'use strict';

const { BASE, launch, cleanPage, fbqCalls, fbqSessionLog, cookies, writeProof, sleep, fillExpress } = require( './qa-lib' );

const PREFIX = process.env.QA_PREFIX || 'QA-L5N';
const QS = '?utm_source=qa-l5n-d7&utm_medium=cpc&utm_campaign=qa-l5n-olympe&utm_content=qa-l5n-ad&fbclid=FAMMATEST-L5N-D7';

( async () => {
	const browser = await launch();
	const s = await cleanPage( browser, { timeout: 90000 } );
	await s.page.goto( BASE + '/produit/qa-simple-sku/' + QS, { waitUntil: 'load' } );
	await sleep( 1500 );
	const jar = await cookies( s.page );
	const arrivee = {
		cookies: jar.filter( ( c ) => /^sbjs_|^famma_utm$|^_fbc$/.test( c.name ) ).map( ( c ) => ( { name: c.name, value: decodeURIComponent( c.value ).slice( 0, 200 ) } ) ),
		champsAttributionDansLeFormulaireExpress: await s.page.evaluate( () => {
			const f = document.querySelector( '.famma-express__form' );
			if ( ! f ) {
				return null;
			}
			const out = {};
			new FormData( f ).forEach( ( v, k ) => {
				if ( 0 === k.indexOf( 'wc_order_attribution_' ) ) {
					out[ k ] = 'wc_order_attribution_user_agent' === k ? '<présent>' : String( v );
				}
			} );
			return out;
		} ),
		elementWcOrderAttributionInputs: await s.page.evaluate( () => document.querySelectorAll( '.famma-express__form wc-order-attribution-inputs' ).length ),
		apiWcOrderAttribution: await s.page.evaluate( () => !! ( window.wc_order_attribution && 'function' === typeof window.wc_order_attribution.getAttributionData ) ),
	};
	await fillExpress( s.page, { firstName: PREFIX + ' D7 Express UTM', phone: '29000554', stateLabel: 'Sousse' } );
	const posted = [];
	s.page.on( 'request', ( req ) => {
		if ( req.url().includes( 'wc-ajax=famma_express_checkout' ) ) {
			const body = req.postData() || '';
			const keys = body.split( '&' ).map( ( kv ) => decodeURIComponent( kv.split( '=' )[ 0 ] || '' ) );
			const vals = {};
			body.split( '&' ).forEach( ( kv ) => {
				const [ k, v = '' ] = kv.split( '=' );
				const key = decodeURIComponent( k || '' );
				if ( 0 === key.indexOf( 'wc_order_attribution_' ) && 'wc_order_attribution_user_agent' !== key ) {
					vals[ key ] = decodeURIComponent( v.replace( /\+/g, ' ' ) );
				}
			} );
			posted.push( { champsAttributionEnvoyes: keys.filter( ( k ) => 0 === k.indexOf( 'wc_order_attribution_' ) ), valeurs: vals } );
		}
	} );
	const before = await fbqCalls( s.page );
	await Promise.all( [ s.page.waitForNavigation( { waitUntil: 'load', timeout: 90000 } ), s.page.click( '.famma-express__submit' ) ] );
	await sleep( 1500 );
	const url = s.page.url();
	const m = url.match( /order-received\/(\d+)/ );
	const log = await fbqSessionLog( s.page );
	const withContentName = log.filter( ( e ) => Array.isArray( e.args ) && e.args[ 2 ] && 'object' === typeof e.args[ 2 ] && Object.prototype.hasOwnProperty.call( e.args[ 2 ], 'content_name' ) );
	const out = {
		test: 'D7 (attribution express, profil standard)',
		date: new Date().toISOString(),
		urlArrivee: BASE + '/produit/qa-simple-sku/' + QS,
		arrivee,
		requeteExpress: posted,
		orderId: m ? Number( m[ 1 ] ) : null,
		appelsFbqFiche: before,
		journalSessionFbq: log.map( ( e ) => ( { page: e.page.replace( /key=[^&]+/, 'key=<masquée>' ), args: e.args } ) ),
		verdict: {
			commandeCreee: !! m,
			champsAttributionEnvoyes: posted.length ? posted[ 0 ].champsAttributionEnvoyes.length : 0,
			appelsFbqDeLaSession: log.length,
			appelsAvecContentName: withContentName.length,
		},
		horsClone: s.blocked,
	};
	writeProof( 'l5-d7-attribution-express.json', out );
	console.log( JSON.stringify( { verdict: out.verdict, orderId: out.orderId, arrivee: out.arrivee, posted }, null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
