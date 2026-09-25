/**
 * Onglets de la fiche produit — les deux cas que WooCommerce ne couvre pas.
 *
 * Les onglets sont ceux de WooCommerce, et `single-product.js` les pilote :
 * un seul panneau visible, rôles ARIA, flèches du clavier, onglet Avis ouvert
 * sur `#reviews`. Ce script n'ajoute que :
 *
 * 1. l'ouverture de n'importe quel onglet par son ancre (`#tab-famma_faq`,
 *    `#tab-famma_shipping`…), à l'arrivée sur la page comme au clic sur un
 *    lien de la page — WooCommerce ne le fait que pour les avis ;
 * 2. sur mobile, le recentrage de l'onglet choisi dans la rangée, qui défile
 *    latéralement : sans lui, un onglet atteint au clavier ou par ancre peut
 *    rester hors de l'écran.
 *
 * Sans ce script, rien ne casse : les onglets restent tous atteignables.
 */
jQuery( function ( $ ) {
	'use strict';

	var $wrapper = $( '.woocommerce-tabs.wc-tabs-wrapper' ).first();

	if ( ! $wrapper.length ) {
		return;
	}

	/**
	 * Lien d'onglet correspondant à une ancre `#tab-<clé>`.
	 *
	 * @param {string} hash Ancre, avec son dièse.
	 * @return {jQuery} Lien trouvé, ou collection vide.
	 */
	function tabFor( hash ) {
		if ( ! hash || 0 !== hash.indexOf( '#tab-' ) ) {
			return $();
		}

		return $wrapper.find( 'ul.tabs a[role="tab"]' ).filter( function () {
			return this.hash === hash;
		} );
	}

	// Ramène l'onglet choisi dans la partie visible de la rangée.
	$wrapper.on( 'click', 'ul.tabs a[role="tab"]', function () {
		if ( 'function' === typeof this.scrollIntoView ) {
			this.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
		}
	} );

	// Liens de la page vers un onglet (`<a href="#tab-famma_faq">`).
	$( document.body ).on( 'click', 'a[href*="#tab-"]', function ( event ) {
		if ( $( this ).closest( 'ul.tabs' ).length ) {
			return;
		}

		var $tab = tabFor( this.hash );

		if ( ! $tab.length ) {
			return;
		}

		event.preventDefault();
		$tab.trigger( 'click' );
		$wrapper[ 0 ].scrollIntoView( { block: 'start' } );
	} );

	// Arrivée directe avec l'ancre d'un onglet autre que les avis.
	var $initial = tabFor( window.location.hash );

	if ( $initial.length && ! $initial.closest( 'li' ).hasClass( 'active' ) ) {
		$initial.trigger( 'click' );
	}
} );
