<?php
/**
 * Mesure de la barre de défilement sans mise en page forcée (PERF-24).
 *
 * Kadence imprime dans le pied de page un script qui lit
 * `document.documentElement.clientWidth` pour poser `--scrollbar-offset` sur
 * `html`. Cette lecture force le navigateur à mettre toute la page en forme
 * avant d'avoir fini de l'analyser : 176 ms (accueil) et 201 ms (fiche)
 * mesurés sur mobile au 25/09.
 *
 * Sur un écran tactile, la barre de défilement se superpose au contenu et ne
 * prend aucune place : la mesure vaut toujours 0. On l'écrit donc sans rien
 * lire. Avec une souris, la barre occupe une largeur réelle (15 px sous
 * Windows) dont dépend `--global-vw` (layout.css) : la mesure est gardée.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remplace le script de Kadence par la version qui ne mesure qu'avec une
 * souris. Appelé sur `wp`, une fois les hooks du parent posés.
 *
 * @return void
 */
function famma_child_swap_scrollbar_script(): void {
	if ( is_admin() || ! function_exists( 'famma_child_remove_parent_callback' ) ) {
		return;
	}

	famma_child_remove_parent_callback( 'wp_footer', 'action_add_scrollbar_offset_script' );
	add_action( 'wp_footer', 'famma_child_scrollbar_offset_script', 2 );
}
add_action( 'wp', 'famma_child_swap_scrollbar_script' );

/**
 * Pose `--scrollbar-offset` : 0 sans lecture sur écran tactile, mesuré sinon.
 *
 * Même emplacement et même priorité que le script d'origine, pour que la
 * variable soit posée avant les scripts du pied de page qui pourraient la lire.
 *
 * @return void
 */
function famma_child_scrollbar_offset_script(): void {
	wp_print_inline_script_tag(
		'(function(d){d.style.setProperty("--scrollbar-offset",matchMedia("(pointer:coarse)").matches?"0px":(innerWidth-d.clientWidth)+"px");}(document.documentElement));'
	);
}
