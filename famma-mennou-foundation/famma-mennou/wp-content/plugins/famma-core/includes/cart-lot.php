<?php
/**
 * Panier, étape 4 du lot « Panier » : « Passer au lot » (brique `cart_lot`).
 *
 * Le couple se règle produit par produit dans l'onglet « Fiche FAMMA »
 * (`Cart_Lot_Admin`) : produit lot et nombre d'articles qu'il contient.
 * Rien n'est écrit en dur : sans couple, pas de proposition (§60). Les
 * montants viennent toujours des prix du catalogue ; un lot qui ne fait pas
 * gagner n'est jamais proposé.
 *
 * Calcul de la maquette du 14/11 : une ligne de 5 articles avec un lot de 2
 * propose 2 lots, le 5e article reste seul.
 *
 * L'échange est un lien protégé par le jeton du panier de WooCommerce, comme
 * la corbeille de chaque ligne : il marche sans script, et la touche Entrée
 * d'un champ du panier ne peut pas le déclencher (ce que ferait un bouton
 * d'envoi placé avant « Mettre à jour le panier »). Il
 * n'envoie ni AddToCart à Meta ni « ajout au panier » au journal des visites
 * (décision D2 du plan : ces articles étaient déjà dans le panier).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lecture du couple, calcul et échange.
 */
final class Cart_Lot {

	/**
	 * Produit lot (identifiant), sur le produit de départ.
	 *
	 * @var string
	 */
	public const META_PRODUCT = '_famma_lot_product';

	/**
	 * Nombre d'articles dans le lot (2 ou plus).
	 *
	 * @var string
	 */
	public const META_SIZE = '_famma_lot_size';

	/**
	 * Lien d'échange d'une ligne.
	 *
	 * @param string $key Clé de la ligne.
	 * @param int    $qty Quantité affichée au client.
	 * @return string
	 */
	public static function url( string $key, int $qty ): string {
		return add_query_arg(
			array(
				self::FIELD => $key . ':' . $qty,
				'_wpnonce'  => wp_create_nonce( 'woocommerce-cart' ),
			),
			wc_get_cart_url()
		);
	}

	/**
	 * Phrase propre au produit, une méta par langue : `_famma_lot_text_<fr|ar>`.
	 *
	 * @var string
	 */
	public const META_TEXT = '_famma_lot_text_';

	/**
	 * Paramètre du lien d'échange ; valeur `<clé de ligne>:<quantité affichée>`.
	 *
	 * @var string
	 */
	public const FIELD = 'famma_lot';

	/**
	 * Pose les hooks (site seulement).
	 *
	 * Priorité 15 sur `wp_loaded` : avant le traitement des liens du panier
	 * par WooCommerce (20), qui ignore ce paramètre.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_loaded', array( self::class, 'handle' ), 15 );
	}

	/**
	 * Lot du produit de départ, ou null.
	 *
	 * @param \WC_Product $source Produit de départ (le parent pour une variante).
	 * @return array{lot: \WC_Product, size: int}|null
	 */
	public static function target( \WC_Product $source ): ?array {
		$id   = (int) $source->get_meta( self::META_PRODUCT );
		$size = (int) $source->get_meta( self::META_SIZE );

		if ( $id <= 0 || $size < 2 || $id === $source->get_id() ) {
			return null;
		}

		$lot = wc_get_product( $id );

		return $lot instanceof \WC_Product && self::usable( $lot ) ? array(
			'lot'  => $lot,
			'size' => $size,
		) : null;
	}

	/**
	 * Le lot peut-il être proposé : produit simple, publié, achetable et en
	 * stock. Il peut être caché de la boutique.
	 *
	 * @param \WC_Product $lot Produit lot.
	 * @return bool
	 */
	public static function usable( \WC_Product $lot ): bool {
		return $lot->is_type( 'simple' ) && 'publish' === $lot->get_status() && $lot->is_purchasable() && $lot->is_in_stock();
	}

