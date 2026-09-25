<?php
/**
 * FAMMA — page « Retours », selon la maquette « FAMMA Retours Echanges ».
 *
 * Pastille, chiffres clés, étapes, retours acceptés et refusés, tableau par
 * situation, formulaire de demande, conseils d'emballage, encart d'aide et
 * questions fréquentes. Aucun texte ne vit ici : tout vient de
 * « FAMMA → Pages → Page Retours ».
 *
 * Chaque bloc qui porte un engagement (délais, frais, remboursement,
 * conditions) est livré vide par le plugin et n'apparaît qu'une fois rempli
 * par le propriétaire : la maquette annonçait une politique que la boutique
 * n'a pas encore fixée. Voir `Famma\Core\Returns_Content`.
 *
 * Le formulaire poste vers cette page ; le plugin compose le message et ouvre
 * WhatsApp. Il n'est affiché que si un numéro WhatsApp est configuré.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

get_header();

$famma_badge  = famma_child_returns_text( 'badge' );
$famma_lede   = famma_child_returns_text( 'lede' );
$famma_notice = famma_child_returns_text( 'notice' );

$famma_highlights = array();
for ( $famma_index = 1; $famma_index <= 4; $famma_index++ ) {
	$famma_title = famma_child_returns_text( 'highlight_' . $famma_index . '_title' );

	if ( '' !== $famma_title ) {
		$famma_highlights[] = array(
			'value' => famma_child_returns_text( 'highlight_' . $famma_index . '_value' ),
			'title' => $famma_title,
			'text'  => famma_child_returns_text( 'highlight_' . $famma_index . '_text' ),
		);
	}
}

$famma_steps_title = famma_child_returns_text( 'steps_title' );
$famma_steps_sub   = famma_child_returns_text( 'steps_sub' );
$famma_steps       = array();
for ( $famma_index = 1; $famma_index <= 4; $famma_index++ ) {
	$famma_title = famma_child_returns_text( 'step_' . $famma_index . '_title' );

	if ( '' !== $famma_title ) {
		$famma_steps[] = array(
			'title' => $famma_title,
			'text'  => famma_child_returns_text( 'step_' . $famma_index . '_text' ),
		);
	}
}

$famma_rules = array(
	'ok' => array(
		'title' => famma_child_returns_text( 'ok_title' ),
		'items' => famma_child_returns_lines( 'ok_items' ),
		'icon'  => 'check',
	),
	'ko' => array(
		'title' => famma_child_returns_text( 'ko_title' ),
		'items' => famma_child_returns_lines( 'ko_items' ),
		'icon'  => 'close',
	),
);
$famma_rules = array_filter(
	$famma_rules,
	static function ( array $rule ): bool {
		return '' !== $rule['title'] && array() !== $rule['items'];
	}
);

$famma_cases_title = famma_child_returns_text( 'cases_title' );
$famma_cases_head  = array_pad( array_map( 'trim', explode( '|', famma_child_returns_text( 'cases_head' ), 4 ) ), 4, '' );
$famma_cases       = famma_child_returns_rows( 'cases', 4 );

$famma_form_title = famma_child_returns_text( 'form_title' );
$famma_has_form   = '' !== $famma_form_title && '' !== famma_child_whatsapp_number();
$famma_reasons    = famma_child_returns_lines( 'form_reasons' );
$famma_outcomes   = famma_child_returns_rows( 'form_outcomes', 2 );

$famma_pack_title = famma_child_returns_text( 'pack_title' );
$famma_pack       = famma_child_returns_lines( 'pack_items' );

$famma_help_title = famma_child_returns_text( 'help_title' );
$famma_help_text  = famma_child_returns_text( 'help_text' );
$famma_help_wa    = famma_child_returns_text( 'help_whatsapp' );
$famma_help_terms = famma_child_returns_text( 'help_terms' );
$famma_hours      = famma_child_page_lines( 'contact', 'hours_text' );
$famma_wa_url     = famma_child_page_whatsapp_url();
$famma_terms_page = get_page_by_path( 'conditions-generales' );

$famma_faq_title = famma_child_returns_text( 'faq_title' );
$famma_faq       = famma_child_returns_faq();

$famma_has_aside = ( '' !== $famma_pack_title && array() !== $famma_pack ) || '' !== $famma_help_title;
?>

<<?php echo esc_attr( famma_child_content_tag() ); ?> class="famma-container famma-page famma-delivery famma-returns">

	<?php famma_child_page_crumbs(); ?>

	<header class="famma-delivery__hero">
		<?php if ( '' !== $famma_badge ) : ?>
			<p class="famma-delivery__badge famma-returns__badge">
				<?php famma_child_the_icon( 'return' ); ?>
				<span><?php echo esc_html( $famma_badge ); ?></span>
			</p>
		<?php endif; ?>
		<h1 class="famma-page__title"><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( '' !== $famma_lede ) : ?>
			<p class="famma-page__lead"><?php echo esc_html( $famma_lede ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $famma_notice ) : ?>
			<p class="famma-note famma-returns__notice">
				<?php famma_child_the_icon( 'info' ); ?>
				<span><?php echo esc_html( $famma_notice ); ?></span>
			</p>
		<?php endif; ?>
	</header>

	<?php if ( array() !== $famma_highlights ) : ?>
		<ul class="famma-returns__highlights">
			<?php foreach ( $famma_highlights as $famma_item ) : ?>
				<li class="famma-returns__highlight">
					<?php if ( '' !== $famma_item['value'] ) : ?>
						<p class="famma-returns__value"><?php echo esc_html( $famma_item['value'] ); ?></p>
					<?php endif; ?>
					<h2 class="famma-returns__highlight-title"><?php echo esc_html( $famma_item['title'] ); ?></h2>
					<?php if ( '' !== $famma_item['text'] ) : ?>
						<p class="famma-returns__highlight-text"><?php echo esc_html( $famma_item['text'] ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( '' !== $famma_steps_title && array() !== $famma_steps ) : ?>
		<section class="famma-delivery__section" aria-labelledby="famma-returns-steps">
			<h2 class="famma-delivery__heading" id="famma-returns-steps"><?php echo esc_html( $famma_steps_title ); ?></h2>
			<?php if ( '' !== $famma_steps_sub ) : ?>
				<p class="famma-delivery__sub"><?php echo esc_html( $famma_steps_sub ); ?></p>
			<?php endif; ?>
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

	<?php if ( array() !== $famma_rules ) : ?>
		<div class="famma-delivery__section famma-returns__rules">
			<?php foreach ( $famma_rules as $famma_kind => $famma_rule ) : ?>
				<section class="famma-returns__rule famma-returns__rule--<?php echo esc_attr( $famma_kind ); ?>" aria-labelledby="famma-returns-<?php echo esc_attr( $famma_kind ); ?>">
					<h2 class="famma-returns__rule-title" id="famma-returns-<?php echo esc_attr( $famma_kind ); ?>">
						<?php famma_child_the_icon( $famma_rule['icon'] ); ?>
						<span><?php echo esc_html( $famma_rule['title'] ); ?></span>
					</h2>
					<ul class="famma-returns__rule-list">
						<?php foreach ( $famma_rule['items'] as $famma_line ) : ?>
							<li><?php echo esc_html( $famma_line ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $famma_cases_title && array() !== $famma_cases ) : ?>
		<section class="famma-delivery__section" aria-labelledby="famma-returns-cases">
			<h2 class="famma-delivery__heading" id="famma-returns-cases"><?php echo esc_html( $famma_cases_title ); ?></h2>
			<div class="famma-returns__table-wrap">
				<table class="famma-returns__cases">
					<thead>
						<tr>
							<?php foreach ( $famma_cases_head as $famma_rank => $famma_label ) : ?>
								<th scope="col"<?php echo 3 === $famma_rank ? ' class="famma-returns__end"' : ''; ?>><?php echo esc_html( $famma_label ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $famma_cases as $famma_row ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $famma_row[0] ); ?></th>
								<td><?php echo esc_html( $famma_row[1] ); ?></td>
								<td class="famma-returns__cost"><?php echo esc_html( $famma_row[2] ); ?></td>
								<td class="famma-returns__end"><?php echo esc_html( $famma_row[3] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $famma_has_form || $famma_has_aside ) : ?>
		<div class="famma-delivery__section famma-returns__split<?php echo $famma_has_form ? '' : ' famma-returns__split--aside'; ?>">
			<?php if ( $famma_has_form ) : ?>
				<section class="famma-returns__request" aria-labelledby="famma-returns-form">
					<h2 class="famma-delivery__heading" id="famma-returns-form"><?php echo esc_html( $famma_form_title ); ?></h2>
					<?php if ( '' !== famma_child_returns_text( 'form_sub' ) ) : ?>
						<p class="famma-delivery__sub"><?php echo esc_html( famma_child_returns_text( 'form_sub' ) ); ?></p>
					<?php endif; ?>

					<?php
					/*
					 * Pas d'attribut `action` : la demande repart vers cette page,
					 * dans sa langue. `target="_blank"` : WhatsApp s'ouvre à côté et
					 * la page reste là, formulaire rempli.
					 */
					?>
					<form class="famma-returns__form" method="post" target="_blank" rel="noopener">
						<input type="hidden" name="famma_return_request" value="1" />

						<div class="famma-returns__pair">
							<p class="famma-returns__field">
								<label for="famma-return-order"><?php echo esc_html( famma_child_returns_text( 'form_order' ) ); ?></label>
								<input type="text" id="famma-return-order" name="famma_return[order]" autocomplete="off" placeholder="<?php echo esc_attr( famma_child_returns_text( 'form_order_ph' ) ); ?>" />
							</p>
							<p class="famma-returns__field">
								<label for="famma-return-phone"><?php echo esc_html( famma_child_returns_text( 'form_phone' ) ); ?></label>
								<input type="tel" id="famma-return-phone" name="famma_return[phone]" autocomplete="tel" inputmode="tel" placeholder="<?php echo esc_attr( famma_child_returns_text( 'form_phone_ph' ) ); ?>" />
							</p>
						</div>

						<?php if ( array() !== $famma_reasons ) : ?>
							<fieldset class="famma-returns__choice">
								<legend><?php echo esc_html( famma_child_returns_text( 'form_reason' ) ); ?></legend>
								<div class="famma-returns__chips">
									<?php foreach ( $famma_reasons as $famma_rank => $famma_reason ) : ?>
										<label class="famma-returns__chip">
											<input type="radio" name="famma_return[reason]" value="<?php echo esc_attr( (string) $famma_rank ); ?>"<?php checked( 0, $famma_rank ); ?> />
											<span><?php echo esc_html( $famma_reason ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</fieldset>
						<?php endif; ?>

						<?php if ( array() !== $famma_outcomes ) : ?>
							<fieldset class="famma-returns__choice">
								<legend><?php echo esc_html( famma_child_returns_text( 'form_want' ) ); ?></legend>
								<div class="famma-returns__options">
									<?php foreach ( $famma_outcomes as $famma_rank => $famma_outcome ) : ?>
										<label class="famma-returns__option">
											<input type="radio" name="famma_return[outcome]" value="<?php echo esc_attr( (string) $famma_rank ); ?>"<?php checked( 0, $famma_rank ); ?> />
											<span class="famma-returns__option-body">
												<span class="famma-returns__option-label"><?php echo esc_html( $famma_outcome[0] ); ?></span>
												<?php if ( '' !== $famma_outcome[1] ) : ?>
													<span class="famma-returns__option-sub"><?php echo esc_html( $famma_outcome[1] ); ?></span>
												<?php endif; ?>
											</span>
										</label>
									<?php endforeach; ?>
								</div>
							</fieldset>
						<?php endif; ?>

						<p class="famma-returns__field">
							<label for="famma-return-note"><?php echo esc_html( famma_child_returns_text( 'form_note' ) ); ?></label>
							<textarea id="famma-return-note" name="famma_return[note]" rows="3" maxlength="800" placeholder="<?php echo esc_attr( famma_child_returns_text( 'form_note_ph' ) ); ?>"></textarea>
						</p>

						<button type="submit" class="famma-button famma-button--primary famma-returns__submit"><?php echo esc_html( famma_child_returns_text( 'form_submit' ) ); ?></button>

						<?php if ( '' !== famma_child_returns_text( 'form_legal' ) ) : ?>
							<p class="famma-returns__legal"><?php echo esc_html( famma_child_returns_text( 'form_legal' ) ); ?></p>
						<?php endif; ?>
					</form>
				</section>
			<?php endif; ?>

			<?php if ( $famma_has_aside ) : ?>
				<div class="famma-returns__aside">
					<?php if ( '' !== $famma_pack_title && array() !== $famma_pack ) : ?>
						<section class="famma-returns__pack" aria-labelledby="famma-returns-pack">
							<h2 class="famma-returns__pack-title" id="famma-returns-pack"><?php echo esc_html( $famma_pack_title ); ?></h2>
							<ul class="famma-returns__pack-list">
								<?php foreach ( $famma_pack as $famma_line ) : ?>
									<li><?php famma_child_the_icon( 'check' ); ?><span><?php echo esc_html( $famma_line ); ?></span></li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>

					<?php if ( '' !== $famma_help_title ) : ?>
						<aside class="famma-help" aria-labelledby="famma-returns-help">
							<span class="famma-help__icon" aria-hidden="true"><?php famma_child_the_icon( 'chat' ); ?></span>
							<h2 class="famma-help__title" id="famma-returns-help"><?php echo esc_html( $famma_help_title ); ?></h2>
							<?php if ( '' !== $famma_help_text || array() !== $famma_hours ) : ?>
								<p class="famma-help__text">
									<?php echo esc_html( $famma_help_text ); ?>
									<?php if ( array() !== $famma_hours ) : ?>
										<span class="famma-help__hours"><?php echo esc_html( implode( ' · ', $famma_hours ) ); ?></span>
									<?php endif; ?>
								</p>
							<?php endif; ?>
							<div class="famma-help__actions">
								<?php if ( '' !== $famma_wa_url && '' !== $famma_help_wa ) : ?>
									<a class="famma-button famma-button--whatsapp" href="<?php echo esc_url( $famma_wa_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $famma_help_wa ); ?></a>
								<?php endif; ?>
								<?php if ( '' !== $famma_help_terms && $famma_terms_page instanceof WP_Post && 'publish' === $famma_terms_page->post_status ) : ?>
									<a class="famma-button famma-button--outline" href="<?php echo esc_url( (string) get_permalink( $famma_terms_page ) ); ?>"><?php echo esc_html( $famma_help_terms ); ?></a>
								<?php endif; ?>
							</div>
						</aside>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $famma_faq_title && array() !== $famma_faq ) : ?>
		<section class="famma-delivery__section" aria-labelledby="famma-returns-faq">
			<h2 class="famma-delivery__heading" id="famma-returns-faq"><?php echo esc_html( $famma_faq_title ); ?></h2>
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

	<?php famma_child_page_editorial(); ?>

</<?php echo esc_attr( famma_child_content_tag() ); ?>>

<?php
get_footer();
