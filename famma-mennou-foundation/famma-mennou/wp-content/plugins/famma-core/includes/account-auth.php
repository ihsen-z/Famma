<?php
/**
 * Page de connexion de « Mon compte » à la manière de la maquette — case
 * `acct_look`.
 *
 * Textes de la carte « Votre espace FAMMA » (promesse, quatre avantages,
 * « Commander sans compte ») et de la carte d'accès (onglets, titres, bouton).
 * Ce fichier ne fait que fournir les textes, réglables dans FAMMA → Pages →
 * « Mon compte » ; le thème dessine (inc/new-look-account.php). Les champs,
 * les règles et l'envoi des formulaires restent ceux de WooCommerce.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Textes de la page de connexion.
 */
final class Account_Auth {

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
	 * Schéma des champs de la page de connexion.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		$empty = __( 'Vide : la ligne disparaît.', 'famma-core' );

		return array(
			self::field( 'auth_title', __( 'Connexion — titre', 'famma-core' ), __( 'Votre espace', 'famma-core' ), 'الحساب متاعك في', __( 'Carte marine à côté du formulaire de connexion, avec la case « Mon compte : présentation de la maquette ». Suivi du nom de la marque en orange.', 'famma-core' ) ),
			self::field( 'auth_brand', __( 'Connexion — mot en orange', 'famma-core' ), 'FAMMA', 'FAMMA' ),
			self::field( 'auth_lead', __( 'Connexion — phrase d’accroche', 'famma-core' ), __( 'Un compte gratuit pour suivre vos colis, garder vos adresses et recommander en un toucher. Toujours payé à la livraison.', 'famma-core' ), 'حساب بلاش باش تتبّع الكوليات متاعك، تحفظ العناوين وتعاود تطلب بضغطة وحدة. ديما الخلاص كي يوصلك.', $empty ),
			self::field( 'ben_1', __( 'Avantage 1 — titre', 'famma-core' ), __( 'Suivez vos colis', 'famma-core' ), 'تبّع الكوليات متاعك', __( 'Vide : l’avantage disparaît.', 'famma-core' ) ),
			self::field( 'ben_1_sub', __( 'Avantage 1 — détail', 'famma-core' ), __( 'De l’appel de confirmation jusqu’au livreur.', 'famma-core' ), 'من تليفون التأكيد حتى للّيفرور.' ),
			self::field( 'ben_2', __( 'Avantage 2 — titre', 'famma-core' ), __( 'Recommandez en un toucher', 'famma-core' ), 'عاود اطلب بضغطة', __( 'Vide : l’avantage disparaît.', 'famma-core' ) ),
			self::field( 'ben_2_sub', __( 'Avantage 2 — détail', 'famma-core' ), __( 'Vos articles et votre adresse sont déjà là.', 'famma-core' ), 'الحوايج والعنوان متاعك موجودين.' ),
			self::field( 'ben_3', __( 'Avantage 3 — titre', 'famma-core' ), __( 'Gardez votre adresse', 'famma-core' ), 'احفظ العنوان متاعك', __( 'La maquette dit « vos adresses » (maison, travail, famille) : à reprendre quand plusieurs adresses seront possibles. Vide : l’avantage disparaît.', 'famma-core' ) ),
			self::field( 'ben_3_sub', __( 'Avantage 3 — détail', 'famma-core' ), __( 'Plus rien à retaper à la prochaine commande.', 'famma-core' ), 'ما عادش تعاود تكتبو في الطلبية الجاية.' ),
			self::field( 'ben_4', __( 'Avantage 4 — titre', 'famma-core' ), __( 'Retrouvez votre sélection', 'famma-core' ), 'لقى الحوايج اللي عجبوك', __( 'Vide : l’avantage disparaît.', 'famma-core' ) ),
			self::field( 'ben_4_sub', __( 'Avantage 4 — détail', 'famma-core' ), __( 'Les produits mis de côté vous attendent.', 'famma-core' ), 'الحوايج اللي خلّيتهم يستناو فيك.' ),
			self::field( 'alt_strong', __( 'Sans compte — phrase en gras', 'famma-core' ), __( 'Pas besoin de compte pour commander.', 'famma-core' ), 'ما تستحقّش حساب باش تطلب.', __( 'Encadré en bas de la carte marine. Vide (les trois lignes) : l’encadré disparaît.', 'famma-core' ) ),
			self::field( 'alt_text', __( 'Sans compte — suite', 'famma-core' ), __( 'Nom, téléphone, adresse : c’est tout.', 'famma-core' ), 'الاسم، التليفون، العنوان: هذا كل شي.' ),
			self::field( 'alt_link', __( 'Sans compte — lien vers la boutique', 'famma-core' ), __( 'Commander sans compte', 'famma-core' ), 'اطلب بلا حساب' ),
			self::field( 'tab_up', __( 'Onglet — créer un compte', 'famma-core' ), __( 'Créer un compte', 'famma-core' ), 'اعمل حساب', __( 'Premier onglet, ouvert d’office, comme dans la maquette.', 'famma-core' ) ),
			self::field( 'tab_in', __( 'Onglet — se connecter', 'famma-core' ), __( 'Me connecter', 'famma-core' ), 'ادخل لحسابي' ),
			self::field( 'up_title', __( 'Création — titre et bouton', 'famma-core' ), __( 'Créer mon compte', 'famma-core' ), 'نعمل حسابي', __( 'Titre du formulaire de création et texte de son bouton.', 'famma-core' ) ),
			self::field( 'up_sub', __( 'Création — phrase sous le titre', 'famma-core' ), '', '', __( 'La maquette dit « Trois champs. Le même numéro que pour vos commandes. » : à écrire seulement quand l’inscription par téléphone sera en place. Vide : la ligne disparaît.', 'famma-core' ) ),
			self::field( 'in_title', __( 'Connexion — titre du formulaire', 'famma-core' ), __( 'Content de vous revoir', 'famma-core' ), 'مرحبا بيك من جديد' ),
			self::field( 'in_button', __( 'Connexion — bouton', 'famma-core' ), __( 'Me connecter', 'famma-core' ), 'ادخل لحسابي' ),
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
	 * Les avantages remplis, dans l'ordre, avec leur icône.
	 *
	 * @return array<int, array{icon: string, title: string, sub: string}>
	 */
	public static function benefits(): array {
		$icons = array(
			1 => 'truck',
			2 => 'redo',
			3 => 'pin',
			4 => 'heart',
		);
		$out   = array();

		foreach ( $icons as $i => $icon ) {
			$title = self::text( 'ben_' . $i );

			if ( '' !== $title ) {
				$out[] = array(
					'icon'  => $icon,
					'title' => $title,
					'sub'   => self::text( 'ben_' . $i . '_sub' ),
				);
			}
		}

		return $out;
	}
}
