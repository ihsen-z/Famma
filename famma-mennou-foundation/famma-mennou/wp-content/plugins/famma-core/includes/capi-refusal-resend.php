<?php
/**
 * Renvoi automatique des événements refusés par Meta (phase B des réformes du pixel, lot 4, D3).
 *
 * Avant ce lot, un refus de Meta (jeton invalide : classe `access` ; contenu
 * refusé : `contract`) n'était jamais relancé : « Renvoyer » commande par
 * commande. Désormais :
 *
 * - chaque refus lève le drapeau `famma_capi_refusal_pending` (`observe()`) ;
 * - au premier envoi accepté qui suit, ou quand la sonde du jeton redevient
 *   valide, un passage est mis en file (`repaired()`, tâche
 *   `famma_capi_resend_refused`) ;
 * - le passage (`sweep()`) relit le registre : refus `access` / `contract`
 *   de moins de 6 jours (`Config::CAPI_MAX_EVENT_AGE`), tentatives non
 *   épuisées (filtré en SQL, relecture M2), 10 renvois au plus (le reste une
 *   minute plus tard). Un événement réellement renvoyé l'est UNE fois
 *   (`famma_capi_auto_resent`, purgée après 7 jours) ; une issue sans envoi
 *   (non configuré, environnement, registre indisponible, envoi en cours) ou
 *   un événement écarté ne le marque pas : il sera repris au passage suivant.
 *
 * Renvoi par `Capi::resend()`, même event_id : réservation du registre
 * (anti-doublon), essais exclus, Purchase revérifié « encore Livrée ».
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Drapeau des refus, déclenchement à la réparation et passage borné.
 */
final class Capi_Refusal_Resend {

	/**
	 * Tâche du passage de renvoi.
	 */
	public const HOOK = 'famma_capi_resend_refused';

	/**
	 * Option : date du dernier refus pas encore suivi d'une réparation.
	 */
	public const PENDING = 'famma_capi_refusal_pending';

	/**
	 * Option : événements déjà renvoyés automatiquement (clé du registre => date).
	 */
	public const DONE = 'famma_capi_auto_resent';

	/**
	 * Renvois au plus par passage.
	 */
	public const BATCH = 10;

	/**
	 * Classes de transport d'un refus de Meta.
	 */
	public const CLASSES = array( 'access', 'contract' );

	/**
	 * Issues sans envoi réel : l'événement reste à renvoyer.
	 */
	private const NOT_SENT = array( 'not_configured', 'blocked_environment', 'registry_unavailable', 'in_progress', 'test_code_invalid', 'disabled' );

