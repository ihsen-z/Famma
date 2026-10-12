<?php
/**
 * Real concurrent CAPI dispatch and crash recovery in the disposable clone.
 *
 * @package FammaIntegration
 */

defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_CAPI_REGISTRE_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || 'famma_integration' !== DB_NAME || 'db' !== DB_HOST || 'local' !== wp_get_environment_type() || 'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'Explicit disposable clone identity required.' );
}
foreach ( array( 'FAMMA_META_PIXEL_ID', 'FAMMA_META_ACCESS_TOKEN', 'FAMMA_META_TEST_EVENT_CODE' ) as $famma_constant ) {
	if ( defined( $famma_constant ) && '' !== (string) constant( $famma_constant ) ) {
		throw new RuntimeException( 'Clone tracking constants must remain empty.' );
	}
}
if ( '' !== get_option( 'famma_meta_access_token', '' ) || ! class_exists( '\Famma\Core\Capi_Delivery_Registry' ) ) {
	throw new RuntimeException( 'Credential-free clone with the registry required.' );
}

/** Owns actual WooCommerce fixtures and process-local transport interception. */
final class Famma_Capi_Registre_Test {
	/**
	 * Exact run-owned option prefix.
	 *
	 * @var string
	 */
	private static string $name = '';
	/**
	 * Synthetic fixture ownership marker.
	 *
	 * @var string
	 */
	private static string $run = '';
	/**
	 * Independent PHP process role.
	 *
	 * @var string
	 */
	private static string $role = '';
	/**
	 * Actual fixture identifiers and clock.
	 *
	 * @var array
	 */
	private static array $context = array();
	/**
	 * Observed transport envelopes without user data or token.
	 *
	 * @var array
	 */
	private static array $requests = array();
	/**
	 * Assertions from real objects and persistent registry.
	 *
	 * @var array
	 */
	private static array $results = array();
	/**
	 * Transport fault scenario.
	 *
	 * @var string
	 */
	private static string $response = 'accepted';
	/**
	 * Exact crash phase.
	 *
	 * @var string
	 */
	private static string $crash = '';
	/**
	 * Seconds of artificial transport latency.
	 *
	 * @var int
	 */
	private static int $delay = 0;

	/**
	 * Execute one real dispatch phase.
	 *
	 * @throws RuntimeException For unsafe environment or failed observations.
	 */
	public static function run(): void {
		self::$run = (string) getenv( 'FAMMA_CAPI_REGISTRE_RUN' );
		if ( ! preg_match( '/^[a-f0-9-]{36}$/', self::$run ) ) {
			throw new RuntimeException( 'Exact UUID ownership required.' );
		}
		self::$name = 'famma_capi_test_' . self::$run;
		self::$role = (string) getenv( 'FAMMA_CAPI_REGISTRE_ROLE' );
		if ( ! in_array( self::$role, array( 's', 'a', 'b', 'c' ), true ) ) {
			throw new RuntimeException( 'Known process role required.' );
		}
		$mode = (string) getenv( 'FAMMA_CAPI_REGISTRE_MODE' );
		add_filter( 'pre_http_request', array( self::class, 'intercept' ), PHP_INT_MAX, 3 );
		add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
		\Famma\Core\Capi_Delivery_Registry::instance()->install();
		if ( in_array( $mode, array( 'setup', 'sequential' ), true ) ) {
			self::fixtures();
		} else {
			self::$context = (array) get_option( self::$name, array() );
			if ( ( self::$context['run'] ?? '' ) !== self::$run ) {
				throw new RuntimeException( 'Owned fixture context not found.' );
			}
		}
		add_filter( 'pre_option_famma_meta_pixel_id', static fn(): string => '123456789', PHP_INT_MAX );
		add_filter( 'pre_option_famma_meta_access_token', static fn(): string => 'INTEGRATION_FAKE_TOKEN_NOT_A_SECRET', PHP_INT_MAX );
		add_filter( 'pre_option_famma_meta_orderplaced_enabled', '__return_true', PHP_INT_MAX );
		add_action( 'famma_capi_delivery_claimed', array( self::class, 'claimed' ), PHP_INT_MAX, 3 );
		add_action( 'famma_capi_delivery_acknowledged', array( self::class, 'acknowledged' ), PHP_INT_MAX, 3 );
		if ( 'sequential' === $mode ) {
			try {
				self::sequential();
			} finally {
				self::cleanup();
			}
		} elseif ( 'worker' === $mode ) {
			self::worker();
		} elseif ( 'verify' === $mode ) {
			self::verify();
		} elseif ( 'crash' === $mode ) {
			self::$crash = (string) getenv( 'FAMMA_CAPI_REGISTRE_CRASH' );
			self::dispatch( self::$context['order'] );
			throw new RuntimeException( 'Injected process exit was not reached.' );
		} elseif ( 'resume' === $mode ) {
			self::resume();
		} elseif ( 'cleanup' === $mode ) {
			self::cleanup();
			self::check( 'cleanup', true, 'Exact marked fixtures deleted.' );
		} elseif ( 'setup' !== $mode ) {
			throw new RuntimeException( 'Unknown test phase.' );
		}
		WP_CLI::line(
			wp_json_encode(
				array(
					'suite'       => 'real-capi-registre-integration',
					'mode'        => $mode,
					'run'         => self::$run,
					'event'       => self::$context['event'],
					'wordpress'   => get_bloginfo( 'version' ),
					'woocommerce' => WC_VERSION,
					'results'     => self::$results,
					'requests'    => self::$requests,
				),
				JSON_PRETTY_PRINT
			)
		);
	}

