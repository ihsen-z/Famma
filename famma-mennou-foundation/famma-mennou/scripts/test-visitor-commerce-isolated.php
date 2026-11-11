<?php
/**
 * Rattachement visite → commande (Visitor_Commerce) : tests isolés, sans WordPress.
 * Lancer : php scripts/test-visitor-commerce-isolated.php
 *
 * Lot 23, tâche 7 : une commande express passée sans cookie du journal, mais
 * avec les champs cachés du formulaire, reçoit sa session. Le cookie reste
 * prioritaire, un identifiant mal formé est ignoré, et les champs ne sont lus
 * que pendant un checkout dont WooCommerce a vérifié le jeton. La vraie classe
 * est chargée ; les doublures remplacent WordPress, Visitor_Context,
 * Visitor_Store et WC_Order.
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded. The doubles below keep the names,
// namespaces and signatures of the FAMMA and WordPress code they replace.
// phpcs:disable Universal.Namespaces -- The FAMMA doubles and the global WordPress doubles must share this single runner.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Generic.Classes.DuplicateClassName -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI runner output, never served.
// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- The runner sets request superglobals; it never reads user input.

namespace Famma\Core {
	final class Visitor_Context {
		public const VISITOR_COOKIE = 'famma_vid';
		public const SESSION_COOKIE = 'famma_sid';
		public static bool $staff   = false;
		public static function is_staff(): bool {
			return self::$staff;
		}
		public static function is_bot(): bool {
			return false;
		}
		public static function is_uuid( string $value ): bool {
			return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value );
		}
		public static function cookie_id( string $name ): string {
			$raw = isset( $_COOKIE[ $name ] ) ? strtolower( (string) $_COOKIE[ $name ] ) : '';
			return self::is_uuid( $raw ) ? $raw : '';
		}
		public static function device(): string {
			return 'mobile';
		}
		public static function ip_hash(): string {
			return 'hash';
		}
	}

	final class Visitor_Store {
		public static array $rows = array();
		public static function instance(): Visitor_Store {
			return new self();
		}
		public function insert_many( array $rows ): void {
			self::$rows = array_merge( self::$rows, $rows );
		}
	}
}

namespace {
	use Famma\Core\Visitor_Commerce;
	use Famma\Core\Visitor_Context;
	use Famma\Core\Visitor_Store;

	define( 'ABSPATH', __DIR__ . '/' );

	$famma_checkout_checked = false;

	function did_action( string $hook ): int {
		global $famma_checkout_checked;
		return 'woocommerce_checkout_process' === $hook && $famma_checkout_checked ? 1 : 0;
	}
	function add_action( ...$args ): void {}
	function sanitize_text_field( $value ): string {
		return trim( strip_tags( (string) $value ) );
	}
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
	function absint( $value ): int {
		return abs( (int) $value );
	}

	class WC_Order {
		public array $meta = array();
		public function update_meta_data( string $key, $value ): void {
			$this->meta[ $key ] = $value;
		}
		public function get_id(): int {
			return 501;
		}
		public function get_total(): string {
			return '49.000';
		}
		public function get_item_count(): int {
			return 1;
		}
	}

	require dirname( __DIR__ ) . '/wp-content/plugins/famma-core/includes/visitor-commerce.php';

	$passed = 0;
	$failed = 0;

	function check( bool $condition, string $message ): void {
		global $passed, $failed;
		if ( $condition ) {
			++$passed;
			echo 'PASS: ' . $message . PHP_EOL;
			return;
		}
		++$failed;
		echo 'FAIL: ' . $message . PHP_EOL;
	}

	const COOKIE_V = '11111111-1111-4111-8111-111111111111';
	const COOKIE_S = '22222222-2222-4222-8222-222222222222';
	const FORM_V   = '33333333-3333-4333-a333-333333333333';
	const FORM_S   = '44444444-4444-4444-b444-444444444444';

	/**
	 * Une commande express : cookies et champs donnés, puis les vrais crochets.
	 *
	 * @param array<string, string> $cookies Cookies de la requête.
	 * @param array<string, mixed>  $post    Champs postés.
	 * @param bool                  $checked Jeton du checkout vérifié par WooCommerce.
	 * @return WC_Order
	 */
	function place( array $cookies, array $post, bool $checked = true ): WC_Order {
		global $famma_checkout_checked;
		$_COOKIE                = $cookies;
		$_POST                  = $post;
		$famma_checkout_checked = $checked;
		Visitor_Store::$rows    = array();
		$famma_order            = new WC_Order();
		$commerce               = Visitor_Commerce::instance();
		$commerce->stamp_order( $famma_order );
		$commerce->order_placed( 501, array(), $famma_order );
		return $famma_order;
	}

	function session_of( WC_Order $famma_order ): string {
		return (string) ( $famma_order->meta[ Visitor_Commerce::META_SESSION ] ?? '' );
	}

	$form = array(
		Visitor_Commerce::FIELD_VISITOR => FORM_V,
		Visitor_Commerce::FIELD_SESSION => FORM_S,
	);

	$famma_order = place( array(), $form );
	check( FORM_S === session_of( $famma_order ) && FORM_V === $famma_order->meta[ Visitor_Commerce::META_VISITOR ], 'sans cookie, avec les champs : la commande reçoit la session du formulaire' );
	check( 1 === count( Visitor_Store::$rows ) && 'order_placed' === Visitor_Store::$rows[0]['event'] && FORM_S === Visitor_Store::$rows[0]['session_id'], 'sans cookie : order_placed entre dans la session (entonnoir du formulaire)' );

	$famma_order = place(
		array(
			'famma_vid' => COOKIE_V,
			'famma_sid' => COOKIE_S,
		),
		$form
	);
	check( COOKIE_S === session_of( $famma_order ) && COOKIE_V === $famma_order->meta[ Visitor_Commerce::META_VISITOR ], 'cookies présents : ils restent prioritaires' );

	$famma_order = place( array( 'famma_vid' => COOKIE_V ), $form );
	check( COOKIE_V === $famma_order->meta[ Visitor_Commerce::META_VISITOR ] && FORM_S === session_of( $famma_order ), 'seul le cookie de session manque : visiteur du cookie, session du formulaire' );

	$famma_order = place( array(), array_map( 'strtoupper', $form ) );
	check( FORM_S === session_of( $famma_order ), 'majuscules : ramenées en minuscules' );

	foreach (
		array(
			'pas un UUID' => 'abc',
			'UUID non v4' => '44444444-4444-1444-b444-444444444444',
			'injection'   => FORM_S . "' OR 1=1",
			'trop long'   => FORM_S . '0',
		) as $case => $value
	) {
		$famma_order = place(
			array(),
			array(
				Visitor_Commerce::FIELD_VISITOR => FORM_V,
				Visitor_Commerce::FIELD_SESSION => $value,
			)
		);
		check( array() === $famma_order->meta && array() === Visitor_Store::$rows, 'identifiant mal formé (' . $case . ') : ignoré, rien n’est écrit' );
	}

	$famma_order = place(
		array(),
		array(
			Visitor_Commerce::FIELD_VISITOR => array( FORM_V ),
			Visitor_Commerce::FIELD_SESSION => FORM_S,
		)
	);
	check( array() === $famma_order->meta, 'champ envoyé en tableau : ignoré' );

	$famma_order = place( array(), $form, false );
	check( array() === $famma_order->meta && array() === Visitor_Store::$rows, 'hors d’un checkout au jeton vérifié : les champs ne sont pas lus' );

	$famma_order = place( array(), array() );
	check( array() === $famma_order->meta, 'ni cookie ni champ : la commande reste sans session, comme avant' );

	Visitor_Context::$staff = true;
	$famma_order            = place( array(), $form );
	check( array() === $famma_order->meta && array() === Visitor_Store::$rows, 'équipe connectée : rien, même avec les champs' );

	echo 'RESULT ' . $passed . '/' . ( $passed + $failed ) . ' contrôles du rattachement visite → commande réussis' . ( $failed ? ', ' . $failed . ' ÉCHEC(S)' : '' ) . '.' . PHP_EOL;
	exit( $failed ? 1 : 0 );
}
