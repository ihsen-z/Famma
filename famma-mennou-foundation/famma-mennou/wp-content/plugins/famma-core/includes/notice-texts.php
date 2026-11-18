<?php
/**
 * Textes des messages du site (brique `notices`, onglet « Partout » de
 * FAMMA → Nouveau look).
 *
 * Même mécanisme que `Cart_Content` : ce fichier ne porte que le schéma et la
 * lecture ; l'assainissement et l'enregistrement sont ceux de
 * `Pages_Content`, qui traite « Partout » comme une page de plus (option
 * `famma_page_site`).
 *
 * Les erreurs de la commande remplacent les phrases de WooCommerce
 * (« Adresse / العنوان de facturation est obligatoire. ») et celles de
 * famma-core, écrites en deux langues : ici une seule, celle de la page. Un
 * texte vidé rend la phrase d'origine. La version derja est à relire.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Schéma et lecture des textes des messages.
 */
final class Notice_Texts {

	/**
	 * Clé de page, au sens de `Pages_Content`.
	 */
	public const PAGE = 'site';

	/**
	 * Schéma des champs.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		$empty = __( 'Vide : la phrase de WooCommerce.', 'famma-core' );

		return array(
			self::field( 'added', __( 'Ajout au panier — bandeau', 'famma-core' ), __( 'En haut de la page après un ajout, fermé seul au bout de 5 secondes. {p} = nom du produit. Vide : la phrase de WooCommerce.', 'famma-core' ), __( 'Ajouté au panier : {p}', 'famma-core' ), 'تزاد للباني: {p}' ),
			self::field( 'added_link', __( 'Ajout au panier — lien', 'famma-core' ), __( 'Lien du bandeau vers le panier.', 'famma-core' ), __( 'Voir le panier', 'famma-core' ), 'شوف الباني' ),
			self::field( 'close', __( 'Bouton « Fermer » (lu par les lecteurs d’écran)', 'famma-core' ), __( 'La croix du bandeau.', 'famma-core' ), __( 'Fermer', 'famma-core' ), 'سكّر' ),
			self::field( 'err_name', __( 'Commande — nom manquant', 'famma-core' ), $empty, __( 'Indiquez votre nom et prénom.', 'famma-core' ), 'اكتب اسمك ولقبك.' ),
			self::field( 'err_name_short', __( 'Commande — nom trop court', 'famma-core' ), __( 'Vide : la phrase de famma-core.', 'famma-core' ), __( 'Votre nom complet : au moins 3 lettres.', 'famma-core' ), 'الاسم واللقب: 3 حروف على الأقل.' ),
			self::field( 'err_phone', __( 'Commande — téléphone manquant', 'famma-core' ), $empty, __( 'Indiquez votre numéro de téléphone.', 'famma-core' ), 'اكتب نومرو التليفون متاعك.' ),
			self::field( 'err_phone_bad', __( 'Commande — téléphone incorrect', 'famma-core' ), __( 'Numéro qui n’a pas 8 chiffres. Vide : la phrase de famma-core.', 'famma-core' ), __( 'Le numéro doit faire 8 chiffres.', 'famma-core' ), 'النومرو لازم يكون 8 أرقام.' ),
			self::field( 'err_state', __( 'Commande — gouvernorat manquant', 'famma-core' ), $empty, __( 'Choisissez votre gouvernorat.', 'famma-core' ), 'اختار الولاية متاعك.' ),
			self::field( 'err_address', __( 'Commande — adresse manquante', 'famma-core' ), $empty, __( 'Indiquez votre adresse pour la livraison.', 'famma-core' ), 'اكتب العنوان متاعك باش نوصلولك.' ),
			self::field( 'err_city', __( 'Commande — ville manquante', 'famma-core' ), __( 'Seulement si la ville devient obligatoire. Vide : la phrase de WooCommerce.', 'famma-core' ), __( 'Indiquez votre ville ou délégation.', 'famma-core' ), 'اكتب المدينة ولا المعتمدية.' ),
			self::field( 'err_email_bad', __( 'Commande — e-mail incorrect', 'famma-core' ), $empty, __( 'Cette adresse e-mail ne semble pas valide.', 'famma-core' ), 'الإيميل هذا موش صحيح.' ),
			self::field( 'err_email_account', __( 'Commande — e-mail pour créer un compte', 'famma-core' ), __( 'Le client a demandé un compte sans donner d’e-mail. Vide : la phrase de famma-core.', 'famma-core' ), __( 'Pour créer un compte, indiquez votre e-mail. Sinon, commandez sans compte.', 'famma-core' ), 'باش تعمل كونت، اكتب الإيميل متاعك. ولا أطلب بلاش كونت.' ),
			self::field( 'err_terms', __( 'Commande — conditions non acceptées', 'famma-core' ), $empty, __( 'Cochez la case pour accepter les conditions de vente.', 'famma-core' ), 'أشّر على الخانة باش تقبل شروط البيع.' ),
			self::field( 'err_field', __( 'Commande — autre champ manquant', 'famma-core' ), __( 'Tout autre champ obligatoire laissé vide. Vide : la phrase de WooCommerce.', 'famma-core' ), __( 'Ce champ est à compléter.', 'famma-core' ), 'الخانة هذي لازم تتعمّر.' ),
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

	/**
	 * Une entrée du schéma.
	 *
	 * @param string $key   Clé.
	 * @param string $label Libellé dans l'administration.
	 * @param string $help  Aide.
	 * @param string $fr    Texte français par défaut.
	 * @param string $ar    Texte derja par défaut.
	 * @return array<string, string>
	 */
	private static function field( string $key, string $label, string $help, string $fr, string $ar ): array {
		return array(
			'key'        => $key,
			'type'       => 'text',
			'label'      => $label,
			'help'       => $help,
			'default_fr' => $fr,
			'default_ar' => $ar,
		);
	}
}
