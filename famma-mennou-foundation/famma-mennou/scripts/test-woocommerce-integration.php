<?php
/**
 * Real WC_Checkout integration scenarios. Isolated clone only; never production.
 * Run sequentially with other integration suites:
 * FAMMA_WOO_INTEGRATION=1 wp eval-file scripts/test-woocommerce-integration.php
 * This is a PHP checkout POST simulation, not a browser/cookie/mobile test.
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Standalone WP-CLI eval-file suite, not an autoloaded production class file.
// phpcs:disable Generic.Files.OneObjectStructurePerFile -- Suite and its private termination signal intentionally share this standalone test file.
defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_WOO_INTEGRATION' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'WP-CLI and explicit isolated-clone opt-in required.' );
}
$famma_woo_database = (string) getenv( 'FAMMA_TEST_DB_NAME' );
if ( '' === $famma_woo_database || ! defined( 'DB_NAME' ) || ! hash_equals( $famma_woo_database, (string) DB_NAME ) ) {
	throw new RuntimeException( 'Explicit FAMMA_TEST_DB_NAME must match this isolated clone DB_NAME before writes.' );
}
$famma_woo_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
if ( ! in_array( $famma_woo_host, array( 'localhost', '127.0.0.1', '::1' ), true ) && ! str_ends_with( $famma_woo_host, '.test' ) ) {
	throw new RuntimeException( 'Refusing writes outside localhost, loopback or .test clone.' );
}
if ( ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! class_exists( '\Famma\Core\Order_Statuses' ) || ! class_exists( 'WC_Checkout' ) ) {
	throw new RuntimeException( 'Local/development active WordPress, WooCommerce and FAMMA required.' );
}
foreach ( array( 'FAMMA_META_ACCESS_TOKEN', 'FAMMA_META_PIXEL_ID', 'FAMMA_META_TEST_EVENT_CODE' ) as $famma_woo_constant ) {
	if ( defined( $famma_woo_constant ) && '' !== (string) constant( $famma_woo_constant ) ) {
		throw new RuntimeException( 'Clear clone tracking constants first; their values are never printed.' );
	}
}
if ( '' !== (string) get_option( 'famma_meta_access_token', '' ) ) {
	throw new RuntimeException( 'Refusing an existing CAPI token; clone must have empty credentials.' );
}

/** Bypasses WC_Checkout's Exception catch solely to intercept wp_die after JSON. */
final class Famma_Woo_Json_Stop extends Error {}

/** Runs and cleans isolated real WooCommerce scenarios. */
final class Famma_Woo_Integration {
	/**
	 * Fixture product identifiers.
	 *
	 * @var array<int,int>
	 */
	private static array $products = array();
	/**
	 * Fixture order identifiers.
	 *
	 * @var array<int,int>
	 */
	private static array $orders = array();
	/**
	 * Options restored after the suite.
	 *
	 * @var array<string,mixed>
	 */
	private static array $options = array();
	/**
	 * Assertion observations.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private static array $assertions = array();
	/**
	 * Suite anti-abuse transients.
	 *
	 * @var array<int,string>
	 */
	private static array $transients = array();
	/**
	 * Fixture ownership marker.
	 *
	 * @var string
	 */
	private static string $run = '';
	/**
	 * Intercepted HTTP attempts.
	 *
	 * @var int
	 */
	private static int $http = 0;
	/**
	 * Intercepted mail attempts.
	 *
	 * @var int
	 */
	private static int $mail = 0;
	/**
	 * Whether fixture ownership capture is active.
	 *
	 * @var bool
	 */
	private static bool $record = false;
	/**
	 * Original user identifier.
	 *
	 * @var int
	 */
	private static int $old_user = 0;
	/**
	 * Original posted request state.
	 *
	 * @var array<string,mixed>
	 */
	private static array $old_post = array();
	/**
	 * Original merged request state.
	 *
	 * @var array<string,mixed>
	 */
	private static array $old_request = array();
	/**
	 * Original server state.
	 *
	 * @var array<string,mixed>
	 */
	private static array $old_server = array();

