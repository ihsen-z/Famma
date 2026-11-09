<?php
/**
 * Saisies des visiteurs — rendu de la page et de l'encadré de session.
 *
 * Échappement tardif partout (règle 4) : chaque valeur saisie par un visiteur
 * est imprimée via `esc_html()` / `esc_attr()` / `esc_url()`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Liste, réglages et encadré « Saisies » de la chronologie.
 */
final class Visitor_Inputs_Views {

	/**
	 * Lignes par page.
	 */
	private const PER_PAGE = 50;

	/**
	 * Libellés des statuts.
	 *
	 * @return array<string, string>
	 */
	public static function status_labels(): array {
		return array(
			'open'      => __( 'À relancer', 'famma-core' ),
			'called'    => __( 'Appelé', 'famma-core' ),
			'converted' => __( 'Commandé', 'famma-core' ),
			'closed'    => __( 'Clos', 'famma-core' ),
		);
	}

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view selectors.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'open';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$saved  = isset( $_GET['saved'] );
		$done   = isset( $_GET['done'] ) ? absint( wp_unslash( $_GET['done'] ) ) : -1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$labels = self::status_labels();
		$status = isset( $labels[ $status ] ) || 'all' === $status ? $status : 'open';
		$store  = Visitor_Inputs::instance();
		$counts = $store->counts();

