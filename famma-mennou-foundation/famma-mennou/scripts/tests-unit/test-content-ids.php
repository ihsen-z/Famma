<?php
/**
 * T1.7 / I5 (lot 5, S3-b) — the same product through every product-identifier
 * generator: browser (Tracking_Events: ViewContent, AddToCart, InitiateCheckout,
 * AJAX loop button), express (Express_Preparation) and CAPI (Capi_Product_Data).
 *
 * Rule under test (Meta_Ids): own SKU of the variation (edit context, never the
 * parent's), else the variation ID, else the product SKU, else the product ID;
 * item_group_id = parent ID; the famma_meta_content_id filter applies everywhere.
 * Before lot 5 this runner was a diagnostic (CHECKS=5 ECARTS=4); it now fails on
 * any gap.
 *
 * Run with: php scripts/tests-unit/test-content-ids.php [--json]
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php for the doubles.
require __DIR__ . '/bootstrap-browser.php';

lb_reset();

// Synthetic catalogue, every product marked TEST.
$simple_sku    = new WC_Product( 101, 'TEST-SKU-A', 'TEST Produit simple avec SKU', 'test-simple-sku', 49.0 );
$simple_bare   = new WC_Product( 102, '', 'TEST Produit simple sans SKU', 'test-simple-sans-sku', 39.0 );
$parent_sku    = new WC_Product_Variable( 200, 'TEST-VAR', 'TEST Produit à tailles', 'test-tailles', 49.0 );
$variation_own = new WC_Product_Variation( 201, $parent_sku, 'TEST-VAR-M', 'TEST Produit à tailles - M', 49.0 );
$parent_inh    = new WC_Product_Variable( 300, 'TEST-VAR2', 'TEST Produit à tailles 2', 'test-tailles-2', 49.0 );
$variation_inh = new WC_Product_Variation( 301, $parent_inh, '', 'TEST Produit à tailles 2 - M', 49.0 );
$parent_bare   = new WC_Product_Variable( 400, '', 'TEST Produit à tailles sans SKU', 'test-tailles-3', 49.0 );
$variation_bar = new WC_Product_Variation( 401, $parent_bare, '', 'TEST Produit à tailles sans SKU - M', 49.0 );

// Label => array( product shown, item bought, expected content_id, expected item_group_id ).
$cases = array(
	'simple avec SKU'                       => array( $simple_sku, $simple_sku, 'TEST-SKU-A', '' ),
	'simple sans SKU'                       => array( $simple_bare, $simple_bare, '102', '' ),
	'variation M avec son propre SKU'       => array( $parent_sku, $variation_own, 'TEST-VAR-M', '200' ),
	'variation M sans SKU, parent avec SKU' => array( $parent_inh, $variation_inh, '301', '300' ),
	'variation M sans SKU, parent sans SKU' => array( $parent_bare, $variation_bar, '401', '400' ),
);

// Parameters of an fbq("track", ...) call built by Tracking_Events::fbq_call().
$fbq_params = static function ( string $script ): array {
	if ( ! preg_match( '/fbq\("track","[A-Za-z]+",(\{.*?\}),\{eventID:/s', $script, $match ) ) {
		return array();
	}
	return (array) json_decode( $match[1], true );
};

/**
 * Identifiers of one bought item through every generator.
 *
 * @param WC_Product $shown  Product page displayed.
 * @param WC_Product $bought Item put in the cart and ordered.
 * @param int        $order  Synthetic order number.
 * @return array<string, mixed>
 */
