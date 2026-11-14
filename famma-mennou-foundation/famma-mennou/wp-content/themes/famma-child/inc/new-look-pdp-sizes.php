<?php
/**
 * Nouveau look — fiche produit, lot 50 : volet « Les tailles » (`pdp_sizes`).
 *
 * Un onglet WooCommerce de plus (`famma_sizes`) : avec `pdp_details` il devient
 * un volet, sinon c'est un onglet comme les autres. Contenu tiré du produit
 * affiché : ses tailles (`Product_Sizes`), le lien « autre taille » du lot 49
 * (si sa brique est cochée) et le tableau des mesures (`Product_Size_Chart`).
 * Sans rien de tout cela, pas d'onglet. Données déjà chargées avec le produit :
 * aucune requête de plus, sauf le produit lié du lien « autre taille ».
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pose l'onglet sur la fiche.
 *
 * @return void
 */
function famma_child_pdp_sizes_setup(): void {
	if ( function_exists( 'famma_child_pdp_part' ) && famma_child_pdp_part( 'pdp_sizes' ) ) {
		add_filter( 'woocommerce_product_tabs', 'famma_child_pdp_sizes_tab' );
	}
}
add_action( 'wp', 'famma_child_pdp_sizes_setup', 20 );

/**
 * Ajoute l'onglet « Les tailles », juste après la description.
 *
 * @param mixed $tabs Onglets existants.
 * @return mixed
 */
function famma_child_pdp_sizes_tab( $tabs ) {
	$product = wc_get_product();
	$title   = class_exists( '\Famma\Core\Product_Content' ) ? \Famma\Core\Product_Content::text( 'size_tab' ) : '';

	if ( ! is_array( $tabs ) || ! $product instanceof WC_Product || '' === $title || '' === famma_child_pdp_sizes_html( $product ) ) {
		return $tabs;
	}

	$tabs['famma_sizes'] = array(
		'title'    => $title,
		'priority' => 15,
		'callback' => 'famma_child_pdp_sizes_panel',
	);

	return $tabs;
}

/**
 * Contenu de l'onglet.
 *
 * @return void
 */
function famma_child_pdp_sizes_panel(): void {
	$product = wc_get_product();

	if ( $product instanceof WC_Product ) {
		echo famma_child_pdp_sizes_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chaque morceau est échappé dans famma_child_pdp_sizes_html().
	}
}

/**
 * HTML du volet, ou chaîne vide. Calculé une fois par produit et par page.
 *
 * @param WC_Product $product Produit affiché.
 * @return string
 */
function famma_child_pdp_sizes_html( WC_Product $product ): string {
	static $done = array();

	$id = $product->get_id();

	if ( isset( $done[ $id ] ) ) {
		return $done[ $id ];
	}

	$html  = '';
	$sizes = class_exists( '\Famma\Core\Product_Sizes' ) ? \Famma\Core\Product_Sizes::options( $product ) : array();
	$avail = class_exists( '\Famma\Core\Product_Content' ) ? \Famma\Core\Product_Content::text( 'size_avail' ) : '';

	if ( array() !== $sizes && '' !== $avail ) {
		$html .= '<p class="famma-sizes__avail">' . esc_html( str_replace( '{tailles}', implode( is_rtl() ? '، ' : ', ', $sizes ), $avail ) ) . '</p>';
	}

	if ( famma_child_pdp_part( 'pdp_altsize' ) && class_exists( '\Famma\Core\Product_Alt_Size' ) ) {
		$link = \Famma\Core\Product_Alt_Size::link( $product );

		if ( array() !== $link ) {
			// Même balisage que sous les tailles (lot 49) : TranslatePress réécrit le lien, pas la phrase.
			$html .= sprintf(
				'<p class="famma-sizes__alt"><a href="%1$s"><span %2$s>%3$s</span></a></p>',
				esc_url( $link['url'] ),
				famma_child_pdp_story_lang( $link['lang'] ),
				esc_html( $link['text'] )
			);
		}
	}

	$chart = class_exists( '\Famma\Core\Product_Size_Chart' ) ? \Famma\Core\Product_Size_Chart::chart( $product ) : array();

	if ( array() !== $chart ) {
		$html .= famma_child_pdp_sizes_table( $chart );
	}

	$done[ $id ] = $html;

	return $html;
}

/**
 * Tableau des mesures. Large : il défile seul dans son cadre, jamais la page.
 *
 * @param array{lang: string, head: array<int, string>, rows: array<int, array<int, string>>} $chart Tableau.
 * @return string
 */
function famma_child_pdp_sizes_table( array $chart ): string {
	$head = '';

	foreach ( $chart['head'] as $cell ) {
		$head .= '<th scope="col">' . esc_html( $cell ) . '</th>';
	}

	$body = '';

	foreach ( $chart['rows'] as $row ) {
		$body .= '<tr>';

		foreach ( $row as $index => $cell ) {
			$body .= 0 === $index ? '<th scope="row">' . esc_html( $cell ) . '</th>' : '<td>' . esc_html( $cell ) . '</td>';
		}

		$body .= '</tr>';
	}

	// Cadre atteignable au clavier : on peut le faire défiler avec les flèches.
	return sprintf(
		'<div class="famma-sizes__wrap" tabindex="0" role="region" aria-label="%1$s" %2$s><table class="famma-sizes__table"><thead><tr>%3$s</tr></thead><tbody>%4$s</tbody></table></div>',
		esc_attr( class_exists( '\Famma\Core\Product_Content' ) ? \Famma\Core\Product_Content::text( 'size_tab' ) : '' ),
		famma_child_pdp_story_lang( $chart['lang'] ),
		$head,
		$body
	);
}
