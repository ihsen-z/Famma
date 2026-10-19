<?php
/**
 * Shared WordPress and WooCommerce doubles for the pixel unit tests (lot 1).
 * No database, no network: every outgoing HTTP call is intercepted in memory.
 * Required by scripts/tests-unit/test-*.php; never loaded by WordPress.
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded. The doubles below keep the names
// and signatures of the WordPress and WooCommerce code they replace.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Generic.Classes.DuplicateClassName -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable Squiz.Commenting.ClassComment.WrongStyle, Squiz.Commenting.FunctionComment.WrongStyle -- Line comments label groups of doubles, not docblocks.

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

final class WP_Error {
	public function __construct( private string $code = '', private string $message = '' ) {}
	public function get_error_code(): string {
		return $this->code;
	}
	public function get_error_message(): string {
		return $this->message;
	}
}

/*
 * Hooks: listeners really run, so a test can observe what WordPress core hands
 * to an action such as http_api_debug.
 */
function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['wp_test_hooks'][ $hook ][] = array( $callback, $accepted_args );
	return true;
}
function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	return add_action( $hook, $callback, $priority, $accepted_args );
}
function do_action( string $hook, ...$args ): void {
	foreach ( $GLOBALS['wp_test_hooks'][ $hook ] ?? array() as $listener ) {
		call_user_func_array( $listener[0], array_slice( $args, 0, $listener[1] ) );
	}
}
function apply_filters( string $hook, $value, ...$args ) {
	foreach ( $GLOBALS['wp_test_hooks'][ $hook ] ?? array() as $listener ) {
		$value = call_user_func_array( $listener[0], array_slice( array_merge( array( $value ), $args ), 0, $listener[1] ) );
	}
	return $value;
}
function get_option( string $name, $default_value = false ) {
	$options = $GLOBALS['wp_test_options'] ?? array();
	return array_key_exists( $name, $options ) ? $options[ $name ] : $default_value;
}

// Translation and escaping.
function __( string $text, string $domain = 'default' ): string {
	return $text;
}
function esc_html( $text ): string {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ): string {
	return esc_html( $text );
}
function esc_html__( string $text, string $domain = 'default' ): string {
	return esc_html( $text );
}
function esc_attr__( string $text, string $domain = 'default' ): string {
	return esc_html( $text );
}
function esc_html_e( string $text, string $domain = 'default' ): void {
	echo esc_html( $text );
}

// Sanitizing, following wp-includes/formatting.php (_sanitize_text_fields, wp_strip_all_tags, wp_pre_kses_less_than).
function wp_strip_all_tags( $text, bool $remove_breaks = false ): string {
	$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', (string) $text );
	$text = strip_tags( $text );
	if ( $remove_breaks ) {
		$text = (string) preg_replace( '/[\r\n\t ]+/', ' ', $text );
	}
	return trim( $text );
}
function wp_pre_kses_less_than( string $content ): string {
	return (string) preg_replace_callback(
		'%<[^>]*?((?=<)|>|$)%',
		static function ( array $matches ): string {
			return str_contains( $matches[0], '>' ) ? $matches[0] : esc_html( $matches[0] );
		},
		$content
	);
}
function sanitize_text_field( $str ): string {
	$filtered = (string) $str;
	if ( ! mb_check_encoding( $filtered, 'UTF-8' ) ) {
		return '';
	}
	if ( str_contains( $filtered, '<' ) ) {
		$filtered = wp_strip_all_tags( wp_pre_kses_less_than( $filtered ) );
		$filtered = str_replace( "<\n", "&lt;\n", $filtered );
	}
	$filtered = trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', $filtered ) );
	$found    = false;
	while ( preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) {
		$filtered = str_replace( $match[0], '', $filtered );
		$found    = true;
	}
	if ( $found ) {
		$filtered = trim( (string) preg_replace( '/ +/', ' ', $filtered ) );
	}
	return (string) apply_filters( 'sanitize_text_field', $filtered, $str );
}
function sanitize_title( string $title ): string {
	// Simplified: ASCII slug only (WordPress also transliterates accents).
	return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $title ) ), '-' );
}
function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function wp_json_encode( $value ) {
	return json_encode( $value );
}
function wc_format_decimal( $number, $dp = false ): string {
	return false === $dp ? (string) $number : number_format( (float) $number, (int) $dp, '.', '' );
}
function wp_generate_uuid4(): string {
	return sprintf( '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', random_int( 0, 0xffff ), random_int( 0, 0xffff ), random_int( 0, 0xffff ), random_int( 0, 0xfff ), random_int( 0x8000, 0xbfff ), random_int( 0, 0xffff ), random_int( 0, 0xffff ), random_int( 0, 0xffff ) );
}
function current_time( string $type ): string {
	return '2026-10-19 12:00:00';
}

