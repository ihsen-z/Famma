<?php
/**
 * Phase B des réformes du pixel, lot 4 (D3, D12) — surveillance de l'envoi à Meta :
 * OrderPlaced manquant à 30 min (commandes nommées), renvoi automatique des refus
 * une fois le problème réglé, e-mail d'alerte quotidien plafonné et adresse réglable,
 * libellé des versions d'API sans date de fin.
 * Run with: php scripts/tests-unit/test-capi-surveillance.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Doubles are documented by the API they replace.
require __DIR__ . '/bootstrap-capi.php';
// Classes added by the other teams of phase B, loaded when present.
foreach ( array( 'capi-external-id.php', 'capi-meta-quality-parser.php', 'capi-meta-quality.php' ) as $famma_optional ) {
	if ( is_file( ABSPATH . 'wp-content/plugins/famma-core/includes/' . $famma_optional ) ) {
		Famma_Unit_Harness::load_include( $famma_optional );
	}
}
Famma_Unit_Harness::load_include( 'capi-health-watch.php' );

use Famma\Core\Capi_Graph_Version;
use Famma\Core\Capi_Health;
use Famma\Core\Capi_Health_Alert;
use Famma\Core\Capi_Health_Watch;
use Famma\Core\Capi_Orderplaced_Watch;
use Famma\Core\Capi_Refusal_Resend;
use Famma\Core\Config;

// WordPress doubles used only by this suite.
function sanitize_email( string $email ): string {
	return (string) preg_replace( '/[^a-zA-Z0-9.@_+\-]/', '', $email );
}
function is_email( string $email ) {
	return 1 === preg_match( '/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $email ) ? $email : false;
}
function wp_next_scheduled( string $hook, array $args = array() ) {
	return $GLOBALS['wp_test_cron'][ $hook ] ?? false;
}
function wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = array() ): bool {
	$GLOBALS['wp_test_cron'][ $hook ] = $timestamp;
	return true;
}

$now   = time();
$codes = static fn( array $state ): array => array_column( $state['raisons'], 'code' );
$find  = static function ( array $state, string $code ): array {
	foreach ( $state['raisons'] as $item ) {
		if ( $code === $item['code'] ) {
			return $item;
		}
	}
	return array();
};
$mails = static fn(): int => count( $GLOBALS['wp_test_mail'] ?? array() );
$order = static function ( int $id, int $age, array $meta = array(), string $status = 'processing', string $via = 'checkout' ) use ( $now ): WC_Order {
	$wc_order              = new WC_Order( $id );
	$wc_order->created_at  = ( new DateTimeImmutable() )->setTimestamp( $now - $age );
	$wc_order->meta        = $meta + array( '_famma_orderplaced_checkout_accepted' => 1 ); // Queued by Capi (I2).
	$wc_order->status      = $status;
	$wc_order->created_via = $via;
	$wc_order->phone       = '22123456';
	return $wc_order;
};

// 1. OrderPlaced missing 30 minutes after the order, orders named.
l5_reset();
$GLOBALS['wc_test_order_query'] = array( $order( 7001, 31 * 60 ) );
$first                          = Capi_Health::evaluate( $now );
l5_check( 'vert' === $first['statut'] && $now === (int) $GLOBALS['wp_test_options'][ Capi_Orderplaced_Watch::SINCE ], 'First run: the start date is stored, earlier orders never alert.' );

l5_reset( array( Capi_Orderplaced_Watch::SINCE => $now - 4 * 3600 ) );
$GLOBALS['famma_test_registry_orders'] = array(
	7007 => array(
		'OrderPlaced' => array(
			'state'      => 'accepted',
			'event_time' => $now - 3600,
			'updated_at' => gmdate( 'Y-m-d H:i:s', $now - 3600 ),
		),
	),
	7008 => array(
		'OrderPlaced' => array(
			'state'      => 'uncertain',
			'event_time' => $now - 3 * 3600,
			'updated_at' => gmdate( 'Y-m-d H:i:s', $now - 2 * 86400 ),
		),
	),
);
$GLOBALS['wc_test_order_query']        = array(
	$order( 7001, 31 * 60 ),
	$order( 7002, 20 * 60 ),
	$order( 7003, 40 * 60, array( '_famma_test_order' => 1 ) ),
	$order( 7004, 5 * 3600 ),
	$order( 7005, 50 * 60, array(), 'processing', 'admin' ),
	$order( 7006, 50 * 60, array( '_famma_orderplaced_sent' => '2026-11-25 10:00:00' ) ),
	$order( 7007, 60 * 60 ),
	$order( 7008, 3 * 3600 ),
	$order( 7009, 45 * 60, array(), 'cancelled' ),
);
$state = Capi_Health::evaluate( $now );
$miss  = $find( $state, 'orderplaced_missing' );
l5_check( 'rouge' === $state['statut'] && 'rouge' === ( $miss['niveau'] ?? '' ) && '7001' === ( $miss['detail'] ?? '' ), 'OrderPlaced not accepted 31 min after the order → red, order n° 7001 named; 20 min old, test order, before the start date, back-office order, already sent, accepted in the registry, cancelled → not reported.' );
l5_check( array( 'code' => 'orderplaced_lost', 'niveau' => 'orange', 'detail' => '7008' ) === $find( $state, 'orderplaced_lost' ), 'Uncertain for 2 days, no resend possible any more → orange, n° 7008 named.' );
l5_check( str_contains( \Famma\Core\Capi_Health_Rules::label( $miss ), 'n° 7001' ), 'The label names the order.' );
$body = (string) ( $GLOBALS['wp_test_mail'][0][2] ?? '' );
l5_check( 1 === $mails() && str_contains( $body, '7001' ) && ! str_contains( $body, '22123456' ) && ! str_contains( $body, 'QA-L5-FAKE-TOKEN' ), 'The e-mail names the order number, never the phone nor the token.' );

add_filter( 'famma_capi_orderplaced_expected', static fn( bool $expected, WC_Order $wc_order ): bool => $expected || 7005 === $wc_order->get_id(), 10, 2 );
l5_check( '7001, 7005' === Capi_Orderplaced_Watch::reasons( $now )[0]['detail'], 'Filter famma_capi_orderplaced_expected: an eligible back-office order (« Panier récupéré », lot 7) is watched too.' );
unset( $GLOBALS['wp_test_hooks']['famma_capi_orderplaced_expected'] );
$GLOBALS['wp_test_options']['famma_meta_orderplaced_enabled'] = false;
l5_check( array() === Capi_Orderplaced_Watch::sort( $now )['missing'], 'OrderPlaced switched off: nothing expected, nothing reported.' );
$GLOBALS['wp_test_options']['famma_meta_orderplaced_enabled'] = true;
$many                           = array();
for ( $n = 0; $n < 12; $n++ ) {
	$many[] = $order( 7200 + $n, 40 * 60 );
}
$GLOBALS['wc_test_order_query'] = $many;
l5_check( str_ends_with( Capi_Orderplaced_Watch::reasons( $now )[0]['detail'], '7209 (+2)' ), 'At most 10 numbers, then the count left.' );
if ( class_exists( \Famma\Core\Capi_Meta_Quality::class ) ) {
	$GLOBALS['wc_test_order_query']                   = array();
	$GLOBALS['wp_test_options']['famma_meta_quality'] = array(
		'status' => 'ok',
		'events' => array(
			array(
				'event' => 'OrderPlaced',
				'score' => 4.2,
			),
		),
	);
	$quality = Capi_Orderplaced_Watch::reasons( $now );
	l5_check( array( 'orderplaced_quality' ) === array_column( $quality, 'code' ) && 'orange' === $quality[0]['niveau'], 'Meta quality score of OrderPlaced below 6 → orange warning only, never red.' );
}

// 2. Automatic resend of refusals once the problem is fixed.
$key = static fn( string $name, int $id ): string => hash( 'sha256', '000000000000001|' . $name . '|famma_' . strtolower( $name ) . '_' . $id );
$row = static function ( string $name, int $id, int $age, string $class = 'access' ) use ( $now, $key ): array {
	return array(
		'event_key'     => $key( $name, $id ),
		'order_id'      => $id,
		'event_name'    => $name,
		'event_id'      => 'famma_' . strtolower( $name ) . '_' . $id,
		'event_time'    => $now - $age,
		'state'         => 'rejected',
		'attempt_count' => 1,
		'attempts'      => wp_json_encode( array( array( 'state' => 'rejected', 'class' => $class ) ) ),
	);
};
$seed = static function ( array $rows ): void {
	$GLOBALS['famma_test_registry_stateful'] = true;
	$GLOBALS['famma_test_registry_rejected'] = $rows;
	foreach ( $rows as $item ) {
		$GLOBALS['famma_test_registry'][ $item['event_key'] ] = array(
			'state'         => 'rejected',
			'attempt_count' => 1,
			'event_time'    => $item['event_time'],
			'order_id'      => $item['order_id'],
		);
	}
};
$posts = static fn(): int => count( $GLOBALS['wp_test_http'] ?? array() );
Capi_Refusal_Resend::instance(); // Registers the pass callback, as famma-core.php does at boot.

l5_reset();
$placed   = $order( 7101, 86400, array( '_famma_orderplaced_checkout_accepted' => 1 ) );
$bought   = $order( 7102, 2 * 86400, array( '_famma_purchase_event_time' => $now - 86400 ), 'delivered' );
$returned = $order( 7103, 2 * 86400, array( '_famma_purchase_event_time' => $now - 86400 ), 'returned' );
$test     = $order( 7105, 86400, array( '_famma_orderplaced_checkout_accepted' => 1, '_famma_test_order' => 1 ) );
$local    = $order( 7106, 86400, array( '_famma_orderplaced_checkout_accepted' => 1 ) );
$old      = $order( 7104, 7 * 86400, array( '_famma_orderplaced_checkout_accepted' => 1 ) );
$seed( array( $row( 'OrderPlaced', 7101, 86400 ), $row( 'Purchase', 7102, 86400, 'contract' ), $row( 'Purchase', 7103, 86400 ), $row( 'OrderPlaced', 7104, 7 * 86400 ), $row( 'OrderPlaced', 7105, 86400 ), $row( 'OrderPlaced', 7106, 86400, '' ) ) );
$GLOBALS['wp_test_http_response'] = l5_response( 400, '{"error":{"code":190,"message":"Invalid OAuth access token"}}' );
Capi_Health::record_outcome( 'access', array( 'code' => 190 ) );
l5_check( Capi_Refusal_Resend::pending() && array() === l5_as_find( Capi_Refusal_Resend::HOOK, null, '', array() ), 'Refusal (token): flag raised, nothing resent while the problem lasts.' );
$GLOBALS['wp_test_http_response'] = l5_response( 200, '{"events_received":1,"messages":[],"fbtrace_id":"QAL4"}', array( 'facebook-api-version' => 'v26.0' ) );
Capi_Health::record_outcome( 'accepted', array() );
l5_check( ! Capi_Refusal_Resend::pending() && 1 === count( l5_as_find( Capi_Refusal_Resend::HOOK, array(), 'famma-capi', array( 'pending' ) ) ), 'First accepted send after the refusal: one resend pass queued (Action Scheduler, famma-capi).' );
l5_as_run_next();
$urls = array_column( $GLOBALS['wp_test_http'], 'url' );
l5_check( 2 === $posts() && $placed->get_meta( '_famma_orderplaced_sent' ) && $bought->get_meta( '_famma_purchase_sent' ), 'Resent once each, same event_id: OrderPlaced 7101 (token refusal) and Purchase 7102 (content refusal, still delivered); both accepted.' );
l5_check( ! $returned->get_meta( '_famma_purchase_sent' ) && ! $test->get_meta( '_famma_orderplaced_sent' ) && ! $local->get_meta( '_famma_orderplaced_sent' ) && ! $old->get_meta( '_famma_orderplaced_sent' ), 'Not resent: Purchase of an order no longer delivered, test order, local refusal (no Meta class), event older than 6 days.' );
l5_check( 1 === count( array_filter( l5_capi_lines(), static fn( string $line ): bool => str_contains( $line, 'auto_resend_skipped' ) && str_contains( $line, 'purchase:not_delivered' ) ) ), 'The journal says why the returned order was skipped.' );
l5_check( str_contains( (string) ( $urls[0] ?? '' ), 'graph.facebook.com' ) && ! str_contains( implode( ' ', l5_capi_lines() ), 'QA-L5-FAKE-TOKEN' ), 'Real transport, token absent from the journal.' );

// Same events refused again, problem fixed again: never a second automatic resend.
$GLOBALS['famma_test_registry'][ $key( 'OrderPlaced', 7101 ) ]['state'] = 'rejected';
$placed->delete_meta_data( '_famma_orderplaced_sent' );
Capi_Health::record_outcome( 'access', array( 'code' => 190 ) );
Capi_Health::record_outcome( 'accepted', array() );
l5_as_run_next();
l5_check( 2 === $posts() && ! $placed->get_meta( '_famma_orderplaced_sent' ), 'A refusal already resent automatically once waits for « Renvoyer »: no loop.' );

// Batch cap: 12 refusals → 10, then 2 on the next pass.
l5_reset();
$rows = array();
for ( $n = 0; $n < 12; $n++ ) {
	$order( 7300 + $n, 3600, array( '_famma_orderplaced_checkout_accepted' => 1 ) );
	$rows[] = $row( 'OrderPlaced', 7300 + $n, 3600 + $n );
}
$seed( $rows );
$result = Capi_Refusal_Resend::sweep( $now );
l5_check( 10 === $result['resent'] && $result['left'] && 10 === $posts() && 1 === count( l5_as_find( Capi_Refusal_Resend::HOOK, array(), 'famma-capi', array( 'pending' ) ) ), 'At most 10 resends per pass; the rest is queued for the next pass.' );
l5_as_run_next();
l5_check( 12 === $posts(), 'Next pass: the 2 remaining refusals.' );

// Token validated by « Vérifier maintenant » (probe /me).
l5_reset();
$GLOBALS['wp_test_options']['famma_capi_health_signals'] = array( 'access' => array( 'code' => 190 ) );
Capi_Health::record_probe( array( 'class' => 'accepted' ) );
l5_check( 1 === count( l5_as_find( Capi_Refusal_Resend::HOOK, array(), 'famma-capi', array( 'pending' ) ) ), 'Probe valid after a token refusal: resend pass queued.' );

// 3. Alert e-mail: address in « Mesure », daily cap while red, recovery e-mail.
l5_reset( array( Capi_Health_Alert::EMAIL => '' ) );
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = $now - 37 * 3600;
l5_check( 'rouge' === Capi_Health::evaluate( $now )['statut'] && 0 === $mails(), 'Empty address: red state shown in the admin, no e-mail.' );
$GLOBALS['wp_test_options'][ Capi_Health_Alert::EMAIL ] = 'chef@qa.invalid';
Capi_Health::evaluate( $now + 60 );
l5_check( 1 === $mails() && 'chef@qa.invalid' === $GLOBALS['wp_test_mail'][0][0], 'Address set: the e-mail goes to it (never the WordPress admin e-mail).' );
Capi_Health::evaluate( $now + 3600 );
Capi_Health::evaluate( $now + 23 * 3600 );
l5_check( 1 === $mails(), 'Still red within 24 h: no second e-mail.' );
Capi_Health::evaluate( $now + 86400 + 120 );
l5_check( 2 === $mails() && str_contains( $GLOBALS['wp_test_mail'][1][2], 'Rappel quotidien' ), 'Still red after 24 h: one daily reminder.' );
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = $now + 86400;
Capi_Health::evaluate( $now + 86400 + 300 );
Capi_Health::evaluate( $now + 86400 + 600 );
l5_check( 3 === $mails() && str_contains( $GLOBALS['wp_test_mail'][2][2], 'revenue au vert' ) && 'FAMMA : mesure Meta rétablie' === $GLOBALS['wp_test_mail'][2][1], 'Back to green: one recovery e-mail, once.' );
l5_reset( array( Capi_Health_Alert::EMAIL => 'pas-une-adresse' ) );
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = $now - 37 * 3600;
Capi_Health::evaluate( $now );
l5_check( 0 === $mails(), 'An invalid stored address sends nothing.' );

// Field « Adresse des alertes »: manage_woocommerce, sanitize_email, invalid kept out.
$GLOBALS['wp_test_options'][ Capi_Health_Alert::EMAIL ] = 'chef@qa.invalid';
$GLOBALS['wp_test_settings_errors']                     = array();
l5_check( 'equipe@qa.invalid' === Capi_Health_Watch::sanitize_email_option( ' equipe@qa.invalid ' ) && '' === Capi_Health_Watch::sanitize_email_option( '' ), 'A valid address is saved; an empty field switches e-mails off.' );
l5_check( 'chef@qa.invalid' === Capi_Health_Watch::sanitize_email_option( 'pas une adresse' ) && 1 === count( $GLOBALS['wp_test_settings_errors'] ), 'An invalid address is refused with a message, the previous one kept.' );
$GLOBALS['wp_test_caps'] = array( 'manage_woocommerce' => false );
l5_check( 'chef@qa.invalid' === Capi_Health_Watch::sanitize_email_option( 'autre@qa.invalid' ), 'Without manage_woocommerce the address cannot be changed.' );
$GLOBALS['wp_test_caps'] = array();

// Hourly WP-Cron watch.
$GLOBALS['wp_test_cron'] = array();
$watch                   = Capi_Health_Watch::instance();
$watch->ensure_scheduled();
$watch->ensure_scheduled();
l5_check( isset( $GLOBALS['wp_test_cron'][ Capi_Health_Watch::HOOK ] ) && isset( $GLOBALS['wp_test_hooks'][ Capi_Health_Watch::HOOK ] ), 'Hourly WP-Cron task scheduled once, its callback registered.' );
l5_reset();
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = $now - 37 * 3600;
do_action( Capi_Health_Watch::HOOK );
l5_check( 'rouge' === $GLOBALS['wp_test_options']['famma_capi_health']['statut'] && 1 === $mails(), 'The cron task recalculates the state and sends the e-mail without any admin visit.' );

// 4. Graph API versions (Meta calendar read on 24/11/2026).
l5_check( '2027-01-21' === Capi_Graph_Version::end_label( 'v21.0' ) && '2028-02-18' === Capi_Graph_Version::end_label( 'v24.0' ) && '2028-07-29' === Capi_Graph_Version::end_label( 'v25.0' ), 'End dates: v21 21/01/2027, v24 18/02/2028, v25 29/07/2028.' );
l5_check( 'date pas encore fixée par Meta' === Capi_Graph_Version::end_label( 'v26.0' ) && 'date pas encore fixée par Meta' === Capi_Graph_Version::end_label( 'v27.0' ) && 'inconnue' === Capi_Graph_Version::end_label( 'v19.0' ), 'v26 and v27: « date pas encore fixée par Meta », not « inconnue ».' );
l5_check( 'ok' === Capi_Graph_Version::status( Config::META_GRAPH_VERSION_DEFAULT, $now )['level'] && 'ok' === Capi_Graph_Version::status( 'v27.0', $now )['level'], 'No false alert for the default v26.0 nor v27.0.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
