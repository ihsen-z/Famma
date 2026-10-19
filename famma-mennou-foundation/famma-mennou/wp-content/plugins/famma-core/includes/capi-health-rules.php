<?php
/**
 * Règles de l'état de santé de la mesure Meta (lot 5, R7, A4).
 *
 * Rouge : jeton illisible, jeton ou droits refusés par Meta, registre
 * d'envoi indisponible, battement de cœur de plus de 36 h, tâche
 * `famma-capi` en retard de plus de 2 h, commande livrée (ou terminée) sans
 * Purchase depuis plus de 24 h et encore renvoyable, version critique (R1),
 * jeton refusé par la sonde (190).
 * Orange : non configuré, envoi bloqué par l'environnement (D4), version
 * servie différente de la demandée, profil « minimal » actif, fbc refusés
 * localement sur 7 jours, mode test sans code valide, refus de contrat,
 * sonde indisponible, Purchase manquant qu'aucun renvoi ne peut plus
 * rattraper (`Capi_Purchase_Gaps`).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Calcul des raisons et de leurs libellés.
 */
final class Capi_Health_Rules {

	/**
	 * Raisons actives, chacune `array( 'code' => ..., 'niveau' => 'rouge'|'orange', 'detail' => ... )`.
	 *
	 * @param array<string, array<string, mixed>> $signals Signaux enregistrés.
	 * @param int                                 $now     Horloge.
	 * @return array<int, array<string, string>>
	 */
	public static function reasons( array $signals, int $now ): array {
		$cfg        = Config::instance();
		$reasons    = array();
		$configured = '' !== $cfg->meta_pixel_id() && '' !== $cfg->meta_access_token();

		if ( 'broken' === Capi_Token_Vault::state() ) {
			$reasons[] = self::reason( 'token_unreadable', 'rouge' );
		}
		if ( ! $configured ) {
			$reasons[] = self::reason( 'not_configured', 'orange' );
		}
		if ( ! $cfg->meta_sending_allowed() ) {
			$reasons[] = self::reason( 'environment', 'orange', $cfg->environment_type() );
		}
		if ( isset( $signals['access'] ) ) {
			$reasons[] = self::reason( 'access', 'rouge', (string) (int) ( $signals['access']['code'] ?? 0 ) );
		}
		if ( isset( $signals['contract'] ) && $now - (int) ( $signals['contract']['at'] ?? 0 ) < Capi_Health::SIGNAL_WINDOW ) {
			$reasons[] = self::reason( 'contract', 'orange', (string) (int) ( $signals['contract']['code'] ?? 0 ) );
		}
		if ( isset( $signals['registry_unavailable'] ) || '1' !== (string) get_option( 'famma_capi_registry_version', '' ) ) {
			$reasons[] = self::reason( 'registry', 'rouge' );
		}
		$heartbeat = (int) get_option( Capi_Health::HEARTBEAT, 0 );
		if ( $heartbeat > 0 && $now - $heartbeat > Capi_Health::HEARTBEAT_MAX_AGE ) {
			$reasons[] = self::reason( 'heartbeat', 'rouge', (string) (int) floor( ( $now - $heartbeat ) / 3600 ) );
		}
		if ( self::late_actions( $now ) ) {
			$reasons[] = self::reason( 'pending_actions', 'rouge' );
		}
		$gaps = $configured && $cfg->meta_sending_allowed() ? Capi_Purchase_Gaps::count( $now ) : array(
			'missing' => 0,
			'lost'    => 0,
		);
		if ( $gaps['missing'] > 0 ) {
			$reasons[] = self::reason( 'purchase_missing', 'rouge', (string) $gaps['missing'] );
		}
		if ( $gaps['lost'] > 0 ) {
			$reasons[] = self::reason( 'purchase_lost', 'orange', (string) $gaps['lost'] );
		}
		$version = Capi_Graph_Version::status( $cfg->meta_graph_version(), $now );
		if ( 'critical' === $version['level'] ) {
			$reasons[] = self::reason( 'version_critical', 'rouge', $cfg->meta_graph_version() );
		}
		if ( ! empty( $signals['served']['mismatch'] ) ) {
			$reasons[] = self::reason( 'version_served', 'orange', (string) ( $signals['served']['served'] ?? '' ) );
		}
		if ( 'minimal' === $cfg->meta_data_profile() ) {
			$reasons[] = self::reason( 'profile_minimal', 'orange' );
		}
		$fbc = self::fbc_rejected( $now );
		if ( $fbc > 0 ) {
			$reasons[] = self::reason( 'fbc_rejected', 'orange', (string) $fbc );
		}
		if ( Capi_Signals::test_mode_enabled() && ! Config::valid_test_event_code( $cfg->meta_test_event_code() ) ) {
			$reasons[] = self::reason( 'test_code_invalid', 'orange' );
		}
		return array_merge( $reasons, self::probe_reasons( $signals ) );
	}

