<?php
/**
 * Profil de données Meta, côté serveur (lot 5, R3, A14).
 *
 * Meta retire les paramètres personnalisés (Configuration principale, E2) et
 * peut restreindre un vocabulaire jugé sensible (E3). Le profil choisi dans
 * « Mesure » décide de ce que `custom_data` transporte :
 *
 * - `standard` (défaut) : tout sauf `famma_stage`, `famma_product` et
 *   `content_name` ;
 * - `minimal` (urgence) : seulement `currency` et `value`, les champs dont
 *   les campagnes ont besoin.
 *
 * L'ancien comportement (tout envoyer) ne revient que par le filtre de code
 * `famma_meta_data_profile_legacy` ; le filtre `famma_capi_custom_data`
 * reçoit le résultat final. Le profil absorbe un champ retiré par Meta,
 * jamais un champ devenu obligatoire.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Filtrage de `custom_data` selon le profil de données.
 */
final class Capi_Data_Profile {

	/**
	 * Champs retirés en profil standard.
	 */
	public const STANDARD_EXCLUDED = array( 'famma_stage', 'famma_product', 'content_name' );

	/**
	 * Seuls champs conservés en profil minimal.
	 */
	public const MINIMAL_FIELDS = array( 'currency', 'value' );

	/**
	 * Applique le profil actif à un bloc `custom_data`.
	 *
	 * @param array<string, mixed> $custom_data Bloc complet.
	 * @param string               $event_name  Événement concerné.
	 * @param \WC_Order|null       $order       Commande, pour les filtres.
	 * @return array<string, mixed>
	 */
	public static function apply( array $custom_data, string $event_name, ?\WC_Order $order = null ): array {
		$profile  = Config::instance()->meta_data_profile();
		$filtered = self::for_profile( $custom_data, $profile );
		if ( true === apply_filters( 'famma_meta_data_profile_legacy', false, $event_name, $order ) ) {
			$filtered = $custom_data;
		}
		$filtered = apply_filters( 'famma_capi_custom_data', $filtered, $profile, $event_name, $order );

		return is_array( $filtered ) ? $filtered : self::for_profile( $custom_data, $profile );
	}

	/**
	 * Filtre un bloc pour un profil donné, sans filtre de code.
	 *
	 * @param array<string, mixed> $custom_data Bloc complet.
	 * @param string               $profile     `standard` ou `minimal`.
	 * @return array<string, mixed>
	 */
	public static function for_profile( array $custom_data, string $profile ): array {
		if ( 'minimal' === $profile ) {
			return array_intersect_key( $custom_data, array_flip( self::MINIMAL_FIELDS ) );
		}
		return array_diff_key( $custom_data, array_flip( self::STANDARD_EXCLUDED ) );
	}
}
