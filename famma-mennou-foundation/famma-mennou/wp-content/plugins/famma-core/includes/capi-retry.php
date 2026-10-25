<?php
/**
 * Relance bornée des envois CAPI incertains (lot 5, D1, A6).
 *
 * Jusqu'en 0.3.1, un envoi incertain (WP_Error, délai dépassé, 5xx, 200
 * sans accusé) restait incertain pour toujours : le Purchase était perdu
 * alors que la note annonçait « tentative 1 sur 5 » (T2.13).
 *
 * Désormais, chaque échec transitoire planifie une seule relance Action
 * Scheduler (`famma_capi_retry`, groupe `famma-capi`, arguments `order_id`
 * et `event_name`) après 5 min, 30 min, 2 h puis 6 h, avec le même
 * event_id. Pas de relance au-delà de `Config::CAPI_MAX_ATTEMPTS`
 * tentatives, ni pour un event_time de plus de 6 jours (Meta refuse un
 * événement de plus de 7 jours, E7). Un refus d'accès ou de contrat n'est
 * jamais relancé : il alerte (état de santé).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Planification des relances ; l'exécution vit dans `Capi::send_retry()`.
 */
final class Capi_Retry {

	/**
	 * Crochet de la relance.
	 */
	public const HOOK = 'famma_capi_retry';

	/**
	 * Groupe Action Scheduler, partagé avec la file OrderPlaced.
	 */
	public const GROUP = 'famma-capi';

	/**
	 * Statut « en attente » d'Action Scheduler (`ActionScheduler_Store::STATUS_PENDING`).
	 */
	private const PENDING = 'pending';

	/**
	 * Délai avant la prochaine tentative, ou -1 s'il n'y en a plus.
	 *
	 * @param int $attempts Tentatives déjà faites (1 après le premier envoi).
	 * @return int Secondes.
	 */
	public static function delay_after( int $attempts ): int {
		if ( $attempts < 1 || $attempts >= Config::CAPI_MAX_ATTEMPTS ) {
			return -1;
		}
		$delays = Config::CAPI_RETRY_DELAYS;

		return (int) $delays[ min( $attempts - 1, count( $delays ) - 1 ) ];
	}

	/**
	 * Planifie la prochaine relance d'un événement, une seule à la fois.
	 *
	 * @param int    $order_id   Order identifier.
	 * @param string $event_name OrderPlaced or Purchase.
	 * @param int    $attempts   Tentatives déjà faites.
	 * @param int    $event_time Horodatage immuable de l'événement.
	 * @param int    $now        Horloge (tests), maintenant par défaut.
	 * @return string `scheduled`, `already`, `exhausted`, `too_old`, `failed` ou `unavailable`.
	 */
	public static function schedule( int $order_id, string $event_name, int $attempts, int $event_time, int $now = 0 ): string {
		$now   = $now > 0 ? $now : time();
		$delay = self::delay_after( $attempts );
		if ( $delay < 0 ) {
			return 'exhausted';
		}
		$when = $now + $delay;
		if ( $event_time <= 0 || $when - $event_time > Config::CAPI_MAX_EVENT_AGE ) {
			return 'too_old';
		}
		$args = self::args( $order_id, $event_name );
		if ( function_exists( 'as_schedule_single_action' ) ) {
			return self::schedule_action( $when, $args );
		}
		if ( function_exists( 'wp_schedule_single_event' ) ) {
			if ( wp_next_scheduled( self::HOOK, $args ) ) {
				return 'already';
			}
			return false !== wp_schedule_single_event( $when, self::HOOK, $args ) ? 'scheduled' : 'failed';
		}
		return 'unavailable';
	}

