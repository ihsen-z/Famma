<?php
/**
 * Nouveau look animé — réglé depuis FAMMA → Pages.
 *
 * Interrupteur unique de la couche visuelle issue des maquettes animées du
 * 04-06/11/2026 (texte marine sur orange, apparitions, vol vers le panier,
 * seconde photo au survol, transitions de page, barre du panier). Le thème ne
 * charge rien tant que le mode reste sur « Désactivé » : le site est alors
 * identique à la version précédente, octet pour octet côté visiteur.
 *
 * Trois modes, pour un déploiement sans risque :
 * - « Désactivé » (défaut) : rien n'est chargé ;
 * - « Administrateurs seulement » : le propriétaire voit le nouveau look sur
 *   le vrai site, les visiteurs non. Les pages des connectés ne sont jamais
 *   servies depuis le cache (Tracking::internal_cache_guard), le cache public
 *   reste donc celui de l'ancien look ;
 * - « Tout le monde » : bascule publique, réversible en un clic.
 *
 * Ce fichier ne porte que l'option et son écran : le dessin vit dans le thème
 * (`inc/new-look.php`), qui ne fait rien si le plugin est absent.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Option « nouveau look animé ».
 */
final class New_Look {

	/**
	 * Option abritant le mode et les briques retenues.
	 */
	public const OPTION = 'famma_new_look';

	/**
	 * Mode servi tant que le propriétaire n'a rien choisi : rien ne change.
	 */
	public const DEFAULT_MODE = 'off';

	/**
	 * Capacité qui donne accès à l'aperçu « administrateurs seulement ».
	 */
	public const PREVIEW_CAP = 'manage_woocommerce';

	/**
	 * Instance unique.
	 *
	 * @var New_Look|null
	 */
	private static ?New_Look $i = null;

	/**
	 * Valeurs déjà résolues pour la requête en cours.
	 *
	 * @var array{mode: string, parts: array<string, bool>}|null
	 */
	private static ?array $cache = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return New_Look
	 */
	public static function instance(): New_Look {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks d'administration.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'flush_page_cache' ) );
		add_action( 'add_option_' . self::OPTION, array( $this, 'flush_page_cache' ) );
	}

	/**
	 * Modes proposés, dans leur ordre d'affichage.
	 *
	 * @return array<string, array{label: string, help: string}>
	 */
	public static function modes(): array {
		return array(
			'off'    => array(
				'label' => __( 'Désactivé', 'famma-core' ),
				'help'  => __( 'Le site garde son look actuel. Aucun fichier du nouveau look n’est envoyé aux visiteurs.', 'famma-core' ),
			),
			'admins' => array(
				'label' => __( 'Administrateurs seulement (aperçu)', 'famma-core' ),
				'help'  => __( 'Vous voyez le nouveau look sur le vrai site quand vous êtes connecté ; les visiteurs voient l’ancien. Attention : le pixel Meta est coupé pour les comptes internes, les événements se vérifient donc dans une fenêtre privée, après bascule.', 'famma-core' ),
			),
			'all'    => array(
				'label' => __( 'Tout le monde', 'famma-core' ),
				'help'  => __( 'Le nouveau look est public. Revenir à « Désactivé » rétablit l’ancien look et vide le cache.', 'famma-core' ),
			),
		);
	}

