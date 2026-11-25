<?php
/**
 * Lot 2 des réformes du pixel (24/11) — Purchase retardé et revérifié.
 * Le Purchase part 15 minutes après le passage à « Livrée », seulement si la
 * commande est encore « Livrée » (ou « Terminée » après « Livrée ») ; son
 * event_time est l'heure de livraison (la dernière) ; une seule tâche en
 * attente par commande ; délai 0 = envoi immédiat ; « Terminée » sans passage
 * par « Livrée » ne donne rien ; le rapprochement n'attend rien pendant le délai.
 * Run with: php scripts/tests-unit/test-capi-purchase-delay.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi;
use Famma\Core\Config;
use Famma\Core\Order_Statuses;

if ( ! function_exists( 'Famma\Core\time' ) ) {
	throw new RuntimeException( 'FAIL: the injectable plugin clock (double-clock.php) is required.' );
}

$statuses = Famma_Unit_Harness::bare( '\Famma\Core\Order_Statuses' );
$capi     = Capi::instance();
$t0       = time() - 600;

/**
 * Freezes the plugin clock.
 *
 * @param int $now Unix time.
 */
$at = static function ( int $now ): void {
	$GLOBALS['famma_test_now'] = $now;
};

/**
 * Purchase requests sent so far.
 *
 * @return array<int, array<string, mixed>>
 */
$purchases = static function (): array {
	$events = array();
	foreach ( $GLOBALS['wp_test_http'] as $call ) {
		$event = json_decode( (string) $call['args']['body'], true )['data'][0] ?? array();
		if ( 'Purchase' === ( $event['event_name'] ?? '' ) ) {
			$events[] = $event;
		}
	}
	return $events;
};

/**
 * Pending in-memory delayed-Purchase actions.
 *
 * @return int
 */
$pending = static function (): int {
	return count( l5_as_find( Capi::PURCHASE_DUE_HOOK, null, 'famma-capi', array( 'pending' ) ) );
};

/**
 * Status change to « Livrée », as WooCommerce runs it (priorities 5, 10, 20).
 *
 * @param int $order_id Order.
 */
$deliver = static function ( int $order_id ) use ( $statuses, $capi ): void {
	wc_get_order( $order_id )->status = 'delivered';
	$statuses->remember_delivery( $order_id );
	$statuses->mark_cod_paid_on_delivery( $order_id );
	$capi->fire_purchase_on_status( $order_id );
};

// Source: the delivery time is remembered before the collection and the Purchase hook; it is
// forgotten (priority 5, three arguments) when the order is cancelled, refused, failed, returned or refunded.
$source = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/famma-core.php' );
l5_check( str_contains( $source, "add_action( 'woocommerce_order_status_delivered', array( \$this, 'remember_delivery' ), 5 );" ) && str_contains( $source, "add_action( self::PURCHASE_DUE_HOOK, array( \$this, 'fire_purchase_on_status' ) );" ), 'Hooks: delivery time at priority 5, delayed task calls the same Purchase rule.' );
l5_check( str_contains( $source, "add_action( 'woocommerce_order_status_changed', array( \$this, 'forget_delivery' ), 5, 3 );" ) && array( 'cancelled', 'refused', 'failed-delivery', 'returned', 'refunded' ) === Order_Statuses::DELIVERY_UNDONE, 'Hook: forget_delivery at priority 5 with 3 arguments, on the five undoing statuses.' );

