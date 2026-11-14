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
			'ledger'      => array(
				'title'   => __( 'Bande « Livraison 0 DT · Avant réception 0 DT »', 'famma-core' ),
				'summary' => __( 'Sous le prix : prix, livraison 0 DT, 0 DT avant de recevoir, montant au livreur.', 'famma-core' ),
				'help'    => __( 'Sous le prix, un petit tableau : prix du produit, livraison 0 DT, 0 DT avant de recevoir, montant à payer au livreur. Les zéros ne s’affichent que s’ils sont vrais (livraison gratuite et paiement à la livraison activés dans WooCommerce). Textes juste en dessous.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array( 'ledger_price', 'ledger_delivery', 'ledger_before', 'ledger_total' ),
				'wait'    => array(),
			),
			// Lot 44 (14/11/2026).
			'pdp_gallery' => array(
				'title'   => __( 'Galerie de la maquette', 'famma-core' ),
				'summary' => __( 'Photo dans un cadre arrondi qui s’ouvre à l’arrivée, compteur « 2 / 5 » et cadre orange qui glisse d’une vignette à l’autre.', 'famma-core' ),
				'help'    => __( 'Sur toutes les fiches : photo dans un cadre arrondi qui s’ouvre à l’arrivée, compteur « 2 / 5 » sur la photo, cadre orange qui glisse d’une vignette à l’autre, et sur ordinateur des flèches posées sur la photo et un zoom qui suit la souris. Les infos à droite montent l’une après l’autre. Un produit à une seule photo n’a ni compteur ni vignettes. La loupe plein écran de WooCommerce reste. Désactivé d’office si le visiteur a demandé moins d’animations.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array(),
				'wait'    => array(),
			),
			'pdp_frame'   => array(
				'title'   => __( 'Cadre de la maquette', 'famma-core' ),
				'summary' => __( 'Barre de lecture orange, logo qui cligne, titres entre deux traits orange et trois produits similaires.', 'famma-core' ),
				'help'    => __( 'Sur toutes les fiches : barre orange d’avancement de la lecture en haut de l’écran, logo qui cligne de l’œil, fil d’Ariane sans le nom du produit sur téléphone, titres de section entre deux traits orange, carte WhatsApp verte sans flèche, et trois produits similaires (le premier en grand sur téléphone, trois colonnes sur ordinateur) qui apparaissent au défilement. Aucun texte ne change.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array(),
				'wait'    => array(),
			),
			// Lot 45 (14/11/2026).
			'pdp_pay'     => array(
				'title'   => __( '« Quand payez-vous ? » et zéros animés', 'famma-core' ),
				'summary' => __( 'Sous le formulaire, une bande marine : le compteur « Déjà payé » reste à 0 DT jusqu’au livreur, puis passe au prix.', 'famma-core' ),
				'help'    => __( 'Sur toutes les fiches, sous le formulaire : une bande marine avec un compteur « Déjà payé 0 DT », un colis qui roule sur une route, les étapes « Comment commander » (textes de l’onglet Boutique) qui se cochent au défilement, et le compteur qui passe au prix du produit à l’arrivée chez le livreur, puis un bouton « Commander » qui ramène au formulaire. Elle remplace le bandeau des quatre bénéfices, qui revient si la bande ne peut pas s’afficher (rupture, paiement à la livraison coupé). Avec la bande « 0 DT » affichée : ses deux zéros s’impriment à l’arrivée, et l’encadré de réassurance ne garde que le délai de livraison. Désactivé d’office si le visiteur a demandé moins d’animations (tout est alors affiché d’un coup). Textes juste en dessous.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array( 'pay_title', 'pay_meter', 'pay_paid' ),
				'wait'    => array( 'pay_title' ),
			),
			'pdp_details' => array(
				'title'   => __( '« Tout savoir avant de commander » en volets', 'famma-core' ),
				'summary' => __( 'Les onglets deviennent des volets : la description découpée à ses intertitres, une question de FAQ par volet, puis la livraison.', 'famma-core' ),
				'help'    => __( 'Sur toutes les fiches, les onglets Description, FAQ et Livraison & Retours deviennent des volets qui s’ouvrent l’un sous l’autre, comme la maquette. La description est découpée à chacun de ses intertitres (niveau 3) : un volet par intertitre, le début de la description reste visible au-dessus. Puis une question de la FAQ par volet, puis « Livraison & Retours ». Rien n’est réécrit : c’est le contenu de chaque produit. Titre juste en dessous.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array( 'det_title' ),
				'wait'    => array(),
			),
		);
	}
}
