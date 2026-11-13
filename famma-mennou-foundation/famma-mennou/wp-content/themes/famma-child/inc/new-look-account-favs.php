<?php
/**
 * Nouveau look — onglet « Ma sélection » de « Mon compte », comme la maquette.
 *
 * Case `acct_favs` (lot 5 du plan de portage de l'espace client), avec
 * `acct_look`. La sélection, sa route et ses textes viennent de famma-core
 * (`Famma\Core\Account_Favs`) ; ce fichier ne fait que dessiner : une carte
 * par produit (photo, nom, prix, « Ajouter » ou « Choisir »), le cœur pour le
 * retirer (avec « Annuler »), et la mascotte quand la sélection est vide.
 *
 * Case décochée ou plugin absent : aucun crochet n'est posé.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * L'onglet « Ma sélection » est-il en place pour la personne en cours ?
 *
 * @return bool
 */
function famma_child_account_favs_on(): bool {
	return is_user_logged_in()
		&& famma_child_new_look( 'acct_look' )
		&& famma_child_new_look( 'acct_favs' )
		&& class_exists( '\Famma\Core\Account_Favs' );
}

/**
 * La page courante est-elle l'onglet « Ma sélection » ?
 *
 * @return bool
 */
function famma_child_account_favs_page(): bool {
	return famma_child_account_favs_on()
		&& function_exists( 'is_wc_endpoint_url' )
		&& is_wc_endpoint_url( \Famma\Core\Account_Favs::ENDPOINT );
}

/**
 * Un texte de l'onglet.
 *
 * @param string $key Clé du champ.
 * @return string
 */
function famma_child_account_favs_text( string $key ): string {
	return \Famma\Core\Account_Favs::text( $key );
}

/**
 * Onglet « Ma sélection » entre « Mes adresses » et « Mes informations »,
 * comme la maquette.
 *
 * @param array<string, string> $items Liens du menu du compte.
 * @return array<string, string>
 */
function famma_child_account_favs_menu( array $items ): array {
	if ( ! famma_child_account_favs_on() ) {
		return $items;
	}

	$label = famma_child_account_favs_text( 'tab_favs' );
	$out   = array();
	$done  = false;

	foreach ( $items as $endpoint => $text ) {
		if ( 'edit-account' === $endpoint && ! $done ) {
			$out[ \Famma\Core\Account_Favs::ENDPOINT ] = $label;
			$done                                      = true;
		}
		$out[ $endpoint ] = $text;
	}

	if ( ! $done ) {
		$out[ \Famma\Core\Account_Favs::ENDPOINT ] = $label;
	}

	return $out;
}
add_filter( 'woocommerce_account_menu_items', 'famma_child_account_favs_menu', 25 );

/**
 * Pose les crochets de l'onglet, seulement quand la case est cochée.
 *
 * @return void
 */
function famma_child_account_favs_setup(): void {
	if ( ! famma_child_account_favs_on() ) {
		return;
	}

	add_action( 'woocommerce_account_' . \Famma\Core\Account_Favs::ENDPOINT . '_endpoint', 'famma_child_account_favs_panel' );
	add_filter( 'woocommerce_endpoint_' . \Famma\Core\Account_Favs::ENDPOINT . '_title', 'famma_child_account_favs_title' );

	if ( famma_child_account_favs_page() ) {
		add_filter( 'body_class', 'famma_child_account_favs_body_class' );
		add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_favs', 28 );
	}
}
add_action( 'template_redirect', 'famma_child_account_favs_setup' );

/**
 * Titre de la page (onglet du navigateur).
 *
 * @param string $title Titre de WooCommerce.
 * @return string
 */
function famma_child_account_favs_title( $title ) {
	$ours = famma_child_account_favs_text( 'tab_favs' );

	return '' !== $ours ? $ours : $title;
}

/**
 * Classe du corps pour la feuille de style de l'onglet.
 *
 * @param array<int, string> $classes Classes du corps.
 * @return array<int, string>
 */
function famma_child_account_favs_body_class( array $classes ): array {
	$classes[] = 'famma-nl-acct-favs';

	return $classes;
}

/**
 * Feuille, script de l'onglet, et ceux des cœurs et de l'ajout au panier.
 *
 * @return void
 */
