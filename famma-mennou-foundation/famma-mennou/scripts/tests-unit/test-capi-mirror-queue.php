<?php
/**
 * Lot 59 — doublon serveur : file, verrou, envoi groupé et état de santé.
 *
 * - envoi groupé par la tâche WP-Cron seulement : N événements → UNE requête,
 *   lignes vidées de leurs données aussitôt, marque de rejeu 24 h (I1, I4) ;
 * - verrou atomique : deux preneurs simultanés, un verrou tenu, un verrou
 *   échu repris par un seul gagnant ; mise en file simultanée du même
 *   événement : une seule ligne (I3) ;
 * - décision sur les lignes elles-mêmes, aucune ligne orpheline (M1) ;
 * - refus de contenu renvoyé par moitiés, seul l'événement fautif perdu (M5) ;
 * - désactivation : file, verrou et compteurs supprimés (M6) ;
 * - santé : refus, écarts et pertes en orange, jamais rouge, aucun e-mail ;
 * - jeton, téléphone et IP jamais dans le journal ; graph.facebook.com
 *   seulement dans capi-transport.php (V3).
 * Run with: php scripts/tests-unit/test-capi-mirror-queue.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap-browser.php.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Helpers of the standalone runner.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.EscapeOutput.OutputNotEscaped -- Command-line test: superglobals are fixtures, output is a report.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- The $wpdb double is swapped around Capi_Health::evaluate(); WordPress is never loaded.
require __DIR__ . '/bootstrap-browser.php';
require __DIR__ . '/double-mirror-wpdb.php';
require __DIR__ . '/double-mirror-wp.php';

use Famma\Core\Capi_Health;
use Famma\Core\Capi_Health_Rules;
use Famma\Core\Capi_Mirror;
use Famma\Core\Capi_Mirror_Health;
use Famma\Core\Capi_Mirror_Queue;
use Famma\Core\Capi_Mirror_Route;
use Famma\Core\Capi_Mirror_Store;
use Famma\Core\Rate_Counter;

$now  = 1795000000;
$hash = hash( 'sha256', '21622123456' );
$pv   = relay( 'PageView', '0b6f7c1e-2d3a-4b5c-8d9e-0f1a2b3c4d5e' );
$vc   = relay( 'ViewContent', '1c6f7c1e-2d3a-4b5c-8d9e-0f1a2b3c4d5e', array( array( 501, 1 ) ) );
$add  = relay( 'AddToCart', 'famma_add_7f3a9c', array( array( 501, 3 ) ), 1500, 'https://qa.invalid/produit/masque/', 66.0 );
$keep = static fn( array $events, int $at = 0, string $ph = '' ): int => Capi_Mirror_Route::accept( body( $events, $ph ), $at > 0 ? $at : $GLOBALS['now'] );
$ok   = static fn( int $n ): array => l5_response( 200, '{"events_received":' . $n . ',"messages":[],"fbtrace_id":"QA59"}', array( 'facebook-api-version' => 'v26.0' ) );
$bad  = l5_response( 400, '{"error":{"code":100,"message":"Invalid parameter","fbtrace_id":"QA59"}}' );

/**
 * One HTTP response per request, in order.
 *
 * @param array $responses Responses.
 */
function mirror_http_sequence( array $responses ): void {
	$GLOBALS['wp_test_http_response'] = array_shift( $responses );
	$GLOBALS['mirror_http_next']      = $responses;
	add_action(
		'http_api_debug',
		static function () {
			if ( array() !== $GLOBALS['mirror_http_next'] ) {
				$GLOBALS['wp_test_http_response'] = array_shift( $GLOBALS['mirror_http_next'] );
			}
		}
	);
}

/**
 * Events of the n-th request sent to Meta.
 *
 * @param int $n Request index.
 * @return array
 */
function mirror_sent( int $n ): array {
	return json_decode( (string) ( $GLOBALS['wp_test_http'][ $n ]['args']['body'] ?? '' ), true )['data'] ?? array();
}

