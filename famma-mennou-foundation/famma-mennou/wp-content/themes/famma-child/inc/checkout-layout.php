<?php
/**
 * Commande — présentation de la maquette « FAMMA Commande ».
 *
 * La maquette range le formulaire en cartes numérotées (coordonnées, adresse,
 * livraison) à gauche, et un récapitulatif collant à droite (articles avec
 * vignette, totaux, paiement, conditions, bouton, réassurance, WhatsApp).
 *
 * Les champs restent ceux de famma-core (nom complet, téléphone tunisien,
 * gouvernorat…) et la validation, l'AJAX et le paiement restent ceux de
 * WooCommerce. Seule la façon de ranger les champs change : le rendu de
 * `woocommerce_checkout_billing` est remplacé par le nôtre, qui appelle les
 * mêmes actions et le même `woocommerce_form_field()` que le gabarit
 * `checkout/form-billing.php` — aucun gabarit n'est copié.
 *
 * Écarts assumés avec la maquette : un seul mode de livraison, offert (§4),
 * un seul mode de paiement, à la livraison (§2), aucun frais de paiement à la
 * livraison, pas d'envoi de SMS promis, et un champ « Nom complet » plutôt que
 * prénom + nom (décision du propriétaire du 23/09).
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Champs de facturation rangés dans la carte « Vos coordonnées ».
 *
 * Tous les autres vont dans « Adresse de livraison ».
 *
 * @return array<int, string>
 */
function famma_child_checkout_contact_keys(): array {
	return array( 'billing_first_name', 'billing_last_name', 'billing_phone', 'billing_email' );
}

/**
 * Remplace le rendu des coordonnées et de l'adresse par les cartes de la maquette.
 *
 * Accroché sur `wp` : `WC()->checkout()` existe et la page est connue.
 *
 * @return void
 */
function famma_child_checkout_swap_forms(): void {
	if ( 2 !== famma_child_tunnel_step() ) {
		return;
	}

	$checkout = WC()->checkout();

	remove_action( 'woocommerce_checkout_billing', array( $checkout, 'checkout_form_billing' ) );
	remove_action( 'woocommerce_checkout_shipping', array( $checkout, 'checkout_form_shipping' ) );
	add_action( 'woocommerce_checkout_billing', 'famma_child_checkout_cards' );
}
add_action( 'wp', 'famma_child_checkout_swap_forms' );

/**
 * Titre de carte numéroté.
 *
 * @param int    $number Numéro de l'étape.
 * @param string $title  Titre.
 * @return void
 */
function famma_child_checkout_card_title( int $number, string $title ): void {
	printf(
		'<h2 class="famma-co-card__title"><span class="famma-co-card__num" aria-hidden="true">%1$d</span>%2$s</h2>',
		(int) $number,
		esc_html( $title )
	);
}

/**
 * Rend les cartes « Vos coordonnées », « Adresse de livraison » et « Mode de livraison ».
 *
 * Chaque groupe de champs garde la classe `woocommerce-billing-fields__field-wrapper` :
 * le script d'adresse de WooCommerce retrie les champs **à l'intérieur** de
 * chaque enveloppe portant cette classe. Deux enveloppes distinctes gardent
 * donc chacune leurs champs, dans l'ordre de leurs priorités.
 *
 * @param \WC_Checkout $checkout Instance de commande.
 * @return void
 */
function famma_child_checkout_cards( $checkout ): void {
	if ( ! $checkout instanceof \WC_Checkout ) {
		$checkout = WC()->checkout();
	}

	$fields  = $checkout->get_checkout_fields( 'billing' );
	$contact = array_intersect_key( $fields, array_flip( famma_child_checkout_contact_keys() ) );
	$address = array_diff_key( $fields, $contact );
	?>
	<div class="woocommerce-billing-fields famma-co-card">
		<?php famma_child_checkout_card_title( 1, __( 'Vos coordonnées', 'famma-child' ) ); ?>

		<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- action native de WooCommerce, rejouée à l'identique. ?>

		<div class="woocommerce-billing-fields__field-wrapper">
			<?php
			foreach ( $contact as $key => $field ) {
				woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
			}
			?>
		</div>

		<p class="famma-co-card__note"><?php esc_html_e( 'Nous vous appelons à ce numéro pour confirmer la commande.', 'famma-child' ); ?></p>

		<?php famma_child_checkout_account_fields( $checkout ); ?>
	</div>

	<div class="famma-co-card">
		<?php famma_child_checkout_card_title( 2, __( 'Adresse de livraison', 'famma-child' ) ); ?>

		<div class="woocommerce-billing-fields__field-wrapper">
			<?php
			foreach ( $address as $key => $field ) {
				woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
			}
			?>
		</div>

		<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- action native de WooCommerce, rejouée à l'identique. ?>

		<?php $checkout->checkout_form_shipping(); ?>
	</div>

	<?php
	famma_child_checkout_delivery_card();
}

