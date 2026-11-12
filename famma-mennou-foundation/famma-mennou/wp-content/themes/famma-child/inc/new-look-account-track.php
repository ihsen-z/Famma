<?php
/**
 * Nouveau look — suivi de commande en 4 étapes dans « Mon compte ».
 *
 * Case `acct_track` (lot 3 du plan de portage de l'espace client). La règle
 * « statut → étape », les libellés et « Recommander » vivent dans famma-core
 * (`Famma\Core\Order_Track`) ; ce fichier ne fait que dessiner :
 *
 * - dans la liste des commandes, une barre de 4 segments sous le statut ;
 * - en tête du détail d'une commande, la frise Reçue → Confirmée →
 *   Expédiée → Livrée et la somme à payer au livreur ; une commande sortie
 *   du parcours (refusée, retournée, annulée…) montre son statut à la place.
 *
 * Aucun modèle WooCommerce n'est copié. Case décochée ou plugin absent :
 * aucun crochet n'est posé, la page reste celle de WooCommerce.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La page courante reçoit-elle le suivi ?
 *
 * @return bool
 */
function famma_child_account_track(): bool {
	return class_exists( '\Famma\Core\Order_Track' )
		&& function_exists( 'is_account_page' )
		&& is_account_page()
		&& famma_child_new_look( 'acct_track' );
}

/**
 * Pose les crochets d'affichage, seulement quand la case est cochée.
 *
 * Le modèle de la liste remplace le statut par défaut dès qu'un crochet
 * existe sur sa colonne : il ne doit exister que case cochée.
 *
 * @return void
 */
function famma_child_account_track_setup(): void {
	if ( ! famma_child_account_track() ) {
		return;
	}

	add_action( 'woocommerce_my_account_my_orders_column_order-status', 'famma_child_account_track_list_status' );
	add_action( 'woocommerce_view_order', 'famma_child_account_track_detail', 5 );
	add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_track', 25 );
}
add_action( 'template_redirect', 'famma_child_account_track_setup' );

/**
 * Feuille du suivi.
 *
 * @return void
 */
function famma_child_enqueue_account_track(): void {
	$css = get_stylesheet_directory() . '/assets/css/new-look-account-track.css';

	if ( ! file_exists( $css ) ) {
		return;
	}

	wp_enqueue_style(
		'famma-new-look-account-track',
		get_stylesheet_directory_uri() . '/assets/css/new-look-account-track.css',
		array( 'famma-tokens' ),
		(string) filemtime( $css )
	);
}

/**
 * Liste des commandes : le statut, puis la barre des 4 étapes.
 *
 * La barre est décorative (`aria-hidden`) : le statut écrit juste avant dit
 * déjà où en est la commande.
 *
 * @param WC_Order $order Commande de la ligne.
 * @return void
 */
function famma_child_account_track_list_status( $order ): void {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$step = \Famma\Core\Order_Track::step( (string) $order->get_status() );

	echo '<span class="famma-track-status">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span>';

	if ( 0 === $step ) {
		return;
	}

	printf(
		'<span class="famma-track-mini is-step-%1$d" aria-hidden="true"><i></i><i></i><i></i><i></i></span>',
		(int) $step
	);
}

/**
 * Détail d'une commande : la frise, avant le tableau des articles.
 *
 * WooCommerce a déjà vérifié que la commande appartient au client
 * (`view_order`) avant de lancer ce crochet.
 *
 * @param int $order_id Identifiant de la commande.
 * @return void
 */
function famma_child_account_track_detail( $order_id ): void {
	$order = wc_get_order( absint( $order_id ) );

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$view  = \Famma\Core\Order_Track::view( $order );
	$step  = (int) $view['step'];
	$icons = array(
		1 => 'box',
		2 => 'phone',
		3 => 'truck',
		4 => 'home',
	);
	$svg   = famma_child_svg_allowed_html();

	echo '<section class="famma-track">';

	if ( 0 === $step ) {
		echo '<p class="famma-track__off">' . esc_html( $view['status'] ) . '</p>';
		echo '</section>';
		return;
	}

	$done = \Famma\Core\Order_Track::STEPS === $step;

	printf(
		'<ol class="famma-track__tl is-step-%1$d%2$s" aria-label="%3$s">',
		(int) $step,
		$done ? ' is-done' : '',
		esc_attr( $view['status'] )
	);

	foreach ( $view['steps'] as $i => $row ) {
		$classes = array();

		if ( $i <= $step ) {
			$classes[] = 'is-on';
		}

		if ( $i === $step && ! $done ) {
			$classes[] = 'is-cur';
		}

		echo '<li class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( $i === $step ? ' aria-current="step"' : '' ) . '>';
		echo '<span class="famma-track__dot">' . wp_kses( famma_child_icon( $icons[ $i ] ?? 'check' ), $svg ) . '</span>';
		echo '<b>' . esc_html( $row['label'] ) . '</b>';

		if ( '' !== $row['date'] ) {
			echo '<small>' . esc_html( $row['date'] ) . '</small>';
		}

		echo '</li>';
	}

	echo '</ol>';

	if ( '' !== $view['due'] ) {
		echo '<p class="famma-track__due">' . wp_kses_post( $view['due'] ) . '</p>';
	}

	echo '</section>';
}
