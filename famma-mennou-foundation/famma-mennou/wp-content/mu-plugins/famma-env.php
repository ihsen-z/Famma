<?php
/**
 * Plugin Name: FAMMA — pont environnement vers constantes
 * Description: Transforme les variables d'environnement FAMMA_* en constantes PHP, avant le chargement des extensions. C'est le pont .env -> code du §41.
 * Version:     0.1.0
 * Author:      FAMMA
 *
 * Pourquoi ici et pas dans wp-config.php : l'image officielle WordPress
 * n'ecrit wp-config.php qu'a la creation du volume et ne le regenere jamais.
 * Toute variable ajoutee a WORDPRESS_CONFIG_EXTRA apres ce premier demarrage
 * n'atteint donc jamais PHP — constate a l'execution, wp-config.php ne
 * contenait aucune constante FAMMA_ alors que la variable etait bien
 * presente dans le conteneur. Un mu-plugin est charge avant toutes les
 * extensions et survit a toute regeneration de la configuration.
 *
 * getenv() est utilise plutot que $_ENV : la directive PHP variables_order
 * vaut « GPCS » par defaut et ne peuple donc pas $_ENV, alors que getenv()
 * lit l'environnement reel du processus quoi qu'il arrive.
 *
 * FAMMA_META_ACCESS_TOKEN reste strictement cote serveur : il n'est jamais
 * enqueue vers le navigateur (§17).
 *
 * @package Famma\Local
 */

defined( 'ABSPATH' ) || exit;

/**
 * Defines the FAMMA_* constants from the process environment.
 *
 * A missing or empty variable defines the constant as an empty string, which
 * Famma\Core\Config reads as "feature disabled" — the intended behaviour
 * until the owner supplies real identifiers (§60: nothing is invented).
 *
 * @return void
 */
function famma_env_define_constants(): void {
	$names = array(
		'FAMMA_WHATSAPP_NUMBER',
		'FAMMA_META_PIXEL_ID',
		'FAMMA_META_ACCESS_TOKEN',
		'FAMMA_META_TEST_EVENT_CODE',
		'FAMMA_TIKTOK_PIXEL_ID',
		'FAMMA_GA4_MEASUREMENT_ID',
		'FAMMA_GOOGLE_SITE_VERIFICATION',
	);

	foreach ( $names as $name ) {
		if ( defined( $name ) ) {
			continue;
		}
		$value = getenv( $name );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- names come from the FAMMA_-prefixed list above.
		define( $name, false === $value ? '' : trim( $value ) );
	}
}

famma_env_define_constants();
