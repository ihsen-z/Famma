<?php
/**
 * Offer block on the thank-you page: one compact form, designed to fit in
 * the first screen of a phone.
 *
 * @package upsell-one-click
 */

namespace UpsellOneClick;

defined( 'ABSPATH' ) || exit;

/**
 * Markup of the offer block, its accept button and messages.
 */
final class Block_View {

	/** HTML ID of the form (the quick view buttons point to it). */
	public const FORM_ID = 'uoc-offer-form';

	/** HTML ID of the quick view sheet. */
	public const SHEET_ID = 'uoc-offer-sheet';

	/**
	 * Prints the offer block.
	 *
	 * @param \WC_Order $order Order.
	 * @param array     $offer Offer from {@see Offer::for_order()}.
	 * @return void
	 */
	public static function render( \WC_Order $order, array $offer ): void {
		$product = $offer['product'];
		$regular = (float) $product->get_price();
		$off     = $regular > $offer['price'] ? (int) round( 100 * ( $regular - $offer['price'] ) / $regular ) : 0;
		$title   = '' !== $offer['title'] ? $offer['title'] : Texts::get( 'title' );
		$text    = '' !== $offer['text'] ? $offer['text'] : Texts::get( 'text' );
		$sheet   = $offer['sheet'];
		$link    = $product->get_permalink();
		$open    = ' data-uoc-sheet-open aria-haspopup="dialog" aria-controls="' . esc_attr( self::SHEET_ID ) . '"';
		?>
		<section class="uoc-offer" aria-labelledby="uoc-offer-title"<?php self::colors(); ?>>
			<form method="post" action="<?php echo esc_url( $order->get_checkout_order_received_url() ); ?>" id="<?php echo esc_attr( self::FORM_ID ); ?>" class="uoc-offer__inner">
				<input type="hidden" name="<?php echo esc_attr( Thankyou::FIELD_NONCE ); ?>" value="<?php echo esc_attr( wp_create_nonce( Thankyou::nonce_action( $order->get_id() ) ) ); ?>" />
				<div class="uoc-offer__head">
					<p class="uoc-offer__kicker"><?php Icons::bilingual( Texts::get( 'kicker' ), Texts::alt( 'kicker' ) ); ?></p>
					<button type="submit" name="<?php echo esc_attr( Thankyou::FIELD_ACTION ); ?>" value="decline" class="uoc-offer__decline"><?php echo esc_html( Texts::get( 'decline' ) ); ?></button>
				</div>
				<div class="uoc-offer__body">
					<?php if ( $sheet ) : ?>
						<a class="uoc-offer__media uoc-offer__media--link" href="<?php echo esc_url( $link ); ?>"<?php echo $open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attributes, ID escaped above. ?>>
							<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) ); ?>
							<?php self::badge( $off ); ?>
							<span class="uoc-offer__zoom" aria-hidden="true"><?php Icons::print( 'eye' ); ?></span>
							<span class="screen-reader-text">
								<?php
								/* translators: %s: product name. */
								echo esc_html( sprintf( __( 'See the photos: %s', 'upsell-one-click' ), $product->get_name() ) );
								?>
							</span>
						</a>
					<?php else : ?>
						<div class="uoc-offer__media">
							<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) ); ?>
							<?php self::badge( $off ); ?>
						</div>
					<?php endif; ?>
					<div class="uoc-offer__info">
						<h2 id="uoc-offer-title" class="uoc-offer__title"><?php echo esc_html( $title ); ?></h2>
						<p class="uoc-offer__name"><?php echo esc_html( $product->get_name() ); ?></p>
						<?php self::price( $regular, (float) $offer['price'], $off, 'uoc-offer__price' ); ?>
						<?php if ( $sheet ) : ?>
							<a class="uoc-offer__more" href="<?php echo esc_url( $link ); ?>"<?php echo $open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attributes, ID escaped above. ?>>
								<?php Icons::bilingual( Texts::get( 'more' ), Texts::alt( 'more' ) ); ?>
							</a>
						<?php endif; ?>
					</div>
					<div class="uoc-offer__form">
						<?php self::accept_button( (float) $offer['price'], '' ); ?>
					</div>
				</div>
				<p class="uoc-offer__text"><?php echo esc_html( $text ); ?></p>
			</form>
			<?php
			if ( $sheet ) {
				Sheet_View::render( $offer, $regular, $off );
			}
			?>
		</section>
		<?php
	}

	/**
	 * Store colours as CSS custom properties (Settings > Upsell One Click).
	 *
	 * @return void
	 */
	private static function colors(): void {
		$vars = array();
		foreach ( array(
			'accent'  => 'upsell_oc_color_accent',
			'primary' => 'upsell_oc_color_primary',
		) as $name => $option ) {
			$color = sanitize_hex_color( (string) get_option( $option, '' ) );
			if ( $color ) {
				$vars[] = '--uoc-' . $name . ':' . $color;
			}
		}
		if ( array() !== $vars ) {
			echo ' style="' . esc_attr( implode( ';', $vars ) ) . '"';
		}
	}

	/**
	 * "Yes, add it" button. Outside the form (quick view), it points to it
	 * through the `form` attribute.
	 *
	 * @param float  $price   Special price.
	 * @param string $form_id Target form, empty when the button is inside it.
	 * @return void
	 */
	public static function accept_button( float $price, string $form_id ): void {
		$alt = Texts::alt( 'accept' );
		?>
		<button type="submit" name="<?php echo esc_attr( Thankyou::FIELD_ACTION ); ?>" value="accept" class="uoc-offer__accept<?php echo '' !== $form_id ? ' uoc-sheet__accept' : ''; ?>"<?php echo '' !== $form_id ? ' form="' . esc_attr( $form_id ) . '"' : ''; ?>>
			<span class="uoc-offer__cta"><?php echo esc_html( Texts::get( 'accept', array( '{price}' => Texts::money( $price ) ) ) ); ?></span>
			<?php if ( '' !== $alt ) : ?>
				<span class="uoc-offer__cta-alt" dir="auto"><?php echo esc_html( $alt ); ?></span>
			<?php endif; ?>
		</button>
		<?php
	}

	/**
	 * Struck-through regular price and special price (amounts always left
	 * to right).
	 *
	 * @param float  $regular Regular price.
	 * @param float  $price   Special price.
	 * @param int    $off     Discount in percent.
	 * @param string $css     Paragraph class.
	 * @return void
	 */
	public static function price( float $regular, float $price, int $off, string $css ): void {
		?>
		<p class="<?php echo esc_attr( $css ); ?>">
			<?php if ( $off > 0 ) : ?>
				<del dir="ltr"><?php echo esc_html( Texts::money( $regular ) ); ?></del>
			<?php endif; ?>
			<strong dir="ltr"><?php echo esc_html( Texts::money( $price ) ); ?></strong>
		</p>
		<?php
	}

	/**
	 * Discount badge on the photo.
	 *
	 * @param int $off Discount in percent.
	 * @return void
	 */
	private static function badge( int $off ): void {
		if ( $off > 0 ) {
			printf( '<span class="uoc-offer__badge" dir="ltr">%s</span>', esc_html( '-' . $off . Texts::NBSP . '%' ) );
		}
	}

	/**
	 * Short message above the order details.
	 *
	 * @param string                $key  Text key (`added`, `closed`, `retry`).
	 * @param string                $kind `ok` or `info`.
	 * @param array<string, string> $vars Placeholders.
	 * @return void
	 */
	public static function message( string $key, string $kind, array $vars = array() ): void {
		echo '<p class="uoc-message uoc-message--' . esc_attr( $kind ) . '" role="status">';
		Icons::bilingual( Texts::get( $key, $vars ), Texts::alt( $key ) );
		echo '</p>';
	}
}
