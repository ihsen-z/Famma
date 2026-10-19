<?php
/**
 * État de santé unique de la mesure Meta côté serveur (lot 5, R7, A4).
 *
 * Une panne de la CAPI ne produit aucune erreur visible : un jeton expiré,
 * une file Action Scheduler arrêtée ou une version retirée font perdre des
 * conversions en silence. Cette classe calcule un état unique, enregistré
 * dans l'option `famma_capi_health` :
 *
 *     array( 'statut' => 'vert'|'orange'|'rouge', 'raisons' => array(...), 'verifie_le' => <timestamp> )
 *
 * Il est recalculé sur `admin_init` (au plus toutes les 5 minutes, sans
 * cron, pour qu'une file arrêtée se voie quand même), par le battement de
 * cœur quotidien et après chaque sonde. Le passage au rouge envoie un
 * e-mail à l'administrateur, au plus un par 24 heures. Le mu-plugin
 * `famma-measurement-watchdog.php` lit cet état sans dépendre de famma-core.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Règles, état enregistré et alerte par e-mail.
 */
final class Capi_Health {

	/**
	 * Option de l'état de santé (lue aussi par le mu-plugin chien de garde).
	 */
	public const OPTION = 'famma_capi_health';

	/**
	 * Option du dernier battement de cœur (tâche quotidienne).
	 */
	public const HEARTBEAT = 'famma_capi_heartbeat';

	/**
	 * Option des signaux relevés par les envois et la sonde.
	 */
	private const SIGNALS = 'famma_capi_health_signals';

	/**
	 * Option de la date du dernier e-mail d'alerte.
	 */
	private const MAIL = 'famma_capi_health_mail';

	/**
	 * Transitoire qui limite le recalcul sur admin_init.
	 */
	private const FRESH = 'famma_capi_health_fresh';

	/**
	 * Seuils, en secondes.
	 */
	public const HEARTBEAT_MAX_AGE = 129600;
	public const PENDING_MAX_AGE   = 7200;
	public const PURCHASE_GRACE    = 86400;
	public const MAIL_INTERVAL     = 86400;
	public const WINDOW            = 1209600;
	public const SIGNAL_WINDOW     = 604800;

