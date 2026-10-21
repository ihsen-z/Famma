<?php
/**
 * Lot 5, A13 (D7, T1.4) and A9 (D5, T2.5) — user_data normalised as in the Meta
 * « Customer Information Parameters » page; action_source of a browserless order.
 * Run with: php scripts/tests-unit/test-capi-user-data.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi;
use Famma\Core\Capi_User_Data;

$name_cases = array(
	'Élodie'      => 'élodie',
	'ÉLODIE'      => 'élodie',
	'  Test  '    => 'test',
	'Ben-Salah'   => 'bensalah',
	"O'Neil"      => 'oneil',
	'محمد'        => 'محمد',
	'الطرابلسي'   => 'الطرابلسي',
	'Jean  Marie' => 'jeanmarie',
);
foreach ( $name_cases as $input => $expected ) {
	l5_check( Capi_User_Data::name( $input ) === $expected, 'fn/ln: « ' . $input . ' » → « ' . $expected . ' » (UTF-8 lowercase, no punctuation, no space since 0.3.4).' );
}
$cities = array(
	'Médenine'      => 'médenine',
	'Ben Arous'     => 'benarous',
	'مدنين'         => 'مدنين',
	'Sousse'        => 'sousse',
	'Sidi-Bou Saïd' => 'sidibousaïd',
	'Béja'          => 'béja',
);
foreach ( $cities as $input => $expected ) {
	l5_check( Capi_User_Data::city( $input ) === $expected, 'ct: « ' . $input . ' » → « ' . $expected . ' » (no space, no punctuation, letters kept).' );
}
l5_check( 'tn82' === Capi_User_Data::state( 'TN-82', 'TN' ) && 'tn51' === Capi_User_Data::state( 'TN-51', 'TN' ), 'st: governorate code without dash (TN-82 → tn82).' );
l5_check( 'tn' === Capi_User_Data::country( 'TN' ) && '' === Capi_User_Data::country( 'Tunisie' ), 'country: ISO 3166-1 alpha-2 in lowercase, otherwise omitted.' );
l5_check( '21622123456' === Capi_User_Data::phone( '+216 22 123 456' ) && '21622123456' === Capi_User_Data::phone( '٢٢١٢٣٤٥٦' ), 'ph: unchanged rule (216 + 8 digits).' );
l5_check( 'client.test@example.invalid' === Capi_User_Data::email( '  Client.Test@Example.INVALID ' ), 'em: trimmed and lowercased.' );
add_filter(
	'famma_capi_state_value',
	static function ( string $value, string $raw ): string {
		return 'TN-82' === $raw ? 'medenine' : $value;
	},
	10,
	2
);
l5_check( 'medenine' === Capi_User_Data::state( 'TN-82', 'TN' ), 'st form can be changed with the famma_capi_state_value filter.' );
$GLOBALS['wp_test_hooks']['famma_capi_state_value'] = array();

// Hashes in the block actually sent.
$wc_order             = new WC_Order( 5800 );
$wc_order->first_name = 'ÉLODIE';
$wc_order->city       = 'Médenine';
$wc_order->state      = 'TN-82';
$wc_order->country    = 'TN';
$wc_order->meta       = array( '_famma_client_ua' => 'Mozilla/5.0 (QA-L5)' );
$data                 = Capi_User_Data::build( $wc_order, true );
l5_check( hash( 'sha256', 'élodie' ) === $data['fn'] && hash( 'sha256', 'médenine' ) === $data['ct'] && hash( 'sha256', 'tn82' ) === $data['st'] && hash( 'sha256', 'tn' ) === $data['country'], 'The sent block hashes the normalised values.' );
l5_check( 'Mozilla/5.0 (QA-L5)' === $data['client_user_agent'] && ! isset( Capi_User_Data::build( $wc_order, false )['client_user_agent'] ), 'client_user_agent only for a website event.' );

// A9 / T2.5: an order without user agent (created in the admin) is not « website ».
l5_reset();
$website       = new WC_Order( 5801 );
$website->meta = array( '_famma_client_ua' => 'Mozilla/5.0 (QA-L5)' );
Capi::instance()->fire_purchase_on_status( 5801 );
$event = json_decode( (string) $GLOBALS['wp_test_http'][0]['args']['body'], true )['data'][0];
l5_check( 'website' === $event['action_source'] && 'https://qa.invalid/commande/' === $event['event_source_url'] && isset( $event['user_data']['client_user_agent'] ), 'Browser order: action_source website, event_source_url and user agent sent.' );
$admin              = new WC_Order( 5802 );
$admin->created_via = 'admin';
Capi::instance()->fire_purchase_on_status( 5802 );
$event = json_decode( (string) $GLOBALS['wp_test_http'][1]['args']['body'], true )['data'][0];
l5_check( 'other' === $event['action_source'] && ! isset( $event['event_source_url'] ) && ! isset( $event['user_data']['client_user_agent'] ), 'T2.5: admin order without user agent → action_source « other » (Server Event Parameters), no event_source_url.' );
add_filter(
	'famma_capi_action_source',
	static function (): string {
		return 'phone_call';
	}
);
$phone              = new WC_Order( 5803 );
$phone->created_via = 'admin';
Capi::instance()->fire_purchase_on_status( 5803 );
$event = json_decode( (string) $GLOBALS['wp_test_http'][2]['args']['body'], true )['data'][0];
l5_check( 'phone_call' === $event['action_source'], 'The off-site source can be set by the famma_capi_action_source filter (e.g. phone_call).' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
