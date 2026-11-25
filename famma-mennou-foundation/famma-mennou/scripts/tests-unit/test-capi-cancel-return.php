<?php
/**
 * Lot 2 des réformes du pixel (24/11) — OrderCancelled et OrderReturned.
 * OrderCancelled une fois quand une commande dont l'OrderPlaced a été ACCEPTÉ
 * passe à Annulée, Refusée ou Livraison échouée ; OrderReturned une fois quand
 * une commande dont le Purchase a été ACCEPTÉ passe à Retournée ou Remboursée.
 * Mêmes user_data, custom_data réduit (currency, value, order_id, content_ids)
 * et filtré par le profil ; même registre, mêmes relances, même garde des
 * 6 jours ; interrupteur « Envoyer les annulations et retours à Meta » activé
 * par défaut ; jamais pour une commande d'essai.
 * Run with: php scripts/tests-unit/test-capi-cancel-return.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi;
use Famma\Core\Config;

$statuses = Famma_Unit_Harness::bare( '\Famma\Core\Order_Statuses' );
$capi     = Capi::instance();
$product  = new WC_Product( 8301, 'SKU-8301', 'Article', 'article', 49.0 );

/**
 * Events sent so far, by name.
 *
 * @param string $name Event name.
 * @return array<int, array<string, mixed>>
 */
$sent = static function ( string $name ): array {
	$events = array();
	foreach ( $GLOBALS['wp_test_http'] as $call ) {
		$event = json_decode( (string) $call['args']['body'], true )['data'][0] ?? array();
		if ( ( $event['event_name'] ?? '' ) === $name ) {
			$events[] = $event;
		}
	}
	return $events;
};

/**
 * A real site order whose OrderPlaced Meta accepted (and its Purchase too when asked).
 *
 * @param int  $id        Order identifier.
 * @param bool $delivered Whether the Purchase was accepted as well.
 * @return WC_Order
 */
$accepted_order = static function ( int $id, bool $delivered = false ) use ( $capi, $statuses, $product ): WC_Order {
	$wc_order             = new WC_Order( $id );
	$wc_order->status     = 'pending-confirm';
	$wc_order->email      = 'client@example.invalid';
	$wc_order->phone      = '+21620000000';
	$wc_order->created_at = new DateTimeImmutable( '-1 hour' );
	$wc_order->items      = array( new WC_Order_Item_Product( $product, 1 ) );
	$capi->order_placed_after_checkout( array( 'result' => 'success' ), $id );
	l5_as_run_next();
	if ( $delivered ) {
		$wc_order->status = 'delivered';
		$statuses->remember_delivery( $id );
		$statuses->mark_cod_paid_on_delivery( $id );
		$capi->fire_purchase_on_status( $id );
	}
	return $wc_order;
};

/**
 * Status change, as WooCommerce fires woocommerce_order_status_changed.
 *
 * @param WC_Order $wc_order Order.
 * @param string   $to    New status.
 */
$move = static function ( WC_Order $wc_order, string $to ) use ( $capi ): void {
	$from             = $wc_order->status;
	$wc_order->status = $to;
	$capi->queue_order_outcome( $wc_order->get_id(), $from, $to );
};

// Defaults and hooks.
l5_reset();
l5_check( Config::instance()->meta_cancel_return_enabled(), 'Switch « Envoyer les annulations et retours à Meta »: ON by default.' );
$settings = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/tracking-settings.php' );
l5_check( str_contains( $settings, "'famma_meta_cancel_return_enabled'" ) && str_contains( $settings, "'default'           => true," ) && str_contains( $settings, 'Envoyer les annulations et retours à Meta' ), 'The switch is registered in the « Mesure » screen (Settings API, default true).' );

