<?php
/**
 * Lot 5, A2 (R5) — single transport to graph.facebook.com and response classes,
 * journal fields (version requested and served, class, code, subcode, messages)
 * without the token; probe without event (R6).
 * Run with: php scripts/tests-unit/test-capi-transport.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi;
use Famma\Core\Capi_Transport;

l5_reset();
$token = 'QA-L5-FAKE-TOKEN-NOT-REAL';
$json  = static fn( array $error ): string => (string) json_encode( array( 'error' => $error ) );

// V2 — classification, in the order of the plan.
$cases = array(
	array( '200 with messages[]', l5_response( 200, '{"events_received":1,"messages":["field ignored"]}', array( 'facebook-api-version' => 'v26.0' ) ), 'accepted_warning', 'messages' ),
	array(
		'190 expired token',
		l5_response(
			400,
			$json(
				array(
					'code'          => 190,
					'error_subcode' => 463,
					'message'       => 'expired',
				)
			)
		),
		'access',
		'code_190',
	),
	array( '10 permission', l5_response( 403, $json( array( 'code' => 10 ) ) ), 'access', 'code_10' ),
	array( '294 permission range', l5_response( 403, $json( array( 'code' => 294 ) ) ), 'access', 'code_294' ),
	array( '368 temporarily blocked', l5_response( 400, $json( array( 'code' => 368 ) ) ), 'access', 'code_368' ),
	array( '100 invalid parameter', l5_response( 400, $json( array( 'code' => 100 ) ) ), 'contract', 'code_100' ),
	array( '500 server error', l5_response( 500, 'Internal error' ), 'transient', 'http_500' ),
	array( 'code 2 service', l5_response( 400, $json( array( 'code' => 2 ) ) ), 'transient', 'code_2' ),
	array(
		'is_transient',
		l5_response(
			400,
			$json(
				array(
					'code'         => 9999,
					'is_transient' => true,
				)
			)
		),
		'transient',
		'code_9999',
	),
	array( '429 throttled', l5_response( 429, '{}' ), 'transient', 'http_429' ),
	array( 'timeout', new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 8000 milliseconds' ), 'transient', 'timeout' ),
	array( 'network', new WP_Error( 'http_request_failed', 'cURL error 7: connection refused' ), 'transient', 'network' ),
	array( '200 without acknowledgement', l5_response( 200, '{}' ), 'transient', 'ack_missing' ),
	array( '404 unknown body', l5_response( 404, 'not json' ), 'contract', 'http_404' ),
	array( 'accepted, header absent', l5_response( 200, '{"events_received":1,"messages":[]}' ), 'accepted', '' ),
	array( 'accepted, other version served', l5_response( 200, '{"events_received":1}', array( 'facebook-api-version' => 'v27.0' ) ), 'accepted_warning', 'version' ),
);
foreach ( $cases as $case ) {
	$result = Capi_Transport::classify( $case[1], 'v26.0' );
	l5_check( $case[2] === $result['class'] && $case[3] === $result['reason'], 'V2: ' . $case[0] . ' → ' . $case[2] );
}
$absent = Capi_Transport::classify( $cases[14][1], 'v26.0' );
l5_check( 'unknown' === $absent['version_served'] && Capi_Transport::UNKNOWN_VERSION === $absent['version_served'] && false === $absent['version_mismatch'], 'V2: header absent → technical value « unknown », no alert.' );
// §7: the visible word is translated at display time, never stored as a hard-coded French string.
l5_check( 'inconnue' === Capi_Transport::served_label( 'unknown' ) && 'inconnue' === Capi_Transport::served_label( '' ) && 'inconnue' === Capi_Transport::served_label( 'inconnue' ) && 'v27.0' === Capi_Transport::served_label( 'v27.0' ), 'V2: « inconnue » is shown through __() (unknown, empty or legacy value); a real version is shown as is.' );
$transport_source = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/capi-transport.php' );
l5_check( str_contains( $transport_source, "__( 'inconnue', 'famma-core' )" ) && ! str_contains( $transport_source, "UNKNOWN_VERSION = 'inconnue'" ), 'V2: the transport keeps a neutral technical value; the label goes through the famma-core text domain.' );
l5_check(
	str_contains(
		\Famma\Core\Capi_Health_Rules::label(
			array(
				'code'   => 'version_served',
				'detail' => 'unknown',
			)
		),
		'(inconnue)'
	),
	'V2: the version_served health label translates the unknown value.'
);
$other = Capi_Transport::classify( $cases[15][1], 'v26.0' );
l5_check( 'v27.0' === $other['version_served'] && true === $other['version_mismatch'], 'V2: served version differs from the requested one → flagged.' );
$sub = Capi_Transport::classify( $cases[1][1], 'v26.0' );
l5_check( 190 === $sub['code'] && 463 === $sub['subcode'], 'V2: code and subcode are read.' );

// One journal line per send, with the transport fields and never the token.
$GLOBALS['wp_test_http_response'] = l5_response( 200, '{"events_received":1,"messages":["warn"],"fbtrace_id":"T1"}', array( 'facebook-api-version' => 'v25.0' ) );
$wc_order                         = new WC_Order( 5201 );
Capi::instance()->send_purchase( $wc_order, 'famma_purchase_5201' );
$lines = l5_capi_lines();
l5_check( 1 === count( $lines ), 'One famma-capi line for one send.' );
l5_check( str_contains( $lines[0], 'version=v26.0 served=v25.0 class=accepted_warning code=0 subcode=0 messages=1' ) && str_contains( $lines[0], 'state=accepted' ), 'The line carries version requested and served, class, code, subcode and message count.' );
l5_check( (bool) $wc_order->get_meta( '_famma_purchase_sent' ), 'An accepted-with-warning send is still accepted.' );
l5_check( ! str_contains( implode( "\n", $lines ), $token ), 'The token never reaches the journal.' );
l5_check( ! str_contains( $GLOBALS['wp_test_http'][0]['url'], $token ) && str_contains( (string) $GLOBALS['wp_test_http'][0]['args']['body'], $token ), 'The token travels in the body, never in the URL.' );
l5_check( 8 === $GLOBALS['wp_test_http'][0]['args']['timeout'] && 0 === $GLOBALS['wp_test_http'][0]['args']['redirection'], 'Timeout 8 s, no redirection.' );

// Probe (R6, 0.3.3): GET /{version}/me?fields=id, token in a header, never POST /events.
// The CAPI token of a system user may send events without reading the Pixel object
// (0.3.2 in production: GET /{pixel} → 400 « (#100) Missing Permission »), so the
// probe reads the token holder instead; dataset access stays proven by real sends.
l5_reset();
$GLOBALS['wp_test_http_response'] = l5_response( 200, '{"id":"000000000000042"}', array( 'facebook-api-version' => 'v26.0' ) );
$probe                            = \Famma\Core\Capi_Probe::run();
$request                          = $GLOBALS['wp_test_http'][0];
l5_check( 'accepted' === $probe['class'] && 'ok' === $probe['verdict'] && 'GET' === $request['method'] && 'https://graph.facebook.com/v26.0/me?fields=id' === $request['url'], 'V4: probe 200 → accepted, by GET /v26.0/me?fields=id.' );
l5_check( ! str_contains( $request['url'], '000000000000001' ), 'V4: the probe no longer reads the Pixel object.' );
l5_check( 'Bearer ' . $token === $request['args']['headers']['Authorization'] && ! str_contains( $request['url'], $token ) && ! str_contains( $request['url'], '/events' ), 'V4: probe token in the Authorization header, no /events call.' );
l5_check( 'vert' === \Famma\Core\Capi_Health::state()['statut'], 'V4: probe 200 → health green.' );
$GLOBALS['wp_test_http_response'] = l5_response( 400, $json( array( 'code' => 190 ) ) );
\Famma\Core\Capi_Probe::run();
l5_check( 'rouge' === \Famma\Core\Capi_Health::state()['statut'], 'V4: probe 190 → health red.' );
$GLOBALS['wp_test_http_response'] = l5_response(
	400,
	$json(
		array(
			'code'    => 100,
			'message' => '(#100) Missing Permission',
			'type'    => 'OAuthException',
		)
	),
	array( 'facebook-api-version' => 'v26.0' )
);
\Famma\Core\Capi_Probe::run();
$state = \Famma\Core\Capi_Health::state();
l5_check( 'orange' === $state['statut'] && in_array( 'probe_permission', array_column( $state['raisons'], 'code' ), true ), 'V4: « Missing Permission » on /me → orange « droits du jeton à vérifier », never red.' );
$GLOBALS['wp_test_http_response'] = l5_response( 400, $json( array( 'code' => 100 ) ) );
\Famma\Core\Capi_Probe::run();
$state = \Famma\Core\Capi_Health::state();
l5_check( 'orange' === $state['statut'] && in_array( 'probe_unavailable', array_column( $state['raisons'], 'code' ), true ), 'V4: other probe-specific error → orange « sonde indisponible », never red.' );
$GLOBALS['wp_test_environment'] = 'local';
$GLOBALS['wp_test_http']        = array();
l5_check( 'blocked_environment' === \Famma\Core\Capi_Probe::run()['class'] && array() === $GLOBALS['wp_test_http'], 'V4: outside production, the probe sends no request.' );

// V3 — one wp_remote_post to graph.facebook.com in famma-core, shared by the test button and real sends.
$plugin_dir = ABSPATH . 'wp-content/plugins/famma-core/';
$post_calls = 0;
$graph      = array();
foreach ( array_merge( array( $plugin_dir . 'famma-core.php' ), glob( $plugin_dir . 'includes/*.php' ) ) as $file ) {
	$code        = (string) file_get_contents( $file );
	$post_calls += preg_match_all( '/\bwp_remote_post\s*\(/', $code );
	if ( str_contains( $code, "'https://graph.facebook.com/'" ) ) {
		$graph[] = basename( $file );
	}
}
l5_check( 1 === $post_calls && array( 'capi-transport.php' ) === $graph, 'V3: a single wp_remote_post and a single graph.facebook.com URL, in capi-transport.php.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
