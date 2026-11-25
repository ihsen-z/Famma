<?php
/**
 * Veille horaire de la mesure Meta et adresse des alertes (phase B des réformes du pixel, lot 4, D3).
 *
 * - WP-Cron, chaque heure (`famma_capi_health_watch`, vérifiée sur `init`,
 *   retirée à la désactivation avec les autres tâches CAPI) : reprend les envois
 *   interrompus puis recalcule l'état de santé. Sans cette tâche, l'état
 *   n'était recalculé qu'à une visite de l'administration ou par le
 *   battement de cœur quotidien (Action Scheduler) : un OrderPlaced manquant
 *   pouvait attendre un jour, et une file Action Scheduler arrêtée
 *   empêchait l'e-mail. WP-Cron ne dépend pas d'Action Scheduler.
 * - Champ « Adresse des alertes » (option `famma_capi_alert_email`) dans
 *   FAMMA → Mesure : enregistré par l'API Réglages (nonce
 *   `famma_tracking-options` vérifié par options.php), refusé sans
 *   `manage_woocommerce`, assaini par `sanitize_email()`. Vide : aucun
 *   e-mail.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Tâche horaire et réglage de l'adresse des alertes.
 */
final class Capi_Health_Watch {

	/**
	 * Crochet WP-Cron horaire.
	 */
	public const HOOK = 'famma_capi_health_watch';

	/**
	 * Section de l'écran « Mesure ».
	 */
	private const SECTION = 'famma_section_meta_alerts';

	/**
	 * Instance unique.
	 *
	 * @var Capi_Health_Watch|null
	 */
	private static ?Capi_Health_Watch $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Health_Watch
	 */
	public static function instance(): Capi_Health_Watch {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( self::HOOK, array( self::$i, 'run' ) );
			// Vérifiée à chaque chargement (tableau `cron` déjà en mémoire) : pas seulement en admin (relecture M3).
			add_action( 'init', array( self::$i, 'ensure_scheduled' ) );
			add_action( 'admin_init', array( self::$i, 'register' ), 12 );
		}
		return self::$i;
	}

	/**
	 * Planifie la tâche horaire si elle manque.
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/**
	 * Retire les tâches planifiées de la mesure Meta (désactivation du plugin, relecture M3) :
	 * veille horaire, lecture qualité quotidienne, passage de renvoi des refus.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		$hooks = array( self::HOOK, Capi_Refusal_Resend::HOOK );
		if ( class_exists( Capi_Meta_Quality::class ) ) {
			$hooks[] = Capi_Meta_Quality::CRON;
		}
		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Capi_Refusal_Resend::HOOK, array(), Capi_Retry::GROUP );
		}
	}

	/**
	 * Tâche horaire : envois interrompus repris, état recalculé (e-mail éventuel).
	 *
	 * @return void
	 */
	public function run(): void {
		Capi_Retry::rescue_stale();
		Capi_Health::evaluate();
	}

	/**
	 * Enregistre l'option et son champ dans l'écran « Mesure ».
	 *
	 * @return void
	 */
	public function register(): void {
		register_setting(
			Tracking_Settings::GROUP,
			Capi_Health_Alert::EMAIL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_email_option' ),
				'default'           => '',
			)
		);
		add_settings_section( self::SECTION, __( 'Alertes de la mesure Meta', 'famma-core' ), '__return_false', Tracking_Settings::SLUG );
		add_settings_field( Capi_Health_Alert::EMAIL, __( 'Adresse des alertes', 'famma-core' ), array( $this, 'render' ), Tracking_Settings::SLUG, self::SECTION, array( 'label_for' => Capi_Health_Alert::EMAIL ) );
	}

	/**
	 * Assainit l'adresse : vide accepté, adresse invalide refusée avec un message.
	 *
	 * @param mixed $value Valeur envoyée (déjà déséchappée par options.php).
	 * @return string
	 */
	public static function sanitize_email_option( $value ): string {
		$previous = (string) get_option( Capi_Health_Alert::EMAIL, '' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $previous;
		}
		$raw = trim( is_scalar( $value ) ? (string) $value : '' );
		if ( '' === $raw ) {
			return '';
		}
		$email = sanitize_email( $raw );
		if ( '' === $email || ! is_email( $email ) ) {
			add_settings_error( Capi_Health_Alert::EMAIL, 'famma_capi_alert_email_invalid', __( 'Adresse des alertes invalide : l’ancienne valeur est conservée.', 'famma-core' ) );
			return $previous;
		}
		return $email;
	}

	/**
	 * Champ de l'adresse.
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<input type="email" id="<?php echo esc_attr( Capi_Health_Alert::EMAIL ); ?>" name="<?php echo esc_attr( Capi_Health_Alert::EMAIL ); ?>" value="<?php echo esc_attr( (string) get_option( Capi_Health_Alert::EMAIL, '' ) ); ?>" class="regular-text" autocomplete="off" />
		<p class="description"><?php esc_html_e( 'Reçoit un e-mail quand la mesure Meta passe au rouge (au plus un par jour tant qu’elle le reste), puis quand elle est rétablie. Vide : aucun e-mail, les alertes restent visibles ici seulement. Le contenu ne cite que des numéros de commande, jamais le jeton ni une donnée client.', 'famma-core' ); ?></p>
		<?php
	}
}
