<?php
/**
 * Relecture du lot 5, SEC-1 / R-CACHE-1 — retour arrière 0.3.2 → 0.3.1 sans
 * jeton CAPI faux : la 0.3.1 lit l'option `famma_meta_access_token` telle
 * quelle (git show 3fa2bce, Config::setting()), donc le jeton doit y être en
 * clair avant la remise de ses fichiers, et y rester pendant l'échange.
 * Run with: php scripts/tests-unit/test-capi-token-rollback.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php and bootstrap-capi.php.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.WrongStyle -- Doubles are documented by the API they replace.
require __DIR__ . '/bootstrap-capi.php';
Famma_Unit_Harness::load_include( 'measurement-settings.php' );
Famma_Unit_Harness::load_include( 'tracking-settings.php' );
Famma_Unit_Harness::load_include( 'capi-token-rollback.php' );

use Famma\Core\Capi_Health;
use Famma\Core\Capi_Token_Rollback;
use Famma\Core\Capi_Token_Vault;
use Famma\Core\Config;

// Screen doubles.
function esc_url( $url ): string {
	return (string) $url;
}
function wp_nonce_field( string $action ): void {
	echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '" />';
}
function submit_button( string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true ): void {
	echo '<button>' . esc_html( $text ) . '</button>';
}
function wp_date( string $format, int $timestamp ): string {
	return gmdate( 'd/m/Y H:i', $timestamp );
}

$token  = 'QA-L5-FAKE-TOKEN-NOT-REAL';
$option = static fn(): string => (string) ( $GLOBALS['wp_test_options'][ Capi_Token_Vault::OPTION ] ?? '' );
// What famma-core 0.3.1 sends as access_token (3fa2bce, famma-core.php L188-196): the raw option.
$sent_by_031 = static fn(): string => trim( (string) get_option( 'famma_meta_access_token', '' ) );
$render      = static function (): string {
	ob_start();
	Capi_Token_Rollback::render();
	return (string) ob_get_clean();
};
$handle      = static function ( string $handler ): string {
	try {
		Capi_Token_Rollback::instance()->$handler();
	} catch ( Famma_Test_Redirect $redirect ) {
		return $redirect->getMessage();
	} catch ( RuntimeException $denied ) {
		return $denied->getMessage();
	}
	return '';
};

// 1. Before any preparation: 0.3.2 seals the token on first read, which a restored 0.3.1 would send.
l5_reset( array( 'famma_meta_access_token' => $token ) );
l5_check( Config::instance()->meta_access_token() === $token, '0.3.2 still sends the clear token stored before it.' );
l5_check( Capi_Token_Vault::is_sealed( $sent_by_031() ), 'SEC-1 (reproduction): after the first read, 0.3.1 would send the encrypted value as its token.' );
$html = $render();
l5_check( str_contains( $html, 'Remettre le jeton en clair' ) && str_contains( $html, 'value="' . Capi_Token_Rollback::UNSEAL . '"' ) && str_contains( $html, 'nonce-' . Capi_Token_Rollback::UNSEAL ) && ! str_contains( $html, $token ) && ! str_contains( $html, $option() ), '« Mesure » offers « Remettre le jeton en clair » (nonce), never showing the token nor its encrypted form.' );

// 2. Preparation: nonce and capabilities first.
$GLOBALS['wp_test_caps'] = array( 'manage_woocommerce' => false );
l5_check( str_starts_with( $handle( 'handle_unseal' ), 'wp_die' ) && Capi_Token_Vault::is_sealed( $option() ), 'Without manage_woocommerce the action is refused and the token stays encrypted.' );
l5_check( '' === $render(), 'Without the capabilities, the block is not shown.' );
$GLOBALS['wp_test_caps'] = array();
$source                  = (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/capi-token-rollback.php' );
l5_check( 2 === substr_count( $source, 'self::authorize( self::' ) && str_contains( $source, "check_admin_referer( \$action );\n\t\tif ( ! self::allowed() )" ), 'Both actions check their nonce, then manage_options and manage_woocommerce.' );
$location = $handle( 'handle_unseal' );
l5_check( str_contains( $location, 'famma_token_rollback=unsealed' ) && ! str_contains( $location, $token ), 'The action redirects to « Mesure » with a fixed result, never the token.' );
l5_check( $sent_by_031() === $token, 'SEC-1: after « Remettre le jeton en clair », 0.3.1 reads the real token.' );

// 3. While the files are being swapped, 0.3.2 keeps reading the token without sealing it again.
Capi_Health::evaluate();
l5_check( Config::instance()->meta_access_token() === $token && $sent_by_031() === $token && 'plain' === Capi_Token_Vault::state(), 'During the suspension, sending, health and « Mesure » never seal the token again.' );
$settings = Famma_Unit_Harness::bare( '\Famma\Core\Tracking_Settings' );
l5_check( 'QA-L5-OTHER-FAKE-TOKEN' === $settings->sanitize_token( 'QA-L5-OTHER-FAKE-TOKEN' ) && $token === $settings->sanitize_token( '' ), 'During the suspension, a token typed in « Mesure » is kept in clear too; an empty field keeps it.' );
ob_start();
$settings->render_field(
	array(
		'option'   => 'famma_meta_access_token',
		'constant' => 'FAMMA_META_ACCESS_TOKEN',
		'secret'   => true,
	)
);
$field = (string) ob_get_clean();
l5_check( str_contains( $field, 'en clair' ) && str_contains( $field, '<code>REAL</code>' ) && ! str_contains( $field, $token ), 'The token field says « en clair » and still shows only the last 4 characters.' );
$html = $render();
l5_check( str_contains( $html, 'Rechiffrer maintenant' ) && str_contains( $html, 'nonce-' . Capi_Token_Rollback::RESEAL ) && ! str_contains( $html, $token ), 'While suspended, the block offers « Rechiffrer maintenant ».' );
l5_check( Capi_Token_Vault::held( time() + Capi_Token_Vault::HOLD_TTL - 60 ) && ! Capi_Token_Vault::held( time() + Capi_Token_Vault::HOLD_TTL + 1 ), 'The suspension lasts 7 days.' );

// 4. « Rechiffrer maintenant »: sealed again at once, suspension lifted.
$location = $handle( 'handle_reseal' );
l5_check( str_contains( $location, 'famma_token_rollback=resealed' ) && Capi_Token_Vault::is_sealed( $option() ) && Capi_Token_Vault::open( $option() ) === $token && ! array_key_exists( Capi_Token_Vault::HOLD, $GLOBALS['wp_test_options'] ), '« Rechiffrer maintenant » seals the token again and lifts the suspension.' );

// 5. Suspension expired (0.3.2 back without « Rechiffrer »): the next read seals the token.
Capi_Token_Vault::unseal();
$GLOBALS['wp_test_options'][ Capi_Token_Vault::HOLD ] = time() - Capi_Token_Vault::HOLD_TTL - 1;
l5_check( Config::instance()->meta_access_token() === $token && Capi_Token_Vault::is_sealed( $option() ) && 'sealed' === Capi_Token_Vault::state(), 'After 7 days, the next read seals the token again (T2.12 kept).' );

// 6. Nothing readable to unseal: refused, nothing written.
l5_reset( array( 'famma_meta_access_token' => '' ) );
Config::instance()->meta_access_token(); // A new request reads the option afresh.
l5_check( ! Capi_Token_Vault::unseal() && str_contains( $handle( 'handle_unseal' ), 'famma_token_rollback=failed' ) && ! isset( $GLOBALS['wp_test_options'][ Capi_Token_Vault::HOLD ] ) && '' === $render(), 'No token: nothing to unseal, no suspension, no block.' );
l5_reset( array( 'famma_meta_access_token' => Capi_Token_Vault::seal( $token ) ) );
$GLOBALS['wp_test_salt'] = 'rotated-salt';
$before                  = $option();
l5_check( ! Capi_Token_Vault::unseal() && $before === $option() && ! isset( $GLOBALS['wp_test_options'][ Capi_Token_Vault::HOLD ] ), 'Unreadable token (site keys changed): nothing is written.' );
unset( $GLOBALS['wp_test_salt'] );
add_filter( 'pre_option_famma_meta_access_token', static fn(): string => 'QA-L5-FILTERED-FAKE-TOKEN' );
$before = $GLOBALS['wp_test_options'];
l5_check( ! Capi_Token_Vault::unseal() && $before === $GLOBALS['wp_test_options'], 'A token supplied by a filter is never written in clear.' );
unset( $GLOBALS['wp_test_hooks']['pre_option_famma_meta_access_token'] );

// 7. Hooks: admin-post actions of logged-in users only.
l5_check( isset( $GLOBALS['wp_test_hooks'][ 'admin_post_' . Capi_Token_Rollback::UNSEAL ], $GLOBALS['wp_test_hooks'][ 'admin_post_' . Capi_Token_Rollback::RESEAL ] ) && ! isset( $GLOBALS['wp_test_hooks'][ 'admin_post_nopriv_' . Capi_Token_Rollback::UNSEAL ] ), 'Both actions are admin-post actions, never nopriv.' );

// 8. Token from the server constant (defined last: it cannot be undefined): nothing to prepare.
define( 'FAMMA_META_ACCESS_TOKEN', 'QA-L5-CONSTANT-FAKE' );
$html = $render();
l5_check( str_contains( $html, 'constante du serveur' ) && ! str_contains( $html, '<form' ) && ! str_contains( $html, 'QA-L5-CONSTANT-FAKE' ), 'Token from the constant: no button, no value shown.' );

echo 'CHECKS=' . $GLOBALS['l5_checks'] . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
