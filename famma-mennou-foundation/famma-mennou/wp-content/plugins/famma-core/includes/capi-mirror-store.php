<?php
/**
 * Doublon serveur (lot 59) : lignes de la file, marques de rejeu et verrou d'envoi.
 *
 * Une ligne `wp_options` par événement (`famma_mq_<empreinte de nom|event_id>`,
 * jamais chargée d'office). Toutes les écritures sont des requêtes SQL
 * atomiques, car `add_option()` ne l'est pas (`get_option()` puis
 * `INSERT … ON DUPLICATE KEY UPDATE`, `wp-includes/option.php`, relecture I3) :
 * - mise en file : UN `INSERT IGNORE` multi-lignes par lot ; le nombre de
 *   lignes réellement insérées dit combien d'événements sont nouveaux ;
 * - après l'envoi (ou la perte), la ligne garde son nom 24 h mais sa valeur
 *   devient `s:<heure>` : l'IP, le user-agent et les empreintes sont effacés
 *   tout de suite, et un event_id rejoué pendant 24 h est refusé (I1) ;
 * - verrou : `INSERT IGNORE` de la ligne `famma_capi_mirror_lock`, dont la
 *   valeur porte l'échéance et un jeton ; un verrou échu n'est repris que
 *   par un `UPDATE … WHERE option_value = <valeur lue>` (un seul gagnant).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Stockage de la file du doublon.
 */
final class Capi_Mirror_Store {

	/**
	 * Préfixe des lignes de la file.
	 */
	public const PREFIX = 'famma_mq_';

	/**
	 * Verrou d'envoi.
	 */
	public const LOCK = 'famma_capi_mirror_lock';

	/**
	 * Début de la valeur d'une ligne traitée (marque de rejeu, sans donnée).
	 */
	public const DONE = 's:';

	/**
	 * Durée de la marque de rejeu, en secondes.
	 */
	public const REPLAY_WINDOW = DAY_IN_SECONDS;

	/**
	 * Durée d'un verrou, en secondes (au-delà, il est considéré comme abandonné).
	 */
	public const LOCK_TTL = 120;

	/**
	 * Nom de la ligne d'un événement (même nom + event_id → même ligne).
	 *
	 * @param array<string, mixed> $event Événement Meta.
	 * @return string
	 */
	public static function name( array $event ): string {
		return self::PREFIX . substr( hash( 'sha256', $event['event_name'] . '|' . $event['event_id'] ), 0, 32 );
	}

	/**
	 * Range des événements en une requête ; renvoie le nombre de nouveaux.
	 *
	 * @param array<int, array<string, mixed>> $events Événements Meta (`Capi_Mirror_Event`).
	 * @param int                              $now    Horloge.
	 * @return int
	 */
	public static function insert( array $events, int $now ): int {
		$args = array();
		foreach ( $events as $event ) {
			$row = wp_json_encode(
				array(
					'e' => $event,
					'q' => $now,
					'n' => 0,
				)
			);
			if ( is_string( $row ) ) {
				array_push( $args, self::name( $event ), $row );
			}
		}
		if ( array() === $args ) {
			return 0;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- Une insertion préparée pour tout le lot : seuls des « (%s, %s, 'off') » sont répétés, les valeurs passent par $args.
		$done = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES " . implode( ',', array_fill( 0, intdiv( count( $args ), 2 ), "(%s, %s, 'off')" ) ), $args ) );
		return is_int( $done ) ? $done : 0;
	}

	/**
	 * Lignes en attente, les plus anciennes d'abord, décodées.
	 *
	 * @param int $limit Nombre maximal.
	 * @return array<string, array<string, mixed>>
	 */
	public static function pending( int $limit ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- File d'attente : lecture fraîche voulue, jamais mise en cache.
		$found = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value NOT LIKE %s ORDER BY option_id ASC LIMIT %d", $wpdb->esc_like( self::PREFIX ) . '%', $wpdb->esc_like( self::DONE ) . '%', $limit ), ARRAY_A );
		$rows  = array();
		$bad   = array();
		foreach ( is_array( $found ) ? $found : array() as $line ) {
			$row = json_decode( (string) ( $line['option_value'] ?? '' ), true );
			if ( is_array( $row ) && isset( $row['e'], $row['q'] ) && is_array( $row['e'] ) ) {
				$rows[ (string) $line['option_name'] ] = $row + array( 'n' => 0 );
			} else {
				$bad[] = (string) ( $line['option_name'] ?? '' );
			}
		}
		self::mark( $bad, time() );
		return $rows;
	}

	/**
	 * Lignes traitées (envoyées ou perdues) : données effacées, nom gardé 24 h.
	 *
	 * @param array<int, string> $names Noms des lignes.
	 * @param int                $now   Horloge.
	 * @return void
	 */
	public static function mark( array $names, int $now ): void {
		$names = array_values( array_filter( $names, static fn( string $n ): bool => str_starts_with( $n, self::PREFIX ) ) );
		if ( array() === $names ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- Une mise à jour préparée pour tout le lot : seuls des %s sont répétés.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name IN (" . implode( ',', array_fill( 0, count( $names ), '%s' ) ) . ')', array_merge( array( self::DONE . $now ), $names ) ) );
	}

	/**
	 * Réécrit une ligne en attente (nombre de tentatives).
	 *
	 * @param string               $name Nom de la ligne.
	 * @param array<string, mixed> $row  Ligne décodée.
	 * @return void
	 */
	public static function update( string $name, array $row ): void {
		$value = wp_json_encode( $row );
		if ( ! is_string( $value ) ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ligne de la file, jamais mise en cache.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value NOT LIKE %s", $value, $name, $wpdb->esc_like( self::DONE ) . '%' ) );
	}

	/**
	 * Supprime les marques de rejeu de plus de 24 h.
	 *
	 * @param int $now Horloge.
	 * @return void
	 */
	public static function purge( int $now ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Purge préparée de la file.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s AND CAST( SUBSTRING( option_value, 3 ) AS UNSIGNED ) < %d", $wpdb->esc_like( self::PREFIX ) . '%', $wpdb->esc_like( self::DONE ) . '%', $now - self::REPLAY_WINDOW ) );
	}

	/**
	 * Vide la file et le verrou (désactivation du plugin, M6).
	 *
	 * @return void
	 */
	public static function clear(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Nettoyage préparé à la désactivation.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name = %s", $wpdb->esc_like( self::PREFIX ) . '%', self::LOCK ) );
	}

	/**
	 * Prend le verrou d'envoi ; renvoie son jeton, ou une chaîne vide s'il est tenu.
	 *
	 * @param int $now Horloge.
	 * @return string
	 */
	public static function lock( int $now ): string {
		global $wpdb;
		$token = ( $now + self::LOCK_TTL ) . ':' . bin2hex( random_bytes( 6 ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verrou atomique : une seule insertion réussit.
		if ( 1 === $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::LOCK, $token ) ) ) {
			return $token;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lecture fraîche du verrou.
		$held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) );
		if ( ! is_string( $held ) || (int) $held >= $now ) {
			return '';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reprise d'un verrou échu : seul l'appel qui voit encore l'ancienne valeur gagne.
		$took = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $token, self::LOCK, $held ) );
		return 1 === $took ? $token : '';
	}

	/**
	 * Rend le verrou, seulement s'il porte encore ce jeton.
	 *
	 * @param string $token Jeton rendu par {@see lock()}.
	 * @return void
	 */
	public static function unlock( string $token ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verrou : suppression conditionnelle au jeton.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK, $token ) );
	}
}
