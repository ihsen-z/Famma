<?php
/**
 * Diagnostic du clone QA pixel : imprime la pile d'appels de chaque
 * « _doing_it_wrong » (par exemple _load_textdomain_just_in_time), pour
 * attribuer un avis du debug.log à son auteur réel (WordPress, WooCommerce,
 * famma-core...) au lieu de le deviner.
 *
 * Usage (WP-CLI, chargé AVANT WordPress) :
 *   ... run --rm cli --require=/tests/qa/qa-trace-doing-it-wrong.php <commande wp>
 *
 * Lecture seule : n'écrit rien en base, ne modifie aucun comportement.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- WP-CLI --require helper, not an autoloaded plugin class file.

defined( 'ABSPATH' ) || defined( 'WP_CLI' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

WP_CLI::add_wp_hook(
	'doing_it_wrong_run',
	static function ( $function_name, $message ): void {
		$trace = function_exists( 'wp_debug_backtrace_summary' ) ? wp_debug_backtrace_summary( null, 0, false ) : array();
		WP_CLI::line( 'QA-TRACE doing_it_wrong: ' . $function_name . ' | ' . wp_strip_all_tags( (string) $message ) );
		foreach ( (array) $trace as $frame ) {
			WP_CLI::line( 'QA-TRACE   ' . $frame );
		}
	},
	10,
	2
);
