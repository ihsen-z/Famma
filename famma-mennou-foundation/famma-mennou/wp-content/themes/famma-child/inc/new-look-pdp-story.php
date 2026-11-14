<?php
/**
 * Nouveau look — fiche produit, lot 46 : récit et « Ce qu'il y a dedans ».
 *
 * Deux briques, valables pour toutes les fiches, qui ne s'affichent que sur
 * les produits remplis (Produits → Modifier → onglet « Fiche FAMMA », lu par
 * `\Famma\Core\Product_Story`) :
 * - `pdp_story` : bande marine sous la boîte d'achat, le récit en grand dont
 *   les mots s'allument au défilement, puis les boutons de situation ;
 * - `pdp_anatomy` : photo à repères qui reste en place et zoome sur la pièce
 *   dont parle la liste.
 *
 * Posées sous le résumé, avant la vidéo (5) et « Quand payez-vous ? » (6),
 * dans l'ordre de la maquette. Les textes sont ceux du produit, dans les deux
 * langues : TranslatePress ne les retraduit pas (`data-no-translation`).
 * Aucune requête de plus : méta et photos du produit sont déjà chargées.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Produit de la page, si la brique est servie et ses données lisibles.
 *
 * @param string $part Clé de brique.
 * @return WC_Product|null
 */
function famma_child_pdp_story_product( string $part ): ?WC_Product {
	if ( ! famma_child_pdp_part( $part ) || ! class_exists( '\Famma\Core\Product_Story' ) ) {
		return null;
	}

	$product = wc_get_product();

	return $product instanceof WC_Product ? $product : null;
}

/**
 * Le script du lot 46 sert-il sur cette fiche ?
 *
 * Seulement si l'une des deux briques est servie ET que le produit est
 * rempli : une fiche sans récit ni anatomie ne charge rien de plus. Méta
 * déjà en cache (requête principale), aucune requête de plus.
 *
 * @return bool
 */
function famma_child_pdp_story_wanted(): bool {
	if ( ! class_exists( '\Famma\Core\Product_Story' ) || ! function_exists( 'is_product' ) || ! is_product() ) {
		return false;
	}

	$product = wc_get_product( get_queried_object_id() );

	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	$story = famma_child_pdp_part( 'pdp_story' ) ? \Famma\Core\Product_Story::story( $product ) : array();

	return ( array() !== $story && array() !== $story['parts'] )
		|| ( famma_child_pdp_part( 'pdp_anatomy' ) && array() !== \Famma\Core\Product_Story::anatomy( $product ) );
}

/**
 * Attributs de langue d'un bloc écrit dans une langue donnée.
 *
 * @param string $lang `fr` ou `ar`.
 * @return string Attributs déjà échappés.
 */
function famma_child_pdp_story_lang( string $lang ): string {
	return sprintf( 'lang="%1$s" dir="%2$s" data-no-translation', esc_attr( $lang ), 'ar' === $lang ? 'rtl' : 'ltr' );
}

/**
 * Bande du récit.
 *
 * @return void
 */
function famma_child_pdp_story(): void {
	$product = famma_child_pdp_story_product( 'pdp_story' );
	$story   = $product ? \Famma\Core\Product_Story::story( $product ) : array();

	if ( array() === $story || array() === $story['parts'] ) {
		return;
	}

	$svg = famma_child_svg_allowed_html();
	$txt = '';

	// Collé, sans blanc de gabarit : les espaces sont ceux du récit saisi.
	foreach ( $story['parts'] as $part ) {
		$txt .= $part['hot'] ? '<span class="famma-story__hot">' . esc_html( $part['text'] ) . '</span>' : esc_html( $part['text'] );
	}
	?>
	<div class="famma-story" <?php echo famma_child_pdp_story_lang( $story['lang'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par famma_child_pdp_story_lang(). ?>>
		<div class="famma-story__wrap">
			<p class="famma-story__txt"><?php echo $txt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chaque morceau est passé par esc_html() ci-dessus. ?></p>
			<?php if ( array() !== $story['chips'] ) : ?>
				<div class="famma-story__chips" role="group" aria-label="<?php echo esc_attr( \Famma\Core\Product_Content::text( 'story_chips' ) ); ?>">
					<?php foreach ( $story['chips'] as $chip ) : ?>
						<button class="famma-story__chip" type="button" aria-pressed="false" data-phrase="<?php echo esc_attr( $chip['phrase'] ); ?>">
							<?php echo wp_kses( '' !== $chip['icon'] ? famma_child_icon( $chip['icon'] ) : '', $svg ); ?>
							<?php echo esc_html( $chip['label'] ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( array() !== $story['chips'] || '' !== $story['start'] ) : ?>
				<p class="famma-story__echo" aria-live="polite" data-pick="<?php echo esc_attr( $story['pick'] ); ?>"><?php echo esc_html( $story['start'] ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
add_action( 'woocommerce_after_single_product_summary', 'famma_child_pdp_story', 3 );

/**
 * Bloc « Ce qu'il y a dedans ».
 *
 * @return void
 */
function famma_child_pdp_anatomy(): void {
	$product = famma_child_pdp_story_product( 'pdp_anatomy' );
	$ana     = $product ? \Famma\Core\Product_Story::anatomy( $product ) : array();
	$title   = array() !== $ana && class_exists( '\Famma\Core\Product_Content' ) ? \Famma\Core\Product_Content::text( 'ana_title' ) : '';

	if ( '' === $title ) {
		return;
	}

	$alt = trim( (string) get_post_meta( $ana['image'], '_wp_attachment_image_alt', true ) );
	$img = wp_get_attachment_image(
		$ana['image'],
		'woocommerce_single',
		false,
		array(
			'class'    => 'famma-ana__img',
			'loading'  => 'lazy',
			'decoding' => 'async',
			'alt'      => '' !== $alt ? $alt : $product->get_name(),
		)
	);

	if ( '' === $img ) {
		return;
	}
	?>
	<section class="famma-ana" aria-labelledby="famma-ana-title">
		<h2 class="famma-pdp-title" id="famma-ana-title"><?php echo esc_html( $title ); ?></h2>
		<div class="famma-ana__grid">
			<div class="famma-ana__sticky">
				<div class="famma-ana__stage">
					<div class="famma-ana__cam">
						<?php echo wp_kses_post( $img ); ?>
						<?php foreach ( $ana['items'] as $n => $item ) : ?>
							<?php // Coordonnées de la photo, jamais retournée en arabe : gauche/haut physiques. ?>
							<span class="famma-ana__spot<?php echo 0 === $n ? ' is-on' : ''; ?>" aria-hidden="true" style="<?php echo esc_attr( sprintf( '--x:%s%%;--y:%s%%', $item['x'], $item['y'] ) ); ?>"><?php echo esc_html( (string) ( $n + 1 ) ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<ol class="famma-ana__list" <?php echo famma_child_pdp_story_lang( $ana['lang'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par famma_child_pdp_story_lang(). ?>>
				<?php foreach ( $ana['items'] as $n => $item ) : ?>
					<li class="famma-ana__item<?php echo 0 === $n ? ' is-on' : ''; ?>"><span class="famma-ana__n" aria-hidden="true"><?php echo esc_html( (string) ( $n + 1 ) ); ?></span><p><?php echo esc_html( $item['text'] ); ?></p></li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>
	<?php
}
add_action( 'woocommerce_after_single_product_summary', 'famma_child_pdp_anatomy', 4 );
