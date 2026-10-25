<?php
/**
 * T1.6 — The CAPI token never reaches the famma-capi log, the order notes,
 * the request URL or the admin redirect; only its last 4 characters in the admin.
 * Declared gap to V2 (H-S8): a third-party listener of `http_api_debug` still
 * receives the request arguments, hence the token (WordPress HTTP API limit,
 * documented in includes/capi-transport.php). It is counted apart, as
 * ECART_V2, never hidden inside FUITES=0.
 * Run with: php scripts/tests-unit/test-capi-log-masquage.php [--json]
 * Real Capi, Config, Tunisia, Capi_Signals, Capi_Log, Capi_Product_Data, the
 * lot 5 collaborators (transport, user data, vault…) and Tracking_Settings;
 * only the delivery registry is doubled. Fake pixel and token.
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php for the doubles.
require __DIR__ . '/bootstrap-capi.php';

Famma_Unit_Harness::load_include( 'tracking-settings.php' );

$fake_token                 = 'QA-FAKE-TOKEN-NOT-REAL';
$GLOBALS['wp_test_options'] = array(
	'famma_meta_pixel_id'     => '000000000000001',
	'famma_meta_access_token' => $fake_token,
);

// Stand-in for any third-party plugin listening to the WordPress HTTP debug action.
add_action(
	'http_api_debug',
	static function ( $response, string $context, string $transport, array $args, string $url ): void {
		$GLOBALS['debug_seen'][] = (string) ( $args['body'] ?? '' ) . ' ' . $url;
	},
	10,
	5
);

/*
 * Longest fragment of the token (8 characters or more) found in a text,
 * case-insensitive; empty string when none appears.
 */
$leak = static function ( string $text, string $token ): string {
	$haystack     = strtolower( $text );
	$token_length = strlen( $token );
	for ( $length = $token_length; $length >= 8; $length-- ) {
		for ( $offset = 0; $offset + $length <= $token_length; $offset++ ) {
			$fragment = substr( $token, $offset, $length );
			if ( str_contains( $haystack, strtolower( $fragment ) ) ) {
				return $fragment;
			}
		}
	}
	return '';
};

$http_response = static function ( int $code, string $body ): array {
	return array(
		'response' => array( 'code' => $code ),
		'body'     => $body,
	);
};

$json_error = static function ( array $error ): string {
	return (string) json_encode( array( 'error' => $error ) );
};

