<?php
/**
 * Derja tunisienne — chargement des traductions et surcharges WooCommerce.
 *
 * Trois responsabilités, toutes liées à la même question : que lit un client
 * arabophone ?
 *
 * 1. Charger `famma-child-ar.mo` au bon moment. Sous TranslatePress, la langue
 *    de la page n'est arrêtée qu'après `init`.
 * 2. Laisser nos propres domaines à gettext plutôt qu'à TranslatePress, comme
 *    l'acte DECISIONS.md : le code est traduit par des fichiers versionnés, le
 *    contenu par TranslatePress.
 * 3. Traduire en derja les chaînes de WooCommerce que le client voit. Sans
 *    cela elles ressortent **en anglais** : la locale passe bien à `ar`, mais
 *    aucun pack de langue arabe n'est installé pour WooCommerce, et l'anglais
 *    est son ultime repli.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nos domaines de traduction, servis par gettext et non par TranslatePress.
 *
 * @return array<int, string>
 */
function famma_child_own_textdomains(): array {
	return array( 'famma-child', 'famma-core' );
}

/**
 * Charge les traductions du thème, une fois la langue connue.
 *
 * Sur `init` puis de nouveau sur `wp` : TranslatePress ne fixe la langue de la
 * page qu'une fois la requête résolue. Appelé une seule fois plus tôt, le
 * chargement visait le français — langue source, pour laquelle il n'existe
 * aucun fichier — et le site arabe restait intégralement en français.
 *
 * @return void
 */
function famma_child_load_textdomain(): void {
	static $loaded = '';

	$locale = determine_locale();

	if ( $locale === $loaded ) {
		return;
	}

	$mofile = get_stylesheet_directory() . '/languages/famma-child-' . $locale . '.mo';

	if ( ! is_readable( $mofile ) ) {
		$loaded = $locale;
		return;
	}

	/*
	 * Le second argument d'`unload_textdomain()` n'est pas décoratif : par
	 * défaut la fonction retire aussi le domaine du registre de chargement
	 * différé, et le `load_textdomain()` qui suit échoue alors sans rien dire.
	 *
	 * `load_textdomain()` avec un chemin et une locale explicites, plutôt que
	 * `load_child_theme_textdomain()` : ce dernier redéduit la locale seul et,
	 * sous TranslatePress, ne retrouvait pas le fichier arabe.
	 */
	unload_textdomain( 'famma-child', true );
	load_textdomain( 'famma-child', $mofile, $locale );

	$loaded = $locale;
}
add_action( 'init', 'famma_child_load_textdomain', 1 );
add_action( 'wp', 'famma_child_load_textdomain', 1 );

/**
 * Les chaînes de WooCommerce vues par le client, en derja.
 *
 * Volontairement courte et limitée à ce qui a été **constaté** à l'écran, pas à
 * tout ce que WooCommerce peut dire : une carte exhaustive deviendrait fausse à
 * la première mise à jour, et une chaîne mal devinée est pire qu'une chaîne en
 * anglais — elle a l'air juste.
 *
 * Installer le pack de langue arabe de WooCommerce donnerait de l'arabe
 * littéraire. Ces surcharges donnent de la derja, qui est la langue du site.
 *
 * @return array<string, string>
 */
