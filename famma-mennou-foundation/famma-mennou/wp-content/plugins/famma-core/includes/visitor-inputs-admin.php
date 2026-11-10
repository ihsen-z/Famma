<?php
/**
 * Saisies des visiteurs — menu, actions et réponses AJAX de la page.
 *
 * FAMMA → Saisies à relancer : liste des formulaires remplis sans commande.
 * Le statut et la remarque se modifient sur place (AJAX) ; sans JavaScript,
 * les boutons de statut restent des formulaires classiques. L'export vit dans
 * `Visitor_Inputs_Export`, la création de commande dans
 * `Visitor_Inputs_Order`, le rendu dans `Visitor_Inputs_Views`.
 *
 * Chaque action écrit : nonce, puis `manage_woocommerce`, puis entrées
 * assainies (règle 4).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, statut, remarque, actualisation, réglages, scripts de la page.
 */
final class Visitor_Inputs_Admin {

	/**
	 * Identifiant de la page.
	 */
	public const SLUG = 'famma-visitor-inputs';

	/**
	 * Action admin-post : changer le statut d'une ligne (sans JavaScript).
	 */
	public const ACTION_STATUS = 'famma_inputs_status';

	/**
	 * Action admin-post : enregistrer les réglages.
	 */
	public const ACTION_SETTINGS = 'famma_inputs_settings';

	/**
	 * Action AJAX : statut ou remarque d'une ligne.
	 */
	public const AJAX_UPDATE = 'famma_inputs_update';

	/**
	 * Action AJAX : nouvelles saisies depuis l'ouverture de la page.
	 */
	public const AJAX_POLL = 'famma_inputs_poll';

