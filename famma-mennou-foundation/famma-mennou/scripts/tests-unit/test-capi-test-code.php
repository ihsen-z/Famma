<?php
/**
 * Lot 5, A12 (D7) — Meta test event code: format TEST followed by digits;
 * invalid → refused in the admin with a message; test mode with a missing or
 * invalid code → send blocked and reported, never silently sent as real.
 * Run with: php scripts/tests-unit/test-capi-test-code.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';
Famma_Unit_Harness::load_include( 'measurement-settings.php' );

use Famma\Core\Capi;
use Famma\Core\Capi_Health;
use Famma\Core\Config;
use Famma\Core\Measurement_Settings;

foreach ( array( 'TEST12345', 'TEST1' ) as $valid ) {
	l5_check( Config::valid_test_event_code( $valid ), 'Valid test code: ' . $valid );
}
foreach ( array( '', 'TEST', 'TEST_ISOLATED', 'TESTQAL2', 'test12345', 'TEST 123', 'TEST123x', "TEST123\n" ) as $invalid ) {
	l5_check( ! Config::valid_test_event_code( $invalid ), 'Invalid test code refused: ' . json_encode( $invalid ) );
}

// Admin field: refused with a message, previous value kept.
l5_reset( array( 'famma_meta_test_event_code' => 'TEST27940' ) );
$GLOBALS['wp_test_settings_errors'] = array();
l5_check( 'TEST27940' === Measurement_Settings::sanitize_test_code( 'TESTQAL2' ) && 1 === count( $GLOBALS['wp_test_settings_errors'] ), 'An invalid code typed in « Mesure » is refused with a message; the old value is kept.' );
l5_check( 'TEST11111' === Measurement_Settings::sanitize_test_code( ' TEST11111 ' ) && '' === Measurement_Settings::sanitize_test_code( '' ), 'A valid code, or an empty field, is accepted.' );

// Test mode on with a valid code: the event carries test_event_code and the line says so.
l5_reset(
	array(
		'famma_meta_capi_test_mode'  => true,
		'famma_meta_test_event_code' => 'TEST27940',
	)
);
$wc_order = new WC_Order( 5700 );
Capi::instance()->fire_purchase_on_status( 5700 );
$body  = json_decode( (string) $GLOBALS['wp_test_http'][0]['args']['body'], true );
$lines = l5_capi_lines();
l5_check( 'TEST27940' === $body['test_event_code'] && str_contains( $lines[0], 'test=oui' ), 'Test mode with a valid code: test_event_code sent, journal line « test=oui ».' );

// Test mode on with an invalid or missing code: blocked and reported.
foreach ( array( 'TESTQAL2', '' ) as $code ) {
	l5_reset(
		array(
			'famma_meta_capi_test_mode'  => true,
			'famma_meta_test_event_code' => $code,
		)
	);
	$wc_order = new WC_Order( 5701 );
	Capi::instance()->fire_purchase_on_status( 5701 );
	$lines = l5_capi_lines();
	l5_check( array() === $GLOBALS['wp_test_http'] && 1 === count( $lines ) && str_contains( $lines[0], 'state=test_code_invalid' ) && ! $wc_order->get_meta( '_famma_purchase_sent' ), 'Test mode with code ' . json_encode( $code ) . ': no request, one test_code_invalid line, event kept for later.' );
	$state = Capi_Health::evaluate();
	l5_check( in_array( 'test_code_invalid', array_column( $state['raisons'], 'code' ), true ), 'Test mode without a valid code is reported in the health state.' );
}

// Test mode off: the configured code is never attached to a real conversion.
l5_reset( array( 'famma_meta_test_event_code' => 'TEST27940' ) );
$wc_order = new WC_Order( 5702 );
Capi::instance()->fire_purchase_on_status( 5702 );
$body = json_decode( (string) $GLOBALS['wp_test_http'][0]['args']['body'], true );
l5_check( ! isset( $body['test_event_code'] ) && (bool) $wc_order->get_meta( '_famma_purchase_sent' ), 'Test mode off: real conversion, no test_event_code.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
