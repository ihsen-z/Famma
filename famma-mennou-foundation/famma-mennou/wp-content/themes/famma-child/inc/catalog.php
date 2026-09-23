<?php
/**
 * Rayons du catalogue — source unique.
 *
 * La colonne de filtres de la boutique et le menu principal doivent lister les
 * mêmes rayons, dans le même ordre : deux requêtes séparées finissent toujours
 * par diverger. Ce fichier ne rend rien, il ne fait que répondre à la question
 * « quels sont les rayons, et dans quel ordre ? ».
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Les rayons du catalogue, dans l'ordre de la boutique.
 *
 * Ordre : la méta `order` que WooCommerce écrit quand on trie les catégories
 * dans l'admin. Un rayon créé sans ce tri n'a pas cette méta ; il passe donc
 * en fin de liste au lieu de disparaître.
 *
 * C'est le piège qui était en place : la colonne de filtres triait avec
 * `orderby => meta_value_num`, ce qui JOINT la table des métas et écarte
 * silencieusement tout terme sans méta `order`. Une catégorie ajoutée depuis
 * l'admin restait donc invisible en boutique — un tri ne doit jamais filtrer.
 *
 * La catégorie par défaut de WooCommerce (« Sans rayon ») est écartée : c'est
 * le fourre-tout des produits non rangés, pas un rayon à proposer au client.
 *
 * @return array<int, WP_Term> Termes `product_cat`, hors catégorie par défaut.
 */
function famma_child_product_categories(): array {
	/*
	 * Rayons vides écartés : menu, carrousel d'accueil et colonne de filtres
	 * proposaient 4 rayons à 0 produit, et le premier clic d'exploration menait
	 * à « Aucun produit ne correspond » (audit du 23/09, UX-09 / SEO-07). Un
	 * rayon réapparaît de lui-même dès qu'un produit y est publié.
	 */
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
		)
	);

	if ( is_wp_error( $terms ) || array() === $terms ) {
		return array();
	}

	$default = (int) get_option( 'default_product_cat' );

	$terms = array_values(
		array_filter(
			$terms,
			static function ( WP_Term $term ) use ( $default ): bool {
				return (int) $term->term_id !== $default;
			}
		)
	);

	usort( $terms, 'famma_child_compare_category_order' );

	return $terms;
}

/**
 * Compare deux rayons : méta `order` d'abord, nom ensuite.
 *
 * @param WP_Term $first  Premier terme.
 * @param WP_Term $second Second terme.
 * @return int
 */
function famma_child_compare_category_order( WP_Term $first, WP_Term $second ): int {
	$rank = famma_child_category_rank( $first ) <=> famma_child_category_rank( $second );

	if ( 0 !== $rank ) {
		return $rank;
	}

	return strnatcasecmp( $first->name, $second->name );
}

/**
 * Rang de tri d'un rayon.
 *
 * Sans méta `order`, le rayon vaut `PHP_INT_MAX` : il se range après les
 * rayons ordonnés, et reste visible.
 *
 * @param WP_Term $term Terme `product_cat`.
 * @return int
 */
function famma_child_category_rank( WP_Term $term ): int {
	$order = get_term_meta( $term->term_id, 'order', true );

	return '' === $order ? PHP_INT_MAX : (int) $order;
}

/**
 * URL d'un rayon : sa page catégorie (`/categorie/<slug>/`).
 *
 * Volontairement distincte de `famma_child_filter_url()` : celle-ci reporte
 * les autres paramètres de l'URL courante, ce qui a un sens dans la colonne de
 * filtres — cocher « en promotion » ne doit pas effacer le rayon choisi — mais
 * pas dans l'en-tête, présent sur toutes les pages du site. Un lien de menu
 * doit mener au même endroit qu'on clique depuis le panier ou depuis une fiche.
 *
 * La page catégorie plutôt que `/boutique/?product_cat=` : c'est elle qui est
 * dans le plan de site. Les 98 liens internes du menu et du carrousel
 * alimentaient jusqu'ici un doublon à paramètre, sans canonical, et laissaient
 * les vraies pages rayon sans aucun lien (audit du 23/09, SEO-01).
 *
 * @param string $slug Identifiant du rayon, ou chaîne vide pour tout le catalogue.
 * @return string
 */
function famma_child_product_category_url( string $slug ): string {
	$base = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

	if ( '' === $slug ) {
		return (string) $base;
	}

	$link = get_term_link( $slug, 'product_cat' );

	return is_wp_error( $link ) ? add_query_arg( 'product_cat', $slug, (string) $base ) : $link;
}
