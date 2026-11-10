<?php
/**
 * Saisies des visiteurs — stockage.
 *
 * Le journal visiteurs ne garde que le NOM des champs touchés. Ce module garde
 * en plus ce qui a été TAPÉ dans les formulaires de commande (nom, téléphone,
 * gouvernorat, adresse, taille, quantité), une ligne par session, pour
 * rappeler ou recibler les visiteurs qui n'ont pas validé leur commande.
 *
 * Table à part plutôt qu'une colonne du journal : ces valeurs sont des données
 * personnelles, avec leur propre durée de conservation (30 jours par défaut,
 * contre 180 pour le journal), leur propre interrupteur et leur propre page
 * d'administration réservée à `manage_woocommerce`.
 *
 * Une ligne passe à « commandé » dès qu'une commande arrive avec la même
 * session, le même visiteur ou le même téléphone (commande du site, ou
 * commande saisie à la main après un rappel), ou quand l'équipe crée la
 * commande depuis la ligne (`Visitor_Inputs_Order`).
 *
 * Lecture filtrée et gestion par l'équipe (liste, export, statut, remarque) :
 * `Visitor_Inputs_Rows`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Table `famma_visitor_inputs` : réglages, écriture, lecture, purge.
 */
final class Visitor_Inputs {

	/**
	 * Version du schéma ; la changer relance `dbDelta()`.
	 */
	private const SCHEMA = '2';

	/**
	 * Option mémorisant la version du schéma installée.
	 */
	private const SCHEMA_OPTION = 'famma_visitor_inputs_schema';

	/**
	 * Option : capture active (« 1 ») ou non (« 0 »).
	 */
	public const ENABLED_OPTION = 'famma_visitor_inputs_enabled';

	/**
	 * Option : durée de conservation, en jours.
	 */
	public const RETENTION_OPTION = 'famma_visitor_inputs_days';

	/**
	 * Événement cron de purge.
	 */
	private const PURGE_HOOK = 'famma_visitor_inputs_purge';

	/**
	 * Statuts possibles d'une ligne.
	 */
	public const STATUSES = array( 'open', 'called', 'unreachable', 'converted', 'refused', 'closed' );

	/**
	 * Statuts encore « vivants » : une commande du même client les fait
	 * passer à « commandé ». « Pas intéressé » et « clos » restent tels quels.
	 */
	public const OPEN_STATUSES = array( 'open', 'called', 'unreachable' );

	/**
	 * Longueur maximale de la remarque d'une ligne.
	 */
	public const NOTE_MAX = 500;

	/**
	 * Colonnes de saisie et leur longueur maximale.
	 */
	public const FIELDS = array(
		'first_name' => 100,
		'phone'      => 20,
		'state'      => 16,
		'address'    => 200,
		'city'       => 100,
		'size'       => 32,
		'quantity'   => 0,
	);

