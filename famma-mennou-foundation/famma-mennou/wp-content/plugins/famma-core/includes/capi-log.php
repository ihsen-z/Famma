<?php
/**
 * Journal des envois à l'API Conversions de Meta (OrderPlaced, Purchase).
 *
 * Une ligne par envoi réel, lisible dans WooCommerce > État > Journaux,
 * source `famma-capi`. On y lit ce qu'il faut pour diagnostiquer un écart
 * dans le Gestionnaire d'événements — valeur, devise, signaux présents,
 * réponse de Meta — sans jamais y écrire le token ni un identifiant client :
 * fbc, fbp et téléphone n'apparaissent que sous la forme « oui / non ».
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Écriture des lignes de journal CAPI.
 */
final class Capi_Log {

	/**
	 * Source WooCommerce du journal.
	 */
	public const SOURCE = 'famma-capi';

	/**
	 * Longueur maximale d'un message d'erreur journalisé.
	 */
	private const MAX_MESSAGE = 300;

	/**
	 * Écrit une ligne pour un envoi.
	 *
	 * Champs attendus : order_id, event_name, event_id, value, currency,
	 * user_data (bloc envoyé, seules les clés sont lues), http, events_received,
	 * fbtrace_id, state, attempt, error. `$secret` est retiré du message
	 * d'erreur par précaution, au cas où une couche HTTP le recopierait.
	 *
	 * @param array<string, mixed> $fields Données de l'envoi.
	 * @param string               $secret Valeur à ne jamais écrire (token).
	 * @return void
	 */
	public static function write( array $fields, string $secret = '' ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$user_data = isset( $fields['user_data'] ) && is_array( $fields['user_data'] ) ? $fields['user_data'] : array();
		$state     = (string) ( $fields['state'] ?? '' );

		$line = sprintf(
			'%1$s order=%2$d event_id=%3$s value=%4$s currency=%5$s fbc=%6$s fbp=%7$s ph=%8$s http=%9$d events_received=%10$d fbtrace_id=%11$s state=%12$s attempt=%13$d error=%14$s',
			self::token( (string) ( $fields['event_name'] ?? '' ) ),
			(int) ( $fields['order_id'] ?? 0 ),
			self::token( (string) ( $fields['event_id'] ?? '' ) ),
			wc_format_decimal( (string) ( $fields['value'] ?? 0 ), 3 ),
			self::token( (string) ( $fields['currency'] ?? '' ) ),
			self::presence( $user_data, 'fbc' ),
			self::presence( $user_data, 'fbp' ),
			self::presence( $user_data, 'ph' ),
			(int) ( $fields['http'] ?? 0 ),
			(int) ( $fields['events_received'] ?? 0 ),
			self::token( (string) ( $fields['fbtrace_id'] ?? '' ) ),
			self::token( $state ),
			(int) ( $fields['attempt'] ?? 0 ),
			self::message( (string) ( $fields['error'] ?? '' ), $secret )
		);

		$level = 'accepted' === $state ? 'info' : 'error';
		wc_get_logger()->log( $level, $line, array( 'source' => self::SOURCE ) );
	}

	/**
	 * « oui » si la clé est présente et non vide dans le bloc user_data.
	 *
	 * @param array<string, mixed> $user_data Bloc envoyé.
	 * @param string               $key       Clé Meta.
	 * @return string
	 */
	private static function presence( array $user_data, string $key ): string {
		return ! empty( $user_data[ $key ] ) ? 'oui' : 'non';
	}

	/**
	 * Réduit une valeur à un jeton sans espace ni caractère de contrôle.
	 *
	 * @param string $value Valeur brute.
	 * @return string `-` si vide.
	 */
	private static function token( string $value ): string {
		$value = preg_replace( '/[^A-Za-z0-9_.:-]/', '', $value );

		return '' === $value ? '-' : substr( (string) $value, 0, 120 );
	}

	/**
	 * Message d'erreur sur une ligne, borné, sans le secret.
	 *
	 * @param string $message Message de Meta ou de la couche HTTP.
	 * @param string $secret  Valeur à masquer.
	 * @return string
	 */
	private static function message( string $message, string $secret ): string {
		if ( '' !== $secret ) {
			$message = str_replace( $secret, '[masqué]', $message );
		}
		$message = trim( (string) preg_replace( '/\s+/', ' ', sanitize_text_field( $message ) ) );

		return '' === $message ? '-' : '"' . str_replace( '"', "'", substr( $message, 0, self::MAX_MESSAGE ) ) . '"';
	}
}
