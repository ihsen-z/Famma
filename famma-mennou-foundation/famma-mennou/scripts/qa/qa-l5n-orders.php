<?php
/**
 * Lot 5 du plan pixel, testeur navigateur (préfixe QA-L5N) : relevé des
 * commandes créées par le navigateur de test dans le clone famma-qa-pixel,
 * et, sur demande, exécution de LEUR tâche OrderPlaced en file.
 *
 * Deux modes (premier argument) :
 *
 *  - « check » (lecture seule) : canal de création, statut, préfixe de test,
 *    signaux CAPI (_famma_fbc, _famma_fbp, présence IP/UA), méta OrderPlaced,
 *    attribution WooCommerce (_wc_order_attribution_*), UTM famma et source de
 *    synthèse, tâches Action Scheduler famma_capi_orderplaced_send.
 *  - « send » : exécute, pour les seules commandes passées en argument, la
 *    tâche famma_capi_orderplaced_send en attente. Le clone est en
 *    environnement « local » : la garde D4 bloque tout envoi. Le filtre de
 *    banc de test `famma_meta_sending_allowed` est donc forcé à vrai pour CE
 *    processus WP-CLI uniquement (rien n'est écrit en base). La requête part
 *    vers le garde qa-pixel-guard.php, qui la journalise et répond une
 *    réponse simulée : aucun appel réel vers Meta. Si la tâche a déjà été
 *    consommée (par exemple par un « action-scheduler run » d'un autre
 *    testeur, qui sortirait « blocked_environment »), le relevé le dit et
 *    rien d'autre n'est tenté.
 *
 * Usage (depuis famma-mennou/, Git Bash, MSYS_NO_PATHCONV=1) :
 *   docker compose -p famma-qa-pixel -f scripts/compose-integration.yml \
 *     -f scripts/qa/compose-qa-pixel.yml run --rm cli \
 *     eval-file /tests/qa/qa-l5n-orders.php check 301 302
 *
 * Sortie : JSON. Ni nom complet, ni téléphone, ni adresse : seulement le
 * préfixe de test du prénom.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- $args est fourni par wp eval-file.

defined( 'ABSPATH' ) || exit;

if ( 'http://localhost:8980' !== untrailingslashit( home_url() ) ) {
	WP_CLI::error( 'Ce relevé ne tourne que sur le clone QA pixel (http://localhost:8980).' );
}

$famma_qa_args = array_values( (array) $args );
$famma_qa_mode = (string) array_shift( $famma_qa_args );
if ( ! in_array( $famma_qa_mode, array( 'check', 'send' ), true ) ) {
	WP_CLI::error( 'Mode attendu : check ou send.' );
}

/**
 * Tâches famma_capi_orderplaced_send d'une commande, par statut.
 *
 * @param int $order_id Commande.
 * @return array<int, array<string, mixed>>
 */
$famma_qa_actions = static function ( int $order_id ): array {
	$out = array();
	if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
		return $out;
	}
	foreach ( array( 'pending', 'in-progress', 'complete', 'failed', 'canceled' ) as $status ) {
		$ids = as_get_scheduled_actions(
			array(
				'hook'     => 'famma_capi_orderplaced_send',
				'args'     => array( 'order_id' => $order_id ),
				'status'   => $status,
				'per_page' => 20,
			),
			'ids'
		);
		foreach ( $ids as $id ) {
			$out[] = array(
				'id'     => (int) $id,
				'status' => $status,
			);
		}
	}
	return $out;
};

$famma_qa_report = array(
	'mode'    => $famma_qa_mode,
	'date'    => gmdate( 'c' ),
	'env'     => wp_get_environment_type(),
	'hpos'    => get_option( 'woocommerce_custom_orders_table_enabled' ),
	'options' => array(
		'famma_meta_orderplaced_enabled' => (string) get_option( 'famma_meta_orderplaced_enabled', '' ),
		'famma_meta_orderplaced_browser' => (string) get_option( 'famma_meta_orderplaced_browser', '<absente>' ),
		'famma_meta_data_profile'        => (string) get_option( 'famma_meta_data_profile', '<absente>' ),
		'jeton_present'                  => '' !== (string) get_option( 'famma_meta_access_token', '' ),
	),
	'orders'  => array(),
);

