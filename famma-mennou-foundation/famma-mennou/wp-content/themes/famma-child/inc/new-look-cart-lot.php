<?php
/**
 * Nouveau look — panier, étape 4 du lot « Panier » : case `cart_lot`
 * (maquette panier du 14/11).
 *
 * Présentation seulement : le couple, le calcul et l'échange sont dans
 * famma-core (`Famma\Core\Cart_Lot`). La proposition est posée dans la ligne,
 * donc redessinée par WooCommerce à chaque changement de quantité. Le bouton
 * est un lien protégé par le jeton du panier (`Cart_Lot::url()`), comme la
 * corbeille : il marche sans script.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remplace les repères d'une phrase du lot. Le texte est échappé avant ;
 * seuls les montants (balisage de `wc_price()`) sont ajoutés en HTML.
 *
 * @param string                $text   Phrase réglée dans l'admin.
 * @param array<string, string> $tokens Repère => HTML déjà sûr.
 * @return string
 */
function famma_child_cart_lot_fill( string $text, array $tokens ): string {
	return strtr( esc_html( $text ), $tokens );
}

/**
 * Proposition « Passer au lot » sous le nom de l'article.
 *
 * Priorité 25 : après « Mettre de côté » (20).
 *
 * @param array<string, mixed> $cart_item     Ligne du panier.
 * @param string               $cart_item_key Clé de la ligne.
 * @return void
 */
function famma_child_cart_lot( $cart_item, $cart_item_key = '' ): void {
	if ( ! famma_child_cart_part( 'cart_lot' ) || ! class_exists( '\Famma\Core\Cart_Lot' ) || ! is_array( $cart_item ) || '' === $cart_item_key ) {
		return;
	}

	$offer = \Famma\Core\Cart_Lot::offer( $cart_item );

	if ( array() === $offer ) {
		return;
	}

	$text = \Famma\Core\Cart_Lot::text( absint( $cart_item['product_id'] ) );
	$text = '' !== $text ? $text : famma_child_cart_text( 'lot_q' );
	$go   = famma_child_cart_text( 'lot_go' );
	$keep = famma_child_cart_text( 'lot_keep' );

	$tokens = array(
		'{n}' => esc_html( (string) $offer['size'] ),
		'{a}' => '<b>' . wc_price( $offer['lot_price'] ) . '</b>',
		'{b}' => '<b>' . wc_price( $offer['unit_total'] ) . '</b>',
		'{m}' => wc_price( $offer['gain'] ),
	);

	$url = \Famma\Core\Cart_Lot::url( (string) $cart_item_key, (int) $cart_item['quantity'] );
	?>
	<div class="famma-cart-lot">
		<?php if ( '' !== $text ) : ?>
			<p class="famma-cart-lot__q">
				<?php echo wp_kses( famma_child_icon( 'box' ), famma_child_svg_allowed_html() ); ?>
				<span><?php echo wp_kses_post( famma_child_cart_lot_fill( $text, $tokens ) ); ?></span>
			</p>
		<?php endif; ?>
		<a class="famma-cart-lot__go" href="<?php echo esc_url( $url ); ?>" rel="nofollow">
			<span><?php echo esc_html( '' !== $go ? $go : __( 'Passer au lot', 'famma-child' ) ); ?></span>
			<?php if ( '' !== $keep ) : ?>
				<b class="famma-cart-lot__keep"><?php echo wp_kses_post( famma_child_cart_lot_fill( $keep, $tokens ) ); ?></b>
			<?php endif; ?>
		</a>
	</div>
	<?php
}
add_action( 'woocommerce_after_cart_item_name', 'famma_child_cart_lot', 25, 2 );
