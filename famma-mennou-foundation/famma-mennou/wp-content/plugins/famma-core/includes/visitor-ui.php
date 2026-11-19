<?php
/**
 * Parcours visiteurs — briques d'affichage communes.
 *
 * Cartes, tuiles d'indicateurs, tableaux, pastilles, barres et liens : tous
 * les onglets les composent au lieu de recopier leur propre balisage. Chaque
 * méthode qui renvoie du HTML l'échappe elle-même ; celles qui l'impriment
 * aussi. Les classes CSS vivent dans `assets/css/visitor-admin.css`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu des éléments partagés de l'écran « Parcours visiteurs ».
 */
final class Visitor_Ui {

	/**
	 * Pourcentage arrondi, sans division par zéro.
	 *
	 * @param int $part  Partie.
	 * @param int $whole Total.
	 * @return string
	 */
	public static function pct( int $part, int $whole ): string {
		return $whole > 0 ? number_format_i18n( 100 * $part / $whole, 1 ) . ' %' : '—';
	}

	/**
	 * Nombre formaté selon la langue.
	 *
	 * @param int|float|string $value Valeur.
	 * @return string
	 */
	public static function number( int|float|string $value ): string {
		return number_format_i18n( (float) $value );
	}

	/**
	 * Ouvre une carte, avec titre et phrase d'explication facultative.
	 *
	 * @param string $title Titre.
	 * @param string $hint  Explication sous le titre.
	 * @param string $extra Classe supplémentaire.
	 * @return void
	 */
	public static function card_open( string $title, string $hint = '', string $extra = '' ): void {
		echo '<section class="famma-vj__card ' . esc_attr( $extra ) . '">';
		if ( '' !== $title ) {
			echo '<header class="famma-vj__card-head"><h2>' . esc_html( $title ) . '</h2>';
			if ( '' !== $hint ) {
				echo '<p>' . esc_html( $hint ) . '</p>';
			}
			echo '</header>';
		}
	}

	/**
	 * Ferme une carte.
	 *
	 * @return void
	 */
	public static function card_close(): void {
		echo '</section>';
	}

	/**
	 * Message « rien à montrer ».
	 *
	 * @param string $message Phrase.
	 * @return void
	 */
	public static function empty_state( string $message ): void {
		echo '<p class="famma-vj__empty"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span>' . esc_html( $message ) . '</p>';
	}

