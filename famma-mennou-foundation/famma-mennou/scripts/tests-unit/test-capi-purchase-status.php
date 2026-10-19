<?php
/**
 * Lot 5, A8 (D5, T2.4) — « Terminée » for a cash-on-delivery order: COD
 * collection at priority 10, then one Purchase at priority 20; the registry
 * prevents a second send when it later moves to « Livrée ».
 * Run with: php scripts/tests-unit/test-capi-purchase-status.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi;
use Famma\Core\Order_Statuses;

$source = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/famma-core.php' );
l5_check(
	str_contains( $source, "add_action( 'woocommerce_order_status_delivered', array( \$this, 'mark_cod_paid_on_delivery' ), 10 );" )
	&& str_contains( $source, "add_action( 'woocommerce_order_status_completed', array( \$this, 'mark_cod_paid_on_completion' ), 10 );" )
	&& str_contains( $source, "add_action( 'woocommerce_order_status_delivered', array( \$this, 'fire_purchase_on_status' ), 20 );" )
	&& str_contains( $source, "add_action( 'woocommerce_order_status_completed', array( \$this, 'fire_purchase_on_status' ), 20 );" ),
	'Explicit priorities: COD collection at 10, Purchase at 20, on « Livrée » and « Terminée ».'
);
l5_check( 1 === substr_count( $source, "'woocommerce_order_status_delivered', array( \$this, 'fire_purchase_on_status' )" ) && ! str_contains( $source, 'fire_purchase_on_delivery' ), 'A single Purchase trigger remains (moved from Tracking to Capi).' );

$statuses = Famma_Unit_Harness::bare( '\Famma\Core\Order_Statuses' );
$capi     = Capi::instance();

// Completed COD order: collected, then one Purchase.
l5_reset();
$GLOBALS['famma_test_registry_stateful'] = true;
$wc_order                                = new WC_Order( 5900 );
$wc_order->status                        = 'completed';
$statuses->mark_cod_paid_on_completion( 5900 );
$capi->fire_purchase_on_status( 5900 );
l5_check( 'yes' === $wc_order->get_meta( '_famma_cod_paid' ) && in_array( 'payment_complete', $wc_order->notes, true ), 'T2.4: « Terminée » collects the COD payment (_famma_cod_paid, payment_complete).' );
l5_check( 1 === count( $GLOBALS['wp_test_http'] ) && (bool) $wc_order->get_meta( '_famma_purchase_sent' ), 'T2.4: « Terminée » sends one Purchase after the collection.' );
l5_check( 'famma_purchase_5900' === json_decode( (string) $GLOBALS['wp_test_http'][0]['args']['body'], true )['data'][0]['event_id'], 'The Purchase keeps the famma_purchase_<id> identifier.' );

// Then « Livrée »: no second send.
$wc_order->status = 'delivered';
$statuses->mark_cod_paid_on_delivery( 5900 );
$capi->fire_purchase_on_status( 5900 );
l5_check( 1 === count( $GLOBALS['wp_test_http'] ) && 1 === count( array_keys( $wc_order->notes, 'payment_complete', true ) ), 'Then « Livrée »: no second collection and no second Purchase.' );
$wc_order->meta = array_diff_key( $wc_order->meta, array( '_famma_purchase_sent' => true ) );
$capi->fire_purchase_on_status( 5900 );
l5_check( 1 === count( $GLOBALS['wp_test_http'] ), 'Even without the order marker, the registry prevents a second Purchase.' );

// Completed order of another payment method: WooCommerce behaviour, no Purchase.
l5_reset();
$card          = new WC_Order( 5901 );
$card->status  = 'completed';
$card->payment = 'stripe';
$statuses->mark_cod_paid_on_completion( 5901 );
$capi->fire_purchase_on_status( 5901 );
$lines = l5_capi_lines();
l5_check( '' === $card->get_meta( '_famma_cod_paid' ) && array() === $GLOBALS['wp_test_http'] && str_contains( $lines[0], 'state=ineligible reason=status:completed' ), 'A completed order paid otherwise is left to WooCommerce, with one ineligible line.' );
l5_check( ! Order_Statuses::is_cod_order( $card ) && Order_Statuses::is_cod_order( new WC_Order( 5902 ) ), 'is_cod_order: cod (or no method) is cash on delivery.' );

// Completed COD order not yet collected (priority order broken): no Purchase.
l5_reset();
$early         = new WC_Order( 5903 );
$early->status = 'completed';
$capi->fire_purchase_on_status( 5903 );
l5_check( array() === $GLOBALS['wp_test_http'], 'No Purchase on « Terminée » before the COD collection.' );

// payment_complete() is allowed from « completed » only for a COD order.
$cod_order         = new WC_Order( 5904 );
$cod_order->status = 'completed';
l5_check( in_array( 'completed', $statuses->allow_complete_from_delivered( array( 'pending' ), $cod_order ), true ) && ! in_array( 'completed', $statuses->allow_complete_from_delivered( array( 'pending' ), $card ), true ), 'payment_complete() from « completed » is allowed for COD orders only.' );
l5_check( 'completed' === $statuses->stay_delivered_when_paid( 'processing', 5904, $cod_order ), 'A completed order stays « completed » when paid.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
