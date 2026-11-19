<?php
/**
 * Parcours visiteurs — onglet « Sessions ».
 *
 * Barre de filtres (type, appareil, source, adresse IP), puis la liste
 * paginée. Filtrer sur une adresse IP affiche d'abord ce que cette adresse a
 * fait sur toute la durée conservée : plusieurs visiteurs ou commandes
 * derrière une même adresse se voient d'un coup d'œil.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu de la liste des sessions.
 */
final class Visitor_Sessions_Views {

	/**
	 * Affiche l'onglet.
	 *
	 * @param Visitor_Report        $report  Lectures.
	 * @param int                   $days    Période.
	 * @param int                   $paged   Page courante.
	 * @param array<string, string> $filters Filtres nettoyés.
	 * @return void
	 */
	public static function render( Visitor_Report $report, int $days, int $paged, array $filters ): void {
		self::filters( $report->sources( $days ), $days, $filters );
		if ( '' !== $filters['ip'] ) {
			self::ip_summary( $filters['ip'], $report->ip_history( $filters['ip'] ) );
		}
		$data = $report->sessions( $days, $paged, $filters );
		/* translators: %s: number of sessions. */
		Visitor_Ui::card_open( sprintf( _n( '%s session', '%s sessions', $data['total'], 'famma-core' ), Visitor_Ui::number( $data['total'] ) ) );
		if ( array() === $data['rows'] ) {
			Visitor_Ui::empty_state( __( 'Aucune session ne correspond à ces critères sur la période.', 'famma-core' ) );
			Visitor_Ui::card_close();
			return;
		}
		self::table( $data['rows'], $days );
		self::pager( $data['total'], $paged, $days, $filters );
		Visitor_Ui::card_close();
	}

	/**
	 * Barre de filtres, envoyée en GET (lecture seule, pas de nonce).
	 *
	 * @param array<int, string>    $sources Sources de la période.
	 * @param int                   $days    Période.
	 * @param array<string, string> $f       Filtres courants.
	 * @return void
	 */
	private static function filters( array $sources, int $days, array $f ): void {
		echo '<form method="get" class="famma-vj__filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr( Visitor_Admin::SLUG ) . '">';
		echo '<input type="hidden" name="tab" value="sessions">';
		echo '<input type="hidden" name="days" value="' . esc_attr( (string) $days ) . '">';

		$kinds = array( '' => __( 'Tous les types', 'famma-core' ) );
		foreach ( Visitor_Session_Type::labels() as $kind => $label ) {
			$kinds[ $kind ] = $label[0];
		}
		self::select( 'kind', __( 'Type', 'famma-core' ), $kinds, $f['kind'] );
		self::select( 'device', __( 'Appareil', 'famma-core' ), array( '' => __( 'Tous', 'famma-core' ) ) + Visitor_Labels::devices(), $f['device'] );
		self::select( 'source', __( 'Source', 'famma-core' ), array( '' => __( 'Toutes', 'famma-core' ) ) + array_combine( $sources, $sources ), $f['source'] );

		echo '<label class="famma-vj__field"><span>' . esc_html__( 'Adresse IP', 'famma-core' ) . '</span>';
		echo '<input type="search" name="ip" value="' . esc_attr( $f['ip'] ) . '" placeholder="197.0.0.1" inputmode="decimal" autocomplete="off"></label>';
		echo '<div class="famma-vj__filter-actions"><button type="submit" class="button button-primary">' . esc_html__( 'Filtrer', 'famma-core' ) . '</button>';
		if ( array_filter( $f ) ) {
			echo '<a class="button" href="' . esc_url(
				Visitor_Admin::instance()->url(
					array(
						'tab'  => 'sessions',
						'days' => $days,
					)
				)
			) . '">' . esc_html__( 'Effacer', 'famma-core' ) . '</a>';
		}
		echo '</div></form>';
	}

	/**
	 * Liste déroulante d'un filtre.
	 *
	 * @param string                $name    Nom du paramètre.
	 * @param string                $label   Libellé.
	 * @param array<string, string> $options Valeur => libellé.
	 * @param string                $current Valeur choisie.
	 * @return void
	 */
	private static function select( string $name, string $label, array $options, string $current ): void {
		echo '<label class="famma-vj__field"><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $text ) {
			echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $value, $current, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select></label>';
	}

