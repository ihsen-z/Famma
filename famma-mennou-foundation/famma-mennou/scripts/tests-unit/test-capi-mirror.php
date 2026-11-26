<?php
/**
 * Lot 59 (Q21, Q22) — doublon serveur (CAPI) des événements navigateur : réception.
 *
 * - contrôles du lot : chaque rejet (corps, JSON, nom, event_id, URL, âge,
 *   articles, produit non publié, robot, visiteur interne, interrupteur,
 *   environnement, jeton) ;
 * - corrections post-relecture : origine (I1), adresse reconstruite (I1),
 *   quantités et valeur bornées, valeur du Pixel reprise (M3), écarts comptés
 *   (M4), `ph` vérifié côté serveur (I1), rejeu refusé 24 h (I1), limite en
 *   événements, plafond du site lu avant toute écriture, groupes d'adresses
 *   bornés et IPv6 par /64 (I2), une seule requête par page avec le journal
 *   et mesures de requêtes / écritures (I4) ;
 * - user_data de la requête, même event_id que le Pixel, rendu PHP du relais.
 * L'envoi groupé, le verrou et la santé sont testés par test-capi-mirror-queue.php,
 * le JavaScript par test-capi-mirror.js.
 * Run with: php scripts/tests-unit/test-capi-mirror.php
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap-browser.php.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Generic.Classes.DuplicateClassName -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.EscapeOutput.OutputNotEscaped -- Command-line test: superglobals are fixtures, output is a report.
require __DIR__ . '/bootstrap-browser.php';
require __DIR__ . '/double-mirror-wpdb.php';
require __DIR__ . '/double-mirror-wp.php';
Famma_Unit_Harness::load_include( 'visitor-collector.php' );

use Famma\Core\Capi_Mirror_Event;
use Famma\Core\Capi_Mirror_Route;
use Famma\Core\Capi_Mirror_Script;
use Famma\Core\Capi_User_Data;
use Famma\Core\Rate_Counter;
use Famma\Core\Tracking_Events;
use Famma\Core\Visitor_Collector;
use Famma\Core\Visitor_Context;

$now   = 1795000000;
$uuid  = '0b6f7c1e-2d3a-4b5c-8d9e-0f1a2b3c4d5e';
$pv    = relay( 'PageView', $uuid );
$vc    = relay( 'ViewContent', '1c6f7c1e-2d3a-4b5c-8d9e-0f1a2b3c4d5e', array( array( 501, 1 ) ) );
$hash  = hash( 'sha256', '21622123456' );
$count = static fn( string $raw ): int => Capi_Mirror_Route::accept( $raw, $GLOBALS['now'] );

// ── Rejets : rien n'est rangé, chaque entrée invalide est comptée ─────────────
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
	'chemin avec guillemet'        => body( array( relay( 'PageView', $uuid, array(), 1500, 'https://qa.invalid/a"b' ) ) ),
	'âge négatif'                  => body( array( relay( 'PageView', $uuid, array(), -5 ) ) ),
	'âge > 1 heure'                => body( array( relay( 'PageView', $uuid, array(), 3600001 ) ) ),
	'PageView avec articles'       => body( array( relay( 'PageView', $uuid, array( array( 501, 1 ) ) ) ) ),
	'ViewContent sans article'     => body( array( relay( 'ViewContent', $uuid ) ) ),
	'ViewContent à deux articles'  => body( array( relay( 'ViewContent', $uuid, array( array( 501, 1 ), array( 601, 1 ) ) ) ) ),
	'AddToCart quantité 0'         => body( array( relay( 'AddToCart', $uuid, array( array( 501, 0 ) ) ) ) ),
	'AddToCart quantité 1,5'       => body( array( relay( 'AddToCart', $uuid, array( array( 501, 1.5 ) ) ) ) ),
	'AddToCart ID texte'           => body( array( relay( 'AddToCart', $uuid, array( array( '501', 1 ) ) ) ) ),
	'AddToCart produit inconnu'    => body( array( relay( 'AddToCart', $uuid, array( array( 999, 1 ) ) ) ) ),
	'AddToCart produit non publié' => body( array( relay( 'AddToCart', $uuid, array( array( 502, 1 ) ) ) ) ),
);
foreach ( $rejects as $label => $raw ) {
	l5_check( 0 === $count( $raw ) && array() === mirror_queued(), 'Rejet — ' . $label . '.' );
}
l5_check( 17 === mirror_health()['content'], 'M4 : chaque entrée valide en JSON mais écartée est comptée (« content »), jamais en silence.' );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';
l5_check( 0 === $count( body( array( $pv ) ) ), 'Robot (user-agent) écarté.' );
$_SERVER['HTTP_USER_AGENT'] = '';
l5_check( 0 === $count( body( array( $pv ) ) ), 'Sans user-agent (requis par Meta pour website) : rien.' );
mirror_reset();
$GLOBALS['wp_test_auth_user'] = 7;
$GLOBALS['wp_test_user_caps'] = array( 'manage_woocommerce' => true );
l5_check( 0 === $count( body( array( $pv ) ) ), 'Gérant connecté (cookie de connexion relu, REST sans nonce) : rien.' );
$GLOBALS['wp_test_options']['famma_track_internal'] = true;
l5_check( 1 === $count( body( array( $pv ) ) ), 'Gérant avec « Suivre mes visites (mode test) » : relayé, comme le Pixel.' );
foreach ( array(
	'interrupteur coupé' => array( 'famma_meta_browser_mirror' => false ),
	'sans jeton CAPI'    => array( 'famma_meta_access_token' => '' ),
) as $label => $options ) {
	mirror_reset( $options );
	l5_check( 0 === $count( body( array( $pv ) ) ) && array() === mirror_queued(), ucfirst( $label ) . ' : rien n’est rangé.' );
}
mirror_reset();
$GLOBALS['wp_test_environment'] = 'staging';
l5_check( 0 === $count( body( array( $pv ) ) ), 'Copie hors production : rien n’est rangé.' );

// ── I1 : origine du site exigée (autres sites refusés) ──────────────────────
foreach ( array(
	'Origin d’un autre site'              => array( 'https://evil.example', '' ),
	'Origin « null » et Referer étranger' => array( 'null', 'https://evil.example/page' ),
	'ni Origin ni Referer'                => array( '', '' ),
) as $label => $headers ) {
	mirror_reset();
	mirror_headers( $headers[0], $headers[1] );
	l5_check( 0 === $count( body( array( $pv ) ) ) && 1 === mirror_health()['origin'] && null === mirror_rate( 'mr_' ), 'I1 : ' . $label . ' → refusé, compté (« origin »), aucun compteur écrit.' );
}
mirror_reset();
mirror_headers( '', 'https://www.qa.invalid/produit/masque/' );
l5_check( 1 === $count( body( array( $pv ) ) ), 'I1 : sans Origin, un Referer du site (avec « www. ») suffit.' );

// ── I1 : adresse reconstruite, paramètres en liste blanche ──────────────────
mirror_reset();
$count( body( array( relay( 'PageView', $uuid, array(), 1500, 'https://qa.invalid/commande/order-received/42/?key=wc_order_x&email=a%40b.c&utm_source=fb&utm_campaign=v%20line&fbclid=IwAR0&tel=22123456#avis' ) ) ) );
l5_check( 'https://qa.invalid/commande/order-received/42/?utm_source=fb&utm_campaign=v%20line&fbclid=IwAR0' === ( mirror_queued()[0]['e']['event_source_url'] ?? '' ), 'I1 : event_source_url = chemin + utm_*, fbclid seulement (ni clé de commande, ni e-mail, ni téléphone, ni fragment).' );

// ── I1 / M4 : quantités et lignes bornées, écart compté ─────────────────────
mirror_reset();
$count( body( array( relay( 'AddToCart', 'famma_add_qty25', array( array( 501, 25 ) ) ), relay( 'InitiateCheckout', 'ic-twelve-lines', array_fill( 0, 12, array( 501, 1 ) ) ) ) ) );
$by = mirror_by_name();
l5_check( 10 === $by['AddToCart']['custom_data']['contents'][0]['quantity'] && 220.0 === (float) $by['AddToCart']['custom_data']['value'], 'Quantité 25 ramenée à 10 (valeur 10 × 22 = 220 TND), l’événement part.' );
l5_check( 10 === $by['InitiateCheckout']['custom_data']['num_items'] && 2 === mirror_health()['adjusted'], 'InitiateCheckout à 12 lignes : 10 gardées ; les deux écarts sont comptés (« adjusted »).' );

// ── M3 : valeur du Pixel reprise, bornée par le catalogue ────────────────────
mirror_reset();
$ic     = static fn( string $id, $value ): array => relay( 'InitiateCheckout', $id, array( array( 501, 2 ), array( 601, 1 ) ), 90000, 'https://qa.invalid/commander/', $value );
$count( body( array( $ic( 'ic-lot-price-44', 44.0 ), $ic( 'ic-too-high-500', 500 ), $ic( 'ic-negative-01', -1 ), $ic( 'ic-text-value', '83' ), $ic( 'ic-no-value-00', null ), relay( 'ViewContent', 'vc-browser-value', array( array( 501, 1 ) ), 1500, 'https://qa.invalid/produit/masque/', 1.0 ) ) ) );
$values = array();
foreach ( mirror_queued() as $row ) {
	$values[ $row['e']['event_id'] ] = (float) $row['e']['custom_data']['value'];
}
l5_check( 44.0 === $values['ic-lot-price-44'], 'M3 : lot à 44 TND (plafond catalogue 2 × 22 + 39 = 83) : la valeur du Pixel est gardée.' );
l5_check( 83.0 === $values['ic-too-high-500'] && 83.0 === $values['ic-negative-01'] && 83.0 === $values['ic-text-value'] && 3 === mirror_health()['adjusted'], 'M3 : valeur au-dessus du plafond, négative ou non numérique → plafond envoyé, écart compté.' );
l5_check( 83.0 === $values['ic-no-value-00'] && 22.0 === $values['vc-browser-value'], 'Sans valeur relayée : plafond ; ViewContent : toujours le prix WooCommerce.' );

// ── Recalcul, même event_id, user_data de la requête ────────────────────────
mirror_reset();
$add = relay( 'AddToCart', 'famma_add_7f3a9c', array( array( 501, 3 ) ), 1500, 'https://qa.invalid/produit/masque/', 66.0 );
l5_check( 4 === $count( body( array( $pv, $vc, $add, $ic( '2c6f7c1e-2d3a-4b5c', 83.0 ), $pv ) ) ), 'Lot valide : 4 événements rangés, le doublon du même lot écarté.' );
$by = mirror_by_name();
l5_check( 66.0 === (float) $by['AddToCart']['custom_data']['value'] && 'TND' === $by['AddToCart']['custom_data']['currency'] && array( '501' ) === $by['AddToCart']['custom_data']['content_ids'], 'AddToCart : devise et identifiants de WooCommerce.' );
l5_check( 3 === $by['InitiateCheckout']['custom_data']['num_items'] && array( '501', '601' ) === $by['InitiateCheckout']['custom_data']['content_ids'], 'InitiateCheckout : 3 articles, ID Meta de la taille.' );
l5_check( 'product' === $by['ViewContent']['custom_data']['content_type'] && ! isset( $by['ViewContent']['custom_data']['content_name'] ) && ! isset( $by['PageView']['custom_data'] ), 'ViewContent : profil standard (content_name retiré) ; PageView sans custom_data.' );
l5_check( 'famma_add_7f3a9c' === $by['AddToCart']['event_id'] && $uuid === $by['PageView']['event_id'] && $now - 1 === $by['PageView']['event_time'], 'Même event_id que le Pixel ; event_time = horloge du serveur moins l’âge relayé.' );
$ud = $by['PageView']['user_data'];
l5_check( '197.0.0.10' === $ud['client_ip_address'] && str_contains( $ud['client_user_agent'], 'iPhone' ) && 'fb.1.1795000000000.1234567890' === $ud['fbp'] && 'fb.1.1795000000000.IwAR0abc' === $ud['fbc'] && hash( 'sha256', $GLOBALS['vid'] ) === $ud['external_id'], 'user_data : IP et user-agent de la requête, fbp, fbc et external_id des cookies.' );

// ── I1 : ph gardé seulement s'il correspond à une saisie connue du serveur ──
mirror_reset();
$count( body( array( $pv ), $hash ) );
l5_check( ! isset( mirror_queued()[0]['e']['user_data']['ph'] ), 'ph sans saisie connue pour ce visiteur : retiré du doublon.' );
mirror_reset();
$wpdb->inputs = array(
	array(
		'visitor_id' => $GLOBALS['vid'],
		'phone_norm' => '22123456',
	),
);
$count( body( array( $pv ), $hash ) );
$ph = mirror_queued()[0]['e']['user_data']['ph'] ?? '';
l5_check( $hash === $ph && Capi_User_Data::hash( Capi_User_Data::phone( '22 123 456' ) ) === $ph, 'ph = empreinte du numéro saisi par ce visiteur (216 + 8 chiffres, celle de la CAPI) : gardé.' );
mirror_reset();
$wpdb->inputs = array(
	array(
		'visitor_id' => $GLOBALS['vid'],
		'phone_norm' => '22123456',
	),
);
$count( body( array( $pv ), hash( 'sha256', '21698765432' ) ) );
$forged = ! isset( mirror_queued()[0]['e']['user_data']['ph'] );
mirror_reset( array( 'famma_visitor_inputs_enabled' => '0' ) );
$count( body( array( $pv ), $hash ) );
l5_check( $forged && ! isset( mirror_queued()[0]['e']['user_data']['ph'] ), 'ph d’un autre numéro, ou capture des saisies coupée : retiré.' );
mirror_reset();
$count( body( array( $pv ), '21622123456' ) );
l5_check( ! isset( mirror_queued()[0]['e']['user_data']['ph'] ) && 0 === $wpdb->reads_inputs, 'Un numéro en clair n’est jamais accepté comme ph (et la table n’est même pas lue).' );

// ── I1 : rejeu refusé ───────────────────────────────────────────────────────
mirror_reset();
$count( body( array( $pv ) ) );
l5_check( 0 === $count( body( array( $pv ) ) ) && 1 === count( mirror_queued() ) && 1 === mirror_health()['replay'], 'Même nom + event_id reçu deux fois : une seule ligne, le rejeu compté (« replay »).' );

// ── I1 / I2 : limite en événements, plafond lu avant toute écriture ────────
mirror_reset();
$accepted = 0;
for ( $call = 0; $call < 13; $call++ ) {
	$batch = array();
	for ( $n = 0; $n < 10; $n++ ) {
		$batch[] = relay( 'PageView', sprintf( 'rate-%02d-%02d-xx', $call, $n ) );
	}
	$accepted += $count( body( $batch ) );
}
l5_check( 120 === $accepted && 10 === mirror_health()['rate'], 'Limite par groupe d’adresses : 120 ÉVÉNEMENTS sur dix minutes (12 lots de 10), le 13e lot refusé et compté.' );
mirror_reset();
$GLOBALS['wp_test_hooks']['famma_capi_mirror_site_limit'] = array( array( static fn() => 2, 1 ) );
$first                  = $count( body( array( $pv, $vc ) ) );
$wpdb->writes           = 0;
$_SERVER['REMOTE_ADDR'] = '197.0.0.99';
$second                 = $count( body( array( relay( 'PageView', 'site-limit-0001' ) ) ) );
l5_check( 2 === $first && 0 === $second && 1 === mirror_health()['site'], 'Plafond du site atteint : lot suivant refusé et compté (« site »).' );
l5_check( 0 === $wpdb->writes && null === mirror_rate( 'mr_' . Visitor_Context::rate_group() ), 'I2 : plafond lu AVANT le compteur par groupe : une nouvelle adresse refusée n’écrit aucune ligne de compteur ni de file (seule l’option de santé est mise à jour).' );

// ── I2 : groupes d'adresses bornés, IPv6 par /64 ───────────────────────────
$group = static function ( string $ip ): string {
	$_SERVER['REMOTE_ADDR'] = $ip;
	return Visitor_Context::rate_group();
};
l5_check( $group( '2a01:e0a:1:2::10' ) === $group( '2a01:e0a:1:2:ffff:ffff:ffff:ffff' ) && $group( '2a01:e0a:1:2::10' ) !== $group( '2a01:e0a:1:3::10' ), 'I2 : deux adresses du même /64 partagent un compteur ; un autre /64 non.' );
l5_check( $group( '::ffff:197.0.0.10' ) === $group( '197.0.0.10' ) && 1 === preg_match( '/^[0-9a-f]{3}$/', $group( '197.0.0.10' ) ) && '' === $group( 'not-an-ip' ), 'I2 : IPv4 écrite en IPv6 = son IPv4 ; empreinte de 3 caractères (4 096 lignes au plus par route).' );
mirror_reset();
for ( $call = 0; $call < 3; $call++ ) {
	Visitor_Context::beacon_limited( 'vj', 120, 1, $now );
}
$row = mirror_rate( 'vj_' . Visitor_Context::rate_group() );
Visitor_Context::beacon_limited( 'vj', 120, 1, $now + Rate_Counter::WINDOW );
l5_check( 3 === (int) $row % 100000 && 1 === Rate_Counter::used( 'vj_' . Visitor_Context::rate_group(), $now + Rate_Counter::WINDOW ) && 1 === count( mirror_rate_rows() ), 'Compteur : UNE ligne par groupe, sans ligne de délai, remise à zéro à la tranche suivante.' );
$sources = file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/visitor-collector.php' ) . file_get_contents( ABSPATH . 'wp-content/plugins/famma-core/includes/visitor-inputs-collector.php' );
l5_check( str_contains( $sources, "beacon_limited( 'vj'" ) && str_contains( $sources, "beacon_limited( 'vi'" ) && ! str_contains( $sources, "rate_limited( 'famma_v" ), 'I2 : routes du journal (famma_vj_) et des saisies (famma_vi_) sur le même compteur borné.' );

// ── I4 : une seule requête par page, avec le journal ────────────────────────
mirror_reset();
$wpdb->writes = 0;
$wpdb->reads  = 0;
$journal      = array(
	'v' => $GLOBALS['vid'],
	's' => '5d1c2b3a-4e5f-4a6b-8c7d-9e0f1a2b3c4d',
	'e' => array(
		array(
			'n'  => 'page_view',
			'pt' => 'product',
			'p'  => '/produit/masque/',
		),
	),
	'm' => array( 'e' => array( $pv, $vc, $add, $ic( 'ic-one-request', 83.0 ) ) ),
);
$response     = Famma_Unit_Harness::bare( Visitor_Collector::class )->collect( new WP_REST_Request( (string) json_encode( $journal ) ) );
l5_check( 204 === $response->status && 4 === count( mirror_queued() ) && 1 === count( $wpdb->journal ) && array() === $GLOBALS['wp_test_http'], 'I4 : UN envoi du journal porte le journal ET les 4 événements du doublon ; aucun appel à Meta.' );
l5_check( 5 === $wpdb->writes, 'I4 : 5 écritures pour cette page (compteur journal, journal, compteur doublon, file en une requête, plafond du site) ; avant (7896a7c) : 2 requêtes et 12 écritures.' );
$GLOBALS['wp_test_hooks'] = array();
Capi_Mirror_Route::receive( new WP_REST_Request( body( array( relay( 'PageView', 'own-route-0001' ) ) ) ) );
l5_check( empty( $GLOBALS['wp_test_hooks']['shutdown'] ) && array() === $GLOBALS['wp_test_http'], 'I4 : la route propre ne programme aucun envoi en fin de requête (WP-Cron seulement).' );

// ── Rendu PHP : même eventID et même valeur que le Pixel ────────────────────
mirror_reset();
$html = lb_product_page( $GLOBALS['simple'] );
l5_check( str_contains( $html, "fbq('track','PageView',{},{eventID:fammaEventId()});window.fammaRelay&&fammaRelay(\"PageView\",window.fammaLastEventId);" ), 'PageView : relayé juste après fbq, avec l’eventID que fammaEventId() vient de tirer.' );
l5_check( 1 === preg_match( '/fbq\("track","ViewContent",\{.*?"value":22.*?\},\{eventID:fammaEventId\(\)\}\);window\.fammaRelay&&fammaRelay\("ViewContent",window\.fammaLastEventId,\[\[501,1\]\],22\);/', $html ), 'ViewContent : relayé avec le même eventID, l’ID WooCommerce et la valeur du Pixel.' );
l5_check( str_contains( Tracking_Events::AJAX_LISTENER_SCRIPT, 'w.fammaRelay("AddToCart",eid,[[pid,q]],p.value)' ), 'AddToCart (liste, AJAX) : même eventID et même valeur passés à fbq et au relais.' );
$GLOBALS['wp_test_scripts']['famma-pixels'] = array( 'after' => array() );
Capi_Mirror_Script::enqueue_relay();
$relay = implode( '', $GLOBALS['wp_test_scripts']['famma-pixels']['after'] );
l5_check( str_contains( $relay, 'window.fammaRelay=window.fammaRelay||' ) && str_contains( $relay, 'f.take=take;f.attach=' ) && str_contains( $relay, '"https:\/\/qa.invalid\/wp-json\/famma\/v1\/meta-relay"' ), 'Relais imprimé pour un visiteur suivi : take/attach pour le journal, route propre en secours.' );
mirror_reset( array( 'famma_meta_browser_mirror' => false ) );
$GLOBALS['wp_test_scripts']['famma-pixels'] = array( 'after' => array() );
Capi_Mirror_Script::enqueue_relay();
l5_check( array() === $GLOBALS['wp_test_scripts']['famma-pixels']['after'], 'Interrupteur coupé : aucun relais imprimé.' );
mirror_reset();
lb_login( array( 'manage_options' ) );
$GLOBALS['wp_test_scripts']['famma-pixels'] = array( 'after' => array() );
Capi_Mirror_Script::enqueue_relay();
l5_check( array() === $GLOBALS['wp_test_scripts']['famma-pixels']['after'], 'Session administrateur : aucun relais (pas de Pixel non plus).' );
l5_check( array() === Capi_Mirror_Event::from_payload( array( 'e' => array() ), $now )['events'] && 0 === Capi_Mirror_Route::accept_payload( array( 'e' => 'x' ), $now ), 'Lot vide ou mal formé : aucun événement.' );

echo 'RESULT ' . $GLOBALS['l5_checks'] . '/' . $GLOBALS['l5_checks'] . PHP_EOL;
