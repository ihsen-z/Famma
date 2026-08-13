<?php
/**
 * Configuration TranslatePress pour FAMMA (ADR-005, §5).
 *
 * Idempotent : relançable sans effet de bord. Appelé par setup.sh via
 * `wp eval-file`, et conservé au dépôt pour rester reproductible.
 *
 * Le corps est enfermé dans une closure : dans un script exécuté par
 * WP-CLI, toute variable de premier niveau est une variable globale.
 *
 * @package Famma\Local
 */

( static function (): void {

	$settings = get_option( 'trp_settings', array() );

	$settings['default-language']                     = 'fr_FR';
	$settings['translation-languages']                = array( 'fr_FR', 'ar' );
	$settings['publish-languages']                    = array( 'fr_FR', 'ar' );
	$settings['native_or_english_name']               = 'native_name';
	$settings['add-subdirectory-to-default-language'] = 'no';
	$settings['force-language-to-custom-links']       = 'yes';
	$settings['url-slugs']                            = array(
		'fr_FR' => 'fr',
		'ar'    => 'ar',
	);

	/*
	 * Le sélecteur flottant est placé en bas à GAUCHE : le bouton WhatsApp de
	 * famma-core occupe le bas à droite (inset-inline-end), et le CTA collant
	 * mobile occupe toute la largeur du bas. À déplacer dans l'en-tête en
	 * Phase 5, où le §9 l'attend.
	 */
	$settings['trp-ls-floater']        = 'yes';
	$settings['floater-position']      = 'bottom-left';
	$settings['floater-options']       = 'full-names';
	$settings['menu-options']          = 'full-names';
	$settings['shortcode-options']     = 'full-names';
	$settings['trp-ls-show-poweredby'] = 'no';

	update_option( 'trp_settings', $settings );

	/*
	 * Traduction automatique désactivée, et pas seulement « non configurée ».
	 * Le §5 interdit la traduction machine de la copie commerciale : la Derja
	 * est écrite par un humain.
	 */
	$mt                        = get_option( 'trp_machine_translation_settings', array() );
	$mt['machine-translation'] = 'no';
	$mt['translation-engine']  = '';
	$mt['block-crawlers']      = 'yes';
	update_option( 'trp_machine_translation_settings', $mt );

	/*
	 * Écrire l'option ne suffit pas : TranslatePress crée ses tables par langue
	 * depuis son écran de réglages. En le configurant par WP-CLI on
	 * court-circuite cette routine, et chaque page publique jette alors des
	 * erreurs SQL (« Table wp_trp_dictionary_fr_fr_ar doesn't exist ») tout en
	 * répondant 200. On appelle donc explicitement ses créateurs de tables.
	 */
	$trp   = TRP_Translate_Press::get_trp_instance();
	$query = $trp->get_component( 'query' );
	$query->check_original_table();
	$query->check_original_meta_table();
	foreach ( $settings['translation-languages'] as $language ) {
		$query->check_table( $settings['default-language'], $language );
	}
	$trp->get_component( 'upgrade' )->check_if_gettext_tables_exist();

	global $wpdb;
	$missing = array();
	foreach ( array( 'trp_dictionary_fr_fr_ar', 'trp_gettext_ar', 'trp_original_strings' ) as $suffix ) {
		$table = $wpdb->prefix . $suffix;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- existence de table, sans équivalent API.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			$missing[] = $table;
		}
	}

	echo "TranslatePress configure :\n";
	echo '  tables manquantes   : ' . ( empty( $missing ) ? 'aucune' : implode( ', ', $missing ) ) . "\n";
	echo '  langue par defaut   : ' . $settings['default-language'] . "\n";
	echo '  langues publiees    : ' . implode( ', ', $settings['publish-languages'] ) . "\n";
	echo '  traduction auto     : ' . $mt['machine-translation'] . " (doit etre 'no')\n";
	echo '  selecteur flottant  : ' . $settings['floater-position'] . "\n";
} )();
