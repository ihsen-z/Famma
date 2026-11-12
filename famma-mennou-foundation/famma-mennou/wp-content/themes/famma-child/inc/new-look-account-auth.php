<?php
/**
 * Nouveau look — page de connexion de « Mon compte » comme la maquette.
 *
 * Case `acct_look` (étape B de l'alignement sur la maquette espace client) :
 * carte marine « Votre espace FAMMA » (promesse, avantages, « Commander sans
 * compte ») à côté du formulaire, titres et boutons de la maquette. Les
 * textes viennent de famma-core (`Famma\Core\Account_Auth`, FAMMA → Pages →
 * « Mon compte ») ; les champs et l'envoi restent ceux de WooCommerce, et
 * aucun modèle WooCommerce n'est copié.
 *
 * Case décochée, ou plugin absent : chaque fonction sort aussitôt.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La page de connexion reçoit-elle la carte « Votre espace FAMMA » ?
 *
 * @return bool
 */
function famma_child_account_auth(): bool {
	return famma_child_account_look() && ! is_user_logged_in() && class_exists( '\Famma\Core\Account_Auth' );
}

/**
 * Titres et boutons de la maquette sur les formulaires de WooCommerce.
 *
 * Le titre « Créer mon compte » sert aussi au bouton, comme dans la maquette ;
 * « Content de vous revoir » titre la connexion, « Me connecter » son bouton.
 * Seuls ces trois textes de WooCommerce changent, et seulement ici.
 *
 * @return void
 */
function famma_child_account_auth_setup(): void {
	if ( famma_child_account_auth() && ! is_wc_endpoint_url() ) {
		add_filter( 'gettext_woocommerce', 'famma_child_account_auth_strings', 10, 2 );
	}
}
add_action( 'template_redirect', 'famma_child_account_auth_setup' );

/**
 * Remplace trois textes de WooCommerce par ceux réglés dans FAMMA → Pages.
 *
 * @param string $translation Texte traduit.
 * @param string $text        Texte d'origine (anglais).
 * @return string
 */
function famma_child_account_auth_strings( $translation, $text ) {
	$map = array(
		'Login'    => 'in_title',
		'Register' => 'up_title',
		'Log in'   => 'in_button',
	);

	if ( ! isset( $map[ $text ] ) ) {
		return $translation;
	}

	$ours = \Famma\Core\Account_Auth::text( $map[ $text ] );

	return '' !== $ours ? $ours : $translation;
}

/**
 * La mascotte FAMMA de la maquette (même dessin que le logo), qui cligne.
 *
 * @return string
 */
function famma_child_account_mascot(): string {
	$svg = '<svg class="famma-acct-mascot" viewBox="0 0 124 122" aria-hidden="true" focusable="false">'
		. '<path d="M34 28H74A20 20 0 0 1 94 48V76A20 20 0 0 1 74 96H60L50 114L42 96H34A20 20 0 0 1 14 76V48A20 20 0 0 1 34 28Z" fill="none" stroke="currentColor" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>'
		. '<g class="famma-acct-mascot__ant"><path d="M54 28V17" stroke="#FF7A00" stroke-width="7" stroke-linecap="round"/><circle cx="54" cy="10" r="8" fill="#FF7A00"/></g>'
		. '<rect class="famma-acct-mascot__eye" x="36" y="50" width="10" height="20" rx="5" fill="#FF7A00"/>'
		. '<path d="M74 52L64 61L74 70" fill="none" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>'
		. '<path d="M44 76Q54 87 64 76" fill="none" stroke="currentColor" stroke-width="7" stroke-linecap="round"/>'
		. '<g stroke="#FF7A00" stroke-width="6.5" stroke-linecap="round"><path d="M104 22L114 12"/><path d="M110 36L122 32"/><path d="M100 11L104 2"/></g>'
		. '</svg>';

	$allowed           = famma_child_svg_allowed_html();
	$allowed['g']      = array_merge( $allowed['g'] ?? array(), array( 'class' => true ) );
	$allowed['rect']   = array_merge( $allowed['rect'] ?? array(), array( 'class' => true ) );
	$allowed['circle'] = array_merge( $allowed['circle'] ?? array(), array( 'fill' => true ) );

	return wp_kses( $svg, $allowed );
}

