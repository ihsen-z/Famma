<?php
/**
 * Événements Meta du tunnel — ViewContent, AddToCart, InitiateCheckout, OrderPlaced.
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
 * ── `eventID` (lot 5, S3-a) ─────────────────────────────────────────────────
 *
 * L'identifiant des événements navigateur est tiré **dans le navigateur**
 * (`fammaEventId()`, posé avec le Pixel par `Tracking`) : une page servie par
 * le cache LiteSpeed ne fige plus le même identifiant pour tous les visiteurs
 * (H-N2). Aucun UUID fabriqué en PHP n'est imprimé dans le HTML. Seuls gardent
 * un identifiant serveur : AddToCart et InitiateCheckout de l'express
 * (`famma_add_<prep>`, `famma_checkout_<prep>`, réponse AJAX jamais mise en
 * cache) et OrderPlaced (`famma_orderplaced_<id>`, le même que la CAPI), émis
 * par `Browser_Order_Placed`.
 *
 * ── Visiteurs internes (S1) et profil de données (R3) ───────────────────────
 *
 * Rien n'est émis pour un visiteur interne (`Tracking::browser_tracking_allowed()`).
 * Les paramètres suivent le profil choisi dans « Mesure » (`profile_params()`).
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
	 * Enveloppe « une seule fois par session de navigation » (sessionStorage).
	 *
	 * `%1$s` : clé JSON ; `%2$s` : corps qui émet puis renvoie `true`. Sans
	 * stockage disponible, l'événement part quand même (il vaut mieux un
	 * doublon, dédupliqué par Meta quand l'eventID est fixe, qu'une perte).
	 */
	public const ONCE_SCRIPT = '(function(k,f){var s=null;try{s=window.sessionStorage;if(s.getItem(k)){return;}}catch(e){s=null;}if(!f()){return;}try{if(s){s.setItem(k,"1");}}catch(e){}})(%1$s,function(){%2$s});';

	/**
	 * Champs retirés côté navigateur par le profil `standard` (les mêmes que la CAPI).
	 */
	private const STANDARD_EXCLUDED = Capi_Data_Profile::STANDARD_EXCLUDED;

	/**
	 * Seuls champs gardés côté navigateur par le profil `minimal`.
	 */
	public const MINIMAL_FIELDS = array( 'value', 'currency', 'content_ids', 'content_type' );

	/**
	 * Écouteur des ajouts au panier faits en AJAX (liste produits) ; `%s` :
	 * clés gardées par le profil de données, en JSON.
	 *
	 * WooCommerce déclenche `added_to_cart` sur `document.body`, par jQuery,
	 * une fois la réponse serveur reçue : après un ajout réussi, pas au clic.
	 * En chargement immédiat (D6, défaut), ce script est imprimé dans le
	 * `<head>`, où `document.body` vaut encore `null` : y brancher l'écouteur
	 * le poserait sur un ensemble vide, et l'événement serait perdu sans trace.
	 * L'écouteur n'est donc posé qu'une fois jQuery ET `document.body`
	 * présents : tout de suite, sinon à DOMContentLoaded, à `load`, à
	 * `DOMContentLiteSpeedLoaded` (JS retardé de LiteSpeed), et en dernier
	 * recours par un court sondage relancé à chaque interaction (jQuery chargé
	 * tardivement). Jamais deux fois. `fbq` est lu au moment de l'ajout.
	 */
	public const AJAX_LISTENER_SCRIPT = '(function(keep){var done=false,t=0,n=0,d=document,w=window;function go(){if(done||!w.jQuery||!d.body){return;}done=true;if(t){clearInterval(t);t=0;}w.jQuery(d.body).on("added_to_cart",function(e,f,c,btn){try{if(!w.fbq){return;}var b=btn&&btn.length?btn[0]:btn;if(!b||!b.getAttribute){return;}var id=b.getAttribute("data-famma-content-id")||b.getAttribute("data-product_id");if(!id){return;}var v=parseFloat(b.getAttribute("data-famma-value")||"0"),q=parseFloat(b.getAttribute("data-quantity")||"1")||1,p={content_type:"product",content_ids:[id],contents:[{id:id,quantity:q}],value:v*q,currency:b.getAttribute("data-famma-currency")||undefined},o={};keep.forEach(function(x){if(x in p){o[x]=p[x];}});w.fbq("track","AddToCart",o,{eventID:w.fammaEventId?w.fammaEventId():String(Date.now())+"-"+id});}catch(err){}});}function poll(){if(done||t){return;}n=0;t=setInterval(function(){go();if(!done&&++n>=40){clearInterval(t);t=0;}},500);}go();if(!done){d.addEventListener("DOMContentLoaded",go);d.addEventListener("DOMContentLiteSpeedLoaded",go);w.addEventListener("DOMContentLiteSpeedLoaded",go);w.addEventListener("load",function(){go();poll();});["pointerdown","keydown","touchstart","scroll"].forEach(function(x){w.addEventListener(x,poll,{passive:true});});}})(%s);';

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
	 * Indique si le Pixel Meta est configuré.
	 *
	 * @return bool
	 */
	private static function pixel_configured(): bool {
		return '' !== Config::instance()->meta_pixel_id();
	}

	/**
	 * Indique si des événements navigateur doivent partir pour cette requête :
	 * Pixel configuré et visiteur non interne (ou dérogation active).
	 *
	 * @return bool
	 */
	public static function pixel_active(): bool {
		return self::pixel_configured() && Tracking::browser_tracking_allowed();
	}

	/**
	 * Applique le profil de données (R3) aux paramètres d'un événement navigateur.
	 *
	 * - `standard` (défaut) : sans `content_name`, `famma_stage` ni `famma_product` ;
	 * - `minimal` : seulement `value`, `currency`, `content_ids` et `content_type`.
	 *
	 * Comme côté serveur, l'ancien comportement ne revient que par le filtre de
	 * code `famma_meta_data_profile_legacy` ; `famma_meta_browser_params`
	 * reçoit le résultat final.
	 *
	 * @param array<string, mixed> $params     Paramètres complets.
	 * @param string               $event_name Événement concerné.
	 * @return array<string, mixed>
	 */
	public static function profile_params( array $params, string $event_name ): array {
		$profile  = Config::instance()->meta_data_profile();
		$filtered = 'minimal' === $profile
			? array_intersect_key( $params, array_flip( self::MINIMAL_FIELDS ) )
			: array_diff_key( $params, array_flip( self::STANDARD_EXCLUDED ) );
		if ( true === apply_filters( 'famma_meta_data_profile_legacy', false, $event_name, null ) ) {
			$filtered = $params;
		}
		$final = apply_filters( 'famma_meta_browser_params', $filtered, $profile, $event_name );

		return is_array( $final ) ? $final : $filtered;
	}

	/**
	 * Ajoute la valeur, la devise et l'identifiant Meta aux boutons de la boucle produit.
	 *
	 * L'écouteur JavaScript ne reçoit de WooCommerce que le bouton cliqué. Le
	 * bouton porte déjà `data-product_id` ; il lui manque le prix, que Meta
	 * attend pour `AddToCart`, et l'identifiant de la règle unique (S3-b), qui
	 * n'est l'ID WooCommerce que pour un produit sans SKU.
	 *
	 * @param array<string, mixed> $args    Attributs du bouton.
	 * @param \WC_Product          $product Produit de la carte.
	 * @return array<string, mixed>
	 */
	public function loop_button_data( array $args, \WC_Product $product ): array {
		if ( ! self::pixel_configured() ) {
			return $args;
		}

		$attributes = isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array();

		$attributes['data-famma-value']      = wc_format_decimal( (string) wc_get_price_to_display( $product ), wc_get_price_decimals() );
		$attributes['data-famma-currency']   = get_woocommerce_currency();
		$attributes['data-famma-content-id'] = Meta_Ids::content_id( $product );

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
		if ( ! self::pixel_active() || ! WC()->session ) {
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
			'id'       => Meta_Ids::content_id( $product ),
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
	 * `wp_json_encode` sur les paramètres plutôt qu'une concaténation : ils
	 * viennent de données de boutique, et rien n'a à être supposé sûr au
	 * moment d'entrer dans du JavaScript. L'eventID est tiré dans le
	 * navigateur, à chaque affichage (S3-a).
	 *
	 * @param string               $event  Nom de l'événement standard Meta.
	 * @param array<string, mixed> $params Paramètres complets de l'événement.
	 * @return string
	 */
	private function fbq_call( string $event, array $params ): string {
		return 'fbq("track",' . wp_json_encode( $event ) . ',' . wp_json_encode( self::profile_params( $params, $event ) )
			. ',{eventID:fammaEventId()});';
	}

	/**
	 * Enveloppe un émetteur pour qu'il ne parte qu'une fois par session de navigation.
	 *
	 * @param string $key  Clé sessionStorage.
	 * @param string $emit Appel `fbq` complet.
	 * @return string
	 */
	public static function once( string $key, string $emit ): string {
		return sprintf( self::ONCE_SCRIPT, wp_json_encode( $key ), $emit . 'return true;' );
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
			Meta_Ids::view_target( $product ) + array(
				'content_name' => $product->get_name(),
				'value'        => (float) wc_get_price_to_display( $product ),
				'currency'     => get_woocommerce_currency(),
			)
		);
	}

	/**
	 * `InitiateCheckout` du panier en cours, une seule fois par panier et par session.
	 *
	 * La clé de session porte l'empreinte du panier : recharger /commande/ ne
	 * renvoie rien (T3.2), modifier le panier ouvre une nouvelle intention.
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

			$id         = Meta_Ids::content_id( $product );
			$ids[]      = $id;
			$contents[] = array(
				'id'       => $id,
				'quantity' => (float) $item['quantity'],
			);
		}

		if ( array() === $contents ) {
			return '';
		}

		$value = (float) WC()->cart->get_total( 'edit' );
		$hash  = (string) WC()->cart->get_cart_hash();
		if ( '' === $hash ) {
			$hash = md5( (string) wp_json_encode( array( $contents, $value ) ) );
		}

		return self::once(
			'famma_ic_' . $hash,
			$this->fbq_call(
				'InitiateCheckout',
				array(
					'content_type' => 'product',
					'content_ids'  => array_values( array_unique( $ids ) ),
					'contents'     => $contents,
					'num_items'    => (int) WC()->cart->get_cart_contents_count(),
					'value'        => $value,
					'currency'     => get_woocommerce_currency(),
				)
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
	 * Écouteur des ajouts au panier faits en AJAX (`AJAX_LISTENER_SCRIPT`).
	 *
	 * Dépendance assumée à jQuery : c'est WooCommerce qui déclenche
	 * l'événement, et il le fait par jQuery. Les clés gardées suivent le
	 * profil de données.
	 *
	 * @return string
	 */
	private function ajax_listener(): string {
		$keys = array_keys(
			self::profile_params(
				array(
					'content_type' => 'product',
					'content_ids'  => array(),
					'contents'     => array(),
					'value'        => 0.0,
					'currency'     => '',
				),
				'AddToCart'
			)
		);

		return sprintf( self::AJAX_LISTENER_SCRIPT, wp_json_encode( array_values( $keys ) ) );
	}

	/**
	 * Met les événements en file d'attente derrière le Pixel.
	 *
	 * Un seul script inline pour tous les événements : chaque appel à
	 * `wp_add_inline_script` sur le même handle produit un fragment distinct,
	 * et les empiler n'apporte rien ici.
	 *
	 * Le garde `window.fbq` couvre le cas réel où seul le Pixel TikTok est
	 * configuré : le handle `famma-pixels` existe alors, mais `fbq` non.
	 *
	 * @return void
	 */
	public function enqueue_events(): void {
		if ( ! self::pixel_active() ) {
			return;
		}

		$script = $this->view_content() . $this->add_to_cart() . $this->initiate_checkout() . Browser_Order_Placed::confirmation_script();

		if ( '' !== $script ) {
			$script = 'if(window.fbq&&window.fammaEventId){' . $script . '}';
		}

		$script .= $this->ajax_listener();

		wp_add_inline_script( 'famma-pixels', $script );
	}
}
