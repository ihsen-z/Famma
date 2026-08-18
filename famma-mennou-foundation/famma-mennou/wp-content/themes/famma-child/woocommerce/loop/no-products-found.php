<?php
/**
 * FAMMA — état vide de la boutique.
 *
 * Deux situations très différentes partagent ce gabarit, et les confondre est
 * une faute d'expérience :
 *
 *  - **Des filtres sont actifs** : il y a des produits, la sélection est trop
 *    étroite. Le bon geste est de relâcher les filtres.
 *  - **Le catalogue est vide** : il n'y a rien à montrer. Proposer de
 *    « réinitialiser les filtres » serait absurde ; on oriente vers WhatsApp,
 *    seul canal qui apporte une réponse. Aucun produit fictif (§58).
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

$famma_has_filters = ( function_exists( 'WC_Query' ) || class_exists( 'WC_Query' ) )
	? ( ! empty( $_GET ) && count( array_intersect( array_keys( $_GET ), array( 's', 'min_price', 'max_price', 'rating_filter', 'product_cat', 'filter_brand' ) ) ) > 0 ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture d'un etat de navigation, aucune ecriture.
	: false;

if ( ! $famma_has_filters ) {
	// Un attribut filtré arrive sous la forme `filter_<attribut>`.
	foreach ( array_keys( $_GET ) as $famma_key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture d'un etat de navigation, aucune ecriture.
		if ( 0 === strpos( (string) $famma_key, 'filter_' ) ) {
			$famma_has_filters = true;
			break;
		}
	}
}

$famma_whatsapp = famma_child_whatsapp_number();
?>

<div class="famma-empty">
	<svg class="famma-empty__mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true" focusable="false">
		<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
	</svg>

	<?php if ( $famma_has_filters ) : ?>

		<p class="famma-empty__title"><?php esc_html_e( 'Aucun produit ne correspond', 'famma-child' ); ?></p>
		<p class="famma-empty__text">
			<?php esc_html_e( 'Essayez d\'élargir votre recherche : moins de filtres, ou une fourchette de prix plus large.', 'famma-child' ); ?>
		</p>
		<a class="famma-empty__cta" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">
			<?php esc_html_e( 'Réinitialiser les filtres', 'famma-child' ); ?>
		</a>

	<?php else : ?>

		<p class="famma-empty__title"><?php esc_html_e( 'La boutique se remplit', 'famma-child' ); ?></p>
		<p class="famma-empty__text" lang="ar" dir="rtl">فمّا ديما حاجة جديدة</p>
		<p class="famma-empty__text">
			<?php esc_html_e( 'Nos premiers produits arrivent. Écrivez-nous pour être prévenu dès leur mise en ligne.', 'famma-child' ); ?>
		</p>

		<?php if ( '' !== $famma_whatsapp ) : ?>
			<a class="famma-empty__cta" href="<?php echo esc_url( 'https://wa.me/' . rawurlencode( $famma_whatsapp ) ); ?>"
				rel="noopener noreferrer" target="_blank">
				<?php esc_html_e( 'Prévenez-moi sur WhatsApp', 'famma-child' ); ?>
			</a>
		<?php endif; ?>

	<?php endif; ?>
</div>
