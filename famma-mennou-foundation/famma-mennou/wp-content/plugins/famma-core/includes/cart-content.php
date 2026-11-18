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
 * Servis par les briques `cart_look`, `cart_split`, `cart_dock`, `cart_promo`,
 * `cart_save` et `cart_lot` (thème, inc/new-look-cart*.php). Phrase vidée : elle disparaît. Titre du panier vide
 * vidé : le texte du thème reste. Les titres des tuiles servent aussi à la
 * barre du bas.
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
			array(
				'key'        => 'split_now',
				'type'       => 'text',
				'label'      => __( 'Tuile verte — titre', 'famma-core' ),
				'help'       => __( 'Au-dessus de « 0 DT », dans le récapitulatif et la barre du bas. Vide avec l’autre titre : les tuiles disparaissent.', 'famma-core' ),
				'default_fr' => __( 'Aujourd’hui', 'famma-core' ),
				'default_ar' => 'اليوم',
			),
			array(
				'key'        => 'split_now_note',
				'type'       => 'text',
				'label'      => __( 'Tuile verte — phrase', 'famma-core' ),
				'help'       => __( 'Sous « 0 DT ». Vide : pas de phrase.', 'famma-core' ),
				'default_fr' => __( 'Rien à payer en ligne', 'famma-core' ),
				'default_ar' => 'ما تخلّص شي أونلاين',
			),
			array(
				'key'        => 'split_rec',
				'type'       => 'text',
				'label'      => __( 'Tuile marine — titre', 'famma-core' ),
				'help'       => __( 'Au-dessus du total, dans le récapitulatif et la barre du bas.', 'famma-core' ),
				'default_fr' => __( 'À la réception', 'famma-core' ),
				'default_ar' => 'كي توصلك',
			),
			array(
				'key'        => 'split_rec_note',
				'type'       => 'text',
				'label'      => __( 'Tuile marine — phrase', 'famma-core' ),
				'help'       => __( 'Sous le total. Vide : pas de phrase.', 'famma-core' ),
				'default_fr' => __( 'En espèces, au livreur', 'famma-core' ),
				'default_ar' => 'كاش، لليفرور',
			),
			array(
				'key'        => 'dock_go',
				'type'       => 'text',
				'label'      => __( 'Barre du bas — bouton', 'famma-core' ),
				'help'       => __( 'Mène à la page de commande. Vide : « Commander ».', 'famma-core' ),
				'default_fr' => __( 'Commander', 'famma-core' ),
				'default_ar' => 'أطلب توا',
			),
			array(
				'key'        => 'promo_label',
				'type'       => 'text',
				'label'      => __( 'Code promo replié — bouton', 'famma-core' ),
				'help'       => __( 'Un toucher ouvre le champ du code. Vide : « Code promo ».', 'famma-core' ),
				'default_fr' => __( 'Vous avez un code promo ?', 'famma-core' ),
				'default_ar' => 'عندك كود برومو؟',
			),
			array(
				'key'        => 'save_label',
				'type'       => 'text',
				'label'      => __( 'Mettre de côté — bouton', 'famma-core' ),
				'help'       => __( 'Sous le nom de chaque article. Vide : le bouton disparaît.', 'famma-core' ),
				'default_fr' => __( 'Mettre de côté', 'famma-core' ),
				'default_ar' => 'خليه على جنب',
			),
			array(
				'key'        => 'save_done',
				'type'       => 'text',
				'label'      => __( 'Mettre de côté — message', 'famma-core' ),
				'help'       => __( 'En tête du message « … retiré. Annuler ? » de WooCommerce. Vide : pas de phrase en plus.', 'famma-core' ),
				'default_fr' => __( 'Gardé dans « Ma sélection ».', 'famma-core' ),
				'default_ar' => 'تحفظ في « اختياراتي ».',
			),
			array(
				'key'        => 'lot_q',
				'type'       => 'text',
				'label'      => __( 'Lot — phrase', 'famma-core' ),
				'help'       => __( 'Sous l’article. {n} = articles du lot, {a} = prix du lot, {b} = prix des articles seuls. Une phrase propre au produit, dans l’onglet « Fiche FAMMA », passe avant celle-ci. Vide : pas de phrase.', 'famma-core' ),
				'default_fr' => __( '{n} articles ? Le lot de {n} coûte {a} au lieu de {b}.', 'famma-core' ),
				'default_ar' => '{n} حوايج؟ اللوط متاع {n} بـ {a} عوض {b}.',
			),
			array(
				'key'        => 'lot_go',
				'type'       => 'text',
				'label'      => __( 'Lot — bouton', 'famma-core' ),
				'help'       => __( 'Remplace les articles par le lot. Vide : « Passer au lot ».', 'famma-core' ),
				'default_fr' => __( 'Passer au lot', 'famma-core' ),
				'default_ar' => 'بدّل باللوط',
			),
			array(
				'key'        => 'lot_keep',
				'type'       => 'text',
				'label'      => __( 'Lot — gain sur le bouton', 'famma-core' ),
				'help'       => __( '{m} = ce que le client garde en passant au lot. Vide : pas de pastille.', 'famma-core' ),
				'default_fr' => __( 'Vous gardez {m}', 'famma-core' ),
				'default_ar' => 'تربح {m}',
			),
			array(
				'key'        => 'lot_done',
				'type'       => 'text',
				'label'      => __( 'Lot — message après l’échange', 'famma-core' ),
				'help'       => __( 'En tête du panier, après l’échange. {m} = ce que le client garde. Vide : pas de message.', 'famma-core' ),
				'default_fr' => __( 'Lot appliqué : vous gardez {m}.', 'famma-core' ),
				'default_ar' => 'اللوط تحطّ: ربحت {m}.',
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
