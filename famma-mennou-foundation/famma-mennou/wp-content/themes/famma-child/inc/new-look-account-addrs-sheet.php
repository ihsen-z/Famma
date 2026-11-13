<?php
/**
 * Nouveau look — feuille « Nouvelle adresse » / « Modifier l'adresse » de la
 * maquette (case `acct_addrs`, lot 7 bis) : étiquette, gouvernorat, « Ville,
 * cité, rue » et « Utiliser par défaut pour mes commandes ». Le formulaire
 * est envoyé à famma-core (`Famma\Core\Account_Addresses::handle()`).
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La feuille de la maquette (nouvelle adresse ou modification).
 *
 * @param array<string, mixed>|null $ad     Adresse modifiée, ou null.
 * @param array<string, string>     $states Gouvernorats.
 * @param bool                      $first  Première adresse du client.
 * @param bool                      $open   Ouverte sans script (lien suivi).
 * @return void
 */
function famma_child_account_addrs_sheet( ?array $ad, array $states, bool $first, bool $open ): void {
	$svg    = famma_child_svg_allowed_html();
	$lbl    = $ad ? (string) $ad['lbl'] : 'home';
	$state  = $ad ? (string) $ad['state'] : '';
	$help   = famma_child_account_forms_text( 'sheet_street_help' );
	$choose = class_exists( '\Famma\Core\Account_Phone_Texts' ) ? \Famma\Core\Account_Phone_Texts::text( 'gov_choose' ) : '';

	printf(
		'<dialog class="famma-acct-sheet famma-acct-adsheet" id="famma-acct-sheet" aria-labelledby="famma-acct-sheet-t"%1$s><div class="famma-acct-sheet__in"><div class="famma-acct-sheet__h"><h2 id="famma-acct-sheet-t" data-new="%2$s" data-edit="%3$s">%4$s</h2><button class="famma-acct-sheet__x" type="button" data-famma-sheet-close aria-label="%5$s">%6$s</button></div>',
		$open ? ' open' : '',
		esc_attr( famma_child_account_forms_text( 'sheet_new' ) ),
		esc_attr( famma_child_account_forms_text( 'sheet_edit' ) ),
		esc_html( famma_child_account_forms_text( $ad ? 'sheet_edit' : 'sheet_new' ) ),
		esc_attr( famma_child_account_forms_text( 'sheet_close' ) ),
		wp_kses( famma_child_icon( 'close' ), $svg )
	);

	printf(
		'<form method="post" action="%1$s" class="famma-acct-adform" novalidate><input type="hidden" name="famma_addr_do" value="save"><input type="hidden" name="famma_addr_id" value="%2$s"><input type="hidden" name="_famma_addr_nonce" value="%3$s">',
		esc_url( wc_get_endpoint_url( 'edit-address' ) ),
		esc_attr( $ad ? (string) $ad['id'] : '' ),
		esc_attr( wp_create_nonce( \Famma\Core\Account_Addresses::NONCE ) )
	);

	echo '<fieldset class="famma-acct-lbls"><legend>' . esc_html( famma_child_account_addrs_text( 'ad_legend' ) ) . '</legend>';

	foreach ( array_keys( \Famma\Core\Account_Addresses::LABELS ) as $key ) {
		printf(
			'<label class="famma-acct-lbo"><input type="radio" name="famma_addr_lbl" value="%1$s"%2$s><span>%3$s</span></label>',
			esc_attr( $key ),
			checked( $lbl, $key, false ),
			esc_html( \Famma\Core\Account_Addresses::label( $key ) )
		);
	}

	echo '</fieldset>';

	printf(
		'<p class="form-row form-row-wide famma-acct-f"><label for="famma_addr_state">%1$s</label><select name="famma_addr_state" id="famma_addr_state" autocomplete="address-level1" aria-describedby="famma_addr_state-e"><option value="">%2$s</option>',
		esc_html( famma_child_account_forms_text( 'sheet_gov' ) ),
		esc_html( $choose )
	);

	foreach ( $states as $code => $label ) {
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $code ), selected( $state, (string) $code, false ), esc_html( (string) $label ) );
	}

	echo '</select><span class="famma-acct-err" id="famma_addr_state-e" hidden></span></p>';

	printf(
		'<p class="form-row form-row-wide famma-acct-f"><label for="famma_addr_line">%1$s</label><input type="text" class="input-text" name="famma_addr_line" id="famma_addr_line" autocomplete="street-address" maxlength="200" value="%2$s"%3$s>%4$s</p>',
		esc_html( famma_child_account_forms_text( 'sheet_street' ) ),
		esc_attr( $ad ? (string) $ad['addr'] : '' ),
		'' !== $help ? ' aria-describedby="famma_addr_line-h"' : '',
		'' !== $help ? '<span class="famma-acct-help" id="famma_addr_line-h">' . esc_html( $help ) . '</span>' : ''
	);

	printf(
		'<p class="form-row famma-acct-defrow"><label class="woocommerce-form__label-for-checkbox"><input type="checkbox" name="famma_addr_def" value="1"%1$s><span>%2$s</span></label></p>',
		checked( $ad ? ! empty( $ad['def'] ) : $first, true, false ),
		esc_html( famma_child_account_addrs_text( 'ad_def_check' ) )
	);

	printf( '<button type="submit" class="button famma-acct-adsave">%s</button></form></div></dialog>', esc_html( famma_child_account_addrs_text( 'ad_save' ) ) );
}
