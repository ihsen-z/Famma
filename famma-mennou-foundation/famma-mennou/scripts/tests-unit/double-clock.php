<?php
/**
 * Injectable clock for the code of the Famma\Core namespace.
 * The plugin calls time() unqualified, so this namespaced function is used
 * instead of PHP's. Without `$GLOBALS['famma_test_now']` it is the real clock
 * (relative fixtures); a test that needs fixed historical fixtures sets it to
 * freeze the plugin clock without weakening any assertion.
 * Loaded by scripts/tests-unit/bootstrap.php; never loaded by WordPress.
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- The double keeps the name of the PHP function it replaces, inside the Famma\Core namespace.

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Current Unix time, or the frozen test time.
 *
 * @return int Unix timestamp.
 */
function time(): int {
	return isset( $GLOBALS['famma_test_now'] ) ? (int) $GLOBALS['famma_test_now'] : \time();
}
