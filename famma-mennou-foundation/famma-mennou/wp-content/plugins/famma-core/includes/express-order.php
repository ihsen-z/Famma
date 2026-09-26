<?php
/**
 * Commande express sur la fiche produit — option C de la décision D-8.
 *
 * Le trafic arrive d'une publicité Meta et atterrit sur une fiche produit,
 * sans connaître la boutique (§B34). Le parcours WooCommerce impose alors
 * *fiche → panier → checkout*. Ce formulaire supprime les deux dernières
 * étapes : le client saisit ses coordonnées là où il est, et la commande part.
 *
 * ── Ce que ce fichier ne fait PAS, et pourquoi c'est l'essentiel ────────────
 *
 * Il ne crée aucune commande. Il n'écrit rien en base. Il ne valide aucun
 * champ. Il **ne réimplémente pas le checkout** — c'est la condition posée par
 * le plan UX pour que l'option C soit acceptable (§27, §61).
 *
 * Le formulaire poste vers les deux points d'entrée AJAX de WooCommerce :
 * `?wc-ajax=add_to_cart`, puis `?wc-ajax=checkout`. Le second exécute
 * `WC_Checkout::process_checkout()`, exactement comme le bouton « Commander »
 * de la page checkout classique. Tout ce qui pend à ce traitement continue
 * donc de s'appliquer, sans une ligne de code en plus :
 *
 * - la validation du téléphone tunisien et du gouvernorat (`Tunisia`) ;
 * - le contrôle du stock, et sa réservation ;
 * - le statut COD initial (`Order_Statuses::cod_initial_status()`) ;
 * - le rattachement des UTM à la commande (§20) ;
 * - le coût de livraison interne de 7 TND posé sur la commande (`Shipping`) ;
 * - la règle « payé seulement à la livraison », et la CAPI qui en découle.
 *
 * Réécrire ce traitement aurait voulu dire rebrancher chacun de ces points à
 * la main, et les voir se désynchroniser à la première évolution.
 *
 * ── Ce que le formulaire demande ────────────────────────────────────────────
 *
 * Quatre champs obligatoires, décidés par le propriétaire le 23/09/2026 : nom
 * complet, téléphone, gouvernorat, adresse. La ville reste proposée, mais
 * facultative. Ce sont exactement les champs obligatoires du checkout, que
 * `Tunisia::checkout_fields()` règle de la même façon : un champ retiré ici mais
 * exigé là-bas ferait refuser la commande par `process_checkout()`, au moment
 * où le client se croit arrivé. Le nom complet est redécoupé en prénom et nom
 * par `Tunisia::split_full_name()`.
 *
 * S'y ajoute la case des conditions de vente, parce que la boutique en a
 * désigné une page : sans elle, WooCommerce refuse la commande.
 *
 * ── Produits variables ─────────────────────────────────────────────────────
 *
 * Le formulaire ne s'affiche pas sur un produit variable : le choix des
 * variations vit dans le formulaire de WooCommerce, et le dédoubler ferait
 * deux sélecteurs concurrents pour une seule commande. Ces produits gardent le
 * parcours standard, qui reste en place sous le formulaire.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Formulaire de commande directe sur la fiche produit.
 */
final class Express_Order {

