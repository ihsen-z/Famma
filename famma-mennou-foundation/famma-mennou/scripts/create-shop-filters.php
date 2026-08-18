<?php
/**
 * Remplit la colonne de filtres de la boutique avec les widgets WooCommerce.
 *
 * Idempotent : relançable sans effet de bord. Le script ne pose un widget que
 * s'il n'est pas déjà présent dans la barre latérale, et n'écrase jamais un
 * réglage fait à la main par le propriétaire.
 *
 * Les filtres sont ceux de WooCommerce, pas des filtres maison : c'est
 * WooCommerce qui possède la requête, la pagination et le comptage. Un filtre
 * réécrit à la main serait un filtre à traduire, à maintenir et à déboguer,
 * pour un résultat moins bon.
 *
 * Le corps est enfermé dans une closure : dans un script exécuté par WP-CLI,
 * toute variable de premier niveau est une variable globale.
 *
 * Usage : docker compose exec wpcli wp eval-file /scripts/create-shop-filters.php
 *
 * @package Famma\Local
 */

defined( 'ABSPATH' ) || exit;

( static function (): void {

	$sidebar_id = 'famma-shop-filters';

	if ( ! function_exists( 'WC' ) ) {
		echo "WooCommerce inactif : rien a faire.\n";
		return;
	}

	/*
	 * Ordre exact de la maquette. Les cinq widgets viennent du theme
	 * (inc/shop-widgets.php) : les widgets natifs de WooCommerce produisent
	 * chacun un balisage different — liste hierarchique, curseur jQuery UI a
	 * deux poignees — et les ramener au dessin demanderait plus de CSS de
	 * correction que de code de rendu. Aucun ne reimplemente de requete.
	 */
	$widgets = array(
		'famma_category_filter' => array( 'title' => 'Categories' ),
		'famma_price_filter'    => array( 'title' => 'Prix' ),
		'famma_stock_filter'    => array( 'title' => 'Disponibilite' ),
		'famma_rating_filter'   => array( 'title' => 'Note moyenne' ),
		'famma_sale_filter'     => array( 'title' => 'Promotions' ),
	);

	if ( taxonomy_exists( 'product_brand' ) ) {
		$widgets['woocommerce_brand_nav'] = array(
			'title'        => 'Marque',
			'orderby'      => 'name',
			'count'        => 1,
			'hierarchical' => 0,
			'dropdown'     => 0,
		);
	}

	$sidebars = get_option( 'sidebars_widgets', array() );
	$assigned = isset( $sidebars[ $sidebar_id ] ) && is_array( $sidebars[ $sidebar_id ] )
		? $sidebars[ $sidebar_id ]
		: array();

	echo "Filtres boutique :\n";

	foreach ( $widgets as $base => $settings ) {

		// Deja pose ? On ne touche a rien.
		$present = false;
		foreach ( $assigned as $widget_id ) {
			if ( 0 === strpos( (string) $widget_id, $base . '-' ) ) {
				$present = true;
				break;
			}
		}

		if ( $present ) {
			echo '  ' . str_pad( $base, 34 ) . "deja present, inchange\n";
			continue;
		}

		$instances = get_option( 'widget_' . $base, array() );
		if ( ! is_array( $instances ) ) {
			$instances = array();
		}

		// Le prochain indice libre. `_multiwidget` n'est pas une instance.
		$next = 1;
		foreach ( array_keys( $instances ) as $key ) {
			if ( is_numeric( $key ) && (int) $key >= $next ) {
				$next = (int) $key + 1;
			}
		}

		$instances[ $next ]        = $settings;
		$instances['_multiwidget'] = 1;

		update_option( 'widget_' . $base, $instances );

		$assigned[] = $base . '-' . $next;

		echo '  ' . str_pad( $base, 34 ) . "ajoute\n";
	}

	$sidebars[ $sidebar_id ] = $assigned;
	update_option( 'sidebars_widgets', $sidebars );

	echo '  ' . count( $assigned ) . " widget(s) dans la colonne de filtres.\n";
	echo "  Le proprietaire peut les reordonner ou en ajouter depuis Apparence > Widgets.\n";
} )();
