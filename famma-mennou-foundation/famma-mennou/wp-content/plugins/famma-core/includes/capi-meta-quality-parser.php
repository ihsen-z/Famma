<?php
/**
 * Analyse de la réponse du Dataset Quality API de Meta.
 *
 * Ne garde que des valeurs typées (nom d'événement, notes, fréquence) et un
 * code d'échec court : aucun texte libre de Meta, donc aucun risque de relayer
 * un jeton ou un message interne.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Transforme une réponse HTTP en résultat classé.
 */
final class Capi_Meta_Quality_Parser {

	/**
	 * Codes d'erreur Graph classés.
	 */
	private const CODES = array(
		190 => 'token',
		102 => 'token',
		10  => 'permission',
		200 => 'permission',
		294 => 'permission',
		100 => 'permission',
		4   => 'limit',
		17  => 'limit',
		32  => 'limit',
		613 => 'limit',
	);

	/**
	 * Analyse une réponse.
	 *
	 * @param mixed $response Réponse de l'API HTTP de WordPress, ou WP_Error.
	 * @return array{status: string, reason: string, events: array<int, array<string, mixed>>}
	 */
	public static function parse( $response ): array {
		if ( is_wp_error( $response ) ) {
			$message = (string) $response->get_error_message();
			$timeout = false !== stripos( $message, 'timed out' ) || false !== stripos( $message, 'cURL error 28' );
			return self::failure( $timeout ? 'timeout' : 'network' );
		}
		$http = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();
		if ( isset( $body['error'] ) && is_array( $body['error'] ) ) {
			return self::failure( self::CODES[ (int) ( $body['error']['code'] ?? 0 ) ] ?? 'meta_error' );
		}
		$success = $http >= 200 && $http < 300;
		if ( ! $success || ! isset( $body['web'] ) || ! is_array( $body['web'] ) ) {
			return self::failure( $success ? 'unexpected' : 'meta_error' );
		}
		$events = array();
		foreach ( $body['web'] as $row ) {
			$name = is_array( $row ) && is_string( $row['event_name'] ?? null ) ? (string) preg_replace( '/[^A-Za-z0-9_.\- ]/', '', $row['event_name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$score     = $row['event_match_quality']['composite_score'] ?? null;
			$coverage  = $row['event_coverage']['percentage'] ?? null;
			$frequency = $row['data_freshness']['upload_frequency'] ?? '';
			$events[]  = array(
				'event'     => $name,
				'score'     => is_numeric( $score ) ? round( (float) $score, 1 ) : null,
				'coverage'  => is_numeric( $coverage ) ? round( (float) $coverage, 1 ) : null,
				'frequency' => is_string( $frequency ) ? (string) preg_replace( '/[^a-z0-9_]/', '', strtolower( $frequency ) ) : '',
			);
		}
		return array(
			'status' => 'ok',
			'reason' => '',
			'events' => $events,
		);
	}

	/**
	 * Échec court.
	 *
	 * @param string $reason Code.
	 * @return array{status: string, reason: string, events: array<int, array<string, mixed>>}
	 */
	public static function failure( string $reason ): array {
		return array(
			'status' => 'unavailable',
			'reason' => $reason,
			'events' => array(),
		);
	}
}
