<?php
/**
 * OrderPlaced des paniers récupérés (lot 7 de la phase B du pixel, D5 de l'évaluation du 11/11).
 *
 * Une commande créée par le bouton « Créer la commande » des Saisies à
 * relancer (`Visitor_Inputs_Order`, canal « Panier récupéré ») est
 * `created_via = admin` : avant ce lot, elle n'envoyait jamais OrderPlaced, et
 * son Purchase partait sans identifiant de clic. Désormais :
 *
 * - à la création, les identifiants Meta de la visite d'origine sont
 *   recopiés sur la commande : cookies `_fbc` / `_fbp` saisis avec la ligne
 *   (lus côté serveur sur la requête du visiteur, `Visitor_Inputs_Collector`),
 *   IP de la session lue dans le journal des visiteurs (si l'IP y est
 *   conservée). Jamais ceux de l'administrateur, jamais de user-agent : la
 *   commande reste un événement hors site, `action_source` = `phone_call`
 *   (Q20 : vente conclue au téléphone par l'équipe), pour tous ses événements ;
 * - OrderPlaced part par la file de `Capi` 15 minutes après la mise en file
 *   (Q19 : le temps de cocher « Commande d'essai » sur la fiche), puis tout
 *   est relu au départ : interrupteurs, essai, statut, déjà envoyé, registre,
 *   âge ≤ 6 jours (event_time = création de la commande). Il faut aussi que
 *   l'interrupteur propre « paniers récupérés » (FAMMA → Mesure, activé par
 *   défaut) l'autorise et que la commande ait un article. La case « Commande
 *   d'essai » du bouton « Créer la commande » pose `_famma_test_order` avant
 *   tout changement de statut (`Visitor_Inputs_Order`).
 *
 * Heure d'envoi prévue (pour la surveillance) : `Capi_Recovered_Cart::due_at()`.
 *
 * Les autres commandes saisies à la main (administration, application) restent sans OrderPlaced.
 *
 * `fbc` réutilisé : la valeur du cookie `_fbc` est recopiée telle quelle
 * (Meta, « fbp and fbc Parameters » : creationTime = « when the `_fbc` was
 * stored », fbclid « case sensitive - do not apply any modifications »).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Règle, identifiants de la visite et mise en file des paniers récupérés.
 */
final class Capi_Recovered_Cart {

	/**
	 * Interrupteur (FAMMA → Mesure), activé par défaut.
	 */
	public const OPTION = 'famma_meta_recovered_orderplaced_enabled';

	/**
	 * Délai par défaut de l'OrderPlaced d'un panier récupéré, en secondes (Q19).
	 * Constante `FAMMA_CAPI_RECOVERED_DELAY` (wp-config.php, secondes) pour le changer.
	 */
	public const DELAY = 900;

	/**
	 * Délai maximal accepté, en secondes.
	 */
	public const DELAY_MAX = 3600;

	/**
	 * Méta : heure de départ prévue de l'OrderPlaced (horodatage Unix).
	 */
	public const DUE_META = '_famma_orderplaced_due_at';

	/**
	 * Source d'action des événements d'un panier récupéré (Q20).
	 */
	public const ACTION_SOURCE = 'phone_call';

	/**
	 * Instance partagée.
	 *
	 * @var Capi_Recovered_Cart|null
	 */
	private static ?Capi_Recovered_Cart $i = null;

