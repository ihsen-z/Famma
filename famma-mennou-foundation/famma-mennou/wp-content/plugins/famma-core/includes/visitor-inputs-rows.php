<?php
/**
 * Saisies des visiteurs — lecture filtrée et gestion par l'équipe.
 *
 * Ce que la page « Saisies à relancer » et l'export lisent (filtres statut,
 * recherche, produit, période) et ce que l'équipe y écrit (statut, remarque,
 * lien vers la commande créée). La capture côté site reste dans
 * `Visitor_Inputs`.
 *
 * Toutes les requêtes passent par `$wpdb->prepare()` (règle 4) ; la clause
 * WHERE n'assemble que des fragments fixes, les valeurs restent des
 * marqueurs `%s` / `%d`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Liste, compteurs, statut et remarque des lignes de saisie.
 */
final class Visitor_Inputs_Rows {

	/**
	 * Périodes proposées, en jours (0 : tout).
	 */
	public const PERIODS = array( 0, 1, 7, 30 );

	/**
	 * Filtres lus dans la requête, bornés aux valeurs admises.
	 *
	 * @param array<string, mixed> $src Source (`$_GET` ou `$_POST`, non déslashée).
	 * @return array{status: string, s: string, product: int, days: int}
	 */
	public static function filters( array $src ): array {
		$get    = static function ( string $key ) use ( $src ): string {
			return isset( $src[ $key ] ) && is_scalar( $src[ $key ] ) ? (string) wp_unslash( (string) $src[ $key ] ) : '';
		};
		$status = '' !== $get( 'status' ) ? sanitize_key( $get( 'status' ) ) : 'open';
		$days   = absint( $get( 'days' ) );
		return array(
			'status'  => in_array( $status, Visitor_Inputs::STATUSES, true ) ? $status : ( 'all' === $status ? 'all' : 'open' ),
			's'       => mb_substr( sanitize_text_field( $get( 's' ) ), 0, 100 ),
			'product' => absint( $get( 'product' ) ),
			'days'    => in_array( $days, self::PERIODS, true ) ? $days : 0,
		);
	}

	/**
	 * Clause WHERE et ses valeurs pour des filtres donnés.
	 *
	 * @param array{status: string, s: string, product: int, days: int} $f           Filtres.
	 * @param bool                                                      $with_status Appliquer le statut.
	 * @return array{0: string, 1: array<int, int|string>}
	 */
	private static function where( array $f, bool $with_status = true ): array {
		global $wpdb;
		$sql  = array( '1=1' );
		$args = array();
		if ( $with_status && 'all' !== $f['status'] ) {
			$sql[]  = 'status = %s';
			$args[] = $f['status'];
		}
		if ( $f['product'] > 0 ) {
			$sql[]  = 'product_id = %d';
			$args[] = $f['product'];
		}
		if ( $f['days'] > 0 ) {
			$sql[]  = 'updated_at >= %s';
			$args[] = gmdate( 'Y-m-d H:i:s', time() - $f['days'] * DAY_IN_SECONDS );
		}
		if ( '' !== $f['s'] ) {
			$like  = '%' . $wpdb->esc_like( $f['s'] ) . '%';
			$sql[] = '( first_name LIKE %s OR phone LIKE %s OR phone_norm LIKE %s OR address LIKE %s OR city LIKE %s OR campaign LIKE %s OR note LIKE %s )';
			array_push( $args, $like, $like, $like, $like, $like, $like, $like );
		}
		return array( implode( ' AND ', $sql ), $args );
	}

	/**
	 * Lignes filtrées, les plus récentes d'abord.
	 *
	 * @param array{status: string, s: string, product: int, days: int} $f      Filtres.
	 * @param int                                                       $limit  Lignes au plus.
	 * @param int                                                       $offset Décalage.
	 * @return array<int, array<string, string>>
	 */
	public static function rows( array $f, int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		list( $where, $args ) = self::where( $f );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom table; $where holds fixed fragments with placeholders only.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE $where ORDER BY updated_at DESC LIMIT %d OFFSET %d", Visitor_Inputs::instance()->table(), ...array_merge( $args, array( $limit, $offset ) ) ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Nombre de lignes par statut, pour les autres filtres en vigueur.
	 *
	 * @param array{status: string, s: string, product: int, days: int} $f Filtres.
	 * @return array<string, int>
	 */
	public static function counts( array $f ): array {
		global $wpdb;
		list( $where, $args ) = self::where( $f, false );
		$out                  = array_fill_keys( Visitor_Inputs::STATUSES, 0 );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom table; $where holds fixed fragments with placeholders only.
		$found = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM %i WHERE $where GROUP BY status", Visitor_Inputs::instance()->table(), ...$args ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		foreach ( (array) $found as $row ) {
			if ( isset( $out[ $row['status'] ] ) ) {
				$out[ $row['status'] ] = (int) $row['n'];
			}
		}
		return $out;
	}

	/**
	 * Nombre de lignes à relancer (pastille du menu).
	 *
	 * @return int
	 */
	public static function open_count(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- admin counter on a custom table.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', Visitor_Inputs::instance()->table(), 'open' ) );
	}

	/**
	 * Produits présents dans les saisies : identifiant → titre.
	 *
	 * @return array<int, string>
	 */
	public static function products(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- admin filter list on a custom table.
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT product_id FROM %i WHERE product_id > %d', Visitor_Inputs::instance()->table(), 0 ) );
		$out = array();
		foreach ( (array) $ids as $id ) {
			$title            = get_the_title( (int) $id );
			$out[ (int) $id ] = '' !== $title ? $title : '#' . (int) $id;
		}
		asort( $out );
		return $out;
	}

	/**
	 * Une ligne par son identifiant, ou null.
	 *
	 * @param int $id Ligne.
	 * @return array<string, string>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, keyed lookup.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Visitor_Inputs::instance()->table(), $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Change le statut d'une ligne, ou la supprime (« delete »).
	 *
	 * « Commandé » ne se pose pas à la main : il vient d'une vraie commande.
	 *
	 * @param int    $id     Ligne.
	 * @param string $status Nouveau statut, ou « delete ».
	 * @return bool
	 */
	public static function set_status( int $id, string $status ): bool {
		global $wpdb;
		$table = Visitor_Inputs::instance()->table();
		if ( 'delete' === $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table write.
			return false !== $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		}
		if ( 'converted' === $status || ! in_array( $status, Visitor_Inputs::STATUSES, true ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table write.
		return false !== $wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * Écrit la remarque d'une ligne.
	 *
	 * @param int    $id   Ligne.
	 * @param string $note Remarque (déjà `wp_unslash()`).
	 * @return string|null Remarque enregistrée, ou null en cas d'échec.
	 */
	public static function set_note( int $id, string $note ): ?string {
		global $wpdb;
		$note = mb_substr( trim( sanitize_textarea_field( $note ) ), 0, Visitor_Inputs::NOTE_MAX );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table write.
		$done = $wpdb->update( Visitor_Inputs::instance()->table(), array( 'note' => $note ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
		return false === $done ? null : $note;
	}

	/**
	 * Relie une ligne à la commande créée depuis elle, quel que soit son statut.
	 *
	 * @param int $id       Ligne.
	 * @param int $order_id Commande.
	 * @return bool
	 */
	public static function set_converted( int $id, int $order_id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table write.
		return false !== $wpdb->update(
			Visitor_Inputs::instance()->table(),
			array(
				'status'     => 'converted',
				'order_id'   => $order_id,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}
}
