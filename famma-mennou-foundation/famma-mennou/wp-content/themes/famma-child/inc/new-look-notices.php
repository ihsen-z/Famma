<?php
/**
 * Nouveau look — messages du site au style FAMMA (brique `notices`, onglet
 * « Partout »).
 *
 * Présentation seulement : classe `famma-nl-notices` sur le corps, feuille
 * `new-look-notices.css` et script `new-look-notices.js` (bandeau qui se
 * ferme seul, erreur sous le champ à la commande). Le texte des messages vient
 * de famma-core (`Famma\Core\Site_Notices`). Case décochée : rien de chargé,
 * les messages restent ceux d'avant.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La brique est-elle servie ?
 *
 * @return bool
 */
function famma_child_notices_on(): bool {
	return function_exists( 'famma_child_new_look' ) && famma_child_new_look( 'notices' ) && class_exists( '\Famma\Core\Site_Notices' );
}

/**
 * Classe du corps, seule prise de la feuille.
 *
 * @param array<int, string> $classes Classes du corps.
 * @return array<int, string>
 */
function famma_child_notices_body_class( array $classes ): array {
	if ( famma_child_notices_on() ) {
		$classes[] = 'famma-nl-notices';
	}

	return $classes;
}
add_filter( 'body_class', 'famma_child_notices_body_class' );

/**
 * Feuille et script des messages, sur tout le site.
 *
 * @return void
 */
function famma_child_notices_enqueue(): void {
	if ( ! famma_child_notices_on() ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = '/assets/css/new-look-notices.css';
	$js  = '/assets/js/new-look-notices.js';

	if ( file_exists( $dir . $css ) ) {
		wp_enqueue_style(
			'famma-new-look-notices',
			$uri . $css,
			wp_style_is( 'famma-layout', 'registered' ) ? array( 'famma-layout' ) : array(),
			(string) filemtime( $dir . $css )
		);
	}

	if ( file_exists( $dir . $js ) ) {
		wp_enqueue_script(
			'famma-new-look-notices',
			$uri . $js,
			array( 'jquery' ),
			(string) filemtime( $dir . $js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script( 'famma-new-look-notices', 'fammaNotices', \Famma\Core\Site_Notices::strings() );
	}
}
add_action( 'wp_enqueue_scripts', 'famma_child_notices_enqueue', 25 );
