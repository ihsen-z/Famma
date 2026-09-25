<?php
/**
 * FAMMA — page « Livraison », selon la maquette « FAMMA Livraison ».
 *
 * Bandeau, trois points forts, mode de livraison, zones, étapes, questions
 * fréquentes et encart d'aide. Aucun texte ne vit ici : tout vient de
 * « FAMMA → Pages → Page Livraison », et le délai du champ « Délai de
 * livraison » de la section Contact, le même que sur les fiches produit.
 *
 * La maquette montrait des tarifs par zone, une livraison express, des points
 * relais et un suivi par SMS : ce sont des données d'exemple, que la boutique
 * ne propose pas. Le gabarit garde le dessin et n'affiche que les vrais
 * services — un seul mode, offert, dans les 24 gouvernorats.
 *
 * Les coordonnées de l'encart d'aide sont celles du pied de page
 * (`famma_child_setting()`), jamais recopiées.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

get_header();

$famma_badge = famma_child_delivery_text( 'badge' );
$famma_lede  = famma_child_delivery_text( 'lede' );

$famma_highlight_icons = array(
	1 => 'truck',
	2 => 'card',
	3 => 'clock',
);

$famma_highlights = array();
foreach ( $famma_highlight_icons as $famma_index => $famma_icon ) {
	$famma_title = famma_child_delivery_text( 'highlight_' . $famma_index . '_title' );

	if ( '' !== $famma_title ) {
		$famma_highlights[] = array(
			'icon'  => $famma_icon,
			'title' => $famma_title,
			'text'  => famma_child_delivery_text( 'highlight_' . $famma_index . '_text' ),
		);
	}
}

$famma_option_title  = famma_child_delivery_text( 'option_title' );
$famma_option_sub    = famma_child_delivery_text( 'option_sub' );
$famma_option_name   = famma_child_delivery_text( 'option_name' );
$famma_option_price  = famma_child_delivery_text( 'option_price' );
$famma_option_points = famma_child_delivery_lines( 'option_points' );

$famma_zone_title = famma_child_delivery_text( 'zone_title' );
$famma_zone_sub   = famma_child_delivery_text( 'zone_sub' );
$famma_zones      = famma_child_delivery_zones();
$famma_zone_price = famma_child_delivery_text( 'zone_price' );
$famma_zone_note  = famma_child_delivery_text( 'zone_note' );

$famma_steps_title = famma_child_delivery_text( 'steps_title' );
$famma_steps       = array();
for ( $famma_index = 1; $famma_index <= 4; $famma_index++ ) {
	$famma_title = famma_child_delivery_text( 'step_' . $famma_index . '_title' );

	if ( '' !== $famma_title ) {
		$famma_steps[] = array(
			'title' => $famma_title,
			'text'  => famma_child_delivery_text( 'step_' . $famma_index . '_text' ),
		);
	}
}

$famma_faq_title = famma_child_delivery_text( 'faq_title' );
$famma_faq       = famma_child_delivery_faq();

$famma_help_title = famma_child_delivery_text( 'help_title' );
$famma_help_text  = famma_child_delivery_text( 'help_text' );
$famma_help_wa    = famma_child_delivery_text( 'help_whatsapp' );
$famma_help_shop  = famma_child_delivery_text( 'help_shop' );
$famma_wa_url     = famma_child_page_whatsapp_url();
$famma_shop_url   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

$famma_phone   = famma_child_setting( 'FAMMA_CONTACT_PHONE' );
$famma_email   = famma_child_setting( 'FAMMA_CONTACT_EMAIL' );
$famma_address = famma_child_setting( 'FAMMA_CONTACT_ADDRESS' );
?>

<<?php echo esc_attr( famma_child_content_tag() ); ?> class="famma-container famma-page famma-delivery">

	<?php famma_child_page_crumbs(); ?>

	<header class="famma-delivery__hero">
		<?php if ( '' !== $famma_badge ) : ?>
			<p class="famma-delivery__badge">
				<?php famma_child_the_icon( 'truck' ); ?>
				<span><?php echo esc_html( $famma_badge ); ?></span>
			</p>
		<?php endif; ?>
		<h1 class="famma-page__title"><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( '' !== $famma_lede ) : ?>
			<p class="famma-page__lead"><?php echo esc_html( $famma_lede ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( array() !== $famma_highlights ) : ?>
		<ul class="famma-delivery__highlights">
			<?php foreach ( $famma_highlights as $famma_item ) : ?>
				<li class="famma-delivery__card">
					<?php famma_child_the_icon( $famma_item['icon'], 'famma-delivery__card-icon' ); ?>
					<h2 class="famma-delivery__card-title"><?php echo esc_html( $famma_item['title'] ); ?></h2>
					<?php if ( '' !== $famma_item['text'] ) : ?>
						<p class="famma-delivery__card-text"><?php echo esc_html( $famma_item['text'] ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( '' !== $famma_option_title && '' !== $famma_option_name ) : ?>
		<section class="famma-delivery__section" aria-labelledby="famma-delivery-option">
			<h2 class="famma-delivery__heading" id="famma-delivery-option"><?php echo esc_html( $famma_option_title ); ?></h2>
			<?php if ( '' !== $famma_option_sub ) : ?>
				<p class="famma-delivery__sub"><?php echo esc_html( $famma_option_sub ); ?></p>
			<?php endif; ?>
			<div class="famma-delivery__option">
				<div class="famma-delivery__option-head">
					<h3 class="famma-delivery__option-name"><?php echo esc_html( $famma_option_name ); ?></h3>
					<?php if ( '' !== $famma_option_price ) : ?>
						<p class="famma-delivery__option-price"><?php echo esc_html( $famma_option_price ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( array() !== $famma_option_points ) : ?>
					<ul class="famma-delivery__points">
						<?php foreach ( $famma_option_points as $famma_point ) : ?>
							<li class="famma-delivery__point">
								<?php famma_child_the_icon( 'check' ); ?>
								<span><?php echo esc_html( $famma_point ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( '' !== $famma_zone_title && array() !== $famma_zones ) : ?>
		<section class="famma-delivery__section" aria-labelledby="famma-delivery-zones">
			<h2 class="famma-delivery__heading" id="famma-delivery-zones"><?php echo esc_html( $famma_zone_title ); ?></h2>
			<?php if ( '' !== $famma_zone_sub ) : ?>
				<p class="famma-delivery__sub"><?php echo esc_html( $famma_zone_sub ); ?></p>
			<?php endif; ?>
			<table class="famma-delivery__zones">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Zone', 'famma-child' ); ?></th>
						<?php if ( '' !== $famma_zone_price ) : ?>
							<th scope="col" class="famma-delivery__zone-price"><?php esc_html_e( 'Frais', 'famma-child' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $famma_zones as $famma_zone ) : ?>
						<tr>
							<th scope="row">
								<span class="famma-delivery__zone-name"><?php echo esc_html( $famma_zone['zone'] ); ?></span>
								<?php if ( '' !== $famma_zone['detail'] ) : ?>
									<span class="famma-delivery__zone-detail"><?php echo esc_html( $famma_zone['detail'] ); ?></span>
								<?php endif; ?>
							</th>
							<?php if ( '' !== $famma_zone_price ) : ?>
								<td class="famma-delivery__zone-price"><?php echo esc_html( $famma_zone_price ); ?></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( '' !== $famma_zone_note ) : ?>
				<p class="famma-note">
					<?php famma_child_the_icon( 'info' ); ?>
					<span><?php echo esc_html( $famma_zone_note ); ?></span>
				</p>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( '' !== $famma_steps_title && array() !== $famma_steps ) : ?>
		<section class="famma-delivery__section" aria-labelledby="famma-delivery-steps">
			<h2 class="famma-delivery__heading" id="famma-delivery-steps"><?php echo esc_html( $famma_steps_title ); ?></h2>
			<ol class="famma-delivery__steps">
				<?php foreach ( $famma_steps as $famma_step ) : ?>
					<li class="famma-delivery__step">
						<h3 class="famma-delivery__step-title"><?php echo esc_html( $famma_step['title'] ); ?></h3>
						<?php if ( '' !== $famma_step['text'] ) : ?>
							<p class="famma-delivery__step-text"><?php echo esc_html( $famma_step['text'] ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endif; ?>

	<div class="famma-delivery__split">
		<?php if ( '' !== $famma_faq_title && array() !== $famma_faq ) : ?>
			<section class="famma-delivery__faq" aria-labelledby="famma-delivery-faq">
				<h2 class="famma-delivery__heading" id="famma-delivery-faq"><?php echo esc_html( $famma_faq_title ); ?></h2>
				<div class="famma-qa">
					<?php foreach ( $famma_faq as $famma_rank => $famma_pair ) : ?>
						<details class="famma-qa__item"<?php echo 0 === $famma_rank ? ' open' : ''; ?>>
							<summary class="famma-qa__question">
								<span><?php echo esc_html( $famma_pair['question'] ); ?></span>
								<?php famma_child_the_icon( 'chevron', 'famma-qa__chevron' ); ?>
							</summary>
							<p class="famma-qa__answer"><?php echo esc_html( $famma_pair['answer'] ); ?></p>
						</details>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( '' !== $famma_help_title ) : ?>
			<aside class="famma-help" aria-labelledby="famma-delivery-help">
				<span class="famma-help__icon" aria-hidden="true"><?php famma_child_the_icon( 'chat' ); ?></span>
				<h2 class="famma-help__title" id="famma-delivery-help"><?php echo esc_html( $famma_help_title ); ?></h2>
				<?php if ( '' !== $famma_help_text ) : ?>
					<p class="famma-help__text"><?php echo esc_html( $famma_help_text ); ?></p>
				<?php endif; ?>
				<div class="famma-help__actions">
					<?php if ( '' !== $famma_wa_url && '' !== $famma_help_wa ) : ?>
						<a class="famma-button famma-button--whatsapp" href="<?php echo esc_url( $famma_wa_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $famma_help_wa ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $famma_help_shop ) : ?>
						<a class="famma-button famma-button--outline" href="<?php echo esc_url( $famma_shop_url ); ?>"><?php echo esc_html( $famma_help_shop ); ?></a>
					<?php endif; ?>
				</div>
				<?php if ( '' !== $famma_phone || '' !== $famma_email || '' !== $famma_address ) : ?>
					<ul class="famma-help__contacts">
						<?php if ( '' !== $famma_phone ) : ?>
							<li><?php famma_child_the_icon( 'phone' ); ?><a href="<?php echo esc_url( famma_child_tel_uri( $famma_phone ) ); ?>"><?php echo esc_html( $famma_phone ); ?></a></li>
						<?php endif; ?>
						<?php if ( '' !== $famma_email ) : ?>
							<li><?php famma_child_the_icon( 'mail' ); ?><a href="<?php echo esc_url( 'mailto:' . $famma_email ); ?>"><?php echo esc_html( $famma_email ); ?></a></li>
						<?php endif; ?>
						<?php if ( '' !== $famma_address ) : ?>
							<li><?php famma_child_the_icon( 'pin' ); ?><span><?php echo esc_html( $famma_address ); ?></span></li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
			</aside>
		<?php endif; ?>
	</div>

	<?php famma_child_page_editorial(); ?>

</<?php echo esc_attr( famma_child_content_tag() ); ?>>

<?php
get_footer();