	/**
	 * Execute scenarios and always remove run-owned fixtures.
	 *
	 * @throws RuntimeException When fixture preconditions or assertions fail.
	 */
	public static function run(): void {
		self::$run      = 'woo-integration-' . wp_generate_uuid4();
		self::$old_user = get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve original request state in opt-in WP-CLI harness; no submitted data is trusted.
		self::$old_post = $_POST;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve original request state; real checkout verifies the synthetic nonce later.
		self::$old_request = $_REQUEST;
		self::$old_server  = $_SERVER;
		add_filter( 'pre_http_request', array( self::class, 'http' ), PHP_INT_MAX, 3 );
		add_filter( 'pre_wp_mail', array( self::class, 'mail' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_die_ajax_handler', array( self::class, 'die_handler' ), PHP_INT_MAX );
		add_filter( 'woocommerce_available_payment_gateways', array( self::class, 'cod_only' ), PHP_INT_MAX );
		add_action( 'woocommerce_checkout_order_created', array( self::class, 'capture_order' ), PHP_INT_MAX );
		add_action( 'set_transient', array( self::class, 'capture_transient' ), PHP_INT_MAX, 3 );
		try {
			foreach ( array( 'famma_meta_orderplaced_enabled', 'woocommerce_enable_guest_checkout', 'woocommerce_enable_signup_and_login_from_checkout', 'woocommerce_cod_settings' ) as $name ) {
				self::$options[ $name ] = get_option( $name, null );
			}
			update_option( 'famma_meta_orderplaced_enabled', false );
			update_option( 'woocommerce_enable_guest_checkout', 'yes' );
			update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );
			$cod                       = (array) get_option( 'woocommerce_cod_settings', array() );
			$cod['enabled']            = 'yes';
			$cod['enable_for_virtual'] = 'yes';
			$cod['enable_for_methods'] = array();
			update_option( 'woocommerce_cod_settings', $cod );
			wp_set_current_user( 0 );
			wc_maybe_define_constant( 'DOING_AJAX', true );
			wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
			if ( ! wp_doing_ajax() ) {
				throw new RuntimeException( 'AJAX response interception unavailable: refusing fixtures before checkout can exit directly.' ); }
			if ( ! WC()->session ) {
				WC()->initialize_session(); }
			if ( ! WC()->cart ) {
				wc_load_cart(); }
			WC()->session->set( 'order_awaiting_payment', false );
			WC()->customer->set_billing_country( 'TN' );
			WC()->customer->set_shipping_country( 'TN' );
			self::$record = true;
			$p            = self::product( 79, 12 );
			$q            = self::product( 42, 12 );

			self::cart( array( array( $p, 2 ), array( $q, 1 ) ) );
			$bad = self::checkout( array( 'billing_phone' => '123' ) );
			self::check( 'invalid-phone', 'failure' === ( $bad['result'] ?? '' ) && 0 === count( self::$orders ), 'Real checkout rejects invalid phone without creating order.' );
			$bad = self::checkout( array( 'billing_first_name' => '' ) );
			self::check( 'missing-name', 'failure' === ( $bad['result'] ?? '' ) && 0 === count( self::$orders ), 'Required name rejected by WooCommerce.' );
			$bad = self::checkout( array( 'billing_state' => 'INVALID' ) );
			self::check( 'invalid-state', 'failure' === ( $bad['result'] ?? '' ) && 0 === count( self::$orders ), 'Unknown governorate rejected by WooCommerce.' );
			$bad = self::checkout( array( 'famma_hp_site' => 'robot' ) );
			self::check( 'honeypot', 'failure' === ( $bad['result'] ?? '' ) && 0 === count( self::$orders ), 'Real checkout invokes FAMMA anti-abuse validation.' );
			$bad = self::checkout( array( 'woocommerce-process-checkout-nonce' => 'expired' ) );
			self::check( 'expired-nonce', 'failure' === ( $bad['result'] ?? '' ) && 0 === count( self::$orders ), 'Core nonce failure cannot create order.' );
			$ok = self::checkout();
			if ( 'success' !== ( $ok['result'] ?? '' ) ) {
				WP_CLI::line( 'LOCAL CHECKOUT DIAGNOSTIC: ' . wp_json_encode( $ok ) );
			}
			self::check( 'checkout-success', 'success' === ( $ok['result'] ?? '' ) && 1 === count( self::$orders ), 'Real posted checkout plus COD gateway creates one order.' );
			$order = wc_get_order( self::$orders[0] );
			self::check( 'initial-cod', $order->has_status( 'pending-confirm' ) && ! $order->is_paid() && ! $order->get_date_paid(), 'Initial COD unpaid and pending confirmation.' );
			self::check( 'multiline-total', 2 === count( $order->get_items() ) && 200.0 === (float) $order->get_total(), 'Virtual two-line cart total 79x2+42 equals 200 TND.' );
			self::check( 'billing', 'TN' === $order->get_billing_country() && 'TN-61' === $order->get_billing_state() && 'Ben Test' === $order->get_billing_last_name(), 'Actual Woo order receives FAMMA country/governorate/name fields.' );
			self::check( 'pending-stock', 12 === (int) wc_get_product( $p->get_id() )->get_stock_quantity(), 'Unconfirmed order has not yet reduced physical stock count.' );
			$order->update_status( 'confirmed' );
			self::check( 'confirmed-stock', 10 === (int) wc_get_product( $p->get_id() )->get_stock_quantity() && 11 === (int) wc_get_product( $q->get_id() )->get_stock_quantity(), 'Confirmation reduces both real Woo product quantities.' );
			do_action( 'woocommerce_order_status_confirmed', $order->get_id() );
			self::check( 'stock-idempotency', 10 === (int) wc_get_product( $p->get_id() )->get_stock_quantity(), 'Repeated reserve hook does not reduce twice.' );
			$order->update_status( 'shipped' );
			self::check( 'shipped-unpaid', ! wc_get_order( $order->get_id() )->is_paid(), 'Shipping alone is not cash revenue.' );
			$order->update_status( 'delivered' );
			$order = wc_get_order( $order->get_id() );
			self::check( 'delivered-paid', $order->has_status( 'delivered' ) && $order->is_paid() && null !== $order->get_date_paid() && 'yes' === $order->get_meta( '_famma_cod_paid' ), 'Delivered collects COD while retaining custom status.' );
			do_action( 'woocommerce_order_status_delivered', $order->get_id() );
			self::check( 'delivery-stock-once', 10 === (int) wc_get_product( $p->get_id() )->get_stock_quantity(), 'Repeated delivered hook cannot double-decrement stock.' );
			$order->update_status( 'returned' );
			self::check( 'returned-stock', 12 === (int) wc_get_product( $p->get_id() )->get_stock_quantity() && 12 === (int) wc_get_product( $q->get_id() )->get_stock_quantity(), 'Returned restores real stock.' );
			self::$assertions[] = array(
				'name'                 => 'return-financial-observation',
				'passed'               => null,
				'refunded_total'       => (float) $order->get_total_refunded(),
				'paid_marker_retained' => 'yes' === $order->get_meta( '_famma_cod_paid' ),
				'message'              => 'Observation: logistic returned status is not assumed to perform a cash refund.',
			);

			foreach ( array( 'cancelled', 'refused', 'failed-delivery' ) as $status ) {
				self::cart( array( array( $p, 1 ) ) );
				$before            = count( self::$orders );
				$result            = self::checkout();
				$creation_scenario = 'new-' . $status;
				self::check( $creation_scenario, 'success' === ( $result['result'] ?? '' ) && 1 === ( count( self::$orders ) - $before ), 'Real checkout creates an independent outcome fixture.' );
				$outcome = wc_get_order( self::$orders[ $before ] );
				$outcome->update_status( 'confirmed' );
				$outcome->update_status( $status );
				do_action( 'woocommerce_order_status_' . $status, $outcome->get_id() );
				$release_scenario = 'release-' . $status;
				self::check( $release_scenario, 12 === (int) wc_get_product( $p->get_id() )->get_stock_quantity() && ! wc_get_order( $outcome->get_id() )->is_paid(), 'Outcome restores stock once and remains unpaid.' );
			}

			// Endpoint replay is observed rather than silently assuming uniqueness.
			self::cart( array( array( $p, 1 ) ) );
			$before             = count( self::$orders );
			$replay             = array( 'billing_phone' => '20999999' );
			$first              = self::checkout( $replay, true );
			$second             = self::checkout( $replay, true );
			self::$assertions[] = array(
				'name'            => 'sequential-express-replay',
				'passed'          => null,
				'new_order_count' => count( self::$orders ) - $before,
				'first_result'    => $first['result'] ?? 'missing',
				'second_result'   => $second['result'] ?? 'missing',
				'message'         => 'Real repeated PHP endpoint calls; two new orders indicate missing submission idempotency. Not simultaneous HTTP requests.',
			);
			// Last-unit sequence observes sequential availability, never claims SQL concurrency.
			$last_unit = self::product( 19, 1 );
			self::cart( array( array( $last_unit, 1 ) ) );
			$last_before = count( self::$orders );
			$last_first  = self::checkout();
			$last_second = array( 'result' => 'not-added' );
			WC()->cart->empty_cart();
			WC()->session->set( 'order_awaiting_payment', false );
			if ( WC()->cart->add_to_cart( $last_unit->get_id(), 1 ) ) {
				WC()->cart->calculate_totals();
				$last_second = self::checkout();
			}
			$last_orders    = array_slice( self::$orders, $last_before );
			$last_confirmed = 0;
			foreach ( $last_orders as $last_id ) {
				$last_order = wc_get_order( $last_id );
				$last_order->update_status( 'confirmed' );
				if ( wc_get_order( $last_id )->has_status( 'confirmed' ) ) {
					++$last_confirmed;
				}
			}
			self::$assertions[] = array(
				'name'             => 'last-unit-sequential-observation',
				'passed'           => null,
				'initial_stock'    => 1,
				'first_result'     => $last_first['result'] ?? 'missing',
				'second_result'    => $last_second['result'] ?? 'missing',
				'new_order_count'  => count( $last_orders ),
				'confirmed_orders' => $last_confirmed,
				'final_stock'      => wc_get_product( $last_unit->get_id() )->get_stock_quantity(),
				'message'          => 'Two sequential checkouts before confirmation, then sequential confirmations; not a parallel database race.',
			);
			self::check( 'no-external-transport', 0 === self::$http, 'Empty CAPI credential prevents all HTTP; remaining external attempts would be blocked.' );
		} finally {
			self::cleanup();
		}
		self::check( 'cleanup-orders', ! array_filter( self::$orders, 'wc_get_order' ), 'All run-owned real orders deleted.' );
		self::check( 'cleanup-products', ! array_filter( self::$products, 'wc_get_product' ), 'All run-owned real products deleted.' );
		WP_CLI::line(
			wp_json_encode(
				array(
					'suite'            => 'real-wp-wc-checkout',
					'run_id'           => self::$run,
					'wordpress'        => get_bloginfo( 'version' ),
					'woocommerce'      => WC_VERSION,
					'assertions'       => self::$assertions,
					'mail_intercepted' => self::$mail,
					'http_intercepted' => self::$http,
					'limitations'      => array( 'virtual products: no shipping-zone calculation', 'PHP POST simulation: no browser/session-cookie/UI/network concurrency' ),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
			)
		);
	}

	/**
	 * Create a suite-owned stocked virtual product.
	 *
	 * @param int $price Fixture price in TND.
	 * @param int $stock Initial inventory count.
	 * @return WC_Product_Simple Fixture object.
	 */
	private static function product( int $price, int $stock ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( '[TEST LOCAL] Woo integration ' . $price );
		$product->set_status( 'publish' );
		$product->set_virtual( true );
		$product->set_tax_status( 'none' );
		$product->set_regular_price( (string) $price );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $stock );
		$product->set_backorders( 'no' );
		$product->update_meta_data( '_famma_integration_run', self::$run );
		$product->save();
		self::$products[] = $product->get_id();
		return $product;
	}

	/**
	 * Prepare a real cart without existing coupons or lines.
	 *
	 * @param array $lines Product and quantity pairs.
	 * @throws RuntimeException When a fixture cannot be added.
	 */
	private static function cart( array $lines ): void {
		WC()->cart->empty_cart();
		WC()->session->set( 'order_awaiting_payment', false );
		foreach ( $lines as $line ) {
			if ( ! WC()->cart->add_to_cart( $line[0]->get_id(), $line[1] ) ) {
				throw new RuntimeException( 'Fixture add-to-cart failed.' ); }
		}
		WC()->cart->calculate_totals();
	}

	/**
	 * Invoke the real checkout with synthetic request fields.
	 *
	 * @param array $changes Explicit fixture field overrides.
	 * @param bool  $express Whether to use the FAMMA express endpoint.
	 * @return array Real endpoint response.
	 * @throws RuntimeException When interception or response decoding fails.
	 */
	private static function checkout( array $changes = array(), bool $express = false ): array {
		$_SERVER['REMOTE_ADDR']     = '192.0.2.' . ( count( self::$orders ) + 20 );
		$_SERVER['HTTP_USER_AGENT'] = 'FAMMA isolated integration';
		$_POST                      = array_merge(
			array(
				'billing_first_name'                 => 'Synthetic Ben Test',
				'billing_phone'                      => '20000' . sprintf( '%03d', count( self::$orders ) + 20 ),
				'billing_state'                      => 'TN-61',
				'billing_country'                    => 'TN',
				'billing_address_1'                  => '[TEST LOCAL] address',
				'billing_city'                       => '',
				'billing_email'                      => '',
				'payment_method'                     => 'cod',
				'terms'                              => '1',
				'terms-field'                        => '1',
				'ship_to_different_address'          => '0',
				'woocommerce-process-checkout-nonce' => wp_create_nonce( 'woocommerce-process_checkout' ),
				'famma_hp_site'                      => '',
				'famma_product_id'                   => self::$products[0],
				'famma_quantity'                     => '1',
			),
			$changes
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Synthetic fixture assignment; core reads REQUEST and verifies its nonce.
		$_REQUEST = $_POST;
		wc_clear_notices();
		ob_start();
		$stopped = false;
		try {
			if ( $express ) {
				\Famma\Core\Express_Checkout::instance()->handle();
			} else {
				WC()->checkout()->process_checkout(); }
		} catch ( Famma_Woo_Json_Stop $stop ) {
			// JSON printed by the real WooCommerce endpoint is captured below.
			$stopped = true;
		} finally {
			$output = ob_get_clean();
		}
		$result = json_decode( (string) $output, true );
		if ( ! $stopped || ! is_array( $result ) ) {
			throw new RuntimeException( 'Checkout response was not JSON; inspect isolated output without exposing customer data.' ); }
		return $result;
	}

	/**
	 * Intercept AJAX termination without exiting the fixture cleanup.
	 *
	 * @param callable $handler Original AJAX handler, deliberately replaced.
	 * @return callable Interception handler.
	 */
	public static function die_handler( $handler ): callable {
		unset( $handler );
		return static function (): void {
			throw new Famma_Woo_Json_Stop( 'Intercepted JSON termination.' );
		}; }
	/**
	 * Block every outbound WordPress HTTP request.
	 *
	 * @param mixed  $preempt Prior preemption, deliberately replaced.
	 * @param array  $args Request arguments, deliberately not logged.
	 * @param string $url Request URL, deliberately not logged.
	 * @return WP_Error Local rejection.
	 */
	public static function http( $preempt, array $args, string $url ): WP_Error {
		unset( $preempt, $args, $url );
		++self::$http;
		return new WP_Error( 'famma_test_network_blocked', 'All HTTP blocked in isolated suite.' ); }
	/**
	 * Neutralize WordPress mail delivery.
	 *
	 * @param mixed $preempt Prior result, deliberately replaced.
	 * @param array $atts Message attributes, deliberately not logged.
	 * @return bool Synthetic accepted result.
	 */
	public static function mail( $preempt, array $atts ): bool {
		unset( $preempt, $atts );
		++self::$mail;
		return true; }
	/**
	 * Supply only the real COD gateway for virtual fixtures.
	 *
	 * @param array $gateways Existing eligible gateways, deliberately replaced.
	 * @return array Real COD gateway.
	 * @throws RuntimeException When the COD gateway is absent.
	 */
	public static function cod_only( array $gateways ): array {
		unset( $gateways );
		$all = WC()->payment_gateways()->payment_gateways();
		if ( ! isset( $all['cod'] ) ) {
			throw new RuntimeException( 'COD gateway missing.' ); }
		return array( 'cod' => $all['cod'] );
	}
	/**
	 * Mark a checkout-created fixture for exact cleanup.
	 *
	 * @param WC_Order $order Actual newly created order.
	 */
	public static function capture_order( WC_Order $order ): void {
		if ( ! self::$record ) {
			return; }
		$order->update_meta_data( '_famma_integration_run', self::$run );
		$order->save();
		self::$orders[] = $order->get_id();
	}
	/**
	 * Remember only anti-abuse counters touched by this suite.
	 *
	 * @param string $name Transient identifier.
	 * @param mixed  $value Counter payload, deliberately not retained.
	 * @param int    $expiration Expiration, deliberately not retained.
	 */
	public static function capture_transient( string $name, $value, int $expiration ): void {
		unset( $value, $expiration );
		if ( self::$record && str_starts_with( $name, 'famma_ab_' ) ) {
			self::$transients[] = $name; }
	}
	/**
	 * Record a meaningful assertion and stop on failure.
	 *
	 * @param string $name Scenario key.
	 * @param bool   $passed Whether the expected observation held.
	 * @param string $message Assertion description.
	 * @throws RuntimeException When the assertion fails.
	 */
	private static function check( string $name, bool $passed, string $message ): void {
		self::$assertions[] = array(
			'name'    => $name,
			'passed'  => $passed,
			'message' => $message,
		);
		if ( ! $passed ) {
			throw new RuntimeException( esc_html( $name . ': ' . $message ) ); }
	}
	/** Remove run-owned fixtures, tasks and counters, then restore options. */
	private static function cleanup(): void {
		self::$record = false;
		if ( WC()->cart ) {
			WC()->cart->empty_cart(); }
		foreach ( self::$orders as $id ) {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( 'famma_capi_orderplaced_send', array( 'order_id' => $id ), 'famma-capi' );
				as_unschedule_all_actions( \Famma\Core\Order_Emails::ACTION, array( $id ), \Famma\Core\Order_Emails::GROUP );
			}
			$order = wc_get_order( $id );
			if ( $order && self::$run === $order->get_meta( '_famma_integration_run' ) ) {
				$order->delete( true ); }
		}
		foreach ( self::$products as $id ) {
			$product = wc_get_product( $id );
			if ( $product && self::$run === $product->get_meta( '_famma_integration_run' ) ) {
				$product->delete( true ); }
		}
		foreach ( array_unique( self::$transients ) as $name ) {
			delete_transient( $name ); }
		foreach ( self::$options as $name => $value ) {
			if ( null === $value ) {
				delete_option( $name );
			} else {
				update_option( $name, $value ); }
		}
		wp_set_current_user( self::$old_user );
		$_POST    = self::$old_post;
		$_REQUEST = self::$old_request;
		$_SERVER  = self::$old_server;
		remove_filter( 'woocommerce_available_payment_gateways', array( self::class, 'cod_only' ), PHP_INT_MAX );
		remove_filter( 'wp_die_ajax_handler', array( self::class, 'die_handler' ), PHP_INT_MAX );
		remove_action( 'woocommerce_checkout_order_created', array( self::class, 'capture_order' ), PHP_INT_MAX );
		remove_action( 'set_transient', array( self::class, 'capture_transient' ), PHP_INT_MAX );
		// Leave transport interception until the PHP process exits: no shutdown email/HTTP escapes.
	}
}

Famma_Woo_Integration::run();
