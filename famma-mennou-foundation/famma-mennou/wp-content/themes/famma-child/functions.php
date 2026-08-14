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
add_action( 'wp_footer', 'famma_child_legal_bar', 5 );

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
 * Déclare les capacités du thème.
 *
 * Chargé sur `after_setup_theme` : c'est le point d'accroche prévu par
 * WordPress pour les déclarations de support.
 */
function famma_child_setup(): void {
	load_child_theme_textdomain( 'famma-child', get_stylesheet_directory() . '/languages' );

	// Galerie produit WooCommerce — zoom/lightbox/slider natifs (§30).
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'famma_child_setup' );
