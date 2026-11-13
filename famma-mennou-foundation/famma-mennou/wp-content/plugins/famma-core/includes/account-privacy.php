<?php
/**
 * Mot de passe oublié et suppression du compte — case `acct_privacy`
 * (lot 8 de l'espace client).
 *
 * Mot de passe oublié (décision D3) : sous « Me connecter », la maquette
 * propose un code SMS ; en attendant un prestataire, le client écrit sur
 * WhatsApp depuis le numéro de son compte et le propriétaire change le mot
 * de passe à la main (Comptes → Modifier). Le lien e-mail de WooCommerce
 * reste proposé pour les comptes qui ont une adresse.
 *
 * Suppression : « Oui, supprimer » crée une demande d'effacement WordPress
 * (Outils → Effacer les données personnelles), déjà confirmée puisque le
 * client est connecté : aucun e-mail n'est envoyé. Rien n'est effacé tant
 * que le propriétaire ne l'a pas validée ; le client peut l'annuler en
 * attendant. Les commandes suivent les réglages WooCommerce actuels.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lot 8 côté client.
 */
final class Account_Privacy {

	public const ACTION = 'remove_personal_data';
	public const META   = '_famma_del_request';
	public const NONCE  = 'famma_del';

	/**
	 * Pose les crochets.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_action( 'template_redirect', array( self::class, 'handle' ), 5 );
		Account_Privacy_Admin::hooks();
	}

	/**
	 * Aide « Mot de passe oublié » montrée sur la page de connexion ?
	 *
	 * @return bool
	 */
	public static function help_active(): bool {
		return New_Look::part( 'acct_privacy' ) && New_Look::part( 'acct_look' );
	}

	/**
	 * Boîte « Supprimer mon compte » montrée au client connecté ? Elle vit
	 * dans les boîtes de « Mes informations » et attend le texte du
	 * propriétaire.
	 *
	 * @return bool
	 */
	public static function delete_active(): bool {
		return is_user_logged_in()
			&& New_Look::part( 'acct_privacy' )
			&& New_Look::part( 'acct_look' )
			&& New_Look::part( 'acct_forms' )
			&& '' !== Account_Privacy_Texts::text( 'del_s' );
	}

	/**
	 * Lien WhatsApp préparé pour un mot de passe oublié, ou vide sans numéro.
	 *
	 * @return string
	 */
	public static function whatsapp_link(): string {
		return WhatsApp::link( Account_Privacy_Texts::text( 'fg_msg' ) );
	}

	/**
	 * Demande en cours du client, ou null.
	 *
	 * @param int $user_id Client.
	 * @return array{id: int, date: string}|null
	 */
	public static function pending( int $user_id ): ?array {
		$id   = (int) get_user_meta( $user_id, self::META, true );
		$post = $id > 0 ? get_post( $id ) : null;
		$user = get_userdata( $user_id );

		if ( ! $post instanceof \WP_Post || ! $user instanceof \WP_User ) {
			return null;
		}

		$ours = 'user_request' === $post->post_type
			&& self::ACTION === $post->post_name
			&& (int) $post->post_author === $user_id
			&& $post->post_title === $user->user_email
			&& in_array( $post->post_status, array( 'request-pending', 'request-confirmed' ), true );

		return $ours ? array(
			'id'   => $id,
			'date' => (string) $post->post_date,
		) : null;
	}

	/**
	 * Reçoit « Oui, supprimer » ou « Annuler ma demande », puis recharge
	 * « Mes informations ».
	 *
	 * @return void
	 */
	public static function handle(): void {
		if ( ! isset( $_POST['famma_del_do'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifié juste après.
			return;
		}

		$nonce = isset( $_POST['_famma_del_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_famma_del_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! self::delete_active() || ! current_user_can( 'read' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$action  = sanitize_key( wp_unslash( $_POST['famma_del_do'] ) );
		$ok      = 'cancel' === $action ? self::cancel( $user_id ) : self::request( $user_id );

		if ( 'cancel' === $action && $ok ) {
			wc_add_notice( Account_Privacy_Texts::text( 'del_cancelled' ), 'success' );
		} elseif ( ! $ok ) {
			wc_add_notice( Account_Privacy_Texts::text( 'del_err' ), 'error' );
		}

		wp_safe_redirect( wc_get_endpoint_url( 'edit-account', '', wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	/**
	 * Crée la demande d'effacement, déjà confirmée (client connecté).
	 *
	 * @param int $user_id Client.
	 * @return bool
	 */
	private static function request( int $user_id ): bool {
		if ( null !== self::pending( $user_id ) ) {
			return true;
		}

		$user = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User || ! function_exists( 'wp_create_user_request' ) ) {
			return false;
		}

		$id = wp_create_user_request( (string) $user->user_email, self::ACTION, array( 'source' => 'famma-account' ), 'confirmed' );

		if ( is_wp_error( $id ) || ! $id ) {
			return false;
		}

		update_user_meta( $user_id, self::META, (int) $id );
		Account_Privacy_Admin::forget_count();

		return true;
	}

	/**
	 * Annule la demande en cours du client.
	 *
	 * @param int $user_id Client.
	 * @return bool
	 */
	private static function cancel( int $user_id ): bool {
		$pending = self::pending( $user_id );

		delete_user_meta( $user_id, self::META );
		Account_Privacy_Admin::forget_count();

		return null === $pending || false !== wp_delete_post( $pending['id'], true );
	}
}
