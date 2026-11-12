<?php
/**
 * Nouveau look — commandes en cartes dans « Mon compte », comme la maquette.
 *
 * Case `acct_track` (lot 3 du plan de portage de l'espace client). La règle
 * « statut → étape », les heures des étapes, les libellés et « Recommander »
 * vivent dans famma-core (`Famma\Core\Order_Track::view()`) ; ce fichier ne
 * fait que dessiner :
 *
 * - « Mes commandes » : une carte par commande (vignettes, pastille « En
 *   route » ou « Livrée », total et paiement, frise des 4 étapes avec leur
 *   heure, rappel pour le livreur, détail dépliable avec les articles, le
 *   récapitulatif, le lieu et les boutons) ;
 * - le détail d'une commande : la même carte, ouverte, à la place du tableau
 *   de WooCommerce ; une commande sortie du parcours (refusée, retournée,
 *   annulée…) montre son statut à la place de la frise.
 *
 * Aucun modèle WooCommerce n'est copié : la requête des commandes et sa
 * pagination restent celles de WooCommerce, et sans commande la page affiche
 * le message de WooCommerce. Case décochée ou plugin absent : aucun crochet
 * n'est posé, la page reste celle de WooCommerce.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La page courante reçoit-elle les cartes ?
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
 * @return void
 */
function famma_child_account_track_setup(): void {
	if ( ! famma_child_account_track() ) {
		return;
	}

	remove_action( 'woocommerce_account_orders_endpoint', 'woocommerce_account_orders' );
	add_action( 'woocommerce_account_orders_endpoint', 'famma_child_account_track_orders' );

	remove_action( 'woocommerce_view_order', 'woocommerce_order_details_table', 10 );
	add_action( 'woocommerce_view_order', 'famma_child_account_track_detail', 10 );
	add_filter( 'woocommerce_order_details_status', '__return_empty_string' );

	add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_track', 25 );
}
add_action( 'template_redirect', 'famma_child_account_track_setup' );

/**
 * Feuille des cartes.
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
 * « Mes commandes » : les cartes, puis la pagination de WooCommerce.
 *
 * Même requête que WooCommerce (même filtre), donc mêmes commandes et même
 * découpage en pages. Sans commande, le message de WooCommerce reste.
 *
 * @param int|string $current_page Page demandée.
 * @return void
 */
