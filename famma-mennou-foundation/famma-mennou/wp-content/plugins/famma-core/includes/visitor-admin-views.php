<?php
/**
 * Parcours visiteurs — gabarits de la vue d'administration.
 *
 * Rendu seul, sans requête directe : les données arrivent de
 * `Visitor_Report`. Chaque valeur est échappée au moment de l'affichage.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Blocs HTML de la page « Parcours visiteurs ».
 */
final class Visitor_Admin_Views {

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
	 * Libellés lisibles des champs de formulaire connus.
	 *
	 * @param string $name Nom technique du champ.
	 * @return string
	 */
	private static function field_label( string $name ): string {
		$labels = array(
			'billing_first_name' => __( 'Prénom', 'famma-core' ),
			'billing_last_name'  => __( 'Nom', 'famma-core' ),
			'billing_phone'      => __( 'Téléphone', 'famma-core' ),
			'billing_state'      => __( 'Gouvernorat', 'famma-core' ),
			'billing_city'       => __( 'Ville', 'famma-core' ),
			'billing_address_1'  => __( 'Adresse', 'famma-core' ),
			'billing_email'      => __( 'E-mail', 'famma-core' ),
			'order_comments'     => __( 'Note de commande', 'famma-core' ),
			'famma_quantity'     => __( 'Quantité', 'famma-core' ),
		);
		return $labels[ $name ] ?? $name;
	}

	/**
	 * Indicateurs clés et entonnoir.
	 *
	 * @param array<string, int> $s Agrégats de la période.
	 * @return void
	 */
	public static function summary( array $s ): void {
		$sessions = (int) ( $s['sessions'] ?? 0 );
		$tiles    = array(
			__( 'Visiteurs uniques', 'famma-core' ) => number_format_i18n( (int) ( $s['visitors'] ?? 0 ) ),
			__( 'Sessions', 'famma-core' )          => number_format_i18n( $sessions ),
			__( 'Pages vues', 'famma-core' )        => number_format_i18n( (int) ( $s['page_views'] ?? 0 ) ),
			__( 'Commandes suivies', 'famma-core' ) => number_format_i18n( (int) ( $s['orders'] ?? 0 ) ),
			__( 'Conversion', 'famma-core' )        => self::pct( (int) ( $s['ordered'] ?? 0 ), $sessions ),
			__( 'Clics WhatsApp', 'famma-core' )    => number_format_i18n( (int) ( $s['whatsapp'] ?? 0 ) ),
		);
		echo '<div class="famma-vj__tiles">';
		foreach ( $tiles as $label => $value ) {
			echo '<div class="famma-vj__tile"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
		}
		echo '</div>';

		$steps = array(
			'sessions' => __( 'Sessions', 'famma-core' ),
			'product'  => __( 'Fiche produit vue', 'famma-core' ),
			'cart'     => __( 'Ajout au panier', 'famma-core' ),
			'form'     => __( 'Formulaire commencé', 'famma-core' ),
			'submit'   => __( 'Formulaire envoyé', 'famma-core' ),
			'ordered'  => __( 'Commande passée', 'famma-core' ),
		);
		echo '<h2>' . esc_html__( 'Entonnoir (sessions)', 'famma-core' ) . '</h2>';
		echo '<table class="widefat striped famma-vj__funnel"><tbody>';
		$previous = 0;
		foreach ( $steps as $key => $label ) {
			$count = (int) ( $s[ $key ] ?? 0 );
			$width = $sessions > 0 ? max( 1, (int) round( 100 * $count / $sessions ) ) : 0;
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th>';
			echo '<td class="famma-vj__bar-cell"><span class="famma-vj__bar" style="inline-size:' . esc_attr( (string) $width ) . '%"></span></td>';
			echo '<td class="num">' . esc_html( number_format_i18n( $count ) ) . '</td>';
			echo '<td class="num">' . esc_html( self::pct( $count, $sessions ) ) . '</td>';
			echo '<td class="num famma-vj__drop">' . ( 'sessions' === $key ? '' : esc_html( self::pct( $count, $previous ) ) ) . '</td></tr>';
			$previous = $count;
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Dernière colonne : passage depuis l\'étape précédente.', 'famma-core' ) . '</p>';
	}

