<?php
/**
 * Lot 5 of the pixel reforms — two guards found undetected by fault injection:
 *  1. the server identifier of OrderPlaced is famma_orderplaced_<order id> (the browser sends the
 *     same one: Meta deduplicates on it), stable across attempts;
 *  2. CAPI_MAX_EVENT_AGE (6 days): an OrderPlaced older than the limit is never sent, one just
 *     inside it is, and the limit is the documented 6 days.
 * Run with: php scripts/tests-unit/test-capi-event-id-and-age.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi;
use Famma\Core\Config;

$capi        = Capi::instance();
$sent_events = static function (): array {
	return array_map( static fn( array $request ): array => json_decode( (string) $request['args']['body'], true )['data'][0], $GLOBALS['wp_test_http'] );
};

/**
 * Places an order the way the checkout does, then runs the queued send.
 *
 * @param int $id      Order identifier.
 * @param int $created Order creation time (Unix).
 * @return WC_Order
 */
$place = static function ( int $id, int $created ) use ( $capi ): WC_Order {
	$placed             = new WC_Order( $id );
	$placed->created_at = new DateTimeImmutable( '@' . $created );
	$placed->status     = 'pending-confirm';
	$capi->order_placed_after_checkout( array( 'result' => 'success' ), $id );
	do_action( 'famma_capi_orderplaced_send', $id );
	return $placed;
};

// The limit itself: 6 days.
l5_check( 518400 === Config::CAPI_MAX_EVENT_AGE, 'CAPI_MAX_EVENT_AGE is 6 days (518400 s), under the 7 days Meta accepts.' );

// 1. OrderPlaced identifier.
l5_reset();
$GLOBALS['famma_test_registry_stateful'] = true;
$placed                                  = $place( 8101, time() - 600 );
$events                                  = $sent_events();
l5_check( 1 === count( $events ) && 'OrderPlaced' === $events[0]['event_name'], 'OrderPlaced: one request leaves for a fresh order.' );
l5_check( 'famma_orderplaced_8101' === $events[0]['event_id'], 'OrderPlaced server event_id is exactly famma_orderplaced_<order id>.' );
l5_check( 'famma_orderplaced_8101' === $placed->get_meta( '_famma_orderplaced_event_id' ), 'OrderPlaced identifier stored on the order is the same one.' );

// Another order never shares the identifier.
$second = $place( 8102, time() - 600 );
$ids    = array_map( static fn( array $event ): string => $event['event_id'], $sent_events() );
l5_check( array( 'famma_orderplaced_8101', 'famma_orderplaced_8102' ) === $ids, 'Two orders, two distinct OrderPlaced identifiers.' );

// A retry of the same order keeps the identifier and sends nothing more once accepted.
do_action( 'famma_capi_orderplaced_send', 8101 );
l5_check( 2 === count( $GLOBALS['wp_test_http'] ), 'OrderPlaced replayed for an accepted order: no second request.' );
$GLOBALS['famma_test_registry_stateful'] = false;

// 2. The 6-day guard on OrderPlaced.
l5_reset();
$GLOBALS['famma_test_registry_stateful'] = true;
$place( 8201, time() - Config::CAPI_MAX_EVENT_AGE - 60 );
$lines = l5_capi_lines();
l5_check( array() === $GLOBALS['wp_test_http'], 'OrderPlaced older than 6 days: no request leaves.' );
l5_check( array() !== array_filter( $lines, static fn( string $line ): bool => str_contains( $line, 'state=too_old' ) ), 'OrderPlaced older than 6 days: journalled « too_old ».' );

l5_reset();
$GLOBALS['famma_test_registry_stateful'] = true;
$place( 8202, time() - Config::CAPI_MAX_EVENT_AGE + 600 );
l5_check( 1 === count( $GLOBALS['wp_test_http'] ), 'OrderPlaced 10 minutes inside the 6-day limit: sent.' );
$event = $sent_events()[0];
l5_check( $event['event_time'] <= time() && time() - $event['event_time'] < Config::CAPI_MAX_EVENT_AGE, 'The event_time sent is the order creation time, inside the window Meta accepts.' );
$GLOBALS['famma_test_registry_stateful'] = false;

echo 'CHECKS=' . ( $GLOBALS['l5_checks'] ?? 0 ) . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
