<?php
/**
 * Lot 5, A11 (D7, T2.12) — CAPI token encrypted at rest: transparent migration
 * of a clear token, only the last 4 characters shown, constant first, an
 * undecryptable token is treated as missing (re-enter notice, red state).
 * Run with: php scripts/tests-unit/test-capi-token-vault.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';
Famma_Unit_Harness::load_include( 'measurement-settings.php' );
Famma_Unit_Harness::load_include( 'tracking-settings.php' );

use Famma\Core\Capi;
use Famma\Core\Capi_Health;
use Famma\Core\Capi_Token_Vault;
use Famma\Core\Config;

$token = 'QA-L5-FAKE-TOKEN-NOT-REAL';
l5_reset( array( 'famma_meta_access_token' => $token ) );

// Round trip.
$sealed = Capi_Token_Vault::seal( $token );
l5_check( Capi_Token_Vault::is_sealed( $sealed ) && ! str_contains( $sealed, $token ) && Capi_Token_Vault::open( $sealed ) === $token, 'A sealed token is unreadable as such and opens back to the same value.' );
l5_check( Capi_Token_Vault::seal( $token ) !== $sealed, 'Each seal uses a fresh nonce.' );

// Transparent migration on first read.
l5_check( Config::instance()->meta_access_token() === $token, 'A clear token stored before 0.3.2 is still used.' );
$stored = (string) $GLOBALS['wp_test_options']['famma_meta_access_token'];
l5_check( Capi_Token_Vault::is_sealed( $stored ) && ! str_contains( $stored, $token ) && 'sealed' === Capi_Token_Vault::state(), 'T2.12: after the first read, the option holds only the encrypted value.' );
l5_check( Config::instance()->meta_access_token() === $token && 'REAL' === Capi_Token_Vault::last4(), 'The encrypted token is read back; only its last 4 characters are exposed.' );

// The admin field shows only the last 4 characters, never the token nor its encrypted form.
ob_start();
Famma_Unit_Harness::bare( '\Famma\Core\Tracking_Settings' )->render_field(
	array(
		'option'   => 'famma_meta_access_token',
		'constant' => 'FAMMA_META_ACCESS_TOKEN',
		'secret'   => true,
	)
);
$html = (string) ob_get_clean();
l5_check( str_contains( $html, '<code>REAL</code>' ) && ! str_contains( $html, $token ) && ! str_contains( $html, $stored ) && str_contains( $html, 'value=""' ), 'The admin field shows the last 4 characters only.' );

// Saving from the screen: new token sealed, sealed value kept, empty keeps, « supprimer » clears.
$settings = Famma_Unit_Harness::bare( '\Famma\Core\Tracking_Settings' );
$new      = $settings->sanitize_token( 'QA-L5-OTHER-FAKE-TOKEN' );
l5_check( Capi_Token_Vault::is_sealed( $new ) && 'QA-L5-OTHER-FAKE-TOKEN' === Capi_Token_Vault::open( $new ), 'A token typed in « Mesure » is stored encrypted.' );
l5_check( $stored === $settings->sanitize_token( $stored ) && $stored === $settings->sanitize_token( '' ) && '' === $settings->sanitize_token( 'supprimer' ), 'An already encrypted value is kept, an empty field keeps the token, « supprimer » clears it.' );

// Site keys changed: the token becomes unreadable, nothing is sent, red state.
$GLOBALS['wp_test_salt'] = 'rotated-salt';
l5_check( '' === Config::instance()->meta_access_token() && 'broken' === Capi_Token_Vault::state(), 'A token that cannot be decrypted is treated as missing.' );
$wc_order = new WC_Order( 5600 );
Capi::instance()->fire_purchase_on_status( 5600 );
$lines = l5_capi_lines();
l5_check( array() === $GLOBALS['wp_test_http'] && str_contains( end( $lines ), 'state=not_configured reason=token' ), 'Nothing is sent with an unreadable token; the journal says why.' );
$state = Capi_Health::evaluate();
l5_check( 'rouge' === $state['statut'] && 'token_unreadable' === $state['raisons'][0]['code'], 'Health is red with the « jeton à ressaisir » reason.' );
unset( $GLOBALS['wp_test_salt'] );

// The server constant stays first (defined last: it cannot be undefined).
define( 'FAMMA_META_ACCESS_TOKEN', 'QA-L5-CONSTANT-FAKE' );
l5_check( 'QA-L5-CONSTANT-FAKE' === Config::instance()->meta_access_token(), 'The FAMMA_META_ACCESS_TOKEN constant overrides the stored option.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