	/**
	 * Ce qu'a fait une adresse IP sur toute la durée conservée.
	 *
	 * @param string                                           $ip      Adresse.
	 * @param array{visitors: int, sessions: int, orders: int} $history Comptes.
	 * @return void
	 */
	private static function ip_summary( string $ip, array $history ): void {
		$shared = $history['visitors'] > 1 || $history['orders'] > 1;
		echo '<div class="famma-vj__callout famma-vj__callout--' . ( $shared ? 'warn' : 'info' ) . '"><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span><p>';
		echo esc_html(
			sprintf(
				/* translators: 1: IP address, 2: visitors, 3: sessions, 4: orders. */
				__( 'Adresse %1$s, sur toute la durée conservée : %2$s visiteur(s), %3$s session(s), %4$s commande(s).', 'famma-core' ),
				$ip,
				Visitor_Ui::number( $history['visitors'] ),
				Visitor_Ui::number( $history['sessions'] ),
				Visitor_Ui::number( $history['orders'] )
			)
		);
		if ( $shared ) {
			echo ' <strong>' . esc_html__( 'Plusieurs visiteurs ou commandes : réseau partagé (4G, entreprise) ou commandes à vérifier.', 'famma-core' ) . '</strong>';
		}
		echo '</p></div>';
	}

	/**
	 * Tableau des sessions.
	 *
	 * @param array<int, array<string, string>> $rows Sessions.
	 * @param int                               $days Période.
	 * @return void
	 */
	private static function table( array $rows, int $days ): void {
		Visitor_Ui::table_open(
			array(
				__( 'Début', 'famma-core' ),
				__( 'Durée', 'famma-core' ),
				__( 'Type', 'famma-core' ),
				__( 'Appareil', 'famma-core' ),
				__( 'Source', 'famma-core' ),
				__( 'Adresse IP', 'famma-core' ),
				__( 'Pages#', 'famma-core' ),
				__( 'Panier#', 'famma-core' ),
				__( 'Commande', 'famma-core' ),
				'',
			)
		);
		foreach ( $rows as $row ) {
			$start  = strtotime( $row['started'] . ' UTC' );
			$source = trim( $row['source'] . ( '' !== $row['campaign'] ? ' / ' . $row['campaign'] : '' ) );
			$url    = Visitor_Admin::instance()->url(
				array(
					'days'    => $days,
					'session' => $row['session_id'],
				)
			);
			echo '<tr>' . Visitor_Ui::cell( Visitor_Ui::date( $row['started'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
			echo Visitor_Ui::cell( Visitor_Ui::duration( max( 0, (int) strtotime( $row['ended'] . ' UTC' ) - (int) $start ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
			echo '<td>' . Visitor_Session_Type::badge( (string) ( $row['kind'] ?? '' ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in badge().
			echo Visitor_Ui::cell( Visitor_Labels::device( (string) $row['device'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
			echo Visitor_Ui::cell( '' !== $source ? $source : 'direct' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
			echo '<td>' . Visitor_Ui::ip_link( (string) $row['ip'], $days ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in ip_link().
			echo Visitor_Ui::num( Visitor_Ui::number( (int) $row['pages'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo Visitor_Ui::num( Visitor_Ui::number( (int) $row['carts'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in num().
			echo '<td>' . Visitor_Ui::order_link( (int) $row['order_id'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in order_link().
			echo '<td class="famma-vj__row-action"><a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Parcours', 'famma-core' ) . '</a></td></tr>';
		}
		Visitor_Ui::table_close();
	}

	/**
	 * Pagination, filtres conservés.
	 *
	 * @param int                   $total   Sessions trouvées.
	 * @param int                   $paged   Page courante.
	 * @param int                   $days    Période.
	 * @param array<string, string> $filters Filtres.
	 * @return void
	 */
	private static function pager( int $total, int $paged, int $days, array $filters ): void {
		$pages = (int) ceil( $total / Visitor_Report::PER_PAGE );
		if ( $pages <= 1 ) {
			return;
		}
		$args = array_filter( $filters ) + array(
			'tab'  => 'sessions',
			'days' => $days,
		);
		echo '<nav class="famma-vj__pager">';
		if ( $paged > 1 ) {
			echo '<a class="button" href="' . esc_url( Visitor_Admin::instance()->url( $args + array( 'paged' => $paged - 1 ) ) ) . '">' . esc_html__( '← Plus récentes', 'famma-core' ) . '</a>';
		}
		/* translators: 1: current page, 2: number of pages. */
		echo '<span class="famma-vj__muted">' . esc_html( sprintf( __( 'Page %1$d sur %2$d', 'famma-core' ), $paged, $pages ) ) . '</span>';
		if ( $paged < $pages ) {
			echo '<a class="button" href="' . esc_url( Visitor_Admin::instance()->url( $args + array( 'paged' => $paged + 1 ) ) ) . '">' . esc_html__( 'Plus anciennes →', 'famma-core' ) . '</a>';
		}
		echo '</nav>';
	}
}
