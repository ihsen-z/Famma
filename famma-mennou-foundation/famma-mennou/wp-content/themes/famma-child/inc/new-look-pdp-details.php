<?php
/**
 * Nouveau look — fiche produit, lot 45 : « Tout savoir avant de commander ».
 *
 * Brique `pdp_details`, valable pour toutes les fiches. Les onglets de
 * WooCommerce deviennent des volets `<details>` l'un sous l'autre, comme la
 * maquette :
 * - la description est découpée à chacun de ses intertitres de niveau 3 (un
 *   volet par intertitre) ; ce qui précède le premier reste visible
 *   au-dessus. Sans intertitre, la description entière forme un volet
 *   « Description », ouvert ;
 * - une question de la FAQ par volet ;
 * - puis les autres onglets (caractéristiques, avis, livraison et retours),
 *   chacun dans son volet, avec leur titre d'onglet.
 *
 * Rien n'est réécrit : c'est le contenu de chaque produit, rendu par les
 * mêmes fonctions que les onglets. Les volets gardent l'identifiant de leur
 * onglet (`tab-<clé>`) pour que les liens existants les ouvrent.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remplace les onglets par les volets quand la brique est servie.
 *
 * @return void
 */
function famma_child_pdp_details_setup(): void {
	if ( ! famma_child_pdp_part( 'pdp_details' ) ) {
		return;
	}

	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
	add_action( 'woocommerce_after_single_product_summary', 'famma_child_pdp_details', 10 );
}
add_action( 'wp', 'famma_child_pdp_details_setup', 20 );

/**
 * Sortie d'un rappel d'onglet, captée.
 *
 * @param string               $key Clé de l'onglet.
 * @param array<string, mixed> $tab Onglet.
 * @return string
 */
function famma_child_pdp_details_capture( string $key, array $tab ): string {
	$callback = $tab['famma_more_callback'] ?? ( $tab['callback'] ?? null );

	if ( ! is_callable( $callback ) ) {
		return '';
	}

	ob_start();
	call_user_func( $callback, $key, $tab );

	return trim( (string) ob_get_clean() );
}

/**
 * Découpe la description à ses intertitres de niveau 3.
 *
 * Le découpage ne se fait que si les H3 sont au premier niveau du contenu
 * (cas de l'éditeur classique et des blocs simples) : couper au milieu d'un
 * bloc imbriqué casserait le balisage. Sinon, la description reste entière.
 *
 * @param string $html Description rendue.
 * @return array{intro: string, parts: array<int, array{title: string, body: string}>}
 */
