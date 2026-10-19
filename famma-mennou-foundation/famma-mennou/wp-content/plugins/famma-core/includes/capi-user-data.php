<?php
/**
 * Bloc `user_data` des événements serveur Meta, normalisé selon la doc (lot 5, A13).
 *
 * Référence : Meta, Conversions API, « Customer Information Parameters »
 * (developers.facebook.com/documentation/ads-commerce/conversions-api/
 * parameters/customer-information-parameters), relue le 19/10/2026 :
 *
 * - fn, ln : « Lowercase only with no punctuation. If using special
 *   characters, the text must be encoded in UTF-8 format » (Valéry → valéry) ;
 * - ct : « Lowercase only with no punctuation, no special characters, and no
 *   spaces », lettres UTF-8 conservées (accents, arabe) ;
 * - st : « Normalize states outside the US in lowercase with no punctuation,
 *   no special characters, and no spaces ». Forme retenue : le code ISO du
 *   gouvernorat sans tiret (TN-82 → tn82), filtrable par `famma_capi_state_value` ;
 * - country : code ISO 3166-1 alpha-2 en minuscules (tn) ;
 * - ph : indicatif compris, chiffres seuls (déjà conforme, inchangé) ;
 * - client_user_agent : « required for website events » — il n'est envoyé,
 *   comme event_source_url, que pour un événement `website`.
 *
 * Avant la 0.3.2, prénom et ville passaient par `strtolower()` ASCII :
 * « Élodie » gardait sa majuscule accentuée, « Médenine » devenait
 * « mdenine » et une ville arabe disparaissait (T1.4).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Normalisation et hachage des identifiants client.
 */
final class Capi_User_Data {

	/**
	 * Source retenue pour une commande sans navigateur (doc Server Event
	 * Parameters, `action_source` : « other — Conversion happened in a way
	 * that is not listed »).
	 */
	public const OFFSITE_ACTION_SOURCE = 'other';

	/**
	 * Sources acceptées par Meta pour `action_source`.
	 */
	private const ACTION_SOURCES = array( 'email', 'website', 'app', 'phone_call', 'chat', 'physical_store', 'system_generated', 'business_messaging', 'other' );

	/**
	 * Construit le bloc user_data d'une commande.
	 *
	 * @param \WC_Order $order   Commande concernée.
	 * @param bool      $website Événement `website` (navigateur connu).
	 * @return array<string, string>
	 */
	public static function build( \WC_Order $order, bool $website ): array {
		$data = array_filter(
			array(
				'em'      => self::hash( self::email( (string) $order->get_billing_email() ) ),
				'ph'      => self::hash( self::phone( (string) $order->get_billing_phone() ) ),
				'fn'      => self::hash( self::name( (string) $order->get_billing_first_name() ) ),
				'ln'      => self::hash( self::name( (string) $order->get_billing_last_name() ) ),
				'ct'      => self::hash( self::city( (string) $order->get_billing_city() ) ),
				'st'      => self::hash( self::state( (string) $order->get_billing_state(), (string) $order->get_billing_country() ) ),
				'country' => self::hash( self::country( (string) $order->get_billing_country() ) ),
			)
		);

		/*
		 * Ces quatre-là ne sont PAS hachés : Meta les attend en clair. Ils
		 * viennent toujours des métadonnées figées à la commande — jamais de
		 * la requête courante, qui pour Purchase est celle de l'admin.
		 */
		foreach ( array(
			'fbp' => '_famma_fbp',
			'fbc' => '_famma_fbc',
		) as $key => $meta ) {
			$value = Capi_Signals::clean_cookie( (string) $order->get_meta( $meta ) );
			if ( '' !== $value ) {
				$data[ $key ] = $value;
			}
		}
		$ip = Capi_Signals::clean_ip( (string) $order->get_meta( '_famma_client_ip' ) );
		if ( '' !== $ip ) {
			$data['client_ip_address'] = $ip;
		}
		$ua = (string) $order->get_meta( '_famma_client_ua' );
		if ( $website && '' !== $ua ) {
			$data['client_user_agent'] = $ua;
		}
		return $data;
	}

