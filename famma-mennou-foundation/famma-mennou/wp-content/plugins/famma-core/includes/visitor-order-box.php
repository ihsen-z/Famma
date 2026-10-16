<?php
/**
 * Parcours visiteurs — encadré « Parcours avant commande » de la fiche commande.
 *
 * Résume, à côté de la commande, d'où vient le client et ce qu'il a fait avant
 * de commander : source d'arrivée, nombre de visites, pages et produits vus,
 * temps entre l'arrivée et la commande. Le détail complet reste dans
 * FAMMA → Parcours visiteurs, via le lien.
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
 * Encadré latéral de la fiche commande.
 */
final class Visitor_Order_Box {

	/**
	 * Événements affichés au plus dans l'encadré.
	 */
	private const SHOWN = 12;

	/**
	 * Instance partagée.
	 *
	 * @var Visitor_Order_Box|null
	 */
	private static ?Visitor_Order_Box $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Visitor_Order_Box
	 */
	public static function instance(): Visitor_Order_Box {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'add_meta_boxes', array( self::$i, 'register' ) );
		}
		return self::$i;
	}

	/**
	 * Déclare l'encadré sur l'écran de commande en vigueur.
	 *
	 * @return void
	 */
	public function register(): void {
		$screen = class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
		add_meta_box( 'famma-visitor-journey', __( 'Parcours avant commande', 'famma-core' ), array( $this, 'render' ), $screen, 'side', 'default' );
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
		$session = (string) $order->get_meta( Visitor_Commerce::META_SESSION );
		$visitor = (string) $order->get_meta( Visitor_Commerce::META_VISITOR );
		if ( ! Visitor_Context::is_uuid( $session ) ) {
			echo '<p>' . esc_html__( 'Aucun parcours enregistré pour cette commande (commande manuelle, ou passée avant le suivi).', 'famma-core' ) . '</p>';
			return;
		}

		$report = new Visitor_Report();
		$rows   = $report->timeline( $session );
		if ( array() === $rows ) {
			echo '<p>' . esc_html__( 'Le parcours de cette commande a été purgé (durée de conservation dépassée).', 'famma-core' ) . '</p>';
			return;
		}
		$history  = Visitor_Context::is_uuid( $visitor ) ? $report->visitor_history( $visitor ) : array(
			'sessions'   => 1,
			'first_seen' => '',
		);
		$first    = $rows[0];
		$start    = strtotime( $first['created_at'] . ' UTC' );
		$pages    = 0;
		$products = array();
		$source   = '';
		foreach ( $rows as $row ) {
			if ( 'page_view' === $row['event'] ) {
				++$pages;
			}
			if ( (int) $row['product_id'] ) {
				$products[ (int) $row['product_id'] ] = true;
			}
			if ( '' === $source && '' !== $row['source'] ) {
				$source = trim( $row['source'] . ' / ' . $row['medium'] . ' / ' . $row['campaign'], ' /' );
			}
		}
		$created = $order->get_date_created();
		$delay   = $created ? max( 0, $created->getTimestamp() - $start ) : 0;

		echo '<ul class="famma-vj-box">';
		/* translators: %s: traffic source. */
		echo '<li>' . esc_html( sprintf( __( 'Source : %s', 'famma-core' ), '' !== $source ? $source : 'direct' ) ) . '</li>';
		/* translators: %s: device family. */
		echo '<li>' . esc_html( sprintf( __( 'Appareil : %s', 'famma-core' ), $first['device'] ) ) . '</li>';
		/* translators: %d: number of visits. */
		echo '<li>' . esc_html( sprintf( _n( '%d visite au total', '%d visites au total', $history['sessions'], 'famma-core' ), $history['sessions'] ) ) . '</li>';
		/* translators: 1: pages viewed, 2: distinct products viewed. */
		echo '<li>' . esc_html( sprintf( __( 'Cette visite : %1$d pages, %2$d produits', 'famma-core' ), $pages, count( $products ) ) ) . '</li>';
		/* translators: %s: human readable duration. */
		echo '<li>' . esc_html( sprintf( __( 'Arrivée → commande : %s', 'famma-core' ), human_time_diff( 0, $delay ) ) ) . '</li>';
		echo '</ul>';

		$labels = Visitor_Admin::event_labels();
		echo '<ol class="famma-vj-box__events">';
		foreach ( array_slice( $rows, -self::SHOWN ) as $row ) {
			$what = $labels[ $row['event'] ] ?? $row['event'];
			if ( 'form_field' === $row['event'] ) {
				$page = Visitor_Admin_Views::field_label( $row['label'] );
			} else {
				$page = (int) $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : $row['path'];
			}
			echo '<li><time>' . esc_html( wp_date( 'H:i', strtotime( $row['created_at'] . ' UTC' ) ) ) . '</time> ' . esc_html( $what ) . ( '' !== $page ? ' <code>' . esc_html( $page ) . '</code>' : '' ) . '</li>';
		}
		echo '</ol>';

		$url = add_query_arg(
			array(
				'page'    => 'famma-visitors',
				'session' => $session,
			),
			admin_url( 'admin.php' )
		);
		echo '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Voir le parcours complet', 'famma-core' ) . '</a></p>';
	}
}
