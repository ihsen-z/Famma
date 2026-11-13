<?php
/**
 * Plusieurs adresses par client — case `acct_addrs` (lot 7 bis de l'espace
 * client, décision D4 : Maison / Travail / Famille).
 *
 * L'adresse « par défaut » reste l'adresse de facturation de WooCommerce :
 * le formulaire de commande la préremplit comme avant, sans rien changer au
 * tunnel. Les autres adresses sont gardées dans la méta `_famma_addresses`
 * (10 au plus avec celle par défaut), l'étiquette de celle par défaut dans
 * `_famma_addr_lbl`. « Utiliser par défaut » échange les deux.
 *
 * Les actions (enregistrer, par défaut, supprimer, annuler la suppression)
 * arrivent par un formulaire de « Mes adresses », jeton et connexion
 * vérifiés, puis la page est rechargée (aucun script nécessaire).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Carnet d'adresses du client.
 */
final class Account_Addresses {

	public const LIST  = '_famma_addresses';
	public const LBL   = '_famma_addr_lbl';
	public const TRASH = '_famma_addr_trash';
	public const NONCE = 'famma_addr';
	public const MAX   = 10;

	/**
	 * Étiquettes possibles (clé => clé de texte).
	 */
	public const LABELS = array(
		'home'   => 'ad_home',
		'work'   => 'ad_work',
		'family' => 'ad_family',
	);

	/**
	 * Pose les crochets.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_action( 'template_redirect', array( self::class, 'handle' ), 5 );
		Account_Identity_Phone::hooks();
	}

	/**
	 * La case est-elle cochée pour le client connecté ? Elle vit dans les
	 * panneaux de la maquette : les trois cases sont nécessaires.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return is_user_logged_in()
			&& New_Look::part( 'acct_addrs' )
			&& New_Look::part( 'acct_forms' )
			&& New_Look::part( 'acct_look' );
	}

	/**
	 * Texte d'une étiquette.
	 *
	 * @param string $lbl Clé d'étiquette.
	 * @return string
	 */
	public static function label( string $lbl ): string {
		return Account_Addresses_Texts::text( self::LABELS[ $lbl ] ?? 'ad_home' );
	}

	/**
	 * Une entrée nettoyée, ou null.
	 *
	 * @param mixed $raw Entrée lue.
	 * @return array{id: string, lbl: string, state: string, addr: string}|null
	 */
	private static function clean( $raw ): ?array {
		if ( ! is_array( $raw ) || empty( $raw['id'] ) || empty( $raw['state'] ) ) {
			return null;
		}

		$lbl = (string) ( $raw['lbl'] ?? 'home' );

		return array(
			'id'    => sanitize_key( (string) $raw['id'] ),
			'lbl'   => isset( self::LABELS[ $lbl ] ) ? $lbl : 'home',
			'state' => strtoupper( sanitize_text_field( (string) $raw['state'] ) ),
			'addr'  => sanitize_text_field( (string) ( $raw['addr'] ?? '' ) ),
		);
	}

	/**
	 * Adresses autres que celle par défaut.
	 *
	 * @param int $user_id Client.
	 * @return array<int, array{id: string, lbl: string, state: string, addr: string}>
	 */
	public static function extras( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::LIST, true );

