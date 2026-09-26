<?php
/**
 * Garde-fous contre les fausses commandes COD.
 *
 * Audit du 23/09 puis du 25/09 (SEC-01, SEC-08) : la commande express se
 * rejouait en trois requêtes, sans piège, sans limite ni plafond côté serveur,
 * et l'API Store acceptait des commandes sans la validation tunisienne. Une
 * rafale de fausses commandes coûte des appels de confirmation, fausse les
 * statistiques et épuise le quota de 40 e-mails par jour de l'hébergeur, ce
 * qui coupe aussi les notifications des vraies commandes.
 *
 * Défenses posées ici, toutes côté serveur :
 * - un champ piège, invisible pour un humain, dans le formulaire express et
 *   dans la page de commande ;
 * - une limite de commandes par connexion (heure) et par téléphone (24 h) ;
 * - un plafond de quantité par article ;
 * - une limite sur le point qui distribue le jeton de la commande express ;
 * - la route de commande de l'API Store fermée : la boutique passe par le
 *   checkout classique (ADR-003).
 *
 * Pas d'horodatage signé dans le formulaire : les fiches sont servies par le
 * cache de page pendant des jours, un horodatage imprimé dans le HTML ne dirait
 * rien du temps passé par le visiteur.
 *
 * Les seuils se règlent par le filtre `famma_anti_abuse_limits`. Un compte
 * gestionnaire de la boutique n'y est pas soumis.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Piège, limites et plafond appliqués aux commandes.
 */
final class Anti_Abuse {

	/**
	 * Nom du champ piège, identique dans les deux formulaires.
	 */
	public const TRAP_FIELD = 'famma_hp_site';

	/**
	 * Seuils par défaut.
	 */
	private const DEFAULT_LIMITS = array(
		'orders_per_ip_hour'   => 5,
		'orders_per_phone_day' => 3,
		'nonces_per_ip_hour'   => 40,
		'max_quantity'         => 10,
	);

	/**
	 * Instance partagée.
	 *
	 * @var Anti_Abuse|null
	 */
	private static ?Anti_Abuse $i = null;

