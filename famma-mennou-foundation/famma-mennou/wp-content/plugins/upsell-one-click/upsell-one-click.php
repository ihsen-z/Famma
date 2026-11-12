<?php
/**
 * Plugin Name:       Upsell One Click
 * Description:       One-click post-purchase upsell for cash on delivery stores: after checkout, the thank-you page offers a complementary product at a special price, added to the same order in one click, without filling the form again.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            FAMMA
 * Text Domain:       upsell-one-click
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 9.0
 * WC tested up to:   10.3
 *
 * @package upsell-one-click
 */

defined( 'ABSPATH' ) || exit;

define( 'UPSELL_OC_VERSION', '1.0.0' );
define( 'UPSELL_OC_FILE', __FILE__ );
define( 'UPSELL_OC_DIR', plugin_dir_path( __FILE__ ) );
define( 'UPSELL_OC_URL', plugin_dir_url( __FILE__ ) );

require_once UPSELL_OC_DIR . 'includes/class-offer.php';
require_once UPSELL_OC_DIR . 'includes/class-texts.php';
require_once UPSELL_OC_DIR . 'includes/class-icons.php';
require_once UPSELL_OC_DIR . 'includes/class-sheet-view.php';
require_once UPSELL_OC_DIR . 'includes/class-block-view.php';
require_once UPSELL_OC_DIR . 'includes/class-thankyou.php';
require_once UPSELL_OC_DIR . 'includes/class-stats.php';
require_once UPSELL_OC_DIR . 'includes/class-product-tab.php';

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', UPSELL_OC_FILE, true );
		}
	}
);

add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'upsell-one-click', false, dirname( plugin_basename( UPSELL_OC_FILE ) ) . '/languages' );
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		\UpsellOneClick\Thankyou::instance();
		if ( is_admin() ) {
			\UpsellOneClick\Product_Tab::instance();
			add_filter(
				'woocommerce_get_settings_pages',
				static function ( array $pages ): array {
					require_once UPSELL_OC_DIR . 'includes/class-settings-page.php';
					$pages[] = new \UpsellOneClick\Settings_Page();
					return $pages;
				}
			);
		}
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( UPSELL_OC_FILE ),
	static function ( array $links ): array {
		$url = admin_url( 'admin.php?page=wc-settings&tab=upsell_one_click' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'upsell-one-click' ) . '</a>' );
		return $links;
	}
);