	/**
	 * Briques du nouveau look, chacune désactivable seule.
	 *
	 * @return array<string, array{label: string, help: string}>
	 */
	public static function parts(): array {
		return array(
			'navy'         => array(
				'label' => __( 'Texte marine sur les boutons orange', 'famma-core' ),
				'help'  => __( 'Contraste 6,3:1 au lieu de 2,6:1 pour le blanc actuel : les boutons deviennent lisibles au soleil et conformes à l’accessibilité.', 'famma-core' ),
			),
			'site_polish'  => array(
				'label' => __( 'Partout : police Poppins, bandeau qui défile, pied de page de la maquette', 'famma-core' ),
				'help'  => __( 'Tout le texte passe dans la police de la charte (Poppins, déjà téléchargée par chaque page sans servir). Sur téléphone, les promesses du bandeau du haut défilent au lieu d’en montrer une seule. Le pied de page se range en colonnes, avec l’encadré « Paiement à la livraison » sous le logo. Aucun texte ne change.', 'famma-core' ),
			),
			'motion'       => array(
				'label' => __( 'Apparitions et vol vers le panier', 'famma-core' ),
				'help'  => __( 'Les cartes apparaissent en douceur, la photo vole vers le panier à l’ajout, le cœur des favoris pétille. Désactivé d’office si le visiteur a demandé moins d’animations.', 'famma-core' ),
			),
			'alt_photo'    => array(
				'label' => __( 'Seconde photo au survol', 'famma-core' ),
				'help'  => __( 'Sur ordinateur, la deuxième photo de la galerie remplace la première au survol. Elle n’est téléchargée qu’au premier survol.', 'famma-core' ),
			),
			'transitions'  => array(
				'label' => __( 'Transitions entre les pages', 'famma-core' ),
				'help'  => __( 'Fondu entre les pages de la boutique (filtres, tri, catégories), l’en-tête reste en place. Ignoré par les navigateurs qui ne le gèrent pas.', 'famma-core' ),
			),
			'tools'        => array(
				'label' => __( 'Boutique : rayons et budget en puces sur mobile', 'famma-core' ),
				'help'  => __( 'Sur téléphone, une rangée de puces reste collée sous l’en-tête : les rayons avec leur nombre de produits, puis des budgets tirés des vrais prix du catalogue. Ce sont les mêmes filtres que la colonne « Filtres », en un toucher.', 'famma-core' ),
			),
			'how'          => array(
				'label' => __( 'Boutique : bloc « Comment commander »', 'famma-core' ),
				'help'  => __( 'En bas de la boutique et des rayons, trois étapes sur une bande marine : commander, l’appel de confirmation, le paiement au livreur. Les textes se modifient plus bas, dans « Boutique — bloc Comment commander ».', 'famma-core' ),
			),
			'ledger'       => array(
				'label' => __( 'Fiche produit : bande « Livraison 0 DT · Avant réception 0 DT »', 'famma-core' ),
				'help'  => __( 'Sous le prix, un petit tableau : prix du produit, livraison 0 DT, 0 DT avant de recevoir, montant à payer au livreur. Les zéros ne s’affichent que s’ils sont vrais (livraison gratuite et paiement à la livraison activés dans WooCommerce). Textes dans « Fiche produit ».', 'famma-core' ),
			),

			/*
			 * Fiche produit, lots 44 et 45 (14/11/2026). Entrées provisoires : le
			 * registre par page (`New_Look_Bricks_Product`, session « admin
			 * nouveau look », lot 48) les reprendra avec summary = la première
			 * phrase de l'aide, needs, texts et wait vides.
			 */
			'pdp_gallery'  => array(
				'label' => __( 'Fiche produit : galerie de la maquette', 'famma-core' ),
				'help'  => __( 'Sur toutes les fiches : photo dans un cadre arrondi qui s’ouvre à l’arrivée, compteur « 2 / 5 » sur la photo, cadre orange qui glisse d’une vignette à l’autre, et sur ordinateur des flèches posées sur la photo et un zoom qui suit la souris. Les infos à droite montent l’une après l’autre. Un produit à une seule photo n’a ni compteur ni vignettes. La loupe plein écran de WooCommerce reste. Désactivé d’office si le visiteur a demandé moins d’animations.', 'famma-core' ),
			),
			'pdp_frame'    => array(
				'label' => __( 'Fiche produit : cadre de la maquette', 'famma-core' ),
				'help'  => __( 'Sur toutes les fiches : barre orange d’avancement de la lecture en haut de l’écran, logo qui cligne de l’œil, fil d’Ariane sans le nom du produit sur téléphone, titres de section entre deux traits orange, carte WhatsApp verte sans flèche, et trois produits similaires (le premier en grand sur téléphone, trois colonnes sur ordinateur) qui apparaissent au défilement. Aucun texte ne change.', 'famma-core' ),
			),
			'pdp_pay'      => array(
				'label' => __( 'Fiche produit : « Quand payez-vous ? » et zéros animés', 'famma-core' ),
				'help'  => __( 'Sur toutes les fiches, sous le formulaire : une bande marine avec un compteur « Déjà payé 0 DT », un colis qui roule sur une route, les étapes « Comment commander » (textes de la section Boutique) qui se cochent au défilement, et le compteur qui passe au prix du produit à l’arrivée chez le livreur, puis un bouton « Commander » qui ramène au formulaire. Elle remplace le bandeau des quatre bénéfices. Avec la bande « 0 DT » cochée : ses deux zéros s’impriment à l’arrivée, et l’encadré de réassurance ne garde que le délai de livraison. Titres dans la section « Fiche produit ». Désactivé d’office si le visiteur a demandé moins d’animations (tout est alors affiché d’un coup).', 'famma-core' ),
			),
			'pdp_details'  => array(
				'label' => __( 'Fiche produit : « Tout savoir avant de commander » en volets', 'famma-core' ),
				'help'  => __( 'Sur toutes les fiches, les onglets Description, FAQ et Livraison & Retours deviennent des volets qui s’ouvrent l’un sous l’autre, comme la maquette. La description est découpée à chacun de ses intertitres (niveau 3) : un volet par intertitre, le début de la description reste visible au-dessus. Puis une question de la FAQ par volet, puis « Livraison & Retours ». Rien n’est réécrit : c’est le contenu de chaque produit. Titre dans la section « Fiche produit ».', 'famma-core' ),
			),
			'home_polish'  => array(
				'label' => __( 'Accueil : mise en page de la maquette', 'famma-core' ),
				'help'  => __( 'Sur la page d’accueil : bouton « Commander » juste sous le nom sur téléphone, rayons en grille, nouveautés sur deux colonnes, questions fréquentes avec chevrons, carte WhatsApp verte, cartes « Pourquoi FAMMA » et infolettre claire. Seuls l’ordre et le dessin changent : les textes restent ceux de l’éditeur.', 'famma-core' ),
			),
			'home_motion'  => array(
				'label' => __( 'Accueil : animations de la maquette', 'famma-core' ),
				'help'  => __( 'Sur la page d’accueil : barre d’avancement en haut de l’écran, logo qui cligne de l’œil (et fait un clin d’œil à chaque ajout au panier), entrée animée du nom et du carrousel, bouton pause et minuteur du carrousel, sections qui apparaissent au défilement. Avec le parcours animé : un colis roule sur la route, la route passe à l’horizontale sur ordinateur, et les 24 gouvernorats (ceux de WooCommerce) défilent sous les étapes. Désactivé d’office si le visiteur a demandé moins d’animations. Aucun texte ne change.', 'famma-core' ),
			),
			'home_facts'   => array(
				'label' => __( 'Accueil : chiffres « 24 gouvernorats · 0 DT · 0 compte »', 'famma-core' ),
				'help'  => __( 'Sous les étapes du bloc « Vous payez à la livraison », trois chiffres en grand qui se comptent à l’arrivée. Les chiffres et leurs légendes se modifient dans FAMMA → Pages → Accueil, champs « Chiffres » ; un chiffre vide disparaît.', 'famma-core' ),
			),
			'home_why'     => array(
				'label' => __( 'Accueil : « Pourquoi FAMMA » juste après le paiement à la livraison', 'famma-core' ),
				'help'  => __( 'Les cartes « Pourquoi choisir FAMMA ? » remontent juste sous le bloc « Vous payez à la livraison », comme dans la maquette, au lieu d’être après les questions fréquentes. Sans ce bloc dans la page d’accueil, elles restent à leur place.', 'famma-core' ),
			),
			'hide_band'    => array(
				'label' => __( 'Accueil : retirer le bloc « Une question sur un produit ? »', 'famma-core' ),
				'help'  => __( 'Le bloc WhatsApp du bas de l’accueil disparaît : la carte WhatsApp des questions fréquentes et le bouton vert flottant restent. Il reste affiché dans la boutique.', 'famma-core' ),
			),
			'cat_icons'    => array(
				'label' => __( 'Accueil : icônes des rayons', 'famma-core' ),
				'help'  => __( 'Un rayon sans miniature montre l’icône au trait choisie dans Produits → Catégories (champ « Icône sur l’accueil »), dessinée à l’arrivée, au lieu de l’étoile orange. Une miniature envoyée reste toujours prioritaire.', 'famma-core' ),
			),
			'wa_ring'      => array(
				'label' => __( 'Accueil : anneau sur le bouton WhatsApp', 'famma-core' ),
				'help'  => __( 'Le bouton vert flottant émet trois ondes à l’ouverture de l’accueil, puis s’arrête. Désactivé d’office si le visiteur a demandé moins d’animations.', 'famma-core' ),
			),
			'acct_mobile'  => array(
				'label' => __( 'Partout : icône « Mon compte » dans l’en-tête du téléphone', 'famma-core' ),
				'help'  => __( 'Sur téléphone, l’icône du compte s’affiche à côté du panier, comme sur ordinateur, au lieu d’être rangée dans le menu.', 'famma-core' ),
			),
			'acct_look'    => array(
				'label' => __( 'Mon compte : présentation de la maquette', 'famma-core' ),
				'help'  => __( 'Sur la page « Mon compte » : connexion et création de compte en deux onglets, champs et boutons de la charte ; une fois connecté, fil d’Ariane, carte marine avec les initiales, le nom, le téléphone, l’ancienneté et l’anneau « profil complété » (50 % le compte, +30 % une adresse, +20 % un e-mail), compteurs (commandes, en route, favoris de ce navigateur), puis trois onglets : Mes commandes, Mes adresses, Mes informations, avec un soulignement qui glisse. Le compte s’ouvre sur les commandes ; « Tableau de bord » et « Téléchargements » quittent les onglets et « Me déconnecter » passe dans « Mes informations ». Les champs, les e-mails et le fonctionnement de WooCommerce ne changent pas. Textes réglables dans la section « Mon compte ». Les animations s’arrêtent si le visiteur a demandé moins d’animations.', 'famma-core' ),
			),
			'acct_track'   => array(
				'label' => __( 'Mon compte : suivi en 4 étapes et « Recommander »', 'famma-core' ),
				'help'  => __( 'Dans « Mon compte » : chaque commande devient une carte comme dans la maquette (photos, « En route » ou « Livrée », total, frise Reçue → Confirmée → Expédiée → Livrée avec l’heure de chaque étape, rappel pour le livreur, détail dépliable avec les articles, le récapitulatif et le lieu) ; le détail d’une commande montre la même carte, ouverte. Une commande refusée, retournée ou annulée affiche son statut à la place de la frise. Boutons « Recommander » (commandes livrées, aux prix du jour) et « Une question » (WhatsApp). Textes réglables dans la section « Mon compte ».', 'famma-core' ),
			),
			'acct_forms'   => array(
				'label' => __( 'Mon compte : adresses et informations de la maquette', 'famma-core' ),
				'help'  => __( 'Dans « Mon compte », avec la case « présentation de la maquette » : « Mes adresses » montre l’adresse du client dans une carte (ou une carte « Ajouter une adresse ») ; « Modifier » ouvre une feuille qui glisse du bas sur téléphone, du côté sur ordinateur, avec le formulaire d’adresse de WooCommerce. « Mes informations » range le formulaire en deux boîtes, « Identité » et « Connexion » (mot de passe à déplier, puis « Me déconnecter »). Une seule adresse, celle de la commande : l’adresse de livraison séparée de WooCommerce n’est plus montrée dans la liste. Champs, contrôles et enregistrement inchangés. Textes réglables dans la section « Mon compte ».', 'famma-core' ),
			),
			'acct_favs'    => array(
				'label' => __( 'Mon compte : « Ma sélection » gardée dans le compte', 'famma-core' ),
				'help'  => __( 'Pour les clients connectés : les produits mis de côté avec le cœur sont aussi gardés dans leur compte, et se retrouvent sur un autre téléphone après connexion. Nouvel onglet « Ma sélection » dans « Mon compte » (avec la case « présentation de la maquette ») : une carte par produit, avec son prix, « Ajouter » et le cœur pour le retirer. Les visiteurs ne changent pas : leur sélection reste dans leur navigateur. Textes réglables dans la section « Mon compte ».', 'famma-core' ),
			),
			'acct_phone'   => array(
				'label' => __( 'Mon compte : inscription et connexion par numéro', 'famma-core' ),
				'help'  => __( 'Avec la case « présentation de la maquette » : la création de compte demande le téléphone, le nom et un mot de passe, comme la maquette ; l’e-mail devient facultatif et l’adresse peut être ajoutée tout de suite (gouvernorat, ville). Un numéro ne peut avoir qu’un compte. La connexion accepte le numéro (avec ou sans +216, avec espaces) ou l’e-mail. Un compte sans e-mail ne reçoit aucun e-mail. Mot de passe oublié : par WhatsApp, à la main, pour le moment. Une fois décochée, les comptes déjà créés par numéro peuvent toujours se connecter. Textes réglables dans la section « Mon compte ».', 'famma-core' ),
			),
			'acct_addrs'   => array(
				'label' => __( 'Mon compte : plusieurs adresses (Maison, Travail, Famille)', 'famma-core' ),
				'help'  => __( 'Avec les cases « présentation de la maquette » et « adresses et informations de la maquette » : « Mes adresses » montre une carte par adresse, avec son étiquette (Maison, Travail, Famille), « Modifier », « Utiliser par défaut » et « Supprimer » (avec « Annuler »), et une carte « Ajouter une adresse ». La feuille de la maquette demande l’étiquette, le gouvernorat, « Ville, cité, rue » et « Utiliser par défaut pour mes commandes ». L’adresse par défaut est celle que la commande préremplit, comme avant. Le téléphone se modifie dans « Mes informations ». Dix adresses au plus. Textes réglables dans la section « Mon compte ».', 'famma-core' ),
			),
			'acct_privacy' => array(
				'label' => __( 'Mon compte : mot de passe oublié et suppression du compte', 'famma-core' ),
				'help'  => __( 'Avec la case « présentation de la maquette » : sous « Me connecter », « Mot de passe oublié ? » se déplie comme dans la maquette et propose d’écrire sur WhatsApp depuis le numéro du compte (message préparé, numéro tapé ajouté), puis le lien e-mail de WooCommerce. Vous changez le mot de passe à la main : Comptes → rechercher le numéro → Modifier. Avec la case « adresses et informations de la maquette », « Mes informations » se termine par la boîte « Supprimer mon compte », avec confirmation dans la page ; elle reste cachée tant que son explication (section « Mon compte ») est vide. « Oui, supprimer » crée une demande dans Outils → Effacer les données personnelles, sans e-mail : rien n’est effacé avant votre validation, et le client peut l’annuler en attendant. Les commandes suivent les réglages de confidentialité de WooCommerce.', 'famma-core' ),
			),
			'acct_prefs'   => array(
				'label' => __( 'Mon compte : préférences (langue des messages, nouveautés WhatsApp)', 'famma-core' ),
				'help'  => __( 'Avec les cases « présentation de la maquette » et « adresses et informations de la maquette » : « Mes informations » montre la boîte « Préférences » de la maquette, en tête de la colonne de droite. « Langue de mes messages » (FR / عربي) note la langue dans laquelle appeler et écrire au client, rappelée dans chaque commande sous l’adresse de facturation, et bascule la page dans cette langue. « Nouveautés sur WhatsApp » est refusé par défaut et n’apparaît qu’une fois son texte de consentement écrit (section « Mon compte ») ; la date et le texte accepté sont gardés sur le compte. Le site n’envoie aucun message : Comptes → « Nouveautés WhatsApp » liste les inscrits, avec une colonne « Préférences ».', 'famma-core' ),
			),
			'acct_welcome' => array(
				'label' => __( 'Mon compte : écran « Bienvenue » après l’inscription', 'famma-core' ),
				'help'  => __( 'Avec la case « présentation de la maquette » : juste après la création d’un compte depuis « Mon compte » (pas depuis la commande), la première page du compte montre l’écran de la maquette : la mascotte cligne avec des étincelles, « Bienvenue, {prénom} ! » et « Votre compte est prêt », puis le compte apparaît avec ses animations d’arrivée (2,3 secondes). Une seule fois par compte. Sans script, l’écran reste en haut du compte. La ligne « commandes rattachées » de la maquette attend le rattachement par code (lot 4). Textes réglables dans la section « Mon compte ».', 'famma-core' ),
			),
			'acct_finish'  => array(
				'label' => __( 'Mon compte : finitions de la maquette', 'famma-core' ),
				'help'  => __( 'Avec la case « présentation de la maquette » : « Mes informations » demande un seul « Nom et prénom » (au lieu de Prénom, Nom et Nom affiché), l’e-mail sans astérisque avec « (facultatif) » et son aide pour un compte créé par numéro ; « Mes commandes » sans commande montre la mascotte et un bouton vers la boutique ; « Mot de passe » sans astérisque à l’inscription et à la connexion ; « Vous êtes déconnecté. » après « Me déconnecter » ; l’initiale du client en pastille orange sur l’icône compte ; sur téléphone, le bas du compte laisse la place du bouton WhatsApp. Textes réglables dans la section « Mon compte ».', 'famma-core' ),
			),
			'journey'      => array(
				'label' => __( 'Accueil : bloc « Vous payez à la livraison » en parcours animé', 'famma-core' ),
				'help'  => __( 'Le bloc déjà présent dans la page d’accueil devient une bande marine : une route relie les étapes, qui se cochent au défilement. Ses textes se modifient toujours dans l’éditeur de la page d’accueil.', 'famma-core' ),
			),
			'dock'         => array(
				'label' => __( 'Barre du panier « Aujourd’hui 0 DT »', 'famma-core' ),
				'help'  => __( 'Dans la boutique, une barre en bas d’écran rappelle qu’on ne paie rien aujourd’hui et donne le total à payer au livreur. Elle n’apparaît que si le panier contient un article.', 'famma-core' ),
			),
		);
	}

