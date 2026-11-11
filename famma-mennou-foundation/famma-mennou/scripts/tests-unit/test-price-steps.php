<?php
/**
 * Nouveau look, brique tools — choix des puces de budget à partir des prix
 * publiés (famma_child_pick_price_steps). Prix de TEST, inventés pour le
 * calcul : aucun n'est un vrai produit.
 * Run with: php scripts/tests-unit/test-price-steps.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test output only.

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

/**
 * Double of add_action: hooks are not run here.
 *
 * @param string   $hook     Hook.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @return bool
 */
function add_action( string $hook, $callback, int $priority = 10 ): bool {
	return true;
}

require ABSPATH . 'wp-content/themes/famma-child/inc/new-look-tools.php';

// Each case: label, test prices, max chips, expected chips.
$cases = array(
	array( 'catalogue vide', array(), 3, array() ),
	array( 'un seul prix : rien à filtrer', array( 39 ), 3, array() ),
	array( 'le plus cher est retiré', array( 22, 29, 39, 49 ), 3, array( 22, 29, 39 ) ),
	array( 'doublons et désordre', array( 49, 22, 29, 22, 49, 29 ), 3, array( 22, 29 ) ),
	array( 'gamme large répartie', array( 10, 15, 20, 25, 30, 35, 40, 45, 99 ), 3, array( 10, 30, 45 ) ),
	array( 'max 1', array( 10, 20, 30 ), 1, array( 10 ) ),
	array( 'max 0', array( 10, 20, 30 ), 0, array() ),
);

$failed = 0;

foreach ( $cases as $case ) {
	list( $label, $prices, $max, $expected ) = $case;

	$got = famma_child_pick_price_steps( $prices, $max );

	if ( $got === $expected ) {
		echo 'OK   ' . $label . PHP_EOL;
	} else {
		++$failed;
		echo 'FAIL ' . $label . ' : attendu ' . json_encode( $expected ) . ', obtenu ' . json_encode( $got ) . PHP_EOL;
	}
}

echo ( $failed ? 'RESULT ' . $failed . ' échec(s)' : 'RESULT ' . count( $cases ) . '/' . count( $cases ) . ' cas de budget réussis.' ) . PHP_EOL;
exit( $failed ? 1 : 0 );
