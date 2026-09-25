<?php
/**
 * FAMMA — thème enfant (parent : Kadence).
 *
 * Présentation uniquement. Aucune logique métier ici : elle vit dans le
 * plugin famma-core (§27). Ce fichier doit rester court ; s'il grossit,
 * c'est le signe qu'une règle métier s'y est glissée par erreur.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

const FAMMA_CHILD_VERSION = '0.1.0';

/**
 * Briques du gabarit partagé.
 *
 * Chargées depuis `inc/` par responsabilité : icônes, en-tête, pied de page,
 * ressources. Ce fichier reste la table des matières du thème ; le rendu du
 * gabarit vit à côté (§6 — un fichier, une responsabilité).
 *
 * Chaque fichier est testé avant d'être inclus : un thème dont un fichier
 * manque doit dégrader, pas produire une erreur fatale sur toutes les pages.
 */
foreach ( array( 'i18n', 'icons', 'catalog', 'header-parts', 'footer-parts', 'shop', 'shop-widgets', 'product', 'product-video', 'home', 'home-social', 'pages', 'tunnel', 'cart', 'checkout', 'checkout-layout', 'editorial-guards', 'assets' ) as $famma_child_part ) {
	$famma_child_file = get_stylesheet_directory() . '/inc/' . $famma_child_part . '.php';

	if ( file_exists( $famma_child_file ) ) {
		require_once $famma_child_file;
	}
}
unset( $famma_child_part, $famma_child_file );

/**
 * Charge la feuille du parent, les jetons du design system, puis la feuille enfant.
 *
 * La version de chaque feuille utilise filemtime() pour invalider le cache
 * navigateur à chaque modification réelle du fichier (règle 9).
 */
