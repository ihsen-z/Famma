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
	 * Le produit lié doit exister, être publié et visible dans la boutique, et
	 * ne pas être le produit lui-même. Phrase dans la langue de la page, sinon
	 * dans l'autre (avec sa langue).
	 *
	 * @param \WC_Product $product Produit affiché.
	 * @return array{url: string, text: string, lang: string}|array{}
	 */
	public static function link( \WC_Product $product ): array {
		$id     = (int) $product->get_meta( self::META_PRODUCT );
		$target = $id > 0 && $id !== $product->get_id() ? wc_get_product( $id ) : null;

		if ( ! $target instanceof \WC_Product || 'publish' !== $target->get_status() || ! $target->is_visible() ) {
			return array();
		}

		$page = is_rtl() ? 'ar' : 'fr';

		foreach ( array( $page, 'ar' === $page ? 'fr' : 'ar' ) as $lang ) {
			$text = trim( (string) $product->get_meta( self::META_TEXT . $lang ) );

			if ( '' !== $text ) {
				return array(
					'url'  => (string) $target->get_permalink(),
					'text' => $text,
					'lang' => $lang,
				);
			}
		}

		return array();
	}
}
