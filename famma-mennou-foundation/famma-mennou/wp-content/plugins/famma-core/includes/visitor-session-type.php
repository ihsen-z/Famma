<?php
/**
 * Parcours visiteurs — type de session (humain ou robot probable).
 *
 * Les robots déclarés (Google, Facebook, outils de mesure, préchauffage du
 * cache) et l'équipe connectée ne sont jamais enregistrés : le collecteur les
 * écarte. Ce qui reste a exécuté le script dans un navigateur. Le type classe
 * ces sessions d'après leur comportement, mesuré par le navigateur :
 *
 * - « Humain — intéressé » : une action (formulaire, clic, galerie, panier,
 *   commande), ou au moins 10 s de lecture, ou un quart de page défilé ;
 * - « Humain — lecture rapide » : page affichée de 3 à 10 s ;
 * - « Humain — rebond éclair » : page affichée moins de 3 s, sans défiler
 *   (souvent un clic par erreur sur une publicité) ;
 * - « Robot probable » : plusieurs pages chargées en 2 s au plus, sans
 *   qu'aucune soit affichée ;
 * - « Non affichée » : la page n'a jamais été vue à l'écran (onglet fermé
 *   pendant le chargement, préchargement, ou robot qui exécute le script).
 *
 * Une seule définition, en SQL : la liste des sessions et le décompte de la
 * période lisent la même règle.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Règle de classement, libellés et légende.
 */
final class Visitor_Session_Type {

	/**
	 * Agrégats par session nécessaires au classement (requête GROUP BY session_id).
	 */
	public const AGGREGATES = "SUM(event = 'page_view') AS pages,
		SUM(event = 'page_leave') AS leaves,
		SUM(CASE WHEN event = 'page_leave' THEN num ELSE 0 END) AS active,
		MAX(CASE WHEN event = 'page_leave' THEN CAST(label AS UNSIGNED) ELSE 0 END) AS scroll,
		SUM(event = 'web_vitals') AS vitals,
		SUM(event NOT IN ('page_view', 'page_leave', 'web_vitals', 'js_error')) AS actions,
		TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) AS span";

	/**
	 * Règle de classement, appliquée aux agrégats ci-dessus.
	 */
	public const CASE_SQL = "CASE
		WHEN actions > 0 OR active >= 10 OR scroll >= 25 THEN 'engaged'
		WHEN leaves > 0 AND active >= 3 THEN 'brief'
		WHEN leaves > 0 OR vitals > 0 THEN 'bounce'
		WHEN pages >= 3 AND span <= 2 THEN 'bot'
		ELSE 'unseen' END";

	/**
	 * Libellés et explications, dans l'ordre d'affichage.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function labels(): array {
		return array(
			'engaged' => array( __( 'Humain — intéressé', 'famma-core' ), __( 'Une action (formulaire, clic, galerie, panier, commande), au moins 10 s de lecture ou un quart de page défilé.', 'famma-core' ) ),
			'brief'   => array( __( 'Humain — lecture rapide', 'famma-core' ), __( 'Page affichée de 3 à 10 secondes, sans action.', 'famma-core' ) ),
			'bounce'  => array( __( 'Humain — rebond éclair', 'famma-core' ), __( 'Page affichée moins de 3 secondes, sans défiler : souvent un clic par erreur sur une publicité.', 'famma-core' ) ),
			'unseen'  => array( __( 'Non affichée', 'famma-core' ), __( 'La page n\'a jamais été vue à l\'écran : fermée pendant le chargement, préchargée, ou robot qui exécute le script.', 'famma-core' ) ),
			'bot'     => array( __( 'Robot probable', 'famma-core' ), __( 'Plusieurs pages chargées en 2 secondes au plus, sans qu\'aucune soit affichée.', 'famma-core' ) ),
		);
	}

	/**
	 * Cellule du tableau des sessions, échappée.
	 *
	 * @param string $kind Type calculé par CASE_SQL.
	 * @return string
	 */
	public static function cell( string $kind ): string {
		$labels = self::labels();
		$kind   = isset( $labels[ $kind ] ) ? $kind : 'unseen';
		return '<span class="famma-vj__type famma-vj__type--' . esc_attr( $kind ) . '" title="' . esc_attr( $labels[ $kind ][1] ) . '">' . esc_html( $labels[ $kind ][0] ) . '</span>';
	}

	/**
	 * Nombre de sessions par type sur la période, gardé cinq minutes.
	 *
	 * @param int $days Période en jours.
	 * @return array<string, int>
	 */
	public static function counts( int $days ): array {
		$key    = 'famma_vj_types_' . $days;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- AGGREGATES and CASE_SQL are class constants, no input.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- aggregate read of a custom table, cached below.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ' . self::CASE_SQL . ' AS kind, COUNT(*) AS sessions FROM (
					SELECT ' . self::AGGREGATES . ' FROM %i WHERE created_at >= %s GROUP BY session_id
				) s GROUP BY kind',
				Visitor_Store::instance()->table(),
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$out = array_fill_keys( array_keys( self::labels() ), 0 );
		foreach ( (array) $rows as $row ) {
			if ( isset( $out[ $row['kind'] ] ) ) {
				$out[ $row['kind'] ] = (int) $row['sessions'];
			}
		}
		set_transient( $key, $out, 5 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Répartition de la période et légende de la colonne « Type ».
	 *
	 * @param array<string, int> $counts Sessions par type.
	 * @return void
	 */
	public static function legend( array $counts ): void {
		$total = array_sum( $counts );
		echo '<h2>' . esc_html__( 'Qui sont ces sessions ?', 'famma-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'Les robots déclarés (Google, Facebook, outils de mesure, préchauffage du cache) et l\'équipe connectée ne sont jamais enregistrés. Les sessions ci-dessous ont toutes exécuté le site dans un navigateur ; leur type vient de ce que le visiteur a fait.', 'famma-core' ) . '</p>';
		echo '<table class="widefat striped famma-vj__types"><thead><tr><th>' . esc_html__( 'Type', 'famma-core' ) . '</th><th class="num">' . esc_html__( 'Sessions', 'famma-core' ) . '</th><th class="num">%</th><th>' . esc_html__( 'Signification', 'famma-core' ) . '</th></tr></thead><tbody>';
		foreach ( self::labels() as $kind => $label ) {
			$count = (int) ( $counts[ $kind ] ?? 0 );
			$pct   = $total > 0 ? number_format_i18n( 100 * $count / $total, 1 ) . ' %' : '—';
			echo '<tr><td>' . self::cell( $kind ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
			echo '<td class="num">' . esc_html( number_format_i18n( $count ) ) . '</td><td class="num">' . esc_html( $pct ) . '</td><td>' . esc_html( $label[1] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