// ── Envoi groupé : une requête, données effacées, verrou rendu ──────────────
mirror_reset();
$keep( array( $pv, $vc, $add ), 0, $hash );
$GLOBALS['wp_test_http_response'] = $ok( 3 );
$report                           = Capi_Mirror_Queue::flush( $now + 70 );
l5_check( 1 === count( $GLOBALS['wp_test_http'] ) && 3 === count( mirror_sent( 0 ) ) && 3 === $report['sent'], 'Envoi groupé : 3 événements → UNE requête à Meta.' );
l5_check( str_ends_with( (string) $GLOBALS['wp_test_http'][0]['url'], '/000000000000001/events' ) && ! str_contains( (string) $GLOBALS['wp_test_http'][0]['url'], 'access_token' ), 'Requête par Capi_Transport : jeton dans le corps, jamais dans l’URL.' );
$stored = (string) json_encode( $GLOBALS['wp_test_options'] );
l5_check( array() === mirror_queued() && ! isset( $GLOBALS['wp_test_options'][ Capi_Mirror_Store::LOCK ] ) && ! str_contains( $stored, '197.0.0.10' ) && ! str_contains( $stored, 'iPhone' ), 'Accusé de Meta : lignes vidées de leurs données (IP, user-agent), verrou rendu.' );
$GLOBALS['wpdb']->writes = 0;
l5_check( 0 === Capi_Mirror_Queue::flush( $now + 400 )['sent'] && 1 === count( $GLOBALS['wp_test_http'] ) && 1 === $GLOBALS['wpdb']->writes, 'File vide : la tâche suivante n’appelle pas Meta et ne prend pas le verrou (une purge, aucune autre écriture).' );
$log = implode( "\n", l5_capi_lines() );
l5_check( str_contains( $log, 'BrowserMirror' ) && ! str_contains( $log, '197.0.0.10' ) && ! str_contains( $log, '22123456' ) && ! str_contains( $log, $hash ) && ! str_contains( $log, 'QA-L5-FAKE-TOKEN' ), 'Journal famma-capi : une ligne par lot, ni IP, ni téléphone, ni empreinte, ni jeton.' );

// ── I1 : rejeu refusé 24 h, même après l'envoi ──────────────────────────────
l5_check( 0 === $keep( array( $pv ), $now + 500 ) && 1 === mirror_health()['replay'], 'I1 : event_id déjà envoyé, rejoué dans les 24 h : refusé et compté.' );
Capi_Mirror_Queue::flush( $now + DAY_IN_SECONDS + 600 );
l5_check( 1 === $keep( array( $pv ), $now + DAY_IN_SECONDS + 700 ), 'Après 24 h, la marque de rejeu est purgée par la tâche.' );

mirror_reset(
	array(
		'famma_meta_capi_test_mode'  => true,
		'famma_meta_test_event_code' => 'TEST12345',
	)
);
$keep( array( $pv ) );
Capi_Mirror_Queue::flush( $now + 70 );
l5_check( 'TEST12345' === ( json_decode( (string) ( $GLOBALS['wp_test_http'][0]['args']['body'] ?? '' ), true )['test_event_code'] ?? '' ), 'Mode test Meta : le lot porte le test_event_code.' );

