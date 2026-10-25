<?php
/**
 * Lot 5, B1 (S1) — internal visitors (administrator or shop manager logged in):
 * no fbq('init'), no browser event, no TikTok, no GA4, no famma-fbc script, an
 * empty express tracking block, a page never cached; server sends and order
 * data are never cut. Acceptance A1, A3, A4 of the plan, on the real classes.
 * Run with: php scripts/tests-unit/test-browser-internal.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap-browser.php.
require __DIR__ . '/bootstrap-browser.php';

use Famma\Core\Tracking;

$tracked_options = array(
	'famma_tiktok_pixel_id'    => 'TTQAL5FAKE',
	'famma_ga4_measurement_id' => 'G-QAL5FAKE',
);

// Anonymous visitor: everything configured reaches the browser.
lb_reset( $tracked_options );
$seen = lb_page_summary();
l5_check( 1 === $seen['init'] && $seen['view_content'] && $seen['tiktok'] && $seen['ga4'] && $seen['fbc'] && $seen['utm'], 'Visiteur anonyme : 1 fbq(init), ViewContent, TikTok, GA4, famma-fbc et capture UTM.' );
l5_check( ! Tracking::is_internal_visitor() && Tracking::browser_tracking_allowed(), 'Visiteur anonyme : non interne, suivi navigateur permis.' );

// Logged-in customer (no management capability): still measured.
lb_reset( $tracked_options );
lb_login( array() );
$seen = lb_page_summary();
l5_check( 1 === $seen['init'] && $seen['view_content'] && ! Tracking::is_internal_visitor(), 'Client connecté sans droit de gestion : toujours mesuré (même règle que famma-google-tags).' );

// Administrator and shop manager: nothing but the UTM capture (A1).
$staff = array(
	'manage_options'     => 'Administrateur',
	'manage_woocommerce' => 'Gérant de boutique',
);
foreach ( $staff as $cap => $who ) {
	lb_reset( $tracked_options );
	lb_login( array( $cap ) );
	$seen = lb_page_summary();
	l5_check( Tracking::is_internal_visitor() && ! Tracking::browser_tracking_allowed(), "A1 {$who} ({$cap}) : visiteur interne." );
	l5_check( 0 === $seen['init'] && 0 === $seen['fbq_calls'] && ! $seen['view_content'], "A1 {$who} : 0 fbq('init') et 0 événement navigateur." );
	l5_check( ! $seen['tiktok'] && ! $seen['ga4'] && ! $seen['fbc'], "A1 {$who} : ni TikTok, ni GA4, ni script famma-fbc." );
	l5_check( $seen['pixels_registered'] && $seen['utm'], "A1 {$who} : la capture UTM reste (R12)." );
}

// Express quote: tracking block empty for an internal visitor, server IDs otherwise.
$prep    = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
$product = new WC_Product( 17, 'QA-L5-EXP', 'QA-L5 Express', 'qa-l5-express', 49.0 );
lb_reset();
$GLOBALS['wc_test_store'] = lb_cart( $product );
$visitor                  = (array) Famma_Unit_Harness::call( '\Famma\Core\Express_Preparation', 'tracking', array( $prep, $product, 1.0, 0.0 ) );
$add_id                   = (string) ( $visitor['add_to_cart']['event_id'] ?? '' );
$checkout_id              = (string) ( $visitor['initiate_checkout']['event_id'] ?? '' );
l5_check( sprintf( 'famma_add_%s', $prep ) === $add_id && sprintf( 'famma_checkout_%s', $prep ) === $checkout_id, 'Express, visiteur : AddToCart et InitiateCheckout gardent leurs identifiants serveur famma_add_/famma_checkout_<prep>.' );
lb_login( array( 'manage_options' ) );
$admin = (array) Famma_Unit_Harness::call( '\Famma\Core\Express_Preparation', 'tracking', array( $prep, $product, 1.0, 0.0 ) );
l5_check( array() === $admin, 'A1 Express, administrateur : bloc tracking vide.' );

// Never cached: LiteSpeed no-cache and DONOTCACHEPAGE for an internal visitor only.
$nocache = 0;
add_action(
	'litespeed_control_set_nocache',
	static function () use ( &$nocache ): void {
		++$nocache;
	}
);
Famma_Unit_Harness::bare( '\Famma\Core\Tracking' )->internal_cache_guard();
l5_check( 1 === $nocache && true === ( $GLOBALS['wp_test_constants']['DONOTCACHEPAGE'] ?? null ), 'Visiteur interne : page jamais mise en cache (litespeed_control_set_nocache + DONOTCACHEPAGE).' );
$GLOBALS['wp_test_logged_in'] = false;
$GLOBALS['wp_test_constants'] = array();
Famma_Unit_Harness::bare( '\Famma\Core\Tracking' )->internal_cache_guard();
l5_check( 1 === $nocache && array() === $GLOBALS['wp_test_constants'], 'Visiteur anonyme : cache public intact.' );

// Filter famma_tracking_exclude_visitor widens or narrows the rule.
lb_reset();
add_filter( 'famma_tracking_exclude_visitor', static fn(): bool => true );
l5_check( Tracking::is_internal_visitor() && 0 === lb_page_summary()['init'], 'Filtre famma_tracking_exclude_visitor (true) : un visiteur anonyme exclu.' );
lb_reset();
lb_login( array( 'manage_options' ) );
add_filter( 'famma_tracking_exclude_visitor', static fn(): bool => false );
l5_check( ! Tracking::is_internal_visitor() && 1 === lb_page_summary()['init'], 'Filtre famma_tracking_exclude_visitor (false) : un administrateur mesuré.' );

// Dérogation « Suivre mes visites (mode test) » (A4): option, then constant.
lb_reset( array( 'famma_track_internal' => true ) );
lb_login( array( 'manage_options' ) );
$seen = lb_page_summary();
l5_check( Tracking::is_internal_visitor() && Tracking::browser_tracking_allowed() && 1 === $seen['init'] && $seen['view_content'] && $seen['fbc'], 'A4 Option famma_track_internal : les événements de l’administrateur réapparaissent.' );

// Server sends are never cut (A3): an administrator marks a COD order delivered.
lb_reset();
lb_login( array( 'manage_options' ) );
$GLOBALS['famma_test_registry_stateful'] = true;
$delivered                               = new WC_Order( 5950 );
$delivered->status                       = 'delivered';
$delivered->items[]                      = new WC_Order_Item_Product( $product, 1 );
Famma_Unit_Harness::bare( '\Famma\Core\Order_Statuses' )->mark_cod_paid_on_delivery( 5950 );
\Famma\Core\Capi::instance()->fire_purchase_on_status( 5950 );
\Famma\Core\Capi::instance()->fire_purchase_on_status( 5950 );
$sent     = $GLOBALS['wp_test_http'];
$event_id = (string) ( json_decode( (string) ( $sent[0]['args']['body'] ?? '' ), true )['data'][0]['event_id'] ?? '' );
l5_check( 1 === count( $sent ) && 'famma_purchase_5950' === $event_id, 'A3 Administrateur connecté : le Purchase serveur part quand même, une seule fois.' );

// Order data of the customer are still recorded when an internal visitor creates the order.
$_COOKIE['_fbc']        = 'fb.1.1760000000000.QAL5fbclid';
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$stamped                = new WC_Order( 5951 );
Famma_Unit_Harness::bare( '\Famma\Core\Tracking' )->stamp_capi_signals( $stamped );
Famma_Unit_Harness::bare( '\Famma\Core\Tracking' )->stamp_utm_on_order( $stamped );
l5_check( 'fb.1.1760000000000.QAL5fbclid' === $stamped->get_meta( '_famma_fbc' ) && '203.0.113.7' === $stamped->get_meta( '_famma_client_ip' ) && 'facebook / fbclid' === $stamped->get_meta( '_famma_order_source' ), 'Données de commande jamais coupées (signaux CAPI et source).' );
unset( $_COOKIE['_fbc'], $_SERVER['REMOTE_ADDR'] );

// Constant FAMMA_TRACK_INTERNAL wins (defined last: a constant cannot be undefined).
lb_reset();
lb_login( array( 'manage_woocommerce' ) );
define( 'FAMMA_TRACK_INTERNAL', true );
l5_check( Tracking::browser_tracking_allowed() && 1 === lb_page_summary()['init'], 'A4 Constante FAMMA_TRACK_INTERNAL : dérogation sans option.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
