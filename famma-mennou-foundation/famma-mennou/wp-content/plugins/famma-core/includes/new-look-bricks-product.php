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
			// Lot 46 (14/11/2026).
			'pdp_story'   => array(
				'title'   => __( 'Récit en bande marine', 'famma-core' ),
				'summary' => __( 'Le récit du produit en grand, mots qui s’allument au défilement, et des boutons de situation à toucher.', 'famma-core' ),
				'help'    => __( 'Sur les fiches dont le récit est rempli (Produits → Modifier → onglet « Fiche FAMMA ») : une bande marine sous la boîte d’achat, le récit en grand dont les mots s’allument au défilement (la phrase entre deux * passe en orange), puis les boutons de situation (écran, téléphone, voiture…) ; toucher un bouton écrit sa phrase en dessous. Un produit sans récit n’a pas de bande. Désactivé d’office si le visiteur a demandé moins d’animations (texte affiché d’un coup).', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array( 'story_chips' ),
				'wait'    => array(),
			),
			'pdp_anatomy' => array(
				'title'   => __( '« Ce qu’il y a dedans » avec repères', 'famma-core' ),
				'summary' => __( 'Une photo à repères numérotés qui reste en place et zoome sur la pièce dont parle la liste.', 'famma-core' ),
				'help'    => __( 'Sur les fiches dont la photo, les repères et les pièces sont remplis (onglet « Fiche FAMMA », au moins deux pièces) : la photo reste en place pendant qu’on fait défiler la liste des pièces, et zoome sur le repère de la pièce lue. Un produit non rempli n’a pas de bloc. Sans zoom si le visiteur a demandé moins d’animations. Titre juste en dessous.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array( 'ana_title' ),
				'wait'    => array( 'ana_title' ),
			),
			// Lot 47 (14/11/2026).
			'pdp_buy'     => array(
				'title'   => __( 'Boîte d’achat « taille d’abord »', 'famma-core' ),
				'summary' => __( 'Taille, quantité et total d’abord ; « Commander » déplie ensuite le formulaire. Barre d’avancement au-dessus.', 'famma-core' ),
				'help'    => __( 'Décision n° 8, à prendre avec le bilan du formulaire express du 18/11. Sur toutes les fiches à formulaire express : la taille, la quantité et le total viennent d’abord, avec une barre d’avancement (25 % produit choisi, 50 % taille, puis chaque champ) ; le bouton « Commander » déplie ensuite téléphone, nom et adresse. Taille oubliée : les tailles tremblent et un message s’affiche. Le bouton reçoit un seul reflet après 10 s sans action. Produit sans taille : la barre part de 50 %. Sans script, le formulaire reste ouvert comme aujourd’hui. Textes juste en dessous.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array( 'buy_pg0', 'buy_pg1', 'buy_pg2', 'buy_pg3', 'buy_chosen', 'buy_after', 'buy_size_err' ),
				'wait'    => array(),
			),
			'pdp_form'    => array(
				'title'   => __( 'Formulaire : coches vertes, sans bouton panier', 'famma-core' ),
				'summary' => __( 'Une coche verte sur chaque champ bien rempli, le total qui rebondit, et plus de bouton panier à côté de « Confirmer ».', 'famma-core' ),
				'help'    => __( 'Décisions n° 9 et 10. Sur toutes les fiches à formulaire express : une coche verte apparaît à côté de chaque champ bien rempli (téléphone à 8 chiffres, nom, gouvernorat), le total rebondit quand la quantité change, et le bouton « Ajouter au panier » qui doublait « Confirmer ma commande » disparaît (le cœur des favoris reste). Les contrôles d’envoi ne changent pas.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array(),
				'wait'    => array(),
			),
			'pdp_points'  => array(
				'title'   => __( 'Points forts sous la boîte d’achat', 'famma-core' ),
				'summary' => __( 'La description courte quitte le « Lire la suite » et s’affiche en entier sous le formulaire, en puces orange.', 'famma-core' ),
				'help'    => __( 'Décision n° 7 (à montrer après le 18/11). Sur toutes les fiches : la description courte du produit n’est plus repliée au-dessus du formulaire ; elle s’affiche en entier juste sous la boîte d’achat, ses listes en puces orange. Rien n’est réécrit : c’est la description courte de chaque produit.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array(),
				'wait'    => array(),
			),
			'pdp_dock'    => array(
				'title'   => __( 'Barre du bas de la maquette', 'famma-core' ),
				'summary' => __( 'Photo, taille ou nom, total, WhatsApp et « Choisir ma taille » puis « Commander », seulement quand la boîte d’achat est hors de l’écran.', 'famma-core' ),
				'help'    => __( 'Décision n° 18. Sur téléphone, la barre du bas de la fiche montre la photo, la taille choisie (ou le nom), le total, WhatsApp et un bouton « Choisir ma taille » tant qu’aucune taille n’est choisie, puis « Commander ». Elle n’apparaît que quand la boîte d’achat est hors de l’écran ; le bouton WhatsApp flottant revient quand elle s’efface. Texte juste en dessous.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array( 'buy_pick' ),
				'wait'    => array(),
			),
			'pdp_altsize' => array(
				'title'   => __( 'Lien « autre taille » sous les tailles', 'famma-core' ),
				'summary' => __( 'Sous les tailles, un lien vers le produit à proposer quand la taille ne convient pas, saisi produit par produit.', 'famma-core' ),
				'help'    => __( 'Lot 49. Exemple de la maquette : « Vous portez S ou M ? Voir la Ceinture dorsale Pro ». Le produit proposé et la phrase se saisissent dans Produits → Modifier → onglet « Fiche FAMMA », bloc « Autre taille ». Un produit sans lien n’affiche rien.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array(),
				'wait'    => array(),
			),
			'pdp_labels'  => array(
				'title'   => __( 'Formulaire dans la langue de la page', 'famma-core' ),
				'summary' => __( 'Les intitulés du formulaire express dans une seule langue, celle de la page, et sans astérisques.', 'famma-core' ),
				'help'    => __( 'Décision n° 9. « Téléphone / الهاتف » devient « Téléphone » sur la page française et « الهاتف » sur la page arabe ; même chose pour la taille, la quantité, l’adresse et le total. Les astérisques disparaissent (tous les champs restent obligatoires). Sur la page française, la ligne en derja des boutons « Confirmer » et de la barre du bas est masquée. À cocher seulement après le bilan du formulaire express (lot 23).', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array(),
				'wait'    => array(),
			),
			// Lot 50 (14/11/2026).
			'pdp_sizes'   => array(
				'title'   => __( 'Volet « Les tailles »', 'famma-core' ),
				'summary' => __( 'Un volet avec les tailles disponibles, le lien « autre taille » et le tableau des mesures du produit.', 'famma-core' ),
				'help'    => __( 'Lot 50. Sur les fiches à tailles ou à tableau des mesures : un volet « Les tailles » (un onglet si les volets ne sont pas cochés) avec les tailles disponibles, le lien « autre taille » si sa brique est cochée, et le tableau des mesures saisi dans Produits → Modifier → onglet « Fiche FAMMA », bloc « Tableau des mesures ». Un produit sans taille ni tableau n’a pas de volet. Textes juste en dessous.', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array( 'size_tab', 'size_avail' ),
				'wait'    => array(),
			),
			// Lot 51 (14/11/2026).
			'pdp_thanks'  => array(
				'title'   => __( 'Page merci : plan en 3 étapes', 'famma-core' ),
				'summary' => __( 'Après une commande payée à la livraison : « Payé aujourd’hui 0 DT » et trois étapes numérotées qui montent l’une après l’autre.', 'famma-core' ),
				'help'    => __( 'Lot 51. Sur la page merci d’une commande payée à la livraison, le bloc « Et maintenant ? » prend la forme de la fenêtre de la maquette : « Payé aujourd’hui 0 DT », puis trois étapes numérotées, dans la langue de la page seulement. Le numéro à appeler, la correction par WhatsApp et l’offre après achat restent. Textes juste en dessous, à faire correspondre à votre façon de travailler (aucun délai n’est annoncé d’office).', 'famma-core' ),
				'needs'   => array(),
				'texts'   => array( 'thx_title', 'thx_today', 'thx_step1', 'thx_step2', 'thx_step3' ),
				'wait'    => array(),
			),
		);
	}
}
