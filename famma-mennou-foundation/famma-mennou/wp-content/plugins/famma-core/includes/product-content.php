<?php
/**
 * Textes de la fiche produit, éditables depuis FAMMA → Pages.
 *
 * Règle du projet : aucun texte commercial en dur. Les promesses sous le prix,
 * le bandeau de bénéfices, les libellés des boutons d'achat et le message
 * WhatsApp pré-rempli se modifient donc dans l'administration, en français et
 * en derja, sans développeur ni déploiement.
 *
 * Ce fichier ne porte que le **schéma** et la lecture. L'écran, l'assainissement
 * et l'enregistrement sont ceux de `Pages_Content`, qui traite la fiche produit
 * comme une page de plus : un seul mécanisme, pas deux copies qui divergent.
 *
 * Les valeurs par défaut sont les textes affichés jusqu'ici : tant que le
 * propriétaire n'y touche pas, le client ne voit aucune différence.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Schéma et lecture des textes de la fiche produit.
 */
final class Product_Content {

	/**
	 * Clé de page, au sens de `Pages_Content`.
	 */
	public const PAGE = 'product';

	/**
	 * Marqueur remplacé par le nom du produit dans le message WhatsApp.
	 */
	public const PRODUCT_TAG = '{produit}';

	/**
	 * Libellés de bouton : jamais vides, un bouton sans texte serait inutilisable.
	 *
	 * @var array<int, string>
	 */
	private const ALWAYS_FILLED = array( 'cta_order', 'cta_confirm', 'cta_cart' );

