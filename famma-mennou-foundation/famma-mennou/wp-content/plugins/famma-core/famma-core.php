<?php
/**
 * Plugin Name:       FAMMA Core
 * Description:       Core business logic for FAMMA MENNOU — custom COD order workflow, cost×3 pricing with margin tracking, Tunisia checkout rules, WhatsApp, and env-driven tracking (Meta/TikTok/GA4). Keeps customisation out of WP/WooCommerce core and the theme.
 * Version:           0.2.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            FAMMA
 * Text Domain:       famma-core
 * WC requires at least: 8.0
 *
 * @package Famma\Core
 *
 * Single-file build: all classes live here so there is no cross-file autoload
 * to misfire, and no way to declare the same class twice. This is deliberate —
 * see ClaudeMemo.md rule 7 and docs/ARCHITECTURE.md. The phpcs ruleset excludes
 * the one-structure-per-file sniffs for this reason.
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

define( 'FAMMA_CORE_VERSION', '0.2.0' );
define( 'FAMMA_CORE_FILE', __FILE__ );

/**
 * Reads configuration from env-fed constants and WordPress options.
 *
 * Constants are defined in wp-config.php by docker-compose from .env.
 * An empty value means "feature disabled" — never a fallback to a guess.
 */
final class Config {

	/**
	 * Singleton instance.
	 *
	 * @var Config|null
	 */
	private static ?Config $i = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Config
	 */
	public static function instance(): Config {
		return self::$i ??= new self();
	}

	/**
	 * Reads a constant, falling back to a default when unset or empty.
	 *
	 * @param string $k Constant name.
	 * @param string $d Default value.
	 * @return string
	 */
	private function c( string $k, string $d = '' ): string {
		return defined( $k ) && '' !== (string) constant( $k ) ? (string) constant( $k ) : $d;
	}

	/**
	 * WhatsApp number, digits only.
	 *
	 * @return string
	 */
	public function whatsapp_number(): string {
		return preg_replace( '/\D+/', '', $this->c( 'FAMMA_WHATSAPP_NUMBER' ) );
	}

	/**
	 * Meta pixel identifier (safe to expose to the browser).
	 *
	 * @return string
	 */
	public function meta_pixel_id(): string {
		return $this->c( 'FAMMA_META_PIXEL_ID' );
	}

	/**
	 * Meta Conversions API token. Server-side only — never enqueued (§17).
	 *
	 * @return string
	 */
	public function meta_access_token(): string {
		return $this->c( 'FAMMA_META_ACCESS_TOKEN' );
	}

	/**
	 * Meta test event code, used when validating CAPI events.
	 *
	 * @return string
	 */
	public function meta_test_event_code(): string {
		return $this->c( 'FAMMA_META_TEST_EVENT_CODE' );
	}

	/**
	 * TikTok pixel identifier.
	 *
	 * @return string
	 */
	public function tiktok_pixel_id(): string {
		return $this->c( 'FAMMA_TIKTOK_PIXEL_ID' );
	}

	/**
	 * GA4 measurement identifier.
	 *
	 * @return string
	 */
	public function ga4_measurement_id(): string {
		return $this->c( 'FAMMA_GA4_MEASUREMENT_ID' );
	}

	/**
	 * Google site verification token.
	 *
	 * @return string
	 */
	public function google_site_verification(): string {
		return $this->c( 'FAMMA_GOOGLE_SITE_VERIFICATION' );
	}

	/**
	 * Internal shipping cost in TND — never charged to the customer (§3, §4).
	 *
	 * @return float
	 */
	public function internal_shipping_cost(): float {
		return (float) get_option( 'famma_internal_shipping_cost', 7.0 );
	}

	/**
	 * Default selling-price multiplier applied to cost price (§3).
	 *
	 * @return float
	 */
	public function price_multiplier(): float {
		return (float) get_option( 'famma_price_multiplier', 3.0 );
	}
}

