<?php
/**
 * Clone QA pixel, lot 2 : scénarios T2.2 à T2.13 du plan
 * docs/PLAN-TESTS-PIXEL-2026-10-19.md, joués sur famma-core 0.3.1 tel qu'il
 * tourne en production (aucune modification de famma-core).
 *
 * Run with FAMMA_QA_PIXEL_TEST=1, FAMMA_TEST_DB_NAME=famma_integration,
 * FAMMA_TEST_DB_HOST=db, then wp eval-file /tests/qa/test-qa-pixel-integration.php :
 *   docker compose -p famma-qa-pixel -f scripts/compose-integration.yml \
 *     -f scripts/qa/compose-qa-pixel.yml run --rm -T -e FAMMA_QA_PIXEL_TEST=1 \
 *     -e FAMMA_TEST_DB_NAME=famma_integration -e FAMMA_TEST_DB_HOST=db \
 *     -e FAMMA_QA_PIXEL_MODE=main cli eval-file /tests/qa/test-qa-pixel-integration.php
 *
 * Modes (FAMMA_QA_PIXEL_MODE) :
 *   main             T2.2 à T2.5, T2.6 (file unique), T2.7, T2.8, T2.9, T2.11,
 *                    T2.12 (partie serveur) et T2.13, par le CRUD WooCommerce réel.
 *   express          T2.6 (double soumission du checkout classique et double
 *                    clic express, séquentiels) et T2.10 (UTM et origine
 *                    WooCommerce), par les vrais points d'entrée wc-ajax.
 *   checkout-setup   crée le produit QA-L2 de la double soumission parallèle
 *                    (scripts/qa/qa-l2-checkout-double.sh, curl réel).
 *   checkout-verify  exécute ici les tâches OrderPlaced de ces commandes et
 *                    compte les envois.
 *   checkout-cleanup supprime les données de ce passage.
 *   run-list         liste les commandes d'un passage gardé (FAMMA_QA_PIXEL_KEEP=1).
 *   run-cleanup      supprime les commandes et produits d'un passage gardé.
 * FAMMA_QA_PIXEL_RUN : identifiant (UUID) du passage, obligatoire pour checkout-*
 *   et run-* ; facultatif pour main et express (sinon tiré au hasard).
 * FAMMA_QA_PIXEL_KEEP=1 : garde les données QA-L2 pour inspection
 *   (scripts/qa/qa-l2-token-scan.sh), puis run-cleanup.
 *
 * Garde-fous :
 *   - seulement la base famma_integration sur l'hôte db, servie par
 *     http://localhost:8980, avec le garde HTTP du clone actif ;
 *   - refus si le jeton stocké n'est ni vide ni le jeton factice du clone ;
 *   - réglages Meta propres au processus (pre_option_*), jamais écrits en
 *     base : le clone est partagé avec le lot 3 ;
 *   - réponses de Meta simulées par pre_http_request (priorité normale) ;
 *     tout autre hôte est bloqué ; le jeton n'est jamais imprimé, seuls sa
 *     présence et sa conformité au jeton factice sont relevées.
 *
 * Ce script CONSTATE : un écart ne l'interrompt pas, il est rendu dans le
 * JSON (« passed » à false) avec l'observation qui le prouve.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- WP-CLI check entry point, not an autoloaded plugin class file.

defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_QA_PIXEL_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This suite requires WP-CLI and FAMMA_QA_PIXEL_TEST=1.' );
}
$famma_expected_db      = (string) getenv( 'FAMMA_TEST_DB_NAME' );
$famma_expected_db_host = (string) getenv( 'FAMMA_TEST_DB_HOST' );
if ( 'local' !== wp_get_environment_type() || 'famma_integration' !== $famma_expected_db || 'db' !== $famma_expected_db_host || ! defined( 'DB_NAME' ) || DB_NAME !== $famma_expected_db || ! defined( 'DB_HOST' ) || DB_HOST !== $famma_expected_db_host ) {
	throw new RuntimeException( 'Refusing: expected disposable local database identity is not explicitly confirmed.' );
}
if ( 'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) || 8980 !== (int) wp_parse_url( home_url(), PHP_URL_PORT ) ) {
	throw new RuntimeException( 'Refusing: only the QA pixel clone on http://localhost:8980 is allowed.' );
}
foreach ( array( 'FAMMA_META_PIXEL_ID', 'FAMMA_META_ACCESS_TOKEN', 'FAMMA_META_TEST_EVENT_CODE' ) as $famma_constant ) {
	if ( defined( $famma_constant ) && '' !== (string) constant( $famma_constant ) ) {
		throw new RuntimeException( 'Refusing: tracking constants must stay empty in the clone.' );
	}
}
if ( ! in_array( (string) get_option( 'famma_meta_access_token', '' ), array( '', 'QA-FAKE-TOKEN-NOT-REAL' ), true ) ) {
	throw new RuntimeException( 'Refusing: the stored Meta token is neither empty nor the QA fake token.' );
}
if ( ! class_exists( 'Famma_Qa_Pixel_Guard' ) || ! Famma_Qa_Pixel_Guard::active() || ! class_exists( '\Famma\Core\Capi' ) || ! class_exists( '\Famma\Core\Capi_Delivery_Registry' ) ) {
	throw new RuntimeException( 'Refusing: qa-pixel-guard.php and famma-core must both be active.' );
}

/** Replaces the native AJAX termination so the JSON reply can be read. */
final class Famma_Qa_L2_Stop extends Error {}

/** Lot 2 integration observations on the QA pixel clone. */
final class Famma_Qa_Pixel_L2 {

	/** Jeton factice du clone, jamais un vrai secret. */
	private const FAKE_TOKEN = 'QA-FAKE-TOKEN-NOT-REAL';

	/** Pixel factice du clone. */
	private const FAKE_PIXEL = '000000000000001';

	/** Un jour, en secondes. */
	private const DAY = 86400;

	/** Téléphone synthétique (format tunisien, aucun client réel). */
	private const PHONE = '+216 20 000 000';

	/**
	 * Identifiant de ce passage.
	 *
	 * @var string
	 */
	private static string $run = '';

	/**
	 * Mode demandé.
	 *
	 * @var string
	 */
	private static string $mode = '';

	/**
	 * Scénario en cours (pour étiqueter les requêtes interceptées).
	 *
	 * @var string
	 */
	private static string $scenario = '';

	/**
	 * Réglages Meta de ce processus (jamais écrits en base).
	 *
	 * @var array<string,string>
	 */
	private static array $cfg = array();

	/**
	 * Réponse simulée de Meta : ok, wp_error, timeout, http500, no_events, too_old.
	 *
	 * @var string
	 */
	private static string $meta_mode = 'ok';

	/**
	 * Vrai pendant la simulation « table du registre absente ».
	 *
	 * @var bool
	 */
	private static bool $missing_table = false;

	/**
	 * Requêtes vers graph.facebook.com interceptées (sans le jeton).
	 *
	 * @var array<int,array>
	 */
	private static array $requests = array();

	/**
	 * Observation de http_request_args pour graph.facebook.com.
	 *
	 * @var array<int,array>
	 */
	private static array $args_seen = array();

	/**
	 * Lignes du journal famma-capi (premier gestionnaire seulement).
	 *
	 * @var array<int,string>
	 */
	private static array $logs = array();

	/**
	 * Gestionnaire de journal retenu.
	 *
	 * @var string
	 */
	private static string $log_handler = '';

	/**
	 * Commandes QA-L2 créées.
	 *
	 * @var array<int,int>
	 */
	private static array $orders = array();

	/**
	 * Produits QA-L2 créés.
	 *
	 * @var array<string,int>
	 */
	private static array $products = array();

	/**
	 * Rapport par scénario.
	 *
	 * @var array<string,array>
	 */
	private static array $report = array();

	/**
	 * Contexte du formulaire express (cookie de session, requêtes).
	 *
	 * @var array<string,mixed>
	 */
	private static array $express = array();