function famma_child_woo_derja(): array {
	static $map = null;

	/*
	 * Memorise pour la requete. Ces filtres se declenchent a chaque appel de
	 * `__()` sur le domaine woocommerce — plusieurs centaines de fois sur une
	 * page boutique. Reconstruire le tableau a chaque fois etait un cout
	 * silencieux paye sur toutes les pages du site (regle 10).
	 */
	if ( null !== $map ) {
		return $map;
	}

	$map = array(
		// Fil d'Ariane et navigation.
		'Home'                                            => 'الرئيسية',
		'Previous'                                        => 'قبل',
		'Next'                                            => 'بعد',

		// Boutique.
		'Showing all %1$d result'                         => 'يتعرض %1$d منتج',
		'Showing all %1$d results'                        => 'يتعرض %1$d منتجات',
		'Showing %1$d&ndash;%2$d of %3$d results'         => 'يتعرض %1$d–%2$d من %3$d منتجات',
		'Showing the single result'                       => 'يتعرض منتج واحد',
		'No products were found matching your selection.' => 'ما لقيناش منتج يوافق اللي طلبت.',

		// Prix barré : lu par les lecteurs d'écran.
		'Original price was: %s.'                         => 'الثمن القديم كان: %s.',
		'Current price is: %s.'                           => 'الثمن توّا: %s.',

		// Fiche produit.
		// « Ajouter au panier » est l'action secondaire depuis le 23/09 : « أطلب » la confondait avec la commande express.
		'Add to cart'                                     => 'زيد للسلّة',
		'Read more'                                       => 'أقرا أكثر',
		'Description'                                     => 'الوصف',
		'Additional information'                          => 'معلومات أخرى',
		'Related products'                                => 'منتجات تشبهلو',
		'You may also like&hellip;'                       => 'يمكن يعجبوك زادة…',
		'%s quantity'                                     => 'الكمية',
		'Category:'                                       => 'القسم:',
		'Categories:'                                     => 'الأقسام:',
		'Tag:'                                            => 'الوسم:',
		'Tags:'                                           => 'الوسوم:',
		'Review (%d)'                                     => 'تقييم (%d)',
		'Reviews (%d)'                                    => 'التقييمات (%d)',

		// Panier et commande.
		'Cart'                                            => 'القفة',
		'Checkout'                                        => 'أكمّل الطلبية',
		'Product'                                         => 'المنتج',
		'Quantity'                                        => 'الكمية',
		'Subtotal'                                        => 'المجموع',
		'Total'                                           => 'الإجمالي',
		'Your cart is currently empty.'                   => 'القفة متاعك فارغة.',
		'Place order'                                     => 'أبعث الطلبية',
		'Order number:'                                   => 'رقم الطلبية:',
		'Thank you. Your order has been received.'        => 'يعيشك. الطلبية متاعك وصلتنا.',
	);

	return $map;
}

/**
 * Traduit en derja une chaîne de WooCommerce.
 *
 * @param string $translation Traduction déjà calculée.
 * @param string $text        Chaîne source, en anglais.
 * @param string $domain      Domaine de traduction.
 * @return string
 */
function famma_child_translate_woo( $translation, $text, $domain ) {
	if ( 'woocommerce' !== $domain || ! famma_child_is_derja() ) {
		return $translation;
	}

	$map = famma_child_woo_derja();

	return $map[ $text ] ?? $translation;
}
add_filter( 'gettext', 'famma_child_translate_woo', 20, 3 );

/**
 * Même surcharge, pour les chaînes au pluriel.
 *
 * WooCommerce annonce le nombre de résultats par `_n()` : sans ce filtre, la
 * boutique arabe garde « Showing all 2 results » en anglais alors que la forme
 * au singulier, elle, serait traduite. Une page à moitié traduite se remarque
 * plus qu'une page qui ne l'est pas.
 *
 * @param string $translation Traduction déjà calculée.
 * @param string $single      Forme au singulier.
 * @param string $plural      Forme au pluriel.
 * @param int    $number      Quantité.
 * @param string $domain      Domaine de traduction.
 * @return string
 */
function famma_child_translate_woo_plural( $translation, $single, $plural, $number, $domain ) {
	if ( 'woocommerce' !== $domain || ! famma_child_is_derja() ) {
		return $translation;
	}

	$map = famma_child_woo_derja();
	$key = 1 === (int) $number ? $single : $plural;

	return $map[ $key ] ?? $translation;
}
add_filter( 'ngettext', 'famma_child_translate_woo_plural', 20, 5 );

/**
 * Même surcharge, pour les chaînes à contexte.
 *
 * `_x()` et `_nx()` passent par des filtres distincts de `__()` et `_n()`.
 * Sans eux, « Home » du fil d'Ariane — écrit `_x( 'Home', 'breadcrumb' )` —
 * reste en anglais alors qu'il figure dans la carte.
 *
 * @param string $translation Traduction déjà calculée.
 * @param string $text        Chaîne source.
 * @param string $context     Contexte, ignoré ici : nos clés sont uniques.
 * @param string $domain      Domaine de traduction.
 * @return string
 */
