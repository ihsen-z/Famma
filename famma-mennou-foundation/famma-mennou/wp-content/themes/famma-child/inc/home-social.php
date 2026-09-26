<?php
/**
 * Accueil — bandeau newsletter et section « Ils nous font confiance ».
 *
 * Maquette « FAMMA Accueil ». Aucun texte n'est écrit ici : titres, libellés,
 * mentions, chiffre et témoignages viennent de FAMMA → Pages → Page d'accueil
 * (`Famma\Core\Home_Content`). Sans le plugin, les deux sections se masquent.
 *
 * L'inscription est traitée par famma-core (`Famma\Core\Newsletter`) : le
 * thème ne fait que dessiner le formulaire et le message de retour.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Texte de l'accueil saisi dans l'administration, dans la langue de la page.
 *
 * @param string $key Clé du champ (voir `Famma\Core\Home_Content::schema()`).
 * @return string Chaîne vide si le champ est vide ou le plugin absent.
 */
function famma_child_home_copy( string $key ): string {
	if ( ! class_exists( '\Famma\Core\Home_Content' ) ) {
		return '';
	}

	return \Famma\Core\Home_Content::text( $key );
}

/**
 * Message affiché après une tentative d'inscription.
 *
 * Le résultat arrive par l'URL (`?famma_nl=…`) après la redirection de
 * `admin-post.php` : aucune écriture n'a lieu ici, seule une clé connue est
 * acceptée.
 *
 * @return array{type: string, text: string}|null
 */
