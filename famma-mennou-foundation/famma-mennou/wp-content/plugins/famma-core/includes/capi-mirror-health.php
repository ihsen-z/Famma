<?php
/**
 * Doublon serveur (lot 59) : envois, refus, écarts et pertes dans l'état de santé de la mesure Meta.
 *
 * Quatre familles de motifs :
 * - envoyés (`sent`) : acceptés par Meta, base du seuil ;
 * - bruit (N5) : origine étrangère (`origin`), visiteur inconnu du journal
 *   (`unknown`), limite par groupe d'adresses (`rate`), event_id déjà reçu
 *   depuis 24 h (`replay`). N'importe qui peut les provoquer de l'extérieur :
 *   ils ne sont QUE comptés (réglage « Doublon serveur »), jamais en orange ;
 * - corrigés (`adjusted`) : valeur, quantité ou nombre de lignes ramené à la
 *   borne ; l'événement part : compteur seulement ;
 * - problèmes : événements réels perdus ou refusés — plafond du site
 *   (`site`), entrée invalide (`content`), lot trop gros (`oversize`), refus
 *   de Meta, tentatives épuisées, attente de plus d'une heure, code de test invalide.
 *
 * ORANGE seulement si, sur les 24 dernières heures, les problèmes atteignent
 * à la fois {@see ALERT_MIN} événements et {@see ALERT_SHARE} % des événements
 * (envoyés + problèmes) ; seuils réglables par le filtre
 * `famma_capi_mirror_alert_thresholds`. Jamais rouge, aucun e-mail
 * (`Capi_Health_Alert` n'écrit que pour le rouge) : ce n'est pas l'événement
 * d'optimisation (OrderPlaced, Purchase). Branché par les filtres
 * `famma_capi_health_reasons` et `famma_capi_health_label`.
 *
 * Stockage : une option, compteurs par heure sur 24 h (`h`) et par motif sur
 * 7 jours glissants (`reasons`, affichés dans le réglage). Une seule écriture
 * par lot reçu ou par tâche d'envoi, et aucune quand rien n'est à compter.
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
	 * Option des compteurs : since, at, reasons (7 jours), h (heure → motif → nombre).
	 */
	public const OPTION = 'famma_capi_mirror_lost';

	/**
	 * Motifs de bruit : comptés, jamais en orange (N5).
	 */
	public const NOISE = array( 'origin', 'unknown', 'rate', 'replay' );

	/**
	 * Problèmes sur 24 h à partir desquels l'orange peut s'allumer (filtre `famma_capi_mirror_alert_thresholds`).
	 */
	public const ALERT_MIN = 20;

	/**
	 * Part des problèmes sur 24 h, en %, à partir de laquelle l'orange s'allume.
	 */
	public const ALERT_SHARE = 5;

	/**
	 * Ajoute des comptes par motif, en une écriture.
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
		$reasons = (array) $state['reasons'];
		$hour    = intdiv( $now, HOUR_IN_SECONDS );
		$hours   = self::recent( (array) ( $state['h'] ?? array() ), $hour );
		foreach ( $counts as $reason => $count ) {
			$key                    = substr( (string) preg_replace( '/[^a-z_]/', '', (string) $reason ), 0, 40 );
			$reasons[ $key ]        = (int) ( $reasons[ $key ] ?? 0 ) + $count;
			$hours[ $hour ][ $key ] = (int) ( $hours[ $hour ][ $key ] ?? 0 ) + $count;
		}
		update_option(
			self::OPTION,
			array(
				'since'   => (int) $state['since'],
				'at'      => $now,
				'reasons' => array_slice( $reasons, 0, 20, true ),
				'h'       => $hours,
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
		return is_array( $state ) && is_array( $state['reasons'] ?? null ) && $now - (int) ( $state['since'] ?? 0 ) < Capi_Health::SIGNAL_WINDOW ? $state : array(
			'since'   => $now,
			'reasons' => array(),
		);
	}

	/**
	 * Les heures des dernières 24 h seulement.
	 *
	 * @param array<int|string, mixed> $hours Heure → motif → nombre.
	 * @param int                      $hour  Heure en cours.
	 * @return array<int, array<string, int>>
	 */
	private static function recent( array $hours, int $hour ): array {
		$kept = array();
		foreach ( $hours as $at => $counts ) {
			if ( (int) $at > $hour - 24 && (int) $at <= $hour && is_array( $counts ) ) {
				$kept[ (int) $at ] = array_map( 'intval', $counts );
			}
		}
		return $kept;
	}

	/**
	 * Famille d'un motif : sent, noise, adjusted ou issue.
	 *
	 * @param string $reason Motif.
	 * @return string
	 */
	public static function kind( string $reason ): string {
		if ( 'sent' === $reason || 'adjusted' === $reason ) {
			return $reason;
		}
		return in_array( $reason, self::NOISE, true ) ? 'noise' : 'issue';
	}

	/**
	 * Totaux par famille sur 24 h (`day`) ou 7 jours (`week`), et motifs de problème vus.
	 *
	 * @param int    $now    Horloge.
	 * @param string $window `day` ou `week`.
	 * @return array{sent: int, issue: int, noise: int, adjusted: int, codes: array<int, string>, reasons: array<string, int>}
	 */
	public static function totals( int $now, string $window = 'day' ): array {
		$state  = self::state( $now );
		$counts = array();
		if ( 'week' === $window ) {
			$counts = (array) $state['reasons'];
		} else {
			foreach ( self::recent( (array) ( $state['h'] ?? array() ), intdiv( $now, HOUR_IN_SECONDS ) ) as $hour ) {
				foreach ( $hour as $reason => $count ) {
					$counts[ $reason ] = (int) ( $counts[ $reason ] ?? 0 ) + $count;
				}
			}
		}
		$totals = array(
			'sent'     => 0,
			'issue'    => 0,
			'noise'    => 0,
			'adjusted' => 0,
			'codes'    => array(),
			'reasons'  => array_map( 'intval', $counts ),
		);
		foreach ( $totals['reasons'] as $reason => $count ) {
			$family             = self::kind( (string) $reason );
			$totals[ $family ] += $count;
			if ( 'issue' === $family && $count > 0 ) {
				$totals['codes'][] = (string) $reason;
			}
		}
		return $totals;
	}

	/**
	 * Seuils d'alerte en vigueur.
	 *
	 * @return array{min: int, share: int}
	 */
	public static function thresholds(): array {
		/**
		 * Seuils de l'orange du doublon serveur, sur 24 h.
		 *
		 * @param array{min: int, share: int} $thresholds `min` : problèmes au moins ; `share` : part en % au moins.
		 */
		$set = apply_filters(
			'famma_capi_mirror_alert_thresholds',
			array(
				'min'   => self::ALERT_MIN,
				'share' => self::ALERT_SHARE,
			)
		);
		$set = is_array( $set ) ? $set : array();
		return array(
			'min'   => max( 1, (int) ( $set['min'] ?? self::ALERT_MIN ) ),
			'share' => min( 100, max( 0, (int) ( $set['share'] ?? self::ALERT_SHARE ) ) ),
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
		$day     = self::totals( $now );
		$limits  = self::thresholds();
		$base    = $day['sent'] + $day['issue'];
		if ( $day['issue'] >= $limits['min'] && $day['issue'] * 100 >= $limits['share'] * $base ) {
			$reasons[] = array(
				'code'   => 'mirror_lost',
				'niveau' => 'orange',
				'detail' => $day['issue'] . '/' . $base . '/' . $limits['min'] . '/' . $limits['share'] . '/' . implode( ',', $day['codes'] ),
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
		$parts = array_pad( explode( '/', (string) ( $reason['detail'] ?? '' ), 5 ), 5, '' );
		return sprintf(
			/* translators: 1: real events lost or refused over 24 h, 2: events handled over 24 h, 3: minimum count threshold, 4: minimum share threshold in percent, 5: reason codes. */
			__( 'Doublon serveur des événements navigateur (PageView, ViewContent, AddToCart, InitiateCheckout) sur 24 h : %1$s événement(s) réel(s) perdu(s) ou refusé(s) sur %2$s (seuil : %3$s et %4$s %%) ; motifs : %5$s ; voir le journal famma-capi, « BrowserMirror ». Les refus d’origine, de visiteur inconnu, de limite et de rejeu ne sont que comptés (réglage « Doublon serveur »). OrderPlaced et Purchase ne sont pas concernés.', 'famma-core' ),
			(string) (int) $parts[0],
			(string) (int) $parts[1],
			(string) (int) $parts[2],
			(string) (int) $parts[3],
			'' !== $parts[4] ? $parts[4] : '-'
		);
	}
}
