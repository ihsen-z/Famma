<?php
/**
 * Avis par lien WhatsApp — le lien personnel d'une commande livrée.
 *
 * Paiement à la livraison : le client n'a ni compte ni, souvent, d'e-mail. Le
 * propriétaire lui envoie après la livraison un lien qui ouvre le formulaire
 * d'avis du produit acheté. Ce fichier ne fait que le lien : le fabriquer, le
 * relire, le consommer. Le formulaire est dans `Review_Form`, le bouton dans
 * `Review_Admin`.
 *
 * Le jeton n'est jamais gardé en clair : la commande ne porte qu'une graine
 * aléatoire, et le jeton est un HMAC de cette graine par le sel du site. Un lien
 * vaut pour une commande Livrée ou Terminée, pendant un nombre de jours réglable,
 * et une seule fois par produit.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lien personnel « Donnez votre avis ».
 */
final class Review_Link {

	/**
	 * Case de FAMMA → Nouveau look → Fiche produit qui active la fonction.
	 */
	public const PART = 'pdp_reviews';

	/**
	 * Réglage : durée de validité du lien, en jours (WooCommerce → Réglages →
	 * Produits, à côté des réglages des avis).
	 */
	public const OPTION_DAYS = 'famma_review_link_days';

	/**
	 * Paramètre d'URL qui porte le lien.
	 */
	public const QUERY = 'famma_avis';

	/**
	 * Méta de commande : graine aléatoire du jeton.
	 */
	private const META_SEED = '_famma_review_seed';

	/**
	 * Méta de commande : date de création du lien (horodatage).
	 */
	private const META_CREATED = '_famma_review_created';

	/**
	 * Méta de commande : produits déjà notés avec ce lien.
	 */
	private const META_DONE = '_famma_review_done';

	/**
	 * Statuts qui ouvrent le lien : la commande a été remise et payée (§2).
	 */
	private const STATUSES = array( 'delivered', 'completed' );

	/**
	 * Commandes déjà vérifiées pendant la requête, par produit.
	 *
	 * @var array<int, \WC_Order|null>
	 */
	private static array $seen = array();

	/**
	 * Accroche la création du lien à la livraison.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		foreach ( self::STATUSES as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( self::class, 'on_delivered' ), 10, 2 );
		}
	}

	/**
	 * Commande passée à Livrée ou Terminée : le délai du lien part de là.
	 *
	 * Seulement avec la case cochée ; une commande livrée avant reçoit son lien
	 * à l'ouverture de sa fiche (`Review_Admin`).
	 *
	 * @param int            $order_id Identifiant de la commande.
	 * @param \WC_Order|null $order    Commande.
	 * @return void
	 */
	public static function on_delivered( int $order_id, $order = null ): void {
		if ( ! self::enabled() ) {
			return;
		}

		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order_id );

