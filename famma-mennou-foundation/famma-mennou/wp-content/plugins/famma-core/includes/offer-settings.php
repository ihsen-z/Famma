<?php
/**
 * Offre après achat : réglages d'un produit et règles d'éligibilité.
 *
 * Partagé par l'administration (`Offer_Admin`) et la page de remerciement
 * (`Offer_Thankyou`). Un produit acheté (par exemple le masque V-Line) peut
 * proposer UN autre produit (les chaussettes gel), à un prix spécial, juste
 * après la commande et tant qu'elle n'est pas confirmée par téléphone.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lecture des réglages et décision « l'offre peut-elle être montrée ? ».
 */
final class Offer_Settings {

	/** Offre active sur ce produit (`yes` / `no`). */
	public const META_ENABLED = '_famma_offer_enabled';

	/** Identifiant du produit proposé. */
	public const META_PRODUCT = '_famma_offer_product_id';

	/** Prix spécial du produit proposé, en dinars. */
	public const META_PRICE = '_famma_offer_price';

	/** Titre du bloc. */
	public const META_TITLE = '_famma_offer_title';

	/** Texte du bloc. */
	public const META_TEXT = '_famma_offer_text';

	/** Fiche rapide « Découvrir le produit » (`no` pour la masquer). */
	public const META_SHEET = '_famma_offer_sheet';

	/** Points forts de la fiche rapide : un par ligne, « français | derja ». */
	public const META_POINTS = '_famma_offer_points';

	/** Mode d'emploi court de la fiche rapide. */
	public const META_USAGE = '_famma_offer_usage';

	/** Nombre maximal de points forts affichés. */
	public const MAX_POINTS = 3;

	/** Nombre maximal de photos dans la fiche rapide. */
	public const MAX_PHOTOS = 5;

	/** Commande : horodatage du premier affichage de l'offre. */
	public const ORDER_SHOWN = '_famma_offer_shown_at';

	/** Commande : horodatage de l'acceptation. */
	public const ORDER_ACCEPTED = '_famma_offer_accepted_at';

	/** Commande : horodatage de la première ouverture de la fiche rapide. */
	public const ORDER_VIEWED = '_famma_offer_viewed_at';

	/** Commande : horodatage du refus (« Non merci »). */
	public const ORDER_DECLINED = '_famma_offer_declined_at';

	/** Commande : montant ajouté par l'offre. */
	public const ORDER_AMOUNT = '_famma_offer_amount';

	/** Commande : produit déclencheur de l'offre. */
	public const ORDER_SOURCE = '_famma_offer_source_id';

	/** Commande : produit ajouté par l'offre. */
	public const ORDER_TARGET = '_famma_offer_target_id';

	/** Ligne de commande : marque la ligne ajoutée par l'offre. */
	public const ITEM_FLAG = '_famma_offer_item';

	/** Seul statut où l'offre reste ouverte. */
	public const OPEN_STATUS = 'pending-confirm';

	/**
	 * Réglages d'un produit, ou null si aucune offre n'est active.
	 *
	 * @param \WC_Product $product Produit acheté.
	 * @return array{source: int, target: int, price: float, title: string, text: string, sheet: bool, points: string, usage: string}|null
	 */
	public static function for_product( \WC_Product $product ): ?array {
		if ( 'yes' !== (string) $product->get_meta( self::META_ENABLED ) ) {
			return null;
		}

		$target = absint( $product->get_meta( self::META_PRODUCT ) );
		$price  = (float) $product->get_meta( self::META_PRICE );

		if ( $target <= 0 || $target === $product->get_id() || $price <= 0 ) {
			return null;
		}

		return array(
			'source' => $product->get_id(),
			'target' => $target,
			'price'  => round( $price, 3 ),
			'title'  => (string) $product->get_meta( self::META_TITLE ),
			'text'   => (string) $product->get_meta( self::META_TEXT ),
			'sheet'  => 'no' !== (string) $product->get_meta( self::META_SHEET ),
			'points' => (string) $product->get_meta( self::META_POINTS ),
			'usage'  => (string) $product->get_meta( self::META_USAGE ),
		);
	}

