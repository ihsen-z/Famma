<?php
/**
 * Parcours visiteurs — contexte de la requête courante.
 *
 * Regroupe ce que le collecteur REST et les événements serveur (panier,
 * commande) doivent lire de la même façon : identifiants visiteur et session
 * posés par le navigateur, type d'appareil, empreinte d'IP, détection des
 * robots et du personnel. Une seule définition, pour que les deux chemins
 * rangent un même visiteur sous un même identifiant.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lecture du contexte visiteur, sans état.
 */
final class Visitor_Context {

	/**
	 * Cookie de l'identifiant visiteur (aléatoire, propre au site).
	 */
	public const VISITOR_COOKIE = 'famma_vid';

	/**
	 * Cookie de l'identifiant de session (30 minutes d'inactivité).
	 */
	public const SESSION_COOKIE = 'famma_sid';

	/**
	 * Indique si une chaîne est un UUID v4 tel que le script le génère.
	 *
	 * @param string $value Valeur reçue.
	 * @return bool
	 */
	public static function is_uuid( string $value ): bool {
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value );
	}

	/**
	 * Texte court assaini, reçu d'un corps JSON.
	 *
	 * @param mixed $value Valeur reçue.
	 * @param int   $max   Longueur maximale.
	 * @return string
	 */
	public static function text( $value, int $max ): string {
		return is_scalar( $value ) ? mb_substr( sanitize_text_field( (string) $value ), 0, $max ) : '';
	}

	/**
	 * Chemin d'URL sans requête ni fragment : un paramètre peut porter une
	 * donnée personnelle (clé de commande, e-mail), le chemin seul suffit.
	 *
	 * @param mixed $value Valeur reçue.
	 * @return string
	 */
	public static function path( $value ): string {
		$path = is_string( $value ) ? (string) wp_parse_url( $value, PHP_URL_PATH ) : '';
		return mb_substr( '/' . ltrim( sanitize_text_field( $path ), '/' ), 0, 255 );
	}

	/**
	 * Compte un envoi pour l'empreinte d'IP et indique si la limite est
	 * dépassée sur dix minutes.
	 *
	 * @param string $prefix Préfixe du compteur (une route = un compteur).
	 * @param int    $limit  Envois acceptés sur dix minutes.
	 * @return bool
	 */
	public static function rate_limited( string $prefix, int $limit ): bool {
		$ip_hash = self::ip_hash();
		if ( '' === $ip_hash ) {
			return false;
		}
		$key   = $prefix . $ip_hash;
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return true;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Identifiant lu dans un cookie, ou chaîne vide s'il est absent ou invalide.
	 *
	 * @param string $name Nom du cookie.
	 * @return string
	 */
	public static function cookie_id( string $name ): string {
		$raw = isset( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) : '';
		$raw = strtolower( $raw );
		return self::is_uuid( $raw ) ? $raw : '';
	}

	/**
	 * Agent utilisateur de la requête, borné.
	 *
	 * @return string
	 */
	private static function user_agent(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return substr( $ua, 0, 400 );
	}

	/**
	 * Famille d'appareil déduite de l'agent utilisateur.
	 *
	 * @return string mobile, tablet ou desktop.
	 */
	public static function device(): string {
		$ua = self::user_agent();
		if ( preg_match( '/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $ua ) ) {
			return 'tablet';
		}
		if ( preg_match( '/Mobi|iPhone|iPod|Android|Opera Mini|IEMobile/i', $ua ) ) {
			return 'mobile';
		}
		return 'desktop';
	}

	/**
	 * Empreinte salée et tronquée de l'IP : repère un abus, n'identifie personne.
	 *
	 * @return string
	 */
	public static function ip_hash(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return '' === $ip ? '' : substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 16 );
	}

	/**
	 * Indique si la requête vient d'un robot, d'un outil de mesure ou du
	 * préchauffage du cache : ces visites fausseraient l'entonnoir.
	 *
	 * @return bool
	 */
	public static function is_bot(): bool {
		$ua = self::user_agent();
		if ( '' === $ua ) {
			return true;
		}
		return 1 === preg_match( '/bot|crawl|spider|slurp|headless|lighthouse|pagespeed|gtmetrix|pingdom|lscache|curl|wget|python|facebookexternalhit|preview/i', $ua );
	}

	/**
	 * Indique si l'utilisateur connecté fait partie de l'équipe : ses propres
	 * passages (tests, vérifications) ne doivent pas compter.
	 *
	 * @return bool
	 */
	public static function is_staff(): bool {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}
}
