<?php
/**
 * Nouveau look — « Mot de passe oublié ? » et « Supprimer mon compte »,
 * comme la maquette (lot 8 de l'espace client, case `acct_privacy`).
 *
 * Sous « Me connecter », le lien de WooCommerce laisse la place au bouton
 * qui se déplie : explication, « Demander sur WhatsApp » (décision D3, au
 * lieu du code SMS de la maquette) et le lien e-mail de WooCommerce. Dans
 * « Mes informations », la boîte rouge « Supprimer mon compte » suit
 * « Me déconnecter », avec la confirmation dans la page. La demande et son
 * annulation sont traitées dans famma-core (`Famma\Core\Account_Privacy`).
 *
 * Sans script : le dépliant est ouvert et la confirmation visible ; tout
 * marche par lien ou formulaire. Case décochée, ou plugin absent : chaque
 * fonction sort aussitôt.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Texte réglé dans FAMMA → Pages → « Mon compte ».
 *
 * @param string $key Clé.
 * @return string
 */
function famma_child_account_privacy_text( string $key ): string {
	return \Famma\Core\Account_Privacy_Texts::text( $key );
}

/**
 * L'aide « Mot de passe oublié » est-elle montrée ?
 *
 * @return bool
 */
function famma_child_account_privacy_help(): bool {
	return famma_child_account_auth()
		&& class_exists( '\Famma\Core\Account_Privacy' )
		&& \Famma\Core\Account_Privacy::help_active();
}

/**
 * La boîte « Supprimer mon compte » est-elle montrée ?
 *
 * @return bool
 */
function famma_child_account_privacy_delete(): bool {
	return function_exists( 'famma_child_account_forms' )
		&& famma_child_account_forms()
		&& class_exists( '\Famma\Core\Account_Privacy' )
		&& \Famma\Core\Account_Privacy::delete_active();
}

/**
 * Pose les crochets.
 *
 * @return void
 */
function famma_child_account_privacy_setup(): void {
	$help   = famma_child_account_privacy_help() && ! is_wc_endpoint_url();
	$delete = famma_child_account_privacy_delete() && is_wc_endpoint_url( 'edit-account' );

	if ( $help ) {
		add_filter( 'body_class', 'famma_child_account_privacy_body_class' );
		add_action( 'woocommerce_login_form_end', 'famma_child_account_privacy_forgot' );
	}

	// « Connexion » et « Supprimer mon compte » partagent la colonne de
	// droite sur ordinateur, comme la maquette : la boîte de déconnexion est
	// reprise dans une colonne commune.
	if ( $delete && false !== has_action( 'woocommerce_after_edit_account_form', 'famma_child_account_look_logout' ) ) {
		remove_action( 'woocommerce_after_edit_account_form', 'famma_child_account_look_logout' );
		add_action( 'woocommerce_after_edit_account_form', 'famma_child_account_privacy_side' );
	}

	if ( $help || $delete ) {
		add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_privacy', 29 );
	}
}
add_action( 'template_redirect', 'famma_child_account_privacy_setup' );

/**
 * Classe du corps : la feuille de style y range le lien de WooCommerce.
 *
 * @param array<int, string> $classes Classes du corps.
 * @return array<int, string>
 */
function famma_child_account_privacy_body_class( array $classes ): array {
	$classes[] = 'famma-nl-acct-forgot';

	return $classes;
}

/**
 * « Mot de passe oublié ? » qui se déplie, à la fin du formulaire de
 * connexion (maquette : .dbtn, .disc, .help, .btn-line).
 *
 * @return void
 */
