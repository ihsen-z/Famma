<?php
/**
 * Événements Meta du tunnel — ViewContent, AddToCart, InitiateCheckout.
 *
 * Jusqu'ici le Pixel n'envoyait que `PageView`, et la CAPI que `Purchase` au
 * passage au statut « livré ». Entre les deux, rien : Meta voyait des visites
 * et des achats, sans jamais voir **ce qui se passe au milieu**.
 *
 * Ce trou a un coût direct, et il n'est pas théorique. Le trafic de la boutique
 * vient de campagnes Meta qui atterrissent sur une fiche produit (§B34) :
 *
 * - sans `ViewContent`, l'algorithme ne sait pas quel produit a retenu qui,
 *   donc il ne peut ni optimiser la diffusion ni construire une audience
 *   similaire à partir des visiteurs réellement intéressés ;
 * - sans `AddToCart`, il n'y a aucune audience de reciblage — pourtant c'est
 *   la plus rentable : quelqu'un qui a rempli son panier sans commander ;
 * - sans `InitiateCheckout`, impossible de voir OÙ le tunnel fuit. C'est
 *   exactement la mesure qui doit décider si le formulaire express mérite
 *   d'exister (décision D-8 du plan UX).
 *
 * ── Pourquoi le chemin serveur plutôt qu'un simple clic ─────────────────────
 *
 * `AddToCart` n'est pas émis au clic sur le bouton : un clic n'est pas un
 * ajout. Le stock peut manquer, une variation peut être incomplète, la
 * validation peut refuser. Émettre au clic gonfle l'événement de paniers qui
 * n'ont jamais existé — et une donnée fausse est pire pour l'optimisation
 * qu'une donnée absente. L'événement part donc de `woocommerce_add_to_cart`,
 * c'est-à-dire une fois l'ajout **réellement** fait.
 *
 * Conséquence : sur la fiche produit, l'ajout est un POST classique suivi d'un
 * rechargement. L'événement est donc mis de côté dans la session WooCommerce,
 * puis émis au rendu suivant. Sur la boutique, l'ajout est en AJAX et il n'y a
 * pas de rendu suivant : c'est l'écouteur JavaScript qui prend le relais. Les
 * deux chemins s'excluent (voir `remember_add_to_cart()`), jamais de doublon.
 *
 * ── `eventID` ───────────────────────────────────────────────────────────────
 *
 * Chaque événement porte un `eventID`. Aujourd'hui il ne sert à rien, puisque
 * ces trois événements ne partent que du navigateur. Il est là pour le jour où
 * ils partiront aussi par la CAPI : sans lui, Meta compterait deux fois le même
 * événement. Le poser maintenant coûte une ligne ; l'ajouter après coup
 * demanderait de rouvrir chaque appel.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Émet les événements Meta du milieu de tunnel.
 */
final class Tracking_Events {

	/**
	 * Clé de session portant les ajouts au panier restant à annoncer.
	 *
	 * @var string
	 */
	private const PENDING = 'famma_pending_add_to_cart';

	/**
	 * Instance partagée.
	 *
	 * @var Tracking_Events|null
	 */
	private static ?Tracking_Events $i = null;

