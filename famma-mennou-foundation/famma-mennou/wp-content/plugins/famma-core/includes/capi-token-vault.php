<?php
/**
 * Jeton de la Conversions API chiffré au repos (lot 5, D7, A11).
 *
 * Jusqu'en 0.3.1, le jeton saisi dans « Mesure » vivait en clair dans
 * `wp_options` : tout administrateur le lisait sur `/wp-admin/options.php`,
 * et il partait tel quel dans chaque sauvegarde de la base (T2.12). Il y est
 * désormais chiffré (`sodium_crypto_secretbox`), avec une clé dérivée de
 * `wp_salt( 'auth' )` et d'un libellé propre à FAMMA : une base copiée sans
 * `wp-config.php` ne livre plus le jeton.
 *
 * - un jeton en clair est chiffré à sa première lecture, sans action du
 *   propriétaire ;
 * - la constante `FAMMA_META_ACCESS_TOKEN` reste prioritaire (Config) ;
 * - si le déchiffrement échoue (clés du site changées), le jeton est traité
 *   comme absent : avis « jeton à ressaisir » et état de santé rouge.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Chiffrement, déchiffrement et migration du jeton enregistré en option.
 */
final class Capi_Token_Vault {

	/**
	 * Option qui abrite le jeton.
	 */
	public const OPTION = 'famma_meta_access_token';

	/**
	 * Préfixe d'une valeur chiffrée.
	 */
	private const PREFIX = 'famma-enc:v1:';

	/**
	 * Libellé de dérivation de la clé.
	 */
	private const LABEL = 'famma-core|meta-capi-token|v1';

	/**
	 * Dernier état lu : `empty`, `sealed`, `plain` ou `broken`.
	 *
	 * @var string
	 */
	private static string $state = '';

	/**
	 * Jeton en clair, ou chaîne vide (absent ou illisible).
	 *
	 * @return string
	 */
	public static function read(): string {
		$stored = trim( (string) get_option( self::OPTION, '' ) );
		if ( '' === $stored ) {
			self::$state = 'empty';
			return '';
		}
		if ( ! self::is_sealed( $stored ) ) {
			// Migration transparente : le jeton en clair est chiffré dès sa première lecture.
			$sealed      = self::seal( $stored );
			self::$state = 'plain';
			if ( '' !== $sealed && update_option( self::OPTION, $sealed, false ) ) {
				self::$state = 'sealed';
			}
			return $stored;
		}
		$plain       = self::open( $stored );
		self::$state = '' === $plain ? 'broken' : 'sealed';
		return $plain;
	}

	/**
	 * État du jeton enregistré, lu si besoin.
	 *
	 * @return string `empty`, `sealed`, `plain` (chiffrement indisponible) ou `broken`.
	 */
	public static function state(): string {
		if ( '' === self::$state ) {
			self::read();
		}
		return self::$state;
	}

	/**
	 * Les quatre derniers caractères du jeton, seule partie jamais affichée.
	 *
	 * @return string
	 */
	public static function last4(): string {
		$token = self::read();
		return '' === $token ? '' : substr( $token, -4 );
	}

	/**
	 * La valeur porte-t-elle la marque d'un jeton chiffré ?
	 *
	 * @param string $value Valeur enregistrée.
	 * @return bool
	 */
	public static function is_sealed( string $value ): bool {
		return str_starts_with( $value, self::PREFIX );
	}

	/**
	 * Chiffre un jeton ; chaîne vide si le chiffrement est indisponible.
	 *
	 * @param string $plain Jeton en clair.
	 * @return string
	 */
	public static function seal( string $plain ): string {
		if ( '' === $plain || ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return '';
		}
		try {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$box   = sodium_crypto_secretbox( $plain, $nonce, self::key() );
			return self::PREFIX . sodium_bin2base64( $nonce . $box, SODIUM_BASE64_VARIANT_ORIGINAL );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Déchiffre une valeur ; chaîne vide si elle est illisible.
	 *
	 * @param string $sealed Valeur chiffrée.
	 * @return string
	 */
	public static function open( string $sealed ): string {
		if ( ! self::is_sealed( $sealed ) || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}
		try {
			$raw = sodium_base642bin( substr( $sealed, strlen( self::PREFIX ) ), SODIUM_BASE64_VARIANT_ORIGINAL );
			if ( strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
				return '';
			}
			$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::key() );
		} catch ( \Exception $e ) {
			return '';
		}
		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Clé de 32 octets dérivée du sel `auth` du site et d'un libellé FAMMA.
	 *
	 * @return string
	 */
	private static function key(): string {
		return hash_hmac( 'sha256', self::LABEL, wp_salt( 'auth' ), true );
	}
}
