<?php
/**
 * Lot 1 des réformes du pixel (24/11) — commandes d'essai exclues.
 * Aucun événement Meta pour une commande `_famma_test_order`, contrôlé AU
 * MOMENT DE L'ENVOI (file, relance planifiée, « Renvoyer ») ; une note unique ;
 * marquage d'office d'une commande passée sur le site par un gérant connecté,
 * jamais d'une commande saisie dans l'administration ; case de l'écran de
 * commande (nonce, capacité) ; note « rien à annuler » après un envoi accepté.
 * Run with: php scripts/tests-unit/test-capi-test-orders.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';
Famma_Unit_Harness::load_include( 'offer-settings.php' );
Famma_Unit_Harness::load_include( 'test-orders.php' );

use Famma\Core\Capi;
use Famma\Core\Offer_Settings;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable WordPress.Security.NonceVerification.Missing -- The test fills $_POST the way the order screen does.
function is_user_logged_in(): bool {
	return ! empty( $GLOBALS['wp_test_logged_in'] );
}
function wp_verify_nonce( $nonce, $action = -1 ) {
	return 'valid-' . $action === $nonce ? 1 : false;
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals, Squiz.Commenting.FunctionComment.Missing

$statuses = Famma_Unit_Harness::bare( '\Famma\Core\Order_Statuses' );
$marker   = Famma_Unit_Harness::bare( '\Famma\Core\Test_Orders' );
$capi     = Capi::instance();
$skipped  = 'Commande d\'essai : aucun événement Meta envoyé (OrderPlaced, Purchase, OfferAccepted, annulation, retour).';

/**
 * Event names sent so far.
 *
 * @return string[]
 */
$sent_names = static function (): array {
	return array_map(
		static fn( array $call ): string => (string) json_decode( (string) $call['args']['body'], true )['data'][0]['event_name'],
		$GLOBALS['wp_test_http']
	);
};

// OrderPlaced: a test order is neither queued nor sent; the note is written once.
l5_reset();
$test_order         = new WC_Order( 8200 );
$test_order->status = 'pending-confirm';
$test_order->meta   = array( Capi::TEST_ORDER_META => 'yes' );
$capi->order_placed_after_checkout( array( 'result' => 'success' ), 8200 );
$capi->send_queued_order_placed( 8200 );
l5_check( array() === $GLOBALS['wp_test_http'] && array() === $GLOBALS['as_test_actions'], 'Test order: OrderPlaced neither queued nor sent.' );
l5_check( '' === $test_order->get_meta( '_famma_orderplaced_checkout_accepted' ) && str_contains( implode( "\n", l5_capi_lines() ), 'reason=test_order' ), 'Checkout acceptance not recorded; journal reason test_order.' );

// Purchase on « Livrée » and « Terminée », retry and « Renvoyer ».
foreach ( array( 'delivered', 'completed' ) as $target_status ) {
	$test_order->status = $target_status;
	$statuses->remember_delivery( 8200 );
	$statuses->mark_cod_paid_on_delivery( 8200 );
	$capi->fire_purchase_on_status( 8200 );
	$capi->send_purchase( $test_order, 'famma_purchase_8200' );
}
$capi->send_retry( 8200, 'Purchase' );
$capi->send_retry( 8200, 'OrderPlaced' );
l5_check( 'ineligible' === $capi->resend( $test_order, 'Purchase' ) && 'ineligible' === $capi->resend( $test_order, 'OrderPlaced' ), '« Renvoyer » refuses both events of a test order.' );
l5_check( array() === $GLOBALS['wp_test_http'] && array() === $GLOBALS['as_test_actions'] && ! $test_order->get_meta( '_famma_purchase_sent' ), 'Test order: no Purchase (nor delayed task) on « Livrée » or « Terminée », nor on retry.' );
l5_check( 1 === count( array_keys( $test_order->notes, $skipped, true ) ), 'The « aucun événement Meta » note is written once.' );

// OfferAccepted and the lot 2 outcome events: nothing for a test order.
l5_reset( array( 'famma_meta_offeraccepted_enabled' => true ) );
$offer         = new WC_Order( 8201 );
$offer->status = 'cancelled';
$offer->meta   = array(
	Capi::TEST_ORDER_META          => 'yes',
	Offer_Settings::ORDER_ACCEPTED => (string) ( time() - 60 ),
	'_famma_orderplaced_sent'      => '2026-11-24 10:00:00',
);
l5_check( 'ineligible' === $capi->send_offer_accepted( $offer ) && 'ineligible' === $capi->send_order_outcome( $offer, 'OrderCancelled' ), 'Test order: no OfferAccepted, no OrderCancelled.' );
$capi->queue_order_outcome( 8201, 'pending-confirm', 'cancelled' );
$capi->send_retry( 8201, 'OrderCancelled' );
l5_check( array() === $GLOBALS['wp_test_http'] && array() === $GLOBALS['as_test_actions'], 'Test order: the cancellation is neither queued nor sent, even on retry.' );