// ── Refus temporaire : trois tentatives ─────────────────────────────────────
mirror_reset();
$keep( array( $pv, $vc ) );
$GLOBALS['wp_test_http_response'] = $ok( 1 );
$first                            = Capi_Mirror_Queue::flush( $now + 70 );
l5_check( 'transient' === $first['class'] && 2 === $first['kept'] && 2 === count( mirror_queued() ) && 1 === mirror_queued()[0]['n'], 'Accusé incomplet (1 sur 2) : lot gardé pour une nouvelle tentative.' );
Capi_Mirror_Queue::flush( $now + 370 );
$last = Capi_Mirror_Queue::flush( $now + 670 );
l5_check( 2 === $last['lost'] && array() === mirror_queued() && 3 === count( $GLOBALS['wp_test_http'] ), 'Trois tentatives au plus, puis le lot est écarté.' );
add_filter( 'famma_capi_health_reasons', array( Capi_Mirror_Health::class, 'health_reasons' ), 10, 3 );
add_filter( 'famma_capi_health_label', array( Capi_Mirror_Health::class, 'health_label' ), 10, 2 );
Capi_Mirror_Health::record_many( array( 'origin' => 4 ), $now + 700 );
$reasons = Capi_Health_Rules::reasons( Capi_Health::signals(), $now + 800 );
$mirror  = array_values( array_filter( $reasons, static fn( array $r ): bool => 'mirror_lost' === $r['code'] ) );
l5_check( 1 === count( $mirror ) && 'orange' === $mirror[0]['niveau'], 'Santé : pertes et refus = UNE raison orange (jamais rouge).' );
l5_check( str_contains( Capi_Health_Rules::label( $mirror[0] ), '2 perdu(s)' ) && str_contains( Capi_Health_Rules::label( $mirror[0] ), '4 refusé(s)' ) && str_contains( Capi_Health_Rules::label( $mirror[0] ), 'transient,origin' ), 'Santé : libellé lisible (perdus, refusés, corrigés, motifs).' );
$GLOBALS['wp_test_mail'] = array();
$famma_wpdb              = $GLOBALS['wpdb'];
unset( $GLOBALS['wpdb'] ); // Verrou de l'alerte par option (sa requête SQL n'est pas doublée ici).
$state           = Capi_Health::evaluate( $now + 800 );
$GLOBALS['wpdb'] = $famma_wpdb;
l5_check( 'orange' === $state['statut'] && array() === $GLOBALS['wp_test_mail'], 'Santé : état orange, aucune alerte e-mail pour le doublon.' );
l5_check( array() === Capi_Mirror_Health::health_reasons( array(), array(), $now + 800 + 8 * DAY_IN_SECONDS ), 'Santé : plus rien après 7 jours.' );

// ── M5 : refus de contenu renvoyé par moitiés ───────────────────────────────
mirror_reset();
$keep( array( $pv ) );
$GLOBALS['wp_test_http_response'] = $bad;
$refused                          = Capi_Mirror_Queue::flush( $now + 70 );
l5_check( 'contract' === $refused['class'] && 1 === $refused['lost'] && array() === mirror_queued() && ! isset( Capi_Health::signals()['contract'] ), 'Refus de contenu d’un seul événement : perdu, compté ; les signaux d’OrderPlaced et Purchase ne bougent pas.' );
mirror_reset();
$keep( array( $pv, $vc, $add, relay( 'PageView', 'fautif-0000-0004' ) ) );
mirror_http_sequence( array( $bad, $ok( 2 ), $bad, $ok( 1 ), $bad ) );
$split = Capi_Mirror_Queue::flush( $now + 70 );
$last  = mirror_sent( 4 );
l5_check( 5 === $split['requests'] && 3 === $split['sent'] && 1 === $split['lost'] && 1 === count( $last ) && 'fautif-0000-0004' === $last[0]['event_id'], 'M5 : lot de 4 refusé → renvoyé par moitiés : 3 envoyés, seul l’événement fautif perdu (5 requêtes).' );

// ── Échéance, interrupteur ──────────────────────────────────────────────────
mirror_reset();
$keep( array( $pv ) );
$expired = Capi_Mirror_Queue::flush( $now + 3601 + 70 );
l5_check( 1 === $expired['lost'] && array() === $GLOBALS['wp_test_http'], 'Événement en attente depuis plus d’une heure : écarté, rien n’est envoyé.' );
mirror_reset();
$keep( array( $pv ) );
$GLOBALS['wp_test_options']['famma_meta_browser_mirror'] = false;
$off = Capi_Mirror_Queue::flush( $now + 70 );
l5_check( 'disabled' === $off['class'] && array() === mirror_queued() && array() === $GLOBALS['wp_test_http'] && 0 === $off['lost'], 'Interrupteur coupé après coup : lignes vidées, rien envoyé, pas compté en échec.' );