$generators = static function ( WC_Product $shown, WC_Product $bought, int $order ) use ( $fbq_params ): array {
	$tracking     = Famma_Unit_Harness::bare( '\Famma\Core\Tracking_Events' );
	$parent_id    = $bought->get_parent_id() > 0 ? $bought->get_parent_id() : $bought->get_id();
	$variation_id = $bought->get_parent_id() > 0 ? $bought->get_id() : 0;
	$cart_line    = array(
		'product_id'   => $parent_id,
		'variation_id' => $variation_id,
		'quantity'     => 1,
		'data'         => $bought,
	);

	$GLOBALS['wc_test_is_product']      = true;
	$GLOBALS['wc_test_current_product'] = $shown;
	$view_content                       = $fbq_params( (string) Famma_Unit_Harness::call( $tracking, 'view_content' ) );
	$GLOBALS['wc_test_is_product']      = false;

	$GLOBALS['wc_test_store'] = new WC_Test_Store( new WC_Test_Cart( array( 'line' => $cart_line ) ), new WC_Test_Session() );
	$tracking->remember_add_to_cart( 'line', $parent_id, 1, $variation_id, array(), array() );
	$add_to_cart = $fbq_params( (string) Famma_Unit_Harness::call( $tracking, 'add_to_cart' ) );

	$GLOBALS['wc_test_is_checkout'] = true;
	$initiate                       = $fbq_params( (string) Famma_Unit_Harness::call( $tracking, 'initiate_checkout' ) );
	$GLOBALS['wc_test_is_checkout'] = false;

	$express = (array) Famma_Unit_Harness::call( '\Famma\Core\Express_Preparation', 'cart_tracking' );
	$loop    = $tracking->loop_button_data( array( 'attributes' => array() ), $bought );

	$test_order          = new WC_Order( $order );
	$test_order->items[] = new WC_Order_Item_Product( $bought, 1 );
	$capi                = \Famma\Core\Capi_Product_Data::for_order( $test_order );

	return array(
		'view_content' => $view_content,
		'ids'          => array(
			'navigateur AddToCart (fiche, POST)'     => implode( ',', $add_to_cart['content_ids'] ?? array() ),
			'navigateur AddToCart (liste, AJAX)'     => (string) ( $loop['attributes']['data-famma-content-id'] ?? '' ),
			'navigateur InitiateCheckout (checkout)' => implode( ',', $initiate['content_ids'] ?? array() ),
			'express InitiateCheckout'               => implode( ',', $express['content_ids'] ?? array() ),
			'CAPI OrderPlaced / Purchase'            => implode( ',', $capi['content_ids'] ),
			'CAPI contents[].id'                     => implode( ',', array_column( $capi['contents'], 'id' ) ),
		),
	);
};

$rows = array();
foreach ( $cases as $label => $case ) {
	list( $shown, $bought, $expected, $group ) = $case;

	$out = $generators( $shown, $bought, 1700 + count( $rows ) );
	foreach ( $out['ids'] as $generator => $content_id ) {
		l5_check( $expected === $content_id, "I5 {$label} : {$generator} = {$expected} (obtenu {$content_id})" );
	}
	l5_check( \Famma\Core\Meta_Ids::item_group_id( $bought ) === $group, "I5 {$label} : item_group_id = « {$group} »" );

	$view = $out['view_content'];
	if ( '' === $group ) {
		l5_check( 'product' === ( $view['content_type'] ?? '' ) && array( $expected ) === ( $view['content_ids'] ?? array() ), "I5 {$label} : ViewContent product [{$expected}]" );
	} else {
		l5_check( 'product_group' === ( $view['content_type'] ?? '' ) && array( $group ) === ( $view['content_ids'] ?? array() ), "I5 {$label} : ViewContent d'un produit variable = product_group [{$group}]" );
	}
	$rows[] = array(
		'cas'          => $label,
		'attendu'      => $expected,
		'groupe'       => $group,
		'ids'          => $out['ids'],
		'view_content' => $view,
	);
}

// The famma_meta_content_id filter applies to every generator, server included.
add_filter(
	'famma_meta_content_id',
	static function ( string $id ): string {
		return 'cat-' . $id;
	}
);
$filtered = $generators( $simple_bare, $variation_inh, 1790 );
foreach ( $filtered['ids'] as $generator => $content_id ) {
	l5_check( 'cat-301' === $content_id, "Filtre famma_meta_content_id appliqué : {$generator} = cat-301" );
}
$GLOBALS['wp_test_hooks']['famma_meta_content_id'] = array();
l5_check( '301' === \Famma\Core\Meta_Ids::content_id( $variation_inh ), 'Filtre retiré : identifiant d’origine' );

// A filter returning nothing usable never empties the identifier.
add_filter(
	'famma_meta_content_id',
	static function () {
		return '';
	}
);
l5_check( 'TEST-SKU-A' === \Famma\Core\Meta_Ids::content_id( $simple_sku ), 'Filtre renvoyant une valeur vide : identifiant d’origine gardé' );
$GLOBALS['wp_test_hooks']['famma_meta_content_id'] = array();

if ( in_array( '--json', $argv, true ) ) {
	echo json_encode(
		array(
			'test'   => 'T1.7 / I5',
			'checks' => $GLOBALS['l5_checks'],
			'rows'   => $rows,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	) . PHP_EOL;
	exit( 0 );
}
echo 'CHECKS=' . $GLOBALS['l5_checks'] . ' ECARTS=0' . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
