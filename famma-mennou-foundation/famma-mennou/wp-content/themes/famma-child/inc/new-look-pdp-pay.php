<?php
/**
 * Nouveau look — fiche produit, lot 45 : « Quand payez-vous ? ».
 *
 * Brique `pdp_pay`, valable pour toutes les fiches. Sous la boîte d'achat, une
 * bande marine : un compteur « Déjà payé 0 DT », un colis qui roule sur une
 * route le long des étapes « Comment commander » (textes de la section
 * Boutique, rien de propre à un produit), et le compteur qui passe au prix
 * du produit à la dernière étape, devant le livreur. Puis le bouton
 * « Commander », qui ramène au formulaire.
 *
 * Elle prend la place des quatre bénéfices (décision n° 16 du 14/11). Avec la
 * bande « 0 DT » cochée, l'encadré de réassurance de la fiche ne garde que le
 * délai de livraison (décision n° 6) : paiement et livraison y sont déjà. Le
 * panier et la commande gardent leur encadré complet.
 *
 * Sans script, ou avec moins d'animations demandé : tout est affiché d'un
 * coup, étapes cochées et montants écrits. Aucune requête de plus.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remplace les bénéfices et allège la réassurance quand la brique est servie.
 *
 * Accroché à `wp` après le réordonnancement de `inc/product.php` (priorité
 * 10), qui place la réassurance à la priorité 8.
 *
 * @return void
 */
function famma_child_pdp_pay_setup(): void {
	// Un paquet incomplet ne doit pas casser tout le site.
	if ( ! function_exists( 'famma_child_pdp_part' ) || ! famma_child_pdp_part( 'pdp_pay' ) ) {
		return;
	}

	remove_action( 'woocommerce_after_single_product_summary', 'famma_child_product_benefits', 6 );
	add_action( 'woocommerce_after_single_product_summary', 'famma_child_pdp_pay', 6 );

	if ( famma_child_pdp_part( 'ledger' ) && function_exists( 'famma_child_ledger_shown_rows' ) ) {
		remove_action( 'woocommerce_single_product_summary', 'famma_child_product_reassurance', 8 );
		add_action( 'woocommerce_single_product_summary', 'famma_child_pdp_pay_reassurance', 8 );
	}
}
add_action( 'wp', 'famma_child_pdp_pay_setup', 20 );

/**
 * Réassurance de la fiche : réduite au délai quand la bande « 0 DT » est à
 * l'écran (elle dit déjà le paiement et la livraison), complète sinon
 * (produit en rupture, aucun zéro vrai…).
 *
 * @return void
 */
function famma_child_pdp_pay_reassurance(): void {
	$product = wc_get_product();

	if ( ! $product instanceof WC_Product || array() === famma_child_ledger_shown_rows( $product ) ) {
		famma_child_product_reassurance();
		return;
	}

	$delay = famma_child_delivery_delay();

	if ( '' === $delay ) {
		return;
	}

	famma_child_reassurance_list(
		array(
			array(
				'fr' => $delay,
				'ar' => '',
			),
		)
	);
}

/**
 * Montant écrit dans le texte « {montant} payé ».
 *
 * @param string $amount HTML de wc_price().
 * @return string HTML, vide si le propriétaire a vidé le texte.
 */
function famma_child_pdp_pay_paid( string $amount ): string {
	$text = \Famma\Core\Product_Content::text( 'pay_paid' );

	if ( '' === $text ) {
		return '';
	}

	$parts = explode( '{montant}', $text, 2 );

	if ( 2 !== count( $parts ) ) {
		return esc_html( $text );
	}

	return esc_html( $parts[0] ) . '<span class="famma-pay__amount">' . wp_kses_post( $amount ) . '</span>' . esc_html( $parts[1] );
}

/**
 * Prix à afficher et, s'il est unique, sa valeur pour le compteur animé.
 *
 * Un produit variable dont les variantes n'ont pas le même prix affiche la
 * fourchette, sans compter : le compteur ne peut pas deviner la taille.
 *
 * @param WC_Product $product Produit.
 * @return array{html: string, value: float|null}
 */
