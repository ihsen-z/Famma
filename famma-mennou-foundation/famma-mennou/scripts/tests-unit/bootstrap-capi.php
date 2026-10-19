<?php
/**
 * Doubles and real sources of the server CAPI (lot 5), on top of bootstrap.php.
 * Loads the real Config, Order_Statuses, Tunisia and Capi classes and every
 * includes/capi-*.php collaborator; only the delivery registry is doubled
 * (double-capi-registry.php). No database, no network.
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded. The doubles below keep the names
// and signatures of the WordPress, WooCommerce and Action Scheduler code they replace.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.WrongStyle -- Doubles are documented by the API they replace; line comments label groups of doubles.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/double-capi-registry.php';

const MINUTE_IN_SECONDS = 60;
const HOUR_IN_SECONDS   = 3600;
const DAY_IN_SECONDS    = 86400;
const WEEK_IN_SECONDS   = 604800;

function update_option( string $name, $value, $autoload = null ): bool {
	$GLOBALS['wp_test_options'][ $name ] = $value;
	return true;
}
function wp_salt( string $scheme = 'auth' ): string {
	return $GLOBALS['wp_test_salt'] ?? 'unit-test-salt-' . $scheme;
}
function wp_get_environment_type(): string {
	return $GLOBALS['wp_test_environment'] ?? 'production';
}
function wp_remote_retrieve_header( $response, string $header ): string {
	return is_array( $response ) && isset( $response['headers'][ strtolower( $header ) ] ) ? (string) $response['headers'][ strtolower( $header ) ] : '';
}
function wp_remote_get( string $url, array $args = array() ) {
	$GLOBALS['wp_test_http'][] = array(
		'url'    => $url,
		'args'   => $args,
		'method' => 'GET',
	);
	return $GLOBALS['wp_test_http_response'] ?? new WP_Error( 'http_request_failed', 'No fixture' );
}
function get_transient( string $name ) {
	return $GLOBALS['wp_test_transients'][ $name ] ?? false;
}
function set_transient( string $name, $value, int $expiration = 0 ): bool {
	$GLOBALS['wp_test_transients'][ $name ] = $value;
	return true;
}
function wp_mail( $to, string $subject, string $message ): bool {
	$GLOBALS['wp_test_mail'][] = array( $to, $subject, $message );
	return true;
}
function add_settings_error( string $setting, string $code, string $message, string $type = 'error' ): void {
	$GLOBALS['wp_test_settings_errors'][] = array( $setting, $code, $message );
}
function has_filter( string $hook, $callback = false ) {
	if ( empty( $GLOBALS['wp_test_hooks'][ $hook ] ) ) {
		return false;
	}
	if ( false === $callback ) {
		return true;
	}
	foreach ( $GLOBALS['wp_test_hooks'][ $hook ] as $listener ) {
		if ( $callback === $listener[0] ) {
			return 10;
		}
	}
	return false;
}
function sanitize_key( $key ): string {
	return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

// Action Scheduler, in memory, with the statuses of Action Scheduler 4.0.0: a "unique"
// insert and as_has_scheduled_action() both count the pending AND the running
// (in-progress) actions. Each action is array( timestamp, hook, args, group, unique, status ).
function as_schedule_single_action( int $timestamp, string $hook, array $args = array(), string $group = '', bool $unique = false ): int {
	if ( $unique && array() !== l5_as_find( $hook, $args, $group, array( 'pending', 'in-progress' ) ) ) {
		return 0;
	}
	$GLOBALS['as_test_actions'][] = array( $timestamp, $hook, $args, $group, $unique, 'pending' );
	return count( $GLOBALS['as_test_actions'] );
}
function as_has_scheduled_action( string $hook, $args = null, string $group = '' ): bool {
	return array() !== l5_as_find( $hook, $args, $group, array( 'pending', 'in-progress' ) );
}
function as_enqueue_async_action( string $hook, array $args = array(), string $group = '', bool $unique = false ): int {
	return as_schedule_single_action( time(), $hook, $args, $group, $unique );
}
function as_get_scheduled_actions( array $args = array(), string $return_format = 'OBJECT' ): array {
	// The health rule asks for late actions by date: fixture. Any other query reads the in-memory queue.
	if ( isset( $args['date'] ) ) {
		return $GLOBALS['as_test_late_actions'] ?? array();
	}
	return l5_as_find( (string) ( $args['hook'] ?? '' ), $args['args'] ?? null, (string) ( $args['group'] ?? '' ), isset( $args['status'] ) ? (array) $args['status'] : array() );
}

/**
 * Identifiers (1-based) of the in-memory actions matching a query.
 *
 * @param string     $hook     Hook, or '' for any.
 * @param array|null $args     Exact arguments, or null for any.
 * @param string     $group    Group, or '' for any.
 * @param string[]   $statuses Statuses, or empty for any.
 * @return int[]
 */
