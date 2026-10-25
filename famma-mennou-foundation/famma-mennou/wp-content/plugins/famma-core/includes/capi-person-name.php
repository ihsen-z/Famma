<?php
/**
 * Prénom et nom envoyés à Meta (`fn`, `ln`) : normalisation et civilité (0.3.4, D1, D2, F1, F2).
 *
 * Référence : Meta, Conversions API, « Customer Information Parameters »,
 * relue le 19/10/2026 : fn, ln en « Lowercase only with no punctuation. If
 * using special characters, the text must be encoded in UTF-8 format »
 * (Valéry → valéry). Les espaces sont aussi retirés (D1), comme le font le
 * pixel (fbevents.js 2.9.426, `whitespace_and_punctuation`) et le Parameter
 * Builder officiel (`getNormalizedName`) : « Ben Salah » → bensalah,
 * « بن صالح » → بنصالح, sans translittération. Ces deux outils ne retirent que
 * la ponctuation ASCII ; FAMMA retire aussi la ponctuation et les symboles
 * Unicode (’, « », ، , emoji) et les caractères invisibles (marques de
 * direction, espaces de largeur nulle) : pour ces caractères, l'empreinte
 * diffère de celle du pixel.
 *
 * Une civilité en tête (« Mme Leila ») est retirée du bloc envoyé, jamais de
 * la commande (D2).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Normalisation des prénoms et noms du bloc `user_data`.
 */
final class Capi_Person_Name {

	/**
	 * Civilités retirées en tête du prénom (D2), comparées en minuscules,
	 * point ou virgule final ignoré (virgule latine ou arabe). Courte liste
	 * fermée, variantes de saisie arabes comprises (F2 : الانسة sans hamza,
	 * السيده écrit avec ha) : jamais d'abréviation développée.
	 */
	private const CIVILITIES = array( 'm', 'mr', 'mme', 'mlle', 'melle', 'mrs', 'ms', 'dr', 'السيد', 'السيدة', 'السيده', 'الآنسة', 'الانسة' );

	/**
	 * Prénom ou nom : minuscules UTF-8, sans ponctuation, symbole ni espace (D1).
	 *
	 * Les caractères invisibles (catégorie Cf : marques de direction, espaces
	 * et liants de largeur nulle, BOM) et les sélecteurs de variante d'emoji
	 * sont retirés aussi : ils arrivent avec une saisie arabe copiée-collée
	 * ou un emoji et changeraient l'empreinte sans changer le nom.
	 *
	 * @param string $name Valeur saisie.
	 * @return string
	 */
	public static function normalize( string $name ): string {
		return (string) preg_replace( '/[\p{P}\p{S}\p{Z}\p{Cf}\s\x{FE00}-\x{FE0F}]+/u', '', Capi_User_Data::lower( $name ) );
	}

	/**
	 * Prénom et nom envoyés à Meta, civilité de tête retirée (D2, F1).
	 *
	 * Le découpage du nom complet à la commande (`Tunisia::split_full_name()`)
	 * fait de « Mme Leila Ben Ali » le prénom « Mme » et le nom
	 * « Leila Ben Ali ». Si le premier mot du prénom est une civilité, elle est
	 * retirée selon ce qui la suit, prénom et nom réunis, une ponctuation
	 * isolée (« M . Ali ») ne comptant pas comme un mot :
	 * - un seul mot (« Mme Trabelsi », « M. » / « Ali ») : convention retenue
	 *   le 21/10 (F1), une civilité précède en général le nom de famille ; ce
	 *   mot part en nom et aucun prénom n'est envoyé (un prénom tapé seul
	 *   après une civilité, « Mme Leila », part donc en nom) ;
	 * - deux mots ou plus : le reste du prénom devient le prénom, ou, si le
	 *   prénom n'était que la civilité, le premier mot du nom (le reste restant
	 *   le nom) ;
	 * - rien : la civilité seule reste inchangée.
	 * Limite connue : un nom de famille composé après une civilité
	 * (« M. » / « Ben Ali ») donne le prénom « Ben » et le nom « Ali ».
	 * Seul le bloc envoyé à Meta est concerné : les données de la commande ne
	 * sont jamais modifiées.
	 *
	 * @param string $first Prénom enregistré.
	 * @param string $last  Nom enregistré.
	 * @return array{0: string, 1: string} Prénom, nom.
	 */
	public static function without_civility( string $first, string $last ): array {
		$first_words = self::words( $first );
		if ( array() === $first_words || ! self::is_civility( $first_words[0] ) ) {
			return array( $first, $last );
		}
		$rest       = self::significant( array_slice( $first_words, 1 ) );
		$last_words = self::significant( self::words( $last ) );
		$left       = array_merge( $rest, $last_words );
		if ( array() === $left ) {
			return array( $first, $last );
		}
		if ( 1 === count( $left ) ) {
			return array( '', $left[0] );
		}
		if ( array() !== $rest ) {
			return array( implode( ' ', $rest ), $last );
		}

		return array( $last_words[0], implode( ' ', array_slice( $last_words, 1 ) ) );
	}

	/**
	 * Mots d'une valeur saisie, séparés par des espaces, caractères invisibles retirés.
	 *
	 * Sans ce retrait, une marque de direction collée à « السيدة » la
	 * cacherait à la liste des civilités.
	 *
	 * @param string $value Valeur saisie.
	 * @return array<int, string>
	 */
	private static function words( string $value ): array {
		$words = preg_split( '/[\s\p{Z}]+/u', (string) preg_replace( '/\p{Cf}+/u', '', $value ), -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $words ) ? $words : array();
	}

	/**
	 * Mots non vides une fois normalisés : une ponctuation isolée (« , »,
	 * « . », « - ») n'est pas un mot (F1).
	 *
	 * @param array<int, string> $words Mots d'une valeur saisie.
	 * @return array<int, string>
	 */
	private static function significant( array $words ): array {
		return array_values(
			array_filter(
				$words,
				static fn( string $word ): bool => '' !== self::normalize( $word )
			)
		);
	}

	/**
	 * Le mot est-il une civilité de la liste, casse et point ou virgule final ignorés (F2) ?
	 *
	 * La virgule arabe « ، » (U+060C), celle du clavier arabe, compte comme
	 * une virgule ; expression UTF-8, `rtrim()` travaillant octet par octet.
	 *
	 * @param string $word Premier mot du prénom.
	 * @return bool
	 */
	private static function is_civility( string $word ): bool {
		return in_array( (string) preg_replace( '/[.,\x{060C}]+$/u', '', Capi_User_Data::lower( $word ) ), self::CIVILITIES, true );
	}
}
