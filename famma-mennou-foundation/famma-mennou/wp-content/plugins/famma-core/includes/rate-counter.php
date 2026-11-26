<?php
/**
 * Compteurs de débit bornés des routes publiques (lot 59, I2 et M2).
 *
 * Un compteur = UNE ligne `wp_options` (`famma_rate_<nom>`, jamais chargée
 * d'office) dont la valeur porte la tranche de dix minutes et le compte :
 * `tranche × 100 000 + compte`. Une seule requête l'incrémente, de façon
 * atomique (`INSERT … ON DUPLICATE KEY UPDATE`), et le remet à zéro quand la
 * tranche change. Contrairement à un transient :
 * - aucune ligne de délai (`_transient_timeout_…`), donc une écriture par appel ;
 * - aucune ligne nouvelle par tranche, rien à purger ;
 * - pas de lecture puis réécriture : deux requêtes simultanées comptent toutes les deux.
 * Le nombre de lignes est borné par le nombre de noms possibles
 * (`Visitor_Context::rate_group()` : 4 096 groupes d'adresses par route).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Compteurs par tranche de dix minutes, en lecture puis incrément atomique.
 */
final class Rate_Counter {

	/**
	 * Préfixe des lignes.
	 */
	public const PREFIX = 'famma_rate_';

	/**
	 * Durée d'une tranche, en secondes.
	 */
	public const WINDOW = 600;

	/**
	 * Multiplicateur de la tranche dans la valeur (compte maximal + 1).
	 */
	private const SPAN = 100000;

	/**
	 * Compte de la tranche en cours (0 si la ligne date d'une autre tranche).
	 *
	 * @param string $name Nom du compteur, sans préfixe.
	 * @param int    $now  Horloge.
	 * @return int
	 */
	public static function used( string $name, int $now ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Compteur de débit : lecture fraîche voulue, jamais mise en cache.
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::PREFIX . $name ) );
		$value = is_numeric( $value ) ? (int) $value : 0;

		return intdiv( $value, self::SPAN ) === intdiv( $now, self::WINDOW ) ? $value % self::SPAN : 0;
	}

	/**
	 * Ajoute au compteur, en une requête atomique ; repart de zéro à chaque tranche.
	 *
	 * @param string $name Nom du compteur, sans préfixe.
	 * @param int    $cost Unités à ajouter.
	 * @param int    $now  Horloge.
	 * @return void
	 */
	public static function add( string $name, int $cost, int $now ): void {
		global $wpdb;
		$slot  = intdiv( $now, self::WINDOW );
		$cost  = max( 0, min( self::SPAN - 1, $cost ) );
		$fresh = (string) ( $slot * self::SPAN + $cost );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Incrément atomique d'une seule ligne ; jamais mis en cache.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off') ON DUPLICATE KEY UPDATE option_value = IF( CAST( option_value AS UNSIGNED ) DIV %d = %d, CAST( option_value AS UNSIGNED ) + %d, %s )", self::PREFIX . $name, $fresh, self::SPAN, $slot, $cost, $fresh ) );
	}

	/**
	 * Supprime les compteurs dont le nom commence par `$name` (désactivation).
	 *
	 * @param string $name Début du nom, sans préfixe.
	 * @return void
	 */
	public static function clear( string $name ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Nettoyage préparé à la désactivation.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::PREFIX . $name ) . '%' ) );
	}
}
