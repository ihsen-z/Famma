<?php
/**
 * Regression test for the report status filters (fatal error of 13/10/2026).
 * Read-only: calls the filters, writes nothing.
 * Run: wp eval-file scripts/test-report-statuses.php
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The suite replays WooCommerce's own filters.
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'WP-CLI required.' );
}
if ( ! class_exists( '\Famma\Core\Order_Statuses' ) ) {
	throw new RuntimeException( 'Active FAMMA Core required.' );
}

$famma_rs_failures = 0;
$famma_rs_assert   = static function ( string $label, mixed $expected, mixed $actual ) use ( &$famma_rs_failures ): void {
	$ok = ( $expected === $actual );
	if ( ! $ok ) {
		++$famma_rs_failures;
	}
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : ' — expected ' . wp_json_encode( $expected ) . ', got ' . wp_json_encode( $actual ) ) );
};

// "Sales by date" refund queries pass false: no status filter, must stay false.
$famma_rs_assert( 'reports filter keeps false', false, apply_filters( 'woocommerce_reports_order_statuses', false ) );

// Regular report queries: processing / on-hold out, delivered / completed in.
$famma_rs_statuses = apply_filters( 'woocommerce_reports_order_statuses', array( 'completed', 'processing', 'on-hold' ) );
sort( $famma_rs_statuses );
$famma_rs_assert( 'reports filter on array', array( 'completed', 'delivered' ), $famma_rs_statuses );

$famma_rs_statuses = apply_filters( 'woocommerce_reports_order_statuses', array( 'refunded' ) );
sort( $famma_rs_statuses );
$famma_rs_assert( 'reports filter keeps other statuses', array( 'completed', 'delivered', 'refunded' ), $famma_rs_statuses );

// Paid statuses: delivered counts as paid, processing does not.
$famma_rs_paid = wc_get_is_paid_statuses();
$famma_rs_assert( 'delivered is paid', true, in_array( 'delivered', $famma_rs_paid, true ) );
$famma_rs_assert( 'processing is not paid', false, in_array( 'processing', $famma_rs_paid, true ) );
$famma_rs_assert( 'paid filter keeps false', false, apply_filters( 'woocommerce_order_is_paid_statuses', false ) );

if ( $famma_rs_failures > 0 ) {
	WP_CLI::error( $famma_rs_failures . ' assertion(s) failed.' );
}
WP_CLI::success( 'Report status filters OK.' );
