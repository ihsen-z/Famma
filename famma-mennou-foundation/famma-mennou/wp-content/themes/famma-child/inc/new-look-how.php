<?php
/**
 * Nouveau look — brique `how` : bloc « Comment commander » en bas de la
 * boutique et des rayons (section #how de la maquette boutique du 06/11).
 *
 * Même balisage que le bloc « Vous payez à la livraison » de l'accueil
 * (`.famma-how`, liste `.famma-steps`) : la feuille new-look.css lui donne le
 * même dessin que la brique journey — bande marine, route, étapes cochées au
 * défilement, CSS seul. Rendu en HTML serveur, dans une liste ordonnée ; en
 * arabe, l'ordre suit le sens de lecture.
 *
 * Les textes viennent de famma-core (FAMMA → Pages → Boutique), en FR et AR.
 * Titre vidé : le bloc disparaît.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Affiche le bloc « Comment commander ».
 *
 * @return void
 */
function famma_child_new_look_how(): void {
	if ( ! in_array( 'how', famma_child_new_look_parts(), true ) || ! class_exists( '\Famma\Core\Shop_Content' ) ) {
		return;
	}

	$title = \Famma\Core\Shop_Content::text( 'how_title' );
	$steps = \Famma\Core\Shop_Content::steps();

	if ( '' === $title || array() === $steps ) {
		return;
	}
	?>
	<section class="famma-how famma-how--journey" aria-labelledby="famma-how-title">
		<h2 class="famma-section__title" id="famma-how-title"><?php echo esc_html( $title ); ?></h2>
		<ol class="famma-steps">
			<?php foreach ( $steps as $step ) : ?>
				<li><span><b><?php echo esc_html( $step['title'] ); ?></b><?php echo '' !== $step['text'] ? ' ' . esc_html( $step['text'] ) : ''; ?></span></li>
			<?php endforeach; ?>
		</ol>
	</section>
	<?php
}
add_action( 'famma_child_after_shop_results', 'famma_child_new_look_how' );