	/**
	 * La commande a-t-elle été passée dans un navigateur connu ?
	 *
	 * Un événement `website` exige `client_user_agent` : sans user-agent
	 * enregistré (commande créée dans l'administration, par exemple), il ne
	 * l'est pas.
	 *
	 * @param \WC_Order $order Commande concernée.
	 * @return bool
	 */
	public static function is_website( \WC_Order $order ): bool {
		return '' !== trim( (string) $order->get_meta( '_famma_client_ua' ) );
	}

	/**
	 * Source hors site d'une commande, filtrable par `famma_capi_action_source`.
	 *
	 * @param \WC_Order $order Commande concernée.
	 * @return string
	 */
	public static function offsite_action_source( \WC_Order $order ): string {
		$source = apply_filters( 'famma_capi_action_source', self::OFFSITE_ACTION_SOURCE, $order );

		return is_string( $source ) && 'website' !== $source && in_array( $source, self::ACTION_SOURCES, true ) ? $source : self::OFFSITE_ACTION_SOURCE;
	}

	/**
	 * Hache une valeur déjà normalisée.
	 *
	 * @param string $value Valeur normalisée.
	 * @return string Empreinte SHA-256, ou chaîne vide.
	 */
	public static function hash( string $value ): string {
		return '' === $value ? '' : hash( 'sha256', $value );
	}

	/**
	 * E-mail : espaces retirés, minuscules.
	 *
	 * @param string $email Valeur saisie.
	 * @return string
	 */
	public static function email( string $email ): string {
		return mb_strtolower( trim( $email ), 'UTF-8' );
	}

	/**
	 * Téléphone : 216 + 8 chiffres nationaux, format attendu par Meta.
	 *
	 * @param string $phone Valeur saisie.
	 * @return string
	 */
	public static function phone( string $phone ): string {
		$national = class_exists( Tunisia::class, false ) ? Tunisia::normalize_phone( $phone ) : '';

		return '' !== $national ? '216' . $national : Config::international_phone( $phone );
	}

	/**
	 * Prénom ou nom : minuscules UTF-8, sans ponctuation ni symbole, espaces réduits.
	 *
	 * @param string $name Valeur saisie.
	 * @return string
	 */
	public static function name( string $name ): string {
		$name = (string) preg_replace( '/[\p{P}\p{S}]+/u', '', self::lower( $name ) );

		return trim( (string) preg_replace( '/\s+/u', ' ', $name ) );
	}

	/**
	 * Ville : minuscules UTF-8, lettres et chiffres seulement, sans espace.
	 *
	 * @param string $city Valeur saisie.
	 * @return string
	 */
	public static function city( string $city ): string {
		return (string) preg_replace( '/[^\p{L}\p{M}\p{N}]+/u', '', self::lower( $city ) );
	}

	/**
	 * Gouvernorat : minuscules, sans ponctuation ni espace (TN-82 → tn82).
	 *
	 * @param string $state   Code enregistré par WooCommerce.
	 * @param string $country Pays de facturation.
	 * @return string
	 */
	public static function state( string $state, string $country ): string {
		$value = (string) preg_replace( '/[^\p{L}\p{M}\p{N}]+/u', '', self::lower( $state ) );
		$value = apply_filters( 'famma_capi_state_value', $value, $state, $country );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Pays : code ISO 3166-1 alpha-2 en minuscules, sinon rien.
	 *
	 * @param string $country Valeur enregistrée.
	 * @return string
	 */
	public static function country( string $country ): string {
		$country = strtolower( trim( $country ) );

		return 1 === preg_match( '/^[a-z]{2}$/D', $country ) ? $country : '';
	}

	/**
	 * Minuscules UTF-8, après composition canonique quand intl est présent.
	 *
	 * @param string $value Valeur saisie.
	 * @return string
	 */
	private static function lower( string $value ): string {
		$value = trim( $value );
		if ( '' === $value || ! mb_check_encoding( $value, 'UTF-8' ) ) {
			return '';
		}
		if ( class_exists( '\Normalizer' ) ) {
			$composed = \Normalizer::normalize( $value, \Normalizer::FORM_C );
			$value    = is_string( $composed ) ? $composed : $value;
		}
		return mb_strtolower( $value, 'UTF-8' );
	}
}
