<?php
/**
 * Doublon serveur (lot 59) : validation d'un lot du relais et construction des événements Meta.
 *
 * Rien de ce que le navigateur envoie n'est cru sur parole :
 * - nom en liste blanche (`Capi_Mirror::EVENTS`), event_id au format des
 *   identifiants du site (UUID du navigateur, `famma_add_<prep>`…) ;
 * - `event_source_url` sur l'hôte du site, sans fragment ;
 * - âge de l'événement entre 0 et 1 heure ; `event_time` = horloge du serveur
 *   moins cet âge (jamais l'heure du navigateur) ;
 * - articles : ID WooCommerce publiés, quantités entières bornées ;
 *   `value`, `currency`, `content_ids` et `contents` sont RECALCULÉS depuis
 *   WooCommerce, avec les règles du Pixel (`Meta_Ids`), puis le profil de
 *   données s'applique (`Capi_Data_Profile`, R3) ;
 * - `user_data` : IP et user-agent de la requête, cookies `_fbp`, `_fbc` et
 *   `famma_vid` (external_id haché) ; `ph` seulement s'il est déjà une
 *   empreinte SHA-256 (64 caractères hexadécimaux) : le numéro en clair ne
 *   quitte jamais le navigateur, et n'est donc jamais reçu ni stocké.
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
	 * Corps maximal accepté, en octets.
	 */
	public const MAX_BODY = 8192;

	/**
	 * Événements maximum par appel.
	 */
	public const MAX_EVENTS = 10;

	/**
	 * Âge maximal d'un événement relayé, en millisecondes (une heure).
	 */
	public const MAX_AGE_MS = 3600000;

	/**
	 * Quantité maximale par article (filtre `famma_capi_mirror_max_quantity`).
	 */
	public const MAX_QUANTITY = 20;

	/**
	 * Nombre d'articles accepté par événement : minimum, maximum.
	 */
	private const ITEM_LIMITS = array(
		'PageView'         => array( 0, 0 ),
		'ViewContent'      => array( 1, 1 ),
		'AddToCart'        => array( 1, 10 ),
		'InitiateCheckout' => array( 1, 20 ),
	);

	/**
	 * Format d'un event_id : celui de `fammaEventId()` et des identifiants serveur.
	 */
	private const EVENT_ID = '/^[A-Za-z0-9_-]{8,100}$/D';

	/**
	 * Événements Meta d'un lot valide ; les entrées invalides sont écartées une à une.
	 *
	 * @param string $body Corps JSON du relais.
	 * @param int    $now  Horloge du serveur.
	 * @return array<int, array<string, mixed>>
	 */
	public static function from_body( string $body, int $now ): array {
		if ( '' === $body || strlen( $body ) > self::MAX_BODY ) {
			return array();
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data['e'] ) || ! is_array( $data['e'] ) ) {
			return array();
		}
		$ph   = isset( $data['ph'] ) && is_string( $data['ph'] ) && 1 === preg_match( '/^[0-9a-f]{64}$/D', $data['ph'] ) ? $data['ph'] : '';
		$user = self::user_data( $ph );
		if ( empty( $user['client_user_agent'] ) ) {
			return array();
		}
		$events = array();
		foreach ( array_slice( $data['e'], 0, self::MAX_EVENTS ) as $raw ) {
			$event = is_array( $raw ) ? self::event( $raw, $now, $user ) : null;
			if ( null !== $event ) {
				$events[ $event['event_name'] . '|' . $event['event_id'] ] = $event;
			}
		}
		return array_values( $events );
	}

	/**
	 * Un événement validé, ou null.
	 *
	 * @param array<string, mixed>  $raw  Entrée du relais : n, id, u, a, i.
	 * @param int                   $now  Horloge du serveur.
	 * @param array<string, string> $user Bloc user_data de la requête.
	 * @return array<string, mixed>|null
	 */
	public static function event( array $raw, int $now, array $user ): ?array {
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
		$items = self::items( $raw['i'] ?? array(), $name );
		if ( '' === $url || null === $items ) {
			return null;
		}
		$custom = self::custom_data( $name, $items );
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
	 * URL de la page, seulement sur l'hôte du site (avec ou sans « www. »), sans fragment.
	 *
	 * @param mixed $raw URL reçue.
	 * @return string URL retenue, ou chaîne vide.
	 */
	public static function source_url( $raw ): string {
		if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > 2000 ) {
			return '';
		}
		$parts = wp_parse_url( $raw );
		$home  = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$host  = is_array( $parts ) ? strtolower( (string) ( $parts['host'] ?? '' ) ) : '';
		if ( '' === $host || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
			return '';
		}
		$bare = static fn( string $h ): string => (string) preg_replace( '/^www\./', '', strtolower( $h ) );
		if ( $bare( $host ) !== $bare( $home ) ) {
			return '';
		}
		$url = strtok( $raw, '#' );
		return false === $url || 1 === preg_match( '/[\s<>"]/', $url ) ? '' : $url;
	}

	/**
	 * Articles `[ID, quantité]` validés pour l'événement, ou null.
	 *
	 * @param mixed  $raw  Liste reçue.
	 * @param string $name Événement.
	 * @return array<int, int[]>|null
	 */
	public static function items( $raw, string $name ): ?array {
		list( $min, $max ) = self::ITEM_LIMITS[ $name ];
		if ( ! is_array( $raw ) || count( $raw ) < $min || count( $raw ) > $max ) {
			return null;
		}
		$limit = max( 1, (int) apply_filters( 'famma_capi_mirror_max_quantity', self::MAX_QUANTITY ) );
		$items = array();
		foreach ( $raw as $item ) {
			$id  = is_array( $item ) ? ( $item[0] ?? null ) : null;
			$qty = is_array( $item ) ? ( $item[1] ?? null ) : null;
			if ( ! is_int( $id ) || $id <= 0 || ! ( is_int( $qty ) || is_float( $qty ) ) || floor( (float) $qty ) !== (float) $qty || $qty < 1 || $qty > $limit ) {
				return null;
			}
			$items[] = array( $id, 'ViewContent' === $name ? 1 : (int) $qty );
		}
		return $items;
	}

	/**
	 * Bloc custom_data recalculé depuis WooCommerce, profil de données appliqué.
	 *
	 * @param string            $name  Événement.
	 * @param array<int, int[]> $items Articles validés.
	 * @return array<string, mixed>|null Vide pour PageView ; null si un article n'est pas publié.
	 */
	public static function custom_data( string $name, array $items ): ?array {
		if ( 'PageView' === $name ) {
			return array();
		}
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 3;
		$contents = array();
		$value    = 0.0;
		$quantity = 0;
		foreach ( $items as $item ) {
			$product = self::product( $item[0] );
			if ( null === $product ) {
				return null;
			}
			$value     += (float) wc_get_price_to_display( $product ) * $item[1];
			$quantity  += $item[1];
			$contents[] = array(
				'id'       => Meta_Ids::content_id( $product ),
				'quantity' => $item[1],
			);
		}
		$custom = array(
			'currency' => get_woocommerce_currency(),
			'value'    => round( $value, $decimals ),
		);
		if ( 'ViewContent' === $name ) {
			$product = self::product( $items[0][0] );
			$custom += Meta_Ids::view_target( $product ) + array( 'content_name' => $product->get_name() );
		} else {
			$custom += array(
				'content_type' => 'product',
				'content_ids'  => array_values( array_unique( array_column( $contents, 'id' ) ) ),
				'contents'     => $contents,
			);
			if ( 'InitiateCheckout' === $name ) {
				$custom['num_items'] = $quantity;
			}
		}
		return Capi_Data_Profile::apply( $custom, $name );
	}

	/**
	 * Produit publié (une taille : elle et son parent publiés), ou null.
	 *
	 * @param int $id ID WooCommerce.
	 * @return \WC_Product|null
	 */
	private static function product( int $id ): ?\WC_Product {
		$product = wc_get_product( $id );
		if ( ! $product instanceof \WC_Product || 'publish' !== get_post_status( $id ) ) {
			return null;
		}
		$parent = $product->get_parent_id();
		return $parent > 0 && 'publish' !== get_post_status( $parent ) ? null : $product;
	}

	/**
	 * Bloc user_data de la requête du relais (jamais rien venant du corps, sauf `ph` déjà haché).
	 *
	 * @param string $ph Empreinte SHA-256 du téléphone, ou chaîne vide.
	 * @return array<string, string>
	 */
	public static function user_data( string $ph ): array {
		return array_filter(
			array(
				'client_ip_address' => Capi_Signals::client_ip(),
				'client_user_agent' => Capi_Signals::client_user_agent(),
				'fbp'               => Capi_Signals::cookie( '_fbp' ),
				'fbc'               => Capi_Signals::cookie( '_fbc' ),
				'external_id'       => Capi_External_Id::from_visitor( Visitor_Context::cookie_id( Visitor_Context::VISITOR_COOKIE ) ),
				'ph'                => $ph,
			)
		);
	}
}