// ── I3 : verrou atomique ────────────────────────────────────────────────────
mirror_reset();
$keep( array( $pv, $vc ) );
$GLOBALS['wp_test_http_response'] = $ok( 2 );
$GLOBALS['mirror_race_on']        = 'INSERT IGNORE';
$GLOBALS['mirror_race']           = static function () {
	$GLOBALS['mirror_race_report'] = Capi_Mirror_Queue::flush( $GLOBALS['now'] + 70 );
};
$a                                = Capi_Mirror_Queue::flush( $now + 70 );
l5_check( 2 === $GLOBALS['mirror_race_report']['sent'] && 0 === $a['sent'] && 1 === count( $GLOBALS['wp_test_http'] ), 'I3 : deux tâches simultanées — B prend le verrou et envoie, A relit sous le verrou : UNE seule requête à Meta.' );
mirror_reset();
$keep( array( $pv ) );
$GLOBALS['mirror_race_on'] = 'INSERT IGNORE';
$GLOBALS['mirror_race']    = static function () {
	$GLOBALS['wp_test_options'][ Capi_Mirror_Store::LOCK ] = ( $GLOBALS['now'] + 190 ) . ':process-b';
};
l5_check( 0 === Capi_Mirror_Queue::flush( $now + 70 )['sent'] && array() === $GLOBALS['wp_test_http'] && 1 === count( mirror_queued() ), 'I3 : verrou pris par B entre-temps (INSERT IGNORE : 0 ligne) : A n’envoie rien.' );
$GLOBALS['wp_test_options'][ Capi_Mirror_Store::LOCK ] = ( $now - 10 ) . ':abandoned';
$GLOBALS['mirror_race_on']                             = 'AND option_value = %s';
$GLOBALS['mirror_race']                                = static function () {
	$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'UPDATE wp_options SET option_value = %s WHERE option_name = %s AND option_value = %s', ( $GLOBALS['now'] + 190 ) . ':process-b', Capi_Mirror_Store::LOCK, ( $GLOBALS['now'] - 10 ) . ':abandoned' ) );
};
l5_check( 0 === Capi_Mirror_Queue::flush( $now + 70 )['sent'] && str_ends_with( (string) $GLOBALS['wp_test_options'][ Capi_Mirror_Store::LOCK ], ':process-b' ), 'I3 : verrou échu repris en même temps par A et B : un seul gagnant (UPDATE sur la valeur lue).' );
$GLOBALS['wp_test_options'][ Capi_Mirror_Store::LOCK ] = ( $now - 10 ) . ':abandoned';
l5_check( 1 === Capi_Mirror_Queue::flush( $now + 70 )['sent'], 'Verrou abandonné (échu) : repris, la file part.' );
mirror_reset();
$GLOBALS['mirror_race_on'] = 'INSERT IGNORE';
$GLOBALS['mirror_race']    = static function () use ( $keep, $pv ) {
	$GLOBALS['mirror_race_count'] = $keep( array( $pv ) );
};
$late                      = $keep( array( $pv ) );
l5_check( 1 === $GLOBALS['mirror_race_count'] && 0 === $late && 1 === count( mirror_queued() ) && 1 === mirror_health()['replay'], 'I3 : même événement rangé par deux requêtes simultanées : une seule ligne, l’autre comptée comme rejeu.' );
$sources = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/capi-mirror-store.php' ) . file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/capi-mirror-queue.php' );
l5_check( 0 === preg_match( '/[^`\w](add|get|update|delete)_option\(/', $sources ), 'I3 : file et verrou sans add_option()/get_option() (non atomiques) : SQL préparé seulement.' );

