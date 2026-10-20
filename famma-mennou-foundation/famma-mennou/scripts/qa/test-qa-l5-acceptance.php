<?php
/**
 * Clone QA pixel, lot 5 : tests d'acceptation serveur de famma-core 0.3.2
 * (docs/PLAN-TESTS-PIXEL-2026-10-19.md, lot 5 : D1, D4, D5, D7, A3, G1 à G4,
 * V1 à V9 côté serveur). Complète test-qa-pixel-integration.php (T2.2 à
 * T2.13 mis à jour) et qa-l2-checkout-double.sh (D3).
 *
 * Run (depuis famma-mennou/, Git Bash, MSYS_NO_PATHCONV=1) :
 *   docker compose -p famma-qa-pixel -f scripts/compose-integration.yml \
 *     -f scripts/qa/compose-qa-pixel.yml run --rm -T \
 *     -v "<chemin Windows>/wp-content/mu-plugins/famma-measurement-watchdog.php:/qa-watchdog/famma-measurement-watchdog.php:ro" \
 *     -e FAMMA_QA_L5_TEST=1 -e FAMMA_TEST_DB_NAME=famma_integration \
 *     -e FAMMA_TEST_DB_HOST=db -e FAMMA_QA_L5_MODE=<mode> \
 *     cli eval-file /tests/qa/test-qa-l5-acceptance.php
 *
 * Modes (FAMMA_QA_L5_MODE) :
 *   sans-derogation  D4 et V4 : clone « local » sans dérogation, aucune requête.
 *   serveur          tout le reste, avec FAMMA_META_ALLOW_NON_PRODUCTION
 *                    définie dans CE processus seulement (constante PHP).
 *
 * Garde-fous : base famma_integration sur db, http://localhost:8980, garde
 * QA actif, jeton stocké vide ou factice (en clair ou chiffré). Réponses de
 * Meta simulées par pre_http_request ; tout autre hôte est bloqué. Réglages
 * Meta propres au processus (pre_option_*). Options de santé en mémoire
 * (qa-l5-sandbox.php). Données préfixées QA-L5I, supprimées à la fin.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- WP-CLI check entry point, not an autoloaded plugin class file.

defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_QA_L5_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This suite requires WP-CLI and FAMMA_QA_L5_TEST=1.' );
}
if ( 'local' !== wp_get_environment_type() || 'famma_integration' !== (string) getenv( 'FAMMA_TEST_DB_NAME' ) || 'db' !== (string) getenv( 'FAMMA_TEST_DB_HOST' ) || ! defined( 'DB_NAME' ) || 'famma_integration' !== DB_NAME || ! defined( 'DB_HOST' ) || 'db' !== DB_HOST ) {
	throw new RuntimeException( 'Refusing: expected disposable local database identity is not explicitly confirmed.' );
}
if ( 'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) || 8980 !== (int) wp_parse_url( home_url(), PHP_URL_PORT ) ) {
	throw new RuntimeException( 'Refusing: only the QA pixel clone on http://localhost:8980 is allowed.' );
}
foreach ( array( 'FAMMA_META_PIXEL_ID', 'FAMMA_META_ACCESS_TOKEN', 'FAMMA_META_TEST_EVENT_CODE', 'FAMMA_META_GRAPH_VERSION', 'FAMMA_META_ALLOW_NON_PRODUCTION' ) as $famma_constant ) {
	if ( defined( $famma_constant ) && '' !== (string) constant( $famma_constant ) ) {
		throw new RuntimeException( 'Refusing: tracking constants must stay empty before this script decides.' );
	}
}
if ( ! class_exists( 'Famma_Qa_Pixel_Guard' ) || ! Famma_Qa_Pixel_Guard::active() || ! class_exists( '\Famma\Core\Capi_Transport' ) || ! class_exists( '\Famma\Core\Capi_Token_Vault' ) ) {
	throw new RuntimeException( 'Refusing: qa-pixel-guard.php and famma-core 0.3.2 must both be active.' );
}
$famma_stored_token = (string) get_option( 'famma_meta_access_token', '' );
if ( \Famma\Core\Capi_Token_Vault::is_sealed( $famma_stored_token ) ) {
	$famma_stored_token = \Famma\Core\Capi_Token_Vault::open( $famma_stored_token );
}
if ( ! in_array( $famma_stored_token, array( '', 'QA-FAKE-TOKEN-NOT-REAL' ), true ) ) {
	throw new RuntimeException( 'Refusing: the stored Meta token is neither empty nor the QA fake token.' );
}
unset( $famma_stored_token );
require_once is_readable( '/tests/qa/qa-l5-sandbox.php' ) ? '/tests/qa/qa-l5-sandbox.php' : __DIR__ . '/qa-l5-sandbox.php';
if ( ! function_exists( 'add_settings_error' ) ) {
	require_once ABSPATH . 'wp-admin/includes/template.php';
}

/** Captured admin-post redirection (replaces exit). */
final class Famma_Qa_L5_Redirect extends Exception {}

/** Lot 5 server acceptance tests on the QA pixel clone. */
final class Famma_Qa_L5_Acceptance {

	/** Jeton factice du clone, jamais un vrai secret. */
	private const FAKE_TOKEN = 'QA-FAKE-TOKEN-NOT-REAL';

	/** Pixel factice du clone. */
	private const FAKE_PIXEL = '000000000000001';

	/** Utilisateur système factice renvoyé par GET /me (sonde 0.3.3). */
	private const FAKE_SYSTEM_USER = '000000000000042';

	/** Un jour, en secondes. */
	private const DAY = 86400;

	/** Libellé des données de ce passage. */
	private const LABEL = 'QA-L5I';

	/**
	 * Identifiant du passage.
	 *
	 * @var string
	 */
	private static string $run = '';

	/**
	 * Scénario en cours.
	 *
	 * @var string
	 */
	private static string $scenario = '';

	/**
	 * Réglages Meta de ce processus.
	 *
	 * @var array<string,string>
	 */
	private static array $cfg = array();

	/**
	 * Réponse simulée : ok, messages, code190, code100, http500, timeout, wp_error.
	 *
	 * @var string
	 */
	private static string $meta = 'ok';

	/**
	 * En-tête facebook-api-version simulé ; vide = absent, « = » = version demandée.
	 *
	 * @var string
	 */
	private static string $served = '=';

	/**
	 * Requêtes vers graph.facebook.com (sans le jeton).
	 *
	 * @var array<int,array>
	 */
	private static array $requests = array();

	/**
	 * Appels de http_api_debug qui voient le jeton (écart déclaré V2).
	 *
	 * @var int
	 */
	private static int $debug_token = 0;

	/**
	 * Lignes famma-capi.
	 *
	 * @var array<int,string>
	 */
	private static array $logs = array();

	/**
	 * Gestionnaire de journal retenu.
	 *
	 * @var string
	 */
	private static string $handler = '';

	/**
	 * Commandes créées.
	 *
	 * @var array<int,int>
	 */
	private static array $orders = array();

	/**
	 * Produit créé.
	 *
	 * @var int
	 */
	private static int $product = 0;

	/**
	 * Rapport.
	 *
	 * @var array<string,array>
	 */
	private static array $report = array();

	/**
	 * Run the requested mode and print one JSON document.
	 *
	 * @throws RuntimeException On an unknown mode.
	 */
	public static function run(): void {
		$mode      = (string) getenv( 'FAMMA_QA_L5_MODE' );
		self::$run = wp_generate_uuid4();
		self::$cfg = array(
			'pixel'       => self::FAKE_PIXEL,
			'token'       => self::FAKE_TOKEN,
			'orderplaced' => '1',
			'test_mode'   => '0',
			'test_code'   => '',
			'version'     => '',
			'profile'     => 'standard',
			'browser_op'  => '0',
		);
		self::hooks();
		try {
			switch ( $mode ) {
				case 'sans-derogation':
					self::d4();
					self::v4_environment();
					break;
				case 'serveur':
					define( 'FAMMA_META_ALLOW_NON_PRODUCTION', true );
					self::d1();
					self::d5_a3();
					self::d7_normalisation();
					self::g2();
					self::g3();
					self::g4_v2();
					self::v1();
					self::v3();
					self::v4();
					self::v5();
					self::v6();
					self::v7();
					self::v9();
					self::g1(); // Dernier : la constante FAMMA_META_GRAPH_VERSION ne se retire pas.
					break;
				default:
					throw new RuntimeException( 'Unknown FAMMA_QA_L5_MODE.' );
			}
		} finally {
			$cleanup = self::cleanup();
		}
		WP_CLI::line(
			wp_json_encode(
				array(
					'suite'                => 'qa-pixel-lot5-acceptance',
					'mode'                 => $mode,
					'run'                  => self::$run,
					'environment'          => array(
						'famma_core'  => defined( 'FAMMA_CORE_VERSION' ) ? FAMMA_CORE_VERSION : '',
						'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
						'wordpress'   => get_bloginfo( 'version' ),
						'type'        => wp_get_environment_type(),
						'hpos'        => class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),
					),
					'derogation'           => defined( 'FAMMA_META_ALLOW_NON_PRODUCTION' ) ? 'FAMMA_META_ALLOW_NON_PRODUCTION définie dans ce processus' : 'aucune',
					'scenarios'            => self::$report,
					'requests'             => self::$requests,
					'famma_capi'           => self::$logs,
					'http_api_debug_jeton' => self::$debug_token,
					'mails'                => Famma_Qa_L5_Sandbox::$mails,
					'cleanup'              => $cleanup,
					'checks_total'         => self::count_checks( null ),
					'checks_false'         => self::count_checks( false ),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			)
		);
	}

