<?php
/**
 * Actual administration test sender and WooCommerce monetary payload checks.
 *
 * @package FammaIntegration
 */

defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_META_VALUE_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || 'famma_integration' !== DB_NAME || 'db' !== DB_HOST || 'local' !== wp_get_environment_type() || 'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'Explicit disposable clone identity required.' );
}
foreach ( array( 'FAMMA_META_PIXEL_ID', 'FAMMA_META_ACCESS_TOKEN', 'FAMMA_META_TEST_EVENT_CODE' ) as $famma_constant ) {
	if ( defined( $famma_constant ) && '' !== (string) constant( $famma_constant ) ) {
		throw new RuntimeException( 'Empty tracking constants required.' );
	}
}
if ( '' !== get_option( 'famma_meta_access_token', '' ) ) {
	throw new RuntimeException( 'Stored token must remain empty.' );
}
// Lot 5 (D4): outside production famma-core sends nothing to Meta. This disposable clone
// already intercepts every outgoing request, so the suite lifts the guard for its own process.
add_filter( 'famma_meta_sending_allowed', '__return_true' );

/** Stop only the native admin redirect before its unconditional exit. */
final class Famma_Meta_Value_Redirect extends Error {}

/** Owns monetary fixtures and intercepts every external transport. */
final class Famma_Meta_Value_Test {
	/**
	 * Captured synthetic payloads, never printed in full.
	 *
	 * @var array
	 */
	private static array $requests = array();
	/**
	 * Named actual assertions.
	 *
	 * @var array
	 */
	private static array $results = array();
	/**
	 * Fixture order identifiers.
	 *
	 * @var array
	 */
	private static array $orders = array();
	/**
	 * Fixture product identifiers.
	 *
	 * @var array
	 */
	private static array $products = array();
	/**
	 * Run ownership marker.
	 *
	 * @var string
	 */
	private static string $run = '';
	/**
	 * Actual observed new-order hook count.
	 *
	 * @var int
	 */
	private static int $created = 0;

	/**
	 * Execute guarded native admin sender and real order calculations.
	 *
	 * @throws RuntimeException When the disposable administrator is absent.
	 */
	public static function run(): void {
		self::$run = wp_generate_uuid4();
		add_filter( 'pre_http_request', array( self::class, 'http' ), PHP_INT_MAX, 3 );
		add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
		add_filter( 'pre_option_famma_meta_pixel_id', static fn(): string => '123456789', PHP_INT_MAX );
		add_filter( 'pre_option_famma_meta_access_token', static fn(): string => 'INTEGRATION_FAKE_TOKEN_NOT_A_SECRET', PHP_INT_MAX );
		add_filter( 'pre_option_famma_meta_orderplaced_enabled', '__return_true', PHP_INT_MAX );
		add_filter( 'pre_option_woocommerce_currency', static fn(): string => 'TND', PHP_INT_MAX );
		add_filter( 'wp_redirect', array( self::class, 'redirect' ), PHP_INT_MIN, 2 );
		add_action( 'woocommerce_new_order', array( self::class, 'created' ) );
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		if ( ! $admins ) {
			throw new RuntimeException( 'Disposable clone administrator not found.' );
		}
		wp_set_current_user( (int) $admins[0] );
		try {
			self::admin_tests();
			remove_filter( 'wp_redirect', array( self::class, 'redirect' ), PHP_INT_MIN );
			self::order_tests();
		} finally {
			self::cleanup();
		}
		self::check( 'fixtures-cleaned', ! array_filter( self::$orders, 'wc_get_order' ) && ! array_filter( self::$products, 'wc_get_product' ), 'All exact run-owned orders and products were deleted.' );
		WP_CLI::line(
			wp_json_encode(
				array(
					'suite'            => 'real-meta-value-integration',
					'wordpress'        => get_bloginfo( 'version' ),
					'woocommerce'      => WC_VERSION,
					'plugin'           => FAMMA_CORE_VERSION,
					'run'              => self::$run,
					'assertions'       => self::$results,
					'http_intercepted' => count( self::$requests ),
					'external_http'    => 0,
				),
				JSON_PRETTY_PRINT
			)
		);
	}

