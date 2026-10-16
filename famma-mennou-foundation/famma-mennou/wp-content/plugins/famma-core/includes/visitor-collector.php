<?php
/**
 * Parcours visiteurs — collecte navigateur.
 *
 * ── Pourquoi le navigateur, et pas PHP à chaque page ───────────────────────
 * LiteSpeed sert les pages depuis son cache : PHP ne s'exécute pas pour la
 * plupart des visites. Un enregistrement côté serveur au rendu ne verrait donc
 * qu'une fraction du trafic. Le script `visitor-journal.js` regroupe les
 * événements et les envoie par `sendBeacon` à `famma/v1/collect`, une requête
 * POST que le cache ne sert jamais.
 *
 * ── Pourquoi pas de nonce ──────────────────────────────────────────────────
 * Un nonce imprimé dans une page en cache serait le même pour tous et
 * périmerait au bout de 24 h. La route est publique (`__return_true`) et
 * n'écrit que des données de mesure ; elle se protège autrement : liste
 * blanche des événements, lot et corps plafonnés, assainissement champ par
 * champ, robots écartés, et limite de débit par empreinte d'IP.
 *
 * Jamais de valeur saisie dans un formulaire : seul le NOM du champ touché
 * remonte, pour mesurer où le formulaire est abandonné.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Charge le script de collecte et reçoit ses lots.
 */
final class Visitor_Collector {

	/**
	 * Événements que le navigateur peut envoyer ; tout autre nom est ignoré.
	 */
	private const EVENTS = array(
		'page_view',
		'page_leave',
		'form_start',
		'form_field',
		'form_submit',
		'click_whatsapp',
		'click_phone',
		'click_outbound',
	);

	/**
	 * Types de page reconnus.
	 */
	private const PAGE_TYPES = array( 'home', 'shop', 'product', 'cart', 'checkout', 'thankyou', 'search', 'page', 'other' );

	/**
	 * Événements par lot, au plus.
	 */
	private const MAX_EVENTS = 25;

	/**
	 * Taille maximale du corps, en octets.
	 */
	private const MAX_BODY = 16384;

	/**
	 * Lots acceptés par empreinte d'IP sur dix minutes.
	 */
	private const RATE_LIMIT = 120;

