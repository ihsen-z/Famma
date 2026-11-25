<?php
/**
 * Commandes d'essai : jamais d'événement Meta (lot 1 des réformes du pixel, 24/11).
 *
 * Le repère `_famma_test_order` n'était lu que par les rapports
 * (rapprochement, santé, offre) ; rien ne l'écrivait. Cette classe le pose :
 *
 * - d'office, quand la commande est passée SUR LE SITE (checkout classique
 *   ou formulaire express, tous deux par `process_checkout()`) par un
 *   utilisateur connecté qui a la capacité `manage_woocommerce` ; une
 *   commande saisie dans l'administration (vraie commande par téléphone)
 *   n'est jamais marquée d'office ;
 * - par la case « Commande d'essai » de l'écran de commande (HPOS et ancien
 *   stockage).
 *
 * La règle d'envoi (`Capi::is_test_order()`) est lue par l'émetteur au moment
 * de l'envoi : file, relance planifiée, bouton « Renvoyer ».
 *
 * @package famma-core
 */

namespace Famma\Core;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Pose et retire le repère « commande d'essai ».
 */
final class Test_Orders {

	/**
	 * Champ et action du nonce de la case.
	 */
	private const NONCE = 'famma_test_order_nonce';

	/**
	 * Champ de la case.
	 */
	private const FIELD = 'famma_test_order';

	/**
	 * Marqueurs d'un événement déjà accepté par Meta.
	 */
	private const SENT_MARKERS = array( '_famma_orderplaced_sent', '_famma_purchase_sent', '_famma_offeraccepted_sent', '_famma_ordercancelled_sent', '_famma_orderreturned_sent' );

