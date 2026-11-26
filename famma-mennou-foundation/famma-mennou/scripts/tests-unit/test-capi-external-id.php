<?php
/**
 * Phase B du pixel — external_id côté serveur (Capi_External_Id, Capi_User_Data).
 * user_data.external_id = SHA-256 (hex minuscule) de `_famma_visitor_id` sur TOUS les
 * événements serveur : OrderPlaced, Purchase, OrderCancelled, OrderReturned, OfferAccepted.
 * Absent (jamais inventé) sans identifiant visiteur, ou s'il n'est pas un UUID du journal.
 * L'égalité avec la valeur du navigateur est prouvée par test-capi-external-id.js.
 * Run with: php scripts/tests-unit/test-capi-external-id.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';
Famma_Unit_Harness::load_include( 'offer-settings.php' );

use Famma\Core\Capi;
use Famma\Core\Capi_External_Id;
use Famma\Core\Capi_User_Data;
use Famma\Core\Offer_Settings;

$statuses = Famma_Unit_Harness::bare( '\Famma\Core\Order_Statuses' );
$capi     = Capi::instance();
$product  = new WC_Product( 9801, 'SKU-9801', 'Article', 'article', 49.0 );
new WC_Product( 9802, 'SKU-9802', 'Offre', 'offre', 22.0 );
$visitor  = '5d6e7f80-91a2-4b3c-8d4e-5f60718293a4';
$expected = hash( 'sha256', $visitor );

/**
 * Every event of one order, by name.
 *
 * @return array<string, array<string, mixed>>
 */
$sent = static function (): array {
	$events = array();
	foreach ( $GLOBALS['wp_test_http'] as $call ) {
		$event                          = json_decode( (string) $call['args']['body'], true )['data'][0];
		$events[ $event['event_name'] ] = $event;
	}
	return $events;
};

/**
 * A site order taken through OrderPlaced, OfferAccepted, Purchase, then refunded (OrderReturned),
 * and a twin cancelled after OrderPlaced (OrderCancelled).
 *
 * @param int   $id   Order identifier.
 * @param array $meta Visitor meta.
 * @return void
 */
$full_cycle = static function ( int $id, array $meta ) use ( $capi, $statuses, $product ): void {
	$order             = new WC_Order( $id );
	$order->status     = 'pending-confirm';
	$order->phone      = '+21620000000';
	$order->created_at = new DateTimeImmutable( '-1 hour' );
	$order->items      = array( new WC_Order_Item_Product( $product, 1 ) );
	$order->meta       = $meta + array(
		Offer_Settings::ORDER_ACCEPTED => (string) ( time() - 600 ),
		Offer_Settings::ORDER_AMOUNT   => '22.000',
		Offer_Settings::ORDER_TARGET   => '9802',
	);
	$capi->order_placed_after_checkout( array( 'result' => 'success' ), $id );
	l5_as_run_next();
	$capi->send_offer_accepted( $order );
	$order->status = 'delivered';
	$statuses->remember_delivery( $id );
	$statuses->mark_cod_paid_on_delivery( $id );
	$capi->fire_purchase_on_status( $id );
	l5_as_run_next();
	$order->status = 'refunded';
	$capi->queue_order_outcome( $id, 'delivered', 'refunded' );
	l5_as_run_next();

	$twin             = new WC_Order( $id + 1 );
	$twin->status     = 'pending-confirm';
	$twin->phone      = '+21620000000';
	$twin->created_at = new DateTimeImmutable( '-1 hour' );
	$twin->items      = $order->items;
	$twin->meta       = $meta;
	$capi->order_placed_after_checkout( array( 'result' => 'success' ), $id + 1 );
	l5_as_run_next();
	$twin->status = 'cancelled';
	$capi->queue_order_outcome( $id + 1, 'pending-confirm', 'cancelled' );
	l5_as_run_next();
};

// Pure function: SHA-256 hex of the lower-case UUID; nothing for a non-UUID.
l5_check( Capi_External_Id::from_visitor( $visitor ) === $expected && Capi_External_Id::from_visitor( ' ' . strtoupper( $visitor ) . ' ' ) === $expected, 'from_visitor(): SHA-256 hex of the trimmed, lower-case UUID.' );
l5_check( 1 === preg_match( '/^[0-9a-f]{64}$/D', $expected ), 'The fingerprint is 64 lower-case hexadecimal characters.' );
l5_check( '' === Capi_External_Id::from_visitor( '' ) && '' === Capi_External_Id::from_visitor( 'abc' ) && '' === Capi_External_Id::from_visitor( '5d6e7f80-91a2-1b3c-8d4e-5f60718293a4' ), 'Empty, arbitrary or non-v4 identifiers: no external_id.' );

// Every server event of an order with a visitor carries the same external_id.
l5_reset(
	array(
		'famma_meta_offeraccepted_enabled'  => true,
		'famma_capi_purchase_delay_minutes' => 0,
	)
);
$GLOBALS['famma_test_registry_stateful'] = true;
$full_cycle( 9810, array( '_famma_visitor_id' => $visitor ) );
$events = $sent();
ksort( $events );
l5_check( array( 'OfferAccepted', 'OrderCancelled', 'OrderPlaced', 'OrderReturned', 'Purchase' ) === array_keys( $events ), 'Setup: OrderPlaced, OfferAccepted, Purchase, OrderReturned and OrderCancelled all left.' );
foreach ( $events as $name => $event ) {
	l5_check( ( $event['user_data']['external_id'] ?? '' ) === $expected, $name . ': user_data.external_id = expected fingerprint (sent value = expected value).' );
}
l5_check( ! str_contains( (string) wp_json_encode( $GLOBALS['wp_test_http'] ), $visitor ), 'The raw visitor identifier never leaves: only its fingerprint.' );

// No visitor identifier (or an invalid one): no external_id on any event.
foreach ( array( array(), array( '_famma_visitor_id' => 'pas-un-uuid' ) ) as $index => $meta ) {
	l5_reset(
		array(
			'famma_meta_offeraccepted_enabled'  => true,
			'famma_capi_purchase_delay_minutes' => 0,
		)
	);
	$GLOBALS['famma_test_registry_stateful'] = true;
	$full_cycle( 9820 + 10 * $index, $meta );
	$events = $sent();
	l5_check( 5 === count( $events ) && array() === array_filter( $events, static fn( array $event ): bool => isset( $event['user_data']['external_id'] ) ), ( array() === $meta ? 'No _famma_visitor_id' : 'Invalid _famma_visitor_id' ) . ': none of the 5 events carries external_id.' );
}

// Capi_User_Data::build() for an order with no browser (admin order): external_id still from the order meta.
$admin              = new WC_Order( 9850 );
$admin->created_via = 'admin';
$admin->meta        = array( '_famma_visitor_id' => strtoupper( $visitor ) );
l5_check( ( Capi_User_Data::build( $admin, false )['external_id'] ?? '' ) === $expected, 'Off-site order: external_id from the stored visitor, upper case normalised.' );

echo 'RESULT ' . $GLOBALS['l5_checks'] . '/' . $GLOBALS['l5_checks'] . PHP_EOL;