// Cancellation after an accepted OrderPlaced: one OrderCancelled.
l5_reset( array( 'famma_capi_purchase_delay_minutes' => 0 ) );
$wc_order = $accepted_order( 8300 );
l5_check( (bool) $wc_order->get_meta( '_famma_orderplaced_sent' ), 'Setup: OrderPlaced accepted by Meta.' );
$move( $wc_order, 'cancelled' );
l5_check(
	1 === count(
		l5_as_find(
			Capi::OUTCOME_HOOK,
			array(
				'order_id'   => 8300,
				'event_name' => 'OrderCancelled',
			),
			'famma-capi',
			array( 'pending' )
		)
	),
	'Annulée: one OrderCancelled task queued (group famma-capi).'
);
l5_as_run_next();
$cancelled = $sent( 'OrderCancelled' );
l5_check( 1 === count( $cancelled ) && 'famma_ordercancelled_8300' === $cancelled[0]['event_id'], 'One OrderCancelled, event_id famma_ordercancelled_<id>.' );
l5_check(
	array( 'content_ids', 'currency', 'order_id', 'value' ) === ( static function ( array $keys ): array {
		sort( $keys );
		return $keys;
	} )( array_keys( $cancelled[0]['custom_data'] ) ),
	'custom_data: currency, value, order_id, content_ids only.'
);
l5_check( 49.0 === (float) $cancelled[0]['custom_data']['value'] && 'TND' === $cancelled[0]['custom_data']['currency'] && '8300' === $cancelled[0]['custom_data']['order_id'] && array() !== $cancelled[0]['custom_data']['content_ids'], 'Value = order total, currency, order id, content ids of the order.' );
$placed = $sent( 'OrderPlaced' );
l5_check( array_keys( $placed[0]['user_data'] ) === array_keys( $cancelled[0]['user_data'] ), 'Same user_data keys as the OrderPlaced of the order.' );
l5_check( (bool) $wc_order->get_meta( '_famma_ordercancelled_sent' ), 'The order is marked: OrderCancelled sent.' );
$move( $wc_order, 'refused' );
l5_as_run_next();
$capi->send_order_outcome( $wc_order, 'OrderCancelled' );
l5_check( 1 === count( $sent( 'OrderCancelled' ) ), 'Then « Refusée », and a direct send: still a single OrderCancelled.' );

// « Livraison échouée » counts as a cancellation too.
l5_reset();
$failed = $accepted_order( 8302 );
$move( $failed, 'failed-delivery' );
l5_as_run_next();
l5_check( 1 === count( $sent( 'OrderCancelled' ) ), 'Livraison échouée: one OrderCancelled.' );

// Cancellation without an accepted OrderPlaced: nothing.
l5_reset();
$never         = new WC_Order( 8303 );
$never->status = 'pending-confirm';
$move( $never, 'cancelled' );
l5_check( array() === $GLOBALS['as_test_actions'] && array() === $GLOBALS['wp_test_http'] && str_contains( implode( "\n", l5_capi_lines() ), 'reason=orderplaced_not_accepted' ), 'No accepted OrderPlaced: nothing queued, journal reason orderplaced_not_accepted.' );

// Status back before the task ran: rechecked at send time.
l5_reset();
$undo = $accepted_order( 8304 );
$move( $undo, 'cancelled' );
$undo->status = 'confirmed';
l5_as_run_next();
l5_check( array() === $sent( 'OrderCancelled' ) && str_contains( implode( "\n", l5_capi_lines() ), 'state=ineligible reason=status:confirmed' ), 'Cancelled then confirmed before the send: nothing sent.' );

// Return after an accepted Purchase: one OrderReturned; « Remboursée » adds nothing.
l5_reset( array( 'famma_capi_purchase_delay_minutes' => 0 ) );
$returned = $accepted_order( 8305, true );
l5_check( (bool) $returned->get_meta( '_famma_purchase_sent' ), 'Setup: Purchase accepted by Meta.' );
$move( $returned, 'returned' );
l5_as_run_next();
$move( $returned, 'refunded' );
l5_as_run_next();
$back = $sent( 'OrderReturned' );
l5_check( 1 === count( $back ) && 'famma_orderreturned_8305' === $back[0]['event_id'] && 49.0 === (float) $back[0]['custom_data']['value'], 'Retournée then Remboursée: a single OrderReturned, event_id famma_orderreturned_<id>.' );
l5_check( array() === $sent( 'OrderCancelled' ), 'A return is not reported as a cancellation.' );

// « Remboursée » alone after an accepted Purchase.
l5_reset( array( 'famma_capi_purchase_delay_minutes' => 0 ) );
$refund = $accepted_order( 8306, true );
$move( $refund, 'refunded' );
l5_as_run_next();
l5_check( 1 === count( $sent( 'OrderReturned' ) ), 'Remboursée: one OrderReturned.' );

