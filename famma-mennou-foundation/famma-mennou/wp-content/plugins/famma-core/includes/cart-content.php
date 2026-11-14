<?php
/**
 * Textes du panier (nouveau look, onglet « Panier ») — éditables depuis
 * FAMMA → Nouveau look.
 *
 * Même mécanisme que `Shop_Content` : ce fichier ne porte que le schéma et la
 * lecture ; l'assainissement et l'enregistrement sont ceux de
 * `Pages_Content`, qui traite le panier comme une page de plus.
 *
 * Les textes par défaut ne promettent que ce que le parcours applique déjà :
 * rien à payer en ligne, paiement en espèces au livreur (§2). Ceux du panier
 * vide reprennent mot pour mot les textes actuels du thème. La version derja
 * est celle de la maquette du 14/11, à relire.
 *
 * Servis par la brique `cart_look` (thème, inc/new-look-cart.php). Phrase
 * vidée : elle disparaît. Titre du panier vide vidé : le texte du thème
 * reste.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Schéma et lecture des textes du panier.
 */
final class Cart_Content {

	/**
	 * Clé de page, au sens de `Pages_Content`.
	 */
	public const PAGE = 'cart';

	/**
	 * Schéma des champs du panier.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		return array(
			array(
				'key'        => 'lead',
				'type'       => 'text',
				'label'      => __( 'Phrase sous « Mon panier »', 'famma-core' ),
				'help'       => __( 'Affichée seulement si le paiement à la livraison est activé dans WooCommerce. Vide : la phrase disparaît.', 'famma-core' ),
				'default_fr' => __( 'Aujourd’hui, vous payez 0 DT. Le reste se règle au livreur, en espèces.', 'famma-core' ),
				'default_ar' => 'اليوم تخلّص 0 دينار. الباقي تخلّصو لليفرور، كاش.',
			),
			array(
				'key'        => 'empty_title',
				'type'       => 'text',
				'label'      => __( 'Panier vide — titre', 'famma-core' ),
				'help'       => __( 'Sous la mascotte. Vide : le titre habituel du thème.', 'famma-core' ),
				'default_fr' => __( 'Votre panier est vide', 'famma-core' ),
				'default_ar' => 'الباني متاعك فارغ',
			),
			array(
				'key'        => 'empty_text',
				'type'       => 'text',
				'label'      => __( 'Panier vide — phrase', 'famma-core' ),
				'help'       => __( 'Sous le titre. Vide : la phrase habituelle du thème.', 'famma-core' ),
				'default_fr' => __( 'Parcourez la boutique et ajoutez vos premiers produits.', 'famma-core' ),
				'default_ar' => 'دور في البوتيك وزيد أول حوايجك.',
			),
		);
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
}
