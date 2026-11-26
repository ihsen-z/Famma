<?php
/**
 * Lot 59 (Q21, Q22) — doublon serveur (CAPI) des événements navigateur.
 *
 * - route du relais : chaque rejet (corps, JSON, nom, event_id, URL, âge,
 *   articles, quantité, produit non publié, robot, limite par IP, visiteur
 *   interne, interrupteur coupé, environnement non production) ;
 * - valeur, devise et identifiants recalculés depuis WooCommerce, profil de
 *   données appliqué ; même event_id que le Pixel ;
 * - file : dédoublonnée, N événements → UNE requête à Meta, file purgée,
 *   reprise bornée d'un refus temporaire, attente d'une heure au plus ;
 * - santé : un événement perdu = raison orange, jamais rouge, aucun e-mail ;
 * - téléphone : seule une empreinte SHA-256 est acceptée ; c'est la même que
 *   celle de la CAPI (`Capi_User_Data`) ; jamais de numéro en clair stocké ou
 *   journalisé ; l'URL graph.facebook.com n'est que dans capi-transport.php (V3).
 * Le JavaScript (relais, eventID partagé, `ph`) est testé par test-capi-mirror.js.
 * Run with: php scripts/tests-unit/test-capi-mirror.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap-browser.php.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Generic.Classes.DuplicateClassName -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.EscapeOutput.OutputNotEscaped -- Command-line test: superglobals are fixtures, output is a report.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- The $wpdb double replaces the database for the queue; WordPress is never loaded.
require __DIR__ . '/bootstrap-browser.php';

use Famma\Core\Capi_Health;
use Famma\Core\Capi_Health_Rules;
use Famma\Core\Capi_Mirror;
use Famma\Core\Capi_Mirror_Event;
use Famma\Core\Capi_Mirror_Health;
use Famma\Core\Capi_Mirror_Queue;
use Famma\Core\Capi_User_Data;
use Famma\Core\Tracking_Events;

const ARRAY_A = 'ARRAY_A';

function add_option( string $name, $value = '', $deprecated = '', $autoload = null ): bool {
	if ( array_key_exists( $name, $GLOBALS['wp_test_options'] ?? array() ) ) {
		return false;
	}
	$GLOBALS['wp_test_options'][ $name ] = $value;
	return true;
}
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
function wp_cache_delete( $key, string $group = '' ): bool {
	return true;
}
function wp_next_scheduled( string $hook ) {
	return $GLOBALS['wp_test_cron'][ $hook ] ?? false;
}
function wp_schedule_event( int $time, string $recurrence, string $hook ): bool {
	$GLOBALS['wp_test_cron'][ $hook ] = array( $time, $recurrence );
	return true;
}

/** `$wpdb` double: the queue lives in wp_test_options, in insertion order. */
final class Mirror_Test_Wpdb {
	public string $options = 'wp_options';
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}
	public function prepare( string $query, ...$args ): array {
		return array(
			'q' => $query,
			'a' => isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : $args,
		);
	}
	public function get_results( array $prepared, $output = null ): array {
		$prefix = stripslashes( rtrim( (string) $prepared['a'][0], '%' ) );
		$rows   = array();
		foreach ( $GLOBALS['wp_test_options'] as $name => $value ) {
			if ( str_starts_with( (string) $name, $prefix ) && count( $rows ) < (int) $prepared['a'][1] ) {
				$rows[] = array(
					'option_name'  => $name,
					'option_value' => $value,
				);
			}
		}
		return $rows;
	}
	public function query( array $prepared ): int {
		$GLOBALS['mirror_test_deletes'] = ( $GLOBALS['mirror_test_deletes'] ?? 0 ) + 1;
		foreach ( $prepared['a'] as $name ) {
			unset( $GLOBALS['wp_test_options'][ $name ] );
		}
		return count( $prepared['a'] );
	}
}
$GLOBALS['wpdb'] = new Mirror_Test_Wpdb();

