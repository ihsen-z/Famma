/**
 * Commande express : un clic, une commande.
 *
 * Ce script n'invente aucun parcours. Il prépare puis envoie :
 *
 *   0. `?wc-ajax=famma_express_nonce`    — dès la première interaction avec le
 *      formulaire, en parallèle de la saisie : jeton de checkout et session ;
 *   1. `?wc-ajax=famma_express_prepare` — devis isolé (ce produit, cette quantité) ;
 *   2. `?wc-ajax=famma_express_checkout` — commande, une seule fois par intention ;
 *   3. `?wc-ajax=famma_express_status` — retrouve un résultat après interruption.
 *
 * ── Machine d'états du bouton « Confirmer ma commande » ─────────────────────
 *
 *   idle ──clic──▶ validation locale ──KO──▶ idle (message au-dessus du bouton,
 *                                             champ fautif en rouge)
 *                         │OK
 *                         ▼
 *   busy (bouton désactivé, « Envoi en cours… », indicateur) ──▶ prepare
 *        prepare KO / réseau ──▶ idle + message (aucun renvoi automatique)
 *        prepare OK : AddToCart / InitiateCheckout (une fois) puis
 *          total serveur = total affiché ──▶ checkout
 *          total différent ──▶ review (modale ; « Valider ma commande – XX DT »
 *                              ──▶ checkout ; fermer ──▶ idle, nouveau devis)
 *        checkout OK ──▶ redirecting (bouton laissé désactivé) ──▶ page WooCommerce
 *        checkout refusé (validation) ──▶ idle + messages WooCommerce
 *        checkout perdu / ambigu ──▶ recovery (champs verrouillés ; le bouton
 *                              interroge famma_express_status, jamais une 2e commande)
 *
 * La commande, ses validations et son statut restent entièrement l'affaire de
 * WooCommerce. En cas d'échec, ce sont ses propres messages qui s'affichent.
 * Aucune commande n'est renvoyée automatiquement après une erreur réseau ou
 * HTTP. Seul cas de renvoi : `famma_token: expired`, que le serveur répond
 * AVANT toute écriture — un nouveau jeton est demandé, une seule fois.
 *
 * Sans JavaScript, la validation est inerte — mais « Ajouter au panier », à
 * côté d'elle, poste encore le produit vers le panier de WooCommerce.
 */
