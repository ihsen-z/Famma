<?php
/**
 * FAMMA — chargement des ressources de l'en-tête et du pied de page.
 *
 * Trois responsabilités, séparées volontairement :
 *  1. servir les polices auto-hébergées, et seulement si elles existent ;
 *  2. charger la feuille de gabarit et le script de navigation ;
 *  3. retirer le CSS d'en-tête et de pied de page de Kadence, devenu inutile
 *     depuis que le thème enfant fournit ses propres gabarits.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Liste des fichiers de police attendus.
 *
 * @return array<int, string>
 */
function famma_child_font_files(): array {
	return array(
		'poppins-400.woff2',
		'poppins-500.woff2',
		'poppins-600.woff2',
		'poppins-700.woff2',
		// Police variable : un fichier couvre 400 à 700 (voir fonts.css).
		'noto-kufi-arabic-var.woff2',
	);
}

/**
 * Les polices auto-hébergées sont-elles réellement présentes ?
 *
 * Sans cette vérification, un thème fraîchement cloné émettrait cinq 404 par
 * page et un `preload` dans le vide. Le repli système déclaré dans
 * `--famma-font` suffit alors parfaitement.
 *
 * Le résultat est mis en cache pour la durée de la requête : `file_exists()`
 * cinq fois par chargement de page ne coûte rien, mais autant ne le payer
 * qu'une fois.
 *
 * @return bool
 */
function famma_child_fonts_available(): bool {
	static $available = null;

	if ( null !== $available ) {
		return $available;
	}

	$dir = get_stylesheet_directory() . '/assets/fonts/';

	foreach ( famma_child_font_files() as $file ) {
		if ( ! file_exists( $dir . $file ) ) {
			$available = false;
			return $available;
		}
	}

	$available = true;
	return $available;
}

/**
 * Charge la feuille des polices auto-hébergées.
 *
 * Accrochée à priorité 19, donc AVANT `famma_child_enqueue_styles()` (20) :
 * les `@font-face` doivent être connus du navigateur avant les règles qui
 * s'en servent, sans quoi il redessine le texte une fois de plus.
 *
 * @return void
 */