function famma_child_account_privacy_forgot(): void {
	$wa   = \Famma\Core\Account_Privacy::whatsapp_link();
	$mail = function_exists( 'wc_lostpassword_url' ) ? wc_lostpassword_url() : wp_lostpassword_url();
	$num  = famma_child_account_privacy_text( 'fg_num' );

	printf(
		'<div class="famma-acct-forgot"><button class="famma-acct-dbtn" type="button" aria-expanded="true" aria-controls="famma-acct-fg" hidden>%1$s<span>%2$s</span></button><div class="famma-acct-disc is-open" id="famma-acct-fg"><div><div class="famma-acct-disc__in">',
		wp_kses( famma_child_icon( 'chevron' ), famma_child_svg_allowed_html() ),
		esc_html( famma_child_account_privacy_text( 'fg_b' ) )
	);

	if ( '' !== $wa ) {
		printf(
			'<p class="famma-acct-help">%1$s</p><a class="famma-acct-bline" href="%2$s" target="_blank" rel="noopener" data-famma-fg-wa data-num="%3$s">%4$s</a>',
			esc_html( famma_child_account_privacy_text( 'fg_p' ) ),
			esc_url( $wa ),
			esc_attr( $num ),
			esc_html( famma_child_account_privacy_text( 'fg_go' ) )
		);
	}

	printf(
		'<a class="famma-acct-fg-mail" href="%1$s">%2$s</a></div></div></div></div>',
		esc_url( $mail ),
		esc_html( famma_child_account_privacy_text( 'fg_mail' ) )
	);
}

/**
 * Colonne « Connexion » puis « Supprimer mon compte ».
 *
 * @return void
 */
function famma_child_account_privacy_side(): void {
	echo '<div class="famma-acct-side">';
	famma_child_account_look_logout();
	famma_child_account_privacy_box();
	echo '</div>';
}

/**
 * Champs cachés d'une action (jeton compris).
 *
 * @param string $action `request` ou `cancel`.
 * @return void
 */
function famma_child_account_privacy_fields( string $action ): void {
	printf( '<input type="hidden" name="famma_del_do" value="%s">', esc_attr( $action ) );
	wp_nonce_field( \Famma\Core\Account_Privacy::NONCE, '_famma_del_nonce' );
}

/**
 * Boîte « Supprimer mon compte » (maquette : .box.danger, .confirm), ou la
 * demande en cours avec « Annuler ma demande ».
 *
 * @return void
 */
function famma_child_account_privacy_box(): void {
	$pending = \Famma\Core\Account_Privacy::pending( get_current_user_id() );
	$title   = famma_child_account_privacy_text( 'del_t' );

	echo '<div class="famma-acct-box famma-acct-del" data-famma-del>';
	echo '<h3 class="famma-acct-box__h">' . esc_html( $title ) . '</h3>';

	if ( null !== $pending ) {
		$date = date_i18n( (string) get_option( 'date_format' ), (int) strtotime( $pending['date'] ) );

		echo '<p class="famma-acct-del__p">' . esc_html( sprintf( famma_child_account_privacy_text( 'del_sent' ), $date ) ) . '</p>';
		echo '<form method="post" class="famma-acct-del__f">';
		famma_child_account_privacy_fields( 'cancel' );
		echo '<button class="famma-acct-bline" type="submit">' . esc_html( famma_child_account_privacy_text( 'del_cancel' ) ) . '</button></form></div>';

		return;
	}

	echo '<p class="famma-acct-del__p">' . esc_html( famma_child_account_privacy_text( 'del_s' ) ) . '</p>';
	echo '<button class="famma-acct-bdanger" type="button" data-famma-del-open hidden>' . esc_html( $title ) . '</button>';
	echo '<form method="post" class="famma-acct-confirm" data-famma-del-confirm>';
	famma_child_account_privacy_fields( 'request' );
	printf(
		'<p><b>%1$s</b></p><div class="famma-acct-confirm__acts"><button class="famma-acct-bdanger is-sm" type="submit">%2$s</button><button class="famma-acct-bline is-sm" type="button" data-famma-del-keep hidden>%3$s</button></div></form></div>',
		esc_html( famma_child_account_privacy_text( 'del_q' ) ),
		esc_html( famma_child_account_privacy_text( 'del_y' ) ),
		esc_html( famma_child_account_privacy_text( 'del_n' ) )
	);
}

/**
 * Feuille de style et script du lot 8.
 *
 * @return void
 */
function famma_child_enqueue_account_privacy(): void {
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = $dir . '/assets/css/new-look-account-privacy.css';
	$js  = $dir . '/assets/js/new-look-account-privacy.js';

	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'famma-new-look-account-privacy', $uri . '/assets/css/new-look-account-privacy.css', array( 'famma-new-look-account' ), (string) filemtime( $css ) );
	}

	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'famma-new-look-account-privacy', $uri . '/assets/js/new-look-account-privacy.js', array(), (string) filemtime( $js ), true );
	}
}
