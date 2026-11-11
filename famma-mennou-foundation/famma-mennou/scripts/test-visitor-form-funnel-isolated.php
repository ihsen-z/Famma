<?php
/**
 * Entonnoir du formulaire express (Visitor_Form_Funnel) : tests isolés, sans WordPress ni base.
 * Lancer : php scripts/test-visitor-form-funnel-isolated.php
 *
 * Jeu d'événements fictifs (marqué « test », sessions test-s1…), compté à la
 * main dans les commentaires ; les vraies classes calculent et affichent.
 * Couvre aussi le cache (une seule lecture de la table) et l'échappement.
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded. The doubles below keep the names,
// namespaces and signatures of the FAMMA and WordPress code they replace.
// phpcs:disable Universal.Namespaces -- The FAMMA doubles and the global WordPress doubles must share this single runner.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Generic.Classes.DuplicateClassName -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.I18n -- CLI runner and translation doubles, never served.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- The $wpdb double replaces the database for this runner only.

namespace Famma\Core {
	final class Visitor_Store {
		public static function instance(): self {
			return new self();
		}
		public function table(): string {
			return 'wp_famma_visitor_events';
		}
	}
}

namespace {
	use Famma\Core\Visitor_Form_Funnel;
	use Famma\Core\Visitor_Form_Funnel_Views;

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'ARRAY_A', 'ARRAY_A' );

	$transients = array();
	$ttls       = array();
	$titles     = array(
		10 => 'Produit test <b>A</b>',
		20 => '',
	);

	function get_transient( string $key ) {
		global $transients;
		return $transients[ $key ] ?? false;
	}
	function set_transient( string $key, $value, int $ttl ): bool {
		global $transients, $ttls;
		$transients[ $key ] = $value;
		$ttls[ $key ]       = $ttl;
		return true;
	}
	function __( string $text, string $domain ): string {
		return $text;
	}
	function esc_html__( string $text, string $domain ): string {
		return esc_html( $text );
	}
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
	function number_format_i18n( $number, int $decimals = 0 ): string {
		return number_format( (float) $number, $decimals, ',', ' ' );
	}
	function get_the_title( int $id ): string {
		global $titles;
		return $titles[ $id ] ?? '';
	}

	final class Fake_Wpdb {
		public int $reads  = 0;
		public array $args = array();
		public array $rows = array();
		public string $sql = '';
		public function prepare( string $sql, ...$args ): string {
			$this->sql  = $sql;
			$this->args = $args;
			return $sql;
		}
		public function get_results( string $sql, string $output ): array {
			++$this->reads;
			return $this->rows;
		}
	}

	require dirname( __DIR__ ) . '/wp-content/plugins/famma-core/includes/visitor-collector.php';
	require dirname( __DIR__ ) . '/wp-content/plugins/famma-core/includes/visitor-insights-views.php';
	require dirname( __DIR__ ) . '/wp-content/plugins/famma-core/includes/visitor-admin-views.php';
	require dirname( __DIR__ ) . '/wp-content/plugins/famma-core/includes/visitor-form-funnel.php';
	require dirname( __DIR__ ) . '/wp-content/plugins/famma-core/includes/visitor-form-funnel-views.php';

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

	function event( string $session, int $product, string $event, string $label = '' ): array {
		return array(
			'session_id' => 'test-' . $session,
			'product_id' => (string) $product,
			'event'      => $event,
			'label'      => $label,
		);
	}

	/*
	 * Jeu de test :
	 * s1 : produit 10, commencé, 2 refus « state », envoyé, commande.
	 * s2 : produit 10, commencé, refus « name,state », envoyé, sans commande.
	 * s3 : produit 10, commencé seulement.
	 * s4 : produit 20, envoyé sans form_start (script chargé tard), commande.
	 * s5 : produit 20, refus « phone,bogus » (code inconnu ignoré), sans commande.
	 * s6 : commande au checkout classique, sans formulaire express : hors entonnoir.
	 * s7 : produits 10 et 20 commencés : une seule session au total.
	 * Plus une ligne sans produit et une ligne sans session : ignorées.
	 */
	$rows = array(
		event( 's1', 10, 'form_start' ),
		event( 's1', 10, 'form_submit' ),
		event( 's1', 10, 'form_error', 'state' ),
		event( 's1', 10, 'form_submit' ),
		event( 's1', 10, 'form_error', 'state' ),
		event( 's1', 0, 'order_placed', '1' ),
		event( 's2', 10, 'form_start' ),
		event( 's2', 10, 'form_submit' ),
		event( 's2', 10, 'form_error', 'name,state' ),
		event( 's3', 10, 'form_start' ),
		event( 's4', 20, 'form_submit' ),
		event( 's4', 0, 'order_placed', '2' ),
		event( 's5', 20, 'form_start' ),
		event( 's5', 20, 'form_error', 'phone,bogus' ),
		event( 's6', 0, 'order_placed', '1' ),
		event( 's7', 10, 'form_start' ),
		event( 's7', 20, 'form_start' ),
		event( 's8', 0, 'form_start' ),
		array(
			'session_id' => '',
			'product_id' => '10',
			'event'      => 'form_start',
			'label'      => '',
		),
	);

	$data = Visitor_Form_Funnel::aggregate( $rows );
	$line = static function ( int $started, int $submitted, int $ordered, int $blocked, int $refusals, int $recovered ): array {
		return compact( 'started', 'submitted', 'ordered', 'blocked', 'refusals', 'recovered' );
	};

	check( $line( 4, 2, 1, 2, 3, 1 ) === $data['products'][10], 'produit 10 : 4 commencés, 2 envoyés, 1 commande, 2 bloquées, 3 refus, 1 débloquée' );
	check( $line( 3, 1, 1, 1, 1, 0 ) === $data['products'][20], 'produit 20 : un envoi sans form_start compte comme commencé' );
	check( array( 10, 20 ) === array_keys( $data['products'] ), 'produits triés par formulaires commencés' );
	check( $line( 6, 3, 2, 3, 4, 1 ) === $data['total'], 'total : sessions distinctes (s7 une fois), commande hors formulaire exclue' );
	check(
		array(
			'state' => array(
				'refusals' => 3,
				'sessions' => 2,
				'lost'     => 1,
			),
			'name'  => array(
				'refusals' => 1,
				'sessions' => 1,
				'lost'     => 1,
			),
			'phone' => array(
				'refusals' => 1,
				'sessions' => 1,
				'lost'     => 1,
			),
		) === $data['causes'],
		'causes : « state » d’abord, code inconnu ignoré, sessions sans commande comptées'
	);
	check( false === strpos( (string) json_encode( $data ), 'test-s' ), 'aucun identifiant de session dans le résultat mis en cache' );

	// Lecture de la table et cache.
	$wpdb       = new Fake_Wpdb();
	$wpdb->rows = $rows;
	$first      = ( new Visitor_Form_Funnel() )->express( 7 );
	$second     = ( new Visitor_Form_Funnel() )->express( 7 );
	check( 1 === $wpdb->reads, 'une seule lecture de la table : le second affichage vient du transient' );
	check( $first === $second && $first['total'] === $data['total'] && false === $first['truncated'], 'le cache rend les mêmes nombres' );
	check( 'wp_famma_visitor_events' === $wpdb->args[0] && Visitor_Form_Funnel::MAX_ROWS === $wpdb->args[2], 'requête préparée : table, date de début, plafond de lignes' );
	check( abs( strtotime( $wpdb->args[1] . ' UTC' ) - ( time() - 7 * DAY_IN_SECONDS ) ) < 5, 'début de période : il y a 7 jours, en UTC' );
	check( isset( $transients['famma_vj_express_7'] ) && ! isset( $transients['famma_vj_express_30'] ), 'une clé de cache par période' );
	check( 300 === $ttls['famma_vj_express_7'], 'cache de cinq minutes, comme les autres agrégats' );

	// Affichage : échappement et nombres.
	ob_start();
	Visitor_Form_Funnel_Views::render( $data );
	$html = (string) ob_get_clean();
	check( false === strpos( $html, '<b>A</b>' ) && false !== strpos( $html, 'Produit test &lt;b&gt;A&lt;/b&gt;' ), 'titre du produit échappé' );
	check( false !== strpos( $html, 'Produit n° 20' ), 'produit sans titre : numéro affiché' );
	check( false !== strpos( $html, '<td class="num">25,0 %</td>' ), 'produit 10 : commandes / commencés = 25,0 %' );
	check( false !== strpos( $html, 'Gouvernorat' ) && false === strpos( $html, 'bogus' ), 'causes en clair, code inconnu absent' );
	check( substr_count( $html, '<table' ) === substr_count( $html, '</table>' ), 'tableaux bien fermés' );

	ob_start();
	Visitor_Form_Funnel_Views::render( Visitor_Form_Funnel::aggregate( array() ) );
	$empty = (string) ob_get_clean();
	check( false !== strpos( $empty, 'Aucun formulaire express commencé' ) && false === strpos( $empty, '<table' ), 'période vide : message, aucun tableau' );

	echo 'RESULT ' . $passed . '/' . ( $passed + $failed ) . ' contrôles de l’entonnoir réussis' . ( $failed ? ', ' . $failed . ' ÉCHEC(S)' : '' ) . '.' . PHP_EOL;
	exit( $failed ? 1 : 0 );
}
