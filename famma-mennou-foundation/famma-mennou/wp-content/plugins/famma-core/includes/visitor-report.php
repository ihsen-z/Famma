<?php
/**
 * Parcours visiteurs — requêtes de la synthèse, des sessions et des parcours.
 *
 * Toutes les lectures passent par `$wpdb->prepare()` sur la table dédiée.
 * Les agrégats d'une période (indicateurs, entonnoir, champs de formulaire)
 * sont calculés en un passage chacun et gardés cinq minutes
 * (`Visitor_Query::cached()`) : ouvrir la page plusieurs fois ne relance pas
 * les comptages. La liste des sessions, filtrée, est lue à chaque fois.
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
	 * Sessions par page de la liste.
	 */
	public const PER_PAGE = 30;

	/**
	 * Filtres reconnus par la liste des sessions, avec leur valeur vide.
	 */
	public const FILTERS = array(
		'kind'   => '',
		'device' => '',
		'source' => '',
		'ip'     => '',
	);

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
		return Visitor_Query::cached(
			'summary_' . $days,
			static function () use ( $days ): array {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, result cached by Visitor_Query::cached().
				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT
							COUNT(DISTINCT visitor_id) AS visitors,
							COUNT(DISTINCT session_id) AS sessions,
							COUNT(DISTINCT NULLIF(ip, '')) AS ips,
							SUM(event = 'page_view') AS page_views,
							COUNT(DISTINCT CASE WHEN event = 'page_view' AND page_type = 'product' THEN session_id END) AS product,
							COUNT(DISTINCT CASE WHEN event = 'add_to_cart' THEN session_id END) AS cart,
							COUNT(DISTINCT CASE WHEN event = 'form_start' OR ( event = 'page_view' AND page_type = 'checkout' ) THEN session_id END) AS form,
							COUNT(DISTINCT CASE WHEN event = 'form_submit' THEN session_id END) AS submit,
							COUNT(DISTINCT CASE WHEN event = 'order_placed' THEN session_id END) AS ordered,
							SUM(event = 'order_placed') AS orders,
							SUM(CASE WHEN event = 'order_placed' THEN num ELSE 0 END) AS revenue,
							SUM(event = 'click_whatsapp') AS whatsapp,
							SUM(device = 'mobile' AND event = 'page_view') AS mobile_views
						FROM %i WHERE created_at >= %s",
						Visitor_Query::table(),
						Visitor_Query::since( $days )
					),
					ARRAY_A
				);
				return array_map( 'intval', is_array( $row ) ? $row : array() );
			}
		);
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
		return Visitor_Query::cached(
			'fields_' . $days,
			static function () use ( $days ): array {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, result cached by Visitor_Query::cached().
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT page_type, event, label, COUNT(DISTINCT session_id) AS sessions
						FROM %i
						WHERE created_at >= %s AND event IN ('form_start','form_field','form_submit')
						GROUP BY page_type, event, label
						ORDER BY sessions DESC",
						Visitor_Query::table(),
						Visitor_Query::since( $days )
					),
					ARRAY_A
				);
				$out  = array();
				foreach ( (array) $rows as $row ) {
					$form           = 'product' === $row['page_type'] ? 'express' : 'checkout';
					$out[ $form ] ??= array(
						'started'   => 0,
						'submitted' => 0,
						'fields'    => array(),
					);
					if ( 'form_start' === $row['event'] ) {
						$out[ $form ]['started'] += (int) $row['sessions'];
					} elseif ( 'form_submit' === $row['event'] ) {
						$out[ $form ]['submitted'] += (int) $row['sessions'];
					} else {
						$out[ $form ]['fields'][ (string) $row['label'] ] = (int) $row['sessions'];
					}
				}
				return $out;
			}
		);
	}

	/**
	 * Sources présentes sur la période, pour le filtre de la liste.
	 *
	 * @param int $days Période en jours.
	 * @return array<int, string>
	 */
	public function sources( int $days ): array {
		return Visitor_Query::cached(
			'source_list_' . $days,
			static function () use ( $days ): array {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, result cached by Visitor_Query::cached().
				$rows = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT source FROM %i WHERE created_at >= %s AND source <> '' ORDER BY source LIMIT 40", Visitor_Query::table(), Visitor_Query::since( $days ) ) );
				return array_map( 'strval', (array) $rows );
			}
		);
	}

	/**
	 * Filtres de la liste, nettoyés : valeurs inconnues ramenées à vide.
	 *
	 * @param array<string, string> $raw Valeurs reçues.
	 * @return array{kind: string, device: string, source: string, ip: string}
	 */
	public static function clean_filters( array $raw ): array {
		$kind   = sanitize_key( $raw['kind'] ?? '' );
		$device = sanitize_key( $raw['device'] ?? '' );
		$ip     = trim( (string) ( $raw['ip'] ?? '' ) );
		return array(
			'kind'   => isset( Visitor_Session_Type::labels()[ $kind ] ) ? $kind : '',
			'device' => isset( Visitor_Labels::devices()[ $device ] ) ? $device : '',
			'source' => mb_substr( strtolower( sanitize_text_field( (string) ( $raw['source'] ?? '' ) ) ), 0, 64 ),
			'ip'     => false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '',
		);
	}

	/**
	 * Sessions de la période, filtrées, des plus récentes aux plus anciennes.
	 *
	 * @param int                   $days    Période en jours.
	 * @param int                   $page    Numéro de page, à partir de 1.
	 * @param array<string, string> $filters Filtres nettoyés (`clean_filters()`).
	 * @return array{rows: array<int, array<string, string>>, total: int}
	 */
	public function sessions( int $days, int $page, array $filters = self::FILTERS ): array {
		global $wpdb;
		list( $sql, $params ) = $this->sessions_sql( $days, $filters );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $sql only holds class constants and placeholders; values passed by spread.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read of a custom table.
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM (' . $sql . ') k', ...$params ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- paginated read of a custom table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM (' . $sql . ') k ORDER BY k.started DESC LIMIT %d OFFSET %d',
				...array_merge( $params, array( self::PER_PAGE, ( max( 1, $page ) - 1 ) * self::PER_PAGE ) )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Requête des sessions filtrées, sans tri ni pagination, et ses valeurs.
	 *
	 * Le type de session (`Visitor_Session_Type`) se calcule sur les agrégats
	 * de chaque session : il se filtre donc après le regroupement.
	 *
	 * @param int                   $days    Période en jours.
	 * @param array<string, string> $filters Filtres nettoyés.
	 * @return array{0: string, 1: array<int, int|string>}
	 */
	private function sessions_sql( int $days, array $filters ): array {
		$having = array();
		$params = array( Visitor_Query::table(), Visitor_Query::since( $days ) );
		foreach ( array( 'device', 'source', 'ip' ) as $column ) {
			if ( '' !== ( $filters[ $column ] ?? '' ) ) {
				$having[] = "MAX($column) = %s";
				$params[] = $filters[ $column ];
			}
		}
		$where = '';
		if ( '' !== ( $filters['kind'] ?? '' ) ) {
			$where    = ' WHERE t.kind = %s';
			$params[] = $filters['kind'];
		}
		$sql = 'SELECT t.* FROM ( SELECT s.*, ' . Visitor_Session_Type::CASE_SQL . " AS kind FROM (
				SELECT session_id, MAX(visitor_id) AS visitor_id, MIN(created_at) AS started, MAX(created_at) AS ended,
					MAX(device) AS device, MAX(source) AS source, MAX(medium) AS medium, MAX(campaign) AS campaign, MAX(ip) AS ip,
					SUM(event = 'add_to_cart') AS carts, MAX(order_id) AS order_id,
					" . Visitor_Session_Type::AGGREGATES . '
				FROM %i WHERE created_at >= %s
				GROUP BY session_id' . ( array() === $having ? '' : ' HAVING ' . implode( ' AND ', $having ) ) . '
			) s ) t' . $where;
		return array( $sql, $params );
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
				'SELECT created_at, event, page_type, path, product_id, order_id, label, num, source, medium, campaign, content, device, visitor_id, ip
				FROM %i WHERE session_id = %s ORDER BY created_at ASC, id ASC LIMIT 500',
				Visitor_Query::table(),
				$session_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Historique d'un visiteur : nombre de sessions et première visite connue.
	 *
	 * @param string $visitor_id Identifiant visiteur.
	 * @return array{sessions: int, first_seen: string}
	 */
	public function visitor_history( string $visitor_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed read of a custom table.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT session_id) AS sessions, MIN(created_at) AS first_seen FROM %i WHERE visitor_id = %s',
				Visitor_Query::table(),
				$visitor_id
			),
			ARRAY_A
		);
		return array(
			'sessions'   => (int) ( $row['sessions'] ?? 0 ),
			'first_seen' => (string) ( $row['first_seen'] ?? '' ),
		);
	}

	/**
	 * Ce qu'une adresse IP a fait sur toute la durée conservée : visiteurs
	 * distincts, sessions et commandes. Plusieurs « clients » derrière une
	 * même adresse signalent un réseau partagé (4G, entreprise) ou un abus.
	 *
	 * @param string $ip Adresse en clair, validée.
	 * @return array{visitors: int, sessions: int, orders: int}
	 */
	public function ip_history( string $ip ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed read of a custom table.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor_id) AS visitors, COUNT(DISTINCT session_id) AS sessions, SUM(event = 'order_placed') AS orders FROM %i WHERE ip = %s",
				Visitor_Query::table(),
				$ip
			),
			ARRAY_A
		);
		return array(
			'visitors' => (int) ( $row['visitors'] ?? 0 ),
			'sessions' => (int) ( $row['sessions'] ?? 0 ),
			'orders'   => (int) ( $row['orders'] ?? 0 ),
		);
	}
}
