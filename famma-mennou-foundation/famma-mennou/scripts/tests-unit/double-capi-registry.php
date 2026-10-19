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
 * Grants ownership the way an empty table does: attempt = recorded attempts + 1,
 * blocked beyond the maximum. `$GLOBALS['famma_test_registry_finish']` set to
 * false simulates an acknowledgement the registry could not persist.
 */
final class Capi_Delivery_Registry {
	private static ?self $i = null;
	public static function instance(): self {
		self::$i ??= new self();
		return self::$i;
	}
	public function acquire( string $pixel, string $name, string $id, int $order_id, int $time, int $maximum, int $legacy = 0 ): array {
		$attempt = $legacy + 1;
		if ( $time <= 0 || $attempt > $maximum ) {
			return array( 'state' => 'blocked' );
		}
		return array(
			'key'        => hash( 'sha256', $pixel . '|' . $name . '|' . $id ),
			'state'      => 'sending',
			'owner'      => 'unit-owner',
			'event_time' => $time,
			'attempt'    => $attempt,
			'owned'      => true,
		);
	}
	public function finish( array $claim, string $state, int $http, int $received ): bool {
		$GLOBALS['famma_test_registry_states'][] = $state;
		if ( 'accepted' === $state && false === ( $GLOBALS['famma_test_registry_finish'] ?? true ) ) {
			return false;
		}
		return ! empty( $claim['owned'] ) && in_array( $state, array( 'accepted', 'rejected', 'uncertain' ), true );
	}
}
