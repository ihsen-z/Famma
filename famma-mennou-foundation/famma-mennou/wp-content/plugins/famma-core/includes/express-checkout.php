<?php
/**
 * Commande express en une seule requête (audit performance, PERF-03).
 *
 * Avant : un clic sur « Commander » enchaînait trois démarrages de PHP non
 * cachables — `?wc-ajax=add_to_cart`, `?wc-ajax=famma_express_nonce`, puis
 * `?wc-ajax=checkout`. Après : le jeton est demandé pendant que le client
 * remplit le formulaire, et le clic n'envoie plus qu'un seul appel,
 * `?wc-ajax=famma_express_checkout`, qui met le produit au panier puis exécute
 * le checkout de WooCommerce.
 *
 * ── Pourquoi la commande passe toujours par `process_checkout()` ───────────
 *
 * Ce point d'entrée ne crée pas la commande lui-même : il appelle
 * `WC_Checkout::process_checkout()`, exactement comme `WC_AJAX::checkout()`.
 * Validation tunisienne, stock, statut COD initial, UTM, signaux CAPI, coût de
 * livraison interne et garde-fous `Anti_Abuse` (piège, limites par connexion
 * et par téléphone, plafond de quantité) s'appliquent donc sans changement.
 *
 * ── Jeton et protection CSRF : le choix retenu ─────────────────────────────
 *
 * Le jeton `woocommerce-process_checkout` reste exigé et vérifié deux fois :
 * ici, avant de toucher au panier, puis par `process_checkout()`.
 *
 * Il n'est PAS fabriqué dans cette même requête. Un point d'entrée qui
 * créerait puis vérifierait son propre jeton ne protégerait de rien : un site
 * tiers pourrait poster le formulaire avec les cookies du visiteur et
 * déclencher une commande à son insu. La protection tient précisément au fait
 * que le jeton doit avoir été LU auparavant, ce que la politique d'origine du
 * navigateur interdit à un autre site (`famma_express_nonce` ne renvoie aucun
 * en-tête CORS).
 *
 * Il n'est pas non plus imprimé dans la fiche produit : servie par le cache de
 * page, elle donnerait à tous les visiteurs le même jeton, calculé sur une
 * session qui n'est pas la leur.
 *
 * Le script le demande donc à `famma_express_nonce` dès la première
 * interaction avec le formulaire, en parallèle de la saisie : il n'est plus sur
 * le chemin critique du clic. Ce point d'entrée ouvre au passage la session
 * WooCommerce du visiteur (voir `Express_Order::send_nonce()`), condition pour
 * que le jeton reste valable à la requête suivante.
 *
 * Jeton périmé ou refusé : la réponse le signale par `famma_token: expired`
 * avant toute écriture (ni panier, ni commande). Le script peut alors en
 * redemander un et renvoyer UNE fois, sans risque de commande en double.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Point d'entrée `wc-ajax` unique de la commande express.
 */
final class Express_Checkout {

	/**
	 * Nom du point d'entrée `wc-ajax`.
	 */
	public const ENDPOINT = 'famma_express_checkout';

