<?php
/**
 * Avis par lien WhatsApp — textes réglables.
 *
 * Dans FAMMA → Pages → « Fiche produit », en français et en derja. Les textes
 * proposés sont des brouillons à relire par le propriétaire (la derja en
 * priorité). Un message vidé est respecté : le bouton de cette langue n'est
 * alors pas proposé sur la commande.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Textes de l'avis par lien.
 */
final class Review_Texts {

	/**
	 * Clé de page, au sens de `Pages_Content`.
	 */
	public const PAGE = 'product';

	/**
	 * Un champ du schéma.
	 *
	 * @param string $key   Clé.
	 * @param string $label Libellé dans l'admin.
	 * @param string $fr    Texte français par défaut.
	 * @param string $ar    Texte derja par défaut.
	 * @param string $help  Aide.
	 * @param string $type  `text` ou `textarea`.
	 * @return array<string, string>
	 */
	private static function field( string $key, string $label, string $fr, string $ar, string $help = '', string $type = 'text' ): array {
		return array(
			'key'        => $key,
			'type'       => $type,
			'label'      => $label,
			'help'       => $help,
			'default_fr' => $fr,
			'default_ar' => $ar,
		);
	}

	/**
	 * Schéma des textes.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		$draft = __( 'Brouillon à relire.', 'famma-core' );

		return array(
			self::field( 'rv_msg', __( 'Avis par WhatsApp — message envoyé au client', 'famma-core' ), __( 'Bonjour {prenom}, merci pour votre commande FAMMA ! Votre avis sur {produit} aide d’autres clients : {lien}', 'famma-core' ), 'عسلامة {prenom}، يعيشك على الكوموند متاعك من FAMMA! قلّنا رايك في {produit}: {lien}', __( 'Préparé par le bouton « Demander un avis » d’une commande Livrée ou Terminée. {prenom}, {produit} et {lien} sont remplacés. Vidé : le bouton de cette langue disparaît.', 'famma-core' ) . ' ' . $draft, 'textarea' ),
			self::field( 'rv_title', __( 'Avis par WhatsApp — titre du formulaire', 'famma-core' ), __( 'Votre avis sur ce produit', 'famma-core' ), 'رايك في البرودوي هذا', $draft ),
			self::field( 'rv_hello', __( 'Avis par WhatsApp — phrase au-dessus du formulaire', 'famma-core' ), __( 'Merci pour votre commande ! Une note et quelques mots suffisent.', 'famma-core' ), 'يعيشك على الكوموند! نجمات وكلمتين يكفيو.', $draft ),
			self::field( 'rv_thanks', __( 'Avis par WhatsApp — remerciement après l’envoi', 'famma-core' ), __( 'Merci ! Votre avis sera publié après vérification.', 'famma-core' ), 'يعيشك! رايك باش يتنشر بعد ما نثبتوه.', $draft ),
			self::field( 'rv_invalid', __( 'Avis par WhatsApp — lien expiré ou déjà utilisé', 'famma-core' ), __( 'Ce lien d’avis n’est plus valable. Écrivez-nous sur WhatsApp si besoin.', 'famma-core' ), 'الرابط هذا ما عادش يخدم. كلّمنا على واتساب كان تحب.', $draft ),
		);
	}

	/**
	 * Lit un texte dans la langue de la page.
	 *
	 * @param string $key Clé du champ.
	 * @return string
	 */
	public static function text( string $key ): string {
		return trim( Pages_Content::text( self::PAGE, $key ) );
	}

	/**
	 * Message WhatsApp prêt à envoyer, dans une langue ; vide si le texte l'est.
	 *
	 * @param string    $lang       `fr` ou `ar`.
	 * @param \WC_Order $order      Commande.
	 * @param int       $product_id Produit.
	 * @return string
	 */
	public static function message( string $lang, \WC_Order $order, int $product_id ): string {
		$template = trim( Pages_Content::text_in( self::PAGE, 'rv_msg', $lang, false ) );
		$link     = Review_Link::url( $order, $product_id );
		$product  = wc_get_product( $product_id );

		if ( '' === $template || '' === $link || ! $product instanceof \WC_Product ) {
			return '';
		}

		return strtr(
			$template,
			array(
				'{prenom}'  => trim( $order->get_billing_first_name() ),
				'{produit}' => wp_strip_all_tags( $product->get_name() ),
				'{lien}'    => $link,
			)
		);
	}
}
