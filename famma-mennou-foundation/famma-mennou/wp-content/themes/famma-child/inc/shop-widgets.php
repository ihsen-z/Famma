<?php
/**
 * FAMMA — colonne de filtres de la boutique.
 *
 * La maquette compose **une seule carte** contenant six sections séparées par
 * un filet, et non six cartes. Chaque ligne suit le même dessin : libellé à
 * gauche, compteur entre parenthèses à droite, l'entrée active en orange.
 *
 * Les widgets natifs de WooCommerce produisent chacun un balisage différent —
 * liste hiérarchique pour les catégories, curseur jQuery UI à deux poignées
 * pour le prix, aucun pour la disponibilité. Les reproduire au pixel
 * demanderait plus de CSS de correction que de code de rendu. Ils sont donc
 * écrits ici, **mais aucun ne réimplémente de requête** : chacun pose un
 * paramètre d'URL que WooCommerce sait déjà lire (`product_cat`, `max_price`,
 * `rating_filter`) ou que `inc/shop.php` branche sur `woocommerce_product_query`.
 *
 * Rendus en liens, pas en boutons de formulaire : un filtre est une
 * navigation. Il fonctionne sans JavaScript, se partage et revient avec le
 * bouton « précédent ».
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Durée de vie des compteurs de filtre.
 */
const FAMMA_CHILD_FILTER_TTL = 15 * MINUTE_IN_SECONDS;

/**
 * Compte les produits publiés répondant à un critère.
 *
 * Mis en cache : l'appel est fait une fois par ligne de filtre, ce qui
 * deviendrait coûteux dès quelques centaines de produits (§10).
 *
 * @param array<string, mixed> $args Arguments `wc_get_products`.
 * @param string               $key  Clé de cache.
 * @return int
 */
function famma_child_count_products( array $args, string $key ): int {
	$cached = get_transient( 'famma_filter_count_' . $key );

	if ( false !== $cached ) {
		return (int) $cached;
	}

	if ( ! function_exists( 'wc_get_products' ) ) {
		return 0;
	}

	$ids = wc_get_products(
		array_merge(
			$args,
			array(
				'status' => 'publish',
				'limit'  => -1,
				'return' => 'ids',
			)
		)
	);

	$count = is_array( $ids ) ? count( $ids ) : 0;

	set_transient( 'famma_filter_count_' . $key, $count, FAMMA_CHILD_FILTER_TTL );

	return $count;
}

/**
 * Nombre de produits en ligne réellement en promotion.
 *
 * `wc_get_product_ids_on_sale()` lit une table de correspondance et un
 * transient que WooCommerce ne remet pas toujours à jour : constaté le 24/09,
 * elle renvoyait un identifiant alors qu'aucun produit n'avait de prix barré,
 * et « En promotion (1) » menait à une page vide. Chaque identifiant est donc
 * revérifié par `is_on_sale()`, sur les seuls produits publiés et visibles.
 *
 * @return int
 */
function famma_child_on_sale_count(): int {
	$cached = get_transient( 'famma_filter_count_onsale' );

	if ( false !== $cached ) {
		return (int) $cached;
	}

	$ids   = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();
	$count = 0;

	if ( is_array( $ids ) && array() !== $ids && function_exists( 'wc_get_products' ) ) {
		$products = wc_get_products(
			array(
				'include'    => array_map( 'absint', $ids ),
				'status'     => 'publish',
				'visibility' => 'catalog',
				'limit'      => -1,
			)
		);

		foreach ( (array) $products as $product ) {
			if ( $product instanceof WC_Product && $product->is_on_sale() ) {
				++$count;
			}
		}
	}

	set_transient( 'famma_filter_count_onsale', $count, FAMMA_CHILD_FILTER_TTL );

	return $count;
}

/**
 * Purge tous les compteurs de filtre.
 *
 * @return void
 */
