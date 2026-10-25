<?php
/**
 * Lot 3 du plan pixel (T3.3) : relevé, en lecture seule, des commandes QA-L3
 * créées par le navigateur de test dans le clone famma-qa-pixel.
 *
 * Pour chaque commande passée en argument : canal de création, statut,
 * méta des signaux CAPI (_famma_fbc, _famma_fbp, présence IP/UA), méta
 * OrderPlaced, et actions Action Scheduler famma_capi_orderplaced_send
 * correspondantes. Aucune écriture, aucun envoi.
 *
 * Usage (depuis famma-mennou/, Git Bash, MSYS_NO_PATHCONV=1) :
 *   docker compose -p famma-qa-pixel -f scripts/compose-integration.yml \
 *     -f scripts/qa/compose-qa-pixel.yml run --rm cli \
 *     eval-file /tests/qa/qa-l3-orders-check.php 142 143
 *
 * Sortie : JSON. Ni nom complet, ni téléphone, ni adresse : seulement le
 * préfixe QA-L3 du prénom, pour prouver qu'il s'agit d'une donnée de test.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- $args est fourni par wp eval-file.

defined( 'ABSPATH' ) || exit;

if ( 'http://localhost:8980' !== untrailingslashit( home_url() ) ) {
	WP_CLI::error( 'Ce relevé ne tourne que sur le clone QA pixel (http://localhost:8980).' );
}

$famma_qa_report = array();
foreach ( (array) $args as $famma_qa_arg ) {
	$famma_qa_id    = absint( $famma_qa_arg );
	$famma_qa_order = wc_get_order( $famma_qa_id );
	if ( ! $famma_qa_order instanceof WC_Order ) {
		$famma_qa_report[ $famma_qa_id ] = array( 'erreur' => 'commande introuvable' );
		continue;
	}
	$famma_qa_first = (string) $famma_qa_order->get_billing_first_name();

	$famma_qa_actions = array();
	if ( class_exists( 'ActionScheduler' ) ) {
		foreach ( array( 'pending', 'in-progress', 'complete', 'failed', 'canceled' ) as $famma_qa_status ) {
			$famma_qa_ids = as_get_scheduled_actions(
				array(
					'hook'     => 'famma_capi_orderplaced_send',
					'args'     => array( 'order_id' => $famma_qa_id ),
					'status'   => $famma_qa_status,
					'per_page' => 20,
				),
				'ids'
			);
			foreach ( $famma_qa_ids as $famma_qa_action ) {
				$famma_qa_actions[] = array(
					'id'     => (int) $famma_qa_action,
					'status' => $famma_qa_status,
				);
			}
		}
	}

	$famma_qa_report[ $famma_qa_id ] = array(
		'prefixe_test'                => 0 === strpos( $famma_qa_first, 'QA-L3' ) ? 'QA-L3' : 'AUTRE',
		'created_via'                 => $famma_qa_order->get_created_via(),
		'statut'                      => $famma_qa_order->get_status(),
		'_famma_fbc'                  => (string) $famma_qa_order->get_meta( '_famma_fbc' ),
		'_famma_fbp'                  => (string) $famma_qa_order->get_meta( '_famma_fbp' ),
		'_famma_client_ip_present'    => '' !== (string) $famma_qa_order->get_meta( '_famma_client_ip' ),
		'_famma_client_ua_present'    => '' !== (string) $famma_qa_order->get_meta( '_famma_client_ua' ),
		'orderplaced_checkout_accept' => (string) $famma_qa_order->get_meta( '_famma_orderplaced_checkout_accepted' ),
		'orderplaced_event_id'        => (string) $famma_qa_order->get_meta( '_famma_orderplaced_event_id' ),
		'orderplaced_sent'            => (string) $famma_qa_order->get_meta( '_famma_orderplaced_sent' ),
		'utm_source'                  => (string) $famma_qa_order->get_meta( '_famma_utm_source' ),
		'actions_orderplaced'         => $famma_qa_actions,
	);
}

$famma_qa_options = array(
	'famma_meta_orderplaced_enabled' => (string) get_option( 'famma_meta_orderplaced_enabled', '' ),
	'famma_meta_pixel_id'            => (string) get_option( 'famma_meta_pixel_id', '' ),
	'jeton_present'                  => '' !== (string) get_option( 'famma_meta_access_token', '' ),
);

WP_CLI::line(
	(string) wp_json_encode(
		array(
			'commandes' => $famma_qa_report,
			'options'   => $famma_qa_options,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	)
);
