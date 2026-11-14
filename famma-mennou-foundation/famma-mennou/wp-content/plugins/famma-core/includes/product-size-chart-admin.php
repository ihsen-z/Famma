<?php
/**
 * Fiche produit, lot 50 : saisie du tableau des mesures.
 *
 * Posée en bas de l'onglet « Fiche FAMMA » (action `famma_story_panel_end`
 * de `Product_Story_Admin`), après le bloc « Autre taille ». Chargé seulement
 * dans l'administration ; lecture côté site : `Product_Size_Chart`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Saisie du tableau des mesures.
 */
final class Product_Size_Chart_Admin {

	/**
	 * Instance partagée.
	 *
	 * @var Product_Size_Chart_Admin|null
	 */
	private static ?Product_Size_Chart_Admin $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Product_Size_Chart_Admin
	 */
	public static function instance(): Product_Size_Chart_Admin {
		if ( null === self::$i ) {
			self::$i = new self();
			// Après le bloc « Autre taille » (priorité 10).
			add_action( 'famma_story_panel_end', array( self::$i, 'render' ), 20 );
			add_action( 'woocommerce_admin_process_product_object', array( self::$i, 'save' ) );
		}
		return self::$i;
	}

	/**
	 * Champs : tableau en français et en derja.
	 *
	 * @param \WC_Product|null $product Produit édité.
	 * @return void
	 */
	public function render( ?\WC_Product $product ): void {
		echo '<div class="options_group"><h4 class="famma-story-h">' . esc_html__( 'Tableau des mesures', 'famma-core' ) . '</h4>';
		echo '<p class="famma-story-intro">' . esc_html__( 'Affiché dans le volet « Les tailles » quand la brique « Volet Les tailles » est cochée dans FAMMA → Nouveau look. Une ligne par rangée, colonnes séparées par | ; la première ligne donne les titres des colonnes. Mesures réelles du fournisseur seulement. Vide : pas de tableau.', 'famma-core' ) . '</p>';

		// Témoin : les champs étaient bien dans le formulaire (sinon rien n'est touché à l'enregistrement).
		echo '<input type="hidden" name="famma_size_chart_shown" value="1" />';

		$langs = array(
			'fr' => __( 'français', 'famma-core' ),
			'ar' => __( 'derja', 'famma-core' ),
		);

		foreach ( $langs as $lang => $name ) {
			woocommerce_wp_textarea_input(
				array(
					'id'          => Product_Size_Chart::META . $lang,
					'value'       => $product ? (string) $product->get_meta( Product_Size_Chart::META . $lang ) : '',
					/* translators: %s: language name. */
					'label'       => sprintf( __( 'Tableau (%s)', 'famma-core' ), $name ),
					'rows'        => 6,
					'placeholder' => __( 'Taille | Tour de taille (cm)', 'famma-core' ) . "\n" . 'L | …',
					'desc_tip'    => false,
					'description' => __( 'Si une langue est vide, l’autre est montrée. 14 lignes et 6 colonnes au plus.', 'famma-core' ),
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

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) || ! current_user_can( 'edit_product', $product->get_id() ) || ! isset( $_POST['famma_size_chart_shown'] ) ) {
			return;
		}

		foreach ( array( 'fr', 'ar' ) as $lang ) {
			$key = Product_Size_Chart::META . $lang;

			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			$value = trim( sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) );

			// Vide : méta retirée, pour ne pas écrire de lignes vides sur chaque produit.
			if ( '' === $value ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		}
	}
}
