<?php
/**
 * Fiche produit, lot 46 : récit et « Ce qu'il y a dedans », produit par produit.
 *
 * Données saisies dans l'onglet « Fiche FAMMA » de Produits → Modifier
 * (`Product_Story_Admin`) ; le thème ne fait que les afficher. Rien n'est
 * écrit en dur : un produit non rempli n'a ni récit ni anatomie (§60).
 *
 * Langue : la version de la page (français, ou derja sur /ar/) ; si elle est
 * vide, l'autre version est servie avec sa propre langue et son sens, comme
 * la maquette qui montre le récit en derja sur la page française.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lecture des champs « récit » et « anatomie » d'un produit.
 */
final class Product_Story {

	/**
	 * Préfixe des méta : `_famma_story_<champ>_<langue>`.
	 *
	 * @var string
	 */
	public const META = '_famma_story_';

	/**
	 * Champs traduits (une méta par langue).
	 *
	 * @var array<int, string>
	 */
	public const TEXT_FIELDS = array( 'text', 'start', 'pick', 'chips', 'parts' );

	/**
	 * Photo de l'anatomie (identifiant de pièce jointe du produit).
	 *
	 * @var string
	 */
	public const META_IMAGE = '_famma_story_image';

	/**
	 * Place des repères : une ligne « x, y » en pourcentage par repère.
	 *
	 * @var string
	 */
	public const META_SPOTS = '_famma_story_spots';

	/**
	 * Icônes proposées pour les situations.
	 *
	 * @var array<int, string>
	 */
	public const ICONS = array( 'monitor', 'mobile', 'car', 'book', 'home' );

	/**
	 * Situations ou repères au plus.
	 *
	 * @var int
	 */
	public const MAX = 6;

	/**
	 * Récit du produit, prêt à afficher.
	 *
	 * @param \WC_Product $product Produit.
	 * @return array{lang: string, parts: array<int, array{text: string, hot: bool}>, start: string, pick: string, chips: array<int, array{icon: string, label: string, phrase: string}>}|array{}
	 */
	public static function story( \WC_Product $product ): array {
		$lang = self::lang( $product, 'text' );

		if ( '' === $lang ) {
			return array();
		}

		$chips = array();

		foreach ( self::lines( self::get( $product, 'chips', $lang ) ) as $line ) {
			// « icône | bouton | phrase » ; l'icône (ou « - ») et la phrase sont facultatives.
			$cells = array_map( 'trim', explode( '|', $line ) );
			$icon  = '';

			if ( count( $cells ) > 1 && in_array( $cells[0], array_merge( self::ICONS, array( '', '-' ) ), true ) ) {
				$icon = '-' === $cells[0] ? '' : $cells[0];
				array_shift( $cells );
			}

			$label = (string) ( $cells[0] ?? '' );

			if ( '' === $label ) {
				continue;
			}

			$chips[] = array(
				'icon'   => $icon,
				'label'  => $label,
				'phrase' => '' !== (string) ( $cells[1] ?? '' ) ? (string) $cells[1] : $label,
			);
		}

		return array(
			'lang'  => $lang,
			'parts' => self::hot_parts( self::get( $product, 'text', $lang ) ),
			'start' => self::get( $product, 'start', $lang ),
			'pick'  => self::get( $product, 'pick', $lang ),
			'chips' => array_slice( $chips, 0, self::MAX ),
		);
	}

	/**
	 * Anatomie du produit : photo et repères, prête à afficher.
	 *
	 * Il faut une photo du produit lui-même et au moins deux repères placés.
	 *
	 * @param \WC_Product $product Produit.
	 * @return array{lang: string, image: int, items: array<int, array{x: float, y: float, text: string}>}|array{}
	 */
	public static function anatomy( \WC_Product $product ): array {
		$lang  = self::lang( $product, 'parts' );
		$image = (int) $product->get_meta( self::META_IMAGE );

		if ( '' === $lang || ! in_array( $image, self::images( $product ), true ) ) {
			return array();
		}

		$spots = self::spots( (string) $product->get_meta( self::META_SPOTS ) );
		$items = array();

		foreach ( self::lines( self::get( $product, 'parts', $lang ) ) as $i => $text ) {
			if ( ! isset( $spots[ $i ] ) || count( $items ) >= self::MAX ) {
				break;
			}

			$items[] = array(
				'x'    => $spots[ $i ][0],
				'y'    => $spots[ $i ][1],
				'text' => $text,
			);
		}

		if ( count( $items ) < 2 ) {
			return array();
		}

		return array(
			'lang'  => $lang,
			'image' => $image,
			'items' => $items,
		);
	}

