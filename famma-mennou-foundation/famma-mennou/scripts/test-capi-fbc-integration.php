<?php
/**
 * Scénarios fbc / fbp / IP / user agent (0.2.6) et bloc produit custom_data (0.2.7)
 * sur un clone WordPress jetable.
 * Lancer avec FAMMA_INTEGRATION_TEST=1, FAMMA_TEST_DB_NAME=famma_integration,
 * FAMMA_TEST_DB_HOST=db, puis wp eval-file /tests/test-capi-fbc-integration.php.
 * Tout appel HTTP est intercepté : rien ne part vers Meta.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- WP-CLI suite entry point, not an autoloaded plugin class file.

defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This suite requires WP-CLI and explicit isolated-clone opt-in.' );
}
if ( 'local' !== wp_get_environment_type() || 'famma_integration' !== (string) getenv( 'FAMMA_TEST_DB_NAME' ) || ! defined( 'DB_NAME' ) || 'famma_integration' !== DB_NAME || 'db' !== (string) getenv( 'FAMMA_TEST_DB_HOST' ) || ! defined( 'DB_HOST' ) || 'db' !== DB_HOST ) {
	throw new RuntimeException( 'Refusing writes: expected disposable local database identity is not explicitly confirmed.' );
}
$famma_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
if ( ! in_array( $famma_host, array( 'localhost', '127.0.0.1', '::1' ), true ) && ! str_ends_with( $famma_host, '.test' ) ) {
	throw new RuntimeException( 'Refusing writes: clone URL must be localhost, loopback or a .test host.' );
}
if ( ! class_exists( '\Famma\Core\Capi_Signals' ) || ! class_exists( '\Famma\Core\Capi_Product_Data' ) || ! class_exists( 'WC_Order' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
	throw new RuntimeException( 'Required active FAMMA 0.2.7/WooCommerce/Action Scheduler APIs are missing.' );
}
foreach ( array( 'FAMMA_META_ACCESS_TOKEN', 'FAMMA_META_PIXEL_ID', 'FAMMA_META_TEST_EVENT_CODE', 'FAMMA_META_CAPI_TEST_MODE' ) as $famma_constant ) {
	if ( defined( $famma_constant ) && '' !== (string) constant( $famma_constant ) ) {
		throw new RuntimeException( 'Clear tracking constants in this isolated clone first; values are not disclosed.' );
	}
}
if ( '' !== (string) get_option( 'famma_meta_access_token', '' ) ) {
	throw new RuntimeException( 'Clear the stored CAPI token in this clone first.' );
}

/** Scénarios fbc/fbp sur de vraies commandes WooCommerce. */
final class Famma_Capi_Fbc_Integration {
	private const FBC       = 'fb.1.1760000000000.IwAR_local-fixture_fbclid';
	private const FBP       = 'fb.1.1760000000000.1234567890';
	private const CLIENT_IP = '198.51.100.7';
	private const CLIENT_UA = 'Mozilla/5.0 (Linux; Android 14) FammaFixtureClient';
	/** @var array Requêtes interceptées. */
	private static array $requests = array();
	/** @var array Commandes créées. */
	private static array $orders = array();
	/** @var array Produits créés. */
	private static array $products = array();
	/** @var array Options d'origine. */
	private static array $options = array();
	/** @var array Résultats. */
	private static array $assertions = array();
	/** @var bool Simuler une panne réseau. */
	private static bool $network_down = false;
	/** @var string Marqueur de la série. */
	private static string $run_id = '';

