<?php
/**
 * Référencement naturel et optimisation pour les moteurs génératifs (GEO).
 *
 * Un seul propriétaire pour tout ce qui sort dans le `<head>` : description,
 * Open Graph, titres, données structurées, directives d'indexation. Le thème
 * s'efface dès que cette classe existe (voir `famma_child_open_graph()`) —
 * deux jeux de balises concurrents valent moins qu'aucun.
 *
 * Aucune donnée n'est inventée ici (§58/§60) : les coordonnées viennent des
 * réglages, les questions/réponses du schéma FAQ sont extraites du contenu
 * réellement publié sur la page, et rien n'est émis quand la source est vide.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Émet les métadonnées de référencement du front.
 */
final class Seo {

	/**
	 * Option abritant les réglages globaux de référencement.
	 */
	public const OPTION = 'famma_seo';

	/**
	 * Préfixe des méta-données par contenu.
	 */
	public const META_PREFIX = '_famma_seo_';

	/**
	 * Longueur maximale d'une description servie aux moteurs.
	 */
	private const MAX_DESCRIPTION = 160;

	/**
	 * Instance unique.
	 *
	 * @var Seo|null
	 */
	private static ?Seo $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Seo
	 */
	public static function instance(): Seo {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'wp_head', array( $this, 'render_meta' ), 3 );
		add_action( 'wp_head', array( $this, 'render_structured_data' ), 4 );
		add_action( 'wp_head', array( $this, 'render_x_default' ), 5 );

