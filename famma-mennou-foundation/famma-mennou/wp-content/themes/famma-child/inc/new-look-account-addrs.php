<?php
/**
 * Nouveau look — « Mes adresses » à plusieurs adresses, comme la maquette.
 *
 * Case `acct_addrs` (lot 7 bis), avec `acct_look` et `acct_forms` : une
 * carte par adresse (étiquette, « Par défaut », Modifier, Utiliser par
 * défaut, Supprimer), une carte « Ajouter une adresse », la feuille de la
 * maquette (étiquette, gouvernorat, « Ville, cité, rue », case « par
 * défaut ») et le message « Adresse supprimée · Annuler ». Le téléphone
 * passe dans « Mes informations ». Les données et les actions sont dans
 * famma-core (`Famma\Core\Account_Addresses`). Sans script, tout marche par
 * formulaires et liens ; la feuille s'ouvre alors dans la page.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le carnet d'adresses est-il montré ?
 *
 * @return bool
 */
function famma_child_account_addrs(): bool {
	return famma_child_account_forms()
		&& class_exists( '\Famma\Core\Account_Addresses' )
		&& \Famma\Core\Account_Addresses::active();
}

/**
 * Texte des adresses (FAMMA → Pages → « Mon compte »).
 *
 * @param string $key Clé.
 * @return string
 */
function famma_child_account_addrs_text( string $key ): string {
	return \Famma\Core\Account_Addresses_Texts::text( $key );
}

/**
 * Pose les crochets (après ceux de l'étape C).
 *
 * @return void
 */
function famma_child_account_addrs_setup(): void {
	if ( ! famma_child_account_addrs() ) {
		return;
	}

	add_action( 'woocommerce_edit_account_form_fields', 'famma_child_account_addrs_tel_field' );

	if ( is_wc_endpoint_url( 'edit-address' ) || is_wc_endpoint_url( 'edit-account' ) ) {
		add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_addrs', 29 );
	}
}
add_action( 'template_redirect', 'famma_child_account_addrs_setup', 11 );

/**
 * Feuille de style et script du carnet.
 *
 * @return void
 */
function famma_child_enqueue_account_addrs(): void {
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = $dir . '/assets/css/new-look-account-addrs.css';
	$js  = $dir . '/assets/js/new-look-account-addrs.js';

	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'famma-new-look-account-addrs', $uri . '/assets/css/new-look-account-addrs.css', array( 'famma-new-look-account-forms' ), (string) filemtime( $css ) );
	}

	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'famma-new-look-account-addrs', $uri . '/assets/js/new-look-account-addrs.js', array(), (string) filemtime( $js ), true );
		wp_add_inline_script( 'famma-new-look-account-addrs', 'window.fammaAddrs=' . wp_json_encode( array( 'gov' => famma_child_account_addrs_text( 'ad_err_gov' ) ) ) . ';', 'before' );
	}
}

/**
 * Numéro groupé « XX XXX XXX ».
 *
 * @param string $phone Numéro enregistré.
 * @return string
 */
function famma_child_account_addrs_group( string $phone ): string {
	$d = class_exists( '\Famma\Core\Tunisia' ) ? \Famma\Core\Tunisia::normalize_phone( $phone ) : '';

	return '' !== $d ? substr( $d, 0, 2 ) . ' ' . substr( $d, 2, 3 ) . ' ' . substr( $d, 5 ) : $phone;
}

/**
 * « Mes informations » : le téléphone, comme la maquette.
 *
 * @return void
 */
