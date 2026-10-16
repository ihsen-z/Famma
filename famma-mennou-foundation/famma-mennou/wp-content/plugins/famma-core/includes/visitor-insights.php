<?php
/**
 * Parcours visiteurs — analyses par source et par produit.
 *
 * ── Attribution ────────────────────────────────────────────────────────────
 * Une session porte UNE source : celle de son arrivée (UTM, fbclid, site
 * référent, sinon « direct »). Le script ouvre d'ailleurs une nouvelle session
 * quand une autre campagne arrive. Les événements serveur (panier, commande)
 * n'ont pas de source : ils héritent de celle de leur session, d'où le
 * regroupement par session avant le regroupement par source.
 *
 * Le chiffre d'affaires affiché est celui des commandes PASSÉES. En paiement à
 * la livraison, une commande passée n'est pas une commande payée : la vue le
 * dit, plutôt que de laisser croire à un encaissement.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lectures agrégées par source/campagne et par produit, en cache cinq minutes.
 */
final class Visitor_Insights {

	/**
	 * Durée du cache, en secondes.
	 */
	private const CACHE_TTL = 300;

	/**
	 * Lignes au plus par tableau.
	 */
	private const LIMIT = 50;

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
	 * Lit un agrégat en cache, ou le calcule et le met en cache.
	 *
	 * @param string   $key     Clé du transient.
	 * @param callable $compute Calcul à exécuter en cas d'absence.
	 * @return array<int, array<string, mixed>>
	 */
	private function cached( string $key, callable $compute ): array {
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$rows = $compute();
		$rows = is_array( $rows ) ? $rows : array();
		set_transient( $key, $rows, self::CACHE_TTL );
		return $rows;
	}

	/**
	 * Entonnoir par source, support, campagne et contenu publicitaire.
	 *
	 * @param int $days Période en jours.
	 * @return array<int, array<string, mixed>>
	 */
	public function sources( int $days ): array {
		return $this->cached(
			'famma_vj_sources_' . $days,
			function () use ( $days ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, cached by cached().
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT source, medium, campaign, content,
							COUNT(*) AS sessions, SUM(product) AS product, SUM(cart) AS cart,
							SUM(form) AS form, SUM(ordered) AS ordered, SUM(orders) AS orders, SUM(revenue) AS revenue
						FROM (
							SELECT session_id,
								MAX(source) AS source, MAX(medium) AS medium, MAX(campaign) AS campaign, MAX(content) AS content,
								MAX(event = 'page_view' AND page_type = 'product') AS product,
								MAX(event = 'add_to_cart') AS cart,
								MAX(event = 'form_start' OR ( event = 'page_view' AND page_type = 'checkout' )) AS form,
								MAX(event = 'order_placed') AS ordered,
								SUM(event = 'order_placed') AS orders,
								SUM(CASE WHEN event = 'order_placed' THEN num ELSE 0 END) AS revenue
							FROM %i WHERE created_at >= %s
							GROUP BY session_id
						) AS s
						GROUP BY source, medium, campaign, content
						ORDER BY sessions DESC
						LIMIT %d",
						Visitor_Store::instance()->table(),
						$this->since( $days ),
						self::LIMIT
					),
					ARRAY_A
				);
			}
		);
	}

	/**
	 * Intérêt par produit : vues, temps actif, défilement, galerie, vidéo,
	 * panier, formulaire, et sessions ayant vu le produit puis commandé.
	 *
	 * @param int $days Période en jours.
	 * @return array<int, array<string, mixed>>
	 */
	public function products( int $days ): array {
		return $this->cached(
			'famma_vj_products_' . $days,
			function () use ( $days ) {
				global $wpdb;
				$since = $this->since( $days );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, cached by cached().
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT t.product_id,
							COUNT(DISTINCT CASE WHEN t.event = 'page_view' THEN t.session_id END) AS views,
							AVG(CASE WHEN t.event = 'page_leave' THEN t.num END) AS avg_time,
							AVG(CASE WHEN t.event = 'page_leave' THEN CAST(t.label AS UNSIGNED) END) AS avg_scroll,
							COUNT(DISTINCT CASE WHEN t.event = 'product_media' AND t.label = 'gallery' THEN t.session_id END) AS gallery,
							COUNT(DISTINCT CASE WHEN t.event = 'product_media' AND t.label = 'video' THEN t.session_id END) AS video,
							COUNT(DISTINCT CASE WHEN t.event = 'add_to_cart' THEN t.session_id END) AS carts,
							COUNT(DISTINCT CASE WHEN t.event = 'form_start' THEN t.session_id END) AS forms,
							COUNT(DISTINCT CASE WHEN t.event = 'page_view' AND o.session_id IS NOT NULL THEN t.session_id END) AS ordered
						FROM %i AS t
						LEFT JOIN (
							SELECT DISTINCT session_id FROM %i WHERE event = 'order_placed' AND created_at >= %s
						) AS o ON o.session_id = t.session_id
						WHERE t.created_at >= %s AND t.product_id > 0
						GROUP BY t.product_id
						ORDER BY views DESC
						LIMIT %d",
						Visitor_Store::instance()->table(),
						Visitor_Store::instance()->table(),
						$since,
						$since,
						self::LIMIT
					),
					ARRAY_A
				);
			}
		);
	}

	/**
	 * Variations les plus choisies, par produit.
	 *
	 * @param int $days Période en jours.
	 * @return array<int, array<string, mixed>>
	 */
	public function variations( int $days ): array {
		return $this->cached(
			'famma_vj_variations_' . $days,
			function () use ( $days ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, cached by cached().
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT product_id, label, COUNT(DISTINCT session_id) AS sessions
						FROM %i WHERE created_at >= %s AND event = 'product_variation'
						GROUP BY product_id, label
						ORDER BY sessions DESC
						LIMIT %d",
						Visitor_Store::instance()->table(),
						$this->since( $days ),
						self::LIMIT
					),
					ARRAY_A
				);
			}
		);
	}
}
