<?php
/**
 * Actions du carnet d'adresses : enregistrer, mettre par défaut, supprimer,
 * annuler la suppression (lot 7 bis de l'espace client).
 *
 * Appelées par `Account_Addresses::handle()`, une fois le jeton, la connexion
 * et la case vérifiés. L'adresse par défaut est toujours la facturation de
 * WooCommerce ; supprimer celle par défaut fait passer la suivante par
 * défaut. La dernière adresse supprimée est gardée dans `_famma_addr_trash`
 * pour « Annuler ».
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Actions sur les adresses.
 */
final class Account_Addresses_Actions {

	/**
	 * Exécute une action.
	 *
	 * @param int    $user_id Client.
	 * @param string $action      save | default | delete | restore.
	 * @param string $id      Adresse visée (`billing` pour celle par défaut).
	 * @return array{done: string, id: string, notice: string}
	 */
	public static function run( int $user_id, string $action, string $id ): array {
		switch ( $action ) {
			case 'save':
				return self::save( $user_id, $id );
			case 'default':
				return self::make_default( $user_id, $id );
			case 'delete':
				return self::delete( $user_id, $id );
			case 'restore':
				return self::restore( $user_id );
		}

		return self::result();
	}

	/**
	 * Résultat d'une action.
	 *
	 * @param string $done   Ce qui a été fait (vide : rien).
	 * @param string $id     Adresse concernée.
	 * @param string $notice Erreur à afficher.
	 * @return array{done: string, id: string, notice: string}
	 */
	private static function result( string $done = '', string $id = '', string $notice = '' ): array {
		return array(
			'done'   => $done,
			'id'     => $id,
			'notice' => $notice,
		);
	}

	/**
	 * Position d'une autre adresse dans la liste, ou -1.
	 *
	 * @param array<int, array<string, string>> $extras Autres adresses.
	 * @param string                            $id     Identifiant.
	 * @return int
	 */
	private static function find( array $extras, string $id ): int {
		foreach ( $extras as $i => $extra ) {
			if ( $extra['id'] === $id ) {
				return (int) $i;
			}
		}

		return -1;
	}

	/**
	 * L'adresse par défaut, rangée parmi les autres (nouvel identifiant).
	 *
	 * @param array<string, string> $billing Adresse par défaut.
	 * @return array<string, string>
	 */
	private static function as_extra( array $billing ): array {
		return array(
			'id'    => Account_Addresses::fresh_id(),
			'lbl'   => $billing['lbl'],
			'state' => $billing['state'],
			'addr'  => $billing['addr'],
		);
	}

	/**
	 * Enregistre une adresse, nouvelle ou modifiée.
	 *
	 * @param int    $user_id Client.
	 * @param string $id      Adresse modifiée (vide : nouvelle).
	 * @return array{done: string, id: string, notice: string}
	 */
	private static function save( int $user_id, string $id ): array {
		$in     = Account_Addresses::posted_entry();
		$states = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_states( 'TN' ) : array();

		if ( ! is_array( $states ) || ! isset( $states[ $in['state'] ] ) ) {
			return self::result( '', '', Account_Addresses_Texts::text( 'ad_err_gov' ) );
		}

		$entry   = array(
			'lbl'   => $in['lbl'],
			'state' => $in['state'],
			'addr'  => $in['addr'],
		);
		$billing = Account_Addresses::current_billing( $user_id );
		$extras  = Account_Addresses::extras( $user_id );

		if ( 'billing' === $id && null !== $billing ) {
			Account_Addresses::write( $user_id, $entry, $extras );
			return self::result( 'saved', 'billing' );
		}

		$at = '' !== $id ? self::find( $extras, $id ) : -1;

		if ( $at >= 0 ) {
			$entry['id'] = $id;

			if ( ! $in['def'] ) {
				$extras[ $at ] = $entry;
				Account_Addresses::write( $user_id, null, $extras );
				return self::result( 'saved', $id );
			}

			$extras[ $at ] = null !== $billing ? self::as_extra( $billing ) : $extras[ $at ];

			if ( null === $billing ) {
				array_splice( $extras, $at, 1 );
			}

			Account_Addresses::write( $user_id, $entry, $extras );
			return self::result( 'saved', 'billing' );
		}

		if ( count( $extras ) + ( null !== $billing ? 1 : 0 ) >= Account_Addresses::MAX ) {
			return self::result( '', '', Account_Addresses_Texts::text( 'ad_full' ) );
		}

		if ( null === $billing ) {
			Account_Addresses::write( $user_id, $entry, $extras );
			return self::result( 'saved', 'billing' );
		}

		if ( $in['def'] ) {
			array_unshift( $extras, self::as_extra( $billing ) );
			Account_Addresses::write( $user_id, $entry, $extras );
			return self::result( 'saved', 'billing' );
		}

		$entry['id'] = Account_Addresses::fresh_id();
		$extras[]    = $entry;
		Account_Addresses::write( $user_id, null, $extras );

		return self::result( 'saved', $entry['id'] );
	}

