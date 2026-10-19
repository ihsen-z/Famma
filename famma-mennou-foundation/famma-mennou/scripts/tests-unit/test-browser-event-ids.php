<?php
/**
 * Lot 5, B2 (S3-a) and B4 (D6) — browser event identifiers and Pixel loading.
 *
 * - PageView and ViewContent carry an eventID drawn IN THE BROWSER
 *   (fammaEventId()); no UUID made in PHP ever reaches the HTML, so a cached
 *   page no longer freezes one identifier for every visitor (I1);
 * - InitiateCheckout of the classic checkout is wrapped "once per cart and per
 *   session" (sessionStorage key with the cart hash, T3.2);
 * - OrderPlaced in the browser: famma_orderplaced_<id>, only when the switch is
 *   on, once (confirmation page and express success response, I2, I3);
 * - loading: immediate (Meta standard code in the head, default) or deferred
 *   (0.3.1 behaviour), exactly one fbq('init') in both.
 * The runtime behaviour of the generated JavaScript is checked by
 * scripts/tests-unit/test-browser-scripts.js (Node).
 * Run with: php scripts/tests-unit/test-browser-event-ids.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap-browser.php.
require __DIR__ . '/bootstrap-browser.php';

use Famma\Core\Browser_Order_Placed;
use Famma\Core\Tracking;
use Famma\Core\Tracking_Events;

$uuid    = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i';
$product = new WC_Product( 16, 'QA-L5-SKU', 'QA-L5 Produit', 'qa-l5-produit', 49.0 );

// ── S3-a : identifiants tirés dans le navigateur ─────────────────────────────
lb_reset();
$html = lb_product_page( $product );
l5_check( 0 === preg_match( $uuid, $html ), 'I1 Fiche produit : aucun UUID fabriqué en PHP dans le HTML.' );
l5_check( str_contains( $html, "fbq('track','PageView',{},{eventID:fammaEventId()});" ), 'PageView porte un eventID tiré dans le navigateur.' );
l5_check( 1 === preg_match( '/fbq\("track","ViewContent",\{.*?\},\{eventID:fammaEventId\(\)\}\);/', $html ), 'ViewContent porte un eventID tiré dans le navigateur.' );
l5_check( strpos( $html, 'window.fammaEventId=' ) < strpos( $html, "fbq('init'," ) && str_contains( $html, 'c.randomUUID' ) && str_contains( $html, 'c.getRandomValues' ), 'fammaEventId() est défini avant le Pixel, avec crypto.randomUUID et un repli.' );
l5_check( lb_product_page( $product ) === $html, 'Deux rendus de la même fiche donnent le même HTML (page cachable, identifiants tirés à l’exécution).' );
$sources = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/tracking-events.php' );
$placing = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/browser-order-placed.php' );
$core    = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/famma-core.php' );
$class   = substr( $core, (int) strpos( $core, 'final class Tracking {' ), (int) strpos( $core, 'final class Capi {' ) - (int) strpos( $core, 'final class Tracking {' ) );
l5_check( ! str_contains( $sources . $placing, 'wp_generate_uuid4' ) && ! str_contains( $class, 'wp_generate_uuid4' ), 'Ni Tracking, ni Tracking_Events, ni Browser_Order_Placed n’appellent wp_generate_uuid4().' );

// AddToCart of a classic product-page POST, replayed on the next page: browser ID too.
lb_reset();
$events = Famma_Unit_Harness::bare( '\Famma\Core\Tracking_Events' );
$GLOBALS['wc_test_store']->session->set(
	'famma_pending_add_to_cart',
	array(
		array(
			'id'       => 'QA-L5-SKU',
			'quantity' => 1.0,
			'value'    => 49.0,
			'currency' => 'TND',
		),
	)
);
$add = (string) Famma_Unit_Harness::call( $events, 'add_to_cart' );
l5_check( str_contains( $add, '"AddToCart"' ) && str_contains( $add, '{eventID:fammaEventId()}' ) && 0 === preg_match( $uuid, $add ), 'AddToCart (fiche, POST) : eventID tiré dans le navigateur.' );
$listener = (string) Famma_Unit_Harness::call( $events, 'ajax_listener' );
l5_check( str_contains( $listener, 'fammaEventId()' ) && str_contains( $listener, 'data-famma-content-id' ) && str_contains( $listener, 'DOMContentLoaded' ) && str_contains( $listener, '!w.jQuery||!d.body' ), 'AddToCart (liste, AJAX) : eventID navigateur, identifiant Meta du bouton, écouteur posé seulement quand jQuery et document.body existent (exécution réelle : test-browser-scripts.js).' );
l5_check( 0 === preg_match( '/%[^s]/', Tracking_Events::AJAX_LISTENER_SCRIPT ) && 1 === substr_count( Tracking_Events::AJAX_LISTENER_SCRIPT, '%s' ) && str_ends_with( $listener, '})(["content_type","content_ids","contents","value","currency"]);' ), 'Écouteur AJAX : un seul emplacement %s, rempli par les clés du profil.' );

// ── InitiateCheckout : une seule fois par panier et par session (T3.2) ────────
lb_reset();
$GLOBALS['wc_test_is_checkout'] = true;
$GLOBALS['wc_test_store']       = lb_cart( $product, 1.0 );
$ic_one                         = (string) Famma_Unit_Harness::call( $events, 'initiate_checkout' );
$ic_same                        = (string) Famma_Unit_Harness::call( $events, 'initiate_checkout' );
$GLOBALS['wc_test_store']       = lb_cart( $product, 2.0 );
$ic_two                         = (string) Famma_Unit_Harness::call( $events, 'initiate_checkout' );
$key                            = static function ( string $script ): string {
	return 1 === preg_match( '/\}\)\("(famma_ic_[0-9a-f]{32})",function\(\)\{fbq\("track","InitiateCheckout",/', $script, $m ) ? $m[1] : '';
};
$hash                           = $GLOBALS['wc_test_store']->cart->get_cart_hash();
l5_check( '' !== $key( $ic_one ) && str_starts_with( $ic_one, '(function(k,f){var s=null;try{s=window.sessionStorage;if(s.getItem(k)){return;}' ), 'T3.2 InitiateCheckout enveloppé « une fois par session » (sessionStorage).' );
l5_check( $key( $ic_one ) === $key( $ic_same ) && $key( $ic_one ) !== $key( $ic_two ) && sprintf( 'famma_ic_%s', $hash ) === $key( $ic_two ), 'T3.2 La clé porte l’empreinte du panier : même panier, même clé ; panier modifié, nouvelle clé.' );
$GLOBALS['wc_test_is_order_received'] = true;
l5_check( '' === (string) Famma_Unit_Harness::call( $events, 'initiate_checkout' ), 'Pas d’InitiateCheckout sur la page de confirmation.' );

// ── OrderPlaced navigateur (I2, I3) ─────────────────────────────────────────
$placed          = new WC_Order( 6100 );
$placed->status  = 'processing';
$placed->items[] = new WC_Order_Item_Product( $product, 1 );
$confirmation    = static function ( string $order_key ) use ( $events ): string {
	$GLOBALS['wc_test_is_order_received']            = true;
	$GLOBALS['wp_test_query_vars']['order-received'] = '6100';
	$_GET['key']                                     = $order_key;
	$out = Browser_Order_Placed::confirmation_script();
	unset( $_GET['key'] );
	return $out;
};
lb_reset();
l5_check( '' === $confirmation( 'wc_order_unit_test' ), 'I2 Interrupteur coupé (défaut) : aucun OrderPlaced navigateur.' );
lb_reset( array( 'famma_meta_orderplaced_browser' => true ) );
$op = $confirmation( 'wc_order_unit_test' );
l5_check( str_contains( $op, '("famma_orderplaced_6100",function(){fbq("trackCustom","OrderPlaced",' ) && str_contains( $op, '{eventID:"famma_orderplaced_6100"});return true;' ), 'I2 Interrupteur actif : OrderPlaced avec eventID famma_orderplaced_<id>, clé de session = eventID (I3).' );
$params = json_decode( (string) ( preg_match( '/"OrderPlaced",(\{.*?\}),\{eventID:/', $op, $m ) ? $m[1] : '' ), true );
l5_check( is_array( $params ) && 49.0 === (float) $params['value'] && 'TND' === $params['currency'] && array( 'QA-L5-SKU' ) === $params['content_ids'] && ! isset( $params['content_name'] ) && ! isset( $params['famma_stage'] ) && ! isset( $params['famma_product'] ), 'OrderPlaced navigateur : valeur, devise, content_ids ; profil standard sans content_name, famma_stage ni famma_product.' );
l5_check( '' === $confirmation( 'wc_order_mauvaise_cle' ) && '' === $confirmation( '' ), 'Clé de commande absente ou fausse : rien n’est lu ni imprimé.' );
$placed->created_via = 'admin';
l5_check( '' === $confirmation( 'wc_order_unit_test' ), 'Commande créée dans l’administration : pas d’OrderPlaced navigateur.' );
$placed->created_via = 'checkout';

// Express success response.
$placer  = Famma_Unit_Harness::bare( '\Famma\Core\Browser_Order_Placed' );
$express = static function ( array $result ) use ( $placer ): array {
	return $placer->express_order_placed( $result, 6100 );
};
$success = array(
	'result'           => 'success',
	'redirect'         => 'https://qa.invalid/commande/order-received/6100/',
	'order_id'         => 6100,
	'famma_request_id' => 'req',
);
lb_reset();
l5_check( ! isset( $express( $success )['famma_order_placed'] ), 'Express, interrupteur coupé : réponse inchangée.' );
lb_reset( array( 'famma_meta_orderplaced_browser' => true ) );
$joined = $express( $success );
l5_check( 'famma_orderplaced_6100' === ( $joined['famma_order_placed']['event_id'] ?? '' ) && 49.0 === (float) ( $joined['famma_order_placed']['params']['value'] ?? 0 ), 'Express, interrupteur actif : OrderPlaced joint à la réponse de succès (même eventID que la CAPI).' );
$classic = $success;
unset( $classic['famma_request_id'] );
l5_check( $classic === $express( $classic ), 'Checkout classique : réponse inchangée (la page de confirmation s’en charge).' );
lb_login( array( 'manage_woocommerce' ) );
l5_check( ! isset( $express( $success )['famma_order_placed'] ), 'Express, visiteur interne : rien n’est joint.' );
l5_check( str_contains( $core, "add_filter( 'woocommerce_payment_successful_result', array( \$this, 'order_placed_after_checkout' ), 999, 2 );" ) && str_contains( $placing, 'private const EXPRESS_RESULT_PRIORITY = 10010;' ) && str_contains( $placing, "add_filter( 'woocommerce_payment_successful_result', array( \$this, 'express_order_placed' ), self::EXPRESS_RESULT_PRIORITY, 2 );" ), 'Priorités : Capi 999, Express_Idempotency 10000, OrderPlaced navigateur 10010.' );
l5_check( ! str_contains( $sources, 'woocommerce_payment_successful_result' ) && 1 === substr_count( $core, 'Browser_Order_Placed::instance();' ) && 1 === substr_count( $core, "require_once __DIR__ . '/includes/browser-order-placed.php';" ), 'OrderPlaced navigateur : une seule classe branchée (Browser_Order_Placed, instanciée une fois dans boot()), plus rien dans Tracking_Events.' );
$GLOBALS['wp_test_hooks'] = array();
$instance                 = Browser_Order_Placed::instance();
l5_check( Browser_Order_Placed::instance() === $instance && 1 === count( $GLOBALS['wp_test_hooks']['woocommerce_payment_successful_result'] ?? array() ), 'Browser_Order_Placed::instance() : singleton, filtre posé une seule fois.' );

// ── D6 : chargement immédiat (défaut) ou différé ──────────────────────────────
$tiktok = array( 'famma_tiktok_pixel_id' => 'TTQAL5FAKE' );
lb_reset( $tiktok );
$html = lb_product_page( $product );
$args = $GLOBALS['wp_test_scripts']['famma-pixels']['args'];
l5_check( false === $args['in_footer'] && 1 === substr_count( $html, "fbq('init'," ) && str_contains( $html, 'n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;' ), 'D6 Immédiat (défaut) : code standard de Meta dans le <head>, bibliothèque insérée aussitôt en async, 1 seul fbq(init).' );
l5_check( str_contains( $html, 'ttq.load("TTQAL5FAKE");ttq.page()' ) && ! str_contains( $html, 'fammaDefer(function(){ttq.load(' ), 'D6 Immédiat : TikTok suit le même réglage.' );
lb_reset( $tiktok + array( 'famma_meta_pixel_loading' => 'deferred' ) );
$html = lb_product_page( $product );
$args = $GLOBALS['wp_test_scripts']['famma-pixels']['args'];
l5_check( true === $args['in_footer'] && 1 === substr_count( $html, "fbq('init'," ) && str_contains( $html, 'n.queue=[];fammaDefer(function(){t=b.createElement(e);' ) && str_contains( $html, 'requestIdleCallback' ), 'D6 Différé : comportement de la 0.3.1 (pied de page, après load et inactivité), 1 seul fbq(init).' );
l5_check( str_contains( $html, 'fammaDefer(function(){ttq.load("TTQAL5FAKE")});' ), 'D6 Différé : TikTok attend aussi fammaDefer.' );
lb_reset( array( 'famma_meta_pixel_loading' => 'n-importe-quoi' ) );
lb_product_page( $product );
l5_check( false === $GLOBALS['wp_test_scripts']['famma-pixels']['args']['in_footer'], 'Valeur de réglage inconnue : chargement immédiat (valeur sûre par défaut).' );
lb_reset();
add_filter( 'famma_defer_pixels', static fn(): bool => true );
lb_product_page( $product );
l5_check( true === $GLOBALS['wp_test_scripts']['famma-pixels']['args']['in_footer'], 'Filtre famma_defer_pixels conservé : il peut encore forcer le différé.' );
lb_reset();
lb_product_page( $product );
l5_check( isset( $GLOBALS['wp_test_scripts']['famma-pixels'], $GLOBALS['wp_test_scripts']['famma-fbc'] ), 'Handles famma-pixels et famma-fbc conservés.' );

// D6 sous LiteSpeed : en immédiat, le Pixel n'est ni différé ni retardé.
$tracker   = Famma_Unit_Harness::bare( '\Famma\Core\Tracking' );
$immediate = $tracker->immediate_attributes( array( 'id' => 'famma-pixels-js-after' ) );
$other     = $tracker->immediate_attributes( array( 'id' => 'famma-express-js-extra' ) );
l5_check( '1' === ( $immediate['data-no-optimize'] ?? '' ) && '1' === ( $immediate['data-no-defer'] ?? '' ) && ! isset( $other['data-no-optimize'] ), 'D6 Immédiat : data-no-optimize et data-no-defer sur famma-pixels seulement.' );
l5_check( array( 'jquery', 'famma-pixels' ) === $tracker->litespeed_exclude( array( 'jquery' ) ), 'D6 Immédiat : famma-pixels ajouté aux exclusions JS de LiteSpeed.' );
lb_reset( array( 'famma_meta_pixel_loading' => 'deferred' ) );
l5_check( ! isset( $tracker->immediate_attributes( array( 'id' => 'famma-pixels-js-after' ) )['data-no-optimize'] ) && array( 'jquery' ) === $tracker->litespeed_exclude( array( 'jquery' ) ), 'D6 Différé : LiteSpeed garde la main, comme en 0.3.1.' );
lb_reset();
l5_check( str_starts_with( Tracking::EVENT_ID_SCRIPT, 'window.fammaEventId=window.fammaEventId||' ) && str_contains( Tracking_Events::ONCE_SCRIPT, 'sessionStorage' ), 'fammaEventId() ne se redéfinit jamais (un seul générateur par page).' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