function famma_child_pdp_pay_total( WC_Product $product ): array {
	$html  = famma_child_ledger_price( $product );
	$value = null;

	if ( $product instanceof WC_Product_Variable ) {
		$min = (float) $product->get_variation_price( 'min', true );

		if ( $min >= (float) $product->get_variation_price( 'max', true ) ) {
			$value = $min;
		}
	} else {
		$value = (float) wc_get_price_to_display( $product );
	}

	return array(
		'html'  => $html,
		'value' => $value,
	);
}

/**
 * Affiche la bande « Quand payez-vous ? ».
 *
 * @return void
 */
function famma_child_pdp_pay(): void {
	$product = wc_get_product();
	$ready   = $product instanceof WC_Product
		&& $product->is_purchasable()
		&& $product->is_in_stock()
		&& famma_child_cod_is_enabled()
		&& class_exists( '\Famma\Core\Product_Content' )
		&& class_exists( '\Famma\Core\Shop_Content' );
	$title   = $ready ? \Famma\Core\Product_Content::text( 'pay_title' ) : '';
	$steps   = $ready ? \Famma\Core\Shop_Content::steps() : array();

	// Bande impossible (rupture, produit non commandable, paiement à la
	// livraison coupé, titre vidé) : les quatre bénéfices reprennent leur place.
	if ( '' === $title || count( $steps ) < 2 ) {
		famma_child_product_benefits();
		return;
	}

	$zero  = wc_price( 0, array( 'decimals' => 0 ) );
	$total = famma_child_pdp_pay_total( $product );
	$last  = count( $steps ) - 1;
	$box   = function_exists( 'famma_child_icon' ) ? famma_child_icon( 'box', 'famma-pay__box' ) : '';
	$check = function_exists( 'famma_child_icon' ) ? famma_child_icon( 'check', 'famma-pay__check' ) : '';
	$svg   = famma_child_svg_allowed_html();
	?>
	<section class="famma-pay" aria-labelledby="famma-pay-title">
		<div class="famma-pay__wrap">
			<h2 class="famma-pay__title" id="famma-pay-title"><?php echo esc_html( $title ); ?></h2>
			<div class="famma-pay__meter">
				<small><?php echo esc_html( \Famma\Core\Product_Content::text( 'pay_meter' ) ); ?></small>
				<b class="famma-pay__sum" dir="ltr" data-no-translation
					data-zero="<?php echo esc_attr( html_entity_decode( wp_strip_all_tags( $zero ), ENT_QUOTES, 'UTF-8' ) ); ?>"
					data-total="<?php echo esc_attr( html_entity_decode( wp_strip_all_tags( $total['html'] ), ENT_QUOTES, 'UTF-8' ) ); ?>"
					<?php if ( null !== $total['value'] ) : ?>
						data-value="<?php echo esc_attr( (string) $total['value'] ); ?>"
						data-decimals="<?php echo esc_attr( (string) wc_get_price_decimals() ); ?>"
						data-sep="<?php echo esc_attr( wc_get_price_decimal_separator() ); ?>"
					<?php endif; ?>
				><?php echo wp_kses_post( $zero ); ?></b>
			</div>
			<div class="famma-pay__road">
				<span class="famma-pay__base" aria-hidden="true"></span>
				<span class="famma-pay__fill" aria-hidden="true"></span>
				<span class="famma-pay__parcel" aria-hidden="true"><?php echo wp_kses( $box, $svg ); ?></span>
				<ol class="famma-pay__stops">
					<?php foreach ( $steps as $index => $step ) : ?>
						<?php $paid = famma_child_pdp_pay_paid( $index === $last ? $total['html'] : $zero ); ?>
						<li class="famma-pay__stop<?php echo 0 === $index ? ' is-on' : ''; ?>">
							<span class="famma-pay__node"><span class="famma-pay__num"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span><?php echo wp_kses( $check, $svg ); ?></span>
							<div class="famma-pay__step">
								<p><strong><?php echo esc_html( $step['title'] ); ?></strong> <?php echo esc_html( $step['text'] ); ?></p>
								<?php if ( '' !== $paid ) : ?>
									<span class="famma-pay__paid"><?php echo $paid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par famma_child_pdp_pay_paid(). ?></span>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
			<p class="famma-pay__cta">
				<a class="button famma-pay__btn" href="#famma-express-title"><?php echo esc_html( \Famma\Core\Product_Content::text( 'cta_order' ) ); ?></a>
			</p>
		</div>
	</section>
	<?php
}