	/**
	 * Renvoie l'instance partagée.
	 *
	 * @return Tracking_Events
	 */
	public static function instance(): Tracking_Events {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les points d'accroche.
	 *
	 * La priorité 20 sur `wp_enqueue_scripts` n'est pas décorative : le handle
	 * `famma-pixels` est enregistré par `Tracking::enqueue_tracking()` en
	 * priorité 10. S'y accrocher plus tôt attacherait le script inline à un
	 * handle qui n'existe pas encore, et il serait silencieusement perdu.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_events' ), 20 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'remember_add_to_cart' ), 10, 6 );
		add_filter( 'woocommerce_loop_add_to_cart_args', array( $this, 'loop_button_data' ), 10, 2 );
	}

	/**
	 * Identifiant catalogue d'un produit, tel que Meta doit le voir.
	 *
	 * Meta rapproche les événements du catalogue produit par cet identifiant.
	 * Tant qu'aucun catalogue n'est branché, l'identifiant WooCommerce est le
	 * seul choix défendable. Mais les flux générés par les extensions de
	 * catalogue préfixent souvent l'identifiant (`wc_post_id_123`), et un
	 * décalage ici casse silencieusement tout le rapprochement — les événements
	 * arrivent, sans jamais se relier à un produit.
	 *
	 * D'où le filtre : le jour où le catalogue est branché, la correspondance
	 * s'aligne sans toucher à ce fichier.
	 *
	 * @param \WC_Product $product Produit concerné.
	 * @return string
	 */
	private function content_id( \WC_Product $product ): string {
		return (string) apply_filters( 'famma_meta_content_id', (string) $product->get_id(), $product );
	}

	/**
	 * Indique si le Pixel Meta est configuré.
	 *
	 * @return bool
	 */
	private function pixel_active(): bool {
		return '' !== Config::instance()->meta_pixel_id();
	}

	/**
	 * Ajoute la valeur et la devise aux boutons d'ajout de la boucle produit.
	 *
	 * L'écouteur JavaScript ne reçoit de WooCommerce que le bouton cliqué. Le
	 * bouton porte déjà `data-product_id` ; il lui manque le prix, que Meta
	 * attend pour `AddToCart`. Le poser ici évite à l'écouteur d'aller le
	 * chercher dans le DOM de la carte, où le prix barré et le prix promo se
	 * ressemblent trop pour être distingués de façon fiable.
	 *
	 * @param array<string, mixed> $args    Attributs du bouton.
	 * @param \WC_Product          $product Produit de la carte.
	 * @return array<string, mixed>
	 */
	public function loop_button_data( array $args, \WC_Product $product ): array {
		if ( ! $this->pixel_active() ) {
			return $args;
		}

		$attributes = isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array();

		$attributes['data-famma-value']    = wc_format_decimal( (string) wc_get_price_to_display( $product ), wc_get_price_decimals() );
		$attributes['data-famma-currency'] = get_woocommerce_currency();

		$args['attributes'] = $attributes;

		return $args;
	}

	/**
	 * Met de côté un ajout au panier réellement effectué.
	 *
	 * Les six arguments de `woocommerce_add_to_cart` sont dans la signature
	 * parce que WooCommerce les passe tous ; seuls le produit et la quantité
	 * servent ici.
	 *
	 * Le garde-fou AJAX est le cœur de la mécanique anti-doublon : quand
	 * l'ajout se fait en AJAX, l'écouteur JavaScript émet déjà l'événement dans
	 * la page courante. Le mettre AUSSI en session le ferait repartir au
	 * prochain chargement, et Meta compterait deux paniers pour un seul ajout.
	 *
	 * @param string    $key       Clé de la ligne de panier.
	 * @param int       $id        Identifiant du produit.
	 * @param int|float $quantity  Quantité ajoutée.
	 * @param int       $variation Identifiant de la variation.
	 * @param array     $data      Données de variation.
	 * @param array     $items     Données de la ligne de panier.
	 * @return void
	 *
	 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
	 */
	public function remember_add_to_cart( $key, $id, $quantity, $variation, $data, $items ): void {
		if ( ! $this->pixel_active() || ! WC()->session ) {
			return;
		}

		// L'AJAX est servi par l'écouteur JavaScript : voir le docblock.
		if ( wp_doing_ajax() || ( defined( 'WC_DOING_AJAX' ) && WC_DOING_AJAX ) ) {
			return;
		}

		$product = wc_get_product( $variation > 0 ? $variation : $id );

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$pending = WC()->session->get( self::PENDING, array() );

		if ( ! is_array( $pending ) ) {
			$pending = array();
		}

		$pending[] = array(
			'id'       => $this->content_id( $product ),
			'quantity' => (float) $quantity,
			'value'    => (float) wc_get_price_to_display( $product ) * (float) $quantity,
			'currency' => get_woocommerce_currency(),
		);

		WC()->session->set( self::PENDING, $pending );
	}

	/**
	 * Récupère et vide les ajouts en attente.
	 *
	 * Vider tout de suite est volontaire : un événement annoncé deux fois vaut
	 * moins qu'un événement annoncé une fois.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function take_pending(): array {
		if ( ! WC()->session ) {
			return array();
		}

		$pending = WC()->session->get( self::PENDING, array() );

		if ( ! is_array( $pending ) || array() === $pending ) {
			return array();
		}

		WC()->session->set( self::PENDING, array() );

		return $pending;
	}

	/**
	 * Construit un appel `fbq` complet, prêt à être inséré.
	 *
	 * `wp_json_encode` sur chaque moitié plutôt qu'une concaténation de
	 * chaînes : les paramètres viennent de données de boutique — un nom de
	 * devise, un identifiant filtré — et rien de tout cela n'a à être supposé
	 * sûr au moment d'entrer dans du JavaScript.
	 *
	 * @param string               $event  Nom de l'événement standard Meta.
	 * @param array<string, mixed> $params Paramètres de l'événement.
	 * @return string
	 */
	private function fbq_call( string $event, array $params ): string {
		return 'fbq("track",' . wp_json_encode( $event ) . ',' . wp_json_encode( $params )
			. ',{eventID:' . wp_json_encode( wp_generate_uuid4() ) . '});';
	}

	/**
	 * Paramètres `ViewContent` de la fiche produit affichée.
	 *
	 * @return string Chaîne vide si la page n'est pas une fiche produit.
	 */
	private function view_content(): string {
		if ( ! is_product() ) {
			return '';
		}

		$product = wc_get_product();

		if ( ! $product instanceof \WC_Product ) {
			return '';
		}

		return $this->fbq_call(
			'ViewContent',
			array(
				'content_type' => 'product',
				'content_ids'  => array( $this->content_id( $product ) ),
				'content_name' => $product->get_name(),
				'value'        => (float) wc_get_price_to_display( $product ),
				'currency'     => get_woocommerce_currency(),
			)
		);
	}

	/**
	 * Paramètres `InitiateCheckout` du panier en cours.
	 *
	 * @return string Chaîne vide hors du checkout, ou sur un panier vide.
	 */
	private function initiate_checkout(): string {
		if ( ! is_checkout() || is_order_received_page() || ! WC()->cart || WC()->cart->is_empty() ) {
			return '';
		}

		$contents = array();
		$ids      = array();

		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$id         = $this->content_id( $product );
			$ids[]      = $id;
			$contents[] = array(
				'id'       => $id,
				'quantity' => (float) $item['quantity'],
			);
		}

		if ( array() === $contents ) {
			return '';
		}

		return $this->fbq_call(
			'InitiateCheckout',
			array(
				'content_type' => 'product',
				'content_ids'  => $ids,
				'contents'     => $contents,
				'num_items'    => (int) WC()->cart->get_cart_contents_count(),
				'value'        => (float) WC()->cart->get_total( 'edit' ),
				'currency'     => get_woocommerce_currency(),
			)
		);
	}

	/**
	 * Paramètres `AddToCart` des ajouts mis de côté.
	 *
	 * @return string Chaîne vide si aucun ajout n'attend d'être annoncé.
	 */
	private function add_to_cart(): string {
		$script = '';

		foreach ( $this->take_pending() as $line ) {
			$script .= $this->fbq_call(
				'AddToCart',
				array(
					'content_type' => 'product',
					'content_ids'  => array( (string) $line['id'] ),
					'contents'     => array(
						array(
							'id'       => (string) $line['id'],
							'quantity' => (float) $line['quantity'],
						),
					),
					'value'        => (float) $line['value'],
					'currency'     => (string) $line['currency'],
				)
			);
		}

		return $script;
	}

	/**
	 * Écouteur des ajouts au panier faits en AJAX.
	 *
	 * WooCommerce déclenche `added_to_cart` sur `document.body` une fois la
	 * réponse serveur reçue — donc après un ajout réussi, pas au clic.
	 *
	 * jQuery est une dépendance assumée : c'est WooCommerce qui déclenche
	 * l'événement, et il le fait par jQuery. Un `addEventListener` natif ne le
	 * verrait jamais. Le garde-fou `window.jQuery` évite l'erreur de console
	 * sur une page où WooCommerce ne l'aurait pas chargé.
	 *
	 * @return string
	 */
	private function ajax_listener(): string {
		return '(function(){if(!window.jQuery||!window.fbq){return;}'
			. 'jQuery(document.body).on("added_to_cart",function(e,f,c,btn){try{'
			. 'var b=btn&&btn.length?btn[0]:btn;if(!b){return;}'
			. 'var id=b.getAttribute("data-product_id");if(!id){return;}'
			. 'var v=parseFloat(b.getAttribute("data-famma-value")||"0");'
			. 'var q=parseFloat(b.getAttribute("data-quantity")||"1")||1;'
			. 'fbq("track","AddToCart",{content_type:"product",content_ids:[id],'
			. 'contents:[{id:id,quantity:q}],value:v*q,'
			. 'currency:b.getAttribute("data-famma-currency")||undefined},'
			. '{eventID:(window.crypto&&crypto.randomUUID)?crypto.randomUUID():String(Date.now())+"-"+id});'
			. '}catch(err){}});})();';
	}

	/**
	 * Met les événements en file d'attente derrière le Pixel.
	 *
	 * Un seul script inline pour les trois événements : chaque appel à
	 * `wp_add_inline_script` sur le même handle produit un fragment distinct,
	 * et les empiler n'apporte rien ici.
	 *
	 * Le garde `window.fbq` couvre le cas réel où seul le Pixel TikTok est
	 * configuré : le handle `famma-pixels` existe alors, mais `fbq` non.
	 *
	 * @return void
	 */
	public function enqueue_events(): void {
		if ( ! $this->pixel_active() ) {
			return;
		}

		$script = $this->view_content() . $this->add_to_cart() . $this->initiate_checkout();

		if ( '' !== $script ) {
			$script = 'if(window.fbq){' . $script . '}';
		}

		$script .= $this->ajax_listener();

		wp_add_inline_script( 'famma-pixels', $script );
	}
}
