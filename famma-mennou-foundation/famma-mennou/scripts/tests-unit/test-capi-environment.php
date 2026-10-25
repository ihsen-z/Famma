<?php
/**
 * Lot 5, A7 (D4) — environment guard: outside production, no real send
 * (events, test button, probe), one « blocked: environment » line, orange
 * state; explicit FAMMA_META_ALLOW_NON_PRODUCTION override.
 * Run with: php scripts/tests-unit/test-capi-environment.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';
Famma_Unit_Harness::load_include( 'measurement-settings.php' );
Famma_Unit_Harness::load_include( 'tracking-settings.php' );

use Famma\Core\Capi;
use Famma\Core\Capi_Health;
use Famma\Core\Capi_Probe;
use Famma\Core\Config;

$capi = Capi::instance();

foreach ( array( 'local', 'development', 'staging' ) as $environment ) {
	l5_reset();
	$GLOBALS['wp_test_environment'] = $environment;
	$wc_order                       = new WC_Order( 5500 );
	$capi->fire_purchase_on_status( 5500 );
	$lines = l5_capi_lines();
	l5_check( array() === $GLOBALS['wp_test_http'] && 1 === count( $lines ) && str_contains( $lines[0], 'state=blocked_environment reason=' . $environment ), 'I4/D4 ' . $environment . ': no request, one « blocked: environment » line.' );
	l5_check( ! $wc_order->get_meta( '_famma_purchase_sent' ) && ! isset( $GLOBALS['famma_test_registry_acquire'] ), 'D4 ' . $environment . ': nothing consumed, the registry is not even claimed.' );
	$state = Capi_Health::evaluate();
	l5_check( 'orange' === $state['statut'] && 'environment' === $state['raisons'][0]['code'], 'D4 ' . $environment . ': health orange « envoi bloqué : environnement ».' );
	unset( $GLOBALS['famma_test_registry_acquire'] );
}

// The test button and the probe never send outside production either.
l5_reset();
$GLOBALS['wp_test_environment'] = 'local';
// phpcs:disable WordPress.Security.NonceVerification.Missing -- Test fixture of the posted admin form; the nonce check is doubled.
$_POST['famma_capi_test_code']  = 'TEST12345';
$_POST['famma_capi_test_event'] = 'OrderPlaced';
$_POST['famma_capi_test_value'] = '1';
// phpcs:enable WordPress.Security.NonceVerification.Missing
$location = '';
try {
	Famma_Unit_Harness::bare( '\Famma\Core\Tracking_Settings' )->send_test_event();
} catch ( Famma_Test_Redirect $redirect ) {
	$location = $redirect->getMessage();
}
l5_check( array() === $GLOBALS['wp_test_http'] && str_contains( $location, 'famma_capi_result=environment' ), 'The « Test CAPI » button sends nothing outside production and says why.' );
l5_check( 'blocked_environment' === Capi_Probe::run()['class'] && array() === $GLOBALS['wp_test_http'], 'The probe sends nothing outside production.' );

// Production: the send goes out.
l5_reset();
$wc_order = new WC_Order( 5501 );
$capi->fire_purchase_on_status( 5501 );
l5_check( 1 === count( $GLOBALS['wp_test_http'] ) && (bool) $wc_order->get_meta( '_famma_purchase_sent' ), 'D4: in production the Purchase is sent.' );
$GLOBALS['wp_test_environment'] = 'nonsense';
l5_check( false === Config::instance()->meta_sending_allowed(), 'An environment other than « production » is blocked.' );

// Explicit override by constant (defined last: it cannot be undefined).
l5_reset();
$GLOBALS['wp_test_environment'] = 'staging';
define( 'FAMMA_META_ALLOW_NON_PRODUCTION', true );
$wc_order = new WC_Order( 5502 );
$capi->fire_purchase_on_status( 5502 );
l5_check( 1 === count( $GLOBALS['wp_test_http'] ) && (bool) $wc_order->get_meta( '_famma_purchase_sent' ), 'FAMMA_META_ALLOW_NON_PRODUCTION true lets a non-production site send.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
