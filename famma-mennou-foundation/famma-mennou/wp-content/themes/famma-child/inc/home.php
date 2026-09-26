<?php
/**
 * FAMMA — page d'accueil.
 *
 * Chaque section est pilotée par la donnée réelle et **disparaît quand cette
 * donnée n'existe pas**. La maquette montrait six catégories, cinq produits,
 * « + 2 500 clients » et trois témoignages signés : ce sont des valeurs de
 * prototype. Une boutique qui démarre les afficherait en mentant à ses
 * premiers visiteurs (§58, §60).
 *
 * Les agrégats passent par des transients : l'accueil est la page la plus
 * demandée du site, elle ne doit pas rejouer les mêmes requêtes à chaque
 * chargement (§10).
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Durée de vie des agrégats de l'accueil.
 *
 * Un quart d'heure : assez pour absorber une pointe de trafic publicitaire,
 * assez court pour qu'un produit mis en ligne apparaisse sans intervention.
 */
const FAMMA_CHILD_HOME_TTL = 15 * MINUTE_IN_SECONDS;

/**
 * Échappe une balise image en gardant `fetchpriority`.
 *
 * `wp_kses_post()` ne connaît pas cet attribut et le retirerait : la priorité
 * posée sur l'image LCP disparaîtrait sans bruit.
 *
 * @param string $html Balise `<img>` produite par WordPress ou WooCommerce.
 * @return string
 */
function famma_child_kses_image( string $html ): string {
	$allowed = wp_kses_allowed_html( 'post' );

	$allowed['img']['fetchpriority'] = true;
	$allowed['img']['decoding']      = true;

	// Sans eux, l'image LCP partait en 600 × 600 sur tous les écrans (CODE-34).
	$allowed['img']['srcset'] = true;
	$allowed['img']['sizes']  = true;

	return wp_kses( $html, $allowed );
}

/**
 * Titre de section — le motif « trait orange · titre · trait orange ».
 *
 * @param string $title Libellé du titre.
 * @return void
 */
function famma_child_home_title( string $title ): void {
	printf(
		'<div class="famma-sectitle"><span class="famma-sectitle__rule" aria-hidden="true"></span>'
		. '<h2 class="famma-sectitle__text">%s</h2>'
		. '<span class="famma-sectitle__rule" aria-hidden="true"></span></div>',
		esc_html( $title )
	);
}

/**
 * Produits mis en avant pour le carrousel du héros.
 *
 * Les produits « à la une » d'abord, les plus récents ensuite. Aucun visuel
 * inventé : un produit sans image ne devient pas une diapositive, il ferait un
 * cadre gris au premier écran.
 *
 * @return array<int, WC_Product>
 */
function famma_child_home_hero_products(): array {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return array();
	}

	$cached = get_transient( 'famma_home_hero' );

	if ( is_array( $cached ) ) {
		return array_filter( array_map( 'wc_get_product', $cached ) );
	}

	$products = wc_get_products(
		array(
			'status'   => 'publish',
			'limit'    => 4,
			'featured' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
		)
	);

	if ( count( $products ) < 2 ) {
		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 4,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
	}

	$products = array_values(
		array_filter(
			$products,
			static function ( $product ): bool {
				return $product instanceof WC_Product && $product->get_image_id();
			}
		)
	);

	set_transient(
		'famma_home_hero',
		array_map(
			static function ( WC_Product $product ): int {
				return $product->get_id();
			},
			$products
		),
		FAMMA_CHILD_HOME_TTL
	);

	return $products;
}

/**
 * Le héros : mot-symbole, slogan, avantages, appel à l'action.
 *
 * Le carrousel n'apparaît qu'à partir de deux visuels. Avec un seul produit —
 * ou aucun — le héros reste un panneau fixe : des flèches et des points qui ne
 * mènent nulle part sont un mécanisme qui ment sur ce qu'il y a à voir.
 *
 * @return void
 */
