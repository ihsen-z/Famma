<?php
/**
 * FAMMA — fiche produit.
 *
 * Réordonne le résumé pour suivre la maquette, ajoute le bandeau de bénéfices,
 * l'onglet Livraison et le résumé des avis. La galerie, le formulaire d'achat,
 * les onglets et les produits liés restent ceux de WooCommerce : on rhabille,
 * on ne réécrit pas.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Réordonne le résumé produit selon la maquette.
 *
 * Ordre voulu : titre · note · accroche · prix · économie · réassurance ·
 * quantité + achat · WhatsApp.
 *
 * WooCommerce pose la note ET le prix en priorité 10, et l'accroche en 20 —
 * donc après le prix. Impossible d'y glisser l'accroche sans redistribuer les
 * priorités : on décale le prix en 12 et l'accroche en 11.
 *
 * La réassurance passe de 35 à 25, c'est-à-dire **avant** le bouton d'achat :
 * le client lit comment il paie et qui le livre au moment où il hésite, pas
 * après s'être engagé. La carte WhatsApp, elle, reste sous le bouton — au-dessus
 * elle concurrencerait le CTA qui fait la vente.
 *
 * @return void
 */
function famma_child_product_reorder_summary(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
	add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 11 );

	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
	add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 12 );

	remove_action( 'woocommerce_single_product_summary', 'famma_child_price_savings', 11 );
	add_action( 'woocommerce_single_product_summary', 'famma_child_price_savings', 13 );

	remove_action( 'woocommerce_single_product_summary', 'famma_child_product_reassurance', 35 );
	add_action( 'woocommerce_single_product_summary', 'famma_child_product_reassurance', 25 );
}
add_action( 'wp', 'famma_child_product_reorder_summary' );

/**
 * Retire le fil d'Ariane que Kadence pose au-dessus de la fiche produit.
 *
 * Constaté au rendu : la page en affichait **deux**, le nôtre
 * (`.woocommerce-breadcrumb`, rendu par `single-product.php` à l'emplacement
 * de la maquette) et celui de Kadence (`.kadence-breadcrumbs`, dans un bloc
 * `.product-title.product-above`). Un contrôle qui ne cherchait que la classe
 * WooCommerce ne pouvait pas le voir : les deux n'ont pas le même nom.
 *
 * Le nôtre est conservé parce qu'il correspond au dessin — `Accueil › Catégorie
 * › Produit` — là où celui de Kadence intercale « Boutique ».
 *
 * Retiré du registre de hooks, et non masqué en CSS : un fil d'Ariane invisible
 * reste lu par les lecteurs d'écran et dupliqué dans les données structurées.
 *
 * Passe par `famma_child_remove_parent_callback()` (inc/shop.php), qui
 * retrouve l'instance dans le registre de hooks : vérifié à l'exécution,
 * l'accesseur `kadence()` n'existe pas dans tous les contextes, et un
 * `remove_action` qui ne trouve pas l'instance échoue en silence.
 *
 * @return void
 */
function famma_child_remove_parent_breadcrumb(): void {
	if ( ! function_exists( 'famma_child_remove_parent_callback' ) ) {
		return;
	}

	famma_child_remove_parent_callback( 'woocommerce_before_single_product', 'output_product_above' );
}
add_action( 'wp', 'famma_child_remove_parent_breadcrumb' );

/**
 * Carte WhatsApp sous le bouton d'achat.
 *
 * Séparée de la réassurance, qui est remontée au-dessus du CTA : les deux
 * blocs n'ont plus la même place, ils ne peuvent plus partager la même
 * fonction. Ne rend rien si aucun numéro n'est configuré (§60).
 *
 * @return void
 */
function famma_child_product_whatsapp_card(): void {
	if ( '' === famma_child_whatsapp_number() ) {
		return;
	}

	echo '<div class="famma-product__whatsapp">';
	echo do_shortcode( '[famma_whatsapp text="' . esc_attr__( 'Une question sur ce produit ?', 'famma-child' ) . '"]' );
	echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'famma_child_product_whatsapp_card', 36 );

/**
 * Badge vert « Nouveau » en tête du résumé.
 *
 * Même règle que sur la carte : la fraîcheur se lit sur la date de publication,
 * et un produit déjà en promotion ne le porte pas — la remise est le message
 * qui vend.
 *
 * @return void
 */
