<?php
/**
 * Parcours visiteurs — stockage des événements.
 *
 * Une table dédiée plutôt que des options ou des métas : chaque visite produit
 * plusieurs lignes, et la vue d'administration agrège par session et par
 * période. Une table indexée sur `(event, created_at)` et `session_id` garde
 * ces agrégats rapides, là où `postmeta` s'effondrerait.
 *
 * Les lignes brutes vivent `retention_days()` jours (180 par défaut), puis une
 * purge quotidienne les supprime par paquets : jamais un DELETE géant qui
 * bloquerait la base sur l'hébergement mutualisé.
 *
 * Aucune IP n'est stockée en clair : seulement une empreinte salée et tronquée,
 * suffisante pour repérer un abus, inutilisable pour identifier quelqu'un.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Table `famma_visitor_events` : création, écriture groupée, purge.
 */
final class Visitor_Store {

	/**
	 * Version du schéma ; la changer relance `dbDelta()`.
	 */
	private const SCHEMA = '1';

	/**
	 * Option mémorisant la version du schéma installée.
	 */
	private const SCHEMA_OPTION = 'famma_visitor_schema_version';

	/**
	 * Option portant la durée de conservation, en jours.
	 */
	public const RETENTION_OPTION = 'famma_visitor_retention_days';

	/**
	 * Événement cron de purge.
	 */
	private const PURGE_HOOK = 'famma_visitor_purge';

	/**
	 * Lignes supprimées par passage de purge.
	 */
	private const PURGE_BATCH = 5000;

	/**
	 * Instance partagée.
	 *
	 * @var Visitor_Store|null
	 */
	private static ?Visitor_Store $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Visitor_Store
	 */
	public static function instance(): Visitor_Store {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les points d'accroche.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'init', array( $this, 'install' ), 5 );
		add_action( 'init', array( $this, 'schedule_purge' ) );
		add_action( self::PURGE_HOOK, array( $this, 'purge' ) );
	}

	/**
	 * Nom complet de la table.
	 *
	 * @return string
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'famma_visitor_events';
	}

	/**
	 * Durée de conservation des lignes brutes, bornée entre 7 et 365 jours.
	 *
	 * @return int
	 */
	public function retention_days(): int {
		$days = absint( get_option( self::RETENTION_OPTION, 180 ) );
		return max( 7, min( 365, $days ? $days : 180 ) );
	}

	/**
	 * Crée ou met à jour la table, une seule fois par version de schéma.
	 *
	 * @return void
	 */
	public function install(): void {
		if ( self::SCHEMA === get_option( self::SCHEMA_OPTION, '' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $this->table();
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			visitor_id char(36) NOT NULL,
			session_id char(36) NOT NULL,
			event varchar(32) NOT NULL,
			page_type varchar(16) NOT NULL DEFAULT '',
			path varchar(255) NOT NULL DEFAULT '',
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			label varchar(100) NOT NULL DEFAULT '',
			num int(11) NOT NULL DEFAULT 0,
			source varchar(64) NOT NULL DEFAULT '',
			medium varchar(64) NOT NULL DEFAULT '',
			campaign varchar(100) NOT NULL DEFAULT '',
			content varchar(100) NOT NULL DEFAULT '',
			device varchar(10) NOT NULL DEFAULT '',
			ip_hash char(16) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY event_time (event,created_at),
			KEY created_at (created_at),
			KEY session_id (session_id),
			KEY visitor_id (visitor_id),
			KEY order_id (order_id)
		) $collate;"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- schema verification needs an uncached read.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( $table === $found ) {
			update_option( self::SCHEMA_OPTION, self::SCHEMA, false );
		}
	}

	/**
	 * Planifie la purge quotidienne si elle ne l'est pas encore.
	 *
	 * @return void
	 */
	public function schedule_purge(): void {
		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}
	}

	/**
	 * Écrit plusieurs événements en une seule requête.
	 *
	 * Les lignes arrivent déjà assainies par le collecteur ; ici, seul le
	 * typage SQL est garanti, via des marqueurs `prepare()` par colonne.
	 *
	 * @param array<int, array<string, int|string>> $rows Événements normalisés.
	 * @return int Nombre de lignes écrites.
	 */
	public function insert_many( array $rows ): int {
		if ( array() === $rows ) {
			return 0;
		}
		global $wpdb;
		$columns = array(
			'created_at' => '%s',
			'visitor_id' => '%s',
			'session_id' => '%s',
			'event'      => '%s',
			'page_type'  => '%s',
			'path'       => '%s',
			'product_id' => '%d',
			'order_id'   => '%d',
			'label'      => '%s',
			'num'        => '%d',
			'source'     => '%s',
			'medium'     => '%s',
			'campaign'   => '%s',
			'content'    => '%s',
			'device'     => '%s',
			'ip_hash'    => '%s',
		);
		$tuple   = '(' . implode( ',', $columns ) . ')';
		$values  = array();
		$params  = array();
		foreach ( $rows as $row ) {
			$values[] = $tuple;
			foreach ( $columns as $name => $format ) {
				$params[] = $row[ $name ] ?? ( '%d' === $format ? 0 : '' );
			}
		}
		$sql = 'INSERT INTO %i (' . implode( ',', array_keys( $columns ) ) . ') VALUES ' . implode( ',', $values );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- custom table; $sql only holds fixed column names and placeholders.
		$done = $wpdb->query( $wpdb->prepare( $sql, $this->table(), ...$params ) );
		return is_int( $done ) ? $done : 0;
	}

	/**
	 * Événements d'une liste de visiteurs, pour l'export des données personnelles.
	 *
	 * @param array<int, string> $visitor_ids Identifiants visiteurs (UUID vérifiés).
	 * @param int                $limit       Lignes au plus.
	 * @return array<int, array<string, string>>
	 */
	public function events_for_visitors( array $visitor_ids, int $limit = 2000 ): array {
		if ( array() === $visitor_ids ) {
			return array();
		}
		global $wpdb;
		$marks = implode( ',', array_fill( 0, count( $visitor_ids ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom table; $marks only holds %s placeholders, values passed by spread.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, created_at, event, page_type, path, label, num, source, medium, campaign, device FROM %i WHERE visitor_id IN ($marks) ORDER BY id LIMIT %d", $this->table(), ...array_merge( $visitor_ids, array( $limit ) ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Supprime tous les événements d'une liste de visiteurs.
	 *
	 * @param array<int, string> $visitor_ids Identifiants visiteurs (UUID vérifiés).
	 * @return int Lignes supprimées.
	 */
	public function delete_visitors( array $visitor_ids ): int {
		if ( array() === $visitor_ids ) {
			return 0;
		}
		global $wpdb;
		$marks = implode( ',', array_fill( 0, count( $visitor_ids ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom table; $marks only holds %s placeholders, values passed by spread.
		$done = $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE visitor_id IN ($marks)", $this->table(), ...$visitor_ids ) );
		return is_int( $done ) ? $done : 0;
	}

	/**
	 * Supprime les lignes plus anciennes que la durée de conservation.
	 *
	 * Paquets bornés et au plus vingt passages par jour : une base en retard se
	 * rattrape en quelques jours sans jamais bloquer la boutique.
	 *
	 * @return void
	 */
	public function purge(): void {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $this->retention_days() * DAY_IN_SECONDS );
		for ( $pass = 0; $pass < 20; $pass++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded maintenance delete on a custom table.
			$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s LIMIT %d', $this->table(), $cutoff, self::PURGE_BATCH ) );
			if ( ! is_int( $deleted ) || $deleted < self::PURGE_BATCH ) {
				break;
			}
		}
	}
}
