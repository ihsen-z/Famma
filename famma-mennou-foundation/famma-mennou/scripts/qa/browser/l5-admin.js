/**
 * Lot 5 — A1, A2, A4 (S1, exclusion des visites internes).
 *
 * Mode « a1a2 » (réglage « Suivre mes visites » coupé, défaut) :
 *  A1. Admin QA connecté (run.sh --admin) : accueil, fiche, boutique, ajout
 *      au panier (POST), panier, commande, puis formulaire express rempli et
 *      confirmé. La requête famma_express_checkout reçoit un refus simulé :
 *      AUCUNE commande. On relève les appels fbq, le contenu du script
 *      famma-pixels, la présence de famma-fbc, le bloc `tracking` de la
 *      réponse famma_express_prepare, et les en-têtes de cache (sonde
 *      qa-l5n-cache-probe.php : action LiteSpeed « no-cache », DONOTCACHEPAGE).
 *      Attendu : 0 init, 0 événement, bloc tracking vide.
 *  A2. Juste après : (a) déconnexion par le lien de la barre d'admin, même
 *      contexte, puis les mêmes pages en visiteur ; (b) nouveau contexte
 *      vierge, mêmes pages. Attendu : Pixel complet (1 init, PageView,
 *      ViewContent, AddToCart, InitiateCheckout) et aucune variante admin.
 * Mode « a4 » (réglage « Suivre mes visites » activé AVANT, par WP-CLI) :
 *  A4. Admin connecté, mêmes pages et même express refusé. Attendu : les
 *      événements de l'admin sont présents, bloc tracking non vide.
 *
 * Le panier de l'admin est vidé à la fin. Données fictives préfixées
 * QA_PREFIX (défaut QA-L5N). Identifiants jamais imprimés.
 *
 * Usage : bash scripts/qa/browser/run.sh l5-admin.js --admin <a1a2|a4>
 */
'use strict';

const { BASE, launch, cleanPage, fbqCalls, summarize, cookies, writeProof, sleep, tracked, fillExpress, adminLogin, cacheHeaders } = require( './qa-lib' );

const MODE = process.env.QA_ARG || 'a1a2';
const PREFIX = process.env.QA_PREFIX || 'QA-L5N';
const REFUS = JSON.stringify( {
	result: 'failure',
	famma_state: 'failed',
	famma_code: 'validation_failed',
	messages: '<ul class="woocommerce-error" role="alert"><li>' + PREFIX + ' : refus simulé par le navigateur de test (aucune commande créée)</li></ul>',
	refresh: false,
	reload: false,
} );

