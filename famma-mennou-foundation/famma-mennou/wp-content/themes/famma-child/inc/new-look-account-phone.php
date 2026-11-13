<?php
/**
 * Nouveau look — création de compte par numéro, comme la maquette.
 *
 * Case `acct_phone` (lot 2 de l'espace client), avec `acct_look` : champs
 * « Téléphone » (+216), « Nom et prénom », puis le mot de passe de
 * WooCommerce avec la jauge de la maquette, et l'adresse à déplier. Le champ
 * e-mail de WooCommerce est masqué (e-mail facultatif, décision D1). Les
 * contrôles et la création du compte sont dans famma-core
 * (`Famma\Core\Account_Phone`) ; aucun modèle WooCommerce n'est copié.
 *
 * Case décochée, ou plugin absent : chaque fonction sort aussitôt.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le formulaire par numéro est-il montré ?
 *
 * @return bool
 */
function famma_child_account_phone(): bool {
	return famma_child_account_auth()
		&& class_exists( '\Famma\Core\Account_Phone' )
		&& \Famma\Core\Account_Phone::active();
}

/**
 * Texte réglé dans FAMMA → Pages → « Mon compte ».
 *
 * @param string $key Clé.
 * @return string
 */
function famma_child_account_phone_text( string $key ): string {
	return \Famma\Core\Account_Phone_Texts::text( $key );
}

/**
 * Pose les crochets de la page de connexion.
 *
 * @return void
 */
function famma_child_account_phone_setup(): void {
	if ( ! famma_child_account_phone() || is_wc_endpoint_url() ) {
		return;
	}

	add_filter( 'body_class', 'famma_child_account_phone_body_class' );
	add_filter( 'gettext_woocommerce', 'famma_child_account_phone_strings', 20, 2 );
	add_action( 'woocommerce_register_form_tag', 'famma_child_account_phone_form_tag' );
	add_action( 'woocommerce_register_form_start', 'famma_child_account_phone_fields', 5 );
	add_action( 'woocommerce_register_form', 'famma_child_account_phone_address', 5 );
	add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_phone', 28 );
}
add_action( 'template_redirect', 'famma_child_account_phone_setup' );

/**
 * Classe du corps pour la feuille de style.
 *
 * @param array<int, string> $classes Classes du corps.
 * @return array<int, string>
 */
function famma_child_account_phone_body_class( array $classes ): array {
	$classes[] = 'famma-nl-acct-phone';

	return $classes;
}

/**
 * Champ de connexion : « Téléphone ou e-mail ».
 *
 * @param string $translation Texte traduit.
 * @param string $text        Texte d'origine.
 * @return string
 */
function famma_child_account_phone_strings( $translation, $text ) {
	if ( 'Username or email address' !== $text ) {
		return $translation;
	}

	$ours = famma_child_account_phone_text( 'login_label' );

	return '' !== $ours ? $ours : $translation;
}

/**
 * Le champ e-mail masqué reste « obligatoire » dans le modèle : le
 * navigateur ne doit pas bloquer l'envoi. Les champs sont contrôlés par le
 * script, puis par famma-core.
 *
 * @return void
 */
function famma_child_account_phone_form_tag(): void {
	echo ' novalidate';
}

/**
 * Coche verte des champs valides (maquette : .okm).
 *
 * @return string
 */
function famma_child_account_phone_okm(): string {
	return '<svg class="famma-acct-okm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
}

/**
 * Valeur renvoyée après une erreur.
 *
 * @param string $key Champ.
 * @return string
 */
function famma_child_account_phone_posted( string $key ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- réaffichage seulement ; famma-core vérifie le jeton à l'envoi.
	return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
}

/**
 * Résumé des erreurs, téléphone, nom et prénom (avant l'e-mail masqué).
 *
 * @return void
 */
function famma_child_account_phone_fields(): void {
	$help = famma_child_account_phone_text( 'tel_help' );
	$okm  = famma_child_account_phone_okm();
	$kses = famma_child_svg_allowed_html();

	echo '<div class="famma-acct-esum" id="famma-up-sum" role="alert" tabindex="-1" hidden></div>';
	echo '<div class="famma-acct-ph">';

	printf(
		'<p class="form-row form-row-wide famma-acct-f"><label for="famma_phone">%1$s</label><span class="famma-acct-inp"><span class="famma-acct-pre" dir="ltr">+216</span><input type="tel" class="input-text" name="famma_phone" id="famma_phone" inputmode="numeric" autocomplete="tel-national" placeholder="XX XXX XXX" value="%2$s" aria-describedby="%3$s famma_phone-e">%4$s</span>%5$s<span class="famma-acct-err" id="famma_phone-e" hidden></span></p>',
		esc_html( famma_child_account_phone_text( 'tel_label' ) ),
		esc_attr( famma_child_account_phone_posted( 'famma_phone' ) ),
		'' !== $help ? 'famma_phone-h' : '',
		wp_kses( $okm, $kses ),
		'' !== $help ? '<span class="famma-acct-help" id="famma_phone-h">' . esc_html( $help ) . '</span>' : ''
	);

	printf(
		'<p class="form-row form-row-wide famma-acct-f"><label for="famma_name">%1$s</label><span class="famma-acct-inp"><input type="text" class="input-text" name="famma_name" id="famma_name" autocomplete="name" value="%2$s" aria-describedby="famma_name-e">%3$s</span><span class="famma-acct-err" id="famma_name-e" hidden></span></p>',
		esc_html( famma_child_account_phone_text( 'name_label' ) ),
		esc_attr( famma_child_account_phone_posted( 'famma_name' ) ),
		wp_kses( $okm, $kses )
	);

	echo '</div>';
}

