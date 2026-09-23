<?php
/**
 * FAMMA — page « Contact ».
 *
 * Aucun formulaire : en Tunisie, un client qui a une question écrit sur
 * WhatsApp ou appelle. Un formulaire aurait ajouté une dépendance, une surface
 * de spam et une attente de réponse par e-mail que personne ne surveille.
 * WhatsApp porte donc l'appel à l'action principal, le téléphone et l'e-mail
 * suivent en canaux directs.
 *
 * Comme pour « À propos », aucun texte ne vit ici : tout vient de
 * « FAMMA → Pages ». Les coordonnées passent par `famma_child_setting()`, la
 * même source que le pied de page — la constante `.env` du serveur d'abord,
 * le champ d'administration en repli.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

get_header();

$famma_hero_title = famma_child_page_text( 'contact', 'hero_title' );
$famma_hero_text  = famma_child_page_text( 'contact', 'hero_text' );

$famma_wa_url   = famma_child_page_whatsapp_url();
$famma_wa_title = famma_child_page_text( 'contact', 'whatsapp_title' );
$famma_wa_text  = famma_child_page_text( 'contact', 'whatsapp_text' );
$famma_wa_label = famma_child_page_text( 'contact', 'whatsapp_label' );

$famma_phone   = famma_child_setting( 'FAMMA_CONTACT_PHONE' );
$famma_email   = famma_child_setting( 'FAMMA_CONTACT_EMAIL' );
$famma_address = famma_child_setting( 'FAMMA_CONTACT_ADDRESS' );

$famma_hours_title = famma_child_page_text( 'contact', 'hours_title' );
$famma_hours       = famma_child_page_lines( 'contact', 'hours_text' );

$famma_coverage_title = famma_child_page_text( 'contact', 'coverage_title' );
$famma_coverage_text  = famma_child_page_text( 'contact', 'coverage_text' );

/*
 * `tel:` n'accepte pas d'espace, et un numéro local à 8 chiffres est tunisien :
 * la normalisation (indicatif +216 ajouté) est celle de famma-core, la même que
 * pour WhatsApp. Le numéro affiché, lui, reste tel que saisi.
 */
$famma_phone_href = famma_child_tel_uri( $famma_phone );
?>

