<?php
/**
 * Saisies des visiteurs — « Créer la commande » depuis une ligne.
 *
 * Après un rappel, l'équipe crée la commande WooCommerce en un clic : chaque
 * donnée tapée par le visiteur va dans son champ WooCommerce (prénom,
 * téléphone, gouvernorat, adresse, ville ; produit, taille, quantité), la
 * commande part au statut « En attente de confirmation » comme une commande
 * du site (avec le même e-mail « Nouvelle commande »), et son canal est
 * « Panier récupéré » — posé par ce seul bouton. Si le produit n'existe
 * plus, elle reste « Attente paiement » pour qu'on puisse l'ajouter.
 *
 * ── Meta ───────────────────────────────────────────────────────────────────
 * La commande est `created_via = admin` : aucun OrderPlaced n'est envoyé
 * (`Capi::order_placed_ineligibility()`), et ni l'IP, ni le navigateur, ni
 * les cookies Meta de l'administrateur ne sont posés dessus. Seule une vraie
 * livraison déclenche ensuite le Purchase, comme pour toute commande.
 *
 * ── Double clic ────────────────────────────────────────────────────────────
 * Un verrou (option unique) empêche deux créations simultanées ; une ligne
 * déjà reliée à une commande renvoie vers elle au lieu d'en créer une autre.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Création d'une commande WooCommerce à partir d'une saisie.
 */
final class Visitor_Inputs_Order {

	/** Action admin-post. */
	public const ACTION = 'famma_inputs_order';

	/** Méta de la commande : ligne de saisie d'origine. */
	public const META_INPUT = '_famma_input_id';

	/** Préfixe de l'option-verrou. */
	private const LOCK = 'famma_inputs_order_lock_';

	/** Fenêtre de recherche d'une commande du même téléphone, en jours. */
	private const DUP_DAYS = 30;

	/**
	 * Instance partagée.
	 *
	 * @var Visitor_Inputs_Order|null
	 */
	private static ?Visitor_Inputs_Order $i = null;

