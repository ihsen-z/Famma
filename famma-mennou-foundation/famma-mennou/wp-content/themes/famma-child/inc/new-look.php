<?php
/**
 * Nouveau look animé — la couche visuelle des maquettes du 04-06/11/2026.
 *
 * Tout ce fichier est une surcouche : il n'enlève rien au gabarit existant et
 * ne touche ni la requête de la boutique, ni le panier, ni le tunnel, ni le
 * suivi publicitaire. L'ajout au panier reste celui de WooCommerce ; la couche
 * ne fait qu'écouter ses événements (`adding_to_cart`, `added_to_cart`), comme
 * le fait déjà le suivi Meta, sans jamais les interrompre.
 *
 * L'interrupteur vit dans famma-core (FAMMA → Pages → Nouveau look animé).
 * Sans le plugin, ou mode « Désactivé », chaque fonction sort aussitôt et le
 * site est strictement celui d'avant.
 *
 * Briques :
 * - navy        : texte marine sur les aplats orange (un seul jeton) ;
 * - motion      : apparitions des cartes, vol vers le panier, cœur qui pétille ;
 * - alt_photo   : seconde photo au survol, chargée au premier survol seulement ;
 * - transitions : transition de vue entre pages de la boutique (CSS seul) ;
 * - dock        : barre « Aujourd'hui 0 DT · À la réception », remplie côté
 *                 navigateur — jamais rendue avec un panier dans le HTML en cache.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Une brique du nouveau look est-elle servie à cette requête ?
 *
 * @param string $part Clé de brique (navy, motion, alt_photo, transitions, dock).
 * @return bool
 */
function famma_child_new_look( string $part ): bool {
	$on = class_exists( '\Famma\Core\New_Look' ) && \Famma\Core\New_Look::part( $part );

	/**
	 * Filtre l'activation d'une brique du nouveau look.
	 *
	 * @param bool   $on   Brique active.
	 * @param string $part Clé de brique.
	 */
	return (bool) apply_filters( 'famma_child_new_look', $on, $part );
}

/**
 * Pages qui reçoivent la couche animée : celles qui affichent des cartes.
 *
 * La fiche produit, le panier et le tunnel en sont exclus volontairement :
 * ils portent la commande express et les événements de conversion. Ils ne
 * reçoivent que la couleur des boutons.
 *
 * @return bool
 */
function famma_child_new_look_page(): bool {
	if ( ! function_exists( 'is_shop' ) ) {
		return false;
	}

	return is_front_page() || is_shop() || is_product_taxonomy();
}

/**
 * Briques animées servies sur la page courante.
 *
 * @return array<int, string>
 */
function famma_child_new_look_parts(): array {
	if ( ! famma_child_new_look_page() ) {
		return array();
	}

	return array_values( array_filter( array( 'motion', 'alt_photo', 'transitions', 'dock' ), 'famma_child_new_look' ) );
}

/**
 * Texte marine sur les aplats orange, sur tout le site.
 *
 * Chaque bouton orange du thème lit `--famma-on-orange` (tokens.css) : une
 * seule déclaration suffit, sans retoucher aucun composant.
 *
 * @return void
 */
function famma_child_new_look_on_orange(): void {
	if ( ! famma_child_new_look( 'navy' ) || ! wp_style_is( 'famma-tokens', 'enqueued' ) ) {
		return;
	}

	wp_add_inline_style( 'famma-tokens', ':root{--famma-on-orange:var(--famma-blue)}' );
}
add_action( 'wp_enqueue_scripts', 'famma_child_new_look_on_orange', 21 );

/**
 * Charge la feuille et le script de la couche animée.
 *
 * @return void
 */
