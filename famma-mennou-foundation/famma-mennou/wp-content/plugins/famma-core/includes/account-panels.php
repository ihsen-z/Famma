<?php
/**
 * « Mes adresses » et « Mes informations » à la manière de la maquette —
 * case `acct_forms`.
 *
 * Textes de la carte d'adresse, de la feuille qui glisse pour la modifier et
 * des boîtes « Identité » / « Connexion ». Ce fichier ne fait que fournir les
 * textes, réglables dans FAMMA → Pages → « Mon compte » ; le thème dessine
 * (inc/new-look-account-forms.php). Les champs, la validation et
 * l'enregistrement restent ceux de WooCommerce : une seule adresse, celle de
 * facturation, comme à la commande.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Textes des panneaux « Mes adresses » et « Mes informations ».
 */
final class Account_Panels {

	/**
	 * Clé de page, au sens de `Pages_Content` (même section que l'en-tête).
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
	 * Schéma des champs des deux panneaux.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		return array(
			self::field( 'addr_card', __( 'Adresses — titre de la carte', 'famma-core' ), __( 'Mon adresse', 'famma-core' ), 'العنوان متاعي', __( 'Avec la case « Mon compte : adresses et informations de la maquette ». La maquette affiche « Maison » : les étiquettes Maison / Travail / Famille viendront avec plusieurs adresses.', 'famma-core' ) ),
			self::field( 'addr_default', __( 'Adresses — pastille « par défaut »', 'famma-core' ), __( 'Par défaut', 'famma-core' ), 'الأساسي', __( 'Vide : la pastille disparaît.', 'famma-core' ) ),
			self::field( 'addr_edit', __( 'Adresses — lien « Modifier »', 'famma-core' ), __( 'Modifier', 'famma-core' ), 'بدّل' ),
			self::field( 'addr_add', __( 'Adresses — carte « Ajouter »', 'famma-core' ), __( 'Ajouter une adresse', 'famma-core' ), 'زيد عنوان' ),
			self::field( 'addr_empty', __( 'Adresses — aucune adresse', 'famma-core' ), __( 'Aucune adresse pour le moment. Ajoutez-en une pour commander plus vite.', 'famma-core' ), 'ما فماش عنوان توا. زيد واحد باش تطلب أسرع.', __( 'Vide : la phrase disparaît.', 'famma-core' ) ),
			self::field( 'sheet_edit', __( 'Feuille — titre (modifier)', 'famma-core' ), __( 'Modifier l’adresse', 'famma-core' ), 'بدّل العنوان' ),
			self::field( 'sheet_new', __( 'Feuille — titre (nouvelle)', 'famma-core' ), __( 'Nouvelle adresse', 'famma-core' ), 'عنوان جديد' ),
			self::field( 'sheet_close', __( 'Feuille — bouton fermer (lu à voix haute)', 'famma-core' ), __( 'Fermer', 'famma-core' ), 'سكّر' ),
			self::field( 'sheet_gov', __( 'Feuille — champ gouvernorat', 'famma-core' ), __( 'Gouvernorat', 'famma-core' ), 'الولاية' ),
			self::field( 'sheet_street', __( 'Feuille — champ adresse', 'famma-core' ), __( 'Ville, cité, rue', 'famma-core' ), 'المدينة، الحي، النهج' ),
			self::field( 'sheet_street_help', __( 'Feuille — aide sous l’adresse', 'famma-core' ), __( 'Un repère aide le livreur : près de la mosquée, 2e étage…', 'famma-core' ), 'علامة تعاون الليفرور: قدّام الجامع، الطابق الثاني…', __( 'Vide : l’aide disparaît.', 'famma-core' ) ),
			self::field( 'infos_identity', __( 'Informations — boîte identité', 'famma-core' ), __( 'Identité', 'famma-core' ), 'المعلومات' ),
			self::field( 'infos_login', __( 'Informations — boîte connexion', 'famma-core' ), __( 'Connexion', 'famma-core' ), 'الدخول' ),
			self::field( 'infos_pw', __( 'Informations — changer le mot de passe', 'famma-core' ), __( 'Changer mon mot de passe', 'famma-core' ), 'بدّل كلمة السر' ),
			self::field( 'infos_save', __( 'Informations — bouton enregistrer', 'famma-core' ), __( 'Enregistrer', 'famma-core' ), 'سجّل' ),
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