	/**
	 * Renvoie l'instance partagée, crochets posés au premier appel.
	 *
	 * @return Capi_Recovered_Cart
	 */
	public static function instance(): Capi_Recovered_Cart {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'woocommerce_order_status_changed', array( self::$i, 'status_changed' ), 25, 3 );
			add_filter( 'famma_capi_action_source', array( self::$i, 'action_source' ), 5, 2 );
			add_action( 'admin_init', array( Capi_Recovered_Cart_Admin::class, 'register_setting' ), 20 );
		}
		return self::$i;
	}

	/**
	 * L'interrupteur est-il actif ?
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return (bool) get_option( self::OPTION, true );
	}

	/**
	 * Commande créée par le bouton « Créer la commande » d'une saisie ?
	 *
	 * @param \WC_Order $order Commande.
	 * @return bool
	 */
	public static function is_recovered( \WC_Order $order ): bool {
		return 'admin' === $order->get_created_via()
			&& Order_Channel::RECOVERED === (string) $order->get_meta( Order_Channel::META )
			&& (int) $order->get_meta( Visitor_Inputs_Order::META_INPUT ) > 0;
	}

	/**
	 * Motif d'exclusion d'une commande qui ne vient pas du checkout, vide si
	 * c'est un panier récupéré (ou une commande saisie à la main dans
	 * l'administration, `Capi_Manual_Order`, L6) qui peut envoyer OrderPlaced.
	 *
	 * @param \WC_Order $order Commande dont `created_via` n'est pas `checkout`.
	 * @return string `created_via:<origine>`, `recovered_cart:disabled`, `recovered_cart:no_product`, `manual_order:*` ou vide.
	 */
	public static function block_reason( \WC_Order $order ): string {
		if ( Capi_Manual_Order::is_manual( $order ) ) {
			return Capi_Manual_Order::block_reason( $order );
		}
		if ( ! self::is_recovered( $order ) ) {
			$via = (string) $order->get_created_via();
			return 'created_via:' . ( '' !== $via ? $via : 'unknown' );
		}
		if ( ! self::enabled() ) {
			return 'recovered_cart:disabled';
		}
		return array() === $order->get_items() ? 'recovered_cart:no_product' : '';
	}

	/**
	 * Délai de l'OrderPlaced d'un panier récupéré, en secondes, borné à [0, 3600].
	 *
	 * @return int
	 */
	public static function delay(): int {
		$seconds = defined( 'FAMMA_CAPI_RECOVERED_DELAY' ) ? (int) constant( 'FAMMA_CAPI_RECOVERED_DELAY' ) : self::DELAY;
		return max( 0, min( self::DELAY_MAX, $seconds ) );
	}

	/**
	 * Fixe (une seule fois) l'heure de départ de l'OrderPlaced d'un panier récupéré.
	 *
	 * Appelée par `Capi::queue_order_placed()` avant l'enregistrement de la
	 * commande. Une heure déjà fixée est gardée : une remise en file ne repousse
	 * jamais le départ.
	 *
	 * @param \WC_Order $order Commande mise en file.
	 * Même délai pour une commande saisie à la main dans l'administration (L6).
	 *
	 * @return int Heure de départ (0 pour une commande du site : immédiat).
	 */
	public static function plan( \WC_Order $order ): int {
		if ( ! self::is_recovered( $order ) && ! Capi_Manual_Order::is_manual( $order ) ) {
			return 0;
		}
		$due = self::due_at( $order );
		if ( $due <= 0 ) {
			$due = time() + self::delay();
			$order->update_meta_data( self::DUE_META, $due );
		}
		return $due;
	}

	/**
	 * Heure d'envoi prévue de l'OrderPlaced (surveillance, lot 4).
	 *
	 * @param \WC_Order $order Commande.
	 * @return int Horodatage Unix ; 0 = pas de départ différé (commande du site, ou panier pas encore en file).
	 */
	public static function due_at( \WC_Order $order ): int {
		return max( 0, (int) $order->get_meta( self::DUE_META ) );
	}

	/**
	 * Filtre `famma_capi_action_source` : `phone_call` pour un panier récupéré.
	 *
	 * Ne s'applique qu'aux événements hors site : une commande du site garde `website`.
	 *
	 * @param mixed     $source Source proposée.
	 * @param \WC_Order $order  Commande.
	 * @return mixed
	 */
	public function action_source( $source, $order ) {
		return $order instanceof \WC_Order && self::is_recovered( $order ) ? self::ACTION_SOURCE : $source;
	}

	/**
	 * Cookies Meta valides de la requête courante, sans compter un `_fbc` refusé
	 * (le compteur de santé ne concerne que les commandes).
	 *
	 * @return array{fbc: string, fbp: string}
	 */
	public static function request_cookies(): array {
		$read = static function ( string $name ): string {
			return isset( $_COOKIE[ $name ] ) && is_string( $_COOKIE[ $name ] ) ? Capi_Signals::clean_cookie( sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) ) : '';
		};
		return array(
			'fbc' => $read( '_fbc' ),
			'fbp' => $read( '_fbp' ),
		);
	}

	/**
	 * Recopie sur la commande les identifiants Meta de la visite d'origine.
	 *
	 * @param \WC_Order             $order Commande en cours de création (enregistrée ensuite).
	 * @param array<string, string> $row   Ligne de saisie.
	 * @return void
	 */
	public static function stamp( \WC_Order $order, array $row ): void {
		$signals = array(
			'_famma_fbc'       => Capi_Signals::clean_cookie( (string) ( $row['fbc'] ?? '' ) ),
			'_famma_fbp'       => Capi_Signals::clean_cookie( (string) ( $row['fbp'] ?? '' ) ),
			'_famma_client_ip' => Capi_Signals::clean_ip( self::journal_ip( (string) ( $row['session_id'] ?? '' ) ) ),
		);
		foreach ( $signals as $meta => $value ) {
			if ( '' !== $value ) {
				$order->update_meta_data( $meta, $value );
			}
		}
	}

	/**
	 * Dernière IP en clair de la session dans le journal, ou chaîne vide.
	 *
	 * Vide si l'IP n'est pas conservée (réglage du journal) ou déjà purgée.
	 *
	 * @param string $session_id Session de la saisie.
	 * @return string
	 */
	private static function journal_ip( string $session_id ): string {
		global $wpdb;
		if ( ! Visitor_Context::is_uuid( $session_id ) || ! isset( $wpdb ) ) {
			return '';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, keyed lookup, run once per created order.
		$ip = $wpdb->get_var( $wpdb->prepare( "SELECT ip FROM %i WHERE session_id = %s AND ip <> '' ORDER BY id DESC LIMIT 1", Visitor_Store::instance()->table(), $session_id ) );
		return is_string( $ip ) ? $ip : '';
	}

	/**
	 * Met OrderPlaced en file pour un panier récupéré (règles de `Capi`).
	 *
	 * Appelée au passage hors de « Attente paiement » : à la création par le
	 * bouton (article présent, statut COD initial) ou plus tard, quand
	 * l'équipe a ajouté l'article.
	 *
	 * @param \WC_Order $order Commande enregistrée.
	 * @return string Issue (`queued`, `not_recovered` ou celle consignée par `Capi`).
	 */
	public static function queue( \WC_Order $order ): string {
		if ( ! self::is_recovered( $order ) ) {
			return 'not_recovered';
		}
		return Capi::instance()->requeue_order_placed( $order );
	}

	/**
	 * Un panier récupéré quitte « Attente paiement » : à sa création avec
	 * article (statut COD initial posé par le bouton), ou quand l'équipe a
	 * ajouté l'article. OrderPlaced est mis en file une seule fois.
	 *
	 * @param int    $order_id Commande.
	 * @param string $from     Ancien statut.
	 * @param string $to       Nouveau statut.
	 * @return void
	 */
	public function status_changed( int $order_id, string $from, string $to ): void {
		unset( $to );
		$order = 'pending' === $from ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof \WC_Order || ! self::is_recovered( $order ) || $order->get_meta( '_famma_orderplaced_checkout_accepted' ) ) {
			return;
		}
		self::queue( $order );
	}
}
