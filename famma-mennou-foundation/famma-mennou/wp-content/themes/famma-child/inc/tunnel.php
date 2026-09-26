<?php
/**
 * Parcours d'achat — en-tête commun au panier, à la commande et à la confirmation.
 *
 * Maquettes « FAMMA Panier » et « FAMMA Commande » : les trois écrans partagent
 * un fil d'Ariane, un indicateur d'étapes (Panier → Commande → Confirmation),
 * un titre, un encart WhatsApp et la même liste de réassurance. Ce fichier les
 * rend une seule fois ; `cart.php` et `checkout-layout.php` ne gardent que ce
 * qui est propre à leur écran.
 *
 * Le bandeau de titre gris de Kadence est remplacé, pas masqué : le masquer en
 * CSS laissait un second `<h1>` dans le document (même raison que l'accueil,
 * cf. `famma_child_remove_front_page_hero()`).
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Étape du parcours affichée par la page courante.
 *
 * @return int 1 panier, 2 commande, 3 confirmation, 0 hors parcours.
 */
function famma_child_tunnel_step(): int {
	if ( ! function_exists( 'is_cart' ) ) {
		return 0;
	}

	if ( is_cart() ) {
		return 1;
	}

	if ( is_order_received_page() ) {
		return 3;
	}

	if ( is_checkout() && ! is_checkout_pay_page() ) {
		return 2;
	}

	return 0;
}

/**
 * Remplace le bandeau de titre de Kadence par l'en-tête du parcours.
 *
 * Accroché sur `wp` : la requête est résolue, donc `is_cart()` répond juste,
 * et le gabarit n'a pas encore été rendu.
 *
 * @return void
 */
function famma_child_tunnel_swap_hero(): void {
	if ( 0 === famma_child_tunnel_step() ) {
		return;
	}

	remove_action( 'kadence_hero_header', 'Kadence\hero_title' );
	add_action( 'kadence_hero_header', 'famma_child_tunnel_header' );
}
add_action( 'wp', 'famma_child_tunnel_swap_hero' );

/**
 * Classe de portée pour les feuilles du parcours.
 *
 * @param array<int, string> $classes Classes du `<body>`.
 * @return array<int, string>
 */
function famma_child_tunnel_body_class( array $classes ): array {
	if ( 0 !== famma_child_tunnel_step() ) {
		$classes[] = 'famma-tunnel';
	}

	return $classes;
}
add_filter( 'body_class', 'famma_child_tunnel_body_class' );

/**
 * Pas de bouton WhatsApp flottant là où l'encart WhatsApp du parcours s'affiche.
 *
 * Sur le panier rempli et la commande, le bouton de 56 px recouvrait les
 * champs et les cartes au moment de valider, alors que l'encart
 * (`famma_child_tunnel_help()`) est déjà dans la page (audit du 25/09, UX-49).
 * Le panier vide et la confirmation n'ont pas d'encart : le bouton y reste.
 *
 * @param bool $show Réponse reçue de famma-core.
 * @return bool
 */
function famma_child_tunnel_whatsapp_float( $show ): bool {
	$step = famma_child_tunnel_step();

	if ( 2 === $step || ( 1 === $step && WC()->cart && ! WC()->cart->is_empty() ) ) {
		return false;
	}

	return (bool) $show;
}
add_filter( 'famma_whatsapp_show_float', 'famma_child_tunnel_whatsapp_float' );

/**
 * Libellé du nombre d'articles, au singulier ou au pluriel.
 *
 * @param int $count Nombre d'articles (quantités cumulées).
 * @return string
 */
function famma_child_cart_count_label( int $count ): string {
	if ( 0 === $count ) {
		return __( 'Votre panier est vide', 'famma-child' );
	}

	/* translators: %d: nombre d'articles dans le panier. */
	return sprintf( _n( '%d article dans votre panier', '%d articles dans votre panier', $count, 'famma-child' ), $count );
}

/**
 * Rend l'en-tête du parcours : fil d'Ariane, étapes, titre.
 *
 * @return void
 */
