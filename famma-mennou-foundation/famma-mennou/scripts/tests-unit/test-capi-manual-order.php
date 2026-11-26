<?php
/**
 * L6 de la réévaluation (décision Q24 du 26/11) — OrderPlaced des commandes saisies à la main
 * dans l'écran de commande (Messenger, WhatsApp, téléphone) : 15 minutes après l'enregistrement,
 * tout revérifié au départ ; event_time = création ; `chat` pour un canal de messagerie, sinon
 * `phone_call` ; ni fbc ni IP ; external_id seulement s'il existe ; interrupteur activé par
 * défaut ; commandes du site non doublées ; paniers récupérés inchangés ; application / API exclues.
 * Run with: php scripts/tests-unit/test-capi-manual-order.php
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Standalone runner outside WordPress: $order is a local fixture.

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi;
use Famma\Core\Capi_Manual_Order;
use Famma\Core\Capi_Orderplaced_Watch;
use Famma\Core\Capi_Recovered_Cart;
use Famma\Core\Order_Channel;
use Famma\Core\Visitor_Inputs_Order;

$capi      = Capi::instance();
$recovered = Capi_Recovered_Cart::instance();
$manual    = Capi_Manual_Order::instance();
$product   = new WC_Product( 9901, 'SKU-9901', 'Article', 'article', 39.0 );

/**
 * Options of every scenario: the feature went live a day ago.
 *
 * @param array $extra Overrides.
 * @return void
 */
$reset = static function ( array $extra = array() ): void {
	l5_reset( $extra + array( Capi_Manual_Order::SINCE_OPTION => time() - DAY_IN_SECONDS ) );
	$GLOBALS['famma_test_registry_stateful'] = true;
};

/**
 * Order as the admin order screen leaves it at priority 60 (status, channel and test box saved).
 *
 * @param int    $id      Order identifier.
 * @param string $channel Order_Channel slug.
 * @param array  $meta    Extra meta.
 * @return WC_Order
 */
$entered = static function ( int $id, string $channel = 'telephone', array $meta = array() ) use ( $product ): WC_Order {
	$order              = new WC_Order( $id );
	$order->created_via = 'admin';
	$order->status      = 'pending-confirm';
	$order->phone       = '+21698765432';
	$order->first_name  = 'Amel';
	$order->country     = 'TN';
	$order->items       = array( new WC_Order_Item_Product( $product, 1 ) );
	$order->meta        = $meta + array( Order_Channel::META => $channel );
	return $order;
};
$sent = static function (): array {
	return array_map( static fn( array $call ): array => json_decode( (string) $call['args']['body'], true )['data'][0], $GLOBALS['wp_test_http'] );
};

// Telephone order entered in the admin: marked, queued for +15 min, sent with phone_call.
$reset();
$order = $entered( 9910 );
$saved = time();
$manual->admin_saved( 9910 );
$task = $GLOBALS['as_test_actions'][0] ?? array();
l5_check( (bool) $order->get_meta( Capi_Manual_Order::ENTRY_META ) && Capi_Manual_Order::is_manual( $order ), 'Order saved through the admin order screen: marked as a manual entry.' );
l5_check( 1 === count( $GLOBALS['as_test_actions'] ) && 'famma_capi_orderplaced_send' === $task[1] && $task[0] >= $saved + 900 && $task[0] <= time() + 900, 'Q24: OrderPlaced queued for +15 minutes, not immediately.' );
l5_check( Capi_Recovered_Cart::due_at( $order ) === $task[0] && Capi_Orderplaced_Watch::due_at( $order ) === $task[0] && Capi_Orderplaced_Watch::expected( $order ), 'Surveillance (lot 4): counted like a recovered cart, due_at = planned send time, OrderPlaced expected.' );
l5_as_run_next();
$event = $sent()[0] ?? array();
l5_check( 'OrderPlaced' === ( $event['event_name'] ?? '' ) && 'famma_orderplaced_9910' === $event['event_id'] && $order->get_date_created()->getTimestamp() === $event['event_time'], 'Sent at departure: stable event_id, event_time = order creation.' );
l5_check( 'phone_call' === $event['action_source'] && hash( 'sha256', '21698765432' ) === ( $event['user_data']['ph'] ?? '' ) && isset( $event['user_data']['fn'] ), 'Telephone channel: action_source phone_call; user_data = phone and name of the order.' );
l5_check( ! isset( $event['user_data']['fbc'] ) && ! isset( $event['user_data']['fbp'] ) && ! isset( $event['user_data']['client_ip_address'] ) && ! isset( $event['user_data']['external_id'] ) && ! isset( $event['event_source_url'] ), 'No visit: no fbc, fbp, IP, external_id or event_source_url.' );
$manual->admin_saved( 9910 );
l5_check( 1 === count( $sent() ) && 1 === count( $GLOBALS['as_test_actions'] ), 'Saving the order again never queues nor sends a second OrderPlaced.' );