// Each path of Capi::send_order_event() that writes a famma-capi line, plus edge inputs.
$paths = array(
	array( 'transport_uncertain : WP_Error contenant le jeton entier', new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out; body access_token=' . $fake_token ), true ),
	array( 'transport_uncertain : WP_Error avec les 16 premiers caractères du jeton', new WP_Error( 'http_request_failed', 'cURL error 56: truncated access_token=' . substr( $fake_token, 0, 16 ) ), true ),
	array( 'transport_uncertain : WP_Error avec le jeton coupé par un saut de ligne', new WP_Error( 'http_request_failed', "access_token=QA-FAKE-\nTOKEN-NOT-REAL" ), true ),
	array( 'transport_uncertain : WP_Error avec le jeton en minuscules', new WP_Error( 'http_request_failed', 'access_token=' . strtolower( $fake_token ) ), true ),
	array( 'transport_uncertain : message de 300+ caractères, jeton en fin', new WP_Error( 'http_request_failed', str_repeat( 'x', 290 ) . ' ' . $fake_token ), true ),
	array(
		'rejected : HTTP 400, error.message contenant le jeton',
		$http_response(
			400,
			$json_error(
				array(
					'message'    => 'Invalid OAuth access token ' . $fake_token,
					'code'       => 190,
					'fbtrace_id' => 'AbCdTrace1',
				)
			)
		),
		true,
	),
	array(
		'rejected : HTTP 400, error.error_user_msg contenant le jeton',
		$http_response(
			400,
			$json_error(
				array(
					'message'        => 'x',
					'error_user_msg' => 'Token ' . $fake_token . ' expired',
					'code'           => 190,
				)
			)
		),
		true,
	),
	array( 'uncertain : HTTP 500, corps non JSON contenant le jeton', $http_response( 500, 'Internal error ' . $fake_token ), true ),
	array( 'uncertain : HTTP 503, error.message contenant le jeton', $http_response( 503, $json_error( array( 'message' => 'Service unavailable ' . $fake_token ) ) ), true ),
	array( 'ack_uncertain : HTTP 200, events_received absent, error contenant le jeton', $http_response( 200, $json_error( array( 'message' => 'Partial ' . $fake_token ) ) ), true ),
	array( 'ack_uncertain : HTTP 200, events_received 0', $http_response( 200, '{"events_received":0}' ), true ),
	array( 'ack_persistence_uncertain : HTTP 200 accepté, registre non confirmé', $http_response( 200, '{"events_received":1,"fbtrace_id":"AbCdTrace2"}' ), false ),
	array( 'accepted : HTTP 200, events_received 1', $http_response( 200, '{"events_received":1,"fbtrace_id":"AbCdTrace3"}' ), true ),
	array(
		'théorique : HTTP 400, fbtrace_id contenant le jeton',
		$http_response(
			400,
			$json_error(
				array(
					'message'    => 'x',
					'code'       => 100,
					'fbtrace_id' => $fake_token,
				)
			)
		),
		true,
	),
);

$capi_client = \Famma\Core\Capi::instance();
$rows        = array();
$failures    = 0;
foreach ( $paths as $index => $capi_path ) {
	list( $label, $response, $registry_ok ) = $capi_path;
	$GLOBALS['wc_test_log']                 = array();
	$GLOBALS['wp_test_http']                = array();
	$GLOBALS['debug_seen']                  = array();
	$GLOBALS['wp_test_http_response']       = $response;
	$GLOBALS['famma_test_registry_finish']  = $registry_ok;
	$test_order                             = new WC_Order( 1600 + $index );
	$capi_client->send_purchase( $test_order, 'famma_purchase_' . ( 1600 + $index ) );
	$log_text  = implode( "\n", array_column( $GLOBALS['wc_test_log'], 'message' ) );
	$notes     = implode( "\n", $test_order->notes );
	$url       = (string) ( $GLOBALS['wp_test_http'][0]['url'] ?? '' );
	$body      = (string) ( $GLOBALS['wp_test_http'][0]['args']['body'] ?? '' );
	$log_leak  = $leak( $log_text, $fake_token );
	$note_leak = $leak( $notes, $fake_token );
	$url_leak  = $leak( $url, $fake_token );
	$ok        = '' === $log_leak && '' === $note_leak && '' === $url_leak;
	$failures += $ok ? 0 : 1;
	$rows[]    = array(
		'chemin'             => $label,
		'verdict'            => $ok ? 'MASQUE' : 'FUITE',
		'fuite_journal'      => $log_leak,
		'fuite_notes'        => $note_leak,
		'fuite_url'          => $url_leak,
		'ligne_journal'      => $log_text,
		'niveau'             => $GLOBALS['wc_test_log'][0]['level'] ?? '',
		'source'             => $GLOBALS['wc_test_log'][0]['source'] ?? '',
		'notes'              => $test_order->notes,
		'jeton_dans_corps'   => str_contains( $body, $fake_token ),
		'http_api_debug_voi' => '' !== $leak( implode( "\n", $GLOBALS['debug_seen'] ), $fake_token ),
	);
}

// Admin screen: the field is never refilled, only the last 4 characters are shown.
ob_start();
Famma_Unit_Harness::bare( '\Famma\Core\Tracking_Settings' )->render_field(
	array(
		'option'   => 'famma_meta_access_token',
		'constant' => 'FAMMA_META_ACCESS_TOKEN',
		'secret'   => true,
	)
);
$admin_html  = (string) ob_get_clean();
$admin_leak  = $leak( $admin_html, $fake_token );
$admin_last4 = str_contains( $admin_html, '<code>' . substr( $fake_token, -4 ) . '</code>' );
$admin_ok    = '' === $admin_leak && $admin_last4 && str_contains( $admin_html, 'value=""' );
$failures   += $admin_ok ? 0 : 1;
$rows[]      = array(
	'chemin'             => 'admin : champ du jeton (render_field)',
	'verdict'            => $admin_ok ? 'MASQUE' : 'FUITE',
	'fuite_journal'      => $admin_leak,
	'fuite_notes'        => '',
	'fuite_url'          => '',
	'ligne_journal'      => trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $admin_html ) ) ),
	'niveau'             => '',
	'source'             => '',
	'notes'              => array(),
	'jeton_dans_corps'   => false,
	'http_api_debug_voi' => false,
);

