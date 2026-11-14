<?php
/**
 * Nouveau look — fiche produit, lot 47 : boîte d'achat de la maquette.
 *
 * Quatre briques, valables pour toutes les fiches, chacune séparable pour
 * que la décision n° 8 (« taille d'abord ») reste à part :
 * - `pdp_buy` (n° 8) : taille, quantité et total d'abord, barre
 *   d'avancement, « Commander » qui déplie le formulaire. Ce fichier pose les
 *   pièces cachées (`hidden`) au-dessus du formulaire ; le script
 *   `new-look-pdp-buy.js` les range dans le formulaire. Sans script, le
 *   formulaire reste ouvert comme aujourd'hui ;
 * - `pdp_form` (n° 9 et 10) : coches vertes, total qui rebondit, sans bouton
 *   panier (feuille et script, rien ici) ;
 * - `pdp_points` (n° 7) : description courte en entier sous la boîte d'achat ;
 * - `pdp_dock` (n° 18) : barre du bas enrichie (voir `famma_child_sticky_cta()`).
 *
 * Lot 49, deux briques de plus :
 * - `pdp_altsize` : lien « autre taille » sous les tailles (données du produit) ;
 * - `pdp_labels` (n° 9) : intitulés du formulaire dans la langue de la page,
 *   sans astérisques (feuille et script, rien ici).
 *
 * Le formulaire express (famma-core, `express-order.js`) n'est pas modifié :
 * ses contrôles, son envoi et l'offre après achat restent les mêmes.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pose les briques sur la fiche.
 *
 * @return void
 */
function famma_child_pdp_buy_setup(): void {
	if ( ! function_exists( 'famma_child_pdp_part' ) ) {
		return;
	}

	if ( famma_child_pdp_part( 'pdp_buy' ) ) {
		add_action( 'woocommerce_single_product_summary', 'famma_child_pdp_buy', 25 );
	}

	if ( famma_child_pdp_part( 'pdp_altsize' ) ) {
		add_action( 'woocommerce_single_product_summary', 'famma_child_pdp_altsize', 25 );
	}

	// Avant le gabarit et les textes du script : pas de saut de mise en page.
	if ( famma_child_pdp_part( 'pdp_labels' ) ) {
		add_filter( 'gettext_famma-core', 'famma_child_pdp_labels_gettext' );
	}

	// Sous la boîte d'achat express seulement : sans elle (produit variable, rupture), l'accroche reste à sa place.
	if ( famma_child_pdp_part( 'pdp_points' ) && function_exists( 'famma_child_product_excerpt' ) && famma_child_pdp_buy_express() ) {
		remove_action( 'woocommerce_single_product_summary', 'famma_child_product_excerpt', 20 );
		add_action( 'woocommerce_single_product_summary', 'famma_child_pdp_points', 27 );
	}
}
add_action( 'wp', 'famma_child_pdp_buy_setup', 20 );

/**
 * La fiche porte-t-elle le formulaire express ?
 *
 * Mêmes conditions que famma-core (`Express_Order::eligible_product()`, privée) :
 * produit simple, achetable, en stock. Produit déjà chargé, aucune requête de plus.
 *
 * @return bool
 */
function famma_child_pdp_buy_express(): bool {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return false;
	}

	$product = wc_get_product( get_queried_object_id() );

	return $product instanceof WC_Product && ! $product->is_type( 'variable' ) && $product->is_purchasable() && $product->is_in_stock();
}

/**
 * `pdp_labels` (n° 9) : un texte « français / derja » de famma-core réduit à
 * la langue de la page (intitulés, messages d'erreur et d'envoi du
 * formulaire express). Seulement un vrai couple : un seul « / », de l'arabe à
 * droite et pas à gauche ; tout autre texte passe tel quel.
 *
 * @param string $translation Texte traduit.
 * @return string
 */
function famma_child_pdp_labels_gettext( $translation ) {
	$translation = (string) $translation;

	if ( 1 !== substr_count( $translation, ' / ' ) ) {
		return $translation;
	}

	list( $fr, $ar ) = explode( ' / ', $translation );

	if ( '' === trim( $fr ) || ! preg_match( '/\p{Arabic}/u', $ar ) || preg_match( '/\p{Arabic}/u', $fr ) ) {
		return $translation;
	}

	return is_rtl() ? trim( $ar ) : trim( $fr );
}

/**
 * Libellé et texte d'une taille pour la barre d'avancement, ou rien.
 *
 * @param WC_Product $product Produit.
 * @return string Nom du choix de taille (« Taille », « Pointure »…), vide si le produit n'en a pas.
 */
function famma_child_pdp_buy_size_label( WC_Product $product ): string {
	if ( ! class_exists( '\Famma\Core\Product_Sizes' ) || array() === \Famma\Core\Product_Sizes::options( $product ) ) {
		return '';
	}

	// « Taille / المقاس » : une seule langue dans une phrase, celle de la page.
	$parts = array_map( 'trim', explode( ' / ', \Famma\Core\Product_Sizes::label( $product ) ) );

	return ( is_rtl() && isset( $parts[1] ) && '' !== $parts[1] ) ? $parts[1] : $parts[0];
}

