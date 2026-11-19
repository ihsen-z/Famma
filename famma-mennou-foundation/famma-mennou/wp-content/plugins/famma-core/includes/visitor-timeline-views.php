<?php
/**
 * Parcours visiteurs — parcours d'une session.
 *
 * Fiche de la session (arrivée, durée, appareil, source, adresse IP, visites
 * du même visiteur, commande), saisies éventuelles, puis la chronologie des
 * événements, chacun avec son icône et sa couleur de famille.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu du parcours d'une session.
 */
final class Visitor_Timeline_Views {

	/**
	 * Affiche le parcours.
	 *
	 * @param string $session_id Identifiant de session (UUID vérifié).
	 * @param int    $days       Période d'où l'on vient (lien retour).
	 * @return void
	 */
	public static function render( string $session_id, int $days ): void {
		$report = new Visitor_Report();
		$rows   = $report->timeline( $session_id );
		$back   = Visitor_Admin::instance()->url(
			array(
				'tab'  => 'sessions',
				'days' => $days,
			)
		);
		echo '<header class="famma-vj__header"><div class="famma-vj__title">';
		echo '<a class="famma-vj__back" href="' . esc_url( $back ) . '"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>' . esc_html__( 'Sessions', 'famma-core' ) . '</a>';
		echo '<h1>' . esc_html__( 'Parcours d\'une session', 'famma-core' ) . '</h1></div></header>';
		echo '<div class="famma-vj__body">';
		if ( array() === $rows ) {
			Visitor_Ui::card_open( '' );
			Visitor_Ui::empty_state( __( 'Aucun événement pour cette session : elle a peut-être été purgée (durée de conservation dépassée).', 'famma-core' ) );
			Visitor_Ui::card_close();
			echo '</div>';
			return;
		}
		self::facts( $rows, $report, $days );
		Visitor_Inputs_Views::session_panel( $session_id );
		self::events( $rows );
		echo '</div>';
	}

	/**
	 * Fiche de la session.
	 *
	 * @param array<int, array<string, string>> $rows   Événements.
	 * @param Visitor_Report                    $report Lectures.
	 * @param int                               $days   Période.
	 * @return void
	 */
	private static function facts( array $rows, Visitor_Report $report, int $days ): void {
		$first  = $rows[0];
		$last   = $rows[ count( $rows ) - 1 ];
		$ip     = '';
		$order  = 0;
		$source = '';
		foreach ( $rows as $row ) {
			$ip    = '' !== $ip ? $ip : (string) $row['ip'];
			$order = $order ? $order : (int) $row['order_id'];
			if ( '' === $source && '' !== $row['source'] ) {
				$source = trim( implode( ' / ', array_filter( array( $row['source'], $row['medium'], $row['campaign'], $row['content'] ) ) ) );
			}
		}
		$history = $report->visitor_history( (string) $first['visitor_id'] );
		$span    = max( 0, (int) strtotime( $last['created_at'] . ' UTC' ) - (int) strtotime( $first['created_at'] . ' UTC' ) );

		$facts = array(
			array( __( 'Arrivée', 'famma-core' ), esc_html( Visitor_Ui::date( $first['created_at'], 'd/m/Y H:i' ) ) ),
			array( __( 'Durée', 'famma-core' ), esc_html( Visitor_Ui::duration( $span ) ) ),
			array( __( 'Appareil', 'famma-core' ), esc_html( Visitor_Labels::device( (string) $first['device'] ) ) ),
			array( __( 'Source', 'famma-core' ), esc_html( '' !== $source ? $source : 'direct' ) ),
			array( __( 'Adresse IP', 'famma-core' ), Visitor_Ui::ip_link( $ip, $days ) . self::ip_note( $ip, $report ) ),
			array(
				__( 'Visiteur', 'famma-core' ),
				esc_html(
					sprintf(
						/* translators: 1: number of visits, 2: date of the first visit. */
						_n( '%1$s visite, depuis le %2$s', '%1$s visites, depuis le %2$s', $history['sessions'], 'famma-core' ),
						Visitor_Ui::number( $history['sessions'] ),
						Visitor_Ui::date( $history['first_seen'], 'd/m/Y' )
					)
				),
			),
			array( __( 'Commande', 'famma-core' ), $order ? Visitor_Ui::order_link( $order ) : esc_html__( 'Aucune', 'famma-core' ) ),
		);
		echo '<dl class="famma-vj__facts">';
		foreach ( $facts as $fact ) {
			echo '<div><dt>' . esc_html( $fact[0] ) . '</dt><dd>' . $fact[1] . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values escaped when built above.
		}
		echo '</dl>';
	}