/**
 * Carte marine « Votre espace FAMMA » à côté du formulaire, comme la maquette.
 *
 * Ouvre aussi l'enveloppe qui range la carte et le formulaire côte à côte
 * sur grand écran ; `famma_child_account_auth_close()` la referme.
 *
 * @return void
 */
function famma_child_account_auth_open(): void {
	if ( ! famma_child_account_auth() ) {
		return;
	}

	$a     = '\Famma\Core\Account_Auth';
	$title = $a::text( 'auth_title' );
	$brand = $a::text( 'auth_brand' );
	$lead  = $a::text( 'auth_lead' );
	$alt   = array( $a::text( 'alt_strong' ), $a::text( 'alt_text' ), $a::text( 'alt_link' ) );
	$svg   = famma_child_svg_allowed_html();

	echo '<div class="famma-acct-auth">';
	echo '<section class="famma-acct-promise" aria-labelledby="famma-acct-promise-t">';
	echo famma_child_account_mascot(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passé par wp_kses().

	printf(
		'<h1 id="famma-acct-promise-t">%1$s%2$s</h1>',
		esc_html( $title ),
		'' !== $brand ? ' <em>' . esc_html( $brand ) . '</em>' : ''
	);

	if ( '' !== $lead ) {
		echo '<p class="famma-acct-promise__lead">' . esc_html( $lead ) . '</p>';
	}

	$benefits = $a::benefits();

	if ( array() !== $benefits ) {
		echo '<ul class="famma-acct-ben">';

		foreach ( $benefits as $i => $ben ) {
			printf(
				'<li style="--i:%1$d"><i aria-hidden="true">%2$s</i><span><b>%3$s</b>%4$s</span></li>',
				(int) $i,
				wp_kses( famma_child_icon( $ben['icon'] ), $svg ),
				esc_html( $ben['title'] ),
				'' !== $ben['sub'] ? '<span>' . esc_html( $ben['sub'] ) . '</span>' : ''
			);
		}

		echo '</ul>';
	}

	if ( '' !== implode( '', $alt ) ) {
		echo '<p class="famma-acct-alt"><span>';

		if ( '' !== $alt[0] ) {
			echo '<b>' . esc_html( $alt[0] ) . '</b> ';
		}

		echo esc_html( $alt[1] ) . '</span>';

		if ( '' !== $alt[2] && function_exists( 'wc_get_page_permalink' ) ) {
			echo '<a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html( $alt[2] ) . '</a>';
		}

		echo '</p>';
	}

	echo '</section><div class="famma-acct-auth__card">';
}
add_action( 'woocommerce_before_customer_login_form', 'famma_child_account_auth_open', 5 );

/**
 * Referme l'enveloppe ouverte par `famma_child_account_auth_open()`.
 *
 * @return void
 */
function famma_child_account_auth_close(): void {
	if ( famma_child_account_auth() ) {
		echo '</div></div>';
	}
}
add_action( 'woocommerce_after_customer_login_form', 'famma_child_account_auth_close', 99 );

/**
 * Phrase sous « Créer mon compte » (vide par défaut : la maquette parle d'une
 * inscription par téléphone, pas encore en place).
 *
 * @return void
 */
function famma_child_account_auth_up_sub(): void {
	if ( ! famma_child_account_auth() ) {
		return;
	}

	$sub = \Famma\Core\Account_Auth::text( 'up_sub' );

	if ( '' !== $sub ) {
		echo '<p class="famma-acct-sub">' . esc_html( $sub ) . '</p>';
	}
}
add_action( 'woocommerce_register_form_start', 'famma_child_account_auth_up_sub', 1 );
