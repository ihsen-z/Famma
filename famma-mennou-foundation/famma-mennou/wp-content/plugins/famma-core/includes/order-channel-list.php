<?php
/**
 * Canal de commande — colonne et filtre de la liste des commandes.
 *
 * Colonne « Canal » et filtre par canal, en HPOS comme avec l'ancien
 * stockage (recettes officielles WooCommerce). Lecture seule : rien n'est
 * écrit ici.
 *
 * @package famma-core
 */

namespace Famma\Core;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Colonne et filtre « Canal » de la liste des commandes.
 */
final class Order_Channel_List {

	/** Paramètre d'URL du filtre de la liste. */
	private const QUERY_VAR = 'famma_channel';

	/**
	 * Instance partagée.
	 *
	 * @var Order_Channel_List|null
	 */
	private static ?Order_Channel_List $i = null;

	/**
	 * Renvoie l'instance partagée, crochets posés au premier appel.
	 *
	 * @return Order_Channel_List
	 */
	public static function instance(): Order_Channel_List {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Crochets de la liste des commandes.
	 *
	 * @return void
	 */
	private function hooks(): void {
		// Liste HPOS.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'render_filter' ) );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( $this, 'filter_hpos' ) );

		// Liste de l'ancien stockage (articles `shop_order`).
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'render_filter_legacy' ) );
		add_filter( 'request', array( $this, 'filter_legacy' ) );
	}

	/**
	 * Ajoute la colonne « Canal » après le statut.
	 *
	 * @param array<string, string> $columns Colonnes de la liste.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$out['famma_channel'] = __( 'Canal', 'famma-core' );
			}
		}
		if ( ! isset( $out['famma_channel'] ) ) {
			$out['famma_channel'] = __( 'Canal', 'famma-core' );
		}
		return $out;
	}

	/**
	 * Affiche la cellule « Canal ».
	 *
	 * @param string                 $column Colonne en cours.
	 * @param \WC_Order|int|\WP_Post $order  Commande (HPOS) ou identifiant (ancien stockage).
	 * @return void
	 */
	public function render_column( string $column, $order ): void {
		if ( 'famma_channel' !== $column ) {
			return;
		}
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order instanceof \WP_Post ? $order->ID : (int) $order );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$slug = Order_Channel::of( $order );
		$ref  = (string) $order->get_meta( Order_Channel::META_REF );
		echo '' === $slug
			? '<span style="color:#b26200">' . esc_html( Order_Channel::label( $slug ) ) . '</span>'
			: esc_html( Order_Channel::label( $slug ) );
		if ( '' !== $ref ) {
			echo '<br><small>' . esc_html( $ref ) . '</small>';
		}
	}

	/**
	 * Canal choisi dans le filtre de la liste ('' : tous).
	 *
	 * @return string
	 */
	private function requested(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, no state change.
		$value = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : '';
		return Order_Channel::SITE === $value || isset( Order_Channel::channels()[ $value ] ) ? $value : '';
	}

	/**
	 * Liste déroulante du filtre (liste HPOS).
	 *
	 * @return void
	 */
	public function render_filter(): void {
		$current = $this->requested();
		echo '<select name="' . esc_attr( self::QUERY_VAR ) . '"><option value="">' . esc_html__( 'Tous les canaux', 'famma-core' ) . '</option>';
		$options = array( Order_Channel::SITE => Order_Channel::label( Order_Channel::SITE ) ) + Order_Channel::channels();
		foreach ( $options as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $current, $slug, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Liste déroulante du filtre (ancien stockage).
	 *
	 * @param string $post_type Type d'article listé.
	 * @return void
	 */
	public function render_filter_legacy( string $post_type ): void {
		if ( 'shop_order' === $post_type ) {
			$this->render_filter();
		}
	}

	/**
	 * Applique le filtre à la liste HPOS.
	 *
	 * @param array<string, mixed> $args Arguments de `wc_get_orders()`.
	 * @return array<string, mixed>
	 */
	public function filter_hpos( array $args ): array {
		$channel = $this->requested();
		if ( Order_Channel::SITE === $channel ) {
			$args['created_via'] = 'checkout';
		} elseif ( '' !== $channel ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin list filter chosen by staff, paginated by WooCommerce.
			$args['meta_query']   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
			$args['meta_query'][] = array(
				'key'   => Order_Channel::META,
				'value' => $channel,
			);
		}
		return $args;
	}

	/**
	 * Applique le filtre à la liste de l'ancien stockage.
	 *
	 * @param array<string, mixed> $vars Variables de la requête principale.
	 * @return array<string, mixed>
	 */
	public function filter_legacy( array $vars ): array {
		if ( ! is_admin() || ( $vars['post_type'] ?? '' ) !== 'shop_order' || ( class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled() ) ) {
			return $vars;
		}
		$channel = $this->requested();
		if ( '' === $channel ) {
			return $vars;
		}
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin list filter chosen by staff, paginated by WordPress.
		$vars['meta_query']   = isset( $vars['meta_query'] ) && is_array( $vars['meta_query'] ) ? $vars['meta_query'] : array();
		$vars['meta_query'][] = Order_Channel::SITE === $channel
			? array(
				'key'   => '_created_via',
				'value' => 'checkout',
			)
			: array(
				'key'   => Order_Channel::META,
				'value' => $channel,
			);
		return $vars;
	}
}
