<?php
/**
 * Doublon serveur (lot 59) : validation d'un lot du relais et construction des événements Meta.
 *
 * Rien de ce que le navigateur envoie n'est cru sur parole :
 * - nom en liste blanche (`Capi_Mirror::EVENTS`), event_id au format des
 *   identifiants du site (UUID du navigateur, `famma_add_<prep>`…) ;
 * - `event_source_url` sur l'hôte du site, reconstruite : chemin et liste
 *   blanche de paramètres (`utm_*`, `fbclid`, `gclid`, `ttclid`) seulement,
 *   jamais une clé de commande ni une donnée glissée dans l'adresse (I1) ;
 * - âge de l'événement entre 0 et 1 heure ; `event_time` = horloge du serveur
 *   moins cet âge (jamais l'heure du navigateur) ;
 * - articles : ID WooCommerce publiés, quantités entières bornées (une
 *   quantité ou un nombre de lignes au-delà de la borne est ramené à la
 *   borne et compté comme écart, M4) ; valeur bornée (`Capi_Mirror_Data`) ;
 * - `user_data` : uniquement l'IP, le user-agent et les cookies de la
 *   requête ; `ph` seulement s'il correspond à une saisie connue du serveur.
 *
 * Références Meta (relues le 26/11/2026) : « Customer Information
 * Parameters » (ph : « Hashing required », indicatif pays compris ;
 * client_ip_address, client_user_agent, fbc, fbp : « Do not hash ») ;
 * « Server Event Parameters » (`client_user_agent` requis pour `website`).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lot du relais → événements Meta prêts pour la file.
 */
final class Capi_Mirror_Event {

	/**
	 * Corps maximal accepté par la route propre, en octets.
	 */
	public const MAX_BODY = 8192;

	/**
	 * Événements maximum par lot.
	 */
	public const MAX_EVENTS = 10;

	/**
	 * Âge maximal d'un événement relayé, en millisecondes (une heure).
	 */
	public const MAX_AGE_MS = 3600000;

	/**
	 * Quantité maximale par article (filtre `famma_capi_mirror_max_quantity`).
	 */
	public const MAX_QUANTITY = 10;

	/**
	 * Nombre d'articles accepté par événement : minimum, maximum.
	 */
	private const ITEM_LIMITS = array(
		'PageView'         => array( 0, 0 ),
		'ViewContent'      => array( 1, 1 ),
		'AddToCart'        => array( 1, 5 ),
		'InitiateCheckout' => array( 1, 10 ),
	);

	/**
	 * Paramètres d'adresse gardés dans `event_source_url` (en plus de `utm_*`).
	 */
	private const URL_PARAMS = array( 'fbclid', 'gclid', 'ttclid' );

	/**
	 * Format d'un event_id : celui de `fammaEventId()` et des identifiants serveur.
	 */
	private const EVENT_ID = '/^[A-Za-z0-9_-]{8,100}$/D';

	/**
	 * Événements Meta d'un lot ; les entrées invalides sont écartées une à une et comptées.
	 *
	 * @param array<string, mixed> $payload Lot décodé : `e` (entrées), `ph` (empreinte).
	 * @param int                  $now     Horloge du serveur.
	 * @return array{events: array<int, array<string, mixed>>, rejected: int, adjusted: int}
	 */
	public static function from_payload( array $payload, int $now ): array {
		$raw    = is_array( $payload['e'] ?? null ) ? array_values( $payload['e'] ) : array();
		$ph     = is_string( $payload['ph'] ?? null ) && 1 === preg_match( '/^[0-9a-f]{64}$/D', $payload['ph'] ) ? Capi_Mirror_Data::verified_ph( $payload['ph'] ) : '';
		$user   = Capi_Mirror_Data::user_data( $ph );
		$result = array(
			'events'   => array(),
			'rejected' => max( 0, count( $raw ) - self::MAX_EVENTS ),
			'adjusted' => 0,
		);
		if ( empty( $user['client_user_agent'] ) ) {
			$result['rejected'] = count( $raw );
			return $result;
		}
		$events = array();
		foreach ( array_slice( $raw, 0, self::MAX_EVENTS ) as $entry ) {
			$adjusted = false;
			$event    = is_array( $entry ) ? self::event( $entry, $now, $user, $adjusted ) : null;
			if ( null === $event || isset( $events[ $event['event_name'] . '|' . $event['event_id'] ] ) ) {
				++$result['rejected'];
				continue;
			}
			$events[ $event['event_name'] . '|' . $event['event_id'] ] = $event;
			$result['adjusted']                                       += $adjusted ? 1 : 0;
		}
		$result['events'] = array_values( $events );
		return $result;
	}

