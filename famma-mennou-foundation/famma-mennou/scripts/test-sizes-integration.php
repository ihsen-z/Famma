<?php
/**
 * Choix de taille et nom complet (famma-core 0.3.0) : tests d'intégration.
 *
 * Lancement, sur la base jetable de scripts/compose-integration.yml uniquement :
 *   docker compose -f scripts/compose-integration.yml run --rm \
 *     -e FAMMA_SIZES_INTEGRATION=1 -e FAMMA_TEST_DB_NAME=famma_integration \
 *     cli eval-file /tests/test-sizes-integration.php
 *
 * Scénarios : taille valide, taille absente, taille hors liste (panier
 * classique ET panier isolé de la commande express), produit sans tailles
 * inchangé, méta d'article « Taille : M » sur la commande, nom trop court.
 * Toutes les fixtures sont marquées `_famma_integration_run` et supprimées
 * à la fin, succès ou échec.
 *
 * @package Famma_Test_Scenarios
 */

defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_SIZES_INTEGRATION' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'Explicit isolated WP-CLI sizes test opt-in required.' );
}
$famma_sizes_db = (string) getenv( 'FAMMA_TEST_DB_NAME' );
if ( 'famma_integration' !== $famma_sizes_db || ! defined( 'DB_NAME' ) || ! hash_equals( $famma_sizes_db, (string) DB_NAME ) || 'db' !== DB_HOST ) {
	throw new RuntimeException( 'Refusing writes outside the explicitly named integration database and host.' );
}
if ( 'local' !== wp_get_environment_type() || ! class_exists( '\Famma\Core\Product_Sizes' ) ) {
	throw new RuntimeException( 'Local clone with famma-core 0.3.0 required.' );
}

/** Scénarios du choix de taille. */
final class Famma_Sizes_Test {

	/**
	 * Marqueur des fixtures de ce passage.
	 *
	 * @var string
	 */
	private static string $run = '';

	/**
	 * Produits créés.
	 *
	 * @var int[]
	 */
	private static array $products = array();

	/**
	 * Commandes créées.
	 *
	 * @var int[]
	 */
	private static array $orders = array();

	/**
	 * Assertions passées.
	 *
	 * @var int
	 */
	private static int $passed = 0;

	/** Lance tous les scénarios, puis nettoie. */
	public static function run(): void {
		self::$run = 'sizes-' . wp_generate_uuid4();
		try {
			wc_load_cart();
			$sized = self::product( 'S, M' );
			$plain = self::product( '' );

			self::cart_scenarios( $sized, $plain );
			self::express_scenarios( $sized, $plain );
			self::order_meta_scenario( $sized );
			self::name_scenarios();
			self::admin_parse_scenarios();

			WP_CLI::success( sprintf( '%d assertions OK.', self::$passed ) );
		} finally {
			self::cleanup();
		}
	}

	/**
	 * Produit simple de test, marqué.
	 *
	 * @param string $sizes Tailles proposées.
	 * @return int
	 */
	private static function product( string $sizes ): int {
		$product = new WC_Product_Simple();
		$product->set_name( '[TEST ' . self::$run . '] produit' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '49' );
		$product->update_meta_data( '_famma_integration_run', self::$run );
		$product->update_meta_data( \Famma\Core\Product_Sizes::META, $sizes );
		$id               = $product->save();
		self::$products[] = $id;
		return $id;
	}

	/**
	 * Ajoute au panier avec la taille postée (null : champ absent).
	 *
	 * @param int         $product_id Produit.
	 * @param string|null $size       Taille postée.
	 * @return string|false Clé de ligne, ou false.
	 */
	private static function add( int $product_id, ?string $size ) {
		unset( $_POST['famma_size'] );
		if ( null !== $size ) {
			$_POST['famma_size'] = $size;
		}
		wc_clear_notices();
		WC()->cart->empty_cart();
		$key = WC()->cart->add_to_cart( $product_id, 1 );
		unset( $_POST['famma_size'] );
		return $key;
	}

