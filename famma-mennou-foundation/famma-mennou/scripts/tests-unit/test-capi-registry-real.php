<?php
/**
 * Lot 5 of the pixel reforms — the REAL Capi_Delivery_Registry (anti-duplicate ledger), not its
 * in-memory double. A small table emulator stands behind $wpdb: primary key on event_key,
 * INSERT IGNORE returning 0 on a duplicate, UPDATE returning the number of rows changed. If the
 * class stops relying on the insert-or-ignore + compare-and-swap contract, a duplicate send
 * becomes possible and this suite fails.
 * Run with: php scripts/tests-unit/test-capi-registry-real.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded; only bootstrap.php (not bootstrap-capi.php, which doubles the registry).
require __DIR__ . '/bootstrap.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Generic.Classes.DuplicateClassName, Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing, Generic.CodeAnalysis.UnusedFunctionParameter -- Table emulator named after the WordPress API it replaces.

/**
 * Minimal $wpdb: one table whose primary key is event_key.
 */
final class Famma_Registry_Wpdb {
	public string $prefix = 'wp_';
	/**
	 * Rows by event_key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $rows           = array();
	public bool $has_primary_key = true;
	/**
	 * Prepared SQL => format and arguments.
	 *
	 * @var array<string, array{0: string, 1: array}>
	 */
	private array $prepared = array();

	public function esc_like( string $text ): string {
		return $text;
	}
	public function get_charset_collate(): string {
		return '';
	}
	public function get_var( $query ) {
		return null;
	}
	public function prepare( string $format, ...$args ): string {
		$flat                   = count( $args ) === 1 && is_array( $args[0] ) ? $args[0] : $args;
		$i                      = 0;
		$sql                    = (string) preg_replace_callback(
			'/%([isd])/',
			static function ( array $m ) use ( &$i, $flat ): string {
				$value = $flat[ $i++ ];
				return 'i' === $m[1] ? '`' . $value . '`' : ( 'd' === $m[1] ? (string) (int) $value : "'" . addslashes( (string) $value ) . "'" );
			},
			$format
		);
		$this->prepared[ $sql ] = array( $format, $flat );
		return $sql;
	}
	public function get_results( $sql, $output = null ): array {
		if ( str_starts_with( $sql, 'SHOW INDEX' ) ) {
			return $this->has_primary_key ? array(
				array(
					'Column_name' => 'event_key',
					'Non_unique'  => 0,
				),
			) : array();
		}
		return array();
	}
	public function get_row( $sql, $output = null ) {
		[ , $args ] = $this->prepared[ $sql ];
		return $this->rows[ (string) $args[1] ] ?? null;
	}
	public function query( $sql ) {
		[ $format, $args ] = $this->prepared[ $sql ];
		if ( str_starts_with( $format, 'INSERT IGNORE INTO' ) || str_starts_with( $format, 'INSERT INTO' ) ) {
			preg_match( '/\(([a-z_,]+)\) VALUES/', $format, $cols );
			$row = array_combine( explode( ',', $cols[1] ), array_slice( $args, 1 ) );
			if ( isset( $this->rows[ $row['event_key'] ] ) ) {
				return str_starts_with( $format, 'INSERT IGNORE' ) ? 0 : false; // A plain INSERT on a duplicate key is a database error.
			}
			$this->rows[ $row['event_key'] ] = $row;
			return 1;
		}
		if ( str_starts_with( $format, 'UPDATE' ) ) {
			preg_match( '/SET (.+) WHERE (.+)$/', $format, $parts );
			$set   = array_map( static fn( string $a ): string => explode( '=', $a )[0], explode( ',', $parts[1] ) );
			$where = array_map( static fn( string $a ): string => explode( '=', $a )[0], explode( ' AND ', $parts[2] ) );
			$vals  = array_slice( $args, 1 );
			$new   = array_combine( $set, array_slice( $vals, 0, count( $set ) ) );
			$cond  = array_combine( $where, array_slice( $vals, count( $set ) ) );
			$row   = $this->rows[ (string) $cond['event_key'] ] ?? null;
			if ( null === $row ) {
				return 0;
			}
			foreach ( $cond as $column => $expected ) {
				if ( (string) $row[ $column ] !== (string) $expected ) {
					return 0;
				}
			}
			$this->rows[ (string) $cond['event_key'] ] = $new + $row;
			return 1;
		}
		return false;
	}
}
// phpcs:enable

