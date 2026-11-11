<?php
/**
 * Collecte des visites (Visitor_Collector) : tests isolés, sans WordPress ni base.
 * Lancer : php scripts/test-visitor-collector-isolated.php
 *
 * Couvre l'événement `form_error` (lot 23) : seuls les codes de la liste
 * blanche entrent dans la base, jamais une valeur tapée, et un lot sans code
 * valable n'écrit aucune ligne. La vraie classe est chargée ; les doublures
 * remplacent WordPress, Visitor_Context et Visitor_Store.
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

namespace Famma\Core {
	final class Visitor_Context {
		public static function is_bot(): bool {
			return false;
		}
		public static function is_staff(): bool {
			return false;
		}
		public static function text( $value, int $max ): string {
			return is_scalar( $value ) ? substr( trim( (string) $value ), 0, $max ) : '';
		}
		public static function is_uuid( string $value ): bool {
			return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $value );
		}
		public static function ip_hash(): string {
			return 'hash';
		}
		public static function rate_limited( string $prefix, int $limit ): bool {
			return false;
		}
		public static function path( $value ): string {
			return is_string( $value ) ? $value : '';
		}
		public static function device(): string {
			return 'mobile';
		}
	}

	final class Visitor_Store {
		public static array $rows = array();
		public static function instance(): self {
			return new self();
		}
		public function insert_many( array $rows ): void {
			self::$rows = array_merge( self::$rows, $rows );
		}
	}
}

namespace {
	use Famma\Core\Visitor_Collector;
	use Famma\Core\Visitor_Store;

	define( 'ABSPATH', __DIR__ . '/' );

	class WP_REST_Request {
		public function __construct( private string $body ) {}
		public function get_body(): string {
			return $this->body;
		}
	}

	class WP_REST_Response {
		public function __construct( public $data = null, public int $status = 200 ) {}
	}

	function absint( $value ): int {
		return abs( (int) $value );
	}

	require dirname( __DIR__ ) . '/wp-content/plugins/famma-core/includes/visitor-collector.php';

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

	/**
	 * Envoie un lot d'événements à la vraie méthode `collect()`.
	 *
	 * @param array<int, array<string, mixed>> $events Événements du lot.
	 * @return array<int, array<string, mixed>> Lignes écrites.
	 */
	function collect( array $events ): array {
		Visitor_Store::$rows = array();
		$body                = json_encode(
			array(
				'v'   => '00000000-0000-4000-8000-000000000001',
				's'   => '00000000-0000-4000-8000-000000000002',
				'src' => array( 'source' => 'facebook' ),
				'e'   => $events,
			)
		);
		$reply               = ( new Visitor_Collector() )->collect( new WP_REST_Request( (string) $body ) );
		check( 204 === $reply->status, 'la route répond toujours 204' );
		return Visitor_Store::$rows;
	}

	// Filtre seul.
	$cases = array(
		array( 'state', 'state', 'un code connu est gardé' ),
		array( 'state,phone', 'phone,state', 'ordre de la liste blanche, quel que soit l’ordre reçu' ),
		array( 'state,state', 'state', 'sans doublon' ),
		array( ' STATE , Phone ', 'phone,state', 'espaces et majuscules tolérés' ),
		array( '20123456', '', 'un numéro de téléphone est rejeté' ),
		array( 'Sousse,state', 'state', 'un nom de ville tapé est retiré' ),
		array( 'storage,network,server,changed,form,size,name', 'name,size,form,storage,network,server,changed', 'les neuf codes de la liste sont acceptés' ),
		array( 'state,' . str_repeat( 'x', 200 ) . ',phone', 'state', 'libellé coupé à 100 caractères' ),
		array( '', '', 'libellé vide' ),
		array( array( 'state' ), '', 'un tableau est rejeté' ),
		array( null, '', 'null est rejeté' ),
		array( 42, '', 'un nombre est rejeté' ),
	);
	foreach ( $cases as $case ) {
		check( Visitor_Collector::form_error_codes( $case[0] ) === $case[1], 'form_error_codes : ' . $case[2] );
	}

	// Lot complet, par la vraie méthode collect().
	$base = array(
		'pt'  => 'product',
		'p'   => '/produit/fixture/',
		'pid' => 123,
		'a'   => 0,
	);
	$rows = collect(
		array(
			$base + array(
				'n' => 'form_error',
				'l' => 'state,Sousse',
			),
		)
	);
	check( 1 === count( $rows ) && 'form_error' === $rows[0]['event'] && 'state' === $rows[0]['label'], 'collect : une ligne form_error, libellé « state » seul' );

	$rows = collect(
		array(
			$base + array(
				'n' => 'form_error',
				'l' => '20 123 456',
			),
		)
	);
	check( array() === $rows, 'collect : aucun code valable → aucune ligne' );

	$rows = collect(
		array(
			$base + array(
				'n' => 'form_submit',
				'x' => 12,
			),
			$base + array(
				'n' => 'form_error',
				'l' => 'phone',
			),
			$base + array(
				'n' => 'form_bogus',
				'l' => 'phone',
			),
		)
	);
	check( array( 'form_submit', 'form_error' ) === array_column( $rows, 'event' ), 'collect : form_error à côté des autres événements, nom inconnu ignoré' );

	$rows = collect(
		array(
			$base + array(
				'n' => 'form_field',
				'l' => 'billing_state',
			),
		)
	);
	check( 1 === count( $rows ) && 'billing_state' === $rows[0]['label'], 'collect : les autres libellés sont inchangés' );

	echo 'RESULT ' . $passed . '/' . ( $passed + $failed ) . ' contrôles du collecteur réussis' . ( $failed ? ', ' . $failed . ' ÉCHEC(S)' : '' ) . '.' . PHP_EOL;
	exit( $failed ? 1 : 0 );
}
