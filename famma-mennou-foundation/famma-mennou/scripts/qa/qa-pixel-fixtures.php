<?php
/**
 * Fixtures QA du clone pixel « famma-qa-pixel » (plan pixel, lot 0, T0.3).
 * Run with FAMMA_INTEGRATION_TEST=1, FAMMA_TEST_DB_NAME=famma_integration,
 * FAMMA_TEST_DB_HOST=db, then wp eval-file /tests/qa/qa-pixel-fixtures.php.
 *
 * Idempotent : chaque produit est retrouvé par son slug et n'est modifié que
 * s'il porte le marqueur `_famma_qa_fixture`. Un produit du même slug sans ce
 * marqueur arrête le script : rien de réel n'est jamais écrasé. Toutes les
 * fixtures sont marquées « QA » et n'existent que dans le clone local.
 *
 * Sortie : JSON (ID, SKU, slug, URL, prix, stock) sur la sortie standard.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- WP-CLI fixture entry point, not an autoloaded plugin class file.

defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This fixture script requires WP-CLI and explicit isolated-clone opt-in.' );
}
$famma_expected_db      = (string) getenv( 'FAMMA_TEST_DB_NAME' );
$famma_expected_db_host = (string) getenv( 'FAMMA_TEST_DB_HOST' );
if ( 'local' !== wp_get_environment_type() || 'famma_integration' !== $famma_expected_db || 'db' !== $famma_expected_db_host || ! defined( 'DB_NAME' ) || DB_NAME !== $famma_expected_db || ! defined( 'DB_HOST' ) || DB_HOST !== $famma_expected_db_host ) {
	throw new RuntimeException( 'Refusing writes: expected disposable local database identity is not explicitly confirmed.' );
}
$famma_home = wp_parse_url( home_url( '/' ) );
if ( ! is_array( $famma_home ) || 'localhost' !== ( $famma_home['host'] ?? '' ) || 8980 !== (int) ( $famma_home['port'] ?? 0 ) ) {
	throw new RuntimeException( 'Refusing writes: the QA pixel clone must answer on http://localhost:8980.' );
}
if ( ! class_exists( 'WC_Product_Simple' ) || ! class_exists( 'WC_Product_Variable' ) || ! class_exists( '\Famma\Core\Product_Sizes' ) ) {
	throw new RuntimeException( 'Required active WooCommerce/FAMMA APIs are missing.' );
}

/** Create or refresh the marked QA products of the pixel clone. */
final class Famma_Qa_Pixel_Fixtures {

	/** Marqueur posé sur chaque produit et variation QA. */
	private const MARKER = 'qa-pixel-20261019';

	/** Méta du marqueur. */
	private const MARKER_KEY = '_famma_qa_fixture';

	/** Description commune, explicite sur la nature du produit. */
	private const DESCRIPTION = 'Produit de test QA (clone local famma-qa-pixel). Donnée fictive, ne jamais publier en production.';

