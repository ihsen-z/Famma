<?php
/**
 * Phase B: daily read-only Meta quality (Dataset Quality API): OK answer parsed,
 * permission error becomes "Lecture impossible" without a red alert, the token
 * appears nowhere (URL, option, screen), at most one call per 24 h.
 * Run with: php scripts/tests-unit/test-capi-meta-quality.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( string $format, int $timestamp ): string {
		return gmdate( $format, $timestamp );
	}
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( float $number, int $decimals = 0 ): string {
		return number_format( $number, $decimals, ',', ' ' );
	}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text ): void {
		echo esc_html( $text );
	}
}
// phpcs:enable

Famma_Unit_Harness::load_include( 'capi-meta-quality-parser.php' );
Famma_Unit_Harness::load_include( 'capi-meta-quality.php' );
Famma_Unit_Harness::load_include( 'capi-meta-quality-view.php' );

use Famma\Core\Capi_Health;
use Famma\Core\Capi_Meta_Quality;
use Famma\Core\Capi_Meta_Quality_View;

$base  = time();
$token = 'QA-QUALITY-FAKE-TOKEN-NOT-REAL';
$reset = static function () use ( $token ): void {
	$GLOBALS['wp_test_options']       = array(
		'famma_meta_pixel_id'     => '1234567890',
		'famma_meta_access_token' => $token,
	);
	$GLOBALS['wp_test_http']          = array();
	$GLOBALS['wp_test_http_response'] = null;
	unset( $GLOBALS['wp_test_http_response'] );
};
$ok_body = (string) json_encode(
	array(
		'web' => array(
			array(
				'event_name'          => 'OrderPlaced',
				'event_match_quality' => array( 'composite_score' => 5.4 ),
				'data_freshness'      => array( 'upload_frequency' => 'real_time' ),
				'event_coverage'      => array( 'percentage' => 88.25 ),
			),
			array(
				'event_name'          => 'PageView',
				'event_match_quality' => array( 'composite_score' => 7 ),
			),
		),
	)
);
$screen  = static function (): string {
	ob_start();
	Capi_Meta_Quality_View::render();
	return (string) ob_get_clean();
};

// 1. OK answer parsed, token in the header only.
$reset();
$GLOBALS['wp_test_http_response'] = l5_response( 200, $ok_body );
Capi_Meta_Quality::refresh( $base );
$data    = Capi_Meta_Quality::get();
$request = $GLOBALS['wp_test_http'][0] ?? array();
l5_check( 'ok' === $data['status'] && 2 === count( $data['events'] ), 'OK answer: two events stored' );
l5_check( 5.4 === Capi_Meta_Quality::score( 'OrderPlaced' ) && 7.0 === Capi_Meta_Quality::score( 'PageView' ) && null === Capi_Meta_Quality::score( 'Purchase' ), 'score() exposes the match note, null when unknown' );
l5_check( 'real_time' === $data['events'][0]['frequency'] && 88.3 === $data['events'][0]['coverage'], 'freshness and coverage kept' );
l5_check( false !== strpos( $request['url'], '/dataset_quality?dataset_id=1234567890' ) && false === strpos( $request['url'], $token ) && false === strpos( $request['url'], 'access_token' ), 'URL has the dataset and no token' );
l5_check( 'Bearer ' . $token === ( $request['args']['headers']['Authorization'] ?? '' ), 'token sent in the Authorization header' );
l5_check( $base === $data['read_at'], 'read date stored' );
$html = $screen();
l5_check( false !== strpos( $html, 'OrderPlaced' ) && false !== strpos( $html, '5,4' ) && false !== strpos( $html, 'en temps réel' ), 'screen shows event, note and freshness' );
l5_check( false === strpos( $html, $token ), 'token absent from the screen' );
l5_check( false === strpos( serialize( get_option( 'famma_meta_quality' ) ), $token ), 'token absent from the stored option' );

// 2. At most one call per 24 h (also after a success).
Capi_Meta_Quality::refresh( $base + 86399 );
l5_check( 1 === count( $GLOBALS['wp_test_http'] ), 'no second call before 24 h' );
Capi_Meta_Quality::refresh( $base + 86400 );
l5_check( 2 === count( $GLOBALS['wp_test_http'] ), 'a call after 24 h' );

// 3. Permission error: Lecture impossible, no red alert, last notes kept.
$reset();
$GLOBALS['wp_test_http_response'] = l5_response( 200, $ok_body );
Capi_Meta_Quality::refresh( $base - 100000 );
$GLOBALS['wp_test_http_response'] = l5_response( 403, (string) json_encode( array( 'error' => array( 'code' => 200, 'message' => 'Permissions error ' . $token ) ) ) );
Capi_Meta_Quality::refresh( $base );
$data = Capi_Meta_Quality::get();
$html = $screen();
l5_check( 'unavailable' === $data['status'] && 'permission' === $data['reason'], 'error 200 classed as permission' );
l5_check( false !== strpos( $html, 'Lecture impossible' ), 'screen says Lecture impossible' );
l5_check( false === strpos( $html . serialize( get_option( 'famma_meta_quality' ) ), $token ), 'Meta message (with token) never stored nor shown' );
l5_check( 2 === count( $data['events'] ) && $base - 100000 === $data['read_at'], 'last known notes and read date kept' );
$state = Capi_Health::state();
l5_check( 'rouge' !== $state['statut'], 'no red alert from a quality read failure' );

// 4. Other failures never raise anything and never leak.
foreach ( array(
	'token'      => l5_response( 400, '{"error":{"code":190}}' ),
	'limit'      => l5_response( 400, '{"error":{"code":17}}' ),
	'unexpected' => l5_response( 200, '{"foo":1}' ),
	'meta_error' => l5_response( 500, 'oops' ),
	'timeout'    => new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ),
	'network'    => new WP_Error( 'http_request_failed', 'Could not resolve host' ),
) as $reason => $response ) {
	$reset();
	$GLOBALS['wp_test_http_response'] = $response;
	Capi_Meta_Quality::refresh( $base );
	l5_check( $reason === Capi_Meta_Quality::get()['reason'], 'failure classed ' . $reason );
}

// 5. Not configured: no call.
$reset();
$GLOBALS['wp_test_options']['famma_meta_access_token'] = '';
Capi_Meta_Quality::refresh( $base );
l5_check( array() === $GLOBALS['wp_test_http'] && 'not_configured' === Capi_Meta_Quality::get()['reason'], 'no token: no call, short reason' );

// 6. Filter exposes the result to the alerts.
$reset();
$GLOBALS['wp_test_hooks']['famma_meta_quality'][] = array(
	static function ( array $d ): array {
		$d['status'] = 'ok';
		$d['events'] = array(
			array(
				'event' => 'OrderPlaced',
				'score' => 4.0,
			),
		);
		return $d;
	},
	1,
);
l5_check( 4.0 === Capi_Meta_Quality::score( 'OrderPlaced' ), 'filter famma_meta_quality overrides the result' );

echo 'RESULT ' . $GLOBALS['l5_checks'] . '/' . $GLOBALS['l5_checks'] . PHP_EOL;
