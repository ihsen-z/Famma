/**
 * Commande express — enchaînement des deux points d'entrée de WooCommerce.
 *
 * Ce script n'invente aucun parcours. Il fait, en deux requêtes, ce que le
 * client ferait en deux pages :
 *
 *   1. `?wc-ajax=add_to_cart`  — met le produit au panier ;
 *   2. `?wc-ajax=checkout`     — exécute `WC_Checkout::process_checkout()`.
 *
 * La commande, ses validations et son statut restent donc entièrement l'affaire
 * de WooCommerce. En cas d'échec, ce sont ses propres messages qui s'affichent :
 * « numéro de téléphone invalide » vient de `Tunisia::validate_phone()`, pas
 * d'un contrôle réécrit ici.
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
	 * `credentials: 'same-origin'` est indispensable : sans les cookies, le
	 * panier et la session WooCommerce du second appel ne seraient pas ceux
	 * du premier, et le checkout porterait sur un panier vide.
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
		var qtyField = form.querySelector( '[name="famma_quantity"]' );
		var quantity = qtyField && qtyField.value ? qtyField.value : '1';
		var unit = form.querySelector( '.famma-express__total' );
		var unitPrice = unit ? parseFloat( unit.getAttribute( 'data-unit-price' ) || '0' ) : 0;

		var cart = new FormData();
		cart.append( 'product_id', productId );
		cart.append( 'quantity', quantity );

		post( fammaExpress.addToCartUrl, cart ).then( function ( added ) {
			if ( ! added || added.error ) {
				/*
				 * `add_to_cart` signale un refus par `error: true` et renvoie
				 * l'URL du produit. Le motif exact (stock, variation) n'est
				 * pas dans la réponse : on ouvre donc le parcours classique
				 * plutôt que d'inventer une explication.
				 */
				release();
				showError( fammaExpress.genericError );
				return;
			}

			pixel( 'AddToCart', {
				content_type: 'product',
				content_ids: [ String( productId ) ],
				contents: [ { id: String( productId ), quantity: parseFloat( quantity ) } ],
				value: unitPrice * parseFloat( quantity ),
				currency: fammaExpress.currency,
			} );

			var order = new FormData( form );

			/*
			 * Ces deux champs pilotent le formulaire, pas la commande :
			 * la quantité a déjà servi à l'ajout au panier, et l'identifiant
			 * produit n'a rien à dire à `process_checkout()`.
			 */
			order.delete( 'famma_quantity' );
			order.delete( 'famma_product_id' );

			pixel( 'InitiateCheckout', {
				content_type: 'product',
				content_ids: [ String( productId ) ],
				num_items: parseFloat( quantity ),
				value: unitPrice * parseFloat( quantity ),
				currency: fammaExpress.currency,
			} );

			/*
			 * Le jeton n'est demandé qu'ICI, et l'ordre est impératif : il
			 * n'est valable qu'une fois la session du visiteur créée, ce que
			 * fait l'ajout au panier qui précède. Demandé plus tôt, il serait
			 * calculé sur une session qui n'existe pas encore et le checkout
			 * le refuserait. Voir `Express_Order::send_nonce()`.
			 */
			return post( fammaExpress.nonceUrl, new FormData() ).then( function ( token ) {
				if ( ! token || ! token.success || ! token.data || ! token.data.nonce ) {
					release();
					showError( fammaExpress.genericError );
					return;
				}

				order.append( 'woocommerce-process-checkout-nonce', token.data.nonce );

				return post( fammaExpress.checkoutUrl, order );
			} ).then( function ( result ) {
				if ( 'undefined' === typeof result ) {
					// Le jeton a échoué : le message est déjà affiché.
					return;
				}
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
			} );
		} ).catch( function () {
			release();
			showError( fammaExpress.genericError );
		} );
	} );
} )();