	/**
	 * Champs touchés, par formulaire : où les visiteurs s'arrêtent.
	 *
	 * @param array<string, array<string, mixed>> $forms Données par formulaire.
	 * @return void
	 */
	public static function forms( array $forms ): void {
		$titles = array(
			'express'  => __( 'Formulaire express (fiche produit)', 'famma-core' ),
			'checkout' => __( 'Checkout classique', 'famma-core' ),
		);
		echo '<h2>' . esc_html__( 'Formulaires de commande', 'famma-core' ) . '</h2>';
		if ( array() === $forms ) {
			echo '<p>' . esc_html__( 'Aucune interaction avec un formulaire sur la période.', 'famma-core' ) . '</p>';
			return;
		}
		echo '<div class="famma-vj__forms">';
		foreach ( $titles as $form => $title ) {
			if ( empty( $forms[ $form ] ) ) {
				continue;
			}
			$started = (int) $forms[ $form ]['started'];
			echo '<div><h3>' . esc_html( $title ) . '</h3><table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'Champ', 'famma-core' ) . '</th><th class="num">' . esc_html__( 'Sessions', 'famma-core' ) . '</th><th class="num">' . esc_html__( '% des formulaires commencés', 'famma-core' ) . '</th></tr></thead><tbody>';
			foreach ( (array) $forms[ $form ]['fields'] as $name => $count ) {
				echo '<tr><td>' . esc_html( self::field_label( (string) $name ) ) . '</td><td class="num">' . esc_html( number_format_i18n( (int) $count ) ) . '</td><td class="num">' . esc_html( self::pct( (int) $count, $started ) ) . '</td></tr>';
			}
			echo '</tbody><tfoot><tr><th>' . esc_html__( 'Envoyés / commencés', 'famma-core' ) . '</th><th class="num">' . esc_html( number_format_i18n( (int) $forms[ $form ]['submitted'] ) . ' / ' . number_format_i18n( $started ) ) . '</th><th class="num">' . esc_html( self::pct( (int) $forms[ $form ]['submitted'], $started ) ) . '</th></tr></tfoot></table></div>';
		}
		echo '</div>';
	}

