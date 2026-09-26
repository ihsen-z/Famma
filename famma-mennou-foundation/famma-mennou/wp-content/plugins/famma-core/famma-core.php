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
 *
 * One exception, in `includes/` : le contenu éditable des pages
 * institutionnelles. Ce fichier avait franchi les 2 100 lignes, et la règle 6
 * demande de scinder par responsabilité passé ce volume. L'exception reste
 * étroite et préserve les deux garanties ci-dessus : un `require_once` explicite
 * plutôt qu'un autoloader, et une seule classe par fichier inclus.
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

define( 'FAMMA_CORE_VERSION', '0.2.0' );
define( 'FAMMA_CORE_FILE', __FILE__ );

require_once __DIR__ . '/includes/pages-content.php';
require_once __DIR__ . '/includes/product-content.php';
require_once __DIR__ . '/includes/home-content.php';
require_once __DIR__ . '/includes/delivery-content.php';
require_once __DIR__ . '/includes/returns-content.php';
require_once __DIR__ . '/includes/newsletter.php';
require_once __DIR__ . '/includes/topbar-content.php';
require_once __DIR__ . '/includes/tracking-settings.php';
require_once __DIR__ . '/includes/tracking-events.php';
require_once __DIR__ . '/includes/express-order.php';
require_once __DIR__ . '/includes/anti-abuse.php';
require_once __DIR__ . '/includes/checkout-lite.php';
require_once __DIR__ . '/includes/product-video.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/seo-listing.php';
require_once __DIR__ . '/includes/seo-admin.php';
require_once __DIR__ . '/includes/hardening.php';
require_once __DIR__ . '/includes/performance.php';

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
	 * La constante du serveur l'emporte, comme partout ailleurs, mais elle
	 * n'est plus la seule source : le numéro se saisit aussi depuis
	 * « FAMMA → Pages ». Sans ce repli, le propriétaire devrait éditer un
	 * fichier sur le serveur pour changer un numéro de téléphone.
	 *
	 * @return string
	 */
	public function whatsapp_number(): string {
		$raw = $this->c( 'FAMMA_WHATSAPP_NUMBER' );

		if ( '' === $raw ) {
			$raw = (string) get_option( 'famma_whatsapp_number', '' );
		}

		return self::international_phone( $raw );
	}

	/**
	 * Normalises a phone number to international digits, Tunisia by default.
	 *
	 * Le service wa.me exige le numéro complet, indicatif compris, sans « + » ni « 00 ».
	 * Saisi en local (« 56 261 412 »), le numéro partait tel quel et WhatsApp
	 * le lisait comme un numéro chilien (+56) : aucun message n'arrivait
	 * (audit du 23/09, UX-01). Un numéro à 8 chiffres est donc tunisien.
	 *
	 * @param string $raw Number as typed: spaces, dots, « + » or « 00 » allowed.
	 * @return string Digits only, e.g. `21656261412`; empty when nothing usable.
	 */
	public static function international_phone( string $raw ): string {
		$digits = (string) preg_replace( '/\D+/', '', $raw );

		if ( str_starts_with( $digits, '00' ) ) {
			$digits = substr( $digits, 2 );
		}

		if ( 8 === strlen( $digits ) ) {
			$digits = '216' . $digits;
		}

		return $digits;
	}

	/**
	 * Builds a `tel:` URI in international format, or an empty string.
	 *
	 * @param string $raw Number as typed in the admin.
	 * @return string e.g. `tel:+21656261412`.
	 */
	public static function tel_uri( string $raw ): string {
		$digits = self::international_phone( $raw );

		return '' === $digits ? '' : 'tel:+' . $digits;
	}

	/**
	 * Reads a constant, falling back to the matching admin option.
	 *
	 * Same precedence as the shop's contact details: a constant defined on the
	 * server always wins, so a `.env` reproduced by `git clone` + `setup.sh`
	 * stays authoritative. The option only fills the gap, which is what lets the
	 * owner change a tracking identifier without touching a file on the server.
	 *
	 * @param string $constant Constant name, e.g. `FAMMA_META_PIXEL_ID`.
	 * @return string Empty string when neither is set — meaning "disabled".
	 */
	private function setting( string $constant ): string {
		$value = $this->c( $constant );

		if ( '' !== $value ) {
			return $value;
		}

		return trim( (string) get_option( strtolower( $constant ), '' ) );
	}

	/**
	 * Meta pixel identifier (safe to expose to the browser).
	 *
	 * @return string
	 */
	public function meta_pixel_id(): string {
		return $this->setting( 'FAMMA_META_PIXEL_ID' );
	}

	/**
	 * Meta Conversions API token. Server-side only — never enqueued (§17).
	 *
	 * @return string
	 */
	public function meta_access_token(): string {
		return $this->setting( 'FAMMA_META_ACCESS_TOKEN' );
	}

	/**
	 * Meta test event code, used when validating CAPI events.
	 *
	 * @return string
	 */
	public function meta_test_event_code(): string {
		return $this->setting( 'FAMMA_META_TEST_EVENT_CODE' );
	}

	/**
	 * TikTok pixel identifier.
	 *
	 * @return string
	 */
	public function tiktok_pixel_id(): string {
		return $this->setting( 'FAMMA_TIKTOK_PIXEL_ID' );
	}

	/**
	 * GA4 measurement identifier.
	 *
	 * @return string
	 */
	public function ga4_measurement_id(): string {
		return $this->setting( 'FAMMA_GA4_MEASUREMENT_ID' );
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

		/*
		 * WC_Gateway_COD drops a fresh order straight into "processing", and
		 * "processing" is one of WooCommerce's paid statuses — so a brand-new,
		 * undelivered order reported is_paid() === true. Observed on a real
		 * browser checkout. §12 forbids exactly that. New COD orders therefore
		 * start at "pending confirmation", which is the first step of the
		 * workflow in §2 anyway.
		 */
		add_filter( 'woocommerce_cod_process_payment_order_status', array( $this, 'cod_initial_status' ) );

		/*
		 * Conséquence du point précédent : WooCommerce ne rattache ses e-mails
		 * « Nouvelle commande » (admin) et « Commande reçue » (client) qu'aux
		 * transitions pending → processing / on-hold / completed. Une commande
		 * qui démarre en « pending-confirm » ne déclenchait donc AUCUN e-mail :
		 * le marchand n'était pas prévenu (audit du 23/09, CODE-01).
		 */
		add_action( 'woocommerce_order_status_pending_to_pending-confirm', array( $this, 'send_new_order_emails' ), 10, 2 );
		add_action( 'woocommerce_order_status_failed_to_pending-confirm', array( $this, 'send_new_order_emails' ), 10, 2 );
	}

	/**
	 * Sends the "new order" (admin) and "order received" (customer) emails.
	 *
	 * Uses WooCommerce's own email classes, so the templates, the recipient set
	 * in WooCommerce → Settings → Emails and the on/off switches all still
	 * apply. WC_Email_New_Order guards against a second send by itself.
	 *
	 * @param int            $order_id Order ID.
	 * @param \WC_Order|null $order    Order object when WooCommerce passes it.
	 * @return void
	 */
	public function send_new_order_emails( int $order_id, $order = null ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$emails = WC()->mailer()->get_emails();

		if ( isset( $emails['WC_Email_New_Order'] ) ) {
			$emails['WC_Email_New_Order']->trigger( $order_id, $order );
		}

		// E-mail client facultatif au checkout : WooCommerce ne l'envoie que s'il existe.
		if ( isset( $emails['WC_Email_Customer_Processing_Order'] ) && '' !== $order->get_billing_email() ) {
			$emails['WC_Email_Customer_Processing_Order']->trigger( $order_id, $order );
		}
	}

	/**
	 * First status of a cash-on-delivery order (§2, §12).
	 *
	 * @return string
	 */
	public function cod_initial_status(): string {
		return 'pending-confirm';
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

		/*
		 * "processing" is dropped on purpose. WooCommerce treats it as paid,
		 * which is right for a prepaid gateway but wrong here: §2 places
		 * PROCESSING between CONFIRMED and SHIPPED, i.e. while the parcel is
		 * being prepared and no cash has been collected yet. Counting it as
		 * revenue is the false-positive §12 forbids.
		 *
		 * Valid while cash on delivery is the only gateway (§2). Adding an
		 * online payment method later means revisiting this line.
		 */
		return array_values( array_diff( $statuses, array( 'processing' ) ) );
	}

	/**
	 * Includes delivered orders in WooCommerce reports.
	 *
	 * @param string[] $s Statuses included in reports.
	 * @return string[]
	 */
	public function reporting_statuses( array $s ): array {
		// Same reasoning as paid_statuses(): a COD order is revenue only once delivered.
		$s = array_values( array_diff( $s, array( 'processing', 'on-hold' ) ) );
		foreach ( array( 'delivered', 'completed' ) as $x ) {
			if ( ! in_array( $x, $s, true ) ) {
				$s[] = $x;
			}
		}
		return $s;
	}
}