function famma_child_account_track_orders( $current_page ): void {
	$current_page = empty( $current_page ) ? 1 : absint( $current_page );
	$query        = array(
		'customer' => get_current_user_id(),
		'page'     => $current_page,
		'paginate' => true,
	);
	// Filtre de WooCommerce relu tel quel : même liste que son tableau.
	$query  = apply_filters( 'woocommerce_my_account_my_orders_query', $query ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	$orders = wc_get_orders( $query );

	if ( ! is_object( $orders ) || empty( $orders->total ) ) {
		woocommerce_account_orders( $current_page );
		return;
	}

	// Crochets de WooCommerce gardés pour les extensions qui s'y accrochent.
	do_action( 'woocommerce_before_account_orders', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

	$title = class_exists( '\Famma\Core\Account_Profile' ) ? \Famma\Core\Account_Profile::text( 'tab_orders' ) : '';

	echo '<div class="famma-ocs">';

	if ( '' !== $title ) {
		echo '<h2 class="famma-ocs__h">' . esc_html( $title ) . '</h2>';
	}

	$i = 0;
	foreach ( $orders->orders as $order ) {
		$order = wc_get_order( $order );

		if ( $order instanceof WC_Order ) {
			famma_child_account_track_card( $order, $i, false );
			++$i;
		}
	}

	echo '</div>';

	do_action( 'woocommerce_before_account_orders_pagination' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

	$pages = (int) $orders->max_num_pages;

	if ( $pages > 1 ) {
		echo '<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">';

		if ( 1 !== $current_page ) {
			printf(
				'<a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button" href="%1$s">%2$s</a>',
				esc_url( wc_get_endpoint_url( 'orders', (string) ( $current_page - 1 ) ) ),
				esc_html__( 'Previous', 'woocommerce' ) // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
			);
		}

		if ( $pages !== $current_page ) {
			printf(
				'<a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button" href="%1$s">%2$s</a>',
				esc_url( wc_get_endpoint_url( 'orders', (string) ( $current_page + 1 ) ) ),
				esc_html__( 'Next', 'woocommerce' ) // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
			);
		}

		echo '</div>';
	}

	do_action( 'woocommerce_after_account_orders', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
}

/**
 * Détail d'une commande : la carte, ouverte.
 *
 * WooCommerce a déjà vérifié que la commande appartient au client
 * (`view_order`) avant de lancer ce crochet.
 *
 * @param int $order_id Identifiant de la commande.
 * @return void
 */
function famma_child_account_track_detail( $order_id ): void {
	$order = wc_get_order( absint( $order_id ) );

	if ( $order instanceof WC_Order ) {
		famma_child_account_track_card( $order, 0, true );
	}
}

/**
 * Une carte de commande.
 *
 * @param WC_Order $order Commande.
 * @param int      $index Rang dans la liste (décalage de l'apparition).
 * @param bool     $open  Détail déplié d'emblée (page d'une commande).
 * @return void
 */
function famma_child_account_track_card( WC_Order $order, int $index, bool $open ): void {
	$view  = \Famma\Core\Order_Track::view( $order );
	$step  = (int) $view['step'];
	$done  = (bool) $view['done'];
	$svg   = famma_child_svg_allowed_html();
	$id    = 'famma-od-' . $order->get_id();
	$more  = \Famma\Core\Order_Track::text( 'card_more' );
	$less  = \Famma\Core\Order_Track::text( 'card_less' );
	$icons = array(
		1 => 'box',
		2 => 'phone',
		3 => 'truck',
		4 => 'home',
	);

	printf(
		'<article class="famma-oc%1$s" style="--i:%2$d">',
		$done ? ' is-done' : '',
		(int) min( $index, 8 )
	);

	// En-tête : vignettes, numéro et date, pastille, total et paiement.
	echo '<div class="famma-oc__h"><span class="famma-oc__thumbs">';
	foreach ( array_slice( $view['items'], 0, 3 ) as $item ) {
		printf( '<img src="%s" alt="" width="52" height="52" loading="lazy" decoding="async">', esc_url( $item['image'] ) );
	}
	echo '</span>';

	printf(
		'<span class="famma-oc__m"><b>%1$s</b><small>%2$s</small></span>',
		esc_html( $view['title'] ),
		esc_html( implode( ' · ', array_filter( array( $view['date'], $view['count'] ) ) ) )
	);

	echo '<span class="famma-oc__r">';
	printf(
		'<span class="famma-oc__st is-%1$s"><i aria-hidden="true"></i>%2$s</span>',
		esc_attr( sanitize_html_class( (string) $view['pill'] ) ),
		esc_html( $view['pill_label'] )
	);
	echo '<b>' . wp_kses_post( $view['total_html'] ) . '</b>';

	if ( '' !== $view['pay'] ) {
		echo '<span class="famma-oc__pay">' . ( $done ? '<em>' . esc_html( $view['pay'] ) . '</em>' : esc_html( $view['pay'] ) ) . '</span>';
	}

	echo '</span></div>';

	// Frise des 4 étapes ; la ligne se remplit à l'écran (script).
	if ( $step > 0 ) {
		printf(
			'<ol class="famma-oc__tl%1$s" style="--p:%2$s" aria-label="%3$s">',
			$done ? ' is-done' : '',
			esc_attr( (string) round( ( $step - 1 ) / ( \Famma\Core\Order_Track::STEPS - 1 ), 3 ) ),
			esc_attr( $view['status'] )
		);

		$k = 0;
		foreach ( $view['steps'] as $i => $row ) {
			$on      = $i <= $step;
			$classes = array();

			if ( $on ) {
				$classes[] = 'is-on';
			}

			if ( $i === $step && ! $done ) {
				$classes[] = 'is-cur';
			}

			printf(
				'<li class="%1$s"%2$s%3$s><span class="famma-oc__dot">%4$s</span><b>%5$s</b>%6$s</li>',
				esc_attr( implode( ' ', $classes ) ),
				$on ? ' style="--k:' . (int) $k . '"' : '',
				$i === $step ? ' aria-current="step"' : '',
				wp_kses( famma_child_icon( $icons[ $i ] ?? 'check' ), $svg ),
				esc_html( $row['label'] ),
				'' !== $row['date'] ? '<small>' . esc_html( $row['date'] ) . '</small>' : ''
			);

			if ( $on ) {
				++$k;
			}
		}

		echo '</ol>';
	}

	if ( '' !== $view['note_html'] ) {
		printf(
			'<p class="famma-oc__note">%1$s<span>%2$s</span></p>',
			wp_kses( famma_child_icon( $done ? 'check' : 'card' ), $svg ),
			wp_kses_post( $view['note_html'] )
		);
	}

	// Détail dépliable.
	if ( '' !== $more ) {
		printf(
			'<button class="famma-oc__more" type="button" aria-expanded="%1$s" aria-controls="%2$s" data-more="%3$s" data-less="%4$s"><span>%5$s</span>%6$s</button>',
			$open ? 'true' : 'false',
			esc_attr( $id ),
			esc_attr( $more ),
			esc_attr( '' !== $less ? $less : $more ),
			esc_html( $open && '' !== $less ? $less : $more ),
			wp_kses( famma_child_icon( 'chevron' ), $svg )
		);
	}

	printf( '<div class="famma-oc__d%1$s" id="%2$s"><div><div class="famma-oc__in">', $open || '' === $more ? ' is-open' : '', esc_attr( $id ) );

	echo '<div class="famma-oc__items">';
	foreach ( $view['items'] as $item ) {
		printf(
			'<div class="famma-oc__item"><img src="%1$s" alt="" width="48" height="48" loading="lazy" decoding="async"><span dir="auto">%2$s<small>× %3$d</small></span><b>%4$s</b></div>',
			esc_url( $item['image'] ),
			esc_html( $item['name'] ),
			(int) $item['qty'],
			wp_kses_post( $item['total_html'] )
		);
	}
	echo '</div>';

	echo '<div class="famma-oc__sum">';
	foreach ( $view['sums'] as $row ) {
		printf(
			'<div class="is-%1$s"><span>%2$s</span><span>%3$s</span></div>',
			esc_attr( $row['kind'] ),
			esc_html( $row['label'] ),
			wp_kses_post( $row['value_html'] )
		);
	}
	echo '</div>';

	if ( '' !== $view['place'] ) {
		printf(
			'<p class="famma-oc__place">%1$s<span>%2$s</span></p>',
			wp_kses( famma_child_icon( 'pin' ), $svg ),
			esc_html( $view['place'] )
		);
	}

	if ( array() !== $view['actions'] ) {
		echo '<div class="famma-oc__acts">';

		foreach ( $view['actions'] as $action ) {
			$key   = (string) $action['key'];
			$class = 'order-again' === $key ? 'button famma-oc__btn is-cta' : 'button famma-oc__btn is-line';
			$icon  = '';

			if ( 'order-again' === $key ) {
				$icon = famma_child_icon( 'redo' );
			} elseif ( 'ask' === $key ) {
				$icon = famma_child_icon( 'whatsapp' );
			}

			printf(
				'<a class="%1$s %2$s" href="%3$s"%4$s>%5$s<span>%6$s</span></a>',
				esc_attr( $class ),
				esc_attr( 'is-' . sanitize_html_class( $key ) ),
				esc_url( $action['url'] ),
				'ask' === $key ? ' target="_blank" rel="noopener"' : '',
				wp_kses( $icon, $svg ),
				esc_html( $action['name'] )
			);
		}

		echo '</div>';
	}

	echo '</div></div></div></article>';
}