// Admin test button: redirect with a fixed result, whatever the response says.
$settings_screen = Famma_Unit_Harness::bare( '\Famma\Core\Tracking_Settings' );
foreach ( array(
	'network'  => new WP_Error( 'http_request_failed', 'cURL error 7 ' . $fake_token ),
	'rejected' => $http_response( 400, $json_error( array( 'message' => 'Invalid ' . $fake_token ) ) ),
) as $expected => $response ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Test fixture of the posted admin form; the nonce check is doubled.
	$_POST['famma_capi_test_code']  = 'TEST12345';
	$_POST['famma_capi_test_event'] = 'Purchase';
	$_POST['famma_capi_test_value'] = '1';
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	$GLOBALS['wp_test_http_response'] = $response;
	$GLOBALS['debug_seen']            = array();
	$location                         = '';
	try {
		$settings_screen->send_test_event();
	} catch ( Famma_Test_Redirect $redirect ) {
		$location = $redirect->getMessage();
	}
	$redirect_leak = $leak( $location, $fake_token );
	$redirect_ok   = '' === $redirect_leak && str_contains( $location, 'famma_capi_result=' . $expected );
	$failures     += $redirect_ok ? 0 : 1;
	$rows[]        = array(
		'chemin'             => 'admin : bouton de test, résultat « ' . $expected . ' »',
		'verdict'            => $redirect_ok ? 'MASQUE' : 'FUITE',
		'fuite_journal'      => '',
		'fuite_notes'        => '',
		'fuite_url'          => $redirect_leak,
		'ligne_journal'      => $location,
		'niveau'             => '',
		'source'             => '',
		'notes'              => array(),
		'jeton_dans_corps'   => true,
		'http_api_debug_voi' => '' !== $leak( implode( "\n", $GLOBALS['debug_seen'] ), $fake_token ),
	);
}

// famma-core itself never listens to http_api_debug.
$core_listener = 0;
foreach ( array( 'famma-core.php', 'includes/capi-log.php', 'includes/capi-signals.php', 'includes/tracking-settings.php' ) as $core_file ) {
	$core_listener += substr_count( (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/' . $core_file ), 'http_api_debug' );
}

// Declared gap to V2: paths where a third-party http_api_debug listener sees the token.
$v2_gaps = count( array_filter( array_column( $rows, 'http_api_debug_voi' ) ) );

if ( in_array( '--json', $argv, true ) ) {
	echo json_encode(
		array(
			'test'                          => 'T1.6',
			'fuites'                        => $failures,
			'ecart_v2_http_api_debug'       => $v2_gaps,
			'ecouteurs_http_api_debug_core' => $core_listener,
			'rows'                          => $rows,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	) . PHP_EOL;
	exit( 0 );
}
foreach ( $rows as $row ) {
	echo $row['verdict'] . ': ' . $row['chemin'] . ( '' !== $row['fuite_journal'] ? ' [journal: « ' . $row['fuite_journal'] . ' »]' : '' ) . ( '' !== $row['fuite_url'] ? ' [url: « ' . $row['fuite_url'] . ' »]' : '' ) . ( '' !== $row['fuite_notes'] ? ' [notes: « ' . $row['fuite_notes'] . ' »]' : '' ) . ' | http_api_debug voit le jeton: ' . ( $row['http_api_debug_voi'] ? 'oui (ÉCART V2 déclaré)' : 'non' ) . PHP_EOL;
	if ( '' !== $row['ligne_journal'] ) {
		echo '    ' . str_replace( "\n", "\n    ", $row['ligne_journal'] ) . PHP_EOL;
	}
}
echo 'Occurrences de http_api_debug dans famma-core : ' . $core_listener . PHP_EOL;
echo 'CHECKS=' . count( $rows ) . ' FUITES=' . $failures . ' ECART_V2=' . $v2_gaps . PHP_EOL;
echo 'ÉCART V2 (déclaré, non corrigé) : un écouteur tiers de http_api_debug reçoit les arguments de la requête, donc le jeton, sur ' . $v2_gaps . ' chemin(s) ; limite de l’API HTTP de WordPress, voir includes/capi-transport.php. T1.6 et V2 ne sont donc pas entièrement verts.' . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