function famma_child_enqueue_styles(): void {
	$child_dir = get_stylesheet_directory();
	$child_uri = get_stylesheet_directory_uri();

	wp_enqueue_style(
		'kadence-parent',
		get_template_directory_uri() . '/style.css',
		array(),
		(string) wp_get_theme( get_template() )->get( 'Version' )
	);

	$tokens = $child_dir . '/assets/css/tokens.css';
	if ( file_exists( $tokens ) ) {
		wp_enqueue_style(
			'famma-tokens',
			$child_uri . '/assets/css/tokens.css',
			array( 'kadence-parent' ),
			(string) filemtime( $tokens )
		);
	}

	wp_enqueue_style(
		'famma-child',
		get_stylesheet_uri(),
		array( 'famma-tokens' ),
		(string) filemtime( $child_dir . '/style.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_styles', 20 );

/**
 * Renvoie le lockup horizontal FAMMA (planche 2a).
 *
 * Le texte est composé en HTML plutôt que gravé dans un SVG : la typo est
 * ainsi celle du site, nette à toute taille et traduisible, là où un SVG
 * textuel dépendrait de polices non embarquées. Seule l'icône, purement
 * vectorielle, vient du fichier de marque.
 *
 * @return string
 */
function famma_child_lockup_markup(): string {
	return '<span class="famma-lockup">'
		. '<svg class="famma-lockup__mark" viewBox="0 0 124 122" aria-hidden="true" focusable="false">'
		. '<path d="M34 28 H74 A20 20 0 0 1 94 48 V76 A20 20 0 0 1 74 96 H60 L50 114 L42 96 H34 A20 20 0 0 1 14 76 V48 A20 20 0 0 1 34 28 Z" fill="none" stroke="currentColor" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>'
		. '<path d="M54 28 V17" stroke="#FF7A00" stroke-width="7" stroke-linecap="round"/>'
		. '<circle cx="54" cy="10" r="8" fill="#FF7A00"/>'
		. '<rect x="36" y="50" width="10" height="20" rx="5" fill="#FF7A00"/>'
		. '<path d="M74 52 L64 61 L74 70" fill="none" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>'
		. '<path d="M44 76 Q54 87 64 76" fill="none" stroke="currentColor" stroke-width="7" stroke-linecap="round"/>'
		. '<g stroke="#FF7A00" stroke-width="6.5" stroke-linecap="round">'
		. '<path d="M104 22 L114 12"/><path d="M110 36 L122 32"/><path d="M100 11 L104 2"/>'
		. '</g></svg>'
		. '<span class="famma-lockup__text">'
		. '<span class="famma-lockup__word">FAMM<span class="famma-lockup__a">A</span></span>'
		. '<span class="famma-lockup__tagline" lang="ar" dir="rtl">'
		. '<span class="famma-lockup__ar">فمّا</span> ديما حاجة جديدة'
		. '</span></span></span>';
}

/**
 * Affiche le lockup FAMMA tant qu'aucun logo n'est défini dans le personnalisateur.
 *
 * Un logo téléversé par le propriétaire l'emporte toujours : ce repli ne
 * remplace jamais un choix explicite (§7 — ne pas modifier le logo sans
 * autorisation).
 *
 * @param string $html Sortie du logo produite par le thème.
 * @return string
 */
function famma_child_brand_logo( $html ) {
	return '' === trim( (string) $html ) ? famma_child_lockup_markup() : $html;
}
add_filter( 'kadence_custom_logo', 'famma_child_brand_logo' );

/**
 * Affiche la barre de liens légaux en bas de page (§8, §45).
 *
 * Rendue ici plutôt que par le constructeur de pied de page du thème :
 * assigner un menu à l'emplacement `footer` ne suffit pas, Kadence
 * n'affiche cet élément que si sa rangée de pied de page le contient.
 * Cette version ne dépend d'aucun réglage du thème et se teste.
 *
 * @return void
 */
function famma_child_legal_bar(): void {
	if ( ! has_nav_menu( 'footer' ) ) {
		return;
	}

	echo '<nav class="famma-legal" aria-label="' . esc_attr__( 'Informations légales', 'famma-child' ) . '">';
	wp_nav_menu(
		array(
			'theme_location' => 'footer',
			'container'      => false,
			'menu_class'     => 'famma-legal__list',
			'depth'          => 1,
			'fallback_cb'    => false,
		)
	);
	echo '</nav>';
}

/*
 * Plus accrochée à `wp_footer` : depuis que le thème enfant fournit son
 * propre `footer.php`, la barre légale a sa vraie place dans la barre basse
 * du pied de page, appelée par `famma_child_footer_bottom()`. La laisser
 * aussi sur `wp_footer` la rendrait deux fois.
 */

/**
 * Déclare l'icône du site (§7).
 *
 * Le favicon est servi en SVG : une seule source, nette à toute densité,
 * et pas de rastérisation — l'extension Imagick du conteneur n'a pas de
 * délégué SVG de toute façon. `site_icon` de WordPress n'est pas utilisé
 * puisqu'il exige une pièce jointe matricielle.
 *
 * @return void
 */
function famma_child_head_icons(): void {
	$img = get_stylesheet_directory_uri() . '/assets/img/';

	printf( '<link rel="icon" type="image/svg+xml" href="%s" />' . "\n", esc_url( $img . 'famma-favicon.svg' ) );
	printf( '<link rel="apple-touch-icon" href="%s" />' . "\n", esc_url( $img . 'famma-favicon.svg' ) );
	printf( '<meta name="theme-color" content="%s" />' . "\n", esc_attr( '#0B1F3A' ) );
}

/**
 * Déclare les métadonnées de partage social (§16).
 *
 * Volontairement minimal, et neutralisé dès qu'une extension SEO est active :
 * deux jeux de balises Open Graph concurrents valent moins qu'aucun.
 *
 * @return void
 */
function famma_child_open_graph(): void {
	if ( class_exists( '\Famma\Core\Seo' ) ) {
		// Le plugin famma-core est le propriétaire unique du `<head>` :
		// il émet description, Open Graph et données structurées pour
		// toutes les pages. Deux jeux concurrents valent moins qu'un.
		return;
	}

	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
		return;
	}

	$title = is_front_page() ? get_bloginfo( 'name' ) : wp_get_document_title();
	$image = get_stylesheet_directory_uri() . '/assets/img/famma-og.png';

	printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( is_singular( 'product' ) ? 'product' : 'website' ) );
	printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( home_url( add_query_arg( array() ) ) ) );
	printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( str_replace( '-', '_', get_bloginfo( 'language' ) ) ) );
	printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
	printf( '<meta property="og:image:width" content="%s" />' . "\n", '1200' );
	printf( '<meta property="og:image:height" content="%s" />' . "\n", '630' );
	printf( '<meta name="twitter:card" content="%s" />' . "\n", 'summary_large_image' );
}
add_action( 'wp_head', 'famma_child_open_graph', 3 );
add_action( 'wp_head', 'famma_child_head_icons', 2 );

