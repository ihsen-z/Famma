<?php
/**
 * Sonde sans événement et battement de cœur de la CAPI (lot 5, R6, R7, A3).
 *
 * - Sonde : `GET /{version}/me?fields=id` (0.3.3), chaque semaine (Action
 *   Scheduler, groupe `famma-capi-health`), à la demande (« Vérifier
 *   maintenant » dans « Mesure ») et après un changement de jeton, de Pixel
 *   ou de version. Elle valide le jeton et la version servie sans créer le
 *   moindre événement chez Meta : jamais de POST /events. L'accès au jeu de
 *   données reste prouvé par les vrais envois (`Capi_Probe_Verdict`). Hors
 *   production, aucune requête (D4).
 * - Battement de cœur : une tâche quotidienne qui note l'heure, reprend les
 *   envois interrompus et recalcule l'état de santé. S'il cesse plus de 36 h,
 *   c'est que les tâches planifiées ne tournent plus (règle vérifiée sur
 *   admin_init, sans cron).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Planification et exécution de la sonde et du battement de cœur.
 */
final class Capi_Probe {

	/**
	 * Crochet de la sonde.
	 */
	public const HOOK = 'famma_capi_probe';

	/**
	 * Crochet du battement de cœur.
	 */
	public const HEARTBEAT_HOOK = 'famma_capi_heartbeat';

	/**
	 * Groupe Action Scheduler.
	 */
	public const GROUP = 'famma-capi-health';

	/**
	 * Transitoire : planification vérifiée récemment.
	 */
	private const CHECKED = 'famma_capi_health_scheduled';

	/**
	 * Options dont le changement relance la sonde.
	 */
	private const WATCHED = array( 'famma_meta_access_token', 'famma_meta_pixel_id', 'famma_meta_graph_version' );

	/**
	 * Instance unique.
	 *
	 * @var Capi_Probe|null
	 */
	private static ?Capi_Probe $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Probe
	 */
	public static function instance(): Capi_Probe {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( self::HOOK, array( $this, 'run_task' ) );
		add_action( self::HEARTBEAT_HOOK, array( $this, 'heartbeat' ) );
		add_action( 'admin_init', array( $this, 'ensure_scheduled' ) );
		add_action( 'admin_post_famma_capi_probe_now', array( $this, 'probe_now' ) );
		foreach ( self::WATCHED as $option ) {
			add_action( 'add_option_' . $option, array( $this, 'settings_changed' ) );
			add_action( 'update_option_' . $option, array( $this, 'settings_changed' ) );
		}
	}

	/**
	 * Planifie la sonde hebdomadaire et le battement de cœur quotidien.
	 *
	 * Vérifié au plus une fois par jour, depuis l'administration.
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		if ( false !== get_transient( self::CHECKED ) || ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		set_transient( self::CHECKED, 1, DAY_IN_SECONDS );
		if ( ! get_option( Capi_Health::HEARTBEAT ) ) {
			// Le compteur part de la mise en place, pas de l'époque Unix.
			update_option( Capi_Health::HEARTBEAT, time(), false );
		}
		if ( ! as_has_scheduled_action( self::HOOK, array(), self::GROUP ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, WEEK_IN_SECONDS, self::HOOK, array(), self::GROUP, true );
		}
		if ( ! as_has_scheduled_action( self::HEARTBEAT_HOOK, array(), self::GROUP ) ) {
			as_schedule_recurring_action( time() + 300, DAY_IN_SECONDS, self::HEARTBEAT_HOOK, array(), self::GROUP, true );
		}
		$probe = Capi_Health::signals()['probe'] ?? array();
		if ( array() !== $probe && ! array_key_exists( 'verdict', $probe ) ) {
			// Sonde de la 0.3.2 (objet Pixel) : la rejouer sur /me.
			$this->settings_changed();
		}
	}

	/**
	 * Battement de cœur : note l'heure, reprend les envois interrompus, recalcule l'état.
	 *
	 * @return void
	 */
	public function heartbeat(): void {
		update_option( Capi_Health::HEARTBEAT, time(), false );
		Capi_Retry::rescue_stale();
		Capi_Health::evaluate();
	}

