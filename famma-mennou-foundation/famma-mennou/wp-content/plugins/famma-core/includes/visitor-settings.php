<?php
/**
 * Parcours visiteurs — réglages (onglet « Réglages » de l'écran).
 *
 * Trois réglages, tous modifiables depuis l'administration :
 *
 * - durée de conservation des événements (7 à 365 jours, 180 par défaut) ;
 * - enregistrement de l'adresse IP en clair (activé par défaut depuis la
 *   0.3.49, à la demande du propriétaire) ;
 * - durée de conservation de l'IP (1 à 365 jours, 90 par défaut).
 *
 * ── L'IP en clair, et pourquoi elle a sa propre durée ──────────────────────
 * Une IP complète est une donnée personnelle : avec le fournisseur d'accès,
 * elle remonte à un abonné. Elle sert ici à repérer les commandes douteuses
 * (plusieurs « clients » derrière la même adresse) et les robots. Passé sa
 * durée, la purge quotidienne l'efface des lignes sans toucher au reste du
 * parcours : l'entonnoir et les sources restent intacts, l'adresse disparaît.
 * Désactiver l'enregistrement n'efface rien : la purge s'en charge à son tour.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lecture, bornes et enregistrement des réglages.
 */
final class Visitor_Settings {

	/**
	 * Option : durée de conservation des événements, en jours.
	 */
	public const RETENTION_OPTION = 'famma_visitor_retention_days';

	/**
	 * Option : enregistrer l'IP en clair (« 1 » ou « 0 »).
	 */
	public const STORE_IP_OPTION = 'famma_visitor_store_ip';

	/**
	 * Option : durée de conservation de l'IP, en jours.
	 */
	public const IP_DAYS_OPTION = 'famma_visitor_ip_days';

	/**
	 * Action admin-post du formulaire.
	 */
	public const ACTION = 'famma_visitor_settings';

	/**
	 * Instance partagée.
	 *
	 * @var Visitor_Settings|null
	 */
	private static ?Visitor_Settings $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Visitor_Settings
	 */
	public static function instance(): Visitor_Settings {
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
		add_action( 'admin_post_' . self::ACTION, array( $this, 'save' ) );
	}

	/**
	 * Durée de conservation des événements, bornée entre 7 et 365 jours.
	 *
	 * @return int
	 */
	public static function retention_days(): int {
		return self::bounded( self::RETENTION_OPTION, 180, 7, 365 );
	}

	/**
	 * Indique si l'IP est enregistrée en clair.
	 *
	 * @return bool
	 */
	public static function store_ip(): bool {
		return '0' !== (string) get_option( self::STORE_IP_OPTION, '1' );
	}

	/**
	 * Durée de conservation de l'IP, bornée entre 1 et 365 jours, et jamais
	 * plus longue que celle des événements qui la portent.
	 *
	 * @return int
	 */
	public static function ip_days(): int {
		return min( self::retention_days(), self::bounded( self::IP_DAYS_OPTION, 90, 1, 365 ) );
	}

	/**
	 * Nombre de jours lu dans une option, borné.
	 *
	 * @param string $option  Nom de l'option.
	 * @param int    $fallback Valeur par défaut.
	 * @param int    $min     Minimum.
	 * @param int    $max     Maximum.
	 * @return int
	 */
	private static function bounded( string $option, int $fallback, int $min, int $max ): int {
		$days = absint( get_option( $option, $fallback ) );
		return max( $min, min( $max, $days ? $days : $fallback ) );
	}

	/**
	 * Enregistre les réglages (nonce, capacité, assainissement, bornes).
	 *
	 * @return void
	 */
	public function save(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ) );
		}
		$days    = isset( $_POST['famma_retention_days'] ) ? absint( wp_unslash( $_POST['famma_retention_days'] ) ) : 180;
		$ip_days = isset( $_POST['famma_ip_days'] ) ? absint( wp_unslash( $_POST['famma_ip_days'] ) ) : 90;
		update_option( self::RETENTION_OPTION, max( 7, min( 365, $days ) ), false );
		update_option( self::IP_DAYS_OPTION, max( 1, min( 365, $ip_days ) ), false );
		update_option( self::STORE_IP_OPTION, empty( $_POST['famma_store_ip'] ) ? '0' : '1', false );
		wp_safe_redirect(
			Visitor_Admin::instance()->url(
				array(
					'tab'   => 'settings',
					'saved' => 1,
				)
			)
		);
		exit;
	}
}
