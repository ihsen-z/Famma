<?php
/**
 * Parcours visiteurs — onglet « Formulaires ».
 *
 * Trois blocs : le formulaire express par fiche produit (de commencé à
 * commandé), les envois refusés par cause, puis les champs touchés de chaque
 * formulaire — c'est là qu'on voit où les visiteurs s'arrêtent.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu de l'onglet Formulaires.
 */
final class Visitor_Form_Funnel_Views {

	/**
	 * Affiche l'onglet.
	 *
	 * @param array<string, mixed>                $express Entonnoir express (`Visitor_Form_Funnel::express()`).
	 * @param array<string, array<string, mixed>> $fields  Champs par formulaire (`Visitor_Report::form_fields()`).
	 * @return void
	 */
	public static function render( array $express, array $fields ): void {
		self::express( $express );
		echo '<div class="famma-vj__grid famma-vj__grid--2">';
		self::causes( (array) ( $express['causes'] ?? array() ) );
		self::fields( $fields );
		echo '</div>';
	}

	/**
	 * Formulaire express, par produit.
	 *
	 * @param array<string, mixed> $data Entonnoir.
	 * @return void
	 */
	private static function express( array $data ): void {
		$products = (array) ( $data['products'] ?? array() );
		Visitor_Ui::card_open( __( 'Formulaire express : de commencé à commandé', 'famma-core' ), __( 'Sessions par fiche produit. Une commande compte quand la même session a commencé le formulaire.', 'famma-core' ) );
		if ( array() === $products ) {
			Visitor_Ui::empty_state( __( 'Aucun formulaire express commencé sur la période.', 'famma-core' ) );
			Visitor_Ui::card_close();
			return;
		}
		if ( ! empty( $data['truncated'] ) ) {
			echo '<p class="famma-vj__note">' . esc_html__( 'Période trop chargée : seuls les premiers événements sont comptés. Choisissez une période plus courte.', 'famma-core' ) . '</p>';
		}
		Visitor_Ui::table_open(
			array(
				__( 'Produit', 'famma-core' ),
				__( 'Commencés#', 'famma-core' ),
				__( 'Envoyés#', 'famma-core' ),
				__( 'Commandés#', 'famma-core' ),
				__( 'Taux de commande', 'famma-core' ),
				__( 'Sessions bloquées#', 'famma-core' ),
				__( 'Envois refusés#', 'famma-core' ),
				__( 'Bloquées puis commandées#', 'famma-core' ),
			)
		);
		foreach ( $products as $product_id => $row ) {
			self::row( Visitor_Ui::product_link( (int) $product_id ), (array) $row, 'td' );
		}
		self::row_foot( (array) ( $data['total'] ?? array() ) );
	}

