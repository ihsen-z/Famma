<?php
/**
 * Nouveau look — « Mes adresses » et « Mes informations » comme la maquette.
 *
 * Case `acct_forms` (étape C de l'alignement sur la maquette espace client),
 * avec `acct_look`. Les textes viennent de famma-core
 * (`Famma\Core\Account_Panels`) ; ce fichier ne fait que dessiner :
 *
 * - « Mes adresses » : l'adresse de facturation du client dans une carte (ou
 *   une carte « Ajouter une adresse ») ; « Modifier » ouvre une feuille qui
 *   contient le formulaire d'adresse de WooCommerce, envoyé à WooCommerce
 *   comme sur sa propre page. Sans script, le lien mène à cette page.
 * - « Mes informations » : titre et boîte « Identité » autour du formulaire
 *   de WooCommerce ; le script range le changement de mot de passe dans la
 *   boîte « Connexion », au-dessus de « Me déconnecter ».
 *
 * Aucun modèle WooCommerce n'est copié. Case décochée ou plugin absent :
 * aucun crochet n'est posé.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La page courante reçoit-elle les panneaux de la maquette ?
 *
 * @return bool
 */
function famma_child_account_forms(): bool {
	return famma_child_account_look()
		&& is_user_logged_in()
		&& famma_child_new_look( 'acct_forms' )
		&& class_exists( '\Famma\Core\Account_Panels' );
}

/**
 * Pose les crochets, seulement quand la case est cochée.
 *
 * @return void
 */
function famma_child_account_forms_setup(): void {
	if ( ! famma_child_account_forms() ) {
		return;
	}

	remove_action( 'woocommerce_account_edit-address_endpoint', 'woocommerce_account_edit_address' );
	add_action( 'woocommerce_account_edit-address_endpoint', 'famma_child_account_forms_addresses' );
	add_action( 'woocommerce_before_edit_account_form', 'famma_child_account_forms_infos_title' );
	add_action( 'woocommerce_edit_account_form_start', 'famma_child_account_forms_identity' );
	add_filter( 'body_class', 'famma_child_account_forms_body_class' );
	add_filter( 'gettext_woocommerce', 'famma_child_account_forms_strings', 10, 2 );
	add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_forms', 27 );
}
add_action( 'template_redirect', 'famma_child_account_forms_setup' );

/**
 * Classe du corps pour la feuille de style des panneaux.
 *
 * @param array<int, string> $classes Classes du corps.
 * @return array<int, string>
 */
function famma_child_account_forms_body_class( array $classes ): array {
	$classes[] = 'famma-nl-acct-forms';

	if ( is_wc_endpoint_url( 'edit-account' ) ) {
		$classes[] = 'famma-nl-acct-infos';
	}

	return $classes;
}

/**
 * « Enregistrer », comme la maquette, sur les deux boutons de WooCommerce
 * (adresse et informations). Seuls ces deux textes changent, et seulement ici.
 *
 * @param string $translation Texte traduit.
 * @param string $text        Texte d'origine (anglais).
 * @return string
 */
function famma_child_account_forms_strings( $translation, $text ) {
	if ( 'Save address' !== $text && 'Save changes' !== $text ) {
		return $translation;
	}

	$ours = famma_child_account_forms_text( 'infos_save' );

	return '' !== $ours ? $ours : $translation;
}

/**
 * Feuille et script des panneaux.
 *
 * @return void
 */
