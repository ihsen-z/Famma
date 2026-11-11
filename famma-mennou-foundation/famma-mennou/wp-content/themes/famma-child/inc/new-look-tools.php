<?php
/**
 * Nouveau look — brique `tools` : rayons et budget en puces, sur mobile.
 *
 * Reprend les « outils collants » de la maquette boutique du 06/11, mais en
 * vrais liens GET : aucun filtrage dans le navigateur, aucune requête
 * réécrite. Chaque puce pose le même paramètre que la colonne « Filtres »
 * (`product_cat`, `max_price`), avec les mêmes fonctions d'URL
 * (inc/shop-widgets.php) : les résultats sont ceux du widget, au produit
 * près. Les pages filtrées restent en `noindex` (famma-core, Seo_Listing).
 *
 * Les budgets ne sont pas des paliers inventés : ce sont les vrais prix
 * publiés, lus une fois puis gardés dans une option chargée d'office.
 * Une page en cache ne coûte donc aucune requête de plus ; l'option est
 * effacée à chaque enregistrement de produit et au passage des promotions
 * programmées.
 *
 * Affichée sous 1000 px seulement : au-delà, la colonne de filtres est
 * dépliée à côté de la grille et propose déjà les mêmes choix.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Option qui garde les budgets proposés.
 */
const FAMMA_CHILD_PRICE_STEPS_OPTION = 'famma_shop_price_steps';

/**
 * Nombre de puces de budget au plus.
 */
const FAMMA_CHILD_PRICE_STEPS_MAX = 3;

/**
 * Choisit au plus `$max` prix répartis sur toute la gamme.
 *
 * Le plus cher n'est jamais proposé : « jusqu'au prix le plus élevé »
 * revient à ne pas filtrer.
 *
 * @param array<int, int> $prices Prix publiés, en dinars entiers.
 * @param int             $max    Nombre de puces au plus.
 * @return array<int, int> Prix croissants, sans doublon.
 */
function famma_child_pick_price_steps( array $prices, int $max ): array {
	$prices = array_values( array_unique( array_map( 'intval', $prices ) ) );
	sort( $prices, SORT_NUMERIC );
	array_pop( $prices );

	$count = count( $prices );

	if ( $count <= $max || $max < 2 ) {
		return array_slice( $prices, 0, max( 0, $max ) );
	}

	$steps = array();

	for ( $i = 0; $i < $max; $i++ ) {
		$steps[] = $prices[ (int) round( $i * ( $count - 1 ) / ( $max - 1 ) ) ];
	}

	return array_values( array_unique( $steps ) );
}

/**
 * Budgets proposés en puces.
 *
 * WooCommerce garde un produit quand son prix le plus bas est sous le
 * plafond (`wc_product_meta_lookup.min_price`) : c'est aussi ce que renvoie
 * `get_price()`, y compris pour un produit à variantes.
 *
 * @return array<int, int>
 */
function famma_child_price_steps(): array {
	$steps = get_option( FAMMA_CHILD_PRICE_STEPS_OPTION );

	if ( is_array( $steps ) ) {
		return array_map( 'intval', $steps );
	}

	$prices = array();

	if ( function_exists( 'wc_get_products' ) ) {
		$products = wc_get_products(
			array(
				'status'     => 'publish',
				'visibility' => 'catalog',
				'limit'      => -1,
			)
		);

		foreach ( (array) $products as $product ) {
			if ( $product instanceof WC_Product && '' !== $product->get_price() ) {
				$prices[] = (int) ceil( (float) $product->get_price() );
			}
		}
	}

	$steps = famma_child_pick_price_steps( $prices, FAMMA_CHILD_PRICE_STEPS_MAX );

	update_option( FAMMA_CHILD_PRICE_STEPS_OPTION, $steps, true );

	return $steps;
}

/**
 * Efface les budgets : ils seront relus au prochain affichage.
 *
 * @return void
 */
function famma_child_flush_price_steps(): void {
	delete_option( FAMMA_CHILD_PRICE_STEPS_OPTION );
}
add_action( 'woocommerce_update_product', 'famma_child_flush_price_steps' );
add_action( 'woocommerce_new_product', 'famma_child_flush_price_steps' );
add_action( 'save_post_product', 'famma_child_flush_price_steps' );
add_action( 'woocommerce_scheduled_sales', 'famma_child_flush_price_steps', 20 );

/**
 * Rayon affiché : celui de la page de rayon, sinon celui de l'URL.
 *
 * @return string Identifiant du rayon, ou chaîne vide.
 */
