<?php
/**
 * Bloc « Qualité vue par Meta » de l'écran FAMMA → Mesure.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu seulement : les données viennent de `Capi_Meta_Quality::get()`.
 */
final class Capi_Meta_Quality_View {

	/**
	 * Libellé court d'un code d'échec, sans texte de Meta.
	 *
	 * @param string $reason Code.
	 * @return string
	 */
	public static function reason_label( string $reason ): string {
		$labels = array(
			'not_configured' => __( 'Pixel ou jeton non configuré', 'famma-core' ),
			'environment'    => __( 'envoi vers Meta bloqué dans cet environnement', 'famma-core' ),
			'token'          => __( 'jeton refusé par Meta', 'famma-core' ),
			'permission'     => __( 'le jeton n’a pas les droits de lecture de la qualité', 'famma-core' ),
			'limit'          => __( 'limite d’appels Meta atteinte', 'famma-core' ),
			'timeout'        => __( 'Meta n’a pas répondu à temps', 'famma-core' ),
			'network'        => __( 'connexion à Meta impossible', 'famma-core' ),
			'unexpected'     => __( 'réponse inattendue de Meta', 'famma-core' ),
			'meta_error'     => __( 'Meta a refusé la demande', 'famma-core' ),
		);
		return $labels[ $reason ] ?? $labels['meta_error'];
	}

	/**
	 * Affiche le bloc ; lit Meta d'abord si la dernière tentative a plus de 24 h.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		Capi_Meta_Quality::refresh();
		$data  = Capi_Meta_Quality::get();
		$freqs = array(
			'real_time' => __( 'en temps réel', 'famma-core' ),
			'hourly'    => __( 'chaque heure', 'famma-core' ),
			'daily'     => __( 'chaque jour', 'famma-core' ),
			'weekly'    => __( 'chaque semaine', 'famma-core' ),
		);
		?>
		<h2 id="famma-meta-quality"><?php esc_html_e( 'Qualité vue par Meta', 'famma-core' ); ?></h2>
		<p><?php esc_html_e( 'Lecture seule, une fois par jour : note de correspondance (sur 10), fréquence de réception et couverture de chaque événement.', 'famma-core' ); ?></p>
		<?php if ( 'unavailable' === $data['status'] ) : ?>
			<p>
				<?php
				/* translators: %s: short reason. */
				echo esc_html( sprintf( __( 'Lecture impossible (%s).', 'famma-core' ), self::reason_label( $data['reason'] ) ) );
				?>
			</p>
		<?php elseif ( 'none' === $data['status'] ) : ?>
			<p><?php esc_html_e( 'Pas encore de lecture.', 'famma-core' ); ?></p>
		<?php endif; ?>
		<?php if ( array() !== $data['events'] ) : ?>
			<table class="widefat striped" style="max-width:720px">
				<thead><tr>
					<th><?php esc_html_e( 'Événement', 'famma-core' ); ?></th>
					<th><?php esc_html_e( 'Note', 'famma-core' ); ?></th>
					<th><?php esc_html_e( 'Fraîcheur', 'famma-core' ); ?></th>
					<th><?php esc_html_e( 'Couverture', 'famma-core' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $data['events'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $row['event'] ?? '' ) ); ?></td>
						<td><?php echo isset( $row['score'] ) ? esc_html( number_format_i18n( (float) $row['score'], 1 ) ) : '—'; ?></td>
						<td><?php echo esc_html( '' !== ( $row['frequency'] ?? '' ) ? ( $freqs[ $row['frequency'] ] ?? (string) $row['frequency'] ) : '—' ); ?></td>
						<td><?php echo isset( $row['coverage'] ) ? esc_html( number_format_i18n( (float) $row['coverage'], 1 ) . ' %' ) : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php if ( $data['read_at'] > 0 ) : ?>
			<p>
				<?php
				/* translators: %s: date and time of the last successful read. */
				echo esc_html( sprintf( __( 'Dernière lecture réussie : %s.', 'famma-core' ), wp_date( 'd/m/Y H:i', $data['read_at'] ) ) );
				?>
			</p>
		<?php endif; ?>
		<?php
	}
}
