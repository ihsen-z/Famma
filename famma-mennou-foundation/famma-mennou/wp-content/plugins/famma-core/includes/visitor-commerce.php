<?php
/**
 * Parcours visiteurs — événements serveur du tunnel et lien à la commande.
 *
 * L'ajout au panier et la commande sont enregistrés côté serveur, là où ils
 * sont certains : un clic sur « Ajouter » n'est pas un ajout (stock, variation
 * incomplète), et une soumission de formulaire n'est pas une commande
 * (validation refusée). Même raisonnement que `Tracking_Events`.
 *
 * Les deux chemins de commande — checkout classique et formulaire express —
 * passent par `WC_Checkout::process_checkout()` : les mêmes crochets couvrent
 * donc les deux.
 *
 * La commande reçoit `_famma_visitor_id` et `_famma_visitor_session` : depuis
 * la fiche commande, on retrouve le parcours qui l'a précédée (choix du
 * propriétaire : parcours rattaché à la commande).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Ajouts au panier, commandes passées, rattachement visiteur → commande.
 */
final class Visitor_Commerce {

	/**
	 * Méta commande : identifiant visiteur.
	 */
	public const META_VISITOR = '_famma_visitor_id';

	/**
	 * Méta commande : identifiant de session.
	 */
	public const META_SESSION = '_famma_visitor_session';

	/**
	 * Instance partagée.
	 *
	 * @var Visitor_Commerce|null
	 */
	private static ?Visitor_Commerce $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Visitor_Commerce
	 */
	public static function instance(): Visitor_Commerce {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les points d'accroche.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'woocommerce_add_to_cart', array( $this, 'add_to_cart' ), 20, 4 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_order' ), 32 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'order_placed' ), 20, 3 );
	}

	/**
	 * Identifiants visiteur et session de la requête, ou null s'il ne faut rien
	 * enregistrer (pas de cookie, robot, équipe).
	 *
	 * @return array{0: string, 1: string}|null
	 */
	private function ids(): ?array {
		if ( Visitor_Context::is_staff() || Visitor_Context::is_bot() ) {
			return null;
		}
		$visitor = Visitor_Context::cookie_id( Visitor_Context::VISITOR_COOKIE );
		$session = Visitor_Context::cookie_id( Visitor_Context::SESSION_COOKIE );
		return ( '' === $visitor || '' === $session ) ? null : array( $visitor, $session );
	}

	/**
	 * Écrit un événement serveur.
	 *
	 * @param string               $event Nom de l'événement.
	 * @param array<string, mixed> $data  Colonnes propres à l'événement.
	 * @return void
	 */
	private function record( string $event, array $data ): void {
		$ids = $this->ids();
		if ( null === $ids ) {
			return;
		}
		Visitor_Store::instance()->insert_many(
			array(
				$data + array(
					'created_at' => gmdate( 'Y-m-d H:i:s' ),
					'visitor_id' => $ids[0],
					'session_id' => $ids[1],
					'event'      => $event,
					'device'     => Visitor_Context::device(),
					'ip_hash'    => Visitor_Context::ip_hash(),
				),
			)
		);
	}

	/**
	 * Ajout au panier effectivement réalisé.
	 *
	 * @param string $key        Clé de ligne du panier (inutilisée).
	 * @param int    $product_id Produit parent.
	 * @param int    $quantity   Quantité ajoutée.
	 * @param int    $variation  Variation éventuelle.
	 * @return void
	 */
	public function add_to_cart( $key, $product_id, $quantity, $variation ): void {
		unset( $key );
		$this->record(
			'add_to_cart',
			array(
				'product_id' => absint( $product_id ),
				'num'        => absint( $quantity ),
				'label'      => $variation ? (string) absint( $variation ) : '',
			)
		);
	}

	/**
	 * Rattache le visiteur à la commande, avant sa première sauvegarde.
	 *
	 * @param \WC_Order $order Commande en cours de création.
	 * @return void
	 */
	public function stamp_order( \WC_Order $order ): void {
		$ids = $this->ids();
		if ( null === $ids ) {
			return;
		}
		$order->update_meta_data( self::META_VISITOR, $ids[0] );
		$order->update_meta_data( self::META_SESSION, $ids[1] );
	}

	/**
	 * Commande passée : dernière marche de l'entonnoir.
	 *
	 * @param int       $order_id Identifiant de commande.
	 * @param array     $posted   Données postées (inutilisées).
	 * @param \WC_Order $order    Commande.
	 * @return void
	 */
	public function order_placed( $order_id, $posted, $order ): void {
		unset( $posted );
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$this->record(
			'order_placed',
			array(
				'order_id' => $order->get_id(),
				'num'      => (int) round( (float) $order->get_total() ),
				'label'    => (string) $order->get_item_count(),
			)
		);
	}
}
