<?php
/**
 * Surveillance des OrderPlaced manquants (phase B des réformes du pixel, lot 4, D3).
 *
 * Avant ce lot, l'état de santé ne comptait les manquants que pour le
 * Purchase : un OrderPlaced jamais mis en file, ou perdu après ses 5
 * tentatives, ne déclenchait rien. Désormais, une commande dont un
 * OrderPlaced est attendu (`expected()`) et qui n'en a pas d'accepté par
 * Meta 30 minutes après sa création allume :
 *
 * - `orderplaced_missing` (rouge) tant qu'un renvoi reste possible
 *   (mêmes bornes que le bouton « Renvoyer », `Capi_Reconcile`) ;
 * - `orderplaced_lost` (orange) quand plus aucun renvoi n'est possible.
 *
 * Les deux raisons NOMMENT les commandes (numéros seulement, jamais de
 * donnée client). Seules comptent les commandes dont l'OrderPlaced a
 * vraiment été accepté pour envoi (`_famma_orderplaced_checkout_accepted`,
 * posé par la mise en file de `Capi` : interrupteur actif et commande
 * éligible à ce moment-là) ; le délai de 30 minutes court depuis l'heure
 * d'envoi prévue (`due_at()` : création, ou heure différée d'un panier
 * récupéré, décision Q19). « Acquitter » (`Capi_Orderplaced_Ack`) lève
 * toujours l'alerte d'une commande. Les commandes créées avant la mise en service de cette
 * surveillance (option `famma_capi_orderplaced_watch_since`, posée au
 * premier calcul) et celles de plus de 6 jours (`Config::CAPI_MAX_EVENT_AGE`)
 * ne remontent pas.
 *
 * Note qualité Meta (lecture du lot « qualité ») : une note OrderPlaced
 * connue inférieure à 6 donne un simple avertissement orange.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Règle « OrderPlaced manquant » de l'état de santé.
 */
final class Capi_Orderplaced_Watch {

	/**
	 * Option : date de mise en service de la surveillance.
	 */
	public const SINCE = 'famma_capi_orderplaced_watch_since';

	/**
	 * Délai après la création de la commande, en secondes.
	 */
	public const GRACE = 1800;

	/**
	 * Commandes lues au plus par calcul.
	 */
	private const LIMIT = 100;

	/**
	 * Numéros cités au plus dans une raison.
	 */
	private const NAMED = 10;

	/**
	 * Note qualité Meta sous laquelle un avertissement orange s'affiche.
	 */
	public const QUALITY_MIN = 6.0;

	/**
	 * Un OrderPlaced est-il attendu pour cette commande ?
	 *
	 * Même règle que l'émetteur (`Capi::order_placed_block()`, « Panier
	 * récupéré » compris) : commande éligible, ni d'essai ni refusée,
	 * interrupteur OrderPlaced actif, ET envoi réellement accepté à la mise en
	 * file (`_famma_orderplaced_checkout_accepted`) — sans ce marqueur,
	 * « Renvoyer » ne pourrait rien (relecture I2). Pas une commande acquittée.
	 * Filtre `famma_capi_orderplaced_expected`.
	 *
	 * @param \WC_Order $order Commande.
	 * @return bool
	 */
	public static function expected( \WC_Order $order ): bool {
		$expected = (bool) get_option( 'famma_meta_orderplaced_enabled', false )
			&& (bool) $order->get_meta( '_famma_orderplaced_checkout_accepted' )
			&& ! Capi_Orderplaced_Ack::acknowledged( $order )
			&& ! Capi::is_test_order( $order )
			&& '' === Capi::instance()->order_placed_block( $order );

		/**
		 * Filtre l'attente d'un OrderPlaced pour une commande (surveillance, lot 4).
		 *
		 * @param bool      $expected Attendu selon la règle de l'émetteur.
		 * @param \WC_Order $order    Commande.
		 */
		return (bool) apply_filters( 'famma_capi_orderplaced_expected', $expected, $order );
	}

	/**
	 * Heure d'envoi prévue de l'OrderPlaced : la création, ou l'heure différée
	 * d'un panier récupéré (Q19), donnée par le filtre
	 * `Capi_Recovered_Cart::due_at()` (méta `_famma_orderplaced_due_at`, 0 sans
	 * départ différé), ajustable par le filtre `famma_capi_orderplaced_due_at`.
	 *
	 * @param \WC_Order $order Commande.
	 * @return int
	 */
	public static function due_at( \WC_Order $order ): int {
		$created = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
		$due     = class_exists( Capi_Recovered_Cart::class ) ? Capi_Recovered_Cart::due_at( $order ) : 0;

		/**
		 * Heure d'envoi prévue de l'OrderPlaced d'une commande (surveillance, lot 4).
		 *
		 * @param int       $due   Horodatage prévu (création par défaut).
		 * @param \WC_Order $order Commande.
		 */
		return (int) apply_filters( 'famma_capi_orderplaced_due_at', max( $created, $due ), $order );
	}