/**
 * Custom COD order workflow and the payment rule that goes with it.
 *
 * See docs/ORDER-WORKFLOW.md for why three filters are needed to make
 * "paid on delivery" actually work.
 */
final class Order_Statuses {

	/**
	 * Singleton instance.
	 *
	 * @var Order_Statuses|null
	 */
	private static ?Order_Statuses $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Order_Statuses
	 */
	public static function instance(): Order_Statuses {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * The custom statuses, slug => translated label.
	 *
	 * @return array<string,string>
	 */
	private function statuses(): array {
		return array(
			'pending-confirm' => _x( 'Pending confirmation', 'Order status', 'famma-core' ),
			'confirmed'       => _x( 'Confirmed', 'Order status', 'famma-core' ),
			'shipped'         => _x( 'Shipped', 'Order status', 'famma-core' ),
			'delivered'       => _x( 'Delivered', 'Order status', 'famma-core' ),
			'failed-delivery' => _x( 'Failed delivery', 'Order status', 'famma-core' ),
			'returned'        => _x( 'Returned', 'Order status', 'famma-core' ),
			'refused'         => _x( 'Refused', 'Order status', 'famma-core' ),
		);
	}

	/**
	 * Registers WordPress and WooCommerce hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'init', array( $this, 'register_post_statuses' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_to_wc_list' ) );
		add_action( 'woocommerce_order_status_delivered', array( $this, 'mark_cod_paid_on_delivery' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( $this, 'reporting_statuses' ) );

		/*
		 * Without these three filters payment_complete() does nothing at all
		 * from a custom status: WooCommerce only performs the payment
		 * bookkeeping from on-hold / pending / failed / cancelled. Verified
		 * broken at runtime on WooCommerce 11.0.1 — the order stayed
		 * "delivered" but with no paid date, and revenue was never counted.
		 * This is non-negotiable business rule 4 (§12).
		 */
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'allow_complete_from_delivered' ) );
		add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'paid_statuses' ) );

		/*
		 * Priority 99, not 10: WC_Gateway_COD registers this same filter and
		 * forces "completed" for any cash-on-delivery order. Gateways are
		 * instantiated after plugins_loaded, so at equal priority its callback
		 * would run after ours and the order would leave "delivered"
		 * immediately. Observed at runtime.
		 */
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'stay_delivered_when_paid' ), 99, 3 );
	}

	/**
	 * Registers the custom statuses as post statuses.
	 *
	 * @return void
	 */
	public function register_post_statuses(): void {
		foreach ( $this->statuses() as $slug => $label ) {
			register_post_status(
				'wc-' . $slug,
				array(
					'label'                     => $label,
					'public'                    => false,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,

					/*
					 * Built by hand rather than with _n_noop(): the label is
					 * dynamic, and translation functions must receive literal
					 * strings. The label itself is already translated above.
					 */
					'label_count'               => array(
						'singular' => $label . ' <span class="count">(%s)</span>',
						'plural'   => $label . ' <span class="count">(%s)</span>',
						'context'  => null,
						'domain'   => 'famma-core',
					),
				)
			);
		}
	}

	/**
	 * Inserts the custom statuses into the WooCommerce status list, in workflow order.
	 *
	 * @param array<string,string> $s Existing statuses.
	 * @return array<string,string>
	 */
	public function add_to_wc_list( array $s ): array {
		$out = array();
		$st  = $this->statuses();
		foreach ( $s as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'wc-pending' === $k ) {
				$out['wc-pending-confirm'] = $st['pending-confirm'];
				$out['wc-confirmed']       = $st['confirmed'];
			}
			if ( 'wc-processing' === $k ) {
				$out['wc-shipped']         = $st['shipped'];
				$out['wc-delivered']       = $st['delivered'];
				$out['wc-failed-delivery'] = $st['failed-delivery'];
				$out['wc-returned']        = $st['returned'];
				$out['wc-refused']         = $st['refused'];
			}
		}
		return $out;
	}

	/**
	 * Collects the cash payment when the order reaches "delivered" (§12).
	 *
	 * @param int $order_id Order identifier.
	 * @return void
	 */
	public function mark_cod_paid_on_delivery( int $order_id ): void {
		$o = wc_get_order( $order_id );
		if ( ! $o instanceof \WC_Order ) {
			return;
		}

		/*
		 * The guard uses a marker of our own, not is_paid() and not date_paid.
		 * Both are already true on entry: WC_Abstract_Order::maybe_set_date_paid()
		 * runs during save(), hence before this transition, and treats
		 * "delivered" as the payment-complete status because of our
		 * stay_delivered_when_paid() filter. Relying on either would return
		 * early, payment_complete() would never run, the
		 * woocommerce_payment_complete action would never fire — and stock
		 * would never be decremented.
		 */
		if ( 'yes' === $o->get_meta( '_famma_cod_paid' ) ) {
			return;
		}

		$o->payment_complete();
		$o->update_meta_data( '_famma_cod_paid', 'yes' );
		$o->add_order_note( __( 'COD payment collected on delivery.', 'famma-core' ) );
		$o->save();
	}

	/**
	 * Allows payment_complete() to run from the "delivered" status.
	 *
	 * @param string[] $statuses Statuses payment completion is allowed from.
	 * @return string[]
	 */
	public function allow_complete_from_delivered( array $statuses ): array {
		$statuses[] = 'delivered';
		return $statuses;
	}

	/**
	 * Keeps the order in "delivered" instead of jumping to processing/completed.
	 *
	 * @param string         $status   Default target status.
	 * @param int            $order_id Order identifier.
	 * @param \WC_Order|null $order    Order concerned.
	 * @return string
	 */
	public function stay_delivered_when_paid( string $status, int $order_id, $order = null ): string {
		if ( $order instanceof \WC_Order && $order->has_status( 'delivered' ) ) {
			return 'delivered';
		}
		return $status;
	}

	/**
	 * Makes "delivered" count as paid.
	 *
	 * Without this is_paid() stays false and revenue reports silently ignore
	 * every delivered order.
	 *
	 * @param string[] $statuses Statuses considered paid.
	 * @return string[]
	 */
	public function paid_statuses( array $statuses ): array {
		$statuses[] = 'delivered';
		return $statuses;
	}

	/**
	 * Includes delivered orders in WooCommerce reports.
	 *
	 * @param string[] $s Statuses included in reports.
	 * @return string[]
	 */
	public function reporting_statuses( array $s ): array {
		foreach ( array( 'delivered', 'completed', 'processing' ) as $x ) {
			if ( ! in_array( $x, $s, true ) ) {
				$s[] = $x;
			}
		}
		return $s;
	}
}