	/** Check accepted positive inputs and rejected blank, invalid and ambiguous inputs. */
	private static function admin_tests(): void {
		$created = self::$created;
		foreach ( array( 'Purchase', 'OrderPlaced' ) as $event ) {
			foreach ( array( '25', '129', '25.125', '25,125' ) as $value ) {
				$before = count( self::$requests );
				self::admin( $event, $value, 'TEST12345' );
				$payload = self::$requests[ $before ] ?? array();
				$actual  = $payload['data'][0] ?? array();
				$number  = $actual['custom_data']['value'] ?? null;
				$ok      = 1 === count( self::$requests ) - $before && ( is_int( $number ) || is_float( $number ) ) && (float) str_replace( ',', '.', $value ) === (float) $number && ( $actual['event_name'] ?? '' ) === $event && 'TND' === ( $actual['custom_data']['currency'] ?? '' ) && 'TEST12345' === ( $payload['test_event_code'] ?? '' ) && str_starts_with( $actual['event_id'] ?? '', 'famma_test_' );
				self::check( 'admin-positive-' . $event . '-' . $value, $ok, 'Native admin sender uses the entered positive numeric amount, exact event and mandatory test code.' );
			}
		}
		foreach ( array(
			'blank'               => '',
			'zero'                => '0',
			'negative'            => '-1',
			'not-numeric'         => 'invalid',
			'infinite'            => 'INF',
			'nan'                 => 'NaN',
			'scientific-overflow' => '1e9999',
			'precision-exceeded'  => '25.1234',
			'mixed'               => '25.1.2',
			'array'               => array( '25' ),
		) as $case => $invalid ) {
			$before = count( self::$requests );
			self::admin( 'Purchase', $invalid, 'TEST12345' );
			$held = count( self::$requests ) === $before;
			self::check( 'admin-invalid-' . $case, $held, 'Invalid, missing, zero or non-finite test amount never reaches HTTP.' );
		}
		$before = count( self::$requests );
		self::admin( 'Purchase', '25', '' );
		self::check( 'admin-no-code-no-http', count( self::$requests ) === $before, 'A valid amount without a test code cannot be sent.' );
		// Lot 5 (A12): a test code must be TEST followed by digits; the former free format is refused.
		self::admin( 'Purchase', '25', 'TEST_LOCAL_VALUE' );
		self::check( 'admin-legacy-code-no-http', count( self::$requests ) === $before, 'A test code outside the TEST + digits format cannot be sent.' );
		self::check( 'admin-no-created-orders', self::$created === $created, 'All synthetic admin sender requests create zero WooCommerce orders.' );
	}

