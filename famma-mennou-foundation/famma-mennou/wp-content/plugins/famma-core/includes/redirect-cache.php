<?php
/**
 * Redirections publiques : chaîne de requête du visiteur conservée, réponse
 * jamais mise en cache (plan pixel, lot 5, défaut D2).
 *
 * Constats du 19/10/2026 :
 * - T3.6 (clone) : la 301 de `wp_old_slug_redirect()` part vers le permalien
 *   nu, le `fbclid` et les `utm_*` du lien publicitaire sont perdus ;
 * - T4.2-bis (prod) : LiteSpeed Cache garde 1 h les 301 sous une clé qui
 *   ignore `fbclid` et `utm_*`, et sert à chacun la redirection du premier
 *   visiteur, ses paramètres compris, ou aucun. L'en-tête `Cache-Control:
 *   no-cache` de WordPress n'y change rien : LiteSpeed n'obéit qu'à son
 *   propre en-tête, posé par l'action `litespeed_control_set_nocache`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Garde la chaîne de requête dans les redirections et les exclut du cache.
 */
final class Redirect_Cache {

	/**
	 * Nombre maximal de paramètres recopiés dans la redirection.
	 */
	private const MAX_ARGS = 50;

	/**
	 * Longueur maximale d'une valeur recopiée : au-delà, le paramètre est
	 * écarté plutôt que tronqué (un fbclid tronqué serait faux).
	 */
	private const MAX_VALUE_LENGTH = 1024;

	/**
	 * Clés acceptées : lettres ASCII, chiffres, tiret et soulignement.
	 */
	private const KEY_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

	/**
	 * Instance partagée.
	 *
	 * @var Redirect_Cache|null
	 */
	private static ?Redirect_Cache $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Redirect_Cache
	 */
	public static function instance(): Redirect_Cache {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks, sur le front seulement (règle 10) : les
	 * redirections de l'administration ne passent jamais par le cache public.
	 *
	 * @return void
	 */
	private function hooks(): void {
		if ( is_admin() ) {
			return;
		}
		add_filter( 'old_slug_redirect_url', array( $this, 'keep_query_string' ), 20 );
		// Priorité tardive : le statut et l'adresse sont alors définitifs.
		add_filter( 'wp_redirect_status', array( $this, 'forbid_cache' ), 999, 2 );
	}

	/**
	 * Filtre `old_slug_redirect_url` : recopie la chaîne de requête du
	 * visiteur sur le nouveau permalien.
	 *
	 * Les variables de requête publiques de WordPress (`name`, `p`, `product`…)
	 * ne sont pas recopiées : elles désignaient l'ancien article, et la
	 * requête GET passant avant le permalien, les garder ramènerait à l'ancien
	 * slug, donc à une boucle de redirections.
	 *
	 * @param mixed $link Adresse de redirection calculée par WordPress.
	 * @return mixed
	 */
	public function keep_query_string( mixed $link ): mixed {
		if ( ! is_string( $link ) || '' === $link ) {
			return $link;
		}
		return self::append_visitor_query( $link, self::public_query_vars() );
	}

	/**
	 * Filtre `wp_redirect_status` : une redirection du front n'est jamais
	 * mise en cache. Le statut est rendu tel quel.
	 *
	 * @param mixed $status   Code HTTP de la redirection.
	 * @param mixed $location Adresse de destination.
	 * @return mixed
	 */
	public function forbid_cache( mixed $status, mixed $location = '' ): mixed {
		$code = (int) $status;
		if ( $code < 300 || $code > 399 || ! is_string( $location ) || '' === $location ) {
			return $status;
		}

		/**
		 * Permet, par exception, de laisser une redirection en cache.
		 *
		 * @param bool   $nocache  Vrai par défaut : la redirection n'est pas mise en cache.
		 * @param int    $code     Code HTTP de la redirection.
		 * @param string $location Adresse de destination.
		 */
		if ( ! (bool) apply_filters( 'famma_redirect_nocache', true, $code, $location ) ) {
			return $status;
		}

		self::mark_uncacheable( 'famma: redirection ' . $code . ' jamais en cache' );
		return $status;
	}

	/**
	 * Marque la réponse en cours comme non cachable : API documentée de
	 * LiteSpeed Cache (sans effet si l'extension est absente), puis en-têtes
	 * standard pour tout autre cache (navigateur, mandataire, CDN).
	 *
	 * @param string $reason Raison inscrite dans le journal de LiteSpeed Cache.
	 * @return void
	 */
	public static function mark_uncacheable( string $reason ): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API publique de LiteSpeed Cache, sans effet si l'extension est absente.
		do_action( 'litespeed_control_set_nocache', $reason );
		nocache_headers();
	}

