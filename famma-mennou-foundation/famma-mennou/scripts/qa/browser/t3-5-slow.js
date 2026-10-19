/**
 * Lot 3 — T3.5 (H-N6) : réseau lent et départ rapide.
 *
 * Question : le chargement différé de fbevents.js (famma-core, Tracking::
 * enqueue_tracking : la file fbq est créée tout de suite, mais la balise
 * <script> de Meta n'est insérée qu'après « load » puis requestIdleCallback,
 * délai max 3 s) perd-il des événements quand le visiteur part vite ?
 *
 * Montage (aucune requête réelle vers Meta) :
 * - L'enregistreur fbq du lot 0 est neutralisé pour ce test (il s'arrête si
 *   window.__qaFbqRecorder existe déjà) et la fonction-panneau du garde
 *   d'intégration est ignorée : le vrai chargeur de famma-core tourne donc,
 *   avec sa file fbq.queue et son insertion différée de fbevents.js.
 * - La requête vers connect.facebook.net/en_US/fbevents.js est servie par
 *   Puppeteer avec une FAUSSE bibliothèque, après un délai qui simule son
 *   téléchargement sur le réseau émulé : latence + 190 Kio / débit (taille
 *   citée par le commentaire PERF-07 de famma-core). Une page suivante du
 *   même contexte la reçoit sans délai (cache HTTP du navigateur).
 * - La fausse bibliothèque vide la file et émet, pour chaque « track », une
 *   requête image vers https://www.facebook.com/tr/ : Puppeteer la note
 *   (= événement parti) et y répond lui-même par un GIF, sans réseau.
 * - Réseau émulé par CDP (Puppeteer PredefinedNetworkConditions) sur les
 *   requêtes du clone.
 *
 * Scénarios, 5 essais chacun dans un contexte vierge :
 * - fiche : arrivée sur /produit/qa-simple-sku/, départ 1,5 s après
 *   l'affichage (DOMContentLoaded). Attendus : PageView, ViewContent.
 * - ajout : la fiche est chargée complètement (bibliothèque reçue), clic sur
 *   « Ajouter au panier » (POST, la fiche se recharge), départ 1,5 s après
 *   l'affichage de la page rechargée. Attendus : PageView, ViewContent,
 *   AddToCart de la page rechargée.
 * Plus un essai témoin par scénario, sans départ, pour la chronologie.
 *
 * Deux modèles de téléchargement de la bibliothèque (argument « :mesure ») :
 * - « 190k » (défaut) : une seule requête de 190 Kio (commentaire PERF-07) ;
 * - « mesure » : tailles relevées en prod par T4.5 (Resource Timing,
 *   transferSize) : fbevents.js 114 293 o, puis la config du pixel
 *   signals/config/<id> 104 801 o, demandée par la bibliothèque après son
 *   exécution ; les événements ne partent qu'une fois la config reçue.
 *
 * Usage : bash scripts/qa/browser/run.sh t3-5-slow.js <slow3g|fast3g|aucun>[:mesure]
 */
'use strict';

const { PredefinedNetworkConditions } = require( 'puppeteer' );
const { BASE, launch, cleanPage, writeProof, sleep } = require( './qa-lib' );