/**
 * Création de compte facultative, telle que la rend `checkout/form-billing.php`.
 *
 * Reproduite à l'identique (mêmes identifiants, mêmes actions) : le script de
 * WooCommerce s'appuie sur `#createaccount` et `.create-account`. Rien n'est
 * rendu tant que la création de compte au checkout est désactivée dans
 * WooCommerce → Réglages → Comptes (état voulu depuis le 27/09, UX-17).
 *
 * @param \WC_Checkout $checkout Instance de commande.
 * @return void
 */
function famma_child_checkout_account_fields( \WC_Checkout $checkout ): void {
	if ( is_user_logged_in() || ! $checkout->is_registration_enabled() ) {
		return;
	}
	?>
	<div class="woocommerce-account-fields">
		<?php if ( ! $checkout->is_registration_required() ) : ?>
			<?php
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- filtre natif de WooCommerce.
			$default_checked = true === apply_filters( 'woocommerce_create_account_default_checked', false );
			?>
			<p class="form-row form-row-wide create-account">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || $default_checked ), true ); ?> type="checkbox" name="createaccount" value="1" /> <span><?php esc_html_e( 'Create an account?', 'woocommerce' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- libellé de WooCommerce, traduit par son propre domaine. ?></span>
				</label>
			</p>
		<?php endif; ?>

		<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- action native de WooCommerce, rejouée à l'identique. ?>

		<?php if ( $checkout->get_checkout_fields( 'account' ) ) : ?>
			<div class="create-account">
				<?php foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $field ) : ?>
					<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
				<?php endforeach; ?>
				<div class="clear"></div>
			</div>
		<?php endif; ?>

		<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- action native de WooCommerce, rejouée à l'identique. ?>
	</div>
	<?php
}

/**
 * Carte « Mode de livraison » : l'unique mode, offert.
 *
 * Informative : le choix réel reste la ligne « Expédition » du récapitulatif,
 * que WooCommerce recalcule. Le délai est celui saisi dans FAMMA → Pages ; vide,
 * aucun délai n'est promis (§60).
 *
 * @return void
 */
function famma_child_checkout_delivery_card(): void {
	$delay    = famma_child_delivery_delay();
	$shipping = WC()->cart ? (float) WC()->cart->get_shipping_total() : 0.0;
	$price    = $shipping > 0 ? wc_price( $shipping ) : esc_html__( 'Offerte', 'famma-child' );
	?>
	<div class="famma-co-card">
		<?php famma_child_checkout_card_title( 3, __( 'Mode de livraison', 'famma-child' ) ); ?>

		<div class="famma-co-option is-selected">
			<span class="famma-co-option__dot" aria-hidden="true"></span>
			<span class="famma-co-option__body">
				<span class="famma-co-option__label"><?php esc_html_e( 'Livraison à domicile, partout en Tunisie', 'famma-child' ); ?></span>
				<?php if ( '' !== $delay ) : ?>
					<span class="famma-co-option__sub"><?php echo esc_html( $delay ); ?></span>
				<?php endif; ?>
			</span>
			<span class="famma-co-option__price<?php echo $shipping > 0 ? '' : ' is-free'; ?>"><?php echo wp_kses_post( $price ); ?></span>
		</div>
	</div>
	<?php
}

/**
 * Ouvre la colonne du récapitulatif, avec son titre et le lien « Modifier ».
 *
 * Le `<h3>` de WooCommerce (« Votre commande ») est retiré par `checkout.css`.
 *
 * @return void
 */
function famma_child_checkout_aside_open(): void {
	?>
	<div class="famma-co-aside">
		<div class="famma-co-aside__card">
			<div class="famma-co-aside__head">
				<h2 class="famma-co-aside__title"><?php esc_html_e( 'Votre commande', 'famma-child' ); ?></h2>
				<a class="famma-co-aside__edit" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'Modifier', 'famma-child' ); ?></a>
			</div>
	<?php
}
add_action( 'woocommerce_checkout_before_order_review_heading', 'famma_child_checkout_aside_open' );