$now      = 1795000000;
$vid      = '3f2b8c1e-9d4a-4c7b-8e2f-1a6d5c4b3e21';
$phone_h  = hash( 'sha256', '21622123456' );
$simple   = new WC_Product( 501, '', 'Masque V-Line', 'masque', 22.0 );
$variable = new WC_Product_Variable( 600, '', 'Ceinture', 'ceinture', 39.0 );
$size     = new WC_Product_Variation( 601, $variable, '', 'Ceinture L', 39.0 );
$hidden   = new WC_Product( 502, '', 'Brouillon', 'brouillon', 10.0 );

/**
 * Fresh request: mobile browser, cookies, production, switch on.
 *
 * @param array $options Options to start from.
 */
function mirror_reset( array $options = array() ): void {
	lb_reset( $options );
	$GLOBALS['wp_test_post_status'] = array( 502 => 'draft' );
	$GLOBALS['wp_test_auth_user']   = false;
	$GLOBALS['wp_test_user_caps']   = array();
	$GLOBALS['mirror_test_deletes'] = 0;
	$_SERVER['REMOTE_ADDR']         = '197.0.0.10';
	$_SERVER['HTTP_USER_AGENT']     = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148';
	$_COOKIE                        = array(
		'_fbp'      => 'fb.1.1795000000000.1234567890',
		'_fbc'      => 'fb.1.1795000000000.IwAR0abc',
		'famma_vid' => $GLOBALS['vid'],
	);
}

/**
 * One relay entry.
 *
 * @param string $name  Event name.
 * @param string $id    Event ID.
 * @param array  $items Items [ID, quantity].
 * @param int    $age   Age in milliseconds.
 * @param string $url   Page URL.
 * @return array
 */