	/**
	 * Rangée de tuiles d'indicateurs.
	 *
	 * @param array<int, array{label: string, value: string, hint?: string, icon?: string, tone?: string}> $tiles Tuiles.
	 * @return void
	 */
	public static function kpis( array $tiles ): void {
		echo '<div class="famma-vj__kpis">';
		foreach ( $tiles as $tile ) {
			$tone = $tile['tone'] ?? 'brand';
			echo '<div class="famma-vj__kpi famma-vj__kpi--' . esc_attr( $tone ) . '">';
			if ( ! empty( $tile['icon'] ) ) {
				echo '<span class="famma-vj__kpi-icon dashicons dashicons-' . esc_attr( $tile['icon'] ) . '" aria-hidden="true"></span>';
			}
			echo '<span class="famma-vj__kpi-label">' . esc_html( $tile['label'] ) . '</span>';
			echo '<strong class="famma-vj__kpi-value">' . esc_html( $tile['value'] ) . '</strong>';
			if ( ! empty( $tile['hint'] ) ) {
				echo '<span class="famma-vj__kpi-hint">' . esc_html( $tile['hint'] ) . '</span>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Ouvre un tableau ; un en-tête terminé par « # » est une colonne de nombres.
	 *
	 * @param array<int, string> $heads En-têtes.
	 * @return void
	 */
	public static function table_open( array $heads ): void {
		echo '<div class="famma-vj__table-wrap"><table class="famma-vj__table"><thead><tr>';
		foreach ( $heads as $head ) {
			$num = str_ends_with( $head, '#' );
			echo '<th scope="col"' . ( $num ? ' class="num"' : '' ) . '>' . esc_html( rtrim( $head, '#' ) ) . '</th>';
		}
		echo '</tr></thead><tbody>';
	}

	/**
	 * Ferme un tableau, avec un pied facultatif déjà échappé.
	 *
	 * @param string $foot Lignes du pied (HTML échappé).
	 * @return void
	 */
	public static function table_close( string $foot = '' ): void {
		echo '</tbody>';
		if ( '' !== $foot ) {
			echo '<tfoot>' . $foot . '</tfoot>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows built from escaped cells.
		}
		echo '</table></div>';
	}

	/**
	 * Cellule numérique échappée.
	 *
	 * @param string $value Valeur affichée.
	 * @param string $tag   `td` ou `th`.
	 * @return string
	 */
	public static function num( string $value, string $tag = 'td' ): string {
		$tag = 'th' === $tag ? 'th' : 'td';
		return '<' . $tag . ' class="num">' . esc_html( $value ) . '</' . $tag . '>';
	}

	/**
	 * Cellule de texte échappée.
	 *
	 * @param string $value Valeur affichée.
	 * @param string $tag   `td` ou `th`.
	 * @return string
	 */
	public static function cell( string $value, string $tag = 'td' ): string {
		$tag = 'th' === $tag ? 'th' : 'td';
		return '<' . $tag . '>' . esc_html( $value ) . '</' . $tag . '>';
	}

	/**
	 * Pastille colorée.
	 *
	 * @param string $text  Texte.
	 * @param string $tone  Famille de couleur (classe CSS).
	 * @param string $title Infobulle.
	 * @return string
	 */
	public static function badge( string $text, string $tone = 'neutral', string $title = '' ): string {
		return '<span class="famma-vj__badge famma-vj__badge--' . esc_attr( $tone ) . '"' . ( '' !== $title ? ' title="' . esc_attr( $title ) . '"' : '' ) . '>' . esc_html( $text ) . '</span>';
	}

	/**
	 * Barre horizontale proportionnelle.
	 *
	 * @param int    $part  Valeur.
	 * @param int    $whole Valeur à 100 %.
	 * @param string $tone  Famille de couleur.
	 * @return string
	 */
	public static function bar( int $part, int $whole, string $tone = 'brand' ): string {
		$width = $whole > 0 ? max( $part > 0 ? 1 : 0, (int) round( 100 * $part / $whole ) ) : 0;
		return '<span class="famma-vj__bar"><span class="famma-vj__bar-fill famma-vj__bar-fill--' . esc_attr( $tone ) . '" style="inline-size:' . esc_attr( (string) min( 100, $width ) ) . '%"></span></span>';
	}

	/**
	 * Date locale d'une valeur UTC de la table.
	 *
	 * @param string $utc    Date `Y-m-d H:i:s` en UTC.
	 * @param string $format Format de `wp_date()`.
	 * @return string
	 */
	public static function date( string $utc, string $format = 'd/m H:i' ): string {
		$time = strtotime( $utc . ' UTC' );
		return false === $time ? '' : (string) wp_date( $format, $time );
	}

	/**
	 * Durée lisible ; « moins d'une seconde » pour zéro.
	 *
	 * `human_time_diff( 0, 0 )` compterait depuis 1970 : une session d'un seul
	 * instant affichait « 57 ans ».
	 *
	 * @param int $seconds Durée.
	 * @return string
	 */
	public static function duration( int $seconds ): string {
		return $seconds > 0 ? human_time_diff( 0, $seconds ) : __( 'moins d\'une seconde', 'famma-core' );
	}

	/**
	 * Nom d'un produit, avec lien vers sa fiche d'édition.
	 *
	 * @param int $product_id Identifiant.
	 * @return string HTML échappé.
	 */
	public static function product_link( int $product_id ): string {
		$title = get_the_title( $product_id );
		/* translators: %d: product ID. */
		$title = '' !== $title ? $title : sprintf( __( 'Produit n° %d', 'famma-core' ), $product_id );
		$link  = get_edit_post_link( $product_id );
		return $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title );
	}

	/**
	 * Lien vers une commande, ou chaîne vide.
	 *
	 * @param int $order_id Identifiant de commande.
	 * @return string HTML échappé.
	 */
	public static function order_link( int $order_id ): string {
		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof \WC_Order ) {
			return '';
		}
		return '<a class="famma-vj__order" href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
	}

	/**
	 * Adresse IP cliquable : ouvre la liste des sessions de cette adresse.
	 *
	 * @param string $ip   Adresse en clair, ou vide.
	 * @param int    $days Période courante.
	 * @return string HTML échappé.
	 */
	public static function ip_link( string $ip, int $days ): string {
		if ( '' === $ip ) {
			return '<span class="famma-vj__muted" title="' . esc_attr__( 'IP non enregistrée : réglage désactivé, ou effacée après sa durée de conservation.', 'famma-core' ) . '">—</span>';
		}
		$url = Visitor_Admin::instance()->url(
			array(
				'tab'  => 'sessions',
				'days' => $days,
				'ip'   => $ip,
			)
		);
		return '<a class="famma-vj__ip" href="' . esc_url( $url ) . '" title="' . esc_attr__( 'Voir toutes les sessions de cette adresse', 'famma-core' ) . '">' . esc_html( $ip ) . '</a>';
	}
}