	/**
	 * Instance partagée.
	 *
	 * @var Express_Checkout|null
	 */
	private static ?Express_Checkout $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Express_Checkout
	 */
	public static function instance(): Express_Checkout {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks.
	 *
	 * `Anti_Abuse::throttle_express()` passe avant, à la priorité 1.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'wc_ajax_' . self::ENDPOINT, array( $this, 'handle' ) );
		add_action( 'wc_ajax_famma_express_status', array( $this, 'status' ) );
	}

	/**
	 * Met le produit au panier et exécute le checkout de WooCommerce.
	 *
	 * Point d'entrée public : la commande invité n'exige aucune capacité. La
	 * séquence de sécurité du projet s'applique ainsi — jeton (CSRF), puis
	 * entrées assainies ; l'échappement des messages est celui de
	 * `wc_print_notices()`.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! Express_Preparation::guard() ) {
			return;
		}
		$id = strtolower( Express_Preparation::posted( 'famma_request_id' ) );
		if ( ! Express_Idempotency::valid_id( $id ) ) {
			wp_send_json(
				array(
					'result'     => 'failure',
					'famma_code' => 'intent_invalid',
				)
			);
			return;
		}
		$registry = Express_Idempotency::instance();
		$payload  = Express_Idempotency::payload_hash();
		$row      = $registry->get( $id );
		if ( $row ) {
			$this->reply( $row, $payload );
			return;
		}
		if ( ! Express_Preparation::instance()->verify() ) {
			return;
		}
		if ( ! $registry->reserve( $id, $payload ) ) {
			$row = $registry->get( $id );
			if ( $row ) {
				$this->reply( $row, $payload );
			} else {
				wp_send_json(
					array(
						'result'     => 'failure',
						'famma_code' => 'registry_unavailable',
					)
				);
			}
			return;
		}

		// Le formulaire express ne demande que nom, téléphone et gouvernorat.
		add_filter( 'woocommerce_checkout_fields', array( $this, 'relax_address_fields' ), 99 );

		// Comme `WC_AJAX::checkout()` : la suite est entièrement celle de WooCommerce.
		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
		WC()->checkout()->process_checkout();
		wp_die( 0 );
	}

	/** Return only the current session's known outcome; no order mutations. */
	public function status(): void {
		if ( ! Express_Preparation::guard() ) {
			return;
		}
		$id  = strtolower( Express_Preparation::posted( 'famma_request_id' ) );
		$row = Express_Idempotency::valid_id( $id ) ? Express_Idempotency::instance()->get( $id ) : null;
		wp_send_json(
			$row ? Express_Idempotency::instance()->response( $row ) : array(
				'result'     => 'failure',
				'famma_code' => 'unknown_intent',
			)
		);
	}

	/** Protect replay against changed data or a different Woo session.
	 *
	 * @param array  $row Existing intention.
	 * @param string $payload Canonical request digest.
	 */
	private function reply( array $row, string $payload ): void {
		if ( Express_Idempotency::session_hash() !== $row['session_hash'] ) {
			wp_send_json(
				array(
					'result'     => 'failure',
					'famma_code' => 'unknown_intent',
				)
			);
			return;
		}
		if ( $payload !== $row['payload_hash'] ) {
			wp_send_json(
				array(
					'result'     => 'failure',
					'famma_code' => 'intent_conflict',
				)
			);
			return;
		}
		wp_send_json( Express_Idempotency::instance()->response( $row ) );
	}

	/**
	 * Rend facultatifs l'adresse, la ville et le code postal.
	 *
	 * Posé uniquement dans cette requête, après la vérification du jeton, et à
	 * une priorité tardive : le checkout classique garde ses champs. L'adresse
	 * est recueillie par téléphone à la confirmation de la commande.
	 *
	 * @param array<string,array<string,array<string,mixed>>> $fields Champs du checkout.
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public function relax_address_fields( array $fields ): array {
		foreach ( array( 'billing', 'shipping' ) as $group ) {
			foreach ( array( 'address_1', 'city', 'postcode' ) as $key ) {
				if ( isset( $fields[ $group ][ $group . '_' . $key ] ) ) {
					$fields[ $group ][ $group . '_' . $key ]['required'] = false;
				}
			}
		}

		return $fields;
	}

	/**
	 * Fixe la ligne du produit à la quantité choisie, sans toucher au reste.
	 *
	 * La ligne existante du même produit est retirée puis remplacée, au lieu
	 * d'être cumulée : un second envoi après une erreur (téléphone mal saisi)
	 * ne double plus la quantité. Les autres articles du panier restent, comme
	 * le formulaire l'annonce (« Plus N articles déjà dans votre panier »).
	 *
	 * Le filtre `woocommerce_add_to_cart_validation` est appliqué comme le fait
	 * `WC_AJAX::add_to_cart()` : le plafond de quantité d'`Anti_Abuse` et les
	 * contrôles de WooCommerce (stock, achat possible) restent en vigueur. En
	 * cas de refus, la ligne retirée est restaurée.
	 *
	 * @param int   $product_id Produit commandé.
	 * @param float $quantity   Quantité demandée.
	 * @return bool
	 */
	public function put_in_cart( int $product_id, float $quantity ): bool {
		$cart    = WC()->cart;
		$removed = array();

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( isset( $item['product_id'] ) && (int) $item['product_id'] === $product_id && empty( $item['variation_id'] ) ) {
				$cart->remove_cart_item( (string) $key );
				$removed[] = (string) $key;
			}
		}

		/** This filter is documented in woocommerce/includes/class-wc-ajax.php */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- filtre natif de WooCommerce, appliqué comme WC_AJAX::add_to_cart().
		$passed = (bool) apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
		$added  = $passed ? $cart->add_to_cart( $product_id, $quantity ) : false;

		if ( false === $added ) {
			foreach ( $removed as $key ) {
				$cart->restore_cart_item( $key );
			}
			return false;
		}

		/** This action is documented in woocommerce/includes/class-wc-ajax.php */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- action native de WooCommerce, rejouée comme WC_AJAX::add_to_cart().
		do_action( 'woocommerce_ajax_added_to_cart', $product_id );

		return true;
	}

	/**
	 * Répond par un échec, au format de `process_checkout()`.
	 *
	 * Les avis déjà posés par WooCommerce ou par les filtres (stock, plafond)
	 * sont renvoyés tels quels ; à défaut, un message générique.
	 *
	 * @return void
	 */
	private function fail(): void {
		$generic = __( 'The order could not go through. Please check the form and try again.', 'famma-core' );

		// Sans session, pas d'avis WooCommerce possibles : message seul, échappé.
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$messages = '<ul class="woocommerce-error" role="alert"><li>' . esc_html( $generic ) . '</li></ul>';
		} else {
			if ( 0 === wc_notice_count( 'error' ) ) {
				wc_add_notice( $generic, 'error' );
			}
			$messages = wc_print_notices( true );
		}

		wp_send_json(
			array(
				'result'   => 'failure',
				'messages' => $messages,
				'refresh'  => false,
				'reload'   => false,
			)
		);
	}
}
