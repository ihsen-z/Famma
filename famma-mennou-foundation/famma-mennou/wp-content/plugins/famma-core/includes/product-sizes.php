<?php
/**
 * Choix de taille d'un produit simple (ceinture « S, M », par exemple).
 *
 * ── Pourquoi pas un produit variable ───────────────────────────────────────
 *
 * Le formulaire express ne s'affiche pas sur un produit variable
 * (`Express_Order::eligible_product()`). La taille est donc une donnée de la
 * LIGNE de commande, réglée produit par produit dans l'administration :
 * méta `_famma_size_options` (« S, M »). Vide : aucun choix, le produit ne
 * change pas.
 *
 * ── Une seule règle serveur, pour tous les chemins d'ajout au panier ───────
 *
 * La taille est lue et contrôlée dans `woocommerce_add_cart_item_data`, que
 * `WC_Cart::add_to_cart()` appelle dans son `try` : une exception y devient
 * un avis d'erreur WooCommerce et l'ajout est refusé. Le même contrôle couvre
 * donc la commande express (`Express_Cart_Isolation::isolate()`), le bouton
 * « Ajouter au panier » de la fiche, l'ajout AJAX des listes et les liens
 * `?add-to-cart=`. Sur les listes, le bouton d'un produit à tailles mène à la
 * fiche (« Choisir la taille ») au lieu d'échouer.
 *
 * La taille choisie suit la ligne : données de panier, puis méta d'article
 * `famma_size` sur la commande, affichée « Taille : M » dans l'administration,
 * les e-mails WooCommerce et la page « commande reçue ».
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Tailles proposées par produit, contrôlées côté serveur.
 */
final class Product_Sizes {

	/**
	 * Méta produit : tailles proposées, séparées par des virgules.
	 */
	public const META = '_famma_size_options';

	/**
	 * Méta produit : intitulé facultatif du choix de taille.
	 */
	public const META_LABEL = '_famma_size_label';

	/**
	 * Nom du champ posté, clé des données de panier et méta de l'article commandé.
	 */
	public const FIELD = 'famma_size';

	/**
	 * Nombre maximal de tailles retenues.
	 */
	private const MAX_OPTIONS = 12;