	/** Exécute les scénarios puis nettoie. */
	public static function run(): void {
		self::$run_id = 'capi-fbc-' . wp_generate_uuid4();
		add_filter( 'pre_http_request', array( self::class, 'intercept' ), PHP_INT_MAX, 3 );
		add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
		$server_backup = $_SERVER;
		$cookie_backup = $_COOKIE;
		try {
			foreach ( array( 'famma_meta_pixel_id', 'famma_meta_access_token', 'famma_meta_test_event_code', 'famma_meta_orderplaced_enabled', 'famma_meta_capi_test_mode' ) as $name ) {
				self::$options[ $name ] = get_option( $name, null );
			}
			update_option( 'famma_meta_pixel_id', '123456789' );
			update_option( 'famma_meta_access_token', 'INTEGRATION_FAKE_TOKEN_NOT_A_SECRET', false );
			update_option( 'famma_meta_test_event_code', 'TEST_FAKE_INTEGRATION' );
			update_option( 'famma_meta_orderplaced_enabled', true );
			delete_option( 'famma_meta_capi_test_mode' );

			$s = '\Famma\Core\Capi_Signals';
			self::check( 'format-valid', self::FBC === $s::clean_cookie( self::FBC ) && self::FBP === $s::clean_cookie( self::FBP ), 'Valeurs fb.1.<ms>.<valeur> acceptées.' );
			self::check( 'format-appendix', '' !== $s::clean_cookie( self::FBC . '.AQYBAQAA' ), 'Appendice Meta Parameter Builder accepté.' );
			$bad = array( '', 'garbage', 'fb.1.abc.IwAR', 'fb.1.1760000000000.', 'fb.1.1760000000000.<script>', 'fb.1.1760000000000.a b', 'xx.1.1760000000000.IwAR' );
			self::check( 'format-invalid', array() === array_filter( array_map( array( $s, 'clean_cookie' ), $bad ) ), 'Valeurs hors format rejetées.' );
			self::check( 'ip-invalid', '' === $s::clean_ip( '1.2.3.999' ) && '2001:db8::1' === $s::clean_ip( '2001:db8::1' ), 'IP validée (IPv4/IPv6).' );
			self::check( 'script', str_contains( $s::fbc_bootstrap_script(), 'max-age=7776000' ) && str_contains( $s::fbc_bootstrap_script(), 'put("_fbc","fb.1."+Date.now()' ) && str_contains( $s::fbc_bootstrap_script(), 'put("_fbp","fb.1."+Date.now()+"."+(1e9+Math.floor(Math.random()*9e9))' ), 'Script : _fbc et _fbp au format fb.1.<ms>, 90 jours.' );
			$attrs = $s::instance()->no_optimize_attributes( array( 'id' => 'famma-fbc-js-after' ) );
			$other = $s::instance()->no_optimize_attributes( array( 'id' => 'famma-pixels-js-after' ) );
			self::check( 'no-optimize', '1' === ( $attrs['data-no-optimize'] ?? '' ) && '1' === ( $attrs['data-no-defer'] ?? '' ) && ! isset( $other['data-no-optimize'] ), 'Attributs anti-optimisation sur famma-fbc seulement.' );
			self::check( 'litespeed-exclude', in_array( 'famma-fbc', $s::instance()->litespeed_exclude( array( 'jquery' ) ), true ) && array( 'famma-fbc' ) === $s::instance()->litespeed_exclude( '' ), 'famma-fbc ajouté aux exclusions LiteSpeed.' );

			$product = new WC_Product_Simple();
			$product->set_name( '[TEST LOCAL] CAPI fbc fixture' );
			$product->set_status( 'private' );
			$product->set_regular_price( '77' );
			$product->set_virtual( true );
			$product->update_meta_data( '_famma_integration_run', self::$run_id );
			$product->save();
			self::$products[] = $product->get_id();
			$success = array(
				'result'   => 'success',
				'redirect' => home_url( '/integration-thanks/' ),
			);

			// 1. Commande client : cookies + IP + navigateur du client.
			$_COOKIE['_fbc']            = self::FBC;
			$_COOKIE['_fbp']            = self::FBP;
			$_SERVER['REMOTE_ADDR']     = self::CLIENT_IP;
			$_SERVER['HTTP_USER_AGENT'] = self::CLIENT_UA;
			$order                      = self::order( $product, true );
			self::check( 'meta-stored', self::FBC === $order->get_meta( '_famma_fbc' ) && self::FBP === $order->get_meta( '_famma_fbp' ) && self::CLIENT_IP === $order->get_meta( '_famma_client_ip' ) && self::CLIENT_UA === $order->get_meta( '_famma_client_ua' ), 'Signaux enregistrés en métadonnées à la commande.' );

			// 2. Cookie forgé : rien n'est enregistré pour lui.
			$_COOKIE['_fbc'] = 'fb.1.1760000000000.<img src=x>';
			$forged          = self::order( $product, true );
			self::check( 'meta-forged', '' === $forged->get_meta( '_famma_fbc' ) && self::FBP === $forged->get_meta( '_famma_fbp' ), 'Cookie _fbc forgé ignoré, _fbp valide gardé.' );

			// 3. OrderPlaced lit les métadonnées.
			apply_filters( 'woocommerce_payment_successful_result', $success, $order->get_id() );
			self::run_action( $order->get_id() );
			$placed = self::$requests[0]['payload']['data'][0] ?? array();
			$ud     = $placed['user_data'] ?? array();
			self::check( 'orderplaced-signals', self::FBC === ( $ud['fbc'] ?? '' ) && self::FBP === ( $ud['fbp'] ?? '' ) && self::CLIENT_IP === ( $ud['client_ip_address'] ?? '' ) && self::CLIENT_UA === ( $ud['client_user_agent'] ?? '' ), 'OrderPlaced : fbc, fbp, IP et UA du client.' );
			self::check( 'orderplaced-identity', 'OrderPlaced' === ( $placed['event_name'] ?? '' ) && 'famma_orderplaced_' . $order->get_id() === ( $placed['event_id'] ?? '' ) && 'website' === ( $placed['action_source'] ?? '' ), 'Nom, event_id et action_source inchangés.' );
			self::check( 'orderplaced-hashes', hash( 'sha256', 'synthetic@example.invalid' ) === ( $ud['em'] ?? '' ) && hash( 'sha256', '21620000000' ) === ( $ud['ph'] ?? '' ), 'Reste de user_data inchangé (em/ph hachés).' );
			self::check( 'no-test-code', ! isset( self::$requests[0]['payload']['test_event_code'] ), 'Mode test éteint : aucun test_event_code.' );
			// Recharge le produit : le slug n'est attribué qu'à l'enregistrement en base.
			$product = wc_get_product( $product->get_id() );
			$mono    = array(
				'content_ids'   => array( (string) $product->get_id() ),
				'content_name'  => $product->get_name(),
				'num_items'     => 1,
				'famma_product' => $product->get_slug(),
			);
			self::check( 'orderplaced-product-mono', self::product_data_matches( $placed, $mono ) && 'order_placed' === ( $placed['custom_data']['famma_stage'] ?? '' ), 'OrderPlaced mono-produit : content_ids, content_name, num_items, famma_product.' );

			// 4. Purchase depuis « l'admin » : autre IP, autre navigateur, autres cookies.
			$_COOKIE['_fbc']            = 'fb.1.1760000000999.ADMIN_SESSION_CLICK';
			$_COOKIE['_fbp']            = 'fb.1.1760000000999.999999';
			$_SERVER['REMOTE_ADDR']     = '203.0.113.250';
			$_SERVER['HTTP_USER_AGENT'] = 'AdminDesktopBrowser/1.0';
			wc_get_order( $order->get_id() )->update_status( 'delivered', 'Local integration: simulated delivery.' );
			$purchase = self::$requests[1]['payload']['data'][0] ?? array();
			$pud      = $purchase['user_data'] ?? array();
			self::check( 'purchase-signals', 'Purchase' === ( $purchase['event_name'] ?? '' ) && self::FBC === ( $pud['fbc'] ?? '' ) && self::FBP === ( $pud['fbp'] ?? '' ) && self::CLIENT_IP === ( $pud['client_ip_address'] ?? '' ) && self::CLIENT_UA === ( $pud['client_user_agent'] ?? '' ), 'Purchase : valeurs de la commande, jamais celles de l’admin.' );
			self::check( 'purchase-identity', 'famma_purchase_' . $order->get_id() === ( $purchase['event_id'] ?? '' ), 'event_id Purchase inchangé.' );
			self::check( 'purchase-product-mono', self::product_data_matches( $purchase, $mono ) && ! isset( $purchase['custom_data']['famma_stage'] ), 'Purchase mono-produit : même bloc produit que OrderPlaced.' );

			// 5. Commande antérieure à 0.2.6, sans métadonnées.
			$legacy = self::order( $product, false );
			wc_get_order( $legacy->get_id() )->update_status( 'delivered', 'Local integration: legacy order.' );
			$lud = self::$requests[2]['payload']['data'][0]['user_data'] ?? array( 'missing' => true );
			self::check( 'legacy-empty', ! isset( $lud['missing'] ) && ! array_intersect_key( $lud, array_flip( array( 'fbc', 'fbp', 'client_ip_address', 'client_user_agent' ) ) ) && (bool) wc_get_order( $legacy->get_id() )->get_meta( '_famma_purchase_sent' ), 'Sans métadonnées : champs omis, envoi normal.' );

			// 6. Commande antérieure avec un _famma_fbc mal formé.
			$dirty = self::order( $product, false );
			$dirty->update_meta_data( '_famma_fbc', 'not-a-click-id' );
			$dirty->update_meta_data( '_famma_client_ip', 'unknown' );
			$dirty->save();
			wc_get_order( $dirty->get_id() )->update_status( 'delivered', 'Local integration: dirty legacy order.' );
			$dud = self::$requests[3]['payload']['data'][0]['user_data'] ?? array( 'fbc' => 'missing' );
			self::check( 'legacy-dirty', ! isset( $dud['fbc'] ) && ! isset( $dud['client_ip_address'] ), 'Ancienne valeur invalide revalidée et omise.' );

			// 7. Mode test : option allumée → test_event_code joint.
			update_option( 'famma_meta_capi_test_mode', 1 );
			$test = self::order( $product, false );
			wc_get_order( $test->get_id() )->update_status( 'delivered', 'Local integration: test mode.' );
			self::check( 'test-mode-on', 'TEST_FAKE_INTEGRATION' === ( self::$requests[4]['payload']['test_event_code'] ?? '' ), 'Mode test : test_event_code joint.' );
			update_option( 'famma_meta_test_event_code', 'not-a-test-code' );
			$test2 = self::order( $product, false );
			wc_get_order( $test2->get_id() )->update_status( 'delivered', 'Local integration: invalid test code.' );
			self::check( 'test-mode-invalid', ! isset( self::$requests[5]['payload']['test_event_code'] ), 'Code hors format TEST… : rien joint.' );
			delete_option( 'famma_meta_capi_test_mode' );

			// 8. Panne réseau : la commande passe quand même.
			self::$network_down = true;
			$down               = self::order( $product, true );
			$result             = apply_filters( 'woocommerce_payment_successful_result', $success, $down->get_id() );
			self::run_action( $down->get_id() );
			wc_get_order( $down->get_id() )->update_status( 'delivered', 'Local integration: network down.' );
			$after = wc_get_order( $down->get_id() );
			self::check( 'non-blocking', $result === $success && $after->has_status( 'delivered' ) && ! $after->get_meta( '_famma_purchase_sent' ), 'Échec Meta : aucune exception, commande livrée normalement.' );
			self::$network_down = false;

			// 9. Multi-produit : SKU repris, quantités sommées, « | » neutralisé.
			$second = new WC_Product_Simple();
			$second->set_name( '[TEST LOCAL] Lame | inox' );
			$second->set_sku( 'FAMMA-LOCAL-' . substr( md5( self::$run_id ), 0, 8 ) );
			$second->set_status( 'private' );
			$second->set_regular_price( '20' );
			$second->set_virtual( true );
			$second->update_meta_data( '_famma_integration_run', self::$run_id );
			$second->save();
			$second = wc_get_product( $second->get_id() ); // Slug attribué à l'enregistrement.
			self::$products[] = $second->get_id();
			$multi            = self::order( $product, false, array( array( $second, 2 ) ) );
			wc_get_order( $multi->get_id() )->update_status( 'delivered', 'Local integration: multi-product.' );
			$mp = self::last_event();
			self::check(
				'purchase-product-multi',
				self::product_data_matches(
					$mp,
					array(
						'content_ids'   => array( (string) $product->get_id(), $second->get_sku() ),
						'content_name'  => $product->get_name() . ' | [TEST LOCAL] Lame / inox',
						'num_items'     => 3,
						'famma_product' => $product->get_slug() . ',' . $second->get_slug(),
					)
				),
				'Multi-produit : deux ids (ID puis SKU), noms joints par « | », 3 articles, deux slugs.'
			);

			// 10. Variations S et M d'un même produit : un seul slug, celui du parent.
			$taille = new WC_Product_Attribute();
			$taille->set_name( 'Taille' );
			$taille->set_options( array( 'S', 'M' ) );
			$taille->set_visible( true );
			$taille->set_variation( true );
			$variable = new WC_Product_Variable();
			$variable->set_name( '[TEST LOCAL] CAPI variable fixture' );
			$variable->set_status( 'private' );
			$variable->set_attributes( array( $taille ) );
			$variable->update_meta_data( '_famma_integration_run', self::$run_id );
			$variable->save();
			$variable = wc_get_product( $variable->get_id() ); // Slug attribué à l'enregistrement.
			$variations = array();
			foreach ( array( 'S', 'M' ) as $size ) {
				$variation = new WC_Product_Variation();
				$variation->set_parent_id( $variable->get_id() );
				$variation->set_attributes( array( 'taille' => $size ) );
				$variation->set_regular_price( '49' );
				$variation->set_virtual( true );
				$variation->update_meta_data( '_famma_integration_run', self::$run_id );
				$variation->save();
				self::$products[] = $variation->get_id();
				$variations[]     = wc_get_product( $variation->get_id() );
			}
			self::$products[] = $variable->get_id();
			$variable         = wc_get_product( $variable->get_id() );
			$varorder         = self::order( $variations[0], true, array( array( $variations[1], 1 ) ) );
			apply_filters( 'woocommerce_payment_successful_result', $success, $varorder->get_id() );
			self::run_action( $varorder->get_id() );
			$vp       = self::last_event();
			$var_data = array(
				'content_ids'   => array( (string) $variations[0]->get_id(), (string) $variations[1]->get_id() ),
				'num_items'     => 2,
				'famma_product' => $variable->get_slug(),
			);
			self::check( 'orderplaced-product-variation', 'OrderPlaced' === ( $vp['event_name'] ?? '' ) && self::product_data_matches( $vp, $var_data ) && 2 === count( explode( ' | ', (string) ( $vp['custom_data']['content_name'] ?? '' ) ) ) && $variable->get_slug() !== $variations[0]->get_slug(), 'Variations : ids des variations, slug du parent une seule fois.' );
			wc_get_order( $varorder->get_id() )->update_status( 'delivered', 'Local integration: variation.' );
			$vpur = self::last_event();
			self::check( 'purchase-product-variation', 'Purchase' === ( $vpur['event_name'] ?? '' ) && self::product_data_matches( $vpur, $var_data ) && ( $vp['custom_data']['content_name'] ?? '' ) === ( $vpur['custom_data']['content_name'] ?? null ), 'Purchase variation : même bloc produit que OrderPlaced.' );
		} finally {
			$_SERVER = $server_backup;
			$_COOKIE = $cookie_backup;
			self::cleanup();
		}
		WP_CLI::line(
			wp_json_encode(
				array(
					'suite'      => 'capi-fbc-fbp-product-0.2.7',
					'plugin'     => FAMMA_CORE_VERSION,
					'transport'  => 'intercepted-no-meta-network',
					'assertions' => self::$assertions,
					'requests'   => count( self::$requests ),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
			)
		);
	}

	/**
	 * Intercepte tout HTTP.
	 *
	 * @param mixed  $preempt Réponse existante.
	 * @param array  $args    Arguments.
	 * @param string $url     Destination.
	 * @return array|WP_Error
	 */
	public static function intercept( $preempt, array $args, string $url ) {
		if ( 'graph.facebook.com' === wp_parse_url( $url, PHP_URL_HOST ) ) {
			if ( self::$network_down ) {
				return new WP_Error( 'http_request_failed', 'Simulated network failure.' );
			}
			self::$requests[] = array(
				'url'     => $url,
				'payload' => json_decode( (string) ( $args['body'] ?? '' ), true ),
			);
			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => '{"events_received":1}',
				'headers'  => array(),
				'cookies'  => array(),
			);
		}
		return new WP_Error( 'famma_integration_network_blocked', 'External HTTP blocked.' );
	}

