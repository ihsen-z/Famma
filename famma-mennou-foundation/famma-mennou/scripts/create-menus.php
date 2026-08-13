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

	// Le menu principal sert aussi le menu mobile : une boutique COD
	// mobile-first n'a aucune raison d'avoir deux navigations divergentes.
	$build(
		'Principal',
		array( 'front', 'a-propos', 'faq', 'contact' ),
		array( 'primary', 'mobile' )
	);

	$build(
		'Pied de page',
		array( 'livraison', 'retours', 'conditions-generales', 'confidentialite', 'cookies' ),
		array( 'footer' )
	);

	echo "  La boutique n'est pas au menu : le catalogue est vide (§58).\n";
	echo "  L'ajouter des la mise en ligne du premier produit reel.\n";
} )();
