<?php
/**
 * Offer results over the last 30 days, per triggering product.
 *
 * Computed from the orders (`_upsell_oc_*` meta), cached ten minutes.
 *
 * @package upsell-one-click
 */

namespace UpsellOneClick;

defined( 'ABSPATH' ) || exit;

/**
 * Counts shown, viewed, accepted and declined offers.
 */
final class Stats {

	/**
	 * Results of a triggering product.
	 *
	 * @param int $source_id Triggering product.
	 * @return array{shown: int, viewed: int, accepted: int, viewed_accepted: int, declined: int, amount: float}
	 */
	public static function get( int $source_id ): array {
		$key    = 'upsell_oc_stats_' . $source_id;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$out = array(
			'shown'           => 0,
			'viewed'          => 0,
			'accepted'        => 0,
			'viewed_accepted' => 0,
			'declined'        => 0,
			'amount'          => 0.0,
		);

		$ids = wc_get_orders(
			array(
				'limit'        => 500,
				'return'       => 'ids',
				'date_created' => '>' . ( time() - 30 * DAY_IN_SECONDS ),
				'meta_key'     => Offer::ORDER_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded to 500 orders of 30 days, cached.
				'meta_value'   => (string) $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- same.
			)
		);

		foreach ( (array) $ids as $id ) {
			$order = wc_get_order( $id );
			/**
			 * Filters whether an order counts in the results (e.g. exclude
			 * test orders).
			 *
			 * @param bool           $count Count it.
			 * @param \WC_Order|bool $order Order.
			 */
			if ( ! $order instanceof \WC_Order || ! apply_filters( 'upsell_oc_count_order_in_stats', true, $order ) ) {
				continue;
			}
			if ( $order->get_meta( Offer::ORDER_SHOWN ) ) {
				++$out['shown'];
			}
			$viewed = (bool) $order->get_meta( Offer::ORDER_VIEWED );
			if ( $viewed ) {
				++$out['viewed'];
			}
			if ( $order->get_meta( Offer::ORDER_ACCEPTED ) ) {
				++$out['accepted'];
				$out['amount'] += (float) $order->get_meta( Offer::ORDER_AMOUNT );
				if ( $viewed ) {
					++$out['viewed_accepted'];
				}
			}
			if ( $order->get_meta( Offer::ORDER_DECLINED ) ) {
				++$out['declined'];
			}
		}

		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Clears the cached results of a triggering product.
	 *
	 * @param int $source_id Triggering product.
	 * @return void
	 */
	public static function flush( int $source_id ): void {
		delete_transient( 'upsell_oc_stats_' . $source_id );
	}
}
