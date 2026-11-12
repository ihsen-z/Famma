<?php
/**
 * "Upsell One Click" tab of the product data box (admin only).
 *
 * On the purchased product, the merchant picks the offered product, its
 * special price, the block texts and the quick view content, and sees the
 * results of the last 30 days.
 *
 * @package upsell-one-click
 */

namespace UpsellOneClick;

defined( 'ABSPATH' ) || exit;

/**
 * Product settings and results.
 */
final class Product_Tab {

	/**
	 * Shared instance.
	 *
	 * @var Product_Tab|null
	 */
	private static ?Product_Tab $i = null;

	/**
	 * Returns the shared instance, registering the hooks on first call.
	 *
	 * @return Product_Tab
	 */
	public static function instance(): Product_Tab {
		if ( null === self::$i ) {
			self::$i = new self();
			add_filter( 'woocommerce_product_data_tabs', array( self::$i, 'add_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( self::$i, 'render_panel' ) );
			add_action( 'woocommerce_admin_process_product_object', array( self::$i, 'save' ) );
		}
		return self::$i;
	}

	/**
	 * Adds the tab.
	 *
	 * @param array<string, array<string, mixed>> $tabs Existing tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_tab( array $tabs ): array {
		$tabs['upsell_oc_offer'] = array(
			'label'    => __( 'Upsell One Click', 'upsell-one-click' ),
			'target'   => 'upsell_oc_offer_panel',
			'class'    => array(),
			'priority' => 75,
		);
		return $tabs;
	}

	/**
	 * Tab content.
	 *
	 * @return void
	 */
	public function render_panel(): void {
		global $product_object;

		$product = $product_object instanceof \WC_Product ? $product_object : null;
		$value   = static function ( string $key ) use ( $product ): string {
			return $product ? (string) $product->get_meta( $key ) : '';
		};
		$money   = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );

		echo '<div id="upsell_oc_offer_panel" class="panel woocommerce_options_panel hidden"><div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id'          => Offer::META_ENABLED,
				'value'       => 'yes' === $value( Offer::META_ENABLED ) ? 'yes' : 'no',
				'label'       => __( 'Offer a product', 'upsell-one-click' ),
				'description' => __( 'After an order of this product, the thank-you page offers the product below, added to the same order in one click. The offer closes as soon as the order leaves the open statuses set in WooCommerce > Settings > Upsell One Click.', 'upsell-one-click' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => Offer::META_PRODUCT,
				'value'       => $value( Offer::META_PRODUCT ),
				'label'       => __( 'Offered product', 'upsell-one-click' ),
				'options'     => $this->product_choices( $product ? $product->get_id() : 0 ),
				'desc_tip'    => false,
				'description' => __( 'Published simple products only: the add must take one click.', 'upsell-one-click' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => Offer::META_PRICE,
				'value'             => $value( Offer::META_PRICE ),
				/* translators: %s: currency symbol. */
				'label'             => sprintf( __( 'Special price (%s)', 'upsell-one-click' ), $money ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 'any',
					'min'  => '0',
				),
				'desc_tip'          => false,
				'description'       => __( 'Price of the product in the offer. The regular price is shown struck through next to it.', 'upsell-one-click' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => Offer::META_TITLE,
				'value'       => $value( Offer::META_TITLE ),
				'label'       => __( 'Block title', 'upsell-one-click' ),
				'placeholder' => Texts::get( 'title' ),
				'desc_tip'    => false,
				'description' => __( 'Optional. Empty: default title from the settings.', 'upsell-one-click' ),
			)
		);

		woocommerce_wp_textarea_input(
			array(
				'id'          => Offer::META_TEXT,
				'value'       => $value( Offer::META_TEXT ),
				'label'       => __( 'Block text', 'upsell-one-click' ),
				'placeholder' => Texts::get( 'text' ),
				'desc_tip'    => false,
				'description' => __( 'Optional. Empty: default text from the settings.', 'upsell-one-click' ),
			)
		);

		echo '</div><div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id'          => Offer::META_SHEET,
				'value'       => 'no' === $value( Offer::META_SHEET ) ? 'no' : 'yes',
				'label'       => __( 'Quick view', 'upsell-one-click' ),
				'description' => __( 'A tap on the photo or on the link opens a quick view (all photos, key points, usage) without leaving the page, with the add button at the bottom.', 'upsell-one-click' ),
			)
		);

		woocommerce_wp_textarea_input(
			array(
				'id'          => Offer::META_POINTS,
				'value'       => $value( Offer::META_POINTS ),
				'label'       => __( 'Key points (quick view)', 'upsell-one-click' ),
				'rows'        => 4,
				'placeholder' => __( 'One point per line, 3 at most. Optional second language after "|", for example: Reusable | قابلة لإعادة الاستعمال', 'upsell-one-click' ),
				'desc_tip'    => false,
				'description' => __( 'Empty: the short description of the offered product is shown.', 'upsell-one-click' ),
			)
		);

		woocommerce_wp_textarea_input(
			array(
				'id'          => Offer::META_USAGE,
				'value'       => $value( Offer::META_USAGE ),
				'label'       => __( 'How to use it', 'upsell-one-click' ),
				'rows'        => 2,
				'desc_tip'    => false,
				'description' => __( 'Optional. One or two sentences. Empty: the section is hidden.', 'upsell-one-click' ),
			)
		);

		echo '</div>';

		if ( $product && $product->get_id() > 0 ) {
			$this->render_stats( $product->get_id() );
		}

		echo '</div>';
	}