	/**
	 * Raisons de l'état de santé : manquants (rouge), perdus (orange), note qualité basse (orange).
	 *
	 * @param int $now Horloge.
	 * @return array<int, array<string, string>>
	 */
	public static function reasons( int $now ): array {
		$sorted  = self::sort( $now );
		$reasons = array();
		$sorted  = array_map( 'array_values', $sorted );
		if ( array() !== $sorted['missing'] ) {
			$reasons[] = self::reason( 'orderplaced_missing', 'rouge', self::numbers( $sorted['missing'] ) );
		}
		if ( array() !== $sorted['lost'] ) {
			$reasons[] = self::reason( 'orderplaced_lost', 'orange', self::numbers( $sorted['lost'] ) );
		}
		$score = class_exists( Capi_Meta_Quality::class ) ? Capi_Meta_Quality::score( 'OrderPlaced' ) : null;
		if ( null !== $score && $score < self::QUALITY_MIN ) {
			$reasons[] = self::reason( 'orderplaced_quality', 'orange', number_format( $score, 1, ',', '' ) );
		}
		return $reasons;
	}

	/**
	 * Commandes sans OrderPlaced accepté 30 minutes après l'heure d'envoi prévue.
	 *
	 * @param int $now Horloge.
	 * @return array{missing: array<int, string>, lost: array<int, string>} Identifiant => numéro.
	 */
	public static function sort( int $now ): array {
		$sorted = array(
			'missing' => array(),
			'lost'    => array(),
		);
		$since  = (int) get_option( self::SINCE, 0 );
		if ( $since <= 0 ) {
			// Mise en service : les commandes antérieures ne remontent jamais.
			update_option( self::SINCE, $now, false );
			return $sorted;
		}
		if ( ! function_exists( 'wc_get_orders' ) || ! get_option( 'famma_meta_orderplaced_enabled', false ) ) {
			return $sorted;
		}
		$from   = max( $since, $now - Config::CAPI_MAX_EVENT_AGE );
		$orders = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'date_created' => '>' . $from,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'limit'        => self::LIMIT,
			)
		);
		$late   = array();
		foreach ( is_array( $orders ) ? $orders : array() as $order ) {
			if ( ! $order instanceof \WC_Order || $order->get_meta( '_famma_orderplaced_sent' ) ) {
				continue;
			}
			$created = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
			if ( $created <= $since || $now - self::due_at( $order ) < self::GRACE || ! self::expected( $order ) ) {
				continue;
			}
			$late[ $order->get_id() ] = $order;
		}
		if ( array() === $late ) {
			return $sorted;
		}
		$records = Capi_Delivery_Registry::instance()->for_orders( array_keys( $late ) );
		foreach ( $late as $order_id => $order ) {
			$record = $records[ $order_id ]['OrderPlaced'] ?? array();
			$state  = isset( $record['state'] ) ? (string) $record['state'] : 'absent';
			if ( 'accepted' === $state ) {
				continue;
			}
			$resend = Capi_Reconcile::resend_allowed( $state, $record, Capi_Reconcile::event_time( $order, 'OrderPlaced', $record, $now ), $now );

			$sorted[ $resend ? 'missing' : 'lost' ][ $order_id ] = (string) $order->get_order_number();
		}
		return $sorted;
	}

	/**
	 * Libellé d'une raison de cette règle, null pour une autre raison.
	 *
	 * @param array<string, string> $reason Raison enregistrée.
	 * @return string|null
	 */
	public static function label( array $reason ): ?string {
		$detail = (string) ( $reason['detail'] ?? '' );
		switch ( $reason['code'] ?? '' ) {
			case 'orderplaced_missing':
				/* translators: %s: order numbers. */
				return sprintf( __( 'Commande(s) n° %s : aucun OrderPlaced accepté par Meta 30 minutes après l’heure d’envoi prévue : « Renvoyer » dans le rapprochement (un refus de Meta est renvoyé automatiquement une fois le problème réglé), ou « Acquitter » ci-dessous.', 'famma-core' ), $detail );
			case 'orderplaced_lost':
				/* translators: %s: order numbers. */
				return sprintf( __( 'Commande(s) n° %s : OrderPlaced jamais accepté par Meta et qu’on ne peut plus renvoyer (tentatives épuisées ou refus trop ancien) : « Acquitter » ci-dessous.', 'famma-core' ), $detail );
			case 'orderplaced_quality':
				/* translators: %s: Meta event match quality score. */
				return sprintf( __( 'Note de qualité Meta d’OrderPlaced basse (%s sur 10) : voir « Qualité des données Meta ».', 'famma-core' ), $detail );
		}
		return null;
	}

	/**
	 * Numéros cités, au plus 10, puis le nombre restant.
	 *
	 * @param string[] $numbers Numéros de commande.
	 * @return string
	 */
	private static function numbers( array $numbers ): string {
		$shown = implode( ', ', array_slice( $numbers, 0, self::NAMED ) );
		$more  = count( $numbers ) - self::NAMED;
		return $more > 0 ? $shown . ' (+' . $more . ')' : $shown;
	}

	/**
	 * Une raison au format de `Capi_Health_Rules`.
	 *
	 * @param string $code   Code stable.
	 * @param string $level  `rouge` ou `orange`.
	 * @param string $detail Détail sans donnée personnelle.
	 * @return array<string, string>
	 */
	private static function reason( string $code, string $level, string $detail ): array {
		return array(
			'code'   => $code,
			'niveau' => $level,
			'detail' => $detail,
		);
	}
}
