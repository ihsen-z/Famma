<?php
/**
 * Doublon serveur (lot 59) : événements perdus dans l'état de santé de la mesure Meta.
 *
 * Un événement écarté sans accusé de Meta (refus, tentatives épuisées, attente
 * de plus d'une heure, code de test invalide) est compté sur 7 jours glissants
 * et affiché en ORANGE : ce n'est pas l'événement d'optimisation (OrderPlaced,
 * Purchase), l'état ne devient jamais rouge pour lui et aucun e-mail n'en part
 * (`Capi_Health_Alert` n'écrit que pour le rouge). Branché par les filtres
 * `famma_capi_health_reasons` et `famma_capi_health_label` (`Capi_Health_Rules`).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Compteur des événements du doublon perdus.
 */
final class Capi_Mirror_Health {

	/**
	 * Option du compteur : since, count, reason, at.
	 */
	public const OPTION = 'famma_capi_mirror_lost';

	/**
	 * Compte des événements perdus ; le compteur repart après 7 jours.
	 *
	 * @param int    $count  Événements perdus.
	 * @param string $reason Classe du transport ou motif.
	 * @param int    $now    Horloge.
	 * @return void
	 */
	public static function record( int $count, string $reason, int $now ): void {
		$lost = get_option( self::OPTION, array() );
		$lost = is_array( $lost ) && $now - (int) ( $lost['since'] ?? 0 ) < Capi_Health::SIGNAL_WINDOW ? $lost : array( 'since' => $now );
		update_option(
			self::OPTION,
			array(
				'since'  => (int) $lost['since'],
				'count'  => (int) ( $lost['count'] ?? 0 ) + $count,
				'reason' => (string) preg_replace( '/[^a-z_]/', '', $reason ),
				'at'     => $now,
			),
			false
		);
	}

	/**
	 * Raison orange de l'état de santé (filtre `famma_capi_health_reasons`).
	 *
	 * @param mixed $reasons Raisons ajoutées.
	 * @param mixed $signals Signaux (inutilisés).
	 * @param mixed $now     Horloge.
	 * @return array<int, array<string, string>>
	 */
	public static function health_reasons( $reasons, $signals = array(), $now = 0 ): array {
		$reasons = is_array( $reasons ) ? $reasons : array();
		$now     = (int) $now > 0 ? (int) $now : time();
		$lost    = get_option( self::OPTION, array() );
		if ( is_array( $lost ) && (int) ( $lost['count'] ?? 0 ) > 0 && $now - (int) ( $lost['at'] ?? 0 ) < Capi_Health::SIGNAL_WINDOW ) {
			$reasons[] = array(
				'code'   => 'mirror_lost',
				'niveau' => 'orange',
				'detail' => (string) (int) $lost['count'],
			);
		}
		return $reasons;
	}

	/**
	 * Libellé de la raison (filtre `famma_capi_health_label`).
	 *
	 * @param mixed $label  Libellé déjà trouvé.
	 * @param mixed $reason Raison.
	 * @return mixed
	 */
	public static function health_label( $label, $reason = array() ) {
		if ( ! is_array( $reason ) || 'mirror_lost' !== ( $reason['code'] ?? '' ) ) {
			return $label;
		}
		/* translators: %s: number of browser events not delivered by the server duplicate. */
		return sprintf( __( '%s événement(s) navigateur (PageView, ViewContent, AddToCart, InitiateCheckout) n’ont pas pu partir en doublon par le serveur sur 7 jours (voir le journal famma-capi, « BrowserMirror »). OrderPlaced et Purchase ne sont pas concernés.', 'famma-core' ), (string) ( $reason['detail'] ?? '' ) );
	}
}
