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
 * Réordonne le résumé produit.
 *
 * Ordre voulu : titre · note · prix · économie · réassurance · accroche ·
 * commande express · ajout au panier · WhatsApp.
 *
 * Le prix passe juste sous le titre (priorité 7), avant l'accroche : venu
 * d'une publicité, le visiteur cherche d'abord « combien ? ». Placée entre les
 * deux, l'accroche repoussait le prix à 973 px, soit 1,4 écran sur mobile
 * (audit du 23/09, FP-03). La note suit le titre en 6, elle n'apparaît
 * qu'avec de vrais avis.
 *
 * La réassurance (8) reste **avant** le bouton d'achat : le client lit comment
 * il paie, qui le livre et en combien de temps au moment où il hésite.
 * L'accroche (20) vient ensuite, juste avant le formulaire express (26). La
 * carte WhatsApp reste sous le bouton : au-dessus, elle concurrencerait le CTA
 * qui fait la vente.
 *
 * @return void
 */
function famma_child_product_reorder_summary(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
	add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 6 );

	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
	add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 7 );

	remove_action( 'woocommerce_single_product_summary', 'famma_child_price_savings', 11 );
	add_action( 'woocommerce_single_product_summary', 'famma_child_price_savings', 7 );

	remove_action( 'woocommerce_single_product_summary', 'famma_child_product_reassurance', 35 );
	add_action( 'woocommerce_single_product_summary', 'famma_child_product_reassurance', 8 );

	// L'accroche reste à sa priorité d'origine (20) : après la réassurance, avant le formulaire.
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
	// Libellé saisi dans FAMMA → Pages → Fiche produit ; vidé, le bouton disparaît.
	$label = famma_child_product_copy( 'whatsapp_card' );

	if ( '' === famma_child_whatsapp_number() || '' === $label ) {
		return;
	}

	echo '<div class="famma-product__whatsapp">';
	echo do_shortcode( '[famma_whatsapp text="' . esc_attr( $label ) . '"]' );
	echo '</div>';
}

/**
 * Libellé du bouton « Ajouter au panier » de la fiche, saisi dans l'administration.
 *
 * @param string $text Libellé de WooCommerce.
 * @return string
 */
function famma_child_single_add_to_cart_text( $text ) {
	$label = famma_child_product_copy( 'cta_cart' );

	return '' !== $label ? $label : $text;
}
add_filter( 'woocommerce_product_single_add_to_cart_text', 'famma_child_single_add_to_cart_text', 20 );
add_action( 'woocommerce_single_product_summary', 'famma_child_product_whatsapp_card', 36 );

/**
 * Badge vert « Nouveau », posé sur la photo.
 *
 * Même règle que sur la carte : la fraîcheur se lit sur la date de publication,
 * et un produit déjà en promotion ne le porte pas — la remise est le message
 * qui vend.
 *
 * Sur la photo et non plus en tête du résumé : il y prenait une ligne de
 * 43 px au-dessus du titre, dans le premier écran mobile. Même emplacement que
 * le badge de promotion de WooCommerce (`woocommerce_show_product_sale_flash`,
 * priorité 10), que la feuille de la fiche positionne sur l'image.
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
		'<span class="famma-badge--new famma-badge--media">%s</span>',
		esc_html__( 'Nouveau', 'famma-child' )
	);
}
add_action( 'woocommerce_before_single_product_summary', 'famma_child_product_new_badge', 11 );

/**
 * Encadre le champ de quantité pour reproduire le sélecteur MOINS / PLUS.
 *
 * La maquette pose un bloc unique bordé : « MOINS », la valeur, « PLUS ».
 * WooCommerce rend un `input[type=number]` seul ; les flèches natives du
 * navigateur sont minuscules sur mobile et hors de la cible tactile de 48 px
 * (§9).
 *
 * Les deux boutons ne remplacent pas le champ, ils l'entourent : sans
 * JavaScript, le client saisit toujours la quantité au clavier et le formulaire
 * fonctionne. Le script ne fait qu'incrémenter la valeur du champ.
 *
 * @return void
 */
