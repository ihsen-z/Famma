<?php
/**
 * Réglage des tailles d'un produit, dans l'administration (voir `Product_Sizes`).
 *
 * Onglet « Général » des données produit : « Tailles proposées » et
 * « Intitulé du choix de taille ». Chargé seulement dans l'administration.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Champs produit « Tailles proposées » et « Intitulé du choix de taille ».
 */
final class Product_Sizes_Admin {

	/**
	 * Instance partagée.
	 *
	 * @var Product_Sizes_Admin|null
	 */
	private static ?Product_Sizes_Admin $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Product_Sizes_Admin
	 */
	public static function instance(): Product_Sizes_Admin {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'woocommerce_product_options_general_product_data', array( self::$i, 'render_admin_fields' ) );
			add_action( 'woocommerce_admin_process_product_object', array( self::$i, 'save_admin_fields' ) );
		}
		return self::$i;
	}

	/**
	 * Champs de l'onglet « Général » de la fiche produit (administration).
	 *
	 * @return void
	 */
	public function render_admin_fields(): void {
		global $product_object;

		$options = '';
		$label   = '';

		if ( $product_object instanceof \WC_Product ) {
			$options = (string) $product_object->get_meta( Product_Sizes::META );
			$label   = (string) $product_object->get_meta( Product_Sizes::META_LABEL );
		}

		echo '<div class="options_group show_if_simple">';

		woocommerce_wp_text_input(
			array(
				'id'          => Product_Sizes::META,
				'value'       => $options,
				'label'       => __( 'Tailles proposées', 'famma-core' ),
				'placeholder' => 'S, M',
				'desc_tip'    => false,
				'description' => __( 'Tailles séparées par une virgule, par exemple « S, M ». Le client devra en choisir une pour commander. Laissez vide : aucun choix de taille.', 'famma-core' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => Product_Sizes::META_LABEL,
				'value'       => $label,
				'label'       => __( 'Intitulé du choix de taille', 'famma-core' ),
				'placeholder' => __( 'Taille / المقاس', 'famma-core' ),
				'desc_tip'    => false,
				'description' => __( 'Facultatif. Laissez vide pour afficher « Taille / المقاس ».', 'famma-core' ),
			)
		);

		echo '</div>';
	}

	/**
	 * Enregistre les deux champs (nonce, capacité, assainissement).
	 *
	 * @param \WC_Product $product Produit enregistré.
	 * @return void
	 */
	public function save_admin_fields( \WC_Product $product ): void {
		$nonce = isset( $_POST['woocommerce_meta_nonce'] )
			? sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		if ( isset( $_POST[ Product_Sizes::META ] ) ) {
			$sizes = Product_Sizes::parse( sanitize_text_field( wp_unslash( $_POST[ Product_Sizes::META ] ) ) );
			$product->update_meta_data( Product_Sizes::META, implode( ', ', $sizes ) );
		}

		if ( isset( $_POST[ Product_Sizes::META_LABEL ] ) ) {
			$product->update_meta_data( Product_Sizes::META_LABEL, sanitize_text_field( wp_unslash( $_POST[ Product_Sizes::META_LABEL ] ) ) );
		}
	}
}
