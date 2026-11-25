<?php
/**
 * Qualité de la mesure vue par Meta (phase B, décision Q6) : lecture seule.
 *
 * Une fois par jour au plus, interroge le « Dataset Quality API » de Meta
 * (GET /{version}/dataset_quality) : note de correspondance (Event Match
 * Quality), fraîcheur et couverture par événement. Le résultat est gardé dans
 * l'option `famma_meta_quality` et affiché dans FAMMA → Mesure. Rien n'est
 * écrit chez Meta ; le jeton part dans l'en-tête Authorization, jamais dans
 * l'URL, un journal ou un message. Un refus de Meta (droits, jeton, limite)
 * donne « Lecture impossible », jamais une alerte rouge.
 *
 * Interface pour les alertes : `Capi_Meta_Quality::get()`, `::score( $event )`
 * et le filtre `famma_meta_quality` (voir le rapport de la phase B).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lecture quotidienne et cache de la qualité Meta.
 */
final class Capi_Meta_Quality {

	/**
	 * Option du dernier résultat.
	 */
	public const OPTION = 'famma_meta_quality';

	/**
	 * Événement wp-cron quotidien.
	 */
	public const CRON = 'famma_meta_quality_daily';

	/**
	 * Intervalle minimal entre deux appels à Meta, en secondes.
	 */
	private const MIN_INTERVAL = 86400;

	/**
	 * Délai maximal de l'appel, en secondes.
	 */
	private const TIMEOUT = 5;

	/**
	 * Champs demandés (doc : Dataset Quality API).
	 */
	private const FIELDS = 'web{event_name,event_match_quality{composite_score},data_freshness{upload_frequency},event_coverage{percentage,goal_percentage}}';

	/**
	 * Instance unique.
	 *
	 * @var Capi_Meta_Quality|null
	 */
	private static ?Capi_Meta_Quality $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Meta_Quality
	 */
	public static function instance(): Capi_Meta_Quality {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( self::CRON, array( self::class, 'refresh' ) );
			if ( is_admin() ) {
				add_action( 'admin_init', array( self::$i, 'ensure_scheduled' ) );
			}
		}
		return self::$i;
	}

	/**
	 * Programme la lecture quotidienne si elle ne l'est pas.
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Dernier résultat enregistré, passé au filtre `famma_meta_quality`.
	 *
	 * @return array{status: string, reason: string, read_at: int, attempt_at: int, events: array<int, array<string, mixed>>}
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$data   = array(
			'status'     => in_array( $stored['status'] ?? '', array( 'ok', 'unavailable' ), true ) ? (string) $stored['status'] : 'none',
			'reason'     => (string) ( $stored['reason'] ?? '' ),
			'read_at'    => (int) ( $stored['read_at'] ?? 0 ),
			'attempt_at' => (int) ( $stored['attempt_at'] ?? 0 ),
			'events'     => isset( $stored['events'] ) && is_array( $stored['events'] ) ? array_values( $stored['events'] ) : array(),
		);
		return (array) apply_filters( 'famma_meta_quality', $data );
	}

	/**
	 * Note de correspondance (0 à 10) d'un événement, null si inconnue.
	 *
	 * @param string $event Nom d'événement Meta (ex. OrderPlaced).
	 * @return float|null
	 */
	public static function score( string $event ): ?float {
		$data = self::get();
		if ( 'ok' !== $data['status'] ) {
			return null;
		}
		foreach ( $data['events'] as $row ) {
			if ( ( $row['event'] ?? '' ) === $event && isset( $row['score'] ) && is_numeric( $row['score'] ) ) {
				return (float) $row['score'];
			}
		}
		return null;
	}

	/**
	 * Lit Meta si la dernière tentative date de plus de 24 h.
	 *
	 * @param int $now Horloge.
	 * @return void
	 */
	public static function refresh( int $now = 0 ): void {
		$now    = $now > 0 ? $now : time();
		$stored = get_option( self::OPTION, array() );
		$last   = is_array( $stored ) ? $stored : array();
		if ( $now - (int) ( $last['attempt_at'] ?? 0 ) < self::MIN_INTERVAL ) {
			return;
		}
		$result               = self::fetch();
		$result['attempt_at'] = $now;
		// Une lecture ratée garde les dernières notes connues et leur date.
		$ok                = 'ok' === $result['status'];
		$result['events']  = $ok ? $result['events'] : (array) ( $last['events'] ?? array() );
		$result['read_at'] = $ok ? $now : (int) ( $last['read_at'] ?? 0 );
		update_option( self::OPTION, $result, false );
	}

	/**
	 * Appel à Meta, sans effet de bord.
	 *
	 * @return array{status: string, reason: string, events: array<int, array<string, mixed>>}
	 */
	private static function fetch(): array {
		$cfg   = Config::instance();
		$pixel = $cfg->meta_pixel_id();
		$token = $cfg->meta_access_token();
		if ( '' === $pixel || '' === $token ) {
			return Capi_Meta_Quality_Parser::failure( 'not_configured' );
		}
		if ( ! $cfg->meta_sending_allowed() ) {
			return Capi_Meta_Quality_Parser::failure( 'environment' );
		}
		$response = Capi_Transport::read_quality( $token, $pixel, self::FIELDS, $cfg->meta_graph_version(), self::TIMEOUT );
		return Capi_Meta_Quality_Parser::parse( $response );
	}
}
