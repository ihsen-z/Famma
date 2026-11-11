<?php
/**
 * Parcours visiteurs — gabarit de l'entonnoir du formulaire express.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Bloc « Formulaire express » de l'onglet Synthèse : marches par produit,
 * puis envois refusés par cause.
 */
final class Visitor_Form_Funnel_Views {

	/**
	 * Affiche le bloc.
	 *
	 * @param array<string, mixed> $data Résultat de Visitor_Form_Funnel::express().
	 * @return void
	 */
	public static function render( array $data ): void {
		$products = (array) ( $data['products'] ?? array() );
		echo '<h2>' . esc_html__( 'Formulaire express : de commencé à commandé', 'famma-core' ) . '</h2>';
		if ( array() === $products ) {
			echo '<p>' . esc_html__( 'Aucun formulaire express commencé sur la période.', 'famma-core' ) . '</p>';
			return;
		}
		echo '<p class="description">' . esc_html__( 'Sessions par fiche produit. Une commande compte quand la même session a commencé le formulaire. Les envois refusés sont mesurés depuis famma-core 0.3.9.', 'famma-core' ) . '</p>';
		if ( ! empty( $data['truncated'] ) ) {
			echo '<p class="description">' . esc_html__( 'Période trop chargée : seuls les premiers événements sont comptés. Choisissez une période plus courte.', 'famma-core' ) . '</p>';
		}

		Visitor_Insights_Views::head(
			array(
				__( 'Produit', 'famma-core' ),
				__( 'Commencés#', 'famma-core' ),
				__( 'Envoyés#', 'famma-core' ),
				__( 'Envoyés / commencés#', 'famma-core' ),
				__( 'Commandes#', 'famma-core' ),
				__( 'Commandes / commencés#', 'famma-core' ),
				__( 'Sessions bloquées#', 'famma-core' ),
				__( 'Envois refusés#', 'famma-core' ),
				__( 'Bloquées puis commandées#', 'famma-core' ),
			)
		);
		foreach ( $products as $product_id => $row ) {
			$title = get_the_title( (int) $product_id );
			/* translators: %d: product ID. */
			self::row( '' !== $title ? $title : sprintf( __( 'Produit n° %d', 'famma-core' ), (int) $product_id ), (array) $row, 'td' );
		}
		echo '</tbody><tfoot>';
		self::row( __( 'Tous les produits', 'famma-core' ), (array) ( $data['total'] ?? array() ), 'th' );
		echo '</tfoot></table>';

		self::causes( (array) ( $data['causes'] ?? array() ) );
	}

	/**
	 * Une ligne de l'entonnoir.
	 *
	 * @param string             $label Produit ou total.
	 * @param array<string, int> $row   Nombres de la ligne.
	 * @param string             $cell  Balise de la première cellule : td ou th.
	 * @return void
	 */
	private static function row( string $label, array $row, string $cell ): void {
		$started = (int) ( $row['started'] ?? 0 );
		$cell    = 'th' === $cell ? 'th' : 'td';
		echo '<tr><' . $cell . '>' . esc_html( $label ) . '</' . $cell . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $cell is td or th.
		echo Visitor_Insights_Views::num( number_format_i18n( $started ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		echo Visitor_Insights_Views::num( number_format_i18n( (int) ( $row['submitted'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		echo Visitor_Insights_Views::num( Visitor_Insights_Views::pct( (int) ( $row['submitted'] ?? 0 ), $started ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		echo Visitor_Insights_Views::num( number_format_i18n( (int) ( $row['ordered'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		echo Visitor_Insights_Views::num( Visitor_Insights_Views::pct( (int) ( $row['ordered'] ?? 0 ), $started ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		echo Visitor_Insights_Views::num( number_format_i18n( (int) ( $row['blocked'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		echo Visitor_Insights_Views::num( number_format_i18n( (int) ( $row['refusals'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		echo Visitor_Insights_Views::num( number_format_i18n( (int) ( $row['recovered'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		echo '</tr>';
	}

	/**
	 * Envois refusés par cause, du plus fréquent au plus rare.
	 *
	 * @param array<string, array<string, int>> $causes Refus, sessions et sessions sans commande, par code.
	 * @return void
	 */
	private static function causes( array $causes ): void {
		echo '<h3>' . esc_html__( 'Envois refusés, par cause', 'famma-core' ) . '</h3>';
		if ( array() === $causes ) {
			echo '<p>' . esc_html__( 'Aucun envoi refusé sur la période.', 'famma-core' ) . '</p>';
			return;
		}
		Visitor_Insights_Views::head(
			array(
				__( 'Cause', 'famma-core' ),
				__( 'Envois refusés#', 'famma-core' ),
				__( 'Sessions#', 'famma-core' ),
				__( 'Sessions sans commande#', 'famma-core' ),
			)
		);
		foreach ( $causes as $code => $cause ) {
			echo '<tr><td>' . esc_html( Visitor_Admin_Views::error_label( (string) $code ) ) . '</td>';
			echo Visitor_Insights_Views::num( number_format_i18n( (int) ( $cause['refusals'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo Visitor_Insights_Views::num( number_format_i18n( (int) ( $cause['sessions'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo Visitor_Insights_Views::num( number_format_i18n( (int) ( $cause['lost'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
