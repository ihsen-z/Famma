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
 * Règle de classement, libellés et pastilles.
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
	 * Couleur de pastille de chaque type.
	 */
	private const TONES = array(
		'engaged' => 'good',
		'brief'   => 'info',
		'bounce'  => 'warn',
		'unseen'  => 'neutral',
		'bot'     => 'bad',
	);

	/**
	 * Pastille d'un type de session, échappée.
	 *
	 * @param string $kind Type calculé par CASE_SQL.
	 * @return string
	 */
	public static function badge( string $kind ): string {
		$labels = self::labels();
		$kind   = isset( $labels[ $kind ] ) ? $kind : 'unseen';
		return Visitor_Ui::badge( $labels[ $kind ][0], self::TONES[ $kind ], $labels[ $kind ][1] );
	}

	/**
	 * Couleur associée à un type.
	 *
	 * @param string $kind Type.
	 * @return string
	 */
	public static function tone( string $kind ): string {
		return self::TONES[ $kind ] ?? 'neutral';
	}

	/**
	 * Nombre de sessions par type sur la période, gardé cinq minutes.
	 *
	 * @param int $days Période en jours.
	 * @return array<string, int>
	 */
	public static function counts( int $days ): array {
		return Visitor_Query::cached(
			'types_' . $days,
			static function () use ( $days ): array {
				global $wpdb;
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- AGGREGATES and CASE_SQL are class constants, no input.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- aggregate read of a custom table, cached by Visitor_Query::cached().
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT ' . self::CASE_SQL . ' AS kind, COUNT(*) AS sessions FROM (
							SELECT ' . self::AGGREGATES . ' FROM %i WHERE created_at >= %s GROUP BY session_id
						) s GROUP BY kind',
						Visitor_Query::table(),
						Visitor_Query::since( $days )
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
				return $out;
			}
		);
	}
}
