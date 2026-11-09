<?php
/**
 * Saisies des visiteurs — réception et rattachement aux commandes.
 *
 * Le script du journal envoie, champ par champ, ce qui est tapé dans le
 * formulaire express (fiche produit) et dans le checkout, vers
 * `famma/v1/inputs`. Même protection que `famma/v1/collect` (route publique
 * car les pages sont en cache, sans nonce) : corps plafonné, identifiants
 * UUID, robots et équipe écartés, limite de débit par empreinte d'IP, liste
 * blanche des champs et assainissement de chacun.
 *
 * Quand une commande est créée (site ou admin), les lignes de la même
 * session, du même visiteur ou du même téléphone passent à « commandé ».
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Route `famma/v1/inputs`, contexte du script, passage à « commandé ».
 */
final class Visitor_Inputs_Collector {

	/**
	 * Champs de formulaire reçus → colonne de la table.
	 */
	private const MAP = array(
		'billing_first_name' => 'first_name',
		'billing_phone'      => 'phone',
		'billing_state'      => 'state',
		'billing_address_1'  => 'address',
		'billing_city'       => 'city',
		'famma_size'         => 'size',
		'famma_quantity'     => 'quantity',
	);

	/**
	 * Taille maximale du corps, en octets.
	 */
	private const MAX_BODY = 4096;

	/**
	 * Envois acceptés par empreinte d'IP sur dix minutes.
	 */
	private const RATE_LIMIT = 60;

	/**
	 * Instance partagée.
	 *
	 * @var Visitor_Inputs_Collector|null
	 */
	private static ?Visitor_Inputs_Collector $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Visitor_Inputs_Collector
	 */
	public static function instance(): Visitor_Inputs_Collector {
		if ( null === self::$i ) {
			self::$i = new self();
			add_filter( 'famma_visitor_journal_context', array( self::$i, 'context' ) );
			add_action( 'rest_api_init', array( self::$i, 'register_route' ) );
			add_action( 'woocommerce_checkout_order_processed', array( self::$i, 'checkout_order' ), 25, 3 );
			add_action( 'woocommerce_process_shop_order_meta', array( self::$i, 'admin_order' ), 60 );
		}
		return self::$i;
	}

	/**
	 * Ajoute au contexte du script l'adresse d'envoi et la phrase d'accord.
	 *
	 * Ne dépend que des réglages, jamais du visiteur : reste juste en cache.
	 *
	 * @param array<string, mixed> $context Contexte du journal.
	 * @return array<string, mixed>
	 */
	public function context( array $context ): array {
		$store = Visitor_Inputs::instance();
		if ( $store->enabled() ) {
			$context['inputs'] = esc_url_raw( rest_url( 'famma/v1/inputs' ) );
			$context['notice'] = $store->notice();
		}
		return $context;
	}

	/**
	 * Déclare la route d'envoi.
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route(
			'famma/v1',
			'/inputs',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'receive' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Texte court assaini.
	 *
	 * @param mixed $value Valeur reçue.
	 * @param int   $max   Longueur maximale.
	 * @return string
	 */
	private function text( $value, int $max ): string {
		return is_scalar( $value ) ? mb_substr( trim( sanitize_text_field( (string) $value ) ), 0, $max ) : '';
	}

	/**
	 * Assainit une saisie selon sa colonne.
	 *
	 * @param string $column Colonne cible.
	 * @param mixed  $value  Valeur reçue.
	 * @return int|string
	 */
	private function clean( string $column, $value ) {
		switch ( $column ) {
			case 'quantity':
				return min( 99, absint( is_scalar( $value ) ? $value : 0 ) );
			case 'phone':
				// Chiffres (latins, arabes, persans), espaces, « + », « . », « - » seulement.
				return mb_substr( (string) preg_replace( '/[^\d\x{0660}-\x{0669}\x{06F0}-\x{06F9}+ .\-]/u', '', $this->text( $value, 40 ) ), 0, 20 );
			case 'state':
				$code   = strtoupper( $this->text( $value, 16 ) );
				$states = function_exists( 'WC' ) ? (array) WC()->countries->get_states( 'TN' ) : array();
				return isset( $states[ $code ] ) ? $code : '';
			default:
				return $this->text( $value, Visitor_Inputs::FIELDS[ $column ] );
		}
	}

