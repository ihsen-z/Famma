<?php
/**
 * FAMMA — fiche produit.
 *
 * Surcharge du gabarit `woocommerce/templates/single-product.php` pour poser
 * le conteneur de la maquette et le fil d'Ariane, qui n'était rendu nulle part
 * sur la fiche (constaté au rendu : zéro `.woocommerce-breadcrumb`).
 *
 * Tous les hooks du gabarit d'origine sont conservés. Seule l'enveloppe de
 * contenu du thème parent est court-circuitée : `header.php` a déjà ouvert le
 * conteneur principal, et la laisser produirait un second `<main>`.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

/** Hook conservé : d'autres extensions s'y accrochent. */
do_action( 'woocommerce_before_main_content' );
?>

<div class="famma-container famma-product">

	<?php woocommerce_breadcrumb(); ?>

	<?php
	while ( have_posts() ) :
		the_post();
		wc_get_template_part( 'content', 'single-product' );
	endwhile;
	?>

</div>

<?php
/** Hook conservé. */
do_action( 'woocommerce_after_main_content' );

/*
 * `woocommerce_sidebar` n'est pas appelé : la fiche produit de la maquette
 * n'a pas de colonne latérale, et une barre latérale vide ajouterait une
 * gouttière fantôme à droite du contenu.
 */

get_footer( 'shop' );
