<?php
/**
 * Verrou anti double commande du checkout classique (lot 5, défaut D3, T2.6).
 *
 * Constat : deux POST simultanés de `?wc-ajax=checkout`, même session et même
 * jeton, créaient deux commandes, décomptaient le stock deux fois et
 * envoyaient deux OrderPlaced. La commande express a déjà son registre
 * (`Express_Idempotency`) ; ce fichier protège le checkout classique.
 *
 * Déroulé (WC_Checkout::process_checkout(), WooCommerce 11.1.2) :
 * 1. `woocommerce_before_checkout_process`, juste après la vérification du
 *    jeton : verrou atomique par session client, empreinte du panier et
 *    empreinte des coordonnées saisies (facturation, livraison, note, moyen de
 *    paiement). Une re-commande corrigée (autre téléphone, autre adresse) ou
 *    la commande d'un autre client saisie depuis la même session n'est donc
 *    jamais prise pour un doublon : seule une soumission identique l'est.
 *    Verrou déjà pris : attente bornée (5 s) de la soumission qui le détient.
 *    Elle a créé la commande : renvoi vers sa confirmation, panier vidé comme
 *    après son paiement. Elle a échoué sans commande : celle-ci reprend la
 *    main. Toujours en cours : exception, que WooCommerce transforme en
 *    message d'erreur, sans commande.
 * 2. `woocommerce_checkout_create_order` : la clé est posée sur la commande
 *    (méta `_famma_checkout_key`) avant son premier enregistrement.
 * 3. Libération : échec de validation, paiement accepté, commande sans
 *    paiement, et dans tous les cas à `shutdown`.
 *
 * Le verrou est une ligne de `wp_options` non autochargée, insérée par
 * `INSERT IGNORE`, comme `WP_Upgrader::create_lock()` : `add_option()` fait un
 * « INSERT … ON DUPLICATE KEY UPDATE », donc deux requêtes simultanées
 * pourraient toutes deux croire l'avoir obtenu. Valeur : « échéance|jeton ».
 * Un verrou échu (requête interrompue) est repris ; les verrous échus sont
 * purgés à chaque libération.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/** Une seule création de commande à la fois par session et par panier. */
final class Checkout_Idempotency {
	/** Préfixe des lignes de verrou dans wp_options. */
	public const LOCK_PREFIX = 'famma_checkout_lock_';
	/** Méta de commande : clé de la soumission qui l'a créée. */
	public const META_KEY = '_famma_checkout_key';
	/** Durée de vie d'un verrou, en secondes. */
	public const LOCK_TTL = 60;
	/** Fenêtre de renvoi vers une commande déjà créée, en secondes. */
	public const REPLAY_WINDOW = 120;
	/** Attente maximale de la soumission concurrente, en secondes. */
	public const BUSY_WAIT = 5;
	/** Intervalle entre deux relectures du verrou concurrent, en microsecondes. */
	public const WAIT_STEP_US = 250000;
	/** Source du journal WooCommerce. */
	public const LOG_SOURCE = 'famma-checkout';
	/** Statuts d'une commande qui ne vaut pas confirmation (WooCommerce la reprend lui-même). */
	private const NOT_CONFIRMED = array( 'pending', 'failed', 'cancelled', 'checkout-draft' );

	/**
	 * Instance partagée.
	 *
	 * @var self|null
	 */
	private static ?self $i = null;
	/**
	 * Clé de la soumission en cours (vide : pas de protection).
	 *
	 * @var string
	 */
	private string $key = '';
	/**
	 * Nom de la ligne de verrou détenue.
	 *
	 * @var string
	 */
	private string $lock = '';
	/**
	 * Valeur exacte du verrou détenu (vide : aucun).
	 *
	 * @var string
	 */
	private string $owned = '';

	/** Instance partagée, crochets posés une fois. @return self */
	public static function instance(): self {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'woocommerce_before_checkout_process', array( self::$i, 'guard' ), 1 );
			add_action( 'woocommerce_checkout_create_order', array( self::$i, 'stamp' ), 2 );
			add_action( 'woocommerce_after_checkout_validation', array( self::$i, 'validation' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_payment_successful_result', array( self::$i, 'settled' ), PHP_INT_MAX );
			add_filter( 'woocommerce_checkout_no_payment_needed_redirect', array( self::$i, 'settled' ), PHP_INT_MAX );
		}
		return self::$i;
	}