	/**
	 * Premier avis d'erreur WooCommerce, en texte.
	 *
	 * @return string
	 */
	private static function first_error(): string {
		$errors = wc_get_notices( 'error' );
		return isset( $errors[0]['notice'] ) ? wp_strip_all_tags( (string) $errors[0]['notice'] ) : '';
	}

	/**
	 * Panier classique (« Ajouter au panier »).
	 *
	 * @param int $sized Produit à tailles.
	 * @param int $plain Produit sans tailles.
	 */
	private static function cart_scenarios( int $sized, int $plain ): void {
		$key = self::add( $sized, 'M' );
		self::assert( 'cart_valid_size', is_string( $key ) && 'M' === ( WC()->cart->get_cart_item( $key )['famma_size'] ?? '' ), 'Taille M acceptée et portée par la ligne.' );

		$key = self::add( $sized, null );
		self::assert( 'cart_missing_size', false === $key && self::first_error() === wp_strip_all_tags( \Famma\Core\Product_Sizes::missing_message() ), 'Sans taille : ajout refusé, message FR + derja.' );

		$key = self::add( $sized, 'XL' );
		self::assert( 'cart_unknown_size', false === $key && self::first_error() === wp_strip_all_tags( \Famma\Core\Product_Sizes::invalid_message() ), 'Taille hors liste : ajout refusé.' );

		$key = self::add( $plain, null );
		self::assert( 'cart_plain_product', is_string( $key ) && ! isset( WC()->cart->get_cart_item( $key )['famma_size'] ), 'Produit sans tailles : ajout inchangé, sans taille.' );

		$key = self::add( $plain, 'M' );
		self::assert( 'cart_plain_ignores_size', is_string( $key ) && ! isset( WC()->cart->get_cart_item( $key )['famma_size'] ), 'Produit sans tailles : taille postée ignorée.' );

		$product = wc_get_product( $sized );
		self::assert( 'loop_links_to_product', ! $product->supports( 'ajax_add_to_cart' ) && $product->add_to_cart_url() === $product->get_permalink(), 'Listes : le bouton mène à la fiche.' );
		self::assert( 'loop_plain_unchanged', wc_get_product( $plain )->supports( 'ajax_add_to_cart' ), 'Listes : produit sans tailles inchangé.' );
		WC()->cart->empty_cart();
		wc_clear_notices();
	}

	/**
	 * Panier isolé de la commande express (chemin de `famma_express_checkout`).
	 *
	 * @param int $sized Produit à tailles.
	 * @param int $plain Produit sans tailles.
	 */
	private static function express_scenarios( int $sized, int $plain ): void {
		$isolation = \Famma\Core\Express_Cart_Isolation::instance();
		$cases     = array(
			array( 'express_valid_size', $sized, 'S', true ),
			array( 'express_missing_size', $sized, null, false ),
			array( 'express_unknown_size', $sized, 'XXL', false ),
			array( 'express_plain_product', $plain, null, true ),
		);
		foreach ( $cases as $case ) {
			list( $name, $product_id, $size, $expected ) = $case;
			unset( $_POST['famma_size'] );
			if ( null !== $size ) {
				$_POST['famma_size'] = $size;
			}
			wc_clear_notices();
			$ok    = $isolation->isolate( $product_id, 1 );
			$lines = array_values( WC()->cart->get_cart() );
			$isolation->restore();
			unset( $_POST['famma_size'] );
			$line_ok = ! $expected || ( 1 === count( $lines ) && (string) ( $size ?? '' ) === (string) ( $lines[0]['famma_size'] ?? '' ) );
			self::assert( $name, $expected === $ok && $line_ok, 'Panier isolé : résultat attendu ' . ( $expected ? 'accepté' : 'refusé' ) . '.' );
		}
		wc_clear_notices();
	}

