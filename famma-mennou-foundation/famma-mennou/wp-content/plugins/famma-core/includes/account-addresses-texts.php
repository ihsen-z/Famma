<?php
/**
 * Textes de « Mes adresses » à plusieurs adresses — case `acct_addrs`.
 *
 * Réglables dans FAMMA → Pages → « Mon compte ». Français et derja de la
 * maquette espace client (derja à relire).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Textes des adresses multiples.
 */
final class Account_Addresses_Texts {

	/**
	 * Clé de page, au sens de `Pages_Content`.
	 */
	public const PAGE = 'account';

	/**
	 * Un champ du schéma.
	 *
	 * @param string $key   Clé.
	 * @param string $label Libellé dans l'admin.
	 * @param string $fr    Texte français par défaut.
	 * @param string $ar    Texte derja par défaut.
	 * @param string $help  Aide.
	 * @return array<string, string>
	 */
	private static function field( string $key, string $label, string $fr, string $ar, string $help = '' ): array {
		return array(
			'key'        => $key,
			'type'       => 'text',
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
		return array(
			self::field( 'ad_home', __( 'Adresses — étiquette Maison', 'famma-core' ), __( 'Maison', 'famma-core' ), 'الدار', __( 'Avec la case « Mon compte : plusieurs adresses ».', 'famma-core' ) ),
			self::field( 'ad_work', __( 'Adresses — étiquette Travail', 'famma-core' ), __( 'Travail', 'famma-core' ), 'الخدمة' ),
			self::field( 'ad_family', __( 'Adresses — étiquette Famille', 'famma-core' ), __( 'Famille', 'famma-core' ), 'العايلة' ),
			self::field( 'ad_legend', __( 'Adresses — choix de l’étiquette', 'famma-core' ), __( 'Nom de l’adresse', 'famma-core' ), 'اسم العنوان' ),
			self::field( 'ad_make_def', __( 'Adresses — bouton « par défaut »', 'famma-core' ), __( 'Utiliser par défaut', 'famma-core' ), 'خليه الأساسي' ),
			self::field( 'ad_del', __( 'Adresses — bouton supprimer', 'famma-core' ), __( 'Supprimer', 'famma-core' ), 'فسّخ' ),
			self::field( 'ad_def_check', __( 'Adresses — case « par défaut »', 'famma-core' ), __( 'Utiliser par défaut pour mes commandes', 'famma-core' ), 'استعملو ديما في الطلبيات' ),
			self::field( 'ad_save', __( 'Adresses — bouton enregistrer', 'famma-core' ), __( 'Enregistrer', 'famma-core' ), 'سجّل' ),
			self::field( 'ad_saved', __( 'Adresses — message enregistrée', 'famma-core' ), __( 'Adresse enregistrée.', 'famma-core' ), 'العنوان تسجّل.' ),
			self::field( 'ad_gone', __( 'Adresses — message supprimée', 'famma-core' ), __( 'Adresse supprimée.', 'famma-core' ), 'العنوان تفسّخ.' ),
			self::field( 'ad_undo', __( 'Adresses — annuler la suppression', 'famma-core' ), __( 'Annuler', 'famma-core' ), 'رجّع' ),
			self::field( 'ad_err_gov', __( 'Adresses — erreur gouvernorat', 'famma-core' ), __( 'Choisissez le gouvernorat.', 'famma-core' ), 'اختار الولاية.' ),
			self::field( 'ad_full', __( 'Adresses — limite atteinte', 'famma-core' ), __( 'Vous avez déjà 10 adresses : supprimez-en une pour en ajouter une autre.', 'famma-core' ), 'عندك 10 عناوين: فسّخ واحد باش تزيد آخر.', __( 'Pas dans la maquette : derja à relire en priorité.', 'famma-core' ) ),
			self::field( 'id_tel', __( 'Mes informations — champ téléphone', 'famma-core' ), __( 'Téléphone', 'famma-core' ), 'التليفون', __( 'Avec la case « plusieurs adresses », le téléphone se modifie ici (il quitte la feuille d’adresse), comme dans la maquette.', 'famma-core' ) ),
			self::field( 'id_tel_taken', __( 'Mes informations — numéro déjà pris', 'famma-core' ), __( 'Ce numéro est déjà utilisé par un autre compte.', 'famma-core' ), 'النومرو هذا مستعمل في حساب آخر.', __( 'Pas dans la maquette : derja à relire en priorité.', 'famma-core' ) ),
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
