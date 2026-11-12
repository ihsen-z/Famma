<?php
/**
 * Retouches de l'accueil issues de la maquette, une case chacune :
 *
 * - home_facts   : trois chiffres sous les étapes « Vous payez à la livraison » ;
 * - home_why     : « Pourquoi FAMMA » remonte juste après ce bloc ;
 * - hide_band : le bloc « Une question sur un produit ? » quitte l'accueil ;
 * - cat_icons    : icône au trait d'un rayon sans miniature ;
 * - wa_ring      : trois ondes sur le bouton WhatsApp flottant (feuille seule) ;
 * - acct_mobile : icône du compte dans l'en-tête du téléphone (partout).
 *
 * Case décochée : aucune de ces fonctions ne change un octet de la page.
 *
 * @package FammaChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Marque posée après le bloc « Vous payez à la livraison » pour y couper
 * le contenu de la page d'accueil.
 */
const FAMMA_CHILD_WHY_SPLIT = '<!--famma-why-split-->';

/**
 * Une brique de l'accueil est-elle servie sur cette page ?
 *
 * @param string $part Clé de brique.
 * @return bool
 */
function famma_child_home_part( string $part ): bool {
	return function_exists( 'famma_child_new_look_parts' ) && in_array( $part, famma_child_new_look_parts(), true );
}

/**
 * Le bloc rendu est-il le groupe « Vous payez à la livraison » de l'accueil ?
 *
 * @param array<string, mixed> $block Bloc analysé.
 * @return bool
 */
function famma_child_home_is_how( array $block ): bool {
	$class = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';

	return is_front_page() && (bool) preg_match( '/(^|\s)famma-how(\s|$)/', $class );
}

/**
 * Trois chiffres sous les étapes, lus dans FAMMA → Pages → Accueil.
 *
 * La valeur finale est écrite par le serveur : sans script, ou pour un
 * visiteur qui demande moins de mouvement, rien ne se compte et tout est juste.
 *
 * @param string               $content HTML du groupe.
 * @param array<string, mixed> $block   Bloc analysé.
 * @return string
 */
function famma_child_home_facts( string $content, array $block ): string {
	if ( ! famma_child_home_is_how( $block ) || ! famma_child_home_part( 'home_facts' ) ) {
		return $content;
	}

	$facts = '';
	for ( $i = 1; $i <= 3; $i++ ) {
		$value = famma_child_home_text( 'facts_' . $i . '_value', '' );
		$label = famma_child_home_text( 'facts_' . $i . '_label', '' );

		if ( '' === $value ) {
			continue;
		}

		$facts .= sprintf(
			'<div class="famma-nl-fact"><b data-famma-count aria-hidden="true">%1$s</b><span aria-hidden="true">%2$s</span><span class="screen-reader-text">%3$s</span></div>',
			esc_html( $value ),
			esc_html( $label ),
			esc_html( trim( $value . ' ' . $label ) )
		);
	}

	$end = strrpos( $content, '</' );

	if ( '' === $facts || false === $end ) {
		return $content;
	}

	return substr_replace( $content, '<div class="famma-nl-facts">' . $facts . '</div>', $end, 0 );
}
add_filter( 'render_block_core/group', 'famma_child_home_facts', 9, 2 );

/**
 * Marque la fin du bloc « Vous payez à la livraison » : front-page.php y
 * coupe le contenu pour y glisser « Pourquoi FAMMA ».
 *
 * @param string               $content HTML du groupe.
 * @param array<string, mixed> $block   Bloc analysé.
 * @return string
 */
function famma_child_home_why_mark( string $content, array $block ): string {
	if ( ! famma_child_home_is_how( $block ) || ! famma_child_home_part( 'home_why' ) || ! in_the_loop() ) {
		return $content;
	}

	return $content . FAMMA_CHILD_WHY_SPLIT;
}
add_filter( 'render_block_core/group', 'famma_child_home_why_mark', 20, 2 );

/**
 * Contenu de la page d'accueil, coupé après le bloc « Vous payez à la
 * livraison » quand la case home_why est cochée.
 *
 * @param string $html Contenu rendu (`the_content`).
 * @return array<int, string> Une partie, ou deux si la marque est présente.
 */
function famma_child_home_editorial_parts( string $html ): array {
	if ( false === strpos( $html, FAMMA_CHILD_WHY_SPLIT ) ) {
		return array( $html );
	}

	return explode( FAMMA_CHILD_WHY_SPLIT, $html, 2 );
}

/**
 * Le bloc « Une question sur un produit ? » reste-t-il sur l'accueil ?
 *
 * @return bool
 */
function famma_child_home_show_band(): bool {
	return ! famma_child_home_part( 'hide_band' );
}

/**
 * Icône au trait d'un rayon sans miniature, choisie dans Produits → Catégories.
 *
 * @param int $term_id Identifiant du rayon.
 * @return string SVG prêt à imprimer, ou chaîne vide (l'étoile reste).
 */
function famma_child_home_cat_icon( int $term_id ): string {
	if ( ! famma_child_home_part( 'cat_icons' ) || ! class_exists( '\Famma\Core\Cat_Icons' ) ) {
		return '';
	}

	$key = \Famma\Core\Cat_Icons::for_term( $term_id );

	return '' === $key ? '' : \Famma\Core\Cat_Icons::svg( $key, 'famma-nl-cat-icon' );
}

/**
 * Feuille des retouches de l'accueil, chargée seulement si une case la sert.
 *
 * @return void
 */
function famma_child_enqueue_home_extras(): void {
	$used = array_intersect( array( 'home_facts', 'home_why', 'cat_icons', 'wa_ring' ), famma_child_new_look_parts() );
	$path = get_stylesheet_directory() . '/assets/css/new-look-home-extras.css';

	if ( ! $used || ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_style(
		'famma-new-look-home-extras',
		get_stylesheet_directory_uri() . '/assets/css/new-look-home-extras.css',
		array( 'famma-new-look' ),
		(string) filemtime( $path )
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_home_extras', 24 );

/**
 * Icône du compte dans l'en-tête du téléphone, sur tout le site.
 *
 * Les deux moitiés de la bascule restent exclusives : le lien du panneau
 * se range quand l'icône apparaît, le compte existe toujours une seule fois.
 *
 * Logo (173 px) + trois boutons de 48 px : sous 430 px, l'en-tête resserre
 * ses marges et ses écarts pour ne pas déborder (360 px = Android courant).
 * Sous 360 px, la place manque : le compte reste dans le menu.
 *
 * @return void
 */
function famma_child_new_look_mobile_account(): void {
	if ( ! famma_child_new_look( 'acct_mobile' ) || ! wp_style_is( 'famma-layout', 'enqueued' ) ) {
		return;
	}

	wp_add_inline_style(
		'famma-layout',
		'@media (min-width:360px) and (max-width:999.98px){.famma-header__action--account{display:inline-flex}.famma-header__panel-account{display:none}}'
		. '@media (min-width:360px) and (max-width:429.98px){.famma-container.famma-header__inner{--gutter:14px;--chrome-header-gap:4px}.famma-header__actions{gap:0}}'
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_new_look_mobile_account', 22 );
