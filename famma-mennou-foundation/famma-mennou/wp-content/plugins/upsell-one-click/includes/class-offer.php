<?php
/**
 * Offer rules: product settings, store settings and eligibility.
 *
 * A purchased product can propose ONE other product at a special price,
 * right after checkout, while the order is still in an "open" status (by
 * default: Processing or On hold, paid on delivery).
 *
 * @package upsell-one-click
 */

namespace UpsellOneClick;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the settings and decides whether an offer can be shown.
 */
final class Offer {

	/** Product: offer enabled (`yes` / `no`). */
	public const META_ENABLED = '_upsell_oc_enabled';

	/** Product: offered product ID. */
	public const META_PRODUCT = '_upsell_oc_product_id';

	/** Product: special price of the offered product. */
	public const META_PRICE = '_upsell_oc_price';

	/** Product: block title (optional). */
	public const META_TITLE = '_upsell_oc_title';

	/** Product: block text (optional). */
	public const META_TEXT = '_upsell_oc_text';

	/** Product: quick view sheet (`no` hides it). */
	public const META_SHEET = '_upsell_oc_sheet';

	/** Product: key points, one per line, "main | second language". */
	public const META_POINTS = '_upsell_oc_points';

	/** Product: short usage text. */
	public const META_USAGE = '_upsell_oc_usage';

	/** Order: first time the offer was shown. */
	public const ORDER_SHOWN = '_upsell_oc_shown_at';

	/** Order: first time the quick view sheet was opened. */
	public const ORDER_VIEWED = '_upsell_oc_viewed_at';

	/** Order: time the offer was accepted. */
	public const ORDER_ACCEPTED = '_upsell_oc_accepted_at';

	/** Order: time the offer was declined. */
	public const ORDER_DECLINED = '_upsell_oc_declined_at';

	/** Order: amount added by the offer. */
	public const ORDER_AMOUNT = '_upsell_oc_amount';

	/** Order: product that triggered the offer. */
	public const ORDER_SOURCE = '_upsell_oc_source_id';

	/** Order: product added by the offer. */
	public const ORDER_TARGET = '_upsell_oc_target_id';

	/** Order item: marks the line added by the offer. */
	public const ITEM_FLAG = '_upsell_oc_item';

	/** Maximum number of key points. */
	public const MAX_POINTS = 3;

	/** Maximum number of photos in the quick view sheet. */
	public const MAX_PHOTOS = 12;

	/**
	 * Order statuses (without `wc-`) in which the offer stays open.
	 *
	 * @return array<int, string>
	 */
	public static function open_statuses(): array {
		$raw = get_option( 'upsell_oc_open_statuses', array( 'wc-processing', 'wc-on-hold' ) );
		$out = array();
		foreach ( (array) $raw as $status ) {
			$out[] = 'wc-' === substr( (string) $status, 0, 3 ) ? substr( (string) $status, 3 ) : (string) $status;
		}
		return array_values( array_filter( $out ) );
	}

	/**
	 * Payment methods allowed (empty array: all). Cash on delivery by
	 * default: an order already paid online must not change its total.
	 *
	 * @return array<int, string>
	 */
	public static function payment_methods(): array {
		return array_values( array_filter( array_map( 'strval', (array) get_option( 'upsell_oc_payment_methods', array( 'cod' ) ) ) ) );
	}

	/**
	 * Product settings, or null when no offer is enabled.
	 *
	 * @param \WC_Product $product Purchased product.
	 * @return array{source: int, target: int, price: float, title: string, text: string, sheet: bool, points: string, usage: string}|null
	 */
	public static function for_product( \WC_Product $product ): ?array {
		if ( 'yes' !== (string) $product->get_meta( self::META_ENABLED ) ) {
			return null;
		}

		$target = absint( $product->get_meta( self::META_PRODUCT ) );
		$price  = (float) $product->get_meta( self::META_PRICE );

		if ( $target <= 0 || $target === $product->get_id() || $price <= 0 ) {
			return null;
		}

		return array(
			'source' => $product->get_id(),
			'target' => $target,
			'price'  => round( $price, wc_get_price_decimals() ),
			'title'  => (string) $product->get_meta( self::META_TITLE ),
			'text'   => (string) $product->get_meta( self::META_TEXT ),
			'sheet'  => 'no' !== (string) $product->get_meta( self::META_SHEET ),
			'points' => (string) $product->get_meta( self::META_POINTS ),
			'usage'  => (string) $product->get_meta( self::META_USAGE ),
		);
	}

