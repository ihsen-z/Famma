<?php
/**
 * Clone QA pixel, lot 5 : partie serveur du tunnel COD de bout en bout
 * (ClaudeMemo §11), après la commande passée en HTTP par
 * scripts/qa/qa-l5-tunnel-cod.sh (panier → checkout COD réels).
 *
 * Modes (FAMMA_QA_L5_TUNNEL_MODE) :
 *   run      exécute la tâche OrderPlaced mise en file par le serveur web,
 *            puis, en administrateur : confirmée → expédiée → livrée, puis
 *            expédiée → livrée de nouveau ; relève la commande, le registre,
 *            le journal famma-capi et les requêtes interceptées par le garde
 *            QA (wp-content/uploads/qa-http-log.jsonl). La dérogation D4
 *            FAMMA_META_ALLOW_NON_PRODUCTION est définie dans CE processus
 *            seulement. Aucune simulation propre : c'est le garde QA du clone
 *            qui répond à graph.facebook.com (HTTP 200 simulé).
 *   cleanup  supprime la commande du tunnel.
 * FAMMA_QA_L5_ORDER : numéro de la commande ; elle doit porter le prénom
 * saisi « QA-L5I Tunnel » (prénom QA-L5I, nom Tunnel) et l'adresse du tunnel (garde-fou).
 *
 * Les e-mails de commande sont interceptés (pre_wp_mail), jamais envoyés.
 * Les options de santé sont tenues en mémoire (qa-l5-sandbox.php).
 *
 * @package Famma_Integration_Tests
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- WP-CLI check entry point, not an autoloaded plugin class file.

defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_QA_L5_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || 'famma_integration' !== DB_NAME || 'db' !== DB_HOST ) {
	throw new RuntimeException( 'Refusing: QA clone only (FAMMA_QA_L5_TEST=1, local, famma_integration on db).' );
}
if ( 'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) || 8980 !== (int) wp_parse_url( home_url(), PHP_URL_PORT ) || ! class_exists( 'Famma_Qa_Pixel_Guard' ) || ! Famma_Qa_Pixel_Guard::active() ) {
	throw new RuntimeException( 'Refusing: only the QA pixel clone with its HTTP guard.' );
}
if ( defined( 'FAMMA_META_ALLOW_NON_PRODUCTION' ) ) {
	throw new RuntimeException( 'Refusing: the D4 derogation must not be predefined.' );
}
require_once is_readable( '/tests/qa/qa-l5-sandbox.php' ) ? '/tests/qa/qa-l5-sandbox.php' : __DIR__ . '/qa-l5-sandbox.php';

$famma_tunnel_id    = absint( getenv( 'FAMMA_QA_L5_ORDER' ) );
$famma_tunnel_order = wc_get_order( $famma_tunnel_id );
// Le checkout famma découpe le nom complet : prénom « QA-L5I », nom « Tunnel ».
if ( ! $famma_tunnel_order instanceof WC_Order || 'QA-L5I' !== $famma_tunnel_order->get_billing_first_name() || 'Tunnel' !== $famma_tunnel_order->get_billing_last_name() || 'QA-L5I adresse fictive du tunnel' !== $famma_tunnel_order->get_billing_address_1() ) {
	throw new RuntimeException( 'Refusing: FAMMA_QA_L5_ORDER is not the QA-L5I tunnel order.' );
}
$famma_tunnel_mode = (string) getenv( 'FAMMA_QA_L5_TUNNEL_MODE' );

if ( 'cleanup' === $famma_tunnel_mode ) {
	as_unschedule_all_actions( 'famma_capi_orderplaced_send', array( 'order_id' => $famma_tunnel_id ), 'famma-capi' );
	as_unschedule_all_actions( \Famma\Core\Order_Emails::ACTION, array( $famma_tunnel_id ), \Famma\Core\Order_Emails::GROUP );
	foreach ( array( 'OrderPlaced', 'Purchase' ) as $famma_event ) {
		as_unschedule_all_actions( \Famma\Core\Capi_Retry::HOOK, \Famma\Core\Capi_Retry::args( $famma_tunnel_id, $famma_event ), \Famma\Core\Capi_Retry::GROUP );
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- registry rows of this QA order only.
	$wpdb->delete( \Famma\Core\Capi_Delivery_Registry::instance()->table(), array( 'order_id' => $famma_tunnel_id ), array( '%d' ) );
	$famma_tunnel_order->delete( true );
	WP_CLI::line(
		wp_json_encode(
			array(
				'cleanup'       => $famma_tunnel_id,
				'existe_encore' => (bool) wc_get_order( $famma_tunnel_id ),
			)
		)
	);
	return;
}
if ( 'run' !== $famma_tunnel_mode ) {
	throw new RuntimeException( 'Unknown FAMMA_QA_L5_TUNNEL_MODE.' );
}

define( 'FAMMA_META_ALLOW_NON_PRODUCTION', true );
Famma_Qa_L5_Sandbox::options( Famma_Qa_L5_Sandbox::HEALTH_OPTIONS );
Famma_Qa_L5_Sandbox::mails();

/** Helpers of the tunnel (static methods). */
final class Famma_Qa_L5_Tunnel {
	/**
	 * Lines of the QA guard HTTP journal added since a byte offset, for one order.
	 *
	 * Le journal est partagé avec les autres agents : seules les requêtes vers
	 * graph.facebook.com dont l'event_id finit par _<n° de commande> sont gardées.
	 *
	 * @param int $from     Offset.
	 * @param int $order_id Order.
	 * @return array<int,array>
	 */
	public static function guard_lines( int $from, int $order_id ): array {
		$file = WP_CONTENT_DIR . '/uploads/qa-http-log.jsonl';
		clearstatcache( true, $file );
		$raw = (string) file_get_contents( $file, false, null, $from ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local QA journal of the clone.
		$out = array();
		foreach ( array_filter( explode( "\n", $raw ) ) as $line ) {
			$entry    = json_decode( $line, true );
			$event_id = is_array( $entry ) && is_array( $entry['body'] ?? null ) ? (string) ( $entry['body']['data'][0]['event_id'] ?? '' ) : '';
			if ( 'graph.facebook.com' === ( $entry['host'] ?? '' ) && 1 === preg_match( '/^famma_(orderplaced|purchase)_' . $order_id . '$/', $event_id ) ) {
				$out[] = $entry;
			}
		}
		return $out;
	}

	/**
	 * Journal lines (famma-capi source) of one order, from the WooCommerce file logs.
	 *
	 * @param int $order_id Order.
	 * @return array<int,string>
	 */
	public static function capi_lines( int $order_id ): array {
		$out = array();
		foreach ( (array) glob( WP_CONTENT_DIR . '/uploads/wc-logs/famma-capi-*.log' ) as $file ) {
			foreach ( (array) file( $file, FILE_IGNORE_NEW_LINES ) as $line ) {
				if ( 1 === preg_match( '/ (OrderPlaced|Purchase) order=' . $order_id . ' /', (string) $line ) ) {
					$out[] = (string) $line;
				}
			}
		}
		return $out;
	}

	/**
	 * Snapshot of the tunnel order.
	 *
	 * @param int $id Order.
	 * @return array<string,mixed>
	 */
	public static function snapshot( int $id ): array {
		$o = wc_get_order( $id );
		$o->read_meta_data( true );
		return array(
			'statut'                  => $o->get_status(),
			'created_via'             => $o->get_created_via(),
			'total'                   => (float) $o->get_total(),
			'devise'                  => $o->get_currency(),
			'is_paid'                 => $o->is_paid(),
			'date_paid'               => $o->get_date_paid() ? $o->get_date_paid()->date( 'c' ) : null,
			'_famma_cod_paid'         => $o->get_meta( '_famma_cod_paid' ),
			'_famma_orderplaced_sent' => (bool) $o->get_meta( '_famma_orderplaced_sent' ),
			'_famma_purchase_sent'    => (bool) $o->get_meta( '_famma_purchase_sent' ),
			'_famma_order_source'     => $o->get_meta( '_famma_order_source' ),
			'_famma_fbc_present'      => '' !== (string) $o->get_meta( '_famma_fbc' ),
			'_famma_fbp_present'      => '' !== (string) $o->get_meta( '_famma_fbp' ),
			'_famma_client_ua'        => '' !== (string) $o->get_meta( '_famma_client_ua' ),
			'attribution'             => $o->get_meta( '_wc_order_attribution_source_type' ),
			'_famma_checkout_key'     => '' !== (string) $o->get_meta( '_famma_checkout_key' ),
		);
	}
}

$famma_admins   = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
$famma_log_file = WP_CONTENT_DIR . '/uploads/qa-http-log.jsonl';
clearstatcache( true, $famma_log_file );
$famma_offset  = (int) filesize( $famma_log_file );
$famma_before  = Famma_Qa_L5_Tunnel::snapshot( $famma_tunnel_id );
$famma_pending = as_get_scheduled_actions(
	array(
		'hook'     => 'famma_capi_orderplaced_send',
		'group'    => 'famma-capi',
		'args'     => array( 'order_id' => $famma_tunnel_id ),
		'status'   => 'pending',
		'per_page' => 5,
	),
	'ids'
);
$famma_ran     = array();
foreach ( $famma_pending as $famma_action ) {
	ActionScheduler::runner()->process_action( (int) $famma_action, 'QA-L5I tunnel' );
	$famma_ran[ (int) $famma_action ] = ActionScheduler::store()->get_status( (int) $famma_action );
}
$famma_after_placed = Famma_Qa_L5_Tunnel::snapshot( $famma_tunnel_id );

wp_set_current_user( (int) ( $famma_admins[0] ?? 0 ) );
$famma_steps = array();
foreach ( array( 'confirmed', 'shipped', 'delivered' ) as $famma_status ) {
	wc_get_order( $famma_tunnel_id )->update_status( $famma_status, 'QA-L5I tunnel COD : passage à ' . $famma_status . ' par l\'administrateur.' );
	$famma_steps[ $famma_status ] = count( Famma_Qa_L5_Tunnel::guard_lines( $famma_offset, $famma_tunnel_id ) );
}
$famma_after_delivered = Famma_Qa_L5_Tunnel::snapshot( $famma_tunnel_id );
wc_get_order( $famma_tunnel_id )->update_status( 'shipped', 'QA-L5I tunnel COD : retour en expédiée.' );
wc_get_order( $famma_tunnel_id )->update_status( 'delivered', 'QA-L5I tunnel COD : seconde livraison.' );
wp_set_current_user( 0 );

$famma_guard  = Famma_Qa_L5_Tunnel::guard_lines( $famma_offset, $famma_tunnel_id );
$famma_events = array();
foreach ( $famma_guard as $famma_entry ) {
	$famma_body     = is_array( $famma_entry['body'] ?? null ) ? $famma_entry['body'] : array();
	$famma_event    = (array) ( $famma_body['data'][0] ?? array() );
	$famma_events[] = array(
		'url'             => $famma_entry['url'] ?? null,
		'methode'         => $famma_entry['method'] ?? null,
		'issue'           => $famma_entry['outcome'] ?? null,
		'contexte'        => $famma_entry['context'] ?? null,
		'event_name'      => $famma_event['event_name'] ?? null,
		'event_id'        => $famma_event['event_id'] ?? null,
		'action_source'   => $famma_event['action_source'] ?? null,
		'value'           => $famma_event['custom_data']['value'] ?? null,
		'currency'        => $famma_event['custom_data']['currency'] ?? null,
		'cles_user_data'  => array_keys( (array) ( $famma_event['user_data'] ?? array() ) ),
		'test_event_code' => $famma_body['test_event_code'] ?? '(absent)',
	);
}
$famma_notes    = array_map(
	static fn( $n ): string => (string) $n->content,
	wc_get_order_notes(
		array(
			'order_id' => $famma_tunnel_id,
			'orderby'  => 'date_created_gmt',
			'order'    => 'ASC',
		)
	)
);
$famma_registry = array();
foreach ( array(
	'OrderPlaced' => 'famma_orderplaced_',
	'Purchase'    => 'famma_purchase_',
) as $famma_name => $famma_prefix ) {
	$famma_row                     = \Famma\Core\Capi_Delivery_Registry::instance()->read( hash( 'sha256', \Famma\Core\Config::instance()->meta_pixel_id() . '|' . $famma_name . '|' . $famma_prefix . $famma_tunnel_id ) );
	$famma_registry[ $famma_name ] = array() === $famma_row ? '(aucune ligne)' : array(
		'state'         => $famma_row['state'],
		'attempt_count' => (int) $famma_row['attempt_count'],
	);
}
$famma_purchases = array_values( array_filter( $famma_events, static fn( array $e ): bool => 'Purchase' === $e['event_name'] ) );
$famma_placed    = array_values( array_filter( $famma_events, static fn( array $e ): bool => 'OrderPlaced' === $e['event_name'] ) );
$famma_note_pos  = static function ( string $needle ) use ( $famma_notes ): int {
	foreach ( $famma_notes as $i => $note ) {
		if ( str_contains( $note, $needle ) ) {
			return (int) $i;
		}
	}
	return -1;
};
$famma_final     = Famma_Qa_L5_Tunnel::snapshot( $famma_tunnel_id );
$famma_checks    = array(
	'orderplaced-mis-en-file-par-le-web' => 1 === count( $famma_pending ),
	'orderplaced-1-envoi'                => 1 === count( $famma_placed ) && 'famma_orderplaced_' . $famma_tunnel_id === $famma_placed[0]['event_id'] && 'website' === $famma_placed[0]['action_source'],
	'aucun-envoi-avant-livree'           => 1 === $famma_steps['confirmed'] && 1 === $famma_steps['shipped'],
	'purchase-1-seul-envoi'              => 1 === count( $famma_purchases ) && 'famma_purchase_' . $famma_tunnel_id === $famma_purchases[0]['event_id'],
	'purchase-valeur-reelle-tnd'         => isset( $famma_purchases[0] ) && abs( (float) $famma_purchases[0]['value'] - $famma_final['total'] ) < 0.001 && 'TND' === $famma_purchases[0]['currency'],
	'encaissement-avant-purchase'        => $famma_note_pos( 'COD payment collected on delivery' ) >= 0 && $famma_note_pos( 'COD payment collected on delivery' ) < $famma_note_pos( 'Meta Purchase dispatch started' ),
	'commande-payee'                     => $famma_final['is_paid'] && 'yes' === $famma_final['_famma_cod_paid'] && $famma_final['_famma_purchase_sent'],
	'registre-accepte'                   => is_array( $famma_registry['Purchase'] ) && 'accepted' === $famma_registry['Purchase']['state'] && is_array( $famma_registry['OrderPlaced'] ) && 'accepted' === $famma_registry['OrderPlaced']['state'],
	'sans-test-event-code'               => array( '(absent)' ) === array_values( array_unique( array_column( $famma_events, 'test_event_code' ) ) ),
	'version-v26'                        => array() === array_filter( $famma_events, static fn( array $e ): bool => ! str_contains( (string) $e['url'], '/v26.0/' ) ),
);
WP_CLI::line(
	wp_json_encode(
		array(
			'suite'               => 'qa-l5-tunnel-cod',
			'commande'            => $famma_tunnel_id,
			'famma_core'          => FAMMA_CORE_VERSION,
			'hpos'                => \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),
			'derogation'          => 'FAMMA_META_ALLOW_NON_PRODUCTION définie dans ce processus',
			'avant'               => $famma_before,
			'taches_orderplaced'  => $famma_ran,
			'apres_orderplaced'   => $famma_after_placed,
			'requetes_par_etape'  => $famma_steps,
			'apres_livraison'     => $famma_after_delivered,
			'final'               => $famma_final,
			'requetes_meta_garde' => $famma_events,
			'registre'            => $famma_registry,
			'journal_famma_capi'  => Famma_Qa_L5_Tunnel::capi_lines( $famma_tunnel_id ),
			'notes'               => $famma_notes,
			'mails_interceptes'   => Famma_Qa_L5_Sandbox::$mails,
			'checks'              => $famma_checks,
			'checks_false'        => count( array_filter( $famma_checks, static fn( bool $v ): bool => ! $v ) ),
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	)
);
