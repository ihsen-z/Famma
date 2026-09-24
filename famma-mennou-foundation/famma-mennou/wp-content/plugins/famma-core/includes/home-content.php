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
		$fields = array(
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
