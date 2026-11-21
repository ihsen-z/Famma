<?php
/**
 * Avis par lien WhatsApp — côté administration.
 *
 * - Encadré « Avis client (WhatsApp) » sur la fiche d'une commande : un bouton
 *   par produit et par langue, qui ouvre WhatsApp (wa.me) avec le numéro du
 *   client et le message prêt. Le propriétaire appuie lui-même sur Envoyer :
 *   rien ne part tout seul.
 * - Réglage de la durée de validité du lien, dans WooCommerce → Réglages →
 *   Produits, juste sous les réglages des avis.
 *
 * Compatible HPOS : l'écran est choisi selon le stockage des commandes, et la
 * commande est relue par `wc_get_order()` (recette officielle WooCommerce).
 *
 * @package famma-core
 */

namespace Famma\Core;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Bouton « Demander un avis » et réglage du lien.
 */
final class Review_Admin {

	/**
	 * Instance partagée.
	 *
	 * @var Review_Admin|null
	 */
	private static ?Review_Admin $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Review_Admin
	 */
	public static function instance(): Review_Admin {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'add_meta_boxes', array( self::$i, 'register' ) );
			add_filter( 'woocommerce_get_settings_products', array( self::$i, 'settings' ), 10, 2 );
		}
		return self::$i;
	}

	/**
	 * Déclare l'encadré, seulement avec la case cochée.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! Review_Link::enabled() ) {
			return;
		}

		$screen = class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
		add_meta_box( 'famma-review-request', __( 'Avis client (WhatsApp)', 'famma-core' ), array( $this, 'render' ), $screen, 'side', 'default' );
	}

	/**
	 * Ajoute la durée du lien sous les réglages des avis.
	 *
	 * @param mixed  $settings Champs de la section.
	 * @param string $section  Section affichée (vide = générale).
	 * @return mixed
	 */
	public function settings( $settings, $section = '' ) {
		if ( '' !== (string) $section || ! is_array( $settings ) || in_array( Review_Link::OPTION_DAYS, array_column( $settings, 'id' ), true ) ) {
			return $settings;
		}

		$field = array(
			'title'             => __( 'Lien d’avis WhatsApp (FAMMA)', 'famma-core' ),
			'desc'              => __( 'Nombre de jours pendant lesquels le lien « Donnez votre avis » reste valable, à compter de la livraison. La fonction s’active dans FAMMA → Nouveau look → Fiche produit.', 'famma-core' ),
			'id'                => Review_Link::OPTION_DAYS,
			'type'              => 'number',
			'default'           => '30',
			'css'               => 'width:6em;',
			'custom_attributes' => array(
				'min'  => '1',
				'max'  => '365',
				'step' => '1',
			),
		);

		$out = array();

		foreach ( $settings as $setting ) {
			$out[] = $setting;

			if ( is_array( $setting ) && 'woocommerce_review_rating_required' === ( $setting['id'] ?? '' ) ) {
				$out[] = $field;
			}
		}

		return $out;
	}

	/**
	 * Affiche l'encadré.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Objet passé par WordPress.
	 * @return void
	 */
	public function render( $post_or_order ): void {
		$order = $post_or_order instanceof \WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;

		if ( ! $order instanceof \WC_Order || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! Review_Link::eligible( $order ) ) {
			$this->line( __( 'Le bouton apparaît quand la commande est Livrée ou Terminée.', 'famma-core' ) );
			return;
		}

		$phone = Tunisia::normalize_phone( $order->get_billing_phone() );

		if ( '' === $phone ) {
			$this->line( __( 'Pas de numéro tunisien valable sur la commande : impossible de préparer le WhatsApp.', 'famma-core' ) );
			return;
		}

		// Commande livrée avant l'activation : son lien naît ici.
		Review_Link::issue( $order );

		$expires = Review_Link::expires( $order );

		if ( time() > $expires ) {
			$this->line( __( 'Le lien d’avis de cette commande a expiré.', 'famma-core' ) );
			return;
		}

		foreach ( Review_Link::products( $order ) as $product_id ) {
			$this->product_row( $order, $product_id, $phone );
		}

		/* translators: %s: date de fin de validité du lien. */
		$this->line( sprintf( __( 'Lien valable jusqu’au %s. Vous appuyez vous-même sur Envoyer dans WhatsApp ; l’avis arrive en attente dans Produits → Avis.', 'famma-core' ), wp_date( get_option( 'date_format' ), $expires ) ), 'description' );
	}

	/**
	 * Une ligne par produit : état, ou boutons WhatsApp.
	 *
	 * @param \WC_Order $order      Commande.
	 * @param int       $product_id Produit.
	 * @param string    $phone      Numéro local à 8 chiffres.
	 * @return void
	 */
	private function product_row( \WC_Order $order, int $product_id, string $phone ): void {
		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		echo '<p><strong>' . esc_html( $product->get_name() ) . '</strong><br />';

		if ( Review_Link::done( $order, $product_id ) ) {
			echo esc_html__( 'Avis reçu avec ce lien.', 'famma-core' ) . '</p>';
			return;
		}

		if ( ! $product->get_reviews_allowed() ) {
			echo esc_html__( 'Avis fermés sur ce produit (case « Activer les avis » de la fiche produit).', 'famma-core' ) . '</p>';
			return;
		}

		$labels = array(
			'fr' => __( 'Demander un avis (français)', 'famma-core' ),
			'ar' => __( 'Demander un avis (derja)', 'famma-core' ),
		);
		$shown  = 0;

		foreach ( $labels as $lang => $label ) {
			$message = Review_Texts::message( $lang, $order, $product_id );

			if ( '' === $message ) {
				continue;
			}

			$url = 'https://wa.me/216' . $phone . '?text=' . rawurlencode( $message );
			echo '<a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
			++$shown;
		}

		if ( 0 === $shown ) {
			echo esc_html__( 'Message vide : à écrire dans FAMMA → Nouveau look → Fiche produit.', 'famma-core' );
		}

		echo '</p>';
	}

	/**
	 * Une ligne de texte de l'encadré.
	 *
	 * @param string $text Texte.
	 * @param string $css  Classe du paragraphe.
	 * @return void
	 */
	private function line( string $text, string $css = '' ): void {
		echo '<p class="' . esc_attr( $css ) . '">' . esc_html( $text ) . '</p>';
	}
}
