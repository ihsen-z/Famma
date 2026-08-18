<?php
/**
 * FAMMA — pied de page partagé.
 *
 * Cinq colonnes en `auto-fit` : la grille se réorganise seule du mobile au
 * grand écran, et une colonne non configurée disparaît sans laisser de trou.
 *
 * Le bouton WhatsApp flottant n'est pas rendu ici : il appartient à
 * `famma-core`, qui l'accroche à `wp_footer` et le pilote depuis `.env`.
 * Le dupliquer donnerait deux boutons.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

printf( '</%s><!-- .famma-content -->', esc_html( famma_child_content_tag() ) );
?>

<footer class="famma-footer">
	<div class="famma-container famma-footer__grid">
		<?php
		famma_child_footer_brand();
		famma_child_footer_menu_col( 'primary', __( 'Navigation', 'famma-child' ) );
		famma_child_footer_menu_col( 'famma_footer_info', __( 'Informations', 'famma-child' ) );
		famma_child_footer_help();
		famma_child_footer_cod();
		?>
	</div>
	<?php famma_child_footer_bottom(); ?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
