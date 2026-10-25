/**
 * Lot 3 — repérage du formulaire express : remplit (QA-L3, fictif), clique
 * « Confirmer » et relève messages, champs invalides et requêtes wc-ajax.
 * La requête famma_express_checkout est interrompue : aucune commande.
 */
'use strict';

const { BASE, launch, cleanPage, writeProof, sleep, fillExpress } = require( './qa-lib' );

( async () => {
	const browser = await launch();
	const c = await cleanPage( browser, {
		onRequest: ( req ) => {
			if ( req.url().includes( 'wc-ajax=famma_express_checkout' ) ) {
				req.abort( 'blockedbyclient' );
				return true;
			}
			return false;
		},
	} );
	const ajax = [];
	c.page.on( 'response', async ( res ) => {
		if ( res.url().includes( 'wc-ajax=' ) ) {
			let body = '';
			try {
				body = ( await res.text() ).slice( 0, 400 );
			} catch ( e ) {
				body = 'n/a';
			}
			ajax.push( { url: res.url().replace( BASE, '' ), status: res.status(), body } );
		}
	} );
	await c.page.goto( BASE + '/produit/qa-simple-sku/', { waitUntil: 'load' } );
	await fillExpress( c.page, { firstName: 'QA-L3 Express Sonde', phone: '29000301', stateLabel: 'Sousse' } );
	const before = await c.page.evaluate( () => {
		const f = document.querySelector( '.famma-express__form' );
		return Array.from( f.elements ).filter( ( e ) => e.name ).map( ( e ) => e.name + '=' + ( 'select-one' === e.type ? e.value : e.value ) );
	} );
	await c.page.click( '.famma-express__submit' );
	await sleep( 6000 );
	const after = await c.page.evaluate( () => ( {
		notices: ( document.querySelector( '.famma-express__notices' ) || {} ).textContent || '',
		info: ( document.querySelector( '.famma-express__info' ) || {} ).textContent || '',
		invalid: Array.from( document.querySelectorAll( '.famma-express__form [aria-invalid="true"], .famma-express__form .is-invalid' ) ).map( ( e ) => e.name || e.className ),
		errors: Array.from( document.querySelectorAll( '.famma-express__error' ) ).map( ( e ) => e.textContent.trim() ).filter( Boolean ),
		submitText: ( document.querySelector( '.famma-express__submit' ) || {} ).textContent.trim(),
		invalidNative: Array.from( document.querySelectorAll( '.famma-express__form :invalid' ) ).map( ( e ) => ( { tag: e.tagName, name: e.name, cls: e.className, msg: e.validationMessage, val: e.value } ) ),
		combo: Array.from( document.querySelectorAll( '.famma-express__form input:not([name])' ) ).map( ( e ) => ( { cls: e.className, val: e.value, req: e.required, role: e.getAttribute( 'role' ) } ) ),
		hit: ( () => {
			const b = document.querySelector( '.famma-express__submit' );
			const r = b.getBoundingClientRect();
			const el = document.elementFromPoint( r.left + r.width / 2, r.top + r.height / 2 );
			return el ? el.tagName + '.' + el.className : null;
		} )(),
		qa: window.__qaFbq,
	} ) );
	writeProof( 'probe-express.json', { before, after, ajax, console: c.console, blocked: c.blocked } );
	console.log( JSON.stringify( { before, after, ajax, console: c.console }, null, 1 ).slice( 0, 5000 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
