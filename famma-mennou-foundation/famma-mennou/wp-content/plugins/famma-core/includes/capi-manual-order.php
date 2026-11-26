<?php
/**
 * OrderPlaced des commandes saisies à la main dans l'administration (L6, décision Q24 du 26/11).
 *
 * Une commande reçue par Messenger, WhatsApp, Instagram ou téléphone et saisie
 * dans l'écran de commande de WooCommerce (`created_via = admin`, posé par
 * WooCommerce au premier enregistrement d'un brouillon) envoie OrderPlaced,
 * sur le modèle des paniers récupérés (`Capi_Recovered_Cart`) :
 *
 * - repérée au seul enregistrement par l'écran de commande
 *   (`woocommerce_process_shop_order_meta`, nonce et droit vérifiés par
 *   WooCommerce) : marque `_famma_admin_entry`. Les commandes créées par
 *   l'application ou l'API REST (`created_via = rest-api`) ou par un autre
 *   code ne passent pas par cet écran : exclues. Le bouton « Créer la
 *   commande » des saisies reste un panier récupéré ;
 * - seulement les commandes créées après la mise en service
 *   (`famma_capi_manual_orderplaced_since`) : une ancienne commande rouverte
 *   n'envoie rien ;
 * - départ 15 minutes après la mise en file (`Capi_Recovered_Cart::plan()`,
 *   méta `_famma_orderplaced_due_at`, lue par la surveillance), tout relu au
 *   départ : interrupteurs, essai (case de la fiche), statut, article, 6 jours ;
 *   `event_time` = création ;
 * - `action_source` : `chat` pour un canal de messagerie (Messenger,
 *   WhatsApp, Instagram), sinon `phone_call` (filtre `famma_capi_action_source`) ;
 * - `user_data` : téléphone, nom, ville… de la commande ; ni fbc ni IP (pas
 *   de visite) ; external_id seulement si `_famma_visitor_id` existe.
 *
 * Interrupteur FAMMA → Mesure (`Capi_Recovered_Cart_Admin`), activé par défaut.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Règle et mise en file des commandes saisies dans l'administration.
 */
final class Capi_Manual_Order {

	/**
	 * Interrupteur (FAMMA → Mesure), activé par défaut.
	 */
	public const OPTION = 'famma_meta_manual_orderplaced_enabled';

	/**
	 * Date de mise en service : seules les commandes créées après comptent.
	 */
	public const SINCE_OPTION = 'famma_capi_manual_orderplaced_since';

	/**
	 * Méta : commande enregistrée par l'écran de commande de l'administration.
	 */
	public const ENTRY_META = '_famma_admin_entry';

	/**
	 * Canaux de messagerie (`Order_Channel`) envoyés en `chat` ; filtre `famma_capi_chat_channels`.
	 */
	public const CHAT_CHANNELS = array( 'messenger', 'whatsapp', 'instagram' );

	/**
	 * Statuts sans OrderPlaced en plus de ceux de `Capi` (brouillon de l'écran).
	 */
	private const DRAFTS = array( 'auto-draft', 'draft', 'checkout-draft' );

	/**
	 * Instance partagée.
	 *
	 * @var Capi_Manual_Order|null
	 */
	private static ?Capi_Manual_Order $i = null;

	/**
	 * Renvoie l'instance partagée, crochets posés au premier appel.
	 *
	 * @return Capi_Manual_Order
	 */
	public static function instance(): Capi_Manual_Order {
		if ( null === self::$i ) {
			self::$i = new self();
			// Après le statut (40), le canal (50) et la case d'essai (35) de l'écran de commande.
			add_action( 'woocommerce_process_shop_order_meta', array( self::$i, 'admin_saved' ), 60 );
			add_action( 'woocommerce_order_status_changed', array( self::$i, 'status_changed' ), 25, 3 );
			add_filter( 'famma_capi_action_source', array( self::$i, 'action_source' ), 5, 2 );
			add_action( 'admin_init', array( self::class, 'start' ) );
		}
		return self::$i;
	}