// Delay setting (« Mesure » screen, minutes): 15 min by default, 0 allowed, bounded to one hour.
l5_reset();
l5_check( 900 === Config::instance()->capi_purchase_delay() && ! Config::capi_purchase_delay_forced(), 'Default delay: 900 seconds, no constant.' );
foreach ( array(
	array( 0, 0 ),
	array( '2', 120 ),
	array( 99, 3600 ),
	array( -5, 0 ),
	array( 'abc', 900 ),
) as $case ) {
	$GLOBALS['wp_test_options']['famma_capi_purchase_delay_minutes'] = $case[0];
	l5_check( Config::instance()->capi_purchase_delay() === $case[1], 'Delay field ' . wp_json_encode( $case[0] ) . ' min gives ' . $case[1] . ' s.' );
}
foreach ( array(
	array( '15', 15 ),
	array( '0', 0 ),
	array( '90', 60 ),
	array( '-3', 0 ),
	array( 'x', 15 ),
	array( array(), 15 ),
) as $case ) {
	l5_check( Config::sanitize_purchase_delay_minutes( $case[0] ) === $case[1], 'Field sanitizer: ' . wp_json_encode( $case[0] ) . ' → ' . $case[1] . ' min.' );
}
$settings = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/tracking-settings.php' );
l5_check( str_contains( $settings, "'famma_capi_purchase_delay_minutes'," ) && str_contains( $settings, "array( Config::class, 'sanitize_purchase_delay_minutes' )" ) && str_contains( $settings, 'FAMMA_CAPI_PURCHASE_DELAY de wp-config.php impose ce délai' ), 'The « Mesure » screen registers the field (Settings API, sanitizer) and says when the constant wins.' );

// Livrée → (15 min) → one Purchase whose event_time is the delivery time.
l5_reset();
$at( $t0 );
$first = new WC_Order( 8100 );
$deliver( 8100 );
l5_check( array() === $GLOBALS['wp_test_http'] && 1 === $pending(), 'Livrée: no Purchase yet, one delayed task pending.' );
l5_check( $t0 + 900 === $GLOBALS['as_test_actions'][0][0] && array( 'order_id' => 8100 ) === $GLOBALS['as_test_actions'][0][2], 'The task is due 900 s after the delivery, group famma-capi, argument order_id.' );
l5_check( $t0 === (int) $first->get_meta( '_famma_purchase_event_time' ) && $t0 === (int) $first->get_meta( Order_Statuses::DELIVERED_AT ), 'The delivery time is stored and becomes the Purchase event_time.' );
l5_check( str_contains( implode( "\n", l5_capi_lines() ), 'state=delayed reason=scheduled' ), 'The famma-capi journal says the Purchase is delayed.' );
$at( $t0 + 899 );
l5_as_run_next();
l5_check( array() === $purchases() && 1 === $pending(), 'A task run one second early sends nothing and leaves one task pending.' );
$at( $t0 + 900 );
l5_as_run_next();
$sent = $purchases();
l5_check( 1 === count( $sent ) && $t0 === (int) $sent[0]['event_time'] && 'famma_purchase_8100' === $sent[0]['event_id'], 'After 15 minutes: one Purchase, event_time = delivery time, same event_id.' );
l5_check( 0 === $pending() && (bool) $first->get_meta( '_famma_purchase_sent' ), 'No task left; the order is marked sent.' );

// Livrée → Confirmée before 15 minutes → no Purchase.
l5_reset();
$at( $t0 );
$back = new WC_Order( 8101 );
$deliver( 8101 );
$at( $t0 + 300 );
$back->status = 'confirmed';
$at( $t0 + 900 );
l5_as_run_next();
l5_check( array() === $GLOBALS['wp_test_http'] && str_contains( implode( "\n", l5_capi_lines() ), 'state=ineligible reason=status:confirmed' ), 'Livrée → Confirmée within the delay: the task sends nothing (status rechecked at send time).' );

