<?php
/**
 * Parcours visiteurs — vue d'administration (FAMMA → Parcours visiteurs).
 *
 * Lecture seule pour l'essentiel : indicateurs, entonnoir en sessions, champs
 * du formulaire où les visiteurs décrochent, liste des sessions et chronologie
 * d'une session. Seul le réglage de la durée de conservation écrit, via
 * `admin-post.php` avec nonce et capacité.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, rendu et réglage de conservation.
 */
final class Visitor_Admin {

	/**
	 * Identifiant de la page.
	 */
	private const SLUG = 'famma-visitors';

	/**
	 * Action admin-post du réglage de conservation.
	 */
	private const SAVE = 'famma_visitor_retention';

	/**
	 * Périodes proposées, en jours.
	 */
	private const PERIODS = array( 1, 7, 30, 90, 180 );

	/**
	 * Suffixe de la page renvoyé par WordPress.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Instance partagée.
	 *
	 * @var Visitor_Admin|null
	 */
	private static ?Visitor_Admin $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Visitor_Admin
	 */
	public static function instance(): Visitor_Admin {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les points d'accroche.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
		add_action( 'admin_post_' . self::SAVE, array( $this, 'save_retention' ) );
	}

	/**
	 * Ajoute l'entrée sous le menu FAMMA.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$hook       = add_submenu_page(
			'famma-dashboard',
			__( 'FAMMA — parcours visiteurs', 'famma-core' ),
			__( 'Parcours visiteurs', 'famma-core' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
		$this->hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Charge la feuille de style, sur cette page seulement.
	 *
	 * @param string $hook Page d'administration courante.
	 * @return void
	 */
	public function styles( string $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_style( 'famma-visitor-admin', plugins_url( 'assets/css/visitor-admin.css', FAMMA_CORE_FILE ), array(), FAMMA_CORE_VERSION );
	}

	/**
	 * Période choisie, limitée aux valeurs proposées.
	 *
	 * @return int
	 */
	private function days(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$days = isset( $_GET['days'] ) ? absint( wp_unslash( $_GET['days'] ) ) : 30;
		return in_array( $days, self::PERIODS, true ) ? $days : 30;
	}

	/**
	 * URL de la page avec des paramètres.
	 *
	 * @param array<string, int|string> $args Paramètres.
	 * @return string
	 */
	public function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Libellés lisibles des événements.
	 *
	 * @return array<string, string>
	 */
	public static function event_labels(): array {
		return array(
			'page_view'      => __( 'Page vue', 'famma-core' ),
			'page_leave'     => __( 'Sortie de page', 'famma-core' ),
			'add_to_cart'    => __( 'Ajout au panier', 'famma-core' ),
			'form_start'     => __( 'Formulaire commencé', 'famma-core' ),
			'form_field'     => __( 'Champ touché', 'famma-core' ),
			'form_submit'    => __( 'Formulaire envoyé', 'famma-core' ),
			'order_placed'   => __( 'Commande passée', 'famma-core' ),
			'click_whatsapp' => __( 'Clic WhatsApp', 'famma-core' ),
			'click_phone'    => __( 'Clic téléphone', 'famma-core' ),
			'click_outbound' => __( 'Lien sortant', 'famma-core' ),
		);
	}

	/**
	 * Affiche la page : chronologie d'une session, ou synthèse de la période.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selector.
		$session = isset( $_GET['session'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['session'] ) ) ) : '';
		echo '<div class="wrap famma-vj">';
		if ( Visitor_Context::is_uuid( $session ) ) {
			Visitor_Admin_Views::timeline( $session, $this->url( array( 'days' => $this->days() ) ) );
		} else {
			$this->overview();
		}
		echo '</div>';
	}

	/**
	 * Synthèse : période, indicateurs, entonnoir, formulaires, sessions.
	 *
	 * @return void
	 */
	private function overview(): void {
		$days   = $this->days();
		$report = new Visitor_Report();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

		echo '<h1>' . esc_html__( 'Parcours visiteurs', 'famma-core' ) . '</h1>';
		echo '<p class="famma-vj__periods">';
		foreach ( self::PERIODS as $period ) {
			printf(
				'<a href="%1$s" class="button%2$s">%3$s</a> ',
				esc_url( $this->url( array( 'days' => $period ) ) ),
				$period === $days ? ' button-primary' : '',
				/* translators: %d: number of days. */
				esc_html( sprintf( _n( '%d jour', '%d jours', $period, 'famma-core' ), $period ) )
			);
		}
		echo '</p>';

		Visitor_Admin_Views::summary( $report->summary( $days ) );
		Visitor_Admin_Views::forms( $report->form_fields( $days ) );
		Visitor_Admin_Views::sessions( $report->sessions( $days, $paged ), $days, $paged, array( $this, 'url' ) );
		$this->retention_form();
	}

	/**
	 * Formulaire du réglage de conservation.
	 *
	 * @return void
	 */
	private function retention_form(): void {
		echo '<h2>' . esc_html__( 'Conservation', 'famma-core' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="famma-vj__retention">';
		wp_nonce_field( self::SAVE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SAVE ) . '" />';
		echo '<label>' . esc_html__( 'Durée de conservation des événements (jours, 7 à 365)', 'famma-core' ) . ' ';
		echo '<input type="number" min="7" max="365" name="famma_retention_days" value="' . esc_attr( (string) Visitor_Store::instance()->retention_days() ) . '" /></label> ';
		submit_button( __( 'Enregistrer', 'famma-core' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Enregistre la durée de conservation.
	 *
	 * @return void
	 */
	public function save_retention(): void {
		check_admin_referer( self::SAVE );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ) );
		}
		$days = isset( $_POST['famma_retention_days'] ) ? absint( wp_unslash( $_POST['famma_retention_days'] ) ) : 180;
		update_option( Visitor_Store::RETENTION_OPTION, max( 7, min( 365, $days ) ), false );
		wp_safe_redirect( $this->url( array( 'saved' => 1 ) ) );
		exit;
	}
}
