<?php
/**
 * Phase B, lot 4 — corrections après la relecture : I2 (OrderPlaced « attendu »
 * seulement s'il a été accepté pour envoi, alerte toujours levable), Q19 (délai
 * compté depuis l'heure d'envoi prévue), M1 (verrou d'envoi, e-mail de retour
 * unique), M2 (« déjà renvoyé » seulement après un envoi, filtrage SQL avant
 * la limite), M3 (tâches retirées à la désactivation).
 * Run with: php scripts/tests-unit/test-capi-surveillance-relecture.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Doubles are documented by the API they replace.
require __DIR__ . '/bootstrap-capi.php';
Famma_Unit_Harness::load_include( 'capi-health-watch.php' );
if ( is_file( ABSPATH . 'wp-content/plugins/famma-core/includes/capi-meta-quality.php' ) ) {
	Famma_Unit_Harness::load_include( 'capi-meta-quality-parser.php' );
	Famma_Unit_Harness::load_include( 'capi-meta-quality.php' );
}

use Famma\Core\Capi_Health;
use Famma\Core\Capi_Health_Alert;
use Famma\Core\Capi_Health_Watch;
use Famma\Core\Capi_Orderplaced_Ack;
use Famma\Core\Capi_Orderplaced_Watch;
use Famma\Core\Capi_Recovered_Cart;
use Famma\Core\Capi_Refusal_Resend;
use Famma\Core\Order_Channel;
use Famma\Core\Visitor_Inputs_Order;

// WordPress doubles used only by this suite.
function esc_url( $url ): string {
	return (string) $url;
}
function wp_nonce_field( string $action ): void {
	echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '" />';
}
function submit_button( string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true ): void {
	echo '<button>' . esc_html( $text ) . '</button>';
}
function absint( $value ): int {
	return abs( (int) $value );
}
function wp_clear_scheduled_hook( string $hook ): int {
	$found = isset( $GLOBALS['wp_test_cron'][ $hook ] ) ? 1 : 0;
	unset( $GLOBALS['wp_test_cron'][ $hook ] );
	return $found;
}
function as_unschedule_all_actions( string $hook, array $args = array(), string $group = '' ): void {
	foreach ( l5_as_find( $hook, null, $group, array( 'pending' ) ) as $id ) {
		$GLOBALS['as_test_actions'][ $id - 1 ][5] = 'canceled';
	}
}

$now    = time();
$mails  = static fn(): int => count( $GLOBALS['wp_test_mail'] ?? array() );
$codes  = static fn( array $state ): array => array_column( $state['raisons'], 'code' );
$order  = static function ( int $id, int $age, array $meta = array(), string $via = 'checkout' ) use ( $now ): WC_Order {
	$wc_order              = new WC_Order( $id );
	$wc_order->created_at  = ( new DateTimeImmutable() )->setTimestamp( $now - $age );
	$wc_order->meta        = $meta;
	$wc_order->status      = 'processing';
	$wc_order->created_via = $via;
	return $wc_order;
};
$recov  = static function ( int $id, int $age, array $meta = array() ) use ( $order ): WC_Order {
	$wc_order        = $order(
		$id,
		$age,
		$meta + array(
			Order_Channel::META              => Order_Channel::RECOVERED,
			Visitor_Inputs_Order::META_INPUT => 55,
		),
		'admin'
	);
	$wc_order->items = array( new WC_Order_Item_Product( new WC_Product( 9740, 'SKU-9740', 'Article', 'article', 39.0 ), 1 ) );
	return $wc_order;
};
$accept = array( '_famma_orderplaced_checkout_accepted' => 1 );

// I2 — the three cases of the review: no OrderPlaced was ever accepted for sending.
l5_reset( array( Capi_Orderplaced_Watch::SINCE => $now - 6 * 3600 ) );
$GLOBALS['wc_test_order_query'] = array(
	$recov( 7401, 2 * 3600 ),          // 1. Recovered cart, product added later without a status change: never queued.
	$recov( 7402, 2 * 3600 ),          // 2. Recovered cart created while its switch was off, switch back on.
	$order( 7403, 2 * 3600 ),          // 3. Site order placed while OrderPlaced was off, switch back on.
);
l5_check( Capi_Recovered_Cart::is_recovered( $GLOBALS['wc_test_orders'][7401] ) && '' === \Famma\Core\Capi::instance()->order_placed_block( $GLOBALS['wc_test_orders'][7401] ), 'Fixture: 7401 is an eligible recovered cart for the sender rule.' );
$state = Capi_Health::evaluate( $now );
l5_check( 'vert' === $state['statut'] && 0 === $mails(), 'I2: none of the three cases (never queued) turns red nor sends an e-mail.' );

// Q19 — recovered carts: the 30 minutes run from the planned send time.
l5_reset( array( Capi_Orderplaced_Watch::SINCE => $now - 6 * 3600 ) );
// Lot 7 stores the planned send time in `_famma_orderplaced_due_at` (Capi_Recovered_Cart::due_at()).
$GLOBALS['wc_test_order_query'] = array( $recov( 7410, 40 * 60, $accept + array( Capi_Recovered_Cart::DUE_META => $now - 25 * 60 ) ), $order( 7411, 40 * 60, $accept ) );
l5_check( array( 7411 => '7411' ) === Capi_Orderplaced_Watch::sort( $now )['missing'], 'Q19: recovered cart queued for 15 min after creation, 40 min old = 25 min after its planned send → not yet; site order 40 min old → reported.' );
l5_check( array( 7410 => '7410', 7411 => '7411' ) === Capi_Orderplaced_Watch::sort( $now + 10 * 60 )['missing'], 'Q19: 35 min after the planned send → the recovered cart is reported too.' );
add_filter( 'famma_capi_orderplaced_due_at', static fn( int $due ): int => $due + 3600 );
l5_check( array() === Capi_Orderplaced_Watch::sort( $now + 10 * 60 )['missing'], 'Filter famma_capi_orderplaced_due_at can move the planned send time.' );
unset( $GLOBALS['wp_test_hooks']['famma_capi_orderplaced_due_at'] );

// I2 — an alert can always be lifted by the admin: « Acquitter les alertes OrderPlaced ».
l5_reset( array( Capi_Orderplaced_Watch::SINCE => $now - 6 * 3600 ) );
$lost                                  = $order( 7420, 3 * 3600, $accept );
$GLOBALS['famma_test_registry_orders'] = array(
	7420 => array(
		'OrderPlaced' => array(
			'state'      => 'uncertain',
			'event_time' => $now - 3 * 3600,
			'updated_at' => gmdate( 'Y-m-d H:i:s', $now - 2 * 86400 ),
		),
	),
);
$GLOBALS['wc_test_order_query']        = array( $order( 7421, 3600, $accept ), $lost );
$state                                 = Capi_Health::evaluate( $now );
l5_check( array( 'orderplaced_missing', 'orderplaced_lost' ) === array_values( array_intersect( $codes( $state ), array( 'orderplaced_missing', 'orderplaced_lost' ) ) ), 'Fixture: one resendable (red), one lost (orange).' );
ob_start();
Capi_Orderplaced_Ack::render( $state );
$form = (string) ob_get_clean();
l5_check( str_contains( $form, 'value="' . Capi_Orderplaced_Ack::ACTION . '"' ) && str_contains( $form, 'nonce-' . Capi_Orderplaced_Ack::ACTION ) && ! str_contains( $form, 'order_id' ), 'Mesure panel: « Acquitter » with its nonce; no order id taken from the request.' );
$GLOBALS['wp_test_caps'] = array( 'manage_woocommerce' => false );
try {
	Capi_Orderplaced_Ack::instance()->handle();
	$denied = '';
} catch ( RuntimeException $e ) {
	$denied = $e->getMessage();
}
l5_check( str_starts_with( $denied, 'wp_die' ) && ! Capi_Orderplaced_Ack::acknowledged( $lost ), 'Without manage_woocommerce: refused, nothing acknowledged.' );
$GLOBALS['wp_test_caps'] = array();
try {
	Capi_Orderplaced_Ack::instance()->handle();
	$location = '';
} catch ( Famma_Test_Redirect $redirect ) {
	$location = $redirect->getMessage();
}
l5_check( str_contains( $location, 'famma_capi_op_ack=2' ) && Capi_Orderplaced_Ack::acknowledged( $lost ) && 'vert' === $GLOBALS['wp_test_options']['famma_capi_health']['statut'], 'Acknowledged: both orders (red and orange) leave the health state, which is green again.' );
l5_check( str_contains( (string) end( $lost->notes ), 'acquittée' ) && isset( $GLOBALS['wp_test_hooks'][ 'admin_post_' . Capi_Orderplaced_Ack::ACTION ] ) && ! isset( $GLOBALS['wp_test_hooks'][ 'admin_post_nopriv_' . Capi_Orderplaced_Ack::ACTION ] ), 'Order note written; admin-post action, never nopriv.' );
l5_check( 0 === Capi_Orderplaced_Ack::acknowledge_all( $now ), 'Nothing left to acknowledge.' );

// M1 — send lock, recovery e-mail sent once even when the mail server refuses it.
l5_reset();
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = $now - 37 * 3600;
$GLOBALS['wp_test_options'][ Capi_Health_Alert::LOCK ] = $now - 30;
Capi_Health::evaluate( $now );
l5_check( 0 === $mails(), 'M1: another evaluation holds the send lock → no second e-mail.' );
$GLOBALS['wp_test_options'][ Capi_Health_Alert::LOCK ] = $now - 600;
Capi_Health::evaluate( $now + 60 );
l5_check( 1 === $mails() && ! isset( $GLOBALS['wp_test_options'][ Capi_Health_Alert::LOCK ] ), 'M1: a lock older than 5 min is taken over; released after the send.' );
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = $now;
$GLOBALS['wp_test_mail_fails']                      = true;
Capi_Health::evaluate( $now + 120 );
Capi_Health::evaluate( $now + 420 );
Capi_Health::evaluate( $now + 3720 );
$GLOBALS['wp_test_mail_fails'] = false;
l5_check( 1 === count( $GLOBALS['wp_test_mail_failed'] ?? array() ) && 1 === $mails(), 'M1: recovery e-mail refused by the server → tried once, not every 5 minutes.' );

// M2 — « already resent » only after a real send; SQL filters before the limit.
l5_reset();
Capi_Refusal_Resend::instance();
$key  = static fn( int $id ): string => hash( 'sha256', '000000000000001|OrderPlaced|famma_orderplaced_' . $id );
$row  = static fn( int $id, int $age, int $attempts = 1, string $class = 'access' ): array => array(
	'event_key'     => $key( $id ),
	'order_id'      => $id,
	'event_name'    => 'OrderPlaced',
	'event_id'      => 'famma_orderplaced_' . $id,
	'event_time'    => $now - $age,
	'state'         => 'rejected',
	'attempt_count' => $attempts,
	'attempts'      => wp_json_encode( array( array( 'class' => $class ) ) ),
);
$rows = array( $row( 7430, 2 * 3600 ) );
for ( $n = 0; $n < 120; $n++ ) {
	$rows[] = $row( 7500 + $n, 600 + $n, 5 );
}
$GLOBALS['famma_test_registry_stateful'] = true;
$GLOBALS['famma_test_registry_rejected'] = $rows;
$GLOBALS['famma_test_registry'][ $key( 7430 ) ] = array(
	'state'         => 'rejected',
	'attempt_count' => 1,
	'event_time'    => $now - 2 * 3600,
	'order_id'      => 7430,
);
$target = $order( 7430, 2 * 3600, $accept );
$GLOBALS['wp_test_environment'] = 'staging';
$first  = Capi_Refusal_Resend::sweep( $now );
l5_check( 1 === $first['resent'] && ! isset( get_option( Capi_Refusal_Resend::DONE, array() )[ $key( 7430 ) ] ), 'M2: 120 newer exhausted refusals do not hide the older resendable one; outcome « blocked_environment » (nothing sent) → not marked as resent.' );
$GLOBALS['wp_test_environment'] = 'production';
Capi_Refusal_Resend::sweep( $now + 60 );
l5_check( $target->get_meta( '_famma_orderplaced_sent' ) && isset( get_option( Capi_Refusal_Resend::DONE, array() )[ $key( 7430 ) ] ), 'M2: next pass in production → sent and accepted, then marked as resent.' );

// M3 — scheduled tasks removed on deactivation.
$GLOBALS['wp_test_cron'] = array(
	Capi_Health_Watch::HOOK  => $now,
	Capi_Refusal_Resend::HOOK => $now,
);
if ( class_exists( \Famma\Core\Capi_Meta_Quality::class ) ) {
	$GLOBALS['wp_test_cron'][ \Famma\Core\Capi_Meta_Quality::CRON ] = $now;
}
as_schedule_single_action( $now + 60, Capi_Refusal_Resend::HOOK, array(), 'famma-capi' );
Capi_Health_Watch::unschedule();
l5_check( array() === $GLOBALS['wp_test_cron'] && array() === l5_as_find( Capi_Refusal_Resend::HOOK, null, '', array( 'pending' ) ), 'M3: hourly watch, Meta quality cron and pending resend pass removed.' );
$source = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/famma-core.php' );
l5_check( str_contains( $source, "register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\\\on_deactivation' )" ) && str_contains( $source, 'Capi_Health_Watch::unschedule();' ), 'M3: famma-core.php calls it from its deactivation hook.' );
Capi_Health_Watch::instance();
l5_check( isset( $GLOBALS['wp_test_hooks']['init'] ) && in_array( array( Capi_Health_Watch::instance(), 'ensure_scheduled' ), array_column( $GLOBALS['wp_test_hooks']['init'], 0 ), true ), 'M3: the hourly watch is checked on init, not only in the admin.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
