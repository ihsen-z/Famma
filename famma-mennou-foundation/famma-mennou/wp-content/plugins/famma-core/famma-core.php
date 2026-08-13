<?php
/**
 * Plugin Name:       FAMMA Core
 * Description:        Core business logic for FAMMA MENNOU — custom COD order workflow, cost×3 pricing with margin tracking, Tunisia checkout rules, WhatsApp, and env-driven tracking (Meta/TikTok/GA4). Keeps customisation out of WP/WooCommerce core and the theme.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            FAMMA
 * Text Domain:       famma-core
 * WC requires at least: 8.0
 *
 * @package Famma\Core
 *
 * Single-file build: all classes live here so there is no cross-file autoload to
 * misfire. Authored to the FAMMA spec but NOT yet run on a live WP+WooCommerce
 * install — treat first activation as a test and check the debug log. You may
 * later split each class into its own file under includes/ if you prefer.
 */

namespace Famma\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'FAMMA_CORE_VERSION', '0.1.0' );
define( 'FAMMA_CORE_FILE', __FILE__ );

/* =============================== Config ================================= */
final class Config {
	private static ?Config $i = null;
	public static function instance(): Config { return self::$i ??= new self(); }
	private function c( string $k, string $d = '' ): string {
		return defined( $k ) && '' !== (string) constant( $k ) ? (string) constant( $k ) : $d;
	}
	public function whatsapp_number(): string { return preg_replace( '/\D+/', '', $this->c( 'FAMMA_WHATSAPP_NUMBER' ) ); }
	public function meta_pixel_id(): string { return $this->c( 'FAMMA_META_PIXEL_ID' ); }
	public function meta_access_token(): string { return $this->c( 'FAMMA_META_ACCESS_TOKEN' ); } // server-side only
	public function meta_test_event_code(): string { return $this->c( 'FAMMA_META_TEST_EVENT_CODE' ); }
	public function tiktok_pixel_id(): string { return $this->c( 'FAMMA_TIKTOK_PIXEL_ID' ); }
	public function ga4_measurement_id(): string { return $this->c( 'FAMMA_GA4_MEASUREMENT_ID' ); }
	public function google_site_verification(): string { return $this->c( 'FAMMA_GOOGLE_SITE_VERIFICATION' ); }
	public function internal_shipping_cost(): float { return (float) get_option( 'famma_internal_shipping_cost', 7.0 ); }
	public function price_multiplier(): float { return (float) get_option( 'famma_price_multiplier', 3.0 ); }
}

