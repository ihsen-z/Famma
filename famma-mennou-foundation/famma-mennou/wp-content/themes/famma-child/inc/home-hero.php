<?php
/**
 * FAMMA — visuel du héros de l'accueil, selon le style choisi en administration.
 *
 * Quatre styles (FAMMA → Pages → « Carrousel du héros ») :
 *
 * - `classic`  : photo sur disque orange et points — l'ancien héros, rendu par
 *                `famma_child_home_hero()` lui-même ;
 * - `stack`    : pile de cartes en éventail, flèches, légende nom · prix ;
 * - `showcase` : grande photo arrondie, pastille nom · prix, barres de temps ;
 * - `thumbs`   : grande photo défilante au doigt, miniatures des produits.
 *
 * Seul le style retenu est servi : son balisage ici, sa feuille
 * (`hero-variants.css`, chargée hors style classique seulement) et sa branche
 * de `hero.js`. Le contenu ne change pas d'un style à l'autre — mêmes produits,
 * même image LCP prioritaire, mêmes diapos différées (PERF-09).
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Style du carrousel à servir.
 *
 * Sans le plugin, le héros garde son dessin historique : le thème ne doit
 * jamais dépendre de famma-core pour afficher l'accueil.
 *
 * @return string `classic`, `stack`, `showcase` ou `thumbs`.
 */
function famma_child_home_hero_style(): string {
	if ( ! class_exists( '\Famma\Core\Home_Hero_Style' ) ) {
		return 'classic';
	}

	return \Famma\Core\Home_Hero_Style::get();
}

/**
 * Balise image d'une diapo, déjà échappée.
 *
 * La première est l'élément LCP de l'accueil mobile : priorité haute, jamais
 * différée. Les suivantes ne doivent pas lui disputer la bande passante : leur
 * `src` est retenu jusqu'à `load` (voir hero.js).
 *
 * @param WC_Product $product Produit de la diapo.
 * @param int        $index   Position de la diapo.
 * @param string     $size    Taille d'image WooCommerce.
 * @return string
 */
function famma_child_home_hero_image( WC_Product $product, int $index, string $size = 'woocommerce_single' ): string {
	$attr = 0 === $index
		? array(
			'loading'       => 'eager',
			'fetchpriority' => 'high',
		)
		: array(
			'loading'       => 'lazy',
			'fetchpriority' => 'low',
		);

	$html = $product->get_image( $size, $attr );

	if ( 0 !== $index ) {
		$html = famma_child_defer_image( $html );
	}

	return famma_child_kses_image( $html );
}

/**
 * Miniature d'un produit pour la rangée de vignettes, toujours différée.
 *
 * @param WC_Product $product Produit.
 * @return string
 */
function famma_child_home_hero_thumb( WC_Product $product ): string {
	$html = $product->get_image(
		'woocommerce_gallery_thumbnail',
		array(
			'loading' => 'lazy',
			'alt'     => '',
		)
	);

	return famma_child_kses_image( famma_child_defer_image( $html ) );
}

/**
 * Prix tel que WooCommerce le formate (prix barré compris), échappé.
 *
 * @param WC_Product $product Produit.
 * @return string
 */
function famma_child_home_hero_price( WC_Product $product ): string {
	return wp_kses_post( $product->get_price_html() );
}

/**
 * Place d'une carte dans la pile, à l'ouverture de la page.
 *
 * Même calcul que `stackSlot()` dans hero.js : 0 devant, 1 et 2 derrière en
 * éventail, `out` pour les cartes rangées hors de vue — dont celle qui vient
 * de partir, pour qu'elle ne revienne pas en glissant derrière la pile.
 *
 * @param int $index Position de la carte.
 * @param int $count Nombre de cartes.
 * @return string
 */
function famma_child_home_hero_stack_slot( int $index, int $count ): string {
	if ( 0 === $index ) {
		return '0';
	}

	if ( $index > 2 || ( $count > 2 && $count - 1 === $index ) ) {
		return 'out';
	}

	return (string) $index;
}

