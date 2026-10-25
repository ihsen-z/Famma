<?php
/**
 * Lot 5, A1 (S2 + R1) — Graph API version: constant > option > default, admin
 * validation, version schedule, one version for the real send and the test button.
 * Run with: php scripts/tests-unit/test-capi-graph-version.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';
Famma_Unit_Harness::load_include( 'measurement-settings.php' );
Famma_Unit_Harness::load_include( 'tracking-settings.php' );

use Famma\Core\Capi;
use Famma\Core\Capi_Graph_Version;
use Famma\Core\Config;
use Famma\Core\Measurement_Settings;

l5_reset();
$cfg = Config::instance();

// G1 — default, then option, then constant.
l5_check( 'v26.0' === $cfg->meta_graph_version() && 'default' === $cfg->meta_graph_version_source(), 'G1: without constant nor option, the default v26.0 is used.' );
$GLOBALS['wp_test_options']['famma_meta_graph_version'] = 'v25.0';
l5_check( 'v25.0' === $cfg->meta_graph_version() && 'option' === $cfg->meta_graph_version_source(), 'G1: the « Mesure » option overrides the default.' );
foreach ( array( '21', 'v21', 'abc', 'v21.0.1', ' v2' ) as $invalid ) {
	$GLOBALS['wp_test_options']['famma_meta_graph_version'] = $invalid;
	l5_check( 'v26.0' === $cfg->meta_graph_version(), 'G1: an invalid stored option is ignored: ' . $invalid );
}
$GLOBALS['wp_test_options']['famma_meta_graph_version'] = 'v25.0';
add_filter(
	'famma_capi_graph_version',
	static function ( $version ) {
		return $GLOBALS['famma_test_filter_version'] ?? $version;
	}
);
$GLOBALS['famma_test_filter_version'] = 'v24.0';
l5_check( 'v24.0' === $cfg->meta_graph_version(), 'The famma_capi_graph_version filter is kept.' );
$GLOBALS['famma_test_filter_version'] = 'not-a-version';
l5_check( 'v25.0' === $cfg->meta_graph_version(), 'A filtered value out of format is ignored.' );
unset( $GLOBALS['famma_test_filter_version'] );

// G2 — admin field validation.
$GLOBALS['wp_test_settings_errors'] = array();
foreach ( array( '21', 'v21', 'abc' ) as $invalid ) {
	l5_check( 'v25.0' === Measurement_Settings::sanitize_graph_version( $invalid ), 'G2: refused value keeps the previous one: ' . $invalid );
}
l5_check( 3 === count( $GLOBALS['wp_test_settings_errors'] ) && 'famma_meta_graph_version' === $GLOBALS['wp_test_settings_errors'][0][0], 'G2: each refusal adds an admin message.' );
l5_check( 'v23.0' === Measurement_Settings::sanitize_graph_version( ' v23.0 ' ) && '' === Measurement_Settings::sanitize_graph_version( '' ), 'G2: vNN.N or empty (back to default) is accepted.' );

// G3 — the real send and the test button aim at the same graph.facebook.com/<version>/ URL.
$GLOBALS['wp_test_options']['famma_meta_graph_version'] = 'v25.0';
$GLOBALS['wp_test_http']                                = array();
$wc_order = new WC_Order( 5101 );
Capi::instance()->send_purchase( $wc_order, 'famma_purchase_5101' );
// phpcs:disable WordPress.Security.NonceVerification.Missing -- Test fixture of the posted admin form; the nonce check is doubled.
$_POST['famma_capi_test_code']  = 'TEST12345';
$_POST['famma_capi_test_event'] = 'Purchase';
$_POST['famma_capi_test_value'] = '1';
// phpcs:enable WordPress.Security.NonceVerification.Missing
try {
	Famma_Unit_Harness::bare( '\Famma\Core\Tracking_Settings' )->send_test_event();
} catch ( Famma_Test_Redirect $redirect ) {
	unset( $redirect );
}
$sent_urls = array_column( $GLOBALS['wp_test_http'], 'url' );
l5_check( 2 === count( $sent_urls ) && 'https://graph.facebook.com/v25.0/000000000000001/events' === $sent_urls[0] && $sent_urls[0] === $sent_urls[1], 'G3: real send and test button use the same version and URL.' );

// R1 / V1 — schedule: critical net, single J-60 warning, nothing for an unknown end date.
$at = static fn( string $date ): int => (int) strtotime( $date . ' UTC' );
l5_check( 'critical' === Capi_Graph_Version::status( 'v21.0', $at( '2026-12-02 00:00:00' ) )['level'], 'V1: v21.0 after 2026-12-01 shows the critical banner.' );
l5_check( 'critical' === Capi_Graph_Version::status( 'v19.0', $at( '2026-12-02 00:00:00' ) )['level'], 'V1: any version below v21.0 after 2026-12-01 is critical.' );
l5_check( 'warning' === Capi_Graph_Version::status( 'v22.0', $at( '2027-03-22 12:00:00' ) )['level'], 'V1: 59 days before the known end date, a single warning.' );
l5_check( 'ok' === Capi_Graph_Version::status( 'v22.0', $at( '2027-03-01 00:00:00' ) )['level'], 'V1: more than 60 days before the end date, nothing.' );
l5_check( 'critical' === Capi_Graph_Version::status( 'v23.0', $at( '2027-10-09 00:00:00' ) )['level'], 'V1: past its end date, a version is critical.' );
l5_check( 'ok' === Capi_Graph_Version::status( 'v26.0', $at( '2029-01-01 00:00:00' ) )['level'] && -1 === Capi_Graph_Version::status( 'v26.0', time() )['days'], 'V1: unknown end date (v26.0): nothing.' );
l5_check( '2027-01-21' === Config::META_GRAPH_VERSION_END_DATES['v21.0'] && '2028-07-29' === Config::META_GRAPH_VERSION_END_DATES['v25.0'] && ! isset( Config::META_GRAPH_VERSION_END_DATES['v26.0'] ), 'R1: the known end dates are those of the plan.' );

// R13 — no vNN.N literal outside the Config class.
$plugin_dir = ABSPATH . 'wp-content/plugins/famma-core/';
$source     = (string) file_get_contents( $plugin_dir . 'famma-core.php' );
$start      = strpos( $source, 'final class Config {' );
$end        = strpos( $source, 'final class Order_Statuses {' );
$outside    = substr( $source, 0, $start ) . substr( $source, $end );
foreach ( glob( $plugin_dir . 'includes/*.php' ) as $include ) {
	$outside .= (string) file_get_contents( $include );
}
l5_check( 0 === preg_match_all( '/\bv\d{1,2}\.\d\b/', $outside ), 'R13: no hard-coded Graph version outside Config.' );

// Constant last: it cannot be undefined afterwards.
define( 'FAMMA_META_GRAPH_VERSION', 'v24.0' );
l5_check( 'v24.0' === $cfg->meta_graph_version() && 'constant' === $cfg->meta_graph_version_source(), 'G1: the server constant overrides the option.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