	/**
	 * Instance partagée.
	 *
	 * @var Visitor_Collector|null
	 */
	private static ?Visitor_Collector $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Visitor_Collector
	 */
	public static function instance(): Visitor_Collector {
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 30 );
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Type de la page affichée, tel que l'entonnoir le lit.
	 *
	 * La page de remerciement est testée avant le checkout : WooCommerce la
	 * sert sous l'URL du checkout, et `is_checkout()` y répond vrai.
	 *
	 * @return string
	 */
	private function page_type(): string {
		if ( is_front_page() ) {
			return 'home';
		}
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return 'thankyou';
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			return 'product';
		}
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) {
			return 'shop';
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return 'cart';
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return 'checkout';
		}
		if ( is_search() ) {
			return 'search';
		}
		return is_page() ? 'page' : 'other';
	}

	/**
	 * Charge le script de collecte, sauf pour l'équipe connectée.
	 *
	 * Le contexte imprimé ne dépend que de la page, jamais du visiteur : il
	 * reste juste quand la page est servie depuis le cache.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( Visitor_Context::is_staff() || is_admin() ) {
			return;
		}
		wp_enqueue_script(
			'famma-visitor-journal',
			plugins_url( 'assets/js/visitor-journal.js', FAMMA_CORE_FILE ),
			array(),
			FAMMA_CORE_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		$context = array(
			'endpoint'  => esc_url_raw( rest_url( 'famma/v1/collect' ) ),
			'pageType'  => $this->page_type(),
			'productId' => ( function_exists( 'is_product' ) && is_product() ) ? (int) get_queried_object_id() : 0,
			'days'      => Visitor_Store::instance()->retention_days(),
		);
		wp_add_inline_script( 'famma-visitor-journal', 'window.fammaJournal=' . wp_json_encode( $context ) . ';', 'before' );
	}

	/**
	 * Déclare la route de collecte.
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route(
			'famma/v1',
			'/collect',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'collect' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Indique si l'empreinte d'IP a dépassé sa limite de lots.
	 *
	 * @param string $ip_hash Empreinte d'IP.
	 * @return bool
	 */
	private function rate_limited( string $ip_hash ): bool {
		if ( '' === $ip_hash ) {
			return false;
		}
		$key   = 'famma_vj_' . $ip_hash;
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return true;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Texte court assaini.
	 *
	 * @param mixed $value Valeur reçue.
	 * @param int   $max   Longueur maximale.
	 * @return string
	 */
	private function text( $value, int $max ): string {
		return is_scalar( $value ) ? mb_substr( sanitize_text_field( (string) $value ), 0, $max ) : '';
	}

	/**
	 * Chemin d'URL sans requête ni fragment : un paramètre peut porter une
	 * donnée personnelle (clé de commande, e-mail), le chemin seul suffit.
	 *
	 * @param mixed $value Valeur reçue.
	 * @return string
	 */
	private function path( $value ): string {
		$path = is_string( $value ) ? (string) wp_parse_url( $value, PHP_URL_PATH ) : '';
		return mb_substr( '/' . ltrim( sanitize_text_field( $path ), '/' ), 0, 255 );
	}

	/**
	 * Reçoit un lot d'événements.
	 *
	 * Répond toujours 204, même quand le lot est écarté : le script n'a rien à
	 * corriger, et un robot n'apprend pas ce qui est filtré.
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response
	 */
	public function collect( \WP_REST_Request $request ): \WP_REST_Response {
		$done = new \WP_REST_Response( null, 204 );
		$body = (string) $request->get_body();
		if ( '' === $body || strlen( $body ) > self::MAX_BODY || Visitor_Context::is_bot() || Visitor_Context::is_staff() ) {
			return $done;
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data['v'], $data['s'], $data['e'] ) || ! is_array( $data['e'] ) ) {
			return $done;
		}
		$visitor = strtolower( $this->text( $data['v'], 36 ) );
		$session = strtolower( $this->text( $data['s'], 36 ) );
		$ip_hash = Visitor_Context::ip_hash();
		if ( ! Visitor_Context::is_uuid( $visitor ) || ! Visitor_Context::is_uuid( $session ) || $this->rate_limited( $ip_hash ) ) {
			return $done;
		}

		$src    = isset( $data['src'] ) && is_array( $data['src'] ) ? $data['src'] : array();
		$common = array(
			'visitor_id' => $visitor,
			'session_id' => $session,
			'source'     => strtolower( $this->text( $src['source'] ?? '', 64 ) ),
			'medium'     => strtolower( $this->text( $src['medium'] ?? '', 64 ) ),
			'campaign'   => $this->text( $src['campaign'] ?? '', 100 ),
			'content'    => $this->text( $src['content'] ?? '', 100 ),
			'device'     => Visitor_Context::device(),
			'ip_hash'    => $ip_hash,
		);
		$now    = time();
		$rows   = array();
		foreach ( array_slice( $data['e'], 0, self::MAX_EVENTS ) as $event ) {
			if ( ! is_array( $event ) || ! in_array( $event['n'] ?? '', self::EVENTS, true ) ) {
				continue;
			}
			$type = (string) ( $event['pt'] ?? '' );
			// Âge de l'événement côté navigateur, borné : un lot retardé garde son heure.
			$age    = min( 1800, max( 0, (int) ( ( $event['a'] ?? 0 ) / 1000 ) ) );
			$rows[] = $common + array(
				'created_at' => gmdate( 'Y-m-d H:i:s', $now - $age ),
				'event'      => (string) $event['n'],
				'page_type'  => in_array( $type, self::PAGE_TYPES, true ) ? $type : 'other',
				'path'       => $this->path( $event['p'] ?? '' ),
				'product_id' => absint( $event['pid'] ?? 0 ),
				'label'      => $this->text( $event['l'] ?? '', 100 ),
				'num'        => min( 86400, absint( $event['x'] ?? 0 ) ),
			);
		}
		Visitor_Store::instance()->insert_many( $rows );
		return $done;
	}
}