// Annulée → Terminée (never delivered) → neither collected (rule 4) nor Purchase; reconciliation expects nothing.
l5_reset();
$at( $t0 );
$revived         = new WC_Order( 8102 );
$revived->status = 'completed';
$statuses->mark_cod_paid_on_completion( 8102 );
$capi->fire_purchase_on_status( 8102 );
l5_check( array() === $GLOBALS['wp_test_http'] && 0 === $pending() && str_contains( implode( "\n", l5_capi_lines() ), 'state=ineligible reason=not_collected' ), 'Annulée → Terminée: no Purchase, no task, reason not_collected.' );
l5_check( '' === $revived->get_meta( '_famma_cod_paid' ) && ! in_array( 'payment_complete', $revived->notes, true ) && str_contains( (string) ( $revived->notes[0] ?? '' ), 'NON encaissé' ), 'Rule 4: « Terminée » without « Livrée » is not collected; a note says so.' );
l5_check( 'not_collected' === Order_Statuses::purchase_ineligibility( $revived ), 'The shared Purchase rule says not_collected.' );
$reconcile = Famma_Unit_Harness::bare( '\Famma\Core\Capi_Reconcile' );
$cell      = $reconcile->cell( $revived, 'Purchase', array(), $t0 );
l5_check( ! $cell['expected'] && ! $cell['resend'] && 'not_collected' === $cell['reason'], 'Reconciliation does not expect (nor offer to resend) a Purchase the sender would refuse.' );
$historical         = new WC_Order( 8108 );
$historical->status = 'completed';
$historical->meta   = array( '_famma_cod_paid' => 'yes' );
$cell               = $reconcile->cell( $historical, 'Purchase', array(), $t0 );
l5_check( 'not_via_delivered' === Order_Statuses::purchase_ineligibility( $historical ) && ! $cell['expected'], 'An order collected at « Terminée » before this rule, never « Livrée »: not_via_delivered, not expected.' );

// Livrée → Terminée → exactly one Purchase, at the delivery time.
l5_reset();
$at( $t0 );
$done = new WC_Order( 8103 );
$deliver( 8103 );
$at( $t0 + 120 );
$done->status = 'completed';
$statuses->mark_cod_paid_on_completion( 8103 );
$capi->fire_purchase_on_status( 8103 );
l5_check( 1 === $pending() && 1 === count( $GLOBALS['as_test_actions'] ) && array() === $GLOBALS['wp_test_http'], 'Livrée → Terminée within the delay: still one single task, nothing sent.' );
$at( $t0 + 900 );
l5_as_run_next();
$sent = $purchases();
l5_check( 1 === count( $sent ) && $t0 === (int) $sent[0]['event_time'], 'Livrée → Terminée: one Purchase, event_time = delivery time.' );
$capi->fire_purchase_on_status( 8103 );
l5_check( 1 === count( $purchases() ), 'A later « Terminée » hook sends no second Purchase.' );

// Delay 0: immediate send (rollback switch).
l5_reset( array( 'famma_capi_purchase_delay_minutes' => 0 ) );
$at( $t0 );
new WC_Order( 8104 );
$deliver( 8104 );
$sent = $purchases();
l5_check( 1 === count( $sent ) && 0 === count( $GLOBALS['as_test_actions'] ) && $t0 === (int) $sent[0]['event_time'], 'Delay 0: the Purchase leaves at once, without any task, event_time = delivery time.' );

// Back and forth: a single pending task, the last delivery time counts.
l5_reset();
$at( $t0 );
$flip = new WC_Order( 8105 );
$deliver( 8105 );
$at( $t0 + 60 );
$flip->status = 'confirmed';
$at( $t0 + 120 );
$deliver( 8105 );
$at( $t0 + 180 );
$flip->status = 'shipped';
$at( $t0 + 240 );
$deliver( 8105 );
l5_check( 1 === $pending() && 1 === count( $GLOBALS['as_test_actions'] ), 'Three deliveries in four minutes: one single delayed task.' );
l5_check( $t0 + 240 === (int) $flip->get_meta( '_famma_purchase_event_time' ), 'The last delivery time is the event_time while nothing is claimed.' );
$at( $t0 + 900 );
l5_as_run_next();
l5_check( array() === $purchases() && 1 === $pending(), 'The first task, run before the new due time, sends nothing and leaves exactly one pending task.' );
$at( $t0 + 240 + 900 );
l5_as_run_next();
$sent = $purchases();
l5_check( 1 === count( $sent ) && $t0 + 240 === (int) $sent[0]['event_time'] && 0 === $pending(), 'Then one Purchase, at the last delivery time; no task left.' );