	/**
	 * Offre à montrer pour une commande, ou null.
	 *
	 * Conditions : commande encore « En attente de confirmation », offre ni
	 * acceptée ni refusée, un article de la commande porte une offre active,
	 * le produit proposé est publié, achetable, en stock, et pas déjà dans la
	 * commande.
	 *
	 * @param \WC_Order $order Commande.
	 * @return array{source: int, target: int, price: float, title: string, text: string, sheet: bool, points: string, usage: string, product: \WC_Product}|null
	 */
	public static function for_order( \WC_Order $order ): ?array {
		if ( ! $order->has_status( self::OPEN_STATUS ) ) {
			return null;
		}
		if ( $order->get_meta( self::ORDER_ACCEPTED ) || $order->get_meta( self::ORDER_DECLINED ) ) {
			return null;
		}

		$in_order = array();
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product ) {
				$in_order[] = (int) $item->get_product_id();
			}
		}

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			// Une variation porte ses réglages sur le produit parent.
			$owner = $product->get_parent_id() > 0 ? wc_get_product( $product->get_parent_id() ) : $product;
			if ( ! $owner instanceof \WC_Product ) {
				continue;
			}
			$offer = self::for_product( $owner );
			if ( null === $offer || in_array( $offer['target'], $in_order, true ) ) {
				continue;
			}
			$target = wc_get_product( $offer['target'] );
			if ( ! self::offerable( $target ) ) {
				continue;
			}
			$offer['product'] = $target;
			return $offer;
		}

		return null;
	}

	/**
	 * Le produit proposé peut-il être ajouté tel quel ?
	 *
	 * Seuls les produits simples sont acceptés : un produit à tailles ou à
	 * variations demanderait un choix, donc plus d'un clic.
	 *
	 * @param mixed $product Produit proposé.
	 * @return bool
	 */
	public static function offerable( $product ): bool {
		return $product instanceof \WC_Product
			&& $product->is_type( 'simple' )
			&& 'publish' === $product->get_status()
			&& $product->is_purchasable()
			&& $product->is_in_stock()
			&& '' === trim( (string) $product->get_meta( Product_Sizes::META ) );
	}

	/**
	 * Points forts saisis : au plus trois, chacun en français avec sa
	 * traduction en derja facultative après « | ».
	 *
	 * @param string $raw Texte saisi dans l'onglet, un point par ligne.
	 * @return array<int, array{fr: string, ar: string}>
	 */
	public static function points( string $raw ): array {
		$out = array();
		// Fins de ligne seulement : sans le drapeau u, « \R » couperait les lettres
		// arabes dont un octet vaut 0x85 (ex. « م »).
		foreach ( preg_split( '/\r\n|\r|\n/u', $raw ) as $line ) {
			$parts = array_map( 'trim', explode( '|', (string) $line, 2 ) );
			if ( '' === $parts[0] ) {
				continue;
			}
			$out[] = array(
				'fr' => $parts[0],
				'ar' => $parts[1] ?? '',
			);
			if ( count( $out ) >= self::MAX_POINTS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Statistiques des 30 derniers jours pour un produit déclencheur.
	 *
	 * Calculées depuis les commandes (méta `_famma_offer_*`), mises en cache
	 * dix minutes (règle 10).
	 *
	 * @param int $source_id Produit déclencheur.
	 * @return array{shown: int, viewed: int, accepted: int, viewed_accepted: int, declined: int, amount: float}
	 */
	public static function stats( int $source_id ): array {
		$key    = 'famma_offer_stats2_' . $source_id;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$out = array(
			'shown'           => 0,
			'viewed'          => 0,
			'accepted'        => 0,
			'viewed_accepted' => 0,
			'declined'        => 0,
			'amount'          => 0.0,
		);

		$ids = wc_get_orders(
			array(
				'limit'        => 500,
				'return'       => 'ids',
				'date_created' => '>' . ( time() - 30 * DAY_IN_SECONDS ),
				'meta_key'     => self::ORDER_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- borné à 500 commandes de 30 jours, mis en cache.
				'meta_value'   => (string) $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- idem.
			)
		);

		foreach ( (array) $ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order instanceof \WC_Order || $order->get_meta( '_famma_test_order' ) ) {
				continue;
			}
			if ( $order->get_meta( self::ORDER_SHOWN ) ) {
				++$out['shown'];
			}
			$viewed = (bool) $order->get_meta( self::ORDER_VIEWED );
			if ( $viewed ) {
				++$out['viewed'];
			}
			if ( $order->get_meta( self::ORDER_ACCEPTED ) ) {
				++$out['accepted'];
				$out['amount'] += (float) $order->get_meta( self::ORDER_AMOUNT );
				if ( $viewed ) {
					++$out['viewed_accepted'];
				}
			}
			if ( $order->get_meta( self::ORDER_DECLINED ) ) {
				++$out['declined'];
			}
		}

		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Vide le cache des statistiques d'un produit déclencheur.
	 *
	 * @param int $source_id Produit déclencheur.
	 * @return void
	 */
	public static function flush_stats( int $source_id ): void {
		delete_transient( 'famma_offer_stats2_' . $source_id );
	}
}
