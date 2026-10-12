<?php
/**
 * Integration scenarios on real WordPress/WooCommerce objects in an isolated clone.
 * Run with FAMMA_INTEGRATION_TEST=1, FAMMA_TEST_DB_NAME=famma_integration,
 * FAMMA_TEST_DB_HOST=db, then wp eval-file scripts/test-capi-integration.php.
 * Meta HTTP and email transports are intercepted; no real secrets are accepted.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- WP-CLI suite entry point, not an autoloaded plugin class file.

defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This suite requires WP-CLI and explicit isolated-clone opt-in.' );
}
$famma_expected_db      = (string) getenv( 'FAMMA_TEST_DB_NAME' );
$famma_expected_db_host = (string) getenv( 'FAMMA_TEST_DB_HOST' );
if ( 'local' !== wp_get_environment_type() || 'famma_integration' !== $famma_expected_db || 'db' !== $famma_expected_db_host || ! defined( 'DB_NAME' ) || DB_NAME !== $famma_expected_db || ! defined( 'DB_HOST' ) || DB_HOST !== $famma_expected_db_host ) {
	throw new RuntimeException( 'Refusing writes: expected disposable local database identity is not explicitly confirmed.' );
}
$famma_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
if ( ! in_array( $famma_host, array( 'localhost', '127.0.0.1', '::1' ), true ) && ! str_ends_with( $famma_host, '.test' ) ) {
	throw new RuntimeException( 'Refusing writes: clone URL must be localhost, loopback or a .test host.' );
}
if ( ! class_exists( '\Famma\Core\Capi' ) || ! class_exists( 'WC_Order' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
	throw new RuntimeException( 'Required active FAMMA/WooCommerce/Action Scheduler APIs are missing.' );
}
foreach ( array( 'FAMMA_META_ACCESS_TOKEN', 'FAMMA_META_PIXEL_ID', 'FAMMA_META_TEST_EVENT_CODE' ) as $famma_constant ) {
	if ( defined( $famma_constant ) && '' !== (string) constant( $famma_constant ) ) {
		throw new RuntimeException( 'Clear tracking constants in this isolated clone first; values are not disclosed.' );
	}
}
if ( '' !== (string) get_option( 'famma_meta_access_token', '' ) ) {
	throw new RuntimeException( 'Clear the stored CAPI token in this clone first; no existing token is read into requests.' );
}

/** Execute isolated integration scenarios with real WooCommerce persistence. */
final class Famma_Capi_Integration {
	/** @var array Captured synthetic requests. */
	private static array $requests       = array();
	/** @var array Identifiers of owned order fixtures. */
	private static array $orders         = array();
	/** @var array Identifiers of owned product fixtures. */
	private static array $products       = array();
	/** @var array Original option values. */
	private static array $options        = array();
	/** @var array Named assertion outcomes. */
	private static array $assertions     = array();
	/** @var array Fixed cleanup summaries without secrets. */
	private static array $cleanup_errors = array();
	/** @var array Current synthetic HTTP response. */
	private static array $response       = array(
		'response' => array(
			'code'    => 200,
			'message' => 'Synthetic success',
		),
		'body'     => '{"events_received":1}',
		'headers'  => array(),
		'cookies'  => array(),
	);
	/** @var string Unique fixture marker. */
	private static string $run_id        = '';

