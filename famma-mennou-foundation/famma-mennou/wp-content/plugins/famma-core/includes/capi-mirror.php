<?php
/**
 * Doublon serveur (CAPI) des événements navigateur (lot 59, Q21 et Q22).
 *
 * PageView, ViewContent, AddToCart et InitiateCheckout partent déjà du Pixel.
 * Ils partent aussi par la CAPI, avec le MÊME event_id : Meta garde le premier
 * reçu de chaque paire « event_name + event_id » et écarte l'autre (doc Meta
 * « Handling Duplicate Pixel and Conversions API Events », relue le 26/11/2026).
 * Un événement perdu par le navigateur (bloqueur, iOS, départ rapide) arrive
 * quand même, avec IP, user-agent, fbp, fbc, external_id et, après la saisie
 * d'un numéro valide connue du serveur, `ph` haché.
 *
 * Répartition (une classe = une responsabilité) :
 * - `Capi_Mirror_Script` : relais `fammaRelay` du navigateur (page en cache) ;
 * - `Capi_Mirror_Route` : contrôles et mise en file (route du journal ou route propre) ;
 * - `Capi_Mirror_Event` et `Capi_Mirror_Data` : événement validé, valeurs recalculées ;
 * - `Capi_Mirror_Store` : lignes de la file, verrou et marques de rejeu ;
 * - `Capi_Mirror_Queue` : envoi groupé par la tâche WP-Cron, jamais pendant une requête du navigateur ;
 * - `Capi_Mirror_Health` : envois, refus et pertes comptés ; orange au-delà d'un seuil sur 24 h.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Interrupteur, planification et points d'accroche du doublon.
 */
final class Capi_Mirror {

	/**
	 * Événements doublés (liste blanche).
	 */
	public const EVENTS = array( 'PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout' );

	/**
	 * Espace de noms et chemin de la route propre (pages sans journal des visiteurs).
	 */
	public const ROUTE_NAMESPACE = 'famma/v1';
	public const ROUTE           = '/meta-relay';

	/**
	 * Tâche WP-Cron de l'envoi groupé, et son intervalle.
	 */
	public const CRON          = 'famma_capi_mirror_flush';
	public const CRON_SCHEDULE = 'famma_capi_mirror_5min';

	/**
	 * Instance partagée.
	 *
	 * @var Capi_Mirror|null
	 */
	private static ?Capi_Mirror $i = null;

	/**
	 * Renvoie l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Mirror
	 */
	public static function instance(): Capi_Mirror {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Points d'accroche. Priorité 11 : le handle `famma-pixels` existe (10),
	 * et le relais est posé AVANT le code de base (`before`).
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'rest_api_init', array( Capi_Mirror_Route::class, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( Capi_Mirror_Script::class, 'enqueue_relay' ), 11 );
		add_filter( 'wp_inline_script_attributes', array( Capi_Mirror_Script::class, 'no_optimize_attributes' ) );
		// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Meta demande l'envoi « dans l'heure » ; la tâche ne fait qu'une lecture quand la file est vide.
		add_filter( 'cron_schedules', array( $this, 'cron_schedule' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
		add_action( self::CRON, array( Capi_Mirror_Queue::class, 'flush' ) );
		add_filter( 'famma_capi_health_reasons', array( Capi_Mirror_Health::class, 'health_reasons' ), 10, 3 );
		add_filter( 'famma_capi_health_label', array( Capi_Mirror_Health::class, 'health_label' ), 10, 2 );
	}

	/**
	 * Doublon actif : case de « Mesure », Pixel et jeton présents, envoi permis par l'environnement.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		$cfg = Config::instance();

		return $cfg->meta_browser_mirror() && '' !== $cfg->meta_pixel_id() && $cfg->meta_sending_allowed() && '' !== $cfg->meta_access_token();
	}

	/**
	 * Intervalle de 5 minutes de la tâche d'envoi groupé.
	 *
	 * @param mixed $schedules Intervalles connus.
	 * @return array<string, array<string, mixed>>
	 */
	public function cron_schedule( $schedules ): array {
		$schedules                        = is_array( $schedules ) ? $schedules : array();
		$schedules[ self::CRON_SCHEDULE ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Toutes les 5 minutes (doublon CAPI)', 'famma-core' ),
		);
		return $schedules;
	}

	/**
	 * Planifie la tâche si elle manque (la liste des tâches est déjà en mémoire).
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON );
		}
	}

	/**
	 * Désactivation du plugin (M6) : tâche retirée, file vidée (IP, user-agent,
	 * fbp, fbc, empreintes), verrou et compteurs de débit des routes publiques supprimés.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON );
		Capi_Mirror_Store::clear();
		Rate_Counter::clear( '' );
	}
}
