<?php
/**
 * Se connecter avec son numéro (lot 2 de l'espace client).
 *
 * Le champ « Téléphone ou e-mail » accepte le numéro sous toutes ses formes
 * (« 20 123 456 », « +216… », chiffres arabes) ; l'e-mail et l'identifiant
 * restent acceptés par WordPress. Actif case cochée ou non : les comptes
 * déjà créés par numéro doivent pouvoir se connecter.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Connexion par numéro.
 */
final class Account_Phone_Login {

	/**
	 * Connexions par numéro tentées par adresse IP en 10 minutes.
	 */
	private const LIMIT = 20;

	/**
	 * Pose le crochet, après la vérification de WordPress (priorité 20) :
	 * un identifiant ou un e-mail reconnus par WordPress passent tels quels.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_filter( 'authenticate', array( self::class, 'authenticate' ), 25, 3 );
	}

	/**
	 * Utilisateur d'un numéro : la méta `_famma_phone` d'abord, puis le
	 * téléphone de facturation (comptes créés avant, par e-mail), s'il est le
	 * seul à l'avoir.
	 *
	 * @param string $phone 8 chiffres.
	 * @return \WP_User|null
	 */
	public static function find_user( string $phone ): ?\WP_User {
		if ( '' === $phone ) {
			return null;
		}

		$users = get_users(
			array(
				'meta_key'   => Account_Phone::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- recherche ponctuelle, à l'inscription ou à la connexion.
				'meta_value' => $phone, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 2,
			)
		);

		if ( 1 === count( $users ) ) {
			return $users[0];
		}

		if ( count( $users ) > 1 ) {
			return null;
		}

		$users = get_users(
			array(
				'meta_key'     => 'billing_phone', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'   => array( $phone, '216' . $phone, '+216' . $phone, '00216' . $phone ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_compare' => 'IN',
				'number'       => 2,
			)
		);

		return 1 === count( $users ) ? $users[0] : null;
	}

	/**
	 * Connexion avec le numéro, quand WordPress n'a reconnu ni identifiant
	 * ni e-mail. Mauvais mot de passe : un message clair, sans l'identifiant
	 * fabriqué par WooCommerce.
	 *
	 * @param \WP_User|\WP_Error|null $user     Résultat jusque-là.
	 * @param string                  $username Saisie du champ identifiant.
	 * @param string                  $password Mot de passe.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function authenticate( $user, $username, $password ) {
		if ( $user instanceof \WP_User || ! is_string( $username ) || '' === $username || '' === (string) $password || is_email( $username ) ) {
			return $user;
		}

		if ( $user instanceof \WP_Error && ! in_array( $user->get_error_code(), array( 'invalid_username', 'invalid_email' ), true ) ) {
			return $user;
		}

		$phone = Tunisia::normalize_phone( $username );

		if ( '' === $phone || Visitor_Context::rate_limited( 'famma_tel_login_', self::LIMIT ) ) {
			return $user;
		}

		$found = self::find_user( $phone );

		if ( ! $found instanceof \WP_User || $found->user_login === $username ) {
			return $user;
		}

		$result = wp_authenticate_username_password( null, $found->user_login, (string) $password );

		if ( $result instanceof \WP_User ) {
			return $result;
		}

		$text = Account_Phone_Texts::text( 'err_login' );

		return new \WP_Error( 'incorrect_password', '' !== $text ? $text : __( 'Numéro ou mot de passe incorrect.', 'famma-core' ) );
	}
}
