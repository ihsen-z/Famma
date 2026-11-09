<?php
/**
 * Saisies des visiteurs — page d'administration et actions.
 *
 * FAMMA → Saisies à relancer : liste des formulaires remplis sans commande,
 * avec téléphone cliquable et lien WhatsApp, statut (à relancer, appelé,
 * commandé, clos), lien vers le parcours de la session, export CSV pour une
 * audience personnalisée Meta (liste de clients), et réglages.
 *
 * Chaque action écrit : nonce, puis `manage_woocommerce`, puis entrées
 * assainies (règle 4).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, actions de statut, export CSV, réglages.
 */
final class Visitor_Inputs_Admin {

	/**
	 * Identifiant de la page.
	 */
	public const SLUG = 'famma-visitor-inputs';

	/**
	 * Action admin-post : changer le statut d'une ligne.
	 */
	public const ACTION_STATUS = 'famma_inputs_status';

	/**
	 * Action admin-post : export CSV.
	 */
	public const ACTION_EXPORT = 'famma_inputs_export';

	/**
	 * Action admin-post : enregistrer les réglages.
	 */
	public const ACTION_SETTINGS = 'famma_inputs_settings';

	/**
	 * Instance partagée.
	 *
	 * @var Visitor_Inputs_Admin|null
	 */
	private static ?Visitor_Inputs_Admin $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Visitor_Inputs_Admin
	 */
	public static function instance(): Visitor_Inputs_Admin {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_menu', array( self::$i, 'register_menu' ), 21 );
			add_action( 'admin_post_' . self::ACTION_STATUS, array( self::$i, 'change_status' ) );
			add_action( 'admin_post_' . self::ACTION_EXPORT, array( self::$i, 'export' ) );
			add_action( 'admin_post_' . self::ACTION_SETTINGS, array( self::$i, 'save_settings' ) );
		}
		return self::$i;
	}

	/**
	 * Ajoute l'entrée sous le menu FAMMA, avec le nombre de lignes à relancer.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$open  = Visitor_Inputs::instance()->counts()['open'];
		$badge = $open > 0 ? ' <span class="awaiting-mod">' . (int) $open . '</span>' : '';
		add_submenu_page(
			'famma-dashboard',
			__( 'FAMMA — saisies à relancer', 'famma-core' ),
			esc_html__( 'Saisies à relancer', 'famma-core' ) . $badge,
			'manage_woocommerce',
			self::SLUG,
			array( Visitor_Inputs_Views::class, 'render' )
		);
	}

	/**
	 * URL de la page avec des paramètres.
	 *
	 * @param array<string, int|string> $args Paramètres.
	 * @return string
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Vérifie le droit, sinon arrête (le nonce est vérifié juste avant).
	 *
	 * @return void
	 */
	private function guard(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Change le statut d'une ligne (ou la supprime), puis revient à la liste.
	 *
	 * @return void
	 */
	public function change_status(): void {
		check_admin_referer( self::ACTION_STATUS );
		$this->guard();
		$id     = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$status = isset( $_POST['to'] ) ? sanitize_key( wp_unslash( $_POST['to'] ) ) : '';
		$back   = isset( $_POST['back'] ) ? sanitize_key( wp_unslash( $_POST['back'] ) ) : 'open';
		$ok     = $id > 0 && Visitor_Inputs::instance()->set_status( $id, $status );
		wp_safe_redirect(
			self::url(
				array(
					'status' => $back,
					'done'   => $ok ? 1 : 0,
				)
			)
		);
		exit;
	}

	/**
	 * Export CSV pour une audience Meta « liste de clients » : téléphone au
	 * format international, prénom, gouvernorat, pays. Seules les lignes avec
	 * un numéro tunisien valide sortent.
	 *
	 * @return void
	 */
	public function export(): void {
		check_admin_referer( self::ACTION_EXPORT );
		$this->guard();
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'open';
		$rows   = Visitor_Inputs::instance()->rows( $status, 5000 );
		$states = function_exists( 'WC' ) ? (array) WC()->countries->get_states( 'TN' ) : array();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=famma-saisies-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			exit;
		}
		fputcsv( $out, array( 'phone', 'fn', 'st', 'country' ) );
		foreach ( $rows as $row ) {
			if ( '' === $row['phone_norm'] ) {
				continue;
			}
			// Préfixe « = - + @ » neutralisé : le fichier peut être ouvert dans un tableur.
			$name = preg_replace( '/^[=\-+@]+/', '', (string) strtok( $row['first_name'], ' ' ) );
			fputcsv( $out, array( '+216' . $row['phone_norm'], $name, $states[ $row['state'] ] ?? '', 'TN' ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream, not a file.
		exit;
	}

	/**
	 * Enregistre les réglages : capture, conservation, phrase d'accord.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		check_admin_referer( self::ACTION_SETTINGS );
		$this->guard();
		$enabled = isset( $_POST['enabled'] ) ? '1' : '0';
		$days    = isset( $_POST['days'] ) ? absint( wp_unslash( $_POST['days'] ) ) : 30;
		$notice  = isset( $_POST['notice'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notice'] ) ) : '';
		update_option( Visitor_Inputs::ENABLED_OPTION, $enabled, false );
		update_option( Visitor_Inputs::RETENTION_OPTION, max( 7, min( 180, $days ) ), false );
		update_option( Visitor_Inputs::NOTICE_OPTION, mb_substr( $notice, 0, 300 ), false );
		wp_safe_redirect( self::url( array( 'saved' => 1 ) ) );
		exit;
	}
}
