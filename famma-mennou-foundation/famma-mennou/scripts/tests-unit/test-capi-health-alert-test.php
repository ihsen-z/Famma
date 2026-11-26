<?php
/**
 * Reevaluation C6 — « Envoyer un e-mail d'essai » (FAMMA → Mesure, alertes de la mesure Meta).
 * Nonce then capability, address read from the saved option (never from the request), empty
 * address, one test per minute, e-mail clearly marked « essai » without token nor customer data,
 * result message, separate form, and no effect on the state of the real alerts.
 * Run with: php scripts/tests-unit/test-capi-health-alert-test.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Test fixtures of the query string.
require __DIR__ . '/bootstrap-capi.php';
Famma_Unit_Harness::load_include( 'capi-health-alert-test.php' );

use Famma\Core\Capi_Health_Alert;
use Famma\Core\Capi_Health_Alert_Test;

function is_email( $email ) {
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}
function esc_url( $url ): string {
	return (string) $url;
}
function wp_nonce_field( string $action ): void {
	echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '" />';
}
function submit_button( string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true ): void {
	echo '<button>' . esc_html( $text ) . '</button>';
}

$mails    = static fn(): int => count( $GLOBALS['wp_test_mail'] ?? array() );
$redirect = static function (): string {
	try {
		Capi_Health_Alert_Test::instance()->handle();
	} catch ( Famma_Test_Redirect $redirect ) {
		return $redirect->getMessage();
	} catch ( RuntimeException $e ) {
		return $e->getMessage();
	}
	return '';
};
$screen   = static function ( string $query = '' ): string {
	$_GET = '' === $query ? array() : array( Capi_Health_Alert_Test::RESULT => $query );
	ob_start();
	Capi_Health_Alert_Test::render();
	$html = (string) ob_get_clean();
	$_GET = array();
	return $html;
};
$token    = 'QA-L5-FAKE-TOKEN-NOT-REAL';
$health   = array( 'famma_capi_health', 'famma_capi_health_mail', 'famma_capi_health_mail_codes', 'famma_capi_health_mail_red' );
$snapshot = static function () use ( $health ): array {
	return array_intersect_key( $GLOBALS['wp_test_options'], array_flip( $health ) );
};

// Source: nonce first, then capability, then the send; the saved address only, never the request.
$source = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/capi-health-alert-test.php' );
$handle = (string) substr( $source, (int) strpos( $source, 'public function handle()' ), (int) strpos( $source, 'public static function run()' ) - (int) strpos( $source, 'public function handle()' ) );
l5_check( strpos( $handle, 'check_admin_referer( self::ACTION )' ) < strpos( $handle, "current_user_can( 'manage_woocommerce' )" ) && strpos( $handle, "current_user_can( 'manage_woocommerce' )" ) < strpos( $handle, 'self::run()' ), 'Source: nonce, then manage_woocommerce, then the send.' );
l5_check( ! str_contains( $source, '$_POST' ) && ! str_contains( $source, '$_REQUEST' ) && str_contains( $source, 'Capi_Health_Alert::recipient()' ), 'Source: nothing is read from the posted form; the recipient is the saved address.' );
l5_check( isset( $GLOBALS['wp_test_hooks'][ 'admin_post_' . Capi_Health_Alert_Test::ACTION ] ) === false, 'Hook not registered before instance().' );
Capi_Health_Alert_Test::instance();
l5_check( isset( $GLOBALS['wp_test_hooks'][ 'admin_post_' . Capi_Health_Alert_Test::ACTION ] ) && ! isset( $GLOBALS['wp_test_hooks'][ 'admin_post_nopriv_' . Capi_Health_Alert_Test::ACTION ] ), 'admin-post action registered, never nopriv.' );

// Capability: refused, nothing sent, no minute consumed.
l5_reset();
$GLOBALS['wp_test_caps'] = array( 'manage_woocommerce' => false );
l5_check( str_starts_with( $redirect(), 'wp_die' ) && 0 === $mails() && false === get_transient( Capi_Health_Alert_Test::LOCK ), 'Without manage_woocommerce: refused, no e-mail, no throttle consumed.' );
l5_check( '' === $screen(), 'Without manage_woocommerce: the block is not displayed.' );
$GLOBALS['wp_test_caps'] = array();

// Empty or invalid address: no e-mail, explicit message, no throttle consumed.
foreach ( array( '', '   ', 'pas-une-adresse' ) as $bad ) {
	l5_reset( array( 'famma_capi_alert_email' => $bad ) );
	$location = $redirect();
	l5_check( str_contains( $location, Capi_Health_Alert_Test::RESULT . '=empty' ) && 0 === $mails() && false === get_transient( Capi_Health_Alert_Test::LOCK ), 'Address « ' . $bad . ' »: result « empty », no e-mail, no throttle consumed.' );
}
l5_check( str_contains( $screen( 'empty' ), 'Aucune adresse des alertes valide' ), 'Empty address: the screen says so.' );

// Sent: saved address, marked « essai », no token, no customer data.
l5_reset( array( 'famma_capi_alert_email' => 'alertes@qa.invalid' ) );
$GLOBALS['wp_test_options']['famma_meta_access_token'] = $token;
$_POST['famma_capi_alert_email']                       = 'autre@qa.invalid';
$before   = $snapshot();
$location = $redirect();
$_POST    = array();
l5_check( str_contains( $location, Capi_Health_Alert_Test::RESULT . '=sent' ) && str_contains( $location, 'page=famma-tracking' ) && 1 === $mails(), 'Sent: one e-mail, back on FAMMA → Mesure with result « sent ».' );
list( $to, $subject, $body ) = $GLOBALS['wp_test_mail'][0];
l5_check( 'alertes@qa.invalid' === $to, 'The e-mail goes to the saved address, not to an address posted with the request.' );
l5_check( str_contains( strtolower( $subject ), '(essai)' ) && str_starts_with( $body, 'ESSAI' ), 'Subject and first word of the body say « essai ».' );
l5_check( ! str_contains( $subject . $body, $token ) && ! str_contains( $subject . $body, 'QA-L5' ) && ! str_contains( strtolower( $body ), 'access_token' ), 'The e-mail never contains the token.' );
l5_check( str_contains( $body, 'admin.php?page=famma-tracking' ) && ! str_contains( $body, '- ' ) && ! preg_match( '/#\d|commande n|@|\+?216\d{8}/i', str_replace( 'alertes@qa.invalid', '', $body ) ), 'Same template as the real alerts (link to Mesure), no reason line, no order number, e-mail or phone.' );
l5_check( $before === $snapshot(), 'The test e-mail does not touch the state of the real alerts (last mail, codes, red flag).' );
l5_check( str_contains( $screen( 'sent' ), 'notice-success' ) && str_contains( $screen( 'sent' ), 'E-mail d’essai envoyé' ), 'Success message displayed.' );

// Throttle: one test per minute, then allowed again.
$location = $redirect();
l5_check( str_contains( $location, Capi_Health_Alert_Test::RESULT . '=throttled' ) && 1 === $mails(), 'Second test within the minute: « throttled », no second e-mail.' );
l5_check( 60 === Capi_Health_Alert_Test::INTERVAL && str_contains( $screen( 'throttled' ), 'notice-warning' ), 'Interval is 60 s; the screen explains the wait.' );
unset( $GLOBALS['wp_test_transients'][ Capi_Health_Alert_Test::LOCK ] );
l5_check( str_contains( $redirect(), '=sent' ) && 2 === $mails(), 'After the minute (transient expired): a new test is sent.' );

// Mail server refusing: explicit failure, and the minute is consumed anyway.
l5_reset();
$GLOBALS['wp_test_mail_fails'] = true;
$location                      = $redirect();
$again                         = $redirect();
$GLOBALS['wp_test_mail_fails'] = false;
l5_check( str_contains( $location, '=failed' ) && 1 === count( $GLOBALS['wp_test_mail_failed'] ?? array() ) && str_contains( $again, '=throttled' ), 'wp_mail() refuses: result « failed », and no retry storm within the minute.' );
l5_check( str_contains( $screen( 'failed' ), 'notice-error' ) && str_contains( $screen( 'failed' ), 'Échec' ), 'Failure message displayed.' );

// Screen: separate form to admin-post.php with the nonce; unknown or hostile result shows nothing.
$html = $screen();
l5_check( 1 === substr_count( $html, '<form' ) && str_contains( $html, 'admin-post.php' ) && str_contains( $html, 'value="' . Capi_Health_Alert_Test::ACTION . '"' ) && str_contains( $html, 'nonce-' . Capi_Health_Alert_Test::ACTION ) && str_contains( $html, 'Envoyer un e-mail d’essai' ), 'Block: one separate form, admin-post.php, action, nonce and the « Envoyer un e-mail d’essai » button.' );
l5_check( ! str_contains( $screen( '<script>alert(1)</script>' ), 'script' ) && ! str_contains( $screen( 'inconnu' ), 'class="notice' ), 'Unknown or hostile result parameter: no message, nothing printed raw.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
