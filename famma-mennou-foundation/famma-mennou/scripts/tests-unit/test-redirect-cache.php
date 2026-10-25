<?php
/**
 * Lot 5, D2 — Redirections: the visitor's query string survives the old-slug
 * 301 and the legacy-URL 301, and no front redirect is ever cached
 * (LiteSpeed "no-cache" action plus standard no-cache headers).
 * Run with: php scripts/tests-unit/test-redirect-cache.php [--json]
 * Real Redirect_Cache and real mu-plugin famma-legacy-urls.php; WordPress,
 * WooCommerce and LiteSpeed Cache are doubles. Exit code 1 on any failure.
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php for the doubles.
require __DIR__ . '/bootstrap.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The test sets the request superglobals itself.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.

// Doubles not provided by bootstrap.php (guarded, in case it gains them later).
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return ! empty( $GLOBALS['wp_test_is_admin'] );
	}
}
if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers(): void {
		$GLOBALS['wp_test_nocache'] = ( $GLOBALS['wp_test_nocache'] ?? 0 ) + 1;
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- This is the double of wp_parse_url() itself.
		return parse_url( $url, $component );
	}
}
if ( ! function_exists( 'wp_parse_str' ) ) {
	function wp_parse_str( string $input_string, &$result ): void {
		parse_str( $input_string, $result );
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/\\' );
	}
}
if ( ! function_exists( 'is_404' ) ) {
	function is_404(): bool {
		return ! empty( $GLOBALS['wp_test_is_404'] );
	}
}
if ( ! function_exists( 'wc_get_page_id' ) ) {
	function wc_get_page_id( string $page ): int {
		return 7;
	}
}
if ( ! function_exists( 'wc_get_page_permalink' ) ) {
	function wc_get_page_permalink( string $page ): string {
		$pages = array(
			'shop'      => 'https://qa.invalid/boutique/',
			'cart'      => 'https://qa.invalid/panier/',
			'checkout'  => 'https://qa.invalid/commande/',
			'myaccount' => 'https://qa.invalid/mon-compte/',
		);
		return $pages[ $page ] ?? '';
	}
}

// LiteSpeed Cache double: records each "no-cache" call and its reason.
add_action(
	'litespeed_control_set_nocache',
	static function ( string $reason ): void {
		$GLOBALS['lscache_nocache'][] = $reason;
	}
);

$reset = static function ( array $query = array() ): void {
	$_GET                        = $query;
	$GLOBALS['wp_test_nocache']  = 0;
	$GLOBALS['lscache_nocache']  = array();
	$GLOBALS['wp_test_is_admin'] = false;
};

// WordPress public query vars, as WP::parse_request() leaves them (WooCommerce adds "product").
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test double of the WP object.
$GLOBALS['wp'] = (object) array( 'public_query_vars' => array( 'name', 'p', 'page_id', 'pagename', 'post_type', 'product', 'paged', 'embed' ) );

$rows   = array();
$checks = static function ( string $label, bool $ok, string $detail ) use ( &$rows ): void {
	$rows[] = array(
		'cas'     => $label,
		'verdict' => $ok ? 'OK' : 'ECHEC',
		'detail'  => $detail,
	);
};

// Runs the real mu-plugin on a 404 for $uri and returns the redirect target ('' if none).
$legacy_redirect = static function ( string $uri ): string {
	$GLOBALS['wp_test_is_404'] = true;
	$_SERVER['REQUEST_URI']    = $uri;
	try {
		famma_legacy_urls_redirect();
	} catch ( Famma_Test_Redirect $redirect ) {
		return $redirect->getMessage();
	}
	return '';
};
$legacy_reason   = 'famma: redirection d une URL heritee jamais en cache';
$legacy_query    = array(
	'fbclid'       => 'QAL5Legacy',
	'utm_campaign' => 'café',
);
$legacy_uri      = '/shop/?fbclid=QAL5Legacy&utm_campaign=caf%C3%A9';

// 0. Mu-plugin alone (famma-core not loaded): 0.1.0 fallback, raw query kept
// (the CODE-19 %XX truncation remains in this case only), never cached.
require ABSPATH . 'wp-content/mu-plugins/famma-legacy-urls.php';
$reset( $legacy_query );
$location = $legacy_redirect( $legacy_uri );
$checks(
	'mu-plugin seul (sans famma-core) : repli 0.1.0, requête gardée, jamais en cache',
	! class_exists( '\Famma\Core\Redirect_Cache', false )
		&& 'https://qa.invalid/boutique/?fbclid=QAL5Legacy&utm_campaign=caf' === $location
		&& array( $legacy_reason ) === $GLOBALS['lscache_nocache'] && 1 === $GLOBALS['wp_test_nocache'],
	$location . ' ; litespeed=' . implode( ' | ', $GLOBALS['lscache_nocache'] ) . ' ; nocache_headers=' . $GLOBALS['wp_test_nocache']
);

$reset();
Famma_Unit_Harness::load_include( 'redirect-cache.php' );
$redirects = \Famma\Core\Redirect_Cache::instance();

$new_link = 'https://qa.invalid/produit/qa-l5-nouveau-slug/';

// 1. Hooks are registered on the front.
$checks(
	'hooks front : old_slug_redirect_url et wp_redirect_status',
	! empty( $GLOBALS['wp_test_hooks']['old_slug_redirect_url'] ) && ! empty( $GLOBALS['wp_test_hooks']['wp_redirect_status'] ),
	implode( ', ', array_keys( $GLOBALS['wp_test_hooks'] ?? array() ) )
);

// 2. Old slug: fbclid and UTM kept, UTF-8 value re-encoded, query vars of the old post dropped.
$reset(
	array(
		'fbclid'       => 'IwAR0QA-L5_FbClid',
		'utm_source'   => 'facebook',
		'utm_campaign' => 'café été',
		'product'      => 'qa-l5-ancien-slug',
		'name'         => 'qa-l5-ancien-slug',
	)
);
$location = (string) apply_filters( 'old_slug_redirect_url', $new_link );
$checks(
	'ancien slug : fbclid et utm_* recopiés, variables de l’ancien article écartées',
	$new_link . '?fbclid=IwAR0QA-L5_FbClid&utm_source=facebook&utm_campaign=caf%C3%A9%20%C3%A9t%C3%A9' === $location,
	$location
);

// 3. No duplicate: a key already in the target keeps the target's value.
$reset(
	array(
		'utm_source' => 'visiteur',
		'fbclid'     => 'QAL5DUP',
	)
);
$location = (string) apply_filters( 'old_slug_redirect_url', $new_link . '?utm_source=cible' );
$checks(
	'sans doublon : la clé déjà présente dans la cible garde sa valeur',
	$new_link . '?utm_source=cible&fbclid=QAL5DUP' === $location && 1 === substr_count( $location, 'utm_source=' ),
	$location
);

// 4. Fragment kept after the query string.
$reset( array( 'fbclid' => 'QAL5FRAG' ) );
$location = (string) apply_filters( 'old_slug_redirect_url', $new_link . '#avis' );
$checks( 'fragment conservé après la requête', $new_link . '?fbclid=QAL5FRAG#avis' === $location, $location );

// 5. No visitor query: the link is unchanged.
$reset();
$location = (string) apply_filters( 'old_slug_redirect_url', $new_link );
$checks( 'sans paramètre : adresse inchangée', $new_link === $location, $location );

// 6. Hostile input: invalid keys, arrays, tags, line breaks, oversized values.
$reset(
	array(
		'<script>'   => 'x',
		'utm medium' => 'espace',
		'tableau'    => array( 'a', 'b' ),
		'utm_term'   => '<b>gras</b>',
		'utm_id'     => "a\r\nSet-Cookie: x=1",
		'gclid'      => str_repeat( 'g', 2000 ),
		'utm_source' => 'O\\\'Reilly',
	)
);
$location = (string) apply_filters( 'old_slug_redirect_url', $new_link );
$checks(
	'entrées hostiles : clés invalides, tableaux, balises, sauts de ligne et valeur géante écartés ou assainis',
	$new_link . '?utm_term=gras&utm_id=a%20Set-Cookie%3A%20x%3D1&utm_source=O%27Reilly' === $location && ! preg_match( '/[\r\n<>]/', $location ),
	$location
);

// 7. Non-string link handed by another filter: returned untouched.
$reset( array( 'fbclid' => 'QAL5' ) );
$checks( 'lien vide ou false : rendu tel quel', false === $redirects->keep_query_string( false ) && '' === $redirects->keep_query_string( '' ), '' );

// 8. Every 3xx of the front is marked "no-cache" for LiteSpeed and gets the standard headers.
foreach ( array( 301, 302, 307, 308 ) as $code ) {
	$reset();
	$returned = apply_filters( 'wp_redirect_status', $code, $new_link . '?fbclid=QAL5' );
	$checks(
		'redirection ' . $code . ' : litespeed_control_set_nocache + nocache_headers, statut inchangé',
		$code === $returned && 1 === count( $GLOBALS['lscache_nocache'] ) && 1 === $GLOBALS['wp_test_nocache'],
		implode( ' | ', $GLOBALS['lscache_nocache'] ) . ' ; nocache_headers=' . $GLOBALS['wp_test_nocache']
	);
}

// 9. Not a redirect, or redirect cancelled (empty location): nothing marked.
$reset();
$redirects->forbid_cache( 200, $new_link );
$redirects->forbid_cache( 301, '' );
$checks( 'statut 200 ou adresse vide : rien n’est marqué', array() === $GLOBALS['lscache_nocache'] && 0 === $GLOBALS['wp_test_nocache'], '' );

// 10. Explicit exception through famma_redirect_nocache.
$reset();
$allow_cache = static function (): bool {
	return false;
};
add_filter( 'famma_redirect_nocache', $allow_cache );
$redirects->forbid_cache( 301, $new_link );
unset( $GLOBALS['wp_test_hooks']['famma_redirect_nocache'] );
$checks( 'filtre famma_redirect_nocache à false : redirection laissée au cache', array() === $GLOBALS['lscache_nocache'], '' );

// 11. Admin: no hook registered.
$reset();
$GLOBALS['wp_test_is_admin'] = true;
$hooks_before                = $GLOBALS['wp_test_hooks'];
unset( $GLOBALS['wp_test_hooks']['old_slug_redirect_url'], $GLOBALS['wp_test_hooks']['wp_redirect_status'] );
Famma_Unit_Harness::call( Famma_Unit_Harness::bare( '\Famma\Core\Redirect_Cache' ), 'hooks' );
$checks( 'administration : aucun hook posé', empty( $GLOBALS['wp_test_hooks']['old_slug_redirect_url'] ) && empty( $GLOBALS['wp_test_hooks']['wp_redirect_status'] ), '' );
$GLOBALS['wp_test_hooks'] = $hooks_before;

// 12. Mu-plugin with famma-core: /shop/ → /boutique/, query kept (UTF-8 intact), and the
// mu-plugin itself marks the response "no-cache" (the wp_safe_redirect() double never runs
// wp_redirect_status, so the reason can only come from the mu-plugin).
$reset( $legacy_query );
$location = $legacy_redirect( $legacy_uri );
$checks(
	'mu-plugin /shop/ avec famma-core : requête conservée (é intact), no-cache posé par le mu-plugin',
	'https://qa.invalid/boutique/?fbclid=QAL5Legacy&utm_campaign=caf%C3%A9' === $location
		&& in_array( $legacy_reason, $GLOBALS['lscache_nocache'], true ) && $GLOBALS['wp_test_nocache'] >= 1,
	$location . ' ; litespeed=' . implode( ' | ', $GLOBALS['lscache_nocache'] )
);

// 13. Documented limit of clean_args(): sanitize_text_field() still strips a literal %XX of
// the decoded value and collapses spaces (same as WordPress core, mirrored by bootstrap.php).
$limit = \Famma\Core\Redirect_Cache::clean_args(
	array(
		'utm_content' => 'promo%50ab',
		'utm_term'    => '  a  b ',
	)
);
$checks(
	'limite documentée : %XX littéral retiré et espaces réduits par sanitize_text_field',
	array(
		'utm_content' => 'promoab',
		'utm_term'    => 'a b',
	) === $limit,
	(string) wp_json_encode( $limit )
);

$failures = count( array_filter( $rows, static fn( array $row ): bool => 'ECHEC' === $row['verdict'] ) );
if ( in_array( '--json', $argv, true ) ) {
	echo json_encode(
		array(
			'test'   => 'D2',
			'echecs' => $failures,
			'rows'   => $rows,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	) . PHP_EOL;
	exit( $failures > 0 ? 1 : 0 );
}
foreach ( $rows as $row ) {
	echo $row['verdict'] . ': ' . $row['cas'] . ( '' !== $row['detail'] ? PHP_EOL . '    ' . $row['detail'] : '' ) . PHP_EOL;
}
echo 'CHECKS=' . count( $rows ) . ' ECHECS=' . $failures . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
exit( $failures > 0 ? 1 : 0 );
