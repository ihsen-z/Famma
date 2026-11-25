<?php
/**
 * E-mail d'alerte de l'état de santé de la mesure Meta (lot 5, R7 ; relecture
 * R-CACHE-2 ; phase B des réformes du pixel, lot 4, D3).
 *
 * Destinataire : l'option `famma_capi_alert_email` (« Adresse des alertes »
 * dans FAMMA → Mesure). Champ vide : aucun e-mail, les alertes restent
 * visibles dans l'administration seulement (décision Q15 : l'adresse réelle
 * est saisie dans l'admin, jamais dans le code).
 *
 * Tant que l'état est rouge, un e-mail par jour au plus (l'hébergeur limite
 * l'envoi à 40 e-mails par jour) : le premier annonce le passage au rouge,
 * les suivants une nouvelle panne ou le rappel quotidien. Le calcul tourne
 * aussi par WP-Cron (`Capi_Health_Watch`, chaque heure), donc le rappel part
 * même sans visite de l'administration. Quand l'état quitte le rouge après
 * un e-mail rouge, un e-mail de retour à la normale part, une fois.
 *
 * Contenu : libellés des raisons (codes, nombres, numéros de commande), le
 * lien vers « Mesure ». Jamais le jeton ni une donnée client.
 *
 * Relecture M1 : un verrou (`famma_capi_health_mail_lock`, insertion
 * atomique, 5 min au plus) empêche deux calculs simultanés (WP-Cron et
 * administration) d'envoyer deux e-mails. L'e-mail de retour part une seule
 * fois, même si `wp_mail()` échoue (pas de nouvel essai toutes les 5 min) ;
 * un e-mail rouge raté consomme lui aussi sa place de 24 h.
 *
 * Options : `famma_capi_health_mail` (date du dernier e-mail rouge),
 * `famma_capi_health_mail_codes` (codes rouges annoncés),
 * `famma_capi_health_mail_red` (un rouge a été annoncé et n'est pas encore
 * levé).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Annonce par e-mail de l'état rouge de `Capi_Health`, puis de son retour.
 */
final class Capi_Health_Alert {

	/**
	 * Option de l'adresse des alertes (vide : aucun e-mail).
	 */
	public const EMAIL = 'famma_capi_alert_email';

	/**
	 * Option de la date du dernier e-mail d'alerte rouge.
	 */
	private const MAIL = 'famma_capi_health_mail';

	/**
	 * Option des codes des raisons rouges déjà annoncées par e-mail.
	 */
	private const MAIL_CODES = 'famma_capi_health_mail_codes';

	/**
	 * Option : un rouge a été annoncé et son retour pas encore.
	 */
	private const MAIL_RED = 'famma_capi_health_mail_red';

	/**
	 * Option-verrou de l'envoi.
	 */
	public const LOCK = 'famma_capi_health_mail_lock';

	/**
	 * Durée de vie du verrou, en secondes.
	 */
	private const LOCK_TTL = 300;

	/**
	 * Adresse des alertes, vide si absente ou invalide.
	 *
	 * @return string
	 */
	public static function recipient(): string {
		$email = trim( (string) get_option( self::EMAIL, '' ) );
		return '' !== $email && ( ! function_exists( 'is_email' ) || is_email( $email ) ) ? $email : '';
	}

	/**
	 * Rouge : un e-mail par 24 heures au plus. Sorti du rouge après un e-mail : un e-mail de retour.
	 *
	 * @param array<string, mixed> $state    État calculé.
	 * @param array<string, mixed> $previous État précédent (non utilisé : la règle suit les e-mails envoyés).
	 * @param int                  $now      Horloge.
	 * @return void
	 */
	public static function notify( array $state, array $previous, int $now ): void {
		$to = self::recipient();
		if ( '' === $to || ! self::lock( $now ) ) {
			return;
		}
		try {
			self::dispatch( $to, $state, $now );
		} finally {
			self::unlock();
		}
	}

