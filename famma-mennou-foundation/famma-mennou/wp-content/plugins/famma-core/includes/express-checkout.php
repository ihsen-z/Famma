<?php
/**
 * Commande express en une seule requête (audit performance, PERF-03).
 *
 * Avant : un clic sur « Commander » enchaînait trois démarrages de PHP non
 * cachables — `?wc-ajax=add_to_cart`, `?wc-ajax=famma_express_nonce`, puis
 * `?wc-ajax=checkout`. Après : le jeton est demandé pendant que le client
 * remplit le formulaire, et le clic n'envoie plus qu'un seul appel,
 * `?wc-ajax=famma_express_checkout`, qui isole le produit de la fiche dans le
 * panier (`Express_Cart_Isolation`) puis exécute le checkout de WooCommerce :
 * seul ce produit est commandé, les autres lignes du panier sont restaurées.
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
	 * Message d'échec générique, échappé, préparé avant `process_checkout()`.
	 *
	 * @var string
	 */
	private string $fallback = '';

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
	 * Isole le produit de la fiche et exécute le checkout de WooCommerce.
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

		// Pas de case CGV dans le formulaire express : ni affichage, ni exigence (cette requête seulement).
		add_filter( 'woocommerce_checkout_show_terms', array( $this, 'hide_terms' ), 99 );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'waive_terms' ), 99 );

		// Tout échec renvoyé par WooCommerce porte un message (voir ensure_failure_message()).
		$this->fallback = '<ul class="woocommerce-error" role="alert"><li>' . esc_html__( 'The order could not go through. Please check the form and try again.', 'famma-core' ) . '</li></ul>';
		ob_start( array( $this, 'ensure_failure_message' ) );

		/*
		 * Comme `WC_AJAX::checkout()` : la suite est entièrement celle de
		 * WooCommerce, sur le panier isolé par verify() (le seul produit de la
		 * fiche). Le panier d'origine est restauré à `shutdown`.
		 */
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
	 * Masque les CGV du checkout express (case non affichée).
	 *
	 * Posé uniquement dans la requête `famma_express_checkout` : le formulaire
	 * express annonce les conditions sous le bouton (« En commandant, vous
	 * acceptez… »), sans case à cocher. Le checkout classique n'est pas touché.
	 *
	 * @return bool
	 */
	public function hide_terms(): bool {
		return false;
	}

	/**
	 * Neutralise l'exigence de case CGV pour la seule commande express.
	 *
	 * `WC_Checkout::validate_checkout()` (WooCommerce 11.0.1) n'exige la case
	 * que si `terms-field` est posté et `terms` absent. Les deux valeurs sont
	 * fixées ici, après `get_posted_data()` et avant la validation.
	 *
	 * @param array<string,mixed> $data Données postées.
	 * @return array<string,mixed>
	 */
	public function waive_terms( array $data ): array {
		$data['terms']       = 0;
		$data['terms-field'] = 0;

		return $data;
	}

	/**
	 * Garantit un message à tout échec renvoyé par `process_checkout()`.
	 *
	 * `WC_Checkout::send_ajax_failure_response()` peut renvoyer `messages`
	 * vide (passerelle absente, rechargement demandé). La réponse JSON passe
	 * par ce tampon de sortie : un échec sans message reçoit le message
	 * générique, déjà échappé. Les réponses avec message (dont les `<li
	 * data-id="billing_phone">` de WooCommerce) et les succès sont rendus
	 * tels quels. Fonction pure : appelée à la fermeture du tampon.
	 *
	 * @param string $buffer Sortie de la requête.
	 * @return string
	 */
	public function ensure_failure_message( string $buffer ): string {
		$response = json_decode( $buffer, true );

		if ( ! is_array( $response ) || 'failure' !== ( $response['result'] ?? '' ) ) {
			return $buffer;
		}
		if ( '' !== trim( wp_strip_all_tags( (string) ( $response['messages'] ?? '' ) ) ) ) {
			return $buffer;
		}
		$response['messages'] = $this->fallback;
		$encoded              = wp_json_encode( $response );

		return false === $encoded ? $buffer : $encoded;
	}
}
