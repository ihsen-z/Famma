<?php
/**
 * Nouveau look — briques valables sur tout le site (onglet « Partout »).
 *
 * Forme d'une entrée : voir `New_Look_Registry`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Briques de l'onglet « Partout ».
 */
final class New_Look_Bricks_Site {

	/**
	 * Briques, dans leur ordre d'affichage.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function bricks(): array {
		return array(
			'navy'        => array(
				'title'   => __( 'Texte marine sur les boutons orange', 'famma-core' ),
				'summary' => __( 'Boutons lisibles au soleil : contraste 6,3:1 au lieu de 2,6:1.', 'famma-core' ),
				'help'    => __( 'Contraste 6,3:1 au lieu de 2,6:1 pour le blanc actuel : les boutons deviennent lisibles au soleil et conformes à l’accessibilité.', 'famma-core' ),
			),
			'site_polish' => array(
				'title'   => __( 'Police Poppins, bandeau qui défile, pied de page', 'famma-core' ),
				'summary' => __( 'Police de la charte partout, promesses du haut qui défilent sur téléphone, pied de page en colonnes.', 'famma-core' ),
				'help'    => __( 'Tout le texte passe dans la police de la charte (Poppins, déjà téléchargée par chaque page sans servir). Sur téléphone, les promesses du bandeau du haut défilent au lieu d’en montrer une seule. Le pied de page se range en colonnes, avec l’encadré « Paiement à la livraison » sous le logo. Aucun texte ne change : les promesses du bandeau se modifient dans FAMMA → Pages.', 'famma-core' ),
			),
			'motion'      => array(
				'title'   => __( 'Apparitions et vol vers le panier', 'famma-core' ),
				'summary' => __( 'Les cartes apparaissent en douceur, la photo vole vers le panier, le cœur pétille.', 'famma-core' ),
				'help'    => __( 'Les cartes apparaissent en douceur, la photo vole vers le panier à l’ajout, le cœur des favoris pétille. Désactivé d’office si le visiteur a demandé moins d’animations.', 'famma-core' ),
			),
			'alt_photo'   => array(
				'title'   => __( 'Seconde photo au survol', 'famma-core' ),
				'summary' => __( 'Sur ordinateur, la deuxième photo remplace la première au survol.', 'famma-core' ),
				'help'    => __( 'Sur ordinateur, la deuxième photo de la galerie remplace la première au survol. Elle n’est téléchargée qu’au premier survol.', 'famma-core' ),
			),
			'transitions' => array(
				'title'   => __( 'Transitions entre les pages', 'famma-core' ),
				'summary' => __( 'Fondu entre les pages de la boutique, l’en-tête reste en place.', 'famma-core' ),
				'help'    => __( 'Fondu entre les pages de la boutique (filtres, tri, catégories), l’en-tête reste en place. Ignoré par les navigateurs qui ne le gèrent pas.', 'famma-core' ),
			),
			'acct_mobile' => array(
				'title'   => __( 'Icône « Mon compte » sur téléphone', 'famma-core' ),
				'summary' => __( 'L’icône du compte s’affiche à côté du panier au lieu d’être rangée dans le menu.', 'famma-core' ),
				'help'    => __( 'Sur téléphone, l’icône du compte s’affiche à côté du panier, comme sur ordinateur, au lieu d’être rangée dans le menu.', 'famma-core' ),
			),
		);
	}
}