function famma_child_enqueue_fonts(): void {
	if ( ! famma_child_fonts_available() ) {
		return;
	}

	$path = get_stylesheet_directory() . '/assets/css/fonts.css';

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_style(
		'famma-fonts',
		get_stylesheet_directory_uri() . '/assets/css/fonts.css',
		array(),
		(string) filemtime( $path )
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_fonts', 19 );

/**
 * Précharge les deux graisses du premier écran.
 *
 * Seulement deux : Poppins 400 pour le corps et 600 pour les titres et le
 * mot-symbole. Précharger les six ferait descendre en priorité haute des
 * fichiers dont la page n'a pas besoin, et retarderait l'affichage au lieu
 * de l'avancer. La graisse arabe n'est préchargée que sur une page arabe.
 *
 * @return void
 */
function famma_child_preload_fonts(): void {
	if ( ! famma_child_fonts_available() ) {
		return;
	}

	$base  = get_stylesheet_directory_uri() . '/assets/fonts/';
	$files = array( 'poppins-400.woff2', 'poppins-600.woff2' );

	if ( is_rtl() ) {
		$files[] = 'noto-kufi-arabic-var.woff2';
	}

	foreach ( $files as $file ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( $base . $file )
		);
	}
}
add_action( 'wp_head', 'famma_child_preload_fonts', 1 );

/**
 * Charge la feuille de gabarit (en-tête, pied de page, conteneur).
 *
 * Dépend de `famma-child` afin de passer après elle : les surcharges de
 * gabarit doivent pouvoir corriger une règle de présentation, pas l'inverse.
 *
 * @return void
 */
function famma_child_enqueue_layout(): void {
	$path = get_stylesheet_directory() . '/assets/css/layout.css';

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_style(
		'famma-layout',
		get_stylesheet_directory_uri() . '/assets/css/layout.css',
		array( 'famma-child' ),
		(string) filemtime( $path )
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_layout', 21 );

/**
 * Charge le script de navigation.
 *
 * Chargé partout, car l'en-tête l'est aussi. En pied de page (`true`) et sans
 * dépendance : il n'a besoin ni de jQuery ni d'aucune bibliothèque.
 *
 * @return void
 */
function famma_child_enqueue_navigation(): void {
	$path = get_stylesheet_directory() . '/assets/js/navigation.js';

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'famma-navigation',
		get_stylesheet_directory_uri() . '/assets/js/navigation.js',
		array(),
		(string) filemtime( $path ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_navigation', 21 );

/**
 * Charge la feuille et le script de la boutique.
 *
 * Sur les archives produit seulement : une page légale n'a que faire des
 * règles de grille, et un script de bascule d'affichage sans grille à basculer
 * est du poids pur sur le premier rendu mobile.
 *
 * @return void
 */
function famma_child_enqueue_shop(): void {
	if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	if ( file_exists( $dir . '/assets/css/shop.css' ) ) {
		wp_enqueue_style(
			'famma-shop',
			$uri . '/assets/css/shop.css',
			array( 'famma-layout' ),
			(string) filemtime( $dir . '/assets/css/shop.css' )
		);
	}

	if ( file_exists( $dir . '/assets/js/shop-view.js' ) ) {
		wp_enqueue_script(
			'famma-shop-view',
			$uri . '/assets/js/shop-view.js',
			array(),
			(string) filemtime( $dir . '/assets/js/shop-view.js' ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_shop', 22 );

/**
 * Charge la feuille de la fiche produit.
 *
 * Le script de la barre d'achat collante est déjà chargé par
 * `famma_child_product_scripts()` (priorité 21) : on ne le double pas.
 *
 * @return void
 */
function famma_child_enqueue_product(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$path = get_stylesheet_directory() . '/assets/css/product.css';

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_style(
		'famma-product',
		get_stylesheet_directory_uri() . '/assets/css/product.css',
		array( 'famma-layout' ),
		(string) filemtime( $path )
	);

	/*
	 * Les onglets sont pilotés par `single-product.js` de WooCommerce. Ce
	 * script n'ajoute que l'ouverture par ancre des onglets autres que les
	 * avis, et le recentrage de la rangée sur mobile. Il dépend du script de
	 * WooCommerce pour s'exécuter après lui : c'est WooCommerce qui choisit
	 * l'onglet ouvert au chargement.
	 */
	$tabs = get_stylesheet_directory() . '/assets/js/product-tabs.js';

	if ( file_exists( $tabs ) && wp_script_is( 'wc-single-product', 'registered' ) ) {
		wp_enqueue_script(
			'famma-product-tabs',
			get_stylesheet_directory_uri() . '/assets/js/product-tabs.js',
			array( 'jquery', 'wc-single-product' ),
			(string) filemtime( $tabs ),
			true
		);
	}

	/*
	 * Sélecteur de quantité − / + : progressif lui aussi. Le champ de
	 * WooCommerce reste utilisable au clavier si le script ne se charge pas.
	 */
	$quantity = get_stylesheet_directory() . '/assets/js/product-quantity.js';

	if ( file_exists( $quantity ) ) {
		wp_enqueue_script(
			'famma-product-quantity',
			get_stylesheet_directory_uri() . '/assets/js/product-quantity.js',
			array(),
			(string) filemtime( $quantity ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_product', 22 );

/**
 * Charge la feuille et le script de l'accueil.
 *
 * Le script du carrousel n'est chargé que sur l'accueil, et il ne s'active
 * qu'en présence de plusieurs visuels — la garde est côté serveur comme côté
 * navigateur.
 *
 * @return void
 */
function famma_child_enqueue_home(): void {
	if ( ! is_front_page() ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	if ( file_exists( $dir . '/assets/css/home.css' ) ) {
		wp_enqueue_style(
			'famma-home',
			$uri . '/assets/css/home.css',
			array( 'famma-layout' ),
			(string) filemtime( $dir . '/assets/css/home.css' )
		);
	}

	if ( file_exists( $dir . '/assets/js/hero.js' ) ) {
		wp_enqueue_script(
			'famma-hero',
			$uri . '/assets/js/hero.js',
			array(),
			(string) filemtime( $dir . '/assets/js/hero.js' ),
			true
		);
	}

	if ( file_exists( $dir . '/assets/js/carousel.js' ) ) {
		wp_enqueue_script(
			'famma-carousel',
			$uri . '/assets/js/carousel.js',
			array(),
			(string) filemtime( $dir . '/assets/js/carousel.js' ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_home', 22 );

/**
 * Charge la feuille partagée de la carte produit.
 *
 * Partout où une carte peut apparaître : accueil, boutique, archives de
 * catégorie, produits liés d'une fiche. Ces règles vivaient dans `shop.css`,
 * chargée sur la seule boutique — d'où des cartes non stylées sur l'accueil,
 * avec une icône de bouton à sa taille naturelle et des badges pleine largeur.
 *
 * Chargée après `famma-child` : `style.css` pose la carte de base, cette
 * feuille applique le dessin de la maquette par-dessus.
 *
 * @return void
 */
function famma_child_enqueue_product_card(): void {
	if ( ! function_exists( 'is_shop' ) ) {
		return;
	}

	if ( ! ( is_front_page() || is_shop() || is_product_taxonomy() || is_product() || is_cart() ) ) {
		return;
	}

	$path = get_stylesheet_directory() . '/assets/css/product-card.css';

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_style(
		'famma-product-card',
		get_stylesheet_directory_uri() . '/assets/css/product-card.css',
		array( 'famma-layout' ),
		(string) filemtime( $path )
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_product_card', 22 );

/**
 * Charge le script des favoris.
 *
 * Partout où une carte produit peut apparaître : accueil, boutique, fiche
 * produit (produits liés). Inutile ailleurs — le script sort de lui-même s'il
 * ne trouve aucun bouton.
 *
 * @return void
 */
function famma_child_enqueue_favourites(): void {
	if ( ! function_exists( 'is_shop' ) ) {
		return;
	}

	if ( ! ( is_front_page() || is_shop() || is_product_taxonomy() || is_product() || is_cart() ) ) {
		return;
	}

	$path = get_stylesheet_directory() . '/assets/js/favourites.js';

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'famma-favourites',
		get_stylesheet_directory_uri() . '/assets/js/favourites.js',
		array(),
		(string) filemtime( $path ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_favourites', 22 );

/**
 * Retire le CSS d'en-tête et de pied de page du thème parent.
 *
 * Depuis que `header.php` et `footer.php` vivent dans le thème enfant, ce
 * balisage n'existe plus : les feuilles correspondantes ne stylent plus rien
 * et ne font que peser sur le premier rendu mobile.
 *
 * Chaque poignée est testée avant d'être retirée. Les noms varient selon la
 * version de Kadence, et une poignée absente est simplement ignorée — jamais
 * une erreur. Le thème parent lui-même n'est évidemment pas modifié (§6).
 *
 * @return void
 */
function famma_child_dequeue_parent_chrome(): void {
	/**
	 * Filtre les feuilles du parent devenues inutiles.
	 *
	 * @param array<int, string> $handles Poignées de style à retirer.
	 */
	$handles = (array) apply_filters(
		'famma_child_parent_chrome_handles',
		array(
			'kadence-header',
			'kadence-footer',
			'kadence-navigation',
			'kadence-mobile-navigation',
			'kadence-search-modal',
			'kadence-header-search-modal',
		)
	);

	foreach ( $handles as $handle ) {
		$handle = (string) $handle;

		if ( wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'registered' ) ) {
			wp_dequeue_style( $handle );
		}

		if ( wp_script_is( $handle, 'enqueued' ) || wp_script_is( $handle, 'registered' ) ) {
			wp_dequeue_script( $handle );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'famma_child_dequeue_parent_chrome', 99 );

/**
 * Rafraîchit le badge du panier après un ajout en AJAX.
 *
 * WooCommerce remplace le contenu de chaque sélecteur retourné ici. Sans ce
 * fragment, le badge afficherait un compte périmé jusqu'au prochain
 * rechargement complet — exactement le moment où le client vérifie que son
 * article est bien pris en compte.
 *
 * @param array<string, string> $fragments Fragments à remplacer.
 * @return array<string, string>
 */
function famma_child_cart_fragment( array $fragments ): array {
	ob_start();
	famma_child_cart_badge();
	$fragments['.famma-cart__badge'] = '<span class="famma-cart__badge">' . ob_get_clean() . '</span>';

	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'famma_child_cart_fragment' );

/**
 * Charge la feuille des pages institutionnelles.
 *
 * Seules les pages institutionnelles en ont besoin : la charger partout
 * ajouterait une requête à chaque page pour du style que personne ne lit.
 *
 * Les identifiants sont filtrables parce qu'ils dépendent des permaliens du
 * site, pas du thème : renommer une page ne doit pas exiger de toucher au code.
 *
 * @return void
 */
function famma_child_enqueue_pages(): void {
	/**
	 * Filtre les pages qui reçoivent la feuille institutionnelle.
	 *
	 * @param array<int, string> $slugs Identifiants de page.
	 */
	$slugs = (array) apply_filters( 'famma_child_content_page_slugs', array( 'a-propos', 'contact', 'livraison', 'retours', 'conditions-generales' ) );

	if ( ! is_page( $slugs ) ) {
		return;
	}

	$path = get_stylesheet_directory() . '/assets/css/pages.css';

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_style(
		'famma-pages',
		get_stylesheet_directory_uri() . '/assets/css/pages.css',
		array( 'famma-layout' ),
		(string) filemtime( $path )
	);

	/*
	 * Livraison, Retours et Conditions générales (maquettes du même nom) :
	 * cartes, tableaux, formulaire de retour, sommaire et articles numérotés.
	 */
	$info = get_stylesheet_directory() . '/assets/css/info-pages.css';

	if ( is_page( array( 'livraison', 'retours', 'conditions-generales' ) ) && file_exists( $info ) ) {
		wp_enqueue_style(
			'famma-info-pages',
			get_stylesheet_directory_uri() . '/assets/css/info-pages.css',
			array( 'famma-pages' ),
			(string) filemtime( $info )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_pages', 22 );
