<?php
/**
 * Transport unique vers l'API Graph de Meta (lot 5, R5).
 *
 * Avant la 0.3.2, les vrais envois et le bouton « Test CAPI » avaient chacun
 * leur requête et leur version écrite en dur (T2.11, H-S7). Ce fichier porte
 * le seul `wp_remote_post` vers graph.facebook.com, et la sonde sans
 * événement (GET, R6). Il ne journalise rien : chaque appelant écrit sa
 * ligne `famma-capi` à partir du résultat.
 *
 * Classes de réponse :
 * - `accepted` : accusé de réception de Meta ;
 * - `accepted_warning` : accepté, mais `messages[]` non vide ou version
 *   servie (en-tête `facebook-api-version`) différente de la demandée ; un
 *   en-tête absent donne « inconnue », sans alerte ;
 * - `transient` : WP_Error, délai dépassé, 5xx, 408, 429, codes 1, 2 et
 *   limites de débit (4, 17, 341, 613), `is_transient`, ou 2xx sans accusé ;
 * - `access` : jeton ou droits (190, 10, 200 à 299 dont 294, 368) ;
 * - `contract` : refus du contenu (code 100…) ou réponse inconnue ;
 * - `blocked_environment` : garde d'environnement (D4), aucune requête.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Requêtes vers l'API Graph et classement de leurs réponses.
 */
final class Capi_Transport {

	/**
	 * Hôte de l'API Graph.
	 */
	public const HOST = 'https://graph.facebook.com/';

	/**
	 * Délai maximal d'une requête, en secondes.
	 */
	public const TIMEOUT = 8;

	/**
	 * Version servie quand Meta ne renvoie pas l'en-tête.
	 */
	public const UNKNOWN_VERSION = 'inconnue';

	/**
	 * Codes d'erreur Meta d'accès (jeton, droits, blocage), hors plage 200-299.
	 */
	private const ACCESS_CODES = array( 10, 190, 368 );

	/**
	 * Codes d'erreur Meta temporaires : inconnu, service, limites de débit.
	 */
	private const TRANSIENT_CODES = array( 1, 2, 4, 17, 341, 613 );

	/**
	 * URL d'envoi des événements d'un Pixel.
	 *
	 * @param string $version Version de l'API Graph.
	 * @param string $pixel   Identifiant du Pixel.
	 * @return string
	 */
	public static function events_url( string $version, string $pixel ): string {
		return self::HOST . rawurlencode( $version ) . '/' . rawurlencode( $pixel ) . '/events';
	}