// URLs, redirects and capabilities of the admin screen.
function home_url( string $path = '' ): string {
	return 'https://qa.invalid' . $path;
}
function admin_url( string $path = '' ): string {
	return 'https://qa.invalid/wp-admin/' . $path;
}
function wc_get_checkout_url(): string {
	return 'https://qa.invalid/commande/';
}
function add_query_arg( string $key, string $value, string $url ): string {
	return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . rawurlencode( $key ) . '=' . rawurlencode( $value );
}
final class Famma_Test_Redirect extends RuntimeException {}
function wp_safe_redirect( string $location ): bool {
	// The real call is followed by exit: the exception stands in for it.
	throw new Famma_Test_Redirect( $location );
}
function check_admin_referer( string $action ): int {
	return 1;
}
function current_user_can( string $capability ): bool {
	return true;
}
function wp_die( $message = '' ): void {
	throw new RuntimeException( 'wp_die: ' . (string) $message );
}

// Intercepted HTTP API: no request ever leaves the process.
function wp_remote_post( string $url, array $args = array() ) {
	$GLOBALS['wp_test_http'][] = array(
		'url'  => $url,
		'args' => $args,
	);
	$response                  = $GLOBALS['wp_test_http_response'] ?? new WP_Error( 'http_request_failed', 'No fixture' );
	// WP_Http::request() fires this action with the request arguments, body included.
	do_action( 'http_api_debug', $response, 'response', 'WpOrg\Requests\Requests', $args, $url );
	return $response;
}
function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}
function wp_remote_retrieve_response_code( $response ) {
	return is_array( $response ) && isset( $response['response']['code'] ) ? $response['response']['code'] : '';
}
function wp_remote_retrieve_body( $response ): string {
	return is_array( $response ) && isset( $response['body'] ) ? (string) $response['body'] : '';
}

// WooCommerce logger (WooCommerce > État > Journaux).
final class WC_Test_Logger {
	public function log( string $level, string $message, array $context = array() ): void {
		$GLOBALS['wc_test_log'][] = array(
			'level'   => $level,
			'source'  => (string) ( $context['source'] ?? '' ),
			'message' => $message,
		);
	}
}
function wc_get_logger(): WC_Test_Logger {
	return new WC_Test_Logger();
}

