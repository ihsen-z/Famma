<?php
/**
 * Clone QA pixel, lot 2 (T2.1) : masque le jeton factice du clone pour UN
 * seul processus WP-CLI, sans toucher à la base partagée.
 *
 * Les suites d'intégration existantes refusent de tourner si l'option
 * famma_meta_access_token n'est pas vide. Le lot 0 prévoit « token off »
 * puis « token on », ce qui vide le jeton pour TOUT le clone, donc aussi
 * pour l'agent du lot 3 qui l'utilise en parallèle. Ce fichier fait voir
 * une option vide au seul processus de la suite, et uniquement tant que la
 * valeur stockée est exactement le jeton factice du clone (en clair, ou
 * chiffré au repos par famma-core 0.3.2) : dès qu'une
 * suite pose son propre jeton factice (update_option), il redevient
 * visible tel quel.
 *
 * Usage (chargé AVANT WordPress) :
 *   ... run --rm -e ... cli --require=/tests/qa/qa-l2-mask-qa-token.php eval-file /tests/<suite>.php
 *
 * N'écrit rien. Ne lit que la ligne d'option, directement en base, pour
 * ne pas dépendre du cache d'options du processus.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- WP-CLI --require helper, not an autoloaded plugin class file.

defined( 'ABSPATH' ) || defined( 'WP_CLI' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

WP_CLI::add_wp_hook(
	'pre_option_famma_meta_access_token',
	static function ( $pre ) {
		global $wpdb;
		if ( false !== $pre || ! isset( $wpdb ) ) {
			return $pre;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- the stored row must be read without the per-process option cache.
		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'famma_meta_access_token' ) );

		$stored = (string) $stored;
		// famma-core 0.3.2 (lot 5, D7) chiffre le jeton au repos : le jeton factice
		// peut être stocké « famma-enc:v1:… ». Il n'est masqué que si son
		// déchiffrement redonne exactement le jeton factice du clone.
		if ( class_exists( '\Famma\Core\Capi_Token_Vault' ) && \Famma\Core\Capi_Token_Vault::is_sealed( $stored ) ) {
			$stored = \Famma\Core\Capi_Token_Vault::open( $stored );
		}

		return 'QA-FAKE-TOKEN-NOT-REAL' === $stored ? '' : false;
	},
	1
);
