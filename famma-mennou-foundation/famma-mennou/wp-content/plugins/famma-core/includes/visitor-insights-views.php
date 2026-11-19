<?php
/**
 * Parcours visiteurs — onglets « Sources » et « Produits ».
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Tableaux par source/campagne et par produit.
 */
final class Visitor_Insights_Views {

	/**
	 * Entonnoir par source et campagne.
	 *
	 * @param array<int, array<string, string>> $rows Lignes de `Visitor_Insights::sources()`.
	 * @return void
	 */
	public static function sources( array $rows ): void {
		Visitor_Ui::card_open( __( 'Sources et campagnes', 'famma-core' ), __( 'Une session prend la source de son arrivée. CA commandé : commandes passées, pas encore encaissées (paiement à la livraison).', 'famma-core' ) );
		if ( array() === $rows ) {
			Visitor_Ui::empty_state( __( 'Aucune session sur la période.', 'famma-core' ) );
			Visitor_Ui::card_close();
			return;
		}
		Visitor_Ui::table_open(
			array(
				__( 'Source', 'famma-core' ),
				__( 'Campagne / annonce', 'famma-core' ),
				__( 'Sessions#', 'famma-core' ),
				__( 'Fiche produit#', 'famma-core' ),
				__( 'Panier#', 'famma-core' ),
				__( 'Formulaire#', 'famma-core' ),
				__( 'Commandes#', 'famma-core' ),
				__( 'Conversion', 'famma-core' ),
				__( 'CA commandé (DT)#', 'famma-core' ),
			)
		);
		$max = max( array_map( static fn( array $r ): int => (int) $r['sessions'], $rows ) );
		foreach ( $rows as $row ) {
			$sessions = (int) $row['sessions'];
			$source   = '' !== (string) $row['source'] ? (string) $row['source'] : __( '(inconnue)', 'famma-core' );
			$campaign = trim( implode( ' · ', array_filter( array( (string) $row['campaign'], (string) $row['content'] ) ) ) );
			echo '<tr><td><strong>' . esc_html( $source ) . '</strong>' . ( '' !== (string) $row['medium'] ? ' <span class="famma-vj__muted">' . esc_html( (string) $row['medium'] ) . '</span>' : '' ) . '</td>';
			echo Visitor_Ui::cell( $campaign ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
			echo '<td class="famma-vj__rate-cell">' . Visitor_Ui::bar( $sessions, $max ) . '<span>' . esc_html( Visitor_Ui::number( $sessions ) ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in bar().
			foreach ( array( 'product', 'cart', 'form', 'orders' ) as $col ) {
				echo Visitor_Ui::num( Visitor_Ui::number( (int) $row[ $col ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			}
			echo '<td>' . Visitor_Ui::badge( Visitor_Ui::pct( (int) $row['ordered'], $sessions ), (int) $row['ordered'] > 0 ? 'good' : 'neutral' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in badge().
			echo Visitor_Ui::num( Visitor_Ui::number( (int) $row['revenue'] ) ) . '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		}
		Visitor_Ui::table_close();
		Visitor_Ui::card_close();
	}

	/**
	 * Intérêt par produit, puis variations choisies.
	 *
	 * @param array<int, array<string, mixed>>  $rows       Lignes de `Visitor_Insights::products()`.
	 * @param array<int, array<string, string>> $variations Lignes de `Visitor_Insights::variations()`.
	 * @return void
	 */
	public static function products( array $rows, array $variations ): void {
		Visitor_Ui::card_open( __( 'Intérêt par produit', 'famma-core' ), __( 'Sessions qui ont vu la fiche, ce qu\'elles y ont fait, et celles qui ont commandé ensuite.', 'famma-core' ) );
		if ( array() === $rows ) {
			Visitor_Ui::empty_state( __( 'Aucune fiche produit vue sur la période.', 'famma-core' ) );
			Visitor_Ui::card_close();
			return;
		}
		Visitor_Ui::table_open(
			array(
				__( 'Produit', 'famma-core' ),
				__( 'Sessions#', 'famma-core' ),
				__( 'Temps actif moyen#', 'famma-core' ),
				__( 'Défilement moyen#', 'famma-core' ),
				__( 'Galerie#', 'famma-core' ),
				__( 'Vidéo#', 'famma-core' ),
				__( 'Panier#', 'famma-core' ),
				__( 'Formulaire#', 'famma-core' ),
				__( 'Vu puis commandé', 'famma-core' ),
			)
		);
		foreach ( $rows as $row ) {
			$views = (int) $row['views'];
			echo '<tr><td>' . Visitor_Ui::product_link( (int) $row['product_id'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in product_link().
			echo Visitor_Ui::num( Visitor_Ui::number( $views ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			/* translators: %s: seconds. */
			echo Visitor_Ui::num( null === $row['avg_time'] ? '—' : sprintf( __( '%s s', 'famma-core' ), number_format_i18n( (float) $row['avg_time'], 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo Visitor_Ui::num( null === $row['avg_scroll'] ? '—' : number_format_i18n( (float) $row['avg_scroll'], 0 ) . ' %' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			foreach ( array( 'gallery', 'video', 'carts', 'forms' ) as $col ) {
				echo Visitor_Ui::num( Visitor_Ui::number( (int) $row[ $col ] ) . ' · ' . Visitor_Ui::pct( (int) $row[ $col ], $views ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			}
			echo '<td class="famma-vj__rate-cell">' . Visitor_Ui::bar( (int) $row['ordered'], $views, 'good' ) . '<span>' . esc_html( Visitor_Ui::pct( (int) $row['ordered'], $views ) ) . '</span></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in bar().
		}
		Visitor_Ui::table_close();
		Visitor_Ui::card_close();

		if ( array() === $variations ) {
			return;
		}
		Visitor_Ui::card_open( __( 'Tailles et variations choisies', 'famma-core' ) );
		Visitor_Ui::table_open( array( __( 'Produit', 'famma-core' ), __( 'Variation', 'famma-core' ), __( 'Sessions#', 'famma-core' ) ) );
		foreach ( $variations as $row ) {
			echo '<tr><td>' . Visitor_Ui::product_link( (int) $row['product_id'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in product_link().
			echo Visitor_Ui::cell( urldecode( (string) $row['label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
			echo Visitor_Ui::num( Visitor_Ui::number( (int) $row['sessions'] ) ) . '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		}
		Visitor_Ui::table_close();
		Visitor_Ui::card_close();
	}
}
