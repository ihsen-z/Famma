<?php
/**
 * Parcours visiteurs — temps de chargement réels et erreurs JavaScript.
 *
 * ── Pourquoi le 75e centile, calculé en PHP ────────────────────────────────
 * Google juge une page sur le 75e centile de ses visites réelles : une
 * moyenne masquerait le quart de visiteurs qui attend le plus. MySQL n'offre
 * pas de fonction de centile portable (MariaDB oui, MySQL non) ; l'hébergeur
 * pouvant servir l'un ou l'autre, le calcul se fait ici, sur un échantillon
 * borné des mesures les plus récentes, et le résultat reste cinq minutes en
 * cache.
 *
 * Seuils Google (« bon » / « à améliorer » au-delà, « mauvais » au-delà du
 * second) : LCP 2,5 s / 4 s, INP 200 ms / 500 ms, CLS 0,1 / 0,25,
 * TTFB 0,8 s / 1,8 s.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Agrégats de performance et d'erreurs, par type de page, appareil et chemin.
 */
final class Visitor_Performance {

	/**
	 * Seuils par mesure : bon jusqu'au premier, mauvais au-delà du second.
	 */
	public const THRESHOLDS = array(
		'lcp'  => array( 2500, 4000 ),
		'inp'  => array( 200, 500 ),
		'cls'  => array( 0.1, 0.25 ),
		'ttfb' => array( 800, 1800 ),
	);

	/**
	 * Mesures lues au plus par période.
	 */
	private const SAMPLE = 20000;

	/**
	 * Mesures minimales pour classer un chemin.
	 */
	private const MIN_SAMPLES = 3;

	/**
	 * Durée du cache, en secondes.
	 */
	private const CACHE_TTL = 300;

	/**
	 * Début de la période, en UTC.
	 *
	 * @param int $days Nombre de jours.
	 * @return string
	 */
	private function since( int $days ): string {
		return gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	}

	/**
	 * Décode une ligne `lcp=…;cls=…;inp=…;ttfb=…`.
	 *
	 * @param string $label Valeurs empaquetées par le script.
	 * @return array<string, float>
	 */
	private function parse( string $label ): array {
		$out = array();
		foreach ( explode( ';', $label ) as $pair ) {
			$parts = explode( '=', $pair, 2 );
			if ( 2 === count( $parts ) && isset( self::THRESHOLDS[ $parts[0] ] ) && is_numeric( $parts[1] ) ) {
				$out[ $parts[0] ] = (float) $parts[1];
			}
		}
		return $out;
	}

	/**
	 * 75e centile d'une série (méthode du rang le plus proche).
	 *
	 * @param array<int, float> $values Valeurs.
	 * @return float|null
	 */
	private function p75( array $values ): ?float {
		if ( array() === $values ) {
			return null;
		}
		sort( $values );
		return $values[ (int) max( 0, ceil( 0.75 * count( $values ) ) - 1 ) ];
	}

	/**
	 * Centiles par groupe, à partir des séries collectées.
	 *
	 * @param array<string, array<string, array<int, float>>> $series Séries par groupe puis mesure.
	 * @param int                                             $min    Mesures minimales par groupe.
	 * @return array<string, array<string, float|int|null>>
	 */
	private function summarize( array $series, int $min ): array {
		$out = array();
		foreach ( $series as $group => $metrics ) {
			$count = max( count( $metrics['lcp'] ?? array() ), count( $metrics['ttfb'] ?? array() ) );
			if ( $count < $min ) {
				continue;
			}
			$row = array( 'samples' => $count );
			foreach ( array_keys( self::THRESHOLDS ) as $metric ) {
				$row[ $metric ] = $this->p75( $metrics[ $metric ] ?? array() );
			}
			$out[ $group ] = $row;
		}
		return $out;
	}

	/**
	 * Centiles par type de page × appareil, et par chemin.
	 *
	 * @param int $days Période en jours.
	 * @return array{pages: array<string, array<string, mixed>>, paths: array<string, array<string, mixed>>}
	 */
	public function vitals( int $days ): array {
		$key    = 'famma_vj_vitals_' . $days;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded read of a custom table, cached just below.
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_type, path, device, label FROM %i
				WHERE created_at >= %s AND event = 'web_vitals'
				ORDER BY id DESC LIMIT %d",
				Visitor_Store::instance()->table(),
				$this->since( $days ),
				self::SAMPLE
			),
			ARRAY_A
		);
		$pages = array();
		$paths = array();
		foreach ( (array) $rows as $row ) {
			foreach ( $this->parse( (string) $row['label'] ) as $metric => $value ) {
				// LCP nul : non mesuré (onglet resté en fond). INP -1 : aucune interaction ;
				// INP 0 : interactions toutes sous 40 ms, donc bonnes.
				if ( $value < 0 || ( 0.0 === $value && 'lcp' === $metric ) ) {
					continue;
				}
				foreach ( array( 'all', (string) $row['device'] ) as $device ) {
					$pages[ $row['page_type'] . '|' . $device ][ $metric ][] = $value;
				}
				$paths[ (string) $row['path'] ][ $metric ][] = $value;
			}
		}
		$paths = $this->summarize( $paths, self::MIN_SAMPLES );
		uasort( $paths, static fn( $a, $b ) => ( $b['lcp'] ?? 0 ) <=> ( $a['lcp'] ?? 0 ) );
		$out = array(
			'pages' => $this->summarize( $pages, 1 ),
			'paths' => array_slice( $paths, 0, 15, true ),
		);
		set_transient( $key, $out, self::CACHE_TTL );
		return $out;
	}

	/**
	 * Erreurs JavaScript regroupées par message et fichier.
	 *
	 * @param int $days Période en jours.
	 * @return array<int, array<string, mixed>>
	 */
	public function errors( int $days ): array {
		$key    = 'famma_vj_errors_' . $days;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, cached just below.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT label, MAX(num) AS line, COUNT(*) AS hits, COUNT(DISTINCT session_id) AS sessions,
					COUNT(DISTINCT path) AS pages, MAX(path) AS sample_path, MAX(created_at) AS last_seen,
					SUM(device = 'mobile') AS mobile
				FROM %i WHERE created_at >= %s AND event = 'js_error'
				GROUP BY label ORDER BY sessions DESC, hits DESC LIMIT 30",
				Visitor_Store::instance()->table(),
				$this->since( $days )
			),
			ARRAY_A
		);
		$rows = is_array( $rows ) ? $rows : array();
		set_transient( $key, $rows, self::CACHE_TTL );
		return $rows;
	}
}
