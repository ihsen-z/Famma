<?php
/**
 * Nouveau look — briques de la fiche produit (onglet « Fiche produit »).
 *
 * Les mêmes réglages valent pour toutes les fiches. Forme d'une entrée : voir
 * `New_Look_Registry`. Les textes sont des clés de `Product_Content::schema()`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Briques de l'onglet « Fiche produit ».
 */
final class New_Look_Bricks_Product {

	/**
	 * Briques, dans leur ordre d'affichage.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function bricks(): array {
		return array(
			'ledger' => array(
				'title'   => __( 'Bande « Livraison 0 DT · Avant réception 0 DT »', 'famma-core' ),
				'summary' => __( 'Sous le prix : prix, livraison 0 DT, 0 DT avant de recevoir, montant au livreur.', 'famma-core' ),
				'help'    => __( 'Sous le prix, un petit tableau : prix du produit, livraison 0 DT, 0 DT avant de recevoir, montant à payer au livreur. Les zéros ne s’affichent que s’ils sont vrais (livraison gratuite et paiement à la livraison activés dans WooCommerce). Textes juste en dessous.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array( 'ledger_price', 'ledger_delivery', 'ledger_before', 'ledger_total' ),
				'wait'    => array(),
			),
		);
	}
}
