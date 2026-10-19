<?php
/**
 * Rapprochement commandes / événements serveur sur 14 jours (lot 5, R8, A5).
 *
 * Règle large et indépendante de l'émetteur : toute commande non test doit
 * avoir un OrderPlaced accepté, toute commande livrée (ou terminée en
 * paiement à la livraison, D5) un Purchase accepté. Une commande que
 * l'émetteur écarte volontairement (création dans l'administration,
 * OrderPlaced désactivé, commande annulée avant l'envoi) est comptée parmi
 * les « inéligibles », avec son motif. Les commandes de test
 * (`_famma_test_order`) sont exclues des comptes mais listées.
 *
 * « Renvoyer » rejoue le même event_id, dans des bornes fixées par Config :
 * rejeté depuis moins de 6 jours, incertain depuis moins de 24 heures,
 * absent si l'événement a moins de 6 jours ; jamais pour un accepté.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Calcul du rapprochement et action « Renvoyer ».
 */
final class Capi_Reconcile {

	/**
	 * Fenêtre du rapprochement, en secondes.
	 */
	public const WINDOW = 1209600;

	/**
	 * Nombre maximal de commandes lues.
	 */
	public const LIMIT = 200;

	/**
	 * Instance unique.
	 *
	 * @var Capi_Reconcile|null
	 */
	private static ?Capi_Reconcile $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Reconcile
	 */
	public static function instance(): Capi_Reconcile {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_post_famma_capi_resend', array( self::$i, 'handle_resend' ) );
		}
		return self::$i;
	}

	/**
	 * Lignes et totaux du rapprochement.
	 *
	 * @param int $now Horloge.
	 * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>, truncated: bool}
	 */
	public function build( int $now ): array {
		$orders  = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'date_created' => '>' . ( $now - self::WINDOW ),
				'limit'        => self::LIMIT,
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);
		$orders  = is_array( $orders ) ? array_filter( $orders, static fn( $o ): bool => $o instanceof \WC_Order ) : array();
		$records = Capi_Delivery_Registry::instance()->for_orders( array_map( static fn( \WC_Order $o ): int => $o->get_id(), $orders ) );
		$totals  = array(
			'orderplaced_expected' => 0,
			'orderplaced_accepted' => 0,
			'purchase_expected'    => 0,
			'purchase_accepted'    => 0,
			'ineligible'           => array(),
			'test_orders'          => 0,
		);
		$rows    = array();
		foreach ( $orders as $order ) {
			$row    = $this->row( $order, $records[ $order->get_id() ] ?? array(), $now );
			$rows[] = $row;
			if ( $row['test'] ) {
				++$totals['test_orders'];
				continue;
			}
			foreach ( array( 'orderplaced', 'purchase' ) as $event ) {
				$cell = $row[ $event ];
				if ( $cell['expected'] ) {
					++$totals[ $event . '_expected' ];
					$totals[ $event . '_accepted' ] += 'accepted' === $cell['state'] ? 1 : 0;
				} elseif ( '' !== $cell['reason'] && 'not_delivered' !== $cell['reason'] ) {
					$totals['ineligible'][ $cell['reason'] ] = ( $totals['ineligible'][ $cell['reason'] ] ?? 0 ) + 1;
				}
			}
		}
		return array(
			'rows'      => $rows,
			'totals'    => $totals,
			'truncated' => count( $orders ) >= self::LIMIT,
		);
	}

	/**
	 * Une ligne : état des deux événements d'une commande.
	 *
	 * @param \WC_Order                           $order   Commande.
	 * @param array<string, array<string, mixed>> $records Enregistrements du registre, par événement.
	 * @param int                                 $now     Horloge.
	 * @return array<string, mixed>
	 */
	public function row( \WC_Order $order, array $records, int $now ): array {
		return array(
			'order'       => $order,
			'test'        => (bool) $order->get_meta( '_famma_test_order' ),
			'fbc'         => '' !== (string) $order->get_meta( '_famma_fbc' ),
			'orderplaced' => $this->cell( $order, 'OrderPlaced', $records['OrderPlaced'] ?? array(), $now ),
			'purchase'    => $this->cell( $order, 'Purchase', $records['Purchase'] ?? array(), $now ),
		);
	}

	/**
	 * État d'un événement d'une commande.
	 *
	 * @param \WC_Order            $order  Commande.
	 * @param string               $event  OrderPlaced ou Purchase.
	 * @param array<string, mixed> $record Enregistrement du registre, ou vide.
	 * @param int                  $now    Horloge.
	 * @return array{expected: bool, reason: string, state: string, resend: bool}
	 */
	public function cell( \WC_Order $order, string $event, array $record, int $now ): array {
		$sent_key = 'Purchase' === $event ? '_famma_purchase_sent' : '_famma_orderplaced_sent';
		$state    = isset( $record['state'] ) ? (string) $record['state'] : 'absent';
		if ( $order->get_meta( $sent_key ) ) {
			$state = 'accepted';
		}
		$reason = 'accepted' === $state ? '' : $this->ineligibility( $order, $event );
		return array(
			'expected' => '' === $reason,
			'reason'   => $reason,
			'state'    => $state,
			'resend'   => '' === $reason && self::resend_allowed( $state, $record, $this->event_time( $order, $event, $record, $now ), $now ),
		);
	}

	/**
	 * Motif d'inéligibilité d'un événement, ou chaîne vide s'il est attendu.
	 *
	 * @param \WC_Order $order Commande.
	 * @param string    $event OrderPlaced ou Purchase.
	 * @return string
	 */
	private function ineligibility( \WC_Order $order, string $event ): string {
		if ( 'Purchase' === $event ) {
			if ( $order->has_status( 'delivered' ) || ( $order->has_status( 'completed' ) && Order_Statuses::is_cod_order( $order ) ) ) {
				return '';
			}
			return 'not_delivered';
		}
		if ( 'checkout' !== $order->get_created_via() ) {
			return 'admin';
		}
		if ( ! get_option( 'famma_meta_orderplaced_enabled', false ) ) {
			return 'disabled';
		}
		if ( $order->has_status( array( 'checkout-draft', 'draft', 'failed', 'cancelled', 'trash', 'refused', 'returned', 'failed-delivery' ) ) ) {
			return 'status';
		}
		return $order->get_meta( '_famma_orderplaced_checkout_accepted' ) ? '' : 'not_accepted';
	}

	/**
	 * Horodatage de l'événement pour les bornes de renvoi.
	 *
	 * @param \WC_Order            $order  Commande.
	 * @param string               $event  OrderPlaced ou Purchase.
	 * @param array<string, mixed> $record Enregistrement du registre.
	 * @param int                  $now    Horloge.
	 * @return int
	 */
	private function event_time( \WC_Order $order, string $event, array $record, int $now ): int {
		if ( isset( $record['event_time'] ) ) {
			return (int) $record['event_time'];
		}
		if ( 'Purchase' === $event ) {
			$stored = (int) $order->get_meta( '_famma_purchase_event_time' );
			return $stored > 0 ? $stored : $now;
		}
		$created = $order->get_date_created();
		return $created ? $created->getTimestamp() : $now;
	}

	/**
	 * Bornes du bouton « Renvoyer » (R8).
	 *
	 * @param string               $state      État : accepted, rejected, uncertain, sending ou absent.
	 * @param array<string, mixed> $record     Enregistrement du registre.
	 * @param int                  $event_time Horodatage de l'événement.
	 * @param int                  $now        Horloge.
	 * @return bool
	 */
	public static function resend_allowed( string $state, array $record, int $event_time, int $now ): bool {
		if ( 'accepted' === $state || $now - $event_time > Config::CAPI_MAX_EVENT_AGE ) {
			return false;
		}
		$updated = isset( $record['updated_at'] ) ? (int) strtotime( (string) $record['updated_at'] . ' UTC' ) : 0;
		$age     = $updated > 0 ? $now - $updated : PHP_INT_MAX;
		switch ( $state ) {
			case 'absent':
				return true;
			case 'rejected':
				return $age < Config::CAPI_RESEND_REJECTED_MAX_AGE;
			case 'uncertain':
				return $age < Config::CAPI_RESEND_UNCERTAIN_MAX_AGE;
			case 'sending':
				return $age >= Config::CAPI_STALE_SENDING && $age < Config::CAPI_RESEND_UNCERTAIN_MAX_AGE;
		}
		return false;
	}

	/**
	 * Action « Renvoyer » : nonce, capacité, bornes, puis même event_id.
	 *
	 * @return void
	 */
	public function handle_resend(): void {
		check_admin_referer( 'famma_capi_resend' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ), '', array( 'response' => 403 ) );
		}
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$event    = isset( $_POST['event'] ) ? sanitize_text_field( wp_unslash( $_POST['event'] ) ) : '';
		$order    = wc_get_order( $order_id );
		$result   = 'refused';
		if ( $order instanceof \WC_Order && in_array( $event, array( 'OrderPlaced', 'Purchase' ), true ) ) {
			$records = Capi_Delivery_Registry::instance()->for_orders( array( $order_id ) );
			$cell    = $this->cell( $order, $event, $records[ $order_id ][ $event ] ?? array(), time() );
			$result  = $cell['resend'] ? Capi::instance()->resend( $order, $event ) : 'refused';
		}
		wp_safe_redirect( add_query_arg( 'famma_capi_resend', sanitize_key( $result ), admin_url( 'admin.php?page=famma-tracking' ) ) . '#famma-reconcile' );
		exit;
	}
}