	/**
	 * Schéma des champs de la fiche produit.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		$empty_hides = __( 'Vide : la ligne disparaît de la fiche.', 'famma-core' );

		return array(
			array(
				'key'        => 'reassure_payment',
				'type'       => 'text',
				'label'      => __( 'Sous le prix — paiement', 'famma-core' ),
				'help'       => $empty_hides,
				'default_fr' => 'Paiement à la livraison',
				'default_ar' => 'الدفع كي يوصلك',
			),
			array(
				'key'        => 'reassure_shipping',
				'type'       => 'text',
				'label'      => __( 'Sous le prix — livraison', 'famma-core' ),
				'help'       => __( 'Le délai de livraison s’affiche juste en dessous : il se règle dans la section Contact, champ « Délai de livraison ». Vide : la ligne disparaît.', 'famma-core' ),
				'default_fr' => 'Livraison offerte, partout en Tunisie',
				'default_ar' => 'التوصيل فابور، في تونس الكل',
			),
			array(
				'key'        => 'cta_order',
				'type'       => 'text',
				'label'      => __( 'Bouton « Commander » (barre du bas, titre du formulaire)', 'famma-core' ),
				'help'       => __( 'Un bouton ne reste jamais sans texte : vide, il reprend le libellé par défaut.', 'famma-core' ),
				'default_fr' => 'Commander',
				'default_ar' => 'أطلب توا',
			),
			array(
				'key'        => 'cta_confirm',
				'type'       => 'text',
				'label'      => __( 'Bouton de validation du formulaire', 'famma-core' ),
				'default_fr' => 'Confirmer ma commande',
				'default_ar' => 'أكّد طلبيتك',
			),
			array(
				'key'        => 'cta_cart',
				'type'       => 'text',
				'label'      => __( 'Bouton « Ajouter au panier »', 'famma-core' ),
				'default_fr' => 'Ajouter au panier',
				'default_ar' => 'زيد للسلّة',
			),
			array(
				'key'        => 'whatsapp_card',
				'type'       => 'text',
				'label'      => __( 'Bouton WhatsApp sous le formulaire', 'famma-core' ),
				'help'       => __( 'Vide : le bouton disparaît (le lien WhatsApp de la barre du bas reste).', 'famma-core' ),
				'default_fr' => 'Une question sur ce produit ?',
				'default_ar' => 'عندك سؤال على المنتج هذا؟',
			),
			array(
				'key'        => 'whatsapp_card_sub',
				'type'       => 'text',
				'label'      => __( 'Bouton WhatsApp — seconde ligne', 'famma-core' ),
				'help'       => $empty_hides,
				'default_fr' => 'Écrivez-nous sur WhatsApp : nous vous répondons.',
				'default_ar' => 'أكتبلنا على الواتساب ونجاوبوك.',
			),
			array(
				'key'        => 'returns_title',
				'type'       => 'text',
				'label'      => __( 'Onglet « Livraison & Retours » — retours, titre', 'famma-core' ),
				'help'       => __( 'Les blocs livraison de cet onglet reprennent les points forts de la section « Page Livraison ». Vide : le bloc retours disparaît.', 'famma-core' ),
				'default_fr' => 'Retours et remboursements',
				'default_ar' => 'الإرجاع والاسترجاع',
			),
			array(
				'key'        => 'returns_text',
				'type'       => 'textarea',
				'label'      => __( 'Onglet « Livraison & Retours » — retours, texte', 'famma-core' ),
				'help'       => __( 'N’écrivez un délai ou une condition de retour qu’une fois votre politique publiée sur la page Retours.', 'famma-core' ),
				'default_fr' => 'Un article reçu pose problème ? Écrivez-nous avant de le renvoyer : nous vous répondrons.',
				'default_ar' => 'كان عندك مشكل مع حاجة وصلتك، أكتبلنا قبل ما تبعثها ونجاوبوك.',
			),
			array(
				'key'        => 'whatsapp_prefill',
				'type'       => 'textarea',
				'label'      => __( 'WhatsApp — message pré-rempli depuis une fiche', 'famma-core' ),
				'help'       => __( '{produit} est remplacé par le nom du produit. Vide : la conversation s’ouvre sans message.', 'famma-core' ),
				'default_fr' => 'Bonjour, une question sur : {produit}',
				'default_ar' => 'سلام، عندي سؤال على: {produit}',
			),
			array(
				'key'        => 'benefit_1_title',
				'type'       => 'text',
				'label'      => __( 'Bénéfice 1 — titre', 'famma-core' ),
				'help'       => __( 'Bandeau des bénéfices, sous la fiche (écrans larges). Un bénéfice sans titre disparaît.', 'famma-core' ),
				'default_fr' => 'Paiement à la livraison',
				'default_ar' => 'الدفع كي يوصلك',
			),
			array(
				'key'        => 'benefit_1_text',
				'type'       => 'text',
				'label'      => __( 'Bénéfice 1 — texte', 'famma-core' ),
				'default_fr' => 'Vous réglez en espèces, à la réception. Rien avant.',
				'default_ar' => 'تخلّص كاش كي يوصلك. حتى حاجة قبل.',
			),
			array(
				'key'        => 'benefit_2_title',
				'type'       => 'text',
				'label'      => __( 'Bénéfice 2 — titre', 'famma-core' ),
				'default_fr' => 'Livraison offerte',
				'default_ar' => 'التوصيل فابور',
			),
			array(
				'key'        => 'benefit_2_text',
				'type'       => 'text',
				'label'      => __( 'Bénéfice 2 — texte', 'famma-core' ),
				'default_fr' => 'Partout en Tunisie, dans les 24 gouvernorats.',
				'default_ar' => 'في تونس الكل، في 24 ولاية.',
			),
			array(
				'key'        => 'benefit_3_title',
				'type'       => 'text',
				'label'      => __( 'Bénéfice 3 — titre', 'famma-core' ),
				'default_fr' => 'Commande sans compte',
				'default_ar' => 'أطلب بلا كونت',
			),
			array(
				'key'        => 'benefit_3_text',
				'type'       => 'text',
				'label'      => __( 'Bénéfice 3 — texte', 'famma-core' ),
				'default_fr' => 'Nom, téléphone, gouvernorat et adresse. Rien de plus.',
				'default_ar' => 'الإسم، التليفون، الولاية والعنوان. حتى حاجة أخرى.',
			),
			array(
				'key'        => 'benefit_4_title',
				'type'       => 'text',
				'label'      => __( 'Bénéfice 4 — titre', 'famma-core' ),
				'help'       => __( 'Affiché seulement si un numéro WhatsApp est renseigné.', 'famma-core' ),
				'default_fr' => 'Une équipe joignable',
				'default_ar' => 'فمّا شكون يجاوبك',
			),
			array(
				'key'        => 'benefit_4_text',
				'type'       => 'text',
				'label'      => __( 'Bénéfice 4 — texte', 'famma-core' ),
				'default_fr' => 'Posez vos questions sur WhatsApp avant de commander.',
				'default_ar' => 'أسأل على الواتساب قبل ما تطلب.',
			),
		);
	}

	/**
	 * Texte dans la langue de la page.
	 *
	 * @param string $key Clé du champ.
	 * @return string Chaîne vide si le propriétaire a vidé le champ.
	 */
	public static function text( string $key ): string {
		$value = trim( Pages_Content::text( self::PAGE, $key ) );

		if ( '' === $value && in_array( $key, self::ALWAYS_FILLED, true ) ) {
			return self::default_value( $key, is_rtl() ? 'ar' : 'fr' );
		}

		return $value;
	}

	/**
	 * Version derja d'un texte, sans repli sur le français.
	 *
	 * Sert aux lignes de traduction affichées sous le français : si la version
	 * derja est vide, on n'affiche rien plutôt que de répéter le français.
	 *
	 * @param string $key Clé du champ.
	 * @return string
	 */
	public static function derja( string $key ): string {
		$value = trim( Pages_Content::text_in( self::PAGE, $key, 'ar', false ) );

		if ( '' === $value && in_array( $key, self::ALWAYS_FILLED, true ) ) {
			return self::default_value( $key, 'ar' );
		}

		return $value;
	}

	/**
	 * Message WhatsApp pré-rempli pour un produit.
	 *
	 * @param string $product_name Nom du produit.
	 * @return string Chaîne vide si le propriétaire ne veut pas de message.
	 */
	public static function whatsapp_message( string $product_name ): string {
		return str_replace( self::PRODUCT_TAG, $product_name, self::text( 'whatsapp_prefill' ) );
	}

	/**
	 * Valeur par défaut d'un champ, telle que déclarée au schéma.
	 *
	 * @param string $key  Clé du champ.
	 * @param string $lang `fr` ou `ar`.
	 * @return string
	 */
	private static function default_value( string $key, string $lang ): string {
		foreach ( self::schema() as $field ) {
			if ( $field['key'] === $key ) {
				return (string) ( $field[ 'default_' . $lang ] ?? '' );
			}
		}

		return '';
	}
}
