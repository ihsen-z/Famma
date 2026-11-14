<?php
/**
 * Nouveau look — briques du panier (onglet « Panier »).
 *
 * Forme d'une entrée : voir `New_Look_Registry`. Plan du lot : 6 briques en
 * 4 étapes (Docs « Plan du lot Panier », 14/11) : étape 1 `cart_look`,
 * étape 2 `cart_split` et `cart_dock`, étape 3 `cart_promo` et `cart_save`.
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
			'cart_look'  => array(
				'title'   => __( 'Présentation de la maquette', 'famma-core' ),
				'summary' => __( 'Cartes plus compactes, phrase « Aujourd’hui, vous payez 0 DT », récapitulatif qui suit l’écran, panier vide avec la mascotte.', 'famma-core' ),
				'help'    => __( 'Sur la page panier : chaque article dans une carte compacte (photo, nom, prix à l’unité, corbeille de retrait dans le coin, sélecteur − / + et total de la ligne) ; sous « Mon panier », la phrase « Aujourd’hui, vous payez 0 DT », seulement si le paiement à la livraison est activé ; sur ordinateur, le récapitulatif reste à l’écran pendant qu’on fait défiler les articles, et l’aide WhatsApp passe sous les articles ; sur téléphone, les produits proposés défilent en rangée. Panier vide : la mascotte FAMMA, un titre et une phrase. Les mises à jour du panier, la suppression avec « Annuler », les prix et la commande restent ceux de WooCommerce.', 'famma-core' ),
				'texts'   => array( 'lead', 'empty_title', 'empty_text' ),
			),
			'cart_split' => array(
				'title'   => __( 'Tuiles « Aujourd’hui 0 DT » et « À la réception »', 'famma-core' ),
				'summary' => __( 'Dans le récapitulatif, juste avant le bouton : ce qu’on paie aujourd’hui (0 DT) et ce qu’on paiera au livreur.', 'famma-core' ),
				'help'    => __( 'Dans le récapitulatif du panier, juste au-dessus de « Passer la commande » : une tuile verte « Aujourd’hui 0 DT, rien à payer en ligne » et une tuile marine avec le total à payer au livreur. Le total est celui de WooCommerce et se met à jour avec les quantités. Les tuiles n’apparaissent que si le paiement à la livraison est le seul moyen de paiement proposé : sinon « 0 DT aujourd’hui » serait faux.', 'famma-core' ),
				'texts'   => array( 'split_now', 'split_now_note', 'split_rec', 'split_rec_note' ),
			),
			'cart_dock'  => array(
				'title'   => __( 'Barre du bas « Aujourd’hui 0 DT · Commander »', 'famma-core' ),
				'summary' => __( 'Sur téléphone, une barre en bas d’écran : 0 DT aujourd’hui, le total au livreur, et le bouton Commander.', 'famma-core' ),
				'help'    => __( 'Sur téléphone et tablette, tant que le bouton « Passer la commande » n’est pas à l’écran, une barre marine reste en bas : « Aujourd’hui 0 DT », le total à la réception (mis à jour avec les quantités) et un bouton vers la page de commande. Elle s’efface quand le vrai bouton est visible, et n’existe pas sur ordinateur, où le récapitulatif reste déjà à l’écran. Si le paiement à la livraison n’est pas le seul moyen de paiement, elle montre seulement le total. Elle reprend les titres des tuiles de la case précédente.', 'famma-core' ),
				'texts'   => array( 'dock_go' ),
			),
			'cart_promo' => array(
				'title'   => __( 'Code promo replié', 'famma-core' ),
				'summary' => __( 'Le champ du code promo se cache derrière « Vous avez un code promo ? ».', 'famma-core' ),
				'help'    => __( 'Le cadre « Code promo » du panier se replie en un bouton « Vous avez un code promo ? » ; un toucher l’ouvre et place le curseur dans le champ. Le code s’applique comme avant. Sans effet si les codes promo sont désactivés dans WooCommerce. Si le navigateur n’exécute pas les scripts, le champ reste ouvert.', 'famma-core' ),
				'texts'   => array( 'promo_label' ),
			),
			'cart_save'  => array(
				'title'   => __( '« Mettre de côté » sur chaque article', 'famma-core' ),
				'summary' => __( 'Un bouton sous chaque article : il part dans « Ma sélection » et quitte le panier, avec « Annuler ».', 'famma-core' ),
				'help'    => __( 'Sous le nom de chaque article du panier, un bouton « Mettre de côté » : le produit est ajouté à « Ma sélection » (le cœur de la boutique), puis retiré du panier exactement comme avec la corbeille, avec le message « … retiré. Annuler ? » de WooCommerce, précédé de « Gardé dans « Ma sélection » ». La sélection reste dans le navigateur du client (et dans son compte si la case « Ma sélection gardée dans le compte » est cochée et qu’il est connecté). Le bouton n’apparaît que si le navigateur exécute les scripts.', 'famma-core' ),
				'texts'   => array( 'save_label', 'save_done' ),
			),
		);
	}
}
