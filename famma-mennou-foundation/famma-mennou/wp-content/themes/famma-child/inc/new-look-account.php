<?php
/**
 * Nouveau look — « Mon compte » à la manière de la maquette espace client.
 *
 * Case `acct_look` (lot 1 du plan de portage de l'espace client). Habillage
 * seul : aucun champ, aucun e-mail, aucune règle de WooCommerce ne change, et
 * aucun modèle WooCommerce n'est copié. Les formulaires restent ceux de
 * `myaccount/form-login.php` ; le script ne fait que ranger les deux colonnes
 * existantes en onglets, et sans script elles restent l'une sous l'autre.
 *
 * Case décochée, ou plugin absent : chaque fonction sort aussitôt.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La page courante reçoit-elle l'habillage du compte ?
 *
 * @return bool
 */
function famma_child_account_look(): bool {
	return function_exists( 'is_account_page' ) && is_account_page() && famma_child_new_look( 'acct_look' );
}

/**
 * Classe du corps sur laquelle la feuille s'appuie.
 *
 * @param array<int, string> $classes Classes du corps.
 * @return array<int, string>
 */
function famma_child_account_look_body_class( array $classes ): array {
	if ( famma_child_account_look() ) {
		$classes[] = 'famma-nl-acct';
	}

	return $classes;
}
add_filter( 'body_class', 'famma_child_account_look_body_class' );

/**
 * Feuille et script, chargés seulement sur « Mon compte ».
 *
 * @return void
 */
function famma_child_enqueue_account_look(): void {
	if ( ! famma_child_account_look() ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = $dir . '/assets/css/new-look-account.css';
	$js  = $dir . '/assets/js/new-look-account.js';

	if ( file_exists( $css ) ) {
		wp_enqueue_style(
			'famma-new-look-account',
			$uri . '/assets/css/new-look-account.css',
			array( 'famma-tokens' ),
			(string) filemtime( $css )
		);
	}

	if ( is_user_logged_in() || ! file_exists( $js ) ) {
		return;
	}

	// Les onglets n'existent que sur le formulaire de connexion / création.
	wp_enqueue_script(
		'famma-new-look-account',
		$uri . '/assets/js/new-look-account.js',
		array(),
		(string) filemtime( $js ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	wp_add_inline_script(
		'famma-new-look-account',
		'window.fammaAccount=' . wp_json_encode(
			array(
				'tabs' => __( 'Se connecter ou créer un compte', 'famma-child' ),
			)
		) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_look', 24 );

/**
 * Retire « Téléchargements » du menu : la boutique ne vend rien à télécharger.
 *
 * Seul le lien disparaît ; la page reste servie par WooCommerce.
 *
 * @param array<string, string> $items Liens du menu du compte.
 * @return array<string, string>
 */
function famma_child_account_look_menu( array $items ): array {
	if ( famma_child_new_look( 'acct_look' ) ) {
		unset( $items['downloads'] );
	}

	return $items;
}
add_filter( 'woocommerce_account_menu_items', 'famma_child_account_look_menu', 20 );
