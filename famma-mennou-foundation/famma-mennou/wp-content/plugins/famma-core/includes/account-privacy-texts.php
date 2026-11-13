<?php
/**
 * Textes « Mot de passe oublié » et « Supprimer mon compte » — case
 * `acct_privacy` (lot 8 de l'espace client).
 *
 * Réglables dans FAMMA → Pages → « Mon compte ». Les libellés de la maquette
 * espace client sont repris ; ceux qui n'y sont pas (WhatsApp à la main au
 * lieu du code SMS, décision D3 ; demande traitée dans l'admin) sont marqués
 * « Pas dans la maquette ». Le texte qui explique la suppression est laissé
 * vide : il revient au propriétaire, et la boîte reste cachée tant qu'il
 * n'est pas écrit.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Textes du lot 8.
 */
final class Account_Privacy_Texts {

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
		$new = __( 'Pas dans la maquette : derja à relire en priorité.', 'famma-core' );

		return array(
			self::field( 'fg_b', __( 'Mot de passe oublié — bouton', 'famma-core' ), __( 'Mot de passe oublié ?', 'famma-core' ), 'نسيت كلمة السر؟', __( 'Avec la case « Mon compte : mot de passe oublié et suppression du compte ».', 'famma-core' ) ),
			self::field( 'fg_p', __( 'Mot de passe oublié — explication', 'famma-core' ), __( 'Écrivez-nous sur WhatsApp depuis le numéro de votre compte : nous vous aidons à choisir un nouveau mot de passe.', 'famma-core' ), 'ابعثلنا على الواتساب من النومرو متاع حسابك، نعاونوك تختار كلمة سر جديدة.', __( 'La maquette prévoit un code SMS ; décision D3 : WhatsApp traité à la main pour commencer. ', 'famma-core' ) . $new ),
			self::field( 'fg_go', __( 'Mot de passe oublié — bouton WhatsApp', 'famma-core' ), __( 'Demander sur WhatsApp', 'famma-core' ), 'اطلب على الواتساب', $new ),
			self::field( 'fg_msg', __( 'Mot de passe oublié — message WhatsApp préparé', 'famma-core' ), __( 'Bonjour FAMMA, j’ai oublié le mot de passe de mon compte.', 'famma-core' ), 'عسلامة FAMMA، نسيت كلمة السر متاع حسابي.', $new ),
			self::field(
				'fg_num',
				__( 'Mot de passe oublié — numéro ajouté au message', 'famma-core' ),
				/* translators: %s: numéro de téléphone tapé par le client. */
				__( 'Numéro du compte : %s', 'famma-core' ),
				'نومرو الحساب: %s',
				__( '« %s » est remplacé par le numéro tapé dans « Téléphone ». ', 'famma-core' ) . $new // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- « %s » est ici un texte affiché tel quel, pas un emplacement.
			),
			self::field( 'fg_mail', __( 'Mot de passe oublié — lien par e-mail', 'famma-core' ), __( 'Recevoir plutôt un lien par e-mail', 'famma-core' ), 'ولا ابعثلي رابط على الإيميل', __( 'Page « mot de passe perdu » de WooCommerce, pour les comptes qui ont une adresse e-mail. ', 'famma-core' ) . $new ),
			self::field( 'del_t', __( 'Supprimer mon compte — titre et bouton', 'famma-core' ), __( 'Supprimer mon compte', 'famma-core' ), 'فسّخ حسابي' ),
			self::field( 'del_s', __( 'Supprimer mon compte — explication (à rédiger)', 'famma-core' ), '', '', __( 'Texte du propriétaire : ce qui est effacé, ce qui est gardé (les commandes), le délai. Tant qu’il est vide, la boîte « Supprimer mon compte » n’est pas montrée.', 'famma-core' ) ),
			self::field( 'del_q', __( 'Supprimer mon compte — question', 'famma-core' ), __( 'Supprimer définitivement votre compte ?', 'famma-core' ), 'تحب تفسّخ حسابك نهائياً؟' ),
			self::field( 'del_y', __( 'Supprimer mon compte — confirmer', 'famma-core' ), __( 'Oui, supprimer', 'famma-core' ), 'إيه، فسّخ' ),
			self::field( 'del_n', __( 'Supprimer mon compte — renoncer', 'famma-core' ), __( 'Garder mon compte', 'famma-core' ), 'خلّي حسابي' ),
			self::field(
				'del_sent',
				__( 'Supprimer mon compte — demande envoyée', 'famma-core' ),
				/* translators: %s: date de la demande. */
				__( 'Demande de suppression envoyée le %s. Nous la traitons à la main.', 'famma-core' ),
				'طلب الفسخ تبعث نهار %s. نعالجوه بيدينا.',
				__( '« %s » est remplacé par la date. La maquette supprime tout de suite ; ici, la demande attend votre validation dans l’admin. ', 'famma-core' ) . $new // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- « %s » est ici un texte affiché tel quel, pas un emplacement.
			),
			self::field( 'del_cancel', __( 'Supprimer mon compte — annuler la demande', 'famma-core' ), __( 'Annuler ma demande', 'famma-core' ), 'بطّل الطلب', $new ),
			self::field( 'del_cancelled', __( 'Supprimer mon compte — demande annulée', 'famma-core' ), __( 'Demande annulée : votre compte est gardé.', 'famma-core' ), 'الطلب تبطّل: حسابك باقي.', $new ),
			self::field( 'del_err', __( 'Supprimer mon compte — erreur', 'famma-core' ), __( 'La demande n’a pas pu être enregistrée. Réessayez ou écrivez-nous sur WhatsApp.', 'famma-core' ), 'الطلب ما تسجّلش. عاود ولا ابعثلنا على الواتساب.', $new ),
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
