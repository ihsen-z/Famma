<?php
/**
 * Écran « Bienvenue » après l'inscription — case `acct_welcome` (espace
 * client, maquette #welcome).
 *
 * Quand un client crée son compte depuis « Mon compte » (formulaire
 * d'inscription, pas la commande), le compte est marqué ; la première page
 * du compte qui suit montre la mascotte, « Bienvenue, {prénom} ! » et « Votre
 * compte est prêt », puis laisse place au compte. La marque est retirée dès
 * l'affichage : l'écran n'apparaît qu'une fois.
 *
 * La ligne « commandes rattachées » de la maquette attend le lot 4 (code
 * envoyé au numéro, décision D2) : elle n'est pas reprise.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Écran « Bienvenue ».
 */
final class Account_Welcome {

	public const META = '_famma_welcome';

	/**
	 * Clé de page, au sens de `Pages_Content`.
	 */
	public const PAGE = 'account';

	/**
	 * Durée pendant laquelle l'écran reste dû (en secondes).
	 */
	private const TTL = DAY_IN_SECONDS;

	/**
	 * Pose les crochets.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_action( 'woocommerce_created_customer', array( self::class, 'mark' ), 20, 1 );
	}

	/**
	 * Case cochée pour la personne en cours ?
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return New_Look::part( 'acct_welcome' ) && New_Look::part( 'acct_look' );
	}

	/**
	 * Schéma des textes (FAMMA → Pages → « Mon compte »).
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		return array(
			array(
				'key'        => 'wel_t',
				'type'       => 'text',
				'label'      => __( 'Bienvenue — titre', 'famma-core' ),
				'help'       => __( 'Avec la case « Mon compte : écran Bienvenue après l’inscription ». {n} est remplacé par le prénom (sans prénom : « Bienvenue ! »).', 'famma-core' ),
				'default_fr' => __( 'Bienvenue, {n} !', 'famma-core' ),
				'default_ar' => 'مرحبا بيك يا {n}!',
			),
			array(
				'key'        => 'wel_p',
				'type'       => 'text',
				'label'      => __( 'Bienvenue — phrase', 'famma-core' ),
				'help'       => '',
				'default_fr' => __( 'Votre compte est prêt. On vous emmène dans votre espace.', 'famma-core' ),
				'default_ar' => 'الحساب متاعك حاضر. توا نهزّوك للمساحة متاعك.',
			),
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
	 * Marque le compte créé depuis le formulaire d'inscription de « Mon
	 * compte » (WooCommerce a déjà vérifié son jeton).
	 *
	 * @param int $customer_id Compte créé.
	 * @return void
	 */
	public static function mark( $customer_id ): void {
		if ( ! isset( $_POST['register'] ) || ! self::active() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- jeton vérifié par WooCommerce avant la création du compte.
			return;
		}

		update_user_meta( (int) $customer_id, self::META, time() );
	}

	/**
	 * Titre et phrase de l'écran dû au client, puis la marque est retirée ;
	 * null s'il n'y a rien à montrer.
	 *
	 * @param int $user_id Client.
	 * @return array{title: string, text: string}|null
	 */
	public static function take( int $user_id ): ?array {
		$at = (int) get_user_meta( $user_id, self::META, true );

		if ( $at <= 0 ) {
			return null;
		}

		delete_user_meta( $user_id, self::META );

		if ( time() - $at > self::TTL || ! self::active() ) {
			return null;
		}

		$user  = get_userdata( $user_id );
		$first = $user instanceof \WP_User ? trim( (string) $user->first_name ) : '';
		$title = self::text( 'wel_t' );
		$title = '' !== $first ? str_replace( '{n}', $first, $title ) : (string) preg_replace( '/[,،]?\s*\{n\}\s*/u', '', $title );

		return array(
			'title' => trim( $title ),
			'text'  => self::text( 'wel_p' ),
		);
	}
}
