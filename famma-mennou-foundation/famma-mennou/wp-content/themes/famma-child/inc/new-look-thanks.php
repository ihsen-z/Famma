<?php
/**
 * Nouveau look — page merci, lot 51 : le plan de la maquette (`pdp_thanks`).
 *
 * Après une commande payée à la livraison, le bloc « Et maintenant ? »
 * (`famma_child_cod_next_steps()`) prend la forme de la fenêtre de la
 * maquette : « Payé aujourd'hui 0 DT », puis trois étapes numérotées qui
 * montent l'une après l'autre, dans la langue de la page seulement. Textes
 * dans FAMMA → Nouveau look → Fiche produit ; une étape vidée n'est pas
 * affichée. Le numéro à appeler, la correction par WhatsApp et l'offre après
 * achat (`woocommerce_before_thankyou`) ne changent pas.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La brique est-elle servie sur cette page ?
 *
 * @return bool
 */
function famma_child_thanks_on(): bool {
	return function_exists( 'is_order_received_page' ) && is_order_received_page()
		&& function_exists( 'famma_child_new_look' ) && famma_child_new_look( 'pdp_thanks' )
		&& class_exists( '\Famma\Core\Product_Content' );
}

/**
 * Remplace le bloc « Et maintenant ? » par celui de la maquette.
 *
 * @return void
 */
function famma_child_thanks_setup(): void {
	if ( ! famma_child_thanks_on() ) {
		return;
	}

	remove_action( 'woocommerce_thankyou_cod', 'famma_child_cod_next_steps' );
	add_action( 'woocommerce_thankyou_cod', 'famma_child_thanks_plan' );
}
add_action( 'wp', 'famma_child_thanks_setup', 20 );

/**
 * Feuille de la page merci, seulement avec la brique.
 *
 * @return void
 */
function famma_child_thanks_enqueue(): void {
	$file = '/assets/css/new-look-thanks.css';

	if ( ! famma_child_thanks_on() || ! file_exists( get_stylesheet_directory() . $file ) ) {
		return;
	}

	wp_enqueue_style(
		'famma-new-look-thanks',
		get_stylesheet_directory_uri() . $file,
		wp_style_is( 'famma-layout', 'registered' ) ? array( 'famma-layout' ) : array(),
		(string) filemtime( get_stylesheet_directory() . $file )
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_thanks_enqueue', 30 );

/**
 * Bloc « Et maintenant ? » de la maquette.
 *
 * @param int $order_id Identifiant de la commande.
 * @return void
 */
function famma_child_thanks_plan( $order_id ): void {
	$order = wc_get_order( $order_id );

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$text   = static function ( string $key ): string {
		return \Famma\Core\Product_Content::text( $key );
	};
	$price  = array( 'currency' => $order->get_currency() );
	$amount = wp_strip_all_tags( wc_price( $order->get_total(), $price ) );
	$title  = $text( 'thx_title' );
	$today  = $text( 'thx_today' );
	$steps  = array();
	$said   = false;

	foreach ( array( 'thx_step1', 'thx_step2', 'thx_step3' ) as $key ) {
		$step = $text( $key );

		if ( '' !== $step ) {
			$said    = $said || false !== strpos( $step, '{montant}' );
			$steps[] = str_replace( '{montant}', $amount, $step );
		}
	}

	echo '<section class="famma-confirm famma-thx">';

	if ( '' !== $title ) {
		printf( '<h2 class="famma-confirm__title">%s</h2>', esc_html( $title ) );
	}

	// Paiement à la livraison : rien n'est payé avant le livreur, c'est le seul cas où ce bloc s'affiche.
	if ( '' !== $today ) {
		printf(
			'<p class="famma-thx__today"><span>%1$s</span><b>%2$s</b></p>',
			esc_html( $today ),
			esc_html( wp_strip_all_tags( wc_price( 0, $price + array( 'decimals' => 0 ) ) ) )
		);
	}

	if ( array() !== $steps ) {
		echo '<ol class="famma-thx__plan">';
		foreach ( $steps as $index => $step ) {
			printf( '<li><i aria-hidden="true">%1$d</i><span>%2$s</span></li>', (int) $index + 1, esc_html( $step ) );
		}
		echo '</ol>';
	}

	echo '<dl class="famma-confirm__facts">';

	$phone = $order->get_billing_phone();
	if ( '' !== $phone ) {
		printf(
			'<dt>%1$s</dt><dd dir="ltr">%2$s</dd>',
			esc_html__( 'Numéro à appeler', 'famma-child' ),
			esc_html( $phone )
		);
	}

	// Le montant n'est rappelé ici que si aucune étape ne le donne déjà.
	if ( ! $said ) {
		printf(
			'<dt>%1$s</dt><dd>%2$s</dd>',
			esc_html__( 'À préparer en espèces', 'famma-child' ),
			wp_kses_post( wc_price( $order->get_total(), $price ) )
		);
	}

	echo '</dl>';

	printf(
		'<p class="famma-confirm__fix">%s</p>',
		esc_html__( 'Une erreur dans le numéro ou l’adresse ? Écrivez-nous, on corrige avant l’envoi.', 'famma-child' )
	);

	echo do_shortcode( '[famma_whatsapp text="' . esc_attr__( 'Corriger ma commande', 'famma-child' ) . '"]' );
	echo '</section>';
}