function famma_child_flush_filter_counts(): void {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- purge de transients : aucune API du coeur ne supprime par prefixe.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_famma_filter_%' OR option_name LIKE '_transient_timeout_famma_filter_%'" );
}
add_action( 'woocommerce_update_product', 'famma_child_flush_filter_counts' );
add_action( 'woocommerce_new_product', 'famma_child_flush_filter_counts' );
add_action( 'save_post_product', 'famma_child_flush_filter_counts' );

/**
 * Construit l'URL de la boutique avec un paramètre ajouté ou retiré.
 *
 * Les autres filtres actifs sont conservés : cocher « en promotion » ne doit
 * pas effacer la catégorie déjà choisie. La pagination est remise à zéro —
 * rester page 4 d'un résultat qui n'en compte plus qu'une affiche du vide.
 *
 * @param string $param Nom du paramètre, ou chaîne vide pour tout retirer.
 * @param string $value Valeur, ou chaîne vide pour retirer ce filtre.
 * @return string
 */
function famma_child_filter_url( string $param, string $value ): string {
	$base = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

	if ( '' === $param ) {
		return (string) $base;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture d'un etat de navigation, aucune ecriture.
	$current = array_map( 'sanitize_text_field', wp_unslash( (array) $_GET ) );

	unset( $current['paged'], $current['page'] );

	if ( '' === $value ) {
		unset( $current[ $param ] );
	} else {
		$current[ $param ] = $value;
	}

	// Une catégorie seule a sa vraie page : le lien la vise plutôt qu'un doublon à paramètre (SEO-01).
	if ( array( 'product_cat' ) === array_keys( $current ) ) {
		$term_link = get_term_link( (string) $current['product_cat'], 'product_cat' );

		if ( ! is_wp_error( $term_link ) ) {
			return $term_link;
		}
	}

	return array() === $current ? (string) $base : add_query_arg( $current, (string) $base );
}

/**
 * Renvoie la valeur active d'un paramètre de filtre.
 *
 * @param string $param Nom du paramètre.
 * @return string
 */
function famma_child_filter_active( string $param ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture d'un etat de navigation, aucune ecriture.
	return isset( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : '';
}

/**
 * La coche de la maquette, en SVG.
 *
 * @return string
 */
function famma_child_filter_tick(): string {
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 12.5l5 5L20 6.5"/></svg>';
}

/**
 * Affiche une ligne de filtre.
 *
 * Un seul rendu pour les six sections : la maquette leur donne le même
 * dessin, et deux balisages divergeraient à la première retouche.
 *
 * @param array{label: string, url: string, count: int|null, active: bool, check: bool, stars: string} $row Ligne.
 * @return void
 */
function famma_child_filter_row( array $row ): void {
	$classes = 'famma-filter__link';

	if ( $row['check'] ) {
		$classes .= ' famma-filter__link--check';
	}

	if ( '' !== $row['stars'] ) {
		$classes .= ' famma-filter__link--rating';
	}

	if ( $row['active'] ) {
		$classes .= $row['check'] ? ' is-checked' : ' is-active';
	}

	echo '<li class="famma-filter__row">';
	printf( '<a class="%1$s" href="%2$s"', esc_attr( $classes ), esc_url( $row['url'] ) );
	echo $row['check'] ? ' aria-pressed="' . ( $row['active'] ? 'true' : 'false' ) . '"' : '';
	echo $row['active'] && ! $row['check'] ? ' aria-current="true"' : '';
	echo '>';

	if ( $row['check'] ) {
		printf(
			'<span class="famma-filter__box" aria-hidden="true">%s</span>',
			$row['active'] ? famma_child_filter_tick() : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- table fermee, aucun contenu utilisateur.
		);
	}

	if ( '' !== $row['stars'] ) {
		printf( '<span class="famma-filter__stars" aria-hidden="true">%s</span>', esc_html( $row['stars'] ) );
	}

	printf( '<span class="famma-filter__label">%s</span>', esc_html( $row['label'] ) );

	if ( null !== $row['count'] ) {
		printf( '<span class="famma-filter__count">(%s)</span>', esc_html( number_format_i18n( $row['count'] ) ) );
	}

	echo '</a></li>';
}

/**
 * Gabarit d'une ligne, pour n'écrire que ce qui change.
 *
 * @param array<string, mixed> $row Valeurs partielles.
 * @return array<string, mixed>
 */
function famma_child_filter_row_defaults( array $row ): array {
	return wp_parse_args(
		$row,
		array(
			'label'  => '',
			'url'    => '',
			'count'  => null,
			'active' => false,
			'check'  => false,
			'stars'  => '',
		)
	);
}

/**
 * Base commune aux widgets de filtre.
 */
abstract class Famma_Child_Filter_Widget extends WP_Widget {

	/**
	 * Titre par défaut.
	 *
	 * @return string
	 */
	abstract protected function default_title(): string;

	/**
	 * Lignes à afficher.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	abstract protected function rows(): array;

	/**
	 * Contenu additionnel rendu avant les lignes (curseur de prix).
	 *
	 * @return void
	 */
	protected function before_rows(): void {}

	/**
	 * La section a-t-elle autre chose que des lignes à montrer ?
	 *
	 * Seul le curseur de prix répond oui : il n'a pas de lignes mais un
	 * contenu propre.
	 *
	 * @return bool
	 */
	protected function has_own_content(): bool {
		return false;
	}

	/**
	 * Affiche le widget.
	 *
	 * @param array<string, string> $args     Enveloppe fournie par la barre latérale.
	 * @param array<string, mixed>  $instance Réglages.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
			return;
		}

		$rows = $this->rows();

		/*
		 * Une section vide n'est pas rendue du tout — pas même son titre.
		 * « Note moyenne » sans un seul produit noté afficherait un intitulé
		 * suivi de rien, et un filet de séparation pour rien.
		 */
		if ( array() === $rows && ! $this->has_own_content() ) {
			return;
		}

		$title = isset( $instance['title'] ) && '' !== $instance['title'] ? $instance['title'] : $this->default_title();

		echo wp_kses_post( $args['before_widget'] );
		echo wp_kses_post( $args['before_title'] . esc_html( $title ) . $args['after_title'] );

		$this->before_rows();

		if ( array() !== $rows ) {
			echo '<ul class="famma-filter__list">';
			foreach ( $rows as $row ) {
				famma_child_filter_row( famma_child_filter_row_defaults( $row ) );
			}
			echo '</ul>';
		}

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Formulaire d'administration.
	 *
	 * @param array<string, mixed> $instance Réglages.
	 * @return void
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? (string) $instance['title'] : $this->default_title();
		printf(
			'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s"></p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_html__( 'Titre :', 'famma-child' ),
			esc_attr( $this->get_field_name( 'title' ) ),
			esc_attr( $title )
		);
	}

	/**
	 * Enregistre les réglages.
	 *
	 * @param array<string, mixed> $new_instance Nouveaux réglages.
	 * @param array<string, mixed> $old_instance Anciens réglages.
	 * @return array<string, string>
	 */
	public function update( $new_instance, $old_instance ) {
		return array( 'title' => sanitize_text_field( (string) ( $new_instance['title'] ?? '' ) ) );
	}
}

/**
 * Filtre « Catégories ».
 */
class Famma_Child_Category_Filter_Widget extends Famma_Child_Filter_Widget {

	/**
	 * Déclare le widget.
	 */
	public function __construct() {
		parent::__construct(
			'famma_category_filter',
			__( 'FAMMA — Filtrer par catégorie', 'famma-child' ),
			array( 'description' => __( 'Liste « Toutes les catégories » + rayons, avec compteurs.', 'famma-child' ) )
		);
	}

	/**
	 * Titre par défaut.
	 *
	 * @return string
	 */
	protected function default_title(): string {
		return __( 'Catégories', 'famma-child' );
	}

	/**
	 * Lignes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function rows(): array {
		/*
		 * Meme source que le sous-menu « Categories » de l'en-tete, dans le
		 * meme ordre : voir `inc/catalog.php`. Deux requetes separees pour la
		 * meme liste finissent toujours par diverger — c'est arrive ici, ou le
		 * menu affichait « Uncategorized » quand la colonne affichait cinq
		 * rayons.
		 */
		$terms = famma_child_product_categories();

		$active = famma_child_filter_active( 'product_cat' );

		/*
		 * « Toutes les catégories » porte le total du catalogue, pas la somme
		 * des rayons : un produit rangé dans deux rayons serait sinon compté
		 * deux fois.
		 */
		$rows = array(
			array(
				'label'  => __( 'Toutes les catégories', 'famma-child' ),
				'url'    => famma_child_filter_url( 'product_cat', '' ),
				'count'  => famma_child_count_products( array(), 'all' ),
				'active' => '' === $active,
			),
		);

		foreach ( $terms as $term ) {
			$rows[] = array(
				'label'  => $term->name,
				'url'    => famma_child_filter_url( 'product_cat', $term->slug ),
				'count'  => (int) $term->count,
				'active' => $active === $term->slug,
			);
		}

		return $rows;
	}
}

