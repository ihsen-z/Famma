<?php
/**
 * Finitions de l'espace client — case `acct_finish` (lot 43, écarts relevés
 * en comparant la prod à la maquette espace client).
 *
 * Logique seulement ; le thème dessine (inc/new-look-account-finish.php) :
 *
 * - « Mes informations » : un seul champ « Nom et prénom » à la place de
 *   Prénom / Nom / Nom affiché de WooCommerce. Le nom saisi est découpé ici
 *   (premier mot = prénom, le reste = nom, le tout = nom affiché) avant
 *   l'enregistrement de WooCommerce, qui garde ses propres contrôles ;
 * - après « Me déconnecter », le retour sur « Mon compte » porte une marque
 *   qui fait afficher « Vous êtes déconnecté. » ;
 * - l'initiale du client, pour la pastille de l'icône compte ;
 * - les textes (FAMMA → Pages → « Mon compte »).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Finitions de l'espace client.
 */
final class Account_Finish {

	/**
	 * Clé de page, au sens de `Pages_Content`.
	 */
	public const PAGE = 'account';

	/**
	 * Champ « Nom et prénom » du formulaire « Mes informations ».
	 */
	public const NAME = 'famma_id_name';

	/**
	 * Marque ajoutée au retour après la déconnexion.
	 */
	public const OUT = 'famma_out';

	/**
	 * Pose les crochets.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_action( 'template_redirect', array( self::class, 'split_name' ), 9 );
		add_filter( 'woocommerce_save_account_details_required_fields', array( self::class, 'required' ), 20 );
		add_filter( 'woocommerce_logout_default_redirect_url', array( self::class, 'logout_url' ) );
	}

	/**
	 * Case cochée pour la personne en cours ?
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return New_Look::part( 'acct_finish' ) && New_Look::part( 'acct_look' );
	}

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
	 * Schéma des textes (FAMMA → Pages → « Mon compte »).
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		$case = __( 'Avec la case « Mon compte : finitions de la maquette ».', 'famma-core' );

		return array(
			self::field( 'fin_name', __( 'Informations — champ nom', 'famma-core' ), __( 'Nom et prénom', 'famma-core' ), 'الاسم واللقب', $case . ' ' . __( 'Remplace Prénom, Nom et Nom affiché de WooCommerce.', 'famma-core' ) ),
			self::field( 'fin_mail', __( 'Informations — champ e-mail', 'famma-core' ), __( 'E-mail', 'famma-core' ), 'الإيميل' ),
			self::field( 'fin_opt', __( 'Informations — mention facultatif', 'famma-core' ), __( '(facultatif)', 'famma-core' ), '(موش لازم)', __( 'Montrée à côté de l’e-mail pour un compte créé par numéro (l’e-mail n’y est pas obligatoire).', 'famma-core' ) ),
			self::field( 'fin_mail_h', __( 'Informations — aide sous l’e-mail', 'famma-core' ), __( 'Pour recevoir le récapitulatif de vos commandes.', 'famma-core' ), 'باش يوصلك ملخص الطلبيات.', __( 'Vide : l’aide disparaît.', 'famma-core' ) ),
			self::field( 'fin_pw', __( 'Accès — champ mot de passe', 'famma-core' ), __( 'Mot de passe', 'famma-core' ), 'كلمة السر', $case . ' ' . __( 'Inscription et connexion, sans astérisque.', 'famma-core' ) ),
			self::field( 'fin_ord_empty', __( 'Commandes — aucune commande', 'famma-core' ), __( 'Vous n’avez pas encore passé de commande. Vous payez à la livraison.', 'famma-core' ), 'مازلت ما عملت حتى طلبية. تخلّص كي توصلك.', $case . ' ' . __( 'Vide : le message de WooCommerce revient.', 'famma-core' ) ),
			self::field( 'fin_ord_go', __( 'Commandes — bouton vers la boutique', 'famma-core' ), __( 'Voir la boutique', 'famma-core' ), 'شوف الحانوت' ),
			self::field( 'fin_out', __( 'Déconnexion — message', 'famma-core' ), __( 'Vous êtes déconnecté.', 'famma-core' ), 'خرجت من حسابك.', $case . ' ' . __( 'Vide : aucun message.', 'famma-core' ) ),
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
	 * Découpe un nom complet : premier mot = prénom, le reste = nom.
	 *
	 * @param string $full Nom saisi.
	 * @return array{0: string, 1: string}
	 */
	public static function split( string $full ): array {
		$parts = preg_split( '/\s+/u', trim( $full ), 2 );
		$parts = is_array( $parts ) ? $parts : array();

		return array( (string) ( $parts[0] ?? '' ), trim( (string) ( $parts[1] ?? '' ) ) );
	}

