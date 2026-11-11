<?php
/**
 * Textes de l'accueil — newsletter et « Ils nous font confiance » — éditables depuis FAMMA → Pages.
 *
 * Même mécanisme que `Product_Content` : ce fichier ne porte que le schéma et
 * la lecture ; l'écran, l'assainissement et l'enregistrement sont ceux de
 * `Pages_Content`, qui traite l'accueil comme une page de plus.
 *
 * Aucun chiffre ni témoignage par défaut (§58) : la maquette affichait
 * « + 2 500 clients satisfaits », « 4.8/5 » et trois avis signés. Ici, les
 * champs correspondants démarrent vides et le bloc reste masqué tant que le
 * propriétaire n'y a pas saisi de **vraies** données. La note moyenne, elle,
 * n'est jamais saisie : elle est calculée sur les avis WooCommerce approuvés.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Schéma et lecture des textes de l'accueil.
 */
final class Home_Content {

	/**
	 * Clé de page, au sens de `Pages_Content`.
	 */
	public const PAGE = 'home';

	/**
	 * Nombre de témoignages proposés dans l'administration.
	 */
	public const TESTIMONIALS = 3;

	/**
	 * Schéma des champs de l'accueil.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		$fields = array_merge(
			self::hero_schema(),
			self::why_schema(),
			self::newsletter_and_trust_schema()
		);

		for ( $i = 1; $i <= self::TESTIMONIALS; $i++ ) {
			$fields[] = array(
				'key'        => 'testimonial_' . $i . '_text',
				'type'       => 'textarea',
				/* translators: %d: numéro du témoignage. */
				'label'      => sprintf( __( 'Témoignage %d — texte', 'famma-core' ), $i ),
				'help'       => __( 'Un vrai message de client, avec son accord. Vide : le témoignage disparaît.', 'famma-core' ),
				'default_fr' => '',
				'default_ar' => '',
			);
			$fields[] = array(
				'key'        => 'testimonial_' . $i . '_name',
				'type'       => 'text',
				/* translators: %d: numéro du témoignage. */
				'label'      => sprintf( __( 'Témoignage %d — nom affiché', 'famma-core' ), $i ),
				'help'       => __( 'Prénom et initiale, par exemple « Sarah M. ».', 'famma-core' ),
				'default_fr' => '',
				'default_ar' => '',
			);
		}

		return $fields;
	}

	/**
	 * Champs du héros, en haut de l'accueil.
	 *
	 * Les valeurs par défaut sont les textes affichés jusqu'ici (thème
	 * famma-child et sa traduction derja) : tant que rien n'est enregistré,
	 * la page reste identique.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function hero_schema(): array {
		return array(
			array(
				'key'        => 'hero_title',
				'type'       => 'text',
				'label'      => __( 'Héros — titre lu par Google et les lecteurs d’écran', 'famma-core' ),
				'help'       => __( 'Complète le mot FAMMA dans le titre principal de la page. Il n’est pas affiché à l’écran.', 'famma-core' ),
				'default_fr' => 'Il y a toujours quelque chose de nouveau.',
				'default_ar' => 'ديما فمّا حاجة جديدة.',
			),
			array(
				'key'        => 'hero_slogan',
				'type'       => 'text',
				'label'      => __( 'Héros — slogan sous le logo', 'famma-core' ),
				'help'       => __( 'Le premier mot est mis en couleur. Vide : le slogan disparaît.', 'famma-core' ),
				'default_fr' => 'فمّا ديما حاجة جديدة',
				'default_ar' => 'فمّا ديما حاجة جديدة',
			),
			array(
				'key'        => 'hero_lead',
				'type'       => 'textarea',
				'label'      => __( 'Héros — phrase de présentation', 'famma-core' ),
				'default_fr' => 'FAMMA choisit des produits pratiques et utiles, livrés partout en Tunisie et payés à la livraison.',
				'default_ar' => 'فمّا تختار حوايج تنفع وتخدم، نوصّلوهم في تونس الكل، وتخلّص كي يوصلك.',
			),
			array(
				'key'        => 'hero_perk_1_title',
				'type'       => 'text',
				'label'      => __( 'Héros — atout 1 (paiement)', 'famma-core' ),
				'help'       => __( 'Vide : l’atout disparaît.', 'famma-core' ),
				'default_fr' => 'Paiement à la livraison',
				'default_ar' => 'الدفع كي يوصلك',
			),
			array(
				'key'        => 'hero_perk_1_sub',
				'type'       => 'text',
				'label'      => __( 'Héros — atout 1, ligne dessous', 'famma-core' ),
				'help'       => __( 'Affichée seulement sur la page française : sur la page arabe, le titre dit déjà la même chose.', 'famma-core' ),
				'default_fr' => 'خلص كي توصل',
				'default_ar' => '',
			),
			array(
				'key'        => 'hero_perk_2_title',
				'type'       => 'text',
				'label'      => __( 'Héros — atout 2 (livraison)', 'famma-core' ),
				'help'       => __( 'Vide : l’atout disparaît.', 'famma-core' ),
				'default_fr' => 'Livraison offerte',
				'default_ar' => 'التوصيل فابور',
			),
			array(
				'key'        => 'hero_perk_2_sub',
				'type'       => 'text',
				'label'      => __( 'Héros — atout 2, ligne dessous', 'famma-core' ),
				'default_fr' => 'partout en Tunisie',
				'default_ar' => 'في تونس الكل',
			),
			array(
				'key'        => 'hero_perk_3_title',
				'type'       => 'text',
				'label'      => __( 'Héros — atout 3 (commande)', 'famma-core' ),
				'help'       => __( 'Vide : l’atout disparaît.', 'famma-core' ),
				'default_fr' => 'Commande sans compte',
				'default_ar' => 'أطلب بلا كونت',
			),
			array(
				'key'        => 'hero_perk_3_sub',
				'type'       => 'text',
				'label'      => __( 'Héros — atout 3, ligne dessous', 'famma-core' ),
				'default_fr' => 'nom, téléphone, adresse',
				'default_ar' => 'الإسم، التليفون، العنوان',
			),
			array(
				'key'        => 'hero_cta',
				'type'       => 'text',
				'label'      => __( 'Héros — bouton vers la boutique', 'famma-core' ),
				'help'       => __( 'Vide : le bouton disparaît.', 'famma-core' ),
				'default_fr' => 'Découvrir la boutique',
				'default_ar' => 'شوف الحانوت',
			),
		);
	}

	/**
	 * Champs de la section « Pourquoi choisir FAMMA ? ».
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function why_schema(): array {
		$items = array(
			1 => array(
				'Vous payez à la réception',
				'تخلّص كي يوصلك',
				'La commande n\'est réglée qu\'une fois livrée, en espèces, au livreur.',
				'الطلبية تتخلّص كي توصل، كاش، في يد الليفرور.',
			),
			2 => array(
				'Livraison offerte',
				'التوصيل فابور',
				'Partout en Tunisie, dans les 24 gouvernorats, sans frais ajoutés.',
				'في تونس الكل، في 24 ولاية، بلا مصاريف زايدة.',
			),
			3 => array(
				'Commande sans compte',
				'أطلب بلا كونت',
				'Nom, téléphone, adresse. Aucun mot de passe à créer.',
				'الإسم، التليفون، العنوان. ما تحتاجش تعمل باسّوورد.',
			),
			4 => array(
				'Une équipe joignable',
				'فمّا شكون يجاوبك',
				'Vos questions trouvent une réponse sur WhatsApp, avant comme après la commande.',
				'أسئلتك تلقى جواب على الواتساب، قبل الطلبية وبعدها.',
			),
		);

		$fields = array(
			array(
				'key'        => 'why_title',
				'type'       => 'text',
				'label'      => __( 'Pourquoi FAMMA — titre de la section', 'famma-core' ),
				'help'       => __( 'Vide : toute la section disparaît de l’accueil.', 'famma-core' ),
				'default_fr' => 'Pourquoi choisir FAMMA ?',
				'default_ar' => 'علاش تختار فمّا؟',
			),
		);

		foreach ( $items as $i => $texts ) {
			$fields[] = array(
				'key'        => 'why_' . $i . '_title',
				'type'       => 'text',
				/* translators: %d: numéro de la carte. */
				'label'      => sprintf( __( 'Pourquoi FAMMA — carte %d, titre', 'famma-core' ), $i ),
				'help'       => 4 === $i
					? __( 'Cette carte n’apparaît que si un numéro WhatsApp est configuré. Vide : la carte disparaît.', 'famma-core' )
					: __( 'Vide : la carte disparaît.', 'famma-core' ),
				'default_fr' => $texts[0],
				'default_ar' => $texts[1],
			);
			$fields[] = array(
				'key'        => 'why_' . $i . '_text',
				'type'       => 'textarea',
				/* translators: %d: numéro de la carte. */
				'label'      => sprintf( __( 'Pourquoi FAMMA — carte %d, texte', 'famma-core' ), $i ),
				'help'       => __( 'Ne promettez que ce qui est vrai aujourd’hui.', 'famma-core' ),
				'default_fr' => $texts[2],
				'default_ar' => $texts[3],
			);
		}

		return $fields;
	}

	/**
	 * Champs de la newsletter et de la section « confiance ».
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function newsletter_and_trust_schema(): array {
		return array(
			array(
				'key'        => 'nl_title',
				'type'       => 'text',
				'label'      => __( 'Newsletter — titre', 'famma-core' ),
				'default_fr' => 'فمّا ديما حاجة جديدة...',
				'default_ar' => 'فمّا ديما حاجة جديدة...',
			),
			array(
				'key'        => 'nl_text',
				'type'       => 'text',
				'label'      => __( 'Newsletter — texte', 'famma-core' ),
				'default_fr' => 'Abonnez-vous et soyez parmi les premiers à découvrir nos nouveautés',
				'default_ar' => 'أشترك وكون من الأوّلين اللي يكتشفو الجديد متاعنا',
			),
			array(
				'key'        => 'nl_placeholder',
				'type'       => 'text',
				'label'      => __( 'Newsletter — texte du champ e-mail', 'famma-core' ),
				'default_fr' => 'Votre e-mail',
				'default_ar' => 'الإيميل متاعك',
			),
			array(
				'key'        => 'nl_button',
				'type'       => 'text',
				'label'      => __( 'Newsletter — bouton', 'famma-core' ),
				'help'       => __( 'Vide : toute la section newsletter disparaît de l’accueil.', 'famma-core' ),
				'default_fr' => 'S’abonner',
				'default_ar' => 'أشترك',
			),
			array(
				'key'        => 'nl_note',
				'type'       => 'text',
				'label'      => __( 'Newsletter — mention sous le champ', 'famma-core' ),
				'help'       => __( 'Ne promettez que ce qui est vrai : le site enregistre l’adresse, il n’envoie aucun e-mail automatique.', 'famma-core' ),
				'default_fr' => 'Votre e-mail sert uniquement à vous prévenir de nos nouveautés.',
				'default_ar' => 'الإيميل متاعك يستعمل كان باش نعلموك بالجديد.',
			),
			array(
				'key'        => 'nl_success',
				'type'       => 'text',
				'label'      => __( 'Newsletter — message de confirmation', 'famma-core' ),
				'default_fr' => 'Merci ! Votre inscription est enregistrée.',
				'default_ar' => 'يعيشك! تسجّلت معانا.',
			),
			array(
				'key'        => 'trust_title',
				'type'       => 'text',
				'label'      => __( 'Confiance — titre de la section', 'famma-core' ),
				'help'       => __( 'La section n’apparaît que si elle a quelque chose de vrai à montrer : un chiffre saisi ci-dessous, un témoignage, ou au moins un avis client approuvé sur une fiche produit.', 'famma-core' ),
				'default_fr' => 'Ils nous font confiance',
				'default_ar' => 'يثقو فينا',
			),
			array(
				'key'        => 'trust_stat_value',
				'type'       => 'text',
				'label'      => __( 'Confiance — chiffre (ex. nombre de clients)', 'famma-core' ),
				'help'       => __( 'Uniquement un chiffre réel et vérifiable. Vide : la case disparaît.', 'famma-core' ),
				'default_fr' => '',
				'default_ar' => '',
			),
			array(
				'key'        => 'trust_stat_label',
				'type'       => 'text',
				'label'      => __( 'Confiance — légende du chiffre', 'famma-core' ),
				'default_fr' => 'Clients satisfaits',
				'default_ar' => 'حريف فرحان',
			),
		);
	}

	/**
	 * Texte dans la langue de la page.
	 *
	 * @param string $key Clé du champ.
	 * @return string Chaîne vide si le champ est vide.
	 */
	public static function text( string $key ): string {
		return trim( Pages_Content::text( self::PAGE, $key ) );
	}

	/**
	 * Témoignages renseignés, dans la langue de la page.
	 *
	 * @return array<int, array{text: string, name: string}>
	 */
	public static function testimonials(): array {
		$items = array();

		for ( $i = 1; $i <= self::TESTIMONIALS; $i++ ) {
			$text = self::text( 'testimonial_' . $i . '_text' );

			if ( '' === $text ) {
				continue;
			}

			$items[] = array(
				'text' => $text,
				'name' => self::text( 'testimonial_' . $i . '_name' ),
			);
		}

		return $items;
	}
}
