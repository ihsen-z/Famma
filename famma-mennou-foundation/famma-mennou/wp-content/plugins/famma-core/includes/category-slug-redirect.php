<?php
/**
 * Redirection 301 des anciens slugs de catégories produit.
 *
 * WordPress ne redirige pas l'ancien slug d'un terme : renommer « Beauté &
 * Santé » en « Beauté & Soin » casserait /categorie/beaute-sante/ (liens des
 * publicités, des moteurs de recherche, des favoris). Ce fichier mémorise
 * l'ancien slug à chaque changement et, sur une page introuvable seulement,
 * renvoie en 301 vers le lien actuel du terme (variantes /page/N/ et /ar/).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Anciens slugs de product_cat : mémorisation et redirection.
 */
final class Category_Slug_Redirect {

	/**
	 * Option : ancien slug => identifiant du terme (autoload désactivé).
	 */
	public const OPTION = 'famma_cat_slug_redirects';

	/**
	 * Taxonomie concernée.
	 */
	private const TAXONOMY = 'product_cat';

	/**
	 * Nombre maximal d'anciens slugs mémorisés.
	 */
	private const MAX_ENTRIES = 200;

	/**
	 * Instance partagée.
	 *
	 * @var Category_Slug_Redirect|null
	 */
	private static ?Category_Slug_Redirect $i = null;