	/**
	 * Crée ou remet à niveau les fixtures, puis imprime leur inventaire.
	 *
	 * @return void
	 */
	public static function run(): void {
		$out = array();

		$out[] = self::simple(
			'qa-simple-sku',
			'QA Simple SKU',
			'QA-SKU-1',
			'49.000',
			''
		);
		$out[] = self::simple(
			'qa-simple-sans-sku',
			'QA Simple sans SKU',
			'',
			'29.500',
			''
		);
		$out[] = self::variable();
		$out[] = self::simple(
			'qa-tailles-famma',
			'QA Tailles famma',
			'QA-SKU-TAILLES',
			'45.000',
			'S, M'
		);

		echo wp_json_encode(
			array(
				'marker'   => self::MARKER,
				'currency' => get_woocommerce_currency(),
				'products' => $out,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		) . "\n";
	}

	/**
	 * Produit simple, avec ou sans SKU, avec ou sans tailles famma-core.
	 *
	 * @param string $slug  Slug du produit.
	 * @param string $name  Nom affiché.
	 * @param string $sku   SKU, vide pour aucun.
	 * @param string $price Prix TND, 3 décimales.
	 * @param string $sizes Tailles famma-core (« S, M »), vide pour aucune.
	 * @return array<string, mixed>
	 */
	private static function simple( string $slug, string $name, string $sku, string $price, string $sizes ): array {
		$existing = self::owned( $slug );
		$product  = $existing instanceof WC_Product_Simple ? $existing : new WC_Product_Simple();

		self::common( $product, $slug, $name, $price, 100 );
		$product->set_sku( $sku );
		if ( '' !== $sizes ) {
			$product->update_meta_data( \Famma\Core\Product_Sizes::META, $sizes );
		} else {
			$product->delete_meta_data( \Famma\Core\Product_Sizes::META );
		}
		$product->save();

		return self::describe( $product );
	}

	/**
	 * Produit variable « QA Tailles », attribut local Taille (S, M).
	 *
	 * @return array<string, mixed>
	 */
	private static function variable(): array {
		$slug     = 'qa-tailles';
		$existing = self::owned( $slug );
		$product  = $existing instanceof WC_Product_Variable ? $existing : new WC_Product_Variable();

		$attribute = new WC_Product_Attribute();
		$attribute->set_name( 'Taille' );
		$attribute->set_options( array( 'S', 'M' ) );
		$attribute->set_position( 0 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		self::common( $product, $slug, 'QA Tailles', '', 0 );
		$product->set_manage_stock( false );
		$product->set_sku( 'QA-TAILLES' );
		$product->set_attributes( array( $attribute ) );
		$product->save();

		$variations = array(
			'S' => array( 'QA-TAILLES-S', '39.000' ),
			'M' => array( 'QA-TAILLES-M', '41.500' ),
		);
		foreach ( $variations as $size => $spec ) {
			$variation_id = (int) wc_get_product_id_by_sku( $spec[0] );
			$variation    = $variation_id > 0 ? wc_get_product( $variation_id ) : null;
			if ( $variation instanceof WC_Product_Variation ) {
				if ( $variation->get_parent_id() !== $product->get_id() || self::MARKER !== $variation->get_meta( self::MARKER_KEY ) ) {
					throw new RuntimeException( 'SKU ' . $spec[0] . ' belongs to a non-QA product; nothing overwritten.' );
				}
			} else {
				$variation = new WC_Product_Variation();
				$variation->set_parent_id( $product->get_id() );
			}
			$variation->set_attributes( array( 'taille' => $size ) );
			$variation->set_sku( $spec[0] );
			$variation->set_regular_price( $spec[1] );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( 50 );
			$variation->set_stock_status( 'instock' );
			$variation->set_status( 'publish' );
			$variation->update_meta_data( self::MARKER_KEY, self::MARKER );
			$variation->save();
		}

		WC_Product_Variable::sync( $product->get_id() );
		$product = wc_get_product( $product->get_id() );

		return self::describe( $product );
	}

	/**
	 * Champs communs : nom, slug, prix, stock, statut, marqueur.
	 *
	 * @param WC_Product $product Produit.
	 * @param string     $slug    Slug.
	 * @param string     $name    Nom.
	 * @param string     $price   Prix TND (vide pour un produit variable).
	 * @param int        $stock   Stock géré (0 : non géré ici).
	 * @return void
	 */
	private static function common( WC_Product $product, string $slug, string $name, string $price, int $stock ): void {
		$product->set_name( $name );
		$product->set_slug( $slug );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_description( self::DESCRIPTION );
		$product->set_short_description( self::DESCRIPTION );
		if ( '' !== $price ) {
			$product->set_regular_price( $price );
		}
		if ( $stock > 0 ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $stock );
			$product->set_stock_status( 'instock' );
		}
		$product->update_meta_data( self::MARKER_KEY, self::MARKER );
	}

	/**
	 * Produit QA existant pour ce slug, ou null. Refuse tout produit non marqué.
	 *
	 * @param string $slug Slug recherché.
	 * @return WC_Product|null
	 */
	private static function owned( string $slug ): ?WC_Product {
		$post = get_page_by_path( $slug, OBJECT, 'product' );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$product = wc_get_product( $post->ID );
		if ( ! $product instanceof WC_Product || self::MARKER !== $product->get_meta( self::MARKER_KEY ) ) {
			throw new RuntimeException( 'Slug ' . $slug . ' is used by a non-QA product; nothing overwritten.' );
		}

		return $product;
	}

	/**
	 * Inventaire d'un produit pour la sortie JSON.
	 *
	 * @param WC_Product $product Produit.
	 * @return array<string, mixed>
	 */
	private static function describe( WC_Product $product ): array {
		$row = array(
			'id'          => $product->get_id(),
			'type'        => $product->get_type(),
			'name'        => $product->get_name(),
			'sku'         => $product->get_sku(),
			'slug'        => $product->get_slug(),
			'url'         => get_permalink( $product->get_id() ),
			'price'       => $product->get_price(),
			'stock'       => $product->get_stock_quantity(),
			'famma_sizes' => \Famma\Core\Product_Sizes::options( $product ),
		);
		if ( $product instanceof WC_Product_Variable ) {
			$row['variations'] = array();
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child instanceof WC_Product_Variation ) {
					$row['variations'][] = array(
						'id'         => $child->get_id(),
						'sku'        => $child->get_sku(),
						'attributes' => $child->get_attributes(),
						'price'      => $child->get_price(),
						'stock'      => $child->get_stock_quantity(),
					);
				}
			}
		}

		return $row;
	}
}

Famma_Qa_Pixel_Fixtures::run();
