<?php
/**
 * Plugin Name: FAMMA — balises Google (Search Console + GA4)
 * Description: Pose la balise de validation Google Search Console et le tag Google Analytics 4 dans le <head> des pages publiques. Rien pour l'administration ni pour les administrateurs connectés.
 * Version:     0.1.0
 * Author:      FAMMA
 *
 * Pourquoi un mu-plugin. Jusqu'a octobre 2026, ces deux balises venaient de
 * Site Kit by Google (balise `GT-WRCTBPR9` -> `G-0M9XKVLDBB`). Site Kit a ete
 * desactive en production, et GA4 a cesse de recevoir des donnees sans la
 * moindre alerte. Un mu-plugin ne se desactive pas depuis l'administration et
 * ne depend ni du theme ni de famma-core : il survit aux mises a jour comme a
 * une fausse manoeuvre dans la liste des extensions.
 *
 * Un seul emetteur par balise. famma-core sait deja poser les deux (reglage
 * « FAMMA > Mesure » et constante FAMMA_GOOGLE_SITE_VERIFICATION), et Site Kit
 * pourrait etre reactive : un second gtag compterait chaque visite deux fois.
 * Chaque fonction ci-dessous s'efface donc si un autre emetteur est deja la.
 *
 * Retrait : supprimer ce fichier, puis purger le cache LiteSpeed.
 *
 * @package Famma\Local
 */

defined( 'ABSPATH' ) || exit;

/*
 * Identifiants publics, visibles dans le code source de toute page : ce ne
 * sont pas des secrets. Une constante definie dans wp-config.php l'emporte.
 */
defined( 'FAMMA_GOOGLE_TAGS_VERIFICATION' ) || define( 'FAMMA_GOOGLE_TAGS_VERIFICATION', 'M5s4b9M-Rmk58hEV_3gfTQevGVMl-KQMt0zhiVf3rEM' );
defined( 'FAMMA_GOOGLE_TAGS_GA4_ID' ) || define( 'FAMMA_GOOGLE_TAGS_GA4_ID', 'G-0M9XKVLDBB' );

/**
 * Dit si la requete courante doit recevoir les balises Google.
 *
 * Les administrateurs et gerants de boutique connectes sont exclus, pour ne
 * pas gonfler les statistiques avec leurs propres visites. Les clients
 * connectes, eux, restent mesures. Le cache public LiteSpeed n'est jamais
 * servi a un utilisateur connecte : une page sans balise ne peut donc pas y
 * etre mise en cache pour les visiteurs.
 *
 * Filtre `famma_google_tags_load` : false pour tout couper (par exemple en
 * attendant le choix d'un visiteur, si un bandeau de consentement arrive).
 *
 * @return bool
 */
function famma_google_tags_should_load(): bool {
	$load = ! is_admin()
		&& ! current_user_can( 'manage_options' )
		&& ! current_user_can( 'manage_woocommerce' );

	return (bool) apply_filters( 'famma_google_tags_load', $load );
}

/**
 * Imprime la balise de validation Google Search Console.
 *
 * S'efface si famma-core la pose deja via sa constante.
 *
 * @return void
 */
function famma_google_tags_verification(): void {
	if ( ! famma_google_tags_should_load() ) {
		return;
	}

	if ( defined( 'FAMMA_GOOGLE_SITE_VERIFICATION' ) && '' !== (string) FAMMA_GOOGLE_SITE_VERIFICATION ) {
		return;
	}

	printf(
		'<meta name="google-site-verification" content="%s" />%s',
		esc_attr( FAMMA_GOOGLE_TAGS_VERIFICATION ),
		"\n"
	);
}
add_action( 'wp_head', 'famma_google_tags_verification', 1 );

/**
 * Charge le tag Google Analytics 4.
 *
 * Passe par wp_enqueue_script plutot qu'un <script> en dur (ClaudeMemo §9).
 * La file `dataLayer` est declaree AVANT la bibliotheque, en position
 * « before » : WordPress retire l'attribut async de tout script suivi d'un
 * code « after » (WP_Scripts::filter_eligible_strategies), ce qui bloquerait
 * le premier affichage. gtag.js rejoue la file a son arrivee : aucun appel
 * n'est perdu.
 *
 * Priorite 100 : famma-core (`famma-ga4`) et Site Kit (`google_gtagjs`) ont
 * deja enfile leur propre tag a ce stade, s'ils en posent un.
 *
 * @return void
 */
function famma_google_tags_enqueue_ga4(): void {
	if ( ! famma_google_tags_should_load() ) {
		return;
	}

	if ( wp_script_is( 'famma-ga4', 'enqueued' ) || wp_script_is( 'google_gtagjs', 'enqueued' ) ) {
		return;
	}

	$id = FAMMA_GOOGLE_TAGS_GA4_ID;

	wp_enqueue_script(
		'famma-google-tags-ga4',
		'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $id ),
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- third-party URL, a ver= query would defeat Google's own caching.
		array(
			'strategy'  => 'async',
			'in_footer' => false,
		)
	);
	wp_add_inline_script(
		'famma-google-tags-ga4',
		'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . wp_json_encode( $id ) . ');',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'famma_google_tags_enqueue_ga4', 100 );