function famma_child_quantity_open(): void {
	echo '<div class="famma-qty" data-famma-qty>';
	printf(
		'<button type="button" class="famma-qty__btn" data-famma-qty-step="down" aria-label="%s">−</button>',
		esc_attr__( 'Diminuer la quantité', 'famma-child' )
	);
}
add_action( 'woocommerce_before_add_to_cart_quantity', 'famma_child_quantity_open' );

/**
 * Ferme le sélecteur de quantité.
 *
 * Priorité 5 : la ligne « En stock » s'accroche au même hook en priorité 10 et
 * doit rester **en dehors** du bloc bordé, comme sur la maquette.
 *
 * @return void
 */
function famma_child_quantity_close(): void {
	printf(
		'<button type="button" class="famma-qty__btn" data-famma-qty-step="up" aria-label="%s">+</button>',
		esc_attr__( 'Augmenter la quantité', 'famma-child' )
	);
	echo '</div>';
}
add_action( 'woocommerce_after_add_to_cart_quantity', 'famma_child_quantity_close', 5 );

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
	/*
	 * Les textes viennent de FAMMA → Pages → Fiche produit ; seules les icônes
	 * restent ici, elles relèvent du dessin. Un bénéfice sans titre disparaît,
	 * et le quatrième n'existe que si un numéro WhatsApp est configuré.
	 */
	$icons = array(
		1 => 'card',
		2 => 'truck',
		3 => 'user',
		4 => 'chat',
	);

	$benefits = array();

	foreach ( $icons as $n => $icon ) {
		if ( 4 === $n && '' === famma_child_whatsapp_number() ) {
			continue;
		}

		$title = famma_child_product_copy( 'benefit_' . $n . '_title' );

		if ( '' === $title ) {
			continue;
		}

		$benefits[] = array(
			'icon'  => $icon,
			'title' => $title,
			'text'  => famma_child_product_copy( 'benefit_' . $n . '_text' ),
		);
	}

	if ( empty( $benefits ) ) {
		return;
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

	$delay = famma_child_delivery_delay();

	if ( '' !== $delay ) {
		printf( '<p class="famma-product__delay"><strong>%s</strong></p>', esc_html( $delay ) );
	}

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

/**
 * Attributs affichables du produit, mis à plat pour le tableau de specs.
 *
 * Source unique : les attributs produit de WooCommerce. Rien n'est écrit en
 * dur — §60 interdit la donnée d'exemple, et un tableau de caractéristiques
 * inventé est exactement le genre de contenu qui se retourne contre la
 * boutique à la livraison.
 *
 * Les attributs masqués sont ignorés : « masqué » est une décision du
 * propriétaire dans l'administration, pas un accident à rattraper ici.
 *
 * @param \WC_Product $product Produit courant.
 * @return array<int, array<string, string>> Lignes `label` / `value`.
 */
function famma_child_product_spec_rows( WC_Product $product ): array {
	$rows = array();

	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_visible() ) {
			continue;
		}

		$label = wc_attribute_label( $attribute->get_name(), $product );

		if ( $attribute->is_taxonomy() ) {
			$terms  = wc_get_product_terms(
				$product->get_id(),
				$attribute->get_name(),
				array( 'fields' => 'names' )
			);
			$values = is_array( $terms ) ? $terms : array();
		} else {
			$values = $attribute->get_options();
		}

		$values = array_filter(
			array_map( 'trim', array_map( 'strval', (array) $values ) ),
			'strlen'
		);

		if ( '' === trim( (string) $label ) || array() === $values ) {
			continue;
		}

		$rows[] = array(
			'label' => (string) $label,
			'value' => implode( ', ', $values ),
		);
	}

	return $rows;
}

/**
 * Ajoute l'onglet « Spécifications ».
 *
 * Remplace l'onglet « Informations complémentaires » de WooCommerce plutôt
 * que de s'ajouter à côté : les deux liraient les mêmes attributs, et le
 * client verrait deux fois le même tableau sous deux noms différents.
 *
 * L'onglet n'existe pas si le produit n'a aucun attribut visible. Un onglet
 * « Spécifications » vide est pire qu'absent : il promet une information
 * précise et livre du blanc.
 *
 * @param array<string, array<string, mixed>> $tabs Onglets existants.
 * @return array<string, array<string, mixed>>
 */
