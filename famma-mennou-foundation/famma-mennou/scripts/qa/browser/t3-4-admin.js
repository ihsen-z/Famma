/**
 * Lot 3 — T3.4 (H-N5) : navigation de l'administrateur QA connecté.
 *
 * Connexion par wp-login.php du clone avec l'admin QA du lot 0 (fichier monté
 * en lecture seule sur /secrets/qa-admin.txt par « run.sh --admin » ; le mot
 * de passe n'est jamais imprimé ni écrit). Puis accueil, fiche, boutique,
 * ajout au panier, panier, commande et une page wp-admin : appels fbq de
 * chaque page. Le panier de l'admin est vidé à la fin. Aucune commande.
 *
 * Constat attendu aujourd'hui (0.3.1, avant S1) : le pixel part aussi pour
 * l'admin. Sert de référence « avant » pour le test A1 du lot 5.
 */
'use strict';

const fs = require( 'node:fs' );
const { BASE, launch, cleanPage, fbqCalls, summarize, cookies, writeProof, sleep } = require( './qa-lib' );

function credentials() {
	const raw = fs.readFileSync( '/secrets/qa-admin.txt', 'utf8' );
	const get = ( k ) => {
		const m = raw.match( new RegExp( '^' + k + '=(.*)$', 'm' ) );
		return m ? m[ 1 ].trim() : '';
	};
	return { user: get( 'user' ), pass: get( 'password' ) };
}

async function visit( s, label, url ) {
	const res = await s.page.goto( url, { waitUntil: 'load' } );
	await sleep( 1200 );
	return snapshot( s, label, res ? res.status() : null );
}

async function snapshot( s, label, status ) {
	const calls = await fbqCalls( s.page );
	const dom = await s.page.evaluate( () => ( {
		adminBar: !! document.getElementById( 'wpadminbar' ),
		loggedInClass: document.body.classList.contains( 'logged-in' ),
		recorder: !! ( window.fbq && window.fbq.qaRecorder ),
		pixelLoader: !! document.getElementById( 'famma-pixels-js-after' ),
	} ) );
	return { label, url: s.page.url(), status, dom, summary: summarize( calls ), calls };
}

( async () => {
	const cred = credentials();
	if ( ! cred.user || ! cred.pass ) {
		throw new Error( 'Identifiants QA absents de /secrets/qa-admin.txt' );
	}
	const browser = await launch();
	const s = await cleanPage( browser );
	const out = { test: 'T3.4', hypothese: 'H-N5', date: new Date().toISOString(), compte: 'qa-admin (administrator, clone local)', pages: [] };

	await s.page.goto( BASE + '/wp-login.php', { waitUntil: 'load' } );
	// wp-login.php sélectionne le champ identifiant 200 ms après le chargement :
	// on attend, puis on pose les valeurs (la frappe serait écrasée).
	await sleep( 800 );
	await s.page.$eval( '#user_login', ( el, v ) => {
		el.value = v;
	}, cred.user );
	await s.page.$eval( '#user_pass', ( el, v ) => {
		el.value = v;
	}, cred.pass );
	const typedOk = await s.page.evaluate( ( u, n ) => document.getElementById( 'user_login' ).value === u && document.getElementById( 'user_pass' ).value.length === n, cred.user, cred.pass.length );
	await Promise.all( [ s.page.waitForNavigation( { waitUntil: 'load' } ), s.page.click( '#wp-submit' ) ] );
	out.connexion = {
		saisieComplete: typedOk,
		messageErreur: await s.page.evaluate( () => ( document.getElementById( 'login_error' ) || {} ).textContent || '' ).then( ( t ) => t.trim().slice( 0, 200 ) ),
		urlApres: s.page.url(),
		cookiesWordPress: ( await cookies( s.page ) ).map( ( c ) => c.name ).filter( ( n ) => /^wordpress_/.test( n ) ).map( ( n ) => n.replace( /_[0-9a-f]{32}$/, '_<hash>' ) ),
	};

	out.pages.push( await visit( s, 'accueil', BASE + '/' ) );
	out.pages.push( await visit( s, 'fiche QA Simple SKU (16)', BASE + '/produit/qa-simple-sku/' ) );
	out.pages.push( await visit( s, 'boutique', BASE + '/boutique/' ) );
	await s.page.goto( BASE + '/produit/qa-simple-sku/', { waitUntil: 'load' } );
	await Promise.all( [ s.page.waitForNavigation( { waitUntil: 'load' } ), s.page.click( '.famma-express__cart' ) ] );
	await sleep( 1200 );
	out.pages.push( await snapshot( s, 'fiche après ajout au panier', 200 ) );
	out.pages.push( await visit( s, 'panier', BASE + '/panier/' ) );
	out.pages.push( await visit( s, 'commande', BASE + '/commande/' ) );
	out.pages.push( await visit( s, 'wp-admin (tableau de bord)', BASE + '/wp-admin/' ) );

	// Remise en état : panier de l'admin vidé (lien « retirer » de WooCommerce).
	await s.page.goto( BASE + '/panier/', { waitUntil: 'load' } );
	let removed = 0;
	while ( await s.page.$( 'a.remove[data-product_id]' ) && removed < 10 ) {
		await Promise.all( [ s.page.waitForNavigation( { waitUntil: 'load' } ).catch( () => null ), s.page.click( 'a.remove[data-product_id]' ) ] );
		await sleep( 1500 );
		removed++;
	}
	out.remiseEnEtat = { articlesRetires: removed, panierVide: ! ( await s.page.$( 'a.remove[data-product_id]' ) ) };

	out.verdict = out.pages.map( ( p ) => ( { page: p.label, connecte: p.dom.loggedInClass, init: p.summary.init, pageView: p.summary.pageView, evenements: p.summary.track } ) );
	out.blockedOutsideClone = s.blocked;
	writeProof( 't3-4-admin.json', out );
	console.log( JSON.stringify( { connexion: out.connexion, verdict: out.verdict, remiseEnEtat: out.remiseEnEtat, blocked: s.blocked.length }, null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( String( e && e.message ? e.message : e ).replace( /password=.*/g, '' ) );
	process.exit( 1 );
} );
