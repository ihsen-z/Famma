/**
 * Commande express — un seul appel au clic (audit PERF-03).
 *
 * Ce script n'invente aucun parcours. Il prépare puis envoie :
 *
 *   0. `?wc-ajax=famma_express_nonce`    — dès la première interaction avec le
 *      formulaire, en parallèle de la saisie : jeton de checkout et session ;
 *   1. `?wc-ajax=famma_express_checkout` — au clic : met le produit au panier
 *      puis exécute `WC_Checkout::process_checkout()` (`Express_Checkout`).
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
	var buttonLabel = button ? button.innerHTML : '';
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
	function syncQuantity() {
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
			button.innerHTML = buttonLabel;
		}
	}

	/**
	 * Émet un événement Meta, si le Pixel est présent.
	 *
	 * @param {string} name   Nom de l'événement standard.
	 * @param {Object} params Paramètres de l'événement.
	 * @return {void}
	 */
	function pixel( name, params ) {
		if ( ! window.fbq ) {
			return;
		}

		var id = ( window.crypto && crypto.randomUUID ) ? crypto.randomUUID() : String( Date.now() );

		window.fbq( 'track', name, params, { eventID: id } );
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

	/**
	 * Envoie la commande au point d'entrée unique.
	 *
	 * @param {string}  token    Jeton de checkout.
	 * @param {boolean} canRetry Vrai si un jeton périmé peut être renouvelé.
	 * @return {Promise<Object|null>} Réponse décodée.
	 */
	function sendOrder( token, canRetry ) {
		var order = new FormData( form );

		order.append( 'woocommerce-process-checkout-nonce', token );

		return post( fammaExpress.expressUrl, order ).then( function ( result ) {
			/*
			 * Jeton refusé : le serveur l'a dit avant d'écrire quoi que ce
			 * soit (ni panier, ni commande). Renvoyer ne peut donc pas créer
			 * de doublon — une fois, pas davantage.
			 */
			if ( result && 'expired' === result.famma_token ) {
				if ( ! canRetry ) {
					return null;
				}

				return checkoutToken( true ).then( function ( fresh ) {
					return fresh ? sendOrder( fresh, false ) : null;
				} );
			}

			return result;
		} );
	}

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

	form.addEventListener( 'submit', function ( event ) {
		if ( cartSubmit ) {
			return;
		}

		event.preventDefault();

		if ( busy ) {
			return;
		}

		/*
		 * Le formulaire porte `novalidate` : la validation du navigateur est
		 * déclenchée ici pour garder la main sur le moment où elle se produit,
		 * sans perdre les messages natifs — traduits, et lus par les lecteurs
		 * d'écran.
		 */
		checkPhone( true );

		if ( ! form.reportValidity() ) {
			return;
		}

		busy = true;

		if ( notices ) {
			notices.innerHTML = '';
		}

		if ( button ) {
			button.disabled = true;
			button.textContent = fammaExpress.sending;
		}

		var productId = form.querySelector( '[name="famma_product_id"]' ).value;
		var quantity = String( currentQuantity() );
		var unit = form.querySelector( '.famma-express__total' );
		var unitPrice = unit ? parseFloat( unit.getAttribute( 'data-unit-price' ) || '0' ) : 0;

		if ( qtyInput ) {
			qtyInput.value = quantity;
		}

		pixel( 'AddToCart', {
			content_type: 'product',
			content_ids: [ String( productId ) ],
			contents: [ { id: String( productId ), quantity: parseFloat( quantity ) } ],
			value: unitPrice * parseFloat( quantity ),
			currency: fammaExpress.currency,
		} );

		pixel( 'InitiateCheckout', {
			content_type: 'product',
			content_ids: [ String( productId ) ],
			num_items: parseFloat( quantity ),
			value: unitPrice * parseFloat( quantity ),
			currency: fammaExpress.currency,
		} );

		checkoutToken( false ).then( function ( token ) {
			if ( ! token ) {
				return null;
			}

			return sendOrder( token, true );
		} ).then( function ( result ) {
			if ( result && 'success' === result.result && result.redirect ) {
				// Page de remerciement : c'est WooCommerce qui décide où.
				window.location.href = result.redirect;
				return;
			}

			release();

			if ( result && result.messages ) {
				showError( result.messages );
				return;
			}

			/*
			 * `reload` demandé par WooCommerce : la session est
			 * désynchronisée (panier expiré, jeton périmé). Recharger est
			 * sa réponse habituelle, et la seule qui remette d'aplomb.
			 */
			if ( result && result.reload ) {
				window.location.reload();
				return;
			}

			showError( fammaExpress.genericError );
		} ).catch( function () {
			// Erreur réseau : pas de renvoi automatique, le client décide.
			release();
			showError( fammaExpress.genericError );
		} );
	} );
} )();