/**
 * Rend une liste de réassurance bilingue.
 *
 * Un seul rendu pour la fiche produit et le checkout : deux copies du même
 * balisage dériveraient l'une de l'autre à la première retouche.
 *
 * Inscrite dans la liste blanche de `phpcs.xml.dist` : PHPCompatibility
 * signale tout appel dont le nom commence par `fam` comme un reste de
 * l'extension `fam`, supprimée en PHP 5.1. Faux positif inévitable avec le
 * préfixe du projet.
 *
 * @param array<int, array<string, string>> $items Entrées { fr, ar }.
 * @param string                            $extra Classe de variante facultative.
 * @return void
 */
function famma_child_reassurance_list( array $items, string $extra = '' ): void {
	/*
	 * Sur le site arabe, la ligne principale est déjà traduite en derja : y
	 * ajouter la ligne arabe donnait deux fois la même promesse (« الدفع كي
	 * يوصلك » puis « الدفع عند التوصيل »).
	 */
	$bilingual = ! famma_child_is_derja();

	printf( '<ul class="famma-reassure %s">', esc_attr( $extra ) );

	foreach ( $items as $item ) {
		echo '<li class="famma-reassure__item"><span>' . esc_html( $item['fr'] ) . '</span>';
		if ( $bilingual && '' !== $item['ar'] ) {
			echo '<span class="famma-reassure__ar" lang="ar" dir="rtl">' . esc_html( $item['ar'] ) . '</span>';
		}
		echo '</li>';
	}

	echo '</ul>';
}

/**
 * Délai de livraison saisi par le propriétaire (FAMMA → Pages).
 *
 * @return string Chaîne vide tant qu'aucun délai n'est renseigné.
 */
function famma_child_delivery_delay(): string {
	// `method_exists` aussi : un thème déployé avant le plugin ne doit pas produire d'erreur fatale.
	if ( ! class_exists( '\Famma\Core\Pages_Content' ) || ! method_exists( '\Famma\Core\Pages_Content', 'delivery_delay' ) ) {
		return '';
	}

	return \Famma\Core\Pages_Content::delivery_delay();
}

/**
 * Texte de la fiche produit saisi dans FAMMA → Pages, dans la langue de la page.
 *
 * Aucun texte commercial de la fiche n'est écrit dans le thème : il vient des
 * réglages de famma-core. Sans le plugin, le bloc concerné se masque.
 *
 * @param string $key Clé du champ (voir `Famma\Core\Product_Content::schema()`).
 * @return string
 */
function famma_child_product_copy( string $key ): string {
	if ( ! class_exists( '\Famma\Core\Product_Content' ) ) {
		return '';
	}

	return \Famma\Core\Product_Content::text( $key );
}

/**
 * Version derja d'un texte de la fiche, pour la ligne affichée sous le français.
 *
 * @param string $key Clé du champ.
 * @return string Chaîne vide si la version derja n'est pas remplie.
 */
function famma_child_product_copy_derja( string $key ): string {
	if ( ! class_exists( '\Famma\Core\Product_Content' ) ) {
		return '';
	}

	return \Famma\Core\Product_Content::derja( $key );
}

/**
 * Lignes de réassurance saisies par le propriétaire.
 *
 * Paiement et livraison viennent de FAMMA → Pages → Fiche produit, le délai
 * **tel qu'il l'a saisi** dans la section Contact. Une ligne vidée disparaît ;
 * tant que le délai est vide, aucun n'est promis (§60). Aucune garantie,
 * aucune statistique.
 *
 * Une seule source pour la fiche, le panier et la commande : trois listes
 * écrites à la main finiraient par promettre trois choses différentes.
 *
 * @return array<int, array<string, string>> Entrées { fr, ar }, éventuellement vide.
 */
function famma_child_reassurance_items(): array {
	$items = array();

	foreach ( array( 'reassure_payment', 'reassure_shipping' ) as $key ) {
		$text = famma_child_product_copy( $key );

		if ( '' !== $text ) {
			$items[] = array(
				'fr' => $text,
				'ar' => famma_child_product_copy_derja( $key ),
			);
		}
	}

	$delay = famma_child_delivery_delay();

	if ( '' !== $delay ) {
		$items[] = array(
			'fr' => $delay,
			'ar' => '',
		);
	}

	return $items;
}

/**
 * Bloc de réassurance entre le prix et le formulaire de commande (§30, §48).
 *
 * @return void
 */
