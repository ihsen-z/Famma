<?php
/**
 * Identifiant produit unique des événements Meta (lot 5, S3-b, R3).
 *
 * Avant le lot 5, trois générateurs fabriquaient chacun leur identifiant :
 * l'ID WooCommerce côté navigateur (le parent dans ViewContent, la variation
 * dans AddToCart), le SKU côté express et côté CAPI. Pour une taille sans
 * SKU propre, WooCommerce renvoie même le SKU du parent (contexte `view`) :
 * toutes les tailles portaient alors le même identifiant (T1.7, H-N4).
 *
 * Règle unique, appliquée partout (navigateur, express, CAPI, futur flux
 * catalogue) : l'ID WooCommerce, jamais le SKU (décision du propriétaire du
 * 20/10/2026). Un SKU saisi ou modifié plus tard changerait l'identifiant en
 * silence et couperait le lien avec l'historique et les audiences Meta ;
 * l'ID, lui, ne change jamais et vaut déjà ce que Meta reçoit (ex. 279).
 *
 * 1. variation WooCommerce : son ID ;
 * 2. autre produit : son ID. Les tailles de famma-core (Product_Sizes) sont des
 *    options d'un produit simple : toutes les tailles partagent son ID.
 *
 * Les tailles d'un produit variable sont regroupées par `item_group_id`, l'ID
 * du produit parent. Le filtre `famma_meta_content_id` s'applique à chaque
 * identifiant, serveur compris : le jour où un catalogue préfixe ses
 * identifiants, un seul filtre aligne tous les événements.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Identifiants produit partagés par tous les émetteurs Meta.
 */
final class Meta_Ids {

	/**
	 * Identifiant `content_id` d'un produit ou d'une variation.
	 *
	 * @param \WC_Product $product Produit, ou variation réellement achetée.
	 * @return string Jamais vide.
	 */
	public static function content_id( \WC_Product $product ): string {
		$id = (string) $product->get_id();

		$filtered = apply_filters( 'famma_meta_content_id', $id, $product );

		return is_scalar( $filtered ) && '' !== trim( (string) $filtered ) ? (string) $filtered : $id;
	}

	/**
	 * Groupe d'une taille : l'ID du produit parent (`item_group_id`).
	 *
	 * Une variation renvoie l'ID de son parent, un produit variable son propre
	 * ID (il est le groupe), un produit simple une chaîne vide (aucun groupe).
	 *
	 * @param \WC_Product $product Produit ou variation.
	 * @return string
	 */
	public static function item_group_id( \WC_Product $product ): string {
		if ( self::is_variation( $product ) ) {
			return (string) $product->get_parent_id();
		}

		return self::is_variable( $product ) ? (string) $product->get_id() : '';
	}

	/**
	 * Cible d'un ViewContent : le produit, ou le groupe d'un produit variable.
	 *
	 * La fiche d'un produit variable ne montre pas encore de taille : la doc
	 * du Pixel prévoit alors `content_type` = `product_group`, avec l'ID du
	 * groupe, celui que portent les tailles dans `item_group_id`.
	 *
	 * @param \WC_Product $product Produit affiché.
	 * @return array{content_type: string, content_ids: array<int, string>}
	 */
	public static function view_target( \WC_Product $product ): array {
		if ( self::is_variable( $product ) ) {
			return array(
				'content_type' => 'product_group',
				'content_ids'  => array( self::item_group_id( $product ) ),
			);
		}

		return array(
			'content_type' => 'product',
			'content_ids'  => array( self::content_id( $product ) ),
		);
	}

	/**
	 * Le produit est-il une variation (une taille) ?
	 *
	 * @param \WC_Product $product Produit.
	 * @return bool
	 */
	private static function is_variation( \WC_Product $product ): bool {
		return $product instanceof \WC_Product_Variation;
	}

	/**
	 * Le produit est-il un produit variable (le parent des tailles) ?
	 *
	 * @param \WC_Product $product Produit.
	 * @return bool
	 */
	private static function is_variable( \WC_Product $product ): bool {
		return $product instanceof \WC_Product_Variable;
	}
}
