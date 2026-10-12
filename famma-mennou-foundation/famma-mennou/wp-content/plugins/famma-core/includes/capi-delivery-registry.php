<?php
/**
 * Atomic CAPI delivery ownership and transport evidence, without customer data.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/** Persistent ownership; abandoned sending records are never silently reclaimed. */
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

	/** Claim a first send or an explicitly triggered retry of a definite rejection.
	 *
	 * @param string $pixel Pixel identifier.
	 * @param string $name Event name.
	 * @param string $id Stable event identifier.
	 * @param int    $order_id Order identifier.
	 * @param int    $time Immutable event time.
	 * @param int    $maximum Maximum total attempts.
	 * @param int    $legacy Legacy attempt count.
	 * @return array
	 */
	public function acquire( string $pixel, string $name, string $id, int $order_id, int $time, int $maximum, int $legacy = 0 ): array {
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
		if ( 1 !== $inserted && 'rejected' === $row['state'] && (int) $row['attempt_count'] < $maximum ) {
			$attempt = (int) $row['attempt_count'] + 1;
			$trace   = json_decode( $row['attempts'], true );
			$trace[] = array(
				'attempt'    => $attempt,
				'started_at' => gmdate( 'c' ),
				'state'      => 'sending',
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- compare-and-swap ensures only one retry owner.
			$changed = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET state=%s,owner=%s,attempt_count=%d,attempts=%s,updated_at=%s WHERE event_key=%s AND state=%s AND attempt_count=%d', $this->table(), 'sending', $owner, $attempt, wp_json_encode( $trace ), gmdate( 'Y-m-d H:i:s' ), $key, 'rejected', (int) $row['attempt_count'] ) );
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
	 * @return bool
	 */
	public function finish( array $claim, string $state, int $http, int $received ): bool {
		if ( empty( $claim['owned'] ) || ! in_array( $state, array( 'accepted', 'rejected', 'uncertain' ), true ) ) {
			return false;
		}
		global $wpdb;
		$trace          = json_decode( $claim['attempts'], true );
		$last           = count( $trace ) - 1;
		$trace[ $last ] = array_merge(
			$trace[ $last ],
			array(
				'finished_at'     => gmdate( 'c' ),
				'state'           => $state,
				'http'            => $http,
				'events_received' => $received,
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- owner-conditional durable ACK precedes WooCommerce metadata.
		$changed = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET state=%s,attempts=%s,updated_at=%s WHERE event_key=%s AND owner=%s AND state=%s', $this->table(), $state, wp_json_encode( $trace ), gmdate( 'Y-m-d H:i:s' ), $claim['key'], $claim['owner'], 'sending' ) );
		return 1 === $changed;
	}

	/** Fail closed unless the expected primary key really exists. */
	private function unique_constraint(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- an ownership constraint must be verified from the live schema.
		$keys = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $this->table(), 'PRIMARY' ), ARRAY_A );
		return is_array( $keys ) && 1 === count( $keys ) && 'event_key' === $keys[0]['Column_name'] && 0 === (int) $keys[0]['Non_unique'];
	}
}
