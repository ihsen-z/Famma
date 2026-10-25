<?php
/**
 * Diagnostic replay of an isolated test script with a non-blocking check().
 * Run with: php scripts/qa/run-check-non-blocking.php scripts/test-pixel-scenarios.php
 * The official result stays the blocking run of the script itself: this replay only
 * lists the contracts that follow the first failure. The script file is never modified;
 * a transformed copy is executed from the system temporary directory, then deleted.
 *
 * @package Famma_Test_Scenarios
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$target = realpath( (string) ( $argv[1] ?? '' ) );
$root   = dirname( __DIR__, 2 );
if ( false === $target || ! str_starts_with( $target, $root . DIRECTORY_SEPARATOR . 'scripts' ) ) {
	fwrite( STDERR, 'Usage: php scripts/qa/run-check-non-blocking.php scripts/<test>.php' . PHP_EOL );
	exit( 2 );
}

$source  = (string) file_get_contents( $target );
$throw   = 'throw new \RuntimeException( $message );';
$replace = '$GLOBALS[\'diagnostic_failures\'][] = $message; echo \'FAIL: \' . $message . PHP_EOL; return;';
if ( 1 !== substr_count( $source, $throw ) ) {
	fwrite( STDERR, 'check() boundary not found exactly once.' . PHP_EOL );
	exit( 2 );
}
$copy = str_replace( $throw, $replace, $source );
// PHP string literal of a path, so the copy keeps reading the real plugin file.
$php_literal = static function ( string $value ): string {
	return "'" . addcslashes( $value, "'\\" ) . "'";
};
$copy        = str_replace( 'dirname( __DIR__ )', $php_literal( dirname( $target, 2 ) ), $copy );
$copy        = str_replace( "define( 'ABSPATH', __DIR__ );", "define( 'ABSPATH', " . $php_literal( dirname( $target ) ) . ' );', $copy );

$replay = sys_get_temp_dir() . '/famma-diagnostic-' . getmypid() . '.php';
file_put_contents( $replay, $copy );
register_shutdown_function(
	static function () use ( $replay ): void {
		$failures = $GLOBALS['diagnostic_failures'] ?? array();
		echo 'DIAGNOSTIC (non bloquant) : ' . count( $failures ) . ' contrat(s) en échec' . PHP_EOL;
		foreach ( $failures as $failure ) {
			echo '  - ' . $failure . PHP_EOL;
		}
		unlink( $replay );
	}
);
require $replay;
