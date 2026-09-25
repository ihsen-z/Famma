<?php
/**
 * FAMMA — boutique (archive produit).
 *
 * Structure et habillage seulement. Aucune logique de requête n'est
 * réimplémentée : le tri, la pagination, le compte de résultats et les filtres
 * restent ceux de WooCommerce. Un filtre maison serait un filtre à maintenir,
 * à traduire et à déboguer, pour un résultat moins bon.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Déclare la colonne de filtres de la boutique.
 *
 * Une barre latérale plutôt qu'un panneau codé en dur : le propriétaire y pose
 * les widgets de filtre de WooCommerce (catégories, prix, note, attributs,
 * marque) et c'est WooCommerce qui possède la requête. `scripts/create-shop-filters.php`
 * la remplit avec un jeu par défaut, de façon idempotente.
 *
 * @return void
 */
function famma_child_register_shop_sidebar(): void {
	/*
	 * Chaque widget est une SECTION, pas une carte : la maquette n'en pose
	 * qu'une, avec des filets internes entre les sections. Six cartes
	 * empilées donneraient six bordures et six ombres là où le dessin n'en
	 * veut qu'une.
	 */
	register_sidebar(
		array(
			'name'          => __( 'Boutique — filtres', 'famma-child' ),
			'id'            => 'famma-shop-filters',
			'description'   => __( 'Sections de filtre affichées dans la carte, à côté de la grille produits.', 'famma-child' ),
			'before_widget' => '<section id="%1$s" class="famma-filter %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="famma-filter__title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'famma_child_register_shop_sidebar' );

/**
 * Les trois promesses affichées au-dessus de la grille.
 *
 * Même source que la barre supérieure : une promesse écrite deux fois est une
 * promesse qui finira par diverger.
 *
 * @return void
 */
function famma_child_shop_promises(): void {
	$promises = famma_child_topbar_promises();

	if ( array() === $promises ) {
		return;
	}
	?>
	<ul class="famma-promises">
		<?php foreach ( $promises as $promise ) : ?>
			<li class="famma-promises__item">
				<?php famma_child_the_icon( $promise['icon'] ); ?>
				<span><?php echo esc_html( $promise['label'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * Affiche la barre d'outils : tri et bascule grille/liste.
 *
 * Le tri est celui de WooCommerce (`woocommerce_catalog_ordering`), seulement
 * rhabillé. La bascule d'affichage est purement visuelle : elle ne touche pas
 * la requête, donc elle n'a pas à passer par le serveur.
 *
 * @return void
 */
function famma_child_shop_toolbar(): void {
	?>
	<div class="famma-shop__toolbar">
		<?php woocommerce_catalog_ordering(); ?>

		<div class="famma-viewswitch" role="group" aria-label="<?php esc_attr_e( 'Affichage des produits', 'famma-child' ); ?>">
			<button type="button" class="famma-viewswitch__button is-active" data-famma-view="grid" aria-pressed="true">
				<svg class="famma-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg>
				<span class="screen-reader-text"><?php esc_html_e( 'Affichage en grille', 'famma-child' ); ?></span>
			</button>
			<button type="button" class="famma-viewswitch__button" data-famma-view="list" aria-pressed="false">
				<svg class="famma-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
				<span class="screen-reader-text"><?php esc_html_e( 'Affichage en liste', 'famma-child' ); ?></span>
			</button>
		</div>
	</div>
	<?php
}

/**
 * Affiche la colonne de filtres, précédée de son bouton mobile.
 *
 * Le bouton n'apparaît que s'il y a quelque chose à déplier : un bouton
 * « Filtres » qui ouvre une colonne vide est pire que pas de bouton.
 *
 * @return void
 */
function famma_child_shop_sidebar(): void {
	if ( ! is_active_sidebar( 'famma-shop-filters' ) ) {
		return;
	}
	?>
	<button
		class="famma-shop__filters-toggle"
		type="button"
		aria-expanded="false"
		aria-controls="famma-shop-filters"
	>
		<svg class="famma-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
		<span><?php esc_html_e( 'Filtres', 'famma-child' ); ?></span>
	</button>

	<aside class="famma-shop__aside" id="famma-shop-filters" hidden>
		<div class="famma-filters">
			<div class="famma-filters__head">
				<p class="famma-filters__title"><?php esc_html_e( 'Filtres', 'famma-child' ); ?></p>
				<?php
				/*
				 * « Réinitialiser » est un lien vers la boutique nue, pas un
				 * bouton : il rétablit un état de navigation, il doit donc
				 * s'ouvrir dans un onglet et se partager comme n'importe
				 * quelle adresse.
				 */
				?>
				<a class="famma-filters__reset" href="<?php echo esc_url( famma_child_filter_url( '', '' ) ); ?>">
					<?php esc_html_e( 'Réinitialiser', 'famma-child' ); ?>
				</a>
			</div>

			<?php dynamic_sidebar( 'famma-shop-filters' ); ?>
		</div>

		<?php famma_child_shop_help_card(); ?>
	</aside>
	<?php
}

/**
 * Carte d'aide WhatsApp de la colonne de filtres.
 *
 * Rendue seulement si un numéro est configuré — sans quoi le bouton mènerait
 * nulle part (§60).
 *
 * @return void
 */
function famma_child_shop_help_card(): void {
	$number = famma_child_whatsapp_number();

	if ( '' === $number ) {
		return;
	}
	?>
	<div class="famma-help-card">
		<span class="famma-help-card__mark"><?php famma_child_the_icon( 'chat' ); ?></span>
		<p class="famma-help-card__title"><?php esc_html_e( 'Besoin d\'aide ?', 'famma-child' ); ?></p>
		<p class="famma-help-card__text">
			<?php esc_html_e( 'Notre équipe vous répond sur WhatsApp et vous aide à choisir.', 'famma-child' ); ?>
		</p>
		<a class="famma-help-card__cta" href="<?php echo esc_url( 'https://wa.me/' . rawurlencode( $number ) ); ?>"
			rel="noopener noreferrer" target="_blank">
			<?php esc_html_e( 'Écrire sur WhatsApp', 'famma-child' ); ?>
		</a>
	</div>
	<?php
}

/**
 * Bande d'appel WhatsApp en bas de boutique.
 *
 * Remplace la bande newsletter de la maquette (arbitrage C-5). Motif : aucun
 * service d'emailing n'est configuré, et un champ qui ne mène nulle part est
 * pire que pas de champ. WhatsApp est de toute façon le canal réel du COD
 * tunisien — le visiteur y obtient une réponse, pas un accusé de réception.
 *
 * @return void
 */
function famma_child_shop_whatsapp_band(): void {
	$number = famma_child_whatsapp_number();

	if ( '' === $number ) {
		return;
	}
	?>
	<aside class="famma-band">
		<div class="famma-band__text">
			<p class="famma-band__title"><?php esc_html_e( 'Une question sur un produit ?', 'famma-child' ); ?></p>
			<p class="famma-band__sub">
				<?php esc_html_e( 'Écrivez-nous sur WhatsApp : on vous répond et on vous aide à choisir avant de commander.', 'famma-child' ); ?>
			</p>
		</div>
		<a class="famma-band__cta" href="<?php echo esc_url( 'https://wa.me/' . rawurlencode( $number ) ); ?>"
			rel="noopener noreferrer" target="_blank">
			<?php famma_child_the_icon( 'chat' ); ?>
			<span><?php esc_html_e( 'Discuter sur WhatsApp', 'famma-child' ); ?></span>
		</a>
	</aside>
	<?php
}

/**
 * Remonte le bénéfice produit au-dessus de la note.
 *
 * Ordre de la maquette : nom, bénéfice, étoiles, prix, bouton. Le bénéfice
 * était accroché en priorité 6, donc après la note (5). Un client qui scanne
 * la grille lit « à quoi ça sert » avant « combien d'avis ».
 *
 * @return void
 */
function famma_child_reorder_loop_benefit(): void {
	if ( ! has_action( 'woocommerce_after_shop_loop_item_title', 'famma_child_loop_benefit' ) ) {
		return;
	}

	remove_action( 'woocommerce_after_shop_loop_item_title', 'famma_child_loop_benefit', 6 );
	add_action( 'woocommerce_after_shop_loop_item_title', 'famma_child_loop_benefit', 4 );
}
add_action( 'wp', 'famma_child_reorder_loop_benefit' );

/**
 * Retire un rappel du thème parent accroché à un hook WooCommerce.
 *
 * Kadence enregistre ses rappels sous forme de méthodes d'objet. `kadence()`
 * n'existe pas dans tous les contextes — vérifié à l'exécution — et un
 * `remove_action` qui ne retrouve pas l'instance échoue en silence. On la
 * retrouve donc dans le registre de hooks lui-même.
 *
 * @param string $hook   Nom du hook.
 * @param string $method Méthode à détacher.
 * @return void
 */
function famma_child_remove_parent_callback( string $hook, string $method ): void {
	global $wp_filter;

	if ( ! isset( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) ) {
		return;
	}

	foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$function = $callback['function'];

			if ( ! is_array( $function ) || ! is_object( $function[0] ) ) {
				continue;
			}

			if ( 0 !== strpos( get_class( $function[0] ), 'Kadence\\' ) || $method !== $function[1] ) {
				continue;
			}

			remove_action( $hook, array( $function[0], $function[1] ), (int) $priority );
		}
	}
}

/**
 * Retire la barre d'outils que Kadence pose au-dessus de la grille.
 *
 * Constaté au rendu : la boutique affichait **deux sélecteurs de tri**, le
 * nôtre dans la barre d'outils de la maquette et celui de Kadence dans son
 * `.kadence-shop-top-row`. Retirer `woocommerce_catalog_ordering` du hook ne
 * suffisait pas : Kadence n'utilise pas ce rappel, il rend sa propre rangée.
 *
 * @return void
 */
function famma_child_remove_parent_shop_toolbar(): void {
	if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
		return;
	}

	famma_child_remove_parent_callback( 'woocommerce_before_shop_loop', 'archive_loop_top' );

	/*
	 * Le compte de résultats vivait DANS cette rangée : le retirer l'emportait
	 * avec lui. Constaté au rendu — plus aucun « x résultats affichés ». On le
	 * remet à sa place d'origine dans WooCommerce, au-dessus de la grille.
	 */
	if ( ! has_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count' ) ) {
		add_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
	}

	/*
	 * Kadence rend son propre titre d'archive, dans un bloc placé AVANT notre
	 * conteneur — donc hors de la barre d'outils de la maquette, qui aligne le
	 * titre et le tri sur la même ligne. Il le fait après avoir désactivé le
	 * titre natif par `woocommerce_show_page_title`. On détache le sien.
	 *
	 * Le filtre `woocommerce_show_page_title` reste à `false` : le réactiver
	 * faisait rendre en plus l'en-tête d'archive natif de WooCommerce, soit
	 * **trois** `<h1>` sur la page. Notre gabarit appelle directement
	 * `woocommerce_page_title()`, sans passer par ce garde-fou.
	 */
	famma_child_remove_parent_callback( 'woocommerce_before_main_content', 'output_product_above_title' );
}
add_action( 'wp', 'famma_child_remove_parent_shop_toolbar' );

/**
 * Bouton « favori » posé sur la carte produit.
 *
 * La maquette met un cœur sur chaque carte. Je l'avais d'abord omis : un cœur
 * qui ne mémorise rien est un bouton qui ment. Il est ici **réellement
 * fonctionnel** — la sélection est gardée dans le navigateur du visiteur
 * (`localStorage`), donc elle survit à la navigation et au rechargement, sans
 * compte à créer ni donnée personnelle envoyée au serveur.
 *
 * Accroché avant l'ouverture du lien produit (priorité 9) : un `<button>` à
 * l'intérieur d'un `<a>` est du HTML invalide, et les lecteurs d'écran comme
 * les navigateurs s'y perdent. Le bouton est donc frère du lien, posé
 * au-dessus de l'image en absolu.
 *
 * @return void
 */
function famma_child_loop_favourite(): void {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	printf(
		'<button type="button" class="famma-fav" data-famma-fav="%1$s" aria-pressed="false">'
		. '<svg class="famma-fav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" aria-hidden="true" focusable="false">'
		. '<path d="M12 20.3l-1.4-1.3C5.4 14.4 2 11.3 2 7.6 2 4.9 4.1 3 6.8 3c1.6 0 3.2.8 4.2 2 1-1.2 2.6-2 4.2-2C18.9 3 21 4.9 21 7.6c0 3.7-3.4 6.8-8.6 11.4z"/></svg>'
		. '<span class="screen-reader-text famma-fav__label">%2$s</span></button>',
		esc_attr( (string) $product->get_id() ),
		esc_html__( 'Ajouter aux favoris', 'famma-child' )
	);
}
add_action( 'woocommerce_before_shop_loop_item', 'famma_child_loop_favourite', 9 );

/**
 * Rend le bouton d'ajout au panier visible en permanence sur les cartes.
 *
 * Kadence pose la classe `woo-archive-action-on-hover` sur `ul.products`, qui
 * déclenche trois règles : le bouton passe en `position: absolute; opacity: 0`,
 * et au survol le bloc de texte glisse de `-2rem` pour lui faire place. Résultat
 * constaté au rendu : le bouton n'existe pas tant qu'on ne survole pas, puis il
 * **recouvre le prix** en apparaissant.
 *
 * La maquette montre le bouton en permanence, sous le prix. Sur une boutique
 * mobile-first, c'est d'ailleurs le seul comportement praticable : il n'y a pas
 * de survol au doigt, donc pas de bouton du tout.
 *
 * On échange la classe pour l'autre mode que Kadence sait rendre —
 * `woo-archive-btn-button` — plutôt que de neutraliser ses trois règles en CSS :
 * la présentation reste la sienne, seul le mode change.
 *
 * @param string $html Ouverture de la liste produite par WooCommerce.
 * @return string
 */
function famma_child_always_show_add_to_cart( $html ): string {
	$html = str_replace(
		'woo-archive-action-on-hover',
		'woo-archive-btn-button',
		(string) $html
	);

	/*
	 * `famma-cards` est la portée unique de `assets/css/product-card.css`.
	 * Posée ici et dans `inc/home.php`, elle fait tenir le dessin de la carte
	 * en un seul jeu de règles — boutique, archives de catégorie, produits
	 * liés et accueil. Sans elle, chaque contexte avait sa copie, et l'accueil
	 * n'en recevait aucune.
	 */
	return str_replace( 'class="products', 'class="famma-cards products', $html );
}
add_filter( 'woocommerce_product_loop_start', 'famma_child_always_show_add_to_cart', 20 );

/**
 * Badge vert « Nouveau » sur les produits récemment publiés.
 *
 * La maquette pose deux badges : la remise en orange, « Nouveau » en vert.
 * WooCommerce ne fournit que le premier.
 *
 * Ce n'est pas une donnée inventée : la fraîcheur se lit sur la date de
 * publication du produit. Un article en promotion ne le porte pas — deux
 * badges superposés dans le même coin d'image seraient illisibles, et la
 * remise est le message qui vend.
 *
 * @return void
 */
function famma_child_loop_new_badge(): void {
	global $product;

	if ( ! $product instanceof WC_Product || $product->is_on_sale() ) {
		return;
	}

	/**
	 * Fenêtre de nouveauté, en jours.
	 *
	 * @param int $days Nombre de jours.
	 */
	$days    = (int) apply_filters( 'famma_child_new_badge_days', 30 );
	$created = $product->get_date_created();

	if ( ! $created instanceof WC_DateTime ) {
		return;
	}

	if ( ( time() - $created->getTimestamp() ) > $days * DAY_IN_SECONDS ) {
		return;
	}

	printf(
		'<span class="onsale famma-badge famma-badge--new">%s</span>',
		esc_html__( 'Nouveau', 'famma-child' )
	);
}
add_action( 'woocommerce_before_shop_loop_item_title', 'famma_child_loop_new_badge', 11 );

/**
 * Note et nombre d'avis sur la carte, comme dans la maquette.
 *
 * `woocommerce_template_loop_rating` n'affiche que les étoiles. La maquette
 * pose « ★★★★★ (128) » — le nombre d'avis est ce qui rend la note crédible :
 * cinq étoiles sur un seul avis ne valent pas cinq étoiles sur cent.
 *
 * Rien n'est affiché tant qu'aucun avis n'existe : une rangée d'étoiles vides
 * ferait croire à une mauvaise note plutôt qu'à une absence de note.
 *
 * @return void
 */
function famma_child_loop_rating(): void {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$count = (int) $product->get_review_count();

	if ( 0 === $count ) {
		return;
	}

	printf(
		'<div class="famma-card__rating">%1$s<span class="famma-card__reviews">(%2$s)</span></div>',
		wp_kses_post( wc_get_rating_html( (float) $product->get_average_rating(), $count ) ),
		esc_html( number_format_i18n( $count ) )
	);
}

/**
 * Remplace la note native par la nôtre.
 *
 * @return void
 */
function famma_child_replace_loop_rating(): void {
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
	add_action( 'woocommerce_after_shop_loop_item_title', 'famma_child_loop_rating', 5 );
}
add_action( 'wp', 'famma_child_replace_loop_rating' );

/**
 * Libellé du bouton des cartes : « Commander ».
 *
 * C'est le mot de la maquette, et celui que le projet a retenu comme appel à
 * l'action (« Commander / أطلب توا »). « Ajouter au panier » décrit un geste
 * d'interface ; « Commander » décrit ce que le client vient faire.
 *
 * Ne touche que la grille : le bouton de la fiche produit passe par
 * `woocommerce_product_single_add_to_cart_text`, laissé intact.
 *
 * @return string
 */
function famma_child_loop_add_to_cart_text(): string {
	return __( 'Commander', 'famma-child' );
}
add_filter( 'woocommerce_product_add_to_cart_text', 'famma_child_loop_add_to_cart_text', 20 );

/**
 * Accorde le nom accessible du bouton avec son libellé visible.
 *
 * WooCommerce compose l'`aria-label` à partir de sa propre formule
 * (« Ajouter au panier : “Nom du produit” »). Le libellé affiché disant
 * désormais « Commander », les deux divergeaient — or le critère WCAG 2.5.3
 * « Étiquette dans le nom » exige que le nom accessible contienne le texte
 * visible, sans quoi la commande vocale « clique sur Commander » échoue.
 *
 * @param string     $description Description produite par WooCommerce.
 * @param WC_Product $product     Produit concerné.
 * @return string
 */
function famma_child_loop_add_to_cart_description( $description, $product ): string {
	if ( ! $product instanceof WC_Product ) {
		return (string) $description;
	}

	return sprintf(
		/* translators: %s: nom du produit. */
		__( 'Commander : %s', 'famma-child' ),
		$product->get_name()
	);
}
add_filter( 'woocommerce_product_add_to_cart_description', 'famma_child_loop_add_to_cart_description', 20, 2 );

/**
 * Place l'icône panier de la maquette devant le libellé du bouton.
 *
 * Kadence insère bien une icône, mais **une flèche, placée après le texte** —
 * constaté au rendu : « Ajouter au panier → ». La maquette pose un chariot
 * avant le mot. Les pictogrammes du parent sont donc masqués en CSS (ils
 * portent aussi les états AJAX, sans valeur visuelle ici) et le chariot est
 * inséré juste après le `<a …>` ouvrant.
 *
 * L'icône seule est insérée : envelopper le reste du lien dans un `<span>`
 * sortait les libellés d'état de leur positionnement et faisait passer le
 * bouton sur deux lignes.
 *
 * @param string $html Lien produit par WooCommerce.
 * @return string
 */
function famma_child_loop_add_to_cart_icon( $html ): string {
	$html = (string) $html;

	if ( '' === $html || false === strpos( $html, '</a>' ) ) {
		return $html;
	}

	$position = strpos( $html, '>' );

	if ( false === $position ) {
		return $html;
	}

	$icon = '<svg class="famma-card__cart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 3h3l2.6 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21.5 7H6"/><circle cx="10" cy="21" r="1.4"/><circle cx="18" cy="21" r="1.4"/></svg>';

	return substr( $html, 0, $position + 1 ) . $icon . substr( $html, $position + 1 );
}
add_filter( 'woocommerce_loop_add_to_cart_link', 'famma_child_loop_add_to_cart_icon', 20 );

/**
 * Restreint la boutique aux produits en promotion sur `?on_sale=1`.
 *
 * La maquette met « Promotions » dans la navigation principale. WooCommerce
 * n'expose aucune archive native pour cela — seulement un shortcode. Plutôt
 * qu'un lien mort ou une page à part, on filtre la requête de la boutique avec
 * `wc_get_product_ids_on_sale()`, la fonction du cœur qui fait autorité sur ce
 * qu'est une promotion (prix barré, planification, variations comprises).
 *
 * Tri, filtres, pagination et compte de résultats continuent de fonctionner :
 * on ne fait qu'ajouter une contrainte à la requête existante.
 *
 * @param WP_Query $query Requête de la boutique.
 * @return void
 */
function famma_child_filter_on_sale( $query ): void {
	if ( ! $query instanceof WP_Query ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture d'un etat de navigation, aucune ecriture.
	if ( empty( $_GET['on_sale'] ) ) {
		return;
	}

	$ids = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();

	/*
	 * Aucun produit en promotion : on force un ensemble vide plutôt que de
	 * laisser passer tout le catalogue. Un visiteur qui clique « Promotions »
	 * et tombe sur la boutique entière croit à une promotion générale.
	 */
	$query->set( 'post__in', array() === $ids ? array( 0 ) : $ids );
}
add_action( 'woocommerce_product_query', 'famma_child_filter_on_sale' );

/**
 * Nombre de colonnes de la grille.
 *
 * La maquette pose `repeat(auto-fit, minmax(196px, 1fr))` : c'est le CSS qui
 * décide, pas PHP. On garde néanmoins une valeur haute côté WooCommerce, sinon
 * il impose ses propres classes `columns-N` et sa largeur calculée.
 *
 * @return int
 */
function famma_child_loop_columns(): int {
	return 4;
}
add_filter( 'loop_shop_columns', 'famma_child_loop_columns', 20 );
