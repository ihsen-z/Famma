<?php
/**
 * Écran FAMMA → Nouveau look : état réel de chaque brique et des pages.
 *
 * Une case cochée ne suffit pas à dire ce que voit le client : le mode, une
 * case requise décochée ou un texte vide peuvent la rendre sans effet. Cet
 * état est calculé ici, à l'affichage, et recalculé par le script quand le
 * propriétaire change une case avant d'enregistrer.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * États, libellés et adresses de l'écran.
 */
final class New_Look_Admin_State {

	/**
	 * Libellés des états d'une brique.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'off'     => __( 'Désactivée', 'famma-core' ),
			'missing' => __( 'Sans effet : il manque une case', 'famma-core' ),
			'wait'    => __( 'Cachée tant que le texte est vide', 'famma-core' ),
			'nobody'  => __( 'Prête, mais personne ne la voit', 'famma-core' ),
			'admins'  => __( 'Seulement les administrateurs', 'famma-core' ),
			'all'     => __( 'Visible par tous', 'famma-core' ),
		);
	}

	/**
	 * Lignes du résumé, un `%d` chacune.
	 *
	 * @return array<string, string>
	 */
	public static function summary_labels(): array {
		return array(
			/* translators: %d: nombre de briques. */
			'all'    => __( 'Visibles par tous : %d', 'famma-core' ),
			/* translators: %d: nombre de briques. */
			'admins' => __( 'Visibles par les administrateurs : %d', 'famma-core' ),
			/* translators: %d: nombre de briques. */
			'warn'   => __( 'Sans effet ou en attente d’un texte : %d', 'famma-core' ),
			/* translators: %d: nombre de briques. */
			'off'    => __( 'Éteintes : %d', 'famma-core' ),
		);
	}

	/**
	 * État réel d'une brique.
	 *
	 * @param string               $key      Clé de la brique.
	 * @param array<string, mixed> $brick    Brique normalisée.
	 * @param array<string, mixed> $settings Réglages (`New_Look::settings()`).
	 * @return string Clé de `labels()`.
	 */
	public static function of( string $key, array $brick, array $settings ): string {
		$parts = (array) $settings['parts'];

		if ( empty( $parts[ $key ] ) ) {
			return 'off';
		}

		foreach ( (array) $brick['needs'] as $need ) {
			if ( empty( $parts[ $need ] ) ) {
				return 'missing';
			}
		}

		$page = New_Look_Registry::text_page( (string) $brick['page'] );
		foreach ( (array) $brick['wait'] as $text ) {
			if ( '' !== $page && '' === trim( Pages_Content::text_in( $page, (string) $text, 'fr' ) ) ) {
				return 'wait';
			}
		}

		return match ( (string) $settings['mode'] ) {
			'all'    => 'all',
			'admins' => 'admins',
			default  => 'nobody',
		};
	}

	/**
	 * Titre et phrase d'introduction d'un onglet.
	 *
	 * @param string $page Clé de page du registre.
	 * @return array{title: string, intro: string, view: string}
	 */
	public static function page( string $page ): array {
		return match ( $page ) {
			'home'    => array(
				'title' => __( 'Accueil', 'famma-core' ),
				'intro' => __( 'Mise en page, animations et blocs de la page d’accueil.', 'famma-core' ),
				'view'  => __( 'Voir l’accueil', 'famma-core' ),
			),
			'shop'    => array(
				'title' => __( 'Boutique', 'famma-core' ),
				'intro' => __( 'La boutique et les rayons.', 'famma-core' ),
				'view'  => __( 'Voir la boutique', 'famma-core' ),
			),
			'product' => array(
				'title' => __( 'Fiche produit', 'famma-core' ),
				'intro' => __( 'Les mêmes réglages et les mêmes textes valent pour toutes les fiches produit.', 'famma-core' ),
				'view'  => __( 'Voir une fiche', 'famma-core' ),
			),
			'cart'    => array(
				'title' => __( 'Panier', 'famma-core' ),
				'intro' => __( 'La page panier. Ajouter un article au panier pour la voir.', 'famma-core' ),
				'view'  => __( 'Voir le panier', 'famma-core' ),
			),
			'account' => array(
				'title' => __( 'Mon compte', 'famma-core' ),
				'intro' => __( 'L’espace client. « Présentation de la maquette » porte la plupart des autres réglages.', 'famma-core' ),
				'view'  => __( 'Voir Mon compte', 'famma-core' ),
			),
			default   => array(
				'title' => __( 'Partout', 'famma-core' ),
				'intro' => __( 'Ce qui touche toutes les pages : couleurs, police, animations des cartes produits, en-tête du téléphone.', 'famma-core' ),
				'view'  => __( 'Voir le site', 'famma-core' ),
			),
		};
	}

	/**
	 * Adresse publique d'une page, pour le lien « Voir ».
	 *
	 * @param string $page Clé de page du registre.
	 * @return string Chaîne vide si la page n'existe pas (aucun produit publié…).
	 */
	public static function view_url( string $page ): string {
		$url = '';

		if ( 'shop' === $page && function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'shop' );
		} elseif ( 'account' === $page && function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'myaccount' );
		} elseif ( 'product' === $page && function_exists( 'wc_get_products' ) ) {
			$ids = wc_get_products(
				array(
					'status'  => 'publish',
					'limit'   => 1,
					'orderby' => 'date',
					'order'   => 'DESC',
					'return'  => 'ids',
				)
			);
			$url = is_array( $ids ) && ! empty( $ids ) ? (string) get_permalink( (int) $ids[0] ) : '';
		} elseif ( 'cart' === $page && function_exists( 'wc_get_cart_url' ) ) {
			$url = wc_get_cart_url();
		} elseif ( ! in_array( $page, array( 'shop', 'account', 'product', 'cart' ), true ) ) {
			$url = home_url( '/' );
		}

		return (string) $url;
	}

	/**
	 * Chaînes du script de l'écran.
	 *
	 * @return array<string, mixed>
	 */
	public static function script_strings(): array {
		return array(
			'states'  => self::labels(),
			'summary' => self::summary_labels(),
			'dirty'   => __( 'Modifications non enregistrées', 'famma-core' ),
			'clean'   => __( 'Aucune modification', 'famma-core' ),
			'noMatch' => __( 'Aucun réglage de cet onglet ne correspond. Les onglets marqués d’un nombre en ont.', 'famma-core' ),
			/* translators: %d: nombre de réglages trouvés. */
			'hits'    => __( '%d trouvés', 'famma-core' ),
		);
	}
}