// Messaging channels: chat.
foreach ( array( 'whatsapp', 'messenger' ) as $index => $channel ) {
	$reset();
	$entered( 9920 + $index, $channel );
	$manual->admin_saved( 9920 + $index );
	l5_as_run_next();
	l5_check( 'chat' === ( $sent()[0]['action_source'] ?? '' ), 'Channel ' . $channel . ': action_source chat.' );
}

// external_id only when the order carries a journal visitor.
$reset();
$entered( 9925, 'whatsapp', array( '_famma_visitor_id' => '0a1b2c3d-4e5f-4a6b-9c7d-8e9f0a1b2c3d' ) );
$manual->admin_saved( 9925 );
l5_as_run_next();
l5_check( hash( 'sha256', '0a1b2c3d-4e5f-4a6b-9c7d-8e9f0a1b2c3d' ) === ( $sent()[0]['user_data']['external_id'] ?? '' ), 'external_id sent only when _famma_visitor_id exists.' );

// Rechecked at departure: test box, cancelled, failed, switch off: nothing leaves.
foreach (
	array(
		'test box ticked within 15 min' => array( 'meta', Capi::TEST_ORDER_META, 'reason=test_order' ),
		'cancelled within 15 min'       => array( 'status', 'cancelled', 'reason=status:cancelled' ),
		'failed within 15 min'          => array( 'status', 'failed', 'reason=status:failed' ),
		'manual switch turned off'      => array( 'option', Capi_Manual_Order::OPTION, 'reason=manual_order:disabled' ),
		'OrderPlaced switch turned off' => array( 'option', 'famma_meta_orderplaced_enabled', 'state=disabled' ),
	) as $label => $change
) {
	$reset();
	$later = $entered( 9930 );
	$manual->admin_saved( 9930 );
	if ( 'status' === $change[0] ) {
		$later->status = $change[1];
	} elseif ( 'option' === $change[0] ) {
		$GLOBALS['wp_test_options'][ $change[1] ] = false;
	} else {
		$later->update_meta_data( $change[1], 'yes' );
	}
	l5_as_run_next();
	l5_check( array() === $GLOBALS['wp_test_http'] && str_contains( implode( "\n", l5_capi_lines() ), $change[2] ), 'Rechecked at departure, ' . $label . ': nothing sent (' . $change[2] . ').' );
}

// Not queued at all: test box ticked at creation, switch off, cancelled, draft, no product.
foreach (
	array(
		'test box ticked at creation' => array( Capi::TEST_ORDER_META => 'yes' ),
		'manual switch off'           => null,
		'cancelled at creation'       => 'cancelled',
		'auto-draft'                  => 'auto-draft',
		'no product'                  => 'no-product',
	) as $label => $case
) {
	$reset( null === $case ? array( Capi_Manual_Order::OPTION => false ) : array() );
	$quiet = $entered( 9940, 'telephone', is_array( $case ) ? $case : array() );
	if ( is_string( $case ) && 'no-product' !== $case ) {
		$quiet->status = $case;
	}
	if ( 'no-product' === $case ) {
		$quiet->items = array();
	}
	$manual->admin_saved( 9940 );
	l5_check( array() === $GLOBALS['as_test_actions'] && array() === $GLOBALS['wp_test_http'] && array() === l5_capi_lines(), 'Not queued (' . $label . '), and no journal noise.' );
}