	/**
	 * Execute the native admin sender with real nonce and capability checks.
	 *
	 * @param string       $event Actual requested event.
	 * @param string|array $value User-entered test amount or deliberately malformed array.
	 * @param string       $code Mandatory test code.
	 * @throws RuntimeException When the native sender does not redirect.
	 */
	private static function admin( string $event, string|array $value, string $code ): void {
		$_POST = array(
			'_wpnonce'              => wp_create_nonce( 'famma_capi_test' ),
			'famma_capi_test_event' => $event,
			'famma_capi_test_code'  => $code,
			'famma_capi_test_value' => $value,
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Native sender verifies the generated nonce.
		$_REQUEST = $_POST;
		try {
			\Famma\Core\Tracking_Settings::instance()->send_test_event();
		} catch ( Famma_Meta_Value_Redirect $stop ) {
			return;
		}
		throw new RuntimeException( 'Native sender did not terminate through its redirect.' );
	}

	/**
	 * Construct two real baskets and a genuine WooCommerce line discount.
	 *
	 * @throws RuntimeException When actual fixture order creation fails.
	 */
	private static function order_tests(): void {
		foreach ( array( '79', '49' ) as $price ) {
			$product = new WC_Product_Simple();
			$product->set_name( '[TEST LOCAL] Monetary payload ' . $price );
			$product->set_status( 'private' );
			$product->set_virtual( true );
			$product->set_tax_status( 'none' );
			$product->set_regular_price( $price );
			$product->update_meta_data( '_famma_meta_value_run', self::$run );
			$product->save();
			self::$products[] = $product->get_id();
		}
		foreach ( array( array( 'single', 1, false, 79.0 ), array( 'mixed-quantity-two', 2, false, 207.0 ), array( 'mixed-with-discount', 2, true, 197.0 ) ) as $scenario ) {
			$order = wc_create_order(
				array(
					'created_via' => 'checkout',
					'status'      => 'pending-confirm',
				)
			);
			if ( ! $order instanceof WC_Order ) {
				throw new RuntimeException( 'Actual fixture order creation failed.' );
			}
			self::$orders[] = $order->get_id();
			$order->update_meta_data( '_famma_meta_value_run', self::$run );
			$order->set_currency( 'TND' );
			$order->set_payment_method( 'cod' );
			$order->set_billing_email( 'synthetic@example.invalid' );
			$order->set_billing_phone( '+21620000000' );
			$order->set_billing_country( 'TN' );
			$order->add_product( wc_get_product( self::$products[0] ), $scenario[1] );
			if ( 2 === $scenario[1] ) {
				$order->add_product( wc_get_product( self::$products[1] ), 1 );
			}
			if ( $scenario[2] ) {
				$items = $order->get_items();
				$first = reset( $items );
				$first->set_total( (float) $first->get_total() - 10.0 );
				$first->save();
			}
			$order->calculate_totals();
			$order->save();
			$before = count( self::$requests );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Real WooCommerce accepted-checkout path.
			apply_filters( 'woocommerce_payment_successful_result', array( 'result' => 'success' ), $order->get_id() );
			do_action( 'famma_capi_orderplaced_send', $order->get_id() );
			$order->update_status( 'delivered', 'Synthetic local monetary integration.' );
			$events = array_slice( self::$requests, $before );
			$valid  = 2 === count( $events ) && (float) $order->get_total() === $scenario[3];
			foreach ( $events as $payload ) {
				$event = $payload['data'][0];
				$value = $event['custom_data']['value'] ?? null;
				$valid = $valid && ( is_int( $value ) || is_float( $value ) ) && (float) $value === $scenario[3] && 'TND' === ( $event['custom_data']['currency'] ?? '' ) && ! isset( $payload['test_event_code'] );
			}
			self::check( 'real-order-values-' . $scenario[0], $valid, 'Actual OrderPlaced and Purchase match the full WooCommerce total and order currency, including quantity and net discount.' );
			self::$results[] = array(
				'name'              => 'actual-basket-' . $scenario[0],
				'woocommerce_total' => (float) $order->get_total(),
				'currency'          => $order->get_currency(),
				'event_values'      => array_map( static fn( array $payload ): float => (float) $payload['data'][0]['custom_data']['value'], $events ),
			);
		}
	}

	/**
	 * Capture a genuine outgoing body without network access or secret output.
	 *
	 * @param mixed  $preempt Prior response.
	 * @param array  $args HTTP arguments.
	 * @param string $url Destination.
	 * @return array|WP_Error Local response only.
	 */
	public static function http( $preempt, array $args, string $url ) {
		unset( $preempt );
		$body = json_decode( (string) ( $args['body'] ?? '' ), true );
		if ( 'graph.facebook.com' !== wp_parse_url( $url, PHP_URL_HOST ) || 'INTEGRATION_FAKE_TOKEN_NOT_A_SECRET' !== ( $body['access_token'] ?? '' ) ) {
			return new WP_Error( 'famma_no_network', 'External HTTP blocked.' );
		}
		unset( $body['access_token'], $body['data'][0]['user_data'] );
		self::$requests[] = $body;
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"events_received":1}',
			'headers'  => array(),
			'cookies'  => array(),
		);
	}

	/**
	 * Stop the native redirect before exit; the enclosing test catches it.
	 *
	 * @param string $location Local redirect URL.
	 * @param int    $status Native redirect status.
	 * @throws Famma_Meta_Value_Redirect To regain control after the native sender.
	 */
	public static function redirect( string $location, int $status ): void {
		unset( $location, $status );
		throw new Famma_Meta_Value_Redirect( 'Native redirect captured.' );
	}

	/** Observe native order creation without persisting any additional state. */
	public static function created(): void {
		++self::$created;
	}

	/**
	 * Record a business observation and stop on failure.
	 *
	 * @param string $name Test identity.
	 * @param bool   $passed Actual observation.
	 * @param string $message Expected behavior.
	 * @throws RuntimeException On an actual failed assertion.
	 */
	private static function check( string $name, bool $passed, string $message ): void {
		self::$results[] = array(
			'name'    => $name,
			'passed'  => $passed,
			'message' => $message,
		);
		if ( ! $passed ) {
			throw new RuntimeException( esc_html( $name . ': ' . $message ) );
		}
	}

	/** Delete only products, orders and registry rows owned by this exact run. */
	private static function cleanup(): void {
		global $wpdb;
		foreach ( self::$orders as $id ) {
			$order = wc_get_order( $id );
			if ( $order && self::$run === $order->get_meta( '_famma_meta_value_run' ) ) {
				as_unschedule_all_actions( 'famma_capi_orderplaced_send', array( 'order_id' => $id ), 'famma-capi' );
				as_unschedule_all_actions( \Famma\Core\Order_Emails::ACTION, array( $id ), \Famma\Core\Order_Emails::GROUP );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact fixture-owned custom rows; Woo orders use CRUD.
				$wpdb->delete( \Famma\Core\Capi_Delivery_Registry::instance()->table(), array( 'order_id' => $id ), array( '%d' ) );
				$order->delete( true );
			}
		}
		foreach ( self::$products as $id ) {
			$product = wc_get_product( $id );
			if ( $product && self::$run === $product->get_meta( '_famma_meta_value_run' ) ) {
				$product->delete( true );
			}
		}
	}
}

Famma_Meta_Value_Test::run();
