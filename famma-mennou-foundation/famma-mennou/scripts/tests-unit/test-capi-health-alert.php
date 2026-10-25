<?php
/**
 * Relecture du lot 5, R-CACHE-2 — un rouge déjà allumé ne rend plus l'alerte
 * aveugle : e-mail pour toute nouvelle raison rouge (limite de 24 h gardée),
 * Purchase manquant sans remède classé orange, acquittement par commande.
 * Run with: php scripts/tests-unit/test-capi-health-alert.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.WrongStyle -- Doubles are documented by the API they replace.
// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.WP.GlobalVariablesOverride.Prohibited -- Standalone runner: $_POST is the fixture of the admin-post request.
require __DIR__ . '/bootstrap-capi.php';

use Famma\Core\Capi_Health;
use Famma\Core\Capi_Health_Rules;
use Famma\Core\Capi_Purchase_Gaps;
use Famma\Core\Capi_Reconcile;

// Screen and request doubles.
function esc_url( $url ): string {
	return (string) $url;
}
function wp_nonce_field( string $action ): void {
	echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '" />';
}
function submit_button( string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true ): void {
	echo '<button>' . esc_html( $text ) . '</button>';
}
function absint( $value ): int {
	return abs( (int) $value );
}

$now     = time();
$codes   = static fn( array $state ): array => array_column( $state['raisons'], 'code' );
$reason  = static function ( array $state, string $code ): array {
	foreach ( $state['raisons'] as $item ) {
		if ( $code === $item['code'] ) {
			return $item;
		}
	}
	return array();
};
$mails   = static fn(): int => count( $GLOBALS['wp_test_mail'] ?? array() );
$updated = static fn( int $age ): string => gmdate( 'Y-m-d H:i:s', $now - $age );
$order   = static function ( int $id, int $paid_ago, array $meta = array() ) use ( $now ): WC_Order {
	$wc_order          = new WC_Order( $id );
	$wc_order->paid_at = ( new DateTimeImmutable() )->setTimestamp( $now - $paid_ago );
	$wc_order->meta    = $meta;
	return $wc_order;
};
$handle  = static function (): string {
	try {
		Capi_Purchase_Gaps::instance()->handle_ack();
	} catch ( Famma_Test_Redirect $redirect ) {
		return $redirect->getMessage();
	} catch ( RuntimeException $denied ) {
		return $denied->getMessage();
	}
	return '';
};

// 1. E-mail for every red reason not announced yet, never twice for the same one.
l5_reset();
$GLOBALS['wp_test_options']['famma_capi_heartbeat'] = $now - 37 * 3600;
$state = Capi_Health::evaluate( $now );
l5_check( 'rouge' === $state['statut'] && 1 === $mails() && str_contains( $GLOBALS['wp_test_mail'][0][2], 'est passée au rouge' ), 'Switch to red: one e-mail, as before.' );
$GLOBALS['wp_test_options']['famma_capi_health_signals'] = array( 'access' => array( 'code' => 190 ) );
$state = Capi_Health::evaluate( $now + 600 );
l5_check( in_array( 'access', $codes( $state ), true ) && 1 === $mails(), 'A new red reason within 24 h of the last e-mail waits for the limit.' );
$state = Capi_Health::evaluate( $now + 86400 + 60 );
l5_check( 'rouge' === $state['statut'] && 2 === $mails(), 'R-CACHE-2: the state never left red, yet the new red reason is announced once the limit allows (before: never).' );
$body = $GLOBALS['wp_test_mail'][1][2];
l5_check( str_contains( $body, 'déjà en alerte rouge, signale une nouvelle panne' ) && str_contains( $body, 'Meta refuse le jeton' ) && ! str_contains( $body, 'QA-L5-FAKE-TOKEN' ), 'The e-mail says it is a new failure on top of a red state, lists the reasons, never the token.' );
Capi_Health::evaluate( $now + 3 * 86400 );
l5_check( 2 === $mails(), 'Same red reasons later: no repeated e-mail.' );
$GLOBALS['wp_test_options']['famma_capi_health_signals'] = array();
Capi_Health::evaluate( $now + 4 * 86400 );
$GLOBALS['wp_test_options']['famma_capi_health_signals'] = array( 'access' => array( 'code' => 190 ) );
Capi_Health::evaluate( $now + 4 * 86400 + 60 );
l5_check( 3 === $mails(), 'A red reason that went away and came back is announced again.' );
$announced = (array) $GLOBALS['wp_test_options']['famma_capi_health_mail_codes'];
sort( $announced );
l5_check( array( 'access', 'heartbeat' ) === $announced, 'The announced red codes are kept in famma_capi_health_mail_codes.' );

// 2. The finding's scenario: a missing Purchase keeps the state red, then Meta revokes the token.
l5_reset();
$GLOBALS['wc_test_order_query'] = array( $order( 6300, 25 * 3600 ) );
$state                          = Capi_Health::evaluate( $now );
l5_check( array( 'purchase_missing' ) === $codes( $state ) && 1 === $mails(), 'Delivered order without Purchase for 24 h: red, one e-mail.' );
$GLOBALS['wp_test_options']['famma_capi_health_signals'] = array( 'access' => array( 'code' => 190 ) );
$state = Capi_Health::evaluate( $now + 2 * 86400 );
l5_check( in_array( 'access', $codes( $state ), true ) && 2 === $mails() && str_contains( $GLOBALS['wp_test_mail'][1][2], 'Meta refuse le jeton' ), 'R-CACHE-2: token revoked while already red → e-mail (the former rule sent none).' );

// 3. Missing Purchases sorted by what can still be done (bounds of « Renvoyer », Capi_Reconcile).
l5_reset();
$never         = $order( 6400, 7 * 86400 );
$old_attempt   = $order( 6401, 8 * 86400, array( '_famma_purchase_event_time' => $now - 7 * 86400 ) );
$fresh_reject  = $order( 6402, 3 * 86400 );
$old_reject    = $order( 6403, 8 * 86400 );
$old_uncertain = $order( 6404, 3 * 86400 );
$registry_ok   = $order( 6405, 3 * 86400 );

$acknowledged = $order(
	6406,
	8 * 86400,
	array(
		'_famma_purchase_event_time' => $now - 8 * 86400,
		Capi_Purchase_Gaps::ACK_META => (string) ( $now - 3600 ),
	)
);

$GLOBALS['famma_test_registry_orders'] = array(
	6402 => array(
		'Purchase' => array(
			'state'      => 'rejected',
			'event_time' => $now - 3 * 86400,
			'updated_at' => $updated( 2 * 86400 ),
		),
	),
	6403 => array(
		'Purchase' => array(
			'state'      => 'rejected',
			'event_time' => $now - 8 * 86400,
			'updated_at' => $updated( 8 * 86400 ),
		),
	),
	6404 => array(
		'Purchase' => array(
			'state'      => 'uncertain',
			'event_time' => $now - 3 * 86400,
			'updated_at' => $updated( 2 * 86400 ),
		),
	),
	6405 => array(
		'Purchase' => array(
			'state'      => 'accepted',
			'event_time' => $now - 3 * 86400,
			'updated_at' => $updated( 3 * 86400 ),
		),
	),
);
$GLOBALS['wc_test_order_query']        = array( $never, $old_attempt, $fresh_reject, $old_reject, $old_uncertain, $registry_ok, $acknowledged );
$gaps                                  = Capi_Purchase_Gaps::count( $now );
l5_check(
	array(
		'missing' => 2,
		'lost'    => 3,
	) === $gaps,
	'Still resendable (never sent; rejected 2 days ago) → missing: 2. Event over 6 days old, old rejection, uncertain over 24 h → lost: 3. Accepted in the registry or acknowledged → not counted.'
);
l5_check( 2 === Capi_Health_Rules::missing_purchases( $now ), 'missing_purchases() counts only the resendable ones.' );
$reconcile = Capi_Reconcile::instance();
l5_check( $reconcile->cell( $never, 'Purchase', array(), $now )['resend'] && ! $reconcile->cell( $old_attempt, 'Purchase', array(), $now )['resend'] && ! $reconcile->cell( $old_reject, 'Purchase', $GLOBALS['famma_test_registry_orders'][6403]['Purchase'], $now )['resend'], 'Same rule as the « Renvoyer » button of the reconciliation.' );
$state = Capi_Health::evaluate( $now );
l5_check(
	in_array(
		array(
			'code'   => 'purchase_missing',
			'niveau' => 'rouge',
			'detail' => '2',
		),
		$state['raisons'],
		true
	) && in_array(
		array(
			'code'   => 'purchase_lost',
			'niveau' => 'orange',
			'detail' => '3',
		),
		$state['raisons'],
		true
	),
	'Health: purchase_missing red (2), purchase_lost orange (3).'
);
l5_reset();
$GLOBALS['wc_test_order_query'] = array( $old_attempt, $old_reject, $old_uncertain );
$state                          = Capi_Health::evaluate( $now );
l5_check( 'orange' === $state['statut'] && array( 'purchase_lost' ) === $codes( $state ) && 0 === $mails(), 'R-CACHE-2: with no remedy left, the state is orange, not red for 14 days; no e-mail.' );
l5_check( str_contains( Capi_Health_Rules::label( $reason( $state, 'purchase_lost' ) ), 'Acquitter l’alerte' ), 'The orange reason says what to do: reconciliation, then « Acquitter l’alerte ».' );

// 4. « Acquitter l'alerte »: nonce, manage_woocommerce, an expected Purchase not accepted, once.
ob_start();
Capi_Purchase_Gaps::render_ack( $old_attempt );
$form = (string) ob_get_clean();
l5_check( str_contains( $form, 'value="' . Capi_Purchase_Gaps::ACK . '"' ) && str_contains( $form, 'nonce-' . Capi_Purchase_Gaps::ACK ) && str_contains( $form, 'name="order_id" value="6401"' ), 'The reconciliation shows « Acquitter l’alerte » with its nonce and the order.' );
$_POST                   = array( 'order_id' => '6401' );
$GLOBALS['wp_test_caps'] = array( 'manage_woocommerce' => false );
l5_check( str_starts_with( $handle(), 'wp_die' ) && ! Capi_Purchase_Gaps::acknowledged( $old_attempt ), 'Without manage_woocommerce the acknowledgement is refused.' );
$GLOBALS['wp_test_caps'] = array();
$location                = $handle();
l5_check( str_contains( $location, 'famma_capi_ack=acknowledged' ) && Capi_Purchase_Gaps::acknowledged( $old_attempt ) && str_contains( (string) end( $old_attempt->notes ), 'acquittée' ), 'Acknowledged: order meta set, order note added, back to the reconciliation.' );
l5_check( 2 === Capi_Purchase_Gaps::count( time() )['lost'] && 2 === (int) $reason( $GLOBALS['wp_test_options']['famma_capi_health'], 'purchase_lost' )['detail'], 'The acknowledged order no longer counts; the health state is recalculated at once.' );
l5_check( str_contains( $handle(), 'famma_capi_ack=refused' ), 'A second acknowledgement is refused.' );
$sent    = $order( 6407, 3 * 86400, array( '_famma_purchase_sent' => 'yes' ) );
$_POST   = array( 'order_id' => '6407' );
$refused = $handle();
l5_check( str_contains( $refused, 'famma_capi_ack=refused' ) && ! Capi_Purchase_Gaps::acknowledged( $sent ), 'An order whose Purchase was accepted cannot be acknowledged.' );
ob_start();
Capi_Purchase_Gaps::render_ack( $old_attempt );
$done = (string) ob_get_clean();
l5_check( str_contains( $done, 'alerte acquittée' ) && ! str_contains( $done, '<form' ), 'An acknowledged order shows « alerte acquittée », no button.' );
l5_reset();
$red_one                        = $order( 6408, 2 * 86400 );
$GLOBALS['wc_test_order_query'] = array( $red_one );
l5_check( 'rouge' === Capi_Health::evaluate( $now )['statut'], 'A resendable missing Purchase is red.' );
$_POST = array( 'order_id' => '6408' );
$handle();
l5_check( 'vert' === $GLOBALS['wp_test_options']['famma_capi_health']['statut'], 'Acknowledging it switches the red off: the owner can silence an alert he knows about.' );
l5_check( isset( $GLOBALS['wp_test_hooks'][ 'admin_post_' . Capi_Purchase_Gaps::ACK ] ) && ! isset( $GLOBALS['wp_test_hooks'][ 'admin_post_nopriv_' . Capi_Purchase_Gaps::ACK ] ), 'The acknowledgement is an admin-post action, never nopriv.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
