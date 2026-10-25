<?php
/**
 * Identifiant de clic `fbc` d'une commande, avec repli sur l'attribution WooCommerce (0.3.4, D6).
 *
 * Le cookie `_fbc` figé à la commande (`_famma_fbc`) reste la source
 * prioritaire. Il manque quand le script de cookies n'a pas tourné, ou quand
 * le navigateur l'a effacé (cookies posés en JavaScript, durée réduite sur
 * iPhone). L'attribution des commandes de WooCommerce garde pourtant l'URL
 * d'entrée de la session, fbclid compris : le `fbc` est alors reconstruit.
 *
 * Références :
 * - Meta, « fbp and fbc Parameters » (doc-meta, relue le 21/10/2026) :
 *   fbc = fb.<subdomainIndex>.<creationTime>.<fbclid> ; « If you're
 *   generating this field on a server, and not saving an `_fbc` cookie, use
 *   the value 1 » ; creationTime = « the timestamp when you first observed or
 *   received this `fbclid` value », en millisecondes ; le fbclid est sensible
 *   à la casse, jamais modifié.
 * - WooCommerce 11.0.1, `OrderAttributionMeta` : métadonnées
 *   `_wc_order_attribution_session_entry` (sourcebuster `current_add.ep`,
 *   `document.location.href` à l'arrivée) et
 *   `_wc_order_attribution_session_start_time` (`current_add.fd`, écrite dans
 *   le même cookie au même instant), au format `AAAA-MM-JJ HH:MM:SS` en UTC
 *   (`sbjs.init( { timezone_offset: '0' } )`), à la seconde près. Ce début
 *   de session vient de l'horloge du téléphone (sourcebuster, `new Date`),
 *   la date de commande de celle du serveur : jusqu'à 24 h d'avance, la date
 *   de création de la commande sert de creationTime, instant où le serveur a
 *   observé ce fbclid au plus tard (0.3.4, F3). Les noms supposent le préfixe
 *   par défaut (filtre `wc_order_attribution_tracking_field_prefix`, non
 *   utilisé sur le site).
 *
 * Aucune valeur n'est devinée : si l'une des deux métadonnées manque ou est
 * invalide, la commande part sans `fbc`. La valeur n'est jamais journalisée.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lecture et reconstruction du `fbc` d'une commande.
 */
final class Capi_Click_Id {

	/**
	 * URL d'entrée de la session (attribution WooCommerce).
	 */
	public const ENTRY_META = '_wc_order_attribution_session_entry';

	/**
	 * Début de la session, `AAAA-MM-JJ HH:MM:SS` en UTC (attribution WooCommerce).
	 */
	public const START_META = '_wc_order_attribution_session_start_time';

	/**
	 * Caractères et longueur permis pour un fbclid : ceux du cookie `_fbc`
	 * (`Capi_Signals::COOKIE_PATTERN` et script `fbc_bootstrap_script()`).
	 */
	private const FBCLID_PATTERN = '/^[A-Za-z0-9_-]{1,500}$/D';

	/**
	 * Âge maximal du fbclid à la commande : durée de vie du cookie `_fbc`
	 * (90 jours, `max-age=7776000`), au-delà duquel il n'existerait plus.
	 */
	private const MAX_AGE = 7776000;

	/**
	 * Avance maximale tolérée de l'horloge du téléphone sur celle du serveur
	 * (24 h, F3) : un début de session postérieur à la commande d'au plus
	 * cette durée prend la date de création de la commande.
	 */
	private const CLOCK_AHEAD = 86400;

	/**
	 * `fbc` à envoyer pour une commande : le cookie figé, sinon l'attribution.
	 *
	 * Même résultat pour OrderPlaced et Purchase : les deux sources sont des
	 * métadonnées figées à la commande, jamais la requête courante (la borne
	 * F3 lit la date de création, qui ne change que si elle est modifiée dans
	 * l'administration).
	 *
	 * @param \WC_Order $order Commande concernée.
	 * @return string Valeur au format Meta, ou chaîne vide.
	 */
	public static function for_order( \WC_Order $order ): string {
		$cookie = Capi_Signals::clean_cookie( (string) $order->get_meta( '_famma_fbc' ) );

		return '' !== $cookie ? $cookie : self::from_attribution( $order );
	}

