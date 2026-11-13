<?php
/**
 * Textes de l'inscription et de la connexion par numéro — case `acct_phone`.
 *
 * Réglables dans FAMMA → Pages → « Mon compte ». Les textes viennent de la
 * maquette espace client (derja à relire) ; ceux qui n'y sont pas sont
 * signalés dans l'aide.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Textes de l'inscription par numéro.
 */
final class Account_Phone_Texts {

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
	 * Schéma des textes.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		$not_mockup = __( 'Pas dans la maquette : derja à relire en priorité.', 'famma-core' );

		return array(
			self::field( 'ph_sub', __( 'Inscription par numéro — phrase sous le titre', 'famma-core' ), __( 'Trois champs. Le même numéro que pour vos commandes.', 'famma-core' ), 'ثلاثة خانات برك. نفس النومرو متاع الطلبيات.', __( 'Remplace la phrase de « Création — phrase sous le titre » quand la case « Mon compte : inscription et connexion par numéro » est cochée. Vide : la phrase disparaît.', 'famma-core' ) ),
			self::field( 'tel_label', __( 'Inscription — champ téléphone', 'famma-core' ), __( 'Téléphone', 'famma-core' ), 'التليفون' ),
			self::field( 'tel_help', __( 'Inscription — aide sous le téléphone', 'famma-core' ), __( 'Il sert à vous connecter et à confirmer vos commandes.', 'famma-core' ), 'بيه تدخل لحسابك ونأكدو معاك الطلبيات.', __( 'Vide : l’aide disparaît.', 'famma-core' ) ),
			self::field( 'name_label', __( 'Inscription — champ nom', 'famma-core' ), __( 'Nom et prénom', 'famma-core' ), 'الاسم واللقب' ),
			self::field( 'addr_toggle', __( 'Inscription — déplier l’adresse', 'famma-core' ), __( 'Ajouter mon adresse maintenant', 'famma-core' ), 'زيد العنوان توا' ),
			self::field( 'optional', __( 'Inscription — mention facultatif', 'famma-core' ), __( '(facultatif)', 'famma-core' ), '(موش لازم)' ),
			self::field( 'gov_label', __( 'Inscription — champ gouvernorat', 'famma-core' ), __( 'Gouvernorat', 'famma-core' ), 'الولاية' ),
			self::field( 'gov_choose', __( 'Inscription — gouvernorat à choisir', 'famma-core' ), __( 'Choisir', 'famma-core' ), 'اختار' ),
			self::field( 'addr_label', __( 'Inscription — champ adresse', 'famma-core' ), __( 'Ville, cité, rue', 'famma-core' ), 'المدينة، الحي، النهج' ),
			self::field( 'pw_m1', __( 'Mot de passe — jauge faible', 'famma-core' ), __( 'Faible : ajoutez des chiffres ou des lettres.', 'famma-core' ), 'ضعيفة: زيد أرقام ولا حروف.' ),
			self::field( 'pw_m2', __( 'Mot de passe — jauge correcte', 'famma-core' ), __( 'Correct.', 'famma-core' ), 'باهية.' ),
			self::field( 'pw_m3', __( 'Mot de passe — jauge solide', 'famma-core' ), __( 'Solide.', 'famma-core' ), 'قوية برشا.' ),
			self::field( 'sum_h', __( 'Erreurs — titre, un point', 'famma-core' ), __( 'Il reste {n} point à corriger', 'famma-core' ), 'باقي {n} حاجة لازم تتصلّح', __( '{n} est remplacé par le nombre.', 'famma-core' ) ),
			self::field( 'sum_hs', __( 'Erreurs — titre, plusieurs points', 'famma-core' ), __( 'Il reste {n} points à corriger', 'famma-core' ), 'باقي {n} حوايج لازم يتصلّحو', __( '{n} est remplacé par le nombre.', 'famma-core' ) ),
			self::field( 'login_label', __( 'Connexion — champ identifiant', 'famma-core' ), __( 'Téléphone ou e-mail', 'famma-core' ), 'التليفون ولا الإيميل', $not_mockup ),
			self::field( 'err_login', __( 'Connexion — numéro ou mot de passe faux', 'famma-core' ), __( 'Numéro ou mot de passe incorrect.', 'famma-core' ), 'النومرو ولا كلمة السر موش صحاح.', __( 'Pas dans la maquette : derja à relire en priorité.', 'famma-core' ) ),
			self::field( 'err_tel', __( 'Erreur — numéro incomplet', 'famma-core' ), __( 'Entrez les 8 chiffres de votre numéro, sans le +216.', 'famma-core' ), 'اكتب الـ 8 أرقام متاع النومرو، بلا +216.' ),
			self::field( 'err_tel_tn', __( 'Erreur — numéro non tunisien', 'famma-core' ), __( 'Ce numéro ne ressemble pas à un numéro tunisien : il commence par 2, 3, 4, 5, 7 ou 9.', 'famma-core' ), 'النومرو هذا موش تونسي: لازم يبدا بـ 2، 3، 4، 5، 7 ولا 9.' ),
			self::field( 'err_taken', __( 'Erreur — numéro déjà inscrit', 'famma-core' ), __( 'Ce numéro a déjà un compte. Connectez-vous avec lui.', 'famma-core' ), 'النومرو هذا عندو حساب. ادخل بيه.', $not_mockup ),
			self::field( 'err_name', __( 'Erreur — nom', 'famma-core' ), __( 'Écrivez votre nom et votre prénom.', 'famma-core' ), 'اكتب اسمك ولقبك.' ),
			self::field( 'err_pw', __( 'Erreur — mot de passe', 'famma-core' ), __( 'Choisissez au moins 8 caractères.', 'famma-core' ), 'اختار 8 حروف ولا أكثر.' ),
			self::field( 'err_later', __( 'Erreur — trop d’essais', 'famma-core' ), __( 'Trop d’essais. Réessayez dans quelques minutes.', 'famma-core' ), 'برشا محاولات. عاود بعد شوية.', $not_mockup ),
			self::field( 'created', __( 'Inscription — message de bienvenue', 'famma-core' ), __( 'Bienvenue ! Votre compte est prêt.', 'famma-core' ), 'مرحبا بيك! الحساب متاعك حاضر.' ),
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
