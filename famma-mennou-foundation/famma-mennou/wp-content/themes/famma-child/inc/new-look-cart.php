<?php
/**
 * Nouveau look — panier, lot « Panier » (maquette panier du 14/11) : socle
 * commun et étape 1, case `cart_look` (suite dans inc/new-look-cart-head.php :
 * titre, promesses, aide WhatsApp). Étape 2 (`cart_split`, `cart_dock`) :
 * inc/new-look-cart-totals.php ; étape 3 (`cart_promo`, `cart_save`) :
 * inc/new-look-cart-keep.php.
 *
 * Présentation seulement, par-dessus `inc/cart.php` et `cart.css` qui restent
 * en place : la feuille `new-look-cart.css` réarrange le tableau de
 * WooCommerce (aucun gabarit copié), ce fichier n'ajoute que :
 * - la phrase « Aujourd'hui, vous payez 0 DT » sous l'en-tête, seulement si le
 *   paiement à la livraison est activé, « 0 DT » en vert ;
 * - le panier vide avec la mascotte triste, à la place de l'icône.
 *
 * Les textes sont ceux de FAMMA → Nouveau look → Panier
 * (`Famma\Core\Cart_Content`). Case décochée : le panier est celui d'avant,
 * octet pour octet.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La brique du panier est-elle servie sur cette page ?
 *
 * @param string $part Clé de brique.
 * @return bool
 */
function famma_child_cart_part( string $part ): bool {
	return function_exists( 'is_cart' ) && is_cart() && in_array( $part, famma_child_new_look_parts(), true );
}

/**
 * Texte du panier réglé dans FAMMA → Nouveau look.
 *
 * @param string $key Clé de `Cart_Content::schema()`.
 * @return string Chaîne vide si famma-core est absent ou le texte vidé.
 */
function famma_child_cart_text( string $key ): string {
	return class_exists( '\Famma\Core\Cart_Content' ) ? \Famma\Core\Cart_Content::text( $key ) : '';
}

/**
 * « 0 DT aujourd'hui » est-il vrai pour ce panier ?
 *
 * Oui seulement si le paiement à la livraison est le seul moyen de paiement
 * proposé : avec un paiement en ligne possible, la phrase serait fausse pour
 * une partie des clients. Lu une fois par requête.
 *
 * @return bool
 */
function famma_child_cart_cod_only(): bool {
	static $only = null;

	if ( null !== $only ) {
		return $only;
	}

	$only = false;

	if ( function_exists( 'famma_child_cod_is_enabled' ) && famma_child_cod_is_enabled() && function_exists( 'WC' ) && WC()->payment_gateways() ) {
		$only = array( 'cod' ) === array_keys( WC()->payment_gateways()->get_available_payment_gateways() );
	}

	return $only;
}

/**
 * Phrase « Aujourd'hui, vous payez 0 DT » en tête du panier.
 *
 * Priorité 5 : avant les messages de WooCommerce (10). Le panier vide ne
 * passe pas par ce crochet : la phrase disparaît avec le dernier article.
 *
 * @return void
 */
function famma_child_cart_lead(): void {
	if ( ! famma_child_cart_part( 'cart_look' ) || ! famma_child_cart_cod_only() ) {
		return;
	}

	$lead = famma_child_cart_text( 'lead' );

	if ( '' === $lead ) {
		return;
	}

	/*
	 * « 0 DT » en vert gras, comme la maquette : le premier montant nul de la
	 * phrase réglée dans l'administration, en français ou en derja. Le texte
	 * est échappé avant ; seule la balise <strong> est ajoutée.
	 */
	$html = preg_replace( '/(?<![\d.,])0(?:[.,]0+)?\s?(?:DT|دينار|د\.ت)/u', '<strong class="famma-cart-lead__zero">$0</strong>', esc_html( $lead ), 1 );

	printf(
		'<p class="famma-cart-lead">%s</p>',
		wp_kses(
			is_string( $html ) ? $html : esc_html( $lead ),
			array( 'strong' => array( 'class' => true ) )
		)
	);
}
add_action( 'woocommerce_before_cart', 'famma_child_cart_lead', 5 );

/**
 * Panier vide de la maquette : mascotte triste, titre, phrase.
 *
 * Garde la classe `wc-empty-cart-message` (voir `famma_child_cart_empty()`).
 *
 * @return void
 */
function famma_child_cart_empty_look(): void {
	$title = famma_child_cart_text( 'empty_title' );
	$sub   = famma_child_cart_text( 'empty_text' );
	?>
	<div class="wc-empty-cart-message famma-cart-empty famma-cart-empty--look" role="status">
		<?php echo famma_child_account_mascot( 'sad' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passé par wp_kses() dans famma_child_account_mascot(). ?>
		<p class="famma-cart-empty__title"><?php echo esc_html( '' !== $title ? $title : __( 'Votre panier est vide', 'famma-child' ) ); ?></p>
		<p class="famma-cart-empty__sub"><?php echo esc_html( '' !== $sub ? $sub : __( 'Parcourez la boutique et ajoutez vos premiers produits.', 'famma-child' ) ); ?></p>
	</div>
	<?php
}

/**
 * Branche le panier vide de la maquette quand la case est cochée.
 *
 * Priorité 11 sur `wp` : après `famma_child_cart_swap_empty_message()` (10).
 *
 * @return void
 */
function famma_child_cart_swap_empty_look(): void {
	if ( ! famma_child_cart_part( 'cart_look' ) || ! function_exists( 'famma_child_account_mascot' ) ) {
		return;
	}

	remove_action( 'woocommerce_cart_is_empty', 'famma_child_cart_empty', 10 );
	add_action( 'woocommerce_cart_is_empty', 'famma_child_cart_empty_look', 10 );
}
add_action( 'wp', 'famma_child_cart_swap_empty_look', 11 );

/**
 * Charge la feuille du panier (une pour toutes les cases, derrière celle
 * d'aujourd'hui) et son script (barre du bas, code promo, mise de côté).
 *
 * @return void
 */
function famma_child_cart_enqueue_look(): void {
	if ( ! array_filter( famma_child_new_look_cart_parts(), 'famma_child_cart_part' ) ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = '/assets/css/new-look-cart.css';
	$js  = '/assets/js/new-look-cart.js';

	if ( file_exists( $dir . $css ) ) {
		wp_enqueue_style(
			'famma-new-look-cart',
			$uri . $css,
			wp_style_is( 'famma-cart', 'registered' ) ? array( 'famma-cart' ) : array( 'famma-layout' ),
			(string) filemtime( $dir . $css )
		);
	}

	if ( array_filter( array( 'cart_dock', 'cart_promo', 'cart_save' ), 'famma_child_cart_part' ) && file_exists( $dir . $js ) ) {
		wp_enqueue_script(
			'famma-new-look-cart',
			$uri . $js,
			array(),
			(string) filemtime( $dir . $js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'famma_child_cart_enqueue_look', 24 );