function famma_child_newsletter_feedback(): ?array {
	if ( ! class_exists( '\Famma\Core\Newsletter' ) ) {
		return null;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule d'un statut d'affichage ; l'écriture, elle, a vérifié son nonce.
	$status = isset( $_GET[ \Famma\Core\Newsletter::QUERY ] ) ? sanitize_key( wp_unslash( $_GET[ \Famma\Core\Newsletter::QUERY ] ) ) : '';

	switch ( $status ) {
		case 'ok':
			$text = famma_child_home_copy( 'nl_success' );
			return array(
				'type' => 'success',
				'text' => '' !== $text ? $text : __( 'Merci ! Votre inscription est enregistrée.', 'famma-child' ),
			);
		case 'invalid':
			return array(
				'type' => 'error',
				'text' => __( 'Cette adresse e-mail ne semble pas valide. Vérifiez-la et réessayez.', 'famma-child' ),
			);
		case 'expired':
		case 'busy':
		case 'error':
			return array(
				'type' => 'error',
				'text' => __( 'L’inscription n’a pas abouti. Réessayez dans un instant.', 'famma-child' ),
			);
	}

	return null;
}

/**
 * Bandeau newsletter.
 *
 * Masqué si le libellé du bouton est vidé dans l'administration : c'est
 * l'interrupteur de la section.
 *
 * @return void
 */
function famma_child_home_newsletter(): void {
	if ( ! class_exists( '\Famma\Core\Newsletter' ) ) {
		return;
	}

	$button = famma_child_home_copy( 'nl_button' );

	if ( '' === $button ) {
		return;
	}

	$title       = famma_child_home_copy( 'nl_title' );
	$text        = famma_child_home_copy( 'nl_text' );
	$placeholder = famma_child_home_copy( 'nl_placeholder' );
	$note        = famma_child_home_copy( 'nl_note' );
	$feedback    = famma_child_newsletter_feedback();
	?>
	<section class="famma-home__section famma-newsletter" id="famma-newsletter" aria-labelledby="famma-newsletter-title">
		<div class="famma-newsletter__intro">
			<?php famma_child_the_icon( 'gift', 'famma-newsletter__icon' ); ?>
			<div class="famma-newsletter__text">
				<?php if ( '' !== $title ) : ?>
					<h2 class="famma-newsletter__title" id="famma-newsletter-title" <?php echo famma_child_text_dir_attrs( $title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributs échappés par la fonction. ?>><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>
				<?php if ( '' !== $text ) : ?>
					<p class="famma-newsletter__sub"><?php echo esc_html( $text ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<?php wp_enqueue_script( \Famma\Core\Newsletter::SCRIPT ); ?>
		<form class="famma-newsletter__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" novalidate data-famma-nl-nonce-url="<?php echo esc_url( \Famma\Core\Newsletter::nonce_endpoint() ); ?>" data-famma-nl-nonce-field="<?php echo esc_attr( \Famma\Core\Newsletter::NONCE ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( \Famma\Core\Newsletter::ACTION ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( \Famma\Core\Newsletter::FIELD_LANG ); ?>" value="<?php echo esc_attr( is_rtl() ? 'ar' : 'fr' ); ?>" />
			<?php wp_nonce_field( \Famma\Core\Newsletter::ACTION, \Famma\Core\Newsletter::NONCE ); ?>

			<?php /* Pot de miel : hors écran et hors tabulation, un humain ne le voit jamais. */ ?>
			<p class="famma-newsletter__trap" aria-hidden="true">
				<label for="famma-nl-website"><?php esc_html_e( 'Ne pas remplir ce champ', 'famma-child' ); ?></label>
				<input type="text" id="famma-nl-website" name="<?php echo esc_attr( \Famma\Core\Newsletter::FIELD_TRAP ); ?>" value="" tabindex="-1" autocomplete="off" />
			</p>

			<div class="famma-newsletter__row">
				<label class="screen-reader-text" for="famma-nl-email"><?php esc_html_e( 'Adresse e-mail', 'famma-child' ); ?></label>
				<input
					class="famma-newsletter__input"
					type="email"
					id="famma-nl-email"
					name="<?php echo esc_attr( \Famma\Core\Newsletter::FIELD_EMAIL ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>"
					autocomplete="email"
					inputmode="email"
					required
				/>
				<button class="famma-newsletter__button" type="submit"><?php echo esc_html( $button ); ?></button>
			</div>

			<?php if ( null !== $feedback ) : ?>
				<p class="famma-newsletter__feedback is-<?php echo esc_attr( $feedback['type'] ); ?>" role="<?php echo 'error' === $feedback['type'] ? 'alert' : 'status'; ?>"><?php echo esc_html( $feedback['text'] ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $note ) : ?>
				<p class="famma-newsletter__note"><?php echo esc_html( $note ); ?></p>
			<?php endif; ?>
		</form>
	</section>
	<?php
}

/**
 * Section « Ils nous font confiance ».
 *
 * Trois sources, toutes réelles, et la section n'apparaît que si l'une
 * d'elles a quelque chose à montrer :
 * - le chiffre saisi par le propriétaire (ex. nombre de clients) ;
 * - la note moyenne **calculée** sur les avis WooCommerce approuvés — jamais
 *   saisie à la main ;
 * - les témoignages saisis par le propriétaire.
 *
 * La maquette affichait « + 2 500 », « 4.8/5 » et trois avis signés : rien de
 * tout cela n'est repris par défaut (§58).
 *
 * @return void
 */
function famma_child_home_reviews(): void {
	$stats        = function_exists( 'famma_child_home_review_stats' ) ? famma_child_home_review_stats() : array(
		'count'   => 0,
		'average' => 0.0,
	);
	$stat_value   = famma_child_home_copy( 'trust_stat_value' );
	$stat_label   = famma_child_home_copy( 'trust_stat_label' );
	$testimonials = class_exists( '\Famma\Core\Home_Content' ) ? \Famma\Core\Home_Content::testimonials() : array();
	$has_rating   = $stats['count'] > 0;

	if ( '' === $stat_value && ! $has_rating && empty( $testimonials ) ) {
		return;
	}

	$title = famma_child_home_copy( 'trust_title' );
	?>
	<section class="famma-home__section">
		<?php if ( '' !== $title ) : ?>
			<?php famma_child_home_title( $title ); ?>
		<?php endif; ?>

		<div class="famma-trust-grid">
			<?php if ( '' !== $stat_value || $has_rating ) : ?>
				<div class="famma-trust-stats">
					<?php if ( '' !== $stat_value ) : ?>
						<div class="famma-trust-stats__cell">
							<p class="famma-trust-stats__value"><?php echo esc_html( $stat_value ); ?></p>
							<?php if ( '' !== $stat_label ) : ?>
								<p class="famma-trust-stats__label"><?php echo esc_html( $stat_label ); ?></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( $has_rating ) : ?>
						<div class="famma-trust-stats__cell">
							<p class="famma-trust-stats__value"><?php echo esc_html( number_format_i18n( $stats['average'], 1 ) ); ?><span class="famma-trust-stats__max">/5</span></p>
							<?php echo wp_kses_post( wc_get_rating_html( $stats['average'], $stats['count'] ) ); ?>
							<p class="famma-trust-stats__label">
								<?php
								printf(
									/* translators: %s: nombre d'avis. */
									esc_html( _n( 'Basé sur %s avis client', 'Basé sur %s avis clients', $stats['count'], 'famma-child' ) ),
									esc_html( number_format_i18n( $stats['count'] ) )
								);
								?>
							</p>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php foreach ( $testimonials as $testimonial ) : ?>
				<figure class="famma-testimonial">
					<?php famma_child_the_icon( 'quote', 'famma-testimonial__mark' ); ?>
					<blockquote class="famma-testimonial__text" <?php echo famma_child_text_dir_attrs( $testimonial['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributs échappés par la fonction. ?>>
						<p><?php echo esc_html( $testimonial['text'] ); ?></p>
					</blockquote>
					<?php if ( '' !== $testimonial['name'] ) : ?>
						<figcaption class="famma-testimonial__name"><?php echo esc_html( $testimonial['name'] ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
