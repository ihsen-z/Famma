<?php
/**
 * Single runner of every unit suite that does not need WordPress.
 *
 * Discovery is by file name, never by a fixed list, so a suite added tomorrow
 * (scripts/tests-unit/test-*.php or .js, or scripts/test-*.php or .js) is run
 * without touching this file:
 *   - scripts/tests-unit/test-*.php and test-*.js : always unit suites;
 *   - scripts/test-*.php and test-*.js            : unit suites unless they mention
 *     WP_CLI or `wp eval-file` (integration suites, see scripts/TESTING.md).
 *
 * A suite fails when its exit code is not 0, when it prints nothing (a script
 * that exits before testing anything must not look green), when a line starts
 * with FAIL, when no PASS line was counted (0 PASS is never green), when a `RESULT x/y` line has x != y, or when PHP reports a warning,
 * notice, deprecation or fatal error.
 *
 * Usage:  php scripts/run-unit-tests.php [--php-only] [--js-only] [--list] [--verbose] [filter...]  (filters are OR-ed, matched on the suite path)
 * Quarantine: scripts/tests-quarantine.txt lists suites already red before the reforms (shown, not blocking).
 * Exit:   0 when every suite passes, 1 otherwise (also when no suite is found).
 * Env:    FAMMA_PHP_ARGS  extra arguments for each php child (e.g. "-n -d extension=mbstring").
 *         FAMMA_NODE      node binary (default: node).
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Command-line report, never rendered in a browser.
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- The runner starts one child process per suite.
// phpcs:disable WordPress.WP.AlternativeFunctions -- Plain PHP runner, WordPress is not loaded.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Standalone script, no WordPress namespace to protect.

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

const FAMMA_SUITE_TIMEOUT = 180;

/**
 * Suites found under scripts/.
 *
 * @param string $scripts Absolute path of the scripts directory.
 * @return array<int, array{kind: string, path: string, name: string}>
 */
function unit_discover_suites( string $scripts ): array {
	$found = array();
	foreach ( array( $scripts . '/tests-unit', $scripts ) as $dir ) {
		$always = $dir === $scripts . '/tests-unit';
		foreach ( array( 'php', 'js' ) as $ext ) {
			$files = glob( $dir . '/test-*.' . $ext );
			sort( $files );
			foreach ( $files as $file ) {
				$source = (string) file_get_contents( $file );
				if ( ! $always && 1 === preg_match( '/WP_CLI|wp eval-file/', $source ) ) {
					continue; // Integration suite: needs a WordPress site.
				}
				$found[] = array(
					'kind' => $ext,
					'path' => $file,
					'name' => ltrim( substr( $file, strlen( $scripts ) ), '/\\' ),
				);
			}
		}
	}
	return $found;
}

/**
 * Environment of the children: the php binary running this script is first on PATH (JS suites call `php`).
 *
 * @return array<string, string>
 */
function unit_child_env(): array {
	$env          = getenv();
	$path         = isset( $env['PATH'] ) ? 'PATH' : ( isset( $env['Path'] ) ? 'Path' : 'PATH' );
	$env[ $path ] = dirname( PHP_BINARY ) . PATH_SEPARATOR . ( $env[ $path ] ?? '' );
	// JS suites that need PHP (express places) must use this very binary; FAMMA_PHP_ARGS is inherited as is.
	$env['FAMMA_PHP'] = PHP_BINARY;
	return $env;
}

/**
 * Runs one command with a timeout.
 *
 * @param array<int, string> $command Program and arguments.
 * @return array{code: int, output: string}
 */