	/**
	 * Liste paginée des sessions.
	 *
	 * @param array<int, array<string, string>> $rows  Sessions.
	 * @param int                               $days  Période.
	 * @param int                               $paged Page courante.
	 * @param callable                          $url   Constructeur d'URL de la page.
	 * @return void
	 */
	public static function sessions( array $rows, int $days, int $paged, callable $url ): void {
		echo '<h2>' . esc_html__( 'Sessions récentes', 'famma-core' ) . '</h2>';
		if ( array() === $rows ) {
			echo '<p>' . esc_html__( 'Aucune session enregistrée sur la période.', 'famma-core' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Début', 'famma-core' ), __( 'Durée', 'famma-core' ), __( 'Appareil', 'famma-core' ), __( 'Source', 'famma-core' ), __( 'Pages', 'famma-core' ), __( 'Panier', 'famma-core' ), __( 'Commande', 'famma-core' ), '' ) as $head ) {
			echo '<th>' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$start    = strtotime( $row['started'] . ' UTC' );
			$duration = max( 0, strtotime( $row['ended'] . ' UTC' ) - $start );
			$source   = trim( $row['source'] . ( '' !== $row['campaign'] ? ' / ' . $row['campaign'] : '' ) );
			echo '<tr><td>' . esc_html( wp_date( 'd/m H:i', $start ) ) . '</td>';
			echo '<td>' . esc_html( human_time_diff( 0, $duration ) ) . '</td>';
			echo '<td>' . esc_html( $row['device'] ) . '</td>';
			echo '<td>' . esc_html( '' !== $source ? $source : 'direct' ) . '</td>';
			echo '<td class="num">' . esc_html( $row['pages'] ) . '</td>';
			echo '<td class="num">' . esc_html( $row['carts'] ) . '</td>';
			echo '<td>' . self::order_link( (int) $row['order_id'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in order_link().
			echo '<td><a href="' . esc_url(
				$url(
					array(
						'days'    => $days,
						'session' => $row['session_id'],
					)
				)
			) . '">' . esc_html__( 'Parcours', 'famma-core' ) . '</a></td></tr>';
		}
		echo '</tbody></table><p class="famma-vj__pager">';
		if ( $paged > 1 ) {
			echo '<a class="button" href="' . esc_url(
				$url(
					array(
						'days'  => $days,
						'paged' => $paged - 1,
					)
				)
			) . '">' . esc_html__( '← Plus récentes', 'famma-core' ) . '</a> ';
		}
		if ( count( $rows ) === Visitor_Report::PER_PAGE ) {
			echo '<a class="button" href="' . esc_url(
				$url(
					array(
						'days'  => $days,
						'paged' => $paged + 1,
					)
				)
			) . '">' . esc_html__( 'Plus anciennes →', 'famma-core' ) . '</a>';
		}
		echo '</p>';
	}

	/**
	 * Lien vers la commande, échappé.
	 *
	 * @param int $order_id Identifiant de commande.
	 * @return string
	 */
	private static function order_link( int $order_id ): string {
		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof \WC_Order ) {
			return '';
		}
		return '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
	}

	/**
	 * Chronologie d'une session.
	 *
	 * @param string $session_id Identifiant de session.
	 * @param string $back       URL de retour à la synthèse.
	 * @return void
	 */
	public static function timeline( string $session_id, string $back ): void {
		$rows   = ( new Visitor_Report() )->timeline( $session_id );
		$labels = Visitor_Admin::event_labels();
		echo '<h1>' . esc_html__( 'Parcours d\'une session', 'famma-core' ) . '</h1>';
		echo '<p><a href="' . esc_url( $back ) . '">' . esc_html__( '← Retour à la synthèse', 'famma-core' ) . '</a></p>';
		if ( array() === $rows ) {
			echo '<p>' . esc_html__( 'Aucun événement pour cette session.', 'famma-core' ) . '</p>';
			return;
		}
		$first = $rows[0];
		printf(
			'<p class="famma-vj__meta">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: device, 2: traffic source, 3: visitor id. */
					__( 'Appareil : %1$s · Source : %2$s · Visiteur : %3$s', 'famma-core' ),
					$first['device'],
					trim( $first['source'] . ' ' . $first['medium'] . ' ' . $first['campaign'] ),
					$first['visitor_id']
				)
			)
		);
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Heure', 'famma-core' ) . '</th><th>' . esc_html__( 'Événement', 'famma-core' ) . '</th><th>' . esc_html__( 'Page', 'famma-core' ) . '</th><th>' . esc_html__( 'Détail', 'famma-core' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$detail = '';
			if ( 'page_leave' === $row['event'] ) {
				/* translators: 1: active seconds, 2: scroll depth percent. */
				$detail = sprintf( __( '%1$s s actives · défilement %2$s %%', 'famma-core' ), $row['num'], $row['label'] );
			} elseif ( 'form_field' === $row['event'] ) {
				$detail = self::field_label( $row['label'] );
			} elseif ( 'add_to_cart' === $row['event'] ) {
				/* translators: %s: quantity. */
				$detail = sprintf( __( 'Quantité %s', 'famma-core' ), $row['num'] );
			} elseif ( '' !== $row['label'] ) {
				$detail = $row['label'];
			}
			$product = (int) $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : '';
			echo '<tr><td>' . esc_html( wp_date( 'd/m H:i:s', strtotime( $row['created_at'] . ' UTC' ) ) ) . '</td>';
			echo '<td>' . esc_html( $labels[ $row['event'] ] ?? $row['event'] ) . '</td>';
			echo '<td>' . esc_html( trim( $row['path'] . ( '' !== $product ? ' — ' . $product : '' ) ) ) . '</td>';
			echo '<td>' . esc_html( $detail ) . ' ' . self::order_link( (int) $row['order_id'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in order_link().
		}
		echo '</tbody></table>';
	}
}