	/**
	 * Proposition pour une ligne du panier, ou tableau vide.
	 *
	 * @param array<string, mixed> $item Ligne du panier.
	 * @return array{lot: \WC_Product, size: int, count: int, lot_price: float, unit_total: float, gain: float}|array{}
	 */
	public static function offer( array $item ): array {
		$line   = $item['data'] ?? null;
		$qty    = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
		$source = isset( $item['product_id'] ) ? wc_get_product( absint( $item['product_id'] ) ) : null;

		if ( ! $line instanceof \WC_Product || ! $source instanceof \WC_Product ) {
			return array();
		}

		$target = self::target( $source );

		if ( null === $target || $target['lot']->get_id() === $line->get_id() ) {
			return array();
		}

		$count = intdiv( $qty, $target['size'] );

		if ( $count < 1 || ! $target['lot']->has_enough_stock( $count ) ) {
			return array();
		}

		$decimals   = wc_get_price_decimals();
		$lot_price  = round( (float) wc_get_price_to_display( $target['lot'] ), $decimals );
		$unit_total = round( (float) wc_get_price_to_display( $line ) * $target['size'], $decimals );
		$gain       = round( ( $unit_total - $lot_price ) * $count, $decimals );

		if ( $lot_price <= 0 || $gain <= 0 ) {
			return array();
		}

		return array(
			'lot'        => $target['lot'],
			'size'       => $target['size'],
			'count'      => $count,
			'lot_price'  => $lot_price,
			'unit_total' => $unit_total,
			'gain'       => $gain,
		);
	}

	/**
	 * Phrase propre au produit dans la langue de la page, sinon dans l'autre,
	 * sinon chaîne vide (le thème prend alors celle de l'onglet « Panier »).
	 *
	 * @param int $product_id Produit de départ.
	 * @return string
	 */
	public static function text( int $product_id ): string {
		$source = wc_get_product( $product_id );

		if ( ! $source instanceof \WC_Product ) {
			return '';
		}

		$page = is_rtl() ? 'ar' : 'fr';

		foreach ( array( $page, 'ar' === $page ? 'fr' : 'ar' ) as $lang ) {
			$text = trim( (string) $source->get_meta( self::META_TEXT . $lang ) );

			if ( '' !== $text ) {
				return $text;
			}
		}

		return '';
	}

	/**
	 * Échange : ajoute les lots, retire les articles qu'ils remplacent.
	 *
	 * Contrôles : brique cochée, jeton du panier, ligne présente avec la
	 * quantité affichée au client (un envoi rejoué après l'échange ne trouve
	 * plus cette quantité et ne fait rien), lot qui fait toujours gagner. Le
	 * client agit sur son propre panier : aucune capacité n'est demandée.
	 *
	 * @return void
	 */
	public static function handle(): void {
		if ( ! isset( $_GET[ self::FIELD ] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! New_Look::part( 'cart_lot' ) || ! wp_verify_nonce( $nonce, 'woocommerce-cart' ) ) {
			return;
		}

		$value = sanitize_text_field( wp_unslash( $_GET[ self::FIELD ] ) );
		$parts = explode( ':', $value );
		$key   = sanitize_key( $parts[0] );
		$shown = isset( $parts[1] ) ? absint( $parts[1] ) : 0;
		$item  = '' !== $key ? WC()->cart->get_cart_item( $key ) : array();
		$offer = array() !== $item && (int) $item['quantity'] === $shown ? self::offer( $item ) : array();

		if ( array() === $offer ) {
			wc_add_notice( __( 'Ce lot n’est plus disponible pour cet article.', 'famma-core' ), 'notice' );
			self::back();
		}

		add_filter( 'famma_cart_add_counts', '__return_false' );
		$added = WC()->cart->add_to_cart( $offer['lot']->get_id(), $offer['count'] );
		remove_filter( 'famma_cart_add_counts', '__return_false' );

		if ( false === $added ) {
			self::back();
		}

		WC()->cart->set_quantity( $key, $shown - $offer['count'] * $offer['size'] );

		$done = Cart_Content::text( 'lot_done' );

		if ( '' !== $done ) {
			wc_add_notice( str_replace( '{m}', wp_strip_all_tags( wc_price( $offer['gain'] ) ), $done ), 'success' );
		}

		self::back();
	}

	/**
	 * Retour au panier, sans le paramètre (recharger la page ne rejoue rien).
	 *
	 * @return void
	 */
	private static function back(): void {
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}
}
