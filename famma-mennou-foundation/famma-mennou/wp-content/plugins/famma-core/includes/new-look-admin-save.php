<?php
/**
 * Écran FAMMA → Nouveau look : enregistrement.
 *
 * Séquence du projet : jeton, capacité, assainissement, puis écriture par
 * `update_option()`, qui repasse chaque option par l'assainissement déclaré
 * à `register_setting()` (`New_Look::sanitize()`, `Pages_Content`).
 *
 * Seuls les champs envoyés changent : le script n'envoie que les textes
 * modifiés, et un texte absent de la requête garde sa valeur. Les textes sont
 * fusionnés dans l'option existante, jamais remplacés en bloc.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Traitement du formulaire « Nouveau look ».
 */
final class New_Look_Admin_Save {

	/**
	 * Enregistre puis revient à l'écran, sur l'onglet d'où l'on vient.
	 *
	 * @return void
	 */
	public static function handle(): void {
		if ( ! current_user_can( New_Look_Admin::CAP ) ) {
			wp_die( esc_html__( 'Vous n’avez pas le droit de modifier ces réglages.', 'famma-core' ), 403 );
		}

		check_admin_referer( New_Look_Admin::ACTION );

		self::save_switches();
		if ( self::save_texts() ) {
			// Le changement du mode ou d'une case vide déjà le cache (New_Look) ;
			// un texte seul doit le faire aussi, sinon le client lit l'ancien.
			do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API publique de LiteSpeed Cache, sans effet si le plugin est absent.
		}

		$tab = isset( $_POST['famma_tab'] ) ? sanitize_key( wp_unslash( $_POST['famma_tab'] ) ) : 'site';
		$tab = in_array( $tab, New_Look_Registry::pages(), true ) ? $tab : 'site';

		wp_safe_redirect( New_Look_Admin::url( array( 'famma_saved' => '1' ) ) . '#' . $tab );
		exit;
	}

	/**
	 * Mode et cases.
	 *
	 * Sans mode dans la requête (formulaire tronqué), rien n'est écrit : une
	 * requête incomplète ne doit jamais éteindre tout le nouveau look.
	 *
	 * @return void
	 */
	private static function save_switches(): void {
		// Jeton vérifié dans handle().
		if ( ! isset( $_POST[ New_Look::OPTION ] ) || ! is_array( $_POST[ New_Look::OPTION ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$sent = map_deep( wp_unslash( $_POST[ New_Look::OPTION ] ), 'sanitize_text_field' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $sent['mode'] ) ) {
			return;
		}

		update_option( New_Look::OPTION, $sent );
	}

	/**
	 * Textes FR + derja, page par page, fusionnés dans leur option.
	 *
	 * @return bool Une option de textes a-t-elle changé ?
	 */
	private static function save_texts(): bool {
		$changed = false;

		foreach ( Pages_Content::MOVED as $page ) {
			$option = Pages_Content::option_for( $page );

			// Jeton vérifié dans handle().
			if ( ! isset( $_POST[ $option ] ) || ! is_array( $_POST[ $option ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				continue;
			}

			$sent   = map_deep( wp_unslash( $_POST[ $option ] ), 'sanitize_textarea_field' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$stored = get_option( $option, array() );
			$stored = is_array( $stored ) ? $stored : array();
			$before = $stored;

			foreach ( Pages_Content::schema( $page ) as $field ) {
				foreach ( array( 'fr', 'ar' ) as $lang ) {
					$name = $field['key'] . '_' . $lang;
					if ( ! isset( $sent[ $name ] ) || ! is_string( $sent[ $name ] ) ) {
						continue;
					}

					$stored[ $name ] = 'textarea' === ( $field['type'] ?? 'text' )
						? $sent[ $name ]
						: sanitize_text_field( $sent[ $name ] );
				}
			}

			if ( $stored === $before ) {
				continue;
			}

			// L'assainissement déclaré (Pages_Content::sanitize_page) retire les
			// barres obliques : on les ajoute pour que le texte arrive intact.
			update_option( $option, wp_slash( $stored ) );
			$changed = true;
		}

		return $changed;
	}
}