/**
 * Filtre « Prix ».
 */
class Famma_Child_Price_Filter_Widget extends Famma_Child_Filter_Widget {

	/**
	 * Déclare le widget.
	 */
	public function __construct() {
		parent::__construct(
			'famma_price_filter',
			__( 'FAMMA — Filtrer par prix', 'famma-child' ),
			array( 'description' => __( 'Curseur de prix maximum, avec bornes en dinars.', 'famma-child' ) )
		);
	}

	/**
	 * Titre par défaut.
	 *
	 * @return string
	 */
	protected function default_title(): string {
		return __( 'Prix', 'famma-child' );
	}

	/**
	 * Pas de lignes : ce filtre est un curseur.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function rows(): array {
		return array();
	}

	/**
	 * Le curseur est un contenu à lui seul.
	 *
	 * @return bool
	 */
	protected function has_own_content(): bool {
		return true;
	}

	/**
	 * Prix le plus élevé du catalogue, arrondi à la dizaine supérieure.
	 *
	 * @return int
	 */
	private function ceiling(): int {
		$cached = get_transient( 'famma_filter_price_max' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$max = 0;

		if ( function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products(
				array(
					'status'   => 'publish',
					'limit'    => 1,
					'orderby'  => 'meta_value_num',
					'meta_key' => '_price', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- une seule ligne, resultat mis en cache.
					'order'    => 'DESC',
				)
			);

			if ( is_array( $products ) && isset( $products[0] ) && $products[0] instanceof WC_Product ) {
				$max = (int) ceil( (float) $products[0]->get_price() / 10 ) * 10;
			}
		}

		$max = max( 10, $max );

		set_transient( 'famma_filter_price_max', $max, FAMMA_CHILD_FILTER_TTL );

		return $max;
	}

	/**
	 * Affiche le curseur et ses bornes.
	 *
	 * Un `<form>` en GET : sans JavaScript, le champ reste utilisable et le
	 * bouton d'envoi — visible au clavier seulement — valide la sélection.
	 * `shop-view.js` se contente d'envoyer le formulaire au relâchement.
	 *
	 * @return void
	 */
	protected function before_rows(): void {
		$ceiling = $this->ceiling();
		$current = famma_child_filter_active( 'max_price' );
		$value   = '' === $current ? $ceiling : min( $ceiling, max( 0, (int) $current ) );
		$symbol  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
		?>
		<form class="famma-price" method="get" action="<?php echo esc_url( famma_child_filter_url( '', '' ) ); ?>">
			<?php
			/*
			 * Les autres filtres actifs voyagent en champs cachés : sans eux,
			 * bouger le curseur effacerait la catégorie déjà choisie.
			 */
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture d'un etat de navigation, aucune ecriture.
			foreach ( array_map( 'sanitize_text_field', wp_unslash( (array) $_GET ) ) as $key => $val ) :
				if ( in_array( $key, array( 'max_price', 'paged', 'page' ), true ) ) :
					continue;
				endif;
				?>
				<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>">
			<?php endforeach; ?>

			<label class="screen-reader-text" for="<?php echo esc_attr( $this->get_field_id( 'max_price' ) ); ?>">
				<?php esc_html_e( 'Prix maximum', 'famma-child' ); ?>
			</label>
			<input
				class="famma-price__range"
				id="<?php echo esc_attr( $this->get_field_id( 'max_price' ) ); ?>"
				type="range"
				name="max_price"
				min="0"
				max="<?php echo esc_attr( (string) $ceiling ); ?>"
				step="10"
				value="<?php echo esc_attr( (string) $value ); ?>"
			>
			<div class="famma-price__bounds">
				<span>0 <?php echo esc_html( $symbol ); ?></span>
				<span class="famma-price__value"><?php echo esc_html( $value . ' ' . $symbol ); ?></span>
			</div>
			<button class="famma-price__submit" type="submit"><?php esc_html_e( 'Filtrer', 'famma-child' ); ?></button>
		</form>
		<?php
	}
}

/**
 * Filtre « Disponibilité ».
 */
class Famma_Child_Stock_Filter_Widget extends Famma_Child_Filter_Widget {

