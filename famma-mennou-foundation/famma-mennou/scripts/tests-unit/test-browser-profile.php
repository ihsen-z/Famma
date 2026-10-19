<?php
/**
 * Lot 5, B3 (R3, V5 côté navigateur) — the Meta data profile applied to browser
 * events: "standard" (default) drops content_name (and famma_stage,
 * famma_product) from ViewContent, AddToCart, InitiateCheckout and OrderPlaced;
 * "minimal" keeps only value, currency, content_ids and content_type, on every
 * browser path (product page, AJAX loop listener, express quote, checkout).
 * Run with: php scripts/tests-unit/test-browser-profile.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap-browser.php.
require __DIR__ . '/bootstrap-browser.php';

use Famma\Core\Capi_Data_Profile;
use Famma\Core\Tracking_Events;

$product           = new WC_Product( 16, 'QA-L5-SKU', 'QA-L5 Ceinture', 'qa-l5-ceinture', 49.0 );
$events            = Famma_Unit_Harness::bare( '\Famma\Core\Tracking_Events' );
$placed            = new WC_Order( 6200 );
$placed->items[]   = new WC_Order_Item_Product( $product, 1 );
$minimal_keys      = array( 'content_ids', 'content_type', 'currency', 'value' );
$sorted            = static function ( array $keys ): array {
	sort( $keys );
	return $keys;
};
$prep              = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
$excluded_standard = array( 'content_name', 'famma_stage', 'famma_product' );

// Profil standard (défaut).
lb_reset();
$standard = lb_all_events( $product, $events );
l5_check( 3 === count( $standard ), 'Profil standard : ViewContent, AddToCart et InitiateCheckout émis.' );
foreach ( $standard as $event => $keys ) {
	l5_check( array() === array_intersect( $excluded_standard, $keys ), "V5 Standard, {$event} : ni content_name, ni famma_stage, ni famma_product." );
	l5_check( array() === array_diff( $minimal_keys, $keys ), "V5 Standard, {$event} : value, currency, content_ids et content_type présents." );
}
l5_check( in_array( 'contents', $standard['AddToCart'], true ) && in_array( 'num_items', $standard['InitiateCheckout'], true ), 'Standard : contents et num_items conservés.' );
$op = Tracking_Events::order_placed_params( $placed );
l5_check( array() === array_intersect( $excluded_standard, array_keys( $op ) ) && isset( $op['contents'], $op['order_id'] ), 'V5 Standard, OrderPlaced navigateur : mêmes retraits que la CAPI.' );
l5_check( array( 'famma_stage', 'famma_product', 'content_name' ) === Capi_Data_Profile::STANDARD_EXCLUDED, 'Standard : liste des retraits partagée avec le profil serveur (Capi_Data_Profile).' );
$listener = (string) Famma_Unit_Harness::call( $events, 'ajax_listener' );
l5_check( str_contains( $listener, 'keep=["content_type","content_ids","contents","value","currency"]' ), 'Standard : l’écouteur AJAX garde aussi contents.' );

// Profil minimal.
lb_reset( array( 'famma_meta_data_profile' => 'minimal' ) );
foreach ( lb_all_events( $product, $events ) as $event => $keys ) {
	l5_check( $sorted( $minimal_keys ) === $sorted( $keys ), "V5 Minimal, {$event} : seulement value, currency, content_ids, content_type." );
}
$op = Tracking_Events::order_placed_params( $placed );
l5_check( $sorted( $minimal_keys ) === $sorted( array_keys( $op ) ), 'V5 Minimal, OrderPlaced navigateur : seulement la liste prévue.' );
$listener = (string) Famma_Unit_Harness::call( $events, 'ajax_listener' );
l5_check( str_contains( $listener, 'keep=["content_type","content_ids","value","currency"]' ), 'V5 Minimal : l’écouteur AJAX (liste produits) retire contents.' );
$express = (array) Famma_Unit_Harness::call( '\Famma\Core\Express_Preparation', 'tracking', array( $prep, $product, 1.0, 0.0 ) );
l5_check( $sorted( $minimal_keys ) === $sorted( array_keys( $express['add_to_cart']['params'] ) ) && $sorted( $minimal_keys ) === $sorted( array_keys( $express['initiate_checkout']['params'] ) ), 'V5 Minimal, express : AddToCart et InitiateCheckout réduits à la liste prévue.' );

// Valeur inconnue : profil standard (valeur sûre).
lb_reset( array( 'famma_meta_data_profile' => 'complet' ) );
l5_check( ! in_array( 'content_name', lb_all_events( $product, $events )['ViewContent'], true ), 'Profil inconnu : standard appliqué.' );

// Ancien comportement : filtre de code seulement.
lb_reset();
add_filter( 'famma_meta_data_profile_legacy', static fn(): bool => true );
l5_check( in_array( 'content_name', lb_all_events( $product, $events )['ViewContent'], true ), 'Filtre famma_meta_data_profile_legacy : content_name revient (code seulement, jamais l’admin).' );

// Filtre final famma_meta_browser_params.
lb_reset();
$calls = array();
add_filter(
	'famma_meta_browser_params',
	static function ( array $params, string $profile, string $event ) use ( &$calls ): array {
		$calls[] = $profile . ':' . $event;
		unset( $params['currency'] );
		return $params;
	},
	10,
	3
);
$keys = lb_all_events( $product, $events );
l5_check( in_array( 'standard:ViewContent', $calls, true ) && ! in_array( 'currency', $keys['ViewContent'], true ), 'Filtre famma_meta_browser_params : reçoit le profil et l’événement, son résultat est envoyé.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
