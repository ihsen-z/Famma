/**
 * Commande express : préparer le panier, vérifier le récapitulatif, confirmer.
 *
 * Ce script n'invente aucun parcours. Il prépare puis envoie :
 *
 *   0. `?wc-ajax=famma_express_nonce`    — dès la première interaction avec le
 *      formulaire, en parallèle de la saisie : jeton de checkout et session ;
 *   1. `?wc-ajax=famma_express_prepare` — prépare le vrai panier et son total ;
 *   2. `?wc-ajax=famma_express_checkout` — confirme la même intention une fois ;
 *   3. `?wc-ajax=famma_express_status` — retrouve un résultat après interruption.
 *
 * La commande, ses validations et son statut restent donc entièrement l'affaire
 * de WooCommerce. En cas d'échec, ce sont ses propres messages qui s'affichent :
 * « numéro de téléphone invalide » vient de `Tunisia::validate_phone()`, pas
 * d'un contrôle réécrit ici.
 *
 * Aucune commande n'est renvoyée automatiquement après une erreur réseau ou
 * HTTP : le client voit le message d'erreur et décide. Seul cas de renvoi : le
 * serveur répond `famma_token: expired`, ce qu'il fait AVANT toute écriture —
 * un nouveau jeton est alors demandé, et la commande renvoyée une seule fois.
 *
 * Sans JavaScript, la validation est inerte — mais « Ajouter au panier », à
 * côté d'elle, poste encore le produit vers le panier de WooCommerce, et le
 * parcours classique (panier puis checkout) reste intact. C'est la raison pour
 * laquelle le formulaire de WooCommerce n'a pas été supprimé, seulement rangé.
 */