	/**
	 * Results of the last 30 days.
	 *
	 * @param int $product_id Triggering product.
	 * @return void
	 */
	private function render_stats( int $product_id ): void {
		$s    = Stats::get( $product_id );
		$rate = $s['shown'] > 0 ? round( 100 * $s['accepted'] / $s['shown'] ) : 0;

		echo '<div class="options_group"><p class="form-field"><strong>' . esc_html__( 'Results over 30 days', 'upsell-one-click' ) . '</strong><br />';
		echo esc_html(
			sprintf(
				/* translators: 1: times shown, 2: times accepted, 3: acceptance rate, 4: times declined, 5: amount added. */
				__( 'Offer shown %1$d times, accepted %2$d times (%3$d%%), declined %4$d times. Amount added: %5$s.', 'upsell-one-click' ),
				$s['shown'],
				$s['accepted'],
				$rate,
				$s['declined'],
				Texts::money( (float) $s['amount'] )
			)
		);
		echo '<br />' . esc_html(
			sprintf(
				/* translators: 1: times the quick view was opened, 2: offers accepted after opening it. */
				__( 'Quick view opened %1$d times, %2$d of them followed by an add.', 'upsell-one-click' ),
				$s['viewed'],
				$s['viewed_accepted']
			)
		);
		echo '<br /><span class="description">' . esc_html__( 'Updated every 10 minutes.', 'upsell-one-click' ) . '</span></p></div>';
	}

	/**
	 * Products that can be offered.
	 *
	 * @param int $exclude Product being edited.
	 * @return array<string, string>
	 */
	private function product_choices( int $exclude ): array {
		$choices = array( '' => __( '— Choose a product —', 'upsell-one-click' ) );
		$ids     = wc_get_products(
			array(
				'status'  => 'publish',
				'type'    => 'simple',
				'limit'   => 200,
				'orderby' => 'title',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);

		foreach ( (array) $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p instanceof \WC_Product || $p->get_id() === $exclude || ! Offer::offerable( $p ) ) {
				continue;
			}
			/* translators: 1: product name, 2: product ID, 3: price. */
			$choices[ (string) $p->get_id() ] = sprintf( __( '%1$s (#%2$d, %3$s)', 'upsell-one-click' ), $p->get_name(), $p->get_id(), Texts::money( (float) $p->get_price() ) );
		}

		return $choices;
	}

	/**
	 * Saves the settings (nonce, capability, sanitizing).
	 *
	 * @param \WC_Product $product Saved product.
	 * @return void
	 */
	public function save( \WC_Product $product ): void {
		$nonce = isset( $_POST['woocommerce_meta_nonce'] ) ? sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		$target = isset( $_POST[ Offer::META_PRODUCT ] ) ? absint( wp_unslash( $_POST[ Offer::META_PRODUCT ] ) ) : 0;
		$price  = isset( $_POST[ Offer::META_PRICE ] ) ? (float) wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ Offer::META_PRICE ] ) ) ) : 0.0;
		if ( $target === $product->get_id() ) {
			$target = 0;
		}

		$product->update_meta_data( Offer::META_ENABLED, isset( $_POST[ Offer::META_ENABLED ] ) ? 'yes' : 'no' );
		$product->update_meta_data( Offer::META_PRODUCT, $target > 0 ? (string) $target : '' );
		$product->update_meta_data( Offer::META_PRICE, $price > 0 ? wc_format_decimal( $price, wc_get_price_decimals() ) : '' );
		$product->update_meta_data( Offer::META_TITLE, isset( $_POST[ Offer::META_TITLE ] ) ? sanitize_text_field( wp_unslash( $_POST[ Offer::META_TITLE ] ) ) : '' );
		$product->update_meta_data( Offer::META_SHEET, isset( $_POST[ Offer::META_SHEET ] ) ? 'yes' : 'no' );
		foreach ( array( Offer::META_TEXT, Offer::META_POINTS, Offer::META_USAGE ) as $key ) {
			$product->update_meta_data( $key, isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '' );
		}
	}
}
