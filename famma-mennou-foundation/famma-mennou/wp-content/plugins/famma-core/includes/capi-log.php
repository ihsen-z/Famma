<?php
/**
 * Journal des envois à l'API Conversions de Meta (OrderPlaced, Purchase).
 *
 * Une ligne par issue, lisible dans WooCommerce > État > Journaux, source
 * `famma-capi` :
 *
 * - un envoi réel (`write`) : valeur, devise, signaux présents, réponse de
 *   Meta, version demandée et servie, classe de réponse, code et sous-code,
 *   nombre de messages, mode test, relance ;
 * - toute autre issue (`outcome`) : déjà envoyé, désactivé, inéligible avec
 *   son motif, bloqué par l'environnement, code de test invalide, registre
 *   indisponible… (lot 5, D7 : 9 issues sur 9 tracées).
 *
 * Jamais le token ni un identifiant client : fbc, fbp et téléphone
 * n'apparaissent que sous la forme « oui / non », et tout fragment du token
 * (8 caractères ou plus, quelle que soit la casse) est masqué (T1.6).
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
	 * Plus court fragment du secret considéré comme une fuite.
	 */
	private const SECRET_FRAGMENT = 8;

	/**
	 * Marque qui remplace un fragment de secret.
	 */
	private const MASK = '[masqué]';

	/**
	 * Niveau de journal de chaque issue sans envoi.
	 */
	private const OUTCOME_LEVELS = array(
		'already_sent'         => 'info',
		'disabled'             => 'info',
		'stale_sending'        => 'warning',
		'ineligible'           => 'notice',
		'held_uncertain'       => 'warning',
		'in_progress'          => 'notice',
		'not_configured'       => 'warning',
		'blocked_environment'  => 'warning',
		'too_old'              => 'error',
		'test_code_invalid'    => 'error',
		'registry_unavailable' => 'error',
		'blocked'              => 'error',
		'queue_failed'         => 'error',
	);

	/**
	 * Écrit une ligne pour un envoi réel.
	 *
	 * Champs attendus : order_id, event_name, event_id, value, currency,
	 * user_data (bloc envoyé, seules les clés sont lues), http, events_received,
	 * fbtrace_id, state, attempt, error, et ceux du transport (version_requested,
	 * version_served, class, code, subcode, messages), test, retry. `$secret`
	 * est retiré de tout ce qui vient de Meta ou de la couche HTTP.
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
			'%1$s order=%2$d event_id=%3$s value=%4$s currency=%5$s fbc=%6$s fbp=%7$s ph=%8$s http=%9$d events_received=%10$d fbtrace_id=%11$s state=%12$s attempt=%13$d version=%14$s served=%15$s class=%16$s code=%17$d subcode=%18$d messages=%19$d test=%20$s retry=%21$s error=%22$s',
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
			self::token( self::redact( (string) ( $fields['fbtrace_id'] ?? '' ), $secret ) ),
			self::token( $state ),
			(int) ( $fields['attempt'] ?? 0 ),
			self::token( (string) ( $fields['version_requested'] ?? '' ) ),
			self::token( self::redact( (string) ( $fields['version_served'] ?? '' ), $secret ) ),
			self::token( (string) ( $fields['class'] ?? '' ) ),
			(int) ( $fields['code'] ?? 0 ),
			(int) ( $fields['subcode'] ?? 0 ),
			(int) ( $fields['messages'] ?? 0 ),
			empty( $fields['test'] ) ? 'non' : 'oui',
			self::token( (string) ( $fields['retry'] ?? '' ) ),
			self::message( (string) ( $fields['error'] ?? '' ), $secret )
		);

		$level = 'accepted' === $state ? 'info' : 'error';
		wc_get_logger()->log( $level, $line, array( 'source' => self::SOURCE ) );
	}

	/**
	 * Écrit une ligne pour une issue sans envoi (D7).
	 *
	 * @param array<string, mixed> $base   order_id, event_name, event_id.
	 * @param string               $state  Issue, par exemple `already_sent`.
	 * @param string               $reason Motif court, par exemple `status:cancelled`.
	 * @return void
	 */
	public static function outcome( array $base, string $state, string $reason = '' ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		$line = sprintf(
			'%1$s order=%2$d event_id=%3$s state=%4$s reason=%5$s',
			self::token( (string) ( $base['event_name'] ?? '' ) ),
			(int) ( $base['order_id'] ?? 0 ),
			self::token( (string) ( $base['event_id'] ?? '' ) ),
			self::token( $state ),
			self::token( $reason )
		);
		wc_get_logger()->log( self::OUTCOME_LEVELS[ $state ] ?? 'notice', $line, array( 'source' => self::SOURCE ) );
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
		$message = trim( (string) preg_replace( '/\s+/', ' ', sanitize_text_field( self::redact( $message, $secret ) ) ) );

		return '' === $message ? '-' : '"' . str_replace( '"', "'", substr( $message, 0, self::MAX_MESSAGE ) ) . '"';
	}

	/**
	 * Masque tout fragment du secret d'au moins 8 caractères, sans tenir compte
	 * de la casse ni des blancs (jeton coupé par un saut de ligne, T1.6).
	 *
	 * @param string $text   Texte venu de Meta ou de la couche HTTP.
	 * @param string $secret Valeur à masquer.
	 * @return string
	 */
	public static function redact( string $text, string $secret ): string {
		$secret = strtolower( (string) preg_replace( '/\s+/', '', $secret ) );
		if ( strlen( $secret ) < self::SECRET_FRAGMENT || '' === $text ) {
			return $text;
		}

		// Positions des caractères non blancs, pour comparer le texte sans ses blancs.
		$positions = array();
		$compact   = '';
		$length    = strlen( $text );
		for ( $i = 0; $i < $length; $i++ ) {
			if ( ! ctype_space( $text[ $i ] ) ) {
				$positions[] = $i;
				$compact    .= strtolower( $text[ $i ] );
			}
		}

		$masked = array_fill( 0, $length, false );
		$count  = strlen( $compact );
		for ( $start = 0; $start + self::SECRET_FRAGMENT <= $count; $start++ ) {
			if ( str_contains( $secret, substr( $compact, $start, self::SECRET_FRAGMENT ) ) ) {
				for ( $k = $positions[ $start ]; $k <= $positions[ $start + self::SECRET_FRAGMENT - 1 ]; $k++ ) {
					$masked[ $k ] = true;
				}
			}
		}

		$out     = '';
		$in_mask = false;
		for ( $i = 0; $i < $length; $i++ ) {
			if ( $masked[ $i ] ) {
				$out    .= $in_mask ? '' : self::MASK;
				$in_mask = true;
				continue;
			}
			$out    .= $text[ $i ];
			$in_mask = false;
		}
		return $out;
	}
}
