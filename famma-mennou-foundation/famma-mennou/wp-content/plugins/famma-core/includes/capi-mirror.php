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
 * d'un numéro valide, `ph` haché.
 *
 * ── Pourquoi un relais du navigateur ───────────────────────────────────────
 * Les pages sont servies par le cache LiteSpeed : PHP ne s'exécute pas, et un
 * nonce imprimé serait le même pour tous. Le navigateur relaie donc chaque
 * événement à `famma/v1/meta-relay` (`fammaRelay`, `sendBeacon`), groupé :
 * PageView et ViewContent d'un même affichage partent dans un seul appel.
 * La route ne fait confiance qu'à ce que le serveur peut vérifier : nom en
 * liste blanche, format de l'event_id, URL du site, âge plausible, articles
 * existants. Valeur, devise et identifiants sont RECALCULÉS depuis WooCommerce
 * (`Capi_Mirror_Event`) ; IP, user-agent et cookies sont lus sur la requête.
 *
 * ── Pourquoi une file ──────────────────────────────────────────────────────
 * Hébergement mutualisé : la route n'appelle jamais Meta. Elle range
 * l'événement (`Capi_Mirror_Queue`), répond 204, et l'envoi groupé part au
 * plus tôt 60 s après le premier événement en attente (fin d'une requête du
 * relais, après la réponse) et au plus tard à la tâche WP-Cron de 5 minutes.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Relais navigateur → file du doublon serveur.
 */
final class Capi_Mirror {

	/**
	 * Événements doublés (liste blanche).
	 */
	public const EVENTS = array( 'PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout' );

	/**
	 * Espace de noms et chemin de la route.
	 */
	public const ROUTE_NAMESPACE = 'famma/v1';
	public const ROUTE           = '/meta-relay';

	/**
	 * Tâche WP-Cron de l'envoi groupé, et son intervalle.
	 */
	public const CRON          = 'famma_capi_mirror_flush';
	public const CRON_SCHEDULE = 'famma_capi_mirror_5min';

	/**
	 * Délai de regroupement côté navigateur, en millisecondes.
	 */
	public const RELAY_DELAY_MS = 800;

	/**
	 * Appels acceptés par empreinte d'IP sur dix minutes (même règle que le journal des visiteurs).
	 */
	private const RATE_LIMIT = 120;

	/**
	 * Événements acceptés pour tout le site sur dix minutes (filtre `famma_capi_mirror_site_limit`).
	 */
	private const SITE_LIMIT = 3000;

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
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_relay' ), 11 );
		add_filter( 'wp_inline_script_attributes', array( $this, 'no_optimize_attributes' ) );
		// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Meta demande l'envoi « dans l'heure » ; la tâche ne fait qu'une lecture d'option quand la file est vide.
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
	 * Appel du relais qui suit un `fbq('track')` imprimé par PHP.
	 *
	 * L'eventID est celui que `fammaEventId()` vient de tirer
	 * (`window.fammaLastEventId`) : jamais un identifiant fabriqué en PHP.
	 * Sans relais défini (doublon coupé), l'appel ne fait rien.
	 *
	 * @param string            $event Événement de la liste blanche.
	 * @param array<int, int[]> $items Articles `[ID WooCommerce, quantité]`.
	 * @return string JavaScript.
	 */
	public static function relay_call( string $event, array $items = array() ): string {
		return 'window.fammaRelay&&fammaRelay(' . wp_json_encode( $event ) . ',window.fammaLastEventId'
			. ( array() !== $items ? ',' . wp_json_encode( array_values( $items ) ) : '' ) . ');';
	}

	/**
	 * Pose le relais avant le code de base du Pixel, pour un visiteur suivi.
	 *
	 * @return void
	 */
	public function enqueue_relay(): void {
		if ( ! Tracking_Events::pixel_active() || ! self::enabled() ) {
			return;
		}
		wp_add_inline_script( 'famma-pixels', self::browser_script(), 'before' );
	}

