<?php
/**
 * Doublon serveur (lot 59) : réception des événements relayés par le navigateur.
 *
 * Deux portes, mêmes contrôles (`accept_payload()`) :
 * - l'envoi du journal des visiteurs (`famma/v1/collect`, clé `m`) : cas
 *   courant, aucune requête de plus par page (I4) ;
 * - la route propre `famma/v1/meta-relay`, pour une page sans journal.
 * Aucune ne contacte Meta : la file part par la tâche WP-Cron (`Capi_Mirror_Queue`).
 *
 * Ordre des contrôles (I1, I2) : interrupteur, équipe, robot ; origine
 * (`Origin`, à défaut `Referer`) sur l'hôte du site, ce qui ferme la porte
 * aux autres sites ; plafond du site LU avant toute écriture ; limite par
 * groupe d'adresses comptée en ÉVÉNEMENTS ; validation de chaque événement ;
 * mise en file en une requête, un event_id déjà reçu depuis 24 h refusé.
 * Chaque refus et chaque écart est compté dans l'état de santé (orange).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Contrôles et mise en file des événements relayés.
 */
final class Capi_Mirror_Route {

	/**
	 * Événements acceptés par groupe d'adresses sur dix minutes.
	 */
	private const RATE_LIMIT = 120;

	/**
	 * Événements acceptés pour tout le site sur dix minutes (filtre `famma_capi_mirror_site_limit`).
	 */
	private const SITE_LIMIT = 600;

	/**
	 * Déclare la route propre du relais (pas de nonce : page en cache).
	 *
	 * @return void
	 */
	public static function register_route(): void {
		register_rest_route(
			Capi_Mirror::ROUTE_NAMESPACE,
			Capi_Mirror::ROUTE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'receive' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Reçoit un lot du relais. Répond toujours 204, jamais d'appel à Meta ici.
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response
	 */
	public static function receive( \WP_REST_Request $request ): \WP_REST_Response {
		self::accept( (string) $request->get_body(), time() );
		return new \WP_REST_Response( null, 204 );
	}

	/**
	 * Corps brut de la route propre : `{e: [...], ph}`.
	 *
	 * @param string $body Corps de la requête.
	 * @param int    $now  Horloge.
	 * @return int Événements mis en file.
	 */
	public static function accept( string $body, int $now ): int {
		if ( '' === $body || strlen( $body ) > Capi_Mirror_Event::MAX_BODY ) {
			return 0;
		}
		return self::accept_payload( json_decode( $body, true ), $now );
	}

	/**
	 * Contrôles puis mise en file ; testable sans serveur REST.
	 *
	 * @param mixed $payload Lot décodé : `e` (entrées), `ph` (empreinte).
	 * @param int   $now     Horloge.
	 * @return int Événements mis en file.
	 */
	public static function accept_payload( $payload, int $now ): int {
		if ( ! is_array( $payload ) || ! isset( $payload['e'] ) || ! is_array( $payload['e'] ) ) {
			return 0;
		}
		$count = min( count( $payload['e'] ), Capi_Mirror_Event::MAX_EVENTS );
		if ( 0 === $count || ! Capi_Mirror::enabled() || self::internal_sender() || Visitor_Context::is_bot() ) {
			return 0;
		}
		$refused = self::refusal( $count, $now );
		if ( '' !== $refused ) {
			Capi_Mirror_Health::record_many( array( $refused => $count ), $now );
			return 0;
		}
		$parsed = Capi_Mirror_Event::from_payload( $payload, $now );
		$stored = array() === $parsed['events'] ? 0 : Capi_Mirror_Store::insert( $parsed['events'], $now );
		if ( $stored > 0 ) {
			Rate_Counter::add( 'mr_site', $stored, $now );
		}
		Capi_Mirror_Health::record_many(
			array(
				'content'  => $parsed['rejected'],
				'adjusted' => $parsed['adjusted'],
				'replay'   => count( $parsed['events'] ) - $stored,
			),
			$now
		);
		return $stored;
	}

	/**
	 * Motif de refus du lot entier, ou chaîne vide.
	 *
	 * Le plafond du site n'est que LU ici : la seule écriture d'un lot refusé
	 * est le compteur de l'état de santé ; le compteur par groupe n'est écrit
	 * que pour un lot dans les limites (I2).
	 *
	 * @param int $count Événements annoncés (bornés à `MAX_EVENTS`).
	 * @param int $now   Horloge.
	 * @return string `origin`, `site`, `rate` ou vide.
	 */
	private static function refusal( int $count, int $now ): string {
		if ( ! self::same_origin() ) {
			return 'origin';
		}
		$site = max( 0, (int) apply_filters( 'famma_capi_mirror_site_limit', self::SITE_LIMIT ) );
		if ( Rate_Counter::used( 'mr_site', $now ) + $count > $site ) {
			return 'site';
		}
		return Visitor_Context::beacon_limited( 'mr', self::RATE_LIMIT, $count, $now ) ? 'rate' : '';
	}

	/**
	 * La requête vient-elle d'une page du site ?
	 *
	 * `sendBeacon` et `fetch` envoient `Origin` sur un POST ; à défaut, le
	 * `Referer`. Une page d'un autre site ne peut pas forger ces en-têtes dans
	 * le navigateur de ses visiteurs. Sans aucun des deux : refus.
	 *
	 * @return bool
	 */
	public static function same_origin(): bool {
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		if ( '' === $origin || 'null' === $origin ) {
			$origin = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		}
		return '' !== $origin && Capi_Mirror_Event::on_site( $origin );
	}

	/**
	 * Expéditeur interne (S1) : même règle que `Tracking::browser_tracking_allowed()`.
	 *
	 * Une requête REST sans nonce est anonyme pour WordPress
	 * (`rest_cookie_check_errors`) : l'utilisateur est donc relu dans le
	 * cookie de connexion, signé par WordPress.
	 *
	 * @return bool
	 */
	public static function internal_sender(): bool {
		if ( Config::instance()->track_internal_visitors() ) {
			return false;
		}
		$user     = function_exists( 'wp_validate_auth_cookie' ) ? (int) wp_validate_auth_cookie( '', 'logged_in' ) : 0;
		$internal = $user > 0 && ( user_can( $user, 'manage_options' ) || user_can( $user, 'manage_woocommerce' ) );

		return (bool) apply_filters( 'famma_tracking_exclude_visitor', $internal );
	}
}
