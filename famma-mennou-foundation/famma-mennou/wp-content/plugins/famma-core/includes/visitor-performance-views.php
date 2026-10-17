<?php
/**
 * Parcours visiteurs — gabarit de l'onglet Performance.
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
	 * Libellés des types de page.
	 *
	 * @return array<string, string>
	 */
	private static function page_labels(): array {
		return array(
			'home'     => __( 'Accueil', 'famma-core' ),
			'shop'     => __( 'Boutique', 'famma-core' ),
			'product'  => __( 'Fiche produit', 'famma-core' ),
			'cart'     => __( 'Panier', 'famma-core' ),
			'checkout' => __( 'Checkout', 'famma-core' ),
			'thankyou' => __( 'Remerciement', 'famma-core' ),
			'search'   => __( 'Recherche', 'famma-core' ),
			'page'     => __( 'Page', 'famma-core' ),
			'other'    => __( 'Autre', 'famma-core' ),
		);
	}

	/**
	 * Cellule d'une mesure, avec sa note (bon / à améliorer / mauvais).
	 *
	 * @param string     $metric Mesure (lcp, inp, cls, ttfb).
	 * @param float|null $value  Valeur au 75e centile.
	 * @return string HTML échappé.
	 */
	private static function cell( string $metric, ?float $value ): string {
		if ( null === $value ) {
			return '<td class="num">—</td>';
		}
		$limits = Visitor_Performance::THRESHOLDS[ $metric ];
		$rating = $value <= $limits[0] ? 'good' : ( $value <= $limits[1] ? 'warn' : 'poor' );
		$text   = 'inp' === $metric && $value < 40 ? __( '< 40 ms', 'famma-core' ) : null;
		$text ??= 'cls' === $metric
			? number_format_i18n( $value, 3 )
			: ( $value >= 1000 ? number_format_i18n( $value / 1000, 2 ) . ' s' : number_format_i18n( $value ) . ' ms' );
		return '<td class="num famma-vj__rate famma-vj__rate--' . esc_attr( $rating ) . '">' . esc_html( $text ) . '</td>';
	}

	/**
	 * En-tête commun des tableaux de mesures.
	 *
	 * @param string $first Libellé de la première colonne.
	 * @return void
	 */
	private static function head( string $first ): void {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html( $first ) . '</th>';
		foreach ( array( __( 'Mesures', 'famma-core' ), 'LCP', 'INP', 'CLS', 'TTFB' ) as $col ) {
			echo '<th class="num">' . esc_html( $col ) . '</th>';
		}
		echo '</tr></thead><tbody>';
	}

	/**
	 * Ligne de mesures.
	 *
	 * @param string               $label Libellé.
	 * @param array<string, mixed> $row   Centiles et nombre de mesures.
	 * @return void
	 */
	private static function row( string $label, array $row ): void {
		echo '<tr><td>' . esc_html( $label ) . '</td><td class="num">' . esc_html( number_format_i18n( (int) $row['samples'] ) ) . '</td>';
		foreach ( array_keys( Visitor_Performance::THRESHOLDS ) as $metric ) {
			echo self::cell( $metric, $row[ $metric ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
		}
		echo '</tr>';
	}

	/**
	 * Onglet complet.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $vitals Résultat de Visitor_Performance::vitals().
	 * @param array<int, array<string, mixed>>                   $errors Résultat de Visitor_Performance::errors().
	 * @return void
	 */
	public static function render( array $vitals, array $errors ): void {
		$devices = array(
			'all'     => __( 'tous appareils', 'famma-core' ),
			'mobile'  => __( 'mobile', 'famma-core' ),
			'desktop' => __( 'ordinateur', 'famma-core' ),
			'tablet'  => __( 'tablette', 'famma-core' ),
		);
		echo '<h2>' . esc_html__( 'Temps de chargement réels (75e centile)', 'famma-core' ) . '</h2>';
		if ( array() === $vitals['pages'] ) {
			echo '<p>' . esc_html__( 'Aucune mesure sur la période.', 'famma-core' ) . '</p>';
		} else {
			self::head( __( 'Type de page', 'famma-core' ) );
			$labels = self::page_labels();
			ksort( $vitals['pages'] );
			foreach ( $vitals['pages'] as $group => $row ) {
				list( $type, $device ) = explode( '|', $group ) + array( '', 'all' );
				self::row( ( $labels[ $type ] ?? $type ) . ' — ' . ( $devices[ $device ] ?? $device ), $row );
			}
			echo '</tbody></table>';
			echo '<p class="description">' . esc_html__( 'Vert : bon. Orange : à améliorer. Rouge : mauvais (seuils Google). LCP : affichage du contenu principal ; INP : réactivité au clic ; CLS : stabilité visuelle ; TTFB : réponse du serveur.', 'famma-core' ) . '</p>';
		}

		if ( array() !== $vitals['paths'] ) {
			echo '<h3>' . esc_html__( 'Pages les plus lentes (LCP)', 'famma-core' ) . '</h3>';
			self::head( __( 'Chemin', 'famma-core' ) );
			foreach ( $vitals['paths'] as $path => $row ) {
				self::row( (string) $path, $row );
			}
			echo '</tbody></table>';
		}

		echo '<h2>' . esc_html__( 'Erreurs JavaScript', 'famma-core' ) . '</h2>';
		if ( array() === $errors ) {
			echo '<p>' . esc_html__( 'Aucune erreur remontée sur la période.', 'famma-core' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Erreur', 'famma-core' ), __( 'Ligne', 'famma-core' ), __( 'Sessions', 'famma-core' ), __( 'Occurrences', 'famma-core' ), __( 'Pages', 'famma-core' ), __( 'Exemple de page', 'famma-core' ), __( 'Dernière fois', 'famma-core' ) ) as $col ) {
			echo '<th>' . esc_html( $col ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $errors as $row ) {
			echo '<tr><td><code>' . esc_html( (string) $row['label'] ) . '</code></td>';
			echo '<td class="num">' . esc_html( (string) $row['line'] ) . '</td>';
			echo '<td class="num">' . esc_html( number_format_i18n( (int) $row['sessions'] ) ) . '</td>';
			echo '<td class="num">' . esc_html( number_format_i18n( (int) $row['hits'] ) ) . '</td>';
			echo '<td class="num">' . esc_html( number_format_i18n( (int) $row['pages'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['sample_path'] ) . '</td>';
			echo '<td>' . esc_html( wp_date( 'd/m H:i', strtotime( $row['last_seen'] . ' UTC' ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