function famma_child_account_addrs_tel_field(): void {
	$customer = new WC_Customer( get_current_user_id() );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- réaffichage après une erreur ; WooCommerce vérifie son jeton.
	$value = isset( $_POST['famma_id_tel'] ) ? sanitize_text_field( wp_unslash( $_POST['famma_id_tel'] ) ) : famma_child_account_addrs_group( (string) $customer->get_billing_phone() );

	printf(
		'<p class="woocommerce-form-row form-row form-row-wide famma-acct-f"><label for="famma_id_tel">%1$s</label><span class="famma-acct-inp"><span class="famma-acct-pre" dir="ltr">+216</span><input type="tel" class="woocommerce-Input input-text" name="famma_id_tel" id="famma_id_tel" inputmode="numeric" autocomplete="tel-national" placeholder="XX XXX XXX" value="%2$s"></span></p>',
		esc_html( famma_child_account_addrs_text( 'id_tel' ) ),
		esc_attr( $value )
	);
}

/**
 * Petit formulaire d'action (par défaut, supprimer, annuler).
 *
 * @param string $action Action.
 * @param string $id    Adresse.
 * @param string $label Texte du bouton.
 * @param string $extra Attributs du bouton.
 * @return string
 */
function famma_child_account_addrs_action( string $action, string $id, string $label, string $extra = '' ): string {
	return sprintf(
		'<form method="post" action="%1$s"><input type="hidden" name="famma_addr_do" value="%2$s"><input type="hidden" name="famma_addr_id" value="%3$s"><input type="hidden" name="_famma_addr_nonce" value="%4$s"><button type="submit" %5$s>%6$s</button></form>',
		esc_url( wc_get_endpoint_url( 'edit-address' ) ),
		esc_attr( $action ),
		esc_attr( $id ),
		esc_attr( wp_create_nonce( \Famma\Core\Account_Addresses::NONCE ) ),
		$extra,
		esc_html( $label )
	);
}

/**
 * « Mes adresses » : cartes, feuille et message.
 *
 * @return void
 */