function unit_run( array $command ): array {
	$process = proc_open(
		$command,
		array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'redirect', 1 ),
		),
		$pipes,
		null,
		unit_child_env()
	);
	if ( ! is_resource( $process ) ) {
		return array(
			'code'   => 127,
			'output' => 'Unable to start: ' . implode( ' ', $command ),
		);
	}
	stream_set_blocking( $pipes[1], false );
	$output   = '';
	$deadline = time() + FAMMA_SUITE_TIMEOUT;
	while ( true ) {
		$output .= (string) stream_get_contents( $pipes[1] );
		$status  = proc_get_status( $process );
		if ( ! $status['running'] ) {
			break;
		}
		if ( time() > $deadline ) {
			proc_terminate( $process );
			$output .= PHP_EOL . 'FAIL: timeout after ' . FAMMA_SUITE_TIMEOUT . ' s.';
			break;
		}
		usleep( 20000 );
	}
	$output .= (string) stream_get_contents( $pipes[1] );
	fclose( $pipes[1] );
	$code = proc_close( $process );
	// proc_close() returns -1 once proc_get_status() has already reaped the child.
	return array(
		'code'   => -1 === $code && isset( $status['exitcode'] ) && $status['exitcode'] >= 0 ? (int) $status['exitcode'] : $code,
		'output' => $output,
	);
}

/**
 * Reasons why a finished suite is not green.
 *
 * @param int    $code   Exit code.
 * @param string $output Everything the suite printed.
 * @return string[] Empty when the suite passed.
 */
function unit_verdict( int $code, string $output ): array {
	$reasons = array();
	if ( 0 !== $code ) {
		$reasons[] = 'exit code ' . $code;
	}
	if ( '' === trim( $output ) ) {
		$reasons[] = 'no output (nothing was tested)';
	}
	if ( '' !== trim( $output ) && 0 === preg_match_all( '/^(?:PASS|ok)\b/mi', $output ) ) {
		$reasons[] = '0 PASS assertion counted (a suite that proves nothing is a failure, not a green)';
	}
	if ( 1 === preg_match( '/^FAIL\b/m', $output ) || 1 === preg_match( '/\bFAIL(?:ED)?:/', $output ) ) {
		$reasons[] = 'a FAIL line was printed';
	}
	if ( preg_match_all( '/RESULT\s+(\d+)\s*\/\s*(\d+)/', $output, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $line ) {
			if ( $line[1] !== $line[2] ) {
				$reasons[] = 'RESULT ' . $line[1] . '/' . $line[2];
			}
		}
	}
	if ( 1 === preg_match( '/^(?:PHP )?(?:Warning|Notice|Deprecated|Fatal error|Parse error):/m', $output ) ) {
		$reasons[] = 'PHP warning, notice, deprecation or fatal error';
	}
	return $reasons;
}

$args      = array_slice( $argv, 1 );
$php_only  = in_array( '--php-only', $args, true );
$js_only   = in_array( '--js-only', $args, true );
$list_only = in_array( '--list', $args, true );
$verbose   = in_array( '--verbose', $args, true );
$filters   = array_values( array_filter( $args, static fn( string $a ): bool => ! str_starts_with( $a, '--' ) ) );
$php_args  = '' !== (string) getenv( 'FAMMA_PHP_ARGS' ) ? preg_split( '/\s+/', trim( (string) getenv( 'FAMMA_PHP_ARGS' ) ) ) : array();
$node      = '' !== (string) getenv( 'FAMMA_NODE' ) ? (string) getenv( 'FAMMA_NODE' ) : 'node';
$scripts   = str_replace( '\\', '/', __DIR__ );
$suites    = unit_discover_suites( $scripts );
$suites    = array_values(
	array_filter(
		$suites,
		static function ( array $suite ) use ( $php_only, $js_only, $filters ): bool {
			if ( ( $php_only && 'php' !== $suite['kind'] ) || ( $js_only && 'js' !== $suite['kind'] ) ) {
				return false;
			}
			foreach ( $filters as $filter ) {
				if ( str_contains( $suite['name'], $filter ) ) {
					return true;
				}
			}
			return array() === $filters;
		}
	)
);

