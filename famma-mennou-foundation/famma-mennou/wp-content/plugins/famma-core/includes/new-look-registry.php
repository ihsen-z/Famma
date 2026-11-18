<?php
/**
 * Registre des briques du nouveau look.
 *
 * Une brique = une entrée dans le fichier de sa page
 * (`new-look-bricks-{site,home,shop,product,cart,account}.php`). Chaque entrée
 * déclare :
 * - `title`   : titre court, affiché à côté de l'interrupteur ;
 * - `summary` : une phrase, ce que le client voit ;
 * - `help`    : le détail complet, replié dans « En savoir plus » ;
 * - `needs`   : les briques sans lesquelles elle reste sans effet ;
 * - `texts`   : ses textes, clés du schéma de sa page dans `Pages_Content`,
 *               ou noms de classes (`Account_Favs::class`) dont tout le
 *               `schema()` lui revient ;
 * - `wait`    : les textes qui, vides, la gardent cachée.
 *
 * Le registre ne change rien au fonctionnement : `New_Look::part()` lit
 * toujours l'option `famma_new_look`, et `needs` / `wait` ne servent qu'à
 * montrer l'état réel dans FAMMA → Nouveau look.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Liste unique des briques, rangées par page.
 */
final class New_Look_Registry {

	/**
	 * Pages, dans l'ordre des onglets, avec la classe qui déclare leurs briques.
	 *
	 * La clé est aussi celle de la page de textes au sens de `Pages_Content`,
	 * sauf `site` qui n'a pas de textes propres.
	 *
	 * @var array<string, class-string>
	 */
	private const SOURCES = array(
		'site'    => New_Look_Bricks_Site::class,
		'home'    => New_Look_Bricks_Home::class,
		'shop'    => New_Look_Bricks_Shop::class,
		'product' => New_Look_Bricks_Product::class,
		'cart'    => New_Look_Bricks_Cart::class,
		'account' => New_Look_Bricks_Account::class,
	);

	/**
	 * Clés des pages, dans l'ordre des onglets.
	 *
	 * @return array<int, string>
	 */
	public static function pages(): array {
		return array_keys( self::SOURCES );
	}

	/**
	 * Toutes les briques, normalisées, dans l'ordre des onglets.
	 *
	 * @return array<string, array{page: string, title: string, summary: string, help: string, needs: array<int, string>, texts: array<int, string>, wait: array<int, string>}>
	 */
	public static function bricks(): array {
		$all = array();

		foreach ( self::SOURCES as $page => $source ) {
			foreach ( $source::bricks() as $key => $brick ) {
				$brick = is_array( $brick ) ? $brick : array();

				$all[ (string) $key ] = array(
					'page'    => $page,
					'title'   => (string) ( $brick['title'] ?? $key ),
					'summary' => (string) ( $brick['summary'] ?? '' ),
					'help'    => (string) ( $brick['help'] ?? '' ),
					'needs'   => self::strings( $brick['needs'] ?? array() ),
					'texts'   => self::strings( $brick['texts'] ?? array() ),
					'wait'    => self::strings( $brick['wait'] ?? array() ),
				);
			}
		}

		return $all;
	}

	/**
	 * Briques d'une page.
	 *
	 * @param string $page Clé de page.
	 * @return array<string, array<string, mixed>>
	 */
	public static function for_page( string $page ): array {
		return array_filter(
			self::bricks(),
			static function ( array $brick ) use ( $page ): bool {
				return $brick['page'] === $page;
			}
		);
	}

	/**
	 * Clés de textes d'une brique, résolues.
	 *
	 * Une classe nommée dans `texts` cède toutes les clés de son `schema()` :
	 * un texte ajouté plus tard à cette classe suit la brique sans retouche.
	 *
	 * @param array<string, mixed> $brick Brique normalisée.
	 * @return array<int, string>
	 */
	public static function text_keys( array $brick ): array {
		$keys = array();

		foreach ( self::strings( $brick['texts'] ?? array() ) as $item ) {
			// Un nom de classe porte toujours son espace de noms : une clé de
			// texte (« ledger_price ») n'a jamais de barre oblique inverse.
			if ( ! str_contains( $item, '\\' ) ) {
				$keys[] = $item;
				continue;
			}

			if ( class_exists( $item ) && method_exists( $item, 'schema' ) ) {
				$keys = array_merge( $keys, self::strings( array_column( (array) $item::schema(), 'key' ) ) );
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Page de textes (`Pages_Content`) d'une page du registre : la même clé.
	 * « Partout » a la sienne depuis la brique `notices` (`Notice_Texts`).
	 *
	 * @param string $page Clé de page.
	 * @return string
	 */
	public static function text_page( string $page ): string {
		return $page;
	}

	/**
	 * Ne garde que des chaînes non vides.
	 *
	 * @param mixed $items Liste brute.
	 * @return array<int, string>
	 */
	private static function strings( $items ): array {
		$out = array();

		foreach ( (array) $items as $item ) {
			if ( is_scalar( $item ) && '' !== (string) $item ) {
				$out[] = (string) $item;
			}
		}

		return $out;
	}
}