	/**
	 * Message de la soumission refusée, en français et en derja. Cette réponse
	 * n'est jamais suivie de la confirmation : il dit de ne pas renvoyer et
	 * comment la commande sera confirmée.
	 *
	 * @return string
	 */
	public static function busy_message(): string {
		return __( 'Cette commande a déjà été envoyée et son enregistrement est en cours. Ne la renvoyez pas, pour éviter une commande en double : nous vous appelons pour la confirmer. En cas de doute, écrivez-nous sur WhatsApp. الطلبية هاذي تبعثت وقاعدة تتسجّل، ما تعاودش تبعثها. باش نكلموك باش نأكدوها، وكان عندك شك ابعثلنا على واتساب.', 'famma-core' );
	}

	/**
	 * Empreinte du panier : lignes (la clé de ligne de WooCommerce couvre produit,
	 * variation, attributs et taille), quantités et codes promo. Sans le total,
	 * que `update_session()` peut recalculer entre deux requêtes.
	 *
	 * @param array<string,array<string,mixed>> $cart    Lignes de WC()->cart->get_cart().
	 * @param array<int,string>                 $coupons Codes appliqués.
	 * @return string
	 */
	public static function cart_hash( array $cart, array $coupons ): string {
		$lines = array();
		foreach ( $cart as $line_key => $line ) {
			$lines[ (string) $line_key ] = (int) ( $line['quantity'] ?? 0 );
		}
		ksort( $lines );
		$codes = array_map( 'strval', $coupons );
		sort( $codes );
		return hash( 'sha256', (string) wp_json_encode( array( $lines, $codes ) ) );
	}

	/**
	 * Empreinte des coordonnées postées : champs `billing_*` et `shipping_*`
	 * (mode de livraison compris), note, moyen de paiement et case « adresse de
	 * livraison différente ». Jamais le jeton, ni les champs d'attribution ou
	 * de mesure, qui changent d'une page à l'autre sans changer la commande.
	 * Casse et espaces ignorés : « 20 123 456 » vaut « 20123456 ».
	 *
	 * @param array<string,mixed> $posted Champs postés (non assainis).
	 * @return string
	 */
	public static function intent_hash( array $posted ): string {
		$intent = array();
		foreach ( $posted as $name => $value ) {
			$name = (string) $name;
			if ( 1 === preg_match( '/^(?:billing_|shipping_|order_comments$|payment_method$|ship_to_different_address$)/', $name ) ) {
				$intent[ $name ] = self::normalize( wc_clean( wp_unslash( $value ) ) );
			}
		}
		ksort( $intent );
		return hash( 'sha256', (string) wp_json_encode( $intent ) );
	}

	/**
	 * Clé de soumission : HMAC de la session client, de l'empreinte du panier et
	 * de celle des coordonnées postées.
	 *
	 * @param string $session Empreinte de session (Express_Idempotency::session_hash()).
	 * @param string $cart    Empreinte du panier.
	 * @param string $intent  Empreinte des coordonnées (intent_hash()).
	 * @return string
	 */
	public static function key( string $session, string $cart, string $intent = '' ): string {
		return hash_hmac( 'sha256', $session . '|' . $cart . '|' . $intent, wp_salt( 'nonce' ) );
	}