	/**
	 * Raisons tirées de la dernière sonde : seul un jeton refusé (190) est rouge.
	 *
	 * @param array<string, array<string, mixed>> $signals Signaux enregistrés.
	 * @return array<int, array<string, string>>
	 */
	private static function probe_reasons( array $signals ): array {
		$probe = $signals['probe'] ?? array();
		$class = (string) ( $probe['class'] ?? '' );
		if ( 'access' === $class && 190 === (int) ( $probe['code'] ?? 0 ) ) {
			return array( self::reason( 'probe_token', 'rouge' ) );
		}
		if ( in_array( $class, array( 'access', 'transient', 'contract' ), true ) ) {
			return array( self::reason( 'probe_unavailable', 'orange', (string) (int) ( $probe['code'] ?? 0 ) ) );
		}
		return array();
	}

	/**
	 * Une raison.
	 *
	 * @param string $code   Code stable.
	 * @param string $level  `rouge` ou `orange`.
	 * @param string $detail Détail sans donnée personnelle.
	 * @return array<string, string>
	 */
	private static function reason( string $code, string $level, string $detail = '' ): array {
		return array(
			'code'   => $code,
			'niveau' => $level,
			'detail' => $detail,
		);
	}

	/**
	 * Une tâche du groupe famma-capi attend-elle depuis plus de 2 heures ?
	 *
	 * @param int $now Horloge.
	 * @return bool
	 */
	private static function late_actions( int $now ): bool {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return false;
		}
		$late = as_get_scheduled_actions(
			array(
				'group'        => Capi_Retry::GROUP,
				'status'       => 'pending',
				'date'         => $now - Capi_Health::PENDING_MAX_AGE,
				'date_compare' => '<=',
				'per_page'     => 1,
			),
			'ids'
		);
		return is_array( $late ) && array() !== $late;
	}

	/**
	 * Commandes livrées (ou terminées, D5) depuis plus de 24 h sans Purchase
	 * accepté et encore renvoyables (règles : `Capi_Purchase_Gaps::count()`).
	 *
	 * @param int $now Horloge.
	 * @return int
	 */
	public static function missing_purchases( int $now ): int {
		return Capi_Purchase_Gaps::count( $now )['missing'];
	}

	/**
	 * Nombre de cookies fbc refusés localement sur 7 jours (option écrite par Capi_Signals).
	 *
	 * Formats lus : `array( 'AAAA-MM-JJ' => n )` (recommandé), `array( 'count' => n, 'at' => t )`, ou un entier.
	 *
	 * @param int $now Horloge.
	 * @return int
	 */
	public static function fbc_rejected( int $now ): int {
		$raw = get_option( 'famma_capi_fbc_rejected', 0 );
		if ( is_numeric( $raw ) ) {
			return max( 0, (int) $raw );
		}
		if ( ! is_array( $raw ) ) {
			return 0;
		}
		if ( isset( $raw['count'] ) ) {
			$at = (int) ( $raw['at'] ?? ( $raw['updated'] ?? $now ) );
			return $now - $at <= Capi_Health::SIGNAL_WINDOW ? max( 0, (int) $raw['count'] ) : 0;
		}
		$since = gmdate( 'Y-m-d', $now - Capi_Health::SIGNAL_WINDOW );
		$total = 0;
		foreach ( $raw as $day => $count ) {
			if ( is_string( $day ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $day ) && $day >= $since ) {
				$total += max( 0, (int) $count );
			}
		}
		return $total;
	}

	/**
	 * Libellé lisible d'une raison.
	 *
	 * @param array<string, string> $reason Raison enregistrée.
	 * @return string
	 */
	public static function label( array $reason ): string {
		$detail = (string) ( $reason['detail'] ?? '' );
		$labels = array(
			'token_unreadable'  => __( 'Le jeton CAPI enregistré est illisible (clés du site modifiées ?) : ressaisissez-le dans FAMMA → Mesure.', 'famma-core' ),
			'not_configured'    => __( 'Pixel ou jeton CAPI absent : aucun événement serveur ne part.', 'famma-core' ),
			/* translators: %s: WordPress environment type. */
			'environment'       => sprintf( __( 'Envoi bloqué : environnement %s (seule la production envoie à Meta).', 'famma-core' ), $detail ),
			/* translators: %s: Meta error code. */
			'access'            => sprintf( __( 'Meta refuse le jeton ou ses droits (code %s) : OrderPlaced et Purchase sont rejetés.', 'famma-core' ), $detail ),
			/* translators: %s: Meta error code. */
			'contract'          => sprintf( __( 'Meta a refusé le contenu d’un événement (code %s) au cours des 7 derniers jours.', 'famma-core' ), $detail ),
			'registry'          => __( 'Le registre d’envoi CAPI est indisponible : aucun envoi n’est possible.', 'famma-core' ),
			/* translators: %s: hours since the last heartbeat. */
			'heartbeat'         => sprintf( __( 'Les tâches planifiées ne tournent plus depuis %s h : files et relances arrêtées.', 'famma-core' ), $detail ),
			'pending_actions'   => __( 'Une tâche CAPI attend depuis plus de 2 heures (Action Scheduler).', 'famma-core' ),
			/* translators: %s: number of orders. */
			'purchase_missing'  => sprintf( __( '%s commande(s) livrée(s) depuis plus de 24 h sans Purchase accepté par Meta : « Renvoyer » dans le rapprochement.', 'famma-core' ), $detail ),
			/* translators: %s: number of orders. */
			'purchase_lost'     => sprintf( __( '%s commande(s) livrée(s) sans Purchase accepté, que l’on ne peut plus renvoyer (délai dépassé) : voir le rapprochement, puis « Acquitter l’alerte ».', 'famma-core' ), $detail ),
			/* translators: %s: Graph API version. */
			'version_critical'  => sprintf( __( 'Version de l’API Meta %s en fin de vie : changez-la dans FAMMA → Mesure.', 'famma-core' ), $detail ),
			/* translators: %s: Graph API version served by Meta. */
			'version_served'    => sprintf( __( 'Meta a répondu avec une autre version que celle demandée (%s).', 'famma-core' ), Capi_Transport::served_label( $detail ) ),
			'profile_minimal'   => __( 'Profil de données « minimal » actif : seuls la valeur et la devise partent.', 'famma-core' ),
			/* translators: %s: number of rejected cookies. */
			'fbc_rejected'      => sprintf( __( '%s identifiant(s) de clic fbc refusé(s) localement sur 7 jours.', 'famma-core' ), $detail ),
			'test_code_invalid' => __( 'Mode test Meta actif sans code TEST valide : les envois sont bloqués.', 'famma-core' ),
			'probe_token'       => __( 'La sonde Meta signale un jeton refusé (code 190).', 'famma-core' ),
			/* translators: %s: Meta error code. */
			'probe_unavailable' => sprintf( __( 'Sonde indisponible (code %s) : vérification impossible pour l’instant.', 'famma-core' ), $detail ),
		);
		return $labels[ $reason['code'] ?? '' ] ?? (string) ( $reason['code'] ?? '' );
	}
}
