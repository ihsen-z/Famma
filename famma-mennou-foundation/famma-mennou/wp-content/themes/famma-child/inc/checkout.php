<?php
/**
 * Commande — le compte client est une option, jamais un passage obligé.
 *
 * L'état de départ n'imposait rien : la commande invité était déjà la seule
 * voie, et elle fonctionne. Ce qui manquait, c'est le **choix** — créer un
 * compte était purement impossible. Ce fichier ajoute cette possibilité sans
 * toucher à la voie invité, qui reste la voie par défaut et la plus courte.
 *
 * Rien n'est réinventé : la case « créer un compte » et le champ mot de passe
 * sont ceux de WooCommerce. On ne fait que les présenter à l'endroit et au
 * moment où le client se pose la question — au tout début de la commande,
 * plutôt qu'au milieu du formulaire d'adresse.
 *
 * Tout le bloc est conditionné à `is_registration_enabled()` : si le réglage
 * WooCommerce est désactivé, la page reste exactement ce qu'elle était. Un
 * choix qu'on ne peut pas honorer ne doit pas être proposé.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le client peut-il se voir proposer un compte sur cette page ?
 *
 * Trois conditions, toutes nécessaires : être sur la commande, ne pas être déjà
 * connecté, et que WooCommerce accepte réellement les inscriptions depuis le
 * checkout.
 *
 * @return bool
 */
function famma_child_can_offer_account(): bool {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_user_logged_in() ) {
		return false;
	}

	$checkout = WC()->checkout();

	return $checkout instanceof \WC_Checkout && $checkout->is_registration_enabled();
}

/**
 * Affiche le choix entre commande invité et création de compte.
 *
 * Rendu **avant** les coordonnées, parce que c'est là que la question se pose.
 * Posée à la fin, elle donne l'impression d'un péage découvert trop tard.
 *
 * L'option invité est cochée d'avance et citée en premier : pour une boutique
 * COD en Tunisie, c'est le parcours attendu, et le défaut doit être le chemin
 * le plus court. Créer un compte se mérite par un clic délibéré.
 *
 * @return void
 */
function famma_child_account_choice(): void {
	if ( ! famma_child_can_offer_account() ) {
		return;
	}
	?>
	<fieldset class="famma-account-choice">
		<legend class="famma-account-choice__legend">
			<?php esc_html_e( 'Comment souhaitez-vous commander ?', 'famma-child' ); ?>
		</legend>

		<label class="famma-account-choice__option" for="famma-account-guest">
			<input
				type="radio"
				id="famma-account-guest"
				name="famma_account_choice"
				value="guest"
				checked="checked"
			/>
			<span class="famma-account-choice__body">
				<span class="famma-account-choice__title">
					<?php esc_html_e( 'Commander sans compte', 'famma-child' ); ?>
				</span>
				<span class="famma-account-choice__hint">
					<?php esc_html_e( 'Nom, téléphone et adresse suffisent. Aucun mot de passe à créer.', 'famma-child' ); ?>
				</span>
			</span>
		</label>

		<label class="famma-account-choice__option" for="famma-account-create">
			<input
				type="radio"
				id="famma-account-create"
				name="famma_account_choice"
				value="create"
			/>
			<span class="famma-account-choice__body">
				<span class="famma-account-choice__title">
					<?php esc_html_e( 'Créer un compte', 'famma-child' ); ?>
				</span>
				<span class="famma-account-choice__hint">
					<?php esc_html_e( 'Retrouvez vos commandes et gagnez du temps la prochaine fois. Une adresse e-mail sera demandée.', 'famma-child' ); ?>
				</span>
			</span>
		</label>
	</fieldset>
	<?php
}
/*
 * Priorité 5 sur `woocommerce_checkout_billing` : en tête de la colonne du
 * formulaire, juste avant la carte « Vos coordonnées ». Accroché avant les
 * coordonnées (`woocommerce_checkout_before_customer_details`), le bloc
 * devenait une cellule de la grille de la commande et repoussait tout le
 * formulaire dans la colonne du récapitulatif.
 */
add_action( 'woocommerce_checkout_billing', 'famma_child_account_choice', 5 );

/**
 * Charge la feuille de la commande, et le script du choix de compte.
 *
 * La feuille porte la mise en page de la maquette : elle est chargée sur toute
 * la page de commande. Le script, lui, ne sert que si le choix de compte est
 * réellement offert — un script chargé pour un bloc absent est une requête
 * pour rien.
 *
 * @return void
 */
function famma_child_enqueue_checkout(): void {
	if ( ! function_exists( 'famma_child_tunnel_step' ) || 2 !== famma_child_tunnel_step() ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	if ( file_exists( $dir . '/assets/css/checkout.css' ) ) {
		wp_enqueue_style(
			'famma-checkout',
			$uri . '/assets/css/checkout.css',
			array( 'famma-tunnel' ),
			(string) filemtime( $dir . '/assets/css/checkout.css' )
		);
	}

	if ( famma_child_can_offer_account() && file_exists( $dir . '/assets/js/checkout-account.js' ) ) {
		wp_enqueue_script(
			'famma-checkout-account',
			$uri . '/assets/js/checkout-account.js',
			array(),
			(string) filemtime( $dir . '/assets/js/checkout-account.js' ),
			true
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
