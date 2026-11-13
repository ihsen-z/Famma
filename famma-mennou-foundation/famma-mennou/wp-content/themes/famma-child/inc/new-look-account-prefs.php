<?php
/**
 * Nouveau look — boîte « Préférences » de « Mes informations » (lot 6 de
 * l'espace client, case `acct_prefs` de famma-core).
 *
 * Présentation seulement : la logique (droits, enregistrement, consentement)
 * vit dans `Famma\Core\Account_Prefs`. La boîte ouvre la colonne de droite,
 * avant « Connexion » et « Supprimer mon compte », comme la maquette
 * (#prefBox, .row-sw, .lseg, .sw).
 *
 * Sans script, chaque choix est un formulaire qui recharge la page ; avec
 * script, la pastille et l'interrupteur glissent d'abord.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Texte de la boîte, réglable dans FAMMA → Pages → « Mon compte ».
 *
 * @param string $key Clé du champ.
 * @return string
 */
function famma_child_account_prefs_text( string $key ): string {
	return \Famma\Core\Account_Prefs_Texts::text( $key );
}

/**
 * Boîte montrée sur cette page ?
 *
 * @return bool
 */
function famma_child_account_prefs(): bool {
	return class_exists( '\Famma\Core\Account_Prefs' )
		&& \Famma\Core\Account_Prefs::active()
		&& is_wc_endpoint_url( 'edit-account' );
}

/**
 * Pose les crochets.
 *
 * @return void
 */
function famma_child_account_prefs_setup(): void {
	if ( famma_child_account_prefs() ) {
		add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_prefs', 30 );
	}
}
add_action( 'template_redirect', 'famma_child_account_prefs_setup' );

/**
 * Adresse de cette page dans chaque langue (TranslatePress), ou vide.
 *
 * @return array{fr: string, ar: string}
 */
function famma_child_account_prefs_urls(): array {
	$urls = array(
		'fr' => '',
		'ar' => '',
	);

	if ( ! function_exists( 'trp_custom_language_switcher' ) ) {
		return $urls;
	}

	$languages = trp_custom_language_switcher();

	foreach ( is_array( $languages ) ? $languages : array() as $code => $language ) {
		if ( ! is_array( $language ) || empty( $language['current_page_url'] ) ) {
			continue;
		}

		$code         = isset( $language['language_code'] ) ? (string) $language['language_code'] : (string) $code;
		$key          = str_starts_with( $code, 'ar' ) ? 'ar' : 'fr';
		$urls[ $key ] = (string) $language['current_page_url'];
	}

	return $urls;
}

/**
 * Champs cachés d'un choix (jeton compris).
 *
 * @param string $action `lang` ou `wa`.
 * @return void
 */
function famma_child_account_prefs_fields( string $action ): void {
	printf(
		'<input type="hidden" name="famma_prefs_do" value="%1$s"><input type="hidden" name="_famma_prefs_nonce" value="%2$s">',
		esc_attr( $action ),
		esc_attr( wp_create_nonce( \Famma\Core\Account_Prefs::NONCE ) )
	);
}

/**
 * Boîte « Préférences » : langue des messages, puis nouveautés WhatsApp
 * quand le texte de consentement est écrit.
 *
 * @return void
 */
function famma_child_account_prefs_box(): void {
	$user_id = get_current_user_id();
	$lang    = \Famma\Core\Account_Prefs::lang( $user_id );
	$lang    = '' !== $lang ? $lang : ( is_rtl() ? 'ar' : 'fr' );
	$title   = famma_child_account_prefs_text( 'pf_lang' );

	echo '<div class="famma-acct-box famma-acct-prefs" data-famma-prefs>';

	echo '<form method="post" class="famma-acct-row" data-famma-prefs-lang>';
	famma_child_account_prefs_fields( 'lang' );

	foreach ( famma_child_account_prefs_urls() as $code => $url ) {
		if ( '' !== $url ) {
			printf( '<input type="hidden" name="_famma_back_%1$s" value="%2$s">', esc_attr( $code ), esc_url( $url ) );
		}
	}

	printf(
		'<span class="famma-acct-row__t"><b>%1$s</b><small>%2$s</small></span><span class="famma-acct-seg is-inline%3$s" role="group" aria-label="%4$s" data-no-translation="yes"><span class="famma-acct-seg__pill" aria-hidden="true"></span><button class="famma-acct-seg__tab" type="submit" name="famma_prefs_value" value="fr" lang="fr" aria-pressed="%5$s">FR</button><button class="famma-acct-seg__tab" type="submit" name="famma_prefs_value" value="ar" lang="ar" aria-pressed="%6$s">عربي</button></span></form>',
		esc_html( $title ),
		esc_html( famma_child_account_prefs_text( 'pf_lang_s' ) ),
		'ar' === $lang ? ' is-second' : '',
		esc_attr( $title ),
		'fr' === $lang ? 'true' : 'false',
		'ar' === $lang ? 'true' : 'false'
	);

	if ( \Famma\Core\Account_Prefs::wa_active() ) {
		$on = null !== \Famma\Core\Account_Prefs::wa( $user_id );

		echo '<form method="post" class="famma-acct-prefs__f" data-famma-prefs-wa>';
		famma_child_account_prefs_fields( 'wa' );
		printf(
			'<input type="hidden" name="famma_prefs_value" value="%1$s"><button class="famma-acct-row" type="submit" role="switch" aria-checked="%2$s" data-err="%5$s"><span class="famma-acct-row__t"><b>%3$s</b><small>%4$s</small></span><span class="famma-acct-sw" aria-hidden="true"></span></button></form>',
			$on ? '0' : '1',
			$on ? 'true' : 'false',
			esc_html( famma_child_account_prefs_text( 'pf_wa' ) ),
			esc_html( famma_child_account_prefs_text( 'pf_wa_s' ) ),
			esc_attr( famma_child_account_prefs_text( 'pf_err' ) )
		);
	}

	echo '</div>';
}

/**
 * Feuille de style et script du lot 6.
 *
 * @return void
 */
function famma_child_enqueue_account_prefs(): void {
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = $dir . '/assets/css/new-look-account-prefs.css';
	$js  = $dir . '/assets/js/new-look-account-prefs.js';

	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'famma-new-look-account-prefs', $uri . '/assets/css/new-look-account-prefs.css', array( 'famma-new-look-account' ), (string) filemtime( $css ) );
	}

	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'famma-new-look-account-prefs', $uri . '/assets/js/new-look-account-prefs.js', array(), (string) filemtime( $js ), true );
	}
}