	/**
	 * Instance partagée.
	 *
	 * @var Product_Sizes|null
	 */
	private static ?Product_Sizes $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Product_Sizes
	 */
	public static function instance(): Product_Sizes {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		if ( is_admin() ) {
			Product_Sizes_Admin::instance();
		}

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'line_item_meta' ), 10, 3 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'display_key' ), 10, 2 );
		add_filter( 'woocommerce_order_again_cart_item_data', array( $this, 'order_again' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'loop_url' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'loop_text' ), 10, 2 );
		add_filter( 'woocommerce_product_supports', array( $this, 'loop_ajax' ), 10, 3 );
	}

	/**
	 * Découpe la saisie de l'administration en liste de tailles.
	 *
	 * Virgule, point-virgule ou retour à la ligne : « S, M » donne S et M.
	 * Doublons et valeurs vides retirés, ordre de saisie conservé.
	 *
	 * @param string $raw Saisie.
	 * @return array<int, string>
	 */
	public static function parse( string $raw ): array {
		$parts = preg_split( '/[,;\r\n]+/', $raw );
		$sizes = array();

		foreach ( is_array( $parts ) ? $parts : array() as $part ) {
			$size = trim( sanitize_text_field( $part ) );
			if ( '' !== $size && ! in_array( $size, $sizes, true ) ) {
				$sizes[] = $size;
			}
		}

		return array_slice( $sizes, 0, self::MAX_OPTIONS );
	}

	/**
	 * Tailles proposées par un produit simple (liste vide : pas de choix).
	 *
	 * @param mixed $product Produit.
	 * @return array<int, string>
	 */
	public static function options( $product ): array {
		if ( ! $product instanceof \WC_Product || $product->is_type( array( 'variable', 'variation' ) ) ) {
			return array();
		}

		return self::parse( (string) $product->get_meta( self::META ) );
	}

	/**
	 * Intitulé du choix : celui de l'administration, sinon « Taille / المقاس ».
	 *
	 * @param \WC_Product $product Produit.
	 * @return string
	 */
	public static function label( \WC_Product $product ): string {
		$label = trim( (string) $product->get_meta( self::META_LABEL ) );

		return '' !== $label ? $label : __( 'Taille / المقاس', 'famma-core' );
	}

	/**
	 * Message : aucune taille choisie.
	 *
	 * @return string
	 */
	public static function missing_message(): string {
		return __( 'Choisissez votre taille. / اختار المقاس متاعك.', 'famma-core' );
	}

	/**
	 * Message : taille absente de la liste du produit.
	 *
	 * @return string
	 */
	public static function invalid_message(): string {
		return __( 'Cette taille n’est pas proposée pour cet article. Choisissez une taille de la liste. / المقاس هذا موش موجود، اختار مقاس من القائمة.', 'famma-core' );
	}

	/**
	 * Boutons radio du formulaire express, juste avant la quantité.
	 *
	 * Aucun choix présélectionné. Rien ne dépend du visiteur : le bloc est
	 * identique pour tous et peut être servi par le cache de page.
	 *
	 * @param \WC_Product $product Produit affiché.
	 * @return void
	 */
	public static function render_field( \WC_Product $product ): void {
		$sizes = self::options( $product );

		if ( array() === $sizes ) {
			return;
		}
		?>
		<fieldset class="famma-express__sizes" data-famma-sizes aria-describedby="famma-express-size-error">
			<legend class="famma-express__sizes-legend">
				<?php echo esc_html( self::label( $product ) ); ?>
				<abbr class="required" title="<?php esc_attr_e( 'required', 'famma-core' ); ?>">*</abbr>
			</legend>
			<div class="famma-express__size-options">
				<?php foreach ( $sizes as $index => $size ) : ?>
					<label class="famma-express__size">
						<input type="radio" name="<?php echo esc_attr( self::FIELD ); ?>" value="<?php echo esc_attr( $size ); ?>" id="<?php echo esc_attr( 'famma-express-size-' . $index ); ?>" required />
						<span class="famma-express__size-text"><?php echo esc_html( $size ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<span class="famma-express__error" id="famma-express-size-error" hidden></span>
		</fieldset>
		<?php
	}

	/**
	 * Taille postée, assainie (chaîne vide si absente).
	 *
	 * Les formulaires d'ajout au panier de WooCommerce ne portent pas de jeton :
	 * la valeur n'est qu'une donnée de ligne, comparée à la liste du produit.
	 * La commande express a déjà vérifié son jeton (`Express_Preparation::guard()`).
	 *
	 * @return string
	 */
	private static function posted_size(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ajout au panier WooCommerce (sans jeton) ; valeur comparée à une liste fermée.
		if ( ! isset( $_POST[ self::FIELD ] ) || ! is_scalar( $_POST[ self::FIELD ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- voir ci-dessus.
		return trim( sanitize_text_field( wp_unslash( (string) $_POST[ self::FIELD ] ) ) );
	}

	/**
	 * Contrôle la taille et l'attache à la ligne de panier.
	 *
	 * Appelé dans le `try` de `WC_Cart::add_to_cart()` : l'exception devient
	 * un avis d'erreur et l'ajout est refusé, quel que soit le chemin.
	 *
	 * @param mixed $cart_item_data Données de la ligne.
	 * @param mixed $product_id     Produit ajouté.
	 * @return array<string, mixed>
	 * @throws \Exception Taille absente ou hors liste.
	 */
	public function cart_item_data( $cart_item_data, $product_id ): array {
		$cart_item_data = (array) $cart_item_data;
		$sizes          = self::options( wc_get_product( absint( $product_id ) ) );

		if ( array() === $sizes ) {
			return $cart_item_data;
		}

		// « Commander à nouveau » : la taille vient de l'ancienne commande.
		$size = isset( $cart_item_data[ self::FIELD ] ) ? (string) $cart_item_data[ self::FIELD ] : self::posted_size();

		if ( '' === $size ) {
			throw new \Exception( esc_html( self::missing_message() ) );
		}
		if ( ! in_array( $size, $sizes, true ) ) {
			throw new \Exception( esc_html( self::invalid_message() ) );
		}

		$cart_item_data[ self::FIELD ] = $size;

		return $cart_item_data;
	}

	/**
	 * Affiche la taille sous l'article, au panier et au checkout.
	 *
	 * @param mixed $item_data Données affichées.
	 * @param mixed $cart_item Ligne du panier.
	 * @return array<int, array<string, string>>
	 */
	public function item_data( $item_data, $cart_item ): array {
		$item_data = (array) $item_data;
		$size      = is_array( $cart_item ) ? (string) ( $cart_item[ self::FIELD ] ?? '' ) : '';

		if ( '' !== $size ) {
			$item_data[] = array(
				'key'     => __( 'Taille', 'famma-core' ),
				'value'   => $size,
				'raw_key' => self::FIELD,
			);
		}

		return $item_data;
	}

	/**
	 * Enregistre la taille sur l'article de la commande.
	 *
	 * @param \WC_Order_Item_Product $item          Article créé.
	 * @param string                 $cart_item_key Clé de la ligne de panier.
	 * @param array<string, mixed>   $values        Ligne de panier.
	 * @return void
	 */
	public function line_item_meta( $item, $cart_item_key, $values ): void {
		unset( $cart_item_key );
		$size = is_array( $values ) ? (string) ( $values[ self::FIELD ] ?? '' ) : '';

		if ( '' !== $size && $item instanceof \WC_Order_Item_Product ) {
			$item->add_meta_data( self::FIELD, $size, true );
		}
	}

	/**
	 * Intitulé lisible de la méta : « Taille » au lieu de `famma_size`.
	 *
	 * @param mixed $display_key Intitulé proposé.
	 * @param mixed $meta        Méta (`WC_Meta_Data`).
	 * @return string
	 */
	public function display_key( $display_key, $meta ): string {
		if ( is_object( $meta ) && isset( $meta->key ) && self::FIELD === $meta->key ) {
			return __( 'Taille', 'famma-core' );
		}

		return (string) $display_key;
	}

	/**
	 * « Commander à nouveau » : la ligne reprend la taille commandée.
	 *
	 * @param mixed $cart_item_data Données de la ligne.
	 * @param mixed $item           Article de l'ancienne commande.
	 * @return array<string, mixed>
	 */
	public function order_again( $cart_item_data, $item ): array {
		$cart_item_data = (array) $cart_item_data;

		if ( $item instanceof \WC_Order_Item_Product ) {
			$size = (string) $item->get_meta( self::FIELD );
			if ( '' !== $size ) {
				$cart_item_data[ self::FIELD ] = $size;
			}
		}

		return $cart_item_data;
	}

	/**
	 * Listes de produits : le bouton d'un produit à tailles mène à la fiche.
	 *
	 * @param mixed $url     Adresse d'ajout au panier.
	 * @param mixed $product Produit.
	 * @return string
	 */
	public function loop_url( $url, $product ): string {
		if ( array() !== self::options( $product ) ) {
			return (string) $product->get_permalink();
		}

		return (string) $url;
	}

	/**
	 * Listes de produits : « Choisir la taille » au lieu d'« Ajouter au panier ».
	 *
	 * @param mixed $text    Libellé.
	 * @param mixed $product Produit.
	 * @return string
	 */
	public function loop_text( $text, $product ): string {
		if ( array() !== self::options( $product ) ) {
			return __( 'Choisir la taille / اختار المقاس', 'famma-core' );
		}

		return (string) $text;
	}

	/**
	 * Pas d'ajout AJAX depuis les listes pour un produit à tailles.
	 *
	 * @param mixed $supports Fonction prise en charge.
	 * @param mixed $feature  Fonction demandée.
	 * @param mixed $product  Produit.
	 * @return bool
	 */
	public function loop_ajax( $supports, $feature, $product ): bool {
		if ( 'ajax_add_to_cart' === $feature && array() !== self::options( $product ) ) {
			return false;
		}

		return (bool) $supports;
	}
}