/**
 * Internal product economics: cost, margin, and the cost×3 default price (§3).
 *
 * None of these figures is ever shown to a customer.
 */
final class Product_Financials {

	/**
	 * Singleton instance.
	 *
	 * @var Product_Financials|null
	 */
	private static ?Product_Financials $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Product_Financials
	 */
	public static function instance(): Product_Financials {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'woocommerce_product_options_pricing', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_fields' ) );
	}

	/**
	 * Renders the private FAMMA financials panel on the product screen.
	 *
	 * @return void
	 */
	public function render_fields(): void {
		echo '<div class="options_group">';
		echo '<p class="form-field"><strong>' . esc_html__( 'FAMMA financials (internal — not shown to customers)', 'famma-core' ) . '</strong></p>';
		woocommerce_wp_text_input(
			array(
				'id'          => '_famma_cost_price',
				'label'       => __( 'Cost price (TND)', 'famma-core' ),
				'desc_tip'    => true,
				'description' => __( 'Leave selling price empty to auto-set to cost × 3.', 'famma-core' ),
				'data_type'   => 'price',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_famma_shipping_cost',
				'label'       => __( 'Internal shipping cost (TND)', 'famma-core' ),
				'desc_tip'    => true,
				'description' => __( 'Included in price; customer pays 0.', 'famma-core' ),
				'data_type'   => 'price',
				'placeholder' => (string) Config::instance()->internal_shipping_cost(),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'        => '_famma_handling_cost',
				'label'     => __( 'Handling cost (TND)', 'famma-core' ),
				'data_type' => 'price',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'        => '_famma_acquisition_cost',
				'label'     => __( 'Acquisition cost / order (TND)', 'famma-core' ),
				'data_type' => 'price',
			)
		);

		global $product_object;
		if ( $product_object instanceof \WC_Product ) {
			$m = $this->margin( $product_object );
			echo '<p class="form-field"><span>' . esc_html(
				sprintf(
					/* translators: 1: net profit in TND, 2: margin as a percentage */
					__( 'Estimated net margin: %1$s TND (%2$s%%)', 'famma-core' ),
					number_format( $m['profit'], 2 ),
					number_format( $m['percent'], 1 )
				)
			) . '</span></p>';
		}
		echo '</div>';
	}

