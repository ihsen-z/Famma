<?php
/**
 * Isolated CAPI contract tests; no WordPress database or real HTTP requests.
 * Run with: php scripts/test-capi-isolated.php
 * The source class is extracted so its actual implementation is exercised.
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded. The doubles below keep the names,
// namespaces and signatures of the FAMMA, WordPress and WooCommerce code they replace.
// phpcs:disable Universal.Namespaces -- The FAMMA Config double and the global WordPress doubles must share this single runner.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Generic.Classes.DuplicateClassName -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.

namespace Famma\Core {
	final class Config {
		public const CAPI_MAX_ATTEMPTS  = 5;
		public const CAPI_MAX_EVENT_AGE = 518400;
		public const CAPI_RETRY_DELAYS  = array( 300, 1800, 7200, 21600 );
		public static string $token     = 'test-token-not-a-secret';
		public static bool $allowed     = true;
		public static string $profile   = 'standard';
		public static function instance(): self {
			return new self();
		}
		public function meta_graph_version(): string {
			return 'v26.0';
		}
		public function meta_sending_allowed(): bool {
			return self::$allowed;
		}
		public function environment_type(): string {
			return self::$allowed ? 'production' : 'local';
		}
		public function meta_data_profile(): string {
			return self::$profile;
		}
		public static function valid_test_event_code( string $code ): bool {
			return 1 === preg_match( '/^TEST\d+$/D', $code );
		}
		public function meta_pixel_id(): string {
			return '123456789';
		}
		public function meta_access_token(): string {
			return self::$token;
		}
		public function meta_test_event_code(): string {
			return 'TEST_DO_NOT_ROUTE_REAL_ORDERS';
		}
		public static function international_phone( string $phone ): string {
			return '216' . $phone;
		}
	}

	/**
	 * Since 0.2.4 Capi delegates to four collaborators (includes/capi-*.php).
	 * The doubles below keep their public signatures. The registry grants
	 * ownership the way an empty table does (attempt = recorded attempts + 1,
	 * blocked beyond the maximum); it does not persist uncertain outcomes.
	 */
	final class Capi_Delivery_Registry {
		private static ?self $i = null;
		public static function instance(): self {
			self::$i ??= new self();
			return self::$i;
		}
		public function acquire( string $pixel, string $name, string $id, int $order_id, int $time, int $maximum, int $legacy = 0 ): array {
			$attempt = $legacy + 1;
			if ( $time <= 0 || $attempt > $maximum ) {
				return array( 'state' => 'blocked' );
			}
			return array(
				'key'        => hash( 'sha256', $pixel . '|' . $name . '|' . $id ),
				'state'      => 'sending',
				'owner'      => 'isolated-owner',
				'event_time' => $time,
				'attempt'    => $attempt,
				'owned'      => true,
			);
		}
		public function finish( array $claim, string $state, int $http, int $received ): bool {
			$GLOBALS['registry_finishes'][] = array( $state, $http, $received );
			return ! empty( $claim['owned'] ) && in_array( $state, array( 'accepted', 'rejected', 'uncertain' ), true );
		}
	}
	final class Capi_Signals {
		public static function clean_cookie( string $raw ): string {
			return trim( $raw );
		}
		public static function clean_ip( string $raw ): string {
			$ip = trim( $raw );
			return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
		}
		public static function test_mode_enabled(): bool {
			// Test mode is off by default, whatever the configured code.
			return ! empty( $GLOBALS['test_mode'] );
		}
	}
	final class Capi_Log {
		public static function write( array $fields, string $secret = '' ): void {
			$GLOBALS['capi_log'][] = $fields;
		}
		public static function outcome( array $base, string $state, string $reason = '' ): void {
			$GLOBALS['capi_outcomes'][] = $base + array(
				'state'  => $state,
				'reason' => $reason,
			);
		}
	}
	final class Capi_Health {
		public static function signal( string $key, array $data = array() ): void {
			$GLOBALS['health_signals'][] = $key;
		}
		public static function record_outcome( string $response_class, array $result ): void {
			$GLOBALS['health_outcomes'][] = $response_class;
		}
	}
	final class Capi_Retry {
		public const HOOK = 'famma_capi_retry';
		public static function schedule( int $order_id, string $event_name, int $attempts, int $event_time ): string {
			$GLOBALS['retries'][] = array( $order_id, $event_name, $attempts );
			return 'scheduled';
		}
	}
	final class Order_Statuses {
		public static function is_cod_order( \WC_Order $order ): bool {
			return true;
		}
		public static function purchase_ineligibility( \WC_Order $order ): string {
			// Same rule as the real Order_Statuses::purchase_ineligibility() (tested in tests-unit).
			if ( $order->has_status( 'delivered' ) ) {
				return '';
			}
			if ( $order->has_status( 'completed' ) ) {
				return 'yes' === $order->get_meta( '_famma_cod_paid' ) ? '' : 'not_collected';
			}
			return 'not_delivered';
		}
	}
	final class Capi_Product_Data {
		public static function for_order( \WC_Order $order ): array {
			return array(
				'contents'      => array(),
				'content_ids'   => array(),
				'content_name'  => '',
				'num_items'     => 0,
				'famma_product' => '',
			);
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	final class WC_Order {
		/**
		 * Order metadata; a browser order by default: its user agent was stored at checkout (website event).
		 *
		 * @var array
		 */
		public array $meta         = array( '_famma_client_ua' => 'IsolatedTestAgent' );
		public array $notes        = array();
		public string $status      = 'delivered';
		public string $created_via = 'checkout';
		public int $id             = 901;
		public function __construct() {
			// The most recent order is the one WooCommerce stores under its identifier.
			$GLOBALS['latest_order'] = $this;
		}
		public function get_meta( string $key ) {
			return $this->meta[ $key ] ?? '';
		}
		public function update_meta_data( string $key, $value ): void {
			$this->meta[ $key ] = $value;
		}
		public function read_meta_data( bool $force_read = false ): void {}
		public function save(): void {}
		public function has_status( $status ): bool {
			return in_array( $this->status, (array) $status, true );
		}
		public function get_items(): array {
			return array();
		}
		public function get_id(): int {
			return $this->id;
		}
		public function get_created_via(): string {
			return $this->created_via;
		}
		public function get_status(): string {
			return $this->status;
		}
		public function get_payment_method(): string {
			return 'cod';
		}
		public function get_billing_email(): string {
			return 'isolated@example.invalid';
		}
		public function get_billing_phone(): string {
			return '00000000';
		}
		public function get_billing_first_name(): string {
			return 'Test';
		}
		public function get_billing_last_name(): string {
			return 'Isolated';
		}
		public function get_billing_city(): string {
			return 'Tunis';
		}
		public function get_billing_state(): string {
			return 'TN-11';
		}
		public function get_billing_country(): string {
			return 'TN';
		}
		public function get_currency(): string {
			return 'TND';
		}
		public function get_total(): string {
			return '79.000';
		}
		public function get_checkout_order_received_url(): string {
			return 'https://example.invalid/order-received/901/';
		}
		public function get_date_created(): \DateTimeImmutable {
			// Two days old: an old order, yet within the 6-day window Meta still accepts (lot 5).
			$GLOBALS['created_at'] ??= time() - 2 * 86400;
			return ( new \DateTimeImmutable() )->setTimestamp( $GLOBALS['created_at'] );
		}
		public function add_order_note( string $note ): void {
			$this->notes[] = $note;
		}
	}
	final class WP_Error {
		public function get_error_message(): string {
			return 'Isolated network error';
		}
	}
	function add_action( ...$args ): void {}
	function add_filter( string $name, ...$args ): void {
		$GLOBALS['registered_filters'][] = $name;
	}
	function get_option( string $name, $default_value = false ) {
		return $GLOBALS['orderplaced_enabled'] ?? $default_value;
	}
	function wc_get_order( int $id ) {
		// Capi re-reads the order it dispatches: return the stored order unless the test deleted it.
		if ( array_key_exists( 'checkout_order', $GLOBALS ) && false === $GLOBALS['checkout_order'] ) {
			return false;
		}
		$order = $GLOBALS['latest_order'] ?? false;
		return $order instanceof WC_Order && $order->get_id() === $id ? $order : false;
	}
	function do_action( string $hook, ...$args ): void {
		$GLOBALS['actions'][] = $hook;
	}
	if ( ! getenv( 'FAMMA_TEST_CRON_FALLBACK' ) ) {
		function as_enqueue_async_action( string $hook, array $args, string $group, bool $unique ): int {
			$GLOBALS['queue_calls'][]             = array( $hook, $args, $group, $unique );
			$GLOBALS['jobs'][ $args['order_id'] ] = $args;
			return 1;
		}
	} else {
		function wp_next_scheduled( string $hook, array $args ): bool {
			return isset( $GLOBALS['jobs'][ $args['order_id'] ] );
		}
		function wp_schedule_single_event( int $time, string $hook, array $args ): bool {
			$GLOBALS['queue_calls'][]             = array( $hook, $args, 'wp-cron', true );
			$GLOBALS['jobs'][ $args['order_id'] ] = $args;
			return true;
		}
	}
	function apply_filters( string $tag, $value ) {
		return $value;
	}
	function wp_json_encode( $value ): string {
		return json_encode( $value );
	}
	function wp_remote_post( string $url, array $args ) {
		$GLOBALS['calls'][] = array( $url, json_decode( $args['body'], true ) );
		return $GLOBALS['response'];
	}
	function is_wp_error( $value ): bool {
		return $value instanceof WP_Error;
	}
	function wp_remote_retrieve_response_code( array $response ): int {
		return $response['code'];
	}
	function wp_remote_retrieve_body( array $response ): string {
		return $response['body'];
	}
	function wp_remote_retrieve_header( array $response, string $header ): string {
		return (string) ( $response['headers'][ $header ] ?? '' );
	}
	function current_time( string $type ): string {
		return '2026-10-09 12:00:00';
	}
	function wc_get_checkout_url(): string {
		return 'https://example.invalid/checkout/';
	}
	function __( string $message, string $domain ): string {
		return $message;
	}

	/**
	 * Stop the run on the first broken contract, otherwise report it as passed.
	 *
	 * @param bool   $condition Contract outcome.
	 * @param string $message   Contract description.
	 * @throws \RuntimeException When the contract does not hold.
	 */
	function check( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
		echo 'PASS: ' . $message . PHP_EOL;
	}
	// phpcs:enable

	$source = file_get_contents( dirname( __DIR__ ) . '/wp-content/plugins/famma-core/famma-core.php' );
	$start  = strpos( $source, 'final class Capi {' );
	$end    = strpos( $source, '/**' . PHP_EOL . ' * Simplified merchant dashboard', $start );
	// Accommodate checkouts using either LF or CRLF.
	if ( false === $end ) {
		$end = strpos( $source, "/**\n * Simplified merchant dashboard", $start );
	}
	if ( false === $start || false === $end ) {
		throw new \RuntimeException( 'Capi source boundaries not found.' );
	}
	// phpcs:ignore Squiz.PHP.Eval.Discouraged -- Runs the real Capi source read from the plugin file, never external input.
	eval( 'namespace Famma\\Core;' . substr( $source, $start, $end - $start ) );
	// Real lot 5 collaborators: single transport, user data normalisation, data profile.
	foreach ( array( 'capi-transport.php', 'capi-user-data.php', 'capi-data-profile.php' ) as $famma_include ) {
		require dirname( __DIR__ ) . '/wp-content/plugins/famma-core/includes/' . $famma_include;
	}
	$client                    = \Famma\Core\Capi::instance();
	$GLOBALS['calls']          = array();
	$GLOBALS['response']       = array(
		'code' => 200,
		'body' => '{"events_received":1}',
	);
	$test_order                = new WC_Order();
	\Famma\Core\Config::$token = '';
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	check( array() === $GLOBALS['calls'] && ! $test_order->get_meta( '_famma_purchase_sent' ), 'Missing token does not send or consume the event.' );
	\Famma\Core\Config::$token = 'test-token-not-a-secret';
	$test_order->status        = 'pending';
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	check( array() === $GLOBALS['calls'], 'Undelivered orders cannot become Purchase events.' );
	$test_order->status = 'delivered';
	$before             = time();
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	$payload = $GLOBALS['calls'][0][1];
	check( ! isset( $payload['test_event_code'] ), 'Global test settings cannot turn a delivered sale into a test.' );
	check( $payload['data'][0]['event_time'] >= $before, 'Purchase time reflects delivery even for an old order.' );
	check( 79.0 === (float) $payload['data'][0]['custom_data']['value'] && 'TND' === $payload['data'][0]['custom_data']['currency'], 'Value and currency match the delivered order.' );
	check( ! isset( $payload['data'][0]['custom_data']['famma_stage'] ), 'Purchase cannot enter the OrderPlaced conversion rule.' );
	check( 'https://example.invalid/checkout/' === $payload['data'][0]['event_source_url'], 'No private order access key is sent to Meta.' );
	check( (bool) $test_order->get_meta( '_famma_purchase_sent' ), 'Confirmed acceptance marks the event sent.' );
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	check( 1 === count( $GLOBALS['calls'] ), 'Repeated delivery does not send twice.' );
	foreach ( array( '{"events_received":0}', '{}', 'invalid-json', '{"events_received":1,"error":{"message":"failure"}}' ) as $body ) {
		$test_order          = new WC_Order();
		$GLOBALS['response'] = array(
			'code' => 200,
			'body' => $body,
		);
		$client->send_purchase( $test_order, 'famma_purchase_901' );
		check( ! $test_order->get_meta( '_famma_purchase_sent' ), 'An unacknowledged response cannot consume the event: ' . $body );
	}
	$test_order          = new WC_Order();
	$GLOBALS['response'] = new WP_Error();
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	$first_time = $test_order->get_meta( '_famma_purchase_event_time' );
	check( ! $test_order->get_meta( '_famma_purchase_sent' ), 'A network failure leaves the event replayable.' );
	$GLOBALS['response'] = array(
		'code' => 200,
		'body' => '{"events_received":1}',
	);
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	check( $first_time === $test_order->get_meta( '_famma_purchase_event_time' ), 'Retry preserves the first delivery timestamp.' );
	check( (bool) $test_order->get_meta( '_famma_purchase_sent' ), 'A successful retry records acceptance.' );
	check( in_array( 'woocommerce_payment_successful_result', $GLOBALS['registered_filters'], true ), 'OrderPlaced is connected to accepted checkout result, not order creation.' );
	$GLOBALS['calls']               = array();
	$GLOBALS['queue_calls']         = array();
	$GLOBALS['jobs']                = array();
	$GLOBALS['orderplaced_enabled'] = false;
	$test_order                     = new WC_Order();
	$test_order->status             = 'pending-confirm';
	$GLOBALS['checkout_order']      = $test_order;
	$result                         = array(
		'result'   => 'success',
		'redirect' => 'https://example.invalid/thanks/',
	);
	check( $result === $client->order_placed_after_checkout( $result, 901 ) && array() === $GLOBALS['calls'] && array() === $GLOBALS['jobs'], 'Default OFF leaves checkout unchanged and sends no customer data.' );
	$GLOBALS['orderplaced_enabled'] = true;
	$failure                        = array( 'result' => 'failure' );
	$client->order_placed_after_checkout( $failure, 901 );
	check( array() === $GLOBALS['calls'], 'Failed checkout cannot send OrderPlaced.' );
	foreach ( array( 'checkout-draft', 'draft', 'failed', 'cancelled', 'trash', 'returned', 'refused', 'failed-delivery' ) as $rejected_status ) {
		$test_order->status = $rejected_status;
		$client->order_placed_after_checkout( $result, 901 );
		check( array() === $GLOBALS['calls'], 'Rejected status cannot send OrderPlaced: ' . $rejected_status );
	}
	$test_order->status      = 'pending-confirm';
	$test_order->created_via = 'admin';
	$client->order_placed_after_checkout( $result, 901 );
	check( array() === $GLOBALS['calls'], 'Admin-created orders cannot send OrderPlaced.' );
	$test_order->created_via = 'checkout';
	$test_order->id          = 0;
	$client->order_placed_after_checkout( $result, 0 );
	check( array() === $GLOBALS['calls'], 'Unsaved orders cannot send OrderPlaced.' );
	$test_order->id            = 901;
	\Famma\Core\Config::$token = '';
	$client->order_placed_after_checkout( $result, 901 );
	check( array() === $GLOBALS['calls'] && ! $test_order->get_meta( '_famma_orderplaced_sent' ), 'Missing token cannot consume OrderPlaced.' );
	check( 1 === count( $GLOBALS['jobs'] ) && (bool) $test_order->get_meta( '_famma_orderplaced_checkout_accepted' ), 'Accepted checkout queues work without a synchronous HTTP call.' );
	$queue_group = getenv( 'FAMMA_TEST_CRON_FALLBACK' ) ? 'wp-cron' : 'famma-capi';
	check( true === $GLOBALS['queue_calls'][0][3] && $queue_group === $GLOBALS['queue_calls'][0][2], 'Selected scheduler receives a unique task: ' . $queue_group );
	$client->order_placed_after_checkout( $result, 901 );
	check( 1 === count( $GLOBALS['jobs'] ), 'Repeated checkout callbacks retain one unique queued job.' );
	\Famma\Core\Config::$token = 'test-token-not-a-secret';
	$client->send_queued_order_placed( 901 );
	$placed = $GLOBALS['calls'][0][1]['data'][0];
	check( 'OrderPlaced' === $placed['event_name'] && 'famma_orderplaced_901' === $placed['event_id'], 'OrderPlaced uses a separate stable event name and identifier.' );
	check( ! isset( $placed['custom_data']['famma_stage'] ) && ! isset( $placed['custom_data']['content_name'] ), 'Standard data profile (lot 5, R3): OrderPlaced no longer carries famma_stage nor content_name.' );
	check( $test_order->get_date_created()->getTimestamp() === $placed['event_time'], 'OrderPlaced carries the creation timestamp.' );
	check( (bool) $test_order->get_meta( '_famma_orderplaced_sent' ) && ! $test_order->get_meta( '_famma_purchase_sent' ), 'OrderPlaced acceptance cannot consume the delivered Purchase.' );
	$client->order_placed_after_checkout( $result, 901 );
	$redirect = 'https://example.invalid/thanks/';
	check( $redirect === $client->order_placed_without_payment( $redirect, $test_order ) && 1 === count( $GLOBALS['calls'] ), 'Repeating successful hooks cannot send OrderPlaced twice or alter redirect.' );
	$test_order->status = 'delivered';
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	check( 2 === count( $GLOBALS['calls'] ) && 'Purchase' === $GLOBALS['calls'][1][1]['data'][0]['event_name'], 'Later delivery sends Purchase independently of OrderPlaced.' );
	foreach ( array( '{"events_received":0}', '{}', 'invalid-json' ) as $body ) {
		$test_order         = new WC_Order();
		$test_order->status = 'pending-confirm';
		$test_order->meta['_famma_orderplaced_checkout_accepted'] = 1;
		$GLOBALS['response']                                      = array(
			'code' => 200,
			'body' => $body,
		);
		$client->send_order_placed( $test_order );
		check( ! $test_order->get_meta( '_famma_orderplaced_sent' ), 'Ambiguous response cannot consume OrderPlaced: ' . $body );
	}
	$test_order         = new WC_Order();
	$test_order->status = 'pending-confirm';
	$test_order->meta['_famma_orderplaced_checkout_accepted'] = 1;
	$GLOBALS['response']                                      = new WP_Error();
	$client->send_order_placed( $test_order );
	$placed_time         = $test_order->get_meta( '_famma_orderplaced_event_time' );
	$GLOBALS['response'] = array(
		'code' => 200,
		'body' => '{"events_received":1}',
	);
	$client->send_order_placed( $test_order );
	check( $placed_time === $test_order->get_meta( '_famma_orderplaced_event_time' ) && (bool) $test_order->get_meta( '_famma_orderplaced_sent' ), 'Retry preserves OrderPlaced timestamp and consumes it only after confirmation.' );
	$test_order                = new WC_Order();
	$test_order->status        = 'pending-confirm';
	$GLOBALS['checkout_order'] = $test_order;
	$client->order_placed_after_checkout( $result, 901 );
	$count                          = count( $GLOBALS['calls'] );
	$GLOBALS['orderplaced_enabled'] = false;
	$client->send_queued_order_placed( 901 );
	check( count( $GLOBALS['calls'] ) === $count, 'Queued worker rechecks the OFF setting before sharing data.' );
	$GLOBALS['orderplaced_enabled'] = true;
	$test_order->status             = 'cancelled';
	$client->send_queued_order_placed( 901 );
	check( count( $GLOBALS['calls'] ) === $count, 'Queued worker rejects an order cancelled after checkout.' );
	$test_order->status      = 'pending-confirm';
	$test_order->created_via = 'admin';
	$client->send_queued_order_placed( 901 );
	check( count( $GLOBALS['calls'] ) === $count, 'Queued worker rechecks the storefront source.' );
	$test_order->created_via = 'checkout';
	unset( $test_order->meta['_famma_orderplaced_checkout_accepted'] );
	$client->send_queued_order_placed( 901 );
	check( count( $GLOBALS['calls'] ) === $count, 'A queued identifier without successful checkout acceptance cannot send.' );
	// Lot 5: browserless order, environment guard, invalid test code, minimal profile, retries.
	$GLOBALS['orderplaced_enabled'] = true;
	$GLOBALS['response']            = array(
		'code' => 200,
		'body' => '{"events_received":1}',
	);
	$test_order                     = new WC_Order();
	$test_order->created_via        = 'admin';
	unset( $test_order->meta['_famma_client_ua'] );
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	$admin_event = $GLOBALS['calls'][ array_key_last( $GLOBALS['calls'] ) ][1]['data'][0];
	check( 'other' === $admin_event['action_source'] && ! isset( $admin_event['event_source_url'] ) && ! isset( $admin_event['user_data']['client_user_agent'] ), 'A browserless (admin) order is sent with action_source other, without event_source_url nor user agent.' );
	$count                       = count( $GLOBALS['calls'] );
	\Famma\Core\Config::$allowed = false;
	$test_order                  = new WC_Order();
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	$last = end( $GLOBALS['capi_outcomes'] );
	check( count( $GLOBALS['calls'] ) === $count && 'blocked_environment' === $last['state'] && ! $test_order->get_meta( '_famma_purchase_sent' ), 'Outside production no request leaves and one blocked_environment line is written.' );
	\Famma\Core\Config::$allowed = true;
	$GLOBALS['test_mode']        = true;
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	$last = end( $GLOBALS['capi_outcomes'] );
	check( count( $GLOBALS['calls'] ) === $count && 'test_code_invalid' === $last['state'], 'Test mode with an invalid code blocks the send instead of sending a real conversion.' );
	$GLOBALS['test_mode']        = false;
	\Famma\Core\Config::$profile = 'minimal';
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	$minimal = $GLOBALS['calls'][ array_key_last( $GLOBALS['calls'] ) ][1]['data'][0]['custom_data'];
	check( array( 'currency', 'value' ) === array_keys( $minimal ), 'Minimal data profile sends only currency and value.' );
	\Famma\Core\Config::$profile = 'standard';
	$GLOBALS['retries']          = array();
	foreach ( array(
		'transient' => array( 503, '{"error":{"message":"down"}}', 1 ),
		'access'    => array( 400, '{"error":{"message":"bad token","code":190}}', 0 ),
		'contract'  => array( 400, '{"error":{"message":"bad param","code":100}}', 0 ),
	) as $label => $fixture ) {
		$test_order          = new WC_Order();
		$GLOBALS['response'] = array(
			'code' => $fixture[0],
			'body' => $fixture[1],
		);
		$before              = count( $GLOBALS['retries'] );
		$client->send_purchase( $test_order, 'famma_purchase_901' );
		check( count( $GLOBALS['retries'] ) - $before === $fixture[2], 'Bounded retry planned only for a transient failure: ' . $label );
	}
	echo 'No network requests or database writes were performed.' . PHP_EOL;
}
