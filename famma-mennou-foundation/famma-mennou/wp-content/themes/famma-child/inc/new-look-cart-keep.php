<?php
/**
 * Nouveau look — panier, étape 3 du lot « Panier » : cases `cart_promo` et
 * `cart_save` (maquette panier du 14/11).
 *
 * Les deux boutons sont rendus avec `hidden` : seul `new-look-cart.js` les
 * montre. Sans script, le code promo reste ouvert comme aujourd'hui et
 * « Mettre de côté » n'existe pas (il a besoin du stockage du navigateur).
 *
 * - `cart_promo` : bouton « Vous avez un code promo ? » posé dans le cadre du
 *   code (`woocommerce_cart_coupon`) ; le script replie le reste du cadre.
 * - `cart_save` : bouton sous le nom de chaque article ; le script ajoute le
 *   produit à « Ma sélection » (`famma-favourites`, comme le cœur de la
 *   boutique) puis clique la corbeille de la ligne : WooCommerce retire
 *   l'article avec son message « Annuler ? » et ses jetons.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bouton qui déplie le code promo.
 *
 * Priorité 5 : avant le titre « Code promo » de `inc/cart.php` (10), que la
 * feuille cache quand le bouton est montré.
 *
 * @return void
 */
function famma_child_cart_promo_toggle(): void {
	if ( ! famma_child_cart_part( 'cart_promo' ) ) {
		return;
	}

	$label = famma_child_cart_text( 'promo_label' );
	?>
	<button type="button" class="famma-cart-promo" aria-expanded="true" aria-controls="coupon_code" data-famma-cart-promo hidden>
		<?php echo wp_kses( famma_child_icon( 'tag' ), famma_child_svg_allowed_html() ); ?>
		<span><?php echo esc_html( '' !== $label ? $label : __( 'Code promo', 'famma-child' ) ); ?></span>
		<?php echo wp_kses( famma_child_icon( 'chevron', 'famma-cart-promo__chev' ), famma_child_svg_allowed_html() ); ?>
	</button>
	<?php
}
add_action( 'woocommerce_cart_coupon', 'famma_child_cart_promo_toggle', 5 );

/**
 * Bouton « Mettre de côté » sous le nom de l'article.
 *
 * L'identifiant gardé est celui du produit (le parent pour une variante),
 * comme le cœur de la boutique.
 *
 * @param array<string, mixed> $cart_item Ligne du panier.
 * @return void
 */
function famma_child_cart_save_button( $cart_item ): void {
	if ( ! famma_child_cart_part( 'cart_save' ) ) {
		return;
	}

	$label = famma_child_cart_text( 'save_label' );
	$id    = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;

	if ( '' === $label || 0 === $id ) {
		return;
	}

	printf(
		'<button type="button" class="famma-cart-save" data-famma-cart-save="%1$s" data-done="%2$s" hidden>%3$s<span>%4$s</span></button>',
		esc_attr( (string) $id ),
		esc_attr( famma_child_cart_text( 'save_done' ) ),
		wp_kses( famma_child_icon( 'heart' ), famma_child_svg_allowed_html() ),
		esc_html( $label )
	);
}
add_action( 'woocommerce_after_cart_item_name', 'famma_child_cart_save_button', 20 );
