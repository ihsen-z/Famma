<?php
/**
 * Relecture du lot 59, M7 — commande saisie à la main (L6) écartée au départ
 * sans tentative (case « Commande d'essai » cochée puis décochée, commande
 * annulée puis rétablie) : remise en file au prochain enregistrement ou
 * changement de statut, une seule fois ; jamais tant qu'une tâche attend,
 * jamais après une tentative réelle (le bouton « Renvoyer » reste la voie).
 * Run with: php scripts/tests-unit/test-capi-manual-order-requeue.php
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Standalone runner outside WordPress: $order is a local fixture.

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi;
use Famma\Core\Capi_Manual_Order;
use Famma\Core\Capi_Orderplaced_Watch;
use Famma\Core\Order_Channel;

Capi::instance();
$manual  = Capi_Manual_Order::instance();
$product = new WC_Product( 9901, 'SKU-9901', 'Article', 'article', 39.0 );

$reset   = static function (): void {
	l5_reset( array( Capi_Manual_Order::SINCE_OPTION => time() - DAY_IN_SECONDS ) );
	$GLOBALS['famma_test_registry_stateful'] = true;
};
$entered = static function ( int $id ) use ( $product ): WC_Order {
	$order              = new WC_Order( $id );
	$order->created_via = 'admin';
	$order->status      = 'pending-confirm';
	$order->phone       = '+21698765432';
	$order->first_name  = 'Amel';
	$order->country     = 'TN';
	$order->items       = array( new WC_Order_Item_Product( $product, 1 ) );
	$order->meta        = array( Order_Channel::META => 'whatsapp' );
	return $order;
};
$pending = static fn(): int => count( array_filter( $GLOBALS['as_test_actions'], static fn( array $a ): bool => 'pending' === $a[5] ) );
$sent    = static fn(): int => count( $GLOBALS['wp_test_http'] );

// Test box ticked by mistake, then unticked: OrderPlaced leaves once.
$reset();
$order = $entered( 9950 );
$manual->admin_saved( 9950 );
$order->update_meta_data( Capi::TEST_ORDER_META, 'yes' );
l5_as_run_next();
l5_check( 0 === $sent() && 0 === $pending(), 'M7: test box ticked within 15 min — skipped at departure, nothing sent, no task left.' );
$order->delete_meta_data( Capi::TEST_ORDER_META );
$manual->admin_saved( 9950 );
l5_check( 1 === $pending() && Capi_Orderplaced_Watch::expected( $order ), 'M7: test box unticked and order saved — OrderPlaced queued again.' );
$manual->admin_saved( 9950 );
l5_check( 1 === $pending(), 'M7: saved again while the task waits — still one task.' );
l5_as_run_next();
$manual->admin_saved( 9950 );
l5_check( 1 === $sent() && 0 === $pending() && 'famma_orderplaced_9950' === ( json_decode( (string) $GLOBALS['wp_test_http'][0]['args']['body'], true )['data'][0]['event_id'] ?? '' ), 'M7: sent once with its stable event_id; saving after the send queues nothing.' );

// Cancelled, then restored before anything left: queued again by the status change.
$reset();
$order = $entered( 9951 );
$manual->admin_saved( 9951 );
$order->status = 'cancelled';
l5_as_run_next();
$order->status = 'pending-confirm';
$manual->status_changed( 9951, 'cancelled', 'pending-confirm' );
l5_as_run_next();
l5_check( 1 === $sent(), 'M7: cancelled then restored — OrderPlaced queued again and sent once.' );

// A real attempt (attempts recorded) is never requeued by a save: the Resend button stays the way.
$reset();
$order = $entered( 9952 );
$manual->admin_saved( 9952 );
l5_as_run_next();
$order->delete_meta_data( '_famma_orderplaced_sent' );
$order->update_meta_data( '_famma_orderplaced_attempts', 1 );
$manual->admin_saved( 9952 );
l5_check( 0 === $pending() && 'already' === Capi_Manual_Order::maybe_queue( $order ), 'M7: after a real attempt, a save never queues a new task.' );

echo 'RESULT ' . $GLOBALS['l5_checks'] . '/' . $GLOBALS['l5_checks'] . PHP_EOL;
