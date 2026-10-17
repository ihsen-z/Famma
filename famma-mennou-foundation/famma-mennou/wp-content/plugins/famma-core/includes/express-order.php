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
 * Le formulaire poste vers un seul point d'entrée, `?wc-ajax=famma_express_checkout`
 * (`Express_Checkout`, audit PERF-03), qui met le produit au panier puis
 * exécute `WC_Checkout::process_checkout()`, exactement comme le bouton
 * « Commander » de la page checkout classique. Tout ce qui pend à ce
 * traitement continue donc de s'appliquer, sans une ligne de code en plus :
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
 * Pas de case des conditions de vente : une case de plus à cocher, sur
 * mobile, c'était une commande de moins. Une mention sous le bouton renvoie à
 * la page des conditions générales, et le serveur (`Express_Checkout`) lève
 * l'exigence de la case pendant la seule requête express.
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
		add_filter( 'script_loader_tag', array( $this, 'script_attributes' ), 10, 2 );
		add_filter( 'style_loader_tag', array( $this, 'style_attributes' ), 10, 2 );
		add_filter( 'wp_inline_script_attributes', array( $this, 'inline_attributes' ) );
		add_filter( 'litespeed_optimize_js_excludes', array( $this, 'litespeed_js_exclude' ) );
		add_filter( 'litespeed_optm_js_defer_exc', array( $this, 'litespeed_js_exclude' ) );
		add_filter( 'litespeed_optimize_css_excludes', array( $this, 'litespeed_css_exclude' ) );
		// Après le thème enfant (priorité 10), qui pose le message de toutes les commandes.
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'thankyou_text' ), 20, 2 );
	}

	/**
	 * Attributs anti-optimisation posés sur les balises du formulaire.
	 *
	 * Mêmes attributs que `Capi_Signals::no_optimize_attributes()` : LiteSpeed
	 * (et Rocket Loader) ne retardent, ne diffèrent ni ne combinent ce script.
	 * Un formulaire de commande qui attend une interaction pour se charger,
	 * c'est un premier clic perdu.
	 *
	 * @return string
	 */
	private static function no_optimize_markup(): string {
		return ' data-no-optimize="1" data-no-defer="1" data-cfasync="false"';
	}

	/**
	 * Ajoute les attributs anti-optimisation au `<script>` du formulaire.
	 *
	 * @param mixed $tag    Balise générée par WordPress.
	 * @param mixed $handle Identifiant du script.
	 * @return string
	 */
	public function script_attributes( $tag, $handle ): string {
		$tag = (string) $tag;
		if ( 'famma-express' !== $handle || str_contains( $tag, 'data-no-optimize' ) ) {
			return $tag;
		}

		return (string) preg_replace( '/<script\b/', '<script' . self::no_optimize_markup(), $tag, 1 );
	}

	/**
	 * Ajoute les attributs anti-optimisation au `<link>` de la feuille du formulaire.
	 *
	 * @param mixed $tag    Balise générée par WordPress.
	 * @param mixed $handle Identifiant de la feuille.
	 * @return string
	 */
	public function style_attributes( $tag, $handle ): string {
		$tag = (string) $tag;
		if ( 'famma-express' !== $handle || str_contains( $tag, 'data-no-optimize' ) ) {
			return $tag;
		}

		return (string) preg_replace( '/<link\b/', '<link' . self::no_optimize_markup(), $tag, 1 );
	}

	/**
	 * Mêmes attributs sur le bloc `fammaExpress` (sortie de `wp_localize_script`).
	 *
	 * @param mixed $attributes Attributs de la balise inline.
	 * @return array<string, string|bool>
	 */
	public function inline_attributes( $attributes ): array {
		$attributes = (array) $attributes;
		if ( 'famma-express-js-extra' === ( $attributes['id'] ?? '' ) ) {
			$attributes['data-no-optimize'] = '1';
			$attributes['data-no-defer']    = '1';
			$attributes['data-cfasync']     = 'false';
		}

		return $attributes;
	}

	/**
	 * Exclut le script du formulaire des optimisations JS de LiteSpeed.
	 *
	 * Filet de sécurité si l'attribut `data-no-optimize` était un jour ignoré.
	 *
	 * @param mixed $excludes Liste d'exclusions de LiteSpeed Cache.
	 * @return array<int, string>
	 */
	public function litespeed_js_exclude( $excludes ): array {
		$excludes   = is_array( $excludes ) ? $excludes : array_filter( (array) $excludes );
		$excludes[] = 'famma-core/assets/js/express-order.js';

		return array_values( array_unique( $excludes ) );
	}

	/**
	 * Exclut la feuille du formulaire des optimisations CSS de LiteSpeed.
	 *
	 * @param mixed $excludes Liste d'exclusions de LiteSpeed Cache.
	 * @return array<int, string>
	 */
	public function litespeed_css_exclude( $excludes ): array {
		$excludes   = is_array( $excludes ) ? $excludes : array_filter( (array) $excludes );
		$excludes[] = 'famma-core/assets/css/express-order.css';

		return array_values( array_unique( $excludes ) );
	}

	/**
	 * Message de la page de remerciement d'une commande express.
	 *
	 * La cliente n'a cliqué qu'une fois : la page doit lui dire que la commande
	 * est bien partie, sous quel numéro, et ce qui se passe ensuite. Une
	 * commande express porte la méta posée par `Express_Idempotency::stamp()`.
	 *
	 * @param mixed $text  Message proposé par WooCommerce (ou par le thème).
	 * @param mixed $order Commande, ou `false` / `null` si elle est inconnue.
	 * @return string
	 */
	public function thankyou_text( $text, $order ): string {
		if ( ! $order instanceof \WC_Order || '' === (string) $order->get_meta( '_famma_express_request_id' ) ) {
			return (string) $text;
		}

		return sprintf(
			'<strong>%1$s</strong> <span lang="ar" dir="rtl">%2$s</span>',
			esc_html(
				sprintf(
					/* translators: %s: order number. */
					__( 'Commande n° %s reçue. On vous appelle pour confirmer.', 'famma-core' ),
					$order->get_order_number()
				)
			),
			esc_html__( 'باش نكلموك باش نأكدو', 'famma-core' )
		);
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
	 * ── Session ouverte ici (PERF-03) ───────────────────────────────────────
	 *
	 * Le jeton est désormais demandé dès la première interaction avec le
	 * formulaire, donc AVANT l'ajout au panier. Pour qu'il soit calculé sur la
	 * session qui servira à commander, ce point d'entrée pose lui-même le
	 * cookie de session WooCommerce s'il manque. Seule cette réponse AJAX (un
	 * POST, jamais mis en cache) porte le cookie : la fiche produit reste
	 * cachable. Aucune donnée de session n'est écrite, donc aucune ligne en
	 * base tant que rien n'est mis au panier.
	 *
	 * Un cookie présent mais refusé par WooCommerce (clés WP changées, valeur
	 * corrompue, session expirée) compte aussi comme manquant : voir
	 * `session_cookie_missing()`.
	 *
	 * @return void
	 */
	public function send_nonce(): void {
		if ( self::session_cookie_missing() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		wp_send_json_success(
			array( 'nonce' => wp_create_nonce( 'woocommerce-process_checkout' ) )
		);
	}

	/**
	 * Le visiteur invité a-t-il besoin qu'on (re)pose son cookie de session ?
	 *
	 * `has_session()` ne suffit pas : il est vrai dès qu'un cookie du bon nom
	 * EXISTE. Si sa signature ne vérifie plus (sels WP régénérés, valeur
	 * corrompue), WooCommerce l'ignore et tire un nouvel identifiant client à
	 * chaque requête sans jamais renvoyer de cookie. Le jeton, lié à cet
	 * identifiant, ne vérifiait alors jamais (`famma_token: expired` en boucle)
	 * et le panier repartait de zéro. Même chose pour une session expirée :
	 * WooCommerce la détruit et change d'identifiant.
	 *
	 * Règle : le cookie reçu doit être valide ET désigner la session en cours.
	 * Un client connecté n'est pas concerné (son jeton dépend de son compte).
	 *
	 * @return bool
	 */
	private static function session_cookie_missing(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->session instanceof \WC_Session_Handler || is_user_logged_in() ) {
			return false;
		}

		$cookie      = WC()->session->get_session_cookie();
		$customer_id = (string) WC()->session->get_customer_id();

		return ! $cookie || $customer_id !== (string) $cookie[0];
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
		 * Les points d'entrée sont demandés à WooCommerce plutôt que
		 * reconstruits : `get_endpoint()` tient compte des permaliens et du
		 * préfixe de la boutique, qu'une URL écrite à la main manquerait.
		 */
		wp_localize_script(
			'famma-express',
			'fammaExpress',
			array(
				'expressUrl'    => \WC_AJAX::get_endpoint( Express_Checkout::ENDPOINT ),
				'prepareUrl'    => \WC_AJAX::get_endpoint( 'famma_express_prepare' ),
				'statusUrl'     => \WC_AJAX::get_endpoint( 'famma_express_status' ),
				'nonceUrl'      => \WC_AJAX::get_endpoint( 'famma_express_nonce' ),
				'currency'      => get_woocommerce_currency(),
				'genericError'  => Express_Checkout::generic_error(),
				'sending'       => __( 'Envoi en cours… / قاعد نبعث…', 'famma-core' ),
				'phoneError'    => __( 'Check the number: 8 digits (XX XXX XXX).', 'famma-core' ),
				'stateError'    => __( 'Pick your governorate from the list.', 'famma-core' ),
				'noMatch'       => __( 'No governorate found', 'famma-core' ),
				'stateHint'     => __( 'Type to search…', 'famma-core' ),
				'confirmLabel'  => __( 'Confirmer ma commande / أكد طلبيتك', 'famma-core' ),
				'retryLabel'    => __( 'Vérifier ma commande en cours / ثبت طلبيتك', 'famma-core' ),
				'amountChanged' => __( 'Le total a été mis à jour. Vérifiez-le puis validez votre commande. تبدل المجموع، راجعو وأكّد.', 'famma-core' ),
				'changed'       => __( 'Votre commande a changé entre-temps (prix ou stock). Appuyez à nouveau sur Confirmer. طلبيتك تبدلت، اضغط مرة أخرى على أكد.', 'famma-core' ),
				'networkError'  => __( 'Connexion impossible. Vérifiez votre connexion internet puis appuyez à nouveau. الكونكسيون مقصوصة، ثبت وعاود اضغط.', 'famma-core' ),
				'nameError'     => Tunisia::name_error(),
				'sizeError'     => Product_Sizes::missing_message(),
				'otherItems'    => __( 'Seul cet article est commandé ici : les autres articles restent dans votre panier. كان المنتوج هذا يتطلب، البقية تقعد في السلة.', 'famma-core' ),
				'modalTitle'    => __( 'Vérifiez le nouveau total / راجع المجموع الجديد', 'famma-core' ),
				'modalClose'    => __( 'Fermer / سكّر', 'famma-core' ),
				/* translators: %s: order total, e.g. "49,000 DT". */
				'finalLabel'    => __( 'Valider ma commande – %s / أكّد نهائيا', 'famma-core' ),
				'pending'       => __( 'Votre commande est en cours de vérification. Utilisez ce bouton pour retrouver son résultat. طلبيتك قاعدين نثبتو فيها، اضغط باش تعرف النتيجة.', 'famma-core' ),
				'recovery'      => __( 'Nous n’avons pas encore reçu le résultat de votre commande. Vérifiez son état avec ce bouton avant de recommencer. مازال ما وصلتناش النتيجة، ثبت حالة طلبيتك قبل ما تعاود.', 'famma-core' ),
				'storageError'  => __( 'Pour commander en toute sécurité, autorisez le stockage de cette page ou utilisez Ajouter au panier. باش تطلب بأمان، فعل التخزين ولا زيد للسلة.', 'famma-core' ),
				'recapLabels'   => array(
					'product'  => __( 'Article / المنتوج', 'famma-core' ),
					'quantity' => __( 'Quantité / الكمية', 'famma-core' ),
					'amount'   => __( 'Montant / المبلغ', 'famma-core' ),
					'subtotal' => __( 'Sous-total / المجموع', 'famma-core' ),
					'discount' => __( 'Réductions / التخفيض', 'famma-core' ),
					'shipping' => __( 'Livraison / التوصيل', 'famma-core' ),
					'fees'     => __( 'Frais / المصاريف', 'famma-core' ),
					'tax'      => __( 'Taxes / الأداءات', 'famma-core' ),
					'total'    => __( 'Total à payer à la livraison / المجموع عند التسليم', 'famma-core' ),
				),

				/*
				 * Format de prix de la boutique, pour recalculer le total quand
				 * la quantité change. Le symbole passe par le même filtre que
				 * `wc_price()` : « DT » en français, « د.ت » en arabe.
				 */
				'price'         => array(
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
		$arabic = self::governorate_arabic_names();
		?>
		<p class="famma-express__field">
			<label for="famma-express-billing-state">
				<?php esc_html_e( 'Governorate / الولاية', 'famma-core' ); ?>
				<abbr class="required" title="<?php esc_attr_e( 'required', 'famma-core' ); ?>">*</abbr>
			</label>
			<select id="famma-express-billing-state" name="billing_state" autocomplete="address-level1" data-famma-combo required>
				<option value=""><?php esc_html_e( 'Choose… / اختار', 'famma-core' ); ?></option>
				<?php foreach ( $states as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" data-alt="<?php echo esc_attr( $arabic[ $code ] ?? '' ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Noms arabes des gouvernorats, pour la recherche du menu intelligent.
	 *
	 * Le client peut taper « صفاقس » comme « Sfax » : le nom arabe sert de mot
	 * clé, il n'est pas affiché. La liste officielle reste celle de `Tunisia`.
	 *
	 * @return array<string,string>
	 */
	private static function governorate_arabic_names(): array {
		return array(
			'TN-11' => 'تونس',
			'TN-12' => 'أريانة',
			'TN-13' => 'بن عروس',
			'TN-14' => 'منوبة',
			'TN-21' => 'نابل',
			'TN-22' => 'زغوان',
			'TN-23' => 'بنزرت',
			'TN-31' => 'باجة',
			'TN-32' => 'جندوبة',
			'TN-33' => 'الكاف',
			'TN-34' => 'سليانة',
			'TN-41' => 'القيروان',
			'TN-42' => 'القصرين',
			'TN-43' => 'سيدي بوزيد',
			'TN-51' => 'سوسة',
			'TN-52' => 'المنستير',
			'TN-53' => 'المهدية',
			'TN-61' => 'صفاقس',
			'TN-71' => 'قفصة',
			'TN-72' => 'توزر',
			'TN-73' => 'قبلي',
			'TN-81' => 'قابس',
			'TN-82' => 'مدنين',
			'TN-83' => 'تطاوين',
		);
	}

	/**
	 * Mention des conditions générales, sous le bouton.
	 *
	 * Remplace la case à cocher : rien à cocher, rien qui bloque. Sans page de
	 * conditions désignée dans WooCommerce, rien n'est affiché — pas de lien mort.
	 *
	 * @return void
	 */
	private function legal_note(): void {
		$page_id = wc_terms_and_conditions_page_id();
		$url     = $page_id > 0 ? get_permalink( $page_id ) : false;

		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}
		?>
		<p class="famma-express__legal">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: 1: opening link tag to the terms page, 2: closing link tag. */
					esc_html__( 'En commandant, vous acceptez nos %1$sconditions générales%2$s. / كي تطلب، راك موافق على %1$sالشروط العامة%2$s.', 'famma-core' ),
					'<a href="' . esc_url( $url ) . '">',
					'</a>'
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Récapitulatif de ce qui sera commandé.
	 *
	 * La commande express est isolée : elle ne porte que ce produit, à cette
	 * quantité (`Express_Checkout`). Les autres articles du panier y restent ;
	 * le script le signale après coup, sans rien bloquer.
	 *
	 * @param \WC_Product $product Produit affiché.
	 * @return void
	 */
	private function summary( \WC_Product $product ): void {
		?>
		<div class="famma-express__summary">
			<span class="famma-express__total-label"><?php esc_html_e( 'Sous-total de cet article / مجموع هذا المنتوج', 'famma-core' ); ?></span>
			<span
				class="famma-express__total"
				data-unit-price="<?php echo esc_attr( (string) wc_get_price_to_display( $product ) ); ?>"
			><?php echo wp_kses_post( wc_price( wc_get_price_to_display( $product ) ) ); ?></span>

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
		$order = Product_Content::text( 'cta_order' );
		$derja = Product_Content::derja( 'cta_order' );
		$title = ( ! is_rtl() && '' !== $derja && $derja !== $order ) ? $order . ' / ' . $derja : $order;
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
				<?php
				/*
				 * Deux sections, chacune avec son titre : « coordonnées » (ce qu'on saisit)
				 * puis « commande » (ce qu'on paie). Le total vit à côté de la quantité qui
				 * le fait varier, et non plus dans un bandeau séparé.
				 */
				?>
				<fieldset class="famma-express__group">
					<legend class="famma-express__legend"><?php esc_html_e( 'Your details / معلوماتك', 'famma-core' ); ?></legend>
					<div class="famma-express__grid">
						<?php
						$this->text_field( 'billing_first_name', __( 'Full name / الاسم واللقب', 'famma-core' ), 'text', 'name', true );
						$this->text_field( 'billing_phone', __( 'Phone / الهاتف', 'famma-core' ), 'tel', 'tel' );
						$this->governorate_field();
						?>
					</div>
				</fieldset>

				<div class="famma-express__group" role="group" aria-labelledby="famma-express-order-title">
					<p class="famma-express__legend" id="famma-express-order-title"><?php esc_html_e( 'Your order / طلبيتك', 'famma-core' ); ?></p>
					<div class="famma-express__order">
						<?php
						// Taille d'abord (produits qui en proposent), puis la quantité.
						Product_Sizes::render_field( $product );
						$this->quantity_field( $product );
						$this->summary( $product );
						?>
					</div>
				</div>

				<?php
				/*
				 * Aucun jeton de checkout ici : la page est servie par le cache,
				 * le jeton serait celui d'un autre visiteur. Le script en demande
				 * un dès la première interaction avec le formulaire — voir
				 * `send_nonce()` et `Express_Checkout`.
				 */
				?>
				<?php Anti_Abuse::trap_field(); ?>
				<input type="hidden" name="payment_method" value="cod" />
				<input type="hidden" name="billing_country" value="TN" />
				<input type="hidden" name="famma_product_id" value="<?php echo esc_attr( (string) $product->get_id() ); ?>" />
				<?php
				/*
				 * Erreurs JUSTE au-dessus du bouton : le script y fait défiler, et le
				 * bouton reste à l'écran sous le message. `aria-live` : les erreurs
				 * arrivent sans rechargement, un lecteur d'écran doit les annoncer.
				 * Rien d'autre ne s'insère au-dessus du bouton après le clic.
				 */
				?>
				<div class="famma-express__notices" role="alert" aria-live="polite"></div>

				<div class="famma-express__actions">
					<button type="submit" class="famma-express__submit">
						<span class="famma-express__spinner" aria-hidden="true"></span>
						<svg class="famma-express__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 3h3l2.6 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21.5 7H6"/><circle cx="10" cy="21" r="1.4"/><circle cx="18" cy="21" r="1.4"/></svg>
						<span class="famma-express__submit-text">
							<span class="famma-express__submit-fr"><?php esc_html_e( 'Confirmer ma commande', 'famma-core' ); ?></span>
							<?php // Traduction du bouton, et non un autre appel à l'action : masquée sur le site arabe, où le libellé principal est déjà en derja. ?>
							<span class="famma-express__submit-ar" lang="ar" dir="rtl"><?php esc_html_e( 'أكد طلبيتك', 'famma-core' ); ?></span>
						</span>
					</button>
					<?php $this->cart_button( $product ); ?>
				</div>

				<?php $this->legal_note(); ?>
				<p class="famma-express__info" aria-live="polite" hidden></p>
			</form>
		</section>
		<?php
	}
}
