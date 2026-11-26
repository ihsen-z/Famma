<?php
/**
 * Doublon serveur (lot 59) : file légère et envoi groupé à Meta.
 *
 * Une ligne `wp_options` par événement (`famma_mq_<empreinte>`, jamais
 * chargée d'office) : `add_option()` est atomique sur le nom, donc deux
 * relais simultanés ne s'écrasent jamais, et le même nom + event_id relayé
 * deux fois n'est rangé qu'une fois. La ligne ne vit que jusqu'au prochain
 * envoi (une minute à quelques minutes) : elle porte l'IP et le user-agent
 * que Meta attend en clair, jamais le téléphone en clair.
 *
 * Envoi : jusqu'à {@see BATCH} événements en UNE requête (doc Meta « Using
 * the API », Batch Requests : « You can send up to 1,000 events in data »,
 * « ideally within an hour of the event occurring »), sous verrou, au plus
 * tôt {@see FLUSH_AFTER} s après le premier événement en attente, au plus
 * tard à la tâche WP-Cron de 5 minutes. Un événement qui attend plus d'une
 * heure est écarté ; `event_time` reste toujours bien en deçà des 7 jours.
 *
 * Santé : un événement écarté sans accusé de Meta est compté par
 * `Capi_Mirror_Health` (orange, jamais rouge, aucun e-mail).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * File du doublon serveur.
 */
final class Capi_Mirror_Queue {

	/**
	 * Préfixe des lignes de la file.
	 */
	public const PREFIX = 'famma_mq_';

	/**
	 * Heure du plus ancien événement en attente (absente : file vide).
	 */
	public const DUE = 'famma_capi_mirror_due';

	/**
	 * Verrou d'envoi.
	 */
	public const LOCK = 'famma_capi_mirror_lock';

	/**
	 * Attente minimale avant l'envoi au fil des requêtes, en secondes.
	 */
	public const FLUSH_AFTER = 60;

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
	 * Durée de vie d'un verrou abandonné, en secondes.
	 */
	private const LOCK_TTL = 120;

	/**
	 * Range des événements validés ; renvoie le nombre de nouveaux.
	 *
	 * @param array<int, array<string, mixed>> $events Événements Meta (`Capi_Mirror_Event`).
	 * @param int                              $now    Horloge.
	 * @return int
	 */
	public static function push_many( array $events, int $now ): int {
		$added = 0;
		foreach ( $events as $event ) {
			$name = self::PREFIX . substr( hash( 'sha256', $event['event_name'] . '|' . $event['event_id'] ), 0, 32 );
			$row  = wp_json_encode(
				array(
					'e' => $event,
					'q' => $now,
					'n' => 0,
				)
			);
			if ( is_string( $row ) && add_option( $name, $row, '', false ) ) {
				++$added;
			}
		}
		if ( $added > 0 ) {
			add_option( self::DUE, $now, '', false );
		}
		return $added;
	}