	/**
	 * Instance partagée.
	 *
	 * @var Visitor_Inputs|null
	 */
	private static ?Visitor_Inputs $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Visitor_Inputs
	 */
	public static function instance(): Visitor_Inputs {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'init', array( self::$i, 'install' ), 5 );
			add_action( 'init', array( self::$i, 'schedule_purge' ) );
			add_action( self::PURGE_HOOK, array( self::$i, 'purge' ) );
		}
		return self::$i;
	}

	/**
	 * Nom complet de la table.
	 *
	 * @return string
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'famma_visitor_inputs';
	}

	/**
	 * Capture active ? Activée par défaut.
	 *
	 * @return bool
	 */
	public function enabled(): bool {
		return '1' === (string) get_option( self::ENABLED_OPTION, '1' );
	}

	/**
	 * Durée de conservation, bornée entre 7 et 180 jours.
	 *
	 * @return int
	 */
	public function retention_days(): int {
		$days = absint( get_option( self::RETENTION_OPTION, 30 ) );
		return max( 7, min( 180, $days ? $days : 30 ) );
	}

	/**
	 * Gouvernorats tunisiens connus de WooCommerce : code → nom.
	 *
	 * @return array<string, string>
	 */
	public static function states(): array {
		return function_exists( 'WC' ) ? (array) WC()->countries->get_states( 'TN' ) : array();
	}

	/**
	 * Crée ou met à jour la table, une seule fois par version de schéma.
	 *
	 * @return void
	 */
	public function install(): void {
		if ( self::SCHEMA === get_option( self::SCHEMA_OPTION, '' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $this->table();
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			visitor_id char(36) NOT NULL,
			session_id char(36) NOT NULL,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			path varchar(255) NOT NULL DEFAULT '',
			first_name varchar(100) NOT NULL DEFAULT '',
			phone varchar(20) NOT NULL DEFAULT '',
			phone_norm char(8) NOT NULL DEFAULT '',
			state varchar(16) NOT NULL DEFAULT '',
			address varchar(200) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			size varchar(32) NOT NULL DEFAULT '',
			quantity smallint(5) unsigned NOT NULL DEFAULT 0,
			source varchar(64) NOT NULL DEFAULT '',
			medium varchar(64) NOT NULL DEFAULT '',
			campaign varchar(100) NOT NULL DEFAULT '',
			content varchar(100) NOT NULL DEFAULT '',
			device varchar(10) NOT NULL DEFAULT '',
			status varchar(16) NOT NULL DEFAULT 'open',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			note varchar(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY session_id (session_id),
			KEY visitor_id (visitor_id),
			KEY phone_norm (phone_norm),
			KEY status_time (status,updated_at)
		) $collate;"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- schema verification needs an uncached read.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( $table === $found ) {
			update_option( self::SCHEMA_OPTION, self::SCHEMA, false );
		}
	}

	/**
	 * Planifie la purge quotidienne si elle ne l'est pas encore.
	 *
	 * @return void
	 */
	public function schedule_purge(): void {
		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}
	}

	/**
	 * Écrit ou complète la ligne d'une session.
	 *
	 * Une valeur vide n'efface jamais une valeur déjà reçue : le visiteur qui
	 * vide le champ avant de partir reste joignable.
	 *
	 * @param array<string, int|string> $meta   visitor_id, session_id, product_id, path, source, medium, campaign, content, device.
	 * @param array<string, int|string> $fields Saisies assainies (clés de FIELDS).
	 * @return bool
	 */
	public function save( array $meta, array $fields ): bool {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, keyed lookup.
		$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE session_id = %s', $this->table(), $meta['session_id'] ), ARRAY_A );
		$data = array( 'updated_at' => $now );
		foreach ( array_keys( self::FIELDS ) as $name ) {
			$value = $fields[ $name ] ?? '';
			if ( '' !== (string) $value && 0 !== $value ) {
				$data[ $name ] = $value;
			}
		}
		if ( isset( $data['phone'] ) ) {
			$data['phone_norm'] = Tunisia::normalize_phone( (string) $data['phone'] );
		}
		if ( (int) $meta['product_id'] > 0 ) {
			$data['product_id'] = (int) $meta['product_id'];
			$data['path']       = (string) $meta['path'];
		}
		if ( is_array( $row ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table write.
			return false !== $wpdb->update( $this->table(), $data, array( 'id' => (int) $row['id'] ) );
		}
		$data += array(
			'created_at' => $now,
			'visitor_id' => (string) $meta['visitor_id'],
			'session_id' => (string) $meta['session_id'],
			'path'       => (string) $meta['path'],
			'source'     => (string) $meta['source'],
			'medium'     => (string) $meta['medium'],
			'campaign'   => (string) $meta['campaign'],
			'content'    => (string) $meta['content'],
			'device'     => (string) $meta['device'],
			'status'     => 'open',
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table write.
		return false !== $wpdb->insert( $this->table(), $data );
	}

	/**
	 * Passe à « commandé » les lignes encore à relancer d'une session, d'un
	 * visiteur ou d'un téléphone.
	 *
	 * @param int    $order_id   Commande.
	 * @param string $session_id Session (UUID ou vide).
	 * @param string $visitor_id Visiteur (UUID ou vide).
	 * @param string $phone_norm Téléphone à 8 chiffres (ou vide).
	 * @return int Lignes passées à « commandé ».
	 */
	public function mark_converted( int $order_id, string $session_id, string $visitor_id, string $phone_norm ): int {
		global $wpdb;
		// Une valeur vide ne doit rien trouver : « - » ne correspond à aucune ligne.
		$session_id = '' !== $session_id ? $session_id : '-';
		$visitor_id = '' !== $visitor_id ? $visitor_id : '-';
		$phone_norm = '' !== $phone_norm ? $phone_norm : '-';
		$marks      = implode( ',', array_fill( 0, count( self::OPEN_STATUSES ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom table; $marks only holds %s placeholders, values passed by spread.
		$done = $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'converted', order_id = %d, updated_at = %s WHERE status IN ($marks) AND ( session_id = %s OR visitor_id = %s OR phone_norm = %s )", $this->table(), $order_id, gmdate( 'Y-m-d H:i:s' ), ...array_merge( self::OPEN_STATUSES, array( $session_id, $visitor_id, $phone_norm ) ) ) );
		return is_int( $done ) ? $done : 0;
	}

	/**
	 * Ligne d'une session, ou null.
	 *
	 * @param string $session_id Session.
	 * @return array<string, string>|null
	 */
	public function for_session( string $session_id ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, keyed lookup.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE session_id = %s', $this->table(), $session_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Supprime les lignes d'une liste de visiteurs (effacement des données).
	 *
	 * @param array<int, string> $visitor_ids Identifiants visiteurs (UUID vérifiés).
	 * @return int
	 */
	public function delete_visitors( array $visitor_ids ): int {
		if ( array() === $visitor_ids ) {
			return 0;
		}
		global $wpdb;
		$marks = implode( ',', array_fill( 0, count( $visitor_ids ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom table; $marks only holds %s placeholders, values passed by spread.
		$done = $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE visitor_id IN ($marks)", $this->table(), ...$visitor_ids ) );
		return is_int( $done ) ? $done : 0;
	}

	/**
	 * Supprime les lignes plus anciennes que la durée de conservation.
	 *
	 * @return void
	 */
	public function purge(): void {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $this->retention_days() * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded maintenance delete on a custom table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE updated_at < %s LIMIT %d', $this->table(), $cutoff, 5000 ) );
	}
}