		add_filter( 'pre_get_document_title', array( $this, 'filter_title' ), 20 );
		add_filter( 'wp_robots', array( $this, 'filter_robots' ), 20 );

		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 20, 2 );
		add_action( 'parse_request', array( $this, 'maybe_serve_llms_txt' ) );

		// Plan de site : ni les comptes, ni les pages de tunnel.
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'filter_sitemap_provider' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'filter_sitemap_posts' ), 10, 2 );

		// Les archives d'auteur n'ont aucun contenu et exposent l'identifiant
		// de connexion : elles répondent 404.
		add_action( 'template_redirect', array( $this, 'block_author_archives' ), 1 );
	}

	// --- Lecture des réglages ---

	/**
	 * Lit un réglage global de référencement.
	 *
	 * @param string $key     Clé du réglage.
	 * @param string $fallback Valeur de repli.
	 * @return string
	 */
	public static function setting( string $key, string $fallback = '' ): string {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$value = isset( $stored[ $key ] ) ? (string) $stored[ $key ] : '';

		return '' !== trim( $value ) ? $value : $fallback;
	}

	/**
	 * Lit une coordonnée : la constante `.env` l'emporte, l'option est le repli.
	 *
	 * Même ordre de priorité que `famma_child_setting()` côté thème (§8).
	 *
	 * @param string $constant Nom de la constante, ex. `FAMMA_CONTACT_EMAIL`.
	 * @return string Chaîne vide si rien n'est configuré.
	 */
	public static function contact( string $constant ): string {
		if ( defined( $constant ) && '' !== (string) constant( $constant ) ) {
			return (string) constant( $constant );
		}

		return (string) get_option( strtolower( $constant ), '' );
	}

	/**
	 * Suffixe de langue à utiliser pour le visiteur courant.
	 *
	 * @return string `ar` ou `fr`.
	 */
	private static function lang(): string {
		return is_rtl() ? 'ar' : 'fr';
	}

	/**
	 * Lit un champ bilingue, avec repli de l'arabe vers le français.
	 *
	 * @param callable $reader Fonction recevant le suffixe de langue.
	 * @return string
	 */
	private static function bilingual( callable $reader ): string {
		$lang  = self::lang();
		$value = (string) $reader( $lang );

		if ( '' === trim( $value ) && 'ar' === $lang ) {
			$value = (string) $reader( 'fr' );
		}

		return trim( $value );
	}

	// --- Description ---

	/**
	 * Raccourcit un texte à la longueur utile d'un extrait de résultat.
	 *
	 * La coupe se fait sur un mot entier, jamais au milieu.
	 *
	 * @param string $text Texte source, éventuellement balisé.
	 * @return string
	 */
	private static function trim_description( string $text ): string {
		$text = wp_strip_all_tags( strip_shortcodes( $text ), true );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

		if ( '' === $text ) {
			return '';
		}

		if ( mb_strlen( $text ) <= self::MAX_DESCRIPTION ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, self::MAX_DESCRIPTION );
		$space = mb_strrpos( $cut, ' ' );

		if ( false !== $space && $space > 60 ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		return rtrim( $cut, " \t\n\r\0\x0B,;:.–-" ) . '…';
	}

	/**
	 * Description de la page courante, prête à être servie.
	 *
	 * Ordre de repli : saisie manuelle → extrait / description courte du
	 * produit → début du contenu publié → description d'accueil réglée en
	 * administration → slogan du site. Aucune étape n'invente de texte.
	 *
	 * @return string Chaîne vide si aucune source n'est renseignée.
	 */
	public static function description(): string {
		$manual = self::bilingual(
			static function ( string $lang ): string {
				$id = self::queried_post_id();

				if ( 0 === $id ) {
					return '';
				}

				return (string) get_post_meta( $id, self::META_PREFIX . 'description_' . $lang, true );
			}
		);

		if ( '' !== $manual ) {
			return self::trim_description( $manual );
		}

		if ( is_front_page() ) {
			$home = self::bilingual(
				static function ( string $lang ): string {
					return self::setting( 'home_description_' . $lang );
				}
			);

			if ( '' !== $home ) {
				return self::trim_description( $home );
			}
		}

		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term && '' !== trim( (string) $term->description ) ) {
				return self::trim_description( (string) $term->description );
			}
		}

		$id = self::queried_post_id();

		if ( $id > 0 ) {
			$post = get_post( $id );

			if ( $post instanceof \WP_Post ) {
				if ( '' !== trim( (string) $post->post_excerpt ) ) {
					return self::trim_description( (string) $post->post_excerpt );
				}

				$content = self::without_owner_notes( (string) $post->post_content );

				if ( '' !== trim( $content ) ) {
					$trimmed = self::trim_description( $content );

					if ( '' !== $trimmed ) {
						return $trimmed;
					}
				}

				/**
				 * Description d'une page composée depuis FAMMA → Pages.
				 *
				 * La page Livraison n'a presque rien dans l'éditeur : son texte
				 * vit dans les réglages (`Delivery_Content`), qui répond ici.
				 *
				 * @param string   $description Description proposée (vide).
				 * @param \WP_Post $post        Page affichée.
				 */
				$composed = trim( (string) apply_filters( 'famma_seo_composed_description', '', $post ) );

				if ( '' !== $composed ) {
					return self::trim_description( $composed );
				}
			}
		}

		return self::trim_description( (string) get_bloginfo( 'description' ) );
	}

	/**
	 * Retire du contenu les notes de chantier réservées au propriétaire.
	 *
	 * Les encarts `.famma-todo` ne sont montrés qu'aux administrateurs ; la
	 * description était pourtant tirée du contenu brut. Constaté le 25/09 :
	 * « À compléter par le propriétaire… » était servi à Google et aux aperçus
	 * de partage des pages Conditions générales et Livraison.
	 *
	 * @param string $html Contenu brut de l'éditeur.
	 * @return string
	 */
	private static function without_owner_notes( string $html ): string {
		return (string) preg_replace( '#<(p|div)[^>]*class="[^"]*\bfamma-todo\b[^"]*"[^>]*>.*?</\1>\s*#is', '', $html );
	}

	/**
	 * Identifiant du contenu affiché, 0 hors contexte singulier.
	 *
	 * La boutique WooCommerce est une page : elle a bien un identifiant, même
	 * si `is_singular()` répond faux.
	 *
	 * @return int
	 */
	private static function queried_post_id(): int {
		if ( is_singular() ) {
			return (int) get_queried_object_id();
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return (int) wc_get_page_id( 'shop' );
		}

		return 0;
	}

	// --- Sortie dans le `<head>` ---

	/**
	 * Émet la description, l'Open Graph et les balises Twitter.
	 *
	 * @return void
	 */
	public function render_meta(): void {
		if ( self::delegated() ) {
			return;
		}

		$description = self::description();
		$title       = is_front_page() ? get_bloginfo( 'name' ) : wp_get_document_title();
		$image       = self::share_image();

		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		}

		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( is_singular( 'product' ) ? 'product' : 'website' ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( self::current_url() ) );
		printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( str_replace( '-', '_', get_bloginfo( 'language' ) ) ) );

		if ( '' !== $description ) {
			printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
		}

		if ( '' !== $image ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
			printf( '<meta property="og:image:width" content="%s" />' . "\n", '1200' );
			printf( '<meta property="og:image:height" content="%s" />' . "\n", '630' );
		}

		printf( '<meta name="twitter:card" content="%s" />' . "\n", 'summary_large_image' );

		if ( '' !== $description ) {
			printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $description ) );
		}
	}

	/**
	 * Visuel de partage : l'image mise en avant si elle existe, sinon le visuel de marque.
	 *
	 * @return string
	 */
	private static function share_image(): string {
		$id = self::queried_post_id();

		if ( $id > 0 && has_post_thumbnail( $id ) ) {
			$url = get_the_post_thumbnail_url( $id, 'full' );

			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return get_stylesheet_directory_uri() . '/assets/img/famma-og.png';
	}

	/**
	 * URL canonique de la page courante.
	 *
	 * @return string
	 */
	private static function current_url(): string {
		$id = self::queried_post_id();

		if ( $id > 0 ) {
			$permalink = get_permalink( $id );

			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		return home_url( add_query_arg( array() ) );
	}

	/**
	 * Vrai lorsqu'une extension SEO tierce est active.
	 *
	 * Elle émet alors ses propres balises : les nôtres feraient doublon.
	 *
	 * @return bool
	 */
	public static function delegated(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' );
	}

	// --- Titres ---

	/**
	 * Applique le titre saisi manuellement, ou le gabarit orienté requête.
	 *
	 * @param string $title Titre calculé par WordPress.
	 * @return string
	 */
	public function filter_title( $title ) {
		if ( self::delegated() || is_admin() ) {
			return $title;
		}

		$manual = self::bilingual(
			static function ( string $lang ): string {
				$id = self::queried_post_id();

				if ( 0 === $id ) {
					return '';
				}

				return (string) get_post_meta( $id, self::META_PREFIX . 'title_' . $lang, true );
			}
		);

		if ( '' !== $manual ) {
			return $manual;
		}

		$is_catalog = is_singular( 'product' )
			|| ( function_exists( 'is_shop' ) && is_shop() )
			|| ( function_exists( 'is_product_category' ) && is_product_category() );

		if ( ! $is_catalog ) {
			return self::translated_page_title( (string) $title );
		}

		$suffix = self::bilingual(
			static function ( string $lang ): string {
				return self::setting( 'title_suffix_' . $lang );
			}
		);

		if ( '' === $suffix ) {
			return $title;
		}

		$name = (string) get_bloginfo( 'name' );
		$base = self::localized_title_base();

		if ( '' === $base ) {
			return $title;
		}

		return sprintf( '%s – %s | %s', $base, $suffix, $name );
	}

	/**
	 * Titre d'une page ordinaire (Livraison, FAQ…) sur la version arabe.
	 *
	 * Sans traduction disponible, le titre calculé par WordPress est rendu
	 * tel quel.
	 *
	 * @param string $title Titre calculé par WordPress.
	 * @return string
	 */
	private static function translated_page_title( string $title ): string {
		if ( 'ar' !== self::lang() || is_front_page() || 0 === self::queried_post_id() ) {
			return $title;
		}

		$base = self::localized_title_base();

		if ( '' === $base || self::page_title_base() === $base ) {
			return $title;
		}

		return sprintf( '%s – %s', $base, (string) get_bloginfo( 'name' ) );
	}

	/**
	 * Titre nu de la page courante, dans la langue affichée.
	 *
	 * TranslatePress ne traite pas la balise `<title>` : constaté le 25/09,
	 * « Livraison – FAMMA MINNOU » restait en français sur /ar/ alors que le
	 * titre de la page, le menu et le fil d'Ariane étaient traduits. Le titre
	 * nu lui est donc soumis par sa fonction publique `trp_translate()` :
	 * l'onglet suit la traduction saisie dans l'éditeur de TranslatePress,
	 * sans aucun texte écrit ici. Mémorisé : le titre sert aussi à og:title.
	 *
	 * @return string
	 */
	private static function localized_title_base(): string {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$base = self::page_title_base();

		if ( '' !== $base && 'ar' === self::lang() && function_exists( 'trp_translate' ) ) {
			$translated = trim( wp_strip_all_tags( (string) trp_translate( $base, null, false ) ) );

			if ( '' !== $translated ) {
				$base = esc_html( $translated );
			}
		}

		$cache = $base;

		return $cache;
	}

	/**
	 * Titre nu de la page courante, sans le nom du site.
	 *
	 * @return string
	 */
	private static function page_title_base(): string {
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$term = get_queried_object();

			return $term instanceof \WP_Term ? (string) $term->name : '';
		}

		$id = self::queried_post_id();

		return $id > 0 ? (string) get_the_title( $id ) : '';
	}

	// --- Indexation ---

	/**
	 * Retire de l'index les pages sans valeur de recherche.
	 *
	 * Panier, commande et compte sont des étapes de tunnel : les indexer
	 * dilue le budget de crawl et fait remonter des pages vides sur des
	 * requêtes de marque.
	 *
	 * @param array<string, mixed> $robots Directives calculées par WordPress.
	 * @return array<string, mixed>
	 */
	public function filter_robots( $robots ) {
		if ( ! is_array( $robots ) ) {
			return $robots;
		}

		if ( ! self::is_private_route() ) {
			return $robots;
		}

		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );

		return $robots;
	}

	/**
	 * Vrai sur une page à tenir hors de l'index.
	 *
	 * @return bool
	 */
	private static function is_private_route(): bool {
		if ( is_search() || is_404() || is_author() || is_attachment() ) {
			return true;
		}

		if ( ! function_exists( 'is_cart' ) ) {
			return false;
		}

		return is_cart() || is_checkout() || is_account_page();
	}

	/**
	 * Répond 404 sur les archives d'auteur.
	 *
	 * Elles n'affichent aucun contenu — le catalogue est en produits, pas en
	 * articles — et publient l'identifiant de connexion de l'administrateur.
	 *
	 * @return void
	 */
	public function block_author_archives(): void {
		if ( ! is_author() ) {
			return;
		}

		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();

		$template = get_404_template();

		if ( is_string( $template ) && '' !== $template ) {
			include $template;
			exit;
		}
	}

	// --- Plan de site ---

	/**
	 * Retire le fournisseur « utilisateurs » du plan de site.
	 *
	 * @param mixed  $provider Fournisseur proposé.
	 * @param string $name     Nom du fournisseur.
	 * @return mixed Faux pour annuler l'enregistrement.
	 */
	public function filter_sitemap_provider( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Exclut les pages de tunnel du plan de site.
	 *
	 * @param array<string, mixed> $args      Arguments de la requête.
	 * @param string               $post_type Type de contenu listé.
	 * @return array<string, mixed>
	 */
	public function filter_sitemap_posts( $args, $post_type ) {
		if ( 'page' !== $post_type || ! is_array( $args ) ) {
			return $args;
		}

		$excluded = self::funnel_page_ids();

		if ( array() === $excluded ) {
			return $args;
		}

		$existing = isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ? $args['post__not_in'] : array();

		$args['post__not_in'] = array_values( array_unique( array_merge( $existing, $excluded ) ) );

		return $args;
	}

	/**
	 * Identifiants des pages panier, commande et compte.
	 *
	 * @return array<int, int>
	 */
	private static function funnel_page_ids(): array {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return array();
		}

		$ids = array(
			(int) wc_get_page_id( 'cart' ),
			(int) wc_get_page_id( 'checkout' ),
			(int) wc_get_page_id( 'myaccount' ),
		);

		return array_values(
			array_filter(
				$ids,
				static function ( int $id ): bool {
					return $id > 0;
				}
			)
		);
	}

	// --- robots.txt et llms.txt ---

	/**
	 * Complète le `robots.txt` servi par WordPress.
	 *
	 * Les robots des moteurs génératifs sont autorisés **explicitement** :
	 * c'est ce qui rend la boutique citable dans leurs réponses. Le choix
	 * devient une décision lisible plutôt qu'un effet de bord du défaut.
	 *
	 * @param string $output Contenu calculé par WordPress.
	 * @param string $is_public Réglage « visibilité » du site.
	 * @return string
	 */
	public function filter_robots_txt( $output, $is_public ) {
		if ( '1' !== (string) $is_public ) {
			return $output;
		}

		/*
		 * Un robot n'applique que le groupe qui le nomme : les règles posées
		 * par WordPress et WooCommerce (`/wp-admin/`, `?add-to-cart=`,
		 * journaux) sont donc recopiées dans chaque groupe, avec les nôtres.
		 * Audit du 25/09 (SEO-17) : les agents nommés, Bingbot compris,
		 * échappaient à ces règles, et deux groupes `*` coexistaient.
		 */
		$rules    = array();
		$sitemaps = array();

		foreach ( (array) preg_split( '/\R/', (string) $output ) as $line ) {
			$line = trim( (string) $line );

			if ( preg_match( '/^(dis)?allow\s*:/i', $line ) ) {
				$rules[] = $line;
			} elseif ( preg_match( '/^sitemap\s*:/i', $line ) ) {
				$sitemaps[] = $line;
			}
		}

		foreach ( self::private_paths() as $path ) {
			$rules[] = 'Disallow: ' . $path;
		}

		$rules = array_values( array_unique( $rules ) );
		$lines = array_merge( array( 'User-agent: *' ), $rules, array( '' ) );

		foreach ( self::ai_agents() as $agent ) {
			$lines = array_merge( $lines, array( 'User-agent: ' . $agent, 'Allow: /' ), $rules, array( '' ) );
		}

		return implode( "\n", array_merge( $lines, $sitemaps ) ) . "\n";
	}

	/**
	 * Étapes de tunnel et archives sans valeur de recherche, en français et en arabe.
	 *
	 * @return array<int, string>
	 */
	private static function private_paths(): array {
		return array( '/panier/', '/commande/', '/mon-compte/', '/author/', '/ar/panier/', '/ar/commande/', '/ar/mon-compte/' );
	}

	/**
	 * Robots des moteurs génératifs, autorisés explicitement.
	 *
	 * @return array<int, string>
	 */
	private static function ai_agents(): array {
		return array(
			'GPTBot',
			'OAI-SearchBot',
			'ChatGPT-User',
			'ClaudeBot',
			'Claude-User',
			'anthropic-ai',
			'PerplexityBot',
			'Perplexity-User',
			'Google-Extended',
			'Applebot-Extended',
			'CCBot',
			'meta-externalagent',
		);
	}

	/**
	 * Sert `/llms.txt` : la fiche d'identité du site pour un agent.
	 *
	 * @param \WP $wp Requête résolue.
	 * @return void
	 */
	public function maybe_serve_llms_txt( $wp ): void {
		if ( ! isset( $wp->request ) || 'llms.txt' !== trim( (string) $wp->request, '/' ) ) {
			return;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );

		echo self::llms_txt(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Texte brut, non HTML.
		exit;
	}

	/**
	 * Compose le contenu de `/llms.txt`.
	 *
	 * Rien n'est affirmé qui ne soit appliqué par le code : paiement à la
	 * livraison, livraison offerte, couverture des 24 gouvernorats.
	 *
	 * @return string
	 */
	private static function llms_txt(): string {
		$name  = (string) get_bloginfo( 'name' );
		$home  = untrailingslashit( home_url( '/' ) );
		$intro = self::setting( 'home_description_fr', (string) get_bloginfo( 'description' ) );

		$lines = array(
			'# ' . $name,
			'',
			'> ' . trim( wp_strip_all_tags( $intro ) ),
			'',
			'- Pays desservi : Tunisie (24 gouvernorats)',
			'- Devise : TND',
			'- Paiement : à la livraison, en espèces, au moment de la réception',
			'- Livraison : offerte, incluse dans le prix affiché',
			'- Langues : français, arabe (derja tunisienne)',
			'',
			'## Pages',
			'',
		);

		foreach ( self::key_pages() as $label => $path ) {
			$lines[] = sprintf( '- [%s](%s%s)', $label, $home, $path );
		}

		$email = self::contact( 'FAMMA_CONTACT_EMAIL' );
		$phone = self::contact( 'FAMMA_CONTACT_PHONE' );

		if ( '' !== $email || '' !== $phone ) {
			$lines[] = '';
			$lines[] = '## Contact';
			$lines[] = '';

			if ( '' !== $email ) {
				$lines[] = '- Courriel : ' . $email;
			}

			if ( '' !== $phone ) {
				$lines[] = '- Téléphone : ' . $phone;
			}
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Pages utiles à un agent, avec leur libellé.
	 *
	 * @return array<string, string>
	 */
	private static function key_pages(): array {
		return array(
			'Boutique'             => '/boutique/',
			'À propos'             => '/a-propos/',
			'Questions fréquentes' => '/faq/',
			'Livraison'            => '/livraison/',
			'Retours'              => '/retours/',
			'Contact'              => '/contact/',
			'Plan du site'         => '/wp-sitemap.xml',
		);
	}

	// --- hreflang ---

	/**
	 * Ajoute `hreflang="x-default"` au jeu émis par TranslatePress.
	 *
	 * Sans lui, aucun signal n'indique quelle version servir à un visiteur
	 * hors des zones française et arabe : Google choisit seul.
	 *
	 * @return void
	 */
	public function render_x_default(): void {
		if ( ! function_exists( 'trp_custom_language_switcher' ) ) {
			return;
		}

		$languages = trp_custom_language_switcher();

		if ( ! is_array( $languages ) ) {
			return;
		}

		foreach ( $languages as $code => $language ) {
			if ( ! is_array( $language ) ) {
				continue;
			}

			$code = isset( $language['language_code'] ) ? (string) $language['language_code'] : (string) $code;

			if ( ! str_starts_with( $code, 'fr' ) ) {
				continue;
			}

			$url = isset( $language['current_page_url'] ) ? (string) $language['current_page_url'] : '';

			// Sans ses paramètres : une variante triée ne doit pas devenir la version par défaut (SEO-16).
			$url = explode( '?', $url, 2 )[0];

			if ( '' === $url ) {
				continue;
			}

			printf( '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n", esc_url( $url ) );

			return;
		}
	}

	// --- Données structurées ---

	/**
	 * Émet le graphe schema.org de la page courante.
	 *
	 * Présent sur **toutes** les pages, et non plus sur la seule page
	 * d'accueil : un moteur génératif qui atterrit sur la FAQ n'a autrement
	 * aucun moyen de rattacher la réponse à la boutique.
	 *
	 * @return void
	 */
	public function render_structured_data(): void {
		if ( self::delegated() || self::is_private_route() ) {
			return;
		}

		$graph = array( self::organization_node(), self::website_node() );

		$faq = self::faq_node();

		if ( array() !== $faq ) {
			$graph[] = $faq;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);
	}

	/**
	 * Nœud décrivant la boutique.
	 *
	 * Typé `OnlineStore` : c'est ce qui permet à un moteur de répondre
	 * « boutique en ligne tunisienne, paiement à la livraison » en citant le
	 * site. Aucun champ n'est émis sans valeur réellement configurée (§60).
	 *
	 * @return array<string, mixed>
	 */
	private static function organization_node(): array {
		$home = home_url( '/' );

		$node = array(
			'@type'              => 'OnlineStore',
			'@id'                => $home . '#organization',
			'name'               => get_bloginfo( 'name' ),
			'url'                => $home,
			'logo'               => array(
				'@type'  => 'ImageObject',
				'url'    => get_stylesheet_directory_uri() . '/assets/img/famma-og.png',
				'width'  => 1200,
				'height' => 630,
			),
			'areaServed'         => array(
				'@type' => 'Country',
				'name'  => 'Tunisie',
			),
			'currenciesAccepted' => 'TND',
			'paymentAccepted'    => 'Paiement à la livraison',
			'knowsLanguage'      => array( 'fr', 'ar' ),
		);

		$description = self::setting( 'home_description_fr', (string) get_bloginfo( 'description' ) );

		if ( '' !== trim( $description ) ) {
			$node['description'] = self::trim_description( $description );
		}

		$phone = self::contact( 'FAMMA_CONTACT_PHONE' );

		if ( '' !== $phone ) {
			$node['telephone'] = $phone;
		}

		$email = self::contact( 'FAMMA_CONTACT_EMAIL' );

		if ( '' !== $email ) {
			$node['email'] = $email;
		}

		$address = self::contact( 'FAMMA_CONTACT_ADDRESS' );

		if ( '' !== $address ) {
			$node['address'] = array(
				'@type'           => 'PostalAddress',
				'addressLocality' => $address,
				'addressCountry'  => 'TN',
			);
		}

		$same_as = self::same_as();

		if ( array() !== $same_as ) {
			$node['sameAs'] = $same_as;
		}

		return $node;
	}

	/**
	 * Profils officiels déclarés en administration.
	 *
	 * @return array<int, string>
	 */
	private static function same_as(): array {
		$raw = self::setting( 'same_as' );

		if ( '' === trim( $raw ) ) {
			return array();
		}

		$lines = preg_split( '/\R/u', $raw );
		$lines = is_array( $lines ) ? $lines : array();

		$urls = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line || ! filter_var( $line, FILTER_VALIDATE_URL ) ) {
				continue;
			}

			$urls[] = $line;
		}

		return $urls;
	}

	/**
	 * Nœud `WebSite`, avec l'action de recherche produit.
	 *
	 * @return array<string, mixed>
	 */
	private static function website_node(): array {
		$home = home_url( '/' );

		return array(
			'@type'           => 'WebSite',
			'@id'             => $home . '#website',
			'url'             => $home,
			'name'            => get_bloginfo( 'name' ),
			'inLanguage'      => get_bloginfo( 'language' ),
			'publisher'       => array( '@id' => $home . '#organization' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => $home . '?s={search_term_string}&post_type=product',
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * Nœud `FAQPage`, construit à partir du contenu réellement publié.
	 *
	 * Les questions et réponses ne sont ni saisies deux fois, ni inventées :
	 * elles sont extraites des titres et des paragraphes de la page. Si la
	 * page est vide, aucun schéma n'est émis.
	 *
	 * @return array<string, mixed> Tableau vide hors de la page FAQ.
	 */
	private static function faq_node(): array {
		$id = self::queried_post_id();

		if ( 0 === $id || self::faq_page_id() !== $id ) {
			return array();
		}

		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post ) {
			return array();
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Filtre du cœur, appliqué au contenu publié.
		$rendered = (string) apply_filters( 'the_content', $post->post_content );

		$pairs = self::extract_questions( $rendered );

		if ( array() === $pairs ) {
			return array();
		}

		$items = array();

		foreach ( $pairs as $pair ) {
			$items[] = array(
				'@type'          => 'Question',
				'name'           => $pair['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $pair['answer'],
				),
			);
		}

		return array(
			'@type'      => 'FAQPage',
			'@id'        => get_permalink( $id ) . '#faq',
			'inLanguage' => get_bloginfo( 'language' ),
			'isPartOf'   => array( '@id' => home_url( '/' ) . '#website' ),
			'mainEntity' => $items,
		);
	}

	/**
	 * Identifiant de la page FAQ.
	 *
	 * @return int 0 si la page n'existe pas.
	 */
	private static function faq_page_id(): int {
		$configured = (int) self::setting( 'faq_page_id', '0' );

		if ( $configured > 0 ) {
			return $configured;
		}

		$page = get_page_by_path( 'faq' );

		return $page instanceof \WP_Post ? (int) $page->ID : 0;
	}

	/**
	 * Découpe un contenu HTML en couples question / réponse.
	 *
	 * Un titre `h2`/`h3`/`h4` ouvre une question ; tout ce qui suit jusqu'au
	 * titre suivant en constitue la réponse.
	 *
	 * @param string $html Contenu rendu de la page.
	 * @return array<int, array<string, string>>
	 */
	private static function extract_questions( string $html ): array {
		$parts = preg_split( '/<h[2-4][^>]*>(.*?)<\/h[2-4]>/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( ! is_array( $parts ) || 3 > count( $parts ) ) {
			return array();
		}

		// Le premier segment précède le premier titre : il n'appartient à
		// aucune question.
		array_shift( $parts );

		$pairs = array();
		$count = count( $parts );

		for ( $i = 0; $i + 1 < $count; $i += 2 ) {
			$question = self::plain( (string) $parts[ $i ] );
			$answer   = self::plain( (string) $parts[ $i + 1 ] );

			if ( '' === $question || '' === $answer ) {
				continue;
			}

			$pairs[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		return $pairs;
	}

	/**
	 * Réduit un fragment HTML à du texte lisible.
	 *
	 * @param string $html Fragment.
	 * @return string
	 */
	private static function plain( string $html ): string {
		$text = wp_strip_all_tags( $html, true );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
