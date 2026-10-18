<?php
/**
 * Isolated FAMMA checkout/COD contract scenarios. No WP load, DB, HTTP or email.
 * Real plugin classes, fake WordPress/WooCommerce collaborators. This does NOT
 * validate the WooCommerce core checkout, inventory SQL or gateway implementation.
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded. The doubles below keep the names
// and signatures of the WordPress and WooCommerce code they replace.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.NamingConventions.ValidFunctionName, Generic.Classes.DuplicateClassName -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['hooks']      = array();
$GLOBALS['transients'] = array();
$GLOBALS['notices']    = array();
$GLOBALS['exempt']     = false;
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	add_action( $hook, $callback, $priority, $args );
}
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $cb ) {
		$value = $cb( $value, ...$args );
	}
	return $value;
}
function do_action( $hook, ...$args ) {
	foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $cb ) {
		$cb( ...$args );
	}
}
function __( $text, $domain = '' ) {
	return $text;
}
function _x( $text, $context, $domain = '' ) {
	return $text;
}
function current_user_can( $cap ) {
	return $GLOBALS['exempt'];
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( $value ) );
}
function wp_unslash( $value ) {
	return $value;
}
function wp_salt( $scheme ) {
	return 'isolated-test-salt';
}
function get_transient( $key ) {
	return $GLOBALS['transients'][ $key ] ?? false;
}
function set_transient( $key, $value, $ttl ) {
	$GLOBALS['transients'][ $key ] = $value;
	return true;
}
function wc_add_notice( $value, $type ) {
	$GLOBALS['notices'][] = $value;
}
function wc_get_order( $id ) {
	return $GLOBALS['test_order'] ?? false;
}
function wc_maybe_reduce_stock_levels( $id ) {
	$GLOBALS['stock_calls'][] = array( 'reduce', $id );
}
function wc_maybe_increase_stock_levels( $id ) {
	$GLOBALS['stock_calls'][] = array( 'increase', $id );
}
function WC() {
	return $GLOBALS['wc'];
}
function esc_html( $text ) {
	return htmlspecialchars( $text );
}
function absint( $value ) {
	return abs( (int) $value );
}
function wc_stock_amount( $value ) {
	return $value;
}
function wp_verify_nonce( $value, $action ) {
	return 'isolated-valid' === $value;
}
function wc_get_product( $id ) {
	return $GLOBALS['product'];
}
function get_post_status( $id ) {
	return 'publish';
}
function wc_maybe_define_constant( $key, $value ) {
	if ( ! defined( $key ) ) {
		define( $key, $value );
	}
}
function wp_die( $message = 0 ) {
	throw new FakeStop();
}
function wp_send_json( $data, $status = 200 ) {
	throw new FakeResponse( $data, $status );
}
function wp_send_json_error( $data, $status = 200 ) {
	wp_send_json(
		array(
			'success' => false,
			'data'    => $data,
		),
		$status
	);
}
class FakeStop extends Exception {}
class FakeResponse extends Exception {
	public function __construct( public array $data, public int $status = 200 ) {
		parent::__construct( 'isolated-response' );
	}
}
class WP_Error {
	public array $errors = array();
	public function add( $code, $message ) {
		$this->errors[ $code ] = $message;
	}
}
class WC_Order {
	public array $meta        = array();
	public array $notes       = array();
	public string $status     = 'delivered';
	public int $payment_calls = 0;
	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? '';
	}
	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}
	public function add_order_note( $value ) {
		$this->notes[] = $value;
	}
	public function save() {}
	public function has_status( $statuses ) {
		return in_array( $this->status, (array) $statuses, true );
	}
	public function payment_complete() {
		++$this->payment_calls;
	}
	public function get_billing_phone() {
		return '22345678';
	}
}
class WC_Product {
	public function is_type( $type ) {
		return false;
	}
}
class FakeCart {
	public array $items   = array();
	public array $removed = array();
	public bool $allow    = true;
	public function get_cart() {
		return $this->items;
	}
	public function remove_cart_item( $key ) {
		$this->removed[ $key ] = $this->items[ $key ];
		unset( $this->items[ $key ] );
	}
	public function restore_cart_item( $key ) {
		$this->items[ $key ] = $this->removed[ $key ];
	}
	public function add_to_cart( $id, $qty ) {
		if ( ! $this->allow ) {
			return false;
		}
		$this->items[ 'p' . $id ] = array(
			'product_id'   => $id,
			'quantity'     => $qty,
			'variation_id' => 0,
		);
		return 'p' . $id;
	}
}
class FakeCheckout {
	public int $calls = 0;
	public function process_checkout() {
		++$this->calls;
	}
}
class FakeWC {
	public FakeCart $cart;
	public object $session;
	public FakeCheckout $checkout;
	public function __construct() {
		$this->cart     = new FakeCart();
		$this->session  = new stdClass();
		$this->checkout = new FakeCheckout();
	}
	public function checkout() {
		return $this->checkout;
	}
}

/**
 * Evaluate one real class from the plugin source, without bootstrapping the plugin side effects.
 *
 * @param string $source Plugin file source.
 * @param string $name   Class to extract into the Famma\Core namespace.
 * @throws RuntimeException When the class is missing from the source.
 */
