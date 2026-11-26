<?php
/**
 * Doublon serveur (lot 59) : refus, écarts et pertes dans l'état de santé de la mesure Meta.
 *
 * Trois familles, comptées sur 7 jours glissants par motif :
 * - refusés à l'arrivée (I1, I2, M4) : origine étrangère (`origin`), plafond
 *   du site (`site`), limite par groupe d'adresses (`rate`), entrée invalide
 *   (`content`), event_id déjà reçu depuis 24 h (`replay`) ;
 * - corrigés (M3, M4) : valeur, quantité ou nombre de lignes ramené à la
 *   borne ; l'événement part, mais sa valeur peut différer de celle du Pixel (`adjusted`) ;
 * - perdus après la mise en file : refus de Meta, tentatives épuisées,
 *   attente de plus d'une heure, code de test invalide.
 * Tout est affiché en ORANGE : ce n'est pas l'événement d'optimisation
 * (OrderPlaced, Purchase), l'état ne devient jamais rouge pour lui et aucun
 * e-mail n'en part (`Capi_Health_Alert` n'écrit que pour le rouge). Branché par
 * les filtres `famma_capi_health_reasons` et `famma_capi_health_label`.
 *
 * Une seule écriture par lot reçu, et aucune quand tout va bien.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Compteurs du doublon pour l'état de santé.
 */
final class Capi_Mirror_Health {

	/**
	 * Option des compteurs : since, at, reasons (motif → nombre).
	 */
	public const OPTION = 'famma_capi_mirror_lost';

	/**
	 * Motifs comptés comme refus à l'arrivée.
	 */
	public const REFUSALS = array( 'origin', 'site', 'rate', 'content', 'replay' );

	/**
	 * Ajoute des comptes par motif, en une écriture ; le compteur repart après 7 jours.
	 *
	 * @param array<string, int> $counts Motif → nombre (les zéros sont ignorés).
	 * @param int                $now    Horloge.
	 * @return void
	 */
	public static function record_many( array $counts, int $now ): void {
		$counts = array_filter( array_map( 'intval', $counts ), static fn( int $n ): bool => $n > 0 );
		if ( array() === $counts ) {
			return;
		}
		$state   = self::state( $now );
		$reasons = (array) ( $state['reasons'] ?? array() );
		foreach ( $counts as $reason => $count ) {
			$key             = substr( (string) preg_replace( '/[^a-z_]/', '', (string) $reason ), 0, 40 );
			$reasons[ $key ] = (int) ( $reasons[ $key ] ?? 0 ) + $count;
		}
		update_option(
			self::OPTION,
			array(
				'since'   => (int) $state['since'],
				'at'      => $now,
				'reasons' => array_slice( $reasons, 0, 20, true ),
			),
			false
		);
	}

	/**
	 * Compteurs de la fenêtre en cours (vides si la fenêtre de 7 jours est passée).
	 *
	 * @param int $now Horloge.
	 * @return array<string, mixed>
	 */
	private static function state( int $now ): array {
		$state = get_option( self::OPTION, array() );
		return is_array( $state ) && is_array( $state['reasons'] ?? null ) && $now - (int) ( $state['since'] ?? 0 ) < Capi_Health::SIGNAL_WINDOW ? $state : array( 'since' => $now );
	}

	/**
	 * Totaux par famille : refusés, corrigés, perdus.
	 *
	 * @param int $now Horloge.
	 * @return array{refused: int, adjusted: int, lost: int}
	 */
	public static function totals( int $now ): array {
		$state  = self::state( $now );
		$totals = array(
			'refused'  => 0,
			'adjusted' => 0,
			'lost'     => 0,
		);
		if ( $now - (int) ( $state['at'] ?? 0 ) >= Capi_Health::SIGNAL_WINDOW ) {
			return $totals;
		}
		foreach ( (array) ( $state['reasons'] ?? array() ) as $reason => $count ) {
			$family             = in_array( $reason, self::REFUSALS, true ) ? 'refused' : ( 'adjusted' === $reason ? 'adjusted' : 'lost' );
			$totals[ $family ] += (int) $count;
		}
		return $totals;
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
		$totals  = self::totals( $now );
		if ( array_sum( $totals ) > 0 ) {
			$state     = self::state( $now );
			$reasons[] = array(
				'code'   => 'mirror_lost',
				'niveau' => 'orange',
				'detail' => $totals['lost'] . '/' . $totals['refused'] . '/' . $totals['adjusted'] . '/' . implode( ',', array_keys( (array) $state['reasons'] ) ),
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
		$parts = array_pad( explode( '/', (string) ( $reason['detail'] ?? '' ), 4 ), 4, '' );
		return sprintf(
			/* translators: 1: events lost after queueing, 2: events refused on arrival, 3: events sent with a corrected value, 4: reason codes. */
			__( 'Doublon serveur des événements navigateur (PageView, ViewContent, AddToCart, InitiateCheckout) sur 7 jours : %1$s perdu(s) après la mise en file, %2$s refusé(s) à l’arrivée, %3$s envoyé(s) avec une valeur ou une quantité corrigée (motifs : %4$s ; voir le journal famma-capi, « BrowserMirror »). OrderPlaced et Purchase ne sont pas concernés.', 'famma-core' ),
			(string) (int) $parts[0],
			(string) (int) $parts[1],
			(string) (int) $parts[2],
			'' !== $parts[3] ? $parts[3] : '-'
		);
	}
}
