<?php
/**
 * Lot 7 des réformes du pixel (phase B, D5 de l'évaluation) — OrderPlaced des paniers récupérés.
 * Une commande créée par « Créer la commande » (created_via admin, canal Panier récupéré, saisie
 * d'origine) reçoit les cookies Meta et l'IP de la visite d'origine, puis envoie OrderPlaced par
 * la file normale 15 minutes plus tard, tout étant revérifié au départ (Q19) ; source
 * `phone_call` (Q20) ; case « Commande d'essai » du bouton posée avant tout statut ;
 * interrupteur propre activé par défaut ; jamais pour une commande d'essai ;
 * les autres commandes saisies à la main restent sans OrderPlaced ; les commandes du site
 * restent immédiates.
 * Run with: php scripts/tests-unit/test-capi-recovered-cart.php
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Standalone runner outside WordPress: $wpdb is a double, $order a local fixture.

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi;
use Famma\Core\Capi_Recovered_Cart;
use Famma\Core\Order_Channel;
use Famma\Core\Visitor_Inputs_Order;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Doubles are documented by the API they replace.
/**
 * Journal des visiteurs en mémoire : seule la requête « dernière IP de la session » est servie.
 */
final class Famma_Test_Wpdb {
	public string $prefix = 'wp_';
	public string $last   = '';
	public function prepare( string $query, ...$args ): string {
		$this->last = (string) end( $args );
		return $query;
	}
	public function get_var( string $query ) {
		return $GLOBALS['famma_test_journal_ip'][ $this->last ] ?? null;
	}
}
function esc_url( string $url ): string {
	return $url;
}
function wp_nonce_field( $action, string $name = '_wpnonce', bool $referer = true, bool $display = true ): string {
	return '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">';
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals, Squiz.Commenting.FunctionComment.Missing

$GLOBALS['wpdb'] = new Famma_Test_Wpdb();
$capi            = Capi::instance();
$recovered_hooks = Capi_Recovered_Cart::instance();
$product         = new WC_Product( 9701, 'SKU-9701', 'Article', 'article', 39.0 );
$session         = '7c1e2d3f-4a5b-4c6d-8e7f-9a0b1c2d3e4f';
$visitor         = '0a1b2c3d-4e5f-4a6b-9c7d-8e9f0a1b2c3d';
$fbc             = 'fb.1.1764000000000.IwAR0AbC_d-Ef';
$fbp             = 'fb.1.1764000000123.1234567890';

/**
 * Commande telle que `Visitor_Inputs_Order` la crée (méta du bouton, cookies de la saisie).
 *
 * @param int   $id    Order identifier.
 * @param array $extra Meta overrides.
 * @return WC_Order
 */
$recovered_order = static function ( int $id, array $extra = array() ) use ( $product, $session, $visitor, $fbc, $fbp ): WC_Order {
	$order              = new WC_Order( $id );
	$order->created_via = 'admin';
	$order->status      = 'pending-confirm';
	$order->phone       = '+21620000000';
	$order->country     = 'TN';
	$order->items       = array( new WC_Order_Item_Product( $product, 1 ) );
	$order->meta        = $extra + array(
		Order_Channel::META              => Order_Channel::RECOVERED,
		Visitor_Inputs_Order::META_INPUT => 55,
		'_famma_visitor_id'              => $visitor,
		'_famma_visitor_session'         => $session,
	);
	Capi_Recovered_Cart::stamp(
		$order,
		array(
			'session_id' => $session,
			'fbc'        => $fbc,
			'fbp'        => $fbp,
		)
	);
	return $order;
};
$sent            = static function (): array {
	return array_map( static fn( array $call ): array => json_decode( (string) $call['args']['body'], true )['data'][0], $GLOBALS['wp_test_http'] );
};

// Identifiants de la visite d'origine recopiés ; jamais un cookie invalide ; IP du journal.
l5_reset();
$GLOBALS['famma_test_journal_ip'] = array( $session => '197.0.12.34' );
$order                            = $recovered_order( 9710 );
l5_check( $fbc === $order->get_meta( '_famma_fbc' ) && $fbp === $order->get_meta( '_famma_fbp' ) && '197.0.12.34' === $order->get_meta( '_famma_client_ip' ), 'Stamp: fbc and fbp of the input row copied unchanged, IP read from the visitor journal.' );
l5_check( '' === $order->get_meta( '_famma_client_ua' ), 'Stamp: no user agent, the event stays off-site.' );
$bad = new WC_Order( 9711 );
Capi_Recovered_Cart::stamp(
	$bad,
	array(
		'session_id' => 'pas-une-session',
		'fbc'        => 'fb.1.123.<script>',
		'fbp'        => '',
	)
);
l5_check( array() === $bad->meta, 'Stamp: an invalid cookie, an unknown session: nothing written, nothing invented.' );

// Interrupteur activé par défaut : OrderPlaced part par la file, avec le fbc de la visite.
l5_reset();
$GLOBALS['famma_test_registry_stateful'] = true;
$order                                   = $recovered_order( 9712 );
$queued_at                               = time();
l5_check( 'queued' === Capi_Recovered_Cart::queue( $order ) && 1 === count( $GLOBALS['as_test_actions'] ), 'Recovered cart, switch untouched (on by default): OrderPlaced queued once.' );
$task = $GLOBALS['as_test_actions'][0];
l5_check( 'famma_capi_orderplaced_send' === $task[1] && 'famma-capi' === $task[3] && $task[0] >= $queued_at + 900 && $task[0] <= time() + 900, 'Q19: the task waits 15 minutes (group famma-capi), not an immediate async action.' );
l5_check( Capi_Recovered_Cart::due_at( $order ) === $task[0] && 900 === Capi_Recovered_Cart::delay(), 'Q19: due_at() exposes the planned send time (15 min by default) for the surveillance.' );
l5_check( $order->get_date_created()->getTimestamp() === (int) $order->get_meta( '_famma_orderplaced_event_time' ), 'event_time = order creation time, not the delayed send time.' );
l5_as_run_next();
$events = $sent();
l5_check( 1 === count( $events ) && 'OrderPlaced' === $events[0]['event_name'] && 'famma_orderplaced_9712' === $events[0]['event_id'], 'Recovered cart: one OrderPlaced sent, stable event_id.' );
l5_check( ( $events[0]['user_data']['fbc'] ?? '' ) === $fbc && ( $events[0]['user_data']['fbp'] ?? '' ) === $fbp, 'Recovered cart: OrderPlaced carries the fbc and fbp of the original visit.' );
l5_check( 'phone_call' === $events[0]['action_source'] && ! isset( $events[0]['user_data']['client_user_agent'] ) && ! isset( $events[0]['event_source_url'] ), 'Q20: recovered cart = action_source phone_call, no user agent, no event_source_url.' );
l5_check( hash( 'sha256', '0a1b2c3d-4e5f-4a6b-9c7d-8e9f0a1b2c3d' ) === ( $events[0]['user_data']['external_id'] ?? '' ), 'Recovered cart: external_id = SHA-256 of the visitor of the input row.' );
l5_check( 'already_sent' === Capi_Recovered_Cart::queue( $order ), 'Recovered cart: a second queue answers already_sent (marker).' );
l5_as_run_next();
l5_check( 1 === count( $sent() ), 'Recovered cart: still one OrderPlaced after a second queue (marker).' );

// Interrupteur coupé : rien ne part.
l5_reset( array( Capi_Recovered_Cart::OPTION => false ) );
$order = $recovered_order( 9713 );
Capi_Recovered_Cart::queue( $order );
l5_as_run_next();
l5_check( array() === $GLOBALS['wp_test_http'] && str_contains( implode( "\n", l5_capi_lines() ), 'reason=recovered_cart:disabled' ), 'Recovered cart switch off: no OrderPlaced, journal reason recovered_cart:disabled.' );

// Interrupteur général OrderPlaced coupé : rien non plus.
l5_reset( array( 'famma_meta_orderplaced_enabled' => false ) );
Capi_Recovered_Cart::queue( $recovered_order( 9714 ) );
l5_check( array() === $GLOBALS['wp_test_http'] && array() === $GLOBALS['as_test_actions'], 'General OrderPlaced switch off: nothing queued for a recovered cart.' );

// Commande d'essai : rien, ni à la mise en file ni à l'envoi.
l5_reset();
$test = $recovered_order( 9715, array( Capi::TEST_ORDER_META => 'yes' ) );
Capi_Recovered_Cart::queue( $test );
$capi->send_order_placed( $test );
l5_check( array() === $GLOBALS['wp_test_http'] && array() === $GLOBALS['as_test_actions'] && str_contains( implode( "\n", l5_capi_lines() ), 'reason=test_order' ), 'Recovered cart marked as a test order: nothing queued, nothing sent.' );

// Marquée d'essai après la mise en file : rien à l'envoi.
l5_reset();
$late = $recovered_order( 9716 );
Capi_Recovered_Cart::queue( $late );
$late->update_meta_data( Capi::TEST_ORDER_META, 'yes' );
l5_as_run_next();
l5_check( array() === $GLOBALS['wp_test_http'], 'Recovered cart marked as a test order after queueing: nothing sent.' );

// Autres commandes saisies à la main : toujours sans OrderPlaced.
l5_reset();
$manual              = new WC_Order( 9717 );
$manual->created_via = 'admin';
$manual->status      = 'pending-confirm';
$manual->items       = array( new WC_Order_Item_Product( $product, 1 ) );
$manual->meta        = array( Order_Channel::META => Order_Channel::RECOVERED );
$app                 = new WC_Order( 9718 );
$app->created_via    = 'rest-api';
$app->items          = $manual->items;
l5_check( 'not_recovered' === Capi_Recovered_Cart::queue( $manual ) && 'not_recovered' === Capi_Recovered_Cart::queue( $app ), 'Channel set by hand without an input row, or an app order: not a recovered cart.' );
$capi->order_placed_without_payment( '', $manual );
$capi->order_placed_without_payment( '', $app );
l5_check( array() === $GLOBALS['as_test_actions'] && str_contains( implode( "\n", l5_capi_lines() ), 'reason=created_via:admin' ) && str_contains( implode( "\n", l5_capi_lines() ), 'reason=created_via:rest-api' ), 'Manual and app orders keep their created_via reason: no OrderPlaced.' );

// Panier récupéré sans article : rien ; l'équipe ajoute l'article et quitte « Attente paiement » : une fois.
l5_reset();
$GLOBALS['famma_test_registry_stateful'] = true;
$empty                                   = $recovered_order( 9719 );
$empty->items                            = array();
$empty->status                           = 'pending';
Capi_Recovered_Cart::queue( $empty );
l5_check( array() === $GLOBALS['as_test_actions'] && str_contains( implode( "\n", l5_capi_lines() ), 'reason=recovered_cart:no_product' ), 'Recovered cart without product: no OrderPlaced (recovered_cart:no_product).' );
$empty->items  = array( new WC_Order_Item_Product( $product, 1 ) );
$empty->status = 'pending-confirm';
$recovered_hooks->status_changed( 9719, 'pending', 'pending-confirm' );
l5_as_run_next();
$recovered_hooks->status_changed( 9719, 'pending', 'pending-confirm' );
$recovered_hooks->status_changed( 9719, 'pending-confirm', 'confirmed' );
l5_check( 1 === count( $sent() ) && 1 === count( $GLOBALS['as_test_actions'] ), 'Leaving « Attente paiement » with a product: OrderPlaced queued and sent once, never re-queued.' );

// Q19 : tout est relu au départ (statut, interrupteurs, essai) ; une remise en file ne repousse pas l'heure.
foreach (
	array(
		'cancelled before the 15 minutes' => array( 'status', 'cancelled', 'reason=status:cancelled' ),
		'failed before the 15 minutes'    => array( 'status', 'failed', 'reason=status:failed' ),
		'recovered switch turned off'     => array( 'option', Capi_Recovered_Cart::OPTION, 'reason=recovered_cart:disabled' ),
		'OrderPlaced switch turned off'   => array( 'option', 'famma_meta_orderplaced_enabled', 'state=disabled' ),
		'test box ticked on the order'    => array( 'meta', Capi::TEST_ORDER_META, 'reason=test_order' ),
	) as $label => $change
) {
	l5_reset();
	$later = $recovered_order( 9730 );
	Capi_Recovered_Cart::queue( $later );
	if ( 'status' === $change[0] ) {
		$later->status = $change[1];
	} elseif ( 'option' === $change[0] ) {
		$GLOBALS['wp_test_options'][ $change[1] ] = false;
	} else {
		$later->update_meta_data( $change[1], 'yes' );
	}
	l5_as_run_next();
	l5_check( array() === $GLOBALS['wp_test_http'] && str_contains( implode( "\n", l5_capi_lines() ), $change[2] ), 'Q19 rechecked at departure, ' . $label . ': nothing sent (' . $change[2] . ').' );
}
l5_reset();
$again                                        = $recovered_order( 9731 );
$planned                                      = time() + 120;
$again->meta[ Capi_Recovered_Cart::DUE_META ] = $planned;
Capi_Recovered_Cart::queue( $again );
l5_check( Capi_Recovered_Cart::due_at( $again ) === $planned && $GLOBALS['as_test_actions'][0][0] === $planned, 'A re-queue keeps the planned time, it never pushes the departure later.' );

// Commandes du site : OrderPlaced immédiat, pas d'heure différée, source inchangée.
l5_reset();
$site         = new WC_Order( 9732 );
$site->status = 'pending-confirm';
$site->items  = array( new WC_Order_Item_Product( $product, 1 ) );
$site->meta   = array( '_famma_client_ua' => 'Mozilla/5.0 (Linux; Android 14)' );
$capi->order_placed_after_checkout( array( 'result' => 'success' ), 9732 );
l5_check( 1 === count( $GLOBALS['as_test_actions'] ) && $GLOBALS['as_test_actions'][0][0] <= time() && 0 === Capi_Recovered_Cart::due_at( $site ), 'Site order: OrderPlaced still immediate, no due_at.' );
l5_as_run_next();
l5_check( 'website' === ( $sent()[0]['action_source'] ?? '' ), 'Site order: action_source website, untouched by the recovered-cart filter.' );
l5_check( 'other' === $recovered_hooks->action_source( 'other', $manual ), 'Other manual orders keep their off-site source (other).' );
l5_check( 'phone_call' === \Famma\Core\Capi_User_Data::offsite_action_source( $again ), 'Q20: phone_call for every off-site event of a recovered cart (Purchase, OrderCancelled, OrderReturned share build_event).' );

// Q19 : case « Commande d'essai » du bouton « Créer la commande », posée avant tout statut.
$source = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/visitor-inputs-order.php' );
$create = substr( $source, (int) strpos( $source, 'public function create(' ) );
l5_check( strpos( $create, 'Capi::TEST_ORDER_META' ) < strpos( $create, '$this->fill(' ) && strpos( $source, 'check_admin_referer( self::nonce_action( $id ) )' ) < strpos( $source, '$_POST[ self::FIELD_TEST ]' ) && strpos( $source, "current_user_can( 'manage_woocommerce' )" ) < strpos( $source, '$_POST[ self::FIELD_TEST ]' ), 'Create order: the test marker is set before fill() (before any status), the box is read after the nonce and capability checks.' );
l5_check( str_contains( Visitor_Inputs_Order::button( 12 ), 'name="famma_test_order" value="1"' ) && str_contains( Visitor_Inputs_Order::button( 12, true ), 'name="famma_test_order"' ), 'The « Créer la commande » and « Créer quand même » forms carry the « Commande d’essai » box.' );
l5_reset();
$boxed = $recovered_order( 9733, array( Capi::TEST_ORDER_META => 'yes' ) );
$recovered_hooks->status_changed( 9733, 'pending', 'pending-confirm' );
l5_as_run_next();
l5_check( array() === $GLOBALS['wp_test_http'] && array() === $GLOBALS['as_test_actions'], 'Box ticked at creation: the status change queues nothing, nothing leaves.' );

// Cookies de la requête du visiteur : valides seulement, sans toucher au compteur de fbc refusés.
l5_reset();
$_COOKIE = array(
	'_fbc' => 'fb.1.123.<b>',
	'_fbp' => $fbp,
);
$cookies = Capi_Recovered_Cart::request_cookies();
l5_check( '' === $cookies['fbc'] && $fbp === $cookies['fbp'] && ! isset( $GLOBALS['wp_test_options']['famma_capi_fbc_rejected'] ), 'Input request: invalid _fbc dropped, valid _fbp kept, rejected-fbc counter untouched.' );
$_COOKIE = array();

// Délai réglable par constante, borné à une heure.
define( 'FAMMA_CAPI_RECOVERED_DELAY', 99999 );
l5_check( 3600 === Capi_Recovered_Cart::delay(), 'FAMMA_CAPI_RECOVERED_DELAY overrides the delay, bounded to one hour.' );

echo 'RESULT ' . $GLOBALS['l5_checks'] . '/' . $GLOBALS['l5_checks'] . PHP_EOL;