	/**
	 * Une autre adresse devient celle par défaut ; l'ancienne prend sa place.
	 *
	 * @param int    $user_id Client.
	 * @param string $id      Adresse choisie.
	 * @return array{done: string, id: string, notice: string}
	 */
	private static function make_default( int $user_id, string $id ): array {
		$billing = Account_Addresses::current_billing( $user_id );
		$extras  = Account_Addresses::extras( $user_id );
		$at      = self::find( $extras, $id );

		if ( $at < 0 || null === $billing ) {
			return self::result();
		}

		$chosen        = $extras[ $at ];
		$extras[ $at ] = self::as_extra( $billing );
		Account_Addresses::write( $user_id, $chosen, $extras );

		return self::result( 'def', 'billing' );
	}

	/**
	 * Supprime une adresse ; elle est gardée pour « Annuler ».
	 *
	 * @param int    $user_id Client.
	 * @param string $id      Adresse supprimée.
	 * @return array{done: string, id: string, notice: string}
	 */
	private static function delete( int $user_id, string $id ): array {
		$billing = Account_Addresses::current_billing( $user_id );
		$extras  = Account_Addresses::extras( $user_id );

		if ( 'billing' === $id && null !== $billing ) {
			update_user_meta( $user_id, Account_Addresses::TRASH, $billing + array( 'def' => true ) );
			$next = array() !== $extras ? array_shift( $extras ) : array();
			Account_Addresses::write( $user_id, $next, $extras );
			return self::result( 'gone' );
		}

		$at = self::find( $extras, $id );

		if ( $at < 0 ) {
			return self::result();
		}

		update_user_meta(
			$user_id,
			Account_Addresses::TRASH,
			$extras[ $at ] + array(
				'def' => false,
				'at'  => $at,
			)
		);
		array_splice( $extras, $at, 1 );
		Account_Addresses::write( $user_id, null, $extras );

		return self::result( 'gone' );
	}

	/**
	 * Remet la dernière adresse supprimée à sa place.
	 *
	 * @param int $user_id Client.
	 * @return array{done: string, id: string, notice: string}
	 */
	private static function restore( int $user_id ): array {
		$was = get_user_meta( $user_id, Account_Addresses::TRASH, true );
		delete_user_meta( $user_id, Account_Addresses::TRASH );

		if ( ! is_array( $was ) || empty( $was['state'] ) ) {
			return self::result();
		}

		$billing = Account_Addresses::current_billing( $user_id );
		$extras  = Account_Addresses::extras( $user_id );
		$entry   = array(
			'lbl'   => (string) ( $was['lbl'] ?? 'home' ),
			'state' => (string) $was['state'],
			'addr'  => (string) ( $was['addr'] ?? '' ),
		);

		if ( ! empty( $was['def'] ) || null === $billing ) {
			if ( null !== $billing ) {
				array_unshift( $extras, self::as_extra( $billing ) );
			}

			Account_Addresses::write( $user_id, $entry, $extras );
			return self::result( 'saved', 'billing' );
		}

		$entry['id'] = Account_Addresses::fresh_id();
		array_splice( $extras, min( (int) ( $was['at'] ?? 0 ), count( $extras ) ), 0, array( $entry ) );
		Account_Addresses::write( $user_id, null, $extras );

		return self::result( 'saved', $entry['id'] );
	}
}