/**
 * Attributs d'accessibilité d'une diapo inactive.
 *
 * Une diapo masquée ne doit être ni lue ni atteignable au clavier ; hero.js
 * tient ensuite ces attributs à jour.
 *
 * @param int $index Position de la diapo.
 * @return string
 */
function famma_child_home_hero_hidden_attr( int $index ): string {
	return 0 === $index ? '' : ' aria-hidden="true" tabindex="-1"';
}

/**
 * Icône flèche, sens de lecture compris (le CSS la retourne en arabe).
 *
 * @param string $direction `prev` ou `next`.
 * @return void
 */
function famma_child_home_hero_chevron( string $direction ): void {
	$path = 'prev' === $direction ? 'M15 6l-6 6 6 6' : 'M9 6l6 6-6 6';
	?>
	<svg class="famma-icon famma-hero2__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="<?php echo esc_attr( $path ); ?>"/></svg>
	<?php
}

/**
 * Visuel du héros pour les styles autres que `classic`.
 *
 * @param string            $style    `stack`, `showcase` ou `thumbs`.
 * @param array<WC_Product> $products Produits du carrousel (au moins un).
 * @return void
 */
function famma_child_home_hero_media( string $style, array $products ): void {
	$count = count( $products );
	?>
	<div class="famma-hero2__media famma-hero2__media--<?php echo esc_attr( $style ); ?>">
		<?php
		if ( 'stack' === $style ) {
			famma_child_home_hero_stack( $products, $count );
		} elseif ( 'showcase' === $style ) {
			famma_child_home_hero_showcase( $products, $count );
		} else {
			famma_child_home_hero_thumbs( $products, $count );
		}
		?>
	</div>
	<?php
}

/**
 * Pile de cartes : la carte du dessus se jette, la suivante remonte.
 *
 * La légende vit hors des cartes (un lien ne contient pas un autre lien) :
 * une légende par produit, hero.js n'affiche que celle de la carte du dessus.
 *
 * @param array<WC_Product> $products Produits.
 * @param int               $count    Nombre de produits.
 * @return void
 */