const CONDITIONS = {
	slow3g: { label: 'Slow 3G (CDP)', net: PredefinedNetworkConditions[ 'Slow 3G' ] },
	fast3g: { label: 'Fast 3G (CDP)', net: PredefinedNetworkConditions[ 'Fast 3G' ] },
	aucun: { label: 'sans bridage', net: null },
};
const [ KEY, MODEL_KEY = '190k' ] = ( process.env.QA_ARG || 'slow3g' ).split( ':' );
const COND = CONDITIONS[ KEY ];
const MODELS = {
	'190k': { label: '1 requête de 190 Kio (commentaire PERF-07 de famma-core)', lib: 190 * 1024, config: 0, suffix: '' },
	mesure: { label: 'tailles mesurées en prod (T4.5) : fbevents.js 114 293 o puis signals/config 104 801 o', lib: 114293, config: 104801, suffix: '-mesure' },
};
const MODEL = MODELS[ MODEL_KEY ];
if ( ! COND || ! MODEL ) {
	throw new Error( 'Condition ou modèle inconnu : ' + process.env.QA_ARG );
}
function transferDelay( bytes ) {
	return COND.net && bytes ? Math.round( COND.net.latency + ( bytes / COND.net.download ) * 1000 ) : 0;
}
const LIB_DELAY = transferDelay( MODEL.lib );
const CONFIG_DELAY = transferDelay( MODEL.config );
const PROOF = 't3-5-' + KEY + MODEL.suffix + '.json';
const DEPART_MS = 1500;
const TRIALS = 5;
const FICHE = BASE + '/produit/qa-simple-sku/';
const EXPECTED = { fiche: [ 'PageView', 'ViewContent' ], ajout: [ 'PageView', 'ViewContent', 'AddToCart' ] };

// Exécuté avant tout script de chaque document du contexte.
const PRE = `(() => {
	window.__qaFbqRecorder = { desactivePourT35: true };
	let real;
	const ignored = [];
	Object.defineProperty( window, 'fbq', {
		configurable: true,
		enumerable: true,
		get() { return real; },
		set( f ) {
			if ( 'function' === typeof f && -1 !== String( f ).indexOf( 'famma-local-pixel-trace' ) ) {
				ignored.push( Date.now() );
				return;
			}
			real = f;
		},
	} );
	window.__qaT35 = { panelIgnored: ignored };
})();`;