/**
 * Stock reservation policy for the cash-on-delivery workflow (§2).
 *
 * WooCommerce reserves stock when an order reaches processing, on-hold or
 * completed. None of those is on the COD path, so without this class stock
 * was only ever decremented at delivery — long after the parcel left, and
 * late enough for two orders to be taken on the same last unit.
 *
 * The reservation point is "confirmed", i.e. once a human has confirmed the
 * order by phone. Reserving earlier would let unconfirmed or fake orders
 * block real stock, which is a routine problem on Tunisian COD stores (§47).
 * Stock is given back on every outcome where the goods come home.
 */
final class Stock {

	/**
	 * Singleton instance.
	 *
	 * @var Stock|null
	 */
	private static ?Stock $i = null;

	/**
	 * Statuses on which the goods return to stock.
	 *
	 * @var string[]
	 */
	private const RELEASING_STATUSES = array( 'cancelled', 'refused', 'returned', 'failed-delivery' );

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Stock
	 */
	public static function instance(): Stock {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers the reservation and release hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'woocommerce_order_status_confirmed', array( $this, 'reserve' ) );
		foreach ( self::RELEASING_STATUSES as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( $this, 'release' ) );
		}
	}

	/**
	 * Reserves stock for a confirmed order.
	 *
	 * Idempotent: WooCommerce guards on the _order_stock_reduced meta, so a
	 * replayed transition cannot decrement twice.
	 *
	 * @param int $order_id Order identifier.
	 * @return void
	 */
	public function reserve( int $order_id ): void {
		wc_maybe_reduce_stock_levels( $order_id );
	}

	/**
	 * Puts the goods back in stock when the order does not reach the customer.
	 *
	 * @param int $order_id Order identifier.
	 * @return void
	 */
	public function release( int $order_id ): void {
		wc_maybe_increase_stock_levels( $order_id );
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
	 * WooCommerce vérifie déjà le nonce avant de déclencher ce hook, mais la
	 * règle 4 du projet impose la séquence complète — nonce, capacité,
	 * assainissement — sur place et sans la déléguer. Le contrôle est donc
	 * refait ici, ce qui supprime au passage les `phpcs:ignore` qui
	 * signalaient l'absence de vérification.
	 *
	 * @param \WC_Product $product Product being saved.
	 * @return void
	 */
	public function save_fields( \WC_Product $product ): void {
		$nonce = isset( $_POST['woocommerce_meta_nonce'] )
			? sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		foreach ( array( '_famma_cost_price', '_famma_shipping_cost', '_famma_handling_cost', '_famma_acquisition_cost' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
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
	 * Le prix retenu est le prix EFFECTIF, pas le prix barré : c'est celui
	 * que le client paie, donc le seul qui produise une marge réelle. Sur un
	 * produit en promotion, se fier au prix régulier surestimait la marge en
	 * silence — exactement au moment où elle se resserre.
	 *
	 * @param \WC_Product $product Product to evaluate.
	 * @return array{price:float,total_cost:float,profit:float,percent:float}
	 */
	public function margin( \WC_Product $product ): array {
		$effective = $product->get_price();
		$price     = '' === (string) $effective
			? (float) $product->get_regular_price()
			: (float) $effective;

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
 * Per-product FAQ, written by the owner.
 *
 * La FAQ vit dans le plugin et non dans le thème : c'est une donnée métier,
 * elle doit survivre à un changement de thème (règle 6). Le thème ne fait que
 * la présenter.
 *
 * Aucune question n'est fournie par défaut. §60 interdit le contenu inventé,
 * et une FAQ est précisément le texte qui engage la boutique : « puis-je
 * échanger ? », « en combien de temps ? ». Une réponse approximative écrite
 * ici deviendrait une promesse non tenue à la porte du client.
 */
final class Product_Faq {

	/**
	 * Meta key holding the parsed question/answer pairs.
	 *
	 * @var string
	 */
	public const META = '_famma_faq';

	/**
	 * Singleton instance.
	 *
	 * @var Product_Faq|null
	 */
	private static ?Product_Faq $i = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Product_Faq
	 */
	public static function instance(): Product_Faq {
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
		add_action( 'woocommerce_product_options_advanced', array( $this, 'render_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_field' ) );
	}

	/**
	 * Renders the FAQ textarea on the product screen.
	 *
	 * Un seul champ texte plutôt qu'un répéteur en JavaScript : le format est
	 * lisible tel quel, se relit sans interface, et ne casse pas si le script
	 * d'administration ne se charge pas.
	 *
	 * @return void
	 */
	public function render_field(): void {
		global $product_object;

		$value = '';

		if ( $product_object instanceof \WC_Product ) {
			$value = $this->to_text( $this->pairs( $product_object ) );
		}

		echo '<div class="options_group">';
		woocommerce_wp_textarea_input(
			array(
				'id'          => self::META,
				'value'       => $value,
				'label'       => __( 'Product FAQ', 'famma-core' ),
				'desc_tip'    => false,
				'description' => __( 'One entry per block: the question on the first line, the answer on the following lines. Separate entries with a blank line. Leave empty to hide the FAQ tab.', 'famma-core' ),
				'rows'        => 10,
			)
		);
		echo '</div>';
	}

	/**
	 * Persists the FAQ pairs.
	 *
	 * Séquence complète de la règle 4 — nonce, capacité, assainissement —
	 * refaite sur place comme dans Product_Financials.
	 *
	 * @param \WC_Product $product Product being saved.
	 * @return void
	 */
	public function save_field( \WC_Product $product ): void {
		$nonce = isset( $_POST['woocommerce_meta_nonce'] )
			? sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::META ] ) ) {
			return;
		}

		$raw = sanitize_textarea_field( wp_unslash( $_POST[ self::META ] ) );

		$product->update_meta_data( self::META, $this->parse( $raw ) );
	}

	/**
	 * Returns the stored pairs for a product, always as a clean list.
	 *
	 * @param \WC_Product $product Product to read.
	 * @return array<int, array<string, string>>
	 */
	public function pairs( \WC_Product $product ): array {
		$stored = $product->get_meta( self::META );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$pairs = array();

		foreach ( $stored as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['q'], $entry['a'] ) ) {
				continue;
			}

			$question = trim( (string) $entry['q'] );
			$answer   = trim( (string) $entry['a'] );

			if ( '' === $question || '' === $answer ) {
				continue;
			}

			$pairs[] = array(
				'q' => $question,
				'a' => $answer,
			);
		}

		return $pairs;
	}

	/**
	 * Parses the textarea into question/answer pairs.
	 *
	 * Une entrée sans réponse est ignorée, pas stockée à moitié : un accordéon
	 * qui s'ouvre sur du vide est un défaut visible par le client.
	 *
	 * @param string $raw Sanitised textarea content.
	 * @return array<int, array<string, string>>
	 */
	private function parse( string $raw ): array {
		$blocks = preg_split( '/\R{2,}/u', trim( $raw ) );
		$pairs  = array();

		foreach ( (array) $blocks as $block ) {
			$lines = preg_split( '/\R/u', trim( (string) $block ) );
			$lines = array_values( array_filter( array_map( 'trim', (array) $lines ), 'strlen' ) );

			if ( count( $lines ) < 2 ) {
				continue;
			}

			$question = sanitize_text_field( (string) array_shift( $lines ) );
			$answer   = sanitize_textarea_field( implode( "\n", $lines ) );

			if ( '' === $question || '' === $answer ) {
				continue;
			}

			$pairs[] = array(
				'q' => $question,
				'a' => $answer,
			);
		}

		return $pairs;
	}

	/**
	 * Rebuilds the editable text from stored pairs.
	 *
	 * @param array<int, array<string, string>> $pairs Stored pairs.
	 * @return string
	 */
	private function to_text( array $pairs ): string {
		$blocks = array();

		foreach ( $pairs as $pair ) {
			$blocks[] = $pair['q'] . "\n" . $pair['a'];
		}

		return implode( "\n\n", $blocks );
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
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'require_email_for_account' ), 20, 2 );

		/*
		 * The country field is removed from the checkout — the shop only sells
		 * to Tunisia, so asking is pure friction (§13). But WooCommerce still
		 * needs a country: WC_Checkout::process_checkout() aborts with
		 * "Please enter an address to continue." when the customer's shipping
		 * country is empty. Verified in a real browser checkout: no order
		 * could be placed at all. These four filters supply it.
		 */
		add_filter( 'woocommerce_customer_default_country', array( $this, 'default_country' ) );
		add_filter( 'default_checkout_billing_country', array( $this, 'default_country' ) );
		add_filter( 'default_checkout_shipping_country', array( $this, 'default_country' ) );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'force_country' ) );

		/*
		 * The store's base location pre-filled « Tunis » as every guest's
		 * governorate (audit 25/09, UX-43): a hurried customer from Sfax
		 * confirmed the wrong one. The country stays, the governorate is left
		 * for the customer to choose, as on the express form.
		 */
		add_filter( 'woocommerce_customer_default_location_array', array( $this, 'blank_default_state' ) );

		/*
		 * One "full name" field instead of first + last name, and an optional
		 * city (owner's decision, 23/09/2026): the governorate is enough to
		 * route a parcel, and every extra field on mobile costs orders.
		 */
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'split_full_name' ), 20 );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'country_locale' ) );

		add_filter( 'woocommerce_currency_symbol', array( $this, 'currency_symbol' ), 10, 2 );
	}

	/**
	 * Splits the single "full name" field into first and last name.
	 *
	 * The customer types « Mohamed Ben Ali » once; the order still carries a
	 * first and a last name, which is what the courier, the order e-mails and
	 * the Meta Conversions API expect. First word = first name, the rest = last
	 * name. A one-word name leaves the last name empty, which is accepted.
	 *
	 * When the parcel goes to the billing address, WooCommerce has already
	 * copied the billing names into the shipping ones: they are aligned here.
	 *
	 * @param array<string,mixed> $data Posted checkout data.
	 * @return array<string,mixed>
	 */
	public function split_full_name( array $data ): array {
		$full = trim( (string) ( $data['billing_first_name'] ?? '' ) );

		if ( '' === $full || '' !== trim( (string) ( $data['billing_last_name'] ?? '' ) ) ) {
			return $data;
		}

		$parts = preg_split( '/\s+/u', $full, 2 );
		$parts = is_array( $parts ) ? $parts : array( $full );

		$data['billing_first_name'] = (string) $parts[0];
		$data['billing_last_name']  = (string) ( $parts[1] ?? '' );

		if ( empty( $data['ship_to_different_address'] ) && array_key_exists( 'shipping_first_name', $data ) ) {
			$data['shipping_first_name'] = $data['billing_first_name'];
			$data['shipping_last_name']  = $data['billing_last_name'];
		}

		return $data;
	}

	/**
	 * Makes the city optional for Tunisia in the country locale.
	 *
	 * The locale is what the classic checkout script reads to toggle the
	 * « required » mark when the address form is refreshed; without this entry
	 * it would put the asterisk back on the city field.
	 *
	 * @param array<string,array<string,mixed>> $locale Locale rules by country.
	 * @return array<string,array<string,mixed>>
	 */
	public function country_locale( array $locale ): array {
		$locale['TN']['city']['required'] = false;

		return $locale;
	}

	/**
	 * « DT » on the French site, « د.ت » on the Arabic one.
	 *
	 * In a left-to-right paragraph, a number followed by Arabic letters is laid
	 * out right-to-left as a block: « 79,000 د.ت » was displayed with the symbol
	 * BEFORE the amount on the French pages (audit du 23/09, FP-06). « DT » is
	 * also how Tunisian shops write the dinar in French.
	 *
	 * @param string $symbol   Currency symbol.
	 * @param string $currency Currency code.
	 * @return string
	 */
	public function currency_symbol( string $symbol, string $currency ): string {
		if ( 'TND' !== $currency || str_starts_with( determine_locale(), 'ar' ) ) {
			return $symbol;
		}

		return 'DT';
	}

	/**
	 * Tunisia is the only country the shop sells to (§11).
	 *
	 * @return string
	 */
	public function default_country(): string {
		return 'TN';
	}

	/**
	 * Keeps Tunisia as the default location, without a governorate.
	 *
	 * @param array<string,string> $location Default location (`country`, `state`).
	 * @return array<string,string>
	 */
	public function blank_default_state( $location ): array {
		$location = (array) $location;

		return array(
			'country' => isset( $location['country'] ) && '' !== $location['country'] ? (string) $location['country'] : 'TN',
			'state'   => '',
		);
	}

	/**
	 * Stamps Tunisia on the posted checkout data, since the field is hidden.
	 *
	 * @param array<string,mixed> $data Posted checkout data.
	 * @return array<string,mixed>
	 */
	public function force_country( array $data ): array {
		$data['billing_country']  = 'TN';
		$data['shipping_country'] = 'TN';
		return $data;
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

		/*
		 * The same trim applies to the "ship to a different address" block:
		 * leaving it untouched put the country and postcode fields back in
		 * front of the customer as soon as the box was ticked.
		 */
		unset(
			$f['shipping']['shipping_company'],
			$f['shipping']['shipping_address_2'],
			$f['shipping']['shipping_postcode'],
			$f['shipping']['shipping_country']
		);
		if ( isset( $f['shipping']['shipping_state'] ) ) {
			$f['shipping']['shipping_state']['label'] = __( 'Governorate / الولاية', 'famma-core' );
		}
		if ( isset( $f['shipping']['shipping_city'] ) ) {
			$f['shipping']['shipping_city']['label'] = __( 'City / Delegation — المدينة', 'famma-core' );
		}

		/*
		 * Email stays available but stops being mandatory: §13 lists the
		 * required fields and email is not among them. On a cash-on-delivery
		 * order the reachable channel is the phone number (§33), and an extra
		 * required field on mobile is pure drop-off.
		 */
		if ( isset( $f['billing']['billing_email'] ) ) {
			$f['billing']['billing_email']['required'] = false;
			$f['billing']['billing_email']['priority'] = 90;
			$f['billing']['billing_email']['label']    = __( 'Email (optional) / البريد الإلكتروني', 'famma-core' );
		}

		/*
		 * One "full name" field: `split_full_name()` turns it back into first
		 * and last name on the order.
		 */
		unset( $f['billing']['billing_last_name'] );
		if ( isset( $f['billing']['billing_first_name'] ) ) {
			$f['billing']['billing_first_name']['label']        = __( 'Full name / الاسم واللقب', 'famma-core' );
			$f['billing']['billing_first_name']['autocomplete'] = 'name';
			$f['billing']['billing_first_name']['class']        = array( 'form-row-wide' );
		}

		if ( isset( $f['billing']['billing_phone'] ) ) {
			$f['billing']['billing_phone']['required']    = true;
			$f['billing']['billing_phone']['priority']    = 25;
			$f['billing']['billing_phone']['placeholder'] = 'XX XXX XXX';
			$f['billing']['billing_phone']['label']       = __( 'Phone / الهاتف', 'famma-core' );
		}
		if ( isset( $f['billing']['billing_state'] ) ) {
			$f['billing']['billing_state']['label']    = __( 'Governorate / الولاية', 'famma-core' );
			$f['billing']['billing_state']['required'] = true;
		}
		if ( isset( $f['billing']['billing_city'] ) ) {
			// WooCommerce appends « (optional) » itself to a non-required field.
			$f['billing']['billing_city']['label']    = __( 'City / Delegation — المدينة', 'famma-core' );
			$f['billing']['billing_city']['required'] = false;
		}
		if ( isset( $f['billing']['billing_address_1'] ) ) {
			$f['billing']['billing_address_1']['label'] = __( 'Address / العنوان', 'famma-core' );
		}
		return $f;
	}

	/**
	 * Requires an email address — but only from a customer asking for an account.
	 *
	 * Email is deliberately optional here (§13): on a cash-on-delivery order the
	 * reachable channel is the phone number, and an extra mandatory field on
	 * mobile is pure drop-off. WooCommerce, however, needs an email to open an
	 * account. Without this rule, ticking "create an account" with no email
	 * produced a raw WooCommerce error at the exact moment the customer believes
	 * they are done.
	 *
	 * The constraint applies to that case only: a guest order still needs no
	 * email. The message names the way out rather than just stating the refusal.
	 *
	 * Reads the posted data WooCommerce has already assembled and sanitised,
	 * never `$_POST`: `WC_Checkout::get_posted_data()` exposes `createaccount`,
	 * so there is no reason to reach for the raw superglobal.
	 *
	 * @param array<string, mixed> $data   Posted checkout data.
	 * @param \WP_Error            $errors Validation errors.
	 * @return void
	 */
	public function require_email_for_account( array $data, \WP_Error $errors ): void {
		if ( empty( $data['createaccount'] ) ) {
			return;
		}

		if ( '' !== trim( (string) ( $data['billing_email'] ?? '' ) ) ) {
			return;
		}

		$errors->add(
			'famma_account_email',
			__( 'To create an account, please give an email address. Otherwise choose "Order without an account": your order goes through without one.', 'famma-core' )
		);
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
		add_shortcode( 'famma_whatsapp', array( $this, 'shortcode' ) );
	}

	/**
	 * Renders a WhatsApp call to action, or nothing at all.
	 *
	 * Returning an empty string when no number is configured is the point:
	 * page content can reference the CTA freely without ever producing a dead
	 * link while the owner has not supplied a number (§15, §60).
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'text'    => '',
				'message' => '',
			),
			$atts,
			'famma_whatsapp'
		);

		$href = self::link( $atts['message'] );
		if ( '' === $href ) {
			return '';
		}

		$label = '' !== $atts['text'] ? $atts['text'] : __( 'Contact us on WhatsApp', 'famma-core' );

		return sprintf(
			'<a class="famma-wa-cta" href="%1$s" target="_blank" rel="noopener nofollow">%2$s</a>',
			esc_url( $href ),
			esc_html( $label )
		);
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
		if ( '' === Config::instance()->whatsapp_number() || ! self::show_float() ) {
			return;
		}

		/*
		 * L'anneau de focus était orange sur un bouton vert : 1,32:1,
		 * autrement dit invisible (§44). Bleu + halo blanc reste lisible
		 * quel que soit le contenu qui défile sous le bouton flottant.
		 *
		 * Les var() portent une valeur de repli : le plugin doit rester
		 * autonome si le thème enfant est remplacé (règle 6).
		 */
		$css = '.famma-wa{position:fixed;inset-inline-end:16px;inset-block-end:16px;z-index:var(--z-float,290);display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;background:#25D366;box-shadow:0 4px 14px rgba(0,0,0,.25);text-decoration:none}.famma-wa svg{width:30px;height:30px;fill:#fff}.famma-wa:focus-visible{outline:3px solid var(--famma-blue,#0B1F3A);outline-offset:2px;box-shadow:0 0 0 5px #fff,0 4px 14px rgba(0,0,0,.25)}';
		wp_register_style( 'famma-wa', false, array(), FAMMA_CORE_VERSION );
		wp_enqueue_style( 'famma-wa' );
		wp_add_inline_style( 'famma-wa', $css );
	}

	/**
	 * Should the floating button be shown on this page?
	 *
	 * The theme answers false where the page already carries its own WhatsApp
	 * card and the floating button would cover form fields (cart, checkout:
	 * audit 25/09, UX-49).
	 *
	 * @return bool
	 */
	public static function show_float(): bool {
		return (bool) apply_filters( 'famma_whatsapp_show_float', true );
	}

	/**
	 * Prints the floating button in the footer.
	 *
	 * @return void
	 */
	public function render_float(): void {
		if ( ! self::show_float() ) {
			return;
		}

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
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_capi_signals' ), 31 );
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
	 * Stores the signals the Conversions API needs, at order creation (§17).
	 *
	 * The Purchase event is sent days later, at delivery — long after the
	 * browser is gone. Adresse IP, agent utilisateur et cookies Meta doivent
	 * donc être capturés maintenant, sinon l'événement serveur partira sans
	 * rien pour être rapproché de la visite qui l'a produit.
	 *
	 * @param \WC_Order $order Order being created.
	 * @return void
	 */
	public function stamp_capi_signals( \WC_Order $order ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule d'un contexte de visite, pas une action.
		$cookie_map = array(
			'_fbp' => '_famma_fbp',
			'_fbc' => '_famma_fbc',
		);
		foreach ( $cookie_map as $cookie => $meta ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
			if ( ! empty( $_COOKIE[ $cookie ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
				$order->update_meta_data( $meta, substr( sanitize_text_field( wp_unslash( $_COOKIE[ $cookie ] ) ), 0, 255 ) );
			}
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' !== $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$order->update_meta_data( '_famma_client_ip', $ip );
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( '' !== $ua ) {
			$order->update_meta_data( '_famma_client_ua', substr( $ua, 0, 400 ) );
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

		/*
		 * L'identifiant d'événement est posé maintenant, mais le marqueur
		 * `_famma_purchase_sent` ne l'est PAS : il n'appartient qu'à
		 * l'émetteur, qui l'écrit après un envoi réellement réussi. Le poser
		 * ici consommerait définitivement le droit d'émettre sur un simple
		 * incident réseau, sans aucune trace côté Meta.
		 */
		$event_id = 'famma_purchase_' . $order_id;
		$order->update_meta_data( '_famma_purchase_event_id', $event_id );
		$order->save();

		/**
		 * Fires when a delivered COD order should be reported as a Purchase.
		 *
		 * @param \WC_Order $order    The delivered order.
		 * @param string    $event_id Deduplication identifier.
		 */
		do_action( 'famma_capi_purchase', $order, $event_id );
	}
}

/**
 * Meta Conversions API client (§17).
 *
 * Écoute `famma_capi_purchase`, déclenché à la livraison uniquement — une
 * commande COD n'est du chiffre d'affaires qu'une fois l'espèce encaissée.
 *
 * Trois règles tenues sans exception :
 *
 *  1. Le token n'est jamais journalisé, jamais renvoyé, jamais imprimé. Les
 *     messages d'erreur ne citent que le code HTTP et le message de Meta.
 *  2. Rien n'est envoyé tant que l'identifiant de pixel ET le token ne sont
 *     pas configurés. Sans eux, la classe est inerte.
 *  3. `_famma_purchase_sent` n'est écrit qu'après un envoi réellement
 *     accepté. Un incident réseau laisse donc la commande rejouable.
 */
final class Capi {

	/**
	 * Singleton instance.
	 *
	 * @var Capi|null
	 */
	private static ?Capi $i = null;

	/**
	 * Version de l'API Graph visée.
	 *
	 * Filtrable par `famma_capi_graph_version` pour pouvoir la faire monter
	 * sans toucher au code le jour où Meta retire celle-ci.
	 */
	private const GRAPH_VERSION = 'v21.0';

	/**
	 * Nombre d'échecs au-delà duquel on cesse de réessayer.
	 */
	private const MAX_ATTEMPTS = 5;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Capi
	 */
	public static function instance(): Capi {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'famma_capi_purchase', array( $this, 'send_purchase' ), 10, 2 );
	}

	/**
	 * Hashes a value the way Meta expects: normalised, then SHA-256.
	 *
	 * @param string $value Raw value.
	 * @return string Empty string when there is nothing to hash.
	 */
	private function hash( string $value ): string {
		$value = trim( strtolower( $value ) );
		return '' === $value ? '' : hash( 'sha256', $value );
	}

	/**
	 * Normalises a Tunisian phone number to digits with country code.
	 *
	 * @param string $phone Raw phone as typed by the customer.
	 * @return string
	 */
	private function normalise_phone( string $phone ): string {
		return Config::international_phone( $phone );
	}

	/**
	 * Builds the user_data block, with every identifier hashed.
	 *
	 * @param \WC_Order $order Delivered order.
	 * @return array<string,string>
	 */
	private function user_data( \WC_Order $order ): array {
		$data = array_filter(
			array(
				'em'      => $this->hash( (string) $order->get_billing_email() ),
				'ph'      => $this->hash( $this->normalise_phone( (string) $order->get_billing_phone() ) ),
				'fn'      => $this->hash( (string) $order->get_billing_first_name() ),
				'ln'      => $this->hash( (string) $order->get_billing_last_name() ),
				'ct'      => $this->hash( preg_replace( '/[^a-z]/', '', strtolower( (string) $order->get_billing_city() ) ) ),
				'st'      => $this->hash( (string) $order->get_billing_state() ),
				'country' => $this->hash( (string) $order->get_billing_country() ),
			)
		);

		// Ces trois-là ne sont PAS hachés : Meta les attend en clair.
		$raw_meta_map = array(
			'fbp' => '_famma_fbp',
			'fbc' => '_famma_fbc',
		);
		foreach ( $raw_meta_map as $key => $meta ) {
			$value = (string) $order->get_meta( $meta );
			if ( '' !== $value ) {
				$data[ $key ] = $value;
			}
		}

		$ip = (string) $order->get_meta( '_famma_client_ip' );
		if ( '' !== $ip ) {
			$data['client_ip_address'] = $ip;
		}

		$ua = (string) $order->get_meta( '_famma_client_ua' );
		if ( '' !== $ua ) {
			$data['client_user_agent'] = $ua;
		}

		return $data;
	}

	/**
	 * Builds the contents block from the order lines.
	 *
	 * @param \WC_Order $order Delivered order.
	 * @return array<int,array<string,mixed>>
	 */
	private function contents( \WC_Order $order ): array {
		$contents = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$sku = (string) $product->get_sku();

			$contents[] = array(
				'id'         => '' !== $sku ? $sku : (string) $product->get_id(),
				'quantity'   => (int) $item->get_quantity(),
				'item_price' => round( (float) $order->get_line_subtotal( $item, false, false ) / max( 1, (int) $item->get_quantity() ), 3 ),
			);
		}

		return $contents;
	}

	/**
	 * Sends the Purchase event, and records the outcome on the order.
	 *
	 * @param \WC_Order $order    Delivered order.
	 * @param string    $event_id Deduplication identifier shared with the pixel.
	 * @return void
	 */
	public function send_purchase( \WC_Order $order, string $event_id ): void {
		$cfg   = Config::instance();
		$pixel = $cfg->meta_pixel_id();
		$token = $cfg->meta_access_token();

		// Fonctionnalité non configurée : on ne fait rien, silencieusement.
		if ( '' === $pixel || '' === $token ) {
			return;
		}

		if ( $order->get_meta( '_famma_purchase_sent' ) ) {
			return;
		}

		$attempts = (int) $order->get_meta( '_famma_capi_attempts' );
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			return;
		}

		$event = array(
			'event_name'       => 'Purchase',
			'event_time'       => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time(),
			'event_id'         => $event_id,
			'action_source'    => 'website',
			'event_source_url' => $order->get_checkout_order_received_url(),
			'user_data'        => $this->user_data( $order ),
			'custom_data'      => array(
				'currency'     => $order->get_currency(),
				'value'        => round( (float) $order->get_total(), 3 ),
				'order_id'     => (string) $order->get_id(),
				'contents'     => $this->contents( $order ),
				'content_type' => 'product',
			),
		);

		$payload = array( 'data' => array( $event ) );

		$test_code = $cfg->meta_test_event_code();
		if ( '' !== $test_code ) {
			$payload['test_event_code'] = $test_code;
		}

		$version = (string) apply_filters( 'famma_capi_graph_version', self::GRAPH_VERSION );
		$url     = 'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . rawurlencode( $pixel ) . '/events';

		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),

				/*
				 * Le token voyage dans le corps, jamais dans l'URL : une URL
				 * finit dans les journaux d'accès, un corps de requête non.
				 */
				'body'        => wp_json_encode( $payload + array( 'access_token' => $token ) ),
			)
		);

		++$attempts;
		$order->update_meta_data( '_famma_capi_attempts', $attempts );

		if ( is_wp_error( $response ) ) {
			$this->record_failure( $order, $response->get_error_message(), $attempts );
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$body    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$message = is_array( $body ) && isset( $body['error']['message'] )
				? (string) $body['error']['message']
				: 'HTTP ' . $code;

			$this->record_failure( $order, $message, $attempts );
			return;
		}

		$order->update_meta_data( '_famma_purchase_sent', current_time( 'mysql' ) );
		$order->add_order_note(
			sprintf(
				/* translators: %s: deduplication event identifier. */
				__( 'Purchase event sent to Meta (event id %s).', 'famma-core' ),
				$event_id
			)
		);
		$order->save();
	}

	/**
	 * Records a failed attempt on the order, without ever exposing the token.
	 *
	 * @param \WC_Order $order    Order concerned.
	 * @param string    $message  Message returned by Meta or by the HTTP layer.
	 * @param int       $attempts Attempts made so far.
	 * @return void
	 */
	private function record_failure( \WC_Order $order, string $message, int $attempts ): void {
		$order->add_order_note(
			sprintf(
				/* translators: 1: error message, 2: attempts made, 3: maximum attempts. */
				__( 'Meta Purchase event failed: %1$s (attempt %2$d of %3$d).', 'famma-core' ),
				$message,
				$attempts,
				self::MAX_ATTEMPTS
			)
		);
		$order->save();
	}
}