		echo '<div class="wrap famma-inputs">';
		echo '<h1>' . esc_html__( 'Saisies à relancer', 'famma-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Ce que les visiteurs ont tapé dans le formulaire de commande sans la valider. Une ligne passe à « Commandé » toute seule dès qu\'une commande arrive avec la même session, le même visiteur ou le même téléphone.', 'famma-core' ) . '</p>';
		if ( $saved ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Réglages enregistrés. Purgez le cache LiteSpeed pour qu\'ils s\'appliquent aux pages déjà en cache.', 'famma-core' ) . '</p></div>';
		}
		if ( 1 === $done ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ligne mise à jour.', 'famma-core' ) . '</p></div>';
		} elseif ( 0 === $done ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'La ligne n\'a pas pu être mise à jour.', 'famma-core' ) . '</p></div>';
		}
		if ( ! $store->enabled() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'La capture des saisies est désactivée.', 'famma-core' ) . '</p></div>';
		}

		echo '<ul class="subsubsub">';
		$links = array();
		foreach ( $labels as $key => $label ) {
			$links[] = sprintf( '<li><a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a></li>', esc_url( Visitor_Inputs_Admin::url( array( 'status' => $key ) ) ), $key === $status ? ' class="current"' : '', esc_html( $label ), (int) $counts[ $key ] );
		}
		$links[] = sprintf( '<li><a href="%1$s"%2$s>%3$s</a></li>', esc_url( Visitor_Inputs_Admin::url( array( 'status' => 'all' ) ) ), 'all' === $status ? ' class="current"' : '', esc_html__( 'Tout', 'famma-core' ) );
		echo implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.
		echo '</ul><br class="clear">';

		self::table( $store->rows( $status, self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE ), $status );
		self::pagination( $status, $paged, 'all' === $status ? array_sum( $counts ) : (int) ( $counts[ $status ] ?? 0 ) );
		self::export_form( $status );
		self::settings_form( $store );
		echo '</div>';
	}

	/**
	 * Tableau des lignes.
	 *
	 * @param array<int, array<string, string>> $rows   Lignes.
	 * @param string                            $status Statut affiché (pour revenir).
	 * @return void
	 */
	private static function table( array $rows, string $status ): void {
		if ( array() === $rows ) {
			echo '<p>' . esc_html__( 'Aucune saisie pour ce filtre.', 'famma-core' ) . '</p>';
			return;
		}
		$labels = self::status_labels();
		$states = Visitor_Inputs::states();
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Dernière saisie', 'famma-core' ), __( 'Nom', 'famma-core' ), __( 'Téléphone', 'famma-core' ), __( 'Gouvernorat / adresse', 'famma-core' ), __( 'Produit', 'famma-core' ), __( 'Source', 'famma-core' ), __( 'Statut', 'famma-core' ), __( 'Actions', 'famma-core' ) ) as $head ) {
			echo '<th>' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$product = (int) $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : '';
			$extra   = trim( ( '' !== $row['size'] ? $row['size'] . ' ' : '' ) . ( (int) $row['quantity'] ? '× ' . (int) $row['quantity'] : '' ) );
			echo '<tr>';
			echo '<td>' . esc_html( wp_date( 'd/m H:i', strtotime( $row['updated_at'] . ' UTC' ) ) ) . '</td>';
			echo '<td>' . esc_html( $row['first_name'] ) . '</td>';
			echo '<td>' . self::phone_cell( $row ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in phone_cell().
			echo '<td>' . esc_html( trim( ( $states[ $row['state'] ] ?? '' ) . ' ' . $row['address'] . ' ' . $row['city'] ) ) . '</td>';
			echo '<td>' . esc_html( trim( $product . ( '' !== $extra ? ' — ' . $extra : '' ) ) ) . '</td>';
			echo '<td>' . esc_html( trim( $row['source'] . ' ' . $row['medium'] . ' ' . $row['campaign'] . ' ' . $row['content'] ) ) . '<br><small>' . esc_html( $row['device'] ) . '</small></td>';
			echo '<td>' . esc_html( $labels[ $row['status'] ] ?? $row['status'] );
			if ( (int) $row['order_id'] ) {
				$order = wc_get_order( (int) $row['order_id'] );
				if ( $order instanceof \WC_Order ) {
					echo ' <a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
				}
			}
			echo '</td><td>' . self::actions( $row, $status ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in actions().
		}
		echo '</tbody></table>';
	}

	/**
	 * Téléphone tel `tel:` + lien WhatsApp quand le numéro est tunisien.
	 *
	 * @param array<string, string> $row Ligne.
	 * @return string HTML échappé.
	 */
	private static function phone_cell( array $row ): string {
		if ( '' === $row['phone_norm'] ) {
			return esc_html( $row['phone'] ) . ( '' !== $row['phone'] ? ' <small>' . esc_html__( '(incomplet)', 'famma-core' ) . '</small>' : '' );
		}
		$intl = '216' . $row['phone_norm'];
		return '<a href="' . esc_url( 'tel:+' . $intl ) . '">' . esc_html( $row['phone_norm'] ) . '</a> · <a href="' . esc_url( 'https://wa.me/' . $intl ) . '" target="_blank" rel="noopener">WhatsApp</a>';
	}

	/**
	 * Boutons : parcours, appelé, clos, supprimer.
	 *
	 * @param array<string, string> $row    Ligne.
	 * @param string                $status Statut affiché (pour revenir).
	 * @return string HTML échappé.
	 */
	private static function actions( array $row, string $status ): string {
		$html  = '<a class="button button-small" href="' . esc_url( Visitor_Admin::instance()->url( array( 'session' => $row['session_id'] ) ) ) . '">' . esc_html__( 'Parcours', 'famma-core' ) . '</a> ';
		$to    = array(
			'called' => __( 'Appelé', 'famma-core' ),
			'closed' => __( 'Clore', 'famma-core' ),
			'open'   => __( 'Rouvrir', 'famma-core' ),
			'delete' => __( 'Supprimer', 'famma-core' ),
		);
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. wp_nonce_field( Visitor_Inputs_Admin::ACTION_STATUS, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="' . esc_attr( Visitor_Inputs_Admin::ACTION_STATUS ) . '">'
			. '<input type="hidden" name="id" value="' . esc_attr( $row['id'] ) . '">'
			. '<input type="hidden" name="back" value="' . esc_attr( $status ) . '">';
		foreach ( $to as $key => $label ) {
			if ( $key === $row['status'] ) {
				continue;
			}
			$html .= '<button type="submit" name="to" value="' . esc_attr( $key ) . '" class="button button-small' . ( 'delete' === $key ? ' button-link-delete' : '' ) . '">' . esc_html( $label ) . '</button> ';
		}
		return $html . '</form>';
	}

	/**
	 * Pagination simple.
	 *
	 * @param string $status Statut affiché.
	 * @param int    $paged  Page courante.
	 * @param int    $total  Lignes au total.
	 * @return void
	 */
	private static function pagination( string $status, int $paged, int $total ): void {
		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages <= 1 ) {
			return;
		}
		echo '<p>';
		for ( $p = 1; $p <= $pages; $p++ ) {
			$url = Visitor_Inputs_Admin::url(
				array(
					'status' => $status,
					'paged'  => $p,
				)
			);
			echo $p === $paged ? ' <strong>' . (int) $p . '</strong>' : ' <a href="' . esc_url( $url ) . '">' . (int) $p . '</a>';
		}
		echo '</p>';
	}

	/**
	 * Bouton d'export CSV (audience Meta « liste de clients »).
	 *
	 * @param string $status Statut exporté.
	 * @return void
	 */
	private static function export_form( string $status ): void {
		echo '<h2>' . esc_html__( 'Recibler sur Meta', 'famma-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'Télécharge les numéros valides de ce filtre (format +216, prénom, gouvernorat) pour créer une audience personnalisée « liste de clients » dans le Gestionnaire de publicités.', 'famma-core' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( Visitor_Inputs_Admin::ACTION_EXPORT );
		echo '<input type="hidden" name="action" value="' . esc_attr( Visitor_Inputs_Admin::ACTION_EXPORT ) . '">';
		echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '">';
		submit_button( __( 'Exporter en CSV', 'famma-core' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Formulaire des réglages.
	 *
	 * @param Visitor_Inputs $store Stockage.
	 * @return void
	 */
	private static function settings_form( Visitor_Inputs $store ): void {
		echo '<h2>' . esc_html__( 'Réglages', 'famma-core' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( Visitor_Inputs_Admin::ACTION_SETTINGS );
		echo '<input type="hidden" name="action" value="' . esc_attr( Visitor_Inputs_Admin::ACTION_SETTINGS ) . '">';
		echo '<table class="form-table"><tr><th scope="row">' . esc_html__( 'Capture des saisies', 'famma-core' ) . '</th><td><label><input type="checkbox" name="enabled" value="1"' . checked( $store->enabled(), true, false ) . '> ' . esc_html__( 'Enregistrer ce que les visiteurs tapent dans le formulaire de commande', 'famma-core' ) . '</label></td></tr>';
		echo '<tr><th scope="row"><label for="famma-inputs-days">' . esc_html__( 'Conservation (jours)', 'famma-core' ) . '</label></th><td><input type="number" id="famma-inputs-days" name="days" min="7" max="180" value="' . esc_attr( (string) $store->retention_days() ) . '"></td></tr></table>';
		submit_button( __( 'Enregistrer les réglages', 'famma-core' ) );
		echo '</form>';
	}

	/**
	 * Encadré « Saisies » en tête de la chronologie d'une session.
	 *
	 * @param string $session_id Session.
	 * @return void
	 */
	public static function session_panel( string $session_id ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$row = Visitor_Inputs::instance()->for_session( $session_id );
		if ( null === $row ) {
			return;
		}
		$states = Visitor_Inputs::states();
		$parts  = array(
			__( 'Nom', 'famma-core' )         => $row['first_name'],
			__( 'Téléphone', 'famma-core' )   => $row['phone'],
			__( 'Gouvernorat', 'famma-core' ) => $states[ $row['state'] ] ?? '',
			__( 'Adresse', 'famma-core' )     => trim( $row['address'] . ' ' . $row['city'] ),
			__( 'Taille', 'famma-core' )      => $row['size'],
			__( 'Quantité', 'famma-core' )    => (int) $row['quantity'] ? (string) (int) $row['quantity'] : '',
			__( 'Statut', 'famma-core' )      => self::status_labels()[ $row['status'] ] ?? $row['status'],
		);
		echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Saisies de cette session', 'famma-core' ) . '</strong> — ';
		$out = array();
		foreach ( $parts as $label => $value ) {
			if ( '' !== (string) $value ) {
				$out[] = esc_html( $label . ' : ' . $value );
			}
		}
		echo implode( ' · ', $out ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.
	}
}