	/**
	 * Ajoute à une adresse la chaîne de requête du visiteur, assainie.
	 *
	 * Lecture seule de `$_GET`, sans effet sur l'état du site : aucun nonce
	 * n'a de sens sur un lien publicitaire.
	 *
	 * @param string   $url      Adresse de destination.
	 * @param string[] $excluded Clés à ne jamais recopier.
	 * @return string
	 */
	public static function append_visitor_query( string $url, array $excluded = array() ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Lecture seule ; chaque clé est validée et chaque valeur passe par wp_unslash() puis sanitize_text_field() dans clean_args().
		$raw  = is_array( $_GET ) ? $_GET : array();
		$args = self::clean_args( $raw, $excluded );

		/**
		 * Filtre les paramètres recopiés dans une redirection.
		 *
		 * @param array<string, string> $args Paramètres assainis, non encodés.
		 * @param string                $url  Adresse de destination.
		 */
		$args = apply_filters( 'famma_redirect_query_args', $args, $url );

		return self::merge_query( $url, is_array( $args ) ? $args : array() );
	}

	/**
	 * Assainit les paramètres bruts du visiteur.
	 *
	 * Les valeurs sont prises décodées (comme PHP les range dans `$_GET`) puis
	 * passées à `sanitize_text_field()`, et réencodées plus tard : un octet
	 * `%C3%A9` survit (é), alors qu'assainir la chaîne encodée le supprimait
	 * (constat CODE-19 de l'audit du 23/09).
	 *
	 * Limites connues (sans effet sur `fbclid`) : un `%XX` littéral de la valeur
	 * décodée est retiré (`promo%2550ab` donne `promoab`), les espaces sont
	 * réduits ; `$_GET` a déjà changé `.` en `_` dans les clés et gardé la
	 * dernière valeur d'une clé répétée ; les tableaux sont écartés.
	 *
	 * @param array<int|string, mixed> $raw      Paramètres bruts.
	 * @param string[]                 $excluded Clés à ne jamais recopier.
	 * @return array<string, string>
	 */
	public static function clean_args( array $raw, array $excluded = array() ): array {
		$clean = array();
		foreach ( $raw as $key => $value ) {
			if ( count( $clean ) >= self::MAX_ARGS ) {
				break;
			}
			$key = (string) $key;
			if ( ! preg_match( self::KEY_PATTERN, $key ) || in_array( $key, $excluded, true ) || ! is_scalar( $value ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( (string) $value ) );
			if ( strlen( $value ) > self::MAX_VALUE_LENGTH ) {
				continue;
			}
			$clean[ $key ] = $value;
		}
		return $clean;
	}

	/**
	 * Ajoute des paramètres à une adresse sans doublon : une clé déjà présente
	 * dans l'adresse cible garde la valeur de la cible. La requête existante
	 * et le fragment ne sont pas réécrits (`add_query_arg()` réencoderait la
	 * requête existante et n'encode pas les valeurs ajoutées).
	 *
	 * @param string                    $url  Adresse de destination.
	 * @param array<int|string, scalar> $args Paramètres non encodés.
	 * @return string
	 */
	public static function merge_query( string $url, array $args ): string {
		$fragment = '';
		$hash     = strpos( $url, '#' );
		if ( false !== $hash ) {
			$fragment = substr( $url, $hash );
			$url      = substr( $url, 0, $hash );
		}

		$existing = array();
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $existing );

		$pairs = array();
		foreach ( $args as $key => $value ) {
			$key = (string) $key;
			if ( '' === $key || array_key_exists( $key, $existing ) || ! is_scalar( $value ) ) {
				continue;
			}
			$pairs[ $key ] = rawurlencode( $key ) . '=' . rawurlencode( (string) $value );
		}

		if ( array() === $pairs ) {
			return $url . $fragment;
		}

		$glue = str_contains( $url, '?' ) ? ( str_ends_with( $url, '?' ) || str_ends_with( $url, '&' ) ? '' : '&' ) : '?';
		return $url . $glue . implode( '&', $pairs ) . $fragment;
	}

	/**
	 * Variables de requête publiques de WordPress, filtre `query_vars` compris.
	 *
	 * @return string[]
	 */
	private static function public_query_vars(): array {
		global $wp;
		if ( ! is_object( $wp ) || ! isset( $wp->public_query_vars ) || ! is_array( $wp->public_query_vars ) ) {
			return array();
		}
		return array_map( 'strval', $wp->public_query_vars );
	}
}
