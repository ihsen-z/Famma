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
	 * Lines written to the famma-capi WooCommerce log (first log handler only).
	 *
	 * @var array
	 */
	private static array $logs = array();
	/**
	 * Log handler whose lines are kept, to avoid counting one line per handler.
	 *
	 * @var string
	 */
	private static string $log_handler = '';
	/**
	 * Synthetic Meta cookies sent with the next endpoint calls.
	 *
	 * @var array
	 */
	private static array $cookies = array();
	/**
	 * Observed shutdown priority of the cart restoration.
	 *
	 * @var int
	 */
	private static int $restore_priority = 0;
	/**
	 * Cart cookies written through wc_setcookie(): name => [value, expire].
	 *
	 * @var array
	 */
	private static array $set_cookies = array();
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
		add_filter( 'woocommerce_logger_log_message', array( self::class, 'capture_log' ), PHP_INT_MAX, 4 );
		// Many synthetic orders share one fixture phone: only the per-phone/IP ceilings are raised, the guards still run.
		add_filter( 'woocommerce_set_cookie_enabled', array( self::class, 'capture_cookie' ), PHP_INT_MAX, 4 );
		add_filter( 'famma_anti_abuse_limits', array( self::class, 'test_limits' ), PHP_INT_MAX );
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
		foreach ( array( 'woocommerce_currency', 'woocommerce_default_country', 'woocommerce_enable_guest_checkout', 'woocommerce_cod_settings', 'woocommerce_terms_page_id', 'famma_meta_orderplaced_enabled', 'famma_meta_pixel_id', 'famma_meta_access_token', 'famma_meta_test_event_code' ) as $name ) {
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
		// A terms page exists, so the classic checkout must still require its checkbox.
		self::$context['terms_page'] = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => '[TEST LOCAL] Conditions fixture',
				'meta_input'  => array( '_famma_integration_run' => self::$run ),
			)
		);
		update_option( 'woocommerce_terms_page_id', self::$context['terms_page'] );
		// Product A at 77 TND (express product), B at 49 and C at 30 TND (other cart lines).
		foreach ( array( 77, 49, 30 ) as $price ) {
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
		$coupon = new WC_Coupon();
		$coupon->set_code( 'famma-qa-' . substr( self::$run, 0, 8 ) );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->update_meta_data( '_famma_integration_run', self::$run );
		$coupon->save();
		self::$context['coupon'] = $coupon->get_id();
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
	 * Replace the guest cart with exact fixture lines and persist the session.
	 *
	 * @param array $lines Fixture product index => quantity.
	 */
	private static function set_cart( array $lines ): void {
		WC()->cart->empty_cart();
		WC()->session->set( 'order_awaiting_payment', false );
		foreach ( $lines as $index => $quantity ) {
			WC()->cart->add_to_cart( self::$context['products'][ $index ], $quantity );
		}
		WC()->cart->calculate_totals();
		WC()->session->save_data();
	}

	/**
	 * Observable cart state: line keys, products, quantities and coupons.
	 *
	 * @return array Comparable state.
	 */
	private static function cart_state(): array {
		$lines = array();
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$lines[ $key ] = array( (int) $item['product_id'], (float) $item['quantity'] );
		}
		ksort( $lines );
		return array(
			'lines'   => $lines,
			'coupons' => WC()->cart->get_applied_coupons(),
		);
	}

	/**
	 * Cart as stored in the WooCommerce session table, reloaded through a new session object.
	 *
	 * @return array Comparable state.
	 */
	private static function stored_cart_state(): array {
		WC()->session->save_data();
		self::session( self::$context['cookie'] );
		return self::cart_state();
	}

	/**
	 * Product index => quantity of a cart state.
	 *
	 * @param array $state Cart state.
	 * @return array Quantities by fixture product ID.
	 */
	private static function quantities( array $state ): array {
		$out = array();
		foreach ( $state['lines'] as $line ) {
			$out[ $line[0] ] = ( $out[ $line[0] ] ?? 0 ) + $line[1];
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Set a guest cart, then call the real prepare endpoint for product A.
	 *
	 * @param int   $quantity Express quantity of product A.
	 * @param array $cart Cart lines before preparation (fixture index => quantity).
	 * @return array Accepted backend preparation.
	 * @throws RuntimeException When preparation fails.
	 */
	private static function prepare( int $quantity = 1, array $cart = array(
		0 => 2,
		1 => 1,
	) ): array {
		++self::$scenario;
		self::set_cart( $cart );
		$prep     = self::prepare_request( $quantity );
		$accepted = 'success' === ( $prep['result'] ?? '' );
		self::assert( 'prepare-success-' . self::$scenario, $accepted, 'Real prepare endpoint accepted.' );
		WC()->session->save_data();
		return $prep;
	}

	/**
	 * Call the real prepare endpoint on the current cart.
	 *
	 * @param int    $quantity Express quantity of product A.
	 * @param string $id Preparation UUID; a new one when empty.
	 * @return array Native response.
	 */
	private static function prepare_request( int $quantity, string $id = '' ): array {
		return self::endpoint(
			'famma_express_prepare',
			array(
				'famma_product_id'     => self::$context['products'][0],
				'famma_quantity'       => (string) $quantity,
				'famma_preparation_id' => '' !== $id ? $id : wp_generate_uuid4(),
				'billing_state'        => 'TN-51',
				'billing_country'      => 'TN',
				'famma_hp_site'        => '',
			)
		);
	}

	/**
	 * Assemble the one-click form payload: no terms checkbox, as the express form sends it.
	 *
	 * @param array  $prep Accepted preparation.
	 * @param string $request Stable request UUID.
	 * @param int    $quantity Express quantity.
	 * @param string $phone Phone exactly as typed.
	 * @return array Commit POST fields.
	 */
	private static function commit_fields( array $prep, string $request, int $quantity = 1, string $phone = '20 123 456' ): array {
		self::$context['requests'][] = $request;
		return array(
			'famma_product_id'     => self::$context['products'][0],
			'famma_quantity'       => (string) $quantity,
			'famma_preparation_id' => $prep['preparation_id'],
			'famma_cart_hash'      => $prep['cart_hash'],
			'famma_request_id'     => $request,
			'billing_first_name'   => 'Client Fixture Test',
			'billing_phone'        => $phone,
			'billing_state'        => 'TN-51',
			'billing_country'      => 'TN',
			'payment_method'       => 'cod',
			'famma_hp_site'        => '',
		);
	}

	/**
	 * Invoke a real AJAX hook and capture its native JSON response.
	 *
	 * Nested output buffers opened by the handler are flushed through their
	 * callbacks, as PHP does at the end of a real request. The end of the
	 * request is then emulated: shutdown callbacks registered before
	 * WooCommerce's own (priority < 0, i.e. the cart restoration) run, and
	 * request-scoped express filters are removed.
	 *
	 * @param string $endpoint Native action name without wc_ajax prefix.
	 * @param array  $fields Synthetic POST fields.
	 * @return array Decoded native response.
	 * @throws RuntimeException When response decoding fails.
	 */
	private static function endpoint( string $endpoint, array $fields ): array {
		$_SERVER['REMOTE_ADDR']     = '192.0.2.' . ( self::$scenario % 200 + 30 );
		$_SERVER['HTTP_USER_AGENT'] = 'FAMMA isolated express integration';
		foreach ( array( '_fbc', '_fbp' ) as $name ) {
			unset( $_COOKIE[ $name ] );
			if ( isset( self::$cookies[ $name ] ) ) {
				$_COOKIE[ $name ] = self::$cookies[ $name ];
			}
		}
		$_POST = array_merge( array( 'woocommerce-process-checkout-nonce' => wp_create_nonce( 'woocommerce-process_checkout' ) ), $fields );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Synthetic POST only; native endpoint verifies its nonce.
		$_REQUEST = $_POST;
		wc_clear_notices();
		$level = ob_get_level();
		ob_start();
		try {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Exercise the native WooCommerce AJAX contract.
			do_action( 'wc_ajax_' . $endpoint );
		} catch ( Famma_Express_Test_Stop $stop ) {
			$stopped = true;
		} finally {
			while ( ob_get_level() > $level + 1 ) {
				ob_end_flush();
			}
			$output = ob_get_clean();
			self::request_end();
		}
		$result = json_decode( (string) $output, true );
		if ( ! isset( $stopped ) || ! is_array( $result ) ) {
			throw new RuntimeException( 'Native endpoint did not yield isolated JSON.' );
		}
		return $result;
	}

	/** Emulate the end of a real request for the code under test. */
	private static function request_end(): void {
		global $wp_filter;
		$checkout               = \Famma\Core\Express_Checkout::instance();
		$restore                = array( \Famma\Core\Express_Cart_Isolation::instance(), 'restore' );
		self::$restore_priority = (int) has_action( 'shutdown', $restore );
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
		unset( $_COOKIE['_fbc'], $_COOKIE['_fbp'] );
		// WC_Checkout caches its field definitions per process; a real request starts without that cache.
		$fields = new ReflectionProperty( WC()->checkout(), 'fields' );
		$fields->setAccessible( true );
		$fields->setValue( WC()->checkout(), null );
	}

	/**
	 * Run WooCommerce's real order-received cart clearing, as template_redirect does.
	 *
	 * @param WC_Order $order Order shown on the page.
	 * @param bool     $clear Whether to run the real clearing after reading the decision.
	 * @return bool Decision of the woocommerce_should_clear_cart_after_payment filter for a cart WooCommerce would clear.
	 */
	private static function thankyou_page( WC_Order $order, bool $clear = true ): bool {
		global $wp;
		$wp->query_vars['order-received'] = (string) $order->get_id();
		$_GET['key']                      = $order->get_order_key();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Native WooCommerce filter.
		$decision = (bool) apply_filters( 'woocommerce_should_clear_cart_after_payment', true );
		if ( $clear ) {
			wc_clear_cart_after_payment();
		}
		unset( $wp->query_vars['order-received'], $_GET['key'] );
		return $decision;
	}

	/**
	 * Execute sequential transaction assertions on the one-click contract.
	 *
	 * @throws RuntimeException When a backend contract assertion fails.
	 */
	private static function sequential(): void {
		$a = self::$context['products'][0];
		$b = self::$context['products'][1];

		// 1. Terms: the classic checkout still requires the box (run first: express filters are request-scoped).
		self::set_cart( array( 0 => 1 ) );
		$classic         = array(
			'billing_first_name'        => 'Client Fixture',
			'billing_last_name'         => 'Classique',
			'billing_phone'             => '20123456',
			'billing_state'             => 'TN-51',
			'billing_country'           => 'TN',
			'billing_address_1'         => '[TEST LOCAL] adresse',
			'billing_city'              => 'Sousse',
			'billing_email'             => '',
			'payment_method'            => 'cod',
			'terms-field'               => '1',
			'ship_to_different_address' => '0',
			'famma_hp_site'             => '',
		);
		$refused         = self::endpoint( 'checkout', $classic );
		self::$results[] = array(
			'name'     => 'classic-terms-response',
			'messages' => wp_strip_all_tags( (string) ( $refused['messages'] ?? '' ) ),
		);
		self::assert( 'classic-checkout-terms-required', 'failure' === ( $refused['result'] ?? '' ) && false !== stripos( (string) ( $refused['messages'] ?? '' ), 'terms' ) && 0 === count( self::fixture_orders() ), 'Classic checkout without the terms box is refused when a terms page exists.' );
		$classic['terms'] = '1';
		$accepted_classic = self::endpoint( 'checkout', $classic );
		self::assert( 'classic-checkout-with-terms-ok', 'success' === ( $accepted_classic['result'] ?? '' ), 'The same classic checkout passes once the box is ticked: terms were the only refusal.' );

		// 2. prepare: isolated quote, no trace left in the cart.
		$tasks_before = self::pending_capi_count();
		$orders       = count( self::fixture_orders() );
		self::set_cart(
			array(
				0 => 2,
				1 => 1,
			)
		);
		$before = self::cart_state();
		$prep   = self::prepare_request( 1 );
		self::assert( 'prepare-success', 'success' === ( $prep['result'] ?? '' ), 'Prepare accepted.' );
		$items = $prep['summary']['items'] ?? array();
		self::assert( 'prepare-isolated-quote', 77.0 === (float) ( $prep['summary']['total'] ?? 0 ) && 'TND' === ( $prep['summary']['currency'] ?? '' ) && 1 === count( $items ) && (int) ( $items[0]['product_id'] ?? 0 ) === $a && 1 === (int) ( $items[0]['quantity'] ?? 0 ), 'Quote holds only product A x1, total 77 TND, although the cart holds A x2 + B x1.' );
		self::assert( 'prepare-other-items', 1 === ( $prep['other_items'] ?? null ), 'other_items counts the B line left in the cart.' );
		self::assert( 'prepare-initiate-checkout-value', 77.0 === (float) ( $prep['tracking']['initiate_checkout']['params']['value'] ?? 0 ) && '' !== (string) ( $prep['tracking']['initiate_checkout']['event_id'] ?? '' ), 'InitiateCheckout carries the isolated total and a server event_id.' );
		self::$results[] = array(
			'name'    => 'prepare-contract',
			'summary' => $prep['summary'] ?? array(),
		);
		self::assert( 'prepare-no-trace', self::cart_state() === $before, 'Prepare leaves the in-memory cart exactly as it was (keys, quantities, coupons).' );
		self::assert( 'prepare-no-trace-stored', self::stored_cart_state() === $before, 'Prepare leaves the stored session cart exactly as it was.' );
		$replay = self::prepare_request( 1, $prep['preparation_id'] );
		self::assert( 'prepare-replay-same-quote', ( $replay['cart_hash'] ?? '' ) === $prep['cart_hash'] && ( $replay['summary'] ?? null ) === $prep['summary'] && self::cart_state() === $before, 'Replayed preparation returns the same quote and still leaves no trace.' );
		self::assert( 'prepare-no-order', count( self::fixture_orders() ) === $orders, 'Preparation creates no order.' );
		self::assert( 'prepare-no-capi', 0 === count( self::$events ), 'Preparation sends nothing to Meta.' );
		self::assert( 'prepare-no-async-conversion', self::pending_capi_count() === $tasks_before, 'Preparation enqueues no OrderPlaced.' );
		$bad_prepare = self::endpoint(
			'famma_express_prepare',
			array(
				'famma_product_id'                   => $a,
				'famma_quantity'                     => '99',
				'famma_preparation_id'               => wp_generate_uuid4(),
				'woocommerce-process-checkout-nonce' => 'expired',
			)
		);
		self::assert( 'prepare-bad-nonce-no-cart-change', 'failure' === ( $bad_prepare['result'] ?? '' ) && self::cart_state() === $before, 'Invalid prepare nonce causes no cart mutation.' );
		$too_many = self::prepare_request( 99 );
		self::assert( 'prepare-refused-quantity-restores-cart', 'failure' === ( $too_many['result'] ?? '' ) && '' !== trim( wp_strip_all_tags( (string) ( $too_many['messages'] ?? '' ) ) ) && self::stored_cart_state() === $before, 'A refused quantity answers with a message and restores the cart.' );

		// 3. One click: one order, A x1 only, B left in the cart, idempotent replay.
		self::$cookies = array(
			'_fbc' => 'fb.1.1760000000000.IwFixtureClickIdLocalOnly',
			'_fbp' => 'fb.1.1760000000000.1234567890',
		);
		$post          = self::commit_fields( $prep, wp_generate_uuid4() );
		$first         = self::endpoint( 'famma_express_checkout', $post );
		self::$cookies = array();
		self::assert( 'restore-before-session-save', self::$restore_priority < 0 && self::$restore_priority < (int) has_action( 'shutdown', array( WC()->session, 'save_data' ) ), 'Cart restoration runs at shutdown before WooCommerce saves the session.' );
		self::assert( 'one-click-success', 'success' === ( $first['result'] ?? '' ) && ! empty( $first['redirect'] ) && ! empty( $first['order_id'] ), 'One click without terms box creates the order and returns the WooCommerce redirect: ' . wp_strip_all_tags( (string) wp_json_encode( $first ) ) );
		$after_success = self::cart_state();
		$retry         = self::endpoint( 'famma_express_checkout', $post );
		self::assert( 'same-key-one-order', ( $first['order_id'] ?? 0 ) === ( $retry['order_id'] ?? -1 ) && 1 === count( self::request_orders( $post['famma_request_id'] ) ), 'Same request_id replayed returns the identical single order.' );
		$order = wc_get_order( $first['order_id'] );
		$lines = array();
		foreach ( $order->get_items() as $item ) {
			$lines[ $item->get_product_id() ] = $item->get_quantity();
		}
		self::assert( 'order-only-product-a', array( $a => 1 ) === $lines && 77.0 === (float) $order->get_total(), 'Order holds only A x1 for 77 TND, never the B line.' );
		self::assert( 'cart-after-success-b-only', array( $b => 1.0 ) === self::quantities( $after_success ) && array( $b => 1.0 ) === self::quantities( self::stored_cart_state() ), 'After the order the cart holds B x1 only, in memory and in the stored session.' );
		self::assert( 'billing-phone-normalized', '20123456' === $order->get_billing_phone(), 'Billing phone stored as 8 national digits.' );
		self::assert( 'express-order-stamped', $post['famma_request_id'] === $order->get_meta( '_famma_express_request_id' ), 'Express order carries its request marker.' );
		self::conversion_assertions( $order, true );

		// 3b. Order-received page: WooCommerce clears the cart on template_redirect; the other lines must stay.
		$c        = self::$context['products'][2];
		$received = self::prepare(
			1,
			array(
				1 => 1,
				2 => 1,
			)
		);
		$rec_post = self::commit_fields( $received, wp_generate_uuid4() );
		$rec      = self::endpoint( 'famma_express_checkout', $rec_post );
		self::assert( 'received-order-success', 'success' === ( $rec['result'] ?? '' ), 'Express order with B and C in the cart.' );
		$rec_order = wc_get_order( $rec['order_id'] );
		self::session( self::$context['cookie'] );
		self::assert( 'order-received-express-not-cleared', false === self::thankyou_page( $rec_order ), 'On an express order-received page the cart is never cleared, whatever the cart hash.' );
		self::assert( 'order-received-classic-still-cleared', true === self::thankyou_page( wc_get_order( $accepted_classic['order_id'] ), false ), 'A classic order keeps WooCommerce clearing decision.' );
		$kept = self::quantities( self::cart_state() );
		self::assert(
			'order-received-keeps-other-lines',
			array(
				$b => 1.0,
				$c => 1.0,
			) === $kept && self::quantities( self::stored_cart_state() ) === $kept,
			'Loading order-received keeps B and C in the cart (got ' . wp_json_encode( $kept ) . ').'
		);

		// 3c. Coupon on the cart: the isolated quote and order ignore it; it stays on the cart (failure and success).
		$code = wc_get_coupon_code_by_id( self::$context['coupon'] );
		self::set_cart(
			array(
				0 => 2,
				1 => 1,
			)
		);
		WC()->cart->apply_coupon( $code );
		WC()->cart->calculate_totals();
		WC()->session->save_data();
		$with_coupon = self::cart_state();
		$c_prep      = self::prepare_request( 1 );
		self::assert( 'coupon-quote-isolated', 77.0 === (float) ( $c_prep['summary']['total'] ?? 0 ) && self::cart_state() === $with_coupon && array( $code ) === $with_coupon['coupons'], 'A cart coupon does not enter the isolated quote (77 TND) and stays applied after prepare.' );
		$c_bad   = self::commit_fields( $c_prep, wp_generate_uuid4(), 1, '123' );
		$refused = self::endpoint( 'famma_express_checkout', $c_bad );
		self::assert( 'coupon-kept-after-refusal', 'failure' === ( $refused['result'] ?? '' ) && self::stored_cart_state() === $with_coupon, 'After a refused express checkout the coupon is still applied.' );
		$c_prep = self::prepare_request( 1 );
		$c_post = self::commit_fields( $c_prep, wp_generate_uuid4() );
		$c_done = self::endpoint( 'famma_express_checkout', $c_post );
		$c_ord  = 'success' === ( $c_done['result'] ?? '' ) ? wc_get_order( $c_done['order_id'] ) : null;
		self::assert( 'coupon-not-consumed', $c_ord && 77.0 === (float) $c_ord->get_total() && array() === $c_ord->get_coupon_codes() && 0 === ( new WC_Coupon( $code ) )->get_usage_count(), 'The express order neither uses nor consumes the cart coupon.' );
		$after = self::stored_cart_state();
		self::assert( 'coupon-restored-after-success', array( $b => 1.0 ) === self::quantities( $after ) && array( $code ) === $after['coupons'], 'After the express order the other line and the coupon are back in the cart.' );

		// 3d. Cart cookies sent with the prepare response describe the restored cart, not the isolated one.
		self::set_cart( array() );
		unset( $_COOKIE['woocommerce_items_in_cart'], $_COOKIE['woocommerce_cart_hash'] );
		self::$set_cookies = array();
		$empty_prep        = self::prepare_request( 1 );
		self::assert( 'prepare-empty-cart-no-cookie', 'success' === ( $empty_prep['result'] ?? '' ) && '' === self::browser_cookie( 'woocommerce_items_in_cart', '' ) && '' === self::browser_cookie( 'woocommerce_cart_hash', '' ), 'After prepare on an empty cart the browser holds no cart cookie: ' . wp_json_encode( self::$set_cookies ) );
		self::set_cart( array( 1 => 1 ) );
		$b_hash                               = WC()->cart->get_cart_hash();
		$_COOKIE['woocommerce_items_in_cart'] = '1';
		$_COOKIE['woocommerce_cart_hash']     = $b_hash;
		self::$set_cookies                    = array();
		$b_prep                               = self::prepare_request( 1 );
		self::assert( 'prepare-cookie-hash-restored-cart', 'success' === ( $b_prep['result'] ?? '' ) && self::browser_cookie( 'woocommerce_cart_hash', $b_hash ) === $b_hash && '1' === self::browser_cookie( 'woocommerce_items_in_cart', '1' ), 'After prepare the cart_hash cookie is the restored cart hash: ' . wp_json_encode( self::$set_cookies ) );
		unset( $_COOKIE['woocommerce_items_in_cart'], $_COOKIE['woocommerce_cart_hash'] );

		// 4. Validation failure: no order, field-tagged message, cart restored identically.
		$prep_bad = self::prepare();
		$before   = self::cart_state();
		$bad_post = self::commit_fields( $prep_bad, wp_generate_uuid4(), 1, '123' );
		$invalid  = self::endpoint( 'famma_express_checkout', $bad_post );
		self::assert( 'invalid-phone-no-order', 'failure' === ( $invalid['result'] ?? '' ) && 0 === count( self::request_orders( $bad_post['famma_request_id'] ) ), 'Invalid phone cannot create an order.' );
		self::assert( 'invalid-phone-data-id', false !== strpos( (string) ( $invalid['messages'] ?? '' ), 'data-id="billing_phone"' ), 'The refusal is tagged data-id="billing_phone" for the form.' );
		self::assert( 'invalid-phone-cart-restored', self::cart_state() === $before && self::stored_cart_state() === $before, 'After a refused checkout the cart is restored identically (keys, quantities).' );
		$state = self::endpoint( 'famma_express_status', array( 'famma_request_id' => $bad_post['famma_request_id'] ) );
		self::assert( 'invalid-status-failed', 'failed' === ( $state['famma_state'] ?? '' ) && 'validation_failed' === ( $state['famma_code'] ?? '' ), 'Status reports the proven validation failure.' );

		// 5. Phone formats: each stored as 20123456.
		$formats      = array( '20123456', '20 123 456', '+216 20123456', '0021620123456', '216 20 123 456', '٢٠١٢٣٤٥٦' );
		$phone_orders = array();
		foreach ( $formats as $index => $typed ) {
			$p        = self::prepare( 1, array( 1 => 1 ) );
			$fields   = self::commit_fields( $p, wp_generate_uuid4(), 1, $typed );
			$response = self::endpoint( 'famma_express_checkout', $fields );
			$stored   = 'success' === ( $response['result'] ?? '' ) ? wc_get_order( $response['order_id'] )->get_billing_phone() : 'refused';
			self::assert( 'phone-format-' . $index, '20123456' === $stored, 'Phone typed as « ' . $typed . ' » stored as 20123456 (got ' . $stored . ').' );
			$phone_orders[] = (int) $response['order_id'];
		}
		$events_before = count( self::$events );
		do_action( 'famma_capi_orderplaced_send', $phone_orders[2] );
		$sent = array_slice( self::$events, $events_before );
		self::assert( 'no-cookie-no-fbc', 1 === count( $sent ) && ! isset( $sent[0]['user_data']['fbc'] ) && ! isset( $sent[0]['user_data']['fbp'] ) && hash( 'sha256', '21620123456' ) === ( $sent[0]['user_data']['ph'] ?? '' ), 'Without cookies no fbc/fbp is sent; +216 format hashes as 21620123456.' );

		// 6. Intent and quote guards.
		$changed                       = $post;
		$changed['billing_first_name'] = 'Autre Fixture';
		$conflict                      = self::endpoint( 'famma_express_checkout', $changed );
		self::assert( 'same-key-payload-conflict', 'intent_conflict' === ( $conflict['famma_code'] ?? '' ) && 1 === count( self::request_orders( $post['famma_request_id'] ) ), 'Changed charge cannot reuse the key.' );
		$other_prep = self::prepare();
		$other_post = self::commit_fields( $other_prep, wp_generate_uuid4() );
		WC()->cart->add_to_cart( $b, 1 );
		WC()->cart->calculate_totals();
		$other = self::endpoint( 'famma_express_checkout', $other_post );
		self::assert( 'other-line-change-irrelevant', 'success' === ( $other['result'] ?? '' ) && $first['order_id'] !== $other['order_id'] && 1 === count( wc_get_order( $other['order_id'] )->get_items() ), 'Another line changed elsewhere does not block the isolated order, nor enter it.' );
		$qty_prep                   = self::prepare();
		$qty_post                   = self::commit_fields( $qty_prep, wp_generate_uuid4() );
		$qty_post['famma_quantity'] = '2';
		$qty                        = self::endpoint( 'famma_express_checkout', $qty_post );
		self::assert( 'quantity-change-reconfirm', 'preparation_missing' === ( $qty['famma_code'] ?? '' ) && 0 === count( self::request_orders( $qty_post['famma_request_id'] ) ), 'A quantity different from the quote requires a new preparation.' );
		$price_prep = self::prepare();
		$price_post = self::commit_fields( $price_prep, wp_generate_uuid4() );
		$product    = wc_get_product( $a );
		$product->set_regular_price( '80' );
		$product->save();
		$repriced = self::endpoint( 'famma_express_checkout', $price_post );
		$product->set_regular_price( '77' );
		$product->save();
		self::assert( 'repriced-reconfirm', 'cart_changed' === ( $repriced['famma_code'] ?? '' ) && 0 === count( self::request_orders( $price_post['famma_request_id'] ) ), 'Server repricing requires reconfirmation.' );
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

		// 7. COD lifecycle: confirmed -> shipped -> delivered; Purchase once, at delivery, real value.
		$purchases = static fn(): array => array_values( array_filter( self::$events, static fn( array $event ): bool => 'Purchase' === ( $event['event_name'] ?? '' ) ) );
		$order     = wc_get_order( $first['order_id'] );
		self::assert( 'cod-unpaid', ! $order->is_paid(), 'COD is unpaid before delivery.' );
		$order->update_status( 'confirmed' );
		$order->update_status( 'shipped' );
		$order = wc_get_order( $first['order_id'] );
		self::assert( 'no-purchase-before-delivery', ! $order->is_paid() && 0 === count( $purchases() ), 'Confirmed and shipped orders stay unpaid and send no Purchase.' );
		$order->update_status( 'delivered' );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Replay the actual WooCommerce status action.
		do_action( 'woocommerce_order_status_delivered', $order->get_id() );
		$order    = wc_get_order( $first['order_id'] );
		$purchase = $purchases();
		self::assert( 'delivered-paid', $order->is_paid() && 'delivered' === $order->get_status(), 'Delivered marks the COD order paid and keeps the delivered status.' );
		self::assert( 'purchase-once-real-value', 1 === count( $purchase ) && 77.0 === (float) ( $purchase[0]['custom_data']['value'] ?? 0 ) && 'TND' === ( $purchase[0]['custom_data']['currency'] ?? '' ), 'Delivery sends exactly one Purchase with the real 77 TND value, even when replayed.' );
		self::assert( 'purchase-logged-once', 1 === count( self::log_lines( 'Purchase', $order->get_id() ) ), 'One famma-capi log line for the Purchase.' );
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
	 * OrderPlaced: one pending task, executed twice through the real queue, one send.
	 *
	 * @param WC_Order $order Exact accepted fixture order.
	 * @param bool     $cookies Whether fixture _fbc/_fbp cookies were present at checkout.
	 */
	private static function conversion_assertions( WC_Order $order, bool $cookies = false ): void {
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
		// The real Action Scheduler runner executes the task, then a duplicate execution is forced.
		ActionScheduler::runner()->process_action( (int) reset( $capi ), 'famma-integration' );
		do_action( 'famma_capi_orderplaced_send', $order->get_id() );
		$placed = array_values( array_filter( self::$events, static fn( array $event ): bool => 'OrderPlaced' === ( $event['event_name'] ?? '' ) && (string) $order->get_id() === (string) ( $event['custom_data']['order_id'] ?? '' ) ) );
		self::assert( 'orderplaced-worker-replay-once', 1 === count( $placed ), 'Two executions of the queue yield one locally intercepted OrderPlaced.' );
		$event = $placed[0];
		self::assert( 'orderplaced-value-currency', (float) $order->get_total() === (float) ( $event['custom_data']['value'] ?? -1 ) && 'TND' === ( $event['custom_data']['currency'] ?? '' ) && '' !== (string) ( $event['event_id'] ?? '' ), 'OrderPlaced value is the real order total, currency TND, with an event_id.' );
		self::assert( 'orderplaced-ph-normalized', hash( 'sha256', '21620123456' ) === ( $event['user_data']['ph'] ?? '' ), 'ph = sha256(21620123456) whatever the typed format.' );
		if ( $cookies ) {
			self::assert( 'orderplaced-fbc-fbp', 'fb.1.1760000000000.IwFixtureClickIdLocalOnly' === ( $event['user_data']['fbc'] ?? '' ) && 'fb.1.1760000000000.1234567890' === ( $event['user_data']['fbp'] ?? '' ), 'fbc and fbp from the checkout cookies are sent.' );
		}
		// Lot 5 (D7): the forced replay now writes its own « already_sent » line; only real sends carry http=.
		$lines = array_values( array_filter( self::log_lines( 'OrderPlaced', $order->get_id() ), static fn( string $line ): bool => false !== strpos( $line, ' http=' ) ) );
		self::assert( 'orderplaced-logged-once', 1 === count( $lines ) && false !== strpos( $lines[0], 'state=accepted' ) && false !== strpos( $lines[0], 'currency=TND' ) && false !== strpos( $lines[0], 'ph=oui' ) && false !== strpos( $lines[0], 'fbc=' . ( $cookies ? 'oui' : 'non' ) ), 'One famma-capi journal line per send, with state, currency and signal presence: ' . implode( ' | ', self::$logs ) );
		foreach ( self::$logs as $line ) {
			self::assert( 'log-without-token', false === strpos( $line, 'INTEGRATION_FAKE_TOKEN' ) && false === strpos( $line, '20123456' ) && false === strpos( $line, 'IwFixtureClickId' ), 'The journal never contains the token, the phone or the cookie values.' );
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Actual FAMMA action constant.
		do_action( \Famma\Core\Order_Emails::ACTION, $order->get_id() );
		$first_mail = self::$mail;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Actual FAMMA action constant.
		do_action( \Famma\Core\Order_Emails::ACTION, $order->get_id() );
		self::assert( 'email-worker-replay-once', $first_mail > 0 && $first_mail === self::$mail, 'Email worker replay adds no second mail; all attempts remain locally intercepted.' );
	}

	/**
	 * Journal lines (famma-capi) of one event for one order.
	 *
	 * @param string $event Event name.
	 * @param int    $order_id Order ID.
	 * @return array Matching lines.
	 */
	private static function log_lines( string $event, int $order_id ): array {
		return array_values( array_filter( self::$logs, static fn( string $line ): bool => str_starts_with( $line, $event . ' order=' . $order_id . ' ' ) ) );
	}

	/**
	 * Keep famma-capi log lines (first handler only); the file handler still writes them.
	 *
	 * @param mixed  $message Log message.
	 * @param string $level Level.
	 * @param array  $context Context.
	 * @param object $handler Log handler.
	 * @return mixed Unchanged message.
	 */
	public static function capture_log( $message, $level, $context, $handler ) {
		unset( $level );
		$id = get_class( $handler );
		if ( '' === self::$log_handler ) {
			self::$log_handler = $id;
		}
		if ( $id === self::$log_handler && 'famma-capi' === ( $context['source'] ?? '' ) ) {
			self::$logs[] = (string) $message;
		}
		return $message;
	}

	/**
	 * Record cart cookies instead of emitting headers.
	 *
	 * @param bool   $enabled Whether to set the cookie.
	 * @param string $name Cookie name.
	 * @param mixed  $value Cookie value.
	 * @param int    $expire Expiry timestamp.
	 * @return bool Unchanged decision.
	 */
	public static function capture_cookie( $enabled, $name, $value, $expire ) {
		if ( in_array( $name, array( 'woocommerce_items_in_cart', 'woocommerce_cart_hash' ), true ) ) {
			self::$set_cookies[ $name ] = array( (string) $value, (int) $expire );
		}
		return $enabled;
	}

	/**
	 * Cart cookie as the browser holds it once the response headers are applied.
	 *
	 * @param string $name Cookie name.
	 * @param string $sent Value the browser sent with the request ('' when absent).
	 * @return string Value kept by the browser, '' when absent or expired.
	 */
	private static function browser_cookie( string $name, string $sent ): string {
		if ( ! isset( self::$set_cookies[ $name ] ) ) {
			return $sent;
		}
		list( $value, $expire ) = self::$set_cookies[ $name ];
		return ( 0 !== $expire && $expire < time() ) ? '' : $value;
	}

	/**
	 * Raise only the per-phone and per-IP order ceilings for synthetic orders.
	 *
	 * @param array $limits Current limits.
	 * @return array Test limits.
	 */
	public static function test_limits( array $limits ): array {
		$limits['orders_per_phone_day'] = 100;
		$limits['orders_per_ip_hour']   = 100;
		return $limits;
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
		$page = (int) ( self::$context['terms_page'] ?? 0 );
		if ( $page && get_post_meta( $page, '_famma_integration_run', true ) === self::$run ) {
			wp_delete_post( $page, true ); }
		$coupon_id = (int) ( self::$context['coupon'] ?? 0 );
		if ( $coupon_id && get_post_meta( $coupon_id, '_famma_integration_run', true ) === self::$run ) {
			wp_delete_post( $coupon_id, true ); }
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