	/**
	 * Offer to show for an order, or null.
	 *
	 * The order is in an open status and paid with an allowed method, the
	 * offer was neither accepted nor declined, an ordered product carries an
	 * offer, and the offered product is published, purchasable, in stock
	 * and not already in the order.
	 *
	 * @param \WC_Order $order Order.
	 * @return array{source: int, target: int, price: float, title: string, text: string, sheet: bool, points: string, usage: string, product: \WC_Product}|null
	 */
	public static function for_order( \WC_Order $order ): ?array {
		if ( ! $order->has_status( self::open_statuses() ) ) {
			return null;
		}
		$methods = self::payment_methods();
		if ( array() !== $methods && ! in_array( (string) $order->get_payment_method(), $methods, true ) ) {
			return null;
		}
		if ( $order->get_meta( self::ORDER_ACCEPTED ) || $order->get_meta( self::ORDER_DECLINED ) ) {
			return null;
		}

		$in_order = array();
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product ) {
				$in_order[] = (int) $item->get_product_id();
			}
		}

		$found = null;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			// A variation carries its settings on the parent product.
			$owner = $product->get_parent_id() > 0 ? wc_get_product( $product->get_parent_id() ) : $product;
			if ( ! $owner instanceof \WC_Product ) {
				continue;
			}
			$offer = self::for_product( $owner );
			if ( null === $offer || in_array( $offer['target'], $in_order, true ) ) {
				continue;
			}
			$target = wc_get_product( $offer['target'] );
			if ( ! self::offerable( $target ) ) {
				continue;
			}
			$offer['product'] = $target;
			$found            = $offer;
			break;
		}

		/**
		 * Filters the offer shown for an order (null: no offer).
		 *
		 * @param array|null $found Offer.
		 * @param \WC_Order  $order Order.
		 */
		return apply_filters( 'upsell_oc_offer_for_order', $found, $order );
	}

	/**
	 * Can the offered product be added as it is, in one click?
	 *
	 * Only simple products: a product with options would need a choice.
	 *
	 * @param mixed $product Offered product.
	 * @return bool
	 */
	public static function offerable( $product ): bool {
		$ok = $product instanceof \WC_Product
			&& $product->is_type( 'simple' )
			&& 'publish' === $product->get_status()
			&& $product->is_purchasable()
			&& $product->is_in_stock();

		/**
		 * Filters whether a product can be offered in one click.
		 *
		 * @param bool  $ok      Offerable.
		 * @param mixed $product Product.
		 */
		return (bool) apply_filters( 'upsell_oc_offerable', $ok, $product );
	}

	/**
	 * Key points: at most three, each with an optional second-language
	 * line after "|".
	 *
	 * @param string $raw Text typed in the product tab, one point per line.
	 * @return array<int, array{main: string, alt: string}>
	 */
	public static function points( string $raw ): array {
		$out = array();
		// Line breaks only: without the u flag, "\R" would cut Arabic letters
		// whose UTF-8 bytes contain 0x85.
		foreach ( (array) preg_split( '/\r\n|\r|\n/u', $raw ) as $line ) {
			$parts = array_map( 'trim', explode( '|', (string) $line, 2 ) );
			if ( '' === $parts[0] ) {
				continue;
			}
			$out[] = array(
				'main' => $parts[0],
				'alt'  => $parts[1] ?? '',
			);
			if ( count( $out ) >= self::MAX_POINTS ) {
				break;
			}
		}
		return $out;
	}
}
