<?php
/**
 * External transport guard for the disposable integration browser only.
 *
 * @package FammaIntegration
 */

defined( 'ABSPATH' ) || exit;

if ( 'local' !== wp_get_environment_type() || ! defined( 'DB_NAME' ) || 'famma_integration' !== DB_NAME ) {
	return;
}

/**
 * Prevent outgoing email from synthetic browser orders.
 *
 * @return bool
 */
function famma_integration_browser_mail(): bool {
	return true;
}
add_filter( 'pre_wp_mail', 'famma_integration_browser_mail', PHP_INT_MAX, 0 );

/**
 * Stop outgoing HTTP in the browser fixture. Suite-specific mocks run earlier.
 *
 * @param mixed $preempt Existing transport result.
 * @return mixed
 */
function famma_integration_browser_http( $preempt ) {
	return false === $preempt ? new WP_Error( 'famma_integration_transport', 'Isolated browser test: outgoing HTTP disabled.' ) : $preempt;
}
add_filter( 'pre_http_request', 'famma_integration_browser_http', PHP_INT_MAX );

/**
 * Mark orders containing the disposable browser products for later cleanup.
 *
 * @param WC_Order $order New checkout order.
 * @return void
 */
function famma_integration_browser_order( WC_Order $order ): void {
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		if ( $product && 'browser-20261010' === $product->get_meta( '_famma_integration_run', true ) ) {
			$order->update_meta_data( '_famma_browser_fixture', 'browser-20261010' );
			break;
		}
	}
}
add_action( 'woocommerce_checkout_create_order', 'famma_integration_browser_order' );

/**
 * Show intercepted browser tracking calls without loading any advertising SDK.
 *
 * @return void
 */
function famma_integration_browser_pixel(): void {
	?>
	<script>
	window.fbq = function () {
		var args = Array.prototype.slice.call(arguments);
		var panel = document.getElementById('famma-local-pixel-trace');
		if (!panel) {
			panel = document.createElement('details');
			panel.id = 'famma-local-pixel-trace';
			var title = document.createElement('summary');
			title.textContent = 'TEST LOCAL — Pixel intercepté, aucun envoi à Meta';
			panel.appendChild(title);
			document.body.appendChild(panel);
		}
		var entry = document.createElement('pre');
		entry.style.whiteSpace = 'pre-wrap';
		entry.textContent = JSON.stringify(args);
		panel.appendChild(entry);
	};
	</script>
	<?php
}
add_action( 'wp_footer', 'famma_integration_browser_pixel', 1 );