		return is_array( $raw ) ? array_values( array_filter( array_map( array( self::class, 'clean' ), $raw ) ) ) : array();
	}

	/**
	 * Toutes les adresses, celle par défaut (la facturation) en premier.
	 *
	 * @param int $user_id Client.
	 * @return array<int, array{id: string, lbl: string, state: string, addr: string, def: bool}>
	 */
	public static function all( int $user_id ): array {
		$out  = array();
		$bill = self::billing( $user_id );

		if ( null !== $bill ) {
			$out[] = $bill + array( 'def' => true );
		}

		foreach ( self::extras( $user_id ) as $extra ) {
			$out[] = $extra + array( 'def' => false );
		}

		return $out;
	}

	/**
	 * L'adresse de facturation, si elle est remplie.
	 *
	 * @param int $user_id Client.
	 * @return array{id: string, lbl: string, state: string, addr: string}|null
	 */
	private static function billing( int $user_id ): ?array {
		$c     = new \WC_Customer( $user_id );
		$state = (string) $c->get_billing_state();
		$addr  = trim( (string) $c->get_billing_address_1() );

		if ( '' === $state && '' === $addr ) {
			return null;
		}

		$lbl = (string) get_user_meta( $user_id, self::LBL, true );

		return array(
			'id'    => 'billing',
			'lbl'   => isset( self::LABELS[ $lbl ] ) ? $lbl : 'home',
			'state' => $state,
			'addr'  => $addr,
		);
	}

	/**
	 * Écrit l'adresse par défaut dans la facturation de WooCommerce.
	 *
	 * @param int                  $user_id Client.
	 * @param array<string,string> $entry  Adresse (state, addr, lbl), ou vide pour l'effacer.
	 * @return void
	 */
	private static function set_billing( int $user_id, array $entry ): void {
		$c = new \WC_Customer( $user_id );
		$c->set_billing_state( (string) ( $entry['state'] ?? '' ) );
		$c->set_billing_address_1( (string) ( $entry['addr'] ?? '' ) );

		if ( '' === (string) $c->get_billing_country() ) {
			$c->set_billing_country( 'TN' );
		}

		$c->save();
		update_user_meta( $user_id, self::LBL, (string) ( $entry['lbl'] ?? 'home' ) );
	}

	/**
	 * Enregistre les adresses autres que celle par défaut.
	 *
	 * @param int                               $user_id Client.
	 * @param array<int, array<string, string>> $extras  Adresses.
	 * @return void
	 */
	private static function set_extras( int $user_id, array $extras ): void {
		update_user_meta( $user_id, self::LIST, array_values( $extras ) );
	}

	/**
	 * Nouvel identifiant d'adresse.
	 *
	 * @return string
	 */
	private static function new_id(): string {
		return 'a' . strtolower( wp_generate_password( 8, false, false ) );
	}

	/**
	 * Champ envoyé, nettoyé (le jeton est vérifié avant tout appel).
	 *
	 * @param string $key Champ.
	 * @return string
	 */
	private static function posted( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- appelé après wp_verify_nonce() dans handle().
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Reçoit une action de « Mes adresses », puis recharge la page.
	 *
	 * @return void
	 */
	public static function handle(): void {
		if ( ! isset( $_POST['famma_addr_do'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifié juste après.
			return;
		}

		$nonce = isset( $_POST['_famma_addr_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_famma_addr_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! self::active() || ! current_user_can( 'read' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$do      = sanitize_key( self::posted( 'famma_addr_do' ) );
		$id      = sanitize_key( self::posted( 'famma_addr_id' ) );
		$result  = Account_Addresses_Actions::run( $user_id, $do, $id );

		$url = wc_get_endpoint_url( 'edit-address', '', wc_get_page_permalink( 'myaccount' ) );

		if ( '' !== $result['notice'] ) {
			wc_add_notice( $result['notice'], 'error' );
		} elseif ( '' !== $result['done'] ) {
			$url = add_query_arg(
				array(
					'famma_ad'    => $result['done'],
					'famma_ad_id' => $result['id'],
				),
				$url
			);
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Données d'une adresse envoyée par la feuille.
	 *
	 * @return array{lbl: string, state: string, addr: string, def: bool}
	 */
	public static function posted_entry(): array {
		$lbl = sanitize_key( self::posted( 'famma_addr_lbl' ) );

		return array(
			'lbl'   => isset( self::LABELS[ $lbl ] ) ? $lbl : 'home',
			'state' => strtoupper( self::posted( 'famma_addr_state' ) ),
			'addr'  => mb_substr( self::posted( 'famma_addr_line' ), 0, 200 ),
			'def'   => '' !== self::posted( 'famma_addr_def' ),
		);
	}

	/**
	 * Accès en écriture pour `Account_Addresses_Actions`.
	 *
	 * @param int                              $user_id Client.
	 * @param array<string, string>|null       $billing Nouvelle adresse par défaut (null : inchangée, [] : effacée).
	 * @param array<int, array<string,string>> $extras  Autres adresses.
	 * @return void
	 */
	public static function write( int $user_id, ?array $billing, array $extras ): void {
		if ( null !== $billing ) {
			self::set_billing( $user_id, $billing );
		}

		self::set_extras( $user_id, $extras );
	}

	/**
	 * Identifiant tout neuf, pour `Account_Addresses_Actions`.
	 *
	 * @return string
	 */
	public static function fresh_id(): string {
		return self::new_id();
	}

	/**
	 * Adresse par défaut actuelle, pour `Account_Addresses_Actions`.
	 *
	 * @param int $user_id Client.
	 * @return array{id: string, lbl: string, state: string, addr: string}|null
	 */
	public static function current_billing( int $user_id ): ?array {
		return self::billing( $user_id );
	}
}