	/**
	 * Réglages enregistrés, revalidés.
	 *
	 * @return array{mode: string, parts: array<string, bool>}
	 */
	public static function settings(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$raw = get_option( self::OPTION, array() );
		$raw = is_array( $raw ) ? $raw : array();

		$mode = isset( $raw['mode'] ) ? (string) $raw['mode'] : self::DEFAULT_MODE;
		if ( ! array_key_exists( $mode, self::modes() ) ) {
			$mode = self::DEFAULT_MODE;
		}

		$saved = isset( $raw['parts'] ) && is_array( $raw['parts'] ) ? $raw['parts'] : null;
		$parts = array();
		foreach ( array_keys( self::parts() ) as $part ) {
			// Jamais enregistré : chaque brique est prête, le mode décide seul.
			$parts[ $part ] = null === $saved ? true : ! empty( $saved[ $part ] );
		}

		self::$cache = array(
			'mode'  => $mode,
			'parts' => $parts,
		);

		return self::$cache;
	}

	/**
	 * Le nouveau look s'applique-t-il à la requête en cours ?
	 *
	 * @return bool
	 */
	public static function active(): bool {
		$mode = self::settings()['mode'];

		if ( 'all' === $mode ) {
			return true;
		}

		return 'admins' === $mode && is_user_logged_in() && current_user_can( self::PREVIEW_CAP );
	}