function relay( string $name, string $id, array $items = array(), int $age = 1500, string $url = 'https://qa.invalid/produit/masque/?fbclid=x#avis' ): array {
	return array(
		'n'  => $name,
		'id' => $id,
		'u'  => $url,
		'a'  => $age,
		'i'  => $items,
	);
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

/** Rows of the queue, decoded. */
function queued(): array {
	$rows = array();
	foreach ( $GLOBALS['wp_test_options'] as $name => $value ) {
		if ( str_starts_with( (string) $name, Capi_Mirror_Queue::PREFIX ) ) {
			$rows[] = json_decode( (string) $value, true );
		}
	}
	return $rows;
}

$uuid = '0b6f7c1e-2d3a-4b5c-8d9e-0f1a2b3c4d5e';
$pv   = relay( 'PageView', $uuid );
$vc   = relay( 'ViewContent', '1c6f7c1e-2d3a-4b5c-8d9e-0f1a2b3c4d5e', array( array( 501, 1 ) ) );

// ── Route : rejets ───────────────────────────────────────────────────────────
mirror_reset();
$rejects = array(
	'corps vide'                   => '',
	'corps > 8 Ko'                 => body( array( relay( 'PageView', $uuid, array(), 1500, 'https://qa.invalid/?' . str_repeat( 'x', 8200 ) ) ) ),
	'JSON invalide'                => '{"e":[',
	'événement hors liste blanche' => body( array( relay( 'Purchase', $uuid ) ) ),
	'event_id mal formé'           => body( array( relay( 'PageView', 'a b<script>' ) ) ),
	'event_id trop court'          => body( array( relay( 'PageView', 'abc' ) ) ),
	'URL d’un autre domaine'       => body( array( relay( 'PageView', $uuid, array(), 1500, 'https://evil.example/produit/' ) ) ),
	'URL d’un sous-domaine piège'  => body( array( relay( 'PageView', $uuid, array(), 1500, 'https://qa.invalid.evil.example/' ) ) ),
	'URL javascript:'              => body( array( relay( 'PageView', $uuid, array(), 1500, 'javascript:alert(1)' ) ) ),
	'âge négatif'                  => body( array( relay( 'PageView', $uuid, array(), -5 ) ) ),
	'âge > 1 heure'                => body( array( relay( 'PageView', $uuid, array(), 3600001 ) ) ),
	'PageView avec articles'       => body( array( relay( 'PageView', $uuid, array( array( 501, 1 ) ) ) ) ),
	'ViewContent sans article'     => body( array( relay( 'ViewContent', $uuid ) ) ),
	'ViewContent à deux articles'  => body( array( relay( 'ViewContent', $uuid, array( array( 501, 1 ), array( 601, 1 ) ) ) ) ),
	'AddToCart quantité 0'         => body( array( relay( 'AddToCart', $uuid, array( array( 501, 0 ) ) ) ) ),
	'AddToCart quantité 21'        => body( array( relay( 'AddToCart', $uuid, array( array( 501, 21 ) ) ) ) ),
	'AddToCart quantité 1,5'       => body( array( relay( 'AddToCart', $uuid, array( array( 501, 1.5 ) ) ) ) ),
	'AddToCart ID texte'           => body( array( relay( 'AddToCart', $uuid, array( array( '501', 1 ) ) ) ) ),
	'AddToCart produit inconnu'    => body( array( relay( 'AddToCart', $uuid, array( array( 999, 1 ) ) ) ) ),
	'AddToCart produit non publié' => body( array( relay( 'AddToCart', $uuid, array( array( 502, 1 ) ) ) ) ),
	'InitiateCheckout 21 articles' => body( array( relay( 'InitiateCheckout', $uuid, array_fill( 0, 21, array( 501, 1 ) ) ) ) ),
);
foreach ( $rejects as $label => $raw ) {
	l5_check( 0 === Capi_Mirror::accept( $raw, $now ) && array() === queued(), 'Route : rejet — ' . $label . '.' );
}
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';
l5_check( 0 === Capi_Mirror::accept( body( array( $pv ) ), $now ), 'Route : robot (user-agent) écarté.' );
$_SERVER['HTTP_USER_AGENT'] = '';
l5_check( 0 === Capi_Mirror::accept( body( array( $pv ) ), $now ), 'Route : sans user-agent (requis par Meta pour website), rien.' );

mirror_reset();
$GLOBALS['wp_test_auth_user'] = 7;
$GLOBALS['wp_test_user_caps'] = array( 'manage_woocommerce' => true );
l5_check( 0 === Capi_Mirror::accept( body( array( $pv ) ), $now ), 'Route : gérant connecté (cookie de connexion relu, REST sans nonce) → rien.' );
$GLOBALS['wp_test_options']['famma_track_internal'] = true;
l5_check( 1 === Capi_Mirror::accept( body( array( $pv ) ), $now ), 'Route : gérant avec « Suivre mes visites (mode test) » → relayé, comme le Pixel.' );
mirror_reset( array( 'famma_meta_browser_mirror' => false ) );
l5_check( 0 === Capi_Mirror::accept( body( array( $pv ) ), $now ) && array() === queued(), 'Interrupteur coupé : la route ne range rien.' );
mirror_reset();
$GLOBALS['wp_test_environment'] = 'staging';
l5_check( 0 === Capi_Mirror::accept( body( array( $pv ) ), $now ), 'Copie hors production : rien n’est rangé.' );
mirror_reset( array( 'famma_meta_access_token' => '' ) );
l5_check( 0 === Capi_Mirror::accept( body( array( $pv ) ), $now ), 'Sans jeton CAPI : rien n’est rangé.' );

mirror_reset();
$accepted = 0;
for ( $call = 0; $call < 125; $call++ ) {
	$accepted += Capi_Mirror::accept( body( array( relay( 'PageView', sprintf( 'rate-limit-%04d', $call ) ) ) ), $now );
}
l5_check( 120 === $accepted, 'Limite par IP : 120 appels acceptés sur dix minutes, les suivants ignorés.' );
mirror_reset();
$GLOBALS['wp_test_hooks']['famma_capi_mirror_site_limit'] = array( array( static fn() => 2, 1 ) );
l5_check( 2 === Capi_Mirror::accept( body( array( $pv, $vc ) ), $now ) && 0 === Capi_Mirror::accept( body( array( relay( 'PageView', 'site-limit-0001' ) ) ), $now ), 'Plafond du site atteint : les lots suivants sont ignorés.' );

// ── Recalcul de la valeur, même event_id, user_data de la requête ───────────
mirror_reset();
$add = relay( 'AddToCart', 'famma_add_7f3a9c', array( array( 501, 3 ) ) ) + array( 'value' => 0.001 );
$ic  = relay( 'InitiateCheckout', '2c6f7c1e-2d3a-4b5c-8d9e-0f1a2b3c4d5e', array( array( 501, 2 ), array( 601, 1 ) ), 90000 );
l5_check( 4 === Capi_Mirror::accept( body( array( $pv, $vc, $add, $ic, $pv ), $phone_h ), $now ), 'Lot valide : 4 événements rangés, le doublon du même lot ignoré.' );
$rows   = queued();
$by     = array_column( array_column( $rows, 'e' ), null, 'event_name' );
$atc    = $by['AddToCart']['custom_data'];
$check  = $by['InitiateCheckout']['custom_data'];
$viewed = $by['ViewContent']['custom_data'];
l5_check( 66.0 === (float) $atc['value'] && 'TND' === $atc['currency'] && array( '501' ) === $atc['content_ids'] && 3 === $atc['contents'][0]['quantity'], 'AddToCart : valeur recalculée (3 × 22 = 66 TND), jamais celle du navigateur.' );
l5_check( 83.0 === (float) $check['value'] && 3 === $check['num_items'] && array( '501', '601' ) === $check['content_ids'], 'InitiateCheckout : 2 × 22 + 39 = 83 TND, 3 articles, ID Meta de la taille.' );
l5_check( 22.0 === (float) $viewed['value'] && 'product' === $viewed['content_type'] && ! isset( $viewed['content_name'] ), 'ViewContent : prix du produit, profil standard (content_name retiré).' );
l5_check( ! isset( $by['PageView']['custom_data'] ), 'PageView : aucun custom_data.' );
l5_check( 'famma_add_7f3a9c' === $by['AddToCart']['event_id'] && $uuid === $by['PageView']['event_id'], 'Même event_id que le Pixel (identifiant navigateur et identifiant serveur de l’express).' );
l5_check( $now - 1 === $by['PageView']['event_time'] && $now - 90 === $by['InitiateCheckout']['event_time'], 'event_time = horloge du serveur moins l’âge relayé.' );
l5_check( 'https://qa.invalid/produit/masque/?fbclid=x' === $by['PageView']['event_source_url'] && 'website' === $by['PageView']['action_source'], 'event_source_url sur le domaine du site, sans fragment ; action_source website.' );
$ud = $by['PageView']['user_data'];
l5_check( '197.0.0.10' === $ud['client_ip_address'] && str_contains( $ud['client_user_agent'], 'iPhone' ) && 'fb.1.1795000000000.1234567890' === $ud['fbp'] && 'fb.1.1795000000000.IwAR0abc' === $ud['fbc'], 'user_data : IP et user-agent de la requête, fbp et fbc des cookies.' );
l5_check( hash( 'sha256', $vid ) === $ud['external_id'], 'user_data : external_id = SHA-256 du cookie famma_vid (comme fbq init et la CAPI).' );
l5_check( Capi_User_Data::hash( Capi_User_Data::phone( '22 123 456' ) ) === $ud['ph'] && $phone_h === $ud['ph'], 'Téléphone : l’empreinte du navigateur (SHA-256 de 216 + 8 chiffres) est celle de la CAPI.' );
mirror_reset();
Capi_Mirror::accept( body( array( $pv ), '21622123456' ), $now );
l5_check( ! isset( queued()[0]['e']['user_data']['ph'] ), 'Un numéro en clair dans le corps n’est jamais accepté comme ph.' );
mirror_reset();
Capi_Mirror::accept( body( array( relay( 'ViewContent', $uuid, array( array( 600, 1 ) ) ) ) ), $now );
l5_check( 'product_group' === queued()[0]['e']['custom_data']['content_type'] && array( '600' ) === queued()[0]['e']['custom_data']['content_ids'], 'ViewContent d’un produit à tailles : product_group, ID du parent (règle du Pixel).' );
mirror_reset( array( 'famma_meta_data_profile' => 'minimal' ) );
Capi_Mirror::accept( body( array( $add ) ), $now );
l5_check( array( 'currency', 'value' ) === array_keys( queued()[0]['e']['custom_data'] ), 'Profil minimal : le doublon ne garde que la valeur et la devise.' );
mirror_reset();
Capi_Mirror::accept( body( array( $pv ) ), $now );
Capi_Mirror::accept( body( array( $pv ) ), $now );
l5_check( 1 === count( queued() ), 'Même nom + event_id relayé deux fois : une seule ligne dans la file.' );

// ── Envoi groupé ─────────────────────────────────────────────────────────────
mirror_reset();
Capi_Mirror::accept( body( array( $pv, $vc, $add ), $phone_h ), $now );
$GLOBALS['wp_test_http_response'] = l5_response( 200, '{"events_received":3,"messages":[],"fbtrace_id":"QA59"}', array( 'facebook-api-version' => 'v26.0' ) );
$report                           = Capi_Mirror_Queue::flush( $now + 70 );
$sent                             = json_decode( (string) ( $GLOBALS['wp_test_http'][0]['args']['body'] ?? '' ), true );
l5_check( 1 === count( $GLOBALS['wp_test_http'] ) && 3 === count( $sent['data'] ?? array() ) && 3 === $report['sent'], 'Envoi groupé : 3 événements → UNE requête à Meta.' );
l5_check( str_ends_with( (string) $GLOBALS['wp_test_http'][0]['url'], '/000000000000001/events' ) && ! str_contains( (string) $GLOBALS['wp_test_http'][0]['url'], 'access_token' ), 'Requête par Capi_Transport : jeton dans le corps, jamais dans l’URL.' );
l5_check( array() === queued() && false === get_option( Capi_Mirror_Queue::DUE ) && false === get_option( Capi_Mirror_Queue::LOCK ) && 1 === $GLOBALS['mirror_test_deletes'], 'Accusé de Meta : file purgée en une requête, repère et verrou retirés.' );
l5_check( 0 === Capi_Mirror_Queue::flush( $now + 400 )['sent'] && 1 === count( $GLOBALS['wp_test_http'] ), 'File vide : la tâche suivante ne fait aucun appel.' );
$log = implode( "\n", l5_capi_lines() );
l5_check( str_contains( $log, 'BrowserMirror' ) && ! str_contains( $log, '197.0.0.10' ) && ! str_contains( $log, '22123456' ) && ! str_contains( $log, $phone_h ) && ! str_contains( $log, 'QA-L5-FAKE-TOKEN' ), 'Journal famma-capi : une ligne par lot, ni IP, ni téléphone, ni empreinte, ni jeton.' );

mirror_reset(
	array(
		'famma_meta_capi_test_mode'  => true,
		'famma_meta_test_event_code' => 'TEST12345',
	)
);
Capi_Mirror::accept( body( array( $pv ) ), $now );
Capi_Mirror_Queue::flush( $now + 70 );
$sent = json_decode( (string) ( $GLOBALS['wp_test_http'][0]['args']['body'] ?? '' ), true );
l5_check( 'TEST12345' === ( $sent['test_event_code'] ?? '' ), 'Mode test Meta : le lot porte le test_event_code.' );

mirror_reset();
Capi_Mirror::accept( body( array( $pv, $vc ) ), $now );
$GLOBALS['wp_test_http_response'] = l5_response( 200, '{"events_received":1,"messages":[],"fbtrace_id":"QA59"}', array( 'facebook-api-version' => 'v26.0' ) );
$first                            = Capi_Mirror_Queue::flush( $now + 70 );
l5_check( 'transient' === $first['class'] && 2 === $first['kept'] && 2 === count( queued() ) && 1 === queued()[0]['n'], 'Accusé incomplet (1 sur 2) : lot gardé pour une nouvelle tentative.' );
Capi_Mirror_Queue::flush( $now + 140 );
$last = Capi_Mirror_Queue::flush( $now + 210 );
l5_check( 2 === $last['lost'] && array() === queued() && 3 === count( $GLOBALS['wp_test_http'] ), 'Trois tentatives au plus, puis le lot est écarté.' );
add_filter( 'famma_capi_health_reasons', array( Capi_Mirror_Health::class, 'health_reasons' ), 10, 3 );
add_filter( 'famma_capi_health_label', array( Capi_Mirror_Health::class, 'health_label' ), 10, 2 );
$reasons = Capi_Health_Rules::reasons( Capi_Health::signals(), $now + 300 );
$mirror  = array_values( array_filter( $reasons, static fn( array $r ): bool => 'mirror_lost' === $r['code'] ) );
l5_check( 1 === count( $mirror ) && 'orange' === $mirror[0]['niveau'] && '2' === $mirror[0]['detail'], 'Santé : 2 événements perdus = raison orange (jamais rouge).' );
l5_check( str_contains( Capi_Health_Rules::label( $mirror[0] ), '2 événement(s)' ), 'Santé : libellé lisible de la raison.' );
$GLOBALS['wp_test_mail'] = array();
$famma_wpdb              = $GLOBALS['wpdb'];
unset( $GLOBALS['wpdb'] ); // Verrou de l'alerte par option (sa requête SQL n'est pas doublée ici).
$state           = Capi_Health::evaluate( $now + 300 );
$GLOBALS['wpdb'] = $famma_wpdb;
l5_check( 'orange' === $state['statut'] && array() === $GLOBALS['wp_test_mail'], 'Santé : état orange, aucune alerte e-mail pour le doublon.' );

mirror_reset();
Capi_Mirror::accept( body( array( $pv ) ), $now );
$GLOBALS['wp_test_http_response'] = l5_response( 400, '{"error":{"code":100,"message":"Invalid parameter","fbtrace_id":"QA59"}}' );
$refused                          = Capi_Mirror_Queue::flush( $now + 70 );
l5_check( 'contract' === $refused['class'] && 1 === $refused['lost'] && array() === queued(), 'Refus de contenu : lot écarté tout de suite, compté perdu.' );
l5_check( ! isset( Capi_Health::signals()['contract'] ), 'Un refus du doublon ne touche pas les signaux d’OrderPlaced et Purchase.' );

mirror_reset();
Capi_Mirror::accept( body( array( $pv ) ), $now );
$expired = Capi_Mirror_Queue::flush( $now + 3601 + 70 );
l5_check( 1 === $expired['lost'] && array() === $GLOBALS['wp_test_http'], 'Événement en attente depuis plus d’une heure : écarté, rien n’est envoyé.' );

mirror_reset();
Capi_Mirror::accept( body( array( $pv ) ), $now );
$GLOBALS['wp_test_options']['famma_meta_browser_mirror'] = false;
$off = Capi_Mirror_Queue::flush( $now + 70 );
l5_check( 'disabled' === $off['class'] && array() === queued() && array() === $GLOBALS['wp_test_http'] && 0 === $off['lost'], 'Interrupteur coupé après coup : file vidée, rien envoyé, pas compté en échec.' );

mirror_reset();
Capi_Mirror::accept( body( array( $pv ) ), $now );
$GLOBALS['wp_test_options'][ Capi_Mirror_Queue::LOCK ] = $now + 60;
l5_check( 0 === Capi_Mirror_Queue::flush( $now + 70 )['sent'] && array() === $GLOBALS['wp_test_http'], 'Verrou tenu par un autre envoi : rien ne part en double.' );
l5_check( 1 === Capi_Mirror_Queue::flush( $now + 400 )['sent'], 'Verrou abandonné (plus de 2 minutes) : repris.' );

mirror_reset();
Capi_Mirror::accept( body( array( $pv ) ), time() - 30 );
Capi_Mirror_Queue::flush_if_due();
l5_check( array() === $GLOBALS['wp_test_http'] && 1 === count( queued() ), 'Fin de requête du relais : rien ne part avant 60 s d’attente.' );
$GLOBALS['wp_test_options'][ Capi_Mirror_Queue::DUE ] = time() - 61;
Capi_Mirror_Queue::flush_if_due();
l5_check( 1 === count( $GLOBALS['wp_test_http'] ) && array() === queued(), 'Fin de requête du relais : envoi groupé dès 60 s d’attente.' );
$GLOBALS['wp_test_cron'] = array();
$famma_mirror            = Famma_Unit_Harness::bare( Capi_Mirror::class );
$famma_mirror->ensure_scheduled();
l5_check( Capi_Mirror::CRON_SCHEDULE === ( $GLOBALS['wp_test_cron'][ Capi_Mirror::CRON ][1] ?? '' ) && 300 === $famma_mirror->cron_schedule( array() )[ Capi_Mirror::CRON_SCHEDULE ]['interval'], 'Tâche WP-Cron toutes les 5 minutes, planifiée une fois.' );

// ── Rendu PHP : même eventID que le Pixel ───────────────────────────────────
mirror_reset();
$html = lb_product_page( $simple );
l5_check( str_contains( $html, "fbq('track','PageView',{},{eventID:fammaEventId()});window.fammaRelay&&fammaRelay(\"PageView\",window.fammaLastEventId);" ), 'PageView : relayé juste après fbq, avec l’eventID que fammaEventId() vient de tirer.' );
l5_check( 1 === preg_match( '/fbq\("track","ViewContent",\{.*?\},\{eventID:fammaEventId\(\)\}\);window\.fammaRelay&&fammaRelay\("ViewContent",window\.fammaLastEventId,\[\[501,1\]\]\);/', $html ), 'ViewContent : relayé avec le même eventID et l’ID WooCommerce.' );
l5_check( str_contains( Tracking_Events::AJAX_LISTENER_SCRIPT, 'w.fammaRelay("AddToCart",eid,[[pid,q]])' ), 'AddToCart (liste, AJAX) : même eventID passé à fbq et au relais.' );
$GLOBALS['wp_test_scripts']['famma-pixels'] = array( 'after' => array() );
Famma_Unit_Harness::bare( Capi_Mirror::class )->enqueue_relay();
$relay = implode( '', $GLOBALS['wp_test_scripts']['famma-pixels']['after'] );
l5_check( str_contains( $relay, 'window.fammaRelay=window.fammaRelay||' ) && str_contains( $relay, '"https:\/\/qa.invalid\/wp-json\/famma\/v1\/meta-relay"' ), 'Relais imprimé pour un visiteur suivi, vers famma/v1/meta-relay.' );
mirror_reset( array( 'famma_meta_browser_mirror' => false ) );
$GLOBALS['wp_test_scripts']['famma-pixels'] = array( 'after' => array() );
Famma_Unit_Harness::bare( Capi_Mirror::class )->enqueue_relay();
l5_check( array() === $GLOBALS['wp_test_scripts']['famma-pixels']['after'], 'Interrupteur coupé : aucun relais imprimé.' );
mirror_reset();
lb_login( array( 'manage_options' ) );
$GLOBALS['wp_test_scripts']['famma-pixels'] = array( 'after' => array() );
Famma_Unit_Harness::bare( Capi_Mirror::class )->enqueue_relay();
l5_check( array() === $GLOBALS['wp_test_scripts']['famma-pixels']['after'], 'Session administrateur : aucun relais (pas de Pixel non plus).' );

// ── V3 et données sensibles ─────────────────────────────────────────────────
$includes = glob( ABSPATH . 'wp-content/plugins/famma-core/includes/*.php' );
$graph    = array();
foreach ( $includes as $file ) {
	if ( str_contains( (string) file_get_contents( $file ), 'graph.facebook.com' ) ) {
		$graph[] = basename( $file );
	}
}
l5_check( array( 'capi-transport.php' ) === $graph, 'V3 : graph.facebook.com n’apparaît que dans capi-transport.php.' );
$mirror_src = '';
foreach ( array( 'capi-mirror.php', 'capi-mirror-event.php', 'capi-mirror-queue.php', 'capi-mirror-health.php', 'capi-mirror-settings.php' ) as $file ) {
	$mirror_src .= (string) file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/' . $file );
}
l5_check( ! str_contains( $mirror_src, 'wp_remote_' ) && ! str_contains( $mirror_src, 'error_log' ) && ! str_contains( $mirror_src, 'billing_phone' ), 'Doublon : aucun appel HTTP hors Capi_Transport, aucun error_log, aucun champ téléphone lu.' );
mirror_reset();
Capi_Mirror::accept( body( array( $pv ), $phone_h ), $now );
$stored = (string) json_encode( $GLOBALS['wp_test_options'] );
l5_check( ! str_contains( $stored, '22123456' ) && ! str_contains( $stored, 'QA-L5-FAKE-TOKEN' ), 'Stockage : ni téléphone en clair ni jeton dans la file.' );
l5_check( 64 === strlen( Capi_Mirror_Event::user_data( $phone_h )['ph'] ), 'Seule l’empreinte (64 caractères hexadécimaux) circule.' );

echo 'RESULT ' . $GLOBALS['l5_checks'] . '/' . $GLOBALS['l5_checks'] . PHP_EOL;
