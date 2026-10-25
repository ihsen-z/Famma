<?php
/**
 * OrderPlaced côté navigateur (lot 5, S3-a), coupé par défaut.
 *
 * La CAPI envoie déjà OrderPlaced côté serveur, avec l'eventID
 * `famma_orderplaced_<id>`. L'envoi navigateur n'existe que derrière
 * l'interrupteur « OrderPlaced côté navigateur » (`Config::meta_orderplaced_browser()`,
 * faux par défaut, E6) : à moins de 5 minutes d'écart, Meta garde la version
 * navigateur, moins riche que la CAPI. Quand il est actif, il porte le MÊME
 * eventID que le serveur et ne part qu'une fois par session de navigation
 * (clé sessionStorage = eventID), depuis :
 *
 * - la page de confirmation, avec une clé de commande valide ;
 * - la réponse de succès du formulaire express, jointe par
 *   `woocommerce_payment_successful_result` en priorité 10010, après Capi
 *   (999) et Express_Idempotency (10000, qui pose `famma_request_id`).
 *
 * Rien ne part pour un visiteur interne (S1) ni sans Pixel configuré.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Émet OrderPlaced dans le navigateur, une seule fois, avec l'eventID de la CAPI.
 */
final class Browser_Order_Placed {

	/**
	 * Priorité du complément OrderPlaced sur la réponse de l'express : après
	 * Capi (999) et Express_Idempotency (10000), qui pose `famma_request_id`.
	 */
	private const EXPRESS_RESULT_PRIORITY = 10010;

	/**
	 * Instance partagée.
	 *
	 * @var Browser_Order_Placed|null
	 */
	private static ?Browser_Order_Placed $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Browser_Order_Placed
	 */
	public static function instance(): Browser_Order_Placed {
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
		add_filter( 'woocommerce_payment_successful_result', array( $this, 'express_order_placed' ), self::EXPRESS_RESULT_PRIORITY, 2 );
	}

	/**
	 * Identifiant d'OrderPlaced, le même côté navigateur et côté CAPI.
	 *
	 * @param \WC_Order $order Commande.
	 * @return string
	 */
	public static function event_id( \WC_Order $order ): string {
		return 'famma_orderplaced_' . $order->get_id();
	}

	/**
	 * Paramètres d'OrderPlaced navigateur : le bloc de la CAPI, même profil.
	 *
	 * @param \WC_Order $order Commande acceptée.
	 * @return array<string, mixed>
	 */
	public static function params( \WC_Order $order ): array {
		$params                = array(
			'currency'     => $order->get_currency(),
			'value'        => round( (float) $order->get_total(), 3 ),
			'order_id'     => (string) $order->get_id(),
			'content_type' => 'product',
		) + Capi_Product_Data::for_order( $order );
		$params['famma_stage'] = 'order_placed';

		return Tracking_Events::profile_params( $params, 'OrderPlaced' );
	}

	/**
	 * Commande de la page de confirmation, si sa clé est valide.
	 *
	 * La clé de commande de l'URL est l'autorisation de WooCommerce sur cette
	 * page : sans elle, rien n'est lu ni imprimé.
	 *
	 * @return \WC_Order|null
	 */
	private static function received_order(): ?\WC_Order {
		$order_id = absint( get_query_var( 'order-received' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule ; la clé de commande est l'autorisation de WooCommerce sur cette page.
		$key   = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$order = $order_id > 0 ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof \WC_Order || '' === $key || ! $order->key_is_valid( $key ) ) {
			return null;
		}
		if ( 'checkout' !== $order->get_created_via() || $order->has_status( array( 'failed', 'cancelled' ) ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Script d'OrderPlaced de la page de confirmation, une seule fois (S3-a).
	 *
	 * Joint par `Tracking_Events::enqueue_events()`, qui ne l'imprime que
	 * Pixel configuré et visiteur non interne. Un rechargement ou un retour
	 * arrière ne le renvoie pas (clé sessionStorage = eventID).
	 *
	 * @return string Chaîne vide si l'interrupteur est coupé ou la commande non autorisée.
	 */
	public static function confirmation_script(): string {
		if ( ! Config::instance()->meta_orderplaced_browser() || ! is_order_received_page() ) {
			return '';
		}

		$order = self::received_order();

		if ( null === $order ) {
			return '';
		}

		$event_id = self::event_id( $order );

		return Tracking_Events::once(
			$event_id,
			'fbq("trackCustom","OrderPlaced",' . wp_json_encode( self::params( $order ) ) . ',{eventID:' . wp_json_encode( $event_id ) . '});'
		);
	}

	/**
	 * Joint l'OrderPlaced navigateur à la réponse de succès de l'express.
	 *
	 * Le script l'émet avant la redirection, avec la même clé sessionStorage
	 * que la page de confirmation : l'événement ne part qu'une fois. Rien n'est
	 * ajouté hors de l'express, sans l'interrupteur, ou pour un visiteur interne.
	 *
	 * @param array $result   Réponse de succès de WooCommerce.
	 * @param int   $order_id Commande créée.
	 * @return array
	 */
	public function express_order_placed( array $result, int $order_id ): array {
		if ( 'success' !== ( $result['result'] ?? '' ) || empty( $result['famma_request_id'] ) ) {
			return $result;
		}
		if ( ! Config::instance()->meta_orderplaced_browser() || ! Tracking_Events::pixel_active() ) {
			return $result;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return $result;
		}

		$result['famma_order_placed'] = array(
			'event_id' => self::event_id( $order ),
			'params'   => self::params( $order ),
		);

		return $result;
	}
}
