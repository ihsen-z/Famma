<?php
/**
 * Adresse technique des comptes créés par numéro : jamais utilisée, jamais
 * montrée (lot 2 de l'espace client).
 *
 * - aucun e-mail ne part vers `@tel.famma.invalid` ;
 * - au paiement, le champ e-mail d'un client sans e-mail reste vide, et
 *   la commande n'en reçoit pas ;
 * - pas d'encart « Confirmez votre adresse e-mail » (WooCommerce 11) ;
 * - dans « Mes informations », le champ s'affiche vide, devient facultatif,
 *   et l'adresse technique est gardée si le client n'en donne pas.
 *
 * Actif case cochée ou non : ces comptes existent dès qu'un client s'est
 * inscrit par numéro.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Protections de l'adresse technique.
 */
final class Account_Phone_Guard {

	/**
	 * Pose les crochets.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_filter( 'pre_wp_mail', array( self::class, 'block_mail' ), 10, 2 );
		add_filter( 'woocommerce_checkout_get_value', array( self::class, 'checkout_email' ), 10, 2 );
		add_action( 'woocommerce_before_order_object_save', array( self::class, 'order_email' ) );
		add_action( 'template_redirect', array( self::class, 'keep_account_email' ), 9 );
		add_filter( 'woocommerce_save_account_details_required_fields', array( self::class, 'account_required' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'hide_account_email' ), 40 );
		add_filter( 'woocommerce_customer_email_verification_should_show_prompt', array( self::class, 'no_verify_prompt' ), 10, 2 );
	}

	/**
	 * WooCommerce 11 propose de « confirmer votre adresse e-mail » pour
	 * retrouver d'anciennes commandes : pas à un compte sans e-mail.
	 *
	 * @param bool $show    Encart montré.
	 * @param int  $user_id Client.
	 * @return bool
	 */
	public static function no_verify_prompt( $show, $user_id ) {
		$user = get_userdata( (int) $user_id );

		return $user && Account_Phone::is_technical( (string) $user->user_email ) ? false : $show;
	}

	/**
	 * Aucun message ne part vers une adresse technique.
	 *
	 * @param null|bool            $short Réponse d'un autre filtre.
	 * @param array<string, mixed> $atts  Destinataires et contenu.
	 * @return null|bool
	 */
	public static function block_mail( $short, $atts ) {
		if ( null !== $short || ! is_array( $atts ) ) {
			return $short;
		}

		$to = $atts['to'] ?? array();
		$to = is_array( $to ) ? $to : explode( ',', (string) $to );

		foreach ( $to as $address ) {
			if ( Account_Phone::is_technical( (string) $address ) ) {
				return false;
			}
		}

		return $short;
	}

	/**
	 * Au paiement, l'e-mail d'un client sans e-mail reste vide.
	 *
	 * @param mixed  $value Valeur proposée par un autre filtre.
	 * @param string $input Champ.
	 * @return mixed
	 */
	public static function checkout_email( $value, $input ) {
		if ( null !== $value || 'billing_email' !== $input || ! is_user_logged_in() ) {
			return $value;
		}

		$user = wp_get_current_user();

		return Account_Phone::is_technical( (string) $user->user_email ) ? '' : $value;
	}

	/**
	 * Commande d'un client sans e-mail : WooCommerce y recopie l'adresse du
	 * compte quand le champ est vide. Elle reste vide, comme pour un invité.
	 *
	 * @param \WC_Order $order Commande enregistrée.
	 * @return void
	 */
	public static function order_email( $order ): void {
		if ( $order instanceof \WC_Order && Account_Phone::is_technical( (string) $order->get_billing_email() ) ) {
			$order->set_billing_email( '' );
		}
	}

	/**
	 * « Mes informations » d'un client sans e-mail : champ laissé vide, il
	 * garde son adresse technique (WooCommerce refuse un e-mail vide).
	 *
	 * @return void
	 */
	public static function keep_account_email(): void {
		if ( ! isset( $_POST['save_account_details'] ) || ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce vérifie son jeton dans la même requête.
			return;
		}

		$nonce = isset( $_POST['save-account-details-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['save-account-details-nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'save_account_details' ) ) {
			return;
		}

		$user  = wp_get_current_user();
		$given = isset( $_POST['account_email'] ) ? sanitize_email( wp_unslash( $_POST['account_email'] ) ) : '';

		if ( '' === $given && Account_Phone::is_technical( (string) $user->user_email ) ) {
			$_POST['account_email'] = $user->user_email;
		}
	}

	/**
	 * E-mail facultatif dans « Mes informations » pour un client sans e-mail.
	 *
	 * @param array<string, string> $fields Champs obligatoires.
	 * @return array<string, string>
	 */
	public static function account_required( $fields ) {
		if ( is_array( $fields ) && is_user_logged_in() && Account_Phone::is_technical( (string) wp_get_current_user()->user_email ) ) {
			unset( $fields['account_email'] );
		}

		return $fields;
	}

	/**
	 * « Mes informations » : le champ e-mail d'un client sans e-mail s'affiche
	 * vide au lieu de l'adresse technique (le modèle de WooCommerce l'imprime).
	 *
	 * @return void
	 */
	public static function hide_account_email(): void {
		if ( ! is_user_logged_in() || ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'edit-account' ) ) {
			return;
		}

		if ( ! Account_Phone::is_technical( (string) wp_get_current_user()->user_email ) ) {
			return;
		}

		wp_register_script( 'famma-account-email', false, array(), FAMMA_CORE_VERSION, true );
		wp_enqueue_script( 'famma-account-email' );
		wp_add_inline_script( 'famma-account-email', '(function(){var e=document.getElementById("account_email");if(e){e.value="";e.removeAttribute("required");e.removeAttribute("aria-required");}})();' );
	}
}