( function () {
	'use strict';

	var form = document.querySelector( '.famma-express__form' );

	if ( ! form || 'undefined' === typeof fammaExpress ) {
		return;
	}

	var button = form.querySelector( '.famma-express__submit' );
	var submitText = button && button.querySelector ? button.querySelector( '.famma-express__submit-text' ) : null;
	var notices = form.querySelector( '.famma-express__notices' );
	var info = form.querySelector( '.famma-express__info' );
	var busy = false;

	var qtyInput = form.querySelector( '[name="famma_quantity"]' );
	var totalNode = form.querySelector( '.famma-express__total' );
	var phoneInput = form.querySelector( '[name="billing_phone"]' );
	var phoneError = phoneInput ? document.getElementById( phoneInput.getAttribute( 'aria-describedby' ) ) : null;

	/**
	 * Indique si l'animation doit être supprimée.
	 *
	 * @return {boolean}
	 */
	function reducedMotion() {
		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	/**
	 * Amène un élément au centre de l'écran (message d'erreur, en général).
	 *
	 * @param {Element} node Élément.
	 */
	function reveal( node ) {
		if ( node && node.scrollIntoView ) {
			node.scrollIntoView( { behavior: reducedMotion() ? 'auto' : 'smooth', block: 'center' } );
		}
	}

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
			.replace( /&nbsp;/g, ' ' );
	}

	/**
	 * Bornes du champ quantité.
	 *
	 * @return {{min:number,max:number}}
	 */
	function bounds() {
		return {
			min: parseInt( qtyInput.getAttribute( 'min' ), 10 ) || 1,
			max: parseInt( qtyInput.getAttribute( 'max' ), 10 ) || Infinity,
		};
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

		var b = bounds();
		var value = parseInt( qtyInput.value, 10 );

		if ( isNaN( value ) || value < b.min ) {
			value = b.min;
		}

		return Math.min( value, b.max );
	}

	var quotedQuantity;

	/**
	 * Quantité changée : total recalculé, devis invalidé.
	 *
	 * @return {void}
	 */
	function syncQuantity() {
		var quantity = currentQuantity();

		if ( quantity !== quotedQuantity ) {
			quotedQuantity = quantity;
			invalidateQuote();
		}
		renderQuantity();
	}

	/**
	 * Reporte la quantité sur le total affiché, sur les boutons − / + et sur le
	 * formulaire WooCommerce rangé par le thème (« Ajouter au panier » doit
	 * ajouter la quantité choisie ici).
	 *
	 * @return {void}
	 */
	function renderQuantity() {
		var quantity = currentQuantity();

		if ( totalNode ) {
			var unit = parseFloat( totalNode.getAttribute( 'data-unit-price' ) || '0' );
			totalNode.textContent = formatPrice( unit * quantity );
		}

		if ( qtyInput ) {
			var b = bounds();
			Array.prototype.forEach.call( form.querySelectorAll( '[data-famma-express-step]' ), function ( step ) {
				var dir = parseInt( step.getAttribute( 'data-famma-express-step' ), 10 );
				step.disabled = ( dir < 0 && quantity <= b.min ) || ( dir > 0 && quantity >= b.max );
			} );
		}

		var cartQty = document.querySelector( 'form.cart input.qty' );

		if ( cartQty && String( quantity ) !== cartQty.value ) {
			cartQty.value = String( quantity );
			cartQty.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	}

	form.addEventListener( 'click', function ( event ) {
		var step = event.target && event.target.closest ? event.target.closest( '[data-famma-express-step]' ) : null;

		if ( ! step || ! qtyInput || busy ) {
			return;
		}

		event.preventDefault();

		var b = bounds();
		var next = currentQuantity() + parseInt( step.getAttribute( 'data-famma-express-step' ), 10 );

		qtyInput.value = String( Math.max( b.min, Math.min( next, b.max ) ) );
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
	 * Numéro tunisien normalisé sur 8 chiffres, ou chaîne vide.
	 *
	 * Même règle que `Tunisia::normalize_phone()` côté serveur : chiffres
	 * arabes-indiens et persans convertis, tout non-chiffre retiré, indicatif
	 * retiré selon la longueur (13 chiffres « 00216… », 11 chiffres « 216… »),
	 * puis 8 chiffres commençant par 2 à 9. « 21612345 » reste un numéro local
	 * valide. Le serveur reste juge ; ce contrôle évite d'attendre l'envoi.
	 *
	 * @param {string} value Saisie brute.
	 * @return {string}
	 */
	function normalizePhone( value ) {
		var digitsOnly = String( value || '' )
			.replace( /[٠-٩]/g, function ( c ) {
				return String( c.charCodeAt( 0 ) - 0x0660 );
			} )
			.replace( /[۰-۹]/g, function ( c ) {
				return String( c.charCodeAt( 0 ) - 0x06F0 );
			} )
			.replace( /\D/g, '' );

		if ( 13 === digitsOnly.length && 0 === digitsOnly.indexOf( '00216' ) ) {
			digitsOnly = digitsOnly.slice( 5 );
		} else if ( 11 === digitsOnly.length && 0 === digitsOnly.indexOf( '216' ) ) {
			digitsOnly = digitsOnly.slice( 3 );
		}

		return /^[2-9]\d{7}$/.test( digitsOnly ) ? digitsOnly : '';
	}

	/**
	 * Affiche ou retire l'erreur du téléphone.
	 *
	 * @param {boolean} strict   Vrai à la sortie du champ et à l'envoi.
	 * @param {boolean} required Vrai à l'envoi : un champ vide est une erreur.
	 * @return {boolean} Vrai si le numéro est acceptable.
	 */
	function checkPhone( strict, required ) {
		if ( ! phoneInput ) {
			return true;
		}

		var value = String( phoneInput.value || '' ).trim();
		var ok = '' === value ? ! required : '' !== normalizePhone( value );

		// Pendant la frappe, on ne signale rien : on se contente d'effacer l'erreur dès qu'elle est corrigée.
		if ( ! strict && ! ok ) {
			return ok;
		}

		phoneInput.setCustomValidity( ok ? '' : fammaExpress.phoneError );
		phoneInput.setAttribute( 'aria-invalid', ok ? 'false' : 'true' );
		if ( phoneInput.classList ) {
			phoneInput.classList.toggle( 'famma-express__invalid', ! ok );
		}

		if ( phoneError ) {
			phoneError.textContent = ok ? '' : fammaExpress.phoneError;
			phoneError.hidden = ok;
		}

		return ok;
	}

	if ( phoneInput ) {
		phoneInput.addEventListener( 'blur', function () {
			checkPhone( true, false );
		} );
		phoneInput.addEventListener( 'input', function () {
			checkPhone( false, false );
		} );
	}

	/*
	 * Champs signalés en rouge : `aria-invalid` (lu par les lecteurs d'écran)
	 * et une classe (le style). L'erreur s'efface dès que le champ est modifié.
	 */
	var invalidFields = [];

	function markInvalid( field ) {
		if ( ! field || ! field.setAttribute ) {
			return;
		}
		field.setAttribute( 'aria-invalid', 'true' );
		if ( field.classList ) {
			field.classList.add( 'famma-express__invalid' );
		}
		invalidFields.push( field );
	}

	function clearInvalid( field ) {
		if ( ! field || ! field.setAttribute || -1 === invalidFields.indexOf( field ) ) {
			return;
		}
		field.setAttribute( 'aria-invalid', 'false' );
		if ( field.classList ) {
			field.classList.remove( 'famma-express__invalid' );
		}
		invalidFields.splice( invalidFields.indexOf( field ), 1 );
	}

	function clearAllInvalid() {
		invalidFields.slice().forEach( clearInvalid );
	}

	form.addEventListener( 'input', function ( event ) {
		clearInvalid( event.target );
	} );

	/**
	 * Champ visible correspondant à un nom de champ WooCommerce (`data-id`).
	 *
	 * Le nom complet est redécoupé en prénom + nom côté serveur : une erreur
	 * sur le nom pointe le champ unique. Le gouvernorat est saisi dans le
	 * menu à recherche, pas dans le `<select>` masqué.
	 *
	 * @param {string} name Nom du champ.
	 * @return {Element|null}
	 */
	function fieldFor( name ) {
		name = String( name || '' ).replace( /[^a-z0-9_\-]/gi, '' );
		if ( ! name ) {
			return null;
		}
		if ( 'billing_last_name' === name ) {
			name = 'billing_first_name';
		}
		var field = form.querySelector( '[name="' + name + '"]' );
		if ( field && field.classList && field.classList.contains( 'famma-express__native' ) && field.parentNode ) {
			field = field.parentNode.querySelector( '[role="combobox"]' ) || field;
		}
		return field;
	}

	/**
	 * Affiche les avis WooCommerce au-dessus du bouton et marque les champs.
	 *
	 * Les `<li>` d'erreur de WooCommerce portent `data-id="<champ>"`.
	 *
	 * @param {string} html Message, déjà mis en forme par WooCommerce.
	 * @return {void}
	 */
	function showError( html ) {
		if ( ! notices ) {
			return;
		}

		notices.innerHTML = html;

		if ( ! String( notices.textContent || html ).trim() ) {
			notices.textContent = fammaExpress.genericError;
		}

		if ( notices.querySelectorAll ) {
			Array.prototype.forEach.call( notices.querySelectorAll( '[data-id]' ), function ( item ) {
				markInvalid( fieldFor( item.getAttribute( 'data-id' ) ) );
			} );
		}

		reveal( notices );
	}

	/**
	 * Validation locale, avant tout appel réseau : nom, téléphone, gouvernorat.
	 *
	 * @return {Array<Array>} Couples [champ, message].
	 */
	function validate() {
		var problems = [];
		var name = form.querySelector( '[name="billing_first_name"]' );
		var state = form.querySelector( '[name="billing_state"]' );

		if ( name && ! String( name.value || '' ).trim() ) {
			problems.push( [ name, fammaExpress.nameError ] );
		}
		if ( phoneInput && ! checkPhone( true, true ) ) {
			problems.push( [ phoneInput, fammaExpress.phoneError ] );
		}
		if ( state && ! state.value ) {
			problems.push( [ fieldFor( 'billing_state' ), fammaExpress.stateError ] );
		}

		return problems;
	}

	/**
	 * Liste les erreurs locales au-dessus du bouton, champs en rouge.
	 *
	 * @param {Array<Array>} problems Couples [champ, message].
	 */
	function showProblems( problems ) {
		if ( ! notices ) {
			return;
		}
		notices.innerHTML = '';
		var list = document.createElement( 'ul' );
		problems.forEach( function ( problem ) {
			var item = document.createElement( 'li' );
			item.textContent = problem[ 1 ] || fammaExpress.genericError;
			list.appendChild( item );
			markInvalid( problem[ 0 ] );
		} );
		notices.appendChild( list );
		reveal( notices );
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
	 * @param {string} id     Identifiant de déduplication (celui du serveur).
	 * @return {boolean}
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
			 * avertissement PHP peut le précéder : on récupère le JSON là où il
			 * commence, comme le script de checkout de WooCommerce.
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
	 * Jeton de checkout, demandé une seule fois et partagé, dès la première
	 * interaction avec le formulaire. Voir `Express_Order::send_nonce()`.
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

	function prepareToken() {
		form.removeEventListener( 'focusin', prepareToken );
		form.removeEventListener( 'pointerdown', prepareToken );
		checkoutToken( false );
	}

	form.addEventListener( 'focusin', prepareToken );
	form.addEventListener( 'pointerdown', prepareToken );

	var productId = form.querySelector( '[name="famma_product_id"]' ).value;
	var storageKey = 'famma_express_intent_' + productId;
	var checkoutKey = 'famma_express_ic_' + productId;
	var record = null;
	var storageReady = false;
	var quoted = false;
	var reviewedTotal = null;
	var lastSummary = null;
	var pendingTracking = null;
	var lockedFields = [];
	var emitted = {};
	var idleHtml = submitText ? submitText.innerHTML : '';

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

	/**
	 * Texte du bouton principal, selon l'état.
	 *
	 * Au repos, le balisage d'origine (français + derja) est remis tel quel :
	 * il vient du gabarit PHP, jamais d'une réponse réseau.
	 *
	 * @param {string} state `idle`, `busy` ou `recovery` (déduit si absent).
	 */
	function label( state ) {
		if ( ! button ) {
			return;
		}
		state = state || ( unresolved() ? 'recovery' : 'idle' );
		button.setAttribute( 'aria-busy', 'busy' === state ? 'true' : 'false' );
		var text = 'busy' === state ? fammaExpress.sending : ( 'recovery' === state ? fammaExpress.retryLabel : '' );
		if ( submitText ) {
			if ( text ) {
				submitText.textContent = text;
			} else {
				submitText.innerHTML = idleHtml;
			}
			return;
		}
		button.textContent = text || fammaExpress.confirmLabel;
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

	/**
	 * Affiche un message en texte brut au-dessus du bouton. Jamais vide : à
	 * défaut de texte précis, le message générique.
	 *
	 * @param {string} text Message.
	 */
	function message( text ) {
		if ( notices ) {
			notices.textContent = text || fammaExpress.genericError;
			reveal( notices );
		}
	}

	/** Message informatif sous le bouton : il ne bloque rien et ne décale pas le bouton. */
	function inform( text ) {
		if ( info ) {
			info.textContent = text || '';
			info.hidden = ! text;
		}
	}

	/**
	 * Le devis n'est plus valable (quantité ou gouvernorat changé) : la
	 * prochaine préparation porte un nouvel identifiant. Jamais pendant qu'un
	 * résultat final est inconnu.
	 */
	function invalidateQuote() {
		if ( busy || unresolved() || ! record ) {
			return;
		}
		quoted = false;
		reviewedTotal = null;
		record.preparation_id = uuid();
		record.cart_hash = '';
		record.phase = 'draft';
		saveRecord();
	}

	form.addEventListener( 'change', function ( event ) {
		if ( event.target && 'billing_state' === event.target.name ) {
			invalidateQuote();
		}
	} );

	/** InitiateCheckout déjà envoyé par ce navigateur pour ce produit (persisté, survit au rechargement). */
	function checkoutEmitted() {
		if ( record && record.checkout_emitted ) {
			return true;
		}
		try {
			return !! window.sessionStorage.getItem( checkoutKey );
		} catch ( error ) {
			return false;
		}
	}

	function rememberCheckout( id ) {
		record.checkout_emitted = true;
		try {
			window.sessionStorage.setItem( checkoutKey, String( id ) );
		} catch ( error ) {
			// Le drapeau du registre suffit pour cette page.
		}
	}

	/**
	 * Use the exact server acknowledgement and event ID, including replays.
	 * InitiateCheckout part une seule fois : au premier clic qui obtient un
	 * devis, avec l'event_id du serveur (déduplication avec la CAPI).
	 */
	function acknowledgedEvents( tracking ) {
		if ( ! tracking ) {
			return;
		}
		[ [ 'AddToCart', tracking.add_to_cart ], [ 'InitiateCheckout', tracking.initiate_checkout ] ].forEach( function ( entry ) {
			var data = entry[ 1 ];
			var isCheckout = 'InitiateCheckout' === entry[ 0 ];
			if ( ! data || false === data.emit || ! data.event_id || emitted[ data.event_id ] || ! window.fbq || ( isCheckout && checkoutEmitted() ) ) {
				return;
			}
			if ( ! pixel( entry[ 0 ], data.params, data.event_id ) ) {
				return;
			}
			if ( isCheckout ) {
				rememberCheckout( data.event_id );
			}
			emitted[ data.event_id ] = true;
			record.events.push( data.event_id );
			record.events = record.events.slice( -100 );
			saveRecord();
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

	/**
	 * Devis isolé du serveur : ce produit, cette quantité, ce gouvernorat.
	 * Le panier n'en garde aucune trace (contrat SERVEUR).
	 *
	 * @return {Promise<Object|null>} Réponse décodée, ou `null`.
	 */
	function prepare() {
		var state = form.querySelector( '[name="billing_state"]' );
		var trap = form.querySelector( '[name="famma_hp_site"]' );
		return request( fammaExpress.prepareUrl, [
			[ 'famma_product_id', productId ], [ 'famma_quantity', String( currentQuantity() ) ],
			[ 'famma_preparation_id', record.preparation_id ], [ 'billing_country', 'TN' ],
			[ 'billing_state', state ? state.value : '' ], [ 'famma_hp_site', trap ? trap.value : '' ],
		], true );
	}

	/** Chiffres seuls d'un montant HTML : « 231,000&nbsp;DT » devient « 231000 ». */
	function digits( html ) {
		return String( html || '' ).replace( /<[^>]*>/g, '' ).replace( /&[a-z0-9#]+;/gi, '' ).replace( /\D/g, '' );
	}

	/** Montant serveur, en nombre (ou NaN). */
	function serverTotal( summary ) {
		return 'number' === typeof summary.total ? summary.total : parseFloat( summary.total );
	}

	/**
	 * Le total serveur est-il celui que la cliente a sous les yeux (prix
	 * unitaire × quantité) ? Comparaison à la précision de la boutique.
	 */
	function sameTotal( summary ) {
		if ( ! totalNode ) {
			return false;
		}
		var unit = parseFloat( totalNode.getAttribute( 'data-unit-price' ) || '' );
		var server = serverTotal( summary );
		if ( isNaN( unit ) || isNaN( server ) ) {
			return digits( summary.total_html ) === digits( totalNode.textContent );
		}
		var decimals = parseInt( ( fammaExpress.price && fammaExpress.price.decimals ), 10 );
		var scale = Math.pow( 10, isNaN( decimals ) ? 3 : decimals );
		return Math.round( server * scale ) === Math.round( unit * currentQuantity() * scale );
	}

	/** Montant lisible du devis, pour le bouton final. */
	function totalText( summary ) {
		var server = serverTotal( summary );
		if ( ! isNaN( server ) ) {
			return formatPrice( server );
		}
		return String( summary.total_html || '' ).replace( /<[^>]*>/g, '' ).replace( /&nbsp;|&#160;/g, ' ' ).trim();
	}

	/** AddToCart sur la quantité réellement ajoutée depuis le premier devis, pas sur le dernier delta. */
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
	 * Parcours d'un clic : devis isolé → (modale seulement si le total a
	 * changé) → commande → redirection.
	 *
	 * @param {Array} fields Champs du formulaire, lus avant le verrouillage.
	 * @return {Promise}
	 */
	function confirmFlow( fields ) {
		var id = record.preparation_id;

		return prepare().then( function ( result ) {
			if ( id !== record.preparation_id ) {
				message( fammaExpress.changed );
				return null;
			}
			if ( result && 'success' === result.result && result.summary && result.cart_hash && result.preparation_id === id ) {
				lastSummary = result.summary;
				record.cart_hash = result.cart_hash;
				record.phase = 'quoted';
				quoted = true;
				pendingTracking = result.tracking || null;
				if ( 'number' !== typeof record.base_prev && pendingTracking && pendingTracking.add_to_cart && 'number' === typeof pendingTracking.add_to_cart.previous ) {
					record.base_prev = pendingTracking.add_to_cart.previous;
				}
				saveRecord();
				acknowledgedEvents( clickTracking() );
				inform( result.other_items > 0 ? fammaExpress.otherItems : '' );
				if ( ! sameTotal( result.summary ) ) {
					return review( result.summary, fields );
				}
				return submitFinal( fields );
			}
			if ( result && result.messages ) {
				showError( result.messages );
			} else if ( result ) {
				message( fammaExpress.genericError );
			} else {
				message( fammaExpress.networkError || fammaExpress.genericError );
			}
			return null;
		} );
	}

	/**
	 * Fige la commande finale (registre persistant AVANT tout HTTP) puis l'envoie.
	 *
	 * @param {Array} fields Champs du formulaire.
	 * @return {Promise}
	 */
	function submitFinal( fields ) {
		record.fields = fields.slice();
		record.fields.push( [ 'famma_request_id', record.request_id ], [ 'famma_preparation_id', record.preparation_id ], [ 'famma_cart_hash', record.cart_hash ] );
		record.phase = 'submitted';
		// Persist before any HTTP. A response lost after creation replays this snapshot.
		if ( ! saveRecord() ) {
			record.phase = 'quoted';
			delete record.fields;
			message( fammaExpress.storageError );
			return Promise.resolve( null );
		}
		return sendFinal();
	}

	function status() {
		return request( fammaExpress.statusUrl, [ [ 'famma_request_id', record.request_id ] ], true );
	}

	function accepted( result ) {
		record.phase = 'accepted';
		delete record.fields;
		saveRecord();
		try {
			// Une nouvelle commande, plus tard, sera une nouvelle intention d'achat.
			window.sessionStorage.removeItem( checkoutKey );
		} catch ( error ) {
			// Sans effet sur la commande.
		}
		window.location.href = result.redirect;
	}

	/**
	 * Résultat final connu, ou à retrouver.
	 *
	 * @param {Object|null} result   Réponse de checkout ou de status.
	 * @param {Object|null} original Réponse du checkout, pour ses messages WooCommerce.
	 */
	function resolve( result, original ) {
		if ( result && 'success' === result.result && result.redirect ) {
			accepted( result );
			return;
		}
		if ( result && 'failed' === result.famma_state && 'validation_failed' === result.famma_code ) {
			newRecord();
			quoted = false;
			reviewedTotal = null;
			unlockFields();
			// Le message précis de WooCommerce (avec data-id) est dans la réponse du checkout ; celui du statut est générique.
			var html = ( original && original.messages ) || result.messages;
			if ( html ) {
				showError( html );
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
				reviewedTotal = null;
				unlockFields();
				message( fammaExpress.changed );
				return;
			}
			if ( result && ( 'success' === result.result || 'processing' === result.result || 'failed' === result.famma_state ) ) {
				resolve( result, result );
				return;
			}
			// A WooCommerce failure alone does not prove that no order was created.
			return status().then( function ( state ) {
				resolve( state, result );
			} );
		} );
	}

	function recover() {
		return status().then( function ( result ) {
			if ( result && 'unknown_intent' === result.famma_code && record.fields ) {
				// Retry the identical snapshot and ID, never create a fresh intent.
				return sendFinal();
			}
			resolve( result, null );
		} );
	}

	/*
	 * Modale « le total a changé ». Elle n'apparaît QUE si le total du serveur
	 * diffère de celui affiché : rien ne s'insère dans la page, le bouton
	 * principal ne bouge pas. Le bouton final est collé en bas de la modale,
	 * visible sans défiler (hauteur maximale = écran).
	 */
	var modal = null;
	var modalFields = null;
	var modalReturn = null;

	function node( tag, className ) {
		var element = document.createElement( tag );
		if ( className ) {
			element.className = className;
		}
		return element;
	}

	function cell( row, text, html, heading ) {
		var element = document.createElement( heading ? 'th' : 'td' );
		if ( html ) {
			// These amount fragments come only from WooCommerce's server summary.
			element.innerHTML = text;
		} else {
			element.textContent = String( text );
		}
		if ( heading ) {
			element.setAttribute( 'scope', 'col' );
		}
		row.appendChild( element );
		return element;
	}

	/** Tableau du devis : lignes (uniquement ce produit) puis totaux non nuls. */
	function summaryTable( summary ) {
		var names = fammaExpress.recapLabels || {};
		var table = node( 'table' );
		var head = node( 'thead' );
		var title = node( 'tr' );
		var body = node( 'tbody' );
		var foot = node( 'tfoot' );

		cell( title, names.product, false, true );
		cell( title, names.quantity, false, true );
		cell( title, names.amount, false, true );
		head.appendChild( title );
		table.appendChild( head );

		( summary.items || [] ).forEach( function ( item ) {
			var row = node( 'tr' );
			cell( row, item.name, false );
			cell( row, item.quantity, false );
			cell( row, item.line_total_html || '', true );
			body.appendChild( row );
		} );
		table.appendChild( body );

		[ 'subtotal', 'discount', 'shipping', 'fees', 'tax', 'total' ].forEach( function ( key ) {
			if ( ! summary[ key + '_html' ] ) {
				return;
			}
			var row = node( 'tr' );
			cell( row, names[ key ], false ).setAttribute( 'colspan', '2' );
			cell( row, summary[ key + '_html' ], true );
			foot.appendChild( row );
		} );
		table.appendChild( foot );

		return table;
	}

	function modalOpen() {
		return !! ( modal && ! modal.overlay.hidden );
	}

	function focusables() {
		return [ modal.close, modal.confirm ].filter( function ( element ) {
			return ! element.disabled;
		} );
	}

	/** Construit la modale une fois, hors du formulaire (fin du `<body>`). */
	function buildModal() {
		if ( modal ) {
			return modal;
		}
		if ( ! document.body || 'function' !== typeof document.body.appendChild ) {
			return null;
		}

		var overlay = node( 'div', 'famma-express-modal' );
		var dialog = node( 'div', 'famma-express-modal__dialog' );
		var head = node( 'div', 'famma-express-modal__head' );
		var title = node( 'h2', 'famma-express-modal__title' );
		var close = node( 'button', 'famma-express-modal__close' );
		var body = node( 'div', 'famma-express-modal__body' );
		var text = node( 'p', 'famma-express-modal__text' );
		var content = node( 'div', 'famma-express-modal__content' );
		var foot = node( 'div', 'famma-express-modal__foot' );
		var confirm = node( 'button', 'famma-express-modal__confirm' );
		var spinner = node( 'span', 'famma-express__spinner' );
		var confirmText = node( 'span', 'famma-express-modal__confirm-text' );

		overlay.hidden = true;
		dialog.setAttribute( 'role', 'dialog' );
		dialog.setAttribute( 'aria-modal', 'true' );
		dialog.setAttribute( 'aria-labelledby', 'famma-express-modal-title' );
		dialog.setAttribute( 'aria-describedby', 'famma-express-modal-text' );
		dialog.tabIndex = -1;
		title.id = 'famma-express-modal-title';
		title.textContent = fammaExpress.modalTitle || '';
		text.id = 'famma-express-modal-text';
		text.textContent = fammaExpress.amountChanged || '';
		close.type = 'button';
		close.setAttribute( 'aria-label', fammaExpress.modalClose || '' );
		close.textContent = '×';
		confirm.type = 'button';
		spinner.setAttribute( 'aria-hidden', 'true' );

		head.appendChild( title );
		head.appendChild( close );
		body.appendChild( text );
		body.appendChild( content );
		confirm.appendChild( spinner );
		confirm.appendChild( confirmText );
		foot.appendChild( confirm );
		dialog.appendChild( head );
		dialog.appendChild( body );
		dialog.appendChild( foot );
		overlay.appendChild( dialog );

		close.addEventListener( 'click', dismissModal );
		confirm.addEventListener( 'click', finalClick );
		overlay.addEventListener( 'click', function ( event ) {
			if ( event.target === overlay ) {
				dismissModal();
			}
		} );
		overlay.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				dismissModal();
				return;
			}
			if ( 'Tab' !== event.key ) {
				return;
			}
			// Focus piégé : Tab et Maj+Tab tournent entre « Fermer » et le bouton final.
			var list = focusables();
			if ( ! list.length ) {
				event.preventDefault();
				dialog.focus();
				return;
			}
			var index = list.indexOf( document.activeElement );
			var next = event.shiftKey ? index - 1 : index + 1;
			event.preventDefault();
			list[ ( next + list.length ) % list.length ].focus();
		} );

		document.body.appendChild( overlay );
		modal = { overlay: overlay, dialog: dialog, content: content, close: close, confirm: confirm, confirmText: confirmText };
		return modal;
	}

	function setModalBusy( on, summary ) {
		modal.confirm.disabled = on;
		modal.close.disabled = on;
		modal.confirm.setAttribute( 'aria-busy', on ? 'true' : 'false' );
		modal.confirmText.textContent = on ? fammaExpress.sending : String( fammaExpress.finalLabel || '%s' ).replace( '%s', totalText( summary ) );
	}

	function hideModal() {
		if ( ! modal ) {
			return;
		}
		modal.overlay.hidden = true;
		if ( document.documentElement && document.documentElement.classList ) {
			document.documentElement.classList.remove( 'famma-express-modal-open' );
		}
		var target = modalReturn || button;
		modalReturn = null;
		if ( target && target.focus ) {
			target.focus( { preventScroll: true } );
		}
	}

	/** Fermeture par la cliente : le devis affiché n'est plus engagé. */
	function dismissModal() {
		if ( busy ) {
			return;
		}
		modalFields = null;
		hideModal();
		invalidateQuote();
	}

	/**
	 * Total serveur différent du total affiché : la cliente revoit le devis.
	 * Sans modale possible (pas de `<body>`), le message s'affiche au-dessus du
	 * bouton et le clic suivant valide ce même devis.
	 *
	 * @param {Object} summary Devis du serveur.
	 * @param {Array}  fields  Champs du formulaire.
	 * @return {null}
	 */
	function review( summary, fields ) {
		var m = buildModal();
		if ( ! m ) {
			reviewedTotal = serverTotal( summary );
			message( fammaExpress.amountChanged + ' ' + totalText( summary ) );
			return null;
		}
		modalFields = fields;
		modalReturn = button;
		m.content.innerHTML = '';
		m.content.appendChild( summaryTable( summary ) );
		setModalBusy( false, summary );
		m.overlay.hidden = false;
		if ( document.documentElement && document.documentElement.classList ) {
			document.documentElement.classList.add( 'famma-express-modal-open' );
		}
		m.confirm.focus();
		return null;
	}

	/** « Valider ma commande – XX DT » : la commande part avec le devis revu. */
	function finalClick() {
		if ( busy || ! modalFields || ! record ) {
			return;
		}
		var fields = modalFields;
		busy = true;
		setModalBusy( true, lastSummary );
		lockFields();
		if ( button ) {
			button.disabled = true;
		}
		label( 'busy' );
		if ( notices ) {
			notices.innerHTML = '';
		}
		submitFinal( fields ).catch( failed ).then( function () {
			if ( record && 'accepted' === record.phase ) {
				// Redirection en cours : rien ne se réactive.
				return;
			}
			modalFields = null;
			setModalBusy( false, lastSummary );
			hideModal();
			release();
			if ( notices && notices.textContent ) {
				reveal( notices );
			}
		} );
	}

	/** Tout chemin d'erreur affiche un message : jamais d'échec silencieux. */
	function failed() {
		if ( unresolved() ) {
			record.phase = 'unknown';
			saveRecord();
			lockFields();
			message( fammaExpress.recovery );
		} else {
			message( fammaExpress.networkError || fammaExpress.genericError );
		}
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
		// Reload requires a fresh quote; no stale amount can be confirmed.
		record.phase = 'draft';
		record.preparation_id = uuid();
		record.cart_hash = '';
		delete record.base_prev;
		saveRecord();
	}
	label();
	renderQuantity();

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

	if ( unresolved() ) {
		// Include the search input created by initCombo in the recovery lock.
		lockFields();
	}

	form.addEventListener( 'submit', function ( event ) {
		if ( cartSubmit ) {
			return;
		}

		event.preventDefault();

		// Double clic, ou clic pendant la modale : ignoré. Une seule commande par intention.
		if ( busy || modalOpen() ) {
			return;
		}

		if ( ! unresolved() ) {
			clearAllInvalid();
			var problems = validate();
			if ( problems.length ) {
				showProblems( problems );
				return;
			}
			if ( 'function' === typeof form.reportValidity && ! form.reportValidity() ) {
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
			operation = quoted && null !== reviewedTotal ? function () {
				return submitFinal( fields );
			} : function () {
				return confirmFlow( fields );
			};
		}

		busy = true;
		if ( notices ) {
			notices.innerHTML = '';
		}
		inform( '' );
		lockFields();
		if ( button ) {
			button.disabled = true;
		}
		label( 'busy' );
		operation().catch( failed ).then( function () {
			if ( record && 'accepted' === record.phase ) {
				// Redirection en cours : le bouton reste désactivé, aucun second envoi possible.
				return;
			}
			release();
		} );
	} );
} )();
