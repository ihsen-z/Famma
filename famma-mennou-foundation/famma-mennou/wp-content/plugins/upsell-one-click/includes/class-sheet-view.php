<?php
/**
 * Quick view sheet "Discover the product": photos, key points, usage and,
 * at the bottom, the same accept button (linked to the offer form through
 * the `form` attribute). Photos load only when the sheet opens.
 *
 * @package upsell-one-click
 */

namespace UpsellOneClick;

defined( 'ABSPATH' ) || exit;

/**
 * Markup of the quick view sheet.
 */
final class Sheet_View {

	/**
	 * Prints the sheet.
	 *
	 * @param array $offer   Offer from {@see Offer::for_order()}.
	 * @param float $regular Regular price.
	 * @param int   $off     Discount in percent.
	 * @return void
	 */
	public static function render( array $offer, float $regular, int $off ): void {
		$product = $offer['product'];
		$photos  = self::photo_ids( $product );
		$total   = count( $photos );
		$points  = Offer::points( $offer['points'] );
		$short   = '' === trim( $offer['points'] ) ? (string) $product->get_short_description() : '';
		$trust   = array(
			'cash'  => Texts::get( 'trust_1' ),
			'box'   => Texts::get( 'trust_2' ),
			'truck' => Texts::get( 'trust_3' ),
		);
		?>
		<dialog id="<?php echo esc_attr( Block_View::SHEET_ID ); ?>" class="uoc-sheet" aria-labelledby="uoc-sheet-title">
			<div class="uoc-sheet__panel">
				<div class="uoc-sheet__top" data-uoc-sheet-drag>
					<span class="uoc-sheet__handle" aria-hidden="true"></span>
				</div>
				<button type="button" class="uoc-sheet__close" data-uoc-sheet-close>
					<?php Icons::print( 'close' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Close', 'upsell-one-click' ); ?></span>
				</button>
				<div class="uoc-sheet__scroll">
					<?php if ( $total > 0 ) : ?>
						<div class="uoc-sheet__media">
							<div class="uoc-sheet__gallery" tabindex="0" aria-label="<?php esc_attr_e( 'Product photos', 'upsell-one-click' ); ?>">
								<?php foreach ( $photos as $n => $photo_id ) : ?>
									<figure class="uoc-sheet__slide">
										<?php self::lazy_image( $photo_id, $product->get_name(), $n + 1, $total ); ?>
									</figure>
								<?php endforeach; ?>
							</div>
							<?php if ( $total > 1 ) : ?>
								<button type="button" class="uoc-sheet__nav uoc-sheet__nav--prev" data-uoc-gallery="prev" disabled>
									<?php Icons::print( 'prev' ); ?>
									<span class="screen-reader-text"><?php esc_html_e( 'Previous photo', 'upsell-one-click' ); ?></span>
								</button>
								<button type="button" class="uoc-sheet__nav uoc-sheet__nav--next" data-uoc-gallery="next">
									<?php Icons::print( 'next' ); ?>
									<span class="screen-reader-text"><?php esc_html_e( 'Next photo', 'upsell-one-click' ); ?></span>
								</button>
								<span class="uoc-sheet__count" aria-live="polite"><span dir="ltr"><span data-uoc-gallery-index>1</span> / <?php echo esc_html( (string) $total ); ?></span></span>
							<?php endif; ?>
						</div>
						<?php if ( $total > 1 ) : ?>
							<div class="uoc-sheet__dots">
								<?php foreach ( $photos as $n => $photo_id ) : ?>
									<button type="button" class="uoc-sheet__dot<?php echo 0 === $n ? ' is-active' : ''; ?>" data-uoc-gallery-dot="<?php echo esc_attr( (string) $n ); ?>"<?php echo 0 === $n ? ' aria-current="true"' : ''; ?>>
										<span class="screen-reader-text">
											<?php
											/* translators: 1: photo number, 2: number of photos. */
											echo esc_html( sprintf( __( 'Photo %1$d of %2$d', 'upsell-one-click' ), $n + 1, $total ) );
											?>
										</span>
									</button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>

					<div class="uoc-sheet__content">
						<p class="uoc-offer__kicker"><?php Icons::bilingual( Texts::get( 'kicker' ), Texts::alt( 'kicker' ) ); ?></p>
						<h2 id="uoc-sheet-title" class="uoc-sheet__title"><?php echo esc_html( $product->get_name() ); ?></h2>
						<?php Block_View::price( $regular, (float) $offer['price'], $off, 'uoc-sheet__price' ); ?>

						<?php if ( array() !== $points ) : ?>
							<ul class="uoc-sheet__points">
								<?php foreach ( $points as $point ) : ?>
									<li>
										<?php Icons::print( 'check' ); ?>
										<span><?php Icons::bilingual( $point['main'], $point['alt'], 'uoc-sheet__alt' ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php elseif ( '' !== trim( $short ) ) : ?>
							<div class="uoc-sheet__desc"><?php echo wp_kses_post( wpautop( $short ) ); ?></div>
						<?php endif; ?>

						<?php if ( '' !== trim( $offer['usage'] ) ) : ?>
							<h3 class="uoc-sheet__subtitle"><?php echo esc_html( Texts::get( 'usage_title' ) ); ?></h3>
							<p class="uoc-sheet__usage"><?php echo nl2br( esc_html( $offer['usage'] ) ); ?></p>
						<?php endif; ?>

						<?php if ( array() !== array_filter( $trust ) ) : ?>
							<ul class="uoc-sheet__trust">
								<?php foreach ( array_filter( $trust ) as $icon => $line ) : ?>
									<li><?php Icons::print( $icon ); ?><?php echo esc_html( $line ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<?php if ( '' !== Texts::get( 'note' ) ) : ?>
							<p class="uoc-sheet__note"><?php echo esc_html( Texts::get( 'note' ) ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<div class="uoc-sheet__bar">
					<button type="button" class="uoc-sheet__back" data-uoc-sheet-close><?php echo esc_html( Texts::get( 'back' ) ); ?></button>
					<?php Block_View::accept_button( (float) $offer['price'], Block_View::FORM_ID ); ?>
				</div>
			</div>
		</dialog>
		<?php
	}

	/**
	 * Main image then gallery, without duplicates or non-images, so that the
	 * "2 / 6" counter matches the photos actually shown.
	 *
	 * @param \WC_Product $product Offered product.
	 * @return array<int, int>
	 */
	public static function photo_ids( \WC_Product $product ): array {
		$ids = array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', $product->get_gallery_image_ids() ) );
		$ids = array_values( array_filter( array_unique( array_filter( $ids ) ), 'wp_attachment_is_image' ) );
		return array_slice( $ids, 0, Offer::MAX_PHOTOS );
	}

	/**
	 * Photo loaded when the sheet opens: `data-src` / `data-srcset`, swapped
	 * by the script.
	 *
	 * @param int    $attachment_id Attachment.
	 * @param string $name          Product name (default alternative text).
	 * @param int    $n             Photo number.
	 * @param int    $total         Number of photos.
	 * @return void
	 */
	private static function lazy_image( int $attachment_id, string $name, int $n, int $total ): void {
		$src = wp_get_attachment_image_src( $attachment_id, 'woocommerce_single' );
		if ( ! is_array( $src ) ) {
			return;
		}
		$srcset = wp_get_attachment_image_srcset( $attachment_id, 'woocommerce_single' );
		$alt    = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' === $alt ) {
			/* translators: 1: product name, 2: photo number, 3: number of photos. */
			$alt = sprintf( __( '%1$s, photo %2$d of %3$d', 'upsell-one-click' ), $name, $n, $total );
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
}