	/**
	 * Create actual CRUD fixtures before fake transport configuration is enabled.
	 *
	 * @throws RuntimeException When ownership or business event is invalid.
	 */
	private static function fixtures(): void {
		if ( get_option( self::$name, false ) ) {
			throw new RuntimeException( 'Refusing to overwrite an existing run.' );
		}
		$event = (string) getenv( 'FAMMA_CAPI_REGISTRE_EVENT' );
		if ( ! in_array( $event, array( 'Purchase', 'OrderPlaced' ), true ) ) {
			throw new RuntimeException( 'Known business event required.' );
		}
		self::$context = array(
			'run'    => self::$run,
			'event'  => $event,
			'orders' => array(),
		);
		$product       = new WC_Product_Simple();
		$product->set_name( '[TEST LOCAL] CAPI registry ' . self::$run );
		$product->set_status( 'private' );
		$product->set_virtual( true );
		$product->set_tax_status( 'none' );
		$product->set_regular_price( '79' );
		$product->update_meta_data( '_famma_capi_registre_run', self::$run );
		$product->save();
		self::$context['product'] = $product->get_id();
		self::$context['order']   = self::order();
		update_option( self::$name, self::$context, false );
	}

	/**
	 * Create one marked real order, with stable business date.
	 *
	 * @return int Saved order identifier.
	 * @throws RuntimeException When actual order creation fails.
	 */
	private static function order(): int {
		$order = wc_create_order(
			array(
				'created_via' => 'checkout',
				'status'      => 'pending-confirm',
			)
		);
		if ( ! $order instanceof WC_Order ) {
			throw new RuntimeException( 'Actual order creation failed.' );
		}
		$order->set_payment_method( 'cod' );
		$order->set_currency( 'TND' );
		$order->set_date_created( time() - 172800 );
		$order->set_billing_email( 'synthetic@example.invalid' );
		$order->set_billing_phone( '+21620000000' );
		$order->set_billing_country( 'TN' );
		$order->update_meta_data( '_famma_capi_registre_run', self::$run );
		$order->add_product( wc_get_product( self::$context['product'] ), 1 );
		$order->calculate_totals();
		$order->save();
		if ( 'Purchase' === self::$context['event'] ) {
			// Disable only the fake test credential while preparing a delivered fixture.
			add_filter( 'pre_option_famma_meta_access_token', '__return_empty_string', PHP_INT_MAX );
			$order->update_status( 'delivered', 'Isolated synthetic delivery fixture.' );
			remove_filter( 'pre_option_famma_meta_access_token', '__return_empty_string', PHP_INT_MAX );
		}
		self::$context['orders'][] = $order->get_id();
		update_option( self::$name, self::$context, false );
		return $order->get_id();
	}

