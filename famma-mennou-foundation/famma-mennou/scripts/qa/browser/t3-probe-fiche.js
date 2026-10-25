/**
 * Lot 3 — repérage : boutons visibles de la fiche (mobile 412 px) et champs
 * du formulaire express. Aucune action d'écriture.
 */
'use strict';

const { BASE, launch, cleanPage, writeProof } = require( './qa-lib' );

( async () => {
	const browser = await launch();
	const s = await cleanPage( browser );
	const out = {};
	for ( const slug of [ 'qa-simple-sku', 'qa-tailles', 'qa-tailles-famma' ] ) {
		await s.page.goto( BASE + '/produit/' + slug + '/', { waitUntil: 'load' } );
		out[ slug ] = await s.page.evaluate( () => {
			const vis = ( el ) => {
				const r = el.getBoundingClientRect();
				const cs = getComputedStyle( el );
				return r.width > 0 && r.height > 0 && 'hidden' !== cs.visibility && 'none' !== cs.display;
			};
			const describe = ( el ) => ( {
				tag: el.tagName.toLowerCase(),
				cls: el.className,
				name: el.getAttribute( 'name' ),
				value: el.getAttribute( 'value' ),
				type: el.getAttribute( 'type' ),
				visible: vis( el ),
				text: ( el.textContent || '' ).trim().slice( 0, 60 ),
				form: el.form ? el.form.className : null,
			} );
			return {
				buttons: Array.from( document.querySelectorAll( 'button, a.button' ) ).filter( ( b ) => /cart|express|submit|commander/i.test( b.className + ' ' + b.name ) ).map( describe ),
				expressFields: Array.from( document.querySelectorAll( '.famma-express__form input, .famma-express__form select' ) ).map( describe ),
				cartFormFields: Array.from( document.querySelectorAll( 'form.cart input, form.cart select' ) ).map( describe ),
			};
		} );
	}
	writeProof( 'probe-fiche.json', out );
	console.log( JSON.stringify( out, null, 1 ).slice( 0, 6000 ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
