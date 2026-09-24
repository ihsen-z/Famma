<?php
/**
 * Panier — présentation de la maquette « FAMMA Panier ».
 *
 * Tout passe par les hooks et filtres de WooCommerce : aucun gabarit n'est
 * copié. Le tableau du panier reste celui de WooCommerce (ses mises à jour
 * AJAX, ses nonces, sa suppression de ligne) ; `cart.css` le redessine en
 * cartes, et ce fichier n'ajoute que ce que la maquette montre en plus :
 * référence, disponibilité, prix unitaire, sélecteur − / +, récapitulatif,
 * réassurance et encart WhatsApp.
 *
 * Écarts assumés avec la maquette, qui illustrait des fonctions absentes de la
 * boutique : pas de seuil de livraison gratuite ni de frais de livraison (la
 * livraison est toujours offerte au client, §4), pas de « retour gratuit sous
 * 7 jours » (aucune politique de retour n'est arrêtée, §60), pas de bouton
 * « Vider le panier » (WooCommerce n'en fournit pas).
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Détache le titre « Panier » que Kadence pose au-dessus du tableau.
 *
 * L'en-tête du parcours porte déjà le `<h1>` « Mon panier ».
 *
 * @return void
 */
function famma_child_cart_remove_parent_title(): void {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() || ! function_exists( 'famma_child_remove_parent_callback' ) ) {
		return;
	}

	famma_child_remove_parent_callback( 'woocommerce_before_cart_table', 'cart_summary_title' );
}
add_action( 'wp', 'famma_child_cart_remove_parent_title' );

/**
 * Active les filtres de ligne le temps du tableau du panier seulement.
 *
 * Les mêmes filtres servent au mini-panier de l'en-tête, rendu sur la même
 * page : branchés en permanence, ils y auraient ajouté « / unité » et
 * « Supprimer ».
 *
 * @return void
 */
function famma_child_cart_line_filters_on(): void {
	add_filter( 'woocommerce_cart_item_remove_link', 'famma_child_cart_remove_link', 10, 2 );
	add_filter( 'woocommerce_cart_item_price', 'famma_child_cart_unit_price', 10, 2 );
	add_filter( 'woocommerce_cart_item_subtotal', 'famma_child_cart_line_subtotal', 10, 2 );
}
add_action( 'woocommerce_before_cart_contents', 'famma_child_cart_line_filters_on' );

/**
 * Désactive les filtres de ligne à la fin du tableau.
 *
 * @return void
 */
function famma_child_cart_line_filters_off(): void {
	remove_filter( 'woocommerce_cart_item_remove_link', 'famma_child_cart_remove_link', 10 );
	remove_filter( 'woocommerce_cart_item_price', 'famma_child_cart_unit_price', 10 );
	remove_filter( 'woocommerce_cart_item_subtotal', 'famma_child_cart_line_subtotal', 10 );
}
add_action( 'woocommerce_cart_contents', 'famma_child_cart_line_filters_off' );

/**
 * Lien de suppression en toutes lettres, avec l'icône de la maquette.
 *
 * Seul le libellé « × » est remplacé : l'URL, le nonce et les attributs que
 * lit le script de WooCommerce restent les siens.
 *
 * @param string $link Lien rendu par WooCommerce.
 * @return string
 */
function famma_child_cart_remove_link( $link ): string {
	$label = wp_kses( famma_child_icon( 'trash' ), famma_child_svg_allowed_html() )
		. '<span>' . esc_html__( 'Supprimer', 'famma-child' ) . '</span>';

	return str_replace( '&times;</a>', $label . '</a>', (string) $link );
}

/**
 * Prix unitaire suivi de « / unité ».
 *
 * @param string $price Prix unitaire formaté.
 * @return string
 */
function famma_child_cart_unit_price( $price ): string {
	return sprintf(
		'<span class="famma-cart__unit">%1$s <span class="famma-cart__unit-suffix">%2$s</span></span>',
		(string) $price,
		esc_html__( '/ unité', 'famma-child' )
	);
}

/**
 * Total de ligne, avec le prix barré d'un article en promotion.
 *
 * Le prix barré est le prix régulier du produit multiplié par la quantité :
 * une donnée du catalogue, jamais un « prix de référence » inventé.
 *
 * @param string               $subtotal  Total de ligne formaté.
 * @param array<string, mixed> $cart_item Ligne du panier.
 * @return string
 */
function famma_child_cart_line_subtotal( $subtotal, $cart_item ): string {
	$product = $cart_item['data'] ?? null;

	if ( ! $product instanceof \WC_Product || ! $product->is_on_sale() ) {
		return (string) $subtotal;
	}

	$regular = (float) $product->get_regular_price();

	if ( $regular <= 0 ) {
		return (string) $subtotal;
	}

	$regular_total = wc_get_price_to_display( $product, array( 'price' => $regular ) ) * (int) $cart_item['quantity'];

	return (string) $subtotal . ' <del class="famma-cart__old" aria-hidden="true">' . wp_kses_post( wc_price( $regular_total ) ) . '</del>';
}