	/** Process-scoped interception and configuration. */
	private static function hooks(): void {
		Famma_Qa_L5_Sandbox::options( Famma_Qa_L5_Sandbox::HEALTH_OPTIONS );
		Famma_Qa_L5_Sandbox::mails();
		add_filter( 'pre_http_request', array( self::class, 'http' ), 10, 3 );
		add_action( 'http_api_debug', array( self::class, 'debug' ), PHP_INT_MAX, 5 );
		add_filter( 'woocommerce_logger_log_message', array( self::class, 'capture_log' ), PHP_INT_MAX, 4 );
		add_filter( 'famma_anti_abuse_limits', array( self::class, 'limits' ), PHP_INT_MAX );
		$map = array(
			'famma_meta_pixel_id'            => 'pixel',
			'famma_meta_access_token'        => 'token',
			'famma_meta_orderplaced_enabled' => 'orderplaced',
			'famma_meta_capi_test_mode'      => 'test_mode',
			'famma_meta_test_event_code'     => 'test_code',
			'famma_meta_graph_version'       => 'version',
			'famma_meta_data_profile'        => 'profile',
			'famma_meta_orderplaced_browser' => 'browser_op',
		);
		foreach ( $map as $option => $key ) {
			add_filter( 'pre_option_' . $option, static fn(): string => (string) self::$cfg[ $key ], PHP_INT_MAX );
		}
		foreach ( array( 'famma_tiktok_pixel_id', 'famma_ga4_measurement_id' ) as $option ) {
			add_filter( 'pre_option_' . $option, '__return_empty_string', PHP_INT_MAX );
		}
		if ( is_readable( '/qa-watchdog/famma-measurement-watchdog.php' ) ) {
			require_once '/qa-watchdog/famma-measurement-watchdog.php';
		}
	}

	// ------------------------------------------------------------------
	// D4 — garde d'environnement, sans dérogation
	// ------------------------------------------------------------------

	/** No real send from a local copy; one journal line. */
	private static function d4(): void {
		self::$scenario = 'D4';
		$order          = self::order( 'D4' );
		$id             = $order->get_id();
		$logs           = count( self::$logs );
		self::queue( $order );
		self::run_queued( $id );
		foreach ( array( 'confirmed', 'shipped', 'delivered' ) as $status ) {
			self::fresh( $id )->update_status( $status, self::LABEL . ' D4.' );
		}
		$direct = \Famma\Core\Capi_Transport::send_events( self::FAKE_PIXEL, self::FAKE_TOKEN, array( 'data' => array() ) );
		$lines  = self::lines( $id, $logs );
		self::check( 'D4', 'zero-requete', array() === self::$requests, '0 requête vers graph.facebook.com (plan : 0 requête réelle)', count( self::$requests ) );
		self::check( 'D4', 'ligne-bloque-environnement', 2 === count( array_filter( $lines, static fn( string $l ): bool => str_contains( $l, 'state=blocked_environment reason=local' ) ) ), '« state=blocked_environment reason=local » pour OrderPlaced et Purchase', $lines );
		self::check( 'D4', 'transport-bloque', 'blocked_environment' === $direct['class'] && 'local' === $direct['reason'] && 0 === $direct['http'], 'Capi_Transport::send_events() renvoie blocked_environment sans requête', $direct );
		$state = \Famma\Core\Capi_Health::evaluate();
		self::check( 'D4', 'sante-orange-environnement', in_array( 'environment', array_column( $state['raisons'], 'code' ), true ), 'raison « environment » (orange) : « Envoi bloqué : environnement local »', $state );
		self::$report['D4']['observations'] = array(
			'commande' => $id,
			'cod_paye' => self::fresh( $id )->get_meta( '_famma_cod_paid' ),
			'remarque' => 'Production simulée : la constante FAMMA_META_ALLOW_NON_PRODUCTION (mode « serveur ») ; wp_get_environment_type() est figé à « local » par le wp-config du clone.',
		);
	}

	/** V4, environment part: the probe sends nothing outside production. */
	private static function v4_environment(): void {
		self::$scenario = 'V4';
		$before         = count( self::$requests );
		$result         = \Famma\Core\Capi_Probe::run();
		self::check( 'V4', 'sonde-hors-production-aucune-requete', count( self::$requests ) === $before && 'blocked_environment' === $result['class'], 'environnement autre que production : aucune requête', $result );
	}

	// ------------------------------------------------------------------
	// D1 — relance bornée, envoi « en cours » repris, même event_id
	// ------------------------------------------------------------------