function load_source_class( $source, $name ) {
	$start = strpos( $source, 'final class ' . $name . ' {' );
	if ( false === $start ) {
		throw new RuntimeException( 'Missing ' . $name );
	}
	$tail   = substr( $source, $start );
	$tokens = token_get_all( '<?php ' . $tail );
	$text   = '';
	$depth  = 0;
	$opened = false;
	foreach ( $tokens as $token ) {
		if ( is_array( $token ) && T_OPEN_TAG === $token[0] ) {
			continue;
		}
		$part  = is_array( $token ) ? $token[1] : $token;
		$text .= $part;
		if ( ! is_array( $token ) && '{' === $token ) {
			++$depth;
			$opened = true;
		}
		if ( ! is_array( $token ) && '}' === $token ) {
			--$depth;
			if ( $opened && 0 === $depth ) {
				break;
			}
		}
	}
	// phpcs:ignore Squiz.PHP.Eval.Discouraged -- Runs the real class source read from the plugin file, never external input.
	eval( 'namespace Famma\\Core; ' . $text );
}

/**
 * Count one contract outcome and report it.
 *
 * @param string $label Contract description.
 * @param bool   $ok    Contract outcome.
 */
function check( $label, $ok ) {
	global $checks, $fails;
	++$checks;
	if ( ! $ok ) {
		++$fails;
	}
	echo ( $ok ? 'PASS' : 'FAIL' ) . ' ' . $label . PHP_EOL;
}
// phpcs:enable