	/**
	 * `fbc` reconstruit depuis l'attribution WooCommerce de la commande.
	 *
	 * @param \WC_Order $order Commande concernée.
	 * @return string Valeur au format Meta, ou chaîne vide.
	 */
	public static function from_attribution( \WC_Order $order ): string {
		$entry = trim( (string) $order->get_meta( self::ENTRY_META ) );
		$start = trim( (string) $order->get_meta( self::START_META ) );
		if ( '' === $entry || '' === $start ) {
			return '';
		}
		$created = $order->get_date_created();

		return self::build( $entry, $start, null !== $created ? $created->getTimestamp() : time() );
	}

	/**
	 * Assemble fb.1.<début de session en ms>.<fbclid exact>, validé comme le cookie.
	 *
	 * Un début de session postérieur à la commande de 24 h au plus (horloge du
	 * téléphone en avance) est remplacé par la date de création de la
	 * commande ; au-delà, ou plus de 90 jours avant la commande, rien n'est
	 * envoyé (F3).
	 *
	 * @param string $entry      URL d'entrée de la session.
	 * @param string $start      Début de session, `AAAA-MM-JJ HH:MM:SS` UTC.
	 * @param int    $order_time Horodatage de création de la commande (secondes).
	 * @return string Valeur au format Meta, ou chaîne vide.
	 */
	public static function build( string $entry, string $start, int $order_time ): string {
		$fbclid  = self::fbclid( $entry );
		$started = self::start_time( $start );
		if ( '' === $fbclid || null === $started || $started - $order_time > self::CLOCK_AHEAD ) {
			return '';
		}
		// Horloge du téléphone en avance (F3) : le serveur a vu ce fbclid au plus tard à la commande.
		$observed = min( $started, $order_time );
		if ( $order_time - $observed > self::MAX_AGE ) {
			return '';
		}

		return Capi_Signals::clean_cookie( 'fb.1.' . ( $observed * 1000 ) . '.' . $fbclid );
	}

	/**
	 * Paramètre fbclid de l'URL d'entrée, tel quel (casse conservée), ou chaîne vide.
	 *
	 * Le PREMIER fbclid de l'URL est retenu, comme `URLSearchParams.get()`
	 * dans le script du cookie `_fbc` et dans le pixel (`parse_str()` aurait
	 * gardé le dernier). S'il est invalide, rien n'est envoyé : le navigateur
	 * n'aurait pas posé de cookie non plus.
	 *
	 * @param string $entry URL d'entrée de la session.
	 * @return string
	 */
	public static function fbclid( string $entry ): string {
		$query = wp_parse_url( $entry, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return '';
		}
		foreach ( explode( '&', $query ) as $pair ) {
			$parts = explode( '=', $pair, 2 );
			if ( 'fbclid' === urldecode( $parts[0] ) ) {
				$fbclid = urldecode( $parts[1] ?? '' );

				return 1 === preg_match( self::FBCLID_PATTERN, $fbclid ) ? $fbclid : '';
			}
		}
		return '';
	}

	/**
	 * Début de session en secondes UTC, quel que soit le fuseau de PHP ou du site.
	 *
	 * Format strict : une date impossible (31/02) ou mal formée est refusée.
	 *
	 * @param string $start Valeur enregistrée par WooCommerce.
	 * @return int|null
	 */
	public static function start_time( string $start ): ?int {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $start, new \DateTimeZone( 'UTC' ) );
		if ( false === $date || $date->format( 'Y-m-d H:i:s' ) !== $start ) {
			return null;
		}
		$time = $date->getTimestamp();

		return $time > 0 ? $time : null;
	}
}