	/**
	 * Fin d'une requête du relais : envoi si le plus ancien attend depuis {@see FLUSH_AFTER} s.
	 *
	 * La réponse (204) est d'abord rendue au navigateur, puis Meta est appelé.
	 *
	 * @return void
	 */
	public static function flush_if_due(): void {
		$due = (int) get_option( self::DUE, 0 );
		if ( $due <= 0 || time() - $due < self::FLUSH_AFTER ) {
			return;
		}
		if ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		} elseif ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		self::flush();
	}

	/**
	 * Envoie un lot à Meta (tâche WP-Cron, ou fin de requête du relais).
	 *
	 * @param int $now Horloge (tests), maintenant par défaut.
	 * @return array{sent: int, kept: int, lost: int, class: string}
	 */
	public static function flush( int $now = 0 ): array {
		$now    = $now > 0 ? $now : time();
		$report = array(
			'sent'  => 0,
			'kept'  => 0,
			'lost'  => 0,
			'class' => '',
		);
		if ( (int) get_option( self::DUE, 0 ) <= 0 || ! self::lock( $now ) ) {
			return $report;
		}
		try {
			$report = self::send( self::rows( self::BATCH ), $now, $report );
			if ( array() === self::rows( 1 ) ) {
				delete_option( self::DUE );
			} else {
				update_option( self::DUE, $now, false );
			}
		} finally {
			delete_option( self::LOCK );
		}
		return $report;
	}

	/**
	 * Envoi d'un lot de lignes et suite donnée à la réponse.
	 *
	 * @param array<string, array<string, mixed>> $rows   Lignes décodées, par nom.
	 * @param int                                 $now    Horloge.
	 * @param array<string, mixed>                $report Compte rendu à compléter.
	 * @return array{sent: int, kept: int, lost: int, class: string}
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

		$cfg  = Config::instance();
		$test = Capi_Signals::test_mode_enabled() ? $cfg->meta_test_event_code() : '';
		// Doublon coupé ou envoi interdit : la file est vidée, rien ne part (pas un échec).
		if ( ! Capi_Mirror::enabled() ) {
			self::delete( array_keys( $rows ) );
			$report['class'] = 'disabled';
			return $report;
		}
		if ( Capi_Signals::test_mode_enabled() && ! Config::valid_test_event_code( $test ) ) {
			self::lose( array_keys( $rows ), $now, 'test_code_invalid', $report );
			return $report;
		}

		$payload = array( 'data' => array_values( array_column( $rows, 'e' ) ) );
		if ( '' !== $test ) {
			$payload['test_event_code'] = $test;
		}
		$token  = $cfg->meta_access_token();
		$result = Capi_Transport::send_events( $cfg->meta_pixel_id(), $token, $payload, '', count( $rows ) );
		$class  = (string) ( $result['class'] ?? '' );
		$ok     = in_array( $class, array( 'accepted', 'accepted_warning' ), true );
		Capi_Log::write(
			array(
				'event_name' => 'BrowserMirror',
				'event_id'   => 'batch-' . count( $rows ),
				'state'      => $ok ? 'accepted' : 'failed',
				'test'       => '' !== $test,
			) + $result,
			$token
		);
		$report['class'] = $class;

		if ( $ok ) {
			self::delete( array_keys( $rows ) );
			$report['sent'] = count( $rows );
			return $report;
		}
		if ( 'transient' !== $class ) {
			self::lose( array_keys( $rows ), $now, $class, $report );
			return $report;
		}
		$spent = array();
		foreach ( $rows as $name => $row ) {
			$row['n'] = (int) $row['n'] + 1;
			if ( $row['n'] >= self::MAX_ATTEMPTS ) {
				$spent[] = $name;
				continue;
			}
			update_option( $name, (string) wp_json_encode( $row ), false );
			++$report['kept'];
		}
		self::lose( $spent, $now, $class, $report );
		return $report;
	}

	/**
	 * Lignes de la file, les plus anciennes d'abord, décodées.
	 *
	 * @param int $limit Nombre maximal.
	 * @return array<string, array<string, mixed>>
	 */
	private static function rows( int $limit ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- File d'attente : lecture fraîche voulue, jamais mise en cache.
		$found = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d", $wpdb->esc_like( self::PREFIX ) . '%', $limit ), ARRAY_A );
		$rows  = array();
		foreach ( is_array( $found ) ? $found : array() as $line ) {
			$row = json_decode( (string) ( $line['option_value'] ?? '' ), true );
			if ( is_array( $row ) && isset( $row['e'], $row['q'] ) && is_array( $row['e'] ) ) {
				$rows[ (string) $line['option_name'] ] = $row + array( 'n' => 0 );
			} else {
				self::delete( array( (string) ( $line['option_name'] ?? '' ) ) );
			}
		}
		return $rows;
	}

	/**
	 * Supprime des lignes en une requête.
	 *
	 * @param array<int, string> $names Noms des lignes.
	 * @return void
	 */
	private static function delete( array $names ): void {
		$names = array_values( array_filter( $names, static fn( string $n ): bool => str_starts_with( $n, self::PREFIX ) ) );
		if ( array() === $names ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Une seule suppression préparée pour tout le lot ; la file n'est jamais mise en cache.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name IN (" . implode( ',', array_fill( 0, count( $names ), '%s' ) ) . ')', $names ) );
		foreach ( $names as $name ) {
			wp_cache_delete( $name, 'options' );
		}
	}

	/**
	 * Écarte des lignes et les compte comme perdues pour l'état de santé.
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
		self::delete( $names );
		$report['lost'] += count( $names );
		Capi_Mirror_Health::record( count( $names ), $reason, $now );
	}

	/**
	 * Verrou d'envoi : `add_option()` échoue si un autre envoi le tient.
	 *
	 * @param int $now Horloge.
	 * @return bool
	 */
	private static function lock( int $now ): bool {
		if ( add_option( self::LOCK, $now, '', false ) ) {
			return true;
		}
		if ( $now - (int) get_option( self::LOCK, 0 ) <= self::LOCK_TTL ) {
			return false;
		}
		delete_option( self::LOCK );
		return add_option( self::LOCK, $now, '', false );
	}
}
