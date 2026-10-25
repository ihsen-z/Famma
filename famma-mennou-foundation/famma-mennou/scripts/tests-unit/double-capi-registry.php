<?php
/**
 * In-memory double of Capi_Delivery_Registry (includes/capi-delivery-registry.php).
 * Same public signatures; no database. Required after scripts/tests-unit/bootstrap.php.
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Generic.Classes.DuplicateClassName -- The double keeps the name and signatures of the class it replaces.
// phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Documented by the class it replaces.

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Default mode: grants ownership the way an empty table does (attempt =
 * recorded attempts + 1, blocked beyond the maximum).
 * `$GLOBALS['famma_test_registry_finish']` set to false simulates an
 * acknowledgement the registry could not persist.
 *
 * Stateful mode (`$GLOBALS['famma_test_registry_stateful']` true): rows live in
 * `$GLOBALS['famma_test_registry']` and follow the real ownership rules of
 * lot 5 — a rejection may be retried, an uncertain send only by a bounded
 * retry, an accepted send never; `$GLOBALS['famma_test_registry_unavailable']`
 * simulates a missing table.
 */
final class Capi_Delivery_Registry {
	private static ?self $i = null;
	public static function instance(): self {
		self::$i ??= new self();
		return self::$i;
	}
	public function acquire( string $pixel, string $name, string $id, int $order_id, int $time, int $maximum, int $legacy = 0, bool $allow_uncertain = false ): array {
		$GLOBALS['famma_test_registry_acquire'][] = array( $name, $id, $allow_uncertain );
		if ( ! empty( $GLOBALS['famma_test_registry_unavailable'] ) ) {
			return array( 'state' => 'unavailable' );
		}
		$attempt = $legacy + 1;
		$key     = hash( 'sha256', $pixel . '|' . $name . '|' . $id );
		if ( $time <= 0 || $attempt > $maximum ) {
			return array( 'state' => 'blocked' );
		}
		if ( empty( $GLOBALS['famma_test_registry_stateful'] ) ) {
			return $this->claim( $key, $time, $attempt );
		}
		$row = $GLOBALS['famma_test_registry'][ $key ] ?? null;
		if ( null === $row ) {
			$GLOBALS['famma_test_registry'][ $key ] = array(
				'state'         => 'sending',
				'attempt_count' => $attempt,
				'event_time'    => $time,
				'order_id'      => $order_id,
			);
			return $this->claim( $key, $time, $attempt );
		}
		$retriable = 'rejected' === $row['state'] || ( $allow_uncertain && 'uncertain' === $row['state'] );
		if ( $retriable && $row['attempt_count'] < $maximum ) {
			$GLOBALS['famma_test_registry'][ $key ]['state'] = 'sending';
			++$GLOBALS['famma_test_registry'][ $key ]['attempt_count'];
			return $this->claim( $key, (int) $row['event_time'], (int) $GLOBALS['famma_test_registry'][ $key ]['attempt_count'] );
		}
		return array(
			'key'        => $key,
			'state'      => $row['state'],
			'event_time' => $row['event_time'],
			'attempt'    => $row['attempt_count'],
			'owned'      => false,
		);
	}
	private function claim( string $key, int $time, int $attempt ): array {
		return array(
			'key'        => $key,
			'state'      => 'sending',
			'owner'      => 'unit-owner',
			'event_time' => $time,
			'attempt'    => $attempt,
			'owned'      => true,
		);
	}
	public function finish( array $claim, string $state, int $http, int $received, string $response_class = '' ): bool {
		$GLOBALS['famma_test_registry_states'][] = $state;
		if ( 'accepted' === $state && false === ( $GLOBALS['famma_test_registry_finish'] ?? true ) ) {
			return false;
		}
		if ( ! empty( $claim['owned'] ) && isset( $GLOBALS['famma_test_registry'][ $claim['key'] ] ) ) {
			$GLOBALS['famma_test_registry'][ $claim['key'] ]['state'] = $state;
		}
		return ! empty( $claim['owned'] ) && in_array( $state, array( 'accepted', 'rejected', 'uncertain' ), true );
	}
	public function stale_sending( int $seconds ): array {
		// Rescue sweep: rows of $GLOBALS['famma_test_registry_stale'] (keyed by event_key), selected
		// like the real query: still "sending", older than the delay, oldest first, 20 at most.
		$limit = gmdate( 'Y-m-d H:i:s', time() - $seconds );
		$rows  = array_filter(
			$GLOBALS['famma_test_registry_stale'] ?? array(),
			static fn( array $row ): bool => 'sending' === $row['state'] && $row['updated_at'] < $limit
		);
		uasort( $rows, static fn( array $a, array $b ): int => strcmp( $a['updated_at'], $b['updated_at'] ) );
		return array_slice( array_values( $rows ), 0, 20 );
	}
	public function release_stale( array $row ): bool {
		$key = (string) ( $row['event_key'] ?? '' );
		if ( 'sending' !== ( $GLOBALS['famma_test_registry_stale'][ $key ]['state'] ?? '' ) ) {
			return false;
		}
		$GLOBALS['famma_test_registry_stale'][ $key ]['state'] = 'uncertain';
		return true;
	}
	public function for_orders( array $order_ids ): array {
		// Fixture `$GLOBALS['famma_test_registry_orders']`: order_id => event_name => record, as the real query returns.
		return array_intersect_key( $GLOBALS['famma_test_registry_orders'] ?? array(), array_flip( $order_ids ) );
	}
}