	/**
	 * Anciens slugs en attente de comparaison : identifiant => slug.
	 *
	 * @var array<int, string>
	 */
	private array $pending = array();

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Category_Slug_Redirect
	 */
	public static function instance(): Category_Slug_Redirect {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'edit_terms', array( self::$i, 'remember_old_slug' ), 10, 2 );
			add_action( 'edited_' . self::TAXONOMY, array( self::$i, 'store_if_changed' ) );
			add_action( 'template_redirect', array( self::$i, 'maybe_redirect' ), 1 );
		}
		return self::$i;
	}

	/**
	 * Slugs historiques connus d'avance : ancien slug => slugs possibles du terme actuel.
	 *
	 * Couvre un renommage fait avant que le crochet n'ait vu l'ancien slug.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function seed(): array {
		$seed = array(
			'beaute-sante' => array( 'beaute-soin', 'beaute-sante' ),
		);

		return (array) apply_filters( 'famma_cat_slug_aliases', $seed );
	}

	/**
	 * Lit un chemin de requête (sans préfixe de sous-dossier du site).
	 *
	 * @param string             $path  Chemin commençant par « / ».
	 * @param string             $base  Base des catégories (« categorie »).
	 * @param array<int, string> $langs Préfixes de langue acceptés (« ar »).
	 * @return array{lang: string, slug: string, page: int}|null Null si ce n'est pas une page catégorie.
	 */
	public static function parse_path( string $path, string $base, array $langs ): ?array {
		$lang_part = '';
		if ( array() !== $langs ) {
			$lang_part = '(?:(' . implode( '|', array_map( 'preg_quote', $langs ) ) . ')/)?';
		}

		$pattern = '#^/' . $lang_part . preg_quote( trim( $base, '/' ), '#' ) . '/([^/]+)(?:/page/([0-9]+))?/?$#';
		if ( 1 !== preg_match( $pattern, $path, $m ) ) {
			return null;
		}

		$has_lang = '' !== $lang_part;
		$slug     = sanitize_title( rawurldecode( $m[ $has_lang ? 2 : 1 ] ) );
		$page     = (int) ( $m[ $has_lang ? 3 : 2 ] ?? 1 );

		return '' === $slug ? null : array(
			'lang' => $has_lang ? (string) $m[1] : '',
			'slug' => $slug,
			'page' => max( 1, $page ),
		);
	}

	/**
	 * Adresse de destination : lien actuel du terme, langue, page et requête reportées.
	 *
	 * @param string $term_link Lien actuel du terme (absolu).
	 * @param string $lang      Préfixe de langue de la requête, ou chaîne vide.
	 * @param int    $page      Numéro de page demandé.
	 * @param string $query     Chaîne de requête d'origine (sans « ? »).
	 * @return string
	 */
	public static function build_target( string $term_link, string $lang, int $page, string $query ): string {
		$parts = (array) wp_parse_url( $term_link );
		$path  = '/' . trim( (string) ( $parts['path'] ?? '' ), '/' ) . '/';

		if ( '' !== $lang && 0 !== strpos( $path, '/' . $lang . '/' ) ) {
			$path = '/' . $lang . $path;
		}
		if ( $page > 1 ) {
			$path .= 'page/' . $page . '/';
		}

		$origin = isset( $parts['scheme'], $parts['host'] ) ? $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) : '';
		$target = $origin . $path;

		return '' === $query ? $target : $target . '?' . $query;
	}

	/**
	 * Décide de la redirection, sans accès à WordPress.
	 *
	 * @param string                   $path        Chemin de la requête.
	 * @param string                   $query       Chaîne de requête d'origine.
	 * @param string                   $base        Base des catégories.
	 * @param array<int, string>       $langs       Préfixes de langue.
	 * @param array<string, int>       $map         Ancien slug => identifiant du terme.
	 * @param array<string, array>     $seed        Slugs historiques connus d'avance.
	 * @param callable(string): bool   $slug_exists Vrai si un terme porte ce slug.
	 * @param callable(string): string $link_by_slug Lien du terme portant ce slug, ou chaîne vide.
	 * @param callable(int): string    $link_by_id  Lien du terme d'identifiant donné, ou chaîne vide.
	 * @return string|null Adresse cible, ou null pour ne rien faire.
	 */
	public static function decide( string $path, string $query, string $base, array $langs, array $map, array $seed, callable $slug_exists, callable $link_by_slug, callable $link_by_id ): ?string {
		$request = self::parse_path( $path, $base, $langs );
		if ( null === $request || $slug_exists( $request['slug'] ) ) {
			return null;
		}

		$link = '';
		if ( isset( $map[ $request['slug'] ] ) ) {
			$link = $link_by_id( (int) $map[ $request['slug'] ] );
		}
		if ( '' === $link && isset( $seed[ $request['slug'] ] ) ) {
			foreach ( (array) $seed[ $request['slug'] ] as $candidate ) {
				$link = $slug_exists( (string) $candidate ) ? $link_by_slug( (string) $candidate ) : '';
				if ( '' !== $link ) {
					break;
				}
			}
		}
		if ( '' === $link ) {
			return null;
		}

		$target = self::build_target( $link, $request['lang'], $request['page'], $query );

		// Jamais de boucle : la cible ne doit pas être la page demandée.
		return trim( (string) wp_parse_url( $target, PHP_URL_PATH ), '/' ) === trim( $path, '/' ) ? null : $target;
	}

	/**
	 * Met à jour la table des anciens slugs après un changement.
	 *
	 * @param array<string, int> $map     Table actuelle.
	 * @param int                $term_id Identifiant du terme.
	 * @param string             $old     Ancien slug.
	 * @param string             $current Nouveau slug.
	 * @return array<string, int>
	 */
	public static function record_change( array $map, int $term_id, string $old, string $current ): array {
		if ( '' === $old || $old === $current ) {
			return $map;
		}

		// Un slug redevenu actuel n'est plus un ancien slug.
		unset( $map[ $current ] );
		$map[ $old ] = $term_id;

		return array_slice( $map, -self::MAX_ENTRIES, null, true );
	}

	/**
	 * Mémorise le slug d'un terme juste avant sa modification.
	 *
	 * @param int    $term_id  Identifiant du terme.
	 * @param string $taxonomy Taxonomie.
	 * @return void
	 */
	public function remember_old_slug( int $term_id, string $taxonomy ): void {
		if ( self::TAXONOMY !== $taxonomy ) {
			return;
		}

		$term = get_term( $term_id, self::TAXONOMY );
		if ( $term instanceof \WP_Term ) {
			$this->pending[ $term_id ] = $term->slug;
		}
	}

	/**
	 * Enregistre l'ancien slug si le slug a changé.
	 *
	 * @param int $term_id Identifiant du terme.
	 * @return void
	 */
	public function store_if_changed( int $term_id ): void {
		$old = $this->pending[ $term_id ] ?? '';
		unset( $this->pending[ $term_id ] );

		$term = get_term( $term_id, self::TAXONOMY );
		if ( '' === $old || ! $term instanceof \WP_Term ) {
			return;
		}

		$map = get_option( self::OPTION, array() );
		$map = self::record_change( is_array( $map ) ? $map : array(), $term_id, $old, $term->slug );
		update_option( self::OPTION, $map, false );
	}

	/**
	 * Sur une page introuvable, redirige en 301 vers le lien actuel de la catégorie.
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		if ( ! is_404() ) {
			return;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $home && 0 === strpos( $path, $home . '/' ) ) {
			$path = substr( $path, strlen( $home ) );
		}

		$permalinks = function_exists( 'wc_get_permalink_structure' ) ? wc_get_permalink_structure() : array();
		$base       = (string) apply_filters( 'famma_cat_redirect_base', $permalinks['category_rewrite_slug'] ?? 'categorie' );
		$langs      = (array) apply_filters( 'famma_cat_redirect_langs', array( 'ar' ) );
		$map        = get_option( self::OPTION, array() );

		$link_of = static function ( $term ): string {
			$link = $term instanceof \WP_Term ? get_term_link( $term ) : '';
			return is_string( $link ) ? $link : '';
		};

		$target = self::decide(
			$path,
			(string) wp_parse_url( $uri, PHP_URL_QUERY ),
			$base,
			array_map( 'strval', $langs ),
			is_array( $map ) ? $map : array(),
			self::seed(),
			static fn( string $slug ): bool => get_term_by( 'slug', $slug, self::TAXONOMY ) instanceof \WP_Term,
			static fn( string $slug ): string => $link_of( get_term_by( 'slug', $slug, self::TAXONOMY ) ),
			static fn( int $id ): string => $link_of( get_term( $id, self::TAXONOMY ) )
		);

		if ( null !== $target ) {
			wp_safe_redirect( esc_url_raw( $target ), 301 );
			exit;
		}
	}
}
