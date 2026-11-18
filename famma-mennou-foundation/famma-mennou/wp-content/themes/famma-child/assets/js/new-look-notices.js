/**
 * FAMMA — messages du site (brique `notices`).
 *
 * 1. Bandeau « Ajouté au panier » (`.famma-added`, posé par famma-core) :
 *    croix pour le fermer, fermeture seule au bout de 5 secondes, minuteur
 *    en pause tant que la souris ou le clavier est dessus.
 * 2. Commande : WooCommerce marque déjà un champ mal rempli quand le client
 *    le quitte (classe `woocommerce-invalid`), sans rien écrire. Ce script
 *    écrit la phrase sous le champ (textes de FAMMA → Nouveau look), vérifie
 *    le téléphone tunisien (8 chiffres, comme famma-core) et, après
 *    « Commander », retire de la carte du haut les erreurs déjà affichées
 *    sous leur champ, puis amène le client au premier champ à corriger.
 *
 * Textes : `fammaNotices` (famma-core, `Site_Notices::strings()`). Un texte
 * vide laisse WooCommerce faire comme avant.
 */
( function ( $ ) {
	'use strict';

	var T = window.fammaNotices || {};
	var DELAY = 5000;
	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var FIELDS = {
		first_name: 'err_name',
		phone: 'err_phone',
		state: 'err_state',
		address_1: 'err_address',
		city: 'err_city',
		email: 'err_email_account',
	};
	var DIGITS = { '٠': 0, '١': 1, '٢': 2, '٣': 3, '٤': 4, '٥': 5, '٦': 6, '٧': 7, '٨': 8, '٩': 9, '۰': 0, '۱': 1, '۲': 2, '۳': 3, '۴': 4, '۵': 5, '۶': 6, '۷': 7, '۸': 8, '۹': 9 };

	/* ---------- 1. Bandeau « Ajouté au panier » ---------- */

	/**
	 * Ferme un bandeau, en fondu si le visiteur accepte les animations.
	 *
	 * @param {HTMLElement} box Le message.
	 * @return {void}
	 */
	function leave( box ) {
		if ( ! box.isConnected ) {
			return;
		}

		if ( reduce ) {
			box.remove();
			return;
		}

		box.classList.add( 'is-leaving' );
		window.setTimeout( function () {
			box.remove();
		}, 220 );
	}

	/**
	 * Croix et minuteur sur un bandeau « Ajouté au panier ».
	 *
	 * @param {HTMLElement} box Le message.
	 * @return {void}
	 */
	function armAdded( box ) {
		var timer = null;
		var close = document.createElement( 'button' );

		box.classList.add( 'famma-notice--added' );
		close.type = 'button';
		close.className = 'famma-notice__close';
		close.setAttribute( 'aria-label', T.close || 'Fermer' );
		close.addEventListener( 'click', function () {
			leave( box );
		} );
		box.appendChild( close );

		function start() {
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				leave( box );
			}, DELAY );
		}

		function stop() {
			window.clearTimeout( timer );
		}

		box.addEventListener( 'mouseenter', stop );
		box.addEventListener( 'mouseleave', start );
		// WooCommerce donne le focus au message à l'ouverture (lecteurs d'écran) :
		// seul le focus sur le lien ou la croix compte comme « le client y est ».
		box.addEventListener( 'focusin', function ( event ) {
			if ( event.target !== box ) {
				stop();
			}
		} );
		box.addEventListener( 'focusout', function ( event ) {
			if ( event.target !== box ) {
				start();
			}
		} );
		start();
	}

	document.querySelectorAll( '.woocommerce-message' ).forEach( function ( box ) {
		if ( box.querySelector( '.famma-added' ) ) {
			armAdded( box );
		}
	} );

	/* ---------- 2. Commande : erreur sous le champ ---------- */

	/**
	 * Le téléphone est-il un numéro tunisien ? Même règle que famma-core.
	 *
	 * @param {string} raw Saisie.
	 * @return {boolean}
	 */
	function tunisian( raw ) {
		var digits = String( raw ).replace( /[٠-٩۰-۹]/g, function ( d ) {
			return String( DIGITS[ d ] );
		} ).replace( /\D+/g, '' );

		if ( 13 === digits.length && 0 === digits.indexOf( '00216' ) ) {
			digits = digits.slice( 5 );
		} else if ( 11 === digits.length && 0 === digits.indexOf( '216' ) ) {
			digits = digits.slice( 3 );
		}

		return /^[2-9]\d{7}$/.test( digits );
	}

	/**
	 * Texte à écrire sous un champ marqué par WooCommerce.
	 *
	 * @param {jQuery} $row   Ligne du champ.
	 * @param {string} id     Identifiant du champ.
	 * @return {string}
	 */
	function textFor( $row, id ) {
		var suffix = id.replace( /^(billing|shipping)_/, '' );

		if ( 'terms' === id ) {
			return T.err_terms || '';
		}
		if ( $row.hasClass( 'woocommerce-invalid-required-field' ) ) {
			return T[ FIELDS[ suffix ] || 'err_field' ] || '';
		}
		if ( $row.hasClass( 'woocommerce-invalid-phone' ) ) {
			return T.err_phone_bad || '';
		}
		if ( $row.hasClass( 'woocommerce-invalid-email' ) ) {
			return T.err_email_bad || '';
		}

		return '';
	}

	/**
	 * Écrit ou efface la phrase sous un champ.
	 *
	 * @param {jQuery} $field Le champ.
	 * @return {void}
	 */
	function inline( $field ) {
		var $row = $field.closest( '.form-row' );
		var id = $field.attr( 'id' ) || '';
		var text;
		var p;

		if ( ! $row.length || ! id ) {
			return;
		}

		// Téléphone : 8 chiffres tunisiens, au-delà du contrôle de WooCommerce.
		if ( /(^|_)phone$/.test( id ) && $field.val() && ! tunisian( $field.val() ) ) {
			$field.attr( 'aria-invalid', 'true' );
			$row.removeClass( 'woocommerce-validated' ).addClass( 'woocommerce-invalid woocommerce-invalid-phone' );
		}

		$row.find( '.checkout-inline-error-message' ).remove();

		if ( ! $row.hasClass( 'woocommerce-invalid' ) ) {
			return;
		}

		text = textFor( $row, id );
		if ( ! text ) {
			return;
		}

		p = document.createElement( 'p' );
		p.id = id + '_description';
		p.className = 'checkout-inline-error-message';
		p.textContent = text;
		$row.append( p );
		$field.attr( 'aria-describedby', p.id );
	}

	// Après le contrôle de WooCommerce (lié au formulaire, donc déclenché avant).
	$( document ).on( 'validate change focusout', 'form.checkout .input-text, form.checkout select, form.checkout input:checkbox', function () {
		inline( $( this ) );
	} );

	/**
	 * Après « Commander » : carte du haut réduite aux erreurs sans champ,
	 * une seule phrase par champ, défilement jusqu'au premier champ fautif.
	 *
	 * @return {void}
	 */
	function afterError() {
		var $group = $( '.woocommerce-NoticeGroup-checkout' );
		var $first;
		var field;
		var top;

		$group.find( 'li' ).each( function () {
			var $li = $( this );
			var id = $li.attr( 'data-id' ) || ( $li.find( 'a[href^="#"]' ).attr( 'href' ) || '' ).slice( 1 );

			if ( id && document.getElementById( id ) ) {
				$li.remove();
			}
		} );

		$group.find( 'ul.woocommerce-error' ).each( function () {
			if ( ! $( this ).children( 'li' ).length ) {
				$( this ).remove();
			}
		} );

		if ( ! $.trim( $group.text() ) ) {
			$group.remove();
		}

		// WooCommerce et ce script ont pu écrire chacun une phrase : garder la dernière (celle du serveur).
		$( 'form.checkout .form-row' ).each( function () {
			$( this ).children( '.checkout-inline-error-message' ).slice( 0, -1 ).remove();
		} );

		if ( $( '.woocommerce-NoticeGroup-checkout' ).length ) {
			return;
		}

		$first = $( 'form.checkout .form-row.woocommerce-invalid' ).first();
		if ( ! $first.length ) {
			return;
		}

		top = $first.offset().top - 120;
		window.scrollTo( { top: Math.max( 0, top ), behavior: reduce ? 'auto' : 'smooth' } );
		field = $first.find( 'input, select, textarea' ).get( 0 );
		if ( field ) {
			field.focus( { preventScroll: true } );
		}
	}

	// `checkout_error` part avant que WooCommerce n'écrive ses phrases sous les champs.
	$( document.body ).on( 'checkout_error', function () {
		window.setTimeout( afterError, 0 );
	} );
}( jQuery ) );
