<?php
/**
 * Fiche produit, lot 49 : champs du lien « autre taille ».
 *
 * Posés en bas de l'onglet « Fiche FAMMA » (action `famma_story_panel_end`
 * de `Product_Story_Admin`). Le produit lié se choisit avec la recherche de
 * produits de WooCommerce (`wc-product-search`, déjà chargée sur l'écran d'un
 * produit). Chargé seulement dans l'administration ; lecture côté site :
 * `Product_Alt_Size`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Saisie du lien « autre taille ».
 */
final class Product_Alt_Size_Admin {

	/**
	 * Instance partagée.
	 *
	 * @var Product_Alt_Size_Admin|null
	 */
	private static ?Product_Alt_Size_Admin $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Product_Alt_Size_Admin
	 */
	public static function instance(): Product_Alt_Size_Admin {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'famma_story_panel_end', array( self::$i, 'render' ) );
			add_action( 'woocommerce_admin_process_product_object', array( self::$i, 'save' ) );
		}
		return self::$i;
	}

	/**
	 * Champs : produit lié, phrase en français et en derja.
	 *
	 * @param \WC_Product|null $product Produit édité.
	 * @return void
	 */
	public function render( ?\WC_Product $product ): void {
		$linked = $product ? (int) $product->get_meta( Product_Alt_Size::META_PRODUCT ) : 0;
		$target = $linked > 0 ? wc_get_product( $linked ) : null;

		echo '<div class="options_group"><h4 class="famma-story-h">' . esc_html__( 'Autre taille', 'famma-core' ) . '</h4>';
		echo '<p class="famma-story-intro">' . esc_html__( 'Lien sous les tailles vers le produit à proposer quand la taille ne convient pas (par exemple : « Vous portez S ou M ? Voir la Ceinture dorsale Pro »). Il s’affiche quand la brique « Lien autre taille » est cochée dans FAMMA → Nouveau look. Sans produit ou sans phrase, pas de lien.', 'famma-core' ) . '</p>';

		echo '<p class="form-field"><label for="' . esc_attr( Product_Alt_Size::META_PRODUCT ) . '">' . esc_html__( 'Produit proposé', 'famma-core' ) . '</label>';
		printf(
			'<select class="wc-product-search" style="width: 50%%;" id="%1$s" name="%1$s" data-placeholder="%2$s" data-action="woocommerce_json_search_products" data-allow_clear="true" data-exclude="%3$s">',
			esc_attr( Product_Alt_Size::META_PRODUCT ),
			esc_attr__( 'Chercher un produit…', 'famma-core' ),
			esc_attr( (string) ( $product ? $product->get_id() : 0 ) )
		);

		if ( $target instanceof \WC_Product ) {
			echo '<option value="' . esc_attr( (string) $target->get_id() ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $target->get_formatted_name() ) ) . '</option>';
		}

		echo '</select></p>';

		if ( $target instanceof \WC_Product && ! Product_Alt_Size::usable( $target ) ) {
			echo '<p class="form-field famma-story-warn"><span class="description">' . esc_html__( 'Ce produit n’est pas affiché : il doit être publié, visible dans la boutique et en stock.', 'famma-core' ) . '</span></p>';
		}

		// Témoin : les champs étaient bien dans le formulaire (sinon rien n'est touché à l'enregistrement).
		echo '<input type="hidden" name="famma_alt_size_shown" value="1" />';

		$langs = array(
			'fr' => __( 'français', 'famma-core' ),
			'ar' => __( 'derja', 'famma-core' ),
		);

		foreach ( $langs as $lang => $name ) {
			woocommerce_wp_text_input(
				array(
					'id'          => Product_Alt_Size::META_TEXT . $lang,
					'value'       => $product ? (string) $product->get_meta( Product_Alt_Size::META_TEXT . $lang ) : '',
					/* translators: %s: language name. */
					'label'       => sprintf( __( 'Phrase du lien (%s)', 'famma-core' ), $name ),
					'desc_tip'    => false,
					'description' => __( 'Si une langue est vide, l’autre est montrée.', 'famma-core' ),
				)
			);
		}

		echo '</div>';
	}

	/**
	 * Enregistre les champs (nonce, capacité, assainissement).
	 *
	 * @param \WC_Product $product Produit enregistré.
	 * @return void
	 */
	public function save( \WC_Product $product ): void {
		$nonce = isset( $_POST['woocommerce_meta_nonce'] ) ? sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) || ! current_user_can( 'edit_product', $product->get_id() ) || ! isset( $_POST['famma_alt_size_shown'] ) ) {
			return;
		}

		// Champ vidé (croix de la recherche) : la clé n'est pas envoyée, le lien est retiré.
		$id     = isset( $_POST[ Product_Alt_Size::META_PRODUCT ] ) ? absint( wp_unslash( $_POST[ Product_Alt_Size::META_PRODUCT ] ) ) : 0;
		$target = $id > 0 && $id !== $product->get_id() ? wc_get_product( $id ) : null;

		$values = array( Product_Alt_Size::META_PRODUCT => $target instanceof \WC_Product ? (string) $target->get_id() : '' );

		foreach ( array( 'fr', 'ar' ) as $lang ) {
			$key = Product_Alt_Size::META_TEXT . $lang;

			if ( isset( $_POST[ $key ] ) ) {
				$values[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}

		// Valeur vide : méta retirée, pour ne pas écrire de lignes vides sur chaque produit.
		foreach ( $values as $key => $value ) {
			if ( '' === $value ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		}
	}
}
