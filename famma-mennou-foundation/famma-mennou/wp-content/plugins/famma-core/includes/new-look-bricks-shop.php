<?php
/**
 * Nouveau look — briques de la boutique et des rayons (onglet « Boutique »).
 *
 * Forme d'une entrée : voir `New_Look_Registry`. Les textes sont des clés de
 * `Shop_Content::schema()`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Briques de l'onglet « Boutique ».
 */
final class New_Look_Bricks_Shop {

	/**
	 * Briques, dans leur ordre d'affichage.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function bricks(): array {
		return array(
			'tools' => array(
				'title'   => __( 'Rayons et budget en puces sur téléphone', 'famma-core' ),
				'summary' => __( 'Une rangée de puces sous l’en-tête : les rayons, puis des budgets tirés des vrais prix.', 'famma-core' ),
				'help'    => __( 'Sur téléphone, une rangée de puces reste collée sous l’en-tête : les rayons avec leur nombre de produits, puis des budgets tirés des vrais prix du catalogue. Ce sont les mêmes filtres que la colonne « Filtres », en un toucher.', 'famma-core' ),
			),
			'how'   => array(
				'title'   => __( 'Bloc « Comment commander »', 'famma-core' ),
				'summary' => __( 'Trois étapes sur une bande marine en bas de la boutique et des rayons.', 'famma-core' ),
				'help'    => __( 'En bas de la boutique et des rayons, trois étapes sur une bande marine : commander, l’appel de confirmation, le paiement au livreur. Les textes se modifient juste en dessous ; un titre vide masque le bloc, une étape vide disparaît.', 'famma-core' ),
				'texts'   => Shop_Content::class,
			),
			'dock'  => array(
				'title'   => __( 'Barre du panier « Aujourd’hui 0 DT »', 'famma-core' ),
				'summary' => __( 'En bas d’écran dès qu’il y a un article : rien à payer aujourd’hui, total au livreur.', 'famma-core' ),
				'help'    => __( 'Dans la boutique, une barre en bas d’écran rappelle qu’on ne paie rien aujourd’hui et donne le total à payer au livreur. Elle n’apparaît que si le panier contient un article.', 'famma-core' ),
			),
		);
	}
}
