<?php
/**
 * Atomic CAPI delivery ownership and transport evidence, without customer data.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Persistent ownership of each (pixel, event, event_id) send.
 *
 * Lot 5 (D1) : un envoi incertain n'est repris que par une relance bornée
 * explicite (`Capi_Retry`, « Renvoyer »), jamais par un déclenchement
 * quelconque ; un envoi resté « en cours » plus de 15 minutes est d'abord
 * marqué incertain, avec une trace, puis suit la même règle.
 */
final class Capi_Delivery_Registry {

	/** Shared instance.
	 *
	 * @var self|null
	 */
	private static ?self $i = null;

	/** Return registry and register its versioned migration. */
	public static function instance(): self {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'init', array( self::$i, 'install' ), 5 );
		}
		return self::$i;
	}

	/** Trusted custom table name. */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'famma_capi_delivery';
	}

	/** Install independently of Woo order storage and mark only verified migrations. */
	public function install(): void {
		if ( '1' === get_option( 'famma_capi_registry_version', '' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $this->table();
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
			event_key char(64) NOT NULL,
			event_name varchar(24) NOT NULL,
			event_id varchar(100) NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			event_time bigint(20) unsigned NOT NULL,
			state varchar(24) NOT NULL,
			owner char(36) NOT NULL,
			attempt_count int unsigned NOT NULL,
			attempts longtext NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (event_key),
			KEY order_id (order_id)
		) $collate;"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom schema verification requires an uncached read.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( $table === $found && $this->unique_constraint() ) {
			update_option( 'famma_capi_registry_version', '1', false );
		}
	}

	/** Read durable evidence, never an object-cache claim.
	 *
	 * @param string $key Event digest.
	 * @return array
	 */
	public function read( string $key ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- atomic ownership cannot use a cached row.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE event_key = %s', $this->table(), $key ), ARRAY_A );
		return is_array( $row ) ? $row : array();
	}

	/** Claim a first send, an explicitly triggered retry of a definite rejection,
	 * or — for a bounded retry only — the retry of an uncertain send.
	 *
	 * @param string $pixel Pixel identifier.
	 * @param string $name Event name.
	 * @param string $id Stable event identifier.
	 * @param int    $order_id Order identifier.
	 * @param int    $time Immutable event time.
	 * @param int    $maximum Maximum total attempts.
	 * @param int    $legacy Legacy attempt count.
	 * @param bool   $allow_uncertain Whether an uncertain send may be retried.
	 * @return array
	 */
	public function acquire( string $pixel, string $name, string $id, int $order_id, int $time, int $maximum, int $legacy = 0, bool $allow_uncertain = false ): array {
		global $wpdb;
		if ( ! $this->unique_constraint() ) {
			return array( 'state' => 'unavailable' );
		}
		$key     = hash( 'sha256', $pixel . '|' . $name . '|' . $id );
		$owner   = wp_generate_uuid4();
		$attempt = $legacy + 1;
		$trace   = array(
			array(
				'attempt'    => $attempt,
				'started_at' => gmdate( 'c' ),
				'state'      => 'sending',
			),
		);
		if ( $time <= 0 || $attempt > $maximum ) {
			$row = $this->read( $key );
			return $row ? $row + array(
				'key'     => $key,
				'attempt' => (int) $row['attempt_count'],
				'owned'   => false,
			) : array( 'state' => 'blocked' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- unique insertion is the cross-process ownership boundary.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (event_key,event_name,event_id,order_id,event_time,state,owner,attempt_count,attempts,updated_at) VALUES (%s,%s,%s,%d,%d,%s,%s,%d,%s,%s)',
				$this->table(),
				$key,
				$name,
				$id,
				$order_id,
				$time,
				'sending',
				$owner,
				$attempt,
				wp_json_encode( $trace ),
				gmdate( 'Y-m-d H:i:s' )
			)
		);
		if ( false === $inserted ) {
			return array( 'state' => 'unavailable' );
		}
		$row = $this->read( $key );
		if ( empty( $row ) ) {
			return array( 'state' => 'unavailable' );
		}
		if ( 1 !== $inserted && 'sending' === $row['state'] ) {
			$row = $this->expire_stale( $key, $row );
		}
		$retriable = 'rejected' === $row['state'] || ( $allow_uncertain && 'uncertain' === $row['state'] );
		if ( 1 !== $inserted && $retriable && (int) $row['attempt_count'] < $maximum ) {
			$attempt = (int) $row['attempt_count'] + 1;
			$trace   = $this->trace( $row );
			$trace[] = array(
				'attempt'    => $attempt,
				'started_at' => gmdate( 'c' ),
				'state'      => 'sending',
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- compare-and-swap ensures only one retry owner.
			$changed = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET state=%s,owner=%s,attempt_count=%d,attempts=%s,updated_at=%s WHERE event_key=%s AND state=%s AND attempt_count=%d', $this->table(), 'sending', $owner, $attempt, wp_json_encode( $trace ), gmdate( 'Y-m-d H:i:s' ), $key, $row['state'], (int) $row['attempt_count'] ) );
			if ( 1 === $changed ) {
				$row = $this->read( $key );
			}
		}
		$row['key']     = $key;
		$row['attempt'] = (int) $row['attempt_count'];
		$row['owned']   = 'sending' === $row['state'] && hash_equals( $owner, $row['owner'] );
		return $row;
	}

	/** Persist an outcome only for the owning execution, before Woo markers.
	 *
	 * @param array  $claim Owned claim.
	 * @param string $state Accepted, rejected or uncertain.
	 * @param int    $http HTTP status, zero for unavailable response.
	 * @param int    $received ACK event count, zero without ACK.
	 * @param string $response_class Transport class (accepted, transient, access…), for diagnosis.
	 * @return bool
	 */
	public function finish( array $claim, string $state, int $http, int $received, string $response_class = '' ): bool {
		if ( empty( $claim['owned'] ) || ! in_array( $state, array( 'accepted', 'rejected', 'uncertain' ), true ) ) {
			return false;
		}
		global $wpdb;
		$trace          = $this->trace( $claim );
		$last           = max( 0, count( $trace ) - 1 );
		$entry          = array(
			'finished_at'     => gmdate( 'c' ),
			'state'           => $state,
			'http'            => $http,
			'events_received' => $received,
		);
		$trace[ $last ] = array_merge( $trace[ $last ] ?? array(), $entry, '' !== $response_class ? array( 'class' => $response_class ) : array() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- owner-conditional durable ACK precedes WooCommerce metadata.
		$changed = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET state=%s,attempts=%s,updated_at=%s WHERE event_key=%s AND owner=%s AND state=%s', $this->table(), $state, wp_json_encode( $trace ), gmdate( 'Y-m-d H:i:s' ), $claim['key'], $claim['owner'], 'sending' ) );
		return 1 === $changed;
	}

	/** Mark a "sending" record older than the stale delay as uncertain (D1).
	 *
	 * Compare-and-swap on the previous owner: a send still in progress in
	 * another process keeps its ownership until the delay has elapsed.
	 *
	 * @param string $key Event digest.
	 * @param array  $row Current record.
	 * @return array Record after the check.
	 */
	private function expire_stale( string $key, array $row ): array {
		$updated = strtotime( (string) $row['updated_at'] . ' UTC' );
		if ( false === $updated || time() - $updated < Config::CAPI_STALE_SENDING ) {
			return $row;
		}
		global $wpdb;
		$trace                    = $this->trace( $row );
		$last                     = max( 0, count( $trace ) - 1 );
		$trace[ $last ]           = $trace[ $last ] ?? array();
		$trace[ $last ]['state']  = 'uncertain';
		$trace[ $last ]['reason'] = 'stale_sending';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- owner-conditional takeover of an abandoned send.
		$changed = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET state=%s,attempts=%s,updated_at=%s WHERE event_key=%s AND state=%s AND owner=%s', $this->table(), 'uncertain', wp_json_encode( $trace ), gmdate( 'Y-m-d H:i:s' ), $key, 'sending', (string) $row['owner'] ) );
		return 1 === $changed ? $this->read( $key ) : $row;
	}

	/** Records still "sending" after the stale delay, for the rescue sweep.
	 *
	 * @param int $seconds Stale delay.
	 * @return array<int, array<string, mixed>>
	 */
	public function stale_sending( int $seconds ): array {
		global $wpdb;
		if ( ! $this->unique_constraint() ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- durable evidence must be read from the live table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT event_key,order_id,event_name,event_id,event_time,state,owner,attempt_count,attempts,updated_at FROM %i WHERE state=%s AND updated_at < %s ORDER BY updated_at ASC LIMIT 20', $this->table(), 'sending', gmdate( 'Y-m-d H:i:s', time() - $seconds ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Take over an abandoned "sending" record found by the rescue sweep (D1).
	 *
	 * Same compare-and-swap as a takeover on acquisition: the record becomes
	 * "uncertain", so the sweep never stalls behind sends it cannot retry.
	 *
	 * @param array $row Record returned by stale_sending().
	 * @return bool True when this call moved the record to "uncertain".
	 */
	public function release_stale( array $row ): bool {
		$key = (string) ( $row['event_key'] ?? '' );
		if ( '' === $key || 'sending' !== ( $row['state'] ?? '' ) ) {
			return false;
		}
		return 'uncertain' === ( $this->expire_stale( $key, $row )['state'] ?? '' );
	}

	/** Records of a set of orders, for the reconciliation screen (R8).
	 *
	 * @param int[] $order_ids Order identifiers.
	 * @return array<int, array<string, array<string, mixed>>> order_id => event_name => record.
	 */
	public function for_orders( array $order_ids ): array {
		global $wpdb;
		$order_ids = array_values( array_filter( array_map( 'absint', $order_ids ) ) );
		if ( array() === $order_ids || ! $this->unique_constraint() ) {
			return array();
		}
		$out = array();
		foreach ( array_chunk( $order_ids, 50 ) as $chunk ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- live table read; one %d placeholder per identifier, all prepared.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT order_id,event_name,event_id,event_time,state,attempt_count,updated_at FROM %i WHERE order_id IN (' . implode( ',', array_fill( 0, count( $chunk ), '%d' ) ) . ')', $this->table(), ...$chunk ), ARRAY_A );
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$out[ (int) $row['order_id'] ][ (string) $row['event_name'] ] = $row;
			}
		}
		return $out;
	}

	/** Decoded attempt trace of a record.
	 *
	 * @param array $row Record or claim.
	 * @return array
	 */
	private function trace( array $row ): array {
		$trace = json_decode( (string) ( $row['attempts'] ?? '' ), true );
		return is_array( $trace ) ? $trace : array();
	}

	/** Fail closed unless the expected primary key really exists. */
	private function unique_constraint(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- an ownership constraint must be verified from the live schema.
		$keys = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $this->table(), 'PRIMARY' ), ARRAY_A );
		return is_array( $keys ) && 1 === count( $keys ) && 'event_key' === $keys[0]['Column_name'] && 0 === (int) $keys[0]['Non_unique'];
	}
}
