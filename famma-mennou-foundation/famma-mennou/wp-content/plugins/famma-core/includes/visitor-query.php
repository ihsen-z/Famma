<?php
/**
 * Parcours visiteurs — socle commun des lectures.
 *
 * Avant la 0.3.49, chaque module de lecture (synthèse, sources, produits,
 * performance, formulaire, types de session) recopiait le même début de
 * période, le même nom de table et le même cache de cinq minutes. Une seule
 * définition ici : changer la durée du cache ou la façon de borner une
 * période ne se fait plus qu'à un endroit.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Période, table et cache des agrégats.
 */
final class Visitor_Query {

	/**
	 * Durée du cache des agrégats, en secondes.
	 */
	public const CACHE_TTL = 300;

	/**
	 * Préfixe des transients d'agrégats.
	 */
	private const PREFIX = 'famma_vj_';

	/**
	 * Début de la période, en UTC, au format de la colonne `created_at`.
	 *
	 * @param int $days Nombre de jours.
	 * @return string
	 */
	public static function since( int $days ): string {
		return gmdate( 'Y-m-d H:i:s', time() - max( 1, $days ) * DAY_IN_SECONDS );
	}

	/**
	 * Nom complet de la table des événements.
	 *
	 * @return string
	 */
	public static function table(): string {
		return Visitor_Store::instance()->table();
	}

	/**
	 * Lit un agrégat en cache, ou le calcule et le garde cinq minutes.
	 *
	 * Le résultat n'est que des nombres et des libellés techniques : jamais
	 * une adresse IP ni une saisie, qui n'ont rien à faire dans `wp_options`.
	 *
	 * @param string   $key     Nom court de l'agrégat (ex. `summary_30`).
	 * @param callable $compute Calcul, renvoie un tableau.
	 * @return array<mixed>
	 */
	public static function cached( string $key, callable $compute ): array {
		$name   = self::PREFIX . $key;
		$cached = get_transient( $name );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$rows = $compute();
		$rows = is_array( $rows ) ? $rows : array();
		set_transient( $name, $rows, self::CACHE_TTL );
		return $rows;
	}
}
