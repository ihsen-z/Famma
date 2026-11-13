<?php
/**
 * Lot 8 côté admin : effacement des données FAMMA d'un client, rappel des
 * demandes de suppression à traiter, recherche d'un client par numéro.
 *
 * - L'effaceur « FAMMA — compte client » s'ajoute à ceux de WordPress et de
 *   WooCommerce (Outils → Effacer les données personnelles) : adresses
 *   supplémentaires, étiquette, corbeille d'adresse, « Ma sélection »,
 *   numéro de connexion, langue des messages et inscription aux nouveautés
 *   WhatsApp. Il n'agit que quand le propriétaire lance
 *   l'effacement d'une demande, case cochée ou non.
 * - Un rappel en haut de l'admin compte les demandes confirmées en attente.
 * - Comptes → Rechercher accepte un numéro tunisien (avec ou sans +216,
 *   avec espaces), pour retrouver le client qui écrit sur WhatsApp.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lot 8 côté admin.
 */
final class Account_Privacy_Admin {

	private const COUNT = 'famma_del_requests';

	/**
	 * Pose les crochets.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( self::class, 'notice' ) );
			add_filter( 'users_list_table_query_args', array( self::class, 'search_by_phone' ) );
		}
	}

	/**
	 * Déclare l'effaceur FAMMA.
	 *
	 * @param array<string, array<string, mixed>> $erasers Effaceurs.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_eraser( $erasers ): array {
		$erasers = is_array( $erasers ) ? $erasers : array();

		$erasers['famma-account'] = array(
			'eraser_friendly_name' => __( 'FAMMA — compte client', 'famma-core' ),
			'callback'             => array( self::class, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Efface les méta FAMMA du client à cette adresse.
	 *
	 * @param string $email Adresse de la demande.
	 * @param int    $page  Page (une seule suffit).
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public static function erase( $email, $page = 1 ): array {
		unset( $page );

		$user    = get_user_by( 'email', (string) $email );
		$removed = false;

		if ( $user instanceof \WP_User ) {
			$keys = array( Account_Addresses::LIST, Account_Addresses::LBL, Account_Addresses::TRASH, Account_Favs::META, Account_Phone::META, Account_Privacy::META, Account_Prefs::LANG, Account_Prefs::WA );

			foreach ( $keys as $key ) {
				if ( '' !== get_user_meta( $user->ID, $key, true ) ) {
					$removed = delete_user_meta( $user->ID, $key ) || $removed;
				}
			}
		}

		self::forget_count();

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Nombre de demandes de suppression confirmées, en attente.
	 *
	 * @return int
	 */
	private static function count(): int {
		$cached = get_transient( self::COUNT );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => 'user_request',
				'post_name__in'  => array( Account_Privacy::ACTION ),
				'post_status'    => 'request-confirmed',
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);

		$count = (int) $query->found_posts;
		set_transient( self::COUNT, $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Oublie le compte en cache (nouvelle demande, annulation, effacement).
	 *
	 * @return void
	 */
	public static function forget_count(): void {
		delete_transient( self::COUNT );
	}

	/**
	 * Rappel des demandes à traiter, pour qui peut les traiter.
	 *
	 * @return void
	 */
	public static function notice(): void {
		if ( ! current_user_can( 'erase_others_personal_data' ) ) {
			return;
		}

		$count = self::count();

		if ( $count < 1 ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %d: nombre de demandes. */
					_n( 'FAMMA : %d demande de suppression de compte attend votre validation.', 'FAMMA : %d demandes de suppression de compte attendent votre validation.', $count, 'famma-core' ),
					$count
				)
			),
			esc_url( admin_url( 'erase-personal-data.php' ) ),
			esc_html__( 'Ouvrir « Effacer les données personnelles »', 'famma-core' )
		);
	}

	/**
	 * Comptes → Rechercher : un numéro tunisien trouve le client qui l'a
	 * comme numéro de connexion ou de facturation.
	 *
	 * @param array<string, mixed> $args Arguments de WP_User_Query.
	 * @return array<string, mixed>
	 */
	public static function search_by_phone( $args ): array {
		$args = is_array( $args ) ? $args : array();
		$term = trim( (string) ( $args['search'] ?? '' ), '* ' );
		$tel  = '' !== $term && ! str_contains( $term, '@' ) ? Tunisia::normalize_phone( $term ) : '';

		if ( '' === $tel || ! current_user_can( 'list_users' ) ) {
			return $args;
		}

		$grouped = substr( $tel, 0, 2 ) . ' ' . substr( $tel, 2, 3 ) . ' ' . substr( $tel, 5 );
		$forms   = array( $tel, '216' . $tel, '+216' . $tel, '00216' . $tel, $grouped, '+216 ' . $grouped );
		$ids     = get_users(
			array(
				'fields'     => 'ID',
				'number'     => 20,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- recherche ponctuelle dans l'admin.
					'relation' => 'OR',
					array(
						'key'   => Account_Phone::META,
						'value' => $tel,
					),
					array(
						'key'     => 'billing_phone',
						'value'   => $forms,
						'compare' => 'IN',
					),
				),
			)
		);

		if ( empty( $ids ) ) {
			return $args;
		}

		unset( $args['search'], $args['search_columns'] );
		$args['include'] = array_map( 'intval', $ids );

		return $args;
	}
}
