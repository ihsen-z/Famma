<?php
/**
 * Affichage des prix ronds sans décimales (brique `price_whole`, onglet
 * « Partout » de FAMMA → Nouveau look).
 *
 * La boutique compte en millimes (3 décimales) : « 29,000 DT ». Les maquettes
 * montrent « 29 DT ». Plutôt que de passer WooCommerce à 0 décimale (ce qui
 * arrondirait un vrai prix en millimes), on demande à `wc_price()` de retirer
 * les zéros de fin : `woocommerce_price_trim_zeros`. Un prix à 29,500 DT
 * garde ses décimales. Les montants enregistrés ne changent pas.
 *
 * Les scripts qui recalculent un prix (formulaire express, bande « À payer »)
 * lisent `Price_Display::trim()` pour suivre la même règle.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Retire les décimales nulles des prix affichés.
 */
final class Price_Display {

	/**
	 * Pose le filtre (site seulement ; l'administration garde les millimes).
	 *
	 * @return void
	 */
	public static function hooks(): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		add_filter( 'woocommerce_price_trim_zeros', array( self::class, 'filter' ) );
	}

	/**
	 * Filtre de `wc_price()`.
	 *
	 * @param mixed $trim Valeur reçue.
	 * @return bool
	 */
	public static function filter( $trim ): bool {
		return (bool) $trim || self::trim();
	}

	/**
	 * La brique est-elle servie à la requête en cours ?
	 *
	 * @return bool
	 */
	public static function trim(): bool {
		return New_Look::part( 'price_whole' );
	}
}