	/**
	 * Déclare le widget.
	 */
	public function __construct() {
		parent::__construct(
			'famma_stock_filter',
			__( 'FAMMA — Filtrer par disponibilité', 'famma-child' ),
			array( 'description' => __( 'Cases « En stock » et « Rupture de stock ».', 'famma-child' ) )
		);
	}

	/**
	 * Titre par défaut.
	 *
	 * @return string
	 */
	protected function default_title(): string {
		return __( 'Disponibilité', 'famma-child' );
	}

	/**
	 * Lignes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function rows(): array {
		$active = famma_child_filter_active( 'stock_status' );

		$rows = array();

		foreach ( array(
			'instock'    => __( 'En stock', 'famma-child' ),
			'outofstock' => __( 'Rupture de stock', 'famma-child' ),
		) as $value => $label ) {
			$checked = $active === $value;

			$rows[] = array(
				'label'  => $label,
				'url'    => famma_child_filter_url( 'stock_status', $checked ? '' : $value ),
				'count'  => famma_child_count_products( array( 'stock_status' => $value ), $value ),
				'active' => $checked,
				'check'  => true,
			);
		}

		return $rows;
	}
}

/**
 * Filtre « Note moyenne ».
 */
class Famma_Child_Rating_Filter_Widget extends Famma_Child_Filter_Widget {