function famma_child_enqueue_account_favs(): void {
	$dir  = get_stylesheet_directory();
	$uri  = get_stylesheet_directory_uri();
	$css  = $dir . '/assets/css/new-look-account-favs.css';
	$js   = $dir . '/assets/js/new-look-account-favs.js';
	$favs = $dir . '/assets/js/favourites.js';

	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'famma-new-look-account-favs', $uri . '/assets/css/new-look-account-favs.css', array( 'famma-new-look-account' ), (string) filemtime( $css ) );
	}

	if ( file_exists( $favs ) ) {
		wp_enqueue_script( 'famma-favourites', $uri . '/assets/js/favourites.js', array(), (string) filemtime( $favs ), true );
	}

	if ( file_exists( $js ) ) {
		wp_enqueue_script(
			'famma-new-look-account-favs',
			$uri . '/assets/js/new-look-account-favs.js',
			array( 'famma-favourites' ),
			(string) filemtime( $js ),
			true
		);
	}

	// « Ajouter » met au panier sans quitter la page, comme sur la boutique.
	wp_enqueue_script( 'wc-add-to-cart' );
}

/**
 * Bouton d'une carte : « Ajouter » pour un produit simple en stock, sinon
 * « Choisir » vers la fiche (tailles, couleurs, rupture).
 *
 * @param WC_Product $product Produit.
 * @return string
 */
function famma_child_account_favs_button( WC_Product $product ): string {
	$icon = wp_kses( famma_child_icon( 'cart' ), famma_child_svg_allowed_html() );

	if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) {
		return sprintf(
			'<a href="%1$s" data-quantity="1" data-product_id="%2$s" data-product_sku="%3$s" class="button famma-acct-fv__add add_to_cart_button ajax_add_to_cart" rel="nofollow">%4$s<span>%5$s</span></a>',
			esc_url( $product->add_to_cart_url() ),
			esc_attr( (string) $product->get_id() ),
			esc_attr( (string) $product->get_sku() ),
			$icon,
			esc_html( famma_child_account_favs_text( 'fav_add' ) )
		);
	}

	return sprintf(
		'<a href="%1$s" class="button famma-acct-fv__add is-choose">%2$s</a>',
		esc_url( $product->get_permalink() ),
		esc_html( famma_child_account_favs_text( 'fav_choose' ) )
	);
}

/**
 * Le panneau « Ma sélection ».
 *
 * Le panneau montre la sélection du compte ; le script de famma-core
 * l'accorde avec le navigateur à l'arrivée, et le script de l'onglet recharge
 * la page une fois si l'accord a changé la liste.
 *
 * @return void
 */
function famma_child_account_favs_panel(): void {
	$ids   = \Famma\Core\Account_Favs::ids( get_current_user_id() );
	$svg   = famma_child_svg_allowed_html();
	$title = famma_child_account_favs_text( 'tab_favs' );
	$shop  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

	if ( '' !== $title ) {
		echo '<h2 class="famma-acct-p-h">' . esc_html( $title ) . '</h2>';
	}

	printf(
		'<div class="famma-acct-fempty"%1$s>%2$s<p>%3$s</p><a class="button famma-acct-fempty__go" href="%4$s">%5$s</a></div>',
		empty( $ids ) ? '' : ' hidden',
		function_exists( 'famma_child_account_mascot' ) ? famma_child_account_mascot() : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passé par wp_kses dans famma_child_account_mascot().
		esc_html( famma_child_account_favs_text( 'fav_empty' ) ),
		esc_url( $shop ),
		esc_html( famma_child_account_favs_text( 'fav_go' ) )
	);

	printf(
		'<div class="famma-acct-favs" data-famma-favs-panel data-ids="%1$s" data-gone="%2$s" data-undo="%3$s">',
		esc_attr( implode( ',', $ids ) ),
		esc_attr( famma_child_account_favs_text( 'fav_gone' ) ),
		esc_attr( famma_child_account_favs_text( 'fav_undo' ) )
	);

	foreach ( $ids as $i => $id ) {
		$product = wc_get_product( $id );

		if ( ! $product instanceof WC_Product ) {
			continue;
		}

		$link = $product->get_permalink();

		printf(
			'<article class="famma-acct-fv" data-id="%1$s" style="--i:%2$d"><div class="famma-acct-fv__m"><a href="%3$s" tabindex="-1" aria-hidden="true">%4$s</a><button type="button" class="famma-acct-heart is-active" data-famma-fav="%1$s" aria-pressed="true" aria-label="%5$s">%6$s</button></div><div class="famma-acct-fv__b"><p dir="auto"><a href="%3$s">%7$s</a></p><b class="famma-acct-fv__p">%8$s</b>%9$s</div></article>',
			esc_attr( (string) $id ),
			(int) min( $i, 8 ),
			esc_url( $link ),
			wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) ),
			esc_attr( famma_child_account_favs_text( 'fav_rm' ) ),
			wp_kses( famma_child_icon( 'heart' ), $svg ),
			esc_html( $product->get_name() ),
			wp_kses_post( $product->get_price_html() ),
			famma_child_account_favs_button( $product ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chaque partie est échappée dans famma_child_account_favs_button().
		);
	}

	echo '</div>';
	echo '<div class="famma-acct-toast" role="status" hidden><span></span><button type="button"></button></div>';
}