	/**
	 * Indique si l'empreinte d'IP a dépassé sa limite.
	 *
	 * @param string $ip_hash Empreinte d'IP.
	 * @return bool
	 */
	private function rate_limited( string $ip_hash ): bool {
		if ( '' === $ip_hash ) {
			return false;
		}
		$key   = 'famma_vi_' . $ip_hash;
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return true;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Reçoit l'état courant d'un formulaire.
	 *
	 * Répond toujours 204 : le script n'a rien à corriger, un robot n'apprend
	 * rien de ce qui est filtré.
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response
	 */
	public function receive( \WP_REST_Request $request ): \WP_REST_Response {
		$done  = new \WP_REST_Response( null, 204 );
		$store = Visitor_Inputs::instance();
		$body  = (string) $request->get_body();
		if ( ! $store->enabled() || '' === $body || strlen( $body ) > self::MAX_BODY || Visitor_Context::is_bot() || Visitor_Context::is_staff() ) {
			return $done;
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data['v'], $data['s'], $data['f'] ) || ! is_array( $data['f'] ) ) {
			return $done;
		}
		$visitor = strtolower( $this->text( $data['v'], 36 ) );
		$session = strtolower( $this->text( $data['s'], 36 ) );
		if ( ! Visitor_Context::is_uuid( $visitor ) || ! Visitor_Context::is_uuid( $session ) || $this->rate_limited( Visitor_Context::ip_hash() ) ) {
			return $done;
		}
		$fields = array();
		foreach ( self::MAP as $name => $column ) {
			if ( array_key_exists( $name, $data['f'] ) ) {
				$fields[ $column ] = $this->clean( $column, $data['f'][ $name ] );
			}
		}
		// La quantité vaut 1 par défaut dans le formulaire : seule, elle ne dit rien.
		if ( array() === array_filter( array_diff_key( $fields, array( 'quantity' => 0 ) ) ) ) {
			return $done;
		}
		$src  = isset( $data['src'] ) && is_array( $data['src'] ) ? $data['src'] : array();
		$path = is_string( $data['p'] ?? null ) ? (string) wp_parse_url( $data['p'], PHP_URL_PATH ) : '';
		$store->save(
			array(
				'visitor_id' => $visitor,
				'session_id' => $session,
				'product_id' => absint( $data['pid'] ?? 0 ),
				'path'       => mb_substr( '/' . ltrim( sanitize_text_field( $path ), '/' ), 0, 255 ),
				'source'     => strtolower( $this->text( $src['source'] ?? '', 64 ) ),
				'medium'     => strtolower( $this->text( $src['medium'] ?? '', 64 ) ),
				'campaign'   => $this->text( $src['campaign'] ?? '', 100 ),
				'content'    => $this->text( $src['content'] ?? '', 100 ),
				'device'     => Visitor_Context::device(),
			),
			$fields
		);
		return $done;
	}

	/**
	 * Commande passée sur le site : session, visiteur et téléphone.
	 *
	 * @param int       $order_id Identifiant de commande.
	 * @param array     $posted   Données postées (inutilisées).
	 * @param \WC_Order $order    Commande.
	 * @return void
	 */
	public function checkout_order( $order_id, $posted, $order ): void {
		unset( $posted );
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		Visitor_Inputs::instance()->mark_converted(
			$order->get_id(),
			Visitor_Context::cookie_id( Visitor_Context::SESSION_COOKIE ),
			Visitor_Context::cookie_id( Visitor_Context::VISITOR_COOKIE ),
			Tunisia::normalize_phone( (string) $order->get_billing_phone() )
		);
	}

	/**
	 * Commande enregistrée dans l'admin (après un rappel) : par téléphone.
	 *
	 * @param int $order_id Identifiant de commande.
	 * @return void
	 */
	public function admin_order( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$phone = Tunisia::normalize_phone( (string) $order->get_billing_phone() );
		if ( '' !== $phone ) {
			Visitor_Inputs::instance()->mark_converted( $order->get_id(), '', '', $phone );
		}
	}
}