function famma_child_translate_woo_context( $translation, $text, $context, $domain ) {
	if ( 'woocommerce' !== $domain || ! famma_child_is_derja() ) {
		return $translation;
	}

	$map = famma_child_woo_derja();

	return $map[ $text ] ?? $translation;
}
add_filter( 'gettext_with_context', 'famma_child_translate_woo_context', 20, 4 );

/**
 * Même surcharge, pour les pluriels à contexte.
 *
 * @param string $translation Traduction déjà calculée.
 * @param string $single      Forme au singulier.
 * @param string $plural      Forme au pluriel.
 * @param int    $number      Quantité.
 * @param string $context     Contexte, ignoré ici.
 * @param string $domain      Domaine de traduction.
 * @return string
 */
function famma_child_translate_woo_plural_context( $translation, $single, $plural, $number, $context, $domain ) {
	if ( 'woocommerce' !== $domain || ! famma_child_is_derja() ) {
		return $translation;
	}

	$map = famma_child_woo_derja();
	$key = 1 === (int) $number ? $single : $plural;

	return $map[ $key ] ?? $translation;
}
add_filter( 'ngettext_with_context', 'famma_child_translate_woo_plural_context', 20, 6 );

/**
 * Les deux phrases de la page 404 de Kadence, réécrites dans nos domaines.
 *
 * Constaté le 24/09 : en arabe, Kadence sortait « عفوا! ’t لا يمكن العثور… »
 * — traduction cassée — suivi d'une phrase restée en anglais ; en français,
 * « aucun contenu n'ai été trouvé » (faute d'accord). Deux chaînes, pas une
 * carte : la page 404 n'en affiche pas d'autres.
 *
 * @param string $text Chaîne source de Kadence.
 * @return string|null Notre texte, ou null si la chaîne n'est pas concernée.
 */
function famma_child_kadence_404_text( string $text ): ?string {
	switch ( $text ) {
		case 'Oops! That page can&rsquo;t be found.':
			return __( 'La page demandée est introuvable.', 'famma-child' );
		case 'It looks like nothing was found at this location. Maybe try a search?':
			return __( 'Aucun contenu n’a été trouvé à cette adresse. Essayez une recherche :', 'famma-child' );
	}

	return null;
}

/**
 * Substitue nos textes à ceux de la page 404 de Kadence.
 *
 * @param string $translation Traduction déjà calculée.
 * @param string $text        Chaîne source, en anglais.
 * @param string $domain      Domaine de traduction.
 * @return string
 */
function famma_child_translate_kadence( $translation, $text, $domain ) {
	if ( 'kadence' !== $domain ) {
		return $translation;
	}

	return famma_child_kadence_404_text( (string) $text ) ?? $translation;
}
add_filter( 'gettext', 'famma_child_translate_kadence', 20, 3 );

/**
 * Libellé du fil d'Ariane de WooCommerce, écrit en dur en anglais.
 *
 * WooCommerce pose `aria-label="Breadcrumb"` sans passer par `__()` : les
 * lecteurs d'écran annonçaient de l'anglais sur la boutique, les rayons et
 * toutes les fiches produit.
 *
 * @param array<string, mixed> $defaults Réglages du fil d'Ariane.
 * @return array<string, mixed>
 */
function famma_child_breadcrumb_label( $defaults ) {
	if ( ! is_array( $defaults ) || ! isset( $defaults['wrap_before'] ) ) {
		return $defaults;
	}

	$defaults['wrap_before'] = str_replace(
		'aria-label="Breadcrumb"',
		'aria-label="' . esc_attr__( 'Fil d’Ariane', 'famma-child' ) . '"',
		(string) $defaults['wrap_before']
	);

	return $defaults;
}
add_filter( 'woocommerce_breadcrumb_defaults', 'famma_child_breadcrumb_label' );