// Checked at SEND time: an order marked test after its OrderPlaced was queued sends nothing.
l5_reset();
$late         = new WC_Order( 8202 );
$late->status = 'pending-confirm';
$capi->order_placed_after_checkout( array( 'result' => 'success' ), 8202 );
l5_check( 1 === count( $GLOBALS['as_test_actions'] ), 'Real order: OrderPlaced queued.' );
$late->meta[ Capi::TEST_ORDER_META ] = 'yes';
l5_as_run_next();
l5_check( array() === $GLOBALS['wp_test_http'] && in_array( $skipped, $late->notes, true ), 'Marked test before the queued send ran: nothing sent, note written.' );

// Checked at retry time too: a transient failure, then the order is marked test.
l5_reset();
$GLOBALS['famma_test_registry_stateful'] = true;
$GLOBALS['wp_test_http_response']        = l5_response( 503, '{"error":{"message":"busy","code":2}}' );
$retry_order                             = new WC_Order( 8203 );
$retry_order->status                     = 'delivered';
$capi->fire_purchase_on_status( 8203 );
l5_check( 1 === count( $GLOBALS['wp_test_http'] ) && 1 === count( l5_as_find( 'famma_capi_retry', null, 'famma-capi', array( 'pending' ) ) ), 'Real order: first Purchase attempt uncertain, one retry planned.' );
$retry_order->meta[ Capi::TEST_ORDER_META ] = 'yes';
$GLOBALS['wp_test_http_response']           = l5_response( 200, '{"events_received":1,"messages":[],"fbtrace_id":"QAL5"}', array( 'facebook-api-version' => 'v26.0' ) );
l5_as_run_next();
l5_check( 1 === count( $GLOBALS['wp_test_http'] ) && ! $retry_order->get_meta( '_famma_purchase_sent' ), 'The planned retry of an order marked test meanwhile sends nothing.' );

// A real order still sends OrderPlaced then Purchase, without the test note.
l5_reset( array( 'famma_capi_purchase_delay_minutes' => 0 ) );
$real             = new WC_Order( 8204 );
$real->status     = 'pending-confirm';
$real->created_at = new DateTimeImmutable( '-1 hour' );
$capi->order_placed_after_checkout( array( 'result' => 'success' ), 8204 );
l5_as_run_next();
$real->status = 'delivered';
$statuses->remember_delivery( 8204 );
$statuses->mark_cod_paid_on_delivery( 8204 );
$capi->fire_purchase_on_status( 8204 );
l5_check( array( 'OrderPlaced', 'Purchase' ) === $sent_names() && ! in_array( $skipped, $real->notes, true ) && ! Capi::is_test_order( $real ), 'A real order still sends OrderPlaced then Purchase, without the test note.' );

// Marked d'office: staff logged in on the SITE (checkout or express, created_via checkout).
$GLOBALS['wp_test_logged_in']                  = true;
$GLOBALS['wp_test_caps']['manage_woocommerce'] = true;
$staff_site                                    = new WC_Order( 8210 );
$marker->mark_staff_checkout( $staff_site );
l5_check( Capi::is_test_order( $staff_site ) && 1 === count( $staff_site->notes ), 'Staff (manage_woocommerce) ordering on the site: marked test at creation, with one note.' );

// Not marked: the same staff entering an order in the admin screen (phone order).
$admin_entry              = new WC_Order( 8211 );
$admin_entry->created_via = 'admin';
$marker->mark_staff_checkout( $admin_entry );
l5_check( ! Capi::is_test_order( $admin_entry ) && array() === $admin_entry->notes, 'Order entered in the admin screen: not marked (real phone order).' );

// Not marked: a logged-in customer, or an anonymous visitor.
$GLOBALS['wp_test_caps']['manage_woocommerce'] = false;
$customer                                      = new WC_Order( 8212 );
$marker->mark_staff_checkout( $customer );
$GLOBALS['wp_test_logged_in'] = false;
$anonymous                    = new WC_Order( 8213 );
$marker->mark_staff_checkout( $anonymous );
l5_check( ! Capi::is_test_order( $customer ) && ! Capi::is_test_order( $anonymous ), 'Logged-in customer and anonymous visitor: not marked.' );
unset( $GLOBALS['wp_test_caps']['manage_woocommerce'] );

// Order screen box: nonce, capability edit_shop_orders, sanitized value.
$box   = new WC_Order( 8220 );
$_POST = array(
	'famma_test_order_nonce' => 'forged',
	'famma_test_order'       => '1',
);
$marker->save_box( 8220 );
l5_check( ! Capi::is_test_order( $box ), 'Box: an invalid nonce changes nothing.' );
$_POST['famma_test_order_nonce']             = 'valid-famma_test_order_nonce';
$GLOBALS['wp_test_caps']['edit_shop_orders'] = false;
$marker->save_box( 8220 );
l5_check( ! Capi::is_test_order( $box ), 'Box: without edit_shop_orders nothing changes.' );
$GLOBALS['wp_test_caps']['edit_shop_orders'] = true;
$marker->save_box( 8220 );
l5_check( 'yes' === $box->get_meta( Capi::TEST_ORDER_META ) && 1 === count( $box->notes ), 'Box checked: _famma_test_order = yes, one note.' );
$marker->save_box( 8220 );
l5_check( 1 === count( $box->notes ), 'Saving again without a change adds nothing.' );