	/**
	 * Persists the financial fields and applies the cost×3 default (§3).
	 *
	 * Nonce and capability are verified by WooCommerce before this hook fires
	 * (WC_Meta_Box_Product_Data::save checks woocommerce_meta_nonce); the
	 * capability check below is kept as a defence in depth.
	 *
	 * @param \WC_Product $product Product being saved.
	 * @return void
	 */
	public function save_fields( \WC_Product $product ): void {
		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}
		foreach ( array( '_famma_cost_price', '_famma_shipping_cost', '_famma_handling_cost', '_famma_acquisition_cost' ) as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook.
			if ( isset( $_POST[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
				$val = wc_clean( wp_unslash( $_POST[ $key ] ) );
				$product->update_meta_data( $key, '' === $val ? '' : wc_format_decimal( $val ) );
			}
		}

		$cost = (float) $product->get_meta( '_famma_cost_price' );
		if ( $cost > 0 && '' === (string) $product->get_regular_price() ) {
			$product->set_regular_price( wc_format_decimal( $cost * Config::instance()->price_multiplier() ) );
		}
	}

	/**
	 * Computes the estimated margin for a product.
	 *
	 * @param \WC_Product $product Product to evaluate.
	 * @return array{price:float,total_cost:float,profit:float,percent:float}
	 */
	public function margin( \WC_Product $product ): array {
		$price  = (float) $product->get_regular_price();
		$cost   = (float) $product->get_meta( '_famma_cost_price' );
		$sm     = $product->get_meta( '_famma_shipping_cost' );
		$ship   = '' === (string) $sm ? Config::instance()->internal_shipping_cost() : (float) $sm;
		$tot    = $cost + $ship + (float) $product->get_meta( '_famma_handling_cost' ) + (float) $product->get_meta( '_famma_acquisition_cost' );
		$profit = $price - $tot;

		return array(
			'price'      => $price,
			'total_cost' => $tot,
			'profit'     => $profit,
			'percent'    => $price > 0 ? ( $profit / $price ) * 100 : 0.0,
		);
	}
}

/**
 * Shipping economics: 0 TND charged, 7 TND tracked internally (§3, §4).
 */
final class Shipping {

	/**
	 * Singleton instance.
	 *
	 * @var Shipping|null
	 */
	private static ?Shipping $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Shipping
	 */
	public static function instance(): Shipping {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers WooCommerce hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_filter( 'woocommerce_general_settings', array( $this, 'settings' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'record_internal_shipping' ), 20 );
	}