/**
 * La page est-elle servie en derja ?
 *
 * @return bool
 */
function famma_child_is_derja(): bool {
	static $derja = null;

	/*
	 * Meme raison que la carte : `determine_locale()` applique des filtres, et
	 * l'appeler a chaque chaine traduite revient a le faire des centaines de
	 * fois par page. La langue ne change pas en cours de requete.
	 */
	if ( null === $derja ) {
		$derja = 0 === strpos( determine_locale(), 'ar' );
	}

	return $derja;
}

/**
 * Laisse nos domaines — et les chaînes WooCommerce qu'on traduit — à gettext.
 *
 * TranslatePress intercepte `gettext` et sert ses propres tables : une chaîne
 * absente de son dictionnaire ressort dans la langue source, même si un fichier
 * `.mo` la traduit. C'est ce qui rendait `famma-child-ar.mo` inerte —
 * traduction écrite, jamais affichée.
 *
 * Le partage des rôles reste celui de DECISIONS.md : le **code** est traduit par
 * des fichiers versionnés et relisibles en revue, le **contenu** (pages,
 * produits, menus, catégories) reste à TranslatePress, qui est fait pour ça.
 * Seules les chaînes WooCommerce effectivement présentes dans notre carte sont
 * soustraites à TranslatePress ; toutes les autres continuent de lui revenir.
 *
 * @param bool   $skip        Vrai si la chaîne doit rester hors de TranslatePress.
 * @param string $translation Traduction déjà calculée.
 * @param string $text        Chaîne source.
 * @param string $domain      Domaine de traduction.
 * @return bool
 */
function famma_child_skip_translatepress_gettext( $skip, $translation, $text, $domain ) {
	if ( in_array( $domain, famma_child_own_textdomains(), true ) ) {
		return true;
	}

	if ( 'woocommerce' === $domain && isset( famma_child_woo_derja()[ $text ] ) ) {
		return true;
	}

	if ( 'kadence' === $domain && null !== famma_child_kadence_404_text( (string) $text ) ) {
		return true;
	}

	return $skip;
}
add_filter( 'trp_skip_gettext_processing', 'famma_child_skip_translatepress_gettext', 10, 4 );

/**
 * Le texte est-il majoritairement en arabe ?
 *
 * Les descriptions courtes sont écrites en derja, mais commencent souvent par
 * un mot français (« Visseuse برو 21V… »). `dir="auto"` ne regarde que la
 * première lettre forte : il choisirait la gauche-à-droite, et c'est justement
 * ce qui cassait l'ordre des mots sur le site français (audit du 23/09, FP-11).
 * On compte donc les lettres des deux écritures.
 *
 * @param string $text Texte, HTML accepté.
 * @return bool
 */
function famma_child_is_mostly_arabic( string $text ): bool {
	$plain  = wp_strip_all_tags( $text );
	$arabic = (int) preg_match_all( '/\p{Arabic}/u', $plain );
	$latin  = (int) preg_match_all( '/\p{Latin}/u', $plain );

	return $arabic > $latin;
}

/**
 * Attributs de langue et de sens pour un texte écrit en derja.
 *
 * Rien sur une page déjà en RTL : le sens y est déjà le bon.
 *
 * @param string $text Texte à afficher.
 * @return string ` lang="ar" dir="rtl"`, ou une chaîne vide.
 */
function famma_child_text_dir_attrs( string $text ): string {
	if ( is_rtl() || ! famma_child_is_mostly_arabic( $text ) ) {
		return '';
	}

	return ' lang="ar" dir="rtl"';
}

/**
 * Remet la description courte de la fiche dans son sens de lecture.
 *
 * @param string $html Description courte, déjà mise en forme.
 * @return string
 */
function famma_child_short_description_dir( $html ) {
	if ( ! is_string( $html ) || '' === trim( $html ) ) {
		return $html;
	}

	$attrs = famma_child_text_dir_attrs( $html );

	return '' === $attrs ? $html : '<div' . $attrs . '>' . $html . '</div>';
}
add_filter( 'woocommerce_short_description', 'famma_child_short_description_dir', 20 );
