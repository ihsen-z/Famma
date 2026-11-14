<?php
/**
 * Fiche produit, lot 46 : onglet « Fiche FAMMA » de Produits → Modifier.
 *
 * Le propriétaire y écrit, produit par produit et en français comme en
 * derja, le récit de la bande marine et la liste « Ce qu'il y a dedans », et
 * place les repères en cliquant sur la photo. Chargé seulement dans
 * l'administration ; lecture côté site : `Product_Story`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Onglet de saisie du récit et de l'anatomie.
 */
final class Product_Story_Admin {

	/**
	 * Instance partagée.
	 *
	 * @var Product_Story_Admin|null
	 */
	private static ?Product_Story_Admin $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Product_Story_Admin
	 */
	public static function instance(): Product_Story_Admin {
		if ( null === self::$i ) {
			self::$i = new self();
			add_filter( 'woocommerce_product_data_tabs', array( self::$i, 'add_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( self::$i, 'render_panel' ) );
			add_action( 'woocommerce_admin_process_product_object', array( self::$i, 'save' ) );
			add_action( 'admin_enqueue_scripts', array( self::$i, 'assets' ) );
		}
		return self::$i;
	}

	/**
	 * Ajoute l'onglet aux données produit.
	 *
	 * @param array<string, array<string, mixed>> $tabs Onglets existants.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_tab( array $tabs ): array {
		$tabs['famma_story'] = array(
			'label'    => __( 'Fiche FAMMA', 'famma-core' ),
			'target'   => 'famma_story_panel',
			'class'    => array(),
			'priority' => 76,
		);
		return $tabs;
	}

	/**
	 * Script et feuille de l'onglet, sur l'écran d'un produit seulement.
	 *
	 * @param string $hook Écran de l'administration.
	 * @return void
	 */
	public function assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		$dir = plugin_dir_path( FAMMA_CORE_FILE ) . 'assets/';
		$uri = plugin_dir_url( FAMMA_CORE_FILE ) . 'assets/';

		wp_enqueue_style( 'famma-product-story-admin', $uri . 'css/product-story-admin.css', array(), (string) filemtime( $dir . 'css/product-story-admin.css' ) );
		wp_enqueue_script(
			'famma-product-story-admin',
			$uri . 'js/product-story-admin.js',
			array(),
			(string) filemtime( $dir . 'js/product-story-admin.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Contenu de l'onglet.
	 *
	 * @return void
	 */
	public function render_panel(): void {
		global $product_object;

		$product = $product_object instanceof \WC_Product ? $product_object : null;
		$meta    = static function ( string $key ) use ( $product ): string {
			return $product ? (string) $product->get_meta( $key ) : '';
		};
		$langs   = array(
			'fr' => __( 'français', 'famma-core' ),
			'ar' => __( 'derja', 'famma-core' ),
		);

		echo '<div id="famma_story_panel" class="panel woocommerce_options_panel hidden">';

		echo '<div class="options_group"><p class="famma-story-intro">' . esc_html__( 'Récit en bande marine et « Ce qu’il y a dedans », pour ce produit seulement. Ils s’affichent quand les briques « Récit » et « Ce qu’il y a dedans » sont cochées dans FAMMA → Nouveau look. Un bloc vide ne s’affiche pas. Si une langue est vide, l’autre est montrée.', 'famma-core' ) . '</p></div>';

		echo '<div class="options_group"><h4 class="famma-story-h">' . esc_html__( 'Récit', 'famma-core' ) . '</h4>';

		foreach ( $langs as $lang => $name ) {
			woocommerce_wp_textarea_input(
				array(
					'id'          => Product_Story::META . 'text_' . $lang,
					'value'       => $meta( Product_Story::META . 'text_' . $lang ),
					/* translators: %s: language name. */
					'label'       => sprintf( __( 'Récit (%s)', 'famma-core' ), $name ),
					'rows'        => 4,
					'desc_tip'    => false,
					'description' => __( 'Deux ou trois phrases, souvent le début de la description. Mettez la phrase clé entre deux * : elle passe en orange. Les mots s’allument au défilement.', 'famma-core' ),
				)
			);
			woocommerce_wp_textarea_input(
				array(
					'id'          => Product_Story::META . 'chips_' . $lang,
					'value'       => $meta( Product_Story::META . 'chips_' . $lang ),
					/* translators: %s: language name. */
					'label'       => sprintf( __( 'Situations (%s)', 'famma-core' ), $name ),
					'rows'        => 5,
					'placeholder' => 'monitor | ' . __( 'Devant le PC', 'famma-core' ) . ' | ' . __( 'de longues heures devant le PC', 'famma-core' ),
					'desc_tip'    => false,
					'description' => __( 'Un bouton par ligne : icône | texte du bouton | phrase (facultative). Icônes : monitor (écran), mobile (téléphone), car (voiture), book (lecture), home (maison), ou - pour aucune. 6 au plus. Vide : pas de boutons.', 'famma-core' ),
				)
			);
			woocommerce_wp_text_input(
				array(
					'id'          => Product_Story::META . 'start_' . $lang,
					'value'       => $meta( Product_Story::META . 'start_' . $lang ),
					/* translators: %s: language name. */
					'label'       => sprintf( __( 'Phrase sous les boutons (%s)', 'famma-core' ), $name ),
					'desc_tip'    => false,
					'description' => __( 'Affichée avant qu’un bouton soit touché. Facultative.', 'famma-core' ),
				)
			);
			woocommerce_wp_text_input(
				array(
					'id'          => Product_Story::META . 'pick_' . $lang,
					'value'       => $meta( Product_Story::META . 'pick_' . $lang ),
					/* translators: %s: language name. */
					'label'       => sprintf( __( 'Phrase après un choix (%s)', 'famma-core' ), $name ),
					'desc_tip'    => false,
					'description' => __( 'Remplace la précédente quand un bouton est touché ; {situation} devient la phrase du bouton, en orange. Vide : seule la phrase du bouton s’affiche.', 'famma-core' ),
				)
			);
		}

		echo '</div><div class="options_group"><h4 class="famma-story-h">' . esc_html__( 'Ce qu’il y a dedans', 'famma-core' ) . '</h4>';

		$this->render_anatomy( $product, $meta );

		foreach ( $langs as $lang => $name ) {
			woocommerce_wp_textarea_input(
				array(
					'id'          => Product_Story::META . 'parts_' . $lang,
					'value'       => $meta( Product_Story::META . 'parts_' . $lang ),
					/* translators: %s: language name. */
					'label'       => sprintf( __( 'Pièces (%s)', 'famma-core' ), $name ),
					'rows'        => 4,
					'desc_tip'    => false,
					'description' => __( 'Une pièce par ligne, dans l’ordre des repères (ligne 1 = repère 1). Il faut au moins deux pièces placées.', 'famma-core' ),
				)
			);
		}

		echo '</div>';

		/**
		 * Fin de l'onglet « Fiche FAMMA » : d'autres champs du produit s'y ajoutent (lien « autre taille », lot 49).
		 *
		 * @param \WC_Product|null $product Produit édité.
		 */
		do_action( 'famma_story_panel_end', $product );

		echo '</div>';
	}

	/**
	 * Choix de la photo et pose des repères au clic.
	 *
	 * @param \WC_Product|null $product Produit édité.
	 * @param callable         $meta    Lecture d'une méta.
	 * @return void
	 */
	private function render_anatomy( ?\WC_Product $product, callable $meta ): void {
		$images  = $product ? Product_Story::images( $product ) : array();
		$current = (int) $meta( Product_Story::META_IMAGE );

		echo '<p class="form-field"><label for="' . esc_attr( Product_Story::META_IMAGE ) . '">' . esc_html__( 'Photo', 'famma-core' ) . '</label>';

		if ( array() === $images ) {
			echo '<span class="description">' . esc_html__( 'Ajoutez d’abord des photos au produit, puis enregistrez : elles apparaîtront ici.', 'famma-core' ) . '</span></p>';
			return;
		}

		echo '<select id="' . esc_attr( Product_Story::META_IMAGE ) . '" name="' . esc_attr( Product_Story::META_IMAGE ) . '" class="famma-story-image">';
		echo '<option value="">' . esc_html__( '— Pas d’anatomie —', 'famma-core' ) . '</option>';

		foreach ( $images as $n => $id ) {
			$src = (string) wp_get_attachment_image_url( $id, 'medium_large' );
			/* translators: %d: photo number. */
			$name = 0 === $n ? __( 'Photo principale', 'famma-core' ) : sprintf( __( 'Photo %d de la galerie', 'famma-core' ), $n );
			echo '<option value="' . esc_attr( (string) $id ) . '" data-src="' . esc_url( $src ) . '"' . selected( $current, $id, false ) . '>' . esc_html( $name ) . '</option>';
		}

		echo '</select></p>';

		echo '<div class="famma-story-stage" hidden><img alt="" /><ol class="famma-story-dots" aria-hidden="true"></ol></div>';
		echo '<p class="form-field famma-story-tools"><button type="button" class="button famma-story-clear">' . esc_html__( 'Effacer les repères', 'famma-core' ) . '</button> <span class="description">' . esc_html__( 'Cliquez sur la photo pour poser le repère suivant.', 'famma-core' ) . '</span></p>';

		woocommerce_wp_textarea_input(
			array(
				'id'          => Product_Story::META_SPOTS,
				'value'       => $meta( Product_Story::META_SPOTS ),
				'label'       => __( 'Place des repères', 'famma-core' ),
				'rows'        => 4,
				'class'       => 'famma-story-spots',
				'desc_tip'    => false,
				'description' => __( 'Rempli par les clics : « gauche, haut » en pourcentage de la photo, un repère par ligne.', 'famma-core' ),
			)
		);
	}

	/**
	 * Enregistre les champs (nonce, capacité, assainissement).
	 *
	 * @param \WC_Product $product Produit enregistré.
	 * @return void
	 */
	public function save( \WC_Product $product ): void {
		$nonce = isset( $_POST['woocommerce_meta_nonce'] ) ? sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) || ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		foreach ( array( 'fr', 'ar' ) as $lang ) {
			foreach ( Product_Story::TEXT_FIELDS as $field ) {
				$key = Product_Story::META . $field . '_' . $lang;

				if ( ! isset( $_POST[ $key ] ) ) {
					continue;
				}

				$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- assaini à la ligne suivante.
				$product->update_meta_data( $key, in_array( $field, array( 'start', 'pick' ), true ) ? sanitize_text_field( $raw ) : sanitize_textarea_field( $raw ) );
			}
		}

		if ( isset( $_POST[ Product_Story::META_IMAGE ] ) ) {
			$image = absint( wp_unslash( $_POST[ Product_Story::META_IMAGE ] ) );
			$product->update_meta_data( Product_Story::META_IMAGE, in_array( $image, Product_Story::images( $product ), true ) ? (string) $image : '' );
		}

		if ( isset( $_POST[ Product_Story::META_SPOTS ] ) ) {
			$spots = Product_Story::spots( sanitize_textarea_field( wp_unslash( $_POST[ Product_Story::META_SPOTS ] ) ) );
			$lines = array_map(
				static function ( array $s ): string {
					return $s[0] . ', ' . $s[1];
				},
				$spots
			);
			$product->update_meta_data( Product_Story::META_SPOTS, implode( "\n", $lines ) );
		}
	}
}