	/**
	 * Dispatch through the real production event handler.
	 *
	 * @param int $order_id Owned real order.
	 */
	private static function dispatch( int $order_id ): void {
		if ( 'OrderPlaced' === self::$context['event'] ) {
			apply_filters(
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Prepare actual accepted-checkout task through WooCommerce.
				'woocommerce_payment_successful_result',
				array(
					'result'   => 'success',
					'redirect' => home_url( '/integration-thanks/' ),
				),
				$order_id
			);
			do_action( 'famma_capi_orderplaced_send', $order_id );
		} else {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Exercise real WooCommerce delivery processing.
			do_action( 'woocommerce_order_status_delivered', $order_id );
		}
	}

	/**
	 * Read the actual persistent dispatcher record.
	 *
	 * @param int $order_id Owned order identifier.
	 * @return array Durable record.
	 */
	private static function record( int $order_id ): array {
		$name = self::$context['event'];
		$id   = 'famma_' . ( 'Purchase' === $name ? 'purchase' : 'orderplaced' ) . '_' . $order_id;
		return \Famma\Core\Capi_Delivery_Registry::instance()->read( hash( 'sha256', '123456789|' . $name . '|' . $id ) );
	}

	/**
	 * Inspect the real WooCommerce acknowledgment marker.
	 *
	 * @param int $order_id Owned order.
	 * @return bool Marker exists.
	 */
	private static function sent( int $order_id ): bool {
		return (bool) wc_get_order( $order_id )->get_meta( 'Purchase' === self::$context['event'] ? '_famma_purchase_sent' : '_famma_orderplaced_sent' );
	}

	/** Test acknowledged replay, rejects, uncertain outcomes and legacy markers. */
	private static function sequential(): void {
		$id = self::$context['order'];
		self::dispatch( $id );
		self::dispatch( $id );
		self::check( 'accepted-replay-one-http', 1 === count( self::$requests ) && self::sent( $id ), 'A replayed successful event performs one intercepted transport.' );
		self::check( 'accepted-durable', 'accepted' === ( self::record( $id )['state'] ?? '' ), 'Acknowledgment is durable outside WooCommerce metadata.' );
		$rejected       = self::order();
		self::$response = 'rejected';
		$count          = count( self::$requests );
		for ( $i = 0; $i < 7; ++$i ) {
			self::dispatch( $rejected );
		}
		$requests = array_slice( self::$requests, $count );
		self::check( 'rejections-bounded', 5 === count( $requests ) && ! self::sent( $rejected ), 'Seven invocations of explicit rejection result in at most five transports.' );
		self::check( 'rejections-stable-id-time', 1 === count( array_unique( array_column( $requests, 'event_id' ) ) ) && 1 === count( array_unique( array_column( $requests, 'event_time' ) ) ), 'All refused attempts retain the same business identifier and timestamp.' );
		$retry = self::order();
		$count = count( self::$requests );
		self::dispatch( $retry );
		self::$response = 'accepted';
		self::dispatch( $retry );
		$requests = array_slice( self::$requests, $count );
		self::check( 'explicit-rejection-retry-accepted', 2 === count( $requests ) && self::sent( $retry ) && 'accepted' === ( self::record( $retry )['state'] ?? '' ), 'An explicit rejection can be retried and durably accepted within the ceiling.' );
		self::check( 'accepted-retry-stable-id-time', 1 === count( array_unique( array_column( $requests, 'event_id' ) ) ) && 1 === count( array_unique( array_column( $requests, 'event_time' ) ) ), 'The acknowledged retry retains the original business ID and time.' );
		foreach ( array( 'timeout', 'server-error', 'malformed-success' ) as $fault ) {
			$uncertain      = self::order();
			self::$response = $fault;
			$count          = count( self::$requests );
			self::dispatch( $uncertain );
			self::$response = 'accepted';
			self::dispatch( $uncertain );
			$held = 1 === count( self::$requests ) - $count && ! self::sent( $uncertain ) && 'uncertain' === ( self::record( $uncertain )['state'] ?? '' );
			self::check( 'uncertain-no-resend-' . $fault, $held, 'Ambiguous transport outcome is retained for review and never blindly retried.' );
		}
		$legacy = self::order();
		$order  = wc_get_order( $legacy );
		$order->update_meta_data( 'Purchase' === self::$context['event'] ? '_famma_purchase_sent' : '_famma_orderplaced_sent', 1 );
		$order->save();
		$count = count( self::$requests );
		self::dispatch( $legacy );
		self::check( 'legacy-sent-preserved', count( self::$requests ) === $count && self::sent( $legacy ), 'Historical successful Woo marker prevents resending.' );
		$exhausted = self::order();
		$order     = wc_get_order( $exhausted );
		$order->update_meta_data( 'Purchase' === self::$context['event'] ? '_famma_capi_attempts' : '_famma_orderplaced_attempts', 5 );
		$order->save();
		self::dispatch( $exhausted );
		self::check( 'legacy-attempt-limit-preserved', count( self::$requests ) === $count, 'Historical five-attempt ceiling blocks a new registry transport.' );
		self::missing_table();
	}

