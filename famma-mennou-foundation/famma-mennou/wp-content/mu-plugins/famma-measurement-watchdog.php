<?php
/**
 * Plugin Name: FAMMA — chien de garde de la mesure Meta
 * Description: Prévient l'administrateur (avis et e-mail, au plus un par jour) si famma-core est inactif, si le Pixel ou le jeton CAPI manquent, ou si l'état de santé de la mesure est rouge ou périmé. Ne dépend pas de famma-core.
 * Version:     0.1.0
 * Author:      FAMMA
 *
 * Pourquoi un mu-plugin. Si famma-core est désactivé (fausse manoeuvre,
 * erreur fatale, mise à jour ratée), plus aucun Purchase ne part et rien ne
 * le signale : l'état de santé de famma-core disparaît avec lui. Un
 * mu-plugin ne se désactive pas depuis l'administration et ne lit que des
 * options : il survit à l'arrêt de famma-core (lot 5, R7, A4).
 *
 * Ce qu'il lit, sans jamais l'afficher : la présence (non la valeur) du
 * Pixel et du jeton, et l'option `famma_capi_health` écrite par famma-core
 * (`statut` vert / orange / rouge, `verifie_le`).
 *
 * Retrait : supprimer ce fichier.
 *
 * @package Famma\Local
 */

namespace Famma\Watchdog;

defined( 'ABSPATH' ) || exit;

/**
 * Âge maximal de l'état de santé avant qu'il soit jugé périmé, en secondes.
 */
const STALE = 172800;

/**
 * Intervalle minimal entre deux e-mails du chien de garde, en secondes.
 */
const MAIL_INTERVAL = 86400;

/**
 * Problèmes constatés, sous forme de messages lisibles.
 *
 * @param int $now Horloge (tests), maintenant par défaut.
 * @return string[]
 */
function problems( int $now = 0 ): array {
	$now      = $now > 0 ? $now : time();
	$problems = array();
	$active   = (array) get_option( 'active_plugins', array() );
	if ( ! in_array( 'famma-core/famma-core.php', $active, true ) ) {
		$problems[] = __( 'L’extension FAMMA Core est inactive : aucun événement Meta serveur (OrderPlaced, Purchase) ne part.', 'famma-core' );
		return $problems;
	}
	if ( ! configured( 'FAMMA_META_PIXEL_ID', 'famma_meta_pixel_id' ) || ! configured( 'FAMMA_META_ACCESS_TOKEN', 'famma_meta_access_token' ) ) {
		$problems[] = __( 'Le Pixel Meta ou le jeton CAPI est vide : la mesure serveur est coupée.', 'famma-core' );
	}
	$health = get_option( 'famma_capi_health', array() );
	$health = is_array( $health ) ? $health : array();
	if ( 'rouge' === ( $health['statut'] ?? '' ) ) {
		$problems[] = __( 'La mesure Meta est en alerte rouge : voir FAMMA → Mesure.', 'famma-core' );
	} elseif ( $now - (int) ( $health['verifie_le'] ?? 0 ) > STALE ) {
		$problems[] = __( 'L’état de santé de la mesure Meta n’a pas été recalculé depuis plus de 48 heures.', 'famma-core' );
	}
	return $problems;
}

/**
 * Un identifiant est-il renseigné (constante prioritaire, sinon option) ?
 *
 * @param string $constant Nom de la constante.
 * @param string $option   Nom de l'option.
 * @return bool
 */
function configured( string $constant, string $option ): bool {
	if ( defined( $constant ) && '' !== trim( (string) constant( $constant ) ) ) {
		return true;
	}
	return '' !== trim( (string) get_option( $option, '' ) );
}

/**
 * Contrôle sur admin_init, après le recalcul de famma-core : e-mail au plus un par jour.
 *
 * @return void
 */
function check(): void {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$problems = problems();
	if ( array() === $problems ) {
		return;
	}
	$last = (int) get_option( 'famma_watchdog_mail', 0 );
	if ( time() - $last < MAIL_INTERVAL ) {
		return;
	}
	update_option( 'famma_watchdog_mail', time(), false );
	$body = implode( "\n", array_map( __NAMESPACE__ . '\line', $problems ) ) . "\n\n" . admin_url( 'admin.php?page=famma-tracking' );
	wp_mail( (string) get_option( 'admin_email' ), __( 'FAMMA : la mesure Meta demande votre attention', 'famma-core' ), $body );
}

/**
 * Ligne d'e-mail.
 *
 * @param string $problem Message.
 * @return string
 */
function line( string $problem ): string {
	return '- ' . $problem;
}

/**
 * Avis d'administration.
 *
 * @return void
 */
function notice(): void {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	foreach ( problems() as $problem ) {
		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $problem ) );
	}
}

add_action( 'admin_init', __NAMESPACE__ . '\check', 99 );
add_action( 'admin_notices', __NAMESPACE__ . '\notice' );