	/**
	 * Nom complet affiché dans le champ « Nom et prénom ».
	 *
	 * @param \WP_User $user Client.
	 * @return string
	 */
	public static function full_name( \WP_User $user ): string {
		return trim( trim( (string) $user->first_name ) . ' ' . trim( (string) $user->last_name ) );
	}

	/**
	 * « Mes informations » envoyé avec « Nom et prénom » : prénom, nom et nom
	 * affiché de WooCommerce en sont tirés, avant son enregistrement (même
	 * requête, priorité 10).
	 *
	 * @return void
	 */
	public static function split_name(): void {
		if ( ! isset( $_POST['save_account_details'], $_POST[ self::NAME ] ) || ! is_user_logged_in() || ! self::active() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- jeton vérifié juste en dessous.
			return;
		}

		$nonce = isset( $_POST['save-account-details-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['save-account-details-nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'save_account_details' ) ) {
			return;
		}

		$full = (string) preg_replace( '/\s+/u', ' ', trim( sanitize_text_field( wp_unslash( $_POST[ self::NAME ] ) ) ) );

		list( $first, $last ) = self::split( $full );

		$_POST['account_first_name']   = $first;
		$_POST['account_last_name']    = $last;
		$_POST['account_display_name'] = $full;
	}

	/**
	 * Champs obligatoires : le nom peut tenir en un mot, et l'erreur d'un nom
	 * vide parle de « Nom et prénom ».
	 *
	 * @param array<string, string> $fields Champs obligatoires et leur libellé.
	 * @return array<string, string>
	 */
	public static function required( $fields ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- lecture seule ; WooCommerce a vérifié son jeton.
		if ( ! is_array( $fields ) || ! isset( $_POST[ self::NAME ] ) || ! self::active() ) {
			return $fields;
		}

		unset( $fields['account_last_name'], $fields['account_display_name'] );

		if ( isset( $fields['account_first_name'] ) ) {
			$label = self::text( 'fin_name' );

			$fields['account_first_name'] = '' !== $label ? $label : $fields['account_first_name'];
		}

		return $fields;
	}

	/**
	 * Retour après la déconnexion : « Mon compte » avec la marque.
	 *
	 * @param string $url Adresse de retour.
	 * @return string
	 */
	public static function logout_url( $url ) {
		if ( ! self::active() || '' === self::text( 'fin_out' ) ) {
			return $url;
		}

		return add_query_arg( self::OUT, '1', (string) $url );
	}

	/**
	 * La page suit-elle une déconnexion ?
	 *
	 * @return bool
	 */
	public static function logged_out(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple affichage d'un message, rien n'est modifié.
		$flag = isset( $_GET[ self::OUT ] ) ? sanitize_key( wp_unslash( $_GET[ self::OUT ] ) ) : '';

		return '1' === $flag && ! is_user_logged_in() && self::active();
	}

	/**
	 * Initiale du client (une lettre), vide si son nom n'en commence pas par
	 * une (compte sans nom : identifiant en chiffres).
	 *
	 * @param int $user_id Client.
	 * @return string
	 */
	public static function initial( int $user_id ): string {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return '';
		}

		$name = self::full_name( $user );
		$name = '' !== $name ? $name : trim( (string) $user->display_name );

		if ( ! preg_match( '/^\p{L}/u', $name ) ) {
			return '';
		}

		return mb_strtoupper( mb_substr( $name, 0, 1 ) );
	}
}
