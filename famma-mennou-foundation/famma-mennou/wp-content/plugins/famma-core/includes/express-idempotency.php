<?php
/**
 * Persistent, atomic express checkout intentions, independent of Woo order storage.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/** A unique request record prevents concurrent checkout creation and unsafe replay. */
final class Express_Idempotency {
	/** Shared instance.
	 *
	 * @var self|null
	 */
	private static ?self $i = null;
	/** Request owned by this PHP execution, never by a replay.
	 *
	 * @var string
	 */
	private string $active = '';
	/** Session digest of the owned request.
	 *
	 * @var string
	 */
	private string $session = '';

	/** Return shared hooks instance. @return self */
	public static function instance(): self {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'init', array( self::$i, 'install' ), 5 );
			add_action( 'woocommerce_checkout_create_order', array( self::$i, 'stamp' ), 1 );
			add_action( 'woocommerce_checkout_order_created', array( self::$i, 'link' ), 1 );
			add_action( 'woocommerce_after_checkout_validation', array( self::$i, 'validation' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_payment_successful_result', array( self::$i, 'accepted' ), 10000, 2 );
			add_filter( 'woocommerce_checkout_no_payment_needed_redirect', array( self::$i, 'free_order' ), 10000, 2 );
		}
		return self::$i;
	}