// A draft completed later: the status change from the order list queues it once.
$reset();
$draft         = $entered( 9945 );
$draft->items  = array();
$draft->status = 'pending';
$manual->admin_saved( 9945 );
$draft->items = array( new WC_Order_Item_Product( $product, 1 ) );
$manual->status_changed( 9945, 'pending', 'pending-confirm' );
$manual->status_changed( 9945, 'pending-confirm', 'confirmed' );
l5_check( 1 === count( $GLOBALS['as_test_actions'] ), 'Product added, status changed: OrderPlaced queued once.' );

// Created before the feature went live: an old order reopened sends nothing.
$reset( array( Capi_Manual_Order::SINCE_OPTION => time() ) );
$old = $entered( 9950 );
$manual->admin_saved( 9950 );
l5_check( ! $old->get_meta( Capi_Manual_Order::ENTRY_META ) && array() === $GLOBALS['as_test_actions'], 'Order created before the go-live date: not marked, nothing queued.' );

// Application / API and code-created admin orders: excluded.
$reset();
$api              = $entered( 9951 );
$api->created_via = 'rest-api';
$manual->admin_saved( 9951 );
$coded = $entered( 9952 );
l5_check( array() === $GLOBALS['as_test_actions'] && ! Capi_Manual_Order::is_manual( $api ) && ! Capi_Manual_Order::is_manual( $coded ), 'REST API / app order, and admin order created by code (never saved through the screen): not manual.' );
l5_check( 'created_via:rest-api' === Capi_Recovered_Cart::block_reason( $api ) && 'created_via:admin' === Capi_Recovered_Cart::block_reason( $coded ), 'They keep their created_via reason.' );

// Site order: not doubled, still immediate.
$reset();
$site         = new WC_Order( 9960 );
$site->status = 'pending-confirm';
$site->items  = array( new WC_Order_Item_Product( $product, 1 ) );
$capi->order_placed_after_checkout( array( 'result' => 'success' ), 9960 );
$manual->admin_saved( 9960 );
$manual->status_changed( 9960, 'pending', 'pending-confirm' );
l5_check( 1 === count( $GLOBALS['as_test_actions'] ) && $GLOBALS['as_test_actions'][0][0] <= time() && 0 === Capi_Recovered_Cart::due_at( $site ), 'Site order: one immediate task, the admin hooks add nothing.' );
l5_as_run_next();
l5_check( 1 === count( $sent() ), 'Site order: one OrderPlaced.' );

// Recovered cart: unchanged (not marked as manual, phone_call, +15 min).
$reset();
$cart              = new WC_Order( 9970 );
$cart->created_via = 'admin';
$cart->status      = 'pending-confirm';
$cart->items       = array( new WC_Order_Item_Product( $product, 1 ) );
$cart->meta        = array(
	Order_Channel::META              => Order_Channel::RECOVERED,
	Visitor_Inputs_Order::META_INPUT => 7,
);
$manual->admin_saved( 9970 );
l5_check( ! $cart->get_meta( Capi_Manual_Order::ENTRY_META ) && array() === $GLOBALS['as_test_actions'], 'Recovered cart saved in the admin: not turned into a manual order.' );
Capi_Recovered_Cart::queue( $cart );
l5_as_run_next();
l5_check( 1 === count( $sent() ) && 'phone_call' === $sent()[0]['action_source'] && Capi_Recovered_Cart::due_at( $cart ) > time(), 'Recovered cart: still its own rule (queued +15 min, phone_call).' );

echo 'RESULT ' . $GLOBALS['l5_checks'] . '/' . $GLOBALS['l5_checks'] . PHP_EOL;