	/**
	 * Méta d'article « Taille : M » sur la commande.
	 *
	 * @param int $sized Produit à tailles.
	 */
	private static function order_meta_scenario( int $sized ): void {
		$key    = self::add( $sized, 'M' );
		$values = WC()->cart->get_cart_item( (string) $key );
		$order  = wc_create_order();
		$order->update_meta_data( '_famma_integration_run', self::$run );
		self::$orders[] = $order->get_id();

		$item = new WC_Order_Item_Product();
		$item->set_product( wc_get_product( $sized ) );
		$item->set_quantity( 1 );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook natif rejoué comme WC_Checkout::create_order_line_items().
		do_action( 'woocommerce_checkout_create_order_line_item', $item, (string) $key, $values, $order );
		$order->add_item( $item );
		$order->save();

		$saved = null;
		foreach ( wc_get_order( $order->get_id() )->get_items() as $line ) {
			$saved = $line;
		}
		$shown = array();
		foreach ( $saved ? $saved->get_formatted_meta_data() : array() as $meta ) {
			$shown[] = wp_strip_all_tags( $meta->display_key ) . ' : ' . wp_strip_all_tags( $meta->display_value );
		}
		self::assert( 'order_line_meta', $saved instanceof WC_Order_Item_Product && 'M' === $saved->get_meta( 'famma_size' ), 'Méta famma_size = M sur la ligne.' );
		self::assert( 'order_line_display', in_array( __( 'Taille', 'famma-core' ) . ' : M', $shown, true ), 'Affichage « Taille : M » (admin, e-mails, commande reçue).' );
		WC()->cart->empty_cart();
	}

	/** Nom complet : moins de 3 caractères refusé. */
	private static function name_scenarios(): void {
		$tunisia = \Famma\Core\Tunisia::instance();
		$cases   = array(
			array( 'name_too_short', 'Al', '', true ),
			array( 'name_short_with_space', 'A', 'B', false ),
			array( 'name_ok', 'Ali', '', false ),
			array( 'name_empty_left_to_woo', '', '', false ),
		);
		foreach ( $cases as $case ) {
			list( $name, $first, $last, $error ) = $case;
			$errors                              = new WP_Error();
			$tunisia->validate_full_name(
				array(
					'billing_first_name' => $first,
					'billing_last_name'  => $last,
				),
				$errors
			);
			self::assert( $name, $error === $errors->has_errors(), 'Règle des 3 caractères.' );
		}
	}

	/** Saisie de l'administration. */
	private static function admin_parse_scenarios(): void {
		self::assert( 'parse_list', array( 'S', 'M' ) === \Famma\Core\Product_Sizes::parse( ' S, M ,, S ' ), 'Découpage, doublons et vides retirés.' );
		self::assert( 'parse_empty', array() === \Famma\Core\Product_Sizes::parse( '' ), 'Vide : aucun choix.' );
	}

	/**
	 * Enregistre une assertion et s'arrête au premier échec.
	 *
	 * @param string $name    Scénario.
	 * @param bool   $passed  Observation.
	 * @param string $message Comportement attendu.
	 * @throws RuntimeException Échec.
	 */
	private static function assert( string $name, bool $passed, string $message ): void {
		if ( ! $passed ) {
			throw new RuntimeException( esc_html( 'FAIL ' . $name . ': ' . $message ) );
		}
		++self::$passed;
		WP_CLI::log( 'PASS ' . $name . ' — ' . $message );
	}

	/** Supprime uniquement les fixtures de ce passage. */
	private static function cleanup(): void {
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}
		foreach ( self::$orders as $id ) {
			$order = wc_get_order( $id );
			if ( $order && self::$run === $order->get_meta( '_famma_integration_run' ) ) {
				$order->delete( true );
			}
		}
		foreach ( self::$products as $id ) {
			$product = wc_get_product( $id );
			if ( $product && self::$run === $product->get_meta( '_famma_integration_run' ) ) {
				$product->delete( true );
			}
		}
	}
}

Famma_Sizes_Test::run();