	/**
	 * Début du checkout classique : verrou, attente bornée de la soumission
	 * concurrente, puis renvoi éventuel vers la commande qu'elle a créée.
	 *
	 * @throws \Exception Quand une soumission identique est encore en cours.
	 */
	public function guard(): void {
		if ( ! $this->applies() || null === WC()->session || null === WC()->cart || WC()->cart->is_empty() ) {
			return; // Panier vide : WooCommerce refuse lui-même juste après.
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() vérifie le jeton avant ce crochet ; intent_hash() assainit chaque champ retenu.
		$intent = self::intent_hash( $_POST );
		$key    = self::key( Express_Idempotency::session_hash(), self::cart_hash( WC()->cart->get_cart(), WC()->cart->get_applied_coupons() ), $intent );
		$state  = $this->acquire( $key );
		$waited = 'busy' === $state;
		for ( $step = self::wait_steps(); 'busy' === $state && $step > 0; --$step ) {
			usleep( self::WAIT_STEP_US );
			if ( ! self::alive( $this->current( self::LOCK_PREFIX . $key ) ) ) {
				$state = $this->acquire( $key );
			}
		}
		if ( 'error' === $state ) {
			$this->log( 'lock_error', 0 ); // Base indisponible : on ne bloque pas la vente.
			return;
		}
		$order = 'acquired' === $state ? $this->recent_order( $key ) : null;
		// Toujours en cours ; ou commande non confirmée que notre session, chargée avant la sienne, ne reprendrait pas.
		if ( 'busy' === $state || ( $waited && null !== $order && $order->has_status( self::NOT_CONFIRMED ) ) ) {
			$this->release();
			$this->log( 'locked', null === $order ? 0 : $order->get_id() );
			throw new \Exception( esc_html( self::busy_message() ) );
		}
		$this->key = $key;
		if ( null !== $order && ! $order->has_status( self::NOT_CONFIRMED ) ) {
			$this->release();
			$this->log( 'replayed', $order->get_id() );
			$this->respond( $order );
		}
		if ( $waited ) {
			$this->log( 'retried', 0 ); // La soumission concurrente a échoué sans commande.
		}
	}

	/**
	 * Le checkout classique seulement : ni l'express (déjà protégé), ni un simple
	 * recalcul des totaux sans JavaScript.
	 *
	 * @return bool
	 */
	public function applies(): bool {
		$express = did_action( 'famma_express_reserved' ) > 0 || doing_action( 'wc_ajax_' . Express_Checkout::ENDPOINT );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() vérifie le jeton avant ce crochet ; simple test de présence.
		$totals = ! empty( $_POST['woocommerce_checkout_update_totals'] );
		/**
		 * Filtre : la protection s'applique-t-elle à cette soumission ?
		 *
		 * @param bool $applies Vrai pour un checkout classique.
		 */
		return (bool) apply_filters( 'famma_checkout_idempotency_applies', ! $express && ! $totals );
	}

	/**
	 * Pose la clé sur la commande avant son premier enregistrement.
	 *
	 * @param \WC_Order $order Commande en cours de création.
	 */
	public function stamp( \WC_Order $order ): void {
		if ( '' !== $this->key ) {
			$order->update_meta_data( self::META_KEY, $this->key );
		}
	}

	/**
	 * Échec de validation prouvé : aucune commande ne sera créée.
	 *
	 * @param array     $data   Données validées.
	 * @param \WP_Error $errors Erreurs de validation.
	 */
	public function validation( array $data, \WP_Error $errors ): void {
		if ( '' !== $this->owned && ( $errors->has_errors() || wc_notice_count( 'error' ) > 0 ) ) {
			$this->release();
		}
	}

	/**
	 * Paiement accepté ou commande sans paiement : la commande existe, verrou libéré.
	 *
	 * @param mixed $result Résultat ou URL de WooCommerce, rendu tel quel.
	 * @return mixed
	 */
	public function settled( mixed $result ): mixed {
		$this->release();
		return $result;
	}

	/** Libère le verrou détenu (jamais celui d'une autre requête), puis purge les échus. */
	public function release(): void {
		if ( '' === $this->owned ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- suppression conditionnelle de notre seule ligne de verrou, jamais mise en cache.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND option_value = %s', $wpdb->options, $this->lock, $this->owned ) );
		$this->owned = '';
		$this->lock  = '';
		$this->key   = '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- purge bornée des verrous échus, par préfixe indexé.
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE option_name LIKE %s AND CAST( SUBSTRING_INDEX( option_value, '|', 1 ) AS UNSIGNED ) < %d LIMIT 20", $wpdb->options, $wpdb->esc_like( self::LOCK_PREFIX ) . '%', time() ) );
	}

	/**
	 * Valeur comparable : sans espaces ni majuscules, tableaux parcourus.
	 *
	 * @param mixed $value Valeur assainie par wc_clean().
	 * @return mixed
	 */
	private static function normalize( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			return array_map( static fn( mixed $item ): mixed => self::normalize( $item ), $value );
		}
		return strtolower( (string) preg_replace( '/\s+/u', '', (string) $value ) );
	}

	/**
	 * Nombre de relectures du verrou concurrent avant de refuser.
	 *
	 * @return int
	 */
	private static function wait_steps(): int {
		/**
		 * Filtre : attente maximale de la soumission concurrente, en secondes (0 à 10).
		 *
		 * @param int $seconds 5 par défaut ; 0 refuse tout de suite.
		 */
		$seconds = min( 10, max( 0, (int) apply_filters( 'famma_checkout_busy_wait', self::BUSY_WAIT ) ) );
		return intdiv( $seconds * 1000000, self::WAIT_STEP_US );
	}

	/**
	 * Le verrou lu est-il encore valable ?
	 *
	 * @param string|null $value « échéance|jeton », ou null si la ligne est absente.
	 * @return bool
	 */
	private static function alive( ?string $value ): bool {
		return null !== $value && (int) strtok( $value, '|' ) >= time();
	}

	/**
	 * Valeur actuelle d'une ligne de verrou, lue hors cache.
	 *
	 * @param string $name Nom de la ligne.
	 * @return string|null
	 */
	private function current( string $name ): ?string {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- lecture immédiate, hors cache, du verrou concurrent.
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, $name ) );
		return null === $value ? null : (string) $value;
	}

	/**
	 * Prend le verrou par insertion unique ; reprend un verrou échu une fois.
	 *
	 * @param string $key Clé de soumission.
	 * @return string « acquired », « busy » ou « error ».
	 */
	private function acquire( string $key ): string {
		global $wpdb;
		$name = self::LOCK_PREFIX . $key;
		/**
		 * Filtre : durée de vie du verrou, en secondes (10 à 300).
		 *
		 * @param int $ttl 60 par défaut.
		 */
		$ttl   = min( 300, max( 10, (int) apply_filters( 'famma_checkout_lock_ttl', self::LOCK_TTL ) ) );
		$value = ( time() + $ttl ) . '|' . wp_generate_uuid4();
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- insertion atomique sur la clé unique option_name (modèle WP_Upgrader::create_lock()).
			$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $wpdb->options, $name, $value ) );
			if ( 1 === $inserted ) {
				$this->lock  = $name;
				$this->owned = $value;
				add_action( 'shutdown', array( $this, 'release' ) );
				return 'acquired';
			}
			if ( false === $inserted ) {
				return 'error';
			}
			$current = $this->current( $name );
			if ( self::alive( $current ) ) {
				return 'busy';
			}
			if ( null !== $current ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- reprise d'un verrou échu : seule cette valeur exacte est supprimée.
				$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND option_value = %s', $wpdb->options, $name, $current ) );
			}
		}
		return 'busy';
	}

	/**
	 * Dernière commande créée par cette même clé dans la fenêtre de renvoi, quel
	 * que soit son statut (l'appelant décide selon NOT_CONFIRMED).
	 *
	 * @param string $key Clé de soumission.
	 * @return \WC_Order|null
	 */
	private function recent_order( string $key ): ?\WC_Order {
		/**
		 * Filtre : fenêtre de renvoi vers la commande déjà créée, en secondes (60 à 600).
		 *
		 * @param int $window 120 par défaut.
		 */
		$window = min( 600, max( 60, (int) apply_filters( 'famma_checkout_replay_window', self::REPLAY_WINDOW ) ) );
		$orders = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'limit'        => 1,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => '>' . ( time() - $window ),
				// phpcs:ignore WordPress.DB.SlowDBQuery -- une requête bornée par soumission, via le CRUD Woo (HPOS et stockage historique).
				'meta_key'     => self::META_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery -- clé HMAC unique de la soumission.
				'meta_value'   => $key,
			)
		);
		$order  = is_array( $orders ) ? ( $orders[0] ?? null ) : null;
		return $order instanceof \WC_Order && hash_equals( $key, (string) $order->get_meta( self::META_KEY ) ) ? $order : null;
	}

	/**
	 * Renvoie vers la confirmation, sans recréer ni rejouer les filtres de
	 * paiement. Le panier, identique à celui de la commande, est vidé comme la
	 * passerelle COD l'a fait pour elle : la session enregistrée à la fin de
	 * cette requête ne le fait pas réapparaître.
	 *
	 * @param \WC_Order $order Commande déjà créée.
	 */
	private function respond( \WC_Order $order ): void {
		WC()->cart->empty_cart();
		$url = $order->get_checkout_order_received_url();
		if ( wp_doing_ajax() ) {
			wp_send_json(
				array(
					'result'       => 'success',
					'redirect'     => esc_url_raw( $url ),
					'order_id'     => $order->get_id(),
					'famma_replay' => true,
				)
			);
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Une ligne de journal par issue, sans donnée client.
	 *
	 * @param string $reason   locked, replayed, retried ou lock_error.
	 * @param int    $order_id Commande concernée (0 si aucune).
	 */
	private function log( string $reason, int $order_id ): void {
		/**
		 * Action : une double soumission du checkout classique a été traitée.
		 *
		 * @param string $reason   locked, replayed, retried ou lock_error.
		 * @param int    $order_id Commande déjà créée (0 si aucune).
		 */
		do_action( 'famma_checkout_duplicate', $reason, $order_id );
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( 'lock_error' === $reason ? 'warning' : 'info', sprintf( 'checkout_duplicate reason=%1$s order=%2$d', $reason, $order_id ), array( 'source' => self::LOG_SOURCE ) );
		}
	}
}