define( 'ARRAY_A', 'ARRAY_A' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WordPress constant.

Famma_Unit_Harness::load_core_class( 'Config', 'Order_Statuses' );
Famma_Unit_Harness::load_include( 'capi-delivery-registry.php' );

use Famma\Core\Capi_Delivery_Registry;
use Famma\Core\Config;

/**
 * Checks one contract of the registry.
 *
 * @param bool   $condition Contract outcome.
 * @param string $message   Contract description.
 * @throws RuntimeException When the contract does not hold.
 */
function unit_registry_check( bool $condition, string $message ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Test helper of a standalone runner.
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	echo 'PASS: ' . $message . PHP_EOL;
	$GLOBALS['reg_checks'] = ( $GLOBALS['reg_checks'] ?? 0 ) + 1;
}

$wpdb            = new Famma_Registry_Wpdb(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test double of the database object.
$GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test double of the database object.
$registry        = Capi_Delivery_Registry::instance();
$now             = time();
$max             = Config::CAPI_MAX_ATTEMPTS;
$key             = hash( 'sha256', '000000000000001|OrderPlaced|famma_orderplaced_9001' );

// First claim: this execution owns the send.
$a = $registry->acquire( '000000000000001', 'OrderPlaced', 'famma_orderplaced_9001', 9001, $now, $max );
unit_registry_check( true === $a['owned'] && 'sending' === $a['state'] && 1 === $a['attempt'] && $key === $a['key'], 'First claim: owned, state sending, attempt 1, key = sha256(pixel|event|id).' );
unit_registry_check( 1 === count( $wpdb->rows ) && 'famma_orderplaced_9001' === $wpdb->rows[ $key ]['event_id'] && 9001 === (int) $wpdb->rows[ $key ]['order_id'], 'The row is written once, with the immutable event_id and order.' );

// Concurrent second claim of the same event: refused.
$b = $registry->acquire( '000000000000001', 'OrderPlaced', 'famma_orderplaced_9001', 9001, $now, $max );
unit_registry_check( false === $b['owned'] && 'sending' === $b['state'], 'Second concurrent claim of the same event: not owned, nothing sent twice.' );
unit_registry_check( 1 === count( $wpdb->rows ) && 1 === (int) $wpdb->rows[ $key ]['attempt_count'], 'The duplicate claim neither adds a row nor spends an attempt.' );

// Only the owner can close the claim.
$forged = array(
	'owned' => true,
	'key'   => $key,
	'owner' => 'someone-else',
) + $a;
unit_registry_check( false === $registry->finish( $forged, 'accepted', 200, 1 ) && 'sending' === $wpdb->rows[ $key ]['state'], 'A claim with the wrong owner cannot close the send.' );
unit_registry_check( false === $registry->finish( array( 'owned' => false ) + $b, 'accepted', 200, 1 ), 'A claim that was never owned cannot close the send.' );
unit_registry_check( false === $registry->finish( $a, 'bogus-state', 200, 1 ), 'An unknown final state is refused.' );
unit_registry_check( true === $registry->finish( $a, 'accepted', 200, 1 ) && 'accepted' === $wpdb->rows[ $key ]['state'], 'The owner closes the send: accepted.' );

// Accepted is final: never claimable again, even for a bounded retry.
$c = $registry->acquire( '000000000000001', 'OrderPlaced', 'famma_orderplaced_9001', 9001, $now, $max, 0, true );
unit_registry_check( false === $c['owned'] && 'accepted' === $c['state'], 'An accepted event is never claimable again (duplicate Purchase/OrderPlaced impossible), even with allow_uncertain.' );
unit_registry_check( 1 === (int) $wpdb->rows[ $key ]['attempt_count'], 'An accepted event keeps its attempt count.' );

// A different event name or id is another event.
$other_name = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9001', 9001, $now, $max );
$other_id   = $registry->acquire( '000000000000001', 'OrderPlaced', 'famma_orderplaced_9002', 9002, $now, $max );
$other_pix  = $registry->acquire( '000000000000002', 'OrderPlaced', 'famma_orderplaced_9001', 9001, $now, $max );
unit_registry_check( $other_name['owned'] && $other_id['owned'] && $other_pix['owned'] && 4 === count( $wpdb->rows ), 'Another event name, id or pixel is an independent event with its own row.' );

// Rejected: the explicit retry is allowed until the attempt ceiling, then it stops.
$r1 = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9100', 9100, $now, $max );
$registry->finish( $r1, 'rejected', 400, 0 );
$r2 = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9100', 9100, $now, $max );
unit_registry_check( true === $r2['owned'] && 2 === $r2['attempt'], 'A definite rejection may be retried: owned, attempt 2.' );
for ( $n = 2; $n < $max; $n++ ) {
	$registry->finish( $r2, 'rejected', 400, 0 );
	$r2 = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9100', 9100, $now, $max );
}
$registry->finish( $r2, 'rejected', 400, 0 );
$last = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9100', 9100, $now, $max );
unit_registry_check( false === $last['owned'] && 'rejected' === $last['state'] && $max === (int) $wpdb->rows[ hash( 'sha256', '000000000000001|Purchase|famma_purchase_9100' ) ]['attempt_count'], 'After the maximum number of attempts the event is no longer claimable.' );

// Uncertain: only a bounded retry (allow_uncertain) may claim it.
$u1 = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9200', 9200, $now, $max );
$registry->finish( $u1, 'uncertain', 0, 0 );
$u2 = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9200', 9200, $now, $max );
unit_registry_check( false === $u2['owned'] && 'uncertain' === $u2['state'], 'An uncertain send is not re-sent by a plain claim (it may have reached Meta).' );
$u3 = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9200', 9200, $now, $max, 0, true );
unit_registry_check( true === $u3['owned'] && 2 === $u3['attempt'], 'A bounded retry may claim an uncertain send: owned, attempt 2.' );

// A claim stuck in « sending » is only taken over after the stale delay.
$s1                        = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9300', 9300, $now, $max );
$GLOBALS['famma_test_now'] = time() + Config::CAPI_STALE_SENDING - 60;
$s2                        = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9300', 9300, $now, $max, 0, true );
unit_registry_check( false === $s2['owned'] && 'sending' === $s2['state'], 'A send still fresh (under the stale delay) keeps its owner.' );
$GLOBALS['famma_test_now'] = time() + Config::CAPI_STALE_SENDING + 60;
$s3                        = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9300', 9300, $now, $max );
unit_registry_check( false === $s3['owned'] && 'uncertain' === $s3['state'], 'An abandoned send becomes uncertain after the stale delay, and a plain claim still does not re-send it.' );
unset( $GLOBALS['famma_test_now'] );

// Guards: no event time, or attempts exhausted, never yields a claim; a missing primary key fails closed.
$zero = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9400', 9400, 0, $max );
unit_registry_check( 'blocked' === $zero['state'] && ! isset( $wpdb->rows[ hash( 'sha256', '000000000000001|Purchase|famma_purchase_9400' ) ] ), 'Without an event time: blocked, no row written.' );
$over = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9401', 9401, $now, $max, $max );
unit_registry_check( 'blocked' === $over['state'], 'Legacy attempts already at the ceiling: blocked.' );
$wpdb->has_primary_key = false;
$closed                = $registry->acquire( '000000000000001', 'Purchase', 'famma_purchase_9402', 9402, $now, $max );
unit_registry_check( 'unavailable' === $closed['state'] && empty( $closed['owned'] ), 'No primary key on event_key: the registry fails closed (unavailable), nothing is sent.' );

echo 'CHECKS=' . ( $GLOBALS['reg_checks'] ?? 0 ) . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
