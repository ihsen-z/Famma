<?php
/**
 * Thank-you page: offer block and one-click add to the order.
 *
 * The button is a real form posted back to the thank-you page itself: it
 * works without JavaScript, in the same WooCommerce session as the order (a
 * guest's nonce depends on it). Three guards before any write: the order
 * key, the nonce and an open order status.
 *
 * @package upsell-one-click
 */

namespace UpsellOneClick;

defined( 'ABSPATH' ) || exit;

/**
 * Displays the offer and handles the click.
 */
final class Thankyou {

	/** Form field: requested action (`accept`, `decline` or `view`). */
	public const FIELD_ACTION = 'upsell_oc_action';

	/** Form field: nonce. */
	public const FIELD_NONCE = 'upsell_oc_nonce';

	/** URL parameter carrying the result after the redirect. */
	private const RESULT = 'uoc';

	/**
	 * Shared instance.
	 *
	 * @var Thankyou|null
	 */
	private static ?Thankyou $i = null;

	/**
	 * Returns the shared instance, registering the hooks on first call.
	 *
	 * @return Thankyou
	 */
	public static function instance(): Thankyou {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'template_redirect', array( self::$i, 'handle' ), 5 );
			// Right under the "Order received" title: the only place visible
			// without scrolling on a phone.
			add_action( 'woocommerce_before_thankyou', array( self::$i, 'render' ), 10 );
			add_action( 'wp_enqueue_scripts', array( self::$i, 'assets' ) );
		}
		return self::$i;
	}

	/**
	 * Nonce action, specific to the order.
	 *
	 * @param int $order_id Order.
	 * @return string
	 */
	public static function nonce_action( int $order_id ): string {
		return 'upsell_oc_offer_' . $order_id;
	}

	/**
	 * Order of the thank-you page, when the key in the URL is right.
	 *
	 * @return \WC_Order|null
	 */
	private function current_order(): ?\WC_Order {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return null;
		}

		$order_id = absint( get_query_var( 'order-received' ) );
		$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- order key read only, nothing written here.
		$order    = $order_id > 0 ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof \WC_Order || '' === $key || ! hash_equals( $order->get_order_key(), $key ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Handles the click, then redirects to the thank-you page (PRG).
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST[ self::FIELD_ACTION ] ) ) {
			return;
		}

		$order = $this->current_order();
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$nonce  = isset( $_POST[ self::FIELD_NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD_NONCE ] ) ) : '';
		$action = sanitize_key( wp_unslash( $_POST[ self::FIELD_ACTION ] ) );
		$valid  = (bool) wp_verify_nonce( $nonce, self::nonce_action( $order->get_id() ) );

		// Quick view opened: background measurement, no redirect, no body.
		if ( 'view' === $action ) {
			if ( $valid ) {
				$this->record( $order, 'viewed' );
			}
			status_header( 204 );
			exit;
		}

		if ( ! $valid ) {
			$this->back( $order, 'expired' );
		}

		// A double click must not add anything twice: short lock per order.
		$lock = 'upsell_oc_lock_' . $order->get_id();
		if ( ! add_option( $lock, (string) time(), '', false ) ) {
			// A lock older than 30 s comes from an interrupted run: take it over.
			if ( time() - (int) get_option( $lock, 0 ) < 30 ) {
				$this->back( $order, 'busy' );
			}
			update_option( $lock, (string) time(), false );
		}

		$result = 'decline' === $action ? $this->record( $order, 'declined' ) : $this->accept( $order );
		delete_option( $lock );
		$this->back( $order, $result );
	}

	/**
	 * Adds the offered product to the order.
	 *
	 * @param \WC_Order $order Order.
	 * @return string Result for the URL.
	 */
	private function accept( \WC_Order $order ): string {
		$order = wc_get_order( $order->get_id() );
		if ( ! $order instanceof \WC_Order ) {
			return 'closed';
		}
		if ( $order->get_meta( Offer::ORDER_ACCEPTED ) ) {
			return 'added';
		}

		$offer = Offer::for_order( $order );
		if ( null === $offer ) {
			return 'closed';
		}

		$item_id = $order->add_product(
			$offer['product'],
			1,
			array(
				'subtotal' => $offer['price'],
				'total'    => $offer['price'],
			)
		);
		if ( ! $item_id ) {
			return 'closed';
		}

		$item = $order->get_item( $item_id );
		if ( $item instanceof \WC_Order_Item_Product ) {
			$item->add_meta_data( Offer::ITEM_FLAG, 'yes', true );
			$item->save();
		}

		$order->calculate_totals( false );
		$order->update_meta_data( Offer::ORDER_ACCEPTED, time() );
		$order->update_meta_data( Offer::ORDER_AMOUNT, wc_format_decimal( $offer['price'], wc_get_price_decimals() ) );
		$order->update_meta_data( Offer::ORDER_SOURCE, (string) $offer['source'] );
		$order->update_meta_data( Offer::ORDER_TARGET, (string) $offer['target'] );
		$order->add_order_note(
			sprintf(
				/* translators: 1: product name, 2: special price, 3: new order total. */
				__( 'Upsell One Click: the customer accepted the offer. %1$s added at %2$s. New total: %3$s.', 'upsell-one-click' ),
				$offer['product']->get_name(),
				Texts::money( (float) $offer['price'] ),
				Texts::money( (float) $order->get_total() )
			)
		);
		$order->save();

		Stats::flush( (int) $offer['source'] );
		$this->event( 'accepted', $order, $offer );

		return 'added';
	}

	/**
	 * Records a view of the quick sheet or a refusal.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $what  `viewed` or `declined`.
	 * @return string Result for the URL, `closed` when the offer is over.
	 */
	private function record( \WC_Order $order, string $what ): string {
		$offer = Offer::for_order( $order );
		if ( null === $offer ) {
			return 'closed';
		}
		$key = 'viewed' === $what ? Offer::ORDER_VIEWED : Offer::ORDER_DECLINED;
		if ( ! $order->get_meta( $key ) ) {
			$order->update_meta_data( $key, time() );
			$order->update_meta_data( Offer::ORDER_SOURCE, (string) $offer['source'] );
			$order->save();
			Stats::flush( (int) $offer['source'] );
			$this->event( $what, $order, $offer );
		}
		return $what;
	}

	/**
	 * Lets other code react (tracking, CRM…).
	 *
	 * @param string    $event `shown`, `viewed`, `accepted` or `declined`.
	 * @param \WC_Order $order Order.
	 * @param array     $offer Offer.
	 * @return void
	 */
	private function event( string $event, \WC_Order $order, array $offer ): void {
		/**
		 * Fires when an offer is shown, viewed, accepted or declined.
		 *
		 * @param string    $event Event name.
		 * @param \WC_Order $order Order.
		 * @param array     $offer Offer.
		 */
		do_action( 'upsell_oc_offer_event', $event, $order, $offer );
	}

	/**
	 * Back to the thank-you page with the result.
	 *
	 * @param \WC_Order $order  Order.
	 * @param string    $result Result.
	 * @return never
	 */
	private function back( \WC_Order $order, string $result ): never {
		wp_safe_redirect( add_query_arg( self::RESULT, $result, $order->get_checkout_order_received_url() ) );
		exit;
	}

	/**
	 * Stylesheet and quick view script, on the thank-you page only. The
	 * script is deferred: it does not delay the page.
	 *
	 * @return void
	 */
	public function assets(): void {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return;
		}
		wp_enqueue_style( 'upsell-one-click', UPSELL_OC_URL . 'assets/css/upsell-one-click.css', array(), UPSELL_OC_VERSION . '.' . (string) filemtime( UPSELL_OC_DIR . 'assets/css/upsell-one-click.css' ) );
		wp_enqueue_script(
			'upsell-one-click',
			UPSELL_OC_URL . 'assets/js/upsell-one-click.js',
			array(),
			UPSELL_OC_VERSION . '.' . (string) filemtime( UPSELL_OC_DIR . 'assets/js/upsell-one-click.js' ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}

	/**
	 * Displays the block (or the result message) on the thank-you page.
	 *
	 * @param mixed $order_id Displayed order.
	 * @return void
	 */
	public function render( $order_id ): void {
		$order = $this->current_order();
		if ( ! $order instanceof \WC_Order || $order->get_id() !== absint( $order_id ) ) {
			return;
		}

		$result = isset( $_GET[ self::RESULT ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- status message after the redirect only.

		if ( 'added' === $result && $order->get_meta( Offer::ORDER_ACCEPTED ) ) {
			Block_View::message( 'added', 'ok', array( '{total}' => Texts::money( (float) $order->get_total() ) ) );
			return;
		}
		if ( 'closed' === $result ) {
			Block_View::message( 'closed', 'info' );
			return;
		}

		$offer = Offer::for_order( $order );
		if ( null === $offer ) {
			return;
		}

		if ( ! $order->get_meta( Offer::ORDER_SHOWN ) ) {
			$order->update_meta_data( Offer::ORDER_SHOWN, time() );
			$order->update_meta_data( Offer::ORDER_SOURCE, (string) $offer['source'] );
			$order->update_meta_data( Offer::ORDER_TARGET, (string) $offer['target'] );
			$order->save();
			$this->event( 'shown', $order, $offer );
		}

		if ( in_array( $result, array( 'expired', 'busy' ), true ) ) {
			Block_View::message( 'retry', 'info' );
		}

		Block_View::render( $order, $offer );
	}
}
