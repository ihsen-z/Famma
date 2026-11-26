<?php
/**
 * Doublon serveur (lot 59) : envoi groupé à Meta par la tâche WP-Cron.
 *
 * Seule la tâche WP-Cron de 5 minutes envoie (I4) : aucune requête venue du
 * navigateur n'attend Meta. Elle décide sur les lignes en attente elles-mêmes
 * (M1), sous le verrou atomique de `Capi_Mirror_Store` (I3), et relit les
 * lignes une fois le verrou pris : deux tâches simultanées n'envoient jamais
 * le même lot.
 *
 * Envoi : jusqu'à {@see BATCH} événements en UNE requête (doc Meta « Using
 * the API », Batch Requests : « You can send up to 1,000 events in data »,
 * « ideally within an hour of the event occurring »). Un événement qui
 * attend plus d'une heure est écarté ; `event_time` reste bien en deçà des
 * 7 jours. Un refus de contenu d'un lot de plusieurs événements est renvoyé
 * par moitiés (M5) : seul l'événement fautif est perdu, au plus
 * {@see MAX_REQUESTS} requêtes par tâche.
 *
 * Santé : un événement écarté sans accusé de Meta est compté par
 * `Capi_Mirror_Health` (orange, jamais rouge, aucun e-mail).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Envoi groupé de la file du doublon.
 */
final class Capi_Mirror_Queue {

	/**
	 * Événements par requête envoyée à Meta (maximum Meta : 1 000).
	 */
	public const BATCH = 500;

	/**
	 * Tentatives avant d'écarter un lot refusé temporairement.
	 */
	public const MAX_ATTEMPTS = 3;

	/**
	 * Attente maximale dans la file, en secondes (Meta : « within an hour »).
	 */
	public const MAX_WAIT = 3600;

	/**
	 * Requêtes à Meta au plus par tâche (renvois par moitiés compris).
	 */
	public const MAX_REQUESTS = 20;

	/**
	 * Envoie un lot à Meta (tâche WP-Cron uniquement).
	 *
	 * @param int $now Horloge (tests), maintenant par défaut.
	 * @return array{sent: int, kept: int, lost: int, class: string, requests: int}
	 */
	public static function flush( int $now = 0 ): array {
		$now    = $now > 0 ? $now : time();
		$report = array(
			'sent'     => 0,
			'kept'     => 0,
			'lost'     => 0,
			'class'    => '',
			'requests' => 0,
		);
		Capi_Mirror_Store::purge( $now );
		if ( array() === Capi_Mirror_Store::pending( 1 ) ) {
			return $report;
		}
		$token = Capi_Mirror_Store::lock( $now );
		if ( '' === $token ) {
			return $report;
		}
		try {
			// Relues sous le verrou : un envoi qui vient de finir les a peut-être déjà traitées.
			$report = self::send( Capi_Mirror_Store::pending( self::BATCH ), $now, $report );
		} finally {
			Capi_Mirror_Store::unlock( $token );
		}
		return $report;
	}

	/**
	 * Écarte les lignes échues, puis envoie le reste.
	 *
	 * @param array<string, array<string, mixed>> $rows   Lignes décodées, par nom.
	 * @param int                                 $now    Horloge.
	 * @param array<string, mixed>                $report Compte rendu à compléter.
	 * @return array{sent: int, kept: int, lost: int, class: string, requests: int}
	 */
	private static function send( array $rows, int $now, array $report ): array {
		$expired = array();
		foreach ( $rows as $name => $row ) {
			$time = (int) ( $row['e']['event_time'] ?? 0 );
			if ( $now - (int) $row['q'] > self::MAX_WAIT || $now - $time > Config::CAPI_MAX_EVENT_AGE ) {
				$expired[] = $name;
			}
		}
		$rows = array_diff_key( $rows, array_flip( $expired ) );
		self::lose( $expired, $now, 'expired', $report );
		if ( array() === $rows ) {
			return $report;
		}
		// Doublon coupé ou envoi interdit : lignes effacées, rien ne part (pas un échec).
		if ( ! Capi_Mirror::enabled() ) {
			Capi_Mirror_Store::mark( array_keys( $rows ), $now );
			$report['class'] = 'disabled';
			return $report;
		}
		$test = Capi_Signals::test_mode_enabled() ? Config::instance()->meta_test_event_code() : '';
		if ( Capi_Signals::test_mode_enabled() && ! Config::valid_test_event_code( $test ) ) {
			self::lose( array_keys( $rows ), $now, 'test_code_invalid', $report );
			return $report;
		}
		self::deliver( $rows, $now, $test, $report );
		return $report;
	}

