<?php
/**
 * T1.7 — The same product through the three product-identifier generators:
 * browser (Tracking_Events), express (Express_Preparation) and CAPI (Capi_Product_Data).
 * Run with: php scripts/tests-unit/test-content-ids.php [--json]
 * The real classes are exercised; catalogue, cart and session are WooCommerce doubles
 * (a variation inherits its parent's SKU in view context, as in WooCommerce).
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php for the doubles.
require __DIR__ . '/bootstrap.php';

Famma_Unit_Harness::load_core_class( 'Config', 'Order_Statuses' );
Famma_Unit_Harness::load_include( 'tracking-events.php' );
Famma_Unit_Harness::load_include( 'express-preparation.php' );
Famma_Unit_Harness::load_include( 'capi-product-data.php' );

$GLOBALS['wp_test_options'] = array( 'famma_meta_pixel_id' => '000000000000001' );

// Synthetic catalogue, every product marked TEST.
$simple_sku    = new WC_Product( 101, 'TEST-SKU-A', 'TEST Produit simple avec SKU', 'test-simple-sku', 49.0 );
$simple_bare   = new WC_Product( 102, '', 'TEST Produit simple sans SKU', 'test-simple-sans-sku', 39.0 );
$parent_sku    = new WC_Product( 200, 'TEST-VAR', 'TEST Produit à tailles', 'test-tailles', 49.0 );
$variation_own = new WC_Product_Variation( 201, $parent_sku, 'TEST-VAR-M', 'TEST Produit à tailles - M', 49.0 );
$parent_inh    = new WC_Product( 300, 'TEST-VAR2', 'TEST Produit à tailles 2', 'test-tailles-2', 49.0 );
$variation_inh = new WC_Product_Variation( 301, $parent_inh, '', 'TEST Produit à tailles 2 - M', 49.0 );
$parent_bare   = new WC_Product( 400, '', 'TEST Produit à tailles sans SKU', 'test-tailles-3', 49.0 );
$variation_bar = new WC_Product_Variation( 401, $parent_bare, '', 'TEST Produit à tailles sans SKU - M', 49.0 );

$cases = array(
	'simple avec SKU'                       => array( $simple_sku, $simple_sku ),
	'simple sans SKU'                       => array( $simple_bare, $simple_bare ),
	'variation M avec son propre SKU'       => array( $parent_sku, $variation_own ),
	'variation M sans SKU, parent avec SKU' => array( $parent_inh, $variation_inh ),
	'variation M sans SKU, parent sans SKU' => array( $parent_bare, $variation_bar ),
);

// Parameters of an fbq("track", ...) call built by Tracking_Events::fbq_call().
$fbq_params = static function ( string $script ): array {
	if ( ! preg_match( '/^fbq\("track","[A-Za-z]+",(\{.*\}),\{eventID:/s', $script, $match ) ) {
		return array();
	}
	return (array) json_decode( $match[1], true );
};

$tracking = Famma_Unit_Harness::bare( '\Famma\Core\Tracking_Events' );
$rows     = array();
$ecarts   = 0;
foreach ( $cases as $label => $pair ) {
	list( $shown, $bought ) = $pair;
	$parent_id              = $bought->get_parent_id() > 0 ? $bought->get_parent_id() : $bought->get_id();
	$variation_id           = $bought->get_parent_id() > 0 ? $bought->get_id() : 0;
	$cart_line              = array(
		'product_id'   => $parent_id,
		'variation_id' => $variation_id,
		'quantity'     => 1,
		'data'         => $bought,
	);

	// Browser, product page: ViewContent of the product displayed.
	$GLOBALS['wc_test_is_product']      = true;
	$GLOBALS['wc_test_current_product'] = $shown;
	$view_content                       = $fbq_params( (string) Famma_Unit_Harness::call( $tracking, 'view_content' ) );
	$GLOBALS['wc_test_is_product']      = false;

	// Browser, product page: AddToCart of a classic POST, replayed on the next page.
	$GLOBALS['wc_test_store'] = new WC_Test_Store( new WC_Test_Cart( array( 'line' => $cart_line ) ), new WC_Test_Session() );
	$tracking->remember_add_to_cart( 'line', $parent_id, 1, $variation_id, array(), array() );
	$add_to_cart = $fbq_params( (string) Famma_Unit_Harness::call( $tracking, 'add_to_cart' ) );

	// Browser, classic checkout: InitiateCheckout of the cart.
	$GLOBALS['wc_test_is_checkout'] = true;
	$initiate                       = $fbq_params( (string) Famma_Unit_Harness::call( $tracking, 'initiate_checkout' ) );
	$GLOBALS['wc_test_is_checkout'] = false;

	// Express form: InitiateCheckout parameters returned with the quote.
	$express = (array) Famma_Unit_Harness::call( '\Famma\Core\Express_Preparation', 'cart_tracking' );

	// Server: OrderPlaced and Purchase product block.
	$test_order          = new WC_Order( 1700 + count( $rows ) );
	$test_order->items[] = new WC_Order_Item_Product( $bought, 1 );
	$capi                = \Famma\Core\Capi_Product_Data::for_order( $test_order );

	$ids      = array(
		'navigateur ViewContent (fiche)'         => implode( ',', $view_content['content_ids'] ?? array() ),
		'navigateur AddToCart (fiche, POST)'     => implode( ',', $add_to_cart['content_ids'] ?? array() ),
		'navigateur InitiateCheckout (checkout)' => implode( ',', $initiate['content_ids'] ?? array() ),
		'express InitiateCheckout'               => implode( ',', $express['content_ids'] ?? array() ),
		'CAPI OrderPlaced / Purchase'            => implode( ',', $capi['content_ids'] ),
	);
	$distinct = array_values( array_unique( $ids ) );
	$same     = 1 === count( $distinct );
	$ecarts  += $same ? 0 : 1;
	$rows[]   = array(
		'cas'           => $label,
		'produit'       => $shown->get_id() . ( $variation_id ? ' / variation ' . $variation_id : '' ),
		'sku_vue'       => $bought->get_sku(),
		'ids'           => $ids,
		'verdict'       => $same ? 'IDENTIQUE' : 'ECART',
		'valeurs'       => $distinct,
		'contents'      => array(
			'navigateur' => $initiate['contents'] ?? array(),
			'express'    => $express['contents'] ?? array(),
			'capi'       => $capi['contents'],
		),
		'famma_product' => $capi['famma_product'],
	);
}

if ( in_array( '--json', $argv, true ) ) {
	echo json_encode(
		array(
			'test'   => 'T1.7',
			'ecarts' => $ecarts,
			'rows'   => $rows,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	) . PHP_EOL;
	exit( 0 );
}
foreach ( $rows as $row ) {
	echo $row['verdict'] . ': ' . $row['cas'] . ' (produit ' . $row['produit'] . ', SKU vu « ' . $row['sku_vue'] . ' »)' . PHP_EOL;
	foreach ( $row['ids'] as $generator => $content_id ) {
		echo '    ' . str_pad( $generator, 42 ) . ' content_ids=[' . $content_id . ']' . PHP_EOL;
	}
}
echo 'CHECKS=' . count( $rows ) . ' ECARTS=' . $ecarts . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
