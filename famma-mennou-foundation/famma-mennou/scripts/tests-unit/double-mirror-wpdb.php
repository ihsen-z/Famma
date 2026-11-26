<?php
/**
 * Lot 59 — `$wpdb` double for the server duplicate: `wp_options` in memory
 * (`$GLOBALS['wp_test_options']`, insertion order = option_id order), with the
 * SQL semantics the code relies on:
 * - `INSERT IGNORE` inserts only the absent names and returns how many it inserted;
 * - `INSERT … ON DUPLICATE KEY UPDATE` of `Rate_Counter` adds within a slot, restarts otherwise;
 * - `UPDATE … WHERE option_value = <read value>` succeeds for one caller only.
 * `$GLOBALS['mirror_race']` (callable) runs once just before the first statement
 * whose text contains `$GLOBALS['mirror_race_on']`: it plays a second process
 * that gets there first. Reads and writes are counted for the measurements.
 * Required after bootstrap-browser.php.
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Documented by the wpdb API it replaces.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test double and helper of the standalone runner.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- The double replaces the database; WordPress is never loaded.

const ARRAY_A = 'ARRAY_A';

/** In-memory `wp_options` with the statements of Rate_Counter, Capi_Mirror_Store, Visitor_Store and Capi_Mirror_Data. */
final class Mirror_Test_Wpdb {
	public string $options   = 'wp_options';
	public string $prefix    = 'wp_';
	public int $reads        = 0;
	public int $writes       = 0;
	public int $reads_inputs = 0;
	public array $journal    = array();
	public array $inputs     = array();

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}
	public function prepare( string $query, ...$args ): array {
		return array(
			'q' => $query,
			'a' => isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : $args,
		);
	}
	private function race( string $query ): void {
		$on = (string) ( $GLOBALS['mirror_race_on'] ?? '' );
		if ( '' !== $on && str_contains( $query, $on ) && is_callable( $GLOBALS['mirror_race'] ?? null ) ) {
			$race                      = $GLOBALS['mirror_race'];
			$GLOBALS['mirror_race']    = null;
			$GLOBALS['mirror_race_on'] = '';
			$race();
		}
	}
	private static function like( string $pattern ): string {
		return stripslashes( rtrim( $pattern, '%' ) );
	}
	public function get_var( array $prepared ) {
		$this->race( $prepared['q'] );
		++$this->reads;
		$value = $GLOBALS['wp_test_options'][ (string) $prepared['a'][0] ] ?? null;
		return null === $value ? null : (string) $value;
	}
	public function get_col( array $prepared ): array {
		++$this->reads;
		++$this->reads_inputs;
		$found = array();
		foreach ( $this->inputs as $row ) {
			if ( $row['visitor_id'] === $prepared['a'][1] && '' !== $row['phone_norm'] ) {
				$found[] = $row['phone_norm'];
			}
		}
		return array_slice( array_reverse( $found ), 0, 3 );
	}
	public function get_results( array $prepared, $output = null ): array {
		++$this->reads;
		$prefix = self::like( (string) $prepared['a'][0] );
		$done   = self::like( (string) $prepared['a'][1] );
		$rows   = array();
		foreach ( $GLOBALS['wp_test_options'] as $name => $value ) {
			if ( str_starts_with( (string) $name, $prefix ) && ! str_starts_with( (string) $value, $done ) && count( $rows ) < (int) $prepared['a'][2] ) {
				$rows[] = array(
					'option_name'  => $name,
					'option_value' => $value,
				);
			}
		}
		return $rows;
	}
	public function query( array $prepared ): int {
		$q = $prepared['q'];
		$a = $prepared['a'];
		$this->race( $q );
		++$this->writes;
		$options = &$GLOBALS['wp_test_options'];
		if ( str_starts_with( $q, 'INSERT INTO %i' ) ) {
			$this->journal[] = $a;
			return 1;
		}
		if ( str_starts_with( $q, 'INSERT IGNORE' ) ) {
			$added = 0;
			$pairs = count( $a );
			for ( $i = 0; $i + 1 < $pairs; $i += 2 ) {
				if ( ! array_key_exists( $a[ $i ], $options ) ) {
					$options[ $a[ $i ] ] = $a[ $i + 1 ];
					++$added;
				}
			}
			return $added;
		}
		if ( str_contains( $q, 'ON DUPLICATE KEY UPDATE' ) ) {
			list( $name, $fresh, $span, $slot, $cost ) = $a;
			$old                                       = isset( $options[ $name ] ) ? (int) $options[ $name ] : null;
			$options[ $name ]                          = null !== $old && intdiv( $old, $span ) === $slot ? (string) ( $old + $cost ) : $fresh;
			return 1;
		}
		if ( str_contains( $q, 'SET option_value = %s WHERE option_name IN' ) ) {
			$value = array_shift( $a );
			$hit   = 0;
			foreach ( $a as $name ) {
				if ( array_key_exists( $name, $options ) ) {
					$options[ $name ] = $value;
					++$hit;
				}
			}
			return $hit;
		}
		if ( str_contains( $q, 'AND option_value NOT LIKE' ) && str_starts_with( $q, 'UPDATE' ) ) {
			if ( isset( $options[ $a[1] ] ) && ! str_starts_with( (string) $options[ $a[1] ], self::like( $a[2] ) ) ) {
				$options[ $a[1] ] = $a[0];
				return 1;
			}
			return 0;
		}
		if ( str_starts_with( $q, 'UPDATE' ) ) {
			if ( isset( $options[ $a[1] ] ) && (string) $options[ $a[1] ] === $a[2] ) {
				$options[ $a[1] ] = $a[0];
				return 1;
			}
			return 0;
		}
		if ( str_contains( $q, 'CAST( SUBSTRING' ) ) {
			$gone = 0;
			foreach ( $options as $name => $value ) {
				if ( str_starts_with( (string) $name, self::like( $a[0] ) ) && str_starts_with( (string) $value, 's:' ) && (int) substr( (string) $value, 2 ) < $a[2] ) {
					unset( $options[ $name ] );
					++$gone;
				}
			}
			return $gone;
		}
		if ( str_contains( $q, 'option_name = %s AND option_value = %s' ) ) {
			if ( isset( $options[ $a[0] ] ) && (string) $options[ $a[0] ] === $a[1] ) {
				unset( $options[ $a[0] ] );
				return 1;
			}
			return 0;
		}
		$gone = 0;
		foreach ( array_keys( $options ) as $name ) {
			if ( str_starts_with( (string) $name, self::like( $a[0] ) ) || ( $a[1] ?? null ) === $name ) {
				unset( $options[ $name ] );
				++$gone;
			}
		}
		return $gone;
	}
}
$GLOBALS['wpdb'] = new Mirror_Test_Wpdb();

/**
 * Rows of the mirror queue still waiting, decoded.
 *
 * @return array
 */
function mirror_queued(): array {
	$rows = array();
	foreach ( $GLOBALS['wp_test_options'] as $name => $value ) {
		if ( str_starts_with( (string) $name, 'famma_mq_' ) && ! str_starts_with( (string) $value, 's:' ) ) {
			$rows[] = json_decode( (string) $value, true );
		}
	}
	return $rows;
}