	/**
	 * Renvoie l'instance partagée, crochet posé au premier appel.
	 *
	 * @return Visitor_Inputs_Order
	 */
	public static function instance(): Visitor_Inputs_Order {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_post_' . self::ACTION, array( self::$i, 'handle' ) );
		}
		return self::$i;
	}

	/**
	 * Nom du nonce d'une ligne.
	 *
	 * @param int $id Ligne.
	 * @return string
	 */
	public static function nonce_action( int $id ): string {
		return self::ACTION . '_' . $id;
	}

	/**
	 * Bouton « Créer la commande » d'une ligne (formulaire POST).
	 *
	 * @param int  $id    Ligne.
	 * @param bool $force  Créer malgré une commande du même téléphone.
	 * @return string HTML échappé.
	 */
	public static function button( int $id, bool $force = false ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="famma-inputs__order">'
			. wp_nonce_field( self::nonce_action( $id ), '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">'
			. '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '">'
			. ( $force ? '<input type="hidden" name="force" value="1">' : '' )
			. '<button type="submit" class="button button-primary button-small">' . ( $force ? esc_html__( 'Créer quand même', 'famma-core' ) : esc_html__( 'Créer la commande', 'famma-core' ) ) . '</button></form>';
	}

	/**
	 * Commande reliée à une ligne, hors corbeille, ou null.
	 *
	 * @param array<string, string> $row Ligne.
	 * @return \WC_Order|null
	 */
	public static function linked_order( array $row ): ?\WC_Order {
		$order = (int) $row['order_id'] ? wc_get_order( (int) $row['order_id'] ) : false;
		return $order instanceof \WC_Order && ! $order->has_status( 'trash' ) ? $order : null;
	}

	/**
	 * Commande récente (30 jours, ni annulée ni échouée) du même téléphone, ou null.
	 *
	 * Rattrape le client qui a déjà commandé autrement (autre appareil, ligne
	 * « Pas intéressé » ou « Clos » jamais reliée) : on évite un doublon.
	 *
	 * @param string $phone_norm Téléphone à 8 chiffres.
	 * @return \WC_Order|null
	 */
	public static function same_phone_order( string $phone_norm ): ?\WC_Order {
		if ( '' === $phone_norm ) {
			return null;
		}
		$statuses = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-cancelled', 'wc-failed', 'wc-checkout-draft' ) );
		$orders   = wc_get_orders(
			array(
				'limit'        => 300,
				'status'       => $statuses,
				'date_created' => '>' . ( time() - self::DUP_DAYS * DAY_IN_SECONDS ),
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);
		foreach ( $orders as $order ) {
			if ( $order instanceof \WC_Order && Tunisia::normalize_phone( (string) $order->get_billing_phone() ) === $phone_norm ) {
				return $order;
			}
		}
		return null;
	}

	/**
	 * Traite le clic : nonce, droit, verrou, création, redirection.
	 *
	 * @return void
	 */
	public function handle(): void {
		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		check_admin_referer( self::nonce_action( $id ) );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ), '', array( 'response' => 403 ) );
		}
		$row = $id > 0 ? Visitor_Inputs_Rows::get( $id ) : null;
		if ( null === $row ) {
			wp_safe_redirect( Visitor_Inputs_Admin::url( array( 'done' => 0 ) ) );
			exit;
		}
		$existing = self::linked_order( $row );
		if ( null !== $existing ) {
			wp_safe_redirect( $existing->get_edit_order_url() );
			exit;
		}
		$twin = empty( $_POST['force'] ) ? self::same_phone_order( (string) $row['phone_norm'] ) : null;
		if ( null !== $twin ) {
			wp_safe_redirect(
				Visitor_Inputs_Admin::url(
					array(
						'status' => 'all',
						'dup'    => $twin->get_id(),
						'row'    => $id,
					)
				)
			);
			exit;
		}
		if ( ! $this->lock( $id ) ) {
			wp_safe_redirect( Visitor_Inputs_Admin::url( array( 'busy' => 1 ) ) );
			exit;
		}
		try {
			// Relue sous verrou : un autre clic a pu la relier entre-temps.
			$row   = Visitor_Inputs_Rows::get( $id );
			$order = null === $row ? null : ( self::linked_order( $row ) ?? $this->create( $row ) );
		} finally {
			delete_option( self::LOCK . $id );
		}
		if ( ! $order instanceof \WC_Order ) {
			wp_safe_redirect( Visitor_Inputs_Admin::url( array( 'done' => 0 ) ) );
			exit;
		}
		wp_safe_redirect( $order->get_edit_order_url() );
		exit;
	}

	/**
	 * Pose le verrou d'une ligne ; un verrou de plus de 2 minutes est périmé.
	 *
	 * @param int $id Ligne.
	 * @return bool Verrou obtenu.
	 */
	private function lock( int $id ): bool {
		if ( add_option( self::LOCK . $id, time(), '', false ) ) {
			return true;
		}
		if ( time() - (int) get_option( self::LOCK . $id, 0 ) > 2 * MINUTE_IN_SECONDS ) {
			update_option( self::LOCK . $id, time(), false );
			return true;
		}
		return false;
	}

	/**
	 * Crée la commande d'une ligne et relie la ligne à elle.
	 *
	 * @param array<string, string> $row Ligne de saisie.
	 * @return \WC_Order|null
	 */
	public function create( array $row ): ?\WC_Order {
		$order = wc_create_order( array( 'created_via' => 'admin' ) );
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}
		try {
			return $this->fill( $order, $row );
		} catch ( \Throwable $e ) {
			// Pas de commande orpheline non reliée : un nouvel essai en créerait une seconde.
			$order->delete( true );
			wc_get_logger()->error( 'Création depuis la saisie ' . (int) $row['id'] . ' : ' . $e->getMessage(), array( 'source' => 'famma-inputs' ) );
			return null;
		}
	}

	/**
	 * Remplit et enregistre la commande, puis relie la ligne.
	 *
	 * @param \WC_Order             $order Commande tout juste créée.
	 * @param array<string, string> $row   Ligne de saisie.
	 * @return \WC_Order
	 */
	private function fill( \WC_Order $order, array $row ): \WC_Order {
		// wc_create_order() pose l'IP et le navigateur de l'administrateur : ce ne sont pas ceux du client.
		$order->set_customer_ip_address( '' );
		$order->set_customer_user_agent( '' );
		$this->fill_address( $order, $row );
		$warnings = $this->add_product( $order, $row );
		$this->add_shipping( $order, (string) $row['state'] );
		$this->stamp( $order, $row );
		// Sans produit, la commande reste « Attente paiement » : WooCommerce ne
		// laisse ajouter des articles qu'à ce statut.
		if ( array() !== $order->get_items() ) {
			$order->set_status( Order_Statuses::instance()->cod_initial_status() );
		}
		$order->calculate_totals( wc_tax_enabled() );

		$user = wp_get_current_user();
		/* translators: 1: saisie number, 2: team member name. */
		$note = sprintf( __( 'Commande créée depuis la saisie n° %1$d (Saisies à relancer) par %2$s. Canal : Panier récupéré.', 'famma-core' ), (int) $row['id'], $user->display_name );
		$order->add_order_note( trim( $note . ' ' . implode( ' ', $warnings ) ), 0, true );

		Visitor_Inputs_Rows::set_converted( (int) $row['id'], $order->get_id() );
		Visitor_Inputs::instance()->mark_converted( $order->get_id(), (string) $row['session_id'], (string) $row['visitor_id'], (string) $row['phone_norm'] );
		return $order;
	}

	/**
	 * Champs de facturation et de livraison (même adresse, comme au checkout).
	 *
	 * @param \WC_Order             $order Commande.
	 * @param array<string, string> $row   Ligne de saisie.
	 * @return void
	 */
	private function fill_address( \WC_Order $order, array $row ): void {
		$address = array(
			'first_name' => (string) $row['first_name'],
			'address_1'  => (string) $row['address'],
			'city'       => (string) $row['city'],
			'state'      => isset( Visitor_Inputs::states()[ $row['state'] ] ) ? (string) $row['state'] : '',
			'country'    => 'TN',
		);
		$order->set_address( $address + array( 'phone' => '' !== $row['phone_norm'] ? (string) $row['phone_norm'] : (string) $row['phone'] ), 'billing' );
		$order->set_address( $address, 'shipping' );
		$order->set_payment_method( WC()->payment_gateways()->payment_gateways()['cod'] ?? 'cod' );
	}

	/**
	 * Ligne produit avec quantité et taille ; renvoie les points à vérifier.
	 *
	 * @param \WC_Order             $order Commande.
	 * @param array<string, string> $row   Ligne de saisie.
	 * @return array<int, string>
	 */
	private function add_product( \WC_Order $order, array $row ): array {
		$product = (int) $row['product_id'] ? wc_get_product( (int) $row['product_id'] ) : false;
		if ( ! $product instanceof \WC_Product ) {
			return array( __( 'Produit introuvable : à ajouter à la main.', 'famma-core' ) );
		}
		$qty   = max( 1, (int) $row['quantity'] );
		$price = (float) wc_get_price_excluding_tax( $product, array( 'qty' => $qty ) );
		$item  = new \WC_Order_Item_Product();
		$item->set_props(
			array(
				'product'  => $product,
				'quantity' => $qty,
				'subtotal' => $price,
				'total'    => $price,
			)
		);
		$size = (string) $row['size'];
		$note = array();
		if ( '' !== $size && in_array( $size, Product_Sizes::options( $product ), true ) ) {
			$item->add_meta_data( Product_Sizes::FIELD, $size, true );
		} elseif ( '' !== $size ) {
			/* translators: %s: size typed by the visitor. */
			$note[] = sprintf( __( 'Taille saisie « %s » à vérifier.', 'famma-core' ), $size );
		}
		$order->add_item( $item );
		return $note;
	}

	/**
	 * Ligne de livraison : première méthode active de la zone du gouvernorat.
	 *
	 * @param \WC_Order $order Commande.
	 * @param string    $state Gouvernorat (code WooCommerce).
	 * @return void
	 */
	private function add_shipping( \WC_Order $order, string $state ): void {
		$zone    = \WC_Shipping_Zones::get_zone_matching_package(
			array(
				'destination' => array(
					'country'  => 'TN',
					'state'    => $state,
					'postcode' => '',
				),
			)
		);
		$methods = $zone->get_shipping_methods( true );
		$method  = reset( $methods );
		if ( ! $method instanceof \WC_Shipping_Method ) {
			return;
		}
		$cost = $method->get_option( 'cost', '0' );
		$item = new \WC_Order_Item_Shipping();
		$item->set_props(
			array(
				'method_title' => $method->get_title(),
				'method_id'    => $method->id,
				'instance_id'  => $method->get_instance_id(),
				'total'        => wc_format_decimal( is_numeric( $cost ) ? $cost : 0 ),
			)
		);
		$order->add_item( $item );
	}

	/**
	 * Méta FAMMA : canal, saisie d'origine, visite, provenance, remarque, coût interne.
	 *
	 * @param \WC_Order             $order Commande.
	 * @param array<string, string> $row   Ligne de saisie.
	 * @return void
	 */
	private function stamp( \WC_Order $order, array $row ): void {
		$order->update_meta_data( Order_Channel::META, Order_Channel::RECOVERED );
		$order->update_meta_data( self::META_INPUT, (int) $row['id'] );
		$order->update_meta_data( Visitor_Commerce::META_VISITOR, (string) $row['visitor_id'] );
		$order->update_meta_data( Visitor_Commerce::META_SESSION, (string) $row['session_id'] );
		$order->update_meta_data( '_famma_internal_shipping_cost', Config::instance()->internal_shipping_cost() );
		$utm = array(
			'utm_source'   => (string) $row['source'],
			'utm_medium'   => (string) $row['medium'],
			'utm_campaign' => (string) $row['campaign'],
			'utm_content'  => (string) $row['content'],
		);
		foreach ( $utm as $key => $value ) {
			if ( '' !== $value ) {
				$order->update_meta_data( '_famma_' . $key, $value );
			}
		}
		$order->update_meta_data( '_famma_order_source', Order_Source::source( $utm, false ) );
		Order_Note::write( $order, Order_Note::clean( (string) ( $row['note'] ?? '' ) ) );
	}
}