	/**
	 * Un événement validé, ou null.
	 *
	 * @param array<string, mixed>  $raw      Entrée du relais : n, id, u, a, i, v.
	 * @param int                   $now      Horloge du serveur.
	 * @param array<string, string> $user     Bloc user_data de la requête.
	 * @param bool                  $adjusted Mis à vrai si une quantité, une ligne ou la valeur a été bornée.
	 * @return array<string, mixed>|null
	 */
	public static function event( array $raw, int $now, array $user, bool &$adjusted = false ): ?array {
		$name = $raw['n'] ?? '';
		$id   = $raw['id'] ?? '';
		$age  = $raw['a'] ?? null;
		if ( ! in_array( $name, Capi_Mirror::EVENTS, true ) || ! is_string( $id ) || 1 !== preg_match( self::EVENT_ID, $id ) ) {
			return null;
		}
		if ( ! is_int( $age ) || $age < 0 || $age > self::MAX_AGE_MS ) {
			return null;
		}
		$url   = self::source_url( $raw['u'] ?? '' );
		$items = self::items( $raw['i'] ?? array(), $name, $adjusted );
		if ( '' === $url || null === $items ) {
			return null;
		}
		$custom = Capi_Mirror_Data::custom_data( $name, $items, $raw['v'] ?? null, $adjusted );
		if ( null === $custom ) {
			return null;
		}
		$event = array(
			'event_name'       => $name,
			'event_time'       => $now - intdiv( $age, 1000 ),
			'event_id'         => $id,
			'action_source'    => 'website',
			'event_source_url' => $url,
			'user_data'        => $user,
		);
		if ( array() !== $custom ) {
			$event['custom_data'] = $custom;
		}
		return $event;
	}

	/**
	 * Adresse sur l'hôte du site (avec ou sans « www. »), en http ou https.
	 *
	 * @param string $url Adresse ou origine (`https://hote`).
	 * @return bool
	 */
	public static function on_site( string $url ): bool {
		$parts = wp_parse_url( $url );
		$host  = is_array( $parts ) ? strtolower( (string) ( $parts['host'] ?? '' ) ) : '';
		if ( '' === $host || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
			return false;
		}
		$bare = static fn( string $h ): string => (string) preg_replace( '/^www\./', '', strtolower( $h ) );

		return $bare( $host ) === $bare( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	}

	/**
	 * Adresse de la page reconstruite : hôte du site, chemin, paramètres de la liste blanche.
	 *
	 * @param mixed $raw URL reçue.
	 * @return string URL retenue, ou chaîne vide.
	 */
	public static function source_url( $raw ): string {
		if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > 2000 || ! self::on_site( $raw ) ) {
			return '';
		}
		$parts = (array) wp_parse_url( $raw );
		$path  = (string) ( $parts['path'] ?? '/' );
		if ( 1 === preg_match( '/[\s<>"\'\\\\]/', $path ) || strlen( $path ) > 500 ) {
			return '';
		}
		parse_str( (string) ( $parts['query'] ?? '' ), $params );
		$kept = array();
		foreach ( $params as $key => $value ) {
			$key = (string) $key;
			if ( is_string( $value ) && '' !== $value && ( in_array( $key, self::URL_PARAMS, true ) || 1 === preg_match( '/^utm_[a-z0-9_]{1,40}$/D', $key ) ) ) {
				$kept[ $key ] = substr( $value, 0, 500 );
			}
		}
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$url  = $parts['scheme'] . '://' . strtolower( (string) $parts['host'] ) . $port . ( '' !== $path ? $path : '/' );

		return array() === $kept ? $url : $url . '?' . http_build_query( $kept, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Articles `[ID, quantité]` validés pour l'événement, ou null.
	 *
	 * Une quantité, ou un nombre de lignes d'AddToCart / InitiateCheckout,
	 * au-delà de la borne est ramené à la borne (l'événement part, l'écart est
	 * compté) ; une entrée mal formée écarte l'événement.
	 *
	 * @param mixed  $raw      Liste reçue.
	 * @param string $name     Événement.
	 * @param bool   $adjusted Mis à vrai si une borne a été appliquée.
	 * @return array<int, int[]>|null
	 */
	public static function items( $raw, string $name, bool &$adjusted = false ): ?array {
		list( $min, $max ) = self::ITEM_LIMITS[ $name ];
		// PageView (aucun article) et ViewContent (un seul) : jamais tronqués, une autre forme est invalide.
		if ( ! is_array( $raw ) || count( $raw ) < $min || ( count( $raw ) > $max && $max <= 1 ) ) {
			return null;
		}
		if ( count( $raw ) > $max ) {
			$raw      = array_slice( array_values( $raw ), 0, $max );
			$adjusted = true;
		}
		$limit = max( 1, (int) apply_filters( 'famma_capi_mirror_max_quantity', self::MAX_QUANTITY ) );
		$items = array();
		foreach ( $raw as $item ) {
			$id  = is_array( $item ) ? ( $item[0] ?? null ) : null;
			$qty = is_array( $item ) ? ( $item[1] ?? null ) : null;
			if ( ! is_int( $id ) || $id <= 0 || ! ( is_int( $qty ) || is_float( $qty ) ) || floor( (float) $qty ) !== (float) $qty || $qty < 1 ) {
				return null;
			}
			$qty      = 'ViewContent' === $name ? 1 : (int) min( (float) $qty, (float) ( $limit + 1 ) );
			$adjusted = $adjusted || $qty > $limit;
			$items[]  = array( $id, min( $qty, $limit ) );
		}
		return $items;
	}
}
