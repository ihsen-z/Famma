<?php
/**
 * Doubles and real sources of the browser side of the pixel (lot 5, flux B),
 * on top of bootstrap-capi.php: the real Tracking class, Meta_Ids,
 * Tracking_Events, Browser_Order_Placed, Order_Source and Express_Preparation.
 * Inline scripts are captured in
 * memory; no database, no network, no browser.
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded. The doubles below keep the names
// and signatures of the WordPress and WooCommerce code they replace.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Generic.Classes.DuplicateClassName -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.WrongStyle -- Doubles are documented by the API they replace; line comments label groups of doubles.

require_once __DIR__ . '/bootstrap-capi.php';

if ( ! defined( 'FAMMA_CORE_VERSION' ) ) {
	define( 'FAMMA_CORE_VERSION', '0.3.1-unit' );
}

// WordPress: current user, request, scripts.
function is_user_logged_in(): bool {
	return ! empty( $GLOBALS['wp_test_logged_in'] );
}
function is_admin(): bool {
	return ! empty( $GLOBALS['wp_test_is_admin'] );
}
function absint( $value ): int {
	return abs( (int) $value );
}
function get_query_var( string $name, $default_value = '' ) {
	return $GLOBALS['wp_test_query_vars'][ $name ] ?? $default_value;
}
function wp_register_script( string $handle, $src, array $deps = array(), $ver = false, $args = array() ): bool {
	$GLOBALS['wp_test_scripts'][ $handle ] = array(
		'src'      => $src,
		'args'     => is_array( $args ) ? $args : array( 'in_footer' => (bool) $args ),
		'after'    => array(),
		'enqueued' => false,
	);
	return true;
}
function wp_enqueue_script( string $handle, $src = '', array $deps = array(), $ver = false, $args = array() ): void {
	if ( ! isset( $GLOBALS['wp_test_scripts'][ $handle ] ) ) {
		wp_register_script( $handle, $src, $deps, $ver, $args );
	}
	$GLOBALS['wp_test_scripts'][ $handle ]['enqueued'] = true;
}
function wp_add_inline_script( string $handle, string $data, string $position = 'after' ): bool {
	if ( ! isset( $GLOBALS['wp_test_scripts'][ $handle ] ) ) {
		return false;
	}
	$GLOBALS['wp_test_scripts'][ $handle ]['after'][] = $data;
	return true;
}

// WooCommerce helpers.
function wc_get_price_decimals(): int {
	return 3;
}
function wc_maybe_define_constant( string $name, $value ): void {
	$GLOBALS['wp_test_constants'][ $name ] = $value;
}
class WC_Product_Variable extends WC_Product {
}

Famma_Unit_Harness::load_core_class( 'Tracking', 'Capi' );
Famma_Unit_Harness::load_include( 'tracking-events.php' );
Famma_Unit_Harness::load_include( 'browser-order-placed.php' );
Famma_Unit_Harness::load_include( 'order-source.php' );
Famma_Unit_Harness::load_include( 'express-preparation.php' );

/**
 * Inline scripts attached to a handle, joined.
 *
 * @param string $handle Script handle.
 * @return string
 */
function lb_inline( string $handle ): string {
	return implode( "\n", $GLOBALS['wp_test_scripts'][ $handle ]['after'] ?? array() );
}

/**
 * Resets the browser-side state, then the CAPI state.
 *
 * @param array $options Options to start from.
 */
function lb_reset( array $options = array() ): void {
	l5_reset( $options );
	$GLOBALS['wp_test_scripts']           = array();
	$GLOBALS['wp_test_hooks']             = array();
	$GLOBALS['wp_test_caps']              = array();
	$GLOBALS['wp_test_logged_in']         = false;
	$GLOBALS['wp_test_constants']         = array();
	$GLOBALS['wp_test_query_vars']        = array();
	$GLOBALS['wc_test_is_product']        = false;
	$GLOBALS['wc_test_is_checkout']       = false;
	$GLOBALS['wc_test_is_order_received'] = false;
	$GLOBALS['wc_test_store']             = new WC_Test_Store( new WC_Test_Cart(), new WC_Test_Session() );
	unset( $_GET['key'], $_COOKIE['famma_utm'], $_COOKIE['_fbc'] );
}

/**
 * Logs in a visitor with the given capabilities (all others refused).
 *
 * @param string[] $caps Capabilities granted.
 */
function lb_login( array $caps ): void {
	$GLOBALS['wp_test_logged_in'] = true;
	$GLOBALS['wp_test_caps']      = array(
		'manage_options'     => in_array( 'manage_options', $caps, true ),
		'manage_woocommerce' => in_array( 'manage_woocommerce', $caps, true ),
	);
}