	/** Bounded retries. */
	private static function d1(): void {
		self::$scenario = 'D1';

		// a) OrderPlaced : échec réseau, relance à 5 min, puis 500 → relance à 30 min, puis acceptée.
		$order = self::order( 'D1-chaine' );
		$id    = $order->get_id();
		self::queue( $order );
		self::$meta = 'wp_error';
		self::run_queued( $id );
		$r1         = self::retries( $id, 'OrderPlaced' );
		self::$meta = 'http500';
		self::run_retry( $id, 'OrderPlaced' );
		$r2         = self::retries( $id, 'OrderPlaced' );
		self::$meta = 'ok';
		self::run_retry( $id, 'OrderPlaced' );
		$r3   = self::retries( $id, 'OrderPlaced' );
		$sent = self::graph( 'OrderPlaced', $id );
		$ids  = array_values( array_unique( array_map( static fn( array $r ): string => (string) ( $r['body']['data'][0]['event_id'] ?? '' ), $sent ) ) );
		self::check( 'D1', 'chaine-5min', 1 === count( $r1 ) && $r1[0]['dans_s'] > 240 && $r1[0]['dans_s'] <= 300, '1re relance à 5 min', $r1 );
		self::check( 'D1', 'chaine-30min', 1 === count( $r2 ) && $r2[0]['dans_s'] > 1740 && $r2[0]['dans_s'] <= 1800, '2e relance à 30 min (la chaîne continue, correctif fe7dfe8)', $r2 );
		self::check(
			'D1',
			'acceptee-une-fois-meme-event-id',
			3 === count( $sent ) && array( 'famma_orderplaced_' . $id ) === $ids && array() === $r3 && 'accepted' === ( self::registry( 'OrderPlaced', 'famma_orderplaced_' . $id )['state'] ?? '' ) && 3 === (int) ( self::registry( 'OrderPlaced', 'famma_orderplaced_' . $id )['attempt_count'] ?? 0 ),
			'3 tentatives (réseau, 500, acceptée), un seul event_id famma_orderplaced_<id>, plus aucune relance',
			array(
				'tentatives' => count( $sent ),
				'event_ids'  => $ids,
				'registre'   => self::registry( 'OrderPlaced', 'famma_orderplaced_' . $id ),
				'restantes'  => $r3,
			)
		);

		// b) Purchase « en cours » abandonné depuis 20 min : repris comme incertain, relancé, accepté.
		$order = self::order( 'D1-en-cours' );
		$id    = $order->get_id();
		foreach ( array( 'confirmed', 'shipped' ) as $status ) {
			self::fresh( $id )->update_status( $status, self::LABEL . ' D1.' );
		}
		self::$cfg['pixel'] = '';
		self::fresh( $id )->update_status( 'delivered', self::LABEL . ' D1 : livraison (pixel vide : aucun envoi).' );
		self::$cfg['pixel'] = self::FAKE_PIXEL;
		$registry           = \Famma\Core\Capi_Delivery_Registry::instance();
		$claim              = $registry->acquire( self::FAKE_PIXEL, 'Purchase', 'famma_purchase_' . $id, $id, time() - 1200, \Famma\Core\Config::CAPI_MAX_ATTEMPTS, 0, false );
		global $wpdb;
		$others = count( $registry->stale_sending( \Famma\Core\Config::CAPI_STALE_SENDING ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- simulate a process interrupted 20 minutes ago (QA row of this run only).
		$wpdb->update( $registry->table(), array( 'updated_at' => gmdate( 'Y-m-d H:i:s', time() - 1200 ) ), array( 'event_key' => (string) $claim['key'] ) );
		$logs    = count( self::$logs );
		$rescued = \Famma\Core\Capi_Retry::rescue_stale();
		$after   = self::registry( 'Purchase', 'famma_purchase_' . $id );
		$pending = self::retries( $id, 'Purchase' );
		$lines   = self::lines( $id, $logs );
		self::check(
			'D1',
			'en-cours-repris-incertain',
			'sending' === ( $claim['state'] ?? '' ) && ! empty( $claim['owned'] ) && 'uncertain' === ( $after['state'] ?? '' ) && 1 === count( $pending ) && 1 === count( array_filter( $lines, static fn( string $l ): bool => str_contains( $l, 'state=stale_sending reason=retry:scheduled' ) ) ),
			'ligne « sending » de plus de 15 min : incertaine, relance planifiée, ligne stale_sending',
			array(
				'avant'                          => $claim['state'] ?? null,
				'apres'                          => $after,
				'relances'                       => $pending,
				'journal'                        => $lines,
				'repris'                         => $rescued,
				'lignes_en_cours_perimees_avant' => $others,
			)
		);
		self::run_retry( $id, 'Purchase' );
		$sent = self::graph( 'Purchase', $id );
		self::check(
			'D1',
			'en-cours-relance-acceptee',
			1 === count( $sent ) && 'famma_purchase_' . $id === ( $sent[0]['body']['data'][0]['event_id'] ?? '' ) && 'accepted' === ( self::registry( 'Purchase', 'famma_purchase_' . $id )['state'] ?? '' ) && (bool) self::fresh( $id )->get_meta( '_famma_purchase_sent' ), // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- both sides are computed values, no literal to put first.
			'1 Purchase accepté, event_id famma_purchase_<id>',
			array(
				'envois'   => count( $sent ),
				'registre' => self::registry( 'Purchase', 'famma_purchase_' . $id ),
			)
		);

		// c) Borne des 6 jours : un échec sur un OrderPlaced de presque 6 jours n'est pas relancé.
		$order = self::order( 'D1-borne', 'checkout', true, 6 * self::DAY - 120 );
		$id    = $order->get_id();
		self::queue( $order );
		$logs       = count( self::$logs );
		self::$meta = 'wp_error';
		self::run_queued( $id );
		self::$meta = 'ok';
		$lines      = self::lines( $id, $logs );
		self::check( 'D1', 'borne-6-jours', array() === self::retries( $id, 'OrderPlaced' ) && 1 === count( array_filter( $lines, static fn( string $l ): bool => str_contains( $l, 'retry=too_old' ) ) ), 'relance refusée (retry=too_old) : 5 min plus tard, l\'événement dépasserait 6 jours', $lines );
	}

	// ------------------------------------------------------------------
	// D5 / A3 — « Terminée » en COD, commande admin, exclusion interne
	// ------------------------------------------------------------------

	/** Completed COD, admin order, internal visitor. */
	private static function d5_a3(): void {
		self::$scenario = 'D5';
		$admin          = self::admin();
		$order          = self::order( 'D5-terminee' );
		$id             = $order->get_id();
		foreach ( array( 'confirmed', 'shipped', 'completed' ) as $status ) {
			self::fresh( $id )->update_status( $status, self::LABEL . ' D5.' );
		}
		$p1 = self::graph( 'Purchase', $id );
		self::fresh( $id )->update_status( 'delivered', self::LABEL . ' D5 : livrée après terminée.' );
		$p2 = self::graph( 'Purchase', $id );
		self::check(
			'D5',
			'terminee-encaisse-puis-1-purchase',
			1 === count( $p1 ) && 'yes' === ( $p1[0]['cod_paid_at_send'] ?? '' ) && 'yes' === self::fresh( $id )->get_meta( '_famma_cod_paid' ),
			'« Terminée » : _famma_cod_paid posé avant le Purchase, 1 seul envoi',
			array(
				'envois'        => count( $p1 ),
				'cod_a_l_envoi' => $p1[0]['cod_paid_at_send'] ?? null,
			)
		);
		self::check( 'D5', 'puis-livree-aucun-second', 1 === count( $p2 ), 'puis « Livrée » : aucun second Purchase', count( $p2 ) );

		$admin_order = self::order( 'D5-admin', 'admin', false );
		$aid         = $admin_order->get_id();
		foreach ( array( 'confirmed', 'shipped', 'delivered' ) as $status ) {
			self::fresh( $aid )->update_status( $status, self::LABEL . ' D5 admin.' );
		}
		$event = self::graph( 'Purchase', $aid )[0]['body']['data'][0] ?? array();
		self::check( 'D5', 'commande-admin-action-source', 'other' === ( $event['action_source'] ?? '' ) && ! isset( $event['event_source_url'] ) && ! isset( $event['user_data']['client_user_agent'] ), 'action_source « other », sans event_source_url ni client_user_agent', array_intersect_key( $event, array_flip( array( 'action_source', 'event_source_url', 'event_id' ) ) ) );

		// A3 : l'administrateur (visiteur interne, S1) passe une commande client à « Livrée ».
		self::$scenario = 'A3';
		wp_set_current_user( $admin );
		$internal = \Famma\Core\Tracking::is_internal_visitor();
		$browser  = \Famma\Core\Tracking::browser_tracking_allowed();
		$client   = self::order( 'A3' );
		$cid      = $client->get_id();
		foreach ( array( 'confirmed', 'shipped', 'delivered' ) as $status ) {
			self::fresh( $cid )->update_status( $status, self::LABEL . ' A3 : par l\'administrateur.' );
		}
		self::fresh( $cid )->update_status( 'shipped', self::LABEL . ' A3.' );
		self::fresh( $cid )->update_status( 'delivered', self::LABEL . ' A3 : seconde livraison.' );
		wp_set_current_user( 0 );
		$sent = self::graph( 'Purchase', $cid );
		self::check(
			'A3',
			'purchase-malgre-exclusion',
			$internal && ! $browser && 1 === count( $sent ) && 'website' === ( $sent[0]['body']['data'][0]['action_source'] ?? '' ) && 'Mozilla/5.0 (QA-L5I synthetic user agent)' === ( $sent[0]['body']['data'][0]['user_data']['client_user_agent'] ?? '' ),
			'visiteur interne (pixel coupé) : 1 seul Purchase serveur, avec l\'UA du client et non celui de l\'admin',
			array(
				'interne'          => $internal,
				'suivi_navigateur' => $browser,
				'envois'           => count( $sent ),
				'action_source'    => $sent[0]['body']['data'][0]['action_source'] ?? null,
			)
		);
	}

	// ------------------------------------------------------------------
	// D7 — normalisation user_data (doc Meta, cas du T1.4)
	// ------------------------------------------------------------------

	/** Normalisation against the Meta documentation (T1.4 cases). */
	private static function d7_normalisation(): void {
		self::$scenario = 'D7';
		$cases          = array(
			array( 'ph', '22 123 456', array( '21622123456' ) ),
			array( 'ph', '+216 22123456', array( '21622123456' ) ),
			array( 'ph', '0021622123456', array( '21622123456' ) ),
			array( 'ph', '216 22 123 456', array( '21622123456' ) ),
			array( 'fn', 'Élodie', array( 'élodie', 'elodie' ) ),
			array( 'fn', 'ÉLODIE', array( 'élodie', 'elodie' ) ),
			array( 'fn', 'محمد', array( 'محمد' ) ),
			array( 'ln', 'Ben-Salah', array( 'bensalah', 'ben salah' ) ),
			array( 'ct', 'Médenine', array( 'médenine', 'medenine' ) ),
			array( 'ct', 'Ben Arous', array( 'benarous' ) ),
			array( 'ct', 'مدنين', array( 'مدنين' ) ),
			array( 'st', 'TN-82', array( 'tn82', '82', 'medenine', 'médenine' ) ),
			array( 'st', 'TN-51', array( 'tn51', '51', 'sousse' ) ),
			array( 'em', 'Client.Test@Example.INVALID', array( 'client.test@example.invalid' ) ),
			array( 'country', 'TN', array( 'tn' ) ),
		);
		$rows           = array();
		$bad            = 0;
		foreach ( $cases as $case ) {
			$order = new WC_Order();
			$order->set_billing_country( 'TN' );
			switch ( $case[0] ) {
				case 'ph':
					$order->set_billing_phone( $case[1] );
					break;
				case 'fn':
					$order->set_billing_first_name( $case[1] );
					break;
				case 'ln':
					$order->set_billing_last_name( $case[1] );
					break;
				case 'ct':
					$order->set_billing_city( $case[1] );
					break;
				case 'st':
					$order->set_billing_state( $case[1] );
					break;
				case 'em':
					$order->set_billing_email( $case[1] );
					break;
				case 'country':
					$order->set_billing_country( $case[1] );
					break;
			}
			$data    = \Famma\Core\Capi_User_Data::build( $order, false );
			$hash    = (string) ( $data[ $case[0] ] ?? '' );
			$matched = '';
			foreach ( $case[2] as $expected ) {
				if ( hash( 'sha256', $expected ) === $hash ) {
					$matched = $expected;
				}
			}
			$bad   += '' === $matched ? 1 : 0;
			$rows[] = array(
				'champ'      => $case[0],
				'entree'     => $case[1],
				'attendu'    => implode( ' | ', $case[2] ),
				'conforme_a' => '' !== $matched ? $matched : '(aucun : écart)',
			);
		}
		self::check( 'D7', 'normalisation-doc-meta', 0 === $bad, '15 cas du T1.4 conformes (0.3.1 : 8 écarts)', $rows );
	}

	// ------------------------------------------------------------------
	// G1 à G4 — version de l'API Graph
	// ------------------------------------------------------------------

	/** Constant > option > default. Runs last. */
	private static function g1(): void {
		self::$scenario       = 'G1';
		$cfg                  = \Famma\Core\Config::instance();
		self::$cfg['version'] = '';
		$default              = array( $cfg->meta_graph_version(), $cfg->meta_graph_version_source() );
		self::$cfg['version'] = 'v25.0';
		$option               = array( $cfg->meta_graph_version(), $cfg->meta_graph_version_source() );
		self::$cfg['version'] = 'abc';
		$invalid_option       = array( $cfg->meta_graph_version(), $cfg->meta_graph_version_source() );
		self::$cfg['version'] = 'v25.0';
		$bad_filter           = static fn(): string => 'v26';
		add_filter( 'famma_capi_graph_version', $bad_filter );
		$filtered_bad         = $cfg->meta_graph_version();
		remove_filter( 'famma_capi_graph_version', $bad_filter );
		define( 'FAMMA_META_GRAPH_VERSION', 'v24.0' );
		$constant = array( $cfg->meta_graph_version(), $cfg->meta_graph_version_source() );
		self::check( 'G1', 'defaut', array( \Famma\Core\Config::META_GRAPH_VERSION_DEFAULT, 'default' ) === $default, array( 'v26.0', 'default' ), $default );
		self::check( 'G1', 'option', array( 'v25.0', 'option' ) === $option, array( 'v25.0', 'option' ), $option );
		self::check( 'G1', 'option-invalide-ignoree', array( 'v26.0', 'default' ) === $invalid_option, 'option « abc » ignorée : v26.0 (défaut)', $invalid_option );
		self::check( 'G1', 'filtre-hors-format-ignore', 'v25.0' === $filtered_bad, 'filtre « v26 » ignoré', $filtered_bad );
		self::check( 'G1', 'constante-prioritaire', array( 'v24.0', 'constant' ) === $constant, 'constante v24.0 > option v25.0', $constant );
		$order = self::order( 'G1-constante' );
		self::queue( $order );
		self::run_queued( $order->get_id() );
		self::check( 'G1', 'envoi-suit-la-constante', '/v24.0/' . self::FAKE_PIXEL . '/events' === ( self::graph( 'OrderPlaced', $order->get_id() )[0]['path'] ?? '' ), '/v24.0/<pixel>/events', self::graph( 'OrderPlaced', $order->get_id() )[0]['path'] ?? null );
	}

	/** Invalid values refused in the « Mesure » screen sanitizer. */
	private static function g2(): void {
		self::$scenario = 'G2';
		\Famma\Core\Measurement_Settings::instance()->register();
		self::$cfg['version'] = 'v25.0';
		$rows                 = array();
		$ok                   = true;
		foreach ( array( '21', 'v21', 'abc', 'v21.0.1' ) as $invalid ) {
			$GLOBALS['wp_settings_errors'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reset the Settings API error list between cases.
			$kept                          = sanitize_option( 'famma_meta_graph_version', $invalid );
			$errors                        = array_column( get_settings_errors( 'famma_meta_graph_version' ), 'code' );
			$ok                            = $ok && 'v25.0' === $kept && in_array( 'famma_meta_graph_version_invalid', $errors, true );
			$rows[ $invalid ]              = array(
				'garde'   => $kept,
				'erreurs' => $errors,
			);
		}
		$GLOBALS['wp_settings_errors'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reset the Settings API error list.
		$valid                         = sanitize_option( 'famma_meta_graph_version', 'v26.0' );
		self::$cfg['version']          = '';
		self::check( 'G2', 'valeurs-invalides-refusees', $ok, 'refusées avec un message, l\'ancienne valeur v25.0 gardée', $rows );
		self::check( 'G2', 'valeur-valide-acceptee', 'v26.0' === $valid && array() === get_settings_errors( 'famma_meta_graph_version' ), 'v26.0 acceptée sans message', $valid );
	}

	/** « Test CAPI » button and a real send share the URL and version. */
	private static function g3(): void {
		self::$scenario = 'G3';
		$admin          = self::admin();
		$results        = array();
		foreach ( array( '', 'v25.0' ) as $version ) {
			self::$cfg['version'] = $version;
			wp_set_current_user( $admin );
			$before   = count( self::$requests );
			$_POST    = array(
				'_wpnonce'              => wp_create_nonce( 'famma_capi_test' ),
				'famma_capi_test_code'  => 'TEST12345',
				'famma_capi_test_event' => 'Purchase',
				'famma_capi_test_value' => '49',
			);
			$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- synthetic POST; the handler verifies its nonce.
			$location = self::admin_post( array( \Famma\Core\Tracking_Settings::instance(), 'send_test_event' ) );
			$_POST    = array();
			$_REQUEST = array();
			wp_set_current_user( 0 );
			$button = self::$requests[ $before ] ?? array();
			$order  = self::order( 'G3-' . ( '' === $version ? 'defaut' : $version ) );
			self::queue( $order );
			self::run_queued( $order->get_id() );
			$real = self::graph( 'OrderPlaced', $order->get_id() )[0] ?? array();
			$results[ '' === $version ? 'defaut' : $version ] = array(
				'bouton'    => $button['url'] ?? null,
				'reel'      => $real['url'] ?? null,
				'resultat'  => $location,
				'test_code' => $button['body']['test_event_code'] ?? null,
			);
		}
		self::$cfg['version'] = '';
		$same                 = static fn( array $r, string $v ): bool => 'https://graph.facebook.com/' . $v . '/' . self::FAKE_PIXEL . '/events' === $r['bouton'] && $r['bouton'] === $r['reel'] && str_contains( (string) $r['resultat'], 'famma_capi_result=accepted' );
		self::check( 'G3', 'meme-url-defaut', $same( $results['defaut'], 'v26.0' ), 'bouton et envoi réel : https://graph.facebook.com/v26.0/<pixel>/events', $results['defaut'] );
		self::check( 'G3', 'meme-url-option', $same( $results['v25.0'], 'v25.0' ), 'option v25.0 : la même version des deux côtés', $results['v25.0'] );
	}

	/** Served version header (G4) and response classes (V2). */
	private static function g4_v2(): void {
		self::$scenario = 'V2';
		$cases          = array(
			'en-tete-identique' => array( 'ok', '=', 'accepted', 'accepted' ),
			'en-tete-different' => array( 'ok', 'v25.0', 'accepted_warning', 'accepted' ),
			'en-tete-absent'    => array( 'ok', '', 'accepted', 'accepted' ),
			'messages'          => array( 'messages', '=', 'accepted_warning', 'accepted' ),
			'code190'           => array( 'code190', '=', 'access', 'rejected' ),
			'code100'           => array( 'code100', '=', 'contract', 'rejected' ),
			'http500'           => array( 'http500', '=', 'transient', 'uncertain' ),
			'timeout'           => array( 'timeout', '', 'transient', 'transport_uncertain' ),
		);
		$seen           = array();
		foreach ( $cases as $name => $case ) {
			Famma_Qa_L5_Sandbox::set( 'famma_capi_health_signals', array() );
			Famma_Qa_L5_Sandbox::set( 'famma_capi_health', array() );
			self::$meta   = $case[0];
			self::$served = $case[1];
			$order        = self::order( 'V2-' . $name );
			$id           = $order->get_id();
			$logs         = count( self::$logs );
			self::queue( $order );
			self::run_queued( $id );
			self::$meta   = 'ok';
			self::$served = '=';
			$lines        = self::lines( $id, $logs );
			$line         = (string) ( $lines[0] ?? '' );
			preg_match( '/ class=([a-z_]+)/', $line, $class );
			preg_match( '/ served=([^ ]+)/', $line, $served );
			preg_match( '/ state=([a-z_]+)/', $line, $state );
			preg_match( '/ retry=([^ ]+)/', $line, $retry );
			$health        = \Famma\Core\Capi_Health::evaluate();
			$seen[ $name ] = array(
				'classe'              => $class[1] ?? null,
				'etat'                => $state[1] ?? null,
				'servie'              => $served[1] ?? null,
				'relance'             => $retry[1] ?? null,
				'sante'               => array_map( static fn( array $r ): string => $r['code'] . ':' . $r['niveau'], $health['raisons'] ),
				'statut'              => $health['statut'],
				'jeton_dans_la_ligne' => str_contains( $line, self::FAKE_TOKEN ) || str_contains( strtolower( $line ), strtolower( substr( self::FAKE_TOKEN, 0, 12 ) ) ),
			);
			self::check( 'V2', 'classe-' . $name, $case[2] === ( $class[1] ?? '' ) && $case[3] === ( $state[1] ?? '' ), 'classe ' . $case[2] . ', état ' . $case[3], $seen[ $name ] ); // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- both sides are computed values, no literal to put first.
		}
		$codes = static fn( string $n ): array => $seen[ $n ]['sante'];
		self::check( 'V2', 'acces-rouge', in_array( 'access:rouge', $codes( 'code190' ), true ) && 'rouge' === $seen['code190']['statut'], 'code 190 : santé rouge « access »', $seen['code190'] );
		self::check( 'V2', 'contrat-orange', in_array( 'contract:orange', $codes( 'code100' ), true ), 'code 100 : orange « contract »', $seen['code100'] );
		self::check( 'V2', 'transitoire-relance', 'scheduled' === $seen['http500']['relance'] && 'scheduled' === $seen['timeout']['relance'], '500 et délai dépassé : relance planifiée', array( $seen['http500'], $seen['timeout'] ) );
		self::check( 'V2', 'en-tete-absent-inconnue-sans-alerte', 'unknown' === $seen['en-tete-absent']['servie'] && ! in_array( 'version_served:orange', $codes( 'en-tete-absent' ), true ), 'served=unknown (affiché « inconnue »), aucune raison version_served', $seen['en-tete-absent'] );
		self::check( 'G4', 'en-tete-journalise', 'v26.0' === $seen['en-tete-identique']['servie'] && 'v25.0' === $seen['en-tete-different']['servie'], 'served=<en-tête facebook-api-version> dans la ligne famma-capi', array( $seen['en-tete-identique']['servie'], $seen['en-tete-different']['servie'] ) );
		self::check( 'G4', 'alerte-version-differente', in_array( 'version_served:orange', $codes( 'en-tete-different' ), true ) && ! in_array( 'version_served:orange', $codes( 'en-tete-identique' ), true ), 'version servie différente : raison orange « version_served » ; identique : aucune', array( $codes( 'en-tete-different' ), $codes( 'en-tete-identique' ) ) );
		ob_start();
		Famma_Qa_L5_Sandbox::set(
			'famma_capi_health_signals',
			array(
				'served' => array(
					'requested' => 'v26.0',
					'served'    => 'v25.0',
					'mismatch'  => true,
					'at'        => time(),
				),
			)
		);
		\Famma\Core\Capi_Health::evaluate();
		\Famma\Core\Capi_Health_View::instance()->render_panel();
		$panel = (string) ob_get_clean();
		self::check( 'G4', 'bandeau-version-servie', str_contains( $panel, 'autre version que celle demandée (v25.0)' ), 'bandeau « Meta a répondu avec une autre version que celle demandée (v25.0) » (raison orange ; la couleur du bandeau suit la pire raison du clone)', wp_strip_all_tags( $panel ) );
		self::check( 'V2', 'jeton-absent-du-journal', 0 === count( array_filter( self::$logs, static fn( string $l ): bool => str_contains( $l, self::FAKE_TOKEN ) ) ) && ! in_array( true, array_column( $seen, 'jeton_dans_la_ligne' ), true ), 'aucune ligne famma-capi ne contient le jeton', count( self::$logs ) );
		self::$report['V2']['observations'] = array(
			'cas'              => $seen,
			'http_api_debug'   => self::$debug_token,
			'ecart_v2_declare' => 'http_api_debug reçoit les arguments de la requête, donc le jeton (limite de l\'API HTTP de WordPress, documentée dans capi-transport.php) : compté à part, pas une fuite de famma-core.',
		);
	}

	// ------------------------------------------------------------------
	// V1, V3, V4, V5
	// ------------------------------------------------------------------

	/** Version timetable. */
	private static function v1(): void {
		self::$scenario = 'V1';
		$at             = static fn( string $date ): int => (int) strtotime( $date . ' UTC' );
		$critical       = \Famma\Core\Capi_Graph_Version::status( 'v21.0', $at( '2026-12-02 00:00:00' ) );
		$warning        = \Famma\Core\Capi_Graph_Version::status( 'v22.0', $at( '2027-05-20 00:00:00' ) - 59 * self::DAY );
		$before         = \Famma\Core\Capi_Graph_Version::status( 'v22.0', $at( '2027-05-20 00:00:00' ) - 61 * self::DAY );
		$unknown        = \Famma\Core\Capi_Graph_Version::status( 'v26.0', $at( '2027-05-01 00:00:00' ) );
		self::check( 'V1', 'critique-v21-apres-01-12', 'critical' === $critical['level'], 'v21.0 au 02/12/2026 : critique', $critical );
		self::check( 'V1', 'avertissement-j-59', 'warning' === $warning['level'] && 59 === $warning['days'] && 'ok' === $before['level'], 'J-59 : avertissement ; J-61 : rien', array( $warning, $before ) );
		self::check( 'V1', 'date-inconnue', 'ok' === $unknown['level'] && -1 === $unknown['days'], 'v26.0 (fin non publiée) : rien', $unknown );
		self::$cfg['version'] = 'v21.0';
		$state                = \Famma\Core\Capi_Health::evaluate( $at( '2026-12-02 00:00:00' ) );
		self::$cfg['version'] = '';
		self::check( 'V1', 'sante-rouge-version-critique', in_array( 'version_critical', array_column( $state['raisons'], 'code' ), true ) && 'rouge' === $state['statut'], 'santé rouge « version_critical » (bandeau permanent)', array_column( $state['raisons'], 'code' ) );
		self::$report['V1']['observations'] = array( 'limite' => 'Capi_Graph_Version::notices() lit time() : le bandeau J-60 masquable n\'est pas affichable sans changer l\'horloge du clone ; le niveau est vérifié par status( $version, $now ), et l\'unicité par l\'option famma_meta_version_notice_dismissed (non jouée ici).' );
	}

	/** One wp_remote_post towards graph.facebook.com. */
	private static function v3(): void {
		self::$scenario = 'V3';
		$dir            = WP_PLUGIN_DIR . '/famma-core/';
		$files          = array_merge( array( $dir . 'famma-core.php' ), (array) glob( $dir . 'includes/*.php' ) );
		$posts          = array();
		$gets           = array();
		foreach ( $files as $file ) {
			$source = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- read-only scan of the mounted plugin.
			foreach ( array(
				'wp_remote_post' => &$posts,
				'wp_remote_get'  => &$gets,
			) as $fn => &$list ) {
				$count = preg_match_all( '/\b' . $fn . '\s*\(/', $source );
				if ( $count > 0 ) {
					$list[ str_replace( $dir, '', $file ) ] = $count;
				}
			}
			unset( $list );
		}
		$hosts = array();
		foreach ( $files as $file ) {
			$code = (string) preg_replace( array( '#/\*.*?\*/#s', '#^\s*//.*$#m' ), '', (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- read-only scan of the mounted plugin.
			if ( str_contains( $code, 'graph.facebook.com' ) ) {
				$hosts[] = str_replace( $dir, '', $file );
			}
		}
		self::check( 'V3', 'un-seul-wp-remote-post', array( 'includes/capi-transport.php' => 1 ) === $posts, '1 seul wp_remote_post dans famma-core, dans includes/capi-transport.php', $posts );
		self::check( 'V3', 'hote-graph-en-un-seul-endroit', array( 'includes/capi-transport.php' ) === $hosts, 'graph.facebook.com présent dans le code (hors commentaires) de capi-transport.php seulement', $hosts );
		self::$report['V3']['observations'] = array(
			'wp_remote_get' => $gets,
			'remarque'      => 'Le bouton « Test CAPI » (G3) et les envois réels passent par Capi_Transport::send_events() ; la sonde par Capi_Transport::probe() (GET /me, 0.3.3).',
		);
	}

	/** Probe on GET /me (0.3.3): 200, 190, « Missing Permission », own error. */
	private static function v4(): void {
		self::$scenario = 'V4';
		$rows           = array();
		foreach ( array(
			'ok'                 => 'vert',
			'code190'            => 'probe_token',
			'missing_permission' => 'probe_permission',
			'http500'            => 'probe_unavailable',
		) as $mode => $expected ) {
			Famma_Qa_L5_Sandbox::set( 'famma_capi_health_signals', array() );
			self::$meta    = $mode;
			$before        = count( self::$requests );
			$result        = \Famma\Core\Capi_Probe::run();
			self::$meta    = 'ok';
			$request       = self::$requests[ $before ] ?? array();
			$state         = \Famma\Core\Capi_Health::state();
			$rows[ $mode ] = array(
				'classe'       => $result['class'],
				'methode'      => $request['method'] ?? null,
				'chemin'       => $request['path'] ?? null,
				'requete'      => $request['query'] ?? null,
				'jeton_url'    => $request['token_in_url'] ?? null,
				'jeton_entete' => $request['token_in_header'] ?? null,
				'raisons'      => array_map( static fn( array $r ): string => $r['code'] . ':' . $r['niveau'], $state['raisons'] ),
			);
		}
		$probe_ok = static fn( array $r ): bool => 'GET' === $r['methode'] && '/v26.0/me' === $r['chemin'] && 'fields=id' === $r['requete'] && false === $r['jeton_url'] && true === $r['jeton_entete'];
		self::check( 'V4', 'sonde-200-vert', 'accepted' === $rows['ok']['classe'] && $probe_ok( $rows['ok'] ) && ! preg_grep( '/^probe_/', $rows['ok']['raisons'] ), 'GET /v26.0/me?fields=id, jeton dans Authorization : aucune raison sonde', $rows['ok'] );
		self::check( 'V4', 'sonde-ne-lit-plus-le-pixel', array() === array_filter( $rows, static fn( array $r ): bool => str_contains( (string) $r['chemin'], self::FAKE_PIXEL ) ), 'aucune sonde sur /<pixel> (0.3.2 : « (#100) Missing Permission » en production)', array_column( $rows, 'chemin' ) );
		self::check( 'V4', 'sonde-190-rouge', in_array( 'probe_token:rouge', $rows['code190']['raisons'], true ) && $probe_ok( $rows['code190'] ), 'rouge « probe_token »', $rows['code190'] );
		self::check( 'V4', 'sonde-droits-orange', in_array( 'probe_permission:orange', $rows['missing_permission']['raisons'], true ) && ! in_array( 'probe_unavailable:orange', $rows['missing_permission']['raisons'], true ) && ! preg_grep( '/^probe_.*:rouge$/', $rows['missing_permission']['raisons'] ), '« Missing Permission » sur /me : orange « droits du jeton à vérifier », jamais rouge', $rows['missing_permission'] );
		self::check( 'V4', 'sonde-indisponible-orange', in_array( 'probe_unavailable:orange', $rows['http500']['raisons'], true ) && ! preg_grep( '/:rouge$/', array_filter( $rows['http500']['raisons'], static fn( string $r ): bool => str_starts_with( $r, 'probe' ) ) ), 'orange « probe_unavailable », jamais rouge', $rows['http500'] );
		$posts = array_filter( self::$requests, static fn( array $r ): bool => 'V4' === $r['scenario'] && 'GET' !== $r['method'] );
		self::check( 'V4', 'jamais-post-events', array() === $posts, 'aucune requête POST /events pendant la sonde', count( $posts ) );
	}

	/** Data profile standard / minimal. */
	private static function v5(): void {
		self::$scenario = 'V5';
		$custom         = array();
		foreach ( array( 'standard', 'minimal' ) as $profile ) {
			self::$cfg['profile'] = $profile;
			$order                = self::order( 'V5-' . $profile );
			self::queue( $order );
			self::run_queued( $order->get_id() );
			foreach ( array( 'confirmed', 'shipped', 'delivered' ) as $status ) {
				self::fresh( $order->get_id() )->update_status( $status, self::LABEL . ' V5.' );
			}
			foreach ( array( 'OrderPlaced', 'Purchase' ) as $event ) {
				$custom[ $profile ][ $event ] = array_keys( (array) ( self::graph( $event, $order->get_id() )[0]['body']['data'][0]['custom_data'] ?? array() ) );
			}
			$health[ $profile ] = array_column( \Famma\Core\Capi_Health::evaluate()['raisons'], 'code' );
		}
		self::$cfg['profile'] = 'standard';
		$excluded             = array( 'famma_stage', 'famma_product', 'content_name' );
		$standard_ok          = array() === array_intersect( $excluded, array_merge( $custom['standard']['OrderPlaced'], $custom['standard']['Purchase'] ) ) && in_array( 'content_ids', $custom['standard']['Purchase'], true );
		$minimal_ok           = array( 'currency', 'value' ) === $custom['minimal']['OrderPlaced'] && array( 'currency', 'value' ) === $custom['minimal']['Purchase'];
		self::check( 'V5', 'standard-sans-champs-perso', $standard_ok, 'ni famma_stage, ni famma_product, ni content_name (CAPI) ; content_ids présent', $custom['standard'] );
		self::check( 'V5', 'minimal-liste-prevue', $minimal_ok, 'seulement currency et value', $custom['minimal'] );
		self::check( 'V5', 'minimal-signale', in_array( 'profile_minimal', $health['minimal'], true ) && ! in_array( 'profile_minimal', $health['standard'], true ), 'raison orange « profile_minimal » en minimal seulement', $health );
		$GLOBALS['wp_settings_errors'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reset the Settings API error list.
		\Famma\Core\Measurement_Settings::instance()->register();
		$refused = sanitize_option( 'famma_meta_data_profile', 'complet' );
		self::check( 'V5', 'valeur-hors-liste-refusee', 'standard' === $refused && in_array( 'famma_meta_data_profile_invalid', array_column( get_settings_errors( 'famma_meta_data_profile' ), 'code' ), true ), '« complet » refusé, message, valeur gardée', $refused );
		self::$report['V5']['observations'] = array( 'nonce_droits' => 'Le réglage passe par l\'API Settings (groupe famma_tracking, options.php) : nonce « famma_tracking-options » et droit manage_options vérifiés par WordPress ; partie navigateur du profil : testeur navigateur.' );
	}

	// ------------------------------------------------------------------
	// V6 — santé : battement de cœur, e-mails, Purchase manquant
	// ------------------------------------------------------------------

	/** Health rules and alert e-mails. */
	private static function v6(): void {
		self::$scenario = 'V6';
		$now            = time();
		$alerts         = static fn(): int => count( array_filter( Famma_Qa_L5_Sandbox::$mails, static fn( string $s ): bool => str_contains( $s, 'mesure Meta en alerte' ) ) );
		Famma_Qa_L5_Sandbox::set(
			'famma_capi_health',
			array(
				'statut'     => 'vert',
				'raisons'    => array(),
				'verifie_le' => $now - 600,
			)
		);
		Famma_Qa_L5_Sandbox::set( 'famma_capi_health_signals', array() );
		Famma_Qa_L5_Sandbox::set( 'famma_capi_health_mail', 0 );
		Famma_Qa_L5_Sandbox::set( 'famma_capi_health_mail_codes', array() );
		Famma_Qa_L5_Sandbox::set( 'famma_capi_heartbeat', $now - 37 * HOUR_IN_SECONDS );
		$mails0 = $alerts();
		$s1     = \Famma\Core\Capi_Health::evaluate( $now );
		$mails1 = $alerts();
		Famma_Qa_L5_Sandbox::set( 'famma_capi_heartbeat', $now );
		Famma_Qa_L5_Sandbox::set( 'famma_capi_health_signals', array( 'registry_unavailable' => array( 'at' => $now ) ) );
		$s2     = \Famma\Core\Capi_Health::evaluate( $now + 600 );
		$mails2 = $alerts();
		$s3     = \Famma\Core\Capi_Health::evaluate( $now + 25 * HOUR_IN_SECONDS );
		$mails3 = $alerts();
		Famma_Qa_L5_Sandbox::set( 'famma_capi_health_signals', array() );
		$codes = static fn( array $s ): array => array_column( $s['raisons'], 'code' );
		self::check( 'V6', 'battement-36h-rouge', in_array( 'heartbeat', $codes( $s1 ), true ) && 'rouge' === $s1['statut'], 'battement de cœur de 37 h : rouge « heartbeat »', $codes( $s1 ) );
		self::check(
			'V6',
			'un-seul-email-en-24h',
			1 === $mails1 - $mails0 && $mails2 === $mails1 && in_array( 'registry', $codes( $s2 ), true ),
			'2 pannes rouges en 24 h : 1 seul e-mail',
			array(
				'apres_1re'  => $mails1 - $mails0,
				'apres_2e'   => $mails2 - $mails0,
				'raisons_2e' => $codes( $s2 ),
			)
		);
		self::check( 'V6', 'panne-retenue-annoncee-apres-24h', $mails3 === $mails2 + 1, 'la panne retenue par la limite est annoncée après 24 h', $mails3 - $mails0 );
		self::check( 'V6', 'evaluation-sur-admin-init', false !== has_action( 'admin_init', array( \Famma\Core\Capi_Health::instance(), 'maybe_evaluate' ) ), 'Capi_Health::maybe_evaluate() accroché à admin_init (sans cron)', has_action( 'admin_init', array( \Famma\Core\Capi_Health::instance(), 'maybe_evaluate' ) ) );

		// Commande « Livrée » sans Purchase depuis plus de 24 h.
		$gaps0 = \Famma\Core\Capi_Purchase_Gaps::count( $now );
		$order = self::order( 'V6-livree-sans-purchase' );
		$id    = $order->get_id();
		foreach ( array( 'confirmed', 'shipped' ) as $status ) {
			self::fresh( $id )->update_status( $status, self::LABEL . ' V6.' );
		}
		self::$cfg['pixel'] = '';
		self::fresh( $id )->update_status( 'delivered', self::LABEL . ' V6 : livraison sans envoi (pixel vide).' );
		self::$cfg['pixel'] = self::FAKE_PIXEL;
		$late               = self::fresh( $id );
		$late->set_date_paid( $now - 25 * HOUR_IN_SECONDS );
		$late->save();
		$gaps1 = \Famma\Core\Capi_Purchase_Gaps::count( $now );
		$s4    = \Famma\Core\Capi_Health::evaluate( $now );
		self::check(
			'V6',
			'livree-sans-purchase-24h-rouge',
			$gaps1['missing'] === $gaps0['missing'] + 1 && in_array( 'purchase_missing', $codes( $s4 ), true ) && 'rouge' === $s4['statut'],
			'Purchase manquant renvoyable de plus de 24 h : +1 et rouge « purchase_missing »',
			array(
				'avant'   => $gaps0,
				'apres'   => $gaps1,
				'raisons' => $codes( $s4 ),
			)
		);
		if ( function_exists( '\Famma\Watchdog\findings' ) ) {
			$findings = array_keys( \Famma\Watchdog\findings( $now ) );
			$wd_mails = count( Famma_Qa_L5_Sandbox::$mails );
			$admin    = self::admin();
			wp_set_current_user( $admin );
			\Famma\Watchdog\check();
			wp_set_current_user( 0 );
			self::check(
				'V6',
				'chien-de-garde-lit-le-rouge-sans-email',
				in_array( 'rouge', $findings, true ) && count( Famma_Qa_L5_Sandbox::$mails ) === $wd_mails,
				'le mu-plugin chien de garde voit le rouge (avis) et n\'envoie pas d\'e-mail en plus',
				array(
					'constats'     => $findings,
					'emails_chien' => count( Famma_Qa_L5_Sandbox::$mails ) - $wd_mails,
				)
			);
		} else {
			self::$report['V6']['observations']['chien_de_garde'] = 'non chargé (fichier /qa-watchdog absent) : non joué';
		}
	}

	// ------------------------------------------------------------------
	// V7 — rapprochement et « Renvoyer » borné
	// ------------------------------------------------------------------

	/** Reconciliation and bounded resend. */
	private static function v7(): void {
		global $wpdb;
		self::$scenario = 'V7';
		$registry       = \Famma\Core\Capi_Delivery_Registry::instance();
		$deliver        = static function ( string $label, string $mode ): int {
			$o  = self::order( $label );
			$id = $o->get_id();
			foreach ( array( 'confirmed', 'shipped' ) as $status ) {
				self::fresh( $id )->update_status( $status, self::LABEL . ' V7.' );
			}
			self::$meta = $mode;
			self::fresh( $id )->update_status( 'delivered', self::LABEL . ' V7 : livraison (' . $mode . ').' );
			self::$meta = 'ok';
			return $id;
		};
		$age            = static function ( int $id, int $seconds, bool $event_time ) use ( $wpdb, $registry ): void {
			$data = array( 'updated_at' => gmdate( 'Y-m-d H:i:s', time() - $seconds ) );
			if ( $event_time ) {
				$data['event_time'] = time() - $seconds;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- age the registry row of a QA order of this run.
			$wpdb->update( $registry->table(), $data, array( 'event_key' => hash( 'sha256', self::FAKE_PIXEL . '|Purchase|famma_purchase_' . $id ) ) );
		};
		$uncertain      = $deliver( 'V7-incertain-25h', 'http500' );
		as_unschedule_all_actions( \Famma\Core\Capi_Retry::HOOK, \Famma\Core\Capi_Retry::args( $uncertain, 'Purchase' ), \Famma\Core\Capi_Retry::GROUP );
		$age( $uncertain, 25 * HOUR_IN_SECONDS, false );
		$rejected = $deliver( 'V7-rejete-5j', 'code100' );
		$age( $rejected, 5 * self::DAY, true );
		$accepted = $deliver( 'V7-accepte', 'ok' );
		$admin_o  = self::order( 'V7-admin', 'admin', false );
		$test_o   = self::order( 'V7-essai' );
		$test_o->update_meta_data( '_famma_test_order', 1 );
		$test_o->save();

		$rows = array();
		foreach ( array(
			'incertain_25h' => $uncertain,
			'rejete_5j'     => $rejected,
			'accepte'       => $accepted,
		) as $name => $id ) {
			$before        = count( self::graph( 'Purchase', $id ) );
			$location      = self::resend( $id, 'Purchase' );
			$sent          = array_slice( self::graph( 'Purchase', $id ), $before );
			$rows[ $name ] = array(
				'commande'   => $id,
				'resultat'   => $location,
				'envois'     => count( $sent ),
				'event_id'   => $sent[0]['body']['data'][0]['event_id'] ?? null,
				'event_time' => $sent[0]['body']['data'][0]['event_time'] ?? null,
				'registre'   => self::registry( 'Purchase', 'famma_purchase_' . $id ),
			);
		}
		self::check( 'V7', 'incertain-25h-refuse', 0 === $rows['incertain_25h']['envois'] && str_contains( (string) $rows['incertain_25h']['resultat'], 'famma_capi_resend=refused' ), 'renvoi refusé (incertain de plus de 24 h)', $rows['incertain_25h'] );
		self::check( 'V7', 'rejete-5j-renvoye-meme-event-id', 1 === $rows['rejete_5j']['envois'] && 'famma_purchase_' . $rejected === $rows['rejete_5j']['event_id'] && abs( ( time() - 5 * self::DAY ) - (int) $rows['rejete_5j']['event_time'] ) < 120 && str_contains( (string) $rows['rejete_5j']['resultat'], 'famma_capi_resend=accepted' ), 'renvoi accepté, même event_id et event_time d\'origine', $rows['rejete_5j'] );
		self::check( 'V7', 'accepte-jamais-renvoye', 0 === $rows['accepte']['envois'] && str_contains( (string) $rows['accepte']['resultat'], 'famma_capi_resend=refused' ), 'aucun renvoi d\'un envoi accepté', $rows['accepte'] );

		$admin = self::admin();
		wp_set_current_user( $admin );
		$build = \Famma\Core\Capi_Reconcile::instance()->build( time() );
		ob_start();
		\Famma\Core\Capi_Reconcile_View::render();
		$html = wp_strip_all_tags( (string) ob_get_clean() );
		wp_set_current_user( 0 );
		$mine = array();
		foreach ( $build['rows'] as $row ) {
			if ( in_array( $row['order']->get_id(), array( $admin_o->get_id(), $test_o->get_id() ), true ) ) {
				$mine[ $row['order']->get_id() ] = array(
					'test'        => $row['test'],
					'fbc'         => $row['fbc'],
					'orderplaced' => $row['orderplaced']['reason'],
				);
			}
		}
		self::check(
			'V7',
			'ligne-ineligibles',
			( $build['totals']['ineligible']['admin'] ?? 0 ) >= 1 && 'admin' === ( $mine[ $admin_o->get_id() ]['orderplaced'] ?? '' ) && 1 === preg_match( '/Inéligibles : \d+ \(/u', $html ),
			'ligne « Inéligibles : N (motif) », commande admin comptée',
			array(
				'totaux' => $build['totals'],
				'lignes' => $mine,
			)
		);
		self::check( 'V7', 'essai-exclue-signalee', true === ( $mine[ $test_o->get_id() ]['test'] ?? null ) && $build['totals']['test_orders'] >= 1 && str_contains( $html, 'Commandes de test exclues' ), 'commande d\'essai exclue des totaux et signalée', $build['totals']['test_orders'] );
		self::check( 'V7', 'colonne-fbc', str_contains( $html, 'fbc présent' ), 'colonne « fbc présent »', null );
		// Le plan exige V7 HPOS activé puis désactivé : on relève le stockage réellement actif.
		$hpos    = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$storage = array();
		foreach ( array( $uncertain, $rejected, $accepted, $admin_o->get_id(), $test_o->get_id() ) as $id ) {
			$storage[ $id ] = array(
				'type_commande' => \Automattic\WooCommerce\Utilities\OrderUtil::get_order_type( $id ),
				'post_type'     => (string) get_post_type( $id ),
			);
		}
		self::$report['V7']['observations'] = array(
			'hpos'     => $hpos ? 'HPOS activé (tables wc_orders)' : 'HPOS désactivé (stockage classique wp_posts)',
			'stockage' => $storage,
		);
	}

	// ------------------------------------------------------------------
	// V9 — fbc refusé localement
	// ------------------------------------------------------------------

	/** Invalid _fbc increments the counter, value never logged. */
	private static function v9(): void {
		self::$scenario = 'V9';
		$value          = 'fb.1.QA-L5I-pas-un-horodatage.valeur invalide!';
		Famma_Qa_L5_Sandbox::set( 'famma_capi_fbc_rejected', array() );
		$_COOKIE['_fbc'] = $value;
		$clean           = \Famma\Core\Capi_Signals::cookie( '_fbc' );
		unset( $_COOKIE['_fbc'] );
		$stored = Famma_Qa_L5_Sandbox::get( 'famma_capi_fbc_rejected' );
		$health = \Famma\Core\Capi_Health::evaluate();
		$leak   = count( array_filter( self::$logs, static fn( string $l ): bool => str_contains( $l, 'valeur invalide' ) ) );
		self::check(
			'V9',
			'compteur-incremente',
			'' === $clean && 1 === (int) ( $stored[ gmdate( 'Y-m-d' ) ] ?? 0 ) && in_array( 'fbc_rejected', array_column( $health['raisons'], 'code' ), true ),
			'fbc refusé, compteur du jour = 1, raison orange « fbc_rejected »',
			array(
				'compteur' => $stored,
				'raisons'  => array_column( $health['raisons'], 'code' ),
			)
		);
		self::check( 'V9', 'valeur-jamais-journalisee', 0 === $leak && ! str_contains( (string) wp_json_encode( $stored ), 'invalide' ), 'ni le journal ni l\'option ne contiennent la valeur', $leak );
	}

	// ------------------------------------------------------------------
	// Outils
	// ------------------------------------------------------------------

	/**
	 * Intercept graph.facebook.com; block every other host.
	 *
	 * @param false|array|WP_Error $preempt Earlier answer.
	 * @param array                $args    Request arguments.
	 * @param string               $url     Destination.
	 * @return array|WP_Error
	 */
	public static function http( $preempt, $args, $url ) {
		if ( false !== $preempt ) {
			return $preempt;
		}
		if ( 'graph.facebook.com' !== (string) wp_parse_url( (string) $url, PHP_URL_HOST ) ) {
			return new WP_Error( 'famma_qa_l5_blocked', 'QA-L5I: outgoing HTTP blocked.' );
		}
		$body  = json_decode( (string) ( $args['body'] ?? '' ), true );
		$body  = is_array( $body ) ? $body : array();
		$token = (string) ( $body['access_token'] ?? '' );
		unset( $body['access_token'] );
		$auth     = (string) ( $args['headers']['Authorization'] ?? '' );
		$path     = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		$order_id = (int) ( $body['data'][0]['custom_data']['order_id'] ?? 0 );
		$cod      = null;
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				$order->read_meta_data( true );
				$cod = $order->get_meta( '_famma_cod_paid' );
			}
		}
		self::$requests[] = array(
			'scenario'         => self::$scenario,
			'simulation'       => self::$meta,
			'method'           => (string) ( $args['method'] ?? '' ),
			'url'              => str_replace( self::FAKE_TOKEN, '[jeton]', (string) $url ),
			'path'             => $path,
			'query'            => (string) wp_parse_url( (string) $url, PHP_URL_QUERY ),
			'token_present'    => '' !== $token || str_contains( $auth, self::FAKE_TOKEN ),
			'token_in_url'     => str_contains( (string) $url, self::FAKE_TOKEN ),
			'token_in_header'  => 'Bearer ' . self::FAKE_TOKEN === $auth,
			'cod_paid_at_send' => $cod,
			'body'             => $body,
		);
		preg_match( '#^/(v\d+\.\d+)/#', $path, $m );
		$headers = array();
		if ( '' !== self::$served ) {
			$headers['facebook-api-version'] = '=' === self::$served ? ( $m[1] ?? '' ) : self::$served;
		}
		$probe = 'GET' === ( $args['method'] ?? '' );
		switch ( self::$meta ) {
			case 'wp_error':
				return new WP_Error( 'http_request_failed', 'QA-L5I simulation: connection refused.' );
			case 'timeout':
				return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 8000 milliseconds (QA-L5I simulation)' );
			case 'http500':
				return self::reply( 500, '{"error":{"message":"QA-L5I simulation: server error","type":"OAuthException","code":2,"is_transient":true,"fbtrace_id":"QA-L5I"}}', $headers );
			case 'code190':
				return self::reply( 400, '{"error":{"message":"QA-L5I simulation: invalid token","type":"OAuthException","code":190,"fbtrace_id":"QA-L5I"}}', $headers );
			case 'code100':
				return self::reply( 400, '{"error":{"message":"QA-L5I simulation: invalid parameter","type":"OAuthException","code":100,"fbtrace_id":"QA-L5I"}}', $headers );
			case 'missing_permission':
				return self::reply( 400, '{"error":{"message":"(#100) Missing Permission","type":"OAuthException","code":100,"fbtrace_id":"QA-L5I"}}', $headers );
			case 'messages':
				return self::reply( 200, '{"events_received":1,"messages":["QA-L5I simulated warning"],"fbtrace_id":"QA-L5I"}', $headers );
			default:
				return $probe ? self::reply( 200, '{"id":"' . self::FAKE_SYSTEM_USER . '"}', $headers ) : self::reply( 200, '{"events_received":1,"messages":[],"fbtrace_id":"QA-L5I"}', $headers );
		}
	}

	/**
	 * WP HTTP API response array.
	 *
	 * @param int                  $code    HTTP status.
	 * @param string               $body    JSON body.
	 * @param array<string,string> $headers Headers.
	 * @return array
	 */
	private static function reply( int $code, string $body, array $headers ): array {
		return array(
			'headers'  => $headers,
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Count http_api_debug calls that receive the token (declared V2 gap).
	 *
	 * @param mixed  $response Response.
	 * @param string $context  Context.
	 * @param string $klass    Transport class.
	 * @param array  $args     Request arguments.
	 * @param string $url      URL.
	 */
	public static function debug( $response, $context, $klass, $args, $url ): void {
		unset( $response, $context, $klass );
		if ( 'graph.facebook.com' === (string) wp_parse_url( (string) $url, PHP_URL_HOST ) && ( str_contains( (string) ( $args['body'] ?? '' ), self::FAKE_TOKEN ) || str_contains( (string) ( $args['headers']['Authorization'] ?? '' ), self::FAKE_TOKEN ) ) ) {
			++self::$debug_token;
		}
	}

	/**
	 * Keep famma-capi lines of the first log handler.
	 *
	 * @param mixed  $message Message.
	 * @param string $level   Level.
	 * @param array  $context Context.
	 * @param object $handler Handler.
	 * @return mixed
	 */
	public static function capture_log( $message, $level, $context, $handler ) {
		unset( $level );
		$name = is_object( $handler ) ? get_class( $handler ) : '';
		if ( '' === self::$handler ) {
			self::$handler = $name;
		}
		if ( $name === self::$handler && 'famma-capi' === ( $context['source'] ?? '' ) ) {
			self::$logs[] = (string) $message;
		}
		return $message;
	}

	/**
	 * Synthetic orders only: raise the anti-abuse ceilings.
	 *
	 * @param array $limits Limits.
	 * @return array
	 */
	public static function limits( $limits ): array {
		$limits                         = (array) $limits;
		$limits['orders_per_phone_day'] = 100;
		$limits['orders_per_ip_hour']   = 100;
		return $limits;
	}

	/**
	 * Run an admin-post handler; its redirection is captured instead of exit.
	 *
	 * @param callable $handler Handler.
	 * @return string Redirect location.
	 */
	private static function admin_post( callable $handler ): string {
		$capture = static function ( $location ) {
			throw new Famma_Qa_L5_Redirect( (string) $location );
		};
		add_filter( 'wp_redirect', $capture, PHP_INT_MAX );
		$location = '';
		try {
			call_user_func( $handler );
		} catch ( Famma_Qa_L5_Redirect $redirect ) {
			$location = $redirect->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $capture, PHP_INT_MAX );
		}
		return $location;
	}

	/**
	 * « Renvoyer » of the reconciliation screen, as the administrator.
	 *
	 * @param int    $id    Order.
	 * @param string $event Event.
	 * @return string Redirect location.
	 */
	private static function resend( int $id, string $event ): string {
		wp_set_current_user( self::admin() );
		$_POST    = array(
			'_wpnonce' => wp_create_nonce( 'famma_capi_resend' ),
			'order_id' => (string) $id,
			'event'    => $event,
		);
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- synthetic POST; the handler verifies its nonce.
		$location = self::admin_post( array( \Famma\Core\Capi_Reconcile::instance(), 'handle_resend' ) );
		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );
		return $location;
	}

	/**
	 * First administrator.
	 *
	 * @return int
	 */
	private static function admin(): int {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		return (int) ( $admins[0] ?? 0 );
	}

	/**
	 * QA product, hidden, no stock management.
	 *
	 * @return WC_Product
	 * @throws RuntimeException When the product cannot be created.
	 */
	private static function product(): WC_Product {
		if ( self::$product > 0 ) {
			return wc_get_product( self::$product );
		}
		$product = new WC_Product_Simple();
		$product->set_name( self::LABEL . ' Produit acceptation ' . substr( self::$run, 0, 8 ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_regular_price( '49' );
		$product->set_sku( self::LABEL . '-' . substr( self::$run, 0, 8 ) );
		$product->set_manage_stock( false );
		$product->set_tax_status( 'none' );
		$product->set_virtual( true );
		$product->update_meta_data( '_famma_qa_l2_run', self::$run );
		$product->save();
		if ( ! $product->get_id() ) {
			throw new RuntimeException( 'QA-L5I product creation failed.' );
		}
		self::$product = $product->get_id();
		return $product;
	}

	/**
	 * Real WooCommerce order with synthetic QA-L5I data.
	 *
	 * @param string $label   Label (order meta).
	 * @param string $via     created_via.
	 * @param bool   $signals Store the checkout signals.
	 * @param int    $age     Creation date offset in seconds.
	 * @return WC_Order
	 * @throws RuntimeException When the order cannot be created.
	 */
	private static function order( string $label, string $via = 'checkout', bool $signals = true, int $age = 0 ): WC_Order {
		$order = wc_create_order(
			array(
				'created_via' => $via,
				'status'      => 'pending-confirm',
			)
		);
		if ( ! $order instanceof WC_Order ) {
			throw new RuntimeException( 'QA-L5I order creation failed.' );
		}
		$order->set_payment_method( 'cod' );
		$order->set_currency( 'TND' );
		$order->set_billing_first_name( self::LABEL );
		$order->set_billing_last_name( 'Acceptation' );
		$order->set_billing_email( 'qa-l5i@example.invalid' );
		$order->set_billing_phone( '+216 20 000 000' );
		$order->set_billing_address_1( self::LABEL . ' adresse fictive' );
		$order->set_billing_city( 'Sousse' );
		$order->set_billing_state( 'TN-51' );
		$order->set_billing_country( 'TN' );
		if ( $age > 0 ) {
			$order->set_date_created( time() - $age );
		}
		$order->add_product( self::product(), 1 );
		if ( $signals ) {
			$order->update_meta_data( '_famma_fbp', 'fb.1.1792400000000.1234567890' );
			$order->update_meta_data( '_famma_fbc', 'fb.1.1792400000000.QA-L5I-fbclid' );
			$order->update_meta_data( '_famma_client_ip', '192.0.2.10' );
			$order->update_meta_data( '_famma_client_ua', 'Mozilla/5.0 (QA-L5I synthetic user agent)' );
		}
		$order->update_meta_data( '_famma_qa_l2_run', self::$run );
		$order->update_meta_data( '_famma_qa_l2_label', $label );
		$order->calculate_totals( false );
		$order->save();
		self::$orders[] = $order->get_id();
		return $order;
	}

	/**
	 * Order reloaded with fresh metadata.
	 *
	 * @param int $id Order.
	 * @return WC_Order
	 */
	private static function fresh( int $id ): WC_Order {
		$order = wc_get_order( $id );
		$order->read_meta_data( true );
		return $order;
	}

	/**
	 * Report an accepted checkout, as the COD gateway does.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function queue( WC_Order $order ): void {
		apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce gateway filter replayed.
			'woocommerce_payment_successful_result',
			array(
				'result'   => 'success',
				'redirect' => wc_get_checkout_url(),
			),
			$order->get_id()
		);
	}

	/**
	 * Run pending actions of one hook and arguments through the real runner.
	 *
	 * @param string $hook Hook.
	 * @param array  $args Arguments.
	 * @param string $group Group.
	 * @return array<int,string>
	 */
	private static function run_actions( string $hook, array $args, string $group ): array {
		$ids = as_get_scheduled_actions(
			array(
				'hook'     => $hook,
				'group'    => $group,
				'args'     => $args,
				'status'   => 'pending',
				'per_page' => 10,
			),
			'ids'
		);
		$out = array();
		foreach ( $ids as $action_id ) {
			ActionScheduler::runner()->process_action( (int) $action_id, 'QA-L5I' );
			$out[ (int) $action_id ] = ActionScheduler::store()->get_status( (int) $action_id );
		}
		return $out;
	}

	/**
	 * Run the OrderPlaced queue of one order.
	 *
	 * @param int $id Order.
	 * @return array<int,string>
	 */
	private static function run_queued( int $id ): array {
		return self::run_actions( 'famma_capi_orderplaced_send', array( 'order_id' => $id ), 'famma-capi' );
	}

	/**
	 * Run pending famma_capi_retry actions of one event (before their time).
	 *
	 * @param int    $id    Order.
	 * @param string $event Event.
	 * @return array<int,string>
	 */
	private static function run_retry( int $id, string $event ): array {
		return self::run_actions( \Famma\Core\Capi_Retry::HOOK, \Famma\Core\Capi_Retry::args( $id, $event ), \Famma\Core\Capi_Retry::GROUP );
	}

	/**
	 * Pending famma_capi_retry actions of one event, with their delay.
	 *
	 * @param int    $id    Order.
	 * @param string $event Event.
	 * @return array<int,array<string,int|null>>
	 */
	private static function retries( int $id, string $event ): array {
		$out = array();
		$ids = as_get_scheduled_actions(
			array(
				'hook'     => \Famma\Core\Capi_Retry::HOOK,
				'group'    => \Famma\Core\Capi_Retry::GROUP,
				'args'     => \Famma\Core\Capi_Retry::args( $id, $event ),
				'status'   => 'pending',
				'per_page' => 10,
			),
			'ids'
		);
		foreach ( $ids as $action_id ) {
			$date  = ActionScheduler::store()->fetch_action( (int) $action_id )->get_schedule()->get_date();
			$out[] = array(
				'action' => (int) $action_id,
				'dans_s' => $date ? $date->getTimestamp() - time() : null,
			);
		}
		return $out;
	}

	/**
	 * Intercepted requests of one event for one order.
	 *
	 * @param string $event    Event name.
	 * @param int    $order_id Order.
	 * @return array<int,array>
	 */
	private static function graph( string $event, int $order_id ): array {
		// Par event_id (famma_<événement>_<n°>) : le profil minimal (R3) retire order_id de custom_data.
		$event_id = 'famma_' . ( 'Purchase' === $event ? 'purchase' : 'orderplaced' ) . '_' . $order_id;
		return array_values( array_filter( self::$requests, static fn( array $r ): bool => ( $r['body']['data'][0]['event_name'] ?? '' ) === $event && ( $r['body']['data'][0]['event_id'] ?? '' ) === $event_id ) );
	}

	/**
	 * Journal lines of one order since an index.
	 *
	 * @param int $order_id Order.
	 * @param int $from     First index.
	 * @return array<int,string>
	 */
	private static function lines( int $order_id, int $from ): array {
		return array_values( array_filter( array_slice( self::$logs, $from ), static fn( string $l ): bool => 1 === preg_match( '/^(OrderPlaced|Purchase) order=' . $order_id . ' /', $l ) ) );
	}

	/**
	 * Registry row of one event.
	 *
	 * @param string $event    Event name.
	 * @param string $event_id Event identifier.
	 * @return array<string,mixed>
	 */
	private static function registry( string $event, string $event_id ): array {
		$row = \Famma\Core\Capi_Delivery_Registry::instance()->read( hash( 'sha256', self::FAKE_PIXEL . '|' . $event . '|' . $event_id ) );
		if ( array() === $row ) {
			return array( 'state' => '(aucune ligne)' );
		}
		return array(
			'state'         => $row['state'],
			'attempt_count' => (int) $row['attempt_count'],
			'event_time'    => (int) $row['event_time'],
			'updated_at'    => $row['updated_at'] ?? null,
		);
	}

	/**
	 * Record one check.
	 *
	 * @param string $scenario Scenario.
	 * @param string $name     Check.
	 * @param bool   $passed   Expected behaviour observed.
	 * @param mixed  $expected Expected.
	 * @param mixed  $observed Observed.
	 */
	private static function check( string $scenario, string $name, bool $passed, $expected, $observed ): void {
		self::$report[ $scenario ]['checks'][] = array(
			'name'    => $name,
			'passed'  => $passed,
			'attendu' => $expected,
			'observe' => $observed,
		);
	}

	/**
	 * Count checks, all or by outcome.
	 *
	 * @param bool|null $passed Outcome filter.
	 * @return int
	 */
	private static function count_checks( ?bool $passed ): int {
		$count = 0;
		foreach ( self::$report as $scenario ) {
			foreach ( (array) ( $scenario['checks'] ?? array() ) as $check ) {
				$count += ( null === $passed || $passed === $check['passed'] ) ? 1 : 0;
			}
		}
		return $count;
	}

	/**
	 * Delete everything this run created.
	 *
	 * @return array<string,int>
	 */
	private static function cleanup(): array {
		global $wpdb;
		$done = array(
			'commandes' => 0,
			'produits'  => 0,
		);
		foreach ( array_unique( self::$orders ) as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order instanceof WC_Order || self::$run !== $order->get_meta( '_famma_qa_l2_run' ) ) {
				continue;
			}
			as_unschedule_all_actions( 'famma_capi_orderplaced_send', array( 'order_id' => $id ), 'famma-capi' );
			foreach ( array( 'OrderPlaced', 'Purchase' ) as $event ) {
				as_unschedule_all_actions( \Famma\Core\Capi_Retry::HOOK, \Famma\Core\Capi_Retry::args( $id, $event ), \Famma\Core\Capi_Retry::GROUP );
			}
			as_unschedule_all_actions( \Famma\Core\Order_Emails::ACTION, array( $id ), \Famma\Core\Order_Emails::GROUP );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- delete only this run's registry rows; orders use CRUD.
			$wpdb->delete( \Famma\Core\Capi_Delivery_Registry::instance()->table(), array( 'order_id' => $id ), array( '%d' ) );
			$order->delete( true );
			++$done['commandes'];
		}
		if ( self::$product > 0 ) {
			$product = wc_get_product( self::$product );
			if ( $product && self::$run === $product->get_meta( '_famma_qa_l2_run' ) ) {
				$product->delete( true );
				++$done['produits'];
			}
		}
		return $done;
	}
}

Famma_Qa_L5_Acceptance::run();