function famma_child_pdp_details_split( string $html ): array {
	$whole = array(
		'intro' => $html,
		'parts' => array(),
	);

	if ( false === stripos( $html, '<h3' ) || ! class_exists( 'DOMDocument' ) ) {
		return $whole;
	}

	$doc      = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$loaded   = $doc->loadHTML( '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="famma-root">' . $html . '</div></body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	$root = $loaded ? $doc->getElementById( 'famma-root' ) : null;

	if ( ! $root ) {
		return $whole;
	}

	$intro   = '';
	$parts   = array();
	$current = -1;

	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- API DOM.
	foreach ( iterator_to_array( $root->childNodes ) as $node ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- API DOM.
		if ( XML_ELEMENT_NODE === $node->nodeType && 'h3' === strtolower( $node->nodeName ) ) {
			$title = trim( wp_strip_all_tags( $doc->saveHTML( $node ) ) );

			if ( '' !== $title ) {
				$parts[] = array(
					'title' => $title,
					'body'  => '',
				);
				$current = count( $parts ) - 1;
				continue;
			}
		}

		$chunk = (string) $doc->saveHTML( $node );

		if ( $current < 0 ) {
			$intro .= $chunk;
		} else {
			$parts[ $current ]['body'] .= $chunk;
		}
	}

	if ( array() === $parts ) {
		return $whole;
	}

	return array(
		'intro' => trim( $intro ),
		'parts' => $parts,
	);
}

/**
 * Un volet.
 *
 * @param string $title Titre, en texte brut.
 * @param string $body  Contenu HTML déjà sûr (rendu par WordPress ou WooCommerce).
 * @param string $id    Identifiant facultatif.
 * @param bool   $open  Volet ouvert au chargement.
 * @return void
 */
function famma_child_pdp_details_item( string $title, string $body, string $id = '', bool $open = false ): void {
	if ( '' === trim( wp_strip_all_tags( $body, true ) ) && false === stripos( $body, '<img' ) && false === stripos( $body, '<form' ) ) {
		return;
	}

	printf(
		'<details class="famma-dts__item"%1$s%5$s><summary class="famma-dts__head"><span dir="auto">%2$s</span>%3$s</summary><div class="famma-dts__body">%4$s</div></details>',
		'' !== $id ? ' id="' . esc_attr( $id ) . '"' : '',
		esc_html( $title ),
		wp_kses( famma_child_icon( 'chevron', 'famma-dts__chev' ), famma_child_svg_allowed_html() ),
		$body, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sortie des rappels d'onglet de WooCommerce et du thème, déjà échappée par eux.
		$open ? ' open' : ''
	);
}

/**
 * Affiche les volets.
 *
 * @return void
 */
function famma_child_pdp_details(): void {
	$product = wc_get_product();

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$tabs = apply_filters( 'woocommerce_product_tabs', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- filtre de WooCommerce, comme son gabarit des onglets.

	if ( ! is_array( $tabs ) || array() === $tabs ) {
		return;
	}

	$title = class_exists( '\Famma\Core\Product_Content' ) ? \Famma\Core\Product_Content::text( 'det_title' ) : '';
	$intro = '';
	$items = array();

	foreach ( $tabs as $key => $tab ) {
		$key = (string) $key;

		if ( ! is_array( $tab ) ) {
			continue;
		}

		if ( 'famma_faq' === $key ) {
			foreach ( famma_child_product_faq_items( $product ) as $pair ) {
				$items[] = array( $pair['q'], '<p>' . esc_html( $pair['a'] ) . '</p>', '' );
			}
			continue;
		}

		$html = famma_child_pdp_details_capture( $key, $tab );

		if ( 'description' === $key ) {
			$split = famma_child_pdp_details_split( $html );

			if ( array() === $split['parts'] ) {
				$label   = isset( $tab['title'] ) ? (string) $tab['title'] : __( 'Description', 'famma-child' );
				$items[] = array( wp_strip_all_tags( $label ), $html, 'tab-description', true );
				continue;
			}

			$intro = $split['intro'];

			foreach ( $split['parts'] as $part ) {
				$items[] = array( $part['title'], $part['body'], '' );
			}
			continue;
		}

		$label   = isset( $tab['title'] ) ? (string) $tab['title'] : $key;
		$label   = (string) apply_filters( 'woocommerce_product_' . $key . '_tab_title', $label, $key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- filtre de WooCommerce.
		$items[] = array( wp_strip_all_tags( $label ), $html, 'tab-' . $key );
	}

	if ( '' === $intro && array() === $items ) {
		return;
	}
	?>
	<section class="famma-dts"<?php echo '' !== $title ? ' aria-labelledby="famma-dts-title"' : ''; ?>>
		<?php if ( '' !== $title ) : ?>
			<h2 class="famma-pdp-title" id="famma-dts-title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $intro ) : ?>
			<div class="famma-dts__intro" id="tab-description"><?php echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- description rendue par WooCommerce (`the_content`). ?></div>
		<?php endif; ?>
		<div class="famma-dts__list">
			<?php
			foreach ( $items as $item ) {
				famma_child_pdp_details_item( $item[0], $item[1], $item[2], ! empty( $item[3] ) );
			}
			?>
		</div>
	</section>
	<?php
}