	/**
	 * Mention sous l'IP quand d'autres visiteurs l'ont utilisée.
	 *
	 * @param string         $ip     Adresse ou vide.
	 * @param Visitor_Report $report Lectures.
	 * @return string HTML échappé.
	 */
	private static function ip_note( string $ip, Visitor_Report $report ): string {
		if ( '' === $ip ) {
			return '';
		}
		$history = $report->ip_history( $ip );
		if ( $history['visitors'] <= 1 ) {
			return '';
		}
		/* translators: %s: number of orders. */
		$orders = sprintf( _n( '%s commande', '%s commandes', $history['orders'], 'famma-core' ), Visitor_Ui::number( $history['orders'] ) );
		/* translators: 1: number of visitors, 2: number of orders (already worded). */
		$text = sprintf( __( '%1$s visiteurs et %2$s sur cette adresse', 'famma-core' ), Visitor_Ui::number( $history['visitors'] ), $orders );
		return ' ' . Visitor_Ui::badge( $text, $history['orders'] > 1 ? 'warn' : 'neutral' );
	}

	/**
	 * Chronologie des événements.
	 *
	 * @param array<int, array<string, string>> $rows Événements.
	 * @return void
	 */
	private static function events( array $rows ): void {
		$meta  = Visitor_Labels::event_meta();
		$pages = Visitor_Labels::page_types();
		Visitor_Ui::card_open( __( 'Chronologie', 'famma-core' ) );
		echo '<ol class="famma-vj__timeline">';
		foreach ( $rows as $row ) {
			$event   = (string) $row['event'];
			$info    = $meta[ $event ] ?? array( $event, 'marker', 'tech' );
			$product = (int) $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : '';
			$where   = '' !== $product ? $product : ( $pages[ $row['page_type'] ] ?? '' );
			echo '<li class="famma-vj__event famma-vj__event--' . esc_attr( $info[2] ) . '">';
			echo '<span class="famma-vj__event-icon dashicons dashicons-' . esc_attr( $info[1] ) . '" aria-hidden="true"></span>';
			echo '<div class="famma-vj__event-body"><div class="famma-vj__event-line">';
			echo '<strong>' . esc_html( $info[0] ) . '</strong>';
			echo '<time>' . esc_html( Visitor_Ui::date( $row['created_at'], 'H:i:s' ) ) . '</time></div>';
			$detail = self::detail( $row );
			if ( '' !== $where || '' !== $row['path'] ) {
				echo '<span class="famma-vj__event-where">' . esc_html( implode( ' · ', array_filter( array( $where, (string) $row['path'] ) ) ) ) . '</span>';
			}
			if ( '' !== $detail || (int) $row['order_id'] ) {
				echo '<span class="famma-vj__event-detail">' . esc_html( $detail ) . ' ' . Visitor_Ui::order_link( (int) $row['order_id'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in order_link().
			}
			echo '</div></li>';
		}
		echo '</ol>';
		Visitor_Ui::card_close();
	}

	/**
	 * Détail lisible d'un événement.
	 *
	 * @param array<string, string> $row Événement.
	 * @return string Texte brut, échappé par l'appelant.
	 */
	private static function detail( array $row ): string {
		switch ( $row['event'] ) {
			case 'page_leave':
				/* translators: 1: active seconds, 2: scroll depth percent. */
				return sprintf( __( '%1$s s actives · défilement %2$s %%', 'famma-core' ), $row['num'], $row['label'] );
			case 'form_field':
				return Visitor_Labels::field( $row['label'] );
			case 'form_error':
				return Visitor_Labels::errors( $row['label'] );
			case 'add_to_cart':
				/* translators: %s: quantity. */
				return sprintf( __( 'Quantité %s', 'famma-core' ), $row['num'] );
			case 'order_placed':
				/* translators: 1: order total in dinars, 2: number of items. */
				return sprintf( __( '%1$s DT · %2$s article(s)', 'famma-core' ), $row['num'], $row['label'] );
			case 'product_variation':
				return urldecode( $row['label'] );
			default:
				return $row['label'];
		}
	}
}