	/**
	 * Crée une vraie commande ; en passant par le hook de checkout si demandé.
	 *
	 * @param WC_Product $product Produit fixture.
	 * @param bool       $stamp   Appliquer woocommerce_checkout_create_order.
	 * @param array      $extra   Lignes supplémentaires : liste de array( WC_Product, quantité ).
	 * @return WC_Order
	 */
	private static function order( WC_Product $product, bool $stamp, array $extra = array() ): WC_Order {
		$order = wc_create_order(
			array(
				'created_via' => 'checkout',
				'status'      => 'pending-confirm',
			)
		);
		self::$orders[] = $order->get_id();
		$order->update_meta_data( '_famma_integration_run', self::$run_id );
		$order->set_payment_method( 'cod' );
		$order->set_billing_email( 'synthetic@example.invalid' );
		$order->set_billing_phone( '+216 20 000 000' );
		$order->set_billing_first_name( 'Local integration' );
		$order->set_billing_last_name( 'Synthetic' );
		$order->set_billing_country( 'TN' );
		$order->add_product( $product, 1 );
		foreach ( $extra as $line ) {
			$order->add_product( $line[0], (int) $line[1] );
		}
		$order->calculate_totals();
		if ( $stamp ) {
			\Famma\Core\Tracking::instance()->stamp_capi_signals( $order );
		}
		$order->save();
		return wc_get_order( $order->get_id() );
	}