function famma_child_product_new_badge(): void {
	global $product;

	if ( ! $product instanceof WC_Product || $product->is_on_sale() ) {
		return;
	}

	/** Fenêtre de nouveauté, en jours — partagée avec la carte produit. */
	$days    = (int) apply_filters( 'famma_child_new_badge_days', 30 );
	$created = $product->get_date_created();

	if ( ! $created instanceof WC_DateTime || ( time() - $created->getTimestamp() ) > $days * DAY_IN_SECONDS ) {
		return;
	}

	printf(
		'<span class="famma-badge--new">%s</span>',
		esc_html__( 'Nouveau', 'famma-child' )
	);
}
add_action( 'woocommerce_single_product_summary', 'famma_child_product_new_badge', 4 );

/**
 * Ligne de disponibilité, à côté du sélecteur de quantité.
 *
 * La maquette pose « ✓ En stock » en vert contre le champ de quantité.
 * WooCommerce n'affiche sa propre ligne `.stock` que lorsque le stock est géré
 * ou épuisé : sur un produit vendu sans gestion de stock, le client n'avait
 * aucune confirmation de disponibilité au moment d'ajouter au panier.
 *
 * L'information vient du produit, pas d'une affirmation : `is_in_stock()`.
 *
 * @return void
 */
function famma_child_product_stock_line(): void {
	global $product;

	if ( ! $product instanceof WC_Product || ! $product->is_in_stock() ) {
		return;
	}

	echo '<span class="famma-stock">';
	echo '<svg class="famma-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>';
	echo '<span>' . esc_html__( 'En stock', 'famma-child' ) . '</span>';
	echo '</span>';
}
add_action( 'woocommerce_after_add_to_cart_quantity', 'famma_child_product_stock_line' );

/**
 * Bouton favori à côté du bouton d'achat.
 *
 * Le même composant que sur les cartes — `data-famma-fav` et le script
 * `favourites.js` — donc la même sélection, mémorisée dans le navigateur.
 * Un cœur qui oublierait ce qu'on lui confie d'une page à l'autre serait pire
 * qu'un cœur absent.
 *
 * @return void
 */
function famma_child_product_favourite(): void {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	printf(
		'<button type="button" class="famma-fav" data-famma-fav="%1$s" aria-pressed="false">'
		. '<svg class="famma-fav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true" focusable="false">'
		. '<path d="M12 20.3l-1.4-1.3C5.4 14.4 2 11.3 2 7.6 2 4.9 4.1 3 6.8 3c1.6 0 3.2.8 4.2 2 1-1.2 2.6-2 4.2-2C18.9 3 21 4.9 21 7.6c0 3.7-3.4 6.8-8.6 11.4z"/></svg>'
		. '<span class="screen-reader-text famma-fav__label">%2$s</span></button>',
		esc_attr( (string) $product->get_id() ),
		esc_html__( 'Ajouter aux favoris', 'famma-child' )
	);
}
add_action( 'woocommerce_after_add_to_cart_button', 'famma_child_product_favourite' );

/**
 * Bandeau de bénéfices sous le résumé.
 *
 * Quatre affirmations **vérifiables dans le code de la boutique**, pas des
 * promesses de maquette : le paiement se fait à la livraison (le statut
 * « payé » n'arrive qu'au statut « livré »), la livraison est offerte et
 * couvre les 24 gouvernorats, aucune création de compte n'est demandée au
 * checkout. La quatrième ligne n'apparaît que si un numéro WhatsApp existe.
 *
 * Aucun délai chiffré, aucune garantie de retour : le §60 les interdit tant
 * que le propriétaire ne les a pas fournis.
 *
 * @return void
 */
function famma_child_product_benefits(): void {
	$benefits = array(
		array(
			'icon'  => 'card',
			'title' => __( 'Paiement à la livraison', 'famma-child' ),
			'text'  => __( 'Vous réglez en espèces, à la réception. Rien avant.', 'famma-child' ),
		),
		array(
			'icon'  => 'truck',
			'title' => __( 'Livraison offerte', 'famma-child' ),
			'text'  => __( 'Partout en Tunisie, dans les 24 gouvernorats.', 'famma-child' ),
		),
		array(
			'icon'  => 'user',
			'title' => __( 'Commande sans compte', 'famma-child' ),
			'text'  => __( 'Nom, téléphone, adresse. Rien de plus à remplir.', 'famma-child' ),
		),
	);

	if ( '' !== famma_child_whatsapp_number() ) {
		$benefits[] = array(
			'icon'  => 'chat',
			'title' => __( 'Une équipe joignable', 'famma-child' ),
			'text'  => __( 'Posez vos questions sur WhatsApp avant de commander.', 'famma-child' ),
		);
	}
	?>
	<ul class="famma-benefits">
		<?php foreach ( $benefits as $benefit ) : ?>
			<li class="famma-benefits__item">
				<span class="famma-benefits__mark"><?php famma_child_the_icon( $benefit['icon'] ); ?></span>
				<span class="famma-benefits__text">
					<span class="famma-benefits__title"><?php echo esc_html( $benefit['title'] ); ?></span>
					<span class="famma-benefits__sub"><?php echo esc_html( $benefit['text'] ); ?></span>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}
