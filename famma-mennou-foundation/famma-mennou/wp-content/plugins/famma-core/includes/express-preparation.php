<?php
/**
 * Authoritative WooCommerce cart recap before express checkout confirmation.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/** Session-bound preparations never create orders or fabricate payable totals. */
final class Express_Preparation {
	/** Shared endpoint instance.
	 *
	 * @var self|null
	 */
	private static ?self $i = null;

	/** Register preparation endpoint. @return self */
	public static function instance(): self {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'wc_ajax_famma_express_prepare', array( self::$i, 'handle' ) );
			add_action( 'wc_ajax_famma_express_prepare', array( Anti_Abuse::instance(), 'throttle_express' ), 1 );
			add_action( 'wc_ajax_famma_express_cart', array( self::$i, 'edit' ) );
			add_action( 'wc_ajax_famma_express_cart', array( Anti_Abuse::instance(), 'throttle_cart_edit' ), 1 );
		}
		return self::$i;
	}

	/** Validate nonce before any cart mutation. @return bool */
	public static function guard(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
			wp_send_json(
				array(
					'result'     => 'failure',
					'famma_code' => 'session_missing',
				)
			);
			return false;
		}
		$nonce = self::posted( 'woocommerce-process-checkout-nonce' );
		if ( ! wp_verify_nonce( $nonce, 'woocommerce-process_checkout' ) ) {
			wp_send_json(
				array(
					'result'      => 'failure',
					'famma_token' => 'expired',
				)
			);
			return false;
		}
		return true;
	}

	/** Read a scalar after endpoint nonce validation.
	 *
	 * @param string $key Field.
	 * @return string
	 */
	public static function posted( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- all public endpoint callers run guard() first.
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
	}

	/** Prepare the real cart and return the exact recap and acknowledged tracking. */
	public function handle(): void {
		if ( ! self::guard() ) {
			return;
		}
		$id         = self::posted( 'famma_preparation_id' );
		$product_id = absint( self::posted( 'famma_product_id' ) );
		$quantity   = (float) wc_stock_amount( self::posted( 'famma_quantity' ) );
		$product    = wc_get_product( $product_id );
		if ( ! Express_Idempotency::valid_id( $id ) || $quantity < 1 || ! $product instanceof \WC_Product || $product->is_type( array( 'variable', 'variation' ) ) || 'publish' !== get_post_status( $product_id ) || ! $product->is_purchasable() || ! $product->is_in_stock() || ! $product->has_enough_stock( $quantity ) ) {
			$this->failure( 'preparation_invalid' );
			return;
		}
		$signature    = hash( 'sha256', (string) wp_json_encode( array( $product_id, $quantity, self::posted( 'billing_state' ), self::posted( 'billing_country' ) ) ) );
		$preparations = (array) WC()->session->get( 'famma_express_preparations', array() );
		$this->customer_location();
		if ( ! $this->recalculate() ) {
			$this->failure( 'cart_rejected' );
			return;
		}
		if ( isset( $preparations[ $id ] ) ) {
			if ( $signature !== $preparations[ $id ]['signature'] ) {
				$this->failure( 'preparation_conflict' );
				return;
			}
			if ( self::cart_hash() !== $preparations[ $id ]['response']['cart_hash'] ) {
				$this->failure( 'cart_changed' );
				return;
			}
			wp_send_json( $preparations[ $id ]['response'] );
			return;
		}
		$previous = 0.0;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( (int) $item['product_id'] === $product_id && empty( $item['variation_id'] ) ) {
				$previous += (float) $item['quantity'];
			}
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- native Woo validation also applies when the quantity already matches.
		if ( $previous === $quantity && ! apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity ) ) {
			$this->failure( 'cart_rejected' );
			return;
		}
		if ( $previous !== $quantity && ! Express_Checkout::instance()->put_in_cart( $product_id, $quantity ) ) {
			$this->failure( 'cart_rejected' );
			return;
		}
		// Revalidate unchanged quantities too; preparation is not a stock reservation.
		WC()->cart->check_cart_items();
		if ( wc_notice_count( 'error' ) > 0 ) {
			$this->failure( 'cart_rejected' );
			return;
		}
		$this->recalculate();
		$delta               = max( 0.0, $quantity - $previous );
		$sku                 = (string) $product->get_sku();
		$content_id          = '' !== $sku ? $sku : (string) $product_id;
		$params              = array(
			'content_ids'  => array( $content_id ),
			'content_type' => 'product',
			'currency'     => get_woocommerce_currency(),
			'value'        => round( $delta * (float) wc_get_price_to_display( $product ), wc_get_price_decimals() ),
			'contents'     => array(
				array(
					'id'       => $content_id,
					'quantity' => $delta,
				),
			),
		);
		$response            = array(
			'result'         => 'success',
			'preparation_id' => $id,
			'cart_hash'      => self::cart_hash(),
			'summary'        => self::summary(),
			'tracking'       => array(
				'add_to_cart'       => array(
					'emit'     => $delta > 0,
					'event_id' => 'famma_add_' . $id,
					'params'   => $params,
				),
				'initiate_checkout' => array(
					'event_id' => 'famma_checkout_' . $id,
					'params'   => self::cart_tracking(),
				),
			),
		);
		$preparations[ $id ] = array(
			'signature' => $signature,
			'response'  => $response,
		);
		WC()->session->set( 'famma_express_preparations', array_slice( $preparations, -10, null, true ) );
		WC()->session->save_data();
		wp_send_json( $response );
	}

	/**
	 * Retire une ligne ou change sa quantité, depuis le récapitulatif.
	 *
	 * Même garde que la préparation : jeton, session, empreinte du panier que le
	 * client vient de voir (un panier modifié ailleurs est refusé), puis les
	 * contrôles natifs de WooCommerce (stock, quantité maximale, plafond
	 * anti-abus). L'empreinte et la signature de la préparation sont mises à jour :
	 * la confirmation finale porte sur le panier tel qu'il est maintenant.
	 */
	public function edit(): void {
		if ( ! self::guard() ) {
			return;
		}
		$id           = self::posted( 'famma_preparation_id' );
		$preparations = (array) WC()->session->get( 'famma_express_preparations', array() );
		$entry        = $preparations[ $id ] ?? null;
		$this->customer_location();
		if ( ! Express_Idempotency::valid_id( $id ) || ! is_array( $entry ) || ! $this->recalculate() ) {
			$this->failure( 'preparation_missing' );
			return;
		}
		if ( self::posted( 'famma_cart_hash' ) !== self::cart_hash() || self::cart_hash() !== $entry['response']['cart_hash'] ) {
			$this->failure( 'cart_changed' );
			return;
		}
		$key    = self::posted( 'famma_cart_key' );
		$action = self::posted( 'famma_cart_action' );
		$item   = WC()->cart->get_cart_item( $key );
		if ( empty( $item ) || ! in_array( $action, array( 'set', 'remove' ), true ) ) {
			$this->failure( 'cart_rejected' );
			return;
		}
		if ( 'remove' === $action ) {
			WC()->cart->remove_cart_item( $key );
		} else {
			$quantity = (float) wc_stock_amount( self::posted( 'famma_cart_quantity' ) );
			$max      = self::max_quantity( $item['data'] );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- native Woo validation, as WC_Form_Handler::update_cart_action() applies it.
			if ( $quantity < 1 || ( $max > 0 && $quantity > $max ) || ! $item['data']->has_enough_stock( $quantity ) || ! apply_filters( 'woocommerce_update_cart_validation', true, $key, $item, $quantity ) ) {
				$this->failure( 'cart_rejected' );
				return;
			}
			WC()->cart->set_quantity( $key, $quantity );
		}
		if ( ! $this->recalculate() ) {
			$this->failure( 'cart_rejected' );
			return;
		}
		WC()->cart->check_cart_items();
		if ( wc_notice_count( 'error' ) > 0 ) {
			$this->failure( 'cart_rejected' );
			return;
		}
		$summary                        = self::summary();
		$hash                           = self::cart_hash();
		$entry['response']['summary']   = $summary;
		$entry['response']['cart_hash'] = $hash;
		$entry['signature']             = hash( 'sha256', (string) wp_json_encode( array( absint( self::posted( 'famma_product_id' ) ), (float) wc_stock_amount( self::posted( 'famma_quantity' ) ), self::posted( 'billing_state' ), self::posted( 'billing_country' ) ) ) );
		$preparations[ $id ]            = $entry;
		WC()->session->set( 'famma_express_preparations', $preparations );
		WC()->session->save_data();
		wp_send_json(
			array(
				'result'         => 'success',
				'preparation_id' => $id,
				'cart_hash'      => $hash,
				'summary'        => $summary,
			)
		);
	}

	/** Require the prepared cart version and location before checkout reservation. @return bool */
	public function verify(): bool {
		$id           = self::posted( 'famma_preparation_id' );
		$preparations = (array) WC()->session->get( 'famma_express_preparations', array() );
		$entry        = $preparations[ $id ] ?? null;
		$signature    = hash( 'sha256', (string) wp_json_encode( array( absint( self::posted( 'famma_product_id' ) ), (float) wc_stock_amount( self::posted( 'famma_quantity' ) ), self::posted( 'billing_state' ), self::posted( 'billing_country' ) ) ) );
		$this->customer_location();
		if ( ! $this->recalculate() ) {
			$this->failure( 'cart_rejected' );
			return false;
		}
		if ( ! $entry || $signature !== $entry['signature'] ) {
			$this->failure( 'preparation_missing' );
			return false;
		}
		$hash = self::cart_hash();
		if ( self::posted( 'famma_cart_hash' ) !== $entry['response']['cart_hash'] || $hash !== $entry['response']['cart_hash'] ) {
			$this->failure( 'cart_changed' );
			return false;
		}
		return true;
	}

	/** Apply posted Tunisian governorate for authoritative Woo delivery/tax calculations. */
	private function customer_location(): void {
		$country = self::posted( 'billing_country' );
		$state   = self::posted( 'billing_state' );
		if ( 'TN' === $country && isset( WC()->countries->get_states( 'TN' )[ $state ] ) ) {
			WC()->customer->set_billing_country( 'TN' );
			WC()->customer->set_shipping_country( 'TN' );
			WC()->customer->set_billing_state( $state );
			WC()->customer->set_shipping_state( $state );
		}
	}

	/** Refresh current product prices before Woo recalculates the quote. @return bool */
	private function recalculate(): bool {
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$product = wc_get_product( ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : (int) $item['product_id'] );
			if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() ) {
				return false;
			}
			WC()->cart->cart_contents[ $key ]['data'] = $product;
		}
		WC()->cart->calculate_totals();
		return true;
	}

	/**
	 * Quantité maximale d'une ligne (0 : illimitée), plafond anti-abus compris.
	 *
	 * @param \WC_Product $product Produit de la ligne.
	 * @return float
	 */
	private static function max_quantity( \WC_Product $product ): float {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- native Woo filter; Anti_Abuse caps it.
		$max = apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product );
		return is_numeric( $max ) && (float) $max > 0 ? (float) $max : 0.0;
	}

	/**
	 * Montant formaté, ou chaîne vide quand il est nul : la ligne n'est pas affichée.
	 *
	 * @param float $amount Montant.
	 * @return string
	 */
	private static function amount_html( float $amount ): string {
		return 0.0 === round( $amount, wc_get_price_decimals() ) ? '' : wc_price( $amount );
	}

	/** Return complete customer-facing Woo recap, never internal delivery costs. @return array */
	public static function summary(): array {
		$items = array();
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$items[] = array(
				'key'             => (string) $key,
				'product_id'      => (int) $item['product_id'],
				'name'            => $item['data']->get_name(),
				'quantity'        => (float) $item['quantity'],
				'max'             => self::max_quantity( $item['data'] ),
				'line_total'      => (float) $item['line_total'] + (float) $item['line_tax'],
				'line_total_html' => wc_price( (float) $item['line_total'] + (float) $item['line_tax'] ),
			);
		}
		$cart     = WC()->cart;
		$discount = self::amount_html( (float) $cart->get_discount_total() + (float) $cart->get_discount_tax() );
		$shipping = self::amount_html( (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax() );
		$fees     = self::amount_html( (float) $cart->get_fee_total() + (float) $cart->get_fee_tax() );
		$tax      = self::amount_html( (float) $cart->get_total_tax() );
		// Un sous-total identique au total n'apprend rien : il n'apparaît que s'il y a une ligne en plus.
		$has_extra = '' !== $discount || '' !== $shipping || '' !== $fees || '' !== $tax;
		return array(
			'items'         => $items,
			'currency'      => get_woocommerce_currency(),
			'total'         => (float) $cart->get_total( 'edit' ),
			'total_html'    => $cart->get_total(),
			'subtotal_html' => $has_extra ? $cart->get_cart_subtotal() : '',
			'discount_html' => $discount,
			'shipping_html' => $shipping,
			'fees_html'     => $fees,
			'tax_html'      => $tax,
			'item_count'    => $cart->get_cart_contents_count(),
			'coupons'       => $cart->get_applied_coupons(),
		);
	}

	/** Hash all payable lines, totals, coupons and delivery location. @return string */
	public static function cart_hash(): string {
		return hash( 'sha256', (string) wp_json_encode( array( self::summary(), WC()->cart->get_cart_hash(), WC()->customer->get_shipping_country(), WC()->customer->get_shipping_state() ) ) );
	}

	/** Full cart Meta parameters for acknowledged InitiateCheckout. @return array */
	private static function cart_tracking(): array {
		$contents = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$sku        = (string) $item['data']->get_sku();
			$contents[] = array(
				'id'       => '' !== $sku ? $sku : (string) $item['product_id'],
				'quantity' => (float) $item['quantity'],
			);
		}
		return array(
			'content_ids'  => array_column( $contents, 'id' ),
			'contents'     => $contents,
			'content_type' => 'product',
			'currency'     => get_woocommerce_currency(),
			'value'        => (float) WC()->cart->get_total( 'edit' ),
		);
	}

	/** Safe refusal with a fresh authoritative recap.
	 *
	 * @param string $code Reason.
	 */
	private function failure( string $code ): void {
		wp_send_json(
			array(
				'result'     => 'failure',
				'famma_code' => $code,
				'messages'   => wc_print_notices( true ),
				'summary'    => self::summary(),
				'cart_hash'  => self::cart_hash(),
			)
		);
	}
}
