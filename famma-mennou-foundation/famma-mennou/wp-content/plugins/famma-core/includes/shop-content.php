<?php
/**
 * Textes de la boutique — bloc « Comment commander » — éditables depuis
 * FAMMA → Pages.
 *
 * Même mécanisme que `Home_Content` : ce fichier ne porte que le schéma et la
 * lecture ; l'écran, l'assainissement et l'enregistrement sont ceux de
 * `Pages_Content`, qui traite la boutique comme une page de plus.
 *
 * Les textes par défaut reprennent ceux du bloc « Vous payez à la livraison »
 * de l'accueil, déjà publiés : commande sans compte, appel de confirmation,
 * paiement en espèces au livreur. Ce sont les seuls faits affirmés, et le
 * parcours de commande les applique réellement. Aucun chiffre, aucun délai
 * (§58). La version derja est celle de la maquette du 06/11, à relire.
 *
 * Le bloc n'est servi que par la brique « how » du nouveau look (thème,
 * inc/new-look-how.php). Titre vidé : le bloc disparaît. Étape dont le titre
 * est vidé : l'étape disparaît.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Schéma et lecture des textes de la boutique.
 */
final class Shop_Content {

	/**
	 * Clé de page, au sens de `Pages_Content`.
	 */
	public const PAGE = 'shop';

	/**
	 * Nombre d'étapes du bloc.
	 */
	public const STEPS = 3;

	/**
	 * Schéma des champs de la boutique.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		$defaults = array(
			1 => array(
				'title_fr' => __( 'Vous commandez', 'famma-core' ),
				'title_ar' => 'تطلب',
				'text_fr'  => __( 'en ligne, sans créer de compte.', 'famma-core' ),
				'text_ar'  => 'أونلاين، بلا ما تحلّ كونت.',
			),
			2 => array(
				'title_fr' => __( 'Nous vous appelons', 'famma-core' ),
				'title_ar' => 'نعيطولك',
				'text_fr'  => __( 'pour confirmer la commande.', 'famma-core' ),
				'text_ar'  => 'باش نأكدو الطلبية.',
			),
			3 => array(
				'title_fr' => __( 'Vous payez au livreur', 'famma-core' ),
				'title_ar' => 'تخلّص لليفرور',
				'text_fr'  => __( 'en espèces, à la réception du colis.', 'famma-core' ),
				'text_ar'  => 'كاش، كي يوصلك الكوليس.',
			),
		);

		$fields = array(
			array(
				'key'        => 'how_title',
				'type'       => 'text',
				'label'      => __( 'Bloc « Comment commander » — titre', 'famma-core' ),
				'help'       => __( 'En bas de la boutique et des rayons, avec le nouveau look. Vide : le bloc disparaît.', 'famma-core' ),
				'default_fr' => __( 'Commander, sans rien payer aujourd’hui', 'famma-core' ),
				'default_ar' => 'أطلب، وما تخلّص شي اليوم',
			),
		);

		foreach ( $defaults as $i => $step ) {
			$fields[] = array(
				'key'        => 'how_' . $i . '_title',
				'type'       => 'text',
				/* translators: %d: numéro de l'étape. */
				'label'      => sprintf( __( 'Étape %d — en gras', 'famma-core' ), $i ),
				'help'       => __( 'Vide : l’étape disparaît.', 'famma-core' ),
				'default_fr' => $step['title_fr'],
				'default_ar' => $step['title_ar'],
			);
			$fields[] = array(
				'key'        => 'how_' . $i . '_text',
				'type'       => 'text',
				/* translators: %d: numéro de l'étape. */
				'label'      => sprintf( __( 'Étape %d — suite de la phrase', 'famma-core' ), $i ),
				'help'       => '',
				'default_fr' => $step['text_fr'],
				'default_ar' => $step['text_ar'],
			);
		}

		return $fields;
	}

	/**
	 * Lit un champ dans la langue de la page.
	 *
	 * @param string $key Clé du champ.
	 * @return string
	 */
	public static function text( string $key ): string {
		return trim( Pages_Content::text( self::PAGE, $key ) );
	}

	/**
	 * Étapes renseignées, dans l'ordre.
	 *
	 * @return array<int, array{title: string, text: string}>
	 */
	public static function steps(): array {
		$steps = array();

		for ( $i = 1; $i <= self::STEPS; $i++ ) {
			$title = self::text( 'how_' . $i . '_title' );

			if ( '' === $title ) {
				continue;
			}

			$steps[] = array(
				'title' => $title,
				'text'  => self::text( 'how_' . $i . '_text' ),
			);
		}

		return $steps;
	}
}