// A claimed Purchase never gets its event_time rewritten.
l5_reset();
$GLOBALS['famma_test_registry_stateful'] = true;
$GLOBALS['wp_test_http_response']        = l5_response( 503, '{"error":{"message":"busy","code":2}}' );
$at( $t0 );
$claimed = new WC_Order( 8106 );
$deliver( 8106 );
$at( $t0 + 900 );
l5_as_run_next();
l5_check( 1 === count( $purchases() ) && ! $claimed->get_meta( '_famma_purchase_sent' ) && 'famma_purchase_8106' === $claimed->get_meta( '_famma_purchase_event_id' ), 'A transient failure: the Purchase is claimed, not sent.' );
$at( $t0 + 1000 );
$claimed->status = 'confirmed';
$deliver( 8106 );
l5_check( $t0 === (int) $claimed->get_meta( '_famma_purchase_event_time' ), 'A new delivery after the claim leaves the stored event_time untouched.' );
$GLOBALS['wp_test_http_response'] = l5_response( 200, '{"events_received":1,"messages":[],"fbtrace_id":"QAL5"}', array( 'facebook-api-version' => 'v26.0' ) );
$at( $t0 + 1000 + 900 );
$capi->send_retry( 8106, 'Purchase' );
$sent = $purchases();
l5_check( 2 === count( $sent ) && $t0 === (int) $sent[1]['event_time'], 'The retry keeps the registry event_time (first delivery).' );

// Reconciliation: no expected Purchase (and no « Renvoyer ») during the delay.
l5_reset();
$at( $t0 );
$waiting = new WC_Order( 8107 );
$deliver( 8107 );
$cell = $reconcile->cell( $waiting, 'Purchase', array(), $t0 + 60 );
l5_check( ! $cell['expected'] && ! $cell['resend'] && 'purchase_delay' === $cell['reason'], 'Reconciliation during the delay: purchase_delay, not expected, no resend button.' );
$cell = $reconcile->cell( $waiting, 'Purchase', array(), $t0 + 901 );
l5_check( $cell['expected'] && $cell['resend'], 'Reconciliation after the delay: expected again, resend allowed.' );
$GLOBALS['wc_test_order_query'] = array( $waiting );
$gaps                           = \Famma\Core\Capi_Purchase_Gaps::count( $t0 + 60 );
l5_check( 0 === $gaps['missing'] && 0 === $gaps['lost'], 'Health: a Purchase waiting for its delay raises no missing-Purchase alert.' );
$reconcile_totals = $reconcile->build( $t0 + 60 )['totals'];
l5_check( 0 === $reconcile_totals['purchase_expected'] && ! isset( $reconcile_totals['ineligible']['purchase_delay'] ), 'Reconciliation totals neither expect it nor count it as ineligible.' );
$GLOBALS['wc_test_order_query'] = array();
Famma_Unit_Harness::load_include( 'capi-reconcile-view.php' );
$GLOBALS['wp_test_options']['famma_capi_purchase_delay_minutes'] = 7;
$label = Famma_Unit_Harness::call( '\Famma\Core\Capi_Reconcile_View', 'reason_label', array( 'purchase_delay' ) );
l5_check( str_contains( $label, '7 min' ) && ! str_contains( $label, '15' ), 'The reconciliation label shows the real delay (7 min here), not « 15 minutes » hard-coded.' );

/**
 * Status change as WooCommerce runs it: woocommerce_order_status_changed (priority 5).
 *
 * @param int    $order_id Order.
 * @param string $to       New status.
 */
$move = static function ( int $order_id, string $to ) use ( $statuses ): void {
	$moved         = wc_get_order( $order_id );
	$from          = $moved->status;
	$moved->status = $to;
	$statuses->forget_delivery( $order_id, $from, $to );
};

