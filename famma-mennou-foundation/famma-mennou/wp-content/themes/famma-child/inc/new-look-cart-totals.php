<?php
/**
 * Nouveau look — panier, étape 2 du lot « Panier » : cases `cart_split` et
 * `cart_dock` (maquette panier du 14/11).
 *
 * - `cart_split` : deux tuiles « Aujourd'hui 0 DT » / « À la réception »
 *   juste avant le bouton de commande. Posées dans `.cart_totals`, que
 *   WooCommerce redessine à chaque changement de quantité : le total est
 *   toujours le sien, sans script.
 * - `cart_dock` : barre du bas sur téléphone, rendue en pied de page avec le
 *   panier courant (la page panier n'est jamais servie depuis le cache) ;
 *   `new-look-cart.js` recopie le total après chaque mise à jour et l'efface
 *   quand le vrai bouton est à l'écran.
 *
 * « 0 DT aujourd'hui » n'est affiché que si le paiement à la livraison est le
 * seul moyen proposé (`famma_child_cart_cod_only()`).
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Libellé des tuiles et de la barre, avec repli sur le texte du thème.
 *
 * @param string $key      Clé de `Cart_Content::schema()`.
 * @param string $fallback Texte du thème si famma-core est absent.
 * @return string
 */
function famma_child_cart_label( string $key, string $fallback ): string {
	if ( ! class_exists( '\Famma\Core\Cart_Content' ) ) {
		return $fallback;
	}

	return famma_child_cart_text( $key );
}

/**
 * Tuiles « Aujourd'hui 0 DT » / « À la réception », dans le récapitulatif.
 *
 * Priorité 5 : avant le bouton (20) et la réassurance (30).
 *
 * @return void
 */
function famma_child_cart_split(): void {
	if ( ! famma_child_cart_part( 'cart_split' ) || ! famma_child_cart_cod_only() || ! WC()->cart ) {
		return;
	}

	$now = famma_child_cart_label( 'split_now', __( 'Aujourd’hui', 'famma-child' ) );
	$rec = famma_child_cart_label( 'split_rec', __( 'À la réception', 'famma-child' ) );

	if ( '' === $now || '' === $rec ) {
		return;
	}

	$notes = array(
		'now' => famma_child_cart_text( 'split_now_note' ),
		'rec' => famma_child_cart_text( 'split_rec_note' ),
	);
	?>
	<div class="famma-cart-split">
		<p class="famma-cart-split__tile famma-cart-split__tile--now">
			<span class="famma-cart-split__label"><?php echo esc_html( $now ); ?></span>
			<b class="famma-cart-split__sum"><?php echo wp_kses_post( wc_price( 0 ) ); ?></b>
			<?php if ( '' !== $notes['now'] ) : ?>
				<span class="famma-cart-split__note"><?php echo esc_html( $notes['now'] ); ?></span>
			<?php endif; ?>
		</p>
		<p class="famma-cart-split__tile famma-cart-split__tile--rec">
			<span class="famma-cart-split__label"><?php echo esc_html( $rec ); ?></span>
			<b class="famma-cart-split__sum"><?php echo wp_kses_post( WC()->cart->get_total() ); ?></b>
			<?php if ( '' !== $notes['rec'] ) : ?>
				<span class="famma-cart-split__note"><?php echo esc_html( $notes['rec'] ); ?></span>
			<?php endif; ?>
		</p>
	</div>
	<?php
}
add_action( 'woocommerce_proceed_to_checkout', 'famma_child_cart_split', 5 );

/**
 * Barre du bas du panier, sur téléphone.
 *
 * Masquée (`hidden`) quand le panier est vide ; le script la masque aussi
 * quand le dernier article est retiré sans recharger la page.
 *
 * @return void
 */
function famma_child_cart_dock(): void {
	if ( ! famma_child_cart_part( 'cart_dock' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	$empty = WC()->cart->is_empty();
	$cod   = famma_child_cart_cod_only();
	$now   = $cod ? famma_child_cart_label( 'split_now', __( 'Aujourd’hui', 'famma-child' ) ) : '';
	$rec   = $cod ? famma_child_cart_label( 'split_rec', __( 'À la réception', 'famma-child' ) ) : '';
	$go    = famma_child_cart_text( 'dock_go' );
	?>
	<div class="famma-cart-dock" data-famma-cart-dock<?php echo $empty ? ' hidden' : ''; ?>>
		<p class="famma-cart-dock__sums">
			<?php if ( '' !== $now ) : ?>
				<span class="famma-cart-dock__row">
					<span class="famma-cart-dock__label"><?php echo esc_html( $now ); ?></span>
					<b class="famma-cart-dock__zero"><?php echo wp_kses_post( wc_price( 0 ) ); ?></b>
				</span>
			<?php endif; ?>
			<span class="famma-cart-dock__row">
				<span class="famma-cart-dock__label"><?php echo esc_html( '' !== $rec ? $rec : __( 'Total', 'famma-child' ) ); ?></span>
				<b class="famma-cart-dock__total" data-famma-cart-dock-total><?php echo $empty ? '' : wp_kses_post( WC()->cart->get_total() ); ?></b>
			</span>
		</p>
		<a class="famma-cart-dock__go" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">
			<span><?php echo esc_html( '' !== $go ? $go : __( 'Commander', 'famma-child' ) ); ?></span>
			<?php echo wp_kses( famma_child_icon( 'forward' ), famma_child_svg_allowed_html() ); ?>
		</a>
	</div>
	<?php
}
add_action( 'wp_footer', 'famma_child_cart_dock', 5 );