/**
 * Référence et disponibilité sous le nom du produit.
 *
 * @param array<string, mixed> $cart_item Ligne du panier.
 * @return void
 */
function famma_child_cart_item_meta( $cart_item ): void {
	if ( ! is_cart() ) {
		return;
	}

	$product = $cart_item['data'] ?? null;

	if ( ! $product instanceof \WC_Product ) {
		return;
	}

	$sku = $product->get_sku();

	if ( '' !== $sku ) {
		/* translators: %s: référence (SKU) du produit. */
		printf( '<span class="famma-cart__ref">%s</span>', esc_html( sprintf( __( 'Réf. %s', 'famma-child' ), $sku ) ) );
	}

	if ( $product->is_in_stock() ) {
		printf( '<span class="famma-cart__stock">%s</span>', esc_html__( 'En stock', 'famma-child' ) );
	}
}
add_action( 'woocommerce_after_cart_item_name', 'famma_child_cart_item_meta' );

/**
 * Encadre le champ de quantité du panier par les boutons − / +.
 *
 * Même composant que la fiche produit (`product-quantity.js`) : le champ de
 * WooCommerce reste la source de vérité, et sans script il se saisit au
 * clavier. Un produit vendu à l'unité garde le rendu de WooCommerce.
 *
 * @param string               $html          Champ de quantité.
 * @param string               $cart_item_key Clé de ligne.
 * @param array<string, mixed> $cart_item     Ligne du panier.
 * @return string
 */
function famma_child_cart_quantity( $html, $cart_item_key, $cart_item ): string {
	$product = $cart_item['data'] ?? null;

	if ( ! $product instanceof \WC_Product || $product->is_sold_individually() ) {
		return (string) $html;
	}

	return sprintf(
		'<div class="famma-qty" data-famma-qty><button type="button" class="famma-qty__btn" data-famma-qty-step="down" aria-label="%1$s">−</button>%2$s<button type="button" class="famma-qty__btn" data-famma-qty-step="up" aria-label="%3$s">+</button></div>',
		esc_attr__( 'Diminuer la quantité', 'famma-child' ),
		(string) $html,
		esc_attr__( 'Augmenter la quantité', 'famma-child' )
	);
}
add_filter( 'woocommerce_cart_item_quantity', 'famma_child_cart_quantity', 10, 3 );

/**
 * Titre visible du code promo, dans son encadré.
 *
 * @return void
 */
function famma_child_cart_coupon_title(): void {
	printf( '<span class="famma-cart__coupon-title">%s</span>', esc_html__( 'Code promo', 'famma-child' ) );
}
add_action( 'woocommerce_cart_coupon', 'famma_child_cart_coupon_title' );

/**
 * Lien « Continuer mes achats » dans la rangée d'actions.
 *
 * @return void
 */
function famma_child_cart_continue_link(): void {
	printf(
		'<a class="famma-cart__continue" href="%1$s">%2$s<span>%3$s</span></a>',
		esc_url( wc_get_page_permalink( 'shop' ) ),
		wp_kses( famma_child_icon( 'back' ), famma_child_svg_allowed_html() ),
		esc_html__( 'Continuer mes achats', 'famma-child' )
	);
}
add_action( 'woocommerce_cart_actions', 'famma_child_cart_continue_link' );

/**
 * Titre « Récapitulatif » du bloc des totaux.
 *
 * Le `<h2>` de WooCommerce (« Total panier ») est retiré par `cart.css`.
 *
 * @return void
 */
function famma_child_cart_totals_title(): void {
	printf( '<h2 class="famma-cart__summary-title">%s</h2>', esc_html__( 'Récapitulatif', 'famma-child' ) );
}
add_action( 'woocommerce_before_cart_totals', 'famma_child_cart_totals_title' );

/**
 * Pas de calculateur d'adresse dans le récapitulatif.
 *
 * La livraison est la même partout en Tunisie ; l'adresse se saisit à l'étape
 * suivante. « Modifier l'adresse » invitait à remplir un formulaire inutile.
 *
 * @param bool $show Afficher le calculateur.
 * @return bool
 */
function famma_child_cart_hide_calculator( $show ): bool {
	return is_cart() ? false : (bool) $show;
}
add_filter( 'woocommerce_shipping_show_shipping_calculator', 'famma_child_cart_hide_calculator' );

/**
 * Remplace le bouton « Valider la commande » par celui de la maquette.
 *
 * Mêmes classes que le bouton de WooCommerce (`checkout-button`, `wc-forward`)
 * pour les scripts qui les ciblent.
 *
 * @return void
 */
