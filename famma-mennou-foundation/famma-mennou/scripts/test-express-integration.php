<?php
/**
 * Real express transaction tests, including separate-process workers.
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile -- Harness and its private AJAX termination signal share the standalone runner.
defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_EXPRESS_INTEGRATION' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'Explicit isolated WP-CLI express test opt-in required.' );
}
$famma_express_db = (string) getenv( 'FAMMA_TEST_DB_NAME' );
if ( 'famma_integration' !== $famma_express_db || ! defined( 'DB_NAME' ) || ! hash_equals( $famma_express_db, (string) DB_NAME ) || 'db' !== DB_HOST ) {
	throw new RuntimeException( 'Refusing writes outside the explicitly named integration database and host.' );
}
$famma_express_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
if ( ! in_array( $famma_express_host, array( 'localhost', '127.0.0.1', '::1' ), true ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Only the local loopback clone is permitted.' );
}
foreach ( array( 'FAMMA_META_PIXEL_ID', 'FAMMA_META_ACCESS_TOKEN', 'FAMMA_META_TEST_EVENT_CODE' ) as $famma_express_constant ) {
	if ( defined( $famma_express_constant ) && '' !== (string) constant( $famma_express_constant ) ) {
		throw new RuntimeException( 'Tracking constants must be empty in the clone.' );
	}
}
if ( '' !== (string) get_option( 'famma_meta_access_token', '' ) || ! class_exists( '\Famma\Core\Express_Idempotency' ) ) {
	throw new RuntimeException( 'A credential-free clone with the new backend is required.' );
}

/** Signals native wp_die without skipping fixture cleanup. */
final class Famma_Express_Test_Stop extends Error {}

/** Owns only marked clone fixtures and neutralizes external transports. */
final class Famma_Express_Test {
	/**
	 * Fixture state, persisted only in the disposable test database.
	 *
	 * @var array
	 */
	private static array $context = array();
	/**
	 * Original tracking and checkout options.
	 *
	 * @var array
	 */
	private static array $options = array();
	/**
	 * Real endpoint observations.
	 *
	 * @var array
	 */
	private static array $results = array();
	/**
	 * HTTP bodies retained only in process memory.
	 *
	 * @var array
	 */
	private static array $events = array();
	/**
	 * Test counter identifiers to clean.
	 *
	 * @var array
	 */
	private static array $transients = array();
	/**
	 * Fixture run marker.
	 *
	 * @var string
	 */
	private static string $run = '';
	/**
	 * Current worker role.
	 *
	 * @var string
	 */
	private static string $role = '';
	/**
	 * Fixture counter for unique synthetic phone numbers.
	 *
	 * @var int
	 */
	private static int $scenario = 0;
	/**
	 * Captured mail attempts.
	 *
	 * @var int
	 */
	private static int $mail = 0;
	/**
	 * Current clock observation.
	 *
	 * @var float
	 */
	private static float $reserved_at = 0;