	/**
	 * Numéro du prochain champ piège imprimé (identifiants uniques).
	 *
	 * @var int
	 */
	private static int $trap_count = 0;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Anti_Abuse
	 */
	public static function instance(): Anti_Abuse {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'trap_field' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 5, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'count_order' ) );

		add_filter( 'woocommerce_quantity_input_max', array( $this, 'cap_max_quantity' ), 20 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 20, 3 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'validate_cart_update' ), 20, 4 );

		// Avant `Express_Order::send_nonce()`, qui est à la priorité 10.
		add_action( 'wc_ajax_famma_express_nonce', array( $this, 'throttle_nonce' ), 1 );

		add_filter( 'rest_endpoints', array( $this, 'close_store_checkout' ) );
	}

	/**
	 * Imprime le champ piège.
	 *
	 * Masqué par l'attribut `hidden` et hors tabulation : un humain ne le voit
	 * ni ne l'atteint, un robot qui remplit tous les champs le remplit aussi.
	 *
	 * @return void
	 */
	public static function trap_field(): void {
		++self::$trap_count;
		$id = 'famma-hp-' . self::$trap_count;
		?>
		<p class="famma-trap" hidden aria-hidden="true">
			<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Laisser ce champ vide', 'famma-core' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::TRAP_FIELD ); ?>" value="" tabindex="-1" autocomplete="off" />
		</p>
		<?php
	}

	/**
	 * Refuse une commande piégée, trop fréquente ou hors plafond.
	 *
	 * Appelée par `WC_Checkout::process_checkout()` après la vérification de
	 * son jeton : le formulaire express et la page de commande passent tous
	 * les deux par ici.
	 *
	 * @param array<string, mixed> $data   Données postées.
	 * @param \WP_Error            $errors Erreurs de validation.
	 * @return void
	 */
	public function validate_checkout( $data, $errors ): void {
		if ( ! $errors instanceof \WP_Error || self::exempt() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- jeton déjà vérifié par WC_Checkout::process_checkout().
		$trap = isset( $_POST[ self::TRAP_FIELD ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ self::TRAP_FIELD ] ) ) ) : '';

		if ( '' !== $trap ) {
			$errors->add( 'famma_order_refused', self::message( 'refused' ) );
			return;
		}

		if ( self::count( self::key( 'ip', self::client_ip() ) ) >= self::limit( 'orders_per_ip_hour' ) ) {
			$errors->add( 'famma_order_ip_limit', self::message( 'ip' ) );
			return;
		}

		$phone = Config::international_phone( is_array( $data ) && isset( $data['billing_phone'] ) ? (string) $data['billing_phone'] : '' );

		if ( '' !== $phone && self::count( self::key( 'phone', $phone ) ) >= self::limit( 'orders_per_phone_day' ) ) {
			$errors->add( 'famma_order_phone_limit', self::message( 'phone' ) );
			return;
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( isset( $item['quantity'] ) && (float) $item['quantity'] > self::limit( 'max_quantity' ) ) {
					$errors->add( 'famma_order_quantity', self::message( 'quantity' ) );
					return;
				}
			}
		}
	}

	/**
	 * Compte une commande créée, par connexion et par téléphone.
	 *
	 * @param \WC_Order $order Commande créée.
	 * @return void
	 */
	public function count_order( $order ): void {
		if ( ! $order instanceof \WC_Order || self::exempt() ) {
			return;
		}

		self::bump( self::key( 'ip', self::client_ip() ), HOUR_IN_SECONDS );

		$phone = Config::international_phone( (string) $order->get_billing_phone() );

		if ( '' !== $phone ) {
			self::bump( self::key( 'phone', $phone ), DAY_IN_SECONDS );
		}
	}

	/**
	 * Plafonne la quantité proposée par les champs de quantité.
	 *
	 * @param int|float $max Quantité maximale calculée (-1 : illimitée).
	 * @return int|float
	 */
	public function cap_max_quantity( $max ) {
		$limit = self::limit( 'max_quantity' );
		$max   = is_numeric( $max ) ? (float) $max : -1.0;

		return ( $max < 0 || $max > $limit ) ? $limit : $max;
	}

	/**
	 * Refuse un ajout au panier au-delà du plafond, panier compris.
	 *
	 * @param bool      $passed     Validation en cours.
	 * @param int       $product_id Produit ajouté.
	 * @param int|float $quantity   Quantité ajoutée.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id = 0, $quantity = 1 ): bool {
		if ( ! $passed || self::exempt() ) {
			return (bool) $passed;
		}

		$in_cart = 0.0;

		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( isset( $item['product_id'], $item['quantity'] ) && (int) $item['product_id'] === (int) $product_id ) {
					$in_cart += (float) $item['quantity'];
				}
			}
		}

		if ( (float) $quantity + $in_cart > self::limit( 'max_quantity' ) ) {
			wc_add_notice( self::message( 'quantity' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Refuse une mise à jour du panier au-delà du plafond.
	 *
	 * @param bool                 $passed        Validation en cours.
	 * @param string               $cart_item_key Ligne du panier.
	 * @param array<string, mixed> $values        Contenu de la ligne.
	 * @param int|float            $quantity      Nouvelle quantité.
	 * @return bool
	 */
	public function validate_cart_update( $passed, $cart_item_key = '', $values = array(), $quantity = 1 ): bool {
		if ( ! $passed || self::exempt() ) {
			return (bool) $passed;
		}

		if ( (float) $quantity > self::limit( 'max_quantity' ) ) {
			wc_add_notice( self::message( 'quantity' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Limite les demandes de jeton de la commande express par connexion.
	 *
	 * Au-delà du seuil, la réponse est une erreur 429 et `send_nonce()` n'est
	 * pas appelé : le script affiche son message d'erreur habituel.
	 *
	 * @return void
	 */
	public function throttle_nonce(): void {
		if ( self::exempt() ) {
			return;
		}

		$key = self::key( 'nonce', self::client_ip() );

		if ( self::count( $key ) >= self::limit( 'nonces_per_ip_hour' ) ) {
			wp_send_json_error( array( 'message' => self::message( 'ip' ) ), 429 );
		}

		self::bump( $key, HOUR_IN_SECONDS );
	}

	/**
	 * Retire la route de commande de l'API Store.
	 *
	 * Le panier de l'API Store reste disponible (blocs WooCommerce) ; seule la
	 * création de commande, qui contournait la validation tunisienne, disparaît.
	 *
	 * @param array<string, mixed> $endpoints Routes REST.
	 * @return array<string, mixed>
	 */
	public function close_store_checkout( $endpoints ): array {
		$endpoints = (array) $endpoints;

		foreach ( array_keys( $endpoints ) as $route ) {
			$route = (string) $route;

			if ( str_starts_with( $route, '/wc/store/v1/checkout' ) || str_starts_with( $route, '/wc/store/checkout' ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}

	/**
	 * Vrai pour un gestionnaire de la boutique, jamais limité.
	 *
	 * @return bool
	 */
	private static function exempt(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Seuil courant.
	 *
	 * @param string $name Nom du seuil.
	 * @return int
	 */
	private static function limit( string $name ): int {
		/**
		 * Seuils des garde-fous de commande.
		 *
		 * @param array<string, int> $limits Seuils par défaut.
		 */
		$limits = (array) apply_filters( 'famma_anti_abuse_limits', self::DEFAULT_LIMITS );

		return max( 1, (int) ( $limits[ $name ] ?? self::DEFAULT_LIMITS[ $name ] ) );
	}

	/**
	 * Adresse IP du visiteur (le site n'est derrière aucun proxy).
	 *
	 * @return string
	 */
	private static function client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Clé de compteur : empreinte salée, jamais l'IP ni le numéro en clair.
	 *
	 * @param string $scope Portée (ip, phone, nonce).
	 * @param string $value Valeur comptée.
	 * @return string
	 */
	private static function key( string $scope, string $value ): string {
		return 'famma_ab_' . $scope . '_' . md5( $value . wp_salt( 'nonce' ) );
	}

	/**
	 * Valeur d'un compteur encore valide.
	 *
	 * @param string $key Clé du compteur.
	 * @return int
	 */
	private static function count( string $key ): int {
		$data = get_transient( $key );

		return is_array( $data ) && isset( $data['n'] ) ? (int) $data['n'] : 0;
	}

	/**
	 * Incrémente un compteur sans prolonger sa fenêtre.
	 *
	 * @param string $key    Clé du compteur.
	 * @param int    $window Durée de la fenêtre, en secondes.
	 * @return void
	 */
	private static function bump( string $key, int $window ): void {
		$now  = time();
		$data = get_transient( $key );

		if ( ! is_array( $data ) || empty( $data['exp'] ) || (int) $data['exp'] <= $now ) {
			$data = array(
				'n'   => 0,
				'exp' => $now + $window,
			);
		}

		$data['n'] = (int) $data['n'] + 1;

		set_transient( $key, $data, max( 1, (int) $data['exp'] - $now ) );
	}

	/**
	 * Message affiché au visiteur, en français puis en derja.
	 *
	 * @param string $reason Motif du refus.
	 * @return string
	 */
	private static function message( string $reason ): string {
		switch ( $reason ) {
			case 'ip':
				return __( 'Trop de commandes ont été passées depuis cette connexion. Réessayez dans une heure, ou écrivez-nous sur WhatsApp. برشا طلبيات من نفس الكونكسيون، عاود بعد ساعة ولا ابعثلنا على واتساب.', 'famma-core' );
			case 'phone':
				return __( 'Ce numéro a déjà plusieurs commandes en attente : nous vous appelons pour les confirmer. Pour ajouter un article, écrivez-nous sur WhatsApp. عندك طلبيات مازالت ما تأكدتش، باش نكلموك. باش تزيد حاجة ابعثلنا على واتساب.', 'famma-core' );
			case 'quantity':
				return sprintf(
					/* translators: %d: quantité maximale par article. */
					__( 'Quantité limitée à %d par article. Pour une commande plus importante, écrivez-nous sur WhatsApp. الكمية محدودة، للطلبيات الكبيرة ابعثلنا على واتساب.', 'famma-core' ),
					self::limit( 'max_quantity' )
				);
		}

		return __( 'La commande n\'a pas pu être enregistrée. Réessayez, ou écrivez-nous sur WhatsApp. ما نجمناش نسجلو الطلبية، عاود جرّب ولا ابعثلنا على واتساب.', 'famma-core' );
	}
}
