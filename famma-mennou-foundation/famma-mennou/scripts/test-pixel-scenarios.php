<?php
/**
 * Extended Pixel / CAPI scenario tests with real Config normalization; no WordPress database or real HTTP requests.
 * Run with: php scripts/test-pixel-scenarios.php
 * The source class is extracted so its actual implementation is exercised.
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded. The doubles below keep the names
// and signatures of the WordPress and WooCommerce code they replace.
// phpcs:disable Universal.Namespaces -- The FAMMA collaborator doubles and the global WordPress doubles must share this single runner.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Generic.Classes.DuplicateClassName -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.

namespace Famma\Core {
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
			return false;
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
		public array $meta         = array( '_famma_client_ua' => 'ScenarioDefaultAgent' );
		public array $notes        = array();
		public string $status      = 'delivered';
		public string $created_via = 'checkout';
		public int $id             = 901;
		public string $email       = 'isolated@example.invalid';
		public string $phone       = '00000000';
		public string $first_name  = 'Test';
		public string $last_name   = 'Isolated';
		public string $city        = 'Tunis';
		public string $state       = 'TN-11';
		public string $country     = 'TN';
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
			return $this->email;
		}
		public function get_billing_phone(): string {
			return $this->phone;
		}
		public function get_billing_first_name(): string {
			return $this->first_name;
		}
		public function get_billing_last_name(): string {
			return $this->last_name;
		}
		public function get_billing_city(): string {
			return $this->city;
		}
		public function get_billing_state(): string {
			return $this->state;
		}
		public function get_billing_country(): string {
			return $this->country;
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
			return $GLOBALS['network_message'] ?? 'Isolated network error';
		}
	}
	function add_action( ...$args ): void {}
	function add_filter( string $name, ...$args ): void {
		$GLOBALS['registered_filters'][] = $name;
	}
	function has_filter( string $name, $callback = false ): bool {
		return in_array( $name, $GLOBALS['registered_filters'] ?? array(), true );
	}
	function get_option( string $name, $default_value = false ) {
		return 'famma_meta_orderplaced_enabled' === $name ? ( $GLOBALS['orderplaced_enabled'] ?? $default_value ) : ( $GLOBALS['options'][ $name ] ?? $default_value );
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
			$GLOBALS['queue_calls'][] = array( $hook, $args, $group, $unique );
			if ( ! empty( $GLOBALS['queue_failure'] ) ) {
				return 0;
			}
			$GLOBALS['jobs'][ $args['order_id'] ] = $args;
			return 1;
		}
	} else {
		function wp_next_scheduled( string $hook, array $args ): bool {
			return isset( $GLOBALS['jobs'][ $args['order_id'] ] );
		}
		function wp_schedule_single_event( int $time, string $hook, array $args ): bool {
			$GLOBALS['queue_calls'][] = array( $hook, $args, 'wp-cron', true );
			if ( ! empty( $GLOBALS['queue_failure'] ) ) {
				return false;
			}
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
	function update_option( string $name, $value, $autoload = null ): bool {
		$GLOBALS['options'][ $name ] = $value;
		return true;
	}
	function wp_salt( string $scheme = 'auth' ): string {
		return 'scenario-salt-' . $scheme;
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
	 * Stop the run on the first broken contract, otherwise count and report it as passed.
	 *
	 * @param bool   $condition Contract outcome.
	 * @param string $message   Contract description.
	 * @throws \RuntimeException When the contract does not hold.
	 */
	function check( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
		$GLOBALS['assertions'] = ( $GLOBALS['assertions'] ?? 0 ) + 1;
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
	// phpcs:ignore Squiz.PHP.Eval.Discouraged -- Runs the real plugin source read from the plugin file, never external input.
	eval( 'namespace Famma\\Core;' . substr( $source, $start, $end - $start ) );
	$config_start = strpos( $source, 'final class Config {' );
	$config_end   = strpos( $source, 'final class Order_Statuses', $config_start );
	// phpcs:ignore Squiz.PHP.Eval.Discouraged -- Runs the real plugin source read from the plugin file, never external input.
	eval( 'namespace Famma\\Core;' . substr( $source, $config_start, $config_end - $config_start ) );
	// Config::international_phone() now delegates to the real Tunisia::normalize_phone().
	$tunisia_start = strpos( $source, 'final class Tunisia {' );
	$tunisia_end   = strpos( $source, 'final class WhatsApp', $tunisia_start );
	// phpcs:ignore Squiz.PHP.Eval.Discouraged -- Runs the real plugin source read from the plugin file, never external input.
	eval( 'namespace Famma\\Core;' . substr( $source, $tunisia_start, $tunisia_end - $tunisia_start ) );
	// Real lot 5 collaborators: token vault, single transport, user data normalisation, data profile.
	foreach ( array( 'capi-token-vault.php', 'capi-transport.php', 'capi-user-data.php', 'capi-data-profile.php' ) as $famma_include ) {
		require dirname( __DIR__ ) . '/wp-content/plugins/famma-core/includes/' . $famma_include;
	}
	$GLOBALS['options']                            = array(
		'famma_meta_pixel_id'        => '123456789',
		'famma_meta_access_token'    => 'test-token-not-a-secret',
		'famma_meta_test_event_code' => 'TEST_ISOLATED',
	);
	$client                                        = \Famma\Core\Capi::instance();
	$GLOBALS['calls']                              = array();
	$GLOBALS['response']                           = array(
		'code' => 200,
		'body' => '{"events_received":1}',
	);
	$test_order                                    = new WC_Order();
	$GLOBALS['options']['famma_meta_access_token'] = '';
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	check( array() === $GLOBALS['calls'] && ! $test_order->get_meta( '_famma_purchase_sent' ), 'Missing token does not send or consume the event.' );
	$GLOBALS['options']['famma_meta_access_token'] = 'test-token-not-a-secret';
	$test_order->status                            = 'pending';
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
	$test_order->id                                = 901;
	$GLOBALS['options']['famma_meta_access_token'] = '';
	$client->order_placed_after_checkout( $result, 901 );
	check( array() === $GLOBALS['calls'] && ! $test_order->get_meta( '_famma_orderplaced_sent' ), 'Missing token cannot consume OrderPlaced.' );
	check( 1 === count( $GLOBALS['jobs'] ) && (bool) $test_order->get_meta( '_famma_orderplaced_checkout_accepted' ), 'Accepted checkout queues work without a synchronous HTTP call.' );
	$queue_group = getenv( 'FAMMA_TEST_CRON_FALLBACK' ) ? 'wp-cron' : 'famma-capi';
	check( true === $GLOBALS['queue_calls'][0][3] && $queue_group === $GLOBALS['queue_calls'][0][2], 'Selected scheduler receives a unique task: ' . $queue_group );
	$client->order_placed_after_checkout( $result, 901 );
	check( 1 === count( $GLOBALS['jobs'] ), 'Repeated checkout callbacks retain one unique queued job.' );
	$GLOBALS['options']['famma_meta_access_token'] = 'test-token-not-a-secret';
	$client->send_queued_order_placed( 901 );
	$placed = $GLOBALS['calls'][0][1]['data'][0];
	check( 'OrderPlaced' === $placed['event_name'] && 'famma_orderplaced_901' === $placed['event_id'], 'OrderPlaced uses a separate stable event name and identifier.' );
	check( ! isset( $placed['custom_data']['famma_stage'] ) && ! isset( $placed['custom_data']['famma_product'] ), 'Standard data profile (lot 5, R3): OrderPlaced no longer carries famma_stage nor famma_product.' );
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
	// Additional scenarios exercise real Config rather than a phone-normalization stub.
	foreach ( array( '20 000 000', '+216 20 000 000', '00216-20-000-000', '21620000000', '20.000.000' ) as $phone ) {
		check( '21620000000' === \Famma\Core\Config::international_phone( $phone ), 'Synthetic Tunisian phone representations normalize identically.' );
	}
	check( '' === \Famma\Core\Config::international_phone( 'letters only' ), 'Unusable phone characters normalize to empty.' );
	$test_order                           = new WC_Order();
	$test_order->email                    = '  SYNTHETIC@EXAMPLE.INVALID  ';
	$test_order->phone                    = '+216 20 000 000';
	$test_order->first_name               = '  TEST  ';
	$test_order->meta['_famma_fbp']       = 'fb.1.1700000000000.synthetic';
	$test_order->meta['_famma_fbc']       = 'fb.1.1700000000000.syntheticclick';
	$test_order->meta['_famma_client_ip'] = '192.0.2.10';
	$test_order->meta['_famma_client_ua'] = 'SyntheticScenarioAgent';
	$GLOBALS['response']                  = array(
		'code' => 201,
		'body' => '{"events_received":1}',
	);
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	$last = $GLOBALS['calls'][ array_key_last( $GLOBALS['calls'] ) ];
	$data = $last[1]['data'][0]['user_data'];
	check( hash( 'sha256', 'synthetic@example.invalid' ) === $data['em'], 'Real Config and Capi normalize email case and spaces before hashing.' );
	check( hash( 'sha256', '21620000000' ) === $data['ph'], 'Tunisian phone is internationalized before hashing.' );
	check( hash( 'sha256', 'test' ) === $data['fn'], 'ASCII first name is lowercased and trimmed before hashing.' );
	check( $test_order->meta['_famma_fbp'] === $data['fbp'] && $test_order->meta['_famma_fbc'] === $data['fbc'], 'Meta click/browser signals retain their expected raw format.' );
	check( '192.0.2.10' === $data['client_ip_address'] && 'SyntheticScenarioAgent' === $data['client_user_agent'], 'Stored synthetic browser context is reused at delivery.' );
	check( ! str_contains( $last[0], 'test-token-not-a-secret' ) && isset( $last[1]['access_token'] ), 'Token is confined to the server request body, not its URL.' );
	check( (bool) $test_order->get_meta( '_famma_purchase_sent' ), 'HTTP 201 with acknowledgement is a valid acceptance.' );
	foreach ( array( 400, 401, 429, 500, 503 ) as $code ) {
		$test_order          = new WC_Order();
		$GLOBALS['response'] = array(
			'code' => $code,
			'body' => '{"error":{"message":"Synthetic rejection"}}',
		);
		$before              = count( $GLOBALS['calls'] );
		$client->send_purchase( $test_order, 'famma_purchase_901' );
		check( ! $test_order->get_meta( '_famma_purchase_sent' ) && 1 === $test_order->get_meta( '_famma_capi_attempts' ) && count( $GLOBALS['calls'] ) === $before + 1, 'Rejected HTTP response remains unsent with one recorded attempt: ' . $code );
	}
	$test_order                               = new WC_Order();
	$test_order->meta['_famma_capi_attempts'] = 5;
	$test_order->meta['_famma_orderplaced_checkout_accepted'] = 1;
	$before = count( $GLOBALS['calls'] );
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	check( count( $GLOBALS['calls'] ) === $before, 'Five Purchase failures stop further attempts.' );
	$GLOBALS['response'] = array(
		'code' => 200,
		'body' => '{"events_received":1}',
	);
	$client->send_order_placed( $test_order );
	check( count( $GLOBALS['calls'] ) === $before + 1, 'Purchase attempt exhaustion does not exhaust OrderPlaced.' );
	$test_order = new WC_Order();
	$test_order->meta['_famma_orderplaced_checkout_accepted'] = 1;
	$test_order->meta['_famma_orderplaced_attempts']          = 5;
	$before = count( $GLOBALS['calls'] );
	$client->send_order_placed( $test_order );
	check( count( $GLOBALS['calls'] ) === $before, 'Five OrderPlaced failures stop further attempts.' );
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	check( count( $GLOBALS['calls'] ) === $before + 1, 'OrderPlaced attempt exhaustion does not exhaust Purchase.' );
	$test_order                                = new WC_Order();
	$GLOBALS['options']['famma_meta_pixel_id'] = '';
	$before                                    = count( $GLOBALS['calls'] );
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	check( count( $GLOBALS['calls'] ) === $before && ! $test_order->get_meta( '_famma_purchase_sent' ), 'Missing Pixel ID prevents requests and leaves event unconsumed.' );
	$GLOBALS['options']['famma_meta_pixel_id'] = '123456789';
	$test_order                                = new WC_Order();
	$test_order->email                         = '';
	$test_order->phone                         = '';
	$test_order->first_name                    = '';
	$test_order->last_name                     = '';
	$test_order->city                          = '';
	$test_order->state                         = '';
	$test_order->country                       = '';
	unset( $test_order->meta['_famma_client_ua'] );
	$GLOBALS['response'] = array(
		'code' => 400,
		'body' => '{"error":{"message":"Synthetic missing identifiers"}}',
	);
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	$last = $GLOBALS['calls'][ array_key_last( $GLOBALS['calls'] ) ];
	check( array() === $last[1]['data'][0]['user_data'] && ! $test_order->get_meta( '_famma_purchase_sent' ), 'Absent matching identifiers are omitted and rejection cannot count as success.' );
	$GLOBALS['checkout_order'] = false;
	$before                    = count( $GLOBALS['calls'] );
	$client->send_queued_order_placed( 901 );
	check( count( $GLOBALS['calls'] ) === $before, 'Deleted order is safely ignored by a queued worker.' );
	// Findings reproduce error-handling limitations, without changing production sources.
	$test_order                = new WC_Order();
	$test_order->status        = 'pending-confirm';
	$GLOBALS['checkout_order'] = $test_order;
	$GLOBALS['jobs']           = array();
	$GLOBALS['queue_failure']  = true;
	$client->order_placed_after_checkout( $result, 901 );
	$queue_outcome = end( $GLOBALS['capi_outcomes'] );
	check( array() === $GLOBALS['jobs'] && (bool) $test_order->get_meta( '_famma_orderplaced_checkout_accepted' ) && 'queue_failed' === $queue_outcome['state'], 'Lot 5 (D7): a failed scheduling now writes one queue_failed journal line.' );
	$GLOBALS['queue_failure']   = false;
	$test_order                 = new WC_Order();
	$GLOBALS['network_message'] = 'Synthetic error containing test-token-not-a-secret';
	$GLOBALS['response']        = new WP_Error();
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	check( ! str_contains( implode( ' ', $test_order->notes ), 'test-token-not-a-secret' ), 'Order notes keep a fixed outcome summary, never the network error text (no secret).' );
	unset( $GLOBALS['network_message'] );
	$test_order             = new WC_Order();
	$test_order->first_name = 'ÉRIC';
	$test_order->city       = 'Béja';
	$GLOBALS['response']    = array(
		'code' => 200,
		'body' => '{"events_received":1}',
	);
	$client->send_purchase( $test_order, 'famma_purchase_901' );
	$last = $GLOBALS['calls'][ array_key_last( $GLOBALS['calls'] ) ];
	check( hash( 'sha256', 'éric' ) === $last[1]['data'][0]['user_data']['fn'] && hash( 'sha256', 'béja' ) === $last[1]['data'][0]['user_data']['ct'], 'Lot 5 (A13): non-ASCII name and city are lowercased in UTF-8, accents kept.' );
	echo 'ASSERTIONS=' . $GLOBALS['assertions'] . PHP_EOL;
	echo 'No network requests or database writes were performed.' . PHP_EOL;
}