	/**
	 * Photos du produit : principale puis galerie.
	 *
	 * @param \WC_Product $product Produit.
	 * @return array<int, int>
	 */
	public static function images( \WC_Product $product ): array {
		$ids = array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', $product->get_gallery_image_ids() ) );

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Repères saisis : « x, y » par ligne, en pourcentage, bornés à 0-100.
	 *
	 * @param string $raw Valeur enregistrée.
	 * @return array<int, array{0: float, 1: float}>
	 */
	public static function spots( string $raw ): array {
		$out = array();

		foreach ( self::lines( $raw ) as $line ) {
			if ( ! preg_match( '/^\s*(\d{1,3}(?:[.,]\d+)?)\s*[,;]\s*(\d{1,3}(?:[.,]\d+)?)\s*$/', $line, $m ) ) {
				continue;
			}

			$out[] = array(
				min( 100.0, round( (float) str_replace( ',', '.', $m[1] ), 1 ) ),
				min( 100.0, round( (float) str_replace( ',', '.', $m[2] ), 1 ) ),
			);
		}

		return $out;
	}

	/**
	 * Valeur d'un champ traduit.
	 *
	 * @param \WC_Product $product Produit.
	 * @param string      $field   Champ de TEXT_FIELDS.
	 * @param string      $lang    `fr` ou `ar`.
	 * @return string
	 */
	public static function get( \WC_Product $product, string $field, string $lang ): string {
		return trim( (string) $product->get_meta( self::META . $field . '_' . $lang ) );
	}

	/**
	 * Langue servie pour un bloc : celle de la page si son champ principal est
	 * rempli, sinon l'autre, sinon aucune.
	 *
	 * @param \WC_Product $product Produit.
	 * @param string      $field   Champ principal du bloc.
	 * @return string `fr`, `ar` ou chaîne vide.
	 */
	private static function lang( \WC_Product $product, string $field ): string {
		$page = is_rtl() ? 'ar' : 'fr';

		foreach ( array( $page, 'ar' === $page ? 'fr' : 'ar' ) as $lang ) {
			if ( '' !== self::get( $product, $field, $lang ) ) {
				return $lang;
			}
		}

		return '';
	}

	/**
	 * Découpe le récit : les passages entre deux astérisques passent en orange.
	 *
	 * @param string $text Récit saisi.
	 * @return array<int, array{text: string, hot: bool}>
	 */
	private static function hot_parts( string $text ): array {
		$text  = (string) preg_replace( '/\s+/u', ' ', $text );
		$parts = array();

		// Un astérisque orphelin est une coquille, pas le début d'un passage.
		if ( 1 === substr_count( $text, '*' ) % 2 ) {
			$text = (string) preg_replace( '/\*(?!.*\*)/u', '', $text );
		}

		foreach ( explode( '*', $text ) as $i => $chunk ) {
			if ( '' !== $chunk ) {
				$parts[] = array(
					'text' => $chunk,
					'hot'  => 1 === $i % 2,
				);
			}
		}

		return $parts;
	}

	/**
	 * Lignes non vides d'un champ.
	 *
	 * @param string $raw Texte saisi.
	 * @return array<int, string>
	 */
	private static function lines( string $raw ): array {
		$lines = preg_split( '/\R/u', $raw );

		return array_values( array_filter( array_map( 'trim', false === $lines ? array() : $lines ), 'strlen' ) );
	}
}
