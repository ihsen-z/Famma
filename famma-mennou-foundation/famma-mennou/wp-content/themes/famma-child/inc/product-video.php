<?php
/**
 * FAMMA — section vidéo de la fiche produit.
 *
 * Bloc facultatif, rendu juste sous la galerie et le résumé, avant la bande
 * d'avantages (priorité 5, celle-ci est à 6). Il n'apparaît que si le
 * propriétaire a renseigné une adresse de vidéo sur l'écran produit : titre,
 * description et vidéo viennent tous du back-office (`Famma\Core\Product_Video`),
 * rien n'est écrit en dur ici (§60).
 *
 * Ce fichier ne contient que de la présentation : la donnée et la fabrication
 * du lecteur restent dans le plugin (règle 6).
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Affiche la section vidéo publicitaire sous la galerie.
 *
 * Trois portes de sortie silencieuses plutôt qu'un bloc à moitié rempli :
 * pas de produit, plugin absent, ou aucune vidéo enregistrée. Un lecteur vide
 * — adresse d'un fournisseur qu'oEmbed ne reconnaît pas — ferme aussi la
 * section : mieux vaut rien qu'un cadre blanc avec un titre orphelin.
 *
 * @return void
 */
function famma_child_product_video(): void {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	if ( ! class_exists( '\Famma\Core\Product_Video' ) ) {
		return;
	}

	$video = \Famma\Core\Product_Video::instance()->data( $product );

	if ( array() === $video ) {
		return;
	}

	$player = \Famma\Core\Product_Video::instance()->player( $video['url'] );

	if ( '' === $player ) {
		return;
	}

	$has_text = '' !== $video['title'] || '' !== $video['description'];
	?>
	<section class="famma-product-video<?php echo $has_text ? '' : ' famma-product-video--media-only'; ?>">
		<?php if ( $has_text ) : ?>
			<div class="famma-product-video__body">
				<?php if ( '' !== $video['title'] ) : ?>
					<h2 class="famma-product-video__title"><?php echo esc_html( $video['title'] ); ?></h2>
				<?php endif; ?>
				<?php if ( '' !== $video['description'] ) : ?>
					<div class="famma-product-video__text">
						<?php echo wp_kses_post( wpautop( $video['description'] ) ); ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="famma-product-video__media">
			<?php
			/*
			 * Balisage produit par WordPress : soit un `<video>` construit dans
			 * le plugin avec `esc_url()`, soit une intégration oEmbed d'un
			 * fournisseur autorisé. L'adresse saisie n'est jamais imprimée
			 * telle quelle.
			 */
			echo $player; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
	</section>
	<?php
}
add_action( 'woocommerce_after_single_product_summary', 'famma_child_product_video', 5 );
