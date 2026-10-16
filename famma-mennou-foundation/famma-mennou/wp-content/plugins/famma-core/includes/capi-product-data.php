<?php
/**
 * Données produit des événements serveur Meta (OrderPlaced et Purchase).
 *
 * Deux produits tournent en publicité en même temps : sans identifiant
 * produit dans `custom_data`, Meta ne peut pas séparer leurs commandes
 * (conversions personnalisées, rapports, règles). Ce fichier construit,
 * en un seul endroit, tout ce qui décrit les lignes d'une commande :
 * `contents`, `content_ids`, `content_name`, `num_items` et `famma_product`.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Bloc produit de `custom_data`, identique pour OrderPlaced et Purchase.
 */
final class Capi_Product_Data {

	/**
	 * Séparateur des noms dans `content_name`.
	 */
	public const NAME_SEPARATOR = ' | ';

	/**
	 * Séparateur des slugs dans `famma_product`.
	 */
	public const SLUG_SEPARATOR = ',';

	/**
	 * Longueur maximale de `content_name` et `famma_product`.
	 */
	private const MAX_LENGTH = 500;

	/**
	 * Construit le bloc produit d'une commande.
	 *
	 * Une ligne dont le produit n'existe plus est ignorée partout, pour que
	 * `contents`, `content_ids` et `num_items` restent cohérents entre eux.
	 * `content_ids` reprend exactement `contents[].id` (SKU, sinon ID du
	 * produit ou de la variation), sans doublon. `famma_product` porte le
	 * slug du produit parent : les tailles d'un même produit variable ne
	 * donnent qu'un seul slug.
	 *
	 * @param \WC_Order $order Commande enregistrée.
	 * @return array{contents: array<int,array<string,mixed>>, content_ids: array<int,string>, content_name: string, num_items: int, famma_product: string}
	 */
	public static function for_order( \WC_Order $order ): array {
		$contents  = array();
		$ids       = array();
		$names     = array();
		$slugs     = array();
		$num_items = 0;

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$quantity = max( 0, (int) $item->get_quantity() );
			$sku      = (string) $product->get_sku();
			$id       = '' !== $sku ? $sku : (string) $product->get_id();

			$contents[] = array(
				'id'         => $id,
				'quantity'   => $quantity,
				'item_price' => round( (float) $order->get_line_subtotal( $item, false, false ) / max( 1, $quantity ), 3 ),
			);
			$ids[]      = $id;
			$num_items += $quantity;

			$name = self::clean_name( (string) $item->get_name() );
			if ( '' !== $name ) {
				$names[] = $name;
			}
			$slug = self::parent_slug( $product );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array(
			'contents'      => $contents,
			'content_ids'   => array_values( array_unique( $ids ) ),
			'content_name'  => self::truncate( implode( self::NAME_SEPARATOR, array_unique( $names ) ) ),
			'num_items'     => $num_items,
			'famma_product' => self::truncate( implode( self::SLUG_SEPARATOR, array_unique( $slugs ) ) ),
		);
	}

	/**
	 * Slug du produit, ou de son parent pour une variation.
	 *
	 * @param \WC_Product $product Produit de la ligne.
	 * @return string Slug assaini, ou chaîne vide.
	 */
	private static function parent_slug( \WC_Product $product ): string {
		$parent_id = (int) $product->get_parent_id();
		if ( $parent_id > 0 ) {
			$parent = wc_get_product( $parent_id );
			if ( $parent instanceof \WC_Product ) {
				$product = $parent;
			}
		}

		return sanitize_title( (string) $product->get_slug() );
	}

	/**
	 * Nom de ligne en texte brut, sans balise, entité ni séparateur.
	 *
	 * @param string $name Nom de la ligne de commande.
	 * @return string
	 */
	private static function clean_name( string $name ): string {
		$name = html_entity_decode( wp_strip_all_tags( $name ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$name = str_replace( '|', '/', $name );

		return trim( sanitize_text_field( $name ) );
	}

	/**
	 * Borne une chaîne sans couper un caractère multioctet.
	 *
	 * @param string $value Chaîne complète.
	 * @return string
	 */
	private static function truncate( string $value ): string {
		return mb_substr( $value, 0, self::MAX_LENGTH, 'UTF-8' );
	}
}
