<?php
/**
 * Commande — feuille de la page et proposition de compte après la commande.
 *
 * Le choix « Commander sans compte / Créer un compte » ouvrait la commande :
 * sur mobile, le premier écran ne montrait que lui et le code promo (audit du
 * 25/09, UX-17). Il est retiré. La commande invité reste la seule voie
 * affichée, et le compte est proposé après la commande, sur la page de
 * confirmation — la confirmation d'abord, la proposition ensuite (§6 du brief).
 *
 * Si le propriétaire réactive « Autoriser les clients à créer un compte lors de
 * la validation de commande » (WooCommerce → Réglages → Comptes), la case native
 * de WooCommerce apparaît, discrète, dans la carte « Vos coordonnées »
 * (`famma_child_checkout_account_fields()`, checkout-layout.php).
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Charge la feuille de la commande.
 *
 * La feuille porte la mise en page de la maquette : elle est chargée sur toute
 * la page de commande, et seulement là.
 *
 * @return void
 */
function famma_child_enqueue_checkout(): void {
	if ( ! function_exists( 'famma_child_tunnel_step' ) || 2 !== famma_child_tunnel_step() ) {
		return;
	}

	$dir = get_stylesheet_directory();

	if ( file_exists( $dir . '/assets/css/checkout.css' ) ) {
		wp_enqueue_style(
			'famma-checkout',
			get_stylesheet_directory_uri() . '/assets/css/checkout.css',
			array( 'famma-tunnel' ),
			(string) filemtime( $dir . '/assets/css/checkout.css' )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_checkout', 23 );

/*
 * La règle « e-mail obligatoire si et seulement si le client demande un compte »
 * vit dans famma-core, avec les autres validations du tunnel tunisien : c'est
 * une règle métier, et le thème ne fait que de la présentation (règle 6).
 */

/**
 * Propose un compte après une commande passée en invité.
 *
 * Facultatif de bout en bout : un simple lien, aucune fenêtre, rien qui retienne
 * le client sur la page. C'est le §6 du brief — la confirmation d'abord, la
 * proposition ensuite, et jamais l'inverse.
 *
 * Volontairement **non** implémenté : la conversion automatique de la commande
 * invité en compte. WooCommerce n'offre rien de natif pour rattacher a
 * posteriori une commande à un utilisateur créé plus tard, et le faire à la
 * main demanderait de manipuler l'identifiant client d'une commande déjà
 * payable — un risque sans rapport avec le gain. Le brief prévoit ce cas et
 * demande alors de s'abstenir.
 *
 * @param int $order_id Commande qui vient d'être passée.
 * @return void
 */
function famma_child_account_offer_after_order( int $order_id ): void {
	if ( is_user_logged_in() ) {
		return;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order instanceof \WC_Order || $order->get_customer_id() > 0 ) {
		return;
	}

	$account_url = wc_get_page_permalink( 'myaccount' );

	if ( ! $account_url || ! get_option( 'woocommerce_enable_myaccount_registration' ) ) {
		return;
	}
	?>
	<aside class="famma-account-offer">
		<p class="famma-account-offer__text">
			<?php esc_html_e( 'Vous souhaitez retrouver facilement vos prochaines commandes ?', 'famma-child' ); ?>
		</p>
		<a class="famma-account-offer__link" href="<?php echo esc_url( $account_url ); ?>">
			<?php esc_html_e( 'Créer un compte', 'famma-child' ); ?>
		</a>
	</aside>
	<?php
}
add_action( 'woocommerce_thankyou', 'famma_child_account_offer_after_order', 30 );
