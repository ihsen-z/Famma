<?php
/**
 * Authoritative WooCommerce cart recap before express checkout confirmation.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/express-cart-isolation.php';

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

	/**
	 * Prépare le devis isolé (le seul produit de la fiche) et le suivi acquitté.
	 *
	 * Le panier est isolé (voir `Express_Cart_Isolation`) le temps du calcul,
	 * puis remis tel quel avant l'enregistrement de la session : `prepare` ne
	 * laisse aucune trace au panier. `other_items` indique combien d'articles
	 * restent au panier, hors commande express.
	 */
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
		$signature    = self::signature();
		$preparations = (array) WC()->session->get( 'famma_express_preparations', array() );
		$isolation    = Express_Cart_Isolation::instance();
		$this->customer_location();
		if ( ! $isolation->isolate( $product_id, $quantity ) || ! $this->recalculate() ) {
			$this->failure( 'cart_rejected' );
			return;
		}
		// Revalidate unchanged quantities too; preparation is not a stock reservation.
		WC()->cart->check_cart_items();
		if ( wc_notice_count( 'error' ) > 0 || ! $this->recalculate() ) {
			$this->failure( 'cart_rejected' );
			return;
		}
		$hash = self::cart_hash();
		if ( isset( $preparations[ $id ] ) ) {
			if ( $signature !== $preparations[ $id ]['signature'] ) {
				$this->failure( 'preparation_conflict' );
				return;
			}
			if ( $hash !== $preparations[ $id ]['response']['cart_hash'] ) {
				$this->failure( 'cart_changed' );
				return;
			}
			$response                = $preparations[ $id ]['response'];
			$response['other_items'] = $isolation->other_items();
			$isolation->restore();
			WC()->session->save_data();
			wp_send_json( $response );
			return;
		}
		$previous   = $isolation->previous_quantity();
		$delta      = max( 0.0, $quantity - $previous );
		$sku        = (string) $product->get_sku();
		$content_id = '' !== $sku ? $sku : (string) $product_id;
		$params     = array(
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
		$response   = array(
			'result'         => 'success',
			'preparation_id' => $id,
			'cart_hash'      => $hash,
			'summary'        => self::summary(),
			'other_items'    => $isolation->other_items(),
			'tracking'       => array(
				'add_to_cart'       => array(
					'emit'     => $delta > 0,
					'event_id' => 'famma_add_' . $id,
					'params'   => $params,
					'previous' => $previous,
					'unit'     => (float) wc_get_price_to_display( $product ),
				),
				'initiate_checkout' => array(
					'event_id' => 'famma_checkout_' . $id,
					'params'   => self::cart_tracking(),
				),
			),
		);
		$isolation->restore();
		$preparations[ $id ] = array(
			'signature' => $signature,
			'response'  => $response,
		);
		WC()->session->set( 'famma_express_preparations', array_slice( $preparations, -10, null, true ) );
		WC()->session->save_data();
		wp_send_json( $response );
	}

	/**
	 * Ancienne modification de ligne depuis le récapitulatif, désormais refusée.
	 *
	 * La commande express ne porte plus que sur le produit de la fiche, à la
	 * quantité du formulaire (`famma_quantity`) ; les autres lignes du panier
	 * ne sont plus commandées. Modifier une ligne ici n'aurait donc plus
	 * d'effet sur la commande. Le point d'entrée reste enregistré pour qu'un
	 * ancien script servi par le cache reçoive un refus explicite, avec message.
	 */
	public function edit(): void {
		if ( ! self::guard() ) {
			return;
		}
		$this->failure( 'cart_edit_unsupported' );
	}

	/**
	 * Exige le devis préparé : même produit, quantité, gouvernorat et même total.
	 *
	 * Isole le panier comme `prepare` : l'empreinte comparée est celle du devis
	 * isolé. L'isolation reste active pendant `process_checkout()` et se termine
	 * à `shutdown` (voir `Express_Cart_Isolation`).
	 *
	 * @return bool
	 */
	public function verify(): bool {
		$id           = self::posted( 'famma_preparation_id' );
		$preparations = (array) WC()->session->get( 'famma_express_preparations', array() );
		$entry        = $preparations[ $id ] ?? null;
		if ( ! is_array( $entry ) || self::signature() !== ( $entry['signature'] ?? '' ) ) {
			$this->failure( 'preparation_missing' );
			return false;
		}
		$this->customer_location();
		$isolated = Express_Cart_Isolation::instance()->isolate( absint( self::posted( 'famma_product_id' ) ), (float) wc_stock_amount( self::posted( 'famma_quantity' ) ) );
		if ( ! $isolated || ! $this->recalculate() ) {
			$this->failure( 'cart_rejected' );
			return false;
		}
		$hash = self::cart_hash();
		if ( self::posted( 'famma_cart_hash' ) !== $entry['response']['cart_hash'] || $hash !== $entry['response']['cart_hash'] ) {
			$this->failure( 'cart_changed' );
			return false;
		}
		return true;
	}

	/** Digest of the posted intent: product, quantity and delivery location. @return string */
	private static function signature(): string {
		return hash( 'sha256', (string) wp_json_encode( array( absint( self::posted( 'famma_product_id' ) ), (float) wc_stock_amount( self::posted( 'famma_quantity' ) ), self::posted( 'billing_state' ), self::posted( 'billing_country' ) ) ) );
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

	/**
	 * Refus sûr : toujours un message, et le devis isolé s'il a été calculé.
	 *
	 * Le panier d'origine est restauré avant la réponse.
	 *
	 * @param string $code Raison.
	 */
	private function failure( string $code ): void {
		if ( 0 === wc_notice_count( 'error' ) ) {
			wc_add_notice( self::message( $code ), 'error' );
		}
		$response  = array(
			'result'     => 'failure',
			'famma_code' => $code,
			'messages'   => wc_print_notices( true ),
		);
		$isolation = Express_Cart_Isolation::instance();
		if ( $isolation->is_active() ) {
			$response['summary']   = self::summary();
			$response['cart_hash'] = self::cart_hash();
		}
		$isolation->restore();
		wp_send_json( $response );
	}

	/**
	 * Message d'un refus sans avis WooCommerce : jamais d'échec muet.
	 *
	 * @param string $code Raison.
	 * @return string
	 */
	private static function message( string $code ): string {
		switch ( $code ) {
			case 'preparation_invalid':
			case 'cart_rejected':
				return __( 'Ce produit ne peut pas être commandé dans cette quantité pour le moment. Vérifiez la quantité et réessayez. / المنتوج هذا ما ينجمش يتطلب بالكمية هاذي توا.', 'famma-core' );
			case 'preparation_conflict':
			case 'preparation_missing':
			case 'cart_changed':
				return __( 'Votre commande a changé depuis sa préparation. Vérifiez le total puis confirmez à nouveau. / الطلبية تبدلت، ثبت في المجموع وأكد من جديد.', 'famma-core' );
			case 'cart_edit_unsupported':
				return __( 'Modifiez la quantité directement dans le formulaire de commande. / بدل الكمية في الفورمولار.', 'famma-core' );
			default:
				return __( 'The order could not go through. Please check the form and try again.', 'famma-core' );
		}
	}
}
