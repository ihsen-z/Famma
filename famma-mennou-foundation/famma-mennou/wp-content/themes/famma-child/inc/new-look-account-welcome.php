<?php
/**
 * Nouveau look — écran « Bienvenue » après l'inscription (case
 * `acct_welcome` de famma-core, maquette espace client #welcome).
 *
 * Présentation seulement : la marque posée à l'inscription et sa lecture
 * unique vivent dans `Famma\Core\Account_Welcome`. Avec script, l'écran
 * occupe seul la page (en-tête, onglets et contenu attendent), puis laisse
 * place au compte et à ses animations d'arrivée ; sans script, il reste en
 * haut du compte.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Écran dû sur cette page (lu une seule fois par requête).
 *
 * @param array{title: string, text: string}|null $set Écran à garder, au premier appel.
 * @return array{title: string, text: string}|null
 */
function famma_child_account_welcome_data( ?array $set = null ): ?array {
	static $data = null;

	if ( null !== $set ) {
		$data = $set;
	}

	return $data;
}

/**
 * Lit l'écran dû et pose les crochets. Après la redirection du tableau de
 * bord vers « Mes commandes » (priorité 20), pour ne pas le perdre.
 *
 * @return void
 */
function famma_child_account_welcome_setup(): void {
	if ( ! is_user_logged_in() || ! is_account_page() || ! class_exists( '\Famma\Core\Account_Welcome' ) || ! \Famma\Core\Account_Welcome::active() ) {
		return;
	}

	$data = \Famma\Core\Account_Welcome::take( get_current_user_id() );

	if ( null === $data || '' === $data['title'] ) {
		return;
	}

	famma_child_account_welcome_data( $data );
	add_filter( 'body_class', 'famma_child_account_welcome_body_class' );
	add_action( 'woocommerce_before_account_navigation', 'famma_child_account_welcome', 0 );
	add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_welcome', 31 );
}
add_action( 'template_redirect', 'famma_child_account_welcome_setup', 30 );

/**
 * Classe du corps : la feuille met le compte en attente derrière l'écran.
 *
 * @param array<int, string> $classes Classes du corps.
 * @return array<int, string>
 */
function famma_child_account_welcome_body_class( array $classes ): array {
	$classes[] = 'famma-acct-welcoming';

	return $classes;
}

/**
 * L'écran : mascotte, titre, phrase.
 *
 * @return void
 */
function famma_child_account_welcome(): void {
	$data = famma_child_account_welcome_data();

	if ( null === $data ) {
		return;
	}

	echo '<section class="famma-acct-welcome" data-famma-welcome aria-live="polite">';
	echo famma_child_account_mascot(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passé par wp_kses().
	echo '<h2>' . esc_html( $data['title'] ) . '</h2>';

	if ( '' !== $data['text'] ) {
		echo '<p>' . esc_html( $data['text'] ) . '</p>';
	}

	echo '</section>';
}

/**
 * Feuille de style et script de l'écran.
 *
 * @return void
 */
function famma_child_enqueue_account_welcome(): void {
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = $dir . '/assets/css/new-look-account-welcome.css';
	$js  = $dir . '/assets/js/new-look-account-welcome.js';

	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'famma-new-look-account-welcome', $uri . '/assets/css/new-look-account-welcome.css', array( 'famma-new-look-account' ), (string) filemtime( $css ) );
	}

	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'famma-new-look-account-welcome', $uri . '/assets/js/new-look-account-welcome.js', array(), (string) filemtime( $js ), true );
	}
}
