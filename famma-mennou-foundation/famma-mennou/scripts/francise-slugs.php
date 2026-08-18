<?php
/**
 * Francise les URL de la boutique (E-37 du plan UX).
 *
 * Un site francais qui sert `/shop/`, `/cart/` et `/product/` melange deux
 * langues dans sa barre d'adresse. Sur une boutique COD tunisienne, l'URL est
 * lue : elle est copiee dans WhatsApp, dictee au telephone, collee dans une
 * publicite. Elle doit se prononcer.
 *
 * Idempotent : relançable sans effet de bord. Une page deja renommee n'est pas
 * touchee, et le script s'arrete sur toute collision de slug plutot que
 * d'ecraser une page existante.
 *
 * **Moment choisi a dessein.** Le catalogue ne contient encore aucun produit
 * reel : renommer maintenant ne coute rien. Le meme changement apres la mise en
 * ligne demanderait un plan de redirections complet — WordPress redirige seul
 * les anciens slugs de PAGE (`wp_old_slug_redirect`), mais **pas** le prefixe
 * des produits, qui est une regle de reecriture et non un contenu.
 *
 * Usage : docker compose exec wpcli wp eval-file /scripts/francise-slugs.php
 *
 * @package Famma\Local
 */

defined( 'ABSPATH' ) || exit;

( static function (): void {

	if ( ! function_exists( 'wc_get_page_id' ) ) {
		echo "WooCommerce inactif : rien a faire.\n";
		return;
	}

	echo "Francisation des URL :\n";

	/*
	 * Pages WooCommerce. `myaccount` et `checkout` sont inclus : le parcours
	 * COD y passe, et une adresse mi-anglaise au moment de payer entame la
	 * confiance autant qu'une faute d'orthographe.
	 */
	$pages = array(
		'shop'      => 'boutique',
		'cart'      => 'panier',
		'checkout'  => 'commande',
		'myaccount' => 'mon-compte',
	);

	foreach ( $pages as $key => $slug ) {
		$page_id = (int) wc_get_page_id( $key );

		if ( $page_id <= 0 ) {
			echo '  ' . str_pad( $key, 12 ) . "page absente, ignoree\n";
			continue;
		}

		$page = get_post( $page_id );

		if ( ! $page instanceof WP_Post ) {
			echo '  ' . str_pad( $key, 12 ) . "introuvable, ignoree\n";
			continue;
		}

		if ( $page->post_name === $slug ) {
			echo '  ' . str_pad( $key, 12 ) . '/' . $slug . "/ deja en place\n";
			continue;
		}

		// Collision : une autre page occupe deja ce slug. On ne l'ecrase pas.
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing instanceof WP_Post && $existing->ID !== $page_id ) {
			echo '  ' . str_pad( $key, 12 ) . 'COLLISION avec la page #' . $existing->ID . ", ignoree\n";
			continue;
		}

		$result = wp_update_post(
			array(
				'ID'        => $page_id,
				'post_name' => $slug,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			echo '  ' . str_pad( $key, 12 ) . 'ECHEC : ' . $result->get_error_message() . "\n";
			continue;
		}

		echo '  ' . str_pad( $key, 12 ) . '/' . $page->post_name . '/ -> /' . $slug . "/\n";
	}

	/*
	 * Prefixes de reecriture des produits. Contrairement aux pages, aucun
	 * mecanisme du coeur ne redirige l'ancienne forme : c'est pour cela que
	 * l'operation se fait tant que le catalogue est vide.
	 */
	$permalinks = get_option( 'woocommerce_permalinks', array() );
	if ( ! is_array( $permalinks ) ) {
		$permalinks = array();
	}

	$bases = array(
		'product_base'   => 'produit',
		'category_base'  => 'categorie',
		'tag_base'       => 'etiquette',
		'attribute_base' => 'caracteristique',
	);

	$changed = false;
	foreach ( $bases as $key => $value ) {
		$current = isset( $permalinks[ $key ] ) ? (string) $permalinks[ $key ] : '';

		if ( $current === $value ) {
			echo '  ' . str_pad( $key, 16 ) . '/' . $value . "/ deja en place\n";
			continue;
		}

		$permalinks[ $key ] = $value;
		$changed            = true;
		echo '  ' . str_pad( $key, 16 ) . ( '' === $current ? '(defaut)' : '/' . $current . '/' ) . ' -> /' . $value . "/\n";
	}

	if ( $changed ) {
		update_option( 'woocommerce_permalinks', $permalinks );
	}

	// Sans cette purge, les nouvelles URL renvoient 404 jusqu'au prochain
	// enregistrement des permaliens depuis l'administration.
	flush_rewrite_rules( false );

	echo "  Regles de reecriture regenerees.\n";
	echo "  WordPress redirige seul les anciens slugs de page ; le prefixe\n";
	echo "  produit, lui, n'a pas de redirection — d'ou le choix de le changer\n";
	echo "  avant la mise en ligne du premier produit reel.\n";
} )();