	/**
	 * Ajoute la relance dans Action Scheduler sans jamais doubler une relance en attente.
	 *
	 * Action Scheduler 4.0 compte une action « en cours » comme existante, dans
	 * `as_has_scheduled_action()` comme dans l'ajout « unique » (même crochet,
	 * même groupe, mêmes arguments, statut en attente OU en cours). La relance
	 * qui échoue à son tour se verrait donc elle-même, et la chaîne 5 min,
	 * 30 min, 2 h, 6 h s'arrêterait après la première relance. Seules les
	 * relances EN ATTENTE comptent : l'ajout « unique » est tenté d'abord (il
	 * protège de deux planifications simultanées), puis un ajout simple quand
	 * seule une action en cours l'a refusé. Un doublon éventuel resterait sans
	 * effet : la réservation atomique du registre n'autorise qu'un envoi.
	 *
	 * @param int                                      $when Horodatage de la relance.
	 * @param array{order_id: int, event_name: string} $args Arguments de la tâche.
	 * @return string `scheduled`, `already` ou `failed`.
	 */
	private static function schedule_action( int $when, array $args ): string {
		if ( self::pending( $args ) ) {
			return 'already';
		}
		$action_id = (int) as_schedule_single_action( $when, self::HOOK, $args, self::GROUP, true );
		if ( $action_id <= 0 ) {
			if ( self::pending( $args ) ) {
				return 'already';
			}
			$action_id = (int) as_schedule_single_action( $when, self::HOOK, $args, self::GROUP, false );
		}
		return $action_id > 0 ? 'scheduled' : 'failed';
	}

	/**
	 * Une relance de cet événement attend-elle déjà son tour ?
	 *
	 * Une relance « en cours » (celle qui appelle, typiquement) ne compte pas.
	 *
	 * @param array{order_id: int, event_name: string} $args Arguments de la tâche.
	 * @return bool
	 */
	private static function pending( array $args ): bool {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return false;
		}
		$ids = as_get_scheduled_actions(
			array(
				'hook'     => self::HOOK,
				'args'     => $args,
				'group'    => self::GROUP,
				'status'   => self::PENDING,
				'per_page' => 1,
			),
			'ids'
		);
		return is_array( $ids ) && array() !== $ids;
	}

	/**
	 * Arguments de la tâche ; leurs noms sont ceux de `Capi::send_retry()`.
	 *
	 * @param int    $order_id   Order identifier.
	 * @param string $event_name OrderPlaced or Purchase.
	 * @return array{order_id: int, event_name: string}
	 */
	public static function args( int $order_id, string $event_name ): array {
		return array(
			'order_id'   => $order_id,
			'event_name' => $event_name,
		);
	}

	/**
	 * Reprend les envois restés « en cours » plus de 15 minutes.
	 *
	 * Un processus interrompu en plein envoi ne planifie rien : ce balayage,
	 * lancé par le contrôle de santé et le battement de cœur, fait passer
	 * chaque envoi abandonné à « incertain » (D1), puis planifie sa relance
	 * quand elle est encore possible. Même sans relance possible (trop
	 * ancien, tentatives épuisées), la ligne quitte l'état « en cours » : le
	 * balayage, borné à 20 lignes, n'est jamais bloqué par des envois morts.
	 * Une ligne `famma-capi` par envoi repris.
	 *
	 * @return int Relances planifiées.
	 */
	public static function rescue_stale(): int {
		$registry = Capi_Delivery_Registry::instance();
		$count    = 0;
		foreach ( $registry->stale_sending( Config::CAPI_STALE_SENDING ) as $row ) {
			if ( ! $registry->release_stale( $row ) ) {
				continue;
			}
			$result = self::schedule( (int) $row['order_id'], (string) $row['event_name'], max( 1, (int) $row['attempt_count'] ), (int) $row['event_time'] );
			$count += 'scheduled' === $result ? 1 : 0;
			Capi_Log::outcome(
				array(
					'order_id'   => (int) $row['order_id'],
					'event_name' => (string) $row['event_name'],
					'event_id'   => (string) $row['event_id'],
				),
				'stale_sending',
				'retry:' . $result
			);
		}
		return $count;
	}
}