// WooCommerce catalogue, cart and orders.
class WC_Product {
	public function __construct( protected int $id, protected string $sku = '', protected string $name = '', protected string $slug = '', protected float $price = 0.0 ) {
		$GLOBALS['wc_test_products'][ $id ] = $this;
	}
	public function get_id(): int {
		return $this->id;
	}
	public function get_sku( string $context = 'view' ): string {
		return $this->sku;
	}
	public function get_name(): string {
		return $this->name;
	}
	public function get_slug(): string {
		return $this->slug;
	}
	public function get_parent_id(): int {
		return 0;
	}
	public function get_price(): string {
		return (string) $this->price;
	}
}
final class WC_Product_Variation extends WC_Product {
	public function __construct( int $id, private WC_Product $parent_product, string $sku, string $name, float $price ) {
		parent::__construct( $id, $sku, $name, $parent_product->get_slug() . '-' . $id, $price );
	}
	public function get_parent_id(): int {
		return $this->parent_product->get_id();
	}
	public function get_sku( string $context = 'view' ): string {
		// WooCommerce: a variation inherits its parent's SKU in view context.
		return 'view' === $context && '' === $this->sku ? $this->parent_product->get_sku( $context ) : $this->sku;
	}
}
function wc_get_product( $product_id = false ) {
	if ( false === $product_id ) {
		return $GLOBALS['wc_test_current_product'] ?? null;
	}
	return $GLOBALS['wc_test_products'][ (int) $product_id ] ?? false;
}
function wc_get_price_to_display( WC_Product $product ): float {
	return (float) $product->get_price();
}
function get_woocommerce_currency(): string {
	return 'TND';
}
function get_woocommerce_currencies(): array {
	return array( 'TND' => 'Dinar tunisien' );
}
function is_product(): bool {
	return ! empty( $GLOBALS['wc_test_is_product'] );
}
function is_checkout(): bool {
	return ! empty( $GLOBALS['wc_test_is_checkout'] );
}
function is_order_received_page(): bool {
	return false;
}
function wp_doing_ajax(): bool {
	return ! empty( $GLOBALS['wc_test_doing_ajax'] );
}
final class WC_Test_Session {
	private array $data = array();
	public function get( string $key, $default_value = null ) {
		return $this->data[ $key ] ?? $default_value;
	}
	public function set( string $key, $value ): void {
		$this->data[ $key ] = $value;
	}
}
final class WC_Test_Cart {
	public function __construct( private array $lines = array() ) {}
	public function get_cart(): array {
		return $this->lines;
	}
	public function is_empty(): bool {
		return array() === $this->lines;
	}
	public function get_cart_contents_count(): int {
		return (int) array_sum( array_column( $this->lines, 'quantity' ) );
	}
	public function get_total( string $context = 'view' ): float {
		$total = 0.0;
		foreach ( $this->lines as $line ) {
			$total += (float) $line['data']->get_price() * (float) $line['quantity'];
		}
		return $total;
	}
}
final class WC_Test_Store {
	public function __construct( public ?WC_Test_Cart $cart = null, public ?WC_Test_Session $session = null ) {}
}
// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- WooCommerce's own function name.
function WC(): WC_Test_Store {
	$GLOBALS['wc_test_store'] ??= new WC_Test_Store( new WC_Test_Cart(), new WC_Test_Session() );
	return $GLOBALS['wc_test_store'];
}
final class WC_Order_Item_Product {
	public function __construct( private WC_Product $product, private int $quantity = 1 ) {}
	public function get_product(): WC_Product {
		return $this->product;
	}
	public function get_quantity(): int {
		return $this->quantity;
	}
	public function get_name(): string {
		return $this->product->get_name();
	}
	public function get_subtotal(): float {
		return (float) $this->product->get_price() * $this->quantity;
	}
}
final class WC_Order {
	public array $meta                 = array();
	public array $notes                = array();
	public array $items                = array();
	public string $status              = 'delivered';
	public string $created_via         = 'checkout';
	public string $email               = '';
	public string $phone               = '';
	public string $first_name          = '';
	public string $last_name           = '';
	public string $city                = '';
	public string $state               = '';
	public string $country             = '';
	public string $payment             = 'cod';
	public ?DateTimeImmutable $paid_at = null;
	public function __construct( public int $id = 901 ) {
		$GLOBALS['wc_test_orders'][ $id ] = $this;
	}
	public function get_id(): int {
		return $this->id;
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
	public function get_status(): string {
		return $this->status;
	}
	public function payment_complete(): bool {
		$this->notes[] = 'payment_complete';
		return true;
	}
	public function get_payment_method(): string {
		return $this->payment;
	}
	public function get_date_paid(): ?DateTimeImmutable {
		return $this->paid_at;
	}
	public function get_date_modified(): ?DateTimeImmutable {
		return $this->paid_at;
	}
	public function get_created_via(): string {
		return $this->created_via;
	}
	public function get_items(): array {
		return $this->items;
	}
	public function get_line_subtotal( WC_Order_Item_Product $item, bool $inc_tax = false, bool $round = true ): float {
		return $item->get_subtotal();
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
		return '49.000';
	}
	public function get_date_created(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-10-19 10:00:00' );
	}
	public function add_order_note( string $note ): void {
		$this->notes[] = $note;
	}
}
function wc_get_order( int $id ) {
	return $GLOBALS['wc_test_orders'][ $id ] ?? false;
}

/**
 * Loads the real famma-core sources and reaches their private methods.
 */
final class Famma_Unit_Harness {

	/**
	 * Evaluates one class of the consolidated plugin file, as the existing isolated tests do.
	 *
	 * @param string $name Class to load.
	 * @param string $next Class that follows it in famma-core.php (end boundary).
	 * @throws RuntimeException When the boundaries are not found.
	 */
	public static function load_core_class( string $name, string $next ): void {
		$source = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/famma-core.php' );
		$start  = strpos( $source, 'final class ' . $name . ' {' );
		$end    = false === $start ? false : strpos( $source, 'final class ' . $next . ' {', $start );
		if ( false === $start || false === $end ) {
			throw new RuntimeException( 'Source boundaries not found for ' . $name );
		}
		// phpcs:ignore Squiz.PHP.Eval.Discouraged -- Runs the real plugin source read from the plugin file, never external input.
		eval( 'namespace Famma\\Core;' . substr( $source, $start, $end - $start ) );
	}

	/**
	 * Requires one real file of famma-core/includes.
	 *
	 * @param string $file File name.
	 */
	public static function load_include( string $file ): void {
		require_once ABSPATH . 'wp-content/plugins/famma-core/includes/' . $file;
	}

	/**
	 * Instance created without its constructor, so no hook is registered.
	 *
	 * @param string $class_name Fully qualified class.
	 * @return object
	 */
	public static function bare( string $class_name ): object {
		return ( new ReflectionClass( $class_name ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Calls a private or protected method of the real code.
	 *
	 * @param object|string $target Instance, or class name for a static method.
	 * @param string        $method Method name.
	 * @param array         $args   Arguments.
	 * @return mixed
	 */
	public static function call( $target, string $method, array $args = array() ) {
		$reflection = new ReflectionMethod( $target, $method );
		return $reflection->invokeArgs( is_object( $target ) ? $target : null, $args );
	}
}
