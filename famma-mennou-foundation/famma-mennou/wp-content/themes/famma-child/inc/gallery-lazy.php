<?php
/**
 * Galerie produit : seules la première photo et les vignettes au chargement.
 *
 * Audit perf du 25/09 (PERF-04) : les 10 photos en 768 px de la galerie
 * partaient dès les 330 premières millisecondes, avec l'image LCP. Flexslider
 * aligne les diapos dans une bande dont le navigateur ne sait pas différer le
 * contenu : `loading="lazy"` n'y change rien.
 *
 * Les photos 2 et suivantes reçoivent donc une image de remplacement SVG aux
 * mêmes proportions (aucun saut de mise en page, Flexslider mesure la bonne
 * hauteur) ; leurs vraies sources attendent dans `data-famma-src` et
 * `data-famma-srcset`, rétablies après `load` ou au premier geste dans la
 * galerie. WooCommerce occupe déjà `data-src` (image plein écran de la
 * visionneuse) : le nom est distinct pour ne pas la casser.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Diffère une photo de la galerie, sauf la photo principale.
 *
 * @param string $html Balise de la diapo produite par WooCommerce.
 * @return string
 */
function famma_child_gallery_defer_image( string $html ): string {
	// La photo principale (classe wp-post-image) est l'élément LCP.
	if ( false !== strpos( $html, 'wp-post-image' ) || false !== strpos( $html, 'data-famma-src' ) ) {
		return $html;
	}

	if ( ! preg_match( '/<img\b[^>]*\swidth="(\d+)"[^>]*\sheight="(\d+)"/', $html, $size ) ) {
		return $html;
	}

	$placeholder = 'data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 ' . (int) $size[1] . ' ' . (int) $size[2] . '%27%3E%3C/svg%3E';

	return (string) preg_replace_callback(
		'/<img\b[^>]*>/',
		static function ( array $img ) use ( $placeholder ): string {
			$tag = (string) preg_replace( '/\s(src|srcset)="/', ' data-famma-$1="', $img[0] );
			return '<img src="' . esc_attr( $placeholder ) . '"' . substr( $tag, 4 );
		},
		$html,
		1
	);
}
add_filter( 'woocommerce_single_product_image_thumbnail_html', 'famma_child_gallery_defer_image', 20 );

/**
 * Rétablit les vraies sources : après `load`, ou plus tôt si le visiteur
 * touche la galerie (glisser, flèche, vignette, clavier).
 *
 * @return void
 */
function famma_child_gallery_hydrate_script(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	wp_add_inline_script(
		'wc-single-product',
		'(function(){var done=false;function h(){if(done){return;}done=true;document.querySelectorAll(".woocommerce-product-gallery img[data-famma-src]").forEach(function(i){if(i.dataset.fammaSrcset){i.srcset=i.dataset.fammaSrcset;}i.src=i.dataset.fammaSrc;i.removeAttribute("data-famma-src");i.removeAttribute("data-famma-srcset");});}'
		. 'if("complete"===document.readyState){h();}else{addEventListener("load",h,{once:true});}'
		. '["pointerdown","touchstart","keydown"].forEach(function(t){document.addEventListener(t,function(e){if(e.target.closest&&e.target.closest(".woocommerce-product-gallery")){h();}},{passive:true,capture:true});});})();'
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_gallery_hydrate_script', 30 );
