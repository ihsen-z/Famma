<?php
/**
 * Nouveau look — briques de l'espace client (onglet « Mon compte »).
 *
 * Forme d'une entrée : voir `New_Look_Registry`. Chaque brique reçoit tout le
 * schéma de la classe de textes qu'elle affiche (option `famma_page_account`).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Briques de l'onglet « Mon compte ».
 */
final class New_Look_Bricks_Account {

	/**
	 * Briques, dans leur ordre d'affichage.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function bricks(): array {
		$look  = array( 'acct_look' );
		$forms = array( 'acct_look', 'acct_forms' );

		return array(
			'acct_look'    => array(
				'title'   => __( 'Présentation de la maquette', 'famma-core' ),
				'summary' => __( 'Connexion en deux onglets, carte marine avec l’anneau « profil complété », compteurs, onglets Commandes, Adresses, Informations.', 'famma-core' ),
				'help'    => __( 'Sur la page « Mon compte » : connexion et création de compte en deux onglets, champs et boutons de la charte ; une fois connecté, fil d’Ariane, carte marine avec les initiales, le nom, le téléphone, l’ancienneté et l’anneau « profil complété » (50 % le compte, +30 % une adresse, +20 % un e-mail), compteurs (commandes, en route, favoris de ce navigateur), puis trois onglets : Mes commandes, Mes adresses, Mes informations, avec un soulignement qui glisse. Le compte s’ouvre sur les commandes ; « Tableau de bord » et « Téléchargements » quittent les onglets et « Me déconnecter » passe dans « Mes informations ». Les champs, les e-mails et le fonctionnement de WooCommerce ne changent pas. Les animations s’arrêtent si le visiteur a demandé moins d’animations.', 'famma-core' ),
				'texts'   => array( Account_Profile::class, Account_Auth::class ),
			),
			'acct_track'   => array(
				'title'   => __( 'Suivi en 4 étapes et « Recommander »', 'famma-core' ),
				'summary' => __( 'Chaque commande devient une carte avec la frise Reçue → Confirmée → Expédiée → Livrée.', 'famma-core' ),
				'help'    => __( 'Dans « Mon compte » : chaque commande devient une carte comme dans la maquette (photos, « En route » ou « Livrée », total, frise Reçue → Confirmée → Expédiée → Livrée avec l’heure de chaque étape, rappel pour le livreur, détail dépliable avec les articles, le récapitulatif et le lieu) ; le détail d’une commande montre la même carte, ouverte. Une commande refusée, retournée ou annulée affiche son statut à la place de la frise. Boutons « Recommander » (commandes livrées, aux prix du jour) et « Une question » (WhatsApp).', 'famma-core' ),
				'texts'   => array( Order_Track::class ),
			),
			'acct_forms'   => array(
				'title'   => __( 'Adresses et informations de la maquette', 'famma-core' ),
				'summary' => __( 'Adresse dans une carte, feuille qui glisse pour la modifier, « Mes informations » en deux boîtes.', 'famma-core' ),
				'help'    => __( '« Mes adresses » montre l’adresse du client dans une carte (ou une carte « Ajouter une adresse ») ; « Modifier » ouvre une feuille qui glisse du bas sur téléphone, du côté sur ordinateur, avec le formulaire d’adresse de WooCommerce. « Mes informations » range le formulaire en deux boîtes, « Identité » et « Connexion » (mot de passe à déplier, puis « Me déconnecter »). Une seule adresse, celle de la commande : l’adresse de livraison séparée de WooCommerce n’est plus montrée dans la liste. Champs, contrôles et enregistrement inchangés.', 'famma-core' ),
				'needs'   => $look,
				'texts'   => array( Account_Panels::class ),
			),
			'acct_favs'    => array(
				'title'   => __( '« Ma sélection » gardée dans le compte', 'famma-core' ),
				'summary' => __( 'Les favoris des clients connectés suivent le compte d’un téléphone à l’autre.', 'famma-core' ),
				'help'    => __( 'Pour les clients connectés : les produits mis de côté avec le cœur sont aussi gardés dans leur compte, et se retrouvent sur un autre téléphone après connexion. Nouvel onglet « Ma sélection » dans « Mon compte » : une carte par produit, avec son prix, « Ajouter » et le cœur pour le retirer. Les visiteurs ne changent pas : leur sélection reste dans leur navigateur.', 'famma-core' ),
				'needs'   => $look,
				'texts'   => array( Account_Favs::class ),
			),
			'acct_phone'   => array(
				'title'   => __( 'Inscription et connexion par numéro', 'famma-core' ),
				'summary' => __( 'Téléphone, nom, mot de passe ; e-mail facultatif. La connexion accepte le numéro.', 'famma-core' ),
				'help'    => __( 'La création de compte demande le téléphone, le nom et un mot de passe, comme la maquette ; l’e-mail devient facultatif et l’adresse peut être ajoutée tout de suite (gouvernorat, ville). Un numéro ne peut avoir qu’un compte. La connexion accepte le numéro (avec ou sans +216, avec espaces) ou l’e-mail. Un compte sans e-mail ne reçoit aucun e-mail. Mot de passe oublié : par WhatsApp, à la main, pour le moment. Une fois décochée, les comptes déjà créés par numéro peuvent toujours se connecter.', 'famma-core' ),
				'needs'   => $look,
				'texts'   => array( Account_Phone_Texts::class ),
			),
			'acct_addrs'   => array(
				'title'   => __( 'Plusieurs adresses (Maison, Travail, Famille)', 'famma-core' ),
				'summary' => __( 'Une carte par adresse, « Utiliser par défaut », « Supprimer » avec « Annuler ».', 'famma-core' ),
				'help'    => __( '« Mes adresses » montre une carte par adresse, avec son étiquette (Maison, Travail, Famille), « Modifier », « Utiliser par défaut » et « Supprimer » (avec « Annuler »), et une carte « Ajouter une adresse ». La feuille de la maquette demande l’étiquette, le gouvernorat, « Ville, cité, rue » et « Utiliser par défaut pour mes commandes ». L’adresse par défaut est celle que la commande préremplit, comme avant. Le téléphone se modifie dans « Mes informations ». Dix adresses au plus.', 'famma-core' ),
				'needs'   => $forms,
				'texts'   => array( Account_Addresses_Texts::class ),
			),
			'acct_privacy' => array(
				'title'   => __( 'Mot de passe oublié et suppression du compte', 'famma-core' ),
				'summary' => __( '« Mot de passe oublié ? » par WhatsApp ; boîte « Supprimer mon compte » dans « Mes informations ».', 'famma-core' ),
				'help'    => __( 'Sous « Me connecter », « Mot de passe oublié ? » se déplie comme dans la maquette et propose d’écrire sur WhatsApp depuis le numéro du compte (message préparé, numéro tapé ajouté), puis le lien e-mail de WooCommerce. Vous changez le mot de passe à la main : Comptes → rechercher le numéro → Modifier. Avec la case « Adresses et informations de la maquette », « Mes informations » se termine par la boîte « Supprimer mon compte », avec confirmation dans la page ; elle reste cachée tant que son explication (textes ci-dessous) est vide. « Oui, supprimer » crée une demande dans Outils → Effacer les données personnelles, sans e-mail : rien n’est effacé avant votre validation, et le client peut l’annuler en attendant. Les commandes suivent les réglages de confidentialité de WooCommerce.', 'famma-core' ),
				'needs'   => $look,
				'texts'   => array( Account_Privacy_Texts::class ),
				'wait'    => array( 'del_s' ),
			),
			'acct_prefs'   => array(
				'title'   => __( 'Préférences : langue, nouveautés WhatsApp', 'famma-core' ),
				'summary' => __( 'Langue des messages FR / عربي ; inscription aux nouveautés WhatsApp, refusée par défaut.', 'famma-core' ),
				'help'    => __( '« Mes informations » montre la boîte « Préférences » de la maquette, en tête de la colonne de droite. « Langue de mes messages » (FR / عربي) note la langue dans laquelle appeler et écrire au client, rappelée dans chaque commande sous l’adresse de facturation, et bascule la page dans cette langue. « Nouveautés sur WhatsApp » est refusé par défaut et n’apparaît qu’une fois son texte de consentement écrit (textes ci-dessous) ; la date et le texte accepté sont gardés sur le compte. Le site n’envoie aucun message : Comptes → « Nouveautés WhatsApp » liste les inscrits, avec une colonne « Préférences ».', 'famma-core' ),
				'needs'   => $forms,
				'texts'   => array( Account_Prefs_Texts::class ),
				'wait'    => array( 'pf_wa_s' ),
			),
			'acct_welcome' => array(
				'title'   => __( 'Écran « Bienvenue » après l’inscription', 'famma-core' ),
				'summary' => __( 'La mascotte cligne, « Bienvenue, {prénom} ! », puis le compte apparaît. Une seule fois par compte.', 'famma-core' ),
				'help'    => __( 'Juste après la création d’un compte depuis « Mon compte » (pas depuis la commande), la première page du compte montre l’écran de la maquette : la mascotte cligne avec des étincelles, « Bienvenue, {prénom} ! » et « Votre compte est prêt », puis le compte apparaît avec ses animations d’arrivée (2,3 secondes). Une seule fois par compte. Sans script, l’écran reste en haut du compte. La ligne « commandes rattachées » de la maquette attend le rattachement par code (lot 4).', 'famma-core' ),
				'needs'   => $look,
				'texts'   => array( Account_Welcome::class ),
			),
			'acct_finish'  => array(
				'title'   => __( 'Finitions de la maquette', 'famma-core' ),
				'summary' => __( 'Un seul « Nom et prénom », écran vide des commandes, pastille avec l’initiale, message de déconnexion.', 'famma-core' ),
				'help'    => __( '« Mes informations » demande un seul « Nom et prénom » (au lieu de Prénom, Nom et Nom affiché), l’e-mail sans astérisque avec « (facultatif) » et son aide pour un compte créé par numéro ; « Mes commandes » sans commande montre la mascotte et un bouton vers la boutique ; « Mot de passe » sans astérisque à l’inscription et à la connexion ; « Vous êtes déconnecté. » après « Me déconnecter » ; l’initiale du client en pastille orange sur l’icône compte ; sur téléphone, le bas du compte laisse la place du bouton WhatsApp.', 'famma-core' ),
				'needs'   => $look,
				'texts'   => array( Account_Finish::class ),
			),
		);
	}
}