/* =========================== Order_Statuses ============================= */
final class Order_Statuses {
	private static ?Order_Statuses $i = null;
	public static function instance(): Order_Statuses { if ( null === self::$i ) { self::$i = new self(); self::$i->hooks(); } return self::$i; }
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
	private function hooks(): void {
		add_action( 'init', array( $this, 'register_post_statuses' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_to_wc_list' ) );
		add_action( 'woocommerce_order_status_delivered', array( $this, 'mark_cod_paid_on_delivery' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( $this, 'reporting_statuses' ) );
	}
	public function register_post_statuses(): void {
		foreach ( $this->statuses() as $slug => $label ) {
			register_post_status( 'wc-' . $slug, array(
				'label' => $label, 'public' => false, 'exclude_from_search' => false,
				'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true,
				'label_count' => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'famma-core' ),
			) );
		}
	}
	public function add_to_wc_list( array $s ): array {
		$out = array(); $st = $this->statuses();
		foreach ( $s as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'wc-pending' === $k ) { $out['wc-pending-confirm'] = $st['pending-confirm']; $out['wc-confirmed'] = $st['confirmed']; }
			if ( 'wc-processing' === $k ) {
				$out['wc-shipped'] = $st['shipped']; $out['wc-delivered'] = $st['delivered'];
				$out['wc-failed-delivery'] = $st['failed-delivery']; $out['wc-returned'] = $st['returned']; $out['wc-refused'] = $st['refused'];
			}
		}
		return $out;
	}
	public function mark_cod_paid_on_delivery( int $order_id ): void {
		$o = wc_get_order( $order_id );
		if ( $o && ! $o->is_paid() ) { $o->payment_complete(); $o->add_order_note( __( 'COD payment collected on delivery.', 'famma-core' ) ); }
	}
	public function reporting_statuses( array $s ): array {
		foreach ( array( 'delivered', 'completed', 'processing' ) as $x ) { if ( ! in_array( $x, $s, true ) ) { $s[] = $x; } }
		return $s;
	}
}

/* ========================= Product_Financials =========================== */
final class Product_Financials {
	private static ?Product_Financials $i = null;
	public static function instance(): Product_Financials { if ( null === self::$i ) { self::$i = new self(); self::$i->hooks(); } return self::$i; }
	private function hooks(): void {
		add_action( 'woocommerce_product_options_pricing', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_fields' ) );
	}
	public function render_fields(): void {
		echo '<div class="options_group">';
		echo '<p class="form-field"><strong>' . esc_html__( 'FAMMA financials (internal — not shown to customers)', 'famma-core' ) . '</strong></p>';
		woocommerce_wp_text_input( array( 'id' => '_famma_cost_price', 'label' => __( 'Cost price (TND)', 'famma-core' ), 'desc_tip' => true, 'description' => __( 'Leave selling price empty to auto-set to cost × 3.', 'famma-core' ), 'data_type' => 'price' ) );
		woocommerce_wp_text_input( array( 'id' => '_famma_shipping_cost', 'label' => __( 'Internal shipping cost (TND)', 'famma-core' ), 'desc_tip' => true, 'description' => __( 'Included in price; customer pays 0.', 'famma-core' ), 'data_type' => 'price', 'placeholder' => (string) Config::instance()->internal_shipping_cost() ) );
		woocommerce_wp_text_input( array( 'id' => '_famma_handling_cost', 'label' => __( 'Handling cost (TND)', 'famma-core' ), 'data_type' => 'price' ) );
		woocommerce_wp_text_input( array( 'id' => '_famma_acquisition_cost', 'label' => __( 'Acquisition cost / order (TND)', 'famma-core' ), 'data_type' => 'price' ) );
		global $product_object;
		if ( $product_object instanceof \WC_Product ) {
			$m = $this->margin( $product_object );
			echo '<p class="form-field"><span>' . esc_html( sprintf( __( 'Estimated net margin: %1$s TND (%2$s%%)', 'famma-core' ), number_format( $m['profit'], 2 ), number_format( $m['percent'], 1 ) ) ) . '</span></p>';
		}
		echo '</div>';
	}
	public function save_fields( \WC_Product $product ): void {
		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) { return; }
		foreach ( array( '_famma_cost_price', '_famma_shipping_cost', '_famma_handling_cost', '_famma_acquisition_cost' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$val = wc_clean( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$product->update_meta_data( $key, '' === $val ? '' : wc_format_decimal( $val ) );
			}
		}
		$cost = (float) $product->get_meta( '_famma_cost_price' );
		if ( $cost > 0 && '' === (string) $product->get_regular_price() ) {
			$product->set_regular_price( wc_format_decimal( $cost * Config::instance()->price_multiplier() ) );
		}
	}
	public function margin( \WC_Product $product ): array {
		$price = (float) $product->get_regular_price();
		$cost  = (float) $product->get_meta( '_famma_cost_price' );
		$sm    = $product->get_meta( '_famma_shipping_cost' );
		$ship  = '' === (string) $sm ? Config::instance()->internal_shipping_cost() : (float) $sm;
		$tot   = $cost + $ship + (float) $product->get_meta( '_famma_handling_cost' ) + (float) $product->get_meta( '_famma_acquisition_cost' );
		$profit = $price - $tot;
		return array( 'price' => $price, 'total_cost' => $tot, 'profit' => $profit, 'percent' => $price > 0 ? ( $profit / $price ) * 100 : 0.0 );
	}
}

/* =============================== Shipping =============================== */
final class Shipping {
	private static ?Shipping $i = null;
	public static function instance(): Shipping { if ( null === self::$i ) { self::$i = new self(); self::$i->hooks(); } return self::$i; }
	private function hooks(): void {
		add_filter( 'woocommerce_general_settings', array( $this, 'settings' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'record_internal_shipping' ), 20, 2 );
	}
	public function settings( array $s ): array {
		return array_merge( $s, array(
			array( 'title' => __( 'FAMMA business rules', 'famma-core' ), 'type' => 'title', 'id' => 'famma_business_rules' ),
			array( 'title' => __( 'Default price multiplier', 'famma-core' ), 'desc' => __( 'Selling price = cost × this (default 3). Manual prices override.', 'famma-core' ), 'id' => 'famma_price_multiplier', 'type' => 'number', 'default' => '3', 'custom_attributes' => array( 'step' => '0.1', 'min' => '1' ) ),
			array( 'title' => __( 'Internal shipping cost (TND)', 'famma-core' ), 'desc' => __( 'Included in price; customer pays 0. Margin only.', 'famma-core' ), 'id' => 'famma_internal_shipping_cost', 'type' => 'number', 'default' => '7', 'custom_attributes' => array( 'step' => '0.5', 'min' => '0' ) ),
			array( 'type' => 'sectionend', 'id' => 'famma_business_rules' ),
		) );
	}
	public function record_internal_shipping( \WC_Order $order, array $data ): void {
		$order->update_meta_data( '_famma_internal_shipping_cost', Config::instance()->internal_shipping_cost() );
	}
}

/* =============================== Tunisia ================================ */
final class Tunisia {
	private static ?Tunisia $i = null;
	public static function instance(): Tunisia { if ( null === self::$i ) { self::$i = new self(); self::$i->hooks(); } return self::$i; }
	private function hooks(): void {
		add_filter( 'woocommerce_states', array( $this, 'governorates' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_phone' ), 10, 2 );
	}
	public function governorates( array $states ): array {
		$states['TN'] = array(
			'TN-11'=>'Tunis','TN-12'=>'Ariana','TN-13'=>'Ben Arous','TN-14'=>'Manouba','TN-21'=>'Nabeul',
			'TN-22'=>'Zaghouan','TN-23'=>'Bizerte','TN-31'=>'Béja','TN-32'=>'Jendouba','TN-33'=>'Le Kef',
			'TN-34'=>'Siliana','TN-41'=>'Kairouan','TN-42'=>'Kasserine','TN-43'=>'Sidi Bouzid','TN-51'=>'Sousse',
			'TN-52'=>'Monastir','TN-53'=>'Mahdia','TN-61'=>'Sfax','TN-71'=>'Gafsa','TN-72'=>'Tozeur',
			'TN-73'=>'Kébili','TN-81'=>'Gabès','TN-82'=>'Médenine','TN-83'=>'Tataouine',
		);
		return $states;
	}
	public function checkout_fields( array $f ): array {
		unset( $f['billing']['billing_company'], $f['billing']['billing_address_2'], $f['billing']['billing_postcode'], $f['billing']['billing_country'] );
		if ( isset( $f['billing']['billing_phone'] ) ) {
			$f['billing']['billing_phone']['required'] = true;
			$f['billing']['billing_phone']['priority'] = 25;
			$f['billing']['billing_phone']['placeholder'] = '2X XXX XXX';
			$f['billing']['billing_phone']['label'] = __( 'Phone / الهاتف', 'famma-core' );
		}
		if ( isset( $f['billing']['billing_state'] ) ) { $f['billing']['billing_state']['label'] = __( 'Governorate / الولاية', 'famma-core' ); $f['billing']['billing_state']['required'] = true; }
		if ( isset( $f['billing']['billing_city'] ) ) { $f['billing']['billing_city']['label'] = __( 'City / Delegation — المدينة', 'famma-core' ); }
		if ( isset( $f['billing']['billing_address_1'] ) ) { $f['billing']['billing_address_1']['label'] = __( 'Address / العنوان', 'famma-core' ); }
		return $f;
	}
	public function validate_phone( array $data, \WP_Error $errors ): void {
		if ( empty( $data['billing_phone'] ) ) { return; }
		$raw    = preg_replace( '/[\s\-\.]/', '', (string) $data['billing_phone'] );
		$digits = preg_replace( '/^(?:\+?216|00216)/', '', $raw );
		if ( ! preg_match( '/^[2-9]\d{7}$/', $digits ) ) {
			$errors->add( 'billing_phone_invalid', __( 'Please enter a valid Tunisian phone number (8 digits). أدخل رقم هاتف تونسي صحيح.', 'famma-core' ) );
		}
	}
}

/* =============================== WhatsApp =============================== */
final class WhatsApp {
	private static ?WhatsApp $i = null;
	public static function instance(): WhatsApp { if ( null === self::$i ) { self::$i = new self(); self::$i->hooks(); } return self::$i; }
	private function hooks(): void { add_action( 'wp_footer', array( $this, 'render_float' ) ); add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) ); }
	public static function link( string $message = '' ): string {
		$n = Config::instance()->whatsapp_number();
		if ( '' === $n ) { return ''; }
		$u = 'https://wa.me/' . $n;
		if ( '' !== $message ) { $u .= '?text=' . rawurlencode( $message ); }
		return $u;
	}
	public function styles(): void {
		$css = '.famma-wa{position:fixed;right:16px;bottom:16px;z-index:9999;display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;background:#25D366;box-shadow:0 4px 14px rgba(0,0,0,.25);text-decoration:none}.famma-wa svg{width:30px;height:30px;fill:#fff}.famma-wa:focus-visible{outline:3px solid #ff7a00;outline-offset:2px}';
		wp_register_style( 'famma-wa', false, array(), FAMMA_CORE_VERSION );
		wp_enqueue_style( 'famma-wa' );
		wp_add_inline_style( 'famma-wa', $css );
	}
	public function render_float(): void {
		$href = self::link( get_bloginfo( 'name' ) . ' — ' . __( "Bonjour, j'ai une question 🙂 / سلام، عندي سؤال", 'famma-core' ) );
		if ( '' === $href ) { return; }
		printf(
			'<a class="famma-wa" href="%1$s" target="_blank" rel="noopener nofollow" aria-label="%2$s">%3$s</a>',
			esc_url( $href ),
			esc_attr__( 'Contact us on WhatsApp', 'famma-core' ),
			'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.4 5 5.1-1.3A10 10 0 1 0 12 2Zm5.9 14.1c-.2.7-1.4 1.4-2 1.4-.5.1-1.1.1-1.8-.1-.4-.1-1-.3-1.6-.6-2.9-1.2-4.7-4.1-4.9-4.3-.1-.2-1.1-1.4-1.1-2.7 0-1.3.7-1.9.9-2.2.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2.1.4 0 .5l-.4.6c-.1.2-.3.3-.1.6.1.3.6 1 1.3 1.6.9.8 1.6 1 1.9 1.2.2.1.4 0 .5-.1l.6-.7c.2-.2.4-.2.6-.1l1.8.9c.2.1.4.2.4.3.1.1.1.6-.1 1.2Z"/></svg>'
		);
	}
}

/* =============================== Tracking =============================== */
final class Tracking {
	private static ?Tracking $i = null;
	public static function instance(): Tracking { if ( null === self::$i ) { self::$i = new self(); self::$i->hooks(); } return self::$i; }
	private function hooks(): void {
		add_action( 'wp_head', array( $this, 'head_snippets' ), 5 );
		add_action( 'wp_footer', array( $this, 'utm_capture' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_utm_on_order' ), 30, 2 );
		add_action( 'woocommerce_order_status_delivered', array( $this, 'fire_purchase_on_delivery' ) );
	}
	public function head_snippets(): void {
		$cfg = Config::instance();
		if ( '' !== $cfg->google_site_verification() ) {
			printf( '<meta name="google-site-verification" content="%s" />' . "\n", esc_attr( $cfg->google_site_verification() ) );
		}
		$ga = $cfg->ga4_measurement_id();
		if ( '' !== $ga ) {
			printf( '<script async src="https://www.googletagmanager.com/gtag/js?id=%s"></script>' . "\n", esc_attr( rawurlencode( $ga ) ) );
			echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . wp_json_encode( $ga ) . ');</script>' . "\n";
		}
		$pixel = $cfg->meta_pixel_id();
		if ( '' !== $pixel ) {
			echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init'," . wp_json_encode( $pixel ) . ");fbq('track','PageView');</script>" . "\n";
		}
		$ttk = $cfg->tiktok_pixel_id();
		if ( '' !== $ttk ) {
			echo '<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];ttq.setAndDefer=function(e,n){e[n]=function(){e.push([n].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.load=function(e){var n="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=n;ttq._t=ttq._t||{};ttq._t[e]=+new Date;var o=d.createElement("script");o.type="text/javascript";o.async=!0;o.src=n+"?sdkid="+e;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load(' . wp_json_encode( $ttk ) . ');ttq.page()}(window,document,"ttq");</script>' . "\n";
		}
	}
	public function utm_capture(): void {
		echo '<script>(function(){try{var p=new URLSearchParams(location.search),k=["utm_source","utm_medium","utm_campaign","utm_content","utm_term"],o={},h=false;k.forEach(function(x){var v=p.get(x);if(v){o[x]=v;h=true;}});if(h){document.cookie="famma_utm="+encodeURIComponent(JSON.stringify(o))+";path=/;max-age="+(60*60*24*30)+";SameSite=Lax";}}catch(e){}})();</script>' . "\n";
	}
	public function stamp_utm_on_order( \WC_Order $order, array $data ): void {
		if ( empty( $_COOKIE['famma_utm'] ) ) { return; }
		$parsed = json_decode( sanitize_text_field( wp_unslash( $_COOKIE['famma_utm'] ) ), true );
		if ( is_array( $parsed ) ) {
			foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ) as $k ) {
				if ( ! empty( $parsed[ $k ] ) ) { $order->update_meta_data( '_famma_' . $k, sanitize_text_field( (string) $parsed[ $k ] ) ); }
			}
		}
	}
	public function fire_purchase_on_delivery( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_famma_purchase_sent' ) ) { return; }
		$event_id = 'famma_purchase_' . $order_id;
		$order->update_meta_data( '_famma_purchase_event_id', $event_id );
		$order->update_meta_data( '_famma_purchase_sent', current_time( 'mysql' ) );
		$order->save();
		// TODO server-side CAPI: POST Purchase to Graph API with this event_id for
		// dedup, using Config::instance()->meta_access_token() (never log the token).
		do_action( 'famma_capi_purchase', $order, $event_id );
	}
}

/* =============================== Bootstrap ============================== */
function boot(): void {
	load_plugin_textdomain( 'famma-core', false, dirname( plugin_basename( FAMMA_CORE_FILE ) ) . '/languages' );
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'FAMMA Core requires WooCommerce to be installed and active.', 'famma-core' ) . '</p></div>';
		} );
		return;
	}
	Config::instance(); Order_Statuses::instance(); Product_Financials::instance();
	Shipping::instance(); Tunisia::instance(); Tracking::instance(); WhatsApp::instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot' );

add_action( 'before_woocommerce_init', static function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FAMMA_CORE_FILE, true );
	}
} );

register_activation_hook( __FILE__, static function () {
	if ( class_exists( 'WooCommerce' ) ) { Order_Statuses::instance()->register_post_statuses(); }
	flush_rewrite_rules();
} );
