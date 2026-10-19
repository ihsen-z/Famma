<?php
/**
 * Clone QA pixel, lot 2 : relevé de l'état du clone, en lecture seule, avant
 * et après une passe et de part et d'autre d'une bascule HPOS.
 *
 * Usage (depuis famma-mennou/, Git Bash, MSYS_NO_PATHCONV=1) :
 *   docker compose -p famma-qa-pixel -f scripts/compose-integration.yml \
 *     -f scripts/qa/compose-qa-pixel.yml run --rm -T -e FAMMA_QA_PIXEL_TEST=1 \
 *     -e FAMMA_TEST_DB_NAME=famma_integration -e FAMMA_TEST_DB_HOST=db \
 *     cli eval-file /tests/qa/qa-l2-etat.php
 *
 * Sortie : JSON. Réglages de suivi (le jeton n'est jamais affiché : seules sa
 * longueur et sa conformité au jeton factice du clone sont relevées), état
 * HPOS (option, stockage réellement utilisé, mode compatibilité), nombre de
 * lignes de commande dans chaque stockage, inventaire des commandes par
 * numéro, statut et préfixe de marquage (QA-L2, QA-L3...), sans nom, ni
 * téléphone, ni adresse ; restes de données QA-L2, registre CAPI, tâches
 * Action Scheduler famma en attente.
 *
 * N'écrit rien.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- WP-CLI read-only report, not an autoloaded plugin class file.

defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_QA_PIXEL_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This report requires WP-CLI and FAMMA_QA_PIXEL_TEST=1.' );
}
if ( 'local' !== wp_get_environment_type() || ! defined( 'DB_NAME' ) || 'famma_integration' !== DB_NAME || (string) getenv( 'FAMMA_TEST_DB_NAME' ) !== DB_NAME || ! defined( 'DB_HOST' ) || 'db' !== DB_HOST ) {
	throw new RuntimeException( 'Refusing: expected disposable local database identity is not explicitly confirmed.' );
}
if ( 'http://localhost:8980' !== untrailingslashit( home_url() ) ) {
	throw new RuntimeException( 'Refusing: only the QA pixel clone on http://localhost:8980 is allowed.' );
}

/** Read-only state report of the QA pixel clone. */
final class Famma_Qa_L2_Etat {

	/** Jeton factice du clone, jamais un vrai secret. */
	private const FAKE_TOKEN = 'QA-FAKE-TOKEN-NOT-REAL';

	/**
	 * Builds the report.
	 *
	 * @return array<string,mixed>
	 */
	public static function report(): array {
		$token = (string) get_option( 'famma_meta_access_token', '' );

		return array(
			'releve_utc'                => gmdate( 'c' ),
			'versions'                  => array(
				'wordpress'   => get_bloginfo( 'version' ),
				'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
				'famma_core'  => defined( 'FAMMA_CORE_VERSION' ) ? FAMMA_CORE_VERSION : '',
			),
			'reglages'                  => array(
				'famma_meta_pixel_id'            => self::option( 'famma_meta_pixel_id' ),
				'famma_meta_orderplaced_enabled' => self::option( 'famma_meta_orderplaced_enabled' ),
				'famma_meta_test_event_code'     => self::option( 'famma_meta_test_event_code' ),
				'famma_meta_capi_test_mode'      => self::option( 'famma_meta_capi_test_mode' ),
				'famma_meta_access_token_len'    => strlen( $token ),
				'jeton_est_factice'              => self::FAKE_TOKEN === $token,
				'woocommerce_currency'           => self::option( 'woocommerce_currency' ),
				'woocommerce_default_country'    => self::option( 'woocommerce_default_country' ),
				'cod_enabled'                    => (string) ( get_option( 'woocommerce_cod_settings', array() )['enabled'] ?? '' ),
			),
			'hpos'                      => self::hpos(),
			'stockage'                  => self::storage(),
			'commandes'                 => self::orders(),
			'produits_publies'          => count(
				wc_get_products(
					array(
						'status' => 'publish',
						'limit'  => -1,
						'return' => 'ids',
					)
				)
			),
			'restes_qa_l2'              => self::leftovers(),
			'registre_lignes'           => self::count_table( \Famma\Core\Capi_Delivery_Registry::instance()->table() ),
			'as_pending_famma'          => self::pending_famma_actions(),
			'session_tokens_qa_admin'   => self::admin_sessions(),
			'options_test_residuelles'  => self::residual_options(),
			'constantes_suivi_definies' => array_values(
				array_filter(
					array( 'FAMMA_META_PIXEL_ID', 'FAMMA_META_ACCESS_TOKEN', 'FAMMA_META_TEST_EVENT_CODE' ),
					static fn( string $name ): bool => defined( $name ) && '' !== (string) constant( $name )
				)
			),
		);
	}

	/**
	 * Option value, or a marker when the option does not exist.
	 *
	 * @param string $name Option name.
	 * @return string
	 */
	private static function option( string $name ): string {
		$value = get_option( $name, null );
		if ( null === $value ) {
			return '(absente)';
		}
		return is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
	}