	/**
	 * Run the requested mode and print one JSON document.
	 *
	 * @throws RuntimeException When the mode or run marker is invalid.
	 */
	public static function run(): void {
		$mode       = (string) getenv( 'FAMMA_QA_PIXEL_MODE' );
		self::$mode = '' === $mode ? 'main' : $mode;
		$run        = (string) getenv( 'FAMMA_QA_PIXEL_RUN' );
		if ( '' === $run ) {
			if ( str_starts_with( self::$mode, 'checkout-' ) || str_starts_with( self::$mode, 'run-' ) ) {
				throw new RuntimeException( 'FAMMA_QA_PIXEL_RUN (UUID) is required for checkout-* and run-* modes.' );
			}
			$run = wp_generate_uuid4();
		}
		if ( 1 !== preg_match( '/^[a-f0-9-]{36}$/', $run ) ) {
			throw new RuntimeException( 'FAMMA_QA_PIXEL_RUN must be a lowercase UUID.' );
		}
		self::$run = $run;
		self::$cfg = array(
			'pixel'       => self::FAKE_PIXEL,
			'token'       => self::FAKE_TOKEN,
			'orderplaced' => '1',
			'test_mode'   => '0',
			'test_code'   => '',
			'tiktok'      => '',
			'ga4'         => '',
		);
		self::hooks();
		$keep = '1' === getenv( 'FAMMA_QA_PIXEL_KEEP' );
		try {
			switch ( self::$mode ) {
				case 'main':
					self::main();
					break;
				case 'express':
					self::express_mode();
					break;
				case 'checkout-setup':
					self::checkout_setup();
					$keep = true;
					break;
				case 'checkout-verify':
					self::checkout_verify();
					$keep = true;
					break;
				case 'checkout-cleanup':
					self::checkout_load();
					$keep = false;
					break;
				case 'run-list':
					self::run_load();
					self::$report['run-list'] = array( 'orders' => array_values( array_unique( self::$orders ) ) );
					$keep                     = true;
					break;
				case 'run-cleanup':
					self::run_load();
					$keep = false;
					break;
				default:
					throw new RuntimeException( 'Unknown FAMMA_QA_PIXEL_MODE.' );
			}
		} finally {
			$cleanup = $keep ? array( 'kept' => true ) : self::cleanup();
		}
		WP_CLI::line(
			wp_json_encode(
				array(
					'suite'        => 'qa-pixel-lot2-integration',
					'mode'         => self::$mode,
					'run'          => self::$run,
					'environment'  => self::environment(),
					'scenarios'    => self::$report,
					'requests'     => self::$requests,
					'famma_capi'   => self::$logs,
					'cleanup'      => $cleanup,
					'checks_total' => self::count_checks( null ),
					'checks_false' => self::count_checks( false ),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			)
		);
	}

	/** Register process-scoped interception and configuration. */
	private static function hooks(): void {
		add_filter( 'pre_http_request', array( self::class, 'http' ), 10, 3 );
		add_filter( 'http_request_args', array( self::class, 'observe_args' ), PHP_INT_MAX, 2 );
		add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
		add_filter( 'woocommerce_logger_log_message', array( self::class, 'capture_log' ), PHP_INT_MAX, 4 );
		add_filter( 'query', array( self::class, 'query' ), PHP_INT_MAX );
		$map = array(
			'famma_meta_pixel_id'            => 'pixel',
			'famma_meta_access_token'        => 'token',
			'famma_meta_orderplaced_enabled' => 'orderplaced',
			'famma_meta_capi_test_mode'      => 'test_mode',
			'famma_meta_test_event_code'     => 'test_code',
			'famma_tiktok_pixel_id'          => 'tiktok',
			'famma_ga4_measurement_id'       => 'ga4',
		);
		foreach ( $map as $option => $key ) {
			add_filter( 'pre_option_' . $option, static fn(): string => (string) self::$cfg[ $key ], PHP_INT_MAX );
		}
		add_action( 'woocommerce_checkout_order_created', array( self::class, 'mark_order' ), PHP_INT_MAX );
	}

	/** Main mode: CRUD scenarios T2.2 to T2.13. */
	private static function main(): void {
		self::t22_t23();
		self::t24();
		self::t25();
		self::t26_queue();
		self::t27();
		self::t28();
		self::t29();
		self::t211();
		self::t213();
		self::t212();
	}

	// ------------------------------------------------------------------
	// T2.2 / T2.3 — cycle Purchase, puis livrée → retournée → livrée
	// ------------------------------------------------------------------

	/** Purchase cycle and redelivery. */
	private static function t22_t23(): void {
		self::$scenario  = 'T2.2';
		self::$meta_mode = 'ok';
		$order           = self::order( 'T2.2' );
		$id              = $order->get_id();
		$quiet           = array();
		foreach ( array( 'confirmed', 'shipped' ) as $status ) {
			$before = count( self::$requests );
			self::fresh( $id )->update_status( $status, 'QA-L2 T2.2 : passage au statut ' . $status . '.' );
			$quiet[ $status ] = count( self::$requests ) - $before;
		}
		$logs_before = count( self::$logs );
		$t0          = time();
		self::fresh( $id )->update_status( 'delivered', 'QA-L2 T2.2 : livraison simulée.' );
		$t1        = time();
		$purchases = self::graph( 'Purchase', $id );
		$request   = $purchases[0] ?? array();
		$event     = $request['body']['data'][0] ?? array();
		$user      = (array) ( $event['user_data'] ?? array() );
		$fresh     = self::fresh( $id );
		$registry  = self::registry( 'Purchase', 'famma_purchase_' . $id );
		$s         = 'T2.2';
		self::check( $s, 'aucun-envoi-avant-livree', ( 0 === array_sum( $quiet ) ), 0, $quiet );
		self::check( $s, 'un-seul-purchase', ( 1 === count( $purchases ) ), 1, count( $purchases ) );
		self::check( $s, 'event-id', ( ( $event['event_id'] ?? '' ) === 'famma_purchase_' . $id ), 'famma_purchase_' . $id, $event['event_id'] ?? null );
		self::check( $s, 'value-total-reel', ( (float) ( $event['custom_data']['value'] ?? -1 ) === round( (float) $fresh->get_total(), 3 ) ), (float) $fresh->get_total(), $event['custom_data']['value'] ?? null );
		self::check( $s, 'currency-tnd', ( 'TND' === ( $event['custom_data']['currency'] ?? '' ) ), 'TND', $event['custom_data']['currency'] ?? null );
		self::check( $s, 'cod-paye-avant-envoi', ( 'yes' === ( $request['cod_paid_at_send'] ?? '' ) && ! empty( $request['date_paid_at_send'] ) ), '_famma_cod_paid=yes et date_paid posée au moment de la requête', array( $request['cod_paid_at_send'] ?? null, $request['date_paid_at_send'] ?? null ) );
		self::check( $s, 'commande-payee-apres', $fresh->is_paid() && $fresh->has_status( 'delivered' ), 'is_paid() vrai, statut delivered', array( $fresh->is_paid(), $fresh->get_status() ) );
		self::check( $s, 'version-api', ( 'v21.0' === ( $request['graph_version'] ?? '' ) ), 'v21.0', $request['graph_version'] ?? null );
		self::check( $s, 'destination', ( '/v21.0/' . self::FAKE_PIXEL . '/events' === ( $request['path'] ?? '' ) ), '/v21.0/' . self::FAKE_PIXEL . '/events', $request['path'] ?? null );
		self::check( $s, 'sans-test-event-code', ! array_key_exists( 'test_event_code', (array) ( $request['body'] ?? array() ) ), 'clé absente', array_key_exists( 'test_event_code', (array) ( $request['body'] ?? array() ) ) ? 'présente' : 'absente' );
		self::check( $s, 'jeton-corps-pas-url', ! empty( $request['token_present'] ) && ! empty( $request['token_is_qa_fake'] ) && empty( $request['token_in_url'] ), 'jeton factice dans le corps, absent de l\'URL', array_intersect_key( $request, array_flip( array( 'token_present', 'token_is_qa_fake', 'token_in_url' ) ) ) );
		self::check( $s, 'event-time-livraison', ( $event['event_time'] ?? 0 ) >= $t0 && ( $event['event_time'] ?? 0 ) <= $t1, array( $t0, $t1 ), $event['event_time'] ?? null );
		self::check( $s, 'action-source-website', ( 'website' === ( $event['action_source'] ?? '' ) ), 'website', $event['action_source'] ?? null );
		$expected_user = array(
			'em'                => hash( 'sha256', 'qa-l2@example.invalid' ),
			'ph'                => hash( 'sha256', '21620000000' ),
			'fn'                => hash( 'sha256', 'qa-l2' ),
			'ln'                => hash( 'sha256', 'fixture' ),
			'ct'                => hash( 'sha256', 'sousse' ),
			'country'           => hash( 'sha256', 'tn' ),
			'fbp'               => 'fb.1.1792400000000.1234567890',
			'fbc'               => 'fb.1.1792400000000.QA-L2-fbclid',
			'client_ip_address' => '192.0.2.10',
			'client_user_agent' => 'Mozilla/5.0 (QA-L2 synthetic user agent)',
		);
		$mismatch      = array();
		foreach ( $expected_user as $key => $value ) {
			if ( ( $user[ $key ] ?? null ) !== $value ) {
				$mismatch[] = $key;
			}
		}
		self::check(
			$s,
			'user-data-signaux',
			array() === $mismatch,
			'em, ph, fn, ln, ct, country hachés ; fbp, fbc, IP, UA en clair',
			array(
				'cles_envoyees' => array_keys( $user ),
				'ecarts'        => $mismatch,
			)
		);
		self::check( $s, 'user-data-st-doc-meta', ( hash( 'sha256', 'tn51' ) === ( $user['st'] ?? '' ) ), 'sha256("tn51") : minuscules sans ponctuation (doc Meta)', ( hash( 'sha256', 'tn-51' ) === ( $user['st'] ?? '' ) ) ? 'sha256("tn-51") : tiret conservé' : ( $user['st'] ?? null ) );
		self::check( $s, 'user-data-external-id', isset( $user['external_id'] ), 'external_id présent (recommandé par Meta)', isset( $user['external_id'] ) ? 'présent' : 'absent' );
		self::check( $s, 'note-et-journal', ( 1 === count( self::log_lines( 'Purchase', $id, $logs_before ) ) && str_contains( implode( '|', self::log_lines( 'Purchase', $id, $logs_before ) ), 'state=accepted' ) ), '1 ligne famma-capi state=accepted', self::log_lines( 'Purchase', $id, $logs_before ) );
		self::check( $s, 'registre-accepte', ( 'accepted' === ( $registry['state'] ?? '' ) && 1 === (int) ( $registry['attempt_count'] ?? 0 ) ), 'accepted, 1 tentative', $registry );
		self::$report[ $s ]['observations'] = array(
			'commande'            => $id,
			'hpos'                => self::hpos(),
			'requete_purchase'    => $request,
			'event_source_url'    => $event['event_source_url'] ?? null,
			'custom_data'         => $event['custom_data'] ?? null,
			'notes_meta_et_cod'   => self::notes( $id ),
			'duree_envoi_timeout' => $request['timeout'] ?? null,
			'bloquant'            => $request['blocking'] ?? null,
		);

		// T2.3 — livrée → retournée → livrée à nouveau.
		$s              = 'T2.3';
		self::$scenario = $s;
		$notes0         = count( self::notes( $id ) );
		self::fresh( $id )->update_status( 'returned', 'QA-L2 T2.3 : retour simulé.' );
		$returned  = self::fresh( $id );
		$after_ret = array(
			'statut'      => $returned->get_status(),
			'is_paid'     => $returned->is_paid(),
			'date_paid'   => $returned->get_date_paid() ? $returned->get_date_paid()->date( 'c' ) : null,
			'cod_paid'    => $returned->get_meta( '_famma_cod_paid' ),
			'purchase_ok' => (bool) $returned->get_meta( '_famma_purchase_sent' ),
		);
		self::fresh( $id )->update_status( 'delivered', 'QA-L2 T2.3 : nouvelle livraison simulée.' );
		$again = self::graph( 'Purchase', $id );
		self::check( $s, 'pas-de-second-purchase', ( 1 === count( $again ) ), 1, count( $again ) );
		self::$report[ $s ]['observations'] = array(
			'commande'          => $id,
			'apres_retour'      => $after_ret,
			'apres_relivraison' => array(
				'statut'   => self::fresh( $id )->get_status(),
				'is_paid'  => self::fresh( $id )->is_paid(),
				'cod_paid' => self::fresh( $id )->get_meta( '_famma_cod_paid' ),
			),
			'nouvelles_notes'   => array_slice( self::notes( $id ), $notes0 ),
			'remarque'          => 'Après « retournée », le marqueur _famma_purchase_sent reste posé : Meta garde le Purchase (aucun événement d\'annulation ni de remboursement n\'est envoyé).',
		);

		// Variante : livrée → terminée (T2.4, chemin « depuis livrée »).
		self::$scenario = 'T2.4';
		self::fresh( $id )->update_status( 'completed', 'QA-L2 T2.4 : terminée après livraison.' );
		self::$report['T2.4']['observations']['depuis_livree'] = array(
			'commande'       => $id,
			'purchase_total' => count( self::graph( 'Purchase', $id ) ),
			'statut'         => self::fresh( $id )->get_status(),
			'is_paid'        => self::fresh( $id )->is_paid(),
		);
	}

	// ------------------------------------------------------------------
	// T2.4 — statut « Terminée » au lieu de « Livrée »
	// ------------------------------------------------------------------

	/** Completed instead of delivered. */
	private static function t24(): void {
		self::$scenario = 'T2.4';
		$order          = self::order( 'T2.4' );
		$id             = $order->get_id();
		foreach ( array( 'confirmed', 'shipped', 'completed' ) as $status ) {
			self::fresh( $id )->update_status( $status, 'QA-L2 T2.4 : passage au statut ' . $status . '.' );
		}
		$fresh = self::fresh( $id );
		self::check( 'T2.4', 'purchase-sur-terminee', 1 === count( self::graph( 'Purchase', $id ) ), 'à trancher : 1 Purchase si « Terminée » vaut encaissement', count( self::graph( 'Purchase', $id ) ) );
		self::check(
			'T2.4',
			'payee-sans-livree',
			! $fresh->is_paid(),
			'non payée tant que non livrée (règle COD)',
			array(
				'is_paid'         => $fresh->is_paid(),
				'date_paid'       => $fresh->get_date_paid() ? $fresh->get_date_paid()->date( 'c' ) : null,
				'_famma_cod_paid' => $fresh->get_meta( '_famma_cod_paid' ),
			)
		);
		self::$report['T2.4']['observations']['expedie_vers_terminee'] = array(
			'commande'        => $id,
			'statut'          => $fresh->get_status(),
			'purchase'        => count( self::graph( 'Purchase', $id ) ),
			'is_paid'         => $fresh->is_paid(),
			'date_paid'       => $fresh->get_date_paid() ? $fresh->get_date_paid()->date( 'c' ) : null,
			'_famma_cod_paid' => $fresh->get_meta( '_famma_cod_paid' ),
			'notes'           => self::notes( $id ),
		);
	}

	// ------------------------------------------------------------------
	// T2.5 — commande créée dans l'admin, puis livrée
	// ------------------------------------------------------------------

	/** Admin-created order delivered. */
	private static function t25(): void {
		self::$scenario = 'T2.5';
		$order          = self::order( 'T2.5', 'admin', false );
		$id             = $order->get_id();
		self::queue( $order );
		$queued = self::pending( $id );
		foreach ( array( 'confirmed', 'shipped', 'delivered' ) as $status ) {
			self::fresh( $id )->update_status( $status, 'QA-L2 T2.5 : passage au statut ' . $status . '.' );
		}
		$purchases = self::graph( 'Purchase', $id );
		$event     = $purchases[0]['body']['data'][0] ?? array();
		$user      = (array) ( $event['user_data'] ?? array() );
		$missing   = array_values( array_diff( array( 'client_user_agent', 'client_ip_address', 'fbp', 'fbc' ), array_keys( $user ) ) );
		self::check( 'T2.5', 'orderplaced-exclu', 0 === $queued, 0, $queued );
		self::check( 'T2.5', 'purchase-envoye', 1 === count( $purchases ), 1, count( $purchases ) );
		self::check(
			'T2.5',
			'website-avec-user-agent',
			isset( $user['client_user_agent'] ),
			'client_user_agent obligatoire pour action_source=website (veille S10/S11, exploration H7)',
			array(
				'action_source'  => $event['action_source'] ?? null,
				'cles_user_data' => array_keys( $user ),
				'absentes'       => $missing,
			)
		);
		self::$report['T2.5']['observations'] = array(
			'commande'         => $id,
			'created_via'      => self::fresh( $id )->get_created_via(),
			'action_source'    => $event['action_source'] ?? null,
			'event_source_url' => $event['event_source_url'] ?? null,
			'cles_user_data'   => array_keys( $user ),
			'cles_absentes'    => $missing,
			'remarque'         => 'L\'événement est déclaré « website » alors qu\'aucun navigateur n\'est lié à la commande : ni UA, ni IP, ni fbp/fbc. event_source_url pointe la page commande.',
		);
	}

	// ------------------------------------------------------------------
	// T2.6 (partie CRUD) — résultat de paiement rejoué
	// ------------------------------------------------------------------

	/** Same accepted checkout reported twice to the gateway filter. */
	private static function t26_queue(): void {
		self::$scenario = 'T2.6';
		$order          = self::order( 'T2.6-file' );
		self::queue( $order );
		self::queue( $order );
		$pending = self::pending( $order->get_id() );
		self::run_queued( $order->get_id() );
		do_action( 'famma_capi_orderplaced_send', $order->get_id() );
		self::check(
			'T2.6',
			'file-unique-meme-commande',
			1 === $pending && 1 === count( self::graph( 'OrderPlaced', $order->get_id() ) ),
			'1 tâche, 1 OrderPlaced',
			array(
				'taches'      => $pending,
				'orderplaced' => count( self::graph( 'OrderPlaced', $order->get_id() ) ),
			)
		);
		self::$report['T2.6']['observations']['meme_commande_deux_resultats'] = array(
			'commande' => $order->get_id(),
			'remarque' => 'Deux résultats « success » pour UNE commande : une seule tâche (as_enqueue_async_action unique). La double soumission réelle est jouée en mode express (séquentiel) et par qa-l2-checkout-double.sh (parallèle).',
		);
	}

	// ------------------------------------------------------------------
	// T2.7 — chaque issue d'OrderPlaced laisse-t-elle une ligne ?
	// ------------------------------------------------------------------

	/** One journal line per OrderPlaced outcome. */
	private static function t27(): void {
		self::$scenario = 'T2.7';
		$cases          = array();

		$a = self::order( 'T2.7-envoye' );
		self::queue( $a );
		$cases['envoye']      = self::outcome( $a->get_id(), static fn(): array => self::run_queued( $a->get_id() ) );
		$cases['deja_envoye'] = self::outcome(
			$a->get_id(),
			static function () use ( $a ): array {
				do_action( 'famma_capi_orderplaced_send', $a->get_id() );
				return array( 'declenchement' => 'tâche rejouée (do_action)' );
			}
		);

		$b = self::order( 'T2.7-desactive' );
		self::queue( $b );
		self::$cfg['orderplaced'] = '0';
		$cases['desactive']       = self::outcome( $b->get_id(), static fn(): array => self::run_queued( $b->get_id() ) );
		self::$cfg['orderplaced'] = '1';

		$c = self::order( 'T2.7-annulee' );
		self::queue( $c );
		self::fresh( $c->get_id() )->update_status( 'cancelled', 'QA-L2 T2.7 : annulée avant exécution de la tâche.' );
		$cases['ineligible_annulee'] = self::outcome( $c->get_id(), static fn(): array => self::run_queued( $c->get_id() ) );

		$d                         = self::order( 'T2.7-admin', 'admin', false );
		$cases['ineligible_admin'] = self::outcome(
			$d->get_id(),
			static function () use ( $d ): array {
				self::queue( $d );
				return array( 'taches_creees' => self::pending( $d->get_id() ) );
			}
		);

		$e = self::order( 'T2.7-sans-jeton' );
		self::queue( $e );
		self::$cfg['token']     = '';
		$cases['non_configure'] = self::outcome( $e->get_id(), static fn(): array => self::run_queued( $e->get_id() ) );
		self::$cfg['token']     = self::FAKE_TOKEN;

		$f = self::order( 'T2.7-incertain' );
		self::queue( $f );
		self::$meta_mode                    = 'wp_error';
		$cases['echec_transport']           = self::outcome( $f->get_id(), static fn(): array => self::run_queued( $f->get_id() ) );
		self::$meta_mode                    = 'ok';
		$cases['ignore_registre_incertain'] = self::outcome(
			$f->get_id(),
			static function () use ( $f ): array {
				do_action( 'famma_capi_orderplaced_send', $f->get_id() );
				return array( 'declenchement' => 'tâche rejouée après échec' );
			}
		);

		$g = self::order( 'T2.7-table-absente' );
		self::queue( $g );
		self::$missing_table                   = true;
		$cases['ignore_registre_indisponible'] = self::outcome( $g->get_id(), static fn(): array => self::run_queued( $g->get_id() ) );
		self::$missing_table                   = false;

		foreach ( $cases as $name => $case ) {
			self::check(
				'T2.7',
				'trace-' . $name,
				$case['lignes_journal'] >= 1,
				'au moins 1 ligne famma-capi',
				array(
					'lignes' => $case['lignes_journal'],
					'notes'  => $case['nouvelles_notes'],
					'http'   => $case['envois_http'],
					'tache'  => $case['declenchement'],
				)
			);
		}
		self::$report['T2.7']['observations'] = array(
			'issues'   => $cases,
			'remarque' => 'Les issues sans ligne correspondent à une tâche Action Scheduler « complete » sans aucune trace famma-capi : c\'est le profil observé sur #288 et #289 en production.',
		);
	}

	/**
	 * Observe one OrderPlaced attempt: HTTP, journal, notes and task.
	 *
	 * @param int      $order_id Order.
	 * @param callable $trigger  Attempt.
	 * @return array<string,mixed>
	 */
	private static function outcome( int $order_id, callable $trigger ): array {
		$http  = count( self::graph( 'OrderPlaced', $order_id ) );
		$logs  = count( self::$logs );
		$notes = count( self::notes( $order_id ) );
		$info  = $trigger();
		$order = self::fresh( $order_id );
		return array(
			'commande'        => $order_id,
			'declenchement'   => $info,
			'envois_http'     => count( self::graph( 'OrderPlaced', $order_id ) ) - $http,
			'lignes_journal'  => count( self::log_lines( 'OrderPlaced', $order_id, $logs ) ),
			'journal'         => self::log_lines( 'OrderPlaced', $order_id, $logs ),
			'nouvelles_notes' => array_slice( self::notes( $order_id ), $notes ),
			'marqueur_envoye' => (bool) $order->get_meta( '_famma_orderplaced_sent' ),
			'registre'        => self::registry( 'OrderPlaced', 'famma_orderplaced_' . $order_id ),
			'relance'         => self::retry_scheduled( $order_id ),
		);
	}

	// ------------------------------------------------------------------
	// T2.8 — repli WP-Cron et rejeu tardif
	// ------------------------------------------------------------------

	/** Fallback queue and late replay. */
	private static function t28(): void {
		self::$scenario = 'T2.8';
		$s              = 'T2.8';
		self::$report[ $s ]['observations']['action_scheduler_present'] = function_exists( 'as_enqueue_async_action' );
		self::$report[ $s ]['observations']['adaptation']               = 'Avec WooCommerce actif, as_enqueue_async_action existe toujours : la branche wp_schedule_single_event (famma-core.php:2016-2017) n\'est pas atteignable. Elle est rejouée à l\'identique (même crochet, mêmes arguments) puis exécutée comme wp-cron.php.';

		$h = self::order( 'T2.8-cron' );
		self::queue( $h );
		as_unschedule_all_actions( 'famma_capi_orderplaced_send', array( 'order_id' => $h->get_id() ), 'famma-capi' );
		$args = array( 'order_id' => $h->get_id() );
		if ( ! wp_next_scheduled( 'famma_capi_orderplaced_send', $args ) ) {
			wp_schedule_single_event( time() + 1, 'famma_capi_orderplaced_send', $args );
		}
		$next   = wp_next_scheduled( 'famma_capi_orderplaced_send', $args );
		$before = count( self::graph( 'OrderPlaced', $h->get_id() ) );
		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			foreach ( (array) ( $hooks['famma_capi_orderplaced_send'] ?? array() ) as $event ) {
				if ( $event['args'] === $args ) {
					wp_unschedule_event( $timestamp, 'famma_capi_orderplaced_send', $event['args'] );
					// Même appel que wp-cron.php.
					do_action_ref_array( 'famma_capi_orderplaced_send', $event['args'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- famma-core hook replayed as WP-Cron does.
				}
			}
		}
		$sent = count( self::graph( 'OrderPlaced', $h->get_id() ) ) - $before;
		self::check(
			$s,
			'repli-cron-envoie',
			false !== $next && 1 === $sent && (bool) self::fresh( $h->get_id() )->get_meta( '_famma_orderplaced_sent' ),
			'événement WP-Cron planifié puis 1 OrderPlaced accepté',
			array(
				'planifie' => (bool) $next,
				'envois'   => $sent,
			)
		);

		$i = self::order( 'T2.8-tardif', 'checkout', true, 8 * self::DAY );
		self::queue( $i );
		self::$meta_mode = 'too_old';
		$late            = self::outcome( $i->get_id(), static fn(): array => self::run_queued( $i->get_id() ) );
		self::$meta_mode = 'ok';
		$first           = self::graph( 'OrderPlaced', $i->get_id() );
		$age             = isset( $first[0]['body']['data'][0]['event_time'] ) ? round( ( time() - (int) $first[0]['body']['data'][0]['event_time'] ) / self::DAY, 2 ) : null;
		$retry           = self::outcome(
			$i->get_id(),
			static function () use ( $i ): array {
				do_action( 'famma_capi_orderplaced_send', $i->get_id() );
				return array( 'declenchement' => 'rejeu manuel après le rejet' );
			}
		);
		$all             = self::graph( 'OrderPlaced', $i->get_id() );
		$times           = array_map( static fn( array $r ): int => (int) ( $r['body']['data'][0]['event_time'] ?? 0 ), $all );
		self::check( $s, 'rejeu-tardif-age-event-time', null !== $age && $age <= 7, 'event_time de moins de 7 jours au moment du rejeu', $age );
		self::check(
			$s,
			'rejet-trace',
			( 'rejected' === ( $late['registre']['state'] ?? '' ) && $late['lignes_journal'] >= 1 ),
			'état rejected + ligne de journal',
			array(
				'etat'   => $late['registre']['state'] ?? null,
				'lignes' => $late['lignes_journal'],
			)
		);
		self::$report[ $s ]['observations']['rejeu_tardif'] = array(
			'commande'              => $i->get_id(),
			'age_event_time_jours'  => $age,
			'premier_envoi'         => $late,
			'rejeu'                 => $retry,
			'event_time_identiques' => 1 === count( array_unique( $times ) ),
			'remarque'              => 'Réponses 400 code 100 SIMULÉES (aucun appel réel à Meta) selon la règle documentée : event_time plus vieux que 7 jours refusé. OrderPlaced garde l\'heure de création : le rejeu repart avec le même event_time et est donc refusé à nouveau (veille E7). Le rejeu manuel est le seul déclencheur : rien n\'est planifié.',
		);

		// Purchase : une reprise après rejet garde l'event_time de la première tentative.
		$j = self::order( 'T2.8-purchase-reprise' );
		foreach ( array( 'confirmed', 'shipped' ) as $status ) {
			self::fresh( $j->get_id() )->update_status( $status, 'QA-L2 T2.8.' );
		}
		self::$meta_mode = 'too_old';
		self::fresh( $j->get_id() )->update_status( 'delivered', 'QA-L2 T2.8 : première livraison, rejet simulé.' );
		self::$meta_mode = 'ok';
		sleep( 2 );
		self::fresh( $j->get_id() )->update_status( 'shipped', 'QA-L2 T2.8 : retour en expédiée.' );
		self::fresh( $j->get_id() )->update_status( 'delivered', 'QA-L2 T2.8 : seconde livraison.' );
		$pt = array_map( static fn( array $r ): int => (int) ( $r['body']['data'][0]['event_time'] ?? 0 ), self::graph( 'Purchase', $j->get_id() ) );
		self::$report[ $s ]['observations']['purchase_reprise'] = array(
			'commande'        => $j->get_id(),
			'envois'          => count( $pt ),
			'event_time'      => $pt,
			'meme_event_time' => count( $pt ) > 1 && 1 === count( array_unique( $pt ) ),
			'registre'        => self::registry( 'Purchase', 'famma_purchase_' . $j->get_id() ),
			'remarque'        => 'Seule une nouvelle transition vers « livrée » relance Purchase ; l\'event_time de la 1re tentative est réutilisé.',
		);
	}

	// ------------------------------------------------------------------
	// T2.9 — pixel vidé : cookie famma_utm et UTM de la commande
	// ------------------------------------------------------------------

	/** UTM persistence when the pixel is empty. */
	private static function t29(): void {
		self::$scenario       = 'T2.9';
		$probe                = static function ( string $pixel ): array {
			self::$cfg['pixel']    = $pixel;
			$GLOBALS['wp_scripts'] = new WP_Scripts(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- fresh script registry per probe, as a new page request would have.
			\Famma\Core\Tracking::instance()->enqueue_tracking();
			\Famma\Core\Capi_Signals::instance()->enqueue_cookie_script();
			$after = wp_script_is( 'famma-pixels', 'registered' ) ? (array) wp_scripts()->get_data( 'famma-pixels', 'after' ) : array();
			$js    = implode( "\n", array_filter( $after, 'is_string' ) );
			return array(
				'pixel'                 => '' === $pixel ? '(vide)' : 'factice',
				'script_famma_pixels'   => wp_script_is( 'famma-pixels', 'registered' ),
				'script_pose_famma_utm' => str_contains( $js, 'famma_utm=' ),
				'script_fbc_fbp'        => wp_script_is( \Famma\Core\Capi_Signals::HANDLE, 'registered' ),
			);
		};
		$with                 = $probe( self::FAKE_PIXEL );
		$without              = $probe( '' );
		$order                = self::order( 'T2.9' );
		$_COOKIE['famma_utm'] = wp_json_encode(
			array(
				'utm_source'   => 'qa-l2-source',
				'utm_medium'   => 'qa-l2-medium',
				'utm_campaign' => 'qa-l2-campagne',
			)
		);
		\Famma\Core\Tracking::instance()->stamp_utm_on_order( $order );
		$order->save();
		unset( $_COOKIE['famma_utm'] );
		self::$cfg['pixel']    = self::FAKE_PIXEL;
		$GLOBALS['wp_scripts'] = new WP_Scripts(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restore a clean registry after the probes.
		$stamped               = self::fresh( $order->get_id() )->get_meta( '_famma_utm_source' );
		self::check( 'T2.9', 'cookie-utm-sans-pixel', $without['script_pose_famma_utm'], 'script de capture UTM présent même sans pixel (commentaire famma-core.php:1810)', $without );
		self::$report['T2.9']['observations'] = array(
			'avec_pixel'                   => $with,
			'sans_pixel'                   => $without,
			'copie_sur_commande_si_cookie' => $stamped,
			'remarque'                     => 'stamp_utm_on_order() recopie bien un cookie existant, mais sans pixel (ni TikTok) enqueue_tracking() s\'arrête avant le script qui pose famma_utm : en vrai, aucun cookie, donc aucune UTM sur la commande. Le cookie _fbc/_fbp n\'est pas posé non plus.',
		);
	}

	// ------------------------------------------------------------------
	// T2.11 — base clonée avec un jeton : ce qui partirait vraiment
	// ------------------------------------------------------------------

	/** What a cloned database with a token would send. */
	private static function t211(): void {
		self::$scenario = 'T2.11';
		$variants       = array(
			'mode_test_eteint'        => array( '0', '' ),
			'mode_test_code_valide'   => array( '1', 'TESTQAL2' ),
			'mode_test_code_invalide' => array( '1', 'QA-L2' ),
		);
		$seen           = array();
		foreach ( $variants as $name => $variant ) {
			self::$cfg['test_mode'] = $variant[0];
			self::$cfg['test_code'] = $variant[1];
			$order                  = self::order( 'T2.11-' . $name );
			foreach ( array( 'confirmed', 'shipped', 'delivered' ) as $status ) {
				self::fresh( $order->get_id() )->update_status( $status, 'QA-L2 T2.11.' );
			}
			$request       = self::graph( 'Purchase', $order->get_id() )[0] ?? array();
			$seen[ $name ] = array(
				'commande'          => $order->get_id(),
				'url'               => $request['url'] ?? null,
				'test_event_code'   => $request['body']['test_event_code'] ?? '(absent)',
				'event_id'          => $request['body']['data'][0]['event_id'] ?? null,
				'jeton_present'     => $request['token_present'] ?? null,
				'jeton_est_factice' => $request['token_is_qa_fake'] ?? null,
			);
		}
		self::$cfg['test_mode'] = '0';
		self::$cfg['test_code'] = '';
		self::check(
			'T2.11',
			'aucune-garde-environnement',
			'local' !== wp_get_environment_type() || '(absent)' !== $seen['mode_test_eteint']['test_event_code'],
			'un clone « local » ne devrait pas envoyer de vrais événements',
			array(
				'environnement' => wp_get_environment_type(),
				'envoi'         => $seen['mode_test_eteint'],
			)
		);
		self::$report['T2.11']['observations'] = array(
			'variantes'     => $seen,
			'environnement' => wp_get_environment_type(),
			'remarque'      => 'Avec le vrai pixel et le vrai jeton recopiés, la même requête partirait vers https://graph.facebook.com/v21.0/<pixel de prod>/events, sans test_event_code (mode test éteint par défaut), avec event_id = famma_purchase_<numéro> : rien ne distingue le clone. Une commande locale de même numéro qu\'une commande réelle porterait le même event_id.',
		);
	}

	// ------------------------------------------------------------------
	// T2.13 (= T1.5) — registre d'envoi face aux échecs
	// ------------------------------------------------------------------

	/** Delivery registry against failures, on Purchase. */
	private static function t213(): void {
		self::$scenario = 'T2.13';
		$cases          = array();
		foreach ( array( 'wp_error', 'timeout', 'http500', 'no_events', 'table_absente', 'tentatives_epuisees' ) as $mode ) {
			$order = self::order( 'T2.13-' . $mode );
			$id    = $order->get_id();
			if ( 'tentatives_epuisees' === $mode ) {
				$o = self::fresh( $id );
				$o->update_meta_data( '_famma_capi_attempts', 5 );
				$o->save();
			}
			foreach ( array( 'confirmed', 'shipped' ) as $status ) {
				self::fresh( $id )->update_status( $status, 'QA-L2 T2.13.' );
			}
			$notes0              = count( self::notes( $id ) );
			$logs0               = count( self::$logs );
			$http0               = count( self::graph( 'Purchase', $id ) );
			self::$meta_mode     = in_array( $mode, array( 'table_absente', 'tentatives_epuisees' ), true ) ? 'ok' : $mode;
			self::$missing_table = 'table_absente' === $mode;
			$start               = microtime( true );
			self::fresh( $id )->update_status( 'delivered', 'QA-L2 T2.13 : livraison (' . $mode . ').' );
			$elapsed             = round( microtime( true ) - $start, 3 );
			self::$meta_mode     = 'ok';
			self::$missing_table = false;
			$sent_now            = array_slice( self::graph( 'Purchase', $id ), $http0 );
			$first               = array(
				'timeout_s'       => $sent_now[0]['timeout'] ?? null,
				'bloquant'        => $sent_now[0]['blocking'] ?? null,
				'envois_http'     => count( self::graph( 'Purchase', $id ) ) - $http0,
				'registre'        => self::registry( 'Purchase', 'famma_purchase_' . $id ),
				'marqueur_envoye' => (bool) self::fresh( $id )->get_meta( '_famma_purchase_sent' ),
				'tentatives_meta' => self::fresh( $id )->get_meta( '_famma_capi_attempts' ),
				'notes'           => array_slice( self::notes( $id ), $notes0 ),
				'journal'         => self::log_lines( 'Purchase', $id, $logs0 ),
				'relance'         => self::retry_scheduled( $id ),
				'cod_paye'        => self::fresh( $id )->get_meta( '_famma_cod_paid' ),
				'duree_s'         => $elapsed,
			);
			$notes1              = count( self::notes( $id ) );
			$logs1               = count( self::$logs );
			$http1               = count( self::graph( 'Purchase', $id ) );
			do_action( 'famma_capi_purchase', self::fresh( $id ), 'famma_purchase_' . $id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- famma-core hook.
			self::fresh( $id )->update_status( 'shipped', 'QA-L2 T2.13 : retour en expédiée.' );
			self::fresh( $id )->update_status( 'delivered', 'QA-L2 T2.13 : nouvelle livraison.' );
			$retry          = array(
				'envois_http'     => count( self::graph( 'Purchase', $id ) ) - $http1,
				'notes'           => array_slice( self::notes( $id ), $notes1 ),
				'journal'         => self::log_lines( 'Purchase', $id, $logs1 ),
				'registre'        => self::registry( 'Purchase', 'famma_purchase_' . $id ),
				'marqueur_envoye' => (bool) self::fresh( $id )->get_meta( '_famma_purchase_sent' ),
			);
			$cases[ $mode ] = array(
				'commande'      => $id,
				'reponse_meta'  => $mode,
				'premier_essai' => $first,
				'reprise'       => $retry,
			);
			$meta_notes     = array_values( array_filter( $first['notes'], static fn( string $n ): bool => str_starts_with( $n, 'Meta ' ) ) );
			$traced         = array() !== $first['journal'] || array() !== $meta_notes;
			self::check(
				'T2.13',
				'trace-' . $mode,
				$traced,
				'note Meta sur la commande ou ligne famma-capi',
				array(
					'notes_meta' => $meta_notes,
					'journal'    => $first['journal'],
				)
			);
			self::check(
				'T2.13',
				'relance-' . $mode,
				! empty( $first['relance']['planifiee'] ),
				'relance planifiée automatiquement (Action Scheduler ou WP-Cron)',
				array(
					'relance'                  => $first['relance'],
					'rattrapage_manuel_reussi' => $retry['marqueur_envoye'],
				)
			);
		}
		self::$report['T2.13']['observations'] = array(
			'cas'        => $cases,
			'adaptation' => 'Table absente simulée dans CE processus seulement (filtre query qui renomme la table dans les requêtes), sans toucher à la table partagée. La suite existante test-capi-registre-integration la renomme réellement (T2.1).',
			'remarque'   => 'Purchase est envoyé de façon synchrone dans la requête qui change le statut (timeout 8 s, bloquant).',
		);
	}

	// ------------------------------------------------------------------
	// T2.12 (partie serveur) — jeton dans les pages, scripts et journaux
	// ------------------------------------------------------------------

	/** Token exposure on the server side. */
	private static function t212(): void {
		global $wpdb;
		self::$scenario = 'T2.12';
		$admins         = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		wp_set_current_user( (int) ( $admins[0] ?? 0 ) );
		$settings = \Famma\Core\Tracking_Settings::instance();
		$settings->register_settings();
		// Titre d'écran fourni comme le fait wp-admin (sinon get_admin_page_title() émet un avis en CLI).
		$GLOBALS['title'] = 'QA-L2 Mesure'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- admin screen title, set by wp-admin in a real request.
		ob_start();
		$settings->render_page();
		$admin_html = (string) ob_get_clean();
		unset( $GLOBALS['title'] );
		$rest      = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/settings' ) );
		$rest_json = (string) wp_json_encode( $rest->get_data() );
		wp_set_current_user( 0 );

		$GLOBALS['wp_scripts'] = new WP_Scripts(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- fresh script registry, as a page request would have.
		\Famma\Core\Tracking::instance()->enqueue_tracking();
		\Famma\Core\Capi_Signals::instance()->enqueue_cookie_script();
		$inline = '';
		foreach ( wp_scripts()->registered as $handle => $script ) {
			foreach ( array( 'before', 'after', 'data' ) as $key ) {
				$inline .= ' ' . $handle . ':' . wp_json_encode( $script->extra[ $key ] ?? '' );
			}
		}
		$GLOBALS['wp_scripts'] = new WP_Scripts(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restore a clean registry.

		$uploads  = wp_upload_dir( null, false );
		$files    = array_merge(
			array( WP_CONTENT_DIR . '/debug.log', $uploads['basedir'] . '/qa-http-log.jsonl' ),
			(array) glob( $uploads['basedir'] . '/wc-logs/*.log' )
		);
		$in_files = array();
		foreach ( $files as $file ) {
			if ( is_readable( $file ) ) {
				$in_files[ str_replace( ABSPATH, '', $file ) ] = substr_count( (string) file_get_contents( $file ), self::FAKE_TOKEN ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local log files of the disposable clone.
			}
		}
		$like = '%' . $wpdb->esc_like( self::FAKE_TOKEN ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only exposure scan of the disposable clone.
		$db = array(
			'options'             => $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE %s", $like ) ),
			'postmeta'            => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $like ) ),
			'commentaires_notes'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_content LIKE %s", $like ) ),
			'wc_orders_meta'      => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE meta_value LIKE %s', $wpdb->prefix . 'wc_orders_meta', $like ) ),
			'registre_capi'       => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE attempts LIKE %s', \Famma\Core\Capi_Delivery_Registry::instance()->table(), $like ) ),
			'actionscheduler_log' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE message LIKE %s', $wpdb->prefix . 'actionscheduler_logs', $like ) ),
			'actionscheduler_arg' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE args LIKE %s OR extended_args LIKE %s', $wpdb->prefix . 'actionscheduler_actions', $like, $like ) ),
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$notes = 0;
		foreach ( self::$orders as $id ) {
			$notes += substr_count( implode( ' ', self::notes( $id ) ), self::FAKE_TOKEN );
		}
		$journal = substr_count( implode( ' ', self::$logs ), self::FAKE_TOKEN );
		self::check( 'T2.12', 'admin-sans-jeton', ! str_contains( $admin_html, self::FAKE_TOKEN ), 'jeton absent de l\'écran Mesure', str_contains( $admin_html, self::FAKE_TOKEN ) ? 'présent' : 'absent' );
		self::check( 'T2.12', 'admin-4-derniers', str_contains( $admin_html, '<code>' . substr( self::FAKE_TOKEN, -4 ) . '</code>' ), '4 derniers caractères seulement', str_contains( $admin_html, '<code>' . substr( self::FAKE_TOKEN, -4 ) . '</code>' ) ? 'affichés' : 'absents' );
		self::check( 'T2.12', 'rest-settings-sans-jeton', ! str_contains( $rest_json, self::FAKE_TOKEN ), 'absent de /wp/v2/settings', str_contains( $rest_json, self::FAKE_TOKEN ) ? 'présent' : 'absent' );
		self::check( 'T2.12', 'scripts-inline-sans-jeton', ! str_contains( $inline, self::FAKE_TOKEN ), 'absent des scripts enfilés', str_contains( $inline, self::FAKE_TOKEN ) ? 'présent' : 'absent' );
		self::check( 'T2.12', 'journaux-fichiers-sans-jeton', 0 === array_sum( $in_files ), 0, $in_files );
		self::check(
			'T2.12',
			'journal-famma-capi-et-notes',
			0 === $journal + $notes,
			0,
			array(
				'journal' => $journal,
				'notes'   => $notes,
			)
		);
		self::check( 'T2.12', 'base-hors-option', array( 'famma_meta_access_token' ) === $db['options'] && 0 === $db['postmeta'] + $db['commentaires_notes'] + $db['wc_orders_meta'] + $db['registre_capi'] + $db['actionscheduler_log'] + $db['actionscheduler_arg'], 'seule l\'option famma_meta_access_token', $db );
		$exposed                               = array_values( array_filter( self::$args_seen, static fn( array $a ): bool => ! empty( $a['body_contient_jeton'] ) ) );
		self::$report['T2.12']['observations'] = array(
			'http_request_args' => array(
				'requetes_graph_vues' => count( self::$args_seen ),
				'corps_avec_jeton'    => count( $exposed ),
				'remarque'            => 'Tout greffon accroché à http_request_args ou http_api_debug reçoit le corps avec le jeton (constat, pas une fuite de famma-core). Les pages HTML/JS sont scannées par qa-l2-token-scan.sh.',
			),
		);
	}

	// ------------------------------------------------------------------
	// Mode express : T2.6 (séquentiel) et T2.10
	// ------------------------------------------------------------------

	/**
	 * Real wc-ajax endpoints, in this process.
	 *
	 * @throws RuntimeException When AJAX interception cannot be guaranteed.
	 */
	private static function express_mode(): void {
		add_filter( 'wp_die_ajax_handler', array( self::class, 'die_handler' ), PHP_INT_MAX );
		add_filter( 'famma_anti_abuse_limits', array( self::class, 'test_limits' ), PHP_INT_MAX );
		wc_maybe_define_constant( 'DOING_AJAX', true );
		if ( ! wp_doing_ajax() ) {
			throw new RuntimeException( 'AJAX interception cannot be guaranteed.' );
		}
		\Famma\Core\Express_Idempotency::instance()->install();
		$product = self::product( false );
		$utm     = array(
			'utm_source'   => 'qa-l2-facebook',
			'utm_medium'   => 'qa-l2-paid',
			'utm_campaign' => 'qa-l2-campagne',
		);

		// Checkout classique : double soumission séquentielle (même session, même jeton).
		self::$scenario = 'T2.6';
		self::session();
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();
		WC()->session->save_data();
		$classic              = array(
			'billing_first_name'                 => 'QA-L2 Classique',
			'billing_phone'                      => '20 000 011',
			'billing_state'                      => 'TN-51',
			'billing_country'                    => 'TN',
			'billing_address_1'                  => 'QA-L2 adresse fictive',
			'billing_city'                       => 'Sousse',
			'billing_email'                      => '',
			'payment_method'                     => 'cod',
			'ship_to_different_address'          => '0',
			'famma_hp_site'                      => '',
			// Champs que le script d'attribution de WooCommerce remplit dans le vrai formulaire.
			'wc_order_attribution_source_type'   => 'utm',
			'wc_order_attribution_utm_source'    => $utm['utm_source'],
			'wc_order_attribution_utm_medium'    => $utm['utm_medium'],
			'wc_order_attribution_utm_campaign'  => $utm['utm_campaign'],
			'wc_order_attribution_session_entry' => home_url( '/produit/?utm_source=' . $utm['utm_source'] ),
			'wc_order_attribution_user_agent'    => 'Mozilla/5.0 (QA-L2 synthetic user agent)',
			'wc_order_attribution_device_type'   => 'Mobile',
		);
		$_COOKIE['famma_utm'] = wp_json_encode( $utm );
		$mark                 = count( self::$orders );
		$first                = self::endpoint( 'checkout', $classic );
		$second               = self::endpoint( 'checkout', $classic );
		unset( $_COOKIE['famma_utm'] );
		$classic_orders = self::orders_since( $mark );
		$classic_id     = (int) ( $classic_orders[0] ?? 0 );
		foreach ( $classic_orders as $id ) {
			self::run_queued( $id );
			do_action( 'famma_capi_orderplaced_send', $id );
		}
		$placed = 0;
		foreach ( $classic_orders as $id ) {
			$placed += count( self::graph( 'OrderPlaced', $id ) );
		}
		self::check(
			'T2.6',
			'classique-double-sequentiel-un-orderplaced',
			1 === count( $classic_orders ) && 1 === $placed,
			'1 commande, 1 OrderPlaced',
			array(
				'commandes'   => $classic_orders,
				'orderplaced' => $placed,
			)
		);
		self::$report['T2.6']['observations']['classique_sequentiel'] = array(
			'reponse_1' => self::reply_summary( $first ),
			'reponse_2' => self::reply_summary( $second ),
			'commandes' => $classic_orders,
		);

		// Formulaire express : double clic séquentiel (même famma_request_id).
		self::session();
		$prep                        = self::endpoint(
			'famma_express_prepare',
			array(
				'famma_product_id'     => $product->get_id(),
				'famma_quantity'       => '1',
				'famma_preparation_id' => wp_generate_uuid4(),
				'billing_state'        => 'TN-51',
				'billing_country'      => 'TN',
				'famma_hp_site'        => '',
			)
		);
		$request                     = wp_generate_uuid4();
		self::$express['requests'][] = $request;
		// Champs exactement comme express-order.js les envoie (FormData du formulaire + 3 champs).
		$fields                  = array(
			'billing_first_name'   => 'QA-L2 Express',
			'billing_phone'        => '20 000 012',
			'billing_state'        => 'TN-51',
			'famma_quantity'       => '1',
			'famma_hp_site'        => '',
			'payment_method'       => 'cod',
			'billing_country'      => 'TN',
			'famma_product_id'     => (string) $product->get_id(),
			'famma_request_id'     => $request,
			'famma_preparation_id' => (string) ( $prep['preparation_id'] ?? '' ),
			'famma_cart_hash'      => (string) ( $prep['cart_hash'] ?? '' ),
		);
		$_COOKIE['famma_utm']    = wp_json_encode( $utm );
		$_COOKIE['sbjs_current'] = 'typ=utm|||src=' . $utm['utm_source'] . '|||mdm=' . $utm['utm_medium'] . '|||cmp=' . $utm['utm_campaign'];
		$mark                    = count( self::$orders );
		$click1                  = self::endpoint( Famma\Core\Express_Checkout::ENDPOINT, $fields );
		$click2                  = self::endpoint( Famma\Core\Express_Checkout::ENDPOINT, $fields );
		unset( $_COOKIE['famma_utm'], $_COOKIE['sbjs_current'] );
		$express_orders = self::orders_since( $mark );
		$express_id     = (int) ( $express_orders[0] ?? 0 );
		$tasks          = $express_id ? self::pending( $express_id ) : 0;
		foreach ( $express_orders as $id ) {
			self::run_queued( $id );
			do_action( 'famma_capi_orderplaced_send', $id );
		}
		$express_placed = 0;
		foreach ( $express_orders as $id ) {
			$express_placed += count( self::graph( 'OrderPlaced', $id ) );
		}
		self::check(
			'T2.6',
			'express-double-clic-un-orderplaced',
			1 === count( $express_orders ) && 1 === $express_placed,
			'1 commande, 1 tâche, 1 OrderPlaced',
			array(
				'commandes'   => $express_orders,
				'taches'      => $tasks,
				'orderplaced' => $express_placed,
			)
		);
		self::$report['T2.6']['observations']['express_sequentiel'] = array(
			'preparation' => self::reply_summary( $prep ),
			'clic_1'      => self::reply_summary( $click1 ),
			'clic_2'      => self::reply_summary( $click2 ),
			'commandes'   => $express_orders,
			'remarque'    => 'La double soumission simultanée de l\'express est couverte par test-express-integration (concurrence, T2.1) ; celle du checkout classique par qa-l2-checkout-double.sh.',
		);

		// T2.10 — origine WooCommerce et UTM sur les deux parcours.
		self::$scenario = 'T2.10';
		$attribution    = static function ( int $id ): array {
			$o = self::fresh( $id );
			return array(
				'commande'                          => $id,
				'_wc_order_attribution_source_type' => $o->get_meta( '_wc_order_attribution_source_type' ),
				'_wc_order_attribution_utm_source'  => $o->get_meta( '_wc_order_attribution_utm_source' ),
				'_famma_utm_source'                 => $o->get_meta( '_famma_utm_source' ),
				'_famma_utm_campaign'               => $o->get_meta( '_famma_utm_campaign' ),
				'created_via'                       => $o->get_created_via(),
			);
		};
		$classic_attr   = $classic_id ? $attribution( $classic_id ) : array();
		$express_attr   = $express_id ? $attribution( $express_id ) : array();
		self::check( 'T2.10', 'temoin-classique-origine', 'utm' === ( $classic_attr['_wc_order_attribution_source_type'] ?? '' ), 'utm (témoin : champs postés par le formulaire classique)', $classic_attr );
		self::check( 'T2.10', 'express-origine-renseignee', '' !== (string) ( $express_attr['_wc_order_attribution_source_type'] ?? '' ), 'origine WooCommerce renseignée', $express_attr );
		self::$report['T2.10']['observations'] = array(
			'classique' => $classic_attr,
			'express'   => $express_attr,
			'remarque'  => 'Le formulaire express (FormData) ne contient aucun champ wc_order_attribution_* : WooCommerce ne lit que $_POST au crochet woocommerce_checkout_order_created, l\'origine reste donc vide (« Inconnu » dans l\'admin) même avec le cookie de session sbjs. Les UTM famma (_famma_utm_*) viennent du cookie famma_utm, indépendamment.',
		);
	}

	// ------------------------------------------------------------------
	// Modes checkout-* (double soumission parallèle par curl)
	// ------------------------------------------------------------------

	/** Create the shared product and save the run context. */
	private static function checkout_setup(): void {
		$product = self::product( false );
		update_option(
			'famma_qa_l2_ctx_' . self::$run,
			array(
				'product' => $product->get_id(),
				'started' => time(),
			),
			false
		);
		self::$report['checkout-setup'] = array(
			'product_id' => $product->get_id(),
			'started'    => time(),
		);
	}

	/**
	 * Load the run context into the cleanup lists.
	 *
	 * @return array<string,int>
	 * @throws RuntimeException When the run context is missing.
	 */
	private static function checkout_load(): array {
		$ctx = (array) get_option( 'famma_qa_l2_ctx_' . self::$run, array() );
		if ( empty( $ctx['product'] ) ) {
			throw new RuntimeException( 'Unknown run context.' );
		}
		self::$products['checkout'] = (int) $ctx['product'];
		$product                    = wc_get_product( (int) $ctx['product'] );
		if ( ! $product || self::$run !== $product->get_meta( '_famma_qa_l2_run' ) ) {
			throw new RuntimeException( 'Run product mismatch.' );
		}
		foreach ( self::orders_with_product( (int) $ctx['product'], (int) $ctx['started'] ) as $id ) {
			// Commandes passées par curl : marquées ici pour que le nettoyage les reconnaisse.
			$order = wc_get_order( $id );
			if ( $order instanceof WC_Order && '' === (string) $order->get_meta( '_famma_qa_l2_run' ) ) {
				$order->update_meta_data( '_famma_qa_l2_run', self::$run );
				$order->update_meta_data( '_famma_qa_l2_label', 'T2.6-parallele' );
				$order->save();
			}
			self::$orders[] = $id;
		}
		return $ctx;
	}

	/** Load the orders and products of a kept run, by their run marker. */
	private static function run_load(): void {
		$orders = wc_get_orders(
			array(
				'limit'      => 200,
				'status'     => array_keys( wc_get_order_statuses() ),
				'meta_key'   => '_famma_qa_l2_run', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- QA lookup by run marker on the disposable clone.
				'meta_value' => self::$run, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- QA lookup by run marker on the disposable clone.
				'return'     => 'ids',
			)
		);
		foreach ( $orders as $id ) {
			self::$orders[] = (int) $id;
		}
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => 10,
				'fields'         => 'ids',
				'meta_key'       => '_famma_qa_l2_run', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- QA lookup by run marker on the disposable clone.
				'meta_value'     => self::$run, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- QA lookup by run marker on the disposable clone.
			)
		);
		foreach ( $products as $index => $id ) {
			self::$products[ 'kept-' . $index ] = (int) $id;
		}
	}

	/** Count OrderPlaced for the orders created by the parallel double submission. */
	private static function checkout_verify(): void {
		self::$scenario = 'T2.6';
		self::checkout_load();
		$by_label = array();
		foreach ( self::$orders as $id ) {
			$o = self::fresh( $id );
			// Une manche = un numéro de téléphone (le nom de famille n'est pas gardé par le checkout famma).
			$label = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_phone() );
			$tasks = self::pending( $id );
			self::run_queued( $id );
			do_action( 'famma_capi_orderplaced_send', $id );
			$o                    = self::fresh( $id );
			$by_label[ $label ][] = array(
				'commande'             => $id,
				'statut'               => $o->get_status(),
				'created_via'          => $o->get_created_via(),
				'taches_en_attente'    => $tasks,
				'orderplaced_ici'      => count( self::graph( 'OrderPlaced', $id ) ),
				'marqueur_orderplaced' => (bool) $o->get_meta( '_famma_orderplaced_sent' ),
				'registre'             => self::registry( 'OrderPlaced', 'famma_orderplaced_' . $id ),
				'attribution'          => $o->get_meta( '_wc_order_attribution_source_type' ),
				'_famma_utm_source'    => $o->get_meta( '_famma_utm_source' ),
				'signaux'              => array(
					'fbp' => '' !== (string) $o->get_meta( '_famma_fbp' ),
					'fbc' => '' !== (string) $o->get_meta( '_famma_fbc' ),
					'ip'  => '' !== (string) $o->get_meta( '_famma_client_ip' ),
					'ua'  => '' !== (string) $o->get_meta( '_famma_client_ua' ),
				),
			);
		}
		foreach ( $by_label as $label => $orders ) {
			$events = 0;
			foreach ( $orders as $row ) {
				$events += $row['marqueur_orderplaced'] ? 1 : 0;
			}
			self::check(
				'T2.6',
				'double-soumission-' . sanitize_title( $label ),
				1 === count( $orders ) && 1 === $events,
				'1 commande, 1 OrderPlaced',
				array(
					'commandes'   => count( $orders ),
					'orderplaced' => $events,
				)
			);
		}
		self::$report['T2.6']['observations']['checkout_parallele'] = $by_label;
	}

	// ------------------------------------------------------------------
	// Outils
	// ------------------------------------------------------------------

	/**
	 * Intercept graph.facebook.com and block every other host.
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
		$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( 'graph.facebook.com' !== $host ) {
			return new WP_Error( 'famma_qa_l2_blocked', 'QA-L2: outgoing HTTP blocked.' );
		}
		$body  = json_decode( (string) ( $args['body'] ?? '' ), true );
		$body  = is_array( $body ) ? $body : array();
		$token = (string) ( $body['access_token'] ?? '' );
		unset( $body['access_token'] );
		$path     = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		$order_id = (int) ( $body['data'][0]['custom_data']['order_id'] ?? 0 );
		$cod      = null;
		$paid     = null;
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				$order->read_meta_data( true );
				$cod  = $order->get_meta( '_famma_cod_paid' );
				$paid = $order->get_date_paid() ? $order->get_date_paid()->date( 'c' ) : null;
			}
		}
		self::$requests[] = array(
			'scenario'          => self::$scenario,
			'simulation'        => self::$meta_mode,
			'url'               => '' !== $token ? str_replace( $token, '[jeton]', (string) $url ) : (string) $url,
			'path'              => $path,
			'graph_version'     => 1 === preg_match( '#^/(v\d+\.\d+)/#', $path, $m ) ? $m[1] : '',
			'method'            => (string) ( $args['method'] ?? '' ),
			'timeout'           => $args['timeout'] ?? null,
			'blocking'          => $args['blocking'] ?? null,
			'token_present'     => '' !== $token,
			'token_is_qa_fake'  => hash_equals( self::FAKE_TOKEN, $token ),
			'token_in_url'      => '' !== $token && str_contains( (string) $url, $token ),
			'cod_paid_at_send'  => $cod,
			'date_paid_at_send' => $paid,
			'body'              => $body,
		);
		return self::response();
	}

	/**
	 * Simulated Meta answer for the current mode.
	 *
	 * @return array|WP_Error
	 */
	private static function response() {
		switch ( self::$meta_mode ) {
			case 'wp_error':
				return new WP_Error( 'http_request_failed', 'QA-L2 simulation: connection refused.' );
			case 'timeout':
				return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 8000 milliseconds with 0 bytes received (QA-L2 simulation)' );
			case 'http500':
				return self::reply( 500, '{"error":{"message":"QA-L2 simulation: server error","type":"OAuthException","code":2,"is_transient":true,"fbtrace_id":"QA-L2"}}' );
			case 'no_events':
				return self::reply( 200, '{"fbtrace_id":"QA-L2"}' );
			case 'too_old':
				return self::too_old();
			default:
				// Règle documentée par Meta : event_time au plus 7 jours avant l'envoi.
				$last = end( self::$requests );
				$time = (int) ( is_array( $last ) ? ( $last['body']['data'][0]['event_time'] ?? time() ) : time() );
				if ( $time < time() - 7 * self::DAY ) {
					return self::too_old();
				}
				return self::reply( 200, '{"events_received":1,"messages":[],"fbtrace_id":"QA-L2"}' );
		}
	}

	/**
	 * Simulated rejection of an event older than 7 days.
	 *
	 * @return array
	 */
	private static function too_old(): array {
		return self::reply( 400, '{"error":{"message":"QA-L2 simulation: event_time older than 7 days","type":"OAuthException","code":100,"is_transient":false,"fbtrace_id":"QA-L2"}}' );
	}

	/**
	 * Build a WP HTTP API response array.
	 *
	 * @param int    $code HTTP status.
	 * @param string $body JSON body.
	 * @return array
	 */
	private static function reply( int $code, string $body ): array {
		return array(
			'headers'  => array(),
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
	 * Record whether a graph request body carries the token (never the value).
	 *
	 * @param array  $args Request arguments.
	 * @param string $url  Destination.
	 * @return array Unchanged arguments.
	 */
	public static function observe_args( $args, $url ) {
		if ( 'graph.facebook.com' === wp_parse_url( (string) $url, PHP_URL_HOST ) ) {
			self::$args_seen[] = array(
				'scenario'            => self::$scenario,
				'body_contient_jeton' => str_contains( (string) ( $args['body'] ?? '' ), self::FAKE_TOKEN ),
			);
		}
		return $args;
	}

	/**
	 * Keep famma-capi lines from the first log handler only.
	 *
	 * @param mixed  $message Message.
	 * @param string $level   Level.
	 * @param array  $context Context.
	 * @param object $handler Handler.
	 * @return mixed Unchanged message.
	 */
	public static function capture_log( $message, $level, $context, $handler ) {
		unset( $level );
		$name = is_object( $handler ) ? get_class( $handler ) : '';
		if ( '' === self::$log_handler ) {
			self::$log_handler = $name;
		}
		if ( $name === self::$log_handler && 'famma-capi' === ( $context['source'] ?? '' ) ) {
			self::$logs[] = (string) $message;
		}
		return $message;
	}

	/**
	 * Simulate a missing registry table in this process only.
	 *
	 * @param string $sql Query.
	 * @return string
	 */
	public static function query( $sql ) {
		if ( self::$missing_table && is_string( $sql ) ) {
			$table = \Famma\Core\Capi_Delivery_Registry::instance()->table();
			if ( str_contains( $sql, $table ) ) {
				$GLOBALS['wpdb']->suppress_errors( true );
				return str_replace( $table, $table . '_qa_l2_absente', $sql );
			}
		}
		return $sql;
	}

	/**
	 * Tag orders created through the real checkout endpoints.
	 *
	 * @param WC_Order $order New order.
	 */
	public static function mark_order( $order ): void {
		if ( $order instanceof WC_Order ) {
			$order->update_meta_data( '_famma_qa_l2_run', self::$run );
			$order->save();
			self::$orders[] = $order->get_id();
		}
	}

	/**
	 * QA-L2 product, hidden from the catalogue, no stock management.
	 *
	 * @param bool $virtual Virtual product.
	 * @return WC_Product
	 * @throws RuntimeException When the product cannot be created.
	 */
	private static function product( bool $virtual = true ): WC_Product {
		$key = $virtual ? 'virtual' : 'physical';
		if ( isset( self::$products[ $key ] ) ) {
			return wc_get_product( self::$products[ $key ] );
		}
		$product = new WC_Product_Simple();
		$product->set_name( 'QA-L2 Produit pixel ' . substr( self::$run, 0, 8 ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_regular_price( '49' );
		$product->set_sku( 'QA-L2-' . $key . '-' . substr( self::$run, 0, 8 ) );
		$product->set_manage_stock( false );
		$product->set_tax_status( 'none' );
		$product->set_virtual( $virtual );
		$product->update_meta_data( '_famma_qa_l2_run', self::$run );
		$product->save();
		if ( ! $product->get_id() ) {
			throw new RuntimeException( 'QA-L2 product creation failed.' );
		}
		self::$products[ $key ] = $product->get_id();
		return $product;
	}

	/**
	 * Real WooCommerce order with synthetic QA-L2 data.
	 *
	 * @param string $label   Scenario label (order meta).
	 * @param string $via     created_via.
	 * @param bool   $signals Store the checkout signals (fbp, fbc, IP, UA).
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
			throw new RuntimeException( 'QA-L2 order creation failed.' );
		}
		$order->set_payment_method( 'cod' );
		$order->set_currency( 'TND' );
		$order->set_billing_first_name( 'QA-L2' );
		$order->set_billing_last_name( 'Fixture' );
		$order->set_billing_email( 'qa-l2@example.invalid' );
		$order->set_billing_phone( self::PHONE );
		$order->set_billing_address_1( 'QA-L2 adresse fictive' );
		$order->set_billing_city( 'Sousse' );
		$order->set_billing_state( 'TN-51' );
		$order->set_billing_country( 'TN' );
		if ( $age > 0 ) {
			$order->set_date_created( time() - $age );
		}
		$order->add_product( self::product(), 1 );
		if ( $signals ) {
			$order->update_meta_data( '_famma_fbp', 'fb.1.1792400000000.1234567890' );
			$order->update_meta_data( '_famma_fbc', 'fb.1.1792400000000.QA-L2-fbclid' );
			$order->update_meta_data( '_famma_client_ip', '192.0.2.10' );
			$order->update_meta_data( '_famma_client_ua', 'Mozilla/5.0 (QA-L2 synthetic user agent)' );
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
	 * Report an accepted checkout to famma-core, as the COD gateway does.
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
	 * Pending OrderPlaced tasks of one order.
	 *
	 * @param int $id Order.
	 * @return int
	 */
	private static function pending( int $id ): int {
		return count(
			as_get_scheduled_actions(
				array(
					'hook'     => 'famma_capi_orderplaced_send',
					'group'    => 'famma-capi',
					'args'     => array( 'order_id' => $id ),
					'status'   => 'pending',
					'per_page' => 10,
				),
				'ids'
			)
		);
	}

	/**
	 * Run this order's pending OrderPlaced tasks through the real runner.
	 *
	 * @param int $id Order.
	 * @return array<string,mixed>
	 */
	private static function run_queued( int $id ): array {
		$ids = as_get_scheduled_actions(
			array(
				'hook'     => 'famma_capi_orderplaced_send',
				'group'    => 'famma-capi',
				'args'     => array( 'order_id' => $id ),
				'status'   => 'pending',
				'per_page' => 10,
			),
			'ids'
		);
		$out = array();
		foreach ( $ids as $action_id ) {
			ActionScheduler::runner()->process_action( (int) $action_id, 'QA-L2' );
			$out[ (int) $action_id ] = ActionScheduler::store()->get_status( (int) $action_id );
		}
		return array( 'taches_executees' => $out );
	}

	/**
	 * Is any retry scheduled for this order (Action Scheduler or WP-Cron)?
	 *
	 * @param int $id Order.
	 * @return array<string,mixed>
	 */
	private static function retry_scheduled( int $id ): array {
		$cron = array();
		foreach ( (array) _get_cron_array() as $hooks ) {
			foreach ( (array) $hooks as $hook => $events ) {
				if ( str_contains( (string) $hook, 'famma_capi' ) ) {
					foreach ( (array) $events as $event ) {
						if ( in_array( $id, array_map( 'intval', (array) $event['args'] ), true ) ) {
							$cron[] = $hook;
						}
					}
				}
			}
		}
		$as = count(
			as_get_scheduled_actions(
				array(
					'group'    => 'famma-capi',
					'args'     => array( 'order_id' => $id ),
					'status'   => 'pending',
					'per_page' => 10,
				),
				'ids'
			)
		);
		return array(
			'planifiee'        => $as > 0 || array() !== $cron,
			'action_scheduler' => $as,
			'wp_cron'          => $cron,
		);
	}

	/**
	 * Intercepted graph requests of one event for one order.
	 *
	 * @param string $event    Event name.
	 * @param int    $order_id Order.
	 * @return array<int,array>
	 */
	private static function graph( string $event, int $order_id ): array {
		return array_values(
			array_filter(
				self::$requests,
				static fn( array $r ): bool => ( ( $r['body']['data'][0]['event_name'] ?? '' ) === $event ) && ( (string) ( $r['body']['data'][0]['custom_data']['order_id'] ?? '' ) === (string) $order_id )
			)
		);
	}

	/**
	 * Journal lines of one event for one order, from an index.
	 *
	 * @param string $event    Event name.
	 * @param int    $order_id Order.
	 * @param int    $from     First index.
	 * @return array<int,string>
	 */
	private static function log_lines( string $event, int $order_id, int $from = 0 ): array {
		return array_values( array_filter( array_slice( self::$logs, $from ), static fn( string $line ): bool => str_starts_with( $line, $event . ' order=' . $order_id . ' ' ) ) );
	}

	/**
	 * Order notes, oldest first.
	 *
	 * @param int $id Order.
	 * @return array<int,string>
	 */
	private static function notes( int $id ): array {
		$notes = wc_get_order_notes(
			array(
				'order_id' => $id,
				'orderby'  => 'date_created_gmt',
				'order'    => 'ASC',
			)
		);
		return array_values( array_map( static fn( $n ): string => (string) $n->content, $notes ) );
	}

	/**
	 * Registry row (state, attempts) of one event.
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
			'attempts'      => json_decode( (string) $row['attempts'], true ),
		);
	}

	/**
	 * Record one observation.
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
	 * Is HPOS the authoritative order storage?
	 *
	 * @return bool
	 */
	private static function hpos(): bool {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Versions and storage.
	 *
	 * @return array<string,mixed>
	 */
	private static function environment(): array {
		return array(
			'wordpress'   => get_bloginfo( 'version' ),
			'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'famma_core'  => defined( 'FAMMA_CORE_VERSION' ) ? FAMMA_CORE_VERSION : '',
			'php'         => PHP_VERSION,
			'hpos'        => self::hpos(),
			'type'        => wp_get_environment_type(),
		);
	}

	/**
	 * Orders created through the real endpoints since a position of the run list.
	 *
	 * @param int $mark Position before the submissions.
	 * @return array<int,int>
	 */
	private static function orders_since( int $mark ): array {
		$out = array_values( array_unique( array_slice( self::$orders, $mark ) ) );
		sort( $out );
		return $out;
	}

	/**
	 * Orders created since a timestamp that contain a product.
	 *
	 * @param int $product_id Product.
	 * @param int $since      Timestamp.
	 * @return array<int,int>
	 */
	private static function orders_with_product( int $product_id, int $since ): array {
		$out    = array();
		$orders = wc_get_orders(
			array(
				'limit'        => 50,
				'date_created' => '>=' . ( $since - 5 ),
				'status'       => array_keys( wc_get_order_statuses() ),
			)
		);
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item instanceof WC_Order_Item_Product && $product_id === $item->get_product_id() ) {
					$out[] = $order->get_id();
					break;
				}
			}
		}
		sort( $out );
		return $out;
	}

	/**
	 * Short summary of a JSON endpoint reply (no nonce, no key).
	 *
	 * @param array $reply Decoded reply.
	 * @return array<string,mixed>
	 */
	private static function reply_summary( array $reply ): array {
		return array(
			'result'   => $reply['result'] ?? null,
			'order_id' => $reply['order_id'] ?? null,
			'messages' => isset( $reply['messages'] ) ? trim( wp_strip_all_tags( (string) $reply['messages'] ) ) : null,
		);
	}

	/**
	 * New guest WooCommerce session for the real endpoints (from test-express-integration).
	 */
	private static function session(): void {
		global $wp_filter;
		if ( WC()->session ) {
			WC()->session->save_data();
		}
		foreach ( $wp_filter as $hook => $callbacks ) {
			foreach ( $callbacks->callbacks as $priority => $registered ) {
				foreach ( $registered as $callback ) {
					$fn = $callback['function'];
					if ( is_array( $fn ) && is_object( $fn[0] ) && ( $fn[0] instanceof WC_Session_Handler || $fn[0] instanceof WC_Cart || $fn[0] instanceof WC_Cart_Session ) ) {
						remove_filter( $hook, $fn, $priority );
					}
				}
			}
		}
		wp_set_current_user( 0 );
		WC()->session = new WC_Session_Handler();
		WC()->session->init();
		WC()->customer = new WC_Customer( 0, true );
		WC()->cart     = new WC_Cart();
		WC()->session->set_customer_session_cookie( true );
		$values = array();
		foreach ( array( '_cookie', '_session_expiration', '_session_expiring' ) as $property ) {
			$reflection = new ReflectionProperty( WC()->session, $property );
			$reflection->setAccessible( true );
			$values[ $property ] = $reflection->getValue( WC()->session );
		}
		$hash = new ReflectionMethod( WC()->session, 'hash' );
		$hash->setAccessible( true );
		$value                         = WC()->session->get_customer_id() . '|' . $values['_session_expiration'] . '|' . $values['_session_expiring'] . '|' . $hash->invoke( WC()->session, WC()->session->get_customer_id() . '|' . $values['_session_expiration'] );
		$_COOKIE[ $values['_cookie'] ] = $value;
		self::$express['sessions'][]   = WC()->session->get_customer_id();
	}

	/**
	 * Call a real wc-ajax endpoint and decode its JSON (from test-express-integration).
	 *
	 * @param string $endpoint Endpoint without the wc_ajax_ prefix.
	 * @param array  $fields   POST fields.
	 * @return array
	 */
	private static function endpoint( string $endpoint, array $fields ): array {
		$_SERVER['REMOTE_ADDR']     = '192.0.2.20';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (QA-L2 synthetic user agent)';
		$_POST                      = array_merge( array( 'woocommerce-process-checkout-nonce' => wp_create_nonce( 'woocommerce-process_checkout' ) ), $fields );
		$_REQUEST                   = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- synthetic POST; the native endpoint verifies its nonce.
		wc_clear_notices();
		$level = ob_get_level();
		ob_start();
		try {
			do_action( 'wc_ajax_' . $endpoint ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound,WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- native WooCommerce AJAX contract.
		} catch ( Famma_Qa_L2_Stop $stop ) {
			unset( $stop );
		} finally {
			while ( ob_get_level() > $level + 1 ) {
				ob_end_flush();
			}
			$output = (string) ob_get_clean();
			self::request_end();
		}
		$result = json_decode( $output, true );
		return is_array( $result ) ? $result : array( 'raw_length' => strlen( $output ) );
	}

	/** Emulate the end of a real request (from test-express-integration). */
	private static function request_end(): void {
		global $wp_filter;
		$checkout = \Famma\Core\Express_Checkout::instance();
		if ( isset( $wp_filter['shutdown'] ) ) {
			foreach ( $wp_filter['shutdown']->callbacks as $priority => $callbacks ) {
				if ( $priority < 0 ) {
					foreach ( $callbacks as $callback ) {
						call_user_func( $callback['function'] );
					}
				}
			}
		}
		remove_filter( 'woocommerce_checkout_show_terms', array( $checkout, 'hide_terms' ), 99 );
		remove_filter( 'woocommerce_checkout_posted_data', array( $checkout, 'waive_terms' ), 99 );
		remove_filter( 'woocommerce_checkout_fields', array( $checkout, 'relax_address_fields' ), 99 );
		$fields = new ReflectionProperty( WC()->checkout(), 'fields' );
		$fields->setAccessible( true );
		$fields->setValue( WC()->checkout(), null );
	}

	/**
	 * Native AJAX termination becomes an Error outside WooCommerce's catch.
	 *
	 * @param callable $handler Native handler.
	 * @return callable
	 */
	public static function die_handler( $handler ): callable {
		unset( $handler );
		return static function (): void {
			throw new Famma_Qa_L2_Stop( 'Captured native JSON.' );
		};
	}

	/**
	 * Raise only the per-phone and per-IP ceilings for synthetic orders.
	 *
	 * @param array $limits Limits.
	 * @return array
	 */
	public static function test_limits( $limits ): array {
		$limits                         = (array) $limits;
		$limits['orders_per_phone_day'] = 100;
		$limits['orders_per_ip_hour']   = 100;
		return $limits;
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
			as_unschedule_all_actions( \Famma\Core\Order_Emails::ACTION, array( $id ), \Famma\Core\Order_Emails::GROUP );
			wp_clear_scheduled_hook( 'famma_capi_orderplaced_send', array( 'order_id' => $id ) );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- delete only this run's registry rows; orders use CRUD.
			$wpdb->delete( \Famma\Core\Capi_Delivery_Registry::instance()->table(), array( 'order_id' => $id ), array( '%d' ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$order->delete( true );
			++$done['commandes'];
		}
		foreach ( self::$products as $id ) {
			$product = wc_get_product( $id );
			if ( $product && self::$run === $product->get_meta( '_famma_qa_l2_run' ) ) {
				$product->delete( true );
				++$done['produits'];
			}
		}
		foreach ( (array) ( self::$express['requests'] ?? array() ) as $request ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- remove only this run's express request rows.
			$wpdb->delete( \Famma\Core\Express_Idempotency::instance()->table(), array( 'request_id' => $request ), array( '%s' ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}
		if ( WC()->session ) {
			foreach ( array_unique( (array) ( self::$express['sessions'] ?? array() ) ) as $session ) {
				WC()->session->delete_session( $session );
			}
		}
		delete_option( 'famma_qa_l2_ctx_' . self::$run );
		return $done;
	}
}

Famma_Qa_Pixel_L2::run();
