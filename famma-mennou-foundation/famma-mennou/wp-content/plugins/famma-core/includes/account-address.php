<?php
/**
 * Adresse du client dans « Mon compte » — mêmes champs que la commande.
 *
 * Case `acct_forms` (avec `acct_look`). Sur « Mon compte » seulement, le
 * formulaire d'adresse de WooCommerce garde ce que la commande demande en
 * Tunisie : nom, gouvernorat, « Ville, cité, rue » (avec un repère pour le
 * livreur) et téléphone. Société, complément, code postal, ville et e-mail
 * disparaissent, comme au tunnel de commande ; le pays devient un champ
 * caché, rempli avec le pays de la boutique.
 *
 * Un seul filtre (`woocommerce_billing_fields`) sert à l'affichage ET au
 * contrôle à l'enregistrement : WooCommerce valide exactement les champs
 * montrés. Hors de « Mon compte » (commande, admin), rien ne change.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Champs de l'adresse de facturation dans « Mon compte ».
 */
final class Account_Address {

	/**
	 * Pose les filtres.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_filter( 'woocommerce_billing_fields', array( self::class, 'billing_fields' ), 20 );
		add_filter( 'woocommerce_address_to_edit', array( self::class, 'address_to_edit' ), 20, 2 );
	}

	/**
	 * Le formulaire courant est-il celui de « Mon compte », case cochée ?
	 *
	 * @return bool
	 */
	private static function active(): bool {
		return is_user_logged_in()
			&& function_exists( 'is_account_page' )
			&& is_account_page()
			&& New_Look::part( 'acct_look' )
			&& New_Look::part( 'acct_forms' );
	}

	/**
	 * Champs de la maquette : nom, gouvernorat, adresse, téléphone.
	 *
	 * @param mixed $fields Champs de facturation de WooCommerce.
	 * @return mixed
	 */
	public static function billing_fields( $fields ) {
		if ( ! is_array( $fields ) || ! self::active() ) {
			return $fields;
		}

		unset(
			$fields['billing_company'],
			$fields['billing_address_2'],
			$fields['billing_postcode'],
			$fields['billing_city'],
			$fields['billing_email']
		);

		if ( isset( $fields['billing_country'] ) ) {
			$fields['billing_country']['type']  = 'hidden';
			$fields['billing_country']['label'] = '';
		}

		if ( isset( $fields['billing_state'] ) ) {
			$label                                  = Account_Panels::text( 'sheet_gov' );
			$fields['billing_state']['required']    = true;
			$fields['billing_state']['priority']    = 40;
			$fields['billing_state']['label']       = '' !== $label ? $label : $fields['billing_state']['label'];
			$fields['billing_state']['class']       = array( 'form-row-wide' );
			$fields['billing_state']['label_class'] = array();
		}

		if ( isset( $fields['billing_address_1'] ) ) {
			$label                                      = Account_Panels::text( 'sheet_street' );
			$fields['billing_address_1']['priority']    = 50;
			$fields['billing_address_1']['label']       = '' !== $label ? $label : $fields['billing_address_1']['label'];
			$fields['billing_address_1']['placeholder'] = '';
			$fields['billing_address_1']['description'] = Account_Panels::text( 'sheet_street_help' );
			$fields['billing_address_1']['label_class'] = array();
		}

		if ( isset( $fields['billing_phone'] ) ) {
			$fields['billing_phone']['required']    = true;
			$fields['billing_phone']['priority']    = 60;
			$fields['billing_phone']['placeholder'] = 'XX XXX XXX';
		}

		return $fields;
	}

	/**
	 * Pays caché : celui du client, sinon celui de la boutique.
	 *
	 * @param mixed  $address      Champs, avec leur valeur.
	 * @param string $load_address Type d'adresse.
	 * @return mixed
	 */
	public static function address_to_edit( $address, $load_address = '' ) {
		if ( 'billing' !== $load_address || ! is_array( $address ) || ! self::active() ) {
			return $address;
		}

		if ( isset( $address['billing_country'] ) && empty( $address['billing_country']['value'] ) && function_exists( 'WC' ) && WC()->countries ) {
			$address['billing_country']['value'] = WC()->countries->get_base_country();
		}

		return $address;
	}
}