		if ( $order instanceof \WC_Order ) {
			self::issue( $order );
		}
	}

	/**
	 * La fonction est-elle activée par le propriétaire ?
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return New_Look::part( self::PART );
	}

	/**
	 * Durée de validité du lien, bornée à 1-365 jours.
	 *
	 * @return int
	 */
	public static function days(): int {
		return max( 1, min( 365, absint( get_option( self::OPTION_DAYS, 30 ) ) ) );
	}

	/**
	 * La commande ouvre-t-elle droit à un avis ?
	 *
	 * @param \WC_Order $order Commande.
	 * @return bool
	 */
	public static function eligible( \WC_Order $order ): bool {
		return $order->has_status( self::STATUSES );
	}

	/**
	 * Crée la graine du lien si elle manque, et la garde sur la commande.
	 *
	 * @param \WC_Order $order Commande.
	 * @return void
	 */
	public static function issue( \WC_Order $order ): void {
		if ( '' !== (string) $order->get_meta( self::META_SEED ) ) {
			return;
		}

		$order->update_meta_data( self::META_SEED, wp_generate_password( 32, false, false ) );
		$order->update_meta_data( self::META_CREATED, time() );
		$order->save_meta_data();
	}

	/**
	 * Date de fin de validité du lien, 0 sans lien.
	 *
	 * @param \WC_Order $order Commande.
	 * @return int Horodatage.
	 */
	public static function expires( \WC_Order $order ): int {
		$created = absint( $order->get_meta( self::META_CREATED ) );

		return $created ? $created + self::days() * DAY_IN_SECONDS : 0;
	}

	/**
	 * Lien d'avis d'un produit de la commande ; vide sans graine.
	 *
	 * @param \WC_Order $order      Commande.
	 * @param int       $product_id Produit (parent pour une variation).
	 * @return string
	 */
	public static function url( \WC_Order $order, int $product_id ): string {
		$token = self::token( $order );
		$url   = get_permalink( $product_id );

		if ( '' === $token || ! $url ) {
			return '';
		}

		return add_query_arg( self::QUERY, $order->get_id() . '-' . $token, $url ) . '#reviews';
	}

	/**
	 * Le produit a-t-il déjà été noté avec ce lien ?
	 *
	 * @param \WC_Order $order      Commande.
	 * @param int       $product_id Produit.
	 * @return bool
	 */
	public static function done( \WC_Order $order, int $product_id ): bool {
		return in_array( $product_id, self::done_ids( $order ), true );
	}

	/**
	 * Produits déjà notés avec le lien (méta absente : liste vide).
	 *
	 * @param \WC_Order $order Commande.
	 * @return array<int, int>
	 */
	private static function done_ids( \WC_Order $order ): array {
		$done = $order->get_meta( self::META_DONE );

		return is_array( $done ) ? array_values( array_filter( array_map( 'absint', $done ) ) ) : array();
	}

	/**
	 * Note le produit comme noté : le lien ne sert plus pour lui.
	 *
	 * @param \WC_Order $order      Commande.
	 * @param int       $product_id Produit.
	 * @return void
	 */
	public static function mark_done( \WC_Order $order, int $product_id ): void {
		$done   = self::done_ids( $order );
		$done[] = $product_id;
		$order->update_meta_data( self::META_DONE, array_values( array_unique( $done ) ) );
		$order->save_meta_data();
	}

	/**
	 * Produits distincts de la commande (parents des variations).
	 *
	 * @param \WC_Order $order Commande.
	 * @return array<int, int>
	 */
	public static function products( \WC_Order $order ): array {
		$ids = array();

		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product && $item->get_product_id() ) {
				$ids[] = $item->get_product_id();
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Le lien de la requête (adresse ou formulaire envoyé) vaut-il pour ce produit ?
	 *
	 * @param int $product_id Produit.
	 * @return \WC_Order|null La commande du lien, ou null.
	 */
	public static function from_request( int $product_id ): ?\WC_Order {
		if ( array_key_exists( $product_id, self::$seen ) ) {
			return self::$seen[ $product_id ];
		}

		// Lecture seule d'un jeton signé : il fait office de preuve, comme une clé de réinitialisation.
		$raw = isset( $_REQUEST[ self::QUERY ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ self::QUERY ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		self::$seen[ $product_id ] = '' === $raw ? null : self::verify( $raw, $product_id );

		return self::$seen[ $product_id ];
	}

	/**
	 * Un lien est-il présent dans la requête, valable ou non ?
	 *
	 * @return bool
	 */
	public static function in_request(): bool {
		return isset( $_REQUEST[ self::QUERY ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Valeur brute du lien, à reporter dans le formulaire.
	 *
	 * @param \WC_Order $order Commande.
	 * @return string
	 */
	public static function value( \WC_Order $order ): string {
		return $order->get_id() . '-' . self::token( $order );
	}

	/**
	 * Vérifie un lien pour un produit.
	 *
	 * @param string $raw        « idcommande-jeton ».
	 * @param int    $product_id Produit.
	 * @return \WC_Order|null
	 */
	private static function verify( string $raw, int $product_id ): ?\WC_Order {
		if ( 1 !== preg_match( '/^(\d+)-([a-f0-9]{40})$/', $raw, $m ) ) {
			return null;
		}

		$order = wc_get_order( absint( $m[1] ) );

		if ( ! $order instanceof \WC_Order || ! self::eligible( $order ) ) {
			return null;
		}

		$token = self::token( $order );

		if ( '' === $token || ! hash_equals( $token, $m[2] ) || time() > self::expires( $order ) ) {
			return null;
		}

		if ( ! in_array( $product_id, self::products( $order ), true ) || self::done( $order, $product_id ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Jeton de la commande, recalculé depuis sa graine ; vide sans graine.
	 *
	 * @param \WC_Order $order Commande.
	 * @return string 40 caractères hexadécimaux.
	 */
	private static function token( \WC_Order $order ): string {
		$seed = (string) $order->get_meta( self::META_SEED );

		return '' === $seed ? '' : substr( hash_hmac( 'sha256', $order->get_id() . '|' . $seed, wp_salt( 'auth' ) ), 0, 40 );
	}
}
