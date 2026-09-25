<?php
/**
 * FAMMA — page « Conditions générales de vente », selon la maquette
 * « FAMMA Conditions Generales ».
 *
 * Le texte reste celui de l'éditeur de la page : des conditions de vente
 * engagent la boutique, elles sont rédigées par le propriétaire, jamais par le
 * thème (§60). La maquette portait un texte juridique d'exemple — paiement
 * par carte, frais de 2 DT, garantie de douze mois, rétractation de sept
 * jours — qui ne décrit pas la boutique : il n'est pas repris.
 *
 * Le gabarit ne fait que mettre en page ce qui est écrit : chaque titre de
 * niveau 2 devient un article numéroté, avec son entrée au sommaire ; les
 * listes prennent la puce orange, les citations le style d'encadré. La date
 * de mise à jour est celle de la dernière modification de la page.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

get_header();

$famma_document = array(
	'intro'    => '',
	'sections' => array(),
);

while ( have_posts() ) :
	the_post();
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- filtre du cœur : blocs, shortcodes et retrait des notes de chantier.
	$famma_document = famma_child_document_sections( (string) apply_filters( 'the_content', get_the_content() ) );
endwhile;

$famma_sections = $famma_document['sections'];
$famma_updated  = get_the_modified_date();
$famma_wa_url   = famma_child_page_whatsapp_url();
$famma_hours    = famma_child_page_lines( 'contact', 'hours_text' );
$famma_delivery = get_page_by_path( 'livraison' );
?>

<<?php echo esc_attr( famma_child_content_tag() ); ?> class="famma-container famma-page famma-document">

	<?php famma_child_page_crumbs(); ?>

	<header class="famma-document__hero">
		<h1 class="famma-page__title"><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( '' !== (string) $famma_updated ) : ?>
			<p class="famma-document__meta">
				<?php
				/* translators: %s: date of the page's last modification. */
				printf( esc_html__( 'Dernière mise à jour : %s', 'famma-child' ), esc_html( (string) $famma_updated ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( '' !== trim( wp_strip_all_tags( $famma_document['intro'] ) ) ) : ?>
			<div class="famma-document__lede entry-content"><?php echo wp_kses_post( $famma_document['intro'] ); ?></div>
		<?php endif; ?>
	</header>

	<div class="famma-document__shell">

		<aside class="famma-document__aside">
			<?php if ( count( $famma_sections ) > 1 ) : ?>
				<nav class="famma-toc" aria-labelledby="famma-toc-title">
					<p class="famma-toc__title" id="famma-toc-title"><?php esc_html_e( 'Sommaire', 'famma-child' ); ?></p>
					<ol class="famma-toc__list">
						<?php foreach ( $famma_sections as $famma_section ) : ?>
							<li>
								<a class="famma-toc__link" href="#<?php echo esc_attr( $famma_section['id'] ); ?>">
									<span class="famma-toc__number" aria-hidden="true"><?php echo esc_html( $famma_section['number'] ); ?></span>
									<span><?php echo esc_html( $famma_section['title'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ol>
				</nav>
			<?php endif; ?>

			<?php if ( '' !== $famma_wa_url ) : ?>
				<div class="famma-help famma-help--compact">
					<p class="famma-help__title"><?php esc_html_e( 'Une question sur ces conditions ?', 'famma-child' ); ?></p>
					<p class="famma-help__text">
						<?php esc_html_e( 'Écrivez-nous sur WhatsApp : nous vous répondons.', 'famma-child' ); ?>
						<?php if ( array() !== $famma_hours ) : ?>
							<span class="famma-help__hours"><?php echo esc_html( implode( ' · ', $famma_hours ) ); ?></span>
						<?php endif; ?>
					</p>
					<a class="famma-button famma-button--whatsapp" href="<?php echo esc_url( $famma_wa_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Nous contacter', 'famma-child' ); ?></a>
				</div>
			<?php endif; ?>
		</aside>

		<article class="famma-document__body">
			<?php foreach ( $famma_sections as $famma_section ) : ?>
				<section class="famma-document__section" id="<?php echo esc_attr( $famma_section['id'] ); ?>">
					<h2 class="famma-document__heading">
						<span class="famma-document__number" aria-hidden="true"><?php echo esc_html( $famma_section['number'] ); ?></span>
						<span><?php echo esc_html( $famma_section['title'] ); ?></span>
					</h2>
					<div class="famma-document__text entry-content"><?php echo wp_kses_post( $famma_section['body'] ); ?></div>
				</section>
			<?php endforeach; ?>

			<?php if ( $famma_delivery instanceof WP_Post && 'publish' === $famma_delivery->post_status ) : ?>
				<p class="famma-document__next">
					<a href="<?php echo esc_url( get_permalink( $famma_delivery ) ); ?>">
						<span><?php esc_html_e( 'Consulter les conditions de livraison', 'famma-child' ); ?></span>
						<?php famma_child_the_icon( 'forward' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</article>

	</div>

</<?php echo esc_attr( famma_child_content_tag() ); ?>>

<?php
get_footer();
