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
 * Sans JavaScript, le formulaire est inerte — mais le parcours classique
 * (« Ajouter au panier » puis checkout) reste juste en dessous, intact. C'est
 * la raison pour laquelle le formulaire de WooCommerce n'a pas été supprimé.
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
