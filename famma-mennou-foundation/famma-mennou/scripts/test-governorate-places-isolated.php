<?php
/**
 * Lieux par gouvernorat (Governorate_Places) : tests isolés, sans WordPress.
 * Lancer : php scripts/test-governorate-places-isolated.php
 *
 * Vérifie la liste officielle des délégations (Ministère de l'Intérieur,
 * 24 gouvernorats, 264 délégations), les formes tirées de ces noms, l'écart
 * des noms communs, le filtre `famma_governorate_places` et le poids envoyé
 * au navigateur. La vraie classe est chargée ; seul `apply_filters` est doublé.
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI runner output, never served.

use Famma\Core\Governorate_Places;

define( 'ABSPATH', __DIR__ . '/' );

$famma_filter = null;

function apply_filters( string $hook, $value ) {
	global $famma_filter;
	return 'famma_governorate_places' === $hook && is_callable( $famma_filter ) ? $famma_filter( $value ) : $value;
}

$famma_plugin = dirname( __DIR__ ) . '/wp-content/plugins/famma-core';
require $famma_plugin . '/includes/governorate-places.php';

$passed = 0;
$failed = 0;

function check( bool $condition, string $message ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo 'PASS: ' . $message . PHP_EOL;
		return;
	}
	++$failed;
	echo 'FAIL: ' . $message . PHP_EOL;
}

// Codes de la liste affichée, lus dans le vrai `Tunisia::governorates()`.
preg_match_all( "/'(TN-\d{2})' => '[^']+',/", (string) file_get_contents( $famma_plugin . '/famma-core.php' ), $listed );
$codes = array_values( array_unique( $listed[1] ) );

$delegations = ( new ReflectionClassConstant( Governorate_Places::class, 'DELEGATIONS' ) )->getValue();
$count       = array_sum( array_map( 'count', $delegations ) );
$pairs       = array_merge( ...array_values( $delegations ) );
$places      = Governorate_Places::places();
$all         = array_merge( ...array_values( $places ) );

check( 24 === count( $codes ) && array_keys( $delegations ) === array_keys( $places ), 'source : 24 gouvernorats, dans l’ordre des données' );
check( array() === array_diff( $codes, array_keys( $places ) ) && array() === array_diff( array_keys( $places ), $codes ), 'source : mêmes codes que la liste des gouvernorats du formulaire' );
check( 264 === $count, 'source : 264 délégations (Ministère de l’Intérieur, 2013), lu : ' . $count );
check(
	array() === array_filter(
		$pairs,
		static function ( $pair ): bool {
			return ! is_array( $pair ) || 2 !== count( $pair ) || ! preg_match( '/^[\p{Latin}\s.\'’-]+$/u', $pair[0] ) || ! preg_match( '/^[\p{Arabic}\s]+$/u', $pair[1] );
		}
	),
	'source : chaque délégation a un nom français et un nom arabe'
);

$expect = array(
	'TN-21' => array( 'Hammamet', 'الحمامات' ),
	'TN-51' => array( 'Msaken', 'مساكن', 'Sousse', 'سوسة', 'Thrayat' ),
	'TN-82' => array( 'Jerba', 'جربة', 'Zazis' ),
	'TN-73' => array( 'Douz' ),
	'TN-52' => array( 'Sayada', 'Lamta', 'Bouhjar', 'Ksar Helal' ),
	'TN-13' => array( 'Boumhel', 'Mourouj', 'المروج' ),
	'TN-23' => array( 'Bizerte', 'بنزرت' ),
	'TN-33' => array( 'Kef' ),
	'TN-12' => array( 'M’nihla' ),
);
foreach ( $expect as $code => $famma_names ) {
	$missing = array_diff( $famma_names, $places[ $code ] );
	check( array() === $missing, 'noms : ' . $code . ' contient ' . implode( ', ', $famma_names ) . ( $missing ? ' (manque ' . implode( ', ', $missing ) . ')' : '' ) );
}

check( ! in_array( 'La Médina', $all, true ) && ! in_array( 'المدينة', $all, true ), 'noms communs : « La Médina » / « المدينة » écartés' );
check( in_array( 'Nouvelle Médina', $places['TN-13'], true ) && in_array( 'سوسة المدينة', $places['TN-51'], true ), 'noms communs : les noms complets qui les contiennent restent' );
check( ! in_array( 'Hammam', $all, true ) && ! in_array( 'Sidi', $all, true ) && ! in_array( 'Menzel', $all, true ), 'premiers mots : écartés quand plusieurs gouvernorats les partagent' );
check(
	array() === array_filter(
		$places,
		static function ( array $names ): bool {
			return count( $names ) !== count( array_unique( $names ) );
		}
	),
	'noms : aucun doublon dans un même gouvernorat'
);

$json = (string) json_encode( $places, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
$gz   = strlen( (string) gzencode( $json, 9 ) );
check( $gz <= 15 * 1024, 'poids : ' . strlen( $json ) . ' octets, ' . $gz . ' compressés (≤ 15 Ko)' );

$famma_filter = static function ( array $value ): array {
	$value['TN-82'][] = 'Zarzis';
	return $value;
};
check( in_array( 'Zarzis', Governorate_Places::places()['TN-82'], true ), 'filtre : un nom ajouté par `famma_governorate_places` est rendu' );

$famma_filter = static function (): string {
	return 'cassé';
};
check( Governorate_Places::places() === $places, 'filtre : une valeur qui n’est pas un tableau garde la liste officielle' );

$source = (string) file_get_contents( $famma_plugin . '/includes/governorate-places.php' );
check( str_contains( $source, 'opendata.interieur.gov.tn' ) && str_contains( $source, '25/06/2013' ), 'source citée dans le fichier (portail du Ministère, date du jeu)' );

echo 'RESULT ' . $passed . '/' . ( $passed + $failed ) . ' contrôles des lieux réussis' . ( $failed ? ', ' . $failed . ' ÉCHEC(S)' : '' ) . '.' . PHP_EOL;
exit( $failed ? 1 : 0 );
