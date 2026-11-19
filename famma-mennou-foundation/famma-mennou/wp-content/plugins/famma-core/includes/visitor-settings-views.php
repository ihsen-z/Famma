<?php
/**
 * Parcours visiteurs — onglet « Réglages ».
 *
 * Un seul formulaire, envoyé à `admin-post.php` (`Visitor_Settings::save()`,
 * nonce et capacité vérifiés) : conservation des événements, enregistrement
 * de l'adresse IP et durée de conservation de l'IP.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu du formulaire des réglages.
 */
final class Visitor_Settings_Views {

	/**
	 * Affiche l'onglet.
	 *
	 * @param bool $saved Réglages tout juste enregistrés.
	 * @return void
	 */
	public static function render( bool $saved ): void {
		if ( $saved ) {
			echo '<div class="famma-vj__callout famma-vj__callout--good"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><p>' . esc_html__( 'Réglages enregistrés.', 'famma-core' ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="famma-vj__settings">';
		wp_nonce_field( Visitor_Settings::ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( Visitor_Settings::ACTION ) . '">';

		Visitor_Ui::card_open( __( 'Conservation du parcours', 'famma-core' ), __( 'Les événements plus anciens sont supprimés chaque nuit, par petits paquets.', 'famma-core' ) );
		self::number( 'famma_retention_days', __( 'Garder les événements pendant', 'famma-core' ), Visitor_Settings::retention_days(), 7, 365, __( 'jours (7 à 365)', 'famma-core' ) );
		Visitor_Ui::card_close();

		Visitor_Ui::card_open( __( 'Adresse IP des visiteurs', 'famma-core' ), __( 'Une adresse IP complète est une donnée personnelle : elle sert à repérer les commandes douteuses et les robots. Mentionnez-la dans la politique de confidentialité.', 'famma-core' ) );
		echo '<label class="famma-vj__switch"><input type="checkbox" name="famma_store_ip" value="1"' . checked( Visitor_Settings::store_ip(), true, false ) . '>';
		echo '<span>' . esc_html__( 'Enregistrer l\'adresse IP en clair de chaque visiteur', 'famma-core' ) . '</span></label>';
		self::number( 'famma_ip_days', __( 'Effacer l\'adresse IP après', 'famma-core' ), Visitor_Settings::ip_days(), 1, 365, __( 'jours (1 à 365, jamais plus que les événements)', 'famma-core' ) );
		echo '<p class="famma-vj__note">' . esc_html__( 'Passé ce délai, seule l\'adresse est effacée : le reste du parcours reste compté. Désactiver l\'enregistrement n\'efface pas les adresses déjà gardées ; elles partent à leur tour avec la purge.', 'famma-core' ) . '</p>';
		Visitor_Ui::card_close();

		submit_button( __( 'Enregistrer les réglages', 'famma-core' ) );
		echo '</form>';
	}

	/**
	 * Champ numérique en jours.
	 *
	 * @param string $name  Nom du champ.
	 * @param string $label Libellé avant le champ.
	 * @param int    $value Valeur actuelle.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @param string $unit  Texte après le champ.
	 * @return void
	 */
	private static function number( string $name, string $label, int $value, int $min, int $max, string $unit ): void {
		echo '<p class="famma-vj__setting"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label> ';
		echo '<input type="number" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" min="' . esc_attr( (string) $min ) . '" max="' . esc_attr( (string) $max ) . '" value="' . esc_attr( (string) $value ) . '"> ';
		echo '<span class="famma-vj__muted">' . esc_html( $unit ) . '</span></p>';
	}
}
