<?php
/**
 * Textes de la boîte « Préférences » — case `acct_prefs` (lot 6 de l'espace
 * client).
 *
 * Réglables dans FAMMA → Pages → « Mon compte ». Les libellés de la maquette
 * espace client sont repris. Le texte qui décrit l'inscription aux nouveautés
 * WhatsApp est le consentement du client (décision D5) : il revient au
 * propriétaire et reste vide ; l'interrupteur n'est pas montré tant qu'il
 * n'est pas écrit.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Textes du lot 6.
 */
final class Account_Prefs_Texts {

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
			self::field( 'pf_lang', __( 'Préférences — langue des messages', 'famma-core' ), __( 'Langue de mes messages', 'famma-core' ), 'لغة الميساجات', __( 'Avec la case « Mon compte : préférences ». Le choix est noté sur le compte et rappelé dans chaque commande du client ; il bascule aussi la page dans cette langue, comme la maquette.', 'famma-core' ) ),
			self::field( 'pf_lang_s', __( 'Préférences — langue des messages, précision', 'famma-core' ), __( 'Appels, SMS et WhatsApp.', 'famma-core' ), 'التليفون، SMS وواتساب.' ),
			self::field( 'pf_wa', __( 'Préférences — nouveautés WhatsApp', 'famma-core' ), __( 'Nouveautés sur WhatsApp', 'famma-core' ), 'الجديد على واتساب' ),
			self::field( 'pf_wa_s', __( 'Préférences — nouveautés WhatsApp, consentement (à rédiger)', 'famma-core' ), '', '', __( 'Texte du propriétaire (décision D5) : quels messages, combien de fois, comment arrêter. La maquette propose « Un message par semaine au plus. ». Tant qu’il est vide, l’interrupteur n’est pas montré. Le texte accepté est gardé avec la date sur le compte du client.', 'famma-core' ) ),
			self::field( 'pf_err', __( 'Préférences — erreur', 'famma-core' ), __( 'Le choix n’a pas pu être enregistré. Réessayez.', 'famma-core' ), 'الاختيار ما تسجّلش. عاود.', __( 'Pas dans la maquette : derja à relire en priorité.', 'famma-core' ) ),
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
