<?php
/**
 * Nouveau look — finitions de l'espace client (case `acct_finish` de
 * famma-core, lot 43).
 *
 * Présentation seulement ; le découpage du nom, la marque de déconnexion,
 * l'initiale et les textes vivent dans `Famma\Core\Account_Finish` :
 *
 * - « Mes informations » : champ « Nom et prénom » (Prénom, Nom et Nom
 *   affiché de WooCommerce restent dans la page mais cachés), e-mail sans
 *   astérisque avec « (facultatif) » et son aide pour un compte créé par
 *   numéro ; le script range le téléphone sous le nom, comme la maquette ;
 * - « Mes commandes » sans commande : mascotte, phrase et bouton vers la
 *   boutique (appelé par inc/new-look-account-track.php) ;
 * - inscription et connexion : « Mot de passe » sans astérisque ;
 * - « Vous êtes déconnecté. » après « Me déconnecter » ;
 * - pastille orange avec l'initiale sur l'icône compte de l'en-tête ;
 * - sur téléphone, le bas du compte laisse la place du bouton WhatsApp.
 *
 * Aucun modèle WooCommerce n'est copié. Case décochée ou plugin absent :
 * aucun crochet n'est posé.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Case cochée (pour la personne en cours) et famma-core présent ?
 *
 * @return bool
 */
function famma_child_account_finish(): bool {
	return class_exists( '\Famma\Core\Account_Finish' ) && \Famma\Core\Account_Finish::active();
}

/**
 * Un texte des finitions.
 *
 * @param string $key Clé du champ.
 * @return string
 */
function famma_child_account_finish_text( string $key ): string {
	return \Famma\Core\Account_Finish::text( $key );
}

/**
 * Le compte en cours a-t-il un e-mail facultatif (compte créé par numéro) ?
 *
 * @return bool
 */
function famma_child_account_finish_mail_optional(): bool {
	return is_user_logged_in()
		&& class_exists( '\Famma\Core\Account_Phone' )
		&& \Famma\Core\Account_Phone::is_technical( (string) wp_get_current_user()->user_email );
}

/**
 * Pose les crochets, après ceux des panneaux (priorités 10 et 11).
 *
 * @return void
 */
function famma_child_account_finish_setup(): void {
	if ( ! famma_child_account_finish() ) {
		return;
	}

	if ( is_user_logged_in() || is_account_page() ) {
		add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_finish', 33 );
	}

	if ( ! is_account_page() ) {
		return;
	}

	add_filter( 'body_class', 'famma_child_account_finish_body_class' );

	if ( ! is_user_logged_in() ) {
		add_filter( 'gettext_woocommerce', 'famma_child_account_finish_auth_strings', 10, 2 );

		if ( \Famma\Core\Account_Finish::logged_out() ) {
			add_action( 'wp_footer', 'famma_child_account_finish_out' );
		}

		return;
	}

	if ( is_wc_endpoint_url( 'edit-account' ) && function_exists( 'famma_child_account_forms' ) && famma_child_account_forms() ) {
		add_action( 'woocommerce_edit_account_form_start', 'famma_child_account_finish_name', 20 );
		add_action( 'woocommerce_edit_account_form_fields', 'famma_child_account_finish_mail_help', 5 );
		add_filter( 'gettext_woocommerce', 'famma_child_account_finish_infos_strings', 10, 2 );
		add_filter( 'body_class', 'famma_child_account_finish_name_class' );
	}
}
add_action( 'template_redirect', 'famma_child_account_finish_setup', 12 );

/**
 * Classes du corps pour la feuille.
 *
 * @param array<int, string> $classes Classes du corps.
 * @return array<int, string>
 */
function famma_child_account_finish_body_class( array $classes ): array {
	$classes[] = 'famma-acct-fin';

	if ( famma_child_account_finish_mail_optional() ) {
		$classes[] = 'famma-acct-fin--mail-opt';
	}

	return $classes;
}

/**
 * Classe du corps quand « Nom et prénom » remplace les champs de
 * WooCommerce : la feuille ne cache ceux-ci que dans ce cas.
 *
 * @param array<int, string> $classes Classes du corps.
 * @return array<int, string>
 */
function famma_child_account_finish_name_class( array $classes ): array {
	$classes[] = 'famma-acct-fin--name';

	return $classes;
}

/**
 * Feuille et script des finitions.
 *
 * @return void
 */
