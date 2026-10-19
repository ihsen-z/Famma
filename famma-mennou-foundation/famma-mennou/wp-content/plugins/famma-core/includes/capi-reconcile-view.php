<?php
/**
 * Écran du rapprochement sur 14 jours, dans « Mesure » (lot 5, R8, A5).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu du tableau ; le calcul vit dans `Capi_Reconcile`.
 */
final class Capi_Reconcile_View {

	/**
	 * Affiche le rapprochement.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'wc_get_orders' ) ) {
			return;
		}
		$data   = Capi_Reconcile::instance()->build( time() );
		$totals = $data['totals'];
		?>
		<h2 id="famma-reconcile"><?php esc_html_e( 'Rapprochement des 14 derniers jours', 'famma-core' ); ?></h2>
		<?php self::resend_result(); ?>
		<?php Capi_Purchase_Gaps::result(); ?>
		<p><?php esc_html_e( 'Chaque commande doit avoir un OrderPlaced accepté par Meta, et chaque commande livrée un Purchase. « Renvoyer » rejoue le même identifiant d’événement : Meta le dédoublonne.', 'famma-core' ); ?></p>
		<ul>
			<li>
				<?php
				/* translators: 1: accepted count, 2: expected count. */
				echo esc_html( sprintf( __( 'OrderPlaced : %1$d accepté(s) sur %2$d attendu(s).', 'famma-core' ), $totals['orderplaced_accepted'], $totals['orderplaced_expected'] ) );
				?>
			</li>
			<li>
				<?php
				/* translators: 1: accepted count, 2: expected count. */
				echo esc_html( sprintf( __( 'Purchase : %1$d accepté(s) sur %2$d attendu(s).', 'famma-core' ), $totals['purchase_accepted'], $totals['purchase_expected'] ) );
				?>
			</li>
			<li><?php echo esc_html( self::ineligible_line( $totals['ineligible'] ) ); ?></li>
			<li>
				<?php
				/* translators: %d: number of test orders. */
				echo esc_html( sprintf( __( 'Commandes de test exclues : %d.', 'famma-core' ), $totals['test_orders'] ) );
				?>
			</li>
		</ul>
		<?php if ( $data['truncated'] ) : ?>
			<p><?php esc_html_e( 'Seules les 200 commandes les plus récentes sont rapprochées.', 'famma-core' ); ?></p>
		<?php endif; ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Commande', 'famma-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Statut', 'famma-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Origine', 'famma-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'OrderPlaced', 'famma-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Purchase', 'famma-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'fbc présent', 'famma-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data['rows'] as $row ) : ?>
					<?php $order = $row['order']; ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( (string) $order->get_order_number() ); ?></a>
							<?php if ( $row['test'] ) : ?>
								<br /><em><?php esc_html_e( 'commande de test, exclue', 'famma-core' ); ?></em>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
						<td><?php echo esc_html( '' !== (string) $order->get_created_via() ? (string) $order->get_created_via() : '-' ); ?></td>
						<td><?php self::cell( $order, 'OrderPlaced', $row['orderplaced'] ); ?></td>
						<td><?php self::cell( $order, 'Purchase', $row['purchase'] ); ?></td>
						<td><?php echo esc_html( $row['fbc'] ? __( 'oui', 'famma-core' ) : __( 'non', 'famma-core' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Une cellule d'événement, avec le bouton « Renvoyer » quand il est permis
	 * et, pour un Purchase attendu non accepté, « Acquitter l'alerte ».
	 *
	 * @param \WC_Order            $order Commande.
	 * @param string               $event OrderPlaced ou Purchase.
	 * @param array<string, mixed> $cell  État calculé.
	 * @return void
	 */
	private static function cell( \WC_Order $order, string $event, array $cell ): void {
		$states = array(
			'accepted'  => __( 'accepté', 'famma-core' ),
			'rejected'  => __( 'rejeté', 'famma-core' ),
			'uncertain' => __( 'incertain', 'famma-core' ),
			'sending'   => __( 'envoi en cours', 'famma-core' ),
			'absent'    => __( 'jamais envoyé', 'famma-core' ),
		);
		if ( ! $cell['expected'] && 'accepted' !== $cell['state'] ) {
			echo esc_html( self::reason_label( (string) $cell['reason'] ) );
			return;
		}
		echo esc_html( $states[ $cell['state'] ] ?? (string) $cell['state'] );
		if ( 'Purchase' === $event && 'accepted' !== $cell['state'] ) {
			Capi_Purchase_Gaps::render_ack( $order );
		}
		if ( ! $cell['resend'] ) {
			return;
		}
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="famma_capi_resend" />
			<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
			<input type="hidden" name="event" value="<?php echo esc_attr( $event ); ?>" />
			<?php wp_nonce_field( 'famma_capi_resend' ); ?>
			<?php submit_button( __( 'Renvoyer', 'famma-core' ), 'small', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Ligne « inéligibles : N (motifs) ».
	 *
	 * @param array<string, int> $ineligible Motif => nombre.
	 * @return string
	 */
	private static function ineligible_line( array $ineligible ): string {
		$parts = array();
		foreach ( $ineligible as $reason => $count ) {
			$parts[] = self::reason_label( (string) $reason ) . ' : ' . (int) $count;
		}
		return sprintf(
			/* translators: 1: number of ineligible events, 2: reasons. */
			__( 'Inéligibles : %1$d (%2$s).', 'famma-core' ),
			array_sum( $ineligible ),
			array() === $parts ? __( 'aucun', 'famma-core' ) : implode( ', ', $parts )
		);
	}

	/**
	 * Libellé d'un motif d'inéligibilité.
	 *
	 * @param string $reason Motif.
	 * @return string
	 */
	private static function reason_label( string $reason ): string {
		$labels = array(
			'admin'         => __( 'commande créée hors du site', 'famma-core' ),
			'disabled'      => __( 'OrderPlaced désactivé', 'famma-core' ),
			'status'        => __( 'commande annulée, échouée ou refusée', 'famma-core' ),
			'not_accepted'  => __( 'checkout non accepté ou OrderPlaced coupé à ce moment', 'famma-core' ),
			'not_delivered' => __( 'pas encore livrée', 'famma-core' ),
			'not_collected' => __( '« Terminée » sans encaissement à la livraison (avant la 0.3.2) : repassez-la en « Livrée » pour l’encaisser et envoyer le Purchase', 'famma-core' ),
		);
		return $labels[ $reason ] ?? $reason;
	}

	/**
	 * Résultat du dernier renvoi.
	 *
	 * @return void
	 */
	private static function resend_result(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only fixed result identifier.
		$result = isset( $_GET['famma_capi_resend'] ) ? sanitize_key( wp_unslash( $_GET['famma_capi_resend'] ) ) : '';
		if ( '' === $result ) {
			return;
		}
		$message = 'accepted' === $result
			? __( 'Renvoi accepté par Meta.', 'famma-core' )
			/* translators: %s: outcome identifier. */
			: sprintf( __( 'Renvoi non accepté (issue : %s). Le détail est dans le journal famma-capi.', 'famma-core' ), $result );
		printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( 'accepted' === $result ? 'success' : 'warning' ), esc_html( $message ) );
	}
}