	/**
	 * Pose la date de mise en service au premier passage dans l'administration.
	 *
	 * @return void
	 */
	public static function start(): void {
		if ( (int) get_option( self::SINCE_OPTION, 0 ) <= 0 ) {
			update_option( self::SINCE_OPTION, time(), false );
		}
	}

	/**
	 * L'interrupteur est-il actif ?
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return (bool) get_option( self::OPTION, true );
	}

	/**
	 * Commande saisie à la main dans l'écran de commande (hors panier récupéré) ?
	 *
	 * @param \WC_Order $order Commande.
	 * @return bool
	 */
	public static function is_manual( \WC_Order $order ): bool {
		return 'admin' === $order->get_created_via()
			&& (bool) $order->get_meta( self::ENTRY_META )
			&& ! Capi_Recovered_Cart::is_recovered( $order );
	}

	/**
	 * Motif d'exclusion d'une commande saisie à la main, vide si OrderPlaced peut partir.
	 *
	 * @param \WC_Order $order Commande saisie à la main.
	 * @return string `manual_order:disabled`, `manual_order:no_product`, `status:<brouillon>` ou vide.
	 */
	public static function block_reason( \WC_Order $order ): string {
		if ( ! self::enabled() ) {
			return 'manual_order:disabled';
		}
		if ( $order->has_status( self::DRAFTS ) ) {
			return 'status:' . $order->get_status();
		}
		return array() === $order->get_items() ? 'manual_order:no_product' : '';
	}

	/**
	 * Enregistrement par l'écran de commande : marque la commande, puis tente la mise en file.
	 *
	 * @param int $order_id Commande.
	 * @return void
	 */
	public function admin_saved( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || 'admin' !== $order->get_created_via() || Capi_Recovered_Cart::is_recovered( $order ) ) {
			return;
		}
		if ( ! $order->get_meta( self::ENTRY_META ) ) {
			self::start();
			$created = $order->get_date_created();
			if ( null === $created || $created->getTimestamp() < (int) get_option( self::SINCE_OPTION, 0 ) ) {
				return;
			}
			$order->update_meta_data( self::ENTRY_META, 1 );
			$order->save();
		}
		self::maybe_queue( $order );
	}

	/**
	 * Changement de statut hors de l'écran (liste des commandes) : nouvelle tentative.
	 *
	 * @param int    $order_id Commande.
	 * @param string $from     Ancien statut.
	 * @param string $to       Nouveau statut.
	 * @return void
	 */
	public function status_changed( int $order_id, string $from, string $to ): void {
		unset( $from, $to );
		$order = wc_get_order( $order_id );
		if ( $order instanceof \WC_Order && self::is_manual( $order ) ) {
			self::maybe_queue( $order );
		}
	}

	/**
	 * Met OrderPlaced en file une seule fois, sans rien écrire au journal tant
	 * que la commande n'est pas prête (brouillon, sans article, essai, annulée).
	 *
	 * @param \WC_Order $order Commande saisie à la main.
	 * @return string Issue (`waiting`, `already`, ou celle de `Capi`).
	 */
	public static function maybe_queue( \WC_Order $order ): string {
		if ( $order->get_meta( '_famma_orderplaced_checkout_accepted' ) || $order->get_meta( '_famma_orderplaced_sent' ) ) {
			return 'already';
		}
		if ( ! self::is_manual( $order ) || '' !== self::block_reason( $order ) || Capi::is_test_order( $order )
			|| $order->has_status( array( 'failed', 'cancelled', 'trash', 'refused', 'returned', 'failed-delivery' ) ) ) {
			return 'waiting';
		}
		return Capi::instance()->requeue_order_placed( $order );
	}

	/**
	 * Filtre `famma_capi_action_source` : `chat` (canal de messagerie) ou `phone_call`.
	 *
	 * @param mixed     $source Source proposée.
	 * @param \WC_Order $order  Commande.
	 * @return mixed
	 */
	public function action_source( $source, $order ) {
		if ( ! $order instanceof \WC_Order || ! self::is_manual( $order ) ) {
			return $source;
		}
		$chat = (array) apply_filters( 'famma_capi_chat_channels', self::CHAT_CHANNELS );

		return in_array( Order_Channel::of( $order ), $chat, true ) ? 'chat' : 'phone_call';
	}
}
