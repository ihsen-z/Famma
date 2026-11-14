<?php
/**
 * Nouveau look — briques du panier (onglet « Panier »).
 *
 * Forme d'une entrée : voir `New_Look_Registry`. Plan du lot : 6 briques en
 * 4 étapes (Docs « Plan du lot Panier », 14/11) ; l'étape 1 n'apporte que
 * `cart_look`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Briques de l'onglet « Panier ».
 */
final class New_Look_Bricks_Cart {

	/**
	 * Briques, dans leur ordre d'affichage.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function bricks(): array {
		return array(
			'cart_look' => array(
				'title'   => __( 'Présentation de la maquette', 'famma-core' ),
				'summary' => __( 'Cartes plus compactes, phrase « Aujourd’hui, vous payez 0 DT », récapitulatif qui suit l’écran, panier vide avec la mascotte.', 'famma-core' ),
				'help'    => __( 'Sur la page panier : chaque article dans une carte compacte (photo, nom, prix à l’unité, corbeille de retrait dans le coin, sélecteur − / + et total de la ligne) ; sous « Mon panier », la phrase « Aujourd’hui, vous payez 0 DT », seulement si le paiement à la livraison est activé ; sur ordinateur, le récapitulatif reste à l’écran pendant qu’on fait défiler les articles, et l’aide WhatsApp passe sous les articles ; sur téléphone, les produits proposés défilent en rangée. Panier vide : la mascotte FAMMA, un titre et une phrase. Les mises à jour du panier, la suppression avec « Annuler », les prix et la commande restent ceux de WooCommerce.', 'famma-core' ),
				'texts'   => array( Cart_Content::class ),
			),
		);
	}
}