	/**
	 * Ligne de l'entonnoir express.
	 *
	 * @param string             $label Libellé déjà échappé.
	 * @param array<string, int> $row   Comptes.
	 * @param string             $tag   `td` ou `th`.
	 * @return void
	 */
	private static function row( string $label, array $row, string $tag ): void {
		echo self::cells( $label, $row, $tag ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cells().
	}

	/**
	 * Pied du tableau express (total), puis fermeture de la carte.
	 *
	 * @param array<string, int> $total Comptes de tous les produits.
	 * @return void
	 */
	private static function row_foot( array $total ): void {
		Visitor_Ui::table_close( self::cells( esc_html__( 'Tous les produits', 'famma-core' ), $total, 'th' ) );
		Visitor_Ui::card_close();
	}

	/**
	 * Cellules d'une ligne, échappées.
	 *
	 * @param string             $label Libellé déjà échappé.
	 * @param array<string, int> $row   Comptes.
	 * @param string             $tag   `td` ou `th`.
	 * @return string
	 */
	private static function cells( string $label, array $row, string $tag ): string {
		$tag     = 'th' === $tag ? 'th' : 'td';
		$started = (int) ( $row['started'] ?? 0 );
		$ordered = (int) ( $row['ordered'] ?? 0 );
		$html    = '<tr><' . $tag . '>' . $label . '</' . $tag . '>';
		$html   .= Visitor_Ui::num( Visitor_Ui::number( $started ), $tag );
		$html   .= Visitor_Ui::num( Visitor_Ui::number( (int) ( $row['submitted'] ?? 0 ) ), $tag );
		$html   .= Visitor_Ui::num( Visitor_Ui::number( $ordered ), $tag );
		$html   .= '<' . $tag . ' class="famma-vj__rate-cell">' . Visitor_Ui::bar( $ordered, $started, 'good' ) . '<span>' . esc_html( Visitor_Ui::pct( $ordered, $started ) ) . '</span></' . $tag . '>';
		$html   .= Visitor_Ui::num( Visitor_Ui::number( (int) ( $row['blocked'] ?? 0 ) ), $tag );
		$html   .= Visitor_Ui::num( Visitor_Ui::number( (int) ( $row['refusals'] ?? 0 ) ), $tag );
		$html   .= Visitor_Ui::num( Visitor_Ui::number( (int) ( $row['recovered'] ?? 0 ) ), $tag );
		return $html . '</tr>';
	}

	/**
	 * Envois refusés par cause, du plus fréquent au plus rare.
	 *
	 * @param array<string, array<string, int>> $causes Comptes par code.
	 * @return void
	 */
	private static function causes( array $causes ): void {
		Visitor_Ui::card_open( __( 'Pourquoi des envois sont refusés', 'famma-core' ), __( 'Chaque refus est compté une fois par cause. « Sans commande » : sessions qui n\'ont pas commandé ensuite.', 'famma-core' ) );
		if ( array() === $causes ) {
			Visitor_Ui::empty_state( __( 'Aucun envoi refusé sur la période.', 'famma-core' ) );
			Visitor_Ui::card_close();
			return;
		}
		$max = max( array_map( static fn( array $c ): int => (int) ( $c['refusals'] ?? 0 ), $causes ) );
		Visitor_Ui::table_open(
			array(
				__( 'Cause', 'famma-core' ),
				__( 'Refus', 'famma-core' ),
				__( 'Sessions#', 'famma-core' ),
				__( 'Sans commande#', 'famma-core' ),
			)
		);
		foreach ( $causes as $code => $cause ) {
			$refusals = (int) ( $cause['refusals'] ?? 0 );
			echo '<tr>' . Visitor_Ui::cell( Visitor_Labels::errors( (string) $code ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
			echo '<td class="famma-vj__rate-cell">' . Visitor_Ui::bar( $refusals, $max, 'bad' ) . '<span>' . esc_html( Visitor_Ui::number( $refusals ) ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in bar().
			echo Visitor_Ui::num( Visitor_Ui::number( (int) ( $cause['sessions'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo Visitor_Ui::num( Visitor_Ui::number( (int) ( $cause['lost'] ?? 0 ) ) ) . '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
		}
		Visitor_Ui::table_close();
		Visitor_Ui::card_close();
	}

	/**
	 * Champs touchés, par formulaire : où les visiteurs s'arrêtent.
	 *
	 * @param array<string, array<string, mixed>> $forms Données par formulaire.
	 * @return void
	 */
	private static function fields( array $forms ): void {
		$titles = array(
			'express'  => __( 'Formulaire express (fiche produit)', 'famma-core' ),
			'checkout' => __( 'Page commande', 'famma-core' ),
		);
		Visitor_Ui::card_open( __( 'Champs remplis', 'famma-core' ), __( 'Part des formulaires commencés où chaque champ a été touché. Un champ peu rempli est un champ où l\'on décroche.', 'famma-core' ) );
		if ( array() === $forms ) {
			Visitor_Ui::empty_state( __( 'Aucune interaction avec un formulaire sur la période.', 'famma-core' ) );
			Visitor_Ui::card_close();
			return;
		}
		foreach ( $titles as $form => $title ) {
			if ( empty( $forms[ $form ] ) ) {
				continue;
			}
			$started   = (int) $forms[ $form ]['started'];
			$submitted = (int) $forms[ $form ]['submitted'];
			/* translators: 1: submitted forms, 2: started forms. */
			$ratio = sprintf( __( '%1$s envoyés / %2$s commencés', 'famma-core' ), Visitor_Ui::number( $submitted ), Visitor_Ui::number( $started ) );
			echo '<h3 class="famma-vj__subhead">' . esc_html( $title ) . ' ' . Visitor_Ui::badge( $ratio, 'info' ) . '</h3>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in badge().
			echo '<ul class="famma-vj__bars">';
			foreach ( (array) $forms[ $form ]['fields'] as $name => $count ) {
				echo '<li><span class="famma-vj__bars-label">' . esc_html( Visitor_Labels::field( (string) $name ) ) . '</span>' . Visitor_Ui::bar( (int) $count, $started ) . '<span class="famma-vj__bars-value">' . esc_html( Visitor_Ui::pct( (int) $count, $started ) ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in bar().
			}
			echo '</ul>';
		}
		Visitor_Ui::card_close();
	}
}
