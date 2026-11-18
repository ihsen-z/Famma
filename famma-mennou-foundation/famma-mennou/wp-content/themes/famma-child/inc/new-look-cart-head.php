<?php
/**
 * Nouveau look — panier, case `cart_look`, suite de inc/new-look-cart.php
 * (correctifs du 18/11, comparaison prod / maquette du panier) :
 * - la mascotte à côté de « Mon panier » et le nombre d'articles en pastille ;
 * - les promesses du récapitulatif dans la langue de la page, avec leur icône ;
 * - le bouton « Écrire sur WhatsApp » dans l'encart d'aide.
 *
 * Textes : FAMMA → Nouveau look → Panier (`Famma\Core\Cart_Content`). Case
 * décochée : rien ne change.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Mascotte à côté de « Mon panier » (étape 1 du parcours seulement).
 *
 * @param int $step Étape du parcours.
 * @return void
 */
function famma_child_cart_title_mascot( $step ): void {
	if ( 1 !== (int) $step || ! famma_child_cart_part( 'cart_look' ) || ! function_exists( 'famma_child_account_mascot' ) ) {
		return;
	}

	echo '<span class="famma-cart-mascot">' . famma_child_account_mascot() . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passé par wp_kses() dans famma_child_account_mascot().
}
add_action( 'famma_child_tunnel_before_title', 'famma_child_cart_title_mascot' );

/**
 * Nombre d'articles en pastille courte : « 4 articles ». Panier vide : rien
 * (le panier vide a son propre titre).
 *
 * @param string $label Libellé du thème.
 * @param int    $count Nombre d'articles.
 * @return string
 */
function famma_child_cart_count_chip( $label, $count ): string {
	if ( ! famma_child_cart_part( 'cart_look' ) ) {
		return (string) $label;
	}

	$count = (int) $count;

	if ( 0 === $count ) {
		return '';
	}

	if ( 1 === $count ) {
		$one = famma_child_cart_text( 'count_one' );

		return '' !== $one ? $one : (string) $label;
	}

	$many = famma_child_cart_text( 'count_many' );

	return '' !== $many ? str_replace( '{n}', (string) $count, $many ) : (string) $label;
}
add_filter( 'famma_child_cart_count_label', 'famma_child_cart_count_chip', 10, 2 );

/**
 * Modèle de la pastille pour le script du panier (`%d` à la place de `{n}`).
 *
 * @param string $many Modèle du thème.
 * @return string
 */
function famma_child_cart_count_chip_many( $many ): string {
	if ( ! famma_child_cart_part( 'cart_look' ) ) {
		return (string) $many;
	}

	$text = famma_child_cart_text( 'count_many' );

	return '' !== $text ? str_replace( '{n}', '%d', $text ) : (string) $many;
}
add_filter( 'famma_child_cart_count_many', 'famma_child_cart_count_chip_many' );

/**
 * Promesses du récapitulatif, dans la langue de la page et avec leur icône.
 *
 * La liste commune (`famma_child_reassurance_list()`) ajoute la ligne derja
 * sous chaque ligne française : sur la page française du panier, la maquette
 * n'en montre qu'une. Mêmes textes, réglés dans FAMMA → Pages.
 *
 * @return void
 */
function famma_child_cart_reassurance_look(): void {
	$items = famma_child_reassurance_items();

	if ( empty( $items ) ) {
		return;
	}

	$svg = famma_child_svg_allowed_html();

	echo '<ul class="famma-reassure famma-reassure--tunnel famma-reassure--look">';

	foreach ( $items as $item ) {
		echo '<li class="famma-reassure__item">';
		echo wp_kses( famma_child_icon( $item['icon'] ?? 'check', 'famma-reassure__icon' ), $svg );
		echo '<span>' . esc_html( $item['fr'] ) . '</span></li>';
	}

	echo '</ul>';
}

/**
 * Remplace la liste bilingue du récapitulatif quand la case est cochée.
 *
 * Priorité 12 sur `wp` : après `famma_child_cart_swap_checkout_button()` (10),
 * qui l'a posée.
 *
 * @return void
 */
function famma_child_cart_swap_reassurance(): void {
	if ( ! famma_child_cart_part( 'cart_look' ) || ! function_exists( 'famma_child_reassurance_items' ) ) {
		return;
	}

	if ( remove_action( 'woocommerce_proceed_to_checkout', 'famma_child_tunnel_reassurance', 30 ) ) {
		add_action( 'woocommerce_proceed_to_checkout', 'famma_child_cart_reassurance_look', 30 );
	}
}
add_action( 'wp', 'famma_child_cart_swap_reassurance', 12 );

/**
 * Bouton « Écrire sur WhatsApp » dans l'encart d'aide du panier.
 *
 * Un simple libellé en forme de bouton : tout l'encart est déjà le lien.
 *
 * @return void
 */
function famma_child_cart_help_cta(): void {
	if ( ! famma_child_cart_part( 'cart_look' ) ) {
		return;
	}

	$cta = famma_child_cart_text( 'help_cta' );

	if ( '' !== $cta ) {
		printf( '<span class="famma-tunnel-help__cta">%s</span>', esc_html( $cta ) );
	}
}
add_action( 'famma_child_tunnel_help_end', 'famma_child_cart_help_cta' );