function famma_child_enqueue_account_finish(): void {
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = $dir . '/assets/css/new-look-account-finish.css';
	$js  = $dir . '/assets/js/new-look-account-finish.js';

	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'famma-new-look-account-finish', $uri . '/assets/css/new-look-account-finish.css', array( 'famma-tokens' ), (string) filemtime( $css ) );
	}

	if ( is_account_page() && file_exists( $js ) ) {
		wp_enqueue_script(
			'famma-new-look-account-finish',
			$uri . '/assets/js/new-look-account-finish.js',
			array(),
			(string) filemtime( $js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}

/**
 * Inscription et connexion : « Mot de passe », comme la maquette.
 *
 * @param string $translation Texte traduit.
 * @param string $text        Texte d'origine (anglais).
 * @return string
 */
function famma_child_account_finish_auth_strings( $translation, $text ) {
	if ( 'Password' !== $text ) {
		return $translation;
	}

	$ours = famma_child_account_finish_text( 'fin_pw' );

	return '' !== $ours ? $ours : $translation;
}

/**
 * « Mes informations » : libellé de l'e-mail, comme la maquette.
 *
 * @param string $translation Texte traduit.
 * @param string $text        Texte d'origine (anglais).
 * @return string
 */
function famma_child_account_finish_infos_strings( $translation, $text ) {
	if ( 'Email address' !== $text ) {
		return $translation;
	}

	$ours = famma_child_account_finish_text( 'fin_mail' );

	return '' !== $ours ? $ours : $translation;
}

/**
 * Champ « Nom et prénom », en tête de la boîte « Identité ».
 *
 * @return void
 */
function famma_child_account_finish_name(): void {
	$label = famma_child_account_finish_text( 'fin_name' );
	$name  = \Famma\Core\Account_Finish::NAME;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- réaffichage après une erreur ; WooCommerce vérifie son jeton.
	$value = isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : \Famma\Core\Account_Finish::full_name( wp_get_current_user() );

	printf(
		'<p class="woocommerce-form-row form-row form-row-wide famma-acct-f famma-acct-fin-name"><label for="%1$s">%2$s</label><input type="text" class="woocommerce-Input input-text" name="%1$s" id="%1$s" autocomplete="name" value="%3$s" required aria-required="true"></p>',
		esc_attr( $name ),
		esc_html( '' !== $label ? $label : __( 'Nom et prénom', 'famma-child' ) ),
		esc_attr( $value )
	);
}

/**
 * Aide sous l'e-mail et mention « (facultatif) » (posée sur le libellé par
 * le script), pour un compte créé par numéro.
 *
 * @return void
 */
function famma_child_account_finish_mail_help(): void {
	if ( ! famma_child_account_finish_mail_optional() ) {
		return;
	}

	$help = famma_child_account_finish_text( 'fin_mail_h' );

	printf(
		'<p class="famma-acct-fin-help" id="famma-acct-mail-h" data-opt="%1$s"%2$s>%3$s</p>',
		esc_attr( famma_child_account_finish_text( 'fin_opt' ) ),
		'' === $help ? ' hidden' : '',
		esc_html( $help )
	);
}

/**
 * L'écran « aucune commande » de la maquette remplace-t-il celui de
 * WooCommerce ? Non si la case est décochée ou son texte vide.
 *
 * @return bool
 */
function famma_child_account_finish_orders_ready(): bool {
	return famma_child_account_finish() && '' !== famma_child_account_finish_text( 'fin_ord_empty' );
}

/**
 * « Mes commandes » sans commande : mascotte, phrase, bouton vers la
 * boutique.
 *
 * @return void
 */
function famma_child_account_finish_orders_empty(): void {
	$text  = famma_child_account_finish_text( 'fin_ord_empty' );
	$title = class_exists( '\Famma\Core\Account_Profile' ) ? \Famma\Core\Account_Profile::text( 'tab_orders' ) : '';
	$go    = famma_child_account_finish_text( 'fin_ord_go' );
	// Filtre de WooCommerce relu tel quel : même adresse que son bouton.
	$shop = (string) apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

	echo '<div class="famma-ocs">';

	if ( '' !== $title ) {
		echo '<h2 class="famma-ocs__h">' . esc_html( $title ) . '</h2>';
	}

	echo '<div class="famma-acct-oempty">';
	echo function_exists( 'famma_child_account_mascot' ) ? famma_child_account_mascot() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passé par wp_kses dans famma_child_account_mascot().
	echo '<p>' . esc_html( $text ) . '</p>';

	if ( '' !== $go ) {
		echo '<a class="button famma-acct-oempty__go" href="' . esc_url( $shop ) . '">' . esc_html( $go ) . '</a>';
	}

	echo '</div></div>';
}

/**
 * « Vous êtes déconnecté. », en bas de l'écran d'accès.
 *
 * @return void
 */
function famma_child_account_finish_out(): void {
	$text = famma_child_account_finish_text( 'fin_out' );

	if ( '' !== $text ) {
		echo '<div class="famma-acct-out" role="status" data-famma-out>' . esc_html( $text ) . '</div>';
	}
}

/**
 * Pastille de l'initiale sur l'icône compte (en-tête et panneau mobile).
 *
 * @return void
 */
function famma_child_account_finish_initial(): void {
	if ( ! is_user_logged_in() || ! famma_child_account_finish() ) {
		return;
	}

	$initial = \Famma\Core\Account_Finish::initial( get_current_user_id() );

	if ( '' !== $initial ) {
		echo '<span class="famma-acct-ini" aria-hidden="true">' . esc_html( $initial ) . '</span>';
	}
}
