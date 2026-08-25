<?php
/**
 * Applique les réglages de référencement saisis en production.
 *
 * Le code du plugin et du thème est partagé entre le dépôt et le site Local ;
 * seuls les contenus saisis en administration vivent en base, et ne se
 * transfèrent donc pas avec les fichiers. Ce script les rejoue à l'identique.
 *
 * Idempotent : relancé, il réécrit les mêmes valeurs sans rien dupliquer.
 *
 * Usage (hors de wp-admin, en CLI, depuis la racine WordPress) :
 *   php seed-seo-settings.php
 *
 * @package Famma\Core
 */

declare( strict_types = 1 );

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'WP_USE_THEMES', false );

$root = getcwd() . '/wp-load.php';

if ( ! is_readable( $root ) ) {
	fwrite( STDERR, "wp-load.php introuvable. Lancez le script depuis la racine WordPress.\n" );
	exit( 1 );
}

require $root;

/**
 * Réglages globaux, tels que saisis dans « FAMMA → Référencement ».
 *
 * `same_as` reste vide : aucun profil officiel n'a été fourni, et une adresse
 * inventée est exactement ce que le §58 interdit.
 */
$settings = array(
	'home_description_fr' => 'Boutique en ligne tunisienne : vous commandez, vous payez en espèces à la livraison, partout en Tunisie. Livraison offerte, déjà comprise dans le prix affiché.',
	'home_description_ar' => 'حانوت أونلاين تونسي: أطلب وخلّص كاش وقت ما توصلك الطلبية، في 24 ولاية. التوصيل مجاني ومحسوب في السوم المعروض.',
	'title_suffix_fr'     => 'Livraison offerte partout en Tunisie, paiement à la livraison',
	'title_suffix_ar'     => 'توصيل مجاني في كامل تونس، الخلاص عند الاستلام',
	'same_as'             => '',
);

/**
 * Descriptions par page, indexées sur le slug.
 *
 * @var array<string, array<string, string>>
 */
$pages = array(
	'boutique'  => array(
		'fr' => 'Le catalogue FAMMA MINNOU : livraison offerte partout en Tunisie, paiement en espèces à la livraison, commande sans création de compte.',
		'ar' => 'كاطالوغ فمّا مينّو: توصيل مجاني في كامل تونس، الخلاص كاش عند الاستلام، وتطلب بلا ما تعمل حساب.',
	),
	'a-propos'  => array(
		'fr' => 'FAMMA MINNOU est une boutique en ligne tunisienne : commande sans compte, appel de confirmation, paiement en espèces au livreur, livraison offerte.',
		'ar' => 'فمّا مينّو حانوت تونسي يبيع أونلاين، يوصّل في 24 ولاية والخلاص كاش وقت الاستلام.',
	),
	'contact'   => array(
		'fr' => 'Une question sur une commande, une livraison ou un produit ? Voici comment joindre FAMMA MINNOU, boutique en ligne tunisienne.',
		'ar' => 'عندك سؤال على طلبية، توصيل ولا منتج؟ هكا تنجم تتواصل مع فمّا مينّو، حانوت أونلاين تونسي.',
	),
	'faq'       => array(
		'fr' => 'Paiement en espèces à la livraison, livraison offerte dans les 24 gouvernorats, commande sans création de compte : les réponses aux questions les plus posées.',
		'ar' => 'الخلاص كاش وقت التوصيل، توصيل مجاني في 24 ولاية، وتطلب بلا حساب: إجابات على أكثر الأسئلة.',
	),
	'livraison' => array(
		'fr' => 'La livraison est offerte partout en Tunisie, dans les 24 gouvernorats : 0 TND, déjà compris dans le prix affiché. Vous payez en espèces au livreur.',
		'ar' => 'التوصيل مجاني في كامل تونس، في 24 ولاية: 0 دينار، محسوب في السوم المعروض. تخلّص كاش عند الاستلام.',
	),
);

echo "Site : " . home_url( '/' ) . "\n\n";

update_option( \Famma\Core\Seo::OPTION, $settings );
echo "[ok]   reglages globaux ecrits (" . count( $settings ) . " champs)\n";

$prefix  = \Famma\Core\Seo::META_PREFIX;
$manques = array();

foreach ( $pages as $slug => $texts ) {
	$page = get_page_by_path( $slug );

	if ( ! $page instanceof WP_Post ) {
		$manques[] = $slug;
		echo "[skip] page introuvable : {$slug}\n";
		continue;
	}

	foreach ( $texts as $lang => $text ) {
		update_post_meta( (int) $page->ID, $prefix . 'description_' . $lang, $text );
	}

	echo "[ok]   {$slug} (#{$page->ID}) : description FR + AR\n";
}

// Le plan de site et les archives d'auteur passent par des regles de
// reecriture : on les rafraichit pour que /llms.txt reponde tout de suite.
flush_rewrite_rules( false );
echo "\n[ok]   regles de reecriture rafraichies\n";

if ( array() !== $manques ) {
	echo "\nPages absentes de ce site : " . implode( ', ', $manques ) . "\n";
	exit( 1 );
}

echo "\nTermine.\n";