	/**
	 * Exécute l'unique tâche OrderPlaced en attente.
	 *
	 * @param int $order_id Commande.
	 */
	private static function run_action( int $order_id ): void {
		$ids = as_get_scheduled_actions(
			array(
				'hook'     => 'famma_capi_orderplaced_send',
				'args'     => array( 'order_id' => $order_id ),
				'group'    => 'famma-capi',
				'status'   => 'pending',
				'per_page' => 100,
			),
			'ids'
		);
		if ( 1 !== count( $ids ) ) {
			throw new RuntimeException( 'Expected exactly one pending scheduler action.' );
		}
		ActionScheduler_QueueRunner::instance()->process_action( (int) $ids[0], 'famma-local-integration' );
	}

	/**
	 * Dernier événement Meta intercepté.
	 *
	 * @return array
	 */
	private static function last_event(): array {
		$last = end( self::$requests );
		return is_array( $last ) ? (array) ( $last['payload']['data'][0] ?? array() ) : array();
	}

	/**
	 * Compare le bloc produit (0.2.7) d'un événement intercepté aux valeurs attendues.
	 *
	 * Vérifie aussi que content_ids reprend exactement contents[].id, sans doublon.
	 *
	 * @param array $event    Événement Meta décodé.
	 * @param array $expected Champs attendus parmi content_ids, content_name, num_items, famma_product.
	 * @return bool
	 */
	private static function product_data_matches( array $event, array $expected ): bool {
		$cd = $event['custom_data'] ?? array();
		if ( ! is_array( $cd ) || ! isset( $cd['contents'], $cd['content_ids'] ) ) {
			return false;
		}
		if ( array_values( array_unique( array_column( $cd['contents'], 'id' ) ) ) !== $cd['content_ids'] ) {
			return false;
		}
		foreach ( $expected as $key => $value ) {
			if ( ! array_key_exists( $key, $cd ) || $value !== $cd[ $key ] ) {
				return false;
			}
		}
		return 'product' === ( $cd['content_type'] ?? '' );
	}