function famma_child_cart_swap_checkout_button(): void {
	remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );
	add_action( 'woocommerce_proceed_to_checkout', 'famma_child_cart_checkout_button', 20 );
	add_action( 'woocommerce_proceed_to_checkout', 'famma_child_tunnel_reassurance', 30 );
}
add_action( 'wp', 'famma_child_cart_swap_checkout_button' );

/**
 * Bouton vers la commande.
 *
 * @return void
 */
function famma_child_cart_checkout_button(): void {
	printf(
		'<a href="%1$s" class="checkout-button button alt wc-forward famma-cart__checkout"><span>%2$s</span>%3$s</a>',
		esc_url( wc_get_checkout_url() ),
		esc_html__( 'Passer la commande', 'famma-child' ),
		wp_kses( famma_child_icon( 'forward' ), famma_child_svg_allowed_html() )
	);
}

/**
 * Encart WhatsApp sous le récapitulatif.
 *
 * Priorité 20 : après les totaux (10), donc hors du bloc que WooCommerce
 * remplace à chaque mise à jour — l'encart ne clignote pas.
 *
 * @return void
 */
function famma_child_cart_help(): void {
	famma_child_tunnel_help( __( 'Une question sur votre panier ?', 'famma-child' ) );
}
add_action( 'woocommerce_cart_collaterals', 'famma_child_cart_help', 20 );

/**
 * Titre des ventes croisées : celui de la maquette.
 *
 * @return string
 */
function famma_child_cross_sells_heading(): string {
	return __( 'Complétez votre commande', 'famma-child' );
}
add_filter( 'woocommerce_product_cross_sells_products_heading', 'famma_child_cross_sells_heading' );

/**
 * Quatre suggestions, sur quatre colonnes, comme la maquette.
 *
 * @return int
 */
function famma_child_cross_sells_count(): int {
	return 4;
}
add_filter( 'woocommerce_cross_sells_total', 'famma_child_cross_sells_count' );
add_filter( 'woocommerce_cross_sells_columns', 'famma_child_cross_sells_count' );

/**
 * Carte vide du panier.
 *
 * Remplace le message de WooCommerce mais garde sa classe
 * `wc-empty-cart-message` : c'est elle que le script du panier recherche dans
 * la réponse quand le dernier article est retiré. Sans elle, la page se vidait
 * entièrement au lieu d'afficher ce message.
 *
 * @return void
 */
function famma_child_cart_empty(): void {
	?>
	<div class="wc-empty-cart-message famma-cart-empty" role="status">
		<?php echo wp_kses( famma_child_icon( 'cart', 'famma-cart-empty__icon' ), famma_child_svg_allowed_html() ); ?>
		<p class="famma-cart-empty__title"><?php esc_html_e( 'Votre panier est vide', 'famma-child' ); ?></p>
		<p class="famma-cart-empty__sub"><?php esc_html_e( 'Parcourez la boutique et ajoutez vos premiers produits.', 'famma-child' ); ?></p>
	</div>
	<?php
}

/**
 * Branche la carte vide à la place du message de WooCommerce.
 *
 * @return void
 */
function famma_child_cart_swap_empty_message(): void {
	remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
	add_action( 'woocommerce_cart_is_empty', 'famma_child_cart_empty', 10 );
}
add_action( 'wp', 'famma_child_cart_swap_empty_message' );

/**
 * Libellé du bouton de retour à la boutique, panier vide.
 *
 * @return string
 */
function famma_child_return_to_shop_text(): string {
	return __( 'Aller à la boutique', 'famma-child' );
}
add_filter( 'woocommerce_return_to_shop_text', 'famma_child_return_to_shop_text' );

/**
 * Charge la feuille et le script du panier.
 *
 * `product-quantity.js` est partagé avec la fiche : il fait avancer le champ.
 * `cart.js` ne fait que demander la mise à jour à WooCommerce ensuite.
 *
 * @return void
 */
function famma_child_enqueue_cart(): void {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	if ( file_exists( $dir . '/assets/css/cart.css' ) ) {
		wp_enqueue_style(
			'famma-cart',
			$uri . '/assets/css/cart.css',
			array( 'famma-tunnel' ),
			(string) filemtime( $dir . '/assets/css/cart.css' )
		);
	}

	foreach ( array( 'product-quantity', 'cart' ) as $script ) {
		$file = $dir . '/assets/js/' . $script . '.js';

		if ( file_exists( $file ) ) {
			wp_enqueue_script(
				'famma-' . $script,
				$uri . '/assets/js/' . $script . '.js',
				'cart' === $script ? array( 'jquery' ) : array(),
				(string) filemtime( $file ),
				true
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_cart', 23 );
