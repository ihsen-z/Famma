<?php
/**
 * Lot 59, contre-relecture de c85ccb3 — garde-fous du doublon serveur.
 *
 * - N1 : seul un visiteur connu du journal (cookie `famma_vid` déjà
 *   enregistré, dans le même envoi ou avant) compte dans le plafond du site
 *   et est relayé ; un inconnu est refusé (`unknown`) sans toucher au
 *   plafond ; plafond du site relevé (6 000 / 10 min), la limite par groupe
 *   d'adresses reste la vraie limite ; coût de l'attaque mesuré ;
 * - N3 : refus de contenu, chaque fautif isolé par moitiés sans perdre les
 *   bons ; budget de requêtes vérifié avant chaque requête ; le reste
 *   attend en file intact ;
 * - N6 : lot du journal trop gros : `m` écarté et compté, le journal gardé.
 * Run with: php scripts/tests-unit/test-capi-mirror-guard.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap-browser.php.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Helpers of the standalone runner.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.EscapeOutput.OutputNotEscaped -- Command-line test: superglobals are fixtures, output is a report.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Fixtures of the standalone runner; WordPress is never loaded.
require __DIR__ . '/bootstrap-browser.php';
require __DIR__ . '/double-mirror-wpdb.php';
require __DIR__ . '/double-mirror-wp.php';
Famma_Unit_Harness::load_include( 'visitor-collector.php' );

use Famma\Core\Capi_Mirror_Queue;
use Famma\Core\Capi_Mirror_Route;
use Famma\Core\Rate_Counter;
use Famma\Core\Visitor_Collector;

$now       = 1795000000;
$pv        = relay( 'PageView', '0b6f7c1e-2d3a-4b5c-8d9e-0f1a2b3c4d5e' );
$count     = static fn( array $events, int $at = 0 ): int => Capi_Mirror_Route::accept( body( $events ), $at > 0 ? $at : $GLOBALS['now'] );
$lot       = static function ( string $tag, int $size = 10 ): array {
	$events = array();
	for ( $n = 0; $n < $size; $n++ ) {
		$events[] = relay( 'PageView', sprintf( '%s-%03d-guard', $tag, $n ) );
	}
	return $events;
};
$beacon    = static function ( array $journal_events, $mirror, string $session = '5d1c2b3a-4e5f-4a6b-8c7d-9e0f1a2b3c4d', int $pad = 0 ): WP_REST_Response {
	$data = array(
		'v' => $GLOBALS['vid'],
		's' => $session,
		'e' => $journal_events,
	);
	if ( null !== $mirror ) {
		$data['m'] = $mirror;
	}
	if ( $pad > 0 ) {
		$data['x'] = str_repeat( 'p', $pad );
	}
	return Famma_Unit_Harness::bare( Visitor_Collector::class )->collect( new WP_REST_Request( (string) json_encode( $data ) ) );
};
$page_view = array(
	array(
		'n'  => 'page_view',
		'pt' => 'product',
		'p'  => '/produit/masque/',
	),
);

// ── N1 : visiteur connu du journal exigé ────────────────────────────────────
mirror_reset();
$wpdb->known = array();
l5_check( 0 === $count( array( $pv ) ) && 1 === mirror_health()['unknown'] && null === mirror_rate( 'mr_site' ) && 1 === $wpdb->reads_journal, 'N1 : cookie famma_vid jamais vu par le journal : refusé (« unknown »), plafond du site intact.' );
unset( $_COOKIE['famma_vid'] );
l5_check( 0 === $count( array( $pv ) ) && 2 === mirror_health()['unknown'] && 1 === $wpdb->reads_journal, 'N1 : sans cookie famma_vid : refusé sans même lire le journal.' );
mirror_reset();
$wpdb->known  = array();
$wpdb->writes = 0;
for ( $call = 0; $call < 60; $call++ ) {
	$_SERVER['REMOTE_ADDR'] = '10.1.' . intdiv( $call, 250 ) . '.' . ( $call % 250 );
	$count( $lot( 'unknown-' . $call ) );
}
l5_check( 600 === mirror_health()['unknown'] && null === mirror_rate( 'mr_site' ) && array() === mirror_queued() && array() === mirror_rate_rows(), 'N1 : 600 événements inconnus venus de 60 adresses : aucun ne compte dans le plafond, aucune ligne de file ni de compteur (seule la santé, en bruit).' );
mirror_reset();
$wpdb->known = array();
$response    = $beacon( $page_view, array( 'e' => array( $pv ) ) );
l5_check( 204 === $response->status && 1 === count( $wpdb->journal ) && 1 === count( mirror_queued() ) && 0 === $wpdb->reads_journal, 'N1 : premier envoi d’un nouveau visiteur : le journal l’enregistre dans ce même envoi, son PageView est relayé (aucune lecture de plus).' );
l5_check( 1 === $count( array( relay( 'ViewContent', 'standalone-after-journal', array( array( 501, 1 ) ) ) ) ), 'N1 : la route propre accepte ensuite ce visiteur (ligne du journal trouvée).' );
mirror_reset();
$wpdb->known = array();
$beacon( $page_view, array( 'e' => array( $pv ) ), 'pas-un-uuid' );
l5_check( array() === $wpdb->journal && array() === mirror_queued() && 1 === mirror_health()['unknown'], 'N1 : journal refusé (session invalide) et visiteur inconnu : le doublon est refusé aussi.' );

// ── N1 : plafond du site relevé, limite par groupe = vraie limite ───────────
mirror_reset();
$accepted = 0;
for ( $call = 0; $call < 13; $call++ ) {
	$accepted += $count( $lot( 'one-group-' . $call ) );
}
l5_check( 120 === $accepted && 120 === Rate_Counter::used( 'mr_site', $now ) && 10 === mirror_health()['rate'], 'N1 : un seul groupe d’adresses plafonne à 120 événements / 10 min : 2 % du plafond du site (6 000).' );
mirror_reset();
$groups   = array();
$accepted = 0;
$distinct = 0;
for ( $host = 1; $distinct < 51; $host++ ) {
	$_SERVER['REMOTE_ADDR'] = '41.' . intdiv( $host, 250 ) . '.' . ( $host % 250 ) . '.7';
	$group                  = Famma\Core\Visitor_Context::rate_group();
	if ( isset( $groups[ $group ] ) ) {
		continue;
	}
	$groups[ $group ] = true;
	++$distinct;
	for ( $call = 0; $call < 12; $call++ ) {
		$accepted += $count( $lot( 'g' . $host . '-' . $call ) );
	}
}
l5_check( 6000 === $accepted && 6000 === Rate_Counter::used( 'mr_site', $now ) && 120 === mirror_health()['site'], 'N1 : coût de l’attaque — remplir le plafond exige 50 groupes d’adresses distincts × 12 envois de 10 événements (600 envois / 10 min) d’un visiteur connu ; le 51e groupe est refusé (« site »).' );

// ── N3 : chaque fautif isolé, les bons partent, budget borné ────────────────
$GLOBALS['wp_test_http_responder'] = static function ( string $url, array $args ): array {
	$data = json_decode( (string) $args['body'], true )['data'] ?? array();
	if ( str_contains( (string) $args['body'], 'fautif' ) ) {
		return l5_response( 400, '{"error":{"code":100,"message":"Invalid parameter","fbtrace_id":"QA59"}}' );
	}
	return l5_response( 200, '{"events_received":' . count( $data ) . ',"messages":[],"fbtrace_id":"QA59"}', array( 'facebook-api-version' => 'v26.0' ) );
};
$responder                         = $GLOBALS['wp_test_http_responder'];
mirror_reset();
$GLOBALS['wp_test_http_responder'] = $responder;
$eight                             = $lot( 'bon', 8 );
$eight[2]                          = relay( 'PageView', 'fautif-0000-0002' );
$eight[7]                          = relay( 'PageView', 'fautif-0000-0007' );
$count( $eight );
$report = Capi_Mirror_Queue::flush( $now + 70 );
$lost   = array();
foreach ( $GLOBALS['wp_test_http'] as $request ) {
	$sent = json_decode( (string) $request['args']['body'], true )['data'];
	if ( 1 === count( $sent ) && str_contains( $sent[0]['event_id'], 'fautif' ) ) {
		$lost[] = $sent[0]['event_id'];
	}
}
l5_check( 6 === $report['sent'] && 2 === $report['lost'] && 0 === $report['deferred'] && array( 'fautif-0000-0002', 'fautif-0000-0007' ) === $lost && array() === mirror_queued(), 'N3 : lot de 8 avec 2 fautifs : les 6 bons envoyés, seuls les 2 fautifs perdus.' );
l5_check( $report['requests'] <= 1 + 2 * 2 * 3 && 11 === $report['requests'], 'N3 : 11 requêtes, sous la borne 1 + 2·k·⌈log2 n⌉ = 13 (k = 2 fautifs, n = 8).' );
mirror_reset();
$GLOBALS['wp_test_http_responder'] = static fn(): array => l5_response( 400, '{"error":{"code":100,"message":"Invalid parameter","fbtrace_id":"QA59"}}' );
$filled                            = 0;
for ( $call = 0; $call < 50; $call++ ) {
	$_SERVER['REMOTE_ADDR'] = '102.' . $call . '.0.1';
	$filled                += $count( $lot( 'systemique-' . $call ) );
}
$first  = Capi_Mirror_Queue::flush( $now + 70 );
$queued = mirror_queued();
l5_check( 500 === $filled && Capi_Mirror_Queue::MAX_REQUESTS === $first['requests'] && 500 === $first['lost'] + $first['deferred'] && $first['deferred'] > 400 && count( $queued ) === $first['deferred'] && array() === array_filter( $queued, static fn( array $row ): bool => 0 !== (int) $row['n'] ), 'N3 : lot de 500 entièrement refusé : 20 requêtes exactement, le reste attend en file intact (ni perdu ni compté comme tentative).' );
$second = Capi_Mirror_Queue::flush( $now + 370 );
l5_check( Capi_Mirror_Queue::MAX_REQUESTS === $second['requests'] && 40 === count( $GLOBALS['wp_test_http'] ), 'N3 : la tâche suivante reprend, toujours 20 requêtes au plus (240 par heure au pire).' );
$third = Capi_Mirror_Queue::flush( $now + 3700 );
l5_check( 0 === $third['requests'] && array() === mirror_queued() && $third['lost'] === $second['deferred'], 'N3 : au-delà d’une heure d’attente, le reste est écarté sans requête (« expired »).' );

// ── N6 : lot du journal trop gros : `m` écarté, journal gardé ───────────────
mirror_reset();
$response = $beacon( $page_view, array( 'e' => array( $pv ) ), '5d1c2b3a-4e5f-4a6b-8c7d-9e0f1a2b3c4d', 17000 );
l5_check( 204 === $response->status && 1 === count( $wpdb->journal ) && array() === mirror_queued() && 1 === mirror_health()['oversize'], 'N6 : envoi de plus de 16 Ko avec `m` : le journal est enregistré, `m` écarté et compté (« oversize »).' );
mirror_reset();
$beacon( $page_view, null, '5d1c2b3a-4e5f-4a6b-8c7d-9e0f1a2b3c4d', 17000 );
l5_check( array() === $wpdb->journal && 0 === mirror_health()['oversize'], 'N6 : sans `m`, la limite du journal (16 Ko) reste celle d’avant.' );
$beacon( $page_view, array( 'e' => array( $pv ) ), '5d1c2b3a-4e5f-4a6b-8c7d-9e0f1a2b3c4d', 25000 );
l5_check( array() === $wpdb->journal && array() === mirror_queued(), 'N6 : au-delà de 24 Ko, rien n’est lu.' );

echo 'RESULT ' . $GLOBALS['l5_checks'] . '/' . $GLOBALS['l5_checks'] . PHP_EOL;