if ( 'send' === $famma_qa_mode ) {
	add_filter( 'famma_meta_sending_allowed', '__return_true', PHP_INT_MAX );
}

foreach ( $famma_qa_args as $famma_qa_arg ) {
	$famma_qa_id    = absint( $famma_qa_arg );
	$famma_qa_order = wc_get_order( $famma_qa_id );
	if ( ! $famma_qa_order instanceof WC_Order ) {
		$famma_qa_report['orders'][ $famma_qa_id ] = array( 'erreur' => 'commande introuvable' );
		continue;
	}

	$famma_qa_first = (string) $famma_qa_order->get_billing_first_name();
	$famma_qa_entry = array( 'prefixe_test' => 0 === strpos( $famma_qa_first, 'QA-L5N' ) ? 'QA-L5N' : 'AUTRE' );

	if ( 'send' === $famma_qa_mode ) {
		if ( 'QA-L5N' !== $famma_qa_entry['prefixe_test'] ) {
			$famma_qa_entry['envoi'] = 'refusé : commande hors préfixe QA-L5N';
		} else {
			$famma_qa_entry['avant'] = $famma_qa_actions( $famma_qa_id );
			$famma_qa_run            = array();
			foreach ( $famma_qa_entry['avant'] as $famma_qa_action ) {
				if ( 'pending' === $famma_qa_action['status'] ) {
					ActionScheduler::runner()->process_action( $famma_qa_action['id'], 'QA-L5N' );
					$famma_qa_run[] = $famma_qa_action['id'];
				}
			}
			$famma_qa_entry['taches_executees'] = $famma_qa_run;
			$famma_qa_entry['envoi']            = array() === $famma_qa_run ? 'aucune tâche en attente' : 'tâche(s) exécutée(s)';
			$famma_qa_order                     = wc_get_order( $famma_qa_id );
		}
	}

	$famma_qa_attr = array();
	foreach ( $famma_qa_order->get_meta_data() as $famma_qa_meta ) {
		$famma_qa_key = (string) $famma_qa_meta->key;
		if ( 0 === strpos( $famma_qa_key, '_wc_order_attribution_' ) ) {
			$famma_qa_value                 = $famma_qa_meta->value;
			$famma_qa_attr[ $famma_qa_key ] = '_wc_order_attribution_user_agent' === $famma_qa_key ? '<présent>' : $famma_qa_value;
		}
	}

	$famma_qa_report['orders'][ $famma_qa_id ] = $famma_qa_entry + array(
		'created_via'                 => $famma_qa_order->get_created_via(),
		'statut'                      => $famma_qa_order->get_status(),
		'total'                       => $famma_qa_order->get_total(),
		'_famma_fbc'                  => (string) $famma_qa_order->get_meta( '_famma_fbc' ),
		'_famma_fbp'                  => (string) $famma_qa_order->get_meta( '_famma_fbp' ),
		'_famma_client_ip_present'    => '' !== (string) $famma_qa_order->get_meta( '_famma_client_ip' ),
		'_famma_client_ua_present'    => '' !== (string) $famma_qa_order->get_meta( '_famma_client_ua' ),
		'orderplaced_checkout_accept' => (string) $famma_qa_order->get_meta( '_famma_orderplaced_checkout_accepted' ),
		'orderplaced_event_id'        => (string) $famma_qa_order->get_meta( '_famma_orderplaced_event_id' ),
		'orderplaced_sent'            => (string) $famma_qa_order->get_meta( '_famma_orderplaced_sent' ),
		'utm_source'                  => (string) $famma_qa_order->get_meta( '_famma_utm_source' ),
		'utm_medium'                  => (string) $famma_qa_order->get_meta( '_famma_utm_medium' ),
		'utm_campaign'                => (string) $famma_qa_order->get_meta( '_famma_utm_campaign' ),
		'_famma_order_source'         => $famma_qa_order->get_meta( '_famma_order_source' ),
		'attribution_woocommerce'     => $famma_qa_attr,
		'actions_orderplaced'         => $famma_qa_actions( $famma_qa_id ),
	);
}

WP_CLI::line( (string) wp_json_encode( $famma_qa_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