/** État de la page : appels fbq, scripts de mesure présents, connexion. */
async function snapshot( s, label, res ) {
	const calls = await fbqCalls( s.page );
	const dom = await s.page.evaluate( () => {
		const px = document.getElementById( 'famma-pixels-js-after' );
		const txt = px ? px.textContent || '' : '';
		return {
			connecte: document.body.classList.contains( 'logged-in' ),
			barreAdmin: !! document.getElementById( 'wpadminbar' ),
			scriptFammaPixels: !! px,
			scriptFammaPixelsContientInit: -1 !== txt.indexOf( "fbq('init'" ),
			scriptFammaPixelsContientEvenements: /fbq\("track"/.test( txt ),
			scriptFammaPixelsContientUtm: -1 !== txt.indexOf( 'famma_utm' ),
			scriptFbc: !! document.getElementById( 'famma-fbc-js-after' ),
			tiktok: !! document.querySelector( 'script[src*="analytics.tiktok.com"]' ) || /TiktokAnalyticsObject/.test( txt ),
		};
	} );
	return { label, url: s.page.url(), status: res ? res.status() : null, entetes: cacheHeaders( res ), dom, summary: summarize( calls ), calls };
}

async function visit( s, label, url ) {
	const res = await s.page.goto( url, { waitUntil: 'load' } );
	await sleep( 1200 );
	return snapshot( s, label, res );
}

/** Parcours commun : pages, ajout au panier, panier, commande. */
async function parcours( s ) {
	const pages = [];
	pages.push( await visit( s, 'accueil', BASE + '/' ) );
	pages.push( await visit( s, 'fiche QA Simple SKU (16)', BASE + '/produit/qa-simple-sku/' ) );
	pages.push( await visit( s, 'boutique', BASE + '/boutique/' ) );
	await s.page.goto( BASE + '/produit/qa-simple-sku/', { waitUntil: 'load' } );
	const [ post ] = await Promise.all( [ s.page.waitForNavigation( { waitUntil: 'load' } ), s.page.click( '.famma-express__cart' ) ] );
	await sleep( 1200 );
	pages.push( await snapshot( s, 'fiche après ajout au panier (POST)', post ) );
	pages.push( await visit( s, 'panier', BASE + '/panier/' ) );
	pages.push( await visit( s, 'commande', BASE + '/commande/' ) );
	return pages;
}

/** Express confirmé avec refus simulé : bloc tracking de la préparation. */
async function expressRefuse( browserState, label ) {
	const s = browserState;
	const prepares = [];
	let intercepted = 0;
	const onResponse = async ( res ) => {
		if ( res.url().includes( 'wc-ajax=famma_express_prepare' ) ) {
			try {
				const j = await res.json();
				prepares.push( { result: j.result, tracking: Object.prototype.hasOwnProperty.call( j, 'tracking' ) ? j.tracking : '<clé absente>' } );
			} catch ( e ) {
				prepares.push( { error: String( e ).slice( 0, 120 ) } );
			}
		}
	};
	s.page.on( 'response', onResponse );
	s.expressHook = ( req ) => {
		if ( req.url().includes( 'wc-ajax=famma_express_checkout' ) ) {
			intercepted++;
			req.respond( { status: 200, contentType: 'application/json; charset=UTF-8', body: REFUS } );
			return true;
		}
		return false;
	};
	const res = await s.page.goto( BASE + '/produit/qa-simple-sku/', { waitUntil: 'load' } );
	await fillExpress( s.page, { firstName: PREFIX + ' Admin ' + label, phone: '29000551', stateLabel: 'Sousse' } );
	await s.page.click( '.famma-express__submit' );
	const end = Date.now() + 20000;
	while ( Date.now() < end && 0 === intercepted ) {
		await sleep( 250 );
	}
	await sleep( 1500 );
	const snap = await snapshot( s, 'fiche, express confirmé (refus simulé)', res );
	s.page.off( 'response', onResponse );
	s.expressHook = null;
	return {
		checkoutIntercepte: intercepted,
		prepares,
		blocTrackingVide: prepares.length > 0 && prepares.every( ( p ) => Array.isArray( p.tracking ) ? 0 === p.tracking.length : ( p.tracking && 'object' === typeof p.tracking && 0 === Object.keys( p.tracking ).length ) ),
		addToCart: tracked( snap.calls, 'AddToCart' ),
		initiateCheckout: tracked( snap.calls, 'InitiateCheckout' ),
		page: snap,
	};
}

async function viderPanier( s ) {
	await s.page.goto( BASE + '/panier/', { waitUntil: 'load' } );
	let removed = 0;
	while ( await s.page.$( 'a.remove[data-product_id]' ) && removed < 10 ) {
		await Promise.all( [ s.page.waitForNavigation( { waitUntil: 'load' } ).catch( () => null ), s.page.click( 'a.remove[data-product_id]' ) ] );
		await sleep( 1500 );
		removed++;
	}
	return { articlesRetires: removed, panierVide: ! ( await s.page.$( 'a.remove[data-product_id]' ) ) };
}

/** Ouvre un onglet dont le filtre peut servir l'express refusé. */
async function page( browser ) {
	const holder = {};
	const s = await cleanPage( browser, { onRequest: ( req ) => ( holder.s && holder.s.expressHook ? holder.s.expressHook( req ) : false ) } );
	holder.s = s;
	return s;
}

function verdictPages( pages ) {
	return pages.map( ( p ) => ( {
		page: p.label,
		connecte: p.dom.connecte,
		init: p.summary.init,
		evenements: p.summary.track,
		fbc: p.dom.scriptFbc,
		nocacheLiteSpeed: p.entetes ? p.entetes[ 'x-qa-l5n-lscache-nocache' ] || null : null,
		donotcachepage: p.entetes ? p.entetes[ 'x-qa-l5n-donotcachepage' ] || null : null,
		cacheControl: p.entetes ? p.entetes[ 'cache-control' ] || null : null,
	} ) );
}

( async () => {
	const browser = await launch();
	const out = { test: 'a4' === MODE ? 'A4' : 'A1+A2', decision: 'S1', mode: MODE, date: new Date().toISOString(), compte: 'qa-admin (administrator, clone local)' };

	const s = await page( browser );
	out.connexion = await adminLogin( s );
	if ( ! out.connexion.connecte ) {
		throw new Error( 'Connexion admin QA échouée' );
	}
	out.admin = { pages: await parcours( s ) };
	out.admin.express = await expressRefuse( s, 'a4' === MODE ? 'A4' : 'A1' );
	out.admin.remiseEnEtat = await viderPanier( s );
	out.admin.verdict = verdictPages( out.admin.pages.concat( [ out.admin.express.page ] ) );
	out.admin.totaux = {
		init: out.admin.pages.reduce( ( n, p ) => n + p.summary.init, 0 ) + out.admin.express.page.summary.init,
		evenements: out.admin.pages.reduce( ( n, p ) => n + p.summary.track.length, 0 ) + out.admin.express.page.summary.track.length,
		blocTrackingVide: out.admin.express.blocTrackingVide,
		scriptFbcPresent: out.admin.pages.some( ( p ) => p.dom.scriptFbc ),
	};

	if ( 'a1a2' === MODE ) {
		// A2 (a) : déconnexion par le lien de la barre d'admin, même contexte.
		const logout = await s.page.evaluate( () => {
			const a = document.querySelector( '#wp-admin-bar-logout a' );
			return a ? a.href : null;
		} );
		if ( logout ) {
			await s.page.goto( logout, { waitUntil: 'load' } );
		}
		const jar = await cookies( s.page );
		out.a2MemeContexte = {
			deconnexion: !! logout && ! jar.some( ( c ) => /^wordpress_logged_in_/.test( c.name ) ),
			pages: await parcours( s ),
		};
		out.a2MemeContexte.verdict = verdictPages( out.a2MemeContexte.pages );
		out.a2MemeContexte.remiseEnEtat = await viderPanier( s );

		// A2 (b) : visiteur vierge juste après.
		const v = await page( browser );
		out.a2Vierge = { pages: await parcours( v ) };
		out.a2Vierge.express = await expressRefuse( v, 'A2' );
		out.a2Vierge.verdict = verdictPages( out.a2Vierge.pages.concat( [ out.a2Vierge.express.page ] ) );
		out.a2Vierge.horsClone = v.blocked;
		await v.context.close();
	}
	out.horsClone = s.blocked.filter( ( u ) => ! /gravatar\.com/.test( u ) );
	out.horsCloneGravatar = s.blocked.filter( ( u ) => /gravatar\.com/.test( u ) ).length;
	await s.context.close();

	const proof = 'a4' === MODE ? 'l5-a4-admin-derogation.json' : 'l5-a1-a2-admin.json';
	writeProof( proof, out );
	console.log( JSON.stringify( {
		connexion: out.connexion,
		admin: { verdict: out.admin.verdict, totaux: out.admin.totaux, prepares: out.admin.express.prepares.map( ( p ) => p.tracking ), express: { atc: out.admin.express.addToCart.length, ic: out.admin.express.initiateCheckout.length, intercepte: out.admin.express.checkoutIntercepte }, remise: out.admin.remiseEnEtat },
		a2MemeContexte: out.a2MemeContexte ? { deconnexion: out.a2MemeContexte.deconnexion, verdict: out.a2MemeContexte.verdict } : null,
		a2Vierge: out.a2Vierge ? { verdict: out.a2Vierge.verdict, blocTrackingVide: out.a2Vierge.express.blocTrackingVide, atc: out.a2Vierge.express.addToCart.length, ic: out.a2Vierge.express.initiateCheckout.length } : null,
		horsClone: out.horsClone.length,
	}, null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( String( e && e.message ? e.message : e ).replace( /password=.*/g, '' ) );
	process.exit( 1 );
} );
