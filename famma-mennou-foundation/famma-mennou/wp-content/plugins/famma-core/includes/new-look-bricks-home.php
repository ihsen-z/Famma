<?php
/**
 * Nouveau look — briques de la page d'accueil (onglet « Accueil »).
 *
 * Forme d'une entrée : voir `New_Look_Registry`. Les textes sont des clés de
 * `Home_Content::schema()`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Briques de l'onglet « Accueil ».
 */
final class New_Look_Bricks_Home {

	/**
	 * Briques, dans leur ordre d'affichage.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function bricks(): array {
		return array(
			'home_polish' => array(
				'title'   => __( 'Mise en page de la maquette', 'famma-core' ),
				'summary' => __( '« Commander » sous le nom sur téléphone, rayons en grille, nouveautés sur deux colonnes, cartes claires.', 'famma-core' ),
				'help'    => __( 'Sur la page d’accueil : bouton « Commander » juste sous le nom sur téléphone, rayons en grille, nouveautés sur deux colonnes, questions fréquentes avec chevrons, carte WhatsApp verte, cartes « Pourquoi FAMMA » et infolettre claire. Seuls l’ordre et le dessin changent : les textes restent ceux de l’éditeur.', 'famma-core' ),
			),
			'home_motion' => array(
				'title'   => __( 'Animations de la maquette', 'famma-core' ),
				'summary' => __( 'Barre d’avancement, logo qui cligne, carrousel animé, sections qui apparaissent au défilement.', 'famma-core' ),
				'help'    => __( 'Sur la page d’accueil : barre d’avancement en haut de l’écran, logo qui cligne de l’œil (et fait un clin d’œil à chaque ajout au panier), entrée animée du nom et du carrousel, bouton pause et minuteur du carrousel, sections qui apparaissent au défilement. Avec le parcours animé : un colis roule sur la route, la route passe à l’horizontale sur ordinateur, et les 24 gouvernorats (ceux de WooCommerce) défilent sous les étapes. Désactivé d’office si le visiteur a demandé moins d’animations. Aucun texte ne change.', 'famma-core' ),
			),
			'journey'     => array(
				'title'   => __( '« Vous payez à la livraison » en parcours animé', 'famma-core' ),
				'summary' => __( 'Une route relie les étapes, qui se cochent au défilement.', 'famma-core' ),
				'help'    => __( 'Le bloc déjà présent dans la page d’accueil devient une bande marine : une route relie les étapes, qui se cochent au défilement. Ses textes se modifient toujours dans l’éditeur de la page d’accueil.', 'famma-core' ),
			),
			'home_facts'  => array(
				'title'   => __( 'Chiffres « 24 gouvernorats · 0 DT · 0 compte »', 'famma-core' ),
				'summary' => __( 'Trois chiffres qui se comptent à l’arrivée, sous les étapes du paiement à la livraison.', 'famma-core' ),
				'help'    => __( 'Sous les étapes du bloc « Vous payez à la livraison », trois chiffres en grand qui se comptent à l’arrivée. Les chiffres et leurs légendes se modifient juste en dessous ; un chiffre vide disparaît.', 'famma-core' ),
				'texts'   => array( 'facts_1_value', 'facts_1_label', 'facts_2_value', 'facts_2_label', 'facts_3_value', 'facts_3_label' ),
			),
			'home_why'    => array(
				'title'   => __( '« Pourquoi FAMMA » après le paiement à la livraison', 'famma-core' ),
				'summary' => __( 'Les cartes remontent juste sous le bloc du paiement à la livraison, comme dans la maquette.', 'famma-core' ),
				'help'    => __( 'Les cartes « Pourquoi choisir FAMMA ? » remontent juste sous le bloc « Vous payez à la livraison », comme dans la maquette, au lieu d’être après les questions fréquentes. Sans ce bloc dans la page d’accueil, elles restent à leur place.', 'famma-core' ),
			),
			'hide_band'   => array(
				'title'   => __( 'Retirer « Une question sur un produit ? »', 'famma-core' ),
				'summary' => __( 'Le bloc WhatsApp du bas de l’accueil disparaît ; le bouton vert flottant reste.', 'famma-core' ),
				'help'    => __( 'Le bloc WhatsApp du bas de l’accueil disparaît : la carte WhatsApp des questions fréquentes et le bouton vert flottant restent. Il reste affiché dans la boutique.', 'famma-core' ),
			),
			'cat_icons'   => array(
				'title'   => __( 'Icônes des rayons', 'famma-core' ),
				'summary' => __( 'Un rayon sans miniature montre son icône au trait (Produits → Catégories).', 'famma-core' ),
				'help'    => __( 'Un rayon sans miniature montre l’icône au trait choisie dans Produits → Catégories (champ « Icône sur l’accueil »), dessinée à l’arrivée, au lieu de l’étoile orange. Une miniature envoyée reste toujours prioritaire.', 'famma-core' ),
			),
			'wa_ring'     => array(
				'title'   => __( 'Anneau sur le bouton WhatsApp', 'famma-core' ),
				'summary' => __( 'Trois ondes à l’ouverture de l’accueil, puis le bouton s’arrête.', 'famma-core' ),
				'help'    => __( 'Le bouton vert flottant émet trois ondes à l’ouverture de l’accueil, puis s’arrête. Désactivé d’office si le visiteur a demandé moins d’animations.', 'famma-core' ),
			),
		);
	}
}