	/**
	 * Envoie des événements serveur ; le jeton voyage dans le corps, jamais dans l'URL.
	 *
	 * @param string               $pixel   Identifiant du Pixel.
	 * @param string               $token   Jeton CAPI.
	 * @param array<string, mixed> $payload Corps sans le jeton (`data`, `test_event_code`).
	 * @param string               $version Version forcée, sinon celle de Config.
	 * @return array<string, mixed> Résultat classé (voir l'en-tête du fichier).
	 */
	public static function send_events( string $pixel, string $token, array $payload, string $version = '' ): array {
		$cfg     = Config::instance();
		$version = '' !== $version ? $version : $cfg->meta_graph_version();
		if ( ! $cfg->meta_sending_allowed() ) {
			return self::blocked( $version, $cfg->environment_type() );
		}
		$response = wp_remote_post(
			self::events_url( $version, $pixel ),
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode( $payload + array( 'access_token' => $token ) ),
			)
		);
		return self::classify( $response, $version, '' );
	}

	/**
	 * Sonde sans événement (R6) : GET /{version}/{pixel}?fields=id.
	 *
	 * Le jeton part dans l'en-tête Authorization, jamais dans l'URL. Aucun
	 * événement n'est créé chez Meta.
	 *
	 * @param string $pixel   Identifiant du Pixel.
	 * @param string $token   Jeton CAPI.
	 * @param string $version Version forcée, sinon celle de Config.
	 * @return array<string, mixed> Résultat classé.
	 */
	public static function probe( string $pixel, string $token, string $version = '' ): array {
		$cfg     = Config::instance();
		$version = '' !== $version ? $version : $cfg->meta_graph_version();
		if ( ! $cfg->meta_sending_allowed() ) {
			return self::blocked( $version, $cfg->environment_type() );
		}
		$response = wp_remote_get(
			self::HOST . rawurlencode( $version ) . '/' . rawurlencode( $pixel ) . '?fields=id',
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'headers'     => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);
		return self::classify( $response, $version, $pixel );
	}

	/**
	 * Classe une réponse de l'API Graph.
	 *
	 * @param mixed  $response Réponse de l'API HTTP de WordPress, ou WP_Error.
	 * @param string $version  Version demandée.
	 * @param string $pixel    Pour la sonde : identifiant attendu dans `id` ; vide pour un envoi.
	 * @return array<string, mixed>
	 */
	public static function classify( $response, string $version, string $pixel = '' ): array {
		$result = self::empty_result( $version );
		if ( is_wp_error( $response ) ) {
			$message          = (string) $response->get_error_message();
			$result['class']  = 'transient';
			$result['reason'] = ( false !== stripos( $message, 'timed out' ) || false !== stripos( $message, 'cURL error 28' ) ) ? 'timeout' : 'network';
			$result['error']  = $message;
			return $result;
		}

		$http   = (int) wp_remote_retrieve_response_code( $response );
		$served = trim( (string) wp_remote_retrieve_header( $response, 'facebook-api-version' ) );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body   = is_array( $body ) ? $body : array();
		$error  = isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();

		$result['http']             = $http;
		$result['version_served']   = '' !== $served ? $served : self::UNKNOWN_VERSION;
		$result['version_mismatch'] = '' !== $served && $served !== $version;
		$result['events_received']  = (int) ( $body['events_received'] ?? 0 );
		$result['messages']         = isset( $body['messages'] ) && is_array( $body['messages'] ) ? count( $body['messages'] ) : 0;
		$result['fbtrace_id']       = (string) ( $body['fbtrace_id'] ?? ( $error['fbtrace_id'] ?? '' ) );
		$result['code']             = (int) ( $error['code'] ?? 0 );
		$result['subcode']          = (int) ( $error['error_subcode'] ?? 0 );
		$result['is_transient']     = ! empty( $error['is_transient'] );
		$result['error']            = (string) ( $error['error_user_msg'] ?? ( $error['message'] ?? '' ) );

		if ( $http >= 200 && $http < 300 && ( array() === $error || 0 === $result['code'] ) ) {
			$acknowledged = '' === $pixel ? 1 === $result['events_received'] : (string) ( $body['id'] ?? '' ) === $pixel;
			if ( $acknowledged && array() === $error ) {
				$warning          = $result['messages'] > 0 || $result['version_mismatch'];
				$result['class']  = $warning ? 'accepted_warning' : 'accepted';
				$result['reason'] = $result['messages'] > 0 ? 'messages' : ( $result['version_mismatch'] ? 'version' : '' );
				return $result;
			}
			$result['class']  = 'transient';
			$result['reason'] = 'ack_missing';
			return $result;
		}

		$result['class']  = self::error_class( $http, $result['code'], $result['is_transient'] );
		$result['reason'] = 0 !== $result['code'] ? 'code_' . $result['code'] : 'http_' . $http;
		return $result;
	}

	/**
	 * Classe d'une réponse en erreur.
	 *
	 * @param int  $http         Statut HTTP.
	 * @param int  $code         Code d'erreur Meta, 0 sans corps exploitable.
	 * @param bool $is_transient Indication `is_transient` de Meta.
	 * @return string
	 */
	private static function error_class( int $http, int $code, bool $is_transient ): string {
		if ( in_array( $code, self::ACCESS_CODES, true ) || ( $code >= 200 && $code <= 299 ) ) {
			return 'access';
		}
		if ( $is_transient || in_array( $code, self::TRANSIENT_CODES, true ) || $http >= 500 || in_array( $http, array( 0, 408, 429 ), true ) ) {
			return 'transient';
		}
		return 'contract';
	}

	/**
	 * Résultat d'un envoi bloqué par la garde d'environnement : aucune requête.
	 *
	 * @param string $version     Version demandée.
	 * @param string $environment Type d'environnement WordPress.
	 * @return array<string, mixed>
	 */
	private static function blocked( string $version, string $environment ): array {
		$result           = self::empty_result( $version );
		$result['class']  = 'blocked_environment';
		$result['reason'] = $environment;
		return $result;
	}

	/**
	 * Squelette d'un résultat.
	 *
	 * @param string $version Version demandée.
	 * @return array<string, mixed>
	 */
	private static function empty_result( string $version ): array {
		return array(
			'class'             => '',
			'reason'            => '',
			'http'              => 0,
			'events_received'   => 0,
			'fbtrace_id'        => '',
			'code'              => 0,
			'subcode'           => 0,
			'messages'          => 0,
			'is_transient'      => false,
			'version_requested' => $version,
			'version_served'    => self::UNKNOWN_VERSION,
			'version_mismatch'  => false,
			'error'             => '',
		);
	}
}