	/**
	 * Une brique précise est-elle servie à la requête en cours ?
	 *
	 * @param string $part Clé de `parts()`.
	 * @return bool
	 */
	public static function part( string $part ): bool {
		$parts = self::settings()['parts'];

		return self::active() && ! empty( $parts[ $part ] );
	}

	/**
	 * Enregistre l'option et son champ, sur l'écran FAMMA → Pages.
	 *
	 * La Settings API vérifie elle-même le nonce (`settings_fields`) et la
	 * capacité `manage_options` à l'envoi du formulaire (`options.php`).
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			Pages_Content::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'famma_section_new_look',
			__( 'Nouveau look animé', 'famma-core' ),
			array( $this, 'section_intro' ),
			Pages_Content::SLUG
		);

		add_settings_field(
			self::OPTION,
			esc_html__( 'Mode et briques', 'famma-core' ),
			array( $this, 'render_field' ),
			Pages_Content::SLUG,
			'famma_section_new_look'
		);
	}

	/**
	 * N'accepte qu'un mode connu et des briques connues.
	 *
	 * @param mixed $value Valeur soumise.
	 * @return array{mode: string, parts: array<string, bool>}
	 */
	public function sanitize( $value ): array {
		$value = is_array( $value ) ? wp_unslash( $value ) : array();

		$mode = isset( $value['mode'] ) ? sanitize_key( (string) $value['mode'] ) : self::DEFAULT_MODE;
		if ( ! array_key_exists( $mode, self::modes() ) ) {
			$mode = self::DEFAULT_MODE;
		}

		$sent  = isset( $value['parts'] ) && is_array( $value['parts'] ) ? $value['parts'] : array();
		$parts = array();
		foreach ( array_keys( self::parts() ) as $part ) {
			$parts[ $part ] = ! empty( $sent[ $part ] );
		}

		self::$cache = null;

		return array(
			'mode'  => $mode,
			'parts' => $parts,
		);
	}