function famma_child_product_reassurance(): void {
	$items = famma_child_reassurance_items();

	// Tout vidé dans l'administration : pas de cadre vide sur la fiche.
	if ( empty( $items ) ) {
		return;
	}

	famma_child_reassurance_list( $items );

	/*
	 * L'invitation WhatsApp a quitté cette fonction : depuis le lot D4, la
	 * réassurance est remontée AU-DESSUS du bouton d'achat (priorité 25) et la
	 * carte WhatsApp reste en dessous (priorité 36, dans inc/product.php). Deux
	 * emplacements différents ne peuvent plus partager une seule fonction.
	 */
}
add_action( 'woocommerce_single_product_summary', 'famma_child_product_reassurance', 35 );

/*
 * La réassurance et l'invitation WhatsApp du panier et de la commande vivent
 * dans inc/tunnel.php, avec le reste du parcours d'achat.
 */

/**
 * Ajoute le bénéfice principal sur la carte produit (§13 du brief UX).
 *
 * Une carte qui ne porte qu'un nom et un prix oblige à ouvrir la fiche pour
 * savoir à quoi sert l'objet — un aller-retour de trop pour un visiteur venu
 * d'une publicité. Le bénéfice vient du résumé court du produit, saisi par le
 * propriétaire : rien n'est généré, et un produit sans résumé n'affiche
 * simplement rien (§60).
 *
 * @return void
 */
function famma_child_loop_benefit(): void {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$short = trim( wp_strip_all_tags( $product->get_short_description() ) );

	if ( '' === $short ) {
		return;
	}

	// Résumé en derja : sens de lecture explicite, sinon l'ordre des mots se casse sur le site français.
	$derja = '' !== famma_child_text_dir_attrs( $short );

	printf(
		'<p class="famma-card__benefit"%1$s>%2$s</p>',
		$derja ? ' lang="ar" dir="rtl"' : '',
		esc_html( wp_trim_words( $short, 12, '…' ) )
	);
}
add_action( 'woocommerce_after_shop_loop_item_title', 'famma_child_loop_benefit', 6 );

/**
 * Remplace le badge « Promo ! » par la remise réelle (§25 du brief UX).
 *
 * Le pourcentage est calculé à partir du prix barré et du prix promo du
 * produit — jamais saisi à la main. Un produit sans promo réelle ne porte
 * aucun badge : le §60 interdit la remise décorative.
 *
 * Les produits variables renvoient un prix régulier vide : on laisse alors
 * le badge d'origine plutôt que d'afficher une remise fausse.
 *
 * @param string      $html    Badge produit par WooCommerce.
 * @param \WP_Post    $post    Publication du produit.
 * @param \WC_Product $product Produit concerné.
 * @return string
 */
function famma_child_sale_badge( $html, $post, $product ) {
	if ( ! $product instanceof WC_Product ) {
		return $html;
	}

	$regular = (float) $product->get_regular_price();
	$sale    = (float) $product->get_sale_price();

	if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
		return $html;
	}

	/*
	 * « -18% » sans espace : c'est la forme de la maquette, et celle que
	 * lisent les visiteurs habitués aux étiquettes de promotion. L'espace
	 * insécable de la typographie française allongerait le badge d'un tiers
	 * dans un coin d'image déjà étroit.
	 */
	return sprintf(
		'<span class="onsale famma-badge">%s</span>',
		esc_html( sprintf( '-%d%%', (int) round( ( ( $regular - $sale ) / $regular ) * 100 ) ) )
	);
}
add_filter( 'woocommerce_sale_flash', 'famma_child_sale_badge', 10, 3 );

/**
 * Affiche l'économie réalisée sous le prix (§25 du brief UX).
 *
 * Le montant vient de la différence entre prix barré et prix promo. Aucun
 * compte à rebours n'accompagne cette ligne : le §60 et le brief interdisent
 * l'urgence fabriquée, et une offre n'est datée que si elle l'est vraiment.
 *
 * @return void
 */
function famma_child_price_savings(): void {
	$product = wc_get_product();

	if ( ! $product instanceof WC_Product || ! $product->is_on_sale() ) {
		return;
	}

	$regular = (float) $product->get_regular_price();
	$sale    = (float) $product->get_sale_price();

	if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
		return;
	}

	printf(
		'<p class="famma-saving">%1$s <span class="famma-saving__ar" lang="ar" dir="rtl">%2$s</span></p>',
		wp_kses_post(
			sprintf(
				/* translators: %s : montant économisé, déjà formaté par WooCommerce. */
				esc_html__( 'Vous économisez %s', 'famma-child' ),
				wc_price( $regular - $sale )
			)
		),
		esc_html( 'توفّر' )
	);
}
add_action( 'woocommerce_single_product_summary', 'famma_child_price_savings', 11 );