	/**
	 * Choix et envoi de l'e-mail, sous verrou.
	 *
	 * @param string               $to    Destinataire.
	 * @param array<string, mixed> $state État calculé.
	 * @param int                  $now   Horloge.
	 * @return void
	 */
	private static function dispatch( string $to, array $state, int $now ): void {
		$red = array();
		foreach ( $state['raisons'] as $reason ) {
			if ( 'rouge' === ( $reason['niveau'] ?? '' ) ) {
				$red[] = (string) ( $reason['code'] ?? '' );
			}
		}
		$announced = (int) get_option( self::MAIL_RED, 0 ) > 0;
		if ( array() === $red ) {
			if ( $announced ) {
				// Une seule fois, réussi ou non : jamais de nouvel essai à chaque calcul (relecture M1).
				delete_option( self::MAIL_RED );
				update_option( self::MAIL_CODES, array(), false );
				self::send( $to, self::recovery( $state ), __( 'FAMMA : mesure Meta rétablie', 'famma-core' ) );
			}
			return;
		}
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::MAIL, 'options' );
		}
		$last = (int) get_option( self::MAIL, 0 );
		if ( $last > 0 && $now - $last < Capi_Health::MAIL_INTERVAL ) {
			return;
		}
		$stored = get_option( self::MAIL_CODES, array() );
		$stored = is_array( $stored ) ? array_values( $stored ) : array();
		if ( ! $announced ) {
			$intro = __( 'La mesure Meta de la boutique est passée au rouge :', 'famma-core' );
		} elseif ( array() !== array_diff( $red, $stored ) ) {
			$intro = __( 'La mesure Meta de la boutique, déjà en alerte rouge, signale une nouvelle panne :', 'famma-core' );
		} else {
			$intro = __( 'Rappel quotidien : la mesure Meta de la boutique est toujours en alerte rouge :', 'famma-core' );
		}
		update_option( self::MAIL, $now, false );
		if ( self::send( $to, self::body( $intro, $state ), __( 'FAMMA : mesure Meta en alerte', 'famma-core' ) ) ) {
			update_option( self::MAIL_CODES, array_values( array_unique( $red ) ), false );
			update_option( self::MAIL_RED, $now, false );
		}
	}

	/**
	 * Prend le verrou d'envoi : insertion atomique dans la table des options
	 * (un verrou de plus de 5 min est repris), en mémoire sans base (tests).
	 *
	 * @param int $now Horloge.
	 * @return bool
	 */
	private static function lock( int $now ): bool {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			if ( (int) get_option( self::LOCK, 0 ) > $now - self::LOCK_TTL ) {
				return false;
			}
			update_option( self::LOCK, $now, false );
			return true;
		}
		for ( $try = 0; $try < 2; $try++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- l'insertion unique est le verrou ; jamais mise en cache.
			if ( 1 === (int) $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)', $wpdb->options, self::LOCK, (string) $now, 'off' ) ) ) {
				return true;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- verrou relu en direct.
			$held = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, self::LOCK ) );
			if ( $now - $held <= self::LOCK_TTL ) {
				return false;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- reprise d'un verrou abandonné, à valeur égale seulement.
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND option_value = %s', $wpdb->options, self::LOCK, (string) $held ) );
		}
		return false;
	}

	/**
	 * Rend le verrou d'envoi.
	 *
	 * @return void
	 */
	private static function unlock(): void {
		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- verrou hors API des options.
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s', $wpdb->options, self::LOCK ) );
			return;
		}
		delete_option( self::LOCK );
	}

	/**
	 * Corps de l'e-mail de retour : vert, ou orange avec ce qui reste à surveiller.
	 *
	 * @param array<string, mixed> $state État calculé.
	 * @return string
	 */
	private static function recovery( array $state ): string {
		if ( array() === $state['raisons'] ) {
			return self::body( __( 'La mesure Meta de la boutique est revenue au vert : plus aucune alerte.', 'famma-core' ), $state );
		}
		return self::body( __( 'La mesure Meta de la boutique n’est plus en alerte rouge. Reste à surveiller :', 'famma-core' ), $state );
	}

	/**
	 * Corps d'un e-mail : introduction, raisons, lien vers « Mesure ».
	 *
	 * @param string               $intro Introduction.
	 * @param array<string, mixed> $state État calculé.
	 * @return string
	 */
	private static function body( string $intro, array $state ): string {
		$lines = array( $intro, '' );
		foreach ( $state['raisons'] as $reason ) {
			$lines[] = '- ' . Capi_Health_Rules::label( (array) $reason );
		}
		$lines[] = '';
		/* translators: %s: URL of the FAMMA → Mesure screen. */
		$lines[] = sprintf( __( 'Détails et actions : %s', 'famma-core' ), admin_url( 'admin.php?page=famma-tracking' ) );
		return implode( "\n", $lines );
	}

	/**
	 * Envoi.
	 *
	 * @param string $to      Destinataire.
	 * @param string $body    Corps.
	 * @param string $subject Sujet.
	 * @return bool
	 */
	private static function send( string $to, string $body, string $subject ): bool {
		return (bool) wp_mail( $to, $subject, $body );
	}
}
