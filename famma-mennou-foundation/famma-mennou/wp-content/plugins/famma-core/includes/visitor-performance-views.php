<?php
/**
 * Parcours visiteurs — onglet « Performance ».
 *
 * Temps de chargement réels (75e centile, seuils Google) par type de page et
 * appareil, pages les plus lentes, puis erreurs JavaScript remontées par les
 * navigateurs des visiteurs.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Tableaux Web Vitals (75e centile) et erreurs JavaScript.
 */
final class Visitor_Performance_Views {

	/**
	 * Couleurs des notes : bon, à améliorer, mauvais.
	 */
	private const RATINGS = array(
		'good' => 'good',
		'warn' => 'warn',
		'poor' => 'bad',
	);

	/**
	 * Affiche l'onglet.
	 *
	 * @param array{pages: array<string, array<string, mixed>>, paths: array<string, array<string, mixed>>} $vitals Centiles.
	 * @param array<int, array<string, string>>                                                             $errors Erreurs.
	 * @return void
	 */
	public static function render( array $vitals, array $errors ): void {
		self::pages( (array) ( $vitals['pages'] ?? array() ) );
		if ( array() !== ( $vitals['paths'] ?? array() ) ) {
			Visitor_Ui::card_open( __( 'Pages les plus lentes', 'famma-core' ), __( 'Classées par temps d\'affichage du contenu principal (LCP), à partir de 3 mesures.', 'famma-core' ) );
			self::head( __( 'Chemin', 'famma-core' ) );
			foreach ( $vitals['paths'] as $path => $row ) {
				self::row( '<code>' . esc_html( (string) $path ) . '</code>', $row );
			}
			Visitor_Ui::table_close();
			Visitor_Ui::card_close();
		}
		self::errors( $errors );
	}

	/**
	 * Centiles par type de page et appareil.
	 *
	 * @param array<string, array<string, mixed>> $pages Centiles par « type|appareil ».
	 * @return void
	 */
	private static function pages( array $pages ): void {
		Visitor_Ui::card_open( __( 'Temps de chargement réels', 'famma-core' ), __( '75e centile des visites réelles, comme Google. LCP : affichage du contenu principal ; INP : réactivité au clic ; CLS : stabilité visuelle ; TTFB : réponse du serveur.', 'famma-core' ) );
		if ( array() === $pages ) {
			Visitor_Ui::empty_state( __( 'Aucune mesure sur la période.', 'famma-core' ) );
			Visitor_Ui::card_close();
			return;
		}
		echo '<p class="famma-vj__legend-inline">' . Visitor_Ui::badge( __( 'Bon', 'famma-core' ), 'good' ) . Visitor_Ui::badge( __( 'À améliorer', 'famma-core' ), 'warn' ) . Visitor_Ui::badge( __( 'Mauvais', 'famma-core' ), 'bad' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in badge().
		$labels  = Visitor_Labels::page_types();
		$devices = array( 'all' => __( 'tous appareils', 'famma-core' ) ) + array_map( 'strtolower', Visitor_Labels::devices() );
		self::head( __( 'Type de page', 'famma-core' ) );
		ksort( $pages );
		foreach ( $pages as $group => $row ) {
			list( $type, $device ) = explode( '|', (string) $group ) + array( '', 'all' );
			$label                 = '<strong>' . esc_html( $labels[ $type ] ?? $type ) . '</strong> <span class="famma-vj__muted">' . esc_html( $devices[ $device ] ?? $device ) . '</span>';
			self::row( $label, $row );
		}
		Visitor_Ui::table_close();
		Visitor_Ui::card_close();
	}

	/**
	 * En-tête commun des tableaux de mesures.
	 *
	 * @param string $first Titre de la première colonne.
	 * @return void
	 */
	private static function head( string $first ): void {
		Visitor_Ui::table_open( array( $first, __( 'Mesures#', 'famma-core' ), 'LCP#', 'INP#', 'CLS#', 'TTFB#' ) );
	}

	/**
	 * Ligne de mesures.
	 *
	 * @param string               $label Libellé déjà échappé.
	 * @param array<string, mixed> $row   Centiles.
	 * @return void
	 */
	private static function row( string $label, array $row ): void {
		echo '<tr><td>' . $label . '</td>' . Visitor_Ui::num( Visitor_Ui::number( (int) $row['samples'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- label escaped by the caller, num() escapes.
		foreach ( array_keys( Visitor_Performance::THRESHOLDS ) as $metric ) {
			echo self::cell( $metric, isset( $row[ $metric ] ) ? (float) $row[ $metric ] : null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
		}
		echo '</tr>';
	}

	/**
	 * Cellule d'une mesure, avec sa note (bon / à améliorer / mauvais).
	 *
	 * @param string     $metric Mesure.
	 * @param float|null $value  Valeur, ou null si absente.
	 * @return string
	 */
	private static function cell( string $metric, ?float $value ): string {
		if ( null === $value ) {
			return '<td class="num famma-vj__muted">—</td>';
		}
		$limits = Visitor_Performance::THRESHOLDS[ $metric ];
		$rating = $value <= $limits[0] ? 'good' : ( $value <= $limits[1] ? 'warn' : 'poor' );
		$text   = 'inp' === $metric && $value < 40 ? __( '< 40 ms', 'famma-core' ) : null;
		$text ??= 'cls' === $metric
			? number_format_i18n( $value, 3 )
			: ( $value >= 1000 ? number_format_i18n( $value / 1000, 2 ) . ' s' : number_format_i18n( $value ) . ' ms' );
		return '<td class="num">' . Visitor_Ui::badge( $text, self::RATINGS[ $rating ] ) . '</td>';
	}

	/**
	 * Erreurs JavaScript regroupées.
	 *
	 * @param array<int, array<string, string>> $errors Erreurs.
	 * @return void
	 */
	private static function errors( array $errors ): void {
		Visitor_Ui::card_open( __( 'Erreurs JavaScript', 'famma-core' ), __( 'Erreurs remontées par les navigateurs des visiteurs, regroupées par message.', 'famma-core' ) );
		if ( array() === $errors ) {
			Visitor_Ui::empty_state( __( 'Aucune erreur remontée sur la période.', 'famma-core' ) );
			Visitor_Ui::card_close();
			return;
		}
		Visitor_Ui::table_open(
			array(
				__( 'Erreur', 'famma-core' ),
				__( 'Ligne#', 'famma-core' ),
				__( 'Sessions#', 'famma-core' ),
				__( 'Occurrences#', 'famma-core' ),
				__( 'Pages#', 'famma-core' ),
				__( 'Exemple de page', 'famma-core' ),
				__( 'Dernière fois', 'famma-core' ),
			)
		);
		foreach ( $errors as $row ) {
			echo '<tr><td><code>' . esc_html( (string) $row['label'] ) . '</code></td>';
			echo Visitor_Ui::num( (string) $row['line'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo Visitor_Ui::num( Visitor_Ui::number( (int) $row['sessions'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo Visitor_Ui::num( Visitor_Ui::number( (int) $row['hits'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo Visitor_Ui::num( Visitor_Ui::number( (int) $row['pages'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo Visitor_Ui::cell( (string) $row['sample_path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
			echo Visitor_Ui::cell( Visitor_Ui::date( (string) $row['last_seen'] ) ) . '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
		}
		Visitor_Ui::table_close();
		Visitor_Ui::card_close();
	}
}