$quarantine = array();
foreach ( (array) file( __DIR__ . '/tests-quarantine.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $entry ) {
	if ( ! str_starts_with( ltrim( $entry ), '#' ) && str_contains( $entry, '|' ) ) {
		[ $quarantined, $why ]      = array_map( 'trim', explode( '|', $entry, 2 ) );
		$quarantine[ $quarantined ] = $why;
	}
}

if ( array() === $suites ) {
	echo 'ERROR: no unit suite found (a runner that finds nothing must not report success).' . PHP_EOL;
	exit( 1 );
}
if ( $list_only ) {
	foreach ( $suites as $suite ) {
		echo $suite['name'] . PHP_EOL;
	}
	exit( 0 );
}

// The children must have what the plugin code needs: fail loudly instead of a confusing fatal per suite.
$probe = unit_run( array_merge( array( PHP_BINARY ), $php_args, array( '-r', 'foreach ( array( "mbstring", "sodium", "json", "hash" ) as $e) { if (!extension_loaded($e)) { echo $e . " "; } }' ) ) );
if ( '' !== trim( $probe['output'] ) ) {
	echo 'ERROR: PHP extension(s) missing for the suites: ' . trim( $probe['output'] ) . '(set FAMMA_PHP_ARGS, e.g. "-n -d extension_dir=... -d extension=mbstring -d extension=sodium").' . PHP_EOL;
	exit( 1 );
}

$failed  = array();
$green   = 0;
$known_n = 0;
$stale_n = 0;
$started = microtime( true );
foreach ( $suites as $suite ) {
	$command = 'php' === $suite['kind'] ? array_merge( array( PHP_BINARY ), $php_args, array( $suite['path'] ) ) : array( $node, $suite['path'] );
	$begin   = microtime( true );
	$result  = unit_run( $command );
	$reasons = unit_verdict( $result['code'], $result['output'] );
	if ( 'js' === $suite['kind'] && 127 === $result['code'] ) {
		$reasons[] = 'node is not available (use --php-only to skip the browser-script suites)';
	}
	$passes = preg_match_all( '/^(?:PASS|ok)\b/mi', $result['output'] );
	$known  = isset( $quarantine[ $suite['name'] ] );
	$label  = array() === $reasons ? 'OK  ' : ( $known ? 'KNOWN' : 'FAIL' );
	$stale  = preg_match_all( '/^KNOWN-STALE\b/m', $result['output'] );
	printf( "%s  %-52s %3d PASS  %5.1f s%s\n", $label, $suite['name'], $passes, microtime( true ) - $begin, $stale > 0 && array() === $reasons ? sprintf( '  (%d scenario(s) KNOWN-STALE)', $stale ) : '' );
	if ( $stale > 0 && array() === $reasons ) {
		$stale_n += $stale;
		foreach ( explode( "\n", $result['output'] ) as $line ) {
			if ( str_starts_with( $line, 'KNOWN-STALE' ) ) {
				echo '      ' . substr( rtrim( $line ), 0, 240 ) . PHP_EOL;
			}
		}
	}
	if ( $known && array() !== $reasons ) {
		++$known_n;
	} elseif ( array() === $reasons ) {
		++$green;
	}
	if ( $known ) {
		echo '      quarantined (scripts/tests-quarantine.txt): ' . ( array() === $reasons ? 'now GREEN, remove it from the file' : $quarantine[ $suite['name'] ] ) . PHP_EOL;
		continue;
	}
	if ( array() !== $reasons ) {
		$failed[ $suite['name'] ] = $reasons;
		$lines                    = explode( "\n", trim( $result['output'] ) );
		echo '      reason: ' . implode( '; ', $reasons ) . PHP_EOL;
		foreach ( array_slice( $lines, -12 ) as $line ) {
			echo '      | ' . substr( rtrim( $line ), 0, 240 ) . PHP_EOL;
		}
	} elseif ( $verbose ) {
		echo $result['output'];
	}
}

printf( "\n%d suite(s) run in %.1f s: %d green, %d red, %d KNOWN (quarantined suites); %d KNOWN-STALE scenario(s) inside green suites.\n", count( $suites ), microtime( true ) - $started, $green, count( $failed ), $known_n, $stale_n );
if ( array() !== $failed ) {
	echo 'RED: ' . implode( ', ', array_keys( $failed ) ) . PHP_EOL;
	exit( 1 );
}
exit( 0 );
