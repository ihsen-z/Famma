<?php
/**
 * Customer-facing texts, all editable in WooCommerce > Settings > Upsell
 * One Click, each with an optional second-language line (for example French
 * and Arabic). An empty setting falls back to the translated default.
 *
 * @package upsell-one-click
 */

namespace UpsellOneClick;

defined( 'ABSPATH' ) || exit;

/**
 * Text registry and money formatting.
 */
final class Texts {

	/** Non-breaking space, kept between an amount and its currency. */
	public const NBSP = "\u{00A0}";

	/**
	 * Keys of the texts that accept a second-language line.
	 *
	 * @var array<int, string>
	 */
	public const WITH_ALT = array( 'kicker', 'more', 'accept', 'added', 'closed', 'retry' );

	/**
	 * Default texts, translated. `{price}` and `{total}` are replaced at
	 * display time.
	 *
	 * @return array<string, array{label: string, default: string}>
	 */
	public static function fields(): array {
		return array(
			'kicker'      => array(
				'label'   => __( 'Offer label', 'upsell-one-click' ),
				'default' => __( 'Special offer', 'upsell-one-click' ),
			),
			'title'       => array(
				'label'   => __( 'Default block title', 'upsell-one-click' ),
				'default' => __( 'Add it to your parcel', 'upsell-one-click' ),
			),
			'text'        => array(
				'label'   => __( 'Default block text', 'upsell-one-click' ),
				'default' => __( 'Same parcel, paid on delivery. Available until your order is confirmed.', 'upsell-one-click' ),
			),
			'accept'      => array(
				'label'   => __( 'Accept button ({price} = special price)', 'upsell-one-click' ),
				'default' => __( 'Yes, add it · +{price}', 'upsell-one-click' ),
			),
			'decline'     => array(
				'label'   => __( 'Decline button', 'upsell-one-click' ),
				'default' => __( 'No thanks', 'upsell-one-click' ),
			),
			'more'        => array(
				'label'   => __( 'Quick view link', 'upsell-one-click' ),
				'default' => __( 'Discover the product', 'upsell-one-click' ),
			),
			'back'        => array(
				'label'   => __( 'Quick view: back button', 'upsell-one-click' ),
				'default' => __( 'Back', 'upsell-one-click' ),
			),
			'usage_title' => array(
				'label'   => __( 'Quick view: usage heading', 'upsell-one-click' ),
				'default' => __( 'How to use it', 'upsell-one-click' ),
			),
			'trust_1'     => array(
				'label'   => __( 'Quick view: reassurance 1 (payment)', 'upsell-one-click' ),
				'default' => __( 'Cash on delivery', 'upsell-one-click' ),
			),
			'trust_2'     => array(
				'label'   => __( 'Quick view: reassurance 2 (parcel)', 'upsell-one-click' ),
				'default' => __( 'In the same parcel', 'upsell-one-click' ),
			),
			'trust_3'     => array(
				'label'   => __( 'Quick view: reassurance 3 (delivery, empty = hidden)', 'upsell-one-click' ),
				'default' => '',
			),
			'note'        => array(
				'label'   => __( 'Quick view: note', 'upsell-one-click' ),
				'default' => __( 'You can still change your mind when we call to confirm your order.', 'upsell-one-click' ),
			),
			'added'       => array(
				'label'   => __( 'Message after adding ({total} = new total)', 'upsell-one-click' ),
				'default' => __( 'Added to your order. New total to pay on delivery: {total}.', 'upsell-one-click' ),
			),
			'closed'      => array(
				'label'   => __( 'Message when the offer has closed', 'upsell-one-click' ),
				'default' => __( 'This offer is no longer available: your order is already being prepared.', 'upsell-one-click' ),
			),
			'retry'       => array(
				'label'   => __( 'Message when a click failed', 'upsell-one-click' ),
				'default' => __( 'The click did not go through. Please try the button again.', 'upsell-one-click' ),
			),
		);
	}

	/**
	 * Text to display: saved setting, otherwise translated default.
	 *
	 * @param string                $key  Text key.
	 * @param array<string, string> $vars Placeholders, e.g. `{price}`.
	 * @return string
	 */
	public static function get( string $key, array $vars = array() ): string {
		$saved = trim( (string) get_option( 'upsell_oc_text_' . $key, '' ) );
		if ( '' === $saved ) {
			$fields = self::fields();
			$saved  = $fields[ $key ]['default'] ?? '';
		}
		return strtr( $saved, $vars );
	}

	/**
	 * Optional second-language line of a text.
	 *
	 * @param string $key Text key.
	 * @return string
	 */
	public static function alt( string $key ): string {
		return in_array( $key, self::WITH_ALT, true ) ? trim( (string) get_option( 'upsell_oc_text_' . $key . '_alt', '' ) ) : '';
	}

	/**
	 * Plain-text amount with the store currency, without useless zeros
	 * ("22 DT", "€19.5", "1 250 DT"), using the store separators, glued to
	 * its currency by a non-breaking space.
	 *
	 * @param float $value Amount.
	 * @return string
	 */
	public static function money( float $value ): string {
		$decimals = max( 0, (int) wc_get_price_decimals() );
		$sep      = (string) wc_get_price_decimal_separator();
		$number   = number_format( $value, $decimals, $sep, (string) wc_get_price_thousand_separator() );
		if ( $decimals > 0 && '' !== $sep ) {
			// "22,000" → "22", "22,500" → "22,5".
			$number = (string) preg_replace( '/(?:' . preg_quote( $sep, '/' ) . '0+|(' . preg_quote( $sep, '/' ) . '\d*?)0+)$/', '$1', $number );
		}
		$symbol = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );

		switch ( (string) get_option( 'woocommerce_currency_pos', 'left' ) ) {
			case 'right':
				return $number . $symbol;
			case 'left_space':
				return $symbol . self::NBSP . $number;
			case 'right_space':
				return $number . self::NBSP . $symbol;
			default:
				return $symbol . $number;
		}
	}
}