	/**
	 * Déclare le widget.
	 */
	public function __construct() {
		parent::__construct(
			'famma_rating_filter',
			__( 'FAMMA — Filtrer par note', 'famma-child' ),
			array( 'description' => __( 'Lignes « ★★★★★ et plus », avec compteurs.', 'famma-child' ) )
		);
	}

	/**
	 * Titre par défaut.
	 *
	 * @return string
	 */
	protected function default_title(): string {
		return __( 'Note moyenne', 'famma-child' );
	}

	/**
	 * Lignes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function rows(): array {
		$active = (int) famma_child_filter_active( 'rating_filter' );
		$rows   = array();

		foreach ( array( 5, 4, 3, 2, 1 ) as $stars ) {
			$count = famma_child_count_products(
				array(
					'meta_key'     => '_wc_average_rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- resultat mis en cache par transient.
					'meta_value'   => $stars, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- idem.
					'meta_compare' => '>=',
					'meta_type'    => 'DECIMAL',
				),
				'rating' . $stars
			);

			/*
			 * Une ligne sans aucun produit n'est pas rendue : elle promettrait
			 * un filtre qui ne renvoie rien.
			 */
			if ( 0 === $count ) {
				continue;
			}

			$rows[] = array(
				'label'  => __( 'et plus', 'famma-child' ),
				'url'    => famma_child_filter_url( 'rating_filter', $active === $stars ? '' : (string) $stars ),
				'count'  => $count,
				'active' => $active === $stars,
				'stars'  => str_repeat( '★', $stars ) . str_repeat( '☆', 5 - $stars ),
			);
		}

