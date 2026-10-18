<?php
/**
 * Parcours visiteurs — gabarits des onglets Sources et Produits.
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
	 * Pourcentage arrondi, sans division par zéro.
	 *
	 * @param int $part  Partie.
	 * @param int $whole Total.
	 * @return string
	 */
	private static function pct( int $part, int $whole ): string {
		return $whole > 0 ? number_format_i18n( 100 * $part / $whole, 1 ) . ' %' : '—';
	}

	/**
	 * En-tête de tableau.
	 *
	 * @param array<int, string> $heads Libellés ; ceux suffixés « # » sont numériques.
	 * @return void
	 */
	private static function head( array $heads ): void {
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( $heads as $head ) {
			$num = str_ends_with( $head, '#' );
			echo '<th' . ( $num ? ' class="num"' : '' ) . '>' . esc_html( rtrim( $head, '#' ) ) . '</th>';
		}
		echo '</tr></thead><tbody>';
	}

	/**
	 * Cellule numérique échappée.
	 *
	 * @param string $value Valeur déjà formatée.
	 * @return string
	 */
	private static function num( string $value ): string {
		return '<td class="num">' . esc_html( $value ) . '</td>';
	}

	/**
	 * Entonnoir par source et campagne.
	 *
	 * @param array<int, array<string, mixed>> $rows Lignes de Visitor_Insights::sources().
	 * @return void
	 */
	public static function sources( array $rows ): void {
		echo '<h2>' . esc_html__( 'Sources et campagnes', 'famma-core' ) . '</h2>';
		if ( array() === $rows ) {
			echo '<p>' . esc_html__( 'Aucune session sur la période.', 'famma-core' ) . '</p>';
			return;
		}
		self::head(
			array(
				__( 'Source', 'famma-core' ),
				__( 'Support', 'famma-core' ),
				__( 'Campagne', 'famma-core' ),
				__( 'Annonce (utm_content)', 'famma-core' ),
				__( 'Sessions#', 'famma-core' ),
				__( 'Fiche produit#', 'famma-core' ),
				__( 'Panier#', 'famma-core' ),
				__( 'Formulaire#', 'famma-core' ),
				__( 'Commandes#', 'famma-core' ),
				__( 'Conversion#', 'famma-core' ),
				__( 'CA commandé (DT)#', 'famma-core' ),
			)
		);
		foreach ( $rows as $row ) {
			$sessions = (int) $row['sessions'];
			echo '<tr><td>' . esc_html( '' !== (string) $row['source'] ? (string) $row['source'] : __( '(inconnue)', 'famma-core' ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['medium'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['campaign'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['content'] ) . '</td>';
			echo self::num( number_format_i18n( $sessions ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			foreach ( array( 'product', 'cart', 'form', 'orders' ) as $col ) {
				echo self::num( number_format_i18n( (int) $row[ $col ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			}
			echo self::num( self::pct( (int) $row['ordered'], $sessions ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo self::num( number_format_i18n( (int) $row['revenue'] ) ) . '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'CA commandé : total des commandes passées, pas encore encaissé (paiement à la livraison). Une session prend la source de son arrivée.', 'famma-core' ) . '</p>';
	}

	/**
	 * Intérêt par produit, puis variations choisies.
	 *
	 * @param array<int, array<string, mixed>> $rows       Lignes de Visitor_Insights::products().
	 * @param array<int, array<string, mixed>> $variations Lignes de Visitor_Insights::variations().
	 * @return void
	 */
	public static function products( array $rows, array $variations ): void {
		echo '<h2>' . esc_html__( 'Intérêt par produit', 'famma-core' ) . '</h2>';
		if ( array() === $rows ) {
			echo '<p>' . esc_html__( 'Aucune fiche produit vue sur la période.', 'famma-core' ) . '</p>';
			return;
		}
		self::head(
			array(
				__( 'Produit', 'famma-core' ),
				__( 'Sessions#', 'famma-core' ),
				__( 'Temps actif moyen#', 'famma-core' ),
				__( 'Défilement moyen#', 'famma-core' ),
				__( 'Galerie#', 'famma-core' ),
				__( 'Vidéo#', 'famma-core' ),
				__( 'Panier#', 'famma-core' ),
				__( 'Formulaire#', 'famma-core' ),
				__( 'Vu puis commandé#', 'famma-core' ),
			)
		);
		foreach ( $rows as $row ) {
			$views = (int) $row['views'];
			$id    = (int) $row['product_id'];
			$title = get_the_title( $id );
			$link  = get_edit_post_link( $id );
			echo '<tr><td>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( '' !== $title ? $title : '#' . $id ) ) . '</td>';
			echo self::num( number_format_i18n( $views ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			/* translators: %s: seconds. */
			echo self::num( null === $row['avg_time'] ? '—' : sprintf( __( '%s s', 'famma-core' ), number_format_i18n( (float) $row['avg_time'], 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo self::num( null === $row['avg_scroll'] ? '—' : number_format_i18n( (float) $row['avg_scroll'], 0 ) . ' %' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			foreach ( array( 'gallery', 'video', 'carts', 'forms', 'ordered' ) as $col ) {
				echo self::num( number_format_i18n( (int) $row[ $col ] ) . ' (' . self::pct( (int) $row[ $col ], $views ) . ')' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			}
			echo '</tr>';
		}
		echo '</tbody></table>';

		if ( array() === $variations ) {
			return;
		}
		echo '<h3>' . esc_html__( 'Variations choisies', 'famma-core' ) . '</h3>';
		self::head( array( __( 'Produit', 'famma-core' ), __( 'Variation', 'famma-core' ), __( 'Sessions#', 'famma-core' ) ) );
		foreach ( $variations as $row ) {
			echo '<tr><td>' . esc_html( get_the_title( (int) $row['product_id'] ) ) . '</td><td>' . esc_html( urldecode( (string) $row['label'] ) ) . '</td>';
			echo self::num( number_format_i18n( (int) $row['sessions'] ) ) . '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		}
		echo '</tbody></table>';
	}
}
