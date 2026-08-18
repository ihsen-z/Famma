<?php
/**
 * Menus de navigation (§8).
 *
 * Idempotent : les menus sont vidés puis reconstruits à l'identique, ce qui
 * évite les doublons à chaque exécution sans jamais toucher aux autres menus.
 *
 * @package Famma\Local
 */

( static function (): void {

	/**
	 * Construit un menu à partir de slugs de pages et l'assigne à des emplacements.
	 *
	 * @param string   $name      Nom du menu.
	 * @param string[] $slugs     Slugs des pages, dans l'ordre voulu.
	 * @param string[] $locations Emplacements du thème à servir.
	 * @return void
	 */
	$build = static function ( string $name, array $slugs, array $locations ): void {
		$menu = wp_get_nav_menu_object( $name );
		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				echo 'ECHEC menu ' . $name . ' : ' . $menu_id->get_error_message() . "\n";
				return;
			}
		} else {
			$menu_id = $menu->term_id;
			foreach ( wp_get_nav_menu_items( $menu_id ) as $item ) {
				wp_delete_post( $item->ID, true );
			}
		}

		$added = 0;
		foreach ( $slugs as $slug ) {
			/*
			 * Une entree peut etre un slug de page, ou un lien personnalise
			 * decrit par { label, url, children } — la boutique, les
			 * promotions et les nouveautes n'ont pas de page dediee, elles
			 * pointent vers des URL reelles de WooCommerce.
			 */
			if ( is_array( $slug ) ) {
				if ( empty( $slug['url'] ) ) {
					echo '  destination absente, entree ignoree : ' . $slug['label'] . "\n";
					continue;
				}

				$parent_id = wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => $slug['label'],
						'menu-item-url'    => $slug['url'],
						'menu-item-type'   => 'custom',
						'menu-item-status' => 'publish',
					)
				);
				++$added;

				foreach ( (array) ( $slug['children'] ?? array() ) as $child ) {
					if ( empty( $child['url'] ) || is_wp_error( $parent_id ) ) {
						continue;
					}
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => $child['label'],
							'menu-item-url'       => $child['url'],
							'menu-item-type'      => 'custom',
							'menu-item-parent-id' => (int) $parent_id,
							'menu-item-status'    => 'publish',
						)
					);
					++$added;
				}

				continue;
			}

			if ( 'front' === $slug ) {
				$page_id = (int) get_option( 'page_on_front' );
			} else {
				$page    = get_page_by_path( $slug, OBJECT, 'page' );
				$page_id = $page instanceof WP_Post ? $page->ID : 0;
			}
			if ( ! $page_id ) {
				echo '  page introuvable, ignoree : ' . $slug . "\n";
				continue;
			}
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-object-id' => $page_id,
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				)
			);
			++$added;
		}

		$assigned = get_theme_mod( 'nav_menu_locations', array() );
		foreach ( $locations as $location ) {
			$assigned[ $location ] = $menu_id;
		}
		set_theme_mod( 'nav_menu_locations', $assigned );

		echo '  ' . str_pad( $name, 24 ) . $added . ' entree(s) -> ' . implode( ', ', $locations ) . "\n";
	};

	echo "Menus :\n";

	/*
	 * Menu principal — les sept entrees de la maquette.
	 *
	 * Le menu sert aussi le menu mobile : une boutique COD mobile-first n'a
	 * aucune raison d'avoir deux navigations divergentes.
	 *
	 * Chaque entree pointe vers une destination reelle. « Categories » vise la
	 * boutique et recoit les vraies categories produit en sous-menu ;
	 * « Nouveautes » est la boutique triee par date ; « Promotions » utilise le
	 * filtre `?on_sale=1` servi par inc/shop.php. Aucun lien mort (§60).
	 */
	$shop_id  = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
	$shop_url = $shop_id > 0 ? (string) get_permalink( $shop_id ) : '';

	$primary = array( 'front' );

	if ( '' !== $shop_url ) {
		$primary[] = array(
			'label'    => 'Boutique',
			'url'      => $shop_url,
			'children' => array(),
		);

		$cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 8,
			)
		);

		$children = array();
		if ( ! is_wp_error( $cats ) ) {
			foreach ( $cats as $cat ) {
				$children[] = array(
					'label' => $cat->name,
					'url'   => (string) get_term_link( $cat ),
				);
			}
		}

		$primary[] = array(
			'label'    => 'Categories',
			'url'      => $shop_url,
			'children' => $children,
		);

		$primary[] = array(
			'label'    => 'Promotions',
			'url'      => add_query_arg( 'on_sale', '1', $shop_url ),
			'children' => array(),
		);

		$primary[] = array(
			'label'    => 'Nouveautes',
			'url'      => add_query_arg( 'orderby', 'date', $shop_url ),
			'children' => array(),
		);
	}

	$primary[] = 'a-propos';
	$primary[] = 'contact';

	$build( 'Principal', $primary, array( 'primary', 'mobile' ) );

	/*
	 * Colonne « Informations » du pied de page (emplacement famma_footer_info).
	 * Reprend les six entrees de la maquette : ce que le client cherche AVANT
	 * de commander — livraison, retours, questions frequentes — puis les
	 * textes contractuels.
	 */
	$build(
		'Pied de page — Informations',
		array( 'livraison', 'retours', 'faq', 'conditions-generales', 'confidentialite', 'cookies' ),
		array( 'famma_footer_info' )
	);

	/*
	 * Barre legale de bas de page (emplacement footer). Volontairement plus
	 * courte que la colonne ci-dessus : elle ne porte que les textes qui
	 * engagent juridiquement la boutique. « Mentions legales » n'y figure pas
	 * tant que la page n'existe pas — pas de lien mort (§60).
	 */
	$build(
		'Pied de page',
		array( 'conditions-generales', 'confidentialite', 'cookies' ),
		array( 'footer' )
	);

	echo "  Le sous-menu Categories reprend les vraies categories produit :\n";
	echo "  il grandit tout seul a mesure que le catalogue se remplit.\n";
} )();