	/**
	 * Instance unique.
	 *
	 * @var Capi_Refusal_Resend|null
	 */
	private static ?Capi_Refusal_Resend $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Refusal_Resend
	 */
	public static function instance(): Capi_Refusal_Resend {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( self::HOOK, array( self::$i, 'run' ) );
		}
		return self::$i;
	}

	/**
	 * Retient un refus, ou déclenche le renvoi au premier envoi accepté qui suit.
	 *
	 * @param string $response_class Classe du transport.
	 * @param bool   $had_refusal    Un signal de refus était encore enregistré.
	 * @return void
	 */
	public static function observe( string $response_class, bool $had_refusal ): void {
		if ( in_array( $response_class, self::CLASSES, true ) ) {
			update_option( self::PENDING, time(), false );
			return;
		}
		if ( in_array( $response_class, array( 'accepted', 'accepted_warning' ), true ) && ( $had_refusal || self::pending() ) ) {
			self::repaired();
		}
	}

	/**
	 * Un refus attend-il une réparation ?
	 *
	 * @return bool
	 */
	public static function pending(): bool {
		return (int) get_option( self::PENDING, 0 ) > 0;
	}

	/**
	 * Le problème est réglé : met le passage en file, sans bloquer l'envoi en cours.
	 *
	 * @return bool Vrai si un passage attend.
	 */
	public static function repaired(): bool {
		delete_option( self::PENDING );
		return self::schedule( time() );
	}

	/**
	 * Exécution de la tâche.
	 *
	 * @return void
	 */
	public function run(): void {
		self::sweep( time() );
	}

	/**
	 * Renvoie, une seule fois chacun, les refus de moins de 6 jours (10 au plus).
	 *
	 * @param int $now Horloge.
	 * @return array{resent: int, skipped: int, left: bool}
	 */
	public static function sweep( int $now ): array {
		$done   = get_option( self::DONE, array() );
		$done   = array_filter( is_array( $done ) ? $done : array(), static fn( $at ): bool => $now - (int) $at < Capi_Health::SIGNAL_WINDOW );
		$result = array(
			'resent'  => 0,
			'skipped' => 0,
			'left'    => false,
		);
		foreach ( Capi_Delivery_Registry::instance()->rejected_since( $now - Config::CAPI_MAX_EVENT_AGE, Config::CAPI_MAX_ATTEMPTS, 100 ) as $row ) {
			$key = (string) ( $row['event_key'] ?? '' );
			if ( '' === $key || isset( $done[ $key ] ) || ! in_array( self::refusal_class( $row ), self::CLASSES, true ) ) {
				continue;
			}
			if ( $result['resent'] >= self::BATCH ) {
				$result['left'] = true;
				break;
			}
			$base = array(
				'order_id'   => (int) ( $row['order_id'] ?? 0 ),
				'event_name' => (string) ( $row['event_name'] ?? '' ),
				'event_id'   => (string) ( $row['event_id'] ?? '' ),
			);
			$skip = self::skip_reason( $base['order_id'], $base['event_name'] );
			if ( '' !== $skip ) {
				++$result['skipped'];
				Capi_Log::outcome( $base, 'auto_resend_skipped', $skip );
				continue;
			}
			$outcome = Capi::instance()->resend( wc_get_order( $base['order_id'] ), $base['event_name'] );
			++$result['resent'];
			if ( ! in_array( $outcome, self::NOT_SENT, true ) ) {
				$done[ $key ] = $now;
			}
			Capi_Log::outcome( $base, 'auto_resend', $outcome );
		}
		update_option( self::DONE, $done, false );
		if ( $result['left'] ) {
			self::schedule( $now + MINUTE_IN_SECONDS );
		}
		return $result;
	}

	/**
	 * Pourquoi un refus n'est pas renvoyé maintenant, ou vide.
	 *
	 * @param int    $order_id   Commande.
	 * @param string $event_name Événement.
	 * @return string `order_missing`, `test_order`, `purchase:<motif>` ou vide.
	 */
	private static function skip_reason( int $order_id, string $event_name ): string {
		$order = $order_id > 0 ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof \WC_Order ) {
			return 'order_missing';
		}
		if ( Capi::is_test_order( $order ) ) {
			return 'test_order';
		}
		$why = 'Purchase' === $event_name ? Order_Statuses::purchase_ineligibility( $order ) : '';
		return '' !== $why ? 'purchase:' . $why : '';
	}

	/**
	 * Classe de transport de la dernière tentative d'une ligne du registre.
	 *
	 * @param array<string, mixed> $row Ligne du registre.
	 * @return string
	 */
	private static function refusal_class( array $row ): string {
		$trace = json_decode( (string) ( $row['attempts'] ?? '' ), true );
		$last  = is_array( $trace ) && array() !== $trace ? end( $trace ) : array();
		return is_array( $last ) ? (string) ( $last['class'] ?? '' ) : '';
	}

	/**
	 * Met le passage en file, une seule tâche EN ATTENTE (le passage en cours
	 * qui se replanifie ne compte pas, comme pour `Capi_Retry`).
	 *
	 * @param int $when Horodatage.
	 * @return bool
	 */
	private static function schedule( int $when ): bool {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			$query   = array(
				'hook'     => self::HOOK,
				'group'    => Capi_Retry::GROUP,
				'status'   => 'pending',
				'per_page' => 1,
			);
			$waiting = function_exists( 'as_get_scheduled_actions' ) ? as_get_scheduled_actions( $query, 'ids' ) : array();
			if ( is_array( $waiting ) && array() !== $waiting ) {
				return true;
			}
			if ( (int) as_schedule_single_action( $when, self::HOOK, array(), Capi_Retry::GROUP, true ) > 0 || (int) as_schedule_single_action( $when, self::HOOK, array(), Capi_Retry::GROUP, false ) > 0 ) {
				return true;
			}
		}
		if ( ! function_exists( 'wp_schedule_single_event' ) ) {
			return false;
		}
		return false !== wp_next_scheduled( self::HOOK ) || false !== wp_schedule_single_event( $when, self::HOOK );
	}
}