	/**
	 * Adds the FAMMA business-rule block to WooCommerce general settings.
	 *
	 * @param array<int,array<string,mixed>> $s Existing settings.
	 * @return array<int,array<string,mixed>>
	 */
	public function settings( array $s ): array {
		return array_merge(
			$s,
			array(
				array(
					'title' => __( 'FAMMA business rules', 'famma-core' ),
					'type'  => 'title',
					'id'    => 'famma_business_rules',
				),
				array(
					'title'             => __( 'Default price multiplier', 'famma-core' ),
					'desc'              => __( 'Selling price = cost × this (default 3). Manual prices override.', 'famma-core' ),
					'id'                => 'famma_price_multiplier',
					'type'              => 'number',
					'default'           => '3',
					'custom_attributes' => array(
						'step' => '0.1',
						'min'  => '1',
					),
				),
				array(
					'title'             => __( 'Internal shipping cost (TND)', 'famma-core' ),
					'desc'              => __( 'Included in price; customer pays 0. Margin only.', 'famma-core' ),
					'id'                => 'famma_internal_shipping_cost',
					'type'              => 'number',
					'default'           => '7',
					'custom_attributes' => array(
						'step' => '0.5',
						'min'  => '0',
					),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'famma_business_rules',
				),
			)
		);
	}

	/**
	 * Stamps the internal shipping cost on the order for later margin analysis.
	 *
	 * @param \WC_Order $order Order being created.
	 * @return void
	 */
	public function record_internal_shipping( \WC_Order $order ): void {
		$order->update_meta_data( '_famma_internal_shipping_cost', Config::instance()->internal_shipping_cost() );
	}
}

/**
 * Tunisia-specific checkout rules: governorates and phone validation (§13, §47).
 */
final class Tunisia {