	/**
	 * Dispatch one coordinated test phase.
	 *
	 * @throws RuntimeException When opt-in, fixture state or an assertion fails.
	 */
	public static function run(): void {
		self::$run = (string) getenv( 'FAMMA_EXPRESS_TEST_RUN' );
		if ( ! preg_match( '/^[a-f0-9-]{36}$/', self::$run ) ) {
			throw new RuntimeException( 'A lowercase UUID run marker is required.' );
		}
		self::$role = (string) getenv( 'FAMMA_EXPRESS_TEST_ROLE' );
		$mode       = (string) getenv( 'FAMMA_EXPRESS_TEST_MODE' );
		add_filter( 'pre_http_request', array( self::class, 'http' ), PHP_INT_MAX, 3 );
		add_filter( 'pre_wp_mail', array( self::class, 'mail' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_die_ajax_handler', array( self::class, 'die_handler' ), PHP_INT_MAX );
		add_filter( 'woocommerce_available_payment_gateways', array( self::class, 'cod_only' ), PHP_INT_MAX );
		// Process-scoped fake configuration avoids exposing a temporary token to peer workers.
		add_filter( 'pre_option_famma_meta_pixel_id', static fn(): string => '123456789', PHP_INT_MAX );
		add_filter( 'pre_option_famma_meta_access_token', static fn(): string => 'INTEGRATION_FAKE_TOKEN_NOT_A_SECRET', PHP_INT_MAX );
		add_filter( 'pre_option_famma_meta_orderplaced_enabled', '__return_true', PHP_INT_MAX );
		add_action( 'woocommerce_checkout_order_created', array( self::class, 'capture_order' ), PHP_INT_MAX );
		add_action( 'set_transient', array( self::class, 'capture_transient' ), PHP_INT_MAX, 3 );
		wc_maybe_define_constant( 'DOING_AJAX', true );
		if ( ! wp_doing_ajax() ) {
			throw new RuntimeException( 'AJAX interception cannot be guaranteed.' ); }
		\Famma\Core\Express_Idempotency::instance()->install();
		if ( 'sequential' === $mode || 'setup' === $mode || 'setup-crash' === $mode ) {
			self::fixtures();
			if ( 'sequential' === $mode ) {
				try {
					self::sequential();
				} finally {
					self::cleanup(); }
			} else {
				$prepared                  = self::prepare();
				self::$context['prepared'] = $prepared;
				self::$context['request']  = wp_generate_uuid4();
				self::$context['post']     = self::commit_fields( $prepared, self::$context['request'] );
				self::save_context();
				self::$results[] = array(
					'name'   => 'worker-fixture-ready',
					'passed' => true,
					'run'    => self::$run,
				);
			}
		} else {
			self::$context = (array) get_option( self::context_name(), array() );
			if ( ( self::$context['run'] ?? '' ) !== self::$run ) {
				throw new RuntimeException( 'Exact fixture ownership context missing.' ); }
			self::session( self::$context['cookie'] );
			if ( 'worker' === $mode ) {
				self::worker();
			} elseif ( 'crash' === $mode || 'crash-accepted' === $mode ) {
				$crash_hook = 'crash' === $mode ? 'famma_express_order_linked' : 'famma_express_accepted';
				add_action( $crash_hook, array( self::class, 'crash' ), PHP_INT_MAX, 2 );
				self::endpoint( 'famma_express_checkout', self::$context['post'] );
				throw new RuntimeException( 'Expected crash injection point was not reached.' );
			} elseif ( 'resume' === $mode || 'resume-accepted' === $mode ) {
				$response = self::endpoint( 'famma_express_checkout', self::$context['post'] );
				$expected = 'resume' === $mode ? 'processing' : 'success';
				self::assert( 'crash-recovery', ( $response['result'] ?? '' ) === $expected, 'Linked-before-payment remains processing; accepted-before-response resolves success.' );
				self::assert( 'crash-one-order', 1 === count( self::request_orders( self::$context['request'] ) ), 'Crash recovery retained exactly one real order.' );
				self::$results[] = array(
					'name'     => 'crash-recovery-response',
					'response' => $response,
				);
			} elseif ( 'verify' === $mode ) {
				self::verify_workers();
			} elseif ( 'cleanup' === $mode ) {
				self::cleanup();
				self::$results[] = array(
					'name'   => 'cleanup',
					'passed' => true,
				);
			} else {
				throw new RuntimeException( 'Unknown isolated phase.' ); }
		}
		WP_CLI::line(
			wp_json_encode(
				array(
					'suite'            => 'express-transaction-integration',
					'mode'             => $mode,
					'run'              => self::$run,
					'wordpress'        => get_bloginfo( 'version' ),
					'woocommerce'      => WC_VERSION,
					'results'          => self::$results,
					'mail_intercepted' => self::$mail,
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
			)
		);
	}

	/**
	 * Prepare real products and preserve options.
	 *
	 * @throws RuntimeException When existing test context would be overwritten.
	 */
	private static function fixtures(): void {
		if ( get_option( self::context_name(), false ) ) {
			throw new RuntimeException( 'Run context already exists; clean it explicitly first.' ); }
		self::$context = array(
			'run'      => self::$run,
			'products' => array(),
			'requests' => array(),
			'sessions' => array(),
		);
		foreach ( array( 'woocommerce_currency', 'woocommerce_default_country', 'woocommerce_enable_guest_checkout', 'woocommerce_cod_settings', 'famma_meta_orderplaced_enabled', 'famma_meta_pixel_id', 'famma_meta_access_token', 'famma_meta_test_event_code' ) as $name ) {
			self::$options[ $name ] = get_option( $name, null );
		}
		self::$context['options'] = self::$options;
		update_option( 'woocommerce_currency', 'TND' );
		update_option( 'woocommerce_default_country', 'TN' );
		update_option( 'woocommerce_enable_guest_checkout', 'yes' );
		update_option( 'famma_meta_orderplaced_enabled', false );
		$cod                       = (array) get_option( 'woocommerce_cod_settings', array() );
		$cod['enabled']            = 'yes';
		$cod['enable_for_virtual'] = 'yes';
		$cod['enable_for_methods'] = array();
		update_option( 'woocommerce_cod_settings', $cod );
		foreach ( array( 79, 49 ) as $price ) {
			$product = new WC_Product_Simple();
			$product->set_name( '[TEST LOCAL] Express transaction ' . $price );
			$product->set_status( 'publish' );
			$product->set_virtual( true );
			$product->set_tax_status( 'none' );
			$product->set_regular_price( (string) $price );
			$product->update_meta_data( '_famma_integration_run', self::$run );
			$product->save();
			self::$context['products'][] = $product->get_id();
		}
		self::session();
		self::save_context();
	}

	/**
	 * Initialize an actual guest cookie/session across PHP processes.
	 *
	 * @param array $cookie Saved synthetic cookie fields, never logged.
	 */
	private static function session( array $cookie = array() ): void {
		global $wp_filter;
		if ( WC()->session ) {
			WC()->session->save_data(); }
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
		if ( $cookie ) {
			$_COOKIE[ $cookie['name'] ] = $cookie['value']; }
		WC()->session = new WC_Session_Handler();
		WC()->session->init();
		WC()->customer = new WC_Customer( 0, true );
		WC()->cart     = new WC_Cart();
		$cart_session  = new ReflectionProperty( WC()->cart, 'session' );
		$cart_session->setAccessible( true );
		$cart_session->getValue( WC()->cart )->get_cart_from_session();
		if ( ! $cookie ) {
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
			self::$context['cookie']       = array(
				'name'  => $values['_cookie'],
				'value' => $value,
			);
			self::$context['sessions'][]   = WC()->session->get_customer_id();
			$_COOKIE[ $values['_cookie'] ] = $value;
		}
	}

	/**
	 * Prepare full cart recap, retaining a genuine other-product line.
	 *
	 * @return array Accepted backend preparation.
	 * @throws RuntimeException When preparation fails.
	 */
	private static function prepare(): array {
		++self::$scenario;
		WC()->cart->empty_cart();
		WC()->session->set( 'order_awaiting_payment', false );
		WC()->cart->add_to_cart( self::$context['products'][0], 1 );
		WC()->cart->add_to_cart( self::$context['products'][1], 1 );
		WC()->cart->calculate_totals();
		$prep         = self::endpoint(
			'famma_express_prepare',
			array(
				'famma_product_id'     => self::$context['products'][0],
				'famma_quantity'       => '2',
				'famma_preparation_id' => wp_generate_uuid4(),
				'billing_state'        => 'TN-61',
				'billing_country'      => 'TN',
				'famma_hp_site'        => '',
			)
		);
		$prepare_name = 'prepare-success-' . self::$scenario;
		$prepare_ok   = 'success' === ( $prep['result'] ?? '' );
		self::assert( $prepare_name, $prepare_ok, 'Real prepare endpoint accepted.' );
		WC()->session->save_data();
		return $prep;
	}

	/**
	 * Assemble an intention's immutable synthetic charge.
	 *
	 * @param array  $prep Accepted preparation.
	 * @param string $request Stable request UUID.
	 * @return array Commit POST fields.
	 */
	private static function commit_fields( array $prep, string $request ): array {
		self::$context['requests'][] = $request;
		return array(
			'famma_product_id'          => self::$context['products'][0],
			'famma_quantity'            => '2',
			'famma_preparation_id'      => $prep['preparation_id'],
			'famma_cart_hash'           => $prep['cart_hash'],
			'famma_request_id'          => $request,
			'billing_first_name'        => 'Synthetic Ben Test',
			'billing_phone'             => '20' . sprintf( '%06d', self::$scenario + 100 ),
			'billing_state'             => 'TN-61',
			'billing_country'           => 'TN',
			'billing_address_1'         => '[TEST LOCAL] address',
			'billing_city'              => '',
			'billing_email'             => '',
			'payment_method'            => 'cod',
			'terms'                     => '1',
			'terms-field'               => '1',
			'ship_to_different_address' => '0',
			'famma_hp_site'             => '',
		);
	}

	/**
	 * Invoke a real AJAX hook and capture its native JSON response.
	 *
	 * @param string $endpoint Native action name without wc_ajax prefix.
	 * @param array  $fields Synthetic POST fields.
	 * @return array Decoded native response.
	 * @throws RuntimeException When response decoding fails.
	 */
	private static function endpoint( string $endpoint, array $fields ): array {
		$_SERVER['REMOTE_ADDR']     = '192.0.2.' . ( self::$scenario + 30 );
		$_SERVER['HTTP_USER_AGENT'] = 'FAMMA isolated express integration';
		$_POST                      = array_merge( array( 'woocommerce-process-checkout-nonce' => wp_create_nonce( 'woocommerce-process_checkout' ) ), $fields );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Synthetic POST only; native endpoint verifies its nonce.
		$_REQUEST = $_POST;
		wc_clear_notices();
		ob_start();
		try {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Exercise the native WooCommerce AJAX contract.
			do_action( 'wc_ajax_' . $endpoint );
		} catch ( Famma_Express_Test_Stop $stop ) {
			$stopped = true;
		} finally {
			$output = ob_get_clean(); }
		$result = json_decode( (string) $output, true );
		if ( ! isset( $stopped ) || ! is_array( $result ) ) {
			throw new RuntimeException( 'Native endpoint did not yield isolated JSON.' ); }
		return $result;
	}

	/**
	 * Execute sequential transaction and recap assertions.
	 *
	 * @throws RuntimeException When a backend contract assertion fails.
	 */
	private static function sequential(): void {
		$tasks_before = self::pending_capi_count();
		$prep         = self::prepare();
		self::assert( 'prepare-no-order', 0 === count( self::fixture_orders() ), 'Preparation creates no order.' );
		self::assert( 'prepare-no-capi', 0 === count( self::$events ), 'Preparation creates no CAPI conversion.' );
		self::assert( 'prepare-no-async-conversion', self::pending_capi_count() === $tasks_before, 'Preparation does not enqueue OrderPlaced even when tracking is enabled.' );
		self::assert( 'recap-total-207', 207.0 === (float) ( $prep['summary']['total'] ?? 0 ) && 'TND' === ( $prep['summary']['currency'] ?? '' ) && 2 === count( $prep['summary']['items'] ?? array() ), 'Backend recap includes both genuine lines and full 207 TND total.' );
		self::$results[]   = array(
			'name'    => 'recap-contract',
			'summary' => $prep['summary'] ?? array(),
		);
		$cart_before_nonce = WC()->cart->get_cart_hash();
		$bad_prepare       = self::endpoint(
			'famma_express_prepare',
			array(
				'famma_product_id'                   => self::$context['products'][0],
				'famma_quantity'                     => '99',
				'famma_preparation_id'               => wp_generate_uuid4(),
				'woocommerce-process-checkout-nonce' => 'expired',
			)
		);
		self::assert( 'prepare-bad-nonce-no-cart-change', 'failure' === ( $bad_prepare['result'] ?? '' ) && WC()->cart->get_cart_hash() === $cart_before_nonce && 0 === count( self::fixture_orders() ), 'Invalid prepare nonce causes no cart mutation or order.' );
		$post  = self::commit_fields( $prep, wp_generate_uuid4() );
		$first = self::endpoint( 'famma_express_checkout', $post );
		$retry = self::endpoint( 'famma_express_checkout', $post );
		self::assert( 'same-key-one-order', 'success' === ( $first['result'] ?? '' ) && ( $first['order_id'] ?? 0 ) === ( $retry['order_id'] ?? -1 ) && 1 === count( self::request_orders( $post['famma_request_id'] ) ), 'Same key/charge returns the identical single real order.' );
		$order = wc_get_order( $first['order_id'] );
		self::conversion_assertions( $order );
		self::assert( 'real-order-207', 207.0 === (float) $order->get_total() && 2 === count( $order->get_items() ), 'Accepted full cart has two real lines and total 207 TND.' );
		$quantities = array();
		foreach ( $order->get_items() as $item ) {
			$quantities[ $item->get_product_id() ] = $item->get_quantity(); }
		self::assert( 'real-order-exact-lines', 2 === ( $quantities[ self::$context['products'][0] ] ?? 0 ) && 1 === ( $quantities[ self::$context['products'][1] ] ?? 0 ), 'Primary quantity two; other-product quantity one.' );
		$changed                       = $post;
		$changed['billing_first_name'] = 'Different Synthetic';
		$conflict                      = self::endpoint( 'famma_express_checkout', $changed );
		self::assert( 'same-key-payload-conflict', 'intent_conflict' === ( $conflict['famma_code'] ?? '' ) && 1 === count( self::request_orders( $post['famma_request_id'] ) ), 'Changed charge cannot reuse the key.' );
		$other_prep = self::prepare();
		$other_post = self::commit_fields( $other_prep, wp_generate_uuid4() );
		$other      = self::endpoint( 'famma_express_checkout', $other_post );
		self::assert( 'new-intention-new-order', 'success' === ( $other['result'] ?? '' ) && $first['order_id'] !== $other['order_id'], 'Distinct valid intention creates a distinct order.' );
		$stale_prep = self::prepare();
		$stale_post = self::commit_fields( $stale_prep, wp_generate_uuid4() );
		WC()->cart->add_to_cart( self::$context['products'][1], 1 );
		WC()->cart->calculate_totals();
		$stale = self::endpoint( 'famma_express_checkout', $stale_post );
		self::assert( 'changed-cart-reconfirm', 'cart_changed' === ( $stale['famma_code'] ?? '' ) && 0 === count( self::request_orders( $stale_post['famma_request_id'] ) ), 'Modified cart requires a new confirmed recap.' );
		$price_prep = self::prepare();
		$price_post = self::commit_fields( $price_prep, wp_generate_uuid4() );
		$product    = wc_get_product( self::$context['products'][0] );
		$product->set_regular_price( '80' );
		$product->save();
		$repriced = self::endpoint( 'famma_express_checkout', $price_post );
		self::assert( 'repriced-reconfirm', 'cart_changed' === ( $repriced['famma_code'] ?? '' ) && 0 === count( self::request_orders( $price_post['famma_request_id'] ) ), 'Server repricing requires reconfirmation.' );
		$product->set_regular_price( '79' );
		$product->save();
		$invalid_prep                  = self::prepare();
		$invalid_post                  = self::commit_fields( $invalid_prep, wp_generate_uuid4() );
		$invalid_post['billing_phone'] = '123';
		$invalid                       = self::endpoint( 'famma_express_checkout', $invalid_post );
		self::assert( 'invalid-no-order', 'failure' === ( $invalid['result'] ?? '' ) && 0 === count( self::request_orders( $invalid_post['famma_request_id'] ) ), 'Invalid fields cannot create an order.' );
		$nonce_post                                       = $post;
		$nonce_post['woocommerce-process-checkout-nonce'] = 'expired';
		$nonce = self::endpoint( 'famma_express_checkout', $nonce_post );
		self::assert( 'expired-nonce-no-order', 'failure' === ( $nonce['result'] ?? '' ) && 1 === count( self::request_orders( $post['famma_request_id'] ) ), 'Expired nonce cannot create another order.' );
		$saved_cookie = self::$context['cookie'];
		unset( $_COOKIE[ $saved_cookie['name'] ] );
		self::session();
		$alien = self::endpoint( 'famma_express_status', array( 'famma_request_id' => $post['famma_request_id'] ) );
		self::assert( 'other-session-no-disclosure', ! isset( $alien['order_id'] ) && ! isset( $alien['redirect'] ) && 'success' !== ( $alien['result'] ?? '' ), 'Another guest cannot observe order identifiers or result.' );
		self::session( $saved_cookie );
		self::assert( 'cod-unpaid', ! wc_get_order( $first['order_id'] )->is_paid(), 'COD is unpaid before delivery.' );
		$order->update_status( 'delivered' );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Replay the actual WooCommerce status action.
		do_action( 'woocommerce_order_status_delivered', $order->get_id() );
		$purchase = array_filter( self::$events, static fn( array $event ): bool => 'Purchase' === ( $event['event_name'] ?? '' ) );
		self::assert( 'cod-paid-purchase-once', wc_get_order( $order->get_id() )->is_paid() && 1 === count( $purchase ), 'Delivery recognizes COD and emits one intercepted Purchase.' );
		self::stale_session_cookie();
	}

	/**
	 * A present-but-unverifiable session cookie must be replaced by the token endpoint.
	 *
	 * Simulates the browser across two requests: the cookie it holds for the
	 * second request is the one the first response set, or the stale one if
	 * none was set.
	 *
	 * @throws RuntimeException When the stale cookie still blocks preparation.
	 */
	private static function stale_session_cookie(): void {
		$saved  = self::$context['cookie'];
		$stale  = array(
			'name'  => $saved['name'],
			'value' => 't_stale|1999999999|1999990000|deadbeef',
		);
		$issued = array();
		self::session( $stale );
		$capture = static function ( $enabled, $name, $value ) use ( &$issued, $saved ) {
			if ( $saved['name'] === $name ) {
				$issued[] = (string) $value; }
			return $enabled;
		};
		add_filter( 'woocommerce_set_cookie_enabled', $capture, PHP_INT_MAX, 3 );
		$token = self::endpoint( 'famma_express_nonce', array() );
		remove_filter( 'woocommerce_set_cookie_enabled', $capture, PHP_INT_MAX );
		$held = $issued ? (string) end( $issued ) : $stale['value'];
		self::session(
			array(
				'name'  => $saved['name'],
				'value' => $held,
			)
		);
		self::$context['sessions'][] = WC()->session->get_customer_id();
		$prepare                     = self::endpoint(
			'famma_express_prepare',
			array(
				'woocommerce-process-checkout-nonce' => (string) ( $token['data']['nonce'] ?? '' ),
				'famma_preparation_id'               => wp_generate_uuid4(),
			)
		);
		self::assert( 'stale-cookie-reissued', '' !== $held && $stale['value'] !== $held && is_array( WC()->session->get_session_cookie() ), 'Token endpoint replaces an unverifiable session cookie with a valid one.' );
		self::assert( 'stale-cookie-token-accepted', 'expired' !== ( $prepare['famma_token'] ?? '' ), 'Token issued over a stale cookie verifies on the next request.' );
		self::session( $saved );
	}

	/**
	 * Run a real PHP worker after a shared readiness barrier.
	 *
	 * @throws RuntimeException When coordination or response polling fails.
	 */
	private static function worker(): void {
		if ( ! in_array( self::$role, array( 'a', 'b' ), true ) ) {
			throw new RuntimeException( 'Worker role must be a or b.' ); }
		update_option( self::context_name() . '_ready_' . self::$role, microtime( true ), false );
		$deadline = microtime( true ) + 20;
		while ( ! self::fresh_option( self::context_name() . '_ready_a' ) || ! self::fresh_option( self::context_name() . '_ready_b' ) ) {
			if ( microtime( true ) > $deadline ) {
				throw new RuntimeException( 'Peer worker did not reach readiness barrier.' ); }
			usleep( 50000 );
		}
		add_action( 'famma_express_reserved', array( self::class, 'hold_reservation' ), PHP_INT_MAX );
		$started        = microtime( true );
		$response       = self::endpoint( 'famma_express_checkout', self::$context['post'] );
		$first_response = $response;
		while ( 'processing' === ( $response['result'] ?? '' ) && microtime( true ) < $deadline ) {
			usleep( 100000 );
			$response = self::endpoint( 'famma_express_status', array( 'famma_request_id' => self::$context['request'] ) );
		}
		$observation = array(
			'role'           => self::$role,
			'started'        => $started,
			'ended'          => microtime( true ),
			'reserved_at'    => self::$reserved_at,
			'first_response' => $first_response,
			'final_response' => $response,
		);
		update_option( self::context_name() . '_worker_' . self::$role, $observation, false );
		self::$results[] = $observation;
	}

	/**
	 * Force a measurable overlap while the unique database reservation is held.
	 *
	 * @param string $request Request UUID reserved by the backend.
	 */
	public static function hold_reservation( string $request ): void {
		if ( self::$context['request'] === $request ) {
			self::$reserved_at = microtime( true );
			usleep( 2000000 ); }
	}

	/**
	 * Terminate a real worker after the order is linked durably.
	 *
	 * @param WC_Order $order Persisted fixture order.
	 * @param string   $request Exact request UUID.
	 */
	public static function crash( WC_Order $order, string $request ): void {
		if ( self::$context['request'] === $request ) {
			$order->update_meta_data( '_famma_integration_run', self::$run );
			$order->save();
			update_option( self::context_name() . '_crash_order', $order->get_id(), false );
			exit( 77 );
		}
	}

	/**
	 * Check separate-process overlap and exact order uniqueness.
	 *
	 * @throws RuntimeException When worker results fail the concurrency contract.
	 */
	private static function verify_workers(): void {
		$a = (array) self::fresh_option( self::context_name() . '_worker_a' );
		$b = (array) self::fresh_option( self::context_name() . '_worker_b' );
		self::assert( 'workers-overlapped', max( $a['started'] ?? PHP_FLOAT_MAX, $b['started'] ?? PHP_FLOAT_MAX ) < min( $a['ended'] ?? 0, $b['ended'] ?? 0 ), 'Two distinct PHP worker execution windows overlap.' );
		self::assert( 'concurrency-one-order', 1 === count( self::request_orders( self::$context['request'] ) ), 'Atomic backend created one order for the shared request.' );
		self::assert( 'workers-same-result', 'success' === ( $a['final_response']['result'] ?? '' ) && 'success' === ( $b['final_response']['result'] ?? '' ) && ( $a['final_response']['order_id'] ?? 0 ) === ( $b['final_response']['order_id'] ?? -1 ), 'Both workers resolve to the same accepted order.' );
		self::conversion_assertions( wc_get_order( $a['final_response']['order_id'] ) );
		self::$results[] = array(
			'name' => 'worker-evidence',
			'a'    => $a,
			'b'    => $b,
		);
	}

	/**
	 * Verify actual task uniqueness and replay the real workers with intercepted transports.
	 *
	 * @param WC_Order $order Exact accepted fixture order.
	 */
	private static function conversion_assertions( WC_Order $order ): void {
		$capi = as_get_scheduled_actions(
			array(
				'hook'     => 'famma_capi_orderplaced_send',
				'group'    => 'famma-capi',
				'args'     => array( 'order_id' => $order->get_id() ),
				'status'   => 'pending',
				'per_page' => 10,
			),
			'ids'
		);
		$mail = as_get_scheduled_actions(
			array(
				'hook'     => \Famma\Core\Order_Emails::ACTION,
				'group'    => \Famma\Core\Order_Emails::GROUP,
				'args'     => array( $order->get_id() ),
				'status'   => 'pending',
				'per_page' => 10,
			),
			'ids'
		);
		self::assert( 'one-orderplaced-task', 1 === count( $capi ), 'Exactly one real OrderPlaced task is pending for this accepted intention.' );
		self::assert( 'one-new-order-email-task', 1 === count( $mail ), 'Exactly one real new-order email task is pending.' );
		do_action( 'famma_capi_orderplaced_send', $order->get_id() );
		do_action( 'famma_capi_orderplaced_send', $order->get_id() );
		$placed = array_filter( self::$events, static fn( array $event ): bool => 'OrderPlaced' === ( $event['event_name'] ?? '' ) );
		self::assert( 'orderplaced-worker-replay-once', 1 === count( $placed ), 'Two real worker executions yield one locally intercepted OrderPlaced.' );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Actual FAMMA action constant.
		do_action( \Famma\Core\Order_Emails::ACTION, $order->get_id() );
		$first_mail = self::$mail;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Actual FAMMA action constant.
		do_action( \Famma\Core\Order_Emails::ACTION, $order->get_id() );
		self::assert( 'email-worker-replay-once', $first_mail > 0 && $first_mail === self::$mail, 'Email worker replay adds no second mail; all attempts remain locally intercepted.' );
	}

	/**
	 * Retrieve one run-owned option with cross-process cache invalidation.
	 *
	 * @param string $name Exact coordination option.
	 * @return mixed Current option state.
	 */
	private static function fresh_option( string $name ) {
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return get_option( $name, false ); }

	/**
	 * Retrieve request-linked orders through the WooCommerce CRUD query.
	 *
	 * @param string $request Exact request UUID.
	 * @return array Matching actual orders.
	 */
	private static function request_orders( string $request ): array {
		return wc_get_orders(
			array(
				'limit'      => 20,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded fixture-owned CRUD lookup.
				'meta_key'   => '_famma_express_request_id',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded fixture-owned CRUD lookup.
				'meta_value' => $request,
			)
		); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded fixture lookup through CRUD; no raw order tables.
	}

	/**
	 * Count actual pending OrderPlaced tasks.
	 *
	 * @return int Pending tasks in the isolated database.
	 */
	private static function pending_capi_count(): int {
		return count(
			as_get_scheduled_actions(
				array(
					'hook'     => 'famma_capi_orderplaced_send',
					'group'    => 'famma-capi',
					'status'   => 'pending',
					'per_page' => 100,
				),
				'ids'
			)
		);
	}

	/**
	 * Retrieve suite-owned orders.
	 *
	 * @return array Actual suite-owned orders.
	 */
	private static function fixture_orders(): array {
		return wc_get_orders(
			array(
				'limit'      => 100,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded fixture-owned CRUD lookup.
				'meta_key'   => '_famma_integration_run',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded fixture-owned CRUD lookup.
				'meta_value' => self::$run,
			)
		); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded suite-owned CRUD lookup for cleanup only.
	}

	/** Persist clone fixture coordination state, including synthetic cookie only in test DB. */
	private static function save_context(): void {
		WC()->session->save_data();
		update_option( self::context_name(), self::$context, false ); }
	/**
	 * Identify the run-owned option.
	 *
	 * @return string Exact run-owned option identifier.
	 */
	private static function context_name(): string {
		return 'famma_test_express_' . self::$run; }

	/**
	 * Mark a real created order before fixture cleanup.
	 *
	 * @param WC_Order $order Newly saved test order.
	 */
	public static function capture_order( WC_Order $order ): void {
		$order->update_meta_data( '_famma_integration_run', self::$run );
		$order->save(); }

	/**
	 * Remember anti-abuse counters created by test requests.
	 *
	 * @param string $name Counter name.
	 * @param mixed  $value Ignored synthetic counter value.
	 * @param int    $expiration Ignored expiration.
	 */
	public static function capture_transient( string $name, $value, int $expiration ): void {
		unset( $value, $expiration );
		if ( str_starts_with( $name, 'famma_ab_' ) ) {
			self::$transients[] = $name; } }

	/**
	 * Intercept every HTTP request and acknowledge only the suite's fake Meta token.
	 *
	 * @param mixed  $preempt Prior transport result.
	 * @param array  $args Request arguments.
	 * @param string $url Destination URL.
	 * @return array|WP_Error Local synthetic response.
	 */
	public static function http( $preempt, array $args, string $url ) {
		unset( $preempt );
		$body = json_decode( (string) ( $args['body'] ?? '' ), true );
		if ( 'graph.facebook.com' === wp_parse_url( $url, PHP_URL_HOST ) && 'INTEGRATION_FAKE_TOKEN_NOT_A_SECRET' === ( $body['access_token'] ?? '' ) ) {
			self::$events = array_merge( self::$events, $body['data'] ?? array() );
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"events_received":1}',
				'headers'  => array(),
				'cookies'  => array(),
			);
		}
		return new WP_Error( 'famma_test_http_blocked', 'No external HTTP in the clone.' );
	}

	/**
	 * Accept mail locally without sending it.
	 *
	 * @param mixed $preempt Original transport decision.
	 * @param array $attributes Message fields, not recorded.
	 * @return bool Synthetic success.
	 */
	public static function mail( $preempt, array $attributes ): bool {
		unset( $preempt, $attributes );
		++self::$mail;
		return true; }

	/**
	 * Supply real COD for virtual fixtures.
	 *
	 * @param array $gateways Existing candidates.
	 * @return array Real COD gateway.
	 */
	public static function cod_only( array $gateways ): array {
		unset( $gateways );
		$all = WC()->payment_gateways()->payment_gateways();
		return array( 'cod' => $all['cod'] ); }

	/**
	 * Replace native AJAX termination with an Error outside Woo's Exception catch.
	 *
	 * @param callable $handler Native handler.
	 * @return callable Termination interceptor.
	 */
	public static function die_handler( $handler ): callable {
		unset( $handler );
		return static function (): void {
			throw new Famma_Express_Test_Stop( 'Captured native JSON.' );
		}; }

	/**
	 * Record a real assertion and fail immediately.
	 *
	 * @param string $name Scenario identifier.
	 * @param bool   $passed Actual observation.
	 * @param string $message Meaningful expected behavior.
	 * @throws RuntimeException When assertion fails.
	 */
	private static function assert( string $name, bool $passed, string $message ): void {
		self::$results[] = array(
			'name'    => $name,
			'passed'  => $passed,
			'message' => $message,
		);
		if ( ! $passed ) {
			throw new RuntimeException( esc_html( $name . ': ' . $message ) ); } }

	/** Delete only fixtures owned by this exact run, then restore options. */
	private static function cleanup(): void {
		global $wpdb;
		foreach ( self::fixture_orders() as $order ) {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( 'famma_capi_orderplaced_send', array( 'order_id' => $order->get_id() ), 'famma-capi' );
				as_unschedule_all_actions( \Famma\Core\Order_Emails::ACTION, array( $order->get_id() ), \Famma\Core\Order_Emails::GROUP ); }
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete only exact run-owned custom registry rows; Woo orders use CRUD.
			$wpdb->delete( \Famma\Core\Capi_Delivery_Registry::instance()->table(), array( 'order_id' => $order->get_id() ), array( '%d' ) );
			$order->delete( true );
		}
		foreach ( self::$context['products'] ?? array() as $id ) {
			$product = wc_get_product( $id );
			if ( $product && self::$run === $product->get_meta( '_famma_integration_run' ) ) {
				$product->delete( true ); }
		}
		foreach ( array_unique( self::$context['requests'] ?? array() ) as $request ) {
			$wpdb->delete( $wpdb->prefix . 'famma_express_requests', array( 'request_id' => $request ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Remove only exact suite-owned test request rows; never raw Woo order metadata.
		}
		foreach ( array_unique( self::$context['sessions'] ?? array() ) as $id ) {
			WC()->session->delete_session( $id ); }
		foreach ( array_unique( self::$transients ) as $name ) {
			delete_transient( $name ); }
		foreach ( self::$context['options'] ?? array() as $name => $value ) {
			if ( null === $value ) {
				delete_option( $name );
			} else {
				update_option( $name, $value ); }
		}
		foreach ( array( '', '_ready_a', '_ready_b', '_worker_a', '_worker_b', '_crash_order' ) as $suffix ) {
			delete_option( self::context_name() . $suffix ); }
	}
}

Famma_Express_Test::run();
