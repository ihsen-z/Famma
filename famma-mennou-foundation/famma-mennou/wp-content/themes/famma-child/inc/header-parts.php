<?php
/**
 * FAMMA — briques de l'en-tête partagé.
 *
 * Ce fichier ne contient que du rendu. Chaque bloc est une fonction courte,
 * appelée par `header.php`, afin qu'un bloc puisse être testé, réordonné ou
 * réutilisé ailleurs (la barre de promesses ressert en tête de boutique)
 * sans toucher au gabarit.
 *
 * Aucun contenu inventé : une ligne dont la donnée n'est pas configurée
 * n'est pas rendue (§58, §60).
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renvoie le numéro WhatsApp configuré, chiffres seulement.
 *
 * Passe par l'API publique de famma-core plutôt que par la constante : c'est
 * le plugin qui possède la règle de normalisation. Le thème reste utilisable
 * si le plugin est désactivé — il affiche simplement une promesse de moins.
 *
 * @return string Chaîne vide si aucun numéro n'est configuré.
 */
function famma_child_whatsapp_number(): string {
	if ( ! class_exists( '\Famma\Core\Config' ) ) {
		return '';
	}

	return (string) \Famma\Core\Config::instance()->whatsapp_number();
}

/**
 * Balise du conteneur de contenu : `main` ou `div`.
 *
 * Constaté sur les pages rendues, pas supposé : Kadence n'émet aucun élément
 * `main` sur une page ordinaire — son conteneur est un `div id="main"` — mais
 * ses gabarits WooCommerce, eux, en émettent un
 * (`<main id="main" class="site-main" role="main">`).
 *
 * Sans arbitrage, on obtiendrait soit **aucun** repère principal sur le site,
 * soit **deux** sur la boutique et la fiche produit. Les deux sont des défauts
 * d'accessibilité. On rend donc `main` partout, sauf là où le parent en pose
 * déjà un.
 *
 * À revoir aux lots D3 et D4 : dès que `woocommerce/archive-product.php` et
 * `single-product.php` seront surchargés par le thème enfant, le parent
 * n'émettra plus rien et cette exception pourra tomber.
 *
 * @return string `main` ou `div`.
 */
function famma_child_content_tag(): string {
	/*
	 * Seule la fiche produit laisse encore le parent poser son `<main>` : sur
	 * les archives produit, `archive-product.php` détache son conteneur de
	 * contenu — celui-ci rendait un second titre d'archive hors de la barre
	 * d'outils de la maquette. Le repère principal revient donc ici.
	 */
	$parent_renders_main = function_exists( 'is_product' ) && is_product();

	/**
	 * Filtre le constat « le thème parent pose déjà un `main` ».
	 *
	 * @param bool $parent_renders_main Vrai si le parent émet son propre `main`.
	 */
	$parent_renders_main = (bool) apply_filters( 'famma_child_parent_renders_main', $parent_renders_main );

	return $parent_renders_main ? 'div' : 'main';
}

/**
 * Les trois promesses de la barre supérieure.
 *
 * Ce sont des affirmations vérifiables, pas du remplissage : la livraison
 * couvre les 24 gouvernorats, le paiement à la livraison est le seul moyen
 * de paiement, et le support WhatsApp n'est annoncé que si un numéro existe
 * réellement.
 *
 * @return array<int, array{icon: string, label: string}>
 */
function famma_child_topbar_promises(): array {
	$promises = array(
		array(
			'icon'  => 'truck',
			'label' => __( 'Livraison partout en Tunisie', 'famma-child' ),
		),
		array(
			'icon'  => 'card',
			'label' => __( 'Paiement à la livraison', 'famma-child' ),
		),
	);

	if ( '' !== famma_child_whatsapp_number() ) {
		$promises[] = array(
			'icon'  => 'chat',
			'label' => __( 'Support WhatsApp', 'famma-child' ),
		);
	}

	/**
	 * Filtre les promesses de la barre supérieure.
	 *
	 * @param array<int, array{icon: string, label: string}> $promises Promesses.
	 */
	return (array) apply_filters( 'famma_child_topbar_promises', $promises );
}

