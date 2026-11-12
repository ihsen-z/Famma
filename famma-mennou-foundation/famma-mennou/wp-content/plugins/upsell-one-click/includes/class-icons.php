<?php
/**
 * Small inline icons (decorative, hidden from screen readers).
 *
 * @package upsell-one-click
 */

namespace UpsellOneClick;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the SVG icons used by the offer block and the quick view sheet.
 */
final class Icons {

	/**
	 * Prints an icon.
	 *
	 * @param string $name Icon name.
	 * @return void
	 */
	public static function print( string $name ): void {
		$paths = array(
			'eye'   => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
			'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
			'prev'  => '<path d="m15 5-7 7 7 7"/>',
			'next'  => '<path d="m9 5 7 7-7 7"/>',
			'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
			'cash'  => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
			'box'   => '<path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/>',
			'truck' => '<path d="M2.5 6.5h11v9h-11zM13.5 9.5h4l3 3v3h-7z"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return;
		}
		echo wp_kses(
			'<svg class="uoc-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>',
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
	 * Prints a text with its optional second-language line.
	 *
	 * @param string $main  Main text.
	 * @param string $alt   Second-language text (may be empty).
	 * @param string $css   CSS class of the second line.
	 * @return void
	 */
	public static function bilingual( string $main, string $alt, string $css = 'uoc-alt' ): void {
		echo esc_html( $main );
		if ( '' !== $alt ) {
			printf( ' <span class="%1$s" dir="auto">%2$s</span>', esc_attr( $css ), esc_html( $alt ) );
		}
	}
}
