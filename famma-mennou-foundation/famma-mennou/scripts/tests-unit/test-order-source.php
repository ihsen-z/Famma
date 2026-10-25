<?php
/**
 * Lot 5, B6 (R12, V8, T2.9) — first-party attribution independent of any pixel:
 * the UTM capture script is printed even without a Meta or TikTok pixel, and
 * every checkout order gets the synthetic _famma_order_source (UTM, then
 * fbclid, then external referrer, then a valid _fbc, else direct), while the
 * existing _famma_utm_* metas keep their exact meaning.
 * The runtime of the capture script is checked by test-browser-scripts.js.
 * Run with: php scripts/tests-unit/test-order-source.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap-browser.php.
require __DIR__ . '/bootstrap-browser.php';

use Famma\Core\Order_Source;

// ── Synthèse de la source ───────────────────────────────────────────────────
// Each case: label, cookie famma_utm decoded, valid _fbc present, expected source.
$cases = array(
	array(
		'UTM source et support',
		array(
			'utm_source' => 'facebook',
			'utm_medium' => 'paid',
		),
		false,
		'facebook / paid',
	),
	array( 'UTM sans support', array( 'utm_source' => 'Instagram' ), false, 'instagram' ),
	array(
		'UTM l’emporte sur fbclid et référent',
		array(
			'utm_source' => 'facebook',
			'utm_medium' => 'cpc',
			'fbclid'     => 1,
			'ref'        => 'l.facebook.com',
		),
		true,
		'facebook / cpc',
	),
	array(
		'fbclid sans UTM',
		array(
			'fbclid' => 1,
			'ref'    => 'l.facebook.com',
		),
		false,
		'facebook / fbclid',
	),
	array( 'référent externe', array( 'ref' => 'www.Google.com' ), false, 'google.com / referral' ),
	array( 'référent forgé, nettoyé', array( 'ref' => 'evil.com/<script>' ), false, 'evil.comscript / referral' ),
	array( 'cookie _fbc valide seul', array(), true, 'facebook / fbclid' ),
	array( 'référent récent avant un _fbc ancien', array( 'ref' => 'google.com' ), true, 'google.com / referral' ),
	array( 'accès direct', array(), false, 'direct' ),
	array(
		'valeurs non scalaires',
		array(
			'utm_source' => array( 'x' ),
			'ref'        => array( 'y' ),
		),
		false,
		'direct',
	),
	array( 'balise dans utm_source', array( 'utm_source' => '<b>Meta</b> Ads' ), false, 'meta ads' ),
);
foreach ( $cases as $case ) {
	list( $label, $cookie, $has_fbc, $expected ) = $case;
	$got = Order_Source::source( $cookie, $has_fbc );
	l5_check( $expected === $got, "Source « {$label} » = {$expected} (obtenu {$got})" );
}
l5_check( 64 === strlen( Order_Source::source( array( 'utm_source' => str_repeat( 'a', 300 ) ), false ) ), 'Source bornée (64 caractères par partie).' );

// ── Recopie sur la commande ─────────────────────────────────────────────────
$tracking = Famma_Unit_Harness::bare( '\Famma\Core\Tracking' );
$stamp    = static function ( ?string $cookie, string $fbc = '' ) use ( $tracking ): WC_Order {
	static $n = 6300;
	unset( $_COOKIE['famma_utm'], $_COOKIE['_fbc'] );
	if ( null !== $cookie ) {
		$_COOKIE['famma_utm'] = $cookie;
	}
	if ( '' !== $fbc ) {
		$_COOKIE['_fbc'] = $fbc;
	}
	$created = new WC_Order( ++$n );
	$tracking->stamp_utm_on_order( $created );
	unset( $_COOKIE['famma_utm'], $_COOKIE['_fbc'] );
	return $created;
};
lb_reset();

$full    = array(
	'utm_source'   => 'facebook',
	'utm_medium'   => 'paid',
	'utm_campaign' => 'olympe',
	'fbclid'       => 1,
	'ref'          => 'l.facebook.com',
);
$stamped = $stamp( (string) json_encode( $full ) );
l5_check( 'facebook' === $stamped->get_meta( '_famma_utm_source' ) && 'paid' === $stamped->get_meta( '_famma_utm_medium' ) && 'olympe' === $stamped->get_meta( '_famma_utm_campaign' ), '_famma_utm_* recopiés comme avant (tableau de bord inchangé).' );
l5_check( 'facebook / paid' === $stamped->get_meta( '_famma_order_source' ), '_famma_order_source = facebook / paid.' );
l5_check( ! isset( $stamped->meta['_famma_fbclid'] ) && ! isset( $stamped->meta['_famma_ref'] ), 'Les nouvelles clés du cookie (fbclid, ref) ne créent aucune méta parasite.' );

$stamped = $stamp( (string) json_encode( array( 'ref' => 'google.com' ) ) );
l5_check( '' === $stamped->get_meta( '_famma_utm_source' ) && 'google.com / referral' === $stamped->get_meta( '_famma_order_source' ), 'Cookie sans UTM : aucune _famma_utm_*, source = référent.' );

$stamped = $stamp( null );
l5_check( 'direct' === $stamped->get_meta( '_famma_order_source' ) && array( '_famma_order_source' ) === array_keys( $stamped->meta ), 'Sans cookie : source « direct », toujours renseignée (V8).' );

$stamped = $stamp( null, 'fb.1.1760000000000.IwAR3' );
l5_check( 'facebook / fbclid' === $stamped->get_meta( '_famma_order_source' ), 'Sans cookie de provenance mais _fbc valide : facebook / fbclid.' );

$stamped = $stamp( '{pas du json' );
l5_check( 'direct' === $stamped->get_meta( '_famma_order_source' ) && '' === $stamped->get_meta( '_famma_utm_source' ), 'Cookie illisible : ignoré, source « direct ».' );

$stamped = $stamp( str_repeat( 'x', 3000 ) );
l5_check( 'direct' === $stamped->get_meta( '_famma_order_source' ), 'Cookie trop long (> 2 Kio) : ignoré.' );

$stamped = $stamp( null, 'fb.1.pas-un-fbc' );
l5_check( 'direct' === $stamped->get_meta( '_famma_order_source' ) && false === get_option( 'famma_capi_fbc_rejected', false ), 'Un _fbc invalide ne compte pas comme clic Meta, et la source ne touche pas au compteur fbc_rejected.' );

// Tracking::stamp_utm_on_order() (hook à 30, API publique de la 0.3.1) délègue à Order_Source::stamp().
$_COOKIE['famma_utm'] = (string) json_encode( $full );
$direct               = new WC_Order( 6390 );
Order_Source::stamp( $direct );
unset( $_COOKIE['famma_utm'] );
l5_check( $stamp( (string) json_encode( $full ) )->meta === $direct->meta, 'Tracking::stamp_utm_on_order() et Order_Source::stamp() écrivent exactement les mêmes métas.' );
$core = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/famma-core.php' );
l5_check( 1 === substr_count( $core, "add_action( 'woocommerce_checkout_create_order', array( \$this, 'stamp_utm_on_order' ), 30 );" ) && 1 === substr_count( $core, "require_once __DIR__ . '/includes/order-source.php';" ) && ! str_contains( $core, 'UTM_SCRIPT = ' ) && ! str_contains( $core, 'function order_source(' ), 'Un seul hook de recopie (priorité 30), une seule définition de la capture (Order_Source).' );

// ── V8 / T2.9 : capture UTM sans aucun Pixel ──────────────────────────────────
lb_reset( array( 'famma_meta_pixel_id' => '' ) );
$scripts = lb_render_page();
$inline  = lb_inline( 'famma-pixels' );
l5_check( isset( $scripts['famma-pixels'] ) && str_contains( $inline, 'famma_utm=' ) && str_contains( $inline, Order_Source::UTM_SCRIPT ), 'V8 Pixel vidé : le script qui pose famma_utm est imprimé quand même.' );
l5_check( ! str_contains( $inline, 'fbq(' ) && ! isset( $scripts['famma-fbc'] ), 'V8 Pixel vidé : ni fbq ni famma-fbc.' );
lb_reset();
lb_render_page();
l5_check( 1 === substr_count( lb_inline( 'famma-pixels' ), Order_Source::UTM_SCRIPT ), 'Pixel configuré : une seule capture UTM par page.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