/**
 * Affiche la barre supérieure marine : promesses + bascule de langue.
 *
 * @return void
 */
function famma_child_topbar(): void {
	$promises = famma_child_topbar_promises();
	?>
	<div class="famma-topbar">
		<div class="famma-container famma-topbar__inner">
			<ul class="famma-topbar__promises">
				<?php foreach ( $promises as $promise ) : ?>
					<li class="famma-topbar__promise">
						<?php famma_child_the_icon( $promise['icon'] ); ?>
						<span><?php echo esc_html( $promise['label'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php famma_child_language_switcher(); ?>
		</div>
	</div>
	<?php
}

/**
 * Affiche la bascule FR / عربي.
 *
 * Rend le sélecteur de TranslatePress, jamais un état côté navigateur : la
 * langue est une URL (`/` et `/ar/`), donc partageable, indexable et
 * mémorisée par le navigateur. Un bouton JavaScript ne serait ni l'un ni
 * l'autre.
 *
 * `configure-translatepress.php` note que le sélecteur flottant par défaut
 * occupe le bas de l'écran sur mobile ; le voici remonté dans l'en-tête,
 * comme prévu.
 *
 * @return void
 */
function famma_child_language_switcher(): void {
	if ( ! function_exists( 'trp_custom_language_switcher' ) ) {
		return;
	}

	$languages = trp_custom_language_switcher();

	if ( ! is_array( $languages ) || count( $languages ) < 2 ) {
		return;
	}

	// Libellés de la maquette. TranslatePress renvoie « Français » et
	// « العربية » ; la barre supérieure est trop dense pour les afficher.
	$labels = array(
		'fr_FR' => 'FR',
		'ar'    => 'عربي',
	);

	/*
	 * La langue active se lit sur `get_locale()`, pas sur une fonction de
	 * TranslatePress : le plugin bascule déjà la locale du front, et les codes
	 * qu'il utilise (`fr_FR`, `ar`) sont précisément des locales WordPress.
	 * Constaté au rendu : sans cette comparaison, aucune des deux langues ne
	 * portait `is-current` et la barre restait grise dans les deux sens.
	 */
	$current = get_locale();
	?>
	<nav class="famma-lang" data-no-translation="" aria-label="<?php esc_attr_e( 'Langue', 'famma-child' ); ?>">
		<?php
		$first = true;
		foreach ( $languages as $code => $language ) :
			if ( ! is_array( $language ) ) {
				continue;
			}

			$code    = isset( $language['language_code'] ) ? (string) $language['language_code'] : (string) $code;
			$url     = isset( $language['current_page_url'] ) ? (string) $language['current_page_url'] : '';
			$label   = $labels[ $code ] ?? (string) ( $language['short_name'] ?? $code );
			$is_ar   = str_starts_with( $code, 'ar' );
			$is_here = $code === $current;

			if ( '' === $url ) {
				continue;
			}

			if ( ! $first ) {
				echo '<span class="famma-lang__sep" aria-hidden="true">|</span>';
			}
			$first = false;
			?>
			<?php
			/*
			 * Ne jamais poser ici d'attribut sans valeur (`data-no-translation`
			 * nu, par exemple) : le parseur simple_html_dom embarqué par
			 * TranslatePress avale alors l'attribut suivant, et le lien perd
			 * sa classe — donc l'exception de `famma_child_keep_language_links()`
			 * ne s'applique plus. Constaté au journal : `class=[]`. L'exclusion
			 * de traduction est portée par le `<nav>`, avec une valeur explicite.
			 */
			?>
			<a
				class="famma-lang__link<?php echo $is_here ? ' is-current' : ''; ?>"
				href="<?php echo esc_url( $url ); ?>"
				hreflang="<?php echo esc_attr( str_replace( '_', '-', $code ) ); ?>"
				<?php echo $is_ar ? ' lang="ar"' : ''; ?>
				<?php echo $is_here ? ' aria-current="true"' : ''; ?>
			><?php echo esc_html( (string) $label ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * Empêche TranslatePress de réécrire les liens du sélecteur de langue.
 *
 * Le réglage `force-language-to-custom-links` réécrit **tous** les href
 * internes de la page vers la langue courante. Appliqué au sélecteur, il le
 * casse : sur `/ar/`, le lien « FR » pointait vers `/ar/` et le visiteur ne
 * pouvait plus revenir au français. Constaté au rendu.
 *
 * `data-no-translation` sur le lien ne suffit pas : la condition du plugin
 * annule cette exception dès qu'un ancêtre porte un marqueur `data-trp-gettext`
 * — ce qui est le cas ici, l'`aria-label` du `<nav>` étant traduit. On passe
 * donc par le point d'extension prévu, `trp_force_custom_links`, plutôt que de
 * lutter contre le DOM.
 *
 * @param string $forced_url   URL réécrite par TranslatePress.
 * @param string $original_url URL d'origine, celle que nous avons produite.
 * @param string $language     Langue courante.
 * @param object $link         Nœud DOM du lien (simple_html_dom).
 * @return string
 */
function famma_child_keep_language_links( $forced_url, $original_url, $language, $link ) {
	$class = '';

	if ( is_object( $link ) ) {
		/*
		 * simple_html_dom expose les attributs par surcharge (`__get`). Le
		 * nom de la méthode d'accès varie selon la copie du parseur embarquée
		 * par le plugin : on tente la propriété d'abord, la méthode ensuite.
		 */
		if ( isset( $link->class ) ) {
			$class = (string) $link->class;
		} elseif ( method_exists( $link, 'getAttribute' ) ) {
			$class = (string) $link->getAttribute( 'class' );
		}
	}

	return str_contains( $class, 'famma-lang__link' ) ? $original_url : $forced_url;
}
add_filter( 'trp_force_custom_links', 'famma_child_keep_language_links', 10, 4 );

/**
 * Affiche le lien de marque de l'en-tête.
 *
 * Réutilise `famma_child_lockup_markup()` : le lockup de la maquette existe
 * déjà dans ce thème et sert aussi de repli au logo du personnalisateur. Un
 * logo téléversé par le propriétaire l'emporte toujours.
 *
 * @return void
 */
function famma_child_brand_link(): void {
	$logo = '';

	if ( function_exists( 'get_custom_logo' ) && has_custom_logo() ) {
		$logo = get_custom_logo();
	}

	if ( '' !== trim( (string) $logo ) ) {
		echo '<div class="famma-header__brand">' . wp_kses_post( $logo ) . '</div>';
		return;
	}

	printf(
		'<a class="famma-header__brand" href="%1$s" rel="home" aria-label="%2$s">%3$s</a>',
		esc_url( home_url( '/' ) ),
		esc_attr( get_bloginfo( 'name', 'display' ) ),
		wp_kses( famma_child_lockup_markup(), famma_child_svg_allowed_html() )
	);
}

/**
 * Affiche la navigation principale.
 *
 * @param string $context `desktop` ou `mobile` — seule la classe change, le
 *                        menu reste le même : une boutique COD mobile-first
 *                        n'a aucune raison d'avoir deux navigations qui
 *                        divergent (voir scripts/create-menus.php).
 * @return void
 */
function famma_child_primary_nav( string $context = 'desktop' ): void {
	if ( ! has_nav_menu( 'primary' ) ) {
		return;
	}

	wp_nav_menu(
		array(
			'theme_location'       => 'primary',
			'container'            => 'nav',
			'container_class'      => 'famma-nav famma-nav--' . sanitize_html_class( $context ),
			'container_aria_label' => __( 'Navigation principale', 'famma-child' ),
			'menu_class'           => 'famma-nav__list',
			'depth'                => 2,
			'fallback_cb'          => false,
		)
	);
}

/**
 * Affiche le champ de recherche produit.
 *
 * Limité au type `product` : sur une boutique, une recherche qui ramène des
 * pages légales avant les articles vendus est une recherche cassée.
 *
 * @param string $context `desktop` ou `mobile`.
 * @return void
 */
function famma_child_search_form( string $context = 'desktop' ): void {
	$id = 'famma-search-' . sanitize_html_class( $context );
	?>
	<form class="famma-search famma-search--<?php echo esc_attr( sanitize_html_class( $context ) ); ?>"
		role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>">
			<?php esc_html_e( 'Rechercher un produit', 'famma-child' ); ?>
		</label>
		<?php famma_child_the_icon( 'search', 'famma-search__icon' ); ?>
		<input
			class="famma-search__input"
			id="<?php echo esc_attr( $id ); ?>"
			type="search"
			name="s"
			value="<?php echo esc_attr( get_search_query() ); ?>"
			placeholder="<?php esc_attr_e( 'Rechercher un produit...', 'famma-child' ); ?>"
		>
		<input type="hidden" name="post_type" value="product">
		<button class="famma-search__submit" type="submit">
			<?php esc_html_e( 'Rechercher', 'famma-child' ); ?>
		</button>
	</form>
	<?php
}

/**
 * Renvoie le nombre d'articles au panier.
 *
 * Renvoie zéro tant que WooCommerce n'a pas initialisé sa session — ce qui
 * arrive sur les requêtes servies depuis le cache ou avant `wp_loaded`. Le
 * badge est alors masqué, puis corrigé par le fragment AJAX.
 *
 * @return int
 */
function famma_child_cart_count(): int {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}

	return (int) WC()->cart->get_cart_contents_count();
}

/**
 * Affiche le badge du panier, seul (sert aussi de fragment AJAX).
 *
 * @return void
 */
function famma_child_cart_badge(): void {
	$count = famma_child_cart_count();

	printf(
		'<span class="%1$s" aria-hidden="true">%2$s</span>',
		esc_attr( 0 === $count ? 'famma-cart__count is-empty' : 'famma-cart__count' ),
		esc_html( (string) $count )
	);
}

/**
 * Affiche les actions de l'en-tête : compte, panier, burger.
 *
 * @return void
 */
function famma_child_header_actions(): void {
	$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
	$cart_url    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';
	$count       = famma_child_cart_count();
	?>
	<div class="famma-header__actions">
		<?php if ( $account_url ) : ?>
			<a class="famma-header__action" href="<?php echo esc_url( $account_url ); ?>">
				<?php famma_child_the_icon( 'user' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Mon compte', 'famma-child' ); ?></span>
			</a>
		<?php endif; ?>

		<?php if ( $cart_url ) : ?>
			<a class="famma-header__action famma-cart" href="<?php echo esc_url( $cart_url ); ?>">
				<?php famma_child_the_icon( 'cart' ); ?>
				<span class="screen-reader-text">
					<?php
					printf(
						/* translators: %d: nombre d'articles dans le panier. */
						esc_html( _n( 'Panier, %d article', 'Panier, %d articles', $count, 'famma-child' ) ),
						(int) $count
					);
					?>
				</span>
				<span class="famma-cart__badge"><?php famma_child_cart_badge(); ?></span>
			</a>
		<?php endif; ?>

		<button
			class="famma-burger"
			type="button"
			aria-expanded="false"
			aria-controls="famma-mobile-panel"
		>
			<?php famma_child_the_icon( 'menu', 'famma-burger__open' ); ?>
			<?php famma_child_the_icon( 'close', 'famma-burger__close' ); ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'famma-child' ); ?></span>
		</button>
	</div>
	<?php
}