	/**
	 * Enregistre une assertion.
	 *
	 * @param string $name    Identifiant.
	 * @param bool   $ok      Résultat.
	 * @param string $message Diagnostic.
	 */
	private static function check( string $name, bool $ok, string $message ): void {
		self::$assertions[] = array(
			'name'    => $name,
			'passed'  => $ok,
			'message' => $message,
		);
		if ( ! $ok ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain WP-CLI diagnostic.
			throw new RuntimeException( $name . ': ' . $message . ' ' . wp_json_encode( self::$requests ) );
		}
	}

	/** Supprime les fixtures et restaure les options. */
	private static function cleanup(): void {
		global $wpdb;
		foreach ( self::$orders as $id ) {
			as_unschedule_all_actions( 'famma_capi_orderplaced_send', array( 'order_id' => $id ), 'famma-capi' );
			$order = wc_get_order( $id );
			if ( $order && self::$run_id === $order->get_meta( '_famma_integration_run' ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Suite-owned registry rows only.
				$wpdb->delete( \Famma\Core\Capi_Delivery_Registry::instance()->table(), array( 'order_id' => $id ), array( '%d' ) );
				$order->delete( true );
			}
		}
		foreach ( self::$products as $id ) {
			$product = wc_get_product( $id );
			if ( $product && self::$run_id === $product->get_meta( '_famma_integration_run' ) ) {
				$product->delete( true );
			}
		}
		foreach ( self::$options as $name => $value ) {
			if ( null === $value ) {
				delete_option( $name );
			} else {
				update_option( $name, $value );
			}
		}
		remove_filter( 'pre_http_request', array( self::class, 'intercept' ), PHP_INT_MAX );
		remove_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
	}
}

Famma_Capi_Fbc_Integration::run();