	/**
	 * Instance partagée.
	 *
	 * @var Test_Orders|null
	 */
	private static ?Test_Orders $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Test_Orders
	 */
	public static function instance(): Test_Orders {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les points d'accroche.
	 *
	 * @return void
	 */
	private function hooks(): void {
		// Avant `woocommerce_payment_successful_result`, où Capi met OrderPlaced en file.
		add_action( 'woocommerce_checkout_order_created', array( $this, 'mark_staff_checkout' ) );
		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'register_box' ) );
			// Priorité 35 : avant WC_Meta_Box_Order_Data::save (40), qui change le statut.
			add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_box' ), 35 );
		}
	}

	/**
	 * Marque d'essai une commande passée sur le site par un gérant connecté.
	 *
	 * @param \WC_Order|mixed $order Commande tout juste créée par le checkout.
	 * @return void
	 */
	public function mark_staff_checkout( $order ): void {
		if ( ! $order instanceof \WC_Order || 'checkout' !== $order->get_created_via() ) {
			return;
		}
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$order->update_meta_data( Capi::TEST_ORDER_META, 'yes' );
		$order->add_order_note( __( 'Commande passée sur le site par un gérant connecté : marquée « commande d\'essai », aucun événement Meta ne partira. Décochez la case « Commande d\'essai » si c\'est une vraie commande.', 'famma-core' ) );
		$order->save();
	}

	/**
	 * Déclare l'encadré sur l'écran de commande en vigueur (HPOS ou ancien stockage).
	 *
	 * @return void
	 */
	public function register_box(): void {
		$screen = class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
		add_meta_box( 'famma-test-order', __( 'Commande d\'essai', 'famma-core' ), array( $this, 'render_box' ), $screen, 'side', 'high' );
	}

	/**
	 * Affiche la case « Commande d'essai ».
	 *
	 * @param \WP_Post|\WC_Order|mixed $post_or_order Objet passé par WordPress.
	 * @return void
	 */
	public function render_box( $post_or_order ): void {
		$order = $post_or_order instanceof \WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		wp_nonce_field( self::NONCE, self::NONCE );
		echo '<label><input type="checkbox" name="' . esc_attr( self::FIELD ) . '" value="1"' . checked( Capi::is_test_order( $order ), true, false ) . ' /> ';
		echo esc_html__( 'Commande d\'essai', 'famma-core' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Aucun événement Meta (OrderPlaced, Purchase, OfferAccepted, annulation, retour) ne part pour une commande d\'essai. Un événement déjà accepté par Meta ne peut pas être rappelé.', 'famma-core' ) . '</p>';
	}

	/**
	 * Enregistre la case : nonce, capacité, assainissement, puis méta et note.
	 *
	 * @param int $order_id Commande enregistrée.
	 * @return void
	 */
	public function save_box( int $order_id ): void {
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Capacité de WooCommerce (WC_Install::get_core_capabilities), modifier les commandes.
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$wanted = isset( $_POST[ self::FIELD ] ) && '1' === sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) );
		if ( Capi::is_test_order( $order ) === $wanted ) {
			return;
		}
		if ( $wanted ) {
			$order->update_meta_data( Capi::TEST_ORDER_META, 'yes' );
			$order->add_order_note( __( 'Marquée « commande d\'essai » : plus aucun événement Meta ne partira pour cette commande.', 'famma-core' ) );
			if ( $this->already_accepted( $order ) ) {
				$order->add_order_note( __( 'Un événement de cette commande avait déjà été accepté par Meta avant le marquage : il n\'y a rien à annuler chez Meta (un événement reçu ne se rappelle pas). Seuls les envois suivants sont bloqués.', 'famma-core' ) );
			}
			$order->save();
			return;
		}
		$order->delete_meta_data( Capi::TEST_ORDER_META );
		$order->delete_meta_data( Capi::TEST_ORDER_NOTED );
		$order->save();
		// Commande du site marquée d'office : son OrderPlaced avait été écarté au checkout.
		$order->add_order_note( self::unmarked_note( Capi::instance()->requeue_order_placed( $order ) ) );
		$order->save();
	}

	/**
	 * Note du décochage : ce qui part désormais vers Meta, et ce qui ne part pas.
	 *
	 * @param string $requeue Issue de `Capi::requeue_order_placed()`.
	 * @return string
	 */
	private static function unmarked_note( string $requeue ): string {
		$next = __( 'Le Purchase (après la livraison) et le retour partiront normalement.', 'famma-core' );
		$none = __( 'Sans OrderPlaced accepté, aucune annulation (OrderCancelled) ne sera envoyée.', 'famma-core' );
		switch ( $requeue ) {
			case 'queued':
				$why = __( 'Repère « commande d\'essai » retiré : l\'OrderPlaced de cette commande est remis en file et partira dans quelques instants (même identifiant d\'événement, heure de la commande). Une fois cet OrderPlaced accepté, une annulation pourra être envoyée.', 'famma-core' );
				return $why . ' ' . $next;
			case 'already_sent':
				return __( 'Repère « commande d\'essai » retiré : l\'OrderPlaced avait déjà été accepté par Meta, rien n\'est renvoyé.', 'famma-core' ) . ' ' . $next;
			case 'disabled':
				$why = __( 'Repère « commande d\'essai » retiré. L\'OrderPlaced ne part pas : il est désactivé dans FAMMA → Mesure.', 'famma-core' );
				break;
			case 'too_old':
				$why = __( 'Repère « commande d\'essai » retiré. L\'OrderPlaced ne part pas : la commande a plus de 6 jours (Meta refuse les événements trop anciens).', 'famma-core' );
				break;
			case 'queue_failed':
				$why = __( 'Repère « commande d\'essai » retiré. La mise en file de l\'OrderPlaced a échoué (tâches planifiées) : utilisez « Renvoyer » dans le rapprochement de FAMMA → Mesure.', 'famma-core' );
				break;
			default:
				$why = __( 'Repère « commande d\'essai » retiré. L\'OrderPlaced ne part pas : la commande n\'a pas été passée sur le site, ou elle est annulée, refusée ou en échec.', 'famma-core' );
		}
		return $why . ' ' . $none . ' ' . $next;
	}

	/**
	 * Un événement de cette commande a-t-il déjà été accepté par Meta ?
	 *
	 * @param \WC_Order $order Commande.
	 * @return bool
	 */
	private function already_accepted( \WC_Order $order ): bool {
		foreach ( self::SENT_MARKERS as $marker ) {
			if ( $order->get_meta( $marker ) ) {
				return true;
			}
		}
		return false;
	}
}
