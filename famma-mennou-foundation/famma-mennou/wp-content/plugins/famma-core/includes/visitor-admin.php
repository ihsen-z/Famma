<?php
/**
 * Parcours visiteurs — écran d'administration (FAMMA → Parcours visiteurs).
 *
 * Ce fichier ne fait que l'aiguillage : menu, feuille de style, période,
 * onglet ou parcours demandé. Les données viennent des classes de lecture
 * (`Visitor_Report`, `Visitor_Insights`, `Visitor_Performance`,
 * `Visitor_Form_Funnel`) et le balisage des classes `*_Views`.
 *
 * Organisation de l'écran (0.3.49) :
 * - Vue d'ensemble : indicateurs, entonnoir, qui sont les visiteurs ;
 * - Sessions : liste filtrable (type, appareil, source, adresse IP) ;
 * - Formulaires : où le formulaire express et le checkout décrochent ;
 * - Sources, Produits, Performance : analyses détaillées ;
 * - Réglages : conservation des événements et de l'adresse IP.
 *
 * Lecture seule, sauf l'onglet Réglages (`Visitor_Settings::save()`, nonce
 * et capacité).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, en-tête commun et aiguillage des onglets.
 */
final class Visitor_Admin {

	/**
	 * Identifiant de la page.
	 */
	public const SLUG = 'famma-visitors';

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
	}

	/**
	 * Onglets, dans l'ordre d'affichage : libellé et icône Dashicons.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function tabs(): array {
		return array(
			'overview' => array( __( 'Vue d\'ensemble', 'famma-core' ), 'dashboard' ),
			'sessions' => array( __( 'Sessions', 'famma-core' ), 'groups' ),
			'forms'    => array( __( 'Formulaires', 'famma-core' ), 'feedback' ),
			'sources'  => array( __( 'Sources', 'famma-core' ), 'megaphone' ),
			'products' => array( __( 'Produits', 'famma-core' ), 'products' ),
			'perf'     => array( __( 'Performance', 'famma-core' ), 'performance' ),
			'settings' => array( __( 'Réglages', 'famma-core' ), 'admin-generic' ),
		);
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
		wp_enqueue_style( 'famma-visitor-admin', plugins_url( 'assets/css/visitor-admin.css', FAMMA_CORE_FILE ), array( 'dashicons' ), FAMMA_CORE_VERSION );
	}

	/**
	 * Paramètre GET de lecture seule, assaini en texte.
	 *
	 * @param string $name Nom du paramètre.
	 * @return string
	 */
	private static function query_arg( string $name ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view parameters, nothing is written.
		return isset( $_GET[ $name ] ) && is_scalar( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $name ] ) ) : '';
	}

	/**
	 * Période choisie, limitée aux valeurs proposées.
	 *
	 * @return int
	 */
	public function days(): int {
		$days = absint( self::query_arg( 'days' ) );
		return in_array( $days, self::PERIODS, true ) ? $days : 30;
	}

	/**
	 * Libellé court d'une période (« 24 h », « 7 j »…).
	 *
	 * @param int $days Nombre de jours.
	 * @return string
	 */
	public static function period_label( int $days ): string {
		if ( 1 === $days ) {
			return __( '24 h', 'famma-core' );
		}
		/* translators: %d: number of days. */
		return sprintf( __( '%d j', 'famma-core' ), $days );
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
	 * Affiche la page : parcours d'une session, ou onglet demandé.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ) );
		}
		$days    = $this->days();
		$session = strtolower( self::query_arg( 'session' ) );
		echo '<div class="wrap famma-vj">';
		if ( Visitor_Context::is_uuid( $session ) ) {
			Visitor_Timeline_Views::render( $session, $days );
			echo '</div>';
			return;
		}
		$tab = sanitize_key( self::query_arg( 'tab' ) );
		$tab = isset( self::tabs()[ $tab ] ) ? $tab : 'overview';
		$this->header( $tab, $days );
		echo '<div class="famma-vj__body">';
		$this->tab( $tab, $days );
		echo '</div></div>';
	}

	/**
	 * En-tête commun : titre, état de l'IP, période et onglets.
	 *
	 * @param string $tab  Onglet courant.
	 * @param int    $days Période.
	 * @return void
	 */
	private function header( string $tab, int $days ): void {
		echo '<header class="famma-vj__header"><div class="famma-vj__title">';
		echo '<h1>' . esc_html__( 'Parcours visiteurs', 'famma-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Ce que font les visiteurs sur le site, de l\'arrivée à la commande.', 'famma-core' ) . '</p></div>';

		if ( 'settings' !== $tab ) {
			echo '<nav class="famma-vj__periods" aria-label="' . esc_attr__( 'Période', 'famma-core' ) . '">';
			foreach ( self::PERIODS as $period ) {
				$args = array(
					'tab'  => $tab,
					'days' => $period,
				);
				foreach ( Visitor_Report::FILTERS as $key => $empty ) {
					$value = self::query_arg( $key );
					if ( $empty !== $value ) {
						$args[ $key ] = $value;
					}
				}
				printf(
					'<a href="%1$s" class="famma-vj__period%2$s"%3$s>%4$s</a>',
					esc_url( $this->url( $args ) ),
					$period === $days ? ' is-active' : '',
					$period === $days ? ' aria-current="true"' : '',
					esc_html( self::period_label( $period ) )
				);
			}
			echo '</nav>';
		}
		echo '</header>';

		echo '<nav class="famma-vj__tabs" aria-label="' . esc_attr__( 'Sections', 'famma-core' ) . '">';
		foreach ( self::tabs() as $key => $meta ) {
			printf(
				'<a href="%1$s" class="famma-vj__tab%2$s"%3$s><span class="dashicons dashicons-%4$s" aria-hidden="true"></span>%5$s</a>',
				esc_url(
					$this->url(
						array(
							'tab'  => $key,
							'days' => $days,
						)
					)
				),
				$key === $tab ? ' is-active' : '',
				$key === $tab ? ' aria-current="page"' : '',
				esc_attr( $meta[1] ),
				esc_html( $meta[0] )
			);
		}
		echo '</nav>';
	}

	/**
	 * Contenu d'un onglet.
	 *
	 * @param string $tab  Onglet.
	 * @param int    $days Période.
	 * @return void
	 */
	private function tab( string $tab, int $days ): void {
		switch ( $tab ) {
			case 'sessions':
				$raw = array();
				foreach ( array_keys( Visitor_Report::FILTERS ) as $key ) {
					$raw[ $key ] = self::query_arg( $key );
				}
				$paged = max( 1, absint( self::query_arg( 'paged' ) ) );
				Visitor_Sessions_Views::render( new Visitor_Report(), $days, $paged, Visitor_Report::clean_filters( $raw ) );
				break;
			case 'forms':
				Visitor_Form_Funnel_Views::render( ( new Visitor_Form_Funnel() )->express( $days ), ( new Visitor_Report() )->form_fields( $days ) );
				break;
			case 'sources':
				Visitor_Insights_Views::sources( ( new Visitor_Insights() )->sources( $days ) );
				break;
			case 'products':
				$insights = new Visitor_Insights();
				Visitor_Insights_Views::products( $insights->products( $days ), $insights->variations( $days ) );
				break;
			case 'perf':
				$perf = new Visitor_Performance();
				Visitor_Performance_Views::render( $perf->vitals( $days ), $perf->errors( $days ) );
				break;
			case 'settings':
				Visitor_Settings_Views::render( '' !== self::query_arg( 'saved' ) );
				break;
			default:
				Visitor_Overview_Views::render( ( new Visitor_Report() )->summary( $days ), Visitor_Session_Type::counts( $days ), $days );
		}
	}
}