function famma_child_home_hero(): void {
	$products = famma_child_home_hero_products();
	$slides   = count( $products );
	$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

	$perks = array(
		array(
			'icon'  => 'card',
			'title' => __( 'Paiement à la livraison', 'famma-child' ),
			// En derja, le titre traduit dit déjà la même chose : pas de doublon.
			'sub'   => famma_child_is_derja() ? '' : 'خلص كي توصل',
		),
		array(
			'icon'  => 'truck',
			'title' => __( 'Livraison offerte', 'famma-child' ),
			'sub'   => __( 'partout en Tunisie', 'famma-child' ),
		),
		array(
			'icon'  => 'user',
			'title' => __( 'Commande sans compte', 'famma-child' ),
			'sub'   => __( 'nom, téléphone, adresse', 'famma-child' ),
		),
	);
	?>
	<section class="famma-hero2<?php echo $slides > 1 ? ' has-carousel' : ''; ?>">
		<div class="famma-hero2__text">
			<?php
			/*
			 * Le mot-symbole porte le `<h1>` de la page.
			 *
			 * Constaté au rendu : après le retrait du bloc héros de l'éditeur,
			 * l'accueil se retrouvait avec **zéro** `<h1>` — le titre du thème
			 * étant lui aussi détaché sur cette page (voir
			 * famma_child_remove_front_page_hero). Une page d'accueil sans
			 * titre principal est une régression de référencement et de
			 * navigation au lecteur d'écran.
			 *
			 * « FAMMA » seul ne décrirait pas la page : la baseline française
			 * la complète pour les lecteurs d'écran et les moteurs, sans rien
			 * changer au dessin. C'est le titre que portait l'ancien bloc.
			 */
			?>
			<h1 class="famma-hero2__word">FAMM<span class="famma-hero2__a">A</span><span class="screen-reader-text"> — <?php esc_html_e( 'Il y a toujours quelque chose de nouveau.', 'famma-child' ); ?></span></h1>

			<p class="famma-hero2__slogan" lang="ar" dir="rtl">
				<span class="famma-hero2__rule" aria-hidden="true"></span>
				<span><span class="famma-hero2__ar">فمّا</span> ديما حاجة جديدة</span>
				<span class="famma-hero2__rule" aria-hidden="true"></span>
			</p>

			<p class="famma-hero2__lead">
				<?php esc_html_e( 'FAMMA choisit des produits pratiques et utiles, livrés partout en Tunisie et payés à la livraison.', 'famma-child' ); ?>
			</p>

			<ul class="famma-hero2__perks">
				<?php foreach ( $perks as $perk ) : ?>
					<li class="famma-hero2__perk">
						<?php famma_child_the_icon( $perk['icon'] ); ?>
						<span>
							<span class="famma-hero2__perk-title"><?php echo esc_html( $perk['title'] ); ?></span>
							<?php if ( '' !== $perk['sub'] ) : ?>
								<span class="famma-hero2__perk-sub"<?php echo 'خلص كي توصل' === $perk['sub'] ? ' lang="ar" dir="rtl"' : ''; ?>>
									<?php echo esc_html( $perk['sub'] ); ?>
								</span>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>

			<a class="famma-hero2__cta" href="<?php echo esc_url( (string) $shop_url ); ?>">
				<span><?php esc_html_e( 'Découvrir la boutique', 'famma-child' ); ?></span>
				<span class="famma-hero2__cta-sep" aria-hidden="true"></span>
				<svg class="famma-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
			</a>
		</div>

		<?php if ( $slides > 0 ) : ?>
			<div class="famma-hero2__media">
				<span class="famma-hero2__disc" aria-hidden="true"></span>
				<span class="famma-hero2__base" aria-hidden="true"></span>

				<div class="famma-hero2__slides"<?php echo $slides > 1 ? ' data-famma-hero' : ''; ?>>
					<?php foreach ( $products as $index => $product ) : ?>
						<a
							class="famma-hero2__slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( (string) $product->get_permalink() ); ?>"
							<?php echo 0 === $index ? '' : 'aria-hidden="true" tabindex="-1"'; ?>
						>
							<?php
							/*
							 * La première diapo est l'élément LCP de l'accueil mobile :
							 * priorité haute, jamais différée. Les suivantes sont
							 * cachées au chargement et ne doivent pas lui disputer
							 * la bande passante (audit perf du 23/09, PERF-09).
							 */
							$famma_hero_attr = 0 === $index
								? array(
									'loading'       => 'eager',
									'fetchpriority' => 'high',
								)
								: array(
									'loading'       => 'lazy',
									'fetchpriority' => 'low',
								);
							echo famma_child_kses_image( $product->get_image( 'woocommerce_single', $famma_hero_attr ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par famma_child_kses_image().
							?>
							<span class="screen-reader-text"><?php echo esc_html( $product->get_name() ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>

				<?php if ( $slides > 1 ) : ?>
					<div class="famma-hero2__dots" role="tablist" aria-label="<?php esc_attr_e( 'Visuels', 'famma-child' ); ?>">
						<?php for ( $i = 0; $i < $slides; $i++ ) : ?>
							<button
								type="button"
								class="famma-hero2__dot<?php echo 0 === $i ? ' is-active' : ''; ?>"
								data-famma-hero-dot="<?php echo esc_attr( (string) $i ); ?>"
								aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
								role="tab"
							>
								<span class="screen-reader-text">
									<?php
									printf(
										/* translators: %d: numéro du visuel. */
										esc_html__( 'Visuel %d', 'famma-child' ),
										(int) $i + 1
									);
									?>
								</span>
							</button>
						<?php endfor; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Carrousel des rayons du catalogue.
 *
 * Source : `famma_child_product_categories()`, la meme que la colonne de
 * filtres et que le sous-menu « Categories » de l'en-tete. La section listait
 * auparavant les termes non vides tries par nombre de produits : elle affichait
 * donc « Sans rayon » — le fourre-tout de WooCommerce — et cachait les rayons
 * encore sans produit, alors que ce sont justement ceux qu'un catalogue jeune a
 * besoin de montrer. Aucune categorie n'est creee ni suggeree ; la section
 * disparait si le catalogue n'en a aucune.
 *
 * Le carrousel est un defilement natif : la piste glisse au doigt sans
 * JavaScript, et `carousel.js` n'ajoute que les fleches, uniquement quand la
 * piste depasse vraiment. Les fleches sont donc `hidden` cote serveur — une
 * commande qui ne commande rien est pire que pas de commande.
 *
 * @return void
 */
function famma_child_home_categories(): void {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return;
	}

	$terms = famma_child_product_categories();

	if ( array() === $terms ) {
		return;
	}
	?>
	<section class="famma-home__section">
		<?php famma_child_home_title( __( 'Parcourez nos catégories', 'famma-child' ) ); ?>

		<div class="famma-cats" data-famma-carousel>
			<button
				class="famma-cats__nav famma-cats__nav--prev"
				type="button"
				data-famma-carousel-prev
				hidden
			>
				<?php famma_child_the_icon( 'chevron', 'famma-cats__nav-icon' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Catégories précédentes', 'famma-child' ); ?></span>
			</button>

			<ul class="famma-cats__track" data-famma-carousel-track>
				<?php
				foreach ( $terms as $term ) :
					$thumb_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
					?>
					<li class="famma-cats__item">
						<a class="famma-cats__card" href="<?php echo esc_url( famma_child_product_category_url( $term->slug ) ); ?>">
							<span class="famma-cats__media">
								<?php if ( $thumb_id ) : ?>
									<?php echo wp_get_attachment_image( $thumb_id, 'thumbnail', false, array( 'alt' => '' ) ); ?>
								<?php else : ?>
									<span class="famma-cats__mark" aria-hidden="true">
										<svg viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M12 2.6l2.9 5.9 6.5.9-4.7 4.6 1.1 6.4L12 17.4 6.2 20.4l1.1-6.4L2.6 9.4l6.5-.9z"/></svg>
									</span>
								<?php endif; ?>
							</span>
							<span class="famma-cats__name"><?php echo esc_html( $term->name ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<button
				class="famma-cats__nav famma-cats__nav--next"
				type="button"
				data-famma-carousel-next
				hidden
			>
				<?php famma_child_the_icon( 'chevron', 'famma-cats__nav-icon' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Catégories suivantes', 'famma-child' ); ?></span>
			</button>
		</div>
	</section>
	<?php
}

/**
 * « Nos nouveautés » — les derniers produits publiés.
 *
 * Rend la même carte que la boutique, via `wc_get_template_part( 'content',
 * 'product' )` : deux balisages de carte finiraient par diverger au premier
 * ajustement.
 *
 * @return void
 */
function famma_child_home_new_products(): void {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return;
	}

	$ids = get_transient( 'famma_home_new' );

	if ( ! is_array( $ids ) ) {
		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 5,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'ids',
			)
		);

		$ids = is_array( $products ) ? $products : array();
		set_transient( 'famma_home_new', $ids, FAMMA_CHILD_HOME_TTL );
	}

	if ( array() === $ids ) {
		return;
	}

	$query = new WP_Query(
		array(
			'post_type'           => 'product',
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $ids ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	if ( ! $query->have_posts() ) {
		return;
	}
	?>
	<section class="famma-home__section">
		<?php famma_child_home_title( __( 'Nos nouveautés', 'famma-child' ) ); ?>

		<div class="famma-home__grid woocommerce">
			<ul class="products famma-cards">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					wc_get_template_part( 'content', 'product' );
				endwhile;
				?>
			</ul>
		</div>
	</section>
	<?php
	wp_reset_postdata();
}

/**
 * « Pourquoi choisir FAMMA ? »
 *
 * Quatre affirmations vérifiables dans le code de la boutique, pas des
 * promesses : le paiement n'est encaissé qu'au statut « livré », la livraison
 * est offerte sur les 24 gouvernorats, le checkout ne demande pas de compte.
 * La quatrième ligne n'existe que si un numéro WhatsApp est configuré.
 *
 * @return void
 */
function famma_child_home_why(): void {
	$items = array(
		array(
			'icon'  => 'card',
			'title' => __( 'Vous payez à la réception', 'famma-child' ),
			'text'  => __( 'La commande n\'est réglée qu\'une fois livrée, en espèces, au livreur.', 'famma-child' ),
		),
		array(
			'icon'  => 'truck',
			'title' => __( 'Livraison offerte', 'famma-child' ),
			'text'  => __( 'Partout en Tunisie, dans les 24 gouvernorats, sans frais ajoutés.', 'famma-child' ),
		),
		array(
			'icon'  => 'user',
			'title' => __( 'Commande sans compte', 'famma-child' ),
			'text'  => __( 'Nom, téléphone, adresse. Aucun mot de passe à créer.', 'famma-child' ),
		),
	);

	if ( '' !== famma_child_whatsapp_number() ) {
		$items[] = array(
			'icon'  => 'chat',
			'title' => __( 'Une équipe joignable', 'famma-child' ),
			'text'  => __( 'Vos questions trouvent une réponse sur WhatsApp, avant comme après la commande.', 'famma-child' ),
		);
	}
	?>
	<section class="famma-home__section">
		<?php famma_child_home_title( __( 'Pourquoi choisir FAMMA ?', 'famma-child' ) ); ?>

		<ul class="famma-why">
			<?php foreach ( $items as $item ) : ?>
				<li class="famma-why__item">
					<span class="famma-why__mark"><?php famma_child_the_icon( $item['icon'] ); ?></span>
					<span class="famma-why__text">
						<span class="famma-why__title"><?php echo esc_html( $item['title'] ); ?></span>
						<span class="famma-why__sub"><?php echo esc_html( $item['text'] ); ?></span>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
}

/**
 * Agrégat des avis de la boutique.
 *
 * @return array{count: int, average: float}
 */
function famma_child_home_review_stats(): array {
	$cached = get_transient( 'famma_home_reviews' );

	if ( is_array( $cached ) && isset( $cached['count'], $cached['average'] ) ) {
		return array(
			'count'   => (int) $cached['count'],
			'average' => (float) $cached['average'],
		);
	}

	$ratings = get_comments(
		array(
			'post_type' => 'product',
			'status'    => 'approve',
			'parent'    => 0,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- requete lourde assumee : resultat mis en cache par transient (FAMMA_CHILD_HOME_TTL) et purge a chaque nouvel avis.
			'meta_key'  => 'rating',
			'fields'    => 'ids',
			'number'    => 500,
		)
	);

	$count = is_array( $ratings ) ? count( $ratings ) : 0;
	$sum   = 0;

	foreach ( (array) $ratings as $comment_id ) {
		$sum += (int) get_comment_meta( (int) $comment_id, 'rating', true );
	}

	$stats = array(
		'count'   => $count,
		'average' => $count > 0 ? round( $sum / $count, 1 ) : 0.0,
	);

	set_transient( 'famma_home_reviews', $stats, FAMMA_CHILD_HOME_TTL );

	return $stats;
}

/*
 * La section « Ils nous font confiance » vit dans inc/home-social.php, avec la
 * newsletter : ses textes sont désormais réglables dans FAMMA → Pages.
 */

/**
 * Vide les agrégats de l'accueil dès qu'un produit ou un avis change.
 *
 * Sans cette purge, un produit mis en ligne pourrait rester invisible un quart
 * d'heure — le pire moment pour un propriétaire qui vient de publier sa
 * première fiche et rafraîchit son accueil.
 *
 * @return void
 */
function famma_child_home_flush_cache(): void {
	delete_transient( 'famma_home_hero' );
	delete_transient( 'famma_home_new' );
	delete_transient( 'famma_home_reviews' );
}
add_action( 'save_post_product', 'famma_child_home_flush_cache' );
add_action( 'deleted_post', 'famma_child_home_flush_cache' );
add_action( 'woocommerce_new_product', 'famma_child_home_flush_cache' );
add_action( 'woocommerce_update_product', 'famma_child_home_flush_cache' );
add_action( 'comment_post', 'famma_child_home_flush_cache' );
add_action( 'edit_comment', 'famma_child_home_flush_cache' );
add_action( 'wp_set_comment_status', 'famma_child_home_flush_cache' );

/**
 * Masque le bloc « Les premiers produits arrivent » dès qu'un produit existe.
 *
 * Ce bloc (`famma-soon`, posé par scripts/create-homepage.php) annonce un
 * catalogue fermé ; affiché sous 16 produits en vente, il faisait croire que
 * la boutique n'était pas ouverte (audit du 23/09, UX-10). Il reste dans le
 * contenu de la page, modifiable depuis l'éditeur, et réapparaît de lui-même
 * si le catalogue redevient vide.
 *
 * @param string               $content Rendu du bloc.
 * @param array<string, mixed> $block   Bloc analysé.
 * @return string
 */
function famma_child_hide_soon_block( $content, $block ) {
	if ( ! is_front_page() || 'core/group' !== ( $block['blockName'] ?? '' ) ) {
		return $content;
	}

	$classes = explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) );

	if ( ! in_array( 'famma-soon', $classes, true ) ) {
		return $content;
	}

	return (int) wp_count_posts( 'product' )->publish > 0 ? '' : $content;
}
add_filter( 'render_block', 'famma_child_hide_soon_block', 10, 2 );