// Fausse fbevents.js : vide la file, puis « envoie » chaque événement.
const FAKE_LIB = `(function (w, d) {
	var f = w.fbq;
	if (!f || f.__qaFakeLib) { return; }
	var pixels = [], withConfig = ${ MODEL.config > 0 ? 'true' : 'false' }, ready = !withConfig, requested = false, pending = [];
	function send(a) {
		var o = a[3] || {};
		var img = new Image();
		img.src = 'https://www.facebook.com/tr/?id=' + encodeURIComponent(pixels[0] || '') + '&ev=' + encodeURIComponent(a[1]) + '&eid=' + encodeURIComponent(o.eventID || '') + '&dl=' + encodeURIComponent(location.pathname) + '&qa_t35=1';
		(w.__qaTrImgs = w.__qaTrImgs || []).push(img);
	}
	function loadConfig(id) {
		if (requested) { return; }
		requested = true;
		var s = d.createElement('script');
		s.async = true;
		s.src = 'https://connect.facebook.net/signals/config/' + encodeURIComponent(id) + '?v=2.9.419&r=stable&qa_t35=1';
		s.onload = function () { ready = true; w.__qaConfigAt = Math.round(performance.now()); pending.splice(0).forEach(send); };
		d.head.appendChild(s);
	}
	function handle(args) {
		var a = Array.prototype.slice.call(args);
		if ('init' === a[0]) { pixels.push(a[1]); if (withConfig) { loadConfig(a[1]); } return; }
		if ('track' === a[0] || 'trackCustom' === a[0]) { if (ready) { send(a); } else { pending.push(a); } }
	}
	f.callMethod = function () { handle(arguments); };
	var q = f.queue.slice();
	f.queue.length = 0;
	for (var i = 0; i < q.length; i++) { handle(q[i]); }
	f.__qaFakeLib = true;
	w.__qaFakeLibAt = Math.round(performance.now());
})(window, document);`;
const GIF = Buffer.from( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64' );

/** Instantané de la page au moment du départ. */
function snapshot( page ) {
	return page.evaluate( () => {
		const f = window.fbq;
		const nav = performance.getEntriesByType( 'navigation' )[ 0 ] || {};
		return {
			maintenantMs: Math.round( performance.now() ),
			readyState: document.readyState,
			dclMs: Math.round( nav.domContentLoadedEventEnd || 0 ),
			loadMs: Math.round( nav.loadEventEnd || 0 ),
			fbqDefini: 'function' === typeof f,
			fileEnAttente: f && f.queue ? Array.prototype.map.call( f.queue, ( a ) => a[ 0 ] + ( a[ 1 ] ? ':' + a[ 1 ] : '' ) ) : null,
			balisePixelInseree: !! document.querySelector( 'script[src*="connect.facebook.net/en_US/fbevents.js"]' ),
			fausseBibliothequeChargeeMs: window.__qaFakeLibAt || null,
			configPixelRecueMs: window.__qaConfigAt || null,
			panneauIntegrationIgnore: window.__qaT35 ? window.__qaT35.panelIgnored.length : null,
		};
	} );
}

/** Prépare un contexte vierge : bridage, interception Meta simulée. */
async function trialPage( browser, log ) {
	const served = {};
	const s = await cleanPage( browser, {
		timeout: 180000,
		onRequest: ( req ) => {
			const url = req.url();
			if ( url.startsWith( 'https://connect.facebook.net/' ) ) {
				const kind = url.includes( '/en_US/fbevents.js' ) ? 'fbevents.js' : ( url.includes( '/signals/config/' ) ? 'config' : 'autre' );
				const full = 'fbevents.js' === kind ? LIB_DELAY : CONFIG_DELAY;
				const delay = served[ kind ] ? 0 : full;
				const entry = { phase: log.phase, ressource: kind, demandeeMs: Date.now() - log.t0, delaiSimuleMs: delay, servie: false };
				log.lib.push( entry );
				if ( 'autre' === kind ) {
					req.abort( 'blockedbyclient' );
					return true;
				}
				const body = 'fbevents.js' === kind ? FAKE_LIB : '/* config QA factice */';
				setTimeout( () => {
					req.respond( { status: 200, contentType: 'application/javascript', body } ).then( () => {
						entry.servie = true;
						entry.servieMs = Date.now() - log.t0;
						served[ kind ] = true;
					}, () => {
						entry.annulee = true;
					} );
				}, delay );
				return true;
			}
			if ( url.startsWith( 'https://www.facebook.com/tr' ) ) {
				const u = new URL( url );
				log.sent.push( { phase: log.phase, ev: u.searchParams.get( 'ev' ), eidPresent: '' !== ( u.searchParams.get( 'eid' ) || '' ), dl: u.searchParams.get( 'dl' ), envoyeMs: Date.now() - log.t0 } );
				req.respond( { status: 200, contentType: 'image/gif', body: GIF } ).catch( () => {} );
				return true;
			}
			return false;
		},
	} );
	await s.page.evaluateOnNewDocument( PRE );
	if ( COND.net ) {
		await s.page.emulateNetworkConditions( COND.net );
	}
	return s;
}

/** Attend que les événements attendus de la phase soient partis. */
async function waitSent( log, phase, expected, timeout ) {
	const end = Date.now() + timeout;
	while ( Date.now() < end ) {
		const got = log.sent.filter( ( e ) => e.phase === phase ).map( ( e ) => e.ev );
		if ( expected.every( ( ev ) => got.includes( ev ) ) ) {
			return true;
		}
		await sleep( 250 );
	}
	return false;
}

async function runTrial( browser, scenario, essai, temoin ) {
	const log = { phase: 'page1', t0: Date.now(), lib: [], sent: [] };
	const s = await trialPage( browser, log );
	const res = { scenario, essai: temoin ? 'témoin (sans départ)' : essai, condition: COND.label };
	try {
		log.t0 = Date.now();
		await s.page.goto( FICHE, { waitUntil: 'domcontentloaded', timeout: 180000 } );
		let phase = 'page1';
		if ( 'ajout' === scenario ) {
			// Visite complète de la fiche avant le clic (la bibliothèque est reçue).
			res.page1ToutParti = await waitSent( log, 'page1', EXPECTED.fiche, 150000 );
			log.phase = phase = 'page2';
			log.t0 = Date.now();
			await Promise.all( [
				s.page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 180000 } ),
				s.page.click( '.famma-express__cart' ),
			] );
		}
		if ( temoin ) {
			res.toutParti = await waitSent( log, phase, EXPECTED[ scenario ], 150000 );
			res.instantane = await snapshot( s.page );
		} else {
			await sleep( DEPART_MS );
			res.instantane = await snapshot( s.page );
			await s.page.goto( 'about:blank' );
			await sleep( 500 );
		}
		const sent = log.sent.filter( ( e ) => e.phase === phase );
		res.envoyes = sent;
		res.bibliotheque = log.lib.filter( ( e ) => e.phase === phase );
		res.attendus = EXPECTED[ scenario ];
		res.perdus = EXPECTED[ scenario ].filter( ( ev ) => ! sent.some( ( e ) => e.ev === ev ) );
	} catch ( e ) {
		res.erreur = String( e && e.message ? e.message : e ).slice( 0, 300 );
	}
	res.horsClone = s.blocked;
	await s.context.close();
	return res;
}

