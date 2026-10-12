<?php
/**
 * Read-only acceptance proof from disposable browser orders.
 *
 * @package FammaIntegration
 */

defined( 'ABSPATH' ) || exit;
if ( '1' !== getenv( 'FAMMA_BROWSER_FIXTURES' ) || 'local' !== wp_get_environment_type() || 'famma_integration' !== DB_NAME || 'db' !== DB_HOST || 'http://localhost:8980' !== home_url() ) {
	throw new RuntimeException( 'Refusing browser verification outside the disposable clone.' );
}

$famma_browser_orders  = wc_get_orders(
	array(
		'limit'      => 100,
		// phpcs:ignore WordPress.DB.SlowDBQuery -- bounded fixture lookup in the disposable clone, through Woo CRUD.
		'meta_key'   => '_famma_browser_fixture',
		// phpcs:ignore WordPress.DB.SlowDBQuery -- synthetic marker only.
		'meta_value' => 'browser-20261010',
	)
);
$famma_browser_results = array();
foreach ( $famma_browser_orders as $famma_browser_order ) {
	$famma_browser_lines = array();
	foreach ( $famma_browser_order->get_items() as $famma_browser_item ) {
		$famma_browser_lines[] = array(
			'name'     => $famma_browser_item->get_name(),
			'quantity' => $famma_browser_item->get_quantity(),
			'total'    => $famma_browser_item->get_total(),
		);
	}
	$famma_browser_registry  = Famma\Core\Express_Idempotency::instance()->get( (string) $famma_browser_order->get_meta( '_famma_express_request_id' ) );
	$famma_browser_results[] = array(
		'order_id' => $famma_browser_order->get_id(),
		'status'   => $famma_browser_order->get_status(),
		'total'    => $famma_browser_order->get_total(),
		'currency' => $famma_browser_order->get_currency(),
		'shipping' => $famma_browser_order->get_shipping_total(),
		'paid'     => (bool) $famma_browser_order->get_date_paid(),
		'accepted' => (bool) $famma_browser_order->get_meta( '_famma_express_accepted' ),
		'registry' => $famma_browser_registry['state'] ?? 'missing',
		'lines'    => $famma_browser_lines,
	);
}
WP_CLI::line(
	wp_json_encode(
		array(
			'order_count' => count( $famma_browser_orders ),
			'orders'      => $famma_browser_results,
		),
		JSON_PRETTY_PRINT
	)
);