function l5_as_find( string $hook, $args, string $group, array $statuses ): array {
	$ids = array();
	foreach ( $GLOBALS['as_test_actions'] ?? array() as $index => $action ) {
		if ( ( '' === $hook || $hook === $action[1] ) && ( null === $args || $args === $action[2] ) && ( '' === $group || $group === $action[3] ) && ( array() === $statuses || in_array( $action[5], $statuses, true ) ) ) {
			$ids[] = $index + 1;
		}
	}
	return $ids;
}

/**
 * Runs the first pending in-memory action as Action Scheduler 4.0.0 does: the action
 * is "in-progress" while its callbacks run, then "complete".
 *
 * @return array|null The action that ran, or null when none is pending.
 */
function l5_as_run_next(): ?array {
	foreach ( $GLOBALS['as_test_actions'] ?? array() as $index => $action ) {
		if ( 'pending' !== $action[5] ) {
			continue;
		}
		$GLOBALS['as_test_actions'][ $index ][5] = 'in-progress';
		do_action( $action[1], ...array_values( $action[2] ) );
		$GLOBALS['as_test_actions'][ $index ][5] = 'complete';
		return $action;
	}
	return null;
}
function wc_get_orders( array $args = array() ): array {
	return $GLOBALS['wc_test_order_query'] ?? array();
}

Famma_Unit_Harness::load_core_class( 'Config', 'Order_Statuses' );
Famma_Unit_Harness::load_core_class( 'Order_Statuses', 'Stock' );
Famma_Unit_Harness::load_core_class( 'Tunisia', 'WhatsApp' );
Famma_Unit_Harness::load_core_class( 'Capi', 'Dashboard' );
foreach ( array( 'meta-ids.php', 'capi-signals.php', 'capi-log.php', 'capi-product-data.php', 'capi-token-vault.php', 'capi-transport.php', 'capi-user-data.php', 'capi-data-profile.php', 'capi-retry.php', 'capi-health.php', 'capi-health-rules.php', 'capi-graph-version.php', 'capi-probe.php' ) as $famma_include ) {
	Famma_Unit_Harness::load_include( $famma_include );
}

/**
 * Builds a WordPress HTTP response array.
 *
 * @param int    $code    HTTP status.
 * @param string $body    Body.
 * @param array  $headers Lower-case headers.
 * @return array
 */
function l5_response( int $code, string $body, array $headers = array() ): array {
	return array(
		'response' => array( 'code' => $code ),
		'body'     => $body,
		'headers'  => $headers,
	);
}

/**
 * Stop the run on the first broken contract, otherwise count it.
 *
 * @param bool   $condition Contract outcome.
 * @param string $message   Contract description.
 * @throws RuntimeException When the contract does not hold.
 */
function l5_check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	$GLOBALS['l5_checks'] = ( $GLOBALS['l5_checks'] ?? 0 ) + 1;
	echo 'PASS: ' . $message . PHP_EOL;
}

/**
 * Journal lines written to the famma-capi source.
 *
 * @return string[]
 */
function l5_capi_lines(): array {
	return array_values(
		array_map(
			static fn( array $entry ): string => $entry['message'],
			array_filter( $GLOBALS['wc_test_log'] ?? array(), static fn( array $entry ): bool => 'famma-capi' === $entry['source'] )
		)
	);
}

/**
 * Resets the in-memory state between scenarios.
 *
 * @param array $options Options to start from.
 */
function l5_reset( array $options = array() ): void {
	$GLOBALS['wp_test_options']       = $options + array(
		'famma_meta_pixel_id'            => '000000000000001',
		'famma_meta_access_token'        => 'QA-L5-FAKE-TOKEN-NOT-REAL',
		'famma_meta_orderplaced_enabled' => true,
		'famma_capi_registry_version'    => '1',
	);
	$GLOBALS['wp_test_http']          = array();
	$GLOBALS['wc_test_log']           = array();
	$GLOBALS['as_test_actions']       = array();
	$GLOBALS['wp_test_mail']          = array();
	$GLOBALS['wp_test_transients']    = array();
	$GLOBALS['famma_test_registry']   = array();
	$GLOBALS['wp_test_environment']   = 'production';
	$GLOBALS['wp_test_http_response'] = l5_response( 200, '{"events_received":1,"messages":[],"fbtrace_id":"QAL5"}', array( 'facebook-api-version' => 'v26.0' ) );
}