	/**
	 * HPOS option and the storage WooCommerce really uses.
	 *
	 * @return array<string,mixed>
	 */
	private static function hpos(): array {
		$util = '\Automattic\WooCommerce\Utilities\OrderUtil';

		return array(
			'option_custom_orders_table_enabled' => self::option( 'woocommerce_custom_orders_table_enabled' ),
			'option_data_sync_enabled'           => self::option( 'woocommerce_custom_orders_table_data_sync_enabled' ),
			'stockage_hpos_utilise'              => class_exists( $util ) && $util::custom_orders_table_usage_is_enabled(),
			'table_des_commandes'                => class_exists( $util ) ? $util::get_table_for_orders() : '',
		);
	}

	/**
	 * Order rows in each storage.
	 *
	 * @return array<string,mixed>
	 */
	private static function storage(): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only storage census of the disposable clone.
		$posts = $wpdb->get_results( "SELECT post_type, COUNT(*) AS n FROM {$wpdb->posts} WHERE post_type IN ('shop_order','shop_order_placehold','shop_order_refund') GROUP BY post_type", ARRAY_A );
		$hpos  = array();
		if ( self::table_exists( $wpdb->prefix . 'wc_orders' ) ) {
			$hpos = $wpdb->get_results( $wpdb->prepare( 'SELECT type, COUNT(*) AS n FROM %i GROUP BY type', $wpdb->prefix . 'wc_orders' ), ARRAY_A );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'wp_posts'  => wp_list_pluck( (array) $posts, 'n', 'post_type' ),
			'wc_orders' => wp_list_pluck( (array) $hpos, 'n', 'type' ),
		);
	}

	/**
	 * Orders read through the WooCommerce CRUD (authoritative storage).
	 *
	 * @return array<string,mixed>
	 */
	private static function orders(): array {
		$ids    = wc_get_orders(
			array(
				'type'    => 'shop_order',
				'status'  => array_merge( array_keys( wc_get_order_statuses() ), array( 'trash' ) ),
				'limit'   => -1,
				'return'  => 'ids',
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);
		$list   = array();
		$prefix = array();
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$first = (string) $order->get_billing_first_name();
			$mark  = preg_match( '/^(QA-L\d+)/', $first, $m ) ? $m[1] : '(autre)';
			if ( '' !== (string) $order->get_meta( '_famma_qa_l2_run' ) ) {
				$mark = 'QA-L2';
			}
			$prefix[ $mark ] = ( $prefix[ $mark ] ?? 0 ) + 1;
			$list[]          = $id . ':' . $order->get_status() . ':' . $mark;
		}

		return array(
			'total'        => count( $list ),
			'par_marquage' => $prefix,
			'liste'        => $list,
		);
	}

	/**
	 * QA-L2 data left behind (orders, products, run markers).
	 *
	 * @return array<string,int>
	 */
	private static function leftovers(): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only leftover census of the disposable clone.
		$out = array(
			'postmeta_run' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", '_famma_qa_l2_run' ) ),
		);
		if ( self::table_exists( $wpdb->prefix . 'wc_orders_meta' ) ) {
			$out['wc_orders_meta_run'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE meta_key = %s', $wpdb->prefix . 'wc_orders_meta', '_famma_qa_l2_run' ) );
		}
		$out['produits_qa_l2'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title LIKE %s", 'QA-L2%' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $out;
	}

	/**
	 * Pending or running Action Scheduler tasks of famma-core, by hook.
	 *
	 * @return array<string,int>
	 */
	private static function pending_famma_actions(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'actionscheduler_actions';
		if ( ! self::table_exists( $table ) ) {
			return array();
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only queue census of the disposable clone.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT hook, COUNT(*) AS n FROM %i WHERE hook LIKE %s AND status IN (%s, %s) GROUP BY hook ORDER BY hook', $table, 'famma%', 'pending', 'in-progress' ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map( 'intval', wp_list_pluck( (array) $rows, 'n', 'hook' ) );
	}

	/**
	 * Number of live sessions of the QA administrator.
	 *
	 * @return int
	 */
	private static function admin_sessions(): int {
		$user = get_user_by( 'login', 'qa-admin' );
		return $user ? count( WP_Session_Tokens::get_instance( $user->ID )->get_all() ) : -1;
	}

	/**
	 * Options left by a test harness (QA-L2 context, probes).
	 *
	 * @return array<int,string>
	 */
	private static function residual_options(): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only option census of the disposable clone.
		return $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s ORDER BY option_name", '%qa\_l2%', 'famma\_%test%' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Row count of a table, or -1 when it does not exist.
	 *
	 * @param string $table Table name.
	 * @return int
	 */
	private static function count_table( string $table ): int {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) {
			return -1;
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only row count of the disposable clone.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Does a table exist?
	 *
	 * @param string $table Table name.
	 * @return bool
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check of the disposable clone.
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}

WP_CLI::line( (string) wp_json_encode( Famma_Qa_L2_Etat::report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
