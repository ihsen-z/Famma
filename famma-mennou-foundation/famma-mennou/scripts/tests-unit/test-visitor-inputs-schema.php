<?php
/**
 * Lot 7 du pixel, correction I3 de la relecture — schéma 3 des saisies (`fbc`, `fbp`).
 * La version 3 n'est validée que si les deux colonnes existent réellement (SHOW COLUMNS
 * via $wpdb->prepare) ; sinon l'installation est rejouée au chargement suivant, et les
 * saisies s'enregistrent sans les cookies Meta : jamais de saisie perdue.
 * Run with: php scripts/tests-unit/test-visitor-inputs-schema.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
require __DIR__ . '/bootstrap-capi.php';
Famma_Unit_Harness::load_include( 'visitor-inputs.php' );
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Doubles are documented by the API they replace.
function dbDelta( string $sql ): array {
	$GLOBALS['famma_test_dbdelta'][] = $sql;
	// The ALTER may fail (rights, lock, concurrent request): the test decides.
	if ( ! empty( $GLOBALS['famma_test_alter_ok'] ) ) {
		$GLOBALS['famma_test_columns'] = array_merge( $GLOBALS['famma_test_columns'], array( 'fbc', 'fbp' ) );
	}
	return array();
}
/**
 * Table des saisies en mémoire : SHOW TABLES / SHOW COLUMNS, lecture et écritures.
 */
final class Famma_Test_Inputs_Wpdb {
	public string $prefix = 'wp_';
	public array $writes  = array();
	public function get_charset_collate(): string {
		return '';
	}
	public function esc_like( string $text ): string {
		return $text;
	}
	public function prepare( string $query, ...$args ): array {
		return array( $query, $args );
	}
	public function get_var( array $prepared ) {
		list( $query, $args ) = $prepared;
		if ( str_starts_with( $query, 'SHOW TABLES' ) ) {
			return 'wp_famma_visitor_inputs';
		}
		if ( str_starts_with( $query, 'SHOW COLUMNS FROM %i LIKE %s' ) ) {
			$GLOBALS['famma_test_show_columns'][] = $args;
			return in_array( $args[1], $GLOBALS['famma_test_columns'], true ) ? $args[1] : null;
		}
		return null;
	}
	public function get_row( array $prepared, $output = null ) {
		return null;
	}
	public function insert( string $table, array $data ) {
		// MySQL refuses an unknown column: the whole write is lost.
		if ( array_diff( array_keys( $data ), $GLOBALS['famma_test_columns'] ) ) {
			return false;
		}
		$this->writes[] = $data;
		return 1;
	}
	public function update( string $table, array $data, array $where ) {
		return $this->insert( $table, $data );
	}
}
// phpcs:enable

$base = array( 'created_at', 'updated_at', 'visitor_id', 'session_id', 'product_id', 'path', 'first_name', 'phone', 'phone_norm', 'state', 'address', 'city', 'size', 'quantity', 'source', 'medium', 'campaign', 'content', 'device', 'status', 'order_id', 'note' );
$meta = array(
	'visitor_id' => '0a1b2c3d-4e5f-4a6b-9c7d-8e9f0a1b2c3d',
	'session_id' => '7c1e2d3f-4a5b-4c6d-8e7f-9a0b1c2d3e4f',
	'product_id' => 0,
	'path'       => '/produit/x/',
	'source'     => 'facebook',
	'medium'     => 'fbclid',
	'campaign'   => '',
	'content'    => '',
	'device'     => 'mobile',
	'fbc'        => 'fb.1.1764000000000.IwAR0AbC',
	'fbp'        => 'fb.1.1764000000123.1234567890',
);
$inputs = Famma_Unit_Harness::bare( '\Famma\Core\Visitor_Inputs' );

// ALTER refused: version not validated, inputs still recorded without the Meta cookies.
l5_reset( array( 'famma_visitor_inputs_schema' => '2' ) );
$GLOBALS['wpdb']                = new Famma_Test_Inputs_Wpdb();
$GLOBALS['famma_test_columns']  = $base;
$GLOBALS['famma_test_alter_ok'] = false;
$inputs->install();
l5_check( '2' === get_option( 'famma_visitor_inputs_schema' ) && ! $inputs->cookie_columns_ready(), 'I3: columns missing after dbDelta: schema option stays at 2.' );
l5_check( array( 'wp_famma_visitor_inputs', 'fbc' ) === ( $GLOBALS['famma_test_show_columns'][0] ?? array() ), 'I3: the check is SHOW COLUMNS FROM %i LIKE %s, through $wpdb->prepare().' );
l5_check( true === $inputs->save( $meta, array( 'first_name' => 'Amel' ) ) && 'Amel' === $GLOBALS['wpdb']->writes[0]['first_name'] && ! isset( $GLOBALS['wpdb']->writes[0]['fbc'] ), 'I3: the input is saved without fbc/fbp: never lost because of the Meta tracking.' );
$inputs->install();
l5_check( 2 === count( $GLOBALS['famma_test_dbdelta'] ), 'I3: the installation is replayed on the next load.' );

// ALTER succeeds on the next load: version 3 validated, cookies recorded.
$GLOBALS['famma_test_alter_ok'] = true;
$inputs->install();
l5_check( '3' === get_option( 'famma_visitor_inputs_schema' ) && $inputs->cookie_columns_ready(), 'I3: both columns present: schema 3 validated.' );
$inputs->save( $meta, array( 'first_name' => 'Amel' ) );
$last = end( $GLOBALS['wpdb']->writes );
l5_check( $meta['fbc'] === ( $last['fbc'] ?? '' ) && $meta['fbp'] === ( $last['fbp'] ?? '' ), 'Schema 3: the latest fbc/fbp are recorded with the input.' );
$inputs->install();
l5_check( 3 === count( $GLOBALS['famma_test_dbdelta'] ), 'Schema 3 installed: dbDelta is not run again.' );

// Only one column added (partial ALTER): still not validated.
l5_reset( array( 'famma_visitor_inputs_schema' => '2' ) );
$GLOBALS['wpdb']                = new Famma_Test_Inputs_Wpdb();
$GLOBALS['famma_test_columns']  = array_merge( $base, array( 'fbc' ) );
$GLOBALS['famma_test_alter_ok'] = false;
$inputs->install();
l5_check( '2' === get_option( 'famma_visitor_inputs_schema' ), 'I3: fbc present but fbp missing: schema 3 not validated.' );

echo 'RESULT ' . $GLOBALS['l5_checks'] . '/' . $GLOBALS['l5_checks'] . PHP_EOL;