/**
 * Remplace le message de confirmation de commande (§14, D-8 du plan UX).
 *
 * En paiement à la livraison, le moment de vérité n'est pas le paiement —
 * il n'y en a pas — mais l'écran qui suit la commande. « Merci, votre
 * commande a été reçue » ne dit ni ce qui se passe ensuite, ni ce que le
 * client doit préparer. Un écran de confirmation faible se paie en refus
 * à la livraison, le KPI le plus coûteux du modèle.
 *
 * @param string    $text  Message d'origine.
 * @param \WC_Order $order Commande concernée.
 * @return string
 */
function famma_child_order_received_text( $text, $order ) {
	if ( ! $order instanceof WC_Order ) {
		return $text;
	}

	return sprintf(
		'<strong>%1$s</strong> <span lang="ar" dir="rtl">%2$s</span>',
		esc_html__( 'Commande enregistrée. Nous vous contactons pour la confirmer.', 'famma-child' ),
		esc_html( 'طلبك وصلنا. باش نتصلو بيك باش نأكدوه.' )
	);
}
add_filter( 'woocommerce_thankyou_order_received_text', 'famma_child_order_received_text', 10, 2 );

/**
 * Corrige le titre de la page de confirmation (§14, §21).
 *
 * La confirmation est un point de terminaison de la page Checkout : son
 * `<h1>` reprenait donc le titre de cette page, « Checkout » — en anglais,
 * et faux sur le plan du sens puisque la commande est déjà passée. Le
 * visiteur lisait « Checkout » au-dessus de « Commande enregistrée ».
 *
 * Le titre d'origine n'est pas repris en paramètre : il est remplacé, pas
 * complété. PHP admet qu'un callback déclare moins d'arguments qu'il n'en
 * reçoit, et un paramètre inutilisé serait signalé par phpcs.
 *
 * @return string
 */
function famma_child_order_received_title(): string {
	return esc_html__( 'Commande reçue', 'famma-child' );
}
add_filter( 'woocommerce_endpoint_order-received_title', 'famma_child_order_received_title' );

/**
 * Applique ce titre au bandeau du thème parent (§14, §21).
 *
 * Le filtre officiel de WooCommerce ne suffit pas : `wc_page_endpoint_title()`
 * exige `in_the_loop()`, or Kadence rend son bandeau de titre **hors boucle**,
 * avant le contenu. Le `<h1>` de la page continuait donc d'afficher
 * « Checkout » pendant que le reste de la page annonçait la commande reçue.
 *
 * Portée resserrée : uniquement le point de terminaison order-received, et
 * uniquement le titre de la page interrogée — un autre titre rendu sur la
 * même page n'est pas touché.
 *
 * @param string $title   Titre d'origine.
 * @param int    $post_id Publication dont le titre est rendu.
 * @return string
 */
function famma_child_order_received_hero_title( $title, $post_id = 0 ) {
	if ( is_admin() || ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) {
		return $title;
	}

	if ( $post_id && get_queried_object_id() !== (int) $post_id ) {
		return $title;
	}

	return famma_child_order_received_title();
}
add_filter( 'the_title', 'famma_child_order_received_hero_title', 10, 2 );

/**
 * Détaille la suite après une commande payée à la livraison (§14, D-8).
 *
 * Accroché à `woocommerce_thankyou_cod` : ce bloc ne concerne que le
 * paiement à la livraison, et disparaît de lui-même si un autre moyen de
 * paiement est ajouté un jour.
 *
 * Les trois étapes reprennent le workflow réel de `famma-core`
 * (pending-confirm → confirmed → shipped → delivered), pas une promesse
 * commerciale. Aucun délai n'est annoncé : le §60 l'interdit tant que le
 * propriétaire n'a pas arrêté ses engagements de livraison.
 *
 * Le téléphone est rappelé tel qu'il a été saisi — c'est le seul champ
 * dont une faute de frappe fait échouer toute la commande, et le client
 * n'a aucun autre endroit pour s'en apercevoir.
 *
 * @param int $order_id Identifiant de la commande.
 * @return void
 */