/**
 * Renders the front-end tracking of one page: Tracking, then Tracking_Events,
 * then the famma-fbc cookie script, in the order of wp_enqueue_scripts.
 *
 * @return array<string, mixed> Captured scripts.
 */
function lb_render_page(): array {
	$GLOBALS['wp_test_scripts'] = array();
	Famma_Unit_Harness::bare( '\Famma\Core\Capi_Signals' )->enqueue_cookie_script();
	Famma_Unit_Harness::bare( '\Famma\Core\Tracking' )->enqueue_tracking();
	Famma_Unit_Harness::bare( '\Famma\Core\Tracking_Events' )->enqueue_events();
	return $GLOBALS['wp_test_scripts'];
}

/**
 * Renders a product page for the current visitor and summarises what reached the browser.
 *
 * @return array<string, mixed>
 */
function lb_page_summary(): array {
	$GLOBALS['wc_test_is_product']      = true;
	$GLOBALS['wc_test_current_product'] = new WC_Product( 16, 'QA-L5-SKU', 'QA-L5 Produit', 'qa-l5-produit', 49.0 );
	$scripts                            = lb_render_page();
	$GLOBALS['wc_test_is_product']      = false;
	$pixels                             = lb_inline( 'famma-pixels' );
	return array(
		'pixels_registered' => isset( $scripts['famma-pixels'] ),
		'init'              => substr_count( $pixels, "fbq('init'," ),
		'fbq_calls'         => substr_count( $pixels, 'fbq(' ),
		'view_content'      => str_contains( $pixels, '"ViewContent"' ),
		'tiktok'            => str_contains( $pixels, 'ttq.load(' ),
		'ga4'               => isset( $scripts['famma-ga4'] ),
		'fbc'               => isset( $scripts['famma-fbc'] ),
		'utm'               => str_contains( $pixels, 'famma_utm=' ),
	);
}

/**
 * Renders a product page and returns every inline script printed.
 *
 * @param WC_Product $product Product shown.
 * @return string
 */
function lb_product_page( WC_Product $product ): string {
	$GLOBALS['wc_test_is_product']      = true;
	$GLOBALS['wc_test_current_product'] = $product;
	$scripts                            = lb_render_page();
	$GLOBALS['wc_test_is_product']      = false;
	return implode( PHP_EOL, array_merge( ...array_column( $scripts, 'after' ) ) );
}

/**
 * Keys of the parameters of every fbq("track") call in a script, by event.
 *
 * @param string $script Inline script.
 * @return array<string, array<int, string>>
 */
function lb_keys( string $script ): array {
	preg_match_all( '/fbq\("track(?:Custom)?","([A-Za-z]+)",(\{.*?\}),\{eventID:/', $script, $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $call ) {
		$out[ $call[1] ] = array_keys( (array) json_decode( $call[2], true ) );
	}
	return $out;
}

/**
 * Cart with one line of the given product.
 *
 * @param WC_Product $product  Product.
 * @param float      $quantity Quantity.
 * @return WC_Test_Store
 */
function lb_cart( WC_Product $product, float $quantity = 1.0 ): WC_Test_Store {
	return new WC_Test_Store(
		new WC_Test_Cart(
			array(
				'l' => array(
					'product_id'   => $product->get_id(),
					'variation_id' => 0,
					'quantity'     => $quantity,
					'data'         => $product,
				),
			)
		),
		new WC_Test_Session()
	);
}

/**
 * Browser events of a product page, a pending AddToCart and the checkout.
 *
 * @param WC_Product $product Product.
 * @param object     $events  Real Tracking_Events instance.
 * @return array<string, array<int, string>>
 */
function lb_all_events( WC_Product $product, $events ): array {
	$GLOBALS['wc_test_is_product']      = true;
	$GLOBALS['wc_test_current_product'] = $product;
	$view                               = (string) Famma_Unit_Harness::call( $events, 'view_content' );
	$GLOBALS['wc_test_is_product']      = false;
	$GLOBALS['wc_test_store']           = lb_cart( $product );
	$GLOBALS['wc_test_store']->session->set(
		'famma_pending_add_to_cart',
		array(
			array(
				'id'       => $product->get_sku(),
				'quantity' => 1.0,
				'value'    => 49.0,
				'currency' => 'TND',
			),
		)
	);
	$add                            = (string) Famma_Unit_Harness::call( $events, 'add_to_cart' );
	$GLOBALS['wc_test_is_checkout'] = true;
	$ic                             = (string) Famma_Unit_Harness::call( $events, 'initiate_checkout' );
	$GLOBALS['wc_test_is_checkout'] = false;
	return lb_keys( $view . $add . $ic );
}