/**
 * Ferme la carte du récapitulatif, ajoute l'encart WhatsApp, ferme la colonne.
 *
 * @return void
 */
function famma_child_checkout_aside_close(): void {
	echo '</div>';
	famma_child_tunnel_help( __( 'Une question avant de commander ?', 'famma-child' ) );
	echo '</div>';
}
add_action( 'woocommerce_checkout_after_order_review', 'famma_child_checkout_aside_close' );

/**
 * Active la vignette et la quantité en toutes lettres, le temps des lignes du récapitulatif.
 *
 * Portée limitée aux lignes : le même filtre de nom sert au mini-panier.
 * Rejoué à chaque rafraîchissement AJAX du récapitulatif, qui repasse par ces
 * mêmes actions.
 *
 * @return void
 */
function famma_child_review_filters_on(): void {
	add_filter( 'woocommerce_cart_item_name', 'famma_child_review_item_name', 10, 2 );
	add_filter( 'woocommerce_checkout_cart_item_quantity', 'famma_child_review_item_quantity', 10, 2 );
}
add_action( 'woocommerce_review_order_before_cart_contents', 'famma_child_review_filters_on' );

/**
 * Désactive les filtres de ligne du récapitulatif.
 *
 * @return void
 */
function famma_child_review_filters_off(): void {
	remove_filter( 'woocommerce_cart_item_name', 'famma_child_review_item_name', 10 );
	remove_filter( 'woocommerce_checkout_cart_item_quantity', 'famma_child_review_item_quantity', 10 );
}
add_action( 'woocommerce_review_order_after_cart_contents', 'famma_child_review_filters_off' );

/**
 * Vignette devant le nom de l'article.
 *
 * @param string               $name      Nom de l'article.
 * @param array<string, mixed> $cart_item Ligne du panier.
 * @return string
 */
function famma_child_review_item_name( $name, $cart_item ): string {
	$product = $cart_item['data'] ?? null;

	if ( ! $product instanceof \WC_Product ) {
		return (string) $name;
	}

	return '<span class="famma-co-line__thumb">' . $product->get_image( 'woocommerce_gallery_thumbnail' ) . '</span>'
		. '<span class="famma-co-line__name">' . (string) $name . '</span>';
}

/**
 * « Quantité : 2 » au lieu de « × 2 ».
 *
 * @param string               $html      Quantité rendue par WooCommerce.
 * @param array<string, mixed> $cart_item Ligne du panier.
 * @return string
 */
function famma_child_review_item_quantity( $html, $cart_item ): string {
	/* translators: %d: quantité commandée. */
	$label = sprintf( __( 'Quantité : %d', 'famma-child' ), (int) ( $cart_item['quantity'] ?? 0 ) );

	return ' <span class="famma-co-line__qty product-quantity">' . esc_html( $label ) . '</span>';
}

/**
 * Libellé du bouton de validation, aligné sur le formulaire express de la fiche.
 *
 * @return string
 */
function famma_child_order_button_text(): string {
	return __( 'Confirmer ma commande', 'famma-child' );
}
add_filter( 'woocommerce_order_button_text', 'famma_child_order_button_text' );

/**
 * Coche devant le libellé du bouton de validation.
 *
 * Le bouton reste celui de WooCommerce (`#place_order`, `name`, `value`) : seul
 * un pictogramme décoratif est inséré avant la fermeture de la balise ouvrante.
 *
 * @param string $html Bouton rendu par WooCommerce.
 * @return string
 */
function famma_child_order_button_html( $html ): string {
	$icon = wp_kses( famma_child_icon( 'check' ), famma_child_svg_allowed_html() );

	return (string) preg_replace( '/(<button\b[^>]*>)/', '$1' . $icon, (string) $html, 1 );
}
add_filter( 'woocommerce_order_button_html', 'famma_child_order_button_html' );

/**
 * Réassurance sous le bouton de validation.
 *
 * Sous le bouton et non au-dessus, comme la maquette : les conditions sont
 * déjà rappelées par le mode de paiement, juste au-dessus des conditions
 * générales.
 *
 * @return void
 */
function famma_child_checkout_reassurance(): void {
	famma_child_tunnel_reassurance();
}
add_action( 'woocommerce_review_order_after_submit', 'famma_child_checkout_reassurance' );
