<?php
/**
 * Fiche produit, lot 50 : tableau des mesures (volet « Les tailles »).
 *
 * Saisi produit par produit dans l'onglet « Fiche FAMMA » de Produits →
 * Modifier (`Product_Size_Chart_Admin`), en français et en derja. Une ligne
 * par rangée, colonnes séparées par « | », la première ligne donne les
 * titres. Rien n'est écrit en dur : sans saisie, pas de tableau (§60).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lecture du tableau des mesures d'un produit.
 */
final class Product_Size_Chart {

	/**
	 * Saisie brute, une méta par langue : `_famma_size_chart_<fr|ar>`.
	 *
	 * @var string
	 */
	public const META = '_famma_size_chart_';

	/**
	 * Rangées au plus (titres compris).
	 *
	 * @var int
	 */
	private const MAX_ROWS = 14;

	/**
	 * Colonnes au plus.
	 *
	 * @var int
	 */
	private const MAX_COLS = 6;

	/**
	 * Tableau prêt à afficher, ou tableau vide.
	 *
	 * Dans la langue de la page, sinon dans l'autre (avec sa langue). Il faut
	 * une ligne de titres et au moins une rangée.
	 *
	 * @param \WC_Product $product Produit affiché.
	 * @return array{lang: string, head: array<int, string>, rows: array<int, array<int, string>>}|array{}
	 */
	public static function chart( \WC_Product $product ): array {
		$page = is_rtl() ? 'ar' : 'fr';

		foreach ( array( $page, 'ar' === $page ? 'fr' : 'ar' ) as $lang ) {
			$lines = self::parse( (string) $product->get_meta( self::META . $lang ) );

			if ( count( $lines ) >= 2 ) {
				return array(
					'lang' => $lang,
					'head' => array_shift( $lines ),
					'rows' => $lines,
				);
			}
		}

		return array();
	}

	/**
	 * Découpe la saisie : lignes vides ignorées, cellules nettoyées, chaque
	 * rangée ramenée au nombre de colonnes des titres.
	 *
	 * @param string $raw Saisie.
	 * @return array<int, array<int, string>>
	 */
	public static function parse( string $raw ): array {
		$lines = array();
		$width = 0;
		$split = preg_split( '/\r\n|\r|\n/', $raw );

		foreach ( is_array( $split ) ? $split : array() as $line ) {
			if ( '' === trim( str_replace( '|', '', $line ) ) ) {
				continue;
			}

			// Une barre en début et en fin de ligne (forme des tableaux en texte) ne compte pas.
			$line = trim( $line );
			$line = '|' === substr( $line, 0, 1 ) ? substr( $line, 1 ) : $line;
			$line = '|' === substr( $line, -1 ) ? substr( $line, 0, -1 ) : $line;

			$cells = array_map(
				static function ( string $cell ): string {
					return trim( sanitize_text_field( $cell ) );
				},
				array_slice( explode( '|', $line ), 0, self::MAX_COLS )
			);

			if ( 0 === $width ) {
				// Titres : colonnes vides de fin retirées.
				while ( array() !== $cells && '' === end( $cells ) ) {
					array_pop( $cells );
				}
				$width = count( $cells );

				if ( 0 === $width ) {
					continue;
				}
			}

			$cells   = array_pad( array_slice( $cells, 0, $width ), $width, '' );
			$lines[] = $cells;

			if ( count( $lines ) >= self::MAX_ROWS ) {
				break;
			}
		}

		return $lines;
	}
}