/**
 * Simplified merchant dashboard (§28).
 *
 * Revenue counts delivered orders only — a COD order is not money until the
 * cash is collected (§12). Every figure here therefore differs on purpose
 * from WooCommerce's own reports, which treat a placed order as revenue.
 *
 * Admin only, and never loaded on the front end (rule 10).
 */
final class Dashboard {

	/**
	 * Singleton instance.
	 *
	 * @var Dashboard|null
	 */
	private static ?Dashboard $i = null;

	/**
	 * Transient holding the computed figures.
	 */
	private const CACHE_KEY = 'famma_dashboard_stats';

	/**
	 * How long the figures stay cached, in seconds.
	 */
	private const CACHE_TTL = 300;

	/**
	 * Hard cap on orders read per window.
	 *
	 * Rule 10 forbids unbounded queries. When the cap is reached the page
	 * says so rather than silently reporting a partial total.
	 */
	private const MAX_ORDERS = 1000;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Dashboard
	 */
	public static function instance(): Dashboard {
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_famma_refresh_stats', array( $this, 'refresh' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
	}

	/**
	 * Adds the FAMMA top-level admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'FAMMA', 'famma-core' ),
			__( 'FAMMA', 'famma-core' ),
			'manage_woocommerce',
			'famma-dashboard',
			array( $this, 'render' ),
			'dashicons-chart-area',
			56
		);
	}

	/**
	 * Loads the dashboard styles, on the dashboard screen only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function styles( string $hook ): void {
		if ( 'toplevel_page_famma-dashboard' !== $hook ) {
			return;
		}

		$css = '.famma-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));margin:16px 0 28px}'
			. '.famma-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}'
			. '.famma-card__label{color:#50575e;font-size:12px;text-transform:uppercase;letter-spacing:.05em;margin:0 0 6px}'
			. '.famma-card__value{font-size:26px;font-weight:700;color:#0B1F3A;line-height:1.2;margin:0}'
			. '.famma-card__note{color:#787c82;font-size:12px;margin:6px 0 0}'
			. '.famma-card--profit .famma-card__value{color:#1FA65A}'
			. '.famma-card--loss .famma-card__value{color:#D64545}'
			. '.famma-warn{border-inline-start:4px solid #E6A700;background:#fff8e6;padding:12px 16px;margin:16px 0}';

		wp_register_style( 'famma-dashboard', false, array(), FAMMA_CORE_VERSION );
		wp_enqueue_style( 'famma-dashboard' );
		wp_add_inline_style( 'famma-dashboard', $css );
	}

	/**
	 * Clears the cache and returns to the dashboard.
	 *
	 * @return void
	 */
	public function refresh(): void {
		check_admin_referer( 'famma_refresh_stats' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'famma-core' ) );
		}

		delete_transient( self::CACHE_KEY );
		wp_safe_redirect( admin_url( 'admin.php?page=famma-dashboard' ) );
		exit;
	}

	/**
	 * Collects the figures for one time window.
	 *
	 * @param string $after ISO date the window starts at.
	 * @return array<string,mixed>
	 */
	private function collect( string $after ): array {
		$orders = wc_get_orders(
			array(
				'limit'        => self::MAX_ORDERS,
				'date_created' => '>=' . $after,
				'status'       => array_keys( wc_get_order_statuses() ),
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);

		$out = array(
			'placed'    => 0,
			'delivered' => 0,
			'revenue'   => 0.0,
			'cost'      => 0.0,
			'cancelled' => 0,
			'returned'  => 0,
			'refused'   => 0,
			'failed'    => 0,
			'products'  => array(),
			'sources'   => array(),
			'capped'    => count( $orders ) >= self::MAX_ORDERS,
		);

		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}

			++$out['placed'];

			switch ( $order->get_status() ) {
				case 'cancelled':
					++$out['cancelled'];
					break;
				case 'returned':
					++$out['returned'];
					break;
				case 'refused':
					++$out['refused'];
					break;
				case 'failed-delivery':
					++$out['failed'];
					break;
			}

			$source                    = $order->get_meta( '_famma_utm_source' );
			$source                    = '' === (string) $source ? __( 'Direct', 'famma-core' ) : (string) $source;
			$out['sources'][ $source ] = ( $out['sources'][ $source ] ?? 0 ) + 1;

			// Seules les commandes livrées comptent comme du chiffre d'affaires (§12).
			if ( ! $order->has_status( 'delivered' ) ) {
				continue;
			}

			++$out['delivered'];
			$out['revenue'] += (float) $order->get_total();
			$out['cost']    += (float) $order->get_meta( '_famma_internal_shipping_cost' );

			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				if ( ! $product instanceof \WC_Product ) {
					continue;
				}

				$qty  = (int) $item->get_quantity();
				$name = $product->get_name();

				$out['products'][ $name ] = ( $out['products'][ $name ] ?? 0 ) + $qty;
				$out['cost']             += (float) $product->get_meta( '_famma_cost_price' ) * $qty;
				$out['cost']             += (float) $product->get_meta( '_famma_handling_cost' ) * $qty;
				$out['cost']             += (float) $product->get_meta( '_famma_acquisition_cost' );
			}
		}

		$out['profit'] = $out['revenue'] - $out['cost'];
		$out['aov']    = $out['delivered'] > 0 ? $out['revenue'] / $out['delivered'] : 0.0;

		arsort( $out['products'] );
		arsort( $out['sources'] );

		return $out;
	}

	/**
	 * Returns every window's figures, cached.
	 *
	 * @return array<string,mixed>
	 */
	private function stats(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$today = wp_date( 'Y-m-d' );
		$stats = array(
			'today'       => $this->collect( $today . ' 00:00:00' ),
			'week'        => $this->collect( wp_date( 'Y-m-d', strtotime( '-6 days' ) ) . ' 00:00:00' ),
			'month'       => $this->collect( wp_date( 'Y-m-d', strtotime( '-29 days' ) ) . ' 00:00:00' ),
			'computed_at' => time(),
		);

		set_transient( self::CACHE_KEY, $stats, self::CACHE_TTL );

		return $stats;
	}

	/**
	 * Prints one figure card.
	 *
	 * @param string $label    Card label.
	 * @param string $value    Already-escaped value markup.
	 * @param string $note     Optional note under the value.
	 * @param string $modifier Optional CSS modifier suffix.
	 * @return void
	 */
	private function card( string $label, string $value, string $note = '', string $modifier = '' ): void {
		printf(
			'<div class="famma-card%1$s"><p class="famma-card__label">%2$s</p><p class="famma-card__value">%3$s</p>%4$s</div>',
			$modifier ? ' famma-card--' . esc_attr( $modifier ) : '',
			esc_html( $label ),
			wp_kses_post( $value ),
			'' !== $note ? '<p class="famma-card__note">' . esc_html( $note ) . '</p>' : ''
		);
	}

	/**
	 * Renders the dashboard page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$stats = $this->stats();
		$month = $stats['month'];

		echo '<div class="wrap"><h1>' . esc_html__( 'FAMMA — dashboard', 'famma-core' ) . '</h1>';

		echo '<p>' . esc_html__( 'Revenue counts delivered orders only: a cash-on-delivery order is not money until the cash is collected. These figures therefore differ from WooCommerce reports on purpose.', 'famma-core' ) . '</p>';

		if ( $month['capped'] ) {
			echo '<div class="famma-warn"><strong>' . esc_html__( 'Partial figures.', 'famma-core' ) . '</strong> '
				. esc_html(
					sprintf(
						/* translators: %d: maximum number of orders read. */
						__( 'The last %d orders were read. Older orders in the window are not counted.', 'famma-core' ),
						self::MAX_ORDERS
					)
				) . '</div>';
		}

		echo '<h2>' . esc_html__( 'Orders', 'famma-core' ) . '</h2><div class="famma-grid">';
		$this->card( __( 'Today', 'famma-core' ), (string) $stats['today']['placed'], __( 'orders placed', 'famma-core' ) );
		$this->card( __( '7 days', 'famma-core' ), (string) $stats['week']['placed'], __( 'orders placed', 'famma-core' ) );
		$this->card( __( '30 days', 'famma-core' ), (string) $month['placed'], __( 'orders placed', 'famma-core' ) );
		$this->card( __( 'Delivered — 30 days', 'famma-core' ), (string) $month['delivered'], __( 'cash collected', 'famma-core' ) );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Money — last 30 days', 'famma-core' ) . '</h2><div class="famma-grid">';
		$this->card( __( 'Revenue', 'famma-core' ), wc_price( $month['revenue'] ), __( 'delivered orders only', 'famma-core' ) );
		$this->card( __( 'Estimated cost', 'famma-core' ), wc_price( $month['cost'] ), __( 'goods, shipping, handling', 'famma-core' ) );
		$this->card(
			__( 'Estimated margin', 'famma-core' ),
			wc_price( $month['profit'] ),
			__( 'revenue minus cost', 'famma-core' ),
			$month['profit'] >= 0 ? 'profit' : 'loss'
		);
		$this->card( __( 'Average order', 'famma-core' ), wc_price( $month['aov'] ), __( 'per delivered order', 'famma-core' ) );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Losses — last 30 days', 'famma-core' ) . '</h2><div class="famma-grid">';
		$this->card( __( 'Failed delivery', 'famma-core' ), (string) $month['failed'] );
		$this->card( __( 'Refused', 'famma-core' ), (string) $month['refused'] );
		$this->card( __( 'Returned', 'famma-core' ), (string) $month['returned'] );
		$this->card( __( 'Cancelled', 'famma-core' ), (string) $month['cancelled'] );
		echo '</div>';

		$this->table( __( 'Top products — 30 days', 'famma-core' ), __( 'Product', 'famma-core' ), __( 'Units delivered', 'famma-core' ), $month['products'] );
		$this->table( __( 'Acquisition — 30 days', 'famma-core' ), __( 'Source', 'famma-core' ), __( 'Orders', 'famma-core' ), $month['sources'] );

		printf(
			'<p><a class="button" href="%1$s">%2$s</a> <span class="famma-card__note">%3$s</span></p>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=famma_refresh_stats' ), 'famma_refresh_stats' ) ),
			esc_html__( 'Recalculate now', 'famma-core' ),
			esc_html(
				sprintf(
					/* translators: %s: human-readable time difference. */
					__( 'Figures computed %s ago, cached to keep the page fast.', 'famma-core' ),
					human_time_diff( (int) $stats['computed_at'] )
				)
			)
		);

		echo '</div>';
	}

	/**
	 * Prints a two-column table, or a placeholder when there is nothing to show.
	 *
	 * @param string            $title  Section title.
	 * @param string            $key    Left column header.
	 * @param string            $value  Right column header.
	 * @param array<string,int> $rows   Rows, already sorted.
	 * @return void
	 */
	private function table( string $title, string $key, string $value, array $rows ): void {
		echo '<h2>' . esc_html( $title ) . '</h2>';

		if ( empty( $rows ) ) {
			echo '<p class="famma-card__note">' . esc_html__( 'Nothing yet.', 'famma-core' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th>'
			. esc_html( $key ) . '</th><th>' . esc_html( $value ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 10, true ) as $label => $count ) {
			printf( '<tr><td>%1$s</td><td>%2$s</td></tr>', esc_html( $label ), esc_html( (string) $count ) );
		}

		echo '</tbody></table>';
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
	static $loaded = '';

	$locale = determine_locale();

	if ( $locale === $loaded ) {
		return;
	}

	/*
	 * Chargement explicite du fichier, et non `load_plugin_textdomain()`.
	 *
	 * Sous TranslatePress, la langue de la page n'est arrêtée qu'après `init`,
	 * et `load_plugin_textdomain()` — qui redéduit la locale lui-même — ne
	 * retrouvait pas le fichier arabe. Le second argument de
	 * `unload_textdomain()` compte autant : par défaut il retire le domaine du
	 * registre de chargement différé, ce qui fait échouer en silence le
	 * chargement qui suit.
	 *
	 * Mesuré sur le site, pas déduit : sans cela, `/ar/` servait les statuts de
	 * commande en anglais alors que la traduction existait.
	 */
	$mofile = plugin_dir_path( FAMMA_CORE_FILE ) . 'languages/famma-core-' . $locale . '.mo';

	if ( is_readable( $mofile ) ) {
		unload_textdomain( 'famma-core', true );
		\load_textdomain( 'famma-core', $mofile, $locale );
		$loaded = $locale;
		return;
	}

	load_plugin_textdomain( 'famma-core', false, dirname( plugin_basename( FAMMA_CORE_FILE ) ) . '/languages' );

	$loaded = $locale;
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
	Stock::instance();
	Product_Financials::instance();
	Shipping::instance();
	Tunisia::instance();
	Tracking::instance();
	Tracking_Events::instance();
	Express_Order::instance();
	Anti_Abuse::instance();
	Checkout_Lite::instance();
	Capi::instance();
	WhatsApp::instance();
	Product_Video::instance();
	Seo::instance();
	Seo_Listing::instance();
	add_filter( 'famma_seo_composed_description', array( Delivery_Content::class, 'seo_description' ), 10, 2 );
	add_filter( 'famma_seo_composed_description', array( Returns_Content::class, 'seo_description' ), 10, 2 );
	Returns_Content::hooks();
	Hardening::instance();
	Performance::instance();
	// Le formulaire public passe par admin-post.php : chargé partout.
	Newsletter::instance();

	// Le tableau de bord ne sert qu'à l'administration : rien de tout cela
	// n'est chargé sur le front (règle 10).
	if ( is_admin() ) {
		Product_Faq::instance();
		Dashboard::instance();
		Pages_Content::instance();
		Topbar_Content::instance();
		Tracking_Settings::instance();
		Seo_Admin::instance();
	}
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
// Rejoué une fois la requête résolue : c'est seulement là que TranslatePress a
// arrêté la langue de la page.
add_action( 'wp', __NAMESPACE__ . '\\load_textdomain', 1 );
add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot' );
add_action( 'before_woocommerce_init', __NAMESPACE__ . '\\declare_hpos_compatibility' );
register_activation_hook( __FILE__, __NAMESPACE__ . '\\on_activation' );