	/**
	 * Une requête pour ces lignes, puis la suite donnée à la réponse.
	 *
	 * @param array<string, array<string, mixed>> $rows   Lignes décodées, par nom.
	 * @param int                                 $now    Horloge.
	 * @param string                              $test   Code de test Meta, ou vide.
	 * @param array<string, mixed>                $report Compte rendu (par référence).
	 * @return void
	 */
	private static function deliver( array $rows, int $now, string $test, array &$report ): void {
		$class = self::post( $rows, $test );
		++$report['requests'];
		$report['class'] = $class;
		if ( in_array( $class, array( 'accepted', 'accepted_warning' ), true ) ) {
			Capi_Mirror_Store::mark( array_keys( $rows ), $now );
			$report['sent'] += count( $rows );
			return;
		}
		if ( 'contract' === $class && count( $rows ) > 1 && $report['requests'] + 2 <= self::MAX_REQUESTS ) {
			$half = intdiv( count( $rows ), 2 );
			self::deliver( array_slice( $rows, 0, $half, true ), $now, $test, $report );
			self::deliver( array_slice( $rows, $half, null, true ), $now, $test, $report );
			return;
		}
		if ( 'transient' !== $class ) {
			self::lose( array_keys( $rows ), $now, $class, $report );
			return;
		}
		$spent = array();
		foreach ( $rows as $name => $row ) {
			$row['n'] = (int) $row['n'] + 1;
			if ( $row['n'] >= self::MAX_ATTEMPTS ) {
				$spent[] = $name;
				continue;
			}
			Capi_Mirror_Store::update( $name, $row );
			++$report['kept'];
		}
		self::lose( $spent, $now, $class, $report );
	}

	/**
	 * Envoie les événements par `Capi_Transport` et écrit une ligne au journal (sans donnée client).
	 *
	 * @param array<string, array<string, mixed>> $rows Lignes décodées.
	 * @param string                              $test Code de test Meta, ou vide.
	 * @return string Classe de la réponse.
	 */
	private static function post( array $rows, string $test ): string {
		$cfg     = Config::instance();
		$payload = array( 'data' => array_values( array_column( $rows, 'e' ) ) );
		if ( '' !== $test ) {
			$payload['test_event_code'] = $test;
		}
		$token  = $cfg->meta_access_token();
		$result = Capi_Transport::send_events( $cfg->meta_pixel_id(), $token, $payload, '', count( $rows ) );
		$class  = (string) ( $result['class'] ?? '' );
		Capi_Log::write(
			array(
				'event_name' => 'BrowserMirror',
				'event_id'   => 'batch-' . count( $rows ),
				'state'      => in_array( $class, array( 'accepted', 'accepted_warning' ), true ) ? 'accepted' : 'failed',
				'test'       => '' !== $test,
			) + $result,
			$token
		);
		return $class;
	}

	/**
	 * Écarte des lignes (données effacées) et les compte comme perdues.
	 *
	 * @param array<int, string>   $names  Lignes.
	 * @param int                  $now    Horloge.
	 * @param string               $reason Classe ou motif.
	 * @param array<string, mixed> $report Compte rendu (par référence).
	 * @return void
	 */
	private static function lose( array $names, int $now, string $reason, array &$report ): void {
		if ( array() === $names ) {
			return;
		}
		Capi_Mirror_Store::mark( $names, $now );
		$report['lost'] += count( $names );
		Capi_Mirror_Health::record_many( array( $reason => count( $names ) ), $now );
	}
}