function famma_child_account_addrs_panel(): void {
	$uid    = get_current_user_id();
	$all    = \Famma\Core\Account_Addresses::all( $uid );
	$c      = new WC_Customer( $uid );
	$states = WC()->countries ? WC()->countries->get_states( 'TN' ) : array();
	$states = is_array( $states ) ? $states : array();
	$name   = trim( $c->get_billing_first_name() . ' ' . $c->get_billing_last_name() );
	$phone  = class_exists( '\Famma\Core\Account_Profile' ) ? \Famma\Core\Account_Profile::format_phone( (string) $c->get_billing_phone() ) : (string) $c->get_billing_phone();
	$base   = wc_get_endpoint_url( 'edit-address' );
	$title  = class_exists( '\Famma\Core\Account_Profile' ) ? \Famma\Core\Account_Profile::text( 'tab_addresses' ) : '';
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- paramètres d'affichage après une action déjà vérifiée.
	$done    = isset( $_GET['famma_ad'] ) ? sanitize_key( wp_unslash( $_GET['famma_ad'] ) ) : '';
	$done_id = isset( $_GET['famma_ad_id'] ) ? sanitize_key( wp_unslash( $_GET['famma_ad_id'] ) ) : '';
	$edit    = isset( $_GET['famma_ad_edit'] ) ? sanitize_key( wp_unslash( $_GET['famma_ad_edit'] ) ) : null;
	// phpcs:enable

	if ( '' !== $title ) {
		echo '<h2 class="famma-acct-p-h">' . esc_html( $title ) . '</h2>';
	}

	$empty = famma_child_account_forms_text( 'addr_empty' );

	if ( array() === $all && '' !== $empty ) {
		echo '<p class="famma-acct-empty">' . esc_html( $empty ) . '</p>';
	}

	echo '<div class="famma-acct-ads">';

	foreach ( $all as $i => $ad ) {
		famma_child_account_addrs_card( $ad, (int) $i, $name, $phone, $states, $done_id === $ad['id'] );
	}

	if ( count( $all ) < \Famma\Core\Account_Addresses::MAX ) {
		printf(
			'<a class="famma-acct-add" href="%1$s" data-famma-sheet data-famma-ad-edit=""><i aria-hidden="true">+</i>%2$s</a>',
			esc_url( add_query_arg( 'famma_ad_edit', 'new', $base ) ),
			esc_html( famma_child_account_forms_text( 'addr_add' ) )
		);
	}

	echo '</div>';

	$current = null;

	foreach ( $all as $ad ) {
		if ( $ad['id'] === $edit ) {
			$current = $ad;
		}
	}

	famma_child_account_addrs_sheet( $current, $states, array() === $all, null !== $edit );

	if ( in_array( $done, array( 'saved', 'def', 'gone' ), true ) ) {
		$msg = famma_child_account_addrs_text( 'gone' === $done ? 'ad_gone' : 'ad_saved' );
		echo '<div class="famma-acct-adtoast" role="status"><span>' . esc_html( $msg ) . '</span>';

		if ( 'gone' === $done ) {
			echo famma_child_account_addrs_action( 'restore', '', famma_child_account_addrs_text( 'ad_undo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans la fonction.
		}

		echo '</div>';
	}
}

/**
 * Une carte d'adresse.
 *
 * @param array<string, mixed>  $ad     Adresse.
 * @param int                   $i      Rang (animation).
 * @param string                $name   Nom du client.
 * @param string                $phone  Téléphone affiché.
 * @param array<string, string> $states Gouvernorats.
 * @param bool                  $glow   Vient d'être enregistrée.
 * @return void
 */
function famma_child_account_addrs_card( array $ad, int $i, string $name, string $phone, array $states, bool $glow ): void {
	$svg   = famma_child_svg_allowed_html();
	$def   = ! empty( $ad['def'] );
	$id    = (string) $ad['id'];
	$place = array_filter( array( $states[ $ad['state'] ] ?? (string) $ad['state'], (string) $ad['addr'] ) );
	$chip  = famma_child_account_forms_text( 'addr_default' );

	printf(
		'<article class="famma-acct-ad%1$s%2$s" style="--i:%3$d" data-famma-ad="%4$s" data-lbl="%5$s" data-state="%6$s" data-addr="%7$s" data-def="%8$s">',
		$def ? ' is-def' : '',
		$glow ? ' is-saved' : '',
		(int) $i,
		esc_attr( $id ),
		esc_attr( (string) $ad['lbl'] ),
		esc_attr( (string) $ad['state'] ),
		esc_attr( (string) $ad['addr'] ),
		$def ? '1' : '0'
	);

	printf(
		'<div class="famma-acct-ad__t"><b>%1$s%2$s</b>%3$s</div>',
		wp_kses( famma_child_icon( 'work' === $ad['lbl'] ? 'box' : 'home' ), $svg ),
		esc_html( \Famma\Core\Account_Addresses::label( (string) $ad['lbl'] ) ),
		$def && '' !== $chip ? '<span class="famma-acct-chip">' . esc_html( $chip ) . '</span>' : ''
	);

	echo '<p>';
	if ( '' !== $name ) {
		echo '<strong>' . esc_html( $name ) . '</strong><br>';
	}
	if ( '' !== $phone ) {
		echo '<bdi class="famma-acct-ad__tel" dir="ltr">' . esc_html( $phone ) . '</bdi><br>';
	}
	echo '<span dir="auto">' . esc_html( implode( ', ', $place ) ) . '</span></p>';

	echo '<div class="famma-acct-ad__a">';
	printf(
		'<a href="%1$s" data-famma-sheet data-famma-ad-edit="%2$s">%3$s</a>',
		esc_url( add_query_arg( 'famma_ad_edit', $id, wc_get_endpoint_url( 'edit-address' ) ) ),
		esc_attr( $id ),
		esc_html( famma_child_account_forms_text( 'addr_edit' ) )
	);

	if ( ! $def ) {
		echo famma_child_account_addrs_action( 'default', $id, famma_child_account_addrs_text( 'ad_make_def' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans la fonction.
	}

	echo famma_child_account_addrs_action( 'delete', $id, famma_child_account_addrs_text( 'ad_del' ), 'class="is-del" data-famma-ad-del' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans la fonction.
	echo '</div></article>';
}
