<?php
/**
 * Nouveau look — page commande comme le formulaire express (brique
 * `co_express`, onglet « Panier »).
 *
 * Choix du propriétaire du 18/11 : une seule carte « Vos coordonnées » au
 * lieu de trois (coordonnées, adresse, mode de livraison), avec les champs du
 * formulaire express de la fiche : téléphone avec « +216 » fixe, nom complet,
 * adresse de livraison (gouvernorat + « Ville, cité, rue… » dans un même
 * cadre, gouvernorat trouvé d'après la ville tapée), puis l'e-mail facultatif.
 * Sous le bouton, la phrase « En commandant, vous acceptez nos conditions
 * générales » remplace la case à cocher ; le bloc « Paiement à la livraison »
 * se cache quand c'est le seul moyen de paiement (il reste choisi).
 *
 * Le cadre de l'adresse et la phrase des conditions sont ceux de famma-core
 * (`Express_Order::address_markup()` et `::legal_note_html()`), la feuille est
 * celle du formulaire express : aucun doublon. Les règles des champs
 * (adresse facultative, plus de « Ville / Délégation », ordre, pas de case des
 * conditions) sont dans `Famma\Core\Checkout_Express_Look`. Validation, AJAX
 * et paiement restent ceux de WooCommerce. Case décochée : les trois cartes
 * d'avant (inc/checkout-layout.php).
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La brique est-elle servie ?
 *
 * @return bool
 */
function famma_child_co_express_on(): bool {
	return function_exists( 'famma_child_new_look' ) && famma_child_new_look( 'co_express' )
		&& class_exists( '\Famma\Core\Checkout_Express_Look' ) && class_exists( '\Famma\Core\Express_Order' );
}

/**
 * Carte unique « Vos coordonnées ».
 *
 * Mêmes actions de WooCommerce que les cartes d'avant, dans le même ordre.
 * Les champs restent dans une enveloppe `woocommerce-billing-fields__field-wrapper`
 * (le script d'adresse de WooCommerce y retrie les champs par priorité).
 *
 * @param \WC_Checkout $checkout Instance de commande.
 * @return void
 */
function famma_child_checkout_card_express( \WC_Checkout $checkout ): void {
	$fields = $checkout->get_checkout_fields( 'billing' );
	?>
	<div class="woocommerce-billing-fields famma-co-card famma-co-card--express">
		<h2 class="famma-co-card__title"><?php esc_html_e( 'Vos coordonnées', 'famma-child' ); ?></h2>

		<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- action native de WooCommerce, rejouée à l'identique. ?>

		<div class="woocommerce-billing-fields__field-wrapper">
			<?php
			foreach ( $fields as $key => $field ) {
				famma_child_checkout_express_field( $checkout, (string) $key, (array) $field );
			}
			?>
		</div>

		<?php famma_child_checkout_account_fields( $checkout ); ?>

		<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- action native de WooCommerce, rejouée à l'identique. ?>

		<?php $checkout->checkout_form_shipping(); ?>
	</div>
	<?php
}

/**
 * Un champ de la carte : téléphone et adresse au format express, le reste tel
 * que WooCommerce le rend.
 *
 * Le gouvernorat est rendu avec l'adresse, dans le même cadre : il est sauté
 * à son propre tour.
 *
 * @param \WC_Checkout         $checkout Instance de commande.
 * @param string               $key      Clé du champ.
 * @param array<string, mixed> $field    Réglages du champ.
 * @return void
 */
