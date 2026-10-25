<?php
/**
 * Purchase manquants : comptage pour l'état de santé et acquittement (relecture du lot 5, R-CACHE-2).
 *
 * Une commande livrée (ou terminée et encaissée) sans Purchase accepté depuis
 * plus de 24 h est rangée selon ce que l'on peut encore faire pour elle, avec
 * les bornes du bouton « Renvoyer » du rapprochement (`Capi_Reconcile`) :
 *
 * - `missing` : un renvoi est encore possible, raison rouge ;
 * - `lost` : renvoi refusé (événement de plus de 6 jours, rejet ou envoi
 *   incertain trop ancien), raison orange. Avant ce tri, une telle commande
 *   gardait le rouge allumé jusqu'à 14 jours sans aucun remède, et l'e-mail
 *   du passage au rouge ne pouvait plus annoncer une autre panne.
 *
 * « Acquitter l'alerte » (rapprochement, droits `manage_woocommerce`) pose la
 * méta `_famma_purchase_ack` : la commande ne compte plus pour l'état de
 * santé, mais le rapprochement continue de la montrer sans Purchase.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Tri des Purchase manquants et action « Acquitter l'alerte ».
 */
final class Capi_Purchase_Gaps {

	/**
	 * Méta de commande : date de l'acquittement.
	 */
	public const ACK_META = '_famma_purchase_ack';

	/**
	 * Action admin-post et nonce de l'acquittement.
	 */
	public const ACK = 'famma_capi_purchase_ack';

	/**
	 * Instance unique.
	 *
	 * @var Capi_Purchase_Gaps|null
	 */
	private static ?Capi_Purchase_Gaps $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Purchase_Gaps
	 */
	public static function instance(): Capi_Purchase_Gaps {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_post_' . self::ACK, array( self::$i, 'handle_ack' ) );
		}
		return self::$i;
	}

	/**
	 * Commandes livrées depuis plus de 24 h sans Purchase accepté, sur 14 jours.
	 *
	 * Mêmes exclusions qu'avant : commandes de test, Purchase non attendu
	 * (`Order_Statuses::purchase_ineligibility()`), et désormais commandes
	 * acquittées ou acceptées selon le registre d'envoi.
	 *
	 * @param int $now Horloge.
	 * @return array{missing: int, lost: int}
	 */
	public static function count( int $now ): array {
		$gaps = array(
			'missing' => 0,
			'lost'    => 0,
		);
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $gaps;
		}
		$orders = wc_get_orders(
			array(
				'type'          => 'shop_order',
				'status'        => array( 'wc-delivered', 'wc-completed' ),
				'date_modified' => '>' . ( $now - Capi_Health::WINDOW ),
				'limit'         => 100,
			)
		);
		$late   = array();
		foreach ( is_array( $orders ) ? $orders : array() as $order ) {
			if ( ! $order instanceof \WC_Order || $order->get_meta( '_famma_purchase_sent' ) || $order->get_meta( '_famma_test_order' ) || self::acknowledged( $order ) ) {
				continue;
			}
			if ( '' !== Order_Statuses::purchase_ineligibility( $order ) ) {
				continue;
			}
			$since = $order->get_date_paid() ? $order->get_date_paid()->getTimestamp() : ( $order->get_date_modified() ? $order->get_date_modified()->getTimestamp() : $now );
			if ( $now - $since > Capi_Health::PURCHASE_GRACE ) {
				$late[ $order->get_id() ] = $order;
			}
		}
		if ( array() === $late ) {
			return $gaps;
		}
		$records = Capi_Delivery_Registry::instance()->for_orders( array_keys( $late ) );
		foreach ( $late as $order_id => $order ) {
			$record = $records[ $order_id ]['Purchase'] ?? array();
			$state  = isset( $record['state'] ) ? (string) $record['state'] : 'absent';
			if ( 'accepted' === $state ) {
				continue;
			}
			$resend = Capi_Reconcile::resend_allowed( $state, $record, Capi_Reconcile::event_time( $order, 'Purchase', $record, $now ), $now );
			++$gaps[ $resend ? 'missing' : 'lost' ];
		}
		return $gaps;
	}

	/**
	 * L'alerte de cette commande a-t-elle été acquittée ?
	 *
	 * @param \WC_Order $order Commande.
	 * @return bool
	 */
	public static function acknowledged( \WC_Order $order ): bool {
		return '' !== (string) $order->get_meta( self::ACK_META );
	}

	/**
	 * Action « Acquitter l'alerte » : nonce, capacité, commande concernée, puis méta et note.
	 *
	 * @return void
	 */
	public function handle_ack(): void {
		check_admin_referer( self::ACK );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ), '', array( 'response' => 403 ) );
		}
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$order    = wc_get_order( $order_id );
		$result   = 'refused';
		if ( $order instanceof \WC_Order && ! $order->get_meta( '_famma_purchase_sent' ) && '' === Order_Statuses::purchase_ineligibility( $order ) && ! self::acknowledged( $order ) ) {
			$order->update_meta_data( self::ACK_META, (string) time() );
			$order->save();
			$order->add_order_note( __( 'Alerte « Purchase manquant » acquittée dans FAMMA → Mesure : cette commande ne compte plus pour l’état de santé de la mesure. Le rapprochement la montre toujours sans Purchase.', 'famma-core' ), 0, true );
			Capi_Health::evaluate();
			$result = 'acknowledged';
		}
		wp_safe_redirect( add_query_arg( 'famma_capi_ack', $result, admin_url( 'admin.php?page=famma-tracking' ) ) . '#famma-reconcile' );
		exit;
	}

	/**
	 * Bouton « Acquitter l'alerte » d'une cellule Purchase du rapprochement, ou la mention de l'acquittement.
	 *
	 * @param \WC_Order $order Commande.
	 * @return void
	 */
	public static function render_ack( \WC_Order $order ): void {
		if ( self::acknowledged( $order ) ) {
			echo '<br /><em>' . esc_html__( 'alerte acquittée', 'famma-core' ) . '</em>';
			return;
		}
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACK ); ?>" />
			<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
			<?php wp_nonce_field( self::ACK ); ?>
			<?php submit_button( __( 'Acquitter l’alerte', 'famma-core' ), 'small', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Avis de l'issue du dernier acquittement.
	 *
	 * @return void
	 */
	public static function result(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- identifiant d'issue fixe, en lecture seule.
		$result = isset( $_GET['famma_capi_ack'] ) ? sanitize_key( wp_unslash( $_GET['famma_capi_ack'] ) ) : '';
		if ( '' === $result ) {
			return;
		}
		$message = 'acknowledged' === $result
			? __( 'Alerte acquittée : la commande ne compte plus pour l’état de santé.', 'famma-core' )
			: __( 'Acquittement refusé : la commande n’attend pas de Purchase, l’a déjà reçu ou est déjà acquittée.', 'famma-core' );
		printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( 'acknowledged' === $result ? 'success' : 'warning' ), esc_html( $message ) );
	}
}