// Checked after an accepted send: a note says there is nothing to cancel at Meta.
$after                               = new WC_Order( 8221 );
$after->meta['_famma_purchase_sent'] = '2026-11-24 10:00:00';
$marker->save_box( 8221 );
l5_check( Capi::is_test_order( $after ) && 2 === count( $after->notes ) && str_contains( (string) end( $after->notes ), 'rien à annuler chez Meta' ), 'Checked after an accepted Purchase: a second note says nothing can be cancelled at Meta.' );
// Unchecked (real order taken on the site by a staff member): OrderPlaced is re-queued, then a cancellation can leave.
l5_reset( array( 'famma_capi_purchase_delay_minutes' => 0 ) );
$GLOBALS['wp_test_logged_in']                  = true;
$GLOBALS['wp_test_caps']['manage_woocommerce'] = true;
$GLOBALS['wp_test_caps']['edit_shop_orders']   = true;
$staff_real                                    = new WC_Order( 8230 );
$staff_real->status                            = 'pending-confirm';
$staff_real->created_at                        = new DateTimeImmutable( '-2 hours' );
$marker->mark_staff_checkout( $staff_real );
$capi->order_placed_after_checkout( array( 'result' => 'success' ), 8230 );
l5_check( Capi::is_test_order( $staff_real ) && array() === $GLOBALS['as_test_actions'] && (bool) $staff_real->get_meta( Capi::TEST_ORDER_NOTED ), 'Staff site order: marked test, OrderPlaced skipped at checkout (note written).' );
$GLOBALS['wp_test_logged_in'] = false;
$_POST                        = array( 'famma_test_order_nonce' => 'valid-famma_test_order_nonce' );
$marker->save_box( 8230 );
l5_check( ! Capi::is_test_order( $staff_real ) && ! $staff_real->get_meta( Capi::TEST_ORDER_NOTED ), 'Unchecked: the test marker and its « noted » flag are removed.' );
l5_check( str_contains( (string) end( $staff_real->notes ), 'remis en file' ) && 1 === count( l5_as_find( 'famma_capi_orderplaced_send', array( 'order_id' => 8230 ), 'famma-capi', array( 'pending' ) ) ), 'Unchecked: OrderPlaced is re-queued, and the note says so.' );
l5_as_run_next();
l5_check( array( 'OrderPlaced' ) === $sent_names() && (bool) $staff_real->get_meta( '_famma_orderplaced_sent' ), 'The re-queued OrderPlaced leaves once (same guards: switch, registry).' );
$staff_real->status = 'cancelled';
$capi->queue_order_outcome( 8230, 'pending-confirm', 'cancelled' );
l5_as_run_next();
l5_check( array( 'OrderPlaced', 'OrderCancelled' ) === $sent_names(), 'After the re-queued OrderPlaced is accepted, the cancellation leaves.' );

// Unchecked but too old (6-day guard), or OrderPlaced switched off: nothing queued, the note says why.
l5_reset();
$old_staff             = new WC_Order( 8231 );
$old_staff->status     = 'pending-confirm';
$old_staff->created_at = new DateTimeImmutable( '-8 days' );
$old_staff->meta       = array( Capi::TEST_ORDER_META => 'yes' );
$marker->save_box( 8231 );
l5_check( array() === $GLOBALS['as_test_actions'] && str_contains( (string) end( $old_staff->notes ), 'plus de 6 jours' ) && str_contains( (string) end( $old_staff->notes ), 'aucune annulation' ), 'Unchecked after 6 days: no OrderPlaced, the note says why and that no cancellation will follow.' );
l5_reset( array( 'famma_meta_orderplaced_enabled' => false ) );
$off_staff         = new WC_Order( 8232 );
$off_staff->status = 'pending-confirm';
$off_staff->meta   = array( Capi::TEST_ORDER_META => 'yes' );
$marker->save_box( 8232 );
l5_check( array() === $GLOBALS['as_test_actions'] && str_contains( (string) end( $off_staff->notes ), 'désactivé' ), 'Unchecked with OrderPlaced switched off: nothing queued, the note says it is disabled.' );
l5_reset();
$phone              = new WC_Order( 8233 );
$phone->created_via = 'admin';
$phone->status      = 'confirmed';
$phone->meta        = array( Capi::TEST_ORDER_META => 'yes' );
$marker->save_box( 8233 );
l5_check( array() === $GLOBALS['as_test_actions'] && str_contains( (string) end( $phone->notes ), 'pas été passée sur le site' ), 'Unchecked admin (phone) order: no OrderPlaced, the note says why.' );

$_POST = array();
unset( $GLOBALS['wp_test_caps']['edit_shop_orders'] );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