function famma_child_cod_next_steps( $order_id ): void {
	$order = wc_get_order( $order_id );

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$steps = array(
		array(
			'fr' => __( 'Nous vous contactons pour confirmer la commande.', 'famma-child' ),
			'ar' => 'نتصلو بيك باش نأكدو الطلب',
		),
		array(
			'fr' => __( 'Le colis part une fois la commande confirmée.', 'famma-child' ),
			'ar' => 'الكولي يخرج كي يتأكد الطلب',
		),
		array(
			'fr' => __( 'Vous payez en espèces au livreur, à la réception.', 'famma-child' ),
			'ar' => 'تخلّص كاش كي توصلك',
		),
	);

	echo '<section class="famma-confirm">';
	printf( '<h2 class="famma-confirm__title">%s</h2>', esc_html__( 'Et maintenant ?', 'famma-child' ) );

	echo '<ol class="famma-confirm__steps">';
	foreach ( $steps as $step ) {
		printf(
			'<li class="famma-confirm__step"><span>%1$s</span><span class="famma-confirm__ar" lang="ar" dir="rtl">%2$s</span></li>',
			esc_html( $step['fr'] ),
			esc_html( $step['ar'] )
		);
	}
	echo '</ol>';

	echo '<dl class="famma-confirm__facts">';

	$phone = $order->get_billing_phone();
	if ( '' !== $phone ) {
		printf(
			'<dt>%1$s</dt><dd>%2$s</dd>',
			esc_html__( 'Numéro à appeler', 'famma-child' ),
			esc_html( $phone )
		);
	}

	printf(
		'<dt>%1$s</dt><dd>%2$s</dd>',
		esc_html__( 'À préparer en espèces', 'famma-child' ),
		wp_kses_post( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) )
	);

	echo '</dl>';

	printf(
		'<p class="famma-confirm__fix">%s</p>',
		esc_html__( 'Une erreur dans le numéro ou l’adresse ? Écrivez-nous, on corrige avant l’envoi.', 'famma-child' )
	);

	echo do_shortcode( '[famma_whatsapp text="' . esc_attr__( 'Corriger ma commande', 'famma-child' ) . '"]' );
	echo '</section>';
}
add_action( 'woocommerce_thankyou_cod', 'famma_child_cod_next_steps' );

/**
 * Barre d'achat collante sur mobile (§13, §30).
 *
 * Le bouton ne réimplémente rien. Ce qu'il déclenche dépend du parcours en
 * place, et c'est `assets/js/sticky-cta.js` qui tranche : s'il y a un
 * formulaire de commande express sur la fiche, la barre y amène le client ;
 * sinon elle déclenche le formulaire d'achat de WooCommerce, donc quantité,
 * variations et validations restent celles du cœur.
 *
 * @return void
 */
function famma_child_sticky_cta(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$product = wc_get_product();
	if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return;
	}

	/*
	 * WhatsApp entre le prix et le bouton : sur la fiche mobile, la pastille
	 * flottante recouvrait en permanence la fin des lignes. Elle est rangée
	 * tant que la barre est affichée (style.css), et le lien passe ici.
	 */
	$whatsapp = '';

	if ( class_exists( '\Famma\Core\WhatsApp' ) ) {
		// Message pré-rempli saisi dans FAMMA → Pages → Fiche produit ; vide = conversation vierge.
		$message = class_exists( '\Famma\Core\Product_Content' )
			? \Famma\Core\Product_Content::whatsapp_message( $product->get_name() )
			: '';

		$whatsapp = \Famma\Core\WhatsApp::link( $message );
	}

	echo '<div class="famma-sticky-cta">';
	printf( '<span class="famma-sticky-cta__price">%s</span>', wp_kses_post( $product->get_price_html() ) );

	if ( '' !== $whatsapp ) {
		printf(
			'<a class="famma-sticky-cta__wa" href="%1$s" target="_blank" rel="noopener nofollow" aria-label="%2$s">%3$s</a>',
			esc_url( $whatsapp ),
			esc_attr__( 'Poser une question sur WhatsApp', 'famma-child' ),
			'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 0 0-8.6 15l-1.4 5 5.1-1.3A10 10 0 1 0 12 2Zm5.9 14.1c-.2.7-1.4 1.4-2 1.4-.5.1-1.1.1-1.8-.1-.4-.1-1-.3-1.6-.6-2.9-1.2-4.7-4.1-4.9-4.3-.1-.2-1.1-1.4-1.1-2.7 0-1.3.7-1.9.9-2.2.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2.1.4 0 .5l-.4.6c-.1.2-.3.3-.1.6.1.3.6 1 1.3 1.6.9.8 1.6 1 1.9 1.2.2.1.4 0 .5-.1l.6-.7c.2-.2.4-.2.6-.1l1.8.9c.2.1.4.2.4.3.1.1.1.6-.1 1.2Z"/></svg>'
		);
	}

	// Libellé du bouton : FAMMA → Pages → Fiche produit, jamais vide (repli sur le défaut).
	$label = famma_child_product_copy( 'cta_order' );
	$label = '' !== $label ? $label : __( 'Commander', 'famma-child' );

	printf(
		'<button type="button" class="famma-sticky-cta__button">%1$s<span class="famma-sticky-cta__ar" lang="ar" dir="rtl">%2$s</span></button>',
		esc_html( $label ),
		esc_html( famma_child_product_copy_derja( 'cta_order' ) )
	);
	echo '</div>';
}
add_action( 'wp_footer', 'famma_child_sticky_cta', 4 );