	/**
	 * Singleton instance.
	 *
	 * @var Tunisia|null
	 */
	private static ?Tunisia $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Tunisia
	 */
	public static function instance(): Tunisia {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers WooCommerce hooks.
	 *
	 * These target the classic (shortcode) checkout, which is the deliberate
	 * choice recorded in docs/DECISIONS.md, ADR-003.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_filter( 'woocommerce_states', array( $this, 'governorates' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_phone' ), 10, 2 );
	}

	/**
	 * Declares the 24 Tunisian governorates as WooCommerce states.
	 *
	 * @param array<string,array<string,string>> $states Existing states by country.
	 * @return array<string,array<string,string>>
	 */
	public function governorates( array $states ): array {
		$states['TN'] = array(
			'TN-11' => 'Tunis',
			'TN-12' => 'Ariana',
			'TN-13' => 'Ben Arous',
			'TN-14' => 'Manouba',
			'TN-21' => 'Nabeul',
			'TN-22' => 'Zaghouan',
			'TN-23' => 'Bizerte',
			'TN-31' => 'Béja',
			'TN-32' => 'Jendouba',
			'TN-33' => 'Le Kef',
			'TN-34' => 'Siliana',
			'TN-41' => 'Kairouan',
			'TN-42' => 'Kasserine',
			'TN-43' => 'Sidi Bouzid',
			'TN-51' => 'Sousse',
			'TN-52' => 'Monastir',
			'TN-53' => 'Mahdia',
			'TN-61' => 'Sfax',
			'TN-71' => 'Gafsa',
			'TN-72' => 'Tozeur',
			'TN-73' => 'Kébili',
			'TN-81' => 'Gabès',
			'TN-82' => 'Médenine',
			'TN-83' => 'Tataouine',
		);
		return $states;
	}

	/**
	 * Trims the checkout to what a Tunisian COD order actually needs (§13).
	 *
	 * @param array<string,array<string,mixed>> $f Checkout fields.
	 * @return array<string,array<string,mixed>>
	 */
	public function checkout_fields( array $f ): array {
		unset(
			$f['billing']['billing_company'],
			$f['billing']['billing_address_2'],
			$f['billing']['billing_postcode'],
			$f['billing']['billing_country']
		);

		if ( isset( $f['billing']['billing_phone'] ) ) {
			$f['billing']['billing_phone']['required']    = true;
			$f['billing']['billing_phone']['priority']    = 25;
			$f['billing']['billing_phone']['placeholder'] = '2X XXX XXX';
			$f['billing']['billing_phone']['label']       = __( 'Phone / الهاتف', 'famma-core' );
		}
		if ( isset( $f['billing']['billing_state'] ) ) {
			$f['billing']['billing_state']['label']    = __( 'Governorate / الولاية', 'famma-core' );
			$f['billing']['billing_state']['required'] = true;
		}
		if ( isset( $f['billing']['billing_city'] ) ) {
			$f['billing']['billing_city']['label'] = __( 'City / Delegation — المدينة', 'famma-core' );
		}
		if ( isset( $f['billing']['billing_address_1'] ) ) {
			$f['billing']['billing_address_1']['label'] = __( 'Address / العنوان', 'famma-core' );
		}
		return $f;
	}

	/**
	 * Rejects anything that is not a plausible Tunisian mobile/landline number.
	 *
	 * Accepts an optional +216 / 00216 prefix, then requires 8 digits.
	 *
	 * @param array<string,mixed> $data   Posted checkout data.
	 * @param \WP_Error           $errors Error collector.
	 * @return void
	 */
	public function validate_phone( array $data, \WP_Error $errors ): void {
		if ( empty( $data['billing_phone'] ) ) {
			return;
		}
		$raw    = preg_replace( '/[\s\-\.]/', '', (string) $data['billing_phone'] );
		$digits = preg_replace( '/^(?:\+?216|00216)/', '', $raw );
		if ( ! preg_match( '/^[2-9]\d{7}$/', $digits ) ) {
			$errors->add(
				'billing_phone_invalid',
				__( 'Please enter a valid Tunisian phone number (8 digits). أدخل رقم هاتف تونسي صحيح.', 'famma-core' )
			);
		}
	}
}

/**
 * Floating WhatsApp button and dynamic wa.me links (§15).
 *
 * Everything is a no-op until FAMMA_WHATSAPP_NUMBER is set: no markup, no CSS.
 */
final class WhatsApp {

	/**
	 * Singleton instance.
	 *
	 * @var WhatsApp|null
	 */
	private static ?WhatsApp $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return WhatsApp
	 */
	public static function instance(): WhatsApp {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers front-end hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'wp_footer', array( $this, 'render_float' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );
	}

	/**
	 * Builds a wa.me link, or an empty string when no number is configured.
	 *
	 * @param string $message Optional prefilled message.
	 * @return string
	 */
	public static function link( string $message = '' ): string {
		$n = Config::instance()->whatsapp_number();
		if ( '' === $n ) {
			return '';
		}
		$u = 'https://wa.me/' . $n;
		if ( '' !== $message ) {
			$u .= '?text=' . rawurlencode( $message );
		}
		return $u;
	}

	/**
	 * Enqueues the button styles, only when the button will actually render.
	 *
	 * @return void
	 */
	public function styles(): void {
		if ( '' === Config::instance()->whatsapp_number() ) {
			return;
		}
		$css = '.famma-wa{position:fixed;inset-inline-end:16px;inset-block-end:16px;z-index:9999;display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;background:#25D366;box-shadow:0 4px 14px rgba(0,0,0,.25);text-decoration:none}.famma-wa svg{width:30px;height:30px;fill:#fff}.famma-wa:focus-visible{outline:3px solid #ff7a00;outline-offset:2px}';
		wp_register_style( 'famma-wa', false, array(), FAMMA_CORE_VERSION );
		wp_enqueue_style( 'famma-wa' );
		wp_add_inline_style( 'famma-wa', $css );
	}

	/**
	 * Prints the floating button in the footer.
	 *
	 * @return void
	 */
	public function render_float(): void {
		$href = self::link( get_bloginfo( 'name' ) . ' — ' . __( "Bonjour, j'ai une question 🙂 / سلام، عندي سؤال", 'famma-core' ) );
		if ( '' === $href ) {
			return;
		}
		printf(
			'<a class="famma-wa" href="%1$s" target="_blank" rel="noopener nofollow" aria-label="%2$s">%3$s</a>',
			esc_url( $href ),
			esc_attr__( 'Contact us on WhatsApp', 'famma-core' ),
			'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.4 5 5.1-1.3A10 10 0 1 0 12 2Zm5.9 14.1c-.2.7-1.4 1.4-2 1.4-.5.1-1.1.1-1.8-.1-.4-.1-1-.3-1.6-.6-2.9-1.2-4.7-4.1-4.9-4.3-.1-.2-1.1-1.4-1.1-2.7 0-1.3.7-1.9.9-2.2.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2.1.4 0 .5l-.4.6c-.1.2-.3.3-.1.6.1.3.6 1 1.3 1.6.9.8 1.6 1 1.9 1.2.2.1.4 0 .5-.1l.6-.7c.2-.2.4-.2.6-.1l1.8.9c.2.1.4.2.4.3.1.1.1.6-.1 1.2Z"/></svg>'
		);
	}
}

/**
 * Analytics wiring: GA4, Meta Pixel, TikTok, UTM persistence (§17–§20).
 *
 * Only public identifiers ever reach the browser. The Meta access token is
 * read server-side and is never enqueued, printed or logged.
 */
final class Tracking {

	/**
	 * Singleton instance.
	 *
	 * @var Tracking|null
	 */
	private static ?Tracking $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Tracking
	 */
	public static function instance(): Tracking {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers front-end and order hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'wp_head', array( $this, 'site_verification' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_utm_on_order' ), 30 );
		add_action( 'woocommerce_order_status_delivered', array( $this, 'fire_purchase_on_delivery' ) );
	}

	/**
	 * Prints the Google site-verification meta tag, when configured.
	 *
	 * @return void
	 */
	public function site_verification(): void {
		$token = Config::instance()->google_site_verification();
		if ( '' === $token ) {
			return;
		}
		printf( '<meta name="google-site-verification" content="%s" />%s', esc_attr( $token ), "\n" );
	}

	/**
	 * Enqueues the analytics snippets that have an identifier configured.
	 *
	 * Everything goes through wp_enqueue_script / wp_add_inline_script rather
	 * than raw <script> echoes, so third parties can dequeue them and the
	 * loading strategy stays under WordPress's control (§23).
	 *
	 * @return void
	 */
	public function enqueue_tracking(): void {
		$cfg = Config::instance();

		$ga = $cfg->ga4_measurement_id();
		if ( '' !== $ga ) {
			wp_enqueue_script(
				'famma-ga4',
				'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga ),
				array(),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- third-party URL, a ver= query would defeat Google's own caching.
				array( 'strategy' => 'async' )
			);
			wp_add_inline_script(
				'famma-ga4',
				'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . wp_json_encode( $ga ) . ');'
			);
		}

		$pixel = $cfg->meta_pixel_id();
		$ttk   = $cfg->tiktok_pixel_id();
		if ( '' === $pixel && '' === $ttk ) {
			return;
		}

		wp_register_script( 'famma-pixels', false, array(), FAMMA_CORE_VERSION, true );
		wp_enqueue_script( 'famma-pixels' );

		if ( '' !== $pixel ) {
			wp_add_inline_script(
				'famma-pixels',
				"!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init'," . wp_json_encode( $pixel ) . ");fbq('track','PageView');"
			);
		}

		if ( '' !== $ttk ) {
			wp_add_inline_script(
				'famma-pixels',
				'!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];ttq.setAndDefer=function(e,n){e[n]=function(){e.push([n].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.load=function(e){var n="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=n;ttq._t=ttq._t||{};ttq._t[e]=+new Date;var o=d.createElement("script");o.type="text/javascript";o.async=!0;o.src=n+"?sdkid="+e;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load(' . wp_json_encode( $ttk ) . ');ttq.page()}(window,document,"ttq");'
			);
		}

		// UTM persistence (§20) — independent of any pixel being configured.
		wp_add_inline_script(
			'famma-pixels',
			'(function(){try{var p=new URLSearchParams(location.search),k=["utm_source","utm_medium","utm_campaign","utm_content","utm_term"],o={},h=false;k.forEach(function(x){var v=p.get(x);if(v){o[x]=v;h=true;}});if(h){document.cookie="famma_utm="+encodeURIComponent(JSON.stringify(o))+";path=/;max-age="+(60*60*24*30)+";SameSite=Lax";}}catch(e){}})();'
		);
	}

	/**
	 * Copies the captured UTM parameters onto the order (§20).
	 *
	 * The cookie is attacker-controllable, so each value is length-capped and
	 * sanitised individually before being stored.
	 *
	 * @param \WC_Order $order Order being created.
	 * @return void
	 */
	public function stamp_utm_on_order( \WC_Order $order ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only attribution cookie, not an action.
		$raw = isset( $_COOKIE['famma_utm'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['famma_utm'] ) ) : '';
		if ( '' === $raw || strlen( $raw ) > 2048 ) {
			return;
		}

		$parsed = json_decode( $raw, true );
		if ( ! is_array( $parsed ) ) {
			return;
		}

		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ) as $k ) {
			if ( ! empty( $parsed[ $k ] ) && is_scalar( $parsed[ $k ] ) ) {
				$order->update_meta_data( '_famma_' . $k, substr( sanitize_text_field( (string) $parsed[ $k ] ), 0, 255 ) );
			}
		}
	}

