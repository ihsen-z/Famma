<?php
/**
 * Nouveau look — fiche produit, lot 44 : galerie et cadre de la maquette.
 *
 * Deux briques, valables pour TOUTES les fiches (aucun produit n'est nommé) :
 * - `pdp_gallery` : la galerie WooCommerce reste (glisser, loupe plein écran,
 *   variantes) ; la feuille et le script l'habillent comme la maquette
 *   (cadre arrondi, compteur, flèches et zoom au survol, cadre orange qui
 *   glisse d'une vignette à l'autre, entrée animée) ;
 * - `pdp_frame` : barre de lecture, logo qui cligne, fil d'Ariane court sur
 *   téléphone, titres de section à traits orange, carte WhatsApp verte, trois
 *   produits similaires.
 *
 * Aucune requête de plus : tout se lit dans le produit déjà chargé.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La brique de la fiche est-elle servie sur cette page ?
 *
 * @param string $part Clé de brique.
 * @return bool
 */
function famma_child_pdp_part( string $part ): bool {
	return function_exists( 'is_product' ) && is_product() && in_array( $part, famma_child_new_look_parts(), true );
}

/**
 * Charge la feuille et le script du lot 44.
 *
 * Feuille derrière `famma-product` (elle l'ajuste), script différé en pied de
 * page, sans jQuery : il ne fait que lire la galerie déjà construite.
 *
 * @return void
 */
function famma_child_pdp_enqueue(): void {
	if ( ! famma_child_pdp_part( 'pdp_gallery' ) && ! famma_child_pdp_part( 'pdp_frame' ) ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	if ( file_exists( $dir . '/assets/css/new-look-pdp.css' ) ) {
		wp_enqueue_style(
			'famma-new-look-pdp',
			$uri . '/assets/css/new-look-pdp.css',
			wp_style_is( 'famma-product', 'registered' ) ? array( 'famma-product' ) : array( 'famma-layout' ),
			(string) filemtime( $dir . '/assets/css/new-look-pdp.css' )
		);
	}

	if ( famma_child_pdp_part( 'pdp_gallery' ) && file_exists( $dir . '/assets/js/new-look-pdp.js' ) ) {
		wp_enqueue_script(
			'famma-new-look-pdp',
			$uri . '/assets/js/new-look-pdp.js',
			array(),
			(string) filemtime( $dir . '/assets/js/new-look-pdp.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'famma_child_pdp_enqueue', 24 );

/**
 * Fil d'Ariane : chaque étape et chaque séparateur dans leur propre balise.
 *
 * La maquette retire le nom du produit sur téléphone (il est déjà écrit sur
 * la photo). WooCommerce l'imprime en texte nu, sans prise pour la feuille :
 * les arguments `before` / `after` / `delimiter` lui en donnent une, sans
 * copier son gabarit.
 *
 * @param mixed $defaults Arguments du fil d'Ariane.
 * @return mixed
 */
function famma_child_pdp_breadcrumb( $defaults ) {
	if ( ! is_array( $defaults ) || ! famma_child_pdp_part( 'pdp_frame' ) ) {
		return $defaults;
	}

	$defaults['delimiter'] = '<span class="famma-crumb-sep" aria-hidden="true">/</span>';
	$defaults['before']    = '<span class="famma-crumb">';
	$defaults['after']     = '</span>';

	return $defaults;
}
add_filter( 'woocommerce_breadcrumb_defaults', 'famma_child_pdp_breadcrumb', 20 );

/**
 * Trois produits similaires, sur trois colonnes, comme la maquette.
 *
 * @param mixed $args Arguments de WooCommerce.
 * @return mixed
 */
function famma_child_pdp_related_args( $args ) {
	if ( ! is_array( $args ) || ! famma_child_pdp_part( 'pdp_frame' ) ) {
		return $args;
	}

	$args['posts_per_page'] = 3;
	$args['columns']        = 3;

	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'famma_child_pdp_related_args', 20 );
