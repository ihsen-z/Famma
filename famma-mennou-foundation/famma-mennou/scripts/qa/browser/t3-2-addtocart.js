/**
 * Lot 3 — T3.2 (H-N3, H-N4) : AddToCart sur les 3 parcours, puis InitiateCheckout.
 *
 *  A. Fiche : bouton visible « Ajouter au panier » (POST, rechargement) sur
 *     16 (SKU), 17 (sans SKU), 21 (tailles famma, taille S) ; et le bouton
 *     WooCommerce de la fiche variable 18 (variation S = 19). Puis la fiche
 *     est rechargée en GET : aucun AddToCart ne doit repartir.
 *  B. Liste (AJAX) : boutique, boutons ajax_add_to_cart de 16 et 17.
 *  C. Express : fiche 16, formulaire rempli (données fictives QA-L3), clic
 *     « Confirmer ». La requête famma_express_checkout est interceptée par le
 *     navigateur et reçoit un refus de validation simulé : AUCUNE commande
 *     n'est créée. Deuxième clic (nouveau devis), puis rechargement et
 *     troisième clic : AddToCart et InitiateCheckout ne doivent partir qu'une fois.
 *  D. Checkout classique : /commande/ chargée deux fois avec le même panier.
 *
 * Succès : chaque AddToCart porte value, currency, content_ids et eventID ;
 * InitiateCheckout part une seule fois.
 *
 * Lot 5 (0.3.2) : préfixe des données fictives réglable (QA_PREFIX, défaut
 * QA-L3) ; chaque contrôle relève aussi l'absence de content_name (profil
 * « standard », R3/D7).
 */
'use strict';

const { BASE, launch, cleanPage, fbqCalls, writeProof, sleep, tracked, fillExpress } = require( './qa-lib' );

const PREFIX = process.env.QA_PREFIX || 'QA-L3';

const REFUS = JSON.stringify( {
	result: 'failure',
	famma_state: 'failed',
	famma_code: 'validation_failed',
	messages: '<ul class="woocommerce-error" role="alert"><li>' + ( process.env.QA_PREFIX || 'QA-L3' ) + ' : refus simulé par le navigateur de test (aucune commande créée)</li></ul>',
	refresh: false,
	reload: false,
} );

function check( ev ) {
	const p = ev.params || {};
	return {
		value: 'number' === typeof p.value && p.value > 0,
		currency: 'string' === typeof p.currency && '' !== p.currency,
		content_ids: Array.isArray( p.content_ids ) && p.content_ids.length > 0,
		eventID: !! ( ev.options && ev.options.eventID ),
		sansContentName: ! Object.prototype.hasOwnProperty.call( p, 'content_name' ),
	};
}

async function fichePost( s, slug, clickSel, prep ) {
	await s.page.goto( BASE + '/produit/' + slug + '/', { waitUntil: 'load' } );
	const before = await fbqCalls( s.page );
	if ( prep ) {
		await prep( s.page );
	}
	await Promise.all( [ s.page.waitForNavigation( { waitUntil: 'load' } ), s.page.click( clickSel ) ] );
	await sleep( 1200 );
	const after = await fbqCalls( s.page );
	const atc = tracked( after, 'AddToCart' );
	// Rechargement GET de la même fiche : la file serveur doit être vide.
	await s.page.goto( BASE + '/produit/' + slug + '/', { waitUntil: 'load' } );
	await sleep( 800 );
	const reload = tracked( await fbqCalls( s.page ), 'AddToCart' );
	return {
		parcours: 'fiche',
		produit: slug,
		viewContent: tracked( before, 'ViewContent' ).map( ( v ) => v.params && v.params.content_ids ),
		addToCart: atc,
		controle: atc.map( check ),
		addToCartApresRechargement: reload.length,
	};
}