// Livrée → Annulée → Terminée within the delay: the delivery is forgotten, no Purchase.
l5_reset();
$at( $t0 );
$undone = new WC_Order( 8109 );
$deliver( 8109 );
$at( $t0 + 300 );
$move( 8109, 'cancelled' );
l5_check( 0 === Order_Statuses::delivered_at( $undone ) && 'yes' === $undone->get_meta( '_famma_cod_paid' ), 'Livrée → Annulée: the delivery time is forgotten (the COD marker of the delivery stays, unchanged rule).' );
$at( $t0 + 600 );
$move( 8109, 'completed' );
$statuses->mark_cod_paid_on_completion( 8109 );
$capi->fire_purchase_on_status( 8109 );
$at( $t0 + 900 );
l5_as_run_next();
l5_check( array() === $purchases() && 0 === $pending() && str_contains( implode( "\n", l5_capi_lines() ), 'state=ineligible reason=not_via_delivered' ), 'Livrée → Annulée → Terminée: the delayed task sends no Purchase (not_via_delivered).' );

// Livrée → Annulée → Livrée: one Purchase, at the new delivery time.
l5_reset();
$at( $t0 );
$again = new WC_Order( 8110 );
$deliver( 8110 );
$at( $t0 + 300 );
$move( 8110, 'cancelled' );
$at( $t0 + 600 );
$deliver( 8110 );
$redelivered = $t0 + 600;
l5_check( Order_Statuses::delivered_at( $again ) === $redelivered && (int) $again->get_meta( '_famma_purchase_event_time' ) === $redelivered && 1 === $pending(), 'Livrée → Annulée → Livrée: new delivery time, still one pending task.' );
$at( $t0 + 900 );
l5_as_run_next();
$at( $t0 + 600 + 900 );
l5_as_run_next();
$sent = $purchases();
l5_check( 1 === count( $sent ) && $t0 + 600 === (int) $sent[0]['event_time'] && 0 === $pending(), 'Then one Purchase, with the new delivery time as event_time.' );

// Action Scheduler cannot store the task: error in the journal, health signal, immediate send.
l5_reset();
$at( $t0 );
$GLOBALS['as_test_schedule_fails'] = true;
new WC_Order( 8111 );
$deliver( 8111 );
unset( $GLOBALS['as_test_schedule_fails'] );
$lines = implode( "\n", l5_capi_lines() );
l5_check( str_contains( $lines, 'state=queue_failed reason=scheduler:failed' ) && ! str_contains( $lines, 'state=delayed' ), 'Scheduling failure: journal queue_failed (error), never « delayed / already ».' );
l5_check( 'error' === ( array_values( array_filter( $GLOBALS['wc_test_log'], static fn( array $entry ): bool => str_contains( $entry['message'], 'queue_failed' ) ) )[0]['level'] ?? '' ), 'The queue_failed line is written at the error level.' );
$sent = $purchases();
l5_check( 1 === count( $sent ) && $t0 === (int) $sent[0]['event_time'], 'Scheduling failure: the Purchase leaves at once rather than being lost (event_time = delivery time).' );
$signals = \Famma\Core\Capi_Health::signals();
$codes   = array_column( \Famma\Core\Capi_Health_Rules::reasons( $signals, $t0 ), 'code' );
l5_check( 8111 === (int) ( $signals['purchase_unqueued']['order_id'] ?? 0 ) && in_array( 'purchase_unqueued', $codes, true ), 'Health: the failure is a reason of the health state (orange, order number).' );
l5_check( ! in_array( 'purchase_unqueued', array_column( \Famma\Core\Capi_Health_Rules::reasons( $signals, $t0 + 8 * DAY_IN_SECONDS ), 'code' ), true ), 'The health reason expires after the 7-day signal window.' );
l5_reset();
$at( $t0 );
new WC_Order( 8112 );
$deliver( 8112 );
l5_check( 'info' === ( array_values( array_filter( $GLOBALS['wc_test_log'], static fn( array $entry ): bool => str_contains( $entry['message'], 'state=delayed' ) ) )[0]['level'] ?? '' ), 'A normal « delayed » line is written at the info level.' );
unset( $GLOBALS['wc_test_order_query'], $GLOBALS['famma_test_now'] );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