	/**
	 * Execute scenarios, then restore options and delete owned fixtures.
	 */
	public static function run(): void {
		self::$run_id = 'capi-integration-' . wp_generate_uuid4();
		add_filter( 'pre_http_request', array( self::class, 'intercept' ), PHP_INT_MAX, 3 );
		add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
		try {
			foreach ( array( 'famma_meta_pixel_id', 'famma_meta_access_token', 'famma_meta_test_event_code', 'famma_meta_orderplaced_enabled' ) as $name ) {
				self::$options[ $name ] = get_option( $name, null );
			}
			update_option( 'famma_meta_pixel_id', '123456789' );
			update_option( 'famma_meta_access_token', 'INTEGRATION_FAKE_TOKEN_NOT_A_SECRET', false );
			update_option( 'famma_meta_test_event_code', 'TEST_FAKE_INTEGRATION' );
			update_option( 'famma_meta_orderplaced_enabled', false );
			$product = new WC_Product_Simple();
			$product->set_name( '[TEST LOCAL] CAPI integration fixture' );
			$product->set_status( 'private' );
			$product->set_regular_price( '79' );
			$product->set_virtual( true );
			$product->update_meta_data( '_famma_integration_run', self::$run_id );
			$product->save();
			self::$products[] = $product->get_id();
			$order            = self::order( $product );
			$success          = array(
				'result'   => 'success',
				'redirect' => home_url( '/integration-thanks/' ),
			);
			$result           = apply_filters( 'woocommerce_payment_successful_result', $success, $order->get_id() );
			self::check( 'off', $result === $success && 0 === self::pending_count( $order->get_id() ) && 0 === count( self::$requests ), 'OFF: no task or HTTP; successful gateway result unchanged.' );
			update_option( 'famma_meta_orderplaced_enabled', true );
			apply_filters( 'woocommerce_payment_successful_result', array( 'result' => 'failure' ), $order->get_id() );
			self::check( 'failed-checkout', 0 === self::pending_count( $order->get_id() ), 'Failed checkout cannot enqueue.' );
			$admin = self::order( $product, 'admin' );
			apply_filters( 'woocommerce_payment_successful_result', $success, $admin->get_id() );
			self::check( 'admin-source', 0 === self::pending_count( $admin->get_id() ), 'Admin-created order excluded.' );
			apply_filters( 'woocommerce_payment_successful_result', $success, $order->get_id() );
			apply_filters( 'woocommerce_payment_successful_result', $success, $order->get_id() );
			self::check( 'async-unique', 1 === self::pending_count( $order->get_id() ) && 0 === count( self::$requests ), 'Real Action Scheduler contains one task, no synchronous HTTP.' );
			self::run_action( $order->get_id() );
			$placed = self::$requests[0]['payload']['data'][0] ?? array();
			$stored = wc_get_order( $order->get_id() );
			self::check( 'orderplaced', 'OrderPlaced' === ( $placed['event_name'] ?? '' ) && (bool) $stored->get_meta( '_famma_orderplaced_sent' ), 'Worker executes real hook and persists acknowledged OrderPlaced.' );
			self::check( 'creation-time', $stored->get_date_created()->getTimestamp() === ( $placed['event_time'] ?? 0 ), 'OrderPlaced time equals real order creation time.' );
			self::check( 'stage-and-value', 'order_placed' === ( $placed['custom_data']['famma_stage'] ?? '' ) && 79.0 === (float) ( $placed['custom_data']['value'] ?? -1 ), 'OrderPlaced business stage and actual WooCommerce total preserved.' );
			self::check( 'normalization', hash( 'sha256', 'synthetic@example.invalid' ) === ( $placed['user_data']['em'] ?? '' ) && hash( 'sha256', '21620000000' ) === ( $placed['user_data']['ph'] ?? '' ), 'Real billing getters, normalization and SHA256 work together.' );
			self::check( 'no-global-test', ! isset( self::$requests[0]['payload']['test_event_code'] ), 'Global test setting does not divert an actual lifecycle event.' );
			do_action( 'famma_capi_orderplaced_send', $order->get_id() );
			self::check( 'orderplaced-dedup', 1 === count( self::$requests ), 'Sequential duplicate worker cannot resend OrderPlaced.' );
			$before = time();
			$stored->update_status( 'delivered', 'Local integration test: simulated delivery.' );
			$paid     = wc_get_order( $order->get_id() );
			$purchase = self::$requests[1]['payload']['data'][0] ?? array();
			self::check( 'purchase-delivery', 'Purchase' === ( $purchase['event_name'] ?? '' ) && (bool) $paid->get_meta( '_famma_purchase_sent' ), 'Actual delivered transition triggers independent acknowledged Purchase.' );
			self::check( 'delivery-time', ( $purchase['event_time'] ?? 0 ) >= $before && ( $purchase['event_time'] ?? 0 ) !== $paid->get_date_created()->getTimestamp(), 'Purchase time is delivery, not the deliberately older creation date.' );
			self::check( 'cod-paid', $paid->has_status( 'delivered' ) && $paid->is_paid() && null !== $paid->get_date_paid(), 'Real COD payment hook sets paid date while retaining delivered status.' );
			self::check( 'distinct-events', ( $placed['event_id'] ?? '' ) !== ( $purchase['event_id'] ?? '' ) && ! isset( $purchase['custom_data']['famma_stage'] ), 'Event identifiers and custom-conversion stage stay separate.' );
			do_action( 'woocommerce_order_status_delivered', $paid->get_id() );
			self::check( 'purchase-dedup', 2 === count( self::$requests ), 'Repeated delivery hook cannot resend Purchase.' );
			foreach ( array( 401, 429, 503 ) as $code ) {
				$failed                             = self::order( $product );
				self::$response['response']['code'] = $code;
				self::$response['body']             = 401 === $code ? '{"error":{"code":190,"message":"Local integration rejected credential"}}' : '{"error":{"code":4,"message":"Local integration rejected request"}}';
				apply_filters( 'woocommerce_payment_successful_result', $success, $failed->get_id() );
				self::run_action( $failed->get_id() );
				$failed = wc_get_order( $failed->get_id() );
				$state = self::delivery_state( $failed );
				$expected_state = 503 === $code ? 'uncertain' : 'rejected';
				$rejected = ! $failed->get_meta( '_famma_orderplaced_sent' ) && 1 === (int) $failed->get_meta( '_famma_orderplaced_attempts' ) && $state === $expected_state;
				self::check( 'rejected-' . $code, $rejected, 'HTTP4xx with explicit Meta code is rejected; HTTP503 is uncertain, never acknowledged.' );
				$old_time                           = $failed->get_meta( '_famma_orderplaced_event_time' );
				$count_before_retry                 = count( self::$requests );
				self::$response['response']['code'] = 200;
				self::$response['body']             = '{"events_received":1}';
				do_action( 'famma_capi_orderplaced_send', $failed->get_id() );
				$retry = wc_get_order( $failed->get_id() );
				$retry_ok = 503 === $code ? ! $retry->get_meta( '_famma_orderplaced_sent' ) && count( self::$requests ) === $count_before_retry && 'uncertain' === self::delivery_state( $retry ) : (bool) $retry->get_meta( '_famma_orderplaced_sent' ) && $old_time === $retry->get_meta( '_famma_orderplaced_event_time' );
				self::check( 'retry-' . $code, $retry_ok, 'Explicit rejected requests may retry; uncertain503 is held without another HTTP request.' );
			}
			$ambiguous = self::order( $product );
			self::$response['response']['code'] = 429;
			self::$response['body'] = '{"error":{"message":"No explicit Meta rejection code"}}';
			apply_filters( 'woocommerce_payment_successful_result', $success, $ambiguous->get_id() );
			self::run_action( $ambiguous->get_id() );
			$ambiguous = wc_get_order( $ambiguous->get_id() );
			self::check( 'missing-error-code-uncertain', 'uncertain' === self::delivery_state( $ambiguous ) && ! $ambiguous->get_meta( '_famma_orderplaced_sent' ), 'HTTP429 without error.code is ambiguous and cannot be treated as an explicit rejection.' );
			$count_before_retry = count( self::$requests );
			self::$response['response']['code'] = 200;
			self::$response['body'] = '{"events_received":1}';
			do_action( 'famma_capi_orderplaced_send', $ambiguous->get_id() );
			self::check( 'missing-error-code-no-retry', count( self::$requests ) === $count_before_retry && ! wc_get_order( $ambiguous->get_id() )->get_meta( '_famma_orderplaced_sent' ), 'Uncertain request without a rejection code remains held on replay.' );
			$held = self::order( $product );
			apply_filters( 'woocommerce_payment_successful_result', $success, $held->get_id() );
			update_option( 'famma_meta_orderplaced_enabled', false );
			$count = count( self::$requests );
			self::run_action( $held->get_id() );
			self::check( 'worker-off', count( self::$requests ) === $count, 'Queued worker rechecks OFF on real options before sharing.' );
			update_option( 'famma_meta_orderplaced_enabled', true );
			$cancelled = self::order( $product );
			apply_filters( 'woocommerce_payment_successful_result', $success, $cancelled->get_id() );
			$cancelled->update_status( 'cancelled', 'Local integration cancellation.' );
			self::run_action( $cancelled->get_id() );
			self::check( 'worker-cancelled', count( self::$requests ) === $count, 'Real cancellation before task execution prevents sharing.' );
			self::check( 'secret-url', ! array_filter( self::$requests, static fn( array $request ): bool => str_contains( $request['url'], 'INTEGRATION_FAKE_TOKEN_NOT_A_SECRET' ) ), 'Synthetic token never appears in a request URL.' );
		} finally {
			self::cleanup();
		}
		self::check( 'cleanup', ! array_filter( self::$orders, 'wc_get_order' ) && ! array_filter( self::$products, 'wc_get_product' ), 'Suite-specific real orders and products were deleted.' );
		self::check( 'cleanup-errors', array() === self::$cleanup_errors, 'Cleanup completed without errors; option restoration ran independently of deletions.' );
		$options_restored = true;
		foreach ( self::$options as $name => $value ) {
			$options_restored = $options_restored && get_option( $name, null ) === $value;
		}
		self::check( 'options-restored', $options_restored, 'All tracking options match their pre-suite values.' );
		$active = 0;
		foreach ( self::$orders as $id ) {
			$active += self::pending_count( $id );
			$active += count(
				as_get_scheduled_actions(
					array(
						'hook'     => 'famma_capi_orderplaced_send',
						'args'     => array( 'order_id' => $id ),
						'group'    => 'famma-capi',
						'status'   => 'in-progress',
						'per_page' => 100,
					),
					'ids'
				)
			);
		}
		self::check( 'tasks-inactive', 0 === $active, 'No pending or in-progress CAPI task remains for fixture orders; scheduler history can remain in the disposable clone.' );
		WP_CLI::line(
			wp_json_encode(
				array(
					'suite'                => 'real-wp-wc-capi-integration',
					'run_id'               => self::$run_id,
					'wordpress'            => get_bloginfo( 'version' ),
					'woocommerce'          => WC_VERSION,
					'plugin'               => FAMMA_CORE_VERSION,
					'transport'            => 'intercepted-no-meta-network',
					'assertions'           => self::$assertions,
					'requests_intercepted' => count( self::$requests ),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
			)
		);
	}

	/**
	 * Intercept all HTTP in this isolated request.
	 *
	 * @param mixed $preempt Existing preempted response.
	 * @param array $args Request arguments.
	 * @param string $url Destination URL.
	 * @return array|WP_Error Synthetic response or transport error.
	 */
	public static function intercept( $preempt, array $args, string $url ) {
		if ( 'graph.facebook.com' === wp_parse_url( $url, PHP_URL_HOST ) ) {
			$payload = json_decode( (string) ( $args['body'] ?? '' ), true );
			if ( 'INTEGRATION_FAKE_TOKEN_NOT_A_SECRET' !== ( $payload['access_token'] ?? '' ) ) {
				throw new RuntimeException( 'Unexpected credential rejected by local interception.' );
			}
			self::$requests[] = array(
				'url'     => $url,
				'payload' => $payload,
			);
			return self::$response;
		}
		return new WP_Error( 'famma_integration_network_blocked', 'External HTTP blocked by isolated integration suite.' );
	}

	/**
	 * Create a real fixture order.
	 *
	 * @param WC_Product $product Real fixture product.
	 * @param string $source Created-via value.
	 * @return WC_Order Persisted fixture.
	 */
	private static function order( WC_Product $product, string $source = 'checkout' ): WC_Order {
		$order = wc_create_order(
			array(
				'created_via' => $source,
				'status'      => 'pending-confirm',
			)
		);
		if ( ! $order instanceof WC_Order ) {
			throw new RuntimeException( 'Fixture creation failed.' ); }
		self::$orders[] = $order->get_id();
		$order->update_meta_data( '_famma_integration_run', self::$run_id );
		$order->save();
		$order->set_payment_method( 'cod' );
		$order->set_date_created( time() - 172800 );
		$order->set_billing_email( 'synthetic@example.invalid' );
		$order->set_billing_phone( '+216 20 000 000' );
		$order->set_billing_first_name( 'Local integration' );
		$order->set_billing_last_name( 'Synthetic' );
		$order->set_billing_country( 'TN' );
		$order->add_product( $product, 1 );
		$order->calculate_totals();
		$order->save();
		return $order;
	}

	/**
	 * Count pending tasks belonging to a fixture.
	 *
	 * @param int $order_id Fixture identifier.
	 * @return int Pending task count.
	 */
	private static function pending_count( int $order_id ): int {
		return count(
			as_get_scheduled_actions(
				array(
					'hook'     => 'famma_capi_orderplaced_send',
					'args'     => array( 'order_id' => $order_id ),
					'group'    => 'famma-capi',
					'status'   => 'pending',
					'per_page' => 100,
				),
				'ids'
			)
		);
	}

	/**
	 * Execute exactly one real scheduler task.
	 *
	 * @param int $order_id Fixture identifier.
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
			throw new RuntimeException( 'Expected exactly one pending real scheduler action.' ); }
		ActionScheduler_QueueRunner::instance()->process_action( (int) $ids[0], 'famma-local-integration' );
	}

	/**
	 * Record and enforce an assertion.
	 *
	 * @param string $name Stable scenario identifier.
	 * @param bool $ok Outcome.
	 * @param string $message Plain CLI diagnostic.
	 */
	private static function check( string $name, bool $ok, string $message ): void {
		self::$assertions[] = array(
			'name'    => $name,
			'passed'  => $ok,
			'message' => $message,
		);
		if ( ! $ok ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain WP-CLI diagnostic, never rendered as HTML.
			throw new RuntimeException( $name . ': ' . $message ); }
	}

	/**
	 * Remove owned fixtures and independently restore options and filters.
	 */
	private static function cleanup(): void {
		global $wpdb;
		try {
			foreach ( self::$orders as $id ) {
				try {
					as_unschedule_all_actions( 'famma_capi_orderplaced_send', array( 'order_id' => $id ), 'famma-capi' );
					if ( class_exists( '\Famma\Core\Order_Emails' ) ) {
						as_unschedule_all_actions( \Famma\Core\Order_Emails::ACTION, array( $id ), \Famma\Core\Order_Emails::GROUP );
					}
					$order = wc_get_order( $id );
					if ( $order && self::$run_id === $order->get_meta( '_famma_integration_run' ) ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete only this suite-owned custom registry record; Woo orders use CRUD.
						$wpdb->delete( \Famma\Core\Capi_Delivery_Registry::instance()->table(), array( 'order_id' => $id ), array( '%d' ) );
						$order->delete( true ); }
				} catch ( Throwable $error ) {
					self::$cleanup_errors[] = 'order cleanup failed';
				}
			}
			foreach ( self::$products as $id ) {
				try {
					$product = wc_get_product( $id );
					if ( $product && self::$run_id === $product->get_meta( '_famma_integration_run' ) ) {
						$product->delete( true ); }
				} catch ( Throwable $error ) {
					self::$cleanup_errors[] = 'product cleanup failed';
				}
			}
		} finally {
			try {
				foreach ( self::$options as $name => $value ) {
					try {
						if ( null === $value ) {
							delete_option( $name );
						} else {
							update_option( $name, $value ); }
					} catch ( Throwable $error ) {
						self::$cleanup_errors[] = 'option restoration failed';
					}
				}
			} finally {
				remove_filter( 'pre_http_request', array( self::class, 'intercept' ), PHP_INT_MAX );
				remove_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
			}
		}
	}

	/**
	 * Read the real durable registry state for one fixture OrderPlaced event.
	 *
	 * @param WC_Order $order Exact suite-owned order.
	 * @return string Durable delivery state or empty if absent.
	 */
	private static function delivery_state( WC_Order $order ): string {
		$event_id = (string) $order->get_meta( '_famma_orderplaced_event_id' );
		$record = \Famma\Core\Capi_Delivery_Registry::instance()->read( hash( 'sha256', '123456789|OrderPlaced|' . $event_id ) );
		return (string) ( $record['state'] ?? '' );
	}
}

Famma_Capi_Integration::run();