	/**
	 * Fires the real Purchase event once, when the order is actually delivered (§17).
	 *
	 * A COD order is only revenue at delivery, so this is the only place a
	 * Purchase may be sent. The event id makes browser/server deduplication
	 * possible.
	 *
	 * @param int $order_id Order identifier.
	 * @return void
	 */
	public function fire_purchase_on_delivery( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || $order->get_meta( '_famma_purchase_sent' ) ) {
			return;
		}

		$event_id = 'famma_purchase_' . $order_id;
		$order->update_meta_data( '_famma_purchase_event_id', $event_id );
		$order->update_meta_data( '_famma_purchase_sent', current_time( 'mysql' ) );
		$order->save();

		/**
		 * Fires when a delivered COD order should be reported as a Purchase.
		 *
		 * No listener yet: the Conversions API client is Phase 4. Until then
		 * no Purchase actually reaches Meta.
		 *
		 * @param \WC_Order $order    The delivered order.
		 * @param string    $event_id Deduplication identifier.
		 */
		do_action( 'famma_capi_purchase', $order, $event_id );
	}
}

/**
 * Loads the plugin translations.
 *
 * Hooked on `init` rather than `plugins_loaded`: since WordPress 6.7,
 * triggering translation loading before `init` raises a _doing_it_wrong
 * notice as soon as a .mo file exists.
 *
 * @return void
 */
