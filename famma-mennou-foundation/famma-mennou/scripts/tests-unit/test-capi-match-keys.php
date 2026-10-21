<?php
/**
 * 0.3.4 (D1 to D7 of the EMQ report of 21/10/2026) — names without spaces nor
 * civility, postcode, shipping city and fbc rebuilt from the WooCommerce order
 * attribution, in the user_data block actually sent for OrderPlaced and Purchase.
 * Run with: php scripts/tests-unit/test-capi-match-keys.php
 *
 * References: Meta « Customer Information Parameters » and « fbp and fbc
 * Parameters »; fbevents.js 2.9.426 and Parameter Builder (getNormalizedName);
 * WooCommerce 11.0.1 OrderAttributionMeta (session_entry, session_start_time in UTC).
 * Synthetic identities only (fictitious names, synthetic fbclid values).
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi;
use Famma\Core\Capi_Click_Id;
use Famma\Core\Capi_User_Data;

$sha = static fn( string $plain ): string => hash( 'sha256', $plain );

// D1: whitespace stripped like the pixel and the Parameter Builder, no transliteration.
$name_cases = array(
	'Leila'            => 'leila',
	'Ben Salah'        => 'bensalah',
	'Ben Ali Trabelsi' => 'benalitrabelsi',
	'Ben-Salah'        => 'bensalah',
	"Ben\u{00A0}Salah" => 'bensalah',
	' Ben   Salah '    => 'bensalah',
	'بن صالح'          => 'بنصالح',
	'Élodie Marie'     => 'élodiemarie',
);
if ( class_exists( '\Normalizer' ) ) {
	// NFC composition only exists with the intl extension (Capi_User_Data::lower()).
	$name_cases["E\u{0301}lodie"] = 'élodie';
}
foreach ( $name_cases as $input => $expected ) {
	l5_check( Capi_User_Data::name( $input ) === $expected, 'D1 fn/ln: « ' . $input . ' » → « ' . $expected . ' ».' );
}

// D2: civility removed in the block sent to Meta only, never guessed nor expanded.
$civility_cases = array(
	array( 'Mme', 'Leila Ben Ali', 'Leila', 'Ben Ali', 'checkout split « Mme Leila Ben Ali »' ),
	array( 'Mme Leila', 'Ben Ali', 'Leila', 'Ben Ali', 'civility typed in the first name' ),
	array( 'MME.', 'Leila', '', 'Leila', 'case and final dot ignored, one word left = last name (F1)' ),
	array( 'M.', 'Sami Ben Salah', 'Sami', 'Ben Salah', 'M. abbreviation' ),
	array( 'Dr', 'Sami', '', 'Sami', 'Dr, one word left = last name (F1)' ),
	array( 'Mlle', 'Sarra Trabelsi', 'Sarra', 'Trabelsi', 'Mlle' ),
	array( 'Melle', 'Sarra', '', 'Sarra', 'Melle, one word left = last name (F1)' ),
	array( 'Mr', 'John Smith', 'John', 'Smith', 'Mr' ),
	array( 'Mrs.', 'Jane Smith', 'Jane', 'Smith', 'Mrs.' ),
	array( 'Ms', 'Jane', '', 'Jane', 'Ms, one word left = last name (F1)' ),
	array( 'السيدة', 'ليلى بن علي', 'ليلى', 'بن علي', 'Arabic السيدة' ),
	array( 'السيد', 'محمد', '', 'محمد', 'Arabic السيد, one word left = last name (F1)' ),
	array( 'الآنسة', 'سارة', '', 'سارة', 'Arabic الآنسة, one word left = last name (F1)' ),
	array( 'Mme', '', 'Mme', '', 'civility alone: unchanged' ),
	array( '  Mme  ', '   ', '  Mme  ', '   ', 'civility alone with spaces: unchanged' ),
	array( 'Leila', 'Mme', 'Leila', 'Mme', 'civility not in first position: unchanged' ),
	array( 'Mohamed', 'Ben Ali', 'Mohamed', 'Ben Ali', 'no civility: unchanged' ),
	array( 'Mmed', 'Ben Ali', 'Mmed', 'Ben Ali', 'abbreviation outside the list: unchanged, never expanded' ),
	array( 'Mme.Leila', 'Ben Ali', 'Mme.Leila', 'Ben Ali', 'no space after the civility: unchanged' ),
);
foreach ( $civility_cases as $civility_case ) {
	list( $first, $last, $want_first, $want_last, $label ) = $civility_case;
	l5_check( array( $want_first, $want_last ) === Capi_User_Data::without_civility( $first, $last ), 'D2 ' . $label . ' → « ' . $want_first . ' » / « ' . $want_last . ' ».' );
}
$titled             = new WC_Order( 6400 );
$titled->first_name = 'Mme';
$titled->last_name  = 'Leila Ben Ali';
$titled_data        = Capi_User_Data::build( $titled, true );
l5_check( $sha( 'leila' ) === $titled_data['fn'] && $sha( 'benali' ) === $titled_data['ln'], 'D2 sent block: fn = leila, ln = benali for « Mme » / « Leila Ben Ali ».' );
l5_check( 'Mme' === $titled->get_billing_first_name() && 'Leila Ben Ali' === $titled->get_billing_last_name() && array() === $titled->meta, 'D2 order data never modified (names and metadata untouched).' );
$alone             = new WC_Order( 6401 );
$alone->first_name = 'Mme';
$alone_data        = Capi_User_Data::build( $alone, true );
l5_check( $sha( 'mme' ) === $alone_data['fn'] && ! isset( $alone_data['ln'] ), 'D2 civility alone: sent unchanged, no ln invented.' );

// D4: postcode, billing then shipping, only when entered; never deduced.
$postcodes = array(
	'4000'       => '4000',
	' 40 00 '    => '4000',
	'4000-'      => '4000',
	'12345-6789' => '12345',
	'94035'      => '94035',
	'M1 1AE'     => 'm11ae',
	'AB-12 3'    => 'ab123',
	''           => '',
	'   '        => '',
	'٤٠٠٠'       => '',
);
foreach ( $postcodes as $input => $expected ) {
	l5_check( Capi_User_Data::postcode( (string) $input ) === $expected, 'D4 zp: « ' . $input . ' » → « ' . ( '' === $expected ? '(omis)' : $expected ) . ' ».' );
}
$zip_billing                    = new WC_Order( 6410 );
$zip_billing->postcode          = '4000';
$zip_billing->shipping_postcode = '4051';
l5_check( $sha( '4000' ) === ( Capi_User_Data::build( $zip_billing, true )['zp'] ?? '' ), 'D4 billing postcode first.' );
$zip_shipping                    = new WC_Order( 6411 );
$zip_shipping->postcode          = '  ';
$zip_shipping->shipping_postcode = ' 4051 ';
l5_check( $sha( '4051' ) === ( Capi_User_Data::build( $zip_shipping, true )['zp'] ?? '' ), 'D4 empty billing postcode → shipping postcode.' );
$zip_none          = new WC_Order( 6412 );
$zip_none->city    = 'Sousse';
$zip_none->state   = 'TN-51';
$zip_none->country = 'TN';
l5_check( ! array_key_exists( 'zp', Capi_User_Data::build( $zip_none, true ) ), 'D4 no postcode entered → no zp, never deduced from the city or the governorate.' );

// D5: city, billing then shipping; normalisation unchanged (accents kept).
$city_shipping                = new WC_Order( 6420 );
$city_shipping->shipping_city = 'Sidi Bou Saïd';
l5_check( $sha( 'sidibousaïd' ) === ( Capi_User_Data::build( $city_shipping, true )['ct'] ?? '' ), 'D5 empty billing city → shipping city, accents kept.' );
$city_both                = new WC_Order( 6421 );
$city_both->city          = 'Médenine';
$city_both->shipping_city = 'Sousse';
l5_check( $sha( 'médenine' ) === Capi_User_Data::build( $city_both, true )['ct'], 'D5 billing city first.' );
$city_blank                = new WC_Order( 6422 );
$city_blank->city          = '   ';
$city_blank->shipping_city = 'Béja';
l5_check( $sha( 'béja' ) === Capi_User_Data::build( $city_blank, true )['ct'], 'D5 billing city made of spaces counts as empty.' );
l5_check( ! array_key_exists( 'ct', Capi_User_Data::build( new WC_Order( 6423 ), true ) ), 'D5 no city at all → no ct.' );

// D6: fbc rebuilt from the order attribution when the _fbc cookie is missing.
$start_utc = '2026-10-19 09:00:00';
$start_ms  = '1792400400000';
$fbclid    = 'IwAR2F4-dbP0l7Mn1_QaSyntheticMiXeDcase';
$entry     = 'https://fammaminnou.tn/produit/ceinture/?utm_source=facebook&fbclid=' . $fbclid . '&utm_medium=paid';
$expected  = 'fb.1.' . $start_ms . '.' . $fbclid;

l5_check( 1792400400 === Capi_Click_Id::start_time( $start_utc ), 'D6 session_start_time read as UTC.' );
$default_zone = date_default_timezone_get();
foreach ( array( 'Africa/Tunis', 'America/New_York', 'Pacific/Kiritimati' ) as $zone ) {
	date_default_timezone_set( $zone ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set -- Proves the reading does not depend on the PHP time zone.
	l5_check( 1792400400 === Capi_Click_Id::start_time( $start_utc ), 'D6 same timestamp whatever the PHP time zone (' . $zone . ').' );
}
date_default_timezone_set( $default_zone ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set -- Restores the CLI default.
foreach ( array( '2026-10-19T09:00:00Z', '2026-02-30 09:00:00', '2026-10-19 9:00:00', '19/10/2026 09:00:00', 'abc', '' ) as $bad_start ) {
	l5_check( null === Capi_Click_Id::start_time( $bad_start ), 'D6 malformed session_start_time refused: « ' . $bad_start . ' ».' );
}

l5_check( Capi_Click_Id::fbclid( $entry ) === $fbclid, 'D6 fbclid read from the entry URL, case kept.' );
$fbclid_cases = array(
	'https://fammaminnou.tn/'                     => 'no query',
	'https://fammaminnou.tn/?utm_source=facebook' => 'no fbclid',
	'https://fammaminnou.tn/?fbclid='             => 'empty fbclid',
	'https://fammaminnou.tn/?fbclid=Iw%2BAR'      => 'forbidden character (+)',
	'https://fammaminnou.tn/?fbclid=Iw.AR'        => 'forbidden character (.)',
	'https://fammaminnou.tn/?fbclid[]=IwAR'       => 'array instead of a string',
	'https://fammaminnou.tn/?fbclid=' . str_repeat( 'a', 501 ) => 'longer than 500 characters',
	'not a url at all'                            => 'not a URL',
);
foreach ( $fbclid_cases as $bad_entry => $label ) {
	l5_check( '' === Capi_Click_Id::fbclid( $bad_entry ), 'D6 fbclid refused: ' . $label . '.' );
}
l5_check( str_repeat( 'a', 500 ) === Capi_Click_Id::fbclid( 'https://fammaminnou.tn/?fbclid=' . str_repeat( 'a', 500 ) ), 'D6 fbclid of 500 characters accepted (cookie limit).' );

$order_time = 1792404000; // 2026-10-19 10:00:00 UTC.
l5_check( Capi_Click_Id::build( $entry, $start_utc, $order_time ) === $expected, 'D6 fbc = fb.1.<session start in ms>.<exact fbclid>.' );
l5_check( Capi_Click_Id::build( $entry, '2026-10-19 10:00:01', $order_time ) === 'fb.1.' . ( $order_time * 1000 ) . '.' . $fbclid, 'D6, F3 session starting 1 s after the order (phone clock ahead) → creationTime = order creation.' );
l5_check( Capi_Click_Id::build( $entry, $start_utc, 1792400400 ) === $expected, 'D6 session started at the order second → fbc kept.' );
l5_check( '' === Capi_Click_Id::build( $entry, '2026-07-21 09:59:59', $order_time ), 'D6 fbclid older than 90 days (cookie lifetime) → no fbc.' );
l5_check( '' !== Capi_Click_Id::build( $entry, '2026-07-21 10:00:00', $order_time ), 'D6 fbclid exactly 90 days old → fbc kept.' );

// Through the real Capi_User_Data::build(), from the order metadata.
$attributed             = new WC_Order( 6430 );
$attributed->created_at = new DateTimeImmutable( '2026-10-19 10:00:00', new DateTimeZone( 'UTC' ) );
$attributed->meta       = array(
	Capi_Click_Id::ENTRY_META => $entry,
	Capi_Click_Id::START_META => $start_utc,
	'_famma_client_ua'        => 'Mozilla/5.0 (QA-0.3.4)',
);
$attributed_block       = Capi_User_Data::build( $attributed, true );
$attributed_offsite     = Capi_User_Data::build( $attributed, false );
l5_check( ( $attributed_block['fbc'] ?? '' ) === $expected && ( $attributed_offsite['fbc'] ?? '' ) === $expected, 'D6 no _fbc cookie → fbc rebuilt from the attribution, same value for every build.' );
l5_check( ! isset( $attributed->meta['_famma_fbc'] ), 'D6 the rebuilt fbc is never written on the order.' );

$cookie_first             = new WC_Order( 6431 );
$cookie_first->created_at = $attributed->created_at;
$cookie_first->meta       = $attributed->meta + array( '_famma_fbc' => 'fb.1.1792399000000.CookieFbclidWins' );
l5_check( 'fb.1.1792399000000.CookieFbclidWins' === Capi_User_Data::build( $cookie_first, true )['fbc'], 'D6 a valid _fbc cookie stays first.' );
$cookie_bad             = new WC_Order( 6432 );
$cookie_bad->created_at = $attributed->created_at;
$cookie_bad->meta       = $attributed->meta + array( '_famma_fbc' => 'fb.1.forged' );
l5_check( Capi_User_Data::build( $cookie_bad, true )['fbc'] === $expected, 'D6 a stored _fbc refused by the cookie rules falls back on the attribution.' );

foreach ( array(
	'entry missing'          => array( Capi_Click_Id::START_META => $start_utc ),
	'start missing'          => array( Capi_Click_Id::ENTRY_META => $entry ),
	'entry without fbclid'   => array(
		Capi_Click_Id::ENTRY_META => 'https://fammaminnou.tn/?utm_source=facebook',
		Capi_Click_Id::START_META => $start_utc,
	),
	'invalid fbclid'         => array(
		Capi_Click_Id::ENTRY_META => 'https://fammaminnou.tn/?fbclid=Iw%2BAR',
		Capi_Click_Id::START_META => $start_utc,
	),
	'invalid start'          => array(
		Capi_Click_Id::ENTRY_META => $entry,
		Capi_Click_Id::START_META => '2026-10-19T09:00:00+01:00',
	),
	'attribution « (none) »' => array(
		Capi_Click_Id::ENTRY_META => '(none)',
		Capi_Click_Id::START_META => '(none)',
	),
) as $label => $meta ) {
	$partial             = new WC_Order( 6440 );
	$partial->created_at = $attributed->created_at;
	$partial->meta       = $meta;
	l5_check( ! array_key_exists( 'fbc', Capi_User_Data::build( $partial, true ) ), 'D6 ' . $label . ' → no fbc.' );
}

// OrderPlaced then Purchase through the real Capi: same fbc, journal says fbc=oui, never the value.
l5_reset();
$capi                = Capi::instance();
$journey             = new WC_Order( 6450 );
$journey->created_at = $attributed->created_at;
$journey->status     = 'pending-confirm';
$journey->first_name = 'Mme';
$journey->last_name  = 'Leila Ben Ali';
$journey->postcode   = '4000';
$journey->meta       = $attributed->meta;
$capi->order_placed_after_checkout( array( 'result' => 'success' ), 6450 );
do_action( 'famma_capi_orderplaced_send', 6450 );
$journey->status = 'delivered';
$capi->fire_purchase_on_status( 6450 );
$sent = array();
foreach ( $GLOBALS['wp_test_http'] as $request ) {
	$event                        = json_decode( (string) $request['args']['body'], true )['data'][0];
	$sent[ $event['event_name'] ] = $event['user_data'];
}
l5_check( isset( $sent['OrderPlaced'], $sent['Purchase'] ), 'OrderPlaced and Purchase both sent (' . implode( ', ', array_keys( $sent ) ) . ').' );
l5_check( ( $sent['OrderPlaced']['fbc'] ?? '' ) === $expected && ( $sent['Purchase']['fbc'] ?? '' ) === $expected, 'D6 OrderPlaced and Purchase carry the same rebuilt fbc.' );
l5_check( $sha( 'leila' ) === $sent['Purchase']['fn'] && $sha( 'benali' ) === $sent['Purchase']['ln'] && $sha( '4000' ) === $sent['Purchase']['zp'], 'D1, D2, D4 in the Purchase actually sent.' );
$lines  = l5_capi_lines();
$joined = implode( "\n", $lines );
l5_check( 2 <= count( $lines ) && str_contains( $joined, 'fbc=oui' ) && ! str_contains( $joined, $fbclid ) && ! str_contains( $joined, $start_ms ), 'D6 journal: fbc=oui, never the fbclid nor the fbc value.' );

// Review of 21/10 (J1 to J6, m2): each case below failed before the correction.

// J4: invisible format characters (RLM, ZWNJ, ZWSP, ZWJ, BOM) and emoji variation selectors removed.
$invisible_cases = array(
	"\u{200F}بن صالح"                 => 'بنصالح',
	"بن\u{200C}صالح"                  => 'بنصالح',
	"Ben\u{200B}Salah"                => 'bensalah',
	"Leila \u{2764}\u{FE0F}"          => 'leila',
	"Leila\u{1F469}\u{200D}\u{1F4BB}" => 'leila',
	"\u{FEFF}Leila"                   => 'leila',
	"Ben\u{200E} Ali"                 => 'benali',
);
foreach ( $invisible_cases as $input => $expected ) {
	l5_check( Capi_User_Data::name( $input ) === $expected, 'J4 fn/ln: « ' . bin2hex( $input ) . ' » → « ' . $expected . ' » (no invisible character left).' );
}
l5_check( array( '', 'ليلى' ) === Capi_User_Data::without_civility( "\u{200F}السيدة ليلى", '' ), 'J4 a leading RLM does not hide the civility (one word left = last name, F1).' );
l5_check( array( 'Leila', 'Ben Ali' ) === Capi_User_Data::without_civility( "Mme\u{200B} Leila", 'Ben Ali' ), 'J4 a zero-width space after the civility does not hide it.' );

// J2, m2: a postcode is never sent truncated, nor with fewer than 2 characters (pixel and Parameter Builder rule).
$strict_postcodes = array(
	'4٠00'         => '',
	'٤000'         => '',
	'40é0'         => '',
	'4'            => '',
	'0'            => '',
	' 4 '          => '',
	"40\u{00A0}00" => '4000',
	"\u{200F}4000" => '4000',
	'1002.'        => '1002',
	'12345 - 6789' => '12345',
	'40'           => '40',
);
foreach ( $strict_postcodes as $input => $expected ) {
	l5_check( Capi_User_Data::postcode( (string) $input ) === $expected, 'J2/m2 zp: « ' . $input . ' » → « ' . ( '' === $expected ? '(omis)' : $expected ) . ' ».' );
}

// J1: the shipping fallback is decided on the normalised value, not on an ASCII trim().
foreach ( array( "\u{00A0}", '-', '.', ' . ' ) as $blank_city ) {
	$city_noise                = new WC_Order( 6460 );
	$city_noise->city          = $blank_city;
	$city_noise->shipping_city = 'Sousse';
	l5_check( $sha( 'sousse' ) === ( Capi_User_Data::build( $city_noise, true )['ct'] ?? '' ), 'J1 billing city « ' . bin2hex( $blank_city ) . ' » normalises to nothing → shipping city.' );
}
foreach ( array( '-', "\u{00A0}", '٤٠٠٠', '4', '4٠00' ) as $blank_postcode ) {
	$zip_noise                    = new WC_Order( 6461 );
	$zip_noise->postcode          = $blank_postcode;
	$zip_noise->shipping_postcode = '4000';
	l5_check( $sha( '4000' ) === ( Capi_User_Data::build( $zip_noise, true )['zp'] ?? '' ), 'J1 billing postcode « ' . $blank_postcode . ' » normalises to nothing → shipping postcode.' );
}
$zip_kept                    = new WC_Order( 6462 );
$zip_kept->postcode          = '4000';
$zip_kept->shipping_postcode = '٤٠٠٠';
l5_check( $sha( '4000' ) === ( Capi_User_Data::build( $zip_kept, true )['zp'] ?? '' ), 'J1 a valid billing postcode still wins.' );

// J3: duplicated fbclid → the first one wins, like URLSearchParams.get() in the _fbc script and the pixel.
$duplicate_cases = array(
	'https://fammaminnou.tn/p/?fbclid=AAA&fbclid=BBB'    => 'AAA',
	'https://fammaminnou.tn/p/?utm_source=fb&fbclid=AAA&fbclid=BBB' => 'AAA',
	'https://fammaminnou.tn/p/?fbclid=Iw.bad&fbclid=BBB' => '',
	'https://fammaminnou.tn/p/?fbclid=&fbclid=BBB'       => '',
	'https://fammaminnou.tn/p/?fbclid[]=AAA&fbclid=BBB'  => 'BBB',
	'https://fammaminnou.tn/p/?xfbclid=AAA&fbclid=BBB'   => 'BBB',
	'https://fammaminnou.tn/p/?fb%63lid=AAA&fbclid=BBB'  => 'AAA',
	'https://fammaminnou.tn/p/?fbclid=AAA#fbclid=BBB'    => 'AAA',
	'https://fammaminnou.tn/p/?a=1&&fbclid=AbC_d-E'      => 'AbC_d-E',
);
foreach ( $duplicate_cases as $dup_entry => $want ) {
	l5_check( Capi_Click_Id::fbclid( $dup_entry ) === $want, 'J3 fbclid of « ' . $dup_entry . ' » → « ' . ( '' === $want ? '(none)' : $want ) . ' ».' );
}

// J6: the reconciliation screen says « fbc present » when a rebuilt fbc is actually sent.
$reconcile_now = 1792404000;
l5_check( true === \Famma\Core\Capi_Reconcile::instance()->row( $attributed, array(), $reconcile_now )['fbc'], 'J6 reconciliation: fbc rebuilt from the attribution → « oui ».' );
l5_check( true === \Famma\Core\Capi_Reconcile::instance()->row( $cookie_first, array(), $reconcile_now )['fbc'], 'J6 reconciliation: _fbc cookie → « oui ».' );
$no_fbc       = new WC_Order( 6470 );
$no_fbc->meta = array( '_famma_fbc' => 'fb.1.forged' );
l5_check( false === \Famma\Core\Capi_Reconcile::instance()->row( $no_fbc, array(), $reconcile_now )['fbc'], 'J6 reconciliation: stored _fbc refused by the cookie rules and no attribution → « non », as nothing is sent.' );

// Finishing touches of 21/10 (F1 to F3): each case below failed before the correction.

// F1: a civility followed by ONE word → that word is the last name, no first name sent.
$one_word_cases = array(
	array( 'Mme Trabelsi', '', '', 'Trabelsi', 'civility and one word in the first name' ),
	array( 'M. Ali', '', '', 'Ali', '« M. Ali » in the first name' ),
	array( 'Mme', 'Trabelsi', '', 'Trabelsi', 'checkout split « Mme Trabelsi »' ),
	array( 'M.', 'Ben', '', 'Ben', 'checkout split « M. Ben »' ),
	array( 'Mme Trabelsi', '   ', '', 'Trabelsi', 'last name made of spaces' ),
	array( "Mme\u{200F}", "\u{200F}Trabelsi", '', 'Trabelsi', 'direction marks around the words' ),
	array( 'السيدة', 'الطرابلسي', '', 'الطرابلسي', 'Arabic civility and one word' ),
	array( 'Mme', 'Ben Ali', 'Ben', 'Ali', 'two words left: unchanged, first word = fn, rest = ln' ),
	array( 'Mme Leila', 'Trabelsi', 'Leila', 'Trabelsi', 'two words left across both fields: unchanged' ),
	array( 'Mme', '', 'Mme', '', 'civility alone: still unchanged' ),
);
foreach ( $one_word_cases as $one_word_case ) {
	list( $first, $last, $want_first, $want_last, $label ) = $one_word_case;
	l5_check( array( $want_first, $want_last ) === Capi_User_Data::without_civility( $first, $last ), 'F1 ' . $label . ' → « ' . $want_first . ' » / « ' . $want_last . ' ».' );
}
$surname             = new WC_Order( 6480 );
$surname->first_name = 'Mme';
$surname->last_name  = 'Trabelsi';
$surname_data        = Capi_User_Data::build( $surname, true );
l5_check( ! isset( $surname_data['fn'] ) && $sha( 'trabelsi' ) === ( $surname_data['ln'] ?? '' ), 'F1 sent block for « Mme » / « Trabelsi »: ln = trabelsi, no fn.' );
l5_check( 'Mme' === $surname->get_billing_first_name() && 'Trabelsi' === $surname->get_billing_last_name() && array() === $surname->meta, 'F1 order data never modified.' );
$mister             = new WC_Order( 6481 );
$mister->first_name = 'M. Ali';
$mister_data        = Capi_User_Data::build( $mister, true );
l5_check( ! isset( $mister_data['fn'] ) && $sha( 'ali' ) === ( $mister_data['ln'] ?? '' ), 'F1 sent block for « M. Ali »: ln = ali, no fn.' );

// F2: input variants of the same civilities.
$variant_cases = array(
	array( 'الانسة', 'سارة بن علي', 'سارة', 'بن علي', 'الانسة written without hamza' ),
	array( 'السيده', 'ليلى بن علي', 'ليلى', 'بن علي', 'السيده with ta marbuta written ha' ),
	array( 'Mme,', 'Leila Ben Ali', 'Leila', 'Ben Ali', 'comma stuck to the civility' ),
	array( 'Mme, Leila', 'Ben Ali', 'Leila', 'Ben Ali', '« Mme, Leila »' ),
	array( 'Dr.', 'Ben Salah', 'Ben', 'Salah', '« Dr. Ben Salah » split at checkout' ),
	array( 'M.,', 'Sami Ben Ali', 'Sami', 'Ben Ali', 'dot and comma stuck to the civility' ),
	array( 'Mme,Leila', 'Ben Ali', 'Mme,Leila', 'Ben Ali', 'no space after the comma: unchanged' ),
	array( 'السيده', 'الطرابلسي', '', 'الطرابلسي', 'variant then one word: last name (F1)' ),
);
foreach ( $variant_cases as $variant_case ) {
	list( $first, $last, $want_first, $want_last, $label ) = $variant_case;
	l5_check( array( $want_first, $want_last ) === Capi_User_Data::without_civility( $first, $last ), 'F2 ' . $label . ' → « ' . $want_first . ' » / « ' . $want_last . ' ».' );
}

// Final check of the finishing touches: Arabic comma (F2) and punctuation-only words (F1).
$final_cases = array(
	array( 'السيدة،', 'ليلى بن علي', 'ليلى', 'بن علي', 'F2 Arabic comma stuck to the civility' ),
	array( 'السيدة، ليلى', 'بن علي', 'ليلى', 'بن علي', 'F2 « السيدة، ليلى »' ),
	array( 'السيد،', 'الطرابلسي', '', 'الطرابلسي', 'F2 Arabic comma, then one word: last name (F1)' ),
	array( 'السيدة،ليلى', 'بن علي', 'السيدة،ليلى', 'بن علي', 'F2 no space after the Arabic comma: unchanged' ),
	array( 'M . Ali', '', '', 'Ali', 'F1 isolated dot is not a word' ),
	array( 'Mme , Leila', '', '', 'Leila', 'F1 isolated comma is not a word' ),
	array( 'السيدة ، ليلى', '', '', 'ليلى', 'F1 isolated Arabic comma is not a word' ),
	array( 'Mme , Leila', 'Ben Ali', 'Leila', 'Ben Ali', 'F1 isolated comma, two words left: fn, ln' ),
	array( 'Mme -', 'Trabelsi', '', 'Trabelsi', 'F1 isolated dash, one word left: last name' ),
	array( 'Mme -', 'Leila Ben', 'Leila', 'Ben', 'F1 isolated dash, first name only the civility: fn = first word of the last name' ),
	array( 'Mme -', '', 'Mme -', '', 'F1 civility followed by punctuation only = civility alone: unchanged' ),
	array( 'Mme Leila', '', '', 'Leila', 'F1 convention: a first name typed alone after a civility goes to ln' ),
);
foreach ( $final_cases as $final_case ) {
	list( $first, $last, $want_first, $want_last, $label ) = $final_case;
	l5_check( array( $want_first, $want_last ) === Capi_User_Data::without_civility( $first, $last ), $label . ' → « ' . $want_first . ' » / « ' . $want_last . ' ».' );
}
$arabic_comma             = new WC_Order( 6485 );
$arabic_comma->first_name = 'السيدة،';
$arabic_comma->last_name  = 'ليلى بن علي';
$arabic_comma_data        = Capi_User_Data::build( $arabic_comma, true );
l5_check( $sha( 'ليلى' ) === ( $arabic_comma_data['fn'] ?? '' ) && $sha( 'بنعلي' ) === ( $arabic_comma_data['ln'] ?? '' ), 'F2 sent block for « السيدة، » / « ليلى بن علي »: fn = ليلى, ln = بنعلي.' );
$isolated_dot             = new WC_Order( 6486 );
$isolated_dot->first_name = 'M . Ali';
$isolated_dot_data        = Capi_User_Data::build( $isolated_dot, true );
l5_check( ! isset( $isolated_dot_data['fn'] ) && $sha( 'ali' ) === ( $isolated_dot_data['ln'] ?? '' ), 'F1 sent block for « M . Ali »: ln = ali, no fn.' );

// F3: phone clock ahead — session start up to 24 h after the order → creationTime = order creation.
$order_ms = 'fb.1.' . ( $order_time * 1000 ) . '.' . $fbclid;
l5_check( Capi_Click_Id::build( $entry, '2026-10-19 10:02:00', $order_time ) === $order_ms, 'F3 session 2 min after the order → fbc with the order creation time.' );
l5_check( Capi_Click_Id::build( $entry, '2026-10-20 10:00:00', $order_time ) === $order_ms, 'F3 session exactly 24 h after the order → fbc with the order creation time.' );
l5_check( '' === Capi_Click_Id::build( $entry, '2026-10-20 10:00:01', $order_time ), 'F3 session more than 24 h after the order → no fbc.' );
l5_check( Capi_Click_Id::build( $entry, $start_utc, $order_time ) === 'fb.1.' . $start_ms . '.' . $fbclid, 'F3 session before the order: session start kept as creationTime.' );
$ahead             = new WC_Order( 6490 );
$ahead->created_at = $attributed->created_at;
$ahead->meta       = array(
	Capi_Click_Id::ENTRY_META => $entry,
	Capi_Click_Id::START_META => '2026-10-19 10:05:00',
);
l5_check( ( Capi_User_Data::build( $ahead, true )['fbc'] ?? '' ) === $order_ms, 'F3 through Capi_User_Data::build(): clock 5 min ahead → fbc with the order creation time.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
