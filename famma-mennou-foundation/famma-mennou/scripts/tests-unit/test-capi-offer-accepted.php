<?php
/**
 * OfferAccepted (post-purchase offer, server event, off by default) — lot 5 of the pixel reforms.
 * Nothing leaves when the switch is off, when the order is a test order or when the offer was not
 * accepted; the value is the offer amount alone; the event_id is stable; the 6-day guard holds.
 * Run with: php scripts/tests-unit/test-capi-offer-accepted.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';
Famma_Unit_Harness::load_include( 'offer-settings.php' );

use Famma\Core\Capi;
use Famma\Core\Config;
use Famma\Core\Offer_Settings;

$capi = Capi::instance();

/**
 * Builds an order with one 22 DT offer accepted an hour ago (main item 49 DT).
 *
 * @param int   $id    Order identifier.
 * @param array $extra Meta overrides.
 * @return WC_Order
 */
$offer_order = static function ( int $id, array $extra = array() ): WC_Order {
	new WC_Product( 7001, 'SKU-MAIN', 'Main product', 'main-product', 49.0 );
	new WC_Product( 7002, 'SKU-OFFER', 'Offer product', 'offer-product', 22.0 );
	$order         = new WC_Order( $id );
	$order->status = 'pending-confirm';
	$order->meta   = $extra + array(
		Offer_Settings::ORDER_ACCEPTED => (string) ( time() - 3600 ),
		Offer_Settings::ORDER_AMOUNT   => '22.000',
		Offer_Settings::ORDER_TARGET   => '7002',
	);
	return $order;
};
$sent_events = static function (): array {
	return array_map( static fn( array $request ): array => json_decode( (string) $request['args']['body'], true )['data'][0], $GLOBALS['wp_test_http'] );
};

// Switch off (the default): nothing leaves, one « disabled » line, no registry claim.
l5_reset();
$GLOBALS['famma_test_registry_acquire'] = array();
$sample                                 = $offer_order( 7100 );
$capi->send_offer_accepted( $sample );
$lines = l5_capi_lines();
l5_check( array() === $GLOBALS['wp_test_http'], 'OfferAccepted switch off: no request leaves.' );
l5_check( 1 === count( $lines ) && str_contains( $lines[0], 'state=disabled' ) && str_contains( $lines[0], 'OfferAccepted' ), 'OfferAccepted switch off: one « disabled » line in the famma-capi journal.' );
l5_check( array() === $GLOBALS['famma_test_registry_acquire'], 'OfferAccepted switch off: the delivery registry is not even claimed.' );

// Switch on, but a test order (_famma_test_order = yes): nothing leaves.
l5_reset( array( 'famma_meta_offeraccepted_enabled' => true ) );
$test_order = $offer_order( 7101, array( '_famma_test_order' => 'yes' ) );
$capi->send_offer_accepted( $test_order );
$lines = l5_capi_lines();
l5_check( array() === $GLOBALS['wp_test_http'] && 1 === count( $lines ) && str_contains( $lines[0], 'state=ineligible reason=test_order' ), 'OfferAccepted on a test order (_famma_test_order = yes): no request, « ineligible reason=test_order ».' );

// Switch on, offer never accepted: nothing leaves.
l5_reset( array( 'famma_meta_offeraccepted_enabled' => true ) );
$declined = $offer_order( 7102 );
$declined->update_meta_data( Offer_Settings::ORDER_ACCEPTED, '' );
$capi->send_offer_accepted( $declined );
l5_check( array() === $GLOBALS['wp_test_http'] && str_contains( l5_capi_lines()[0], 'reason=offer_not_accepted' ), 'OfferAccepted without an accepted offer: no request, « offer_not_accepted ».' );

// Switch on, cancelled order: nothing leaves.
l5_reset( array( 'famma_meta_offeraccepted_enabled' => true ) );
$cancelled         = $offer_order( 7103 );
$cancelled->status = 'cancelled';
$capi->send_offer_accepted( $cancelled );
l5_check( array() === $GLOBALS['wp_test_http'] && str_contains( l5_capi_lines()[0], 'reason=status:cancelled' ), 'OfferAccepted on a cancelled order: no request.' );

// Switch on, genuine order: one request, value = offer amount alone, stable event_id.
l5_reset( array( 'famma_meta_offeraccepted_enabled' => true ) );
$GLOBALS['famma_test_registry_stateful'] = true;
$real                                    = $offer_order( 7104 );
$capi->send_offer_accepted( $real );
$events = $sent_events();
l5_check( 1 === count( $events ) && 'OfferAccepted' === $events[0]['event_name'], 'OfferAccepted genuine order: exactly one OfferAccepted request leaves.' );
l5_check( 'famma_offeraccepted_7104' === $events[0]['event_id'], 'OfferAccepted event_id is famma_offeraccepted_<order id>.' );
l5_check( 22.0 === (float) $events[0]['custom_data']['value'], 'OfferAccepted value is the offer amount alone (22), not the order total.' );
l5_check( array( '7002' ) === $events[0]['custom_data']['content_ids'] && 1 === $events[0]['custom_data']['num_items'], 'OfferAccepted carries only the added item (WooCommerce ID), not the main product.' );

// Same order again: the registry keeps a single request.
$capi->send_offer_accepted( $real );
l5_check( 1 === count( $GLOBALS['wp_test_http'] ), 'OfferAccepted sent twice for one order: the registry keeps a single request.' );
$GLOBALS['famma_test_registry_stateful'] = false;

// The event_id depends on the order alone (identical on every attempt).
l5_reset( array( 'famma_meta_offeraccepted_enabled' => true ) );
$first_id = '';
foreach ( array( 1, 2 ) as $round ) {
	$GLOBALS['wp_test_http'] = array();
	$capi->send_offer_accepted( $offer_order( 7105 ) );
	$first_id = '' === $first_id ? $sent_events()[0]['event_id'] : $first_id;
	l5_check( $first_id === $sent_events()[0]['event_id'], 'OfferAccepted event_id identical on attempt ' . $round . '.' );
}

// 6-day guard: an offer accepted more than CAPI_MAX_EVENT_AGE ago is never sent; just inside the limit it is.
l5_reset( array( 'famma_meta_offeraccepted_enabled' => true ) );
$old = $offer_order( 7106, array( Offer_Settings::ORDER_ACCEPTED => (string) ( time() - Config::CAPI_MAX_EVENT_AGE - 60 ) ) );
$capi->send_offer_accepted( $old );
l5_check( array() === $GLOBALS['wp_test_http'] && str_contains( l5_capi_lines()[0], 'state=too_old' ), 'OfferAccepted accepted 6 days and 1 minute ago: not sent, « too_old ».' );
l5_reset( array( 'famma_meta_offeraccepted_enabled' => true ) );
$edge = $offer_order( 7107, array( Offer_Settings::ORDER_ACCEPTED => (string) ( time() - Config::CAPI_MAX_EVENT_AGE + 600 ) ) );
$capi->send_offer_accepted( $edge );
l5_check( 1 === count( $GLOBALS['wp_test_http'] ), 'OfferAccepted accepted 10 minutes inside the 6-day limit: sent.' );

// The queued path used in production: a missing order is journalled, never fatal.
l5_reset( array( 'famma_meta_offeraccepted_enabled' => true ) );
$capi->send_queued_offer_accepted( 999999 );
l5_check( array() === $GLOBALS['wp_test_http'] && str_contains( l5_capi_lines()[0], 'order_missing' ), 'Queued OfferAccepted for a missing order: nothing sent, « order_missing ».' );

echo 'CHECKS=' . ( $GLOBALS['l5_checks'] ?? 0 ) . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
