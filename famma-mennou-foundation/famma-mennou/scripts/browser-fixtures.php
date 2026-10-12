<?php
/**
 * Disposable physical products for browser acceptance tests.
 *
 * @package FammaIntegration
 */

defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_BROWSER_FIXTURES' ) || 'local' !== wp_get_environment_type() || 'famma_integration' !== DB_NAME || 'db' !== DB_HOST || 'http://localhost:8980' !== home_url() ) {
	throw new RuntimeException( 'Refusing browser fixtures outside the disposable clone.' );
}

$famma_browser_ids = (array) get_option( 'famma_browser_fixture_ids', array() );
if ( 'cleanup' === getenv( 'FAMMA_BROWSER_ACTION' ) ) {
	$famma_browser_orders = wc_get_orders(
		array(
			'limit'      => 100,
			// phpcs:ignore WordPress.DB.SlowDBQuery -- bounded CRUD query for disposable fixtures in the guarded local database.
			'meta_key'   => '_famma_browser_fixture',
			// phpcs:ignore WordPress.DB.SlowDBQuery -- isolated fixture marker, never a storefront query.
			'meta_value' => 'browser-20261010',
		)
	);
	global $wpdb;
	foreach ( $famma_browser_orders as $famma_browser_order ) {
		if ( 'browser-20261010' !== $famma_browser_order->get_meta( '_famma_browser_fixture', true ) ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- remove only this guarded clone's fixture intent; orders use Woo CRUD below.
		$wpdb->delete( Famma\Core\Express_Idempotency::instance()->table(), array( 'order_id' => $famma_browser_order->get_id() ), array( '%d' ) );
		$famma_browser_order->delete( true );
	}
	foreach ( $famma_browser_ids as $famma_browser_id ) {
		$famma_browser_product = wc_get_product( absint( $famma_browser_id ) );
		if ( $famma_browser_product && 'browser-20261010' === $famma_browser_product->get_meta( '_famma_integration_run', true ) ) {
			$famma_browser_product->delete( true );
		}
	}
	delete_option( 'famma_browser_fixture_ids' );
	delete_option( 'famma_browser_hash_diagnostic' );
	delete_option( 'famma_browser_verify_diagnostic' );
	$famma_browser_shipping = absint( get_option( 'famma_browser_shipping_instance' ) );
	if ( $famma_browser_shipping ) {
		$famma_browser_zone = new WC_Shipping_Zone( 0 );
		$famma_browser_zone->delete_shipping_method( $famma_browser_shipping );
		delete_option( 'famma_browser_shipping_instance' );
	}
	WP_CLI::success( 'Browser products cleaned.' );
	return;
}

if ( empty( $famma_browser_ids ) ) {
	foreach ( array( '79', '49' ) as $famma_browser_price ) {
		$famma_browser_product = new WC_Product_Simple();
		$famma_browser_product->set_name( 'TEST LOCAL FAMMA ' . $famma_browser_price );
		$famma_browser_product->set_status( 'publish' );
		$famma_browser_product->set_regular_price( $famma_browser_price );
		$famma_browser_product->set_manage_stock( true );
		$famma_browser_product->set_stock_quantity( 50 );
		$famma_browser_product->set_virtual( false );
		$famma_browser_product->update_meta_data( '_famma_integration_run', 'browser-20261010' );
		$famma_browser_ids[] = $famma_browser_product->save();
	}
	update_option( 'famma_browser_fixture_ids', $famma_browser_ids, false );
}

WC_Install::create_pages();
update_option( 'woocommerce_currency', 'TND' );
update_option( 'woocommerce_default_country', 'TN' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes' ) );
update_option( 'woocommerce_terms_page_id', 0 );

$famma_browser_zone = new WC_Shipping_Zone( 0 );
if ( ! get_option( 'famma_browser_shipping_instance' ) ) {
	$famma_browser_shipping = $famma_browser_zone->add_shipping_method( 'free_shipping' );
	update_option( 'famma_browser_shipping_instance', $famma_browser_shipping, false );
}

$famma_browser_urls = array();
foreach ( $famma_browser_ids as $famma_browser_id ) {
	$famma_browser_urls[] = get_permalink( absint( $famma_browser_id ) );
}
WP_CLI::line(
	wp_json_encode(
		array(
			'ids'  => $famma_browser_ids,
			'urls' => $famma_browser_urls,
		)
	)
);
