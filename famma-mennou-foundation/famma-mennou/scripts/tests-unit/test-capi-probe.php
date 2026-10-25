<?php
/**
 * 0.3.3 — probe on GET /{version}/me?fields=id (V4): verdict of every Meta
 * answer, health reason and level, served version still compared, 0.3.2
 * signals (Pixel object) re-probed, a valid /me never clears a dataset
 * refusal, journal without the token.
 * Run with: php scripts/tests-unit/test-capi-probe.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi_Health;
use Famma\Core\Capi_Probe;
use Famma\Core\Capi_Probe_Verdict;
use Famma\Core\Capi_Transport;

$token      = 'QA-L5-FAKE-TOKEN-NOT-REAL';
$meta_error = static fn( array $fields, int $http = 400 ): array => l5_response( $http, (string) json_encode( array( 'error' => $fields + array( 'type' => 'OAuthException' ) ) ), array( 'facebook-api-version' => 'v26.0' ) );
$probe      = static function ( $response ): array {
	l5_reset();
	$GLOBALS['wp_test_http_response'] = $response;
	$result                           = Capi_Probe::run();
	$state                            = Capi_Health::state();
	return array(
		'result'  => $result,
		'statut'  => $state['statut'],
		'raisons' => array_map( static fn( array $r ): string => $r['code'] . ':' . $r['niveau'] . ':' . $r['detail'], $state['raisons'] ),
		'request' => $GLOBALS['wp_test_http'][0] ?? array(),
	);
};

// Verdict matrix: code (or subcode) → verdict → health reason.
$cases = array(
	array( '200 with the system user id', l5_response( 200, '{"id":"000000000000042"}', array( 'facebook-api-version' => 'v26.0' ) ), 'ok', 'vert', null ),
	array( '190 invalid token', $meta_error( array( 'code' => 190 ) ), 'token', 'rouge', 'probe_token:rouge:190' ),
	array(
		'190 / 463 expired token',
		$meta_error(
			array(
				'code'          => 190,
				'error_subcode' => 463,
			)
		),
		'token',
		'rouge',
		'probe_token:rouge:190',
	),
	array(
		'190 / 467 invalid token',
		$meta_error(
			array(
				'code'          => 190,
				'error_subcode' => 467,
			)
		),
		'token',
		'rouge',
		'probe_token:rouge:190',
	),
	array( '102 API session', $meta_error( array( 'code' => 102 ) ), 'token', 'rouge', 'probe_token:rouge:102' ),
	array( '10 permission', $meta_error( array( 'code' => 10 ), 403 ), 'permission', 'orange', 'probe_permission:orange:10' ),
	array( '200 permission', $meta_error( array( 'code' => 200 ), 403 ), 'permission', 'orange', 'probe_permission:orange:200' ),
	array( '294 permission range', $meta_error( array( 'code' => 294 ), 403 ), 'permission', 'orange', 'probe_permission:orange:294' ),
	array( '368 temporarily blocked', $meta_error( array( 'code' => 368 ) ), 'permission', 'orange', 'probe_permission:orange:368' ),
	array(
		'100 « Missing Permission »',
		$meta_error(
			array(
				'code'    => 100,
				'message' => '(#100) Missing Permission',
			)
		),
		'permission',
		'orange',
		'probe_permission:orange:100',
	),
	array(
		'100 other invalid parameter',
		$meta_error(
			array(
				'code'    => 100,
				'message' => 'Unsupported get request.',
			)
		),
		'unavailable',
		'orange',
		'probe_unavailable:orange:100',
	),
	array(
		'500 transient',
		$meta_error(
			array(
				'code'         => 2,
				'is_transient' => true,
			),
			500
		),
		'unavailable',
		'orange',
		'probe_unavailable:orange:2',
	),
	array( 'timeout', new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 8000 milliseconds' ), 'unavailable', 'orange', 'probe_unavailable:orange:0' ),
	array( '200 without id', l5_response( 200, '{}', array( 'facebook-api-version' => 'v26.0' ) ), 'unavailable', 'orange', 'probe_unavailable:orange:0' ),
	array( '200 with a non-numeric id', l5_response( 200, '{"id":"me"}', array( 'facebook-api-version' => 'v26.0' ) ), 'unavailable', 'orange', 'probe_unavailable:orange:0' ),
);
foreach ( $cases as $case ) {
	$row    = $probe( $case[1] );
	$reason = preg_grep( '/^probe_/', $row['raisons'] );
	l5_check( $case[2] === $row['result']['verdict'] && $case[3] === $row['statut'] && ( null === $case[4] ? array() === $reason : array( $case[4] ) === array_values( $reason ) ), 'V4: ' . $case[0] . ' → ' . $case[2] . ', ' . $case[3] . '.' );
	l5_check( 'GET' === $row['request']['method'] && Capi_Transport::probe_url( 'v26.0' ) === $row['request']['url'] && 'https://graph.facebook.com/v26.0/me?fields=id' === $row['request']['url'], 'V4: ' . $case[0] . ' — one GET /v26.0/me?fields=id, never POST /events.' );
	l5_check( 1 === count( $GLOBALS['wp_test_http'] ) && 'Bearer ' . $token === $row['request']['args']['headers']['Authorization'] && ! str_contains( $row['request']['url'], $token ), 'V4: ' . $case[0] . ' — token in the Authorization header only.' );
}

// The served version is still read from facebook-api-version and compared.
$other = $probe( l5_response( 200, '{"id":"000000000000042"}', array( 'facebook-api-version' => 'v27.0' ) ) );
l5_check( 'accepted_warning' === $other['result']['class'] && 'ok' === $other['result']['verdict'] && 'v27.0' === $other['result']['version_served'] && in_array( 'version_served:orange:v27.0', $other['raisons'], true ), 'V4: another served version → accepted_warning, orange « version servie ».' );
$absent = $probe( l5_response( 200, '{"id":"000000000000042"}' ) );
l5_check( 'accepted' === $absent['result']['class'] && Capi_Transport::UNKNOWN_VERSION === $absent['result']['version_served'] && 'vert' === $absent['statut'], 'V4: header absent → « unknown », no alert.' );
l5_check( 'v26.0' === ( Capi_Health::signals()['served']['requested'] ?? '' ), 'V4: the probe records the requested version.' );

// A valid /me proves the token, not the dataset: only a token refusal is cleared.
foreach ( array(
	190 => true,
	102 => true,
	10  => false,
	200 => false,
) as $code => $cleared ) {
	l5_reset();
	Capi_Health::signal( 'access', array( 'code' => $code ) );
	$GLOBALS['wp_test_http_response'] = l5_response( 200, '{"id":"000000000000042"}', array( 'facebook-api-version' => 'v26.0' ) );
	Capi_Probe::run();
	$codes = array_column( Capi_Health::state()['raisons'], 'code' );
	$kept  = isset( Capi_Health::signals()['access'] ) && in_array( 'access', $codes, true );
	$gone  = ! isset( Capi_Health::signals()['access'] ) && ! in_array( 'access', $codes, true );
	l5_check( $cleared ? $gone : $kept, 'V4: send refusal ' . $code . ( $cleared ? ' cleared by a valid /me (token fixed).' : ' kept after a valid /me (dataset access is proven by a real send).' ) );
}

// 0.3.2 signal (GET /{pixel}, no verdict): only its 190 still counts, and it is re-probed on /me.
$legacy = array(
	'class'     => 'contract',
	'code'      => 100,
	'http'      => 400,
	'requested' => 'v26.0',
	'served'    => 'v26.0',
	'at'        => time(),
);
l5_reset( array( 'famma_capi_health_signals' => array( 'probe' => $legacy ) ) );
$codes = array_column( Capi_Health::evaluate()['raisons'], 'code' );
l5_check( ! in_array( 'probe_unavailable', $codes, true ) && 'vert' === Capi_Health::state()['statut'], 'V4: the 0.3.2 « (#100) Missing Permission » on the Pixel no longer keeps the banner orange.' );
$legacy_190 = array(
	'class' => 'access',
	'code'  => 190,
) + $legacy;
l5_reset( array( 'famma_capi_health_signals' => array( 'probe' => $legacy_190 ) ) );
l5_check( in_array( 'probe_token', array_column( Capi_Health::evaluate()['raisons'], 'code' ), true ), 'V4: a 0.3.2 probe 190 stays red until the next probe.' );
l5_reset( array( 'famma_capi_health_signals' => array( 'probe' => $legacy ) ) );
Capi_Probe::instance()->ensure_scheduled();
l5_check( array() !== l5_as_find( Capi_Probe::HOOK, array( 'reason' => 'settings' ), Capi_Probe::GROUP, array( 'pending' ) ), 'V4: a 0.3.2 probe signal is re-probed on /me by Action Scheduler.' );
$GLOBALS['wp_test_http_response'] = l5_response( 200, '{"id":"000000000000042"}', array( 'facebook-api-version' => 'v26.0' ) );
while ( null !== l5_as_run_next() ) {
	continue;
}
l5_check( 'ok' === ( Capi_Health::signals()['probe']['verdict'] ?? '' ) && 'https://graph.facebook.com/v26.0/me?fields=id' === ( $GLOBALS['wp_test_http'][0]['url'] ?? '' ), 'V4: the re-probe ran on /me and recorded its verdict.' );
$GLOBALS['as_test_actions']    = array();
$GLOBALS['wp_test_transients'] = array();
Capi_Probe::instance()->ensure_scheduled();
l5_check( array() === l5_as_find( Capi_Probe::HOOK, array( 'reason' => 'settings' ), Capi_Probe::GROUP, array() ), 'V4: a 0.3.3 probe signal is not re-probed again.' );

// Displayed texts (§7): French, through the famma-core text domain.
$token_row = $probe( $meta_error( array( 'code' => 190 ) ) );
l5_check( 'token' === Capi_Probe_Verdict::outcome( $token_row['result'] ) && 'token' === Capi_Probe_Verdict::outcome( Capi_Health::signals()['probe'] ?? array() ), 'V4: the notice and the last check of a 190 both say « token ».' );
l5_check(
	'contract' === Capi_Probe_Verdict::outcome(
		array(
			'class'   => 'contract',
			'verdict' => 'unavailable',
		)
	) && 'permission' === Capi_Probe_Verdict::outcome(
		array(
			'class'   => 'contract',
			'verdict' => 'permission',
		)
	) && 'contract' === Capi_Probe_Verdict::outcome( $legacy ),
	'V4: outcome() keeps the transport class unless the verdict is « token » or « permission ».'
);
l5_check( ! str_contains( Capi_Probe_Verdict::label( 'accepted' ), 'connexion valide' ) && str_contains( Capi_Probe_Verdict::label( 'accepted' ), 'jeton valide' ) && str_contains( Capi_Probe_Verdict::label( 'permission' ), 'droits du jeton à vérifier' ), 'V4: notice texts describe the token check, not a Pixel connection.' );
$sources = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/capi-probe-verdict.php' ) . (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/capi-health-rules.php' );
l5_check( str_contains( $sources, "__( 'jeton reconnu, droits du jeton à vérifier', 'famma-core' )" ) && str_contains( $sources, "'probe_permission'  => sprintf( __( 'Droits du jeton à vérifier" ), 'V4: new texts go through __() with the famma-core text domain.' );
$health_label = static fn( string $code, string $detail ): string => \Famma\Core\Capi_Health_Rules::label(
	array(
		'code'   => $code,
		'detail' => $detail,
	)
);
l5_check( str_contains( $health_label( 'probe_permission', '100' ), 'Droits du jeton à vérifier (code 100)' ) && str_contains( $health_label( 'probe_token', '102' ), 'jeton invalide ou expiré (code 102)' ), 'V4: health labels name the code.' );

// Journal: one famma-capi line per probe, never the token.
$row   = $probe(
	$meta_error(
		array(
			'code'    => 100,
			'message' => '(#100) Missing Permission ' . $token,
		)
	)
);
$lines = l5_capi_lines();
l5_check( 1 === count( $lines ) && str_contains( $lines[0], 'Probe' ) && str_contains( $lines[0], 'class=contract code=100' ) && ! str_contains( implode( "\n", $lines ), $token ), 'V4: one journal line, with class and code, never the token.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