function famma_child_tools_active_category(): string {
	if ( is_product_category() ) {
		$term = get_queried_object();

		return $term instanceof WP_Term ? $term->slug : '';
	}

	return famma_child_filter_active( 'product_cat' );
}

/**
 * Lien d'une puce de budget.
 *
 * Sur une page de rayon, le budget reste dans le rayon : le lien garde
 * l'adresse du rayon au lieu de repartir de la boutique entière.
 *
 * @param string $value Plafond en dinars, ou chaîne vide pour le retirer.
 * @return string
 */
function famma_child_tools_budget_url( string $value ): string {
	$term = is_product_taxonomy() ? get_queried_object() : null;
	$base = $term instanceof WP_Term ? get_term_link( $term ) : '';

	if ( ! is_string( $base ) || '' === $base ) {
		return famma_child_filter_url( 'max_price', $value );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture d'un etat de navigation, aucune ecriture.
	$args = array_map( 'sanitize_text_field', wp_unslash( (array) $_GET ) );

	unset( $args['paged'], $args['page'], $args['max_price'] );

	if ( '' !== $value ) {
		$args['max_price'] = $value;
	}

	return array() === $args ? $base : add_query_arg( $args, $base );
}

/**
 * Puces des rayons : « Toutes » puis chaque rayon, comme la colonne.
 *
 * @return array<int, array{label: string, url: string, count: int, active: bool}>
 */
function famma_child_tools_category_chips(): array {
	$active = famma_child_tools_active_category();

	$chips = array(
		array(
			'label'  => __( 'Toutes', 'famma-child' ),
			'url'    => famma_child_filter_url( 'product_cat', '' ),
			'count'  => famma_child_count_products( array(), 'all' ),
			'active' => '' === $active,
		),
	);

	foreach ( famma_child_product_categories() as $term ) {
		$chips[] = array(
			'label'  => $term->name,
			'url'    => famma_child_filter_url( 'product_cat', $term->slug ),
			'count'  => (int) $term->count,
			'active' => $active === $term->slug,
		);
	}

	return $chips;
}

/**
 * Affiche la rangée de puces.
 *
 * @return void
 */
function famma_child_new_look_tools(): void {
	if ( ! in_array( 'tools', famma_child_new_look_parts(), true )
		|| ! function_exists( 'famma_child_filter_url' )
		|| ! function_exists( 'famma_child_product_categories' ) ) {
		return;
	}

	$chips   = famma_child_tools_category_chips();
	$steps   = famma_child_price_steps();
	$current = (int) famma_child_filter_active( 'max_price' );
	$symbol  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';

	if ( count( $chips ) < 2 && array() === $steps ) {
		return;
	}
	?>
	<nav class="famma-tools" aria-label="<?php esc_attr_e( 'Rayons et budget', 'famma-child' ); ?>">
		<div class="famma-tools__scroll">
			<ul class="famma-tools__list" aria-label="<?php esc_attr_e( 'Rayons', 'famma-child' ); ?>">
				<?php foreach ( $chips as $chip ) : ?>
					<li>
						<a class="famma-chip" href="<?php echo esc_url( $chip['url'] ); ?>"<?php echo $chip['active'] ? ' aria-current="page"' : ''; ?>>
							<?php echo esc_html( $chip['label'] ); ?>
							<small class="famma-chip__count"><?php echo esc_html( (string) $chip['count'] ); ?></small>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( array() !== $steps ) : ?>
				<ul class="famma-tools__list famma-tools__list--budget" aria-label="<?php esc_attr_e( 'Budget', 'famma-child' ); ?>">
					<?php
					foreach ( $steps as $step ) :
						$on = $current === $step;
						?>
						<li>
							<a class="famma-chip famma-chip--budget" href="<?php echo esc_url( famma_child_tools_budget_url( $on ? '' : (string) $step ) ); ?>"<?php echo $on ? ' aria-current="true"' : ''; ?>>
								<?php
								printf(
									/* translators: %s: prix plafond, par exemple « 29 DT ». */
									esc_html__( 'Jusqu’à %s', 'famma-child' ),
									esc_html( $step . ' ' . $symbol )
								);
								?>
								<?php if ( $on ) : ?>
									<span class="famma-chip__off" aria-hidden="true">×</span>
									<span class="screen-reader-text"><?php esc_html_e( '(retirer ce budget)', 'famma-child' ); ?></span>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</nav>
	<?php
}
add_action( 'famma_child_after_shop_head', 'famma_child_new_look_tools' );