// Return without an accepted Purchase: nothing.
l5_reset();
$no_purchase = $accepted_order( 8307 );
$move( $no_purchase, 'returned' );
l5_check( array() === $sent( 'OrderReturned' ) && 0 === count( l5_as_find( Capi::OUTCOME_HOOK, null, '', array() ) ) && str_contains( implode( "\n", l5_capi_lines() ), 'reason=purchase_not_accepted' ), 'No accepted Purchase: no OrderReturned, reason purchase_not_accepted.' );

// Switch off: nothing, neither at queue time nor at send time.
l5_reset( array( 'famma_meta_cancel_return_enabled' => false ) );
$off = $accepted_order( 8308 );
$move( $off, 'cancelled' );
l5_check( 0 === count( l5_as_find( Capi::OUTCOME_HOOK, null, '', array() ) ) && str_contains( implode( "\n", l5_capi_lines() ), 'state=disabled reason=option' ), 'Switch off: nothing queued, journal disabled.' );
l5_reset();
$late_off = $accepted_order( 8309 );
$move( $late_off, 'cancelled' );
$GLOBALS['wp_test_options']['famma_meta_cancel_return_enabled'] = false;
l5_as_run_next();
l5_check( array() === $sent( 'OrderCancelled' ), 'Switch turned off after queuing: the queued task sends nothing.' );

// Test order: nothing.
l5_reset();
$trial                                = $accepted_order( 8310 );
$trial->meta[ Capi::TEST_ORDER_META ] = 'yes';
$move( $trial, 'cancelled' );
$capi->send_order_outcome( $trial, 'OrderCancelled' );
l5_check( array() === $sent( 'OrderCancelled' ), 'Test order: no OrderCancelled.' );

// Same registry and bounded retries, same event_id.
l5_reset();
$GLOBALS['famma_test_registry_stateful'] = true;
$retry                                   = $accepted_order( 8311 );
$GLOBALS['wp_test_http_response']        = l5_response( 503, '{"error":{"message":"busy","code":2}}' );
$move( $retry, 'cancelled' );
l5_as_run_next();
l5_check(
	1 === count( $sent( 'OrderCancelled' ) ) && 1 === count(
		l5_as_find(
			'famma_capi_retry',
			array(
				'order_id'   => 8311,
				'event_name' => 'OrderCancelled',
			),
			'famma-capi',
			array( 'pending' )
		)
	),
	'Transient failure: one bounded retry planned for OrderCancelled.'
);
$GLOBALS['wp_test_http_response'] = l5_response( 200, '{"events_received":1,"messages":[],"fbtrace_id":"QAL5"}', array( 'facebook-api-version' => 'v26.0' ) );
l5_as_run_next();
$retried = $sent( 'OrderCancelled' );
l5_check( 2 === count( $retried ) && $retried[0]['event_id'] === $retried[1]['event_id'] && $retried[0]['event_time'] === $retried[1]['event_time'] && (bool) $retry->get_meta( '_famma_ordercancelled_sent' ), 'The retry reuses event_id and event_time, then marks the order sent.' );
$GLOBALS['famma_test_registry_acquire'] = array();
$capi->send_order_outcome( $retry, 'OrderCancelled' );
l5_check( 2 === count( $sent( 'OrderCancelled' ) ), 'Accepted once: never sent again.' );

// Six-day guard.
l5_reset();
$old = $accepted_order( 8312 );
$move( $old, 'cancelled' );
$old->meta['_famma_ordercancelled_event_time'] = time() - 7 * DAY_IN_SECONDS;
l5_as_run_next();
l5_check( array() === $sent( 'OrderCancelled' ) && str_contains( implode( "\n", l5_capi_lines() ), 'state=too_old' ), 'An event_time older than 6 days is never sent.' );

// Minimal data profile: only currency and value.
l5_reset( array( 'famma_meta_data_profile' => 'minimal' ) );
$minimal = $accepted_order( 8313 );
$move( $minimal, 'cancelled' );
l5_as_run_next();
$event = $sent( 'OrderCancelled' )[0] ?? array();
l5_check( array( 'currency', 'value' ) === array_keys( $event['custom_data'] ?? array() ), 'Minimal profile: custom_data filtered by Capi_Data_Profile (currency, value).' );

// Neutral vocabulary.
$core = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/famma-core.php' );
l5_check( str_contains( $core, "'cancelled'       => 'OrderCancelled'," ) && str_contains( $core, "'returned'        => 'OrderReturned'," ), 'Event names OrderCancelled / OrderReturned (neutral vocabulary).' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
