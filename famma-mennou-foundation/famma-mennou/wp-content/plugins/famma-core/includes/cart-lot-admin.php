<?php
/**
 * Panier, étape 4 : champs du lot dans l'onglet « Fiche FAMMA » du produit.
 *
 * Posés en bas de l'onglet (action `famma_story_panel_end`), comme le lien
 * « autre taille » du lot 49. Chargé seulement dans l'administration ;
 * lecture et échange côté site : `Cart_Lot`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Saisie du couple produit → lot.
 */
final class Cart_Lot_Admin {

	/**
	 * Instance partagée.
	 *
	 * @var Cart_Lot_Admin|null
	 */
	private static ?Cart_Lot_Admin $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Cart_Lot_Admin
	 */
	public static function instance(): Cart_Lot_Admin {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'famma_story_panel_end', array( self::$i, 'render' ), 20 );
			add_action( 'woocommerce_admin_process_product_object', array( self::$i, 'save' ) );
		}
		return self::$i;
	}

	/**
	 * Champs : produit lot, nombre d'articles, phrase en français et en derja.
	 *
	 * @param \WC_Product|null $product Produit édité.
	 * @return void
	 */
	public function render( ?\WC_Product $product ): void {
		$linked = $product ? (int) $product->get_meta( Cart_Lot::META_PRODUCT ) : 0;
		$size   = $product ? (int) $product->get_meta( Cart_Lot::META_SIZE ) : 0;
		$lot    = $linked > 0 ? wc_get_product( $linked ) : null;

		echo '<div class="options_group"><h4 class="famma-story-h">' . esc_html__( 'Lot dans le panier', 'famma-core' ) . '</h4>';
		echo '<p class="famma-story-intro">' . esc_html__( 'Quand le panier contient assez d’articles de ce produit, la ligne propose de les remplacer par le lot (par exemple : deux paires de chaussettes, ou le lot de deux). Le lot doit exister dans le catalogue, à son vrai prix. Il s’affiche quand la brique « Passer au lot » est cochée dans FAMMA → Nouveau look → Panier, et seulement si le lot coûte moins cher.', 'famma-core' ) . '</p>';

		echo '<p class="form-field"><label for="' . esc_attr( Cart_Lot::META_PRODUCT ) . '">' . esc_html__( 'Produit lot', 'famma-core' ) . '</label>';
		printf(
			'<select class="wc-product-search" style="width: 50%%;" id="%1$s" name="%1$s" data-placeholder="%2$s" data-action="woocommerce_json_search_products" data-allow_clear="true" data-exclude="%3$s">',
			esc_attr( Cart_Lot::META_PRODUCT ),
			esc_attr__( 'Chercher un produit…', 'famma-core' ),
			esc_attr( (string) ( $product ? $product->get_id() : 0 ) )
		);

		if ( $lot instanceof \WC_Product ) {
			echo '<option value="' . esc_attr( (string) $lot->get_id() ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $lot->get_formatted_name() ) ) . '</option>';
		}

		echo '</select></p>';

		$warn = $this->warning( $product, $lot, $size );

		if ( '' !== $warn ) {
			echo '<p class="form-field famma-story-warn"><span class="description">' . esc_html( $warn ) . '</span></p>';
		}

		woocommerce_wp_text_input(
			array(
				'id'                => Cart_Lot::META_SIZE,
				'value'             => $size >= 2 ? (string) $size : '',
				'label'             => __( 'Articles dans le lot', 'famma-core' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '2',
					'step' => '1',
				),
				'desc_tip'          => false,
				'description'       => __( 'Combien d’articles de ce produit le lot remplace (2 ou plus). C’est aussi le seuil : la proposition apparaît à partir de ce nombre.', 'famma-core' ),
			)
		);

		// Témoin : les champs étaient bien dans le formulaire.
		echo '<input type="hidden" name="famma_lot_shown" value="1" />';

		$langs = array(
			'fr' => __( 'français', 'famma-core' ),
			'ar' => __( 'derja', 'famma-core' ),
		);

		foreach ( $langs as $lang => $name ) {
			woocommerce_wp_text_input(
				array(
					'id'          => Cart_Lot::META_TEXT . $lang,
					'value'       => $product ? (string) $product->get_meta( Cart_Lot::META_TEXT . $lang ) : '',
					/* translators: %s: language name. */
					'label'       => sprintf( __( 'Phrase du lot (%s)', 'famma-core' ), $name ),
					'desc_tip'    => false,
					'description' => __( 'Facultatif, par exemple « Deux paires ? Le lot de 2 coûte {a} au lieu de {b}. » : {n} = articles du lot, {a} = prix du lot, {b} = prix des articles seuls. Vide : la phrase de l’onglet « Panier ».', 'famma-core' ),
				)
			);
		}

		echo '</div>';
	}

	/**
	 * Avertissement sous le produit lot, ou chaîne vide.
	 *
	 * @param \WC_Product|null $product Produit édité.
	 * @param \WC_Product|null $lot     Produit lot.
	 * @param int              $size    Articles dans le lot.
	 * @return string
	 */
	private function warning( ?\WC_Product $product, ?\WC_Product $lot, int $size ): string {
		if ( ! $lot instanceof \WC_Product ) {
			return '';
		}

		if ( ! Cart_Lot::usable( $lot ) ) {
			return __( 'Ce lot n’est pas proposé : il doit être un produit simple (sans tailles), publié, avec un prix et en stock.', 'famma-core' );
		}

		if ( $size < 2 ) {
			return __( 'Indiquez combien d’articles le lot contient : sans ce nombre, rien n’est proposé.', 'famma-core' );
		}

		// Produit à tailles : le prix dépend de la taille, comparé dans le panier seulement.
		if ( $product && $product->is_type( 'simple' ) && (float) wc_get_price_to_display( $lot ) >= (float) wc_get_price_to_display( $product ) * $size ) {
			return __( 'Le lot ne coûte pas moins cher que les articles seuls : il ne sera pas proposé.', 'famma-core' );
		}

		return '';
	}

	/**
	 * Enregistre les champs (nonce, capacité, assainissement).
	 *
	 * @param \WC_Product $product Produit enregistré.
	 * @return void
	 */
	public function save( \WC_Product $product ): void {
		$nonce = isset( $_POST['woocommerce_meta_nonce'] ) ? sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) || ! current_user_can( 'edit_product', $product->get_id() ) || ! isset( $_POST['famma_lot_shown'] ) ) {
			return;
		}

		// Champ vidé (croix de la recherche) : la clé n'est pas envoyée, le lot est retiré.
		$id   = isset( $_POST[ Cart_Lot::META_PRODUCT ] ) ? absint( wp_unslash( $_POST[ Cart_Lot::META_PRODUCT ] ) ) : 0;
		$lot  = $id > 0 && $id !== $product->get_id() ? wc_get_product( $id ) : null;
		$size = isset( $_POST[ Cart_Lot::META_SIZE ] ) ? absint( wp_unslash( $_POST[ Cart_Lot::META_SIZE ] ) ) : 0;

		$values = array(
			Cart_Lot::META_PRODUCT => $lot instanceof \WC_Product ? (string) $lot->get_id() : '',
			Cart_Lot::META_SIZE    => $size >= 2 ? (string) $size : '',
		);

		foreach ( array( 'fr', 'ar' ) as $lang ) {
			$key = Cart_Lot::META_TEXT . $lang;

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