	/**
	 * Tâche planifiée de la sonde (hebdomadaire ou après un changement de réglage).
	 *
	 * @return void
	 */
	public function run_task(): void {
		self::run();
	}

	/**
	 * Relance la sonde après un changement de jeton, de Pixel ou de version.
	 *
	 * @return void
	 */
	public function settings_changed(): void {
		$args = array( 'reason' => 'settings' );
		if ( function_exists( 'as_enqueue_async_action' ) && ! ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK, $args, self::GROUP ) ) ) {
			as_enqueue_async_action( self::HOOK, $args, self::GROUP, true );
		}
	}

	/**
	 * Interroge Meta sans créer d'événement, journalise et retient le résultat.
	 *
	 * @return array<string, mixed> Résultat classé du transport.
	 */
	public static function run(): array {
		$cfg   = Config::instance();
		$pixel = $cfg->meta_pixel_id();
		$token = $cfg->meta_access_token();
		if ( '' === $pixel || '' === $token ) {
			$result = array(
				'class'             => 'not_configured',
				'version_requested' => $cfg->meta_graph_version(),
			);
		} else {
			$result = Capi_Transport::probe( $token );
		}
		Capi_Log::write(
			array(
				'event_name' => 'Probe',
				'event_id'   => 'probe',
				'state'      => (string) $result['class'],
			) + $result,
			$token
		);
		Capi_Health::record_probe( $result );
		return $result;
	}

	/**
	 * Bouton « Vérifier maintenant » (nonce + manage_woocommerce).
	 *
	 * @return void
	 */
	public function probe_now(): void {
		check_admin_referer( 'famma_capi_probe_now' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ), '', array( 'response' => 403 ) );
		}
		$result = self::run();
		wp_safe_redirect( add_query_arg( 'famma_capi_probe', sanitize_key( Capi_Probe_Verdict::outcome( $result ) ), admin_url( 'admin.php?page=famma-tracking' ) ) );
		exit;
	}

	/**
	 * Bloc « Vérifier maintenant » de l'écran « Mesure ».
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$probe = Capi_Health::signals()['probe'] ?? array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only fixed result identifier.
		$done = isset( $_GET['famma_capi_probe'] ) ? sanitize_key( wp_unslash( $_GET['famma_capi_probe'] ) ) : '';
		?>
		<h2><?php esc_html_e( 'Vérifier le jeton Meta', 'famma-core' ); ?></h2>
		<?php if ( '' !== $done ) : ?>
			<div class="notice notice-<?php echo esc_attr( in_array( $done, array( 'accepted', 'accepted_warning' ), true ) ? 'success' : ( 'token' === $done ? 'error' : 'warning' ) ); ?> inline"><p><?php echo esc_html( Capi_Probe_Verdict::label( $done ) ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Demande à Meta l’identifiant du titulaire du jeton (utilisateur système), avec la version configurée, sans envoyer aucun événement : vérifie le jeton et la version servie. L’accès au Pixel est vérifié par les vrais envois et le rapprochement. Automatique chaque semaine et après un changement de réglage.', 'famma-core' ); ?></p>
		<?php if ( array() !== $probe ) : ?>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: date of the last check, 2: probe outcome, 3: requested version, 4: version served by Meta. */
						__( 'Dernière vérification le %1$s : %2$s (version demandée %3$s, servie %4$s).', 'famma-core' ),
						wp_date( 'd/m/Y H:i', (int) ( $probe['at'] ?? 0 ) ),
						Capi_Probe_Verdict::label( Capi_Probe_Verdict::outcome( $probe ) ),
						(string) ( $probe['requested'] ?? '-' ),
						Capi_Transport::served_label( (string) ( $probe['served'] ?? '' ) )
					)
				);
				?>
			</p>
		<?php endif; ?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="famma_capi_probe_now" />
			<?php wp_nonce_field( 'famma_capi_probe_now' ); ?>
			<?php submit_button( __( 'Vérifier maintenant', 'famma-core' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}
}