	/**
	 * Instance partagée.
	 *
	 * @var Express_Order|null
	 */
	private static ?Express_Order $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Express_Order
	 */
	public static function instance(): Express_Order {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les points d'accroche.
	 *
	 * Priorité 26 : le thème a réordonné le résumé et place la réassurance en
	 * 25, le formulaire d'achat de WooCommerce restant en 30. Le formulaire
	 * express se glisse entre les deux — le client lit comment il paie et qui
	 * le livre, puis commande, et le parcours classique reste disponible juste
	 * en dessous.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'woocommerce_single_product_summary', array( $this, 'render' ), 26 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wc_ajax_famma_express_nonce', array( $this, 'send_nonce' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Signale au thème que la fiche porte le formulaire express.
	 *
	 * Le formulaire a sa propre quantité : le thème s'en sert pour ranger celle
	 * du bouton « Ajouter au panier », que le script synchronise. Deux
	 * sélecteurs de quantité visibles pour un seul produit, c'était deux
	 * réponses possibles à la même question.
	 *
	 * @param array<int, string> $classes Classes du `<body>`.
	 * @return array<int, string>
	 */
	public function body_class( array $classes ): array {
		if ( $this->eligible_product() instanceof \WC_Product ) {
			$classes[] = 'famma-has-express';
		}

		return $classes;
	}

	/**
	 * Renvoie un jeton de checkout valable pour la session en cours.
	 *
	 * ── Pourquoi le jeton n'est pas simplement imprimé dans la page ─────────
	 *
	 * Parce qu'il serait faux. Pour un visiteur non connecté, WooCommerce
	 * remplace l'identifiant utilisateur par celui de sa session client
	 * (`WC_Session_Handler::nonce_user_logged_out()`). Or à l'affichage d'une
	 * fiche produit, le visiteur venu d'une publicité n'a **pas encore** de
	 * session : elle naît de l'ajout au panier. Un jeton fabriqué avant cet
	 * ajout est donc calculé sur un identifiant qui n'existera plus après, et
	 * `process_checkout()` le rejette — en répondant « Nous n'avons pas pu
	 * traiter votre commande », le message le moins parlant du lot.
	 *
	 * Sur la page checkout classique le problème ne se pose pas : elle n'est
	 * atteinte qu'avec un panier, donc avec une session déjà établie.
	 *
	 * L'autre issue aurait été de forcer le cookie de session dès la fiche
	 * produit (`set_customer_session_cookie()`). Elle a été écartée : cela
	 * rendrait chaque fiche produit non cachable, c'est-à-dire précisément les
	 * pages d'atterrissage des campagnes, celles dont le temps de chargement
	 * compte le plus.
	 *
	 * Servir ce jeton n'ouvre rien : il n'est valable que pour la session qui
	 * le demande, la réponse est soumise à la politique d'origine du
	 * navigateur, et aucune écriture n'a lieu ici. C'est le même principe que
	 * les jetons distribués par la Store API de WooCommerce.
	 *
	 * @return void
	 */
	public function send_nonce(): void {
		wp_send_json_success(
			array( 'nonce' => wp_create_nonce( 'woocommerce-process_checkout' ) )
		);
	}

	/**
	 * Produit éligible au formulaire express, ou `null`.
	 *
	 * @return \WC_Product|null
	 */
	private function eligible_product(): ?\WC_Product {
		if ( ! is_product() ) {
			return null;
		}

		$product = wc_get_product();

		if ( ! $product instanceof \WC_Product ) {
			return null;
		}

		// Voir le docblock du fichier : les variations gardent le parcours Woo.
		if ( $product->is_type( 'variable' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return null;
		}

		return $product;
	}

	/**
	 * Met la feuille et le script du formulaire en file d'attente.
	 *
	 * @return void
	 */
	public function assets(): void {
		if ( ! $this->eligible_product() instanceof \WC_Product ) {
			return;
		}

		$dir = plugin_dir_path( __DIR__ );
		$url = plugin_dir_url( __DIR__ );

		$css = $dir . 'assets/css/express-order.css';
		$js  = $dir . 'assets/js/express-order.js';

		if ( file_exists( $css ) ) {
			wp_enqueue_style( 'famma-express', $url . 'assets/css/express-order.css', array(), (string) filemtime( $css ) );
		}

		if ( ! file_exists( $js ) ) {
			return;
		}

		wp_enqueue_script( 'famma-express', $url . 'assets/js/express-order.js', array(), (string) filemtime( $js ), true );

		/*
		 * Les deux points d'entrée sont demandés à WooCommerce plutôt que
		 * reconstruits : `get_endpoint()` tient compte des permaliens et du
		 * préfixe de la boutique, qu'une URL écrite à la main manquerait.
		 */
		wp_localize_script(
			'famma-express',
			'fammaExpress',
			array(
				'addToCartUrl' => \WC_AJAX::get_endpoint( 'add_to_cart' ),
				'checkoutUrl'  => \WC_AJAX::get_endpoint( 'checkout' ),
				'nonceUrl'     => \WC_AJAX::get_endpoint( 'famma_express_nonce' ),
				'currency'     => get_woocommerce_currency(),
				'genericError' => __( 'The order could not go through. Please check the form and try again.', 'famma-core' ),
				'sending'      => __( 'Sending…', 'famma-core' ),
				'phoneError'   => __( 'Check the number: 8 digits (XX XXX XXX).', 'famma-core' ),

				/*
				 * Format de prix de la boutique, pour recalculer le total quand
				 * la quantité change. Le symbole passe par le même filtre que
				 * `wc_price()` : « DT » en français, « د.ت » en arabe.
				 */
				'price'        => array(
					'decimals' => wc_get_price_decimals(),
					'decimal'  => wc_get_price_decimal_separator(),
					'thousand' => wc_get_price_thousand_separator(),
					'format'   => get_woocommerce_price_format(),
					'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				),
			)
		);
	}

	/**
	 * Champ texte du formulaire.
	 *
	 * @param string $name         Nom du champ, tel que le checkout l'attend.
	 * @param string $label        Intitulé visible.
	 * @param string $type         Type HTML.
	 * @param string $autocomplete Valeur d'`autocomplete`.
	 * @param bool   $wide         Champ sur toute la largeur de la grille.
	 * @param bool   $required     Champ obligatoire.
	 * @return void
	 */
	private function text_field( string $name, string $label, string $type = 'text', string $autocomplete = '', bool $wide = false, bool $required = true ): void {
		$id    = 'famma-express-' . str_replace( '_', '-', $name );
		$error = $id . '-error';
		?>
		<p class="famma-express__field<?php echo $wide ? ' famma-express__field--wide' : ''; ?>">
			<label for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $label ); ?>
				<?php if ( $required ) : ?>
					<abbr class="required" title="<?php esc_attr_e( 'required', 'famma-core' ); ?>">*</abbr>
				<?php else : ?>
					<span class="famma-express__optional"><?php esc_html_e( '(optional)', 'famma-core' ); ?></span>
				<?php endif; ?>
			</label>
			<input
				type="<?php echo esc_attr( $type ); ?>"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				<?php if ( '' !== $autocomplete ) : ?>
					autocomplete="<?php echo esc_attr( $autocomplete ); ?>"
				<?php endif; ?>
				<?php if ( 'tel' === $type ) : ?>
					<?php
					/*
					 * `inputmode` sort le pavé numérique sur mobile. Le contrôle
					 * en direct du script n'est qu'un confort : la règle qui fait
					 * foi reste `Tunisia::validate_phone()`, côté serveur.
					 */
					?>
					inputmode="tel" placeholder="XX XXX XXX" maxlength="20"
					aria-describedby="<?php echo esc_attr( $error ); ?>"
				<?php endif; ?>
				<?php echo $required ? 'required' : ''; ?>
			/>
			<?php if ( 'tel' === $type ) : ?>
				<span class="famma-express__error" id="<?php echo esc_attr( $error ); ?>" hidden></span>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Quantité, avec les boutons − / + à 48 px.
	 *
	 * Le champ reste saisissable au clavier : sans script, les boutons ne font
	 * rien et la commande part avec la valeur tapée.
	 *
	 * @param \WC_Product $product Produit affiché.
	 * @return void
	 */
	private function quantity_field( \WC_Product $product ): void {
		$max = $product->get_max_purchase_quantity();
		?>
		<div class="famma-express__field famma-express__field--qty">
			<label for="famma-express-qty"><?php esc_html_e( 'Quantity / الكمية', 'famma-core' ); ?></label>
			<div class="famma-express__qty" data-famma-express-qty>
				<button type="button" class="famma-express__qty-btn" data-famma-express-step="-1" aria-label="<?php esc_attr_e( 'Decrease quantity', 'famma-core' ); ?>">−</button>
				<input
					type="number"
					id="famma-express-qty"
					name="famma_quantity"
					value="1"
					min="1"
					<?php if ( $max > 0 ) : ?>
						max="<?php echo esc_attr( (string) $max ); ?>"
					<?php endif; ?>
					step="1"
					inputmode="numeric"
				/>
				<button type="button" class="famma-express__qty-btn" data-famma-express-step="1" aria-label="<?php esc_attr_e( 'Increase quantity', 'famma-core' ); ?>">+</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Liste déroulante des gouvernorats.
	 *
	 * La liste vient de `WC()->countries`, donc du filtre posé par `Tunisia` :
	 * une seule source pour les 24 gouvernorats, ici comme au checkout.
	 *
	 * @return void
	 */
	private function governorate_field(): void {
		$states = WC()->countries->get_states( 'TN' );
		$states = is_array( $states ) ? $states : array();
		?>
		<p class="famma-express__field">
			<label for="famma-express-billing-state">
				<?php esc_html_e( 'Governorate / الولاية', 'famma-core' ); ?>
				<abbr class="required" title="<?php esc_attr_e( 'required', 'famma-core' ); ?>">*</abbr>
			</label>
			<select id="famma-express-billing-state" name="billing_state" autocomplete="address-level1" required>
				<option value=""><?php esc_html_e( 'Choose… / اختار', 'famma-core' ); ?></option>
				<?php foreach ( $states as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Case des conditions de vente, si la boutique en a désigné une page.
	 *
	 * @return void
	 */
	private function terms_field(): void {
		if ( ! wc_terms_and_conditions_checkbox_enabled() ) {
			return;
		}
		?>
		<p class="famma-express__field famma-express__field--wide famma-express__terms">
			<label for="famma-express-terms">
				<input type="checkbox" id="famma-express-terms" name="terms" value="1" required />
				<span><?php wc_terms_and_conditions_checkbox_text(); ?></span>
			</label>
			<input type="hidden" name="terms-field" value="1" />
		</p>
		<?php
	}

	/**
	 * Récapitulatif de ce qui sera commandé.
	 *
	 * Le panier déjà rempli est annoncé explicitement. Sans cette mention, un
	 * client ayant laissé des articles lors d'une visite précédente les
	 * commanderait sans le savoir : `process_checkout()` porte sur le panier
	 * entier, pas sur le seul produit affiché.
	 *
	 * @param \WC_Product $product Produit affiché.
	 * @return void
	 */
	private function summary( \WC_Product $product ): void {
		$already = WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
		?>
		<div class="famma-express__summary">
			<span class="famma-express__total-label"><?php esc_html_e( 'Total to pay on delivery', 'famma-core' ); ?></span>
			<span
				class="famma-express__total"
				data-unit-price="<?php echo esc_attr( (string) wc_get_price_to_display( $product ) ); ?>"
			><?php echo wp_kses_post( wc_price( wc_get_price_to_display( $product ) ) ); ?></span>

			<?php if ( $already > 0 ) : ?>
				<span class="famma-express__cart-note">
					<?php
					printf(
						/* translators: %d: number of items already in the cart. */
						esc_html( _n( 'Plus %d item already in your cart.', 'Plus %d items already in your cart.', $already, 'famma-core' ) ),
						(int) $already
					);
					?>
				</span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Bouton « Ajouter au panier », à côté du bouton de validation.
	 *
	 * Demande du propriétaire (25/09/2026) : le client qui préfère continuer ses
	 * achats trouve l'ajout au panier au même endroit que la commande directe,
	 * et non plus sous le formulaire.
	 *
	 * Il n'ajoute rien lui-même. Le script transmet le clic au formulaire de
	 * WooCommerce, resté dans la page (masqué par le thème) : même quantité,
	 * mêmes contrôles de stock, même suivi `AddToCart` côté serveur. Sans
	 * script, le bouton poste ce formulaire-ci avec `add-to-cart`, que
	 * WooCommerce traite comme le sien — la quantité revient alors à 1.
	 *
	 * @param \WC_Product $product Produit affiché.
	 * @return void
	 */
	private function cart_button( \WC_Product $product ): void {
		?>
		<button type="submit" class="famma-express__cart" name="add-to-cart" value="<?php echo esc_attr( (string) $product->get_id() ); ?>">
			<svg class="famma-express__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 3h3l2.6 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21.5 7H6"/><circle cx="10" cy="21" r="1.4"/><circle cx="18" cy="21" r="1.4"/><path d="M14 9.5v5M11.5 12h5"/></svg>
			<span class="famma-express__cart-label"><?php echo esc_html( Product_Content::text( 'cta_cart' ) ); ?></span>
		</button>
		<?php
	}

	/**
	 * Affiche le formulaire de commande express.
	 *
	 * @return void
	 */
	public function render(): void {
		$product = $this->eligible_product();

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		/*
		 * Libellés saisis dans FAMMA → Pages → Fiche produit. Sur le site
		 * français, le titre porte aussi sa version derja (« Commander / أطلب
		 * توا ») ; sur le site arabe, le libellé principal est déjà en derja.
		 */
		$order   = Product_Content::text( 'cta_order' );
		$derja   = Product_Content::derja( 'cta_order' );
		$title   = ( ! is_rtl() && '' !== $derja && $derja !== $order ) ? $order . ' / ' . $derja : $order;
		$confirm = Product_Content::text( 'cta_confirm' );
		?>
		<section class="famma-express" aria-labelledby="famma-express-title">
			<h2 class="famma-express__title" id="famma-express-title">
				<?php echo esc_html( $title ); ?>
			</h2>
			<?php
			/*
			 * Plus de chapeau « Paiement à la livraison… » ici : la liste de
			 * réassurance juste au-dessus le dit déjà. Le même message cinq fois
			 * sur la fiche, c'était de la hauteur perdue (audit du 23/09, FP-09).
			 */
			?>

			<form class="famma-express__form" method="post" novalidate>
				<div class="famma-express__grid">
					<?php
					$this->text_field( 'billing_first_name', __( 'Full name / الاسم واللقب', 'famma-core' ), 'text', 'name', true );
					$this->text_field( 'billing_phone', __( 'Phone / الهاتف', 'famma-core' ), 'tel', 'tel' );
					$this->governorate_field();
					$this->text_field( 'billing_city', __( 'City / Delegation — المدينة', 'famma-core' ), 'text', 'address-level2', false, false );
					$this->text_field( 'billing_address_1', __( 'Address / العنوان', 'famma-core' ), 'text', 'street-address', true );
					$this->quantity_field( $product );
					$this->terms_field();
					?>
				</div>

				<?php $this->summary( $product ); ?>

				<?php
				/*
				 * Aucun jeton de checkout ici : il serait calculé avant que la
				 * session du visiteur existe, donc invalide au moment de
				 * commander. Le script en demande un frais après l'ajout au
				 * panier — voir `send_nonce()`.
				 */
				?>
				<?php Anti_Abuse::trap_field(); ?>
				<input type="hidden" name="payment_method" value="cod" />
				<input type="hidden" name="famma_product_id" value="<?php echo esc_attr( (string) $product->get_id() ); ?>" />

				<div class="famma-express__actions">
					<button type="submit" class="famma-express__submit">
						<svg class="famma-express__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 3h3l2.6 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21.5 7H6"/><circle cx="10" cy="21" r="1.4"/><circle cx="18" cy="21" r="1.4"/></svg>
						<span class="famma-express__submit-text">
							<span class="famma-express__submit-fr"><?php echo esc_html( $confirm ); ?></span>
							<?php // Traduction du bouton, et non un autre appel à l'action : masquée sur le site arabe, où le libellé principal est déjà en derja. ?>
							<span class="famma-express__submit-ar" lang="ar" dir="rtl"><?php echo esc_html( Product_Content::derja( 'cta_confirm' ) ); ?></span>
						</span>
					</button>
					<?php $this->cart_button( $product ); ?>
				</div>

				<?php
				/*
				 * `aria-live` : les erreurs renvoyées par WooCommerce arrivent
				 * après la soumission, sans rechargement. Sans cette zone, un
				 * lecteur d'écran ne les annoncerait jamais.
				 */
				?>
				<div class="famma-express__notices" role="alert" aria-live="polite"></div>
			</form>
		</section>
		<?php
	}
}
