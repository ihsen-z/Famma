<?php
/**
 * Double of usleep() for Famma\Core\Checkout_Idempotency (includes/checkout-idempotency.php).
 * The class calls usleep() unqualified, so this namespaced function is used
 * instead of PHP's: the bounded wait is measured without really sleeping.
 * Required before the class's first call, by scripts/tests-unit/test-checkout-idempotency.php.
 *
 * `$GLOBALS['t_slept']` adds up the requested microseconds; the callable in
 * `$GLOBALS['t_on_sleep']`, when set, runs at each step to play the
 * concurrent request (release its lock, create its order).
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- The double keeps the name of the PHP function it replaces, inside the Famma\Core namespace.

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Records the pause instead of sleeping.
 *
 * @param int $microseconds Requested pause.
 */
function usleep( int $microseconds ): void {
	$GLOBALS['t_slept'] = (int) ( $GLOBALS['t_slept'] ?? 0 ) + $microseconds;
	if ( isset( $GLOBALS['t_on_sleep'] ) && is_callable( $GLOBALS['t_on_sleep'] ) ) {
		( $GLOBALS['t_on_sleep'] )();
	}
}