function famma_child_tunnel_header(): void {
	$step = famma_child_tunnel_step();

	$crumbs = array(
		array(
			'label' => __( 'Accueil', 'famma-child' ),
			'url'   => home_url( '/' ),
		),
	);

	if ( 1 === $step ) {
		$title = __( 'Mon panier', 'famma-child' );
	} elseif ( 2 === $step ) {
		$title    = __( 'Finaliser ma commande', 'famma-child' );
		$crumbs[] = array(
			'label' => __( 'Panier', 'famma-child' ),
			'url'   => wc_get_cart_url(),
		);
	} else {
		$title = __( 'Commande reçue, merci !', 'famma-child' );
	}

	$crumbs[] = array(
		'label' => $title,
		'url'   => '',
	);
	?>
	<div class="famma-tunnel-head site-container">
		<nav class="famma-tunnel-head__crumbs" aria-label="<?php esc_attr_e( 'Fil d’Ariane', 'famma-child' ); ?>">
			<ol>
				<?php foreach ( $crumbs as $crumb ) : ?>
					<li>
						<?php if ( '' !== $crumb['url'] ) : ?>
							<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
						<?php else : ?>
							<span aria-current="page"><?php echo esc_html( $crumb['label'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>

		<?php famma_child_tunnel_steps( $step ); ?>

		<h1 class="famma-tunnel-head__title"><?php echo esc_html( $title ); ?></h1>

		<?php if ( 1 === $step ) : ?>
			<?php $count = WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0; ?>
			<p
				class="famma-tunnel-head__sub"
				data-famma-cart-count
				data-empty="<?php echo esc_attr( famma_child_cart_count_label( 0 ) ); ?>"
				data-one="<?php echo esc_attr( famma_child_cart_count_label( 1 ) ); ?>"
				<?php /* translators: garder %d : le script y place le nombre d'articles. */ ?>
				data-many="<?php echo esc_attr( _n( '%d article dans votre panier', '%d articles dans votre panier', 3, 'famma-child' ) ); ?>"
			><?php echo esc_html( famma_child_cart_count_label( $count ) ); ?></p>
		<?php elseif ( 2 === $step ) : ?>
			<p class="famma-tunnel-head__sub"><?php esc_html_e( 'Remplissez vos coordonnées : nous vous appelons pour confirmer la commande avant l’expédition.', 'famma-child' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Indicateur d'étapes Panier → Commande → Confirmation.
 *
 * Une étape passée est cochée en vert, l'étape courante est orange et porte
 * `aria-current="step"` : l'état ne repose pas sur la seule couleur.
 *
 * @param int $current Étape courante (1 à 3).
 * @return void
 */
function famma_child_tunnel_steps( int $current ): void {
	$labels = array(
		1 => __( 'Panier', 'famma-child' ),
		2 => __( 'Commande', 'famma-child' ),
		3 => __( 'Confirmation', 'famma-child' ),
	);

	echo '<ol class="famma-steps">';

	foreach ( $labels as $number => $label ) {
		$state = 'todo';

		if ( $number < $current ) {
			$state = 'done';
		} elseif ( $number === $current ) {
			$state = 'current';
		}

		printf(
			'<li class="famma-steps__item is-%1$s"%2$s><span class="famma-steps__dot">%3$s</span><span class="famma-steps__label">%4$s</span></li>',
			esc_attr( $state ),
			'current' === $state ? ' aria-current="step"' : '',
			'done' === $state ? wp_kses( famma_child_icon( 'check' ), famma_child_svg_allowed_html() ) : esc_html( (string) $number ),
			esc_html( $label )
		);
	}

	echo '</ol>';
}

/**
 * Réassurance du parcours, sous le bouton d'action.
 *
 * Les lignes sont celles de l'administration (`famma_child_reassurance_items()`) :
 * rien n'est promis ici qui ne le soit déjà sur la fiche produit.
 *
 * @return void
 */
function famma_child_tunnel_reassurance(): void {
	$items = famma_child_reassurance_items();

	if ( empty( $items ) ) {
		return;
	}

	famma_child_reassurance_list( $items, 'famma-reassure--tunnel' );
}

/**
 * Encart WhatsApp du parcours.
 *
 * Rien tant qu'aucun numéro n'est configuré : pas de lien mort (§15, §60).
 *
 * @param string $title Titre de l'encart.
 * @return void
 */
function famma_child_tunnel_help( string $title ): void {
	if ( ! class_exists( '\Famma\Core\WhatsApp' ) ) {
		return;
	}

	$href = \Famma\Core\WhatsApp::link();

	if ( '' === $href ) {
		return;
	}
	?>
	<a class="famma-tunnel-help" href="<?php echo esc_url( $href ); ?>" target="_blank" rel="noopener nofollow">
		<span class="famma-tunnel-help__icon"><?php echo wp_kses( famma_child_icon( 'chat' ), famma_child_svg_allowed_html() ); ?></span>
		<span class="famma-tunnel-help__body">
			<span class="famma-tunnel-help__title"><?php echo esc_html( $title ); ?></span>
			<span class="famma-tunnel-help__sub"><?php esc_html_e( 'Écrivez-nous sur WhatsApp.', 'famma-child' ); ?></span>
		</span>
	</a>
	<?php
}

/**
 * Charge la feuille commune du parcours.
 *
 * @return void
 */
function famma_child_enqueue_tunnel(): void {
	if ( 0 === famma_child_tunnel_step() ) {
		return;
	}

	$path = get_stylesheet_directory() . '/assets/css/tunnel.css';

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_style(
		'famma-tunnel',
		get_stylesheet_directory_uri() . '/assets/css/tunnel.css',
		array( 'famma-layout' ),
		(string) filemtime( $path )
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_tunnel', 22 );

/**
 * « Livraison » plutôt que « Expédition » dans les totaux, comme la maquette.
 *
 * @param string $name Nom du colis affiché par WooCommerce.
 * @return string
 */
function famma_child_tunnel_package_name( $name ): string {
	return 0 !== famma_child_tunnel_step() ? __( 'Livraison', 'famma-child' ) : (string) $name;
}
add_filter( 'woocommerce_shipping_package_name', 'famma_child_tunnel_package_name' );
