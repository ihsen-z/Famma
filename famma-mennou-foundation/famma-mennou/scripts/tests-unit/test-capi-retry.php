<?php
/**
 * Lot 5, A6 (D1) and A10 (D7) — bounded retry of uncertain sends with the same
 * event_id, no retry for access or contract refusals, one famma-capi line per
 * outcome of OrderPlaced and Purchase (9 outcomes out of 9, T2.7).
 * Run with: php scripts/tests-unit/test-capi-retry.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi;
use Famma\Core\Capi_Retry;
use Famma\Core\Config;

$capi                                    = Capi::instance();
$GLOBALS['famma_test_registry_stateful'] = true;
$json                                    = static fn( array $error ): string => (string) json_encode( array( 'error' => $error ) );

// Delays: 5 min, 30 min, 2 h, 6 h, then nothing (5 attempts in all).
l5_check( array( 300, 1800, 7200, 21600, -1 ) === array_map( array( Capi_Retry::class, 'delay_after' ), array( 1, 2, 3, 4, 5 ) ), 'Retry delays are 5 min, 30 min, 2 h, 6 h, within MAX_ATTEMPTS.' );

// Scheduling: hook, group, arguments, uniqueness, 6-day limit.
l5_reset();
$now = time();
l5_check( 'scheduled' === Capi_Retry::schedule( 77, 'Purchase', 1, $now, $now ), 'A first transient failure schedules one retry.' );
$retry_action = $GLOBALS['as_test_actions'][0];
l5_check(
	$now + 300 === $retry_action[0] && 'famma_capi_retry' === $retry_action[1] && array(
		'order_id'   => 77,
		'event_name' => 'Purchase',
	) === $retry_action[2] && 'famma-capi' === $retry_action[3] && true === $retry_action[4],
	'Retry action: famma_capi_retry, group famma-capi, args order_id and event_name, unique, +5 min.'
);
l5_check( 'already' === Capi_Retry::schedule( 77, 'Purchase', 1, $now, $now ) && 1 === count( $GLOBALS['as_test_actions'] ), 'A pending retry is never duplicated.' );
// Action Scheduler 4.0 counts the running retry as existing: the retry that fails again must still plan the next one.
$GLOBALS['as_test_actions'][0][5] = 'in-progress';
l5_check( 'scheduled' === Capi_Retry::schedule( 77, 'Purchase', 2, $now, $now ) && 2 === count( $GLOBALS['as_test_actions'] ) && $now + 1800 === $GLOBALS['as_test_actions'][1][0] && 'pending' === $GLOBALS['as_test_actions'][1][5], 'A running retry (in-progress) does not block the next one, +30 min.' );
l5_check( 'already' === Capi_Retry::schedule( 77, 'Purchase', 2, $now, $now ) && 2 === count( $GLOBALS['as_test_actions'] ), 'Once the next retry is pending, it is never duplicated.' );
l5_check( 'too_old' === Capi_Retry::schedule( 78, 'Purchase', 4, $now - Config::CAPI_MAX_EVENT_AGE + 3600, $now ), 'No retry for an event_time that would exceed 6 days.' );
l5_check( 'exhausted' === Capi_Retry::schedule( 79, 'Purchase', 5, $now, $now ), 'No retry beyond the maximum number of attempts.' );

// D1 / T2.13: transient failure → uncertain + retry; an ordinary trigger never resends; the retry is accepted once.
foreach ( array(
	'wp_error'  => new WP_Error( 'http_request_failed', 'cURL error 7: connection refused' ),
	'timeout'   => new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ),
	'http500'   => l5_response( 500, 'Internal error' ),
	'no_events' => l5_response( 200, '{"events_received":0}' ),
) as $label => $failure ) {
	l5_reset();
	$wc_order                         = new WC_Order( 5300 );
	$GLOBALS['wp_test_http_response'] = $failure;
	$capi->fire_purchase_on_status( 5300 );
	l5_check( 1 === count( $GLOBALS['wp_test_http'] ) && ! $wc_order->get_meta( '_famma_purchase_sent' ) && 1 === count( $GLOBALS['as_test_actions'] ), 'T2.13 ' . $label . ': uncertain, retry planned.' );
	$GLOBALS['wp_test_http_response'] = l5_response( 200, '{"events_received":1}' );
	$capi->send_purchase( $wc_order, 'famma_purchase_5300' );
	$held = end( $GLOBALS['wc_test_log'] )['message'];
	l5_check( 1 === count( $GLOBALS['wp_test_http'] ) && str_contains( $held, 'state=held_uncertain' ), 'T2.13 ' . $label . ': an ordinary trigger never resends an uncertain event blindly.' );
	$retry  = l5_as_run_next()[2];
	$bodies = array_map( static fn( array $call ): array => json_decode( (string) $call['args']['body'], true ), $GLOBALS['wp_test_http'] );
	l5_check( 2 === count( $bodies ) && 'famma_purchase_5300' === $bodies[1]['data'][0]['event_id'] && $bodies[0]['data'][0]['event_time'] === $bodies[1]['data'][0]['event_time'], 'T2.13 ' . $label . ': the retry reuses the same event_id and event_time.' );
	l5_check( (bool) $wc_order->get_meta( '_famma_purchase_sent' ) && 2 === (int) $wc_order->get_meta( '_famma_capi_attempts' ) && array() === l5_as_find( 'famma_capi_retry', null, '', array( 'pending' ) ), 'T2.13 ' . $label . ': the retry is accepted, attempt 2, nothing else planned.' );
	do_action( 'famma_capi_retry', $retry['order_id'], $retry['event_name'] );
	l5_check( 2 === count( $GLOBALS['wp_test_http'] ), 'T2.13 ' . $label . ': a replayed retry never sends twice.' );
}

// Access and contract refusals: no automatic retry, alert.
foreach ( array(
	'access'   => array( 190, 'rouge' ),
	'contract' => array( 100, 'orange' ),
) as $label => $fixture ) {
	l5_reset();
	$wc_order                         = new WC_Order( 5310 );
	$GLOBALS['wp_test_http_response'] = l5_response( 400, $json( array( 'code' => $fixture[0] ) ) );
	$capi->fire_purchase_on_status( 5310 );
	$state = \Famma\Core\Capi_Health::evaluate()['statut'];
	l5_check( array() === $GLOBALS['as_test_actions'] && $fixture[1] === $state, 'No retry for ' . $label . ' (code ' . $fixture[0] . '), health ' . $fixture[1] . '.' );
}

// The whole chain runs as Action Scheduler does (each retry is in-progress while it fails again),
// then stops at MAX_ATTEMPTS.
l5_reset();
$wc_order                         = new WC_Order( 5320 );
$GLOBALS['wp_test_http_response'] = l5_response( 503, '{}' );
$chain_start                      = time();
$capi->fire_purchase_on_status( 5320 );
$runs = 0;
while ( $runs < 6 && null !== l5_as_run_next() ) {
	// Each run is one retry; the loop stops when nothing is pending any more.
	++$runs;
}
$chain_end = time();
$planned   = $GLOBALS['as_test_actions'];
l5_check( 4 === count( $planned ) && array() === l5_as_find( 'famma_capi_retry', null, '', array( 'pending', 'in-progress' ) ), 'D1: four retries planned one after the other, all run, none left.' );
$delays_ok = true;
foreach ( array( 300, 1800, 7200, 21600 ) as $index => $delay ) {
	$base      = $planned[ $index ][0] - $delay;
	$delays_ok = $delays_ok && $base >= $chain_start && $base <= $chain_end;
	$delays_ok = $delays_ok && array(
		'order_id'   => 5320,
		'event_name' => 'Purchase',
	) === $planned[ $index ][2];
}
l5_check( $delays_ok, 'D1: the retries come after 5 min, 30 min, 2 h then 6 h, same order and event.' );
l5_check( Config::CAPI_MAX_ATTEMPTS === count( $GLOBALS['wp_test_http'] ) && Config::CAPI_MAX_ATTEMPTS === (int) $wc_order->get_meta( '_famma_capi_attempts' ), 'Five transient failures: five requests, then no more retry.' );
$event_ids = array_unique( array_map( static fn( array $call ): string => (string) json_decode( (string) $call['args']['body'], true )['data'][0]['event_id'], $GLOBALS['wp_test_http'] ) );
l5_check( array( 'famma_purchase_5320' ) === array_values( $event_ids ), 'D1: the five attempts share the same event_id.' );

// D1: the rescue sweep takes every abandoned "sending" row out of that state, even when it
// cannot retry it (too old): 21 dead rows never hide a more recent interrupted send.
l5_reset();
$GLOBALS['famma_test_registry_stale'] = array();
$stale_row                            = static function ( string $key, int $order_id, int $event_time, int $updated ): array {
	return array(
		'event_key'     => $key,
		'order_id'      => $order_id,
		'event_name'    => 'Purchase',
		'event_id'      => 'famma_purchase_' . $order_id,
		'event_time'    => $event_time,
		'state'         => 'sending',
		'attempt_count' => 1,
		'updated_at'    => gmdate( 'Y-m-d H:i:s', $updated ),
	);
};
for ( $n = 0; $n < 21; $n++ ) {
	$GLOBALS['famma_test_registry_stale'][ 'dead' . $n ] = $stale_row( 'dead' . $n, 5500 + $n, time() - Config::CAPI_MAX_EVENT_AGE - 3600, time() - 3 * 86400 + $n );
}
$GLOBALS['famma_test_registry_stale']['recent'] = $stale_row( 'recent', 5600, time() - 3600, time() - 20 * 60 );
$swept_states                                   = static fn(): array => array_count_values( array_column( $GLOBALS['famma_test_registry_stale'], 'state' ) );
$first_sweep                                    = Capi_Retry::rescue_stale();
$first_lines                                    = l5_capi_lines();
$after_first                                    = array(
	'uncertain' => 20,
	'sending'   => 2,
);
l5_check( 0 === $first_sweep && $after_first === $swept_states() && 20 === count( $first_lines ) && str_contains( $first_lines[0], 'state=stale_sending reason=retry:too_old' ) && array() === $GLOBALS['as_test_actions'], 'D1 rescue: the 20 oldest dead sends become uncertain, one line each, no retry (too old).' );
$second_sweep = Capi_Retry::rescue_stale();
$recent_retry = l5_as_find( 'famma_capi_retry', Capi_Retry::args( 5600, 'Purchase' ), 'famma-capi', array( 'pending' ) );
l5_check( 1 === $second_sweep && array( 'uncertain' => 22 ) === $swept_states() && 1 === count( $recent_retry ) && 1 === count( $GLOBALS['as_test_actions'] ) && str_contains( l5_capi_lines()[21], 'state=stale_sending reason=retry:scheduled' ), 'D1 rescue: the 21st dead send no longer hides the recent interrupted one; its retry is planned.' );
l5_check( 0 === Capi_Retry::rescue_stale() && 22 === count( l5_capi_lines() ), 'D1 rescue: nothing left in "sending", nothing more to do.' );
$GLOBALS['famma_test_registry_stale'] = array();

// Missing registry table: one journal line and a red health state.
l5_reset();
$GLOBALS['famma_test_registry_unavailable'] = true;
$wc_order                                   = new WC_Order( 5330 );
$capi->fire_purchase_on_status( 5330 );
$lines = l5_capi_lines();
l5_check( array() === $GLOBALS['wp_test_http'] && 1 === count( $lines ) && str_contains( $lines[0], 'state=registry_unavailable' ) && 'rouge' === \Famma\Core\Capi_Health::state()['statut'], 'Missing registry table: no request, one journal line, red state.' );
$GLOBALS['famma_test_registry_unavailable'] = false;

// A10 / T2.7: each of the 9 outcomes of OrderPlaced writes exactly one line.
$outcomes = array();
$checkout = array( 'result' => 'success' );
$run      = static function ( string $label, callable $scenario ) use ( &$outcomes ): void {
	$before = count( l5_capi_lines() );
	$scenario();
	$new                = array_slice( l5_capi_lines(), $before );
	$outcomes[ $label ] = $new;
	l5_check( 1 === count( $new ), 'T2.7 ' . $label . ': exactly one famma-capi line (' . ( $new[0] ?? 'none' ) . ').' );
};
l5_reset();
$placed         = new WC_Order( 5400 );
$placed->status = 'pending-confirm';
$capi->order_placed_after_checkout( $checkout, 5400 );
$run( 'envoye', static fn() => do_action( 'famma_capi_orderplaced_send', 5400 ) );
$run( 'deja_envoye', static fn() => do_action( 'famma_capi_orderplaced_send', 5400 ) );
$disabled         = new WC_Order( 5401 );
$disabled->status = 'pending-confirm';
$capi->order_placed_after_checkout( $checkout, 5401 );
$run(
	'desactive',
	static function (): void {
		$GLOBALS['wp_test_options']['famma_meta_orderplaced_enabled'] = false;
		do_action( 'famma_capi_orderplaced_send', 5401 );
		$GLOBALS['wp_test_options']['famma_meta_orderplaced_enabled'] = true;
	}
);
$cancelled         = new WC_Order( 5402 );
$cancelled->status = 'pending-confirm';
$capi->order_placed_after_checkout( $checkout, 5402 );
$cancelled->status = 'cancelled';
$run( 'ineligible_annulee', static fn() => do_action( 'famma_capi_orderplaced_send', 5402 ) );
$admin              = new WC_Order( 5403 );
$admin->status      = 'pending-confirm';
$admin->created_via = 'admin';
$run( 'ineligible_admin', static fn() => $capi->order_placed_after_checkout( $checkout, 5403 ) );
$unconfigured         = new WC_Order( 5404 );
$unconfigured->status = 'pending-confirm';
$capi->order_placed_after_checkout( $checkout, 5404 );
$run(
	'non_configure',
	static function (): void {
		$GLOBALS['wp_test_options']['famma_meta_pixel_id'] = '';
		do_action( 'famma_capi_orderplaced_send', 5404 );
		$GLOBALS['wp_test_options']['famma_meta_pixel_id'] = '000000000000001';
	}
);
$failing         = new WC_Order( 5405 );
$failing->status = 'pending-confirm';
$capi->order_placed_after_checkout( $checkout, 5405 );
$run(
	'echec_transport',
	static function (): void {
		$GLOBALS['wp_test_http_response'] = new WP_Error( 'http_request_failed', 'cURL error 7' );
		do_action( 'famma_capi_orderplaced_send', 5405 );
		$GLOBALS['wp_test_http_response'] = l5_response( 200, '{"events_received":1}' );
	}
);
$run( 'ignore_registre_incertain', static fn() => do_action( 'famma_capi_orderplaced_send', 5405 ) );
$missing         = new WC_Order( 5406 );
$missing->status = 'pending-confirm';
$capi->order_placed_after_checkout( $checkout, 5406 );
$run(
	'ignore_registre_indisponible',
	static function (): void {
		$GLOBALS['famma_test_registry_unavailable'] = true;
		do_action( 'famma_capi_orderplaced_send', 5406 );
		$GLOBALS['famma_test_registry_unavailable'] = false;
	}
);
$expected = array(
	'envoye'                       => 'state=accepted',
	'deja_envoye'                  => 'state=already_sent',
	'desactive'                    => 'state=disabled',
	'ineligible_annulee'           => 'state=ineligible reason=status:cancelled',
	'ineligible_admin'             => 'state=ineligible reason=created_via:admin',
	'non_configure'                => 'state=not_configured reason=pixel',
	'echec_transport'              => 'state=transport_uncertain',
	'ignore_registre_incertain'    => 'state=held_uncertain',
	'ignore_registre_indisponible' => 'state=registry_unavailable',
);
foreach ( $expected as $label => $needle ) {
	l5_check( str_contains( $outcomes[ $label ][0] ?? '', $needle ), 'T2.7 ' . $label . ': ' . $needle );
}

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
