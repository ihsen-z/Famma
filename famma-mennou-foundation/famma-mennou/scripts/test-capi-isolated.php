<?php
/**
 * Isolated CAPI contract tests; no WordPress database or real HTTP requests.
 * Run with: php scripts/test-capi-isolated.php
 * The source class is extracted so its actual implementation is exercised.
 */

namespace Famma\Core {
	final class Config {
		public static string $token = 'test-token-not-a-secret';
		public static function instance(): self { return new self(); }
		public function meta_pixel_id(): string { return '123456789'; }
		public function meta_access_token(): string { return self::$token; }
		public function meta_test_event_code(): string { return 'TEST_DO_NOT_ROUTE_REAL_ORDERS'; }
		public static function international_phone( string $phone ): string { return '216' . $phone; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	final class WC_Order {
		public array $meta = array();
		public array $notes = array();
		public string $status = 'delivered';
		public string $created_via = 'checkout';
		public int $id = 901;
		public function get_meta( string $key ) { return $this->meta[ $key ] ?? ''; }
		public function update_meta_data( string $key, $value ): void { $this->meta[ $key ] = $value; }
		public function save(): void {}
		public function has_status( $status ): bool { return in_array( $this->status, (array) $status, true ); }
		public function get_items(): array { return array(); }
		public function get_id(): int { return $this->id; }
		public function get_created_via(): string { return $this->created_via; }
		public function get_billing_email(): string { return 'isolated@example.invalid'; }
		public function get_billing_phone(): string { return '00000000'; }
		public function get_billing_first_name(): string { return 'Test'; }
		public function get_billing_last_name(): string { return 'Isolated'; }
		public function get_billing_city(): string { return 'Tunis'; }
		public function get_billing_state(): string { return 'TN-11'; }
		public function get_billing_country(): string { return 'TN'; }
		public function get_currency(): string { return 'TND'; }
		public function get_total(): string { return '79.000'; }
		public function get_checkout_order_received_url(): string { return 'https://example.invalid/order-received/901/'; }
		public function get_date_created(): \DateTimeImmutable { return new \DateTimeImmutable( '2020-01-01' ); }
		public function add_order_note( string $note ): void { $this->notes[] = $note; }
	}
	final class WP_Error {
		public function get_error_message(): string { return 'Isolated network error'; }
	}
	function add_action( ...$args ): void {}
	function add_filter( string $name, ...$args ): void { $GLOBALS['registered_filters'][] = $name; }
	function get_option( string $name, $default = false ) { return $GLOBALS['orderplaced_enabled'] ?? $default; }
	function wc_get_order( int $id ) { return $GLOBALS['checkout_order'] ?? false; }
	if ( ! getenv( 'FAMMA_TEST_CRON_FALLBACK' ) ) {
		function as_enqueue_async_action( string $hook, array $args, string $group, bool $unique ): int {
			$GLOBALS['queue_calls'][] = array( $hook, $args, $group, $unique );
			$GLOBALS['jobs'][ $args['order_id'] ] = $args;
			return 1;
		}
	} else {
		function wp_next_scheduled( string $hook, array $args ): bool {
			return isset( $GLOBALS['jobs'][ $args['order_id'] ] );
		}
		function wp_schedule_single_event( int $time, string $hook, array $args ): bool {
			$GLOBALS['queue_calls'][] = array( $hook, $args, 'wp-cron', true );
			$GLOBALS['jobs'][ $args['order_id'] ] = $args;
			return true;
		}
	}
	function apply_filters( string $tag, $value ) { return $value; }
	function wp_json_encode( $value ): string { return json_encode( $value ); }
	function wp_remote_post( string $url, array $args ) {
		$GLOBALS['calls'][] = array( $url, json_decode( $args['body'], true ) );
		return $GLOBALS['response'];
	}
	function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
	function wp_remote_retrieve_response_code( array $response ): int { return $response['code']; }
	function wp_remote_retrieve_body( array $response ): string { return $response['body']; }
	function current_time( string $type ): string { return '2026-10-09 12:00:00'; }
	function wc_get_checkout_url(): string { return 'https://example.invalid/checkout/'; }
	function __( string $message, string $domain ): string { return $message; }
	function check( bool $condition, string $message ): void {
		if ( ! $condition ) { throw new \RuntimeException( $message ); }
		echo 'PASS: ' . $message . PHP_EOL;
	}
	$source = file_get_contents( dirname( __DIR__ ) . '/wp-content/plugins/famma-core/famma-core.php' );
	$start = strpos( $source, 'final class Capi {' );
	$end = strpos( $source, '/**' . PHP_EOL . ' * Simplified merchant dashboard', $start );
	// Accommodate checkouts using either LF or CRLF.
	if ( false === $end ) { $end = strpos( $source, "/**\n * Simplified merchant dashboard", $start ); }
	if ( false === $start || false === $end ) { throw new \RuntimeException( 'Capi source boundaries not found.' ); }
	eval( 'namespace Famma\\Core;' . substr( $source, $start, $end - $start ) );
	$client = \Famma\Core\Capi::instance();
	$GLOBALS['calls'] = array();
	$GLOBALS['response'] = array( 'code' => 200, 'body' => '{"events_received":1}' );
	$order = new WC_Order();
	\Famma\Core\Config::$token = '';
	$client->send_purchase( $order, 'famma_purchase_901' );
	check( array() === $GLOBALS['calls'] && ! $order->get_meta( '_famma_purchase_sent' ), 'Missing token does not send or consume the event.' );
	\Famma\Core\Config::$token = 'test-token-not-a-secret';
	$order->status = 'pending';
	$client->send_purchase( $order, 'famma_purchase_901' );
	check( array() === $GLOBALS['calls'], 'Undelivered orders cannot become Purchase events.' );
	$order->status = 'delivered';
	$before = time();
	$client->send_purchase( $order, 'famma_purchase_901' );
	$payload = $GLOBALS['calls'][0][1];
	check( ! isset( $payload['test_event_code'] ), 'Global test settings cannot turn a delivered sale into a test.' );
	check( $payload['data'][0]['event_time'] >= $before, 'Purchase time reflects delivery even for an old order.' );
	check( 79.0 === (float) $payload['data'][0]['custom_data']['value'] && 'TND' === $payload['data'][0]['custom_data']['currency'], 'Value and currency match the delivered order.' );
	check( ! isset( $payload['data'][0]['custom_data']['famma_stage'] ), 'Purchase cannot enter the OrderPlaced conversion rule.' );
	check( 'https://example.invalid/checkout/' === $payload['data'][0]['event_source_url'], 'No private order access key is sent to Meta.' );
	check( (bool) $order->get_meta( '_famma_purchase_sent' ), 'Confirmed acceptance marks the event sent.' );
	$client->send_purchase( $order, 'famma_purchase_901' );
	check( 1 === count( $GLOBALS['calls'] ), 'Repeated delivery does not send twice.' );
	foreach ( array( '{"events_received":0}', '{}', 'invalid-json', '{"events_received":1,"error":{"message":"failure"}}' ) as $body ) {
		$order = new WC_Order();
		$GLOBALS['response'] = array( 'code' => 200, 'body' => $body );
		$client->send_purchase( $order, 'famma_purchase_901' );
		check( ! $order->get_meta( '_famma_purchase_sent' ), 'An unacknowledged response cannot consume the event: ' . $body );
	}
	$order = new WC_Order();
	$GLOBALS['response'] = new WP_Error();
	$client->send_purchase( $order, 'famma_purchase_901' );
	$first_time = $order->get_meta( '_famma_purchase_event_time' );
	check( ! $order->get_meta( '_famma_purchase_sent' ), 'A network failure leaves the event replayable.' );
	$GLOBALS['response'] = array( 'code' => 200, 'body' => '{"events_received":1}' );
	$client->send_purchase( $order, 'famma_purchase_901' );
	check( $first_time === $order->get_meta( '_famma_purchase_event_time' ), 'Retry preserves the first delivery timestamp.' );
	check( (bool) $order->get_meta( '_famma_purchase_sent' ), 'A successful retry records acceptance.' );
	check( in_array( 'woocommerce_payment_successful_result', $GLOBALS['registered_filters'], true ), 'OrderPlaced is connected to accepted checkout result, not order creation.' );
	$GLOBALS['calls'] = array();
	$GLOBALS['queue_calls'] = array();
	$GLOBALS['jobs'] = array();
	$GLOBALS['orderplaced_enabled'] = false;
	$order = new WC_Order();
	$order->status = 'pending-confirm';
	$GLOBALS['checkout_order'] = $order;
	$result = array( 'result' => 'success', 'redirect' => 'https://example.invalid/thanks/' );
	check( $result === $client->order_placed_after_checkout( $result, 901 ) && array() === $GLOBALS['calls'] && array() === $GLOBALS['jobs'], 'Default OFF leaves checkout unchanged and sends no customer data.' );
	$GLOBALS['orderplaced_enabled'] = true;
	$failure = array( 'result' => 'failure' );
	$client->order_placed_after_checkout( $failure, 901 );
	check( array() === $GLOBALS['calls'], 'Failed checkout cannot send OrderPlaced.' );
	foreach ( array( 'checkout-draft', 'draft', 'failed', 'cancelled', 'trash', 'returned', 'refused', 'failed-delivery' ) as $status ) {
		$order->status = $status;
		$client->order_placed_after_checkout( $result, 901 );
		check( array() === $GLOBALS['calls'], 'Rejected status cannot send OrderPlaced: ' . $status );
	}
	$order->status = 'pending-confirm';
	$order->created_via = 'admin';
	$client->order_placed_after_checkout( $result, 901 );
	check( array() === $GLOBALS['calls'], 'Admin-created orders cannot send OrderPlaced.' );
	$order->created_via = 'checkout';
	$order->id = 0;
	$client->order_placed_after_checkout( $result, 0 );
	check( array() === $GLOBALS['calls'], 'Unsaved orders cannot send OrderPlaced.' );
	$order->id = 901;
	\Famma\Core\Config::$token = '';
	$client->order_placed_after_checkout( $result, 901 );
	check( array() === $GLOBALS['calls'] && ! $order->get_meta( '_famma_orderplaced_sent' ), 'Missing token cannot consume OrderPlaced.' );
	check( 1 === count( $GLOBALS['jobs'] ) && (bool) $order->get_meta( '_famma_orderplaced_checkout_accepted' ), 'Accepted checkout queues work without a synchronous HTTP call.' );
	$queue_group = getenv( 'FAMMA_TEST_CRON_FALLBACK' ) ? 'wp-cron' : 'famma-capi';
	check( true === $GLOBALS['queue_calls'][0][3] && $queue_group === $GLOBALS['queue_calls'][0][2], 'Selected scheduler receives a unique task: ' . $queue_group );
	$client->order_placed_after_checkout( $result, 901 );
	check( 1 === count( $GLOBALS['jobs'] ), 'Repeated checkout callbacks retain one unique queued job.' );
	\Famma\Core\Config::$token = 'test-token-not-a-secret';
	$client->send_queued_order_placed( 901 );
	$placed = $GLOBALS['calls'][0][1]['data'][0];
	check( 'OrderPlaced' === $placed['event_name'] && 'famma_orderplaced_901' === $placed['event_id'], 'OrderPlaced uses a separate stable event name and identifier.' );
	check( 'order_placed' === $placed['custom_data']['famma_stage'], 'OrderPlaced carries a distinct public business stage for custom conversion.' );
	check( $order->get_date_created()->getTimestamp() === $placed['event_time'], 'OrderPlaced carries the creation timestamp.' );
	check( (bool) $order->get_meta( '_famma_orderplaced_sent' ) && ! $order->get_meta( '_famma_purchase_sent' ), 'OrderPlaced acceptance cannot consume the delivered Purchase.' );
	$client->order_placed_after_checkout( $result, 901 );
	$redirect = 'https://example.invalid/thanks/';
	check( $redirect === $client->order_placed_without_payment( $redirect, $order ) && 1 === count( $GLOBALS['calls'] ), 'Repeating successful hooks cannot send OrderPlaced twice or alter redirect.' );
	$order->status = 'delivered';
	$client->send_purchase( $order, 'famma_purchase_901' );
	check( 2 === count( $GLOBALS['calls'] ) && 'Purchase' === $GLOBALS['calls'][1][1]['data'][0]['event_name'], 'Later delivery sends Purchase independently of OrderPlaced.' );
	foreach ( array( '{"events_received":0}', '{}', 'invalid-json' ) as $body ) {
		$order = new WC_Order();
		$order->status = 'pending-confirm';
		$order->meta['_famma_orderplaced_checkout_accepted'] = 1;
		$GLOBALS['response'] = array( 'code' => 200, 'body' => $body );
		$client->send_order_placed( $order );
		check( ! $order->get_meta( '_famma_orderplaced_sent' ), 'Ambiguous response cannot consume OrderPlaced: ' . $body );
	}
	$order = new WC_Order();
	$order->status = 'pending-confirm';
	$order->meta['_famma_orderplaced_checkout_accepted'] = 1;
	$GLOBALS['response'] = new WP_Error();
	$client->send_order_placed( $order );
	$placed_time = $order->get_meta( '_famma_orderplaced_event_time' );
	$GLOBALS['response'] = array( 'code' => 200, 'body' => '{"events_received":1}' );
	$client->send_order_placed( $order );
	check( $placed_time === $order->get_meta( '_famma_orderplaced_event_time' ) && (bool) $order->get_meta( '_famma_orderplaced_sent' ), 'Retry preserves OrderPlaced timestamp and consumes it only after confirmation.' );
	$order = new WC_Order();
	$order->status = 'pending-confirm';
	$GLOBALS['checkout_order'] = $order;
	$client->order_placed_after_checkout( $result, 901 );
	$count = count( $GLOBALS['calls'] );
	$GLOBALS['orderplaced_enabled'] = false;
	$client->send_queued_order_placed( 901 );
	check( $count === count( $GLOBALS['calls'] ), 'Queued worker rechecks the OFF setting before sharing data.' );
	$GLOBALS['orderplaced_enabled'] = true;
	$order->status = 'cancelled';
	$client->send_queued_order_placed( 901 );
	check( $count === count( $GLOBALS['calls'] ), 'Queued worker rejects an order cancelled after checkout.' );
	$order->status = 'pending-confirm';
	$order->created_via = 'admin';
	$client->send_queued_order_placed( 901 );
	check( $count === count( $GLOBALS['calls'] ), 'Queued worker rechecks the storefront source.' );
	$order->created_via = 'checkout';
	unset( $order->meta['_famma_orderplaced_checkout_accepted'] );
	$client->send_queued_order_placed( 901 );
	check( $count === count( $GLOBALS['calls'] ), 'A queued identifier without successful checkout acceptance cannot send.' );
	echo 'No network requests or database writes were performed.' . PHP_EOL;
}
