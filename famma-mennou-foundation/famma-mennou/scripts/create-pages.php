<?php
/**
 * Pages d'information et légales (§8, §45).
 *
 * Idempotent : chaque page est retrouvée par son slug et mise à jour.
 *
 * DEUX RÉGIMES DE CONTENU, jamais mélangés (§60) :
 *
 *  1. Ce que le code fait réellement — données collectées au checkout,
 *     cookies réellement posés, livraison à 0 TND, 24 gouvernorats. C'est
 *     vérifiable dans famma-core, donc écrit noir sur blanc.
 *  2. Ce qui relève d'une décision du propriétaire ou d'un conseil
 *     juridique — délais, retours, identité légale, transporteur. Rien
 *     n'est inventé : un encart « à valider » le signale à sa place.
 *
 * Les encarts doivent disparaître avant toute mise en production.
 *
 * @package Famma\Local
 */

( static function (): void {

	$todo = static function ( string $what ): string {
		return '<!-- wp:html --><p class="famma-todo"><strong>À compléter par le propriétaire :</strong> '
			. $what . '</p><!-- /wp:html -->';
	};

	$h2 = static function ( string $text ): string {
		return '<!-- wp:heading --><h2 class="wp-block-heading">' . $text . '</h2><!-- /wp:heading -->';
	};

	$p = static function ( string $text ): string {
		return '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->';
	};

	$pages = array(

		'a-propos'             => array(
			'title'   => 'À propos',
			'content' => $p( 'FAMMA est une boutique en ligne tunisienne. Nous sélectionnons des produits pratiques, utiles et parfois surprenants, livrés partout en Tunisie et payés à la livraison.' )
				. $p( '<em lang="ar" dir="rtl">فمّا ديما حاجة جديدة</em> — il y a toujours quelque chose de nouveau.' )
				. $h2( 'Comment nous travaillons' )
				. $p( 'Vous commandez sans créer de compte. Nous vous appelons pour confirmer. Vous payez en espèces au livreur, à la réception.' )
				. $todo( 'histoire de la marque, équipe, et tout élément vérifiable que vous souhaitez présenter. Aucun chiffre ni témoignage ne sera écrit sans source.' ),
		),

		'contact'              => array(
			'title'   => 'Contact',
			'content' => $p( 'Une question sur une commande, un produit ou une livraison ? Écrivez-nous.' )
				. '<!-- wp:shortcode -->[famma_whatsapp text="Écrire sur WhatsApp"]<!-- /wp:shortcode -->'
				. $todo( 'numéro WhatsApp, téléphone, adresse e-mail et horaires de réponse. Le bouton WhatsApp apparaîtra automatiquement dès que le numéro sera renseigné.' ),
		),

		'faq'                  => array(
			'title'   => 'Questions fréquentes',
			'content' => $h2( 'Comment je paie ?' )
				. $p( 'En espèces, au livreur, au moment où vous recevez votre commande. Aucun paiement en ligne n\'est demandé.' )
				. $h2( 'Combien coûte la livraison ?' )
				. $p( '0 TND. La livraison est déjà comprise dans le prix affiché.' )
				. $h2( 'Vous livrez où ?' )
				. $p( 'Partout en Tunisie, dans les 24 gouvernorats.' )
				. $h2( 'Dois-je créer un compte ?' )
				. $p( 'Non. La commande se fait sans compte, avec votre nom, votre téléphone et votre adresse.' )
				. $todo( 'délais de livraison, conditions d\'échange et de retour, que faire en cas de produit abîmé. Ces réponses engagent la boutique : elles ne seront pas rédigées à votre place.' ),
		),

		'livraison'            => array(
			'title'   => 'Livraison',
			'content' => $h2( 'Zone couverte' )
				. $p( 'Toute la Tunisie, dans les 24 gouvernorats.' )
				. $h2( 'Frais de livraison' )
				. $p( '0 TND. Le prix affiché sur la fiche produit est le prix final : la livraison y est déjà comprise.' )
				. $h2( 'Paiement' )
				. $p( 'À la livraison, en espèces, au livreur.' )
				. $todo( 'transporteur retenu, délais par gouvernorat, suivi de colis, et procédure en cas d\'absence à la livraison. Aucun transporteur n\'est encore sélectionné.' ),
		),

		'retours'              => array(
			'title'   => 'Retours et remboursements',
			'content' => $todo( 'l\'intégralité de cette page. Une politique de retour engage juridiquement la boutique et dépend de vos accords avec le transporteur — elle ne peut pas être rédigée sans vous.' )
				. $p( 'Points à trancher : délai de rétractation accordé, état dans lequel le produit doit être renvoyé, qui paie le retour, délai et moyen de remboursement, traitement d\'un colis refusé à la livraison.' ),
		),

		'conditions-generales' => array(
			'title'   => 'Conditions générales de vente',
			'content' => $todo( 'l\'intégralité de cette page, à faire valider par un conseil juridique. Aucun texte légal ne sera généré automatiquement (§60).' )
				. $h2( 'Informations légales à fournir' )
				. $p( 'Raison sociale, forme juridique, adresse du siège, matricule fiscal, numéro de registre du commerce, nom du responsable de la publication, coordonnées de contact.' )
				. $h2( 'Clauses à faire rédiger' )
				. $p( 'Objet et champ d\'application, processus de commande, prix et devise, modalités de paiement à la livraison, livraison, droit de rétractation, garanties légales, données personnelles, droit applicable et règlement des litiges.' ),
		),

		'confidentialite'      => array(
			'title'   => 'Politique de confidentialité',
			'content' => $p( 'Cette page décrit les données que la boutique collecte réellement, telles qu\'elles sont implémentées dans le code.' )
				. $h2( 'Données collectées à la commande' )
				. $p( 'Nom et prénom, numéro de téléphone, adresse, ville ou délégation, gouvernorat. L\'adresse e-mail est facultative. Aucun moyen de paiement n\'est collecté : le règlement se fait en espèces à la livraison.' )
				. $h2( 'Pourquoi' )
				. $p( 'Ces informations servent uniquement à confirmer la commande par téléphone, à préparer le colis et à le faire livrer.' )
				. $h2( 'Données d\'acquisition' )
				. $p( 'Si vous arrivez depuis une publicité, les paramètres de campagne (utm_source, utm_medium, utm_campaign, utm_content, utm_term) sont conservés dans un cookie pendant 30 jours et rattachés à la commande. Ils ne contiennent aucune donnée personnelle.' )
				. $h2( 'Mesure d\'audience' )
				. $p( 'Des outils de mesure (Meta, TikTok, Google Analytics) peuvent être activés. Ils ne le sont que si les identifiants correspondants sont configurés ; en leur absence, aucun script de mesure n\'est chargé.' )
				. $h2( 'Vos droits' )
				. $p( 'Vous pouvez demander l\'accès à vos données, leur rectification ou leur suppression en nous contactant.' )
				. $todo( 'identité du responsable de traitement, adresse de contact pour l\'exercice des droits, durée de conservation des commandes, et validation de l\'ensemble par un conseil juridique.' ),
		),

		'cookies'              => array(
			'title'   => 'Politique de cookies',
			'content' => $p( 'Voici les cookies réellement déposés par cette boutique.' )
				. $h2( 'Cookies nécessaires' )
				. $p( 'WooCommerce dépose des cookies techniques pour retenir le contenu de votre panier et votre session pendant la commande. Sans eux, la boutique ne peut pas fonctionner.' )
				. $h2( 'Langue' )
				. $p( 'Un cookie retient la langue choisie, française ou arabe, pour vous la réafficher à la visite suivante.' )
				. $h2( 'Acquisition' )
				. $p( 'Le cookie <code>famma_utm</code> conserve pendant 30 jours les paramètres de campagne publicitaire par lesquels vous êtes arrivé. Il sert à savoir quelles publicités fonctionnent. Il ne contient ni nom, ni téléphone, ni adresse.' )
				. $h2( 'Mesure d\'audience' )
				. $p( 'Meta, TikTok et Google Analytics déposent leurs propres cookies, mais uniquement si ces outils ont été activés par la boutique. Tant qu\'aucun identifiant n\'est configuré, aucun de ces scripts n\'est chargé et aucun de ces cookies n\'est déposé.' )
				. $todo( 'décision sur la bannière de consentement, à arbitrer avec votre conseil juridique une fois les outils de mesure activés.' ),
		),
	);

	$created = 0;
	$updated = 0;

	foreach ( $pages as $slug => $page ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );

		$args = array(
			'post_title'   => $page['title'],
			'post_name'    => $slug,
			'post_content' => $page['content'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);

		if ( $existing instanceof WP_Post ) {
			$args['ID'] = $existing->ID;
			$id         = wp_update_post( $args, true );
			++$updated;
		} else {
			$id = wp_insert_post( $args, true );
			++$created;
		}

		if ( is_wp_error( $id ) ) {
			echo 'ECHEC ' . $slug . ' : ' . $id->get_error_message() . "\n";
			continue;
		}

		// Rattache les pages que WordPress et WooCommerce savent utiliser.
		if ( 'confidentialite' === $slug ) {
			update_option( 'wp_page_for_privacy_policy', $id );
		}
		if ( 'conditions-generales' === $slug ) {
			update_option( 'woocommerce_terms_page_id', $id );
		}
	}

	// La page « Privacy Policy » que WordPress crée en brouillon fait doublon.
	$stock = get_page_by_path( 'privacy-policy', OBJECT, 'page' );
	if ( $stock instanceof WP_Post && (int) get_option( 'wp_page_for_privacy_policy' ) !== $stock->ID ) {
		wp_delete_post( $stock->ID, true );
		echo "  page « Privacy Policy » de WordPress supprimee (doublon).\n";
	}

	echo 'Pages d\'information : ' . $created . ' creee(s), ' . $updated . " mise(s) a jour.\n";
	echo "  Confidentialite et cookies decrivent le comportement reel du code.\n";
	echo "  Retours et CGV restent entierement a rediger : aucun texte legal invente (§60).\n";
} )();
