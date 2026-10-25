<?php
/**
 * Verdict de la sonde CAPI (0.3.3).
 *
 * La sonde lit `GET /{version}/me?fields=id` : le jeton d'un utilisateur
 * système renvoie l'identifiant de cet utilisateur. En 0.3.2 elle lisait
 * l'objet Pixel (`GET /{pixel}?fields=id`) ; le jeton de l'API Conversions
 * peut envoyer des événements sans pouvoir lire cet objet, et Meta répondait
 * « (#100) Missing Permission » en production : bandeau orange permanent.
 * La sonde vérifie donc le jeton et la version servie ; l'accès au jeu de
 * données reste prouvé par les vrais envois (transport, rapprochement,
 * santé).
 *
 * Verdicts :
 * - `ok` : identifiant reçu (vert) ;
 * - `token` : jeton invalide ou expiré, codes 190 et 102, sous-codes 463 et
 *   467 (rouge) ;
 * - `permission` : jeton reconnu mais lecture refusée, codes 10, 200 à 299
 *   (dont 294), 368 ou message « Missing Permission » (orange « droits du
 *   jeton à vérifier ») ;
 * - `unavailable` : transitoire, réponse inattendue ou sans accusé (orange
 *   « sonde indisponible »).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Classement d'un résultat de sonde et libellés de ses issues.
 */
final class Capi_Probe_Verdict {

	/**
	 * Verdicts.
	 */
	public const OK          = 'ok';
	public const TOKEN       = 'token';
	public const PERMISSION  = 'permission';
	public const UNAVAILABLE = 'unavailable';

	/**
	 * Jeton invalide ou expiré : codes 190 et 102, sous-codes 463 et 467.
	 */
	private const TOKEN_CODES = array( 102, 190, 463, 467 );

	/**
	 * Verdict d'un résultat classé par `Capi_Transport::classify()`.
	 *
	 * @param array<string, mixed> $result   Résultat classé.
	 * @param mixed                $response Réponse brute, pour le message « Missing Permission ».
	 * @return string Une des constantes de la classe.
	 */
	public static function of( array $result, $response ): string {
		$response_class = (string) ( $result['class'] ?? '' );
		if ( in_array( $response_class, array( 'accepted', 'accepted_warning' ), true ) ) {
			return self::OK;
		}
		if ( self::is_token_error( (int) ( $result['code'] ?? 0 ), (int) ( $result['subcode'] ?? 0 ) ) ) {
			return self::TOKEN;
		}
		if ( 'access' === $response_class || self::missing_permission( $response ) ) {
			return self::PERMISSION;
		}
		return self::UNAVAILABLE;
	}

	/**
	 * Le code (ou sous-code) Meta désigne-t-il un jeton invalide ou expiré ?
	 *
	 * @param int $code    Code d'erreur Meta.
	 * @param int $subcode Sous-code d'erreur Meta.
	 * @return bool
	 */
	public static function is_token_error( int $code, int $subcode = 0 ): bool {
		return in_array( $code, self::TOKEN_CODES, true ) || in_array( $subcode, self::TOKEN_CODES, true );
	}

	/**
	 * Verdict d'une sonde enregistrée ; une sonde de la 0.3.2 (sans verdict) lisait le Pixel.
	 *
	 * Seul son refus de jeton (190) vaut encore : ses autres erreurs portaient
	 * sur l'objet Pixel, pas sur le jeton. Elle est rejouée sur `/me` au
	 * prochain passage de `Capi_Probe::ensure_scheduled()`.
	 *
	 * @param array<string, mixed> $probe Signal `probe` enregistré.
	 * @return string Une des constantes de la classe, ou '' sans verdict exploitable.
	 */
	public static function stored( array $probe ): string {
		if ( array_key_exists( 'verdict', $probe ) ) {
			return (string) $probe['verdict'];
		}
		if ( array() === $probe ) {
			return '';
		}
		return 'access' === ( $probe['class'] ?? '' ) && 190 === (int) ( $probe['code'] ?? 0 ) ? self::TOKEN : '';
	}

	/**
	 * Issue affichée d'une sonde : `token` ou `permission`, sinon la classe du transport.
	 *
	 * @param array<string, mixed> $result Résultat de la sonde, ou signal `probe` enregistré.
	 * @return string
	 */
	public static function outcome( array $result ): string {
		$verdict = self::stored( $result );
		return in_array( $verdict, array( self::TOKEN, self::PERMISSION ), true ) ? $verdict : (string) ( $result['class'] ?? '' );
	}

	/**
	 * Libellé d'une issue de sonde.
	 *
	 * @param string $outcome Issue (`outcome()`).
	 * @return string
	 */
	public static function label( string $outcome ): string {
		$labels = array(
			'accepted'            => __( 'jeton valide, version servie vérifiée (l’accès au Pixel se vérifie par les vrais envois)', 'famma-core' ),
			'accepted_warning'    => __( 'jeton valide, mais Meta a servi une autre version que celle demandée', 'famma-core' ),
			'token'               => __( 'jeton invalide ou expiré : générez-en un nouveau et ressaisissez-le', 'famma-core' ),
			'permission'          => __( 'jeton reconnu, droits du jeton à vérifier', 'famma-core' ),
			'transient'           => __( 'sonde indisponible : Meta injoignable pour l’instant', 'famma-core' ),
			'access'              => __( 'jeton ou droits refusés', 'famma-core' ),
			'contract'            => __( 'sonde indisponible : réponse inattendue de Meta', 'famma-core' ),
			'blocked_environment' => __( 'aucune requête : ce site n’est pas la production', 'famma-core' ),
			'not_configured'      => __( 'Pixel ou jeton absent', 'famma-core' ),
		);
		return $labels[ $outcome ] ?? $outcome;
	}

	/**
	 * La réponse en erreur dit-elle « Missing Permission » ?
	 *
	 * @param mixed $response Réponse de l'API HTTP de WordPress, ou WP_Error.
	 * @return bool
	 */
	private static function missing_permission( $response ): bool {
		if ( ! is_array( $response ) ) {
			return false;
		}
		$body  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$error = is_array( $body ) && isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();
		$text  = (string) ( $error['message'] ?? '' ) . ' ' . (string) ( $error['error_user_msg'] ?? '' );
		return false !== stripos( $text, 'Missing Permission' );
	}
}