	/**
	 * Crochet de la page, connu après `admin_menu`.
	 *
	 * @var string
	 */
	private string $hook = '';

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
			add_action( 'admin_enqueue_scripts', array( self::$i, 'assets' ) );
			add_action( 'admin_post_' . self::ACTION_STATUS, array( self::$i, 'change_status' ) );
			add_action( 'admin_post_' . self::ACTION_SETTINGS, array( self::$i, 'save_settings' ) );
			add_action( 'wp_ajax_' . self::AJAX_UPDATE, array( self::$i, 'ajax_update' ) );
			add_action( 'wp_ajax_' . self::AJAX_POLL, array( self::$i, 'ajax_poll' ) );
		}
		return self::$i;
	}

	/**
	 * Ajoute l'entrée sous le menu FAMMA, avec le nombre de lignes à relancer.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$open       = Visitor_Inputs_Rows::open_count();
		$badge      = $open > 0 ? ' <span class="awaiting-mod">' . (int) $open . '</span>' : '';
		$this->hook = (string) add_submenu_page(
			'famma-dashboard',
			__( 'FAMMA — saisies à relancer', 'famma-core' ),
			esc_html__( 'Saisies à relancer', 'famma-core' ) . $badge,
			'manage_woocommerce',
			self::SLUG,
			array( Visitor_Inputs_Views::class, 'render' )
		);
	}

	/**
	 * Styles et script de la page, sur elle seule.
	 *
	 * @param string $hook Page d'administration courante.
	 * @return void
	 */
	public function assets( string $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_style( 'famma-inputs-admin', plugins_url( 'assets/css/visitor-inputs-admin.css', FAMMA_CORE_FILE ), array(), FAMMA_CORE_VERSION );
		wp_enqueue_script( 'famma-inputs-admin', plugins_url( 'assets/js/visitor-inputs-admin.js', FAMMA_CORE_FILE ), array(), FAMMA_CORE_VERSION, true );
		wp_localize_script(
			'famma-inputs-admin',
			'fammaInputs',
			array(
				'url'    => admin_url( 'admin-ajax.php' ),
				'update' => self::AJAX_UPDATE,
				'poll'   => self::AJAX_POLL,
				'nonce'  => wp_create_nonce( self::AJAX_UPDATE ),
				'since'  => gmdate( 'Y-m-d H:i:s' ),
				// Mêmes filtres que la page : les compteurs renvoyés restent cohérents.
				'scope'  => array_diff_key( Visitor_Inputs_Rows::filters( $_GET ), array( 'status' => 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selectors.
				'i18n'   => array(
					'saved'   => __( 'Enregistré', 'famma-core' ),
					'error'   => __( 'Échec, réessayez.', 'famma-core' ),
					'confirm' => __( 'Supprimer définitivement cette saisie ?', 'famma-core' ),
					'order'   => __( 'Créer la commande WooCommerce avec ces données ? Faites-le seulement après avoir joint le client.', 'famma-core' ),
					/* translators: %d: number of new entries. */
					'fresh'   => __( '%d nouvelle(s) saisie(s) — actualiser', 'famma-core' ),
				),
			)
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
		$back   = Visitor_Inputs_Rows::filters( isset( $_POST['back'] ) && is_array( $_POST['back'] ) ? $_POST['back'] : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- filters() unslashes and sanitizes each key.
		$ok     = $id > 0 && Visitor_Inputs_Rows::set_status( $id, $status );
		wp_safe_redirect( self::url( array_filter( $back ) + array( 'done' => $ok ? 1 : 0 ) ) );
		exit;
	}

	/**
	 * AJAX : change le statut ou la remarque d'une ligne.
	 *
	 * @return void
	 */
	public function ajax_update(): void {
		check_ajax_referer( self::AJAX_UPDATE, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( null, 403 );
		}
		$id    = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$field = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		if ( $id <= 0 || null === Visitor_Inputs_Rows::get( $id ) ) {
			wp_send_json_error( null, 404 );
		}
		if ( 'note' === $field ) {
			$note = Visitor_Inputs_Rows::set_note( $id, isset( $_POST['value'] ) ? (string) wp_unslash( $_POST['value'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in set_note().
			if ( null === $note ) {
				wp_send_json_error();
			}
			wp_send_json_success( array( 'value' => $note ) );
		}
		$status = isset( $_POST['value'] ) ? sanitize_key( wp_unslash( $_POST['value'] ) ) : '';
		if ( 'status' !== $field || ! Visitor_Inputs_Rows::set_status( $id, $status ) ) {
			wp_send_json_error();
		}
		wp_send_json_success(
			array(
				'value'  => $status,
				'counts' => Visitor_Inputs_Rows::counts( Visitor_Inputs_Rows::filters( $_POST ) ),
			)
		);
	}

	/**
	 * AJAX : nombre de saisies arrivées ou complétées depuis une date.
	 *
	 * @return void
	 */
	public function ajax_poll(): void {
		check_ajax_referer( self::AJAX_UPDATE, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( null, 403 );
		}
		$since = isset( $_POST['since'] ) ? sanitize_text_field( wp_unslash( $_POST['since'] ) ) : '';
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since ) ) {
			wp_send_json_error();
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- small admin counter on a custom table.
		$fresh = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s AND created_at > %s', Visitor_Inputs::instance()->table(), 'open', $since ) );
		wp_send_json_success( array( 'fresh' => $fresh ) );
	}

	/**
	 * Enregistre les réglages : capture et durée de conservation.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		check_admin_referer( self::ACTION_SETTINGS );
		$this->guard();
		$enabled = isset( $_POST['enabled'] ) ? '1' : '0';
		$days    = isset( $_POST['days'] ) ? absint( wp_unslash( $_POST['days'] ) ) : 30;
		update_option( Visitor_Inputs::ENABLED_OPTION, $enabled, false );
		update_option( Visitor_Inputs::RETENTION_OPTION, max( 7, min( 180, $days ) ), false );
		wp_safe_redirect( self::url( array( 'saved' => 1 ) ) );
		exit;
	}
}
