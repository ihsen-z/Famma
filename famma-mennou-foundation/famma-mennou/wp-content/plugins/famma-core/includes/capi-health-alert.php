<?php
/**
 * E-mail d'alerte de l'état de santé de la mesure Meta (lot 5, R7 ; relecture R-CACHE-2).
 *
 * Jusqu'à la relecture, seul le passage au rouge envoyait un e-mail : un rouge
 * déjà allumé (Purchase manquant, tâches arrêtées) masquait toute nouvelle
 * panne, comme un jeton révoqué, et le chien de garde laisse volontairement
 * le rouge à famma-core. Désormais, chaque raison rouge pas encore annoncée
 * l'est, au plus un e-mail par 24 heures ; la liste des codes annoncés vit
 * dans `famma_capi_health_mail_codes`, la date du dernier e-mail dans
 * `famma_capi_health_mail`.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Annonce par e-mail des raisons rouges de `Capi_Health`.
 */
final class Capi_Health_Alert {

	/**
	 * Option de la date du dernier e-mail d'alerte.
	 */
	private const MAIL = 'famma_capi_health_mail';

	/**
	 * Option des codes des raisons rouges déjà annoncées par e-mail.
	 */
	private const MAIL_CODES = 'famma_capi_health_mail_codes';

	/**
	 * Annonce les raisons rouges qui ne l'ont pas encore été. Un rouge déjà
	 * allumé (Purchase manquant, tâches arrêtées) ne masque plus une autre
	 * panne (jeton révoqué) ; une raison disparue puis revenue est annoncée de
	 * nouveau. Une raison retenue par la limite de 24 h reste à annoncer.
	 *
	 * @param array<string, mixed> $state    État calculé.
	 * @param array<string, mixed> $previous État précédent.
	 * @param int                  $now      Horloge.
	 * @return void
	 */
	public static function notify( array $state, array $previous, int $now ): void {
		$red = array();
		foreach ( $state['raisons'] as $reason ) {
			if ( 'rouge' === ( $reason['niveau'] ?? '' ) ) {
				$red[] = (string) ( $reason['code'] ?? '' );
			}
		}
		$stored   = get_option( self::MAIL_CODES, array() );
		$stored   = is_array( $stored ) ? array_values( $stored ) : array();
		$notified = array_values( array_intersect( $stored, $red ) );
		if ( array() !== array_diff( $red, $notified ) && self::mail( $state, 'rouge' === $previous['statut'] && array() !== $notified, $now ) ) {
			$notified = array_values( array_unique( $red ) );
		}
		if ( $notified !== $stored ) {
			update_option( self::MAIL_CODES, $notified, false );
		}
	}

	/**
	 * E-mail à l'administrateur, au plus un par 24 heures.
	 *
	 * @param array<string, mixed> $state       État calculé.
	 * @param bool                 $already_red L'état était déjà rouge et annoncé : nouvelle panne.
	 * @param int                  $now         Horloge.
	 * @return bool Whether an e-mail was sent.
	 */
	private static function mail( array $state, bool $already_red, int $now ): bool {
		$last = (int) get_option( self::MAIL, 0 );
		if ( $last > 0 && $now - $last < Capi_Health::MAIL_INTERVAL ) {
			return false;
		}
		update_option( self::MAIL, $now, false );
		$intro = $already_red
			? __( 'La mesure Meta de la boutique, déjà en alerte rouge, signale une nouvelle panne :', 'famma-core' )
			: __( 'La mesure Meta de la boutique est passée au rouge :', 'famma-core' );
		$lines = array( $intro, '' );
		foreach ( $state['raisons'] as $reason ) {
			$lines[] = '- ' . Capi_Health_Rules::label( $reason );
		}
		$lines[] = '';
		/* translators: %s: URL of the FAMMA → Mesure screen. */
		$lines[] = sprintf( __( 'Détails et actions : %s', 'famma-core' ), admin_url( 'admin.php?page=famma-tracking' ) );

		return (bool) wp_mail( (string) get_option( 'admin_email' ), __( 'FAMMA : mesure Meta en alerte', 'famma-core' ), implode( "\n", $lines ) );
	}
}
