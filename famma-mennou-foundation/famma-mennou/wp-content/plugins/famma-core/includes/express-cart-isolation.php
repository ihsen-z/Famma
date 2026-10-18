<?php
/**
 * Commande express isolée : seul le produit de la fiche est commandé.
 *
 * `WC_Checkout::process_checkout()` commande tout le panier. Un client qui
 * avait déjà un autre article au panier voyait donc un total différent de
 * celui de la fiche, puis un récapitulatif inattendu. Pendant
 * `famma_express_prepare` et `famma_express_checkout`, les lignes du panier
 * sont mises de côté, le panier ne contient que le produit de la fiche à la
 * quantité choisie, et le panier d'origine est remis en place ensuite.
 *
 * ── Restauration ──────────────────────────────────────────────────────────
 *
 * - `prepare` restaure explicitement avant d'enregistrer la session et de
 *   répondre : il ne laisse aucune trace au panier.
 * - `checkout` se termine par `wp_send_json()` / `wp_die()` dans
 *   WooCommerce : la restauration est accrochée à `shutdown` à la priorité
 *   -1000, AVANT `WC_Cart_Session::maybe_set_cart_cookies()` (priorité 0) et
 *   `WC_Session_Handler::save_data()` (priorité 20) — vérifié dans la source
 *   de WooCommerce 11.0.1. La session enregistrée contient donc le panier
 *   restauré, que la commande ait réussi ou échoué.
 * - Succès : la passerelle COD (`WC_Gateway_COD::process_payment()`) et la
 *   commande sans paiement (`wc_empty_cart()`) vident le panier, ce qui
 *   déclenche `woocommerce_cart_emptied`. Dans ce cas, seules les AUTRES
 *   lignes sont remises ; la ligne du produit commandé ne revient pas. Sans
 *   vidage (échec, validation refusée, exception), le panier d'origine
 *   revient à l'identique : mêmes clés, produits, variations, attributs,
 *   données de ligne et quantités.
 * - Codes promo : retirés du panier isolé (la commande express est au prix
 *   de la fiche, aucun code n'y est consommé), remis dans les deux cas.
 * - Le panier persistant des clients connectés (méta utilisateur), réécrit
 *   par `add_to_cart()` et effacé par `empty_cart()`, est réécrit avec le
 *   panier restauré, comme le fait `WC_Cart_Session::persistent_cart_update()`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Met de côté puis restaure les lignes du panier autour d'une commande express.
 */
final class Express_Cart_Isolation {

	/**
	 * Instance partagée.
	 *
	 * @var Express_Cart_Isolation|null
	 */
	private static ?Express_Cart_Isolation $i = null;

	/**
	 * Isolation en cours dans cette requête.
	 *
	 * @var bool
	 */
	private bool $active = false;

	/**
	 * Le panier a été vidé par WooCommerce pendant l'isolation (commande passée).
	 *
	 * @var bool
	 */
	private bool $emptied = false;

	/**
	 * Lignes d'origine, avec leurs clés et leurs objets produit.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $original = array();

	/**
	 * Codes promo appliqués à l'origine.
	 *
	 * @var string[]
	 */
	private array $coupons = array();

	/**
	 * Produit commandé.
	 *
	 * @var int
	 */
	private int $product_id = 0;

