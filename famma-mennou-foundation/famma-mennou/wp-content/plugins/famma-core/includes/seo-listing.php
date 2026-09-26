<?php
/**
 * Indexation des listes de produits : boutique, rayons, étiquettes.
 *
 * Audit du 25/09 (SEO-02, SEO-07) : WordPress n'émet de balise canonique que
 * sur les contenus singuliers. La boutique et les rayons n'en avaient aucune,
 * et chaque tri ou filtre (`?orderby=`, `?min_price=`, `?product_cat=`…)
 * créait une page indexable en double de la liste d'origine.
 *
 * Règles appliquées :
 * - liste sans tri ni filtre : canonique vers elle-même, pagination et langue
 *   comprises (l'URL demandée, sans ses paramètres) ;
 * - tri ou filtre : `noindex, follow`, sans canonique, car les deux signaux se
 *   contrediraient. Les liens restent suivis vers les fiches ;
 * - rayon vide : `noindex, follow`.
 *
 * Les paramètres de suivi publicitaire (`utm_*`, `fbclid`…) ne comptent pas
 * comme des filtres : une visite venue d'une pub garde la canonique propre.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Canonique et directives robots des listes WooCommerce.
 */
final class Seo_Listing {

	/**
	 * Paramètres qui trient ou filtrent une liste.
	 */
	private const VARIANT_PARAMS = array( 'orderby', 'order', 'min_price', 'max_price', 'stock_status', 'on_sale', 'rating_filter', 'product_cat', 'product_tag' );

	/**
	 * Préfixes des filtres par attribut de WooCommerce.
	 */
	private const VARIANT_PREFIXES = array( 'filter_', 'query_type_' );

	/**
	 * Instance partagée.
	 *
	 * @var Seo_Listing|null
	 */
	private static ?Seo_Listing $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Seo_Listing
	 */
	public static function instance(): Seo_Listing {
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
		add_action( 'wp_head', array( $this, 'render_canonical' ), 2 );
		add_filter( 'wp_robots', array( $this, 'filter_robots' ), 20 );
	}

	/**
	 * Émet la canonique d'une liste sans tri ni filtre.
	 *
	 * @return void
	 */
	public function render_canonical(): void {
		if ( Seo::delegated() || ! self::is_listing() || self::is_variant() || self::is_empty_term() ) {
			return;
		}

		$url = self::clean_request_url();

		if ( '' !== $url ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $url ) );
		}
	}

	/**
	 * Sort de l'index les variantes triées ou filtrées et les rayons vides.
	 *
	 * @param array<string, mixed> $robots Directives calculées par WordPress.
	 * @return array<string, mixed>
	 */
	public function filter_robots( $robots ) {
		if ( ! is_array( $robots ) || ! self::is_listing() ) {
			return $robots;
		}

		if ( ! self::is_variant() && ! self::is_empty_term() ) {
			return $robots;
		}

		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'], $robots['nofollow'] );

		return $robots;
	}

	/**
	 * Vrai sur la boutique ou une archive de produits.
	 *
	 * @return bool
	 */
	private static function is_listing(): bool {
		if ( ! function_exists( 'is_shop' ) || is_search() ) {
			return false;
		}

		return is_shop() || is_product_taxonomy();
	}

	/**
	 * Vrai si la requête porte un tri ou un filtre.
	 *
	 * @return bool
	 */
	private static function is_variant(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture des noms de paramètres, aucune écriture.
		foreach ( array_keys( (array) $_GET ) as $key ) {
			$key = sanitize_key( (string) $key );

			if ( in_array( $key, self::VARIANT_PARAMS, true ) ) {
				return true;
			}

			foreach ( self::VARIANT_PREFIXES as $prefix ) {
				if ( str_starts_with( $key, $prefix ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Vrai sur un rayon ou une étiquette sans produit publié.
	 *
	 * @return bool
	 */
	private static function is_empty_term(): bool {
		if ( ! is_product_taxonomy() ) {
			return false;
		}

		$term = get_queried_object();

		return $term instanceof \WP_Term && 0 === (int) $term->count;
	}

	/**
	 * URL demandée, sans paramètres ni fragment.
	 *
	 * Construite à partir du chemin réel plutôt que de `get_term_link()` :
	 * le préfixe de langue (`/ar/`) et la pagination (`/page/2/`) sont ainsi
	 * conservés tels que le visiteur les a demandés. L'hôte vient de l'option
	 * `home`, qui n'est pas réécrite par l'extension de traduction.
	 *
	 * @return string
	 */
	private static function clean_request_url(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = wp_parse_url( (string) get_option( 'home' ) );

		if ( '' === $path || ! is_array( $home ) || empty( $home['host'] ) ) {
			return '';
		}

		$scheme = isset( $home['scheme'] ) ? $home['scheme'] : 'https';

		return $scheme . '://' . $home['host'] . $path;
	}
}