add_action( 'woocommerce_after_single_product_summary', 'famma_child_product_benefits', 6 );

/**
 * Ajoute l'onglet « Livraison » aux onglets produit.
 *
 * Le contenu vient de la page `/livraison/`, écrite par le propriétaire. Une
 * source unique : si la politique change, elle change à un seul endroit, et la
 * fiche produit suit. Recopier le texte ici garantirait qu'il diverge.
 *
 * L'onglet n'existe pas tant que la page est absente ou vide (§60).
 *
 * @param array<string, array<string, mixed>> $tabs Onglets existants.
 * @return array<string, array<string, mixed>>
 */
function famma_child_product_shipping_tab( array $tabs ): array {
	$page = get_page_by_path( 'livraison' );

	if ( ! $page instanceof WP_Post || '' === trim( (string) $page->post_content ) ) {
		return $tabs;
	}

	$tabs['famma_shipping'] = array(
		'title'    => __( 'Livraison', 'famma-child' ),
		'priority' => 25,
		'callback' => 'famma_child_product_shipping_panel',
	);

	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'famma_child_product_shipping_tab' );

/**
 * Contenu de l'onglet « Livraison ».
 *
 * @return void
 */
function famma_child_product_shipping_panel(): void {
	$page = get_page_by_path( 'livraison' );

	if ( ! $page instanceof WP_Post ) {
		return;
	}

	echo '<div class="famma-product__shipping">';

	/*
	 * `the_content` est un filtre du cœur, pas un hook que nous inventons :
	 * c'est lui qui exécute les blocs, les shortcodes et les paragraphes. Le
	 * remplacer par un rendu maison ferait perdre la mise en forme saisie par
	 * le propriétaire. D'où l'exception locale à la règle de préfixe.
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- filtre du coeur WordPress, applique a dessein.
	echo wp_kses_post( apply_filters( 'the_content', $page->post_content ) );
	printf(
		'<p><a href="%1$s">%2$s</a></p>',
		esc_url( (string) get_permalink( $page ) ),
		esc_html__( 'Voir la page Livraison complète', 'famma-child' )
	);
	echo '</div>';
}

/**
 * Résumé des avis, entre les onglets et les produits liés.
 *
 * Chiffres réels uniquement : moyenne et nombre d'avis WooCommerce. La
 * maquette affichait « 4.8/5 » et « 538 avis » — des valeurs de prototype. Ici,
 * **tant qu'il n'y a aucun avis, la section n'existe pas** : une note inventée
 * sur une boutique qui démarre est un mensonge commercial (§58, §60).
 *
 * @return void
 */
function famma_child_product_review_summary(): void {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$count = (int) $product->get_review_count();

	if ( 0 === $count ) {
		return;
	}

	$average = (float) $product->get_average_rating();
	?>
	<section class="famma-reviews">
		<div class="famma-reviews__score">
			<p class="famma-reviews__average">
				<span class="famma-reviews__value"><?php echo esc_html( number_format_i18n( $average, 1 ) ); ?></span>
				<span class="famma-reviews__max">/5</span>
			</p>
			<?php echo wp_kses_post( wc_get_rating_html( $average, $count ) ); ?>
			<p class="famma-reviews__count">
				<?php
				printf(
					/* translators: %s: nombre d'avis. */
					esc_html( _n( 'Basé sur %s avis', 'Basé sur %s avis', $count, 'famma-child' ) ),
					esc_html( number_format_i18n( $count ) )
				);
				?>
			</p>
		</div>
		<p class="famma-reviews__link">
			<a href="#reviews"><?php esc_html_e( 'Lire tous les avis', 'famma-child' ); ?></a>
		</p>
	</section>
	<?php
}
add_action( 'woocommerce_after_single_product_summary', 'famma_child_product_review_summary', 12 );
