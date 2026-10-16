<?php
/**
 * Parcours visiteurs — requêtes de la vue d'administration.
 *
 * Toutes les lectures passent par `$wpdb->prepare()` sur la table dédiée.
 * Les agrégats d'une période (indicateurs, entonnoir, champs de formulaire)
 * sont calculés en un passage chacun et gardés cinq minutes en transient :
 * ouvrir la page plusieurs fois ne relance pas les comptages.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lectures agrégées et détaillées des événements visiteurs.
 */
final class Visitor_Report {

	/**
	 * Durée du cache des agrégats, en secondes.
	 */
	private const CACHE_TTL = 300;

	/**
	 * Sessions par page de la liste.
	 */
	public const PER_PAGE = 30;

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
	 * Nom de la table.
	 *
	 * @return string
	 */
	private function table(): string {
		return Visitor_Store::instance()->table();
	}

	/**
	 * Indicateurs et entonnoir de la période, comptés en sessions distinctes.
	 *
	 * Chaque marche compte les sessions qui ont atteint l'étape, quel que soit
	 * l'ordre : une session qui commande depuis un lien direct vers le
	 * checkout compte à « commande » sans être passée par la fiche produit.
	 *
	 * @param int $days Période en jours.
	 * @return array<string, int>
	 */
	public function summary( int $days ): array {
		$key    = 'famma_vj_summary_' . $days;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, result cached in a transient just below.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(DISTINCT visitor_id) AS visitors,
					COUNT(DISTINCT session_id) AS sessions,
					SUM(event = 'page_view') AS page_views,
					COUNT(DISTINCT CASE WHEN event = 'page_view' AND page_type = 'product' THEN session_id END) AS product,
					COUNT(DISTINCT CASE WHEN event = 'add_to_cart' THEN session_id END) AS cart,
					COUNT(DISTINCT CASE WHEN event = 'form_start' OR ( event = 'page_view' AND page_type = 'checkout' ) THEN session_id END) AS form,
					COUNT(DISTINCT CASE WHEN event = 'form_submit' THEN session_id END) AS submit,
					COUNT(DISTINCT CASE WHEN event = 'order_placed' THEN session_id END) AS ordered,
					SUM(event = 'order_placed') AS orders,
					SUM(event = 'click_whatsapp') AS whatsapp
				FROM %i WHERE created_at >= %s",
				$this->table(),
				$this->since( $days )
			),
			ARRAY_A
		);
		$out = array_map( 'intval', is_array( $row ) ? $row : array() );
		set_transient( $key, $out, self::CACHE_TTL );
		return $out;
	}

	/**
	 * Sessions ayant touché chaque champ, par formulaire.
	 *
	 * Le formulaire se déduit du type de page : `product` pour la commande
	 * express de la fiche, `checkout` pour le tunnel classique.
	 *
	 * @param int $days Période en jours.
	 * @return array<string, array{started: int, submitted: int, fields: array<string, int>}>
	 */
	public function form_fields( int $days ): array {
		$key    = 'famma_vj_fields_' . $days;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, result cached in a transient just below.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_type, event, label, COUNT(DISTINCT session_id) AS sessions
				FROM %i
				WHERE created_at >= %s AND event IN ('form_start','form_field','form_submit')
				GROUP BY page_type, event, label
				ORDER BY sessions DESC",
				$this->table(),
				$this->since( $days )
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$form = 'product' === $row['page_type'] ? 'express' : 'checkout';
			if ( ! isset( $out[ $form ] ) ) {
				$out[ $form ] = array(
					'started'   => 0,
					'submitted' => 0,
					'fields'    => array(),
				);
			}
			if ( 'form_start' === $row['event'] ) {
				$out[ $form ]['started'] += (int) $row['sessions'];
			} elseif ( 'form_submit' === $row['event'] ) {
				$out[ $form ]['submitted'] += (int) $row['sessions'];
			} else {
				$out[ $form ]['fields'][ (string) $row['label'] ] = (int) $row['sessions'];
			}
		}
		set_transient( $key, $out, self::CACHE_TTL );
		return $out;
	}

	/**
	 * Sessions de la période, des plus récentes aux plus anciennes.
	 *
	 * @param int $days Période en jours.
	 * @param int $page Numéro de page, à partir de 1.
	 * @return array<int, array<string, string>>
	 */
	public function sessions( int $days, int $page ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- paginated read of a custom table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_id, MAX(visitor_id) AS visitor_id, MIN(created_at) AS started, MAX(created_at) AS ended,
					MAX(device) AS device, MAX(source) AS source, MAX(campaign) AS campaign,
					SUM(event = 'page_view') AS pages, SUM(event = 'add_to_cart') AS carts,
					MAX(order_id) AS order_id
				FROM %i WHERE created_at >= %s
				GROUP BY session_id
				ORDER BY started DESC
				LIMIT %d OFFSET %d",
				$this->table(),
				$this->since( $days ),
				self::PER_PAGE,
				( max( 1, $page ) - 1 ) * self::PER_PAGE
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Événements d'une session, dans l'ordre.
	 *
	 * @param string $session_id Identifiant de session.
	 * @return array<int, array<string, string>>
	 */
	public function timeline( string $session_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded read of a custom table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT created_at, event, page_type, path, product_id, order_id, label, num, source, medium, campaign, device, visitor_id
				FROM %i WHERE session_id = %s ORDER BY created_at ASC, id ASC LIMIT 500',
				$this->table(),
				$session_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}