function famma_child_product_specs_tab( array $tabs ): array {
	$product = wc_get_product();

	if ( ! $product instanceof WC_Product ) {
		return $tabs;
	}

	if ( array() === famma_child_product_spec_rows( $product ) ) {
		return $tabs;
	}

	unset( $tabs['additional_information'] );

	$tabs['famma_specs'] = array(
		'title'    => __( 'Spécifications', 'famma-child' ),
		'priority' => 15,
		'callback' => 'famma_child_product_specs_panel',
	);

	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'famma_child_product_specs_tab' );

/**
 * Contenu de l'onglet « Spécifications ».
 *
 * Une liste de définitions plutôt qu'un `<table>` : ce sont des paires
 * clé/valeur, pas un tableau de données à deux dimensions. La grille CSS leur
 * donne l'apparence tabulaire de la maquette sans mentir sur la structure.
 *
 * @return void
 */
function famma_child_product_specs_panel(): void {
	$product = wc_get_product();

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$rows = famma_child_product_spec_rows( $product );

	if ( array() === $rows ) {
		return;
	}

	echo '<dl class="famma-specs">';

	foreach ( $rows as $row ) {
		printf(
			'<div class="famma-specs__row"><dt class="famma-specs__key">%1$s</dt><dd class="famma-specs__value">%2$s</dd></div>',
			esc_html( $row['label'] ),
			esc_html( $row['value'] )
		);
	}

	echo '</dl>';
}

/**
 * Impose l'ordre des onglets de la maquette.
 *
 * Description · Spécifications · Avis · FAQ · Livraison.
 *
 * Posé en un seul endroit et en fin de chaîne, après toutes les inscriptions :
 * autrement chaque onglet porte sa priorité et l'ordre final devient le
 * résultat d'un calcul réparti dans cinq fonctions. Les onglets absents sont
 * ignorés — la fonction ne crée rien, elle ne fait que classer.
 *
 * @param array<string, array<string, mixed>> $tabs Onglets existants.
 * @return array<string, array<string, mixed>>
 */
function famma_child_product_tabs_order( array $tabs ): array {
	/*
	 * Livraison juste après la description et la fiche technique : c'est la
	 * première objection d'un achat payé à la livraison. Elle arrivait en
	 * dernier, à 8,8 écrans sur mobile (audit du 23/09, FP-10).
	 */
	$order = array(
		'description'    => 10,
		'famma_specs'    => 15,
		'famma_shipping' => 20,
		'famma_faq'      => 25,
		'reviews'        => 30,
	);

	foreach ( $order as $key => $priority ) {
		if ( isset( $tabs[ $key ] ) ) {
			$tabs[ $key ]['priority'] = $priority;
		}
	}

	/*
	 * « Avis (0) » disait au visiteur que personne n'avait acheté, et ouvrait
	 * à tous un formulaire nom + e-mail : une porte pour le spam. La section
	 * n'apparaît qu'à partir du premier avis.
	 */
	$product = wc_get_product();

	if ( isset( $tabs['reviews'] ) && $product instanceof WC_Product && 0 === (int) $product->get_review_count() ) {
		unset( $tabs['reviews'] );
	}

	return $tabs;
}

/**
 * Retire le titre « Description » répété dans la section du même nom.
 *
 * L'intitulé de l'accordéon dit déjà « Description » : WooCommerce ajoutait
 * un H2 identique juste dessous.
 *
 * @return string
 */
function famma_child_product_description_heading(): string {
	return '';
}
add_filter( 'woocommerce_product_description_heading', 'famma_child_product_description_heading' );
add_filter( 'woocommerce_product_tabs', 'famma_child_product_tabs_order', 98 );

/**
 * Paires question/réponse de la FAQ du produit.
 *
 * La méta est écrite par `famma-core` (classe `Product_Faq`) : la donnée est
 * métier, elle vit dans le plugin. Le thème la lit directement plutôt que
 * d'appeler la classe, pour ne pas dépendre du plugin — si celui-ci est
 * désactivé, la méta est simplement absente et l'onglet ne s'affiche pas,
 * sans erreur fatale.
 *
 * @param \WC_Product $product Produit courant.
 * @return array<int, array<string, string>> Paires `q` / `a`.
 */