	/**
	 * Vide tout le cache de page après un changement.
	 *
	 * Le nouveau look touche toutes les pages (couleur des boutons) : purger
	 * une seule adresse laisserait l'ancien look sur les autres.
	 *
	 * @return void
	 */
	public function flush_page_cache(): void {
		self::$cache = null;
		do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API publique de LiteSpeed Cache, sans effet si le plugin est absent.
	}

	/**
	 * Texte d'introduction de la section.
	 *
	 * @return void
	 */
	public function section_intro(): void {
		?>
		<p>
			<?php esc_html_e( 'Commencez par « Administrateurs seulement » : vous voyez le nouveau look sur le vrai site, les visiteurs et les campagnes ne voient aucun changement. Passez à « Tout le monde » seulement après avoir testé une commande de bout en bout.', 'famma-core' ); ?>
		</p>
		<?php
	}

	/**
	 * Affiche le mode (boutons radio) et les briques (cases à cocher).
	 *
	 * @return void
	 */
	public function render_field(): void {
		$current = self::settings();
		$name    = self::OPTION;
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Mode du nouveau look', 'famma-core' ); ?></legend>
			<?php foreach ( self::modes() as $mode => $meta ) : ?>
				<p>
					<label>
						<input
							type="radio"
							name="<?php echo esc_attr( $name . '[mode]' ); ?>"
							value="<?php echo esc_attr( $mode ); ?>"
							aria-describedby="<?php echo esc_attr( $name . '_' . $mode . '_help' ); ?>"
							<?php checked( $current['mode'], $mode ); ?>
						/>
						<strong><?php echo esc_html( $meta['label'] ); ?></strong>
					</label>
					<br />
					<span class="description" id="<?php echo esc_attr( $name . '_' . $mode . '_help' ); ?>"><?php echo esc_html( $meta['help'] ); ?></span>
				</p>
			<?php endforeach; ?>
		</fieldset>
		<fieldset>
			<legend><strong><?php esc_html_e( 'Briques servies quand le nouveau look est actif', 'famma-core' ); ?></strong></legend>
			<?php foreach ( self::parts() as $part => $meta ) : ?>
				<p>
					<label>
						<input
							type="checkbox"
							name="<?php echo esc_attr( $name . '[parts][' . $part . ']' ); ?>"
							value="1"
							aria-describedby="<?php echo esc_attr( $name . '_' . $part . '_help' ); ?>"
							<?php checked( ! empty( $current['parts'][ $part ] ) ); ?>
						/>
						<?php echo esc_html( $meta['label'] ); ?>
					</label>
					<br />
					<span class="description" id="<?php echo esc_attr( $name . '_' . $part . '_help' ); ?>"><?php echo esc_html( $meta['help'] ); ?></span>
				</p>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}
}