		return $rows;
	}
}

/**
 * Filtre « Promotions ».
 */
class Famma_Child_Sale_Filter_Widget extends Famma_Child_Filter_Widget {

	/**
	 * Déclare le widget.
	 */
	public function __construct() {
		parent::__construct(
			'famma_sale_filter',
			__( 'FAMMA — Filtrer par promotion', 'famma-child' ),
			array( 'description' => __( 'Case « En promotion ».', 'famma-child' ) )
		);
	}

	/**
	 * Titre par défaut.
	 *
	 * @return string
	 */
	protected function default_title(): string {
		return __( 'Promotions', 'famma-child' );
	}

	/**
	 * Lignes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function rows(): array {
		$checked = '' !== famma_child_filter_active( 'on_sale' );

		$count = famma_child_on_sale_count();

		/*
		 * Comme pour les notes : une case qui ne renvoie rien n'est pas rendue.
		 * Sans promotion en cours, « En promotion (0) » annonçait des remises
		 * qui n'existent pas.
		 */
		if ( 0 === $count && ! $checked ) {
			return array();
		}

		return array(
			array(
				'label'  => __( 'En promotion', 'famma-child' ),
				'url'    => famma_child_filter_url( 'on_sale', $checked ? '' : '1' ),
				'count'  => $count,
				'active' => $checked,
				'check'  => true,
			),
		);
	}
}

/**
 * Enregistre les widgets de filtre.
 *
 * @return void
 */
function famma_child_register_filter_widgets(): void {
	register_widget( 'Famma_Child_Category_Filter_Widget' );
	register_widget( 'Famma_Child_Price_Filter_Widget' );
	register_widget( 'Famma_Child_Stock_Filter_Widget' );
	register_widget( 'Famma_Child_Rating_Filter_Widget' );
	register_widget( 'Famma_Child_Sale_Filter_Widget' );
}
add_action( 'widgets_init', 'famma_child_register_filter_widgets' );

/**
 * Restreint la boutique par état de stock sur `?stock_status=`.
 *
 * Les autres paramètres — `product_cat`, `max_price`, `rating_filter` — sont
 * déjà compris par WooCommerce : rien à écrire pour eux.
 *
 * @param WP_Query $query Requête de la boutique.
 * @return void
 */
function famma_child_filter_stock( $query ): void {
	if ( ! $query instanceof WP_Query ) {
		return;
	}

	$status = famma_child_filter_active( 'stock_status' );

	if ( ! in_array( $status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
		return;
	}

	$meta = (array) $query->get( 'meta_query' );

	$meta[] = array(
		'key'     => '_stock_status',
		'value'   => $status,
		'compare' => '=',
	);

	$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- `_stock_status` est indexe par WooCommerce.
}
add_action( 'woocommerce_product_query', 'famma_child_filter_stock' );

/**
 * Retire le tri « par notes moyennes » tant qu'aucun produit n'est noté.
 *
 * Sans un seul avis, ce tri laisse croire à des notes clients qui n'existent
 * pas, et ne change rien à l'ordre affiché. Il revient de lui-même au premier
 * produit noté. Le compte est celui du filtre « 1 étoile et plus » : même
 * requête, même clé de cache.
 *
 * @param array<string, string> $options Tris proposés.
 * @return array<string, string>
 */
function famma_child_catalog_orderby( $options ) {
	if ( ! is_array( $options ) || ! isset( $options['rating'] ) ) {
		return $options;
	}

	$rated = famma_child_count_products(
		array(
			'meta_key'     => '_wc_average_rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- resultat mis en cache par transient.
			'meta_value'   => 1, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- idem.
			'meta_compare' => '>=',
			'meta_type'    => 'DECIMAL',
		),
		'rating1'
	);

	if ( 0 === $rated ) {
		unset( $options['rating'] );
	}

	return $options;
}
add_filter( 'woocommerce_catalog_orderby', 'famma_child_catalog_orderby' );