function famma_child_checkout_express_field( \WC_Checkout $checkout, string $key, array $field ): void {
	if ( 'billing_state' === $key ) {
		return;
	}

	if ( 'billing_address_1' === $key ) {
		\Famma\Core\Express_Order::address_markup(
			array(
				'id'       => 'famma-co',
				'class'    => 'form-row form-row-wide address-field validate-required famma-express__address famma-co-address',
				'tag'      => 'p',
				'row_id'   => 'famma-co-address',
				'state_id' => 'billing_state',
				'line_id'  => 'billing_address_1',
				'state'    => (string) $checkout->get_value( 'billing_state' ),
				'line'     => (string) $checkout->get_value( 'billing_address_1' ),
			)
		);
		return;
	}

	if ( 'billing_phone' !== $key ) {
		woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
		return;
	}

	$field['return'] = true;
	$field['class']  = array_merge( (array) ( $field['class'] ?? array() ), array( 'famma-express__phone' ) );

	$field['custom_attributes'] = array_merge(
		(array) ( $field['custom_attributes'] ?? array() ),
		array( 'enterkeyhint' => 'next' )
	);

	$field['placeholder'] = 'XX XXX XXX';

	$html = (string) woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
	$box  = '<span class="famma-express__phone-box" dir="ltr"><span class="famma-express__prefix" aria-hidden="true">+216</span>$1</span>';
	$html = (string) preg_replace( '/(<input\b[^>]*>)/', $box, $html, 1 );

	// La phrase d'appel va sous le numéro, dans la ligne du champ : WooCommerce ne retrie que les lignes `.form-row`.
	$note = '<span class="famma-co-card__note famma-co-phone-note">' . esc_html__( 'Nous vous appelons à ce numéro pour confirmer la commande.', 'famma-child' ) . '</span>';
	$html = (string) preg_replace( '/<\/p>\s*$/', $note . '</p>', $html, 1 );

	// Chaîne rendue et échappée par woocommerce_form_field() ; seuls le cadre « +216 » et la phrase (échappée) sont ajoutés.
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML de woocommerce_form_field(), déjà échappé.
}

/**
 * Phrase des conditions sous le bouton, à la place de la case à cocher.
 *
 * Priorité 5 : avant la réassurance (10).
 *
 * @return void
 */
function famma_child_co_express_legal(): void {
	if ( famma_child_co_express_on() ) {
		echo wp_kses_post( \Famma\Core\Express_Order::legal_note_html( 'famma-co-legal' ) );
	}
}
add_action( 'woocommerce_review_order_after_submit', 'famma_child_co_express_legal', 5 );

/**
 * Classes du corps : la brique, et « paiement à la livraison seul ».
 *
 * @param array<int, string> $classes Classes du corps.
 * @return array<int, string>
 */
function famma_child_co_express_body_class( array $classes ): array {
	if ( famma_child_co_express_on() && function_exists( 'is_checkout' ) && is_checkout() ) {
		$classes[] = 'famma-nl-co-express';

		if ( function_exists( 'famma_child_cart_cod_only' ) && famma_child_cart_cod_only() ) {
			$classes[] = 'famma-co-cod-only';
		}
	}

	return $classes;
}
add_filter( 'body_class', 'famma_child_co_express_body_class' );

/**
 * Feuille et script de la carte, sur la page commande seulement.
 *
 * Le script partagé du gouvernorat et la feuille du formulaire express sont
 * mis en file par famma-core (priorité 26) ; on passe après.
 *
 * @return void
 */
function famma_child_co_express_enqueue(): void {
	if ( ! famma_child_co_express_on() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = '/assets/css/new-look-checkout.css';
	$js  = '/assets/js/new-look-checkout.js';

	if ( file_exists( $dir . $css ) ) {
		$deps = array_values( array_filter( array( 'famma-checkout', 'famma-express' ), static fn( string $h ): bool => wp_style_is( $h, 'registered' ) ) );
		wp_enqueue_style( 'famma-new-look-checkout', $uri . $css, $deps, (string) filemtime( $dir . $css ) );
	}

	if ( file_exists( $dir . $js ) && wp_script_is( 'famma-governorate-places', 'enqueued' ) ) {
		wp_enqueue_script(
			'famma-new-look-checkout',
			$uri . $js,
			array( 'famma-governorate-places' ),
			(string) filemtime( $dir . $js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script( 'famma-new-look-checkout', 'fammaCoExpress', \Famma\Core\Checkout_Express_Look::strings() );
	}
}
add_action( 'wp_enqueue_scripts', 'famma_child_co_express_enqueue', 30 );
