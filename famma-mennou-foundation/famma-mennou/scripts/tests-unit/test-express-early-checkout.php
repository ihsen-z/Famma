<?php
/**
 * Lot 6 (Q16) — InitiateCheckout du formulaire express dès un téléphone valide.
 *
 * Côté PHP : l'interrupteur « Mesure » (option famma_meta_ic_early, activé par
 * défaut), son enregistrement, et la donnée que le serveur imprime pour le
 * script (`Express_Preparation::early_checkout_data()`, passée par le profil
 * de données). Le déclenchement unique côté navigateur est testé par
 * `test-express-early-checkout.js`.
 * Run with: php scripts/tests-unit/test-express-early-checkout.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap-browser.php.
require __DIR__ . '/bootstrap-browser.php';

// phpcs:disable Squiz.Commenting, WordPress.NamingConventions.PrefixAllGlobals -- Doubles of the settings API.
if ( ! class_exists( '\Famma\Core\Tracking_Settings' ) ) {
	// phpcs:ignore Squiz.PHP.Eval.Discouraged -- Test double of a constants-only class.
	eval( 'namespace Famma\Core; final class Tracking_Settings { public const GROUP = "famma_tracking"; public const SLUG = "famma-tracking"; }' );
}

function register_setting( string $group, string $option, array $args = array() ): void {
	$GLOBALS['lb_settings'][ $option ] = $args;
}
function add_settings_section(): void {}
function add_settings_field( string $id ): void {
	$GLOBALS['lb_fields'][] = $id;
}
function rest_sanitize_boolean( $value ): bool {
	return is_string( $value ) ? ! in_array( strtolower( $value ), array( 'false', '0', '' ), true ) : (bool) $value;
}

Famma_Unit_Harness::load_include( 'measurement-settings.php' );

use Famma\Core\Config;
use Famma\Core\Express_Preparation;
use Famma\Core\Measurement_Settings;

$product = new WC_Product( 16, 'QA-L5-SKU', 'QA-L5 Ceinture', 'qa-l5-ceinture', 49.0 );

// Activé par défaut : option jamais enregistrée.
lb_reset();
l5_check( true === Config::instance()->meta_initiate_checkout_early(), 'Interrupteur activé par défaut (option absente).' );
$data = Express_Preparation::early_checkout_data( $product );
l5_check( array( 'unit', 'params' ) === array_keys( $data ), 'Interrupteur actif : la donnée du script porte unit et params.' );
l5_check( 49.0 === $data['unit'], 'unit = prix affiché unitaire.' );
l5_check( 49.0 === (float) $data['params']['value'] && 'product' === $data['params']['content_type'], 'params : value (quantité 1) et content_type.' );
l5_check( array( '16' ) === array_map( 'strval', $data['params']['content_ids'] ), 'params : content_ids = règle unique Meta_Ids.' );
l5_check( 1 === $data['params']['contents'][0]['quantity'] && ! empty( $data['params']['currency'] ), 'params : contents[0].quantity = 1 et devise présente.' );
l5_check( ! array_key_exists( 'content_name', $data['params'] ), 'Profil standard : pas de content_name.' );

// Coupé : plus rien n'est passé au script, le comportement d'avant (au clic) reprend.
lb_reset( array( 'famma_meta_ic_early' => false ) );
l5_check( false === Config::instance()->meta_initiate_checkout_early(), 'Option à false : interrupteur coupé.' );
l5_check( array() === Express_Preparation::early_checkout_data( $product ), 'Interrupteur coupé : donnée vide (le script garde l’envoi au clic).' );

// Profil minimal : seuls value, currency, content_ids, content_type.
lb_reset( array( 'famma_meta_data_profile' => 'minimal' ) );
$minimal = Express_Preparation::early_checkout_data( $product );
l5_check( ! array_key_exists( 'contents', $minimal['params'] ) && isset( $minimal['params']['value'], $minimal['params']['currency'] ), 'Profil minimal : contents retiré, value et currency gardés.' );

// Réglage enregistré dans « Mesure » : booléen, défaut true, champ présent.
lb_reset();
$GLOBALS['lb_settings'] = array();
$GLOBALS['lb_fields']   = array();
Measurement_Settings::instance()->register();
$setting = $GLOBALS['lb_settings']['famma_meta_ic_early'] ?? array();
l5_check( 'boolean' === ( $setting['type'] ?? '' ) && true === ( $setting['default'] ?? null ), 'Option enregistrée : booléenne, défaut true.' );
l5_check( in_array( 'famma_meta_ic_early', $GLOBALS['lb_fields'], true ), 'Case ajoutée à l’écran Mesure.' );
l5_check( false === call_user_func( $setting['sanitize_callback'], '0' ) && true === call_user_func( $setting['sanitize_callback'], '1' ), 'Case décochée (« 0 » du champ caché) = false, cochée = true.' );
l5_check( false === ( $GLOBALS['lb_settings']['famma_meta_orderplaced_browser']['default'] ?? null ), 'Les autres cases booléennes gardent leur défaut false.' );

// Le serveur continue d'annoncer InitiateCheckout au devis : le script décide (aucun doublon côté PHP).
$source = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/express-preparation.php' );
l5_check( str_contains( $source, "'initiate_checkout' => array(" ), 'Le devis garde le bloc initiate_checkout (repli interrupteur coupé et page en cache).' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