<<?php echo esc_attr( famma_child_content_tag() ); ?> class="famma-container famma-page famma-contact-page">

	<section class="famma-page__hero">
		<?php if ( '' !== $famma_hero_title ) : ?>
			<h1 class="famma-page__title"><?php echo esc_html( $famma_hero_title ); ?></h1>
		<?php else : ?>
			<h1 class="famma-page__title"><?php echo esc_html( get_the_title() ); ?></h1>
			<?php famma_child_page_todo( __( 'Titre principal', 'famma-child' ) ); ?>
		<?php endif; ?>

		<?php if ( '' !== $famma_hero_text ) : ?>
			<p class="famma-page__lead"><?php echo esc_html( $famma_hero_text ); ?></p>
		<?php endif; ?>
	</section>

	<?php if ( '' !== $famma_wa_url ) : ?>
		<section class="famma-page__section famma-contact-page__whatsapp">
			<span class="famma-contact-card__icon" aria-hidden="true">
				<?php famma_child_the_icon( 'chat' ); ?>
			</span>
			<?php if ( '' !== $famma_wa_title ) : ?>
				<h2 class="famma-page__heading"><?php echo esc_html( $famma_wa_title ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $famma_wa_text ) : ?>
				<p class="famma-page__text"><?php echo esc_html( $famma_wa_text ); ?></p>
			<?php endif; ?>
			<a
				class="famma-button famma-button--primary famma-contact-page__cta"
				href="<?php echo esc_url( $famma_wa_url ); ?>"
				target="_blank"
				rel="noopener noreferrer"
			>
				<?php echo esc_html( '' !== $famma_wa_label ? $famma_wa_label : __( 'Ouvrir WhatsApp', 'famma-child' ) ); ?>
			</a>
		</section>
	<?php else : ?>
		<?php famma_child_page_todo( __( 'Numéro WhatsApp', 'famma-child' ) ); ?>
	<?php endif; ?>

	<?php if ( '' !== $famma_phone || '' !== $famma_email || '' !== $famma_address ) : ?>
		<section class="famma-page__section famma-contact-page__channels">
			<ul class="famma-contact-cards">
				<?php if ( '' !== $famma_phone ) : ?>
					<li class="famma-contact-card">
						<span class="famma-contact-card__icon" aria-hidden="true">
							<?php famma_child_the_icon( 'phone' ); ?>
						</span>
						<h2 class="famma-contact-card__title"><?php esc_html_e( 'Par téléphone', 'famma-child' ); ?></h2>
						<a class="famma-contact-card__value" href="<?php echo esc_url( $famma_phone_href ); ?>">
							<?php echo esc_html( $famma_phone ); ?>
						</a>
					</li>
				<?php endif; ?>

				<?php if ( '' !== $famma_email ) : ?>
					<li class="famma-contact-card">
						<span class="famma-contact-card__icon" aria-hidden="true">
							<?php famma_child_the_icon( 'mail' ); ?>
						</span>
						<h2 class="famma-contact-card__title"><?php esc_html_e( 'Par e-mail', 'famma-child' ); ?></h2>
						<a class="famma-contact-card__value" href="<?php echo esc_url( 'mailto:' . $famma_email ); ?>">
							<?php echo esc_html( $famma_email ); ?>
						</a>
					</li>
				<?php endif; ?>

				<?php if ( '' !== $famma_address ) : ?>
					<li class="famma-contact-card">
						<span class="famma-contact-card__icon" aria-hidden="true">
							<?php famma_child_the_icon( 'pin' ); ?>
						</span>
						<h2 class="famma-contact-card__title"><?php esc_html_e( 'Où nous sommes', 'famma-child' ); ?></h2>
						<p class="famma-contact-card__value"><?php echo esc_html( $famma_address ); ?></p>
					</li>
				<?php endif; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( '' === $famma_phone ) : ?>
		<?php famma_child_page_todo( __( 'Téléphone', 'famma-child' ) ); ?>
	<?php endif; ?>

	<?php if ( '' === $famma_email ) : ?>
		<?php famma_child_page_todo( __( 'Adresse e-mail', 'famma-child' ) ); ?>
	<?php endif; ?>

	<?php if ( '' === $famma_address ) : ?>
		<?php famma_child_page_todo( __( 'Adresse / ville', 'famma-child' ) ); ?>
	<?php endif; ?>

	<?php if ( array() !== $famma_hours ) : ?>
		<section class="famma-page__section famma-contact-page__hours">
			<span class="famma-contact-card__icon" aria-hidden="true">
				<?php famma_child_the_icon( 'clock' ); ?>
			</span>
			<?php if ( '' !== $famma_hours_title ) : ?>
				<h2 class="famma-page__heading"><?php echo esc_html( $famma_hours_title ); ?></h2>
			<?php endif; ?>
			<ul class="famma-hours">
				<?php foreach ( $famma_hours as $famma_slot ) : ?>
					<li class="famma-hours__item"><?php echo esc_html( $famma_slot ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php else : ?>
		<?php famma_child_page_todo( __( 'Horaires', 'famma-child' ) ); ?>
	<?php endif; ?>

	<?php if ( '' !== $famma_coverage_text ) : ?>
		<section class="famma-page__section famma-contact-page__coverage">
			<span class="famma-contact-card__icon" aria-hidden="true">
				<?php famma_child_the_icon( 'truck' ); ?>
			</span>
			<?php if ( '' !== $famma_coverage_title ) : ?>
				<h2 class="famma-page__heading"><?php echo esc_html( $famma_coverage_title ); ?></h2>
			<?php endif; ?>
			<p class="famma-page__text"><?php echo esc_html( $famma_coverage_text ); ?></p>
		</section>
	<?php endif; ?>

	<?php famma_child_page_editorial(); ?>

</<?php echo esc_attr( famma_child_content_tag() ); ?>>

<?php
get_footer();
