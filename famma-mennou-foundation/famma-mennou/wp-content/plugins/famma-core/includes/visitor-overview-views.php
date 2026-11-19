<?php
/**
 * Parcours visiteurs — onglet « Vue d'ensemble ».
 *
 * Trois blocs, du plus général au plus fin : les chiffres de la période,
 * l'entonnoir de l'arrivée à la commande, puis qui sont ces visiteurs (type
 * de session). Rendu seul : les nombres arrivent de `Visitor_Report` et de
 * `Visitor_Session_Type`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu de la vue d'ensemble.
 */
final class Visitor_Overview_Views {

	/**
	 * Affiche l'onglet.
	 *
	 * @param array<string, int> $s     Agrégats de la période.
	 * @param array<string, int> $types Sessions par type.
	 * @param int                $days  Période.
	 * @return void
	 */
	public static function render( array $s, array $types, int $days ): void {
		self::kpis( $s );
		echo '<div class="famma-vj__grid famma-vj__grid--2">';
		self::funnel( $s );
		self::types( $types, $days );
		echo '</div>';
	}

	/**
	 * Tuiles d'indicateurs.
	 *
	 * @param array<string, int> $s Agrégats.
	 * @return void
	 */
	private static function kpis( array $s ): void {
		$sessions = (int) ( $s['sessions'] ?? 0 );
		$views    = (int) ( $s['page_views'] ?? 0 );
		Visitor_Ui::kpis(
			array(
				array(
					'label' => __( 'Visiteurs uniques', 'famma-core' ),
					'value' => Visitor_Ui::number( (int) ( $s['visitors'] ?? 0 ) ),
					'icon'  => 'admin-users',
				),
				array(
					'label' => __( 'Sessions', 'famma-core' ),
					'value' => Visitor_Ui::number( $sessions ),
					/* translators: %s: share of page views made on a phone. */
					'hint'  => sprintf( __( '%s des pages vues sur mobile', 'famma-core' ), Visitor_Ui::pct( (int) ( $s['mobile_views'] ?? 0 ), $views ) ),
					'icon'  => 'groups',
				),
				array(
					'label' => __( 'Pages vues', 'famma-core' ),
					'value' => Visitor_Ui::number( $views ),
					/* translators: %s: average pages per session. */
					'hint'  => sprintf( __( '%s par session', 'famma-core' ), $sessions > 0 ? number_format_i18n( $views / $sessions, 1 ) : '—' ),
					'icon'  => 'visibility',
				),
				array(
					'label' => __( 'Commandes', 'famma-core' ),
					'value' => Visitor_Ui::number( (int) ( $s['orders'] ?? 0 ) ),
					/* translators: %s: total amount of the orders, in dinars. */
					'hint'  => sprintf( __( '%s DT commandés (non encaissés)', 'famma-core' ), Visitor_Ui::number( (int) ( $s['revenue'] ?? 0 ) ) ),
					'icon'  => 'cart',
					'tone'  => 'good',
				),
				array(
					'label' => __( 'Conversion', 'famma-core' ),
					'value' => Visitor_Ui::pct( (int) ( $s['ordered'] ?? 0 ), $sessions ),
					'hint'  => __( 'Sessions qui ont commandé', 'famma-core' ),
					'icon'  => 'chart-line',
					'tone'  => 'good',
				),
				array(
					'label' => __( 'Clics WhatsApp', 'famma-core' ),
					'value' => Visitor_Ui::number( (int) ( $s['whatsapp'] ?? 0 ) ),
					'icon'  => 'whatsapp',
					'tone'  => 'contact',
				),
				array(
					'label' => __( 'Adresses IP', 'famma-core' ),
					'value' => Visitor_Ui::number( (int) ( $s['ips'] ?? 0 ) ),
					'hint'  => Visitor_Settings::store_ip() ? __( 'Distinctes, enregistrées en clair', 'famma-core' ) : __( 'Enregistrement désactivé', 'famma-core' ),
					'icon'  => 'admin-site-alt3',
					'tone'  => 'neutral',
				),
			)
		);
	}

