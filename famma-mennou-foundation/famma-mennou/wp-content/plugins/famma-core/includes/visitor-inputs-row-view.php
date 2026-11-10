<?php
/**
 * Saisies des visiteurs — une ligne du tableau « Saisies à relancer ».
 *
 * Statut : liste déroulante enregistrée sur place (AJAX), avec un bouton
 * « OK » de secours sans JavaScript. Remarque : zone de texte enregistrée à
 * la sortie du champ. Actions : créer la commande, voir la commande, parcours,
 * supprimer. Échappement tardif partout (règle 4).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu d'une ligne de saisie.
 */
final class Visitor_Inputs_Row_View {

	/**
	 * Affiche la ligne.
	 *
	 * @param array<string, string>                                     $row Ligne.
	 * @param array{status: string, s: string, product: int, days: int} $f   Filtres (pour revenir).
	 * @return void
	 */
	public static function render( array $row, array $f ): void {
		$id    = (int) $row['id'];
		$order = Visitor_Inputs_Order::linked_order( $row );
		printf( '<tr data-id="%1$d" class="famma-inputs__row famma-inputs__row--%2$s">', (int) $id, esc_attr( $row['status'] ) );
		echo '<td>' . self::when( $row ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in when().
		echo '<td>' . self::client( $row ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in client().
		echo '<td>' . self::address( $row ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in address().
		echo '<td>' . self::product( $row ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in product().
		echo '<td>' . esc_html( trim( $row['campaign'] . ( '' !== $row['content'] ? ' · ' . $row['content'] : '' ) ) ) . '<br><small>' . esc_html( trim( $row['source'] . ' ' . $row['medium'] . ' · ' . $row['device'], ' ·' ) ) . '</small></td>';
		echo '<td>' . self::status( $row, $order, $f ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in status().
		printf(
			'<td><textarea class="famma-inputs__note" data-id="%1$d" rows="2" maxlength="%2$d" aria-label="%3$s" placeholder="%4$s">%5$s</textarea><span class="famma-inputs__saved" role="status"></span></td>',
			(int) $id,
			(int) Visitor_Inputs::NOTE_MAX,
			esc_attr__( 'Remarque', 'famma-core' ),
			esc_attr__( 'Écrire une remarque…', 'famma-core' ),
			esc_textarea( (string) ( $row['note'] ?? '' ) )
		);
		echo '<td class="famma-inputs__actions">' . self::actions( $row, $order, $f ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in actions().
	}

	/**
	 * Date relative, date exacte en info-bulle.
	 *
	 * @param array<string, string> $row Ligne.
	 * @return string HTML échappé.
	 */
	private static function when( array $row ): string {
		$ts = (int) strtotime( $row['updated_at'] . ' UTC' );
		/* translators: %s: time elapsed, e.g. "2 heures". */
		$ago = sprintf( __( 'il y a %s', 'famma-core' ), human_time_diff( $ts ) );
		return '<span title="' . esc_attr( (string) wp_date( 'd/m/Y H:i', $ts ) ) . '">' . esc_html( $ago ) . '</span><br><small>' . esc_html( (string) wp_date( 'd/m H:i', $ts ) ) . '</small>';
	}

	/**
	 * Prénom, téléphone cliquable et bouton WhatsApp.
	 *
	 * @param array<string, string> $row Ligne.
	 * @return string HTML échappé.
	 */
	private static function client( array $row ): string {
		$name = '' !== $row['first_name'] ? '<strong>' . esc_html( $row['first_name'] ) . '</strong>' : '<em>' . esc_html__( 'Sans nom', 'famma-core' ) . '</em>';
		if ( '' === $row['phone_norm'] ) {
			return $name . '<br>' . esc_html( $row['phone'] ) . ( '' !== $row['phone'] ? ' <small>' . esc_html__( '(incomplet)', 'famma-core' ) . '</small>' : '' );
		}
		$intl = '216' . $row['phone_norm'];
		return $name . '<br><a class="famma-inputs__tel" href="' . esc_url( 'tel:+' . $intl ) . '">' . esc_html( $row['phone_norm'] ) . '</a> <a class="famma-inputs__wa" href="' . esc_url( 'https://wa.me/' . $intl ) . '" target="_blank" rel="noopener">' . esc_html__( 'WhatsApp', 'famma-core' ) . '</a>';
	}

	/**
	 * Gouvernorat, adresse, ville.
	 *
	 * @param array<string, string> $row Ligne.
	 * @return string HTML échappé.
	 */
	private static function address( array $row ): string {
		$state = Visitor_Inputs::states()[ $row['state'] ] ?? '';
		$line  = trim( $row['address'] . ( '' !== $row['city'] ? ', ' . $row['city'] : '' ), ', ' );
		return ( '' !== $state ? '<strong>' . esc_html( $state ) . '</strong>' : '<em>' . esc_html__( 'Gouvernorat ?', 'famma-core' ) . '</em>' ) . ( '' !== $line ? '<br>' . esc_html( $line ) : '' );
	}

	/**
	 * Produit, taille, quantité.
	 *
	 * @param array<string, string> $row Ligne.
	 * @return string HTML échappé.
	 */
	private static function product( array $row ): string {
		$id = (int) $row['product_id'];
		if ( ! $id ) {
			return '—';
		}
		$extra = array_filter(
			array(
				'' !== $row['size'] ? __( 'Taille', 'famma-core' ) . ' ' . $row['size'] : '',
				(int) $row['quantity'] ? '× ' . (int) $row['quantity'] : '',
			)
		);
		return esc_html( get_the_title( $id ) ) . ( array() !== $extra ? '<br><small>' . esc_html( implode( ' · ', $extra ) ) . '</small>' : '' );
	}

	/**
	 * Champs cachés qui ramènent à la même vue après une action sans JavaScript.
	 *
	 * @param array{status: string, s: string, product: int, days: int} $f Filtres.
	 * @return string HTML échappé.
	 */
	private static function back( array $f ): string {
		$out = '';
		foreach ( $f as $key => $value ) {
			$out .= '<input type="hidden" name="back[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '">';
		}
		return $out;
	}

	/**
	 * Liste des statuts (enregistrée sur place) et lien vers la commande.
	 *
	 * @param array<string, string>                                     $row   Ligne.
	 * @param \WC_Order|null                                            $order Commande reliée.
	 * @param array{status: string, s: string, product: int, days: int} $f     Filtres.
	 * @return string HTML échappé.
	 */
	private static function status( array $row, ?\WC_Order $order, array $f ): string {
		$html = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="famma-inputs__status-form">'
			. wp_nonce_field( Visitor_Inputs_Admin::ACTION_STATUS, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="' . esc_attr( Visitor_Inputs_Admin::ACTION_STATUS ) . '">'
			. '<input type="hidden" name="id" value="' . esc_attr( $row['id'] ) . '">' . self::back( $f )
			. '<select name="to" class="famma-inputs__status" data-id="' . esc_attr( $row['id'] ) . '" aria-label="' . esc_attr__( 'Statut', 'famma-core' ) . '">';
		foreach ( Visitor_Inputs_Views::status_labels() as $key => $label ) {
			$html .= '<option value="' . esc_attr( $key ) . '"' . selected( $row['status'], $key, false ) . disabled( 'converted' === $key && 'converted' !== $row['status'], true, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select> <button type="submit" class="button button-small famma-inputs__nojs">' . esc_html__( 'OK', 'famma-core' ) . '</button><span class="famma-inputs__saved" role="status"></span></form>';
		if ( null !== $order ) {
			$html .= '<a class="famma-inputs__order-link" href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . ' · ' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</a>';
		}
		return $html;
	}

	/**
	 * Boutons : créer la commande (ou la voir), parcours, supprimer.
	 *
	 * @param array<string, string>                                     $row   Ligne.
	 * @param \WC_Order|null                                            $order Commande reliée.
	 * @param array{status: string, s: string, product: int, days: int} $f     Filtres.
	 * @return string HTML échappé.
	 */
	private static function actions( array $row, ?\WC_Order $order, array $f ): string {
		$html  = null === $order
			? Visitor_Inputs_Order::button( (int) $row['id'] )
			: '<a class="button button-small" href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Voir la commande', 'famma-core' ) . '</a>';
		$html .= '<a class="button button-small" href="' . esc_url( Visitor_Admin::instance()->url( array( 'session' => $row['session_id'] ) ) ) . '">' . esc_html__( 'Parcours', 'famma-core' ) . '</a>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="famma-inputs__delete">'
			. wp_nonce_field( Visitor_Inputs_Admin::ACTION_STATUS, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="' . esc_attr( Visitor_Inputs_Admin::ACTION_STATUS ) . '">'
			. '<input type="hidden" name="id" value="' . esc_attr( $row['id'] ) . '">' . self::back( $f )
			. '<button type="submit" name="to" value="delete" class="button-link button-link-delete">' . esc_html__( 'Supprimer', 'famma-core' ) . '</button></form>';
		return $html;
	}
}