/**
 * Pièces de la boîte d'achat, cachées jusqu'au script.
 *
 * @return void
 */
function famma_child_pdp_buy(): void {
	$product = wc_get_product();

	if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() || ! class_exists( '\Famma\Core\Product_Content' ) ) {
		return;
	}

	$text  = static function ( string $key ): string {
		return \Famma\Core\Product_Content::text( $key );
	};
	$after = $text( 'buy_after' );
	?>
	<div class="famma-buy" hidden
		data-pg0="<?php echo esc_attr( $text( 'buy_pg0' ) ); ?>"
		data-pg1="<?php echo esc_attr( $text( 'buy_pg1' ) ); ?>"
		data-pg2="<?php echo esc_attr( $text( 'buy_pg2' ) ); ?>"
		data-pg3="<?php echo esc_attr( $text( 'buy_pg3' ) ); ?>"
		data-chosen="<?php echo esc_attr( $text( 'buy_chosen' ) ); ?>"
		data-size-err="<?php echo esc_attr( $text( 'buy_size_err' ) ); ?>"
		data-size-label="<?php echo esc_attr( famma_child_pdp_buy_size_label( $product ) ); ?>"
	>
		<div class="famma-buy__pg">
			<p class="famma-buy__pg-t" aria-live="polite"></p>
			<span class="famma-buy__pg-b" aria-hidden="true"><i></i></span>
		</div>
		<p class="famma-buy__cta">
			<button type="button" class="button famma-buy__open"><?php echo esc_html( $text( 'cta_order' ) ); ?></button>
		</p>
		<?php if ( '' !== $after ) : ?>
			<p class="famma-buy__after"><?php echo esc_html( $after ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Lien « autre taille » (`pdp_altsize`, lot 49), saisi dans l'onglet produit
 * « Fiche FAMMA ». Posé juste avant le formulaire express ; le script le
 * range sous les tailles. Sans script, il reste lisible au-dessus du formulaire.
 *
 * @return void
 */
function famma_child_pdp_altsize(): void {
	$product = wc_get_product();
	$link    = $product instanceof WC_Product && class_exists( '\Famma\Core\Product_Alt_Size' ) ? \Famma\Core\Product_Alt_Size::link( $product ) : array();

	if ( array() === $link ) {
		return;
	}

	/*
	 * L'exclusion de traduction est sur le texte seulement : TranslatePress
	 * doit pouvoir réécrire le lien vers /ar/. Toujours avec une valeur (un
	 * attribut nu casse son analyseur, voir inc/header-parts.php).
	 */
	printf(
		'<p class="famma-altsize"><a href="%3$s"><span lang="%1$s" dir="%2$s" data-no-translation="">%4$s</span></a></p>',
		esc_attr( $link['lang'] ),
		'ar' === $link['lang'] ? 'rtl' : 'ltr',
		esc_url( $link['url'] ),
		esc_html( $link['text'] )
	);
}

/**
 * Points forts : la description courte en entier, sous la boîte d'achat.
 *
 * Même contenu que l'accroche de WooCommerce (`woocommerce_short_description`),
 * sans le « Lire la suite » : rien n'est réécrit.
 *
 * @return void
 */
function famma_child_pdp_points(): void {
	global $post;

	if ( ! $post instanceof WP_Post || '' === trim( (string) $post->post_excerpt ) ) {
		return;
	}

	$html = apply_filters( 'woocommerce_short_description', $post->post_excerpt ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- filtre de WooCommerce.

	if ( '' === trim( wp_strip_all_tags( (string) $html ) ) ) {
		return;
	}
	?>
	<div class="famma-points woocommerce-product-details__short-description"><?php echo wp_kses_post( $html ); ?></div>
	<?php
}

/**
 * Barre du bas (`pdp_dock`) : photo et ligne du dessus, avant le prix.
 *
 * Appelée par `famma_child_sticky_cta()` quand la brique est servie.
 *
 * @param WC_Product $product Produit.
 * @return void
 */
function famma_child_pdp_dock_head( WC_Product $product ): void {
	$thumb = $product->get_image_id() ? wp_get_attachment_image_url( (int) $product->get_image_id(), 'woocommerce_gallery_thumbnail' ) : '';
	$label = famma_child_pdp_buy_size_label( $product );

	if ( $thumb ) {
		printf( '<img class="famma-sticky-cta__img" src="%s" alt="" width="44" height="44" loading="lazy" decoding="async" />', esc_url( $thumb ) );
	}

	printf(
		'<span class="famma-sticky-cta__line" data-size-label="%1$s" data-pick="%2$s">%3$s</span>',
		esc_attr( $label ),
		esc_attr( '' !== $label && class_exists( '\Famma\Core\Product_Content' ) ? \Famma\Core\Product_Content::text( 'buy_pick' ) : '' ),
		esc_html( $product->get_name() )
	);
}