	/**
	 * Entonnoir en sessions : barre, nombre, part des sessions, passage.
	 *
	 * Le panier n'est pas une marche : la commande express le remplit à
	 * l'envoi du formulaire, après « Formulaire commencé ». Le placer ici
	 * donnait des passages supérieurs à 100 %. Il figure dans les sessions.
	 *
	 * @param array<string, int> $s Agrégats.
	 * @return void
	 */
	private static function funnel( array $s ): void {
		$steps    = array(
			'sessions' => array( __( 'Arrivée sur le site', 'famma-core' ), 'admin-home' ),
			'product'  => array( __( 'Fiche produit vue', 'famma-core' ), 'products' ),
			'form'     => array( __( 'Formulaire commencé', 'famma-core' ), 'edit' ),
			'submit'   => array( __( 'Formulaire envoyé', 'famma-core' ), 'yes' ),
			'ordered'  => array( __( 'Commande passée', 'famma-core' ), 'cart' ),
		);
		$sessions = (int) ( $s['sessions'] ?? 0 );
		Visitor_Ui::card_open( __( 'Entonnoir', 'famma-core' ), __( 'Sessions qui ont atteint chaque étape. La pastille indique le passage depuis l\'étape précédente.', 'famma-core' ) );
		if ( 0 === $sessions ) {
			Visitor_Ui::empty_state( __( 'Aucune session sur la période.', 'famma-core' ) );
			Visitor_Ui::card_close();
			return;
		}
		echo '<ol class="famma-vj__funnel">';
		$previous = 0;
		foreach ( $steps as $key => $meta ) {
			$count = (int) ( $s[ $key ] ?? 0 );
			echo '<li class="famma-vj__step"><div class="famma-vj__step-head">';
			echo '<span class="famma-vj__step-name"><span class="dashicons dashicons-' . esc_attr( $meta[1] ) . '" aria-hidden="true"></span>' . esc_html( $meta[0] ) . '</span>';
			echo '<span class="famma-vj__step-figures"><strong>' . esc_html( Visitor_Ui::number( $count ) ) . '</strong> <span class="famma-vj__muted">' . esc_html( Visitor_Ui::pct( $count, $sessions ) ) . '</span>';
			if ( 'sessions' !== $key ) {
				echo ' ' . Visitor_Ui::badge( '→ ' . Visitor_Ui::pct( $count, $previous ), 'info', __( 'Passage depuis l\'étape précédente', 'famma-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in badge().
			}
			echo '</span></div>' . Visitor_Ui::bar( $count, $sessions ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in bar().
			$previous = $count;
		}
		echo '</ol>';
		/* translators: %s: number of sessions with an add to cart. */
		echo '<p class="famma-vj__note">' . esc_html( sprintf( __( 'Sessions avec un ajout au panier : %s.', 'famma-core' ), Visitor_Ui::number( (int) ( $s['cart'] ?? 0 ) ) ) ) . '</p>';
		Visitor_Ui::card_close();
	}

	/**
	 * Répartition des sessions par type, avec la signification de chacun.
	 *
	 * @param array<string, int> $counts Sessions par type.
	 * @param int                $days   Période.
	 * @return void
	 */
	private static function types( array $counts, int $days ): void {
		$total = array_sum( $counts );
		Visitor_Ui::card_open( __( 'Qui sont ces visiteurs ?', 'famma-core' ), __( 'Robots déclarés et équipe connectée ne sont jamais enregistrés. Le type vient de ce que le visiteur a fait.', 'famma-core' ) );
		if ( 0 === $total ) {
			Visitor_Ui::empty_state( __( 'Aucune session sur la période.', 'famma-core' ) );
			Visitor_Ui::card_close();
			return;
		}
		echo '<div class="famma-vj__stack" role="img" aria-label="' . esc_attr__( 'Répartition des sessions par type', 'famma-core' ) . '">';
		foreach ( $counts as $kind => $count ) {
			if ( $count > 0 ) {
				echo '<span class="famma-vj__stack-part famma-vj__bar-fill--' . esc_attr( Visitor_Session_Type::tone( $kind ) ) . '" style="flex-grow:' . esc_attr( (string) (int) $count ) . '"></span>';
			}
		}
		echo '</div><ul class="famma-vj__legend">';
		foreach ( Visitor_Session_Type::labels() as $kind => $label ) {
			$count = (int) ( $counts[ $kind ] ?? 0 );
			$url   = Visitor_Admin::instance()->url(
				array(
					'tab'  => 'sessions',
					'days' => $days,
					'kind' => $kind,
				)
			);
			echo '<li><a href="' . esc_url( $url ) . '">' . Visitor_Session_Type::badge( $kind ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in badge().
			echo '<span class="famma-vj__legend-figures"><strong>' . esc_html( Visitor_Ui::number( $count ) ) . '</strong> <span class="famma-vj__muted">' . esc_html( Visitor_Ui::pct( $count, $total ) ) . '</span></span>';
			echo '<span class="famma-vj__legend-text">' . esc_html( $label[1] ) . '</span></li>';
		}
		echo '</ul>';
		Visitor_Ui::card_close();
	}
}