( async () => {
	const browser = await launch();
	const result = { test: 'T3.2', hypotheses: [ 'H-N3', 'H-N4' ], date: new Date().toISOString(), fiche: [], liste: [], express: {}, checkout: {} };

	// ---------- A. Fiche ----------
	const a = await cleanPage( browser );
	result.fiche.push( await fichePost( a, 'qa-simple-sku', '.famma-express__cart' ) );
	result.fiche.push( await fichePost( a, 'qa-simple-sans-sku', '.famma-express__cart' ) );
	result.fiche.push( await fichePost( a, 'qa-tailles-famma', '.famma-express__cart', async ( page ) => {
		await page.evaluate( () => {
			const r = document.querySelector( '.famma-express__form input[name="famma_size"][value="S"]' );
			if ( r ) {
				r.click();
			}
		} );
	} ) );
	result.fiche.push( await fichePost( a, 'qa-tailles', 'form.variations_form button.single_add_to_cart_button', async ( page ) => {
		await page.select( 'select[name="attribute_taille"]', 'S' );
		await page.waitForFunction( () => {
			const v = document.querySelector( 'input[name="variation_id"]' );
			return v && '0' !== v.value && '' !== v.value;
		}, { timeout: 10000 } );
	} ) );
	result.ficheBlocked = a.blocked;
	await a.context.close();

	// ---------- B. Liste AJAX ----------
	const b = await cleanPage( browser );
	await b.page.goto( BASE + '/boutique/', { waitUntil: 'load' } );
	await sleep( 800 );
	for ( const id of [ '16', '17' ] ) {
		const n0 = tracked( await fbqCalls( b.page ), 'AddToCart' ).length;
		await b.page.click( 'a.ajax_add_to_cart[data-product_id="' + id + '"]' );
		let after = [];
		const end = Date.now() + 15000;
		while ( Date.now() < end ) {
			after = await fbqCalls( b.page );
			if ( tracked( after, 'AddToCart' ).length > n0 ) {
				break;
			}
			await sleep( 250 );
		}
		await sleep( 800 );
		const atc = tracked( await fbqCalls( b.page ), 'AddToCart' ).slice( n0 );
		result.liste.push( { parcours: 'liste AJAX (boutique)', produit: id, addToCart: atc, controle: atc.map( check ) } );
	}
	// Page suivante : aucun AddToCart en double venant de la file serveur.
	await b.page.goto( BASE + '/panier/', { waitUntil: 'load' } );
	await sleep( 800 );
	result.listePanierEnsuite = tracked( await fbqCalls( b.page ), 'AddToCart' ).length;

	// ---------- D. Checkout classique, chargé deux fois ----------
	const ic = [];
	for ( let i = 0; i < 2; i++ ) {
		await b.page.goto( BASE + '/commande/', { waitUntil: 'load' } );
		await sleep( 1200 );
		ic.push( tracked( await fbqCalls( b.page ), 'InitiateCheckout' ) );
	}
	result.checkout = {
		chargements: ic.map( ( l, i ) => ( { chargement: i + 1, initiateCheckout: l.length, events: l } ) ),
		eventIDsDistincts: new Set( ic.flat().map( ( e ) => e.options && e.options.eventID ) ).size,
	};
	result.checkout.blocked = b.blocked;
	await b.context.close();

	// ---------- C. Express (refus simulé, aucune commande) ----------
	const c = await cleanPage( browser, {
		onRequest: ( req ) => {
			if ( req.url().includes( 'wc-ajax=famma_express_checkout' ) ) {
				result.express.checkoutIntercepte = ( result.express.checkoutIntercepte || 0 ) + 1;
				req.respond( { status: 200, contentType: 'application/json; charset=UTF-8', body: REFUS } );
				return true;
			}
			return false;
		},
	} );
	const prepares = [];
	c.page.on( 'response', async ( res ) => {
		if ( res.url().includes( 'wc-ajax=famma_express_prepare' ) ) {
			try {
				const j = await res.json();
				prepares.push( { result: j.result, tracking: j.tracking || null } );
			} catch ( e ) {
				prepares.push( { error: String( e ).slice( 0, 120 ) } );
			}
		}
	} );
	await c.page.goto( BASE + '/produit/qa-simple-sku/', { waitUntil: 'load' } );
	await fillExpress( c.page, { firstName: PREFIX + ' Express T32', phone: '29000301', stateLabel: 'Sousse' } );
	const clicks = [];
	for ( let i = 1; i <= 2; i++ ) {
		const n = ( result.express.checkoutIntercepte || 0 );
		await c.page.click( '.famma-express__submit' );
		const end = Date.now() + 20000;
		while ( Date.now() < end && ( result.express.checkoutIntercepte || 0 ) <= n ) {
			await sleep( 250 );
		}
		await sleep( 1500 );
		const calls = await fbqCalls( c.page );
		clicks.push( { clic: i, page: 1, addToCart: tracked( calls, 'AddToCart' ), initiateCheckout: tracked( calls, 'InitiateCheckout' ), notice: await c.page.evaluate( () => ( document.querySelector( '.famma-express__notices' ) || {} ).textContent || '' ) } );
	}
	// Rechargement de la fiche, même onglet (sessionStorage conservé), troisième clic.
	await c.page.goto( BASE + '/produit/qa-simple-sku/', { waitUntil: 'load' } );
	await fillExpress( c.page, { firstName: PREFIX + ' Express T32', phone: '29000301', stateLabel: 'Sousse' } );
	{
		const n = ( result.express.checkoutIntercepte || 0 );
		await c.page.click( '.famma-express__submit' );
		const end = Date.now() + 20000;
		while ( Date.now() < end && ( result.express.checkoutIntercepte || 0 ) <= n ) {
			await sleep( 250 );
		}
		await sleep( 1500 );
		const calls = await fbqCalls( c.page );
		clicks.push( { clic: 3, page: 2, addToCart: tracked( calls, 'AddToCart' ), initiateCheckout: tracked( calls, 'InitiateCheckout' ) } );
	}
	result.express.clics = clicks;
	result.express.prepares = prepares;
	// window.__qaFbq est propre au document : la page 1 cumule les clics 1 et 2.
	result.express.totaux = {
		page1_addToCart: clicks[ 1 ].addToCart.length,
		page1_initiateCheckout: clicks[ 1 ].initiateCheckout.length,
		page2_addToCart: clicks[ 2 ].addToCart.length,
		page2_initiateCheckout: clicks[ 2 ].initiateCheckout.length,
	};
	result.express.controleAddToCart = ( clicks[ 0 ].addToCart || [] ).map( check );
	result.express.controleInitiateCheckout = ( clicks[ 0 ].initiateCheckout || [] ).map( check );
	result.express.blocked = c.blocked;
	await c.context.close();

	writeProof( 't3-2-addtocart.json', result );
	const brief = {
		fiche: result.fiche.map( ( f ) => ( { produit: f.produit, vc: f.viewContent, atc: f.addToCart.map( ( e ) => ( { ids: e.params && e.params.content_ids, value: e.params && e.params.value, cur: e.params && e.params.currency, eid: !! ( e.options && e.options.eventID ) } ) ), apresRechargement: f.addToCartApresRechargement } ) ),
		liste: result.liste.map( ( l ) => ( { produit: l.produit, atc: l.addToCart.map( ( e ) => ( { ids: e.params && e.params.content_ids, value: e.params && e.params.value, cur: e.params && e.params.currency, eid: !! ( e.options && e.options.eventID ) } ) ) } ) ),
		listePanierEnsuite: result.listePanierEnsuite,
		checkout: result.checkout.chargements.map( ( k ) => k.initiateCheckout ),
		checkoutEventIDsDistincts: result.checkout.eventIDsDistincts,
		express: clicks.map( ( k ) => ( { clic: k.clic, page: k.page, atc: k.addToCart.length, ic: k.initiateCheckout.length } ) ),
		expressCheckoutIntercepte: result.express.checkoutIntercepte,
		expressPrepares: prepares.length,
	};
	console.log( JSON.stringify( brief, null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
