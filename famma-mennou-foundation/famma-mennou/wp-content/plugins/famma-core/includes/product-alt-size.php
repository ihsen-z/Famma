<?php
/**
 * Fiche produit, lot 49 : lien « autre taille » sous les tailles.
 *
 * Exemple de la maquette : sur la ceinture Confort (L à XXXL), « Vous portez
 * S ou M ? Voir la Ceinture dorsale Pro ». Produit lié et phrase saisis
 * produit par produit dans l'onglet « Fiche FAMMA » de Produits → Modifier
 * (`Product_Alt_Size_Admin`) ; rien n'est écrit en dur : sans produit ou sans
 * phrase, pas de lien (§60).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lecture du lien « autre taille » d'un produit.
 */
final class Product_Alt_Size {

	/**
	 * Produit lié (identifiant).
	 *
	 * @var string
	 */
	public const META_PRODUCT = '_famma_alt_size_product';

	/**
	 * Phrase du lien, une méta par langue : `_famma_alt_size_text_<fr|ar>`.
	 *
	 * @var string
	 */
	public const META_TEXT = '_famma_alt_size_text_';

	/**
	 * Lien prêt à afficher, ou tableau vide.
	 *
	 * Le produit lié doit pouvoir être acheté (voir `usable()`) et ne pas être
	 * le produit lui-même. Phrase dans la langue de la page, sinon dans l'autre
	 * (avec sa langue). La phrase est lue d'abord : sans elle, le produit lié
	 * n'est même pas chargé.
	 *
	 * @param \WC_Product $product Produit affiché.
	 * @return array{url: string, text: string, lang: string}|array{}
	 */
	public static function link( \WC_Product $product ): array {
		$page = is_rtl() ? 'ar' : 'fr';
		$text = '';
		$lang = '';

		foreach ( array( $page, 'ar' === $page ? 'fr' : 'ar' ) as $try ) {
			$text = trim( (string) $product->get_meta( self::META_TEXT . $try ) );

			if ( '' !== $text ) {
				$lang = $try;
				break;
			}
		}

		$id = (int) $product->get_meta( self::META_PRODUCT );

		if ( '' === $lang || $id <= 0 || $id === $product->get_id() ) {
			return array();
		}

		$target = wc_get_product( $id );

		if ( ! $target instanceof \WC_Product || ! self::usable( $target ) ) {
			return array();
		}

		return array(
			'url'  => (string) $target->get_permalink(),
			'text' => $text,
			'lang' => $lang,
		);
	}

	/**
	 * Le produit proposé peut-il l'être : publié, visible dans la boutique et
	 * en stock (jamais un lien vers un produit qu'on ne peut pas acheter).
	 *
	 * @param \WC_Product $target Produit proposé.
	 * @return bool
	 */
	public static function usable( \WC_Product $target ): bool {
		return 'publish' === $target->get_status() && $target->is_visible() && $target->is_in_stock();
	}
}