/**
 * Charge le script de la barre collante, sur les fiches produit seulement.
 *
 * @return void
 */
function famma_child_product_scripts(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$path = get_stylesheet_directory() . '/assets/js/sticky-cta.js';
	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'famma-sticky-cta',
		get_stylesheet_directory_uri() . '/assets/js/sticky-cta.js',
		array(),
		(string) filemtime( $path ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_product_scripts', 21 );

/**
 * Empêche le 404 sur les plans de site XML.
 *
 * `WP::handle_404()` ne prévoit aucune exception pour ces routes : il pose un
 * 404 dès que la requête principale ne ramène aucun article. Or l'URL
 * `/wp-sitemap.xml` déclenche une requête d'articles, et ce site n'en publie
 * aucun — c'est une boutique, pas un blog. Le statut part donc en 404, puis le
 * générateur de WordPress écrit son XML par-dessus sans corriger le code.
 *
 * Resultat mesuré avant correctif : les trois plans de site renvoyaient un
 * XML valide avec un code 404. Un moteur de recherche jette la reponse sans
 * la lire, et le site perd le chemin le plus direct vers ses pages.
 *
 * `pre_handle_404` est le point de sortie prevu par WordPress pour ce cas. Le
 * filtre ne touche a rien d'autre : hors route de plan de site, il rend la
 * valeur qu'il a recue.
 *
 * @param bool      $bypass Vrai si un autre code a deja pris en charge le 404.
 * @param \WP_Query $query  Requête principale.
 * @return bool
 */
function famma_child_sitemap_not_found( $bypass, $query ) {
	if ( $bypass ) {
		return $bypass;
	}

	return ( $query->get( 'sitemap' ) || $query->get( 'sitemap-stylesheet' ) ) ? true : $bypass;
}
add_filter( 'pre_handle_404', 'famma_child_sitemap_not_found', 10, 2 );

/**
 * Émet les données structurées Organization et WebSite (§21).
 *
 * Volontairement limité à ces deux types. WooCommerce produit déjà lui-même
 * `Product`, `Offer`, `Review` et `BreadcrumbList` dès qu'un produit existe :
 * les redéclarer créerait des entités concurrentes, ce qui vaut moins que
 * pas de balisage du tout.
 *
 * Aucune adresse, aucun téléphone, aucun profil social n'est déclaré : le
 * propriétaire ne les a pas fournis et une donnée structurée inventée est
 * exactement ce que le §60 interdit.
 *
 * @return void
 */
function famma_child_structured_data(): void {
	if ( class_exists( '\Famma\Core\Seo' ) ) {
		// Le plugin famma-core est le propriétaire unique du `<head>` :
		// il émet description, Open Graph et données structurées pour
		// toutes les pages. Deux jeux concurrents valent moins qu'un.
		return;
	}

	if ( ! is_front_page() ) {
		return;
	}

	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
		return;
	}

	$home = home_url( '/' );
	$name = get_bloginfo( 'name' );

	$graph = array(
		'@context' => 'https://schema.org',
		'@graph'   => array(
			array(
				'@type'      => 'Organization',
				'@id'        => $home . '#organization',
				'name'       => $name,
				'url'        => $home,
				'logo'       => array(
					'@type'  => 'ImageObject',
					'url'    => get_stylesheet_directory_uri() . '/assets/img/famma-og.png',
					'width'  => 1200,
					'height' => 630,
				),
				'areaServed' => array(
					'@type' => 'Country',
					'name'  => 'Tunisie',
				),
			),
			array(
				'@type'           => 'WebSite',
				'@id'             => $home . '#website',
				'url'             => $home,
				'name'            => $name,
				'inLanguage'      => get_bloginfo( 'language' ),
				'publisher'       => array( '@id' => $home . '#organization' ),
				// Recherche produit : utile dès que le catalogue sera rempli.
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => $home . '?s={search_term_string}&post_type=product',
					),
					'query-input' => 'required name=search_term_string',
				),
			),
		),
	);

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
	);
}
add_action( 'wp_head', 'famma_child_structured_data', 4 );