/**
 * « Ajouter mon adresse maintenant (facultatif) », et le champ piège.
 *
 * @return void
 */
function famma_child_account_phone_address(): void {
	$states = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_states( 'TN' ) : array();
	$gov    = strtoupper( famma_child_account_phone_posted( 'famma_gov' ) );
	$addr   = famma_child_account_phone_posted( 'famma_addr' );
	$open   = '' !== $gov || '' !== $addr;
	$kses   = famma_child_svg_allowed_html();

	printf(
		'<button type="button" class="famma-acct-dbtn" aria-expanded="%1$s" aria-controls="famma-up-addr">%2$s<span>%3$s <span class="famma-acct-opt">%4$s</span></span></button>',
		$open ? 'true' : 'false',
		wp_kses( famma_child_icon( 'chevron' ), $kses ),
		esc_html( famma_child_account_phone_text( 'addr_toggle' ) ),
		esc_html( famma_child_account_phone_text( 'optional' ) )
	);

	printf( '<div class="famma-acct-disc%s" id="famma-up-addr"><div><div class="famma-acct-disc__in">', $open ? ' is-open' : '' );

	if ( is_array( $states ) && array() !== $states ) {
		printf(
			'<p class="form-row form-row-wide famma-acct-f"><label for="famma_gov">%1$s</label><select name="famma_gov" id="famma_gov" autocomplete="address-level1"><option value="">%2$s</option>',
			esc_html( famma_child_account_phone_text( 'gov_label' ) ),
			esc_html( famma_child_account_phone_text( 'gov_choose' ) )
		);

		foreach ( $states as $code => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $code ), selected( $gov, (string) $code, false ), esc_html( (string) $label ) );
		}

		echo '</select></p>';
	}

	printf(
		'<p class="form-row form-row-wide famma-acct-f"><label for="famma_addr">%1$s</label><input type="text" class="input-text" name="famma_addr" id="famma_addr" autocomplete="street-address" value="%2$s"></p>',
		esc_html( famma_child_account_phone_text( 'addr_label' ) ),
		esc_attr( $addr )
	);

	echo '</div></div></div>';

	// Champ piège : invisible, rempli seulement par les robots.
	echo '<p class="famma-acct-hp" aria-hidden="true"><label for="famma_hp">' . esc_html__( 'Ne pas remplir', 'famma-child' ) . '</label><input type="text" name="famma_hp" id="famma_hp" value="" tabindex="-1" autocomplete="off"></p>';
}

/**
 * Feuille et script du formulaire. La jauge de WooCommerce (zxcvbn, qui
 * bloque l'envoi) laisse la place à celle de la maquette : 8 caractères au
 * moins, contrôlés aussi par famma-core.
 *
 * @return void
 */
function famma_child_enqueue_account_phone(): void {
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = $dir . '/assets/css/new-look-account-phone.css';
	$js  = $dir . '/assets/js/new-look-account-phone.js';

	wp_dequeue_script( 'wc-password-strength-meter' );

	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'famma-new-look-account-phone', $uri . '/assets/css/new-look-account-phone.css', array( 'famma-new-look-account' ), (string) filemtime( $css ) );
	}

	if ( ! file_exists( $js ) ) {
		return;
	}

	wp_enqueue_script( 'famma-new-look-account-phone', $uri . '/assets/js/new-look-account-phone.js', array(), (string) filemtime( $js ), true );

	$keys = array( 'err_tel', 'err_tel_tn', 'err_name', 'err_pw', 'pw_m1', 'pw_m2', 'pw_m3', 'sum_h', 'sum_hs' );
	$t    = array();

	foreach ( $keys as $key ) {
		$t[ $key ] = famma_child_account_phone_text( $key );
	}

	wp_add_inline_script( 'famma-new-look-account-phone', 'window.fammaAcctPhone=' . wp_json_encode( $t ) . ';', 'before' );
}
