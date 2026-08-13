<?php
/**
 * FAMMA — thème enfant (parent : Kadence).
 *
 * Présentation uniquement. Aucune logique métier ici : elle vit dans le
 * plugin famma-core (§27). Ce fichier doit rester court ; s'il grossit,
 * c'est le signe qu'une règle métier s'y est glissée par erreur.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

const FAMMA_CHILD_VERSION = '0.1.0';

/**
 * Charge la feuille du parent, les jetons du design system, puis la feuille enfant.
 *
 * La version de chaque feuille utilise filemtime() pour invalider le cache
 * navigateur à chaque modification réelle du fichier (règle 9).
 */
function famma_child_enqueue_styles(): void {
	$child_dir = get_stylesheet_directory();
	$child_uri = get_stylesheet_directory_uri();

	wp_enqueue_style(
		'kadence-parent',
		get_template_directory_uri() . '/style.css',
		array(),
		(string) wp_get_theme( get_template() )->get( 'Version' )
	);

	$tokens = $child_dir . '/assets/css/tokens.css';
	if ( file_exists( $tokens ) ) {
		wp_enqueue_style(
			'famma-tokens',
			$child_uri . '/assets/css/tokens.css',
			array( 'kadence-parent' ),
			(string) filemtime( $tokens )
		);
	}

	wp_enqueue_style(
		'famma-child',
		get_stylesheet_uri(),
		array( 'famma-tokens' ),
		(string) filemtime( $child_dir . '/style.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_styles', 20 );

/**
 * Déclare les capacités du thème.
 *
 * Chargé sur `after_setup_theme` : c'est le point d'accroche prévu par
 * WordPress pour les déclarations de support.
 */
function famma_child_setup(): void {
	load_child_theme_textdomain( 'famma-child', get_stylesheet_directory() . '/languages' );

	// Galerie produit WooCommerce — zoom/lightbox/slider natifs (§30).
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'famma_child_setup' );
