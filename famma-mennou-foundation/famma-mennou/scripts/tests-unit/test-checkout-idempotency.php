<?php
/**
 * Lot 5, D3 — Checkout_Idempotency (includes/checkout-idempotency.php), sans base ni réseau.
 * Run with: php scripts/tests-unit/test-checkout-idempotency.php
 *
 * Doublures autonomes (ce fichier ne charge pas bootstrap.php, dont la session
 * WooCommerce n'a pas d'identifiant client) : wp_options est un tableau en
 * mémoire qui reproduit INSERT IGNORE sur la clé unique option_name, et
 * usleep() est remplacé par double-checkout-clock.php (attente mesurée, sans
 * dormir ; la requête concurrente est jouée à chaque pas). La preuve
 * de bout en bout (2 POST simultanés → 1 commande) se fait dans la campagne de
 * tests, une fois le câblage posé.
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Generic.Classes.DuplicateClassName -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable Squiz.Commenting.FunctionComment.WrongStyle, Squiz.Commenting.ClassComment.WrongStyle -- Line comments label groups of doubles.
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode, WordPress.WP.GlobalVariablesOverride.Prohibited -- Standalone runner: WordPress is never loaded, its globals are doubles.

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

// Every Warning, Notice or Deprecated raised during the suite counts as a failure (checked at the end).
// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting, WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting -- Standalone CLI runner: all errors must surface, never served to a browser.
error_reporting( E_ALL );
$GLOBALS['t_php_errors'] = array();
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Standalone runner: errors are collected, then reported as failures.
set_error_handler(
	static function ( int $errno, string $errstr, string $errfile, int $errline ): bool {
		$GLOBALS['t_php_errors'][] = $errno . ' ' . $errstr . ' (' . basename( $errfile ) . ':' . $errline . ')';
		return true;
	}
);

// Hooks: listeners really run; priorities are recorded for the wiring checks.
function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['t_hooks'][ $hook ][] = array( $callback, $accepted_args, $priority );
	return true;
}
function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	return add_action( $hook, $callback, $priority, $accepted_args );
}
function do_action( string $hook, ...$args ): void {
	$GLOBALS['t_did'][ $hook ] = ( $GLOBALS['t_did'][ $hook ] ?? 0 ) + 1;
	foreach ( $GLOBALS['t_hooks'][ $hook ] ?? array() as $listener ) {
		call_user_func_array( $listener[0], array_slice( $args, 0, $listener[1] ) );
	}
}
function apply_filters( string $hook, $value, ...$args ) {
	foreach ( $GLOBALS['t_hooks'][ $hook ] ?? array() as $listener ) {
		$value = call_user_func_array( $listener[0], array_slice( array_merge( array( $value ), $args ), 0, $listener[1] ) );
	}
	return $value;
}
function did_action( string $hook ): int {
	return (int) ( $GLOBALS['t_did'][ $hook ] ?? 0 );
}
function doing_action( ?string $hook = null ): bool {
	return in_array( $hook, $GLOBALS['t_doing'] ?? array(), true );
}

// Translation, escaping, salts, sessions.
function __( string $text, string $domain = 'default' ): string {
	return $text;
}
function esc_html( $text ): string {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url_raw( string $url ): string {
	return $url;
}
function wp_json_encode( $value ) {
	return json_encode( $value );
}
function wp_salt( string $scheme = 'auth' ): string {
	return 'test-salt-' . $scheme;
}
function wp_get_session_token(): string {
	return '';
}
function wp_generate_uuid4(): string {
	return sprintf( '%04x%04x-%04x-4%03x-%04x-%012x', random_int( 0, 0xffff ), random_int( 0, 0xffff ), random_int( 0, 0xffff ), random_int( 0, 0xfff ), random_int( 0x8000, 0xbfff ), random_int( 0, 0xffffffffffff ) );
}
function wc_notice_count( string $type = '' ): int {
	return (int) ( $GLOBALS['t_notices'] ?? 0 );
}
function wp_doing_ajax(): bool {
	return ! empty( $GLOBALS['t_ajax'] );
}
final class Famma_Test_Exit extends RuntimeException {
	public function __construct( public string $kind, public $payload ) {
		parent::__construct( $kind );
	}
}
function wp_send_json( $response ): void {
	throw new Famma_Test_Exit( 'json', $response );
}
function wp_safe_redirect( string $location ): bool {
	throw new Famma_Test_Exit( 'redirect', $location );
}
final class WC_Test_Logger {
	public function log( string $level, string $message, array $context = array() ): void {
		$GLOBALS['t_log'][] = $level . ' ' . ( $context['source'] ?? '' ) . ' ' . $message;
	}
}
function wc_get_logger(): WC_Test_Logger {
	return new WC_Test_Logger();
}
final class WP_Error {
	public function __construct( private array $codes = array() ) {}
	public function has_errors(): bool {
		return array() !== $this->codes;
	}
}

// WooCommerce: session, cart, orders.
final class WC_Test_Session {
	public function __construct( public string $customer = 't_qa_l5_a' ) {}
	public function get_customer_id(): string {
		return $this->customer;
	}
}
final class WC_Test_Cart {
	public function __construct( public array $lines = array(), public array $coupons = array() ) {}
	public function get_cart(): array {
		return $this->lines;
	}
	public function get_applied_coupons(): array {
		return $this->coupons;
	}
	public function is_empty(): bool {
		return array() === $this->lines;
	}
	public function empty_cart( bool $clear_persistent_cart = true ): void {
		$this->lines          = array();
		$this->coupons        = array();
		$GLOBALS['t_emptied'] = (int) ( $GLOBALS['t_emptied'] ?? 0 ) + 1;
	}
}
final class WC_Test_Store {
	public function __construct( public ?WC_Test_Cart $cart = null, public ?WC_Test_Session $session = null ) {}
}
// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- WooCommerce's own function name.
function WC(): WC_Test_Store {
	return $GLOBALS['t_store'];
}
final class WC_Order {
	public array $meta = array();
	public function __construct( public int $id = 0, public string $wc_status = 'pending-confirm' ) {}
	public function get_id(): int {
		return $this->id;
	}
	public function get_meta( string $key ) {
		return $this->meta[ $key ] ?? '';
	}
	public function update_meta_data( string $key, $value ): void {
		$this->meta[ $key ] = $value;
	}
	public function has_status( $wc_status ): bool {
		return in_array( $this->wc_status, (array) $wc_status, true );
	}
	public function get_checkout_order_received_url(): string {
		return 'https://qa.invalid/commande/order-received/' . $this->id . '/?key=wc_order_qa_l5';
	}
}
function wc_get_orders( array $args ): array {
	$GLOBALS['t_order_args'] = $args;
	$found                   = array();
	foreach ( $GLOBALS['t_orders'] ?? array() as $wc_order ) {
		if ( $wc_order->get_meta( $args['meta_key'] ) === $args['meta_value'] ) {
			$found[] = $wc_order;
		}
	}
	return array_slice( $found, 0, (int) $args['limit'] );
}

// wp_options in memory: INSERT IGNORE honours the unique option_name key.
final class Famma_Test_Wpdb {
	public string $options = 'wp_options';
	public array $rows     = array();
	public bool $fail      = false;
	public array $sql      = array();
	public int $reads      = 0;
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}
	public function prepare( string $query, ...$args ): string {
		return (string) json_encode( array( $query, $args ) );
	}
	public function query( string $prepared ) {
		list( $sql, $args ) = json_decode( $prepared, true );
		$this->sql[]        = $sql;
		if ( $this->fail ) {
			return false;
		}
		if ( str_starts_with( $sql, 'INSERT IGNORE' ) ) {
			if ( isset( $this->rows[ $args[1] ] ) ) {
				return 0;
			}
			$this->rows[ $args[1] ] = $args[2];
			return 1;
		}
		if ( str_contains( $sql, 'LIKE' ) ) {
			$prefix  = str_replace( array( '\\_', '\\%' ), array( '_', '%' ), substr( $args[1], 0, -1 ) );
			$deleted = 0;
			foreach ( $this->rows as $name => $value ) {
				if ( str_starts_with( $name, $prefix ) && (int) strtok( $value, '|' ) < $args[2] && $deleted < 20 ) {
					unset( $this->rows[ $name ] );
					++$deleted;
				}
			}
			return $deleted;
		}
		if ( ( $this->rows[ $args[1] ] ?? null ) === $args[2] ) {
			unset( $this->rows[ $args[1] ] );
			return 1;
		}
		return 0;
	}
	public function get_var( string $prepared ) {
		list( , $args ) = json_decode( $prepared, true );
		++$this->reads;
		return $this->fail ? null : ( $this->rows[ $args[1] ] ?? null );
	}
}

require_once __DIR__ . '/double-checkout-clock.php';
require_once ABSPATH . 'wp-content/plugins/famma-core/includes/express-checkout.php';
require_once ABSPATH . 'wp-content/plugins/famma-core/includes/express-idempotency.php';
require_once ABSPATH . 'wp-content/plugins/famma-core/includes/checkout-idempotency.php';

use Famma\Core\Checkout_Idempotency;

$checks   = 0;
$failures = 0;
$check    = static function ( string $label, bool $ok ) use ( &$checks, &$failures ): void {
	++$checks;
	if ( ! $ok ) {
		++$failures;
	}
	echo ( $ok ? 'OK    ' : 'ECHEC ' ) . $label . PHP_EOL;
};
$line     = static function ( int $product, int $quantity, string $size = '' ): array {
	return array(
		'key'  => md5( $product . '|' . $size ),
		'line' => array(
			'product_id'   => $product,
			'variation_id' => 0,
			'quantity'     => $quantity,
			'famma_size'   => $size,
		),
	);
};
$cart     = static function ( array $lines, array $coupons = array() ): WC_Test_Cart {
	$items = array();
	foreach ( $lines as $entry ) {
		$items[ $entry['key'] ] = $entry['line'];
	}
	return new WC_Test_Cart( $items, $coupons );
};
$reset    = static function ( ?WC_Test_Cart $basket = null, string $customer = 't_qa_l5_a' ) use ( $line, $cart ): void {
	$GLOBALS['t_hooks']   = array();
	$GLOBALS['t_did']     = array();
	$GLOBALS['t_doing']   = array();
	$GLOBALS['t_log']     = array();
	$GLOBALS['t_orders']  = array();
	$GLOBALS['t_notices'] = 0;
	$GLOBALS['t_ajax']    = true;
	$GLOBALS['t_slept']   = 0;
	$GLOBALS['t_emptied'] = 0;
	$GLOBALS['wpdb']      = new Famma_Test_Wpdb();
	$GLOBALS['t_store']   = new WC_Test_Store( $basket ?? $cart( array( $line( 9001, 1, 'M' ) ) ), new WC_Test_Session( $customer ) );
	unset( $GLOBALS['t_on_sleep'], $_POST['woocommerce_checkout_update_totals'] );
};
$refill   = static function () use ( $line, $cart ): void {
	$GLOBALS['t_store']->cart = $cart( array( $line( 9001, 1, 'M' ) ) );
};
$guard    = static function ( Checkout_Idempotency $request ): string {
	try {
		$request->guard();
		return 'continue';
	} catch ( Famma_Test_Exit $exit ) {
		$GLOBALS['t_exit'] = $exit;
		return $exit->kind;
	} catch ( Exception $e ) {
		$GLOBALS['t_exception'] = $e->getMessage();
		return 'exception';
	}
};
$locks    = static function (): array {
	return array_filter( $GLOBALS['wpdb']->rows, static fn( string $name ): bool => str_starts_with( $name, Checkout_Idempotency::LOCK_PREFIX ), ARRAY_FILTER_USE_KEY );
};

// 1. Empreinte du panier et clé.
$a = $line( 9001, 1, 'M' );
$b = $line( 9002, 2 );
$check( 'empreinte indépendante de l’ordre des lignes', Checkout_Idempotency::cart_hash( $cart( array( $a, $b ) )->get_cart(), array() ) === Checkout_Idempotency::cart_hash( $cart( array( $b, $a ) )->get_cart(), array() ) );
$check( 'empreinte change avec la quantité', Checkout_Idempotency::cart_hash( $cart( array( $a ) )->get_cart(), array() ) !== Checkout_Idempotency::cart_hash( $cart( array( $line( 9001, 2, 'M' ) ) )->get_cart(), array() ) );
$check( 'empreinte change avec la taille (clé de ligne)', Checkout_Idempotency::cart_hash( $cart( array( $a ) )->get_cart(), array() ) !== Checkout_Idempotency::cart_hash( $cart( array( $line( 9001, 1, 'L' ) ) )->get_cart(), array() ) );
$check( 'empreinte change avec un code promo', Checkout_Idempotency::cart_hash( $cart( array( $a ) )->get_cart(), array() ) !== Checkout_Idempotency::cart_hash( $cart( array( $a ) )->get_cart(), array( 'qa-l5' ) ) );
$check( 'clé différente pour une autre session', Checkout_Idempotency::key( 's1', 'c' ) !== Checkout_Idempotency::key( 's2', 'c' ) );
$check( 'clé stable pour la même session et le même panier', Checkout_Idempotency::key( 's1', 'c' ) === Checkout_Idempotency::key( 's1', 'c' ) );

// 2. Câblage : crochets et priorités (Capi 999 et Express_Idempotency 10000 inchangés).
$reset();
Checkout_Idempotency::instance();
$priority = static function ( string $hook ): array {
	return array_map( static fn( array $l ): int => $l[2], $GLOBALS['t_hooks'][ $hook ] ?? array() );
};
$check( 'verrou pris à woocommerce_before_checkout_process, priorité 1', array( 1 ) === $priority( 'woocommerce_before_checkout_process' ) );
$check( 'clé posée à woocommerce_checkout_create_order, priorité 2', array( 2 ) === $priority( 'woocommerce_checkout_create_order' ) );
$check( 'libération après validation, paiement et commande gratuite (PHP_INT_MAX)', array( PHP_INT_MAX ) === $priority( 'woocommerce_after_checkout_validation' ) && array( PHP_INT_MAX ) === $priority( 'woocommerce_payment_successful_result' ) && array( PHP_INT_MAX ) === $priority( 'woocommerce_checkout_no_payment_needed_redirect' ) );
$check( 'aucun crochet shutdown avant la prise du verrou', array() === $priority( 'shutdown' ) );

// 3. Première soumission : verrou atomique non autochargé, échéance ~60 s.
$reset();
$first = new Checkout_Idempotency();
$check( 'première soumission : le checkout continue', 'continue' === $guard( $first ) );
$held  = $locks();
$value = (string) reset( $held );
$check( 'une seule ligne de verrou', 1 === count( $held ) );
$check( 'verrou inséré par INSERT IGNORE, autoload off', str_contains( $GLOBALS['wpdb']->sql[0], 'INSERT IGNORE' ) && str_contains( $GLOBALS['wpdb']->sql[0], "'off'" ) );
$check( 'échéance du verrou à 60 s', abs( (int) strtok( $value, '|' ) - ( time() + 60 ) ) <= 1 );
$check( 'libération enregistrée sur shutdown', array( 10 ) === $priority( 'shutdown' ) );

// 4. Seconde soumission simultanée (même session, même panier) : attente bornée, puis refus sans commande.
$second = new Checkout_Idempotency();
$check( 'seconde soumission : erreur de checkout', 'exception' === $guard( $second ) );
$check( 'attente bornée par défaut : 5 s, verrou relu tous les 250 ms', 5000000 === $GLOBALS['t_slept'] && 21 === $GLOBALS['wpdb']->reads );
$message = (string) $GLOBALS['t_exception'];
$check( 'message : commande déjà envoyée, ne pas la renvoyer', str_contains( $message, 'Cette commande a déjà été envoyée' ) && str_contains( $message, 'Ne la renvoyez pas' ) );
$check( 'message : ne promet plus une confirmation à l’écran', ! str_contains( $message, 'va s’afficher' ) && ! str_contains( $message, 'Patientez' ) );
$check( 'message : dit comment la commande est confirmée (appel, WhatsApp)', str_contains( $message, 'nous vous appelons pour la confirmer' ) && str_contains( $message, 'WhatsApp' ) );
$check( 'message : version derja présente', str_contains( $message, 'ما تعاودش تبعثها' ) && str_contains( $message, 'واتساب' ) );
$check( 'message de l’exception identique à busy_message()', esc_html( Checkout_Idempotency::busy_message() ) === $message );
$check( 'le verrou de la première est intact', $locks() === $held );
$check( 'issue journalisée sans donnée client', in_array( 'info famma-checkout checkout_duplicate reason=locked order=0', $GLOBALS['t_log'], true ) );
$check( 'action famma_checkout_duplicate déclenchée', 1 === did_action( 'famma_checkout_duplicate' ) );
$wc_order = new WC_Order( 7001 );
$second->stamp( $wc_order );
$check( 'la requête refusée ne pose aucune clé', '' === $wc_order->get_meta( Checkout_Idempotency::META_KEY ) );

// 5. Un autre panier ou une autre session ne sont pas bloqués.
$GLOBALS['t_store']->cart = $cart( array( $line( 9003, 1 ) ) );
$check( 'autre panier, même session : verrou distinct', 'continue' === $guard( new Checkout_Idempotency() ) && 2 === count( $locks() ) );
$GLOBALS['t_store']->cart    = $cart( array( $a ) );
$GLOBALS['t_store']->session = new WC_Test_Session( 't_qa_l5_b' );
$check( 'même panier, autre session : verrou distinct', 'continue' === $guard( new Checkout_Idempotency() ) && 3 === count( $locks() ) );

// 6. Clé posée sur la commande, puis libération après paiement accepté.
$reset();
$first = new Checkout_Idempotency();
$guard( $first );
$wc_order = new WC_Order( 7002 );
$first->stamp( $wc_order );
$check( 'clé posée sur la commande avant enregistrement', 64 === strlen( (string) $wc_order->get_meta( Checkout_Idempotency::META_KEY ) ) );
$result = $first->settled( array( 'result' => 'success' ) );
$check( 'paiement accepté : résultat rendu tel quel', array( 'result' => 'success' ) === $result );
$check( 'paiement accepté : verrou libéré', array() === $locks() );
$first->release();
$check( 'libération idempotente (shutdown après succès)', array() === $locks() );

// 7. Échec de validation : libéré ; validation sans erreur : conservé.
$reset();
$first = new Checkout_Idempotency();
$guard( $first );
$first->validation( array(), new WP_Error() );
$check( 'validation sans erreur : verrou conservé', 1 === count( $locks() ) );
$first->validation( array(), new WP_Error( array( 'billing_phone' ) ) );
$check( 'validation en échec : verrou libéré', array() === $locks() );
$reset();
$first = new Checkout_Idempotency();
$guard( $first );
$GLOBALS['t_notices'] = 1;
$first->validation( array(), new WP_Error() );
$check( 'notice d’erreur (Anti_Abuse) : verrou libéré', array() === $locks() );

// 8. Verrou échu (requête interrompue) : repris ; libération jamais sur le verrou d'autrui.
$reset();
$first = new Checkout_Idempotency();
$guard( $first );
$name                           = (string) array_key_first( $locks() );
$GLOBALS['wpdb']->rows[ $name ] = ( time() - 5 ) . '|stale';
$second                         = new Checkout_Idempotency();
$check( 'verrou échu : repris par la soumission suivante', 'continue' === $guard( $second ) && 'stale' !== substr( (string) $GLOBALS['wpdb']->rows[ $name ], -5 ) );
$taken = $GLOBALS['wpdb']->rows[ $name ];
$first->release();
$check( 'l’ancienne requête ne supprime pas le verrou repris', ( $GLOBALS['wpdb']->rows[ $name ] ?? '' ) === $taken );

// 9. Purge des verrous échus à la libération.
$reset();
$GLOBALS['wpdb']->rows['famma_checkout_lock_dead']   = ( time() - 30 ) . '|x';
$GLOBALS['wpdb']->rows['famma_checkout_lock_live']   = ( time() + 30 ) . '|y';
$GLOBALS['wpdb']->rows['famma_express_registry_ver'] = '1';
$first = new Checkout_Idempotency();
$guard( $first );
$first->release();
$check( 'purge : verrou échu supprimé', ! isset( $GLOBALS['wpdb']->rows['famma_checkout_lock_dead'] ) );
$check( 'purge : verrou vivant et autres options conservés', isset( $GLOBALS['wpdb']->rows['famma_checkout_lock_live'], $GLOBALS['wpdb']->rows['famma_express_registry_ver'] ) );

// 10. Commande déjà créée par cette session pour ce panier : renvoi vers sa confirmation.
$reset();
$probe = new Checkout_Idempotency();
$guard( $probe );
$known = new WC_Order( 7003 );
$probe->stamp( $known );
$probe->release();
$GLOBALS['t_orders'] = array( $known );
$replayed            = false;
add_filter(
	'woocommerce_payment_successful_result',
	static function ( $result ) use ( &$replayed ) {
		$replayed = true;
		return $result;
	}
);
$check( 'requête AJAX : réponse JSON de succès', 'json' === $guard( new Checkout_Idempotency() ) );
$payload = $GLOBALS['t_exit']->payload;
$check( 'renvoi vers la page de confirmation de la commande existante', 'success' === $payload['result'] && $known->get_checkout_order_received_url() === $payload['redirect'] && 7003 === $payload['order_id'] && true === $payload['famma_replay'] );
$check( 'verrou libéré avant la réponse', array() === $locks() );
$check( 'renvoi : panier vidé comme après le paiement de la commande', 1 === $GLOBALS['t_emptied'] && WC()->cart->is_empty() );
$check( 'renvoi immédiat : aucune attente', 0 === $GLOBALS['t_slept'] );
$check( 'renvoi journalisé', in_array( 'info famma-checkout checkout_duplicate reason=replayed order=7003', $GLOBALS['t_log'], true ) );
$args = $GLOBALS['t_order_args'];
$check( 'recherche bornée : 1 commande, 2 dernières minutes, par méta', 1 === $args['limit'] && Checkout_Idempotency::META_KEY === $args['meta_key'] && abs( (int) substr( $args['date_created'], 1 ) - ( time() - 120 ) ) <= 1 && '>' === $args['date_created'][0] );
$check( 'filtres de paiement (Capi, Express_Idempotency) non rejoués', false === $replayed );
$refill();
$GLOBALS['t_ajax'] = false;
$check( 'sans JavaScript : redirection vers la confirmation', 'redirect' === $guard( new Checkout_Idempotency() ) && $known->get_checkout_order_received_url() === $GLOBALS['t_exit']->payload );
$GLOBALS['t_ajax'] = true;
$refill();
foreach ( array( 'pending', 'failed', 'cancelled', 'checkout-draft' ) as $wc_status ) {
	$known->wc_status = $wc_status;
	$check( 'commande « ' . $wc_status . ' » : pas de renvoi, WooCommerce la reprend', 'continue' === $guard( new Checkout_Idempotency() ) && ! WC()->cart->is_empty() );
	$GLOBALS['wpdb']->rows = array();
}
$known->wc_status            = 'pending-confirm';
$GLOBALS['t_store']->session = new WC_Test_Session( 't_qa_l5_other' );
$check( 'autre session : pas de renvoi vers la commande d’autrui', 'continue' === $guard( new Checkout_Idempotency() ) );

// 10 bis. Attente bornée : la soumission concurrente se termine pendant l'attente.
$reset();
$first = new Checkout_Idempotency();
$guard( $first );
$created = new WC_Order( 7005 );
$first->stamp( $created );
$steps                 = 0;
$GLOBALS['t_on_sleep'] = static function () use ( &$steps, $first, $created ): void {
	if ( 2 === ++$steps ) {
		$GLOBALS['t_orders'] = array( $created );
		$first->settled( array( 'result' => 'success' ) );
	}
};
$check( 'attente : la première crée la commande, la seconde renvoie vers sa confirmation', 'json' === $guard( new Checkout_Idempotency() ) && 7005 === $GLOBALS['t_exit']->payload['order_id'] );
$check( 'attente : arrêtée dès la libération (2 pas de 250 ms)', 500000 === $GLOBALS['t_slept'] );
$check( 'attente : panier vidé, aucun verrou restant', 1 === $GLOBALS['t_emptied'] && array() === $locks() );
$check( 'attente : renvoi journalisé', in_array( 'info famma-checkout checkout_duplicate reason=replayed order=7005', $GLOBALS['t_log'], true ) );

$reset();
$first = new Checkout_Idempotency();
$guard( $first );
$GLOBALS['t_on_sleep'] = static function () use ( $first ): void {
	$first->validation( array(), new WP_Error( array( 'billing_phone' ) ) );
};
$second                = new Checkout_Idempotency();
$check( 'attente : la première échoue sans commande, la seconde reprend la main', 'continue' === $guard( $second ) && 1 === count( $locks() ) && 250000 === $GLOBALS['t_slept'] );
$check( 'attente : reprise journalisée', in_array( 'info famma-checkout checkout_duplicate reason=retried order=0', $GLOBALS['t_log'], true ) );
$wc_order = new WC_Order( 7006 );
$second->stamp( $wc_order );
$check( 'reprise : la seconde pose sa clé sur sa propre commande', 64 === strlen( (string) $wc_order->get_meta( Checkout_Idempotency::META_KEY ) ) );

$reset();
$first = new Checkout_Idempotency();
$guard( $first );
$unpaid = new WC_Order( 7007, 'pending' );
$first->stamp( $unpaid );
$GLOBALS['t_on_sleep'] = static function () use ( $first, $unpaid ): void {
	$GLOBALS['t_orders'] = array( $unpaid );
	$first->release();
};
$check( 'attente : commande « pending » de la première, session périmée : refus sans nouvelle commande', 'exception' === $guard( new Checkout_Idempotency() ) && array() === $locks() && ! WC()->cart->is_empty() );
$check( 'refus journalisé avec la commande concernée', in_array( 'info famma-checkout checkout_duplicate reason=locked order=7007', $GLOBALS['t_log'], true ) );

$reset();
$guard( new Checkout_Idempotency() );
$name                  = (string) array_key_first( $locks() );
$GLOBALS['t_on_sleep'] = static function () use ( $name ): void {
	$GLOBALS['wpdb']->rows[ $name ] = ( time() - 1 ) . '|killed';
};
$check( 'attente : verrou échu pendant l’attente (requête interrompue) : repris', 'continue' === $guard( new Checkout_Idempotency() ) && 'killed' !== substr( (string) $GLOBALS['wpdb']->rows[ $name ], -6 ) && 250000 === $GLOBALS['t_slept'] );

$reset();
$guard( new Checkout_Idempotency() );
$GLOBALS['t_on_sleep'] = static function (): void {
	$GLOBALS['wpdb']->fail = true;
};
$check( 'attente : base indisponible pendant l’attente, la vente n’est pas bloquée', 'continue' === $guard( new Checkout_Idempotency() ) && in_array( 'warning famma-checkout checkout_duplicate reason=lock_error order=0', $GLOBALS['t_log'], true ) );

$waits = array(
	0    => 0,
	-5   => 0,
	1000 => 10000000,
);
foreach ( $waits as $asked => $slept ) {
	$reset();
	add_filter( 'famma_checkout_busy_wait', static fn(): int => $asked );
	$guard( new Checkout_Idempotency() );
	$check( 'attente demandée ' . $asked . ' s : ' . intdiv( $slept, 1000000 ) . ' s au plus, puis refus', 'exception' === $guard( new Checkout_Idempotency() ) && $slept === $GLOBALS['t_slept'] );
}

// 11. Hors champ : express, recalcul des totaux, panier vide, filtre.
$reset();
$GLOBALS['t_did']['famma_express_reserved'] = 1;
$check( 'commande express (registre réservé) : aucun verrou', 'continue' === $guard( new Checkout_Idempotency() ) && array() === $locks() );
$reset();
$GLOBALS['t_doing'] = array( 'wc_ajax_' . \Famma\Core\Express_Checkout::ENDPOINT );
$check( 'commande express (point d’entrée en cours) : aucun verrou', 'continue' === $guard( new Checkout_Idempotency() ) && array() === $locks() );
$reset();
$_POST['woocommerce_checkout_update_totals'] = '1';
$check( 'simple recalcul des totaux : aucun verrou', 'continue' === $guard( new Checkout_Idempotency() ) && array() === $locks() );
$reset( new WC_Test_Cart() );
$check( 'panier vide : laissé à WooCommerce', 'continue' === $guard( new Checkout_Idempotency() ) && array() === $locks() );
$reset();
add_filter( 'famma_checkout_idempotency_applies', static fn(): bool => false );
$check( 'filtre famma_checkout_idempotency_applies à faux : aucun verrou', 'continue' === $guard( new Checkout_Idempotency() ) && array() === $locks() );

// 12. Base indisponible : la vente n'est pas bloquée, l'incident est tracé.
$reset();
$GLOBALS['wpdb']->fail = true;
$first                 = new Checkout_Idempotency();
$wc_order              = new WC_Order( 7004 );
$check( 'erreur SQL : le checkout continue', 'continue' === $guard( $first ) );
$first->stamp( $wc_order );
$check( 'erreur SQL : aucune clé posée', '' === $wc_order->get_meta( Checkout_Idempotency::META_KEY ) );
$check( 'erreur SQL : avertissement journalisé', in_array( 'warning famma-checkout checkout_duplicate reason=lock_error order=0', $GLOBALS['t_log'], true ) );

// 13. Durée du verrou bornée.
$bounds = array(
	1000 => 300,
	1    => 10,
);
foreach ( $bounds as $asked => $expected ) {
	$reset();
	add_filter( 'famma_checkout_lock_ttl', static fn(): int => $asked );
	$guard( new Checkout_Idempotency() );
	$held = $locks();
	$check( 'durée demandée ' . $asked . ' s bornée à ' . $expected . ' s', abs( (int) strtok( (string) reset( $held ), '|' ) - ( time() + $expected ) ) <= 1 );
}

// 14. Source : jamais add_option() (upsert non atomique), toujours $wpdb->prepare().
$source = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/checkout-idempotency.php' );
$calls  = array();
foreach ( token_get_all( $source ) as $token ) {
	if ( is_array( $token ) && T_STRING === $token[0] ) {
		$calls[ $token[1] ] = true;
	}
}
$check( 'source : aucun appel à add_option()', ! isset( $calls['add_option'] ) );
$check( 'source : chaque requête passe par prepare()', substr_count( $source, '$wpdb->query(' ) + substr_count( $source, '$wpdb->get_var(' ) === substr_count( $source, '$wpdb->prepare(' ) );

// 15. La suite elle-même ne lève ni Warning, ni Notice, ni Deprecated.
restore_error_handler();
foreach ( $GLOBALS['t_php_errors'] as $php_error ) {
	echo 'PHP   ' . $php_error . PHP_EOL;
}
$check( 'aucun Warning, Notice ni Deprecated pendant la suite', array() === $GLOBALS['t_php_errors'] );

echo 'CHECKS=' . $checks . ' ECHECS=' . $failures . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
exit( $failures > 0 ? 1 : 0 );