function famma_child_home_hero_stack( array $products, int $count ): void {
	?>
	<div class="famma-hero2__slides famma-hero2__deck"<?php echo $count > 1 ? ' data-famma-hero="stack"' : ''; ?>>
		<?php foreach ( $products as $index => $product ) : ?>
			<a
				class="famma-hero2__slide famma-hero2__card<?php echo 0 === $index ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( (string) $product->get_permalink() ); ?>"
				data-pos="<?php echo esc_attr( famma_child_home_hero_stack_slot( $index, $count ) ); ?>"
				draggable="false"
				<?php echo famma_child_home_hero_hidden_attr( $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributs fixes. ?>
			>
				<?php echo famma_child_home_hero_image( $product, $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par famma_child_kses_image(). ?>
				<span class="screen-reader-text"><?php echo esc_html( $product->get_name() ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="famma-hero2__nav">
		<?php if ( $count > 1 ) : ?>
			<button type="button" class="famma-hero2__step" data-famma-hero-step="-1" aria-label="<?php esc_attr_e( 'Produit précédent', 'famma-child' ); ?>">
				<?php famma_child_home_hero_chevron( 'prev' ); ?>
			</button>
		<?php endif; ?>

		<div class="famma-hero2__captions">
			<?php foreach ( $products as $index => $product ) : ?>
				<p class="famma-hero2__caption<?php echo 0 === $index ? ' is-active' : ''; ?>" data-famma-hero-caption<?php echo 0 === $index ? '' : ' aria-hidden="true"'; ?>>
					<span class="famma-hero2__caption-name"><?php echo esc_html( $product->get_name() ); ?></span>
					<span class="famma-hero2__caption-meta">
						<span class="famma-hero2__price"><?php echo famma_child_home_hero_price( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par wp_kses_post(). ?></span>
						<?php if ( $count > 1 ) : ?>
							<span class="famma-hero2__count" aria-hidden="true"><?php echo esc_html( sprintf( '%02d / %02d', $index + 1, $count ) ); ?></span>
						<?php endif; ?>
					</span>
				</p>
			<?php endforeach; ?>
		</div>

		<?php if ( $count > 1 ) : ?>
			<button type="button" class="famma-hero2__step" data-famma-hero-step="1" aria-label="<?php esc_attr_e( 'Produit suivant', 'famma-child' ); ?>">
				<?php famma_child_home_hero_chevron( 'next' ); ?>
			</button>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Vitrine : grande photo arrondie, pastille nom · prix posée dessus.
 *
 * @param array<WC_Product> $products Produits.
 * @param int               $count    Nombre de produits.
 * @return void
 */
function famma_child_home_hero_showcase( array $products, int $count ): void {
	?>
	<div class="famma-hero2__slides famma-hero2__frame"<?php echo $count > 1 ? ' data-famma-hero="showcase"' : ''; ?>>
		<?php foreach ( $products as $index => $product ) : ?>
			<a
				class="famma-hero2__slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( (string) $product->get_permalink() ); ?>"
				draggable="false"
				<?php echo famma_child_home_hero_hidden_attr( $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributs fixes. ?>
			>
				<?php echo famma_child_home_hero_image( $product, $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par famma_child_kses_image(). ?>
				<span class="famma-hero2__chip">
					<span class="famma-hero2__chip-text">
						<span class="famma-hero2__chip-name"><?php echo esc_html( $product->get_name() ); ?></span>
						<span class="famma-hero2__price"><?php echo famma_child_home_hero_price( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par wp_kses_post(). ?></span>
					</span>
					<span class="famma-hero2__chip-go" aria-hidden="true"><?php famma_child_home_hero_chevron( 'next' ); ?></span>
				</span>
			</a>
		<?php endforeach; ?>
	</div>

	<?php if ( $count > 1 ) : ?>
		<div class="famma-hero2__bars" role="tablist" aria-label="<?php esc_attr_e( 'Visuels', 'famma-child' ); ?>">
			<?php foreach ( $products as $index => $product ) : ?>
				<button
					type="button"
					class="famma-hero2__bar<?php echo 0 === $index ? ' is-active' : ''; ?>"
					data-famma-hero-dot="<?php echo esc_attr( (string) $index ); ?>"
					role="tab"
					aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
					aria-label="<?php echo esc_attr( $product->get_name() ); ?>"
				><span class="famma-hero2__bar-track" aria-hidden="true"></span></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
}

/**
 * Vignettes : piste défilante au doigt (scroll-snap natif), miniatures dessous.
 *
 * Pas de défilement automatique : le visiteur choisit ce qu'il regarde.
 *
 * @param array<WC_Product> $products Produits.
 * @param int               $count    Nombre de produits.
 * @return void
 */
function famma_child_home_hero_thumbs( array $products, int $count ): void {
	?>
	<div class="famma-hero2__slides famma-hero2__track"<?php echo $count > 1 ? ' data-famma-hero="thumbs"' : ''; ?>>
		<?php foreach ( $products as $index => $product ) : ?>
			<a
				class="famma-hero2__slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( (string) $product->get_permalink() ); ?>"
				draggable="false"
				<?php echo famma_child_home_hero_hidden_attr( $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributs fixes. ?>
			>
				<?php echo famma_child_home_hero_image( $product, $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par famma_child_kses_image(). ?>
				<span class="famma-hero2__tag famma-hero2__price"><?php echo famma_child_home_hero_price( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par wp_kses_post(). ?></span>
				<span class="famma-hero2__name"><?php echo esc_html( $product->get_name() ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<?php if ( $count > 1 ) : ?>
		<div class="famma-hero2__thumbs" role="tablist" aria-label="<?php esc_attr_e( 'Produits', 'famma-child' ); ?>">
			<?php foreach ( $products as $index => $product ) : ?>
				<button
					type="button"
					class="famma-hero2__thumb<?php echo 0 === $index ? ' is-active' : ''; ?>"
					data-famma-hero-dot="<?php echo esc_attr( (string) $index ); ?>"
					role="tab"
					aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
					aria-label="<?php echo esc_attr( $product->get_name() ); ?>"
				><?php echo famma_child_home_hero_thumb( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par famma_child_kses_image(). ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
}
