<?php
/**
 * FAMMA — archive produit (la boutique).
 *
 * Surcharge du gabarit `woocommerce/templates/archive-product.php` pour poser
 * la mise en page de la maquette : fil d'Ariane, titre + tri + bascule
 * d'affichage, colonne de filtres, bandeau de promesses, grille, pagination.
 *
 * **Tous les hooks du gabarit d'origine sont conservés**, aux mêmes endroits.
 * En retirer un couperait sans prévenir les extensions qui s'y accrochent —
 * c'est la panne la plus difficile à diagnostiquer sur une boutique.
 *
 * Deux hooks sont neutralisés à dessein et remplacés par notre propre
 * enveloppe : `woocommerce_before_main_content` et `woocommerce_after_main_content`,
 * qui n'existent que pour ouvrir et fermer le conteneur du thème. Le nôtre est
 * déjà ouvert par `header.php`.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

/*
 * L'enveloppe de contenu du thème parent est court-circuitée : `header.php`
 * a déjà ouvert le conteneur principal. La laisser produirait un second
 * `<main>` et un conteneur imbriqué (constaté au rendu avant surcharge).
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

/*
 * Titre d'archive du thème parent : retiré ici, et non depuis un `add_action`
 * sur `wp`. Vérifié au rendu — le détachement fait plus tôt ne prenait pas, et
 * la page sortait avec deux `<h1> Boutique`. Fait juste avant le
 * déclenchement du hook, le retrait est certain.
 *
 * Notre gabarit rend le titre plus bas, sur la même ligne que le tri comme
 * dans la maquette.
 */
if ( function_exists( 'famma_child_remove_parent_callback' ) ) {
	famma_child_remove_parent_callback( 'woocommerce_before_main_content', 'output_product_above_title' );

	/*
	 * Le conteneur de contenu de Kadence rend AUSSI l'en-tête d'archive, donc
	 * un second `<h1> Boutique` hors de notre barre d'outils. Sonde posée à
	 * l'exécution pour l'établir : `output_product_above_title` était bien
	 * détaché, et le titre venait de `output_content_wrapper`.
	 *
	 * Il est donc retiré ici — uniquement sur les archives produit, où le
	 * gabarit fournit sa propre mise en page. Le panier, la commande et le
	 * compte gardent l'enveloppe du parent : ils s'appuient dessus.
	 *
	 * `header.php` prend le relais du `<main>` : voir famma_child_content_tag().
	 */
	famma_child_remove_parent_callback( 'woocommerce_before_main_content', 'output_content_wrapper' );
	famma_child_remove_parent_callback( 'woocommerce_after_main_content', 'output_content_wrapper_end' );
}

/** Hook conservé : d'autres extensions s'y accrochent. */
do_action( 'woocommerce_before_main_content' );
?>

<div class="famma-container famma-shop">

	<?php woocommerce_breadcrumb(); ?>

	<div class="famma-shop__head">
		<div class="famma-shop__titles">
			<?php
			/*
			 * Titre rendu sans passer par `woocommerce_show_page_title` :
			 * Kadence met ce filtre à `false` pour poser le sien ailleurs, et
			 * le réactiver ajouterait en plus l'en-tête d'archive natif de
			 * WooCommerce. Constaté au rendu : trois `<h1>` sur la page. Ici
			 * le titre est rendu une fois, à l'emplacement de la maquette —
			 * sur la même ligne que le tri.
			 */
			?>
			<h1 class="famma-shop__title"><?php woocommerce_page_title(); ?></h1>

			<?php
			/*
			 * Le sous-titre est la description de la catégorie, saisie par le
			 * propriétaire. Aucun texte de remplissage : sans description,
			 * la ligne n'existe pas.
			 */
			do_action( 'woocommerce_archive_description' );
			?>
		</div>

		<?php famma_child_shop_toolbar(); ?>
	</div>

	<div class="famma-shop__layout">

		<?php famma_child_shop_sidebar(); ?>

		<div class="famma-shop__results">

			<?php famma_child_shop_promises(); ?>

			<?php
			/*
			 * `woocommerce_shop_loop_header` porte le compte de résultats et,
			 * historiquement, le tri. Le tri ayant été remonté dans la barre
			 * d'outils, on le retire ici pour ne pas l'afficher deux fois.
			 */
			remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

			do_action( 'woocommerce_shop_loop_header' );

			if ( woocommerce_product_loop() ) {

				/** Hook conservé — filtres actifs, compte de résultats, notices. */
				do_action( 'woocommerce_before_shop_loop' );

				woocommerce_product_loop_start();

				if ( wc_get_loop_prop( 'total' ) ) {
					while ( have_posts() ) {
						the_post();

						/** Hook conservé. */
						do_action( 'woocommerce_shop_loop' );

						wc_get_template_part( 'content', 'product' );
					}
				}

				woocommerce_product_loop_end();

				/** Hook conservé — pagination. */
				do_action( 'woocommerce_after_shop_loop' );

			} else {

				/** Hook conservé — état vide. */
				do_action( 'woocommerce_no_products_found' );

			}
			?>

		</div>
	</div>

	<?php famma_child_shop_whatsapp_band(); ?>

</div>

<?php
/** Hook conservé. */
do_action( 'woocommerce_after_main_content' );

/*
 * `woocommerce_sidebar` n'est pas appelé : la colonne latérale de la boutique
 * est rendue plus haut, dans la grille, et non collée après le contenu.
 */

get_footer( 'shop' );
