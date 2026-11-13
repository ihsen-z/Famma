<?php
/**
 * Téléphone dans « Mes informations » — avec la case `acct_addrs`.
 *
 * La feuille d'adresse de la maquette ne demande plus le téléphone : il se
 * modifie dans la boîte « Identité », comme dans la maquette. Le champ est
 * dessiné par le thème (`famma_id_tel`) ; ici, le contrôle et
 * l'enregistrement, après ceux de WooCommerce (jeton déjà vérifié par
 * `WC_Form_Handler::save_account_details()`).
 *
 * Le numéro doit être tunisien et ne pas appartenir à un autre compte. Pour
 * un compte créé par numéro, la méta `_famma_phone` suit.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Téléphone de la boîte « Identité ».
 */
final class Account_Identity_Phone {

	/**
	 * Champ du formulaire.
	 */
	public const FIELD = 'famma_id_tel';

	/**
	 * Pose les crochets de WooCommerce.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_action( 'woocommerce_save_account_details_errors', array( self::class, 'check' ), 10, 2 );
		add_action( 'woocommerce_save_account_details', array( self::class, 'save' ) );
	}

	/**
	 * Saisie brute, null si le champ n'a pas été envoyé.
	 *
	 * @return string|null
	 */
	private static function raw(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce a vérifié son jeton avant ces crochets.
		if ( ! isset( $_POST[ self::FIELD ] ) || ! Account_Addresses::active() ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- idem.
		return sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) );
	}

	/**
	 * Numéro envoyé (8 chiffres), vide s'il est invalide, null si le champ
	 * n'a pas été envoyé.
	 *
	 * @return string|null
	 */
	private static function posted(): ?string {
		$raw = self::raw();

		return null === $raw ? null : Tunisia::normalize_phone( $raw );
	}

	/**
	 * Numéro invalide ou déjà pris par un autre compte : refusé.
	 *
	 * @param \WP_Error $errors Erreurs de WooCommerce.
	 * @param mixed     $user   Client (objet de WooCommerce).
	 * @return void
	 */
	public static function check( $errors, $user ): void {
		$phone = self::posted();

		if ( null === $phone || ! $errors instanceof \WP_Error ) {
			return;
		}

		if ( '' === $phone ) {
			$digits = strlen( (string) preg_replace( '/\D+/', '', (string) self::raw() ) );
			$errors->add( self::FIELD, Account_Phone_Texts::text( 8 === $digits ? 'err_tel_tn' : 'err_tel' ) );
			return;
		}

		$owner = Account_Phone_Login::find_user( $phone );
		$me    = is_object( $user ) && isset( $user->ID ) ? (int) $user->ID : get_current_user_id();

		if ( null !== $owner && (int) $owner->ID !== $me ) {
			$errors->add( self::FIELD, Account_Addresses_Texts::text( 'id_tel_taken' ) );
		}
	}

	/**
	 * Enregistre le numéro accepté.
	 *
	 * @param int $user_id Client.
	 * @return void
	 */
	public static function save( $user_id ): void {
		$phone = self::posted();

		if ( null === $phone || '' === $phone ) {
			return;
		}

		$customer = new \WC_Customer( (int) $user_id );
		$customer->set_billing_phone( $phone );
		$customer->save();

		if ( '' !== (string) get_user_meta( (int) $user_id, Account_Phone::META, true ) ) {
			update_user_meta( (int) $user_id, Account_Phone::META, $phone );
		}
	}
}