function famma_child_enqueue_account_forms(): void {
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = $dir . '/assets/css/new-look-account-forms.css';
	$js  = $dir . '/assets/js/new-look-account-forms.js';

	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'famma-new-look-account-forms', $uri . '/assets/css/new-look-account-forms.css', array( 'famma-new-look-account' ), (string) filemtime( $css ) );
	}

	if ( file_exists( $js ) ) {
		wp_enqueue_script(
			'famma-new-look-account-forms',
			$uri . '/assets/js/new-look-account-forms.js',
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
 * Un texte des panneaux.
 *
 * @param string $key Clé du champ.
 * @return string
 */
function famma_child_account_forms_text( string $key ): string {
	return \Famma\Core\Account_Panels::text( $key );
}

/**
 * « Mes adresses » : la carte d'adresse et la feuille qui la modifie.
 *
 * Une page d'adresse précise (`edit-address/billing/`, `…/shipping/`) reste
 * celle de WooCommerce : c'est la page des visiteurs sans script, et celle
 * qui montre les erreurs si un champ est refusé.
 *
 * @param string $type Type d'adresse demandé dans l'adresse de la page.
 * @return void
 */
function famma_child_account_forms_addresses( $type ): void {
	$type = sanitize_title( (string) $type );

	if ( '' !== $type ) {
		woocommerce_account_edit_address( $type );
		return;
	}

	if ( function_exists( 'famma_child_account_addrs' ) && famma_child_account_addrs() ) {
		famma_child_account_addrs_panel();
		return;
	}

	$customer = new WC_Customer( get_current_user_id() );
	$state    = (string) $customer->get_billing_state();
	$has      = '' !== trim( (string) $customer->get_billing_address_1() ) || '' !== trim( $state );
	$url      = wc_get_endpoint_url( 'edit-address', 'billing' );
	$svg      = famma_child_svg_allowed_html();
	$title    = class_exists( '\Famma\Core\Account_Profile' ) ? \Famma\Core\Account_Profile::text( 'tab_addresses' ) : '';

	if ( '' !== $title ) {
		echo '<h2 class="famma-acct-p-h">' . esc_html( $title ) . '</h2>';
	}

	$empty = famma_child_account_forms_text( 'addr_empty' );

	if ( ! $has && '' !== $empty ) {
		echo '<p class="famma-acct-empty">' . esc_html( $empty ) . '</p>';
	}

	echo '<div class="famma-acct-ads">';

	if ( $has ) {
		$states = WC()->countries ? WC()->countries->get_states( (string) $customer->get_billing_country() ) : array();
		$place  = array_filter(
			array(
				is_array( $states ) && isset( $states[ $state ] ) ? (string) $states[ $state ] : $state,
				trim( (string) $customer->get_billing_address_1() . ' ' . (string) $customer->get_billing_address_2() ),
				(string) $customer->get_billing_city(),
			)
		);
		$name   = trim( $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name() );
		$phone  = class_exists( '\Famma\Core\Account_Profile' ) ? \Famma\Core\Account_Profile::format_phone( (string) $customer->get_billing_phone() ) : (string) $customer->get_billing_phone();
		$def    = famma_child_account_forms_text( 'addr_default' );

		echo '<article class="famma-acct-ad is-def">';
		printf(
			'<div class="famma-acct-ad__t"><b>%1$s%2$s</b>%3$s</div>',
			wp_kses( famma_child_icon( 'home' ), $svg ),
			esc_html( famma_child_account_forms_text( 'addr_card' ) ),
			'' !== $def ? '<span class="famma-acct-chip">' . esc_html( $def ) . '</span>' : ''
		);

		echo '<p>';
		if ( '' !== $name ) {
			echo '<strong>' . esc_html( $name ) . '</strong><br>';
		}
		if ( '' !== $phone ) {
			echo '<bdi class="famma-acct-ad__tel" dir="ltr">' . esc_html( $phone ) . '</bdi><br>';
		}
		echo '<span dir="auto">' . esc_html( implode( ', ', $place ) ) . '</span></p>';

		printf(
			'<div class="famma-acct-ad__a"><a href="%1$s" data-famma-sheet>%2$s</a></div>',
			esc_url( $url ),
			esc_html( famma_child_account_forms_text( 'addr_edit' ) )
		);
		echo '</article>';
	} else {
		printf(
			'<a class="famma-acct-add" href="%1$s" data-famma-sheet><i aria-hidden="true">+</i>%2$s</a>',
			esc_url( $url ),
			esc_html( famma_child_account_forms_text( 'addr_add' ) )
		);
	}

	echo '</div>';

	// Le formulaire de WooCommerce, envoyé à sa propre adresse : WooCommerce
	// y lit le type d'adresse (facturation) avant d'enregistrer.
	ob_start();
	woocommerce_account_edit_address( 'billing' );
	$form = (string) ob_get_clean();
	$form = preg_replace( '/<form method="post"/', '<form method="post" action="' . esc_url( $url ) . '"', $form, 1 );

	printf(
		'<dialog class="famma-acct-sheet" id="famma-acct-sheet" aria-labelledby="famma-acct-sheet-t"><div class="famma-acct-sheet__in"><div class="famma-acct-sheet__h"><h2 id="famma-acct-sheet-t">%1$s</h2><button class="famma-acct-sheet__x" type="button" data-famma-sheet-close aria-label="%2$s">%3$s</button></div>',
		esc_html( famma_child_account_forms_text( $has ? 'sheet_edit' : 'sheet_new' ) ),
		esc_attr( famma_child_account_forms_text( 'sheet_close' ) ),
		wp_kses( famma_child_icon( 'close' ), $svg )
	);
	echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formulaire rendu et échappé par le modèle de WooCommerce.
	echo '</div></dialog>';
}

/**
 * « Mes informations » : titre du panneau, comme la maquette.
 *
 * @return void
 */
function famma_child_account_forms_infos_title(): void {
	$title = class_exists( '\Famma\Core\Account_Profile' ) ? \Famma\Core\Account_Profile::text( 'tab_infos' ) : '';

	if ( '' !== $title ) {
		echo '<h2 class="famma-acct-p-h">' . esc_html( $title ) . '</h2>';
	}
}

/**
 * Titre de la boîte « Identité », en tête du formulaire de WooCommerce.
 *
 * @return void
 */
function famma_child_account_forms_identity(): void {
	$title = famma_child_account_forms_text( 'infos_identity' );

	if ( '' !== $title ) {
		echo '<h3 class="famma-acct-box__h">' . esc_html( $title ) . '</h3>';
	}
}

/**
 * Haut de la boîte « Connexion » : titre et bouton qui déplie le changement
 * de mot de passe (le script y range le bloc de WooCommerce). Le contenant
 * est un <form> vide, pour reprendre le dessin des champs de WooCommerce ;
 * il n'envoie rien lui-même : ses champs appartiennent au formulaire
 * « Identité » (attribut form).
 *
 * @return void
 */
function famma_child_account_forms_connection(): void {
	if ( ! famma_child_account_forms() ) {
		return;
	}

	$title = famma_child_account_forms_text( 'infos_login' );

	if ( '' !== $title ) {
		echo '<h3 class="famma-acct-box__h">' . esc_html( $title ) . '</h3>';
	}

	printf(
		'<button class="famma-acct-dbtn" type="button" aria-expanded="false" aria-controls="famma-acct-pw" hidden>%1$s<span>%2$s</span></button><div class="famma-acct-disc" id="famma-acct-pw"><div><form class="famma-acct-disc__in" data-famma-pw data-save="%3$s"></form></div></div>',
		wp_kses( famma_child_icon( 'chevron' ), famma_child_svg_allowed_html() ),
		esc_html( famma_child_account_forms_text( 'infos_pw' ) ),
		esc_attr( famma_child_account_forms_text( 'infos_save' ) )
	);
}
