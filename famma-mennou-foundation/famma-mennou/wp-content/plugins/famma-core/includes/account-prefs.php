<?php
/**
 * Boîte « Préférences » de « Mes informations » — case `acct_prefs` (lot 6
 * de l'espace client).
 *
 * - « Langue de mes messages » (FR / عربي) : notée sur le compte pour que
 *   l'équipe appelle et écrive dans la bonne langue ; la page bascule aussi
 *   dans cette langue, comme la maquette.
 * - « Nouveautés sur WhatsApp » : refusé par défaut. Montré seulement quand
 *   le propriétaire a écrit le texte de consentement (décision D5). À
 *   l'inscription, la date et le texte accepté sont gardés sur le compte ;
 *   à la désinscription, ils sont effacés. Le site n'envoie aucun message :
 *   la liste sert à l'équipe (Comptes → « Nouveautés WhatsApp »).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lot 6 côté client.
 */
final class Account_Prefs {

	public const LANG  = '_famma_msg_lang';
	public const WA    = '_famma_wa_optin';
	public const NONCE = 'famma_prefs';

	/**
	 * Pose les crochets.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_action( 'template_redirect', array( self::class, 'handle' ), 5 );
		Account_Prefs_Admin::hooks();
	}

	/**
	 * Boîte montrée au client connecté ? Elle vit dans « Mes informations ».
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return is_user_logged_in()
			&& New_Look::part( 'acct_prefs' )
			&& New_Look::part( 'acct_look' )
			&& New_Look::part( 'acct_forms' );
	}

	/**
	 * Interrupteur WhatsApp montré ? Il attend le texte du propriétaire.
	 *
	 * @return bool
	 */
	public static function wa_active(): bool {
		return self::active() && '' !== Account_Prefs_Texts::text( 'pf_wa_s' );
	}

	/**
	 * Langue choisie par le client (`fr`, `ar`), ou vide.
	 *
	 * @param int $user_id Client.
	 * @return string
	 */
	public static function lang( int $user_id ): string {
		$lang = (string) get_user_meta( $user_id, self::LANG, true );

		return in_array( $lang, array( 'fr', 'ar' ), true ) ? $lang : '';
	}

	/**
	 * Inscription aux nouveautés WhatsApp, ou null.
	 *
	 * @param int $user_id Client.
	 * @return array{at: string, text: string}|null
	 */
	public static function wa( int $user_id ): ?array {
		$wa = get_user_meta( $user_id, self::WA, true );

		if ( ! is_array( $wa ) || empty( $wa['at'] ) ) {
			return null;
		}

		return array(
			'at'   => (string) $wa['at'],
			'text' => (string) ( $wa['text'] ?? '' ),
		);
	}

	/**
	 * Reçoit un choix de langue ou l'interrupteur WhatsApp. Avec script, la
	 * réponse est en JSON et la page ne se recharge pas ; sans script, la
	 * page est rechargée (dans la langue choisie pour la langue).
	 *
	 * @return void
	 */
	public static function handle(): void {
		if ( ! isset( $_POST['famma_prefs_do'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifié juste après.
			return;
		}

		$nonce = isset( $_POST['_famma_prefs_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_famma_prefs_nonce'] ) ) : '';
		$ajax  = ! empty( $_POST['famma_prefs_ajax'] );

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! self::active() || ! current_user_can( 'read' ) ) {
			if ( $ajax ) {
				wp_send_json_error( null, 403 );
			}

			return;
		}

		$user_id = get_current_user_id();
		$action  = sanitize_key( wp_unslash( $_POST['famma_prefs_do'] ) );
		$value   = isset( $_POST['famma_prefs_value'] ) ? sanitize_key( wp_unslash( $_POST['famma_prefs_value'] ) ) : '';
		$back    = wc_get_endpoint_url( 'edit-account', '', wc_get_page_permalink( 'myaccount' ) );
		$ok      = false;

		if ( 'lang' === $action && in_array( $value, array( 'fr', 'ar' ), true ) ) {
			$ok = self::save_lang( $user_id, $value );

			// Adresse de « Mes informations » dans la langue choisie (TranslatePress).
			$key  = '_famma_back_' . $value;
			$url  = isset( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : '';
			$back = '' !== $url ? wp_validate_redirect( $url, $back ) : $back;
		} elseif ( 'wa' === $action && self::wa_active() ) {
			$ok = self::save_wa( $user_id, '1' === $value );
		}

		if ( $ajax ) {
			if ( $ok ) {
				wp_send_json_success( array( 'wa' => null !== self::wa( $user_id ) ) );
			}

			wp_send_json_error( array( 'message' => Account_Prefs_Texts::text( 'pf_err' ) ) );
		}

		if ( ! $ok ) {
			wc_add_notice( Account_Prefs_Texts::text( 'pf_err' ), 'error' );
		}

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Enregistre la langue des messages.
	 *
	 * @param int    $user_id Client.
	 * @param string $lang    `fr` ou `ar`.
	 * @return bool
	 */
	private static function save_lang( int $user_id, string $lang ): bool {
		if ( self::lang( $user_id ) === $lang ) {
			return true;
		}

		return false !== update_user_meta( $user_id, self::LANG, $lang );
	}

	/**
	 * Inscrit (date et texte accepté) ou désinscrit des nouveautés WhatsApp.
	 *
	 * @param int  $user_id Client.
	 * @param bool $on      Inscription.
	 * @return bool
	 */
	private static function save_wa( int $user_id, bool $on ): bool {
		if ( ! $on ) {
			Account_Prefs_Admin::forget_count();

			return null === self::wa( $user_id ) || delete_user_meta( $user_id, self::WA );
		}

		if ( null !== self::wa( $user_id ) ) {
			return true;
		}

		$saved = update_user_meta(
			$user_id,
			self::WA,
			array(
				'at'   => current_time( 'mysql', true ),
				'text' => Account_Prefs_Texts::text( 'pf_wa_s' ),
			)
		);

		Account_Prefs_Admin::forget_count();

		return false !== $saved;
	}
}