/**
 * Retire le bandeau de titre du thème sur la page d'accueil.
 *
 * Le masquer en CSS laissait un second `<h1>` (« Accueil ») dans le
 * document, en concurrence avec le titre du héros : deux h1 pour une même
 * page, ce que la hiérarchie de titres du §21 proscrit. On détache donc le
 * rendu au lieu de le cacher.
 *
 * Accroché sur `wp` : la requête principale est résolue, donc
 * `is_front_page()` répond juste, et le gabarit n'a pas encore été rendu.
 *
 * @return void
 */
function famma_child_remove_front_page_hero(): void {
	if ( is_front_page() ) {
		remove_action( 'kadence_entry_hero', 'Kadence\kadence_entry_header', 10 );
	}
}
add_action( 'wp', 'famma_child_remove_front_page_hero' );

/**
 * Réapplique le theme.json de l'enfant, que Kadence écrase par défaut (§25, §49).
 *
 * Kadence branche `edit_theme_json` en priorité 10 et remplace l'intégralité des
 * données par un tableau PHP figé, tant que le réglage `theme_json_mode` n'est pas
 * activé (`inc/components/editor/component.php:219`). Le fichier du parent comme
 * celui de l'enfant sont alors ignorés : sans ce filtre, la palette FAMMA
 * n'apparaît jamais dans l'éditeur de blocs, et le fichier `theme.json` est un
 * document mort.
 *
 * On repasse donc derrière, en priorité 20, en relisant le fichier plutôt qu'en
 * recopiant ses valeurs ici : `theme.json` reste la source unique, et activer un
 * jour `theme_json_mode` ne créerait pas deux définitions divergentes.
 *
 * @param mixed $theme_json Instance WP_Theme_JSON_Data accumulée par les filtres.
 * @return mixed
 */
function famma_child_theme_json( $theme_json ) {
	if ( ! is_object( $theme_json ) || ! method_exists( $theme_json, 'update_with' ) ) {
		return $theme_json;
	}

	$path = get_stylesheet_directory() . '/theme.json';
	if ( ! is_readable( $path ) ) {
		return $theme_json;
	}

	$data = wp_json_file_decode( $path, array( 'associative' => true ) );

	return is_array( $data ) ? $theme_json->update_with( $data ) : $theme_json;
}
add_filter( 'wp_theme_json_data_theme', 'famma_child_theme_json', 20 );

/**
 * Déclare les capacités du thème.
 *
 * Chargé sur `after_setup_theme` : c'est le point d'accroche prévu par
 * WordPress pour les déclarations de support.
 */
function famma_child_setup(): void {
	// Galerie produit WooCommerce — lightbox et slider natifs (§30). Pas de
	// zoom au survol : inutile au doigt, il chargeait l'original et
	// `jquery.zoom` sur chaque fiche (audit perf du 23/09, PERF-04).
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	/*
	 * Colonne « Informations » du pied de page.
	 *
	 * Un emplacement à part, et non le menu `footer` existant : celui-ci sert
	 * la barre légale de bas de page, qui n'a ni le même contenu ni la même
	 * place. Les confondre obligerait à afficher les CGV deux fois.
	 */
	register_nav_menus(
		array(
			'famma_footer_info' => __( 'Pied de page — Informations', 'famma-child' ),
		)
	);
}
add_action( 'after_setup_theme', 'famma_child_setup' );

/**
 * Retire le zoom au survol de la galerie produit.
 *
 * Kadence le déclare sur `after_setup_theme` à la priorité 10, APRÈS le thème
 * enfant (le parent est chargé en second) : il faut donc passer derrière lui.
 * Au doigt, le zoom ne sert à rien, et il chargeait l'original de la photo et
 * `jquery.zoom` sur chaque fiche (audit perf du 23/09, PERF-04).
 */
function famma_child_drop_gallery_zoom(): void {
	remove_theme_support( 'wc-product-gallery-zoom' );
}
add_action( 'after_setup_theme', 'famma_child_drop_gallery_zoom', 11 );
