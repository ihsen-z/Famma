<?php
/**
 * Doublon serveur (lot 59) : custom_data et user_data d'un événement relayé.
 *
 * Valeur (M3) : le Pixel envoie la valeur du panier (lots, codes promo
 * compris). Le relais transmet cette même valeur, et le serveur ne la garde
 * que si elle est comprise entre 0 et le plafond tiré du catalogue
 * (Σ prix affiché WooCommerce × quantité) : un lot ou une remise ne font que
 * baisser la valeur, jamais la dépasser. Au-delà, ou sans nombre valide, le
 * plafond est envoyé et l'écart est compté (M4). Devise et identifiants
 * viennent toujours de WooCommerce (`Meta_Ids`), puis le profil de données
 * s'applique (`Capi_Data_Profile`, R3).
 *
 * user_data (I1) : rien du corps de la requête, sauf `ph`, accepté seulement
 * s'il est l'empreinte d'un numéro saisi par ce visiteur et connu du serveur
 * (table des saisies, `Visitor_Inputs`). Sinon `ph` est retiré : une
 * empreinte forgée n'est jamais transmise à Meta.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Données recalculées ou vérifiées par le serveur.
 */
final class Capi_Mirror_Data {

	/**
	 * Événements dont la valeur du Pixel est reprise (bornée).
	 */
	private const BROWSER_VALUE = array( 'AddToCart', 'InitiateCheckout' );

	/**
	 * Bloc custom_data recalculé depuis WooCommerce, profil de données appliqué.
	 *
	 * @param string            $name     Événement.
	 * @param array<int, int[]> $items    Articles validés.
	 * @param mixed             $browser  Valeur envoyée au Pixel (relais), ou null.
	 * @param bool              $adjusted Mis à vrai si la valeur reçue a été remplacée par le plafond.
	 * @return array<string, mixed>|null Vide pour PageView ; null si un article n'est pas publié.
	 */
	public static function custom_data( string $name, array $items, $browser = null, bool &$adjusted = false ): ?array {
		if ( 'PageView' === $name ) {
			return array();
		}
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 3;
		$contents = array();
		$cap      = 0.0;
		$quantity = 0;
		foreach ( $items as $item ) {
			$product = self::product( $item[0] );
			if ( null === $product ) {
				return null;
			}
			$cap       += (float) wc_get_price_to_display( $product ) * $item[1];
			$quantity  += $item[1];
			$contents[] = array(
				'id'       => Meta_Ids::content_id( $product ),
				'quantity' => $item[1],
			);
		}
		$cap   = round( $cap, $decimals );
		$value = $cap;
		if ( in_array( $name, self::BROWSER_VALUE, true ) && null !== $browser ) {
			$valid = ( is_int( $browser ) || is_float( $browser ) ) && $browser >= 0 && round( (float) $browser, $decimals ) <= $cap;
			$value = $valid ? round( (float) $browser, $decimals ) : $cap;
			if ( ! $valid ) {
				$adjusted = true;
			}
		}
		$custom = array(
			'currency' => get_woocommerce_currency(),
			'value'    => $value,
		);
		if ( 'ViewContent' === $name ) {
			$product = self::product( $items[0][0] );
			$custom += Meta_Ids::view_target( $product ) + array( 'content_name' => $product->get_name() );
		} else {
			$custom += array(
				'content_type' => 'product',
				'content_ids'  => array_values( array_unique( array_column( $contents, 'id' ) ) ),
				'contents'     => $contents,
			);
			if ( 'InitiateCheckout' === $name ) {
				$custom['num_items'] = $quantity;
			}
		}
		return Capi_Data_Profile::apply( $custom, $name );
	}

	/**
	 * Produit publié (une taille : elle et son parent publiés), ou null.
	 *
	 * @param int $id ID WooCommerce.
	 * @return \WC_Product|null
	 */
	private static function product( int $id ): ?\WC_Product {
		$product = wc_get_product( $id );
		if ( ! $product instanceof \WC_Product || 'publish' !== get_post_status( $id ) ) {
			return null;
		}
		$parent = $product->get_parent_id();
		return $parent > 0 && 'publish' !== get_post_status( $parent ) ? null : $product;
	}

	/**
	 * Bloc user_data de la requête : en-têtes, cookies, et `ph` déjà vérifié.
	 *
	 * @param string $ph Empreinte vérifiée par {@see verified_ph()}, ou chaîne vide.
	 * @return array<string, string>
	 */
	public static function user_data( string $ph ): array {
		return array_filter(
			array(
				'client_ip_address' => Capi_Signals::client_ip(),
				'client_user_agent' => Capi_Signals::client_user_agent(),
				'fbp'               => Capi_Signals::cookie( '_fbp' ),
				'fbc'               => Capi_Signals::cookie( '_fbc' ),
				'external_id'       => Capi_External_Id::from_visitor( Visitor_Context::cookie_id( Visitor_Context::VISITOR_COOKIE ) ),
				'ph'                => $ph,
			)
		);
	}

	/**
	 * L'empreinte reçue, si elle est celle d'un numéro saisi par ce visiteur ; sinon vide.
	 *
	 * Lecture indexée (`visitor_id`) des trois derniers numéros de la table des
	 * saisies, faite seulement quand le lot porte une empreinte. Même format
	 * que la CAPI : SHA-256 de 216 + 8 chiffres (`Capi_User_Data`).
	 *
	 * @param string $ph Empreinte reçue (64 caractères hexadécimaux).
	 * @return string
	 */
	public static function verified_ph( string $ph ): string {
		$visitor = Visitor_Context::cookie_id( Visitor_Context::VISITOR_COOKIE );
		$store   = Visitor_Inputs::instance();
		if ( '' === $visitor || ! $store->enabled() ) {
			return '';
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table des saisies, lecture indexée sur visitor_id ; jamais mise en cache (donnée personnelle).
		$phones = $wpdb->get_col( $wpdb->prepare( "SELECT phone_norm FROM %i WHERE visitor_id = %s AND phone_norm <> '' ORDER BY updated_at DESC LIMIT 3", $store->table(), $visitor ) );
		foreach ( is_array( $phones ) ? $phones : array() as $norm ) {
			if ( hash_equals( Capi_User_Data::hash( Capi_User_Data::phone( (string) $norm ) ), $ph ) ) {
				return $ph;
			}
		}
		return '';
	}

	/**
	 * Le journal des visiteurs a-t-il déjà enregistré ce visiteur (N1) ?
	 *
	 * Lecture indexée (`visitor_id`) d'une seule ligne, faite seulement quand
	 * le lot n'a pas lui-même été enregistré par le journal dans le même envoi.
	 *
	 * @param string $visitor Valeur du cookie `famma_vid` (uuid).
	 * @return bool
	 */
	public static function journal_knows( string $visitor ): bool {
		if ( ! Visitor_Context::is_uuid( $visitor ) ) {
			return false;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Journal des visiteurs, lecture indexée sur visitor_id d'une ligne ; jamais mise en cache (anti-abus).
		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE visitor_id = %s LIMIT 1', Visitor_Store::instance()->table(), $visitor ) );
	}
}