( async () => {
	const browser = await launch();
	const out = {
		test: 'T3.5',
		hypothese: 'H-N6',
		date: new Date().toISOString(),
		condition: COND.label,
		reseau: COND.net,
		modele: MODEL.label + ' ; page suivante du même contexte : 0 ms (cache HTTP)',
		delaiBibliothequeSimuleMs: LIB_DELAY,
		delaiConfigSimuleMs: CONFIG_DELAY,
		departMsApresAffichage: DEPART_MS,
		essais: [],
	};
	for ( const scenario of [ 'fiche', 'ajout' ] ) {
		out.essais.push( await runTrial( browser, scenario, 0, true ) );
		writeProof( PROOF, out );
		for ( let i = 1; i <= TRIALS; i++ ) {
			out.essais.push( await runTrial( browser, scenario, i, false ) );
			writeProof( PROOF, out );
		}
	}
	out.synthese = {};
	for ( const scenario of [ 'fiche', 'ajout' ] ) {
		const runs = out.essais.filter( ( r ) => r.scenario === scenario && 'number' === typeof r.essai );
		const attendus = runs.reduce( ( n, r ) => n + ( r.attendus ? r.attendus.length : 0 ), 0 );
		const perdus = runs.reduce( ( n, r ) => n + ( r.perdus ? r.perdus.length : 0 ), 0 );
		const keyEv = 'ajout' === scenario ? 'AddToCart' : 'ViewContent';
		out.synthese[ scenario ] = {
			essais: runs.length,
			erreurs: runs.filter( ( r ) => r.erreur ).length,
			evenementsAttendus: attendus,
			evenementsPerdus: perdus,
			partPerdue: attendus ? Math.round( ( 1000 * perdus ) / attendus ) / 10 + ' %' : null,
			[ keyEv + 'Perdus' ]: runs.filter( ( r ) => r.perdus && r.perdus.includes( keyEv ) ).length + ' / ' + runs.length,
		};
	}
	writeProof( PROOF, out );
	console.log( JSON.stringify( { condition: COND.label, modele: MODEL_KEY, delaiBibliotheque: LIB_DELAY, delaiConfig: CONFIG_DELAY, synthese: out.synthese, temoins: out.essais.filter( ( r ) => 'string' === typeof r.essai ).map( ( r ) => ( { scenario: r.scenario, toutParti: r.toutParti, instantane: r.instantane, envoyes: ( r.envoyes || [] ).map( ( e ) => e.ev + '@' + e.envoyeMs ), erreur: r.erreur } ) ) }, null, 1 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