function famma_child_enqueue_new_look(): void {
	$parts = famma_child_new_look_parts();

	if ( ! $parts ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	if ( file_exists( $dir . '/assets/css/new-look.css' ) ) {
		wp_enqueue_style(
			'famma-new-look',
			$uri . '/assets/css/new-look.css',
			array( 'famma-layout' ),
			(string) filemtime( $dir . '/assets/css/new-look.css' )
		);
	}

	if ( in_array( 'transitions', $parts, true ) && file_exists( $dir . '/assets/css/new-look-transitions.css' ) ) {
		wp_enqueue_style(
			'famma-new-look-transitions',
			$uri . '/assets/css/new-look-transitions.css',
			array(),
			(string) filemtime( $dir . '/assets/css/new-look-transitions.css' )
		);
	}

	if ( array_intersect( array( 'motion', 'alt_photo', 'dock' ), $parts ) && file_exists( $dir . '/assets/js/new-look.js' ) ) {
		wp_enqueue_script(
			'famma-new-look',
			$uri . '/assets/js/new-look.js',
			array( 'jquery' ),
			(string) filemtime( $dir . '/assets/js/new-look.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_new_look', 23 );

/**
 * Classes du corps : le script et la feuille ne s'appuient que sur elles.
 *
 * @param array<int, string> $classes Classes du corps.
 * @return array<int, string>
 */
function famma_child_new_look_body_class( array $classes ): array {
	$parts = famma_child_new_look_parts();

	if ( $parts ) {
		$classes[] = 'famma-nl';
		foreach ( $parts as $part ) {
			$classes[] = 'famma-nl-' . sanitize_html_class( str_replace( '_', '-', $part ) );
		}
	}

	if ( famma_child_new_look( 'navy' ) ) {
		$classes[] = 'famma-nl-navy';
	}

	return $classes;
}
add_filter( 'body_class', 'famma_child_new_look_body_class' );

/**
 * Seconde photo de la carte, en attente : aucune image n'est téléchargée.
 *
 * Le serveur ne pose que l'adresse ; le script crée l'image au premier
 * survol d'un pointeur fin. Sur mobile, rien n'est jamais chargé.
 *
 * @return void
 */
function famma_child_loop_alt_photo(): void {
	if ( ! famma_child_new_look( 'alt_photo' ) || ! famma_child_new_look_page() ) {
		return;
	}

	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$gallery = $product->get_gallery_image_ids();

	if ( empty( $gallery ) ) {
		return;
	}

	$id  = (int) reset( $gallery );
	$src = wp_get_attachment_image_url( $id, 'woocommerce_thumbnail' );

	if ( ! $src ) {
		return;
	}

	$srcset = (string) wp_get_attachment_image_srcset( $id, 'woocommerce_thumbnail' );

	printf(
		'<span class="famma-alt" data-src="%1$s" data-srcset="%2$s" aria-hidden="true"></span>',
		esc_url( $src ),
		esc_attr( $srcset )
	);
}
add_action( 'woocommerce_before_shop_loop_item_title', 'famma_child_loop_alt_photo', 12 );

/**
 * Balisage de la barre du panier.
 *
 * Sans panier (`$with_cart` faux), la barre sort vide et masquée : c'est la
 * version posée dans le HTML en cache, identique pour tous. La version avec
 * panier ne voyage que dans les fragments WooCommerce, propres à chacun.
 *
 * @param bool $with_cart Lire le panier de la session courante.
 * @return string
 */
function famma_child_new_look_dock_markup( bool $with_cart ): string {
	$count = 0;
	$total = '';

	if ( $with_cart && function_exists( 'WC' ) && WC()->cart ) {
		$count = (int) WC()->cart->get_cart_contents_count();
		$total = $count ? (string) WC()->cart->get_total() : '';
	}

	$endpoint = class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'get_refreshed_fragments' ) : '';

	ob_start();
	?>
	<div class="famma-dock" data-count="<?php echo esc_attr( (string) $count ); ?>" data-endpoint="<?php echo esc_url( $endpoint ); ?>"<?php echo $count ? '' : ' hidden'; ?>>
		<div class="famma-dock__sums">
			<span class="famma-dock__row">
				<span class="famma-dock__label"><?php esc_html_e( 'Aujourd’hui', 'famma-child' ); ?></span>
				<b class="famma-dock__zero"><?php echo wp_kses_post( wc_price( 0 ) ); ?></b>
			</span>
			<span class="famma-dock__row">
				<span class="famma-dock__label"><?php esc_html_e( 'À la réception', 'famma-child' ); ?></span>
				<b class="famma-dock__total"><?php echo wp_kses_post( $total ); ?></b>
			</span>
		</div>
		<a class="famma-dock__go" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
			<?php esc_html_e( 'Mon panier', 'famma-child' ); ?>
			<span class="famma-dock__count">
				<?php
				printf(
					/* translators: %d: nombre d'articles dans le panier. */
					esc_html( _n( '%d article', '%d articles', $count, 'famma-child' ) ),
					(int) $count
				);
				?>
			</span>
		</a>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Pose la barre vide en bas des pages de cartes.
 *
 * @return void
 */
function famma_child_new_look_dock(): void {
	if ( ! function_exists( 'wc_get_cart_url' ) || ! in_array( 'dock', famma_child_new_look_parts(), true ) ) {
		return;
	}

	echo famma_child_new_look_dock_markup( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- balisage échappé champ par champ dans famma_child_new_look_dock_markup().
}
add_action( 'wp_footer', 'famma_child_new_look_dock', 5 );

/**
 * Remplit la barre après un ajout ou une relecture des fragments.
 *
 * @param array<string, string> $fragments Fragments à remplacer.
 * @return array<string, string>
 */
function famma_child_new_look_dock_fragment( array $fragments ): array {
	if ( function_exists( 'wc_get_cart_url' ) && famma_child_new_look( 'dock' ) ) {
		$fragments['div.famma-dock'] = famma_child_new_look_dock_markup( true );
	}

	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'famma_child_new_look_dock_fragment' );
