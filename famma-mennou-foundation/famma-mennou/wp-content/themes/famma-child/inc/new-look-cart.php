<?php
/**
 * Nouveau look — panier, étape 1 du lot « Panier » : case `cart_look`
 * (maquette panier du 14/11).
 *
 * Présentation seulement, par-dessus `inc/cart.php` et `cart.css` qui restent
 * en place : la feuille `new-look-cart.css` réarrange le tableau de
 * WooCommerce (aucun gabarit copié), ce fichier n'ajoute que :
 * - la phrase « Aujourd'hui, vous payez 0 DT » sous l'en-tête, seulement si le
 *   paiement à la livraison est activé ;
 * - le panier vide avec la mascotte, à la place de l'icône.
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
 * Phrase « Aujourd'hui, vous payez 0 DT » en tête du panier.
 *
 * Priorité 5 : avant les messages de WooCommerce (10). Le panier vide ne
 * passe pas par ce crochet : la phrase disparaît avec le dernier article.
 *
 * @return void
 */
function famma_child_cart_lead(): void {
	if ( ! famma_child_cart_part( 'cart_look' ) || ! function_exists( 'famma_child_cod_is_enabled' ) || ! famma_child_cod_is_enabled() ) {
		return;
	}

	$lead = famma_child_cart_text( 'lead' );

	if ( '' === $lead ) {
		return;
	}

	printf( '<p class="famma-cart-lead">%s</p>', esc_html( $lead ) );
}
add_action( 'woocommerce_before_cart', 'famma_child_cart_lead', 5 );

/**
 * Panier vide de la maquette : mascotte, titre, phrase.
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
		<?php echo famma_child_account_mascot(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passé par wp_kses() dans famma_child_account_mascot(). ?>
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
 * Charge la feuille du panier, derrière celle d'aujourd'hui.
 *
 * @return void
 */
function famma_child_cart_enqueue_look(): void {
	if ( ! famma_child_cart_part( 'cart_look' ) ) {
		return;
	}

	$dir  = get_stylesheet_directory();
	$file = '/assets/css/new-look-cart.css';

	if ( ! file_exists( $dir . $file ) ) {
		return;
	}

	wp_enqueue_style(
		'famma-new-look-cart',
		get_stylesheet_directory_uri() . $file,
		wp_style_is( 'famma-cart', 'registered' ) ? array( 'famma-cart' ) : array( 'famma-layout' ),
		(string) filemtime( $dir . $file )
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_cart_enqueue_look', 24 );