function famma_child_product_faq_pairs( WC_Product $product ): array {
	$stored = $product->get_meta( '_famma_faq' );

	if ( ! is_array( $stored ) ) {
		return array();
	}

	$pairs = array();

	foreach ( $stored as $entry ) {
		if ( ! is_array( $entry ) || ! isset( $entry['q'], $entry['a'] ) ) {
			continue;
		}

		$question = trim( (string) $entry['q'] );
		$answer   = trim( (string) $entry['a'] );

		if ( '' === $question || '' === $answer ) {
			continue;
		}

		$pairs[] = array(
			'q' => $question,
			'a' => $answer,
		);
	}

	return $pairs;
}

/**
 * Ajoute l'onglet « FAQ ».
 *
 * Absent tant que le propriétaire n'a saisi aucune paire : §60 interdit la
 * question d'exemple, et un accordéon vide donne au client l'impression que
 * la page est cassée.
 *
 * @param array<string, array<string, mixed>> $tabs Onglets existants.
 * @return array<string, array<string, mixed>>
 */
function famma_child_product_faq_tab( array $tabs ): array {
	$product = wc_get_product();

	if ( ! $product instanceof WC_Product ) {
		return $tabs;
	}

	if ( array() === famma_child_product_faq_pairs( $product ) ) {
		return $tabs;
	}

	$tabs['famma_faq'] = array(
		'title'    => __( 'FAQ', 'famma-child' ),
		'priority' => 25,
		'callback' => 'famma_child_product_faq_panel',
	);

	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'famma_child_product_faq_tab' );

/**
 * Contenu de l'onglet « FAQ ».
 *
 * `<details>` / `<summary>` du HTML natif : l'accordéon fonctionne sans une
 * ligne de JavaScript, reste ouvrable au clavier, et le contenu est trouvable
 * par la recherche du navigateur. Un accordéon maison aurait demandé un
 * script, des attributs ARIA à tenir à jour, et se serait cassé si le script
 * ne se chargeait pas — sur mobile tunisien, ce cas arrive.
 *
 * @return void
 */
function famma_child_product_faq_panel(): void {
	$product = wc_get_product();

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$pairs = famma_child_product_faq_pairs( $product );

	if ( array() === $pairs ) {
		return;
	}

	echo '<div class="famma-faq">';

	foreach ( $pairs as $pair ) {
		printf(
			'<details class="famma-faq__item"><summary class="famma-faq__question">%1$s</summary><div class="famma-faq__answer">%2$s</div></details>',
			esc_html( $pair['q'] ),
			esc_html( $pair['a'] )
		);
	}

	echo '</div>';
}

/**
 * Active les flèches de la galerie produit (P-3).
 *
 * WooCommerce livre flexslider avec `directionNav => false`. La maquette pose
 * deux flèches rondes de 32px de part et d'autre de la rangée de vignettes ;
 * elles ne peuvent pas être ajoutées en CSS seul, le balisage n'existe pas
 * tant que l'option est fausse.
 *
 * `allowOneSlide` reste à `false` côté WooCommerce : un produit à une seule
 * image n'initialise pas le carrousel, donc n'affiche ni vignette ni flèche.
 * C'est le comportement voulu — pas de commande de navigation là où il n'y a
 * rien à naviguer.
 *
 * @param array<string, mixed> $options Options flexslider.
 * @return array<string, mixed>
 */
function famma_child_gallery_carousel_options( array $options ): array {
	$options['directionNav'] = true;

	/*
	 * Flexslider nomme ses flèches « Previous » / « Next », en anglais, et
	 * c'est ce que lisaient les lecteurs d'écran sur les pages FR et AR. Il
	 * insère ce texte tel quel dans du HTML : il est donc échappé ici.
	 */
	$options['prevText'] = esc_html__( 'Photo précédente', 'famma-child' );
	$options['nextText'] = esc_html__( 'Photo suivante', 'famma-child' );

	return $options;
}
add_filter( 'woocommerce_single_product_carousel_options', 'famma_child_gallery_carousel_options' );