( function () {
	'use strict';

	var form = document.querySelector( '.famma-express__form' );

	if ( ! form || 'undefined' === typeof fammaExpress ) {
		return;
	}

	var button = form.querySelector( '.famma-express__submit' );
	var notices = form.querySelector( '.famma-express__notices' );
	var busy = false;

	var qtyInput = form.querySelector( '[name="famma_quantity"]' );
	var totalNode = form.querySelector( '.famma-express__total' );
	var phoneInput = form.querySelector( '[name="billing_phone"]' );
	var phoneError = phoneInput ? document.getElementById( phoneInput.getAttribute( 'aria-describedby' ) ) : null;

	/**
	 * Met un montant au format de la boutique (décimales, séparateurs, symbole).
	 *
	 * @param {number} amount Montant.
	 * @return {string} Montant formaté, en texte brut.
	 */
	function formatPrice( amount ) {
		var p = fammaExpress.price || {};
		var decimals = parseInt( p.decimals, 10 ) || 0;
		var parts = amount.toFixed( decimals ).split( '.' );

		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, p.thousand || '' );

		var number = parts.join( p.decimal || ',' );
		var format = p.format || '%2$s %1$s';

		return format
			.replace( '%1$s', p.symbol || '' )
			.replace( '%2$s', number )
			.replace( /&nbsp;/g, ' ' );
	}

	/**
	 * Quantité courante, bornée par les attributs du champ.
	 *
	 * @return {number}
	 */
	function currentQuantity() {
		if ( ! qtyInput ) {
			return 1;
		}

		var min = parseInt( qtyInput.getAttribute( 'min' ), 10 ) || 1;
		var max = parseInt( qtyInput.getAttribute( 'max' ), 10 ) || Infinity;
		var value = parseInt( qtyInput.value, 10 );

		if ( isNaN( value ) || value < min ) {
			value = min;
		}

		return Math.min( value, max );
	}

	/**
	 * Recalcule le total et aligne la quantité du bouton « Ajouter au panier ».
	 *
	 * Le client ne voit qu'un sélecteur de quantité : celui du panier est rangé
	 * par le thème, mais il doit porter la même valeur, sinon « Ajouter au
	 * panier » ajouterait 1 article quand le client en a choisi 3.
	 *
	 * @return {void}
	 */
	var quotedQuantity;

	function syncQuantity() {
		var quantity = currentQuantity();

		if ( quantity !== quotedQuantity ) {
			quotedQuantity = quantity;
			requote();
		}
		renderQuantity();
	}

	/**
	 * Reporte la quantité sur le total affiché et sur le formulaire WooCommerce,
	 * sans toucher au devis : le récapitulatif l'utilise après une modification
	 * de ligne, déjà validée par le serveur.
	 *
	 * @return {void}
	 */
	function renderQuantity() {
		var quantity = currentQuantity();

		if ( totalNode ) {
			var unit = parseFloat( totalNode.getAttribute( 'data-unit-price' ) || '0' );
			totalNode.textContent = formatPrice( unit * quantity );
		}

		var cartQty = document.querySelector( 'form.cart input.qty' );

		if ( cartQty && String( quantity ) !== cartQty.value ) {
			cartQty.value = String( quantity );
			cartQty.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	}

	form.addEventListener( 'click', function ( event ) {
		var step = event.target.closest( '[data-famma-express-step]' );

		if ( ! step || ! qtyInput ) {
			return;
		}

		event.preventDefault();

		var next = currentQuantity() + parseInt( step.getAttribute( 'data-famma-express-step' ), 10 );
		var min = parseInt( qtyInput.getAttribute( 'min' ), 10 ) || 1;
		var max = parseInt( qtyInput.getAttribute( 'max' ), 10 ) || Infinity;

		qtyInput.value = String( Math.max( min, Math.min( next, max ) ) );
		syncQuantity();
	} );

	if ( qtyInput ) {
		qtyInput.addEventListener( 'input', syncQuantity );
		qtyInput.addEventListener( 'change', syncQuantity );
	}

	/*
	 * « Ajouter au panier », à côté de la validation. Le clic est transmis au
	 * bouton du formulaire de WooCommerce, rangé par le thème : c'est lui qui
	 * porte la quantité synchronisée et le parcours standard du panier.
	 *
	 * S'il manque, ce formulaire-ci part tel quel vers la page, avec
	 * `add-to-cart` et une quantité ajoutée à la volée ; `cartSubmit` écarte
	 * alors le traitement de commande express ci-dessous.
	 */
	var cartSubmit = false;
	var cartButton = form.querySelector( '.famma-express__cart' );

	if ( cartButton ) {
		cartButton.addEventListener( 'click', function ( event ) {
			var wooForm = document.querySelector( 'form.cart' );
			var wooButton = wooForm ? wooForm.querySelector( 'button[name="add-to-cart"]' ) : null;

			syncQuantity();

			if ( wooButton ) {
				event.preventDefault();

				if ( 'function' === typeof wooForm.requestSubmit ) {
					wooForm.requestSubmit( wooButton );
				} else {
					wooButton.click();
				}

				return;
			}

			var quantity = form.querySelector( 'input[name="quantity"]' );

			if ( ! quantity ) {
				quantity = document.createElement( 'input' );
				quantity.type = 'hidden';
				quantity.name = 'quantity';
				form.appendChild( quantity );
			}

			quantity.value = String( currentQuantity() );
			cartSubmit = true;
		} );
	}

	/**
	 * Le numéro ressemble-t-il à un numéro tunisien ?
	 *
	 * Même règle que `Tunisia::validate_phone()` : espaces, points et tirets
	 * tolérés, préfixe +216 ou 00216 facultatif, puis 8 chiffres. Le serveur
	 * reste juge ; ce contrôle évite seulement d'attendre l'envoi pour le savoir.
	 *
	 * @param {string} value Saisie brute.
	 * @return {boolean}
	 */
	function plausiblePhone( value ) {
		var digits = value.replace( /[\s.\-]/g, '' ).replace( /^(?:\+?216|00216)/, '' );

		return /^[2-9]\d{7}$/.test( digits );
	}

	/**
	 * Affiche ou retire l'erreur du téléphone.
	 *
	 * `setCustomValidity` fait entrer l'erreur dans `reportValidity()` : le
	 * formulaire ne part pas avec un numéro manifestement faux.
	 *
	 * @param {boolean} strict Vrai à la sortie du champ et à l'envoi.
	 * @return {boolean} Vrai si le numéro est acceptable.
	 */
	function checkPhone( strict ) {
		if ( ! phoneInput ) {
			return true;
		}

		var value = phoneInput.value.trim();
		var ok = '' === value || plausiblePhone( value );

		// Pendant la frappe, on ne signale rien : on se contente d'effacer l'erreur dès qu'elle est corrigée.
		if ( ! strict && ! ok ) {
			return ok;
		}

		phoneInput.setCustomValidity( ok ? '' : fammaExpress.phoneError );
		phoneInput.setAttribute( 'aria-invalid', ok ? 'false' : 'true' );

		if ( phoneError ) {
			phoneError.textContent = ok ? '' : fammaExpress.phoneError;
			phoneError.hidden = ok;
		}

		return ok;
	}

	if ( phoneInput ) {
		phoneInput.addEventListener( 'blur', function () {
			checkPhone( true );
		} );
		phoneInput.addEventListener( 'input', function () {
			checkPhone( false );
		} );
	}

	/**
	 * Affiche un message d'échec au-dessus du bouton.
	 *
	 * @param {string} html Message, déjà mis en forme par WooCommerce.
	 * @return {void}
	 */
	function showError( html ) {
		if ( ! notices ) {
			return;
		}

		notices.innerHTML = html;

		/*
		 * Le formulaire est long : sur mobile, le message atterrit souvent
		 * hors écran, et le client croit que rien ne s'est passé.
		 */
		notices.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}

	/**
	 * Rend le bouton à son état d'origine.
	 *
	 * @return {void}
	 */
	function release() {
		busy = false;
		if ( button ) {
			button.disabled = false;
		}
		if ( ! unresolved() ) {
			unlockFields();
		}
		label();
	}

	/**
	 * Émet un événement Meta, si le Pixel est présent.
	 *
	 * @param {string} name   Nom de l'événement standard.
	 * @param {Object} params Paramètres de l'événement.
	 * @return {void}
	 */
	function pixel( name, params, id ) {
		if ( ! window.fbq || ! id || ! params ) {
			return false;
		}
		try {
			window.fbq( 'track', name, params, { eventID: id } );
			return true;
		} catch ( error ) {
			// A third-party tracking failure must never block a valid checkout.
			return false;
		}
	}

	/**
	 * Poste un jeu de données à un point d'entrée `wc-ajax`.
	 *
	 * `credentials: 'same-origin'` est indispensable : sans les cookies, la
	 * session WooCommerce de la commande ne serait pas celle pour laquelle le
	 * jeton a été calculé, et le checkout le refuserait.
	 *
	 * @param {string}   url  Point d'entrée.
	 * @param {FormData} data Données à poster.
	 * @return {Promise<Object>} Réponse décodée.
	 */
	function post( url, data ) {
		return fetch( url, {
			method: 'POST',
			body: data,
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		} ).then( function ( response ) {
			return response.text();
		} ).then( function ( text ) {
			/*
			 * WooCommerce répond en JSON, mais une extension bavarde ou un
			 * avertissement PHP peut le précéder. Plutôt que d'abandonner, on
			 * récupère le JSON là où il commence — c'est ce que fait le script
			 * de checkout de WooCommerce lui-même.
			 */
			try {
				return JSON.parse( text );
			} catch ( e ) {
				var start = text.indexOf( '{' );
				var end = text.lastIndexOf( '}' );

				if ( start > -1 && end > start ) {
					try {
						return JSON.parse( text.slice( start, end + 1 ) );
					} catch ( inner ) {
						return null;
					}
				}

				return null;
			}
		} );
	}

	/*
	 * Jeton de checkout, demandé une seule fois et partagé. La requête part à
	 * la première interaction avec le formulaire : le temps que le client le
	 * remplisse, la réponse est arrivée, et le clic n'attend plus qu'un appel.
	 * Voir `Express_Order::send_nonce()`.
	 */
	var tokenRequest = null;

	/**
	 * Renvoie le jeton de checkout, en le demandant si besoin.
	 *
	 * @param {boolean} fresh Vrai pour ignorer le jeton déjà obtenu.
	 * @return {Promise<string>} Jeton, ou chaîne vide en cas d'échec.
	 */
	function checkoutToken( fresh ) {
		if ( tokenRequest && ! fresh ) {
			return tokenRequest;
		}

		tokenRequest = post( fammaExpress.nonceUrl, new FormData() ).then( function ( token ) {
			if ( token && token.success && token.data && token.data.nonce ) {
				return String( token.data.nonce );
			}

			tokenRequest = null;
			return '';
		} ).catch( function () {
			tokenRequest = null;
			return '';
		} );

		return tokenRequest;
	}

	/**
	 * Prépare le jeton à la première interaction, une seule fois.
	 *
	 * @return {void}
	 */
	function prepareToken() {
		form.removeEventListener( 'focusin', prepareToken );
		form.removeEventListener( 'pointerdown', prepareToken );
		checkoutToken( false );
	}

	form.addEventListener( 'focusin', prepareToken );
	form.addEventListener( 'pointerdown', prepareToken );

	var recap = form.querySelector( '.famma-express__recap' );
	var recapContent = form.querySelector( '.famma-express__recap-content' );
	var productId = form.querySelector( '[name="famma_product_id"]' ).value;
	var storageKey = 'famma_express_intent_' + productId;
	var record = null;
	var storageReady = false;
	var quoted = false;
	var quoting = null;
	var requoteTimer = 0;
	var requoteAgain = false;
	var lastSummary = null;
	var pendingTracking = null;
	var lockedFields = [];
	var emitted = {};

	/** Secure IDs and session persistence are required before final submission. */
	function uuid() {
		if ( ! window.crypto ) {
			return '';
		}
		if ( window.crypto.randomUUID ) {
			return window.crypto.randomUUID();
		}
		if ( ! window.crypto.getRandomValues ) {
			return '';
		}
		var bytes = new Uint8Array( 16 );
		window.crypto.getRandomValues( bytes );
		bytes[ 6 ] = ( bytes[ 6 ] & 15 ) | 64;
		bytes[ 8 ] = ( bytes[ 8 ] & 63 ) | 128;
		var hex = Array.prototype.map.call( bytes, function ( value ) {
			return ( '0' + value.toString( 16 ) ).slice( -2 );
		} ).join( '' );
		return hex.slice( 0, 8 ) + '-' + hex.slice( 8, 12 ) + '-' + hex.slice( 12, 16 ) + '-' + hex.slice( 16, 20 ) + '-' + hex.slice( 20 );
	}

	function saveRecord() {
		try {
			window.sessionStorage.setItem( storageKey, JSON.stringify( record ) );
			storageReady = window.sessionStorage.getItem( storageKey ) === JSON.stringify( record );
		} catch ( error ) {
			storageReady = false;
		}
		return storageReady;
	}

	function newRecord() {
		var requestId = uuid();
		var preparationId = uuid();
		if ( ! requestId || ! preparationId ) {
			storageReady = false;
			return false;
		}
		record = { version: 1, product: productId, request_id: requestId, preparation_id: preparationId, phase: 'draft', cart_hash: '', events: [] };
		return saveRecord();
	}

	function unresolved() {
		return record && ( 'submitted' === record.phase || 'unknown' === record.phase || 'processing' === record.phase );
	}

	function label() {
		if ( button ) {
			button.textContent = unresolved() ? fammaExpress.retryLabel : fammaExpress.confirmLabel;
		}
	}

	function lockFields() {
		if ( lockedFields.length ) {
			return;
		}
		Array.prototype.forEach.call( form.querySelectorAll( 'input, select, textarea, button' ), function ( field ) {
			if ( field !== button ) {
				lockedFields.push( { field: field, disabled: field.disabled } );
				field.disabled = true;
			}
		} );
	}

	function unlockFields() {
		lockedFields.forEach( function ( entry ) { entry.field.disabled = entry.disabled; } );
		lockedFields = [];
	}

	function message( text ) {
		if ( notices ) {
			notices.textContent = text || fammaExpress.genericError;
			notices.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
	}

	/** Never generate a new intent while a previous final result is unknown. */
	function invalidateQuote() {
		if ( busy || unresolved() || ! record ) {
			return;
		}
		quoted = false;
		record.preparation_id = uuid();
		record.cart_hash = '';
		record.phase = 'draft';
		saveRecord();
		label();
	}

	function stateChosen() {
		var state = form.querySelector( '[name="billing_state"]' );
		return !! ( state && state.value );
	}

	/**
	 * Le récapitulatif se prépare tout seul dès que le gouvernorat est choisi,
	 * puis à chaque changement de quantité ou de gouvernorat : le client n'a
	 * qu'un bouton, « Confirmer ». Seuls ces deux champs comptent pour le panier ;
	 * le nom et le téléphone n'invalident rien.
	 */
	function requote() {
		invalidateQuote();
		if ( busy || unresolved() || ! record ) {
			return;
		}
		window.clearTimeout( requoteTimer );
		if ( ! stateChosen() ) {
			recap.hidden = true;
			return;
		}
		recap.setAttribute( 'aria-busy', 'true' );
		requoteTimer = window.setTimeout( autoQuote, 350 );
	}

	/** Une seule préparation à la fois : le panier serveur n'accepte pas deux écritures concurrentes. */
	function autoQuote() {
		requoteTimer = 0;
		if ( quoting ) {
			requoteAgain = true;
			return quoting;
		}
		if ( busy || editing || unresolved() || ! record || quoted || ! stateChosen() ) {
			return Promise.resolve();
		}
		quoting = prepare( true ).catch( function () {
			// Silencieux : le clic sur « Confirmer » réessaie.
			return null;
		} ).then( function () {
			quoting = null;
			if ( requoteAgain && ! busy ) {
				requoteAgain = false;
				if ( ! quoted ) {
					return autoQuote();
				}
			}
			requoteAgain = false;
			recap.setAttribute( 'aria-busy', 'false' );
		} );
		return quoting;
	}

	form.addEventListener( 'change', function ( event ) {
		if ( event.target && 'billing_state' === event.target.name ) {
			requote();
		}
	} );

	/** Use the exact server acknowledgement and event ID, including replays. */
	function acknowledgedEvents( tracking ) {
		if ( ! tracking ) {
			return;
		}
		[ [ 'AddToCart', tracking.add_to_cart ], [ 'InitiateCheckout', tracking.initiate_checkout ] ].forEach( function ( entry ) {
			var data = entry[ 1 ];
			if ( ! data || false === data.emit || ! data.event_id || emitted[ data.event_id ] || ! window.fbq || ( 'InitiateCheckout' === entry[ 0 ] && record.checkout_emitted ) ) {
				return;
			}
			if ( ! pixel( entry[ 0 ], data.params, data.event_id ) ) {
				return;
			}
			if ( 'InitiateCheckout' === entry[ 0 ] ) {
				record.checkout_emitted = true;
			}
			emitted[ data.event_id ] = true;
			record.events.push( data.event_id );
			record.events = record.events.slice( -100 );
			saveRecord();
		} );
	}

	function cell( row, text, price, heading ) {
		var node = document.createElement( heading ? 'th' : 'td' );
		if ( price ) {
			// These amount fragments come only from WooCommerce's server recap.
			node.innerHTML = text;
		} else {
			node.textContent = String( text );
		}
		if ( heading ) {
			node.setAttribute( 'scope', 'col' );
		}
		row.appendChild( node );
		return node;
	}

	var editing = false;
	var recapButtons = [];

	function recapButton( className, label, text, onClick ) {
		var node = document.createElement( 'button' );
		node.type = 'button';
		node.className = className;
		node.setAttribute( 'aria-label', label );
		node.textContent = text;
		node.addEventListener( 'click', onClick );
		recapButtons.push( node );
		return node;
	}

	/** Les lignes éditables viennent du serveur : clé de ligne, quantité, maximum. */
	function quantityControl( item ) {
		var names = fammaExpress.recapLabels;
		var wrap = document.createElement( 'div' );
		var value = document.createElement( 'span' );
		var less = recapButton( 'famma-express__qty-btn', String( names.decrease ).replace( '%s', item.name ), '−', function () {
			editCart( item, 'set', item.quantity - 1, 'less' );
		} );
		var more = recapButton( 'famma-express__qty-btn', String( names.increase ).replace( '%s', item.name ), '+', function () {
			editCart( item, 'set', item.quantity + 1, 'more' );
		} );

		wrap.className = 'famma-express__qty famma-express__qty--line';
		value.className = 'famma-express__qty-value';
		value.textContent = String( item.quantity );
		less.disabled = item.quantity <= 1;
		more.disabled = item.max > 0 && item.quantity >= item.max;
		less.setAttribute( 'data-recap', item.key + ':less' );
		more.setAttribute( 'data-recap', item.key + ':more' );
		wrap.appendChild( less );
		wrap.appendChild( value );
		wrap.appendChild( more );
		return wrap;
	}

	function renderSummary( summary, focus ) {
		if ( ! recap || ! recapContent || ! summary || ! Array.isArray( summary.items ) || ! summary.total_html ) {
			throw new Error( 'Incomplete recap' );
		}
		var names = fammaExpress.recapLabels;
		var table = document.createElement( 'table' );
		var head = document.createElement( 'thead' );
		var title = document.createElement( 'tr' );
		var target = null;
		recapButtons = [];
		cell( title, names.product, false, true );
		cell( title, names.quantity, false, true );
		cell( title, names.amount, false, true );
		head.appendChild( title );
		table.appendChild( head );
		var body = document.createElement( 'tbody' );
		summary.items.forEach( function ( item ) {
			var row = document.createElement( 'tr' );
			var nameCell = cell( row, item.name, false );
			if ( item.key ) {
				var remove = recapButton( 'famma-express__remove', String( names.remove ).replace( '%s', item.name ), names.removeText, function () {
					editCart( item, 'remove', 0, 'remove' );
				} );
				remove.setAttribute( 'data-recap', item.key + ':remove' );
				nameCell.appendChild( remove );
				var qtyCell = cell( row, '', false );
				qtyCell.appendChild( quantityControl( item ) );
			} else {
				cell( row, item.quantity, false );
			}
			cell( row, item.line_total_html, true );
			body.appendChild( row );
		} );
		table.appendChild( body );
		var foot = document.createElement( 'tfoot' );
		// Une ligne à zéro (réduction, livraison, frais, taxes) n'apprend rien au client : le serveur ne l'envoie pas.
		[ 'subtotal', 'discount', 'shipping', 'fees', 'tax', 'total' ].forEach( function ( key ) {
			if ( ! summary[ key + '_html' ] ) {
				return;
			}
			var row = document.createElement( 'tr' );
			cell( row, names[ key ], false );
			row.children[ 0 ].setAttribute( 'colspan', '2' );
			cell( row, summary[ key + '_html' ], true );
			foot.appendChild( row );
		} );
		table.appendChild( foot );
		recapContent.innerHTML = '';
		recapContent.appendChild( table );
		recap.hidden = false;
		lastSummary = summary;
		if ( false === focus ) {
			return;
		}
		recapButtons.forEach( function ( node ) {
			if ( focus && node.getAttribute( 'data-recap' ) === focus && ! node.disabled ) {
				target = node;
			}
		} );
		if ( 'string' === typeof focus ) {
			// Modification en place : le focus reste sur le bouton utilisé (ou sur le bloc), la page ne bouge pas.
			( target || recap ).focus( { preventScroll: true } );
			return;
		}
		recap.focus();
		recap.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}

	var busyStates = [];

	function setRecapBusy( on ) {
		editing = on;
		if ( recap ) {
			recap.setAttribute( 'aria-busy', on ? 'true' : 'false' );
		}
		if ( on ) {
			busyStates = recapButtons.map( function ( node ) { return node.disabled; } );
			recapButtons.forEach( function ( node ) { node.disabled = true; } );
		} else {
			recapButtons.forEach( function ( node, index ) { node.disabled = !! busyStates[ index ]; } );
			busyStates = [];
		}
	}

	/** Le panier a changé ailleurs, ou la préparation a expiré : un nouveau récapitulatif est nécessaire. */
	function requireNewRecap( text ) {
		record.phase = 'draft';
		record.preparation_id = uuid();
		record.cart_hash = '';
		delete record.fields;
		saveRecord();
		quoted = false;
		recap.hidden = true;
		label();
		message( text );
	}

	/**
	 * Retire une ligne ou change sa quantité dans le vrai panier.
	 *
	 * Le serveur contrôle stock, maximum et empreinte du panier, puis renvoie le
	 * récapitulatif exact : le client n'affiche jamais un montant qu'il a calculé.
	 *
	 * @param {Object} item     Ligne du récapitulatif.
	 * @param {string} action   `set` ou `remove`.
	 * @param {number} quantity Nouvelle quantité (ignorée pour `remove`).
	 * @param {string} focus    Bouton à refocaliser après la mise à jour.
	 */
	function editCart( item, action, quantity, focus ) {
		if ( busy || editing || ! quoted || unresolved() || ! record || ! item.key ) {
			return;
		}
		var state = form.querySelector( '[name="billing_state"]' );
		var trap = form.querySelector( '[name="famma_hp_site"]' );
		var formQuantity = currentQuantity();
		var isMain = String( item.product_id ) === String( productId );
		if ( isMain && 'set' === action ) {
			formQuantity = quantity;
		}
		setRecapBusy( true );
		request( fammaExpress.cartUrl, [
			[ 'famma_preparation_id', record.preparation_id ], [ 'famma_cart_hash', record.cart_hash ],
			[ 'famma_cart_key', item.key ], [ 'famma_cart_action', action ], [ 'famma_cart_quantity', String( quantity ) ],
			[ 'famma_product_id', productId ], [ 'famma_quantity', String( formQuantity ) ], [ 'billing_country', 'TN' ],
			[ 'billing_state', state ? state.value : '' ], [ 'famma_hp_site', trap ? trap.value : '' ],
		], true ).then( function ( result ) {
			setRecapBusy( false );
			if ( result && 'success' === result.result && result.summary && result.cart_hash && result.preparation_id === record.preparation_id ) {
				record.cart_hash = result.cart_hash;
				saveRecord();
				if ( isMain && qtyInput ) {
					// Le formulaire suit le récapitulatif ; la signature de la préparation a été mise à jour côté serveur.
					qtyInput.value = String( 'remove' === action ? formQuantity : quantity );
					quotedQuantity = currentQuantity();
					renderQuantity();
				}
				if ( ! result.summary.items.length ) {
					requireNewRecap( fammaExpress.cartEmpty );
					return;
				}
				renderSummary( result.summary, 'remove' === action ? '' : item.key + ':' + focus );
				return;
			}
			if ( result && ( 'cart_changed' === result.famma_code || 'preparation_missing' === result.famma_code ) ) {
				requireNewRecap( fammaExpress.changed );
				return;
			}
			if ( result && result.summary && result.cart_hash && result.cart_hash === record.cart_hash ) {
				// Refus avant toute modification (stock, maximum) : le récapitulatif reste valable.
				renderSummary( result.summary, item.key + ':' + focus );
				message( fammaExpress.cartRefused );
				return;
			}
			requireNewRecap( fammaExpress.cartRefused );
		} ).catch( function () {
			setRecapBusy( false );
			message( fammaExpress.genericError );
		} );
	}

	/** Refresh only the nonce; IDs and final payload are never regenerated. */
	function request( url, fields, canRetry ) {
		return checkoutToken( false ).then( function ( token ) {
			if ( ! token ) {
				return null;
			}
			var data = new FormData();
			fields.forEach( function ( entry ) { data.append( entry[ 0 ], entry[ 1 ] ); } );
			data.append( 'woocommerce-process-checkout-nonce', token );
			return post( url, data );
		} ).then( function ( result ) {
			if ( result && 'expired' === result.famma_token && canRetry ) {
				return checkoutToken( true ).then( function ( fresh ) {
					return fresh ? request( url, fields, false ) : null;
				} );
			}
			return result;
		} );
	}

	function snapshot() {
		var fields = [];
		new FormData( form ).forEach( function ( value, name ) {
			if ( 'woocommerce-process-checkout-nonce' !== name && 'string' === typeof value ) {
				fields.push( [ name, value ] );
			}
		} );
		return fields;
	}

	function prepare( auto ) {
		var id = record.preparation_id;
		var state = form.querySelector( '[name="billing_state"]' );
		var trap = form.querySelector( '[name="famma_hp_site"]' );
		return request( fammaExpress.prepareUrl, [
			[ 'famma_product_id', productId ], [ 'famma_quantity', String( currentQuantity() ) ],
			[ 'famma_preparation_id', id ], [ 'billing_country', 'TN' ],
			[ 'billing_state', state ? state.value : '' ], [ 'famma_hp_site', trap ? trap.value : '' ],
		], true ).then( function ( result ) {
			if ( id !== record.preparation_id ) {
				// La quantité ou le gouvernorat a changé pendant la requête : réponse périmée, ignorée.
				return;
			}
			if ( result && 'success' === result.result && result.summary && result.cart_hash && result.preparation_id === id ) {
				renderSummary( result.summary, false );
				record.cart_hash = result.cart_hash;
				record.phase = 'quoted';
				quoted = true;
				pendingTracking = result.tracking || null;
				if ( 'number' !== typeof record.base_prev && pendingTracking && pendingTracking.add_to_cart && 'number' === typeof pendingTracking.add_to_cart.previous ) {
					record.base_prev = pendingTracking.add_to_cart.previous;
				}
				saveRecord();
			} else if ( ! auto && result && result.messages ) {
				showError( result.messages );
			} else if ( ! auto ) {
				message( fammaExpress.genericError );
			}
		} );
	}

	/** Chiffres seuls d'un montant HTML : « 231,000&nbsp;DT » devient « 231000 ». */
	function digits( html ) {
		return String( html || '' ).replace( /<[^>]*>/g, '' ).replace( /&[a-z0-9#]+;/gi, '' ).replace( /\D/g, '' );
	}

	/** Ce que le client a sous les yeux avant de cliquer : le total de la page, ou celui du récapitulatif s'il y a plusieurs articles. */
	function expectedTotal() {
		var multiple = lastSummary && ( lastSummary.items.length > 1 || ( lastSummary.items[ 0 ] && String( lastSummary.items[ 0 ].product_id ) !== String( productId ) ) );
		if ( multiple ) {
			return digits( lastSummary.total_html );
		}
		return totalNode ? digits( totalNode.textContent ) : '';
	}

	/** AddToCart sur la quantité réellement ajoutée depuis le premier récapitulatif, pas sur le dernier delta. */
	function clickTracking() {
		var t = pendingTracking;
		var add = t && t.add_to_cart;
		if ( ! add || ! add.params || 'number' !== typeof add.unit || 'number' !== typeof record.base_prev ) {
			return t;
		}
		var delta = Math.max( 0, currentQuantity() - record.base_prev );
		var params = JSON.parse( JSON.stringify( add.params ) );
		var scale = Math.pow( 10, ( fammaExpress.price && fammaExpress.price.decimals ) || 3 );
		params.value = Math.round( delta * add.unit * scale ) / scale;
		if ( params.contents && params.contents[ 0 ] ) {
			params.contents[ 0 ].quantity = delta;
		}
		return { add_to_cart: { emit: delta > 0, event_id: add.event_id, params: params }, initiate_checkout: t.initiate_checkout };
	}

	/**
	 * Un seul clic : le récapitulatif est déjà prêt, ou il est préparé ici, puis
	 * la commande part. Si le montant frais n'est pas celui que le client voyait,
	 * on s'arrête sur le nouveau récapitulatif plutôt que de commander à l'aveugle.
	 *
	 * @param {Array} fields Champs du formulaire, lus avant le verrouillage.
	 */
	function confirmFlow( fields ) {
		var expected = expectedTotal();
		var ready;
		var fresh = ! quoted;

		window.clearTimeout( requoteTimer );
		if ( quoted ) {
			ready = Promise.resolve();
		} else if ( quoting ) {
			ready = quoting.then( function () {
				requoteAgain = false;
				return quoted ? null : prepare( false );
			} );
		} else {
			ready = prepare( false );
		}

		return ready.then( function () {
			if ( ! quoted ) {
				return null;
			}
			if ( fresh && expected && lastSummary && digits( lastSummary.total_html ) !== expected ) {
				recap.focus( { preventScroll: true } );
				recap.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				message( fammaExpress.amountChanged );
				return null;
			}
			acknowledgedEvents( clickTracking() );
			record.fields = fields;
			record.fields.push( [ 'famma_request_id', record.request_id ], [ 'famma_preparation_id', record.preparation_id ], [ 'famma_cart_hash', record.cart_hash ] );
			record.phase = 'submitted';
			// Persist before any HTTP. A response lost after creation replays this snapshot.
			if ( ! saveRecord() ) {
				record.phase = 'quoted';
				delete record.fields;
				message( fammaExpress.storageError );
				return null;
			}
			return sendFinal();
		} );
	}

	function status() {
		return request( fammaExpress.statusUrl, [ [ 'famma_request_id', record.request_id ] ], true );
	}

	function accepted( result ) {
		record.phase = 'accepted';
		delete record.fields;
		saveRecord();
		window.location.href = result.redirect;
	}

	function resolve( result ) {
		if ( result && 'success' === result.result && result.redirect ) {
			accepted( result );
			return;
		}
		if ( result && 'failed' === result.famma_state && 'validation_failed' === result.famma_code ) {
			newRecord();
			quoted = false;
			recap.hidden = true;
			unlockFields();
			if ( result.messages ) {
				showError( result.messages );
			} else {
				message( fammaExpress.genericError );
			}
			return;
		}
		record.phase = result && 'processing' === result.result ? 'processing' : 'unknown';
		saveRecord();
		lockFields();
		message( 'processing' === record.phase ? fammaExpress.pending : fammaExpress.recovery );
	}

	function sendFinal() {
		return request( fammaExpress.expressUrl, record.fields, true ).then( function ( result ) {
			if ( result && ( 'cart_changed' === result.famma_code || 'preparation_missing' === result.famma_code ) ) {
				// The backend guarantees these guards ran before intent reservation.
				record.phase = 'draft';
				record.preparation_id = uuid();
				record.cart_hash = '';
				delete record.fields;
				saveRecord();
				quoted = false;
				recap.hidden = true;
				unlockFields();
				message( fammaExpress.changed );
				return;
			}
			if ( result && ( 'success' === result.result || 'processing' === result.result || 'failed' === result.famma_state ) ) {
				resolve( result );
				return;
			}
			// A WooCommerce failure alone does not prove that no order was created.
			return status().then( resolve );
		} );
	}

	function recover() {
		return status().then( function ( result ) {
			if ( result && 'unknown_intent' === result.famma_code && record.fields ) {
				// Retry the identical snapshot and ID, never create a fresh intent.
				return sendFinal();
			}
			resolve( result );
		} );
	}

	try {
		var saved = JSON.parse( window.sessionStorage.getItem( storageKey ) || 'null' );
		if ( saved && 1 === saved.version && saved.product === productId && saved.request_id && saved.preparation_id && 'accepted' !== saved.phase ) {
			record = saved;
			record.events = Array.isArray( record.events ) ? record.events : [];
			record.events.forEach( function ( id ) { emitted[ id ] = true; } );
			storageReady = saveRecord();
		} else {
			newRecord();
		}
	} catch ( error ) {
		storageReady = false;
	}
	if ( unresolved() ) {
		message( fammaExpress.recovery );
	} else if ( record ) {
		// Reload requires a fresh recap; no stale quoted amount can be confirmed.
		record.phase = 'draft';
		record.preparation_id = uuid();
		record.cart_hash = '';
		delete record.base_prev;
		saveRecord();
	}
	label();

	/**
	 * Gouvernorat : menu déroulant à recherche.
	 *
	 * Le `<select>` natif reste dans le formulaire et porte la valeur envoyée ;
	 * sans script, c'est lui qui s'affiche. Le champ de saisie le remplace : on
	 * tape « sfa » ou « صفاقس », la liste se réduit, et seule une valeur de la
	 * liste est acceptée (aucun gouvernorat inventé).
	 *
	 * @param {HTMLSelectElement} select Liste native des gouvernorats.
	 */
	function initCombo( select ) {
		var listId = select.id + '-list';
		var options = [];

		function norm( text ) {
			return String( text )
				.normalize( 'NFD' )
				.replace( /[̀-ًͯ-ٟ]/g, '' )
				.replace( /[أإآ]/g, 'ا' )
				.toLowerCase()
				.trim();
		}

		Array.prototype.forEach.call( select.options, function ( option ) {
			if ( ! option.value ) {
				return;
			}

			options.push( {
				value: option.value,
				label: option.textContent,
				alt: option.getAttribute( 'data-alt' ) || '',
				key: norm( option.textContent ),
				altKey: norm( option.getAttribute( 'data-alt' ) || '' ),
			} );
		} );

		var wrap = document.createElement( 'div' );
		var input = document.createElement( 'input' );
		var list = document.createElement( 'ul' );
		var active = -1;
		var shown = [];

		wrap.className = 'famma-express__combo';
		input.type = 'text';
		input.id = select.id;
		input.required = true;
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'aria-controls', listId );
		input.setAttribute( 'autocomplete', 'off' );
		input.setAttribute( 'autocapitalize', 'off' );
		input.setAttribute( 'spellcheck', 'false' );
		input.setAttribute( 'placeholder', fammaExpress.stateHint || '' );
		list.id = listId;
		list.className = 'famma-express__combo-list';
		list.setAttribute( 'role', 'listbox' );
		list.hidden = true;

		select.id = select.id + '-native';
		select.tabIndex = -1;
		select.required = false;
		select.setAttribute( 'aria-hidden', 'true' );
		select.removeAttribute( 'autocomplete' );
		select.classList.add( 'famma-express__native' );

		select.parentNode.insertBefore( wrap, select );
		wrap.appendChild( input );
		wrap.appendChild( list );
		wrap.appendChild( select );

		function chosen() {
			for ( var i = 0; i < options.length; i++ ) {
				if ( options[ i ].value === select.value ) {
					return options[ i ];
				}
			}

			return null;
		}

		function validity() {
			var current = chosen();

			input.setCustomValidity( '' === input.value || current ? '' : ( fammaExpress.stateError || '' ) );
			input.setAttribute( 'aria-invalid', '' !== input.value && ! current ? 'true' : 'false' );
		}

		function close() {
			list.hidden = true;
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
			active = -1;
		}

		function highlight( index ) {
			var items = list.children;

			if ( active >= 0 && items[ active ] ) {
				items[ active ].removeAttribute( 'aria-selected' );
			}

			active = index;

			if ( active >= 0 && items[ active ] ) {
				items[ active ].setAttribute( 'aria-selected', 'true' );
				input.setAttribute( 'aria-activedescendant', items[ active ].id );
				items[ active ].scrollIntoView( { block: 'nearest' } );
			}
		}

		function pick( option ) {
			select.value = option.value;
			input.value = option.label;
			validity();
			close();
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}

		function render( term ) {
			var q = norm( term );

			shown = options.filter( function ( o ) {
				return '' === q || -1 !== o.key.indexOf( q ) || -1 !== o.altKey.indexOf( q );
			} ).sort( function ( a, b ) {
				var aStart = 0 === a.key.indexOf( q ) || 0 === a.altKey.indexOf( q ) ? 0 : 1;
				var bStart = 0 === b.key.indexOf( q ) || 0 === b.altKey.indexOf( q ) ? 0 : 1;

				return aStart - bStart;
			} );

			list.innerHTML = '';

			shown.forEach( function ( o, i ) {
				var item = document.createElement( 'li' );
				var name = document.createElement( 'span' );

				item.id = listId + '-' + i;
				item.setAttribute( 'role', 'option' );
				name.textContent = o.label;
				item.appendChild( name );

				if ( o.alt ) {
					var alt = document.createElement( 'span' );

					alt.lang = 'ar';
					alt.dir = 'rtl';
					alt.textContent = o.alt;
					item.appendChild( alt );
				}

				item.addEventListener( 'mousedown', function ( event ) {
					// Garde le focus dans le champ : le blur fermerait la liste avant le clic.
					event.preventDefault();
					pick( o );
				} );
				list.appendChild( item );
			} );

			if ( ! shown.length ) {
				var empty = document.createElement( 'li' );

				empty.className = 'famma-express__combo-empty';
				empty.textContent = fammaExpress.noMatch || '';
				list.appendChild( empty );
			}

			list.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
			active = -1;
			highlight( shown.length && '' !== q ? 0 : -1 );
		}

		input.addEventListener( 'input', function () {
			select.value = '';
			validity();
			render( input.value );
		} );

		input.addEventListener( 'focus', function () {
			input.select();
			render( chosen() ? '' : input.value );
		} );

		input.addEventListener( 'blur', function () {
			// Un seul résultat possible : on le retient au lieu de laisser un champ « invalide ».
			if ( ! chosen() && '' !== input.value ) {
				var q = norm( input.value );
				var exact = options.filter( function ( o ) {
					return o.key === q || o.altKey === q;
				} );
				var found = exact[ 0 ] || ( 1 === shown.length ? shown[ 0 ] : null );

				if ( found ) {
					pick( found );
					return;
				}
			}

			validity();
			close();
		} );

		input.addEventListener( 'keydown', function ( event ) {
			var key = event.key;

			if ( 'ArrowDown' === key || 'ArrowUp' === key ) {
				event.preventDefault();

				if ( list.hidden ) {
					render( input.value );
					return;
				}

				if ( shown.length ) {
					var step = 'ArrowDown' === key ? 1 : -1;

					highlight( ( active + step + shown.length ) % shown.length );
				}
			} else if ( 'Enter' === key && ! list.hidden && active >= 0 && shown[ active ] ) {
				event.preventDefault();
				pick( shown[ active ] );
			} else if ( 'Escape' === key && ! list.hidden ) {
				event.preventDefault();
				close();
			}
		} );

		var initial = chosen();

		if ( initial ) {
			input.value = initial.label;
		}
	}

	Array.prototype.forEach.call( form.querySelectorAll( 'select[data-famma-combo]' ), initCombo );
	if ( ! unresolved() && stateChosen() ) {
		requote();
	}
	if ( unresolved() ) {
		// Include the search input created by initCombo in the recovery lock.
		lockFields();
	}

	form.addEventListener( 'submit', function ( event ) {
		if ( cartSubmit ) {
			return;
		}

		event.preventDefault();

		if ( busy ) {
			return;
		}

		if ( ! unresolved() ) {
			checkPhone( true );
			if ( ! form.reportValidity() ) {
				return;
			}
		}
		if ( ! record || ! saveRecord() ) {
			message( fammaExpress.storageError );
			return;
		}
		if ( qtyInput && ! unresolved() ) {
			qtyInput.value = String( currentQuantity() );
		}

		var operation;
		if ( unresolved() ) {
			operation = recover;
		} else {
			// Lus avant le verrouillage : un champ désactivé n'est pas envoyé.
			var fields = snapshot();
			operation = function () {
				return confirmFlow( fields );
			};
		}

		busy = true;
		if ( notices ) {
			notices.innerHTML = '';
		}
		lockFields();
		if ( button ) {
			button.disabled = true;
			button.textContent = fammaExpress.sending;
		}
		operation().catch( function () {
			if ( unresolved() ) {
				record.phase = 'unknown';
				saveRecord();
				message( fammaExpress.recovery );
			} else {
				message( fammaExpress.genericError );
			}
		} ).then( release );
	} );
} )();