	/**
	 * Verify a real missing registry table causes no transport and restore it.
	 *
	 * @throws RuntimeException When reversible schema fault cannot be prepared or restored.
	 */
	private static function missing_table(): void {
		global $wpdb;
		$id    = self::order();
		$count = count( self::$requests );
		$table = \Famma\Core\Capi_Delivery_Registry::instance()->table();
		$held  = $wpdb->prefix . 'famma_capi_hold_' . str_replace( '-', '', self::$run );
		if ( ! str_starts_with( $table, $wpdb->prefix ) ) {
			throw new RuntimeException( 'Unexpected disposable registry table identifier.' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reversible schema fault in explicitly guarded disposable DB; no data removed.
		$renamed = $wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $table, $held ) );
		if ( false === $renamed ) {
			throw new RuntimeException( 'Could not prepare reversible schema fault.' );
		}
		$prior_suppression = $wpdb->suppress_errors( true );
		try {
			self::dispatch( $id );
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Restore exact renamed table immediately and preserve all existing rows.
			$restored = $wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $held, $table ) );
			$wpdb->suppress_errors( $prior_suppression );
			if ( false === $restored ) {
				throw new RuntimeException( 'Test registry restoration failed.' );
			}
		}
		self::check( 'missing-table-no-http', count( self::$requests ) === $count && ! self::sent( $id ), 'Missing real registry table fails closed without any outgoing transport.' );
	}

	/**
	 * Run two actual worker processes behind a shared readiness barrier.
	 *
	 * @throws RuntimeException When the peer process is missing.
	 */
	private static function worker(): void {
		update_option( self::$name . '_ready_' . self::$role, 1, false );
		$deadline = microtime( true ) + 20;
		while ( ! self::fresh( '_ready_a' ) || ! self::fresh( '_ready_b' ) ) {
			if ( microtime( true ) > $deadline ) {
				throw new RuntimeException( 'Concurrent peer did not reach barrier.' );
			}
			usleep( 50000 );
		}
		self::$delay = 2;
		if ( 'b' === self::$role ) {
			while ( ! self::fresh( '_http_a' ) ) {
				if ( microtime( true ) > $deadline ) {
					throw new RuntimeException( 'First worker never entered the transport.' );
				}
				usleep( 10000 );
			}
		}
		$started = microtime( true );
		self::dispatch( self::$context['order'] );
		update_option(
			self::$name . '_worker_' . self::$role,
			array(
				'started' => $started,
				'ended'   => microtime( true ),
			),
			false
		);
	}

	/** Check concurrency from persistent evidence recorded by independent processes. */
	private static function verify(): void {
		$a      = (array) self::fresh( '_worker_a' );
		$b      = (array) self::fresh( '_worker_b' );
		$events = array_merge( self::http_records( 'a' ), self::http_records( 'b' ) );
		self::check( 'workers-overlapped', max( $a['started'] ?? PHP_FLOAT_MAX, $b['started'] ?? PHP_FLOAT_MAX ) < min( $a['ended'] ?? 0, $b['ended'] ?? 0 ), 'Distinct real PHP execution windows overlap.' );
		self::check( 'concurrency-one-transport', 1 === count( $events ), 'Both concurrent handlers yield one actual HTTP interception.' );
		self::check( 'second-worker-during-http', ( $b['started'] ?? 0 ) >= ( $events[0]['started'] ?? PHP_FLOAT_MAX ) && ( $b['ended'] ?? PHP_FLOAT_MAX ) <= ( $events[0]['ended'] ?? 0 ), 'Second real PHP handler executes while the first transport is still in flight.' );
		self::check( 'concurrency-one-durable-ack', 'accepted' === ( self::record( self::$context['order'] )['state'] ?? '' ) && self::sent( self::$context['order'] ), 'Registry acknowledgment and Woo marker both persisted.' );
		self::$results[] = array(
			'name'       => 'process-evidence',
			'a'          => $a,
			'b'          => $b,
			'transports' => $events,
		);
	}

	/** Resolve a killed worker conservatively from persistent state. */
	private static function resume(): void {
		$prior         = self::http_records( 'a' );
		$record_before = self::record( self::$context['order'] );
		$marker_before = self::sent( self::$context['order'] );
		$stale         = wc_get_order( self::$context['order'] );
		$stale->get_meta( 'Purchase' === self::$context['event'] ? '_famma_purchase_sent' : '_famma_orderplaced_sent' );
		self::dispatch( self::$context['order'] );
		$stale->save();
		$accepted = 'accepted' === ( $record_before['state'] ?? '' );
		self::check( 'crash-replay-no-http', 0 === count( self::$requests ), 'Restart cannot blindly repeat an in-flight or acknowledged transport.' );
		self::check( 'crash-ack-recovered-or-held', $accepted ? self::sent( self::$context['order'] ) && ! $marker_before : ! self::sent( self::$context['order'] ), 'Durable ACK repairs missing Woo marker; unfinished dispatch remains held.' );
		self::$results[] = array(
			'name'             => 'crash-evidence',
			'prior_http'       => $prior,
			'prior_state'      => $record_before['state'] ?? '',
			'prior_woo_marker' => $marker_before,
			'current_state'    => self::record( self::$context['order'] )['state'] ?? '',
		);
	}

	/**
	 * Inject an actual process exit before transport.
	 *
	 * @param array    $claim Registry lease.
	 * @param WC_Order $order Real order.
	 * @param string   $event Business event.
	 */
	public static function claimed( array $claim, WC_Order $order, string $event ): void {
		unset( $claim, $order, $event );
		if ( 'claimed' === self::$crash ) {
			exit( 77 );
		}
	}

	/**
	 * Inject exit after durable ACK but before the Woo marker.
	 *
	 * @param array    $claim Registry lease.
	 * @param WC_Order $order Real order.
	 * @param string   $event Business event.
	 */
	public static function acknowledged( array $claim, WC_Order $order, string $event ): void {
		unset( $claim, $order, $event );
		if ( 'acknowledged' === self::$crash ) {
			exit( 77 );
		}
	}

	/**
	 * Capture real outgoing calls without any network, retaining no personal payload.
	 *
	 * @param mixed  $preempt Prior interception.
	 * @param array  $args Request arguments.
	 * @param string $url Destination.
	 * @return array|WP_Error Local response or deliberate timeout.
	 */
	public static function intercept( $preempt, array $args, string $url ) {
		unset( $preempt );
		$body = json_decode( (string) ( $args['body'] ?? '' ), true );
		if ( 'graph.facebook.com' !== wp_parse_url( $url, PHP_URL_HOST ) || 'INTEGRATION_FAKE_TOKEN_NOT_A_SECRET' !== ( $body['access_token'] ?? '' ) ) {
			return new WP_Error( 'famma_test_network_blocked', 'No external transport in this clone.' );
		}
		$event            = $body['data'][0] ?? array();
		self::$requests[] = array(
			'event_name' => $event['event_name'] ?? '',
			'event_id'   => $event['event_id'] ?? '',
			'event_time' => $event['event_time'] ?? 0,
			'started'    => microtime( true ),
		);
		update_option( self::$name . '_http_' . self::$role, self::$requests, false );
		if ( 'transport' === self::$crash ) {
			exit( 77 );
		}
		if ( self::$delay ) {
			sleep( self::$delay );
		}
		self::$requests[ count( self::$requests ) - 1 ]['ended'] = microtime( true );
		update_option( self::$name . '_http_' . self::$role, self::$requests, false );
		if ( 'timeout' === self::$response ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28: synthetic lost response.' );
		}
		$code    = 'rejected' === self::$response ? 429 : ( 'server-error' === self::$response ? 503 : 200 );
		$payload = 200 === $code ? ( 'malformed-success' === self::$response ? 'not-json' : '{"events_received":1}' ) : '{"error":{"code":4,"message":"Synthetic local rejection"}}';
		return array(
			'response' => array( 'code' => $code ),
			'body'     => $payload,
			'headers'  => array(),
			'cookies'  => array(),
		);
	}

	/**
	 * Invalidate process cache and read a run-owned option.
	 *
	 * @param string $suffix Run-owned suffix.
	 * @return mixed Current persisted value.
	 */
	private static function fresh( string $suffix ) {
		wp_cache_delete( self::$name . $suffix, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return get_option( self::$name . $suffix, false );
	}

	/**
	 * Read records without treating an absent option as one false record.
	 *
	 * @param string $role Independent worker role.
	 * @return array Persisted transport records.
	 */
	private static function http_records( string $role ): array {
		$records = self::fresh( '_http_' . $role );
		return is_array( $records ) ? $records : array();
	}

	/**
	 * Record a behavior assertion and fail without printing payloads.
	 *
	 * @param string $name Assertion identity.
	 * @param bool   $passed Observation.
	 * @param string $message Business expectation.
	 * @throws RuntimeException When the observed behavior violates the expectation.
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

	/** Remove only UUID-owned fixtures, never other orders or dispatcher records. */
	private static function cleanup(): void {
		global $wpdb;
		foreach ( self::$context['orders'] as $id ) {
			$order = wc_get_order( $id );
			if ( $order && self::$run === $order->get_meta( '_famma_capi_registre_run' ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact suite-owned custom registry rows only; orders use CRUD.
				$wpdb->delete(
					\Famma\Core\Capi_Delivery_Registry::instance()->table(),
					array(
						'order_id' => $id,
					),
					array( '%d' )
				);
				as_unschedule_all_actions( 'famma_capi_orderplaced_send', array( 'order_id' => $id ), 'famma-capi' );
				as_unschedule_all_actions( \Famma\Core\Order_Emails::ACTION, array( $id ), \Famma\Core\Order_Emails::GROUP );
				$order->delete( true );
			}
		}
		$product = wc_get_product( self::$context['product'] );
		if ( $product && self::$run === $product->get_meta( '_famma_capi_registre_run' ) ) {
			$product->delete( true );
		}
		foreach ( array( '', '_ready_a', '_ready_b', '_worker_a', '_worker_b', '_http_s', '_http_a', '_http_b', '_http_c' ) as $suffix ) {
			delete_option( self::$name . $suffix );
		}
	}
}

Famma_Capi_Registre_Test::run();
