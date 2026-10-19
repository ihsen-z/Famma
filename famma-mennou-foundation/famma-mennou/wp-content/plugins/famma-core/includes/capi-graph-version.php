<?php
/**
 * Échéancier de la version de l'API Graph (lot 5, R1, A1).
 *
 * L'API Conversions suit le calendrier de l'API Graph : chaque version est
 * retirée environ deux ans après sa sortie, et un appel dans une version
 * retirée risque une erreur franche (OrderPlaced et Purchase perdus, E1).
 * Config connaît les dates de fin publiées ; cette classe en tire :
 *
 * - un avertissement unique à J-60, masquable pour la version concernée ;
 * - un bandeau critique permanent si la version vaut le plafond codé ou
 *   moins après la date du filet (ou si sa date de fin est passée).
 *
 * Une version sans date connue ne déclenche rien.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Niveau d'alerte d'une version et avis d'administration.
 */
final class Capi_Graph_Version {

	/**
	 * Option : version dont l'avertissement J-60 a été masqué.
	 */
	private const DISMISSED = 'famma_meta_version_notice_dismissed';

	/**
	 * Instance unique.
	 *
	 * @var Capi_Graph_Version|null
	 */
	private static ?Capi_Graph_Version $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Graph_Version
	 */
	public static function instance(): Capi_Graph_Version {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_notices', array( self::$i, 'notices' ) );
			add_action( 'admin_post_famma_dismiss_version_notice', array( self::$i, 'dismiss' ) );
		}
		return self::$i;
	}

	/**
	 * Niveau d'alerte d'une version à une date.
	 *
	 * @param string $version Version vNN.N.
	 * @param int    $now     Horloge.
	 * @return array{level: string, end: string, days: int}
	 *               level : `critical`, `warning` ou `ok` ; days : jours restants, -1 sans date connue.
	 */
	public static function status( string $version, int $now ): array {
		$end      = (string) ( Config::META_GRAPH_VERSION_END_DATES[ $version ] ?? '' );
		$end_time = '' !== $end ? (int) strtotime( $end . ' 00:00:00 UTC' ) : 0;
		$days     = $end_time > 0 ? (int) floor( ( $end_time - $now ) / DAY_IN_SECONDS ) : -1;
		$floor    = version_compare( ltrim( $version, 'v' ), ltrim( Config::META_GRAPH_VERSION_CRITICAL_MAX, 'v' ), '<=' );
		$after    = $now >= (int) strtotime( Config::META_GRAPH_VERSION_CRITICAL_AFTER . ' 00:00:00 UTC' );
		if ( ( $floor && $after ) || ( $end_time > 0 && $now >= $end_time ) ) {
			$level = 'critical';
		} elseif ( $end_time > 0 && $now >= $end_time - Config::META_GRAPH_VERSION_NOTICE_DAYS * DAY_IN_SECONDS ) {
			$level = 'warning';
		} else {
			$level = 'ok';
		}
		return array(
			'level' => $level,
			'end'   => $end,
			'days'  => $days,
		);
	}

	/**
	 * Bandeau critique permanent, ou avertissement J-60 masquable.
	 *
	 * @return void
	 */
	public function notices(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$version = Config::instance()->meta_graph_version();
		$status  = self::status( $version, time() );
		if ( 'critical' === $status['level'] ) {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong></p></div>',
				esc_html(
					sprintf(
						/* translators: 1: Graph API version in use, 2: end date or "inconnue", 3: recommended version. */
						__( 'FAMMA : la version de l’API Meta %1$s est en fin de vie (fin : %2$s). OrderPlaced et Purchase risquent d’être refusés. Passez à %3$s dans FAMMA → Mesure.', 'famma-core' ),
						$version,
						'' !== $status['end'] ? $status['end'] : __( 'inconnue', 'famma-core' ),
						Config::META_GRAPH_VERSION_DEFAULT
					)
				)
			);
			return;
		}
		if ( 'warning' !== $status['level'] || get_option( self::DISMISSED, '' ) === $version ) {
			return;
		}
		$dismiss = wp_nonce_url( add_query_arg( 'version', rawurlencode( $version ), admin_url( 'admin-post.php?action=famma_dismiss_version_notice' ) ), 'famma_dismiss_version_notice' );
		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: 1: Graph API version in use, 2: end date, 3: days left. */
					__( 'FAMMA : la version de l’API Meta %1$s prend fin le %2$s (dans %3$d jours). Prévoyez la montée de version dans FAMMA → Mesure.', 'famma-core' ),
					$version,
					$status['end'],
					max( 0, $status['days'] )
				)
			),
			esc_url( $dismiss ),
			esc_html__( 'Masquer pour cette version', 'famma-core' )
		);
	}

	/**
	 * Masque l'avertissement J-60 pour la version en cours (nonce + capacité).
	 *
	 * @return void
	 */
	public function dismiss(): void {
		check_admin_referer( 'famma_dismiss_version_notice' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ), '', array( 'response' => 403 ) );
		}
		$version = isset( $_GET['version'] ) ? sanitize_text_field( wp_unslash( $_GET['version'] ) ) : '';
		if ( Config::valid_graph_version( $version ) ) {
			update_option( self::DISMISSED, $version, false );
		}
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}
}
