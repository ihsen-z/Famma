<?php
/**
 * Parcours visiteurs — export et effacement des données personnelles.
 *
 * Le parcours est anonyme tant qu'il n'est rattaché à aucune commande. Une
 * fois rattaché (`_famma_visitor_id`), il devient lié à une personne : il doit
 * donc sortir avec ses autres données dans Outils → Exporter les données
 * personnelles, et disparaître avec Outils → Effacer les données personnelles.
 *
 * La personne est retrouvée par l'e-mail de facturation de ses commandes,
 * comme le font les outils de WooCommerce eux-mêmes.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Exporteur et effaceur WordPress pour les parcours rattachés aux commandes.
 */
final class Visitor_Privacy {

	/**
	 * Commandes lues par passage.
	 */
	private const ORDERS_PER_PAGE = 50;

	/**
	 * Identifiant de l'exporteur et de l'effaceur.
	 */
	private const KEY = 'famma-visitor-journey';

	/**
	 * Instance partagée.
	 *
	 * @var Visitor_Privacy|null
	 */
	private static ?Visitor_Privacy $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Visitor_Privacy
	 */
	public static function instance(): Visitor_Privacy {
		if ( null === self::$i ) {
			self::$i = new self();
			add_filter( 'wp_privacy_personal_data_exporters', array( self::$i, 'register_exporter' ) );
			add_filter( 'wp_privacy_personal_data_erasers', array( self::$i, 'register_eraser' ) );
		}
		return self::$i;
	}

	/**
	 * Déclare l'exporteur.
	 *
	 * @param array<string, array<string, mixed>> $exporters Exporteurs.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters[ self::KEY ] = array(
			'exporter_friendly_name' => __( 'Parcours visiteurs FAMMA', 'famma-core' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Déclare l'effaceur.
	 *
	 * @param array<string, array<string, mixed>> $erasers Effaceurs.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers[ self::KEY ] = array(
			'eraser_friendly_name' => __( 'Parcours visiteurs FAMMA', 'famma-core' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Commandes d'une adresse e-mail, page par page.
	 *
	 * @param string $email Adresse e-mail.
	 * @param int    $page  Page, à partir de 1.
	 * @return array<int, \WC_Order>
	 */
	private function orders( string $email, int $page ): array {
		$email = sanitize_email( $email );
		if ( '' === $email ) {
			return array();
		}
		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'limit'         => self::ORDERS_PER_PAGE,
				'paged'         => max( 1, $page ),
				'orderby'       => 'ID',
				'order'         => 'ASC',
				'return'        => 'objects',
			)
		);
		return array_values( array_filter( (array) $orders, static fn( $o ) => $o instanceof \WC_Order ) );
	}

	/**
	 * Identifiants visiteurs portés par des commandes.
	 *
	 * @param array<int, \WC_Order> $orders Commandes.
	 * @return array<int, string>
	 */
	private function visitor_ids( array $orders ): array {
		$ids = array();
		foreach ( $orders as $order ) {
			$id = (string) $order->get_meta( Visitor_Commerce::META_VISITOR );
			if ( Visitor_Context::is_uuid( $id ) ) {
				$ids[ $id ] = $id;
			}
		}
		return array_values( $ids );
	}

	/**
	 * Exporte les événements rattachés aux commandes de l'adresse.
	 *
	 * @param string $email Adresse e-mail.
	 * @param int    $page  Page.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( $email, $page = 1 ): array {
		$orders = $this->orders( (string) $email, (int) $page );
		$labels = Visitor_Admin::event_labels();
		$items  = array();
		foreach ( Visitor_Store::instance()->events_for_visitors( $this->visitor_ids( $orders ) ) as $row ) {
			$items[] = array(
				'group_id'    => self::KEY,
				'group_label' => __( 'Parcours sur le site', 'famma-core' ),
				'item_id'     => 'famma-visitor-event-' . $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Date (UTC)', 'famma-core' ),
						'value' => $row['created_at'],
					),
					array(
						'name'  => __( 'Événement', 'famma-core' ),
						'value' => $labels[ $row['event'] ] ?? $row['event'],
					),
					array(
						'name'  => __( 'Page', 'famma-core' ),
						'value' => $row['path'],
					),
					array(
						'name'  => __( 'Détail', 'famma-core' ),
						'value' => trim( $row['label'] . ' ' . ( (int) $row['num'] ? $row['num'] : '' ) ),
					),
					array(
						'name'  => __( 'Source', 'famma-core' ),
						'value' => trim( $row['source'] . ' ' . $row['medium'] . ' ' . $row['campaign'] ),
					),
					array(
						'name'  => __( 'Appareil', 'famma-core' ),
						'value' => $row['device'],
					),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => count( $orders ) < self::ORDERS_PER_PAGE,
		);
	}

	/**
	 * Efface les événements et le rattachement des commandes de l'adresse.
	 *
	 * @param string $email Adresse e-mail.
	 * @param int    $page  Page.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( $email, $page = 1 ): array {
		$orders  = $this->orders( (string) $email, (int) $page );
		$removed = Visitor_Store::instance()->delete_visitors( $this->visitor_ids( $orders ) ) > 0;
		foreach ( $orders as $order ) {
			if ( '' !== (string) $order->get_meta( Visitor_Commerce::META_VISITOR ) ) {
				$order->delete_meta_data( Visitor_Commerce::META_VISITOR );
				$order->delete_meta_data( Visitor_Commerce::META_SESSION );
				$order->save();
				$removed = true;
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $orders ) < self::ORDERS_PER_PAGE,
		);
	}
}