	/**
	 * Script `fammaRelay(nom, eventID, articles)`.
	 *
	 * File en mémoire, un envoi `sendBeacon` (sinon `fetch` keepalive)
	 * {@see RELAY_DELAY_MS} après le premier événement, et à la sortie de
	 * page. Une paire nom + eventID ne part qu'une fois par affichage. Le
	 * corps porte l'âge de l'événement (pas l'heure du navigateur) et, s'il
	 * existe, `ph` : l'empreinte du téléphone posée par `express-order.js`.
	 * Le texte ne dépend que des réglages (page en cache).
	 *
	 * @return string JavaScript.
	 */
	public static function browser_script(): string {
		return 'window.fammaRelay=window.fammaRelay||(function(u,w,d){var q=[],k={},t=0;'
			. 'function ph(){var h=w.fammaPh||"";if(!h){try{h=w.sessionStorage.getItem("' . Capi_External_Id::PHONE_KEY . '")||"";}catch(e){}}return /^[0-9a-f]{64}$/.test(h)?h:"";}'
			. 'function send(){if(t){clearTimeout(t);t=0;}while(q.length){var n=Date.now(),b={e:q.splice(0,' . Capi_Mirror_Event::MAX_EVENTS . ').map(function(x){return{n:x.n,id:x.id,u:x.u,a:n-x.s,i:x.i};})},p=ph(),s;if(p){b.ph=p;}s=JSON.stringify(b);'
			. 'try{if(navigator.sendBeacon&&navigator.sendBeacon(u,new Blob([s],{type:"text/plain"}))){continue;}}catch(e){}'
			. 'try{if(w.fetch){w.fetch(u,{method:"POST",body:s,keepalive:true,credentials:"same-origin",headers:{"Content-Type":"text/plain"}}).catch(function(){});}}catch(e){}}}'
			. 'w.addEventListener("pagehide",send);d.addEventListener("visibilitychange",function(){if("hidden"===d.visibilityState){send();}});'
			. 'return function(n,id,i){w.fammaLastEventId=void 0;if(!n||!id||k[n+"|"+id]){return;}k[n+"|"+id]=1;'
			. 'q.push({n:String(n),id:String(id),u:String(location.href).slice(0,2000),s:Date.now(),i:i||[]});if(!t){t=setTimeout(send,' . self::RELAY_DELAY_MS . ');}};'
			. '})(' . wp_json_encode( rest_url( self::ROUTE_NAMESPACE . self::ROUTE ) ) . ',window,document);';
	}

	/**
	 * Le relais n'est jamais différé ni regroupé par LiteSpeed : sans lui, le
	 * PageView du code de base ne serait pas relayé.
	 *
	 * @param mixed $attributes Attributs de la balise inline.
	 * @return array<string, string|bool>
	 */
	public function no_optimize_attributes( $attributes ): array {
		$attributes = (array) $attributes;
		if ( 'famma-pixels-js-before' === ( $attributes['id'] ?? '' ) ) {
			$attributes['data-no-optimize'] = '1';
			$attributes['data-no-defer']    = '1';
			$attributes['data-cfasync']     = 'false';
		}
		return $attributes;
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
	 * Déclare la route publique du relais (pas de nonce : page en cache).
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'receive' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Reçoit un lot du relais. Répond toujours 204, jamais d'appel à Meta ici.
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response
	 */
	public function receive( \WP_REST_Request $request ): \WP_REST_Response {
		$done = new \WP_REST_Response( null, 204 );
		if ( 0 < self::accept( (string) $request->get_body(), time() ) ) {
			add_action( 'shutdown', array( Capi_Mirror_Queue::class, 'flush_if_due' ) );
		}
		return $done;
	}

	/**
	 * Contrôles puis mise en file ; testable sans serveur REST.
	 *
	 * @param string $body Corps brut de la requête.
	 * @param int    $now  Horloge.
	 * @return int Événements mis en file.
	 */
	public static function accept( string $body, int $now ): int {
		if ( '' === $body || strlen( $body ) > Capi_Mirror_Event::MAX_BODY || ! self::enabled() || self::internal_sender() || Visitor_Context::is_bot() ) {
			return 0;
		}
		if ( Visitor_Context::rate_limited( 'famma_mr_', self::RATE_LIMIT ) ) {
			return 0;
		}
		$events = Capi_Mirror_Event::from_body( $body, $now );
		if ( array() === $events || self::site_limited( count( $events ), $now ) ) {
			return 0;
		}
		return Capi_Mirror_Queue::push_many( $events, $now );
	}

	/**
	 * Expéditeur interne (S1) : même règle que `Tracking::browser_tracking_allowed()`.
	 *
	 * Une requête REST sans nonce est anonyme pour WordPress
	 * (`rest_cookie_check_errors`) : l'utilisateur est donc relu dans le
	 * cookie de connexion, signé par WordPress.
	 *
	 * @return bool
	 */
	public static function internal_sender(): bool {
		if ( Config::instance()->track_internal_visitors() ) {
			return false;
		}
		$user     = function_exists( 'wp_validate_auth_cookie' ) ? (int) wp_validate_auth_cookie( '', 'logged_in' ) : 0;
		$internal = $user > 0 && ( user_can( $user, 'manage_options' ) || user_can( $user, 'manage_woocommerce' ) );

		return (bool) apply_filters( 'famma_tracking_exclude_visitor', $internal );
	}

	/**
	 * Plafond global, contre un afflux réparti sur beaucoup d'adresses.
	 *
	 * Compteur par tranche fixe de dix minutes (clé datée) : un trafic continu
	 * ne prolonge jamais le compteur, qui repart à zéro à chaque tranche.
	 *
	 * @param int $count Événements du lot.
	 * @param int $now   Horloge.
	 * @return bool
	 */
	private static function site_limited( int $count, int $now ): bool {
		$limit = (int) apply_filters( 'famma_capi_mirror_site_limit', self::SITE_LIMIT );
		$key   = 'famma_mr_site_' . intdiv( $now, 10 * MINUTE_IN_SECONDS );
		$used  = (int) get_transient( $key );
		if ( $used + $count > $limit ) {
			return true;
		}
		set_transient( $key, $used + $count, 10 * MINUTE_IN_SECONDS );
		return false;
	}
}
