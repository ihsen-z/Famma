<?php
/**
 * Lot 5, A4 (R7), A5 (R8) and A14 (R3) — health rules and e-mail, resend
 * bounds of the reconciliation, server data profile; watchdog mu-plugin.
 * Run with: php scripts/tests-unit/test-capi-health.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';
Famma_Unit_Harness::load_include( 'capi-reconcile.php' );

use Famma\Core\Capi;
use Famma\Core\Capi_Data_Profile;
use Famma\Core\Capi_Health;
use Famma\Core\Capi_Health_Rules;
use Famma\Core\Capi_Reconcile;
use Famma\Core\Config;
use Famma\Core\Order_Statuses;

$now   = time();
$codes = static fn( array $state ): array => array_column( $state['raisons'], 'code' );

l5_reset();
l5_check( 'vert' === Capi_Health::evaluate( $now )['statut'], 'Configured, in production, nothing wrong: green.' );

// V6 — heartbeat older than 36 h, checked on admin_init without cron.
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = $now - 37 * 3600;
$state = Capi_Health::evaluate( $now );
l5_check( 'rouge' === $state['statut'] && in_array( 'heartbeat', $codes( $state ), true ), 'V6: heartbeat older than 36 h → red.' );
l5_check( 1 === count( $GLOBALS['wp_test_mail'] ) && ! str_contains( $GLOBALS['wp_test_mail'][0][2], 'QA-L5-FAKE-TOKEN' ), 'V6: one e-mail on the switch to red, without the token.' );
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = $now;
Capi_Health::evaluate( $now + 60 );
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = $now - 40 * 3600;
Capi_Health::evaluate( $now + 120 );
l5_check( 1 === count( $GLOBALS['wp_test_mail'] ), 'V6: two switches to red within 24 h → a single e-mail.' );
Capi_Health::evaluate( $now + 86400 + 60 );
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = $now + 86400;
Capi_Health::evaluate( $now + 86400 + 120 );
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = $now - 40 * 3600;
Capi_Health::evaluate( $now + 86400 + 180 );
l5_check( 2 === count( $GLOBALS['wp_test_mail'] ), 'V6: after 24 h, a new switch to red sends a new e-mail.' );

// V6 — delivered order without Purchase for more than 24 h; test orders excluded.
l5_reset();
$late                           = new WC_Order( 6000 );
$late->paid_at                  = ( new DateTimeImmutable() )->setTimestamp( $now - 25 * 3600 );
$test                           = new WC_Order( 6001 );
$test->paid_at                  = $late->paid_at;
$test->meta                     = array( '_famma_test_order' => 1 );
$fresh                          = new WC_Order( 6002 );
$fresh->paid_at                 = ( new DateTimeImmutable() )->setTimestamp( $now - 3600 );
$GLOBALS['wc_test_order_query'] = array( $late, $test, $fresh );
$state                          = Capi_Health::evaluate( $now );
l5_check(
	'rouge' === $state['statut'] && in_array(
		array(
			'code'   => 'purchase_missing',
			'niveau' => 'rouge',
			'detail' => '1',
		),
		$state['raisons'],
		true
	),
	'V6: one delivered order without Purchase for 24 h → red (test order and recent order not counted).'
);
$GLOBALS['wc_test_order_query'] = array();

// V6 / V7 — « Terminée » COD order not collected (before 0.3.2): the sender, the
// reconciliation and the health state share one rule, so no red state without remedy.
l5_reset();
$legacy             = new WC_Order( 6100 );
$legacy->status     = 'completed';
$legacy->paid_at    = ( new DateTimeImmutable() )->setTimestamp( $now - 25 * 3600 );
$collected          = new WC_Order( 6101 );
$collected->status  = 'completed';
$collected->paid_at = $legacy->paid_at;
$collected->meta    = array( '_famma_cod_paid' => 'yes' );
$card               = new WC_Order( 6102 );
$card->status       = 'completed';
$card->payment      = 'stripe';
$card->paid_at      = $legacy->paid_at;
l5_check( 'not_collected' === Order_Statuses::purchase_ineligibility( $legacy ) && '' === Order_Statuses::purchase_ineligibility( $collected ) && 'not_delivered' === Order_Statuses::purchase_ineligibility( $card ) && '' === Order_Statuses::purchase_ineligibility( new WC_Order( 6103 ) ), 'One Purchase rule: delivered or collected COD « Terminée » expected; not collected → not_collected; other payment → not_delivered.' );
$GLOBALS['wc_test_order_query'] = array( $legacy, $card );
$state                          = Capi_Health::evaluate( $now );
l5_check( 'vert' === $state['statut'] && ! in_array( 'purchase_missing', $codes( $state ), true ) && array() === $GLOBALS['wp_test_mail'], 'V6: a « Terminée » order never collected is not a missing Purchase: no red, no e-mail.' );
$GLOBALS['wc_test_order_query'] = array( $legacy, $collected, $card );
$state                          = Capi_Health::evaluate( $now );
l5_check(
	in_array(
		array(
			'code'   => 'purchase_missing',
			'niveau' => 'rouge',
			'detail' => '1',
		),
		$state['raisons'],
		true
	),
	'V6: a collected « Terminée » order without Purchase still counts (1 out of 3).'
);
$reconcile = Capi_Reconcile::instance();
l5_check(
	array(
		'expected' => false,
		'reason'   => 'not_collected',
		'state'    => 'absent',
		'resend'   => false,
	) === $reconcile->cell( $legacy, 'Purchase', array(), $now ),
	'V7: the reconciliation uses the sender rule: not_collected, not expected, no « Renvoyer » button.'
);
l5_check( $reconcile->cell( $collected, 'Purchase', array(), $now )['expected'] && $reconcile->cell( $collected, 'Purchase', array(), $now )['resend'], 'V7: a collected « Terminée » order is expected and can be sent.' );
$reconcile_totals = $reconcile->build( $now )['totals'];
l5_check( 1 === ( $reconcile_totals['ineligible']['not_collected'] ?? 0 ) && 1 === $reconcile_totals['purchase_expected'], 'V7: « Inéligibles » line counts the order with its reason (not_collected : 1).' );
$GLOBALS['wc_test_log'] = array();
l5_check( 'ineligible' === Capi::instance()->resend( $legacy, 'Purchase' ) && array() === $GLOBALS['wp_test_http'] && str_contains( l5_capi_lines()[0], 'state=ineligible reason=not_collected' ), 'A resend of such an order is refused by the sender with the same reason, without any request.' );
$GLOBALS['wc_test_order_query'] = array();

// Pending famma-capi action for more than 2 h.
l5_reset();
$GLOBALS['as_test_late_actions'] = array( 42 );
l5_check( in_array( 'pending_actions', $codes( Capi_Health::evaluate( $now ) ), true ), 'A famma-capi action pending for more than 2 h → red.' );
$GLOBALS['as_test_late_actions'] = array();

// Orange rules: served version, minimal profile, fbc rejected (all formats), environment.
l5_reset( array( 'famma_meta_data_profile' => 'minimal' ) );
Capi_Health::record_outcome(
	'accepted_warning',
	array(
		'version_requested' => 'v26.0',
		'version_served'    => 'v27.0',
		'version_mismatch'  => true,
	)
);
$state = Capi_Health::evaluate( $now );
l5_check( 'orange' === $state['statut'] && array( 'version_served', 'profile_minimal' ) === $codes( $state ), 'G4: served version different from the requested one → orange; minimal profile → orange.' );
$GLOBALS['wp_test_options']['famma_capi_fbc_rejected'] = array(
	gmdate( 'Y-m-d', $now ) => 2,
	'2020-01-01'            => 9,
);
l5_check( 2 === Capi_Health_Rules::fbc_rejected( $now ), 'fbc_rejected counted on 7 days (daily counters).' );
$GLOBALS['wp_test_options']['famma_capi_fbc_rejected'] = array(
	'count' => 3,
	'at'    => $now - 8 * 86400,
);
l5_check( 0 === Capi_Health_Rules::fbc_rejected( $now ), 'fbc_rejected older than 7 days is ignored.' );
$GLOBALS['wp_test_options']['famma_capi_fbc_rejected'] = 4;
l5_check( in_array( 'fbc_rejected', $codes( Capi_Health::evaluate( $now ) ), true ), 'fbc_rejected non zero → orange reason.' );

// Stored shape read by the watchdog mu-plugin.
$stored = $GLOBALS['wp_test_options']['famma_capi_health'];
l5_check( array( 'statut', 'raisons', 'verifie_le' ) === array_keys( $stored ) && $now === $stored['verifie_le'], 'famma_capi_health = {statut, raisons[], verifie_le}.' );

// Watchdog mu-plugin, independent of famma-core.
require ABSPATH . 'wp-content/mu-plugins/famma-measurement-watchdog.php';
$GLOBALS['wp_test_options']['active_plugins'] = array();
l5_check( str_contains( implode( ' ', \Famma\Watchdog\problems( $now ) ), 'inactive' ), 'Watchdog: famma-core inactive is reported.' );
$GLOBALS['wp_test_options']['active_plugins']    = array( 'famma-core/famma-core.php' );
$GLOBALS['wp_test_options']['famma_capi_health'] = array(
	'statut'     => 'vert',
	'raisons'    => array(),
	'verifie_le' => $now,
);
l5_check( array() === \Famma\Watchdog\problems( $now ), 'Watchdog: configured and green → nothing.' );
$GLOBALS['wp_test_options']['famma_capi_health']['verifie_le'] = $now - 3 * 86400;
l5_check( 1 === count( \Famma\Watchdog\problems( $now ) ), 'Watchdog: stale health state is reported.' );
$GLOBALS['wp_test_options']['famma_meta_pixel_id'] = '';
l5_check( 2 === count( \Famma\Watchdog\problems( $now ) ), 'Watchdog: empty pixel is reported.' );
$GLOBALS['wp_test_mail'] = array();
\Famma\Watchdog\check();
\Famma\Watchdog\check();
l5_check( 1 === count( $GLOBALS['wp_test_mail'] ), 'Watchdog: at most one e-mail per day.' );

// R7 / V6 — one e-mail per alert: the switch to red is mailed by famma-core only; the
// watchdog shows it in its notice, and mails only what famma-core cannot report itself.
l5_reset();
$GLOBALS['wp_test_options']['active_plugins']       = array( 'famma-core/famma-core.php' );
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = time() - 37 * 3600;
$red_state = Capi_Health::evaluate( time() );
\Famma\Watchdog\check();
\Famma\Watchdog\check();
ob_start();
\Famma\Watchdog\notice();
$watchdog_notice = (string) ob_get_clean();
l5_check( 'rouge' === $red_state['statut'] && 1 === count( $GLOBALS['wp_test_mail'] ) && str_contains( $watchdog_notice, 'alerte rouge' ), 'V6: a switch to red gives exactly one e-mail in all (famma-core); the watchdog shows it as a notice.' );
l5_check( array( 'La mesure Meta est en alerte rouge : voir FAMMA → Mesure.' ) === \Famma\Watchdog\problems() && array() === \Famma\Watchdog\mail_problems(), 'Watchdog: the red state is a notice, never a watchdog e-mail.' );
$GLOBALS['wp_test_options']['active_plugins'] = array();
\Famma\Watchdog\check();
l5_check( 2 === count( $GLOBALS['wp_test_mail'] ) && str_contains( $GLOBALS['wp_test_mail'][1][2], 'inactive' ), 'Watchdog: famma-core inactive → its own e-mail, since famma-core cannot send it.' );

// A5 / V7 — resend bounds.
$updated = static fn( int $age ): array => array( 'updated_at' => gmdate( 'Y-m-d H:i:s', $now - $age ) );
l5_check( ! Capi_Reconcile::resend_allowed( 'accepted', $updated( 60 ), $now - 60, $now ), 'V7: never a resend for an accepted event.' );
l5_check( Capi_Reconcile::resend_allowed( 'rejected', $updated( 5 * 86400 ), $now - 5 * 86400, $now ), 'V7: rejected 5 days ago → resend allowed.' );
l5_check( ! Capi_Reconcile::resend_allowed( 'rejected', $updated( 7 * 86400 ), $now - 7 * 86400, $now ), 'V7: rejected 7 days ago → refused.' );
l5_check( ! Capi_Reconcile::resend_allowed( 'uncertain', $updated( 25 * 3600 ), $now - 25 * 3600, $now ), 'V7: uncertain 25 h ago → refused.' );
l5_check( Capi_Reconcile::resend_allowed( 'uncertain', $updated( 2 * 3600 ), $now - 2 * 3600, $now ), 'V7: uncertain 2 h ago → allowed.' );
l5_check( ! Capi_Reconcile::resend_allowed( 'sending', $updated( 60 ), $now - 60, $now ) && Capi_Reconcile::resend_allowed( 'sending', $updated( 20 * 60 ), $now - 20 * 60, $now ), 'V7: a send in progress only after 15 min (taken as uncertain).' );
l5_check( Capi_Reconcile::resend_allowed( 'absent', array(), $now - 86400, $now ) && ! Capi_Reconcile::resend_allowed( 'absent', array(), $now - 7 * 86400, $now ), 'V7: a never-sent event within 6 days can be sent.' );
l5_check( 518400 === Config::CAPI_RESEND_REJECTED_MAX_AGE && 86400 === Config::CAPI_RESEND_UNCERTAIN_MAX_AGE, 'Resend delays live in Config.' );

// A14 / V5 — server data profile.
$custom = array(
	'currency'      => 'TND',
	'value'         => 49.0,
	'order_id'      => '1',
	'content_type'  => 'product',
	'content_ids'   => array( 'SKU' ),
	'content_name'  => 'Ceinture',
	'famma_product' => 'ceinture',
	'famma_stage'   => 'order_placed',
);
l5_reset();
$standard = Capi_Data_Profile::apply( $custom, 'OrderPlaced' );
l5_check( ! isset( $standard['famma_stage'], $standard['famma_product'], $standard['content_name'] ) && isset( $standard['content_ids'], $standard['value'] ), 'V5: standard profile → no famma_stage, famma_product nor content_name.' );
$GLOBALS['wp_test_options']['famma_meta_data_profile'] = 'minimal';
l5_check( array( 'currency', 'value' ) === array_keys( Capi_Data_Profile::apply( $custom, 'Purchase' ) ), 'V5: minimal profile → currency and value only.' );
$GLOBALS['wp_test_options']['famma_meta_data_profile'] = 'everything';
l5_check( 'standard' === Config::instance()->meta_data_profile(), 'V5: a value out of the list falls back to standard.' );
add_filter( 'famma_meta_data_profile_legacy', static fn(): bool => true );
l5_check( Capi_Data_Profile::apply( $custom, 'OrderPlaced' ) === $custom, 'The former behaviour only comes back through a code filter.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