	/**
	 * Taille de la ligne commandée (chaîne vide : produit sans taille).
	 *
	 * @var string
	 */
	private string $size = '';

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Express_Cart_Isolation
	 */
	public static function instance(): Express_Cart_Isolation {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'woocommerce_cart_emptied', array( self::$i, 'mark_emptied' ) );
			add_action( 'shutdown', array( self::$i, 'restore' ), -1000 );
			add_filter( 'woocommerce_should_clear_cart_after_payment', array( self::$i, 'keep_cart_after_express' ) );
		}
		return self::$i;
	}

	/**
	 * Page « commande reçue » d'une commande express : le panier n'est pas vidé.
	 *
	 * `wc_clear_cart_after_payment()` (template_redirect) vide le panier quand
	 * l'URL porte la clé d'une commande. Une commande express ne contient que le
	 * produit de la fiche, et sa ligne n'a jamais été remise au panier : ce
	 * qui reste au panier, ce sont les AUTRES articles du client, à garder.
	 * WooCommerce 11 les garde déjà (empreinte de panier différente de celle de
	 * la commande) ; ce filtre ne dépend pas de ce détail.
	 *
	 * @param bool $clear Décision de WooCommerce.
	 * @return bool
	 */
	public function keep_cart_after_express( $clear ): bool {
		global $wp;

		if ( ! $clear || empty( $wp->query_vars['order-received'] ) ) {
			return (bool) $clear;
		}
		$order = wc_get_order( absint( $wp->query_vars['order-received'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- clé de commande lue comme WooCommerce, comparée en temps constant, aucune écriture.
		$key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';

		if ( $order instanceof \WC_Order && is_string( $key ) && hash_equals( $order->get_order_key(), $key ) && '' !== (string) $order->get_meta( '_famma_express_request_id' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Indique si le panier est actuellement isolé.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->active;
	}

	/**
	 * Ne laisse au panier que le produit, à la quantité demandée.
	 *
	 * Le filtre `woocommerce_add_to_cart_validation` est appliqué comme le fait
	 * `WC_AJAX::add_to_cart()` : plafond d'`Anti_Abuse`, stock et contrôles de
	 * WooCommerce restent en vigueur. En cas de refus, l'avis WooCommerce est
	 * posé et le panier d'origine sera restauré par l'appelant (ou à
	 * `shutdown`).
	 *
	 * @param int   $product_id Produit de la fiche (simple).
	 * @param float $quantity   Quantité choisie.
	 * @return bool
	 */
	public function isolate( int $product_id, float $quantity ): bool {
		$cart = WC()->cart;

		if ( $this->active ) {
			$this->restore();
		}

		$this->original   = $cart->get_cart();
		$this->coupons    = $cart->get_applied_coupons();
		$this->product_id = $product_id;
		$this->size       = '';
		$this->emptied    = false;
		$this->active     = true;

		$cart->set_cart_contents( array() );
		// Les codes promo du panier ne s'appliquent pas à la commande express (prix de la fiche) : mis de côté, remis par restore().
		$cart->set_applied_coupons( array() );

		/** This filter is documented in woocommerce/includes/class-wc-ajax.php */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- filtre natif de WooCommerce, appliqué comme WC_AJAX::add_to_cart().
		$passed = (bool) apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );

		$key = $passed ? $cart->add_to_cart( $product_id, $quantity ) : false;

		if ( ! is_string( $key ) || '' === $key ) {
			return false;
		}
		// Taille contrôlée par `Product_Sizes::cart_item_data()` : seule la ligne de cette taille est commandée.
		$this->size = (string) ( $cart->cart_contents[ $key ][ Product_Sizes::FIELD ] ?? '' );

		return true;
	}

	/**
	 * Quantité du produit déjà présente dans le panier d'origine.
	 *
	 * @return float
	 */
	public function previous_quantity(): float {
		$quantity = 0.0;
		foreach ( $this->original as $item ) {
			if ( $this->is_ordered_line( $item ) ) {
				$quantity += (float) $item['quantity'];
			}
		}
		return $quantity;
	}

	/**
	 * Nombre d'articles (quantités) laissés au panier par une commande réussie.
	 *
	 * @return int
	 */
	public function other_items(): int {
		$count = 0.0;
		foreach ( $this->others() as $item ) {
			$count += (float) $item['quantity'];
		}
		return (int) round( $count );
	}

	/**
	 * Note que WooCommerce a vidé le panier isolé : la commande est passée.
	 *
	 * @return void
	 */
	public function mark_emptied(): void {
		if ( $this->active ) {
			$this->emptied = true;
		}
	}

	/**
	 * Remet le panier d'origine (sans la ligne commandée si la commande est passée).
	 *
	 * Idempotent : sans isolation en cours, ne fait rien. Appelé
	 * explicitement par `prepare`, et à `shutdown` pour tous les autres cas.
	 *
	 * @return void
	 */
	public function restore(): void {
		if ( ! $this->active ) {
			return;
		}
		$this->active = false;

		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
			return;
		}
		$cart = WC()->cart;

		$cart->set_cart_contents( $this->emptied ? $this->others() : $this->original );
		$cart->set_applied_coupons( $this->coupons );

		// Recalcule les totaux, puis WC_Cart_Session::set_session() (priorité 1000) remet le panier en session.
		$cart->calculate_totals();
		$this->persist();
		$this->sync_cart_cookies();

		$this->original = array();
		$this->coupons  = array();
	}

	/**
	 * Remet les cookies de panier en accord avec le panier restauré.
	 *
	 * `add_to_cart()` sur le panier isolé pose `woocommerce_items_in_cart` et
	 * `woocommerce_cart_hash` (empreinte du panier isolé). La synchronisation
	 * de WooCommerce à `shutdown` n'a plus lieu si la réponse JSON a déjà
	 * envoyé les en-têtes (`prepare`) : elle est faite ici, même règle que
	 * `WC_Cart_Session::set_cart_cookies()`.
	 *
	 * @return void
	 */
	private function sync_cart_cookies(): void {
		if ( headers_sent() ) {
			return;
		}
		$cart = WC()->cart;

		if ( ! $cart->is_empty() ) {
			$values = array(
				'woocommerce_items_in_cart' => '1',
				'woocommerce_cart_hash'     => $cart->get_cart_hash(),
			);
			foreach ( $values as $name => $value ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- comparaison stricte seulement, comme WooCommerce.
				if ( ! isset( $_COOKIE[ $name ] ) || $_COOKIE[ $name ] !== $value ) {
					wc_setcookie( $name, $value );
					$_COOKIE[ $name ] = $value;
				}
			}
			return;
		}
		foreach ( array( 'woocommerce_items_in_cart', 'woocommerce_cart_hash' ) as $name ) {
			if ( isset( $_COOKIE[ $name ] ) ) {
				wc_setcookie( $name, '0', time() - HOUR_IN_SECONDS );
				unset( $_COOKIE[ $name ] );
			}
		}
	}

	/**
	 * Lignes d'origine autres que le produit commandé.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function others(): array {
		return array_filter(
			$this->original,
			function ( $item ): bool {
				return ! $this->is_ordered_line( $item );
			}
		);
	}

	/**
	 * La ligne est-elle celle du produit commandé (produit simple, même taille) ?
	 *
	 * @param mixed $item Ligne du panier.
	 * @return bool
	 */
	private function is_ordered_line( $item ): bool {
		return is_array( $item ) && isset( $item['product_id'] ) && (int) $item['product_id'] === $this->product_id && empty( $item['variation_id'] )
			&& (string) ( $item[ Product_Sizes::FIELD ] ?? '' ) === $this->size;
	}

	/**
	 * Réécrit le panier persistant d'un client connecté.
	 *
	 * Même règle que `WC_Cart_Session::persistent_cart_update()` (méthode non
	 * accessible : `WC_Cart::$session` est protégé).
	 *
	 * @return void
	 */
	private function persist(): void {
		$user_id = get_current_user_id();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- filtre natif de WooCommerce.
		if ( ! $user_id || ! apply_filters( 'woocommerce_persistent_cart_enabled', true ) ) {
			return;
		}
		$key  = '_woocommerce_persistent_cart_' . get_current_blog_id();
		$cart = WC()->session->get( 'cart' );

		if ( empty( $cart ) ) {
			delete_user_meta( $user_id, $key );
			return;
		}
		update_user_meta( $user_id, $key, array( 'cart' => $cart ) );
	}
}