	/** Install the versioned schema without touching WooCommerce order tables. */
	public function install(): void {
		if ( '1' === get_option( 'famma_express_registry_version', '' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $this->table();
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
			request_id char(36) NOT NULL,
			session_hash char(64) NOT NULL,
			payload_hash char(64) NOT NULL,
			state varchar(24) NOT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			redirect text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (request_id),
			KEY order_id (order_id)
		) $collate;"
		);
		// Only claim migration completion when the actual table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- schema verification cannot use order CRUD or a stale cache.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( $table === $found ) {
			update_option( 'famma_express_registry_version', '1', false );
		}
	}

	/** Return trusted registry name. @return string */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'famma_express_requests';
	}

	/** Validate strong client-generated UUID.
	 *
	 * @param string $id UUID.
	 * @return bool
	 */
	public static function valid_id( string $id ): bool {
		return 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $id );
	}

	/** Digest the current Woo session, without storing its cookie. @return string */
	public static function session_hash(): string {
		return hash_hmac( 'sha256', (string) WC()->session->get_customer_id() . '|' . wp_get_session_token(), wp_salt( 'nonce' ) );
	}

	/** Hash scalar posted intent data, excluding replaceable authentication. @return string */
	public static function payload_hash(): string {
		$data = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller verifies checkout nonce before reading.
		foreach ( $_POST as $key => $value ) {
			if ( in_array( $key, array( 'woocommerce-process-checkout-nonce', '_wpnonce', '_wp_http_referer', 'famma_request_id' ), true ) ) {
				continue;
			}
			$data[ sanitize_key( $key ) ] = is_scalar( $value ) ? sanitize_text_field( wp_unslash( (string) $value ) ) : '__invalid_array__';
		}
		ksort( $data );
		return hash( 'sha256', (string) wp_json_encode( $data ) );
	}

	/** Read a registry record.
	 *
	 * @param string $id UUID.
	 * @return array|null
	 */
	public function get( string $id ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- dedicated intent table; immediate uncached reads preserve concurrent reconciliation.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE request_id = %s', $this->table(), $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Reserve once atomically. False means no caller may create a new order.
	 *
	 * @param string $id UUID.
	 * @param string $payload Intent digest.
	 * @return bool Whether this process inserted the unique row.
	 */
	public function reserve( string $id, string $payload ): bool {
		global $wpdb;
		$session = self::session_hash();
		$now     = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- atomic unique-key insertion in the intent table, never direct order writes.
		$inserted = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (request_id, session_hash, payload_hash, state, order_id, redirect, created_at, updated_at) VALUES (%s, %s, %s, %s, 0, %s, %s, %s)', $this->table(), $id, $session, $payload, 'processing', '', $now, $now ) );
		if ( 1 !== $inserted ) {
			return false;
		}
		$this->active  = $id;
		$this->session = $session;
		do_action( 'famma_express_reserved', $id );
		return true;
	}

	/**
	 * Stamp the intent before first save; never repurpose another intent's order.
	 *
	 * @param \WC_Order $order Unsaved checkout order.
	 * @throws \Exception When Woo tries to reuse another intent's order.
	 */
	public function stamp( \WC_Order $order ): void {
		if ( '' === $this->active ) {
			return;
		}
		$previous = (string) $order->get_meta( '_famma_express_request_id' );
		if ( '' !== $previous && $previous !== $this->active ) {
			throw new \Exception( esc_html__( 'Cette commande doit être vérifiée avant un nouvel essai.', 'famma-core' ) );
		}
		$order->update_meta_data( '_famma_express_request_id', $this->active );
		$order->update_meta_data( '_famma_express_session_hash', $this->session );
	}

	/** Link newly persisted order.
	 *
	 * @param \WC_Order $order Checkout order.
	 */
	public function link( \WC_Order $order ): void {
		if ( '' !== $this->active && $this->active === $order->get_meta( '_famma_express_request_id' ) ) {
			$this->update(
				$this->active,
				array(
					'order_id' => $order->get_id(),
					'state'    => 'order_created',
				)
			);
			do_action( 'famma_express_order_linked', $order, $this->active );
		}
	}

	/** Mark only proven validation failures before any order exists.
	 *
	 * @param array     $data Validated data.
	 * @param \WP_Error $errors Validation errors.
	 */
	public function validation( array $data, \WP_Error $errors ): void {
		if ( '' !== $this->active && ( $errors->has_errors() || wc_notice_count( 'error' ) > 0 ) ) {
			$row = $this->get( $this->active );
			if ( $row && 0 === (int) $row['order_id'] ) {
				$this->update( $this->active, array( 'state' => 'failed' ) );
				$this->active  = '';
				$this->session = '';
			}
		}
	}

	/** Persist accepted result before Woo's JSON exit.
	 *
	 * @param array $result Gateway success response.
	 * @param int   $order_id Order identifier.
	 * @return array Enriched Woo response.
	 */
	public function accepted( array $result, int $order_id ): array {
		if ( '' === $this->active || 'success' !== ( $result['result'] ?? '' ) ) {
			return $result;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || $this->active !== $order->get_meta( '_famma_express_request_id' ) ) {
			return $result;
		}
		$redirect = esc_url_raw( (string) ( $result['redirect'] ?? $order->get_checkout_order_received_url() ) );
		$order->update_meta_data( '_famma_express_accepted', 1 );
		$order->update_meta_data( '_famma_express_redirect', $redirect );
		$order->save();
		$this->update(
			$this->active,
			array(
				'state'    => 'accepted',
				'order_id' => $order_id,
				'redirect' => $redirect,
			)
		);
		$result['order_id']         = $order_id;
		$result['famma_request_id'] = $this->active;
		$result['famma_state']      = 'accepted';
		do_action( 'famma_express_accepted', $order, $this->active );
		$this->active  = '';
		$this->session = '';
		return $result;
	}

	/** Accept zero-total checkout through Woo's own successful redirect hook.
	 *
	 * @param string    $redirect Receipt redirect.
	 * @param \WC_Order $order Created order.
	 * @return string Unchanged receipt URL.
	 */
	public function free_order( string $redirect, \WC_Order $order ): string {
		$this->accepted(
			array(
				'result'   => 'success',
				'redirect' => $redirect,
			),
			$order->get_id()
		);
		return $redirect;
	}

	/**
	 * Recover accepted evidence via Woo CRUD; no timeout permits recreation.
	 *
	 * @param array $row Session-authorized record.
	 * @return array Session-safe public result.
	 */
	public function response( array $row ): array {
		if ( self::session_hash() !== $row['session_hash'] ) {
			return array(
				'result'     => 'failure',
				'famma_code' => 'unknown_intent',
			);
		}
		if ( ! in_array( $row['state'], array( 'accepted', 'failed' ), true ) ) {
			$orders = (int) $row['order_id'] > 0 ? array( wc_get_order( (int) $row['order_id'] ) ) : wc_get_orders(
				array(
					'limit'      => 2,
					// phpcs:ignore WordPress.DB.SlowDBQuery -- bounded recovery via Woo CRUD, compatible with HPOS and legacy storage.
					'meta_key'   => '_famma_express_request_id',
					// phpcs:ignore WordPress.DB.SlowDBQuery -- matching the unique intention is required to recover a first-save interruption.
					'meta_value' => $row['request_id'],
				)
			);
			if ( 1 === count( $orders ) && $orders[0] instanceof \WC_Order && $row['session_hash'] === $orders[0]->get_meta( '_famma_express_session_hash' ) && $orders[0]->get_meta( '_famma_express_accepted' ) ) {
				$row['state']    = 'accepted';
				$row['order_id'] = $orders[0]->get_id();
				$row['redirect'] = (string) $orders[0]->get_meta( '_famma_express_redirect' );
				$this->update(
					$row['request_id'],
					array(
						'state'    => 'accepted',
						'order_id' => $row['order_id'],
						'redirect' => $row['redirect'],
					)
				);
			}
		}
		if ( 'accepted' === $row['state'] ) {
			return array(
				'result'           => 'success',
				'redirect'         => $row['redirect'],
				'order_id'         => (int) $row['order_id'],
				'famma_request_id' => $row['request_id'],
				'famma_state'      => 'accepted',
				'famma_replay'     => true,
			);
		}
		if ( 'failed' === $row['state'] ) {
			return array(
				'result'           => 'failure',
				'famma_code'       => 'validation_failed',
				'famma_state'      => 'failed',
				'famma_request_id' => $row['request_id'],
				// Jamais d'échec muet : le détail de la validation a déjà été rendu à la première réponse.
				'messages'         => '<ul class="woocommerce-error" role="alert"><li>' . esc_html( Express_Checkout::generic_error() ) . '</li></ul>',
			);
		}
		return array(
			'result'           => 'processing',
			'famma_state'      => 'processing',
			'famma_request_id' => $row['request_id'],
		);
	}

	/** Update owned registry state.
	 *
	 * @param string $id UUID.
	 * @param array  $data Changes.
	 */
	private function update( string $id, array $data ): void {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- intent table only; uncached state transitions must be visible immediately.
		$wpdb->update(
			$this->table(),
			$data,
			array(
				'request_id'   => $id,
				'session_hash' => self::session_hash(),
			)
		);
	}
}
