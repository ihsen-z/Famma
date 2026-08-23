<?php
/**
 * FAMMA — page « À propos ».
 *
 * Ce gabarit ne contient aucun texte : chaque bloc est lu depuis
 * « FAMMA → Pages » via `inc/pages.php`. Un bloc vide s'efface pour le client
 * et laisse un rappel visible du seul propriétaire.
 *
 * L'ordre suit la logique de confiance, du plus général au plus engageant :
 * qui parle, ce que nous faisons, pourquoi commander ici, ce que nous
 * promettons, puis l'invitation au catalogue. Le contenu rédigé dans l'éditeur
 * de la page s'insère avant l'appel à l'action, comme sur l'accueil.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

get_header();

$famma_hero_title  = famma_child_page_text( 'about', 'hero_title' );
$famma_hero_text   = famma_child_page_text( 'about', 'hero_text' );
$famma_story_title = famma_child_page_text( 'about', 'story_title' );
$famma_story_text  = famma_child_page_text( 'about', 'story_text' );
$famma_commit_head = famma_child_page_text( 'about', 'commitments_title' );
$famma_commitments = famma_child_page_lines( 'about', 'commitments_text' );
$famma_cta_title   = famma_child_page_text( 'about', 'cta_title' );
$famma_cta_label   = famma_child_page_text( 'about', 'cta_label' );
$famma_shop_url    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';

/*
 * Les trois piliers, chacun avec son icône. Une paire titre/texte vide retire
 * la carte : trois cartes dont une est à moitié remplie donne l'impression d'une
 * page cassée, pas d'un contenu en cours.
 */
$famma_pillars = array();

foreach ( array(
	1 => 'card',
	2 => 'tag',
	3 => 'truck',
) as $famma_index => $famma_icon ) {
	$famma_pillar_title = famma_child_page_text( 'about', 'pillar' . $famma_index . '_title' );
	$famma_pillar_text  = famma_child_page_text( 'about', 'pillar' . $famma_index . '_text' );

	if ( '' === $famma_pillar_title && '' === $famma_pillar_text ) {
		continue;
	}

	$famma_pillars[] = array(
		'icon'  => $famma_icon,
		'title' => $famma_pillar_title,
		'text'  => $famma_pillar_text,
	);
}
?>

<<?php echo esc_attr( famma_child_content_tag() ); ?> class="famma-container famma-page famma-about">

	<section class="famma-page__hero">
		<?php if ( '' !== $famma_hero_title ) : ?>
			<h1 class="famma-page__title"><?php echo esc_html( $famma_hero_title ); ?></h1>
		<?php else : ?>
			<h1 class="famma-page__title"><?php echo esc_html( get_the_title() ); ?></h1>
			<?php famma_child_page_todo( __( 'Titre principal', 'famma-child' ) ); ?>
		<?php endif; ?>

		<?php if ( '' !== $famma_hero_text ) : ?>
			<p class="famma-page__lead"><?php echo esc_html( $famma_hero_text ); ?></p>
		<?php else : ?>
			<?php famma_child_page_todo( __( 'Accroche', 'famma-child' ) ); ?>
		<?php endif; ?>
	</section>

	<?php if ( '' !== $famma_story_text ) : ?>
		<section class="famma-page__section famma-about__story">
			<?php if ( '' !== $famma_story_title ) : ?>
				<h2 class="famma-page__heading"><?php echo esc_html( $famma_story_title ); ?></h2>
			<?php endif; ?>
			<p class="famma-page__text"><?php echo esc_html( $famma_story_text ); ?></p>
		</section>
	<?php else : ?>
		<?php famma_child_page_todo( __( 'Texte « Qui sommes-nous »', 'famma-child' ) ); ?>
	<?php endif; ?>

	<?php if ( array() !== $famma_pillars ) : ?>
		<section class="famma-page__section famma-about__pillars">
			<?php
			$famma_pillars_title = famma_child_page_text( 'about', 'pillars_title' );

			if ( '' !== $famma_pillars_title ) :
				?>
				<h2 class="famma-page__heading"><?php echo esc_html( $famma_pillars_title ); ?></h2>
			<?php endif; ?>

			<ul class="famma-pillars">
				<?php foreach ( $famma_pillars as $famma_pillar ) : ?>
					<li class="famma-pillars__item">
						<span class="famma-pillars__icon" aria-hidden="true">
							<?php famma_child_the_icon( $famma_pillar['icon'] ); ?>
						</span>
						<?php if ( '' !== $famma_pillar['title'] ) : ?>
							<h3 class="famma-pillars__title"><?php echo esc_html( $famma_pillar['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( '' !== $famma_pillar['text'] ) : ?>
							<p class="famma-pillars__text"><?php echo esc_html( $famma_pillar['text'] ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( array() !== $famma_commitments ) : ?>
		<section class="famma-page__section famma-about__commitments">
			<?php if ( '' !== $famma_commit_head ) : ?>
				<h2 class="famma-page__heading"><?php echo esc_html( $famma_commit_head ); ?></h2>
			<?php endif; ?>
			<ul class="famma-commitments">
				<?php foreach ( $famma_commitments as $famma_commitment ) : ?>
					<li class="famma-commitments__item"><?php echo esc_html( $famma_commitment ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php famma_child_page_editorial(); ?>

	<?php if ( '' !== $famma_shop_url && '' !== $famma_cta_label ) : ?>
		<section class="famma-page__section famma-page__cta">
			<?php if ( '' !== $famma_cta_title ) : ?>
				<h2 class="famma-page__heading"><?php echo esc_html( $famma_cta_title ); ?></h2>
			<?php endif; ?>
			<a class="famma-button famma-button--primary" href="<?php echo esc_url( $famma_shop_url ); ?>">
				<?php echo esc_html( $famma_cta_label ); ?>
			</a>
		</section>
	<?php endif; ?>

</<?php echo esc_attr( famma_child_content_tag() ); ?>>

<?php
get_footer();
