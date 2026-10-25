<?php
/**
 * Lot 5, B7 (R7, V9) — a _fbc cookie present but refused by the Meta format
 * increments famma_capi_fbc_rejected (array( 'AAAA-MM-JJ' => n ), 7 sliding
 * days, UTC), at most once per request, without ever logging its value; the
 * health rules read the same counter. Also: the test-mode notice no longer
 * claims that events "leave normally" without a valid code (A12).
 * Run with: php scripts/tests-unit/test-fbc-rejected.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap-browser.php.
require __DIR__ . '/bootstrap-browser.php';

use Famma\Core\Capi_Health_Rules;
use Famma\Core\Capi_Signals;

$bad_fbc = 'fb.1.horodatage.QAL5-SECRET-VALUE';

lb_reset();
$_COOKIE['_fbc'] = 'fb.1.1760000000000.IwAR-QAL5-valid';
l5_check( 'fb.1.1760000000000.IwAR-QAL5-valid' === Capi_Signals::cookie( '_fbc' ) && false === get_option( Capi_Signals::FBC_REJECTED_OPTION, false ), 'fbc valide : accepté, compteur intact.' );
unset( $_COOKIE['_fbc'] );
l5_check( '' === Capi_Signals::cookie( '_fbc' ) && false === get_option( Capi_Signals::FBC_REJECTED_OPTION, false ), 'fbc absent : rien n’est compté (absent ≠ refusé).' );
$_COOKIE['_fbp'] = 'pas-un-fbp';
l5_check( '' === Capi_Signals::cookie( '_fbp' ) && false === get_option( Capi_Signals::FBC_REJECTED_OPTION, false ), 'fbp invalide : refusé sans toucher au compteur fbc.' );
unset( $_COOKIE['_fbp'] );

$_COOKIE['_fbc'] = $bad_fbc;
l5_check( '' === Capi_Signals::cookie( '_fbc' ), 'fbc hors format : refusé (jamais transmis à la CAPI).' );
$stored = get_option( Capi_Signals::FBC_REJECTED_OPTION, array() );
l5_check( array( gmdate( 'Y-m-d' ) => 1 ) === $stored, 'V9 fbc refusé : compteur du jour = 1, forme array( AAAA-MM-JJ => n ).' );
Capi_Signals::cookie( '_fbc' );
l5_check( array( gmdate( 'Y-m-d' ) => 1 ) === get_option( Capi_Signals::FBC_REJECTED_OPTION, array() ), 'Au plus une fois par requête.' );
unset( $_COOKIE['_fbc'] );
$dump = (string) json_encode( array( $GLOBALS['wp_test_options'], $GLOBALS['wc_test_log'] ?? array(), $GLOBALS['wp_test_mail'] ?? array() ) );
l5_check( ! str_contains( $dump, 'QAL5-SECRET-VALUE' ) && ! str_contains( $dump, 'horodatage' ), 'V9 La valeur du cookie refusé n’est jamais conservée ni journalisée.' );

// Fenêtre glissante de 7 jours.
$now = strtotime( '2026-10-19 12:00:00 UTC' );
update_option(
	Capi_Signals::FBC_REJECTED_OPTION,
	array(
		'2026-10-01'   => 9,
		'2026-10-12'   => 4,
		'2026-10-13'   => 2,
		'pas-une-date' => 50,
	)
);
Capi_Signals::count_rejected_fbc( $now );
Capi_Signals::count_rejected_fbc( $now );
l5_check(
	array(
		'2026-10-13' => 2,
		'2026-10-19' => 2,
	) === get_option( Capi_Signals::FBC_REJECTED_OPTION ),
	'Fenêtre de 7 jours : les jours plus anciens et les clés invalides sont purgés.'
);
l5_check( 4 === Capi_Health_Rules::fbc_rejected( $now ), 'La santé lit le même compteur (4 refus sur 7 jours).' );
update_option( Capi_Signals::FBC_REJECTED_OPTION, 'corrompu' );
Capi_Signals::count_rejected_fbc( $now );
l5_check( array( '2026-10-19' => 1 ) === get_option( Capi_Signals::FBC_REJECTED_OPTION ), 'Option corrompue : repart proprement.' );

// Avis du mode test (A12) : plus de « partent normalement ».
lb_reset(
	array(
		'famma_meta_capi_test_mode'  => 1,
		'famma_meta_test_event_code' => 'TESTQAL2',
	)
);
ob_start();
Famma_Unit_Harness::bare( '\Famma\Core\Capi_Signals' )->test_mode_notice();
$notice = (string) ob_get_clean();
l5_check( '' === Capi_Signals::test_event_code() && str_contains( $notice, 'sont bloqués' ) && ! str_contains( $notice, 'normalement' ), 'Mode test sans code valide (TESTQAL2) : l’avis dit que les envois sont bloqués.' );
lb_reset(
	array(
		'famma_meta_capi_test_mode'  => 1,
		'famma_meta_test_event_code' => 'TEST12345',
	)
);
ob_start();
Famma_Unit_Harness::bare( '\Famma\Core\Capi_Signals' )->test_mode_notice();
$notice = (string) ob_get_clean();
l5_check( 'TEST12345' === Capi_Signals::test_event_code() && str_contains( $notice, 'comptent quand même' ), 'Mode test avec code valide : l’avis rappelle que les événements de test comptent (E5).' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