	/**
	 * Instance unique.
	 *
	 * @var Capi_Health|null
	 */
	private static ?Capi_Health $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Health
	 */
	public static function instance(): Capi_Health {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_init', array( self::$i, 'maybe_evaluate' ) );
		}
		return self::$i;
	}

	/**
	 * Recalcule l'état sur admin_init, au plus toutes les 5 minutes.
	 *
	 * @return void
	 */
	public function maybe_evaluate(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || false !== get_transient( self::FRESH ) ) {
			return;
		}
		set_transient( self::FRESH, 1, 300 );
		Capi_Retry::rescue_stale();
		self::evaluate();
	}

	/**
	 * Enregistre un signal relevé par un envoi ou la sonde.
	 *
	 * @param string               $key  Signal.
	 * @param array<string, mixed> $data Détails (jamais de secret).
	 * @return void
	 */
	public static function signal( string $key, array $data = array() ): void {
		$signals         = self::signals();
		$signals[ $key ] = $data + array( 'at' => time() );
		update_option( self::SIGNALS, $signals, false );
		if ( in_array( $key, array( 'access', 'registry_unavailable' ), true ) ) {
			self::evaluate();
		}
	}

	/**
	 * Retient ce qu'un envoi réel dit de la santé de la CAPI.
	 *
	 * @param string               $response_class Classe du transport.
	 * @param array<string, mixed> $result         Résultat du transport.
	 * @return void
	 */
	public static function record_outcome( string $response_class, array $result ): void {
		if ( in_array( $response_class, array( 'accepted', 'accepted_warning' ), true ) ) {
			$signals           = array_diff_key( self::signals(), array_flip( array( 'access', 'contract', 'registry_unavailable' ) ) );
			$signals['served'] = array(
				'requested' => (string) ( $result['version_requested'] ?? '' ),
				'served'    => (string) ( $result['version_served'] ?? '' ),
				'mismatch'  => ! empty( $result['version_mismatch'] ),
				'at'        => time(),
			);
			update_option( self::SIGNALS, $signals, false );
			return;
		}
		if ( in_array( $response_class, array( 'access', 'contract' ), true ) ) {
			self::signal( $response_class, array( 'code' => (int) ( $result['code'] ?? 0 ) ) );
		}
	}

	/**
	 * Retient le résultat de la sonde, puis recalcule l'état.
	 *
	 * @param array<string, mixed> $result Résultat du transport.
	 * @return void
	 */
	public static function record_probe( array $result ): void {
		$signals          = self::signals();
		$signals['probe'] = array(
			'class'     => (string) ( $result['class'] ?? '' ),
			'code'      => (int) ( $result['code'] ?? 0 ),
			'http'      => (int) ( $result['http'] ?? 0 ),
			'requested' => (string) ( $result['version_requested'] ?? '' ),
			'served'    => (string) ( $result['version_served'] ?? '' ),
			'at'        => time(),
		);
		if ( in_array( $signals['probe']['class'], array( 'accepted', 'accepted_warning' ), true ) ) {
			unset( $signals['access'] );
			$signals['served'] = array(
				'requested' => $signals['probe']['requested'],
				'served'    => $signals['probe']['served'],
				'mismatch'  => ! empty( $result['version_mismatch'] ),
				'at'        => time(),
			);
		}
		update_option( self::SIGNALS, $signals, false );
		self::evaluate();
	}

	/**
	 * Signaux enregistrés.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function signals(): array {
		$signals = get_option( self::SIGNALS, array() );
		return is_array( $signals ) ? $signals : array();
	}

	/**
	 * État enregistré, ou un état vide.
	 *
	 * @return array{statut: string, raisons: array<int, array<string, string>>, verifie_le: int}
	 */
	public static function state(): array {
		$state = get_option( self::OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		return array(
			'statut'     => in_array( $state['statut'] ?? '', array( 'vert', 'orange', 'rouge' ), true ) ? (string) $state['statut'] : 'vert',
			'raisons'    => isset( $state['raisons'] ) && is_array( $state['raisons'] ) ? $state['raisons'] : array(),
			'verifie_le' => (int) ( $state['verifie_le'] ?? 0 ),
		);
	}

	/**
	 * Calcule, enregistre et renvoie l'état de santé.
	 *
	 * @param int $now Horloge (tests), maintenant par défaut.
	 * @return array{statut: string, raisons: array<int, array<string, string>>, verifie_le: int}
	 */
	public static function evaluate( int $now = 0 ): array {
		$now     = $now > 0 ? $now : time();
		$reasons = Capi_Health_Rules::reasons( self::signals(), $now );
		$status  = 'vert';
		foreach ( $reasons as $reason ) {
			$status = 'rouge' === $reason['niveau'] ? 'rouge' : ( 'vert' === $status ? 'orange' : $status );
		}
		$previous = self::state();
		$state    = array(
			'statut'     => $status,
			'raisons'    => $reasons,
			'verifie_le' => $now,
		);
		update_option( self::OPTION, $state, true );
		if ( 'rouge' === $status && 'rouge' !== $previous['statut'] ) {
			self::mail( $state, $now );
		}
		return $state;
	}

	/**
	 * E-mail à l'administrateur au passage au rouge, au plus un par 24 heures.
	 *
	 * @param array<string, mixed> $state État calculé.
	 * @param int                  $now   Horloge.
	 * @return bool Whether an e-mail was sent.
	 */
	private static function mail( array $state, int $now ): bool {
		$last = (int) get_option( self::MAIL, 0 );
		if ( $last > 0 && $now - $last < self::MAIL_INTERVAL ) {
			return false;
		}
		update_option( self::MAIL, $now, false );
		$lines = array( __( 'La mesure Meta de la boutique est passée au rouge :', 'famma-core' ), '' );
		foreach ( $state['raisons'] as $reason ) {
			$lines[] = '- ' . Capi_Health_Rules::label( $reason );
		}
		$lines[] = '';
		/* translators: %s: URL of the FAMMA → Mesure screen. */
		$lines[] = sprintf( __( 'Détails et actions : %s', 'famma-core' ), admin_url( 'admin.php?page=famma-tracking' ) );

		return (bool) wp_mail( (string) get_option( 'admin_email' ), __( 'FAMMA : mesure Meta en alerte', 'famma-core' ), implode( "\n", $lines ) );
	}
}
