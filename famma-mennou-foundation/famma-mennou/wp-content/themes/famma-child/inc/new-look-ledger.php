<?php
/**
 * Nouveau look — brique `ledger` : bande « Livraison 0 DT · Avant réception
 * 0 DT » sous le prix de la fiche produit (maquette fiche du 04/11).
 *
 * Quatre lignes : prix du produit, livraison, à payer avant de recevoir, à
 * payer au livreur. Aucun montant n'est saisi à la main :
 * - la livraison vaut 0 seulement si toutes les méthodes actives de la zone
 *   de livraison « Tunisie » sont gratuites sans condition (livraison gratuite
 *   sans minimum, ou forfait à 0) ; sinon la ligne n'est pas affichée ;
 * - « avant de recevoir » vaut 0 seulement si le paiement à la livraison est
 *   activé dans WooCommerce ; sinon la ligne n'est pas affichée ;
 * - le prix et le total viennent du produit (fourchette pour un produit à
 *   variantes de prix différents).
 *
 * Posée à la priorité 8 du résumé, enregistrée au chargement du thème : elle
 * passe après le prix (7) et avant la réassurance (8, enregistrée plus tard
 * sur `wp` par inc/product.php). Le formulaire express (26) et la carte
 * WhatsApp (36) ne sont pas touchés.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La livraison est-elle gratuite pour le client, partout en Tunisie ?
 *
 * Lu dans la configuration WooCommerce, gardé un jour dans un transient
 * lié à la version du cache de livraison (toute modification des zones
 * l'invalide).
 *
 * @return bool
 */
function famma_child_delivery_is_free(): bool {
	if ( ! class_exists( 'WC_Shipping_Zones' ) || ! class_exists( 'WC_Cache_Helper' ) ) {
		return false;
	}

	$key    = 'famma_ledger_free_' . WC_Cache_Helper::get_transient_version( 'shipping' );
	$cached = get_transient( $key );

	if ( false !== $cached ) {
		return 'yes' === $cached;
	}

	$zone = WC_Shipping_Zones::get_zone_matching_package(
		array(
			'destination' => array(
				'country'  => 'TN',
				'state'    => '',
				'postcode' => '',
			),
		)
	);

	$methods = $zone->get_shipping_methods( true );
	$free    = array() !== $methods;

	foreach ( $methods as $method ) {
		if ( 'free_shipping' === $method->id && '' === (string) $method->get_option( 'requires', '' ) ) {
			continue;
		}

		if ( 'flat_rate' === $method->id && is_numeric( $method->get_option( 'cost', '' ) ) && 0.0 === (float) $method->get_option( 'cost', '' ) ) {
			continue;
		}

		$free = false;
		break;
	}

	set_transient( $key, $free ? 'yes' : 'no', DAY_IN_SECONDS );

	return $free;
}

/**
 * Le paiement à la livraison est-il proposé ?
 *
 * @return bool
 */
function famma_child_cod_is_enabled(): bool {
	$settings = get_option( 'woocommerce_cod_settings', array() );

	return is_array( $settings ) && isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];
}

/**
 * Prix affiché du produit : montant, ou fourchette pour des variantes.
 *
 * @param WC_Product $product Produit.
 * @return string HTML de wc_price().
 */
function famma_child_ledger_price( WC_Product $product ): string {
	if ( $product instanceof WC_Product_Variable ) {
		$min = (float) $product->get_variation_price( 'min', true );
		$max = (float) $product->get_variation_price( 'max', true );

		return $min < $max ? wc_format_price_range( $min, $max ) : wc_price( $min );
	}

	return wc_price( (float) wc_get_price_to_display( $product ) );
}

/**
 * Lignes de la bande, dans l'ordre, sans les lignes vides ou fausses.
 *
 * @param WC_Product $product Produit.
 * @return array<int, array{label: string, amount: string, zero: bool}>
 */
function famma_child_ledger_rows( WC_Product $product ): array {
	$price = famma_child_ledger_price( $product );
	$zero  = wc_price( 0, array( 'decimals' => 0 ) );
	$rows  = array(
		array( 'ledger_price', $price, false, true ),
		array( 'ledger_delivery', $zero, true, famma_child_delivery_is_free() ),
		array( 'ledger_before', $zero, true, famma_child_cod_is_enabled() ),
		array( 'ledger_total', $price, false, famma_child_delivery_is_free() ),
	);
	$out   = array();

	foreach ( $rows as $row ) {
		list( $key, $amount, $is_zero, $show ) = $row;

		$label = $show ? \Famma\Core\Product_Content::text( $key ) : '';

		if ( '' === $label ) {
			continue;
		}

		$out[] = array(
			'label'  => $label,
			'amount' => $amount,
			'zero'   => $is_zero,
		);
	}

	return $out;
}

/**
 * Affiche la bande sous le prix.
 *
 * @return void
 */
function famma_child_new_look_ledger(): void {
	if ( ! in_array( 'ledger', famma_child_new_look_parts(), true ) || ! class_exists( '\Famma\Core\Product_Content' ) ) {
		return;
	}

	$product = wc_get_product();

	if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return;
	}

	$rows = famma_child_ledger_rows( $product );

	// Sans aucun zéro vrai, la bande ne dit rien de plus que le prix.
	if ( array() === array_filter( array_column( $rows, 'zero' ) ) ) {
		return;
	}
	?>
	<dl class="famma-ledger">
		<?php foreach ( $rows as $row ) : ?>
			<div class="famma-ledger__row<?php echo $row['zero'] ? ' is-zero' : ''; ?>">
				<dt><?php echo esc_html( $row['label'] ); ?></dt>
				<dd>
					<?php if ( $row['zero'] ) : ?>
						<svg class="famma-ledger__check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 12.5l5 5L20 6.5"/></svg>
					<?php endif; ?>
					<?php echo wp_kses_post( $row['amount'] ); ?>
				</dd>
			</div>
		<?php endforeach; ?>
	</dl>
	<?php
}
add_action( 'woocommerce_single_product_summary', 'famma_child_new_look_ledger', 8 );
