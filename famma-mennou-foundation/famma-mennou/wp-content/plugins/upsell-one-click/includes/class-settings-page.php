<?php
/**
 * WooCommerce > Settings > Upsell One Click: open statuses, payment
 * methods, colours and every customer-facing text.
 *
 * Loaded only when WooCommerce builds its settings pages.
 *
 * @package upsell-one-click
 */

namespace UpsellOneClick;

defined( 'ABSPATH' ) || exit;

/**
 * Store-wide settings page.
 */
final class Settings_Page extends \WC_Settings_Page {

	/**
	 * Sets the page ID and label.
	 */
	public function __construct() {
		$this->id    = 'upsell_one_click';
		$this->label = __( 'Upsell One Click', 'upsell-one-click' );
		parent::__construct();
	}

	/**
	 * Fields of the page.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_default_section() {
		$gateways = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
				$gateways[ $id ] = wp_strip_all_tags( (string) $gateway->get_method_title() );
			}
		}

		$fields = array(
			array(
				'title' => __( 'When the offer is shown', 'upsell-one-click' ),
				'type'  => 'title',
				'desc'  => __( 'The offer stays open while the order is in one of these statuses. Choose the statuses of an order not yet confirmed or shipped.', 'upsell-one-click' ),
				'id'    => 'upsell_oc_rules',
			),
			array(
				'title'   => __( 'Open order statuses', 'upsell-one-click' ),
				'id'      => 'upsell_oc_open_statuses',
				'type'    => 'multiselect',
				'class'   => 'wc-enhanced-select',
				'options' => wc_get_order_statuses(),
				'default' => array( 'wc-processing', 'wc-on-hold' ),
			),
			array(
				'title'    => __( 'Payment methods', 'upsell-one-click' ),
				'id'       => 'upsell_oc_payment_methods',
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'options'  => $gateways,
				'default'  => array( 'cod' ),
				'desc_tip' => __( 'Orders paid with these methods only. Keep cash on delivery: an order already paid online must not change its total.', 'upsell-one-click' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'upsell_oc_rules',
			),
			array(
				'title' => __( 'Colours', 'upsell-one-click' ),
				'type'  => 'title',
				'desc'  => __( 'Empty: default colours.', 'upsell-one-click' ),
				'id'    => 'upsell_oc_colors',
			),
			array(
				'title'   => __( 'Accent (border, offer label, add button, badge)', 'upsell-one-click' ),
				'id'      => 'upsell_oc_color_accent',
				'type'    => 'color',
				'css'     => 'width:6em;',
				'default' => '',
			),
			array(
				'title'   => __( 'Links, prices and focus', 'upsell-one-click' ),
				'id'      => 'upsell_oc_color_primary',
				'type'    => 'color',
				'css'     => 'width:6em;',
				'default' => '',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'upsell_oc_colors',
			),
			array(
				'title' => __( 'Texts', 'upsell-one-click' ),
				'type'  => 'title',
				'desc'  => __( 'Empty: the default text, shown in grey, is used. The "second language" line is optional and is shown under the main text (for example French and Arabic).', 'upsell-one-click' ),
				'id'    => 'upsell_oc_texts',
			),
		);

		foreach ( Texts::fields() as $key => $field ) {
			$fields[] = array(
				'title'       => $field['label'],
				'id'          => 'upsell_oc_text_' . $key,
				'type'        => 'text',
				'placeholder' => $field['default'],
				'default'     => '',
				'css'         => 'min-width:22em;',
			);
			if ( in_array( $key, Texts::WITH_ALT, true ) ) {
				$fields[] = array(
					/* translators: %s: label of the main text. */
					'title'   => sprintf( __( '%s — second language', 'upsell-one-click' ), $field['label'] ),
					'id'      => 'upsell_oc_text_' . $key . '_alt',
					'type'    => 'text',
					'default' => '',
					'css'     => 'min-width:22em;',
				);
			}
		}

		$fields[] = array(
			'type' => 'sectionend',
			'id'   => 'upsell_oc_texts',
		);

		return $fields;
	}
}
