<?php
/**
 * FAMMA — page d'accueil.
 *
 * Compose les sections pilotées par le catalogue **autour** du contenu éditorial
 * de la page « Accueil », au lieu de le remplacer : les explications COD, la FAQ
 * et l'appel WhatsApp sont écrits par le propriétaire dans l'éditeur, et doivent
 * le rester. Un gabarit qui écrase le contenu de sa page transforme
 * l'administration en décor.
 *
 * L'ordre suit la maquette : héros, catégories, nouveautés, puis le contenu
 * rédigé, puis les raisons de choisir FAMMA, la newsletter, les avis et
 * l'appel WhatsApp.
 *
 * Chaque section pilotée par la donnée s'efface d'elle-même quand cette donnée
 * n'existe pas — voir inc/home.php.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="famma-container famma-home">

	<?php famma_child_home_hero(); ?>

	<?php famma_child_home_categories(); ?>

	<?php famma_child_home_new_products(); ?>

	<?php
	/*
	 * Le contenu de la page « Accueil ». `the_content` exécute les blocs et
	 * les shortcodes : c'est lui qui rend les sections rédigées par le
	 * propriétaire.
	 */
	while ( have_posts() ) :
		the_post();

		$famma_content = trim( (string) get_the_content() );

		if ( '' !== $famma_content ) :
			?>
			<section class="famma-home__editorial entry-content">
				<?php the_content(); ?>
			</section>
			<?php
		endif;
	endwhile;
	?>

	<?php famma_child_home_why(); ?>

	<?php famma_child_home_newsletter(); ?>

	<?php famma_child_home_reviews(); ?>

	<?php famma_child_shop_whatsapp_band(); ?>

</div>

<?php
get_footer();
