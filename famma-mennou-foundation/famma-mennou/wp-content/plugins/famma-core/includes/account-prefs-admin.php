<?php
/**
 * Lot 6 côté admin : où l'équipe retrouve les préférences du client.
 *
 * - Commande (écran de modification) : sous l'adresse de facturation, la
 *   langue des messages et l'inscription aux nouveautés WhatsApp du client,
 *   quand il en a choisi une.
 * - Comptes : colonne « Préférences » et vue « Nouveautés WhatsApp (n) », qui
 *   liste les inscrits, quand la case `acct_prefs` est cochée.
 *
 * Lecture seule : rien n'est modifié depuis ces écrans.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lot 6 côté admin.
 */
final class Account_Prefs_Admin {

	private const COUNT  = 'famma_wa_optins';
	private const COLUMN = 'famma_prefs';
	private const VIEW   = 'famma_wa';

	/**
	 * Pose les crochets.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'woocommerce_admin_order_data_after_billing_address', array( self::class, 'order_line' ) );

		if ( New_Look::part( 'acct_prefs' ) ) {
			add_filter( 'manage_users_columns', array( self::class, 'columns' ) );
			add_filter( 'manage_users_custom_column', array( self::class, 'column_value' ), 10, 3 );
			add_filter( 'views_users', array( self::class, 'views' ) );
			add_filter( 'users_list_table_query_args', array( self::class, 'filter_list' ), 20 );
		}
	}

	/**
	 * Résumé des préférences d'un client, ou vide.
	 *
	 * @param int $user_id Client.
	 * @return string
	 */
	private static function summary( int $user_id ): string {
		$lang  = Account_Prefs::lang( $user_id );
		$wa    = Account_Prefs::wa( $user_id );
		$parts = array();

		if ( 'ar' === $lang ) {
			$parts[] = __( 'Messages en arabe / derja', 'famma-core' );
		} elseif ( 'fr' === $lang ) {
			$parts[] = __( 'Messages en français', 'famma-core' );
		}

		if ( null !== $wa ) {
			$date = get_date_from_gmt( $wa['at'], (string) get_option( 'date_format' ) );

			/* translators: %s: date d'inscription. */
			$parts[] = sprintf( __( 'Nouveautés WhatsApp : oui depuis le %s', 'famma-core' ), $date );
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Ligne « Préférences du client » sous l'adresse de facturation.
	 *
	 * @param mixed $order Commande.
	 * @return void
	 */
	public static function order_line( $order ): void {
		if ( ! $order instanceof \WC_Order || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$user_id = (int) $order->get_customer_id();
		$text    = $user_id > 0 ? self::summary( $user_id ) : '';

		if ( '' !== $text ) {
			printf( '<p><strong>%1$s</strong> %2$s</p>', esc_html__( 'Préférences du client :', 'famma-core' ), esc_html( $text ) );
		}
	}

	/**
	 * Colonne « Préférences » de la liste des comptes.
	 *
	 * @param array<string, string> $columns Colonnes.
	 * @return array<string, string>
	 */
	public static function columns( $columns ): array {
		$columns                 = is_array( $columns ) ? $columns : array();
		$columns[ self::COLUMN ] = __( 'Préférences', 'famma-core' );

		return $columns;
	}

	/**
	 * Valeur de la colonne « Préférences ».
	 *
	 * @param string $output  Contenu déjà prévu.
	 * @param string $column  Colonne.
	 * @param int    $user_id Compte.
	 * @return string
	 */
	public static function column_value( $output, $column, $user_id ): string {
		if ( self::COLUMN !== $column ) {
			return (string) $output;
		}

		$text = self::summary( (int) $user_id );

		return '' !== $text ? esc_html( $text ) : '—';
	}

	/**
	 * Nombre d'inscrits aux nouveautés WhatsApp.
	 *
	 * @return int
	 */
	private static function count(): int {
		$count = get_transient( self::COUNT );

		if ( false === $count ) {
			$query = new \WP_User_Query(
				array(
					'meta_key'     => Account_Prefs::WA, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- compte mis en cache dix minutes.
					'meta_compare' => 'EXISTS',
					'fields'       => 'ID',
					'number'       => 1,
					'count_total'  => true,
				)
			);
			$count = (int) $query->get_total();
			set_transient( self::COUNT, $count, 10 * MINUTE_IN_SECONDS );
		}

		return (int) $count;
	}

	/**
	 * Oublie le compte mis en cache (inscription ou désinscription).
	 *
	 * @return void
	 */
	public static function forget_count(): void {
		delete_transient( self::COUNT );
	}

	/**
	 * La vue demandée est-elle « Nouveautés WhatsApp » ?
	 *
	 * @return bool
	 */
	private static function viewing(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtre de liste en lecture seule.
		return isset( $_GET[ self::VIEW ] ) && '1' === sanitize_key( wp_unslash( $_GET[ self::VIEW ] ) );
	}

	/**
	 * Vue « Nouveautés WhatsApp (n) » au-dessus de la liste des comptes.
	 *
	 * @param array<string, string> $views Vues.
	 * @return array<string, string>
	 */
	public static function views( $views ): array {
		$views = is_array( $views ) ? $views : array();

		if ( ! current_user_can( 'list_users' ) ) {
			return $views;
		}

		$views[ self::VIEW ] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
			esc_url( add_query_arg( self::VIEW, '1', admin_url( 'users.php' ) ) ),
			self::viewing() ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Nouveautés WhatsApp', 'famma-core' ),
			esc_html( number_format_i18n( self::count() ) )
		);

		return $views;
	}

	/**
	 * Vue « Nouveautés WhatsApp » : seulement les inscrits.
	 *
	 * @param array<string, mixed> $args Arguments de WP_User_Query.
	 * @return array<string, mixed>
	 */
	public static function filter_list( $args ): array {
		$args = is_array( $args ) ? $args : array();

		if ( self::viewing() && current_user_can( 'list_users' ) ) {
			$args['meta_key']     = Account_Prefs::WA; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- vue ponctuelle dans l'admin.
			$args['meta_compare'] = 'EXISTS';
		}

		return $args;
	}
}
