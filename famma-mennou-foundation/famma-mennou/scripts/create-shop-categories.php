<?php
/**
 * Cree l'arborescence de categories produit de la maquette.
 *
 * Une categorie est une **structure de catalogue**, pas une donnee inventee :
 * elle ne pretend rien sur un produit, un avis ou un chiffre d'affaires. Le
 * §58 interdit les faux produits et les faux temoignages ; il n'interdit pas
 * de preparer les rayons avant de les remplir.
 *
 * Le compteur affiche a cote de chaque categorie reste le compteur REEL de
 * WooCommerce : une categorie vide affiche zero. Personne n'est trompe.
 *
 * Idempotent : une categorie deja presente n'est ni dupliquee ni renommee, et
 * une categorie renommee a la main par le proprietaire n'est pas ecrasee.
 *
 * « Nouveautes » n'est volontairement PAS une categorie : dans la maquette,
 * c'est une tuile qui mene aux derniers produits publies, pas un rayon. Elle
 * est servie par `?orderby=date`, sans quoi il faudrait ranger chaque produit
 * dans un rayon « Nouveautes » puis l'en sortir a la main.
 *
 * Usage : docker compose exec wpcli wp eval-file /scripts/create-shop-categories.php
 *
 * @package Famma\Local
 */

defined( 'ABSPATH' ) || exit;

( static function (): void {

	if ( ! taxonomy_exists( 'product_cat' ) ) {
		echo "WooCommerce inactif : rien a faire.\n";
		return;
	}

	/*
	 * Les cinq rayons de la maquette, dans son ordre. Le slug est explicite :
	 * il finit dans l'URL, donc il doit se prononcer (voir francise-slugs.php).
	 */
	$categories = array(
		'maison-cuisine' => 'Maison & Cuisine',
		'electronique'   => 'Électronique',
		'auto-moto'      => 'Auto & Moto',
		'beaute-sante'   => 'Beauté & Santé',
		'accessoires'    => 'Accessoires',
	);

	echo "Categories produit :\n";

	$order = 0;
	foreach ( $categories as $slug => $name ) {
		++$order;

		$existing = get_term_by( 'slug', $slug, 'product_cat' );

		if ( $existing instanceof WP_Term ) {
			echo '  ' . str_pad( $name, 22 ) . "deja presente (#{$existing->term_id})\n";
			continue;
		}

		$result = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );

		if ( is_wp_error( $result ) ) {
			echo '  ' . str_pad( $name, 22 ) . 'ECHEC : ' . $result->get_error_message() . "\n";
			continue;
		}

		// L'ordre d'affichage de WooCommerce, sinon la liste sort par identifiant.
		update_term_meta( (int) $result['term_id'], 'order', $order );

		echo '  ' . str_pad( $name, 22 ) . "creee (#{$result['term_id']})\n";
	}

	/*
	 * « Uncategorized » est le rayon par defaut de WooCommerce. Il n'est pas
	 * supprime ici — effacer un terme deplacerait les produits qui s'y
	 * trouvent, ce qui est irreversible et releve d'une decision. On se
	 * contente de le renommer s'il porte encore son nom anglais, pour qu'il ne
	 * depare pas dans un fil d'Ariane francais.
	 */
	$default_id = (int) get_option( 'default_product_cat' );
	$default    = $default_id > 0 ? get_term( $default_id, 'product_cat' ) : null;

	if ( $default instanceof WP_Term && in_array( $default->name, array( 'Uncategorized', 'Non classé' ), true ) ) {
		wp_update_term( $default->term_id, 'product_cat', array( 'name' => 'Sans rayon' ) );
		echo '  ' . str_pad( 'Uncategorized', 22 ) . "renommee « Sans rayon »\n";
		echo "  (non supprimee : effacer un terme deplace les produits qu'il contient)\n";
	}

	echo "  Les compteurs affiches restent ceux de WooCommerce : une categorie\n";
	echo "  vide affiche zero, aucun chiffre n'est simule (§58).\n";
} )();