$GLOBALS['wc']          = new FakeWC();
$GLOBALS['product']     = new WC_Product();
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
$_POST                  = array();
$source                 = file_get_contents( __DIR__ . '/../wp-content/plugins/famma-core/famma-core.php' );
foreach ( array( 'Config', 'Order_Statuses', 'Stock', 'Tunisia' ) as $name ) {
	load_source_class( $source, $name );
}
require __DIR__ . '/../wp-content/plugins/famma-core/includes/express-checkout.php';
require __DIR__ . '/../wp-content/plugins/famma-core/includes/anti-abuse.php';
$checks   = 0;
$fails    = 0;
$tn       = Famma\Core\Tunisia::instance();
$statuses = Famma\Core\Order_Statuses::instance();
$stock    = Famma\Core\Stock::instance();
$anti     = Famma\Core\Anti_Abuse::instance();
$express  = Famma\Core\Express_Checkout::instance();
foreach ( array( '22345678', '22 345 678', '+21622345678', '0021622345678', '21622345678' ) as $phone ) {
	$e = new WP_Error();
	$tn->validate_phone( array( 'billing_phone' => $phone ), $e );
	check( 'phone accepted ' . $phone, ! $e->errors );
}
foreach ( array( '12345678', '2234567', '223456789', '+3322345678', 'abcdefgh' ) as $phone ) {
	$e = new WP_Error();
	$tn->validate_phone( array( 'billing_phone' => $phone ), $e );
	check( 'phone rejected ' . $phone, isset( $e->errors['billing_phone_invalid'] ) );
}
$e = new WP_Error();
$tn->require_email_for_account(
	array(
		'createaccount' => 1,
		'billing_email' => '',
	),
	$e
);
check( 'account requires email', isset( $e->errors['famma_account_email'] ) );
$e = new WP_Error();
$tn->require_email_for_account(
	array(
		'createaccount' => 0,
		'billing_email' => '',
	),
	$e
);
check( 'guest email optional', ! $e->errors );
$name = $tn->split_full_name(
	array(
		'billing_first_name'  => 'Mohamed Ben Ali',
		'billing_last_name'   => '',
		'shipping_first_name' => '',
		'shipping_last_name'  => '',
	)
);
check( 'full name aligned shipping', 'Mohamed' === $name['billing_first_name'] && 'Ben Ali' === $name['billing_last_name'] && 'Ben Ali' === $name['shipping_last_name'] );
check( '24 governorates', count( $tn->governorates( array() )['TN'] ) === 24 );
check(
	'default governorate blank',
	'' === $tn->blank_default_state(
		array(
			'country' => 'TN',
			'state'   => 'TN-11',
		)
	)['state']
);
check( 'country forced TN', 'TN' === $tn->force_country( array( 'billing_country' => 'FR' ) )['billing_country'] );
check( 'COD initial unpaid pending-confirm', $statuses->cod_initial_status() === 'pending-confirm' );
$paid = $statuses->paid_statuses( array( 'processing', 'completed' ) );
check( 'processing removed delivered paid', ! in_array( 'processing', $paid, true ) && in_array( 'delivered', $paid, true ) );
$reports = $statuses->reporting_statuses( array( 'processing', 'on-hold' ) );
check( 'revenue statuses delivered/completed only', array( 'delivered', 'completed' ) === $reports );
$GLOBALS['test_order'] = new WC_Order();
$statuses->mark_cod_paid_on_delivery( 1 );
$statuses->mark_cod_paid_on_delivery( 1 );
check( 'COD payment hook sequential idempotency', 1 === $GLOBALS['test_order']->payment_calls && $GLOBALS['test_order']->get_meta( '_famma_cod_paid' ) === 'yes' );
check( 'payment status remains delivered', $statuses->stay_delivered_when_paid( 'completed', 1, $GLOBALS['test_order'] ) === 'delivered' );
$GLOBALS['stock_calls'] = array();
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Replay the actual WooCommerce status action.
do_action( 'woocommerce_order_status_confirmed', 1 );
foreach ( array( 'cancelled', 'refused', 'returned', 'failed-delivery' ) as $release_status ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Replay the actual WooCommerce status actions.
	do_action( 'woocommerce_order_status_' . $release_status, 1 );
}
check( 'stock delegates confirm/release hooks', array( array( 'reduce', 1 ), array( 'increase', 1 ), array( 'increase', 1 ), array( 'increase', 1 ), array( 'increase', 1 ) ) === $GLOBALS['stock_calls'] );
check( 'quantity input cap ten', $anti->cap_max_quantity( -1 ) === 10 );
$GLOBALS['wc']->cart->items = array(
	'a' => array(
		'product_id' => 5,
		'quantity'   => 8,
	),
);
check( 'cart aggregate max ten rejected', ! $anti->validate_add_to_cart( true, 5, 3 ) );
check( 'cart aggregate ten allowed', $anti->validate_add_to_cart( true, 5, 2 ) );
check( 'cart update eleven rejected', ! $anti->validate_cart_update( true, 'a', array(), 11 ) );
$_POST = array( 'famma_hp_site' => 'bot' );
$e     = new WP_Error();
$anti->validate_checkout( array( 'billing_phone' => '22345678' ), $e );
check( 'honeypot blocked', isset( $e->errors['famma_order_refused'] ) );
$_POST                      = array();
$GLOBALS['wc']->cart->items = array();
for ( $i = 0; $i < 3; $i++ ) {
	$anti->count_order( new WC_Order() );
}
$e = new WP_Error();
$anti->validate_checkout( array( 'billing_phone' => '+21622345678' ), $e );
check( 'normalized phone third-order limit', isset( $e->errors['famma_order_phone_limit'] ) );
$GLOBALS['transients'] = array();
for ( $i = 0; $i < 5; $i++ ) {
	$anti->count_order( new WC_Order() );
}
$e = new WP_Error();
$anti->validate_checkout( array( 'billing_phone' => '29345678' ), $e );
check( 'fifth-order IP limit', isset( $e->errors['famma_order_ip_limit'] ) );
$routes = $anti->close_store_checkout(
	array(
		'/wc/store/v1/checkout'       => array(),
		'/wc/store/v1/checkout/order' => array(),
		'/wc/store/v1/cart'           => array(),
	)
);
check( 'Store checkout closed cart preserved', array_keys( $routes ) === array( '/wc/store/v1/cart' ) );
$cart        = $GLOBALS['wc']->cart;
$cart->items = array(
	'a' => array(
		'product_id'   => 5,
		'quantity'     => 2,
		'variation_id' => 0,
	),
	'b' => array(
		'product_id'   => 6,
		'quantity'     => 1,
		'variation_id' => 0,
	),
);
$put         = new ReflectionMethod( $express, 'put_in_cart' );
$put->setAccessible( true );
check( 'retry replaces same product qty', $put->invoke( $express, 5, 2.0 ) && count( $cart->items ) === 2 && 2.0 === $cart->items['p5']['quantity'] );
$put->invoke( $express, 5, 2.0 );
check( 'second retry never accumulates qty', 2.0 === $cart->items['p5']['quantity'] && count( $cart->items ) === 2 );
$before      = $cart->items;
$cart->allow = false;
check( 'failed add restores cart', ! $put->invoke( $express, 5, 2.0 ) && $cart->items === $before );
$cart->allow = true;
$_POST       = array( 'woocommerce-process-checkout-nonce' => 'expired' );
$before      = $cart->items;
try {
	$express->handle();
} catch ( FakeResponse $r ) {
	check( 'expired nonce stops before cart/checkout', 'expired' === $r->data['famma_token'] && $before === $cart->items && 0 === $GLOBALS['wc']->checkout->calls );
}
$fields = array(
	'billing'  => array(
		'billing_address_1' => array( 'required' => true ),
		'billing_city'      => array( 'required' => true ),
	),
	'shipping' => array( 'shipping_postcode' => array( 'required' => true ) ),
);
$fields = $express->relax_address_fields( $fields );
check( 'express address optional intentionally', ! $fields['billing']['billing_address_1']['required'] && ! $fields['shipping']['shipping_postcode']['required'] );
// Simulate two sequential authenticated POSTs. Core is stubbed; measure delegation, not real order count.
$_POST = array(
	'woocommerce-process-checkout-nonce' => 'isolated-valid',
	'famma_product_id'                   => 5,
	'famma_quantity'                     => 2,
);
for ( $i = 0; $i < 2; $i++ ) {
	try {
		$express->handle();
	} catch ( FakeStop $stop ) {
		// The wp_die() double ends each forwarded request, as WooCommerce does after checkout.
		continue;
	}
}
check( 'repeat POST forwarded twice no endpoint idempotency key', 2 === $GLOBALS['wc']->checkout->calls );
echo "RESULT $checks checks, $fails failures; isolated collaborators only\n";
exit( $fails ? 1 : 0 );
