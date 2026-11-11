<?php
/**
 * Offre après achat : bloc de la page de remerciement et ajout en un clic.
 *
 * Le bouton est un vrai formulaire renvoyé à la page de remerciement elle-même :
 * il fonctionne sans JavaScript, dans la même session WooCommerce que la
 * commande (le jeton de sécurité d'un visiteur non connecté en dépend). Trois
 * gardes avant toute écriture : la clé secrète de la commande, le jeton
 * (nonce) et le statut « En attente de confirmation ».
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Affichage de l'offre et traitement du clic.
 */
final class Offer_Thankyou {

	/** Champ de formulaire : action demandée (`accept` ou `decline`). */
	private const FIELD_ACTION = 'famma_offer_action';

	/** Champ de formulaire : jeton de sécurité. */
	private const FIELD_NONCE = 'famma_offer_nonce';

	/** Paramètre d'URL du résultat, après redirection. */
	private const RESULT = 'famma_offer';

	/**
	 * Instance partagée.
	 *
	 * @var Offer_Thankyou|null
	 */
	private static ?Offer_Thankyou $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Offer_Thankyou
	 */
	public static function instance(): Offer_Thankyou {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'template_redirect', array( self::$i, 'handle' ), 5 );
			// Tout en haut du contenu, juste sous le titre « Commande reçue » : seul
			// emplacement visible sans défiler sur téléphone (mesuré le 12/11).
			add_action( 'woocommerce_before_thankyou', array( self::$i, 'render' ), 10 );
			add_action( 'wp_enqueue_scripts', array( self::$i, 'assets' ) );
		}
		return self::$i;
	}

	/**
	 * Nom de l'action du jeton, propre à la commande.
	 *
	 * @param int $order_id Commande.
	 * @return string
	 */
	private static function nonce_action( int $order_id ): string {
		return 'famma_offer_' . $order_id;
	}

	/**
	 * Commande de la page de remerciement, si la clé de l'URL est la bonne.
	 *
	 * @return \WC_Order|null
	 */
	private function current_order(): ?\WC_Order {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return null;
		}

		$order_id = absint( get_query_var( 'order-received' ) );
		$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- clé de commande lue, aucune écriture ici.
		$order    = $order_id > 0 ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof \WC_Order || '' === $key || ! hash_equals( $order->get_order_key(), $key ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Traite le clic, puis redirige vers la page de remerciement (PRG).
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST[ self::FIELD_ACTION ] ) ) {
			return;
		}

		$order = $this->current_order();
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$nonce  = isset( $_POST[ self::FIELD_NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD_NONCE ] ) ) : '';
		$action = sanitize_key( wp_unslash( $_POST[ self::FIELD_ACTION ] ) );

		if ( ! wp_verify_nonce( $nonce, self::nonce_action( $order->get_id() ) ) ) {
			$this->back( $order, 'expired' );
		}

		// Un double clic ne doit rien ajouter deux fois : verrou court par commande.
		$lock = 'famma_offer_lock_' . $order->get_id();
		if ( ! add_option( $lock, (string) time(), '', false ) ) {
			// Un verrou de plus de 30 s vient d'une exécution interrompue : on le reprend.
			if ( time() - (int) get_option( $lock, 0 ) < 30 ) {
				$this->back( $order, 'busy' );
			}
			update_option( $lock, (string) time(), false );
		}

		$result = 'decline' === $action ? $this->decline( $order ) : $this->accept( $order );
		delete_option( $lock );
		$this->back( $order, $result );
	}

	/**
	 * Ajoute l'article proposé à la commande.
	 *
	 * @param \WC_Order $order Commande.
	 * @return string Résultat pour l'URL.
	 */
	private function accept( \WC_Order $order ): string {
		$order = wc_get_order( $order->get_id() );
		if ( ! $order instanceof \WC_Order ) {
			return 'closed';
		}
		if ( $order->get_meta( Offer_Settings::ORDER_ACCEPTED ) ) {
			return 'added';
		}

		$offer = Offer_Settings::for_order( $order );
		if ( null === $offer ) {
			return 'closed';
		}

		$item_id = $order->add_product(
			$offer['product'],
			1,
			array(
				'subtotal' => $offer['price'],
				'total'    => $offer['price'],
			)
		);
		if ( ! $item_id ) {
			return 'closed';
		}

		$item = $order->get_item( $item_id );
		if ( $item instanceof \WC_Order_Item_Product ) {
			$item->add_meta_data( Offer_Settings::ITEM_FLAG, 'yes', true );
			$item->save();
		}

		$order->calculate_totals( false );
		$order->update_meta_data( Offer_Settings::ORDER_ACCEPTED, time() );
		$order->update_meta_data( Offer_Settings::ORDER_AMOUNT, wc_format_decimal( $offer['price'], 3 ) );
		$order->update_meta_data( Offer_Settings::ORDER_SOURCE, (string) $offer['source'] );
		$order->update_meta_data( Offer_Settings::ORDER_TARGET, (string) $offer['target'] );
		$order->add_order_note(
			sprintf(
				/* translators: 1: product name, 2: special price, 3: new order total. */
				__( 'Offre après achat acceptée par la cliente : %1$s ajouté à %2$s DT. Nouveau total : %3$s DT.', 'famma-core' ),
				$offer['product']->get_name(),
				wc_format_decimal( $offer['price'], 3 ),
				wc_format_decimal( $order->get_total(), 3 )
			)
		);
		$order->save();

		Offer_Settings::flush_stats( (int) $offer['source'] );
		Capi::instance()->queue_offer_accepted( $order->get_id() );

		return 'added';
	}

	/**
	 * Enregistre le refus : l'offre ne s'affiche plus pour cette commande.
	 *
	 * @param \WC_Order $order Commande.
	 * @return string Résultat pour l'URL.
	 */
	private function decline( \WC_Order $order ): string {
		$offer = Offer_Settings::for_order( $order );
		if ( null === $offer ) {
			return 'closed';
		}

		$order->update_meta_data( Offer_Settings::ORDER_DECLINED, time() );
		$order->update_meta_data( Offer_Settings::ORDER_SOURCE, (string) $offer['source'] );
		$order->save();
		Offer_Settings::flush_stats( (int) $offer['source'] );

		return 'declined';
	}

	/**
	 * Retour à la page de remerciement avec le résultat.
	 *
	 * @param \WC_Order $order  Commande.
	 * @param string    $result Résultat.
	 * @return never
	 */
	private function back( \WC_Order $order, string $result ): never {
		wp_safe_redirect( add_query_arg( self::RESULT, $result, $order->get_checkout_order_received_url() ) );
		exit;
	}

	/**
	 * Feuille de style, sur la page de remerciement seulement.
	 *
	 * @return void
	 */
	public function assets(): void {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return;
		}

		$file = plugin_dir_path( __DIR__ ) . 'assets/css/offer.css';
		if ( file_exists( $file ) ) {
			wp_enqueue_style( 'famma-offer', plugin_dir_url( __DIR__ ) . 'assets/css/offer.css', array(), (string) filemtime( $file ) );
		}
	}

	/**
	 * Affiche le bloc (ou le message de résultat) sur la page de remerciement.
	 *
	 * @param mixed $order_id Commande affichée.
	 * @return void
	 */
	public function render( $order_id ): void {
		$order = $this->current_order();
		if ( ! $order instanceof \WC_Order || $order->get_id() !== absint( $order_id ) ) {
			return;
		}

		$result = isset( $_GET[ self::RESULT ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple message d'état après redirection.

		if ( 'added' === $result && $order->get_meta( Offer_Settings::ORDER_ACCEPTED ) ) {
			$this->message(
				sprintf(
					/* translators: %s: new order total. */
					__( 'C\'est ajouté à votre commande. Nouveau total à payer à la livraison : %s DT.', 'famma-core' ),
					self::amount( (float) $order->get_total() )
				),
				__( 'تزاد للكوموند متاعك', 'famma-core' ),
				'ok'
			);
			return;
		}
		if ( 'closed' === $result ) {
			$this->message( __( 'Cette offre n\'est plus disponible : votre commande est déjà en préparation.', 'famma-core' ), __( 'العرض هذا وفى', 'famma-core' ), 'info' );
			return;
		}

		$offer = Offer_Settings::for_order( $order );
		if ( null === $offer ) {
			return;
		}

		if ( ! $order->get_meta( Offer_Settings::ORDER_SHOWN ) ) {
			$order->update_meta_data( Offer_Settings::ORDER_SHOWN, time() );
			$order->update_meta_data( Offer_Settings::ORDER_SOURCE, (string) $offer['source'] );
			$order->update_meta_data( Offer_Settings::ORDER_TARGET, (string) $offer['target'] );
			$order->save();
		}

		if ( in_array( $result, array( 'expired', 'busy' ), true ) ) {
			$this->message( __( 'Le clic n\'a pas abouti. Réessayez le bouton ci-dessous.', 'famma-core' ), __( 'عاود اضغط على الزر', 'famma-core' ), 'info' );
		}

		$this->offer_block( $order, $offer );
	}

	/**
	 * Montant affiché sans zéros inutiles (« 22 », « 22.5 »).
	 *
	 * @param float $value Montant.
	 * @return string
	 */
	private static function amount( float $value ): string {
		return (string) wc_format_decimal( $value, 3, true );
	}

	/**
	 * Bloc de l'offre : un seul formulaire, compact, pensé pour tenir dans le
	 * premier écran d'un téléphone (bouton sous 600 px de haut à 390 × 844).
	 *
	 * @param \WC_Order                                                                                        $order Commande.
	 * @param array{source: int, target: int, price: float, title: string, text: string, product: \WC_Product} $offer Offre.
	 * @return void
	 */
	private function offer_block( \WC_Order $order, array $offer ): void {
		$product = $offer['product'];
		$regular = (float) $product->get_price();
		$off     = $regular > $offer['price'] ? (int) round( 100 * ( $regular - $offer['price'] ) / $regular ) : 0;
		$title   = '' !== $offer['title'] ? $offer['title'] : __( 'Ajoutez-le à votre colis', 'famma-core' );
		$text    = '' !== $offer['text'] ? $offer['text'] : __( 'Même colis, livraison gratuite. Valable jusqu’à notre appel de confirmation.', 'famma-core' );
		$action  = self::FIELD_ACTION;
		?>
		<section class="famma-offer" aria-labelledby="famma-offer-title">
			<form method="post" action="<?php echo esc_url( $order->get_checkout_order_received_url() ); ?>" class="famma-offer__inner">
				<input type="hidden" name="<?php echo esc_attr( self::FIELD_NONCE ); ?>" value="<?php echo esc_attr( wp_create_nonce( self::nonce_action( $order->get_id() ) ) ); ?>" />
				<div class="famma-offer__head">
					<p class="famma-offer__kicker"><?php esc_html_e( 'Offre spéciale', 'famma-core' ); ?> <span lang="ar" dir="rtl"><?php esc_html_e( 'عرض خاص', 'famma-core' ); ?></span></p>
					<button type="submit" name="<?php echo esc_attr( $action ); ?>" value="decline" class="famma-offer__decline"><?php esc_html_e( 'Non merci', 'famma-core' ); ?></button>
				</div>
				<div class="famma-offer__body">
					<div class="famma-offer__media">
						<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) ); ?>
						<?php if ( $off > 0 ) : ?>
							<span class="famma-offer__badge" dir="ltr"><?php echo esc_html( '-' . $off . ' %' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="famma-offer__info">
						<h2 id="famma-offer-title" class="famma-offer__title"><?php echo esc_html( $title ); ?></h2>
						<p class="famma-offer__name"><?php echo esc_html( $product->get_name() ); ?></p>
						<p class="famma-offer__price">
							<?php if ( $off > 0 ) : ?>
								<del dir="ltr"><?php echo esc_html( self::amount( $regular ) . ' DT' ); ?></del>
							<?php endif; ?>
							<strong dir="ltr"><?php echo esc_html( self::amount( $offer['price'] ) . ' DT' ); ?></strong>
						</p>
					</div>
					<div class="famma-offer__form">
						<button type="submit" name="<?php echo esc_attr( $action ); ?>" value="accept" class="famma-offer__accept">
							<span class="famma-offer__cta">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: special price. */
										__( 'Oui, ajouter · +%s DT', 'famma-core' ),
										self::amount( $offer['price'] )
									)
								);
								?>
							</span>
							<span class="famma-offer__cta-ar" lang="ar" dir="rtl"><?php esc_html_e( 'زيدها للكوموند', 'famma-core' ); ?></span>
						</button>
					</div>
				</div>
				<p class="famma-offer__text"><?php echo esc_html( $text ); ?></p>
			</form>
		</section>
		<?php
	}

	/**
	 * Message court au-dessus du détail de la commande.
	 *
	 * @param string $fr   Texte français.
	 * @param string $ar   Texte en derja.
	 * @param string $kind `ok` ou `info`.
	 * @return void
	 */
	private function message( string $fr, string $ar, string $kind ): void {
		printf(
			'<p class="famma-offer-message famma-offer-message--%1$s" role="status">%2$s <span lang="ar" dir="rtl">%3$s</span></p>',
			esc_attr( $kind ),
			esc_html( $fr ),
			esc_html( $ar )
		);
	}
}