// ── M1 : une ligne arrivée pendant l'envoi part à la tâche suivante ─────────
mirror_reset();
$keep( array( $pv ) );
$GLOBALS['mirror_race_on'] = 'WHERE option_name IN';
$GLOBALS['mirror_race']    = static function () use ( $keep, $vc ) {
	$keep( array( $vc ), $GLOBALS['now'] + 60 );
};
Capi_Mirror_Queue::flush( $now + 70 );
$orphan = count( mirror_queued() );
$next   = Capi_Mirror_Queue::flush( $now + 370 );
l5_check( 1 === $orphan && 1 === $next['sent'] && 'ViewContent' === ( mirror_sent( 1 )[0]['event_name'] ?? '' ), 'M1 : ligne arrivée pendant un envoi : pas de repère à perdre, la tâche suivante l’envoie.' );

// ── M6 : désactivation, tâche WP-Cron ───────────────────────────────────────
mirror_reset();
$keep( array( $pv, $vc ) );
$GLOBALS['wp_test_options'][ Capi_Mirror_Store::LOCK ] = ( $now + 100 ) . ':x';
$GLOBALS['wp_test_cron'][ Capi_Mirror::CRON ]          = array( $now, Capi_Mirror::CRON_SCHEDULE );
Capi_Mirror::deactivate();
$left = array_filter( array_keys( $GLOBALS['wp_test_options'] ), static fn( $n ): bool => str_starts_with( (string) $n, Capi_Mirror_Store::PREFIX ) || str_starts_with( (string) $n, Rate_Counter::PREFIX ) || Capi_Mirror_Store::LOCK === $n );
l5_check( array() === $left && ! isset( $GLOBALS['wp_test_cron'][ Capi_Mirror::CRON ] ), 'M6 : désactivation — file (IP, user-agent, fbp, fbc), verrou, compteurs et tâche supprimés.' );
$GLOBALS['wp_test_cron'] = array();
$famma_mirror            = Famma_Unit_Harness::bare( Capi_Mirror::class );
$famma_mirror->ensure_scheduled();
l5_check( Capi_Mirror::CRON_SCHEDULE === ( $GLOBALS['wp_test_cron'][ Capi_Mirror::CRON ][1] ?? '' ) && 300 === $famma_mirror->cron_schedule( array() )[ Capi_Mirror::CRON_SCHEDULE ]['interval'], 'Tâche WP-Cron toutes les 5 minutes, planifiée une fois : seule voie d’envoi.' );

// ── V3 et données sensibles ─────────────────────────────────────────────────
$graph = array();
foreach ( glob( ABSPATH . 'wp-content/plugins/famma-core/includes/*.php' ) as $file ) {
	if ( str_contains( (string) file_get_contents( $file ), 'graph.facebook.com' ) ) {
		$graph[] = basename( $file );
	}
}
l5_check( array( 'capi-transport.php' ) === $graph, 'V3 : graph.facebook.com n’apparaît que dans capi-transport.php.' );
$mirror_src = '';
foreach ( glob( ABSPATH . 'wp-content/plugins/famma-core/includes/capi-mirror*.php' ) as $file ) {
	$mirror_src .= (string) file_get_contents( $file );
}
l5_check( ! str_contains( $mirror_src, 'wp_remote_' ) && ! str_contains( $mirror_src, 'error_log' ) && ! str_contains( $mirror_src, 'billing_phone' ) && ! str_contains( $mirror_src, 'finish_request' ) && ! str_contains( $mirror_src, "'shutdown'" ), 'Doublon : aucun appel HTTP hors Capi_Transport, aucun error_log, aucun champ téléphone lu, aucun envoi en fin de requête.' );
mirror_reset();
$keep( array( $pv ), 0, $hash );
$stored = (string) json_encode( $GLOBALS['wp_test_options'] );
l5_check( ! str_contains( $stored, '22123456' ) && ! str_contains( $stored, 'QA-L5-FAKE-TOKEN' ), 'Stockage : ni téléphone en clair ni jeton dans la file.' );

echo 'RESULT ' . $GLOBALS['l5_checks'] . '/' . $GLOBALS['l5_checks'] . PHP_EOL;