function load_textdomain(): void {
	load_plugin_textdomain( 'famma-core', false, dirname( plugin_basename( FAMMA_CORE_FILE ) ) . '/languages' );
}

/**
 * Boots every component, once WooCommerce is known to be present.
 *
 * @return void
 */
function boot(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'FAMMA Core requires WooCommerce to be installed and active.', 'famma-core' ) . '</p></div>';
			}
		);
		return;
	}

	Config::instance();
	Order_Statuses::instance();
	Product_Financials::instance();
	Shipping::instance();
	Tunisia::instance();
	Tracking::instance();
	WhatsApp::instance();
}

/**
 * Declares compatibility with WooCommerce High-Performance Order Storage.
 *
 * @return void
 */
function declare_hpos_compatibility(): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FAMMA_CORE_FILE, true );
	}
}

/**
 * Registers the custom statuses on activation and refreshes rewrite rules.
 *
 * @return void
 */
function on_activation(): void {
	if ( class_exists( 'WooCommerce' ) ) {
		Order_Statuses::instance()->register_post_statuses();
	}
	flush_rewrite_rules();
}

add_action( 'init', __NAMESPACE__ . '\\load_textdomain' );
add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot' );
add_action( 'before_woocommerce_init', __NAMESPACE__ . '\\declare_hpos_compatibility' );
register_activation_hook( __FILE__, __NAMESPACE__ . '\\on_activation' );
