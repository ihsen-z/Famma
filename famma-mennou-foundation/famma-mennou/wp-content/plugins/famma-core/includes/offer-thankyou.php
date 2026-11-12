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
 * Depuis la 0.3.14, « Découvrir le produit » ouvre une fiche rapide (photos,
 * points forts, mode d'emploi) sans quitter la page : le même bouton d'ajout
 * reste en bas de la fiche. Sans JavaScript, le lien mène à la fiche produit.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Affichage de l'offre et traitement du clic.
 */
final class Offer_Thankyou {

	/** Champ de formulaire : action demandée (`accept`, `decline` ou `view`). */
	private const FIELD_ACTION = 'famma_offer_action';

	/** Champ de formulaire : jeton de sécurité. */
	private const FIELD_NONCE = 'famma_offer_nonce';

	/** Identifiant HTML du formulaire (les boutons de la fiche rapide s'y rattachent). */
	private const FORM_ID = 'famma-offer-form';

	/** Identifiant HTML de la fiche rapide. */
	private const SHEET_ID = 'famma-offer-sheet';

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
		$valid  = (bool) wp_verify_nonce( $nonce, self::nonce_action( $order->get_id() ) );

		// Ouverture de la fiche rapide : simple mesure envoyée en arrière-plan,
		// sans redirection ni contenu de réponse.
		if ( 'view' === $action ) {
			if ( $valid ) {
				$this->record_view( $order );
			}
			status_header( 204 );
			exit;
		}

		if ( ! $valid ) {
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
	 * Note la première ouverture de la fiche rapide (statistiques de l'onglet).
	 *
	 * @param \WC_Order $order Commande.
	 * @return string `viewed`, ou `closed` si l'offre n'est plus ouverte.
	 */
	private function record_view( \WC_Order $order ): string {
		$offer = Offer_Settings::for_order( $order );
		if ( null === $offer ) {
			return 'closed';
		}
		if ( ! $order->get_meta( Offer_Settings::ORDER_VIEWED ) ) {
			$order->update_meta_data( Offer_Settings::ORDER_VIEWED, time() );
			$order->update_meta_data( Offer_Settings::ORDER_SOURCE, (string) $offer['source'] );
			$order->save();
			Offer_Settings::flush_stats( (int) $offer['source'] );
		}
		return 'viewed';
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
	 * Feuille de style et script de la fiche rapide, sur la page de
	 * remerciement seulement. Le script est différé : il ne retarde pas
	 * l'affichage de la page.
	 *
	 * @return void
	 */
	public function assets(): void {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return;
		}

		$dir = plugin_dir_path( __DIR__ );
		$url = plugin_dir_url( __DIR__ );

		if ( file_exists( $dir . 'assets/css/offer.css' ) ) {
			wp_enqueue_style( 'famma-offer', $url . 'assets/css/offer.css', array(), (string) filemtime( $dir . 'assets/css/offer.css' ) );
		}
		if ( file_exists( $dir . 'assets/js/offer-sheet.js' ) ) {
			wp_enqueue_script(
				'famma-offer-sheet',
				$url . 'assets/js/offer-sheet.js',
				array(),
				(string) filemtime( $dir . 'assets/js/offer-sheet.js' ),
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
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
					__( 'C\'est ajouté à votre commande. Nouveau total à payer à la livraison : %s DT.', 'famma-core' ),
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
	 * @param \WC_Order                                                                                                                                    $order Commande.
	 * @param array{source: int, target: int, price: float, title: string, text: string, sheet: bool, points: string, usage: string, product: \WC_Product} $offer Offre.
	 * @return void
	 */
	private function offer_block( \WC_Order $order, array $offer ): void {
		$product = $offer['product'];
		$regular = (float) $product->get_price();
		$off     = $regular > $offer['price'] ? (int) round( 100 * ( $regular - $offer['price'] ) / $regular ) : 0;
		$title   = '' !== $offer['title'] ? $offer['title'] : __( 'Ajoutez-le à votre colis', 'famma-core' );
		$text    = '' !== $offer['text'] ? $offer['text'] : __( 'Même colis, livraison gratuite. Valable jusqu’à notre appel de confirmation.', 'famma-core' );
		$sheet   = $offer['sheet'];
		$link    = $product->get_permalink();
		$action  = self::FIELD_ACTION;
		?>
		<section class="famma-offer" aria-labelledby="famma-offer-title">
			<form method="post" action="<?php echo esc_url( $order->get_checkout_order_received_url() ); ?>" id="<?php echo esc_attr( self::FORM_ID ); ?>" class="famma-offer__inner">
				<input type="hidden" name="<?php echo esc_attr( self::FIELD_NONCE ); ?>" value="<?php echo esc_attr( wp_create_nonce( self::nonce_action( $order->get_id() ) ) ); ?>" />
				<div class="famma-offer__head">
					<p class="famma-offer__kicker"><?php esc_html_e( 'Offre spéciale', 'famma-core' ); ?> <span lang="ar" dir="rtl"><?php esc_html_e( 'عرض خاص', 'famma-core' ); ?></span></p>
					<button type="submit" name="<?php echo esc_attr( $action ); ?>" value="decline" class="famma-offer__decline"><?php esc_html_e( 'Non merci', 'famma-core' ); ?></button>
				</div>
				<div class="famma-offer__body">
					<?php if ( $sheet ) : ?>
						<a class="famma-offer__media famma-offer__media--link" href="<?php echo esc_url( $link ); ?>" data-famma-sheet-open aria-haspopup="dialog" aria-controls="<?php echo esc_attr( self::SHEET_ID ); ?>">
							<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) ); ?>
							<?php $this->badge( $off ); ?>
							<span class="famma-offer__zoom" aria-hidden="true"><?php $this->icon( 'eye' ); ?></span>
							<span class="screen-reader-text">
								<?php
								/* translators: %s: product name. */
								echo esc_html( sprintf( __( 'Voir les photos : %s', 'famma-core' ), $product->get_name() ) );
								?>
							</span>
						</a>
					<?php else : ?>
						<div class="famma-offer__media">
							<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) ); ?>
							<?php $this->badge( $off ); ?>
						</div>
					<?php endif; ?>
					<div class="famma-offer__info">
						<h2 id="famma-offer-title" class="famma-offer__title"><?php echo esc_html( $title ); ?></h2>
						<p class="famma-offer__name"><?php echo esc_html( $product->get_name() ); ?></p>
						<?php $this->price( $regular, $offer['price'], $off, 'famma-offer__price' ); ?>
						<?php if ( $sheet ) : ?>
							<a class="famma-offer__more" href="<?php echo esc_url( $link ); ?>" data-famma-sheet-open aria-haspopup="dialog" aria-controls="<?php echo esc_attr( self::SHEET_ID ); ?>">
								<?php esc_html_e( 'Découvrir le produit', 'famma-core' ); ?>
								<span lang="ar" dir="rtl"><?php esc_html_e( 'شوف المنتج', 'famma-core' ); ?></span>
							</a>
						<?php endif; ?>
					</div>
					<div class="famma-offer__form">
						<?php $this->accept_button( $offer['price'], '' ); ?>
					</div>
				</div>
				<p class="famma-offer__text"><?php echo esc_html( $text ); ?></p>
			</form>
			<?php
			if ( $sheet ) {
				$this->sheet( $offer, $regular, $off );
			}
			?>
		</section>
		<?php
	}

	/**
	 * Fiche rapide « Découvrir le produit » : photos, points forts, mode
	 * d'emploi et, en bas, le même bouton d'ajout (rattaché au formulaire de
	 * l'offre par l'attribut `form`). Les photos ne sont chargées qu'à
	 * l'ouverture (attributs `data-src`), pour ne pas ralentir la page.
	 *
	 * @param array{source: int, target: int, price: float, title: string, text: string, sheet: bool, points: string, usage: string, product: \WC_Product} $offer   Offre.
	 * @param float                                                                                                                                        $regular Prix de la fiche produit.
	 * @param int                                                                                                                                          $off     Remise en pourcentage.
	 * @return void
	 */
	private function sheet( array $offer, float $regular, int $off ): void {
		$product = $offer['product'];
		$photos  = $this->photo_ids( $product );
		$points  = Offer_Settings::points( $offer['points'] );
		$short   = '' === trim( $offer['points'] ) ? (string) $product->get_short_description() : '';
		?>
		<dialog id="<?php echo esc_attr( self::SHEET_ID ); ?>" class="famma-sheet" aria-labelledby="famma-sheet-title">
			<div class="famma-sheet__panel">
				<div class="famma-sheet__top" data-famma-sheet-drag>
					<span class="famma-sheet__handle" aria-hidden="true"></span>
				</div>
				<button type="button" class="famma-sheet__close" data-famma-sheet-close>
					<?php $this->icon( 'close' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Fermer', 'famma-core' ); ?></span>
				</button>
				<div class="famma-sheet__scroll">
					<?php if ( array() !== $photos ) : ?>
						<div class="famma-sheet__gallery" tabindex="0" aria-label="<?php esc_attr_e( 'Photos du produit', 'famma-core' ); ?>">
							<?php foreach ( $photos as $n => $photo_id ) : ?>
								<figure class="famma-sheet__slide">
									<?php $this->lazy_image( $photo_id, $product->get_name(), $n + 1, count( $photos ) ); ?>
								</figure>
							<?php endforeach; ?>
						</div>
						<?php if ( count( $photos ) > 1 ) : ?>
							<div class="famma-sheet__dots" aria-hidden="true">
								<?php foreach ( $photos as $n => $photo_id ) : ?>
									<span class="famma-sheet__dot<?php echo 0 === $n ? ' is-active' : ''; ?>"></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>

					<div class="famma-sheet__content">
						<p class="famma-offer__kicker"><?php esc_html_e( 'Offre spéciale', 'famma-core' ); ?> <span lang="ar" dir="rtl"><?php esc_html_e( 'عرض خاص', 'famma-core' ); ?></span></p>
						<h2 id="famma-sheet-title" class="famma-sheet__title"><?php echo esc_html( $product->get_name() ); ?></h2>
						<?php $this->price( $regular, $offer['price'], $off, 'famma-sheet__price' ); ?>

						<?php if ( array() !== $points ) : ?>
							<ul class="famma-sheet__points">
								<?php foreach ( $points as $point ) : ?>
									<li>
										<?php $this->icon( 'check' ); ?>
										<span>
											<?php echo esc_html( $point['fr'] ); ?>
											<?php if ( '' !== $point['ar'] ) : ?>
												<span class="famma-sheet__ar" lang="ar" dir="rtl"><?php echo esc_html( $point['ar'] ); ?></span>
											<?php endif; ?>
										</span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php elseif ( '' !== trim( $short ) ) : ?>
							<div class="famma-sheet__desc"><?php echo wp_kses_post( wpautop( $short ) ); ?></div>
						<?php endif; ?>

						<?php if ( '' !== trim( $offer['usage'] ) ) : ?>
							<h3 class="famma-sheet__subtitle"><?php esc_html_e( 'Comment l’utiliser', 'famma-core' ); ?></h3>
							<p class="famma-sheet__usage"><?php echo nl2br( esc_html( $offer['usage'] ) ); ?></p>
						<?php endif; ?>

						<ul class="famma-sheet__trust">
							<li><?php $this->icon( 'cash' ); ?><?php esc_html_e( 'Paiement à la livraison', 'famma-core' ); ?></li>
							<li><?php $this->icon( 'box' ); ?><?php esc_html_e( 'Dans le même colis', 'famma-core' ); ?></li>
							<li><?php $this->icon( 'truck' ); ?><?php esc_html_e( 'Livraison gratuite', 'famma-core' ); ?></li>
						</ul>
						<p class="famma-sheet__note"><?php esc_html_e( 'Vous pourrez encore changer d’avis lors de notre appel de confirmation.', 'famma-core' ); ?></p>
					</div>
				</div>
				<div class="famma-sheet__bar">
					<button type="button" class="famma-sheet__back" data-famma-sheet-close><?php esc_html_e( 'Retour', 'famma-core' ); ?></button>
					<?php $this->accept_button( $offer['price'], self::FORM_ID ); ?>
				</div>
			</div>
		</dialog>
		<?php
	}

	/**
	 * Bouton « Oui, ajouter ». Hors du formulaire (fiche rapide), il s'y
	 * rattache par l'attribut `form`.
	 *
	 * @param float  $price   Prix spécial.
	 * @param string $form_id Formulaire visé, vide si le bouton est dedans.
	 * @return void
	 */
	private function accept_button( float $price, string $form_id ): void {
		?>
		<button type="submit" name="<?php echo esc_attr( self::FIELD_ACTION ); ?>" value="accept" class="famma-offer__accept<?php echo '' !== $form_id ? ' famma-sheet__accept' : ''; ?>"<?php echo '' !== $form_id ? ' form="' . esc_attr( $form_id ) . '"' : ''; ?>>
			<span class="famma-offer__cta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: special price. */
						__( 'Oui, ajouter · +%s DT', 'famma-core' ),
						self::amount( $price )
					)
				);
				?>
			</span>
			<span class="famma-offer__cta-ar" lang="ar" dir="rtl"><?php esc_html_e( 'زيدها للكوموند', 'famma-core' ); ?></span>
		</button>
		<?php
	}

	/**
	 * Prix barré et prix spécial (montants toujours de gauche à droite).
	 *
	 * @param float  $regular Prix de la fiche.
	 * @param float  $price   Prix spécial.
	 * @param int    $off     Remise en pourcentage.
	 * @param string $css     Classe du paragraphe.
	 * @return void
	 */
	private function price( float $regular, float $price, int $off, string $css ): void {
		?>
		<p class="<?php echo esc_attr( $css ); ?>">
			<?php if ( $off > 0 ) : ?>
				<del dir="ltr"><?php echo esc_html( self::amount( $regular ) . ' DT' ); ?></del>
			<?php endif; ?>
			<strong dir="ltr"><?php echo esc_html( self::amount( $price ) . ' DT' ); ?></strong>
		</p>
		<?php
	}

	/**
	 * Pastille de remise sur la photo.
	 *
	 * @param int $off Remise en pourcentage.
	 * @return void
	 */
	private function badge( int $off ): void {
		if ( $off > 0 ) {
			printf( '<span class="famma-offer__badge" dir="ltr">%s</span>', esc_html( '-' . $off . ' %' ) );
		}
	}

	/**
	 * Photos de la fiche rapide : image principale puis galerie, sans doublon.
	 *
	 * @param \WC_Product $product Produit proposé.
	 * @return array<int, int>
	 */
	private function photo_ids( \WC_Product $product ): array {
		$ids = array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', $product->get_gallery_image_ids() ) );
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		return array_slice( $ids, 0, Offer_Settings::MAX_PHOTOS );
	}

	/**
	 * Photo chargée à l'ouverture de la fiche : `data-src` / `data-srcset`,
	 * remplacés par le script. Sans script, la fiche ne s'ouvre pas.
	 *
	 * @param int    $attachment_id Pièce jointe.
	 * @param string $name          Nom du produit (texte de remplacement par défaut).
	 * @param int    $n             Rang de la photo.
	 * @param int    $total         Nombre de photos.
	 * @return void
	 */
	private function lazy_image( int $attachment_id, string $name, int $n, int $total ): void {
		$src = wp_get_attachment_image_src( $attachment_id, 'woocommerce_single' );
		if ( ! is_array( $src ) ) {
			return;
		}
		$srcset = wp_get_attachment_image_srcset( $attachment_id, 'woocommerce_single' );
		$alt    = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' === $alt ) {
			/* translators: 1: product name, 2: photo number, 3: number of photos. */
			$alt = sprintf( __( '%1$s, photo %2$d sur %3$d', 'famma-core' ), $name, $n, $total );
		}
		printf(
			'<img data-src="%1$s" data-srcset="%2$s" sizes="(min-width: 768px) 560px, 100vw" width="%3$d" height="%4$d" alt="%5$s" decoding="async" />',
			esc_url( $src[0] ),
			esc_attr( is_string( $srcset ) ? $srcset : '' ),
			(int) $src[1],
			(int) $src[2],
			esc_attr( $alt )
		);
	}

	/**
	 * Petites icônes en ligne (décoratives, masquées aux lecteurs d'écran).
	 *
	 * @param string $name Icône.
	 * @return void
	 */
	private function icon( string $name ): void {
		$paths = array(
			'eye'   => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
			'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
			'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
			'cash'  => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
			'box'   => '<path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/>',
			'truck' => '<path d="M2.5 6.5h11v9h-11zM13.5 9.5h4l3 3v3h-7z"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return;
		}
		echo wp_kses(
			'<svg class="famma-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>',
			array(
				'svg'    => array(
					'class'           => true,
					'viewbox'         => true,
					'width'           => true,
					'height'          => true,
					'fill'            => true,
					'stroke'          => true,
					'stroke-width'    => true,
					'stroke-linecap'  => true,
					'stroke-linejoin' => true,
					'aria-hidden'     => true,
					'focusable'       => true,
				),
				'path'   => array( 'd' => true ),
				'circle' => array(
					'cx' => true,
					'cy' => true,
					'r'  => true,
				),
				'rect'   => array(
					'x'      => true,
					'y'      => true,
					'width'  => true,
					'height' => true,
					'rx'     => true,
				),
			)
		);
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
