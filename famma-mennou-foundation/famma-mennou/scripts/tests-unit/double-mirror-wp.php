<?php
/**
 * Lot 59 — WordPress doubles, fixtures and helpers shared by the server-duplicate
 * suites (test-capi-mirror.php, test-capi-mirror-queue.php). Required after
 * bootstrap-browser.php and double-mirror-wpdb.php.
 *
 * @package Famma_Test_Scenarios
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Generic.Classes.DuplicateClassName -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- Command-line test: superglobals are fixtures.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Fixtures of the standalone runner; WordPress is never loaded.

use Famma\Core\Capi_Mirror_Health;
use Famma\Core\Rate_Counter;

function get_post_status( $id ) {
	return $GLOBALS['wp_test_post_status'][ (int) $id ] ?? 'publish';
}
function rest_url( string $path = '' ): string {
	return 'https://qa.invalid/wp-json/' . $path;
}
function wp_validate_auth_cookie( $cookie = '', $scheme = '' ) {
	return $GLOBALS['wp_test_auth_user'] ?? false;
}
function user_can( $user, string $cap ): bool {
	return ! empty( $GLOBALS['wp_test_user_caps'][ $cap ] );
}
function wp_next_scheduled( string $hook, array $args = array() ) {
	return $GLOBALS['wp_test_cron'][ $hook ] ?? false;
}
function wp_schedule_event( int $time, string $recurrence, string $hook ): bool {
	$GLOBALS['wp_test_cron'][ $hook ] = array( $time, $recurrence );
	return true;
}
function wp_clear_scheduled_hook( string $hook ): int {
	unset( $GLOBALS['wp_test_cron'][ $hook ] );
	return 1;
}
class WP_REST_Request {
	public function __construct( private string $body ) {}
	public function get_body(): string {
		return $this->body;
	}
}
class WP_REST_Response {
	public function __construct( public $data = null, public int $status = 200 ) {}
}

$vid      = '3f2b8c1e-9d4a-4c7b-8e2f-1a6d5c4b3e21';
$simple   = new WC_Product( 501, '', 'Masque V-Line', 'masque', 22.0 );
$variable = new WC_Product_Variable( 600, '', 'Ceinture', 'ceinture', 39.0 );
$size     = new WC_Product_Variation( 601, $variable, '', 'Ceinture L', 39.0 );
$hidden   = new WC_Product( 502, '', 'Brouillon', 'brouillon', 10.0 );

/**
 * Fresh request: mobile browser on a page of the site, cookies, production, switch on.
 *
 * @param array $options Options to start from.
 */
function mirror_reset( array $options = array() ): void {
	lb_reset( $options );
	$GLOBALS['wp_test_post_status'] = array( 502 => 'draft' );
	$GLOBALS['wp_test_auth_user']   = false;
	$GLOBALS['wp_test_user_caps']   = array();
	$GLOBALS['wp_test_cron']        = array();
	$GLOBALS['mirror_race']         = null;
	$GLOBALS['mirror_race_on']      = '';
	$GLOBALS['wpdb']                = new Mirror_Test_Wpdb();
	$_SERVER['REMOTE_ADDR']         = '197.0.0.10';
	$_SERVER['HTTP_USER_AGENT']     = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148';
	mirror_headers( 'https://qa.invalid', 'https://qa.invalid/produit/masque/' );
	$_COOKIE = array(
		'_fbp'      => 'fb.1.1795000000000.1234567890',
		'_fbc'      => 'fb.1.1795000000000.IwAR0abc',
		'famma_vid' => $GLOBALS['vid'],
	);
}

/**
 * Origin and Referer request headers ('' = absent).
 *
 * @param string $origin  Origin.
 * @param string $referer Referer.
 */
function mirror_headers( string $origin, string $referer ): void {
	unset( $_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER'] );
	if ( '' !== $origin ) {
		$_SERVER['HTTP_ORIGIN'] = $origin;
	}
	if ( '' !== $referer ) {
		$_SERVER['HTTP_REFERER'] = $referer;
	}
}

/**
 * One relay entry.
 *
 * @param string $name  Event name.
 * @param string $id    Event ID.
 * @param array  $items Items [ID, quantity].
 * @param int    $age   Age in milliseconds.
 * @param string $url   Page URL.
 * @param mixed  $value Value sent to the Pixel (null: none).
 * @return array
 */
function relay( string $name, string $id, array $items = array(), int $age = 1500, string $url = 'https://qa.invalid/produit/masque/?fbclid=x#avis', $value = null ): array {
	$entry = array(
		'n'  => $name,
		'id' => $id,
		'u'  => $url,
		'a'  => $age,
		'i'  => $items,
	);
	if ( null !== $value ) {
		$entry['v'] = $value;
	}
	return $entry;
}

/**
 * Body of one relay call.
 *
 * @param array  $events Relay entries.
 * @param string $ph     Phone hash, or empty.
 * @return string
 */
function body( array $events, string $ph = '' ): string {
	$data = array( 'e' => $events );
	if ( '' !== $ph ) {
		$data['ph'] = $ph;
	}
	return (string) json_encode( $data );
}

/** Waiting events, by event name. */
function mirror_by_name(): array {
	return array_column( array_column( mirror_queued(), 'e' ), null, 'event_name' );
}

/** Health counters by reason, zero when absent. */
function mirror_health(): array {
	$state = get_option( Capi_Mirror_Health::OPTION, array() );
	return (array) ( $state['reasons'] ?? array() ) + array_fill_keys( array( 'origin', 'site', 'rate', 'content', 'replay', 'adjusted', 'expired', 'contract', 'transient' ), 0 );
}

/**
 * Raw value of one rate counter row, or null.
 *
 * @param string $name Counter name without prefix.
 * @return string|null
 */
function mirror_rate( string $name ): ?string {
	$value = $GLOBALS['wp_test_options'][ Rate_Counter::PREFIX . $name ] ?? null;
	return null === $value ? null : (string) $value;
}

/** Names of every rate counter row. */
function mirror_rate_rows(): array {
	return array_values( array_filter( array_keys( $GLOBALS['wp_test_options'] ), static fn( $n ): bool => str_starts_with( (string) $n, Rate_Counter::PREFIX ) ) );
}
