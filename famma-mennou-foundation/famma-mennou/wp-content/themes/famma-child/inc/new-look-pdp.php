<?php
/**
 * Nouveau look — fiche produit, lots 44 et 45 : socle commun.
 *
 * Lot 44 : galerie et cadre de la maquette (ce fichier). Lot 45 : « Quand
 * payez-vous ? » (`inc/new-look-pdp-pay.php`) et volets « Tout savoir avant
 * de commander » (`inc/new-look-pdp-details.php`), qui s'appuient sur
 * `famma_child_pdp_part()` et sur la feuille chargée ici.
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
 * Charge la feuille et les scripts des lots 44 à 47.
 *
 * Une seule feuille pour toutes les briques de la fiche, derrière
 * `famma-product` (elle l'ajuste). Scripts différés en pied de page, sans
 * jQuery, chacun seulement avec sa brique : la galerie (lot 44), la bande
 * « Quand payez-vous ? » et l'ouverture des volets par ancre (lot 45), le
 * récit et « Ce qu'il y a dedans » (lot 46), la boîte d'achat (lot 47).
 *
 * @return void
 */
function famma_child_pdp_enqueue(): void {
	$parts = array_filter( array( 'pdp_gallery', 'pdp_frame', 'pdp_pay', 'pdp_details', 'pdp_story', 'pdp_anatomy', 'pdp_buy', 'pdp_form', 'pdp_points', 'pdp_dock', 'pdp_altsize', 'pdp_labels' ), 'famma_child_pdp_part' );

	if ( array() === $parts ) {
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

	$scripts = array(
		'new-look-pdp'          => in_array( 'pdp_gallery', $parts, true ),
		'new-look-pdp-sections' => (bool) array_intersect( array( 'pdp_pay', 'pdp_details' ), $parts ),
		'new-look-pdp-story'    => function_exists( 'famma_child_pdp_story_wanted' ) && famma_child_pdp_story_wanted(),
		'new-look-pdp-buy'      => (bool) array_intersect( array( 'pdp_buy', 'pdp_form', 'pdp_dock', 'pdp_altsize', 'pdp_labels' ), $parts ),
	);

	// Copies allégées (`assets/js/min/`, scripts/build-pdp-min.py), sources commentées avec SCRIPT_DEBUG.
	$light = ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );

	foreach ( $scripts as $name => $wanted ) {
		$file = '/assets/js/' . ( $light && file_exists( $dir . '/assets/js/min/' . $name . '.js' ) ? 'min/' : '' ) . $name . '.js';

		if ( ! $wanted || ! file_exists( $dir . $file ) ) {
			continue;
		}

		wp_enqueue_script(
			'famma-' . $name,
			$uri . $file,
			array(),
			(string) filemtime( $dir . $file ),
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
