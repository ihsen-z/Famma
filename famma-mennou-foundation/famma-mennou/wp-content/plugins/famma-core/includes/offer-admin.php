<?php
/**
 * Offre après achat : onglet « Offre après achat » de la fiche produit.
 *
 * Le propriétaire choisit, sur le produit acheté, l'article proposé, son prix
 * spécial, le titre et le texte du bloc, et voit les chiffres des 30 derniers
 * jours. Chargé seulement dans l'administration.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Réglage et statistiques de l'offre, sur la fiche produit.
 */
final class Offer_Admin {

	/**
	 * Instance partagée.
	 *
	 * @var Offer_Admin|null
	 */
	private static ?Offer_Admin $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Offer_Admin
	 */
	public static function instance(): Offer_Admin {
		if ( null === self::$i ) {
			self::$i = new self();
			add_filter( 'woocommerce_product_data_tabs', array( self::$i, 'add_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( self::$i, 'render_panel' ) );
			add_action( 'woocommerce_admin_process_product_object', array( self::$i, 'save' ) );
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
		$tabs['famma_offer'] = array(
			'label'    => __( 'Offre après achat', 'famma-core' ),
			'target'   => 'famma_offer_panel',
			'class'    => array(),
			'priority' => 75,
		);
		return $tabs;
	}

	/**
	 * Contenu de l'onglet.
	 *
	 * @return void
	 */
	public function render_panel(): void {
		global $product_object;

		$product = $product_object instanceof \WC_Product ? $product_object : null;
		$value   = static function ( string $key ) use ( $product ): string {
			return $product ? (string) $product->get_meta( $key ) : '';
		};

		echo '<div id="famma_offer_panel" class="panel woocommerce_options_panel hidden"><div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id'          => Offer_Settings::META_ENABLED,
				'value'       => 'yes' === $value( Offer_Settings::META_ENABLED ) ? 'yes' : 'no',
				'label'       => __( 'Proposer une offre', 'famma-core' ),
				'description' => __( 'Après la commande de ce produit, la page de remerciement propose l\'article ci-dessous, ajouté à la même commande en un clic. L\'offre se ferme dès que la commande est confirmée.', 'famma-core' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => Offer_Settings::META_PRODUCT,
				'value'       => $value( Offer_Settings::META_PRODUCT ),
				'label'       => __( 'Article proposé', 'famma-core' ),
				'options'     => $this->product_choices( $product ? $product->get_id() : 0 ),
				'desc_tip'    => false,
				'description' => __( 'Produits simples publiés, sans choix de taille : l\'ajout doit tenir en un clic.', 'famma-core' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => Offer_Settings::META_PRICE,
				'value'             => $value( Offer_Settings::META_PRICE ),
				'label'             => __( 'Prix spécial (DT)', 'famma-core' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.001',
					'min'  => '0',
				),
				'desc_tip'          => false,
				'description'       => __( 'Prix de l\'article dans l\'offre. Le prix de la fiche est affiché barré à côté.', 'famma-core' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => Offer_Settings::META_TITLE,
				'value'       => $value( Offer_Settings::META_TITLE ),
				'label'       => __( 'Titre du bloc', 'famma-core' ),
				'placeholder' => __( 'Ajoutez-le à votre colis', 'famma-core' ),
				'desc_tip'    => false,
				'description' => __( 'Facultatif. Vide : titre par défaut.', 'famma-core' ),
			)
		);

		woocommerce_wp_textarea_input(
			array(
				'id'          => Offer_Settings::META_TEXT,
				'value'       => $value( Offer_Settings::META_TEXT ),
				'label'       => __( 'Texte du bloc', 'famma-core' ),
				'placeholder' => __( 'Même colis, livraison gratuite. Valable jusqu’à notre appel de confirmation.', 'famma-core' ),
				'desc_tip'    => false,
				'description' => __( 'Facultatif. Vide : texte par défaut.', 'famma-core' ),
			)
		);

		echo '</div>';

		if ( $product && $product->get_id() > 0 ) {
			$this->render_stats( $product->get_id() );
		}

		echo '</div>';
	}

	/**
	 * Chiffres des 30 derniers jours.
	 *
	 * @param int $product_id Produit déclencheur.
	 * @return void
	 */
	private function render_stats( int $product_id ): void {
		$s    = Offer_Settings::stats( $product_id );
		$rate = $s['shown'] > 0 ? round( 100 * $s['accepted'] / $s['shown'] ) : 0;

		echo '<div class="options_group"><p class="form-field"><strong>' . esc_html__( 'Résultats sur 30 jours', 'famma-core' ) . '</strong><br />';
		echo esc_html(
			sprintf(
				/* translators: 1: times shown, 2: times accepted, 3: acceptance rate, 4: times declined, 5: amount added. */
				__( 'Offre vue %1$d fois, acceptée %2$d fois (%3$d %%), refusée %4$d fois. Montant ajouté : %5$s DT.', 'famma-core' ),
				$s['shown'],
				$s['accepted'],
				$rate,
				$s['declined'],
				wc_format_decimal( $s['amount'], 3 )
			)
		);
		echo '<br /><span class="description">' . esc_html__( 'Commandes d\'essai exclues. Chiffres mis à jour toutes les 10 minutes.', 'famma-core' ) . '</span></p></div>';
	}

	/**
	 * Produits qu'on peut proposer.
	 *
	 * @param int $exclude Produit en cours d'édition.
	 * @return array<string, string>
	 */
	private function product_choices( int $exclude ): array {
		$choices = array( '' => __( '— Choisir un produit —', 'famma-core' ) );
		$ids     = wc_get_products(
			array(
				'status'  => 'publish',
				'type'    => 'simple',
				'limit'   => 200,
				'orderby' => 'title',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);

		foreach ( (array) $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p instanceof \WC_Product || $p->get_id() === $exclude || ! Offer_Settings::offerable( $p ) ) {
				continue;
			}
			$choices[ (string) $p->get_id() ] = sprintf( '%1$s (n° %2$d, %3$s DT)', $p->get_name(), $p->get_id(), wc_format_decimal( $p->get_price(), 3 ) );
		}

		return $choices;
	}

	/**
	 * Enregistre les réglages (nonce, capacité, assainissement).
	 *
	 * @param \WC_Product $product Produit enregistré.
	 * @return void
	 */
	public function save( \WC_Product $product ): void {
		$nonce = isset( $_POST['woocommerce_meta_nonce'] )
			? sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		$enabled = isset( $_POST[ Offer_Settings::META_ENABLED ] ) ? 'yes' : 'no';
		$target  = isset( $_POST[ Offer_Settings::META_PRODUCT ] ) ? absint( wp_unslash( $_POST[ Offer_Settings::META_PRODUCT ] ) ) : 0;
		$price   = isset( $_POST[ Offer_Settings::META_PRICE ] ) ? (float) wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ Offer_Settings::META_PRICE ] ) ) ) : 0.0;
		$title   = isset( $_POST[ Offer_Settings::META_TITLE ] ) ? sanitize_text_field( wp_unslash( $_POST[ Offer_Settings::META_TITLE ] ) ) : '';
		$text    = isset( $_POST[ Offer_Settings::META_TEXT ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ Offer_Settings::META_TEXT ] ) ) : '';

		if ( $target === $product->get_id() ) {
			$target = 0;
		}

		$product->update_meta_data( Offer_Settings::META_ENABLED, $enabled );
		$product->update_meta_data( Offer_Settings::META_PRODUCT, $target > 0 ? (string) $target : '' );
		$product->update_meta_data( Offer_Settings::META_PRICE, $price > 0 ? wc_format_decimal( $price, 3 ) : '' );
		$product->update_meta_data( Offer_Settings::META_TITLE, $title );
		$product->update_meta_data( Offer_Settings::META_TEXT, $text );
	}
}
