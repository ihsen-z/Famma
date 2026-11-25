<?php
/**
 * Category slug redirect: an old product_cat slug answers 301 to the current term link.
 *
 * Covers the pure decision (no WordPress): old slug to new link, slug still
 * existing, /page/N/, /ar/, query string kept, no loop, history table upkeep,
 * the built-in beaute-sante alias.
 *
 * Run with: php scripts/tests-unit/test-category-slug-redirect.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php for the doubles.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Standalone script.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Command-line report.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Test report prints the failing values.

require __DIR__ . '/bootstrap.php';
require dirname( __DIR__, 2 ) . '/wp-content/plugins/famma-core/includes/category-slug-redirect.php';

use Famma\Core\Category_Slug_Redirect as R;

// Terms of the synthetic shop: slug => link.
$GLOBALS['terms'] = array(
	'beaute-soin'  => 'https://qa.invalid/categorie/beaute-soin/',
	'electronique' => 'https://qa.invalid/categorie/electronique/',
);

$total  = 0;
$passed = 0;

/**
 * Compare one result and print a PASS or FAIL line.
 *
 * @param string $label    Scenario.
 * @param mixed  $actual   Result.
 * @param mixed  $expected Expectation.
 * @return void
 */
function check( string $label, $actual, $expected ): void {
	global $total, $passed;
	++$total;
	if ( $actual === $expected ) {
		++$passed;
		echo "PASS $label\n";
		return;
	}
	echo "FAIL $label : " . var_export( $actual, true ) . ' au lieu de ' . var_export( $expected, true ) . "\n";
}

/**
 * Run the decision against the synthetic shop.
 *
 * @param string               $path  Request path.
 * @param array<string, int>   $map   History table.
 * @param array<string, array> $seed  Built-in aliases.
 * @param string               $query Query string.
 * @return string|null
 */
function decide( string $path, array $map = array(), array $seed = array(), string $query = '' ): ?string {
	$terms = $GLOBALS['terms'];
	$ids   = array(
		7 => 'beaute-soin',
		8 => 'electronique',
	);

	return R::decide(
		$path,
		$query,
		'categorie',
		array( 'ar' ),
		$map,
		$seed,
		static fn( string $slug ): bool => isset( $terms[ $slug ] ),
		static fn( string $slug ): string => $terms[ $slug ] ?? '',
		static fn( int $id ): string => isset( $ids[ $id ] ) ? $terms[ $ids[ $id ] ] : ''
	);
}

$history = array( 'beaute-sante' => 7 );
$seed    = R::seed();

check( 'ancien slug (table) -> 301 vers le lien actuel', decide( '/categorie/beaute-sante/', $history ), 'https://qa.invalid/categorie/beaute-soin/' );
check( 'sans barre finale', decide( '/categorie/beaute-sante', $history ), 'https://qa.invalid/categorie/beaute-soin/' );
check( 'alias intégré sans table (renommage fait avant le crochet)', decide( '/categorie/beaute-sante/', array(), $seed ), 'https://qa.invalid/categorie/beaute-soin/' );
check( 'alias : le terme ne porte pas encore le nouveau slug -> rien', decide( '/categorie/beaute-sante/', array(), array( 'beaute-sante' => array( 'beaute-soin-absent' ) ) ), null );
check( 'slug encore existant -> pas de redirection', decide( '/categorie/electronique/', array( 'electronique' => 7 ), $seed ), null );
check( 'slug existant même présent dans l’alias -> pas de redirection', decide( '/categorie/beaute-soin/', array( 'beaute-soin' => 8 ), array( 'beaute-soin' => array( 'electronique' ) ) ), null );
check( 'slug inconnu -> rien', decide( '/categorie/inconnu/', $history, $seed ), null );
check( 'identifiant de terme disparu, sans alias -> rien', decide( '/categorie/vieux/', array( 'vieux' => 99 ) ), null );
check( 'pagination /page/3/', decide( '/categorie/beaute-sante/page/3/', $history ), 'https://qa.invalid/categorie/beaute-soin/page/3/' );
check( 'page 1 explicite -> sans /page/1/', decide( '/categorie/beaute-sante/page/1/', $history ), 'https://qa.invalid/categorie/beaute-soin/' );
check( 'variante arabe /ar/', decide( '/ar/categorie/beaute-sante/', $history ), 'https://qa.invalid/ar/categorie/beaute-soin/' );
check( 'variante arabe + pagination', decide( '/ar/categorie/beaute-sante/page/2/', $history ), 'https://qa.invalid/ar/categorie/beaute-soin/page/2/' );
check( 'lien déjà préfixé /ar/ : pas de double préfixe', R::build_target( 'https://qa.invalid/ar/categorie/beaute-soin/', 'ar', 1, '' ), 'https://qa.invalid/ar/categorie/beaute-soin/' );
check( 'requête (utm) conservée', decide( '/categorie/beaute-sante/', $history, array(), 'utm_source=fb&fbclid=abc' ), 'https://qa.invalid/categorie/beaute-soin/?utm_source=fb&fbclid=abc' );
check( 'autre chemin (produit) -> rien', decide( '/produit/beaute-sante/', $history, $seed ), null );
check( 'sous-chemin inconnu -> rien', decide( '/categorie/beaute-sante/extra/', $history, $seed ), null );
check( 'préfixe de langue inconnu -> rien', decide( '/fr/categorie/beaute-sante/', $history, $seed ), null );

// No loop: a history entry that points to a term whose link is the requested path.
check( 'pas de boucle (cible = chemin demandé)', R::decide( '/categorie/vieux/', '', 'categorie', array( 'ar' ), array( 'vieux' => 5 ), array(), static fn(): bool => false, static fn(): string => '', static fn(): string => 'https://qa.invalid/categorie/vieux/' ), null );

// Upkeep of the history table.
check( 'enregistre ancien slug => id', R::record_change( array(), 7, 'beaute-sante', 'beaute-soin' ), array( 'beaute-sante' => 7 ) );
check( 'slug inchangé -> table inchangée', R::record_change( array( 'a' => 1 ), 7, 'x', 'x' ), array( 'a' => 1 ) );
check( 'retour à un ancien slug -> l’entrée disparaît, l’ancien est noté', R::record_change( array( 'beaute-sante' => 7 ), 7, 'beaute-soin', 'beaute-sante' ), array( 'beaute-soin' => 7 ) );
check( 'ancien slug vide -> table inchangée', R::record_change( array( 'a' => 1 ), 7, '', 'x' ), array( 'a' => 1 ) );
$big = array();
for ( $n = 0; $n < 205; ++$n ) {
	$big = R::record_change( $big, $n, 'old' . $n, 'new' );
}
check( 'table plafonnée à 200 entrées, plus récentes gardées', array( count( $big ), isset( $big['old204'] ), isset( $big['old0'] ) ), array( 200, true, false ) );

echo "RESULT $passed/$total\n";
exit( $passed === $total ? 0 : 1 );
