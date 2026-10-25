<?php
/**
 * Sonde de cache du clone QA pixel (plan pixel, lot 5, testeur navigateur QA-L5N).
 *
 * Le clone n'a pas LiteSpeed Cache. Pour les tests A2 (aucune variante
 * « admin sans Pixel » servie à un visiteur) et D2 (redirections jamais en
 * cache), cette sonde rend VISIBLE dans les en-têtes de réponse ce que
 * famma-core demande au cache, sans rien changer au comportement :
 *
 *  - X-QA-L5N-LSCache-Nocache : posé chaque fois que l'action
 *    `litespeed_control_set_nocache` (API publique de LiteSpeed Cache) est
 *    déclenchée pendant la requête, avec la raison donnée (encodée URL) ;
 *  - X-QA-L5N-DONOTCACHEPAGE : 1 si la constante DONOTCACHEPAGE est définie à
 *    la fin de `template_redirect`, 0 sinon (pages non redirigées seulement).
 *
 * Inerte hors du clone (home_url() doit valoir http://localhost:8980).
 * Copiée TEMPORAIREMENT dans wp-content/mu-plugins du clone par le testeur
 * (docker cp), puis retirée à la fin des tests : jamais déployée ailleurs.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- mu-plugin de test.

defined( 'ABSPATH' ) || exit;

/**
 * Le site courant est-il le clone QA pixel ?
 *
 * @return bool
 */
function famma_qa_l5n_probe_active(): bool {
	$parts = wp_parse_url( home_url( '/' ) );

	return is_array( $parts )
		&& 'localhost' === strtolower( (string) ( $parts['host'] ?? '' ) )
		&& 8980 === (int) ( $parts['port'] ?? 0 );
}

/**
 * Rend visible l'appel à l'API « no-cache » de LiteSpeed.
 *
 * @param mixed $reason Raison transmise par l'appelant.
 * @return void
 */
function famma_qa_l5n_probe_nocache( $reason = '' ): void {
	if ( ! headers_sent() ) {
		header( 'X-QA-L5N-LSCache-Nocache: ' . rawurlencode( substr( (string) $reason, 0, 120 ) ), false );
	}
}

/**
 * Rend visible la constante DONOTCACHEPAGE en fin de template_redirect.
 *
 * @return void
 */
function famma_qa_l5n_probe_donotcache(): void {
	if ( ! headers_sent() ) {
		header( 'X-QA-L5N-DONOTCACHEPAGE: ' . ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ? '1' : '0' ) );
	}
}

if ( famma_qa_l5n_probe_active() ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API publique de LiteSpeed Cache, observée seulement.
	add_action( 'litespeed_control_set_nocache', 'famma_qa_l5n_probe_nocache', 10, 1 );
	add_action( 'template_redirect', 'famma_qa_l5n_probe_donotcache', PHP_INT_MAX );
}
